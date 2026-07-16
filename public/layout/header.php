<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <?php
  // ── SEO & Share meta ─────────────────────────────────────────────
  $_metaTitle = $pageTitle ?? 'DUHN FRAGRANCES — Indulge Your Senses';
  $_metaDesc  = $pageDesc  ?? 'Premium Egyptian fragrances — 50ML · 899 EGP. Luxury scents for every mood, every occasion. Shop DUHN FRAGRANCES, Egypt\'s finest perfume brand.';
  $_metaUrl   = 'https://duhnfragrances.com' . ($_SERVER['REQUEST_URI'] ?? '/');
  $_metaImg   = 'https://duhnfragrances.com/public/images/og-image.png';
  ?>

  <title><?= htmlspecialchars($_metaTitle) ?></title>
  <meta name="description"        content="<?= htmlspecialchars($_metaDesc) ?>">
  <meta name="keywords"           content="DUHN, DUHN fragrances, perfume Egypt, luxury perfume, Egyptian fragrance, عطور دن, عطر مصري, أفضل عطر مصري, 899 EGP perfume">
  <meta name="author"             content="DUHN FRAGRANCES">
  <meta name="robots"             content="index, follow">
  <link rel="canonical"           href="<?= htmlspecialchars($_metaUrl) ?>">

  <!-- Open Graph — Facebook, WhatsApp, LinkedIn, Telegram -->
  <meta property="og:type"        content="website">
  <meta property="og:site_name"   content="DUHN FRAGRANCES">
  <meta property="og:url"         content="<?= htmlspecialchars($_metaUrl) ?>">
  <meta property="og:title"       content="<?= htmlspecialchars($_metaTitle) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($_metaDesc) ?>">
  <meta property="og:image"       content="<?= htmlspecialchars($_metaImg) ?>">
  <meta property="og:image:width" content="1024">
  <meta property="og:image:height"content="1024">
  <meta property="og:image:alt"   content="DUHN FRAGRANCES — Premium Egyptian Perfumes">
  <meta property="og:locale"      content="en_US">

  <!-- Twitter / X Card -->
  <meta name="twitter:card"        content="summary_large_image">
  <meta name="twitter:site"        content="@duhnfragrances">
  <meta name="twitter:title"       content="<?= htmlspecialchars($_metaTitle) ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars($_metaDesc) ?>">
  <meta name="twitter:image"       content="<?= htmlspecialchars($_metaImg) ?>">
  <meta name="twitter:image:alt"   content="DUHN FRAGRANCES — Premium Egyptian Perfumes">

  <!-- Favicon -->
  <link rel="icon" href="/favicon.ico?v=5" sizes="any">
  <link rel="icon" type="image/png" href="/favicon.png?v=5">
  <link rel="apple-touch-icon" href="/favicon.png?v=5">

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- Icons (Phosphor) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/bold/style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css">

  <!-- Main CSS -->
  <link rel="stylesheet" href="/public/css/style.css?v=<?= filemtime(__DIR__.'/../css/style.css') ?>">

  <?= $extraHead ?? '' ?>
</head>
<body class="<?= isset($pageHome) && $pageHome ? 'page-home' : '' ?>">

<!-- Announcement Bar -->
<?php
// Load announcement settings from DB
$_annEnabled  = '1';
$_annText     = 'OUR FIRST ANNIVERSARY ✦ BUY 2 GET 2 FREE ✦ FREE DELIVERY';
$_annSpeed    = 32; // seconds
try {
    $_db = Database::getInstance();
    $_annRow = $_db->query("SELECT `key`, `value` FROM `settings` WHERE `key` IN ('announcement_enabled','announcement_text','ann_bar_speed')")->fetchAll();
    foreach ($_annRow as $_r) {
        if ($_r['key'] === 'announcement_enabled') $_annEnabled = $_r['value'];
        if ($_r['key'] === 'announcement_text')    $_annText    = $_r['value'];
        if ($_r['key'] === 'ann_bar_speed')        $_annSpeed   = max(5, (int)$_r['value']);
    }
} catch (Throwable $_e) {}
$_annText = htmlspecialchars($_annText);
?>
<?php if ($_annEnabled === '1'): ?>
<div class="announcement-bar" id="announcement-bar"<?= !empty($pageHome) ? ' style="opacity:0;pointer-events:none"' : '' ?>>
  <!-- Left badge -->
  <div class="ann-badge">OFFER</div>

  <!-- Scrolling text -->
  <div class="ann-marquee-wrap">
    <?php
    $chunk = $_annText . ' &nbsp; <span class="ann-sep">◆</span> &nbsp; ';
    $track = str_repeat($chunk, 8);
    ?>
    <div class="ann-marquee-inner" style="animation-duration:<?= (int)$_annSpeed ?>s">
      <span class="ann-track"><?= $track ?></span>
      <span class="ann-track" aria-hidden="true"><?= $track ?></span>
    </div>
  </div>

