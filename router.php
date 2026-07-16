<?php
/**
 * DUHN FRAGRANCES — PHP Built-in Server Router
 * Usage: php -S localhost:8080 router.php
 *
 * Simulates .htaccess rewrite rules for local development.
 * Do NOT upload this file to production (Hostinger uses Apache + .htaccess).
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Serve real files (CSS, JS, images, etc.) directly
$filePath = __DIR__ . $uri;
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    return false; // Let PHP built-in server handle static files
}

// Route /api/* → api/index.php
if (str_starts_with($uri, '/api/')) {
    // Strip /api prefix and set PATH_INFO
    $_SERVER['PATH_INFO']    = substr($uri, 4) ?: '/';
    $_SERVER['SCRIPT_NAME']  = '/api/index.php';
    require __DIR__ . '/api/index.php';
    return true;
}

// Clean product URLs: /product/slug → product.php?slug=slug
if (preg_match('#^/product/([a-z0-9-]+)/?$#', $uri, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/product.php';
    return true;
}

// Clean collection URLs: /collections/slug → collections.php?slug=slug
if (preg_match('#^/collections/([a-z0-9-]+)/?$#', $uri, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/collections.php';
    return true;
}

// Map clean paths to PHP files
$routes = [
    '/'                   => 'index.php',
    '/index.php'          => 'index.php',
    '/collections.php'    => 'collections.php',
    '/collections'        => 'collections.php',
    '/product.php'        => 'product.php',
    '/checkout.php'       => 'checkout.php',
    '/checkout'           => 'checkout.php',
    '/order-confirmation.php' => 'order-confirmation.php',
    '/contact.php'        => 'contact.php',
    '/contact'            => 'contact.php',
    '/about.php'          => 'about.php',
    '/about'              => 'about.php',
    '/account.php'        => 'account.php',
    '/account'            => 'account.php',
    '/shipping-policy.php'  => 'shipping-policy.php',
    '/shipping-policy'      => 'shipping-policy.php',
    '/exchange-policy.php'  => 'exchange-policy.php',
    '/exchange-policy'      => 'exchange-policy.php',
];

if (isset($routes[$uri])) {
    require __DIR__ . '/' . $routes[$uri];
    return true;
}

// Admin routes — pass through to the PHP files
if (str_starts_with($uri, '/admin')) {
    $adminFile = __DIR__ . $uri;
    // Default to index.php for /admin or /admin/
    if (is_dir($adminFile)) {
        $adminFile .= '/index.php';
    }
    if (!str_ends_with($adminFile, '.php')) {
        $adminFile .= '.php';
    }
    if (file_exists($adminFile)) {
        require $adminFile;
        return true;
    }
}

// 404
http_response_code(404);
require __DIR__ . '/404.php';
return true;
