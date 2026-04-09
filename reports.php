<?php
require_once 'config/init.php';

requireAuth();

$user = getCurrentUser();
$userId = $_SESSION['user_id'];
$db = Database::getInstance();

$projectId = isset($_GET['project']) ? (int)$_GET['project'] : null;
$exportFormat = isset($_GET['export']) ? $_GET['export'] : null;
$dateFrom = isset($_GET['from']) ? SecurityHelper::sanitize($_GET['from']) : date('Y-m-01');
$dateTo = isset($_GET['to']) ? SecurityHelper::sanitize($_GET['to']) : date('Y-m-d');

// Build query
$where = "ts.user_id = ?";
$params = [$userId];

if ($projectId) {
    $where .= " AND ts.project_id = ?";
    $params[] = $projectId;
}

if ($dateFrom && $dateTo) {
    $where .= " AND DATE(ts.start_time) BETWEEN ? AND ?";
    $params[] = $dateFrom;
    $params[] = $dateTo;
}

// Get all sessions for report
$sessions = $db->fetchAll(
    "SELECT ts.*, p.name as project_name FROM time_sessions ts
     LEFT JOIN projects p ON ts.project_id = p.id
     WHERE $where
     ORDER BY ts.start_time DESC",
    $params
);

// Calculate statistics
$totalDuration = 0;
$sessionsByProject = [];
$sessionsByDate = [];

foreach ($sessions as $session) {
    $totalDuration += $session['duration_seconds'];
    
    $projectName = $session['project_name'] ?? 'Uncategorized';
    if (!isset($sessionsByProject[$projectName])) {
        $sessionsByProject[$projectName] = ['count' => 0, 'duration' => 0];
    }
    $sessionsByProject[$projectName]['count']++;
    $sessionsByProject[$projectName]['duration'] += $session['duration_seconds'];
    
    $date = date('Y-m-d', strtotime($session['start_time']));
    if (!isset($sessionsByDate[$date])) {
        $sessionsByDate[$date] = ['count' => 0, 'duration' => 0];
    }
    $sessionsByDate[$date]['count']++;
    $sessionsByDate[$date]['duration'] += $session['duration_seconds'];
}

// Break time stats
$breakResult = $db->fetch(
    "SELECT SUM(b.duration_seconds) as total FROM breaks b
     LEFT JOIN time_sessions ts ON b.session_id = ts.id
     WHERE $where",
    $params
);
$breakTotal = $breakResult['total'] ?? 0;
$breakPercent = $totalDuration > 0 ? ($breakTotal / $totalDuration * 100) : 0;

// Breaks by date (for trend chart)
$breaksByDateRows = $db->fetchAll(
    "SELECT DATE(ts.start_time) as date, SUM(b.duration_seconds) as break_seconds
     FROM breaks b
     LEFT JOIN time_sessions ts ON b.session_id = ts.id
     WHERE $where
     GROUP BY DATE(ts.start_time)",
    $params
);
$breaksByDate = [];
foreach ($breaksByDateRows as $r) {
    $breaksByDate[$r['date']] = (int)$r['break_seconds'];
}

// Break percent stats per day
$dailyBreakPercents = [];
foreach ($sessionsByDate as $date => $data) {
    $dailyDuration = $data['duration'];
    if ($dailyDuration > 0) {
        $breakSeconds = $breaksByDate[$date] ?? 0;
        $dailyBreakPercents[] = ($breakSeconds / $dailyDuration) * 100;
    }
}

$avgBreakPercent = 0;
$minBreakPercent = 0;
$maxBreakPercent = 0;
$startBreakPercent = 0;
$endBreakPercent = 0;
if (!empty($dailyBreakPercents)) {
    $avgBreakPercent = array_sum($dailyBreakPercents) / count($dailyBreakPercents);
    $minBreakPercent = min($dailyBreakPercents);
    $maxBreakPercent = max($dailyBreakPercents);
    $sortedDates = array_keys($sessionsByDate);
    sort($sortedDates);
    $firstDate = $sortedDates[0];
    $lastDate = end($sortedDates);
    $startBreakPercent = (($breaksByDate[$firstDate] ?? 0) / $sessionsByDate[$firstDate]['duration']) * 100;
    $endBreakPercent = (($breaksByDate[$lastDate] ?? 0) / $sessionsByDate[$lastDate]['duration']) * 100;
}

