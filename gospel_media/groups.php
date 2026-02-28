<?php
/**
 * CRC Gospel Media - Groups
 * List and manage groups
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();

// Get filter
$filter = $_GET['filter'] ?? 'all'; // all, my, community, sell

// Build query
$params = [];
$conditions = ["g.status = 'active'"];

if ($filter === 'my') {
    $conditions[] = "gm.user_id = ?";
    $params[] = Auth::id();
} elseif ($filter === 'community') {
    $conditions[] = "g.group_type = 'community'";
} elseif ($filter === 'sell') {
    $conditions[] = "g.group_type = 'sell'";
}

// Only show global groups and user's congregation groups
if ($primaryCong) {
    $conditions[] = "(g.scope = 'global' OR g.congregation_id = ?)";
    $params[] = $primaryCong['id'];
} else {
    $conditions[] = "g.scope = 'global'";
}

// Privacy enforcement: only show private groups if user is a member (unless admin)
if (!Auth::isAdmin()) {
    $conditions[] = "(g.privacy = 'public' OR EXISTS (SELECT 1 FROM group_members gm2 WHERE gm2.group_id = g.id AND gm2.user_id = ? AND gm2.status = 'active'))";
    $params[] = Auth::id();
}

$where = implode(' AND ', $conditions);

$joinClause = $filter === 'my'
    ? "JOIN group_members gm ON g.id = gm.group_id AND gm.status = 'active'"
    : "LEFT JOIN group_members gm ON g.id = gm.group_id AND gm.user_id = " . Auth::id() . " AND gm.status = 'active'";

$groups = Database::fetchAll(
    "SELECT g.*,
            u.name as creator_name,
            c.name as congregation_name,
            (SELECT COUNT(*) FROM group_members WHERE group_id = g.id AND status = 'active') as member_count,
            (SELECT COUNT(*) FROM posts WHERE group_id = g.id AND status = 'active') as post_count,
            gm.role as user_role,
            gm.status as user_status
     FROM `groups` g
     {$joinClause}
     JOIN users u ON g.created_by = u.id
     LEFT JOIN congregations c ON g.congregation_id = c.id
     WHERE {$where}
     GROUP BY g.id
     ORDER BY g.created_at DESC",
    $params
);

$pageTitle = 'Groups - CRC';

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
    <?= CSRF::meta() ?>
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="/home/css/home.css?v=<?= filemtime(__DIR__ . '/../home/css/home.css') ?>">
    <link rel="stylesheet" href="/gospel_media/css/gospel_media.css?v=<?= filemtime(__DIR__ . '/css/gospel_media.css') ?>">
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
        .groups-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            padding: 1rem;
        }

        .group-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .group-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .group-cover {
            height: 120px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .group-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .group-cover-icon {
            width: 48px;
            height: 48px;
            color: white;
            opacity: 0.8;
        }

        .group-body {
            padding: 1rem;
        }

        .group-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .group-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .group-type-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .group-type-badge.community {
            background: rgba(59, 130, 246, 0.15);
            color: #3B82F6;
        }

        .group-type-badge.sell {
            background: rgba(34, 197, 94, 0.15);
            color: #22C55E;
        }

        .group-description {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.75rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .group-stats {
            display: flex;
            gap: 1rem;
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 0.75rem;
        }

        .group-stat {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .group-stat svg {
            width: 14px;
            height: 14px;
        }

        .group-actions {
            display: flex;
            gap: 0.5rem;
        }

        .group-btn {
            flex: 1;
            padding: 0.6rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
        }

        .group-btn svg {
            width: 16px;
            height: 16px;
        }

        .group-btn-primary {
            background: var(--primary);
            color: white;
            border: none;
        }

        .group-btn-primary:hover {
            background: var(--primary-dark);
        }

        .group-btn-secondary {
            background: transparent;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .group-btn-secondary:hover {
            background: var(--hover-bg);
        }

        .group-btn-joined {
            background: rgba(34, 197, 94, 0.15);
            color: #22C55E;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            padding: 1rem;
            overflow-x: auto;
            border-bottom: 1px solid var(--border-color);
        }

        .filter-tab {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-secondary);
            background: transparent;
            border: 1px solid var(--border-color);
            cursor: pointer;
            white-space: nowrap;
            text-decoration: none;
            transition: all 0.2s;
        }

        .filter-tab:hover {
            background: var(--hover-bg);
        }

        .filter-tab.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .empty-groups {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-muted);
        }

        .empty-groups svg {
            width: 64px;
            height: 64px;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-groups h3 {
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .create-group-btn {
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
        }

        .create-group-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(124, 58, 237, 0.5);
        }

        .create-group-btn svg {
            width: 24px;
            height: 24px;
        }

        @media (max-width: 640px) {
            .groups-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body data-theme="dark">
    <!-- Top Bar / Navigation (matching Feed page exactly) -->
    <div class="topbar">
        <div class="inner">
            <div class="brand">
                <div class="logo" aria-hidden="true"></div>
                <div>
                    <h1>CRC</h1>
                    <span><?= e($primaryCong['name'] ?? 'Gospel Media') ?></span>
                </div>
            </div>

            <div class="actions">
                <!-- Status Chip (hidden on mobile) -->
                <div class="chip" title="Status">
                    <span class="dot"></span>
                    <?= e(explode(' ', $user['name'])[0]) ?>
                </div>

                <!-- Theme Toggle -->
                <button class="theme-toggle" onclick="toggleTheme()" title="Toggle theme" data-ripple>
                    <svg class="sun-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 3v2m0 14v2M3 12h2m14 0h2M5.2 5.2l1.4 1.4m10.8 10.8l1.4 1.4M18.8 5.2l-1.4 1.4M6.6 17.4l-1.4 1.4"></path>
                        <circle cx="12" cy="12" r="5"></circle>
                    </svg>
                    <svg class="moon-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                    </svg>
                </button>

                <!-- Notifications -->
                <a href="/notifications/" class="nav-icon-btn" title="Notifications" data-ripple>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="notification-badge"><?= $unreadNotifications > 9 ? '9+' : $unreadNotifications ?></span>
                    <?php endif; ?>
                </a>

                <!-- 3-dot More Menu -->
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

                <!-- User Profile Menu -->
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
        <a href="/gospel_media/" class="feed-tab">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 11a9 9 0 0 1 9 9"></path>
                <path d="M4 4a16 16 0 0 1 16 16"></path>
                <circle cx="5" cy="19" r="1"></circle>
            </svg>
            <span>Feed</span>
        </a>
        <a href="/gospel_media/groups.php" class="feed-tab active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <span>Groups</span>
        </a>
        <a href="/calendar/" class="feed-tab">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <span>Events</span>
        </a>
    </nav>

    <main class="feed-container">
        <!-- Groups Filter Tabs -->
        <div class="filter-tabs">
            <a href="?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">All Groups</a>
            <a href="?filter=my" class="filter-tab <?= $filter === 'my' ? 'active' : '' ?>">My Groups</a>
            <a href="?filter=community" class="filter-tab <?= $filter === 'community' ? 'active' : '' ?>">Community</a>
            <a href="?filter=sell" class="filter-tab <?= $filter === 'sell' ? 'active' : '' ?>">Marketplace</a>
        </div>

        <!-- Groups Grid -->
        <?php if (empty($groups)): ?>
            <div class="empty-groups">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <h3>No groups found</h3>
                <p>
                    <?php if ($filter === 'my'): ?>
                        You haven't joined any groups yet.
                    <?php else: ?>
                        No groups available. Be the first to create one!
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="groups-grid">
                <?php foreach ($groups as $group): ?>
                    <article class="group-card">
                        <div class="group-cover">
                            <?php if ($group['cover_image']): ?>
                                <img src="<?= e($group['cover_image']) ?>" alt="">
                            <?php else: ?>
                                <svg class="group-cover-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <?php if ($group['group_type'] === 'sell'): ?>
                                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                                        <line x1="3" y1="6" x2="21" y2="6"></line>
                                        <path d="M16 10a4 4 0 0 1-8 0"></path>
                                    <?php else: ?>
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                    <?php endif; ?>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="group-body">
                            <div class="group-header">
                                <h3 class="group-name"><?= e($group['name']) ?></h3>
                                <span class="group-type-badge <?= $group['group_type'] ?>">
                                    <?= $group['group_type'] === 'sell' ? 'Marketplace' : 'Community' ?>
                                </span>
                            </div>
                            <?php if ($group['description']): ?>
                                <p class="group-description"><?= e($group['description']) ?></p>
                            <?php endif; ?>
                            <div class="group-stats">
                                <span class="group-stat">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                    </svg>
                                    <?= number_format($group['member_count']) ?> members
                                </span>
                                <span class="group-stat">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                    </svg>
                                    <?= number_format($group['post_count']) ?> posts
                                </span>
                            </div>
                            <div class="group-actions">
                                <?php if ($group['user_role']): ?>
                                    <a href="/gospel_media/group.php?id=<?= $group['id'] ?>" class="group-btn group-btn-joined">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="20 6 9 17 4 12"></polyline>
                                        </svg>
                                        Joined
                                    </a>
                                    <a href="/gospel_media/group.php?id=<?= $group['id'] ?>" class="group-btn group-btn-secondary">
                                        View
                                    </a>
                                <?php else: ?>
                                    <button class="group-btn group-btn-primary" onclick="joinGroup(<?= $group['id'] ?>)">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="8.5" cy="7" r="4"></circle>
                                            <line x1="20" y1="8" x2="20" y2="14"></line>
                                            <line x1="23" y1="11" x2="17" y2="11"></line>
                                        </svg>
                                        Join
                                    </button>
                                    <a href="/gospel_media/group.php?id=<?= $group['id'] ?>" class="group-btn group-btn-secondary">
                                        View
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Create Group FAB -->
    <?php if (Auth::isAdmin()): ?>
    <button class="create-group-btn" onclick="openCreateGroupModal()" title="Create Group">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
    </button>
    <?php endif; ?>

    <div id="toast" class="toast"></div>

    <!-- Create Group Modal -->
    <div class="modal-overlay" id="createGroupModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create New Group</h2>
                <button class="modal-close" onclick="closeCreateGroupModal()">&times;</button>
            </div>
            <form id="createGroupForm" onsubmit="submitCreateGroup(event)">
                <div class="form-group">
                    <label for="groupName">Group Name *</label>
                    <input type="text" id="groupName" name="name" required maxlength="255"
                           placeholder="Enter group name" class="form-input">
                </div>
                <div class="form-group">
                    <label for="groupDescription">Description</label>
                    <textarea id="groupDescription" name="description" rows="3"
                              placeholder="What is this group about?" class="form-input"></textarea>
                </div>
                <div class="form-group">
                    <label for="groupType">Group Type *</label>
                    <select id="groupType" name="group_type" class="form-input">
                        <option value="community">Community</option>
                        <option value="sell">Marketplace</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="groupPrivacy">Privacy *</label>
                    <select id="groupPrivacy" name="privacy" class="form-input">
                        <option value="public">Public - Anyone can join</option>
                        <option value="private">Private - Approval required</option>
                    </select>
                </div>
                <?php if (Auth::isAdmin()): ?>
                <div class="form-group">
                    <label for="groupScope">Scope</label>
                    <select id="groupScope" name="scope" class="form-input">
                        <option value="congregation"><?= e($primaryCong['name'] ?? 'My Congregation') ?></option>
                        <option value="global">Global (All Congregations)</option>
                    </select>
                </div>
                <?php endif; ?>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeCreateGroupModal()">Cancel</button>
                    <button type="submit" class="btn-primary" id="createGroupBtn">Create Group</button>
                </div>
            </form>
        </div>
    </div>

    <script src="/gospel_media/js/gospel_media.js"></script>
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

        function getCSRFToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        }

        async function joinGroup(groupId) {
            try {
                const response = await fetch('/gospel_media/api/groups.php?action=join', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': getCSRFToken()
                    },
                    body: JSON.stringify({ group_id: groupId })
                });

                const data = await response.json();

                if (data.ok) {
                    showToast('Joined group!');
                    setTimeout(() => location.reload(), 500);
                } else {
                    showToast(data.error || 'Failed to join group', 'error');
                }
            } catch (error) {
                showToast('Network error', 'error');
            }
        }

        async function leaveGroup(groupId) {
            if (!confirm('Are you sure you want to leave this group?')) return;

            try {
                const response = await fetch('/gospel_media/api/groups.php?action=leave', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': getCSRFToken()
                    },
                    body: JSON.stringify({ group_id: groupId })
                });

                const data = await response.json();

                if (data.ok) {
                    showToast('Left group');
                    setTimeout(() => location.reload(), 500);
                } else {
                    showToast(data.error || 'Failed to leave group', 'error');
                }
            } catch (error) {
                showToast('Network error', 'error');
            }
        }

        function openCreateGroupModal() {
            document.getElementById('createGroupModal').classList.add('show');
            setTimeout(() => document.getElementById('groupName').focus(), 100);
        }

        function closeCreateGroupModal() {
            document.getElementById('createGroupModal').classList.remove('show');
            document.getElementById('createGroupForm').reset();
        }

        async function submitCreateGroup(e) {
            e.preventDefault();

            const btn = document.getElementById('createGroupBtn');
            const form = e.target;

            const name = document.getElementById('groupName').value.trim();
            const description = document.getElementById('groupDescription').value.trim();
            const groupType = document.getElementById('groupType').value;
            const privacy = document.getElementById('groupPrivacy').value;
            const scope = document.getElementById('groupScope')?.value || 'congregation';

            if (!name) {
                showToast('Group name is required', 'error');
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Creating...';

            try {
                const response = await fetch('/gospel_media/api/groups.php?action=create', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': getCSRFToken()
                    },
                    body: JSON.stringify({
                        name: name,
                        description: description,
                        group_type: groupType,
                        privacy: privacy,
                        scope: scope
                    })
                });

                const data = await response.json();

                if (data.ok) {
                    showToast('Group created!');
                    closeCreateGroupModal();
                    setTimeout(() => location.reload(), 500);
                } else {
                    showToast(data.error || 'Failed to create group', 'error');
                }
            } catch (error) {
                showToast('Network error', 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Create Group';
            }
        }

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeCreateGroupModal();
            }
        });

        // Close modal when clicking outside
        document.getElementById('createGroupModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCreateGroupModal();
            }
        });
    </script>
</body>
</html>
