<?php
/**
 * CRC Home Dashboard
 * Main page after login
 */

require_once __DIR__ . '/../core/bootstrap.php';

// Require authentication
Auth::requireAuth();

// Check for primary congregation
$primaryCong = Auth::primaryCongregation();
if (!$primaryCong) {
    Response::redirect('/onboarding/');
}

$user = Auth::user();
$pageTitle = 'Home - CRC';

// Get today's morning watch (with error handling for missing tables)
$todaySession = null;
$completedToday = false;
$streak = null;
$upcomingEvents = [];
$recentPosts = [];
$unreadNotifications = 0;

try {
    $todaySession = Database::fetchOne(
        "SELECT * FROM morning_sessions
         WHERE (scope = 'global' OR congregation_id = ?)
           AND session_date = CURDATE()
           AND published_at IS NOT NULL
         ORDER BY scope = 'congregation' DESC
         LIMIT 1",
        [$primaryCong['id']]
    );
} catch (Exception $e) {}

// Check if user completed today's morning watch
if ($todaySession) {
    try {
        $entry = Database::fetchOne(
            "SELECT completed_at FROM morning_user_entries
             WHERE user_id = ? AND session_id = ?",
            [$user['id'], $todaySession['id']]
        );
        $completedToday = $entry && $entry['completed_at'];
    } catch (Exception $e) {}
}

// Get user's streak
try {
    $streak = Database::fetchOne(
        "SELECT current_streak, longest_streak FROM morning_streaks WHERE user_id = ?",
        [$user['id']]
    );
} catch (Exception $e) {}

// Get upcoming events (next 7 days)
try {
    $upcomingEvents = Database::fetchAll(
        "SELECT e.*, c.name as congregation_name
         FROM events e
         LEFT JOIN congregations c ON e.congregation_id = c.id
         WHERE (e.scope = 'global' OR e.congregation_id = ?)
           AND e.start_datetime >= NOW()
           AND e.start_datetime <= DATE_ADD(NOW(), INTERVAL 7 DAY)
           AND e.status = 'published'
         ORDER BY e.start_datetime ASC
         LIMIT 5",
        [$primaryCong['id']]
    ) ?: [];
} catch (Exception $e) {}

// Get recent posts from congregation
try {
    $recentPosts = Database::fetchAll(
        "SELECT p.*, u.name as author_name, u.avatar as author_avatar,
                (SELECT COUNT(*) FROM reactions WHERE reactable_type = 'post' AND reactable_id = p.id) as reaction_count,
                (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND status = 'active') as comment_count
         FROM posts p
         JOIN users u ON p.user_id = u.id
         WHERE (p.scope = 'global' OR p.congregation_id = ?)
           AND p.status = 'active'
           AND p.group_id IS NULL
         ORDER BY p.is_pinned DESC, p.created_at DESC
         LIMIT 5",
        [$primaryCong['id']]
    ) ?: [];
} catch (Exception $e) {}

