<?php
/**
 * CRC Calendar Reminders API
 * POST /calendar/api/reminders.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();
Response::requirePost();
CSRF::require();

$user = Auth::user();
$action = input('action', 'list');

switch ($action) {
    case 'add':
        $eventId = (int)input('event_id');
        $minutesBefore = (int)input('minutes_before');

        if (!$eventId || !$minutesBefore) {
            Response::error('Event ID and minutes required');
        }

        // Check event ownership
        $event = Database::fetchOne(
            "SELECT * FROM calendar_events WHERE id = ? AND user_id = ?",
            [$eventId, $user['id']]
        );

        if (!$event) {
            Response::error('Event not found');
        }

        // Check if reminder already exists
        $existing = Database::fetchOne(
            "SELECT id FROM calendar_reminders WHERE event_id = ? AND user_id = ? AND minutes_before = ?",
            [$eventId, $user['id'], $minutesBefore]
        );

        if ($existing) {
            Response::error('Reminder already exists');
        }

        // Calculate reminder time
        $reminderTime = date('Y-m-d H:i:s', strtotime($event['start_datetime']) - ($minutesBefore * 60));

        $reminderId = Database::insert('calendar_reminders', [
            'event_id' => $eventId,
            'user_id' => $user['id'],
            'minutes_before' => $minutesBefore,
            'reminder_datetime' => $reminderTime,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        Response::success(['id' => $reminderId], 'Reminder added');
        break;

    case 'remove':
        $reminderId = (int)input('reminder_id');

        if (!$reminderId) {
            Response::error('Reminder ID required');
        }

        // Check ownership
        $reminder = Database::fetchOne(
            "SELECT * FROM calendar_reminders WHERE id = ? AND user_id = ?",
            [$reminderId, $user['id']]
        );

        if (!$reminder) {
            Response::error('Reminder not found');
        }

        Database::delete('calendar_reminders', 'id = ?', [$reminderId]);
        Response::success([], 'Reminder removed');
        break;

    case 'list':
        $eventId = (int)input('event_id');

        if ($eventId) {
            // Reminders for specific event
            $reminders = Database::fetchAll(
                "SELECT * FROM calendar_reminders WHERE event_id = ? AND user_id = ? ORDER BY minutes_before ASC",
                [$eventId, $user['id']]
            );
        } else {
            // All pending reminders
            $reminders = Database::fetchAll(
                "SELECT r.*, e.title as event_title, e.start_datetime
                 FROM calendar_reminders r
                 JOIN calendar_events e ON r.event_id = e.id
                 WHERE r.user_id = ?
                 AND r.status = 'pending'
                 AND r.reminder_datetime >= NOW()
                 ORDER BY r.reminder_datetime ASC
                 LIMIT 20",
                [$user['id']]
            );
        }

        Response::success(['reminders' => $reminders]);
        break;

    case 'dismiss':
        $reminderId = (int)input('reminder_id');

        if (!$reminderId) {
            Response::error('Reminder ID required');
        }

        $reminder = Database::fetchOne(
            "SELECT * FROM calendar_reminders WHERE id = ? AND user_id = ?",
            [$reminderId, $user['id']]
        );

        if (!$reminder) {
            Response::error('Reminder not found');
        }

        Database::update('calendar_reminders', [
            'status' => 'dismissed'
        ], 'id = ?', [$reminderId]);

        Response::success([], 'Reminder dismissed');
        break;

    case 'get_due':
        // Get reminders that are due (for notification check)
        $dueReminders = Database::fetchAll(
            "SELECT r.*, e.title as event_title, e.start_datetime, e.location
             FROM calendar_reminders r
             JOIN calendar_events e ON r.event_id = e.id
             WHERE r.user_id = ?
             AND r.status = 'pending'
             AND r.reminder_datetime <= NOW()
             ORDER BY r.reminder_datetime DESC
             LIMIT 10",
            [$user['id']]
        );

        // Mark as sent
        foreach ($dueReminders as $reminder) {
            Database::update('calendar_reminders', [
                'status' => 'sent',
                'sent_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$reminder['id']]);
        }

        Response::success(['reminders' => $dueReminders]);
        break;

    default:
        Response::error('Invalid action');
}