</div>
<?php endif; ?>

<!-- Header -->
<header class="site-header" id="site-header"<?= !empty($pageHome) ? ' style="opacity:0;pointer-events:none"' : '' ?>>
  <nav class="nav-container">
    <!-- Logo -->
    <?php
    $_siteLogo  = '';
    $_logoH     = 36;
    $_logoMode  = 'text';
    $_name1     = 'DUHN';
    $_name2     = 'FRAGRANCES';
    try {
        $_logoRows = Database::getInstance()->query(
            "SELECT `key`, `value` FROM `settings`
             WHERE `key` IN ('site_logo','site_logo_height','logo_mode','site_name_1','site_name_2')"
        )->fetchAll();
        foreach ($_logoRows as $_lr) {
            if ($_lr['key'] === 'site_logo')        $_siteLogo = $_lr['value'];
            if ($_lr['key'] === 'site_logo_height') $_logoH    = max(20, (int)$_lr['value']);
            if ($_lr['key'] === 'logo_mode')        $_logoMode = $_lr['value'];
            if ($_lr['key'] === 'site_name_1')      $_name1    = $_lr['value'];
            if ($_lr['key'] === 'site_name_2')      $_name2    = $_lr['value'];
        }
    } catch (Throwable $_le) {}
    // If no logo uploaded, always fall back to text
    if (!$_siteLogo) $_logoMode = 'text';
    ?>
    <a href="/index.php" class="nav-logo<?= ($_logoMode !== 'text') ? ' nav-logo--img' : '' ?>"
       style="display:flex;align-items:center;gap:8px;text-decoration:none"
       aria-label="<?= htmlspecialchars($_name1 . ($_name2 ? ' ' . $_name2 : '')) ?>">
      <?php if ($_siteLogo && ($_logoMode === 'image' || $_logoMode === 'both')): ?>
        <img src="<?= htmlspecialchars($_siteLogo) ?>"
             alt="<?= htmlspecialchars($_name1) ?>"
             style="height:<?= $_logoH ?>px;width:auto;object-fit:contain;display:block">
      <?php endif; ?>
      <?php if ($_logoMode === 'text' || $_logoMode === 'both'): ?>
        <?php
        // On homepage: text starts white (hero is dark); CSS flips it dark on scroll via .header-visible
        // On all other pages: header is already white so text must be dark immediately
        $_logoTextColor = !empty($pageHome) ? '#ffffff' : '#1A1A1A';
        ?>
        <span class="logo-text-wrap" style="font-family:'Jost',sans-serif;font-size:inherit;font-weight:700;letter-spacing:.1em;white-space:nowrap;color:<?= $_logoTextColor ?>">
          <?= htmlspecialchars($_name1) ?><?php if ($_name2): ?>&nbsp;<span class="logo-text-accent" style="color:var(--accent)"><?= htmlspecialchars($_name2) ?></span><?php endif; ?>
        </span>
      <?php endif; ?>
    </a>

    <!-- Nav Links (desktop) -->
    <ul class="nav-links" id="nav-links">
      <li><a href="/index.php" class="<?= (basename($_SERVER['PHP_SELF']) === 'index.php') ? 'active' : '' ?>">Home</a></li>
      <li><a href="/collections.php?slug=new-drops">NEW DROPS</a></li>
      <li><a href="/collections.php?slug=for-him">For Him</a></li>
      <li><a href="/collections.php?slug=for-her">For Her</a></li>
      <li><a href="/collections.php?slug=bestsellers">Bestsellers</a></li>
    </ul>

    <!-- Nav Actions -->
    <div class="nav-actions">
      <!-- Social Icons -->
      <div class="nav-socials">
        <a href="https://www.facebook.com/duhnfragrances" target="_blank" class="nav-social-icon" title="Facebook" aria-label="Facebook">
          <i class="ph ph-facebook-logo"></i>
        </a>
        <a href="https://www.instagram.com/duhnfragrances" target="_blank" class="nav-social-icon" title="Instagram" aria-label="Instagram">
          <i class="ph ph-instagram-logo"></i>
        </a>
        <a href="https://www.tiktok.com/@duhnfragrances" target="_blank" class="nav-social-icon" title="TikTok" aria-label="TikTok">
          <i class="ph ph-tiktok-logo"></i>
        </a>
      </div>

      <button class="nav-icon-btn" data-search-open title="Search">
        <i class="ph ph-magnifying-glass"></i>
      </button>

      <a href="/account.php" class="nav-icon-btn" id="account-icon" title="Account">
        <i class="ph ph-user"></i>
      </a>

      <button class="nav-icon-btn" id="wallet-icon" title="My Wallet" onclick="Wallet.open(event)" style="display:none" aria-label="Wallet">
        <i class="ph ph-wallet"></i>
        <span class="wallet-badge" id="wallet-badge" style="display:none"></span>
      </button>

      <button class="nav-icon-btn" data-cart-open title="Cart">
        <i class="ph ph-shopping-bag"></i>
        <span class="cart-badge" style="display:none">0</span>
      </button>

      <button class="nav-icon-btn nav-toggle" id="nav-toggle" aria-label="Menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </nav>
