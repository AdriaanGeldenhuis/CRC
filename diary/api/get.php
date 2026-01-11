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
        "SELECT id, title, content, entry_date, mood, weather, created_at, updated_at
         FROM diary_entries
         WHERE id = ? AND user_id = ?",
        [$entryId, $userId]
    );

    if (!$entry) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found']);
        exit;
    }

    // Map fields for JS compatibility
    $entry['date'] = $entry['entry_date'];
    $entry['time'] = date('H:i', strtotime($entry['created_at']));
    $entry['body'] = $entry['content'];

    // Get tags for this entry
    $tags = Database::fetchAll(
        "SELECT t.name FROM diary_tags t
         JOIN diary_tag_links l ON t.id = l.tag_id
         WHERE l.entry_id = ?",
        [$entryId]
    ) ?: [];
    $entry['tags'] = array_column($tags, 'name');

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
