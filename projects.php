<?php
require_once 'config/init.php';

requireAuth();

$user = getCurrentUser();
$userId = $_SESSION['user_id'];
$db = Database::getInstance();

$success = '';
$error = '';

// Handle project deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!SecurityHelper::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token invalid.';
    } elseif ($_POST['action'] === 'delete' && isset($_POST['project_id'])) {
        $projectId = (int)$_POST['project_id'];
        $project = $db->fetch("SELECT id FROM projects WHERE id = ? AND user_id = ?", [$projectId, $userId]);
        
        if ($project) {
            $db->delete('projects', 'id = ?', [$projectId]);
            ActivityLogger::log($userId, 'DELETE_PROJECT', 'project', $projectId);
            $success = 'Project deleted successfully.';
        }
    } elseif ($_POST['action'] === 'create' && isset($_POST['name'])) {
        $name = SecurityHelper::sanitize($_POST['name']);
        $description = SecurityHelper::sanitize($_POST['description'] ?? '');
        $color = preg_match('/^#[0-9A-F]{6}$/i', $_POST['color'] ?? '') ? $_POST['color'] : '#667eea';
        
        if (empty($name)) {
            $error = 'Project name is required.';
        } else {
            $projectId = $db->insert('projects', [
                'user_id' => $userId,
                'name' => $name,
                'description' => $description,
                'color' => $color,
                'is_active' => 1
            ]);
            ActivityLogger::log($userId, 'CREATE_PROJECT', 'project', $projectId);
            $success = 'Project created successfully.';
        }
    }
}

$projects = $db->fetchAll(
    "SELECT p.*, COUNT(ts.id) as session_count, SUM(ts.duration_seconds) as total_time 
     FROM projects p
     LEFT JOIN time_sessions ts ON p.id = ts.project_id
     WHERE p.user_id = ?
     GROUP BY p.id
     ORDER BY p.created_at DESC",
    [$userId]
);
?>
<?php HTMLHelper::renderHeader('Projects', $user); ?>
<body class="<?php echo $user['theme'] === 'dark' ? 'dark-theme' : 'light-theme'; ?>">
    <?php HTMLHelper::renderNavigation('projects', $user); ?>

    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <div>
                <h1>Projects</h1>
                <p class="text-muted">Organize and track your work</p>
            </div>
            <button class="btn btn-primary" onclick="showCreateModal()">+ New Project</button>
        </div>

        <?php if ($success): ?>
            <?php HTMLHelper::renderAlert($success, 'success'); ?>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <?php HTMLHelper::renderAlert($error, 'error'); ?>
        <?php endif; ?>

        <?php if (empty($projects)): ?>
            <div class="card" style="text-align: center; padding: 60px 20px;">
                <h2>No projects yet</h2>
                <p class="text-muted">Create your first project to organize your time tracking</p>
                <button class="btn btn-primary" onclick="showCreateModal()" style="margin-top: 20px;">Create Project</button>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($projects as $project): ?>
                    <div class="card">
                        <div class="card-header">
                            <div style="display: flex; align-items: center; gap: 10px; flex: 1;">
                                <div style="width: 16px; height: 16px; border-radius: 50%; background: <?php echo htmlspecialchars($project['color']); ?>;"></div>
                                <h3 class="card-title" style="margin: 0;">
                                    <?php echo htmlspecialchars($project['name']); ?>
                                </h3>
                            </div>
                            <form method="POST" style="margin: 0;">
                                <?php HTMLHelper::renderCSRFField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this project?')">Delete</button>
                            </form>
                        </div>
                        <div class="card-body">
                            <?php if ($project['description']): ?>
                                <p><?php echo htmlspecialchars($project['description']); ?></p>
                            <?php endif; ?>
                            
                            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 20px;">
                                <div>
                                    <div class="text-muted" style="font-size: 0.85rem; margin-bottom: 5px;">Total Time</div>
                                    <div style="font-size: 1.5rem; font-weight: bold; color: #667eea;">
                                        <?php echo formatDuration($project['total_time'] ?? 0); ?>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-muted" style="font-size: 0.85rem; margin-bottom: 5px;">Sessions</div>
                                    <div style="font-size: 1.5rem; font-weight: bold; color: #42c88a;">
                                        <?php echo $project['session_count'] ?? 0; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <a href="stopwatch.php?project=<?php echo $project['id']; ?>" class="btn btn-primary btn-sm">
                                ⏱️ Track Time
                            </a>
                            <a href="reports.php?project=<?php echo $project['id']; ?>" class="btn btn-secondary btn-sm">
                                📊 View Report
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Create Project Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeCreateModal()">×</button>
            <h2>Create New Project</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="name">Project Name</label>
                    <input type="text" id="name" name="name" placeholder="My Project" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Project description..."></textarea>
                </div>
                <div class="form-group">
                    <label for="color">Color</label>
                    <input type="color" id="color" name="color" value="#667eea">
                </div>
                <div class="btn-group">
                    <button type="submit" name="action" value="create" class="btn btn-primary">Create</button>
                    <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">Cancel</button>
                </div>
                <?php HTMLHelper::renderCSRFField(); ?>
            </form>
        </div>
    </div>

    <script>
        function showCreateModal() {
            document.getElementById('createModal').classList.add('active');
        }
        function closeCreateModal() {
            document.getElementById('createModal').classList.remove('active');
        }
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('createModal');
            if (e.target === modal) modal.classList.remove('active');
        });
    </script>

    <?php HTMLHelper::renderFooter(); ?>
</body>
</html>
