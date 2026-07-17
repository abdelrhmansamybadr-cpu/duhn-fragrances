<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/config/database.php';

$db      = Database::getInstance();
$success = '';
$error   = '';

// Load all settings — table uses `key` and `value` columns
$rows     = $db->query("SELECT `key`, `value` FROM `settings`")->fetchAll();
$settings = [];
foreach ($rows as $row) {
    $settings[$row['key']] = $row['value'];
}

// ── Helper: upsert a setting ──────────────────────────────────────
function saveSetting($db, $key, $value) {
    $db->prepare("INSERT INTO `settings` (`key`, `value`) VALUES (:k, :v)
                  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
       ->execute([':k' => $key, ':v' => $value]);
}

// ── Helper: sanitize hero RTE HTML (allow bold, color, font-size only) ──
function sanitizeHeroText(string $html): string {
    // Normalize block-level tags from contenteditable Enter-key to <br>
    $html = preg_replace('/<\/?(div|p|section)[^>]*>/i', '<br>', $html);
    // Allow only safe inline tags
    $html = strip_tags($html, '<b><strong><em><i><u><span><br>');
    // Remove attributes from block-style tags; sanitize span style
    $html = preg_replace('/<(b|strong|em|i|u|br)(\s[^>]*)?>/i', '<$1>', $html);
    $html = preg_replace_callback('/<span([^>]*)>/i', function ($m) {
        preg_match('/style\s*=\s*["\']([^"\']*)["\']/', $m[1], $sMatch);
        $style = $sMatch[1] ?? '';
        $safe  = [];
        if (preg_match('/color\s*:\s*(#[0-9a-fA-F]{3,8}|rgba?\([^)]+\))/i', $style, $c))
            $safe[] = 'color:' . $c[1];
        if (preg_match('/font-size\s*:\s*(\d+(?:\.\d+)?(?:px|em|rem|%))/i', $style, $s))
            $safe[] = 'font-size:' . $s[1];
        if (preg_match('/font-weight\s*:\s*(bold|\d+)/i', $style, $w))
            $safe[] = 'font-weight:' . $w[1];
        return empty($safe) ? '' : '<span style="' . implode(';', $safe) . '">';
    }, $html);
    // Collapse empty spans
    $html = preg_replace('/<span[^>]*>\s*<\/span>/i', '', $html);
    // Trim leading/trailing <br>
    $html = trim(preg_replace('/^(<br\s*\/?>\s*)+|(<br\s*\/?>\s*)+$/i', '', $html));
    return $html;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Save Kashier payment gateway settings ─────────────────────
    if (isset($_POST['save_kashier'])) {
        $kMid    = trim($_POST['kashier_mid']     ?? '');
        $kApiKey = trim($_POST['kashier_api_key'] ?? '');
        $kMode   = in_array($_POST['kashier_mode'] ?? '', ['test','live']) ? $_POST['kashier_mode'] : 'test';

        try {
            saveSetting($db, 'kashier_mid',  $kMid);
            saveSetting($db, 'kashier_mode', $kMode);
            if ($kApiKey) saveSetting($db, 'kashier_api_key', $kApiKey);

            // Test connection if both credentials provided
            $kTestMsg = '';
            $kTestOk  = false;
            if ($kMid && ($kApiKey ?: ($settings['kashier_api_key'] ?? ''))) {
                $kKeyToTest = $kApiKey ?: ($settings['kashier_api_key'] ?? '');
                $kOrdId     = 'TEST-' . time();
                $kAmount    = '1.00';
                $kCurrency  = 'EGP';
                $kPath      = "/?payment={$kMid}.{$kOrdId}.{$kAmount}.{$kCurrency}";
                $kHash      = hash_hmac('sha256', $kPath, $kKeyToTest, false);
                $kUrl       = "https://checkout.kashier.io/?merchantId={$kMid}&orderId={$kOrdId}&amount={$kAmount}&currency={$kCurrency}&hash={$kHash}&mode={$kMode}";
                $kCtx       = stream_context_create(['http'=>['timeout'=>6,'ignore_errors'=>true,'method'=>'GET'],'ssl'=>['verify_peer'=>false]]);
                $kResp      = @file_get_contents($kUrl, false, $kCtx);
                if ($kResp === false) {
                    $kTestMsg = '⚠️ Could not reach Kashier servers. Check server internet access.';
                } elseif (stripos($kResp, 'invalid merchant') !== false || stripos($kResp, 'merchant not found') !== false) {
                    $kTestMsg = '❌ Invalid Merchant ID. Please check your MID.';
                } elseif (stripos($kResp, 'invalid hash') !== false || stripos($kResp, 'hash mismatch') !== false) {
                    $kTestMsg = '❌ Invalid API Key (hash mismatch). Please check your API key.';
                } else {
                    $kTestMsg = '✅ Connected to Kashier! Ready to accept online payments.';
                    $kTestOk  = true;
                    saveSetting($db, 'kashier_verified', '1');
                }
            }

            // Reload settings
            $rows = $db->query("SELECT `key`, `value` FROM `settings`")->fetchAll();
            $settings = [];
            foreach ($rows as $row) { $settings[$row['key']] = $row['value']; }

            $success = 'Kashier settings saved.' . ($kTestMsg ? ' ' . $kTestMsg : '');
        } catch (Throwable $e) { $error = $e->getMessage(); }
    }

    // ── Save social proof settings ────────────────────────────────
    if (isset($_POST['save_social_proof'])) {
        try {
            saveSetting($db, 'social_proof_enabled', isset($_POST['social_proof_enabled']) ? '1' : '0');
            saveSetting($db, 'social_proof_min',     (string)max(1, (int)($_POST['social_proof_min'] ?? 3)));
            saveSetting($db, 'social_proof_max',     (string)max(2, (int)($_POST['social_proof_max'] ?? 18)));
            $success = 'Social proof settings saved.';
        } catch (Throwable $e) { $error = $e->getMessage(); }
        // Reload
        $rows = $db->query("SELECT `key`, `value` FROM `settings`")->fetchAll();
        $settings = [];
        foreach ($rows as $row) { $settings[$row['key']] = $row['value']; }
    }

    // ── Save branding / logo ──────────────────────────────────────
    if (isset($_POST['save_branding'])) {
        try {
            // Text parts
            saveSetting($db, 'site_name_1', trim($_POST['site_name_1'] ?? 'DUHN'));
            saveSetting($db, 'site_name_2', trim($_POST['site_name_2'] ?? 'FRAGRANCES'));
            // Logo mode: text | image | both
            $logoMode = in_array($_POST['logo_mode'] ?? '', ['text','image','both']) ? $_POST['logo_mode'] : 'text';
            saveSetting($db, 'logo_mode', $logoMode);
            // Remove logo
            if (!empty($_POST['remove_logo'])) {
                $oldLogo = $settings['site_logo'] ?? '';
                if ($oldLogo && file_exists(__DIR__ . '/../' . ltrim($oldLogo, '/'))) {
                    @unlink(__DIR__ . '/../' . ltrim($oldLogo, '/'));
                }
                saveSetting($db, 'site_logo', '');
                saveSetting($db, 'logo_mode', 'text');
            }
            // Upload new logo
            elseif (!empty($_FILES['site_logo']['tmp_name'])) {
                $file    = $_FILES['site_logo'];
                $allowed = ['image/png','image/jpeg','image/jpg','image/gif','image/svg+xml','image/webp'];
                if (!in_array($file['type'], $allowed)) throw new Exception('Invalid file type. Use PNG, JPG, SVG, or WebP.');
                if ($file['size'] > 2 * 1024 * 1024)   throw new Exception('Logo must be under 2MB.');
                $ext   = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $fname = 'site_logo_' . time() . '.' . $ext;
                $dir   = __DIR__ . '/../public/images/';
                $old   = $settings['site_logo'] ?? '';
                if ($old && file_exists(__DIR__ . '/../' . ltrim($old, '/'))) @unlink(__DIR__ . '/../' . ltrim($old, '/'));
                move_uploaded_file($file['tmp_name'], $dir . $fname);
                saveSetting($db, 'site_logo', '/public/images/' . $fname);
            }
            // Logo height
            $lh = max(20, min(80, (int)($_POST['site_logo_height'] ?? 36)));
            saveSetting($db, 'site_logo_height', (string)$lh);
            // Reload
            $rows = $db->query("SELECT `key`, `value` FROM `settings`")->fetchAll();
            $settings = [];
            foreach ($rows as $row) { $settings[$row['key']] = $row['value']; }
            $success = 'Branding saved.';
        } catch (Throwable $e) { $error = $e->getMessage(); }
    }

    // ── Save store / announcement / promo / shipping ──────────────
    if (isset($_POST['save_store'])) {
        try {
            saveSetting($db, 'announcement_text',    trim($_POST['announcement_text']    ?? ''));
            saveSetting($db, 'announcement_enabled', isset($_POST['announcement_enabled']) ? '1' : '0');
            saveSetting($db, 'promo_text',           trim($_POST['promo_text']           ?? ''));
            saveSetting($db, 'delivery_fee',         (string)(float)($_POST['delivery_fee'] ?? 0));
            saveSetting($db, 'promo_enabled',        isset($_POST['promo_enabled']) ? '1' : '0');
            saveSetting($db, 'promo_min_items',      (string)max(2, (int)($_POST['promo_min_items'] ?? 4)));
            saveSetting($db, 'delivery_info',        trim($_POST['delivery_info']        ?? ''));
            saveSetting($db, 'return_info',          trim($_POST['return_info']          ?? ''));
            saveSetting($db, 'shipping_policy',      trim($_POST['shipping_policy']      ?? ''));
            saveSetting($db, 'notify_email',         trim($_POST['notify_email']         ?? ''));
            saveSetting($db, 'notify_from_name',     trim($_POST['notify_from_name']     ?? ''));
            saveSetting($db, 'notify_from_email',    trim($_POST['notify_from_email']    ?? ''));
            saveSetting($db, 'smtp_host',            trim($_POST['smtp_host']            ?? ''));
            saveSetting($db, 'smtp_port',            (string)max(1, (int)($_POST['smtp_port'] ?? 587)));
            saveSetting($db, 'smtp_user',            trim($_POST['smtp_user']            ?? ''));
            if (!empty($_POST['smtp_pass'])) {
                saveSetting($db, 'smtp_pass', trim($_POST['smtp_pass']));
            }

            // Reload
            $rows     = $db->query("SELECT `key`, `value` FROM `settings`")->fetchAll();
            $settings = [];
            foreach ($rows as $row) { $settings[$row['key']] = $row['value']; }
            $success = 'Store settings saved successfully.';
        } catch (Throwable $e) {
            $error = 'Failed to save settings: ' . $e->getMessage();
        }
    }

    // ── Save hero slides (dynamic — any number) ───────────────────
    if (isset($_POST['save_hero'])) {
        try {
            $slideTitles = $_POST['slide_title'] ?? [];
            $newCount    = count($slideTitles);
            if ($newCount < 1) $newCount = 1;

            // Delete all old hero_N_* settings (core + button keys)
            $oldCount = (int)($settings['hero_count'] ?? 2);
            for ($i = 1; $i <= max($oldCount, $newCount + 5); $i++) {
                $db->prepare("DELETE FROM `settings` WHERE `key` IN (:a,:b,:c,:d,:e,:f,:g,:h,:i)")
                   ->execute([
                       ':a' => "hero_{$i}_eyebrow",       ':b' => "hero_{$i}_title",
                       ':c' => "hero_{$i}_subtitle",       ':d' => "hero_{$i}_btn_text",
                       ':e' => "hero_{$i}_btn_url",        ':f' => "hero_{$i}_image",
                       ':g' => "hero_{$i}_btn_above_text", ':h' => "hero_{$i}_btn_count",
                       ':i' => "hero_{$i}_content_pos",
                   ]);
                for ($b = 1; $b <= 3; $b++) {
                    $db->prepare("DELETE FROM `settings` WHERE `key` IN (:a,:b,:c)")
                       ->execute([
                           ':a' => "hero_{$i}_btn_{$b}_text",
                           ':b' => "hero_{$i}_btn_{$b}_url",
                           ':c' => "hero_{$i}_btn_{$b}_style",
                       ]);
                }
            }

            saveSetting($db, 'hero_count', (string)$newCount);

            $uploadDir = __DIR__ . '/../public/images/hero/';
            $allowed   = ['image/jpeg','image/png','image/webp'];

            foreach ($slideTitles as $idx => $title) {
                $n = $idx + 1;
                saveSetting($db, "hero_{$n}_eyebrow",        sanitizeHeroText($_POST['slide_eyebrow'][$idx]  ?? ''));
                saveSetting($db, "hero_{$n}_title",          sanitizeHeroText($title));
                saveSetting($db, "hero_{$n}_subtitle",       sanitizeHeroText($_POST['slide_subtitle'][$idx] ?? ''));
                saveSetting($db, "hero_{$n}_btn_above_text", trim($_POST['slide_btn_above_text'][$idx] ?? ''));
                $validPos   = ['top-left','top-center','top-right','mid-left','mid-center','mid-right','bot-left','bot-center','bot-right'];
                $contentPos = $_POST['slide_content_pos'][$idx] ?? 'mid-left';
                saveSetting($db, "hero_{$n}_content_pos", in_array($contentPos, $validPos) ? $contentPos : 'mid-left');

                // Decode buttons JSON serialized by JS on submit
                $btnsJson = $_POST['slide_btns_json'][$idx] ?? '[]';
                $btns     = json_decode($btnsJson, true);
                if (!is_array($btns)) $btns = [];
                $btns     = array_values(array_filter($btns, fn($b) => !empty(trim($b['text'] ?? ''))));
                $btnCount = min(count($btns), 3);
                saveSetting($db, "hero_{$n}_btn_count", (string)$btnCount);
                for ($b = 1; $b <= $btnCount; $b++) {
                    $btn = $btns[$b - 1];
                    $bStyle = in_array($btn['style'] ?? '', ['solid', 'ghost']) ? $btn['style'] : 'solid';
                    saveSetting($db, "hero_{$n}_btn_{$b}_text",  trim($btn['text'] ?? ''));
                    saveSetting($db, "hero_{$n}_btn_{$b}_url",   trim($btn['url']  ?? '/collections.php'));
                    saveSetting($db, "hero_{$n}_btn_{$b}_style", $bStyle);
                }

                // Image upload
                $tmpName = $_FILES['slide_image']['tmp_name'][$idx] ?? '';
                if (!empty($tmpName) && is_uploaded_file($tmpName)) {
                    $mime = mime_content_type($tmpName);
                    $size = $_FILES['slide_image']['size'][$idx] ?? 0;
                    if (in_array($mime, $allowed) && $size <= 5 * 1024 * 1024) {
                        $ext      = ($mime === 'image/webp') ? 'webp' : (($mime === 'image/png') ? 'png' : 'jpg');
                        $filename = "hero-{$n}.{$ext}";
                        move_uploaded_file($tmpName, $uploadDir . $filename);
                        saveSetting($db, "hero_{$n}_image", "/public/images/hero/{$filename}");
                    }
                } else {
                    $existingImg = trim($_POST['slide_existing_image'][$idx] ?? '');
                    if ($existingImg) saveSetting($db, "hero_{$n}_image", $existingImg);
                }
            }

            // Reload settings
            $rows     = $db->query("SELECT `key`, `value` FROM `settings`")->fetchAll();
            $settings = [];
            foreach ($rows as $row) { $settings[$row['key']] = $row['value']; }
            $success = "Hero slider saved — {$newCount} slide(s) active.";
        } catch (Throwable $e) {
            $error = 'Failed to save hero settings: ' . $e->getMessage();
        }
    }

    // ── Save newsletter popup settings ───────────────────────────
    if (isset($_POST['save_newsletter_popup'])) {
        try {
            saveSetting($db, 'nl_popup_enabled',  isset($_POST['nl_popup_enabled']) ? '1' : '0');
            saveSetting($db, 'nl_popup_delay',    (string)max(0, (int)($_POST['nl_popup_delay']    ?? 1800)));
            saveSetting($db, 'nl_popup_eyebrow',  trim($_POST['nl_popup_eyebrow']  ?? 'SIGNUP FOR EMAILS'));
            saveSetting($db, 'nl_popup_title',    trim($_POST['nl_popup_title']     ?? 'GET 20% DISCOUNT SHIPPED TO YOUR INBOX'));
            saveSetting($db, 'nl_popup_desc',     trim($_POST['nl_popup_desc']      ?? ''));
            saveSetting($db, 'nl_popup_btn_text', trim($_POST['nl_popup_btn_text']  ?? 'SUBSCRIBE'));
            $rows     = $db->query("SELECT `key`, `value` FROM `settings`")->fetchAll();
            $settings = [];
            foreach ($rows as $row) { $settings[$row['key']] = $row['value']; }
            $success = 'Newsletter popup settings saved.';
        } catch (Throwable $e) {
            $error = 'Failed: ' . $e->getMessage();
        }
    }

    // ── Change password ───────────────────────────────────────────
    if (isset($_POST['change_password'])) {
        $currentPw = $_POST['current_password'] ?? '';
        $newPw     = $_POST['new_password']     ?? '';
        $confirmPw = $_POST['confirm_password'] ?? '';

        if (strlen($newPw) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($newPw !== $confirmPw) {
            $error = 'Passwords do not match.';
        } else {
            $adminId  = $_SESSION['admin_id'];
            $adminRow = $db->prepare("SELECT password_hash FROM users WHERE id = :id");
            $adminRow->execute([':id' => $adminId]);
            $adminRow = $adminRow->fetch();

            if (!$adminRow || !password_verify($currentPw, $adminRow['password_hash'])) {
                $error = 'Current password is incorrect.';
            } else {
                $hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);
                $db->prepare("UPDATE users SET password_hash = :h WHERE id = :id")
                   ->execute([':h' => $hash, ':id' => $adminId]);
                $success = 'Password changed successfully.';
            }
        }
    }
}

$adminTitle = 'Settings';
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($success): ?><div class="admin-alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="admin-alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- ══════════════════════════════════════════════════════════════
     HERO SLIDER SETTINGS — Dynamic (add/remove slides)
══════════════════════════════════════════════════════════════ -->
<?php
$heroCount = max(1, (int)($settings['hero_count'] ?? 2));
// Build slide data array from settings
$heroSlides = [];
for ($n = 1; $n <= $heroCount; $n++) {
    // Load buttons for this slide
    $sBtnCount = (int)($settings["hero_{$n}_btn_count"] ?? 0);
    $sBtns     = [];
    for ($b = 1; $b <= min($sBtnCount, 3); $b++) {
        $bt = $settings["hero_{$n}_btn_{$b}_text"] ?? '';
        if ($bt) $sBtns[] = [
            'text'  => $bt,
            'url'   => $settings["hero_{$n}_btn_{$b}_url"]   ?? '/collections.php',
            'style' => $settings["hero_{$n}_btn_{$b}_style"] ?? 'solid',
        ];
    }
    // Fallback: legacy single btn_text
    if (empty($sBtns) && !empty($settings["hero_{$n}_btn_text"])) {
        $sBtns[] = [
            'text'  => $settings["hero_{$n}_btn_text"],
            'url'   => $settings["hero_{$n}_btn_url"] ?? '/collections.php',
            'style' => 'solid',
        ];
    }
    if (empty($sBtns)) {
        $sBtns[] = ['text' => 'Shop Now', 'url' => '/collections.php', 'style' => 'solid'];
    }
    $heroSlides[] = [
        'eyebrow'        => $settings["hero_{$n}_eyebrow"]        ?? '',
        'title'          => $settings["hero_{$n}_title"]          ?? '',
        'subtitle'       => $settings["hero_{$n}_subtitle"]       ?? '',
        'btn_above_text' => $settings["hero_{$n}_btn_above_text"] ?? '',
        'content_pos'    => $settings["hero_{$n}_content_pos"]    ?? 'mid-left',
        'btns'           => $sBtns,
        'image'          => $settings["hero_{$n}_image"]          ?? "/public/images/hero/hero-{$n}.jpg",
    ];
}
$ts = time(); // cache-bust
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <h2 style="font-size:15px;font-weight:700;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">🖼 Hero Slider</h2>
  <button type="button" onclick="heroAddSlide()"
          style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:var(--accent);color:#1A1A1A;border:none;border-radius:6px;font-size:13px;font-weight:700;cursor:pointer">
    <i class="ph ph-plus"></i> Add Slide
  </button>
</div>

<form method="POST" enctype="multipart/form-data" id="hero-form" style="margin-bottom:32px">
  <input type="hidden" name="save_hero" value="1">

  <div id="hero-slides-container" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(380px,1fr));gap:20px;margin-bottom:16px">

    <?php foreach ($heroSlides as $idx => $slide):
      $n = $idx + 1;
      $imgExists = !empty($slide['image']) && file_exists(__DIR__ . '/..' . $slide['image']);
    ?>
    <div class="admin-card hero-slide-card" data-slide="<?= $n ?>" style="position:relative">

      <!-- Delete button -->
      <button type="button" onclick="heroRemoveSlide(this)"
              style="position:absolute;top:12px;right:12px;background:rgba(220,53,69,0.15);border:1px solid var(--error);color:var(--error);border-radius:6px;padding:4px 10px;font-size:11px;font-weight:700;cursor:pointer"
              title="Remove this slide">
        <i class="ph ph-trash"></i> Remove
      </button>

      <h3 style="font-size:13px;font-weight:700;margin-bottom:14px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">
        Slide <span class="slide-num"><?= $n ?></span>
      </h3>

      <!-- Existing image preview + hidden path -->
      <div class="admin-form-group">
        <label class="admin-label">Background Image</label>
        <?php if ($imgExists): ?>
        <img src="<?= htmlspecialchars($slide['image']) ?>?v=<?= $ts ?>"
             style="width:100%;height:130px;object-fit:cover;border-radius:6px;margin-bottom:8px" id="preview-<?= $idx ?>">
        <?php endif; ?>
        <input type="hidden" name="slide_existing_image[]" value="<?= htmlspecialchars($slide['image']) ?>">
        <input type="file" name="slide_image[]" accept="image/*" style="font-size:12px;color:var(--text-muted)"
               onchange="previewHeroImg(this, <?= $idx ?>)">
        <p style="font-size:11px;color:var(--text-muted);margin-top:3px">JPG/PNG/WebP · Max 5MB</p>
      </div>

      <!-- Eyebrow RTE -->
      <div class="admin-form-group rte-group">
        <label class="admin-label">Eyebrow <span style="color:var(--text-muted);font-weight:400">(small text above title)</span></label>
        <div class="rte-toolbar">
          <button type="button" onclick="rteCmd(this,'bold')" title="Bold"><b>B</b></button>
          <button type="button" onclick="rteCmd(this,'italic')" title="Italic"><em>I</em></button>
          <div class="rte-sep"></div>
          <select onchange="rteSetSize(this)" title="Font size">
            <option value="">Size</option>
            <?php foreach ([10,11,12,13,14,16,18,20,22,24,28,32,36,40,48,56,64] as $fs): ?>
            <option value="<?= $fs ?>px"><?= $fs ?>px</option>
            <?php endforeach; ?>
          </select>
          <div class="rte-sep"></div>
          <input type="color" onchange="rteSetColor(this)" value="#ffffff" title="Text color">
          <button type="button" onclick="rteClear(this)" title="Remove formatting" style="font-size:11px">✕ Clear</button>
        </div>
        <div contenteditable="true" class="admin-rte" data-placeholder="e.g. Our First Anniversary"><?= $slide['eyebrow'] ?></div>
        <input type="hidden" name="slide_eyebrow[]" class="rte-value" value="<?= htmlspecialchars($slide['eyebrow']) ?>">
      </div>

      <!-- Title RTE -->
      <div class="admin-form-group rte-group">
        <label class="admin-label">Title <span style="color:var(--text-muted);font-weight:400">(press Enter for new line)</span></label>
        <div class="rte-toolbar">
          <button type="button" onclick="rteCmd(this,'bold')" title="Bold"><b>B</b></button>
          <button type="button" onclick="rteCmd(this,'italic')" title="Italic"><em>I</em></button>
          <div class="rte-sep"></div>
          <select onchange="rteSetSize(this)" title="Font size">
            <option value="">Size</option>
            <?php foreach ([10,11,12,13,14,16,18,20,22,24,28,32,36,40,48,56,64] as $fs): ?>
            <option value="<?= $fs ?>px"><?= $fs ?>px</option>
            <?php endforeach; ?>
          </select>
          <div class="rte-sep"></div>
          <input type="color" onchange="rteSetColor(this)" value="#ffffff" title="Text color">
          <button type="button" onclick="rteClear(this)" title="Remove formatting" style="font-size:11px">✕ Clear</button>
        </div>
        <div contenteditable="true" class="admin-rte" data-placeholder="Indulge Your Senses" style="min-height:60px"><?= $slide['title'] ?></div>
        <input type="hidden" name="slide_title[]" class="rte-value" value="<?= htmlspecialchars($slide['title']) ?>">
        <p class="rte-hint">Select any word and use toolbar to make it <b style="color:#fff">bold</b>, change <span style="color:var(--accent)">color</span>, or adjust size.</p>
      </div>

      <!-- Subtitle RTE -->
      <div class="admin-form-group rte-group">
        <label class="admin-label">Subtitle</label>
        <div class="rte-toolbar">
          <button type="button" onclick="rteCmd(this,'bold')" title="Bold"><b>B</b></button>
          <button type="button" onclick="rteCmd(this,'italic')" title="Italic"><em>I</em></button>
          <div class="rte-sep"></div>
          <select onchange="rteSetSize(this)" title="Font size">
            <option value="">Size</option>
            <?php foreach ([10,11,12,13,14,16,18,20,22,24,28,32,36,40,48,56,64] as $fs): ?>
            <option value="<?= $fs ?>px"><?= $fs ?>px</option>
            <?php endforeach; ?>
          </select>
          <div class="rte-sep"></div>
          <input type="color" onchange="rteSetColor(this)" value="#ffffff" title="Text color">
          <button type="button" onclick="rteClear(this)" title="Remove formatting" style="font-size:11px">✕ Clear</button>
        </div>
        <div contenteditable="true" class="admin-rte" data-placeholder="Short description"><?= $slide['subtitle'] ?></div>
        <input type="hidden" name="slide_subtitle[]" class="rte-value" value="<?= htmlspecialchars($slide['subtitle']) ?>">
      </div>

      <!-- Content Position Picker — 3×3 grid -->
      <?php
      $posMap = [
          'top-left'   => ['label'=>'Top Left',    'icon'=>'↖'],
          'top-center' => ['label'=>'Top Center',  'icon'=>'↑'],
          'top-right'  => ['label'=>'Top Right',   'icon'=>'↗'],
          'mid-left'   => ['label'=>'Mid Left',    'icon'=>'←'],
          'mid-center' => ['label'=>'Center',      'icon'=>'⬛'],
          'mid-right'  => ['label'=>'Mid Right',   'icon'=>'→'],
          'bot-left'   => ['label'=>'Bot Left',    'icon'=>'↙'],
          'bot-center' => ['label'=>'Bot Center',  'icon'=>'↓'],
          'bot-right'  => ['label'=>'Bot Right',   'icon'=>'↘'],
      ];
      $curPos = $slide['content_pos'];
      ?>
      <div class="admin-form-group">
        <label class="admin-label">Content Position <span style="color:var(--text-muted);font-weight:400">(where text & buttons appear)</span></label>
        <div class="pos-picker" style="display:grid;grid-template-columns:repeat(3,1fr);gap:4px;max-width:200px">
          <?php foreach ($posMap as $posVal => $posInfo): ?>
          <label style="cursor:pointer" title="<?= $posInfo['label'] ?>">
            <input type="radio" name="slide_content_pos[]" value="<?= $posVal ?>"
                   class="pos-radio" <?= $curPos === $posVal ? 'checked' : '' ?> style="display:none">
            <div class="pos-cell"
                 style="height:52px;border:1.5px solid <?= $curPos === $posVal ? 'var(--accent)' : '#333' ?>;
                        border-radius:5px;display:flex;align-items:center;justify-content:center;
                        font-size:18px;background:<?= $curPos === $posVal ? 'rgba(248,196,23,0.12)' : 'transparent' ?>;
                        transition:all 0.15s" title="<?= $posInfo['label'] ?>">
              <?= $posInfo['icon'] ?>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
        <p style="font-size:11px;color:var(--text-muted);margin-top:4px">Selected: <strong class="pos-label-display" style="color:var(--accent)"><?= $posMap[$curPos]['label'] ?? 'Mid Left' ?></strong></p>
      </div>

      <!-- Hidden JSON — serialized by JS before submit -->
      <input type="hidden" name="slide_btns_json[]" class="slide-btns-json"
             value="<?= htmlspecialchars(json_encode($slide['btns'])) ?>">

      <div class="admin-form-group">
        <label class="admin-label">Text Above Buttons <span style="color:var(--text-muted);font-weight:400">(optional)</span></label>
        <input type="text" name="slide_btn_above_text[]" class="admin-input"
               value="<?= htmlspecialchars($slide['btn_above_text']) ?>" placeholder="e.g. Exclusive Offer">
      </div>

      <!-- Multi-button manager -->
      <div class="slide-btns-section">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
          <label class="admin-label" style="margin-bottom:0">Buttons (up to 3)</label>
          <button type="button" onclick="heroAddBtn(this)" class="btn-add-btn"
                  style="font-size:11px;padding:4px 10px;background:rgba(248,196,23,0.1);border:1px solid rgba(248,196,23,0.3);color:var(--accent);border-radius:4px;cursor:pointer;font-weight:700"
                  <?= count($slide['btns']) >= 3 ? 'disabled' : '' ?>>
            + ADD BUTTON
          </button>
        </div>
        <div class="btn-rows">
          <?php foreach ($slide['btns'] as $hbtn): ?>
          <div class="btn-row" style="display:grid;grid-template-columns:1fr 1fr auto auto;gap:6px;margin-bottom:6px;align-items:center">
            <input type="text" class="admin-input btn-row__text" placeholder="Button text"
                   value="<?= htmlspecialchars($hbtn['text']) ?>" style="font-size:12px;padding:7px 10px">
            <input type="text" class="admin-input btn-row__url"  placeholder="Link URL"
                   value="<?= htmlspecialchars($hbtn['url']) ?>"  style="font-size:12px;padding:7px 10px">
            <select class="admin-input btn-row__style" style="font-size:12px;padding:7px 10px;width:auto">
              <option value="solid" <?= ($hbtn['style'] ?? 'solid') === 'solid' ? 'selected' : '' ?>>Solid</option>
              <option value="ghost" <?= ($hbtn['style'] ?? '') === 'ghost' ? 'selected' : '' ?>>Ghost</option>
            </select>
            <button type="button" onclick="heroRemoveBtn(this)"
                    style="background:rgba(220,53,69,0.15);border:1px solid rgba(220,53,69,0.3);color:var(--danger);border-radius:4px;padding:7px 10px;cursor:pointer;line-height:1">
              <i class="ph ph-x"></i>
            </button>
          </div>
          <?php endforeach; ?>
        </div>
        <p style="font-size:11px;color:var(--text-muted);margin-top:4px">Solid = filled black button &nbsp;·&nbsp; Ghost = transparent outlined</p>
      </div>

    </div>
    <?php endforeach; ?>

  </div><!-- /hero-slides-container -->

  <button type="submit" class="btn-admin-gold" style="width:100%">
    <i class="ph ph-floppy-disk"></i> Save Hero Slider
  </button>
