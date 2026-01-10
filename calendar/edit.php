<?php
/**
 * CRC Calendar - Edit Event
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

// Try to find event
$event = Database::fetchOne(
    "SELECT * FROM calendar_events WHERE id = ? AND user_id = ?",
    [$eventId, $user['id']]
);

$isPersonalEvent = true;
if (!$event) {
    // Check congregation events
    $event = Database::fetchOne(
        "SELECT e.* FROM events e
         WHERE e.id = ? AND (e.user_id = ? OR (e.congregation_id = ? AND e.congregation_id IS NOT NULL))",
        [$eventId, $user['id'], $primaryCong['id']]
    );
    $isPersonalEvent = false;

    if ($event && $event['user_id'] != $user['id'] && !Auth::isCongregationAdmin($primaryCong['id'])) {
        Response::redirect('/calendar/');
    }
}

if (!$event) {
    Response::redirect('/calendar/');
}

$pageTitle = 'Edit Event - CRC';
$isAdmin = Auth::isCongregationAdmin($primaryCong['id']);

// Get reminders
$reminders = [];
if ($isPersonalEvent) {
    $reminders = Database::fetchAll(
        "SELECT minutes_before FROM calendar_reminders WHERE event_id = ? AND user_id = ?",
        [$eventId, $user['id']]
    );
    $reminderValues = array_column($reminders, 'minutes_before');
}
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
            <div class="form-page">
                <div class="form-header">
                    <a href="/calendar/event.php?id=<?= $eventId ?>" class="back-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                        Back to Event
                    </a>
                    <h1>Edit Event</h1>
                </div>

                <form id="edit-event-form" class="event-form" data-event-id="<?= $eventId ?>" data-personal="<?= $isPersonalEvent ? '1' : '0' ?>">
                    <div class="form-group">
                        <label for="title">Event Title *</label>
                        <input type="text" id="title" name="title" value="<?= e($event['title']) ?>" required maxlength="200">
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="4" maxlength="2000"><?= e($event['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="start_date">Start Date *</label>
                            <input type="date" id="start_date" name="start_date" value="<?= date('Y-m-d', strtotime($event['start_datetime'])) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="start_time">Start Time *</label>
                            <input type="time" id="start_time" name="start_time" value="<?= date('H:i', strtotime($event['start_datetime'])) ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="end_date">End Date</label>
                            <input type="date" id="end_date" name="end_date" value="<?= !empty($event['end_datetime']) ? date('Y-m-d', strtotime($event['end_datetime'])) : '' ?>">
                        </div>
                        <div class="form-group">
                            <label for="end_time">End Time</label>
                            <input type="time" id="end_time" name="end_time" value="<?= !empty($event['end_datetime']) ? date('H:i', strtotime($event['end_datetime'])) : '' ?>">
                        </div>
                    </div>

                    <div class="form-check">
                        <input type="checkbox" id="all_day" name="all_day" <?= !empty($event['all_day']) ? 'checked' : '' ?>>
                        <label for="all_day">All day event</label>
                    </div>

                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" value="<?= e($event['location'] ?? '') ?>" maxlength="500">
                    </div>

                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category">
                            <?php
                            $categories = ['general', 'service', 'prayer', 'bible_study', 'homecell', 'youth', 'outreach', 'meeting', 'social', 'other'];
                            foreach ($categories as $cat):
                            ?>
                                <option value="<?= $cat ?>" <?= ($event['category'] ?? 'general') === $cat ? 'selected' : '' ?>>
                                    <?= ucwords(str_replace('_', ' ', $cat)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="color">Color</label>
                        <div class="color-options">
                            <?php
                            $colors = ['#4F46E5', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899'];
                            $currentColor = $event['color'] ?? '#4F46E5';
                            foreach ($colors as $color):
                            ?>
                                <label class="color-option">
                                    <input type="radio" name="color" value="<?= $color ?>" <?= $currentColor === $color ? 'checked' : '' ?>>
                                    <span class="color-swatch" style="background: <?= $color ?>;"></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if ($isPersonalEvent): ?>
                        <div class="form-group">
                            <label>Reminder</label>
                            <div class="reminder-options">
                                <label class="reminder-option">
                                    <input type="checkbox" name="reminders[]" value="15" <?= in_array(15, $reminderValues ?? []) ? 'checked' : '' ?>>
                                    <span>15 minutes before</span>
                                </label>
                                <label class="reminder-option">
                                    <input type="checkbox" name="reminders[]" value="60" <?= in_array(60, $reminderValues ?? []) ? 'checked' : '' ?>>
                                    <span>1 hour before</span>
                                </label>
                                <label class="reminder-option">
                                    <input type="checkbox" name="reminders[]" value="1440" <?= in_array(1440, $reminderValues ?? []) ? 'checked' : '' ?>>
                                    <span>1 day before</span>
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="form-actions">
                        <a href="/calendar/event.php?id=<?= $eventId ?>" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="/calendar/js/calendar.js"></script>
</body>
</html>
