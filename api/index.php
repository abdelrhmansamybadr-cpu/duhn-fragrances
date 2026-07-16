<?php
/**
 * DUHN FRAGRANCES — API Entry Point & Router
 * All /api/* requests are routed here via .htaccess
 */

// Buffer everything so PHP notices/warnings never corrupt JSON
ob_start();
error_reporting(0);
ini_set('display_errors', '0');

// Catch fatal errors and return clean JSON instead of blank/HTML page
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'error' => 'SERVER_ERROR', 'message' => 'A server error occurred.']);
    }
});

// ── Headers ──────────────────────────────────────────────────────
header('Content-Type: application/json; charset=UTF-8');
$_allowOrigin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
header('Access-Control-Allow-Origin: ' . $_allowOrigin);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-API-Key');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Session — start early so all routes share the same session ───
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ── Bootstrap ────────────────────────────────────────────────────
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/ResponseHelper.php';

// ── Routing ───────────────────────────────────────────────────────
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = rtrim(preg_replace('#^/api#', '', $uri), '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'];
$parts  = array_values(array_filter(explode('/', trim($uri, '/'))));
$base   = $parts[0] ?? '';
$sub    = $parts[1] ?? '';
$id     = $parts[2] ?? null;

try {
    // ── Products ──────────────────────────────────────────────────
    if ($base === 'products') {
        // Live view count: GET /api/products/views/{id}
        if ($method === 'GET' && $sub === 'views' && $id !== null) {
            $db  = Database::getInstance();
            $row = $db->prepare("SELECT views FROM products WHERE id = :id LIMIT 1");
            $row->execute([':id' => (int)$id]);
            $r = $row->fetch();
            ResponseHelper::success(['views' => $r ? max(1, (int)$r['views']) : 1]);
        } else {
            require_once __DIR__ . '/controllers/ProductController.php';
            $ctrl = new ProductController();
            match(true) {
                $method === 'GET' && $sub === ''            => $ctrl->index(),
                $method === 'GET' && $sub === 'featured'    => $ctrl->featured(),
                $method === 'GET' && $sub === 'new-drops'   => $ctrl->newDrops(),
                $method === 'GET' && $sub === 'bestsellers' => $ctrl->bestsellers(),
                $method === 'GET' && $sub === 'search'      => $ctrl->search(),
                $method === 'GET' && $sub !== ''            => $ctrl->show($sub),
                default => ResponseHelper::notFound("Route not found: $method /api/$base/$sub"),
            };
        }
    }

    // ── Collections ───────────────────────────────────────────────
    elseif ($base === 'collections') {
        require_once __DIR__ . '/controllers/CollectionController.php';
        $ctrl = new CollectionController();
        match(true) {
            $method === 'GET' && $sub === '' => $ctrl->index(),
            $method === 'GET' && $sub !== '' => $ctrl->show($sub),
            default => ResponseHelper::notFound(),
        };
    }

    // ── Cart ──────────────────────────────────────────────────────
    elseif ($base === 'cart') {
        require_once __DIR__ . '/controllers/CartController.php';
        $ctrl = new CartController();
        match(true) {
            $method === 'GET'    && $sub === ''       => $ctrl->index(),
            $method === 'POST'   && $sub === 'add'    => $ctrl->add(),
            $method === 'PUT'    && $sub === 'update' => $ctrl->update(),
            $method === 'DELETE' && $sub === 'clear'  => $ctrl->clear(),
            $method === 'DELETE' && $sub === 'remove' => $ctrl->remove((int)$id),
            default => ResponseHelper::notFound(),
        };
    }

    // ── Orders ────────────────────────────────────────────────────
    elseif ($base === 'orders') {
        require_once __DIR__ . '/controllers/OrderController.php';
        $ctrl = new OrderController();
        match(true) {
            $method === 'POST' && $sub === ''  => $ctrl->store(),
            $method === 'GET'  && $sub === ''  => $ctrl->index(),
            $method === 'GET'  && $sub !== ''  => $ctrl->show((int)$sub),
            default => ResponseHelper::notFound(),
        };
    }

    // ── Auth ──────────────────────────────────────────────────────
    elseif ($base === 'auth') {
        require_once __DIR__ . '/controllers/AuthController.php';
        $ctrl = new AuthController();
        match(true) {
            $method === 'POST' && $sub === 'register'     => $ctrl->register(),
            $method === 'POST' && $sub === 'login'        => $ctrl->login(),
            $method === 'POST' && $sub === 'first-login'  => $ctrl->firstLogin(),
            $method === 'GET'  && $sub === 'profile'      => $ctrl->profile(),
            $method === 'PUT'  && $sub === 'profile'      => $ctrl->updateProfile(),
            $method === 'POST' && $sub === 'set-password' => $ctrl->setPassword(),
            default => ResponseHelper::notFound(),
        };
    }

    // ── Contact ───────────────────────────────────────────────────
    elseif ($base === 'contact' && $method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        require_once __DIR__ . '/helpers/ValidationHelper.php';
        $v = (new ValidationHelper($body))
            ->required('name')->required('email')->required('message')
            ->email('email')->maxLength('message', 2000);

        if (!$v->passes()) ResponseHelper::error('VALIDATION_ERROR', 'Fix errors', 422, $v->errors());

        $db  = Database::getInstance();
        $ins = $db->prepare("INSERT INTO contact_messages (name, email, message) VALUES (:n,:e,:m)");
        $ins->execute([
            ':n' => ValidationHelper::sanitize($body['name']),
            ':e' => filter_var($body['email'], FILTER_SANITIZE_EMAIL),
            ':m' => ValidationHelper::sanitize($body['message']),
        ]);
        ResponseHelper::success(null, 'Message sent successfully!', 201);
    }

    // ── Promo Codes ───────────────────────────────────────────────
    elseif ($base === 'promo') {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $db = Database::getInstance();

        if ($method === 'POST' && $sub === 'apply') {
            $body  = json_decode(file_get_contents('php://input'), true) ?? [];
            $code  = strtoupper(trim($body['code'] ?? ''));
            $total = (float)($body['cart_total'] ?? 0);

            if (!$code) ResponseHelper::error('MISSING_CODE', 'Please enter a promo code.', 400);

            $row = $db->prepare("SELECT * FROM promo_codes WHERE code = :c LIMIT 1");
            $row->execute([':c' => $code]);
            $promo = $row->fetch();

            if (!$promo)
                ResponseHelper::error('INVALID_CODE', 'Promo code not found.', 404);
            if (!$promo['is_active'])
                ResponseHelper::error('INACTIVE_CODE', 'This promo code is inactive.', 400);
            if ($promo['expires_at'] && $promo['expires_at'] < date('Y-m-d'))
                ResponseHelper::error('EXPIRED_CODE', 'This promo code has expired.', 400);
            if ($promo['max_uses'] && $promo['used_count'] >= $promo['max_uses'])
                ResponseHelper::error('MAX_USES', 'This promo code has reached its usage limit.', 400);
            if ($promo['min_order'] > 0 && $total < $promo['min_order'])
                ResponseHelper::error('MIN_ORDER', "Minimum order of {$promo['min_order']} EGP required.", 400);

            // ── Per-product check ─────────────────────────────────────
            if (!empty($promo['per_product'])) {
                try {
                    $cartToken = $_SESSION['cart_token'] ?? null;
                    if ($cartToken) {
                        $cartRowQ = $db->prepare("SELECT id FROM carts WHERE session_token = :t LIMIT 1");
                        $cartRowQ->execute([':t' => $cartToken]);
                        $cartRowD = $cartRowQ->fetch();
                        if ($cartRowD) {
                            $ciQ = $db->prepare("SELECT ci.product_id, p.name FROM cart_items ci JOIN products p ON p.id=ci.product_id WHERE ci.cart_id=:cid");
                            $ciQ->execute([':cid' => $cartRowD['id']]);
                            $blocked = [];
                            foreach ($ciQ->fetchAll() as $ci) {
                                $uQ = $db->prepare("SELECT use_count FROM promo_code_product_uses WHERE promo_code_id=:pid AND product_id=:prod LIMIT 1");
                                $uQ->execute([':pid' => $promo['id'], ':prod' => $ci['product_id']]);
                                $uR = $uQ->fetch();
                                if ($uR && (int)$uR['use_count'] >= 1) $blocked[] = $ci['name'];
                            }
                            if (!empty($blocked))
                                ResponseHelper::error('PER_PRODUCT_USED', 'This code was already used for: ' . implode(', ', $blocked) . '. It cannot be applied again for the same product.', 400);
                        }
                    }
                } catch (Throwable $_ppE) { /* table may not exist yet — skip */ }
            }

            $discount = $promo['type'] === 'percent'
                ? round($total * ($promo['value'] / 100), 2)
                : min((float)$promo['value'], $total);

            $_SESSION['promo_code']     = $code;
            $_SESSION['promo_discount'] = $discount;
            $_SESSION['promo_type']     = $promo['type'];
            $_SESSION['promo_value']    = $promo['value'];

            ResponseHelper::success([
                'code'            => $code,
                'type'            => $promo['type'],
                'value'           => $promo['value'],
                'discount_amount' => number_format($discount, 2, '.', ''),
                'message'         => $promo['type'] === 'percent'
                    ? number_format($promo['value'],0).'% discount applied!'
                    : number_format($promo['value'],0).' EGP discount applied!',
            ]);
        }

        elseif ($method === 'POST' && $sub === 'remove') {
            unset($_SESSION['promo_code'], $_SESSION['promo_discount'], $_SESSION['promo_type'], $_SESSION['promo_value']);
            ResponseHelper::success(null, 'Promo code removed.');
        }

        else ResponseHelper::notFound();
    }

    // ── Newsletter ────────────────────────────────────────────────
    elseif ($base === 'newsletter' && $sub === 'subscribe' && $method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($body['email']) || !filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
            ResponseHelper::error('INVALID_EMAIL', 'Please enter a valid email.', 400);
        }

        $db    = Database::getInstance();
        $email = strtolower(trim($body['email']));

        // Ensure new columns exist (idempotent auto-migration)
        try { $db->exec("ALTER TABLE newsletter_subscribers ADD COLUMN promo_code VARCHAR(30) DEFAULT NULL"); } catch (Throwable $_) {}
        try { $db->exec("ALTER TABLE newsletter_subscribers ADD COLUMN email_sent TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $_) {}
        // Abandoned cart: add guest_email to carts table
        try { $db->exec("ALTER TABLE carts ADD COLUMN guest_email VARCHAR(191) DEFAULT NULL"); } catch (Throwable $_) {}
        try { $db->exec("ALTER TABLE carts ADD COLUMN guest_name VARCHAR(120) DEFAULT NULL"); } catch (Throwable $_) {}

        // Check if already subscribed
        $existing = $db->prepare("SELECT promo_code, email_sent FROM newsletter_subscribers WHERE email = :e");
        $existing->execute([':e' => $email]);
        $row = $existing->fetch();

        if ($row) {
            // Already subscribed — resend their code if email failed before
            if ($row['promo_code'] && !$row['email_sent']) {
                require_once __DIR__ . '/helpers/Mailer.php';
                $sent = Mailer::sendWelcomePromoEmail($email, $row['promo_code']);
                if ($sent) {
                    $db->prepare("UPDATE newsletter_subscribers SET email_sent = 1 WHERE email = :e")
                       ->execute([':e' => $email]);
                }
            }
            // Set session wallet email for returning subscriber
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['wallet_email'] = $email;

        $walletEnabled2  = '1';
        $walletDiscount2 = 50;
        try {
            $wRows2 = $db->query("SELECT `key`,`value` FROM settings WHERE `key` IN ('wallet_enabled','wallet_discount_per_product')")->fetchAll();
            foreach ($wRows2 as $wR2) {
                if ($wR2['key'] === 'wallet_enabled') $walletEnabled2 = $wR2['value'];
                if ($wR2['key'] === 'wallet_discount_per_product') $walletDiscount2 = (int)$wR2['value'];
            }
        } catch (Throwable $_) {}

        ResponseHelper::success([
            'already'          => true,
            'wallet_enabled'   => $walletEnabled2 === '1',
            'wallet_discount'  => $walletDiscount2,
            'wallet_email'     => $email,
        ], 'You are already subscribed! Check your inbox for your discount code.');
        }

        // New subscriber — generate unique one-time promo code
        $code = 'WELCOME20-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));

        // Insert promo code (single-use, 20% off, no minimum, never expires)
        $db->prepare("
            INSERT INTO promo_codes (code, description, type, value, min_order, max_uses, is_active)
            VALUES (:code, :desc, 'percent', 20, 0, 1, 1)
        ")->execute([
            ':code' => $code,
            ':desc' => 'Newsletter welcome — ' . $email,
        ]);

        // Insert subscriber with their promo code
        $db->prepare("INSERT INTO newsletter_subscribers (email, promo_code, email_sent) VALUES (:e, :c, 0)")
           ->execute([':e' => $email, ':c' => $code]);

        // Send welcome email
        require_once __DIR__ . '/helpers/Mailer.php';
        $sent = Mailer::sendWelcomePromoEmail($email, $code);

        // Mark email as sent
        if ($sent) {
            $db->prepare("UPDATE newsletter_subscribers SET email_sent = 1 WHERE email = :e")
               ->execute([':e' => $email]);
        }

        // Link email to the visitor's active cart (abandoned cart tracking)
        if (session_status() === PHP_SESSION_NONE) session_start();
        $sessionToken = $_SESSION['cart_token'] ?? null;
        if ($sessionToken) {
            $db->prepare("UPDATE carts SET guest_email = :e WHERE session_token = :tok AND guest_email IS NULL")
               ->execute([':e' => $email, ':tok' => $sessionToken]);
        }

        // ── Wallet: create table + init settings ─────────────────────
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS wallet_product_uses (
              id INT AUTO_INCREMENT PRIMARY KEY,
              subscriber_email VARCHAR(191) NOT NULL,
              product_id INT NOT NULL,
              order_id INT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY uq_email_product (subscriber_email, product_id),
              INDEX idx_wallet_email (subscriber_email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Throwable $_) {}
        try {
            $db->prepare("INSERT IGNORE INTO settings (`key`,`value`) VALUES
                ('wallet_enabled','1'),('wallet_discount_per_product','50')")
               ->execute();
        } catch (Throwable $_) {}

        // Set session wallet email
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['wallet_email'] = $email;

        // Get wallet settings for response
        $walletEnabled  = '1';
        $walletDiscount = 50;
        try {
            $wRows = $db->query("SELECT `key`,`value` FROM settings WHERE `key` IN ('wallet_enabled','wallet_discount_per_product')")->fetchAll();
            foreach ($wRows as $wR) {
                if ($wR['key'] === 'wallet_enabled') $walletEnabled = $wR['value'];
                if ($wR['key'] === 'wallet_discount_per_product') $walletDiscount = (int)$wR['value'];
            }
        } catch (Throwable $_) {}

        ResponseHelper::success(
            [
                'code'             => $code,
                'wallet_enabled'   => $walletEnabled === '1',
                'wallet_discount'  => $walletDiscount,
                'wallet_email'     => $email,
            ],
            'Subscribed! Your 20% discount code has been sent to your email.'
        );
    }

    // ── Wallet Identify (sets session from localStorage email) ────
    elseif ($base === 'wallet' && $sub === 'identify' && $method === 'POST') {
        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $email = strtolower(trim($body['email'] ?? ''));
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ResponseHelper::error('INVALID', 'Invalid email', 400);
        }
        $db = Database::getInstance();
        $row = $db->prepare("SELECT email FROM newsletter_subscribers WHERE email = :e LIMIT 1");
        $row->execute([':e' => $email]);
        if (!$row->fetch()) {
            ResponseHelper::error('NOT_SUBSCRIBER', 'Email not subscribed', 404);
        }
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['wallet_email'] = $email;

        $walletEnabled = '1'; $walletDiscount = 50;
        try {
            $wRows = $db->query("SELECT `key`,`value` FROM settings WHERE `key` IN ('wallet_enabled','wallet_discount_per_product')")->fetchAll();
            foreach ($wRows as $wR) {
                if ($wR['key'] === 'wallet_enabled') $walletEnabled = $wR['value'];
                if ($wR['key'] === 'wallet_discount_per_product') $walletDiscount = (int)$wR['value'];
            }
        } catch (Throwable $_) {}

        // Count used products for this email
        $usedCount = 0;
        try {
            $uc = $db->prepare("SELECT COUNT(*) FROM wallet_product_uses WHERE subscriber_email = :e");
            $uc->execute([':e' => $email]);
            $usedCount = (int)$uc->fetchColumn();
        } catch (Throwable $_) {}

        ResponseHelper::success([
            'wallet_enabled'  => $walletEnabled === '1',
            'wallet_discount' => $walletDiscount,
            'wallet_email'    => $email,
            'used_count'      => $usedCount,
        ]);
    }

    // ── Wallet Status (returns current wallet session info) ───────
    elseif ($base === 'wallet' && $sub === 'status' && $method === 'GET') {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $email = $_SESSION['wallet_email'] ?? null;
        if (!$email) {
            ResponseHelper::success(['active' => false]);
        }
        $db = Database::getInstance();
        $walletEnabled = '1'; $walletDiscount = 50;
        try {
            $wRows = $db->query("SELECT `key`,`value` FROM settings WHERE `key` IN ('wallet_enabled','wallet_discount_per_product')")->fetchAll();
            foreach ($wRows as $wR) {
                if ($wR['key'] === 'wallet_enabled') $walletEnabled = $wR['value'];
                if ($wR['key'] === 'wallet_discount_per_product') $walletDiscount = (int)$wR['value'];
            }
        } catch (Throwable $_) {}
        $usedCount = 0;
        try {
            $uc = $db->prepare("SELECT COUNT(*) FROM wallet_product_uses WHERE subscriber_email = :e");
            $uc->execute([':e' => $email]);
            $usedCount = (int)$uc->fetchColumn();
        } catch (Throwable $_) {}
        ResponseHelper::success([
            'active'          => $walletEnabled === '1',
            'wallet_enabled'  => $walletEnabled === '1',
            'wallet_discount' => $walletDiscount,
            'wallet_email'    => $email,
            'used_count'      => $usedCount,
        ]);
    }

    else {
        ResponseHelper::notFound("Endpoint not found.");
    }

} catch (Throwable $e) {
    $msg = (APP_ENV === 'development') ? $e->getMessage() : 'An unexpected error occurred.';
    ResponseHelper::serverError($msg);
}