// Handle export
if ($exportFormat) {
    if ($exportFormat === 'html') {
        // Export as HTML with embedded charts
        header('Content-Type: text/html');
        header('Content-Disposition: attachment; filename="time_report_' . date('Y-m-d') . '.html"');

        // Prepare data for charts
        $sortedDates = array_keys($sessionsByDate);
        sort($sortedDates);

        $dates = [];
        $trackedHours = [];
        $breakHours = [];
        $breakPercents = [];

        foreach ($sortedDates as $date) {
            $trackedSeconds = $sessionsByDate[$date]['duration'];
            $breakSeconds = $breaksByDate[$date] ?? 0;
            $dates[] = $date;
            $trackedHours[] = round($trackedSeconds / 3600, 2);
            $breakHours[] = round($breakSeconds / 3600, 2);
            $breakPercents[] = $trackedSeconds > 0 ? round(($breakSeconds / $trackedSeconds) * 100, 2) : 0;
        }

        $datesJson = json_encode($dates);
        $trackedJson = json_encode($trackedHours);
        $breakJson = json_encode($breakHours);
        $percentJson = json_encode($breakPercents);

        $projectName = '';
        if ($projectId) {
            $projectName = $db->fetch("SELECT name FROM projects WHERE id = ?", [$projectId])['name'] ?? 'Unknown';
            $projectName = '<p>Project: ' . htmlspecialchars($projectName) . '</p>';
        }

        $tableRows = '';
        foreach ($sortedDates as $index => $date) {
            $tableRows .= '<tr>';
            $tableRows .= '<td>' . $dates[$index] . '</td>';
            $tableRows .= '<td>' . $trackedHours[$index] . '</td>';
            $tableRows .= '<td>' . $breakHours[$index] . '</td>';
            $tableRows .= '<td>' . $breakPercents[$index] . '</td>';
            $tableRows .= '</tr>';
        }

        $currentDate = date('Y-m-d');
        $currentDateTime = date('Y-m-d H:i:s');

        echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Time Tracking Report - {$currentDate}</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .charts-container { display: flex; gap: 30px; margin: 20px 0; flex-wrap: wrap; width: 100%; }
        .chart-container { flex: 1; min-width: calc(50% - 15px); height: 700px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        h1, h2 { color: #333; }
        .chart-section { margin-bottom: 40px; flex: 1; min-width: calc(50% - 15px); }
        .chart-section h2 { margin-bottom: 10px; }
    </style>
</head>
<body>
    <h1>Time Tracking Report</h1>
    <p>Generated on: {$currentDateTime}</p>
    <p>Period: {$dateFrom} to {$dateTo}</p>
    {$projectName}
    
    <h2>Daily Time Summary</h2>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Tracked Duration (Hours)</th>
                <th>Break Duration (Hours)</th>
                <th>Break Percentage (%)</th>
            </tr>
        </thead>
        <tbody>
            {$tableRows}
        </tbody>
    </table>

    <div class="charts-container">
        <div class="chart-section">
            <h2>Time Tracked vs Break Time</h2>
            <div class="chart-container">
                <canvas id="timeChart"></canvas>
            </div>
        </div>

        <div class="chart-section">
            <h2>Break Percentage Trend</h2>
            <div class="chart-container">
                <canvas id="breakChart"></canvas>
            </div>
        </div>
    </div>

    <script>
        // Time Tracked vs Break Time Chart
        const timeCtx = document.getElementById("timeChart").getContext("2d");
        new Chart(timeCtx, {
            type: "line",
            data: {
                labels: {$datesJson},
                datasets: [
                    {
                        label: "Tracked Hours",
                        data: {$trackedJson},
                        borderColor: "rgb(54, 162, 235)",
                        backgroundColor: "rgba(54, 162, 235, 0.1)",
                        tension: 0.1
                    },
                    {
                        label: "Break Hours",
                        data: {$breakJson},
                        borderColor: "rgb(255, 99, 132)",
                        backgroundColor: "rgba(255, 99, 132, 0.1)",
                        tension: 0.1
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: "Daily Time Tracked vs Break Time"
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: "Hours"
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: "Date"
                        }
                    }
                }
            }
        });

        // Break Percentage Chart
        const breakCtx = document.getElementById("breakChart").getContext("2d");
        new Chart(breakCtx, {
            type: "line",
            data: {
                labels: {$datesJson},
                datasets: [{
                    label: "Break Percentage (%)",
                    data: {$percentJson},
                    borderColor: "rgb(75, 192, 192)",
                    backgroundColor: "rgba(75, 192, 192, 0.1)",
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: "Daily Break Percentage Trend"
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: "Percentage (%)"
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: "Date"
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
HTML;
        exit;
    } else {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="daily_time_report_' . date('Y-m-d') . '.csv"');

        echo "Date,Tracked Duration (Hours),Break Duration (Hours),Break Percentage (%)\n";

        // Sort dates for consistent output
        $sortedDates = array_keys($sessionsByDate);
        sort($sortedDates);

        foreach ($sortedDates as $date) {
            $trackedSeconds = $sessionsByDate[$date]['duration'];
            $breakSeconds = $breaksByDate[$date] ?? 0;
            $trackedHours = round($trackedSeconds / 3600, 2);
            $breakHours = round($breakSeconds / 3600, 2);
            $breakPercent = $trackedSeconds > 0 ? round(($breakSeconds / $trackedSeconds) * 100, 2) : 0;

            echo sprintf(
                '"%s",%.2f,%.2f,%.2f' . "\n",
                $date,
                $trackedHours,
                $breakHours,
                $breakPercent
            );
        }
        exit;
    }
}
?>
<?php HTMLHelper::renderHeader('Reports', $user); ?>
<body class="<?php echo $user['theme'] === 'dark' ? 'dark-theme' : 'light-theme'; ?>">
    <?php HTMLHelper::renderNavigation('reports', $user); ?>

    <div class="container">
        <div style="margin-bottom: 30px;">
            <h1>Reports & Analytics</h1>
            <p class="text-muted">Track your productivity statistics and export data</p>
        </div>

        <!-- Filters -->
        <div class="card mb-30">
            <div class="card-body">
                <form method="GET" class="form-row">
                    <div class="form-group">
                        <label for="project">Project</label>
                        <select id="project" name="project">
                            <option value="">All Projects</option>
                            <?php
                            $projects = $db->fetchAll("SELECT id, name FROM projects WHERE user_id = ? ORDER BY name", [$userId]);
                            foreach ($projects as $p):
                            ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo $projectId === (int)$p['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="from">From Date</label>
                        <input type="date" id="from" name="from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                    </div>
                    <div class="form-group">
                        <label for="to">To Date</label>
                        <input type="date" id="to" name="to" value="<?php echo htmlspecialchars($dateTo); ?>">
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end;">
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Overview -->
        <div class="row mb-30">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted" style="font-size: 0.9rem; margin-bottom: 8px;">Total Time</div>
                    <div style="font-size: 2rem; font-weight: bold; color: #667eea;">
                        <?php echo formatDuration($totalDuration); ?>
                    </div>
                    <small class="text-muted">
                        <?php echo round($totalDuration / 3600, 1); ?> hours
                    </small>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="text-muted" style="font-size: 0.9rem; margin-bottom: 8px;">Break Time</div>
                    <div style="font-size: 2rem; font-weight: bold; color: #f39c12;">
                        <?php echo formatDuration($breakTotal); ?>
                    </div>
                    <small class="text-muted">
                        <?php echo number_format($breakPercent, 1); ?>% of tracked time
                    </small>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="text-muted" style="font-size: 0.9rem; margin-bottom: 8px;">Sessions</div>
                    <div style="font-size: 2rem; font-weight: bold; color: #42c88a;">
                        <?php echo count($sessions); ?>
                    </div>
                    <small class="text-muted">
                        <?php echo round($totalDuration / max(count($sessions), 1) / 60, 1); ?> min avg
                    </small>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="text-muted" style="font-size: 0.9rem; margin-bottom: 8px;">Days Tracked</div>
                    <div style="font-size: 2rem; font-weight: bold; color: #f39c12;">
                        <?php echo count($sessionsByDate); ?>
                    </div>
                    <small class="text-muted">
                        <?php echo $dateFrom; ?> to <?php echo $dateTo; ?>
                    </small>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div style="display: flex; gap: 10px;">
                        <a href="?export=csv&project=<?php echo $projectId ?? ''; ?>&from=<?php echo $dateFrom; ?>&to=<?php echo $dateTo; ?>" 
                           class="btn btn-secondary" style="flex: 1;">
                            📊 Export CSV
                        </a>
                        <a href="?export=html&project=<?php echo $projectId ?? ''; ?>&from=<?php echo $dateFrom; ?>&to=<?php echo $dateTo; ?>" 
                           class="btn btn-primary" style="flex: 1;">
                            📈 Export with Graphs
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Data Section -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 40px;">
            <!-- By Project -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Time by Project</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($sessionsByProject)): ?>
                        <p class="text-muted" style="text-align: center; padding: 20px 0;">No data</p>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 15px;">
                            <?php foreach ($sessionsByProject as $project => $data): ?>
                                <div>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                        <span><?php echo htmlspecialchars($project); ?></span>
                                        <span style="font-weight: bold; color: #667eea;">
                                            <?php echo formatDuration($data['duration']); ?>
                                        </span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar" style="width: <?php echo ($data['duration'] / $totalDuration * 100); ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- By Date -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Time by Date</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($sessionsByDate)): ?>
                        <p class="text-muted" style="text-align: center; padding: 20px 0;">No data</p>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <?php foreach (array_slice($sessionsByDate, 0, 10) as $date => $data): ?>
                                <div style="display: flex; justify-content: space-between;">
                                    <span><?php echo date('Y-m-d', strtotime($date)); ?></span>
                                    <span style="font-weight: bold; color: #667eea;">
                                        <?php echo formatDuration($data['duration']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Detailed Sessions -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Detailed Sessions</h3>
            </div>
            <div class="card-body">
                <?php if (empty($sessions)): ?>
                    <p class="text-muted" style="text-align: center; padding: 20px 0;">No sessions in this period</p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Date & Time</th>
                                    <th>Duration</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $session): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($session['project_name'] ?? 'Uncategorized'); ?></td>
                                        <td><?php echo formatDateTime($session['start_time']); ?></td>
                                        <td style="font-weight: bold; color: #667eea;">
                                            <?php echo formatDuration($session['duration_seconds']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($session['description'] ?? '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Time Trend Chart -->
        <div class="card" style="margin-top:30px;">
            <div class="card-header">
                <h3 class="card-title">Time Trend (Daily Session & Break Time)</h3>
            </div>
            <div class="card-body">
                <canvas id="timeTrendChart" width="800" height="400"></canvas>
            </div>
        </div>

        <!-- Break % Trend Chart -->
        <div class="card" style="margin-top:30px;">
            <div class="card-header">
                <h3 class="card-title">Break % Trend</h3>
            </div>
            <div class="card-body">
                <canvas id="breakPercentChart" width="800" height="400"></canvas>
                <div style="margin-top:20px; display:flex; flex-wrap:wrap; gap:1rem;">
                    <div><strong>Avg Break %</strong>: <?php echo number_format($avgBreakPercent, 1); ?>%</div>
                    <div><strong>Min Break %</strong>: <?php echo number_format($minBreakPercent, 1); ?>%</div>
                    <div><strong>Max Break %</strong>: <?php echo number_format($maxBreakPercent, 1); ?>%</div>
                    <div><strong>Start Period Break %</strong>: <?php echo number_format($startBreakPercent, 1); ?>%</div>
                    <div><strong>End Period Break %</strong>: <?php echo number_format($endBreakPercent, 1); ?>%</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        (function(){
            // Prepare labels (dates) sorted ascending
            var sessionsByDate = <?php echo json_encode($sessionsByDate); ?>;
            var breaksByDate = <?php echo json_encode($breaksByDate); ?>;

            var dates = Object.keys(sessionsByDate).sort();
            var labels = [];
            var sessionMinutes = [];
            var breakMinutes = [];
            var breakPercents = [];

            dates.forEach(function(d){
                var sessionDur = sessionsByDate[d].duration || 0;
                var breakSec = breaksByDate[d] || 0;
                var sessionMin = sessionDur / 60;
                var breakMin = breakSec / 60;
                var pct = sessionDur > 0 ? (breakSec / sessionDur * 100) : 0;

                labels.push(d);
                sessionMinutes.push(Math.round(sessionMin));
                breakMinutes.push(Math.round(breakMin));
                breakPercents.push(Math.round(pct * 10) / 10);
            });

            // helper: convert minutes to H:MM label
            function minsToTimeLabel(mins) {
                var sign = mins < 0 ? '-' : '';
                mins = Math.abs(mins);
                var h = Math.floor(mins / 60);
                var m = Math.round(mins % 60);
                if (m === 60) {
                    h += 1; m = 0;
                }
                return sign + h + ':' + (m < 10 ? '0' : '') + m;
            }

            var timeCtx = document.getElementById('timeTrendChart').getContext('2d');
            new Chart(timeCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Session Time',
                            data: sessionMinutes,
                            fill: false,
                            borderColor: '#667eea',
                            backgroundColor: '#667eea',
                            tension: 0.2,
                            pointRadius: 4
                        },
                        {
                            label: 'Break Time',
                            data: breakMinutes,
                            fill: false,
                            borderColor: '#f39c12',
                            backgroundColor: '#f39c12',
                            tension: 0.2,
                            pointRadius: 3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            title: { display: true, text: 'Time (H:MM)' },
                            ticks: {
                                callback: function(value) { return minsToTimeLabel(value); }
                            }
                        },
                        x: {
                            title: { display: true, text: 'Date' }
                        }
                    },
                    plugins: {
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + minsToTimeLabel(context.parsed.y);
                                }
                            }
                        },
                        legend: { position: 'top' }
                    }
                }
            });

            var percentCtx = document.getElementById('breakPercentChart').getContext('2d');
            new Chart(percentCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Break %',
                            data: breakPercents,
                            fill: false,
                            borderColor: '#42c88a',
                            backgroundColor: '#42c88a',
                            tension: 0.2,
                            pointRadius: 3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            title: { display: true, text: '% of Total Time' },
                            min: 0,
                            max: 100
                        },
                        x: {
                            title: { display: true, text: 'Date' }
                        }
                    },
                    plugins: {
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.y + '%';
                                }
                            }
                        },
                        legend: { position: 'top' }
                    }
                }
            });
        })();
    </script>

    <?php HTMLHelper::renderFooter(); ?>
</body>
</html>
