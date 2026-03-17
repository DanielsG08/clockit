<?php
/**
 * Security Utilities
 * Handles CSRF tokens, password hashing, rate limiting, input validation
 */

class SecurityHelper {
    const CSRF_TOKEN_LENGTH = 32;
    const RATE_LIMIT_ATTEMPTS = 5;
    const RATE_LIMIT_WINDOW = 900; // 15 minutes

    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(self::CSRF_TOKEN_LENGTH));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verify CSRF token
     */
    public static function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Hash password using bcrypt
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Verify password against hash
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Check rate limiting for login attempts
     */
    public static function checkRateLimit($email, $ipAddress) {
        $db = Database::getInstance()->getConnection();

        // Clean old attempts
        $cutoffTime = date('Y-m-d H:i:s', strtotime('-' . self::RATE_LIMIT_WINDOW . ' seconds'));
        $db->prepare("DELETE FROM login_attempts WHERE attempted_at < ?")->execute([$cutoffTime]);

        // Count recent attempts
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM login_attempts WHERE email = ? AND ip_address = ? AND attempted_at > ?");
        $stmt->execute([$email, $ipAddress, $cutoffTime]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['count'] < self::RATE_LIMIT_ATTEMPTS;
    }

    /**
     * Record login attempt
     */
    public static function recordLoginAttempt($email, $ipAddress) {
        $db = Database::getInstance();
        $db->insert('login_attempts', [
            'email' => $email,
            'ip_address' => $ipAddress
        ]);
    }

    /**
     * Clear login attempts for email
     */
    public static function clearLoginAttempts($email) {
        $db = Database::getInstance();
        $db->delete('login_attempts', 'email = ?', [$email]);
    }

    /**
     * Sanitize input
     */
    public static function sanitize($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate password strength
     */
    public static function validatePasswordStrength($password) {
        $rules = [
            'length' => strlen($password) >= 8,
            'uppercase' => preg_match('/[A-Z]/', $password),
            'lowercase' => preg_match('/[a-z]/', $password),
            'number' => preg_match('/[0-9]/', $password),
            'special' => preg_match('/[^a-zA-Z0-9]/', $password)
        ];
        return $rules;
    }

    /**
     * Get client IP address
     */
    public static function getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
    }

    /**
     * Generate secure random token
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
}

/**
 * Response Helper for API and AJAX
 */
class ResponseHelper {
    public static function json($success = true, $message = '', $data = null, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ]);
        exit;
    }

    public static function error($message, $statusCode = 400, $data = null) {
        self::json(false, $message, $data, $statusCode);
    }

    public static function success($message = 'Success', $data = null, $statusCode = 200) {
        self::json(true, $message, $data, $statusCode);
    }
}

/**
 * Activity Logger
 */
class ActivityLogger {
    public static function log($userId, $action, $entityType = null, $entityId = null, $details = null) {
        $db = Database::getInstance();
        $db->insert('activity_logs', [
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details ? json_encode($details) : null,
            'ip_address' => SecurityHelper::getClientIP()
        ]);
    }

    public static function getUserLogs($userId, $limit = 50, $offset = 0) {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [$userId, $limit, $offset]
        );
    }

    public static function getSystemLogs($limit = 100, $offset = 0) {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT al.*, u.email FROM activity_logs al 
             JOIN users u ON al.user_id = u.id 
             ORDER BY al.created_at DESC LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }
}

/**
 * Notification Helper
 */
class NotificationHelper {
    public static function sendNotification($userId, $title, $message, $type = 'info') {
        // Store in database for future real-time implementation
        // For now, this is a placeholder
        $notification = [
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'read' => false,
            'created_at' => date('Y-m-d H:i:s')
        ];
        return $notification;
    }
}

?>
