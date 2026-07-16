<?php
/**
 * DUHN FRAGRANCES — Checkout Page
 */
require_once __DIR__ . '/api/config/config.php';

$pageTitle = 'Checkout — DUHN FRAGRANCES';
require_once __DIR__ . '/public/layout/header.php';

$governorates = [
    'Cairo','Giza','Alexandria','Luxor','Aswan','Asyut','Beni Suef',
    'Dakahlia','Damietta','Faiyum','Gharbia','Ismailia','Kafr El Sheikh',
    'Matruh','Minya','Monufia','New Valley','North Sinai','Port Said',
    'Qalyubia','Qena','Red Sea','Sharqia','Sohag','South Sinai','Suez'
];
?>

<div class="container" style="padding-top:48px;padding-bottom:80px;max-width:960px">
  <h1 class="section-title" style="margin-bottom:32px">Checkout</h1>

  <div class="checkout-layout" style="display:grid;grid-template-columns:1fr 380px;gap:40px;align-items:start">

    <!-- LEFT: Customer Form -->
    <form id="checkout-form">
      <div style="display:flex;flex-direction:column;gap:20px">

        <h2 style="font-size:18px;font-weight:700;padding-bottom:12px;border-bottom:1px solid var(--divider)">Delivery Information</h2>

        <div class="form-group">
          <label class="form-label">Full Name *</label>
          <input type="text" name="customer_name" class="form-control" placeholder="Mohamed Ahmed" required>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
          <div class="form-group">
            <label class="form-label">Phone Number *</label>
            <input type="tel" name="customer_phone" class="form-control" placeholder="01xxxxxxxxx" required>
          </div>
          <div class="form-group">
            <label class="form-label">
              Email <span style="color:var(--text-muted);font-size:11px;font-weight:400">(optional — creates your account to track orders)</span>
            </label>
            <input type="email" name="customer_email" class="form-control" placeholder="email@example.com">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Governorate *</label>
          <select name="governorate" class="form-control" required>
            <option value="">Select Governorate</option>
            <?php foreach ($governorates as $gov): ?>
            <option value="<?= htmlspecialchars($gov) ?>"><?= htmlspecialchars($gov) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Delivery Address *</label>
          <textarea name="delivery_address" class="form-control" rows="3" placeholder="Street name, building number, floor, apartment..." required style="resize:vertical"></textarea>
        </div>

        <!-- ── GPS LOCATION ──────────────────────────────────────── -->
        <div class="form-group">
          <label class="form-label" style="display:flex;align-items:center;gap:6px">
            <i class="ph ph-map-pin" style="color:var(--accent)"></i>
            Pin Your Location <span style="font-size:11px;color:var(--text-muted);font-weight:400">(optional — helps delivery)</span>
          </label>

          <!-- Saved locations selector -->
          <div id="saved-locations-row" style="display:none;margin-bottom:10px">
            <select id="saved-locations-select" class="form-control" onchange="loadSavedLocation(this.value)" style="font-size:13px">
              <option value="">📂 Select a saved location...</option>
            </select>
          </div>

          <!-- Pin & status -->
          <div style="display:flex;gap:10px;align-items:stretch;flex-wrap:wrap">
            <button type="button" onclick="getGpsLocation()"
                    style="display:flex;align-items:center;gap:8px;padding:11px 18px;background:var(--accent);color:#1A1A1A;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;transition:opacity 0.2s"
                    id="gps-btn">
              <i class="ph ph-crosshair-simple"></i> Get My Location
            </button>
            <div id="gps-status"
                 style="flex:1;min-width:200px;padding:11px 14px;border:1.5px dashed #DEDEDE;border-radius:8px;font-size:13px;color:var(--text-muted);display:flex;align-items:center;gap:8px">
              <i class="ph ph-map-pin"></i>
              <span id="gps-status-text">No location pinned yet.</span>
            </div>
          </div>

          <!-- Save with a name -->
          <div id="gps-save-row" style="display:none;margin-top:10px;display:none">
            <div style="display:flex;gap:8px;align-items:center">
              <input type="text" id="location-label-input" class="form-control"
                     placeholder='Label this location, e.g. "Home" or "Work"'
                     style="flex:1;font-size:13px">
              <button type="button" onclick="saveCurrentLocation()"
                      style="padding:11px 16px;background:#1A1A1A;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap">
                <i class="ph ph-floppy-disk"></i> Save
              </button>
            </div>
          </div>

          <!-- Google Maps preview link -->
          <div id="gps-map-link" style="display:none;margin-top:8px;font-size:12px">
            <a id="gps-map-anchor" href="#" target="_blank" rel="noopener"
               style="color:var(--accent);display:flex;align-items:center;gap:4px">
              <i class="ph ph-arrow-square-out"></i> View on Google Maps
            </a>
          </div>

          <!-- Hidden fields sent with order -->
          <input type="hidden" name="gps_lat"     id="gps-lat">
          <input type="hidden" name="gps_lng"     id="gps-lng">
          <input type="hidden" name="gps_label"   id="gps-label">
          <input type="hidden" name="gps_map_url" id="gps-map-url">
        </div>

        <div class="form-group">
          <label class="form-label">Order Notes (optional)</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Any special instructions for your order..." style="resize:vertical"></textarea>
        </div>

        <h2 style="font-size:18px;font-weight:700;padding-bottom:12px;border-bottom:1px solid var(--divider);margin-top:8px">Payment Method</h2>

        <div style="display:flex;flex-direction:column;gap:10px">
          <label style="display:flex;align-items:center;gap:12px;padding:16px;border:1.5px solid var(--accent);border-radius:8px;cursor:pointer">
            <input type="radio" name="payment_method" value="cod" checked style="accent-color:var(--accent)">
            <div>
              <div style="font-size:15px;font-weight:700">Cash on Delivery</div>
              <div style="font-size:12px;color:var(--text-muted);margin-top:2px">Pay when your order arrives</div>
            </div>
            <span style="margin-left:auto;font-size:20px">💵</span>
          </label>

          <label style="display:flex;align-items:center;gap:12px;padding:16px;border:1.5px solid var(--divider);border-radius:8px;cursor:not-allowed;opacity:0.5">
            <input type="radio" name="payment_method" value="card" disabled style="accent-color:var(--accent)">
            <div>
              <div style="font-size:15px;font-weight:700">Credit / Debit Card</div>
              <div style="font-size:12px;color:var(--text-muted);margin-top:2px">Coming soon</div>
            </div>
            <span style="margin-left:auto;font-size:20px">💳</span>
          </label>
        </div>

        <!-- Error Display -->
        <div id="checkout-errors" style="display:none;background:rgba(220,53,69,0.1);border:1px solid var(--error);border-radius:8px;padding:14px;font-size:13px;color:var(--error)"></div>

      </div>
    </form>

    <!-- RIGHT: Order Summary -->
    <div style="position:sticky;top:88px">
      <div style="background:var(--surface-dark);border-radius:var(--radius);padding:24px">
        <h3 style="font-size:16px;font-weight:700;margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid rgba(255,255,255,0.1);color:#fff">Order Summary</h3>

        <div id="checkout-items" style="display:flex;flex-direction:column;gap:12px;margin-bottom:20px">
          <div style="color:#888;font-size:13px">Loading cart...</div>
        </div>

        <div style="display:flex;flex-direction:column;gap:10px;padding-top:14px;border-top:1px solid rgba(255,255,255,0.1)">
          <div style="display:flex;justify-content:space-between;font-size:14px">
            <span style="color:#aaa">Subtotal</span>
            <span id="co-subtotal" style="color:#e8e0d5">— EGP</span>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:14px;color:var(--success)" id="co-discount-row">
            <span>Promo (Buy 2 Get 2 Free)</span>
            <span id="co-discount">—</span>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:14px">
            <span style="color:#aaa">Delivery</span>
            <span id="co-delivery" style="color:#e8e0d5">FREE</span>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:18px;font-weight:700;padding-top:10px;border-top:1px solid rgba(255,255,255,0.1)">
            <span style="color:#fff">Total</span>
            <span id="co-total" style="color:var(--accent)">— EGP</span>
          </div>
        </div>

        <div style="margin-top:8px;padding:10px 14px;background:rgba(248,196,23,0.1);border:1px solid rgba(248,196,23,0.2);border-radius:6px;font-size:12px;color:var(--accent);display:none" id="co-promo-note">
          🎁 BUY 2 GET 2 FREE discount applied!
        </div>

        <button type="button" id="place-order-btn" onclick="placeOrder()"
                class="btn btn-gold btn-full btn-lg" style="margin-top:20px">
          <i class="ph ph-check-circle"></i>
          PLACE ORDER
        </button>

        <!-- Trust badges -->
        <div style="display:flex;justify-content:center;gap:20px;margin-top:16px;flex-wrap:wrap">
          <span style="display:flex;align-items:center;gap:5px;font-size:11px;color:#aaa">
            <i class="ph ph-hand-coins" style="color:var(--accent);font-size:14px"></i> Cash on Delivery
          </span>
          <span style="display:flex;align-items:center;gap:5px;font-size:11px;color:#aaa">
            <i class="ph ph-arrow-counter-clockwise" style="color:var(--accent);font-size:14px"></i> Easy Returns
          </span>
          <span style="display:flex;align-items:center;gap:5px;font-size:11px;color:#aaa">
            <i class="ph ph-lock-simple" style="color:var(--accent);font-size:14px"></i> Secure & Private
          </span>
        </div>
        <p style="font-size:11px;color:#777;text-align:center;margin-top:10px">
          By placing your order, you agree to our <a href="/exchange-policy.php" style="color:var(--accent)">policies</a>.
        </p>
      </div>
    </div>
  </div>
