<?php

// EMAIL        sample.user@example.com
// PASSWORD     Password123!

require_once __DIR__ . '/config/init.php';

// Load DB wrapper from config (already done by init)
$db = Database::getInstance();

// Target user account for the seeding
$userEmail = 'sample.user@example.com';
$fullName = 'Sample User';

// Ensure user exists
$user = $db->fetch('SELECT * FROM users WHERE email = ?', [$userEmail]);
if (!$user) {
    $userId = $db->insert('users', [
        'email' => $userEmail,
        'password_hash' => password_hash('Password123!', PASSWORD_DEFAULT),
        'full_name' => $fullName,
        'is_admin' => 0,
        'theme' => 'light',
        'notifications_enabled' => 1
    ]);
    echo "Created user $userEmail (id=$userId).\n";
} else {
    $userId = $user['id'];
    echo "Using existing user $userEmail (id=$userId).\n";
}

// Delete existing seeded data for this user to avoid duplication
$db->beginTransaction();
try {
    $sessions = $db->fetchAll('SELECT id FROM time_sessions WHERE user_id = ?', [$userId]);
    foreach ($sessions as $session) {
        $db->delete('breaks', 'session_id = ?', [$session['id']]);
    }
    $db->delete('time_sessions', 'user_id = ?', [$userId]);
    $db->commit();
    echo "Cleared existing sessions and breaks for user.\n";
} catch (Exception $e) {
    $db->rollback();
    echo "Failed to clear existing data: " . $e->getMessage() . "\n";
    exit(1);
}

// Create 5 months of weekdays for the selected period
$baseMonth = new DateTimeImmutable('first day of last month');
$monthsToSeed = 5;

$workDurationMin = 2 * 3600; // 2 hours
$workDurationMax = 3 * 3600; // 3 hours

$entries = [];
for ($monthOffset = 0; $monthOffset < $monthsToSeed; $monthOffset++) {
    $month = $baseMonth->modify("-$monthOffset month");
    $yearMonth = $month->format('Y-m');
    $startDate = new DateTimeImmutable("$yearMonth-01");
    $endDate = $startDate->modify('last day of this month');

    $weekdayCount = 0;
    $currentDate = $startDate;
    while ($currentDate <= $endDate) {
        if ((int)$currentDate->format('N') <= 5) {
            $weekdayCount++;
        }
        $currentDate = $currentDate->modify('+1 day');
    }

    if ($weekdayCount === 0) {
        continue;
    }

    // starting break ratio for the month is between 20-30%
    $ratioStart = rand(2000, 3000) / 10000;
    $drop = rand(10, 15) / 100; // 10-15% drop guaranteed
    $ratioEnd = max(0.1, $ratioStart - $drop); // at least 10% drop, clamped

    $currentDate = $startDate;
    $dayIndex = 0;
    $prevRatio = null;
    while ($currentDate <= $endDate) {
        $weekDayNo = (int)$currentDate->format('N');
        if ($weekDayNo <= 5) {
            $dayIndex++;
            // linear trend per weekday from start to end
            $baseRatio = $ratioStart - ($dayIndex - 1) * (($ratioStart - $ratioEnd) / max(1, $weekdayCount - 1));
            // daily jitter +/- 1.2%
            $variation = rand(-120, 120) / 10000;
            $ratio = $baseRatio + $variation;
            $ratio = max(0.10, min(0.30, $ratio));

            // ensure non-increasing trend (safely step-down) across weekdays
            if ($prevRatio !== null) {
                $ratio = min($ratio, $prevRatio - 0.001); // at least 0.1% downward each day
                $ratio = max(0.10, $ratio);
            }
            $prevRatio = $ratio;

            $durationSec = rand($workDurationMin, $workDurationMax);

            // daily duration variation only - no outliers
            // break ratio is controlled by trend + jitter only

            $breakSec = (int)round($durationSec * $ratio);

            $sessionStart = $currentDate->setTime(9, 0, 0);
            $sessionEnd = $sessionStart->modify("+$durationSec seconds");

            $breakOffsetSeconds = rand(30 * 60, 60 * 60);
            if ($breakOffsetSeconds + $breakSec > $durationSec - 300) {
                $breakOffsetSeconds = max(60, $durationSec - $breakSec - 300);
            }
            $breakStart = $sessionStart->modify("+$breakOffsetSeconds seconds");
            $breakEnd = $breakStart->modify("+$breakSec seconds");

            $entries[] = [
                'session_start' => $sessionStart->format('Y-m-d H:i:s'),
                'session_end' => $sessionEnd->format('Y-m-d H:i:s'),
                'duration' => $durationSec,
                'break_start' => $breakStart->format('Y-m-d H:i:s'),
                'break_end' => $breakEnd->format('Y-m-d H:i:s'),
                'break_seconds' => $breakSec,
                'break_ratio' => round($breakSec / max(1, $durationSec) * 100, 2),
                'date' => $currentDate->format('Y-m-d'),
            ];
        }
        $currentDate = $currentDate->modify('+1 day');
    }
}

$db->beginTransaction();
try {
    $inserted = 0;
    foreach ($entries as $entry) {
        $sessionId = $db->insert('time_sessions', [
            'user_id' => $userId,
            'project_id' => null,
            'start_time' => $entry['session_start'],
            'end_time' => $entry['session_end'],
            'duration_seconds' => $entry['duration'],
            'description' => 'Auto-generated sample work session',
            'notes' => 'Break %' . $entry['break_ratio'],
        ]);

        $db->insert('breaks', [
            'session_id' => $sessionId,
            'start_time' => $entry['break_start'],
            'end_time' => $entry['break_end'],
            'duration_seconds' => $entry['break_seconds'],
            'break_type' => 'auto',
        ]);

        $inserted++;
    }
    $db->commit();

    echo "Inserted $inserted session records with corresponding breaks for $weekdayCount weekdays of $yearMonth.\n";
    echo "Check reports for user $userEmail.\n";
} catch (Exception $e) {
    $db->rollback();
    echo "Failed to insert sessions: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Done.\n";
