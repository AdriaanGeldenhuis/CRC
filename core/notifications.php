<?php
/**
 * CRC Notification Helper
 *
 * Single entry point for creating notifications. Centralises:
 *   - per-user preference enforcement (notification_settings)
 *   - consistent icon / priority / payload storage
 *   - Web Push dispatch (via Push) with quiet-hours suppression
 *
 * Producers should call Notify::send() / Notify::sendMany() instead of writing
 * raw INSERTs into the notifications table.
 */

// Prevent direct access
if (!defined('CRC_LOADED')) {
    die('Direct access not permitted');
}

class Notify {
    /**
     * Map a notification type to the notification_settings column that controls
     * it. A null value means the notification is always delivered (e.g. account
     * / approval outcomes the user cannot opt out of).
     */
    const TYPE_SETTINGS = [
        'new_comment'         => 'social_updates',
        'new_reaction'        => 'social_updates',
        'homecell_join'       => 'homecell_updates',
        'homecell_meeting'    => 'homecell_updates',
        'announcement'        => 'announcements',
        'event_reminder'      => 'event_reminders',
        'prayer_answered'     => 'prayer_updates',
        'new_sermon'          => 'sermon_updates',
        'course_update'       => 'course_updates',
        'livestream_reminder' => 'livestream_alerts',
        'approval_status'     => null,
        'approval_request'    => null,
        'welcome'             => null,
        'system'              => null,
    ];

    /** Map a type to an icon key understood by the notification UI. */
    const TYPE_ICON = [
        'new_comment'         => 'comment',
        'new_reaction'        => 'heart',
        'homecell_join'       => 'homecell_join',
        'homecell_meeting'    => 'homecell_meeting',
        'announcement'        => 'announcement',
        'event_reminder'      => 'event_reminder',
        'prayer_answered'     => 'prayer_answered',
        'new_sermon'          => 'new_sermon',
        'course_update'       => 'course_complete',
        'livestream_reminder' => 'livestream_start',
        'approval_status'     => 'check',
        'approval_request'    => 'homecell_join',
        'welcome'             => 'welcome',
        'system'              => 'system',
    ];

    /**
     * Create a notification for a single user.
     *
     * @param array $opts  icon, priority ('low'|'normal'|'high'), payload (array),
     *                     skip_push (bool)
     * @return int|null    notification id, or null if suppressed by preferences
     */
    public static function send(int $userId, string $type, string $title, string $message, ?string $link = null, array $opts = []): ?int {
        if ($userId <= 0) {
            return null;
        }

        $prefs = self::prefs($userId);

        // Respect the per-type in-app preference. A null mapping = always send.
        $col = self::TYPE_SETTINGS[$type] ?? null;
        if ($col !== null && empty($prefs[$col])) {
            return null;
        }

        $id = (int) Database::insert('notifications', [
            'user_id'    => $userId,
            'type'       => $type,
            'title'      => $title,
            'message'    => $message,
            'link'       => $link,
            'icon'       => $opts['icon'] ?? (self::TYPE_ICON[$type] ?? null),
            'priority'   => $opts['priority'] ?? 'normal',
            'payload'    => isset($opts['payload']) ? json_encode($opts['payload']) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Dispatch a Web Push if the user has push enabled and is not in their
        // quiet hours. Push failures must never block notification creation.
        if (empty($opts['skip_push']) && !empty($prefs['push_enabled']) && !self::inQuietHours($prefs)) {
            try {
                if (class_exists('Push')) {
                    Push::sendToUser($userId, [
                        'title' => $title,
                        'body'  => $message,
                        'url'   => $link ?: '/notifications/',
                        'tag'   => $type,
                    ]);
                }
            } catch (\Throwable $e) {
                if (class_exists('Logger')) {
                    Logger::error('Push dispatch failed: ' . $e->getMessage());
                }
            }
        }

        return $id;
    }

    /**
     * Create the same notification for many users. Returns the number actually
     * delivered (after preference filtering).
     */
    public static function sendMany(array $userIds, string $type, string $title, string $message, ?string $link = null, array $opts = []): int {
        $count = 0;
        foreach (array_unique(array_map('intval', $userIds)) as $uid) {
            if ($uid > 0 && self::send($uid, $type, $title, $message, $link, $opts) !== null) {
                $count++;
            }
        }
        return $count;
    }

    /** Default preferences when a user has no notification_settings row yet. */
    public static function defaults(): array {
        return [
            'email_enabled'     => 1,
            'push_enabled'      => 1,
            'event_reminders'   => 1,
            'prayer_updates'    => 1,
            'homecell_updates'  => 1,
            'course_updates'    => 1,
            'sermon_updates'    => 1,
            'livestream_alerts' => 1,
            'announcements'     => 1,
            'social_updates'    => 1,
            'digest_frequency'  => 'daily',
            'quiet_hours_start' => null,
            'quiet_hours_end'   => null,
        ];
    }

    /** Fetch (and cache for the request) a user's preferences, or defaults. */
    private static function prefs(int $userId): array {
        static $cache = [];
        if (isset($cache[$userId])) {
            return $cache[$userId];
        }

        $row = null;
        try {
            $row = Database::fetchOne("SELECT * FROM notification_settings WHERE user_id = ?", [$userId]);
        } catch (\Throwable $e) {
            $row = null;
        }

        return $cache[$userId] = ($row ?: self::defaults());
    }

    /** True if the current local time falls within the user's quiet hours. */
    private static function inQuietHours(array $prefs): bool {
        $start = $prefs['quiet_hours_start'] ?? null;
        $end   = $prefs['quiet_hours_end'] ?? null;
        if (empty($start) || empty($end)) {
            return false;
        }

        $now = date('H:i');
        $s = substr((string) $start, 0, 5);
        $e = substr((string) $end, 0, 5);
        if ($s === $e) {
            return false;
        }
        if ($s < $e) {
            return $now >= $s && $now < $e;   // same-day window (e.g. 13:00-14:00)
        }
        return $now >= $s || $now < $e;       // overnight window (e.g. 22:00-07:00)
    }
}
