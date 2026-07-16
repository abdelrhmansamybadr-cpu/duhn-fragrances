<?php
require_once __DIR__ . '/../../admin/includes/auth_check.php';
require_once __DIR__ . '/../../api/config/config.php';
require_once __DIR__ . '/../../api/config/database.php';

$db       = Database::getInstance();
$products = $db->query("SELECT id, name FROM products ORDER BY name ASC")->fetchAll();
$error    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name              = trim($_POST['name']              ?? '');
    $offerType         = $_POST['offer_type']             ?? 'bogo';
    $triggerProductId  = (int)($_POST['trigger_product_id'] ?? 0);
    $triggerQty        = max(1, (int)($_POST['trigger_qty'] ?? 1));
    $freeProductId     = (int)($_POST['free_product_id']  ?? 0);
    $freeQty           = max(1, (int)($_POST['free_qty']   ?? 1));
    $badgeText         = trim($_POST['badge_text']        ?? 'BUY 1 GET 1 FREE');
    $isActive          = isset($_POST['is_active']) ? 1 : 0;
    $startsAt          = !empty($_POST['starts_at']) ? $_POST['starts_at'] : null;
    $endsAt            = !empty($_POST['ends_at'])   ? $_POST['ends_at']   : null;

    // For BOGO, free product = trigger product
    if ($offerType === 'bogo') {
        $freeProductId = $triggerProductId;
    }
    // For cart_deal, no specific product needed
    if ($offerType === 'cart_deal') {
        $triggerProductId = 0;
        $freeProductId    = 0;
    }

    $isCartDeal = ($offerType === 'cart_deal');
    if (!$name || (!$isCartDeal && (!$triggerProductId || !$freeProductId))) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            $db->prepare("
                INSERT INTO offers (name, offer_type, trigger_product_id, trigger_qty, free_product_id, free_qty, badge_text, is_active, starts_at, ends_at)
                VALUES (:name, :type, :tpid, :tqty, :fpid, :fqty, :badge, :active, :starts, :ends)
            ")->execute([
                ':name'   => $name,
                ':type'   => $offerType,
                ':tpid'   => $triggerProductId,
                ':tqty'   => $triggerQty,
                ':fpid'   => $freeProductId,
                ':fqty'   => $freeQty,
                ':badge'  => $badgeText,
                ':active' => $isActive,
                ':starts' => $startsAt,
                ':ends'   => $endsAt,
            ]);
            header('Location: /admin/offers.php?saved=1');
            exit;
        } catch (Throwable $e) {
            $error = 'Failed to save offer: ' . (APP_ENV === 'development' ? $e->getMessage() : '');
        }
    }
}

$adminTitle = 'New Offer';
require_once __DIR__ . '/../../admin/includes/header.php';
?>

