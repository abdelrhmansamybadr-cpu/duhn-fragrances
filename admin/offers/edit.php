<?php
require_once __DIR__ . '/../../admin/includes/auth_check.php';
require_once __DIR__ . '/../../api/config/config.php';
require_once __DIR__ . '/../../api/config/database.php';

$db    = Database::getInstance();
$id    = (int)($_GET['id'] ?? 0);
$offer = $db->prepare("SELECT * FROM offers WHERE id = :id")->execute([':id' => $id]) ? null : null;
$stmt  = $db->prepare("SELECT * FROM offers WHERE id = :id");
$stmt->execute([':id' => $id]);
$offer = $stmt->fetch();
if (!$offer) { header('Location: /admin/offers.php'); exit; }

$products = $db->query("SELECT id, name FROM products ORDER BY name ASC")->fetchAll();
$error    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name             = trim($_POST['name']              ?? '');
    $offerType        = $_POST['offer_type']             ?? 'bogo';
    $triggerProductId = (int)($_POST['trigger_product_id'] ?? 0);
    $triggerQty       = max(1, (int)($_POST['trigger_qty'] ?? 1));
    $freeProductId    = (int)($_POST['free_product_id']  ?? 0);
    $freeQty          = max(1, (int)($_POST['free_qty']   ?? 1));
    $badgeText        = trim($_POST['badge_text']        ?? 'BUY 1 GET 1 FREE');
    $isActive         = isset($_POST['is_active']) ? 1 : 0;
    $startsAt         = !empty($_POST['starts_at']) ? $_POST['starts_at'] : null;
    $endsAt           = !empty($_POST['ends_at'])   ? $_POST['ends_at']   : null;

    if ($offerType === 'bogo') { $freeProductId = $triggerProductId; }

    if (!$name || !$triggerProductId || !$freeProductId) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            $db->prepare("
                UPDATE offers SET
                  name=:name, offer_type=:type, trigger_product_id=:tpid, trigger_qty=:tqty,
                  free_product_id=:fpid, free_qty=:fqty, badge_text=:badge,
                  is_active=:active, starts_at=:starts, ends_at=:ends
                WHERE id=:id
            ")->execute([
                ':name'  => $name, ':type' => $offerType, ':tpid' => $triggerProductId,
                ':tqty'  => $triggerQty, ':fpid' => $freeProductId, ':fqty' => $freeQty,
                ':badge' => $badgeText,  ':active' => $isActive,
                ':starts' => $startsAt,  ':ends' => $endsAt, ':id' => $id,
            ]);
            header('Location: /admin/offers.php?saved=1');
            exit;
        } catch (Throwable $e) {
            $error = 'Failed to update offer: ' . (APP_ENV === 'development' ? $e->getMessage() : '');
        }
    }
    // Re-populate $offer for re-render
    $offer = array_merge($offer, $_POST);
}

$adminTitle = 'Edit Offer';
require_once __DIR__ . '/../../admin/includes/header.php';
?>

