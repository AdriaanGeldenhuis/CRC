<?php
/**
 * CRC Bible Bookmarks API
 * POST /bible/api/bookmarks.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();
Response::requirePost();
CSRF::require();

$user = Auth::user();
$action = input('action', 'toggle');

switch ($action) {
    case 'add':
    case 'toggle':
        $bookNumber = (int)input('book_number');
        $chapter = (int)input('chapter');
        $verse = (int)input('verse');

        if (!$bookNumber || !$chapter || !$verse) {
            Response::error('Book, chapter, and verse are required');
        }

        // Check if bookmark exists
        $existing = Database::fetchOne(
            "SELECT id FROM bible_bookmarks
             WHERE user_id = ? AND book_number = ? AND chapter = ? AND verse_start = ?",
            [$user['id'], $bookNumber, $chapter, $verse]
        );

        if ($existing) {
            // Toggle: remove if exists
            Database::delete('bible_bookmarks', 'id = ?', [$existing['id']]);
            Response::success(['bookmarked' => false], 'Bookmark removed');
        } else {
            // Add new bookmark
            $id = Database::insert('bible_bookmarks', [
                'user_id' => $user['id'],
                'version_code' => 'KJV',
                'book_number' => $bookNumber,
                'chapter' => $chapter,
                'verse_start' => $verse,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            Response::success(['id' => $id, 'bookmarked' => true], 'Bookmark added');
        }
        break;

    case 'remove':
        $bookNumber = (int)input('book_number');
        $chapter = (int)input('chapter');
        $verse = (int)input('verse');

        if (!$bookNumber || !$chapter || !$verse) {
            Response::error('Book, chapter, and verse are required');
        }

        Database::delete(
            'bible_bookmarks',
            'user_id = ? AND book_number = ? AND chapter = ? AND verse_start = ?',
            [$user['id'], $bookNumber, $chapter, $verse]
        );

        Response::success(['bookmarked' => false], 'Bookmark removed');
        break;

    case 'list':
    case 'get_all':
        $bookmarks = Database::fetchAll(
            "SELECT id, book_number, chapter, verse_start as verse, created_at
             FROM bible_bookmarks
             WHERE user_id = ?
             ORDER BY created_at DESC",
            [$user['id']]
        );

        Response::success(['bookmarks' => $bookmarks]);
        break;

    default:
        Response::error('Invalid action');
}
