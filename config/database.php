<?php
/**
 * Database Configuration & Connection
 * Supports SQLite for easy setup, can be extended to MySQL
 */

class Database {
    private static $instance = null;
    private $connection;
    private $dbType = 'sqlite'; // Can be 'sqlite' or 'mysql'
    private $dbPath;

    private function __construct() {
        if ($this->dbType === 'sqlite') {
            $this->dbPath = dirname(__DIR__) . '/data/clock-it.db';
            $this->connectSQLite();
        }
    }

    private function connectSQLite() {
        try {
            // Create data directory if it doesn't exist
            $dataDir = dirname($this->dbPath);
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0755, true);
            }

            $this->connection = new PDO('sqlite:' . $this->dbPath);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->initializeDatabase();
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }

    private function initializeDatabase() {
        // Check if tables exist
        $stmt = $this->connection->query("SELECT name FROM sqlite_master WHERE type='table'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('users', $tables)) {
            $this->createTables();
        }
    }

    private function createTables() {
        $queries = [
            // Users table
            "CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                full_name TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                is_admin INTEGER DEFAULT 0,
                theme TEXT DEFAULT 'light',
                notifications_enabled INTEGER DEFAULT 1
            )",

            // Projects table
            "CREATE TABLE projects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                description TEXT,
                color TEXT DEFAULT '#667eea',
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
            )",

            // Time sessions (tracking)
            "CREATE TABLE time_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                project_id INTEGER,
                start_time DATETIME NOT NULL,
                end_time DATETIME,
                duration_seconds INTEGER,
                description TEXT,
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE SET NULL
            )",

            // Breaks table
            "CREATE TABLE breaks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id INTEGER NOT NULL,
                start_time DATETIME NOT NULL,
                end_time DATETIME,
                duration_seconds INTEGER,
                break_type TEXT DEFAULT 'break',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(session_id) REFERENCES time_sessions(id) ON DELETE CASCADE
            )",

            // Activity logs
            "CREATE TABLE activity_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                action TEXT NOT NULL,
                entity_type TEXT,
                entity_id INTEGER,
                details TEXT,
                ip_address TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
            )",

            // Login attempts (for rate limiting)
            "CREATE TABLE login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL,
                ip_address TEXT NOT NULL,
                attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",

            // Sessions table (optional: for session management)
            "CREATE TABLE sessions (
                id TEXT PRIMARY KEY,
                user_id INTEGER NOT NULL,
                data TEXT,
                ip_address TEXT,
                user_agent TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
            )",

            // Team members (for future team features)
            "CREATE TABLE team_members (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                team_id INTEGER NOT NULL,
                role TEXT DEFAULT 'member',
                joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, team_id)
            )",

            // Calendar events
            "CREATE TABLE calendar_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                event_date DATE NOT NULL,
                title TEXT NOT NULL,
                description TEXT,
                event_time TIME,
                color TEXT DEFAULT '#667eea',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
            )",

            // Create index for calendar_events
            "CREATE INDEX IF NOT EXISTS idx_calendar_events_user_date ON calendar_events(user_id, event_date)"
        ];

        foreach ($queries as $query) {
            try {
                $this->connection->exec($query);
            } catch (PDOException $e) {
                // Table might already exist
            }
        }

        // Create indexes for better performance
        $this->createIndexes();
    }

    private function createIndexes() {
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_sessions_user_date ON time_sessions(user_id, start_time)",
            "CREATE INDEX IF NOT EXISTS idx_sessions_project ON time_sessions(project_id)",
            "CREATE INDEX IF NOT EXISTS idx_projects_user ON projects(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_activity_user_date ON activity_logs(user_id, created_at)",
            "CREATE INDEX IF NOT EXISTS idx_login_attempts_email ON login_attempts(email, attempted_at)"
        ];

        foreach ($indexes as $query) {
            try {
                $this->connection->exec($query);
            } catch (PDOException $e) {
                // Index might already exist
            }
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function query($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch(PDO::FETCH_ASSOC);
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert($table, $data) {
        $keys = array_keys($data);
        $placeholders = array_fill(0, count($keys), '?');
        $sql = "INSERT INTO $table (" . implode(',', $keys) . ") VALUES (" . implode(',', $placeholders) . ")";
        $this->query($sql, array_values($data));
        return $this->connection->lastInsertId();
    }

    public function update($table, $data, $where, $whereParams = []) {
        $setClause = implode(',', array_map(fn($k) => "$k=?", array_keys($data)));
        $sql = "UPDATE $table SET $setClause WHERE $where";
        $params = array_merge(array_values($data), $whereParams);
        return $this->query($sql, $params);
    }

    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM $table WHERE $where";
        return $this->query($sql, $params);
    }

    public function beginTransaction() {
        $this->connection->beginTransaction();
    }

    public function commit() {
        $this->connection->commit();
    }

    public function rollback() {
        $this->connection->rollBack();
    }
}

// Initialize database
$db = Database::getInstance();
?>
