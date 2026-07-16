<?php
/**
 * DUHN FRAGRANCES — Collections / Shop Page
 */
require_once __DIR__ . '/api/config/config.php';
require_once __DIR__ . '/api/config/database.php';

$db   = Database::getInstance();
$slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($_GET['slug'] ?? ''));

// Auto-migrate is_hidden column
try { $db->exec("ALTER TABLE collections ADD COLUMN is_hidden TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $_) {}

// All visible collections for sidebar
$allCollections = $db->query("
    SELECT c.*, COUNT(pc.product_id) AS product_count
    FROM collections c
    LEFT JOIN product_collections pc ON pc.collection_id = c.id
    WHERE c.is_hidden = 0
    GROUP BY c.id ORDER BY c.sort_order ASC
")->fetchAll();

$activeCollection = null;
$products         = [];

if ($slug) {
    // Fetch specific collection
    $colStmt = $db->prepare("SELECT * FROM collections WHERE slug = :slug AND is_hidden = 0 LIMIT 1");
    $colStmt->execute([':slug' => $slug]);
    $activeCollection = $colStmt->fetch();

    if ($activeCollection) {
        $prodStmt = $db->prepare("
            SELECT p.*, (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) AS image
            FROM products p
            JOIN product_collections pc ON pc.product_id = p.id
            JOIN collections c ON c.id = pc.collection_id AND c.slug = :slug
            ORDER BY p.avg_rating DESC
        ");
        $prodStmt->execute([':slug' => $slug]);
        $products = $prodStmt->fetchAll();
    }
} else {
    // All products
    $products = $db->query("
        SELECT p.*, (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) AS image
        FROM products p
        ORDER BY p.name ASC
    ")->fetchAll();
}

// Decode HTML entities stored by old admin code (e.g. &#039; → ')
foreach ($products as &$_p) {
    foreach (['name', 'inspired_by', 'short_description'] as $_f) {
        if (!empty($_p[$_f])) $_p[$_f] = html_entity_decode($_p[$_f], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
unset($_p);
if (!empty($activeCollection['name'])) {
    $activeCollection['name'] = html_entity_decode($activeCollection['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

$pageTitle = ($activeCollection ? htmlspecialchars($activeCollection['name']) . ' — ' : 'All Collections — ') . 'DUHN FRAGRANCES';
require_once __DIR__ . '/public/layout/header.php';
?>

<div class="container" style="padding-top:40px;padding-bottom:64px">

  <!-- Page Header -->
  <div style="margin-bottom:36px">
    <nav class="breadcrumb" style="margin-bottom:10px">
      <a href="/index.php">Home</a>
      <span>/</span>
      <?php if ($activeCollection): ?>
      <a href="/collections.php">Collections</a>
      <span>/</span>
      <span><?= htmlspecialchars($activeCollection['name']) ?></span>
      <?php else: ?>
      <span>All Collections</span>
      <?php endif; ?>
    </nav>

    <h1 class="section-title" style="font-size:32px">
      <?= $activeCollection ? htmlspecialchars($activeCollection['name']) : 'All Fragrances' ?>
    </h1>
    <?php if ($activeCollection && $activeCollection['description']): ?>
    <p style="color:var(--text-muted);margin-top:8px;font-size:15px"><?= htmlspecialchars($activeCollection['description']) ?></p>
    <?php endif; ?>
    <p style="color:var(--text-muted);font-size:13px;margin-top:6px"><?= count($products) ?> product<?= count($products) !== 1 ? 's' : '' ?></p>
  </div>

  <div class="coll-page-layout">

    <!-- Sidebar / Collections Filter -->
    <aside class="coll-sidebar">
      <h3 class="coll-sidebar__heading">Collections</h3>
      <ul class="coll-sidebar__list">
        <li>
          <a href="/collections.php" class="coll-filter-link <?= !$slug ? 'active' : '' ?>">
            All Fragrances
          </a>
        </li>
        <?php foreach ($allCollections as $col): ?>
        <li>
          <a href="/collections.php?slug=<?= urlencode($col['slug']) ?>"
             class="coll-filter-link <?= $slug === $col['slug'] ? 'active' : '' ?>">
            <?= htmlspecialchars($col['name']) ?>
            <span class="coll-filter-count"><?= $col['product_count'] ?></span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    </aside>

    <!-- Products Grid -->
    <div>
      <?php if (empty($products) && !$slug): ?>
      <!-- Show collection cards instead -->
      <div class="collection-grid">
        <?php foreach ($allCollections as $col): ?>
        <a href="/collections.php?slug=<?= urlencode($col['slug']) ?>" class="collection-card">
          <img src="<?= !empty($col['cover_image_url']) ? htmlspecialchars($col['cover_image_url']) : '/public/images/placeholder-collection.jpg' ?>"
               alt="<?= htmlspecialchars($col['name']) ?>"
               onerror="this.src='/public/images/placeholder-collection.jpg'">
          <div class="collection-card__name"><?= htmlspecialchars($col['name']) ?></div>
        </a>
        <?php endforeach; ?>
      </div>

      <?php elseif (empty($products)): ?>
      <div style="text-align:center;padding:80px 40px;color:var(--text-muted)">
        <i class="ph ph-package" style="font-size:48px;opacity:0.3;display:block;margin-bottom:16px"></i>
        <p>No products in this collection yet.</p>
        <a href="/collections.php" class="btn btn-outline-gold" style="margin-top:20px;width:auto">Browse All</a>
      </div>

      <?php else: ?>
      <div class="product-grid">
        <?php foreach ($products as $product): ?>
        <div class="product-card">
          <a href="/product.php?slug=<?= urlencode($product['slug']) ?>" class="product-card__image">
            <?php if ($product['is_new_drop']): ?>
            <span class="product-card__badge new">NEW</span>
            <?php endif; ?>
            <img
              src="<?= !empty($product['image']) ? htmlspecialchars($product['image']) : '/public/images/placeholder.jpg' ?>"
              alt="<?= htmlspecialchars($product['name']) ?>"
              loading="lazy"
              onerror="this.src='/public/images/placeholder.jpg'">
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
                $r = (float)$product['avg_rating'];
                for ($i = 1; $i <= 5; $i++):
                  if ($i <= floor($r)) echo '<span>★</span>';
                  elseif ($i - $r < 1) echo '<span style="opacity:0.5">★</span>';
                  else echo '<span class="empty">★</span>';
                endfor;
                ?>
              </div>
              <span class="review-count">(<?= $product['review_count'] ?>)</span>
            </div>
            <?php endif; ?>
            <div class="product-card__price">
              <?= number_format((float)$product['price'], 1) ?>
              <span class="currency">EGP</span>
            </div>
            <button class="btn-add-cart" onclick="Cart.add(<?= (int)$product['id'] ?>, 1, this)">
              Add to Cart
            </button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/public/layout/footer.php'; ?>
