<?php
header('Content-Type: application/json');

require_once '../../config/init.php';

requireAuth();

$userId = $_SESSION['user_id'];
$db = Database::getInstance();

$from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-01');
$to = isset($_GET['to']) ? $_GET['to'] : date('Y-m-t');

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    ResponseHelper::error('Invalid date format', 400);
}

try {
    $events = $db->fetchAll(
        "SELECT id, title, description, event_date, event_time, color
         FROM calendar_events
         WHERE user_id = ? AND event_date BETWEEN ? AND ?
         ORDER BY event_date ASC, event_time ASC",
        [$userId, $from, $to]
    );

    echo json_encode([
        'success' => true,
        'data' => $events ?: []
    ]);
} catch (Exception $e) {
    ResponseHelper::error('Failed to fetch events: ' . $e->getMessage(), 500);
}
?>
