<?php
require_once __DIR__ . '/api/config/config.php';
require_once __DIR__ . '/api/config/database.php';
$db = Database::getInstance();
$rows = $db->query("SELECT `key`,`value` FROM `settings` WHERE `key` IN ('page_about_title','page_about_subtitle','page_about_content')")->fetchAll();
$ps = []; foreach ($rows as $r) { $ps[$r['key']] = $r['value']; }
$title    = htmlspecialchars($ps['page_about_title']    ?? 'Indulge Your Senses');
$subtitle = htmlspecialchars($ps['page_about_subtitle'] ?? 'You only get one chance to make a first impression — make it unforgettable.');
$content  = $ps['page_about_content'] ?? '
<div style="margin-bottom:40px">
  <h2 style="font-size:26px;font-weight:700;margin-bottom:20px;color:var(--accent)">Who We Are</h2>
  <p style="font-size:16px;line-height:1.9;color:#d0d0d0;margin-bottom:16px">
    DUHN FRAGRANCES is a premium Egyptian fragrance brand born from a passion for luxury scent and accessible elegance.
    We believe that extraordinary fragrance should not require an extraordinary price — which is why every bottle
    in our collection is priced at just <strong style="color:var(--accent)">899 EGP</strong>.
  </p>
  <p style="font-size:16px;line-height:1.9;color:#d0d0d0">
    Our fragrances are inspired by the world\'s most iconic designer perfumes — reimagined and crafted with
    premium ingredients for the Egyptian market. Each 50ml bottle is a statement: bold, refined, and made to last.
  </p>
</div>
<div style="background:rgba(248,196,23,.06);border:1px solid rgba(248,196,23,.2);border-radius:16px;padding:36px;margin-bottom:40px">
  <h2 style="font-size:22px;font-weight:700;margin-bottom:16px">Our Promise</h2>
  <p>✦ <strong>Premium Ingredients</strong> — Every fragrance crafted with high-quality fragrance oils for longevity and projection.</p>
  <p>✦ <strong>Fixed Price</strong> — All fragrances at a single price — 899 EGP. No surprises.</p>
  <p>✦ <strong>Fast Delivery</strong> — Orders processed and delivered across Egypt within 2–5 business days.</p>
  <p>✦ <strong>Exclusive Offer</strong> — Buy 2 Get 2 Free — because luxury is better shared.</p>
</div>';
$pageTitle = 'About DUHN FRAGRANCES';
require_once __DIR__ . '/public/layout/header.php';
?>

<!-- Hero -->
<section style="background:var(--primary);padding:80px 24px;text-align:center;border-bottom:1px solid rgba(248,196,23,.12)">
  <p style="color:var(--accent);font-size:13px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;margin-bottom:12px">Our Story</p>
  <h1 style="font-size:clamp(32px,5vw,56px);font-weight:700;line-height:1.1;margin-bottom:16px"><?= $title ?></h1>
  <?php if ($subtitle): ?>
  <p style="color:var(--text-muted);font-size:16px;max-width:520px;margin:0 auto"><?= $subtitle ?></p>
  <?php endif; ?>
</section>

<div style="max-width:800px;margin:0 auto;padding:64px 24px">
  <div class="policy-body" style="display:flex;flex-direction:column;gap:20px;color:rgba(255,255,255,0.8);font-size:15px;line-height:1.8">
    <?= $content ?>
  </div>

  <!-- Social -->
  <div style="text-align:center;margin-top:56px">
    <h2 style="font-size:20px;font-weight:700;margin-bottom:8px">Follow Our Journey</h2>
    <p style="color:var(--text-muted);margin-bottom:24px;font-size:14px">Stay connected for new drops, campaigns, and exclusive offers.</p>
    <div style="display:flex;justify-content:center;gap:16px;flex-wrap:wrap">
      <a href="https://www.facebook.com/duhnfragrances" target="_blank"
         style="display:flex;align-items:center;gap:8px;padding:12px 24px;background:#1877F2;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px">
        <i class="ph ph-facebook-logo"></i> Facebook
      </a>
      <a href="https://www.instagram.com/duhnfragrances" target="_blank"
         style="display:flex;align-items:center;gap:8px;padding:12px 24px;background:linear-gradient(135deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888);color:#fff;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px">
        <i class="ph ph-instagram-logo"></i> Instagram
      </a>
      <a href="https://www.tiktok.com/@duhnfragrances" target="_blank"
         style="display:flex;align-items:center;gap:8px;padding:12px 24px;background:#010101;color:#fff;border:1px solid #333;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px">
        <i class="ph ph-tiktok-logo"></i> TikTok
      </a>
    </div>
  </div>
</div>

<!-- CTA Banner -->
<section style="background:var(--accent);padding:48px 24px;text-align:center">
  <h2 style="font-size:26px;font-weight:700;color:#000;margin-bottom:8px">Ready to Find Your Signature Scent?</h2>
  <p style="color:rgba(0,0,0,.6);margin-bottom:24px">Explore our premium fragrances — all 50ML, all 899 EGP.</p>
  <a href="/collections.php" class="btn-gold" style="background:#000;color:#F8C417;border-color:#000">Shop All Collections</a>
</section>

<?php require_once __DIR__ . '/public/layout/footer.php'; ?>
<script src="/public/js/app.js"></script>
