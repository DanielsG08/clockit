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

$calendarId = isset($_POST['id']) ? (int)$_POST['id'] : null;
$name = isset($_POST['name']) ? trim($_POST['name']) : null;
$color = isset($_POST['color']) ? trim($_POST['color']) : '#667eea';

if (!$name) {
    ResponseHelper::error('Calendar name is required', 400);
}

if (strlen($name) > 100) {
    ResponseHelper::error('Calendar name must be less than 100 characters', 400);
}

try {
    if ($calendarId) {
        $calendar = $db->fetch("SELECT id FROM calendars WHERE id = ? AND user_id = ?", [$calendarId, $userId]);
        if (!$calendar) {
            ResponseHelper::error('Calendar not found', 404);
        }

        $db->update('calendars', [
            'name' => $name,
            'color' => $color,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ' . $calendarId);

        ActivityLogger::log($userId, 'calendar_updated', 'calendars', $calendarId);
        ResponseHelper::success('Calendar updated', ['id' => $calendarId], 200);
    } else {
        $lastId = $db->insert('calendars', [
            'user_id' => $userId,
            'name' => $name,
            'color' => $color
        ]);

        ActivityLogger::log($userId, 'calendar_created', 'calendars', $lastId);
        ResponseHelper::success('Calendar created', ['id' => $lastId], 201);
    }
} catch (Exception $e) {
    ResponseHelper::error('Failed to save calendar: ' . $e->getMessage(), 500);
}
?>