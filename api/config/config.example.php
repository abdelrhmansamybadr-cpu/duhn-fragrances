<?php
/**
 * DUHN FRAGRANCES — Global Config EXAMPLE
 * ─────────────────────────────────────────
 * Copy this file to config.php and fill in your real values.
 * NEVER commit config.php to version control.
 */

define('APP_NAME', 'DUHN FRAGRANCES');

$_currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost';

if (str_contains($_currentHost, 'quadrocloud.net') || str_contains($_currentHost, 'duhnfragrances.com')) {
    define('APP_ENV', 'production');
} else {
    define('APP_ENV', 'local');
}

// ── Database ──────────────────────────────────────────────────────
if (APP_ENV === 'production') {
    $_ENV['DB_HOST'] = 'localhost';
    $_ENV['DB_PORT'] = '3306';

    if (str_contains($_currentHost, 'duhnfragrances.com')) {
        define('APP_URL', 'https://duhnfragrances.com');
        $_ENV['DB_NAME'] = 'YOUR_LIVE_DB_NAME';
        $_ENV['DB_USER'] = 'YOUR_LIVE_DB_USER';
        $_ENV['DB_PASS'] = 'YOUR_LIVE_DB_PASSWORD';
    } else {
        define('APP_URL', 'https://your-staging-domain.com');
        $_ENV['DB_NAME'] = 'YOUR_STAGING_DB_NAME';
        $_ENV['DB_USER'] = 'YOUR_STAGING_DB_USER';
        $_ENV['DB_PASS'] = 'YOUR_STAGING_DB_PASSWORD';
    }
} else {
    define('APP_URL', 'http://localhost:8080');
    $_ENV['DB_HOST'] = 'localhost';
    $_ENV['DB_NAME'] = 'duhn_db';
    $_ENV['DB_USER'] = 'root';
    $_ENV['DB_PASS'] = '';
    $_ENV['DB_PORT'] = '3306';
}

// ── JWT ───────────────────────────────────────────────────────────
// Generate a strong random secret: openssl rand -hex 32
define('JWT_SECRET', 'YOUR_STRONG_RANDOM_JWT_SECRET_HERE');
define('JWT_EXPIRY', 7 * 24 * 3600);

// ── Uploads ───────────────────────────────────────────────────────
define('UPLOAD_DIR', __DIR__ . '/../../api/uploads/products/');
define('UPLOAD_URL', APP_URL . '/api/uploads/products/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);

// ── Rate Limiting ─────────────────────────────────────────────────
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

// ── Admin ─────────────────────────────────────────────────────────
define('ADMIN_SESSION_NAME', 'duhn_admin');
define('ADMIN_URL', APP_URL . '/admin');
