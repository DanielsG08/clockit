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
$eventDate = isset($_POST['date']) ? $_POST['date'] : null;
$title = isset($_POST['title']) ? trim($_POST['title']) : null;
$description = isset($_POST['description']) ? trim($_POST['description']) : null;
$eventTime = isset($_POST['time']) ? $_POST['time'] : null;
$color = isset($_POST['color']) ? $_POST['color'] : '#667eea';

// Validate inputs
if (!$eventDate || !$title) {
    ResponseHelper::error('Date and title are required', 400);
}

if (strlen($title) > 100) {
    ResponseHelper::error('Title must be less than 100 characters', 400);
}

if (strlen($description) > 500) {
    ResponseHelper::error('Description must be less than 500 characters', 400);
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
    ResponseHelper::error('Invalid date format', 400);
}

try {
    if ($eventId) {
        // Update existing event
        $event = $db->fetch(
            "SELECT id FROM calendar_events WHERE id = ? AND user_id = ?",
            [$eventId, $userId]
        );

        if (!$event) {
            ResponseHelper::error('Event not found', 404);
        }

        $db->update('calendar_events', [
            'title' => $title,
            'description' => $description,
            'event_date' => $eventDate,
            'event_time' => $eventTime,
            'color' => $color,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ' . $eventId);

        ActivityLogger::log($userId, 'event_updated', 'calendar_events', $eventId);
        ResponseHelper::success('Event updated', ['id' => $eventId], 200);
    } else {
        // Create new event
        $lastId = $db->insert('calendar_events', [
            'user_id' => $userId,
            'event_date' => $eventDate,
            'title' => $title,
            'description' => $description,
            'event_time' => $eventTime,
            'color' => $color
        ]);

        ActivityLogger::log($userId, 'event_created', 'calendar_events', $lastId);
        ResponseHelper::success('Event created', ['id' => $lastId], 201);
    }
} catch (Exception $e) {
    ResponseHelper::error('Failed to save event: ' . $e->getMessage(), 500);
}
?>
