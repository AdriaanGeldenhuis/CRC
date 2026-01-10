<?php
/**
 * CRC Congregation Events API
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();

$primaryCong = Auth::primaryCongregation();
if (!$primaryCong || !Auth::isCongregationAdmin($primaryCong['id'])) {
    Response::forbidden('Admin access required');
}

$congregationId = $primaryCong['id'];
$userId = Auth::id();

// Handle GET request for fetching single event
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = input('action');
    $eventId = (int) input('id');

    if ($action === 'get' && $eventId) {
        $event = Database::fetchOne(
            "SELECT * FROM events WHERE id = ? AND congregation_id = ?",
            [$eventId, $congregationId]
        );

        if (!$event) {
            Response::error('Event not found');
        }

        Response::success(['event' => $event]);
    }

    Response::error('Invalid request');
}

// Handle POST requests
Response::requirePost();
CSRF::require();

$action = input('action');

switch ($action) {
    case 'create':
        $title = trim(input('title'));
        $description = trim(input('description'));
        $startDatetime = input('start_datetime');
        $endDatetime = input('end_datetime');
        $location = trim(input('location'));
        $eventType = input('event_type') ?: 'general';
        $status = input('status') ?: 'draft';

        if (!$title) {
            Response::error('Title is required');
        }

        if (!$startDatetime) {
            Response::error('Start date/time is required');
        }

        // Validate status
        if (!in_array($status, ['draft', 'published'])) {
            $status = 'draft';
        }

        $eventId = Database::insert('events', [
            'congregation_id' => $congregationId,
            'user_id' => $userId,
            'title' => $title,
            'description' => $description ?: null,
            'start_datetime' => $startDatetime,
            'end_datetime' => $endDatetime ?: null,
            'location' => $location ?: null,
            'event_type' => $eventType,
            'scope' => 'congregation',
            'status' => $status,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        Logger::audit($userId, 'created_event', [
            'event_id' => $eventId,
            'congregation_id' => $congregationId
        ]);

        Response::success(['message' => 'Event created', 'event_id' => $eventId]);
        break;

    case 'update':
        $eventId = (int) input('id');
        $title = trim(input('title'));
        $description = trim(input('description'));
        $startDatetime = input('start_datetime');
        $endDatetime = input('end_datetime');
        $location = trim(input('location'));
        $eventType = input('event_type') ?: 'general';
        $status = input('status');

        if (!$eventId) {
            Response::error('Event ID required');
        }

        if (!$title) {
            Response::error('Title is required');
        }

        // Verify event belongs to this congregation
        $event = Database::fetchOne(
            "SELECT * FROM events WHERE id = ? AND congregation_id = ?",
            [$eventId, $congregationId]
        );

        if (!$event) {
            Response::error('Event not found');
        }

        $updateData = [
            'title' => $title,
            'description' => $description ?: null,
            'start_datetime' => $startDatetime,
            'end_datetime' => $endDatetime ?: null,
            'location' => $location ?: null,
            'event_type' => $eventType,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($status && in_array($status, ['draft', 'published', 'cancelled'])) {
            $updateData['status'] = $status;
        }

        Database::update('events', $updateData, 'id = ?', [$eventId]);

        Logger::audit($userId, 'updated_event', [
            'event_id' => $eventId,
            'congregation_id' => $congregationId
        ]);

        Response::success(['message' => 'Event updated']);
        break;

    case 'publish':
        $eventId = (int) input('id');

        if (!$eventId) {
            Response::error('Event ID required');
        }

        $event = Database::fetchOne(
            "SELECT * FROM events WHERE id = ? AND congregation_id = ?",
            [$eventId, $congregationId]
        );

        if (!$event) {
            Response::error('Event not found');
        }

        Database::update(
            'events',
            ['status' => 'published', 'updated_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$eventId]
        );

        Logger::audit($userId, 'published_event', [
            'event_id' => $eventId,
            'congregation_id' => $congregationId
        ]);

        Response::success(['message' => 'Event published']);
        break;

    case 'delete':
        $eventId = (int) input('id');

        if (!$eventId) {
            Response::error('Event ID required');
        }

        $event = Database::fetchOne(
            "SELECT * FROM events WHERE id = ? AND congregation_id = ?",
            [$eventId, $congregationId]
        );

        if (!$event) {
            Response::error('Event not found');
        }

        // Delete RSVPs first (foreign key)
        Database::delete('event_rsvps', 'event_id = ?', [$eventId]);

        // Delete event
        Database::delete('events', 'id = ?', [$eventId]);

        Logger::audit($userId, 'deleted_event', [
            'event_id' => $eventId,
            'event_title' => $event['title'],
            'congregation_id' => $congregationId
        ]);

        Response::success(['message' => 'Event deleted']);
        break;

    default:
        Response::error('Invalid action');
}
