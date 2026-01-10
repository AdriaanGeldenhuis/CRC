<?php
/**
 * CRC Notifications API
 * POST /notifications/api/notifications.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();
Response::requirePost();
CSRF::require();

$user = Auth::user();
$action = input('action', 'list');

switch ($action) {
    case 'list':
        $page = max(1, (int)input('page', 1));
        $perPage = min(50, max(10, (int)input('per_page', 20)));
        $offset = ($page - 1) * $perPage;
        $unreadOnly = input('unread_only') === '1';

        $whereClause = "WHERE user_id = ?";
        $params = [$user['id']];

        if ($unreadOnly) {
            $whereClause .= " AND read_at IS NULL";
        }

        $notifications = Database::fetchAll(
            "SELECT * FROM notifications $whereClause ORDER BY created_at DESC LIMIT $perPage OFFSET $offset",
            $params
        );

        $totalCount = Database::fetchColumn(
            "SELECT COUNT(*) FROM notifications $whereClause",
            $params
        );

        $unreadCount = Database::fetchColumn(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL",
            [$user['id']]
        );

        Response::success([
            'notifications' => $notifications,
            'total' => (int)$totalCount,
            'unread_count' => (int)$unreadCount,
            'page' => $page,
            'per_page' => $perPage
        ]);
        break;

    case 'mark_read':
        $notificationId = (int)input('notification_id');

        if (!$notificationId) {
            Response::error('Notification ID required');
        }

        // Verify ownership
        $notification = Database::fetchOne(
            "SELECT * FROM notifications WHERE id = ? AND user_id = ?",
            [$notificationId, $user['id']]
        );

        if (!$notification) {
            Response::error('Notification not found');
        }

        if (!$notification['read_at']) {
            Database::update('notifications', [
                'read_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$notificationId]);
        }

        Response::success([], 'Notification marked as read');
        break;

    case 'mark_all_read':
        Database::query(
            "UPDATE notifications SET read_at = ? WHERE user_id = ? AND read_at IS NULL",
            [date('Y-m-d H:i:s'), $user['id']]
        );

        Response::success([], 'All notifications marked as read');
        break;

    case 'delete':
        $notificationId = (int)input('notification_id');

        if (!$notificationId) {
            Response::error('Notification ID required');
        }

        // Verify ownership and delete
        $deleted = Database::delete(
            'notifications',
            'id = ? AND user_id = ?',
            [$notificationId, $user['id']]
        );

        if (!$deleted) {
            Response::error('Notification not found');
        }

        Response::success([], 'Notification deleted');
        break;

    case 'clear_all':
        Database::delete('notifications', 'user_id = ?', [$user['id']]);
        Response::success([], 'All notifications cleared');
        break;

    case 'get_unread_count':
        $count = Database::fetchColumn(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL",
            [$user['id']]
        );

        Response::success(['count' => (int)$count]);
        break;

    case 'save_settings':
        $settings = [
            'user_id' => $user['id'],
            'email_enabled' => input('email_enabled') ? 1 : 0,
            'push_enabled' => input('push_enabled') ? 1 : 0,
            'event_reminders' => input('event_reminders') ? 1 : 0,
            'prayer_updates' => input('prayer_updates') ? 1 : 0,
            'homecell_updates' => input('homecell_updates') ? 1 : 0,
            'course_updates' => input('course_updates') ? 1 : 0,
            'sermon_updates' => input('sermon_updates') ? 1 : 0,
            'livestream_alerts' => input('livestream_alerts') ? 1 : 0,
            'announcements' => input('announcements') ? 1 : 0,
            'digest_frequency' => input('digest_frequency', 'daily'),
            'quiet_hours_start' => input('quiet_hours_start') ?: null,
            'quiet_hours_end' => input('quiet_hours_end') ?: null,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Check if settings exist
        $existing = Database::fetchColumn(
            "SELECT id FROM notification_settings WHERE user_id = ?",
            [$user['id']]
        );

        if ($existing) {
            unset($settings['user_id']);
            Database::update('notification_settings', $settings, 'user_id = ?', [$user['id']]);
        } else {
            $settings['created_at'] = date('Y-m-d H:i:s');
            Database::insert('notification_settings', $settings);
        }

        Response::success([], 'Settings saved');
        break;

    case 'get_settings':
        $settings = Database::fetchOne(
            "SELECT * FROM notification_settings WHERE user_id = ?",
            [$user['id']]
        );

        if (!$settings) {
            // Return defaults
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

        Response::success(['settings' => $settings]);
        break;

    case 'subscribe_push':
        $subscription = input('subscription');

        if (!$subscription) {
            Response::error('Subscription data required');
        }

        // Store push subscription
        $existing = Database::fetchColumn(
            "SELECT id FROM push_subscriptions WHERE user_id = ? AND endpoint = ?",
            [$user['id'], $subscription['endpoint'] ?? '']
        );

        if (!$existing) {
            Database::insert('push_subscriptions', [
                'user_id' => $user['id'],
                'endpoint' => $subscription['endpoint'] ?? '',
                'p256dh_key' => $subscription['keys']['p256dh'] ?? '',
                'auth_key' => $subscription['keys']['auth'] ?? '',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }

        Response::success([], 'Push subscription saved');
        break;

    case 'unsubscribe_push':
        $endpoint = input('endpoint');

        if ($endpoint) {
            Database::delete(
                'push_subscriptions',
                'user_id = ? AND endpoint = ?',
                [$user['id'], $endpoint]
            );
        }

        Response::success([], 'Push subscription removed');
        break;

    default:
        Response::error('Invalid action');
}
