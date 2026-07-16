<?php
/**
 * DUHN FRAGRANCES Admin — Navigation Links Manager
 */
require_once __DIR__ . '/../admin/includes/auth_check.php';
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/config/database.php';

$db      = Database::getInstance();
$success = '';
$error   = '';

// Default nav links (used if nothing saved yet)
$defaults = [
    ['label' => 'Home',        'url' => '/index.php',                          'is_visible' => true],
    ['label' => 'NEW DROPS',   'url' => '/collections.php?slug=new-drops',     'is_visible' => true],
    ['label' => 'For Him',     'url' => '/collections.php?slug=for-him',        'is_visible' => true],
    ['label' => 'For Her',     'url' => '/collections.php?slug=for-her',        'is_visible' => true],
    ['label' => 'Bestsellers', 'url' => '/collections.php?slug=bestsellers',    'is_visible' => true],
];

// Load current nav links from DB
function loadNavLinks($db, $defaults): array {
    try {
        $row = $db->query("SELECT `value` FROM `settings` WHERE `key` = 'nav_links' LIMIT 1")->fetch();
        if ($row && !empty($row['value'])) {
            $parsed = json_decode($row['value'], true);
            if (is_array($parsed) && count($parsed) > 0) return $parsed;
        }
    } catch (Throwable $_) {}
    return $defaults;
}

