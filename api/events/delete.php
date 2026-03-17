<?php
header('Content-Type: application/json');

require_once '../../config/init.php';

requireAuth();

$userId = $_SESSION['user_id'];
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ResponseHelper::error('Method not allowed', 405);
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !SecurityHelper::verifyCSRFToken($_POST['csrf_token'])) {
    ResponseHelper::error('Invalid CSRF token', 403);
}

$eventId = isset($_POST['id']) ? (int)$_POST['id'] : null;

if (!$eventId) {
    ResponseHelper::error('Event ID is required', 400);
}

try {
    // Verify ownership
    $event = $db->fetch(
        "SELECT id FROM calendar_events WHERE id = ? AND user_id = ?",
        [$eventId, $userId]
    );

    if (!$event) {
        ResponseHelper::error('Event not found', 404);
    }

    // Delete the event
    $db->delete('calendar_events', 'id = ' . $eventId);
    
    ActivityLogger::log($userId, 'event_deleted', 'calendar_events', $eventId);
    ResponseHelper::success('Event deleted', 200);
} catch (Exception $e) {
    ResponseHelper::error('Failed to delete event: ' . $e->getMessage(), 500);
}
?>
