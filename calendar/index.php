<?php
/**
 * CRC Calendar Page - Dashboard Layout
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$primaryCong = Auth::primaryCongregation();
if (!$primaryCong) {
    Response::redirect('/onboarding/');
}

$user = Auth::user();
$pageTitle = 'Calendar - CRC';

// Get current month/year
$month = (int)($_GET['month'] ?? date('n'));
$year = (int)($_GET['year'] ?? date('Y'));

// Validate
if ($month < 1 || $month > 12) $month = date('n');
if ($year < 2020 || $year > 2030) $year = date('Y');

// Get first and last day of month
$firstDay = mktime(0, 0, 0, $month, 1, $year);
$lastDay = mktime(0, 0, 0, $month + 1, 0, $year);
$daysInMonth = date('j', $lastDay);
$startDayOfWeek = date('w', $firstDay);

// Get events for this month
$startDate = date('Y-m-01', $firstDay);
$endDate = date('Y-m-t', $lastDay);

$events = [];
$upcomingEvents = [];
$todayEvents = [];

try {
    $events = Database::fetchAll(
        "SELECT e.*, c.name as congregation_name
         FROM events e
         LEFT JOIN congregations c ON e.congregation_id = c.id
         WHERE ((e.scope = 'global') OR (e.congregation_id = ?) OR (e.user_id = ?))
         AND e.status = 'published'
         AND DATE(e.start_datetime) BETWEEN ? AND ?
         ORDER BY e.start_datetime ASC",
        [$primaryCong['id'], $user['id'], $startDate, $endDate]
    ) ?: [];
} catch (Exception $e) {}

// Organize by date
$eventsByDate = [];
foreach ($events as $event) {
    $date = date('Y-m-d', strtotime($event['start_datetime']));
    $eventsByDate[$date][] = $event;
}

// Get today's events
$today = date('Y-m-d');
$todayEvents = $eventsByDate[$today] ?? [];

// Get upcoming events
try {
    $upcomingEvents = Database::fetchAll(
        "SELECT e.*, c.name as congregation_name
         FROM events e
         LEFT JOIN congregations c ON e.congregation_id = c.id
         WHERE (e.scope = 'global' OR e.congregation_id = ? OR e.user_id = ?)
         AND e.status = 'published'
         AND e.start_datetime >= NOW()
         ORDER BY e.start_datetime ASC
         LIMIT 5",
        [$primaryCong['id'], $user['id']]
    ) ?: [];
} catch (Exception $e) {}

// Month navigation
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

$monthName = date('F', $firstDay);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/home/css/home.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .calendar-card {
            background: linear-gradient(135deg, #059669 0%, #10B981 100%);
            color: var(--white);
        }
        .calendar-card .card-header h2 { color: var(--white); }
        .month-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        .month-nav button, .month-nav a {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition);
        }
        .month-nav button:hover, .month-nav a:hover { background: rgba(255,255,255,0.3); }
        .month-name { font-size: 1.25rem; font-weight: 600; }
        .mini-calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
            font-size: 0.75rem;
        }
        .mini-calendar .day-header {
            text-align: center;
            padding: 0.25rem;
            font-weight: 600;
            color: rgba(255,255,255,0.8);
        }
        .mini-calendar .day {
            text-align: center;
            padding: 0.5rem 0.25rem;
            border-radius: 4px;
            cursor: pointer;
            transition: var(--transition);
        }
        .mini-calendar .day:hover { background: rgba(255,255,255,0.2); }
        .mini-calendar .day.today { background: rgba(255,255,255,0.3); font-weight: 700; }
        .mini-calendar .day.has-event { position: relative; }
        .mini-calendar .day.has-event::after {
            content: '';
            position: absolute;
            bottom: 2px;
            left: 50%;
            transform: translateX(-50%);
            width: 4px;
            height: 4px;
            background: white;
            border-radius: 50%;
        }
        .mini-calendar .day.empty { visibility: hidden; }
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }
        .quick-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem;
            background: var(--gray-50);
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--gray-700);
            transition: var(--transition);
        }
        .quick-action:hover { background: var(--primary); color: white; }
        .quick-action-icon { font-size: 1.5rem; }
        .event-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .event-item {
            display: flex;
            gap: 1rem;
            padding: 0.75rem;
            background: var(--gray-50);
            border-radius: var(--radius);
            text-decoration: none;
            transition: var(--transition);
        }
        .event-item:hover { background: var(--gray-100); }
        .event-date-badge {
            min-width: 48px;
            padding: 0.5rem;
            background: var(--primary);
            color: white;
            border-radius: var(--radius);
            text-align: center;
        }
        .event-date-badge .day { font-size: 1.25rem; font-weight: 700; line-height: 1; }
        .event-date-badge .month { font-size: 0.7rem; text-transform: uppercase; }
        .event-info h4 { font-size: 0.875rem; color: var(--gray-800); margin-bottom: 0.25rem; }
        .event-info p { font-size: 0.75rem; color: var(--gray-500); }
        .today-events-card .event-item { background: rgba(5, 150, 105, 0.1); }
        @media (max-width: 640px) {
            .mini-calendar { font-size: 0.65rem; }
            .mini-calendar .day { padding: 0.35rem 0.15rem; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <!-- Welcome Section -->
            <section class="welcome-section">
                <div class="welcome-content">
                    <h1>Calendar</h1>
                    <p>Your events and congregation activities</p>
                </div>
            </section>

            <div class="dashboard-grid">
                <!-- Calendar Card -->
                <div class="dashboard-card calendar-card">
                    <div class="month-nav">
                        <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>">←</a>
                        <span class="month-name"><?= $monthName ?> <?= $year ?></span>
                        <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>">→</a>
                    </div>
                    <div class="mini-calendar">
                        <div class="day-header">S</div>
                        <div class="day-header">M</div>
                        <div class="day-header">T</div>
                        <div class="day-header">W</div>
                        <div class="day-header">T</div>
                        <div class="day-header">F</div>
                        <div class="day-header">S</div>
                        <?php for ($i = 0; $i < $startDayOfWeek; $i++): ?>
                            <div class="day empty"></div>
                        <?php endfor; ?>
                        <?php for ($day = 1; $day <= $daysInMonth; $day++):
                            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                            $isToday = $date === date('Y-m-d');
                            $hasEvent = isset($eventsByDate[$date]);
                        ?>
                            <div class="day <?= $isToday ? 'today' : '' ?> <?= $hasEvent ? 'has-event' : '' ?>">
                                <?= $day ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Quick Actions Card -->
                <div class="dashboard-card">
                    <h2 style="margin-bottom: 1rem;">Quick Actions</h2>
                    <div class="quick-actions-grid">
                        <a href="/calendar/create.php" class="quick-action">
                            <span class="quick-action-icon">➕</span>
                            <span>Add Event</span>
                        </a>
                        <a href="/calendar/?month=<?= date('n') ?>&year=<?= date('Y') ?>" class="quick-action">
                            <span class="quick-action-icon">📅</span>
                            <span>This Month</span>
                        </a>
                        <a href="/calendar/my-events.php" class="quick-action">
                            <span class="quick-action-icon">⭐</span>
                            <span>My Events</span>
                        </a>
                        <a href="/calendar/birthdays.php" class="quick-action">
                            <span class="quick-action-icon">🎂</span>
                            <span>Birthdays</span>
                        </a>
                    </div>
                </div>

                <!-- Today's Events Card -->
                <div class="dashboard-card today-events-card">
                    <div class="card-header">
                        <h2>Today</h2>
                        <span style="color: var(--gray-500); font-size: 0.875rem;"><?= date('l, M j') ?></span>
                    </div>
                    <?php if ($todayEvents): ?>
                        <div class="event-list">
                            <?php foreach ($todayEvents as $event): ?>
                                <a href="/calendar/event.php?id=<?= $event['id'] ?>" class="event-item">
                                    <div class="event-date-badge" style="background: var(--secondary);">
                                        <div class="day"><?= date('H:i', strtotime($event['start_datetime'])) ?></div>
                                    </div>
                                    <div class="event-info">
                                        <h4><?= e($event['title']) ?></h4>
                                        <p><?= e($event['location'] ?: 'No location') ?></p>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 2rem 1rem; color: var(--gray-500);">
                            <p>No events today</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Upcoming Events Card -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>Upcoming Events</h2>
                        <a href="/calendar/all.php" class="view-all-link">View All</a>
                    </div>
                    <?php if ($upcomingEvents): ?>
                        <div class="event-list">
                            <?php foreach ($upcomingEvents as $event): ?>
                                <a href="/calendar/event.php?id=<?= $event['id'] ?>" class="event-item">
                                    <div class="event-date-badge">
                                        <div class="day"><?= date('d', strtotime($event['start_datetime'])) ?></div>
                                        <div class="month"><?= date('M', strtotime($event['start_datetime'])) ?></div>
                                    </div>
                                    <div class="event-info">
                                        <h4><?= e($event['title']) ?></h4>
                                        <p><?= date('H:i', strtotime($event['start_datetime'])) ?> • <?= e($event['location'] ?: 'No location') ?></p>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 2rem 1rem; color: var(--gray-500);">
                            <p>No upcoming events</p>
                            <a href="/calendar/create.php" class="btn btn-outline" style="margin-top: 0.5rem;">Create Event</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
