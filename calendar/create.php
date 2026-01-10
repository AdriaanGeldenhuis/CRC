<?php
/**
 * CRC Calendar - Create Event
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$primaryCong = Auth::primaryCongregation();
if (!$primaryCong) {
    Response::redirect('/onboarding/');
}

$user = Auth::user();
$pageTitle = 'Create Event - CRC';
$isAdmin = Auth::isCongregationAdmin($primaryCong['id']);

// Pre-fill date if passed
$prefillDate = $_GET['date'] ?? date('Y-m-d');
$prefillTime = $_GET['time'] ?? '09:00';
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
                    <a href="/calendar/" class="back-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                        Back to Calendar
                    </a>
                    <h1>Create Event</h1>
                    <p>Add a new event to your calendar</p>
                </div>

                <form id="create-event-form" class="event-form">
                    <div class="form-group">
                        <label for="title">Event Title *</label>
                        <input type="text" id="title" name="title" required maxlength="200">
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="4" maxlength="2000"></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="start_date">Start Date *</label>
                            <input type="date" id="start_date" name="start_date" value="<?= e($prefillDate) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="start_time">Start Time *</label>
                            <input type="time" id="start_time" name="start_time" value="<?= e($prefillTime) ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="end_date">End Date</label>
                            <input type="date" id="end_date" name="end_date" value="<?= e($prefillDate) ?>">
                        </div>
                        <div class="form-group">
                            <label for="end_time">End Time</label>
                            <input type="time" id="end_time" name="end_time" value="10:00">
                        </div>
                    </div>

                    <div class="form-check">
                        <input type="checkbox" id="all_day" name="all_day">
                        <label for="all_day">All day event</label>
                    </div>

                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" maxlength="500" placeholder="Enter location or online link">
                    </div>

                    <div class="form-group">
                        <label for="event_type">Event Type *</label>
                        <select id="event_type" name="event_type" required>
                            <option value="personal">Personal Event</option>
                            <?php if ($isAdmin): ?>
                                <option value="congregation">Congregation Event</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category">
                            <option value="general">General</option>
                            <option value="service">Church Service</option>
                            <option value="prayer">Prayer Meeting</option>
                            <option value="bible_study">Bible Study</option>
                            <option value="homecell">Homecell</option>
                            <option value="youth">Youth</option>
                            <option value="outreach">Outreach</option>
                            <option value="meeting">Meeting</option>
                            <option value="social">Social Event</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="color">Color</label>
                        <div class="color-options">
                            <label class="color-option">
                                <input type="radio" name="color" value="#4F46E5" checked>
                                <span class="color-swatch" style="background: #4F46E5;"></span>
                            </label>
                            <label class="color-option">
                                <input type="radio" name="color" value="#10B981">
                                <span class="color-swatch" style="background: #10B981;"></span>
                            </label>
                            <label class="color-option">
                                <input type="radio" name="color" value="#F59E0B">
                                <span class="color-swatch" style="background: #F59E0B;"></span>
                            </label>
                            <label class="color-option">
                                <input type="radio" name="color" value="#EF4444">
                                <span class="color-swatch" style="background: #EF4444;"></span>
                            </label>
                            <label class="color-option">
                                <input type="radio" name="color" value="#8B5CF6">
                                <span class="color-swatch" style="background: #8B5CF6;"></span>
                            </label>
                            <label class="color-option">
                                <input type="radio" name="color" value="#EC4899">
                                <span class="color-swatch" style="background: #EC4899;"></span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Reminder</label>
                        <div class="reminder-options">
                            <label class="reminder-option">
                                <input type="checkbox" name="reminders[]" value="15">
                                <span>15 minutes before</span>
                            </label>
                            <label class="reminder-option">
                                <input type="checkbox" name="reminders[]" value="60" checked>
                                <span>1 hour before</span>
                            </label>
                            <label class="reminder-option">
                                <input type="checkbox" name="reminders[]" value="1440">
                                <span>1 day before</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group" id="recurrence-group">
                        <label for="recurrence">Repeat</label>
                        <select id="recurrence" name="recurrence">
                            <option value="none">Does not repeat</option>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>

                    <div id="recurrence-end-group" style="display: none;">
                        <div class="form-group">
                            <label for="recurrence_end">Repeat Until</label>
                            <input type="date" id="recurrence_end" name="recurrence_end">
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="/calendar/" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Create Event</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="/calendar/js/calendar.js"></script>
</body>
</html>
