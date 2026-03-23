<?php
header('Content-Type: application/json');

require_once '../../config/init.php';

requireAuth();

$userId = $_SESSION['user_id'];
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ResponseHelper::error('Method not allowed', 405);
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);
if (!is_array($data)) $data = $_POST;

$sessionId = isset($data['session_id']) ? (int)$data['session_id'] : null;
$startTime = isset($data['start_time']) ? $data['start_time'] : date('Y-m-d H:i:s');

if (!$sessionId) ResponseHelper::error('Session id required', 400);

// Verify session belongs to user
$session = $db->fetch('SELECT id, user_id FROM time_sessions WHERE id = ?', [$sessionId]);
if (!$session || (int)$session['user_id'] !== (int)$userId) ResponseHelper::error('Session not found', 404);

try {
    $breakId = $db->insert('breaks', [
        'session_id' => $sessionId,
        'start_time' => $startTime,
        'end_time' => null,
        'duration_seconds' => null,
        'break_type' => $data['break_type'] ?? 'break'
    ]);

    ActivityLogger::log($userId, 'BREAK_STARTED', 'breaks', $breakId);

    ResponseHelper::success('Break started', ['break_id' => $breakId]);
} catch (Exception $e) {
    ResponseHelper::error('Failed to start break: ' . $e->getMessage(), 500);
}

?>
