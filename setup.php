#!/usr/bin/env php
<?php
/**
 * Clock.it Setup Script
 * Initializes database and creates demo user
 */

echo "╔════════════════════════════════════════════════════╗\n";
echo "║  Clock.it - Advanced Time Tracking Application    ║\n";
echo "║              Database Setup Script                ║\n";
echo "╚════════════════════════════════════════════════════╝\n\n";

require_once __DIR__ . '/config/init.php';

try {
    echo "[✓] Database initialization...\n";
    $db = Database::getInstance();
    echo "[✓] Database connected successfully\n\n";

    // Create demo user if not exists
    $demoUser = $db->fetch("SELECT id FROM users WHERE email = ?", ['demo@example.com']);

    if (!$demoUser) {
        echo "[*] Creating demo user account...\n";
        
        $demoUserId = $db->insert('users', [
            'email' => 'demo@example.com',
            'full_name' => 'Demo User',
            'password_hash' => SecurityHelper::hashPassword('Demo123!@'),
            'is_admin' => 0,
            'theme' => 'light',
            'notifications_enabled' => 1
        ]);

        echo "[✓] Demo user created (ID: $demoUserId)\n";

        // Create demo projects
        echo "[*] Creating demo projects...\n";
        
        $projects = [
            [
                'name' => 'Web Development',
                'description' => 'Frontend and backend development tasks',
                'color' => '#667eea'
            ],
            [
                'name' => 'Documentation',
                'description' => 'Writing and updating documentation',
                'color' => '#42c88a'
            ],
            [
                'name' => 'Testing',
                'description' => 'QA and testing activities',
                'color' => '#f39c12'
            ],
            [
                'name' => 'Meetings',
                'description' => 'Team meetings and discussions',
                'color' => '#3498db'
            ]
        ];

        foreach ($projects as $project) {
            $db->insert('projects', array_merge(
                $project,
                ['user_id' => $demoUserId, 'is_active' => 1]
            ));
        }

        echo "[✓] Created " . count($projects) . " demo projects\n";

        // Create demo time sessions
        echo "[*] Creating demo time sessions...\n";
        
        $projectIds = $db->fetchAll("SELECT id FROM projects WHERE user_id = ?", [$demoUserId]);
        
        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');
        
        $sessions = [];
        for ($i = 0; $i < 5; $i++) {
            $startTime = date('Y-m-d H:i:s', strtotime("$today " . (9 + ($i * 2)) . ":00:00"));
            $endTime = date('Y-m-d H:i:s', strtotime($startTime . " +1 hour 30 minutes"));
            $duration = 5400; // 1.5 hours
            
            $db->insert('time_sessions', [
                'user_id' => $demoUserId,
                'project_id' => $projectIds[$i % count($projectIds)]['id'],
                'start_time' => $startTime,
                'end_time' => $endTime,
                'duration_seconds' => $duration,
                'description' => 'Demo session ' . ($i + 1)
            ]);
        }

        echo "[✓] Created 5 demo time sessions\n";

    } else {
        echo "[!] Demo user already exists\n";
    }

    echo "\n╔════════════════════════════════════════════════════╗\n";
    echo "║              Setup Complete!                       ║\n";
    echo "╠════════════════════════════════════════════════════╣\n";
    echo "║ Demo Account Credentials:                          ║\n";
    echo "║ ├─ Email: demo@example.com                         ║\n";
    echo "║ ├─ Password: Demo123!@                             ║\n";
    echo "║ └─ Role: Regular User                              ║\n";
    echo "║                                                    ║\n";
    echo "║ Access the application:                            ║\n";
    echo "║ → http://localhost/clock-it/                       ║\n";
    echo "╚════════════════════════════════════════════════════╝\n\n";

    exit(0);

} catch (Exception $e) {
    echo "\n[✕] Error: " . $e->getMessage() . "\n\n";
    exit(1);
}
?>
