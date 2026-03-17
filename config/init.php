<?php
/**
 * Application Initialization
 * Load all necessary dependencies
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Always off to prevent HTML in API responses
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/../data/error.log');

// Custom error handler to prevent output corruption
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Log the error
    error_log("[$errno] $errstr in $errfile:$errline");
    // Don't display to user
    return true;
});

// Catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("Fatal: [{$error['type']}] {$error['message']} in {$error['file']}:{$error['line']}");
        
        // If we haven't sent output yet and content-type is JSON, return JSON error
        if (!headers_sent() && strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
            header('Content-Type: application/json', true);
            echo json_encode(['success' => false, 'message' => 'Server error occurred']);
        }
    }
});

// Session timeout configuration
define('SESSION_TIMEOUT', 3600); // 1 hour

// Set session handlers BEFORE session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path' => '/',
        'domain' => '',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// Load configuration files
require_once dirname(__FILE__) . '/database.php';
require_once dirname(__FILE__) . '/security.php';
require_once dirname(__FILE__) . '/html-helper.php';

// Define app constants
define('APP_NAME', 'Clock.it');
define('APP_VERSION', '2.0.0');
define('BASE_URL', rtrim(dirname($_SERVER['PHP_SELF']), '/'));

// Session validation
if (isset($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
    if ((time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_destroy();
        header('Location: ' . BASE_URL . '/index.php?session=expired');
        exit;
    }
}
$_SESSION['last_activity'] = time();

// Timezone
date_default_timezone_set('UTC');

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_email']);
}

/**
 * Get current user
 */
function getCurrentUser() {
    if (!isAuthenticated()) return null;
    $db = Database::getInstance();
    return $db->fetch("SELECT id, email, full_name, is_admin, theme FROM users WHERE id = ?", [$_SESSION['user_id']]);
}

/**
 * Redirect if not authenticated
 */
function requireAuth() {
    if (!isAuthenticated()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

/**
 * Redirect if not admin
 */
function requireAdmin() {
    requireAuth();
    $user = getCurrentUser();
    if (!$user || !$user['is_admin']) {
        header('HTTP/1.0 403 Forbidden');
        exit('Access Denied');
    }
}

/**
 * Format duration in seconds to HH:MM:SS
 */
function formatDuration($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
}

/**
 * Format datetime to readable format
 */
function formatDateTime($datetime) {
    $date = new DateTime($datetime);
    return $date->format('M d, Y H:i');
}

/**
 * Get time since
 */
function getTimeSince($datetime) {
    $date = new DateTime($datetime);
    $now = new DateTime();
    $diff = $now->diff($date);

    if ($diff->days > 0) {
        return $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
    } elseif ($diff->h > 0) {
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    } elseif ($diff->i > 0) {
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    } else {
        return 'just now';
    }
}
?>
