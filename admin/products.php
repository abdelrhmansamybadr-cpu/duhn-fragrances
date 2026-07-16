<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/config/database.php';

$db = Database::getInstance();

// Inline delete handler (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $delId = (int)($_POST['id'] ?? 0);
    if ($delId > 0) {
        $imgs = $db->prepare("SELECT image_url FROM product_images WHERE product_id = :id");
        $imgs->execute([':id' => $delId]);
        try {
            $db->beginTransaction();
            $db->prepare("DELETE FROM product_collections WHERE product_id = :id")->execute([':id' => $delId]);
            foreach ($imgs->fetchAll() as $img) {
                $p = __DIR__ . '/../' . ltrim($img['image_url'], '/');
                if (file_exists($p)) @unlink($p);
            }
            $db->prepare("DELETE FROM product_images WHERE product_id = :id")->execute([':id' => $delId]);
            $db->prepare("DELETE FROM cart_items WHERE product_id = :id")->execute([':id' => $delId]);
            $db->prepare("DELETE FROM products WHERE id = :id")->execute([':id' => $delId]);
            $db->commit();
        } catch (Throwable $e) { $db->rollBack(); }
    }
    header('Location: /admin/products.php?deleted=1');
    exit;
}

$search = trim($_GET['q'] ?? '');
$colFilter = trim($_GET['collection'] ?? '');

$params = [];
$where  = [];

