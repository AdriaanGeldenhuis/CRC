<?php
/**
 * CRC Morning Study - Today's Session
 * Revamped to match app design system
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
$pageTitle = "Morning Study - CRC";

$today = date('Y-m-d');

// Initialize with defaults
$session = null;
$userEntry = null;
$streak = null;
$prayerPoints = [];
$studyQuestions = [];
$hasRecap = false;

// Get today's session (global or congregation-specific)
try {
    $session = Database::fetchOne(
        "SELECT ms.*, u.name as author_name
         FROM morning_sessions ms
         LEFT JOIN users u ON ms.created_by = u.id
         WHERE ms.session_date = ?
         AND (ms.scope = 'global' OR ms.congregation_id = ?)
         AND ms.published_at IS NOT NULL
         ORDER BY ms.scope = 'congregation' DESC
         LIMIT 1",
        [$today, $primaryCong['id'] ?? 0]
    );
} catch (Exception $e) {}

// Get user's entry for today
if ($session) {
    try {
        $userEntry = Database::fetchOne(
            "SELECT *, personal_notes as application, prayer_notes as prayer
             FROM morning_user_entries
             WHERE user_id = ? AND session_id = ?",
            [$user['id'], $session['id']]
        );
    } catch (Exception $e) {}

    // Check if recap exists
    try {
        $recap = Database::fetchOne(
            "SELECT id FROM morning_study_recaps WHERE session_id = ?",
            [$session['id']]
        );
        $hasRecap = $recap ? true : false;
    } catch (Exception $e) {}
}

// Get user's streak
try {
    $streak = Database::fetchOne(
        "SELECT current_streak, longest_streak, total_completions as total_entries
         FROM morning_streaks
         WHERE user_id = ?",
        [$user['id']]
    );
} catch (Exception $e) {}

// Parse prayer points and study questions
if ($session && !empty($session['prayer_points'])) {
    $prayerPoints = json_decode($session['prayer_points'], true) ?? [];
}
if ($session && !empty($session['study_questions'])) {
    $studyQuestions = json_decode($session['study_questions'], true) ?? [];
}

// Check if this is a live study session
$isStudyMode = $session && ($session['content_mode'] ?? 'watch') === 'study';
$isLive = $session && ($session['live_status'] ?? 'scheduled') === 'live';
$isEnded = $session && ($session['live_status'] ?? 'scheduled') === 'ended';
$hasStream = $session && ($session['stream_url'] || $session['replay_url']);

// Get notification count
$unreadNotifications = 0;
try {
    $unreadNotifications = Database::fetchColumn(
        "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL",
        [$user['id']]
    ) ?: 0;
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/home/css/home.css?v=<?= filemtime(__DIR__ . '/../home/css/home.css') ?>">
    <link rel="stylesheet" href="/gospel_media/css/gospel_media.css?v=<?= filemtime(__DIR__ . '/../gospel_media/css/gospel_media.css') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
    <script>
        (function() {
            const saved = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', saved);
        })();
    </script>
    <style>
        /* Morning Study specific styles */
        .morning-page {
            padding-bottom: 100px;
        }

        .page-header {
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-title h1 {
            font-size: 1.75rem;
            font-weight: 800;
            background: linear-gradient(135deg, #7C3AED, #22D3EE);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }

        .page-title p {
            color: var(--muted);
            font-size: 0.9rem;
            margin: 0.25rem 0 0;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .btn-outline {
            background: var(--card);
            border: 1px solid var(--line);
            color: var(--text);
        }

        .btn-outline:hover {
            border-color: #7C3AED;
            color: #7C3AED;
        }

        .btn-primary {
            background: linear-gradient(135deg, #7C3AED, #22D3EE);
            color: white;
            box-shadow: 0 4px 15px rgba(124, 58, 237, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(124, 58, 237, 0.4);
        }

        /* Streak Bar */
        .streak-bar {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
            padding: 0 1rem 1.5rem;
        }

        .streak-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1rem;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .streak-item::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #7C3AED, #22D3EE);
        }

        .streak-icon {
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }

        .streak-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text);
        }

        .streak-label {
            font-size: 0.7rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Live Study Card */
        .live-study-card {
            margin: 0 1rem 1.5rem;
            padding: 1.5rem;
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.2), rgba(34, 211, 238, 0.1));
            border: 1px solid rgba(124, 58, 237, 0.3);
            border-radius: 16px;
        }

        .live-study-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.375rem 0.75rem;
            background: #EF4444;
            border-radius: 20px;
            color: white;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .live-badge.ended {
            background: #6B7280;
        }

        .live-badge.scheduled {
            background: #F59E0B;
        }

        .live-dot {
            width: 8px;
            height: 8px;
            background: white;
            border-radius: 50%;
            animation: blink 1s ease-in-out infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }

        .live-study-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text);
            margin: 0 0 0.5rem;
        }

        .live-study-scripture {
            color: #7C3AED;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .live-study-key-verse {
            background: var(--card);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-top: 1rem;
            border-left: 3px solid #7C3AED;
            font-style: italic;
            color: var(--text);
        }

        .live-study-questions {
            margin-top: 1rem;
        }

        .live-study-questions h4 {
            font-size: 0.8rem;
            text-transform: uppercase;
            color: #7C3AED;
            margin: 0 0 0.5rem;
            letter-spacing: 0.05em;
        }

        .live-study-questions ul {
            margin: 0;
            padding-left: 1.25rem;
        }

        .live-study-questions li {
            margin-bottom: 0.375rem;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .live-study-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.25rem;
            flex-wrap: wrap;
        }

        /* Devotional Card */
        .devotional-card {
            margin: 0 1rem 1.5rem;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 1.5rem;
        }

        .devotional-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .devotional-badge {
            padding: 0.375rem 0.75rem;
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.2), rgba(34, 211, 238, 0.1));
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #7C3AED;
            text-transform: uppercase;
        }

        .devotional-theme {
            font-size: 0.85rem;
            color: var(--muted);
        }

        .devotional-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text);
            margin: 0 0 1rem;
        }

        .scripture-section {
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(34, 211, 238, 0.05));
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .scripture-ref {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            color: #7C3AED;
            margin-bottom: 0.75rem;
        }

        .scripture-ref svg {
            width: 18px;
            height: 18px;
        }

        .version-badge {
            margin-left: auto;
            padding: 0.2rem 0.5rem;
            background: rgba(124, 58, 237, 0.2);
            border-radius: 4px;
            font-size: 0.7rem;
        }

        .scripture-text {
            margin: 0;
            font-family: 'Merriweather', serif;
            font-size: 1rem;
            line-height: 1.7;
            color: var(--text);
            border-left: 3px solid #7C3AED;
            padding-left: 1rem;
        }

        .devotional-content h3 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text);
            margin: 0 0 0.75rem;
        }

        .content-text {
            color: var(--muted);
            line-height: 1.7;
        }

        .prayer-section {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--line);
        }

        .prayer-section h3 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text);
            margin: 0 0 0.75rem;
        }

        .prayer-list {
            margin: 0;
            padding-left: 1.25rem;
        }

        .prayer-list li {
            margin-bottom: 0.5rem;
            color: var(--muted);
        }

        .devotional-author {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--line);
            font-size: 0.85rem;
            color: var(--muted);
        }

        /* Entry Card */
        .entry-card {
            margin: 0 1rem 1.5rem;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 1.5rem;
        }

        .entry-card h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text);
            margin: 0 0 0.25rem;
        }

        .entry-subtitle {
            font-size: 0.85rem;
            color: var(--muted);
            margin: 0 0 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 0.5rem;
        }

        .form-group textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            background: var(--bg);
            border: 1px solid var(--line);
            border-radius: 10px;
            color: var(--text);
            font-size: 0.95rem;
            resize: vertical;
            font-family: inherit;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: #7C3AED;
        }

        .form-group textarea::placeholder {
            color: var(--muted);
        }

        .form-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 1rem;
        }

        .saved-indicator {
            font-size: 0.85rem;
            color: #10B981;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            margin: 0 1rem;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
        }

        .empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .empty-state h2 {
            margin: 0 0 0.5rem;
            font-size: 1.25rem;
            color: var(--text);
        }

        .empty-state p {
            margin: 0 0 1.5rem;
            color: var(--muted);
        }

        @media (max-width: 640px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .streak-bar {
                grid-template-columns: 1fr;
            }

            .streak-item {
                flex-direction: row;
                justify-content: flex-start;
                gap: 1rem;
                text-align: left;
            }
        }
    </style>
