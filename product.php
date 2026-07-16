<?php
/**
 * DUHN FRAGRANCES — Product Detail Page
 */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/api/config/config.php';
require_once __DIR__ . '/api/config/database.php';

$isAdmin = !empty($_SESSION['admin_id']) && ($_SESSION['admin_role'] ?? '') === 'admin';

$slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($_GET['slug'] ?? ''));
if (!$slug) { header('Location: /collections.php'); exit; }

$db = Database::getInstance();

// Fetch product
$stmt = $db->prepare("SELECT * FROM products WHERE slug = :slug LIMIT 1");
$stmt->execute([':slug' => $slug]);
$product = $stmt->fetch();
if (!$product) { header('HTTP/1.0 404 Not Found'); include __DIR__ . '/404.php'; exit; }

// ── Auto-migrate missing columns (idempotent — safe to run every request) ────
try { $db->exec("ALTER TABLE products ADD COLUMN views INT UNSIGNED NOT NULL DEFAULT 0"); } catch (Throwable $_) {}
try { $db->exec("ALTER TABLE products ADD COLUMN compare_at_price DECIMAL(10,2) DEFAULT NULL AFTER price"); } catch (Throwable $_) {}
try { $db->exec("ALTER TABLE products ADD COLUMN short_description TEXT DEFAULT NULL AFTER description"); } catch (Throwable $_) {}
try { $db->exec("ALTER TABLE products ADD COLUMN content_blocks LONGTEXT DEFAULT NULL AFTER short_description"); } catch (Throwable $_) {}

// Re-fetch product after potential column additions so $product has all fields
$stmt = $db->prepare("SELECT * FROM products WHERE slug = :slug LIMIT 1");
$stmt->execute([':slug' => $slug]);
$product = $stmt->fetch();

// Increment once per browser session per product (prevents refresh inflation)
$_viewKey = 'viewed_' . $product['id'];
if (empty($_SESSION[$_viewKey])) {
    $_SESSION[$_viewKey] = true;
    try {
        $db->prepare("UPDATE products SET views = views + 1 WHERE id = :id")
           ->execute([':id' => $product['id']]);
        $product['views'] = ($product['views'] ?? 0) + 1;
    } catch (Throwable $_e) {}
}
$productViews = max(1, (int)($product['views'] ?? 1));

// Social proof: simulated live viewers count
$spEnabled = true;
$spMin     = 3;
$spMax     = 18;
try {
    $_spRows = $db->query("SELECT `key`,`value` FROM `settings` WHERE `key` IN ('social_proof_enabled','social_proof_min','social_proof_max')")->fetchAll();
    foreach ($_spRows as $_r) {
        if ($_r['key'] === 'social_proof_enabled') $spEnabled = $_r['value'] === '1';
        if ($_r['key'] === 'social_proof_min')     $spMin     = max(1, (int)$_r['value']);
        if ($_r['key'] === 'social_proof_max')     $spMax     = max($spMin + 1, (int)$_r['value']);
    }
} catch (Throwable $_e) {}
// Seed by product ID + 2-hour window → consistent within same window, changes every 2h
mt_srand($product['id'] * 137 + intval(date('H') / 2) * 31 + intval(date('j')));
$liveViewers = mt_rand($spMin, $spMax);

// Fetch images
$imgStmt = $db->prepare("SELECT image_url FROM product_images WHERE product_id = :id ORDER BY sort_order");
$imgStmt->execute([':id' => $product['id']]);
$images = array_column($imgStmt->fetchAll(), 'image_url');

// Fetch reviews
$revStmt = $db->prepare("SELECT * FROM reviews WHERE product_id = :id AND is_approved = 1 ORDER BY created_at DESC LIMIT 20");
$revStmt->execute([':id' => $product['id']]);
$reviews = $revStmt->fetchAll();

