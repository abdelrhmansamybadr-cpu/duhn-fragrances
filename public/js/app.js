/**
 * DUHN FRAGRANCES — Main JavaScript
 * Handles: cart, search, hero slider, toasts, nav
 */

/* ─────────────────────────────────────────────────────────────
   PAGE LOADER — dismiss once page is ready
───────────────────────────────────────────────────────────── */
(function () {
  const MIN_DISPLAY = 2400; // ms: minimum time loader stays visible
  const start = Date.now();

  function dismissLoader() {
    const loader = document.getElementById('page-loader');
    if (!loader || loader.classList.contains('ldr-hide')) return;

    const elapsed = Date.now() - start;
    const delay   = Math.max(0, MIN_DISPLAY - elapsed);

    setTimeout(() => {
      loader.classList.add('ldr-hide');
      loader.addEventListener('animationend', () => {
        loader.classList.add('ldr-gone');
      }, { once: true });
    }, delay);
  }

  if (document.readyState === 'complete') {
    dismissLoader();
  } else {
    window.addEventListener('load', dismissLoader, { once: true });
    // Safety fallback: never block user longer than 4.5s
    setTimeout(dismissLoader, 4500);
  }
})();

const API = '/api';

/* ─────────────────────────────────────────────────────────────
   CART MANAGER
───────────────────────────────────────────────────────────── */
const Cart = {
  data: null,

  async fetch() {
    try {
      const res = await fetch(`${API}/cart`, {
        headers: authHeaders(),
        credentials: 'include'
      });
      const json = await res.json();
      if (json.success) {
        this.data = json.data;
        this.render();
        this.updateBadge();
      }
    } catch (e) { console.warn('Cart fetch failed', e); }
  },

  async add(productId, qty = 1, btn = null) {
    // Optimistic button feedback
    let origHTML = null;
    if (btn) {
      origHTML = btn.innerHTML;
      btn.innerHTML = '✓ Added!';
      btn.disabled = true;
      btn.style.opacity = '0.85';
    }
    try {
      const res = await fetch(`${API}/cart/add`, {
        method: 'POST',
        headers: { ...authHeaders(), 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ product_id: productId, quantity: qty })
      });
      const json = await res.json();
      if (json.success) {
        Toast.show('✓ Added to cart!', 'success');
        await this.fetch();
        this.open();
      } else {
        Toast.show(json.message || 'Could not add to cart', 'error');
        if (btn && origHTML !== null) { btn.innerHTML = origHTML; btn.disabled = false; btn.style.opacity = ''; }
      }
    } catch (e) {
      Toast.show('Network error', 'error');
      if (btn && origHTML !== null) { btn.innerHTML = origHTML; btn.disabled = false; btn.style.opacity = ''; }
    }
    // Restore button after delay (even on success)
    if (btn && origHTML !== null) {
      setTimeout(() => { btn.innerHTML = origHTML; btn.disabled = false; btn.style.opacity = ''; }, 1800);
    }
  },

  async updateQty(itemId, qty) {
    await fetch(`${API}/cart/update`, {
      method: 'PUT',
      headers: { ...authHeaders(), 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ item_id: itemId, quantity: qty })
    });
    await this.fetch();
  },

  async remove(itemId) {
    await fetch(`${API}/cart/remove/${itemId}`, {
      method: 'DELETE',
      headers: authHeaders(),
      credentials: 'include'
    });
    await this.fetch();
  },

  open() {
    document.getElementById('cart-overlay').classList.add('open');
    document.body.style.overflow = 'hidden';
  },

  close() {
    document.getElementById('cart-overlay').classList.remove('open');
    document.body.style.overflow = '';
  },

  updateBadge() {
    const count = this.data?.item_count ?? 0;
    document.querySelectorAll('.cart-badge').forEach(el => {
      el.textContent = count;
      el.style.display = count > 0 ? 'flex' : 'none';
    });
  },

  render() {
    const d = this.data;
    const listEl = document.getElementById('cart-items-list');
    const emptyEl = document.getElementById('cart-empty');
    const footerEl = document.getElementById('cart-footer');

    if (!d || !d.items || d.items.length === 0) {
      listEl.innerHTML = '';
      if (emptyEl)  emptyEl.style.display = 'flex';
      if (footerEl) footerEl.style.display = 'none';
      return;
    }

    if (emptyEl)  emptyEl.style.display = 'none';
    if (footerEl) footerEl.style.display = 'block';

    listEl.innerHTML = d.items.map(item => `
      <div class="cart-item" data-item-id="${item.id}">
        <img src="${item.image || '/public/images/placeholder.jpg'}" alt="${item.name}" onerror="this.src='/public/images/placeholder.jpg'">
        <div class="cart-item__info">
          <div class="cart-item__name">${escapeHtml(item.name)}</div>
          <div class="cart-item__price">${parseFloat(item.price).toFixed(0)} EGP</div>
          <div class="cart-item__qty">
            <button class="qty-btn" onclick="Cart.updateQty(${item.id}, ${item.quantity - 1})">−</button>
            <span class="qty-value">${item.quantity}</span>
            <button class="qty-btn" onclick="Cart.updateQty(${item.id}, ${item.quantity + 1})">+</button>
          </div>
        </div>
        <button class="cart-item__remove" onclick="Cart.remove(${item.id})" title="Remove">✕</button>
      </div>
    `).join('');

    // Promo / deal banner
    const promoBanner = document.getElementById('cart-promo');
    const labelEl = document.getElementById('cart-promo-label');
    if (promoBanner) {
      const showBanner = d.promo_active || d.deal_teaser;
      promoBanner.style.display = showBanner ? 'flex' : 'none';
      if (labelEl) {
        if (d.promo_active && d.promo_label) {
          labelEl.textContent = d.promo_label;
          promoBanner.style.background = 'rgba(40,167,69,0.12)';
          promoBanner.style.borderColor = 'rgba(40,167,69,0.3)';
          promoBanner.style.color = '#6fcf97';
        } else if (d.deal_teaser) {
          labelEl.textContent = d.deal_teaser;
          promoBanner.style.background = 'rgba(248,196,23,0.08)';
          promoBanner.style.borderColor = 'rgba(248,196,23,0.25)';
          promoBanner.style.color = '#F8C417';
        }
      }
    }

    // Promo code input UI
    const appliedRow = document.getElementById('promo-applied-row');
    const inputRow   = document.getElementById('promo-input-row');
    const appliedLbl = document.getElementById('promo-applied-label');
    if (d.promo_code && appliedRow && inputRow) {
      appliedRow.style.display = 'flex';
      inputRow.style.display   = 'none';
      if (appliedLbl) appliedLbl.textContent = '✓ ' + d.promo_code + ' applied';
    } else if (appliedRow && inputRow) {
      appliedRow.style.display = 'none';
      inputRow.style.display   = 'flex';
    }

    // Totals
    const setEl = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.textContent = val;
    };
    setEl('cart-subtotal', `${parseFloat(d.subtotal).toFixed(0)} EGP`);
    setEl('cart-discount', d.promo_active ? `−${parseFloat(d.discount).toFixed(0)} EGP` : '—');
    // Update discount row label based on wallet vs promo
    const discLbl = document.getElementById('cart-discount-label');
    if (discLbl) discLbl.textContent = d.wallet_active ? '💰 Wallet Discount' : 'Promo Discount';
    // Hide promo code input when wallet is active
    const promoInputWrap = document.getElementById('cart-promo-input-wrap');
    if (promoInputWrap) promoInputWrap.style.display = d.wallet_active ? 'none' : '';
    setEl('cart-delivery', parseFloat(d.delivery_fee) === 0 ? 'FREE' : `${parseFloat(d.delivery_fee).toFixed(0)} EGP`);
    setEl('cart-total', `${parseFloat(d.total).toFixed(0)} EGP`);
  },

  async applyPromo() {
    const input = document.getElementById('promo-code-input');
    const msgEl = document.getElementById('promo-msg');
    const btn   = document.getElementById('promo-apply-btn');
    const code  = input?.value.trim().toUpperCase();
    if (!code) return;

    // Get current subtotal from cart
    const subtotalEl = document.getElementById('cart-subtotal');
    const cartTotal  = subtotalEl ? parseFloat(subtotalEl.textContent) || 0 : 0;

    btn.textContent = '...';
    btn.disabled    = true;
    msgEl.style.display = 'none';

    try {
      const res  = await fetch(`${API}/promo/apply`, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ code, cart_total: cartTotal }),
      });
      const json = await res.json();
      if (json.success) {
        msgEl.style.cssText = 'font-size:11px;margin-top:6px;display:block;color:#4CAF50';
        msgEl.textContent   = '✓ ' + json.message;
        input.value = '';
        await Cart.fetch();   // refresh totals with discount applied
      } else {
        msgEl.style.cssText = 'font-size:11px;margin-top:6px;display:block;color:#e55';
        msgEl.textContent   = json.message || 'Invalid promo code.';
      }
    } catch (e) {
      msgEl.style.cssText = 'font-size:11px;margin-top:6px;display:block;color:#e55';
      msgEl.textContent   = 'Could not apply code. Try again.';
    }
    btn.textContent = 'APPLY';
    btn.disabled    = false;
  },

  async removePromo() {
    await fetch(`${API}/promo/remove`, { method: 'POST' });
    const inputRow   = document.getElementById('promo-input-row');
    const appliedRow = document.getElementById('promo-applied-row');
    if (inputRow)   inputRow.style.display   = 'flex';
    if (appliedRow) appliedRow.style.display = 'none';
    await Cart.fetch();
  },

  // Alias for fetch — used after wallet/newsletter actions
  load() { return this.fetch(); },
};

