<?php
/**
 * CRC Calendar Page
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

$events = Database::fetchAll(
    "SELECT e.*,
            c.name as congregation_name
     FROM events e
     LEFT JOIN congregations c ON e.congregation_id = c.id
     WHERE (
         (e.scope = 'global')
         OR (e.congregation_id = ?)
         OR (e.user_id = ?)
     )
     AND e.status = 'published'
     AND DATE(e.start_datetime) BETWEEN ? AND ?
     ORDER BY e.start_datetime ASC",
    [$primaryCong['id'], $user['id'], $startDate, $endDate]
);

// Get personal calendar events
$personalEvents = Database::fetchAll(
    "SELECT * FROM calendar_events
     WHERE user_id = ?
     AND DATE(start_datetime) BETWEEN ? AND ?
     AND status = 'active'
     ORDER BY start_datetime ASC",
    [$user['id'], $startDate, $endDate]
);

// Merge and organize by date
$eventsByDate = [];
foreach ($events as $event) {
    $date = date('Y-m-d', strtotime($event['start_datetime']));
    $eventsByDate[$date][] = array_merge($event, ['type' => 'event']);
}
foreach ($personalEvents as $event) {
    $date = date('Y-m-d', strtotime($event['start_datetime']));
    $eventsByDate[$date][] = array_merge($event, ['type' => 'personal']);
}

// Month navigation
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}
$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

$monthName = date('F', $firstDay);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/calendar/css/calendar.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="calendar-header">
                <div class="calendar-title">
                    <h1>Calendar</h1>
                    <p>Your events and congregation activities</p>
                </div>
                <div class="calendar-actions">
                    <a href="/calendar/create.php" class="btn btn-primary">+ Add Event</a>
                </div>
            </div>

            <div class="calendar-layout">
                <div class="calendar-main">
                    <!-- Month Navigation -->
                    <div class="calendar-nav">
                        <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="nav-btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="15 18 9 12 15 6"></polyline>
                            </svg>
                        </a>
                        <h2><?= $monthName ?> <?= $year ?></h2>
                        <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="nav-btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6"></polyline>
                            </svg>
                        </a>
                    </div>

                    <!-- Calendar Grid -->
                    <div class="calendar-grid">
                        <!-- Day Headers -->
                        <div class="day-header">Sun</div>
                        <div class="day-header">Mon</div>
                        <div class="day-header">Tue</div>
                        <div class="day-header">Wed</div>
                        <div class="day-header">Thu</div>
                        <div class="day-header">Fri</div>
                        <div class="day-header">Sat</div>

                        <!-- Empty cells before first day -->
                        <?php for ($i = 0; $i < $startDayOfWeek; $i++): ?>
                            <div class="calendar-cell empty"></div>
                        <?php endfor; ?>

                        <!-- Days -->
                        <?php for ($day = 1; $day <= $daysInMonth; $day++):
                            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                            $isToday = $date === date('Y-m-d');
                            $dayEvents = $eventsByDate[$date] ?? [];
                        ?>
                            <div class="calendar-cell <?= $isToday ? 'today' : '' ?> <?= !empty($dayEvents) ? 'has-events' : '' ?>">
                                <span class="day-number"><?= $day ?></span>
                                <?php if ($dayEvents): ?>
                                    <div class="cell-events">
                                        <?php foreach (array_slice($dayEvents, 0, 3) as $event): ?>
                                            <div class="event-dot <?= $event['type'] === 'personal' ? 'personal' : ($event['scope'] ?? 'congregation') ?>">
                                                <?= e(truncate($event['title'], 15)) ?>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (count($dayEvents) > 3): ?>
                                            <span class="more-events">+<?= count($dayEvents) - 3 ?> more</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Upcoming Events Sidebar -->
                <div class="calendar-sidebar">
                    <h3>Upcoming Events</h3>
                    <?php
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
                    );
                    ?>
                    <?php if ($upcomingEvents): ?>
                        <div class="upcoming-list">
                            <?php foreach ($upcomingEvents as $event): ?>
                                <a href="/calendar/event.php?id=<?= $event['id'] ?>" class="upcoming-item">
                                    <div class="event-date">
                                        <span class="day"><?= date('d', strtotime($event['start_datetime'])) ?></span>
                                        <span class="month"><?= date('M', strtotime($event['start_datetime'])) ?></span>
                                    </div>
                                    <div class="event-details">
                                        <h4><?= e($event['title']) ?></h4>
                                        <p><?= date('H:i', strtotime($event['start_datetime'])) ?></p>
                                        <?php if ($event['location']): ?>
                                            <p><?= e($event['location']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-events">
                            <p>No upcoming events</p>
                        </div>
                    <?php endif; ?>

                    <div class="legend">
                        <h4>Legend</h4>
                        <div class="legend-item">
                            <span class="dot global"></span> Global
                        </div>
                        <div class="legend-item">
                            <span class="dot congregation"></span> Congregation
                        </div>
                        <div class="legend-item">
                            <span class="dot personal"></span> Personal
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
