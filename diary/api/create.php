<?php
/**
 * CRC Diary API - Create Entry
 * Creates a new diary entry
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

// Require authentication
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$user = Auth::user();
$userId = (int)$user['id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$date = trim((string)($input['date'] ?? ''));
$time = trim((string)($input['time'] ?? '00:00'));
$title = trim((string)($input['title'] ?? ''));
$body = trim((string)($input['body'] ?? ''));
$mood = trim((string)($input['mood'] ?? ''));
$weather = trim((string)($input['weather'] ?? ''));
$tags = $input['tags'] ?? [];
$reminderMinutes = (int)($input['reminder_minutes'] ?? 60);
$addToCalendar = (bool)($input['add_to_calendar'] ?? true);

if (empty($date)) {
    http_response_code(400);
    echo json_encode(['error' => 'date_required']);
    exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_date_format']);
    exit;
}

try {
    // Create diary entry
    $entryId = Database::insert('diary_entries', [
        'user_id' => $userId,
        'entry_date' => $date,
        'entry_time' => $time,
        'title' => $title ?: null,
        'body' => $body ?: null,
        'mood' => $mood ?: null,
        'weather' => $weather ?: null,
        'tags' => !empty($tags) ? json_encode($tags) : null,
        'reminder_minutes' => $reminderMinutes,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    // Optionally add to calendar
    if ($addToCalendar && $entryId) {
        try {
            $startDatetime = $date . ' ' . $time . ':00';
            $calendarEventId = Database::insert('calendar_events', [
                'user_id' => $userId,
                'title' => $title ?: 'Diary Entry',
                'description' => $body ? substr($body, 0, 200) : null,
                'start_datetime' => $startDatetime,
                'end_datetime' => $startDatetime,
                'event_type' => 'diary',
                'reminder_minutes' => $reminderMinutes,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // Link calendar event to diary
            if ($calendarEventId) {
                Database::execute(
                    "UPDATE diary_entries SET calendar_event_id = ? WHERE id = ?",
                    [$calendarEventId, $entryId]
                );
            }
        } catch (Throwable $e) {
            // Calendar is optional, don't fail if it errors
            error_log('Diary calendar sync error: ' . $e->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'id' => $entryId,
        'message' => 'Entry created'
    ]);

} catch (Throwable $e) {
    error_log('Diary create error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'server_error',
        'message' => 'Could not create entry'
    ]);
}
