<?php
/**
 * DUHN FRAGRANCES — Products Mock Data Seeder
 * Adds compare_at_price + short_description to all existing products.
 * DELETE THIS FILE AFTER RUNNING.
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/config/database.php';

$db = Database::getInstance();

// Short descriptions pool — fragrance-themed, luxury copywriting
$shortDescs = [
    "A bold, captivating scent that commands attention from the first spritz. Crafted for those who leave a lasting impression wherever they go.",
    "Warm, woody, and undeniably seductive. This signature fragrance wraps you in depth and elegance that lingers throughout the day.",
    "A fresh floral burst with a musky heart — the perfect everyday scent that effortlessly transitions from morning meetings to evening outings.",
    "Inspired by the world's most iconic luxury perfumes, reimagined for Egyptian skin and climate. All-day longevity guaranteed.",
    "An oriental masterpiece that opens with sparkling citrus, evolves into rich spice, and dries down to a smooth, powdery base.",
    "Clean, sophisticated, and timeless. A versatile fragrance that works equally well in the boardroom and on the weekend.",
    "Sweet yet complex, this crowd-pleasing scent has earned its place as a must-have in every fragrance wardrobe.",
    "Dark, mysterious, and intensely addictive. For the bold personality who isn't afraid to make a statement.",
    "A radiant, sun-kissed fragrance that evokes Mediterranean beaches and warm summer evenings. Pure joy in a bottle.",
    "Effortlessly chic and endlessly wearable, this scent blends fresh green notes with a warm amber base for timeless appeal.",
    "A powerhouse of longevity and projection — built to last all day on your skin without a single reapplication.",
    "Delicately balanced between fresh and warm, this unisex fragrance is a modern classic for any occasion.",
];

// Fetch all products
$products = $db->query("SELECT id, name, price FROM products ORDER BY id")->fetchAll();

$log = [];
$i   = 0;

foreach ($products as $p) {
    $id    = (int)$p['id'];
    $price = (float)$p['price'];

    // Compare price: 15%–35% higher than current price, rounded to nearest 50
    $markupPct    = [0.15, 0.18, 0.20, 0.22, 0.25, 0.28, 0.30, 0.35][$i % 8];
    $compareRaw   = $price / (1 - $markupPct);
    $comparePrice = round($compareRaw / 50) * 50; // round to nearest 50 EGP

    // Cycle through short descriptions
    $shortDesc = $shortDescs[$i % count($shortDescs)];

    try {
        $db->prepare("
            UPDATE products
            SET compare_at_price   = :cp,
                short_description  = :sd
            WHERE id = :id
        ")->execute([
            ':cp' => $comparePrice,
            ':sd' => $shortDesc,
            ':id' => $id,
        ]);
        $save = round((($comparePrice - $price) / $comparePrice) * 100);
        $log[] = ['ok', "#{$id} {$p['name']} — {$price} EGP (was {$comparePrice} EGP, SAVE {$save}%)"];
    } catch (Throwable $e) {
        $log[] = ['err', "#{$id} {$p['name']}: " . $e->getMessage()];
    }

    $i++;
}
?><!DOCTYPE html><html><head><meta charset="UTF-8"><title>Mock Data Seeded</title>
<style>
  body{font-family:sans-serif;background:#111;color:#eee;padding:40px;max-width:700px;margin:auto}
  h2{color:#CBBA9C;margin-bottom:4px}
  p{color:#888;font-size:13px;margin-bottom:20px}
  ul{list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:6px}
  li{padding:8px 14px;border-radius:6px;font-size:13px}
  .ok{background:rgba(40,167,69,0.12);border:1px solid rgba(40,167,69,0.25);color:#6fcf97}
  .err{background:rgba(220,53,69,0.12);border:1px solid rgba(220,53,69,0.3);color:#f99}
  .warn{margin-top:24px;background:rgba(220,53,69,0.12);border:1px solid #f44;border-radius:6px;padding:14px;font-size:13px;color:#f99}
  .actions{display:flex;gap:12px;margin-top:24px}
  a{display:inline-block;padding:11px 24px;border-radius:6px;font-weight:700;text-decoration:none;font-size:13px}
  .btn-gold{background:#CBBA9C;color:#000}
  .btn-outline{border:1px solid #555;color:#ccc}
</style></head><body>
<h2>✅ Mock Data Seeded — <?= count($products) ?> Products</h2>
<p>All products now have a compare-at price and short description for the product page.</p>
<ul>
<?php foreach ($log as [$type, $msg]): ?>
  <li class="<?= $type ?>"><?= $type === 'ok' ? '✓' : '✗' ?> <?= htmlspecialchars($msg) ?></li>
<?php endforeach; ?>
</ul>
<div class="warn">⚠️ Delete <code>admin/seed-products-mock.php</code> immediately after running!</div>
<div class="actions">
  <a href="/admin/products.php" class="btn-gold">→ View Products</a>
  <a href="/collections.php" class="btn-outline" target="_blank">View Shop</a>
</div>
</body></html>
