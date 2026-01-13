<?php
/**
 * CRC Bible - Navigation Page
 * Full page navigation for selecting book and chapter
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$pageTitle = 'Navigate - Bible - CRC';

// Bible book lists
$oldTestament = [
    'Genesis', 'Exodus', 'Leviticus', 'Numbers', 'Deuteronomy',
    'Joshua', 'Judges', 'Ruth', '1 Samuel', '2 Samuel', '1 Kings', '2 Kings',
    '1 Chronicles', '2 Chronicles', 'Ezra', 'Nehemiah', 'Esther',
    'Job', 'Psalms', 'Proverbs', 'Ecclesiastes', 'Song of Solomon',
    'Isaiah', 'Jeremiah', 'Lamentations', 'Ezekiel', 'Daniel',
    'Hosea', 'Joel', 'Amos', 'Obadiah', 'Jonah', 'Micah', 'Nahum',
    'Habakkuk', 'Zephaniah', 'Haggai', 'Zechariah', 'Malachi'
];

$newTestament = [
    'Matthew', 'Mark', 'Luke', 'John', 'Acts',
    'Romans', '1 Corinthians', '2 Corinthians', 'Galatians', 'Ephesians',
    'Philippians', 'Colossians', '1 Thessalonians', '2 Thessalonians',
    '1 Timothy', '2 Timothy', 'Titus', 'Philemon',
    'Hebrews', 'James', '1 Peter', '2 Peter', '1 John', '2 John', '3 John',
    'Jude', 'Revelation'
];

// Chapter counts for each book
$chapterCounts = [
    'Genesis' => 50, 'Exodus' => 40, 'Leviticus' => 27, 'Numbers' => 36, 'Deuteronomy' => 34,
    'Joshua' => 24, 'Judges' => 21, 'Ruth' => 4, '1 Samuel' => 31, '2 Samuel' => 24,
    '1 Kings' => 22, '2 Kings' => 25, '1 Chronicles' => 29, '2 Chronicles' => 36,
    'Ezra' => 10, 'Nehemiah' => 13, 'Esther' => 10, 'Job' => 42, 'Psalms' => 150,
    'Proverbs' => 31, 'Ecclesiastes' => 12, 'Song of Solomon' => 8, 'Isaiah' => 66,
    'Jeremiah' => 52, 'Lamentations' => 5, 'Ezekiel' => 48, 'Daniel' => 12,
    'Hosea' => 14, 'Joel' => 3, 'Amos' => 9, 'Obadiah' => 1, 'Jonah' => 4,
    'Micah' => 7, 'Nahum' => 3, 'Habakkuk' => 3, 'Zephaniah' => 3, 'Haggai' => 2,
    'Zechariah' => 14, 'Malachi' => 4, 'Matthew' => 28, 'Mark' => 16, 'Luke' => 24,
    'John' => 21, 'Acts' => 28, 'Romans' => 16, '1 Corinthians' => 16,
    '2 Corinthians' => 13, 'Galatians' => 6, 'Ephesians' => 6, 'Philippians' => 4,
    'Colossians' => 4, '1 Thessalonians' => 5, '2 Thessalonians' => 3,
    '1 Timothy' => 6, '2 Timothy' => 4, 'Titus' => 3, 'Philemon' => 1,
    'Hebrews' => 13, 'James' => 5, '1 Peter' => 5, '2 Peter' => 3,
    '1 John' => 5, '2 John' => 1, '3 John' => 1, 'Jude' => 1, 'Revelation' => 22
];

// Get current step from URL
$step = $_GET['step'] ?? 'testament';
$testament = $_GET['testament'] ?? null;
$book = $_GET['book'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        (function() {
            const saved = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', saved);
        })();
    </script>
    <style>
        :root {
            --bg-primary: #0A0A0A;
            --bg-card: #1A1A2E;
            --text-primary: #FFFFFF;
            --text-secondary: #A1A1C7;
            --primary: #8B5CF6;
            --glass-border: rgba(139, 92, 246, 0.2);
        }
        [data-theme="light"] {
            --bg-primary: #F5F3FF;
            --bg-card: #FFFFFF;
            --text-primary: #1E1B4B;
            --text-secondary: #6D5BA8;
            --glass-border: rgba(139, 92, 246, 0.15);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
        }
        .nav-page {
            min-height: 100vh;
            padding-top: 56px;
        }
        .nav-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 56px;
            background: var(--bg-card);
            border-bottom: 1px solid var(--glass-border);
            display: flex;
            align-items: center;
            padding: 0 1rem;
            gap: 1rem;
            z-index: 100;
        }
        .back-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: none;
            border: none;
            color: var(--text-primary);
            text-decoration: none;
            border-radius: 10px;
        }
        .back-btn:active {
            background: rgba(139, 92, 246, 0.15);
        }
        .back-btn svg { width: 24px; height: 24px; }
        .nav-header h1 {
            font-size: 1.1rem;
            font-weight: 600;
            flex: 1;
        }
        .nav-body {
            padding: 1rem;
        }
        .nav-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 1rem;
            text-align: center;
        }
        .nav-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }
        .nav-grid-small {
            grid-template-columns: repeat(5, 1fr);
        }
        .nav-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 100px;
            padding: 1.25rem 1rem;
            background: var(--bg-card);
            border: 2px solid var(--glass-border);
            border-radius: 12px;
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.2s ease;
        }
        .nav-card:active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        .nav-card-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .nav-card-title {
            font-size: 1rem;
            font-weight: 600;
            text-align: center;
        }
        .nav-card-subtitle {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.35rem;
            text-align: center;
        }
        .nav-card:active .nav-card-subtitle {
            color: rgba(255,255,255,0.8);
        }
        .nav-card-small {
            min-height: 60px;
            padding: 0.75rem 0.5rem;
        }
        .nav-card-small .nav-card-title {
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="nav-page">
        <header class="nav-header">
            <?php if ($step === 'testament'): ?>
                <a href="/bible/" class="back-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </a>
                <h1>Quick Navigation</h1>
            <?php elseif ($step === 'book'): ?>
                <a href="/bible/navigate.php" class="back-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </a>
                <h1><?= $testament === 'old' ? 'Old Testament' : 'New Testament' ?></h1>
            <?php elseif ($step === 'chapter'): ?>
                <a href="/bible/navigate.php?step=book&testament=<?= e($testament) ?>" class="back-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </a>
                <h1><?= e($book) ?></h1>
            <?php endif; ?>
        </header>

        <div class="nav-body">
            <?php if ($step === 'testament'): ?>
                <h2 class="nav-title">Choose Testament</h2>
                <div class="nav-grid">
                    <a href="/bible/navigate.php?step=book&testament=old" class="nav-card">
                        <div class="nav-card-icon">📖</div>
                        <div class="nav-card-title">Old Testament</div>
                        <div class="nav-card-subtitle">Genesis - Malachi</div>
                    </a>
                    <a href="/bible/navigate.php?step=book&testament=new" class="nav-card">
                        <div class="nav-card-icon">✨</div>
                        <div class="nav-card-title">New Testament</div>
                        <div class="nav-card-subtitle">Matthew - Revelation</div>
                    </a>
                </div>

            <?php elseif ($step === 'book'): ?>
                <h2 class="nav-title">Choose Book</h2>
                <div class="nav-grid">
                    <?php
                    $books = $testament === 'old' ? $oldTestament : $newTestament;
                    foreach ($books as $bookName):
                    ?>
                        <a href="/bible/navigate.php?step=chapter&testament=<?= e($testament) ?>&book=<?= urlencode($bookName) ?>" class="nav-card nav-card-small">
                            <div class="nav-card-title"><?= e($bookName) ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>

            <?php elseif ($step === 'chapter'): ?>
                <h2 class="nav-title">Choose Chapter</h2>
                <div class="nav-grid nav-grid-small">
                    <?php
                    $chapters = $chapterCounts[$book] ?? 1;
                    for ($i = 1; $i <= $chapters; $i++):
                    ?>
                        <a href="/bible/?book=<?= urlencode($book) ?>&chapter=<?= $i ?>" class="nav-card nav-card-small">
                            <div class="nav-card-title"><?= $i ?></div>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
