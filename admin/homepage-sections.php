<?php
/**
 * DUHN FRAGRANCES — Admin: Homepage Sections Manager
 * Controls: Newsletter Popup · Brand Banner · Collection Editorial · Inspiration Posts
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/config/database.php';

$db      = Database::getInstance();
$success = '';
$error   = '';

// ── Load all settings ────────────────────────────────────────────
$rows = $db->query("SELECT `key`, `value` FROM `settings`")->fetchAll();
$s    = [];
foreach ($rows as $row) { $s[$row['key']] = $row['value']; }

// ── Upsert helper ────────────────────────────────────────────────
function save($db, $key, $value) {
    $db->prepare("INSERT INTO `settings` (`key`, `value`) VALUES (:k, :v)
                  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
       ->execute([':k' => $key, ':v' => $value]);
}

// ── Upload helper ─────────────────────────────────────────────────
function uploadImage($fileKey, $subDir, $filename) {
    $allowed = ['image/jpeg','image/png','image/webp'];
    $tmp     = $_FILES[$fileKey]['tmp_name'] ?? '';
    if (empty($tmp) || !is_uploaded_file($tmp)) return null;
    $mime = mime_content_type($tmp);
    $size = $_FILES[$fileKey]['size'] ?? 0;
    if (!in_array($mime, $allowed) || $size > 5 * 1024 * 1024) return null;
    $ext  = match($mime) { 'image/webp' => 'webp', 'image/png' => 'png', default => 'jpg' };
    $dir  = __DIR__ . "/../public/images/{$subDir}/";
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $fname = $filename . '.' . $ext;
    if (move_uploaded_file($tmp, $dir . $fname)) {
        return "/public/images/{$subDir}/{$fname}";
    }
    return null;
}

// ════════════════════════════════════════════════════════════════
// POST HANDLERS
// ════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── 1. Newsletter Popup ──────────────────────────────────────
    if (isset($_POST['save_popup'])) {
        try {
            save($db, 'nl_popup_enabled',  isset($_POST['nl_popup_enabled'])  ? '1' : '0');
            save($db, 'nl_popup_subtitle', trim($_POST['nl_popup_subtitle']   ?? ''));
            save($db, 'nl_popup_title',    trim($_POST['nl_popup_title']      ?? ''));
            save($db, 'nl_popup_desc',     trim($_POST['nl_popup_desc']       ?? ''));
            save($db, 'nl_popup_btn_text',           trim($_POST['nl_popup_btn_text']           ?? ''));
            save($db, 'nl_popup_delay',              (string)max(0, (int)($_POST['nl_popup_delay'] ?? 1800)));
            save($db, 'wallet_enabled',              isset($_POST['wallet_enabled'])             ? '1' : '0');
            save($db, 'wallet_discount_per_product', (string)max(0, (int)($_POST['wallet_discount_per_product'] ?? 50)));
            $success = 'Newsletter popup settings saved.';
        } catch (Throwable $e) { $error = $e->getMessage(); }
    }

    // ── Reset Popup for All Users ────────────────────────────────
    if (isset($_POST['reset_popup_all'])) {
        try {
            save($db, 'nl_popup_reset_at', (string)time());
            $success = '✅ Popup reset — all users will see it again on their next visit.';
        } catch (Throwable $e) { $error = $e->getMessage(); }
    }

    // ── 2. Brand Banner ──────────────────────────────────────────
    if (isset($_POST['save_brand_banner'])) {
        try {
            save($db, 'brand_banner_bg_color',  ltrim(trim($_POST['brand_banner_bg_color']  ?? '#CBBA9C'), '#'));
            save($db, 'brand_banner_bg_pattern', trim($_POST['brand_banner_bg_pattern'] ?? 'diagonal'));
            save($db, 'brand_banner_eyebrow',   trim($_POST['brand_banner_eyebrow']    ?? ''));
            save($db, 'brand_banner_title',     trim($_POST['brand_banner_title']      ?? ''));
            save($db, 'brand_banner_subtitle',  trim($_POST['brand_banner_subtitle']   ?? ''));
            save($db, 'brand_banner_btn_text',  trim($_POST['brand_banner_btn_text']   ?? ''));
            save($db, 'brand_banner_btn_url',   trim($_POST['brand_banner_btn_url']    ?? '/collections.php'));
            $success = 'Brand banner saved.';
        } catch (Throwable $e) { $error = $e->getMessage(); }
    }

    // ── 3. Collection Editorial Banner ───────────────────────────
    if (isset($_POST['save_coll_editorial'])) {
        try {
            save($db, 'coll_ed_main_title',     trim($_POST['coll_ed_main_title']     ?? ''));
            save($db, 'coll_ed_main_link_url',  trim($_POST['coll_ed_main_link_url']  ?? ''));
            save($db, 'coll_ed_main_link_text', trim($_POST['coll_ed_main_link_text'] ?? ''));
            for ($i = 1; $i <= 2; $i++) {
                save($db, "coll_ed_item{$i}_title",    trim($_POST["coll_ed_item{$i}_title"]    ?? ''));
                save($db, "coll_ed_item{$i}_link_url", trim($_POST["coll_ed_item{$i}_link_url"] ?? ''));
                save($db, "coll_ed_item{$i}_link_text",trim($_POST["coll_ed_item{$i}_link_text"]?? ''));
            }
            $success = 'Collection editorial banner saved.';
        } catch (Throwable $e) { $error = $e->getMessage(); }
    }

    // ── 5. Feature Cards (Why DUHN) ─────────────────────────────
    if (isset($_POST['save_why'])) {
        try {
            for ($i = 1; $i <= 4; $i++) {
                save($db, "why_{$i}_icon",    trim($_POST["why_{$i}_icon"]    ?? ''));
                save($db, "why_{$i}_title",   trim($_POST["why_{$i}_title"]   ?? ''));
                save($db, "why_{$i}_desc",    trim($_POST["why_{$i}_desc"]    ?? ''));
                save($db, "why_{$i}_enabled", isset($_POST["why_{$i}_enabled"]) ? '1' : '0');
            }
            $success = 'Feature cards saved.';
        } catch (Throwable $e) { $error = $e->getMessage(); }
    }

    // ── 6. Ticker Speeds ─────────────────────────────────────────
    if (isset($_POST['save_speeds'])) {
        try {
            save($db, 'ann_bar_speed', (string)max(5, (int)($_POST['ann_bar_speed'] ?? 32)));
            save($db, 'promo_speed',   (string)max(5, (int)($_POST['promo_speed']   ?? 22)));
            $success = 'Ticker speeds saved.';
        } catch (Throwable $e) { $error = $e->getMessage(); }
    }

    // ── 8. Collections Section Heading ───────────────────────────
    if (isset($_POST['save_collections'])) {
        try {
            save($db, 'collections_title', trim($_POST['collections_title'] ?? 'SHOP BY COLLECTION'));
            $success = 'Collections section saved.';
        } catch (Throwable $e) { $error = $e->getMessage(); }
    }

    // ── 7. Featured Section Heading ──────────────────────────────
    if (isset($_POST['save_featured'])) {
        try {
            save($db, 'featured_title',    trim($_POST['featured_title']    ?? 'BEST SELLER'));
            save($db, 'featured_subtitle', trim($_POST['featured_subtitle'] ?? 'Best Seller Product This Week!'));
            $success = 'Featured section saved.';
        } catch (Throwable $e) { $error = $e->getMessage(); }
    }

    // ── 4. Inspiration Posts ─────────────────────────────────────
    if (isset($_POST['save_inspiration'])) {
        try {
            for ($i = 1; $i <= 3; $i++) {
                save($db, "inspo_{$i}_title",        trim($_POST["inspo_{$i}_title"]        ?? ''));
                save($db, "inspo_{$i}_desc",         trim($_POST["inspo_{$i}_desc"]         ?? ''));
                save($db, "inspo_{$i}_link_url",     trim($_POST["inspo_{$i}_link_url"]     ?? ''));
                save($db, "inspo_{$i}_link_text",    trim($_POST["inspo_{$i}_link_text"]    ?? ''));
                save($db, "inspo_{$i}_mode",         trim($_POST["inspo_{$i}_mode"]         ?? 'url'));
                save($db, "inspo_{$i}_page_body",    trim($_POST["inspo_{$i}_page_body"]    ?? ''));
                save($db, "inspo_{$i}_page_cta_text",trim($_POST["inspo_{$i}_page_cta_text"]?? 'Add to Cart Now'));
                save($db, "inspo_{$i}_page_cta_url", trim($_POST["inspo_{$i}_page_cta_url"] ?? '/collections.php'));

                // Image upload
                $uploaded = uploadImage("inspo_{$i}_image", 'inspiration', "inspo-{$i}");
                if ($uploaded) {
                    save($db, "inspo_{$i}_image", $uploaded);
                } else {
                    // Keep existing
                    $existing = trim($_POST["inspo_{$i}_image_existing"] ?? '');
                    if ($existing) save($db, "inspo_{$i}_image", $existing);
                }
            }
            $success = 'Inspiration posts saved.';
        } catch (Throwable $e) { $error = $e->getMessage(); }
    }

    // Reload settings after save
    $rows = $db->query("SELECT `key`, `value` FROM `settings`")->fetchAll();
    $s    = [];
    foreach ($rows as $row) { $s[$row['key']] = $row['value']; }
}

$adminTitle = 'Homepage Sections';
require_once __DIR__ . '/includes/header.php';
$ts = time();
?>

<?php if ($success): ?><div class="admin-alert success"><i class="ph ph-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="admin-alert error"><i class="ph ph-warning-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- ── TABS ──────────────────────────────────────────────────────── -->
<div style="display:flex;gap:4px;margin-bottom:28px;border-bottom:1px solid var(--admin-border);padding-bottom:0">
  <?php
  $tabs = [
    'popup'     => ['🔔', 'Newsletter Popup'],
    'banner'    => ['🎨', 'Brand Banner'],
    'editorial' => ['🖼', 'Collection Banner'],
    'inspo'     => ['✨', 'Inspiration Posts'],
    'why'       => ['⭐', 'Feature Cards'],
    'speeds'    => ['⚡', 'Ticker Speeds'],
    'featured'    => ['🏆', 'Best Seller'],
    'collections' => ['🗂️', 'Collections'],
  ];
  $activeTab = $_GET['tab'] ?? 'popup';
  foreach ($tabs as $key => [$icon, $label]):
    $isActive = ($activeTab === $key);
  ?>
  <a href="?tab=<?= $key ?>"
     style="padding:10px 20px;font-size:13px;font-weight:<?= $isActive ? '700' : '500' ?>;
            color:<?= $isActive ? 'var(--accent)' : '#aaa' ?>;
            border-bottom:2px solid <?= $isActive ? 'var(--accent)' : 'transparent' ?>;
            text-decoration:none;transition:all 0.2s;margin-bottom:-1px">
    <?= $icon ?> <?= $label ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- ══════════════════════════════════════════════════════════════
     TAB 1: NEWSLETTER POPUP
══════════════════════════════════════════════════════════════ -->
<?php if ($activeTab === 'popup'): ?>

<div style="display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start">

  <form method="POST">
    <input type="hidden" name="save_popup" value="1">
    <div class="admin-card">
      <h2 style="font-size:15px;font-weight:700;margin-bottom:20px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">
        🔔 Newsletter Popup Settings
      </h2>

      <div class="admin-form-group">
        <label style="display:flex;justify-content:space-between;align-items:center;cursor:pointer">
          <span class="admin-label" style="margin-bottom:0">Popup Enabled</span>
          <label class="toggle-switch">
            <input type="checkbox" name="nl_popup_enabled"
                   <?= ($s['nl_popup_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
            <span class="toggle-slider"></span>
          </label>
        </label>
        <p style="font-size:11px;color:var(--text-muted)">Show this popup to visitors on their first visit.</p>
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Delay Before Showing (ms)</label>
        <input type="number" name="nl_popup_delay" class="admin-input" style="max-width:160px"
               value="<?= (int)($s['nl_popup_delay'] ?? 1800) ?>" min="0" step="100">
        <p style="font-size:11px;color:var(--text-muted)">1800 = 1.8 seconds. 0 = show instantly.</p>
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Top Eyebrow Text</label>
        <input type="text" name="nl_popup_subtitle" class="admin-input"
               value="<?= htmlspecialchars($s['nl_popup_subtitle'] ?? 'SIGNUP FOR EMAILS') ?>"
               placeholder="SIGNUP FOR EMAILS">
        <p style="font-size:11px;color:var(--text-muted)">Small uppercase text at the top of the popup.</p>
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Main Title</label>
        <textarea name="nl_popup_title" class="admin-input" rows="3"
                  placeholder="GET 20% DISCOUNT SHIPPED TO YOUR INBOX"><?= htmlspecialchars($s['nl_popup_title'] ?? 'GET 20% DISCOUNT SHIPPED TO YOUR INBOX') ?></textarea>
        <p style="font-size:11px;color:var(--text-muted)">Large bold heading inside the popup.</p>
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Description Text</label>
        <textarea name="nl_popup_desc" class="admin-input" rows="3"
                  placeholder="Let's Subscribe to our newsletter..."><?= htmlspecialchars($s['nl_popup_desc'] ?? "Let's Subscribe to our newsletter and we will ship 20% discount code today") ?></textarea>
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Subscribe Button Text</label>
        <input type="text" name="nl_popup_btn_text" class="admin-input"
               value="<?= htmlspecialchars($s['nl_popup_btn_text'] ?? 'SUBSCRIBE') ?>"
               placeholder="SUBSCRIBE">
      </div>

      <button type="submit" class="btn-admin-gold" style="width:100%">
        <i class="ph ph-floppy-disk"></i> Save Popup Settings
      </button>

      <div style="border-top:1px solid var(--admin-border);margin-top:24px;padding-top:24px">
        <h4 style="font-size:13px;font-weight:700;margin-bottom:4px;color:var(--accent);display:flex;align-items:center;gap:6px">
          <i class="ph ph-wallet"></i> Subscriber Wallet Discount
        </h4>
        <p style="font-size:11px;color:var(--text-muted);margin-bottom:18px">When enabled, subscribers get a fixed discount per product on every order — except products they've already purchased.</p>

        <div class="admin-form-group">
          <label style="display:flex;justify-content:space-between;align-items:center;cursor:pointer">
            <span class="admin-label" style="margin-bottom:0">Wallet Discount Enabled</span>
            <label class="toggle-switch">
              <input type="checkbox" name="wallet_enabled" <?= ($s['wallet_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
              <span class="toggle-slider"></span>
            </label>
          </label>
          <p style="font-size:11px;color:var(--text-muted)">Subscribers see their discount automatically applied in the cart.</p>
        </div>

        <div class="admin-form-group">
          <label class="admin-label">Discount Per Product (EGP)</label>
          <div style="display:flex;align-items:center;gap:12px">
            <input type="number" name="wallet_discount_per_product" class="admin-input" style="max-width:140px"
                   value="<?= (int)($s['wallet_discount_per_product'] ?? 50) ?>" min="0" step="5">
            <span style="font-size:12px;color:var(--text-muted)">EGP per eligible product</span>
          </div>
          <p style="font-size:11px;color:var(--text-muted);margin-top:4px">E.g. 50 = subscriber saves 50 EGP on each new product they buy.</p>
        </div>
      </div>

    </div>
  </form>

  <!-- Reset Popup (separate form — no reload of main settings) -->
  <div style="margin-top:16px" id="reset-popup-wrap">
    <form method="POST" onsubmit="return confirm('This will make the newsletter popup appear again for ALL visitors. Continue?')">
      <input type="hidden" name="reset_popup_all" value="1">
      <button type="submit" class="btn-admin-outline" style="width:100%;gap:8px">
        <i class="ph ph-arrow-counter-clockwise"></i> Reset Popup for All Users
      </button>
    </form>
    <?php if (!empty($s['nl_popup_reset_at'])): ?>
    <p style="font-size:11px;color:var(--text-muted);margin-top:6px;text-align:center">
      Last reset: <strong><?= date('d M Y — H:i', (int)$s['nl_popup_reset_at']) ?></strong>
    </p>
    <?php endif; ?>
  </div>

  <!-- Preview -->
  <div>
    <div style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text-muted);margin-bottom:10px">Live Preview</div>
    <div style="background:#fff;border-radius:4px;padding:32px 24px;text-align:center;color:#111;box-shadow:0 8px 40px rgba(0,0,0,0.4)">
      <p style="font-size:9px;letter-spacing:.2em;text-transform:uppercase;color:#666;margin-bottom:10px" id="prev-subtitle"><?= htmlspecialchars($s['nl_popup_subtitle'] ?? 'SIGNUP FOR EMAILS') ?></p>
      <div style="width:30px;height:2px;background:#111;margin:0 auto 16px"></div>
      <h3 style="font-size:14px;font-weight:700;text-transform:uppercase;line-height:1.3;margin-bottom:10px" id="prev-title"><?= htmlspecialchars($s['nl_popup_title'] ?? 'GET 20% DISCOUNT SHIPPED TO YOUR INBOX') ?></h3>
      <p style="font-size:11px;color:#666;line-height:1.6;margin-bottom:16px" id="prev-desc"><?= htmlspecialchars($s['nl_popup_desc'] ?? "Let's Subscribe to our newsletter and we will ship 20% discount code today") ?></p>
      <div style="display:flex;margin-bottom:12px">
        <div style="flex:1;height:36px;border:1px solid #ddd;border-right:none;display:flex;align-items:center;padding:0 10px;font-size:10px;color:#aaa">Enter your email...</div>
        <div style="padding:0 14px;background:#111;color:#fff;font-size:9px;font-weight:700;display:flex;align-items:center;letter-spacing:.12em" id="prev-btn"><?= htmlspecialchars($s['nl_popup_btn_text'] ?? 'SUBSCRIBE') ?></div>
      </div>
      <span style="font-size:11px;color:#888;text-decoration:underline">No, Thanks.</span>
    </div>
    <script>
    document.querySelector('[name=nl_popup_subtitle]')?.addEventListener('input', e => document.getElementById('prev-subtitle').textContent = e.target.value);
    document.querySelector('[name=nl_popup_title]')?.addEventListener('input', e => document.getElementById('prev-title').textContent = e.target.value);
    document.querySelector('[name=nl_popup_desc]')?.addEventListener('input', e => document.getElementById('prev-desc').textContent = e.target.value);
    document.querySelector('[name=nl_popup_btn_text]')?.addEventListener('input', e => document.getElementById('prev-btn').textContent = e.target.value);
    </script>
  </div>

</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════════
     TAB 2: BRAND BANNER
══════════════════════════════════════════════════════════════ -->
<?php if ($activeTab === 'banner'): ?>

<?php
$bgColor  = $s['brand_banner_bg_color']  ?? 'CBBA9C';
$bgColor  = ltrim($bgColor, '#');
$pattern  = $s['brand_banner_bg_pattern'] ?? 'diagonal';
$eyebrow  = $s['brand_banner_eyebrow']   ?? 'DUHN FRAGRANCES';
$title    = $s['brand_banner_title']     ?? "YOU ONLY GET ONE CHANCE\nTO MAKE THE FIRST IMPRESSION.";
$subtitle = $s['brand_banner_subtitle']  ?? 'Premium fragrances crafted for those who refuse to be forgotten.';
$btnText  = $s['brand_banner_btn_text']  ?? 'EXPLORE ALL SCENTS';
$btnUrl   = $s['brand_banner_btn_url']   ?? '/collections.php';
?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start">

  <form method="POST">
    <input type="hidden" name="save_brand_banner" value="1">
    <div class="admin-card">
      <h2 style="font-size:15px;font-weight:700;margin-bottom:20px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">
        🎨 Brand Banner Settings
      </h2>

      <!-- Background Color -->
      <div class="admin-form-group">
        <label class="admin-label">Background Color</label>
        <div style="display:flex;gap:10px;align-items:center">
          <input type="color" id="bg-color-picker" value="#<?= htmlspecialchars($bgColor) ?>"
                 style="width:52px;height:40px;border:1px solid var(--admin-border);border-radius:6px;background:none;cursor:pointer;padding:2px"
                 oninput="document.querySelector('[name=brand_banner_bg_color]').value=this.value;updateBannerPreview()">
          <input type="text" name="brand_banner_bg_color" class="admin-input" style="max-width:160px"
                 value="#<?= htmlspecialchars($bgColor) ?>" placeholder="#CBBA9C"
                 oninput="document.getElementById('bg-color-picker').value=this.value;updateBannerPreview()">
        </div>
        <p style="font-size:11px;color:var(--text-muted)">Hex color code for the banner background (e.g. #CBBA9C for champagne gold)</p>
      </div>

      <!-- Background Pattern -->
      <div class="admin-form-group">
        <label class="admin-label">Background Pattern / Texture</label>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px">
          <?php foreach (['diagonal','dots','lines','none'] as $pat): ?>
          <label style="cursor:pointer;text-align:center">
            <div style="height:48px;border:2px solid <?= $pattern === $pat ? 'var(--accent)' : 'var(--admin-border)' ?>;border-radius:6px;margin-bottom:5px;display:flex;align-items:center;justify-content:center;font-size:11px;color:#aaa;
            <?php
            if ($pat === 'diagonal') echo "background:repeating-linear-gradient(135deg,rgba(0,0,0,0.1) 0,rgba(0,0,0,0.1) 1px,transparent 0,transparent 50%)";
            elseif ($pat === 'dots') echo "background:radial-gradient(circle,rgba(0,0,0,0.2) 1px,transparent 1px) 0 0 / 8px 8px";
            elseif ($pat === 'lines') echo "background:repeating-linear-gradient(0deg,rgba(0,0,0,0.1) 0,rgba(0,0,0,0.1) 1px,transparent 0,transparent 12px)";
            else echo "background:var(--admin-card)";
            ?>
            ">
              <?= ucfirst($pat) ?>
            </div>
            <input type="radio" name="brand_banner_bg_pattern" value="<?= $pat ?>"
                   <?= $pattern === $pat ? 'checked' : '' ?>
                   onchange="updateBannerPreview()"
                   style="display:none">
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Text fields -->
      <div class="admin-form-group">
        <label class="admin-label">Eyebrow Text <span style="color:var(--text-muted);font-weight:400">(small text above title)</span></label>
        <input type="text" name="brand_banner_eyebrow" class="admin-input"
               value="<?= htmlspecialchars($eyebrow) ?>" placeholder="DUHN FRAGRANCES"
               oninput="updateBannerPreview()">
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Main Title <span style="color:var(--text-muted);font-weight:400">(use \n for line break)</span></label>
        <textarea name="brand_banner_title" class="admin-input" rows="3"
                  placeholder="YOU ONLY GET ONE CHANCE\nTO MAKE THE FIRST IMPRESSION."
                  oninput="updateBannerPreview()"><?= htmlspecialchars($title) ?></textarea>
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Subtitle / Description</label>
        <textarea name="brand_banner_subtitle" class="admin-input" rows="2"
                  placeholder="Premium fragrances crafted for those who refuse to be forgotten."
                  oninput="updateBannerPreview()"><?= htmlspecialchars($subtitle) ?></textarea>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="admin-form-group">
          <label class="admin-label">Button Text</label>
          <input type="text" name="brand_banner_btn_text" class="admin-input"
                 value="<?= htmlspecialchars($btnText) ?>" placeholder="EXPLORE ALL SCENTS"
                 oninput="updateBannerPreview()">
        </div>
        <div class="admin-form-group">
          <label class="admin-label">Button Link</label>
          <input type="text" name="brand_banner_btn_url" class="admin-input"
                 value="<?= htmlspecialchars($btnUrl) ?>" placeholder="/collections.php">
        </div>
      </div>

      <button type="submit" class="btn-admin-gold" style="width:100%">
        <i class="ph ph-floppy-disk"></i> Save Brand Banner
      </button>
    </div>
  </form>

  <!-- Live Preview -->
  <div>
    <div style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text-muted);margin-bottom:10px">Live Preview</div>
    <div id="banner-preview" style="border-radius:6px;padding:48px 32px;text-align:center;position:relative;overflow:hidden;min-height:220px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;background:#<?= htmlspecialchars($bgColor) ?>"
         data-pattern="<?= htmlspecialchars($pattern) ?>">
      <div id="banner-pattern-layer" style="position:absolute;inset:0;pointer-events:none;opacity:0.5;
      <?php
      if ($pattern === 'diagonal') echo "background:repeating-linear-gradient(135deg,rgba(0,0,0,0.06) 0,rgba(0,0,0,0.06) 1px,transparent 0,transparent 50%)";
      elseif ($pattern === 'dots')  echo "background:radial-gradient(circle,rgba(0,0,0,0.15) 1px,transparent 1px) 0 0 / 12px 12px";
      elseif ($pattern === 'lines') echo "background:repeating-linear-gradient(0deg,rgba(0,0,0,0.06) 0,rgba(0,0,0,0.06) 1px,transparent 0,transparent 16px)";
      ?>
      "></div>
      <p id="pv-eyebrow" style="font-size:9px;letter-spacing:.22em;text-transform:uppercase;color:rgba(0,0,0,0.5);margin:0;position:relative;z-index:1"><?= htmlspecialchars($eyebrow) ?></p>
      <h2 id="pv-title" style="font-size:18px;font-weight:300;text-transform:uppercase;letter-spacing:.06em;color:#111;line-height:1.25;margin:0;position:relative;z-index:1;white-space:pre-line"><?= htmlspecialchars(str_replace('\n', "\n", $title)) ?></h2>
      <p id="pv-subtitle" style="font-size:12px;color:rgba(0,0,0,0.6);margin:0;max-width:300px;line-height:1.6;position:relative;z-index:1"><?= htmlspecialchars($subtitle) ?></p>
      <div id="pv-btn" style="padding:12px 28px;background:#fff;color:#111;font-size:10px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;position:relative;z-index:1"><?= htmlspecialchars($btnText) ?></div>
    </div>
    <p style="font-size:11px;color:var(--text-muted);margin-top:8px;text-align:center">Preview updates as you type ↑</p>
  </div>
</div>

<script>
function updateBannerPreview() {
  const preview = document.getElementById('banner-preview');
  const patLayer = document.getElementById('banner-pattern-layer');

  // Background color
  const colorVal = document.querySelector('[name=brand_banner_bg_color]')?.value || '#CBBA9C';
  preview.style.background = colorVal;

  // Pattern
  const pat = document.querySelector('[name=brand_banner_bg_pattern]:checked')?.value || 'diagonal';
  const patStyles = {
    diagonal: "repeating-linear-gradient(135deg,rgba(0,0,0,0.06) 0,rgba(0,0,0,0.06) 1px,transparent 0,transparent 50%)",
    dots:     "radial-gradient(circle,rgba(0,0,0,0.15) 1px,transparent 1px) 0 0 / 12px 12px",
    lines:    "repeating-linear-gradient(0deg,rgba(0,0,0,0.06) 0,rgba(0,0,0,0.06) 1px,transparent 0,transparent 16px)",
    none:     "none"
  };
  if (patLayer) patLayer.style.background = patStyles[pat] || 'none';

  // Text fields
  const qs = n => document.querySelector(`[name="${n}"]`);
  document.getElementById('pv-eyebrow').textContent   = qs('brand_banner_eyebrow')?.value  || '';
  document.getElementById('pv-title').textContent     = (qs('brand_banner_title')?.value || '').replace(/\\n/g,'\n');
  document.getElementById('pv-subtitle').textContent  = qs('brand_banner_subtitle')?.value || '';
  document.getElementById('pv-btn').textContent       = qs('brand_banner_btn_text')?.value || '';

  // Highlight pattern radios
  document.querySelectorAll('[name=brand_banner_bg_pattern]').forEach(r => {
    const lbl = r.closest('label')?.querySelector('div');
    if (lbl) lbl.style.borderColor = r.checked ? 'var(--accent)' : 'var(--admin-border)';
  });
}
document.querySelectorAll('[name=brand_banner_bg_pattern]').forEach(r => r.addEventListener('change', updateBannerPreview));
</script>

<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════════
     TAB 3: COLLECTION EDITORIAL BANNER
══════════════════════════════════════════════════════════════ -->
<?php if ($activeTab === 'editorial'): ?>

<form method="POST">
  <input type="hidden" name="save_coll_editorial" value="1">
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">

    <!-- Main (Left) Panel -->
    <div class="admin-card">
      <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">
        🖼 Left Big Panel <span style="font-size:11px;opacity:.5">(uses 1st collection image)</span>
      </h3>
      <div class="admin-form-group">
        <label class="admin-label">Panel Title</label>
        <textarea name="coll_ed_main_title" class="admin-input" rows="3"
                  placeholder="Check Out The Latest Collection Of Fragrances"><?= htmlspecialchars($s['coll_ed_main_title'] ?? 'Check Out The Latest Collection Of Fragrances') ?></textarea>
        <p style="font-size:11px;color:var(--text-muted)">Large white text at the bottom-left of the panel.</p>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="admin-form-group">
          <label class="admin-label">Link Text</label>
          <input type="text" name="coll_ed_main_link_text" class="admin-input"
                 value="<?= htmlspecialchars($s['coll_ed_main_link_text'] ?? 'SHOP COLLECTION') ?>"
                 placeholder="SHOP COLLECTION">
        </div>
        <div class="admin-form-group">
          <label class="admin-label">Link URL</label>
          <input type="text" name="coll_ed_main_link_url" class="admin-input"
                 value="<?= htmlspecialchars($s['coll_ed_main_link_url'] ?? '/collections.php') ?>"
                 placeholder="/collections.php">
        </div>
      </div>
      <p style="font-size:11px;color:var(--text-muted);background:rgba(248,196,23,0.06);padding:10px;border-radius:6px;border:1px solid rgba(248,196,23,0.15)">
        💡 The background image for this panel comes from your <strong>1st Collection</strong>'s cover image.<br>
        To change it: <a href="/admin/collections.php" style="color:var(--accent)">Edit Collections →</a>
      </p>
    </div>

    <!-- Right Stacked Panels -->
    <div style="display:flex;flex-direction:column;gap:20px">
      <?php for ($i = 1; $i <= 2; $i++):
        $label = $i === 1 ? 'Top-Right Panel (2nd Collection Image)' : 'Bottom-Right Panel (3rd Collection Image)';
      ?>
      <div class="admin-card">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">
          🖼 <?= $label ?>
        </h3>
        <div class="admin-form-group">
          <label class="admin-label">Panel Title</label>
          <input type="text" name="coll_ed_item<?= $i ?>_title" class="admin-input"
                 value="<?= htmlspecialchars($s["coll_ed_item{$i}_title"] ?? ($i === 1 ? 'Shop Exquisite Fragrances' : 'Top Luxury Scents')) ?>"
                 placeholder="Panel title">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div class="admin-form-group">
            <label class="admin-label">Link Text</label>
            <input type="text" name="coll_ed_item<?= $i ?>_link_text" class="admin-input"
                   value="<?= htmlspecialchars($s["coll_ed_item{$i}_link_text"] ?? 'SHOP NOW') ?>" placeholder="SHOP NOW">
          </div>
          <div class="admin-form-group">
            <label class="admin-label">Link URL</label>
            <input type="text" name="coll_ed_item<?= $i ?>_link_url" class="admin-input"
                   value="<?= htmlspecialchars($s["coll_ed_item{$i}_link_url"] ?? ($i === 1 ? '/collections.php?slug=for-him' : '/collections.php?slug=for-her')) ?>"
                   placeholder="/collections.php?slug=...">
          </div>
        </div>
        <p style="font-size:11px;color:var(--text-muted);background:rgba(248,196,23,0.06);padding:10px;border-radius:6px;border:1px solid rgba(248,196,23,0.15)">
          💡 Background image = <strong><?= $i+1 ?><?= $i === 1 ? 'nd' : 'rd' ?> Collection</strong> cover.
          <a href="/admin/collections.php" style="color:var(--accent)">Edit Collections →</a>
        </p>
      </div>
      <?php endfor; ?>
    </div>

  </div>

  <div style="margin-top:20px">
    <button type="submit" class="btn-admin-gold" style="width:100%">
      <i class="ph ph-floppy-disk"></i> Save Collection Editorial Banner
    </button>
  </div>
</form>

<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════════
     TAB 4: INSPIRATION POSTS
══════════════════════════════════════════════════════════════ -->
<?php if ($activeTab === 'inspo'): ?>

<form method="POST" enctype="multipart/form-data">
  <input type="hidden" name="save_inspiration" value="1">

  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px">
    <?php for ($i = 1; $i <= 3; $i++):
      $img = $s["inspo_{$i}_image"] ?? '';
      $imgExists = !empty($img) && file_exists(__DIR__ . '/..' . $img);
    ?>
    <div class="admin-card">
      <h3 style="font-size:13px;font-weight:700;margin-bottom:14px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">
        ✨ Post <?= $i ?>
      </h3>

      <!-- Image -->
      <div class="admin-form-group">
        <label class="admin-label">Card Image</label>
        <?php if ($imgExists): ?>
        <img src="<?= htmlspecialchars($img) ?>?v=<?= $ts ?>"
             id="inspo-prev-<?= $i ?>"
             style="width:100%;height:140px;object-fit:cover;border-radius:6px;margin-bottom:8px">
        <?php else: ?>
        <div id="inspo-prev-<?= $i ?>"
             style="width:100%;height:100px;background:#2A2A2A;border-radius:6px;margin-bottom:8px;display:flex;align-items:center;justify-content:center;color:#555;font-size:12px">
          No image — upload below
        </div>
        <?php endif; ?>
        <input type="hidden" name="inspo_<?= $i ?>_image_existing" value="<?= htmlspecialchars($img) ?>">
        <input type="file" name="inspo_<?= $i ?>_image" accept="image/*"
               style="font-size:12px;color:var(--text-muted)"
               onchange="previewInspo(this, <?= $i ?>)">
        <p style="font-size:11px;color:var(--text-muted);margin-top:3px">JPG/PNG/WebP · Max 5MB</p>
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Title</label>
        <input type="text" name="inspo_<?= $i ?>_title" class="admin-input"
               value="<?= htmlspecialchars($s["inspo_{$i}_title"] ?? '') ?>"
               placeholder="Inspiration title">
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Description</label>
        <textarea name="inspo_<?= $i ?>_desc" class="admin-input" rows="3"
                  placeholder="Short description..."><?= htmlspecialchars($s["inspo_{$i}_desc"] ?? '') ?></textarea>
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Link Text <span style="color:var(--text-muted);font-weight:400">(button label)</span></label>
        <input type="text" name="inspo_<?= $i ?>_link_text" class="admin-input"
               value="<?= htmlspecialchars($s["inspo_{$i}_link_text"] ?? 'READ MORE →') ?>"
               placeholder="READ MORE →">
      </div>

      <!-- Mode toggle -->
      <?php $mode = $s["inspo_{$i}_mode"] ?? 'url'; ?>
      <div class="admin-form-group">
        <label class="admin-label">When "<?= htmlspecialchars($s["inspo_{$i}_link_text"] ?? 'READ MORE') ?>" is clicked…</label>
        <div style="display:flex;gap:0;border:1px solid var(--admin-border);border-radius:8px;overflow:hidden;margin-top:4px">
          <label style="flex:1;cursor:pointer">
            <input type="radio" name="inspo_<?= $i ?>_mode" value="url"
                   <?= $mode === 'url' ? 'checked' : '' ?>
                   onchange="toggleInspoMode(<?= $i ?>)"
                   style="display:none">
            <div class="inspo-mode-btn inspo-mode-url-<?= $i ?>"
                 style="padding:10px;text-align:center;font-size:12px;font-weight:600;
                        background:<?= $mode === 'url' ? 'var(--accent)' : 'transparent' ?>;
                        color:<?= $mode === 'url' ? '#000' : '#aaa' ?>;transition:all .2s">
              🔗 URL Redirect
            </div>
          </label>
          <label style="flex:1;cursor:pointer;border-left:1px solid var(--admin-border)">
            <input type="radio" name="inspo_<?= $i ?>_mode" value="page"
                   <?= $mode === 'page' ? 'checked' : '' ?>
                   onchange="toggleInspoMode(<?= $i ?>)"
                   style="display:none">
            <div class="inspo-mode-btn inspo-mode-page-<?= $i ?>"
                 style="padding:10px;text-align:center;font-size:12px;font-weight:600;
                        background:<?= $mode === 'page' ? 'var(--accent)' : 'transparent' ?>;
                        color:<?= $mode === 'page' ? '#000' : '#aaa' ?>;transition:all .2s">
              📄 Open as Page
            </div>
          </label>
        </div>
      </div>

      <!-- URL mode fields -->
      <div id="inspo-url-fields-<?= $i ?>" style="<?= $mode === 'page' ? 'display:none' : '' ?>">
        <div class="admin-form-group">
          <label class="admin-label">Redirect URL</label>
          <input type="text" name="inspo_<?= $i ?>_link_url" class="admin-input"
                 value="<?= htmlspecialchars($s["inspo_{$i}_link_url"] ?? '/collections.php') ?>"
                 placeholder="/collections.php">
          <p style="font-size:11px;color:var(--text-muted)">Visitor is sent to this URL when they click.</p>
        </div>
      </div>

      <!-- Page mode fields -->
      <div id="inspo-page-fields-<?= $i ?>" style="<?= $mode !== 'page' ? 'display:none' : '' ?>">
        <div class="admin-form-group">

          <!-- ── Open Full Editor ───────────────────────────── -->
          <?php
          $hasContent = !empty($s["inspo_{$i}_page_body"]);
          $charCount  = $hasContent ? strlen($s["inspo_{$i}_page_body"]) : 0;
          ?>
          <div style="background:rgba(248,196,23,0.04);border:1px solid rgba(248,196,23,0.15);border-radius:10px;padding:20px;text-align:center">
            <i class="ph ph-layout" style="font-size:36px;color:rgba(248,196,23,0.5);display:block;margin-bottom:10px"></i>
            <p style="font-size:13px;font-weight:600;color:#ccc;margin-bottom:4px">
              <?= $hasContent ? '✅ Page has content' : '📄 No content yet' ?>
            </p>
            <?php if ($hasContent): ?>
            <p style="font-size:11px;color:var(--text-muted);margin-bottom:14px"><?= number_format($charCount) ?> characters saved</p>
            <?php else: ?>
            <p style="font-size:11px;color:var(--text-muted);margin-bottom:14px">Click below to open the visual editor and design this page</p>
            <?php endif; ?>
            <a href="/admin/page-editor.php?id=<?= $i ?>" target="_blank"
               style="display:inline-flex;align-items:center;gap:8px;background:var(--accent);color:#000;border-radius:8px;
                      font-size:13px;font-weight:700;letter-spacing:.04em;padding:11px 24px;text-decoration:none;
                      transition:all .2s;box-shadow:0 4px 16px rgba(248,196,23,0.25)"
               onmouseover="this.style.background='#ffd740'" onmouseout="this.style.background='var(--accent)'">
              <i class="ph ph-layout"></i>
              <?= $hasContent ? 'Edit Page in Full Editor ↗' : 'Open Page Editor ↗' ?>
            </a>
            <?php if ($hasContent): ?>
            <div style="margin-top:12px">
              <a href="/inspo.php?id=<?= $i ?>" target="_blank"
                 style="font-size:11px;color:rgba(248,196,23,0.6);text-decoration:none">
                <i class="ph ph-arrow-square-out"></i> Preview live page →
              </a>
            </div>
            <?php endif; ?>
          </div>

          <!-- ══ PAGE BUILDER WRAPPER (hidden, legacy — kept for compatibility) ════════ -->
          <div class="pb-wrap" id="pb-wrap-<?= $i ?>" style="display:none">

            <!-- ROW 1: Formatting toolbar -->
            <div class="pb-row1">
              <select class="pb-select" onchange="pbExec(<?= $i ?>,'formatBlock',this.value);this.selectedIndex=0" title="Paragraph style">
                <option value="">¶ Style</option>
                <option value="p">Paragraph</option>
                <option value="h2">Heading 2</option>
                <option value="h3">Heading 3</option>
                <option value="h4">Heading 4</option>
                <option value="blockquote">Blockquote</option>
              </select>
              <div class="pb-sep"></div>
              <button type="button" class="pb-icon-btn" onclick="pbExec(<?= $i ?>,'bold')"          title="Bold"><i class="ph-bold ph-text-b"></i></button>
              <button type="button" class="pb-icon-btn" onclick="pbExec(<?= $i ?>,'italic')"        title="Italic"><i class="ph-bold ph-text-italic"></i></button>
              <button type="button" class="pb-icon-btn" onclick="pbExec(<?= $i ?>,'underline')"     title="Underline"><i class="ph-bold ph-text-underline"></i></button>
              <button type="button" class="pb-icon-btn" onclick="pbExec(<?= $i ?>,'strikeThrough')" title="Strikethrough"><i class="ph-bold ph-text-strikethrough"></i></button>
              <div class="pb-sep"></div>
              <button type="button" class="pb-icon-btn" onclick="pbExec(<?= $i ?>,'justifyLeft')"   title="Align left"><i class="ph-bold ph-text-align-left"></i></button>
              <button type="button" class="pb-icon-btn" onclick="pbExec(<?= $i ?>,'justifyCenter')" title="Align center"><i class="ph-bold ph-text-align-center"></i></button>
              <button type="button" class="pb-icon-btn" onclick="pbExec(<?= $i ?>,'justifyRight')"  title="Align right"><i class="ph-bold ph-text-align-right"></i></button>
              <div class="pb-sep"></div>
              <button type="button" class="pb-icon-btn" onclick="pbExec(<?= $i ?>,'insertUnorderedList')" title="Bullet list"><i class="ph-bold ph-list-bullets"></i></button>
              <button type="button" class="pb-icon-btn" onclick="pbExec(<?= $i ?>,'insertOrderedList')"   title="Numbered list"><i class="ph-bold ph-list-numbers"></i></button>
              <div class="pb-sep"></div>
              <button type="button" class="pb-icon-btn" onclick="pbEditLink(<?= $i ?>)" title="Insert / Edit link (click inside a link first to edit it)"><i class="ph-bold ph-link"></i></button>
              <button type="button" class="pb-icon-btn" onclick="pbImg(<?= $i ?>)"   title="Insert image URL"><i class="ph-bold ph-image"></i></button>
              <button type="button" class="pb-icon-btn" onclick="pbUpload(<?= $i ?>)" title="Upload image from device"><i class="ph-bold ph-upload-simple"></i></button>
              <input type="file" id="pb-file-<?= $i ?>" accept="image/*" style="display:none" onchange="pbHandleUpload(<?= $i ?>,this)">
              <button type="button" class="pb-icon-btn" onclick="pbExec(<?= $i ?>,'insertHorizontalRule')" title="Horizontal line"><i class="ph-bold ph-minus"></i></button>
              <button type="button" class="pb-html-btn" onclick="pbToggleCode(<?= $i ?>)" id="pb-code-btn-<?= $i ?>">
                <i class="ph-bold ph-code"></i> HTML
              </button>
            </div>

            <!-- ROW 2: Layout blocks -->
            <div class="pb-row2">
              <span class="pb-row2-label">Insert Block →</span>
              <button type="button" class="pb-block-btn" onclick="pbBlock(<?= $i ?>,'hero')"     title="Full-width hero banner"><i class="ph ph-image-square"></i> Hero</button>
              <button type="button" class="pb-block-btn" onclick="pbBlock(<?= $i ?>,'2col')"     title="Two-column layout"><i class="ph ph-columns"></i> 2 Columns</button>
              <button type="button" class="pb-block-btn" onclick="pbBlock(<?= $i ?>,'quote')"    title="Styled quote"><i class="ph ph-quotes"></i> Quote</button>
              <button type="button" class="pb-block-btn" onclick="pbBlock(<?= $i ?>,'features')" title="Feature checklist"><i class="ph ph-check-square"></i> Features</button>
              <button type="button" class="pb-block-btn" onclick="pbBlock(<?= $i ?>,'cta')"      title="Call-to-action banner"><i class="ph ph-megaphone-simple"></i> CTA</button>
              <button type="button" class="pb-block-btn" onclick="pbBlock(<?= $i ?>,'divider')"  title="Section divider"><i class="ph ph-minus"></i> Divider</button>
            </div>

            <!-- Visual editor -->
            <div class="pb-editor" id="pb-editor-<?= $i ?>"
                 contenteditable="true"
                 data-ph="Start writing your story…"><?= $s["inspo_{$i}_page_body"] ?? '' ?></div>

            <!-- Code view -->
            <textarea class="pb-code" id="pb-code-<?= $i ?>"><?= htmlspecialchars($s["inspo_{$i}_page_body"] ?? '') ?></textarea>

            <!-- Status bar -->
            <div class="pb-statusbar">
              <span class="pb-status-txt" id="pb-wc-<?= $i ?>">0 words</span>
              <button type="button" class="pb-clear-btn" onclick="pbExec(<?= $i ?>,'removeFormat')" title="Clear all formatting"><i class="ph ph-eraser"></i> Clear format</button>
            </div>

          </div><!-- /.pb-wrap -->

          <!-- Hidden sync field for form POST -->
          <input type="hidden" name="inspo_<?= $i ?>_page_body" id="pb-hidden-<?= $i ?>"
                 value="<?= htmlspecialchars($s["inspo_{$i}_page_body"] ?? '') ?>">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div class="admin-form-group">
            <label class="admin-label">CTA Button Text</label>
            <input type="text" name="inspo_<?= $i ?>_page_cta_text" class="admin-input"
                   value="<?= htmlspecialchars($s["inspo_{$i}_page_cta_text"] ?? 'Add to Cart Now') ?>"
                   placeholder="Add to Cart Now">
          </div>
          <div class="admin-form-group">
            <label class="admin-label">CTA Button URL</label>
            <input type="text" name="inspo_<?= $i ?>_page_cta_url" class="admin-input"
                   value="<?= htmlspecialchars($s["inspo_{$i}_page_cta_url"] ?? '/collections.php') ?>"
                   placeholder="/collections.php">
          </div>
        </div>
        <p style="font-size:11px;color:rgba(248,196,23,0.8);background:rgba(248,196,23,0.06);border:1px solid rgba(248,196,23,0.15);padding:8px;border-radius:6px">
          🔗 Visitors will go to: <strong>/inspo.php?id=<?= $i ?></strong>
        </p>
      </div>

    </div>
    <?php endfor; ?>
  </div>

  <div style="margin-top:20px">
    <button type="submit" class="btn-admin-gold" style="width:100%">
      <i class="ph ph-floppy-disk"></i> Save All Inspiration Posts
    </button>
  </div>
</form>

<style>
/* ═══════════════════════════════════════════════════════════
   PAGE BUILDER — Professional Admin Editor
═══════════════════════════════════════════════════════════ */

