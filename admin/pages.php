<?php
/**
 * DUHN FRAGRANCES — Admin: Page Content Hub
 * Shows launcher cards for each static page.
 * Actual editing happens in policy-editor.php (new tab).
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/config/database.php';

$db   = Database::getInstance();
$rows = $db->query("SELECT `key`, `value` FROM `settings`")->fetchAll();
$s    = [];
foreach ($rows as $row) { $s[$row['key']] = $row['value']; }

$pages = [
    'about'    => [
        'icon'  => '📖',
        'label' => 'About DUHN',
        'desc'  => 'Your brand story, mission, values, and identity.',
        'url'   => '/about.php',
    ],
    'shipping' => [
        'icon'  => '🚚',
        'label' => 'Shipping Policy',
        'desc'  => 'Delivery timelines, coverage areas, and logistics info.',
        'url'   => '/shipping-policy.php',
    ],
    'exchange' => [
        'icon'  => '🔄',
        'label' => 'Exchange Policy',
        'desc'  => 'Returns, defective items, and claims process.',
        'url'   => '/exchange-policy.php',
    ],
    'refill'   => [
        'icon'  => '♻️',
        'label' => 'Refill Policy',
        'desc'  => 'Bottle refill service, conditions, and sustainability story.',
        'url'   => '/refill-policy.php',
    ],
];

$adminTitle = 'Page Content';
require_once __DIR__ . '/includes/header.php';
?>
<style>
.ph-hero{margin-bottom:28px}
.ph-hero h1{font-size:22px;font-weight:700;color:#fff;margin:0 0 6px}
.ph-hero p{font-size:13px;color:#888;margin:0}

.ph-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px}

.ph-card{background:var(--admin-card);border:1px solid var(--admin-border);border-radius:12px;padding:24px;display:flex;flex-direction:column;gap:14px;transition:border-color .2s,box-shadow .2s}
.ph-card:hover{border-color:rgba(248,196,23,.35);box-shadow:0 4px 20px rgba(0,0,0,.3)}

.ph-card-top{display:flex;align-items:flex-start;gap:14px}
.ph-icon{font-size:28px;line-height:1;flex-shrink:0;margin-top:2px}
.ph-card-info{flex:1;min-width:0}
.ph-card-info h3{font-size:15px;font-weight:700;color:#fff;margin:0 0 5px}
.ph-card-info p{font-size:12px;color:#777;margin:0;line-height:1.5}

.ph-preview{background:#141414;border:1px solid var(--admin-border);border-radius:8px;padding:12px 14px;font-size:12px;color:#666;line-height:1.6;max-height:64px;overflow:hidden;position:relative}
.ph-preview::after{content:'';position:absolute;bottom:0;left:0;right:0;height:24px;background:linear-gradient(transparent,#141414)}
.ph-preview.empty{font-style:italic;color:#444}

.ph-card-foot{display:flex;align-items:center;justify-content:space-between;gap:10px}
.ph-badge{font-size:10px;font-weight:700;letter-spacing:.08em;padding:3px 8px;border-radius:20px;text-transform:uppercase}
.ph-badge.has-content{background:rgba(111,207,151,.12);color:#6fcf97}
.ph-badge.no-content{background:rgba(255,255,255,.06);color:#555}

.ph-edit-btn{display:inline-flex;align-items:center;gap:6px;background:var(--accent);color:#000;font-family:'Barlow',sans-serif;font-size:12px;font-weight:700;letter-spacing:.05em;padding:8px 16px;border-radius:7px;text-decoration:none;transition:opacity .2s;white-space:nowrap;flex-shrink:0}
.ph-edit-btn:hover{opacity:.85}
.ph-edit-btn svg{width:13px;height:13px;flex-shrink:0}

.ph-view-btn{font-size:11px;color:#666;text-decoration:none;transition:color .2s}
.ph-view-btn:hover{color:var(--accent)}
</style>

<div class="admin-content">
  <div class="ph-hero">
    <h1>📄 Page Content</h1>
    <p>Manage the content for each public-facing static page. Click <strong>Open Page Editor</strong> to edit in a full-screen editor.</p>
  </div>

  <div class="ph-grid">
    <?php foreach ($pages as $key => $pg): ?>
    <?php
      $title   = $s["page_{$key}_title"]   ?? $pg['label'];
      $content = $s["page_{$key}_content"] ?? '';
      // Strip tags for preview snippet
      $snippet = trim(strip_tags($content));
      $hasContent = strlen($snippet) > 10;
      $preview = $hasContent ? mb_substr($snippet, 0, 140) : 'No content yet — click to start writing.';
    ?>
    <div class="ph-card">
      <div class="ph-card-top">
        <span class="ph-icon"><?= $pg['icon'] ?></span>
        <div class="ph-card-info">
          <h3><?= htmlspecialchars($title) ?></h3>
          <p><?= htmlspecialchars($pg['desc']) ?></p>
        </div>
      </div>

      <div class="ph-preview <?= $hasContent ? '' : 'empty' ?>">
        <?= htmlspecialchars($preview) ?>
      </div>

      <div class="ph-card-foot">
        <span class="ph-badge <?= $hasContent ? 'has-content' : 'no-content' ?>">
          <?= $hasContent ? '✓ Has Content' : 'Empty' ?>
        </span>
        <div style="display:flex;align-items:center;gap:12px">
          <a href="<?= $pg['url'] ?>" target="_blank" class="ph-view-btn">View ↗</a>
          <a href="/admin/policy-editor.php?tab=<?= $key ?>" target="_blank" class="ph-edit-btn">
            <svg viewBox="0 0 256 256" fill="currentColor"><path d="M227.31,73.37,182.63,28.69a16,16,0,0,0-22.63,0L36.69,152A15.86,15.86,0,0,0,32,163.31V208a16,16,0,0,0,16,16H92.69A15.86,15.86,0,0,0,104,219.31L227.31,96a16,16,0,0,0,0-22.63ZM92.69,208H48V163.31l88-88L180.69,120ZM192,108.69,147.31,64l24-24L216,84.69Z"/></svg>
            Open Page Editor
          </a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