<?php if ($error): ?><div class="admin-alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="POST">
  <div style="display:grid;grid-template-columns:1fr 320px;gap:28px;align-items:start">

    <!-- Left: Offer details -->
    <div style="display:flex;flex-direction:column;gap:20px">

      <div class="admin-card">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">Offer Details</h3>

        <div class="admin-form-group">
          <label class="admin-label">Offer Name *</label>
          <input type="text" name="name" class="admin-input" required
                 value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                 placeholder="e.g. Buy Carnation, Get Euphoria Free">
        </div>

        <div class="admin-form-group">
          <label class="admin-label">Offer Type *</label>
          <select name="offer_type" class="admin-input" id="offer-type-select" onchange="toggleOfferType(this.value)">
            <option value="bogo"      <?= ($_POST['offer_type'] ?? '') === 'bogo'      ? 'selected' : '' ?>>🔁 BOGO — Buy one, get the SAME product free</option>
            <option value="bundle"    <?= ($_POST['offer_type'] ?? '') === 'bundle'    ? 'selected' : '' ?>>🎁 Bundle — Buy product A, get a DIFFERENT product free</option>
            <option value="cart_deal" <?= ($_POST['offer_type'] ?? '') === 'cart_deal' ? 'selected' : '' ?>>🛒 Cart Deal — Buy N items (any), get M free (sitewide)</option>
          </select>
        </div>
      </div>

      <!-- Product BOGO / Bundle trigger section -->
      <div class="admin-card" id="product-trigger-card">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">
          🛒 Trigger — What the customer must buy
        </h3>

        <div style="display:grid;grid-template-columns:1fr 120px;gap:16px">
          <div class="admin-form-group" style="margin-bottom:0">
            <label class="admin-label">Product *</label>
            <select name="trigger_product_id" class="admin-input" id="trigger-product" onchange="syncBogo()">
              <option value="">Select a product...</option>
              <?php foreach ($products as $p): ?>
              <option value="<?= $p['id'] ?>" <?= (int)($_POST['trigger_product_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($p['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="admin-form-group" style="margin-bottom:0">
            <label class="admin-label">Qty needed</label>
            <input type="number" name="trigger_qty" class="admin-input" min="1" max="99"
                   value="<?= (int)($_POST['trigger_qty'] ?? 1) ?>">
          </div>
        </div>
      </div>

      <!-- Free Gift section (BOGO / Bundle) -->
      <div class="admin-card" id="free-product-card">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">
          🎁 Free Gift — What the customer gets
        </h3>

        <div id="bogo-note" style="display:none;padding:12px;background:rgba(248,196,23,0.08);border:1px solid rgba(248,196,23,0.25);border-radius:6px;font-size:13px;color:var(--accent);margin-bottom:16px">
          ✦ BOGO: the free product is automatically the same as the trigger product.
        </div>

        <div style="display:grid;grid-template-columns:1fr 120px;gap:16px" id="free-product-row">
          <div class="admin-form-group" style="margin-bottom:0">
            <label class="admin-label">Product *</label>
            <select name="free_product_id" class="admin-input" id="free-product">
              <option value="">Select a product...</option>
              <?php foreach ($products as $p): ?>
              <option value="<?= $p['id'] ?>" <?= (int)($_POST['free_product_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($p['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="admin-form-group" style="margin-bottom:0">
            <label class="admin-label">Qty free</label>
            <input type="number" name="free_qty" class="admin-input" min="1" max="99"
                   value="<?= (int)($_POST['free_qty'] ?? 1) ?>">
          </div>
        </div>
      </div>

      <!-- Cart Deal section — shown instead of product sections -->
      <div class="admin-card" id="cart-deal-card" style="display:none">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:6px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">🛒 Cart Deal — Sitewide</h3>
        <p style="font-size:12px;color:var(--text-muted);margin-bottom:18px">
          Applies to ALL products. When a customer has enough items in their cart, the cheapest items become free.
          This controls the <strong style="color:var(--accent)">BUY 2 GET 2 FREE</strong> banner in the cart.
        </p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
          <div class="admin-form-group" style="margin-bottom:0">
            <label class="admin-label">Min Items in Cart to Trigger *</label>
            <input type="number" name="trigger_qty" id="cart-deal-trigger-qty" class="admin-input" min="1" max="20"
                   value="<?= (int)($_POST['trigger_qty'] ?? 4) ?>">
            <p style="font-size:11px;color:var(--text-muted);margin-top:4px">e.g. 4 = customer must have 4 items</p>
          </div>
          <div class="admin-form-group" style="margin-bottom:0">
            <label class="admin-label">Items Given Free *</label>
            <input type="number" name="free_qty" id="cart-deal-free-qty" class="admin-input" min="1" max="20"
                   value="<?= (int)($_POST['free_qty'] ?? 2) ?>">
            <p style="font-size:11px;color:var(--text-muted);margin-top:4px">e.g. 2 = cheapest 2 items are free</p>
          </div>
        </div>
        <div style="margin-top:14px;padding:12px;background:rgba(248,196,23,0.06);border:1px solid rgba(248,196,23,0.2);border-radius:8px;font-size:12px;color:var(--text-muted)">
          💡 The <strong style="color:var(--accent)">Badge Text</strong> on the right is what appears in the cart banner and announcement bar.
          Example: <em>"BUY 2 GET 2 FREE"</em>
        </div>
      </div>

    </div>

    <!-- Right: Settings -->
    <div style="display:flex;flex-direction:column;gap:16px">

      <div class="admin-card">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">Badge & Display</h3>
        <div class="admin-form-group">
          <label class="admin-label">Badge Text</label>
          <input type="text" name="badge_text" class="admin-input"
                 value="<?= htmlspecialchars($_POST['badge_text'] ?? 'BUY 1 GET 1 FREE') ?>"
                 placeholder="BUY 1 GET 1 FREE">
          <p style="font-size:11px;color:var(--text-muted);margin-top:4px">
            Shown as a gold badge on the product card and product page.
          </p>
        </div>
        <!-- Preview -->
        <div style="margin-top:8px">
          <span id="badge-preview" style="background:var(--accent);color:#1A1A1A;font-size:10px;font-weight:700;padding:4px 10px;border-radius:4px;letter-spacing:0.06em">
            BUY 1 GET 1 FREE
          </span>
        </div>
      </div>

      <div class="admin-card">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">Status & Schedule</h3>

        <div class="admin-form-group">
          <label style="display:flex;justify-content:space-between;align-items:center;cursor:pointer">
            <span class="admin-label" style="margin-bottom:0">Active</span>
            <label class="toggle-switch">
              <input type="checkbox" name="is_active" <?= !isset($_POST['offer_type']) || isset($_POST['is_active']) ? 'checked' : '' ?>>
              <span class="toggle-slider"></span>
            </label>
          </label>
        </div>

        <div class="admin-form-group">
          <label class="admin-label">Start Date <span style="color:var(--text-muted);font-weight:400">(optional)</span></label>
          <input type="datetime-local" name="starts_at" class="admin-input"
                 value="<?= htmlspecialchars($_POST['starts_at'] ?? '') ?>">
        </div>
        <div class="admin-form-group">
          <label class="admin-label">End Date <span style="color:var(--text-muted);font-weight:400">(optional)</span></label>
          <input type="datetime-local" name="ends_at" class="admin-input"
                 value="<?= htmlspecialchars($_POST['ends_at'] ?? '') ?>">
          <p style="font-size:11px;color:var(--text-muted);margin-top:4px">Leave blank = no expiry.</p>
        </div>
      </div>

      <div style="display:flex;gap:10px">
        <a href="/admin/offers.php" class="btn-admin-outline" style="flex:1;justify-content:center">Cancel</a>
        <button type="submit" class="btn-admin-gold" style="flex:2">Save Offer</button>
      </div>
    </div>

  </div>
</form>

<script>
function toggleOfferType(type) {
  const bogoNote        = document.getElementById('bogo-note');
  const freeProductRow  = document.getElementById('free-product-row');
  const productTrigger  = document.getElementById('product-trigger-card');
  const freeProductCard = document.getElementById('free-product-card');
  const cartDealCard    = document.getElementById('cart-deal-card');

  if (type === 'cart_deal') {
    // Show only cart deal card
    productTrigger.style.display  = 'none';
    freeProductCard.style.display = 'none';
    cartDealCard.style.display    = 'block';
    bogoNote.style.display        = 'none';
    // Default badge text for cart deals
    const badgeInput = document.querySelector('[name=badge_text]');
    if (badgeInput && !badgeInput.value) {
      badgeInput.value = 'BUY 2 GET 2 FREE';
      document.getElementById('badge-preview').textContent = 'BUY 2 GET 2 FREE';
    }
  } else {
    productTrigger.style.display  = 'block';
    freeProductCard.style.display = 'block';
    cartDealCard.style.display    = 'none';
    if (type === 'bogo') {
      bogoNote.style.display       = 'block';
      freeProductRow.style.opacity = '0.4';
      freeProductRow.style.pointerEvents = 'none';
      syncBogo();
    } else {
      bogoNote.style.display       = 'none';
      freeProductRow.style.opacity = '1';
      freeProductRow.style.pointerEvents = 'auto';
    }
  }
}

function syncBogo() {
  if (document.getElementById('offer-type-select').value === 'bogo') {
    document.getElementById('free-product').value =
      document.getElementById('trigger-product').value;
  }
}

// Live badge preview
document.querySelector('[name=badge_text]').addEventListener('input', function() {
  document.getElementById('badge-preview').textContent = this.value || 'OFFER';
});

// Init
toggleOfferType(document.getElementById('offer-type-select').value);
</script>

<?php require_once __DIR__ . '/../../admin/includes/footer.php'; ?>
