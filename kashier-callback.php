<?php
/**
 * DUHN FRAGRANCES — Kashier Payment Callback
 *
 * Kashier redirects the customer here after HPP payment with GET params.
 * We verify the HMAC signature, update the order status, send emails,
 * then redirect to the confirmation page.
 */
require_once __DIR__ . '/api/config/config.php';
require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/api/helpers/Mailer.php';
require_once __DIR__ . '/api/helpers/ValidationHelper.php';

// ── 1. Read & validate incoming params ─────────────────────────────────────
$params    = $_GET;
$signature = $params['signature'] ?? '';
$orderId   = (int)($params['orderId']     ?? 0);
$orderStatus = strtoupper($params['orderStatus'] ?? '');

function redirectFail(string $reason = ''): never
{
    $q = $reason ? '?payment=failed&reason=' . urlencode($reason) : '?payment=failed';
    header('Location: /order-confirmation.php' . $q);
    exit;
}

if (!$signature || !$orderId) {
    redirectFail('missing_params');
}

// ── 2. Load Kashier API key from settings ───────────────────────────────────
try {
    $db = Database::getInstance();
    $kRows = $db->query("SELECT `key`,`value` FROM settings WHERE `key` IN ('kashier_api_key','kashier_mode')")->fetchAll();
    $kS = [];
    foreach ($kRows as $kr) $kS[$kr['key']] = $kr['value'];
    $apiKey = $kS['kashier_api_key'] ?? '';
} catch (Throwable $_) {
    redirectFail('db_error');
}

if (empty($apiKey)) {
    redirectFail('no_api_key');
}

// ── 3. Verify HMAC-SHA256 signature ────────────────────────────────────────
// Kashier: remove 'signature' and 'mode' from params, sort keys, build query string
$verifyParams = $params;
unset($verifyParams['signature'], $verifyParams['mode']);
ksort($verifyParams);
$queryString   = http_build_query($verifyParams);
$expectedSig   = hash_hmac('sha256', $queryString, $apiKey);

if (!hash_equals($expectedSig, $signature)) {
    redirectFail('invalid_signature');
}

// ── 4. Load order from DB ───────────────────────────────────────────────────
try {
    $orderStmt = $db->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
    $orderStmt->execute([':id' => $orderId]);
    $order = $orderStmt->fetch();
} catch (Throwable $_) {
    redirectFail('db_error');
}

if (!$order) {
    redirectFail('order_not_found');
}

// Prevent re-processing an already confirmed order
if ($order['status'] === 'confirmed') {
    header("Location: /order-confirmation.php?order=" . urlencode($order['order_number'])
           . "&total=" . urlencode($order['total']) . "&payment=success");
    exit;
}

// ── 5. Handle SUCCESS vs FAILED ─────────────────────────────────────────────
if ($orderStatus === 'SUCCESS') {

    // Update order status
    try {
        $db->prepare("UPDATE orders SET status = 'confirmed' WHERE id = :id")
           ->execute([':id' => $orderId]);
    } catch (Throwable $_) { /* non-blocking */ }

    // Load order items for emails
    try {
        $itemsStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = :oid");
        $itemsStmt->execute([':oid' => $orderId]);
        $cartItems = $itemsStmt->fetchAll();
    } catch (Throwable $_) {
        $cartItems = [];
    }

    // Build order row array for Mailer (same format as OrderController)
    $orderRow = [
        'order_number'     => $order['order_number'],
        'customer_name'    => $order['customer_name'],
        'customer_email'   => $order['customer_email'],
        'customer_phone'   => $order['customer_phone'],
        'delivery_address' => $order['delivery_address'],
        'governorate'      => $order['governorate'],
        'subtotal'         => $order['subtotal'],
        'discount'         => $order['discount'],
        'delivery_fee'     => $order['delivery_fee'],
        'total'            => $order['total'],
        'payment_method'   => 'card',
    ];

    // Send admin notification
    try {
        Mailer::sendOrderNotification($orderRow, $cartItems);
    } catch (Throwable $_) {}

    // Send customer confirmation
    try {
        if (!empty($orderRow['customer_email'])) {
            Mailer::sendOrderConfirmation($orderRow, $cartItems);
        }
    } catch (Throwable $_) {}

    header("Location: /order-confirmation.php?order=" . urlencode($order['order_number'])
           . "&total=" . urlencode($order['total']) . "&payment=success");
    exit;

} else {
    // Payment failed or cancelled
    try {
        $db->prepare("UPDATE orders SET status = 'payment_failed' WHERE id = :id")
           ->execute([':id' => $orderId]);
    } catch (Throwable $_) {}

    header("Location: /order-confirmation.php?order=" . urlencode($order['order_number'])
           . "&payment=failed");
    exit;
}