</form>

<!-- Hidden template for new slide -->
<template id="hero-slide-tpl">
  <div class="admin-card hero-slide-card" style="position:relative">
    <button type="button" onclick="heroRemoveSlide(this)"
            style="position:absolute;top:12px;right:12px;background:rgba(220,53,69,0.15);border:1px solid var(--error);color:var(--error);border-radius:6px;padding:4px 10px;font-size:11px;font-weight:700;cursor:pointer">
      <i class="ph ph-trash"></i> Remove
    </button>
    <h3 style="font-size:13px;font-weight:700;margin-bottom:14px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">
      Slide <span class="slide-num"></span>
    </h3>
    <div class="admin-form-group">
      <label class="admin-label">Background Image</label>
      <input type="hidden" name="slide_existing_image[]" value="">
      <div style="width:100%;height:100px;background:#2A2A2A;border-radius:6px;margin-bottom:8px;display:flex;align-items:center;justify-content:center;color:#555;font-size:13px">
        No image yet — upload below
      </div>
      <input type="file" name="slide_image[]" accept="image/*" style="font-size:12px;color:var(--text-muted)">
      <p style="font-size:11px;color:var(--text-muted);margin-top:3px">JPG/PNG/WebP · Max 5MB</p>
    </div>
    <div class="admin-form-group rte-group">
      <label class="admin-label">Eyebrow <span style="color:var(--text-muted);font-weight:400">(small text above title)</span></label>
      <div class="rte-toolbar">
        <button type="button" onclick="rteCmd(this,'bold')"><b>B</b></button>
        <button type="button" onclick="rteCmd(this,'italic')"><em>I</em></button>
        <div class="rte-sep"></div>
        <select onchange="rteSetSize(this)"><option value="">Size</option><option value="10px">10px</option><option value="12px">12px</option><option value="14px">14px</option><option value="16px">16px</option><option value="18px">18px</option><option value="20px">20px</option><option value="24px">24px</option><option value="28px">28px</option><option value="32px">32px</option><option value="36px">36px</option><option value="40px">40px</option><option value="48px">48px</option></select>
        <div class="rte-sep"></div>
        <input type="color" onchange="rteSetColor(this)" value="#ffffff" title="Text color">
        <button type="button" onclick="rteClear(this)" style="font-size:11px">✕ Clear</button>
      </div>
      <div contenteditable="true" class="admin-rte" data-placeholder="e.g. New Collection"></div>
      <input type="hidden" name="slide_eyebrow[]" class="rte-value" value="">
    </div>
    <div class="admin-form-group rte-group">
      <label class="admin-label">Title <span style="color:var(--text-muted);font-weight:400">(press Enter for new line)</span></label>
      <div class="rte-toolbar">
        <button type="button" onclick="rteCmd(this,'bold')"><b>B</b></button>
        <button type="button" onclick="rteCmd(this,'italic')"><em>I</em></button>
        <div class="rte-sep"></div>
        <select onchange="rteSetSize(this)"><option value="">Size</option><option value="10px">10px</option><option value="12px">12px</option><option value="14px">14px</option><option value="16px">16px</option><option value="18px">18px</option><option value="20px">20px</option><option value="24px">24px</option><option value="28px">28px</option><option value="32px">32px</option><option value="36px">36px</option><option value="40px">40px</option><option value="48px">48px</option></select>
        <div class="rte-sep"></div>
        <input type="color" onchange="rteSetColor(this)" value="#ffffff" title="Text color">
        <button type="button" onclick="rteClear(this)" style="font-size:11px">✕ Clear</button>
      </div>
      <div contenteditable="true" class="admin-rte" data-placeholder="Your Title" style="min-height:60px"></div>
      <input type="hidden" name="slide_title[]" class="rte-value" value="">
      <p class="rte-hint">Select any word and use toolbar to make it bold, change color, or adjust size.</p>
    </div>
    <div class="admin-form-group rte-group">
      <label class="admin-label">Subtitle</label>
      <div class="rte-toolbar">
        <button type="button" onclick="rteCmd(this,'bold')"><b>B</b></button>
        <button type="button" onclick="rteCmd(this,'italic')"><em>I</em></button>
        <div class="rte-sep"></div>
        <select onchange="rteSetSize(this)"><option value="">Size</option><option value="10px">10px</option><option value="12px">12px</option><option value="14px">14px</option><option value="16px">16px</option><option value="18px">18px</option><option value="20px">20px</option><option value="24px">24px</option><option value="28px">28px</option><option value="32px">32px</option><option value="36px">36px</option><option value="40px">40px</option><option value="48px">48px</option></select>
        <div class="rte-sep"></div>
        <input type="color" onchange="rteSetColor(this)" value="#ffffff" title="Text color">
        <button type="button" onclick="rteClear(this)" style="font-size:11px">✕ Clear</button>
      </div>
      <div contenteditable="true" class="admin-rte" data-placeholder="Short description"></div>
      <input type="hidden" name="slide_subtitle[]" class="rte-value" value="">
    </div>

    <!-- Content Position Picker -->
    <div class="admin-form-group">
      <label class="admin-label">Content Position <span style="color:var(--text-muted);font-weight:400">(where text & buttons appear)</span></label>
      <div class="pos-picker" style="display:grid;grid-template-columns:repeat(3,1fr);gap:4px;max-width:200px">
        <label style="cursor:pointer" title="Top Left"><input type="radio" name="slide_content_pos[]" value="top-left" class="pos-radio" style="display:none"><div class="pos-cell" style="height:52px;border:1.5px solid #333;border-radius:5px;display:flex;align-items:center;justify-content:center;font-size:18px;background:transparent;transition:all 0.15s">↖</div></label>
        <label style="cursor:pointer" title="Top Center"><input type="radio" name="slide_content_pos[]" value="top-center" class="pos-radio" style="display:none"><div class="pos-cell" style="height:52px;border:1.5px solid #333;border-radius:5px;display:flex;align-items:center;justify-content:center;font-size:18px;background:transparent;transition:all 0.15s">↑</div></label>
        <label style="cursor:pointer" title="Top Right"><input type="radio" name="slide_content_pos[]" value="top-right" class="pos-radio" style="display:none"><div class="pos-cell" style="height:52px;border:1.5px solid #333;border-radius:5px;display:flex;align-items:center;justify-content:center;font-size:18px;background:transparent;transition:all 0.15s">↗</div></label>
        <label style="cursor:pointer" title="Mid Left"><input type="radio" name="slide_content_pos[]" value="mid-left" class="pos-radio" checked style="display:none"><div class="pos-cell" style="height:52px;border:1.5px solid var(--accent);border-radius:5px;display:flex;align-items:center;justify-content:center;font-size:18px;background:rgba(248,196,23,0.12);transition:all 0.15s">←</div></label>
        <label style="cursor:pointer" title="Center"><input type="radio" name="slide_content_pos[]" value="mid-center" class="pos-radio" style="display:none"><div class="pos-cell" style="height:52px;border:1.5px solid #333;border-radius:5px;display:flex;align-items:center;justify-content:center;font-size:18px;background:transparent;transition:all 0.15s">⬛</div></label>
        <label style="cursor:pointer" title="Mid Right"><input type="radio" name="slide_content_pos[]" value="mid-right" class="pos-radio" style="display:none"><div class="pos-cell" style="height:52px;border:1.5px solid #333;border-radius:5px;display:flex;align-items:center;justify-content:center;font-size:18px;background:transparent;transition:all 0.15s">→</div></label>
        <label style="cursor:pointer" title="Bot Left"><input type="radio" name="slide_content_pos[]" value="bot-left" class="pos-radio" style="display:none"><div class="pos-cell" style="height:52px;border:1.5px solid #333;border-radius:5px;display:flex;align-items:center;justify-content:center;font-size:18px;background:transparent;transition:all 0.15s">↙</div></label>
        <label style="cursor:pointer" title="Bot Center"><input type="radio" name="slide_content_pos[]" value="bot-center" class="pos-radio" style="display:none"><div class="pos-cell" style="height:52px;border:1.5px solid #333;border-radius:5px;display:flex;align-items:center;justify-content:center;font-size:18px;background:transparent;transition:all 0.15s">↓</div></label>
        <label style="cursor:pointer" title="Bot Right"><input type="radio" name="slide_content_pos[]" value="bot-right" class="pos-radio" style="display:none"><div class="pos-cell" style="height:52px;border:1.5px solid #333;border-radius:5px;display:flex;align-items:center;justify-content:center;font-size:18px;background:transparent;transition:all 0.15s">↘</div></label>
      </div>
      <p style="font-size:11px;color:var(--text-muted);margin-top:4px">Selected: <strong class="pos-label-display" style="color:var(--accent)">Mid Left</strong></p>
    </div>

    <!-- Hidden JSON -->
    <input type="hidden" name="slide_btns_json[]" class="slide-btns-json" value="[]">

    <div class="admin-form-group">
      <label class="admin-label">Text Above Buttons <span style="color:var(--text-muted);font-weight:400">(optional)</span></label>
      <input type="text" name="slide_btn_above_text[]" class="admin-input" placeholder="e.g. Exclusive Offer">
    </div>

    <!-- Multi-button manager -->
    <div class="slide-btns-section">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <label class="admin-label" style="margin-bottom:0">Buttons (up to 3)</label>
        <button type="button" onclick="heroAddBtn(this)" class="btn-add-btn"
                style="font-size:11px;padding:4px 10px;background:rgba(248,196,23,0.1);border:1px solid rgba(248,196,23,0.3);color:var(--accent);border-radius:4px;cursor:pointer;font-weight:700">
          + ADD BUTTON
        </button>
      </div>
      <div class="btn-rows">
        <div class="btn-row" style="display:grid;grid-template-columns:1fr 1fr auto auto;gap:6px;margin-bottom:6px;align-items:center">
          <input type="text" class="admin-input btn-row__text" placeholder="Button text" value="Shop Now" style="font-size:12px;padding:7px 10px">
          <input type="text" class="admin-input btn-row__url"  placeholder="Link URL" value="/collections.php" style="font-size:12px;padding:7px 10px">
          <select class="admin-input btn-row__style" style="font-size:12px;padding:7px 10px;width:auto">
            <option value="solid">Solid</option>
            <option value="ghost">Ghost</option>
          </select>
          <button type="button" onclick="heroRemoveBtn(this)"
                  style="background:rgba(220,53,69,0.15);border:1px solid rgba(220,53,69,0.3);color:var(--danger);border-radius:4px;padding:7px 10px;cursor:pointer;line-height:1">
            <i class="ph ph-x"></i>
          </button>
        </div>
      </div>
      <p style="font-size:11px;color:var(--text-muted);margin-top:4px">Solid = filled black button &nbsp;·&nbsp; Ghost = transparent outlined</p>
    </div>

  </div>
