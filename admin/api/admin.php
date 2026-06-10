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

        // Use the provided code as a slug hint, otherwise derive it from the name
        $slug = adminUniqueSlug('congregations', $code ?: $name);

        $congId = Database::insert('congregations', [
            'name' => $name,
            'slug' => $slug,
            'city' => $city,
            'country' => $country,
            'address' => $address,
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
                "SELECT id FROM settings WHERE setting_key = ?",
                [$key]
            );

            if ($existing) {
                Database::update('settings', [
                    'setting_value' => $value,
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'setting_key = ?', [$key]);
            } else {
                Database::insert('settings', [
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
        $thumbnail = trim(input('thumbnail'));
        $congId = (int)input('congregation_id') ?: null;
        $status = in_array(input('status'), ['draft', 'published', 'archived'], true) ? input('status') : 'published';
        $duration = sermonParseDuration(input('duration_minutes'), input('duration'));
        $scripture = sermonParseScripture(input('scripture_references'));
        $seriesId = sermonResolveSeries(input('series_id'), input('new_series'), $congId, $user['id']);

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
            'thumbnail' => $thumbnail ?: null,
            'duration' => $duration,
            'scripture_references' => $scripture,
            'series_id' => $seriesId,
            'congregation_id' => $congId,
            'scope' => $congId ? 'congregation' : 'global',
            'status' => $status,
            'created_by' => $user['id'],
            'created_at' => date('Y-m-d H:i:s')
        ]);

        logActivity($user['id'], 'Added sermon: ' . $title);

        // Notify congregation members about the new sermon
        if ($congId) {
            $members = Database::fetchAll(
                "SELECT user_id FROM user_congregations WHERE congregation_id = ? AND status = 'active'",
                [$congId]
            );
            Notify::sendMany(
                array_column($members, 'user_id'),
                'new_sermon',
                'New Sermon',
                $title . ' • ' . $speaker,
                '/media/sermon.php?id=' . $sermonId
            );
        }

        Response::success(['sermon_id' => $sermonId], 'Sermon added successfully');
        break;

    case 'get_sermon':
        $sermonId = (int)input('sermon_id');
        $sermon = Database::fetchOne("SELECT * FROM sermons WHERE id = ?", [$sermonId]);
        if (!$sermon) {
            Response::error('Sermon not found');
        }
        Response::success(['sermon' => $sermon]);
        break;

    case 'update_sermon':
        $sermonId = (int)input('sermon_id');
        $title = trim(input('title'));
        $speaker = trim(input('speaker'));
        $sermonDate = input('sermon_date');
        $description = trim(input('description'));
        $videoUrl = trim(input('video_url'));
        $audioUrl = trim(input('audio_url'));
        $category = trim(input('category'));
        $thumbnail = trim(input('thumbnail'));
        $congId = (int)input('congregation_id') ?: null;
        $status = in_array(input('status'), ['draft', 'published', 'archived'], true) ? input('status') : 'published';
        $duration = sermonParseDuration(input('duration_minutes'), input('duration'));
        $scripture = sermonParseScripture(input('scripture_references'));
        $seriesId = sermonResolveSeries(input('series_id'), input('new_series'), $congId, $user['id']);

        if (!$sermonId || !$title || !$speaker || !$sermonDate) {
            Response::error('Title, speaker, and date are required');
        }

        Database::update('sermons', [
            'title' => $title,
            'speaker' => $speaker,
            'sermon_date' => $sermonDate,
            'description' => $description,
            'video_url' => $videoUrl,
            'audio_url' => $audioUrl,
            'category' => $category,
            'thumbnail' => $thumbnail ?: null,
            'duration' => $duration,
            'scripture_references' => $scripture,
            'series_id' => $seriesId,
            'congregation_id' => $congId,
            'scope' => $congId ? 'congregation' : 'global',
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$sermonId]);

        logActivity($user['id'], 'Updated sermon: ' . $title);

        Response::success([], 'Sermon updated successfully');
        break;

    case 'delete_sermon':
        $sermonId = (int)input('sermon_id');
        if (!$sermonId) {
            Response::error('Sermon ID required');
        }
        Database::delete('sermons', 'id = ?', [$sermonId]);
        logActivity($user['id'], 'Deleted sermon ID: ' . $sermonId);
        Response::success([], 'Sermon deleted');
        break;

    // Livestream management
    case 'add_livestream':
    case 'update_livestream':
        $streamId = (int)input('stream_id');
        $isUpdate = ($action === 'update_livestream');

        $title = trim(input('title'));
        if (!$title) {
            Response::error('Title is required');
        }
        if ($isUpdate && !$streamId) {
            Response::error('Stream ID required');
        }

        $status = in_array(input('status'), ['scheduled', 'live', 'ended'], true) ? input('status') : 'scheduled';
        $congId = (int)input('congregation_id') ?: null;

        $data = [
            'title'         => $title,
            'description'   => trim(input('description')) ?: null,
            'embed_url'     => trim(input('embed_url')) ?: null,
            'recording_url' => trim(input('recording_url')) ?: null,
            'thumbnail_url' => trim(input('thumbnail_url')) ?: null,
            'duration'      => sermonParseDuration(input('duration_minutes'), input('duration')),
            'status'        => $status,
            'scheduled_at'  => livestreamParseDateTime(input('scheduled_at')),
            'congregation_id' => $congId,
            'chat_enabled'  => input('chat_enabled') ? 1 : 0,
            'updated_at'    => date('Y-m-d H:i:s'),
        ];

        // Status transitions: stamp start/end times automatically.
        $existing = $isUpdate ? Database::fetchOne("SELECT started_at, ended_at FROM livestreams WHERE id = ?", [$streamId]) : null;
        if ($status === 'live' && empty($existing['started_at'])) {
            $data['started_at'] = date('Y-m-d H:i:s');
        }
        if ($status === 'ended' && empty($existing['ended_at'])) {
            $data['ended_at'] = date('Y-m-d H:i:s');
        }

        if ($isUpdate) {
            Database::update('livestreams', $data, 'id = ?', [$streamId]);
            logActivity($user['id'], 'Updated livestream: ' . $title);
            Response::success([], 'Livestream updated successfully');
        } else {
            $data['created_by'] = $user['id'];
            $data['created_at'] = date('Y-m-d H:i:s');
            $streamId = Database::insert('livestreams', $data);
            logActivity($user['id'], 'Added livestream: ' . $title);
            Response::success(['stream_id' => $streamId], 'Livestream added successfully');
        }
        break;

    case 'get_livestream':
        $streamId = (int)input('stream_id');
        $stream = Database::fetchOne("SELECT * FROM livestreams WHERE id = ?", [$streamId]);
        if (!$stream) {
            Response::error('Livestream not found');
        }
        Response::success(['livestream' => $stream]);
        break;

    case 'delete_livestream':
        $streamId = (int)input('stream_id');
        if (!$streamId) {
            Response::error('Stream ID required');
        }
        Database::delete('livestreams', 'id = ?', [$streamId]);
        logActivity($user['id'], 'Deleted livestream ID: ' . $streamId);
        Response::success([], 'Livestream deleted');
        break;

    case 'add_course':
        $title = trim(input('title'));
        $description = trim(input('description'));
        $category = trim(input('category'));
        $difficulty = input('difficulty', 'beginner');
        $coverImage = trim(input('cover_image', ''));
        $congId = (int)input('congregation_id') ?: null;

        if (!$title) {
            Response::error('Title is required');
        }

        $courseId = Database::insert('courses', [
            'title' => $title,
            'slug' => adminUniqueSlug('courses', $title, $congId),
            'scope' => $congId ? 'congregation' : 'global',
            'description' => $description,
            'category' => $category,
            'difficulty' => $difficulty,
            'cover_image' => $coverImage ?: null,
            'congregation_id' => $congId,
            'is_published' => 0,
            'created_by' => $user['id'],
            'created_at' => date('Y-m-d H:i:s')
        ]);

        logActivity($user['id'], 'Created course: ' . $title);

        Response::success(['course_id' => $courseId], 'Course created successfully');
        break;

    case 'get_course':
        $courseId = (int)input('course_id');
        $course = Database::fetchOne("SELECT * FROM courses WHERE id = ?", [$courseId]);
        if (!$course) {
            Response::error('Course not found');
        }
        Response::success(['course' => $course]);
        break;

    case 'update_course':
        $courseId = (int)input('course_id');
        $title = trim(input('title'));
        $description = trim(input('description'));
        $category = trim(input('category'));
        $difficulty = input('difficulty', 'beginner');
        $coverImage = trim(input('cover_image', ''));

        if (!$courseId || !$title) {
            Response::error('Title is required');
        }

        Database::update('courses', [
            'title' => $title,
            'description' => $description,
            'category' => $category,
            'difficulty' => $difficulty,
            'cover_image' => $coverImage ?: null
        ], 'id = ?', [$courseId]);

        logActivity($user['id'], 'Updated course: ' . $title);

        Response::success([], 'Course updated successfully');
        break;

    case 'delete_course':
        $courseId = (int)input('course_id');
        if (!$courseId) {
            Response::error('Course ID required');
        }
        Database::delete('courses', 'id = ?', [$courseId]);
        logActivity($user['id'], 'Deleted course ID: ' . $courseId);
        Response::success([], 'Course deleted');
        break;

    // Content management (pages / articles / announcements / devotionals / resources)
    case 'add_content':
        $title = trim(input('title'));
        $type = trim(input('type')) ?: 'page';
        $body = input('body');
        $status = input('status', 'draft');

        if (!$title) {
            Response::error('Title is required');
        }

        $contentId = Database::insert('content', [
            'title' => $title,
            'type' => $type,
            'body' => $body,
            'status' => $status,
            'author' => $user['name'],
            'created_by' => $user['id'],
            'created_at' => date('Y-m-d H:i:s')
        ]);

        logActivity($user['id'], 'Created content: ' . $title);

        Response::success(['content_id' => $contentId], 'Content created successfully');
        break;

    case 'get_content':
        $contentId = (int)input('content_id');
        $content = Database::fetchOne("SELECT * FROM content WHERE id = ?", [$contentId]);
        if (!$content) {
            Response::error('Content not found');
        }
        Response::success(['content' => $content]);
        break;

    case 'update_content':
        $contentId = (int)input('content_id');
        $title = trim(input('title'));
        $type = trim(input('type')) ?: 'page';
        $body = input('body');
        $status = input('status', 'draft');

        if (!$contentId || !$title) {
            Response::error('Title is required');
        }

        Database::update('content', [
            'title' => $title,
            'type' => $type,
            'body' => $body,
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$contentId]);

        logActivity($user['id'], 'Updated content: ' . $title);

        Response::success([], 'Content updated successfully');
        break;

    case 'delete_content':
        $contentId = (int)input('content_id');
        if (!$contentId) {
            Response::error('Content ID required');
        }
        Database::delete('content', 'id = ?', [$contentId]);
        logActivity($user['id'], 'Deleted content ID: ' . $contentId);
        Response::success([], 'Content deleted');
        break;

    // Dashboard stats
    case 'get_stats':
        $stats = [
            'users' => Database::fetchColumn("SELECT COUNT(*) FROM users"),
            'users_today' => Database::fetchColumn("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()"),
            'congregations' => Database::fetchColumn("SELECT COUNT(*) FROM congregations WHERE status = 'active'"),
            'sermons' => Database::fetchColumn("SELECT COUNT(*) FROM sermons WHERE status = 'published'"),
            'courses' => Database::fetchColumn("SELECT COUNT(*) FROM courses WHERE is_published = 1"),
            'events' => Database::fetchColumn("SELECT COUNT(*) FROM events WHERE start_datetime >= CURDATE() AND status = 'published'"),
            'homecells' => Database::fetchColumn("SELECT COUNT(*) FROM homecells WHERE status = 'active'")
        ];

        Response::success(['stats' => $stats]);
        break;

    default:
        Response::error('Invalid action');
}

/**
 * Build a unique slug for a table (global, or per-congregation for courses).
 */
function adminUniqueSlug(string $table, string $base, ?int $congId = null): string {
    $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($base)), '-');
    if ($base === '') {
        $base = 'item';
    }
    $slug = $base;
    $n = 1;
    while (true) {
        if ($congId !== null) {
            $exists = Database::fetchColumn("SELECT COUNT(*) FROM `$table` WHERE slug = ? AND congregation_id = ?", [$slug, $congId]);
        } else {
            $exists = Database::fetchColumn("SELECT COUNT(*) FROM `$table` WHERE slug = ?", [$slug]);
        }
        if (!$exists) {
            break;
        }
        $slug = $base . '-' . (++$n);
    }
    return $slug;
}

