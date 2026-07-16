<?php
/**
 * DUHN FRAGRANCES — Product Fields Migration
 * Adds: compare_at_price, short_description to products table
 * Adds: delivery_info, return_info, shipping_policy to settings table
 * DELETE THIS FILE AFTER RUNNING.
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/config/database.php';

$db  = Database::getInstance();
$log = [];

function tryAlter($db, $sql, &$log, $label) {
    try { $db->exec($sql); $log[] = ['ok', $label]; }
    catch (Throwable $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) $log[] = ['skip', $label . ' (already exists)'];
        else $log[] = ['err', $label . ': ' . $e->getMessage()];
    }
}

function trySetting($db, $key, $value, &$log) {
    try {
        $db->prepare("INSERT INTO settings (`key`,`value`) VALUES (:k,:v) ON DUPLICATE KEY UPDATE `key`=`key`")
           ->execute([':k' => $key, ':v' => $value]);
        $log[] = ['ok', "Setting '$key' seeded"];
    } catch (Throwable $e) { $log[] = ['err', "Setting '$key': " . $e->getMessage()]; }
}

tryAlter($db, "ALTER TABLE products ADD COLUMN compare_at_price DECIMAL(10,2) DEFAULT NULL AFTER price", $log, 'products.compare_at_price');
tryAlter($db, "ALTER TABLE products ADD COLUMN short_description TEXT DEFAULT NULL AFTER description", $log, 'products.short_description');

trySetting($db, 'delivery_info', 'Estimate delivery times: <strong>2–5 business days</strong> across Egypt. <strong>Free delivery</strong> on orders over 499 EGP.', $log);
trySetting($db, 'return_info',   'Return within <strong>7 days</strong> of purchase. Items must be unused and in original packaging.', $log);
trySetting($db, 'shipping_policy', "Free shipping on all orders over 499 EGP across Egypt.\nOrders are processed within 1–2 business days.\nDelivery takes 2–5 business days depending on your location.\nFor returns: items must be unused, in original packaging, and returned within 7 days of receipt.\nTo initiate a return, contact us via WhatsApp or email before shipping the item back.", $log);

// Output
header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html><html><head><meta charset="UTF-8"><title>Migration</title>
<style>
body{font-family:sans-serif;background:#111;color:#eee;padding:40px;max-width:600px;margin:auto}
h2{color:#CBBA9C}
.ok{color:#4CAF50}.skip{color:#aaa}.err{color:#f44}
li{padding:5px 0;font-size:14px}
.btn{display:inline-block;margin-top:24px;background:#CBBA9C;color:#000;padding:10px 24px;border-radius:6px;font-weight:700;text-decoration:none}
.warn{background:rgba(220,53,69,0.15);border:1px solid #f44;border-radius:6px;padding:12px;margin-top:20px;font-size:13px;color:#f99}
</style></head><body>
<h2>✅ Migration Complete</h2>
<ul>
<?php foreach ($log as [$type, $msg]): ?>
<li class="<?= $type ?>">
  <?= $type === 'ok' ? '✓' : ($type === 'skip' ? '↷' : '✗') ?> <?= htmlspecialchars($msg) ?>
</li>
<?php endforeach; ?>
</ul>
<div class="warn">⚠️ Delete <code>admin/migrate-product-fields.php</code> immediately after running!</div>
<a class="btn" href="/admin/products/add.php">Go to Add Product</a>
</body></html>
