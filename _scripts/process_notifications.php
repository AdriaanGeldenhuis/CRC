<?php
/**
 * CRC Scheduled Notification Worker
 *
 * Delivers time-based notifications that nothing else fires:
 *   1. Due rows in scheduled_notifications (e.g. livestream reminders).
 *   2. Due calendar_reminders for upcoming calendar events.
 *
 * Both are routed through Notify::send(), so they respect user preferences and
 * trigger Web Push just like any other notification.
 *
 * Run from cron every minute:
 *   * * * * * /usr/bin/php /path/to/CRC/_scripts/process_notifications.php >> /var/log/crc_notifications.log 2>&1
 *
 * Usage: php _scripts/process_notifications.php
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

// Load only the core libraries we need (avoids session/header side effects).
define('CRC_LOADED', true);
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/logger.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/push.php';
require_once __DIR__ . '/../core/notifications.php';

$now = date('Y-m-d H:i:s');
$dispatched = 0;

// ---- 1. Scheduled notifications (livestream reminders, etc.) ----
try {
    $due = Database::fetchAll(
        "SELECT * FROM scheduled_notifications
         WHERE sent_at IS NULL AND send_at <= ?
         ORDER BY send_at ASC LIMIT 500",
        [$now]
    );

    foreach ($due as $row) {
        Notify::send(
            (int) $row['user_id'],
            $row['type'] ?: 'system',
            $row['title'],
            $row['message'] ?? '',
            $row['link'] ?? null
        );
        Database::update('scheduled_notifications', ['sent_at' => date('Y-m-d H:i:s')], 'id = ?', [$row['id']]);
        $dispatched++;
    }

    echo count($due) . " scheduled notification(s) processed\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Scheduled notifications error: " . $e->getMessage() . "\n");
}

// ---- 2. Calendar event reminders ----
// calendar_reminders stores minutes_before; the fire time is derived from the
// linked event's start_datetime. Only remind for events that have not started.
try {
    $reminders = Database::fetchAll(
        "SELECT r.id AS reminder_id, r.user_id, r.minutes_before,
                e.title, e.start_datetime
         FROM calendar_reminders r
         JOIN calendar_events e ON e.id = r.calendar_event_id
         WHERE r.is_sent = 0
           AND e.status = 'active'
           AND e.start_datetime >= ?
           AND DATE_SUB(e.start_datetime, INTERVAL r.minutes_before MINUTE) <= ?
         LIMIT 500",
        [$now, $now]
    );

    foreach ($reminders as $rem) {
        $startTs = strtotime($rem['start_datetime']);
        $whenText = 'starts at ' . date('H:i', $startTs) . ' on ' . date('M j', $startTs);

        Notify::send(
            (int) $rem['user_id'],
            'event_reminder',
            'Event Reminder',
            $rem['title'] . ' ' . $whenText,
            '/calendar/'
        );
        Database::update('calendar_reminders', [
            'is_sent' => 1,
            'sent_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$rem['reminder_id']]);
        $dispatched++;
    }

    echo count($reminders) . " event reminder(s) processed\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Event reminders error: " . $e->getMessage() . "\n");
}

echo "Done. {$dispatched} notification(s) dispatched at {$now}.\n";
