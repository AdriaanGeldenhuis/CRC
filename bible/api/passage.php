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

// Bible book data
$bibleBooks = [
    'Genesis', 'Exodus', 'Leviticus', 'Numbers', 'Deuteronomy',
    'Joshua', 'Judges', 'Ruth', '1 Samuel', '2 Samuel',
    '1 Kings', '2 Kings', '1 Chronicles', '2 Chronicles', 'Ezra',
    'Nehemiah', 'Esther', 'Job', 'Psalms', 'Proverbs',
    'Ecclesiastes', 'Song of Solomon', 'Isaiah', 'Jeremiah', 'Lamentations',
    'Ezekiel', 'Daniel', 'Hosea', 'Joel', 'Amos',
    'Obadiah', 'Jonah', 'Micah', 'Nahum', 'Habakkuk',
    'Zephaniah', 'Haggai', 'Zechariah', 'Malachi',
    'Matthew', 'Mark', 'Luke', 'John', 'Acts',
    'Romans', '1 Corinthians', '2 Corinthians', 'Galatians', 'Ephesians',
    'Philippians', 'Colossians', '1 Thessalonians', '2 Thessalonians', '1 Timothy',
    '2 Timothy', 'Titus', 'Philemon', 'Hebrews', 'James',
    '1 Peter', '2 Peter', '1 John', '2 John', '3 John',
    'Jude', 'Revelation'
];

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

        if (empty($verses)) {
            // Generate sample verses if no data in DB
            $verses = generateSampleVerses($book, $chapter, $version);
        }

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

function generateSampleVerses($book, $chapter, $version) {
    // Sample verses for common passages when no DB data available
    $sampleData = [
        'Genesis' => [
            1 => [
                ['verse_number' => 1, 'text' => 'In the beginning God created the heaven and the earth.'],
                ['verse_number' => 2, 'text' => 'And the earth was without form, and void; and darkness was upon the face of the deep. And the Spirit of God moved upon the face of the waters.'],
                ['verse_number' => 3, 'text' => 'And God said, Let there be light: and there was light.'],
                ['verse_number' => 4, 'text' => 'And God saw the light, that it was good: and God divided the light from the darkness.'],
                ['verse_number' => 5, 'text' => 'And God called the light Day, and the darkness he called Night. And the evening and the morning were the first day.'],
            ]
        ],
        'Psalms' => [
            23 => [
                ['verse_number' => 1, 'text' => 'The LORD is my shepherd; I shall not want.'],
                ['verse_number' => 2, 'text' => 'He maketh me to lie down in green pastures: he leadeth me beside the still waters.'],
                ['verse_number' => 3, 'text' => 'He restoreth my soul: he leadeth me in the paths of righteousness for his name\'s sake.'],
                ['verse_number' => 4, 'text' => 'Yea, though I walk through the valley of the shadow of death, I will fear no evil: for thou art with me; thy rod and thy staff they comfort me.'],
                ['verse_number' => 5, 'text' => 'Thou preparest a table before me in the presence of mine enemies: thou anointest my head with oil; my cup runneth over.'],
                ['verse_number' => 6, 'text' => 'Surely goodness and mercy shall follow me all the days of my life: and I will dwell in the house of the LORD for ever.'],
            ]
        ],
        'John' => [
            3 => [
                ['verse_number' => 16, 'text' => 'For God so loved the world, that he gave his only begotten Son, that whosoever believeth in him should not perish, but have everlasting life.'],
                ['verse_number' => 17, 'text' => 'For God sent not his Son into the world to condemn the world; but that the world through him might be saved.'],
            ]
        ]
    ];

    if (isset($sampleData[$book][$chapter])) {
        return $sampleData[$book][$chapter];
    }

    // Generate placeholder verses
    $verses = [];
    $verseCount = rand(15, 30);
    for ($i = 1; $i <= $verseCount; $i++) {
        $verses[] = [
            'verse_number' => $i,
            'text' => "This is verse $i of $book chapter $chapter. Bible text would appear here from the $version translation."
        ];
    }
    return $verses;
}
