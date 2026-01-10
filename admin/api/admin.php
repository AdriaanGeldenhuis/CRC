<?php
/**
 * CRC Global Admin API
 * POST /admin/api/admin.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();
Auth::requireRole('super_admin');
Response::requirePost();
CSRF::require();

$user = Auth::user();
$action = input('action');

switch ($action) {
    // User management
    case 'add_user':
        $name = trim(input('name'));
        $email = trim(input('email'));
        $password = input('password');
        $role = input('global_role', 'user');

        if (!$name || !$email || !$password) {
            Response::error('All fields are required');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Invalid email address');
        }

        if (strlen($password) < 8) {
            Response::error('Password must be at least 8 characters');
        }

        // Check if email exists
        $existing = Database::fetchColumn("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            Response::error('Email already in use');
        }

        Database::insert('users', [
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'global_role' => $role,
            'email_verified_at' => date('Y-m-d H:i:s'), // Auto-verify admin-created users
            'created_at' => date('Y-m-d H:i:s')
        ]);

        logActivity($user['id'], 'Created user: ' . $email);

        Response::success([], 'User created successfully');
        break;

    case 'get_user':
        $userId = (int)input('user_id');

        $userData = Database::fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
        if (!$userData) {
            Response::error('User not found');
        }

        unset($userData['password_hash']);
        Response::success(['user' => $userData]);
        break;

    case 'update_user':
        $userId = (int)input('user_id');
        $name = trim(input('name'));
        $email = trim(input('email'));
        $password = input('password');
        $role = input('global_role');

        if (!$userId || !$name || !$email) {
            Response::error('Required fields missing');
        }

        // Check if email exists for another user
        $existing = Database::fetchColumn(
            "SELECT id FROM users WHERE email = ? AND id != ?",
            [$email, $userId]
        );
        if ($existing) {
            Response::error('Email already in use');
        }

        $updateData = [
            'name' => $name,
            'email' => $email,
            'global_role' => $role,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($password && strlen($password) >= 8) {
            $updateData['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        Database::update('users', $updateData, 'id = ?', [$userId]);

        logActivity($user['id'], 'Updated user: ' . $email);

        Response::success([], 'User updated successfully');
        break;

    case 'delete_user':
        $userId = (int)input('user_id');

        if ($userId === $user['id']) {
            Response::error('Cannot delete your own account');
        }

        $targetUser = Database::fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
        if (!$targetUser) {
            Response::error('User not found');
        }

        // Soft delete
        Database::update('users', [
            'deleted_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$userId]);

        logActivity($user['id'], 'Deleted user: ' . $targetUser['email']);

        Response::success([], 'User deleted successfully');
        break;

    // Congregation management
    case 'add_congregation':
        $name = trim(input('name'));
        $city = trim(input('city'));
        $country = trim(input('country'));
        $address = trim(input('address'));
        $code = trim(input('code'));
        $status = input('status', 'active');

        if (!$name || !$city || !$country) {
            Response::error('Name, city, and country are required');
        }

        // Generate code if not provided
        if (!$code) {
            $code = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $city), 0, 3)) . '-' . rand(100, 999);
        }

        $congId = Database::insert('congregations', [
            'name' => $name,
            'city' => $city,
            'country' => $country,
            'address' => $address,
            'code' => $code,
            'status' => $status,
            'created_by' => $user['id'],
            'created_at' => date('Y-m-d H:i:s')
        ]);

        logActivity($user['id'], 'Created congregation: ' . $name);

        Response::success(['congregation_id' => $congId], 'Congregation created successfully');
        break;

    case 'get_congregation':
        $congId = (int)input('congregation_id');

        $cong = Database::fetchOne("SELECT * FROM congregations WHERE id = ?", [$congId]);
        if (!$cong) {
            Response::error('Congregation not found');
        }

        Response::success(['congregation' => $cong]);
        break;

    case 'update_congregation':
        $congId = (int)input('congregation_id');
        $name = trim(input('name'));
        $city = trim(input('city'));
        $country = trim(input('country'));
        $address = trim(input('address'));
        $code = trim(input('code'));
        $status = input('status');

        if (!$congId || !$name) {
            Response::error('Required fields missing');
        }

        Database::update('congregations', [
            'name' => $name,
            'city' => $city,
            'country' => $country,
            'address' => $address,
            'code' => $code,
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$congId]);

        logActivity($user['id'], 'Updated congregation: ' . $name);

        Response::success([], 'Congregation updated successfully');
        break;

    case 'suspend_congregation':
        $congId = (int)input('congregation_id');

        Database::update('congregations', [
            'status' => 'suspended',
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$congId]);

        logActivity($user['id'], 'Suspended congregation ID: ' . $congId);

        Response::success([], 'Congregation suspended');
        break;

    case 'activate_congregation':
        $congId = (int)input('congregation_id');

        Database::update('congregations', [
            'status' => 'active',
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$congId]);

        logActivity($user['id'], 'Activated congregation ID: ' . $congId);

        Response::success([], 'Congregation activated');
        break;

    // Settings management
    case 'save_settings':
        $settingsToSave = [
            'site_name', 'site_description', 'contact_email', 'default_timezone',
            'allow_registration', 'require_email_verification',
            'enable_bible', 'enable_morning_watch', 'enable_learning',
            'enable_homecells', 'enable_diary', 'enable_calendar', 'enable_media',
            'maintenance_mode', 'maintenance_message',
            'ai_provider', 'ai_api_key'
        ];

        foreach ($settingsToSave as $key) {
            $value = input($key, '');

            // Handle checkboxes
            if (in_array($key, ['allow_registration', 'require_email_verification', 'maintenance_mode',
                               'enable_bible', 'enable_morning_watch', 'enable_learning',
                               'enable_homecells', 'enable_diary', 'enable_calendar', 'enable_media'])) {
                $value = $value ? '1' : '0';
            }

            // Check if setting exists
            $existing = Database::fetchColumn(
                "SELECT id FROM system_settings WHERE setting_key = ?",
                [$key]
            );

            if ($existing) {
                Database::update('system_settings', [
                    'setting_value' => $value,
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'setting_key = ?', [$key]);
            } else {
                Database::insert('system_settings', [
                    'setting_key' => $key,
                    'setting_value' => $value,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
        }

        logActivity($user['id'], 'Updated system settings');

        Response::success([], 'Settings saved successfully');
        break;

    // Content management
    case 'add_sermon':
        $title = trim(input('title'));
        $speaker = trim(input('speaker'));
        $sermonDate = input('sermon_date');
        $description = trim(input('description'));
        $videoUrl = trim(input('video_url'));
        $audioUrl = trim(input('audio_url'));
        $category = trim(input('category'));
        $seriesId = (int)input('series_id') ?: null;
        $congId = (int)input('congregation_id') ?: null;

        if (!$title || !$speaker || !$sermonDate) {
            Response::error('Title, speaker, and date are required');
        }

        $sermonId = Database::insert('sermons', [
            'title' => $title,
            'speaker' => $speaker,
            'sermon_date' => $sermonDate,
            'description' => $description,
            'video_url' => $videoUrl,
            'audio_url' => $audioUrl,
            'category' => $category,
            'series_id' => $seriesId,
            'congregation_id' => $congId,
            'status' => 'published',
            'created_by' => $user['id'],
            'created_at' => date('Y-m-d H:i:s')
        ]);

        logActivity($user['id'], 'Added sermon: ' . $title);

        Response::success(['sermon_id' => $sermonId], 'Sermon added successfully');
        break;

    case 'add_course':
        $title = trim(input('title'));
        $description = trim(input('description'));
        $category = trim(input('category'));
        $difficulty = input('difficulty', 'beginner');
        $congId = (int)input('congregation_id') ?: null;

        if (!$title) {
            Response::error('Title is required');
        }

        $courseId = Database::insert('courses', [
            'title' => $title,
            'description' => $description,
            'category' => $category,
            'difficulty' => $difficulty,
            'congregation_id' => $congId,
            'status' => 'draft',
            'created_by' => $user['id'],
            'created_at' => date('Y-m-d H:i:s')
        ]);

        logActivity($user['id'], 'Created course: ' . $title);

        Response::success(['course_id' => $courseId], 'Course created successfully');
        break;

    // Dashboard stats
    case 'get_stats':
        $stats = [
            'users' => Database::fetchColumn("SELECT COUNT(*) FROM users"),
            'users_today' => Database::fetchColumn("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()"),
            'congregations' => Database::fetchColumn("SELECT COUNT(*) FROM congregations WHERE status = 'active'"),
            'sermons' => Database::fetchColumn("SELECT COUNT(*) FROM sermons WHERE status = 'published'"),
            'courses' => Database::fetchColumn("SELECT COUNT(*) FROM courses WHERE status = 'published'"),
            'events' => Database::fetchColumn("SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()"),
            'homecells' => Database::fetchColumn("SELECT COUNT(*) FROM homecells WHERE status = 'active'")
        ];

        Response::success(['stats' => $stats]);
        break;

    default:
        Response::error('Invalid action');
}

function logActivity($userId, $action) {
    Database::insert('activity_log', [
        'user_id' => $userId,
        'action' => $action,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'created_at' => date('Y-m-d H:i:s')
    ]);
}
