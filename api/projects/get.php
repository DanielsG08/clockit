<?php
header('Content-Type: application/json');

require_once '../../config/init.php';

requireAuth();

$userId = $_SESSION['user_id'];
$db = Database::getInstance();

// Optional id to fetch single project
$projectId = isset($_GET['id']) && $_GET['id'] !== '' ? (int)$_GET['id'] : null;

try {
    if ($projectId) {
        $proj = $db->fetch("SELECT id, name, description, color, is_active FROM projects WHERE id = ? AND user_id = ?", [$projectId, $userId]);
        if (!$proj) {
            ResponseHelper::error('Project not found', 404);
        }
        ResponseHelper::success('Project retrieved', $proj);
    } else {
        $projects = $db->fetchAll("SELECT id, name, description, color, is_active FROM projects WHERE user_id = ? ORDER BY name ASC", [$userId]);
        ResponseHelper::success('Projects retrieved', $projects);
    }
} catch (Exception $e) {
    ResponseHelper::error('Failed to fetch projects: ' . $e->getMessage(), 500);
}

?>