function logActivity($userId, $action) {
    try {
        Database::insert('activity_log', [
            'user_id' => $userId,
            'action' => $action,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        // activity_log is optional — never let audit logging break an admin action
    }
}

/**
 * Convert a sermon duration from the admin form into stored seconds.
 * Accepts minutes (preferred form field) or a raw seconds value.
 */
function sermonParseDuration($minutes, $seconds): ?int {
    $minutes = is_string($minutes) ? trim($minutes) : $minutes;
    if ($minutes !== '' && $minutes !== null) {
        $m = (int)$minutes;
        return $m > 0 ? $m * 60 : null;
    }
    $seconds = is_string($seconds) ? trim($seconds) : $seconds;
    if ($seconds !== '' && $seconds !== null) {
        $s = (int)$seconds;
        return $s > 0 ? $s : null;
    }
    return null;
}

/**
 * Normalise a free-text scripture-reference field (one per line / comma
 * separated) into a JSON array string for storage, or null when empty.
 */
function sermonParseScripture($raw): ?string {
    $raw = trim((string)$raw);
    if ($raw === '') {
        return null;
    }
    $parts = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $raw))));
    return $parts ? json_encode($parts) : null;
}

/**
 * Convert an HTML datetime-local value (YYYY-MM-DDTHH:MM) into a MySQL
 * DATETIME, or null when empty.
 */
function livestreamParseDateTime($raw): ?string {
    $raw = trim((string)$raw);
    if ($raw === '') {
        return null;
    }
    $ts = strtotime($raw);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

/**
 * Resolve the series for a sermon: create a new series when a name is given,
 * otherwise use the selected id (or null for none).
 */
function sermonResolveSeries($seriesId, $newSeries, ?int $congId, $userId): ?int {
    $newSeries = trim((string)$newSeries);
    if ($newSeries !== '') {
        $existing = Database::fetchColumn(
            "SELECT id FROM sermon_series WHERE name = ? AND (congregation_id <=> ?)",
            [$newSeries, $congId]
        );
        if ($existing) {
            return (int)$existing;
        }
        return (int)Database::insert('sermon_series', [
            'name' => $newSeries,
            'congregation_id' => $congId,
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    $seriesId = (int)$seriesId;
    return $seriesId ?: null;
}
