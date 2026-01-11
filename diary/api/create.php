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
        foreach ($tags as $tagName) {
            $tagName = trim($tagName);
            if (empty($tagName)) continue;

            // Get or create tag
            $tag = Database::fetchOne(
                "SELECT id FROM diary_tags WHERE user_id = ? AND name = ?",
                [$userId, $tagName]
            );

            if (!$tag) {
                $tagId = Database::insert('diary_tags', [
                    'user_id' => $userId,
                    'name' => $tagName
                ]);
            } else {
                $tagId = $tag['id'];
            }

            // Link tag to entry
            if ($tagId) {
                try {
                    Database::insert('diary_tag_links', [
                        'entry_id' => $entryId,
                        'tag_id' => $tagId
                    ]);
                } catch (Throwable $e) {
                    // Ignore duplicate tag links
                }
            }
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
