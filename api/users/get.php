<?php
header('Content-Type: application/json');

require_once '../../config/init.php';

requireAuth();

$userId = $_SESSION['user_id'];
$db = Database::getInstance();

try {
    $user = $db->fetch("SELECT id, email, full_name, is_admin, theme, notifications_enabled, created_at FROM users WHERE id = ?", [$userId]);
    if (!$user) {
        ResponseHelper::error('User not found', 404);
    }

    ResponseHelper::success('User retrieved', $user);
} catch (Exception $e) {
    ResponseHelper::error('Failed to fetch user: ' . $e->getMessage(), 500);
}

?>
