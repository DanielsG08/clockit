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

$breakId = isset($data['id']) ? (int)$data['id'] : null;
$endTime = isset($data['end_time']) ? $data['end_time'] : date('Y-m-d H:i:s');
$duration = isset($data['duration_seconds']) ? (int)$data['duration_seconds'] : null;

if (!$breakId) ResponseHelper::error('Break id required', 400);

$break = $db->fetch('SELECT b.*, ts.user_id FROM breaks b LEFT JOIN time_sessions ts ON b.session_id = ts.id WHERE b.id = ?', [$breakId]);
if (!$break || (int)$break['user_id'] !== (int)$userId) ResponseHelper::error('Break not found', 404);

try {
    $updates = [];
    if ($duration !== null) {
        $updates['duration_seconds'] = $duration;
        $updates['end_time'] = date('Y-m-d H:i:s');
    } else {
        // compute from stored start_time
        $s = strtotime($break['start_time']);
        $e = strtotime($endTime);
        if ($s === false || $e === false || $e < $s) ResponseHelper::error('Invalid end_time', 400);
        $updates['end_time'] = date('Y-m-d H:i:s', $e);
        $updates['duration_seconds'] = $e - $s;
    }

    $updates['updated_at'] = date('Y-m-d H:i:s');
    $db->update('breaks', $updates, 'id = ' . $breakId);

    ActivityLogger::log($userId, 'BREAK_ENDED', 'breaks', $breakId, $updates);

    ResponseHelper::success('Break updated');
} catch (Exception $e) {
    ResponseHelper::error('Failed to update break: ' . $e->getMessage(), 500);
}

?>