</header>

<!-- Search Overlay -->
<div class="search-overlay" id="search-overlay">
  <div class="search-input-wrapper">
    <input type="search" class="search-input" id="search-input" placeholder="Search fragrances, notes, moods..." autocomplete="off">
    <button class="search-close" id="search-close" aria-label="Close">✕</button>
  </div>
  <div class="search-results" id="search-results"></div>
</div>

<!-- Cart Sidebar -->
<div class="cart-overlay" id="cart-overlay">
  <div class="cart-backdrop" id="cart-backdrop"></div>
  <div class="cart-sidebar">
    <div class="cart-header">
      <h3>Shopping Cart</h3>
      <button class="cart-close" id="cart-close" aria-label="Close cart">✕</button>
    </div>

    <div class="cart-items-list" id="cart-items-list"></div>

    <div class="cart-empty" id="cart-empty" style="display:none">
      <div class="cart-empty__icon">
        <i class="ph ph-package"></i>
      </div>
      <h4 class="cart-empty__title">Your cart is empty</h4>
      <p class="cart-empty__desc">You may check out all the available products and buy some in the shop.</p>
      <a href="/collections.php" class="cart-empty__btn" onclick="Cart.close()">CONTINUE SHOPPING</a>
    </div>

    <div class="cart-promo-banner" id="cart-promo" style="display:none">
      🎁 <span id="cart-promo-label">BUY 2 GET 2 FREE applied!</span>
    </div>

    <div class="cart-footer" id="cart-footer" style="display:none">
      <div class="cart-totals">
        <div class="row">
          <span>Subtotal</span>
          <span id="cart-subtotal">— EGP</span>
        </div>
        <div class="row discount" id="cart-discount-row">
          <span id="cart-discount-label">Promo Discount</span>
          <span id="cart-discount">—</span>
        </div>
        <div class="row">
          <span>Delivery</span>
          <span id="cart-delivery">FREE</span>
        </div>
        <div class="row total">
          <span>Total</span>
          <span id="cart-total">— EGP</span>
        </div>
      </div>
      <!-- Promo Code Input -->
      <div class="cart-promo-input" id="cart-promo-input-wrap">
        <div id="promo-applied-row" style="display:none;align-items:center;justify-content:space-between;margin-bottom:10px;padding:8px 12px;background:rgba(40,167,69,0.12);border:1px solid rgba(40,167,69,0.3);border-radius:6px">
          <span style="font-size:12px;color:#6fcf97;font-weight:700" id="promo-applied-label"></span>
          <button type="button" onclick="Cart.removePromo()" style="background:none;border:none;color:#aaa;cursor:pointer;font-size:11px;text-decoration:underline">Remove</button>
        </div>
        <div id="promo-input-row" style="display:flex;gap:8px">
          <input type="text" id="promo-code-input" placeholder="Promo code"
                 style="flex:1;padding:9px 12px;border:1px solid #ddd;border-radius:6px;font-size:13px;text-transform:uppercase;letter-spacing:0.05em"
                 oninput="this.value=this.value.toUpperCase()" onkeydown="if(event.key==='Enter')Cart.applyPromo()">
          <button type="button" onclick="Cart.applyPromo()"
                  style="padding:9px 14px;background:#1A1A1A;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap"
                  id="promo-apply-btn">APPLY</button>
        </div>
        <div id="promo-msg" style="font-size:11px;margin-top:6px;display:none"></div>
      </div>

      <a href="/checkout.php" class="btn btn-gold btn-full" onclick="Cart.close()">PROCEED TO CHECKOUT</a>
    </div>
  </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toast-container"></div>

