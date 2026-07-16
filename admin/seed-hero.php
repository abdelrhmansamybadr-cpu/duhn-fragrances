<?php
/**
 * DUHN FRAGRANCES — Hero Seeder
 * Seeds beautiful default hero slide content into the settings table.
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

/* ──────────────────────────────────────────────────────────────
   SLIDE 1 — "Indulge Your Senses" — Left position
────────────────────────────────────────────────────────────── */
s($db, 'hero_count',        '2');
s($db, 'hero_1_image',      '/public/images/hero/hero-1.jpg');
s($db, 'hero_1_content_pos','mid-left');
s($db, 'hero_1_eyebrow',    'EXCLUSIVE COLLECTION &nbsp;·&nbsp; 2026');
s($db, 'hero_1_title',
    'Indulge<br>Your <em>Senses</em>'
);
s($db, 'hero_1_subtitle',
    'Premium Egyptian fragrances — crafted to last all day, priced to love forever.'
);
s($db, 'hero_1_btn_above_text', '');
s($db, 'hero_1_btn_count',  '2');
s($db, 'hero_1_btn_1_text', 'SHOP NOW');
s($db, 'hero_1_btn_1_url',  '/collections.php');
s($db, 'hero_1_btn_1_style','solid');
s($db, 'hero_1_btn_2_text', 'EXPLORE ALL');
s($db, 'hero_1_btn_2_url',  '/collections.php');
s($db, 'hero_1_btn_2_style','ghost');

/* ──────────────────────────────────────────────────────────────
   SLIDE 2 — "First Impression" — Right position
────────────────────────────────────────────────────────────── */
s($db, 'hero_2_image',      '/public/images/hero/hero-2.jpg');
s($db, 'hero_2_content_pos','mid-right');
s($db, 'hero_2_eyebrow',    'THE ART OF FRAGRANCE');
s($db, 'hero_2_title',
    'You Only Get One<br><em>First</em> Impression'
);
s($db, 'hero_2_subtitle',
    'Buy 2 · Get 2 Free &nbsp;—&nbsp; Free delivery across Egypt.'
);
s($db, 'hero_2_btn_above_text', 'OUR SIGNATURE OFFER');
s($db, 'hero_2_btn_count',  '1');
s($db, 'hero_2_btn_1_text', 'CLAIM YOUR OFFER');
s($db, 'hero_2_btn_1_url',  '/collections.php');
s($db, 'hero_2_btn_1_style','solid');

echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
<title>Hero Seeded</title>
<style>
  body{font-family:sans-serif;background:#111;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
  .box{background:#1A1A1A;border:1px solid #2A2A2A;border-radius:10px;padding:40px;max-width:480px;text-align:center}
  h2{color:#F8C417;margin-bottom:12px}
  p{color:#aaa;font-size:14px;margin:6px 0}
  .warn{margin-top:20px;background:rgba(220,53,69,0.12);border:1px solid rgba(220,53,69,0.3);color:#ff6b6b;border-radius:6px;padding:12px;font-size:13px}
  a{display:inline-block;margin-top:20px;background:#F8C417;color:#000;padding:12px 28px;border-radius:6px;font-weight:700;text-decoration:none}
</style></head><body>
<div class="box">
  <h2>✅ Hero Content Seeded!</h2>
  <p>Slide 1 — <strong>"Indulge Your Senses"</strong> · Left position</p>
  <p>Slide 2 — <strong>"First Impression"</strong> · Right position</p>
  <p>2 slides · Rich text · Dual/single buttons · Gold italic accents</p>
  <div class="warn">⚠️ Delete <code>admin/seed-hero.php</code> now!</div>
  <a href="/index.php" target="_blank">→ View Homepage</a>
  &nbsp;
  <a href="/admin/settings.php" style="background:transparent;border:1px solid #555;color:#ccc">Edit in Admin</a>
</div></body></html>';