/* Outer wrapper */
.pb-wrap{border:1px solid rgba(248,196,23,0.22);border-radius:10px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.35)}

/* Row 1 — Formatting toolbar */
.pb-row1{display:flex;align-items:center;gap:0;background:#0f0f0f;border-bottom:1px solid rgba(255,255,255,0.07);padding:0 2px}
.pb-sep{width:1px;height:22px;background:rgba(255,255,255,0.08);margin:0 4px;flex-shrink:0}
.pb-select{background:transparent;border:none;color:#ccc;cursor:pointer;font-size:12px;font-family:inherit;padding:10px 8px;outline:none;min-width:100px}
.pb-select:hover{color:#fff}
.pb-icon-btn{background:transparent;border:none;color:#aaa;cursor:pointer;font-size:16px;padding:8px 9px;transition:all .15s;display:flex;align-items:center;justify-content:center;line-height:1;border-radius:0}
.pb-icon-btn:hover{color:var(--accent);background:rgba(248,196,23,0.09)}
.pb-icon-btn.active{color:var(--accent);background:rgba(248,196,23,0.12)}
.pb-html-btn{margin-left:auto;display:flex;align-items:center;gap:6px;background:rgba(255,255,255,0.05);border:none;border-left:1px solid rgba(255,255,255,0.07);color:#888;cursor:pointer;font-size:11px;font-family:inherit;padding:8px 14px;transition:all .2s;white-space:nowrap;letter-spacing:.04em}
.pb-html-btn:hover{background:rgba(248,196,23,0.1);color:var(--accent)}
.pb-html-btn.active{background:rgba(248,196,23,0.18);color:var(--accent)}

/* Row 2 — Layout blocks bar */
.pb-row2{display:flex;align-items:center;gap:6px;background:#0a0a0a;border-bottom:1px solid rgba(255,255,255,0.06);padding:8px 12px;flex-wrap:wrap}
.pb-row2-label{font-size:9px;font-weight:700;letter-spacing:.12em;color:rgba(248,196,23,0.5);text-transform:uppercase;margin-right:4px;white-space:nowrap}
.pb-block-btn{display:flex;align-items:center;gap:5px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:6px;color:#bbb;cursor:pointer;font-size:11px;font-family:inherit;padding:5px 11px;transition:all .18s;white-space:nowrap;font-weight:500}
.pb-block-btn:hover{background:rgba(248,196,23,0.13);border-color:rgba(248,196,23,0.35);color:#f0c040;transform:translateY(-1px);box-shadow:0 3px 10px rgba(248,196,23,0.12)}
.pb-block-btn i{font-size:14px}

/* The visual editor area */
.pb-editor{background:#141414;color:#e0e0e0;font-family:'Jost',sans-serif;font-size:14px;line-height:1.8;min-height:240px;outline:none;padding:20px 22px;overflow-y:auto}
.pb-editor:focus{outline:none}
.pb-editor:empty::before{content:attr(data-ph);color:#444;pointer-events:none}
.pb-editor h2{font-size:21px;font-weight:700;margin:0 0 12px;letter-spacing:.04em;color:#fff}
.pb-editor h3{font-size:16px;font-weight:600;margin:18px 0 8px;color:#f0c040}
.pb-editor h4{font-size:14px;font-weight:600;margin:14px 0 6px;color:#ccc}
.pb-editor p{margin:0 0 12px}
.pb-editor blockquote{border-left:3px solid #C8A030;padding:14px 20px;margin:18px 0;background:rgba(200,160,48,0.05);font-style:italic;color:#ccc;border-radius:0 6px 6px 0}
.pb-editor ul,.pb-editor ol{padding-left:22px;margin:0 0 12px}
.pb-editor a{color:#C8A030;text-decoration:underline}
.pb-editor hr{border:none;border-top:1px solid rgba(255,255,255,0.1);margin:20px 0}
.pb-editor img{max-width:100%;border-radius:8px;margin:8px 0;display:block}

/* Status bar */
.pb-statusbar{display:flex;align-items:center;justify-content:space-between;background:#0a0a0a;border-top:1px solid rgba(255,255,255,0.06);padding:6px 14px}
.pb-status-txt{font-size:10px;color:#444;letter-spacing:.04em}
.pb-clear-btn{background:none;border:none;color:#555;cursor:pointer;font-size:10px;font-family:inherit;padding:2px 6px;border-radius:4px;transition:all .15s}
.pb-clear-btn:hover{color:#e05555;background:rgba(224,85,85,0.1)}

/* Code view */
.pb-code{background:#0d0d0d;border:none;border-radius:0;color:#88c8a0;font-family:'Courier New',monospace;font-size:12px;line-height:1.6;min-height:240px;padding:18px 20px;resize:vertical;width:100%;box-sizing:border-box;outline:none;display:none}

/* Focus ring on the whole wrap when editor is focused */
.pb-wrap:focus-within{border-color:rgba(248,196,23,0.45);box-shadow:0 0 0 3px rgba(248,196,23,0.06)}
</style>

<script>
/* ─────────────────────────────────────────────────────────────
   PAGE BUILDER — core helpers
───────────────────────────────────────────────────────────── */
function pbFocus(n) { document.getElementById('pb-editor-' + n).focus(); }

function pbExec(n, cmd, val) {
  pbFocus(n);
  document.execCommand(cmd, false, val || null);
}

function pbLink(n) {
  const url  = prompt('Link URL:', 'https://');
  if (!url) return;
  const text = window.getSelection().toString() || prompt('Link text:', 'Click here');
  if (!text) return;
  pbExec(n, 'insertHTML', `<a href="${url.replace(/"/g,'&quot;')}" style="color:#C8A030">${text}</a>`);
}

function pbImg(n) {
  const url = prompt('Image URL (https://...):', '');
  if (!url) return;
  const alt = prompt('Alt text (optional):', '') || '';
  pbExec(n, 'insertHTML',
    `<img src="${url.replace(/"/g,'&quot;')}" alt="${alt}" style="max-width:100%;border-radius:8px;display:block;margin:12px 0">`);
}

function pbUpload(n) {
  document.getElementById('pb-file-' + n).click();
}

function pbHandleUpload(n, input) {
  if (!input.files[0]) return;
  const file = input.files[0];
  const fd   = new FormData();
  fd.append('pb_image', file);
  fd.append('inspo_n', n);
  fetch('/admin/actions/pb_upload.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      if (d.url) {
        pbExec(n, 'insertHTML',
          `<img src="${d.url}" alt="" style="max-width:100%;border-radius:8px;display:block;margin:12px 0">`);
      } else {
        alert('Upload failed: ' + (d.error || 'unknown error'));
      }
    })
    .catch(() => alert('Upload error. Check your connection.'));
  input.value = '';
}

/* ── Word-count + REAL-TIME SYNC to hidden field ──────────── */
function pbUpdateWC(n) {
  const editor = document.getElementById('pb-editor-' + n);
  const wc     = document.getElementById('pb-wc-' + n);
  const hidden = document.getElementById('pb-hidden-' + n);
  if (!editor) return;
  // Sync HTML to hidden field on every keystroke
  if (hidden) hidden.value = editor.innerHTML;
  // Update word count
  if (wc) {
    const text  = editor.innerText.trim();
    const words = text ? text.split(/\s+/).length : 0;
    wc.textContent = words + (words === 1 ? ' word' : ' words');
  }
}
document.addEventListener('DOMContentLoaded', function() {
  [1, 2, 3].forEach(function(n) {
    const editor = document.getElementById('pb-editor-' + n);
    if (editor) {
      pbUpdateWC(n);
      editor.addEventListener('input', () => pbUpdateWC(n));
      // Also sync after block insertions (insertHTML doesn't fire 'input')
      editor.addEventListener('DOMSubtreeModified', () => pbUpdateWC(n));
    }
  });
  // Robust submit sync — final safety net
  const form = document.querySelector('form[enctype="multipart/form-data"]');
  if (form) {
    form.addEventListener('submit', function() {
      [1, 2, 3].forEach(function(n) {
        const editor = document.getElementById('pb-editor-' + n);
        const code   = document.getElementById('pb-code-' + n);
        const hidden = document.getElementById('pb-hidden-' + n);
        if (!editor || !hidden) return;
        hidden.value = (code && code.style.display !== 'none') ? code.value : editor.innerHTML;
      });
    });
  }
});

/* ─────────────────────────────────────────────────────────────
   LAYOUT BLOCKS
───────────────────────────────────────────────────────────── */
const PB_BLOCKS = {
  hero: `<div style="background:linear-gradient(135deg,#111 0%,#1c1c1c 100%);border-radius:12px;padding:48px 32px;text-align:center;margin:0 0 20px">
  <p style="font-size:10px;letter-spacing:.18em;color:#C8A030;text-transform:uppercase;margin:0 0 10px">DUHN FRAGRANCES</p>
  <h2 style="font-size:26px;font-weight:700;color:#fff;margin:0 0 12px;letter-spacing:.04em">Hero Heading</h2>
  <p style="color:#aaa;margin:0 0 24px;max-width:460px;margin-left:auto;margin-right:auto">Add your intro text here.</p>
  <a href="/collections.php" style="display:inline-block;background:#C8A030;color:#000;padding:11px 28px;border-radius:6px;font-weight:700;text-decoration:none;letter-spacing:.06em;font-size:13px">EXPLORE COLLECTION</a>
</div>`,

  '2col': `<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin:16px 0">
  <div>
    <h3 style="margin:0 0 10px;color:#C8A030">Left Heading</h3>
    <p style="color:#ccc;font-size:14px;line-height:1.7">Left column content goes here. Edit freely.</p>
  </div>
  <div>
    <h3 style="margin:0 0 10px;color:#C8A030">Right Heading</h3>
    <p style="color:#ccc;font-size:14px;line-height:1.7">Right column content goes here. Edit freely.</p>
  </div>
</div>`,

  quote: `<blockquote style="border-left:3px solid #C8A030;padding:18px 22px;margin:20px 0;background:rgba(200,160,48,0.05);border-radius:0 8px 8px 0">
  <p style="font-style:italic;color:#ddd;margin:0 0 8px;font-size:16px;line-height:1.7">"Write your quote or highlight sentence here."</p>
  <cite style="font-size:12px;color:#888;font-style:normal">— Name or Source</cite>
</blockquote>`,

  features: `<ul style="list-style:none;padding:0;margin:16px 0">
  <li style="display:flex;gap:12px;padding:12px 0;border-bottom:1px solid rgba(255,255,255,0.06)">
    <span style="color:#C8A030;font-size:18px;flex-shrink:0">✓</span>
    <div><strong style="color:#fff">Feature Title</strong><br><span style="font-size:13px;color:#aaa;line-height:1.6">Feature description here.</span></div>
  </li>
  <li style="display:flex;gap:12px;padding:12px 0;border-bottom:1px solid rgba(255,255,255,0.06)">
    <span style="color:#C8A030;font-size:18px;flex-shrink:0">✓</span>
    <div><strong style="color:#fff">Feature Title</strong><br><span style="font-size:13px;color:#aaa;line-height:1.6">Feature description here.</span></div>
  </li>
  <li style="display:flex;gap:12px;padding:12px 0">
    <span style="color:#C8A030;font-size:18px;flex-shrink:0">✓</span>
    <div><strong style="color:#fff">Feature Title</strong><br><span style="font-size:13px;color:#aaa;line-height:1.6">Feature description here.</span></div>
  </li>
</ul>`,

  cta: `<div style="background:linear-gradient(135deg,rgba(200,160,48,0.1),rgba(200,160,48,0.03));border:1px solid rgba(200,160,48,0.22);border-radius:12px;padding:30px;text-align:center;margin:20px 0">
  <h3 style="color:#C8A030;margin:0 0 8px;font-size:18px">Your CTA Heading</h3>
  <p style="color:#aaa;margin:0 0 20px;font-size:14px">Add supporting copy for the call to action here.</p>
  <a href="/collections.php" style="display:inline-block;background:#C8A030;color:#000;padding:11px 26px;border-radius:6px;font-weight:700;text-decoration:none;letter-spacing:.05em;font-size:13px">SHOP NOW</a>
</div>`,

  divider: `<div style="display:flex;align-items:center;gap:16px;margin:24px 0">
  <div style="flex:1;height:1px;background:rgba(200,160,48,0.25)"></div>
  <span style="font-size:11px;letter-spacing:.12em;color:#C8A030;text-transform:uppercase;white-space:nowrap">Section Title</span>
  <div style="flex:1;height:1px;background:rgba(200,160,48,0.25)"></div>
</div>`
};

function pbBlock(n, type) {
  let html = PB_BLOCKS[type];
  if (!html) return;

  // Prompt for URL + button text on blocks that have buttons
  if (type === 'cta') {
    const btnUrl  = prompt('CTA Button URL (where should it go?):', '/collections.php');
    if (btnUrl === null) return; // cancelled
    const btnText = prompt('CTA Button Text:', 'SHOP NOW');
    if (btnText === null) return;
    const head    = prompt('CTA Heading:', 'Your CTA Heading');
    html = html
      .replace('href="/collections.php"', 'href="' + (btnUrl || '/collections.php') + '"')
      .replace('>SHOP NOW<', '>' + (btnText || 'SHOP NOW') + '<')
      .replace('Your CTA Heading', head || 'Your CTA Heading');
  }
  if (type === 'hero') {
    const btnUrl  = prompt('Hero Button URL:', '/collections.php');
    if (btnUrl === null) return;
    const btnText = prompt('Hero Button Text:', 'EXPLORE COLLECTION');
    if (btnText === null) return;
    html = html
      .replace('href="/collections.php"', 'href="' + (btnUrl || '/collections.php') + '"')
      .replace('>EXPLORE COLLECTION<', '>' + (btnText || 'EXPLORE COLLECTION') + '<');
  }

  pbExec(n, 'insertHTML', html);
  // Sync immediately after block insert (insertHTML doesn't fire 'input')
  setTimeout(() => pbUpdateWC(n), 50);
}

/* ── Edit selected link ────────────────────────────────────── */
function pbEditLink(n) {
  pbFocus(n);
  // Find if cursor is inside an <a> tag
  const sel = window.getSelection();
  if (!sel.rangeCount) return;
  let node = sel.anchorNode;
  while (node && node.nodeName !== 'A' && node !== document.getElementById('pb-editor-' + n)) {
    node = node.parentNode;
  }
  if (node && node.nodeName === 'A') {
    const newUrl = prompt('Edit link URL:', node.getAttribute('href') || '');
    if (newUrl !== null) node.setAttribute('href', newUrl);
    const newText = prompt('Edit link text (leave blank to keep):', node.textContent || '');
    if (newText !== null && newText !== '') node.textContent = newText;
    pbUpdateWC(n);
  } else {
    pbLink(n);
  }
}

/* ── Toggle code view ──────────────────────────────────────── */
function pbToggleCode(n) {
  const editor = document.getElementById('pb-editor-' + n);
  const code   = document.getElementById('pb-code-' + n);
  const btn    = document.getElementById('pb-code-btn-' + n);
  const hidden = document.getElementById('pb-hidden-' + n);
  const inCode = code.style.display !== 'none';
  if (!inCode) {
    // Switch to HTML code view
    code.value           = editor.innerHTML;
    editor.style.display = 'none';
    code.style.display   = 'block';
    btn.classList.add('active');
    // Sync from code textarea to hidden on every change
    code.oninput = function() { if (hidden) hidden.value = this.value; };
  } else {
    // Switch back to visual
    editor.innerHTML     = code.value;
    editor.style.display = 'block';
    code.style.display   = 'none';
    btn.classList.remove('active');
    pbUpdateWC(n);
  }
}

/* ── Sync editors to hidden fields before submit ──────────── */
document.querySelector('form[enctype]').addEventListener('submit', function() {
  [1, 2, 3].forEach(function(n) {
    const editor = document.getElementById('pb-editor-' + n);
    const code   = document.getElementById('pb-code-' + n);
    const hidden = document.getElementById('pb-hidden-' + n);
    if (!editor || !hidden) return;
    hidden.value = (code && code.style.display !== 'none') ? code.value : editor.innerHTML;
  });
});

/* ── Existing functions ────────────────────────────────────── */
function previewInspo(input, n) {
  if (!input.files[0]) return;
  const prev = document.getElementById('inspo-prev-' + n);
  if (!prev) return;
  prev.tagName === 'IMG'
    ? (prev.src = URL.createObjectURL(input.files[0]))
    : (() => {
        const img = document.createElement('img');
        img.src = URL.createObjectURL(input.files[0]);
        img.id = 'inspo-prev-' + n;
        img.style = 'width:100%;height:140px;object-fit:cover;border-radius:6px;margin-bottom:8px';
        prev.replaceWith(img);
      })();
}

function toggleInspoMode(n) {
  const mode = document.querySelector(`[name="inspo_${n}_mode"]:checked`)?.value || 'url';
  document.getElementById('inspo-url-fields-' + n).style.display  = mode === 'url'  ? '' : 'none';
  document.getElementById('inspo-page-fields-' + n).style.display = mode === 'page' ? '' : 'none';
  const urlBtn  = document.querySelector(`.inspo-mode-url-${n}`);
  const pageBtn = document.querySelector(`.inspo-mode-page-${n}`);
  if (urlBtn)  { urlBtn.style.background  = mode === 'url'  ? 'var(--accent)' : 'transparent'; urlBtn.style.color  = mode === 'url'  ? '#000' : '#aaa'; }
  if (pageBtn) { pageBtn.style.background = mode === 'page' ? 'var(--accent)' : 'transparent'; pageBtn.style.color = mode === 'page' ? '#000' : '#aaa'; }
}
</script>

<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════════
     TAB 5: FEATURE CARDS (Why DUHN)
══════════════════════════════════════════════════════════════ -->
<?php if ($activeTab === 'why'):
$whyDefaults = [
  1 => ['icon'=>'ph-medal',          'title'=>'Premium Quality',    'desc'=>'Crafted with the finest fragrance oils — every bottle is a work of art that lasts all day.'],
  2 => ['icon'=>'ph-clock-countdown','title'=>'All-Day Longevity',  'desc'=>'Our 50ml EDP formula is designed for 8–12 hour wear, so you stay memorable from morning to midnight.'],
  3 => ['icon'=>'ph-package',        'title'=>'Free Delivery',      'desc'=>'Every order ships free to your door anywhere in Egypt. Fast, safe, and beautifully packaged.'],
  4 => ['icon'=>'ph-gift',           'title'=>'Buy 2, Get 2 Free',  'desc'=>'Our signature deal — pick any 4 fragrances and pay for only 2. Automatically applied at checkout.'],
];
?>

<p style="font-size:12px;color:var(--text-muted);margin-bottom:20px">
  These 4 cards appear on the homepage below the promo banner. Use any
  <a href="https://phosphoricons.com" target="_blank" style="color:var(--accent)">Phosphor icon name</a>
  (e.g. <code>ph-medal</code>, <code>ph-truck</code>, <code>ph-star</code>).
</p>

<form method="POST">
  <input type="hidden" name="save_why" value="1">
  <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:20px;margin-bottom:20px">
    <?php for ($i = 1; $i <= 4; $i++):
      $wIcon    = $s["why_{$i}_icon"]    ?? $whyDefaults[$i]['icon'];
      $wTitle   = $s["why_{$i}_title"]   ?? $whyDefaults[$i]['title'];
      $wDesc    = $s["why_{$i}_desc"]    ?? $whyDefaults[$i]['desc'];
      $wEnabled = ($s["why_{$i}_enabled"] ?? '1') === '1';
    ?>
    <div class="admin-card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
        <h3 style="font-size:13px;font-weight:700;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">
          Card <?= $i ?>
          <span style="font-size:18px;margin-left:8px"><i class="ph <?= htmlspecialchars($wIcon) ?>" id="icon-prev-<?= $i ?>"></i></span>
        </h3>
        <label class="toggle-switch" title="Show/Hide this card">
          <input type="checkbox" name="why_<?= $i ?>_enabled" <?= $wEnabled ? 'checked' : '' ?>>
          <span class="toggle-slider"></span>
        </label>
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Icon <span style="font-weight:400;color:var(--text-muted)">(Phosphor class without "ph-"… or full e.g. ph-truck)</span></label>
        <input type="text" name="why_<?= $i ?>_icon" class="admin-input"
               value="<?= htmlspecialchars($wIcon) ?>"
               placeholder="ph-medal"
               oninput="document.getElementById('icon-prev-<?= $i ?>').className='ph '+this.value">
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Title</label>
        <input type="text" name="why_<?= $i ?>_title" class="admin-input"
               value="<?= htmlspecialchars($wTitle) ?>"
               placeholder="e.g. Premium Quality">
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Description</label>
        <textarea name="why_<?= $i ?>_desc" class="admin-input" rows="3"
                  placeholder="Short description..."><?= htmlspecialchars($wDesc) ?></textarea>
      </div>
    </div>
    <?php endfor; ?>
  </div>
  <button type="submit" class="btn-admin-gold" style="width:100%">
    <i class="ph ph-floppy-disk"></i> Save Feature Cards
  </button>
</form>

<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════════
     TAB 6: TICKER SPEEDS
══════════════════════════════════════════════════════════════ -->
<?php if ($activeTab === 'speeds'):
  $annSpeed   = (int)($s['ann_bar_speed'] ?? 32);
  $promoSpeed = (int)($s['promo_speed']   ?? 22);
?>

<div style="max-width:560px">
  <form method="POST">
    <input type="hidden" name="save_speeds" value="1">
    <div class="admin-card">
      <h2 style="font-size:15px;font-weight:700;margin-bottom:20px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">
        ⚡ Scrolling Ticker Speeds
      </h2>
      <p style="font-size:12px;color:var(--text-muted);margin-bottom:20px">
        Controls how fast each banner scrolls. <strong>Lower = faster, Higher = slower.</strong><br>
        Recommended: Announcement Bar 25–40s · Promo Strip 15–30s
      </p>

      <div class="admin-form-group">
        <label class="admin-label">🔔 Announcement Bar Speed (seconds)</label>
        <div style="display:flex;gap:12px;align-items:center">
          <input type="range" name="ann_bar_speed" min="5" max="80" step="1"
                 value="<?= $annSpeed ?>"
                 style="flex:1;accent-color:var(--accent)"
                 oninput="document.getElementById('ann-speed-val').textContent=this.value+'s'">
          <span id="ann-speed-val" style="font-size:20px;font-weight:700;color:var(--accent);min-width:50px"><?= $annSpeed ?>s</span>
        </div>
        <p style="font-size:11px;color:var(--text-muted);margin-top:6px">
          Currently: <strong><?= $annSpeed ?>s</strong> — the top announcement bar (OUR FIRST ANNIVERSARY…)
        </p>
      </div>

      <div class="admin-form-group">
        <label class="admin-label">🎯 Promo Strip Speed (seconds)</label>
        <div style="display:flex;gap:12px;align-items:center">
          <input type="range" name="promo_speed" min="5" max="80" step="1"
                 value="<?= $promoSpeed ?>"
                 style="flex:1;accent-color:var(--accent)"
                 oninput="document.getElementById('promo-speed-val').textContent=this.value+'s'">
          <span id="promo-speed-val" style="font-size:20px;font-weight:700;color:var(--accent);min-width:50px"><?= $promoSpeed ?>s</span>
        </div>
        <p style="font-size:11px;color:var(--text-muted);margin-top:6px">
          Currently: <strong><?= $promoSpeed ?>s</strong> — the gold strip below the hero (BUY 2 GET 2 FREE…)
        </p>
      </div>

      <div style="background:rgba(248,196,23,0.06);border:1px solid rgba(248,196,23,0.15);border-radius:8px;padding:12px;margin-bottom:20px">
        <p style="font-size:12px;color:var(--text-muted)">
          💡 <strong>Speed guide:</strong> 5s = very fast · 20s = fast · 35s = medium · 60s = slow · 80s = very slow
        </p>
      </div>

      <button type="submit" class="btn-admin-gold" style="width:100%">
        <i class="ph ph-floppy-disk"></i> Save Ticker Speeds
      </button>
    </div>
  </form>
</div>

<?php endif; ?>

<?php if ($activeTab === 'featured'): ?>
<div class="admin-card" style="max-width:600px">
  <h3 style="font-size:14px;font-weight:700;margin-bottom:20px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">🏆 Best Seller Section Heading</h3>
  <form method="POST">
    <input type="hidden" name="save_featured" value="1">

    <div class="admin-form-group">
      <label class="admin-label">Section Title</label>
      <input type="text" name="featured_title" class="admin-input"
             value="<?= htmlspecialchars($s['featured_title'] ?? 'BEST SELLER') ?>"
             placeholder="BEST SELLER">
      <p style="font-size:11px;color:var(--text-muted);margin-top:4px">The large bold heading shown above the featured products grid.</p>
    </div>

    <div class="admin-form-group">
      <label class="admin-label">Section Subtitle</label>
      <input type="text" name="featured_subtitle" class="admin-input"
             value="<?= htmlspecialchars($s['featured_subtitle'] ?? 'Best Seller Product This Week!') ?>"
             placeholder="Best Seller Product This Week!">
      <p style="font-size:11px;color:var(--text-muted);margin-top:4px">The smaller grey text shown below the title.</p>
    </div>

    <!-- Live preview -->
    <div style="margin:24px 0 20px;padding:24px;background:var(--admin-bg);border:1px solid var(--admin-border);border-radius:8px;text-align:center">
      <div style="font-size:11px;color:var(--text-muted);letter-spacing:.06em;margin-bottom:12px;text-transform:uppercase">Preview</div>
      <div id="prev-title" style="font-size:22px;font-weight:700;letter-spacing:.08em;color:var(--text)"><?= htmlspecialchars($s['featured_title'] ?? 'BEST SELLER') ?></div>
      <div style="width:40px;height:1px;background:var(--accent);margin:10px auto"></div>
      <div id="prev-sub" style="font-size:13px;color:var(--text-muted);margin-top:6px"><?= htmlspecialchars($s['featured_subtitle'] ?? 'Best Seller Product This Week!') ?></div>
    </div>

    <button type="submit" class="btn-admin-gold">Save</button>
  </form>
</div>
<script>
document.querySelector('[name=featured_title]').addEventListener('input', function(){
  document.getElementById('prev-title').textContent = this.value || 'BEST SELLER';
});
document.querySelector('[name=featured_subtitle]').addEventListener('input', function(){
  document.getElementById('prev-sub').textContent = this.value || 'Best Seller Product This Week!';
});
</script>
<?php endif; ?>

<?php if ($activeTab === 'collections'): ?>
<div class="admin-card" style="max-width:600px">
  <h3 style="font-size:14px;font-weight:700;margin-bottom:20px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">🗂️ Shop By Collection — Section Heading</h3>
  <form method="POST">
    <input type="hidden" name="save_collections" value="1">

    <div class="admin-form-group">
      <label class="admin-label">Section Title</label>
      <input type="text" name="collections_title" class="admin-input" id="col-title-input"
             value="<?= htmlspecialchars($s['collections_title'] ?? 'SHOP BY COLLECTION') ?>"
             placeholder="SHOP BY COLLECTION">
      <p style="font-size:11px;color:var(--text-muted);margin-top:4px">The heading shown above the collections grid on the homepage.</p>
    </div>

    <div style="margin:24px 0 20px;padding:24px;background:var(--admin-bg);border:1px solid var(--admin-border);border-radius:8px;text-align:center">
      <div style="font-size:11px;color:var(--text-muted);letter-spacing:.06em;margin-bottom:12px;text-transform:uppercase">Preview</div>
      <div id="col-prev-title" style="font-size:22px;font-weight:700;letter-spacing:.08em;color:var(--text)"><?= htmlspecialchars($s['collections_title'] ?? 'SHOP BY COLLECTION') ?></div>
      <div style="width:40px;height:1px;background:var(--accent);margin:10px auto"></div>
    </div>

    <button type="submit" class="btn-admin-gold">Save</button>
  </form>
</div>
<script>
document.getElementById('col-title-input').addEventListener('input', function(){
  document.getElementById('col-prev-title').textContent = this.value || 'SHOP BY COLLECTION';
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
