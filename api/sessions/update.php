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
    $session = $db->fetch('SELECT * FROM time_sessions WHERE id = ? AND user_id = ?', [$sessionId, $userId]);
    if (!$session) ResponseHelper::error('Session not found', 404);

    $updates = [];
    if (isset($data['start_time']) && isset($data['end_time'])) {
        $s = strtotime($data['start_time']);
        $e = strtotime($data['end_time']);
        if ($s === false || $e === false || $e < $s) ResponseHelper::error('Invalid start/end', 400);
        $updates['start_time'] = date('Y-m-d H:i:s', $s);
        $updates['end_time'] = date('Y-m-d H:i:s', $e);
        $updates['duration_seconds'] = $e - $s;
    } elseif (isset($data['duration_seconds'])) {
        $dur = (int)$data['duration_seconds'];
        if ($dur < 0) ResponseHelper::error('Invalid duration', 400);
        $updates['duration_seconds'] = $dur;
        $updates['end_time'] = date('Y-m-d H:i:s');
        $updates['start_time'] = date('Y-m-d H:i:s', strtotime("-{$dur} seconds"));
    }

    if (isset($data['project_id'])) {
        $projId = (int)$data['project_id'];
        if ($projId) {
            $proj = $db->fetch('SELECT id FROM projects WHERE id = ? AND user_id = ?', [$projId, $userId]);
            if (!$proj) ResponseHelper::error('Project not found', 404);
            $updates['project_id'] = $projId;
        } else {
            $updates['project_id'] = null;
        }
    }

    if (isset($data['description'])) $updates['description'] = $data['description'];

    if (empty($updates)) ResponseHelper::error('No changes submitted', 400);

    $updates['updated_at'] = date('Y-m-d H:i:s');
    $db->update('time_sessions', $updates, 'id = ' . $sessionId);

    ActivityLogger::log($userId, 'session_updated', 'time_sessions', $sessionId, $updates);

    ResponseHelper::success('Session updated');
} catch (Exception $e) {
    ResponseHelper::error('Failed to update session: ' . $e->getMessage(), 500);
}

?>
