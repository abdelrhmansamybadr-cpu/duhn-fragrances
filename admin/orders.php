<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/config/database.php';

$db     = Database::getInstance();
$status = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');

$where  = [];
$params = [];

if ($status && in_array($status, ['pending','confirmed','shipped','delivered','cancelled'])) {
    $where[] = 'status = :status';
    $params[':status'] = $status;
}
if ($search) {
    $where[] = '(order_number LIKE :q OR customer_name LIKE :q OR customer_phone LIKE :q)';
    $params[':q'] = "%$search%";
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$orders = $db->prepare("
    SELECT * FROM orders $whereSql ORDER BY created_at DESC
");
$orders->execute($params);
$orders = $orders->fetchAll();

$statusCounts = $db->query("SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

$adminTitle = 'Orders';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Status Tabs -->
<div style="display:flex;gap:6px;margin-bottom:20px;flex-wrap:wrap">
  <?php
  $tabs = [''=>'All','pending'=>'Pending','confirmed'=>'Confirmed','shipped'=>'Shipped','delivered'=>'Delivered','cancelled'=>'Cancelled'];
  foreach ($tabs as $val => $label):
    $cnt   = $val ? ($statusCounts[$val] ?? 0) : array_sum($statusCounts);
    $active = $status === $val;
  ?>
  <a href="/admin/orders.php?status=<?= $val ?><?= $search ? '&q='.urlencode($search) : '' ?>"
     style="padding:7px 16px;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;
            background:<?= $active ? 'var(--accent)' : 'var(--admin-card)' ?>;
            color:<?= $active ? '#000' : 'var(--text-muted)' ?>;
            border:1px solid <?= $active ? 'var(--accent)' : 'var(--admin-border)' ?>">
    <?= $label ?> <?php if ($cnt): ?><span style="opacity:0.6">(<?= $cnt ?>)</span><?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- Search -->
<form method="GET" style="display:flex;gap:10px;margin-bottom:20px">
  <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
  <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search order #, name, phone..." class="admin-input" style="max-width:360px">
  <button type="submit" class="btn-admin-outline">Search</button>
  <?php if ($search): ?><a href="/admin/orders.php?status=<?= htmlspecialchars($status) ?>" class="btn-admin-outline">Clear</a><?php endif; ?>
</form>

<div class="admin-card" style="padding:0;overflow:hidden">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Order #</th>
        <th>Customer</th>
        <th>Phone</th>
        <th>Governorate</th>
        <th>Items</th>
        <th>Total</th>
        <th>Payment</th>
        <th>Status</th>
        <th>Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($orders)): ?>
      <tr><td colspan="10" style="text-align:center;color:var(--text-muted);padding:48px">No orders found.</td></tr>
      <?php endif; ?>
      <?php foreach ($orders as $o):
        $itemCount = $db->prepare("SELECT SUM(quantity) FROM order_items WHERE order_id = :id");
        $itemCount->execute([':id' => $o['id']]);
        $qty = (int)$itemCount->fetchColumn();

        $statusColors = [
          'pending'   => 'badge-gold',
          'confirmed' => 'badge-muted',
          'shipped'   => 'badge-muted',
          'delivered' => 'badge-success',
          'cancelled' => 'badge-danger',
        ];
        $clr = $statusColors[$o['status']] ?? 'badge-muted';
      ?>
      <tr>
        <td style="font-weight:700;color:var(--accent)"><?= htmlspecialchars($o['order_number']) ?></td>
        <td><?= htmlspecialchars($o['customer_name']) ?></td>
        <td style="color:var(--text-muted)"><?= htmlspecialchars($o['customer_phone']) ?></td>
        <td style="color:var(--text-muted);font-size:12px"><?= htmlspecialchars($o['governorate']) ?></td>
        <td style="text-align:center"><?= $qty ?></td>
        <td style="font-weight:700"><?= number_format((float)$o['total'], 0) ?> EGP</td>
        <td style="font-size:12px;color:var(--text-muted);text-transform:uppercase"><?= $o['payment_method'] ?></td>
        <td><span class="admin-badge <?= $clr ?>"><?= ucfirst($o['status']) ?></span></td>
        <td style="color:var(--text-muted);font-size:12px"><?= date('d M Y', strtotime($o['created_at'])) ?></td>
        <td>
          <a href="/admin/orders/view.php?id=<?= $o['id'] ?>" class="btn-admin-outline">View</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
