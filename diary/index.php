<?php
/**
 * CRC Diary - Main Page
 * Revamped to match app design system
 */

require_once __DIR__ . '/../core/bootstrap.php';

header('Content-Type: text/html; charset=UTF-8');

Auth::requireAuth();

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

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
$pageTitle = 'My Diary - CRC';

// Get filters from URL
$search = input('search');
$tag = input('tag');
$mood = input('mood');
$calYear = (int)($_GET['cal_year'] ?? date('Y'));
$calMonth = (int)($_GET['cal_month'] ?? date('n'));
$filterYear = (int)($_GET['year'] ?? 0);
$filterMonth = (int)($_GET['month'] ?? 0);

// Initialize variables
$entries = [];
$tags = [];
$totalEntries = 0;
$streak = 0;
$longestStreak = 0;
$archives = [];
$entryDates = [];
$moods = ['grateful', 'joyful', 'peaceful', 'hopeful', 'anxious', 'sad', 'angry', 'confused'];

// Get total entries count
try {
    $totalEntries = Database::fetchColumn(
        "SELECT COUNT(*) FROM diary_entries WHERE user_id = ?",
        [$user['id']]
    ) ?: 0;
} catch (Exception $e) {
    $totalEntries = 0;
}

// Get streak (days this week)
try {
    $streak = Database::fetchColumn(
        "SELECT COUNT(DISTINCT DATE(entry_date)) FROM diary_entries
         WHERE user_id = ? AND entry_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
        [$user['id']]
    ) ?: 0;
} catch (Exception $e) {
    $streak = 0;
}

// Get longest streak
try {
    $longestStreak = Database::fetchColumn(
        "SELECT COUNT(DISTINCT DATE(entry_date)) FROM diary_entries
         WHERE user_id = ? AND entry_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
        [$user['id']]
    ) ?: 0;
} catch (Exception $e) {
    $longestStreak = 0;
}

// Get entry dates for calendar highlighting
try {
    $entryDatesRaw = Database::fetchAll(
        "SELECT DATE(entry_date) as date FROM diary_entries
         WHERE user_id = ? AND YEAR(entry_date) = ? AND MONTH(entry_date) = ?",
        [$user['id'], $calYear, $calMonth]
    ) ?: [];
    foreach ($entryDatesRaw as $d) {
        $entryDates[] = $d['date'];
    }
} catch (Exception $e) {
    $entryDates = [];
}

// Get entries based on filters
try {
    $where = ['user_id = ?'];
    $params = [$user['id']];

    if ($search) {
        $where[] = "(title LIKE ? OR content LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    if ($mood) {
        $where[] = "mood = ?";
        $params[] = $mood;
    }

    if ($filterMonth && $filterYear) {
        $where[] = "YEAR(entry_date) = ? AND MONTH(entry_date) = ?";
        $params[] = $filterYear;
        $params[] = $filterMonth;
    } elseif ($filterYear) {
        $where[] = "YEAR(entry_date) = ?";
        $params[] = $filterYear;
    }

    $whereClause = implode(' AND ', $where);

    $entries = Database::fetchAll(
        "SELECT * FROM diary_entries WHERE $whereClause ORDER BY entry_date DESC, created_at DESC LIMIT 50",
        $params
    ) ?: [];
} catch (Exception $e) {
    $entries = [];
}

// Get tags if filtering by tag
if ($tag) {
    try {
        $tagData = Database::fetchOne(
            "SELECT * FROM diary_tags WHERE name = ? AND user_id = ?",
            [$tag, $user['id']]
        );
        if ($tagData) {
            $entries = Database::fetchAll(
                "SELECT e.* FROM diary_entries e
                 JOIN diary_entry_tags det ON e.id = det.entry_id
                 WHERE det.tag_id = ? AND e.user_id = ?
                 ORDER BY e.entry_date DESC",
                [$tagData['id'], $user['id']]
            ) ?: [];
        }
    } catch (Exception $e) {
        // Keep existing entries
    }
}

// Get user's tags for sidebar
try {
    $tags = Database::fetchAll(
        "SELECT DISTINCT t.name, COUNT(*) as count
         FROM diary_entry_tags det
         JOIN diary_tags t ON det.tag_id = t.id
         JOIN diary_entries e ON det.entry_id = e.id
         WHERE e.user_id = ?
         GROUP BY t.id, t.name
         ORDER BY count DESC
         LIMIT 15",
        [$user['id']]
    ) ?: [];
} catch (Exception $e) {
    $tags = [];
}

