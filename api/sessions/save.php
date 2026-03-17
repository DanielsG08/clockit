<?php
header('Content-Type: application/json');

require_once '../../config/init.php';

requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$userId = $_SESSION['user_id'];
$db = Database::getInstance();

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['project_id']) || !isset($data['duration_seconds'])) {
        ResponseHelper::error('Missing required fields', 400);
    }

    $projectId = !empty($data['project_id']) ? (int)$data['project_id'] : null;
    $description = $data['description'] ?? null;
    $durationSeconds = (int)$data['duration_seconds'];
    $breakSeconds = (int)($data['break_seconds'] ?? 0);

    // Validate project ownership
    if ($projectId) {
        $project = $db->fetch("SELECT id FROM projects WHERE id = ? AND user_id = ?", [$projectId, $userId]);
        if (!$project) {
            ResponseHelper::error('Project not found', 404);
        }
    }

    try {
        $sessionId = $db->insert('time_sessions', [
            'user_id' => $userId,
            'project_id' => $projectId,
            'start_time' => date('Y-m-d H:i:s', strtotime("-$durationSeconds seconds")),
            'end_time' => date('Y-m-d H:i:s'),
            'duration_seconds' => $durationSeconds,
            'description' => $description
        ]);

        // Save break if exists
        if ($breakSeconds > 0) {
            $db->insert('breaks', [
                'session_id' => $sessionId,
                'start_time' => date('Y-m-d H:i:s', strtotime("-$breakSeconds seconds")),
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
