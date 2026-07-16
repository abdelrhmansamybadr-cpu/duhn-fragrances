<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/config/database.php';

$db = Database::getInstance();

$stats = [
    'products'   => $db->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'collections'=> $db->query("SELECT COUNT(*) FROM collections")->fetchColumn(),
    'orders'     => $db->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'customers'  => $db->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn(),
    'revenue'    => $db->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status='delivered'")->fetchColumn(),
    'pending'    => $db->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn(),
    'messages'   => $db->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn(),
    'reviews'    => $db->query("SELECT COUNT(*) FROM reviews WHERE is_approved=0")->fetchColumn(),
];

$recentOrders = $db->query("
    SELECT id, order_number, customer_name, customer_phone, total, status, created_at
    FROM orders ORDER BY created_at DESC LIMIT 10
")->fetchAll();

$adminTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Stats Grid -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px">
  <div class="stat-card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start">
      <div>
        <div class="stat-label">Products</div>
        <div class="stat-value"><?= $stats['products'] ?></div>
      </div>
      <i class="ph ph-flask stat-icon"></i>
    </div>
    <a href="/admin/products.php" style="font-size:12px;color:var(--accent);margin-top:10px;display:inline-block">Manage →</a>
  </div>

  <div class="stat-card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start">
      <div>
        <div class="stat-label">Total Orders</div>
        <div class="stat-value"><?= $stats['orders'] ?></div>
      </div>
      <i class="ph ph-package stat-icon"></i>
    </div>
    <span style="font-size:12px;color:var(--warning)"><?= $stats['pending'] ?> pending</span>
  </div>

  <div class="stat-card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start">
      <div>
        <div class="stat-label">Revenue (Delivered)</div>
        <div class="stat-value" style="font-size:22px"><?= number_format((float)$stats['revenue']) ?> <span style="font-size:14px;color:var(--text-muted)">EGP</span></div>
      </div>
      <i class="ph ph-currency-egp stat-icon"></i>
    </div>
  </div>

  <div class="stat-card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start">
      <div>
        <div class="stat-label">Customers</div>
        <div class="stat-value"><?= $stats['customers'] ?></div>
      </div>
      <i class="ph ph-users stat-icon"></i>
    </div>
  </div>
</div>

<!-- Alerts Row -->
<?php if ($stats['messages'] > 0 || $stats['reviews'] > 0): ?>
<div style="display:flex;gap:12px;margin-bottom:24px">
  <?php if ($stats['messages'] > 0): ?>
  <a href="/admin/contact.php" style="flex:1;background:rgba(248,196,23,0.1);border:1px solid rgba(248,196,23,0.3);border-radius:8px;padding:14px 18px;display:flex;align-items:center;gap:10px;color:var(--accent);text-decoration:none;font-size:13px;font-weight:600">
    <i class="ph ph-envelope" style="font-size:18px"></i>
    <?= $stats['messages'] ?> unread message<?= $stats['messages'] > 1 ? 's' : '' ?>
  </a>
  <?php endif; ?>
  <?php if ($stats['reviews'] > 0): ?>
  <a href="/admin/reviews.php" style="flex:1;background:rgba(248,196,23,0.1);border:1px solid rgba(248,196,23,0.3);border-radius:8px;padding:14px 18px;display:flex;align-items:center;gap:10px;color:var(--accent);text-decoration:none;font-size:13px;font-weight:600">
    <i class="ph ph-star" style="font-size:18px"></i>
    <?= $stats['reviews'] ?> review<?= $stats['reviews'] > 1 ? 's' : '' ?> awaiting approval
  </a>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Recent Orders -->
<div class="admin-card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
    <h2 style="font-size:16px;font-weight:700">Recent Orders</h2>
    <a href="/admin/orders.php" class="btn-admin-outline">View All</a>
  </div>

  <?php if (empty($recentOrders)): ?>
  <p style="color:var(--text-muted);text-align:center;padding:32px">No orders yet.</p>
  <?php else: ?>
  <table class="admin-table">
    <thead>
      <tr>
        <th>Order #</th>
        <th>Customer</th>
        <th>Phone</th>
        <th>Total</th>
        <th>Status</th>
        <th>Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($recentOrders as $order):
        $statusColors = [
          'pending'   => 'badge-gold',
          'confirmed' => 'badge-muted',
          'shipped'   => 'badge-muted',
          'delivered' => 'badge-success',
          'cancelled' => 'badge-danger',
        ];
        $color = $statusColors[$order['status']] ?? 'badge-muted';
      ?>
      <tr>
        <td style="font-weight:700;color:var(--accent)"><?= htmlspecialchars($order['order_number']) ?></td>
        <td><?= htmlspecialchars($order['customer_name']) ?></td>
        <td style="color:var(--text-muted)"><?= htmlspecialchars($order['customer_phone']) ?></td>
        <td style="font-weight:700"><?= number_format((float)$order['total'], 0) ?> EGP</td>
        <td><span class="admin-badge <?= $color ?>"><?= ucfirst($order['status']) ?></span></td>
        <td style="color:var(--text-muted)"><?= date('d M Y', strtotime($order['created_at'])) ?></td>
        <td>
          <a href="/admin/orders/view.php?id=<?= $order['id'] ?>" class="btn-admin-outline">View</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