</div>

<?php
$extraScripts = <<<'JS'
<script>
// Load cart into checkout
async function loadCheckoutCart() {
  const res  = await fetch(`${API}/cart`, { credentials: 'include', headers: authHeaders() });
  const json = await res.json();

  if (!json.success || !json.data.items?.length) {
    window.location.href = '/collections.php';
    return;
  }

  const d = json.data;
  const itemsEl = document.getElementById('checkout-items');
  itemsEl.innerHTML = d.items.map(item => `
    <div style="display:flex;gap:10px;align-items:center">
      <img src="${item.image || '/public/images/placeholder.jpg'}" style="width:52px;height:52px;border-radius:6px;object-fit:cover;background:#3a3a3a" onerror="this.src='/public/images/placeholder.jpg'">
      <div style="flex:1">
        <div style="font-size:13px;font-weight:600;color:#f0ede6">${escapeHtml(item.name)}</div>
        <div style="font-size:12px;color:#888">Qty: ${item.quantity}</div>
      </div>
      <div style="font-size:13px;font-weight:700;color:var(--accent)">${parseFloat(item.price).toFixed(0)} EGP</div>
    </div>
  `).join('');

  document.getElementById('co-subtotal').textContent = `${parseFloat(d.subtotal).toFixed(0)} EGP`;
  document.getElementById('co-total').textContent    = `${parseFloat(d.total).toFixed(0)} EGP`;
  document.getElementById('co-delivery').textContent = parseFloat(d.delivery_fee) === 0 ? 'FREE' : `${parseFloat(d.delivery_fee).toFixed(0)} EGP`;

  if (d.promo_active) {
    document.getElementById('co-discount').textContent  = `-${parseFloat(d.discount).toFixed(0)} EGP`;
    document.getElementById('co-discount-row').style.display = 'flex';
    document.getElementById('co-promo-note').style.display   = 'block';
  } else {
    document.getElementById('co-discount-row').style.display = 'none';
  }
}

