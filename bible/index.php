<?php
/**
 * CRC Bible Reader - Dashboard Layout
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
$bookmarks = [];
$recentHistory = [];

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

// Get user bookmarks
try {
    $bookmarks = Database::fetchAll(
        "SELECT * FROM bible_bookmarks
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 5",
        [$user['id']]
    ) ?: [];
} catch (Exception $e) {}

// Get recent reading history
try {
    $recentHistory = Database::fetchAll(
        "SELECT * FROM bible_reading_history
         WHERE user_id = ?
         ORDER BY read_at DESC
         LIMIT 5",
        [$user['id']]
    ) ?: [];
} catch (Exception $e) {}

// Count highlights and notes
$totalHighlights = 0;
$totalNotes = 0;
try {
    $totalHighlights = Database::fetchColumn(
        "SELECT COUNT(*) FROM bible_highlights WHERE user_id = ?",
        [$user['id']]
    ) ?: 0;
    $totalNotes = Database::fetchColumn(
        "SELECT COUNT(*) FROM bible_notes WHERE user_id = ?",
        [$user['id']]
    ) ?: 0;
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($book) ?> <?= $chapter ?> - <?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/home/css/home.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
    <style>
        .bible-reader-card {
            background: linear-gradient(135deg, #1E3A5F 0%, #2C5282 100%);
            color: var(--white);
        }
        .bible-reader-card .card-header h2 { color: var(--white); }
        .version-badge {
            background: rgba(255,255,255,0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 100px;
            font-size: 0.875rem;
        }
        .scripture-display {
            background: rgba(255,255,255,0.1);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-top: 1rem;
            font-family: 'Merriweather', serif;
            line-height: 1.8;
            max-height: 300px;
            overflow-y: auto;
        }
        .verse-num {
            font-size: 0.75rem;
            font-weight: 600;
            color: rgba(255,255,255,0.7);
            vertical-align: super;
            margin-right: 0.25rem;
        }
        .chapter-nav-btns {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        .chapter-nav-btns a {
            flex: 1;
            padding: 0.75rem;
            background: rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            border-radius: var(--radius);
            text-align: center;
            transition: var(--transition);
        }
        .chapter-nav-btns a:hover { background: rgba(255,255,255,0.3); }
        .quick-jump-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }
        .quick-jump-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1rem;
            background: var(--gray-50);
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--gray-700);
            transition: var(--transition);
        }
        .quick-jump-item:hover {
            background: var(--primary);
            color: white;
        }
        .quick-jump-icon { font-size: 1.5rem; margin-bottom: 0.5rem; }
        .bookmark-list, .history-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .bookmark-item, .history-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: var(--gray-50);
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--gray-700);
            transition: var(--transition);
        }
        .bookmark-item:hover, .history-item:hover { background: var(--gray-100); }
        .bookmark-icon, .history-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary);
            color: white;
            border-radius: var(--radius);
            font-size: 0.875rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .stat-item {
            text-align: center;
            padding: 1rem;
            background: var(--gray-50);
            border-radius: var(--radius);
        }
        .stat-value { font-size: 1.5rem; font-weight: 700; color: var(--primary); }
        .stat-label { font-size: 0.75rem; color: var(--gray-500); }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <!-- Welcome Section -->
            <section class="welcome-section">
                <div class="welcome-content">
                    <h1>Bible</h1>
                    <p>Read and study God's Word</p>
                </div>
            </section>

            <div class="dashboard-grid">
                <!-- Bible Reader Card -->
                <div class="dashboard-card bible-reader-card">
                    <div class="card-header">
                        <h2><?= e($book) ?> <?= $chapter ?></h2>
                        <span class="version-badge"><?= e($version) ?></span>
                    </div>
                    <div class="reference-selector" style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
                        <select id="bookSelect" onchange="changeBook(this.value)" style="flex: 1; padding: 0.5rem; border-radius: var(--radius); border: 1px solid rgba(255,255,255,0.3); background: rgba(255,255,255,0.1); color: white;">
                            <?php foreach ($bibleBooks as $b): ?>
                                <option value="<?= e($b['name']) ?>" <?= $b['name'] === $book ? 'selected' : '' ?> style="color: #333;">
                                    <?= e($b['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select id="chapterSelect" onchange="changeChapter(this.value)" style="padding: 0.5rem; border-radius: var(--radius); border: 1px solid rgba(255,255,255,0.3); background: rgba(255,255,255,0.1); color: white;">
                            <?php for ($c = 1; $c <= $currentBook['chapters']; $c++): ?>
                                <option value="<?= $c ?>" <?= $c === $chapter ? 'selected' : '' ?> style="color: #333;">
                                    Ch <?= $c ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="scripture-display" id="versesContainer">
                        <div class="loading">Loading verses...</div>
                    </div>
                    <div class="chapter-nav-btns">
                        <?php if ($prevChapter): ?>
                            <a href="?v=<?= $version ?>&b=<?= urlencode($prevChapter['book']) ?>&c=<?= $prevChapter['chapter'] ?>">
                                ← <?= e($prevChapter['book']) ?> <?= $prevChapter['chapter'] ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($nextChapter): ?>
                            <a href="?v=<?= $version ?>&b=<?= urlencode($nextChapter['book']) ?>&c=<?= $nextChapter['chapter'] ?>">
                                <?= e($nextChapter['book']) ?> <?= $nextChapter['chapter'] ?> →
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Jump Card -->
                <div class="dashboard-card">
                    <h2 style="margin-bottom: 1rem;">Quick Jump</h2>
                    <div class="quick-jump-grid">
                        <a href="?b=Psalms&c=23" class="quick-jump-item">
                            <span class="quick-jump-icon">📖</span>
                            <span>Psalm 23</span>
                        </a>
                        <a href="?b=John&c=3" class="quick-jump-item">
                            <span class="quick-jump-icon">❤️</span>
                            <span>John 3:16</span>
                        </a>
                        <a href="?b=Romans&c=8" class="quick-jump-item">
                            <span class="quick-jump-icon">✨</span>
                            <span>Romans 8</span>
                        </a>
                        <a href="?b=Proverbs&c=3" class="quick-jump-item">
                            <span class="quick-jump-icon">🙏</span>
                            <span>Proverbs 3</span>
                        </a>
                    </div>
                </div>

                <!-- Bookmarks & Notes Card -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>My Study</h2>
                        <a href="/bible/bookmarks.php" class="view-all-link">View All</a>
                    </div>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-value"><?= $totalHighlights ?></div>
                            <div class="stat-label">Highlights</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= $totalNotes ?></div>
                            <div class="stat-label">Notes</div>
                        </div>
                    </div>
                    <?php if ($bookmarks): ?>
                        <div class="bookmark-list">
                            <?php foreach (array_slice($bookmarks, 0, 3) as $bm): ?>
                                <a href="?b=<?= urlencode($bm['book_name'] ?? 'Genesis') ?>&c=<?= $bm['chapter'] ?? 1 ?>" class="bookmark-item">
                                    <div class="bookmark-icon">📌</div>
                                    <div>
                                        <strong><?= e($bm['book_name'] ?? 'Bookmark') ?> <?= $bm['chapter'] ?? '' ?>:<?= $bm['verse'] ?? '' ?></strong>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: var(--gray-500); text-align: center; padding: 1rem;">No bookmarks yet</p>
                    <?php endif; ?>
                </div>

                <!-- Reading History Card -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>Recent Reading</h2>
                    </div>
                    <?php if ($recentHistory): ?>
                        <div class="history-list">
                            <?php foreach ($recentHistory as $h): ?>
                                <a href="?b=<?= urlencode($h['book_name'] ?? 'Genesis') ?>&c=<?= $h['chapter'] ?? 1 ?>" class="history-item">
                                    <div class="history-icon">📚</div>
                                    <div>
                                        <strong><?= e($h['book_name'] ?? 'Chapter') ?> <?= $h['chapter'] ?? '' ?></strong>
                                        <br><small style="color: var(--gray-500);"><?= time_ago($h['read_at'] ?? 'now') ?></small>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="history-list">
                            <a href="?b=Genesis&c=1" class="history-item">
                                <div class="history-icon">📚</div>
                                <div><strong>Start Reading</strong><br><small style="color: var(--gray-500);">Genesis 1</small></div>
                            </a>
                            <a href="?b=Matthew&c=1" class="history-item">
                                <div class="history-icon">📚</div>
                                <div><strong>New Testament</strong><br><small style="color: var(--gray-500);">Matthew 1</small></div>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        const currentVersion = '<?= e($version) ?>';
        const currentBook = '<?= e($book) ?>';
        const currentChapter = <?= $chapter ?>;
        const bookIndex = <?= $bookIndex ?>;
        const highlights = <?= json_encode($highlightMap) ?>;

        function changeBook(bookName) {
            window.location = '?v=' + currentVersion + '&b=' + encodeURIComponent(bookName) + '&c=1';
        }

        function changeChapter(chapter) {
            window.location = '?v=' + currentVersion + '&b=' + encodeURIComponent(currentBook) + '&c=' + chapter;
        }

        // Load verses
        document.addEventListener('DOMContentLoaded', function() {
            fetch('/bible/api/verses.php?v=' + currentVersion + '&b=' + encodeURIComponent(currentBook) + '&c=' + currentChapter)
                .then(r => r.json())
                .then(data => {
                    if (data.verses) {
                        let html = '';
                        data.verses.forEach(v => {
                            const highlight = highlights[v.verse] ? 'style="background: rgba(255,255,0,0.3);"' : '';
                            html += '<span ' + highlight + '><sup class="verse-num">' + v.verse + '</sup>' + v.text + ' </span>';
                        });
                        document.getElementById('versesContainer').innerHTML = html;
                    }
                })
                .catch(() => {
                    document.getElementById('versesContainer').innerHTML = '<p>Unable to load verses. Please try again.</p>';
                });
        });
    </script>
</body>
</html>