/* ─────────────────────────────────────────────────────────────
   SEARCH
───────────────────────────────────────────────────────────── */
const Search = {
  timer: null,

  open() {
    document.getElementById('search-overlay').classList.add('open');
    setTimeout(() => document.getElementById('search-input').focus(), 50);
    document.body.style.overflow = 'hidden';
  },

  close() {
    document.getElementById('search-overlay').classList.remove('open');
    document.body.style.overflow = '';
    document.getElementById('search-input').value = '';
    document.getElementById('search-results').innerHTML = '';
  },

  async query(q) {
    clearTimeout(this.timer);
    const resultsEl = document.getElementById('search-results');
    if (!q || q.length < 2) { resultsEl.innerHTML = ''; return; }

    this.timer = setTimeout(async () => {
      try {
        const res  = await fetch(`${API}/products/search?q=${encodeURIComponent(q)}`);
        const json = await res.json();
        if (!json.success || !json.data.length) {
          resultsEl.innerHTML = '<div style="text-align:center;color:var(--text-muted);padding:20px">No products found</div>';
          return;
        }
        resultsEl.innerHTML = json.data.map(p => `
          <a href="/product.php?slug=${p.slug}" class="search-result-item" onclick="Search.close()">
            <img src="${p.images[0] || '/public/images/placeholder.jpg'}" alt="${p.name}" onerror="this.src='/public/images/placeholder.jpg'">
            <div>
              <div class="name">${escapeHtml(p.name)}</div>
              <div style="font-size:11px;color:var(--text-muted)">Inspired by ${escapeHtml(p.inspired_by)}</div>
            </div>
            <div class="price">${p.price} EGP</div>
          </a>
        `).join('');
      } catch (e) { resultsEl.innerHTML = ''; }
    }, 350);
  }
};

