<?php
header('Content-Type: application/json');

require_once '../../config/init.php';

requireAuth();

$userId = $_SESSION['user_id'];
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ResponseHelper::error('Method not allowed', 405);
}

// Support form or JSON
$body = file_get_contents('php://input');
$data = json_decode($body, true);
if (!is_array($data)) $data = $_POST;

// CSRF
if (!isset($data['csrf_token']) || !SecurityHelper::verifyCSRFToken($data['csrf_token'])) {
    ResponseHelper::error('Invalid CSRF token', 403);
}

$sessionId = isset($data['id']) ? (int)$data['id'] : null;
if (!$sessionId) ResponseHelper::error('Session id required', 400);

try {
    $session = $db->fetch('SELECT id FROM time_sessions WHERE id = ? AND user_id = ?', [$sessionId, $userId]);
    if (!$session) ResponseHelper::error('Session not found', 404);

    $db->delete('time_sessions', 'id = ' . $sessionId);
    ActivityLogger::log($userId, 'session_deleted', 'time_sessions', $sessionId);

    ResponseHelper::success('Session deleted');
} catch (Exception $e) {
    ResponseHelper::error('Failed to delete session: ' . $e->getMessage(), 500);
}

?>
