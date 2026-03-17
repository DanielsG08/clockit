<?php
require_once '../config/init.php';

requireAdmin();

$user = getCurrentUser();
$userId = $_SESSION['user_id'];
$db = Database::getInstance();

// Get statistics
$totalUsers = $db->fetch("SELECT COUNT(*) as count FROM users")['count'];
$totalSessions = $db->fetch("SELECT COUNT(*) as count FROM time_sessions")['count'];
$totalTime = $db->fetch("SELECT SUM(duration_seconds) as total FROM time_sessions")['total'] ?? 0;
$totalProjects = $db->fetch("SELECT COUNT(*) as count FROM projects")['count'];

// Get recent activity
$recentActivity = $db->fetchAll(
    "SELECT al.*, u.email FROM activity_logs al
     JOIN users u ON al.user_id = u.id
     ORDER BY al.created_at DESC LIMIT 20"
);

// Get all users
$users = $db->fetchAll("SELECT id, email, full_name, created_at, is_admin FROM users ORDER BY created_at DESC");

// Handle user management actions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!SecurityHelper::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Security token invalid.';
    } elseif ($_POST['action'] === 'toggle_admin' && isset($_POST['user_id'])) {
        $targetUserId = (int)$_POST['user_id'];
        $user_to_update = $db->fetch("SELECT is_admin FROM users WHERE id = ?", [$targetUserId]);
        
        if ($user_to_update) {
            $newStatus = $user_to_update['is_admin'] ? 0 : 1;
            $db->update('users', ['is_admin' => $newStatus], 'id = ?', [$targetUserId]);
            ActivityLogger::log($userId, 'TOGGLE_USER_ADMIN', 'user', $targetUserId);
            $message = 'User admin status updated.';
        }
    } elseif ($_POST['action'] === 'delete_user' && isset($_POST['user_id'])) {
        $targetUserId = (int)$_POST['user_id'];
        if ($targetUserId !== $userId) {
            $db->delete('users', 'id = ?', [$targetUserId]);
            ActivityLogger::log($userId, 'DELETE_USER', 'user', $targetUserId);
            $message = 'User deleted.';
        } else {
            $message = 'Cannot delete yourself.';
        }
    }
}
?>
<?php HTMLHelper::renderHeader('Admin Dashboard', $user); ?>
<body class="<?php echo $user['theme'] === 'dark' ? 'dark-theme' : 'light-theme'; ?>">
    <?php HTMLHelper::renderNavigation('admin', $user); ?>

    <div class="container">
        <div style="margin-bottom: 40px;">
            <h1>🔧 Admin Dashboard</h1>
            <p class="text-muted">System overview and user management</p>
        </div>

        <?php if ($message): ?>
            <?php HTMLHelper::renderAlert($message, 'info'); ?>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row mb-40">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted" style="font-size: 0.9rem; margin-bottom: 8px;">Total Users</div>
                    <div style="font-size: 2rem; font-weight: bold; color: #667eea;">
                        <?php echo $totalUsers; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="text-muted" style="font-size: 0.9rem; margin-bottom: 8px;">Total Sessions</div>
                    <div style="font-size: 2rem; font-weight: bold; color: #42c88a;">
                        <?php echo $totalSessions; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="text-muted" style="font-size: 0.9rem; margin-bottom: 8px;">Total Projects</div>
                    <div style="font-size: 2rem; font-weight: bold; color: #f39c12;">
                        <?php echo $totalProjects; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="text-muted" style="font-size: 0.9rem; margin-bottom: 8px;">Total Time Tracked</div>
                    <div style="font-size: 2rem; font-weight: bold; color: #3498db;">
                        <?php echo formatDuration($totalTime); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card mb-40">
            <div class="card-header">
                <h3 class="card-title">Recent Activity</h3>
            </div>
            <div class="card-body">
                <div style="overflow-x: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th>Type</th>
                                <th>Time</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentActivity as $activity): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($activity['email']); ?></td>
                                    <td><?php echo htmlspecialchars($activity['action']); ?></td>
                                    <td><?php echo htmlspecialchars($activity['entity_type'] ?? '-'); ?></td>
                                    <td><?php echo getTimeSince($activity['created_at']); ?></td>
                                    <td><small><?php echo htmlspecialchars($activity['ip_address'] ?? '-'); ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- User Management -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">User Management</h3>
            </div>
            <div class="card-body">
                <div style="overflow-x: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Email</th>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td><?php echo htmlspecialchars($u['full_name'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge <?php echo $u['is_admin'] ? 'badge-primary' : 'badge-success'; ?>">
                                            <?php echo $u['is_admin'] ? 'Admin' : 'User'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($u['created_at']); ?></td>
                                    <td>
                                        <?php if ($u['id'] !== $userId): ?>
                                            <form method="POST" style="display: flex; gap: 5px;">
                                                <?php HTMLHelper::renderCSRFField(); ?>
                                                <button type="submit" name="action" value="toggle_admin" class="btn btn-secondary btn-sm">
                                                    <?php echo $u['is_admin'] ? 'Revoke' : 'Grant'; ?> Admin
                                                </button>
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <?php HTMLHelper::renderCSRFField(); ?>
                                                <button type="submit" name="action" value="delete_user" class="btn btn-danger btn-sm" onclick="return confirm('Delete user?')">
                                                    Delete
                                                </button>
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            </form>
                                        <?php else: ?>
                                            <span class="badge badge-primary">You</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php HTMLHelper::renderFooter(); ?>
</body>
</html>
