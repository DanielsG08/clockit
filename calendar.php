<?php
require_once 'config/init.php';

requireAuth();

$user = getCurrentUser();
$userId = $_SESSION['user_id'];
$db = Database::getInstance();

$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Validate month/year
if ($month < 1 || $month > 12) $month = date('n');
if ($year < 2020 || $year > 2100) $year = date('Y');

// Get events for the month
$monthStart = date('Y-m-01', strtotime("$year-$month-01"));
$monthEnd = date('Y-m-t', strtotime("$year-$month-01"));

// Load user calendars (for filtering and management)
$calendarId = isset($_GET['calendar_id']) ? (int)$_GET['calendar_id'] : 0;
$calendars = $db->fetchAll(
    "SELECT id, name, color FROM calendars WHERE user_id = ? ORDER BY name ASC",
    [$userId]
) ?? [];

// Ensure at least one calendar exists
if (empty($calendars)) {
    $defaultCalendarId = $db->insert('calendars', [
        'user_id' => $userId,
        'name' => 'Default',
        'color' => '#667eea'
    ]);
    $calendars = [[
        'id' => $defaultCalendarId,
        'name' => 'Default',
        'color' => '#667eea'
    ]];
}

// Keep the current selection valid
$validIds = array_column($calendars, 'id');
if ($calendarId && !in_array($calendarId, $validIds)) {
    $calendarId = 0;
}

$selectedCalendar = null;
foreach ($calendars as $cal) {
    if ($cal['id'] === $calendarId) {
        $selectedCalendar = $cal;
        break;
    }
}

$events = [];
try {
    $sql = "SELECT id, title, description, event_date, event_time, color, calendar_id
         FROM calendar_events
         WHERE user_id = ?";
    $params = [$userId];

    if ($calendarId > 0) {
        $sql .= " AND calendar_id = ?";
        $params[] = $calendarId;
    }

    $sql .= " AND event_date BETWEEN ? AND ? ORDER BY event_date ASC, event_time ASC";
    $params[] = $monthStart;
    $params[] = $monthEnd;

    $events = $db->fetchAll($sql, $params) ?? [];
} catch (Exception $e) {
    // Table might not exist yet, continue without events
}

// Build calendar arrays for events
$eventsByDate = [];
foreach ($events as $event) {
    if (!isset($eventsByDate[$event['event_date']])) {
        $eventsByDate[$event['event_date']] = [];
    }
    $eventsByDate[$event['event_date']][] = $event;
}

// Calculate calendar grid
$firstDay = strtotime("$year-$month-01");
$lastDay = strtotime("$year-$month-" . date('d', strtotime("$year-$month-01 +1 month -1 day")));
$daysInMonth = (int)date('d', $lastDay);
$startingWeek = (int)date('w', $firstDay);

