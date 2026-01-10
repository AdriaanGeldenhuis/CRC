<?php
/**
 * CRC Notifications - Settings
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$pageTitle = "Notification Settings - CRC";

// Get user's notification settings
$settings = Database::fetchOne(
    "SELECT * FROM notification_settings WHERE user_id = ?",
    [$user['id']]
);

// Default settings if none exist
if (!$settings) {
    $settings = [
        'email_enabled' => 1,
        'push_enabled' => 1,
        'event_reminders' => 1,
        'prayer_updates' => 1,
        'homecell_updates' => 1,
        'course_updates' => 1,
        'sermon_updates' => 1,
        'livestream_alerts' => 1,
        'announcements' => 1,
        'digest_frequency' => 'daily',
        'quiet_hours_start' => null,
        'quiet_hours_end' => null
    ];
}

$digestOptions = [
    'none' => 'No email digest',
    'daily' => 'Daily digest',
    'weekly' => 'Weekly digest'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/notifications/css/notifications.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <a href="/notifications/" class="back-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
                Back to Notifications
            </a>

            <div class="page-header">
                <div class="page-title">
                    <h1>Notification Settings</h1>
                    <p>Manage how you receive notifications</p>
                </div>
            </div>

            <form id="settings-form" class="settings-form">
                <!-- General Settings -->
                <div class="settings-card">
                    <h2>General</h2>
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Email Notifications</h4>
                            <p>Receive notifications via email</p>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" name="email_enabled" <?= $settings['email_enabled'] ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Push Notifications</h4>
                            <p>Receive push notifications in browser</p>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" name="push_enabled" <?= $settings['push_enabled'] ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <!-- Notification Types -->
                <div class="settings-card">
                    <h2>Notification Types</h2>
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>📅 Event Reminders</h4>
                            <p>Get reminded about upcoming events</p>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" name="event_reminders" <?= $settings['event_reminders'] ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>🙏 Prayer Updates</h4>
                            <p>Updates on your prayer requests</p>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" name="prayer_updates" <?= $settings['prayer_updates'] ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>🏠 Homecell Updates</h4>
                            <p>New members, meetings, and announcements</p>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" name="homecell_updates" <?= $settings['homecell_updates'] ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>📚 Course Updates</h4>
                            <p>New lessons and course completions</p>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" name="course_updates" <?= $settings['course_updates'] ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>🎤 New Sermons</h4>
                            <p>When new sermons are uploaded</p>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" name="sermon_updates" <?= $settings['sermon_updates'] ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>📺 Livestream Alerts</h4>
                            <p>When a livestream starts</p>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" name="livestream_alerts" <?= $settings['livestream_alerts'] ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>📢 Announcements</h4>
                            <p>Church and congregation announcements</p>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" name="announcements" <?= $settings['announcements'] ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <!-- Email Digest -->
                <div class="settings-card">
                    <h2>Email Digest</h2>
                    <p class="card-desc">Receive a summary of notifications via email</p>
                    <div class="radio-group">
                        <?php foreach ($digestOptions as $value => $label): ?>
                            <label class="radio-item">
                                <input type="radio" name="digest_frequency" value="<?= $value ?>"
                                       <?= $settings['digest_frequency'] === $value ? 'checked' : '' ?>>
                                <span class="radio-label"><?= $label ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Quiet Hours -->
                <div class="settings-card">
                    <h2>Quiet Hours</h2>
                    <p class="card-desc">Don't send notifications during these hours</p>
                    <div class="quiet-hours">
                        <div class="time-input">
                            <label>Start Time</label>
                            <input type="time" name="quiet_hours_start"
                                   value="<?= $settings['quiet_hours_start'] ?? '' ?>">
                        </div>
                        <span class="time-separator">to</span>
                        <div class="time-input">
                            <label>End Time</label>
                            <input type="time" name="quiet_hours_end"
                                   value="<?= $settings['quiet_hours_end'] ?? '' ?>">
                        </div>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            </form>

            <!-- Danger Zone -->
            <div class="settings-card danger-zone">
                <h2>Clear Notifications</h2>
                <p class="card-desc">Permanently delete all your notifications</p>
                <button type="button" onclick="clearAllNotifications()" class="btn btn-danger">
                    Clear All Notifications
                </button>
            </div>
        </div>
    </main>

    <script src="/notifications/js/notifications.js"></script>
</body>
</html>
