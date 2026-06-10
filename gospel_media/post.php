<?php
/**
 * CRC Gospel Media - Single Post View
 * Shareable / deep-linkable view for an individual post (used by the Share
 * button, home dashboard post links and comment/reaction notifications).
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
$postId = (int)($_GET['id'] ?? 0);

// Fetch the post with author + engagement context (single row, no N+1).
$post = null;
if ($postId) {
    try {
        $post = Database::fetchOne(
            "SELECT p.*,
                    u.name as author_name, u.avatar as author_avatar,
                    c.name as congregation_name,
                    (SELECT COUNT(*) FROM reactions WHERE reactable_type = 'post' AND reactable_id = p.id) as reaction_count,
                    (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND status = 'active') as comment_count,
                    (SELECT reaction_type FROM reactions WHERE reactable_type = 'post' AND reactable_id = p.id AND user_id = ?) as user_reaction
             FROM posts p
             JOIN users u ON p.user_id = u.id
             LEFT JOIN congregations c ON p.congregation_id = c.id
             WHERE p.id = ? AND p.status = 'active'",
            [Auth::id(), $postId]
        );
    } catch (Exception $e) {}
}

// Access control: global posts are visible to everyone; otherwise the viewer
// must own it, be an admin, or belong to the post's congregation/group.
$canView = false;
if ($post) {
    if ($post['scope'] === 'global' || $post['user_id'] == Auth::id() || Auth::isAdmin()) {
        $canView = true;
    } elseif (!empty($post['congregation_id'])) {
        $canView = (bool) Database::fetchColumn(
            "SELECT 1 FROM user_congregations WHERE user_id = ? AND congregation_id = ? AND status = 'active'",
            [Auth::id(), $post['congregation_id']]
        );
    }
    if (!$canView && !empty($post['group_id'])) {
        $canView = (bool) Database::fetchColumn(
            "SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ? AND status = 'active'",
            [$post['group_id'], Auth::id()]
        );
    }
}
if (!$canView) {
    $post = null;
}

// Reaction breakdown for the icons row (single post → single query).
$reactionBreakdown = [];
if ($post && $post['reaction_count'] > 0) {
    try {
        foreach (Database::fetchAll(
            "SELECT reaction_type, COUNT(*) as count FROM reactions WHERE reactable_type = 'post' AND reactable_id = ? GROUP BY reaction_type ORDER BY count DESC",
            [$post['id']]
        ) ?: [] as $r) {
            $reactionBreakdown[$r['reaction_type']] = (int)$r['count'];
        }
    } catch (Exception $e) {}
}

$pageTitle = $post
    ? (mb_strimwidth($post['author_name'] . ': ' . trim($post['content']), 0, 60, '…') . ' - CRC')
    : 'Post - CRC';

$unreadNotifications = 0;
try {
    $unreadNotifications = Database::fetchColumn(
        "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL",
        [$user['id']]
    ) ?: 0;
} catch (Exception $e) {}

// Same content formatter as the feed.
function formatPostContent($content) {
    $text = e($content);
    $text = preg_replace('/(https?:\/\/[^\s<]+)/', '<a href="$1" class="post-link" target="_blank" rel="noopener noreferrer">$1</a>', $text);
    $text = preg_replace('/#(\w+)/u', '<span class="hashtag">#$1</span>', $text);
    $text = preg_replace('/@(\w+)/u', '<span class="mention">@$1</span>', $text);
    return nl2br($text);
}

$reactionIcons = [
    'like' => '<svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/></svg>',
    'love' => '<svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
    'pray' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M12 22s-8-4-8-10V5l8-3 8 3v7c0 6-8 10-8 10z"/></svg>',
    'amen' => '<svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>',
];
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
        .single-post-wrap { max-width: 680px; margin: 0 auto; }
        .back-bar { display: flex; align-items: center; gap: 10px; padding: 8px 2px 12px; }
        .back-bar a {
            display: inline-flex; align-items: center; gap: 8px;
            color: var(--muted); text-decoration: none; font-size: 13px; font-weight: 600;
            padding: 8px 12px; border: 1px solid var(--line); border-radius: 12px; background: var(--card2);
            transition: border-color 0.2s ease, transform 0.2s ease;
        }
        .back-bar a:hover { border-color: var(--accent); transform: translateY(-1px); }
        .back-bar a svg { width: 16px; height: 16px; }
        /* On a dedicated post page the comments are always shown. */
        .single-post-wrap .comments-section { border-top: 1px solid var(--line); }
    </style>
