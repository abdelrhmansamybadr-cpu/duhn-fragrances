<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($adminTitle ?? 'Admin') ?> — DUHN FRAGRANCES Admin</title>
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' fill='%230C0C0C'/%3E%3Crect width='64' height='4' fill='%23F8C417'/%3E%3Ctext x='32' y='48' font-family='Georgia%2Cserif' font-size='40' font-weight='700' fill='%23F8C417' text-anchor='middle'%3ED%3C/text%3E%3C/svg%3E">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/bold/style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <style>
    :root {
      --admin-bg:      #111111;
      --admin-sidebar: #1A1A1A;
      --admin-card:    #1F1F1F;
      --admin-border:  #2A2A2A;
      --accent:        #F8C417;
      --accent-dark:   #E0AA00;
      --text-muted:    #888888;
      --success:       #28A745;
      --danger:        #DC3545;
      --warning:       #FFC107;
    }

    *, *::before, *::after { box-sizing: border-box; }

    body {
      font-family: 'Barlow', sans-serif;
      background: var(--admin-bg);
      color: #fff;
      margin: 0;
    }

    /* Sidebar */
    .admin-sidebar {
      position: fixed;
      top: 0; left: 0;
      width: 240px;
      height: 100vh;
      background: var(--admin-sidebar);
      border-right: 1px solid var(--admin-border);
      display: flex;
      flex-direction: column;
      z-index: 100;
      overflow-y: auto;
    }

    .sidebar-logo {
      padding: 20px 20px 16px;
      border-bottom: 1px solid var(--admin-border);
    }
    .sidebar-logo .brand {
      font-size: 18px;
      font-weight: 700;
      letter-spacing: 0.1em;
      color: #fff;
    }
    .sidebar-logo .brand span { color: var(--accent); }
    .sidebar-logo .sub { font-size: 11px; color: var(--text-muted); letter-spacing: 0.05em; }

    .sidebar-nav { flex: 1; padding: 12px 0; }
    .sidebar-section {
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: var(--text-muted);
      padding: 14px 20px 6px;
    }

    .sidebar-link {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 20px;
      color: #aaa;
      text-decoration: none;
      font-size: 14px;
      font-weight: 500;
      transition: all 0.2s;
      border-left: 3px solid transparent;
    }

    .sidebar-link:hover {
      color: #fff;
      background: rgba(255,255,255,0.04);
    }

    .sidebar-link.active {
      color: var(--accent);
      border-left-color: var(--accent);
      background: rgba(248,196,23,0.08);
      font-weight: 700;
    }

    .sidebar-link i { font-size: 18px; }

    .sidebar-bottom {
      padding: 16px 20px;
      border-top: 1px solid var(--admin-border);
    }

    /* Main */
    .admin-main {
      margin-left: 240px;
      min-height: 100vh;
    }

    .admin-topbar {
      position: sticky;
      top: 0;
      z-index: 50;
      background: var(--admin-card);
      border-bottom: 1px solid var(--admin-border);
      padding: 14px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .admin-topbar h1 {
      font-size: 18px;
      font-weight: 700;
      margin: 0;
    }

    .admin-content { padding: 28px 24px; }

    /* Cards */
    .admin-card {
      background: var(--admin-card);
      border: 1px solid var(--admin-border);
      border-radius: 10px;
      padding: 20px;
    }

    .stat-card {
      background: var(--admin-card);
      border: 1px solid var(--admin-border);
      border-radius: 10px;
      padding: 20px 24px;
    }

    .stat-card .stat-label {
      font-size: 12px;
      font-weight: 600;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--text-muted);
      margin-bottom: 8px;
    }

    .stat-card .stat-value {
      font-size: 28px;
      font-weight: 700;
      color: var(--accent);
    }

    .stat-card .stat-icon {
      font-size: 32px;
      opacity: 0.2;
    }

    /* Tables */
    .admin-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
    }

    .admin-table th {
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--text-muted);
      padding: 10px 14px;
      border-bottom: 1px solid var(--admin-border);
      text-align: left;
      background: rgba(255,255,255,0.02);
    }

    .admin-table td {
      padding: 12px 14px;
      border-bottom: 1px solid var(--admin-border);
      vertical-align: middle;
    }

    .admin-table tr:hover td { background: rgba(255,255,255,0.02); }

    /* Product thumb */
    .product-thumb {
      width: 44px;
      height: 44px;
      border-radius: 6px;
      object-fit: cover;
      background: #f0f0f0;
    }

    /* Badges */
    .badge-gold    { background: rgba(248,196,23,0.15); color: var(--accent); border: 1px solid rgba(248,196,23,0.3); }
    .badge-success { background: rgba(40,167,69,0.15);  color: var(--success); border: 1px solid rgba(40,167,69,0.3); }
    .badge-danger  { background: rgba(220,53,69,0.15);  color: var(--danger); border: 1px solid rgba(220,53,69,0.3); }
    .badge-muted   { background: rgba(255,255,255,0.07); color: var(--text-muted); border: 1px solid var(--admin-border); }

    .admin-badge {
      display: inline-block;
      font-size: 11px;
      font-weight: 600;
      padding: 3px 10px;
      border-radius: 20px;
    }

    /* Forms */
    .admin-form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 20px; }
    .admin-label { font-size: 12px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--text-muted); }
    .admin-input {
      background: #111;
      border: 1px solid var(--admin-border);
      border-radius: 6px;
      padding: 10px 14px;
      color: #fff;
      font-size: 14px;
      font-family: 'Barlow', sans-serif;
      transition: border-color 0.2s;
      width: 100%;
    }
    .admin-input:focus { border-color: var(--accent); outline: none; }
    .admin-input::placeholder { color: var(--text-muted); }

    select.admin-input { appearance: none; }
    textarea.admin-input { resize: vertical; }

    .btn-admin-gold {
      background: var(--accent);
      color: #000;
      border: none;
      padding: 10px 22px;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 700;
      letter-spacing: 0.06em;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: background 0.2s;
    }
    .btn-admin-gold:hover { background: var(--accent-dark); color: #000; }

    .btn-admin-danger {
      background: rgba(220,53,69,0.15);
      color: var(--danger);
      border: 1px solid rgba(220,53,69,0.3);
      padding: 6px 14px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
    }
    .btn-admin-danger:hover { background: var(--danger); color: #fff; }

    .btn-admin-outline {
      background: transparent;
      color: #aaa;
      border: 1px solid var(--admin-border);
      padding: 6px 14px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      transition: all 0.2s;
    }
    .btn-admin-outline:hover { border-color: #fff; color: #fff; }

    /* Alert */
    .admin-alert {
      padding: 12px 16px;
      border-radius: 8px;
      font-size: 13px;
      margin-bottom: 20px;
    }
    .admin-alert.success { background: rgba(40,167,69,0.12); border: 1px solid rgba(40,167,69,0.3); color: var(--success); }
    .admin-alert.error   { background: rgba(220,53,69,0.12);  border: 1px solid rgba(220,53,69,0.3);  color: var(--danger); }

    /* Toggle */
    .toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    .toggle-slider {
      position: absolute; inset: 0; cursor: pointer;
      background: var(--admin-border); border-radius: 24px; transition: 0.3s;
    }
    .toggle-slider::before {
      content: ''; position: absolute;
      height: 18px; width: 18px; left: 3px; bottom: 3px;
      background: #fff; border-radius: 50%; transition: 0.3s;
    }
    input:checked + .toggle-slider { background: var(--accent); }
    input:checked + .toggle-slider::before { transform: translateX(20px); }
  </style>
</head>
<body>

<!-- Sidebar -->
<aside class="admin-sidebar">
  <div class="sidebar-logo">
    <div class="brand">DUHN <span>FRAGRANCES</span></div>
    <div class="sub">Admin Dashboard</div>
  </div>

  <nav class="sidebar-nav">
    <div class="sidebar-section">Overview</div>
    <a href="/admin/index.php"         class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' && strpos($_SERVER['PHP_SELF'], 'admin') !== false ? 'active' : '' ?>">
      <i class="ph ph-squares-four"></i> Dashboard
    </a>

    <div class="sidebar-section">Catalog</div>
    <a href="/admin/products.php"      class="sidebar-link <?= strpos($_SERVER['PHP_SELF'], 'products') !== false ? 'active' : '' ?>">
      <i class="ph ph-flask"></i> Products
    </a>
    <a href="/admin/collections.php"   class="sidebar-link <?= strpos($_SERVER['PHP_SELF'], 'collections') !== false && strpos($_SERVER['PHP_SELF'], 'admin') !== false ? 'active' : '' ?>">
      <i class="ph ph-tag"></i> Collections
    </a>
    <a href="/admin/offers.php" class="sidebar-link <?= strpos($_SERVER['PHP_SELF'], 'offers') !== false ? 'active' : '' ?>">
      <i class="ph ph-gift"></i> Offers & Deals
    </a>
    <a href="/admin/promo-codes.php" class="sidebar-link <?= strpos($_SERVER['PHP_SELF'], 'promo-codes') !== false ? 'active' : '' ?>">
      <i class="ph ph-ticket"></i> Promo Codes
    </a>

    <div class="sidebar-section">Orders</div>
    <a href="/admin/orders.php"        class="sidebar-link <?= strpos($_SERVER['PHP_SELF'], 'orders') !== false && strpos($_SERVER['PHP_SELF'], 'admin') !== false ? 'active' : '' ?>">
      <i class="ph ph-package"></i> Orders
    </a>
    <a href="/admin/customers.php"     class="sidebar-link <?= strpos($_SERVER['PHP_SELF'], 'customers') !== false ? 'active' : '' ?>">
      <i class="ph ph-users"></i> Customers
    </a>

    <div class="sidebar-section">Content</div>
    <a href="/admin/homepage-sections.php" class="sidebar-link <?= strpos($_SERVER['PHP_SELF'], 'homepage-sections') !== false ? 'active' : '' ?>">
      <i class="ph ph-layout"></i> Homepage Sections
    </a>
    <a href="/admin/pages.php" class="sidebar-link <?= strpos($_SERVER['PHP_SELF'], 'admin/pages') !== false ? 'active' : '' ?>">
      <i class="ph ph-file-text"></i> Page Editor
    </a>
    <a href="/admin/reviews.php"       class="sidebar-link <?= strpos($_SERVER['PHP_SELF'], 'reviews') !== false ? 'active' : '' ?>">
      <i class="ph ph-star"></i> Reviews
    </a>
    <a href="/admin/contact.php"       class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'contact.php' && strpos($_SERVER['PHP_SELF'], 'admin') !== false ? 'active' : '' ?>">
      <i class="ph ph-envelope"></i> Messages
    </a>
    <a href="/admin/newsletter.php"    class="sidebar-link <?= strpos($_SERVER['PHP_SELF'], 'newsletter') !== false ? 'active' : '' ?>">
      <i class="ph ph-bell"></i> Newsletter
    </a>
    <a href="/admin/abandoned-carts.php" class="sidebar-link <?= strpos($_SERVER['PHP_SELF'], 'abandoned-carts') !== false ? 'active' : '' ?>">
      <i class="ph ph-shopping-cart-simple"></i> Abandoned Carts
    </a>

    <div class="sidebar-section">Config</div>
    <a href="/admin/settings.php"      class="sidebar-link <?= strpos($_SERVER['PHP_SELF'], 'settings') !== false ? 'active' : '' ?>">
      <i class="ph ph-gear"></i> Settings
    </a>
  </nav>

  <div class="sidebar-bottom">
    <a href="/index.php" target="_blank" class="sidebar-link" style="font-size:12px;color:var(--text-muted)">
      <i class="ph ph-arrow-square-out"></i> View Website
    </a>
    <a href="/admin/logout.php" class="sidebar-link" style="font-size:12px;color:var(--danger)">
      <i class="ph ph-sign-out"></i> Logout
    </a>
  </div>
</aside>

<!-- Main -->
<main class="admin-main">
  <div class="admin-topbar">
    <h1><?= htmlspecialchars($adminTitle ?? 'Dashboard') ?></h1>
    <div style="display:flex;align-items:center;gap:12px">
      <!-- Browser notification bell -->
      <button id="notif-bell" onclick="requestNotifPermission()"
              title="Enable browser notifications"
              style="background:none;border:none;cursor:pointer;color:#888;font-size:20px;padding:4px;position:relative;display:flex;align-items:center">
        <i class="ph ph-bell" id="bell-icon"></i>
        <span id="notif-dot" style="display:none;position:absolute;top:2px;right:2px;width:8px;height:8px;border-radius:50%;background:#ef4444;border:1.5px solid var(--admin-card)"></span>
      </button>
      <span style="font-size:13px;color:var(--text-muted)">
        Welcome, <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?>
      </span>
    </div>
  </div>
  <div class="admin-content">

<script>
/* ── Admin Browser Notifications — Order Polling ─────────────── */
(function () {
  const POLL_INTERVAL = 30000; // 30 seconds
  const STORE_KEY     = 'duhn_last_order_count';
  const bell          = document.getElementById('bell-icon');
  const dot           = document.getElementById('notif-dot');

  // Restore saved count
  let lastCount = parseInt(localStorage.getItem(STORE_KEY) || '-1', 10);

  function updateBellState(granted) {
    if (granted) {
      bell.className = 'ph ph-bell-ringing';
      bell.parentElement.style.color = 'var(--accent)';
      bell.parentElement.title = 'Browser notifications ON';
    } else {
      bell.className = 'ph ph-bell-slash';
      bell.parentElement.style.color = '#555';
      bell.parentElement.title = 'Click to enable notifications';
    }
  }

  function showNotification(title, body, url) {
    if (Notification.permission !== 'granted') return;
    const n = new Notification(title, {
      body:  body,
      icon:  '/public/images/favicon.svg',
      badge: '/public/images/favicon.svg',
      tag:   'duhn-order',
      requireInteraction: true,
    });
    n.onclick = () => { window.focus(); if (url) window.location.href = url; n.close(); };
    // Flash tab title
    let orig = document.title, i = 0;
    const flash = setInterval(() => {
      document.title = i++ % 2 === 0 ? '🛍 NEW ORDER!' : orig;
    }, 800);
    setTimeout(() => { clearInterval(flash); document.title = orig; }, 12000);
  }

  async function poll() {
    try {
      const res  = await fetch('/admin/actions/orders_poll.php', { cache: 'no-store' });
      if (!res.ok) return;
      const json = await res.json();
      const count = json.count ?? 0;

      if (lastCount === -1) {
        // First poll — just initialise baseline, no notification
        lastCount = count;
        localStorage.setItem(STORE_KEY, count);
        return;
      }

      if (count > lastCount) {
        const diff   = count - lastCount;
        const latest = json.latest;
        const title  = diff === 1
          ? `🛍 New Order — ${latest?.customer_name ?? ''}`
          : `🛍 ${diff} New Orders!`;
        const body = latest
          ? `#${latest.order_number} · ${Number(latest.total).toLocaleString('en-US')} EGP`
          : `${diff} pending order(s) waiting`;

        showNotification(title, body, '/admin/orders.php');

        // Show red dot on bell
        dot.style.display = 'block';

        lastCount = count;
        localStorage.setItem(STORE_KEY, count);
      }
    } catch (_) {}
  }

  let pollTimer = null;

  function startPolling() {
    if (pollTimer) return; // already running — don't duplicate
    poll(); // immediate first check
    pollTimer = setInterval(poll, POLL_INTERVAL);
  }

  async function requestNotifPermission() {
    if (!('Notification' in window)) {
      alert('Your browser does not support desktop notifications.');
      return;
    }

    // If already granted — just confirm and start polling
    if (Notification.permission === 'granted') {
      updateBellState(true);
      dot.style.display = 'none';
      startPolling();
      return;
    }

    // If already denied — can't re-ask, guide the user
    if (Notification.permission === 'denied') {
      alert('Notifications are blocked.\n\nTo enable:\n1. Click the lock icon (🔒) in your browser address bar\n2. Find "Notifications" → set to Allow\n3. Reload this page');
      return;
    }

    // Ask for permission
    const perm = await Notification.requestPermission();
    updateBellState(perm === 'granted');

    if (perm === 'granted') {
      dot.style.display = 'none';
      startPolling(); // ← start the repeating interval NOW
    } else {
      alert('Notifications blocked. You can enable them via the address bar lock icon.');
    }
  }

  // Init on page load
  if ('Notification' in window) {
    updateBellState(Notification.permission === 'granted');
    if (Notification.permission === 'granted') {
      startPolling(); // already had permission — start immediately
    }
  }

  // Clear dot + reset baseline when admin visits orders page
  if (window.location.pathname.includes('/orders')) {
    dot.style.display = 'none';
    lastCount = -1;
    localStorage.setItem(STORE_KEY, -1);
  }
})();
</script>
