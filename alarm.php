<?php
require_once 'config/init.php';

requireAuth();

$user = getCurrentUser();
$userId = $_SESSION['user_id'];
$db = Database::getInstance();

$selectedProjectId = isset($_GET['project']) ? (int)$_GET['project'] : null;
$projectId = $selectedProjectId;

// Get user's projects
$projects = $db->fetchAll(
    "SELECT id, name, color FROM projects WHERE user_id = ? AND is_active = 1 ORDER BY name",
    [$userId]
);

// Get current session if exists
$currentSession = $db->fetch(
    "SELECT id FROM time_sessions WHERE user_id = ? AND end_time IS NULL ORDER BY start_time DESC LIMIT 1",
    [$userId]
);
?>
<?php HTMLHelper::renderHeader('Alarm', $user); ?>
<body class="<?php echo $user['theme'] === 'dark' ? 'dark-theme' : 'light-theme'; ?>">
    <?php HTMLHelper::renderNavigation('alarm', $user); ?>

    <div class="container-narrow">
        <div style="margin-bottom: 30px;">
            <h1>⏰ Alarm Clock</h1>
            <p class="text-muted">Set reminders for your tasks</p>
        </div>

        <div class="card">
            <div style="background: var(--bg-secondary); padding: 40px 20px; border-radius: 8px; margin-bottom: 30px; text-align: center;">
                <div id="currentTime" style="font-size: 3.5rem; font-family: 'Courier New', monospace; font-weight: bold; color: #667eea; letter-spacing: 2px;">
                    00:00:00 AM
                </div>
                <div id="alarmStatus" style="font-size: 1.2rem; color: var(--text-secondary); margin-top: 10px; height: 1.5rem;">
                    </div>
            </div>

            <div class="form-group mb-30">
                <label for="alarmTime">Set Alarm Time</label>
                <input type="time" id="alarmTime" class="form-control" style="max-width: 220px; margin: 0 auto; display: block; text-align: center; font-size: 1.2rem;">
            </div>

            <div style="display: flex; gap: 10px; margin-bottom: 10px; justify-content: center; flex-wrap: wrap;">
                <button id="setAlarm" class="btn btn-primary btn-lg">Set Alarm</button>
                <button id="clearAlarm" class="btn btn-secondary btn-lg" disabled>✕ Clear</button>
            </div>
        </div>
    </div>

    <audio id="alarmSound" src="https://actions.google.com/sounds/v1/alarms/alarm_clock.ogg" preload="auto"></audio>

    <script>
        const currentTimeEl = document.getElementById('currentTime');
        const alarmTimeInput = document.getElementById('alarmTime');
        const setAlarmBtn = document.getElementById('setAlarm');
        const clearAlarmBtn = document.getElementById('clearAlarm');
        const alarmSound = document.getElementById('alarmSound');
        const alarmStatus = document.getElementById('alarmStatus');

        let alarmTime = null;

        function updateCurrentTime() {
            const now = new Date();
            
            // Format for display: HH:MM:SS AM/PM
            const timeString = now.toLocaleTimeString([], { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit', 
                hour12: true 
            });
            
            currentTimeEl.textContent = timeString;

            if (alarmTime) {
                checkAlarm(now);
            }
        }

        function checkAlarm(now) {
            // Time
            const current24h = now.getHours().toString().padStart(2, '0') + ":" + 
                             now.getMinutes().toString().padStart(2, '0');
            
            if (current24h === alarmTime) {
                alarmSound.play().catch(err => console.log('Could not play sound:', err));
                alert('Alarm!');
                clearAlarm();
            }
        }

        function formatTo12h(time24) {
            if (!time24) return '';
            let [hours, minutes] = time24.split(':');
            let ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12 || 12;
            return `${hours}:${minutes} ${ampm}`;
        }

        function setAlarm() {
            const val = alarmTimeInput.value;
            if (!val) {
                alert('Please select a time');
                return;
            }
            
            alarmTime = val;
            setAlarmBtn.disabled = true;
            clearAlarmBtn.disabled = false;
            alarmStatus.textContent = `Alarm set for ${formatTo12h(val)}`;
            alarmStatus.style.color = "#667eea";
        }

        function clearAlarm() {
            alarmTime = null;
            setAlarmBtn.disabled = false;
            clearAlarmBtn.disabled = true;
            alarmStatus.textContent = '';
        }

        setInterval(updateCurrentTime, 1000);
        setAlarmBtn.addEventListener('click', setAlarm);
        clearAlarmBtn.addEventListener('click', clearAlarm);
        
        updateCurrentTime();
    </script>

    <?php HTMLHelper::renderFooter(); ?>
</body>
</html>