</template>

<script>
// ── Slide add / remove ────────────────────────────────────────────
function heroAddSlide() {
  const tpl       = document.getElementById('hero-slide-tpl');
  const container = document.getElementById('hero-slides-container');
  const clone     = tpl.content.cloneNode(true);
  container.appendChild(clone);
  renumberSlides();
  container.lastElementChild.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function heroRemoveSlide(btn) {
  const cards = document.querySelectorAll('#hero-slides-container .hero-slide-card');
  if (cards.length <= 1) { alert('You must have at least 1 slide.'); return; }
  if (!confirm('Remove this slide?')) return;
  btn.closest('.hero-slide-card').remove();
  renumberSlides();
}

function renumberSlides() {
  document.querySelectorAll('#hero-slides-container .hero-slide-card').forEach((card, i) => {
    const numEl = card.querySelector('.slide-num');
    if (numEl) numEl.textContent = i + 1;
  });
}

function previewHeroImg(input, idx) {
  const preview = document.getElementById('preview-' + idx);
  if (!preview || !input.files[0]) return;
  preview.src = URL.createObjectURL(input.files[0]);
}

// ── Button add / remove (per slide) ──────────────────────────────
function heroAddBtn(addBtn) {
  const section   = addBtn.closest('.slide-btns-section');
  const container = section.querySelector('.btn-rows');
  if (container.querySelectorAll('.btn-row').length >= 3) {
    alert('Maximum 3 buttons per slide.'); return;
  }
  const row = document.createElement('div');
  row.className = 'btn-row';
  row.style.cssText = 'display:grid;grid-template-columns:1fr 1fr auto auto;gap:6px;margin-bottom:6px;align-items:center';
  row.innerHTML = `
    <input type="text" class="admin-input btn-row__text" placeholder="Button text" style="font-size:12px;padding:7px 10px">
    <input type="text" class="admin-input btn-row__url"  placeholder="Link URL" value="/collections.php" style="font-size:12px;padding:7px 10px">
    <select class="admin-input btn-row__style" style="font-size:12px;padding:7px 10px;width:auto">
      <option value="solid">Solid</option>
      <option value="ghost">Ghost</option>
    </select>
    <button type="button" onclick="heroRemoveBtn(this)"
            style="background:rgba(220,53,69,0.15);border:1px solid rgba(220,53,69,0.3);color:var(--danger);border-radius:4px;padding:7px 10px;cursor:pointer;line-height:1">
      <i class="ph ph-x"></i>
    </button>`;
  container.appendChild(row);
  if (container.querySelectorAll('.btn-row').length >= 3) addBtn.disabled = true;
}

function heroRemoveBtn(removeBtn) {
  const row     = removeBtn.closest('.btn-row');
  const section = removeBtn.closest('.slide-btns-section');
  row.remove();
  section.querySelector('.btn-add-btn').disabled = false;
}

// ── Mini Rich Text Editor ────────────────────────────────────────
function _rteEditor(el) {
  return el.closest('.rte-group').querySelector('.admin-rte');
}

function rteCmd(btn, cmd) {
  const ed = _rteEditor(btn);
  ed.focus();
  document.execCommand('styleWithCSS', false, true);
  document.execCommand(cmd, false, null);
  // Toggle active state
  btn.classList.toggle('rte-active', document.queryCommandState(cmd));
}

function rteSetSize(select) {
  const size = select.value;
  if (!size) return;
  const ed  = _rteEditor(select);
  ed.focus();
  const sel = window.getSelection();
  if (!sel || !sel.rangeCount || sel.getRangeAt(0).collapsed) { select.value = ''; return; }
  document.execCommand('styleWithCSS', false, true);
  // Insert a span with font-size
  try {
    const range = sel.getRangeAt(0);
    const span  = document.createElement('span');
    span.style.fontSize = size;
    range.surroundContents(span);
  } catch(e) {
    document.execCommand('insertHTML', false,
      `<span style="font-size:${size}">${sel.toString()}</span>`);
  }
  select.value = '';
}

function rteSetColor(input) {
  const ed = _rteEditor(input);
  ed.focus();
  document.execCommand('styleWithCSS', false, true);
  document.execCommand('foreColor', false, input.value);
}

function rteClear(btn) {
  const ed = _rteEditor(btn);
  ed.focus();
  document.execCommand('removeFormat', false, null);
}

// Sync RTE on any input (live update hidden field)
document.getElementById('hero-slides-container').addEventListener('input', function(e) {
  const rte = e.target.closest?.('.admin-rte');
  if (!rte) return;
  const hidden = rte.nextElementSibling;
  if (hidden?.classList.contains('rte-value')) hidden.value = rte.innerHTML;
});

// ── Position picker: click to select ─────────────────────────────
const posLabels = {
  'top-left':'Top Left','top-center':'Top Center','top-right':'Top Right',
  'mid-left':'Mid Left','mid-center':'Center','mid-right':'Mid Right',
  'bot-left':'Bot Left','bot-center':'Bot Center','bot-right':'Bot Right',
};

document.getElementById('hero-slides-container').addEventListener('change', function(e) {
  if (!e.target.classList.contains('pos-radio')) return;
  const picker  = e.target.closest('.pos-picker');
  const card    = e.target.closest('.hero-slide-card');
  const display = card?.querySelector('.pos-label-display');

  // Reset all cells in this picker
  picker.querySelectorAll('.pos-cell').forEach(cell => {
    cell.style.borderColor = '#333';
    cell.style.background  = 'transparent';
  });
  // Highlight selected
  const activeCell = e.target.nextElementSibling;
  activeCell.style.borderColor = 'var(--accent)';
  activeCell.style.background  = 'rgba(248,196,23,0.12)';
  if (display) display.textContent = posLabels[e.target.value] || e.target.value;
});

// ── On submit: sync all RTEs + serialize button rows ─────────────
document.getElementById('hero-form').addEventListener('submit', function() {
  document.querySelectorAll('#hero-slides-container .hero-slide-card').forEach(card => {
    // Sync RTE editors → hidden inputs
    card.querySelectorAll('.admin-rte').forEach(rte => {
      const hidden = rte.nextElementSibling;
      if (hidden?.classList.contains('rte-value')) hidden.value = rte.innerHTML;
    });
    // Serialize button rows → JSON
    const jsonInput = card.querySelector('.slide-btns-json');
    if (!jsonInput) return;
    const btns = [];
    card.querySelectorAll('.btn-row').forEach(row => {
      const text  = (row.querySelector('.btn-row__text')?.value  || '').trim();
      const url   = (row.querySelector('.btn-row__url')?.value   || '/collections.php').trim();
      const style = row.querySelector('.btn-row__style')?.value  || 'solid';
      if (text) btns.push({ text, url, style });
    });
    jsonInput.value = JSON.stringify(btns);
  });
});
</script>

<!-- ══════════════════════════════════════════════════════════════
     STORE / ANNOUNCEMENT / PROMO SETTINGS
══════════════════════════════════════════════════════════════ -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start">

  <div>
    <form method="POST">
      <input type="hidden" name="save_store" value="1">

      <!-- Announcement Bar -->
      <div class="admin-card" style="margin-bottom:20px">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">
          📢 Announcement Bar <span style="font-size:11px;opacity:0.5">(dark top strip)</span>
        </h3>
        <div class="admin-form-group">
          <label style="display:flex;justify-content:space-between;align-items:center;cursor:pointer;margin-bottom:14px">
            <span class="admin-label" style="margin-bottom:0">Enabled</span>
            <label class="toggle-switch">
              <input type="checkbox" name="announcement_enabled"
                     <?= ($settings['announcement_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
              <span class="toggle-slider"></span>
            </label>
          </label>
        </div>
        <div class="admin-form-group">
          <label class="admin-label">Scrolling Text</label>
          <input type="text" name="announcement_text" class="admin-input"
                 value="<?= htmlspecialchars($settings['announcement_text'] ?? '') ?>"
                 placeholder="e.g. BUY 2 GET 2 FREE ✦ OUR FIRST ANNIVERSARY">
          <p style="font-size:11px;color:var(--text-muted);margin-top:4px">
            Use &nbsp;✦&nbsp; between phrases. This scrolls continuously at the top.
          </p>
        </div>
      </div>

      <!-- Promo Marquee -->
      <div class="admin-card" style="margin-bottom:20px">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">
          🟡 Promo Strip <span style="font-size:11px;opacity:0.5">(gold bar under hero)</span>
        </h3>
        <div class="admin-form-group">
          <label class="admin-label">Scrolling Text</label>
          <input type="text" name="promo_text" class="admin-input"
                 value="<?= htmlspecialchars($settings['promo_text'] ?? '') ?>"
                 placeholder="e.g. BUY 2 GET 2 FREE ✦ ALL PERFUMES 899 EGP">
          <p style="font-size:11px;color:var(--text-muted);margin-top:4px">
            This is the gold scrolling bar below the hero image.
          </p>
        </div>
      </div>

      <!-- Promotion -->
      <div class="admin-card" style="margin-bottom:20px">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">
          🎁 Promotion (Buy X Get X Free)
        </h3>
        <div class="admin-form-group">
          <label style="display:flex;justify-content:space-between;align-items:center;cursor:pointer;margin-bottom:14px">
            <span class="admin-label" style="margin-bottom:0">BUY 2 GET 2 FREE Enabled</span>
            <label class="toggle-switch">
              <input type="checkbox" name="promo_enabled"
                     <?= ($settings['promo_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
              <span class="toggle-slider"></span>
            </label>
          </label>
        </div>
        <div class="admin-form-group">
          <label class="admin-label">Minimum Items for Promo</label>
          <input type="number" name="promo_min_items" class="admin-input" style="max-width:100px"
                 value="<?= (int)($settings['promo_min_items'] ?? 4) ?>" min="2" max="20">
          <p style="font-size:11px;color:var(--text-muted);margin-top:4px">Default: 4 (buy 4, pay for 2)</p>
        </div>
      </div>

      <!-- Shipping -->
      <div class="admin-card" style="margin-bottom:20px">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">
          🚚 Shipping &amp; Delivery
        </h3>
        <div class="admin-form-group">
          <label class="admin-label">Delivery Fee (EGP)</label>
          <input type="number" name="delivery_fee" class="admin-input" style="max-width:140px"
                 value="<?= number_format((float)($settings['delivery_fee'] ?? 0), 2) ?>"
                 min="0" step="0.01">
          <p style="font-size:11px;color:var(--text-muted);margin-top:4px">Set 0 for free delivery</p>
        </div>
        <div class="admin-form-group">
          <label class="admin-label">🚛 Delivery Info Line <span style="color:var(--text-muted);font-weight:400">(shown on every product page)</span></label>
          <input type="text" name="delivery_info" class="admin-input"
                 value="<?= htmlspecialchars($settings['delivery_info'] ?? 'Estimate delivery times: <strong>2–5 business days</strong> across Egypt.') ?>"
                 placeholder="Estimate delivery times: 2–5 business days across Egypt.">
          <p style="font-size:11px;color:var(--text-muted);margin-top:4px">Supports &lt;strong&gt; tags for bold text</p>
        </div>
        <div class="admin-form-group">
          <label class="admin-label">↩ Return Info Line <span style="color:var(--text-muted);font-weight:400">(shown on every product page)</span></label>
          <input type="text" name="return_info" class="admin-input"
                 value="<?= htmlspecialchars($settings['return_info'] ?? 'Return within <strong>7 days</strong> of purchase. Unused items in original packaging.') ?>"
                 placeholder="Return within 7 days of purchase.">
          <p style="font-size:11px;color:var(--text-muted);margin-top:4px">Supports &lt;strong&gt; tags for bold text</p>
        </div>
        <div class="admin-form-group">
          <label class="admin-label">📄 Full Shipping Policy <span style="color:var(--text-muted);font-weight:400">(Shipping &amp; Return tab on product page)</span></label>
          <textarea name="shipping_policy" class="admin-input" rows="5"
                    placeholder="Free shipping on orders over 499 EGP.&#10;Delivery takes 2–5 business days.&#10;Returns accepted within 7 days of receipt."><?= htmlspecialchars($settings['shipping_policy'] ?? "Free shipping on orders over 499 EGP.\nDelivery takes 2–5 business days.\nReturns accepted within 7 days of receipt.") ?></textarea>
        </div>
      </div>

      <!-- Order Notifications Email -->
      <div class="admin-card" style="margin-bottom:20px">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:6px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">
          🔔 Order Notification Emails
        </h3>
        <p style="font-size:12px;color:var(--text-muted);margin-bottom:16px">
          Every time a customer places an order, a notification email is sent to the address below.
        </p>
        <div class="admin-form-group">
          <label class="admin-label">📬 Send Notifications To (your email)</label>
          <input type="email" name="notify_email" class="admin-input"
                 value="<?= htmlspecialchars($settings['notify_email'] ?? '') ?>"
                 placeholder="you@example.com">
          <p style="font-size:11px;color:var(--text-muted);margin-top:4px">Leave empty to disable email notifications</p>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px">
          <div class="admin-form-group" style="margin-bottom:0">
            <label class="admin-label">Sender Name</label>
            <input type="text" name="notify_from_name" class="admin-input"
                   value="<?= htmlspecialchars($settings['notify_from_name'] ?? 'DUHN FRAGRANCES') ?>"
                   placeholder="DUHN FRAGRANCES">
          </div>
          <div class="admin-form-group" style="margin-bottom:0">
            <label class="admin-label">Sender Email</label>
            <input type="email" name="notify_from_email" class="admin-input"
                   value="<?= htmlspecialchars($settings['notify_from_email'] ?? '') ?>"
                   placeholder="noreply@duhnfragrances.com">
          </div>
        </div>

        <!-- SMTP Config -->
        <div style="background:rgba(248,196,23,0.06);border:1px solid rgba(248,196,23,0.2);border-radius:8px;padding:16px;margin-top:4px">
          <p style="font-size:12px;font-weight:700;color:var(--accent);margin:0 0 12px;letter-spacing:.04em">
            ⚡ SMTP — Required for reliable email delivery
          </p>
          <p style="font-size:11px;color:#bbb;margin:0 0 14px;line-height:1.6">
            For <strong style="color:#fff">Gmail</strong>: host = <code style="background:rgba(255,255,255,0.08);color:#F8C417;padding:1px 5px;border-radius:3px">smtp.gmail.com</code>, port = <code style="background:rgba(255,255,255,0.08);color:#F8C417;padding:1px 5px;border-radius:3px">587</code>, user = your Gmail address, password = App Password (not your login password).<br>
            For <strong style="color:#fff">Hostinger</strong>: host = <code style="background:rgba(255,255,255,0.08);color:#F8C417;padding:1px 5px;border-radius:3px">mail.yourdomain.com</code>, port = <code style="background:rgba(255,255,255,0.08);color:#F8C417;padding:1px 5px;border-radius:3px">587</code>, user = your email address.
          </p>
          <div style="display:grid;grid-template-columns:1fr auto;gap:12px;margin-bottom:12px">
            <div class="admin-form-group" style="margin-bottom:0">
              <label class="admin-label">SMTP Host</label>
              <input type="text" name="smtp_host" class="admin-input"
                     value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>"
                     placeholder="smtp.gmail.com">
            </div>
            <div class="admin-form-group" style="margin-bottom:0">
              <label class="admin-label">Port</label>
              <input type="number" name="smtp_port" class="admin-input" style="width:90px"
                     value="<?= (int)($settings['smtp_port'] ?? 587) ?>"
                     placeholder="587">
            </div>
          </div>
          <div class="admin-form-group" style="margin-bottom:12px">
            <label class="admin-label">SMTP Username (email address)</label>
            <input type="email" name="smtp_user" class="admin-input"
                   value="<?= htmlspecialchars($settings['smtp_user'] ?? '') ?>"
                   placeholder="you@gmail.com" autocomplete="off">
          </div>
          <div class="admin-form-group" style="margin-bottom:0">
            <label class="admin-label">SMTP Password <?= !empty($settings['smtp_pass']) ? '<span style="color:var(--success);font-size:11px;font-weight:400">✓ saved</span>' : '' ?></label>
            <input type="password" name="smtp_pass" class="admin-input"
                   placeholder="<?= !empty($settings['smtp_pass']) ? '••••••••••••' : 'Enter SMTP password' ?>"
                   autocomplete="new-password">
            <p style="font-size:11px;color:var(--text-muted);margin-top:4px">Leave blank to keep the existing password</p>
          </div>
        </div>
      </div>

      <button type="submit" class="btn-admin-gold" style="width:100%">Save Store Settings</button>
    </form>

    <!-- Test Email Button -->
    <div style="margin-top:12px">
      <button type="button" onclick="sendTestEmail()"
              id="test-email-btn"
              style="width:100%;padding:12px;background:transparent;border:1.5px solid rgba(248,196,23,0.4);color:var(--accent);border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all 0.2s">
        <i class="ph ph-paper-plane-tilt"></i> Send Test Email
      </button>
      <div id="test-email-result" style="display:none;margin-top:10px;padding:12px 14px;border-radius:8px;font-size:13px;line-height:1.5"></div>
    </div>

    <script>
    async function sendTestEmail() {
      const btn    = document.getElementById('test-email-btn');
      const result = document.getElementById('test-email-result');

      btn.disabled  = true;
      btn.innerHTML = '<i class="ph ph-spinner"></i> Sending...';
      result.style.display = 'none';

      try {
        const res  = await fetch('/admin/actions/test_email.php', { credentials: 'include' });
        const raw  = await res.text();   // get raw text first

        // Strip UTF-8 BOM (\uFEFF) that some PHP files emit before JSON
        const cleaned = raw.replace(/^\uFEFF+/, '').trim();
        let json = null;
        try { json = JSON.parse(cleaned); } catch (_) { /* not JSON */ }

        result.style.display = 'block';

        if (json) {
          result.style.background = json.ok ? 'rgba(40,167,69,0.1)' : 'rgba(220,53,69,0.1)';
          result.style.border     = json.ok ? '1px solid #28a745'    : '1px solid #dc3545';
          result.style.color      = json.ok ? '#28a745'              : '#dc3545';
          result.textContent      = json.message;
        } else {
          // Show raw PHP output so we can see the actual error
          result.style.background = 'rgba(220,53,69,0.1)';
          result.style.border     = '1px solid #dc3545';
          result.style.color      = '#dc3545';
          result.style.fontFamily = 'monospace';
          result.style.fontSize   = '11px';
          result.style.whiteSpace = 'pre-wrap';
          result.textContent      = '❌ PHP returned non-JSON. Raw output:\n\n' + raw.substring(0, 800);
        }

      } catch (e) {
        result.style.display    = 'block';
        result.style.background = 'rgba(220,53,69,0.1)';
        result.style.border     = '1px solid #dc3545';
        result.style.color      = '#dc3545';
        result.textContent      = '❌ Fetch failed: ' + e.message;
      }

      btn.disabled  = false;
      btn.innerHTML = '<i class="ph ph-paper-plane-tilt"></i> Send Test Email';
    }
    </script>
  </div>

  <!-- Right column: Branding + Newsletter + Security -->
  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Branding & Logo -->
    <?php
      $currentLogo  = $settings['site_logo']       ?? '';
      $currentLogoH = (int)($settings['site_logo_height'] ?? 36);
      $logoMode     = $settings['logo_mode']        ?? 'text';
      $siteName1    = $settings['site_name_1']      ?? 'DUHN';
      $siteName2    = $settings['site_name_2']      ?? 'FRAGRANCES';
    ?>
    <div class="admin-card">
      <h3 style="font-size:14px;font-weight:700;margin-bottom:6px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">
        🎨 Branding &amp; Logo
      </h3>
      <p style="font-size:12px;color:var(--text-muted);margin-bottom:16px">
        Customise how the store name appears in the header — text only, logo only, or both side by side.
      </p>

      <!-- Live Preview -->
      <div style="margin-bottom:16px">
        <p style="font-size:11px;font-weight:700;color:var(--text-muted);letter-spacing:.05em;text-transform:uppercase;margin-bottom:8px">Header Preview</p>
        <div id="logo-preview-wrap" style="padding:14px 20px;background:#07090f;border:1px solid var(--admin-border);border-radius:8px;display:flex;align-items:center;gap:10px;min-height:64px">
          <!-- rendered by JS on load -->
        </div>
      </div>

      <form method="POST" enctype="multipart/form-data" id="branding-form">
        <input type="hidden" name="save_branding" value="1">

        <!-- Mode selector -->
        <div class="admin-form-group">
          <label class="admin-label">Display Mode</label>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <?php foreach(['text'=>'✏️ Text Only','image'=>'🖼 Logo Only','both'=>'🖼+✏️ Logo + Text'] as $val=>$lbl): ?>
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:7px 12px;border:1px solid var(--admin-border);border-radius:7px;font-size:12px;font-weight:600;transition:all .15s;<?= $logoMode===$val ? 'border-color:var(--accent);background:rgba(248,196,23,.08);color:var(--accent)' : 'color:#aaa' ?>">
              <input type="radio" name="logo_mode" value="<?= $val ?>" <?= $logoMode===$val?'checked':'' ?> style="display:none" onchange="updateLogoPreview()">
              <?= $lbl ?>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Text Part 1 & 2 -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div class="admin-form-group" style="margin-bottom:0">
            <label class="admin-label">Text — Part 1 <span style="color:var(--text-muted);font-weight:400">(white)</span></label>
            <input type="text" name="site_name_1" id="inp-name1" class="admin-input"
                   value="<?= htmlspecialchars($siteName1) ?>"
                   oninput="updateLogoPreview()" placeholder="DUHN">
          </div>
          <div class="admin-form-group" style="margin-bottom:0">
            <label class="admin-label">Text — Part 2 <span style="color:var(--text-muted);font-weight:400">(gold)</span></label>
            <input type="text" name="site_name_2" id="inp-name2" class="admin-input"
                   value="<?= htmlspecialchars($siteName2) ?>"
                   oninput="updateLogoPreview()" placeholder="FRAGRANCES">
          </div>
        </div>
        <p style="font-size:11px;color:var(--text-muted);margin:6px 0 16px">Leave Part 2 empty for a single-colour name.</p>

        <!-- Upload logo -->
        <div class="admin-form-group">
          <label class="admin-label">Logo Image <span style="color:var(--text-muted);font-weight:400">(PNG, SVG, WebP · transparent bg · max 2MB)</span></label>
          <?php if ($currentLogo): ?>
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;padding:8px 10px;background:#111;border-radius:6px">
            <img src="<?= htmlspecialchars($currentLogo) ?>?v=<?= time() ?>" style="height:32px;object-fit:contain" alt="logo">
            <span style="font-size:11px;color:#6fcf97;flex:1">✓ Logo uploaded</span>
            <button type="button" onclick="removeLogo()" style="background:none;border:none;color:var(--error);cursor:pointer;font-size:11px;font-weight:700">✕ Remove</button>
          </div>
          <?php endif; ?>
          <input type="file" name="site_logo" id="logo-file-input"
                 accept="image/png,image/jpeg,image/svg+xml,image/webp,image/gif"
                 style="font-size:12px;color:var(--text-muted)"
                 onchange="onLogoFileChange(this)">
        </div>

        <!-- Logo height -->
        <div class="admin-form-group">
          <label class="admin-label">Logo Height (px) <span style="color:var(--text-muted);font-weight:400">20–80, default 36</span></label>
          <input type="number" name="site_logo_height" id="inp-logo-h" class="admin-input" style="max-width:90px"
                 value="<?= $currentLogoH ?>" min="20" max="80" step="2"
                 oninput="updateLogoPreview()">
        </div>

        <!-- Hidden remove flag -->
        <input type="hidden" name="remove_logo" id="remove-logo-flag" value="">

        <button type="submit" class="btn-admin-gold" style="width:100%">
          <i class="ph ph-floppy-disk"></i> Save Branding
        </button>
      </form>
    </div>

    <!-- Newsletter Popup -->
    <div class="admin-card">
      <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">
        📧 Newsletter Popup (20% Discount)
      </h3>
      <p style="font-size:12px;color:var(--text-muted);margin:-8px 0 16px">
        When a visitor enters their email, a unique one-time 20% promo code is generated, stored in Promo Codes, and emailed to them automatically.
      </p>
      <form method="POST">
        <input type="hidden" name="save_newsletter_popup" value="1">

        <label style="display:flex;justify-content:space-between;align-items:center;cursor:pointer;margin-bottom:18px">
          <span class="admin-label" style="margin-bottom:0">Show Popup to Visitors</span>
          <label class="toggle-switch">
            <input type="checkbox" name="nl_popup_enabled"
                   <?= ($settings['nl_popup_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
            <span class="toggle-slider"></span>
          </label>
        </label>

        <div class="admin-form-group">
          <label class="admin-label">Delay Before Showing (ms)</label>
          <input type="number" name="nl_popup_delay" class="admin-input" style="max-width:120px"
                 value="<?= (int)($settings['nl_popup_delay'] ?? 1800) ?>" min="0" step="100">
          <p style="font-size:11px;color:var(--text-muted);margin-top:4px">1800 ms = 1.8 seconds after page load</p>
        </div>

        <div class="admin-form-group">
          <label class="admin-label">Eyebrow Label</label>
          <input type="text" name="nl_popup_eyebrow" class="admin-input"
                 value="<?= htmlspecialchars($settings['nl_popup_eyebrow'] ?? 'SIGNUP FOR EMAILS') ?>">
        </div>

        <div class="admin-form-group">
          <label class="admin-label">Headline</label>
          <input type="text" name="nl_popup_title" class="admin-input"
                 value="<?= htmlspecialchars($settings['nl_popup_title'] ?? 'GET 20% DISCOUNT SHIPPED TO YOUR INBOX') ?>">
        </div>

        <div class="admin-form-group">
          <label class="admin-label">Description</label>
          <textarea name="nl_popup_desc" class="admin-input" rows="2"
                    style="resize:vertical"><?= htmlspecialchars($settings['nl_popup_desc'] ?? '') ?></textarea>
        </div>

        <div class="admin-form-group">
          <label class="admin-label">Button Text</label>
          <input type="text" name="nl_popup_btn_text" class="admin-input"
                 value="<?= htmlspecialchars($settings['nl_popup_btn_text'] ?? 'SUBSCRIBE') ?>">
        </div>

        <button type="submit" class="btn-admin-gold" style="width:100%">Save Popup Settings</button>
      </form>
      <div style="margin-top:12px;padding:10px 14px;background:rgba(248,196,23,0.08);border:1px solid rgba(248,196,23,0.2);border-radius:8px">
        <p style="margin:0;font-size:12px;color:var(--text-muted)">
          📊 View subscriber list and their promo codes →
          <a href="/admin/newsletter.php" style="color:var(--accent);font-weight:700">Newsletter Subscribers</a>
        </p>
      </div>
    </div>

    <!-- Social Proof -->
    <div class="admin-card">
      <h3 style="font-size:14px;font-weight:700;margin-bottom:4px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">
        🔥 Social Proof Badge
      </h3>
      <p style="font-size:11px;color:var(--text-muted);margin-bottom:16px">Shows "X people are viewing this · trending now" on product pages</p>
      <form method="POST">
        <input type="hidden" name="save_social_proof" value="1">

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
          <span style="font-size:14px">Enable Social Proof Badge</span>
          <label class="toggle-switch">
            <input type="checkbox" name="social_proof_enabled"
                   <?= ($settings['social_proof_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
            <span class="toggle-slider"></span>
          </label>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
          <div class="admin-form-group" style="margin-bottom:0">
            <label class="admin-label">Min Viewers</label>
            <input type="number" name="social_proof_min" class="admin-input"
                   value="<?= (int)($settings['social_proof_min'] ?? 3) ?>" min="1" max="99">
          </div>
          <div class="admin-form-group" style="margin-bottom:0">
            <label class="admin-label">Max Viewers</label>
            <input type="number" name="social_proof_max" class="admin-input"
                   value="<?= (int)($settings['social_proof_max'] ?? 18) ?>" min="2" max="999">
          </div>
        </div>
        <p style="font-size:11px;color:var(--text-muted);margin-bottom:12px">Number changes every 2 hours per product. Recommended: 3–18.</p>
        <button type="submit" class="btn-admin-gold" style="width:100%">
          <i class="ph ph-floppy-disk"></i> Save Social Proof
        </button>
      </form>
    </div>

    <!-- Security -->
    <div class="admin-card">
      <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">
        🔒 Change Admin Password
      </h3>
      <form method="POST">
        <input type="hidden" name="change_password" value="1">
        <div class="admin-form-group">
          <label class="admin-label">Current Password</label>
          <input type="password" name="current_password" class="admin-input" required autocomplete="current-password">
        </div>
        <div class="admin-form-group">
          <label class="admin-label">New Password</label>
          <input type="password" name="new_password" class="admin-input" required minlength="8" autocomplete="new-password">
        </div>
        <div class="admin-form-group">
          <label class="admin-label">Confirm New Password</label>
          <input type="password" name="confirm_password" class="admin-input" required minlength="8" autocomplete="new-password">
        </div>
        <button type="submit" class="btn-admin-outline" style="width:100%">Change Password</button>
      </form>
    </div>

  </div><!-- /right column -->

</div><!-- /two-col grid -->

<script>
/* ── Branding preview ─────────────────────────────────────────── */
let _previewLogoSrc = <?= json_encode($currentLogo) ?>;

function getMode() {
  const r = document.querySelector('input[name="logo_mode"]:checked');
  return r ? r.value : 'text';
}

function updateLogoPreview() {
  const wrap  = document.getElementById('logo-preview-wrap');
  if (!wrap) return;
  const mode  = getMode();
  const n1    = document.getElementById('inp-name1')?.value  || 'DUHN';
  const n2    = document.getElementById('inp-name2')?.value  || 'FRAGRANCES';
  const h     = parseInt(document.getElementById('inp-logo-h')?.value) || 36;

  let html = '';

  if ((mode === 'image' || mode === 'both') && _previewLogoSrc) {
    html += `<img src="${_previewLogoSrc}" style="height:${h}px;width:auto;object-fit:contain;display:block" alt="logo">`;
  }
  if (mode === 'text' || mode === 'both' || !_previewLogoSrc) {
    const part2 = n2 ? `&nbsp;<span style="color:#CBBA9C;font-weight:700">${n2}</span>` : '';
    html += `<span style="font-family:'Jost',sans-serif;font-size:17px;font-weight:700;letter-spacing:.12em;color:#fff;white-space:nowrap">${n1}${part2}</span>`;
  }

  wrap.innerHTML = html || `<span style="color:#555;font-size:12px">Preview will appear here</span>`;

  // Highlight active mode radio labels
  document.querySelectorAll('input[name="logo_mode"]').forEach(r => {
    const lbl = r.closest('label');
    if (!lbl) return;
    if (r.checked) {
      lbl.style.borderColor = 'var(--accent)';
      lbl.style.background  = 'rgba(248,196,23,.08)';
      lbl.style.color       = 'var(--accent)';
    } else {
      lbl.style.borderColor = 'var(--admin-border)';
      lbl.style.background  = '';
      lbl.style.color       = '#aaa';
    }
  });
}

function onLogoFileChange(input) {
  if (!input.files || !input.files[0]) return;
  const reader = new FileReader();
  reader.onload = e => {
    _previewLogoSrc = e.target.result;
    document.getElementById('remove-logo-flag').value = '';
    updateLogoPreview();
  };
  reader.readAsDataURL(input.files[0]);
}

function removeLogo() {
  if (!confirm('Remove the logo image?')) return;
  _previewLogoSrc = '';
  document.getElementById('remove-logo-flag').value = '1';
  // Switch to text mode automatically
  const textRadio = document.querySelector('input[name="logo_mode"][value="text"]');
  if (textRadio) { textRadio.checked = true; }
  // Hide the logo row
  const logoRow = document.getElementById('logo-file-input')?.closest('.admin-form-group');
  const previewRow = document.querySelector('[style*="✓ Logo uploaded"]')?.closest('div[style*="padding:8px"]');
  if (previewRow) previewRow.style.display = 'none';
  updateLogoPreview();
}

// Init on load
document.addEventListener('DOMContentLoaded', updateLogoPreview);
document.querySelectorAll('input[name="logo_mode"]').forEach(r => r.addEventListener('change', updateLogoPreview));
</script>

<!-- ══════════════════════════════════════════════════════════════
     PAYMENT GATEWAY — KASHIER.IO
══════════════════════════════════════════════════════════════ -->
<div style="margin-top:40px">
  <h2 style="font-size:16px;font-weight:700;margin-bottom:20px;display:flex;align-items:center;gap:10px">
    <i class="ph ph-credit-card" style="color:var(--accent)"></i> Payment Gateway
  </h2>

  <?php
  $kMid      = $settings['kashier_mid']      ?? '';
  $kMode     = $settings['kashier_mode']     ?? 'test';
  $kVerified = $settings['kashier_verified'] ?? '0';
  $kHasKey   = !empty($settings['kashier_api_key']);
  $kEnabled  = $kMid && $kHasKey;
  ?>

  <form method="POST">
    <input type="hidden" name="save_kashier" value="1">
    <div class="admin-card" style="max-width:640px">

      <!-- Status badge -->
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--admin-border)">
        <div style="display:flex;align-items:center;gap:10px">
          <span style="font-size:14px;font-weight:700;letter-spacing:.05em">Kashier.io</span>
          <span style="font-size:11px;color:#888">Egypt's leading payment gateway</span>
        </div>
        <?php if ($kEnabled && $kVerified === '1'): ?>
        <span style="background:rgba(40,167,69,.15);color:#28a745;font-size:12px;font-weight:700;padding:4px 12px;border-radius:20px;border:1px solid rgba(40,167,69,.3)">
          ✅ Active
        </span>
        <?php elseif ($kEnabled): ?>
        <span style="background:rgba(255,193,7,.15);color:#ffc107;font-size:12px;font-weight:700;padding:4px 12px;border-radius:20px;border:1px solid rgba(255,193,7,.3)">
          ⚠️ Not Verified
        </span>
        <?php else: ?>
        <span style="background:rgba(136,136,136,.15);color:#888;font-size:12px;font-weight:700;padding:4px 12px;border-radius:20px;border:1px solid rgba(136,136,136,.3)">
          ⬜ Not Configured
        </span>
        <?php endif; ?>
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Merchant ID (MID)</label>
        <input type="text" name="kashier_mid" class="admin-input"
               value="<?= htmlspecialchars($kMid) ?>"
               placeholder="MID-XXXX-XXXX">
        <p style="font-size:11px;color:var(--text-muted);margin-top:4px">
          Found in your Kashier merchant portal → Account → under your username.
        </p>
      </div>

      <div class="admin-form-group">
        <label class="admin-label">
          API Key (Payment Secret)
          <?= $kHasKey ? '<span style="color:var(--success);font-size:11px;font-weight:400">✓ saved</span>' : '' ?>
        </label>
        <input type="password" name="kashier_api_key" class="admin-input"
               placeholder="<?= $kHasKey ? '••••••••••••••••••••' : 'Paste your Kashier API key' ?>"
               autocomplete="new-password">
        <p style="font-size:11px;color:var(--text-muted);margin-top:4px">
          Found in Kashier portal → Developers → API Keys. Used to sign payment hashes. Never shown publicly.
        </p>
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Mode</label>
        <div style="display:flex;gap:12px;margin-top:4px">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 18px;border-radius:8px;border:1.5px solid <?= $kMode==='test' ? 'var(--accent)' : 'var(--admin-border)' ?>;background:<?= $kMode==='test' ? 'rgba(248,196,23,.08)' : 'transparent' ?>">
            <input type="radio" name="kashier_mode" value="test" <?= $kMode==='test' ? 'checked' : '' ?> style="accent-color:var(--accent)">
            <div>
              <span style="font-weight:600;font-size:13px">🧪 Test Mode</span>
              <p style="margin:0;font-size:11px;color:var(--text-muted)">Use Kashier test cards — no real charges</p>
            </div>
          </label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 18px;border-radius:8px;border:1.5px solid <?= $kMode==='live' ? '#28a745' : 'var(--admin-border)' ?>;background:<?= $kMode==='live' ? 'rgba(40,167,69,.08)' : 'transparent' ?>">
            <input type="radio" name="kashier_mode" value="live" <?= $kMode==='live' ? 'checked' : '' ?> style="accent-color:#28a745">
            <div>
              <span style="font-weight:600;font-size:13px">🟢 Live Mode</span>
              <p style="margin:0;font-size:11px;color:var(--text-muted)">Real customer payments</p>
            </div>
          </label>
        </div>
      </div>

      <div style="padding:12px 14px;background:rgba(248,196,23,.06);border:1px solid rgba(248,196,23,.2);border-radius:8px;font-size:12px;color:var(--text-muted);margin-bottom:18px">
        <strong style="color:var(--accent)">📋 Setup:</strong>
        Your Kashier callback URL (enter in Kashier portal under Redirect URL / Webhook):<br>
        <code style="color:#F8C417;background:rgba(255,255,255,0.06);padding:3px 8px;border-radius:4px;display:inline-block;margin-top:4px;font-size:11px">
          <?= defined('APP_URL') ? APP_URL : 'https://duhnfragrances.com' ?>/kashier-callback.php
        </code>
      </div>

      <button type="submit" class="btn-admin-gold" style="width:100%">
        <i class="ph ph-plugs-connected"></i> Save & Test Connection
      </button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
