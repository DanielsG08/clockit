<?php
header('Content-Type: application/json');

require_once '../../config/init.php';

requireAuth();

$userId = $_SESSION['user_id'];
$db = Database::getInstance();

$sessions = $db->fetchAll(
    "SELECT ts.id, ts.project_id, ts.description, ts.duration_seconds, p.name as project_name
     FROM time_sessions ts
     LEFT JOIN projects p ON ts.project_id = p.id
     WHERE ts.user_id = ? AND DATE(ts.start_time) = DATE('now')
     ORDER BY ts.start_time DESC",
    [$userId]
);

ResponseHelper::success('Sessions retrieved', $sessions);
?>