// Get archives for sidebar
try {
    $archives = Database::fetchAll(
        "SELECT YEAR(entry_date) as year, MONTH(entry_date) as month, COUNT(*) as count
         FROM diary_entries WHERE user_id = ?
         GROUP BY YEAR(entry_date), MONTH(entry_date)
         ORDER BY year DESC, month DESC
         LIMIT 12",
        [$user['id']]
    ) ?: [];
} catch (Exception $e) {
    $archives = [];
}

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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        (function() {
            const saved = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', saved);
        })();
    </script>
    <style>
        /* Diary-specific styles matching app design */
        .diary-page {
            padding-bottom: 100px;
        }

        .diary-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .diary-title h1 {
            font-size: 1.75rem;
            font-weight: 800;
            background: linear-gradient(135deg, #7C3AED, #22D3EE);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }

        .diary-title p {
            color: var(--muted);
            font-size: 0.9rem;
            margin: 0.25rem 0 0;
        }

        .diary-actions {
            display: flex;
            gap: 0.75rem;
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
            background: var(--card2);
            border-color: var(--accent);
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

        /* Stats Cards */
        .diary-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            padding: 0 1rem 1rem;
        }

        .stat-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 1.25rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #7C3AED, #22D3EE);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: rgba(124, 58, 237, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.75rem;
        }

        .stat-icon svg {
            width: 24px;
            height: 24px;
            color: #7C3AED;
        }

        .stat-icon.accent {
            background: rgba(34, 211, 238, 0.15);
        }

        .stat-icon.accent svg {
            color: #22D3EE;
        }

        .stat-icon.success {
            background: rgba(16, 185, 129, 0.15);
        }

        .stat-icon.success svg {
            color: #10B981;
        }

        .stat-value {
            display: block;
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--text);
        }

        .stat-label {
            display: block;
            font-size: 0.8rem;
            color: var(--muted);
            margin-top: 0.25rem;
        }

        /* Mood Filter Tabs */
        .mood-tabs {
            display: flex;
            gap: 0.5rem;
            padding: 0 1rem 1rem;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .mood-tab {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.6rem 1rem;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 20px;
            color: var(--muted);
            font-size: 0.85rem;
            font-weight: 500;
            text-decoration: none;
            white-space: nowrap;
            transition: all 0.2s ease;
        }

        .mood-tab:hover {
            background: var(--card2);
            color: var(--text);
        }

        .mood-tab.active {
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.2), rgba(34, 211, 238, 0.2));
            border-color: #7C3AED;
            color: var(--text);
        }

        /* Entries Grid */
        .entries-section {
            padding: 0 1rem;
        }

        .entries-grid {
            display: grid;
            gap: 1rem;
        }

        .entry-card {
            display: flex;
            gap: 1rem;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 1.25rem;
            text-decoration: none;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .entry-card::before {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(ellipse at center, rgba(124, 58, 237, 0.05), transparent 70%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .entry-card:hover::before {
            opacity: 1;
        }

        .entry-card:hover {
            border-color: rgba(124, 58, 237, 0.3);
            transform: translateY(-2px);
        }

        .entry-date {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-width: 60px;
            padding: 0.75rem;
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.15), rgba(34, 211, 238, 0.1));
            border-radius: 12px;
        }

        .entry-date .day {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1;
        }

        .entry-date .month {
            font-size: 0.75rem;
            font-weight: 600;
            color: #7C3AED;
            text-transform: uppercase;
        }

        .entry-date .year {
            font-size: 0.7rem;
            color: var(--muted);
        }

        .entry-content {
            flex: 1;
            min-width: 0;
        }

        .entry-content h3 {
            margin: 0 0 0.5rem;
            font-size: 1rem;
            font-weight: 700;
            color: var(--text);
        }

        .entry-content p {
            margin: 0 0 0.75rem;
            font-size: 0.9rem;
            color: var(--muted);
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .entry-footer {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .entry-mood {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.3rem 0.6rem;
            background: rgba(139, 92, 246, 0.15);
            border-radius: 20px;
            font-size: 0.75rem;
            color: var(--text);
        }

        .entry-scripture {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.75rem;
            color: var(--muted);
        }

        .entry-private {
            position: absolute;
            top: 1rem;
            right: 1rem;
            color: var(--muted);
        }

        /* Filter Banner */
        .filter-banner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            margin: 0 1rem 1rem;
            background: rgba(124, 58, 237, 0.1);
            border: 1px solid rgba(124, 58, 237, 0.2);
            border-radius: 12px;
            font-size: 0.9rem;
            color: var(--text);
        }

        .clear-filter {
            color: #7C3AED;
            text-decoration: none;
            font-weight: 600;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            margin: 0 1rem;
        }

        .empty-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.15), rgba(34, 211, 238, 0.1));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .empty-icon svg {
            width: 40px;
            height: 40px;
            color: #7C3AED;
        }

        .empty-state h3 {
            margin: 0 0 0.5rem;
            font-size: 1.25rem;
            color: var(--text);
        }

        .empty-state p {
            margin: 0 0 1.5rem;
            color: var(--muted);
        }

        /* FAB Button */
        .fab-btn {
            position: fixed;
            bottom: 80px;
            right: 1rem;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, #7C3AED, #22D3EE);
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(124, 58, 237, 0.4);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .fab-btn:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 6px 20px rgba(124, 58, 237, 0.5);
        }

        .fab-btn svg {
            width: 24px;
            height: 24px;
        }

        @media (max-width: 640px) {
            .diary-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .diary-stats {
                grid-template-columns: 1fr;
            }

            .stat-card {
                display: flex;
                align-items: center;
                gap: 1rem;
                text-align: left;
            }

            .stat-icon {
                margin: 0;
            }

            .entry-card {
                flex-direction: column;
            }

            .entry-date {
                flex-direction: row;
                gap: 0.5rem;
                width: fit-content;
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
                    <span><?= e($primaryCong['name'] ?? 'My Diary') ?></span>
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
        <a href="/diary/" class="feed-tab active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
            </svg>
            <span>Entries</span>
        </a>
        <a href="/diary/prayers.php" class="feed-tab">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
            </svg>
            <span>Prayers</span>
        </a>
    </nav>

    <main class="feed-container diary-page">
        <!-- Header -->
        <div class="diary-header">
            <div class="diary-title">
                <h1>My Diary</h1>
                <p>Your private space for reflection</p>
            </div>
            <div class="diary-actions">
                <a href="/diary/entry.php" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    New Entry
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="diary-stats">
            <div class="stat-card">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                </div>
                <div>
                    <span class="stat-value"><?= number_format($totalEntries) ?></span>
                    <span class="stat-label">Total Entries</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon accent">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
                <div>
                    <span class="stat-value"><?= $streak ?></span>
                    <span class="stat-label">This Week</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon success">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </div>
                <div>
                    <span class="stat-value"><?= $longestStreak ?></span>
                    <span class="stat-label">This Month</span>
                </div>
            </div>
        </div>

        <!-- Mood Filter Tabs -->
        <div class="mood-tabs">
            <a href="?" class="mood-tab <?= !$mood ? 'active' : '' ?>">All</a>
            <?php foreach ($moods as $m): ?>
                <a href="?mood=<?= $m ?>" class="mood-tab <?= $mood === $m ? 'active' : '' ?>">
                    <?= getMoodEmoji($m) ?> <?= ucfirst($m) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Filter Banner -->
        <?php if ($search || $tag || $mood || $filterMonth): ?>
            <div class="filter-banner">
                <span>
                    <?php if ($search): ?>
                        Searching: "<?= e($search) ?>"
                    <?php elseif ($tag): ?>
                        Tag: <?= e($tag) ?>
                    <?php elseif ($mood): ?>
                        Mood: <?= getMoodEmoji($mood) ?> <?= ucfirst($mood) ?>
                    <?php elseif ($filterMonth): ?>
                        <?= date('F Y', mktime(0, 0, 0, $filterMonth, 1, $filterYear)) ?>
                    <?php endif; ?>
                </span>
                <a href="/diary/" class="clear-filter">Clear</a>
            </div>
        <?php endif; ?>

        <!-- Entries -->
        <div class="entries-section">
            <?php if (empty($entries)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                    </div>
                    <h3>No entries yet</h3>
                    <p>Start writing to capture your thoughts and reflections</p>
                    <a href="/diary/entry.php" class="btn btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                        Write First Entry
                    </a>
                </div>
            <?php else: ?>
                <div class="entries-grid">
                    <?php foreach ($entries as $entry): ?>
                        <a href="/diary/entry.php?id=<?= $entry['id'] ?>" class="entry-card">
                            <div class="entry-date">
                                <span class="day"><?= date('d', strtotime($entry['entry_date'])) ?></span>
                                <span class="month"><?= date('M', strtotime($entry['entry_date'])) ?></span>
                                <span class="year"><?= date('Y', strtotime($entry['entry_date'])) ?></span>
                            </div>
                            <div class="entry-content">
                                <h3><?= e($entry['title'] ?: 'Untitled Entry') ?></h3>
                                <p><?= e(truncate(strip_tags($entry['content'] ?? ''), 120)) ?></p>
                                <div class="entry-footer">
                                    <?php if (!empty($entry['mood'])): ?>
                                        <span class="entry-mood">
                                            <?= getMoodEmoji($entry['mood']) ?> <?= ucfirst($entry['mood']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($entry['scripture_ref'])): ?>
                                        <span class="entry-scripture">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                                <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                            </svg>
                                            <?= e($entry['scripture_ref']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($entry['is_private']): ?>
                                <span class="entry-private" title="Private">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                    </svg>
                                </span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- FAB Button -->
    <a href="/diary/entry.php" class="fab-btn" title="New Entry">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
    </a>

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
        <a href="/diary/entry.php" class="bottom-nav-item create-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
        </a>
        <a href="/diary/" class="bottom-nav-item active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
            </svg>
            <span>Diary</span>
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
    </script>
</body>
</html>
