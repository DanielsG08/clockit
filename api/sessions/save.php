<?php
header('Content-Type: application/json');

require_once '../../config/init.php';

requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$userId = $_SESSION['user_id'];
$db = Database::getInstance();

if ($method === 'POST') {
    // Accept JSON body or form-encoded
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    if (!is_array($data)) {
        // fall back to $_POST for form submissions
        $data = $_POST;
    }

    // Allow either explicit start/end times or duration_seconds
    $projectId = !empty($data['project_id']) ? (int)$data['project_id'] : null;
    $description = $data['description'] ?? null;
    $breakSeconds = (int)($data['break_seconds'] ?? 0);

    $startTime = $data['start_time'] ?? null; // expected 'YYYY-MM-DD HH:MM:SS'
    $endTime = $data['end_time'] ?? null;
    $durationSeconds = isset($data['duration_seconds']) ? (int)$data['duration_seconds'] : null;

    // If start and end provided, compute duration
    if ($startTime && $endTime) {
        $s = strtotime($startTime);
        $e = strtotime($endTime);
        if ($s === false || $e === false || $e < $s) {
            ResponseHelper::error('Invalid start_time or end_time', 400);
        }
        $durationSeconds = $e - $s;
        $startTime = date('Y-m-d H:i:s', $s);
        $endTime = date('Y-m-d H:i:s', $e);
    }

    // If duration given but no explicit times, compute end_time as now
    if ($durationSeconds !== null && (!$startTime || !$endTime)) {
        $endTime = date('Y-m-d H:i:s');
        $startTime = date('Y-m-d H:i:s', strtotime("-{$durationSeconds} seconds"));
    }

    // Allow creating an open session when only start_time is provided (no end_time/duration)
    if ($durationSeconds === null && !$startTime) {
        ResponseHelper::error('Missing required fields: provide duration_seconds or start_time and end_time', 400);
    }

    // Validate project ownership
    if ($projectId) {
        $project = $db->fetch("SELECT id FROM projects WHERE id = ? AND user_id = ?", [$projectId, $userId]);
        if (!$project) {
            ResponseHelper::error('Project not found', 404);
        }
    }

    try {
        $sessionData = [
            'user_id' => $userId,
            'project_id' => $projectId,
            'start_time' => $startTime,
            'description' => $description
        ];

        // If we have an end_time or duration, include them
        if ($endTime) $sessionData['end_time'] = $endTime;
        if ($durationSeconds !== null) $sessionData['duration_seconds'] = $durationSeconds;

        $sessionId = $db->insert('time_sessions', $sessionData);

        // Save break if exists
        if ($breakSeconds > 0) {
            $db->insert('breaks', [
                'session_id' => $sessionId,
                'start_time' => date('Y-m-d H:i:s', strtotime("-{$breakSeconds} seconds")),
                'end_time' => date('Y-m-d H:i:s'),
                'duration_seconds' => $breakSeconds,
                'break_type' => 'break'
            ]);
        }

        ActivityLogger::log($userId, 'CREATE_SESSION', 'session', $sessionId);

        ResponseHelper::success('Session saved', ['session_id' => $sessionId]);
    } catch (Exception $e) {
        ResponseHelper::error('Failed to save session: ' . $e->getMessage(), 500);
    }
} else {
    ResponseHelper::error('Invalid method', 405);
}
?>
