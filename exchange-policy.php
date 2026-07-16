<?php
require_once __DIR__ . '/api/config/config.php';
require_once __DIR__ . '/api/config/database.php';
$db = Database::getInstance();
$rows = $db->query("SELECT `key`,`value` FROM `settings` WHERE `key` IN ('page_exchange_title','page_exchange_subtitle','page_exchange_content')")->fetchAll();
$ps = []; foreach ($rows as $r) { $ps[$r['key']] = $r['value']; }
$title    = htmlspecialchars($ps['page_exchange_title']    ?? 'Exchange Policy');
$subtitle = htmlspecialchars($ps['page_exchange_subtitle'] ?? '');
$content  = $ps['page_exchange_content'] ?? '
<div style="background:rgba(220,53,69,0.1);border:1px solid rgba(220,53,69,0.3);border-radius:var(--radius);padding:20px">
  <p>🚫 <strong style="color:var(--text-light)">DUHN FRAGRANCES does not accept exchanges or returns</strong> once a purchase is completed. Each creation is a unique sensorial journey.</p>
</div>
<div style="background:var(--surface-dark);border-radius:var(--radius);padding:24px">
  <h3 style="color:var(--accent);font-size:16px;font-weight:700;margin-bottom:10px">💡 Our Recommendation</h3>
  <p>We encourage customers to purchase Discovery Sets before committing to full-size bottles to avoid uncertain blind purchases.</p>
</div>
<div style="background:var(--surface-dark);border-radius:var(--radius);padding:24px">
  <h3 style="color:var(--accent);font-size:16px;font-weight:700;margin-bottom:14px">✅ Defective Product Replacement</h3>
  <p style="margin-bottom:12px">We will replace defective items at no additional delivery cost. Qualifying defects include:</p>
  <ul style="list-style:none;display:flex;flex-direction:column;gap:8px">
    <li>✓ A broken or malfunctioning atomizer</li>
    <li>✓ Receipt of a different fragrance than ordered</li>
    <li>✓ Evident impurities or irregularities in the liquid</li>
    <li>✓ A broken or damaged bottle</li>
  </ul>
</div>
<div style="background:var(--surface-dark);border-radius:var(--radius);padding:24px">
  <h3 style="color:var(--accent);font-size:16px;font-weight:700;margin-bottom:10px">📸 How to Claim</h3>
  <p>Contact our <a href="https://www.instagram.com/duhnfragrances" target="_blank" style="color:var(--accent)">Instagram team (@duhnfragrances)</a> within <strong style="color:var(--text-light)">5 days</strong> of receiving your order with clear photos.</p>
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
