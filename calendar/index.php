<?php
/**
 * CRC Calendar Page - Month, Week, Day Views
 * Integrates: Personal, Congregation, Morning Study, Homecell, Courses
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$primaryCong = Auth::primaryCongregation();
if (!$primaryCong) {
    Response::redirect('/onboarding/');
}

$user = Auth::user();
$pageTitle = 'Calendar - CRC';

// Get current view and date
$view = $_GET['view'] ?? 'month';
if (!in_array($view, ['month', 'week', 'day'])) {
    $view = 'month';
}

// Parse date parameters
$today = date('Y-m-d');
$year = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));
$day = (int)($_GET['day'] ?? date('j'));

// Validate
if ($month < 1 || $month > 12) $month = date('n');
if ($year < 2020 || $year > 2030) $year = date('Y');
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
if ($day < 1 || $day > $daysInMonth) $day = 1;

$currentDate = sprintf('%04d-%02d-%02d', $year, $month, $day);

// Calculate date ranges based on view
switch ($view) {
    case 'day':
        $startDate = $currentDate;
        $endDate = $currentDate;
        break;
    case 'week':
        $weekStart = date('Y-m-d', strtotime('monday this week', strtotime($currentDate)));
        $weekEnd = date('Y-m-d', strtotime('sunday this week', strtotime($currentDate)));
        $startDate = $weekStart;
        $endDate = $weekEnd;
        break;
    case 'month':
    default:
        $startDate = date('Y-m-01', strtotime($currentDate));
        $endDate = date('Y-m-t', strtotime($currentDate));
        break;
}

// Month calculations for month view
$firstDay = mktime(0, 0, 0, $month, 1, $year);
$lastDay = mktime(0, 0, 0, $month + 1, 0, $year);
$daysInMonth = date('j', $lastDay);
$startDayOfWeek = date('w', $firstDay);

// Navigation URLs
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

$monthName = date('F', $firstDay);

// Week navigation
$prevWeek = date('Y-m-d', strtotime('-1 week', strtotime($currentDate)));
$nextWeek = date('Y-m-d', strtotime('+1 week', strtotime($currentDate)));

// Day navigation
$prevDay = date('Y-m-d', strtotime('-1 day', strtotime($currentDate)));
$nextDay = date('Y-m-d', strtotime('+1 day', strtotime($currentDate)));

// Fetch all events for current range
$allEvents = [];
$eventsByDate = [];
$eventsByHour = [];

try {
    // Congregation/Global events
    $congEvents = Database::fetchAll(
        "SELECT e.*, 'event' as source_type,
                CASE WHEN e.scope = 'global' THEN 'global' ELSE 'congregation' END as event_category
         FROM events e
         WHERE (e.scope = 'global' OR e.congregation_id = ?)
         AND e.status = 'published'
         AND DATE(e.start_datetime) BETWEEN ? AND ?
         ORDER BY e.start_datetime ASC",
        [$primaryCong['id'], $startDate, $endDate]
    ) ?: [];

    foreach ($congEvents as $event) {
        $event['source'] = $event['scope'] === 'global' ? 'global' : 'congregation';
        $event['color'] = $event['scope'] === 'global' ? '#4F46E5' : '#10B981';
        $allEvents[] = $event;
    }
} catch (Exception $e) {}

try {
    // Personal events
    $personalEvents = Database::fetchAll(
        "SELECT *, 'personal' as source_type
         FROM calendar_events
         WHERE user_id = ?
         AND status = 'active'
         AND DATE(start_datetime) BETWEEN ? AND ?
         ORDER BY start_datetime ASC",
        [$user['id'], $startDate, $endDate]
    ) ?: [];

    foreach ($personalEvents as $event) {
        $event['source'] = 'personal';
        $event['color'] = $event['color'] ?? '#F59E0B';
        $allEvents[] = $event;
    }
} catch (Exception $e) {}

try {
    // Morning study sessions
    $morningSessions = Database::fetchAll(
        "SELECT ms.*, mue.completed_at
         FROM morning_sessions ms
         LEFT JOIN morning_user_entries mue ON ms.id = mue.session_id AND mue.user_id = ?
         WHERE ms.session_date BETWEEN ? AND ?
         ORDER BY ms.session_date ASC",
        [$user['id'], $startDate, $endDate]
    ) ?: [];

    foreach ($morningSessions as $session) {
        $isCompleted = !empty($session['completed_at']);
        $allEvents[] = [
            'id' => 'morning_' . $session['id'],
            'title' => 'Morning Study' . ($session['scripture'] ? ': ' . $session['scripture'] : ''),
            'start_datetime' => $session['session_date'] . ' 06:00:00',
            'end_datetime' => $session['session_date'] . ' 06:30:00',
            'all_day' => false,
            'source' => 'morning_study',
            'color' => $isCompleted ? '#10B981' : '#8B5CF6',
            'completed' => $isCompleted,
            'url' => '/morning_watch/'
        ];
    }
} catch (Exception $e) {}

try {
    // Homecell meetings
    $userHomecell = Database::fetchOne(
        "SELECT h.* FROM homecells h
         JOIN homecell_members hm ON h.id = hm.homecell_id
         WHERE hm.user_id = ? AND hm.status = 'active' AND h.status = 'active'",
        [$user['id']]
    );

    if ($userHomecell) {
        $homecellMeetings = Database::fetchAll(
            "SELECT hm.*, h.name as homecell_name, h.location, h.meeting_time
             FROM homecell_meetings hm
             JOIN homecells h ON hm.homecell_id = h.id
             WHERE hm.homecell_id = ?
             AND hm.meeting_date BETWEEN ? AND ?
             ORDER BY hm.meeting_date ASC",
            [$userHomecell['id'], $startDate, $endDate]
        ) ?: [];

        foreach ($homecellMeetings as $meeting) {
            $meetingTime = $meeting['meeting_time'] ?? '19:00:00';
            $allEvents[] = [
                'id' => 'homecell_' . $meeting['id'],
                'title' => 'Homecell: ' . $meeting['homecell_name'],
                'start_datetime' => $meeting['meeting_date'] . ' ' . $meetingTime,
                'end_datetime' => $meeting['meeting_date'] . ' ' . date('H:i:s', strtotime($meetingTime) + 7200),
                'all_day' => false,
                'location' => $meeting['location'],
                'source' => 'homecell',
                'color' => '#EC4899',
                'url' => '/homecells/view.php?id=' . $meeting['homecell_id']
            ];
        }
    }
} catch (Exception $e) {}

// Organize events by date
foreach ($allEvents as $event) {
    $date = date('Y-m-d', strtotime($event['start_datetime']));
    $eventsByDate[$date][] = $event;

    // Also organize by hour for day/week view
    $hour = (int)date('G', strtotime($event['start_datetime']));
    $eventsByHour[$date][$hour][] = $event;
}

// Sort events
foreach ($eventsByDate as &$dayEvents) {
    usort($dayEvents, fn($a, $b) => strtotime($a['start_datetime']) - strtotime($b['start_datetime']));
}

// Get upcoming events for sidebar
$upcomingEvents = [];
try {
    $upcomingEvents = Database::fetchAll(
        "SELECT e.*, c.name as congregation_name, 'event' as source
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

// Hours for day/week view
$hours = range(0, 23);
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <!-- Calendar Header -->
            <div class="calendar-header">
                <div class="calendar-title">
                    <h1>Calendar</h1>
                    <p>All your events, activities and schedules in one place</p>
                </div>
                <div class="calendar-actions">
                    <a href="/calendar/create.php" class="btn btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Add Event
                    </a>
                </div>
            </div>

            <!-- View Controls -->
            <div class="calendar-controls">
                <div class="controls-left">
                    <!-- Navigation -->
                    <?php if ($view === 'month'): ?>
                        <a href="?view=month&month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="nav-btn" title="Previous Month">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        </a>
                        <a href="?view=month&month=<?= date('n') ?>&year=<?= date('Y') ?>" class="btn btn-today">Today</a>
                        <a href="?view=month&month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="nav-btn" title="Next Month">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </a>
                    <?php elseif ($view === 'week'): ?>
                        <a href="?view=week&year=<?= date('Y', strtotime($prevWeek)) ?>&month=<?= date('n', strtotime($prevWeek)) ?>&day=<?= date('j', strtotime($prevWeek)) ?>" class="nav-btn" title="Previous Week">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        </a>
                        <a href="?view=week&year=<?= date('Y') ?>&month=<?= date('n') ?>&day=<?= date('j') ?>" class="btn btn-today">Today</a>
                        <a href="?view=week&year=<?= date('Y', strtotime($nextWeek)) ?>&month=<?= date('n', strtotime($nextWeek)) ?>&day=<?= date('j', strtotime($nextWeek)) ?>" class="nav-btn" title="Next Week">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </a>
                    <?php else: ?>
                        <a href="?view=day&year=<?= date('Y', strtotime($prevDay)) ?>&month=<?= date('n', strtotime($prevDay)) ?>&day=<?= date('j', strtotime($prevDay)) ?>" class="nav-btn" title="Previous Day">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        </a>
                        <a href="?view=day&year=<?= date('Y') ?>&month=<?= date('n') ?>&day=<?= date('j') ?>" class="btn btn-today">Today</a>
                        <a href="?view=day&year=<?= date('Y', strtotime($nextDay)) ?>&month=<?= date('n', strtotime($nextDay)) ?>&day=<?= date('j', strtotime($nextDay)) ?>" class="nav-btn" title="Next Day">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </a>
                    <?php endif; ?>

                    <!-- Current Date Display -->
                    <h2 class="current-date">
                        <?php if ($view === 'month'): ?>
                            <?= $monthName ?> <?= $year ?>
                        <?php elseif ($view === 'week'): ?>
                            <?= date('M j', strtotime($weekStart)) ?> - <?= date('M j, Y', strtotime($weekEnd)) ?>
                        <?php else: ?>
                            <?= date('l, F j, Y', strtotime($currentDate)) ?>
                        <?php endif; ?>
                    </h2>
                </div>

                <div class="controls-right">
                    <!-- View Switcher -->
                    <div class="view-switcher">
                        <a href="?view=month&month=<?= $month ?>&year=<?= $year ?>" class="view-btn <?= $view === 'month' ? 'active' : '' ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            Month
                        </a>
                        <a href="?view=week&year=<?= $year ?>&month=<?= $month ?>&day=<?= $day ?>" class="view-btn <?= $view === 'week' ? 'active' : '' ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <path d="M3 3h18v18H3zM3 9h18M9 3v18M15 3v18"></path>
                            </svg>
                            Week
                        </a>
                        <a href="?view=day&year=<?= $year ?>&month=<?= $month ?>&day=<?= $day ?>" class="view-btn <?= $view === 'day' ? 'active' : '' ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                                <circle cx="12" cy="15" r="3"></circle>
                            </svg>
                            Day
                        </a>
                    </div>
                </div>
            </div>

            <div class="calendar-layout">
                <!-- Main Calendar Area -->
                <div class="calendar-main">

                    <?php if ($view === 'month'): ?>
                    <!-- ============ MONTH VIEW ============ -->
                    <div class="calendar-grid month-view">
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
                        <?php for ($d = 1; $d <= $daysInMonth; $d++):
                            $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
                            $isToday = $date === $today;
                            $dayEvents = $eventsByDate[$date] ?? [];
                        ?>
                            <div class="calendar-cell <?= $isToday ? 'today' : '' ?> <?= !empty($dayEvents) ? 'has-events' : '' ?>"
                                 onclick="window.location.href='?view=day&year=<?= $year ?>&month=<?= $month ?>&day=<?= $d ?>'">
                                <span class="day-number"><?= $d ?></span>
                                <?php if ($dayEvents): ?>
                                    <div class="cell-events">
                                        <?php foreach (array_slice($dayEvents, 0, 3) as $event): ?>
                                            <div class="event-pill" style="background: <?= e($event['color'] ?? '#4F46E5') ?>">
                                                <?php if (!empty($event['all_day'])): ?>
                                                    <?= e(truncate($event['title'], 12)) ?>
                                                <?php else: ?>
                                                    <span class="event-time"><?= date('g:i', strtotime($event['start_datetime'])) ?></span>
                                                    <?= e(truncate($event['title'], 10)) ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (count($dayEvents) > 3): ?>
                                            <span class="more-events">+<?= count($dayEvents) - 3 ?> more</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>

                        <!-- Fill remaining cells -->
                        <?php
                        $totalCells = $startDayOfWeek + $daysInMonth;
                        $remainingCells = (7 - ($totalCells % 7)) % 7;
                        for ($i = 0; $i < $remainingCells; $i++): ?>
                            <div class="calendar-cell empty"></div>
                        <?php endfor; ?>
                    </div>

                    <?php elseif ($view === 'week'): ?>
                    <!-- ============ WEEK VIEW ============ -->
                    <div class="week-view">
                        <!-- Week Header -->
                        <div class="week-header">
                            <div class="time-gutter"></div>
                            <?php
                            $weekDays = [];
                            for ($i = 0; $i < 7; $i++) {
                                $dayDate = date('Y-m-d', strtotime($weekStart . " +$i days"));
                                $weekDays[] = $dayDate;
                            }
                            foreach ($weekDays as $dayDate):
                                $isToday = $dayDate === $today;
                            ?>
                                <div class="week-day-header <?= $isToday ? 'today' : '' ?>">
                                    <span class="day-name"><?= date('D', strtotime($dayDate)) ?></span>
                                    <span class="day-num <?= $isToday ? 'today-num' : '' ?>"><?= date('j', strtotime($dayDate)) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- All Day Events Row -->
                        <div class="all-day-row">
                            <div class="time-gutter all-day-label">All Day</div>
                            <?php foreach ($weekDays as $dayDate):
                                $dayAllDayEvents = array_filter($eventsByDate[$dayDate] ?? [], fn($e) => !empty($e['all_day']));
                            ?>
                                <div class="all-day-cell">
                                    <?php foreach ($dayAllDayEvents as $event): ?>
                                        <a href="<?= e($event['url'] ?? '/calendar/event.php?id=' . $event['id']) ?>"
                                           class="all-day-event" style="background: <?= e($event['color']) ?>">
                                            <?= e(truncate($event['title'], 15)) ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Time Grid -->
                        <div class="week-grid-container">
                            <div class="week-grid">
                                <?php foreach ($hours as $hour): ?>
                                    <div class="time-row">
                                        <div class="time-gutter">
                                            <span class="time-label"><?= date('g A', mktime($hour, 0, 0)) ?></span>
                                        </div>
                                        <?php foreach ($weekDays as $dayDate):
                                            $hourEvents = $eventsByHour[$dayDate][$hour] ?? [];
                                            $hourEvents = array_filter($hourEvents, fn($e) => empty($e['all_day']));
                                            $isCurrentHour = ($dayDate === $today && $hour === (int)date('G'));
                                        ?>
                                            <div class="time-cell <?= $isCurrentHour ? 'current-hour' : '' ?>">
                                                <?php foreach ($hourEvents as $event):
                                                    $startMin = (int)date('i', strtotime($event['start_datetime']));
                                                    $endTime = $event['end_datetime'] ? strtotime($event['end_datetime']) : strtotime($event['start_datetime']) + 3600;
                                                    $duration = ($endTime - strtotime($event['start_datetime'])) / 60;
                                                    $height = max(25, min(200, $duration * 0.8));
                                                    $top = $startMin * 0.8;
                                                ?>
                                                    <a href="<?= e($event['url'] ?? '/calendar/event.php?id=' . $event['id']) ?>"
                                                       class="week-event"
                                                       style="background: <?= e($event['color']) ?>; top: <?= $top ?>px; min-height: <?= $height ?>px;">
                                                        <span class="event-time"><?= date('g:i', strtotime($event['start_datetime'])) ?></span>
                                                        <span class="event-title"><?= e(truncate($event['title'], 20)) ?></span>
                                                        <?php if (!empty($event['location'])): ?>
                                                            <span class="event-location"><?= e(truncate($event['location'], 15)) ?></span>
                                                        <?php endif; ?>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>

                                <!-- Current time indicator -->
                                <?php if ($today >= $weekStart && $today <= $weekEnd):
                                    $currentHour = (int)date('G');
                                    $currentMin = (int)date('i');
                                    $topPos = ($currentHour * 48) + ($currentMin * 0.8);
                                    $dayIndex = array_search($today, $weekDays);
                                ?>
                                    <div class="current-time-line" style="top: <?= $topPos ?>px; left: calc(60px + (<?= $dayIndex ?> * (100% - 60px) / 7));">
                                        <div class="current-time-dot"></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php else: ?>
                    <!-- ============ DAY VIEW ============ -->
                    <div class="day-view">
                        <!-- All Day Events -->
                        <?php $allDayEvents = array_filter($eventsByDate[$currentDate] ?? [], fn($e) => !empty($e['all_day'])); ?>
                        <?php if ($allDayEvents): ?>
                            <div class="day-all-day-section">
                                <div class="time-gutter">All Day</div>
                                <div class="all-day-events">
                                    <?php foreach ($allDayEvents as $event): ?>
                                        <a href="<?= e($event['url'] ?? '/calendar/event.php?id=' . $event['id']) ?>"
                                           class="day-all-day-event" style="background: <?= e($event['color']) ?>">
                                            <span class="event-title"><?= e($event['title']) ?></span>
                                            <?php if (!empty($event['location'])): ?>
                                                <span class="event-location"><?= e($event['location']) ?></span>
                                            <?php endif; ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Hour Grid -->
                        <div class="day-grid-container">
                            <div class="day-grid">
                                <?php foreach ($hours as $hour):
                                    $hourEvents = $eventsByHour[$currentDate][$hour] ?? [];
                                    $hourEvents = array_filter($hourEvents, fn($e) => empty($e['all_day']));
                                    $isCurrentHour = ($currentDate === $today && $hour === (int)date('G'));
                                ?>
                                    <div class="day-time-row <?= $isCurrentHour ? 'current-hour' : '' ?>">
                                        <div class="time-gutter">
                                            <span class="time-label"><?= date('g:i A', mktime($hour, 0, 0)) ?></span>
                                        </div>
                                        <div class="day-time-cell">
                                            <?php foreach ($hourEvents as $event):
                                                $startMin = (int)date('i', strtotime($event['start_datetime']));
                                                $endTime = $event['end_datetime'] ? strtotime($event['end_datetime']) : strtotime($event['start_datetime']) + 3600;
                                                $duration = ($endTime - strtotime($event['start_datetime'])) / 60;
                                                $height = max(40, min(300, $duration * 1.0));
                                                $top = $startMin * 1.0;
                                            ?>
                                                <a href="<?= e($event['url'] ?? '/calendar/event.php?id=' . $event['id']) ?>"
                                                   class="day-event"
                                                   style="background: <?= e($event['color']) ?>; top: <?= $top ?>px; min-height: <?= $height ?>px;">
                                                    <div class="day-event-content">
                                                        <div class="day-event-header">
                                                            <span class="event-time">
                                                                <?= date('g:i A', strtotime($event['start_datetime'])) ?>
                                                                <?php if ($event['end_datetime']): ?>
                                                                    - <?= date('g:i A', strtotime($event['end_datetime'])) ?>
                                                                <?php endif; ?>
                                                            </span>
                                                            <span class="event-source source-<?= $event['source'] ?>">
                                                                <?= ucfirst(str_replace('_', ' ', $event['source'])) ?>
                                                            </span>
                                                        </div>
                                                        <span class="event-title"><?= e($event['title']) ?></span>
                                                        <?php if (!empty($event['location'])): ?>
                                                            <span class="event-location">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12">
                                                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                                                    <circle cx="12" cy="10" r="3"></circle>
                                                                </svg>
                                                                <?= e($event['location']) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($event['description'])): ?>
                                                            <span class="event-desc"><?= e(truncate($event['description'], 100)) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <!-- Current time indicator -->
                                <?php if ($currentDate === $today):
                                    $currentHour = (int)date('G');
                                    $currentMin = (int)date('i');
                                    $topPos = ($currentHour * 60) + $currentMin;
                                ?>
                                    <div class="current-time-line day-view-time" style="top: <?= $topPos ?>px;">
                                        <div class="current-time-dot"></div>
                                        <span class="current-time-text"><?= date('g:i A') ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Day Summary (when no events) -->
                        <?php if (empty($eventsByDate[$currentDate])): ?>
                            <div class="day-empty">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                <h3>No events scheduled</h3>
                                <p>This day is clear! <a href="/calendar/create.php?date=<?= $currentDate ?>">Add an event</a></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="calendar-sidebar">
                    <!-- Mini Calendar -->
                    <div class="mini-calendar">
                        <div class="mini-calendar-header">
                            <a href="?view=<?= $view ?>&month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="mini-nav">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="15 18 9 12 15 6"></polyline></svg>
                            </a>
                            <span><?= date('M Y', $firstDay) ?></span>
                            <a href="?view=<?= $view ?>&month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="mini-nav">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="9 18 15 12 9 6"></polyline></svg>
                            </a>
                        </div>
                        <div class="mini-calendar-grid">
                            <span class="mini-day-header">S</span>
                            <span class="mini-day-header">M</span>
                            <span class="mini-day-header">T</span>
                            <span class="mini-day-header">W</span>
                            <span class="mini-day-header">T</span>
                            <span class="mini-day-header">F</span>
                            <span class="mini-day-header">S</span>
                            <?php for ($i = 0; $i < $startDayOfWeek; $i++): ?>
                                <span class="mini-day empty"></span>
                            <?php endfor; ?>
                            <?php for ($d = 1; $d <= $daysInMonth; $d++):
                                $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
                                $isToday = $date === $today;
                                $isSelected = ($view === 'day' && $date === $currentDate);
                                $hasEvents = !empty($eventsByDate[$date]);
                            ?>
                                <a href="?view=day&year=<?= $year ?>&month=<?= $month ?>&day=<?= $d ?>"
                                   class="mini-day <?= $isToday ? 'today' : '' ?> <?= $isSelected ? 'selected' : '' ?> <?= $hasEvents ? 'has-events' : '' ?>">
                                    <?= $d ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Upcoming Events -->
                    <div class="upcoming-section">
                        <h3>Upcoming Events</h3>
                        <?php if ($upcomingEvents): ?>
                            <div class="upcoming-list">
                                <?php foreach ($upcomingEvents as $event): ?>
                                    <a href="/calendar/event.php?id=<?= $event['id'] ?>" class="upcoming-item">
                                        <div class="event-date-badge">
                                            <span class="day"><?= date('d', strtotime($event['start_datetime'])) ?></span>
                                            <span class="month"><?= date('M', strtotime($event['start_datetime'])) ?></span>
                                        </div>
                                        <div class="event-info">
                                            <h4><?= e(truncate($event['title'], 25)) ?></h4>
                                            <p><?= date('g:i A', strtotime($event['start_datetime'])) ?></p>
                                            <?php if ($event['location']): ?>
                                                <p class="location"><?= e(truncate($event['location'], 20)) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-upcoming">
                                <p>No upcoming events</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Event Sources Legend -->
                    <div class="legend-section">
                        <h4>Event Sources</h4>
                        <div class="legend-items">
                            <label class="legend-item">
                                <span class="legend-dot" style="background: #4F46E5;"></span>
                                <span>Global Events</span>
                            </label>
                            <label class="legend-item">
                                <span class="legend-dot" style="background: #10B981;"></span>
                                <span>Congregation</span>
                            </label>
                            <label class="legend-item">
                                <span class="legend-dot" style="background: #F59E0B;"></span>
                                <span>Personal</span>
                            </label>
                            <label class="legend-item">
                                <span class="legend-dot" style="background: #8B5CF6;"></span>
                                <span>Morning Study</span>
                            </label>
                            <label class="legend-item">
                                <span class="legend-dot" style="background: #EC4899;"></span>
                                <span>Homecell</span>
                            </label>
                            <label class="legend-item">
                                <span class="legend-dot" style="background: #06B6D4;"></span>
                                <span>Courses</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="/calendar/js/calendar.js"></script>
</body>
</html>
