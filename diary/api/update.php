<?php
/**
 * CRC Diary API - Update Entry
 * Updates an existing diary entry
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
$entryId = (int)($_GET['id'] ?? 0);

if ($entryId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'bad_request']);
    exit;
}

// Verify ownership
$existing = Database::fetchOne(
    "SELECT id, calendar_event_id FROM diary_entries WHERE id = ? AND user_id = ?",
    [$entryId, $userId]
);

if (!$existing) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

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

if (empty($date)) {
    http_response_code(400);
    echo json_encode(['error' => 'date_required']);
    exit;
}

try {
    Database::execute(
        "UPDATE diary_entries SET
            entry_date = ?,
            entry_time = ?,
            title = ?,
            body = ?,
            mood = ?,
            weather = ?,
            tags = ?,
            reminder_minutes = ?,
            updated_at = NOW()
         WHERE id = ? AND user_id = ?",
        [
            $date,
            $time,
            $title ?: null,
            $body ?: null,
            $mood ?: null,
            $weather ?: null,
            !empty($tags) ? json_encode($tags) : null,
            $reminderMinutes,
            $entryId,
            $userId
        ]
    );

    // Update linked calendar event if exists
    if ($existing['calendar_event_id']) {
        try {
            $startDatetime = $date . ' ' . $time . ':00';
            Database::execute(
                "UPDATE calendar_events SET
                    title = ?,
                    description = ?,
                    start_datetime = ?,
                    end_datetime = ?,
                    reminder_minutes = ?
                 WHERE id = ?",
                [
                    $title ?: 'Diary Entry',
                    $body ? substr($body, 0, 200) : null,
                    $startDatetime,
                    $startDatetime,
                    $reminderMinutes,
                    $existing['calendar_event_id']
                ]
            );
        } catch (Throwable $e) {
            error_log('Diary calendar update error: ' . $e->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Entry updated'
    ]);

} catch (Throwable $e) {
    error_log('Diary update error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'server_error',
        'message' => 'Could not update entry'
    ]);
}
