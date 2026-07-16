<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/config/database.php';

$db     = Database::getInstance();
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 25;
$offset = ($page - 1) * $limit;

// ── Build WHERE for search ────────────────────────────────────────
$havingClause = '';
$params       = [];
if ($search) {
    $havingClause = "HAVING (email LIKE :q OR name LIKE :q OR phone LIKE :q)";
    $params[':q'] = "%{$search}%";
}

// ── Count total unique customers from orders ──────────────────────
$countSql = "
    SELECT COUNT(*) FROM (
        SELECT COALESCE(NULLIF(o.customer_email,''), o.customer_phone) AS grp,
               MAX(o.customer_name)  AS name,
               MAX(o.customer_email) AS email,
               MAX(o.customer_phone) AS phone
        FROM orders o
        GROUP BY grp
        {$havingClause}
    ) AS sub
";
$totalStmt = $db->prepare($countSql);
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $limit));

// ── Fetch customers grouped by email (or phone if no email) ───────
$sql = "
    SELECT
        COALESCE(NULLIF(o.customer_email,''), o.customer_phone) AS grp,
        MAX(o.customer_name)    AS name,
        MAX(o.customer_email)   AS email,
        MAX(o.customer_phone)   AS phone,
        COUNT(o.id)             AS order_count,
        COALESCE(SUM(o.total),0) AS total_spent,
        MIN(o.created_at)       AS first_order,
        MAX(o.created_at)       AS last_order,
        MAX(CASE WHEN u.id IS NOT NULL THEN 1 ELSE 0 END) AS has_account
    FROM orders o
    LEFT JOIN users u ON u.email = o.customer_email AND u.role = 'customer'
    GROUP BY grp
    {$havingClause}
    ORDER BY last_order DESC
    LIMIT {$limit} OFFSET {$offset}
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$adminTitle = 'Customers';
require_once __DIR__ . '/includes/header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
  <form method="GET" style="display:flex;gap:10px;align-items:center">
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
           placeholder="Search name, email, phone..." class="admin-input" style="width:300px">
    <button type="submit" class="btn-admin-outline">Search</button>
    <?php if ($search): ?>
    <a href="/admin/customers.php" class="btn-admin-outline">Clear</a>
    <?php endif; ?>
  </form>
  <span style="color:var(--text-muted);font-size:13px">
    <?= number_format($total) ?> customer<?= $total !== 1 ? 's' : '' ?> total
  </span>
</div>

<div class="admin-card" style="padding:0;overflow:hidden">
  <table class="admin-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Customer</th>
        <th>Phone</th>
        <th style="text-align:center">Orders</th>
        <th style="text-align:right">Total Spent</th>
        <th>First Order</th>
        <th>Last Order</th>
        <th style="text-align:center">Account</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($customers)): ?>
      <tr>
        <td colspan="9" style="text-align:center;color:var(--text-muted);padding:60px">
          <?= $search ? 'No customers match your search.' : 'No customers yet — orders will appear here.' ?>
        </td>
      </tr>
      <?php endif; ?>

      <?php foreach ($customers as $i => $c):
        $viewParam = !empty($c['email'])
            ? 'email=' . urlencode($c['email'])
            : 'phone=' . urlencode($c['phone']);
      ?>
      <tr>
        <td style="color:var(--text-muted);font-size:12px"><?= $offset + $i + 1 ?></td>
        <td>
          <div style="font-weight:600;color:#fff"><?= htmlspecialchars($c['name']) ?></div>
          <?php if ($c['email']): ?>
          <div style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($c['email']) ?></div>
          <?php endif; ?>
        </td>
        <td style="color:var(--text-muted);font-size:13px">
          <a href="tel:<?= htmlspecialchars($c['phone']) ?>" style="color:inherit"><?= htmlspecialchars($c['phone']) ?></a>
        </td>
        <td style="text-align:center">
          <span style="font-weight:700;font-size:15px;color:<?= $c['order_count'] >= 3 ? 'var(--accent)' : '#fff' ?>">
            <?= (int)$c['order_count'] ?>
          </span>
        </td>
        <td style="text-align:right;font-weight:700;color:var(--accent)">
          <?= number_format((float)$c['total_spent'], 0) ?> EGP
        </td>
        <td style="color:var(--text-muted);font-size:12px">
          <?= date('d M Y', strtotime($c['first_order'])) ?>
        </td>
        <td style="color:var(--text-muted);font-size:12px">
          <?= date('d M Y', strtotime($c['last_order'])) ?>
        </td>
        <td style="text-align:center">
          <?php if ($c['has_account']): ?>
          <span class="admin-badge badge-success" style="font-size:11px">Registered</span>
          <?php else: ?>
          <span class="admin-badge" style="font-size:11px;background:rgba(255,255,255,0.06);color:#888">Guest</span>
          <?php endif; ?>
        </td>
        <td>
          <a href="/admin/customers/view.php?<?= $viewParam ?>" class="btn-admin-gold" style="font-size:12px;padding:6px 14px">
            View Profile
          </a>
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
  <a href="/admin/customers.php?q=<?= urlencode($search) ?>&page=<?= $p ?>"
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
