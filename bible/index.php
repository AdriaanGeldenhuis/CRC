<?php
/**
 * CRC Bible Reader - Dashboard Layout
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$pageTitle = 'Bible - CRC';

$version = $_GET['v'] ?? 'KJV';
$book = $_GET['b'] ?? 'Genesis';
$chapter = max(1, (int)($_GET['c'] ?? 1));

// Get user bookmarks
$bookmarks = [];
try {
    $bookmarks = Database::fetchAll(
        "SELECT * FROM bible_bookmarks WHERE user_id = ? ORDER BY created_at DESC LIMIT 5",
        [$user['id']]
    ) ?: [];
} catch (Exception $e) {}

// Get reading history
$history = [];
try {
    $history = Database::fetchAll(
        "SELECT * FROM bible_reading_history WHERE user_id = ? ORDER BY read_at DESC LIMIT 5",
        [$user['id']]
    ) ?: [];
} catch (Exception $e) {}

// Count stats
$highlightCount = 0;
$noteCount = 0;
try {
    $highlightCount = Database::fetchColumn("SELECT COUNT(*) FROM bible_highlights WHERE user_id = ?", [$user['id']]) ?: 0;
    $noteCount = Database::fetchColumn("SELECT COUNT(*) FROM bible_notes WHERE user_id = ?", [$user['id']]) ?: 0;
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
                    <h1>Bible</h1>
                    <p>Read and study God's Word</p>
                </div>
            </section>

            <div class="dashboard-grid">
                <!-- Bible Reader Card (Featured) -->
                <div class="dashboard-card morning-watch-card">
                    <div class="card-header">
                        <h2><?= e($book) ?> <?= $chapter ?></h2>
                        <span class="streak-badge"><?= e($version) ?></span>
                    </div>
                    <div class="morning-watch-preview">
                        <p class="scripture-ref">Select a book and chapter to start reading</p>
                        <a href="/bible/reader.php?b=<?= urlencode($book) ?>&c=<?= $chapter ?>" class="btn btn-primary">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                            </svg>
                            Open Reader
                        </a>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-card quick-actions-card">
                    <h2>Quick Actions</h2>
                    <div class="quick-actions-grid">
                        <a href="/bible/reader.php?b=Psalms&c=23" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                                </svg>
                            </div>
                            <span>Psalm 23</span>
                        </a>
                        <a href="/bible/reader.php?b=John&c=3" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                </svg>
                            </div>
                            <span>John 3:16</span>
                        </a>
                        <a href="/bible/search.php" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                </svg>
                            </div>
                            <span>Search</span>
                        </a>
                        <a href="/bible/bookmarks.php" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                                </svg>
                            </div>
                            <span>Bookmarks</span>
                        </a>
                    </div>
                </div>

                <!-- My Study -->
                <div class="dashboard-card events-card">
                    <div class="card-header">
                        <h2>My Study</h2>
                        <a href="/bible/bookmarks.php" class="view-all-link">View All</a>
                    </div>
                    <div class="events-list">
                        <div class="event-item" style="cursor: default;">
                            <div class="event-date">
                                <span class="event-day"><?= $highlightCount ?></span>
                                <span class="event-month">HLTS</span>
                            </div>
                            <div class="event-info">
                                <h4>Highlights</h4>
                                <p>Verses you've highlighted</p>
                            </div>
                        </div>
                        <div class="event-item" style="cursor: default;">
                            <div class="event-date">
                                <span class="event-day"><?= $noteCount ?></span>
                                <span class="event-month">NOTE</span>
                            </div>
                            <div class="event-info">
                                <h4>Notes</h4>
                                <p>Personal study notes</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Reading -->
                <div class="dashboard-card posts-card">
                    <div class="card-header">
                        <h2>Continue Reading</h2>
                    </div>
                    <?php if ($history): ?>
                        <div class="posts-list">
                            <?php foreach ($history as $h): ?>
                                <a href="/bible/reader.php?b=<?= urlencode($h['book_name'] ?? 'Genesis') ?>&c=<?= $h['chapter'] ?? 1 ?>" class="post-item">
                                    <div class="post-author">
                                        <div class="author-avatar-placeholder">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                                            </svg>
                                        </div>
                                        <span><?= e($h['book_name'] ?? 'Genesis') ?> <?= $h['chapter'] ?? 1 ?></span>
                                        <span class="post-time"><?= time_ago($h['read_at'] ?? 'now') ?></span>
                                    </div>
                                    <p class="post-content">Continue where you left off</p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-content">
                            <p>Start reading to build your history</p>
                            <a href="/bible/reader.php?b=Genesis&c=1" class="btn btn-outline">Start with Genesis</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
