<?php
/**
 * DUHN FRAGRANCES — Inline save fragrance notes (admin only)
 * POST /admin/actions/save_notes.php
 */
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=UTF-8');

// Admin-only
if (empty($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../api/config/config.php';
require_once __DIR__ . '/../../api/config/database.php';

$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$productId  = (int)($body['product_id'] ?? 0);
$topNotes   = array_values(array_filter(array_map('trim', explode(',', $body['top_notes']   ?? ''))));
$heartNotes = array_values(array_filter(array_map('trim', explode(',', $body['heart_notes'] ?? ''))));
$baseNotes  = array_values(array_filter(array_map('trim', explode(',', $body['base_notes']  ?? ''))));

if (!$productId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

try {
    $db = Database::getInstance();
    $db->prepare("UPDATE products SET top_notes=:t, heart_notes=:h, base_notes=:b, updated_at=NOW() WHERE id=:id")
       ->execute([
           ':t'  => json_encode($topNotes),
           ':h'  => json_encode($heartNotes),
           ':b'  => json_encode($baseNotes),
           ':id' => $productId,
       ]);
    echo json_encode(['success' => true, 'message' => 'Fragrance notes saved.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Save failed.']);
}
