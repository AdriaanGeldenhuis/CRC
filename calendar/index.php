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

// Get notification count
$unreadNotifications = 0;
try {
    $unreadNotifications = Database::fetchColumn(
        "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL",
        [$user['id']]
    ) ?: 0;
} catch (Exception $e) {}

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
        $event['color'] = $event['scope'] === 'global' ? '#7C3AED' : '#10B981';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <?= CSRF::meta() ?>
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="/home/css/home.css?v=<?= filemtime(__DIR__ . '/../home/css/home.css') ?>">
    <link rel="stylesheet" href="/gospel_media/css/gospel_media.css?v=<?= filemtime(__DIR__ . '/../gospel_media/css/gospel_media.css') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        (function() {
            const saved = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', saved);
        })();
    </script>
    <style>
        /* Calendar Specific Styles */
        .calendar-page {
            padding-top: 0;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 0;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .calendar-title h1 {
            font-size: 1.75rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }

        .calendar-title p {
            color: var(--muted);
            font-size: 0.9rem;
            margin: 0.25rem 0 0;
        }

        .btn-add-event {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(124, 58, 237, 0.3);
            transition: all 0.2s ease;
        }

        .btn-add-event:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(124, 58, 237, 0.4);
        }

        .btn-add-event svg {
            width: 18px;
            height: 18px;
        }

        /* Controls */
        .calendar-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .controls-left {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-btn {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--btn-bg);
            border: 1px solid var(--line);
            border-radius: 10px;
            color: var(--muted);
            text-decoration: none;
            transition: all 0.12s ease;
        }

        .nav-btn:hover {
            background: var(--btn-bg-hover);
            color: var(--text);
        }

        .nav-btn svg {
            width: 18px;
            height: 18px;
        }

        .btn-today {
            padding: 0.5rem 1rem;
            background: var(--btn-bg);
            border: 1px solid var(--line);
            border-radius: 10px;
            color: var(--text);
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.12s ease;
        }

        .btn-today:hover {
            background: var(--btn-bg-hover);
            border-color: var(--accent);
        }

        .current-date {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text);
            margin: 0 0.5rem;
        }

        .view-switcher {
            display: flex;
            background: var(--card2);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 4px;
            gap: 4px;
        }

        .view-btn {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.5rem 1rem;
            background: transparent;
            border: none;
            border-radius: 8px;
            color: var(--muted);
            font-weight: 600;
            font-size: 0.8rem;
            text-decoration: none;
            transition: all 0.12s ease;
        }

        .view-btn:hover {
            color: var(--text);
            background: var(--btn-bg);
        }

        .view-btn.active {
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: white;
        }

        .view-btn svg {
            width: 16px;
            height: 16px;
        }

        /* Calendar Layout */
        .calendar-layout {
            display: grid;
            grid-template-columns: 1fr 280px;
            gap: 1rem;
        }

        @media (max-width: 900px) {
            .calendar-layout {
                grid-template-columns: 1fr;
            }
            .calendar-sidebar {
                order: -1;
            }
        }

        /* Month View Grid */
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            overflow: hidden;
        }

        .day-header {
            padding: 0.75rem;
            text-align: center;
            font-weight: 700;
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--muted);
            background: var(--card2);
            border-bottom: 1px solid var(--line);
        }

        .calendar-cell {
            min-height: 100px;
            padding: 0.5rem;
            border-right: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
            cursor: pointer;
            transition: background 0.12s ease;
        }

        .calendar-cell:nth-child(7n) {
            border-right: none;
        }

        .calendar-cell:hover {
            background: var(--card2);
        }

        .calendar-cell.empty {
            background: var(--bg0);
            cursor: default;
        }

        .calendar-cell.today {
            background: rgba(124, 58, 237, 0.1);
        }

        .calendar-cell.today .day-number {
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: white;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .day-number {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text);
            margin-bottom: 0.25rem;
        }

        .cell-events {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .event-pill {
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 4px;
            color: white;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: 500;
        }

        .event-pill .event-time {
            opacity: 0.8;
            margin-right: 4px;
        }

        .more-events {
            font-size: 0.7rem;
            color: var(--muted);
            font-weight: 500;
        }

        /* Sidebar */
        .calendar-sidebar {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .upcoming-section,
        .legend-section {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 1rem;
        }

        .upcoming-section h3,
        .legend-section h4 {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text);
            margin: 0 0 1rem;
        }

        .upcoming-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .upcoming-item {
            display: flex;
            gap: 0.75rem;
            padding: 0.75rem;
            background: var(--card2);
            border: 1px solid var(--line);
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.12s ease;
        }

        .upcoming-item:hover {
            border-color: var(--accent);
            background: var(--card);
        }

        .event-date-badge {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-width: 45px;
            padding: 0.5rem;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border-radius: 8px;
            color: white;
        }

        .event-date-badge .day {
            font-size: 1.1rem;
            font-weight: 700;
            line-height: 1;
        }

        .event-date-badge .month {
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            opacity: 0.9;
        }

        .event-info h4 {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text);
            margin: 0 0 0.25rem;
        }

        .event-info p {
            font-size: 0.75rem;
            color: var(--muted);
            margin: 0;
        }

        .event-info .location {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            margin-top: 0.25rem;
        }

        .no-upcoming {
            text-align: center;
            padding: 1rem;
            color: var(--muted);
            font-size: 0.85rem;
        }

        .legend-items {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            color: var(--muted);
        }

        .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        /* Week View */
        .week-view-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }

        .week-day-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 1rem;
            cursor: pointer;
            transition: all 0.12s ease;
        }

        .week-day-card:hover {
            border-color: var(--accent);
        }

        .week-day-card.today {
            border-color: var(--accent);
            background: rgba(124, 58, 237, 0.1);
        }

        .week-day-card.sunday-card {
            grid-column: span 3;
        }

        .week-day-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .week-day-card-header .day-name {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--muted);
        }

        .week-day-card-header .day-num {
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--text);
        }

        .week-day-card-header .today-num {
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .week-day-card-events {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .week-card-event {
            padding: 0.5rem;
            background: var(--card2);
            border-left: 3px solid var(--accent);
            border-radius: 0 6px 6px 0;
        }

        .week-card-event .event-time {
            font-size: 0.7rem;
            color: var(--muted);
            display: block;
        }

        .week-card-event .event-title {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text);
        }

        .no-events {
            font-size: 0.8rem;
            color: var(--muted2);
            text-align: center;
            padding: 1rem 0;
        }

        /* Day View */
        .day-view {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            overflow: hidden;
        }

        .day-grid-container {
            max-height: 600px;
            overflow-y: auto;
        }

        .day-time-row {
            display: flex;
            border-bottom: 1px solid var(--line);
            min-height: 60px;
        }

        .day-time-row.current-hour {
            background: rgba(124, 58, 237, 0.05);
        }

        .time-gutter {
            width: 70px;
            padding: 0.5rem;
            font-size: 0.75rem;
            color: var(--muted);
            border-right: 1px solid var(--line);
            text-align: right;
            flex-shrink: 0;
        }

        .day-time-cell {
            flex: 1;
            position: relative;
            min-height: 60px;
        }

        .day-event {
            position: absolute;
            left: 4px;
            right: 4px;
            padding: 0.5rem;
            border-radius: 6px;
            color: white;
            text-decoration: none;
            font-size: 0.8rem;
            overflow: hidden;
        }

        .day-event-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.25rem;
        }

        .day-event .event-time {
            font-size: 0.7rem;
            opacity: 0.9;
        }

        .day-event .event-title {
            font-weight: 600;
            display: block;
        }

        .day-event .event-location {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.7rem;
            opacity: 0.9;
            margin-top: 0.25rem;
        }

        .day-empty {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--muted);
        }

        .day-empty svg {
            opacity: 0.5;
            margin-bottom: 1rem;
        }

        .day-empty h3 {
            color: var(--text);
            margin-bottom: 0.5rem;
        }

        .day-empty a {
            color: var(--accent);
        }

        /* All Day Events */
        .day-all-day-section {
            display: flex;
            border-bottom: 1px solid var(--line);
            background: var(--card2);
        }

        .all-day-events {
            flex: 1;
            padding: 0.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .day-all-day-event {
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            color: white;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* Mobile Responsive */
        @media (max-width: 640px) {
            .calendar-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .calendar-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .controls-left,
            .controls-right {
                justify-content: center;
            }

            .current-date {
                font-size: 1rem;
            }

            .view-switcher {
                justify-content: center;
            }

            .view-btn span {
                display: none;
            }

            .calendar-cell {
                min-height: 70px;
                padding: 0.25rem;
            }

            .day-number {
                font-size: 0.8rem;
            }

            .event-pill {
                font-size: 0.6rem;
                padding: 1px 4px;
            }

            .week-view-grid {
                grid-template-columns: 1fr;
            }

            .week-day-card.sunday-card {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body data-theme="dark">
    <!-- Top Bar / Navigation -->
    <div class="topbar">
        <div class="inner">
            <div class="brand">
                <div class="logo" aria-hidden="true"></div>
                <div>
                    <h1>CRC</h1>
                    <span><?= e($primaryCong['name'] ?? 'Calendar') ?></span>
                </div>
            </div>

            <div class="actions">
                <div class="chip" title="Status">
                    <span class="dot"></span>
                    <?= e(explode(' ', $user['name'])[0]) ?>
                </div>

                <button class="theme-toggle" onclick="toggleTheme()" title="Toggle theme" data-ripple>
                    <svg class="sun-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 3v2m0 14v2M3 12h2m14 0h2M5.2 5.2l1.4 1.4m10.8 10.8l1.4 1.4M18.8 5.2l-1.4 1.4M6.6 17.4l-1.4 1.4"></path>
                        <circle cx="12" cy="12" r="5"></circle>
                    </svg>
                    <svg class="moon-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                    </svg>
                </button>

                <a href="/notifications/" class="nav-icon-btn" title="Notifications" data-ripple>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="notification-badge"><?= $unreadNotifications > 9 ? '9+' : $unreadNotifications ?></span>
                    <?php endif; ?>
                </a>

                <div class="more-menu">
                    <button class="more-menu-btn" onclick="toggleMoreMenu()" title="More" data-ripple>
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                            <circle cx="12" cy="5" r="2"></circle>
                            <circle cx="12" cy="12" r="2"></circle>
                            <circle cx="12" cy="19" r="2"></circle>
                        </svg>
                    </button>
                    <div class="more-dropdown" id="moreDropdown">
                        <a href="/home/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                <polyline points="9 22 9 12 15 12 15 22"></polyline>
                            </svg>
                            Home
                        </a>
                        <a href="/gospel_media/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 11a9 9 0 0 1 9 9"></path>
                                <path d="M4 4a16 16 0 0 1 16 16"></path>
                                <circle cx="5" cy="19" r="1"></circle>
                            </svg>
                            Feed
                        </a>
                        <a href="/bible/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                                <path d="M12 6v7"></path>
                                <path d="M8 9h8"></path>
                            </svg>
                            Bible
                        </a>
                        <a href="/ai_smartbible/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                <circle cx="19" cy="5" r="3" fill="currentColor"></circle>
                            </svg>
                            AI SmartBible
                        </a>
                        <a href="/morning_watch/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="5"></circle>
                                <line x1="12" y1="1" x2="12" y2="3"></line>
                                <line x1="12" y1="21" x2="12" y2="23"></line>
                                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                                <line x1="1" y1="12" x2="3" y2="12"></line>
                                <line x1="21" y1="12" x2="23" y2="12"></line>
                                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                            </svg>
                            Morning Study
                        </a>
                        <a href="/calendar/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            Calendar
                        </a>
                        <a href="/media/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="23 7 16 12 23 17 23 7"></polygon>
                                <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
                            </svg>
                            Media
                        </a>
                        <div class="more-dropdown-divider"></div>
                        <a href="/diary/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                            </svg>
                            My Diary
                        </a>
                        <a href="/homecells/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                            Homecells
                        </a>
                        <a href="/learning/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                                <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                            </svg>
                            Courses
                        </a>
                    </div>
                </div>

                <div class="user-menu">
                    <button class="user-menu-btn" onclick="toggleUserMenu()">
                        <?php if ($user['avatar']): ?>
                            <img src="<?= e($user['avatar']) ?>" alt="" class="user-avatar">
                        <?php else: ?>
                            <div class="user-avatar-placeholder"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
                        <?php endif; ?>
                    </button>
                    <div class="user-dropdown" id="userDropdown">
                        <div class="user-dropdown-header">
                            <strong><?= e($user['name']) ?></strong>
                            <span><?= e($primaryCong['name'] ?? '') ?></span>
                        </div>
                        <div class="user-dropdown-divider"></div>
                        <a href="/profile/" class="user-dropdown-item">Profile</a>
                        <?php if ($primaryCong && Auth::isCongregationAdmin($primaryCong['id'])): ?>
                            <div class="user-dropdown-divider"></div>
                            <a href="/admin_congregation/" class="user-dropdown-item">Manage Congregation</a>
                        <?php endif; ?>
                        <?php if (Auth::isAdmin()): ?>
                            <a href="/admin/" class="user-dropdown-item">Admin Panel</a>
                        <?php endif; ?>
                        <div class="user-dropdown-divider"></div>
                        <a href="/auth/logout.php" class="user-dropdown-item logout">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Feed Filter Tabs -->
    <nav class="feed-tabs">
        <a href="/gospel_media/" class="feed-tab">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 11a9 9 0 0 1 9 9"></path>
                <path d="M4 4a16 16 0 0 1 16 16"></path>
                <circle cx="5" cy="19" r="1"></circle>
            </svg>
            <span>Feed</span>
        </a>
        <a href="/gospel_media/groups.php" class="feed-tab">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <span>Groups</span>
        </a>
        <a href="/calendar/" class="feed-tab active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <span>Events</span>
        </a>
    </nav>

    <main class="feed-container calendar-page">
        <!-- Calendar Header -->
        <div class="calendar-header">
            <div class="calendar-title">
                <h1>Calendar</h1>
                <p>All your events, activities and schedules in one place</p>
            </div>
            <a href="/calendar/create.php" class="btn-add-event">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Add Event
            </a>
        </div>

        <!-- View Controls -->
        <div class="calendar-controls">
            <div class="controls-left">
                <?php if ($view === 'month'): ?>
                    <a href="?view=month&month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="nav-btn" title="Previous Month">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    </a>
                    <a href="?view=month&month=<?= date('n') ?>&year=<?= date('Y') ?>" class="btn-today">Today</a>
                    <a href="?view=month&month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="nav-btn" title="Next Month">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </a>
                <?php elseif ($view === 'week'): ?>
                    <a href="?view=week&year=<?= date('Y', strtotime($prevWeek)) ?>&month=<?= date('n', strtotime($prevWeek)) ?>&day=<?= date('j', strtotime($prevWeek)) ?>" class="nav-btn" title="Previous Week">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    </a>
                    <a href="?view=week&year=<?= date('Y') ?>&month=<?= date('n') ?>&day=<?= date('j') ?>" class="btn-today">Today</a>
                    <a href="?view=week&year=<?= date('Y', strtotime($nextWeek)) ?>&month=<?= date('n', strtotime($nextWeek)) ?>&day=<?= date('j', strtotime($nextWeek)) ?>" class="nav-btn" title="Next Week">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </a>
                <?php else: ?>
                    <a href="?view=day&year=<?= date('Y', strtotime($prevDay)) ?>&month=<?= date('n', strtotime($prevDay)) ?>&day=<?= date('j', strtotime($prevDay)) ?>" class="nav-btn" title="Previous Day">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    </a>
                    <a href="?view=day&year=<?= date('Y') ?>&month=<?= date('n') ?>&day=<?= date('j') ?>" class="btn-today">Today</a>
                    <a href="?view=day&year=<?= date('Y', strtotime($nextDay)) ?>&month=<?= date('n', strtotime($nextDay)) ?>&day=<?= date('j', strtotime($nextDay)) ?>" class="nav-btn" title="Next Day">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </a>
                <?php endif; ?>

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
                <div class="view-switcher">
                    <a href="?view=month&month=<?= $month ?>&year=<?= $year ?>" class="view-btn <?= $view === 'month' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        <span>Month</span>
                    </a>
                    <a href="?view=week&year=<?= $year ?>&month=<?= $month ?>&day=<?= $day ?>" class="view-btn <?= $view === 'week' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 3h18v18H3zM3 9h18M9 3v18M15 3v18"></path>
                        </svg>
                        <span>Week</span>
                    </a>
                    <a href="?view=day&year=<?= $year ?>&month=<?= $month ?>&day=<?= $day ?>" class="view-btn <?= $view === 'day' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        <span>Day</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="calendar-layout">
            <!-- Main Calendar Area -->
            <div class="calendar-main">
                <?php if ($view === 'month'): ?>
                <!-- MONTH VIEW -->
                <div class="calendar-grid month-view">
                    <div class="day-header">Sun</div>
                    <div class="day-header">Mon</div>
                    <div class="day-header">Tue</div>
                    <div class="day-header">Wed</div>
                    <div class="day-header">Thu</div>
                    <div class="day-header">Fri</div>
                    <div class="day-header">Sat</div>

                    <?php for ($i = 0; $i < $startDayOfWeek; $i++): ?>
                        <div class="calendar-cell empty"></div>
                    <?php endfor; ?>

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
                                        <div class="event-pill" style="background: <?= e($event['color'] ?? '#7C3AED') ?>">
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

                    <?php
                    $totalCells = $startDayOfWeek + $daysInMonth;
                    $remainingCells = (7 - ($totalCells % 7)) % 7;
                    for ($i = 0; $i < $remainingCells; $i++): ?>
                        <div class="calendar-cell empty"></div>
                    <?php endfor; ?>
                </div>

                <?php elseif ($view === 'week'): ?>
                <!-- WEEK VIEW -->
                <?php
                $weekDays = [];
                for ($i = 0; $i < 7; $i++) {
                    $dayDate = date('Y-m-d', strtotime($weekStart . " +$i days"));
                    $weekDays[] = $dayDate;
                }
                ?>
                <div class="week-view-grid">
                    <?php for ($i = 0; $i < 3; $i++):
                        $dayDate = $weekDays[$i];
                        $isToday = $dayDate === $today;
                        $dayEvents = $eventsByDate[$dayDate] ?? [];
                    ?>
                        <div class="week-day-card <?= $isToday ? 'today' : '' ?>"
                             onclick="window.location.href='?view=day&year=<?= date('Y', strtotime($dayDate)) ?>&month=<?= date('n', strtotime($dayDate)) ?>&day=<?= date('j', strtotime($dayDate)) ?>'">
                            <div class="week-day-card-header">
                                <span class="day-name"><?= date('l', strtotime($dayDate)) ?></span>
                                <span class="day-num <?= $isToday ? 'today-num' : '' ?>"><?= date('j', strtotime($dayDate)) ?></span>
                            </div>
                            <div class="week-day-card-events">
                                <?php if ($dayEvents): ?>
                                    <?php foreach (array_slice($dayEvents, 0, 4) as $event): ?>
                                        <div class="week-card-event" style="border-left-color: <?= e($event['color'] ?? '#7C3AED') ?>">
                                            <span class="event-time"><?= date('g:i A', strtotime($event['start_datetime'])) ?></span>
                                            <span class="event-title"><?= e(truncate($event['title'], 25)) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($dayEvents) > 4): ?>
                                        <span class="more-events">+<?= count($dayEvents) - 4 ?> more</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="no-events">No events</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endfor; ?>

                    <?php for ($i = 3; $i < 6; $i++):
                        $dayDate = $weekDays[$i];
                        $isToday = $dayDate === $today;
                        $dayEvents = $eventsByDate[$dayDate] ?? [];
                    ?>
                        <div class="week-day-card <?= $isToday ? 'today' : '' ?>"
                             onclick="window.location.href='?view=day&year=<?= date('Y', strtotime($dayDate)) ?>&month=<?= date('n', strtotime($dayDate)) ?>&day=<?= date('j', strtotime($dayDate)) ?>'">
                            <div class="week-day-card-header">
                                <span class="day-name"><?= date('l', strtotime($dayDate)) ?></span>
                                <span class="day-num <?= $isToday ? 'today-num' : '' ?>"><?= date('j', strtotime($dayDate)) ?></span>
                            </div>
                            <div class="week-day-card-events">
                                <?php if ($dayEvents): ?>
                                    <?php foreach (array_slice($dayEvents, 0, 4) as $event): ?>
                                        <div class="week-card-event" style="border-left-color: <?= e($event['color'] ?? '#7C3AED') ?>">
                                            <span class="event-time"><?= date('g:i A', strtotime($event['start_datetime'])) ?></span>
                                            <span class="event-title"><?= e(truncate($event['title'], 25)) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($dayEvents) > 4): ?>
                                        <span class="more-events">+<?= count($dayEvents) - 4 ?> more</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="no-events">No events</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endfor; ?>

                    <?php
                    $dayDate = $weekDays[6];
                    $isToday = $dayDate === $today;
                    $dayEvents = $eventsByDate[$dayDate] ?? [];
                    ?>
                    <div class="week-day-card sunday-card <?= $isToday ? 'today' : '' ?>"
                         onclick="window.location.href='?view=day&year=<?= date('Y', strtotime($dayDate)) ?>&month=<?= date('n', strtotime($dayDate)) ?>&day=<?= date('j', strtotime($dayDate)) ?>'">
                        <div class="week-day-card-header">
                            <span class="day-name"><?= date('l', strtotime($dayDate)) ?></span>
                            <span class="day-num <?= $isToday ? 'today-num' : '' ?>"><?= date('j', strtotime($dayDate)) ?></span>
                        </div>
                        <div class="week-day-card-events sunday-events">
                            <?php if ($dayEvents): ?>
                                <?php foreach (array_slice($dayEvents, 0, 6) as $event): ?>
                                    <div class="week-card-event" style="border-left-color: <?= e($event['color'] ?? '#7C3AED') ?>">
                                        <span class="event-time"><?= date('g:i A', strtotime($event['start_datetime'])) ?></span>
                                        <span class="event-title"><?= e(truncate($event['title'], 40)) ?></span>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (count($dayEvents) > 6): ?>
                                    <span class="more-events">+<?= count($dayEvents) - 6 ?> more</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="no-events">No events scheduled</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php else: ?>
                <!-- DAY VIEW -->
                <div class="day-view">
                    <?php $allDayEvents = array_filter($eventsByDate[$currentDate] ?? [], fn($e) => !empty($e['all_day'])); ?>
                    <?php if ($allDayEvents): ?>
                        <div class="day-all-day-section">
                            <div class="time-gutter">All Day</div>
                            <div class="all-day-events">
                                <?php foreach ($allDayEvents as $event): ?>
                                    <a href="<?= e($event['url'] ?? '/calendar/event.php?id=' . $event['id']) ?>"
                                       class="day-all-day-event" style="background: <?= e($event['color']) ?>">
                                        <span class="event-title"><?= e($event['title']) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

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
                                                    <span class="event-time">
                                                        <?= date('g:i A', strtotime($event['start_datetime'])) ?>
                                                        <?php if ($event['end_datetime']): ?>
                                                            - <?= date('g:i A', strtotime($event['end_datetime'])) ?>
                                                        <?php endif; ?>
                                                    </span>
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
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

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

                <div class="legend-section">
                    <h4>Event Sources</h4>
                    <div class="legend-items">
                        <label class="legend-item">
                            <span class="legend-dot" style="background: #7C3AED;"></span>
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
                            <span class="legend-dot" style="background: #22D3EE;"></span>
                            <span>Courses</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Theme Toggle
        function toggleTheme() {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme') || 'dark';
            const next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
        }

        // More Menu Toggle
        function toggleMoreMenu() {
            document.getElementById('moreDropdown').classList.toggle('show');
            document.getElementById('userDropdown')?.classList.remove('show');
        }

        // User Menu Toggle
        function toggleUserMenu() {
            document.getElementById('userDropdown').classList.toggle('show');
            document.getElementById('moreDropdown')?.classList.remove('show');
        }

        // Close menus when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.user-menu')) {
                document.getElementById('userDropdown')?.classList.remove('show');
            }
            if (!e.target.closest('.more-menu')) {
                document.getElementById('moreDropdown')?.classList.remove('show');
            }
        });
    </script>
</body>
</html>
