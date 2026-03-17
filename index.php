<?php
require_once 'config/init.php';

$error = '';
$success = '';
$sessionExpired = isset($_GET['session']) && $_GET['session'] === 'expired';

if ($sessionExpired) {
    $error = 'Your session has expired. Please login again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !SecurityHelper::verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Security token invalid. Please try again.';
    } else {
        $email = SecurityHelper::sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $ipAddress = SecurityHelper::getClientIP();

        // Check rate limiting
        if (!SecurityHelper::checkRateLimit($email, $ipAddress)) {
            $error = 'Too many login attempts. Please try again in 15 minutes.';
        } elseif (empty($email) || empty($password)) {
            $error = 'Email and password are required.';
        } elseif (!SecurityHelper::validateEmail($email)) {
            $error = 'Invalid email format.';
        } else {
            $db = Database::getInstance();
            $user = $db->fetch("SELECT id, email, full_name, password_hash, is_admin, theme FROM users WHERE email = ?", [$email]);

            if ($user && SecurityHelper::verifyPassword($password, $user['password_hash'])) {
                // Successful login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['is_admin'] = $user['is_admin'];

                SecurityHelper::clearLoginAttempts($email);
                ActivityLogger::log($user['id'], 'LOGIN', 'user', $user['id']);

                header('Location: dashboard.php');
                exit;
            } else {
                // Failed login
                SecurityHelper::recordLoginAttempt($email, $ipAddress);
                $error = 'Invalid email or password.';
                
                if ($user) {
                    ActivityLogger::log($user['id'], 'FAILED_LOGIN', 'user', $user['id']);
                }
            }
        }
    }
}
?>
<?php HTMLHelper::renderHeader('Login', null); ?>
<body class="light-theme">
    <div class="container-narrow" style="padding-top: 80px; padding-bottom: 80px;">
        <div class="card" style="max-width: 400px; margin: 0 auto;">
            <div class="text-center mb-30">
                <h1 style="font-size: 2.5rem; margin-bottom: 10px;">⏱️ Clock.it</h1>
                <p class="text-muted">Advanced Time Tracking & Productivity</p>
            </div>

            <?php if ($error): ?>
                <?php HTMLHelper::renderAlert($error, 'error'); ?>
            <?php endif; ?>

            <?php if ($sessionExpired): ?>
                <?php HTMLHelper::renderAlert('Your session has expired. Please login again.', 'warning'); ?>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="your@email.com" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>

                <div class="form-check mb-20">
                    <input type="checkbox" id="remember" name="remember" value="1">
                    <label for="remember" style="margin: 0;">Remember me</label>
                </div>

                <?php HTMLHelper::renderCSRFField(); ?>

                <button type="submit" name="login" class="btn btn-primary btn-lg" style="width: 100%; margin-bottom: 15px;">
                    Sign In
                </button>
            </form>

            <hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;">

            <div class="text-center">
                <p class="text-muted" style="margin: 0 0 10px 0;">Don't have an account?</p>
                <a href="<?php echo BASE_URL; ?>/signup.php" class="btn btn-secondary" style="width: 100%;">Create Account</a>
            </div>

            <div style="margin-top: 20px; padding: 15px; background: #f0f4ff; border-radius: 5px; text-align: center;">
                <small class="text-muted">
                    💡 Demo credentials:<br>
                    Email: demo@example.com<br>
                    Password: Demo123!@
                </small>
            </div>
        </div>
    </div>

    <style>
        hr { border: none; border-top: 2px solid #ddd; margin: 20px 0; }
    </style>
</body>
</html>