<?php if ($error): ?><div class="admin-alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="POST">
  <div style="display:grid;grid-template-columns:1fr 320px;gap:28px;align-items:start">

    <div style="display:flex;flex-direction:column;gap:20px">
      <div class="admin-card">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">Offer Details</h3>
        <div class="admin-form-group">
          <label class="admin-label">Offer Name *</label>
          <input type="text" name="name" class="admin-input" required value="<?= htmlspecialchars($offer['name']) ?>">
        </div>
        <div class="admin-form-group">
          <label class="admin-label">Offer Type *</label>
          <select name="offer_type" class="admin-input" id="offer-type-select" onchange="toggleOfferType(this.value)">
            <option value="bogo"   <?= $offer['offer_type'] === 'bogo'   ? 'selected' : '' ?>>🔁 BOGO — Same product free</option>
            <option value="bundle" <?= $offer['offer_type'] === 'bundle' ? 'selected' : '' ?>>🎁 Bundle — Different product free</option>
          </select>
        </div>
      </div>

      <div class="admin-card">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">🛒 Trigger</h3>
        <div style="display:grid;grid-template-columns:1fr 120px;gap:16px">
          <div class="admin-form-group" style="margin-bottom:0">
            <label class="admin-label">Product *</label>
            <select name="trigger_product_id" class="admin-input" id="trigger-product" onchange="syncBogo()">
              <?php foreach ($products as $p): ?>
              <option value="<?= $p['id'] ?>" <?= (int)$offer['trigger_product_id'] === (int)$p['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($p['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="admin-form-group" style="margin-bottom:0">
            <label class="admin-label">Qty needed</label>
            <input type="number" name="trigger_qty" class="admin-input" min="1" value="<?= (int)$offer['trigger_qty'] ?>">
          </div>
        </div>
      </div>

      <div class="admin-card">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">🎁 Free Gift</h3>
        <div id="bogo-note" style="display:none;padding:12px;background:rgba(248,196,23,0.08);border:1px solid rgba(248,196,23,0.25);border-radius:6px;font-size:13px;color:var(--accent);margin-bottom:16px">
          ✦ BOGO: free product = trigger product automatically.
        </div>
        <div style="display:grid;grid-template-columns:1fr 120px;gap:16px" id="free-product-row">
          <div class="admin-form-group" style="margin-bottom:0">
            <label class="admin-label">Product *</label>
            <select name="free_product_id" class="admin-input" id="free-product">
              <?php foreach ($products as $p): ?>
              <option value="<?= $p['id'] ?>" <?= (int)$offer['free_product_id'] === (int)$p['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($p['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="admin-form-group" style="margin-bottom:0">
            <label class="admin-label">Qty free</label>
            <input type="number" name="free_qty" class="admin-input" min="1" value="<?= (int)$offer['free_qty'] ?>">
          </div>
        </div>
      </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:16px">
      <div class="admin-card">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">Badge & Display</h3>
        <div class="admin-form-group">
          <label class="admin-label">Badge Text</label>
          <input type="text" name="badge_text" class="admin-input" value="<?= htmlspecialchars($offer['badge_text']) ?>">
        </div>
        <span id="badge-preview" style="background:var(--accent);color:#1A1A1A;font-size:10px;font-weight:700;padding:4px 10px;border-radius:4px;letter-spacing:0.06em">
          <?= htmlspecialchars($offer['badge_text']) ?>
        </span>
      </div>

      <div class="admin-card">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">Status & Schedule</h3>
        <div class="admin-form-group">
          <label style="display:flex;justify-content:space-between;align-items:center;cursor:pointer">
            <span class="admin-label" style="margin-bottom:0">Active</span>
            <label class="toggle-switch">
              <input type="checkbox" name="is_active" <?= $offer['is_active'] ? 'checked' : '' ?>>
              <span class="toggle-slider"></span>
            </label>
          </label>
        </div>
        <div class="admin-form-group">
          <label class="admin-label">Start Date</label>
          <input type="datetime-local" name="starts_at" class="admin-input"
                 value="<?= $offer['starts_at'] ? date('Y-m-d\TH:i', strtotime($offer['starts_at'])) : '' ?>">
        </div>
        <div class="admin-form-group">
          <label class="admin-label">End Date</label>
          <input type="datetime-local" name="ends_at" class="admin-input"
                 value="<?= $offer['ends_at'] ? date('Y-m-d\TH:i', strtotime($offer['ends_at'])) : '' ?>">
        </div>
      </div>

      <div style="display:flex;gap:10px">
        <a href="/admin/offers.php" class="btn-admin-outline" style="flex:1;justify-content:center">Cancel</a>
        <button type="submit" class="btn-admin-gold" style="flex:2">Update Offer</button>
      </div>
    </div>
  </div>
</form>

<script>
function toggleOfferType(type) {
  const bogoNote = document.getElementById('bogo-note');
  const freeRow  = document.getElementById('free-product-row');
  if (type === 'bogo') {
    bogoNote.style.display = 'block';
    freeRow.style.opacity  = '0.4';
    freeRow.style.pointerEvents = 'none';
    syncBogo();
  } else {
    bogoNote.style.display = 'none';
    freeRow.style.opacity  = '1';
    freeRow.style.pointerEvents = 'auto';
  }
}
function syncBogo() {
  if (document.getElementById('offer-type-select').value === 'bogo') {
    document.getElementById('free-product').value =
      document.getElementById('trigger-product').value;
  }
}
document.querySelector('[name=badge_text]').addEventListener('input', function() {
  document.getElementById('badge-preview').textContent = this.value || 'OFFER';
});
toggleOfferType(document.getElementById('offer-type-select').value);
</script>

<?php require_once __DIR__ . '/../../admin/includes/footer.php'; ?>
