<?php
/**
 * CRC Calendar - Event Detail
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$primaryCong = Auth::primaryCongregation();
if (!$primaryCong) {
    Response::redirect('/onboarding/');
}

$user = Auth::user();
$eventId = (int)($_GET['id'] ?? 0);

if (!$eventId) {
    Response::redirect('/calendar/');
}

// Try to find event (either general event or personal calendar event)
$event = Database::fetchOne(
    "SELECT e.*, c.name as congregation_name, u.name as creator_name
     FROM events e
     LEFT JOIN congregations c ON e.congregation_id = c.id
     LEFT JOIN users u ON e.user_id = u.id
     WHERE e.id = ?
     AND (e.scope = 'global' OR e.congregation_id = ? OR e.user_id = ?)",
    [$eventId, $primaryCong['id'], $user['id']]
);

$isPersonalEvent = false;
if (!$event) {
    // Check personal calendar events
    $event = Database::fetchOne(
        "SELECT * FROM calendar_events WHERE id = ? AND user_id = ?",
        [$eventId, $user['id']]
    );
    $isPersonalEvent = true;
}

if (!$event) {
    Response::redirect('/calendar/');
}

$pageTitle = e($event['title']) . ' - CRC Calendar';
$canEdit = ($event['user_id'] == $user['id']) ||
           (!$isPersonalEvent && $event['congregation_id'] && Auth::isCongregationAdmin($event['congregation_id']));

// Get reminders for this event
$reminders = [];
if ($isPersonalEvent) {
    $reminders = Database::fetchAll(
        "SELECT * FROM calendar_reminders WHERE event_id = ? AND user_id = ? ORDER BY minutes_before ASC",
        [$eventId, $user['id']]
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/calendar/css/calendar.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="event-detail-page">
                <div class="event-header">
                    <a href="/calendar/" class="back-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                        Back to Calendar
                    </a>

                    <div class="event-title-row">
                        <div class="event-color" style="background: <?= e($event['color'] ?? '#4F46E5') ?>;"></div>
                        <h1><?= e($event['title']) ?></h1>
                    </div>

                    <?php if ($canEdit): ?>
                        <div class="event-actions">
                            <a href="/calendar/edit.php?id=<?= $eventId ?>" class="btn btn-secondary">Edit</a>
                            <button type="button" class="btn btn-danger" onclick="deleteEvent(<?= $eventId ?>)">Delete</button>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="event-content">
                    <div class="event-main">
                        <div class="event-meta">
                            <div class="meta-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                <div>
                                    <strong>Date</strong>
                                    <span>
                                        <?= date('l, F j, Y', strtotime($event['start_datetime'])) ?>
                                        <?php if (!empty($event['end_datetime']) && date('Y-m-d', strtotime($event['end_datetime'])) !== date('Y-m-d', strtotime($event['start_datetime']))): ?>
                                            - <?= date('l, F j, Y', strtotime($event['end_datetime'])) ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>

                            <?php if (empty($event['all_day'])): ?>
                                <div class="meta-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <polyline points="12 6 12 12 16 14"></polyline>
                                    </svg>
                                    <div>
                                        <strong>Time</strong>
                                        <span>
                                            <?= date('g:i A', strtotime($event['start_datetime'])) ?>
                                            <?php if (!empty($event['end_datetime'])): ?>
                                                - <?= date('g:i A', strtotime($event['end_datetime'])) ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($event['location'])): ?>
                                <div class="meta-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                        <circle cx="12" cy="10" r="3"></circle>
                                    </svg>
                                    <div>
                                        <strong>Location</strong>
                                        <span><?= e($event['location']) ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!$isPersonalEvent): ?>
                                <div class="meta-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                    </svg>
                                    <div>
                                        <strong>Scope</strong>
                                        <span class="badge badge-<?= $event['scope'] ?? 'congregation' ?>">
                                            <?= ucfirst($event['scope'] ?? 'congregation') ?>
                                            <?php if (!empty($event['congregation_name'])): ?>
                                                - <?= e($event['congregation_name']) ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($event['description'])): ?>
                            <div class="event-description">
                                <h3>Description</h3>
                                <p><?= nl2br(e($event['description'])) ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if (!$isPersonalEvent && !empty($event['creator_name'])): ?>
                            <div class="event-creator">
                                <small>Created by <?= e($event['creator_name']) ?></small>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="event-sidebar">
                        <?php if ($reminders): ?>
                            <div class="reminders-card">
                                <h3>Reminders</h3>
                                <ul>
                                    <?php foreach ($reminders as $reminder): ?>
                                        <li>
                                            <?php
                                            $mins = $reminder['minutes_before'];
                                            if ($mins < 60) {
                                                echo $mins . ' minutes before';
                                            } elseif ($mins < 1440) {
                                                echo ($mins / 60) . ' hour(s) before';
                                            } else {
                                                echo ($mins / 1440) . ' day(s) before';
                                            }
                                            ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <div class="add-to-calendar">
                            <h3>Add to Calendar</h3>
                            <a href="#" class="btn btn-outline" onclick="addToGoogleCalendar()">
                                <svg viewBox="0 0 24 24" width="16" height="16">
                                    <path fill="currentColor" d="M19.5 4H18V3a1 1 0 0 0-2 0v1H8V3a1 1 0 0 0-2 0v1H4.5A2.5 2.5 0 0 0 2 6.5v13A2.5 2.5 0 0 0 4.5 22h15a2.5 2.5 0 0 0 2.5-2.5v-13A2.5 2.5 0 0 0 19.5 4zm0 16h-15a.5.5 0 0 1-.5-.5V9h16v10.5a.5.5 0 0 1-.5.5z"/>
                                </svg>
                                Google Calendar
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const eventData = {
            id: <?= $eventId ?>,
            title: <?= json_encode($event['title']) ?>,
            start: <?= json_encode($event['start_datetime']) ?>,
            end: <?= json_encode($event['end_datetime'] ?? $event['start_datetime']) ?>,
            location: <?= json_encode($event['location'] ?? '') ?>,
            description: <?= json_encode($event['description'] ?? '') ?>
        };
    </script>
    <script src="/calendar/js/calendar.js"></script>
</body>
</html>