// ── GPS LOCATION LOGIC ─────────────────────────────────────────
const LS_KEY = 'duhn_saved_locations';

function getSavedLocations() {
  try { return JSON.parse(localStorage.getItem(LS_KEY)) || []; } catch { return []; }
}

function renderSavedLocations() {
  const locs   = getSavedLocations();
  const row    = document.getElementById('saved-locations-row');
  const select = document.getElementById('saved-locations-select');
  if (!locs.length) { row.style.display = 'none'; return; }
  row.style.display = 'block';
  select.innerHTML  = '<option value="">📂 Select a saved location...</option>';
  locs.forEach((loc, i) => {
    const opt = document.createElement('option');
    opt.value = i;
    opt.textContent = `📍 ${loc.label} (${loc.lat.toFixed(4)}, ${loc.lng.toFixed(4)})`;
    select.appendChild(opt);
  });
  // Add delete options at bottom
  const delOpt = document.createElement('option');
  delOpt.value = '__manage__';
  delOpt.textContent = '🗑 Manage saved locations...';
  select.appendChild(delOpt);
}

function loadSavedLocation(idx) {
  if (idx === '__manage__') { manageSavedLocations(); return; }
  if (idx === '' || idx === null) return;
  const locs = getSavedLocations();
  const loc  = locs[parseInt(idx)];
  if (!loc) return;
  applyLocation(loc.lat, loc.lng, loc.label);
  document.getElementById('location-label-input').value = loc.label;
}

