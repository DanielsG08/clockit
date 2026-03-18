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

$fullName = isset($_POST['full_name']) ? SecurityHelper::sanitize($_POST['full_name']) : null;
$email = isset($_POST['email']) ? SecurityHelper::sanitize($_POST['email']) : null;
$theme = isset($_POST['theme']) ? SecurityHelper::sanitize($_POST['theme']) : null;
$notifications = isset($_POST['notifications_enabled']) ? (int)$_POST['notifications_enabled'] : null;

$updates = [];

if ($fullName !== null) {
    if (strlen($fullName) > 150) {
        ResponseHelper::error('Full name too long', 400);
    }
    $updates['full_name'] = $fullName;
}

if ($theme !== null) {
    $allowed = ['light', 'dark'];
    if (!in_array($theme, $allowed)) {
        ResponseHelper::error('Invalid theme', 400);
    }
    $updates['theme'] = $theme;
}

if ($notifications !== null) {
    $updates['notifications_enabled'] = $notifications ? 1 : 0;
}

if ($email !== null) {
    if (!SecurityHelper::validateEmail($email)) {
        ResponseHelper::error('Invalid email', 400);
    }

    // Check uniqueness
    $existing = $db->fetch('SELECT id FROM users WHERE email = ? AND id != ?', [$email, $userId]);
    if ($existing) {
        ResponseHelper::error('Email already in use', 400);
    }

    $updates['email'] = $email;
}

if (empty($updates)) {
    ResponseHelper::error('No changes submitted', 400);
}

try {
    $updates['updated_at'] = date('Y-m-d H:i:s');
    $db->update('users', $updates, 'id = ' . $userId);

    ActivityLogger::log($userId, 'profile_updated', 'user', $userId, $updates);

    // Refresh session email/name if changed
    if (isset($updates['email'])) $_SESSION['user_email'] = $updates['email'];
    if (isset($updates['full_name'])) $_SESSION['user_name'] = $updates['full_name'];

    ResponseHelper::success('Profile updated', $updates);
} catch (Exception $e) {
    ResponseHelper::error('Failed to update profile: ' . $e->getMessage(), 500);
}

?>
