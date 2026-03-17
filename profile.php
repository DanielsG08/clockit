<?php
require_once 'config/init.php';

requireAuth();

$user = getCurrentUser();
$userId = $_SESSION['user_id'];
$db = Database::getInstance();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!SecurityHelper::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token invalid.';
    } elseif ($_POST['action'] === 'update_profile') {
        $fullName = SecurityHelper::sanitize($_POST['full_name'] ?? '');
        
        try {
            $db->update('users', ['full_name' => $fullName], 'id = ?', [$userId]);
            $_SESSION['user_name'] = $fullName;
            $success = 'Profile updated successfully.';
        } catch (Exception $e) {
            $error = 'Failed to update profile.';
        }
    } elseif ($_POST['action'] === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'All password fields are required.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'New password must be at least 8 characters.';
        } else {
            $currentUser = $db->fetch("SELECT password_hash FROM users WHERE id = ?", [$userId]);
            
            if (!SecurityHelper::verifyPassword($currentPassword, $currentUser['password_hash'])) {
                $error = 'Current password is incorrect.';
            } else {
                $strength = SecurityHelper::validatePasswordStrength($newPassword);
                if (!$strength['length'] || !$strength['uppercase'] || !$strength['lowercase'] || !$strength['number']) {
                    $error = 'New password must contain uppercase, lowercase, and numeric characters.';
                } else {
                    $db->update('users', ['password_hash' => SecurityHelper::hashPassword($newPassword)], 'id = ?', [$userId]);
                    ActivityLogger::log($userId, 'CHANGE_PASSWORD', 'user', $userId);
                    $success = 'Password changed successfully.';
                }
            }
        }
    }
}

// Get user statistics
$stats = $db->fetch(
    "SELECT 
        COUNT(DISTINCT DATE(start_time)) as days_tracked,
        COUNT(*) as total_sessions,
        SUM(duration_seconds) as total_time
     FROM time_sessions
     WHERE user_id = ?",
    [$userId]
);
?>
<?php HTMLHelper::renderHeader('Profile', $user); ?>
<body class="<?php echo $user['theme'] === 'dark' ? 'dark-theme' : 'light-theme'; ?>">
    <?php HTMLHelper::renderNavigation('profile', $user); ?>

    <div class="container">
        <div style="margin-bottom: 30px;">
            <h1>👤 Your Profile</h1>
            <p class="text-muted">Manage your account information</p>
        </div>

        <?php if ($success): ?>
            <?php HTMLHelper::renderAlert($success, 'success'); ?>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <?php HTMLHelper::renderAlert($error, 'error'); ?>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px;">
            <!-- Left Column -->
            <div>
                <!-- Profile Card -->
                <div class="card mb-30">
                    <div class="card-body" style="text-align: center;">
                        <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; margin: 0 auto 15px; font-weight: bold;">
                            <?php echo ucfirst(substr($user['email'], 0, 1)); ?>
                        </div>
                        <div style="font-size: 1.2rem; font-weight: bold;">
                            <?php echo htmlspecialchars($user['full_name'] ?? explode('@', $user['email'])[0]); ?>
                        </div>
                        <div class="text-muted" style="word-break: break-all;">
                            <?php echo htmlspecialchars($user['email']); ?>
                        </div>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Statistics</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; flex-direction: column; gap: 15px;">
                            <div>
                                <div class="text-muted" style="font-size: 0.9rem;">Days Tracked</div>
                                <div style="font-size: 1.8rem; font-weight: bold; color: #667eea;">
                                    <?php echo $stats['days_tracked'] ?? 0; ?>
                                </div>
                            </div>
                            <div>
                                <div class="text-muted" style="font-size: 0.9rem;">Total Sessions</div>
                                <div style="font-size: 1.8rem; font-weight: bold; color: #42c88a;">
                                    <?php echo $stats['total_sessions'] ?? 0; ?>
                                </div>
                            </div>
                            <div>
                                <div class="text-muted" style="font-size: 0.9rem;">Total Time</div>
                                <div style="font-size: 1.8rem; font-weight: bold; color: #f39c12;">
                                    <?php echo formatDuration($stats['total_time'] ?? 0); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Update Profile -->
                <div class="card mb-30">
                    <div class="card-header">
                        <h3 class="card-title">Update Profile</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label for="full_name">Full Name</label>
                                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" placeholder="John Doe">
                            </div>
                            <div class="form-group">
                                <label for="email">Email (Read-only)</label>
                                <input type="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                            </div>
                            <button type="submit" name="action" value="update_profile" class="btn btn-primary">Save Changes</button>
                            <?php HTMLHelper::renderCSRFField(); ?>
                        </form>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Change Password</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password" placeholder="••••••••" required>
                            </div>
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" placeholder="••••••••" required>
                                <small class="text-muted" style="display: block; margin-top: 5px;">
                                    At least 8 characters with uppercase, lowercase, and numbers
                                </small>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required>
                            </div>
                            <button type="submit" name="action" value="change_password" class="btn btn-primary">Update Password</button>
                            <?php HTMLHelper::renderCSRFField(); ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php HTMLHelper::renderFooter(); ?>
</body>
</html>
