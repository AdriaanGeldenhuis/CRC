<?php
/**
 * CRC Bible Reader
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$pageTitle = 'Bible - CRC';

// Get selected version and reference
$version = $_GET['v'] ?? 'KJV';
$book = $_GET['b'] ?? 'Genesis';
$chapter = max(1, (int)($_GET['c'] ?? 1));

// Bible books data
$bibleBooks = [
    // Old Testament
    ['name' => 'Genesis', 'abbr' => 'Gen', 'chapters' => 50, 'testament' => 'old'],
    ['name' => 'Exodus', 'abbr' => 'Exod', 'chapters' => 40, 'testament' => 'old'],
    ['name' => 'Leviticus', 'abbr' => 'Lev', 'chapters' => 27, 'testament' => 'old'],
    ['name' => 'Numbers', 'abbr' => 'Num', 'chapters' => 36, 'testament' => 'old'],
    ['name' => 'Deuteronomy', 'abbr' => 'Deut', 'chapters' => 34, 'testament' => 'old'],
    ['name' => 'Joshua', 'abbr' => 'Josh', 'chapters' => 24, 'testament' => 'old'],
    ['name' => 'Judges', 'abbr' => 'Judg', 'chapters' => 21, 'testament' => 'old'],
    ['name' => 'Ruth', 'abbr' => 'Ruth', 'chapters' => 4, 'testament' => 'old'],
    ['name' => '1 Samuel', 'abbr' => '1Sam', 'chapters' => 31, 'testament' => 'old'],
    ['name' => '2 Samuel', 'abbr' => '2Sam', 'chapters' => 24, 'testament' => 'old'],
    ['name' => '1 Kings', 'abbr' => '1Kgs', 'chapters' => 22, 'testament' => 'old'],
    ['name' => '2 Kings', 'abbr' => '2Kgs', 'chapters' => 25, 'testament' => 'old'],
    ['name' => '1 Chronicles', 'abbr' => '1Chr', 'chapters' => 29, 'testament' => 'old'],
    ['name' => '2 Chronicles', 'abbr' => '2Chr', 'chapters' => 36, 'testament' => 'old'],
    ['name' => 'Ezra', 'abbr' => 'Ezra', 'chapters' => 10, 'testament' => 'old'],
    ['name' => 'Nehemiah', 'abbr' => 'Neh', 'chapters' => 13, 'testament' => 'old'],
    ['name' => 'Esther', 'abbr' => 'Esth', 'chapters' => 10, 'testament' => 'old'],
    ['name' => 'Job', 'abbr' => 'Job', 'chapters' => 42, 'testament' => 'old'],
    ['name' => 'Psalms', 'abbr' => 'Ps', 'chapters' => 150, 'testament' => 'old'],
    ['name' => 'Proverbs', 'abbr' => 'Prov', 'chapters' => 31, 'testament' => 'old'],
    ['name' => 'Ecclesiastes', 'abbr' => 'Eccl', 'chapters' => 12, 'testament' => 'old'],
    ['name' => 'Song of Solomon', 'abbr' => 'Song', 'chapters' => 8, 'testament' => 'old'],
    ['name' => 'Isaiah', 'abbr' => 'Isa', 'chapters' => 66, 'testament' => 'old'],
    ['name' => 'Jeremiah', 'abbr' => 'Jer', 'chapters' => 52, 'testament' => 'old'],
    ['name' => 'Lamentations', 'abbr' => 'Lam', 'chapters' => 5, 'testament' => 'old'],
    ['name' => 'Ezekiel', 'abbr' => 'Ezek', 'chapters' => 48, 'testament' => 'old'],
    ['name' => 'Daniel', 'abbr' => 'Dan', 'chapters' => 12, 'testament' => 'old'],
    ['name' => 'Hosea', 'abbr' => 'Hos', 'chapters' => 14, 'testament' => 'old'],
    ['name' => 'Joel', 'abbr' => 'Joel', 'chapters' => 3, 'testament' => 'old'],
    ['name' => 'Amos', 'abbr' => 'Amos', 'chapters' => 9, 'testament' => 'old'],
    ['name' => 'Obadiah', 'abbr' => 'Obad', 'chapters' => 1, 'testament' => 'old'],
    ['name' => 'Jonah', 'abbr' => 'Jonah', 'chapters' => 4, 'testament' => 'old'],
    ['name' => 'Micah', 'abbr' => 'Mic', 'chapters' => 7, 'testament' => 'old'],
    ['name' => 'Nahum', 'abbr' => 'Nah', 'chapters' => 3, 'testament' => 'old'],
    ['name' => 'Habakkuk', 'abbr' => 'Hab', 'chapters' => 3, 'testament' => 'old'],
    ['name' => 'Zephaniah', 'abbr' => 'Zeph', 'chapters' => 3, 'testament' => 'old'],
    ['name' => 'Haggai', 'abbr' => 'Hag', 'chapters' => 2, 'testament' => 'old'],
    ['name' => 'Zechariah', 'abbr' => 'Zech', 'chapters' => 14, 'testament' => 'old'],
    ['name' => 'Malachi', 'abbr' => 'Mal', 'chapters' => 4, 'testament' => 'old'],
    // New Testament
    ['name' => 'Matthew', 'abbr' => 'Matt', 'chapters' => 28, 'testament' => 'new'],
    ['name' => 'Mark', 'abbr' => 'Mark', 'chapters' => 16, 'testament' => 'new'],
    ['name' => 'Luke', 'abbr' => 'Luke', 'chapters' => 24, 'testament' => 'new'],
    ['name' => 'John', 'abbr' => 'John', 'chapters' => 21, 'testament' => 'new'],
    ['name' => 'Acts', 'abbr' => 'Acts', 'chapters' => 28, 'testament' => 'new'],
    ['name' => 'Romans', 'abbr' => 'Rom', 'chapters' => 16, 'testament' => 'new'],
    ['name' => '1 Corinthians', 'abbr' => '1Cor', 'chapters' => 16, 'testament' => 'new'],
    ['name' => '2 Corinthians', 'abbr' => '2Cor', 'chapters' => 13, 'testament' => 'new'],
    ['name' => 'Galatians', 'abbr' => 'Gal', 'chapters' => 6, 'testament' => 'new'],
    ['name' => 'Ephesians', 'abbr' => 'Eph', 'chapters' => 6, 'testament' => 'new'],
    ['name' => 'Philippians', 'abbr' => 'Phil', 'chapters' => 4, 'testament' => 'new'],
    ['name' => 'Colossians', 'abbr' => 'Col', 'chapters' => 4, 'testament' => 'new'],
    ['name' => '1 Thessalonians', 'abbr' => '1Thess', 'chapters' => 5, 'testament' => 'new'],
    ['name' => '2 Thessalonians', 'abbr' => '2Thess', 'chapters' => 3, 'testament' => 'new'],
    ['name' => '1 Timothy', 'abbr' => '1Tim', 'chapters' => 6, 'testament' => 'new'],
    ['name' => '2 Timothy', 'abbr' => '2Tim', 'chapters' => 4, 'testament' => 'new'],
    ['name' => 'Titus', 'abbr' => 'Titus', 'chapters' => 3, 'testament' => 'new'],
    ['name' => 'Philemon', 'abbr' => 'Phlm', 'chapters' => 1, 'testament' => 'new'],
    ['name' => 'Hebrews', 'abbr' => 'Heb', 'chapters' => 13, 'testament' => 'new'],
    ['name' => 'James', 'abbr' => 'Jas', 'chapters' => 5, 'testament' => 'new'],
    ['name' => '1 Peter', 'abbr' => '1Pet', 'chapters' => 5, 'testament' => 'new'],
    ['name' => '2 Peter', 'abbr' => '2Pet', 'chapters' => 3, 'testament' => 'new'],
    ['name' => '1 John', 'abbr' => '1John', 'chapters' => 5, 'testament' => 'new'],
    ['name' => '2 John', 'abbr' => '2John', 'chapters' => 1, 'testament' => 'new'],
    ['name' => '3 John', 'abbr' => '3John', 'chapters' => 1, 'testament' => 'new'],
    ['name' => 'Jude', 'abbr' => 'Jude', 'chapters' => 1, 'testament' => 'new'],
    ['name' => 'Revelation', 'abbr' => 'Rev', 'chapters' => 22, 'testament' => 'new'],
];

// Find current book info
$currentBook = null;
$bookIndex = 0;
foreach ($bibleBooks as $i => $b) {
    if (strtolower($b['name']) === strtolower($book)) {
        $currentBook = $b;
        $bookIndex = $i;
        break;
    }
}

if (!$currentBook) {
    $currentBook = $bibleBooks[0];
    $book = $currentBook['name'];
}

// Ensure chapter is valid
if ($chapter > $currentBook['chapters']) {
    $chapter = 1;
}

// Calculate prev/next chapter
$prevChapter = null;
$nextChapter = null;

if ($chapter > 1) {
    $prevChapter = ['book' => $book, 'chapter' => $chapter - 1];
} elseif ($bookIndex > 0) {
    $prevBook = $bibleBooks[$bookIndex - 1];
    $prevChapter = ['book' => $prevBook['name'], 'chapter' => $prevBook['chapters']];
}

if ($chapter < $currentBook['chapters']) {
    $nextChapter = ['book' => $book, 'chapter' => $chapter + 1];
} elseif ($bookIndex < count($bibleBooks) - 1) {
    $nextBook = $bibleBooks[$bookIndex + 1];
    $nextChapter = ['book' => $nextBook['name'], 'chapter' => 1];
}

// Get user highlights for this chapter
$highlights = [];
$highlightMap = [];
$notes = [];

try {
    $highlights = Database::fetchAll(
        "SELECT * FROM bible_highlights
         WHERE user_id = ? AND version_code = ? AND book_number = ? AND chapter = ?",
        [$user['id'], $version, $bookIndex + 1, $chapter]
    ) ?: [];
} catch (Exception $e) {}

foreach ($highlights as $h) {
    for ($v = $h['verse_start']; $v <= ($h['verse_end'] ?: $h['verse_start']); $v++) {
        $highlightMap[$v] = $h['color'];
    }
}

// Get user notes for this chapter
try {
    $notes = Database::fetchAll(
        "SELECT * FROM bible_notes
         WHERE user_id = ? AND version_code = ? AND book_number = ? AND chapter = ?
         ORDER BY verse_start ASC",
        [$user['id'], $version, $bookIndex + 1, $chapter]
    ) ?: [];
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($book) ?> <?= $chapter ?> - <?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/bible/css/bible.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="bible-layout">
        <!-- Sidebar - Book List -->
        <aside class="bible-sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>Books</h2>
                <button class="close-sidebar" onclick="toggleSidebar()">×</button>
            </div>

            <div class="testament-section">
                <h3>Old Testament</h3>
                <div class="book-list">
                    <?php foreach ($bibleBooks as $b):
                        if ($b['testament'] !== 'old') continue;
                    ?>
                        <a href="?v=<?= $version ?>&b=<?= urlencode($b['name']) ?>&c=1"
                           class="book-link <?= $b['name'] === $book ? 'active' : '' ?>">
                            <?= e($b['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="testament-section">
                <h3>New Testament</h3>
                <div class="book-list">
                    <?php foreach ($bibleBooks as $b):
                        if ($b['testament'] !== 'new') continue;
                    ?>
                        <a href="?v=<?= $version ?>&b=<?= urlencode($b['name']) ?>&c=1"
                           class="book-link <?= $b['name'] === $book ? 'active' : '' ?>">
                            <?= e($b['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="bible-main">
            <!-- Top Bar -->
            <div class="bible-topbar">
                <button class="menu-btn" onclick="toggleSidebar()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>

                <div class="reference-selector">
                    <select id="bookSelect" onchange="changeBook(this.value)">
                        <?php foreach ($bibleBooks as $b): ?>
                            <option value="<?= e($b['name']) ?>" <?= $b['name'] === $book ? 'selected' : '' ?>>
                                <?= e($b['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="chapterSelect" onchange="changeChapter(this.value)">
                        <?php for ($c = 1; $c <= $currentBook['chapters']; $c++): ?>
                            <option value="<?= $c ?>" <?= $c === $chapter ? 'selected' : '' ?>>
                                Chapter <?= $c ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <select id="versionSelect" onchange="changeVersion(this.value)">
                    <option value="KJV" <?= $version === 'KJV' ? 'selected' : '' ?>>KJV</option>
                    <option value="NIV" <?= $version === 'NIV' ? 'selected' : '' ?>>NIV</option>
                    <option value="ESV" <?= $version === 'ESV' ? 'selected' : '' ?>>ESV</option>
                    <option value="NLT" <?= $version === 'NLT' ? 'selected' : '' ?>>NLT</option>
                </select>

                <button class="tool-btn" onclick="openSearch()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </button>
            </div>

            <!-- Chapter Content -->
            <div class="chapter-content">
                <h1 class="chapter-title"><?= e($book) ?> <?= $chapter ?></h1>

                <div class="verses" id="versesContainer">
                    <div class="loading">Loading verses...</div>
                </div>

                <!-- Chapter Navigation -->
                <div class="chapter-nav">
                    <?php if ($prevChapter): ?>
                        <a href="?v=<?= $version ?>&b=<?= urlencode($prevChapter['book']) ?>&c=<?= $prevChapter['chapter'] ?>" class="nav-link prev">
                            ← <?= e($prevChapter['book']) ?> <?= $prevChapter['chapter'] ?>
                        </a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>

                    <?php if ($nextChapter): ?>
                        <a href="?v=<?= $version ?>&b=<?= urlencode($nextChapter['book']) ?>&c=<?= $nextChapter['chapter'] ?>" class="nav-link next">
                            <?= e($nextChapter['book']) ?> <?= $nextChapter['chapter'] ?> →
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tools Panel -->
        <aside class="bible-tools" id="toolsPanel">
            <div class="tools-header">
                <h3>Tools</h3>
                <button class="close-tools" onclick="closeTools()">×</button>
            </div>
            <div class="tools-content">
                <div id="toolsContentArea">
                    <p class="tools-hint">Select a verse to see options</p>
                </div>
            </div>
        </aside>
    </main>

    <!-- Verse Action Menu -->
    <div class="verse-menu" id="verseMenu" style="display:none;">
        <button onclick="highlightVerse('yellow')">🟡 Highlight</button>
        <button onclick="addNote()">📝 Add Note</button>
        <button onclick="addTag()">🏷️ Add Tag</button>
        <button onclick="copyVerse()">📋 Copy</button>
        <button onclick="shareVerse()">📤 Share</button>
        <button onclick="aiExplain()">✨ AI Explain</button>
    </div>

    <div id="toast" class="toast"></div>

    <script>
        const currentVersion = '<?= e($version) ?>';
        const currentBook = '<?= e($book) ?>';
        const currentChapter = <?= $chapter ?>;
        const bookIndex = <?= $bookIndex ?>;
        const highlights = <?= json_encode($highlightMap) ?>;
    </script>
    <script src="/bible/js/bible.js"></script>
</body>
</html>
