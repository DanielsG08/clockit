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
    } elseif ($_POST['action'] === 'update_settings') {
        $theme = in_array($_POST['theme'] ?? 'light', ['light', 'dark']) ? $_POST['theme'] : 'light';
        $notificationsEnabled = isset($_POST['notifications_enabled']) ? 1 : 0;

        try {
            $db->update('users', [
                'theme' => $theme,
                'notifications_enabled' => $notificationsEnabled
            ], 'id = ?', [$userId]);

            ActivityLogger::log($userId, 'UPDATE_SETTINGS', 'user', $userId);
            $success = 'Settings updated successfully.';
            
            // Refresh user data
            $user = getCurrentUser();
        } catch (Exception $e) {
            $error = 'Failed to update settings.';
        }
    }
}
?>
<?php HTMLHelper::renderHeader('Settings', $user); ?>
<body class="<?php echo $user['theme'] === 'dark' ? 'dark-theme' : 'light-theme'; ?>">
    <?php HTMLHelper::renderNavigation('settings', $user); ?>

    <div class="container">
        <div style="margin-bottom: 30px;">
            <h1>⚙️ Settings</h1>
            <p class="text-muted">Customize your experience</p>
        </div>

        <?php if ($success): ?>
            <?php HTMLHelper::renderAlert($success, 'success'); ?>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <?php HTMLHelper::renderAlert($error, 'error'); ?>
        <?php endif; ?>

        <div style="max-width: 600px;">
            <!-- Theme Settings -->
            <div class="card mb-30">
                <div class="card-header">
                    <h3 class="card-title">Appearance</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label for="theme">Theme</label>
                            <select id="theme" name="theme" class="form">
                                <option value="light" <?php echo $user['theme'] === 'light' ? 'selected' : ''; ?>>Light</option>
                                <option value="dark" <?php echo $user['theme'] === 'dark' ? 'selected' : ''; ?>>Dark</option>
                            </select>
                            <small class="text-muted" style="display: block; margin-top: 8px;">
                                Choose your preferred color theme
                            </small>
                        </div>

                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" id="notifications_enabled" name="notifications_enabled" value="1" 
                                    <?php echo ($user['notifications_enabled'] ?? 1) ? 'checked' : ''; ?>>
                                <label for="notifications_enabled" style="margin: 0;">Enable notifications</label>
                            </div>
                            <small class="text-muted" style="display: block; margin-top: 8px;">
                                Receive alerts for important events
                            </small>
                        </div>

                        <button type="submit" name="action" value="update_settings" class="btn btn-primary">Save Settings</button>
                        <?php HTMLHelper::renderCSRFField(); ?>
                    </form>
                </div>
            </div>

            <!-- Data & Privacy -->
            <div class="card mb-30">
                <div class="card-header">
                    <h3 class="card-title">Data & Privacy</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted" style="margin-bottom: 15px;">Your data is stored securely and never shared with third parties.</p>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="reports.php?export=csv" class="btn btn-secondary">📥 Export Data</a>
                        <button type="button" class="btn btn-secondary" onclick="alert('Contact support to request deletion.')">🗑️ Delete Account</button>
                    </div>
                </div>
            </div>

            <!-- Security -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Security</h3>
                </div>
                <div class="card-body">
                    <div style="margin-bottom: 15px;">
                        <strong>Active Sessions:</strong>
                        <p class="text-muted">Your current session is secure with HTTPS encryption and CSRF protection.</p>
                    </div>
                    <div>
                        <a href="logout.php" class="btn btn-danger">🚪 Logout All Sessions</a>
                    </div>
                </div>
            </div>

            <!-- About -->
            <div class="card" style="margin-top: 30px; background: var(--bg-secondary);">
                <div class="card-body" style="text-align: center;">
                    <h4>⏱️ Clock.it v2.0.0</h4>
                    <p class="text-muted">Advanced time tracking and productivity management</p>
                    <small class="text-muted">
                        © 2024 Clock.it. All rights reserved.<br>
                        <a href="#" style="color: #667eea;">Privacy Policy</a> | 
                        <a href="#" style="color: #667eea;">Terms of Service</a>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <?php HTMLHelper::renderFooter(); ?>
    
    <script>
        // Sync localStorage with database theme on page load
        const currentTheme = document.body.className.includes('dark-theme') ? 'dark' : 'light';
        localStorage.setItem('clock-it-theme', currentTheme);
        
        // If settings were just saved, show notification
        <?php if ($success): ?>
            // Small delay to ensure theme change takes effect
            setTimeout(() => {
                // Theme is now synced with database
            }, 100);
        <?php endif; ?>
    </script>
</body>
</html>
