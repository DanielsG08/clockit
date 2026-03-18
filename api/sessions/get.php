<?php
header('Content-Type: application/json');

require_once '../../config/init.php';

requireAuth();

$userId = $_SESSION['user_id'];
$db = Database::getInstance();

// Params: from (YYYY-MM-DD), to (YYYY-MM-DD), date (YYYY-MM-DD), project_id, limit, offset
$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;
$date = $_GET['date'] ?? null;
$projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
$limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 100;
$offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

// If single date provided, use it as from/to
if ($date && !$from && !$to) {
    $from = $date;
    $to = $date;
}

// Validate date format if provided
if ($from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    ResponseHelper::error('Invalid from date format', 400);
}
if ($to && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    ResponseHelper::error('Invalid to date format', 400);
}

try {
    $params = [$userId];
    $where = "ts.user_id = ?";

    if ($projectId) {
        $where .= " AND ts.project_id = ?";
        $params[] = $projectId;
    }

    if ($from && $to) {
        $where .= " AND DATE(ts.start_time) BETWEEN ? AND ?";
        $params[] = $from;
        $params[] = $to;
    } elseif ($from) {
        $where .= " AND DATE(ts.start_time) >= ?";
        $params[] = $from;
    } elseif ($to) {
        $where .= " AND DATE(ts.start_time) <= ?";
        $params[] = $to;
    }

    $sql = "SELECT ts.id, ts.project_id, ts.start_time, ts.end_time, ts.duration_seconds, ts.description, p.name as project_name
            FROM time_sessions ts
            LEFT JOIN projects p ON ts.project_id = p.id
            WHERE {$where}
            ORDER BY ts.start_time DESC
            LIMIT ? OFFSET ?";

    $params[] = $limit;
    $params[] = $offset;

    $sessions = $db->fetchAll($sql, $params);

    ResponseHelper::success('Sessions retrieved', ['sessions' => $sessions, 'limit' => $limit, 'offset' => $offset]);
} catch (Exception $e) {
    ResponseHelper::error('Failed to fetch sessions: ' . $e->getMessage(), 500);
}

?>
