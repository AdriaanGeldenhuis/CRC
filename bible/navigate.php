<?php
/**
 * CRC Bible - Navigation Page
 * Full page navigation for selecting book and chapter
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$pageTitle = 'Navigate - Bible - CRC';

// Bible book lists (shared data)
require_once __DIR__ . '/books.php';
$oldTestament = $BIBLE_OLD_TESTAMENT;
$newTestament = $BIBLE_NEW_TESTAMENT;
$chapterCounts = $BIBLE_CHAPTER_COUNTS;

// Get current step from URL
$step = $_GET['step'] ?? 'testament';
$testament = $_GET['testament'] ?? null;
$book = $_GET['book'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#7C3AED">
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