// Get unread notifications count
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/home/css/home.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Fallback styles in case CSS fails to load */
        svg { max-width: 24px; max-height: 24px; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="/" class="nav-logo">CRC</a>

            <div class="nav-links">
                <a href="/gospel_media/" class="nav-link">Feed</a>
                <a href="/bible/" class="nav-link">Bible</a>
                <a href="/morning_watch/" class="nav-link">Morning Study</a>
                <a href="/calendar/" class="nav-link">Calendar</a>
                <a href="/media/" class="nav-link">Media</a>
            </div>

            <div class="nav-actions">
                <a href="/notifications/" class="nav-icon-btn" title="Notifications">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="notification-badge"><?= $unreadNotifications > 9 ? '9+' : $unreadNotifications ?></span>
                    <?php endif; ?>
                </a>

                <div class="user-menu">
                    <button class="user-menu-btn">
                        <?php if ($user['avatar']): ?>
                            <img src="<?= e($user['avatar']) ?>" alt="" class="user-avatar">
                        <?php else: ?>
                            <div class="user-avatar-placeholder"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
                        <?php endif; ?>
                    </button>
                    <div class="user-dropdown">
                        <div class="user-dropdown-header">
                            <strong><?= e($user['name']) ?></strong>
                            <span><?= e($primaryCong['name']) ?></span>
                        </div>
                        <div class="user-dropdown-divider"></div>
                        <a href="/profile/" class="user-dropdown-item">Profile</a>
                        <a href="/diary/" class="user-dropdown-item">My Diary</a>
                        <a href="/homecells/" class="user-dropdown-item">Homecells</a>
                        <a href="/learning/" class="user-dropdown-item">Courses</a>
                        <?php if (Auth::isCongregationAdmin($primaryCong['id'])): ?>
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
    </nav>

    <main class="main-content">
        <div class="container">
            <!-- Welcome Section -->
            <section class="welcome-section">
                <div class="welcome-content">
                    <h1>Welkom, <?= e(explode(' ', $user['name'])[0]) ?>!</h1>
                    <p><?= date('l, j F Y') ?></p>
                </div>
            </section>

            <div class="dashboard-grid">
                <!-- Morning Study Card -->
                <div class="dashboard-card morning-watch-card">
                    <div class="card-header">
                        <h2>Morning Study</h2>
                        <?php if ($streak): ?>
                            <span class="streak-badge"><?= $streak['current_streak'] ?> day streak</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($todaySession): ?>
                        <div class="morning-watch-preview">
                            <h3><?= e($todaySession['title'] ?: $todaySession['theme']) ?></h3>
                            <p class="scripture-ref"><?= e($todaySession['scripture_ref']) ?></p>
                            <?php if ($completedToday): ?>
                                <div class="completed-badge">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                    </svg>
                                    Completed
                                </div>
                            <?php else: ?>
                                <a href="/morning_watch/" class="btn btn-primary">Start Today's Session</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-content">
                            <p>No Morning Study session for today yet.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-card quick-actions-card">
                    <h2>Quick Actions</h2>
                    <div class="quick-actions-grid">
                        <a href="/bible/" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                                </svg>
                            </div>
                            <span>Bible</span>
                        </a>
                        <a href="/gospel_media/create.php" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                            </div>
                            <span>New Post</span>
                        </a>
                        <a href="/diary/create.php" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                </svg>
                            </div>
                            <span>Journal</span>
                        </a>
                        <a href="/calendar/create.php" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                            </div>
                            <span>Add Event</span>
                        </a>
                    </div>
                </div>

                <!-- Upcoming Events -->
                <div class="dashboard-card events-card">
                    <div class="card-header">
                        <h2>Upcoming Events</h2>
                        <a href="/calendar/" class="view-all-link">View All</a>
                    </div>
                    <?php if ($upcomingEvents): ?>
                        <div class="events-list">
                            <?php foreach ($upcomingEvents as $event): ?>
                                <a href="/calendar/event.php?id=<?= $event['id'] ?>" class="event-item">
                                    <div class="event-date">
                                        <span class="event-day"><?= date('d', strtotime($event['start_datetime'])) ?></span>
                                        <span class="event-month"><?= date('M', strtotime($event['start_datetime'])) ?></span>
                                    </div>
                                    <div class="event-info">
                                        <h4><?= e($event['title']) ?></h4>
                                        <p><?= date('H:i', strtotime($event['start_datetime'])) ?> • <?= e($event['location'] ?: 'No location') ?></p>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-content">
                            <p>No upcoming events in the next 7 days.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Posts -->
                <div class="dashboard-card posts-card">
                    <div class="card-header">
                        <h2>Recent Posts</h2>
                        <a href="/gospel_media/" class="view-all-link">View All</a>
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

    <script>
        // User menu dropdown
        document.querySelector('.user-menu-btn').addEventListener('click', function() {
            document.querySelector('.user-dropdown').classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.user-menu')) {
                document.querySelector('.user-dropdown').classList.remove('show');
            }
        });
    </script>
</body>
</html>
