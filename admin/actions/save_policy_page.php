<?php
/**
 * DUHN FRAGRANCES — Save policy/about page content (AJAX)
 * Accepts JSON: { tab, title, subtitle, content }
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (empty($_SESSION['admin_id']) || ($_SESSION['admin_role'] ?? '') !== 'admin') {
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']); exit;
}

require_once __DIR__ . '/../../api/config/config.php';
require_once __DIR__ . '/../../api/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']); exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) { echo json_encode(['ok' => false, 'error' => 'Invalid JSON']); exit; }

$allowed = ['about', 'shipping', 'exchange', 'refill'];
$tab     = $body['tab'] ?? '';
if (!in_array($tab, $allowed)) { echo json_encode(['ok' => false, 'error' => 'Invalid tab']); exit; }

try {
    $db = Database::getInstance();
    $up = $db->prepare(
        "INSERT INTO `settings`(`key`,`value`) VALUES(:k,:v)
         ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)"
    );
    $up->execute([':k' => "page_{$tab}_title",    ':v' => trim($body['title']    ?? '')]);
    $up->execute([':k' => "page_{$tab}_subtitle", ':v' => trim($body['subtitle'] ?? '')]);
    $up->execute([':k' => "page_{$tab}_content",  ':v' => $body['content'] ?? '']);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
