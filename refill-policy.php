<?php
require_once __DIR__ . '/api/config/config.php';
require_once __DIR__ . '/api/config/database.php';
$db = Database::getInstance();
$rows = $db->query("SELECT `key`,`value` FROM `settings` WHERE `key` IN ('page_refill_title','page_refill_subtitle','page_refill_content')")->fetchAll();
$ps = []; foreach ($rows as $r) { $ps[$r['key']] = $r['value']; }
$title    = htmlspecialchars($ps['page_refill_title']    ?? 'Refill Policy');
$subtitle = htmlspecialchars($ps['page_refill_subtitle'] ?? '');
$content  = $ps['page_refill_content'] ?? '
<div style="background:rgba(203,186,156,0.1);border:1px solid rgba(203,186,156,0.3);border-radius:var(--radius);padding:20px">
  <p>♻️ <strong style="color:var(--text-light)">DUHN FRAGRANCES offers a refill service</strong> for customers who wish to refill their existing DUHN bottles.</p>
</div>
<div style="background:var(--surface-dark);border-radius:var(--radius);padding:24px">
  <h3 style="color:var(--accent);font-size:16px;font-weight:700;margin-bottom:14px">✅ How Refills Work</h3>
  <ol style="display:flex;flex-direction:column;gap:10px;padding-left:20px">
    <li>Contact us via Instagram @duhnfragrances or the contact form to request a refill.</li>
    <li>Specify the fragrance and confirm bottle condition.</li>
    <li>Our team will confirm availability and pricing.</li>
    <li>Your refilled bottle will be delivered with the same care and packaging.</li>
  </ol>
</div>
<div style="background:var(--surface-dark);border-radius:var(--radius);padding:24px">
  <h3 style="color:var(--accent);font-size:16px;font-weight:700;margin-bottom:14px">📋 Refill Conditions</h3>
  <ul style="list-style:none;display:flex;flex-direction:column;gap:8px">
    <li>✓ Only available for DUHN FRAGRANCES bottles</li>
    <li>✓ Bottle must be intact, clean, and undamaged</li>
    <li>✓ Refill pricing is lower than a full new purchase</li>
    <li>✗ We do not refill bottles from other brands</li>
  </ul>
</div>';
$pageTitle = $title . ' — DUHN FRAGRANCES';
require_once __DIR__ . '/public/layout/header.php';
?>
<div class="container" style="padding:60px 20px;max-width:720px">
  <h1 class="section-title" style="margin-bottom:<?= $subtitle ? '8px' : '32px' ?>"><?= $title ?></h1>
  <?php if ($subtitle): ?><p style="color:var(--text-muted);margin-bottom:32px;font-size:15px"><?= $subtitle ?></p><?php endif; ?>
  <div class="policy-body" style="display:flex;flex-direction:column;gap:20px;color:rgba(255,255,255,0.8);font-size:15px;line-height:1.8">
    <?= $content ?>
  </div>
</div>
<?php require_once __DIR__ . '/public/layout/footer.php'; ?>
