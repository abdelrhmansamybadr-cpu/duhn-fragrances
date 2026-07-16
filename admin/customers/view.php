<?php
require_once __DIR__ . '/../../admin/includes/auth_check.php';
require_once __DIR__ . '/../../api/config/config.php';
require_once __DIR__ . '/../../api/config/database.php';

$db    = Database::getInstance();
$email = trim($_GET['email'] ?? '');
$phone = trim($_GET['phone'] ?? '');

if (!$email && !$phone) {
    header('Location: /admin/customers.php');
    exit;
}

// ── Build identifier for lookup ───────────────────────────────────
if ($email) {
    $where  = "WHERE (o.customer_email = :val OR (o.customer_email = '' AND o.customer_phone = :val2))";
    $params = [':val' => $email, ':val2' => $email];
} else {
    $where  = "WHERE o.customer_phone = :val AND (o.customer_email IS NULL OR o.customer_email = '')";
    $params = [':val' => $phone];
}

// ── Fetch all orders for this customer ────────────────────────────
$ordersSql = "
    SELECT o.*,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count
    FROM orders o
    {$where}
    ORDER BY o.created_at DESC
";
$stmt = $db->prepare($ordersSql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

if (empty($orders)) {
    header('Location: /admin/customers.php');
    exit;
}

// ── Customer summary from aggregated orders ───────────────────────
$first      = $orders[count($orders) - 1]; // oldest order
$latest     = $orders[0];                   // newest order
$name       = $latest['customer_name'];
$custEmail  = $latest['customer_email'] ?? '';
$custPhone  = $latest['customer_phone'] ?? '';
$totalSpent = array_sum(array_column($orders, 'total'));
$orderCount = count($orders);

// Check if registered user
$user = null;
if ($custEmail) {
    $uStmt = $db->prepare("SELECT * FROM users WHERE email = :e AND role = 'customer' LIMIT 1");
    $uStmt->execute([':e' => $custEmail]);
    $user = $uStmt->fetch();
}

// Status color map
$statusColors = [
    'pending'   => 'badge-gold',
    'confirmed' => 'badge-muted',
    'shipped'   => 'badge-muted',
    'delivered' => 'badge-success',
    'cancelled' => 'badge-danger',
];

$adminTitle = 'Customer: ' . htmlspecialchars($name);
require_once __DIR__ . '/../../admin/includes/header.php';
?>

<!-- Back -->
<a href="/admin/customers.php" style="color:var(--text-muted);font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:5px;margin-bottom:20px">
  <i class="ph ph-arrow-left"></i> Back to Customers
</a>

<div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start">

  <!-- ── LEFT: Orders History ─────────────────────────────────── -->
  <div>

    <!-- Customer Header Card -->
    <div class="admin-card" style="margin-bottom:16px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
        <div>
          <h1 style="font-size:20px;font-weight:700;color:#fff;margin:0 0 4px"><?= htmlspecialchars($name) ?></h1>
          <?php if ($custEmail): ?>
          <div style="font-size:13px;color:var(--text-muted)"><?= htmlspecialchars($custEmail) ?></div>
          <?php endif; ?>
          <?php if ($custPhone): ?>
          <div style="font-size:13px;color:var(--text-muted)">
            <a href="tel:<?= htmlspecialchars($custPhone) ?>" style="color:var(--accent)"><?= htmlspecialchars($custPhone) ?></a>
          </div>
          <?php endif; ?>
        </div>
        <div style="display:flex;gap:8px;align-items:center">
          <?php if ($user): ?>
          <span class="admin-badge badge-success">Registered Account</span>
          <?php else: ?>
          <span class="admin-badge" style="background:rgba(255,255,255,0.06);color:#888">Guest Customer</span>
          <?php endif; ?>

          <?php if ($custPhone): ?>
          <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $custPhone) ?>?text=<?= urlencode('Hello ' . $name . ', thank you for shopping at DUHN FRAGRANCES!') ?>"
             target="_blank"
             style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#25D366;color:#fff;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600">
            <i class="ph ph-whatsapp-logo"></i> WhatsApp
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Orders Table -->
    <h2 style="font-size:15px;font-weight:700;color:#fff;margin:0 0 12px">
      Order History <span style="color:var(--text-muted);font-weight:400;font-size:13px">(<?= $orderCount ?> orders)</span>
    </h2>

    <?php foreach ($orders as $o):
      $clr = $statusColors[$o['status']] ?? 'badge-muted';
    ?>
    <div class="admin-card" style="margin-bottom:14px;padding:0;overflow:hidden">

      <!-- Order Header -->
      <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 20px;border-bottom:1px solid var(--admin-border);background:rgba(255,255,255,0.03)">
        <div style="display:flex;align-items:center;gap:16px">
          <span style="font-weight:700;color:var(--accent);font-size:15px">#<?= htmlspecialchars($o['order_number']) ?></span>
          <span class="admin-badge <?= $clr ?>"><?= ucfirst($o['status']) ?></span>
          <span style="font-size:12px;color:var(--text-muted)"><?= date('d M Y, H:i', strtotime($o['created_at'])) ?></span>
        </div>
        <a href="/admin/orders/view.php?id=<?= $o['id'] ?>"
           class="btn-admin-outline" style="font-size:12px;padding:5px 12px">
          Manage Order
        </a>
      </div>

      <!-- Order Items -->
      <?php
      $itemsStmt = $db->prepare("
          SELECT oi.*, pi.image_url
          FROM order_items oi
          LEFT JOIN (
              SELECT product_id, MIN(image_url) AS image_url
              FROM product_images GROUP BY product_id
          ) pi ON pi.product_id = oi.product_id
          WHERE oi.order_id = :id
      ");
      $itemsStmt->execute([':id' => $o['id']]);
      $items = $itemsStmt->fetchAll();
      ?>
      <div style="padding:16px 20px">
        <?php foreach ($items as $item): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.04)">
          <img src="<?= !empty($item['image_url']) ? htmlspecialchars($item['image_url']) : '/public/images/placeholder.jpg' ?>"
               alt="" onerror="this.src='/public/images/placeholder.jpg'"
               style="width:40px;height:40px;object-fit:cover;border-radius:6px;border:1px solid var(--admin-border);flex-shrink:0">
          <div style="flex:1;min-width:0">
            <div style="font-weight:600;font-size:14px;color:#fff"><?= htmlspecialchars($item['product_name']) ?></div>
            <div style="font-size:12px;color:var(--text-muted)">Qty: <?= (int)$item['quantity'] ?> &times; <?= number_format((float)$item['product_price'], 0) ?> EGP</div>
          </div>
          <div style="font-weight:700;color:var(--accent);white-space:nowrap">
            <?= number_format((float)$item['line_total'], 0) ?> EGP
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Order Totals & Delivery -->
      <div style="display:flex;justify-content:space-between;align-items:flex-end;padding:14px 20px;background:rgba(0,0,0,0.2);gap:16px;flex-wrap:wrap">

        <!-- Delivery Info -->
        <div style="font-size:12px;color:var(--text-muted);line-height:1.7">
          <div><strong style="color:#aaa">Address:</strong> <?= htmlspecialchars($o['delivery_address'] ?? '—') ?></div>
          <div><strong style="color:#aaa">Governorate:</strong> <?= htmlspecialchars($o['governorate'] ?? '—') ?></div>
          <div><strong style="color:#aaa">Payment:</strong> <span style="text-transform:uppercase"><?= htmlspecialchars($o['payment_method'] ?? '—') ?></span></div>
          <?php if ((float)($o['discount'] ?? 0) > 0): ?>
          <div><strong style="color:#aaa">Promo Discount:</strong> <span style="color:#4CAF50">−<?= number_format((float)$o['discount'], 0) ?> EGP</span></div>
          <?php endif; ?>
          <?php if ($o['notes']): ?>
          <div><strong style="color:#aaa">Notes:</strong> <?= htmlspecialchars($o['notes']) ?></div>
          <?php endif; ?>
        </div>

        <!-- Order Total -->
        <div style="text-align:right;flex-shrink:0">
          <div style="font-size:11px;color:var(--text-muted);margin-bottom:2px;text-transform:uppercase;letter-spacing:.06em">Order Total</div>
          <div style="font-size:22px;font-weight:700;color:var(--accent)"><?= number_format((float)$o['total'], 0) ?> EGP</div>
          <?php if ((float)($o['delivery_fee'] ?? 0) > 0): ?>
          <div style="font-size:11px;color:var(--text-muted)">incl. <?= number_format((float)$o['delivery_fee'], 0) ?> EGP delivery</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- GPS Location if available -->
      <?php if (!empty($o['gps_lat']) && !empty($o['gps_lng'])): ?>
      <div style="padding:10px 20px;background:rgba(248,196,23,0.05);border-top:1px solid rgba(248,196,23,0.15);display:flex;align-items:center;gap:10px">
        <i class="ph ph-map-pin" style="color:var(--accent)"></i>
        <?php if (!empty($o['gps_label'])): ?>
        <span style="font-size:13px;color:#fff;font-weight:600"><?= htmlspecialchars($o['gps_label']) ?></span>
        <?php endif; ?>
        <span style="font-size:12px;color:var(--text-muted)"><?= (float)$o['gps_lat'] ?>, <?= (float)$o['gps_lng'] ?></span>
        <?php if (!empty($o['gps_map_url'])): ?>
        <a href="<?= htmlspecialchars($o['gps_map_url']) ?>" target="_blank" rel="noopener"
           style="margin-left:auto;display:inline-flex;align-items:center;gap:5px;background:var(--accent);color:#1A1A1A;padding:5px 12px;border-radius:6px;font-size:12px;font-weight:700;text-decoration:none">
          <i class="ph ph-map-pin"></i> Google Maps
        </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

    </div>
    <?php endforeach; ?>

  </div>

  <!-- ── RIGHT: Customer Summary ───────────────────────────────── -->
  <div style="position:sticky;top:20px">

    <!-- Stats -->
    <div class="admin-card" style="margin-bottom:16px">
      <h3 style="font-size:13px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:16px">Customer Summary</h3>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div style="background:rgba(248,196,23,0.08);border:1px solid rgba(248,196,23,0.2);border-radius:8px;padding:14px;text-align:center">
          <div style="font-size:26px;font-weight:700;color:var(--accent)"><?= $orderCount ?></div>
          <div style="font-size:11px;color:var(--text-muted);margin-top:2px">Total Orders</div>
        </div>
        <div style="background:rgba(255,255,255,0.04);border:1px solid var(--admin-border);border-radius:8px;padding:14px;text-align:center">
          <div style="font-size:22px;font-weight:700;color:#fff"><?= number_format($totalSpent, 0) ?></div>
          <div style="font-size:11px;color:var(--text-muted);margin-top:2px">EGP Spent</div>
        </div>
      </div>

      <div style="margin-top:14px;display:flex;flex-direction:column;gap:8px;font-size:13px">
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.05)">
          <span style="color:var(--text-muted)">First Order</span>
          <span style="color:#fff;font-weight:600"><?= date('d M Y', strtotime($first['created_at'])) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.05)">
          <span style="color:var(--text-muted)">Latest Order</span>
          <span style="color:#fff;font-weight:600"><?= date('d M Y', strtotime($latest['created_at'])) ?></span>
        </div>
        <?php
        $delivered  = count(array_filter($orders, fn($o) => $o['status'] === 'delivered'));
        $cancelled  = count(array_filter($orders, fn($o) => $o['status'] === 'cancelled'));
        ?>
        <?php if ($delivered > 0): ?>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.05)">
          <span style="color:var(--text-muted)">Delivered</span>
          <span style="color:#4CAF50;font-weight:600"><?= $delivered ?></span>
        </div>
        <?php endif; ?>
        <?php if ($cancelled > 0): ?>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.05)">
          <span style="color:var(--text-muted)">Cancelled</span>
          <span style="color:#f66;font-weight:600"><?= $cancelled ?></span>
        </div>
        <?php endif; ?>
        <?php if ($orderCount >= 2): ?>
        <div style="display:flex;justify-content:space-between;padding:8px 0">
          <span style="color:var(--text-muted)">Avg. Order Value</span>
          <span style="color:var(--accent);font-weight:700"><?= number_format($totalSpent / $orderCount, 0) ?> EGP</span>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Quick Contact -->
    <div class="admin-card" style="margin-bottom:16px">
      <h3 style="font-size:13px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:14px">Quick Contact</h3>
      <div style="display:flex;flex-direction:column;gap:8px">
        <?php if ($custPhone): ?>
        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $custPhone) ?>?text=<?= urlencode('Hello ' . $name . ', thank you for shopping at DUHN FRAGRANCES!') ?>"
           target="_blank"
           style="display:flex;align-items:center;gap:8px;padding:10px 14px;background:#25D366;color:#fff;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600">
          <i class="ph ph-whatsapp-logo"></i> WhatsApp <?= htmlspecialchars($name) ?>
        </a>
        <a href="tel:<?= htmlspecialchars($custPhone) ?>"
           style="display:flex;align-items:center;gap:8px;padding:10px 14px;background:var(--admin-card);border:1px solid var(--admin-border);color:#fff;border-radius:8px;text-decoration:none;font-size:13px">
          <i class="ph ph-phone"></i> Call <?= htmlspecialchars($custPhone) ?>
        </a>
        <?php endif; ?>
        <?php if ($custEmail): ?>
        <a href="mailto:<?= htmlspecialchars($custEmail) ?>"
           style="display:flex;align-items:center;gap:8px;padding:10px 14px;background:var(--admin-card);border:1px solid var(--admin-border);color:#fff;border-radius:8px;text-decoration:none;font-size:13px">
          <i class="ph ph-envelope"></i> <?= htmlspecialchars($custEmail) ?>
        </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Account Info -->
    <?php if ($user): ?>
    <div class="admin-card">
      <h3 style="font-size:13px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:12px">Registered Account</h3>
      <div style="font-size:13px;display:flex;flex-direction:column;gap:6px">
        <div style="display:flex;justify-content:space-between">
          <span style="color:var(--text-muted)">Joined</span>
          <span><?= date('d M Y', strtotime($user['created_at'])) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between">
          <span style="color:var(--text-muted)">Email Verified</span>
          <span><?= $user['email_verified_at'] ? '<span style="color:#4CAF50">Yes</span>' : '<span style="color:#888">No</span>' ?></span>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div>

</div>

<?php require_once __DIR__ . '/../../admin/includes/footer.php'; ?>
