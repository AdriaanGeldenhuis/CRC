<?php
/**
 * CRC Bible - Verse Actions Page
 * Premium Glass Morphism Design - Matches Home Page
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=no">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        (function() {
            const saved = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', saved);
        })();
    </script>
    <style>
        /* ===== CSS VARIABLES - DARK THEME (DEFAULT) ===== */
        :root {
            --bg0: #070A12;
            --bg1: #090F1F;
            --bg2: #0D1326;
            --bg-glass: rgba(9, 15, 31, 0.85);
            --card: rgba(255,255,255,0.06);
            --card2: rgba(255,255,255,0.085);
            --line: rgba(255,255,255,0.10);
            --text: #EAF0FF;
            --muted: rgba(234,240,255,0.72);
            --accent: #7C3AED;
            --accent2: #22D3EE;
            --accent-glow: rgba(124, 58, 237, 0.4);
            --good: #22C55E;
            --bad: #EF4444;
            --font: 'Inter', -apple-system, BlinkMacSystemFont, system-ui, sans-serif;
            --radius: 18px;
            --radius-sm: 10px;
            --blur: 18px;

            /* Highlight Colors */
            --highlight-1: rgba(244, 114, 182, 0.4);
            --highlight-2: rgba(251, 146, 60, 0.4);
            --highlight-3: rgba(250, 204, 21, 0.4);
            --highlight-4: rgba(34, 197, 94, 0.4);
            --highlight-5: rgba(59, 130, 246, 0.4);
            --highlight-6: rgba(139, 92, 246, 0.4);
        }

        /* ===== LIGHT THEME ===== */
        [data-theme="light"] {
            --bg0: #F6F7FB;
            --bg1: #FFFFFF;
            --bg2: #F0F2F8;
            --bg-glass: rgba(255, 255, 255, 0.85);
            --card: rgba(10,15,31,0.05);
            --card2: rgba(10,15,31,0.07);
            --line: rgba(10,15,31,0.12);
            --text: #0A0F1F;
            --muted: rgba(10,15,31,0.72);
            --accent-glow: rgba(124, 58, 237, 0.15);

            --highlight-1: rgba(244, 114, 182, 0.35);
            --highlight-2: rgba(251, 146, 60, 0.35);
            --highlight-3: rgba(250, 204, 21, 0.35);
            --highlight-4: rgba(34, 197, 94, 0.35);
            --highlight-5: rgba(59, 130, 246, 0.35);
            --highlight-6: rgba(139, 92, 246, 0.35);
        }

        /* ===== GLOBAL RESET ===== */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

        html {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body {
            font-family: var(--font);
            background: var(--bg0);
            background-image:
                radial-gradient(ellipse 1200px 800px at 15% -10%, rgba(124, 58, 237, 0.4), transparent 60%),
                radial-gradient(ellipse 900px 600px at 85% 0%, rgba(34, 211, 238, 0.35), transparent 60%);
            background-attachment: fixed;
            color: var(--text);
            min-height: 100vh;
            line-height: 1.5;
        }

        [data-theme="light"] body {
            background-image:
                radial-gradient(ellipse 1200px 800px at 15% -10%, rgba(124, 58, 237, 0.15), transparent 60%),
                radial-gradient(ellipse 900px 600px at 85% 0%, rgba(34, 211, 238, 0.12), transparent 60%);
        }

        /* ===== PAGE LAYOUT ===== */
        .actions-page {
            min-height: 100vh;
            padding-top: 68px;
        }

        /* ===== HEADER ===== */
        .actions-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 68px;
            background: var(--bg-glass);
            backdrop-filter: blur(var(--blur));
            -webkit-backdrop-filter: blur(var(--blur));
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: center;
            padding: 0 16px;
            gap: 12px;
            z-index: 100;
        }

        .back-btn {
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            color: var(--muted);
            text-decoration: none;
            transition: all 0.12s ease;
        }

        .back-btn:active {
            background: var(--card2);
            color: var(--text);
            transform: scale(0.96);
        }

        .back-btn svg {
            width: 22px;
            height: 22px;
        }

        .actions-header h1 {
            font-size: 1rem;
            font-weight: 700;
            flex: 1;
            color: var(--text);
        }

        /* ===== BODY ===== */
        .actions-body {
            padding: 20px 16px;
            max-width: 600px;
            margin: 0 auto;
        }

        /* ===== VERSE PREVIEW ===== */
        .verse-preview {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 16px;
            margin-bottom: 24px;
        }

        .verse-ref {
            font-size: 0.8125rem;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 8px;
        }

        .verse-text {
            font-size: 0.9375rem;
            line-height: 1.7;
            color: var(--text);
        }

        /* ===== SECTION TITLE ===== */
        .section-title {
            font-size: 0.6875rem;
            font-weight: 700;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 12px;
            padding: 0 4px;
        }

        /* ===== HIGHLIGHT COLORS ===== */
        .highlight-colors {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: center;
            margin-bottom: 24px;
            padding: 12px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
        }

        .color-btn {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 3px solid transparent;
            cursor: pointer;
            transition: all 0.15s ease;
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
            background: var(--card2);
            border: 2px solid var(--line);
            color: var(--muted);
            font-size: 1.25rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .color-clear:active {
            border-color: var(--bad);
            color: var(--bad);
        }

        /* ===== ACTIONS LIST ===== */
        .actions-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            padding: 14px 16px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius-sm);
            color: var(--text);
            font-family: inherit;
            font-size: 0.9375rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.12s ease;
            min-height: 52px;
        }

        .action-btn:active {
            background: linear-gradient(135deg, var(--accent) 0%, #9333EA 100%);
            border-color: var(--accent);
            color: white;
            transform: scale(0.98);
        }

        .action-btn svg {
            width: 20px;
            height: 20px;
            color: var(--accent);
            flex-shrink: 0;
        }

        .action-btn:active svg {
            color: white;
        }

        /* ===== TOAST ===== */
        .toast {
            position: fixed;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: var(--bg-glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--line);
            color: var(--text);
            padding: 14px 24px;
            border-radius: var(--radius);
            font-size: 0.875rem;
            font-weight: 500;
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 0 8px 24px rgba(0,0,0,0.3);
        }

        .toast.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }

        .toast.success {
            border-color: var(--good);
        }

        .toast.error {
            border-color: var(--bad);
        }

        /* ===== SAFE AREA ===== */
        @supports (padding: max(0px)) {
            .actions-body {
                padding-bottom: max(20px, env(safe-area-inset-bottom));
            }
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

        function showToast(msg, type = '') {
            const toast = document.getElementById('toast');
            toast.textContent = msg;
            toast.className = 'toast show' + (type ? ' ' + type : '');
            setTimeout(() => toast.className = 'toast', 2500);
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
                    showToast(color === 0 ? 'Highlight removed' : 'Highlight saved', 'success');
                } else {
                    showToast('Error: ' + (data.error || 'Failed'), 'error');
                }
            } catch (e) {
                showToast('Error saving highlight', 'error');
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
                    showToast(data.bookmarked ? 'Bookmark added' : 'Bookmark removed', 'success');
                } else {
                    showToast('Error: ' + (data.error || 'Failed'), 'error');
                }
            } catch (e) {
                showToast('Error saving bookmark', 'error');
            }
        }

        function copyVerse() {
            const copyText = `${verseData.ref} - ${verseData.text} (KJV)`;
            navigator.clipboard.writeText(copyText).then(() => {
                showToast('Verse copied!', 'success');
            }).catch(() => {
                showToast('Failed to copy', 'error');
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
            window.location.href = '/bible/ai-explain.php?book=' + encodeURIComponent(verseData.book) +
                '&chapter=' + verseData.chapter +
                '&verse=' + verseData.verse +
                '&text=' + encodeURIComponent(verseData.text);
        }

        async function loadCrossRefs() {
            window.location.href = '/bible/cross-refs.php?book=' + encodeURIComponent(verseData.book) +
                '&chapter=' + verseData.chapter +
                '&verse=' + verseData.verse;
        }
    </script>
</body>
</html>