function manageSavedLocations() {
  const locs = getSavedLocations();
  if (!locs.length) { alert('No saved locations.'); return; }
  const list = locs.map((l, i) => `${i + 1}. ${l.label}`).join('\n');
  const del  = prompt(`Your saved locations:\n${list}\n\nEnter number to DELETE (or cancel):`);
  if (!del) return;
  const idx = parseInt(del) - 1;
  if (isNaN(idx) || idx < 0 || idx >= locs.length) { alert('Invalid number.'); return; }
  locs.splice(idx, 1);
  localStorage.setItem(LS_KEY, JSON.stringify(locs));
  renderSavedLocations();
  document.getElementById('saved-locations-select').value = '';
}

function getGpsLocation() {
  if (!navigator.geolocation) {
    alert('Geolocation is not supported by your browser.');
    return;
  }
  const btn = document.getElementById('gps-btn');
  btn.innerHTML = '<i class="ph ph-spinner"></i> Locating...';
  btn.disabled  = true;

  navigator.geolocation.getCurrentPosition(
    pos => {
      btn.innerHTML = '<i class="ph ph-crosshair-simple"></i> Update Location';
      btn.disabled  = false;
      applyLocation(pos.coords.latitude, pos.coords.longitude, '');
      document.getElementById('gps-save-row').style.display = 'block';
    },
    err => {
      btn.innerHTML = '<i class="ph ph-crosshair-simple"></i> Get My Location';
      btn.disabled  = false;
      const msgs = {1:'Location access denied. Please allow in browser settings.',
                    2:'Could not detect location. Try again.',
                    3:'Location request timed out.'};
      document.getElementById('gps-status-text').textContent = msgs[err.code] || 'Error getting location.';
    },
    { timeout: 10000, enableHighAccuracy: true }
  );
}

function applyLocation(lat, lng, label) {
  const mapUrl = `https://www.google.com/maps?q=${lat},${lng}`;
  document.getElementById('gps-lat').value     = lat;
  document.getElementById('gps-lng').value     = lng;
  document.getElementById('gps-label').value   = label;
  document.getElementById('gps-map-url').value = mapUrl;

  const short = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
  document.getElementById('gps-status-text').textContent = label ? `📍 ${label} — ${short}` : `📍 ${short}`;
  document.getElementById('gps-status').style.borderColor = 'var(--accent)';
  document.getElementById('gps-status').style.color       = '#1A1A1A';

  const anchor = document.getElementById('gps-map-anchor');
  anchor.href  = mapUrl;
  document.getElementById('gps-map-link').style.display = 'block';
  document.getElementById('gps-save-row').style.display = 'block';
}

