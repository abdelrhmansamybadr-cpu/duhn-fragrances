<?php
/**
 * DUHN FRAGRANCES — Admin order count poll (for browser notifications)
 * GET /admin/actions/orders_poll.php
 * Returns: { count: N, latest: { order_number, customer_name, total, created_at } }
 */
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if (empty($_SESSION['admin_id']) || ($_SESSION['admin_role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../api/config/config.php';
require_once __DIR__ . '/../../api/config/database.php';

try {
    $db  = Database::getInstance();
    $row = $db->query(
        "SELECT COUNT(*) AS total_count,
                MAX(id) AS latest_id
         FROM orders
         WHERE status = 'pending'"
    )->fetch();

    $latest = null;
    if ($row['latest_id']) {
        $latest = $db->prepare(
            "SELECT order_number, customer_name, total, created_at
             FROM orders WHERE id = :id LIMIT 1"
        );
        $latest->execute([':id' => $row['latest_id']]);
        $latest = $latest->fetch();
    }

    echo json_encode([
        'count'  => (int)$row['total_count'],
        'latest' => $latest ?: null,
    ]);
} catch (Throwable $e) {
    echo json_encode(['count' => 0, 'latest' => null]);
}
