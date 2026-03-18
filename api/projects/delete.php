<?php
header('Content-Type: application/json');

require_once '../../config/init.php';

requireAuth();

$userId = $_SESSION['user_id'];
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ResponseHelper::error('Method not allowed', 405);
}

// CSRF validation
if (!isset($_POST['csrf_token']) || !SecurityHelper::verifyCSRFToken($_POST['csrf_token'])) {
    ResponseHelper::error('Invalid CSRF token', 403);
}

$projectId = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;

if (!$projectId) {
    ResponseHelper::error('Project ID is required', 400);
}

try {
    $proj = $db->fetch("SELECT id FROM projects WHERE id = ? AND user_id = ?", [$projectId, $userId]);
    if (!$proj) {
        ResponseHelper::error('Project not found', 404);
    }

    $db->delete('projects', 'id = ' . $projectId);

    ActivityLogger::log($userId, 'project_deleted', 'projects', $projectId);
    ResponseHelper::success('Project deleted');
} catch (Exception $e) {
    ResponseHelper::error('Failed to delete project: ' . $e->getMessage(), 500);
}

?>
