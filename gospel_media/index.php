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

// Get trending/popular posts
$trendingPosts = [];
try {
    $trendingPosts = Database::fetchAll(
        "SELECT p.*, u.name as author_name,
                (SELECT COUNT(*) FROM reactions WHERE reactable_type = 'post' AND reactable_id = p.id) as reaction_count
         FROM posts p
         JOIN users u ON p.user_id = u.id
         WHERE (p.scope = 'global' OR p.congregation_id = ?)
           AND p.status = 'active' AND p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         ORDER BY reaction_count DESC LIMIT 3",
        [$primaryCong['id']]
    ) ?: [];
} catch (Exception $e) {}

// Get user's recent posts
$myPosts = [];
try {
    $myPosts = Database::fetchAll(
        "SELECT *, (SELECT COUNT(*) FROM reactions WHERE reactable_type = 'post' AND reactable_id = posts.id) as reaction_count
         FROM posts WHERE user_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT 3",
        [$user['id']]
    ) ?: [];
} catch (Exception $e) {}

// Get groups
$groups = [];
try {
    $groups = Database::fetchAll(
        "SELECT g.*, (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count
         FROM groups g
         WHERE g.congregation_id = ? AND g.status = 'active'
         ORDER BY member_count DESC LIMIT 4",
        [$primaryCong['id']]
    ) ?: [];
} catch (Exception $e) {}
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
    <style>
        .feed-card {
            background: linear-gradient(135deg, #EC4899 0%, #F472B6 100%);
            color: var(--white);
        }
        .feed-card .card-header h2 { color: var(--white); }
        .create-post-box {
            background: rgba(255,255,255,0.15);
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
        }
        .create-post-box textarea {
            width: 100%;
            background: rgba(255,255,255,0.9);
            border: none;
            border-radius: var(--radius);
            padding: 0.75rem;
            resize: none;
            font-family: inherit;
            margin-bottom: 0.5rem;
        }
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }
        .quick-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem;
            background: var(--gray-50);
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--gray-700);
            transition: var(--transition);
        }
        .quick-action:hover { background: var(--primary); color: white; }
        .quick-action-icon { font-size: 1.5rem; }
        .post-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .post-item {
            padding: 1rem;
            background: var(--gray-50);
            border-radius: var(--radius);
            text-decoration: none;
            transition: var(--transition);
        }
        .post-item:hover { background: var(--gray-100); }
        .post-author {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .author-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .author-name { font-weight: 500; color: var(--gray-800); }
        .post-time { font-size: 0.75rem; color: var(--gray-500); }
        .post-content { font-size: 0.875rem; color: var(--gray-600); margin-bottom: 0.5rem; }
        .post-stats { font-size: 0.75rem; color: var(--gray-400); }
        .group-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
        }
        .group-item {
            padding: 0.75rem;
            background: var(--gray-50);
            border-radius: var(--radius);
            text-decoration: none;
            text-align: center;
        }
        .group-item:hover { background: var(--gray-100); }
        .group-icon { font-size: 1.5rem; margin-bottom: 0.25rem; }
        .group-name { font-size: 0.75rem; color: var(--gray-700); font-weight: 500; }
        .group-members { font-size: 0.65rem; color: var(--gray-500); }
    </style>
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
                <!-- Create Post Card -->
                <div class="dashboard-card feed-card">
                    <div class="card-header">
                        <h2>Share Something</h2>
                    </div>
                    <div class="create-post-box">
                        <textarea placeholder="What's on your heart?" rows="2" onclick="window.location='/gospel_media/create.php'"></textarea>
                        <div style="display: flex; gap: 0.5rem;">
                            <a href="/gospel_media/create.php?type=image" class="btn" style="flex:1; background: rgba(255,255,255,0.9); color: #EC4899; font-size: 0.75rem;">📷 Photo</a>
                            <a href="/gospel_media/create.php?type=scripture" class="btn" style="flex:1; background: rgba(255,255,255,0.9); color: #EC4899; font-size: 0.75rem;">📖 Scripture</a>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-card">
                    <h2 style="margin-bottom: 1rem;">Explore</h2>
                    <div class="quick-actions-grid">
                        <a href="/gospel_media/?scope=global" class="quick-action">
                            <span class="quick-action-icon">🌍</span>
                            <span>Global</span>
                        </a>
                        <a href="/gospel_media/?scope=congregation" class="quick-action">
                            <span class="quick-action-icon">🏠</span>
                            <span>Local</span>
                        </a>
                        <a href="/gospel_media/groups.php" class="quick-action">
                            <span class="quick-action-icon">👥</span>
                            <span>Groups</span>
                        </a>
                        <a href="/gospel_media/sell.php" class="quick-action">
                            <span class="quick-action-icon">🛒</span>
                            <span>Market</span>
                        </a>
                    </div>
                </div>

                <!-- Recent Posts -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>Recent Posts</h2>
                        <a href="/gospel_media/all.php" class="view-all-link">View All</a>
                    </div>
                    <?php if ($recentPosts): ?>
                        <div class="post-list">
                            <?php foreach (array_slice($recentPosts, 0, 3) as $post): ?>
                                <a href="/gospel_media/post.php?id=<?= $post['id'] ?>" class="post-item">
                                    <div class="post-author">
                                        <div class="author-avatar"><?= strtoupper(substr($post['author_name'], 0, 1)) ?></div>
                                        <span class="author-name"><?= e($post['author_name']) ?></span>
                                        <span class="post-time"><?= time_ago($post['created_at']) ?></span>
                                    </div>
                                    <p class="post-content"><?= e(truncate(strip_tags($post['content']), 80)) ?></p>
                                    <div class="post-stats"><?= $post['reaction_count'] ?> reactions • <?= $post['comment_count'] ?> comments</div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 2rem; color: var(--gray-500);">
                            <p>No posts yet</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Groups -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>Groups</h2>
                        <a href="/gospel_media/groups.php" class="view-all-link">View All</a>
                    </div>
                    <?php if ($groups): ?>
                        <div class="group-grid">
                            <?php foreach ($groups as $group): ?>
                                <a href="/gospel_media/group.php?id=<?= $group['id'] ?>" class="group-item">
                                    <div class="group-icon">👥</div>
                                    <div class="group-name"><?= e(truncate($group['name'], 15)) ?></div>
                                    <div class="group-members"><?= $group['member_count'] ?> members</div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 1rem; color: var(--gray-500);">
                            <a href="/gospel_media/groups.php" class="btn btn-outline">Browse Groups</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