// Fetch related products (same collections)
$relStmt = $db->prepare("
    SELECT DISTINCT p.id, p.name, p.slug, p.price, p.compare_at_price,
           (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) AS image
    FROM products p
    JOIN product_collections pc1 ON pc1.product_id = p.id
    WHERE pc1.collection_id IN (SELECT collection_id FROM product_collections WHERE product_id = :id)
      AND p.id != :id2
    ORDER BY p.avg_rating DESC LIMIT 8
");
try {
    $relStmt->execute([':id' => $product['id'], ':id2' => $product['id']]);
    $related = $relStmt->fetchAll();
} catch (Throwable $e) { $related = []; }

// Global settings (delivery, return, shipping policy)
$settingsRaw = $db->query("SELECT `key`,`value` FROM settings WHERE `key` IN ('delivery_info','return_info','shipping_policy')")->fetchAll();
$gs = [];
foreach ($settingsRaw as $row) $gs[$row['key']] = $row['value'];
$deliveryInfo   = $gs['delivery_info']   ?? 'Estimate delivery times: <strong>2–5 business days</strong> across Egypt.';
$returnInfo     = $gs['return_info']     ?? 'Return within <strong>7 days</strong> of purchase. Unused items in original packaging.';
$shippingPolicy = $gs['shipping_policy'] ?? "Free shipping on orders over 499 EGP.\nDelivery takes 2–5 business days.\nReturns accepted within 7 days of receipt.";

// Decode any HTML entities saved by old admin code (e.g. &#039; → ')
foreach (['name', 'inspired_by', 'description', 'short_description', 'sku'] as $_f) {
    if (!empty($product[$_f])) {
        $product[$_f] = html_entity_decode($product[$_f], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

// Derived values
$price        = (float)$product['price'];
$comparePrice = isset($product['compare_at_price']) ? (float)$product['compare_at_price'] : 0;
$hasDiscount  = $comparePrice > $price && $comparePrice > 0;
$savePct      = $hasDiscount ? round((($comparePrice - $price) / $comparePrice) * 100) : 0;

$shortDesc = '';
if (!empty($product['short_description'])) {
    $shortDesc = htmlspecialchars($product['short_description']);
} elseif (!empty($product['description'])) {
    $raw = strip_tags($product['description']);
    $shortDesc = mb_strlen($raw) > 160 ? mb_substr($raw, 0, 157) . '...' : htmlspecialchars($raw);
}

$contentBlocks = json_decode($product['content_blocks'] ?? '[]', true) ?: [];
// Decode HTML entities in content block text/headings saved by old admin code
foreach ($contentBlocks as &$_cb) {
    if (!empty($_cb['heading'])) $_cb['heading'] = html_entity_decode($_cb['heading'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (!empty($_cb['text']))    $_cb['text']    = html_entity_decode($_cb['text'],    ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
unset($_cb);
$topNotes   = json_decode($product['top_notes']   ?? '[]', true) ?: [];
$heartNotes = json_decode($product['heart_notes'] ?? '[]', true) ?: [];
$baseNotes  = json_decode($product['base_notes']  ?? '[]', true) ?: [];

$avgRating    = (float)($product['avg_rating'] ?? 0);
$reviewCount  = (int)($product['review_count'] ?? 0);
$mainImage    = !empty($images[0]) ? $images[0] : '/public/images/placeholder.jpg';

$pageTitle = htmlspecialchars($product['name']) . ' — DUHN FRAGRANCES';
$pageDesc  = $shortDesc ?: htmlspecialchars(substr(strip_tags($product['description'] ?? ''), 0, 160));
require_once __DIR__ . '/public/layout/header.php';
?>

<!-- ── BREADCRUMB ──────────────────────────────────────────────── -->
<div class="container">
  <nav class="pd-breadcrumb">
    <a href="/index.php">Home</a>
    <span>›</span>
    <a href="/collections.php">Collections</a>
    <span>›</span>
    <span><?= htmlspecialchars($product['name']) ?></span>
  </nav>
</div>

<!-- ── MAIN PRODUCT AREA ────────────────────────────────────────── -->
<div class="container pd-wrap">

  <!-- Gallery -->
  <div class="pd-gallery">
    <div class="pd-gallery__main">
      <img id="gallery-main-img"
           src="<?= htmlspecialchars($mainImage) ?>"
           alt="<?= htmlspecialchars($product['name']) ?>"
           onclick="openLightbox(this.src)"
           style="cursor:zoom-in">
      <?php if ($hasDiscount): ?>
      <div class="pd-gallery__sale-badge">SALE</div>
      <?php endif; ?>
    </div>
    <?php if (count($images) > 1): ?>
    <div class="pd-gallery__thumbs">
      <?php foreach ($images as $i => $img): ?>
      <div class="pd-thumb <?= $i === 0 ? 'active' : '' ?>"
           onclick="switchImage('<?= htmlspecialchars($img) ?>', this)">
        <img src="<?= htmlspecialchars($img) ?>" alt="">
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Info Panel -->
  <div class="pd-info">

    <!-- Title -->
    <h1 class="pd-title"><?= htmlspecialchars($product['name']) ?></h1>

    <?php if ($product['inspired_by']): ?>
    <p class="pd-inspired">Inspired by <em><?= htmlspecialchars($product['inspired_by']) ?></em></p>
    <?php endif; ?>

    <!-- Rating row -->
    <div class="pd-rating-row">
      <div class="pd-stars">
        <?php for ($i = 1; $i <= 5; $i++): ?>
        <span class="<?= $i <= floor($avgRating) ? 'star-on' : ($i - $avgRating < 1 && $avgRating > 0 ? 'star-half' : 'star-off') ?>">★</span>
        <?php endfor; ?>
      </div>
      <?php if ($reviewCount > 0): ?>
      <a href="#reviews" class="pd-review-link"><?= $reviewCount ?> review<?= $reviewCount > 1 ? 's' : '' ?></a>
      <?php else: ?>
      <span class="pd-review-link">No reviews</span>
      <?php endif; ?>
    </div>

    <!-- Price -->
    <div class="pd-price-row">
      <span class="pd-price-current"><?= number_format($price, 0) ?> EGP</span>
      <?php if ($hasDiscount): ?>
      <span class="pd-price-original"><?= number_format($comparePrice, 0) ?> EGP</span>
      <span class="pd-save-badge">SAVE <?= $savePct ?>%</span>
      <?php endif; ?>
    </div>

    <hr class="pd-divider">

    <!-- Short description -->
    <?php if ($shortDesc): ?>
    <p class="pd-short-desc"><?= $shortDesc ?></p>
    <?php endif; ?>

    <!-- Size badge -->
    <?php if (!empty($product['size_ml'])): ?>
    <div style="margin-bottom:0">
      <span class="product-size-badge">
        <i class="ph ph-drop" style="color:var(--accent)"></i>
        <?= (int)$product['size_ml'] ?> ML
      </span>
    </div>
    <?php endif; ?>

    <!-- View Counter / Social Proof -->
    <?php if ($spEnabled): ?>
    <div class="pd-view-count">
      <span class="pd-view-count__dot"></span>
      <span class="pd-view-count__fire">🔥</span>
      <span class="pd-view-count__text">
        <strong><?= $liveViewers ?></strong> people are viewing this
        <em>· trending now</em>
      </span>
    </div>
    <?php endif; ?>

    <!-- Offer Banner -->
    <div class="offer-banner">
      <span class="offer-banner__icon">🎁</span>
      <span class="offer-banner__text">
        <strong>BUY 2 GET 2 FREE</strong> — Add 4 items to your cart to activate the offer!
      </span>
    </div>

    <!-- Qty + Add to Cart -->
    <div class="pd-atc-row" id="atc-anchor">
      <div class="pd-qty">
        <button type="button" onclick="changeQty(-1)">−</button>
        <input type="number" id="qty-input" value="1" min="1" max="99" readonly>
        <button type="button" onclick="changeQty(1)">+</button>
      </div>
      <button id="main-add-btn" class="pd-btn-atc" onclick="addToCartFromDetail(this)"
              <?= $product['stock_qty'] == 0 ? 'disabled' : '' ?>>
        <i class="ph ph-shopping-bag"></i>
        <?= $product['stock_qty'] == 0 ? 'OUT OF STOCK' : 'ADD TO CART' ?>
      </button>
    </div>

    <!-- Buy It Now (WhatsApp) -->
    <a href="https://wa.me/201157879622?text=<?= urlencode('I want to order: ' . $product['name']) ?>"
       target="_blank" rel="noopener" class="pd-btn-buy-now">
      <i class="ph ph-whatsapp-logo"></i>
      ORDER VIA WHATSAPP
    </a>

    <!-- Delivery & Return Info -->
    <div class="pd-delivery-info">
      <div class="pd-delivery-row">
        <i class="ph ph-truck"></i>
        <span><?= $deliveryInfo ?></span>
      </div>
      <div class="pd-delivery-row">
        <i class="ph ph-arrow-counter-clockwise"></i>
        <span><?= $returnInfo ?></span>
      </div>
    </div>

    <!-- Fragrance Notes -->
    <?php if (!empty($topNotes) || !empty($heartNotes) || !empty($baseNotes) || $isAdmin): ?>
    <div class="notes-section" style="margin-top:20px" id="notes-section">

      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
        <div class="notes-title" style="margin-bottom:0">Fragrance Notes</div>
        <?php if ($isAdmin): ?>
        <button type="button" id="notes-edit-btn" onclick="notesEnterEdit()"
                style="display:inline-flex;align-items:center;gap:5px;padding:5px 12px;background:rgba(200,160,48,0.12);border:1px solid rgba(200,160,48,0.4);border-radius:6px;font-size:11px;font-weight:700;color:#b8860b;cursor:pointer;letter-spacing:0.05em">
          <i class="ph ph-pencil-simple"></i> EDIT NOTES
        </button>
        <div id="notes-save-row" style="display:none;gap:8px">
          <button type="button" onclick="notesSave()"
                  style="display:inline-flex;align-items:center;gap:5px;padding:5px 14px;background:#1a1a1a;border:1px solid #1a1a1a;border-radius:6px;font-size:11px;font-weight:700;color:#fff;cursor:pointer">
            <i class="ph ph-floppy-disk"></i> SAVE
          </button>
          <button type="button" onclick="notesExitEdit()"
                  style="padding:5px 10px;background:transparent;border:1px solid #ddd;border-radius:6px;font-size:11px;color:#888;cursor:pointer">
            CANCEL
          </button>
        </div>
        <?php endif; ?>
      </div>

      <!-- VIEW MODE -->
      <div id="notes-view" class="notes-row">
        <div class="notes-group">
          <label>🔝 Top Notes</label>
          <div class="notes-tags" id="view-top">
            <?php foreach ($topNotes as $note): ?>
            <span class="note-tag"><?= htmlspecialchars($note) ?></span>
            <?php endforeach; ?>
            <?php if (empty($topNotes) && $isAdmin): ?><span style="font-size:12px;color:#bbb;font-style:italic">none — click Edit to add</span><?php endif; ?>
          </div>
        </div>
        <div class="notes-group">
          <label>💛 Heart Notes</label>
          <div class="notes-tags" id="view-heart">
            <?php foreach ($heartNotes as $note): ?>
            <span class="note-tag"><?= htmlspecialchars($note) ?></span>
            <?php endforeach; ?>
            <?php if (empty($heartNotes) && $isAdmin): ?><span style="font-size:12px;color:#bbb;font-style:italic">none</span><?php endif; ?>
          </div>
        </div>
        <div class="notes-group">
          <label>🌑 Base Notes</label>
          <div class="notes-tags" id="view-base">
            <?php foreach ($baseNotes as $note): ?>
            <span class="note-tag"><?= htmlspecialchars($note) ?></span>
            <?php endforeach; ?>
            <?php if (empty($baseNotes) && $isAdmin): ?><span style="font-size:12px;color:#bbb;font-style:italic">none</span><?php endif; ?>
          </div>
        </div>
      </div>

      <?php if ($isAdmin): ?>
      <!-- EDIT MODE (hidden by default) -->
      <div id="notes-edit" style="display:none">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
          <div>
            <label style="font-size:11px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:0.08em;display:block;margin-bottom:6px">🔝 Top Notes</label>
            <input type="text" id="edit-top"
                   value="<?= htmlspecialchars(implode(', ', $topNotes)) ?>"
                   placeholder="Bergamot, Lemon, Grapefruit"
                   style="width:100%;padding:9px 12px;border:1.5px solid #d0b060;border-radius:8px;font-size:13px;outline:none;font-family:inherit">
            <p style="font-size:10px;color:#aaa;margin-top:4px">Separate with commas</p>
          </div>
          <div>
            <label style="font-size:11px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:0.08em;display:block;margin-bottom:6px">💛 Heart Notes</label>
            <input type="text" id="edit-heart"
                   value="<?= htmlspecialchars(implode(', ', $heartNotes)) ?>"
                   placeholder="Rose, Jasmine, Iris"
                   style="width:100%;padding:9px 12px;border:1.5px solid #d0b060;border-radius:8px;font-size:13px;outline:none;font-family:inherit">
            <p style="font-size:10px;color:#aaa;margin-top:4px">Separate with commas</p>
          </div>
          <div>
            <label style="font-size:11px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:0.08em;display:block;margin-bottom:6px">🌑 Base Notes</label>
            <input type="text" id="edit-base"
                   value="<?= htmlspecialchars(implode(', ', $baseNotes)) ?>"
                   placeholder="Musk, Amber, Sandalwood"
                   style="width:100%;padding:9px 12px;border:1.5px solid #d0b060;border-radius:8px;font-size:13px;outline:none;font-family:inherit">
            <p style="font-size:10px;color:#aaa;margin-top:4px">Separate with commas</p>
          </div>
        </div>
        <div id="notes-msg" style="margin-top:10px;font-size:12px;display:none"></div>
      </div>

      <script>
      const NOTES_PRODUCT_ID = <?= (int)$product['id'] ?>;

      function notesEnterEdit() {
        document.getElementById('notes-view').style.display = 'none';
        document.getElementById('notes-edit').style.display = 'block';
        document.getElementById('notes-edit-btn').style.display = 'none';
        document.getElementById('notes-save-row').style.display = 'flex';
        document.getElementById('edit-top').focus();
      }

      function notesExitEdit() {
        document.getElementById('notes-view').style.display = '';
        document.getElementById('notes-edit').style.display = 'none';
        document.getElementById('notes-edit-btn').style.display = '';
        document.getElementById('notes-save-row').style.display = 'none';
        const msg = document.getElementById('notes-msg');
        msg.style.display = 'none';
      }

      function buildTags(csv, containerId) {
        const tags = csv.split(',').map(s => s.trim()).filter(Boolean);
        const el   = document.getElementById(containerId);
        el.innerHTML = tags.length
          ? tags.map(t => `<span class="note-tag">${t.replace(/</g,'&lt;')}</span>`).join('')
          : '<span style="font-size:12px;color:#bbb;font-style:italic">none</span>';
      }

      async function notesSave() {
        const top   = document.getElementById('edit-top').value;
        const heart = document.getElementById('edit-heart').value;
        const base  = document.getElementById('edit-base').value;
        const msg   = document.getElementById('notes-msg');
        const btn   = document.querySelector('#notes-save-row button');
        btn.disabled   = true;
        btn.textContent = '⏳ Saving…';
        try {
          const res  = await fetch('/admin/actions/save_notes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: NOTES_PRODUCT_ID, top_notes: top, heart_notes: heart, base_notes: base })
          });
          const json = await res.json();
          if (json.success) {
            buildTags(top,   'view-top');
            buildTags(heart, 'view-heart');
            buildTags(base,  'view-base');
            msg.style.cssText   = 'display:block;color:#28a745;font-weight:600';
            msg.textContent     = '✓ Saved successfully!';
            setTimeout(notesExitEdit, 900);
          } else {
            msg.style.cssText   = 'display:block;color:#dc3545';
            msg.textContent     = '✕ ' + (json.message || 'Save failed');
          }
        } catch(e) {
          msg.style.cssText   = 'display:block;color:#dc3545';
          msg.textContent     = '✕ Network error — try again';
        }
        btn.disabled    = false;
        btn.innerHTML   = '<i class="ph ph-floppy-disk"></i> SAVE';
      }
      </script>
      <?php endif; ?>

    </div>
    <?php endif; ?>

  </div><!-- /.pd-info -->
</div><!-- /.pd-wrap -->

<!-- ── DESCRIPTION / SHIPPING TABS ─────────────────────────────── -->
<div class="pd-tabs-outer">
  <div class="pd-tabs">
    <div class="pd-tabs__nav">
      <button class="pd-tab-btn active" data-tab="description">Description</button>
      <button class="pd-tab-btn" data-tab="shipping">Shipping &amp; return</button>
    </div>
  </div>
  <div class="container">
    <div class="pd-tabs__content">

      <!-- Description tab -->
      <div class="pd-tab-panel active" id="tab-description">
        <?php if ($product['description']): ?>
        <div class="pd-description-body">
          <?= nl2br(htmlspecialchars(html_entity_decode($product['description'], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?>
        </div>
        <?php endif; ?>

        <?php foreach ($contentBlocks as $block):
          $bLayout  = $block['layout'] ?? 'left';
          $bHeading = $block['heading'] ?? '';
          $bText    = $block['text']    ?? '';
          $bImage   = $block['image']   ?? '';
          $isHoriz  = in_array($bLayout, ['left','right']);
        ?>
        <div class="cb-render cb-render--<?= $bLayout ?>">
          <?php if ($bImage && ($bLayout === 'left' || $bLayout === 'top')): ?>
          <div class="cb-render__img"><img src="<?= htmlspecialchars($bImage) ?>" alt="<?= htmlspecialchars($bHeading) ?>" loading="lazy"></div>
          <?php endif; ?>
          <div class="cb-render__text">
            <?php if ($bHeading): ?><h3 class="cb-render__heading"><?= htmlspecialchars($bHeading) ?></h3><?php endif; ?>
            <?php if ($bText): ?><div class="cb-render__body"><?= nl2br(htmlspecialchars(html_entity_decode($bText, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?></div><?php endif; ?>
          </div>
          <?php if ($bImage && ($bLayout === 'right' || $bLayout === 'bottom')): ?>
          <div class="cb-render__img"><img src="<?= htmlspecialchars($bImage) ?>" alt="<?= htmlspecialchars($bHeading) ?>" loading="lazy"></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Shipping tab -->
      <div class="pd-tab-panel" id="tab-shipping">
        <div class="pd-description-body">
          <?= nl2br(htmlspecialchars($shippingPolicy)) ?>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- ── REVIEWS ──────────────────────────────────────────────────── -->
<?php if (!empty($reviews)): ?>
<div class="container" id="reviews" style="padding-top:0">
  <h2 class="section-title" style="margin-bottom:24px">Customer Reviews</h2>
  <?php
    $counts = array_fill(1, 5, 0);
    foreach ($reviews as $rev) $counts[(int)$rev['rating']]++;
  ?>
  <div class="reviews-summary">
    <div class="reviews-avg">
      <div class="avg-number"><?= number_format($avgRating, 1) ?></div>
      <div class="stars avg-stars">
        <?php for ($i = 1; $i <= 5; $i++): ?>
        <span class="<?= $i > round($avgRating) ? 'empty' : '' ?>">★</span>
        <?php endfor; ?>
      </div>
      <div class="avg-count"><?= $reviewCount ?> reviews</div>
    </div>
    <div class="rating-bars">
      <?php for ($star = 5; $star >= 1; $star--): ?>
      <?php $pct = $reviewCount > 0 ? ($counts[$star] / $reviewCount) * 100 : 0; ?>
      <div class="rating-bar-row">
        <span><?= $star ?>★</span>
        <div class="rating-bar-track"><div class="rating-bar-fill" style="width:<?= round($pct) ?>%"></div></div>
        <span><?= $counts[$star] ?></span>
      </div>
      <?php endfor; ?>
    </div>
  </div>
  <div class="reviews-list">
    <?php foreach ($reviews as $review): ?>
    <div class="review-card">
      <div class="review-header">
        <div>
          <div class="reviewer-name"><?= htmlspecialchars($review['reviewer_name']) ?></div>
          <div class="review-date"><?= date('d M Y', strtotime($review['created_at'])) ?></div>
        </div>
        <div class="stars">
          <?php for ($i = 1; $i <= 5; $i++): ?>
          <span class="<?= $i > $review['rating'] ? 'empty' : '' ?>">★</span>
          <?php endfor; ?>
        </div>
      </div>
      <?php if ($review['body']): ?>
      <p class="review-body"><?= htmlspecialchars($review['body']) ?></p>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ── YOU MIGHT ALSO LIKE ──────────────────────────────────────── -->
<?php if (!empty($related)): ?>
<div class="container pd-related">
  <h2 class="pd-related__title">You Might Also Like</h2>
  <div class="pd-rel-slider-wrap">
    <button class="pd-rel-arrow pd-rel-arrow--prev" onclick="relSlide(-1)" aria-label="Previous">&#8249;</button>
    <div class="pd-rel-slider" id="pd-rel-slider">
      <?php foreach ($related as $rel):
        $relPrice    = (float)$rel['price'];
        $relCompare  = isset($rel['compare_at_price']) ? (float)$rel['compare_at_price'] : 0;
        $relOnSale   = $relCompare > $relPrice && $relCompare > 0;
      ?>
      <div class="pd-rel-card">
        <a href="/product.php?slug=<?= urlencode($rel['slug']) ?>" class="pd-rel-card__img">
          <?php if ($relOnSale): ?><div class="pd-rel-sale-ribbon">Sale</div><?php endif; ?>
          <img src="<?= !empty($rel['image']) ? htmlspecialchars($rel['image']) : '/public/images/placeholder.jpg' ?>"
               alt="<?= htmlspecialchars($rel['name']) ?>" loading="lazy"
               onerror="this.src='/public/images/placeholder.jpg'">
        </a>
        <div class="pd-rel-card__body">
          <a href="/product.php?slug=<?= urlencode($rel['slug']) ?>" class="pd-rel-card__name">
            <?= htmlspecialchars($rel['name']) ?>
          </a>
          <div class="pd-rel-card__prices">
            <span class="pd-rel-price-now"><?= number_format($relPrice, 0) ?> EGP</span>
            <?php if ($relOnSale): ?>
            <span class="pd-rel-price-old"><?= number_format($relCompare, 0) ?> EGP</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <button class="pd-rel-arrow pd-rel-arrow--next" onclick="relSlide(1)" aria-label="Next">&#8250;</button>
  </div>
  <div class="pd-rel-dots" id="pd-rel-dots"></div>
</div>
<?php endif; ?>

<!-- ── STICKY BOTTOM BAR ────────────────────────────────────────── -->
<div class="pd-sticky-bar" id="pd-sticky-bar">
  <div class="pd-sticky-bar__inner container">
    <div class="pd-sticky-bar__product">
      <img src="<?= htmlspecialchars($mainImage) ?>" alt="" class="pd-sticky-bar__img"
           onerror="this.src='/public/images/placeholder.jpg'">
      <div>
        <div class="pd-sticky-bar__name"><?= htmlspecialchars($product['name']) ?></div>
        <div class="pd-sticky-bar__prices">
          <span class="pd-sticky-price-now"><?= number_format($price, 0) ?> EGP</span>
          <?php if ($hasDiscount): ?>
          <span class="pd-sticky-price-old"><?= number_format($comparePrice, 0) ?> EGP</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="pd-sticky-bar__actions">
      <div class="pd-qty pd-qty--sm">
        <button type="button" onclick="changeQty(-1)">−</button>
        <span id="qty-display">1</span>
        <button type="button" onclick="changeQty(1)">+</button>
      </div>
      <button class="pd-btn-atc pd-btn-atc--sm" onclick="addToCartFromDetail(this)"
              <?= $product['stock_qty'] == 0 ? 'disabled' : '' ?>>
        ADD TO CART
      </button>
    </div>
  </div>
</div>

<!-- ── Lightbox ──────────────────────────────────────────────────── -->
<div id="lightbox" onclick="closeLightbox()"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.92);z-index:9999;align-items:center;justify-content:center;cursor:zoom-out">
  <button onclick="closeLightbox()" style="position:absolute;top:20px;right:24px;background:none;border:none;color:#fff;font-size:32px;cursor:pointer;opacity:0.7">✕</button>
  <img id="lightbox-img" src="" alt="" style="max-width:90vw;max-height:88vh;object-fit:contain;border-radius:8px">
</div>

<?php
$productId    = (int)$product['id'];
$relatedCount = count($related);
$extraScripts = '<script>
const PRODUCT_ID = ' . $productId . ';

/* ── qty ── */
function changeQty(delta) {
  const input   = document.getElementById("qty-input");
  const display = document.getElementById("qty-display");
  const newVal  = Math.max(1, Math.min(99, parseInt(input.value) + delta));
  input.value = newVal;
  if (display) display.textContent = newVal;
}

function addToCartFromDetail(btn) {
  const qty = parseInt(document.getElementById("qty-input").value);
  Cart.add(PRODUCT_ID, qty, btn);
}

/* ── gallery ── */
function switchImage(url, thumbEl) {
  document.getElementById("gallery-main-img").src = url;
  document.querySelectorAll(".pd-thumb").forEach(t => t.classList.remove("active"));
  thumbEl.classList.add("active");
}

function openLightbox(src) {
  const lb = document.getElementById("lightbox");
  document.getElementById("lightbox-img").src = src;
  lb.style.display = "flex";
  document.body.style.overflow = "hidden";
}
function closeLightbox() {
  document.getElementById("lightbox").style.display = "none";
  document.body.style.overflow = "";
}

document.addEventListener("keydown", e => { if (e.key === "Escape") closeLightbox(); });

/* ── tabs ── */
document.querySelectorAll(".pd-tab-btn").forEach(btn => {
  btn.addEventListener("click", function() {
    document.querySelectorAll(".pd-tab-btn").forEach(b => b.classList.remove("active"));
    document.querySelectorAll(".pd-tab-panel").forEach(p => p.classList.remove("active"));
    this.classList.add("active");
    document.getElementById("tab-" + this.dataset.tab).classList.add("active");
  });
});

/* ── sticky bar ── */
const stickyBar = document.getElementById("pd-sticky-bar");
const atcAnchor = document.getElementById("atc-anchor");
if (stickyBar && atcAnchor) {
  const obs = new IntersectionObserver(entries => {
    stickyBar.classList.toggle("visible", !entries[0].isIntersecting);
  }, { threshold: 0 });
  obs.observe(atcAnchor);
}

/* ── related slider ── */
const relSlider = document.getElementById("pd-rel-slider");
const relDots   = document.getElementById("pd-rel-dots");
const VISIBLE   = window.innerWidth >= 1024 ? 4 : window.innerWidth >= 640 ? 2 : 1;
const TOTAL     = ' . $relatedCount . ';
let relPage     = 0;
const PAGES     = Math.ceil(TOTAL / VISIBLE);

function buildDots() {
  if (!relDots) return;
  relDots.innerHTML = "";
  for (let i = 0; i < PAGES; i++) {
    const d = document.createElement("button");
    d.className = "pd-rel-dot" + (i === 0 ? " active" : "");
    d.onclick = () => goRelPage(i);
    relDots.appendChild(d);
  }
}

function goRelPage(page) {
  if (!relSlider) return;
  relPage = Math.max(0, Math.min(PAGES - 1, page));
  const pct = relPage * (VISIBLE / TOTAL) * 100;
  relSlider.style.transform = "translateX(-" + pct + "%)";
  document.querySelectorAll(".pd-rel-dot").forEach((d,i) => d.classList.toggle("active", i === relPage));
}

function relSlide(dir) { goRelPage(relPage + dir); }

if (PAGES > 1) buildDots();

/* ── Live view count polling ── */
(function () {
  const el = document.querySelector(".pd-view-count__text strong");
  if (!el) return;
  const productId = PRODUCT_ID;

  function fmt(n) {
    return n.toLocaleString("en-US");
  }

  function poll() {
    fetch(`${window.location.origin}/api/products/views/${productId}`)
      .then(r => r.json())
      .then(json => {
        if (json.success && json.data && json.data.views) {
          const newVal = json.data.views;
          const curVal = parseInt(el.textContent.replace(/,/g, ""), 10);
          if (newVal !== curVal) {
            el.style.transition = "transform 0.25s ease, opacity 0.25s ease";
            el.style.transform  = "translateY(-6px)";
            el.style.opacity    = "0";
            setTimeout(() => {
              el.textContent     = fmt(newVal);
              el.style.transform = "translateY(6px)";
              setTimeout(() => {
                el.style.transform = "translateY(0)";
                el.style.opacity   = "1";
              }, 20);
            }, 250);
          }
        }
      })
      .catch(() => {});
  }

  // Poll every 15 seconds
  setInterval(poll, 15000);
})();
</script>';
require_once __DIR__ . '/public/layout/footer.php';
?>
