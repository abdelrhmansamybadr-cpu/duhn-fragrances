<?php
/**
 * DUHN FRAGRANCES — Admin: Abandoned Cart Recovery
 * Shows carts with a guest email + items but no completed order.
 * Admin can send a recovery email to individual carts or all at once.
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../api/helpers/Mailer.php';

$db = Database::getInstance();

// Auto-migrate columns
try { $db->exec("ALTER TABLE carts ADD COLUMN guest_email VARCHAR(191) DEFAULT NULL"); } catch (Throwable $_) {}
try { $db->exec("ALTER TABLE carts ADD COLUMN guest_name  VARCHAR(120) DEFAULT NULL"); } catch (Throwable $_) {}
try { $db->exec("ALTER TABLE carts ADD COLUMN recovery_sent_at DATETIME DEFAULT NULL"); } catch (Throwable $_) {}

$success = '';
$error   = '';

// ── Send recovery email to single cart ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_cart_id'])) {
    $cartId = (int)$_POST['send_cart_id'];
    try {
        $cart = $db->prepare("SELECT c.id, c.guest_email, c.guest_name FROM carts c WHERE c.id = :id AND c.guest_email IS NOT NULL");
        $cart->execute([':id' => $cartId]);
        $cart = $cart->fetch();

        if ($cart) {
            $items = $db->prepare("
                SELECT ci.quantity, p.name, p.price,
                       (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) AS image
                FROM cart_items ci
                JOIN products p ON p.id = ci.product_id
                WHERE ci.cart_id = :cid
            ");
            $items->execute([':cid' => $cartId]);
            $items = $items->fetchAll();

            if ($items) {
                $total = array_sum(array_map(fn($i) => (float)$i['price'] * (int)$i['quantity'], $items));
                $sent  = Mailer::sendAbandonedCartEmail($cart['guest_email'], $cart['guest_name'] ?? '', $items, $total);
                if ($sent) {
                    $db->prepare("UPDATE carts SET recovery_sent_at = NOW() WHERE id = :id")->execute([':id' => $cartId]);
                    $success = "✉️ Recovery email sent to {$cart['guest_email']}";
                } else {
                    $error = "Failed to send email to {$cart['guest_email']} — check SMTP settings.";
                }
            }
        }
    } catch (Throwable $e) { $error = $e->getMessage(); }
}

// ── Send to ALL unsent carts ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_all'])) {
    try {
        $allCarts = $db->query("
            SELECT c.id, c.guest_email, c.guest_name
            FROM carts c
            INNER JOIN cart_items ci ON ci.cart_id = c.id
            WHERE c.guest_email IS NOT NULL AND c.recovery_sent_at IS NULL
            GROUP BY c.id
        ")->fetchAll();

        $sentCount = 0;
        foreach ($allCarts as $cart) {
            $items = $db->prepare("
                SELECT ci.quantity, p.name, p.price,
                       (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) AS image
                FROM cart_items ci
                JOIN products p ON p.id = ci.product_id
                WHERE ci.cart_id = :cid
            ");
            $items->execute([':cid' => $cart['id']]);
            $items = $items->fetchAll();
            if (!$items) continue;
            $total = array_sum(array_map(fn($i) => (float)$i['price'] * (int)$i['quantity'], $items));
            $sent  = Mailer::sendAbandonedCartEmail($cart['guest_email'], $cart['guest_name'] ?? '', $items, $total);
            if ($sent) {
                $db->prepare("UPDATE carts SET recovery_sent_at = NOW() WHERE id = :id")->execute([':id' => $cart['id']]);
                $sentCount++;
            }
        }
        $success = "✉️ Sent {$sentCount} recovery email(s).";
    } catch (Throwable $e) { $error = $e->getMessage(); }
}

// ── Load abandoned carts ──────────────────────────────────────────
$abandonedCarts = $db->query("
    SELECT
        c.id,
        c.guest_email,
        c.guest_name,
        c.recovery_sent_at,
        c.created_at,
        COUNT(ci.id) AS item_count,
        SUM(ci.quantity * p.price) AS cart_total
    FROM carts c
    INNER JOIN cart_items ci ON ci.cart_id = c.id
    INNER JOIN products p ON p.id = ci.product_id
    WHERE c.guest_email IS NOT NULL
    GROUP BY c.id
    ORDER BY c.created_at DESC
")->fetchAll();

// Count unsent
$unsentCount = count(array_filter($abandonedCarts, fn($c) => empty($c['recovery_sent_at'])));

$adminTitle = 'Abandoned Carts';
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($success): ?><div class="admin-alert success"><i class="ph ph-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="admin-alert error"><i class="ph ph-warning-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Stats Bar -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:28px">
  <div class="admin-card" style="padding:18px 20px;text-align:center">
    <div style="font-size:28px;font-weight:700;color:var(--accent)"><?= count($abandonedCarts) ?></div>
    <div style="font-size:12px;color:var(--text-muted);margin-top:4px">Total Abandoned Carts</div>
  </div>
  <div class="admin-card" style="padding:18px 20px;text-align:center">
    <div style="font-size:28px;font-weight:700;color:#f87171"><?= $unsentCount ?></div>
    <div style="font-size:12px;color:var(--text-muted);margin-top:4px">Not Yet Emailed</div>
  </div>
  <div class="admin-card" style="padding:18px 20px;text-align:center">
    <div style="font-size:28px;font-weight:700;color:#4ade80"><?= count($abandonedCarts) - $unsentCount ?></div>
    <div style="font-size:12px;color:var(--text-muted);margin-top:4px">Recovery Emails Sent</div>
  </div>
</div>

<!-- Header & Bulk Send -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <h2 style="font-size:16px;font-weight:700">🛒 Carts with Email — Recovery Emails</h2>
  <?php if ($unsentCount > 0): ?>
  <form method="POST" onsubmit="return confirm('Send recovery emails to all <?= $unsentCount ?> unsent carts?')">
    <input type="hidden" name="send_all" value="1">
    <button type="submit" class="btn-admin-gold">
      <i class="ph ph-paper-plane-tilt"></i> Send to All (<?= $unsentCount ?> unsent)
    </button>
  </form>
  <?php endif; ?>
</div>

<!-- How it works -->
<div style="background:rgba(248,196,23,0.06);border:1px solid rgba(248,196,23,0.15);border-radius:8px;padding:14px 18px;margin-bottom:20px;font-size:12px;color:var(--text-muted);line-height:1.7">
  💡 <strong style="color:var(--accent)">How abandoned carts are captured:</strong>
  When a visitor subscribes via the <strong>Newsletter Popup</strong> and already has items in their cart,
  their email is automatically linked to their cart. You can then send them a friendly recovery email
  reminding them to complete their order.
</div>

<!-- Table -->
<div class="admin-card" style="padding:0;overflow:hidden">
  <?php if (empty($abandonedCarts)): ?>
  <div style="padding:64px;text-align:center;color:var(--text-muted)">
    <i class="ph ph-shopping-cart" style="font-size:48px;opacity:0.3;display:block;margin-bottom:12px"></i>
    No abandoned carts with emails yet. Carts appear here when a visitor subscribes to the newsletter with items in their cart.
  </div>
  <?php else: ?>
  <table class="admin-table" style="margin:0">
    <thead>
      <tr>
        <th>#</th>
        <th>Email</th>
        <th>Items</th>
        <th style="text-align:right">Cart Value</th>
        <th>Date Added</th>
        <th>Recovery Email</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($abandonedCarts as $cart): ?>
      <tr>
        <td style="color:var(--text-muted);font-size:12px"><?= $cart['id'] ?></td>
        <td>
          <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($cart['guest_email']) ?></div>
          <?php if ($cart['guest_name']): ?>
          <div style="font-size:11px;color:var(--text-muted)"><?= htmlspecialchars($cart['guest_name']) ?></div>
          <?php endif; ?>
        </td>
        <td style="text-align:center">
          <span class="admin-badge badge-muted"><?= $cart['item_count'] ?> item<?= $cart['item_count'] != 1 ? 's' : '' ?></span>
        </td>
        <td style="text-align:right;font-weight:700;color:var(--accent)">
          <?= number_format((float)$cart['cart_total'], 0) ?> EGP
        </td>
        <td style="font-size:12px;color:var(--text-muted)">
          <?= date('d M Y, H:i', strtotime($cart['created_at'])) ?>
        </td>
        <td>
          <?php if ($cart['recovery_sent_at']): ?>
          <span class="admin-badge badge-success">
            <i class="ph ph-check"></i> Sent <?= date('d M', strtotime($cart['recovery_sent_at'])) ?>
          </span>
          <?php else: ?>
          <span class="admin-badge badge-danger">Not sent</span>
          <?php endif; ?>
        </td>
        <td>
          <form method="POST" style="display:inline">
            <input type="hidden" name="send_cart_id" value="<?= $cart['id'] ?>">
            <button type="submit" class="btn-admin-outline"
                    style="font-size:11px;padding:5px 12px"
                    onclick="return confirm('Send recovery email to <?= htmlspecialchars($cart['guest_email']) ?>?')">
              <i class="ph ph-paper-plane-tilt"></i>
              <?= $cart['recovery_sent_at'] ? 'Re-send' : 'Send Email' ?>
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
