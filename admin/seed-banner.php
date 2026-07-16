<?php
/**
 * DUHN FRAGRANCES — Brand Banner Seeder
 * DELETE THIS FILE AFTER RUNNING.
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/config/database.php';

$db = Database::getInstance();

function s($db, $key, $value) {
    $db->prepare("INSERT INTO `settings` (`key`, `value`) VALUES (:k, :v)
                  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
       ->execute([':k' => $key, ':v' => $value]);
}

s($db, 'brand_banner_color',    '#0D1B1E');
s($db, 'brand_banner_pattern',  'diagonal');
s($db, 'brand_banner_eyebrow',  'THE DUHN PROMISE');
s($db, 'brand_banner_title',    'Wear a scent they<br>will never <em>forget.</em>');
s($db, 'brand_banner_sub',      'Every drop of DUHN is a statement — bold, refined, and made to outlast the moment. Premium Egyptian fragrance, priced for everyone.');
s($db, 'brand_banner_btn_text', 'DISCOVER THE COLLECTION');
s($db, 'brand_banner_btn_url',  '/collections.php');

echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
<title>Banner Seeded</title>
<style>
  body{font-family:sans-serif;background:#0D1B1E;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
  .box{background:#1A2A2E;border:1px solid #2A3A3E;border-radius:10px;padding:40px;max-width:500px;text-align:center}
  h2{color:#CBBA9C;margin-bottom:12px}
  .eyebrow{font-size:11px;letter-spacing:0.15em;color:#CBBA9C;text-transform:uppercase;margin-bottom:6px}
  .title{font-size:26px;line-height:1.3;margin:0 0 10px}
  .sub{font-size:13px;color:#aaa;margin:0 0 20px}
  .warn{margin-top:20px;background:rgba(220,53,69,0.12);border:1px solid rgba(220,53,69,0.3);color:#ff6b6b;border-radius:6px;padding:12px;font-size:13px}
  a{display:inline-block;margin-top:20px;background:#CBBA9C;color:#0D1B1E;padding:12px 28px;border-radius:6px;font-weight:700;text-decoration:none;letter-spacing:0.06em}
</style></head><body>
<div class="box">
  <h2>✅ Brand Banner Seeded!</h2>
  <div class="eyebrow">THE DUHN PROMISE</div>
  <div class="title">Wear a scent they will never <em>forget.</em></div>
  <div class="sub">Every drop of DUHN is a statement — bold, refined, and made to outlast the moment.</div>
  <div class="warn">⚠️ Delete <code>admin/seed-banner.php</code> after running!</div>
  <a href="/index.php" target="_blank">→ View Homepage</a>
</div></body></html>';
