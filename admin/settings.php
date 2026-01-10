<?php
/**
 * CRC Global Admin - System Settings
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();
Auth::requireRole('super_admin');

$user = Auth::user();
$pageTitle = "Settings - Admin";

// Get current settings
$settings = [];
$settingsRows = Database::fetchAll("SELECT setting_key, setting_value FROM system_settings");
foreach ($settingsRows as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Default values
$defaults = [
    'site_name' => 'CRC App',
    'site_description' => 'Church Resource Center',
    'contact_email' => 'info@crc.app',
    'default_timezone' => 'Africa/Johannesburg',
    'allow_registration' => '1',
    'require_email_verification' => '1',
    'enable_bible' => '1',
    'enable_morning_watch' => '1',
    'enable_learning' => '1',
    'enable_homecells' => '1',
    'enable_diary' => '1',
    'enable_calendar' => '1',
    'enable_media' => '1',
    'maintenance_mode' => '0',
    'maintenance_message' => 'We are currently performing maintenance. Please check back soon.',
    'ai_api_key' => '',
    'ai_provider' => 'openai'
];

$settings = array_merge($defaults, $settings);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/admin/css/admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <a href="/admin/" class="admin-logo">CRC Admin</a>
            </div>
            <nav class="sidebar-nav">
                <a href="/admin/" class="nav-item">
                    <span class="nav-icon">📊</span>
                    Dashboard
                </a>
                <a href="/admin/users.php" class="nav-item">
                    <span class="nav-icon">👥</span>
                    Users
                </a>
                <a href="/admin/congregations.php" class="nav-item">
                    <span class="nav-icon">⛪</span>
                    Congregations
                </a>
                <a href="/admin/sermons.php" class="nav-item">
                    <span class="nav-icon">🎤</span>
                    Sermons
                </a>
                <a href="/admin/courses.php" class="nav-item">
                    <span class="nav-icon">📚</span>
                    Courses
                </a>
                <a href="/admin/content.php" class="nav-item">
                    <span class="nav-icon">📝</span>
                    Content
                </a>
                <a href="/admin/settings.php" class="nav-item active">
                    <span class="nav-icon">⚙️</span>
                    Settings
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="/home/" class="nav-item">
                    <span class="nav-icon">🏠</span>
                    Back to App
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-header">
                <h1>Settings</h1>
            </header>

            <div class="admin-content">
                <form id="settings-form">
                    <!-- General Settings -->
                    <div class="card">
                        <div class="card-header">
                            <h2>General</h2>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Site Name</label>
                                <input type="text" name="site_name" value="<?= e($settings['site_name']) ?>" class="form-input">
                            </div>
                            <div class="form-group">
                                <label>Site Description</label>
                                <input type="text" name="site_description" value="<?= e($settings['site_description']) ?>" class="form-input">
                            </div>
                            <div class="form-group">
                                <label>Contact Email</label>
                                <input type="email" name="contact_email" value="<?= e($settings['contact_email']) ?>" class="form-input">
                            </div>
                            <div class="form-group">
                                <label>Default Timezone</label>
                                <select name="default_timezone" class="form-select">
                                    <option value="Africa/Johannesburg" <?= $settings['default_timezone'] === 'Africa/Johannesburg' ? 'selected' : '' ?>>Africa/Johannesburg</option>
                                    <option value="UTC" <?= $settings['default_timezone'] === 'UTC' ? 'selected' : '' ?>>UTC</option>
                                    <option value="America/New_York" <?= $settings['default_timezone'] === 'America/New_York' ? 'selected' : '' ?>>America/New York</option>
                                    <option value="Europe/London" <?= $settings['default_timezone'] === 'Europe/London' ? 'selected' : '' ?>>Europe/London</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Registration Settings -->
                    <div class="card">
                        <div class="card-header">
                            <h2>Registration</h2>
                        </div>
                        <div class="card-body">
                            <div class="setting-toggle">
                                <div class="toggle-info">
                                    <label>Allow Registration</label>
                                    <p>Allow new users to register accounts</p>
                                </div>
                                <label class="toggle">
                                    <input type="checkbox" name="allow_registration" <?= $settings['allow_registration'] ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div class="setting-toggle">
                                <div class="toggle-info">
                                    <label>Require Email Verification</label>
                                    <p>Users must verify their email before accessing the app</p>
                                </div>
                                <label class="toggle">
                                    <input type="checkbox" name="require_email_verification" <?= $settings['require_email_verification'] ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Feature Toggles -->
                    <div class="card">
                        <div class="card-header">
                            <h2>Features</h2>
                        </div>
                        <div class="card-body">
                            <div class="settings-grid">
                                <div class="setting-toggle">
                                    <div class="toggle-info">
                                        <label>Bible</label>
                                    </div>
                                    <label class="toggle">
                                        <input type="checkbox" name="enable_bible" <?= $settings['enable_bible'] ? 'checked' : '' ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="setting-toggle">
                                    <div class="toggle-info">
                                        <label>Morning Watch</label>
                                    </div>
                                    <label class="toggle">
                                        <input type="checkbox" name="enable_morning_watch" <?= $settings['enable_morning_watch'] ? 'checked' : '' ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="setting-toggle">
                                    <div class="toggle-info">
                                        <label>Learning</label>
                                    </div>
                                    <label class="toggle">
                                        <input type="checkbox" name="enable_learning" <?= $settings['enable_learning'] ? 'checked' : '' ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="setting-toggle">
                                    <div class="toggle-info">
                                        <label>Homecells</label>
                                    </div>
                                    <label class="toggle">
                                        <input type="checkbox" name="enable_homecells" <?= $settings['enable_homecells'] ? 'checked' : '' ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="setting-toggle">
                                    <div class="toggle-info">
                                        <label>Diary</label>
                                    </div>
                                    <label class="toggle">
                                        <input type="checkbox" name="enable_diary" <?= $settings['enable_diary'] ? 'checked' : '' ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="setting-toggle">
                                    <div class="toggle-info">
                                        <label>Calendar</label>
                                    </div>
                                    <label class="toggle">
                                        <input type="checkbox" name="enable_calendar" <?= $settings['enable_calendar'] ? 'checked' : '' ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="setting-toggle">
                                    <div class="toggle-info">
                                        <label>Media</label>
                                    </div>
                                    <label class="toggle">
                                        <input type="checkbox" name="enable_media" <?= $settings['enable_media'] ? 'checked' : '' ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- AI Settings -->
                    <div class="card">
                        <div class="card-header">
                            <h2>AI Integration</h2>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>AI Provider</label>
                                <select name="ai_provider" class="form-select">
                                    <option value="openai" <?= $settings['ai_provider'] === 'openai' ? 'selected' : '' ?>>OpenAI</option>
                                    <option value="anthropic" <?= $settings['ai_provider'] === 'anthropic' ? 'selected' : '' ?>>Anthropic</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>API Key</label>
                                <input type="password" name="ai_api_key" value="<?= e($settings['ai_api_key']) ?>"
                                       class="form-input" placeholder="Enter API key">
                                <p class="form-help">Used for Bible AI explanations and other AI features</p>
                            </div>
                        </div>
                    </div>

                    <!-- Maintenance Mode -->
                    <div class="card">
                        <div class="card-header">
                            <h2>Maintenance Mode</h2>
                        </div>
                        <div class="card-body">
                            <div class="setting-toggle">
                                <div class="toggle-info">
                                    <label>Enable Maintenance Mode</label>
                                    <p>When enabled, only admins can access the site</p>
                                </div>
                                <label class="toggle">
                                    <input type="checkbox" name="maintenance_mode" <?= $settings['maintenance_mode'] ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div class="form-group">
                                <label>Maintenance Message</label>
                                <textarea name="maintenance_message" class="form-textarea" rows="2"><?= e($settings['maintenance_message']) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Save Button -->
                    <div class="form-actions-fixed">
                        <button type="submit" class="btn btn-primary btn-lg">Save Settings</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script src="/admin/js/admin.js"></script>
</body>
</html>
