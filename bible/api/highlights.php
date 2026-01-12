<?php
/**
 * CRC Bible Highlights API
 * POST /bible/api/highlights.php
 *
 * Uses numbered colors (1-6) like OAC:
 * 1=Pink, 2=Orange, 3=Yellow, 4=Green, 5=Blue, 6=Purple
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();
Response::requirePost();
CSRF::require();

$user = Auth::user();
$action = input('action', 'add');

switch ($action) {
    case 'add':
        $bookNumber = (int)input('book_number');
        $chapter = (int)input('chapter');
        $verse = (int)input('verse');
        $color = (int)input('color', 3); // Default to yellow (3)

        if (!$bookNumber || !$chapter || !$verse) {
            Response::error('Book, chapter, and verse are required');
        }

        // Validate color (1-6)
        if ($color < 1 || $color > 6) {
            $color = 3; // Default to yellow
        }

        // Check if highlight exists
        $existing = Database::fetchOne(
            "SELECT id FROM bible_highlights
             WHERE user_id = ? AND book_number = ? AND chapter = ? AND verse_start = ?",
            [$user['id'], $bookNumber, $chapter, $verse]
        );

        if ($existing) {
            // Update color
            Database::update('bible_highlights', [
                'color' => $color,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$existing['id']]);

            Response::success(['id' => $existing['id']], 'Highlight updated');
        } else {
            // Insert new
            $id = Database::insert('bible_highlights', [
                'user_id' => $user['id'],
                'version_code' => 'KJV',
                'book_number' => $bookNumber,
                'chapter' => $chapter,
                'verse_start' => $verse,
                'verse_end' => $verse,
                'color' => $color,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            Response::success(['id' => $id], 'Highlight added');
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
            'bible_highlights',
            'user_id = ? AND book_number = ? AND chapter = ? AND verse_start = ?',
            [$user['id'], $bookNumber, $chapter, $verse]
        );

        Response::success([], 'Highlight removed');
        break;

    case 'list':
        $bookNumber = (int)input('book_number');
        $chapter = (int)input('chapter');

        $where = ['user_id = ?'];
        $params = [$user['id']];

        if ($bookNumber) {
            $where[] = 'book_number = ?';
            $params[] = $bookNumber;
        }

        if ($chapter) {
            $where[] = 'chapter = ?';
            $params[] = $chapter;
        }

        $highlights = Database::fetchAll(
            "SELECT id, book_number, chapter, verse_start as verse, color
             FROM bible_highlights
             WHERE " . implode(' AND ', $where) . "
             ORDER BY book_number, chapter, verse_start",
            $params
        );

        Response::success(['highlights' => $highlights]);
        break;

    case 'get_all':
        // Get all highlights for initial load
        $highlights = Database::fetchAll(
            "SELECT id, book_number, chapter, verse_start as verse, color
             FROM bible_highlights
             WHERE user_id = ?
             ORDER BY book_number, chapter, verse_start",
            [$user['id']]
        );

        Response::success(['highlights' => $highlights]);
        break;

    default:
        Response::error('Invalid action');
}
