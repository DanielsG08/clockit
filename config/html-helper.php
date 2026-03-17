<?php
/**
 * Common HTML Components
 */

class HTMLHelper {
    /**
     * Generate page header with navigation
     */
    public static function renderHeader($title, $user = null) {
        $theme = $user && isset($user['theme']) ? $user['theme'] : 'light';
        $baseUrl = defined('BASE_URL') ? BASE_URL : '';
        echo <<<HTML
<!DOCTYPE html>
<html lang="en" data-theme="$theme" data-base-url="$baseUrl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>$title - Clock.it</title>
    <link rel="stylesheet" href="{$baseUrl}/assets/css/style.css">
    <link rel="stylesheet" href="{$baseUrl}/assets/css/responsive.css">
    <link rel="stylesheet" href="{$baseUrl}/assets/css/dark-mode.css">
</head>
<body class="$theme-theme">
HTML;
    }

    /**
     * Generate navigation bar
     */
    public static function renderNavigation($currentPage = '', $user = null) {
        $baseUrl = defined('BASE_URL') ? BASE_URL : '';
        $userEmail = $user ? htmlspecialchars($user['email']) : '';
        $userName = $user && $user['full_name'] ? htmlspecialchars($user['full_name']) : explode('@', $userEmail)[0];
        $userInitial = !empty($userName) ? $userName[0] : '?';
        $adminClass = $user && $user['is_admin'] ? 'admin-user' : '';

        echo <<<HTML
<nav class="navbar">
    <div class="navbar-container">
        <div class="navbar-brand">
            <a href="{$baseUrl}/dashboard.php" class="brand-logo">⏱️ Clock.it</a>
        </div>
        
        <ul class="navbar-menu" id="navMenu">
            <li><a href="{$baseUrl}/dashboard.php" class="nav-link $(currentPage === 'dashboard' ? 'active' : '')">Dashboard</a></li>
            <li><a href="{$baseUrl}/stopwatch.php" class="nav-link $(currentPage === 'stopwatch' ? 'active' : '')">Stopwatch</a></li>
            <li><a href="{$baseUrl}/calendar.php" class="nav-link $(currentPage === 'calendar' ? 'active' : '')">Calendar</a></li>
            <li><a href="{$baseUrl}/reports.php" class="nav-link $(currentPage === 'reports' ? 'active' : '')">Reports</a></li>
            <li><a href="{$baseUrl}/projects.php" class="nav-link $(currentPage === 'projects' ? 'active' : '')">Projects</a></li>
HTML;

        if ($user && $user['is_admin']) {
            echo "<li><a href=\"{$baseUrl}/admin/index.php\" class=\"nav-link admin-link\">🔧 Admin</a></li>";
        }

        echo <<<HTML
        </ul>

        <div class="navbar-end">
            <button id="themeToggle" class="theme-toggle" title="Toggle dark/light mode">
                <span id="themeIcon">🌙</span>
            </button>
            
            <div class="user-menu">
                <div class="user-avatar">$userInitial</div>
                <div class="user-dropdown">
                    <div class="user-info">
                        <div class="user-name">$userName</div>
                        <div class="user-email">$userEmail</div>
                    </div>
                    <hr>
                    <a href="{$baseUrl}/profile.php" class="dropdown-item">👤 Profile</a>
                    <a href="{$baseUrl}/settings.php" class="dropdown-item">⚙️ Settings</a>
                    <hr>
                    <a href="{$baseUrl}/logout.php" class="dropdown-item logout">🚪 Logout</a>
                </div>
            </div>
        </div>
        
        <button id="menuToggle" class="menu-toggle">☰</button>
    </div>
</nav>
HTML;
    }

    /**
     * Generate page footer
     */
    public static function renderFooter() {
        $year = date('Y');
        $baseUrl = defined('BASE_URL') ? BASE_URL : '';
        echo <<<HTML
<footer class="footer">
    <div class="footer-container">
        <div class="footer-section">
            <h3>Clock.it</h3>
            <p>Advanced time tracking and productivity management tool.</p>
        </div>
        <div class="footer-section">
            <h4>Quick Links</h4>
            <ul>
                <li><a href="{$baseUrl}/dashboard.php">Dashboard</a></li>
                <li><a href="{$baseUrl}/reports.php">Reports</a></li>
                <li><a href="{$baseUrl}/settings.php">Settings</a></li>
            </ul>
        </div>
        <div class="footer-section">
            <h4>Support</h4>
            <ul>
                <li><a href="{$baseUrl}/help.php">Help Center</a></li>
                <li><a href="{$baseUrl}/docs.php">Documentation</a></li>
                <li><a href="mailto:support@clock-it.local">Contact Support</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; $year Clock.it - Advanced Time Tracking. All rights reserved.</p>
    </div>
</footer>

<script>
    window.APP_BASE_URL = window.APP_BASE_URL || document.documentElement.dataset.baseUrl || '';
</script>
<script src="{$baseUrl}/assets/js/main.js"></script>
<script src="{$baseUrl}/assets/js/theme.js"></script>
</body>
</html>
HTML;
    }

    /**
     * Generate alert message
     */
    public static function renderAlert($message, $type = 'info') {
        $class = "alert alert-$type";
        $icon = match($type) {
            'success' => '✓',
            'error' => '✕',
            'warning' => '⚠',
            'info' => 'ℹ',
            default => '•'
        };
        
        echo <<<HTML
<div class="$class" role="alert">
    <span class="alert-icon">$icon</span>
    <span class="alert-message">$message</span>
</div>
HTML;
    }

    /**
     * Generate CSRF token input field
     */
    public static function renderCSRFField() {
        $token = SecurityHelper::generateCSRFToken();
        echo "<input type='hidden' name='csrf_token' value='" . htmlspecialchars($token) . "'>";
    }
}
?>
