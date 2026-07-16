<?php
/**
 * DUHN FRAGRANCES — My Account Page
 */
require_once __DIR__ . '/api/config/config.php';
require_once __DIR__ . '/api/config/database.php';

$pageTitle = 'My Account — DUHN FRAGRANCES';
$pageDesc  = 'Sign in or create an account to track your orders.';
require_once __DIR__ . '/public/layout/header.php';
?>

<style>
/* ── Account page — works on light (#fff) body ─────────────────── */
.account-wrap {
  max-width: 900px;
  margin: 0 auto;
  padding: 56px 24px 80px;
}

/* Auth cards */
.auth-card {
  background: #fff;
  border: 1.5px solid #E0E0E0;
  border-radius: 16px;
  padding: 36px 32px;
  box-shadow: 0 4px 24px rgba(0,0,0,0.08);
}

.auth-card h2 {
  font-size: 22px;
  font-weight: 700;
  color: #1A1A1A;
  margin-bottom: 24px;
}

.auth-field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }

.auth-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #888;
}

/* Input fields — strong values to override any cascade */
.auth-inp {
  display: block !important;
  width: 100% !important;
  padding: 13px 16px !important;
  background: #FFFFFF !important;
  border: 2px solid #AAAAAA !important;
  border-radius: 8px !important;
  color: #111111 !important;
  font-family: 'Barlow', sans-serif !important;
  font-size: 15px !important;
  outline: none !important;
  box-sizing: border-box !important;
  transition: border-color .2s !important;
  -webkit-appearance: none !important;
  appearance: none !important;
}
.auth-inp:focus {
  border-color: #F8C417 !important;
  box-shadow: 0 0 0 3px rgba(248,196,23,0.18) !important;
  outline: none !important;
}
.auth-inp::placeholder { color: #AAAAAA !important; }
.auth-inp[readonly]    { background: #F0F0F0 !important; color: #888 !important; cursor: default !important; }

.auth-err {
  display: none;
  background: #FFF3F3;
  border: 1px solid #FFCDD2;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 13px;
  color: #D32F2F;
  margin-bottom: 14px;
  line-height: 1.5;
}

.auth-succ {
  display: none;
  background: #F0FFF4;
  border: 1px solid #C6F6D5;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 13px;
  color: #276749;
  margin-bottom: 14px;
}

.auth-note {
  background: rgba(248,196,23,0.1);
  border: 1px solid rgba(248,196,23,0.3);
  border-radius: 8px;
  padding: 12px 14px;
  font-size: 13px;
  color: #7A5C00;
  margin-bottom: 16px;
  line-height: 1.5;
}

/* Tabs */
.account-tabs {
  display: flex;
  gap: 0;
  border-bottom: 2px solid #F0F0F0;
  margin-bottom: 28px;
}
.account-tab {
  padding: 11px 22px;
  font-size: 14px;
  font-weight: 600;
  color: #888;
  cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  transition: .2s;
  background: none;
  border-top: none;
  border-left: none;
  border-right: none;
}
.account-tab.active { color: #1A1A1A; border-bottom-color: #F8C417; }
.account-tab:hover  { color: #1A1A1A; }
.tab-pane           { display: none; }
.tab-pane.active    { display: block; }

/* Order card */
.order-card {
  background: #FAFAFA;
  border: 1px solid #EBEBEB;
  border-radius: 10px;
  padding: 18px 22px;
  margin-bottom: 10px;
}
.order-card:hover { border-color: #F8C417; }

.status-pending   { color: #B8860B; }
.status-confirmed { color: #555; }
.status-shipped   { color: #E08000; }
.status-delivered { color: #22863A; }
.status-cancelled { color: #C0392B; }
</style>

<div class="account-wrap">

  <!-- ══════════════════════════════════════════════════════════════ -->
  <!--  GUEST VIEW                                                    -->
  <!-- ══════════════════════════════════════════════════════════════ -->
  <div id="guest-view" style="display:none">
    <h1 style="font-size:30px;font-weight:700;color:#1A1A1A;margin-bottom:6px">My Account</h1>
    <p style="color:#888888;margin-bottom:40px;text-decoration:none">Sign in to view your orders and manage your profile.</p>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;max-width:780px">

      <!-- ── Sign In ─────────────────────────────────────────────── -->
      <div class="auth-card" id="login-card">
        <h2>Sign In</h2>
        <div class="auth-err" id="login-error"></div>

        <!-- Normal sign-in form -->
        <div id="login-form">
          <div class="auth-field">
            <label class="auth-label">Email</label>
            <input type="email" id="login-email" class="auth-inp" placeholder="your@email.com" autocomplete="email">
          </div>
          <div class="auth-field">
            <label class="auth-label">Password</label>
            <input type="password" id="login-pw" class="auth-inp" placeholder="••••••••" autocomplete="current-password">
          </div>
          <button onclick="doLogin()" class="btn btn-gold btn-full" style="margin-top:4px">Sign In</button>
        </div>

        <!-- Set password form (shown when account has no password yet) -->
        <div id="set-pwd-form" style="display:none">
          <div class="auth-note">
            🔑 Your account was created when you placed an order.<br>
            <strong>Set a password below to sign in.</strong>
          </div>
          <div class="auth-err" id="set-pwd-error"></div>
          <div class="auth-field">
            <label class="auth-label">Email</label>
            <input type="email" id="set-pwd-email" class="auth-inp" readonly>
          </div>
          <div class="auth-field">
            <label class="auth-label">Choose Your Password</label>
            <input type="password" id="set-pwd-pw" class="auth-inp" placeholder="Minimum 8 characters" autocomplete="new-password">
          </div>
          <div class="auth-field">
            <label class="auth-label">Confirm Password</label>
            <input type="password" id="set-pwd-confirm" class="auth-inp" placeholder="Repeat your password" autocomplete="new-password">
          </div>
          <button onclick="doFirstLogin()" class="btn btn-gold btn-full" style="margin-top:4px">
            <i class="ph ph-lock-key"></i> Set Password & Sign In
          </button>
          <p style="font-size:13px;color:#888;text-align:center;margin-top:12px">
            <button onclick="backToLogin()" style="background:none;border:none;color:#888;cursor:pointer;font-size:13px;text-decoration:underline">← Back to Sign In</button>
          </p>
        </div>

        <p style="font-size:13px;color:#888;text-align:center;margin-top:16px">
          Don't have an account?
          <button onclick="showRegister()" style="background:none;border:none;color:#F8C417;cursor:pointer;font-weight:700;font-size:13px;padding:0">Register</button>
        </p>
      </div>

      <!-- ── Register ────────────────────────────────────────────── -->
      <div class="auth-card" id="register-card" style="display:none">
        <h2>Create Account</h2>
        <div class="auth-err" id="reg-error"></div>
        <div class="auth-field">
          <label class="auth-label">Full Name</label>
          <input type="text" id="reg-name" class="auth-inp" placeholder="Mohamed Ahmed" autocomplete="name">
        </div>
        <div class="auth-field">
          <label class="auth-label">Phone</label>
          <input type="tel" id="reg-phone" class="auth-inp" placeholder="01xxxxxxxxx" autocomplete="tel">
        </div>
        <div class="auth-field">
          <label class="auth-label">Email</label>
          <input type="email" id="reg-email" class="auth-inp" placeholder="your@email.com" autocomplete="email">
        </div>
        <div class="auth-field">
          <label class="auth-label">Password</label>
          <input type="password" id="reg-pw" class="auth-inp" placeholder="Minimum 8 characters" autocomplete="new-password">
        </div>
        <button onclick="doRegister()" class="btn btn-gold btn-full" style="margin-top:4px">Create Account</button>
        <p style="font-size:13px;color:#888;text-align:center;margin-top:16px">
          Already have an account?
          <button onclick="showLogin()" style="background:none;border:none;color:#F8C417;cursor:pointer;font-weight:700;font-size:13px;padding:0">Sign In</button>
        </p>
      </div>

    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════════════ -->
  <!--  LOGGED-IN VIEW                                               -->
  <!-- ══════════════════════════════════════════════════════════════ -->
  <div id="auth-view" style="display:none">

    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:32px;flex-wrap:wrap;gap:12px">
      <div>
        <h1 style="font-size:28px;font-weight:700;color:#1A1A1A;margin-bottom:4px" id="welcome-name">Welcome back</h1>
        <p style="color:#888;font-size:14px" id="welcome-email"></p>
      </div>
      <button onclick="doLogout()" class="btn btn-outline">Sign Out</button>
    </div>

    <div class="account-tabs">
      <button class="account-tab active" onclick="switchTab('orders')">My Orders</button>
      <button class="account-tab" onclick="switchTab('profile')">Profile</button>
      <button class="account-tab" onclick="switchTab('password')">Change Password</button>
    </div>

    <!-- Orders tab -->
    <div class="tab-pane active" id="tab-orders">
      <div id="orders-loading" style="text-align:center;padding:56px;color:#888">
        <i class="ph ph-spinner-gap" style="font-size:36px;display:block;margin-bottom:12px;opacity:.4"></i>
        Loading your orders...
      </div>
      <div id="orders-list"></div>
    </div>

    <!-- Profile tab -->
    <div class="tab-pane" id="tab-profile">
      <div style="max-width:460px">
        <div class="auth-succ" id="profile-success"></div>
        <div class="auth-err"  id="profile-error"></div>
        <div class="auth-field">
          <label class="auth-label">Full Name</label>
          <input type="text" id="p-name" class="auth-inp">
        </div>
        <div class="auth-field">
          <label class="auth-label">Phone</label>
          <input type="tel" id="p-phone" class="auth-inp">
        </div>
        <div class="auth-field">
          <label class="auth-label">Email</label>
          <input type="email" id="p-email" class="auth-inp" readonly>
        </div>
        <button onclick="saveProfile()" class="btn btn-gold">Save Changes</button>
      </div>
    </div>

    <!-- Change password tab -->
    <div class="tab-pane" id="tab-password">
      <div style="max-width:460px">
        <div class="auth-succ" id="pwd-success"></div>
        <div class="auth-err"  id="pwd-error"></div>
        <div id="pwd-locked-note" style="display:none" class="auth-note">
          🔑 You haven't set a password yet. Set one below to sign in next time.
        </div>
        <div class="auth-field">
          <label class="auth-label">New Password</label>
          <input type="password" id="new-pw" class="auth-inp" placeholder="Minimum 8 characters" autocomplete="new-password">
        </div>
        <div class="auth-field">
          <label class="auth-label">Confirm Password</label>
          <input type="password" id="new-pw2" class="auth-inp" placeholder="Repeat password" autocomplete="new-password">
        </div>
        <button onclick="changePassword()" class="btn btn-gold">
          <i class="ph ph-lock-key"></i> Update Password
        </button>
      </div>
    </div>

  </div>
</div>

<?php
$extraScripts = <<<'JS'
<script>
const TK = 'duhn_token';
const UK = 'duhn_user';

const getToken = () => localStorage.getItem(TK);
const getUser  = () => { try { return JSON.parse(localStorage.getItem(UK)); } catch { return null; } };

/* ── Page init ──────────────────────────────────────────────────── */
function initAccountPage() {
  const token = getToken();
  const user  = getUser();

  if (token && user) {
    document.getElementById('guest-view').style.display = 'none';
    document.getElementById('auth-view').style.display  = 'block';
    document.getElementById('welcome-name').textContent  = 'Welcome, ' + (user.name || 'Customer');
    document.getElementById('welcome-email').textContent = user.email || '';
    document.getElementById('p-name').value  = user.name  || '';
    document.getElementById('p-phone').value = user.phone || '';
    document.getElementById('p-email').value = user.email || '';

    // If account was just auto-created (from checkout), prompt password change
    if (sessionStorage.getItem('duhn_new_account_email')) {
      document.getElementById('pwd-locked-note').style.display = 'block';
      switchTab('password');
      sessionStorage.removeItem('duhn_new_account_email');
    }

    loadOrders();
  } else {
    document.getElementById('guest-view').style.display = 'block';
    document.getElementById('auth-view').style.display  = 'none';
  }
}

/* ── Auth card switching ─────────────────────────────────────────── */
function showRegister() {
  document.getElementById('login-card').style.display    = 'none';
  document.getElementById('register-card').style.display = 'block';
}
function showLogin() {
  document.getElementById('register-card').style.display = 'none';
  document.getElementById('login-card').style.display    = 'block';
}
function backToLogin() {
  document.getElementById('set-pwd-form').style.display = 'none';
  document.getElementById('login-form').style.display   = 'block';
  document.getElementById('login-error').style.display  = 'none';
}

/* ── Tab switching ───────────────────────────────────────────────── */
function switchTab(name) {
  document.querySelectorAll('.account-tab').forEach(t  => t.classList.remove('active'));
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  const tabs = ['orders', 'profile', 'password'];
  const idx  = tabs.indexOf(name);
  const btns = document.querySelectorAll('.account-tab');
  if (btns[idx]) btns[idx].classList.add('active');
}

/* ── Login ───────────────────────────────────────────────────────── */
async function doLogin() {
  const email = document.getElementById('login-email').value.trim();
  const pw    = document.getElementById('login-pw').value;
  const err   = document.getElementById('login-error');
  err.style.display = 'none';

  if (!email) { err.textContent = 'Please enter your email.'; err.style.display = 'block'; return; }
  if (!pw)    { err.textContent = 'Please enter your password.'; err.style.display = 'block'; return; }

  try {
    const res  = await fetch('/api/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password: pw }),
    });
    const data = await res.json();

    if (data.success) {
      localStorage.setItem(TK, data.data.token);
      localStorage.setItem(UK, JSON.stringify(data.data.user));
      initAccountPage();
      return;
    }

    // Account exists but no password set yet → show Set Password form
    if (data.code === 'PASSWORD_NOT_SET') {
      document.getElementById('login-form').style.display   = 'none';
      document.getElementById('set-pwd-form').style.display = 'block';
      document.getElementById('set-pwd-email').value = email;
      document.getElementById('login-error').style.display  = 'none';
      return;
    }

    err.textContent = data.message || 'Incorrect email or password.';
    err.style.display = 'block';

  } catch {
    err.textContent = 'Connection error. Please try again.';
    err.style.display = 'block';
  }
}

/* ── First login (set password for auto-created account) ─────────── */
async function doFirstLogin() {
  const email   = document.getElementById('set-pwd-email').value.trim();
  const pw      = document.getElementById('set-pwd-pw').value;
  const confirm = document.getElementById('set-pwd-confirm').value;
  const err     = document.getElementById('set-pwd-error');
  err.style.display = 'none';

  if (pw.length < 8) { err.textContent = 'Password must be at least 8 characters.'; err.style.display = 'block'; return; }
  if (pw !== confirm) { err.textContent = 'Passwords do not match.'; err.style.display = 'block'; return; }

  try {
    const res  = await fetch('/api/auth/first-login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password: pw, password_confirm: confirm }),
    });
    const data = await res.json();

    if (data.success) {
      localStorage.setItem(TK, data.data.token);
      localStorage.setItem(UK, JSON.stringify(data.data.user));
      initAccountPage();
    } else {
      const msgs = data.errors ? Object.values(data.errors).flat().join(', ') : (data.message || 'Failed.');
      err.textContent = msgs;
      err.style.display = 'block';
    }
  } catch {
    err.textContent = 'Connection error. Please try again.';
    err.style.display = 'block';
  }
}

/* ── Register ────────────────────────────────────────────────────── */
async function doRegister() {
  const name  = document.getElementById('reg-name').value.trim();
  const phone = document.getElementById('reg-phone').value.trim();
  const email = document.getElementById('reg-email').value.trim();
  const pw    = document.getElementById('reg-pw').value;
  const err   = document.getElementById('reg-error');
  err.style.display = 'none';

  try {
    const res  = await fetch('/api/auth/register', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name, phone, email, password: pw }),
    });
    const data = await res.json();
    if (data.success) {
      localStorage.setItem(TK, data.data.token);
      localStorage.setItem(UK, JSON.stringify(data.data.user));
      initAccountPage();
    } else {
      const msgs = data.errors ? Object.values(data.errors).flat().join(', ') : (data.message || 'Registration failed.');
      err.textContent = msgs;
      err.style.display = 'block';
    }
  } catch {
    err.textContent = 'Connection error. Try again.';
    err.style.display = 'block';
  }
}

/* ── Logout ──────────────────────────────────────────────────────── */
function doLogout() {
  localStorage.removeItem(TK);
  localStorage.removeItem(UK);
  window.location.reload();
}

/* ── Load orders ─────────────────────────────────────────────────── */
async function loadOrders() {
  const loading = document.getElementById('orders-loading');
  const list    = document.getElementById('orders-list');
  try {
    const res  = await fetch('/api/orders', { headers: { Authorization: 'Bearer ' + getToken() } });
    const data = await res.json();
    loading.style.display = 'none';
    if (!data.success || !data.data.length) {
      list.innerHTML = `
        <div style="text-align:center;padding:56px 24px;color:#aaa">
          <i class="ph ph-package" style="font-size:52px;display:block;margin-bottom:14px;opacity:.2"></i>
          <div style="font-size:16px;color:#666;margin-bottom:10px">No orders yet</div>
          <a href="/collections.php" style="color:#F8C417;font-size:14px;font-weight:600">Start shopping →</a>
        </div>`;
      return;
    }
    list.innerHTML = data.data.map(o => `
      <div class="order-card">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
          <div>
            <div style="font-weight:700;color:#F8C417;font-size:15px;margin-bottom:3px">#${o.order_number}</div>
            <div style="font-size:13px;color:#888">${new Date(o.created_at).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'})}</div>
          </div>
          <div style="text-align:right">
            <div style="font-weight:700;font-size:17px;color:#1A1A1A">${parseInt(o.total).toLocaleString()} EGP</div>
            <div class="status-${o.status}" style="font-size:13px;font-weight:600;margin-top:3px">
              ${o.status.charAt(0).toUpperCase()+o.status.slice(1)}
            </div>
          </div>
        </div>
        <div style="margin-top:10px;padding-top:10px;border-top:1px solid #EBEBEB;font-size:13px;color:#666">
          ${o.governorate} &nbsp;·&nbsp; ${o.payment_method === 'cod' ? 'Cash on Delivery' : 'Card'}
        </div>
      </div>`).join('');
  } catch {
    loading.innerHTML = '<p style="color:#888;padding:24px">Failed to load orders.</p>';
  }
}

/* ── Save profile ────────────────────────────────────────────────── */
async function saveProfile() {
  const succ = document.getElementById('profile-success');
  const err  = document.getElementById('profile-error');
  succ.style.display = err.style.display = 'none';
  try {
    const res = await fetch('/api/auth/profile', {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + getToken() },
      body: JSON.stringify({
        name:  document.getElementById('p-name').value.trim(),
        phone: document.getElementById('p-phone').value.trim(),
      }),
    });
    const data = await res.json();
    if (data.success) {
      const u = getUser();
      if (u) { u.name = document.getElementById('p-name').value.trim(); localStorage.setItem(UK, JSON.stringify(u)); }
      succ.textContent = '✅ Profile updated successfully.';
      succ.style.display = 'block';
    } else {
      err.textContent = data.message || 'Update failed.';
      err.style.display = 'block';
    }
  } catch {
    err.textContent = 'Connection error.';
    err.style.display = 'block';
  }
}

/* ── Change password ─────────────────────────────────────────────── */
async function changePassword() {
  const pw  = document.getElementById('new-pw').value;
  const pw2 = document.getElementById('new-pw2').value;
  const s   = document.getElementById('pwd-success');
  const e   = document.getElementById('pwd-error');
  s.style.display = e.style.display = 'none';
  if (pw.length < 8) { e.textContent = 'Minimum 8 characters.'; e.style.display = 'block'; return; }
  if (pw !== pw2)    { e.textContent = 'Passwords do not match.'; e.style.display = 'block'; return; }
  try {
    const res  = await fetch('/api/auth/set-password', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + getToken() },
      body: JSON.stringify({ password: pw, password_confirm: pw2 }),
    });
    const data = await res.json();
    if (data.success) {
      document.getElementById('pwd-locked-note').style.display = 'none';
      document.getElementById('new-pw').value  = '';
      document.getElementById('new-pw2').value = '';
      s.textContent = '✅ Password updated! You can now sign in with your new password next time.';
      s.style.display = 'block';
    } else {
      e.textContent = data.message || 'Failed.';
      e.style.display = 'block';
    }
  } catch {
    e.textContent = 'Connection error.';
    e.style.display = 'block';
  }
}

/* ── Keyboard shortcuts ──────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  initAccountPage();
  document.getElementById('login-pw')?.addEventListener('keydown', e => { if (e.key === 'Enter') doLogin(); });
  document.getElementById('login-email')?.addEventListener('keydown', e => { if (e.key === 'Enter') document.getElementById('login-pw')?.focus(); });
  document.getElementById('set-pwd-confirm')?.addEventListener('keydown', e => { if (e.key === 'Enter') doFirstLogin(); });
  document.getElementById('reg-pw')?.addEventListener('keydown', e => { if (e.key === 'Enter') doRegister(); });
});
</script>
JS;
require_once __DIR__ . '/public/layout/footer.php';
?>
