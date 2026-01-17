<?php
/**
 * CRC Notifications - Main Page
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$pageTitle = "Notifications - CRC";

// Get filter
$filter = $_GET['filter'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query based on filter
$whereClause = "WHERE user_id = ?";
$params = [$user['id']];

if ($filter === 'unread') {
    $whereClause .= " AND read_at IS NULL";
} elseif ($filter === 'read') {
    $whereClause .= " AND read_at IS NOT NULL";
}

// Initialize defaults
$totalCount = 0;
$notifications = [];
$unreadCount = 0;

// Get total count
try {
    $totalCount = Database::fetchColumn(
        "SELECT COUNT(*) FROM notifications $whereClause",
        $params
    ) ?: 0;
} catch (Exception $e) {}

// Get notifications
try {
    $notifications = Database::fetchAll(
        "SELECT * FROM notifications $whereClause ORDER BY created_at DESC LIMIT $perPage OFFSET $offset",
        $params
    ) ?: [];
} catch (Exception $e) {}

// Get unread count
try {
    $unreadCount = Database::fetchColumn(
        "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL",
        [$user['id']]
    ) ?: 0;
} catch (Exception $e) {}

$totalPages = ceil($totalCount / $perPage);

// Group notifications by date
$grouped = [];
foreach ($notifications as $notif) {
    $date = date('Y-m-d', strtotime($notif['created_at']));
    $grouped[$date][] = $notif;
}

// Time ago helper
function notifTimeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', $time);
}

// Notification type icons and colors
function getNotifIcon($type) {
    $icons = [
        'event_reminder' => '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
        'prayer_answered' => '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>',
        'homecell_join' => '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>',
        'homecell_meeting' => '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
        'course_complete' => '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        'lesson_unlock' => '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 019.9-1"/></svg>',
        'new_sermon' => '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>',
        'livestream_start' => '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>',
        'announcement' => '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 17H2a3 3 0 003 3h14a3 3 0 003-3zm-4-4H6V5a3 3 0 013-3h6a3 3 0 013 3v8z"/></svg>',
        'system' => '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg>',
        'welcome' => '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 9V5a3 3 0 00-3-3l-4 9v11h11.28a2 2 0 002-1.7l1.38-9a2 2 0 00-2-2.3zM7 22H4a2 2 0 01-2-2v-7a2 2 0 012-2h3"/></svg>',
        'invite_accepted' => '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg>',
    ];
    return $icons[$type] ?? '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>';
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
        .topbar-actions { display: flex; align-items: center; gap: 8px; }
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
        .feed-tab .count {
            display: inline-block;
            margin-left: 6px;
            padding: 2px 8px;
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
            font-size: 12px;
        }
        .page-header {
            padding: 20px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .page-title { font-size: 24px; font-weight: 700; }
        .page-subtitle { font-size: 14px; color: var(--muted); margin-top: 4px; }
        .header-actions { display: flex; gap: 10px; }
        .btn {
            padding: 10px 16px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            border: none;
        }
        .btn-outline {
            background: var(--card);
            color: var(--text);
            border: 1px solid var(--line);
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: white;
        }
        .btn:hover { opacity: 0.9; }
        .btn svg { width: 16px; height: 16px; }
        .section { padding: 0 16px 20px; }
        .date-header {
            font-size: 14px;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .notif-list { display: flex; flex-direction: column; gap: 8px; margin-bottom: 24px; }
        .notif-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 16px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .notif-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .notif-item.unread {
            border-left: 4px solid var(--accent);
            background: var(--card);
        }
        .notif-item.unread::before {
            content: '';
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 10px;
            height: 10px;
            background: var(--accent);
            border-radius: 50%;
        }
        .notif-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }
        .notif-icon svg { width: 22px; height: 22px; }
        .notif-content { flex: 1; min-width: 0; }
        .notif-title { font-size: 15px; font-weight: 600; margin-bottom: 4px; }
        .notif-message { font-size: 14px; color: var(--muted); line-height: 1.4; margin-bottom: 6px; }
        .notif-time { font-size: 12px; color: var(--muted); }
        .notif-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            background: var(--bg);
            color: var(--muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: all 0.2s;
        }
        .action-btn:hover {
            background: var(--accent);
            color: white;
        }
        .action-btn.delete:hover {
            background: #EF4444;
        }
        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            padding: 20px;
        }
        .page-btn {
            padding: 10px 16px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 10px;
            color: var(--text);
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
        }
        .page-btn:hover { border-color: var(--accent); }
        .page-info { font-size: 14px; color: var(--muted); }
        .empty-state {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 48px 24px;
            text-align: center;
            margin: 0 16px;
        }
        .empty-icon {
            width: 80px;
            height: 80px;
            background: var(--bg);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }
        .empty-icon svg { width: 40px; height: 40px; color: var(--muted); }
        .empty-title { font-size: 18px; font-weight: 600; margin-bottom: 8px; }
        .empty-desc { font-size: 14px; color: var(--muted); }
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
                    <?= strtoupper(substr($user['first_name'] ?? $user['name'] ?? 'U', 0, 1)) ?>
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
        <a href="?filter=all" class="feed-tab <?= $filter === 'all' ? 'active' : '' ?>">
            All <span class="count"><?= $totalCount ?></span>
        </a>
        <a href="?filter=unread" class="feed-tab <?= $filter === 'unread' ? 'active' : '' ?>">
            Unread <span class="count"><?= $unreadCount ?></span>
        </a>
        <a href="?filter=read" class="feed-tab <?= $filter === 'read' ? 'active' : '' ?>">
            Read
        </a>
    </div>

    <div class="page-header">
        <div>
            <h1 class="page-title">Notifications</h1>
            <p class="page-subtitle"><?= $unreadCount ?> unread notification<?= $unreadCount != 1 ? 's' : '' ?></p>
        </div>
        <div class="header-actions">
            <?php if ($unreadCount > 0): ?>
                <button onclick="markAllRead()" class="btn btn-outline">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    Mark All Read
                </button>
            <?php endif; ?>
            <a href="/notifications/settings.php" class="btn btn-outline">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                Settings
            </a>
        </div>
    </div>

    <?php if ($grouped): ?>
        <?php foreach ($grouped as $date => $dayNotifs): ?>
            <section class="section">
                <h3 class="date-header">
                    <?php
                    $today = date('Y-m-d');
                    $yesterday = date('Y-m-d', strtotime('-1 day'));
                    if ($date === $today) {
                        echo 'Today';
                    } elseif ($date === $yesterday) {
                        echo 'Yesterday';
                    } else {
                        echo date('l, F j', strtotime($date));
                    }
                    ?>
                </h3>
                <div class="notif-list">
                    <?php foreach ($dayNotifs as $notif): ?>
                        <div class="notif-item <?= $notif['read_at'] ? 'read' : 'unread' ?>"
                             data-id="<?= $notif['id'] ?>"
                             onclick="handleNotificationClick(<?= $notif['id'] ?>, '<?= e($notif['link'] ?? '') ?>')">
                            <div class="notif-icon">
                                <?= getNotifIcon($notif['type']) ?>
                            </div>
                            <div class="notif-content">
                                <h4 class="notif-title"><?= e($notif['title']) ?></h4>
                                <p class="notif-message"><?= e($notif['message']) ?></p>
                                <span class="notif-time"><?= notifTimeAgo($notif['created_at']) ?></span>
                            </div>
                            <div class="notif-actions">
                                <?php if (!$notif['read_at']): ?>
                                    <button onclick="event.stopPropagation(); markRead(<?= $notif['id'] ?>)"
                                            class="action-btn" title="Mark as read">
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="16" height="16"><polyline points="20 6 9 17 4 12"/></svg>
                                    </button>
                                <?php endif; ?>
                                <button onclick="event.stopPropagation(); deleteNotification(<?= $notif['id'] ?>)"
                                        class="action-btn delete" title="Delete">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="16" height="16"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?filter=<?= $filter ?>&page=<?= $page - 1 ?>" class="page-btn">Previous</a>
                <?php endif; ?>
                <span class="page-info">Page <?= $page ?> of <?= $totalPages ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="?filter=<?= $filter ?>&page=<?= $page + 1 ?>" class="page-btn">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 01-3.46 0"/>
                </svg>
            </div>
            <h3 class="empty-title">No notifications</h3>
            <p class="empty-desc">
                <?php if ($filter === 'unread'): ?>
                    You're all caught up! No unread notifications.
                <?php elseif ($filter === 'read'): ?>
                    No read notifications yet.
                <?php else: ?>
                    You don't have any notifications yet.
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

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
        <a href="/notifications/" class="active">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
            Alerts
        </a>
        <a href="/profile/">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Profile
        </a>
    </nav>

    <script>
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

        async function handleNotificationClick(id, link) {
            await markRead(id);
            if (link) {
                window.location.href = link;
            }
        }

        async function markRead(id) {
            try {
                const response = await fetch('/notifications/api/mark_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({ id: id })
                });
                if (response.ok) {
                    const item = document.querySelector(`.notif-item[data-id="${id}"]`);
                    if (item) {
                        item.classList.remove('unread');
                        item.classList.add('read');
                        const markBtn = item.querySelector('.action-btn:not(.delete)');
                        if (markBtn) markBtn.remove();
                    }
                }
            } catch (e) {
                console.error('Error marking as read:', e);
            }
        }

        async function markAllRead() {
            try {
                const response = await fetch('/notifications/api/mark_all_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    }
                });
                if (response.ok) {
                    location.reload();
                }
            } catch (e) {
                console.error('Error marking all as read:', e);
            }
        }

        async function deleteNotification(id) {
            if (!confirm('Delete this notification?')) return;
            try {
                const response = await fetch('/notifications/api/delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({ id: id })
                });
                if (response.ok) {
                    const item = document.querySelector(`.notif-item[data-id="${id}"]`);
                    if (item) {
                        item.style.transform = 'translateX(100%)';
                        item.style.opacity = '0';
                        setTimeout(() => item.remove(), 300);
                    }
                }
            } catch (e) {
                console.error('Error deleting notification:', e);
            }
        }
    </script>
</body>
</html>