/* ─────────────────────────────────────────────────────────────
   HERO SLIDER
───────────────────────────────────────────────────────────── */
const Slider = {
  current: 0,
  total: 0,
  timer: null,

  init() {
    const slides = document.querySelectorAll('.hero-slide');
    const dots   = document.querySelectorAll('.hero-dot');
    this.total   = slides.length;
    if (!this.total) return;

    this.goTo(0);
    this.timer = setInterval(() => this.next(), 5000);

    dots.forEach((dot, i) => dot.addEventListener('click', () => {
      clearInterval(this.timer);
      this.goTo(i);
      this.timer = setInterval(() => this.next(), 5000);
    }));
  },

  goTo(index) {
    const slides = document.querySelectorAll('.hero-slide');
    const dots   = document.querySelectorAll('.hero-dot');
    slides.forEach((s, i) => s.classList.toggle('active', i === index));
    dots.forEach((d, i)   => d.classList.toggle('active', i === index));
    this.current = index;
  },

  next() { this.goTo((this.current + 1) % this.total); }
};

/* ─────────────────────────────────────────────────────────────
   TOAST NOTIFICATIONS
───────────────────────────────────────────────────────────── */
const Toast = {
  show(message, type = 'success', duration = 3000) {
    const container = document.getElementById('toast-container')
      || (() => {
        const el = document.createElement('div');
        el.id = 'toast-container';
        el.className = 'toast-container';
        document.body.appendChild(el);
        return el;
      })();

    const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ';
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<span>${icon}</span> ${escapeHtml(message)}`;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateX(120%)'; setTimeout(() => toast.remove(), 300); }, duration);
  }
};

/* ─────────────────────────────────────────────────────────────
   AUTH HELPERS
───────────────────────────────────────────────────────────── */
function getToken() { return localStorage.getItem('duhn_token'); }
function setToken(t) { localStorage.setItem('duhn_token', t); }
function clearToken() { localStorage.removeItem('duhn_token'); localStorage.removeItem('duhn_user'); }
function getUser() {
  try { return JSON.parse(localStorage.getItem('duhn_user')); } catch { return null; }
}
function authHeaders() {
  const token = getToken();
  return token ? { 'Authorization': `Bearer ${token}` } : {};
}

/* ─────────────────────────────────────────────────────────────
   NEWSLETTER
───────────────────────────────────────────────────────────── */
async function subscribeNewsletter(e) {
  e.preventDefault();
  const input = e.target.querySelector('input[type=email]');
  const email = input?.value?.trim();
  if (!email) return;

  try {
    const res  = await fetch(`${API}/newsletter/subscribe`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email })
    });
    const json = await res.json();
    Toast.show(json.message || 'Subscribed!', json.success ? 'success' : 'error');
    if (json.success) {
      if (input) input.value = '';
      if (json.data?.wallet_email) {
        localStorage.setItem('duhn_wallet_email', json.data.wallet_email);
        Wallet.show();
        Cart.load(); // refresh cart to show wallet discount
      }
    }
  } catch { Toast.show('Network error', 'error'); }
}

/* ─────────────────────────────────────────────────────────────
   NEWSLETTER POPUP
───────────────────────────────────────────────────────────── */
const NewsletterPopup = {
  STORAGE_KEY: 'duhn_popup_dismissed',
  EXPIRY_DAYS: 7,

  init() {
    const stored  = localStorage.getItem(this.STORAGE_KEY);
    const popup   = document.getElementById('newsletter-popup');
    const delay   = popup ? (parseInt(popup.dataset.delay) || 1800) : 1800;
    const resetAt = parseInt(popup?.dataset.resetAt || '0') * 1000; // server Unix ts → ms

    if (stored) {
      const expiryMs    = parseInt(stored);
      const dismissedMs = expiryMs - (this.EXPIRY_DAYS * 864e5); // when user dismissed
      // Only suppress if: not expired AND dismissed AFTER last admin reset
      if (Date.now() < expiryMs && dismissedMs >= resetAt) return;
      // Otherwise: either expired, or dismissed before admin reset → show again
      if (dismissedMs < resetAt) localStorage.removeItem(this.STORAGE_KEY);
    }
    setTimeout(() => this.show(), delay);
  },

  show() {
    const el = document.getElementById('newsletter-popup');
    if (!el) return;
    el.style.display = 'flex';
    requestAnimationFrame(() => el.classList.add('nl-active'));
  },

  close() {
    const el = document.getElementById('newsletter-popup');
    if (!el) return;
    el.classList.remove('nl-active');
    setTimeout(() => { el.style.display = 'none'; }, 380);
    const expiry = Date.now() + (this.EXPIRY_DAYS * 864e5);
    localStorage.setItem(this.STORAGE_KEY, expiry.toString());
  },

  async submit(e) {
    e.preventDefault();
    const input = document.getElementById('nl-email');
    const email = input?.value?.trim();
    if (!email) return;
    try {
      const res  = await fetch(`${API}/newsletter/subscribe`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email })
      });
      const json = await res.json();
      Toast.show(json.message || 'Subscribed!', json.success ? 'success' : 'error');
      if (json.success) {
        if (input) input.value = '';
        this.close();
        if (json.data?.wallet_email) {
          localStorage.setItem('duhn_wallet_email', json.data.wallet_email);
          Wallet.show();
          Cart.load();
        }
      }
    } catch { Toast.show('Network error', 'error'); }
  }
};

/* ─────────────────────────────────────────────────────────────
   WALLET
───────────────────────────────────────────────────────────── */
const Wallet = {
  init() {
    const email = localStorage.getItem('duhn_wallet_email');
    if (!email) return;
    this.show();
    // Identify to backend to restore session
    fetch(`${API}/wallet/identify`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ email })
    }).then(r => r.json()).then(json => {
      if (!json.success) {
        // Email not a subscriber — clear
        localStorage.removeItem('duhn_wallet_email');
        this.hide();
      } else {
        Cart.fetch(); // refresh cart to apply wallet discount
      }
    }).catch(() => {});
  },

  show() {
    const icon = document.getElementById('wallet-icon');
    if (icon) icon.style.display = '';
  },

  hide() {
    const icon = document.getElementById('wallet-icon');
    if (icon) icon.style.display = 'none';
    const panel = document.getElementById('wallet-panel');
    if (panel) panel.style.display = 'none';
  },

  open(e) {
    if (e) e.preventDefault();
    const panel = document.getElementById('wallet-panel');
    if (!panel) return;
    if (panel.style.display !== 'none') { panel.style.display = 'none'; return; }
    panel.style.display = 'block';
    this.loadPanel();
  },

  close() {
    const panel = document.getElementById('wallet-panel');
    if (panel) panel.style.display = 'none';
  },

  async loadPanel() {
    const contentEl = document.getElementById('wallet-panel-content');
    if (!contentEl) return;
    try {
      const res  = await fetch(`${API}/wallet/status`, { credentials: 'include' });
      const json = await res.json();
      const d    = json.data || {};
      const email = localStorage.getItem('duhn_wallet_email') || d.wallet_email || '';
      if (!d.active) {
        contentEl.innerHTML = `<p style="font-size:12px;color:#aaa">Wallet not active.</p>`;
        return;
      }
      contentEl.innerHTML = `
        <p style="font-size:11px;color:#888;margin-bottom:12px;word-break:break-all">${email}</p>
        <div style="background:rgba(200,160,48,0.08);border:1px solid rgba(200,160,48,0.25);border-radius:8px;padding:14px;margin-bottom:12px;text-align:center">
          <div style="font-size:28px;font-weight:700;color:var(--accent,#F8C417)">${d.wallet_discount || 50} <span style="font-size:14px">EGP</span></div>
          <div style="font-size:10px;color:#aaa;letter-spacing:.08em;text-transform:uppercase;margin-top:4px">discount per new product</div>
        </div>
        <p style="font-size:11px;color:#888;line-height:1.6">
          ${d.used_count > 0 ? `✓ Used on <strong style="color:#ccc">${d.used_count}</strong> product${d.used_count > 1 ? 's' : ''} already.<br>` : ''}
          Your wallet discount applies automatically on every new product you order.
        </p>
        <button onclick="localStorage.removeItem('duhn_wallet_email');Wallet.hide();location.reload()"
                style="margin-top:14px;width:100%;padding:8px;background:none;border:1px solid #333;border-radius:6px;color:#888;font-size:11px;cursor:pointer">
          Sign out of wallet
        </button>
      `;
    } catch {
      contentEl.innerHTML = `<p style="font-size:12px;color:#aaa">Could not load wallet info.</p>`;
    }
  }
};

/* ─────────────────────────────────────────────────────────────
   UTILITY
───────────────────────────────────────────────────────────── */
function escapeHtml(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function renderStars(rating, max = 5) {
  let html = '<div class="stars">';
  for (let i = 1; i <= max; i++) {
    if (i <= Math.floor(rating))      html += '<span>★</span>';
    else if (i - rating < 1)          html += '<span style="opacity:0.5">★</span>';
    else                              html += '<span class="empty">★</span>';
  }
  return html + '</div>';
}

/* ─────────────────────────────────────────────────────────────
   INIT
───────────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  // Hero slider
  Slider.init();

  // Initial cart load
  Cart.fetch();

  // Nav toggle (mobile)
  const toggleBtn = document.getElementById('nav-toggle');
  const navLinks  = document.getElementById('nav-links');
  if (toggleBtn && navLinks) {
    toggleBtn.addEventListener('click', () => {
      navLinks.classList.toggle('open');
    });
  }

  // Search open/close
  document.querySelectorAll('[data-search-open]').forEach(el =>
    el.addEventListener('click', () => Search.open()));

  const searchInput = document.getElementById('search-input');
  if (searchInput) {
    searchInput.addEventListener('input', e => Search.query(e.target.value));
    searchInput.addEventListener('keydown', e => { if (e.key === 'Escape') Search.close(); });
  }

  document.getElementById('search-close')?.addEventListener('click', () => Search.close());

  // Cart open/close
  document.querySelectorAll('[data-cart-open]').forEach(el =>
    el.addEventListener('click', () => { Cart.open(); }));

  document.getElementById('cart-close')?.addEventListener('click', () => Cart.close());
  document.getElementById('cart-backdrop')?.addEventListener('click', () => Cart.close());

  // Newsletter forms
  document.querySelectorAll('.newsletter-form').forEach(form =>
    form.addEventListener('submit', subscribeNewsletter));

  // Update nav auth state
  const user = getUser();
  const accountIcon = document.getElementById('account-icon');
  if (accountIcon && user) {
    accountIcon.title = user.name;
    accountIcon.href  = '/account.php';
  }

  // Newsletter popup
  NewsletterPopup.init();
  Wallet.init();
  document.getElementById('nl-backdrop')?.addEventListener('click', () => NewsletterPopup.close());
  document.getElementById('nl-close')?.addEventListener('click', () => NewsletterPopup.close());
  document.getElementById('nl-skip')?.addEventListener('click', () => NewsletterPopup.close());
  document.getElementById('nl-form')?.addEventListener('submit', e => NewsletterPopup.submit(e));

  // Scroll-to-top button
  const scrollTopBtn = document.getElementById('scroll-top');
  if (scrollTopBtn) {
    window.addEventListener('scroll', () => {
      scrollTopBtn.style.display = window.scrollY > 400 ? 'flex' : 'none';
    }, { passive: true });
    scrollTopBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
  }

  // ── Header behaviour ─────────────────────────────────────────────
  // Homepage: header transparent overlay over hero, turns white on scroll.
  // Other pages: sticky white header always visible.
  const header  = document.getElementById('site-header');
  const annBar  = document.getElementById('announcement-bar');
  const isHome  = document.body.classList.contains('page-home');

  if (header) {
    // Homepage: header is INVISIBLE on load (inline style), appears after scroll
    const REVEAL_THRESHOLD = isHome ? 40 : 60;

    const onScroll = () => {
      const y = window.scrollY;

      if (isHome) {
        if (y > REVEAL_THRESHOLD) {
          // Remove inline opacity:0 so CSS transition can animate
          header.style.opacity = '';
          header.style.pointerEvents = '';
          if (annBar) { annBar.style.opacity = ''; annBar.style.pointerEvents = ''; }
          header.classList.add('header-visible', 'scrolled');
          annBar?.classList.add('header-visible');
        } else {
          header.classList.remove('header-visible', 'scrolled');
          annBar?.classList.remove('header-visible');
        }
      } else {
        // Other pages: glass effect after 60px
        if (y > 60) {
          header.classList.add('scrolled');
        } else {
          header.classList.remove('scrolled');
        }
      }
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll(); // run once on load to set correct initial state
  }
});

/* ── Marquee animation restart ────────────────────────────────────────
   iOS Safari pauses CSS animations on bfcache restore and tab-switch.
   Uses getComputedStyle so it always respects the admin-set speed
   (inline animation-duration) rather than the CSS default 32s. */
function restartMarquee() {
  const annInner = document.querySelector('.ann-marquee-inner');
  if (!annInner) return;
  // Read the ACTUAL computed duration before touching anything.
  // getComputedStyle correctly resolves inline style vs CSS class,
  // so the admin's slow speed is preserved — not reset to 32s default.
  const dur = getComputedStyle(annInner).animationDuration || '32s';
  annInner.style.animation = 'none';
  void annInner.offsetWidth; // force reflow — required for restart
  // Rebuild the full shorthand with the correct duration
  annInner.style.animation = `ann-scroll ${dur} linear infinite`;
}

/* ── Fix: announcement bar & header invisible on browser back button ── */
window.addEventListener('pageshow', function(e) {
  // e.persisted = true when page is restored from bfcache (back/forward)
  if (!e.persisted) return;

  const annBar = document.getElementById('announcement-bar');
  const header  = document.getElementById('site-header');
  const isHome  = document.body.classList.contains('page-home');

  if (isHome && window.scrollY > 40) {
    // Already scrolled past threshold — immediately reveal both elements
    if (annBar) { annBar.style.opacity = ''; annBar.style.pointerEvents = ''; }
    if (header)  { header.style.opacity  = ''; header.style.pointerEvents  = ''; }
  } else if (isHome) {
    // At top of homepage — re-trigger scroll handler so reveal logic runs
    window.dispatchEvent(new Event('scroll'));
  } else {
    // Non-home pages: ensure header and announcement bar are always visible
    if (annBar) { annBar.style.opacity = ''; annBar.style.pointerEvents = ''; }
    if (header)  { header.style.opacity  = ''; header.style.pointerEvents  = ''; }
  }

  // Always restart marquee animation after bfcache restore
  restartMarquee();
});

/* ── Fix: marquee animation pauses when tab goes to background ─────── */
document.addEventListener('visibilitychange', function() {
  if (document.visibilityState === 'visible') {
    restartMarquee();
  }
});
