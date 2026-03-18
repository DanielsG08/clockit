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
if (!$calendarId) {
    ResponseHelper::error('Calendar id is required', 400);
}

try {
    $calendar = $db->fetch("SELECT id FROM calendars WHERE id = ? AND user_id = ?", [$calendarId, $userId]);
    if (!$calendar) {
        ResponseHelper::error('Calendar not found', 404);
    }

    // Clear calendar assignment for events linked to this calendar
    $db->update('calendar_events', [
        'calendar_id' => null
    ], 'calendar_id = ' . $calendarId);

    $db->delete('calendars', 'id = ' . $calendarId);

    ActivityLogger::log($userId, 'calendar_deleted', 'calendars', $calendarId);
    ResponseHelper::success('Calendar deleted', [], 200);
} catch (Exception $e) {
    ResponseHelper::error('Failed to delete calendar: ' . $e->getMessage(), 500);
}
?>