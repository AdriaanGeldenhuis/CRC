<?php
/**
 * CRC Diary API - Create Entry
 * Creates a new diary entry
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';
require_once dirname(__DIR__) . '/helpers.php';

// Require POST, authentication, CSRF, and rate limit
Response::requirePost();
Auth::requireAuth();
CSRF::require();
Security::requireRateLimit('diary_create', 30, 60);

$user = Auth::user();
$userId = (int)$user['id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$date = trim((string)($input['date'] ?? ''));
$title = trim((string)($input['title'] ?? ''));
$body = trim((string)($input['body'] ?? ''));
$mood = trim((string)($input['mood'] ?? ''));
$weather = trim((string)($input['weather'] ?? ''));
$tags = $input['tags'] ?? [];

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
        'title' => $title ?: null,
        'content' => $body ?: '',
        'mood' => $mood ?: null,
        'weather' => $weather ?: null
    ]);

    // Add tags
    if (!empty($tags) && $entryId) {
        syncEntryTags((int)$entryId, $userId, $tags);
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
