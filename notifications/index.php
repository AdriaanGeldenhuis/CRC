<?php
/**
 * CRC Notifications - Main Page
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
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

// Notification type icons
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
        'comment' => '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>',
        'heart' => '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>',
        'check' => '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>',
    ];
    return $icons[$type] ?? '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>';
}
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/home/css/home.css?v=<?= filemtime(__DIR__ . '/../home/css/home.css') ?>">
    <link rel="stylesheet" href="/gospel_media/css/gospel_media.css?v=<?= filemtime(__DIR__ . '/../gospel_media/css/gospel_media.css') ?>">
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
        /* ===== Notifications — home design components ===== */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding: 6px 2px 14px;
        }
        .page-title { font-size: 22px; font-weight: 800; letter-spacing: -0.3px; }
        .page-subtitle { font-size: 13px; color: var(--muted); margin-top: 4px; }
        .header-actions { display: flex; gap: 10px; flex-wrap: wrap; }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 14px;
            font-size: 13px;
            font-weight: 600;
            font-family: inherit;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid var(--line);
            color: var(--text);
            transition: transform 0.25s cubic-bezier(0.2,0.8,0.25,1.4), box-shadow 0.25s ease, border-color 0.25s ease;
        }
        .btn svg { width: 16px; height: 16px; }
        .btn-outline { background: var(--card2); }
        .btn-outline:hover { border-color: var(--accent); transform: translateY(-2px); }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: #fff;
            border: none;
            box-shadow: 0 10px 24px rgba(124,58,237,0.35);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 16px 34px rgba(124,58,237,0.45); }

        /* Filter tab counts (tabs themselves come from gospel_media.css) */
        .feed-tab .count {
            margin-left: 6px;
            padding: 1px 7px;
            border-radius: 999px;
            background: var(--card2);
            font-size: 11px;
            font-weight: 700;
        }
        .feed-tab.active .count { background: rgba(255,255,255,0.25); color: #fff; }

        .section { margin-bottom: 6px; }
        .date-header {
            font-size: 12px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin: 16px 4px 10px;
        }
        .notif-list { display: flex; flex-direction: column; gap: 10px; }

        .notif-item {
            position: relative;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 14px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow2);
            cursor: pointer;
            transition: transform 0.3s cubic-bezier(0.2,0.8,0.25,1.2), box-shadow 0.3s ease, border-color 0.3s ease;
        }
        .notif-item::after {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.40), transparent);
            pointer-events: none;
        }
        .notif-item:hover {
            transform: translateY(-3px);
            border-color: rgba(124,58,237,0.40);
            box-shadow: 0 22px 44px rgba(0,0,0,0.45), 0 0 26px rgba(124,58,237,0.16);
        }
        .notif-item.unread { border-left: 3px solid var(--accent); }

        .notif-icon {
            width: 46px;
            height: 46px;
            flex-shrink: 0;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            box-shadow: 0 8px 20px rgba(124,58,237,0.32), inset 0 1px 2px rgba(255,255,255,0.45);
        }
        .notif-icon svg { width: 22px; height: 22px; }
        .notif-content { flex: 1; min-width: 0; }
        .notif-title { font-size: 15px; font-weight: 700; margin-bottom: 4px; }
        .notif-message { font-size: 14px; color: var(--muted); line-height: 1.45; margin-bottom: 6px; }
        .notif-time { font-size: 12px; color: var(--muted2); }
        .notif-actions { display: flex; gap: 8px; flex-shrink: 0; }
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: var(--card2);
            color: var(--muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        .action-btn:hover { background: var(--accent); color: #fff; border-color: transparent; }
        .action-btn.delete:hover { background: var(--bad); }

        .pagination { display: flex; align-items: center; justify-content: center; gap: 16px; padding: 22px 0 8px; }
        .page-btn {
            padding: 10px 16px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 12px;
            color: var(--text);
            text-decoration: none;
            font-size: 14px;
            transition: border-color 0.2s ease, transform 0.2s ease;
        }
        .page-btn:hover { border-color: var(--accent); transform: translateY(-1px); }
        .page-info { font-size: 14px; color: var(--muted); }

        .empty-state {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow2);
            padding: 48px 24px;
            text-align: center;
        }
        .empty-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 16px;
            border-radius: 999px;
            background: linear-gradient(135deg, rgba(124,58,237,0.18), rgba(34,211,238,0.12));
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .empty-icon svg { width: 38px; height: 38px; color: var(--accent); }
        .empty-title { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
        .empty-desc { font-size: 14px; color: var(--muted); }

        @media (max-width: 640px) {
            .page-header { align-items: flex-start; }
        }
        @media (prefers-reduced-motion: reduce) {
            .notif-item, .btn, .page-btn { transition: none !important; }
        }
    </style>
</head>
<body data-theme="dark">
    <!-- Top Bar / Navigation -->
    <div class="topbar">
        <div class="inner">
            <a href="/home/" class="brand">
                <div class="logo" aria-hidden="true"></div>
                <div>
                    <h1>CRC App</h1>
                    <span><?= e($primaryCong['name'] ?? 'CRC') ?></span>
                </div>
            </a>

            <div class="actions">
                <div class="chip" title="Status">
                    <span class="dot"></span>
                    <?= e(explode(' ', $user['name'])[0]) ?>
                </div>

                <button class="theme-toggle" onclick="toggleTheme()" title="Toggle theme" data-ripple>
                    <svg class="sun-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 3v2m0 14v2M3 12h2m14 0h2M5.2 5.2l1.4 1.4m10.8 10.8l1.4 1.4M18.8 5.2l-1.4 1.4M6.6 17.4l-1.4 1.4"></path>
                        <circle cx="12" cy="12" r="5"></circle>
                    </svg>
                    <svg class="moon-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                    </svg>
                </button>

                <a href="/notifications/" class="nav-icon-btn" title="Notifications" data-ripple>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <?php if ($unreadCount > 0): ?>
                        <span class="notification-badge"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span>
                    <?php endif; ?>
                </a>

                <div class="more-menu">
                    <button class="more-menu-btn" onclick="toggleMoreMenu()" title="More" data-ripple>
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                            <circle cx="12" cy="5" r="2"></circle>
                            <circle cx="12" cy="12" r="2"></circle>
                            <circle cx="12" cy="19" r="2"></circle>
                        </svg>
                    </button>
                    <div class="more-dropdown" id="moreDropdown">
                        <a href="/home/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                            Home
                        </a>
                        <a href="/gospel_media/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 11a9 9 0 0 1 9 9"></path><path d="M4 4a16 16 0 0 1 16 16"></path><circle cx="5" cy="19" r="1"></circle></svg>
                            Feed
                        </a>
                        <a href="/bible/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path><path d="M12 6v7"></path><path d="M8 9h8"></path></svg>
                            Bible
                        </a>
                        <a href="/ai_smartbible/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path><circle cx="19" cy="5" r="3" fill="currentColor"></circle></svg>
                            AI SmartBible
                        </a>
                        <a href="/morning_watch/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line></svg>
                            Morning Study
                        </a>
                        <a href="/calendar/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                            Calendar
                        </a>
                        <a href="/media/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>
                            Media
                        </a>
                        <div class="more-dropdown-divider"></div>
                        <a href="/diary/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                            My Diary
                        </a>
                        <a href="/homecells/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            Homecells
                        </a>
                        <a href="/learning/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>
                            Courses
                        </a>
                    </div>
                </div>

                <div class="user-menu">
                    <button class="user-menu-btn" onclick="toggleUserMenu()">
                        <?php if (!empty($user['avatar'])): ?>
                            <img src="<?= e($user['avatar']) ?>" alt="" class="user-avatar" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                            <div class="user-avatar-placeholder" style="display:none;"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
                        <?php else: ?>
                            <div class="user-avatar-placeholder"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
                        <?php endif; ?>
                    </button>
                    <div class="user-dropdown" id="userDropdown">
                        <div class="user-dropdown-header">
                            <strong><?= e($user['name']) ?></strong>
                            <span><?= e($primaryCong['name'] ?? '') ?></span>
                        </div>
                        <div class="user-dropdown-divider"></div>
                        <a href="/profile/" class="user-dropdown-item">Profile</a>
                        <?php if ($primaryCong && Auth::isCongregationAdmin($primaryCong['id'])): ?>
                            <div class="user-dropdown-divider"></div>
                            <a href="/admin_congregation/" class="user-dropdown-item">Manage Congregation</a>
                        <?php endif; ?>
                        <?php if (Auth::isAdmin()): ?>
                            <a href="/admin/" class="user-dropdown-item">Admin Panel</a>
                        <?php endif; ?>
                        <div class="user-dropdown-divider"></div>
                        <a href="/auth/logout.php" class="user-dropdown-item logout">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <nav class="feed-tabs">
        <a href="?filter=all" class="feed-tab <?= $filter === 'all' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            <span>All<?php if ($totalCount > 0): ?> <b class="count"><?= $totalCount ?></b><?php endif; ?></span>
        </a>
        <a href="?filter=unread" class="feed-tab <?= $filter === 'unread' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
            <span>Unread<?php if ($unreadCount > 0): ?> <b class="count"><?= $unreadCount ?></b><?php endif; ?></span>
        </a>
        <a href="?filter=read" class="feed-tab <?= $filter === 'read' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
            <span>Read</span>
        </a>
    </nav>

    <main class="feed-container">
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
                                    <?= getNotifIcon(($notif['icon'] ?? null) ?: $notif['type']) ?>
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
                        <a href="?filter=<?= e($filter) ?>&page=<?= $page - 1 ?>" class="page-btn">Previous</a>
                    <?php endif; ?>
                    <span class="page-info">Page <?= $page ?> of <?= $totalPages ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a href="?filter=<?= e($filter) ?>&page=<?= $page + 1 ?>" class="page-btn">Next</a>
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
    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="/home/" class="bottom-nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
            <span>Home</span>
        </a>
        <a href="/gospel_media/" class="bottom-nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 11a9 9 0 0 1 9 9"></path><path d="M4 4a16 16 0 0 1 16 16"></path><circle cx="5" cy="19" r="1"></circle></svg>
            <span>Feed</span>
        </a>
        <a href="/bible/" class="bottom-nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
            <span>Bible</span>
        </a>
        <a href="/notifications/" class="bottom-nav-item active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
            <span>Alerts</span>
        </a>
        <a href="/profile/" class="bottom-nav-item">
            <?php if (!empty($user['avatar'])): ?>
                <img src="<?= e($user['avatar']) ?>" alt="" class="bottom-nav-avatar" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <div class="bottom-nav-avatar-placeholder" style="display:none;"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
            <?php else: ?>
                <div class="bottom-nav-avatar-placeholder"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
            <?php endif; ?>
            <span>Me</span>
        </a>
    </nav>

    <script>
        const CSRF_TOKEN = '<?= CSRF::token() ?>';
        const NOTIF_API = '/notifications/api/notifications.php';

        // Theme + menu toggles (match home)
        function toggleTheme() {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme') || 'dark';
            const next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            document.body.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
        }
        function toggleMoreMenu() {
            document.getElementById('moreDropdown').classList.toggle('show');
            document.getElementById('userDropdown')?.classList.remove('show');
        }
        function toggleUserMenu() {
            document.getElementById('userDropdown').classList.toggle('show');
            document.getElementById('moreDropdown')?.classList.remove('show');
        }
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.user-menu')) {
                document.getElementById('userDropdown')?.classList.remove('show');
            }
            if (!e.target.closest('.more-menu')) {
                document.getElementById('moreDropdown')?.classList.remove('show');
            }
        });

        // ===== Notifications API =====
        async function notifApi(action, extra) {
            return fetch(NOTIF_API, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN
                },
                body: JSON.stringify(Object.assign({ action: action }, extra || {}))
            });
        }

        async function handleNotificationClick(id, link) {
            await markRead(id);
            if (link) {
                window.location.href = link;
            }
        }

        async function markRead(id) {
            try {
                const res = await notifApi('mark_read', { notification_id: id });
                if (res.ok) {
                    const item = document.querySelector('.notif-item[data-id="' + id + '"]');
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
                const res = await notifApi('mark_all_read');
                if (res.ok) location.reload();
            } catch (e) {
                console.error('Error marking all as read:', e);
            }
        }

        async function deleteNotification(id) {
            if (!confirm('Delete this notification?')) return;
            try {
                const res = await notifApi('delete', { notification_id: id });
                if (res.ok) {
                    const item = document.querySelector('.notif-item[data-id="' + id + '"]');
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
