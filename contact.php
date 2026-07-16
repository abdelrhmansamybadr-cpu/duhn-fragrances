<?php
$pageTitle = 'Contact Us — DUHN FRAGRANCES';
require_once __DIR__ . '/public/layout/header.php';
?>

<div class="container" style="padding:60px 20px;max-width:800px">
  <h1 class="section-title" style="margin-bottom:8px">Contact Us</h1>
  <p style="color:var(--text-muted);margin-bottom:40px">Do you have any question? We're here to help.</p>

  <div class="contact-grid">
    <!-- Form -->
    <form id="contact-form" onsubmit="submitContact(event)">
      <div style="display:flex;flex-direction:column;gap:18px">
        <div class="form-group">
          <label class="form-label">Name *</label>
          <input type="text" name="name" class="form-control" placeholder="Your name" required>
        </div>
        <div class="form-group">
          <label class="form-label">Email *</label>
          <input type="email" name="email" class="form-control" placeholder="your@email.com" required>
        </div>
        <div class="form-group">
          <label class="form-label">Message *</label>
          <textarea name="message" class="form-control" rows="5" placeholder="How can we help you?" required style="resize:vertical"></textarea>
        </div>
        <div id="contact-msg" style="display:none;padding:12px;border-radius:8px;font-size:13px"></div>
        <button type="submit" class="btn btn-gold">SEND MESSAGE</button>
      </div>
    </form>

    <!-- Info -->
    <div style="display:flex;flex-direction:column;gap:24px">
      <div>
        <h3 style="font-size:14px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-muted);margin-bottom:10px">WhatsApp</h3>
        <a href="https://wa.me/201157879622" target="_blank"
           class="btn btn-outline" style="width:auto;justify-content:flex-start;gap:10px">
          <i class="ph ph-whatsapp-logo" style="color:#25D366;font-size:20px"></i>
          +20 155 654 6708
        </a>
      </div>

      <div>
        <h3 style="font-size:14px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-muted);margin-bottom:10px">Follow Us</h3>
        <div style="display:flex;gap:10px">
          <a href="https://www.facebook.com/duhnfragrances" target="_blank" class="social-link"><i class="ph ph-facebook-logo"></i></a>
          <a href="https://www.instagram.com/duhnfragrances" target="_blank" class="social-link"><i class="ph ph-instagram-logo"></i></a>
          <a href="https://www.tiktok.com/@duhnfragrances" target="_blank" class="social-link"><i class="ph ph-tiktok-logo"></i></a>
        </div>
      </div>

      <div>
        <h3 style="font-size:14px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-muted);margin-bottom:10px">Newsletter</h3>
        <form class="newsletter-form" style="display:flex;gap:8px">
          <input type="email" class="form-control" placeholder="Your email" required style="flex:1">
          <button type="submit" class="btn btn-gold" style="flex-shrink:0">Subscribe</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php
$extraScripts = <<<'JS'
<script>
async function submitContact(e) {
  e.preventDefault();
  const form  = e.target;
  const msgEl = document.getElementById('contact-msg');
  const data  = {
    name:    form.name.value.trim(),
    email:   form.email.value.trim(),
    message: form.message.value.trim()
  };

  try {
    const res  = await fetch(`${API}/contact`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    const json = await res.json();

    msgEl.style.display = 'block';
    if (json.success) {
      msgEl.style.background = 'rgba(40,167,69,0.1)';
      msgEl.style.color      = '#28A745';
      msgEl.textContent      = '✓ Message sent! We\'ll get back to you soon.';
      form.reset();
    } else {
      msgEl.style.background = 'rgba(220,53,69,0.1)';
      msgEl.style.color      = 'var(--error)';
      msgEl.textContent      = json.message || 'Failed to send message.';
    }
  } catch {
    msgEl.style.display    = 'block';
    msgEl.style.background = 'rgba(220,53,69,0.1)';
    msgEl.style.color      = 'var(--error)';
    msgEl.textContent      = 'Network error. Please try again.';
  }
}
</script>
JS;
require_once __DIR__ . '/public/layout/footer.php';
?>
