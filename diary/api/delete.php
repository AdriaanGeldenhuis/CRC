<?php
/**
 * CRC Diary API - Delete Entry
 * Deletes a diary entry
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

try {
    // Get calendar_event_id before deleting
    $entry = Database::fetchOne(
        "SELECT calendar_event_id FROM diary_entries WHERE id = ? AND user_id = ?",
        [$entryId, $userId]
    );

    if (!$entry) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found']);
        exit;
    }

    // Delete diary entry
    Database::execute(
        "DELETE FROM diary_entries WHERE id = ? AND user_id = ?",
        [$entryId, $userId]
    );

    // Delete linked calendar event if exists
    if ($entry['calendar_event_id']) {
        try {
            Database::execute(
                "DELETE FROM calendar_events WHERE id = ?",
                [$entry['calendar_event_id']]
            );
        } catch (Throwable $e) {
            error_log('Diary calendar delete error: ' . $e->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Entry deleted'
    ]);

} catch (Throwable $e) {
    error_log('Diary delete error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'server_error',
        'message' => 'Could not delete entry'
    ]);
}
