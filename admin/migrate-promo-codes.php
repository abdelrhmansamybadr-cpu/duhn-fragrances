<?php
/**
 * DUHN FRAGRANCES — Promo Codes Migration
 * Creates the promo_codes table.
 * DELETE THIS FILE AFTER RUNNING.
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/config/database.php';

$db = Database::getInstance();
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS promo_codes (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            code          VARCHAR(50)  NOT NULL UNIQUE,
            description   VARCHAR(255) DEFAULT NULL,
            type          ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
            value         DECIMAL(10,2) NOT NULL,
            min_order     DECIMAL(10,2) DEFAULT 0,
            max_uses      INT          DEFAULT NULL,
            used_count    INT          DEFAULT 0,
            is_active     TINYINT(1)   DEFAULT 1,
            expires_at    DATE         DEFAULT NULL,
            created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $msg = ['ok', 'promo_codes table created successfully.'];
} catch (Throwable $e) {
    $msg = ['err', $e->getMessage()];
}
?><!DOCTYPE html><html><head><meta charset="UTF-8"><title>Migration</title>
<style>body{font-family:sans-serif;background:#111;color:#eee;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}.box{background:#1A1A1A;border:1px solid #2a2a2a;border-radius:10px;padding:36px;max-width:500px;text-align:center}h2{color:#CBBA9C;margin-bottom:10px}p{font-size:13px;color:#888;margin:10px 0 20px}.ok{color:#6fcf97}.err{color:#f99}.warn{background:rgba(220,53,69,.12);border:1px solid #f44;border-radius:6px;padding:12px;font-size:13px;color:#f99;margin-top:16px}a{display:inline-block;margin-top:20px;background:#CBBA9C;color:#000;padding:11px 26px;border-radius:6px;font-weight:700;text-decoration:none}</style>
</head><body><div class="box">
<h2>Promo Codes Migration</h2>
<p class="<?= $msg[0] ?>"><?= $msg[0]==='ok'?'✅':'✗' ?> <?= htmlspecialchars($msg[1]) ?></p>
<div class="warn">⚠️ Delete <code>admin/migrate-promo-codes.php</code> after running!</div>
<a href="/admin/promo-codes.php">→ Manage Promo Codes</a>
</div></body></html>
