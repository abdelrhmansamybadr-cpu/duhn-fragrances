<?php
/**
 * DUHN FRAGRANCES — Content Blocks Mock Data Seeder
 * Seeds sample image+text blocks into all products.
 * DELETE THIS FILE AFTER RUNNING.
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/config/database.php';

$db = Database::getInstance();

// Use a free-to-use product lifestyle image (from your existing uploads or a placeholder)
// We'll use a consistent placeholder that works without upload
$blocks_templates = [
    // Template A — text left, image right
    [
        [
            'heading' => 'Outstanding Longevity',
            'text'    => "This fragrance is engineered to stay on your skin for 8–12 hours without reapplication. Our proprietary fixative blend locks the scent into your skin's natural oils, evolving beautifully throughout the day.\n\n• Long-lasting sillage that fills the room\n• Suitable for Egypt's warm, humid climate\n• Skin-safe formula tested by dermatologists",
            'image'   => '',
            'layout'  => 'right',
        ],
        [
            'heading' => 'The Art of Egyptian Perfumery',
            'text'    => "Inspired by centuries of Arab oud tradition, this fragrance blends ancient ingredients with a modern sensibility. Each bottle is hand-inspected before leaving our facility to ensure you receive nothing but perfection.\n\nEvery note was selected to harmonise with Egyptian skin chemistry — creating a scent that smells uniquely yours.",
            'image'   => '',
            'layout'  => 'left',
        ],
    ],
    // Template B — image top, then text block
    [
        [
            'heading' => 'Crafted for the Bold',
            'text'    => "Bold fragrance for bold personalities. This is not a fragrance for the timid — it commands attention, turns heads, and leaves a trace long after you've left the room.\n\nPerfect for:\n• Evening events & special occasions\n• The office professional who wants to be remembered\n• Gifting to someone extraordinary",
            'image'   => '',
            'layout'  => 'top',
        ],
    ],
    // Template C — image left
    [
        [
            'heading' => 'Premium Ingredients',
            'text'    => "We source only the finest raw materials for this fragrance — natural oud from Assam, Bulgarian rose absolute, and sustainably harvested sandalwood from Mysore.\n\nEvery ingredient passes a strict quality assurance process before blending begins. The result is a fragrance that is rich, complex, and unmistakably luxurious.",
            'image'   => '',
            'layout'  => 'left',
        ],
        [
            'heading' => 'How to Apply for Best Results',
            'text'    => "For maximum longevity, apply to pulse points — your wrists, neck, behind the ears, and inner elbows. These warm areas amplify the projection and help the scent bloom naturally.\n\nPro tip: Apply after a warm shower on slightly damp skin. Avoid rubbing your wrists together — this breaks the molecular structure of the fragrance.",
            'image'   => '',
            'layout'  => 'right',
        ],
    ],
];

$products = $db->query("SELECT id, name FROM products ORDER BY id")->fetchAll();
$log      = [];
$t        = 0;

foreach ($products as $p) {
    $template = $blocks_templates[$t % count($blocks_templates)];
    try {
        $db->prepare("UPDATE products SET content_blocks = :cb WHERE id = :id")
           ->execute([':cb' => json_encode($template), ':id' => $p['id']]);
        $log[] = ['ok', "#{$p['id']} {$p['name']} — " . count($template) . " block(s) added"];
    } catch (Throwable $e) {
        $log[] = ['err', "#{$p['id']} {$p['name']}: " . $e->getMessage()];
    }
    $t++;
}
?><!DOCTYPE html><html><head><meta charset="UTF-8"><title>Content Blocks Seeded</title>
<style>
body{font-family:sans-serif;background:#111;color:#eee;padding:40px;max-width:680px;margin:auto}
h2{color:#CBBA9C;margin-bottom:4px}p{color:#888;font-size:13px;margin-bottom:20px}
ul{list-style:none;padding:0;display:flex;flex-direction:column;gap:6px}
li{padding:8px 14px;border-radius:6px;font-size:13px}
.ok{background:rgba(40,167,69,.12);border:1px solid rgba(40,167,69,.25);color:#6fcf97}
.err{background:rgba(220,53,69,.12);border:1px solid rgba(220,53,69,.3);color:#f99}
.warn{margin-top:24px;background:rgba(220,53,69,.12);border:1px solid #f44;border-radius:6px;padding:14px;font-size:13px;color:#f99}
.actions{display:flex;gap:12px;margin-top:24px}
a{display:inline-block;padding:11px 24px;border-radius:6px;font-weight:700;text-decoration:none;font-size:13px}
.g{background:#CBBA9C;color:#000}.o{border:1px solid #555;color:#ccc}
</style></head><body>
<h2>✅ Content Blocks Seeded — <?= count($products) ?> Products</h2>
<p>Text-only blocks added (no images needed — you can add images per product via the admin edit page).</p>
<ul>
<?php foreach ($log as [$type, $msg]): ?>
<li class="<?= $type ?>"><?= $type==='ok'?'✓':'✗' ?> <?= htmlspecialchars($msg) ?></li>
<?php endforeach; ?>
</ul>
<div class="warn">⚠️ Delete <code>admin/seed-content-blocks.php</code> after running!</div>
<div class="actions">
  <a href="/collections.php" class="g" target="_blank">→ View Shop</a>
  <a href="/admin/products.php" class="o">Admin Products</a>
</div>
</body></html>
