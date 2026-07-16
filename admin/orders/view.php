<?php
require_once __DIR__ . '/../../admin/includes/auth_check.php';
require_once __DIR__ . '/../../api/config/config.php';
require_once __DIR__ . '/../../api/config/database.php';

$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: /admin/orders.php');
    exit;
}

$order = $db->prepare("SELECT * FROM orders WHERE id = :id");
$order->execute([':id' => $id]);
$order = $order->fetch();

if (!$order) {
    header('Location: /admin/orders.php');
    exit;
}

$items = $db->prepare("
    SELECT oi.*, pi.image_url
    FROM order_items oi
    LEFT JOIN (
        SELECT product_id, MIN(image_url) AS image_url
        FROM product_images GROUP BY product_id
    ) pi ON pi.product_id = oi.product_id
    WHERE oi.order_id = :id
");
$items->execute([':id' => $id]);
$items = $items->fetchAll();

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $newStatus = $_POST['status'] ?? '';
    $notes     = trim($_POST['notes'] ?? '');
    $allowed   = ['pending','confirmed','shipped','delivered','cancelled'];

    if (in_array($newStatus, $allowed)) {
        $prevStatus = $order['status'];
        $upd = $db->prepare("UPDATE orders SET status = :s, notes = :n, updated_at = NOW() WHERE id = :id");
        $upd->execute([':s' => $newStatus, ':n' => $notes, ':id' => $id]);

        // Send status email to customer if status actually changed and customer has email
        $emailSent = false;
        if ($newStatus !== $prevStatus && !empty($order['customer_email']) && $newStatus !== 'pending') {
            require_once __DIR__ . '/../../api/helpers/Mailer.php';
            $emailSent = Mailer::sendOrderStatusUpdate($order, $items, $newStatus);
        }

        $success = 'Order updated successfully.' . ($emailSent ? ' ✉️ Status email sent to customer.' : '');
        // Reload
        $order['status'] = $newStatus;
        $order['notes']  = $notes;
    } else {
        $error = 'Invalid status.';
    }
}

$statusColors = [
    'pending'   => 'badge-gold',
    'confirmed' => 'badge-muted',
    'shipped'   => 'badge-muted',
    'delivered' => 'badge-success',
    'cancelled' => 'badge-danger',
];
$clr = $statusColors[$order['status']] ?? 'badge-muted';

$adminTitle = 'Order #' . htmlspecialchars($order['order_number']);
require_once __DIR__ . '/../../admin/includes/header.php';
?>

