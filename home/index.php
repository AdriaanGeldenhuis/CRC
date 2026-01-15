<?php
/**
 * CRC Home Dashboard
 * Main page after login - Premium Glass Morphism Design
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
$newsItems = [];
$aiMessage = null;

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

// Get active news items for carousel
try {
    $newsItems = Database::fetchAll(
        "SELECT * FROM news_items WHERE is_active = 1 ORDER BY display_order ASC"
    ) ?: [];
} catch (Exception $e) {}

// Get or generate AI message of the day
try {
    $aiMessage = Database::fetchOne(
        "SELECT * FROM ai_daily_messages WHERE message_date = CURDATE()"
    );
} catch (Exception $e) {}

// Default inspirational messages if no AI message
$defaultMessages = [
    ['message' => "May the Lord bless your day with peace and grace. Remember, in Him we have everything we need.", 'scripture' => "Philippians 4:19"],
    ['message' => "Today is a new opportunity to experience God's love and share it with others.", 'scripture' => "Lamentations 3:22-23"],
    ['message' => "Let your light shine before others, that they may see your good deeds and glorify your Father in heaven.", 'scripture' => "Matthew 5:16"],
    ['message' => "The Lord is your shepherd, you shall not want. Trust in His provision today.", 'scripture' => "Psalm 23:1"],
    ['message' => "Be strong and courageous! Do not be afraid, for the Lord your God is with you wherever you go.", 'scripture' => "Joshua 1:9"],
    ['message' => "In all things we are more than conquerors through Him who loved us.", 'scripture' => "Romans 8:37"],
    ['message' => "Cast all your anxiety on Him, because He cares for you.", 'scripture' => "1 Peter 5:7"],
];

if (!$aiMessage) {
    // Use a daily rotating message based on day of year
    $dayIndex = date('z') % count($defaultMessages);
    $aiMessage = [
        'message_content' => $defaultMessages[$dayIndex]['message'],
        'scripture_ref' => $defaultMessages[$dayIndex]['scripture'],
        'mood' => 'inspirational'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/home/css/home.css?v=<?= filemtime(__DIR__ . '/css/home.css') ?>">
    <script>
        // Load saved theme before page renders to prevent flash
        (function() {
            const saved = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', saved);
        })();
    </script>
</head>
<body data-theme="dark">
    <!-- Top Bar / Navigation -->
    <div class="topbar">
        <div class="inner">
            <div class="brand">
                <div class="logo" aria-hidden="true"></div>
                <div>
                    <h1>CRC App</h1>
                    <span><?= e($primaryCong['name']) ?></span>
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
                        <a href="/gospel_media/" class="more-dropdown-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 11a9 9 0 0 1 9 9"></path>
                                <path d="M4 4a16 16 0 0 1 16 16"></path>
                                <circle cx="5" cy="19" r="1"></circle>
                            </svg>
                            Feed
                        </a>
                        <a href="/bible/" class="more-dropdown-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                                <path d="M12 6v7"></path>
                                <path d="M8 9h8"></path>
                            </svg>
                            Bible
                        </a>
                        <a href="/ai_smartbible/" class="more-dropdown-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                <circle cx="19" cy="5" r="3" fill="currentColor"></circle>
                            </svg>
                            AI SmartBible
                        </a>
                        <a href="/morning_watch/" class="more-dropdown-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            Calendar
                        </a>
                        <a href="/media/" class="more-dropdown-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="23 7 16 12 23 17 23 7"></polygon>
                                <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
                            </svg>
                            Media
                        </a>
                        <div class="more-dropdown-divider"></div>
                        <a href="/diary/" class="more-dropdown-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                            </svg>
                            My Diary
                        </a>
                        <a href="/homecells/" class="more-dropdown-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                            Homecells
                        </a>
                        <a href="/learning/" class="more-dropdown-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
                            <span><?= e($primaryCong['name']) ?></span>
                        </div>
                        <div class="user-dropdown-divider"></div>
                        <a href="/profile/" class="user-dropdown-item">Profile</a>
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
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Welcome Section -->
            <section class="welcome-section">
                <div class="welcome-content">
                    <h1>Welkom, <?= e(explode(' ', $user['name'])[0]) ?>!</h1>
                    <p><?= date('l, j F Y') ?></p>
                </div>
            </section>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- AI Message of the Day Card -->
                <div class="dashboard-card ai-message-card">
                    <div class="card-header">
                        <h2>
                            <span class="icon" style="display:inline;vertical-align:middle;margin-right:8px;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--accent);">
                                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
                                </svg>
                            </span>
                            AI Message of the Day
                        </h2>
                        <span class="ai-badge">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <path d="M12 16v-4"></path>
                                <path d="M12 8h.01"></path>
                            </svg>
                            AI
                        </span>
                    </div>
                    <div class="ai-message-content">
                        <blockquote class="ai-message-text">
                            "<?= e($aiMessage['message_content']) ?>"
                        </blockquote>
                        <?php if (!empty($aiMessage['scripture_ref'])): ?>
                            <p class="ai-scripture-ref">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                                </svg>
                                <?= e($aiMessage['scripture_ref']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <a href="/ai_smartbible/" class="btn-ai-chat" data-ripple>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                        Chat with AI SmartBible
                    </a>
                </div>

                <!-- News Card -->
                <div class="dashboard-card news-card">
                    <div class="card-header">
                        <h2>
                            <span class="icon" style="display:inline;vertical-align:middle;margin-right:8px;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--accent2);">
                                    <rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/>
                                </svg>
                            </span>
                            NEWS
                        </h2>
                    </div>
                    <?php if ($newsItems): ?>
                        <div class="news-list">
                            <?php foreach ($newsItems as $news): ?>
                                <div class="news-item">
                                    <?php if ($news['link_url']): ?>
                                        <a href="<?= e($news['link_url']) ?>" class="news-item-link" target="_blank">
                                    <?php endif; ?>
                                        <div class="news-item-image">
                                            <img src="<?= e($news['image_path']) ?>" alt="<?= e($news['title']) ?>">
                                        </div>
                                        <div class="news-item-content">
                                            <h3 class="news-item-title"><?= e($news['title']) ?></h3>
                                            <?php if ($news['description']): ?>
                                                <p class="news-item-desc"><?= e($news['description']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php if ($news['link_url']): ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-content news-empty">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:0.4;margin-bottom:12px;">
                                <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>
                            </svg>
                            <p>No news at the moment.</p>
                        </div>
                    <?php endif; ?>
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
                                <a href="/calendar/event.php?id=<?= $event['id'] ?>" class="event-item" data-ripple>
                                    <div class="event-date">
                                        <span class="event-day"><?= date('d', strtotime($event['start_datetime'])) ?></span>
                                        <span class="event-month"><?= date('M', strtotime($event['start_datetime'])) ?></span>
                                    </div>
                                    <div class="event-info">
                                        <h4><?= e($event['title']) ?></h4>
                                        <p><?= date('H:i', strtotime($event['start_datetime'])) ?> &bull; <?= e($event['location'] ?: 'No location') ?></p>
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
                                <a href="/gospel_media/post.php?id=<?= $post['id'] ?>" class="post-item" data-ripple>
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
                            <a href="/gospel_media/create.php" class="btn btn-outline" data-ripple>Create Post</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Bottom Navigation -->
    <div class="bottom">
        <nav class="nav" aria-label="Bottom navigation">
            <a class="active" href="/" data-ripple>
                <span class="icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10.5l8-7 8 7V20a1.5 1.5 0 01-1.5 1.5h-3.5V15a1 1 0 00-1-1h-4a1 1 0 00-1 1v6.5H5.5A1.5 1.5 0 014 20v-9.5z" stroke-linejoin="round"/></svg>
                </span>
                Home
            </a>
            <a href="/morning_watch/" data-ripple>
                <span class="icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h10M4 18h16" stroke-linecap="round"/></svg>
                </span>
                Study
            </a>
            <a href="/bible/" data-ripple>
                <span class="icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 4h10a2 2 0 012 2v14l-7-3-7 3V6a2 2 0 012-2z" stroke-linejoin="round"/></svg>
                </span>
                Bible
            </a>
            <a href="/homecells/" data-ripple>
                <span class="icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s7-4.4 7-11a7 7 0 10-14 0c0 6.6 7 11 7 11z" stroke-linejoin="round"/></svg>
                </span>
                Gemeente
            </a>
        </nav>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast" role="status" aria-live="polite">
        <span class="mini" aria-hidden="true"></span>
        <p id="toastText">Notification</p>
    </div>

    <script>
        // Theme toggle function
        function toggleTheme() {
            const html = document.documentElement;
            const body = document.body;
            const currentTheme = html.getAttribute('data-theme') || 'dark';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

            html.setAttribute('data-theme', newTheme);
            body.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);

            showToast('Theme: ' + newTheme);
        }

        function toggleUserMenu() {
            document.getElementById('moreDropdown')?.classList.remove('show');
            document.getElementById('userDropdown').classList.toggle('show');
        }

        function toggleMoreMenu() {
            document.getElementById('userDropdown')?.classList.remove('show');
            document.getElementById('moreDropdown').classList.toggle('show');
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.user-menu')) {
                document.getElementById('userDropdown')?.classList.remove('show');
            }
            if (!e.target.closest('.more-menu')) {
                document.getElementById('moreDropdown')?.classList.remove('show');
            }
        });

        // Ripple effect
        document.addEventListener('click', function(e) {
            const target = e.target.closest('[data-ripple]');
            if (!target) return;

            const rect = target.getBoundingClientRect();
            const ripple = document.createElement('span');
            ripple.className = 'ripple';
            ripple.style.left = (e.clientX - rect.left) + 'px';
            ripple.style.top = (e.clientY - rect.top) + 'px';
            target.appendChild(ripple);
            setTimeout(function() { ripple.remove(); }, 650);
        });

        // Toast helper
        var toast = document.getElementById('toast');
        var toastText = document.getElementById('toastText');
        var toastTimer = null;

        function showToast(msg) {
            toastText.textContent = msg;
            toast.classList.add('show');
            clearTimeout(toastTimer);
            toastTimer = setTimeout(function() { toast.classList.remove('show'); }, 2400);
        }

        // Keyboard shortcut for theme toggle (Ctrl/Cmd + Shift + L)
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'L') {
                e.preventDefault();
                toggleTheme();
            }
        });

        // Apply saved theme on load
        (function() {
            var saved = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', saved);
            document.body.setAttribute('data-theme', saved);
        })();
    </script>
</body>
</html>
