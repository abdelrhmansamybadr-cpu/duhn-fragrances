<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/config/database.php';

$db     = Database::getInstance();
$filter = $_GET['filter'] ?? 'pending';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

$where = '';
if ($filter === 'approved') {
    $where = 'WHERE r.is_approved = 1';
} elseif ($filter === 'pending') {
    $where = 'WHERE r.is_approved = 0';
}

$total = $db->query("SELECT COUNT(*) FROM reviews r $where")->fetchColumn();
$pages = max(1, (int)ceil($total / $limit));

$reviews = $db->prepare("
    SELECT r.*, p.name AS product_name, p.slug AS product_slug
    FROM reviews r
    LEFT JOIN products p ON p.id = r.product_id
    $where
    ORDER BY r.created_at DESC
    LIMIT $limit OFFSET $offset
");
$reviews->execute();
$reviews = $reviews->fetchAll();

$counts = [
    'all'      => $db->query("SELECT COUNT(*) FROM reviews")->fetchColumn(),
    'pending'  => $db->query("SELECT COUNT(*) FROM reviews WHERE is_approved=0")->fetchColumn(),
    'approved' => $db->query("SELECT COUNT(*) FROM reviews WHERE is_approved=1")->fetchColumn(),
];

$adminTitle = 'Reviews';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Filter Tabs -->
<div style="display:flex;gap:6px;margin-bottom:20px">
  <?php foreach (['all' => 'All', 'pending' => 'Pending Approval', 'approved' => 'Approved'] as $val => $label):
    $active = $filter === $val;
  ?>
  <a href="/admin/reviews.php?filter=<?= $val ?>"
     style="padding:7px 16px;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;
            background:<?= $active ? 'var(--accent)' : 'var(--admin-card)' ?>;
            color:<?= $active ? '#000' : 'var(--text-muted)' ?>;
            border:1px solid <?= $active ? 'var(--accent)' : 'var(--admin-border)' ?>">
    <?= $label ?> <span style="opacity:.6">(<?= $counts[$val] ?>)</span>
  </a>
  <?php endforeach; ?>
</div>

<div class="admin-card" style="padding:0;overflow:hidden">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Product</th>
        <th>Reviewer</th>
        <th style="text-align:center">Rating</th>
        <th>Review</th>
        <th>Date</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($reviews)): ?>
      <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:48px">No reviews found.</td></tr>
      <?php endif; ?>
      <?php foreach ($reviews as $r): ?>
      <tr>
        <td>
          <a href="/product.php?slug=<?= urlencode($r['product_slug']) ?>" target="_blank"
             style="color:var(--accent);font-weight:600;font-size:13px"><?= htmlspecialchars($r['product_name']) ?></a>
        </td>
        <td>
          <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($r['reviewer_name']) ?></div>
          <?php if ($r['reviewer_email']): ?>
          <div style="font-size:11px;color:var(--text-muted)"><?= htmlspecialchars($r['reviewer_email']) ?></div>
          <?php endif; ?>
        </td>
        <td style="text-align:center">
          <span style="color:var(--accent);font-weight:700"><?= (int)$r['rating'] ?>★</span>
        </td>
        <td style="max-width:300px">
          <p style="font-size:13px;color:#ccc;margin:0;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical">
            <?= htmlspecialchars($r['body']) ?>
          </p>
        </td>
        <td style="color:var(--text-muted);font-size:12px;white-space:nowrap"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
        <td>
          <?php if ($r['is_approved']): ?>
          <span class="admin-badge badge-success">Approved</span>
          <?php else: ?>
          <span class="admin-badge badge-gold">Pending</span>
          <?php endif; ?>
        </td>
        <td>
          <div style="display:flex;gap:6px">
            <?php if (!$r['is_approved']): ?>
            <a href="/admin/actions/review_action.php?action=approve&id=<?= $r['id'] ?>&back=<?= urlencode($_SERVER['REQUEST_URI']) ?>"
               class="btn-admin-outline" style="color:var(--success);border-color:var(--success)" title="Approve">✓ Approve</a>
            <?php else: ?>
            <a href="/admin/actions/review_action.php?action=unapprove&id=<?= $r['id'] ?>&back=<?= urlencode($_SERVER['REQUEST_URI']) ?>"
               class="btn-admin-outline" title="Unapprove">Unapprove</a>
            <?php endif; ?>
            <button onclick="confirmDelete('/admin/actions/review_action.php?action=delete&id=<?= $r['id'] ?>', 'this review')" class="btn-admin-danger">Delete</button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Pagination -->
<?php if ($pages > 1): ?>
<div style="display:flex;justify-content:center;gap:6px;margin-top:20px">
  <?php for ($p = 1; $p <= $pages; $p++): ?>
  <a href="/admin/reviews.php?filter=<?= $filter ?>&page=<?= $p ?>"
     style="padding:6px 12px;border-radius:6px;font-size:13px;text-decoration:none;font-weight:600;
            background:<?= $p === $page ? 'var(--accent)' : 'var(--admin-card)' ?>;
            color:<?= $p === $page ? '#000' : 'var(--text-muted)' ?>;
            border:1px solid <?= $p === $page ? 'var(--accent)' : 'var(--admin-border)' ?>">
    <?= $p ?>
  </a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