// ── Handle POST ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Add new link ─────────────────────────────────────────────────
    if (isset($_POST['add_link'])) {
        $label = trim($_POST['new_label'] ?? '');
        $url   = trim($_POST['new_url']   ?? '');
        if ($label && $url) {
            $links   = loadNavLinks($db, $defaults);
            $links[] = ['label' => $label, 'url' => $url, 'is_visible' => true];
            try {
                $db->prepare("INSERT INTO settings (`key`,`value`) VALUES ('nav_links',:v) ON DUPLICATE KEY UPDATE `value`=:v")
                   ->execute([':v' => json_encode($links, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
                $success = 'Link added.';
            } catch (Throwable $e) { $error = $e->getMessage(); }
        } else {
            $error = 'Label and URL are required.';
        }
    }

    // ── Save all links (reorder, labels, URLs, visibility) ───────────
    elseif (isset($_POST['save_all'])) {
        $labels     = $_POST['label']      ?? [];
        $urls       = $_POST['url']        ?? [];
        $visibles   = $_POST['visible']    ?? [];
        $sortOrders = $_POST['sort_order'] ?? [];

        $combined = [];
        foreach ($labels as $i => $lbl) {
            $combined[] = [
                'label'      => trim($lbl),
                'url'        => trim($urls[$i] ?? ''),
                'is_visible' => isset($visibles[$i]),
                'sort_order' => (int)($sortOrders[$i] ?? $i),
            ];
        }
        // Sort by sort_order
        usort($combined, fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);
        // Remove sort_order from stored data
        $links = array_map(fn($l) => ['label' => $l['label'], 'url' => $l['url'], 'is_visible' => $l['is_visible']], $combined);

        try {
            $db->prepare("INSERT INTO settings (`key`,`value`) VALUES ('nav_links',:v) ON DUPLICATE KEY UPDATE `value`=:v")
               ->execute([':v' => json_encode($links, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            $success = 'Navigation links saved.';
        } catch (Throwable $e) { $error = $e->getMessage(); }
    }

    // ── Delete a link ─────────────────────────────────────────────────
    elseif (isset($_POST['delete_index'])) {
        $idx   = (int)$_POST['delete_index'];
        $links = loadNavLinks($db, $defaults);
        array_splice($links, $idx, 1);
        try {
            $db->prepare("INSERT INTO settings (`key`,`value`) VALUES ('nav_links',:v) ON DUPLICATE KEY UPDATE `value`=:v")
               ->execute([':v' => json_encode($links, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            $success = 'Link removed.';
        } catch (Throwable $e) { $error = $e->getMessage(); }
    }

    // ── Reset to defaults ─────────────────────────────────────────────
    elseif (isset($_POST['reset_defaults'])) {
        try {
            $db->prepare("INSERT INTO settings (`key`,`value`) VALUES ('nav_links',:v) ON DUPLICATE KEY UPDATE `value`=:v")
               ->execute([':v' => json_encode($defaults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            $success = 'Navigation reset to defaults.';
        } catch (Throwable $e) { $error = $e->getMessage(); }
    }
}

$navLinks  = loadNavLinks($db, $defaults);
$adminTitle = 'Navigation Links';
require_once __DIR__ . '/../admin/includes/header.php';
?>

<?php if ($success): ?><div class="admin-alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="admin-alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">
  <div>
    <h2 style="font-size:20px;font-weight:700;margin:0">Navigation Links</h2>
    <p style="color:var(--text-muted);font-size:13px;margin:4px 0 0">Control which links appear in the main navigation bar.</p>
  </div>
  <form method="POST" onsubmit="return confirm('Reset navigation to the original defaults?')">
    <button name="reset_defaults" class="btn-admin-outline" style="font-size:12px">
      <i class="ph ph-arrow-counter-clockwise"></i> Reset to Defaults
    </button>
  </form>
</div>

<!-- Current Links (save / reorder / toggle / delete) -->
<div class="admin-card" style="margin-bottom:24px">
  <h3 style="font-size:13px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">Current Links</h3>
  <p style="font-size:12px;color:var(--text-muted);margin-bottom:18px">Change the sort order numbers to reorder links. Uncheck the eye icon to hide a link from visitors.</p>

  <form method="POST" id="save-form">
    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <thead>
        <tr style="border-bottom:1px solid var(--admin-border)">
          <th style="padding:8px 10px;text-align:left;color:var(--text-muted);font-weight:600;font-size:11px;letter-spacing:.05em;width:50px">ORDER</th>
          <th style="padding:8px 10px;text-align:left;color:var(--text-muted);font-weight:600;font-size:11px;letter-spacing:.05em">LABEL</th>
          <th style="padding:8px 10px;text-align:left;color:var(--text-muted);font-weight:600;font-size:11px;letter-spacing:.05em">URL</th>
          <th style="padding:8px 10px;text-align:center;color:var(--text-muted);font-weight:600;font-size:11px;letter-spacing:.05em;width:80px">VISIBLE</th>
          <th style="padding:8px 10px;width:60px"></th>
        </tr>
      </thead>
      <tbody id="nav-tbody">
        <?php foreach ($navLinks as $i => $link): ?>
        <tr class="nav-row" style="border-bottom:1px solid var(--admin-border)">
          <td style="padding:10px">
            <input type="number" name="sort_order[]" value="<?= $i + 1 ?>" min="1" max="50"
                   class="admin-input" style="width:58px;text-align:center;padding:6px 8px">
          </td>
          <td style="padding:10px">
            <input type="text" name="label[]" value="<?= htmlspecialchars($link['label']) ?>"
                   class="admin-input" placeholder="Link label" required>
          </td>
          <td style="padding:10px">
            <input type="text" name="url[]" value="<?= htmlspecialchars($link['url']) ?>"
                   class="admin-input" placeholder="/collections.php?slug=..." required>
          </td>
          <td style="padding:10px;text-align:center">
            <label class="toggle-switch" style="display:inline-flex">
              <input type="checkbox" name="visible[<?= $i ?>]" <?= !empty($link['is_visible']) ? 'checked' : '' ?>>
              <span class="toggle-slider"></span>
            </label>
          </td>
          <td style="padding:10px;text-align:center">
            <form method="POST" style="display:inline" onsubmit="return confirm('Remove this link?')">
              <input type="hidden" name="delete_index" value="<?= $i ?>">
              <button type="submit" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:16px;padding:4px 8px" title="Delete">
                <i class="ph ph-trash"></i>
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div style="margin-top:16px;display:flex;justify-content:flex-end">
      <button type="submit" name="save_all" class="btn-admin-gold">
        <i class="ph ph-floppy-disk"></i> Save Navigation
      </button>
    </div>
  </form>
</div>

<!-- Add New Link -->
<div class="admin-card">
  <h3 style="font-size:13px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">Add New Link</h3>
  <form method="POST">
    <div style="display:grid;grid-template-columns:1fr 2fr auto;gap:12px;align-items:end">
      <div>
        <label class="admin-label">Label</label>
        <input type="text" name="new_label" class="admin-input" placeholder="e.g. SALE" required>
      </div>
      <div>
        <label class="admin-label">URL</label>
        <input type="text" name="new_url" class="admin-input" placeholder="e.g. /collections.php?slug=sale" required>
      </div>
      <button type="submit" name="add_link" class="btn-admin-gold" style="white-space:nowrap">
        <i class="ph ph-plus"></i> Add Link
      </button>
    </div>
    <p style="font-size:11px;color:var(--text-muted);margin-top:8px">The new link will be added at the end. Use the order numbers above to reposition it.</p>
  </form>
</div>

<!-- Live preview hint -->
<div style="margin-top:20px;padding:14px;background:rgba(248,196,23,0.07);border:1px solid rgba(248,196,23,0.2);border-radius:8px;font-size:12px;color:var(--text-muted)">
  <i class="ph ph-info" style="color:var(--accent)"></i>
  Changes take effect immediately on the live site. <a href="/index.php" target="_blank" style="color:var(--accent);text-decoration:none">View Site →</a>
</div>

<?php require_once __DIR__ . '/../admin/includes/footer.php'; ?>
