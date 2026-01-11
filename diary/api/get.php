<?php
/**
 * CRC Diary API - Get Entry
 * Returns a single diary entry by ID
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
    $entry = Database::fetchOne(
        "SELECT id, title, body, entry_date, entry_time, mood, weather, tags, reminder_minutes, created_at, updated_at
         FROM diary_entries
         WHERE id = ? AND user_id = ?",
        [$entryId, $userId]
    );

    if (!$entry) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found']);
        exit;
    }

    // Map fields
    $entry['date'] = $entry['entry_date'];
    $entry['time'] = $entry['entry_time'];

    // Decode tags
    $entry['tags'] = $entry['tags'] ? json_decode($entry['tags'], true) : [];

    echo json_encode([
        'success' => true,
        'entry' => $entry
    ]);

} catch (Throwable $e) {
    error_log('Diary get error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'server_error',
        'message' => 'Could not fetch entry'
    ]);
}
