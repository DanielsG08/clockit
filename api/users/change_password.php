<?php
header('Content-Type: application/json');

require_once '../../config/init.php';

requireAuth();

$userId = $_SESSION['user_id'];
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ResponseHelper::error('Method not allowed', 405);
}

// CSRF validation
if (!isset($_POST['csrf_token']) || !SecurityHelper::verifyCSRFToken($_POST['csrf_token'])) {
    ResponseHelper::error('Invalid CSRF token', 403);
}

$current = $_POST['current_password'] ?? '';
$new = $_POST['new_password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

if (empty($current) || empty($new) || empty($confirm)) {
    ResponseHelper::error('All password fields are required', 400);
}

if ($new !== $confirm) {
    ResponseHelper::error('New passwords do not match', 400);
}

// Validate strength
$strength = SecurityHelper::validatePasswordStrength($new);
if (!$strength['length'] || !$strength['uppercase'] || !$strength['lowercase'] || !$strength['number']) {
    ResponseHelper::error('Password must be at least 8 characters and include upper, lower, and number', 400);
}

try {
    $user = $db->fetch('SELECT password_hash FROM users WHERE id = ?', [$userId]);
    if (!$user || !SecurityHelper::verifyPassword($current, $user['password_hash'])) {
        ResponseHelper::error('Current password is incorrect', 403);
    }

    $hash = SecurityHelper::hashPassword($new);
    $db->update('users', ['password_hash' => $hash, 'updated_at' => date('Y-m-d H:i:s')], 'id = ' . $userId);

    ActivityLogger::log($userId, 'password_changed', 'user', $userId);

    ResponseHelper::success('Password changed successfully');
} catch (Exception $e) {
    ResponseHelper::error('Failed to change password: ' . $e->getMessage(), 500);
}

?>
