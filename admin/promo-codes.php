<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/config/database.php';

$db    = Database::getInstance();
$error = '';
$success = '';

// ── Auto-migrate new columns ──────────────────────────────────────
try { $db->exec("ALTER TABLE promo_codes ADD COLUMN per_product TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $_) {}
try { $db->exec("CREATE TABLE IF NOT EXISTS promo_code_product_uses (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    promo_code_id INT UNSIGNED NOT NULL,
    product_id    INT UNSIGNED NOT NULL,
    use_count     INT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY uq_code_product (promo_code_id, product_id)
)"); } catch (Throwable $_) {}

// ── Handle POST actions ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Add new promo code
    if ($action === 'add') {
        $code       = strtoupper(trim($_POST['code'] ?? ''));
        $desc       = trim($_POST['description'] ?? '');
        $type       = $_POST['type'] === 'fixed' ? 'fixed' : 'percent';
        $value      = max(0.01, (float)($_POST['value'] ?? 0));
        $minOrder   = max(0, (float)($_POST['min_order'] ?? 0));
        $maxUses    = $_POST['max_uses'] !== '' ? max(1, (int)$_POST['max_uses']) : null;
        $expires    = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
        $perProduct = isset($_POST['per_product']) ? 1 : 0;

        if (!$code || !preg_match('/^[A-Z0-9_\-]{3,30}$/', $code)) {
            $error = 'Code must be 3–30 uppercase letters, numbers, hyphens or underscores.';
        } elseif ($type === 'percent' && $value > 100) {
            $error = 'Percent discount cannot exceed 100%.';
        } else {
            try {
                $db->prepare("
                    INSERT INTO promo_codes (code, description, type, value, min_order, max_uses, expires_at, per_product)
                    VALUES (:code,:desc,:type,:val,:min,:max,:exp,:pp)
                ")->execute([
                    ':code' => $code, ':desc' => $desc, ':type' => $type,
                    ':val'  => $value, ':min'  => $minOrder,
                    ':max'  => $maxUses, ':exp' => $expires, ':pp' => $perProduct,
                ]);
                $success = "Promo code <strong>{$code}</strong> created successfully!";
            } catch (Throwable $e) {
                $error = str_contains($e->getMessage(), 'Duplicate') ? "Code '{$code}' already exists." : $e->getMessage();
            }
        }
    }

    // Toggle active
    if ($action === 'toggle') {
        $id  = (int)$_POST['id'];
        $db->prepare("UPDATE promo_codes SET is_active = NOT is_active WHERE id = :id")->execute([':id' => $id]);
        header('Location: /admin/promo-codes.php?toggled=1'); exit;
    }

    // Delete
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare("DELETE FROM promo_codes WHERE id = :id")->execute([':id' => $id]);
        header('Location: /admin/promo-codes.php?deleted=1'); exit;
    }
}

$codes = $db->query("SELECT * FROM promo_codes ORDER BY created_at DESC")->fetchAll();

$adminTitle = 'Promo Codes';
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($success): ?>
<div class="admin-alert success" id="flash-msg">✅ <?= $success ?></div>
<?php elseif ($error): ?>
<div class="admin-alert error" id="flash-msg">⚠️ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if (isset($_GET['deleted'])): ?>
<div class="admin-alert success" id="flash-msg">🗑 Promo code deleted.</div>
<?php endif; ?>
<?php if (isset($_GET['toggled'])): ?>
<div class="admin-alert success" id="flash-msg">✅ Status updated.</div>
<?php endif; ?>

