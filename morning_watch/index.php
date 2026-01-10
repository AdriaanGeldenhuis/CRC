<?php
/**
 * CRC Morning Study - Dashboard Layout
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
$pageTitle = "Morning Study - CRC";

$todaySession = null;
$completedToday = false;
$streak = null;
$recentSessions = [];

// Get today's session
try {
    $todaySession = Database::fetchOne(
        "SELECT * FROM morning_sessions WHERE (scope = 'global' OR congregation_id = ?) AND session_date = CURDATE() AND published_at IS NOT NULL ORDER BY scope = 'congregation' DESC LIMIT 1",
        [$primaryCong['id'] ?? 0]
    );
} catch (Exception $e) {}

// Check if completed
if ($todaySession) {
    try {
        $entry = Database::fetchOne(
            "SELECT completed_at FROM morning_user_entries WHERE user_id = ? AND session_id = ?",
            [$user['id'], $todaySession['id']]
        );
        $completedToday = $entry && $entry['completed_at'];
    } catch (Exception $e) {}
}

// Get streak
try {
    $streak = Database::fetchOne("SELECT current_streak, longest_streak FROM morning_streaks WHERE user_id = ?", [$user['id']]);
} catch (Exception $e) {}

// Get recent sessions
try {
    $recentSessions = Database::fetchAll(
        "SELECT * FROM morning_sessions WHERE (scope = 'global' OR congregation_id = ?) AND published_at IS NOT NULL AND session_date < CURDATE() ORDER BY session_date DESC LIMIT 5",
        [$primaryCong['id'] ?? 0]
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
    <script>
        (function() {
            var theme = localStorage.getItem('theme');
            if (theme === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <section class="welcome-section">
                <div class="welcome-content">
                    <h1>Morning Study</h1>
                    <p><?= date('l, j F Y') ?></p>
                </div>
            </section>

            <div class="dashboard-grid">
                <!-- Today's Study Card (Featured) -->
                <div class="dashboard-card morning-watch-card">
                    <div class="card-header">
                        <h2>Today's Study</h2>
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
                                <a href="/morning_watch/session.php?id=<?= $todaySession['id'] ?>" class="btn btn-primary">Start Session</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="morning-watch-preview">
                            <h3>No session today</h3>
                            <p class="scripture-ref">Check back later for today's study</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-card quick-actions-card">
                    <h2>Quick Actions</h2>
                    <div class="quick-actions-grid">
                        <a href="/morning_watch/archive.php" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 8v13H3V8"></path>
                                    <path d="M1 3h22v5H1z"></path>
                                    <path d="M10 12h4"></path>
                                </svg>
                            </div>
                            <span>Archive</span>
                        </a>
                        <a href="/morning_watch/notes.php" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                </svg>
                            </div>
                            <span>My Notes</span>
                        </a>
                        <a href="/bible/" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                                </svg>
                            </div>
                            <span>Bible</span>
                        </a>
                        <a href="/morning_watch/stats.php" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="20" x2="18" y2="10"></line>
                                    <line x1="12" y1="20" x2="12" y2="4"></line>
                                    <line x1="6" y1="20" x2="6" y2="14"></line>
                                </svg>
                            </div>
                            <span>Stats</span>
                        </a>
                    </div>
                </div>

                <!-- Stats -->
                <div class="dashboard-card events-card">
                    <div class="card-header">
                        <h2>Your Progress</h2>
                    </div>
                    <div class="events-list">
                        <div class="event-item" style="cursor: default;">
                            <div class="event-date">
                                <span class="event-day"><?= $streak['current_streak'] ?? 0 ?></span>
                                <span class="event-month">DAY</span>
                            </div>
                            <div class="event-info">
                                <h4>Current Streak</h4>
                                <p>Keep it going!</p>
                            </div>
                        </div>
                        <div class="event-item" style="cursor: default;">
                            <div class="event-date">
                                <span class="event-day"><?= $streak['longest_streak'] ?? 0 ?></span>
                                <span class="event-month">DAY</span>
                            </div>
                            <div class="event-info">
                                <h4>Longest Streak</h4>
                                <p>Personal best</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Past Sessions -->
                <div class="dashboard-card posts-card">
                    <div class="card-header">
                        <h2>Past Sessions</h2>
                        <a href="/morning_watch/archive.php" class="view-all-link">View All</a>
                    </div>
                    <?php if ($recentSessions): ?>
                        <div class="posts-list">
                            <?php foreach ($recentSessions as $session): ?>
                                <a href="/morning_watch/session.php?id=<?= $session['id'] ?>" class="post-item">
                                    <div class="post-author">
                                        <div class="author-avatar-placeholder">
                                            <?= date('d', strtotime($session['session_date'])) ?>
                                        </div>
                                        <span><?= e($session['title'] ?: $session['theme']) ?></span>
                                        <span class="post-time"><?= date('M j', strtotime($session['session_date'])) ?></span>
                                    </div>
                                    <p class="post-content"><?= e($session['scripture_ref']) ?></p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-content">
                            <p>No past sessions yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
