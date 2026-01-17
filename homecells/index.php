<?php
/**
 * CRC Homecells - List
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
if (!$primaryCong) {
    Response::redirect('/onboarding/');
}

$pageTitle = "Homecells - CRC";

// Initialize defaults
$myHomecell = null;
$homecells = [];

// Get user's homecell membership
try {
    $myHomecell = Database::fetchOne(
        "SELECT h.*, u.name as leader_name,
                (SELECT COUNT(*) FROM homecell_members WHERE homecell_id = h.id AND status = 'active') as member_count
         FROM homecell_members hm
         JOIN homecells h ON hm.homecell_id = h.id
         LEFT JOIN users u ON h.leader_user_id = u.id
         WHERE hm.user_id = ? AND hm.status = 'active' AND h.congregation_id = ?",
        [$user['id'], $primaryCong['id']]
    );
} catch (Exception $e) {}

// Get all active homecells in congregation
try {
    $homecells = Database::fetchAll(
        "SELECT h.*, u.name as leader_name,
                (SELECT COUNT(*) FROM homecell_members WHERE homecell_id = h.id AND status = 'active') as member_count
         FROM homecells h
         LEFT JOIN users u ON h.leader_user_id = u.id
         WHERE h.congregation_id = ? AND h.status = 'active'
         ORDER BY h.name ASC",
        [$primaryCong['id']]
    ) ?: [];
} catch (Exception $e) {}

$days = ['monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
         'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday'];
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
        }
        .page-title { font-size: 24px; font-weight: 700; margin-bottom: 4px; }
        .page-subtitle { font-size: 14px; color: var(--muted); }
        .section { padding: 0 16px 20px; }
        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .my-cell-card {
            display: flex;
            align-items: center;
            gap: 16px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border-radius: 16px;
            padding: 20px;
            text-decoration: none;
            color: white;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .my-cell-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(124, 58, 237, 0.3);
        }
        .cell-icon {
            width: 56px;
            height: 56px;
            background: rgba(255,255,255,0.2);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }
        .cell-info { flex: 1; }
        .cell-name { font-size: 18px; font-weight: 700; margin-bottom: 4px; }
        .cell-leader { font-size: 14px; opacity: 0.9; margin-bottom: 8px; }
        .cell-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 13px;
            opacity: 0.85;
        }
        .cell-meta span { display: flex; align-items: center; gap: 4px; }
        .cell-arrow {
            width: 32px;
            height: 32px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .cell-arrow svg { width: 18px; height: 18px; }
        .cells-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
        }
        .cell-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .cell-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .card-name { font-size: 18px; font-weight: 600; }
        .my-badge {
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .card-desc {
            font-size: 14px;
            color: var(--muted);
            margin-bottom: 16px;
            line-height: 1.5;
        }
        .card-details {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 16px;
        }
        .detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: var(--text);
        }
        .detail-icon {
            width: 32px;
            height: 32px;
            background: var(--bg);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--muted);
        }
        .detail-icon svg { width: 16px; height: 16px; }
        .card-actions {
            display: flex;
            gap: 10px;
            padding-top: 16px;
            border-top: 1px solid var(--line);
        }
        .btn {
            flex: 1;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.2s;
            border: none;
        }
        .btn-outline {
            background: var(--bg);
            color: var(--text);
            border: 1px solid var(--line);
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: white;
        }
        .btn:hover { opacity: 0.9; }
        .empty-state {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 48px 24px;
            text-align: center;
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
        @media (max-width: 400px) {
            .cells-grid { grid-template-columns: 1fr; }
        }
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
        <a href="/homecells/" class="feed-tab active">Homecells</a>
        <a href="/groups/" class="feed-tab">Groups</a>
    </div>

    <div class="page-header">
        <h1 class="page-title">Homecells</h1>
        <p class="page-subtitle">Connect with believers in your area</p>
    </div>

    <?php if ($myHomecell): ?>
        <section class="section">
            <h2 class="section-title">My Homecell</h2>
            <a href="/homecells/view.php?id=<?= $myHomecell['id'] ?>" class="my-cell-card">
                <div class="cell-icon">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="28" height="28">
                        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                </div>
                <div class="cell-info">
                    <div class="cell-name"><?= e($myHomecell['name']) ?></div>
                    <div class="cell-leader">Led by <?= e($myHomecell['leader_name']) ?></div>
                    <div class="cell-meta">
                        <span>
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="14" height="14"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                            <?= ucfirst($myHomecell['meeting_day']) ?>s at <?= date('g:i A', strtotime($myHomecell['meeting_time'])) ?>
                        </span>
                        <span>
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="14" height="14"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                            <?= $myHomecell['member_count'] ?> members
                        </span>
                    </div>
                </div>
                <div class="cell-arrow">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </div>
            </a>
        </section>
    <?php endif; ?>

    <section class="section">
        <h2 class="section-title">All Homecells</h2>
        <?php if ($homecells): ?>
            <div class="cells-grid">
                <?php foreach ($homecells as $hc): ?>
                    <div class="cell-card">
                        <div class="card-header">
                            <h3 class="card-name"><?= e($hc['name']) ?></h3>
                            <?php if ($myHomecell && $myHomecell['id'] == $hc['id']): ?>
                                <span class="my-badge">My Cell</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($hc['description']): ?>
                            <p class="card-desc"><?= e(truncate($hc['description'], 100)) ?></p>
                        <?php endif; ?>

                        <div class="card-details">
                            <div class="detail-item">
                                <div class="detail-icon">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                </div>
                                <span>Led by <?= e($hc['leader_name']) ?></span>
                            </div>
                            <div class="detail-item">
                                <div class="detail-icon">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                                </div>
                                <span><?= ucfirst($hc['meeting_day']) ?>s at <?= date('g:i A', strtotime($hc['meeting_time'])) ?></span>
                            </div>
                            <div class="detail-item">
                                <div class="detail-icon">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                </div>
                                <span><?= e($hc['location'] ?: 'Contact leader') ?></span>
                            </div>
                            <div class="detail-item">
                                <div class="detail-icon">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                                </div>
                                <span><?= $hc['member_count'] ?> members</span>
                            </div>
                        </div>

                        <div class="card-actions">
                            <a href="/homecells/view.php?id=<?= $hc['id'] ?>" class="btn btn-outline">View Details</a>
                            <?php if (!$myHomecell): ?>
                                <button onclick="joinHomecell(<?= $hc['id'] ?>)" class="btn btn-primary">Join</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                </div>
                <h3 class="empty-title">No homecells yet</h3>
                <p class="empty-desc">Homecells haven't been set up for this congregation yet.</p>
            </div>
        <?php endif; ?>
    </section>

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
        <a href="/homecells/" class="active">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
            Cells
        </a>
        <a href="/profile/">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Profile
        </a>
    </nav>

    <script>
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
    </script>
    <script src="/homecells/js/homecells.js"></script>
</body>
</html>
