<?php
/**
 * CRC Gospel Media Feed - Dashboard Layout
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$primaryCong = Auth::primaryCongregation();
if (!$primaryCong) {
    Response::redirect('/onboarding/');
}

$user = Auth::user();
$pageTitle = 'Feed - CRC';

// Get recent posts
$recentPosts = [];
try {
    $recentPosts = Database::fetchAll(
        "SELECT p.*, u.name as author_name, u.avatar as author_avatar,
                (SELECT COUNT(*) FROM reactions WHERE reactable_type = 'post' AND reactable_id = p.id) as reaction_count,
                (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND status = 'active') as comment_count
         FROM posts p
         JOIN users u ON p.user_id = u.id
         WHERE (p.scope = 'global' OR p.congregation_id = ?)
           AND p.status = 'active' AND p.group_id IS NULL
         ORDER BY p.is_pinned DESC, p.created_at DESC LIMIT 5",
        [$primaryCong['id']]
    ) ?: [];
} catch (Exception $e) {}

$postCount = count($recentPosts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/home/css/home.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <section class="welcome-section">
                <div class="welcome-content">
                    <h1>Feed</h1>
                    <p>Stay connected with your community</p>
                </div>
            </section>

            <div class="dashboard-grid">
                <!-- Create Post Card (Featured) -->
                <div class="dashboard-card morning-watch-card">
                    <div class="card-header">
                        <h2>Share Something</h2>
                    </div>
                    <div class="morning-watch-preview">
                        <h3>What's on your heart?</h3>
                        <p class="scripture-ref">Share a testimony, scripture, or encouragement</p>
                        <a href="/gospel_media/create.php" class="btn btn-primary">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Create Post
                        </a>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-card quick-actions-card">
                    <h2>Explore</h2>
                    <div class="quick-actions-grid">
                        <a href="/gospel_media/all.php?scope=global" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="2" y1="12" x2="22" y2="12"></line>
                                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                                </svg>
                            </div>
                            <span>Global</span>
                        </a>
                        <a href="/gospel_media/all.php?scope=congregation" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                </svg>
                            </div>
                            <span>Local</span>
                        </a>
                        <a href="/gospel_media/groups.php" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                            </div>
                            <span>Groups</span>
                        </a>
                        <a href="/gospel_media/sell.php" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="9" cy="21" r="1"></circle>
                                    <circle cx="20" cy="21" r="1"></circle>
                                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                                </svg>
                            </div>
                            <span>Market</span>
                        </a>
                    </div>
                </div>

                <!-- Post Types -->
                <div class="dashboard-card events-card">
                    <div class="card-header">
                        <h2>Create</h2>
                    </div>
                    <div class="events-list">
                        <a href="/gospel_media/create.php?type=text" class="event-item">
                            <div class="event-date">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                </svg>
                            </div>
                            <div class="event-info">
                                <h4>Text Post</h4>
                                <p>Share your thoughts</p>
                            </div>
                        </a>
                        <a href="/gospel_media/create.php?type=image" class="event-item">
                            <div class="event-date">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                    <polyline points="21 15 16 10 5 21"></polyline>
                                </svg>
                            </div>
                            <div class="event-info">
                                <h4>Photo</h4>
                                <p>Share an image</p>
                            </div>
                        </a>
                        <a href="/gospel_media/create.php?type=scripture" class="event-item">
                            <div class="event-date">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                                </svg>
                            </div>
                            <div class="event-info">
                                <h4>Scripture</h4>
                                <p>Share a verse</p>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Recent Posts -->
                <div class="dashboard-card posts-card">
                    <div class="card-header">
                        <h2>Recent Posts</h2>
                        <a href="/gospel_media/all.php" class="view-all-link">View All</a>
                    </div>
                    <?php if ($recentPosts): ?>
                        <div class="posts-list">
                            <?php foreach ($recentPosts as $post): ?>
                                <a href="/gospel_media/post.php?id=<?= $post['id'] ?>" class="post-item">
                                    <div class="post-author">
                                        <?php if ($post['author_avatar']): ?>
                                            <img src="<?= e($post['author_avatar']) ?>" alt="" class="author-avatar">
                                        <?php else: ?>
                                            <div class="author-avatar-placeholder"><?= strtoupper(substr($post['author_name'], 0, 1)) ?></div>
                                        <?php endif; ?>
                                        <span><?= e($post['author_name']) ?></span>
                                        <span class="post-time"><?= time_ago($post['created_at']) ?></span>
                                    </div>
                                    <p class="post-content"><?= e(truncate(strip_tags($post['content']), 100)) ?></p>
                                    <div class="post-stats">
                                        <span><?= $post['reaction_count'] ?> reactions</span>
                                        <span><?= $post['comment_count'] ?> comments</span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-content">
                            <p>No posts yet. Be the first to share!</p>
                            <a href="/gospel_media/create.php" class="btn btn-outline">Create Post</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
