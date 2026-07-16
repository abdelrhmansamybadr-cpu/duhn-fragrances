<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/config/database.php';

$db = Database::getInstance();

try {
    $offers = $db->query("
        SELECT o.*,
               tp.name AS trigger_name, tp.slug AS trigger_slug,
               fp.name AS free_name,    fp.slug AS free_slug
        FROM offers o
        JOIN products tp ON tp.id = o.trigger_product_id
        JOIN products fp ON fp.id = o.free_product_id
        ORDER BY o.created_at DESC
    ")->fetchAll();
} catch (Throwable $e) {
    $offers = [];
}

$adminTitle = 'Offers & Deals';
require_once __DIR__ . '/includes/header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
  <div>
    <p style="font-size:13px;color:var(--text-muted);margin-top:4px">Product-level offers: BOGO, bundle deals, free gifts.</p>
  </div>
  <a href="/admin/offers/add.php" class="btn-admin-gold" style="display:inline-flex;align-items:center;gap:6px">
    <i class="ph ph-plus"></i> New Offer
  </a>
</div>

<?php if (isset($_GET['saved'])): ?>
<div class="admin-alert success" style="margin-bottom:16px">Offer saved successfully.</div>
<?php endif; ?>
<?php if (isset($_GET['deleted'])): ?>
<div class="admin-alert success" style="margin-bottom:16px">Offer deleted.</div>
<?php endif; ?>

<?php if (empty($offers)): ?>
<div class="admin-card" style="text-align:center;padding:60px 24px;color:var(--text-muted)">
  <i class="ph ph-tag" style="font-size:48px;display:block;margin-bottom:12px;opacity:0.4"></i>
  <p style="font-size:15px;margin-bottom:4px">No offers yet.</p>
  <p style="font-size:13px;margin-bottom:20px">Create your first deal — e.g. buy Carnation, get Euphoria free.</p>
  <a href="/admin/offers/add.php" class="btn-admin-gold">Create First Offer</a>
</div>
<?php else: ?>
<div class="admin-card" style="padding:0;overflow:hidden">
  <table class="admin-table" style="margin:0">
    <thead>
      <tr>
        <th>Offer Name</th>
        <th>When Customer Buys</th>
        <th>They Get Free</th>
        <th>Badge</th>
        <th>Period</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($offers as $offer): ?>
      <tr>
        <td>
          <div style="font-weight:700;font-size:14px"><?= htmlspecialchars($offer['name']) ?></div>
          <div style="font-size:11px;color:var(--text-muted);margin-top:2px">
            <?= $offer['offer_type'] === 'bogo' ? 'BOGO' : 'Bundle Deal' ?>
          </div>
        </td>
        <td>
          <div style="font-size:13px"><?= htmlspecialchars($offer['trigger_name']) ?></div>
          <div style="font-size:11px;color:var(--text-muted)">Qty: <?= (int)$offer['trigger_qty'] ?></div>
        </td>
        <td>
          <div style="font-size:13px;color:var(--success)"><?= htmlspecialchars($offer['free_name']) ?></div>
          <div style="font-size:11px;color:var(--text-muted)">Qty: <?= (int)$offer['free_qty'] ?> × FREE</div>
        </td>
        <td>
          <span style="background:var(--accent);color:#1A1A1A;font-size:10px;font-weight:700;padding:3px 8px;border-radius:4px;letter-spacing:0.05em;white-space:nowrap">
            <?= htmlspecialchars($offer['badge_text']) ?>
          </span>
        </td>
        <td style="font-size:12px;color:var(--text-muted)">
          <?php if ($offer['starts_at'] || $offer['ends_at']): ?>
            <?= $offer['starts_at'] ? date('d M Y', strtotime($offer['starts_at'])) : '∞' ?>
            →
            <?= $offer['ends_at']   ? date('d M Y', strtotime($offer['ends_at']))   : '∞' ?>
          <?php else: ?>
            Always active
          <?php endif; ?>
        </td>
        <td>
          <?php
          $now    = date('Y-m-d H:i:s');
          $active = $offer['is_active']
                    && (!$offer['starts_at'] || $offer['starts_at'] <= $now)
                    && (!$offer['ends_at']   || $offer['ends_at']   >= $now);
          ?>
          <span style="display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:700;color:<?= $active ? 'var(--success)' : 'var(--text-muted)' ?>">
            <i class="ph ph-<?= $active ? 'check-circle' : 'pause-circle' ?>"></i>
            <?= $active ? 'Active' : 'Inactive' ?>
          </span>
        </td>
        <td>
          <div style="display:flex;gap:6px">
            <a href="/admin/offers/edit.php?id=<?= $offer['id'] ?>" class="btn-admin-outline" style="font-size:12px;padding:6px 12px">
              <i class="ph ph-pencil"></i> Edit
            </a>
            <a href="/admin/actions/offer_delete.php?id=<?= $offer['id'] ?>"
               onclick="return confirm('Delete this offer?')"
               style="display:inline-flex;align-items:center;gap:4px;padding:6px 12px;border:1px solid var(--error);border-radius:6px;font-size:12px;color:var(--error);font-weight:600;cursor:pointer;text-decoration:none">
              <i class="ph ph-trash"></i>
            </a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