if ($search) {
    $where[]  = "(p.name LIKE :q OR p.inspired_by LIKE :q OR p.sku LIKE :q)";
    $params[':q'] = "%$search%";
}
if ($colFilter) {
    $where[]  = "EXISTS (SELECT 1 FROM product_collections pc JOIN collections c ON c.id=pc.collection_id WHERE pc.product_id=p.id AND c.slug=:col)";
    $params[':col'] = $colFilter;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("
    SELECT p.*,
           (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) AS image
    FROM products p
    $whereSql
    ORDER BY p.created_at DESC
");
$stmt->execute($params);
$products = $stmt->fetchAll();

$collections = $db->query("SELECT slug, name FROM collections ORDER BY sort_order")->fetchAll();

$adminTitle = 'Products';
require_once __DIR__ . '/includes/header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:22px">
  <!-- Search/Filter -->
  <form method="GET" style="display:flex;gap:10px;align-items:center">
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search products..." class="admin-input" style="width:240px">
    <select name="collection" class="admin-input" style="width:180px">
      <option value="">All Collections</option>
      <?php foreach ($collections as $col): ?>
      <option value="<?= htmlspecialchars($col['slug']) ?>" <?= $colFilter === $col['slug'] ? 'selected' : '' ?>><?= htmlspecialchars($col['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn-admin-outline">Filter</button>
    <?php if ($search || $colFilter): ?><a href="/admin/products.php" class="btn-admin-outline">Clear</a><?php endif; ?>
  </form>
  <a href="/admin/products/add.php" class="btn-admin-gold"><i class="ph ph-plus"></i> Add Product</a>
</div>

<div class="admin-card" style="padding:0;overflow:hidden">
  <table class="admin-table">
    <thead>
      <tr>
        <th width="60">Image</th>
        <th>Name</th>
        <th>Inspired By</th>
        <th>Price</th>
        <th>Stock</th>
        <th>Rating</th>
        <th>Badges</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($products)): ?>
      <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:48px">No products found. <a href="/admin/products/add.php" style="color:var(--accent)">Add one</a>.</td></tr>
      <?php endif; ?>
      <?php foreach ($products as $p): ?>
      <tr>
        <td>
          <img src="<?= !empty($p['image']) ? htmlspecialchars($p['image']) : '/public/images/placeholder.jpg' ?>"
               alt="<?= htmlspecialchars($p['name']) ?>" class="product-thumb"
               onerror="this.src='/public/images/placeholder.jpg'">
        </td>
        <td>
          <div style="font-weight:700"><?= htmlspecialchars($p['name']) ?></div>
          <div style="font-size:11px;color:var(--text-muted)">SKU: <?= htmlspecialchars($p['sku'] ?? '—') ?></div>
        </td>
        <td style="color:var(--text-muted);font-size:12px;font-style:italic"><?= htmlspecialchars($p['inspired_by'] ?? '—') ?></td>
        <td style="font-weight:700;color:var(--accent)"><?= number_format((float)$p['price'], 0) ?> EGP</td>
        <td>
          <span style="color:<?= (int)$p['stock_qty'] > 10 ? 'var(--success)' : ((int)$p['stock_qty'] > 0 ? 'var(--warning)' : 'var(--danger)') ?>">
            <?= (int)$p['stock_qty'] ?>
          </span>
        </td>
        <td>
          <?php if ((int)$p['review_count'] > 0): ?>
          <span style="color:var(--accent)">★ <?= number_format((float)$p['avg_rating'],1) ?></span>
          <span style="color:var(--text-muted);font-size:11px"> (<?= $p['review_count'] ?>)</span>
          <?php else: ?>
          <span style="color:var(--text-muted)">—</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($p['is_featured']): ?><span class="admin-badge badge-gold" style="margin-right:4px">Featured</span><?php endif; ?>
          <?php if ($p['is_new_drop']): ?><span class="admin-badge badge-success" style="margin-right:4px">NEW</span><?php endif; ?>
          <?php if (!empty($p['is_top_rated'])): ?><span class="admin-badge" style="background:rgba(99,102,241,0.18);color:#a5b4fc;margin-right:4px">⭐ Top Rated</span><?php endif; ?>
        </td>
        <td>
          <div style="display:flex;gap:6px">
            <a href="/product.php?slug=<?= urlencode($p['slug']) ?>" target="_blank" class="btn-admin-outline" title="Preview">👁</a>
            <a href="/admin/products/edit.php?id=<?= $p['id'] ?>" class="btn-admin-outline">Edit</a>
            <button type="button" class="btn-admin-danger del-trigger"
                    data-id="<?= $p['id'] ?>"
                    data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                    title="Delete">
              <i class="ph ph-trash"></i>
            </button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Delete confirmation modal -->
<div id="del-modal-overlay" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,0.65);backdrop-filter:blur(3px);align-items:center;justify-content:center">
  <div style="background:#1A1A1A;border:1px solid var(--danger);border-radius:12px;padding:28px 32px;width:340px;max-width:90vw;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.7)">
    <div style="width:52px;height:52px;background:rgba(220,53,69,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
      <i class="ph ph-trash" style="font-size:24px;color:var(--danger)"></i>
    </div>
    <h3 style="color:#fff;font-size:16px;font-weight:700;margin:0 0 8px">Delete Product?</h3>
    <p id="del-modal-name" style="color:var(--accent);font-size:14px;font-weight:600;margin:0 0 8px"></p>
    <p style="color:var(--text-muted);font-size:13px;margin:0 0 24px">This will permanently remove the product and all its images. This cannot be undone.</p>
    <form method="POST" id="del-modal-form">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" id="del-modal-id" value="">
      <div style="display:flex;gap:10px">
        <button type="button" id="del-modal-cancel" style="flex:1;padding:10px;background:rgba(255,255,255,0.06);color:#aaa;border:1px solid #333;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer">Cancel</button>
        <button type="submit" style="flex:1;padding:10px;background:var(--danger);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer">Yes, Delete</button>
      </div>
    </form>
  </div>
</div>

<script>
const delOverlay = document.getElementById('del-modal-overlay');
const delName    = document.getElementById('del-modal-name');
const delId      = document.getElementById('del-modal-id');
const delCancel  = document.getElementById('del-modal-cancel');

function openDelModal(id, name) {
  delId.value = id;
  delName.textContent = '"' + name + '"';
  delOverlay.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function closeDelModal() {
  delOverlay.style.display = 'none';
  document.body.style.overflow = '';
}

document.addEventListener('click', function(e) {
  const trigger = e.target.closest('.del-trigger');
  if (trigger) { openDelModal(trigger.dataset.id, trigger.dataset.name); return; }
});
delCancel.addEventListener('click', closeDelModal);
delOverlay.addEventListener('click', function(e) {
  if (e.target === delOverlay) closeDelModal();
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeDelModal();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
