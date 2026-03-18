<?php
header('Content-Type: application/json');

require_once '../../config/init.php';

requireAuth();

$userId = $_SESSION['user_id'];
$db = Database::getInstance();

$input = json_decode(file_get_contents('php://input'), true);
$theme = isset($input['theme']) ? trim($input['theme']) : null;

if (!$theme || !in_array($theme, ['light', 'dark'])) {
    ResponseHelper::error('Invalid theme', 400);
}

try {
    $db->update('users', [
        'theme' => $theme,
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = ' . $userId);

    ResponseHelper::success('Theme updated');
} catch (Exception $e) {
    ResponseHelper::error('Failed to update theme: ' . $e->getMessage(), 500);
}
?>