<!-- ── ADD FORM ───────────────────────────────────────────────── -->
<div class="admin-card" style="margin-bottom:28px">
  <h3 style="font-size:14px;font-weight:700;color:var(--text-muted);letter-spacing:0.06em;text-transform:uppercase;margin-bottom:20px">
    🏷️ Create New Promo Code
  </h3>
  <form method="POST">
    <input type="hidden" name="action" value="add">
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px">

      <div class="admin-form-group" style="margin:0">
        <label class="admin-label">Code *</label>
        <input type="text" name="code" class="admin-input" value="<?= htmlspecialchars($_POST['code'] ?? '') ?>"
               placeholder="e.g. SAVE20" style="text-transform:uppercase"
               oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9_\-]/g,'')" required>
        <span style="font-size:11px;color:var(--text-muted)">Uppercase letters, numbers, - and _ only</span>
      </div>

      <div class="admin-form-group" style="margin:0">
        <label class="admin-label">Discount Type *</label>
        <select name="type" class="admin-input" id="promo-type" onchange="toggleTypeHint()">
          <option value="percent" <?= ($_POST['type'] ?? '') === 'percent' ? 'selected' : '' ?>>Percentage (%)</option>
          <option value="fixed"   <?= ($_POST['type'] ?? '') === 'fixed'   ? 'selected' : '' ?>>Fixed Amount (EGP)</option>
        </select>
      </div>

      <div class="admin-form-group" style="margin:0">
        <label class="admin-label">Discount Value *</label>
        <div style="position:relative">
          <input type="number" name="value" class="admin-input" value="<?= htmlspecialchars($_POST['value'] ?? '') ?>"
                 min="0.01" step="0.01" placeholder="e.g. 20" required style="padding-right:50px">
          <span id="type-hint" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;pointer-events:none">%</span>
        </div>
      </div>

      <div class="admin-form-group" style="margin:0">
        <label class="admin-label">Description (optional)</label>
        <input type="text" name="description" class="admin-input" value="<?= htmlspecialchars($_POST['description'] ?? '') ?>"
               placeholder="Internal note about this code">
      </div>

      <div class="admin-form-group" style="margin:0">
        <label class="admin-label">Min. Order Amount (EGP)</label>
        <input type="number" name="min_order" class="admin-input" value="<?= htmlspecialchars($_POST['min_order'] ?? '0') ?>"
               min="0" step="0.01" placeholder="0 = no minimum">
      </div>

      <div class="admin-form-group" style="margin:0">
        <label class="admin-label">Max Uses</label>
        <input type="number" name="max_uses" class="admin-input" value="<?= htmlspecialchars($_POST['max_uses'] ?? '') ?>"
               min="1" placeholder="Leave empty = unlimited">
      </div>

      <div class="admin-form-group" style="margin:0">
        <label class="admin-label">Expiry Date</label>
        <input type="date" name="expires_at" class="admin-input" value="<?= htmlspecialchars($_POST['expires_at'] ?? '') ?>"
               min="<?= date('Y-m-d') ?>">
        <span style="font-size:11px;color:var(--text-muted)">Leave empty = never expires</span>
      </div>
    </div>

    <!-- Per-Product option -->
    <div style="margin:12px 0 20px;padding:14px 18px;background:rgba(248,196,23,0.05);border:1px solid rgba(248,196,23,0.18);border-radius:8px">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <div>
          <div style="font-size:14px;font-weight:600">🎯 One-Time Per Product</div>
          <div style="font-size:12px;color:var(--text-muted);margin-top:3px">
            Once this code is used in an order containing Product X, it can never be applied to another order containing Product X again.<br>
            <em>Example: SAVE50 is used on "Carnation" → customers can never get 50 EGP off "Carnation" again using this code.</em>
          </div>
        </div>
        <label class="toggle-switch" style="flex-shrink:0;margin-left:16px">
          <input type="checkbox" name="per_product" <?= isset($_POST['per_product']) ? 'checked' : '' ?>>
          <span class="toggle-slider"></span>
        </label>
      </div>
    </div>

    <button type="submit" class="btn-admin-gold"><i class="ph ph-plus"></i> Create Promo Code</button>
  </form>
</div>

