<?php
/**
 * CRC Calendar - Dashboard Layout
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$primaryCong = Auth::primaryCongregation();
if (!$primaryCong) {
    Response::redirect('/onboarding/');
}

$user = Auth::user();
$pageTitle = 'Calendar - CRC';

// Get today's events
$todayEvents = [];
try {
    $todayEvents = Database::fetchAll(
        "SELECT * FROM events WHERE (scope = 'global' OR congregation_id = ?) AND status = 'published' AND DATE(start_datetime) = CURDATE() ORDER BY start_datetime ASC LIMIT 5",
        [$primaryCong['id']]
    ) ?: [];
} catch (Exception $e) {}

// Get upcoming events
$upcomingEvents = [];
try {
    $upcomingEvents = Database::fetchAll(
        "SELECT * FROM events WHERE (scope = 'global' OR congregation_id = ?) AND status = 'published' AND start_datetime > NOW() ORDER BY start_datetime ASC LIMIT 5",
        [$primaryCong['id']]
    ) ?: [];
} catch (Exception $e) {}

$eventCount = count($todayEvents);
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
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <section class="welcome-section">
                <div class="welcome-content">
                    <h1>Calendar</h1>
                    <p><?= date('l, j F Y') ?></p>
                </div>
            </section>

            <div class="dashboard-grid">
                <!-- Today Card (Featured) -->
                <div class="dashboard-card morning-watch-card">
                    <div class="card-header">
                        <h2>Today</h2>
                        <span class="streak-badge"><?= $eventCount ?> event<?= $eventCount != 1 ? 's' : '' ?></span>
                    </div>
                    <?php if ($todayEvents): ?>
                        <div class="morning-watch-preview">
                            <h3><?= e($todayEvents[0]['title']) ?></h3>
                            <p class="scripture-ref"><?= date('H:i', strtotime($todayEvents[0]['start_datetime'])) ?> - <?= e($todayEvents[0]['location'] ?: 'No location') ?></p>
                            <a href="/calendar/event.php?id=<?= $todayEvents[0]['id'] ?>" class="btn btn-primary">View Event</a>
                        </div>
                    <?php else: ?>
                        <div class="morning-watch-preview">
                            <h3>No events today</h3>
                            <p class="scripture-ref">Your schedule is clear</p>
                            <a href="/calendar/create.php" class="btn btn-primary">Add Event</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-card quick-actions-card">
                    <h2>Quick Actions</h2>
                    <div class="quick-actions-grid">
                        <a href="/calendar/create.php" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                            </div>
                            <span>Add Event</span>
                        </a>
                        <a href="/calendar/month.php" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                            </div>
                            <span>Month View</span>
                        </a>
                        <a href="/calendar/my-events.php" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                                </svg>
                            </div>
                            <span>My Events</span>
                        </a>
                        <a href="/calendar/birthdays.php" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                            </div>
                            <span>Birthdays</span>
                        </a>
                    </div>
                </div>

                <!-- Today's Schedule -->
                <div class="dashboard-card events-card">
                    <div class="card-header">
                        <h2>Today's Schedule</h2>
                    </div>
                    <?php if ($todayEvents): ?>
                        <div class="events-list">
                            <?php foreach ($todayEvents as $event): ?>
                                <a href="/calendar/event.php?id=<?= $event['id'] ?>" class="event-item">
                                    <div class="event-date">
                                        <span class="event-day"><?= date('H', strtotime($event['start_datetime'])) ?></span>
                                        <span class="event-month"><?= date('i', strtotime($event['start_datetime'])) ?></span>
                                    </div>
                                    <div class="event-info">
                                        <h4><?= e($event['title']) ?></h4>
                                        <p><?= e($event['location'] ?: 'No location') ?></p>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-content">
                            <p>No events scheduled for today</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Upcoming Events -->
                <div class="dashboard-card posts-card">
                    <div class="card-header">
                        <h2>Upcoming Events</h2>
                        <a href="/calendar/all.php" class="view-all-link">View All</a>
                    </div>
                    <?php if ($upcomingEvents): ?>
                        <div class="posts-list">
                            <?php foreach ($upcomingEvents as $event): ?>
                                <a href="/calendar/event.php?id=<?= $event['id'] ?>" class="post-item">
                                    <div class="post-author">
                                        <div class="author-avatar-placeholder">
                                            <?= date('d', strtotime($event['start_datetime'])) ?>
                                        </div>
                                        <span><?= e($event['title']) ?></span>
                                        <span class="post-time"><?= date('M j', strtotime($event['start_datetime'])) ?></span>
                                    </div>
                                    <p class="post-content"><?= date('H:i', strtotime($event['start_datetime'])) ?> - <?= e($event['location'] ?: 'No location') ?></p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-content">
                            <p>No upcoming events</p>
                            <a href="/calendar/create.php" class="btn btn-outline">Create Event</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