<?php if ($success): ?><div class="admin-alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="admin-alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
  <div>
    <a href="/admin/orders.php" style="color:var(--text-muted);font-size:13px;text-decoration:none">← Back to Orders</a>
    <h1 style="font-size:20px;font-weight:700;margin-top:4px">Order #<?= htmlspecialchars($order['order_number']) ?></h1>
  </div>
  <span class="admin-badge <?= $clr ?>" style="font-size:13px;padding:6px 14px"><?= ucfirst($order['status']) ?></span>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">

  <!-- Left Column -->
  <div style="display:flex;flex-direction:column;gap:16px">

    <!-- Order Info -->
    <div class="admin-card">
      <h3 style="font-size:13px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:14px">Order Info</h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div>
          <div style="font-size:11px;color:var(--text-muted);margin-bottom:3px">ORDER NUMBER</div>
          <div style="font-weight:700;color:var(--accent)"><?= htmlspecialchars($order['order_number']) ?></div>
        </div>
        <div>
          <div style="font-size:11px;color:var(--text-muted);margin-bottom:3px">DATE PLACED</div>
          <div><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></div>
        </div>
        <div>
          <div style="font-size:11px;color:var(--text-muted);margin-bottom:3px">PAYMENT METHOD</div>
          <div style="text-transform:uppercase;font-size:13px"><?= htmlspecialchars($order['payment_method']) ?></div>
        </div>
        <div>
          <div style="font-size:11px;color:var(--text-muted);margin-bottom:3px">STATUS</div>
          <span class="admin-badge <?= $clr ?>"><?= ucfirst($order['status']) ?></span>
        </div>
      </div>
    </div>

    <!-- Customer Info -->
    <div class="admin-card">
      <h3 style="font-size:13px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:14px">Customer</h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div>
          <div style="font-size:11px;color:var(--text-muted);margin-bottom:3px">NAME</div>
          <div style="font-weight:600"><?= htmlspecialchars($order['customer_name']) ?></div>
        </div>
        <div>
          <div style="font-size:11px;color:var(--text-muted);margin-bottom:3px">PHONE</div>
          <div><a href="tel:<?= htmlspecialchars($order['customer_phone']) ?>" style="color:var(--accent)"><?= htmlspecialchars($order['customer_phone']) ?></a></div>
        </div>
        <?php if ($order['customer_email']): ?>
        <div>
          <div style="font-size:11px;color:var(--text-muted);margin-bottom:3px">EMAIL</div>
          <div style="font-size:13px"><?= htmlspecialchars($order['customer_email']) ?></div>
        </div>
        <?php endif; ?>
        <div>
          <div style="font-size:11px;color:var(--text-muted);margin-bottom:3px">GOVERNORATE</div>
          <div><?= htmlspecialchars($order['governorate']) ?></div>
        </div>
        <?php if ($order['delivery_address']): ?>
        <div style="grid-column:1/-1">
          <div style="font-size:11px;color:var(--text-muted);margin-bottom:3px">DELIVERY ADDRESS</div>
          <div style="font-size:13px;color:#ccc"><?= nl2br(htmlspecialchars($order['delivery_address'])) ?></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($order['gps_lat']) && !empty($order['gps_lng'])): ?>
        <div style="grid-column:1/-1">
          <div style="font-size:11px;color:var(--text-muted);margin-bottom:6px">📍 GPS LOCATION PIN</div>
          <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <?php if (!empty($order['gps_label'])): ?>
            <span style="font-size:13px;font-weight:700;color:#fff"><?= htmlspecialchars($order['gps_label']) ?></span>
            <?php endif; ?>
            <span style="font-size:12px;color:#aaa"><?= (float)$order['gps_lat'] ?>, <?= (float)$order['gps_lng'] ?></span>
            <?php if (!empty($order['gps_map_url'])): ?>
            <a href="<?= htmlspecialchars($order['gps_map_url']) ?>" target="_blank" rel="noopener"
               style="display:inline-flex;align-items:center;gap:5px;background:var(--accent);color:#1A1A1A;padding:6px 14px;border-radius:6px;font-size:12px;font-weight:700;text-decoration:none">
              <i class="ph ph-map-pin"></i> Open in Google Maps
            </a>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Order Items -->
    <div class="admin-card" style="padding:0;overflow:hidden">
      <div style="padding:16px 20px 14px;border-bottom:1px solid var(--admin-border)">
        <h3 style="font-size:13px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em">
          Items (<?= count($items) ?>)
        </h3>
      </div>
      <table class="admin-table" style="margin:0">
        <thead>
          <tr>
            <th width="56">Image</th>
            <th>Product</th>
            <th style="text-align:center">Qty</th>
            <th style="text-align:right">Unit Price</th>
            <th style="text-align:right">Line Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
          <tr>
            <td>
              <img src="<?= !empty($item['image_url']) ? htmlspecialchars($item['image_url']) : '/public/images/placeholder.jpg' ?>"
                   alt="" style="width:44px;height:44px;object-fit:cover;border-radius:6px;border:1px solid var(--admin-border)"
                   onerror="this.src='/public/images/placeholder.jpg'">
            </td>
            <td style="font-weight:600"><?= htmlspecialchars($item['product_name']) ?></td>
            <td style="text-align:center"><?= (int)$item['quantity'] ?></td>
            <td style="text-align:right"><?= number_format((float)$item['product_price'], 0) ?> EGP</td>
            <td style="text-align:right;font-weight:700;color:var(--accent)"><?= number_format((float)$item['line_total'], 0) ?> EGP</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <!-- Totals -->
      <div style="padding:16px 20px;border-top:1px solid var(--admin-border);display:flex;flex-direction:column;gap:8px;align-items:flex-end">
        <div style="display:flex;justify-content:space-between;width:260px;font-size:13px">
          <span style="color:var(--text-muted)">Subtotal</span>
          <span><?= number_format((float)$order['subtotal'], 0) ?> EGP</span>
        </div>
        <?php if ((float)$order['discount'] > 0): ?>
        <div style="display:flex;justify-content:space-between;width:260px;font-size:13px">
          <span style="color:var(--success)">Promo Discount</span>
          <span style="color:var(--success)">-<?= number_format((float)$order['discount'], 0) ?> EGP</span>
        </div>
        <?php endif; ?>
        <div style="display:flex;justify-content:space-between;width:260px;font-size:13px">
          <span style="color:var(--text-muted)">Delivery Fee</span>
          <span><?= (float)$order['delivery_fee'] > 0 ? number_format((float)$order['delivery_fee'],0).' EGP' : 'Free' ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;width:260px;font-size:16px;font-weight:700;border-top:1px solid var(--admin-border);padding-top:10px;margin-top:4px">
          <span>Total</span>
          <span style="color:var(--accent)"><?= number_format((float)$order['total'], 0) ?> EGP</span>
        </div>
      </div>
    </div>

  </div>

  <!-- Right Column: Update Status -->
  <div style="display:flex;flex-direction:column;gap:16px">
    <div class="admin-card">
      <h3 style="font-size:13px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:14px">Update Order</h3>
      <form method="POST">
        <div class="admin-form-group">
          <label class="admin-label">Status</label>
          <select name="status" class="admin-input">
            <?php foreach (['pending','confirmed','shipped','delivered','cancelled'] as $s): ?>
            <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="admin-form-group">
          <label class="admin-label">Internal Notes</label>
          <textarea name="notes" class="admin-input" rows="4" placeholder="Add a note..."><?= htmlspecialchars($order['notes'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn-admin-gold" style="width:100%">Update Order</button>
      </form>
    </div>

    <!-- Quick Actions -->
    <div class="admin-card">
      <h3 style="font-size:13px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:14px">Quick Actions</h3>
      <div style="display:flex;flex-direction:column;gap:8px">
        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $order['customer_phone']) ?>?text=<?= urlencode('Hello '.$order['customer_name'].', your DUHN FRAGRANCES order #'.$order['order_number'].' update:') ?>"
           target="_blank"
           style="display:flex;align-items:center;gap:8px;padding:10px 14px;background:#25D366;color:#fff;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600">
          <i class="ph ph-whatsapp-logo"></i> WhatsApp Customer
        </a>
        <a href="tel:<?= htmlspecialchars($order['customer_phone']) ?>"
           style="display:flex;align-items:center;gap:8px;padding:10px 14px;background:var(--admin-card);border:1px solid var(--admin-border);color:#fff;border-radius:8px;text-decoration:none;font-size:13px">
          <i class="ph ph-phone"></i> Call Customer
        </a>
      </div>
    </div>

    <?php if ($order['notes']): ?>
    <div class="admin-card">
      <h3 style="font-size:13px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">Notes</h3>
      <p style="font-size:13px;color:#ccc;line-height:1.6"><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
    </div>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . '/../../admin/includes/footer.php'; ?>
