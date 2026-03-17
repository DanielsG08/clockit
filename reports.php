<?php
require_once 'config/init.php';

requireAuth();

$user = getCurrentUser();
$userId = $_SESSION['user_id'];
$db = Database::getInstance();

$projectId = isset($_GET['project']) ? (int)$_GET['project'] : null;
$exportFormat = isset($_GET['export']) ? $_GET['export'] : null;
$dateFrom = isset($_GET['from']) ? SecurityHelper::sanitize($_GET['from']) : date('Y-m-01');
$dateTo = isset($_GET['to']) ? SecurityHelper::sanitize($_GET['to']) : date('Y-m-d');

// Build query
$where = "ts.user_id = ?";
$params = [$userId];

if ($projectId) {
    $where .= " AND ts.project_id = ?";
    $params[] = $projectId;
}

if ($dateFrom && $dateTo) {
    $where .= " AND DATE(ts.start_time) BETWEEN ? AND ?";
    $params[] = $dateFrom;
    $params[] = $dateTo;
}

// Get all sessions for report
$sessions = $db->fetchAll(
    "SELECT ts.*, p.name as project_name FROM time_sessions ts
     LEFT JOIN projects p ON ts.project_id = p.id
     WHERE $where
     ORDER BY ts.start_time DESC",
    $params
);

// Calculate statistics
$totalDuration = 0;
$sessionsByProject = [];
$sessionsByDate = [];

foreach ($sessions as $session) {
    $totalDuration += $session['duration_seconds'];
    
    $projectName = $session['project_name'] ?? 'Uncategorized';
    if (!isset($sessionsByProject[$projectName])) {
        $sessionsByProject[$projectName] = ['count' => 0, 'duration' => 0];
    }
    $sessionsByProject[$projectName]['count']++;
    $sessionsByProject[$projectName]['duration'] += $session['duration_seconds'];
    
    $date = date('Y-m-d', strtotime($session['start_time']));
    if (!isset($sessionsByDate[$date])) {
        $sessionsByDate[$date] = ['count' => 0, 'duration' => 0];
    }
    $sessionsByDate[$date]['count']++;
    $sessionsByDate[$date]['duration'] += $session['duration_seconds'];
}

// Handle export
if ($exportFormat) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="time_report_' . date('Y-m-d') . '.csv"');
    
    echo "Project,Date,Duration (Hours),Session Duration,Notes\n";
    foreach ($sessions as $session) {
        $duration = round($session['duration_seconds'] / 3600, 2);
        echo sprintf(
            '"%s","%s",%.2f,"%s","%s"' . "\n",
            $session['project_name'] ?? 'Uncategorized',
            date('Y-m-d H:i', strtotime($session['start_time'])),
            $duration,
            formatDuration($session['duration_seconds']),
            $session['description'] ?? ''
        );
    }
    exit;
}
?>
<?php HTMLHelper::renderHeader('Reports', $user); ?>
<body class="<?php echo $user['theme'] === 'dark' ? 'dark-theme' : 'light-theme'; ?>">
    <?php HTMLHelper::renderNavigation('reports', $user); ?>

    <div class="container">
        <div style="margin-bottom: 30px;">
            <h1>Reports & Analytics</h1>
            <p class="text-muted">Track your productivity statistics and export data</p>
        </div>

        <!-- Filters -->
        <div class="card mb-30">
            <div class="card-body">
                <form method="GET" class="form-row">
                    <div class="form-group">
                        <label for="project">Project</label>
                        <select id="project" name="project">
                            <option value="">All Projects</option>
                            <?php
                            $projects = $db->fetchAll("SELECT id, name FROM projects WHERE user_id = ? ORDER BY name", [$userId]);
                            foreach ($projects as $p):
                            ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo $projectId === (int)$p['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="from">From Date</label>
                        <input type="date" id="from" name="from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                    </div>
                    <div class="form-group">
                        <label for="to">To Date</label>
                        <input type="date" id="to" name="to" value="<?php echo htmlspecialchars($dateTo); ?>">
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end;">
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Overview -->
        <div class="row mb-30">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted" style="font-size: 0.9rem; margin-bottom: 8px;">Total Time</div>
                    <div style="font-size: 2rem; font-weight: bold; color: #667eea;">
                        <?php echo formatDuration($totalDuration); ?>
                    </div>
                    <small class="text-muted">
                        <?php echo round($totalDuration / 3600, 1); ?> hours
                    </small>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="text-muted" style="font-size: 0.9rem; margin-bottom: 8px;">Sessions</div>
                    <div style="font-size: 2rem; font-weight: bold; color: #42c88a;">
                        <?php echo count($sessions); ?>
                    </div>
                    <small class="text-muted">
                        <?php echo round($totalDuration / max(count($sessions), 1) / 60, 1); ?> min avg
                    </small>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="text-muted" style="font-size: 0.9rem; margin-bottom: 8px;">Days Tracked</div>
                    <div style="font-size: 2rem; font-weight: bold; color: #f39c12;">
                        <?php echo count($sessionsByDate); ?>
                    </div>
                    <small class="text-muted">
                        <?php echo $dateFrom; ?> to <?php echo $dateTo; ?>
                    </small>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <a href="?export=csv&project=<?php echo $projectId ?? ''; ?>&from=<?php echo $dateFrom; ?>&to=<?php echo $dateTo; ?>" 
                       class="btn btn-secondary" style="width: 100%; justify-content: center;">
                        📥 Export CSV
                    </a>
                </div>
            </div>
        </div>

        <!-- Charts Data Section -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 40px;">
            <!-- By Project -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Time by Project</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($sessionsByProject)): ?>
                        <p class="text-muted" style="text-align: center; padding: 20px 0;">No data</p>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 15px;">
                            <?php foreach ($sessionsByProject as $project => $data): ?>
                                <div>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                        <span><?php echo htmlspecialchars($project); ?></span>
                                        <span style="font-weight: bold; color: #667eea;">
                                            <?php echo formatDuration($data['duration']); ?>
                                        </span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar" style="width: <?php echo ($data['duration'] / $totalDuration * 100); ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- By Date -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Time by Date</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($sessionsByDate)): ?>
                        <p class="text-muted" style="text-align: center; padding: 20px 0;">No data</p>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <?php foreach (array_slice($sessionsByDate, 0, 10) as $date => $data): ?>
                                <div style="display: flex; justify-content: space-between;">
                                    <span><?php echo formatDate($date); ?></span>
                                    <span style="font-weight: bold; color: #667eea;">
                                        <?php echo formatDuration($data['duration']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Detailed Sessions -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Detailed Sessions</h3>
            </div>
            <div class="card-body">
                <?php if (empty($sessions)): ?>
                    <p class="text-muted" style="text-align: center; padding: 20px 0;">No sessions in this period</p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Date & Time</th>
                                    <th>Duration</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $session): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($session['project_name'] ?? 'Uncategorized'); ?></td>
                                        <td><?php echo formatDateTime($session['start_time']); ?></td>
                                        <td style="font-weight: bold; color: #667eea;">
                                            <?php echo formatDuration($session['duration_seconds']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($session['description'] ?? '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php HTMLHelper::renderFooter(); ?>
</body>
</html>
