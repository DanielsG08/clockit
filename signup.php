<?php
require_once 'config/init.php';

// Redirect if already logged in
if (isAuthenticated()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !SecurityHelper::verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Security token invalid. Please try again.';
    } else {
        $email = SecurityHelper::sanitize($_POST['email'] ?? '');
        $fullName = SecurityHelper::sanitize($_POST['full_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validation
        if (empty($email) || empty($password) || empty($confirmPassword)) {
            $error = 'All fields are required.';
        } elseif (empty($fullName)) {
            $fullName = explode('@', $email)[0]; // Use email prefix as default name
        }
        
        if (empty($error) && !SecurityHelper::validateEmail($email)) {
            $error = 'Invalid email address.';
        } elseif (empty($error) && $password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } elseif (empty($error) && strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } else {
            // Check password strength
            if (empty($error)) {
                $strength = SecurityHelper::validatePasswordStrength($password);
                if (!$strength['length'] || !$strength['uppercase'] || !$strength['lowercase'] || !$strength['number']) {
                    $error = 'Password must contain uppercase, lowercase, and numeric characters.';
                }
            }
            
            if (empty($error)) {
                // Check if email exists
                $db = Database::getInstance();
                $existing = $db->fetch("SELECT id FROM users WHERE email = ?", [$email]);

                if ($existing) {
                    $error = 'Email already registered. Please login instead.';
                } else {
                    // Create new user
                    try {
                        $userId = $db->insert('users', [
                            'email' => $email,
                            'full_name' => $fullName,
                            'password_hash' => SecurityHelper::hashPassword($password),
                            'is_admin' => 0,
                            'theme' => 'light',
                            'notifications_enabled' => 1
                        ]);

                        // Create default project
                        $db->insert('projects', [
                            'user_id' => $userId,
                            'name' => 'General',
                            'description' => 'General project for all tasks',
                            'color' => '#667eea',
                            'is_active' => 1
                        ]);

                        ActivityLogger::log($userId, 'SIGNUP', 'user', $userId);

                        $success = 'Account created successfully! Redirecting to login...';
                    } catch (Exception $e) {
                        $error = 'An error occurred during registration. Please try again.';
                    }
                }
            }
        }
    }
}

// Do NOT auto-redirect - let the form handling happen naturally
?>
<?php HTMLHelper::renderHeader('Sign Up', null); ?>
<body class="light-theme">
    <div class="container-narrow" style="padding-top: 80px; padding-bottom: 80px;">
        <div class="card" style="max-width: 450px; margin: 0 auto;">
            <div class="text-center mb-30">
                <h1 style="font-size: 2.5rem; margin-bottom: 10px;">⏱️ Clock.it</h1>
                <p class="text-muted">Create Your Account</p>
            </div>

            <?php if ($success): ?>
                <?php HTMLHelper::renderAlert($success, 'success'); ?>
                
                <div style="background: #f8f9fa; border-radius: 8px; padding: 20px; text-align: center; margin-bottom: 20px;">
                    <p style="color: #666; margin-bottom: 15px;">Your account has been created successfully!</p>
                    <p style="color: #999; font-size: 0.9rem; margin-bottom: 20px;">You can now sign in with your email and password.</p>
                    <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-primary btn-lg" style="width: 100%; padding: 12px;">
                        Proceed to Login →
                    </a>
                </div>
            <?php else: ?>
                <?php if ($error): ?>
                    <?php HTMLHelper::renderAlert($error, 'error'); ?>
                <?php endif; ?>

                <form method="POST" id="signupForm">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" placeholder="John Doe" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" placeholder="your@email.com" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="••••••••" required>
                        <small class="text-muted" style="display: block; margin-top: 5px;">
                            ✓ At least 8 characters<br>
                            ✓ One uppercase letter<br>
                            ✓ One lowercase letter<br>
                            ✓ One number
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required>
                    </div>

                    <div class="form-check mb-20">
                        <input type="checkbox" id="agree" name="agree" value="1" required>
                        <label for="agree" style="margin: 0;">I agree to the Terms of Service</label>
                    </div>

                    <?php HTMLHelper::renderCSRFField(); ?>

                    <button type="submit" name="signup" class="btn btn-primary btn-lg" style="width: 100%; margin-bottom: 15px;">
                        Create Account
                    </button>
                </form>

                <hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;">

                <div class="text-center">
                    <p class="text-muted" style="margin: 0 0 10px 0;">Already have an account?</p>
                    <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-secondary" style="width: 100%;">Sign In</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
