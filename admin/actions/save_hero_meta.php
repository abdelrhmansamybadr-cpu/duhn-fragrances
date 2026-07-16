<?php
/**
 * DUHN FRAGRANCES — Save hero image meta (image URL, size, position)
 * Accepts JSON: { id, image_url?, remove?, image_size?, image_pos? }
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

$id = (int)($body['id'] ?? 0);
if ($id < 1 || $id > 3) { echo json_encode(['ok' => false, 'error' => 'Invalid ID']); exit; }

try {
    $db = Database::getInstance();
    $up = $db->prepare(
        "INSERT INTO `settings`(`key`,`value`) VALUES(:k,:v)
         ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)"
    );

    if (isset($body['remove']) && $body['remove'] === true) {
        $up->execute([':k' => "inspo_{$id}_image", ':v' => '']);
    } elseif (isset($body['image_url'])) {
        $url = trim($body['image_url']);
        $up->execute([':k' => "inspo_{$id}_image", ':v' => $url]);
    }

    if (isset($body['image_size'])) {
        $sz = in_array($body['image_size'], ['small','medium','full']) ? $body['image_size'] : 'medium';
        $up->execute([':k' => "inspo_{$id}_image_size", ':v' => $sz]);
    }

    if (isset($body['image_pos'])) {
        $pos = in_array($body['image_pos'], ['top','center','bottom']) ? $body['image_pos'] : 'center';
        $up->execute([':k' => "inspo_{$id}_image_pos", ':v' => $pos]);
    }

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
