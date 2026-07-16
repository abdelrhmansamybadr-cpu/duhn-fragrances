<?php
/**
 * DUHN FRAGRANCES — Content Blocks Migration
 * Adds content_blocks JSON column to products table.
 * DELETE THIS FILE AFTER RUNNING.
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/config/database.php';

$db = Database::getInstance();
try {
    $db->exec("ALTER TABLE products ADD COLUMN content_blocks LONGTEXT DEFAULT NULL AFTER short_description");
    $msg = ['ok', 'products.content_blocks column added successfully.'];
} catch (Throwable $e) {
    $msg = strpos($e->getMessage(), 'Duplicate column') !== false
        ? ['skip', 'Column already exists — nothing to do.']
        : ['err',  $e->getMessage()];
}
?><!DOCTYPE html><html><head><meta charset="UTF-8"><title>Migration</title>
<style>body{font-family:sans-serif;background:#111;color:#eee;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}.box{background:#1A1A1A;border:1px solid #2a2a2a;border-radius:10px;padding:36px;max-width:480px;text-align:center}h2{color:#CBBA9C;margin-bottom:10px}.ok{color:#6fcf97}.skip{color:#aaa}.err{color:#f99}p{font-size:13px;color:#888;margin:10px 0 20px}.warn{background:rgba(220,53,69,.12);border:1px solid #f44;border-radius:6px;padding:12px;font-size:13px;color:#f99;margin-top:16px}a{display:inline-block;margin-top:20px;background:#CBBA9C;color:#000;padding:11px 26px;border-radius:6px;font-weight:700;text-decoration:none}</style>
</head><body><div class="box">
<h2>Content Blocks Migration</h2>
<p class="<?= $msg[0] ?>"><?= $msg[0] === 'ok' ? '✅' : ($msg[0] === 'skip' ? '↷' : '✗') ?> <?= htmlspecialchars($msg[1]) ?></p>
<div class="warn">⚠️ Delete <code>admin/migrate-content-blocks.php</code> after running!</div>
<a href="/admin/products.php">→ Go to Products</a>
</div></body></html>
