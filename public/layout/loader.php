<!-- ══ PAGE LOADER ══════════════════════════════════════════════ -->
<div id="page-loader" aria-hidden="true">

  <!-- Spray particles -->
  <div class="ldr-particles">
    <span class="ldr-p ldr-p1"></span>
    <span class="ldr-p ldr-p2"></span>
    <span class="ldr-p ldr-p3"></span>
    <span class="ldr-p ldr-p4"></span>
    <span class="ldr-p ldr-p5"></span>
    <span class="ldr-p ldr-p6"></span>
    <span class="ldr-p ldr-p7"></span>
    <span class="ldr-p ldr-p8"></span>
    <span class="ldr-p ldr-p9"></span>
    <span class="ldr-p ldr-p10"></span>
    <span class="ldr-p ldr-p11"></span>
    <span class="ldr-p ldr-p12"></span>
  </div>

  <!-- ══ CIRCULAR ANIMATION (current) ══════════════════════════ -->
  <div class="ldr-circle-wrap">
    <img class="ldr-circle" src="/public/images/loader_final.gif" alt="" aria-hidden="true">
  </div>

  <!-- ══ ORIGINAL BOTTLE SVG — kept here for instant revert ════
  <div class="ldr-bottle-wrap">
    <svg class="ldr-bottle" viewBox="0 0 160 300" fill="none" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="ldrGlass" x1="0" y1="0" x2="1" y2="0">
          <stop offset="0%"   stop-color="#c8d8e8" stop-opacity="0.55"/>
          <stop offset="18%"  stop-color="#e8f2fa" stop-opacity="0.72"/>
          <stop offset="42%"  stop-color="#f4f9ff" stop-opacity="0.85"/>
          <stop offset="62%"  stop-color="#ddeeff" stop-opacity="0.65"/>
          <stop offset="82%"  stop-color="#b8cfe0" stop-opacity="0.55"/>
          <stop offset="100%" stop-color="#90aec4" stop-opacity="0.70"/>
        </linearGradient>
        <linearGradient id="ldrGold" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%"   stop-color="#f5e18a"/>
          <stop offset="40%"  stop-color="#c8960c"/>
          <stop offset="100%" stop-color="#8a6200"/>
        </linearGradient>
        <linearGradient id="ldrGoldH" x1="0" y1="0" x2="1" y2="0">
          <stop offset="0%"   stop-color="#f5e18a"/>
          <stop offset="50%"  stop-color="#c8960c"/>
          <stop offset="100%" stop-color="#8a6200"/>
        </linearGradient>
        <linearGradient id="ldrShimmer" x1="0" y1="0" x2="1" y2="0">
          <stop offset="0%"   stop-color="white" stop-opacity="0"/>
          <stop offset="50%"  stop-color="white" stop-opacity="0.18"/>
          <stop offset="100%" stop-color="white" stop-opacity="0"/>
        </linearGradient>
        <linearGradient id="ldrRefl" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%"   stop-color="white" stop-opacity="0.70"/>
          <stop offset="40%"  stop-color="white" stop-opacity="0.25"/>
          <stop offset="100%" stop-color="white" stop-opacity="0"/>
        </linearGradient>
        <radialGradient id="ldrGlow" cx="50%" cy="50%" r="50%">
          <stop offset="0%"   stop-color="#C8A030" stop-opacity="0.35"/>
          <stop offset="100%" stop-color="#C8A030" stop-opacity="0"/>
        </radialGradient>
        <filter id="ldrBlur">
          <feGaussianBlur stdDeviation="2.5"/>
        </filter>
        <clipPath id="ldrBodyClip">
          <rect x="22" y="108" width="116" height="168" rx="16"/>
        </clipPath>
      </defs>
      <ellipse class="ldr-glow-aura" cx="80" cy="200" rx="70" ry="80" fill="url(#ldrGlow)" filter="url(#ldrBlur)"/>
      <rect x="73" y="2" width="14" height="32" rx="5" fill="url(#ldrGold)"/>
      <rect x="75" y="4" width="4" height="26" rx="2" fill="white" opacity="0.25"/>
      <rect x="56" y="28" width="48" height="14" rx="5" fill="url(#ldrGoldH)"/>
      <rect x="58" y="30" width="14" height="10" rx="3" fill="white" opacity="0.2"/>
      <rect x="46" y="38" width="68" height="64" rx="10" fill="url(#ldrGold)"/>
      <rect x="50" y="42" width="16" height="56" rx="8" fill="white" opacity="0.18"/>
      <ellipse cx="80" cy="44" rx="22" ry="5" fill="white" opacity="0.12"/>
      <rect x="38" y="98" width="84" height="14" rx="5" fill="url(#ldrGoldH)"/>
      <rect x="40" y="100" width="24" height="10" rx="3" fill="white" opacity="0.2"/>
      <rect x="50" y="108" width="60" height="22" rx="4" fill="url(#ldrGlass)"/>
      <rect x="53" y="110" width="10" height="18" rx="3" fill="white" opacity="0.45"/>
      <rect x="22" y="126" width="116" height="150" rx="16" fill="url(#ldrGlass)"/>
      <rect x="26" y="130" width="16" height="140" rx="8" fill="url(#ldrRefl)" opacity="0.55" clip-path="url(#ldrBodyClip)"/>
      <rect x="122" y="130" width="12" height="140" rx="6" fill="#7aaac8" opacity="0.35" clip-path="url(#ldrBodyClip)"/>
      <rect class="ldr-shimmer" x="22" y="126" width="116" height="150" rx="16" fill="url(#ldrShimmer)" clip-path="url(#ldrBodyClip)"/>
      <rect x="34" y="144" width="92" height="96" rx="7" fill="none" stroke="rgba(200,160,48,0.45)" stroke-width="1"/>
      <rect x="36" y="146" width="88" height="92" rx="6" fill="rgba(255,255,255,0.55)"/>
      <line x1="50" y1="160" x2="110" y2="160" stroke="rgba(200,160,48,0.6)" stroke-width="0.8"/>
      <text x="80" y="188" text-anchor="middle" font-family="Georgia,serif" font-size="24" font-weight="700" fill="#1a1a1a" letter-spacing="8">DUHN</text>
      <text x="80" y="206" text-anchor="middle" font-family="Arial,Helvetica,sans-serif" font-size="7" font-weight="400" fill="rgba(60,50,20,0.85)" letter-spacing="4">FRAGRANCES</text>
      <line x1="50" y1="220" x2="110" y2="220" stroke="rgba(200,160,48,0.6)" stroke-width="0.8"/>
      <rect x="22" y="268" width="116" height="8" rx="4" fill="url(#ldrGoldH)" opacity="0.75"/>
      <ellipse cx="80" cy="277" rx="16" ry="3" fill="white" opacity="0.1"/>
    </svg>
  </div>
  ══════════════════════════════════════════════════════════════ -->

  <!-- Brand text -->
  <div class="ldr-brand">
    <p class="ldr-brand-name">DUHN FRAGRANCES</p>
    <p class="ldr-brand-sub">Indulge Your Senses</p>
  </div>

  <!-- Loading dots -->
  <div class="ldr-dots">
    <span></span><span></span><span></span>
  </div>

</div>
<!-- ══ END LOADER ════════════════════════════════════════════════ -->
