<?php
/**
 * CRC Gospel Media Feed
 * Social media style feed for the church community
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$primaryCong = Auth::primaryCongregation();
if (!$primaryCong) {
    Response::redirect('/onboarding/');
}

$user = Auth::user();
$pageTitle = 'Feed - CRC';

// Get scope filter
$scope = $_GET['scope'] ?? 'all';
$validScopes = ['all', 'global', 'congregation'];
if (!in_array($scope, $validScopes)) {
    $scope = 'all';
}

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = DEFAULT_PAGE_SIZE;
$offset = ($page - 1) * $perPage;

// Build query based on scope
$params = [$primaryCong['id']];
$scopeCondition = "(p.scope = 'global' OR p.congregation_id = ?)";

if ($scope === 'global') {
    $scopeCondition = "p.scope = 'global'";
    $params = [];
} elseif ($scope === 'congregation') {
    $scopeCondition = "p.congregation_id = ?";
}

// Initialize defaults
$posts = [];
$totalPosts = 0;

// Get posts
try {
    $posts = Database::fetchAll(
        "SELECT p.*,
                u.name as author_name, u.avatar as author_avatar,
                c.name as congregation_name,
                (SELECT COUNT(*) FROM reactions WHERE reactable_type = 'post' AND reactable_id = p.id) as reaction_count,
                (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND status = 'active') as comment_count,
                (SELECT reaction_type FROM reactions WHERE reactable_type = 'post' AND reactable_id = p.id AND user_id = ?) as user_reaction
         FROM posts p
         JOIN users u ON p.user_id = u.id
         LEFT JOIN congregations c ON p.congregation_id = c.id
         WHERE {$scopeCondition}
           AND p.status = 'active'
           AND p.group_id IS NULL
         ORDER BY p.is_pinned DESC, p.created_at DESC
         LIMIT ? OFFSET ?",
        array_merge([Auth::id()], $params, [$perPage, $offset])
    ) ?: [];
} catch (Exception $e) {}

// Get total for pagination
try {
    $totalParams = $scope === 'global' ? [] : [$primaryCong['id']];
    $totalPosts = Database::fetchColumn(
        "SELECT COUNT(*) FROM posts p
         WHERE {$scopeCondition}
           AND p.status = 'active'
           AND p.group_id IS NULL",
        $totalParams
    ) ?: 0;
} catch (Exception $e) {}

$totalPages = ceil($totalPosts / $perPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/gospel_media/css/gospel_media.css?v=<?= time() ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        (function() {
            const saved = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', saved);
        })();
    </script>
</head>
<body>
    <!-- Mobile Header -->
    <header class="mobile-header">
        <a href="/home/" class="header-logo">CRC</a>
        <div class="header-actions">
            <button class="header-btn" onclick="toggleTheme()" id="themeToggle">
                <svg class="sun-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
                <svg class="moon-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                </svg>
            </button>
            <a href="/notifications/" class="header-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
            </a>
            <button class="header-btn" onclick="toggleMobileMenu()" id="mobileMenuBtn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>
            <div class="mobile-menu" id="mobileMenu">
                <a href="/home/" class="mobile-menu-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                    Home
                </a>
                <a href="/gospel_media/" class="mobile-menu-item active">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 11a9 9 0 0 1 9 9"></path><path d="M4 4a16 16 0 0 1 16 16"></path><circle cx="5" cy="19" r="1"></circle></svg>
                    Feed
                </a>
                <a href="/calendar/" class="mobile-menu-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    Events
                </a>
                <a href="/gospel_media/groups.php" class="mobile-menu-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    Groups
                </a>
                <div class="mobile-menu-divider"></div>
                <a href="/profile/" class="mobile-menu-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    Profile
                </a>
                <?php if (Auth::isAdmin()): ?>
                <a href="/admin/" class="mobile-menu-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    Admin
                </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Feed Filter Tabs (Mobile) -->
    <nav class="feed-tabs">
        <a href="?scope=all" class="feed-tab <?= $scope === 'all' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7"></rect>
                <rect x="14" y="3" width="7" height="7"></rect>
                <rect x="14" y="14" width="7" height="7"></rect>
                <rect x="3" y="14" width="7" height="7"></rect>
            </svg>
            <span>All</span>
        </a>
        <a href="?scope=global" class="feed-tab <?= $scope === 'global' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="2" y1="12" x2="22" y2="12"></line>
                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
            </svg>
            <span>Global</span>
        </a>
        <a href="?scope=congregation" class="feed-tab <?= $scope === 'congregation' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            <span>Church</span>
        </a>
        <a href="/gospel_media/groups.php" class="feed-tab">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <span>Groups</span>
        </a>
    </nav>

    <!-- Desktop Navbar (shows on larger screens) -->
    <nav class="desktop-navbar">
        <div class="nav-container">
            <a href="/home/" class="nav-logo">CRC</a>
            <div class="nav-center">
                <a href="?scope=all" class="nav-tab <?= $scope === 'all' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                    All
                </a>
                <a href="?scope=global" class="nav-tab <?= $scope === 'global' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="2" y1="12" x2="22" y2="12"></line>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                    </svg>
                    Global
                </a>
                <a href="?scope=congregation" class="nav-tab <?= $scope === 'congregation' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    <?= e($primaryCong['name']) ?>
                </a>
            </div>
            <div class="nav-actions">
                <a href="/notifications/" class="nav-icon-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                </a>
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
                            <span><?= e($primaryCong['name']) ?></span>
                        </div>
                        <div class="user-dropdown-divider"></div>
                        <a href="/profile/" class="user-dropdown-item">Profile</a>
                        <a href="/home/" class="user-dropdown-item">Home</a>
                        <?php if (Auth::isCongregationAdmin($primaryCong['id'])): ?>
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
    </nav>

    <main class="feed-container">
        <!-- Create Post Card -->
        <div class="create-post-card">
            <div class="create-post-row">
                <?php if ($user['avatar']): ?>
                    <img src="<?= e($user['avatar']) ?>" alt="" class="create-avatar">
                <?php else: ?>
                    <div class="create-avatar-placeholder"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
                <?php endif; ?>
                <a class="create-post-input" href="/gospel_media/create.php">
                    What's on your heart?
                </a>
            </div>
            <div class="create-post-actions">
                <a href="/gospel_media/create.php" class="create-action">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                        <polyline points="21 15 16 10 5 21"></polyline>
                    </svg>
                    <span>Photo</span>
                </a>
                <a href="/gospel_media/create.php" class="create-action">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="23 7 16 12 23 17 23 7"></polygon>
                        <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
                    </svg>
                    <span>Video</span>
                </a>
                <a href="/gospel_media/create.php" class="create-action">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                    </svg>
                    <span>Scripture</span>
                </a>
            </div>
        </div>

        <!-- Posts Feed -->
        <div class="posts-feed">
            <?php if ($posts): ?>
                <?php foreach ($posts as $post): ?>
                    <article class="post-card" data-post-id="<?= $post['id'] ?>">
                        <div class="post-header">
                            <div class="post-author">
                                <?php if ($post['author_avatar']): ?>
                                    <img src="<?= e($post['author_avatar']) ?>" alt="" class="author-avatar">
                                <?php else: ?>
                                    <div class="author-avatar-placeholder"><?= strtoupper(substr($post['author_name'], 0, 1)) ?></div>
                                <?php endif; ?>
                                <div class="author-info">
                                    <strong><?= e($post['author_name']) ?></strong>
                                    <span class="post-meta">
                                        <?= time_ago($post['created_at']) ?>
                                        <?php if ($post['scope'] === 'global'): ?>
                                            <span class="scope-badge global">Global</span>
                                        <?php elseif ($post['congregation_name']): ?>
                                            <span class="scope-badge"><?= e($post['congregation_name']) ?></span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="post-header-right">
                                <?php if ($post['is_pinned']): ?>
                                    <span class="pinned-badge">
                                        <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14">
                                            <path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5v6l1 1 1-1v-6h5v-2l-2-2z"/>
                                        </svg>
                                    </span>
                                <?php endif; ?>
                                <?php if (Auth::isAdmin() || $post['user_id'] == Auth::id()): ?>
                                    <div class="post-options">
                                        <button class="post-options-btn" onclick="togglePostMenu(<?= $post['id'] ?>)">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="1"></circle>
                                                <circle cx="12" cy="5" r="1"></circle>
                                                <circle cx="12" cy="19" r="1"></circle>
                                            </svg>
                                        </button>
                                        <div class="post-options-menu" id="postMenu-<?= $post['id'] ?>">
                                            <button class="post-option" onclick="editPost(<?= $post['id'] ?>)">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                                Edit
                                            </button>
                                            <button class="post-option delete" onclick="deletePost(<?= $post['id'] ?>)">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="post-content">
                            <?= nl2br(e($post['content'])) ?>
                        </div>

                        <?php if ($post['media']): ?>
                            <?php $media = json_decode($post['media'], true); ?>
                            <?php if ($media): ?>
                                <div class="post-media <?= count($media) > 1 ? 'media-grid-' . min(count($media), 4) : '' ?>">
                                    <?php foreach (array_slice($media, 0, 4) as $item): ?>
                                        <?php if (strpos($item['type'] ?? '', 'image') !== false): ?>
                                            <img src="<?= e($item['url']) ?>" alt="" class="media-image" onclick="openImageViewer('<?= e($item['url']) ?>')">
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <div class="post-engagement">
                            <?php if ($post['reaction_count'] > 0 || $post['comment_count'] > 0): ?>
                                <div class="engagement-stats">
                                    <?php if ($post['reaction_count'] > 0): ?>
                                        <span class="stat">
                                            <span class="reaction-icons">
                                                <svg viewBox="0 0 24 24" fill="var(--danger)" width="16" height="16">
                                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                                </svg>
                                            </span>
                                            <?= $post['reaction_count'] ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($post['comment_count'] > 0): ?>
                                        <span class="stat"><?= $post['comment_count'] ?> comments</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="post-actions">
                            <button class="post-action <?= $post['user_reaction'] ? 'liked' : '' ?>" onclick="toggleReaction(<?= $post['id'] ?>)">
                                <svg viewBox="0 0 24 24" fill="<?= $post['user_reaction'] ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2">
                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                </svg>
                                <span>Like</span>
                            </button>
                            <button class="post-action" onclick="toggleComments(<?= $post['id'] ?>)">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                </svg>
                                <span>Comment</span>
                            </button>
                            <button class="post-action" onclick="sharePost(<?= $post['id'] ?>)">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="18" cy="5" r="3"></circle>
                                    <circle cx="6" cy="12" r="3"></circle>
                                    <circle cx="18" cy="19" r="3"></circle>
                                    <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                                    <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                                </svg>
                                <span>Share</span>
                            </button>
                        </div>

                        <!-- Comments Section -->
                        <div class="comments-section" id="comments-<?= $post['id'] ?>" style="display: none;">
                            <div class="comments-list"></div>
                            <form class="comment-form" onsubmit="submitComment(event, <?= $post['id'] ?>)">
                                <?php if ($user['avatar']): ?>
                                    <img src="<?= e($user['avatar']) ?>" alt="" class="comment-avatar">
                                <?php else: ?>
                                    <div class="comment-avatar-placeholder"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
                                <?php endif; ?>
                                <input type="text" placeholder="Write a comment..." class="comment-input" required>
                                <button type="submit" class="comment-submit">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="22" y1="2" x2="11" y2="13"></line>
                                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>

                <!-- Load More / Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="load-more-section">
                        <?php if ($page < $totalPages): ?>
                            <a href="?scope=<?= $scope ?>&page=<?= $page + 1 ?>" class="load-more-btn">
                                Load More Posts
                            </a>
                        <?php endif; ?>
                        <span class="page-info">Page <?= $page ?> of <?= $totalPages ?></span>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                    </div>
                    <h3>No posts yet</h3>
                    <p>Be the first to share something with the community!</p>
                    <a href="/gospel_media/create.php" class="btn-primary">Create Post</a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Bottom Navigation (Mobile) -->
    <nav class="bottom-nav">
        <a href="/home/" class="bottom-nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            <span>Home</span>
        </a>
        <a href="/gospel_media/" class="bottom-nav-item active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 11a9 9 0 0 1 9 9"></path>
                <path d="M4 4a16 16 0 0 1 16 16"></path>
                <circle cx="5" cy="19" r="1"></circle>
            </svg>
            <span>Feed</span>
        </a>
        <a href="/gospel_media/create.php" class="bottom-nav-item create-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
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

    <!-- Image Viewer -->
    <div class="image-viewer" id="imageViewer" onclick="closeImageViewer()">
        <button class="viewer-close">&times;</button>
        <img src="" alt="" id="viewerImage">
    </div>

    <div id="toast" class="toast"></div>

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

        // Mobile Menu
        function toggleMobileMenu() {
            document.getElementById('mobileMenu').classList.toggle('show');
        }

        // Desktop User Menu
        function toggleUserMenu() {
            document.getElementById('userDropdown').classList.toggle('show');
        }

        // Close menus when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.user-menu')) {
                document.getElementById('userDropdown')?.classList.remove('show');
            }
            if (!e.target.closest('#mobileMenuBtn') && !e.target.closest('#mobileMenu')) {
                document.getElementById('mobileMenu')?.classList.remove('show');
            }
        });

        function openImageViewer(src) {
            document.getElementById('viewerImage').src = src;
            document.getElementById('imageViewer').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeImageViewer() {
            document.getElementById('imageViewer').classList.remove('show');
            document.body.style.overflow = '';
        }

        // Post Options Menu
        function togglePostMenu(postId) {
            // Close all other menus first
            document.querySelectorAll('.post-options-menu.show').forEach(menu => {
                menu.classList.remove('show');
            });
            document.getElementById('postMenu-' + postId).classList.toggle('show');
        }

        // Close post menus when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.post-options')) {
                document.querySelectorAll('.post-options-menu.show').forEach(menu => {
                    menu.classList.remove('show');
                });
            }
        });

        // Edit Post - redirect to edit page
        function editPost(postId) {
            window.location.href = '/gospel_media/edit.php?id=' + postId;
        }

        // Delete Post
        async function deletePost(postId) {
            if (!confirm('Are you sure you want to delete this post? This cannot be undone.')) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('post_id', postId);
                formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.content);

                const response = await fetch('/gospel_media/api/posts.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    // Remove the post from DOM
                    document.querySelector('[data-post-id="' + postId + '"]').remove();
                    showToast('Post deleted successfully', 'success');
                } else {
                    showToast(data.error || 'Failed to delete post', 'error');
                }
            } catch (error) {
                showToast('Error deleting post', 'error');
            }
        }

        // Toast notification
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast show ' + type;
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }
    </script>
</body>
</html>
