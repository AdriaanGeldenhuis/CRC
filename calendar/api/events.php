<?php
/**
 * CRC Calendar Events API
 * POST /calendar/api/events.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();
Response::requirePost();
CSRF::require();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
if (!$primaryCong) {
    Response::error('No congregation membership');
}

$action = input('action', 'create');

switch ($action) {
    case 'create':
        $title = input('title');
        $description = input('description');
        $startDate = input('start_date');
        $startTime = input('start_time');
        $endDate = input('end_date');
        $endTime = input('end_time');
        $allDay = input('all_day') ? 1 : 0;
        $location = input('location');
        $eventType = input('event_type', 'personal');
        $category = input('category', 'general');
        $color = input('color', '#4F46E5');
        $reminders = $_POST['reminders'] ?? [];
        $recurrence = input('recurrence', 'none');
        $recurrenceEnd = input('recurrence_end');

        // Validation
        if (!$title || !$startDate || !$startTime) {
            Response::error('Title, start date and time are required');
        }

        if (strlen($title) > 200) {
            Response::error('Title too long (max 200 characters)');
        }

        // Build datetime
        $startDatetime = $startDate . ' ' . $startTime . ':00';
        $endDatetime = null;
        if ($endDate && $endTime) {
            $endDatetime = $endDate . ' ' . $endTime . ':00';
        } elseif ($endTime) {
            $endDatetime = $startDate . ' ' . $endTime . ':00';
        }

        // Validate times
        if ($endDatetime && strtotime($endDatetime) < strtotime($startDatetime)) {
            Response::error('End time must be after start time');
        }

        if ($eventType === 'personal') {
            // Create personal calendar event
            $eventId = Database::insert('calendar_events', [
                'user_id' => $user['id'],
                'title' => $title,
                'description' => $description,
                'start_datetime' => $startDatetime,
                'end_datetime' => $endDatetime,
                'all_day' => $allDay,
                'location' => $location,
                'category' => $category,
                'color' => $color,
                'recurrence' => $recurrence,
                'recurrence_end' => $recurrence !== 'none' && $recurrenceEnd ? $recurrenceEnd : null,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // Add reminders
            if ($eventId && !empty($reminders)) {
                foreach ($reminders as $minutes) {
                    $minutes = (int)$minutes;
                    if ($minutes > 0) {
                        $reminderTime = date('Y-m-d H:i:s', strtotime($startDatetime) - ($minutes * 60));
                        Database::insert('calendar_reminders', [
                            'event_id' => $eventId,
                            'user_id' => $user['id'],
                            'minutes_before' => $minutes,
                            'reminder_datetime' => $reminderTime,
                            'status' => 'pending',
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                }
            }

            Response::success(['id' => $eventId], 'Event created');
        } else {
            // Congregation event - requires admin
            if (!Auth::isCongregationAdmin($primaryCong['id'])) {
                Response::forbidden('Admin access required');
            }

            $eventId = Database::insert('events', [
                'congregation_id' => $primaryCong['id'],
                'user_id' => $user['id'],
                'scope' => 'congregation',
                'title' => $title,
                'description' => $description,
                'start_datetime' => $startDatetime,
                'end_datetime' => $endDatetime,
                'all_day' => $allDay,
                'location' => $location,
                'category' => $category,
                'color' => $color,
                'status' => 'published',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            Response::success(['id' => $eventId], 'Congregation event created');
        }
        break;

    case 'update':
        $eventId = (int)input('event_id');
        $isPersonal = input('is_personal') === '1';

        if (!$eventId) {
            Response::error('Event ID required');
        }

        $title = input('title');
        $description = input('description');
        $startDate = input('start_date');
        $startTime = input('start_time');
        $endDate = input('end_date');
        $endTime = input('end_time');
        $allDay = input('all_day') ? 1 : 0;
        $location = input('location');
        $category = input('category', 'general');
        $color = input('color', '#4F46E5');
        $reminders = $_POST['reminders'] ?? [];

        if (!$title || !$startDate || !$startTime) {
            Response::error('Title, start date and time are required');
        }

        $startDatetime = $startDate . ' ' . $startTime . ':00';
        $endDatetime = null;
        if ($endDate && $endTime) {
            $endDatetime = $endDate . ' ' . $endTime . ':00';
        } elseif ($endTime) {
            $endDatetime = $startDate . ' ' . $endTime . ':00';
        }

        if ($isPersonal) {
            // Check ownership
            $event = Database::fetchOne(
                "SELECT * FROM calendar_events WHERE id = ? AND user_id = ?",
                [$eventId, $user['id']]
            );

            if (!$event) {
                Response::error('Event not found');
            }

            Database::update('calendar_events', [
                'title' => $title,
                'description' => $description,
                'start_datetime' => $startDatetime,
                'end_datetime' => $endDatetime,
                'all_day' => $allDay,
                'location' => $location,
                'category' => $category,
                'color' => $color,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$eventId]);

            // Update reminders
            Database::delete('calendar_reminders', 'event_id = ? AND user_id = ?', [$eventId, $user['id']]);
            if (!empty($reminders)) {
                foreach ($reminders as $minutes) {
                    $minutes = (int)$minutes;
                    if ($minutes > 0) {
                        $reminderTime = date('Y-m-d H:i:s', strtotime($startDatetime) - ($minutes * 60));
                        Database::insert('calendar_reminders', [
                            'event_id' => $eventId,
                            'user_id' => $user['id'],
                            'minutes_before' => $minutes,
                            'reminder_datetime' => $reminderTime,
                            'status' => 'pending',
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                }
            }
        } else {
            // Congregation event
            $event = Database::fetchOne(
                "SELECT * FROM events WHERE id = ?",
                [$eventId]
            );

            if (!$event) {
                Response::error('Event not found');
            }

            // Check permissions
            if ($event['user_id'] != $user['id'] && !Auth::isCongregationAdmin($event['congregation_id'])) {
                Response::forbidden('Permission denied');
            }

            Database::update('events', [
                'title' => $title,
                'description' => $description,
                'start_datetime' => $startDatetime,
                'end_datetime' => $endDatetime,
                'all_day' => $allDay,
                'location' => $location,
                'category' => $category,
                'color' => $color,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$eventId]);
        }

        Response::success([], 'Event updated');
        break;

    case 'delete':
        $eventId = (int)input('event_id');
        $isPersonal = input('is_personal') === '1';

        if (!$eventId) {
            Response::error('Event ID required');
        }

        if ($isPersonal) {
            // Check ownership
            $event = Database::fetchOne(
                "SELECT * FROM calendar_events WHERE id = ? AND user_id = ?",
                [$eventId, $user['id']]
            );

            if (!$event) {
                Response::error('Event not found');
            }

            // Delete reminders first
            Database::delete('calendar_reminders', 'event_id = ? AND user_id = ?', [$eventId, $user['id']]);
            // Delete event
            Database::delete('calendar_events', 'id = ?', [$eventId]);
        } else {
            // Congregation event
            $event = Database::fetchOne("SELECT * FROM events WHERE id = ?", [$eventId]);

            if (!$event) {
                Response::error('Event not found');
            }

            if ($event['user_id'] != $user['id'] && !Auth::isCongregationAdmin($event['congregation_id'])) {
                Response::forbidden('Permission denied');
            }

            Database::delete('events', 'id = ?', [$eventId]);
        }

        Response::success([], 'Event deleted');
        break;

    case 'list':
        $startDate = input('start');
        $endDate = input('end');

        if (!$startDate || !$endDate) {
            Response::error('Start and end dates required');
        }

        // Get congregation events
        $congEvents = Database::fetchAll(
            "SELECT e.*, 'congregation' as event_source
             FROM events e
             WHERE (e.scope = 'global' OR e.congregation_id = ?)
             AND e.status = 'published'
             AND DATE(e.start_datetime) BETWEEN ? AND ?
             ORDER BY e.start_datetime ASC",
            [$primaryCong['id'], $startDate, $endDate]
        );

        // Get personal events
        $personalEvents = Database::fetchAll(
            "SELECT *, 'personal' as event_source
             FROM calendar_events
             WHERE user_id = ?
             AND status = 'active'
             AND DATE(start_datetime) BETWEEN ? AND ?
             ORDER BY start_datetime ASC",
            [$user['id'], $startDate, $endDate]
        );

        $allEvents = array_merge($congEvents, $personalEvents);
        usort($allEvents, fn($a, $b) => strtotime($a['start_datetime']) - strtotime($b['start_datetime']));

        Response::success(['events' => $allEvents]);
        break;

    default:
        Response::error('Invalid action');
}
