<?php
/**
 * CRC Media Hub - Dashboard Layout
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
$pageTitle = "Media - CRC";

$activeLivestream = null;
$latestSermons = [];
$series = [];
$congId = $primaryCong ? $primaryCong['id'] : 0;

if ($primaryCong) {
    try {
        $activeLivestream = Database::fetchOne(
            "SELECT * FROM livestreams WHERE congregation_id = ? AND status = 'live' ORDER BY started_at DESC LIMIT 1",
            [$primaryCong['id']]
        );
    } catch (Exception $e) {}
}

try {
    $latestSermons = Database::fetchAll(
        "SELECT s.*, u.name as speaker_name FROM sermons s
         LEFT JOIN users u ON s.speaker_user_id = u.id
         WHERE s.status = 'published' AND (s.congregation_id = ? OR s.congregation_id IS NULL)
         ORDER BY s.sermon_date DESC LIMIT 5",
        [$congId]
    ) ?: [];
} catch (Exception $e) {}

try {
    $series = Database::fetchAll(
        "SELECT ss.*, (SELECT COUNT(*) FROM sermons WHERE series_id = ss.id AND status = 'published') as sermon_count
         FROM sermon_series ss WHERE ss.congregation_id = ? OR ss.congregation_id IS NULL
         ORDER BY ss.created_at DESC LIMIT 4",
        [$congId]
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
                    <h1>Media</h1>
                    <p>Sermons, livestreams, and more</p>
                </div>
            </section>

            <div class="dashboard-grid">
                <!-- Live/Featured Card -->
                <div class="dashboard-card morning-watch-card">
                    <div class="card-header">
                        <h2><?= $activeLivestream ? 'Live Now' : 'Watch' ?></h2>
                        <?php if ($activeLivestream): ?>
                            <span class="streak-badge">LIVE</span>
                        <?php endif; ?>
                    </div>
                    <div class="morning-watch-preview">
                        <?php if ($activeLivestream): ?>
                            <h3><?= e($activeLivestream['title']) ?></h3>
                            <p class="scripture-ref">Join the live service</p>
                            <a href="/media/livestream.php?id=<?= $activeLivestream['id'] ?>" class="btn btn-primary">Watch Live</a>
                        <?php elseif ($latestSermons): ?>
                            <h3><?= e($latestSermons[0]['title']) ?></h3>
                            <p class="scripture-ref"><?= e($latestSermons[0]['speaker_name'] ?? $latestSermons[0]['speaker'] ?? '') ?></p>
                            <a href="/media/sermon.php?id=<?= $latestSermons[0]['id'] ?>" class="btn btn-primary">Watch Now</a>
                        <?php else: ?>
                            <h3>No media available</h3>
                            <p class="scripture-ref">Check back soon for new content</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-card quick-actions-card">
                    <h2>Browse</h2>
                    <div class="quick-actions-grid">
                        <a href="/media/sermons.php" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polygon points="23 7 16 12 23 17 23 7"></polygon>
                                    <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
                                </svg>
                            </div>
                            <span>Sermons</span>
                        </a>
                        <a href="/media/livestream.php" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="2" y="7" width="20" height="15" rx="2" ry="2"></rect>
                                    <polyline points="17 2 12 7 7 2"></polyline>
                                </svg>
                            </div>
                            <span>Live</span>
                        </a>
                        <a href="/media/sermons.php?view=series" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                                </svg>
                            </div>
                            <span>Series</span>
                        </a>
                        <a href="/media/music.php" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 18V5l12-2v13"></path>
                                    <circle cx="6" cy="18" r="3"></circle>
                                    <circle cx="18" cy="16" r="3"></circle>
                                </svg>
                            </div>
                            <span>Music</span>
                        </a>
                    </div>
                </div>

                <!-- Latest Sermons -->
                <div class="dashboard-card events-card">
                    <div class="card-header">
                        <h2>Latest Sermons</h2>
                        <a href="/media/sermons.php" class="view-all-link">View All</a>
                    </div>
                    <?php if ($latestSermons): ?>
                        <div class="events-list">
                            <?php foreach (array_slice($latestSermons, 0, 3) as $sermon): ?>
                                <a href="/media/sermon.php?id=<?= $sermon['id'] ?>" class="event-item">
                                    <div class="event-date">
                                        <span class="event-day"><?= date('d', strtotime($sermon['sermon_date'])) ?></span>
                                        <span class="event-month"><?= date('M', strtotime($sermon['sermon_date'])) ?></span>
                                    </div>
                                    <div class="event-info">
                                        <h4><?= e(truncate($sermon['title'], 30)) ?></h4>
                                        <p><?= e($sermon['speaker_name'] ?? $sermon['speaker']) ?></p>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-content">
                            <p>No sermons yet</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Series -->
                <div class="dashboard-card posts-card">
                    <div class="card-header">
                        <h2>Sermon Series</h2>
                        <a href="/media/sermons.php?view=series" class="view-all-link">View All</a>
                    </div>
                    <?php if ($series): ?>
                        <div class="posts-list">
                            <?php foreach ($series as $s): ?>
                                <a href="/media/sermons.php?series=<?= $s['id'] ?>" class="post-item">
                                    <div class="post-author">
                                        <div class="author-avatar-placeholder">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                                            </svg>
                                        </div>
                                        <span><?= e($s['name']) ?></span>
                                    </div>
                                    <p class="post-content"><?= $s['sermon_count'] ?> sermons</p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-content">
                            <p>No series yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
