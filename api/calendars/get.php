<?php
header('Content-Type: application/json');

require_once '../../config/init.php';

requireAuth();

$userId = $_SESSION['user_id'];
$db = Database::getInstance();

try {
    $calendars = $db->fetchAll(
        "SELECT id, name, color FROM calendars WHERE user_id = ? ORDER BY name ASC",
        [$userId]
    );

    echo json_encode([
        'success' => true,
        'data' => $calendars ?: []
    ]);
} catch (Exception $e) {
    ResponseHelper::error('Failed to fetch calendars: ' . $e->getMessage(), 500);
}
?>