<!-- ── CODES LIST ─────────────────────────────────────────────── -->
<div class="admin-card" style="padding:0;overflow:hidden">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Code</th>
        <th>Type</th>
        <th>Discount</th>
        <th>Min Order</th>
        <th style="text-align:center">Uses</th>
        <th style="text-align:center">Per-Product</th>
        <th>Expires</th>
        <th style="text-align:center">Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($codes)): ?>
      <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:48px">
        No promo codes yet. Create your first one above.
      </td></tr>
      <?php endif; ?>
      <?php foreach ($codes as $pc):
        $expired  = $pc['expires_at'] && $pc['expires_at'] < date('Y-m-d');
        $maxed    = $pc['max_uses'] && $pc['used_count'] >= $pc['max_uses'];
        $isLive   = $pc['is_active'] && !$expired && !$maxed;
      ?>
      <tr style="opacity:<?= $isLive ? '1' : '0.55' ?>">
        <td>
          <div style="font-family:monospace;font-size:15px;font-weight:700;color:var(--accent);letter-spacing:0.08em">
            <?= htmlspecialchars($pc['code']) ?>
          </div>
          <?php if ($pc['description']): ?>
          <div style="font-size:11px;color:var(--text-muted)"><?= htmlspecialchars($pc['description']) ?></div>
          <?php endif; ?>
        </td>
        <td style="font-size:12px;color:var(--text-muted)"><?= $pc['type'] === 'percent' ? 'Percentage' : 'Fixed (EGP)' ?></td>
        <td>
          <span style="font-size:15px;font-weight:700;color:<?= $isLive ? '#4CAF50' : 'var(--text-muted)' ?>">
            <?= $pc['type'] === 'percent' ? number_format($pc['value'],0).'%' : number_format($pc['value'],0).' EGP' ?> OFF
          </span>
        </td>
        <td style="font-size:13px;color:var(--text-muted)">
          <?= $pc['min_order'] > 0 ? number_format($pc['min_order'],0).' EGP' : '<span style="color:var(--text-muted)">None</span>' ?>
        </td>
        <td style="text-align:center">
          <span style="font-weight:700"><?= (int)$pc['used_count'] ?></span>
          <?php if ($pc['max_uses']): ?>
          <span style="color:var(--text-muted);font-size:11px"> / <?= (int)$pc['max_uses'] ?></span>
          <?php else: ?>
          <span style="color:var(--text-muted);font-size:11px"> / ∞</span>
          <?php endif; ?>
        </td>
        <td style="text-align:center">
          <?php if (!empty($pc['per_product'])): ?>
          <span title="One-time per product" style="font-size:16px">🎯</span>
          <?php else: ?>
          <span style="color:var(--text-muted);font-size:12px">—</span>
          <?php endif; ?>
        </td>
        <td style="font-size:12px;color:var(--text-muted)">
          <?php if ($pc['expires_at']): ?>
          <span style="color:<?= $expired ? 'var(--danger)' : 'inherit' ?>">
            <?= date('d M Y', strtotime($pc['expires_at'])) ?>
            <?= $expired ? ' (Expired)' : '' ?>
          </span>
          <?php else: ?>
          <span style="color:var(--text-muted)">Never</span>
          <?php endif; ?>
        </td>
        <td style="text-align:center">
          <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= $pc['id'] ?>">
            <button type="submit" class="admin-badge <?= $pc['is_active'] ? 'badge-success' : '' ?>"
                    style="border:none;cursor:pointer;padding:4px 10px;font-size:11px;border-radius:20px;background:<?= $pc['is_active'] ? '' : 'rgba(255,255,255,0.08)' ?>;color:<?= $pc['is_active'] ? '' : '#aaa' ?>">
              <?= $pc['is_active'] ? 'Active' : 'Disabled' ?>
            </button>
          </form>
          <?php if ($maxed): ?><div style="font-size:10px;color:var(--danger);margin-top:3px">Limit reached</div><?php endif; ?>
          <?php if ($expired && !$maxed): ?><div style="font-size:10px;color:var(--danger);margin-top:3px">Expired</div><?php endif; ?>
        </td>
        <td>
          <div style="display:flex;gap:6px">
            <!-- Copy code button -->
            <button type="button" onclick="copyCode('<?= htmlspecialchars($pc['code']) ?>')"
                    class="btn-admin-outline" title="Copy code" style="padding:6px 10px">
              <i class="ph ph-copy"></i>
            </button>
            <!-- Delete -->
            <button type="button" class="btn-admin-danger del-trigger"
                    data-id="<?= $pc['id'] ?>"
                    data-name="<?= htmlspecialchars($pc['code']) ?>"
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

<!-- Delete modal -->
<div id="del-modal-overlay" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,0.65);backdrop-filter:blur(3px);align-items:center;justify-content:center">
  <div style="background:#1A1A1A;border:1px solid var(--danger);border-radius:12px;padding:28px 32px;width:340px;max-width:90vw;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.7)">
    <div style="width:52px;height:52px;background:rgba(220,53,69,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
      <i class="ph ph-trash" style="font-size:24px;color:var(--danger)"></i>
    </div>
    <h3 style="color:#fff;font-size:16px;font-weight:700;margin:0 0 8px">Delete Promo Code?</h3>
    <p id="del-modal-name" style="color:var(--accent);font-size:15px;font-weight:700;letter-spacing:0.08em;margin:0 0 8px;font-family:monospace"></p>
    <p style="color:var(--text-muted);font-size:13px;margin:0 0 24px">This cannot be undone.</p>
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
const flash = document.getElementById('flash-msg');
if (flash) setTimeout(() => flash.style.display='none', 4000);

// Type hint
function toggleTypeHint() {
  const t = document.getElementById('promo-type').value;
  document.getElementById('type-hint').textContent = t === 'percent' ? '%' : 'EGP';
}

// Copy code
function copyCode(code) {
  navigator.clipboard.writeText(code).then(() => {
    const btn = event.currentTarget;
    btn.innerHTML = '<i class="ph ph-check"></i>';
    btn.style.color = '#4CAF50';
    setTimeout(() => { btn.innerHTML = '<i class="ph ph-copy"></i>'; btn.style.color = ''; }, 1500);
  });
}

// Delete modal
const delOverlay = document.getElementById('del-modal-overlay');
const delName    = document.getElementById('del-modal-name');
const delId      = document.getElementById('del-modal-id');
document.getElementById('del-modal-cancel').addEventListener('click', () => { delOverlay.style.display='none'; });
delOverlay.addEventListener('click', e => { if (e.target===delOverlay) delOverlay.style.display='none'; });
document.addEventListener('click', e => {
  const t = e.target.closest('.del-trigger');
  if (t) { delId.value=t.dataset.id; delName.textContent=t.dataset.name; delOverlay.style.display='flex'; }
});
document.addEventListener('keydown', e => { if (e.key==='Escape') delOverlay.style.display='none'; });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
