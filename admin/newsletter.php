<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/config/database.php';

$db = Database::getInstance();

// Handle CSV export
if (isset($_GET['export'])) {
    $subs = $db->query("SELECT email, subscribed_at FROM newsletter_subscribers ORDER BY subscribed_at DESC")->fetchAll();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="newsletter_subscribers_'.date('Y-m-d').'.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Email', 'Subscribed At']);
    foreach ($subs as $s) {
        fputcsv($out, [$s['email'], $s['subscribed_at']]);
    }
    fclose($out);
    exit;
}

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 30;
$offset = ($page - 1) * $limit;

$total = $db->query("SELECT COUNT(*) FROM newsletter_subscribers")->fetchColumn();
$pages = max(1, (int)ceil($total / $limit));

// Ensure new columns exist
try { $db->exec("ALTER TABLE newsletter_subscribers ADD COLUMN promo_code VARCHAR(30) DEFAULT NULL"); } catch (Throwable $_) {}
try { $db->exec("ALTER TABLE newsletter_subscribers ADD COLUMN email_sent TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $_) {}

$subscribers = $db->prepare("SELECT * FROM newsletter_subscribers ORDER BY subscribed_at DESC LIMIT $limit OFFSET $offset");
$subscribers->execute();
$subscribers = $subscribers->fetchAll();

$adminTitle = 'Newsletter Subscribers';
require_once __DIR__ . '/includes/header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:22px">
  <h1 style="font-size:18px;font-weight:700"><?= number_format((int)$total) ?> Subscribers</h1>
  <a href="/admin/newsletter.php?export=1" class="btn-admin-outline"><i class="ph ph-download-simple"></i> Export CSV</a>
</div>

<div class="admin-card" style="padding:0;overflow:hidden">
  <table class="admin-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Email</th>
        <th>Promo Code</th>
        <th>Email Sent</th>
        <th>Subscribed At</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($subscribers)): ?>
      <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:48px">No subscribers yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($subscribers as $i => $sub): ?>
      <tr>
        <td style="color:var(--text-muted);font-size:12px"><?= $offset + $i + 1 ?></td>
        <td>
          <a href="mailto:<?= htmlspecialchars($sub['email']) ?>" style="color:var(--accent)"><?= htmlspecialchars($sub['email']) ?></a>
        </td>
        <td>
          <?php if (!empty($sub['promo_code'])): ?>
          <span style="font-family:monospace;font-size:13px;font-weight:700;color:var(--accent);letter-spacing:0.06em">
            <?= htmlspecialchars($sub['promo_code']) ?>
          </span>
          <?php else: ?>
          <span style="color:var(--text-muted);font-size:12px">—</span>
          <?php endif; ?>
        </td>
        <td style="text-align:center">
          <?php if (!empty($sub['promo_code'])): ?>
            <?php if ($sub['email_sent'] ?? 0): ?>
            <span class="admin-badge badge-success" style="font-size:11px">✓ Sent</span>
            <?php else: ?>
            <span class="admin-badge" style="font-size:11px;background:rgba(220,53,69,0.15);color:#f66">✗ Failed</span>
            <?php endif; ?>
          <?php else: ?>
          <span style="color:var(--text-muted);font-size:12px">—</span>
          <?php endif; ?>
        </td>
        <td style="color:var(--text-muted);font-size:12px"><?= date('d M Y, H:i', strtotime($sub['subscribed_at'])) ?></td>
        <td>
          <button onclick="confirmDelete('/admin/actions/newsletter_action.php?action=delete&id=<?= $sub['id'] ?>', 'this subscriber')" class="btn-admin-danger">Remove</button>
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
  <a href="/admin/newsletter.php?page=<?= $p ?>"
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
