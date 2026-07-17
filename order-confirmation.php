<?php
$orderNum    = htmlspecialchars($_GET['order']   ?? '');
$total       = htmlspecialchars($_GET['total']   ?? '');
$acctType    = htmlspecialchars($_GET['acct']    ?? '');
$paymentRes  = htmlspecialchars($_GET['payment'] ?? '');   // 'success' | 'failed' | ''
$pageTitle   = ($paymentRes === 'failed') ? 'Payment Failed — DUHN FRAGRANCES' : 'Order Confirmed — DUHN FRAGRANCES';
require_once __DIR__ . '/public/layout/header.php';
require_once __DIR__ . '/public/layout/loader.php';
?>

<div class="container" style="padding:80px 0;max-width:580px">

  <?php if ($paymentRes === 'failed'): ?>
  <!-- ── Payment failed icon ───────────────────────────────────── -->
  <div style="text-align:center;margin-bottom:32px">
    <div style="width:80px;height:80px;background:rgba(220,53,69,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;border:2px solid #dc3545">
      <i class="ph ph-x-circle" style="font-size:40px;color:#dc3545"></i>
    </div>
    <h1 style="font-size:28px;font-weight:700;margin-bottom:8px;color:#dc3545">Payment Failed</h1>
    <p style="color:var(--text-muted);font-size:15px">Your card payment was not completed. Your order has been saved — you can retry or choose Cash on Delivery.</p>
  </div>

  <!-- ── Retry options ────────────────────────────────────────── -->
  <div style="background:rgba(220,53,69,0.06);border:1px solid rgba(220,53,69,0.2);border-radius:var(--radius);padding:20px;margin-bottom:24px;text-align:center">
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px">What would you like to do?</p>
    <div style="display:flex;flex-direction:column;gap:10px">
      <a href="/checkout.php" class="btn btn-gold btn-full">
        <i class="ph ph-arrow-counter-clockwise"></i> TRY AGAIN
      </a>
      <a href="https://wa.me/201157879622?text=Hi! My card payment failed for <?= urlencode($orderNum) ?>. Can I switch to Cash on Delivery?"
         target="_blank" rel="noopener" class="btn btn-outline btn-full">
        <i class="ph ph-whatsapp-logo" style="color:#25D366"></i> CONTACT US ON WHATSAPP
      </a>
    </div>
  </div>

  <?php else: ?>
  <!-- ── Success icon ──────────────────────────────────────────── -->
  <div style="text-align:center;margin-bottom:32px">
    <div style="width:80px;height:80px;background:rgba(40,167,69,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;border:2px solid #28A745">
      <i class="ph ph-check-circle" style="font-size:40px;color:#28A745"></i>
    </div>
    <h1 style="font-size:28px;font-weight:700;margin-bottom:8px">Order <?= $paymentRes === 'success' ? 'Paid & Confirmed!' : 'Placed!' ?></h1>
    <p style="color:var(--text-muted);font-size:15px">
      <?= $paymentRes === 'success'
          ? 'Your payment was successful. Thank you for your order!'
          : 'Thank you for choosing DUHN FRAGRANCES. Your order has been received.' ?>
    </p>
  </div>

  <?php if ($paymentRes === 'success'): ?>
  <!-- ── Card payment success badge ──────────────────────────── -->
  <div style="background:rgba(40,167,69,0.08);border:1px solid rgba(40,167,69,0.25);border-radius:10px;padding:14px 18px;margin-bottom:16px;display:flex;align-items:center;gap:12px">
    <i class="ph ph-credit-card" style="color:#28A745;font-size:22px;flex-shrink:0"></i>
    <div>
      <div style="font-size:13px;font-weight:700;color:#28A745">Payment confirmed via Kashier</div>
      <div style="font-size:12px;color:var(--text-muted)">Your card was charged successfully. A confirmation email has been sent.</div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Order box ─────────────────────────────────────────────── -->
  <?php if ($orderNum): ?>
  <div style="background:var(--surface-dark);border-radius:var(--radius);padding:24px;margin-bottom:20px">
    <div style="display:flex;justify-content:space-between;margin-bottom:12px">
      <span style="color:var(--text-muted);font-size:14px">Order Number</span>
      <span style="font-weight:700;color:var(--accent)"><?= $orderNum ?></span>
    </div>
    <?php if ($total): ?>
    <div style="display:flex;justify-content:space-between;margin-bottom:12px">
      <span style="color:var(--text-muted);font-size:14px">Total</span>
      <span style="font-weight:700;color:var(--accent);font-size:18px"><?= number_format((float)$total, 0) ?> <span style="font-size:13px;opacity:0.8">EGP</span></span>
    </div>
    <?php endif; ?>
    <div style="display:flex;justify-content:space-between">
      <span style="color:var(--text-muted);font-size:14px">Status</span>
      <?php if ($paymentRes === 'success'): ?>
      <span style="font-weight:700;color:#28A745">✓ Confirmed & Paid</span>
      <?php else: ?>
      <span style="font-weight:700;color:#28A745">✓ Pending Confirmation</span>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── SET PASSWORD BANNER (new auto-created account) ────────── -->
  <?php if ($acctType === 'created'): ?>
  <div id="set-password-box" style="background:linear-gradient(135deg,rgba(248,196,23,0.1) 0%,rgba(248,196,23,0.04) 100%);border:1.5px solid rgba(248,196,23,0.3);border-radius:14px;padding:24px;margin-bottom:20px">

    <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px">
      <div style="width:40px;height:40px;background:rgba(248,196,23,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <i class="ph ph-user-circle-plus" style="color:var(--accent);font-size:22px"></i>
      </div>
      <div>
        <div style="font-size:16px;font-weight:700;color:var(--accent)">Account Created For You!</div>
        <div style="font-size:12px;color:var(--text-muted)" id="acct-email-line">Set a password to access your orders anytime</div>
      </div>
    </div>

    <!-- Success state (hidden until password is set) -->
    <div id="pwd-success" style="display:none;background:rgba(40,167,69,0.12);border:1px solid rgba(40,167,69,0.3);border-radius:8px;padding:12px 14px;font-size:13px;color:#4ade80;margin-top:14px">
      ✅ Password set! You're now signed in. <a href="/account.php" style="color:var(--accent);font-weight:700">View My Orders →</a>
    </div>

    <!-- Form -->
    <div id="pwd-form" style="margin-top:16px">
      <div id="pwd-error" style="display:none;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);border-radius:8px;padding:10px 14px;font-size:13px;color:#f87171;margin-bottom:12px"></div>

      <div style="display:flex;flex-direction:column;gap:10px">
        <div>
          <label style="font-size:12px;color:var(--text-muted);font-weight:600;letter-spacing:0.05em;text-transform:uppercase;display:block;margin-bottom:6px">Your Email</label>
          <input type="email" id="new-acct-email" class="form-control" readonly
                 style="background:rgba(255,255,255,0.04);border-color:rgba(255,255,255,0.1);color:var(--text-muted);cursor:default">
        </div>
        <div>
          <label style="font-size:12px;color:var(--text-muted);font-weight:600;letter-spacing:0.05em;text-transform:uppercase;display:block;margin-bottom:6px">Choose a Password *</label>
          <input type="password" id="new-pwd" class="form-control" placeholder="Minimum 8 characters" autocomplete="new-password">
        </div>
        <div>
          <label style="font-size:12px;color:var(--text-muted);font-weight:600;letter-spacing:0.05em;text-transform:uppercase;display:block;margin-bottom:6px">Confirm Password *</label>
          <input type="password" id="new-pwd-confirm" class="form-control" placeholder="Repeat your password" autocomplete="new-password">
        </div>
        <button id="set-pwd-btn" onclick="setAccountPassword()" class="btn btn-gold btn-full" style="margin-top:4px">
          <i class="ph ph-lock-key"></i> Set Password & Activate Account
        </button>
      </div>

      <p style="font-size:11px;color:var(--text-muted);margin-top:10px;text-align:center">
        You can also skip this and <a href="/account.php" style="color:var(--accent)">set it later from My Account</a>.
      </p>
    </div>
  </div>

  <?php elseif ($acctType === 'existing'): ?>
  <div style="background:rgba(40,167,69,0.08);border:1px solid rgba(40,167,69,0.25);border-radius:10px;padding:16px 20px;margin-bottom:20px;font-size:13px">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
      <i class="ph ph-user-check" style="color:#28A745;font-size:18px"></i>
      <strong style="color:#28A745">Order linked to your account</strong>
    </div>
    <p style="color:var(--text-muted);margin:0">This order has been added to your account history. <a href="/account.php" style="color:var(--accent)">View My Orders →</a></p>
  </div>
  <?php endif; ?>

  <!-- ── Delivery note ─────────────────────────────────────────── -->
  <div style="background:rgba(248,196,23,0.08);border:1px solid rgba(248,196,23,0.25);border-radius:var(--radius);padding:16px;margin-bottom:28px;font-size:13px;color:#6b5717;text-align:center">
    📦 Your order will be delivered within <strong style="color:#b8860b">2–5 business days</strong>.<br>
    You'll receive a tracking update from our team.
  </div>

  <!-- ── Actions ───────────────────────────────────────────────── -->
  <div style="display:flex;flex-direction:column;gap:12px">
    <a href="/collections.php" class="btn btn-gold btn-full">CONTINUE SHOPPING</a>
    <a href="https://wa.me/201157879622?text=Hi! I just placed order <?= urlencode($orderNum) ?> and want to follow up."
       target="_blank" rel="noopener"
       class="btn btn-outline btn-full">
      <i class="ph ph-whatsapp-logo" style="color:#25D366"></i>
      TRACK VIA WHATSAPP
    </a>
  </div>

  <?php endif; // payment failed / success ?>