$csrfToken = SecurityHelper::generateCSRFToken();
?>
<?php HTMLHelper::renderHeader('Calendar', $user); ?>
<body class="<?php echo $user['theme'] === 'dark' ? 'dark-theme' : 'light-theme'; ?>">
    <?php HTMLHelper::renderNavigation('calendar', $user); ?>

    <div class="container">
        <div style="margin-bottom: 30px;">
            <h1>📅 Calendar</h1>
            <p class="text-muted">View your time tracking and add events</p>
        </div>

        <!-- Month Navigation -->
        <div class="card mb-30">
            <div class="card-body" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <?php
                    $baseParams = ['month' => $month, 'year' => $year];
                    if ($calendarId > 0) {
                        $baseParams['calendar_id'] = $calendarId;
                    }

                    $prevParams = $baseParams;
                    $prevParams['month'] = $month === 1 ? 12 : $month - 1;
                    $prevParams['year'] = $month === 1 ? $year - 1 : $year;

                    $nextParams = $baseParams;
                    $nextParams['month'] = $month === 12 ? 1 : $month + 1;
                    $nextParams['year'] = $month === 12 ? $year + 1 : $year;
                ?>

                <a href="?<?php echo http_build_query($prevParams); ?>" class="btn btn-secondary btn-sm">
                    ← Previous
                </a>

                <div style="text-align: center; flex: 1; min-width: 220px;">
                    <h3 style="margin: 0; font-size: 1.5rem;">
                        <?php echo date('F Y', strtotime("$year-$month-01")); ?>
                    </h3>
                    <div style="font-size: 0.9rem; color: var(--text-secondary);">
                        Showing: <strong><?php echo $selectedCalendar ? htmlspecialchars($selectedCalendar['name']) : 'All Calendars'; ?></strong>
                    </div>
                </div>

                <div style="display: flex; gap: 8px; align-items: center;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="shareCalendar()">Share</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="openCalendarModal()">Calendars</button>
                    <a href="?<?php echo http_build_query($nextParams); ?>" class="btn btn-secondary btn-sm">
                        Next →
                    </a>
                </div>
            </div>
        </div>

        <!-- Calendar Grid -->
        <div class="card">
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: var(--border-color);">
                    <!-- Day headers -->
                    <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day): ?>
                        <div style="padding: 12px; background: var(--bg-secondary); font-weight: bold; text-align: center;">
                            <?php echo $day; ?>
                        </div>
                    <?php endforeach; ?>

                    <!-- Empty cells before month starts -->
                    <?php for ($i = 0; $i < $startingWeek; $i++): ?>
                        <div style="padding: 12px; background: rgba(0,0,0,0.02); aspect-ratio: 1; min-height: 0;"></div>
                    <?php endfor; ?>

                    <!-- Days of month -->
                    <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                        <?php
                            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                            $dayEvents = $eventsByDate[$date] ?? [];
                            $isToday = $date === date('Y-m-d');
                        ?>
                        <div style="padding: 12px; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 5px; cursor: pointer; aspect-ratio: 1; min-height: 0; display: flex; flex-direction: column; justify-content: space-between; overflow: hidden;
                                    <?php echo $isToday ? 'border: 2px solid #667eea;' : ''; ?>"
                             onclick="openEventModal('<?php echo $date; ?>')"
                        >
                            <div style="font-weight: bold; margin-bottom: 8px; color: <?php echo $isToday ? '#667eea' : 'var(--text-primary)'; ?>">
                                <?php echo $day; ?>
                            </div>

                            <?php if (!empty($dayEvents)): ?>
                                <div style="font-size: 0.7rem; max-height: 90px; overflow-y: auto;">
                                    <?php foreach (array_slice($dayEvents, 0, 4) as $event): ?>
                                        <div style="padding: 4px 6px; margin: 3px 0; background: <?php echo htmlspecialchars($event['color']); ?>20; border-left: 3px solid <?php echo htmlspecialchars($event['color']); ?>; border-radius: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
                                             title="<?php echo htmlspecialchars($event['title']); ?> - <?php echo $event['event_time'] ?? ''; ?>">
                                            <?php echo htmlspecialchars($event['title']); ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($dayEvents) > 4): ?>
                                        <div style="color: #999; font-size: 0.65rem; padding: 2px 4px;">+<?php echo count($dayEvents) - 4; ?> more</div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>

                    <!-- Empty cells after month ends -->
                    <?php
                        $remainingCells = (7 * ceil(($startingWeek + $daysInMonth) / 7)) - ($startingWeek + $daysInMonth);
                        for ($i = 0; $i < $remainingCells; $i++):
                    ?>
                        <div style="padding: 12px; background: rgba(0,0,0,0.02); aspect-ratio: 1; min-height: 0;"></div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row" style="margin-top: 30px;">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted" style="font-size: 0.9rem; margin-bottom: 8px;">Events This Month</div>
                    <div style="font-size: 2rem; font-weight: bold; color: #f39c12;">
                        <?php echo count($events); ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="text-muted" style="font-size: 0.9rem; margin-bottom: 8px;">Days with Events</div>
                    <div style="font-size: 2rem; font-weight: bold; color: #667eea;">
                        <?php echo count($eventsByDate); ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="text-muted" style="font-size: 0.9rem; margin-bottom: 8px;">Total Event Colors</div>
                    <div style="font-size: 2rem; font-weight: bold; color: #42c88a;">
                        <?php echo count(array_unique(array_column($events, 'color'))); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Event Modal -->
    <div id="eventModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: var(--bg-primary); padding: 30px; border-radius: 10px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
            <h2 style="margin-top: 0;">Add Event</h2>
            <p style="color: var(--text-secondary);">Selected date: <strong id="selectedDate"></strong></p>

            <form id="eventForm" style="display: flex; flex-direction: column; gap: 15px;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="id" id="eventId">
                <input type="hidden" name="date" id="eventDate">
                <input type="hidden" name="calendar_id" id="eventCalendarId" value="<?php echo (int)$calendarId; ?>">

                <div>
                    <label for="eventTitle" style="display: block; margin-bottom: 5px; font-weight: bold;">Event Title *</label>
                    <input type="text" id="eventTitle" name="title" placeholder="e.g., Meeting, Deadline" maxlength="100" required style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 5px; background: var(--bg-secondary);">
                </div>

                <div>
                    <label for="eventTime" style="display: block; margin-bottom: 5px; font-weight: bold;">Time (Optional)</label>
                    <input type="time" id="eventTime" name="time" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 5px; background: var(--bg-secondary);">
                </div>

                <div>
                    <label for="eventDescription" style="display: block; margin-bottom: 5px; font-weight: bold;">Description (Optional)</label>
                    <textarea id="eventDescription" name="description" placeholder="Add details..." maxlength="500" rows="3" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 5px; background: var(--bg-secondary); font-family: Arial, sans-serif; resize: vertical;"></textarea>
                </div>

                <div>
                    <label for="eventColor" style="display: block; margin-bottom: 5px; font-weight: bold;">Color</label>
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;">
                        <?php $colors = ['#667eea', '#42c88a', '#f39c12', '#e74c3c', '#3498db', '#9b59b6', '#1abc9c', '#34495e']; ?>
                        <?php foreach ($colors as $color): ?>
                            <label style="cursor: pointer; display: flex; align-items: center;">
                                <input type="radio" name="color" value="<?php echo $color; ?>" style="display: none;" onchange="updateColorPreview()">
                                <div style="width: 30px; height: 30px; background: <?php echo $color; ?>; border-radius: 5px; border: 2px solid transparent; margin-right: 5px;" class="color-option"></div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Save Event</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEventModal()" style="flex: 1;">Cancel</button>
                </div>
                <button type="button" id="deleteBtn" class="btn btn-danger" style="display: none;">Delete Event</button>
            </form>

            <div id="eventsList" style="margin-top: 20px; max-height: 300px; overflow-y: auto;">
                <!-- Events for this date will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Calendar management modal -->
    <div id="calendarModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: var(--bg-primary); padding: 30px; border-radius: 10px; max-width: 520px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
            <h2 style="margin-top: 0;">Manage Calendars</h2>
            <p style="color: var(--text-secondary); margin-bottom: 10px;">Select a calendar to view, or add a new one.</p>

            <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 20px;">
                <button type="button" class="btn btn-primary" onclick="showCalendarForm()">+ Add Calendar</button>
                <button type="button" class="btn btn-secondary" onclick="closeCalendarModal()">Close</button>
            </div>

            <div id="calendarForm" style="display: none; margin-bottom: 20px;">
                <div style="margin-bottom: 12px;">
                    <label for="calendarName" style="display: block; margin-bottom: 5px; font-weight: bold;">Name</label>
                    <input type="text" id="calendarName" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 5px; background: var(--bg-secondary);">
                </div>
                <div style="margin-bottom: 12px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Color</label>
                    <input type="color" id="calendarColor" value="#667eea" style="width: 100%; height: 40px; border: 1px solid var(--border-color); border-radius: 5px; padding: 0;">
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-primary" onclick="saveCalendar()">Save</button>
                    <button type="button" class="btn btn-secondary" onclick="hideCalendarForm()">Cancel</button>
                </div>
                <input type="hidden" id="calendarId" value="">
            </div>

            <div id="calendarList"></div>
        </div>
    </div>

    <script>
        const currentCalendarId = <?php echo (int)$calendarId; ?>;
        const currentMonth = <?php echo $month; ?>;
        const currentYear = <?php echo $year; ?>;
        let calendarListData = <?php echo json_encode($calendars); ?>;

        let currentSelectedDate = null;

        function openEventModal(date) {
            currentSelectedDate = date;
            document.getElementById('eventDate').value = date;
            document.getElementById('selectedDate').textContent = new Date(date + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            document.getElementById('eventModal').style.display = 'flex';
            document.getElementById('eventForm').reset();
            document.getElementById('eventId').value = '';
            document.getElementById('deleteBtn').style.display = 'none';
            document.getElementById('eventCalendarId').value = currentCalendarId;

            // Set default color
            document.querySelector('input[name="color"][value="#667eea"]').checked = true;
            
            loadEventsForDate(date);
        }

        function closeEventModal() {
            document.getElementById('eventModal').style.display = 'none';
            document.getElementById('eventsList').innerHTML = '';
        }

        function loadEventsForDate(date) {
            let url = `/clockit/api/events/get.php?from=${date}&to=${date}`;
            if (currentCalendarId && currentCalendarId > 0) {
                url += `&calendar_id=${currentCalendarId}`;
            }

            fetch(url)
                .then(r => r.json())
                .then(data => {
                    let html = '<h4 style="margin-top: 0;">Events on this day:</h4>';
                    if (data.data && data.data.length > 0) {
                        html += '<div style="display: flex; flex-direction: column; gap: 10px;">';
                        data.data.forEach(event => {
                            html += `
                                <div onclick="editEvent(${event.id}, '${event.title.replace(/'/g, "\\'")}', '${(event.description || '').replace(/'/g, "\\'")}', '${event.event_time || ''}', '${event.color}')" 
                                     style="padding: 10px; background: ${event.color}20; border-left: 3px solid ${event.color}; border-radius: 3px; cursor: pointer; transition: all 0.2s;">
                                    <div style="font-weight: bold;">${event.title}</div>
                                    ${event.event_time ? '<div style="font-size: 0.8rem; color: var(--text-secondary);">🕐 ' + event.event_time + '</div>' : ''}
                                    ${event.description ? '<div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 3px;">' + event.description + '</div>' : ''}
                                </div>
                            `;
                        });
                        html += '</div>';
                    } else {
                        html += '<p style="color: var(--text-secondary);">No events scheduled for this day.</p>';
                    }
                    document.getElementById('eventsList').innerHTML = html;
                });
        }

        function editEvent(id, title, description, time, color) {
            document.getElementById('eventId').value = id;
            document.getElementById('eventTitle').value = title;
            document.getElementById('eventDescription').value = description;
            document.getElementById('eventTime').value = time;
            document.getElementById('eventCalendarId').value = currentCalendarId;
            document.querySelector(`input[name="color"][value="${color}"]`).checked = true;
            document.getElementById('deleteBtn').style.display = 'block';
            document.querySelector('#eventForm button[type="submit"]').textContent = 'Update Event';
        }

        function updateColorPreview() {
            // Optional: add visual preview of selected color
        }

        document.getElementById('eventForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            
            try {
                const response = await fetch('/clockit/api/events/save.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    Notification.show('Event saved successfully!', 'success');
                    document.getElementById('eventForm').reset();
                    document.getElementById('eventId').value = '';
                    document.getElementById('deleteBtn').style.display = 'none';
                    document.querySelector('#eventForm button[type="submit"]').textContent = 'Save Event';
                    
                    // Reload events and close modal
                    loadEventsForDate(currentSelectedDate);
                    setTimeout(() => closeEventModal(), 800);
                } else {
                    Notification.show(data.message || 'Failed to save event', 'error');
                }
            } catch (err) {
                Notification.show('Error: ' + err.message, 'error');
            }
        });

        document.getElementById('deleteBtn').addEventListener('click', async () => {
            if (!confirm('Delete this event?')) return;

            const eventId = document.getElementById('eventId').value;
            const formData = new FormData();
            formData.append('id', eventId);
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

            try {
                const response = await fetch('/clockit/api/events/delete.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    Notification.show('Event deleted successfully!', 'success');
                    loadEventsForDate(currentSelectedDate);
                    setTimeout(() => closeEventModal(), 800);
                } else {
                    Notification.show(data.message || 'Failed to delete event', 'error');
                }
            } catch (err) {
                Notification.show('Error: ' + err.message, 'error');
            }
        });

        // Close modal when clicking outside
        document.getElementById('eventModal').addEventListener('click', (e) => {
            if (e.target.id === 'eventModal') closeEventModal();
        });

        function shareCalendar() {
            const url = window.location.href;
            Utils.copyToClipboard(url);
        }

        function openCalendarModal() {
            document.getElementById('calendarModal').style.display = 'flex';
            renderCalendarList();
        }

        function closeCalendarModal() {
            document.getElementById('calendarModal').style.display = 'none';
            hideCalendarForm();
        }

        function renderCalendarList() {
            const list = document.getElementById('calendarList');
            list.innerHTML = '';

            // Option: show all calendars
            const allEntry = document.createElement('div');
            allEntry.style.display = 'flex';
            allEntry.style.justifyContent = 'space-between';
            allEntry.style.alignItems = 'center';
            allEntry.style.padding = '10px';
            allEntry.style.borderBottom = '1px solid var(--border-color)';
            allEntry.style.cursor = 'pointer';
            allEntry.innerHTML = `<div style="display: flex; align-items: center; gap: 10px;"><span style="width: 12px; height: 12px; background: #999; border-radius: 50%; display: inline-block;"></span><strong>All Calendars</strong></div>`;
            allEntry.addEventListener('click', () => selectCalendar(0));
            list.appendChild(allEntry);

            calendarListData.forEach(cal => {
                const row = document.createElement('div');
                row.style.display = 'flex';
                row.style.justifyContent = 'space-between';
                row.style.alignItems = 'center';
                row.style.padding = '10px';
                row.style.borderBottom = '1px solid var(--border-color)';

                const left = document.createElement('div');
                left.style.display = 'flex';
                left.style.alignItems = 'center';
                left.style.gap = '10px';
                left.style.cursor = 'pointer';
                left.innerHTML = `
                    <span style="width: 12px; height: 12px; background: ${cal.color}; border-radius: 50%; display: inline-block;"></span>
                    <span>${cal.name}</span>
                `;
                left.addEventListener('click', () => selectCalendar(cal.id));

                const actions = document.createElement('div');
                actions.style.display = 'flex';
                actions.style.gap = '8px';

                const editBtn = document.createElement('button');
                editBtn.type = 'button';
                editBtn.className = 'btn btn-secondary btn-sm';
                editBtn.textContent = 'Edit';
                editBtn.addEventListener('click', () => editCalendar(cal));

                const deleteBtn = document.createElement('button');
                deleteBtn.type = 'button';
                deleteBtn.className = 'btn btn-danger btn-sm';
                deleteBtn.textContent = 'Delete';
                deleteBtn.addEventListener('click', () => deleteCalendar(cal.id));

                actions.appendChild(editBtn);
                actions.appendChild(deleteBtn);

                row.appendChild(left);
                row.appendChild(actions);
                list.appendChild(row);
            });
        }

        function showCalendarForm(calendar = null) {
            document.getElementById('calendarForm').style.display = 'block';
            document.getElementById('calendarName').value = calendar ? calendar.name : '';
            document.getElementById('calendarColor').value = calendar ? calendar.color : '#667eea';
            document.getElementById('calendarId').value = calendar ? calendar.id : '';
        }

        function hideCalendarForm() {
            document.getElementById('calendarForm').style.display = 'none';
            document.getElementById('calendarName').value = '';
            document.getElementById('calendarColor').value = '#667eea';
            document.getElementById('calendarId').value = '';
        }

        async function saveCalendar() {
            const name = document.getElementById('calendarName').value.trim();
            const color = document.getElementById('calendarColor').value;
            const id = document.getElementById('calendarId').value;

            if (!name) {
                Notification.error('Calendar name is required');
                return;
            }

            const formData = new FormData();
            if (id) formData.append('id', id);
            formData.append('name', name);
            formData.append('color', color);
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

            try {
                const response = await fetch('/clockit/api/calendars/save.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    Notification.success(data.message || 'Calendar saved', 2000);
                    hideCalendarForm();
                    await reloadCalendars();
                } else {
                    Notification.error(data.message || 'Failed to save calendar');
                }
            } catch (err) {
                Notification.error('Error: ' + err.message);
            }
        }

        async function deleteCalendar(id) {
            if (!confirm('Delete this calendar? Events will not be removed.')) return;

            const formData = new FormData();
            formData.append('id', id);
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

            try {
                const response = await fetch('/clockit/api/calendars/delete.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    Notification.success(data.message || 'Calendar deleted', 2000);
                    await reloadCalendars();
                } else {
                    Notification.error(data.message || 'Failed to delete calendar');
                }
            } catch (err) {
                Notification.error('Error: ' + err.message);
            }
        }

        async function reloadCalendars() {
            try {
                const response = await fetch('/clockit/api/calendars/get.php');
                const data = await response.json();
                if (data.success) {
                    window.calendarListData = data.data || [];
                    renderCalendarList();
                }
            } catch (err) {
                console.error(err);
            }
        }

        function editCalendar(calendar) {
            showCalendarForm(calendar);
        }

        function selectCalendar(id) {
            const params = new URLSearchParams(window.location.search);
            if (id && id > 0) {
                params.set('calendar_id', id);
            } else {
                params.delete('calendar_id');
            }
            params.set('month', currentMonth);
            params.set('year', currentYear);
            window.location.search = params.toString();
        }
    </script>

    <?php HTMLHelper::renderFooter(); ?>
</body>
</html>
