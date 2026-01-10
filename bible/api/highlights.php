<?php
/**
 * CRC Bible Highlights API
 * POST /bible/api/highlights.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();
Response::requirePost();
CSRF::require();

$user = Auth::user();
$action = input('action', 'add');

switch ($action) {
    case 'add':
        $version = input('version', 'KJV');
        $bookNumber = (int)input('book_number');
        $chapter = (int)input('chapter');
        $verseStart = (int)input('verse_start');
        $verseEnd = (int)input('verse_end', $verseStart);
        $color = input('color', 'yellow');

        if (!$bookNumber || !$chapter || !$verseStart) {
            Response::error('Book, chapter, and verse are required');
        }

        $validColors = ['yellow', 'green', 'blue', 'pink', 'purple'];
        if (!in_array($color, $validColors)) {
            $color = 'yellow';
        }

        // Check if highlight exists
        $existing = Database::fetchOne(
            "SELECT id FROM bible_highlights
             WHERE user_id = ? AND version_code = ? AND book_number = ?
             AND chapter = ? AND verse_start = ?",
            [$user['id'], $version, $bookNumber, $chapter, $verseStart]
        );

        if ($existing) {
            // Update color
            Database::update('bible_highlights', [
                'color' => $color,
                'verse_end' => $verseEnd,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$existing['id']]);

            Response::success(['id' => $existing['id']], 'Highlight updated');
        } else {
            // Insert new
            $id = Database::insert('bible_highlights', [
                'user_id' => $user['id'],
                'version_code' => $version,
                'book_number' => $bookNumber,
                'chapter' => $chapter,
                'verse_start' => $verseStart,
                'verse_end' => $verseEnd,
                'color' => $color,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            Response::success(['id' => $id], 'Highlight added');
        }
        break;

    case 'remove':
        $highlightId = (int)input('highlight_id');

        if ($highlightId) {
            Database::delete(
                'bible_highlights',
                'id = ? AND user_id = ?',
                [$highlightId, $user['id']]
            );
        } else {
            // Remove by reference
            $version = input('version', 'KJV');
            $bookNumber = (int)input('book_number');
            $chapter = (int)input('chapter');
            $verseStart = (int)input('verse_start');

            Database::delete(
                'bible_highlights',
                'user_id = ? AND version_code = ? AND book_number = ? AND chapter = ? AND verse_start = ?',
                [$user['id'], $version, $bookNumber, $chapter, $verseStart]
            );
        }

        Response::success([], 'Highlight removed');
        break;

    case 'list':
        $version = input('version');
        $bookNumber = (int)input('book_number');
        $chapter = (int)input('chapter');

        $where = ['user_id = ?'];
        $params = [$user['id']];

        if ($version) {
            $where[] = 'version_code = ?';
            $params[] = $version;
        }

        if ($bookNumber) {
            $where[] = 'book_number = ?';
            $params[] = $bookNumber;
        }

        if ($chapter) {
            $where[] = 'chapter = ?';
            $params[] = $chapter;
        }

        $highlights = Database::fetchAll(
            "SELECT * FROM bible_highlights WHERE " . implode(' AND ', $where) . " ORDER BY book_number, chapter, verse_start",
            $params
        );

        Response::success(['highlights' => $highlights]);
        break;

    case 'get_all':
        // Get all highlights for export/sync
        $highlights = Database::fetchAll(
            "SELECT * FROM bible_highlights WHERE user_id = ? ORDER BY created_at DESC",
            [$user['id']]
        );

        Response::success(['highlights' => $highlights]);
        break;

    default:
        Response::error('Invalid action');
}
