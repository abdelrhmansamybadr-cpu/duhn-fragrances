<?php
require_once __DIR__ . '/../../admin/includes/auth_check.php';
require_once __DIR__ . '/../../api/config/config.php';
require_once __DIR__ . '/../../api/config/database.php';

$db     = Database::getInstance();
$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

if ($id) {
    switch ($action) {
        case 'approve':
            $db->prepare("UPDATE reviews SET is_approved = 1 WHERE id = :id")->execute([':id' => $id]);
            // Recalculate product rating
            $pid = $db->prepare("SELECT product_id FROM reviews WHERE id = :id");
            $pid->execute([':id' => $id]);
            $pid = $pid->fetchColumn();
            if ($pid) {
                $db->prepare("
                    UPDATE products
                    SET avg_rating = (SELECT AVG(rating) FROM reviews WHERE product_id = :pid AND is_approved = 1),
                        review_count = (SELECT COUNT(*) FROM reviews WHERE product_id = :pid2 AND is_approved = 1)
                    WHERE id = :pid3
                ")->execute([':pid' => $pid, ':pid2' => $pid, ':pid3' => $pid]);
            }
            break;

        case 'unapprove':
            $db->prepare("UPDATE reviews SET is_approved = 0 WHERE id = :id")->execute([':id' => $id]);
            $pid = $db->prepare("SELECT product_id FROM reviews WHERE id = :id");
            $pid->execute([':id' => $id]);
            $pid = $pid->fetchColumn();
            if ($pid) {
                $db->prepare("
                    UPDATE products
                    SET avg_rating = COALESCE((SELECT AVG(rating) FROM reviews WHERE product_id = :pid AND is_approved = 1), 0),
                        review_count = (SELECT COUNT(*) FROM reviews WHERE product_id = :pid2 AND is_approved = 1)
                    WHERE id = :pid3
                ")->execute([':pid' => $pid, ':pid2' => $pid, ':pid3' => $pid]);
            }
            break;

        case 'delete':
            $pid = $db->prepare("SELECT product_id FROM reviews WHERE id = :id");
            $pid->execute([':id' => $id]);
            $pid = $pid->fetchColumn();
            $db->prepare("DELETE FROM reviews WHERE id = :id")->execute([':id' => $id]);
            if ($pid) {
                $db->prepare("
                    UPDATE products
                    SET avg_rating = COALESCE((SELECT AVG(rating) FROM reviews WHERE product_id = :pid AND is_approved = 1), 0),
                        review_count = (SELECT COUNT(*) FROM reviews WHERE product_id = :pid2 AND is_approved = 1)
                    WHERE id = :pid3
                ")->execute([':pid' => $pid, ':pid2' => $pid, ':pid3' => $pid]);
            }
            break;
    }
}

$back = $_GET['back'] ?? '/admin/reviews.php';
if (!str_starts_with($back, '/admin/')) $back = '/admin/reviews.php';
header('Location: ' . $back);
exit;
