<?php
/**
 * CRC Diary - Dashboard Layout
 */

require_once __DIR__ . '/../core/bootstrap.php';

header('Content-Type: text/html; charset=UTF-8');

Auth::requireAuth();

$user = Auth::user();
$pageTitle = 'My Diary - CRC';

$recentEntries = [];
$totalEntries = 0;
$thisWeekCount = 0;

try {
    $totalEntries = Database::fetchColumn("SELECT COUNT(*) FROM diary_entries WHERE user_id = ?", [$user['id']]) ?: 0;
} catch (Exception $e) {}

try {
    $thisWeekCount = Database::fetchColumn(
        "SELECT COUNT(*) FROM diary_entries WHERE user_id = ? AND entry_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
        [$user['id']]
    ) ?: 0;
} catch (Exception $e) {}

try {
    $recentEntries = Database::fetchAll(
        "SELECT * FROM diary_entries WHERE user_id = ? ORDER BY entry_date DESC, created_at DESC LIMIT 5",
        [$user['id']]
    ) ?: [];
} catch (Exception $e) {}

$prayers = [];
try {
    $prayers = Database::fetchAll(
        "SELECT * FROM prayer_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 3",
        [$user['id']]
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
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <section class="welcome-section">
                <div class="welcome-content">
                    <h1>My Diary</h1>
                    <p>Your private space for reflection</p>
                </div>
            </section>

            <div class="dashboard-grid">
                <!-- Stats Card (Featured) -->
                <div class="dashboard-card morning-watch-card">
                    <div class="card-header">
                        <h2>Your Journey</h2>
                        <span class="streak-badge"><?= $thisWeekCount ?> this week</span>
                    </div>
                    <div class="morning-watch-preview">
                        <h3><?= number_format($totalEntries) ?> Entries</h3>
                        <p class="scripture-ref">Keep journaling your spiritual journey</p>
                        <a href="/diary/entry.php" class="btn btn-primary">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Write New Entry
                        </a>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-card quick-actions-card">
                    <h2>Quick Actions</h2>
                    <div class="quick-actions-grid">
                        <a href="/diary/entry.php" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                            </div>
                            <span>New Entry</span>
                        </a>
                        <a href="/diary/prayers.php" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                </svg>
                            </div>
                            <span>Prayers</span>
                        </a>
                        <a href="/diary/archive.php" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 8v13H3V8"></path>
                                    <path d="M1 3h22v5H1z"></path>
                                </svg>
                            </div>
                            <span>Archive</span>
                        </a>
                        <a href="/diary/gratitude.php" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                                </svg>
                            </div>
                            <span>Gratitude</span>
                        </a>
                    </div>
                </div>

                <!-- Recent Entries -->
                <div class="dashboard-card events-card">
                    <div class="card-header">
                        <h2>Recent Entries</h2>
                        <a href="/diary/all.php" class="view-all-link">View All</a>
                    </div>
                    <?php if ($recentEntries): ?>
                        <div class="events-list">
                            <?php foreach (array_slice($recentEntries, 0, 3) as $entry): ?>
                                <a href="/diary/entry.php?id=<?= $entry['id'] ?>" class="event-item">
                                    <div class="event-date">
                                        <span class="event-day"><?= date('d', strtotime($entry['entry_date'])) ?></span>
                                        <span class="event-month"><?= date('M', strtotime($entry['entry_date'])) ?></span>
                                    </div>
                                    <div class="event-info">
                                        <h4><?= e($entry['title'] ?: 'Untitled Entry') ?></h4>
                                        <p><?= e(truncate(strip_tags($entry['content'] ?? ''), 40)) ?></p>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-content">
                            <p>No entries yet</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Prayer Requests -->
                <div class="dashboard-card posts-card">
                    <div class="card-header">
                        <h2>Prayer Requests</h2>
                        <a href="/diary/prayers.php" class="view-all-link">View All</a>
                    </div>
                    <?php if ($prayers): ?>
                        <div class="posts-list">
                            <?php foreach ($prayers as $p): ?>
                                <div class="post-item">
                                    <div class="post-author">
                                        <div class="author-avatar-placeholder">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                            </svg>
                                        </div>
                                        <span><?= $p['answered_at'] ? 'Answered' : 'Active' ?></span>
                                        <span class="post-time"><?= time_ago($p['created_at']) ?></span>
                                    </div>
                                    <p class="post-content"><?= e(truncate($p['content'], 80)) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-content">
                            <p>No prayer requests yet</p>
                            <a href="/diary/prayers.php" class="btn btn-outline">Add Prayer Request</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
