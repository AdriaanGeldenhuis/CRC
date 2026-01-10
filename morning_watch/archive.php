<?php
/**
 * CRC Morning Watch - Archive
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
$pageTitle = "Morning Watch Archive - CRC";

// Get month/year for filter
$month = (int)($_GET['month'] ?? date('n'));
$year = (int)($_GET['year'] ?? date('Y'));

// Validate
if ($month < 1 || $month > 12) $month = date('n');
if ($year < 2020 || $year > 2030) $year = date('Y');

$startDate = sprintf('%04d-%02d-01', $year, $month);
$endDate = date('Y-m-t', strtotime($startDate));

// Get sessions for this month
$sessions = Database::fetchAll(
    "SELECT ms.*, u.name as author_name,
            (SELECT COUNT(*) FROM morning_watch_entries WHERE session_id = ms.id) as entry_count
     FROM morning_sessions ms
     LEFT JOIN users u ON ms.created_by = u.id
     WHERE ms.session_date BETWEEN ? AND ?
     AND (ms.scope = 'global' OR ms.congregation_id = ?)
     AND ms.published_at IS NOT NULL
     ORDER BY ms.session_date DESC",
    [$startDate, $endDate, $primaryCong['id'] ?? 0]
);

// Get user's completed entries
$userEntries = Database::fetchAll(
    "SELECT session_id FROM morning_watch_entries
     WHERE user_id = ?
     AND session_id IN (SELECT id FROM morning_sessions WHERE session_date BETWEEN ? AND ?)",
    [$user['id'], $startDate, $endDate]
);
$completedSessions = array_column($userEntries, 'session_id');

$monthName = date('F', strtotime($startDate));

// Navigation
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/morning_watch/css/morning_watch.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <!-- Header -->
            <div class="mw-header">
                <div class="mw-title">
                    <a href="/morning_watch/" class="back-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                        Back to Today
                    </a>
                    <h1>Archive</h1>
                </div>
            </div>

            <!-- Month Navigation -->
            <div class="archive-nav">
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

            <!-- Sessions List -->
            <?php if ($sessions): ?>
                <div class="archive-list">
                    <?php foreach ($sessions as $session):
                        $isCompleted = in_array($session['id'], $completedSessions);
                        $isToday = $session['session_date'] === date('Y-m-d');
                    ?>
                        <a href="/morning_watch/view.php?id=<?= $session['id'] ?>" class="archive-item <?= $isCompleted ? 'completed' : '' ?> <?= $isToday ? 'today' : '' ?>">
                            <div class="archive-date">
                                <span class="day"><?= date('d', strtotime($session['session_date'])) ?></span>
                                <span class="weekday"><?= date('D', strtotime($session['session_date'])) ?></span>
                            </div>
                            <div class="archive-content">
                                <h3><?= e($session['title']) ?></h3>
                                <p class="archive-scripture"><?= e($session['scripture_ref']) ?></p>
                                <?php if ($session['theme']): ?>
                                    <span class="archive-theme"><?= e($session['theme']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="archive-status">
                                <?php if ($isCompleted): ?>
                                    <span class="status-completed">✓</span>
                                <?php elseif ($isToday): ?>
                                    <span class="status-today">Today</span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📅</div>
                    <h2>No devotionals this month</h2>
                    <p>There are no morning watch sessions for <?= $monthName ?> <?= $year ?>.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
