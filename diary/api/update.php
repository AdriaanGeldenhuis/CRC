<?php
/**
 * CRC Diary API - Update Entry
 * Updates an existing diary entry
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';
require_once dirname(__DIR__) . '/helpers.php';

// Require POST, authentication, CSRF, and rate limit
Response::requirePost();
Auth::requireAuth();
CSRF::require();
Security::requireRateLimit('diary_update', 30, 60);

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
    "SELECT id FROM diary_entries WHERE id = ? AND user_id = ?",
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

try {
    Database::execute(
        "UPDATE diary_entries SET
            entry_date = ?,
            title = ?,
            content = ?,
            mood = ?,
            weather = ?,
            updated_at = NOW()
         WHERE id = ? AND user_id = ?",
        [
            $date,
            $title ?: null,
            $body ?: '',
            $mood ?: null,
            $weather ?: null,
            $entryId,
            $userId
        ]
    );

    // Update tags - remove old links and add new ones
    Database::execute("DELETE FROM diary_tag_links WHERE entry_id = ?", [$entryId]);

    if (!empty($tags)) {
        syncEntryTags($entryId, $userId, $tags);
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
