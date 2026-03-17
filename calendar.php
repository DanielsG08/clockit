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

$events = [];
try {
    $events = $db->fetchAll(
        "SELECT id, title, description, event_date, event_time, color, calendar_type
         FROM calendar_events
         WHERE user_id = ? AND event_date BETWEEN ? AND ?
         ORDER BY event_date ASC, event_time ASC",
        [$userId, $monthStart, $monthEnd]
    ) ?? [];
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

function getNationalHolidays($year, $month) {
    $holidays = [];

    // Fixed date holidays
    $fixed = [
        '01-01' => 'New Year\'s Day',
        '07-04' => 'Independence Day',
        '11-11' => 'Veterans Day',
        '12-25' => 'Christmas Day'
    ];

    foreach ($fixed as $md => $name) {
        list($m, $d) = explode('-', $md);
        if ((int)$m === (int)$month) {
            $holidays[sprintf('%04d-%02d-%02d', $year, $m, $d)] = $name;
        }
    }

    // Helper to get nth weekday of month
    $getNthWeekday = function($year, $month, $weekday, $n) {
        $first = new DateTime("$year-$month-01");
        $firstWeekday = (int)$first->format('w');
        $offset = ($weekday - $firstWeekday + 7) % 7;
        $day = 1 + $offset + 7 * ($n - 1);
        if ($day > (int)date('t', strtotime("$year-$month-01"))) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    };

    // Helper to get last weekday of month
    $getLastWeekday = function($year, $month, $weekday) {
        $last = new DateTime("$year-$month-01");
        $last->modify('last day of this month');
        while ((int)$last->format('w') !== $weekday) {
            $last->modify('-1 day');
        }
        return $last->format('Y-m-d');
    };

    if ((int)$month === 1) {
        $holidays[$getNthWeekday($year, $month, 1, 3)] = 'Martin Luther King Jr. Day';
    }

    if ((int)$month === 2) {
        $holidays[$getNthWeekday($year, $month, 1, 3)] = 'Presidents\' Day';
    }

    if ((int)$month === 5) {
        $holidays[$getLastWeekday($year, $month, 1)] = 'Memorial Day';
    }

    if ((int)$month === 9) {
        $holidays[$getNthWeekday($year, $month, 1, 1)] = 'Labor Day';
    }

    if ((int)$month === 10) {
        $holidays[$getNthWeekday($year, $month, 1, 2)] = 'Columbus Day';
    }

    if ((int)$month === 11) {
        $holidays[$getNthWeekday($year, $month, 4, 4)] = 'Thanksgiving';
    }

    return $holidays;
}

$holidays = getNationalHolidays($year, $month);

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
                <a href="?month=<?php echo $month === 1 ? 12 : $month - 1; ?>&year=<?php echo $month === 1 ? $year - 1 : $year; ?>" class="btn btn-secondary btn-sm">
                    ← Previous
                </a>
                
                <h3 style="margin: 0; font-size: 1.5rem;">
                    <?php echo date('F Y', strtotime("$year-$month-01")); ?>
                </h3>

                <div style="display: flex; gap: 10px; align-items: center;">
                    <span style="font-size: 0.85rem; color: var(--text-secondary);">Calendar view:</span>
                    <select id="calendarTypeSelect" style="padding: 6px; border-radius: 5px; border: 1px solid var(--border-color);">
                        <option value="All">All Calendars</option>
                        <option value="Personal">Personal</option>
                        <option value="Work">Work</option>
                        <option value="Health">Health</option>
                        <option value="Education">Education</option>
                    </select>
                </div>
                
                <a href="?month=<?php echo $month === 12 ? 1 : $month + 1; ?>&year=<?php echo $month === 12 ? $year + 1 : $year; ?>" class="btn btn-secondary btn-sm">
                    Next →
                </a>
            </div>
        </div>

        <style>
            .calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(140px, 1fr)); grid-auto-rows: 160px; gap: 1px; background: var(--border-color); }
            .calendar-header-cell { padding: 12px; background: var(--bg-secondary); font-weight: bold; text-align: center; height: 48px; min-height: 48px; }
            .calendar-day-cell, .calendar-empty-cell { padding: 10px; min-height: 160px; height: 160px; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 5px; cursor: pointer; overflow: hidden; display: flex; flex-direction: column; justify-content: flex-start; max-width: 160px; }
            .calendar-empty-cell { background: rgba(0,0,0,0.02); cursor: default; }
            .holiday-badge { margin-top: 6px; color: #c0392b; font-size: 0.72rem; font-weight: 700; }
            .calendar-event-chip { padding: 2px 5px; margin: 2px 0; border-radius: 3px; font-size: 0.72rem; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        </style>

        <div style="display: grid; grid-template-columns: 260px minmax(680px, 1fr) 260px; gap: 20px; align-items: start; margin-top: 30px; min-width: 1200px;">
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted" style="font-size: 0.9rem; margin-bottom: 8px;">Total Event Colors</div>
                        <div id="totalEventColorsCount" style="font-size: 2rem; font-weight: bold; color: #42c88a;">0</div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h3 style="margin: 0 0 12px 0;">Coming Up Events</h3>
                        <div id="comingUpList" style="display: flex; flex-direction: column; gap: 8px;"><p class="text-muted">No upcoming events found.</p></div>
                    </div>
                </div>
            </div>

            <div>
                <div class="card">
                    <div class="card-body">
                        <div class="calendar-grid" style="min-height: 100vh;">
                            <!-- Day headers -->
                            <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day): ?>
                                <div style="padding: 12px; background: var(--bg-secondary); font-weight: bold; text-align: center;">
                                    <?php echo $day; ?>
                                </div>
                            <?php endforeach; ?>

                            <!-- Empty cells before month starts -->
                            <?php for ($i = 0; $i < $startingWeek; $i++): ?>
                                <div class="calendar-empty-cell"></div>
                            <?php endfor; ?>

                            <!-- Days of month -->
                            <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                                <?php
                                    $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                                    $dayEvents = $eventsByDate[$date] ?? [];
                                    $isToday = $date === date('Y-m-d');
                                ?>
                                <div class="calendar-day-cell" data-date="<?php echo $date; ?>" style="padding: 12px; background: var(--bg-primary); border: 1px solid var(--border-color); min-height: 160px; border-radius: 5px; cursor: pointer; <?php echo $isToday ? 'border: 2px solid #667eea;' : ''; ?>" onclick="openEventModal('<?php echo $date; ?>')">
                                    <div style="font-weight: bold; margin-bottom: 8px; color: <?php echo $isToday ? '#667eea' : 'var(--text-primary)'; ?>;">
                                        <?php echo $day; ?>
                                    </div>
                                    <?php if (!empty($holidays[$date])): ?>
                                        <div class="holiday-badge">🏛️ <?php echo htmlspecialchars($holidays[$date]); ?></div>
                                    <?php endif; ?>

                                    <?php if (!empty($dayEvents)): ?>
                                        <div style="font-size: 0.7rem; max-height: 90px; overflow-y: auto;">
                                            <?php foreach (array_slice($dayEvents, 0, 4) as $event): ?>
                                                <div class="calendar-event-chip" style="background: <?php echo htmlspecialchars($event['color']); ?>20; border-left: 3px solid <?php echo htmlspecialchars($event['color']); ?>;" title="<?php echo htmlspecialchars($event['title']); ?> - <?php echo $event['event_time'] ?? ''; ?>">
                                                    <strong><?php echo htmlspecialchars($event['title']); ?></strong>
                                                    <span style="font-size: 0.65rem; color: #555; margin-left: 4px;">[<?php echo htmlspecialchars($event['calendar_type'] ?? 'Personal'); ?>]</span>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php if (count($dayEvents) > 4): ?>
                                                <div style="color: #999; font-size: 0.65rem; padding: 2px 4px;">+<?php echo count($dayEvents) - 4; ?> more</div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endfor; ?>

                            <?php
                                $remainingCells = 42 - ($startingWeek + $daysInMonth);
                                for ($i = 0; $i < $remainingCells; $i++):
                            ?>
                                <div class="calendar-empty-cell"></div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted" style="font-size: 0.9rem; margin-bottom: 8px;">Events This Month</div>
                        <div id="eventsThisMonthCount" style="font-size: 2rem; font-weight: bold; color: #f39c12;">0</div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="text-muted" style="font-size: 0.9rem; margin-bottom: 8px;">Days with Events</div>
                        <div id="daysWithEventsCount" style="font-size: 2rem; font-weight: bold; color: #667eea;">0</div>
                    </div>
                </div>
            </div>
        </div>

    <!-- Event Modal -->
    <div id="eventModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: var(--bg-primary); padding: 30px; border-radius: 10px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
            <h2 style="margin-top: 0;">Add Event</h2>
            <p style="color: var(--text-secondary);">Selected date: <strong id="selectedDate"></strong></p>
            <p id="holidayLabel" style="color: #c0392b; font-weight: 700; margin-top: 5px; display: none;"></p>

            <form id="eventForm" style="display: flex; flex-direction: column; gap: 15px;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="id" id="eventId">
                <input type="hidden" name="date" id="eventDate">

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
                    <label for="eventCategory" style="display: block; margin-bottom: 5px; font-weight: bold;">Category</label>
                    <select id="eventCategory" name="calendar_type" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 5px; background: var(--bg-secondary);">
                        <option value="Personal">Personal</option>
                        <option value="Work">Work</option>
                        <option value="Health">Health</option>
                        <option value="Education">Education</option>
                    </select>
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

    <script>
        let currentSelectedDate = null;
        const HOLIDAYS = <?php echo json_encode($holidays); ?>;
        const calendarTypeSelect = document.getElementById('calendarTypeSelect');
        let selectedCalendarType = calendarTypeSelect ? calendarTypeSelect.value : 'All';

        if (calendarTypeSelect) {
            calendarTypeSelect.addEventListener('change', () => {
                selectedCalendarType = calendarTypeSelect.value;
                refreshCalendarData();
                if (currentSelectedDate) loadEventsForDate(currentSelectedDate);
            });
        }

        function ensureCurrentMonthYearOnReload() {
            const now = new Date();
            const currentMonth = now.getMonth() + 1;
            const currentYear = now.getFullYear();
            const params = new URLSearchParams(window.location.search);
            const seenMonth = Number(params.get('month')) || null;
            const seenYear = Number(params.get('year')) || null;

            const navigation = performance.getEntriesByType('navigation')[0];
            const isReload = (navigation && navigation.type === 'reload') || (performance.navigation && performance.navigation.type === 1);

            if (isReload && (seenMonth !== currentMonth || seenYear !== currentYear)) {
                params.set('month', String(currentMonth));
                params.set('year', String(currentYear));
                window.location.replace(window.location.pathname + '?' + params.toString());
            }
        }

        ensureCurrentMonthYearOnReload();

        // initial data load
        currentSelectedDate = '<?php echo date('Y-m-d'); ?>';
        refreshCalendarData();
        loadEventsForDate(currentSelectedDate);

        function openEventModal(date) {
            currentSelectedDate = date;
            document.getElementById('eventModal').style.display = 'flex';
            document.getElementById('eventForm').reset();
            document.getElementById('eventId').value = '';
            document.getElementById('eventDate').value = date;
            document.getElementById('eventCategory').value = 'Personal';
            // Set default color
            document.querySelector('input[name="color"][value="#667eea"]').checked = true;

            // Show holiday if any
            const holidayLabel = document.getElementById('holidayLabel');
            if (HOLIDAYS[date]) {
                holidayLabel.textContent = `National Holiday: ${HOLIDAYS[date]}`;
                holidayLabel.style.display = 'block';
            } else {
                holidayLabel.textContent = '';
                holidayLabel.style.display = 'none';
            }

            loadEventsForDate(date);
        }

        function closeEventModal() {
            document.getElementById('eventModal').style.display = 'none';
            document.getElementById('eventsList').innerHTML = '';
        }

        function loadEventsForDate(date) {
            fetch(`${APP_BASE_URL}/api/events/get.php?from=${date}&to=${date}`)
                .then(r => r.json())
                .then(data => {
                    let html = '<h4 style="margin-top: 0;">Events on this day:</h4>';
                    if (HOLIDAYS[date]) {
                        html += `<div class="holiday-badge" style="margin-bottom: 10px;">🏛️ ${HOLIDAYS[date]}</div>`;
                    }

                    const events = (data.data || []).filter(event => selectedCalendarType === 'All' || (event.calendar_type || 'Personal') === selectedCalendarType);

                    if (events.length > 0) {
                        html += '<div style="display: flex; flex-direction: column; gap: 10px;">';
                        events.forEach(event => {
                            html += `
                                <div onclick="editEvent(${event.id}, '${event.title.replace(/'/g, "\\'")}', '${(event.description || '').replace(/'/g, "\\'")}', '${event.event_time || ''}', '${event.color}', '${event.calendar_type || 'Personal'}')" 
                                     style="padding: 10px; background: ${event.color}20; border-left: 3px solid ${event.color}; border-radius: 3px; cursor: pointer; transition: all 0.2s;">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div style="font-weight: bold;">${event.title}</div>
                                        <span style="font-size: 0.7rem; color: #555; padding: 1px 5px; background: rgba(0,0,0,0.05); border-radius: 3px;">${event.calendar_type || 'Personal'}</span>
                                    </div>
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

        async function refreshCalendarData() {
            const fromDate = '<?php echo $monthStart; ?>';
            const toDate = '<?php echo $monthEnd; ?>';
            try {
                const response = await fetch(`${APP_BASE_URL}/api/events/get.php?from=${fromDate}&to=${toDate}`);
                const data = await response.json();
                if (data.success && Array.isArray(data.data)) {
                    const allEvents = data.data;
                    const filteredEvents = allEvents.filter(event => selectedCalendarType === 'All' || (event.calendar_type || 'Personal') === selectedCalendarType);

                    const eventsByDate = {};
                    filteredEvents.forEach(event => {
                        if (!eventsByDate[event.event_date]) eventsByDate[event.event_date] = [];
                        eventsByDate[event.event_date].push(event);
                    });

                    document.querySelectorAll('.calendar-day-cell').forEach(cell => {
                        const date = cell.dataset.date;
                        const events = eventsByDate[date] || [];

                        const existingTitle = cell.querySelector('div');
                        const lines = [];
                        if (existingTitle) {
                            const d = existingTitle.textContent;
                        }

                        const existingHoliday = cell.querySelector('.holiday-badge');
                        if (HOLIDAYS[date]) {
                            if (!existingHoliday) {
                                const h = document.createElement('div');
                                h.className = 'holiday-badge';
                                h.textContent = `🏛️ ${HOLIDAYS[date]}`;
                                h.style.marginTop = '4px';
                                cell.insertBefore(h, cell.children[1] || null);
                            }
                        } else if (existingHoliday) {
                            existingHoliday.remove();
                        }

                        const eventContainer = cell.querySelector('.calendar-events-list');
                        if (eventContainer) {
                            eventContainer.remove();
                        }

                        if (events.length > 0) {
                            const container = document.createElement('div');
                            container.className = 'calendar-events-list';
                            container.style.marginTop = '6px';
                            container.style.display = 'flex';
                            container.style.flexDirection = 'column';
                            container.style.gap = '2px';
                            events.slice(0, 4).forEach(event => {
                                const slot = document.createElement('div');
                                slot.className = 'calendar-event-chip';
                                slot.textContent = event.title;
                                slot.title = `${event.title} ${event.event_time || ''}`;
                                slot.style.background = `${event.color}20`;
                                slot.style.borderLeft = `3px solid ${event.color}`;
                                container.appendChild(slot);
                            });
                            if (events.length > 4) {
                                const extra = document.createElement('div');
                                extra.style.color = '#999';
                                extra.style.fontSize = '0.65rem';
                                extra.textContent = `+${events.length - 4} more`;
                                container.appendChild(extra);
                            }
                            cell.appendChild(container);
                        }
                    });

                    updateStats(filteredEvents);
                }
            } catch (err) {
                console.error('Failed to refresh calendar data:', err);
            }
        }

        function updateStats(events) {
            const eventsThisMonthCount = document.getElementById('eventsThisMonthCount');
            const daysWithEventsCount = document.getElementById('daysWithEventsCount');
            const totalEventColorsCount = document.getElementById('totalEventColorsCount');
            const comingUpList = document.getElementById('comingUpList');

            if (eventsThisMonthCount) {
                eventsThisMonthCount.textContent = events.length;
            }

            const daysWithEvents = new Set(events.map(e => e.event_date));
            if (daysWithEventsCount) {
                daysWithEventsCount.textContent = daysWithEvents.size;
            }

            const colors = new Set(events.map(e => e.color || '#667eea'));
            if (totalEventColorsCount) {
                totalEventColorsCount.textContent = colors.size;
            }

            if (comingUpList) {
                const today = new Date().toISOString().slice(0, 10);
                const upcoming = events
                    .filter(e => e.event_date >= today)
                    .sort((a, b) => {
                        if (a.event_date !== b.event_date) return a.event_date.localeCompare(b.event_date);
                        return (a.event_time || '').localeCompare(b.event_time || '');
                    })
                    .slice(0, 5);

                if (upcoming.length === 0) {
                    comingUpList.innerHTML = '<p class="text-muted">No upcoming events found.</p>';
                } else {
                    comingUpList.innerHTML = '';
                    upcoming.forEach(event => {
                        const card = document.createElement('div');
                        card.style.padding = '8px';
                        card.style.border = '1px solid var(--border-color)';
                        card.style.borderRadius = '5px';
                        card.style.background = 'var(--bg-secondary)';
                        card.innerHTML = `<strong>${event.event_date} ${event.event_time || 'All day'}</strong><br>${event.title} [${event.calendar_type || 'Personal'}]`;
                        comingUpList.appendChild(card);
                    });
                }
            }
        }

        function editEvent(id, title, description, time, color, category) {
            document.getElementById('eventId').value = id;
            document.getElementById('eventTitle').value = title;
            document.getElementById('eventDescription').value = description;
            document.getElementById('eventTime').value = time;
            document.getElementById('eventCategory').value = category || 'Personal';
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
                const response = await fetch(`${APP_BASE_URL}/api/events/save.php`, {
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
                    
                    // Refresh day and calendar in place
                    await refreshCalendarData();
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
                const response = await fetch(`${APP_BASE_URL}/api/events/delete.php`, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    Notification.show('Event deleted successfully!', 'success');
                    await refreshCalendarData();
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
    </script>

    <?php HTMLHelper::renderFooter(); ?>
</body>
</html>
