<?php
/**
 * CRC Gospel Media Feed
 * Global and congregation posts
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
    <link rel="stylesheet" href="/gospel_media/css/gospel_media.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="feed-layout">
                <!-- Sidebar -->
                <aside class="feed-sidebar">
                    <div class="sidebar-section">
                        <h3>Feed</h3>
                        <nav class="sidebar-nav">
                            <a href="?scope=all" class="sidebar-link <?= $scope === 'all' ? 'active' : '' ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="7" height="7"></rect>
                                    <rect x="14" y="3" width="7" height="7"></rect>
                                    <rect x="14" y="14" width="7" height="7"></rect>
                                    <rect x="3" y="14" width="7" height="7"></rect>
                                </svg>
                                All Posts
                            </a>
                            <a href="?scope=global" class="sidebar-link <?= $scope === 'global' ? 'active' : '' ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="2" y1="12" x2="22" y2="12"></line>
                                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                                </svg>
                                Global
                            </a>
                            <a href="?scope=congregation" class="sidebar-link <?= $scope === 'congregation' ? 'active' : '' ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                </svg>
                                <?= e($primaryCong['name']) ?>
                            </a>
                        </nav>
                    </div>

                    <div class="sidebar-section">
                        <h3>Groups</h3>
                        <a href="/gospel_media/groups.php" class="sidebar-link">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                            Browse Groups
                        </a>
                        <a href="/gospel_media/sell.php" class="sidebar-link">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="9" cy="21" r="1"></circle>
                                <circle cx="20" cy="21" r="1"></circle>
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                            </svg>
                            Marketplace
                        </a>
                    </div>

                    <div class="sidebar-section">
                        <h3>Events</h3>
                        <a href="/calendar/" class="sidebar-link">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            View Calendar
                        </a>
                    </div>
                </aside>

                <!-- Main Feed -->
                <div class="feed-main">
                    <!-- Create Post -->
                    <div class="create-post-card">
                        <div class="create-post-header">
                            <?php if ($user['avatar']): ?>
                                <img src="<?= e($user['avatar']) ?>" alt="" class="user-avatar">
                            <?php else: ?>
                                <div class="user-avatar-placeholder"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
                            <?php endif; ?>
                            <button class="create-post-input" onclick="openPostModal()">
                                What's on your heart, <?= e(explode(' ', $user['name'])[0]) ?>?
                            </button>
                        </div>
                        <div class="create-post-actions">
                            <button class="create-action" onclick="openPostModal('image')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                    <polyline points="21 15 16 10 5 21"></polyline>
                                </svg>
                                Photo
                            </button>
                            <button class="create-action" onclick="openPostModal('video')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polygon points="23 7 16 12 23 17 23 7"></polygon>
                                    <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
                                </svg>
                                Video
                            </button>
                            <button class="create-action" onclick="openPostModal('scripture')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                                </svg>
                                Scripture
                            </button>
                        </div>
                    </div>

                    <!-- Posts -->
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
                                            <span>
                                                <?= time_ago($post['created_at']) ?>
                                                <?php if ($post['scope'] === 'congregation' && $post['congregation_name']): ?>
                                                    • <?= e($post['congregation_name']) ?>
                                                <?php elseif ($post['scope'] === 'global'): ?>
                                                    • Global
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php if ($post['is_pinned']): ?>
                                        <span class="pinned-badge">Pinned</span>
                                    <?php endif; ?>
                                </div>

                                <div class="post-content">
                                    <?= nl2br(e($post['content'])) ?>
                                </div>

                                <?php if ($post['media']): ?>
                                    <?php $media = json_decode($post['media'], true); ?>
                                    <?php if ($media): ?>
                                        <div class="post-media">
                                            <?php foreach ($media as $item): ?>
                                                <?php if (strpos($item['type'] ?? '', 'image') !== false): ?>
                                                    <img src="<?= e($item['url']) ?>" alt="" class="media-image">
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <div class="post-stats">
                                    <?php if ($post['reaction_count'] > 0): ?>
                                        <span class="stat"><?= $post['reaction_count'] ?> reactions</span>
                                    <?php endif; ?>
                                    <?php if ($post['comment_count'] > 0): ?>
                                        <span class="stat"><?= $post['comment_count'] ?> comments</span>
                                    <?php endif; ?>
                                </div>

                                <div class="post-actions">
                                    <button class="post-action <?= $post['user_reaction'] ? 'active' : '' ?>" onclick="toggleReaction(<?= $post['id'] ?>)">
                                        <svg viewBox="0 0 24 24" fill="<?= $post['user_reaction'] ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2">
                                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                        </svg>
                                        Like
                                    </button>
                                    <button class="post-action" onclick="toggleComments(<?= $post['id'] ?>)">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                        </svg>
                                        Comment
                                    </button>
                                    <button class="post-action" onclick="sharePost(<?= $post['id'] ?>)">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="18" cy="5" r="3"></circle>
                                            <circle cx="6" cy="12" r="3"></circle>
                                            <circle cx="18" cy="19" r="3"></circle>
                                            <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                                            <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                                        </svg>
                                        Share
                                    </button>
                                </div>

                                <!-- Comments Section (hidden by default) -->
                                <div class="comments-section" id="comments-<?= $post['id'] ?>" style="display: none;">
                                    <div class="comments-list"></div>
                                    <form class="comment-form" onsubmit="submitComment(event, <?= $post['id'] ?>)">
                                        <input type="text" placeholder="Write a comment..." class="comment-input" required>
                                        <button type="submit" class="btn btn-primary btn-sm">Post</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?scope=<?= $scope ?>&page=<?= $page - 1 ?>" class="pagination-link">Previous</a>
                                <?php endif; ?>
                                <span class="pagination-info">Page <?= $page ?> of <?= $totalPages ?></span>
                                <?php if ($page < $totalPages): ?>
                                    <a href="?scope=<?= $scope ?>&page=<?= $page + 1 ?>" class="pagination-link">Next</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-feed">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            </svg>
                            <h3>No posts yet</h3>
                            <p>Be the first to share something!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Post Modal -->
    <div class="modal" id="postModal">
        <div class="modal-overlay" onclick="closePostModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create Post</h2>
                <button class="modal-close" onclick="closePostModal()">&times;</button>
            </div>
            <form id="createPostForm" onsubmit="createPost(event)">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="postScope">Post to</label>
                        <select id="postScope" name="scope" required>
                            <option value="congregation"><?= e($primaryCong['name']) ?></option>
                            <?php if (Auth::isAdmin()): ?>
                                <option value="global">Global (All Congregations)</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <textarea id="postContent" name="content" placeholder="What's on your heart?" rows="4" required></textarea>
                    </div>
                    <div id="mediaPreview" class="media-preview"></div>
                    <div class="post-tools">
                        <label class="tool-btn">
                            <input type="file" id="postMedia" accept="image/*" multiple style="display:none" onchange="previewMedia(this)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <polyline points="21 15 16 10 5 21"></polyline>
                            </svg>
                            Add Photo
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closePostModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="postSubmitBtn">Post</button>
                </div>
            </form>
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <script src="/gospel_media/js/gospel_media.js"></script>
</body>
</html>
