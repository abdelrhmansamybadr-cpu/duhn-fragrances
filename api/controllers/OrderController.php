<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/ResponseHelper.php';
require_once __DIR__ . '/../helpers/ValidationHelper.php';
require_once __DIR__ . '/../helpers/JwtHelper.php';
require_once __DIR__ . '/../helpers/Mailer.php';
require_once __DIR__ . '/CartController.php';

/**
 * DUHN FRAGRANCES — Order Controller
 */
class OrderController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureGpsColumns();
    }

    /** Add GPS columns to orders table if they don't exist yet */
    private function ensureGpsColumns(): void
    {
        try {
            $this->db->exec("ALTER TABLE orders
                ADD COLUMN gps_lat    DOUBLE       NULL,
                ADD COLUMN gps_lng    DOUBLE       NULL,
                ADD COLUMN gps_label  VARCHAR(255) NULL,
                ADD COLUMN gps_map_url VARCHAR(500) NULL
            ");
        } catch (Throwable $_) {
            // Columns already exist — ignore
        }
    }

    /** POST /api/orders — Place a new order */
    public function store(): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $v = (new ValidationHelper($body))
            ->required('customer_name',    'Full Name')
            ->required('customer_phone',   'Phone Number')
            ->required('delivery_address', 'Delivery Address')
            ->required('governorate',      'Governorate')
            ->email('customer_email')
            ->egyptPhone('customer_phone')
            ->in('payment_method', ['cod', 'card'], 'Payment Method');

        if (!$v->passes()) {
            ResponseHelper::error('VALIDATION_ERROR', 'Please fix the errors below.', 422, $v->errors());
        }

        // Get cart items
        $cartCtrl = new CartController();
        // Resolve cart via session/JWT
        if (session_status() === PHP_SESSION_NONE) session_start();

        $payload = JwtHelper::fromRequest();
        $userId  = $payload ? (int)$payload['user_id'] : null;
        $sessionToken = $_SESSION['cart_token'] ?? null;

        if ($userId) {
            $cartStmt = $this->db->prepare("SELECT id FROM carts WHERE user_id = :uid LIMIT 1");
            $cartStmt->execute([':uid' => $userId]);
        } elseif ($sessionToken) {
            $cartStmt = $this->db->prepare("SELECT id FROM carts WHERE session_token = :tok LIMIT 1");
            $cartStmt->execute([':tok' => $sessionToken]);
        } else {
            ResponseHelper::error('EMPTY_CART', 'Your cart is empty.', 400);
        }

        $cart = $cartStmt->fetch();
        if (!$cart) ResponseHelper::error('EMPTY_CART', 'Your cart is empty.', 400);

        $itemsStmt = $this->db->prepare("
            SELECT ci.quantity, p.id AS product_id, p.name AS product_name,
                   p.price, p.stock_qty
            FROM cart_items ci
            JOIN products p ON p.id = ci.product_id
            WHERE ci.cart_id = :cid
        ");
        $itemsStmt->execute([':cid' => $cart['id']]);
        $cartItems = $itemsStmt->fetchAll();

        if (empty($cartItems)) {
            ResponseHelper::error('EMPTY_CART', 'Your cart is empty.', 400);
        }

        // Calculate totals
        $subtotal  = 0;
        $itemCount = 0;
        foreach ($cartItems as $item) {
            $subtotal  += (float)$item['price'] * (int)$item['quantity'];
            $itemCount += (int)$item['quantity'];
        }

        // BUY 2 GET 2 FREE
        $promoMin = 4;
        $discount = 0;
        if ($itemCount >= $promoMin) {
            $paidItems   = (int)ceil($itemCount / 2);
            $freeItems   = $itemCount - $paidItems;
            $pricePerItem = $subtotal / $itemCount;
            $discount    = round($freeItems * $pricePerItem, 2);
        }

        $deliveryFee = 0.00;
        $total = max(0, $subtotal - $discount + $deliveryFee);

        // Generate order number
        $orderNumber = 'ILV-' . strtoupper(substr(uniqid(), -6));

        // Insert order
        $this->db->beginTransaction();
        try {
            $orderStmt = $this->db->prepare("
                INSERT INTO orders
                (order_number, user_id, customer_name, customer_email, customer_phone,
                 delivery_address, governorate, subtotal, delivery_fee, discount, total,
                 payment_method, notes, gps_lat, gps_lng, gps_label, gps_map_url)
                VALUES
                (:num, :uid, :name, :email, :phone,
                 :addr, :gov, :sub, :del, :disc, :tot,
                 :pay, :notes, :glat, :glng, :glabel, :gmapurl)
            ");
            $orderStmt->execute([
                ':num'     => $orderNumber,
                ':uid'     => $userId,
                ':name'    => ValidationHelper::sanitize($body['customer_name']),
                ':email'   => filter_var($body['customer_email'] ?? '', FILTER_SANITIZE_EMAIL),
                ':phone'   => ValidationHelper::sanitize($body['customer_phone']),
                ':addr'    => ValidationHelper::sanitize($body['delivery_address']),
                ':gov'     => ValidationHelper::sanitize($body['governorate']),
                ':sub'     => $subtotal,
                ':del'     => $deliveryFee,
                ':disc'    => $discount,
                ':tot'     => $total,
                ':pay'     => $body['payment_method'] ?? 'cod',
                ':notes'   => ValidationHelper::sanitize($body['notes'] ?? ''),
                ':glat'    => isset($body['gps_lat'])    ? (float)$body['gps_lat']   : null,
                ':glng'    => isset($body['gps_lng'])    ? (float)$body['gps_lng']   : null,
                ':glabel'  => isset($body['gps_label'])  ? ValidationHelper::sanitize($body['gps_label'])  : null,
                ':gmapurl' => isset($body['gps_map_url']) ? filter_var($body['gps_map_url'], FILTER_SANITIZE_URL) : null,
            ]);

            $orderId = (int)$this->db->lastInsertId();

            // Insert order items
            $itemInsert = $this->db->prepare("
                INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, line_total)
                VALUES (:oid, :pid, :pname, :pprice, :qty, :lt)
            ");
            foreach ($cartItems as $item) {
                $lineTotal = (float)$item['price'] * (int)$item['quantity'];
                $itemInsert->execute([
                    ':oid'    => $orderId,
                    ':pid'    => $item['product_id'],
                    ':pname'  => $item['product_name'],
                    ':pprice' => $item['price'],
                    ':qty'    => $item['quantity'],
                    ':lt'     => $lineTotal,
                ]);
            }

            // ── Record per-product promo usage ───────────────────────
            $sessionCode = $_SESSION['promo_code'] ?? null;
            if ($sessionCode) {
                try {
                    $pcRow = $this->db->prepare("SELECT id, per_product FROM promo_codes WHERE code=:c LIMIT 1");
                    $pcRow->execute([':c' => $sessionCode]);
                    $pcData = $pcRow->fetch();
                    if ($pcData && !empty($pcData['per_product'])) {
                        foreach ($cartItems as $ci) {
                            $this->db->prepare("
                                INSERT INTO promo_code_product_uses (promo_code_id, product_id, use_count)
                                VALUES (:pcid, :pid, 1)
                                ON DUPLICATE KEY UPDATE use_count = use_count + 1
                            ")->execute([':pcid' => $pcData['id'], ':pid' => $ci['product_id']]);
                        }
                    }
                    // Increment global used_count
                    $this->db->prepare("UPDATE promo_codes SET used_count = used_count + 1 WHERE code = :c")
                             ->execute([':c' => $sessionCode]);
                } catch (Throwable $_ppOrderErr) { /* ignore */ }
            }
            // Clear promo from session
            unset($_SESSION['promo_code'], $_SESSION['promo_discount'], $_SESSION['promo_type'], $_SESSION['promo_value']);

            // Clear cart
            $clearCart = $this->db->prepare("DELETE FROM cart_items WHERE cart_id = :cid");
            $clearCart->execute([':cid' => $cart['id']]);

            // ── Auto-create guest account ────────────────────────────
            $autoAccount = null;
            $guestEmail  = filter_var($body['customer_email'] ?? '', FILTER_SANITIZE_EMAIL);

            if (!$userId && !empty($guestEmail)) {
                // Check if account already exists
                $existStmt = $this->db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
                $existStmt->execute([':email' => $guestEmail]);
                $existingUser = $existStmt->fetch();

                if ($existingUser) {
                    // Link order to existing account
                    $this->db->prepare("UPDATE orders SET user_id = :uid WHERE id = :oid")
                             ->execute([':uid' => $existingUser['id'], ':oid' => $orderId]);
                    $autoAccount = ['type' => 'existing', 'email' => $guestEmail];
                } else {
                    // Create account with a locked hash (user must set password via confirmation page)
                    $lockedHash = '*LOCKED*' . bin2hex(random_bytes(16));

                    $insUser = $this->db->prepare("
                        INSERT INTO users (name, email, phone, password_hash, role)
                        VALUES (:name, :email, :phone, :hash, 'customer')
                    ");
                    $insUser->execute([
                        ':name'  => ValidationHelper::sanitize($body['customer_name']),
                        ':email' => $guestEmail,
                        ':phone' => ValidationHelper::sanitize($body['customer_phone']),
                        ':hash'  => $lockedHash,
                    ]);
                    $newUserId = (int)$this->db->lastInsertId();

                    // Link order to the new account
                    $this->db->prepare("UPDATE orders SET user_id = :uid WHERE id = :oid")
                             ->execute([':uid' => $newUserId, ':oid' => $orderId]);

                    // Generate JWT so user is auto-logged in on confirmation page
                    $token = JwtHelper::generate(['user_id' => $newUserId, 'role' => 'customer']);

                    $autoAccount = [
                        'type'  => 'created',
                        'email' => $guestEmail,
                        'name'  => ValidationHelper::sanitize($body['customer_name']),
                        'token' => $token,
                    ];
                }
            }
            // ────────────────────────────────────────────────────────

            $this->db->commit();

            // ── Record wallet product uses after order ───────────────
            if (session_status() === PHP_SESSION_NONE) session_start();
            $walletEmail = $_SESSION['wallet_email'] ?? null;
            if ($walletEmail) {
                try {
                    $wEnabled = $this->db->prepare("SELECT value FROM settings WHERE `key`='wallet_enabled' LIMIT 1");
                    $wEnabled->execute();
                    $wEnabledVal = $wEnabled->fetchColumn();
                    if ($wEnabledVal === '1') {
                        $wIns = $this->db->prepare("
                            INSERT IGNORE INTO wallet_product_uses (subscriber_email, product_id, order_id)
                            VALUES (:e, :pid, :oid)
                        ");
                        foreach ($cartItems as $ci) {
                            $wIns->execute([':e' => $walletEmail, ':pid' => $ci['product_id'], ':oid' => $orderId]);
                        }
                    }
                } catch (Throwable $_wErr) {}
            }
            // ────────────────────────────────────────────────────────

            $paymentMethod = $body['payment_method'] ?? 'cod';
            $orderRow = [
                'order_number'    => $orderNumber,
                'customer_name'   => ValidationHelper::sanitize($body['customer_name']),
                'customer_email'  => filter_var($body['customer_email'] ?? '', FILTER_SANITIZE_EMAIL),
                'customer_phone'  => ValidationHelper::sanitize($body['customer_phone']),
                'delivery_address'=> ValidationHelper::sanitize($body['delivery_address']),
                'governorate'     => ValidationHelper::sanitize($body['governorate']),
                'subtotal'        => $subtotal,
                'discount'        => $discount,
                'delivery_fee'    => $deliveryFee,
                'total'           => $total,
                'payment_method'  => $paymentMethod,
            ];

            $resp = [
                'order_number' => $orderNumber,
                'order_id'     => $orderId,
                'total'        => number_format($total, 2, '.', ''),
                'status'       => 'pending',
            ];
            if ($autoAccount) $resp['auto_account'] = $autoAccount;

            // ── Kashier online payment: generate HPP redirect URL ────
            if ($paymentMethod === 'card') {
                try {
                    $kRows = $this->db->query(
                        "SELECT `key`,`value` FROM settings WHERE `key` IN ('kashier_mid','kashier_api_key','kashier_mode')"
                    )->fetchAll();
                    $kS = [];
                    foreach ($kRows as $kr) $kS[$kr['key']] = $kr['value'];

                    $kMid    = $kS['kashier_mid']    ?? '';
                    $kApiKey = $kS['kashier_api_key'] ?? '';
                    $kMode   = $kS['kashier_mode']   ?? 'live';

                    if ($kMid && $kApiKey) {
                        // Mark order as pending_payment (awaiting Kashier confirmation)
                        $this->db->prepare("UPDATE orders SET status='pending_payment' WHERE id=:id")
                                 ->execute([':id' => $orderId]);

                        $kAmount   = number_format($total, 2, '.', '');
                        $kCurrency = 'EGP';
                        // Use DB order ID as Kashier merchantOrderId
                        $kPath   = "/?payment={$kMid}.{$orderId}.{$kAmount}.{$kCurrency}";
                        $kHash   = hash_hmac('sha256', $kPath, $kApiKey, false);
                        $kBase   = defined('APP_URL') ? APP_URL : 'https://duhnfragrances.com';
                        $kParams = http_build_query([
                            'merchantId'       => $kMid,
                            'orderId'          => (string)$orderId,
                            'amount'           => $kAmount,
                            'currency'         => $kCurrency,
                            'hash'             => $kHash,
                            'merchantRedirect' => $kBase . '/kashier-callback.php',
                            'allowedMethods'   => 'card,wallet,bank_installments',
                            'display'          => 'en',
                            'mode'             => $kMode,
                            'description'      => 'Order ' . $orderNumber . ' — DUHN FRAGRANCES',
                        ]);
                        $resp['kashier_redirect_url'] = 'https://checkout.kashier.io/?' . $kParams;
                        $resp['status'] = 'pending_payment';
                    }
                } catch (Throwable $_kErr) { /* if Kashier fails, still return order */ }

                // For card orders, emails are sent after payment confirmed in kashier-callback.php
                ResponseHelper::success($resp, 'Order created! Redirecting to payment...', 201);
            }
            // ────────────────────────────────────────────────────────

            // ── Email notification to admin (COD only) ───────────────
            try {
                Mailer::sendOrderNotification($orderRow, $cartItems);
            } catch (Throwable $_mailErr) { /* never block the order response */ }

            // ── Email confirmation to customer (COD only) ────────────
            try {
                if (!empty($orderRow['customer_email'])) {
                    Mailer::sendOrderConfirmation($orderRow, $cartItems);
                }
            } catch (Throwable $_custMailErr) { /* never block the order response */ }
            // ────────────────────────────────────────────────────────

            ResponseHelper::success($resp, 'Order placed successfully!', 201);

        } catch (Throwable $e) {
            $this->db->rollBack();
            ResponseHelper::serverError('Failed to place order. Please try again.');
        }
    }

    /** GET /api/orders — My orders (auth required) */
    public function index(): void
    {
        $payload = JwtHelper::fromRequest();
        if (!$payload) ResponseHelper::unauthorized();

        $stmt = $this->db->prepare("
            SELECT id, order_number, total, status, payment_method,
                   governorate, created_at
            FROM orders
            WHERE user_id = :uid
            ORDER BY created_at DESC
            LIMIT 50
        ");
        $stmt->execute([':uid' => $payload['user_id']]);
        ResponseHelper::success($stmt->fetchAll());
    }

    /** GET /api/orders/{id} — Order detail (auth required) */
    public function show(int $id): void
    {
        $payload = JwtHelper::fromRequest();
        if (!$payload) ResponseHelper::unauthorized();

        $stmt = $this->db->prepare("SELECT * FROM orders WHERE id = :id AND user_id = :uid LIMIT 1");
        $stmt->execute([':id' => $id, ':uid' => $payload['user_id']]);
        $order = $stmt->fetch();
        if (!$order) ResponseHelper::notFound('Order not found');

        $items = $this->db->prepare(
            "SELECT * FROM order_items WHERE order_id = :oid"
        );
        $items->execute([':oid' => $id]);
        $order['items'] = $items->fetchAll();

        ResponseHelper::success($order);
    }
}