</div>

<?php
$extraScripts = <<<'JS'
<script>
// Pre-fill email from sessionStorage
(function () {
  const email = sessionStorage.getItem('duhn_new_account_email');
  const el    = document.getElementById('new-acct-email');
  const lbl   = document.getElementById('acct-email-line');
  if (email && el) {
    el.value = email;
    if (lbl) lbl.textContent = email;
  }
})();

async function setAccountPassword() {
  const pwd     = document.getElementById('new-pwd')?.value ?? '';
  const confirm = document.getElementById('new-pwd-confirm')?.value ?? '';
  const errEl   = document.getElementById('pwd-error');
  const btn     = document.getElementById('set-pwd-btn');

  errEl.style.display = 'none';

  if (pwd.length < 8) {
    errEl.textContent = 'Password must be at least 8 characters.';
    errEl.style.display = 'block';
    return;
  }
  if (pwd !== confirm) {
    errEl.textContent = 'Passwords do not match.';
    errEl.style.display = 'block';
    return;
  }

  const token = localStorage.getItem('duhn_token');
  if (!token) {
    errEl.textContent = 'Session expired. Please go to My Account to set your password.';
    errEl.style.display = 'block';
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<i class="ph ph-spinner"></i> Saving...';

  try {
    const res = await fetch('/api/auth/set-password', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
      body: JSON.stringify({ password: pwd, password_confirm: confirm }),
    });
    const data = await res.json();

    if (data.success) {
      document.getElementById('pwd-form').style.display    = 'none';
      document.getElementById('pwd-success').style.display = 'block';
      // Clear the temporary email from sessionStorage
      sessionStorage.removeItem('duhn_new_account_email');
    } else {
      const msgs = data.errors ? Object.values(data.errors).flat().join(', ') : (data.message || 'Failed to set password.');
      errEl.textContent = msgs;
      errEl.style.display = 'block';
      btn.disabled = false;
      btn.innerHTML = '<i class="ph ph-lock-key"></i> Set Password & Activate Account';
    }
  } catch {
    errEl.textContent = 'Network error. Please try again.';
    errEl.style.display = 'block';
    btn.disabled = false;
    btn.innerHTML = '<i class="ph ph-lock-key"></i> Set Password & Activate Account';
  }
}
</script>
JS;
require_once __DIR__ . '/public/layout/footer.php';
?>
