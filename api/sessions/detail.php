<?php
header('Content-Type: application/json');

require_once '../../config/init.php';

requireAuth();

$userId = $_SESSION['user_id'];
$db = Database::getInstance();

$sessionId = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$sessionId) ResponseHelper::error('Session id required', 400);

try {
    $s = $db->fetch(
        "SELECT ts.*, p.name as project_name, p.color as project_color
         FROM time_sessions ts
         LEFT JOIN projects p ON ts.project_id = p.id
         WHERE ts.id = ? AND ts.user_id = ?",
        [$sessionId, $userId]
    );

    if (!$s) ResponseHelper::error('Session not found', 404);

    ResponseHelper::success('Session retrieved', $s);
} catch (Exception $e) {
    ResponseHelper::error('Failed to fetch session: ' . $e->getMessage(), 500);
}

?>
