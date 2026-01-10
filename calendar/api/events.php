<?php
/**
 * CRC Calendar Events API
 * POST /calendar/api/events.php
 *
 * Integrates: Personal events, Congregation events, Morning Study, Homecells, Courses
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
        if (!$title || !$startDate) {
            Response::error('Title and start date are required');
        }

        if (strlen($title) > 200) {
            Response::error('Title too long (max 200 characters)');
        }

        // Build datetime
        if ($allDay) {
            $startDatetime = $startDate . ' 00:00:00';
            $endDatetime = ($endDate ?: $startDate) . ' 23:59:59';
        } else {
            if (!$startTime) {
                Response::error('Start time is required for non all-day events');
            }
            $startDatetime = $startDate . ' ' . $startTime . ':00';
            $endDatetime = null;
            if ($endDate && $endTime) {
                $endDatetime = $endDate . ' ' . $endTime . ':00';
            } elseif ($endTime) {
                $endDatetime = $startDate . ' ' . $endTime . ':00';
            }
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

        if (!$title || !$startDate) {
            Response::error('Title and start date are required');
        }

        if ($allDay) {
            $startDatetime = $startDate . ' 00:00:00';
            $endDatetime = ($endDate ?: $startDate) . ' 23:59:59';
        } else {
            if (!$startTime) {
                Response::error('Start time is required');
            }
            $startDatetime = $startDate . ' ' . $startTime . ':00';
            $endDatetime = null;
            if ($endDate && $endTime) {
                $endDatetime = $endDate . ' ' . $endTime . ':00';
            } elseif ($endTime) {
                $endDatetime = $startDate . ' ' . $endTime . ':00';
            }
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
    case 'list_all':
        $startDate = input('start');
        $endDate = input('end');
        $sources = input('sources') ? explode(',', input('sources')) : ['all'];

        if (!$startDate || !$endDate) {
            Response::error('Start and end dates required');
        }

        $allEvents = [];

        // Get congregation events (global + congregation scope)
        if (in_array('all', $sources) || in_array('events', $sources) || in_array('congregation', $sources)) {
            try {
                $congEvents = Database::fetchAll(
                    "SELECT e.*,
                            'event' as source_type,
                            CASE
                                WHEN e.scope = 'global' THEN 'global'
                                ELSE 'congregation'
                            END as event_category,
                            COALESCE(e.color, '#10B981') as display_color
                     FROM events e
                     WHERE (e.scope = 'global' OR e.congregation_id = ?)
                     AND e.status = 'published'
                     AND DATE(e.start_datetime) BETWEEN ? AND ?
                     ORDER BY e.start_datetime ASC",
                    [$primaryCong['id'], $startDate, $endDate]
                ) ?: [];

                foreach ($congEvents as $event) {
                    $allEvents[] = [
                        'id' => $event['id'],
                        'title' => $event['title'],
                        'description' => $event['description'],
                        'start' => $event['start_datetime'],
                        'end' => $event['end_datetime'],
                        'all_day' => (bool)($event['all_day'] ?? false),
                        'location' => $event['location'] ?? null,
                        'color' => $event['display_color'],
                        'source' => $event['source_type'],
                        'category' => $event['event_category'],
                        'editable' => false,
                        'url' => '/gospel_media/event.php?id=' . $event['id']
                    ];
                }
            } catch (Exception $e) {}
        }

        // Get personal calendar events
        if (in_array('all', $sources) || in_array('personal', $sources)) {
            try {
                $personalEvents = Database::fetchAll(
                    "SELECT *, 'personal' as source_type
                     FROM calendar_events
                     WHERE user_id = ?
                     AND status = 'active'
                     AND DATE(start_datetime) BETWEEN ? AND ?
                     ORDER BY start_datetime ASC",
                    [$user['id'], $startDate, $endDate]
                ) ?: [];

                foreach ($personalEvents as $event) {
                    $allEvents[] = [
                        'id' => $event['id'],
                        'title' => $event['title'],
                        'description' => $event['description'],
                        'start' => $event['start_datetime'],
                        'end' => $event['end_datetime'],
                        'all_day' => (bool)($event['all_day'] ?? false),
                        'location' => $event['location'] ?? null,
                        'color' => $event['color'] ?? '#F59E0B',
                        'source' => 'personal',
                        'category' => $event['category'] ?? 'general',
                        'editable' => true,
                        'url' => '/calendar/event.php?id=' . $event['id']
                    ];
                }
            } catch (Exception $e) {}
        }

        // Get Morning Study sessions
        if (in_array('all', $sources) || in_array('morning_study', $sources)) {
            try {
                $morningSessions = Database::fetchAll(
                    "SELECT ms.*,
                            mue.started_at, mue.completed_at,
                            mr.reminder_time
                     FROM morning_sessions ms
                     LEFT JOIN morning_user_entries mue ON ms.id = mue.session_id AND mue.user_id = ?
                     LEFT JOIN morning_reminders mr ON mr.user_id = ? AND mr.is_active = 1
                     WHERE ms.session_date BETWEEN ? AND ?
                     ORDER BY ms.session_date ASC",
                    [$user['id'], $user['id'], $startDate, $endDate]
                ) ?: [];

                foreach ($morningSessions as $session) {
                    $isCompleted = !empty($session['completed_at']);
                    $reminderTime = $session['reminder_time'] ?? '06:00:00';

                    $allEvents[] = [
                        'id' => 'morning_' . $session['id'],
                        'title' => 'Morning Study' . ($session['scripture'] ? ': ' . $session['scripture'] : ''),
                        'description' => $session['devotional'] ?? 'Daily morning devotional',
                        'start' => $session['session_date'] . ' ' . $reminderTime,
                        'end' => $session['session_date'] . ' ' . date('H:i:s', strtotime($reminderTime) + 1800),
                        'all_day' => false,
                        'location' => null,
                        'color' => $isCompleted ? '#10B981' : '#8B5CF6',
                        'source' => 'morning_study',
                        'category' => 'devotional',
                        'editable' => false,
                        'completed' => $isCompleted,
                        'url' => '/morning_watch/'
                    ];
                }
            } catch (Exception $e) {}
        }

        // Get Homecell meetings
        if (in_array('all', $sources) || in_array('homecell', $sources)) {
            try {
                // Get user's homecell membership
                $userHomecell = Database::fetchOne(
                    "SELECT h.* FROM homecells h
                     JOIN homecell_members hm ON h.id = hm.homecell_id
                     WHERE hm.user_id = ? AND hm.status = 'active' AND h.status = 'active'",
                    [$user['id']]
                );

                if ($userHomecell) {
                    $homecellMeetings = Database::fetchAll(
                        "SELECT hm.*, h.name as homecell_name, h.location as homecell_location,
                                h.meeting_time
                         FROM homecell_meetings hm
                         JOIN homecells h ON hm.homecell_id = h.id
                         WHERE hm.homecell_id = ?
                         AND hm.meeting_date BETWEEN ? AND ?
                         ORDER BY hm.meeting_date ASC",
                        [$userHomecell['id'], $startDate, $endDate]
                    ) ?: [];

                    foreach ($homecellMeetings as $meeting) {
                        $meetingTime = $meeting['meeting_time'] ?? '19:00:00';
                        $endTime = date('H:i:s', strtotime($meetingTime) + 7200); // 2 hours

                        $allEvents[] = [
                            'id' => 'homecell_' . $meeting['id'],
                            'title' => 'Homecell: ' . ($meeting['homecell_name'] ?? 'Meeting'),
                            'description' => $meeting['topic'] ?? 'Weekly homecell gathering',
                            'start' => $meeting['meeting_date'] . ' ' . $meetingTime,
                            'end' => $meeting['meeting_date'] . ' ' . $endTime,
                            'all_day' => false,
                            'location' => $meeting['homecell_location'] ?? null,
                            'color' => '#EC4899',
                            'source' => 'homecell',
                            'category' => 'homecell',
                            'editable' => false,
                            'url' => '/homecells/view.php?id=' . $meeting['homecell_id']
                        ];
                    }
                }
            } catch (Exception $e) {}
        }

        // Get Course/Learning sessions
        if (in_array('all', $sources) || in_array('courses', $sources)) {
            try {
                $enrolledCourses = Database::fetchAll(
                    "SELECT c.*, uce.enrolled_at, uce.status as enrollment_status,
                            uce.progress_percent
                     FROM courses c
                     JOIN user_course_enrollments uce ON c.id = uce.course_id
                     WHERE uce.user_id = ? AND uce.status = 'active'",
                    [$user['id']]
                ) ?: [];

                foreach ($enrolledCourses as $course) {
                    // Get upcoming lessons with due dates
                    $lessons = Database::fetchAll(
                        "SELECT l.*, ulp.status as lesson_status, ulp.completed_at
                         FROM lessons l
                         LEFT JOIN user_lesson_progress ulp ON l.id = ulp.lesson_id AND ulp.user_id = ?
                         WHERE l.course_id = ?
                         AND (ulp.status IS NULL OR ulp.status != 'completed')
                         ORDER BY l.order_index ASC
                         LIMIT 5",
                        [$user['id'], $course['id']]
                    ) ?: [];

                    // Add course start if within range
                    $enrolledDate = date('Y-m-d', strtotime($course['enrolled_at']));
                    if ($enrolledDate >= $startDate && $enrolledDate <= $endDate) {
                        $allEvents[] = [
                            'id' => 'course_start_' . $course['id'],
                            'title' => 'Course Started: ' . $course['title'],
                            'description' => 'You enrolled in this course',
                            'start' => $course['enrolled_at'],
                            'end' => null,
                            'all_day' => true,
                            'location' => null,
                            'color' => '#06B6D4',
                            'source' => 'course',
                            'category' => 'learning',
                            'editable' => false,
                            'url' => '/learning/course.php?id=' . $course['id']
                        ];
                    }
                }
            } catch (Exception $e) {}
        }

        // Sort all events by start time
        usort($allEvents, function($a, $b) {
            return strtotime($a['start']) - strtotime($b['start']);
        });

        Response::success(['events' => $allEvents]);
        break;

    case 'get_sources':
        // Return available event sources for filtering
        $sources = [
            ['id' => 'personal', 'name' => 'Personal Events', 'color' => '#F59E0B', 'enabled' => true],
            ['id' => 'congregation', 'name' => 'Congregation Events', 'color' => '#10B981', 'enabled' => true],
            ['id' => 'global', 'name' => 'Global Events', 'color' => '#4F46E5', 'enabled' => true],
            ['id' => 'morning_study', 'name' => 'Morning Study', 'color' => '#8B5CF6', 'enabled' => true],
            ['id' => 'homecell', 'name' => 'Homecell Meetings', 'color' => '#EC4899', 'enabled' => true],
            ['id' => 'courses', 'name' => 'Learning/Courses', 'color' => '#06B6D4', 'enabled' => true]
        ];

        Response::success(['sources' => $sources]);
        break;

    default:
        Response::error('Invalid action');
}
