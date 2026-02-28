<?php
/**
 * CRC Media Hub - Main Page
 * Revamped to match app design system
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
$pageTitle = "Media - CRC";

// Initialize defaults
$activeLivestream = null;
$upcomingStreams = [];
$latestSermons = [];
$series = [];
$categories = [];
$congId = $primaryCong ? $primaryCong['id'] : 0;

// Get active livestream
if ($primaryCong) {
    try {
        $activeLivestream = Database::fetchOne(
            "SELECT * FROM livestreams WHERE congregation_id = ? AND status = 'live' ORDER BY started_at DESC LIMIT 1",
            [$primaryCong['id']]
        );
    } catch (Exception $e) {}
}

// Get upcoming scheduled livestreams
if ($primaryCong) {
    try {
        $upcomingStreams = Database::fetchAll(
            "SELECT * FROM livestreams
             WHERE congregation_id = ? AND status = 'scheduled' AND scheduled_at > NOW()
             ORDER BY scheduled_at ASC LIMIT 3",
            [$primaryCong['id']]
        ) ?: [];
    } catch (Exception $e) {}
}

// Get latest sermons (congregation + global)
try {
    $latestSermons = Database::fetchAll(
        "SELECT s.*, u.name as speaker_name, c.name as congregation_name
         FROM sermons s
         LEFT JOIN users u ON s.speaker_user_id = u.id
         LEFT JOIN congregations c ON s.congregation_id = c.id
         WHERE s.status = 'published' AND (s.congregation_id = ? OR s.congregation_id IS NULL)
         ORDER BY s.sermon_date DESC LIMIT 6",
        [$congId]
    ) ?: [];
} catch (Exception $e) {}

// Get sermon series
try {
    $series = Database::fetchAll(
        "SELECT ss.*,
                (SELECT COUNT(*) FROM sermons WHERE series_id = ss.id AND status = 'published') as sermon_count
         FROM sermon_series ss
         WHERE ss.congregation_id = ? OR ss.congregation_id IS NULL
         ORDER BY ss.created_at DESC LIMIT 4",
        [$congId]
    ) ?: [];
} catch (Exception $e) {}

// Get categories
try {
    $categories = Database::fetchAll(
        "SELECT DISTINCT category FROM sermons
         WHERE status = 'published' AND category IS NOT NULL AND category != ''
         ORDER BY category ASC"
    ) ?: [];
} catch (Exception $e) {}

// Get notification count
$unreadNotifications = 0;
try {
    $unreadNotifications = Database::fetchColumn(
        "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL",
        [$user['id']]
    ) ?: 0;
} catch (Exception $e) {}

function formatDuration($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;

    if ($hours > 0) {
        return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
    }
    return sprintf('%d:%02d', $minutes, $secs);
}
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        (function() {
            const saved = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', saved);
        })();
    </script>
    <style>
        /* Media-specific styles matching app design */
        .media-page {
            padding-bottom: 100px;
        }

        .page-header {
            padding: 1.5rem;
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

        /* Live Banner */
        .live-banner {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 0 1rem 1rem;
            padding: 1.25rem;
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(124, 58, 237, 0.2));
            border: 1px solid rgba(239, 68, 68, 0.4);
            border-radius: 16px;
            text-decoration: none;
            transition: all 0.2s ease;
            animation: pulse-border 2s ease-in-out infinite;
        }

        @keyframes pulse-border {
            0%, 100% { border-color: rgba(239, 68, 68, 0.4); }
            50% { border-color: rgba(239, 68, 68, 0.8); }
        }

        .live-banner:hover {
            transform: translateY(-2px);
        }

        .live-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: #EF4444;
            border-radius: 20px;
            color: white;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.5px;
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

        .live-info {
            flex: 1;
        }

        .live-info h2 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text);
        }

        .live-info p {
            margin: 0.25rem 0 0;
            font-size: 0.85rem;
            color: var(--muted);
        }

        .live-arrow {
            color: #EF4444;
            font-weight: 600;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
            padding: 0 1rem 1.5rem;
        }

        .action-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            padding: 1.25rem 0.75rem;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .action-card:hover {
            background: var(--card2);
            border-color: rgba(124, 58, 237, 0.3);
            transform: translateY(-2px);
        }

        .action-icon {
            font-size: 1.75rem;
        }

        .action-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text);
            text-align: center;
        }

        /* Section */
        .section {
            padding: 0 1rem 1.5rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text);
            margin: 0;
        }

        .view-all {
            color: #7C3AED;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
        }

        /* Sermons Grid */
        .sermons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
        }

        .sermon-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            overflow: hidden;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .sermon-card:hover {
            border-color: rgba(124, 58, 237, 0.3);
            transform: translateY(-2px);
        }

        .sermon-thumb {
            position: relative;
            aspect-ratio: 16/9;
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.2), rgba(34, 211, 238, 0.1));
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sermon-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .thumb-placeholder {
            font-size: 3rem;
        }

        .duration {
            position: absolute;
            bottom: 0.5rem;
            right: 0.5rem;
            padding: 0.25rem 0.5rem;
            background: rgba(0, 0, 0, 0.8);
            border-radius: 4px;
            font-size: 0.75rem;
            color: white;
            font-weight: 500;
        }

        .sermon-info {
            padding: 1rem;
        }

        .sermon-info h3 {
            margin: 0 0 0.5rem;
            font-size: 1rem;
            font-weight: 700;
            color: var(--text);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .sermon-speaker {
            margin: 0;
            font-size: 0.85rem;
            color: #7C3AED;
            font-weight: 500;
        }

        .sermon-date {
            margin: 0.25rem 0 0;
            font-size: 0.8rem;
            color: var(--muted);
        }

        /* Upcoming Grid */
        .upcoming-grid {
            display: grid;
            gap: 0.75rem;
        }

        .upcoming-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 12px;
        }

        .upcoming-date {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0.75rem;
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.15), rgba(34, 211, 238, 0.1));
            border-radius: 10px;
            min-width: 55px;
        }

        .upcoming-date .day {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1;
        }

        .upcoming-date .month {
            font-size: 0.7rem;
            font-weight: 600;
            color: #7C3AED;
            text-transform: uppercase;
        }

        .upcoming-info {
            flex: 1;
        }

        .upcoming-info h3 {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text);
        }

        .upcoming-info p {
            margin: 0.25rem 0 0;
            font-size: 0.8rem;
            color: var(--muted);
        }

        .btn-outline {
            padding: 0.5rem 1rem;
            background: transparent;
            border: 1px solid var(--line);
            border-radius: 8px;
            color: var(--text);
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-outline:hover {
            border-color: #7C3AED;
            color: #7C3AED;
        }

        /* Series Grid */
        .series-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
        }

        .series-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 12px;
            overflow: hidden;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .series-card:hover {
            border-color: rgba(124, 58, 237, 0.3);
            transform: translateY(-2px);
        }

        .series-cover {
            aspect-ratio: 1;
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.3), rgba(34, 211, 238, 0.2));
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .series-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .cover-placeholder {
            font-size: 2rem;
            font-weight: 800;
            color: white;
            text-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }

        .series-info {
            padding: 0.75rem;
        }

        .series-info h3 {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text);
        }

        .series-info p {
            margin: 0.25rem 0 0;
            font-size: 0.75rem;
            color: var(--muted);
        }

        /* Categories */
        .categories-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .category-tag {
            padding: 0.5rem 1rem;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 20px;
            color: var(--text);
            font-size: 0.85rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .category-tag:hover {
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.15), rgba(34, 211, 238, 0.1));
            border-color: #7C3AED;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
        }

        .empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            margin: 0 0 0.5rem;
            font-size: 1.1rem;
            color: var(--text);
        }

        .empty-state p {
            margin: 0;
            color: var(--muted);
            font-size: 0.9rem;
        }

        @media (max-width: 640px) {
            .quick-actions {
                grid-template-columns: repeat(3, 1fr);
            }

            .live-banner {
                flex-direction: column;
                text-align: center;
            }

            .live-info {
                text-align: center;
            }

            .sermons-grid {
                grid-template-columns: 1fr;
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
                    <span><?= e($primaryCong['name'] ?? 'Media') ?></span>
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
        <a href="/media/" class="feed-tab active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polygon points="23 7 16 12 23 17 23 7"></polygon>
                <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
            </svg>
            <span>Media</span>
        </a>
        <a href="/media/sermons.php" class="feed-tab">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path>
                <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                <line x1="12" y1="19" x2="12" y2="23"></line>
                <line x1="8" y1="23" x2="16" y2="23"></line>
            </svg>
            <span>Sermons</span>
        </a>
        <a href="/media/livestream.php" class="feed-tab">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="2"></circle>
                <path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"></path>
            </svg>
            <span>Live</span>
        </a>
    </nav>

    <main class="feed-container media-page">
        <!-- Live Now Banner -->
        <?php if ($activeLivestream): ?>
            <a href="/media/livestream.php?id=<?= $activeLivestream['id'] ?>" class="live-banner">
                <div class="live-indicator">
                    <span class="live-dot"></span>
                    LIVE NOW
                </div>
                <div class="live-info">
                    <h2><?= e($activeLivestream['title']) ?></h2>
                    <p>Join the live service now</p>
                </div>
                <div class="live-arrow">Watch →</div>
            </a>
        <?php endif; ?>

        <div class="page-header">
            <div class="page-title">
                <h1>Media</h1>
                <p>Sermons, livestreams, and more</p>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="/media/sermons.php" class="action-card">
                <span class="action-icon">🎤</span>
                <span class="action-label">All Sermons</span>
            </a>
            <a href="/media/livestream.php" class="action-card">
                <span class="action-icon">📺</span>
                <span class="action-label">Livestream</span>
            </a>
            <?php if ($series): ?>
                <a href="/media/sermons.php?view=series" class="action-card">
                    <span class="action-icon">📚</span>
                    <span class="action-label">Series</span>
                </a>
            <?php else: ?>
                <a href="/media/sermons.php?view=series" class="action-card">
                    <span class="action-icon">📚</span>
                    <span class="action-label">Series</span>
                </a>
            <?php endif; ?>
        </div>

        <!-- Upcoming Livestreams -->
        <?php if ($upcomingStreams): ?>
            <section class="section">
                <h2 class="section-title">Upcoming Livestreams</h2>
                <div class="upcoming-grid">
                    <?php foreach ($upcomingStreams as $stream): ?>
                        <div class="upcoming-card">
                            <div class="upcoming-date">
                                <span class="day"><?= date('d', strtotime($stream['scheduled_at'])) ?></span>
                                <span class="month"><?= date('M', strtotime($stream['scheduled_at'])) ?></span>
                            </div>
                            <div class="upcoming-info">
                                <h3><?= e($stream['title']) ?></h3>
                                <p><?= date('l, g:i A', strtotime($stream['scheduled_at'])) ?></p>
                            </div>
                            <button class="btn-outline" onclick="setReminder(<?= $stream['id'] ?>)">
                                Remind Me
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- Latest Sermons -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Latest Sermons</h2>
                <a href="/media/sermons.php" class="view-all">View All →</a>
            </div>
            <?php if ($latestSermons): ?>
                <div class="sermons-grid">
                    <?php foreach ($latestSermons as $sermon): ?>
                        <a href="/media/sermon.php?id=<?= $sermon['id'] ?>" class="sermon-card">
                            <div class="sermon-thumb">
                                <?php if ($sermon['thumbnail_url']): ?>
                                    <img src="<?= e($sermon['thumbnail_url']) ?>" alt="">
                                <?php else: ?>
                                    <div class="thumb-placeholder">🎤</div>
                                <?php endif; ?>
                                <?php if ($sermon['duration']): ?>
                                    <span class="duration"><?= formatDuration($sermon['duration']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="sermon-info">
                                <h3><?= e($sermon['title']) ?></h3>
                                <p class="sermon-speaker"><?= e($sermon['speaker_name'] ?? $sermon['speaker']) ?></p>
                                <p class="sermon-date"><?= date('M j, Y', strtotime($sermon['sermon_date'])) ?></p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">🎤</div>
                    <h3>No sermons yet</h3>
                    <p>Sermons will appear here once they are uploaded.</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Sermon Series -->
        <?php if ($series): ?>
            <section class="section">
                <div class="section-header">
                    <h2 class="section-title">Sermon Series</h2>
                    <a href="/media/sermons.php?view=series" class="view-all">View All →</a>
                </div>
                <div class="series-grid">
                    <?php foreach ($series as $s): ?>
                        <a href="/media/sermons.php?series=<?= $s['id'] ?>" class="series-card">
                            <div class="series-cover">
                                <?php if ($s['cover_url']): ?>
                                    <img src="<?= e($s['cover_url']) ?>" alt="">
                                <?php else: ?>
                                    <div class="cover-placeholder"><?= strtoupper(substr($s['name'], 0, 2)) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="series-info">
                                <h3><?= e($s['name']) ?></h3>
                                <p><?= $s['sermon_count'] ?> sermon<?= $s['sermon_count'] != 1 ? 's' : '' ?></p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- Browse by Category -->
        <?php if ($categories): ?>
            <section class="section">
                <h2 class="section-title">Browse by Topic</h2>
                <div class="categories-list">
                    <?php foreach ($categories as $cat): ?>
                        <a href="/media/sermons.php?category=<?= urlencode($cat['category']) ?>" class="category-tag">
                            <?= e($cat['category']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
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
        <a href="/media/" class="bottom-nav-item create-btn active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polygon points="23 7 16 12 23 17 23 7"></polygon>
                <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
            </svg>
        </a>
        <a href="/calendar/" class="bottom-nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <span>Events</span>
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

        function setReminder(streamId) {
            alert('Reminder set! You will be notified when the stream starts.');
        }
    </script>
</body>
</html>
