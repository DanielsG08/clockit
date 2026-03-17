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
<?php HTMLHelper::renderHeader('Stopwatch', $user); ?>
<body class="<?php echo $user['theme'] === 'dark' ? 'dark-theme' : 'light-theme'; ?>">
    <?php HTMLHelper::renderNavigation('stopwatch', $user); ?>

    <div class="container-narrow">
        <div style="margin-bottom: 30px;">
            <h1>⏱️ Time Tracker</h1>
            <p class="text-muted">Track your time with precision</p>
        </div>

        <div class="card">
            <!-- Display -->
            <div style="background: var(--bg-secondary); padding: 40px 20px; border-radius: 8px; margin-bottom: 30px; text-align: center;">
                <div id="display" style="font-size: 4rem; font-family: 'Courier New', monospace; font-weight: bold; color: #667eea; letter-spacing: 5px;">
                    00:00:00
                </div>
                <div id="breakDisplay" style="font-size: 1.2rem; color: var(--text-secondary); margin-top: 10px; display: none;">
                    Break: <span id="breakTime">00:00</span>
                </div>
            </div>

            <!-- Project Selection -->
            <div class="form-group mb-30">
                <label for="project">Project</label>
                <select id="project" name="project" class="form-control">
                    <option value="">-- Select Project --</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $projectId === (int)$p['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Description -->
            <div class="form-group mb-30">
                <label for="description">What are you working on?</label>
                <input type="text" id="description" placeholder="e.g., Design homepage, Write documentation" class="form-control">
            </div>

            <!-- Controls -->
            <div style="display: flex; gap: 10px; margin-bottom: 30px; flex-wrap: wrap;">
                <button id="startBtn" class="btn btn-primary btn-lg">▶ Start</button>
                <button id="pauseBtn" class="btn btn-warning btn-lg" style="background: #f39c12;" disabled>⏸ Pause</button>
                <button id="stopBtn" class="btn btn-danger btn-lg" disabled>⏹ Stop</button>
                <button id="resetBtn" class="btn btn-secondary btn-lg">↻ Reset</button>
            </div>

            <!-- Break Controls -->
            <div style="padding: 15px; background: var(--bg-secondary); border-radius: 5px; margin-bottom: 20px;">
                <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                    <button id="breakBtn" class="btn btn-info btn-sm" disabled>☕ Take Break</button>
                    <button id="resumeBtn" class="btn btn-info btn-sm" style="background: #27ae60;" disabled>🚀 Resume</button>
                </div>
                <small class="text-muted">Use breaks to track non-productive time</small>
            </div>

            <!-- Recent Sessions -->
            <div style="border-top: 2px solid var(--border-color); padding-top: 20px;">
                <h3 style="margin: 0 0 15px 0;">Today's Sessions</h3>
                <div id="sessionsList" style="display: flex; flex-direction: column; gap: 10px;">
                    <p class="text-muted" style="text-align: center;">No sessions yet</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        class Stopwatch {
            constructor() {
                this.elapsed = 0;
                this.elapsedMs = 0;
                this.breakTime = 0;
                this.startTime = null;
                this.breakStartTime = null;
                this.isRunning = false;
                this.isPaused = false;
                this.onBreak = false;
                this.sessionId = null;
                this.animationId = null;

                this.elements = {
                    display: document.getElementById('display'),
                    breakDisplay: document.getElementById('breakDisplay'),
                    breakTime: document.getElementById('breakTime'),
                    description: document.getElementById('description'),
                    project: document.getElementById('project'),
                    startBtn: document.getElementById('startBtn'),
                    pauseBtn: document.getElementById('pauseBtn'),
                    stopBtn: document.getElementById('stopBtn'),
                    resetBtn: document.getElementById('resetBtn'),
                    breakBtn: document.getElementById('breakBtn'),
                    resumeBtn: document.getElementById('resumeBtn')
                };

                this.attachListeners();
            }

            attachListeners() {
                this.elements.startBtn.addEventListener('click', () => this.start());
                this.elements.pauseBtn.addEventListener('click', () => this.pause());
                this.elements.stopBtn.addEventListener('click', () => this.stop());
                this.elements.resetBtn.addEventListener('click', () => this.reset());
                this.elements.breakBtn.addEventListener('click', () => this.takeBreak());
                this.elements.resumeBtn.addEventListener('click', () => this.onBreak ? this.resumeWork() : this.resumeFromPause());
            }

            start() {
                if (!this.elements.project.value) {
                    alert('Please select a project');
                    return;
                }

                this.isRunning = true;
                this.isPaused = false;
                this.onBreak = false;
                this.startTime = Date.now() - this.elapsedMs;
                this.elements.startBtn.textContent = '▶ Start';
                this.updateButtons();
                this.tick();
            }

            pause() {
                if (!this.isRunning) return;

                this.elapsedMs = Date.now() - this.startTime;
                this.elapsed = Math.floor(this.elapsedMs / 1000);
                this.elements.display.textContent = this.formatTime(this.elapsed);

                this.isRunning = false;
                this.isPaused = true;
                this.elements.startBtn.textContent = '▶ Resume';
                this.updateButtons();
            }

            resumeFromPause() {
                if (!this.isPaused) return;

                this.isRunning = true;
                this.isPaused = false;
                this.elements.startBtn.textContent = '▶ Start';
                this.startTime = Date.now() - this.elapsedMs;
                this.updateButtons();
                this.tick();
            }

            stop() {
                if (this.elapsed === 0) return;

                this.isRunning = false;
                this.isPaused = false;
                this.elements.startBtn.textContent = '▶ Start';
                this.saveSession();
                this.reset();
            }

            reset() {
                this.elapsed = 0;
                this.elapsedMs = 0;
                this.breakTime = 0;
                this.sessionId = null;
                this.onBreak = false;
                this.isPaused = false;
                cancelAnimationFrame(this.animationId);
                this.elements.display.textContent = '00:00:00';
                this.elements.breakDisplay.style.display = 'none';
                this.elements.description.value = '';
                this.elements.startBtn.textContent = '▶ Start';
                this.updateButtons();
            }

            takeBreak() {
                if (!this.isRunning) return;

                this.onBreak = true;
                this.breakStartTime = Date.now();
                this.elements.breakDisplay.style.display = 'block';
                this.updateButtons();
            }

            resumeWork() {
                if (!this.onBreak) return;

                if (this.breakStartTime) {
                    this.breakTime += Date.now() - this.breakStartTime;
                }
                this.onBreak = false;
                this.breakStartTime = null;
                this.elements.breakDisplay.style.display = 'none';
                this.isRunning = true;
                this.updateButtons();
                this.tick();
            }

            tick() {
                if (!this.isRunning) return;

                if (this.onBreak) {
                    const breakDuration = Math.floor((Date.now() - this.breakStartTime) / 1000);
                    this.elements.breakTime.textContent = this.formatTime(breakDuration);
                } else {
                    this.elapsedMs = Date.now() - this.startTime;
                    this.elapsed = Math.floor(this.elapsedMs / 1000);
                    this.elements.display.textContent = this.formatTime(this.elapsed);
                }

                this.animationId = requestAnimationFrame(() => this.tick());
            }

            updateButtons() {
                const hasTime = this.elapsed > 0;
                const isRunning = this.isRunning;

                this.elements.startBtn.disabled = isRunning || this.onBreak;
                this.elements.pauseBtn.disabled = !isRunning;
                this.elements.stopBtn.disabled = !hasTime;
                this.elements.resetBtn.disabled = !hasTime && !isRunning;
                this.elements.breakBtn.disabled = !isRunning || this.onBreak;
                this.elements.resumeBtn.disabled = !(this.onBreak || this.isPaused);
                this.elements.project.disabled = isRunning;
            }

            formatTime(seconds) {
                const h = Math.floor(seconds / 3600);
                const m = Math.floor((seconds % 3600) / 60);
                const s = seconds % 60;
                return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
            }

            async saveSession() {
                if (this.elapsed === 0) return;

                const data = {
                    project_id: this.elements.project.value || null,
                    description: this.elements.description.value,
                    duration_seconds: this.elapsed,
                    break_seconds: Math.floor((this.breakTime || 0) / 1000)
                };

                try {
                    const response = await API.post(`${APP_BASE_URL}/api/sessions/save.php`, data);
                    if (response.success) {
                        Notification.success('Session saved!');
                        this.loadTodaySessions();
                    }
                } catch (e) {
                    Notification.error('Failed to save session: ' + e.message);
                }
            }

            async loadTodaySessions() {
                try {
                    const response = await API.get(`${APP_BASE_URL}/api/sessions/today.php`);
                    if (response.success && response.data) {
                        const list = document.getElementById('sessionsList');
                        list.innerHTML = response.data.map(session => `
                            <div style="padding: 12px; background: var(--bg-secondary); border-radius: 5px; display: flex; justify-content: space-between;">
                                <div>
                                    <strong>${session.project_name || 'Untitled'}</strong><br>
                                    <small class="text-muted">${session.description || 'No description'}</small>
                                </div>
                                <div style="text-align: right; font-weight: bold; color: #667eea;">
                                    ${this.formatTime(session.duration_seconds)}
                                </div>
                            </div>
                        `).join('');
                    }
                } catch (e) {
                    console.error('Failed to load sessions:', e);
                }
            }
        }

        // Initialize
        const stopwatch = new Stopwatch();
        stopwatch.loadTodaySessions();
    </script>

    <?php HTMLHelper::renderFooter(); ?>
</body>
</html>