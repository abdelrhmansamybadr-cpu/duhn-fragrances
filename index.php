<?php
/**
 * DUHN FRAGRANCES — Homepage
 */
require_once __DIR__ . '/api/config/config.php';
require_once __DIR__ . '/api/config/database.php';

$db = Database::getInstance();

// Fetch site settings (hero, promo, announcement)
$settingsRows = $db->query("SELECT `key`, `value` FROM `settings`")->fetchAll();
$siteSettings = [];
foreach ($settingsRows as $row) { $siteSettings[$row['key']] = $row['value']; }

// Helper: render hero title — supports HTML from RTE or legacy \n plain text
function heroTitle(string $raw): string {
    // Legacy plain text with \n escape → convert to <br>
    if (strpos($raw, '<') === false) {
        return nl2br(htmlspecialchars(str_replace('\n', "\n", $raw)));
    }
    // RTE HTML — already sanitized on save, render directly
    return $raw;
}

// Fetch featured products
$featuredStmt = $db->query("
    SELECT p.*,
           (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) AS image,
           NULL AS offer_badge
    FROM products p WHERE p.is_featured = 1
    ORDER BY p.avg_rating DESC LIMIT 8
");
$featuredProducts = $featuredStmt->fetchAll();

// Auto-migrate is_hidden on collections (safe on every request)
try { $db->exec("ALTER TABLE collections ADD COLUMN is_hidden TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $_) {}

// Fetch visible collections only
$collectionsStmt = $db->query("
    SELECT c.*, COUNT(pc.product_id) AS product_count
    FROM collections c
    LEFT JOIN product_collections pc ON pc.collection_id = c.id
    WHERE c.is_hidden = 0
    GROUP BY c.id ORDER BY c.sort_order ASC
");
$collections = $collectionsStmt->fetchAll();

// Fetch approved reviews (homepage sample)
$reviewsStmt = $db->query("
    SELECT r.*, p.name AS product_name
    FROM reviews r
    JOIN products p ON p.id = r.product_id
    WHERE r.is_approved = 1
    ORDER BY r.rating DESC, r.created_at DESC
    LIMIT 6
");
$reviews = $reviewsStmt->fetchAll();

// Auto-migrate is_top_rated column if missing
try { $db->exec("ALTER TABLE products ADD COLUMN is_top_rated TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $_) {}

// Fetch top-rated products: manually pinned first, then auto-fill by avg_rating
$topRatedStmt = $db->query("
    SELECT p.*,
           (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) AS image
    FROM products p
    WHERE p.is_top_rated = 1 OR p.review_count > 0
    ORDER BY p.is_top_rated DESC, p.avg_rating DESC, p.review_count DESC
    LIMIT 3
");
$topRatedProducts = $topRatedStmt->fetchAll();

// Fetch new drops (for 3-col list)
$newDropsStmt = $db->query("
    SELECT p.*,
           (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) AS image
    FROM products p
    WHERE p.is_new_drop = 1
    ORDER BY p.created_at DESC LIMIT 3
");
$newDropProducts = $newDropsStmt->fetchAll();

// Decode HTML entities stored by old admin code (e.g. &#039; → ')
$_decodeFields = ['name', 'inspired_by', 'short_description'];
foreach ($featuredProducts as &$_p) {
    foreach ($_decodeFields as $_f) {
        if (!empty($_p[$_f])) $_p[$_f] = html_entity_decode($_p[$_f], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
foreach ($topRatedProducts as &$_p) {
    foreach ($_decodeFields as $_f) {
        if (!empty($_p[$_f])) $_p[$_f] = html_entity_decode($_p[$_f], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
foreach ($newDropProducts as &$_p) {
    foreach ($_decodeFields as $_f) {
        if (!empty($_p[$_f])) $_p[$_f] = html_entity_decode($_p[$_f], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
unset($_p, $_decodeFields, $_f);

$pageTitle = 'DUHN FRAGRANCES — Indulge Your Senses';
$pageDesc  = 'Premium Egyptian fragrance brand. Luxury 50ml perfumes inspired by the world\'s finest designers. BUY 2 GET 2 FREE.';
$pageHome  = true;
require_once __DIR__ . '/public/layout/header.php';
require_once __DIR__ . '/public/layout/loader.php';
?>

<!-- ── HERO SLIDER — fully DB-driven ─────────────────────────── -->
<?php
$heroCount = max(1, (int)($siteSettings['hero_count'] ?? 2));

// Helper: load per-slide buttons from DB
function getHeroButtons(array $ss, int $n): array {
    $hk    = "hero_{$n}";
    $count = (int)($ss["{$hk}_btn_count"] ?? 0);
    $btns  = [];
    if ($count > 0) {
        for ($b = 1; $b <= min($count, 3); $b++) {
            $t = $ss["{$hk}_btn_{$b}_text"]  ?? '';
            $u = $ss["{$hk}_btn_{$b}_url"]   ?? '/collections.php';
            $s = $ss["{$hk}_btn_{$b}_style"] ?? 'solid';
            if ($t) $btns[] = ['text' => htmlspecialchars($t), 'url' => htmlspecialchars($u), 'style' => $s];
        }
    }
    if (empty($btns)) {
        // Fallback: legacy single-button settings
        $lt = $ss["{$hk}_btn_text"] ?? ($ss["hero{$n}_btn_text"] ?? '');
        $lu = $ss["{$hk}_btn_url"]  ?? ($ss["hero{$n}_btn_url"]  ?? '/collections.php');
        if ($lt) $btns[] = ['text' => htmlspecialchars($lt), 'url' => htmlspecialchars($lu), 'style' => 'solid'];
    }
    if (empty($btns)) {
        $btns[] = ['text' => 'Shop Now', 'url' => '/collections.php', 'style' => 'solid'];
    }
    return $btns;
}
?>
<section class="hero-slider">
  <?php for ($hn = 1; $hn <= $heroCount; $hn++):
    $hk         = "hero_{$hn}";
    $eyebrow    = $siteSettings["{$hk}_eyebrow"]  ?? '';  // sanitized HTML from RTE
    $titleRaw   = $siteSettings["{$hk}_title"]    ?? ($siteSettings["hero{$hn}_title"] ?? '');
    $subtitle   = $siteSettings["{$hk}_subtitle"] ?? ($siteSettings["hero{$hn}_subtitle"] ?? '');  // sanitized HTML
    $heroImg    = htmlspecialchars($siteSettings["{$hk}_image"]    ?? ($siteSettings["hero{$hn}_image"] ?? "/public/images/hero/hero-{$hn}.jpg"));
    $btnAbove   = htmlspecialchars($siteSettings["{$hk}_btn_above_text"] ?? '');
    $heroBtns   = getHeroButtons($siteSettings, $hn);
    $validPos   = ['top-left','top-center','top-right','mid-left','mid-center','mid-right','bot-left','bot-center','bot-right'];
    $contentPos = $siteSettings["{$hk}_content_pos"] ?? 'mid-left';
    if (!in_array($contentPos, $validPos)) $contentPos = 'mid-left';
  ?>
  <div class="hero-slide <?= $hn === 1 ? 'active' : '' ?>">
    <img src="<?= $heroImg ?>" alt="DUHN FRAGRANCES — Slide <?= $hn ?>">
    <div class="hero-overlay"></div>
    <div class="hero-pos hero-pos--<?= $contentPos ?>">
      <div class="hero-content">
        <?php if ($eyebrow): ?><p class="hero-eyebrow"><?= $eyebrow ?></p><?php endif; ?>
        <h1 class="hero-title"><?= heroTitle($titleRaw) ?></h1>
        <?php if ($subtitle): ?><p class="hero-subtitle"><?= $subtitle ?></p><?php endif; ?>
        <?php if (!empty($heroBtns)): ?>
        <div class="hero-cta-group">
          <?php if ($btnAbove): ?><p class="hero-btn-above-text"><?= $btnAbove ?></p><?php endif; ?>
          <?php foreach ($heroBtns as $hbtn): ?>
          <a href="<?= $hbtn['url'] ?>"
             class="<?= $hbtn['style'] === 'ghost' ? 'btn btn-hero-secondary' : 'btn btn-hero-primary' ?>">
            <?= $hbtn['text'] ?>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endfor; ?>

  <div class="hero-dots">
    <?php for ($d = 1; $d <= $heroCount; $d++): ?>
    <button class="hero-dot <?= $d === 1 ? 'active' : '' ?>" aria-label="Slide <?= $d ?>"></button>
    <?php endfor; ?>
  </div>
  <div class="hero-scroll-hint">
    <span>Scroll</span>
    <i class="ph ph-arrow-down"></i>
  </div>
</section>

<!-- ── PROMO MARQUEE ─────────────────────────────────────────── -->
<?php
$promoText  = htmlspecialchars($siteSettings['promo_text']  ?? 'BUY 2 GET 2 FREE  ✦  ALL PERFUMES 899 EGP  ✦  FREE DELIVERY');
$promoSpeed = max(5, (int)($siteSettings['promo_speed'] ?? 22));
$promoChunk = $promoText . ' &nbsp;&nbsp;';
$promoTrack = str_repeat($promoChunk, 6);
?>
<div class="promo-marquee">
  <div class="marquee-inner" style="animation-duration:<?= $promoSpeed ?>s">
    <span class="marquee-track promo-track"><?= $promoTrack ?></span>
    <span class="marquee-track promo-track"><?= $promoTrack ?></span>
  </div>
</div>

<!-- ── WHY DUHN FRAGRANCES ────────────────────────────────────────────── -->
<?php
$whyDefaults = [
  1 => ['icon'=>'ph-medal',          'title'=>'Premium Quality',    'desc'=>'Crafted with the finest fragrance oils — every bottle is a work of art that lasts all day.'],
  2 => ['icon'=>'ph-clock-countdown','title'=>'All-Day Longevity',  'desc'=>'Our 50ml EDP formula is designed for 8–12 hour wear, so you stay memorable from morning to midnight.'],
  3 => ['icon'=>'ph-package',        'title'=>'Free Delivery',      'desc'=>'Every order ships free to your door anywhere in Egypt. Fast, safe, and beautifully packaged.'],
  4 => ['icon'=>'ph-gift',           'title'=>'Buy 2, Get 2 Free',  'desc'=>'Our signature deal — pick any 4 fragrances and pay for only 2. Automatically applied at checkout.'],
];
?>
<section class="why-section">
  <div class="container">
    <div class="why-grid">
      <?php for ($wi = 1; $wi <= 4; $wi++):
        $wIcon  = htmlspecialchars($siteSettings["why_{$wi}_icon"]  ?? $whyDefaults[$wi]['icon']);
        $wTitle = htmlspecialchars($siteSettings["why_{$wi}_title"] ?? $whyDefaults[$wi]['title']);
        $wDesc  = htmlspecialchars($siteSettings["why_{$wi}_desc"]  ?? $whyDefaults[$wi]['desc']);
        if (($siteSettings["why_{$wi}_enabled"] ?? '1') === '0') continue;
      ?>
      <div class="why-card">
        <div class="why-icon"><i class="ph <?= $wIcon ?>"></i></div>
        <h3 class="why-title"><?= $wTitle ?></h3>
        <p class="why-desc"><?= $wDesc ?></p>
      </div>
      <?php endfor; ?>
    </div>
  </div>
</section>

<!-- ── FEATURED PRODUCTS ─────────────────────────────────────── -->
<section class="section section--products">
  <div class="container">
    <div class="section-header section-header--catier">
      <h2 class="section-title"><?= htmlspecialchars($siteSettings['featured_title'] ?? 'BEST SELLER') ?></h2>
      <p class="section-subtitle"><?= htmlspecialchars($siteSettings['featured_subtitle'] ?? 'Best Seller Product This Week!') ?></p>
    </div>

    <div class="featured-grid">
      <?php foreach ($featuredProducts as $product): ?>
      <div class="product-card">
        <a href="/product.php?slug=<?= urlencode($product['slug']) ?>" class="product-card__image">
          <?php if (!empty($product['offer_badge'])): ?>
          <span class="product-card__badge offer"><?= htmlspecialchars($product['offer_badge']) ?></span>
          <?php elseif ($product['is_new_drop']): ?>
          <span class="product-card__badge new">NEW</span>
          <?php endif; ?>
          <img
            src="<?= !empty($product['image']) ? htmlspecialchars($product['image']) : '/public/images/placeholder.jpg' ?>"
            alt="<?= htmlspecialchars($product['name']) ?>"
            loading="lazy"
            onerror="this.src='/public/images/placeholder.jpg'">
          <div class="product-card__hover-actions">
            <button class="product-action-circle" onclick="event.preventDefault();Cart.add(<?= (int)$product['id'] ?>, 1, this)" title="Add to Cart">
              <i class="ph ph-shopping-bag"></i>
            </button>
          </div>
        </a>
        <div class="product-card__body">
          <a href="/product.php?slug=<?= urlencode($product['slug']) ?>">
            <div class="product-card__name"><?= htmlspecialchars($product['name']) ?></div>
          </a>
          <?php if ($product['inspired_by']): ?>
          <div class="product-card__inspired">Inspired by <?= htmlspecialchars($product['inspired_by']) ?></div>
          <?php endif; ?>
          <?php if ($product['review_count'] > 0): ?>
          <div class="product-card__rating">
            <div class="stars">
              <?php
              $rating = (float)$product['avg_rating'];
              for ($i = 1; $i <= 5; $i++):
                if ($i <= floor($rating)) echo '<span>★</span>';
                elseif ($i - $rating < 1) echo '<span style="opacity:0.5">★</span>';
                else echo '<span class="empty">★</span>';
              endfor;
              ?>
            </div>
            <span class="review-count">(<?= number_format($product['review_count']) ?>)</span>
          </div>
          <?php endif; ?>
          <div class="product-card__price">
            <?= number_format((float)$product['price'], 1) ?>
            <span class="currency">EGP</span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>

      <?php if (empty($featuredProducts)): ?>
      <p style="color:var(--text-muted);padding:40px;text-align:center;width:100%">No featured products yet. Add products via the <a href="/admin/" style="color:var(--accent)">Admin Panel</a>.</p>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ── COLLECTIONS GRID ──────────────────────────────────────── -->
<section class="section section--collections">
  <div class="container">
    <div class="section-header section-header--catier">
      <h2 class="section-title"><?= htmlspecialchars($siteSettings['collections_title'] ?? 'SHOP BY COLLECTION') ?></h2>
    </div>

    <div class="collection-grid-v2">
      <?php foreach ($collections as $col): ?>
      <div class="collection-item<?= empty($col['cover_image_url']) ? ' collection-item--no-img' : '' ?>">
        <a href="/collections.php?slug=<?= urlencode($col['slug']) ?>" class="collection-item__media">
          <?php if (!empty($col['cover_image_url'])): ?>
          <img
            src="<?= htmlspecialchars($col['cover_image_url']) ?>"
            alt="<?= htmlspecialchars($col['name']) ?>"
            loading="lazy"
            onerror="this.parentElement.parentElement.classList.add('collection-item--no-img')">
          <?php else: ?>
          <div class="collection-item__placeholder"></div>
          <?php endif; ?>
        </a>
        <div class="collection-item__body">
          <h3 class="collection-item__name"><?= htmlspecialchars($col['name']) ?></h3>
          <?php if ($col['product_count'] > 0): ?>
          <span class="collection-item__count"><?= $col['product_count'] ?> products</span>
          <?php endif; ?>
          <a href="/collections.php?slug=<?= urlencode($col['slug']) ?>" class="collection-item__btn">ALL PRODUCTS →</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── COLLECTION EDITORIAL BANNER ──────────────────────────── -->
<?php
$ceMainTitle    = $siteSettings['coll_ed_main_title']     ?? 'Check Out The Latest Collection Of Fragrances';
$ceMainLink     = htmlspecialchars($siteSettings['coll_ed_main_link_text'] ?? 'SHOP COLLECTION');
$ceMainUrl      = htmlspecialchars($siteSettings['coll_ed_main_url']       ?? '/collections.php');
$ceItem1Title   = $siteSettings['coll_ed_1_title']        ?? 'Shop Exquisite Fragrances';
$ceItem1Link    = htmlspecialchars($siteSettings['coll_ed_1_link_text']    ?? 'SHOP NOW');
$ceItem1Url     = htmlspecialchars($siteSettings['coll_ed_1_url']          ?? '/collections.php');
$ceItem2Title   = $siteSettings['coll_ed_2_title']        ?? 'Top Luxury Scents';
$ceItem2Link    = htmlspecialchars($siteSettings['coll_ed_2_link_text']    ?? 'SHOP NOW');
$ceItem2Url     = htmlspecialchars($siteSettings['coll_ed_2_url']          ?? '/collections.php');
?>
<section class="coll-editorial">
  <div class="coll-editorial__grid">
    <!-- Left: big feature panel -->
    <a href="<?= $ceMainUrl ?>" class="coll-ed-main">
      <?php if (!empty($collections[0]['cover_image_url'])): ?>
      <img src="<?= htmlspecialchars($collections[0]['cover_image_url']) ?>" alt="<?= htmlspecialchars($collections[0]['name'] ?? 'Collection') ?>">
      <?php else: ?>
      <div class="coll-ed-main__placeholder"></div>
      <?php endif; ?>
      <div class="coll-ed-main__overlay"></div>
      <div class="coll-ed-main__content">
        <h2 class="coll-ed-main__title"><?= htmlspecialchars($ceMainTitle) ?></h2>
        <span class="coll-ed-main__link"><?= $ceMainLink ?></span>
      </div>
    </a>

    <!-- Right: two stacked panels -->
    <div class="coll-ed-stack">
      <a href="<?= $ceItem1Url ?>" class="coll-ed-item">
        <?php if (!empty($collections[1]['cover_image_url'] ?? '')): ?>
        <img src="<?= htmlspecialchars($collections[1]['cover_image_url']) ?>" alt="<?= htmlspecialchars($collections[1]['name'] ?? '') ?>">
        <?php else: ?>
        <div class="coll-ed-item__placeholder"></div>
        <?php endif; ?>
        <div class="coll-ed-item__overlay"></div>
        <div class="coll-ed-item__content">
          <h3 class="coll-ed-item__title"><?= htmlspecialchars($ceItem1Title) ?></h3>
          <span class="coll-ed-item__link"><?= $ceItem1Link ?></span>
        </div>
      </a>
      <a href="<?= $ceItem2Url ?>" class="coll-ed-item">
        <?php if (!empty($collections[2]['cover_image_url'] ?? '')): ?>
        <img src="<?= htmlspecialchars($collections[2]['cover_image_url']) ?>" alt="<?= htmlspecialchars($collections[2]['name'] ?? '') ?>">
        <?php else: ?>
        <div class="coll-ed-item__placeholder"></div>
        <?php endif; ?>
        <div class="coll-ed-item__overlay"></div>
        <div class="coll-ed-item__content">
          <h3 class="coll-ed-item__title"><?= htmlspecialchars($ceItem2Title) ?></h3>
          <span class="coll-ed-item__link"><?= $ceItem2Link ?></span>
        </div>
      </a>
    </div>
  </div>
</section>

<!-- ── 3-COLUMN PRODUCT LIST ─────────────────────────────────── -->
<?php if (!empty($featuredProducts) || !empty($topRatedProducts) || !empty($newDropProducts)): ?>
<section class="section section--product-cols">
  <div class="container">
    <div class="product-cols-grid">

      <!-- Column 1: Featured -->
      <div class="product-col-list">
        <h3 class="product-col-list__heading">FEATURED PRODUCTS</h3>
        <?php foreach (array_slice($featuredProducts, 0, 3) as $p): ?>
        <a href="/product.php?slug=<?= urlencode($p['slug']) ?>" class="product-col-row">
          <div class="product-col-row__thumb">
            <img src="<?= !empty($p['image']) ? htmlspecialchars($p['image']) : '/public/images/placeholder.jpg' ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
          </div>
          <div class="product-col-row__info">
            <div class="product-col-row__name"><?= htmlspecialchars($p['name']) ?></div>
            <div class="product-col-row__price">
              <span class="price-sale"><?= number_format((float)$p['price'], 0) ?> EGP</span>
              <span class="price-original">1,200 EGP</span>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- Column 2: Top Rated -->
      <div class="product-col-list">
        <h3 class="product-col-list__heading">TOP RATED PRODUCTS</h3>
        <?php foreach ($topRatedProducts as $p): ?>
        <a href="/product.php?slug=<?= urlencode($p['slug']) ?>" class="product-col-row">
          <div class="product-col-row__thumb">
            <img src="<?= !empty($p['image']) ? htmlspecialchars($p['image']) : '/public/images/placeholder.jpg' ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
          </div>
          <div class="product-col-row__info">
            <div class="product-col-row__name"><?= htmlspecialchars($p['name']) ?></div>
            <div class="product-col-row__price">
              <span class="price-sale"><?= number_format((float)$p['price'], 0) ?> EGP</span>
              <span class="price-original">1,200 EGP</span>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- Column 3: New Drops -->
      <div class="product-col-list">
        <h3 class="product-col-list__heading">NEW DROPS</h3>
        <?php foreach ($newDropProducts as $p): ?>
        <a href="/product.php?slug=<?= urlencode($p['slug']) ?>" class="product-col-row">
          <div class="product-col-row__thumb">
            <img src="<?= !empty($p['image']) ? htmlspecialchars($p['image']) : '/public/images/placeholder.jpg' ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
          </div>
          <div class="product-col-row__info">
            <div class="product-col-row__name"><?= htmlspecialchars($p['name']) ?></div>
            <div class="product-col-row__price">
              <span class="price-sale"><?= number_format((float)$p['price'], 0) ?> EGP</span>
              <span class="price-original">899 EGP</span>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>

    </div>
  </div>
</section>
<?php endif; ?>

<!-- ── BRAND BANNER ──────────────────────────────────────────── -->
<?php
// Keys saved by admin/homepage-sections.php
$bbRawColor = ltrim(trim($siteSettings['brand_banner_bg_color'] ?? '1A1A1A'), '#');
if (!preg_match('/^[0-9a-fA-F]{3,6}$/', $bbRawColor)) $bbRawColor = '1A1A1A';
$bbColor   = '#' . $bbRawColor;
$bbPattern = $siteSettings['brand_banner_bg_pattern'] ?? 'diagonal';
$bbEyebrow = htmlspecialchars($siteSettings['brand_banner_eyebrow']  ?? 'DUHN FRAGRANCES');
$bbTitle   = nl2br(htmlspecialchars($siteSettings['brand_banner_title'] ?? "YOU ONLY GET ONE CHANCE\nTO MAKE THE FIRST IMPRESSION."));
$bbSub     = htmlspecialchars($siteSettings['brand_banner_subtitle'] ?? 'Premium fragrances crafted for those who refuse to be forgotten.');
$bbBtnText = htmlspecialchars($siteSettings['brand_banner_btn_text'] ?? 'Explore All Scents');
$bbBtnUrl  = htmlspecialchars($siteSettings['brand_banner_btn_url']  ?? '/collections.php');

// Determine if background is light or dark → pick readable text color
$hex = str_pad($bbRawColor, 6, '0');
$r = hexdec(substr($hex,0,2)); $g = hexdec(substr($hex,2,2)); $b = hexdec(substr($hex,4,2));
$luminance = (0.299*$r + 0.587*$g + 0.114*$b) / 255;
$bbTextColor   = $luminance > 0.55 ? '#1A1A1A' : '#ffffff';
$bbMutedColor  = $luminance > 0.55 ? 'rgba(0,0,0,0.45)' : 'rgba(255,255,255,0.6)';
$bbSubColor    = $luminance > 0.55 ? 'rgba(0,0,0,0.55)' : 'rgba(255,255,255,0.75)';
$bbBtnBg       = $luminance > 0.55 ? '#1A1A1A' : '#ffffff';
$bbBtnFg       = $luminance > 0.55 ? '#ffffff' : '#1A1A1A';

$bbPatternSvg = match($bbPattern) {
    'dots'     => "url(\"data:image/svg+xml,%3Csvg width='20' height='20' xmlns='http://www.w3.org/2000/svg'%3E%3Ccircle cx='2' cy='2' r='1.5' fill='rgba(255,255,255,0.07)'/%3E%3C/svg%3E\")",
    'lines'    => "repeating-linear-gradient(0deg,rgba(255,255,255,0.05) 0,rgba(255,255,255,0.05) 1px,transparent 1px,transparent 24px)",
    'diagonal' => "repeating-linear-gradient(45deg,rgba(255,255,255,0.04) 0,rgba(255,255,255,0.04) 1px,transparent 1px,transparent 18px)",
    default    => 'none',
};
?>
<section class="brand-banner" style="background:<?= $bbColor ?>">
  <div class="brand-banner__bg" style="background:<?= $bbPatternSvg ?>"></div>
  <div class="container brand-banner__inner">
    <p class="brand-banner__eyebrow" style="color:<?= $bbMutedColor ?>"><?= $bbEyebrow ?></p>
    <h2 class="brand-banner__title" style="color:<?= $bbTextColor ?>"><?= $bbTitle ?></h2>
    <p class="brand-banner__sub" style="color:<?= $bbSubColor ?>"><?= $bbSub ?></p>
    <?php if ($bbBtnText): ?>
    <a href="<?= $bbBtnUrl ?>" class="btn btn-hero-primary"
       style="border-radius:0;background:<?= $bbBtnBg ?>;color:<?= $bbBtnFg ?>;border-color:<?= $bbBtnBg ?>"><?= $bbBtnText ?></a>
    <?php endif; ?>
  </div>
</section>

<!-- ── OUR TESTIMONIAL ───────────────────────────────────────── -->
<?php if (!empty($reviews)): ?>
<section class="section section--testimonials">
  <div class="container">
    <div class="testimonial-heading">
      <h2 class="testimonial-heading__title">OUR TESTIMONIAL</h2>
      <div class="testimonial-heading__line"></div>
    </div>

    <div class="testimonial-grid">
      <?php foreach (array_slice($reviews, 0, 3) as $review):
        $initials = strtoupper(mb_substr(trim($review['reviewer_name']), 0, 1));
      ?>
      <div class="testimonial-card">
        <div class="testimonial-card__avatar">
          <span class="testimonial-card__initials"><?= htmlspecialchars($initials) ?></span>
        </div>
        <div class="testimonial-card__stars">
          <?php for ($i = 1; $i <= 5; $i++): ?>
          <span class="<?= $i <= $review['rating'] ? 'star-filled' : 'star-empty' ?>">★</span>
          <?php endfor; ?>
        </div>
        <?php if ($review['body']): ?>
        <p class="testimonial-card__text"><?= htmlspecialchars($review['body']) ?></p>
        <?php endif; ?>
        <div class="testimonial-card__name"><?= htmlspecialchars(strtoupper($review['reviewer_name'])) ?></div>
        <div class="testimonial-card__role"><?= htmlspecialchars($review['product_name']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ── INSPIRATION ───────────────────────────────────────────── -->
<?php
$inspoDefaults = [
    1 => ['image' => '/public/images/hero/hero-1.jpg', 'title' => 'Adds Incredible Value to Your Day',      'desc' => 'One signature scent changes how people remember you — from morning meetings to late evenings.',          'link_text' => 'READ MORE →', 'url' => '/collections.php'],
    2 => ['image' => '/public/images/hero/hero-2.jpg', 'title' => 'The Next Generation of Fragrance',        'desc' => 'Discover our newest drops — crafted with rare ingredients for those who dare to be different.',       'link_text' => 'READ MORE →', 'url' => '/collections.php?slug=new-drops'],
    3 => ['image' => '/public/images/hero/hero-1.jpg', 'title' => 'The Perfect Scent for Every Life',        'desc' => 'Our bestsellers are beloved by thousands — find your perfect match from Egypt\'s finest collection.', 'link_text' => 'READ MORE →', 'url' => '/collections.php?slug=bestsellers'],
];
$inspoPosts = [];
for ($ip = 1; $ip <= 3; $ip++) {
    $d    = $inspoDefaults[$ip];
    $mode = $siteSettings["inspo_{$ip}_mode"] ?? 'url';
    $url  = $mode === 'page'
          ? "/inspo.php?id={$ip}"
          : ($siteSettings["inspo_{$ip}_link_url"] ?? $d['url']);
    $inspoPosts[] = [
        'image'     => htmlspecialchars($siteSettings["inspo_{$ip}_image"]     ?? $d['image']),
        'title'     => htmlspecialchars($siteSettings["inspo_{$ip}_title"]     ?? $d['title']),
        'desc'      => htmlspecialchars($siteSettings["inspo_{$ip}_desc"]      ?? $d['desc']),
        'link_text' => htmlspecialchars($siteSettings["inspo_{$ip}_link_text"] ?? $d['link_text']),
        'url'       => htmlspecialchars($url),
    ];
}
?>
<section class="section section--inspiration">
  <div class="container">
    <h2 class="inspiration-heading">INSPIRATION</h2>
    <div class="inspiration-grid">
      <?php foreach ($inspoPosts as $ip): ?>
      <article class="inspiration-card">
        <a href="<?= $ip['url'] ?>" class="inspiration-card__img-wrap">
          <img src="<?= $ip['image'] ?>" alt="<?= $ip['title'] ?>" loading="lazy" onerror="this.parentElement.style.background='#f0ece6'">
        </a>
        <h3 class="inspiration-card__title"><?= $ip['title'] ?></h3>
        <p class="inspiration-card__desc"><?= $ip['desc'] ?></p>
        <a href="<?= $ip['url'] ?>" class="inspiration-card__link"><?= $ip['link_text'] ?></a>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── WHATSAPP FLOAT ────────────────────────────────────────── -->
<a href="https://wa.me/201157879622?text=Hello%20DUHN%20FRAGRANCES!%20I%20need%20help."
   target="_blank"
   rel="noopener"
   style="position:fixed;bottom:24px;left:24px;z-index:999;width:54px;height:54px;background:#25D366;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 16px rgba(37,211,102,0.4);transition:transform 0.2s"
   onmouseover="this.style.transform='scale(1.1)'"
   onmouseout="this.style.transform='scale(1)'"
   title="Chat on WhatsApp">
  <i class="ph ph-whatsapp-logo" style="color:#fff;font-size:28px"></i>
</a>

<?php require_once __DIR__ . '/public/layout/footer.php'; ?>
