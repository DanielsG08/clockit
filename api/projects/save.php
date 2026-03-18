<?php
header('Content-Type: application/json');

require_once '../../config/init.php';

requireAuth();

$userId = $_SESSION['user_id'];
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ResponseHelper::error('Method not allowed', 405);
}

// CSRF validation (form POST)
if (!isset($_POST['csrf_token']) || !SecurityHelper::verifyCSRFToken($_POST['csrf_token'])) {
    ResponseHelper::error('Invalid CSRF token', 403);
}

$projectId = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : null;
$color = isset($_POST['color']) ? trim($_POST['color']) : '#667eea';
$isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

if (empty($name)) {
    ResponseHelper::error('Project name is required', 400);
}

if (strlen($name) > 100) {
    ResponseHelper::error('Project name must be less than 100 characters', 400);
}

try {
    if ($projectId) {
        // Verify ownership
        $proj = $db->fetch("SELECT id FROM projects WHERE id = ? AND user_id = ?", [$projectId, $userId]);
        if (!$proj) {
            ResponseHelper::error('Project not found', 404);
        }

        $db->update('projects', [
            'name' => $name,
            'description' => $description,
            'color' => $color,
            'is_active' => $isActive,
            'created_at' => date('Y-m-d H:i:s')
        ], 'id = ' . $projectId);

        ActivityLogger::log($userId, 'project_updated', 'projects', $projectId);
        ResponseHelper::success('Project updated', ['id' => $projectId], 200);
    } else {
        $lastId = $db->insert('projects', [
            'user_id' => $userId,
            'name' => $name,
            'description' => $description,
            'color' => $color,
            'is_active' => $isActive
        ]);

        ActivityLogger::log($userId, 'project_created', 'projects', $lastId);
        ResponseHelper::success('Project created', ['id' => $lastId], 201);
    }
} catch (Exception $e) {
    ResponseHelper::error('Failed to save project: ' . $e->getMessage(), 500);
}

?>
