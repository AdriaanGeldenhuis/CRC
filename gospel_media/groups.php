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

$pageTitle = 'Groups';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $_COOKIE['theme'] ?? 'dark' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?= CSRF::token() ?>">
    <title><?= e($pageTitle) ?> - CRC Gospel Media</title>
    <link rel="stylesheet" href="/gospel_media/css/gospel_media.css">
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
            background: var(--primary);
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100;
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
<body class="gospel-media-body">
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="feed-container" style="padding-top: calc(var(--navbar-height) + 1rem);">
        <!-- Filter Tabs -->
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

    <!-- Bottom Navigation (Mobile) -->
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
        <a href="/gospel_media/groups.php" class="bottom-nav-item active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <span>Groups</span>
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
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
            <span>Profile</span>
        </a>
    </nav>

    <div id="toast" class="toast"></div>

    <script src="/gospel_media/js/gospel_media.js"></script>
    <script>
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

        function openCreateGroupModal() {
            // TODO: Implement create group modal
            showToast('Create group coming soon!');
        }
    </script>
</body>
</html>