</head>
<body data-theme="dark">
    <!-- Top Bar / Navigation -->
    <div class="topbar">
        <div class="inner">
            <div class="brand">
                <div class="logo" aria-hidden="true"></div>
                <div>
                    <h1>CRC App</h1>
                    <span><?= e($primaryCong['name'] ?? 'Morning Study') ?></span>
                </div>
            </div>

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
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="notification-badge"><?= $unreadNotifications > 9 ? '9+' : $unreadNotifications ?></span>
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
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                <polyline points="9 22 9 12 15 12 15 22"></polyline>
                            </svg>
                            Home
                        </a>
                        <a href="/gospel_media/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 11a9 9 0 0 1 9 9"></path>
                                <path d="M4 4a16 16 0 0 1 16 16"></path>
                                <circle cx="5" cy="19" r="1"></circle>
                            </svg>
                            Feed
                        </a>
                        <a href="/bible/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                                <path d="M12 6v7"></path>
                                <path d="M8 9h8"></path>
                            </svg>
                            Bible
                        </a>
                        <a href="/ai_smartbible/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                <circle cx="19" cy="5" r="3" fill="currentColor"></circle>
                            </svg>
                            AI SmartBible
                        </a>
                        <a href="/morning_watch/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="5"></circle>
                                <line x1="12" y1="1" x2="12" y2="3"></line>
                                <line x1="12" y1="21" x2="12" y2="23"></line>
                                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                                <line x1="1" y1="12" x2="3" y2="12"></line>
                                <line x1="21" y1="12" x2="23" y2="12"></line>
                                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                            </svg>
                            Morning Study
                        </a>
                        <a href="/calendar/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            Calendar
                        </a>
                        <a href="/media/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="23 7 16 12 23 17 23 7"></polygon>
                                <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
                            </svg>
                            Media
                        </a>
                        <div class="more-dropdown-divider"></div>
                        <a href="/diary/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                            </svg>
                            My Diary
                        </a>
                        <a href="/homecells/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                            Homecells
                        </a>
                        <a href="/learning/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                                <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                            </svg>
                            Courses
                        </a>
                    </div>
                </div>

                <div class="user-menu">
                    <button class="user-menu-btn" onclick="toggleUserMenu()">
                        <?php if ($user['avatar']): ?>
                            <img src="<?= e($user['avatar']) ?>" alt="" class="user-avatar">
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

    <!-- Feed Filter Tabs -->
    <nav class="feed-tabs">
        <a href="/morning_watch/" class="feed-tab active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="5"></circle>
                <line x1="12" y1="1" x2="12" y2="3"></line>
                <line x1="12" y1="21" x2="12" y2="23"></line>
            </svg>
            <span>Today</span>
        </a>
        <a href="/morning_watch/archive.php" class="feed-tab">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="22 12 16 12 14 15 10 15 8 12 2 12"></polyline>
                <path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"></path>
            </svg>
            <span>Archive</span>
        </a>
    </nav>

    <main class="feed-container morning-page">
        <!-- Header -->
        <div class="page-header">
            <div class="page-title">
                <h1>Morning Study</h1>
                <p><?= date('l, F j, Y') ?></p>
            </div>
            <a href="/morning_watch/archive.php" class="btn btn-outline">View Archive</a>
        </div>

        <!-- Streak Bar -->
        <div class="streak-bar">
            <div class="streak-item">
                <span class="streak-icon">🔥</span>
                <span class="streak-value"><?= $streak['current_streak'] ?? 0 ?></span>
                <span class="streak-label">Day Streak</span>
            </div>
            <div class="streak-item">
                <span class="streak-icon">🏆</span>
                <span class="streak-value"><?= $streak['longest_streak'] ?? 0 ?></span>
                <span class="streak-label">Best Streak</span>
            </div>
            <div class="streak-item">
                <span class="streak-icon">📖</span>
                <span class="streak-value"><?= $streak['total_entries'] ?? 0 ?></span>
                <span class="streak-label">Total Days</span>
            </div>
        </div>

        <?php if ($session): ?>
            <?php if ($isStudyMode && $hasStream): ?>
                <!-- Live Study Card -->
                <div class="live-study-card">
                    <div class="live-study-header">
                        <?php if ($isLive): ?>
                            <span class="live-badge"><span class="live-dot"></span> Live Now</span>
                        <?php elseif ($isEnded): ?>
                            <span class="live-badge ended">Replay Available</span>
                        <?php else: ?>
                            <span class="live-badge scheduled">
                                <?= $session['live_starts_at'] ? 'Starts ' . date('g:i A', strtotime($session['live_starts_at'])) : 'Coming Soon' ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <h2 class="live-study-title"><?= e($session['title']) ?></h2>
                    <p class="live-study-scripture"><?= e($session['scripture_ref']) ?></p>

                    <?php if ($session['key_verse']): ?>
                        <div class="live-study-key-verse">
                            <strong>Key Verse:</strong> <?= e($session['key_verse']) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($studyQuestions): ?>
                        <div class="live-study-questions">
                            <h4>Today's Study Questions</h4>
                            <ul>
                                <?php foreach (array_slice($studyQuestions, 0, 5) as $q): ?>
                                    <li><?= e($q) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="live-study-actions">
                        <a href="/morning_watch/room.php?session_id=<?= $session['id'] ?>" class="btn btn-primary">
                            <?php if ($isLive): ?>
                                Join Live Room
                            <?php elseif ($isEnded): ?>
                                Watch Replay
                            <?php else: ?>
                                Enter Waiting Room
                            <?php endif; ?>
                        </a>
                        <?php if ($hasRecap): ?>
                            <a href="/morning_watch/recap.php?session_id=<?= $session['id'] ?>" class="btn btn-outline">
                                View Recap
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Today's Devotional -->
            <div class="devotional-card">
                <div class="devotional-header">
                    <span class="devotional-badge"><?= $isStudyMode ? 'Study' : ucfirst($session['scope']) ?></span>
                    <?php if ($session['theme']): ?>
                        <span class="devotional-theme"><?= e($session['theme']) ?></span>
                    <?php endif; ?>
                </div>

                <h2 class="devotional-title"><?= e($session['title']) ?></h2>

                <!-- Scripture -->
                <div class="scripture-section">
                    <div class="scripture-ref">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        <?= e($session['scripture_ref']) ?>
                        <span class="version-badge"><?= e($session['version_code'] ?? 'KJV') ?></span>
                    </div>
                    <?php if ($session['scripture_text']): ?>
                        <blockquote class="scripture-text">
                            <?= nl2br(e($session['scripture_text'])) ?>
                        </blockquote>
                    <?php endif; ?>
                </div>

                <!-- Devotional Content -->
                <?php if ($session['devotional']): ?>
                    <div class="devotional-content">
                        <h3>Today's Reflection</h3>
                        <div class="content-text">
                            <?= nl2br(e($session['devotional'])) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Prayer Points -->
                <?php if ($prayerPoints): ?>
                    <div class="prayer-section">
                        <h3>Prayer Points</h3>
                        <ul class="prayer-list">
                            <?php foreach ($prayerPoints as $point): ?>
                                <li><?= e($point) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($session['author_name']): ?>
                    <div class="devotional-author">
                        Written by <?= e($session['author_name']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- User Entry Form -->
            <div class="entry-card">
                <h3><?= $userEntry ? 'Your Private Notes' : 'My Notes (Private)' ?></h3>
                <p class="entry-subtitle">Record what God is speaking to you today</p>

                <form id="entry-form" data-session-id="<?= $session['id'] ?>">
                    <div class="form-group">
                        <label for="reflection">My Reflection</label>
                        <textarea id="reflection" name="reflection" rows="4"
                                  placeholder="What stood out to you from today's reading?"><?= e($userEntry['reflection'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="prayer">My Prayer Notes</label>
                        <textarea id="prayer" name="prayer" rows="3"
                                  placeholder="Write your prayer response..."><?= e($userEntry['prayer'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="application">Personal Application</label>
                        <textarea id="application" name="application" rows="2"
                                  placeholder="How will you apply this today?"><?= e($userEntry['application'] ?? '') ?></textarea>
                    </div>

                    <div class="form-actions">
                        <?php if ($userEntry): ?>
                            <span class="saved-indicator">✓ Saved at <?= date('g:i A', strtotime($userEntry['updated_at'])) ?></span>
                        <?php else: ?>
                            <span></span>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">
                            <?= $userEntry ? 'Update Notes' : 'Save Notes' ?>
                        </button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- No Session Today -->
            <div class="empty-state">
                <div class="empty-icon">☀️</div>
                <h2>No study session for today yet</h2>
                <p>Check back later or explore the archive for past sessions.</p>
                <a href="/morning_watch/archive.php" class="btn btn-primary">View Archive</a>
            </div>
        <?php endif; ?>
    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="/home/" class="bottom-nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            <span>Home</span>
        </a>
        <a href="/gospel_media/" class="bottom-nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 11a9 9 0 0 1 9 9"></path>
                <path d="M4 4a16 16 0 0 1 16 16"></path>
                <circle cx="5" cy="19" r="1"></circle>
            </svg>
            <span>Feed</span>
        </a>
        <a href="/morning_watch/" class="bottom-nav-item create-btn active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="5"></circle>
                <line x1="12" y1="1" x2="12" y2="3"></line>
                <line x1="12" y1="21" x2="12" y2="23"></line>
            </svg>
        </a>
        <a href="/bible/" class="bottom-nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
            </svg>
            <span>Bible</span>
        </a>
        <a href="/profile/" class="bottom-nav-item">
            <?php if ($user['avatar']): ?>
                <img src="<?= e($user['avatar']) ?>" alt="" class="bottom-nav-avatar">
            <?php else: ?>
                <div class="bottom-nav-avatar-placeholder"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
            <?php endif; ?>
            <span>Me</span>
        </a>
    </nav>

    <script>
        // Theme Toggle
        function toggleTheme() {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme') || 'dark';
            const next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
        }

        // More Menu Toggle
        function toggleMoreMenu() {
            document.getElementById('moreDropdown').classList.toggle('show');
            document.getElementById('userDropdown')?.classList.remove('show');
        }

        // User Menu Toggle
        function toggleUserMenu() {
            document.getElementById('userDropdown').classList.toggle('show');
            document.getElementById('moreDropdown')?.classList.remove('show');
        }

        // Close menus when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.user-menu')) {
                document.getElementById('userDropdown')?.classList.remove('show');
            }
            if (!e.target.closest('.more-menu')) {
                document.getElementById('moreDropdown')?.classList.remove('show');
            }
        });

        // Entry form handling
        document.getElementById('entry-form')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const form = e.target;
            const sessionId = form.dataset.sessionId;
            const btn = form.querySelector('button[type="submit"]');
            const originalText = btn.textContent;

            btn.disabled = true;
            btn.textContent = 'Saving...';

            try {
                const response = await fetch('/morning_watch/api/entry.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    body: JSON.stringify({
                        session_id: sessionId,
                        reflection: form.reflection.value,
                        prayer: form.prayer.value,
                        application: form.application.value
                    })
                });

                if (response.ok) {
                    btn.textContent = 'Saved!';
                    setTimeout(() => {
                        btn.textContent = 'Update Notes';
                        btn.disabled = false;
                    }, 2000);
                } else {
                    throw new Error('Failed to save');
                }
            } catch (err) {
                btn.textContent = originalText;
                btn.disabled = false;
                alert('Failed to save notes. Please try again.');
            }
        });
    </script>
</body>
</html>