function saveCurrentLocation() {
  const lat   = parseFloat(document.getElementById('gps-lat').value);
  const lng   = parseFloat(document.getElementById('gps-lng').value);
  const label = document.getElementById('location-label-input').value.trim();
  if (!lat || !lng) { alert('Get your location first.'); return; }
  if (!label)       { alert('Please enter a name for this location (e.g. Home, Work).'); return; }

  const locs = getSavedLocations();
  // Avoid duplicates by label
  const existing = locs.findIndex(l => l.label.toLowerCase() === label.toLowerCase());
  if (existing >= 0) {
    if (!confirm(`A location named "${label}" already exists. Replace it?`)) return;
    locs[existing] = { label, lat, lng };
  } else {
    locs.push({ label, lat, lng });
  }
  localStorage.setItem(LS_KEY, JSON.stringify(locs));
  renderSavedLocations();
  applyLocation(lat, lng, label);

  // Flash confirmation
  const saveBtn = document.querySelector('button[onclick="saveCurrentLocation()"]');
  saveBtn.textContent = '✓ Saved!';
  setTimeout(() => { saveBtn.innerHTML = '<i class="ph ph-floppy-disk"></i> Save'; }, 2000);
}

document.addEventListener('DOMContentLoaded', renderSavedLocations);
// ───────────────────────────────────────────────────────────────

async function placeOrder() {
  const form = document.getElementById('checkout-form');
  const btn  = document.getElementById('place-order-btn');
  const errEl = document.getElementById('checkout-errors');

  errEl.style.display = 'none';
  btn.disabled = true;
  btn.textContent = 'Placing order...';

  const gpsLat   = document.getElementById('gps-lat').value;
  const gpsLng   = document.getElementById('gps-lng').value;
  const gpsLabel = document.getElementById('gps-label').value;
  const gpsUrl   = document.getElementById('gps-map-url').value;

  const data = {
    customer_name:    form.querySelector('[name=customer_name]').value.trim(),
    customer_phone:   form.querySelector('[name=customer_phone]').value.trim(),
    customer_email:   form.querySelector('[name=customer_email]').value.trim(),
    governorate:      form.querySelector('[name=governorate]').value,
    delivery_address: form.querySelector('[name=delivery_address]').value.trim(),
    notes:            form.querySelector('[name=notes]').value.trim(),
    payment_method:   form.querySelector('[name=payment_method]:checked').value,
    gps_lat:          gpsLat   || null,
    gps_lng:          gpsLng   || null,
    gps_label:        gpsLabel || null,
    gps_map_url:      gpsUrl   || null,
  };

  try {
    const res  = await fetch(`${API}/orders`, {
      method: 'POST',
      headers: { ...authHeaders(), 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(data)
    });
    const json = await res.json();

    if (json.success) {
      // Handle auto-created guest account
      if (json.data.auto_account) {
        const acct = json.data.auto_account;
        if (acct.type === 'created' && acct.token) {
          // Auto-log the user in immediately with the JWT
          localStorage.setItem('duhn_token', acct.token);
          localStorage.setItem('duhn_user', JSON.stringify({
            name: acct.name, email: acct.email, role: 'customer'
          }));
          // Store email so confirmation page can pre-fill set-password form
          sessionStorage.setItem('duhn_new_account_email', acct.email);
        } else if (acct.type === 'existing') {
          sessionStorage.setItem('duhn_existing_account', '1');
        }
      }
      const acctParam = json.data.auto_account ? `&acct=${json.data.auto_account.type}` : '';
      window.location.href = `/order-confirmation.php?order=${encodeURIComponent(json.data.order_number)}&total=${encodeURIComponent(json.data.total)}${acctParam}`;
    } else {
      errEl.innerHTML = json.message || 'Failed to place order.';
      if (json.errors) {
        errEl.innerHTML += '<br>' + Object.values(json.errors).flat().join('<br>');
      }
      errEl.style.display = 'block';
      btn.disabled = false;
      btn.innerHTML = '<i class="ph ph-check-circle"></i> PLACE ORDER';
    }
  } catch (e) {
    errEl.textContent  = 'Network error. Please try again.';
    errEl.style.display = 'block';
    btn.disabled = false;
    btn.innerHTML = '<i class="ph ph-check-circle"></i> PLACE ORDER';
  }
}

document.addEventListener('DOMContentLoaded', loadCheckoutCart);
</script>
JS;
require_once __DIR__ . '/public/layout/footer.php';
?>
