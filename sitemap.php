<?php
/**
 * DUHN FRAGRANCES — Dynamic XML Sitemap
 * Accessible at: https://duhnfragrances.com/sitemap.xml
 */
require_once __DIR__ . '/api/config/config.php';
require_once __DIR__ . '/api/config/database.php';

header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');

$base    = 'https://duhnfragrances.com';
$today   = date('Y-m-d');

// ── Static pages ─────────────────────────────────────────────────────────
$static = [
    ['loc' => '/',                   'priority' => '1.0', 'freq' => 'daily'],
    ['loc' => '/collections.php',    'priority' => '0.9', 'freq' => 'daily'],
    ['loc' => '/about.php',          'priority' => '0.7', 'freq' => 'monthly'],
    ['loc' => '/shipping-policy.php','priority' => '0.5', 'freq' => 'monthly'],
    ['loc' => '/exchange-policy.php','priority' => '0.5', 'freq' => 'monthly'],
    ['loc' => '/refill-policy.php',  'priority' => '0.5', 'freq' => 'monthly'],
    ['loc' => '/contact.php',        'priority' => '0.6', 'freq' => 'monthly'],
    ['loc' => '/inspo.php?id=1',     'priority' => '0.6', 'freq' => 'weekly'],
    ['loc' => '/inspo.php?id=2',     'priority' => '0.6', 'freq' => 'weekly'],
    ['loc' => '/inspo.php?id=3',     'priority' => '0.6', 'freq' => 'weekly'],
];

// ── Dynamic: Collections ─────────────────────────────────────────────────
$collections = [];
$products    = [];
try {
    $db = Database::getInstance();
    $collections = $db->query(
        "SELECT slug, updated_at FROM collections WHERE is_active = 1 ORDER BY sort_order ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    $products = $db->query(
        "SELECT slug, updated_at FROM products WHERE stock_qty > 0 ORDER BY created_at DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// ── Output XML ───────────────────────────────────────────────────────────
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

// Static pages
foreach ($static as $page) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($base . $page['loc']) . "</loc>\n";
    echo "    <lastmod>{$today}</lastmod>\n";
    echo "    <changefreq>{$page['freq']}</changefreq>\n";
    echo "    <priority>{$page['priority']}</priority>\n";
    echo "  </url>\n";
}

// Collections
foreach ($collections as $col) {
    $lastmod = !empty($col['updated_at']) ? date('Y-m-d', strtotime($col['updated_at'])) : $today;
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($base . '/collections/' . $col['slug']) . "</loc>\n";
    echo "    <lastmod>{$lastmod}</lastmod>\n";
    echo "    <changefreq>weekly</changefreq>\n";
    echo "    <priority>0.8</priority>\n";
    echo "  </url>\n";
}

// Products
foreach ($products as $prod) {
    $lastmod = !empty($prod['updated_at']) ? date('Y-m-d', strtotime($prod['updated_at'])) : $today;
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($base . '/product/' . $prod['slug']) . "</loc>\n";
    echo "    <lastmod>{$lastmod}</lastmod>\n";
    echo "    <changefreq>weekly</changefreq>\n";
    echo "    <priority>0.85</priority>\n";
    echo "  </url>\n";
}

echo '</urlset>';
