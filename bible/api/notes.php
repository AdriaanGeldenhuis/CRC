<?php
/**
 * CRC Bible Notes API
 * POST /bible/api/notes.php
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
        $content = input('content');

        if (!$bookNumber || !$chapter || !$verseStart) {
            Response::error('Book, chapter, and verse are required');
        }

        if (!$content) {
            Response::error('Note content is required');
        }

        if (strlen($content) > 10000) {
            Response::error('Note is too long (max 10000 characters)');
        }

        $id = Database::insert('bible_notes', [
            'user_id' => $user['id'],
            'version_code' => $version,
            'book_number' => $bookNumber,
            'chapter' => $chapter,
            'verse_start' => $verseStart,
            'verse_end' => $verseEnd,
            'content' => $content,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        Response::success(['id' => $id], 'Note added');
        break;

    case 'update':
        $noteId = (int)input('note_id');
        $content = input('content');

        if (!$noteId) {
            Response::error('Note ID required');
        }

        $note = Database::fetchOne(
            "SELECT * FROM bible_notes WHERE id = ? AND user_id = ?",
            [$noteId, $user['id']]
        );

        if (!$note) {
            Response::error('Note not found');
        }

        Database::update('bible_notes', [
            'content' => $content,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$noteId]);

        Response::success([], 'Note updated');
        break;

    case 'delete':
        $noteId = (int)input('note_id');

        if (!$noteId) {
            Response::error('Note ID required');
        }

        Database::delete(
            'bible_notes',
            'id = ? AND user_id = ?',
            [$noteId, $user['id']]
        );

        Response::success([], 'Note deleted');
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

        $notes = Database::fetchAll(
            "SELECT * FROM bible_notes WHERE " . implode(' AND ', $where) . " ORDER BY book_number, chapter, verse_start DESC",
            $params
        );

        Response::success(['notes' => $notes]);
        break;

    case 'get_all':
        // Get all notes for export/sync
        $notes = Database::fetchAll(
            "SELECT * FROM bible_notes WHERE user_id = ? ORDER BY created_at DESC",
            [$user['id']]
        );

        Response::success(['notes' => $notes]);
        break;

    case 'search':
        $query = input('query');

        if (!$query || strlen($query) < 2) {
            Response::error('Search query too short');
        }

        $notes = Database::fetchAll(
            "SELECT * FROM bible_notes
             WHERE user_id = ? AND content LIKE ?
             ORDER BY created_at DESC
             LIMIT 50",
            [$user['id'], '%' . $query . '%']
        );

        Response::success(['notes' => $notes]);
        break;

    default:
        Response::error('Invalid action');
}
