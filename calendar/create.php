<?php
/**
 * CRC Calendar - Create Event
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$primaryCong = Auth::primaryCongregation();
if (!$primaryCong) {
    Response::redirect('/onboarding/');
}

$user = Auth::user();
$pageTitle = 'Create Event - CRC';
$isAdmin = Auth::isCongregationAdmin($primaryCong['id']);

// Pre-fill date if passed
$prefillDate = $_GET['date'] ?? date('Y-m-d');
$prefillTime = $_GET['time'] ?? '09:00';
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
        .dropdown {
            position: relative;
        }
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
        .dropdown-divider {
            height: 1px;
            background: var(--line);
            margin: 4px 0;
        }
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
        .page-title {
            font-size: 20px;
            font-weight: 700;
        }
        .form-container {
            padding: 0 16px;
        }
        .form-card {
            background: var(--card);
            border-radius: 16px;
            padding: 20px;
            border: 1px solid var(--line);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group:last-child { margin-bottom: 0; }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-input,
        .form-select,
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
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
        }
        .form-textarea { resize: vertical; min-height: 100px; }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 0;
        }
        .form-check input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: var(--accent);
        }
        .form-check label {
            font-size: 15px;
            color: var(--text);
            cursor: pointer;
        }
        .color-options {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .color-option {
            cursor: pointer;
        }
        .color-option input { display: none; }
        .color-swatch {
            display: block;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 3px solid transparent;
            transition: all 0.2s;
        }
        .color-option input:checked + .color-swatch {
            border-color: var(--text);
            transform: scale(1.1);
        }
        .reminder-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .reminder-option {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }
        .reminder-option input {
            width: 18px;
            height: 18px;
            accent-color: var(--accent);
        }
        .reminder-option span {
            font-size: 15px;
            color: var(--text);
        }
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
            transition: all 0.2s;
        }
        .btn-secondary {
            background: var(--bg);
            color: var(--text);
            border: 1px solid var(--line);
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: white;
        }
        .btn-primary:hover { opacity: 0.9; }
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
            .form-row { grid-template-columns: 1fr; }
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
        <a href="/calendar/" class="feed-tab">Calendar</a>
        <a href="/calendar/create.php" class="feed-tab active">Create Event</a>
    </div>

    <div class="page-header">
        <a href="/calendar/" class="back-btn">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
        </a>
        <h1 class="page-title">Create Event</h1>
    </div>

    <div class="form-container">
        <form id="create-event-form" class="form-card">
            <div class="form-group">
                <label class="form-label" for="title">Event Title *</label>
                <input type="text" id="title" name="title" class="form-input" required maxlength="200" placeholder="Enter event title">
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Description</label>
                <textarea id="description" name="description" class="form-textarea" rows="4" maxlength="2000" placeholder="Add event details..."></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="start_date">Start Date *</label>
                    <input type="date" id="start_date" name="start_date" class="form-input" value="<?= e($prefillDate) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="start_time">Start Time *</label>
                    <input type="time" id="start_time" name="start_time" class="form-input" value="<?= e($prefillTime) ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date" class="form-input" value="<?= e($prefillDate) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="end_time">End Time</label>
                    <input type="time" id="end_time" name="end_time" class="form-input" value="10:00">
                </div>
            </div>

            <div class="form-check">
                <input type="checkbox" id="all_day" name="all_day">
                <label for="all_day">All day event</label>
            </div>

            <div class="form-group">
                <label class="form-label" for="location">Location</label>
                <input type="text" id="location" name="location" class="form-input" maxlength="500" placeholder="Enter location or online link">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="event_type">Event Type *</label>
                    <select id="event_type" name="event_type" class="form-select" required>
                        <option value="personal">Personal Event</option>
                        <?php if ($isAdmin): ?>
                            <option value="congregation">Congregation Event</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="category">Category</label>
                    <select id="category" name="category" class="form-select">
                        <option value="general">General</option>
                        <option value="service">Church Service</option>
                        <option value="prayer">Prayer Meeting</option>
                        <option value="bible_study">Bible Study</option>
                        <option value="homecell">Homecell</option>
                        <option value="youth">Youth</option>
                        <option value="outreach">Outreach</option>
                        <option value="meeting">Meeting</option>
                        <option value="social">Social Event</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Color</label>
                <div class="color-options">
                    <label class="color-option">
                        <input type="radio" name="color" value="#7C3AED" checked>
                        <span class="color-swatch" style="background: #7C3AED;"></span>
                    </label>
                    <label class="color-option">
                        <input type="radio" name="color" value="#22D3EE">
                        <span class="color-swatch" style="background: #22D3EE;"></span>
                    </label>
                    <label class="color-option">
                        <input type="radio" name="color" value="#10B981">
                        <span class="color-swatch" style="background: #10B981;"></span>
                    </label>
                    <label class="color-option">
                        <input type="radio" name="color" value="#F59E0B">
                        <span class="color-swatch" style="background: #F59E0B;"></span>
                    </label>
                    <label class="color-option">
                        <input type="radio" name="color" value="#EF4444">
                        <span class="color-swatch" style="background: #EF4444;"></span>
                    </label>
                    <label class="color-option">
                        <input type="radio" name="color" value="#EC4899">
                        <span class="color-swatch" style="background: #EC4899;"></span>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Reminder</label>
                <div class="reminder-options">
                    <label class="reminder-option">
                        <input type="checkbox" name="reminders[]" value="15">
                        <span>15 minutes before</span>
                    </label>
                    <label class="reminder-option">
                        <input type="checkbox" name="reminders[]" value="60" checked>
                        <span>1 hour before</span>
                    </label>
                    <label class="reminder-option">
                        <input type="checkbox" name="reminders[]" value="1440">
                        <span>1 day before</span>
                    </label>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" id="recurrence-group">
                    <label class="form-label" for="recurrence">Repeat</label>
                    <select id="recurrence" name="recurrence" class="form-select">
                        <option value="none">Does not repeat</option>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                </div>
                <div class="form-group" id="recurrence-end-group" style="display: none;">
                    <label class="form-label" for="recurrence_end">Repeat Until</label>
                    <input type="date" id="recurrence_end" name="recurrence_end" class="form-input">
                </div>
            </div>

            <div class="form-actions">
                <a href="/calendar/" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Create Event</button>
            </div>
        </form>
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
        <a href="/calendar/" class="active">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            Calendar
        </a>
        <a href="/profile/">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Profile
        </a>
    </nav>

    <script src="/calendar/js/calendar.js"></script>
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
        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);

        // Show recurrence end when repeat selected
        document.getElementById('recurrence').addEventListener('change', function() {
            document.getElementById('recurrence-end-group').style.display =
                this.value !== 'none' ? 'block' : 'none';
        });
    </script>
</body>
</html>
