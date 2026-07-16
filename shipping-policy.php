<?php
require_once __DIR__ . '/api/config/config.php';
require_once __DIR__ . '/api/config/database.php';
$db = Database::getInstance();
$rows = $db->query("SELECT `key`,`value` FROM `settings` WHERE `key` IN ('page_shipping_title','page_shipping_subtitle','page_shipping_content')")->fetchAll();
$ps = []; foreach ($rows as $r) { $ps[$r['key']] = $r['value']; }
$title    = htmlspecialchars($ps['page_shipping_title']    ?? 'Shipping Policy');
$subtitle = htmlspecialchars($ps['page_shipping_subtitle'] ?? '');
$content  = $ps['page_shipping_content'] ?? '
<div style="background:var(--surface-dark);border-radius:var(--radius);padding:24px">
  <h3 style="color:var(--accent);font-size:16px;font-weight:700;margin-bottom:10px">📦 Delivery Timeline</h3>
  <p>Orders are processed promptly and delivered within <strong style="color:var(--text-light)">2 to 5 business days</strong> in most cases.</p>
</div>
<div style="background:var(--surface-dark);border-radius:var(--radius);padding:24px">
  <h3 style="color:var(--accent);font-size:16px;font-weight:700;margin-bottom:10px">📅 Excluded Days</h3>
  <p>Weekends and official holidays are excluded from the delivery timeline.</p>
</div>
<div style="background:var(--surface-dark);border-radius:var(--radius);padding:24px">
  <h3 style="color:var(--accent);font-size:16px;font-weight:700;margin-bottom:10px">🔍 Order Tracking</h3>
  <p>Once shipped, you will receive a tracking link via WhatsApp or email to follow your order\'s journey.</p>
</div>
<div style="background:var(--surface-dark);border-radius:var(--radius);padding:24px">
  <h3 style="color:var(--accent);font-size:16px;font-weight:700;margin-bottom:10px">💬 Customer Support</h3>
  <p>For any inquiries, our customer care team is here to assist you via <a href="https://wa.me/201157879622" style="color:var(--accent)" target="_blank">WhatsApp</a> or the <a href="/contact.php" style="color:var(--accent)">contact form</a>.</p>
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
