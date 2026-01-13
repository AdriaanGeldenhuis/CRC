<?php
/**
 * CRC Bible - Verse Actions Page
 * Full page for verse actions (highlight, bookmark, note, etc.)
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$pageTitle = 'Verse Actions - Bible - CRC';

// Get verse reference from URL
$ref = $_GET['ref'] ?? '';
$book = $_GET['book'] ?? '';
$chapter = $_GET['chapter'] ?? '';
$verse = $_GET['verse'] ?? '';
$text = $_GET['text'] ?? '';

// Build display reference
$displayRef = '';
if ($book && $chapter && $verse) {
    $displayRef = "$book $chapter:$verse";
}
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
            --highlight-1: #EC4899;
            --highlight-2: #F97316;
            --highlight-3: #EAB308;
            --highlight-4: #22C55E;
            --highlight-5: #3B82F6;
            --highlight-6: #A855F7;
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
        .actions-page {
            min-height: 100vh;
            padding-top: 56px;
        }
        .actions-header {
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
        .actions-header h1 {
            font-size: 1.1rem;
            font-weight: 600;
            flex: 1;
        }
        .actions-body {
            padding: 1rem;
        }
        .verse-preview {
            background: var(--bg-card);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .verse-ref {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        .verse-text {
            font-size: 1rem;
            line-height: 1.6;
            color: var(--text-primary);
        }
        .section-title {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.75rem;
        }
        .highlight-colors {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            justify-content: center;
            margin-bottom: 1.5rem;
            padding: 0.5rem;
        }
        .color-btn {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            border: 3px solid transparent;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        .color-btn:active {
            transform: scale(0.9);
            border-color: white;
        }
        .color-1 { background: var(--highlight-1); }
        .color-2 { background: var(--highlight-2); }
        .color-3 { background: var(--highlight-3); }
        .color-4 { background: var(--highlight-4); }
        .color-5 { background: var(--highlight-5); }
        .color-6 { background: var(--highlight-6); }
        .color-clear {
            background: var(--bg-card);
            border: 2px solid var(--glass-border);
            color: var(--text-secondary);
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .actions-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .action-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            width: 100%;
            padding: 1rem;
            background: var(--bg-card);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            color: var(--text-primary);
            font-family: inherit;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .action-btn:active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        .action-btn svg {
            width: 22px;
            height: 22px;
            color: var(--primary);
            flex-shrink: 0;
        }
        .action-btn:active svg {
            color: white;
        }
        .toast {
            position: fixed;
            bottom: 80px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: var(--bg-card);
            border: 1px solid var(--primary);
            color: var(--text-primary);
            padding: 0.75rem 1.5rem;
            border-radius: 30px;
            font-size: 0.9rem;
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 1000;
        }
        .toast.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="actions-page">
        <header class="actions-header">
            <a href="/bible/" class="back-btn" id="backBtn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </a>
            <h1>Verse Actions</h1>
        </header>

        <div class="actions-body">
            <?php if ($displayRef): ?>
            <div class="verse-preview">
                <div class="verse-ref"><?= e($displayRef) ?></div>
                <div class="verse-text"><?= e($text) ?></div>
            </div>
            <?php endif; ?>

            <div class="section-title">Highlight Color</div>
            <div class="highlight-colors">
                <button class="color-btn color-1" onclick="applyHighlight(1)"></button>
                <button class="color-btn color-2" onclick="applyHighlight(2)"></button>
                <button class="color-btn color-3" onclick="applyHighlight(3)"></button>
                <button class="color-btn color-4" onclick="applyHighlight(4)"></button>
                <button class="color-btn color-5" onclick="applyHighlight(5)"></button>
                <button class="color-btn color-6" onclick="applyHighlight(6)"></button>
                <button class="color-btn color-clear" onclick="applyHighlight(0)">&times;</button>
            </div>

            <div class="section-title">Actions</div>
            <div class="actions-list">
                <button class="action-btn" onclick="toggleBookmark()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                    </svg>
                    Bookmark
                </button>

                <a href="/bible/add-note.php?book=<?= urlencode($book) ?>&chapter=<?= urlencode($chapter) ?>&verse=<?= urlencode($verse) ?>&text=<?= urlencode($text) ?>" class="action-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                    </svg>
                    Add Note
                </a>

                <button class="action-btn" onclick="askAI()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M12 1v6m0 6v6M1 12h6m6 0h6"/>
                    </svg>
                    Ask AI
                </button>

                <button class="action-btn" onclick="loadCrossRefs()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                        <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                    </svg>
                    Cross References
                </button>

                <button class="action-btn" onclick="copyVerse()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="9" y="9" width="13" height="13" rx="2"/>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                    </svg>
                    Copy
                </button>

                <button class="action-btn" onclick="shareVerse()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="18" cy="5" r="3"/>
                        <circle cx="6" cy="12" r="3"/>
                        <circle cx="18" cy="19" r="3"/>
                        <path d="M8.59 13.51l6.83 3.98m-.01-10.98l-6.82 3.98"/>
                    </svg>
                    Share
                </button>
            </div>
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const verseData = {
            book: <?= json_encode($book) ?>,
            chapter: <?= json_encode((int)$chapter) ?>,
            verse: <?= json_encode((int)$verse) ?>,
            text: <?= json_encode($text) ?>,
            ref: <?= json_encode($displayRef) ?>
        };

        // Get book index
        const allBooks = [
            'Genesis', 'Exodus', 'Leviticus', 'Numbers', 'Deuteronomy',
            'Joshua', 'Judges', 'Ruth', '1 Samuel', '2 Samuel', '1 Kings', '2 Kings',
            '1 Chronicles', '2 Chronicles', 'Ezra', 'Nehemiah', 'Esther',
            'Job', 'Psalms', 'Proverbs', 'Ecclesiastes', 'Song of Solomon',
            'Isaiah', 'Jeremiah', 'Lamentations', 'Ezekiel', 'Daniel',
            'Hosea', 'Joel', 'Amos', 'Obadiah', 'Jonah', 'Micah', 'Nahum',
            'Habakkuk', 'Zephaniah', 'Haggai', 'Zechariah', 'Malachi',
            'Matthew', 'Mark', 'Luke', 'John', 'Acts',
            'Romans', '1 Corinthians', '2 Corinthians', 'Galatians', 'Ephesians',
            'Philippians', 'Colossians', '1 Thessalonians', '2 Thessalonians',
            '1 Timothy', '2 Timothy', 'Titus', 'Philemon',
            'Hebrews', 'James', '1 Peter', '2 Peter', '1 John', '2 John', '3 John',
            'Jude', 'Revelation'
        ];
        const bookIndex = allBooks.indexOf(verseData.book) + 1;

        function showToast(msg) {
            const toast = document.getElementById('toast');
            toast.textContent = msg;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 2500);
        }

        async function applyHighlight(color) {
            try {
                const formData = new FormData();
                formData.append('action', color === 0 ? 'remove' : 'add');
                formData.append('book_number', bookIndex);
                formData.append('chapter', verseData.chapter);
                formData.append('verse', verseData.verse);
                formData.append('color', color);

                const res = await fetch('/bible/api/highlights.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-CSRF-Token': csrfToken }
                });

                const data = await res.json();
                if (data.ok) {
                    showToast(color === 0 ? 'Highlight removed' : 'Highlight saved');
                } else {
                    showToast('Error: ' + (data.error || 'Failed'));
                }
            } catch (e) {
                showToast('Error saving highlight');
            }
        }

        async function toggleBookmark() {
            try {
                const formData = new FormData();
                formData.append('action', 'toggle');
                formData.append('book_number', bookIndex);
                formData.append('chapter', verseData.chapter);
                formData.append('verse', verseData.verse);

                const res = await fetch('/bible/api/bookmarks.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-CSRF-Token': csrfToken }
                });

                const data = await res.json();
                if (data.ok) {
                    showToast(data.bookmarked ? 'Bookmark added' : 'Bookmark removed');
                } else {
                    showToast('Error: ' + (data.error || 'Failed'));
                }
            } catch (e) {
                showToast('Error saving bookmark');
            }
        }

        function copyVerse() {
            const copyText = `${verseData.ref} - ${verseData.text} (KJV)`;
            navigator.clipboard.writeText(copyText).then(() => {
                showToast('Verse copied!');
            }).catch(() => {
                showToast('Failed to copy');
            });
        }

        function shareVerse() {
            const shareText = `${verseData.ref} - ${verseData.text} (KJV)`;
            if (navigator.share) {
                navigator.share({
                    title: verseData.ref,
                    text: shareText
                }).catch(() => {});
            } else {
                copyVerse();
            }
        }

        async function askAI() {
            // Navigate to AI page
            window.location.href = '/bible/ai-explain.php?book=' + encodeURIComponent(verseData.book) +
                '&chapter=' + verseData.chapter +
                '&verse=' + verseData.verse +
                '&text=' + encodeURIComponent(verseData.text);
        }

        async function loadCrossRefs() {
            // Navigate to cross refs page
            window.location.href = '/bible/cross-refs.php?book=' + encodeURIComponent(verseData.book) +
                '&chapter=' + verseData.chapter +
                '&verse=' + verseData.verse;
        }
    </script>
</body>
</html>
