<?php
/**
 * CRC Bible Passage API
 * POST /bible/api/passage.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();
Response::requirePost();
CSRF::require();

$action = input('action', 'get');

// Bible book data (shared)
require_once __DIR__ . '/../books.php';
$bibleBooks = $BIBLE_ALL_BOOKS;

switch ($action) {
    case 'get':
        $book = input('book', 'Genesis');
        $chapter = max(1, (int)input('chapter', 1));
        $version = input('version', 'KJV');

        // Find book number
        $bookNumber = array_search($book, $bibleBooks);
        if ($bookNumber === false) {
            Response::error('Invalid book');
        }
        $bookNumber++; // 1-indexed

        // Try to get from database
        $verses = [];
        try {
            $verses = Database::fetchAll(
                "SELECT verse_number, text FROM bible_verses
                 WHERE version_code = ? AND book_number = ? AND chapter = ?
                 ORDER BY verse_number ASC",
                [$version, $bookNumber, $chapter]
            ) ?: [];
        } catch (Exception $e) {}

        Response::success(['verses' => $verses]);
        break;

    case 'search':
        $query = input('query');
        $version = input('version', 'KJV');

        if (!$query || strlen($query) < 3) {
            Response::error('Search query must be at least 3 characters');
        }

        $results = [];
        try {
            $results = Database::fetchAll(
                "SELECT v.book_number, v.chapter, v.verse_number, v.text
                 FROM bible_verses v
                 WHERE v.version_code = ?
                 AND v.text LIKE ?
                 ORDER BY v.book_number, v.chapter, v.verse_number
                 LIMIT 50",
                [$version, '%' . $query . '%']
            ) ?: [];
        } catch (Exception $e) {}

        // Map book numbers to names
        $mappedResults = array_map(function($r) use ($bibleBooks) {
            return [
                'book' => $bibleBooks[$r['book_number'] - 1] ?? 'Unknown',
                'chapter' => $r['chapter'],
                'verse' => $r['verse_number'],
                'text' => $r['text']
            ];
        }, $results);

        Response::success(['results' => $mappedResults]);
        break;

    case 'verse_of_day':
        // Get or generate verse of the day
        $today = date('Y-m-d');
        $votd = null;
        try {
            $votd = Database::fetchOne(
                "SELECT * FROM verse_of_day WHERE date = ?",
                [$today]
            );
        } catch (Exception $e) {}

        if (!$votd) {
            // Generate new VOTD
            $votdVerses = [
                ['ref' => 'John 3:16', 'text' => 'For God so loved the world, that he gave his only begotten Son, that whosoever believeth in him should not perish, but have everlasting life.'],
                ['ref' => 'Philippians 4:13', 'text' => 'I can do all things through Christ which strengtheneth me.'],
                ['ref' => 'Psalm 23:1', 'text' => 'The LORD is my shepherd; I shall not want.'],
                ['ref' => 'Jeremiah 29:11', 'text' => 'For I know the thoughts that I think toward you, saith the LORD, thoughts of peace, and not of evil, to give you an expected end.'],
                ['ref' => 'Romans 8:28', 'text' => 'And we know that all things work together for good to them that love God, to them who are the called according to his purpose.'],
                ['ref' => 'Isaiah 41:10', 'text' => 'Fear thou not; for I am with thee: be not dismayed; for I am thy God: I will strengthen thee; yea, I will help thee; yea, I will uphold thee with the right hand of my righteousness.'],
                ['ref' => 'Proverbs 3:5-6', 'text' => 'Trust in the LORD with all thine heart; and lean not unto thine own understanding. In all thy ways acknowledge him, and he shall direct thy paths.'],
            ];

            $index = crc32($today) % count($votdVerses);
            $votd = $votdVerses[$index];
        }

        Response::success(['verse' => $votd]);
        break;

    default:
        Response::error('Invalid action');
}