<!-- Newsletter Popup -->
<?php
$_nlEnabled  = '1';
$_nlDelay    = 1800;
$_nlEyebrow  = 'SIGNUP FOR EMAILS';
$_nlTitle    = 'GET 20% DISCOUNT SHIPPED TO YOUR INBOX';
$_nlDesc     = "Let's Subscribe to our newsletter and we will ship 20% discount code today";
$_nlBtnText  = 'SUBSCRIBE';
$_nlResetAt  = 0;
try {
    $_nlRows = Database::getInstance()->query(
        "SELECT `key`, `value` FROM `settings` WHERE `key` LIKE 'nl_popup_%'"
    )->fetchAll();
    foreach ($_nlRows as $_r) {
        match ($_r['key']) {
            'nl_popup_enabled'  => $_nlEnabled  = $_r['value'],
            'nl_popup_delay'    => $_nlDelay    = max(0, (int)$_r['value']),
            'nl_popup_eyebrow'  => $_nlEyebrow  = $_r['value'],
            'nl_popup_title'    => $_nlTitle    = $_r['value'],
            'nl_popup_desc'     => $_nlDesc     = $_r['value'],
            'nl_popup_btn_text' => $_nlBtnText  = $_r['value'],
            'nl_popup_reset_at' => $_nlResetAt  = (int)$_r['value'],
            default => null,
        };
    }
} catch (Throwable $_e) {}
?>
<?php if ($_nlEnabled !== '0'): ?>
<div class="nl-popup" id="newsletter-popup"
     style="display:none"
     data-delay="<?= (int)$_nlDelay ?>"
     data-reset-at="<?= (int)$_nlResetAt ?>"
     role="dialog" aria-modal="true" aria-label="Newsletter signup">
  <div class="nl-popup__backdrop" id="nl-backdrop"></div>
  <div class="nl-popup__modal">
    <button class="nl-popup__close" id="nl-close" aria-label="Close popup">✕</button>
    <p class="nl-popup__eyebrow"><?= htmlspecialchars($_nlEyebrow) ?></p>
    <div class="nl-popup__divider"></div>
    <h2 class="nl-popup__title"><?= htmlspecialchars($_nlTitle) ?></h2>
    <p class="nl-popup__desc"><?= htmlspecialchars($_nlDesc) ?></p>
    <form class="nl-popup__form newsletter-form" id="nl-form">
      <input type="email" id="nl-email" placeholder="Enter your email..." required class="nl-popup__input" autocomplete="email">
      <button type="submit" class="nl-popup__btn"><?= htmlspecialchars($_nlBtnText) ?></button>
    </form>
    <button class="nl-popup__skip" id="nl-skip">No, Thanks.</button>
  </div>
</div>
<?php endif; ?>

<!-- Scroll to Top -->
<button class="scroll-top-btn" id="scroll-top" aria-label="Scroll to top" style="display:none">
  <i class="ph ph-arrow-up"></i>
</button>

<!-- Wallet Panel -->
<div id="wallet-panel" style="display:none;position:fixed;top:70px;right:80px;z-index:9000;width:280px;background:#1a1a1a;border:1px solid rgba(200,160,48,0.3);border-radius:12px;padding:20px;box-shadow:0 8px 40px rgba(0,0,0,0.6)">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
    <span style="font-size:13px;font-weight:700;color:var(--accent);letter-spacing:.06em"><i class="ph ph-wallet"></i> MY WALLET</span>
    <button onclick="Wallet.close()" style="background:none;border:none;color:#888;cursor:pointer;font-size:16px">✕</button>
  </div>
  <div id="wallet-panel-content">
    <p style="font-size:12px;color:#aaa">Loading...</p>
  </div>
</div>

<!-- Main content starts here -->
<main>
