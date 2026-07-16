<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/config/database.php';

$db     = Database::getInstance();
$filter = $_GET['filter'] ?? 'unread';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

$where = '';
if ($filter === 'unread') $where = 'WHERE is_read = 0';
elseif ($filter === 'read') $where = 'WHERE is_read = 1';

$total = $db->query("SELECT COUNT(*) FROM contact_messages $where")->fetchColumn();
$pages = max(1, (int)ceil($total / $limit));

$messages = $db->prepare("
    SELECT * FROM contact_messages $where
    ORDER BY created_at DESC
    LIMIT $limit OFFSET $offset
");
$messages->execute();
$messages = $messages->fetchAll();

$counts = [
    'all'    => $db->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn(),
    'unread' => $db->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn(),
    'read'   => $db->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=1")->fetchColumn(),
];

$adminTitle = 'Contact Messages';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Filter Tabs -->
<div style="display:flex;gap:6px;margin-bottom:20px">
  <?php foreach (['all' => 'All', 'unread' => 'Unread', 'read' => 'Read'] as $val => $label):
    $active = $filter === $val;
  ?>
  <a href="/admin/contact.php?filter=<?= $val ?>"
     style="padding:7px 16px;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;
            background:<?= $active ? 'var(--accent)' : 'var(--admin-card)' ?>;
            color:<?= $active ? '#000' : 'var(--text-muted)' ?>;
            border:1px solid <?= $active ? 'var(--accent)' : 'var(--admin-border)' ?>">
    <?= $label ?> <span style="opacity:.6">(<?= $counts[$val] ?>)</span>
  </a>
  <?php endforeach; ?>
</div>

<?php if (empty($messages)): ?>
<div class="admin-card" style="text-align:center;padding:64px;color:var(--text-muted)">
  <i class="ph ph-envelope-open" style="font-size:48px;margin-bottom:12px;display:block;opacity:.4"></i>
  No messages found.
</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:12px">
  <?php foreach ($messages as $msg): ?>
  <div class="admin-card" style="<?= !$msg['is_read'] ? 'border-left:3px solid var(--accent)' : 'border-left:3px solid transparent' ?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
      <div>
        <div style="display:flex;align-items:center;gap:10px">
          <span style="font-weight:700;font-size:15px"><?= htmlspecialchars($msg['name']) ?></span>
          <?php if (!$msg['is_read']): ?>
          <span class="admin-badge badge-gold" style="font-size:10px;padding:2px 8px">NEW</span>
          <?php endif; ?>
        </div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:2px">
          <a href="mailto:<?= htmlspecialchars($msg['email']) ?>" style="color:var(--accent)"><?= htmlspecialchars($msg['email']) ?></a>
          · <?= date('d M Y, H:i', strtotime($msg['created_at'])) ?>
        </div>
      </div>
      <div style="display:flex;gap:6px">
        <?php if (!$msg['is_read']): ?>
        <a href="/admin/actions/contact_action.php?action=read&id=<?= $msg['id'] ?>&back=<?= urlencode($_SERVER['REQUEST_URI']) ?>"
           class="btn-admin-outline" style="font-size:12px">Mark Read</a>
        <?php endif; ?>
        <a href="mailto:<?= htmlspecialchars($msg['email']) ?>?subject=Re: Your message to DUHN FRAGRANCES"
           class="btn-admin-outline" style="font-size:12px"><i class="ph ph-paper-plane-tilt"></i> Reply</a>
        <button onclick="confirmDelete('/admin/actions/contact_action.php?action=delete&id=<?= $msg['id'] ?>', 'this message')" class="btn-admin-danger" style="font-size:12px">Delete</button>
      </div>
    </div>
    <p style="font-size:14px;color:#d0d0d0;line-height:1.7;white-space:pre-wrap;margin:0;border-top:1px solid var(--admin-border);padding-top:10px"><?= htmlspecialchars($msg['message']) ?></p>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Pagination -->
<?php if ($pages > 1): ?>
<div style="display:flex;justify-content:center;gap:6px;margin-top:20px">
  <?php for ($p = 1; $p <= $pages; $p++): ?>
  <a href="/admin/contact.php?filter=<?= $filter ?>&page=<?= $p ?>"
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
