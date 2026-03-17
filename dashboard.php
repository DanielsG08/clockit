<?php
require_once 'config/init.php';

requireAuth();

$user = getCurrentUser();
$userId = $_SESSION['user_id'];
$db = Database::getInstance();

// Get today's total time
$todayResult = $db->fetch(
    "SELECT SUM(duration_seconds) as total FROM time_sessions 
     WHERE user_id = ? AND DATE(start_time) = DATE('now')",
    [$userId]
);
$todayTotal = $todayResult['total'] ?? 0;

// Get this week's total
$weekResult = $db->fetch(
    "SELECT SUM(duration_seconds) as total FROM time_sessions 
     WHERE user_id = ? AND DATE(start_time) >= DATE('now', '-7 days')",
    [$userId]
);
$weekTotal = $weekResult['total'] ?? 0;

// Get projects
$projects = $db->fetchAll(
    "SELECT * FROM projects WHERE user_id = ? AND is_active = 1 ORDER BY name",
    [$userId]
);

// Get recent sessions
$recentSessions = $db->fetchAll(
    "SELECT ts.*, p.name as project_name FROM time_sessions ts
     LEFT JOIN projects p ON ts.project_id = p.id
     WHERE ts.user_id = ? 
     ORDER BY ts.start_time DESC LIMIT 5",
    [$userId]
);

// Get statistics
$totalSessions = $db->fetch(
    "SELECT COUNT(*) as count, SUM(duration_seconds) as total FROM time_sessions WHERE user_id = ?",
    [$userId]
);
$userName = $user && $user['full_name'] ? htmlspecialchars($user['full_name']) : (isset($user['email']) ? explode('@', $user['email'])[0] : 'User');
?>
<?php HTMLHelper::renderHeader('Dashboard', $user); ?>
<body class="<?php echo $user['theme'] === 'dark' ? 'dark-theme' : 'light-theme'; ?>">
    <?php HTMLHelper::renderNavigation('dashboard', $user); ?>

    <div class="container">
        <div style="margin-bottom: 40px;">
            <h1>Welcome back, <?php echo $userName; ?>! 👋</h1>
            <p class="text-muted">Track your time and boost productivity</p>
        </div>

        <!-- Quick Stats -->
        <div class="row" style="margin-bottom: 40px;">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted" style="font-size: 0.9rem; margin-bottom: 8px;">Today's Time</div>
                    <div style="font-size: 2rem; font-weight: bold; color: #667eea;">
                        <?php echo formatDuration($todayTotal); ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="text-muted" style="font-size: 0.9rem; margin-bottom: 8px;">This Week</div>
                    <div style="font-size: 2rem; font-weight: bold; color: #42c88a;">
                        <?php echo formatDuration($weekTotal); ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="text-muted" style="font-size: 0.9rem; margin-bottom: 8px;">Total Sessions</div>
                    <div style="font-size: 2rem; font-weight: bold; color: #f39c12;">
                        <?php echo $totalSessions['count'] ?? 0; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="text-muted" style="font-size: 0.9rem; margin-bottom: 8px;">Total Time Tracked</div>
                    <div style="font-size: 2rem; font-weight: bold; color: #3498db;">
                        <?php echo formatDuration($totalSessions['total'] ?? 0); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-bottom: 40px;">
            <!-- Left Column -->
            <div>
                <!-- Quick Actions -->
                <div class="card mb-30">
                    <div class="card-header">
                        <h3 class="card-title">Quick Start</h3>
                    </div>
                    <div class="card-body">
                        <div class="btn-group" style="flex-direction: column;">
                            <a href="<?php echo BASE_URL; ?>/stopwatch.php" class="btn btn-primary" style="justify-content: flex-start;">
                                ⏱️ Start Timer
                            </a>
                            <a href="<?php echo BASE_URL; ?>/projects.php" class="btn btn-secondary" style="justify-content: flex-start;">
                                📁 Manage Projects
                            </a>
                            <a href="<?php echo BASE_URL; ?>/reports.php" class="btn btn-secondary" style="justify-content: flex-start;">
                                📊 View Reports
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Recent Sessions -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Sessions</h3>
                        <a href="<?php echo BASE_URL; ?>/calendar.php" style="text-decoration: none; color: #667eea; font-size: 0.9rem;">View All →</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentSessions)): ?>
                            <p class="text-muted" style="text-align: center; padding: 20px 0;">No sessions yet. Start tracking!</p>
                        <?php else: ?>
                            <div style="display: flex; flex-direction: column; gap: 15px;">
                                <?php foreach ($recentSessions as $session): ?>
                                    <div style="padding: 12px; border-radius: 5px; background: var(--bg-secondary); border-left: 4px solid #667eea;">
                                        <div style="display: flex; justify-content: space-between; align-items: start;">
                                            <div>
                                                <div style="font-weight: 600;">
                                                    <?php echo htmlspecialchars($session['project_name'] ?? 'Untitled'); ?>
                                                </div>
                                                <div class="text-muted" style="font-size: 0.85rem;">
                                                    <?php echo formatDateTime($session['start_time']); ?>
                                                </div>
                                                <?php if ($session['description']): ?>
                                                    <div class="text-muted" style="font-size: 0.85rem;">
                                                        <?php echo htmlspecialchars($session['description']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div style="text-align: right; font-weight: bold; color: #667eea;">
                                                <?php echo formatDuration($session['duration_seconds'] ?? 0); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Projects -->
                <div class="card mb-30">
                    <div class="card-header">
                        <h3 class="card-title">Projects</h3>
                        <a href="projects.php" style="text-decoration: none; color: #667eea; font-size: 0.9rem;">Manage →</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($projects)): ?>
                            <p class="text-muted" style="text-align: center; padding: 20px 0;">No projects yet</p>
                        <?php else: ?>
                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                <?php foreach (array_slice($projects, 0, 5) as $project): ?>
                                    <div style="padding: 10px; border-radius: 5px; background: var(--bg-secondary); display: flex; justify-content: space-between; align-items: center;">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div style="width: 12px; height: 12px; border-radius: 50%; background: <?php echo htmlspecialchars($project['color']); ?>;"></div>
                                            <span><?php echo htmlspecialchars($project['name']); ?></span>
                                        </div>
                                        <a href="projects.php?id=<?php echo $project['id']; ?>" class="text-primary" style="text-decoration: none;">→</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tips -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">💡 Tips</h3>
                    </div>
                    <div class="card-body" style="font-size: 0.9rem;">
                        <ul style="margin: 0; padding-left: 20px;">
                            <li style="margin-bottom: 10px;">Create projects to organize your work</li>
                            <li style="margin-bottom: 10px;">Use the stopwatch for real-time tracking</li>
                            <li style="margin-bottom: 10px;">Review reports to analyze productivity</li>
                            <li>Take regular breaks for better focus</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php HTMLHelper::renderFooter(); ?>
</body>
</html>