</head>
<body data-theme="dark">
    <!-- Top Bar / Navigation (matching Home page exactly) -->
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
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="notification-badge"><?= $unreadNotifications > 9 ? '9+' : $unreadNotifications ?></span>
                    <?php endif; ?>
                </a>

                <div class="user-menu">
                    <button class="user-menu-btn" onclick="toggleUserMenu()">
                        <?php if ($user['avatar']): ?>
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
                        <a href="/gospel_media/" class="user-dropdown-item">Feed</a>
                        <div class="user-dropdown-divider"></div>
                        <a href="/auth/logout.php" class="user-dropdown-item logout">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <main class="feed-container">
        <div class="single-post-wrap">
            <div class="back-bar">
                <a href="/gospel_media/">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    Back to Feed
                </a>
            </div>

            <?php if (!$post): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                    </div>
                    <h3>Post not available</h3>
                    <p>This post may have been removed, or you don't have access to it.</p>
                    <a href="/gospel_media/" class="btn-primary">Back to Feed</a>
                </div>
            <?php else: ?>
                <div class="posts-feed">
                    <article class="post-card" data-post-id="<?= $post['id'] ?>">
                        <div class="post-header">
                            <div class="post-author">
                                <?php if ($post['author_avatar']): ?>
                                    <img src="<?= e($post['author_avatar']) ?>" alt="" class="author-avatar" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                    <div class="author-avatar-placeholder" style="display:none;"><?= strtoupper(substr($post['author_name'], 0, 1)) ?></div>
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
                                        <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5v6l1 1 1-1v-6h5v-2l-2-2z"/></svg>
                                    </span>
                                <?php endif; ?>
                                <?php if (Auth::isAdmin() || $post['user_id'] == Auth::id()): ?>
                                    <div class="post-options">
                                        <button class="post-options-btn" onclick="togglePostMenu(<?= $post['id'] ?>)">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"></circle><circle cx="12" cy="5" r="1"></circle><circle cx="12" cy="19" r="1"></circle></svg>
                                        </button>
                                        <div class="post-options-menu" id="postMenu-<?= $post['id'] ?>">
                                            <?php if (Auth::isAdmin()): ?>
                                            <button class="post-option" onclick="togglePin(<?= $post['id'] ?>, <?= $post['is_pinned'] ? 'true' : 'false' ?>)">
                                                <svg viewBox="0 0 24 24" fill="<?= $post['is_pinned'] ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5v6l1 1 1-1v-6h5v-2l-2-2z"/></svg>
                                                <?= $post['is_pinned'] ? 'Unpin' : 'Pin' ?>
                                            </button>
                                            <?php endif; ?>
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
                            <?= formatPostContent($post['content']) ?>
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
                                        <span class="stat reaction-summary" data-post-id="<?= $post['id'] ?>">
                                            <span class="reaction-icons-row">
                                                <?php
                                                $shown = 0;
                                                foreach ($reactionBreakdown as $rType => $rCount):
                                                    if ($shown >= 3) break;
                                                    $colors = ['like' => '#3B82F6', 'love' => '#EF4444', 'pray' => '#8B5CF6', 'amen' => '#F59E0B'];
                                                    $color = $colors[$rType] ?? '#7C3AED';
                                                ?>
                                                    <span class="reaction-mini" style="color: <?= $color ?>"><?= $reactionIcons[$rType] ?? '' ?></span>
                                                <?php $shown++; endforeach; ?>
                                            </span>
                                            <?= $post['reaction_count'] ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($post['comment_count'] > 0): ?>
                                        <span class="stat comment-stat"><?= $post['comment_count'] ?> comment<?= $post['comment_count'] != 1 ? 's' : '' ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="post-actions">
                            <?php
                            $userReactionType = $post['user_reaction'] ?: '';
                            $reactionLabels = ['like' => 'Like', 'love' => 'Love', 'pray' => 'Pray', 'amen' => 'Amen'];
                            $reactionColors = ['like' => '#3B82F6', 'love' => '#EF4444', 'pray' => '#8B5CF6', 'amen' => '#F59E0B'];
                            $activeLabel = $reactionLabels[$userReactionType] ?? 'Like';
                            $activeColor = $reactionColors[$userReactionType] ?? '';
                            ?>
                            <div class="reaction-btn-wrap">
                                <button class="post-action <?= $userReactionType ? 'reacted reaction-' . e($userReactionType) : '' ?>"
                                        data-reaction="<?= e($userReactionType) ?>"
                                        onclick="toggleReaction(<?= $post['id'] ?>, '<?= e($userReactionType ?: 'like') ?>')"
                                        <?= $activeColor ? 'style="color:' . $activeColor . '"' : '' ?>>
                                    <?php if ($userReactionType && isset($reactionIcons[$userReactionType])): ?>
                                        <?= $reactionIcons[$userReactionType] ?>
                                    <?php else: ?>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path></svg>
                                    <?php endif; ?>
                                    <span><?= $activeLabel ?></span>
                                </button>
                                <div class="reaction-picker" id="reactionPicker-<?= $post['id'] ?>">
                                    <button class="reaction-option" data-type="like" onclick="selectReaction(<?= $post['id'] ?>, 'like')" title="Like"><span class="reaction-emoji">&#128077;</span></button>
                                    <button class="reaction-option" data-type="love" onclick="selectReaction(<?= $post['id'] ?>, 'love')" title="Love"><span class="reaction-emoji">&#10084;&#65039;</span></button>
                                    <button class="reaction-option" data-type="pray" onclick="selectReaction(<?= $post['id'] ?>, 'pray')" title="Pray"><span class="reaction-emoji">&#128591;</span></button>
                                    <button class="reaction-option" data-type="amen" onclick="selectReaction(<?= $post['id'] ?>, 'amen')" title="Amen"><span class="reaction-emoji">&#11088;</span></button>
                                </div>
                            </div>
                            <button class="post-action" onclick="toggleComments(<?= $post['id'] ?>)">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                                <span>Comment</span>
                            </button>
                            <button class="post-action" onclick="sharePost(<?= $post['id'] ?>)">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
                                <span>Share</span>
                            </button>
                        </div>

                        <div class="comments-section" id="comments-<?= $post['id'] ?>" style="display: none;">
                            <div class="comments-list"></div>
                            <form class="comment-form" onsubmit="submitComment(event, <?= $post['id'] ?>)">
                                <?php if ($user['avatar']): ?>
                                    <img src="<?= e($user['avatar']) ?>" alt="" class="comment-avatar" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                    <div class="comment-avatar-placeholder" style="display:none;"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
                                <?php else: ?>
                                    <div class="comment-avatar-placeholder"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
                                <?php endif; ?>
                                <input type="text" placeholder="Write a comment..." class="comment-input" required>
                                <button type="submit" class="comment-submit">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                                </button>
                            </form>
                        </div>
                    </article>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Bottom Navigation (Mobile) -->
    <nav class="bottom-nav">
        <a href="/home/" class="bottom-nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
            <span>Home</span>
        </a>
        <a href="/gospel_media/" class="bottom-nav-item active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 11a9 9 0 0 1 9 9"></path><path d="M4 4a16 16 0 0 1 16 16"></path><circle cx="5" cy="19" r="1"></circle></svg>
            <span>Feed</span>
        </a>
        <a href="/gospel_media/create.php" class="bottom-nav-item create-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        </a>
        <a href="/calendar/" class="bottom-nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            <span>Events</span>
        </a>
        <a href="/profile/" class="bottom-nav-item">
            <?php if ($user['avatar']): ?>
                <img src="<?= e($user['avatar']) ?>" alt="" class="bottom-nav-avatar" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <div class="bottom-nav-avatar-placeholder" style="display:none;"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
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
        function toggleTheme() {
            const html = document.documentElement;
            const next = (html.getAttribute('data-theme') || 'dark') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            document.body.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
        }
        function toggleUserMenu() {
            document.getElementById('userDropdown').classList.toggle('show');
        }
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.user-menu')) {
                document.getElementById('userDropdown')?.classList.remove('show');
            }
        });
        document.querySelectorAll('[data-ripple]').forEach(btn => {
            btn.addEventListener('click', function(e) {
                const rect = this.getBoundingClientRect();
                const ripple = document.createElement('span');
                ripple.className = 'ripple';
                ripple.style.left = (e.clientX - rect.left) + 'px';
                ripple.style.top = (e.clientY - rect.top) + 'px';
                this.appendChild(ripple);
                setTimeout(() => ripple.remove(), 600);
            });
        });
        <?php if ($post): ?>
        // Comments are always expanded on the single-post page.
        document.addEventListener('DOMContentLoaded', function() {
            toggleComments(<?= $post['id'] ?>);
        });
        <?php endif; ?>
    </script>
</body>
</html>
