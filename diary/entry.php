<?php
/**
 * CRC Diary - Entry View/Edit
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$entryId = (int)($_GET['id'] ?? 0);
$entry = null;
$entryTags = [];

if ($entryId) {
    $entry = Database::fetchOne(
        "SELECT * FROM diary_entries WHERE id = ? AND user_id = ?",
        [$entryId, $user['id']]
    );

    if (!$entry) {
        Response::redirect('/diary/');
    }

    // Get tags for this entry
    $entryTags = Database::fetchAll(
        "SELECT t.* FROM diary_tags t
         JOIN diary_tag_links det ON t.id = det.tag_id
         WHERE det.entry_id = ?",
        [$entryId]
    );

    $pageTitle = ($entry['title'] ?: 'Entry') . ' - My Diary';
} else {
    $pageTitle = 'New Entry - My Diary';
}

// Get all user's tags for autocomplete
$allTags = Database::fetchAll(
    "SELECT * FROM diary_tags WHERE user_id = ? ORDER BY name ASC",
    [$user['id']]
);

$moods = ['grateful', 'joyful', 'peaceful', 'hopeful', 'anxious', 'sad', 'angry', 'confused'];

function getMoodEmoji($mood) {
    $emojis = [
        'grateful' => '🙏',
        'joyful' => '😊',
        'peaceful' => '😌',
        'hopeful' => '🌟',
        'anxious' => '😰',
        'sad' => '😢',
        'angry' => '😤',
        'confused' => '😕'
    ];
    return $emojis[$mood] ?? '📝';
}

function getMoodColor($mood) {
    $colors = [
        'grateful' => '#8B5CF6',
        'joyful' => '#F59E0B',
        'peaceful' => '#06B6D4',
        'hopeful' => '#10B981',
        'anxious' => '#EF4444',
        'sad' => '#6366F1',
        'angry' => '#DC2626',
        'confused' => '#F97316'
    ];
    return $colors[$mood] ?? '#8B5CF6';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #F3F4F6;
            --card: #FFFFFF;
            --text: #111827;
            --muted: #6B7280;
            --line: #E5E7EB;
            --accent: #7C3AED;
            --accent2: #22D3EE;
        }
        [data-theme="dark"] {
            --bg: #0f172a;
            --card: #1e293b;
            --text: #f1f5f9;
            --muted: #94a3b8;
            --line: #334155;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding-bottom: 80px;
        }
        .topbar {
            background: var(--card);
            border-bottom: 1px solid var(--line);
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .topbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: 18px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .topbar-brand svg { width: 32px; height: 32px; }
        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .topbar-btn {
            background: var(--bg);
            border: none;
            border-radius: 10px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text);
            position: relative;
        }
        .topbar-btn svg { width: 20px; height: 20px; }
        .notif-badge {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            background: #EF4444;
            border-radius: 50%;
        }
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
        }
        .dropdown { position: relative; }
        .dropdown-menu {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 12px;
            min-width: 200px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            z-index: 200;
            overflow: hidden;
        }
        .dropdown-menu.show { display: block; }
        .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text);
            text-decoration: none;
            font-size: 14px;
            transition: background 0.2s;
        }
        .dropdown-menu a:hover { background: var(--bg); }
        .dropdown-menu a svg { width: 18px; height: 18px; color: var(--muted); }
        .dropdown-divider { height: 1px; background: var(--line); margin: 4px 0; }
        .feed-tabs {
            display: flex;
            gap: 8px;
            padding: 12px 16px;
            background: var(--card);
            border-bottom: 1px solid var(--line);
            overflow-x: auto;
        }
        .feed-tab {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            white-space: nowrap;
            color: var(--muted);
            background: var(--bg);
            transition: all 0.2s;
        }
        .feed-tab.active {
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: white;
        }
        .page-header {
            padding: 20px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .page-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .back-btn {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: var(--card);
            border: 1px solid var(--line);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text);
            text-decoration: none;
        }
        .back-btn svg { width: 20px; height: 20px; }
        .page-title { font-size: 20px; font-weight: 700; }
        .delete-btn {
            background: rgba(239, 68, 68, 0.1);
            color: #EF4444;
            border: none;
            padding: 10px 16px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .delete-btn svg { width: 16px; height: 16px; }
        .form-container { padding: 0 16px; }
        .form-card {
            background: var(--card);
            border-radius: 16px;
            padding: 20px;
            border: 1px solid var(--line);
        }
        .form-group { margin-bottom: 20px; }
        .form-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-label svg { width: 16px; height: 16px; }
        .form-input,
        .form-textarea {
            width: 100%;
            padding: 14px 16px;
            font-size: 15px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: var(--bg);
            color: var(--text);
            font-family: inherit;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-input:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
        }
        .form-textarea { resize: vertical; min-height: 150px; }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .mood-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .mood-option {
            cursor: pointer;
        }
        .mood-option input { display: none; }
        .mood-chip {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 10px 14px;
            background: var(--bg);
            border: 2px solid transparent;
            border-radius: 24px;
            transition: all 0.2s;
        }
        .mood-option input:checked + .mood-chip {
            border-color: var(--mood-color, var(--accent));
            background: var(--card);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .mood-emoji { font-size: 18px; }
        .mood-text { font-size: 13px; font-weight: 500; color: var(--text); }
        .tags-wrapper {
            background: var(--bg);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 12px;
        }
        .tags-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }
        .tag-chip {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: white;
            border-radius: 16px;
            font-size: 13px;
        }
        .tag-remove {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 16px;
            line-height: 1;
            opacity: 0.8;
        }
        .tag-remove:hover { opacity: 1; }
        .tags-input {
            width: 100%;
            padding: 8px;
            font-size: 14px;
            border: none;
            background: transparent;
            color: var(--text);
            outline: none;
        }
        .tag-suggestions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--line);
        }
        .tag-suggestion {
            padding: 6px 12px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            font-size: 13px;
            color: var(--muted);
            cursor: pointer;
            transition: all 0.2s;
        }
        .tag-suggestion:hover {
            border-color: var(--accent);
            color: var(--accent);
        }
        .privacy-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .privacy-option { cursor: pointer; }
        .privacy-option input { display: none; }
        .privacy-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            padding: 16px;
            background: var(--bg);
            border: 2px solid transparent;
            border-radius: 12px;
            text-align: center;
            transition: all 0.2s;
        }
        .privacy-option input:checked + .privacy-card {
            border-color: var(--accent);
            background: var(--card);
        }
        .privacy-card svg { width: 24px; height: 24px; color: var(--muted); }
        .privacy-label { font-size: 14px; font-weight: 600; color: var(--text); }
        .privacy-desc { font-size: 12px; color: var(--muted); }
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--line);
        }
        .btn {
            flex: 1;
            padding: 14px 20px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            border: none;
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .btn svg { width: 18px; height: 18px; }
        .btn-secondary {
            background: var(--bg);
            color: var(--text);
            border: 1px solid var(--line);
        }
        .btn-ai {
            background: linear-gradient(135deg, #8B5CF6, #6366F1);
            color: white;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: white;
        }
        .btn:hover { opacity: 0.9; }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .entry-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 16px;
            padding: 0 16px;
        }
        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--muted);
        }
        .meta-item svg { width: 14px; height: 14px; }
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--card);
            border-top: 1px solid var(--line);
            display: flex;
            justify-content: space-around;
            padding: 8px 0 20px;
            z-index: 100;
        }
        .bottom-nav a {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            color: var(--muted);
            text-decoration: none;
            font-size: 11px;
            padding: 4px 12px;
        }
        .bottom-nav a.active { color: var(--accent); }
        .bottom-nav svg { width: 24px; height: 24px; }
        @media (max-width: 500px) {
            .form-row, .privacy-options { grid-template-columns: 1fr; }
            .form-actions { flex-direction: column; }
        }
        .spin { animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="topbar-brand">
            <svg viewBox="0 0 32 32" fill="none">
                <rect width="32" height="32" rx="8" fill="url(#g1)"/>
                <path d="M16 6L22 10V22L16 26L10 22V10L16 6Z" fill="white" fill-opacity="0.9"/>
                <defs>
                    <linearGradient id="g1" x1="0" y1="0" x2="32" y2="32">
                        <stop stop-color="#7C3AED"/>
                        <stop offset="1" stop-color="#22D3EE"/>
                    </linearGradient>
                </defs>
            </svg>
            CRC App
        </div>
        <div class="topbar-actions">
            <button class="topbar-btn" onclick="toggleTheme()" title="Toggle theme">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M21 12.79A9 9 0 1111.21 3a7 7 0 009.79 9.79z"/>
                </svg>
            </button>
            <button class="topbar-btn">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/>
                </svg>
                <span class="notif-badge"></span>
            </button>
            <div class="dropdown">
                <button class="topbar-btn" onclick="toggleMoreMenu()">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>
                    </svg>
                </button>
                <div class="dropdown-menu" id="moreMenu">
                    <a href="/home/">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                        Home
                    </a>
                    <a href="/feed/">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 11a9 9 0 019 9M4 4a16 16 0 0116 16"/><circle cx="5" cy="19" r="1"/></svg>
                        Feed
                    </a>
                    <a href="/bible/">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                        Bible
                    </a>
                    <a href="/bible/ai.php">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                        AI SmartBible
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="/morning_watch/">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                        Morning Study
                    </a>
                    <a href="/calendar/">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        Calendar
                    </a>
                    <a href="/media/">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                        Media
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="/diary/">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>
                        My Diary
                    </a>
                    <a href="/homecells/">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                        Homecells
                    </a>
                    <a href="/courses/">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c0 2 6 3 6 3s6-1 6-3v-5"/></svg>
                        Courses
                    </a>
                </div>
            </div>
            <div class="dropdown">
                <div class="user-avatar" onclick="toggleUserMenu()">
                    <?= strtoupper(substr($user['first_name'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="dropdown-menu" id="userMenu">
                    <a href="/profile/">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        My Profile
                    </a>
                    <a href="/settings/">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                        Settings
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="/auth/logout.php">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="feed-tabs">
        <a href="/diary/" class="feed-tab">Entries</a>
        <a href="/diary/entry.php" class="feed-tab active"><?= $entry ? 'Edit Entry' : 'New Entry' ?></a>
    </div>

    <div class="page-header">
        <div class="page-header-left">
            <a href="/diary/" class="back-btn">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
            </a>
            <h1 class="page-title"><?= $entry ? 'Edit Entry' : 'New Entry' ?></h1>
        </div>
        <?php if ($entry): ?>
            <button type="button" class="delete-btn" onclick="deleteEntry(<?= $entryId ?>)">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                </svg>
                Delete
            </button>
        <?php endif; ?>
    </div>

    <div class="form-container">
        <form id="entry-form" class="form-card" data-entry-id="<?= $entryId ?>">
            <?= CSRF::field() ?>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Title
                    </label>
                    <input type="text" id="title" name="title" class="form-input" value="<?= e($entry['title'] ?? '') ?>" placeholder="Give your entry a title (optional)">
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        Date
                    </label>
                    <input type="date" id="entry_date" name="entry_date" class="form-input" value="<?= $entry ? date('Y-m-d', strtotime($entry['entry_date'])) : date('Y-m-d') ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                    How are you feeling?
                </label>
                <div class="mood-selector">
                    <?php foreach ($moods as $m):
                        $emoji = getMoodEmoji($m);
                        $color = getMoodColor($m);
                    ?>
                        <label class="mood-option" style="--mood-color: <?= $color ?>">
                            <input type="radio" name="mood" value="<?= $m ?>" <?= ($entry['mood'] ?? '') === $m ? 'checked' : '' ?>>
                            <span class="mood-chip">
                                <span class="mood-emoji"><?= $emoji ?></span>
                                <span class="mood-text"><?= ucfirst($m) ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="17" y1="10" x2="3" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="17" y1="18" x2="3" y2="18"/></svg>
                    Your Thoughts
                </label>
                <textarea id="content" name="content" class="form-textarea" rows="8" placeholder="What's on your mind today? Share your thoughts, prayers, and reflections..."><?= e($entry['content'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    Scripture Reference
                </label>
                <input type="text" id="scripture_ref" name="scripture_ref" class="form-input" value="<?= e($entry['scripture_ref'] ?? '') ?>" placeholder="e.g., Psalm 23:1-6, John 3:16">
            </div>

            <div class="form-group">
                <label class="form-label">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                    Tags
                </label>
                <div class="tags-wrapper">
                    <div class="tags-list" id="tags-list">
                        <?php foreach ($entryTags as $t): ?>
                            <span class="tag-chip" data-id="<?= $t['id'] ?>">
                                <?= e($t['name']) ?>
                                <button type="button" class="tag-remove">&times;</button>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <input type="text" id="tag-input" class="tags-input" placeholder="Add tags...">
                    <?php if (!empty($allTags)): ?>
                        <div class="tag-suggestions" id="tag-suggestions">
                            <?php foreach ($allTags as $t): ?>
                                <button type="button" class="tag-suggestion" data-id="<?= $t['id'] ?>" data-name="<?= e($t['name']) ?>">
                                    <?= e($t['name']) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                    Privacy
                </label>
                <div class="privacy-options">
                    <label class="privacy-option">
                        <input type="radio" name="is_private" value="1" <?= ($entry['is_private'] ?? 1) == 1 ? 'checked' : '' ?>>
                        <span class="privacy-card">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                            <span class="privacy-label">Private</span>
                            <span class="privacy-desc">Only visible to you</span>
                        </span>
                    </label>
                    <label class="privacy-option">
                        <input type="radio" name="is_private" value="0" <?= ($entry['is_private'] ?? 1) == 0 ? 'checked' : '' ?>>
                        <span class="privacy-card">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                            <span class="privacy-label">Share with leaders</span>
                            <span class="privacy-desc">Visible to church leaders</span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <a href="/diary/" class="btn btn-secondary">Cancel</a>
                <button type="button" class="btn btn-ai" id="aiAssistBtn">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    AI Assist
                </button>
                <button type="submit" class="btn btn-primary">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    <?= $entry ? 'Save Changes' : 'Save Entry' ?>
                </button>
            </div>
        </form>

        <?php if ($entry): ?>
            <div class="entry-meta">
                <span class="meta-item">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Created: <?= date('M j, Y \a\t g:i A', strtotime($entry['created_at'])) ?>
                </span>
                <?php if ($entry['updated_at'] !== $entry['created_at']): ?>
                    <span class="meta-item">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Updated: <?= date('M j, Y \a\t g:i A', strtotime($entry['updated_at'])) ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <nav class="bottom-nav">
        <a href="/home/">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
            Home
        </a>
        <a href="/feed/">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 11a9 9 0 019 9M4 4a16 16 0 0116 16"/><circle cx="5" cy="19" r="1"/></svg>
            Feed
        </a>
        <a href="/bible/">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
            Bible
        </a>
        <a href="/diary/" class="active">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>
            Diary
        </a>
        <a href="/profile/">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Profile
        </a>
    </nav>

    <script>
        const allTags = <?= json_encode(array_map(fn($t) => ['id' => $t['id'], 'name' => $t['name']], $allTags ?? [])) ?>;
        const CSRF_TOKEN = '<?= CSRF::token() ?>';

        function toggleTheme() {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
        }
        function toggleMoreMenu() {
            document.getElementById('moreMenu').classList.toggle('show');
            document.getElementById('userMenu').classList.remove('show');
        }
        function toggleUserMenu() {
            document.getElementById('userMenu').classList.toggle('show');
            document.getElementById('moreMenu').classList.remove('show');
        }
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown')) {
                document.getElementById('moreMenu').classList.remove('show');
                document.getElementById('userMenu').classList.remove('show');
            }
        });
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);

        // AI Assist functionality
        document.getElementById('aiAssistBtn')?.addEventListener('click', async function() {
            const contentEl = document.getElementById('content');
            const text = contentEl?.value?.trim();

            if (!text) {
                alert('Please write something first');
                return;
            }

            const btn = this;
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<svg class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><circle cx="12" cy="12" r="10" stroke-dasharray="32" stroke-dashoffset="32"></circle></svg> Enhancing...';

            try {
                const response = await fetch('/diary/api/ai_enhance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({ text: text })
                });

                const data = await response.json();

                if (data.success && data.enhanced_text) {
                    contentEl.value = data.enhanced_text;
                    contentEl.style.borderColor = '#10B981';
                    setTimeout(() => contentEl.style.borderColor = '', 2000);
                } else {
                    alert(data.error || 'AI enhancement failed. Please try again.');
                }
            } catch (error) {
                console.error('AI enhance error:', error);
                alert('Failed to connect to AI service. Please try again.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        });
    </script>
    <script src="/diary/js/diary.js"></script>
</body>
</html>
