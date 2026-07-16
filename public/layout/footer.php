</main>
<!-- ── Site Footer ──────────────────────────────────────────── -->
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <!-- Brand -->
      <div class="footer-brand">
        <div class="logo">DUHN <span>FRAGRANCES</span></div>
        <p class="footer-tagline">Indulge Your Senses.<br>You only get one chance to make a first impression.</p>
        <p class="footer-quote">"Wear a scent that speaks before you do."</p>
        <div class="footer-socials">
          <a href="https://www.facebook.com/duhnfragrances" target="_blank" rel="noopener" class="social-link" aria-label="Facebook">
            <i class="ph ph-facebook-logo"></i>
          </a>
          <a href="https://www.instagram.com/duhnfragrances" target="_blank" rel="noopener" class="social-link" aria-label="Instagram">
            <i class="ph ph-instagram-logo"></i>
          </a>
          <a href="https://www.tiktok.com/@duhnfragrances" target="_blank" rel="noopener" class="social-link" aria-label="TikTok">
            <i class="ph ph-tiktok-logo"></i>
          </a>
          <a href="https://wa.me/201157879622" target="_blank" rel="noopener" class="social-link" aria-label="WhatsApp">
            <i class="ph ph-whatsapp-logo"></i>
          </a>
        </div>
      </div>

      <!-- Shop -->
      <div>
        <h4 class="footer-heading">Shop</h4>
        <ul class="footer-links">
          <li><a href="/collections.php?slug=new-drops">New Drops</a></li>
          <li><a href="/collections.php?slug=bestsellers">Bestsellers</a></li>
          <li><a href="/collections.php?slug=for-him">For Him</a></li>
          <li><a href="/collections.php?slug=for-her">For Her</a></li>
          <li><a href="/collections.php">All Collections</a></li>
        </ul>
      </div>

      <!-- Info -->
      <div>
        <h4 class="footer-heading">Information</h4>
        <ul class="footer-links">
          <li><a href="/about.php">About DUHN FRAGRANCES</a></li>
          <li><a href="/contact.php">Contact Us</a></li>
          <li><a href="/shipping-policy.php">Shipping Policy</a></li>
          <li><a href="/exchange-policy.php">Exchange Policy</a></li>
          <li><a href="/refill-policy.php">Refill Policy</a></li>
        </ul>
      </div>

      <!-- Newsletter -->
      <div>
        <h4 class="footer-heading">Stay Updated</h4>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:12px">Sign up for new arrivals and exclusive offers.</p>
        <form class="footer-newsletter newsletter-form">
          <input type="email" placeholder="Your email" required>
          <button type="submit" class="btn btn-gold" style="padding:12px 16px;flex-shrink:0">→</button>
        </form>
      </div>
    </div>

    <!-- Bottom Bar -->
    <div class="footer-bottom">
      <span>© <?= date('Y') ?> DUHN FRAGRANCES. All rights reserved.</span>
      <span>Crafted with passion in Egypt 🇪🇬</span>
    </div>
  </div>
</footer>

<!-- Main JS -->
<script src="/public/js/app.js?v=<?= filemtime(__DIR__.'/../js/app.js') ?>"></script>
<?= $extraScripts ?? '' ?>
</body>
</html>
