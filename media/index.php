<?php
/**
 * CRC Media Hub - Main Page
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
$pageTitle = "Media - CRC";

// Get active livestream
$activeLivestream = null;
if ($primaryCong) {
    $activeLivestream = Database::fetchOne(
        "SELECT * FROM livestreams WHERE congregation_id = ? AND status = 'live' ORDER BY started_at DESC LIMIT 1",
        [$primaryCong['id']]
    );
}

// Get upcoming scheduled livestreams
$upcomingStreams = [];
if ($primaryCong) {
    $upcomingStreams = Database::fetchAll(
        "SELECT * FROM livestreams
         WHERE congregation_id = ? AND status = 'scheduled' AND scheduled_at > NOW()
         ORDER BY scheduled_at ASC LIMIT 3",
        [$primaryCong['id']]
    );
}

// Get latest sermons (congregation + global)
$congId = $primaryCong ? $primaryCong['id'] : 0;
$latestSermons = Database::fetchAll(
    "SELECT s.*, u.name as speaker_name, c.name as congregation_name
     FROM sermons s
     LEFT JOIN users u ON s.speaker_user_id = u.id
     LEFT JOIN congregations c ON s.congregation_id = c.id
     WHERE s.status = 'published' AND (s.congregation_id = ? OR s.congregation_id IS NULL)
     ORDER BY s.sermon_date DESC LIMIT 6",
    [$congId]
);

// Get sermon series
$series = Database::fetchAll(
    "SELECT ss.*,
            (SELECT COUNT(*) FROM sermons WHERE series_id = ss.id AND status = 'published') as sermon_count
     FROM sermon_series ss
     WHERE ss.congregation_id = ? OR ss.congregation_id IS NULL
     ORDER BY ss.created_at DESC LIMIT 4",
    [$congId]
);

// Get categories
$categories = Database::fetchAll(
    "SELECT DISTINCT category FROM sermons
     WHERE status = 'published' AND category IS NOT NULL AND category != ''
     ORDER BY category ASC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/media/css/media.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <!-- Live Now Banner -->
            <?php if ($activeLivestream): ?>
                <a href="/media/livestream.php?id=<?= $activeLivestream['id'] ?>" class="live-banner">
                    <div class="live-indicator">
                        <span class="live-dot"></span>
                        LIVE NOW
                    </div>
                    <div class="live-info">
                        <h2><?= e($activeLivestream['title']) ?></h2>
                        <p>Join the live service now</p>
                    </div>
                    <div class="live-arrow">Watch →</div>
                </a>
            <?php endif; ?>

            <div class="page-header">
                <div class="page-title">
                    <h1>Media</h1>
                    <p>Sermons, livestreams, and more</p>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="/media/sermons.php" class="action-card">
                    <span class="action-icon">🎤</span>
                    <span class="action-label">All Sermons</span>
                </a>
                <a href="/media/livestream.php" class="action-card">
                    <span class="action-icon">📺</span>
                    <span class="action-label">Livestream</span>
                </a>
                <?php if ($series): ?>
                    <a href="/media/sermons.php?view=series" class="action-card">
                        <span class="action-icon">📚</span>
                        <span class="action-label">Sermon Series</span>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Upcoming Livestreams -->
            <?php if ($upcomingStreams): ?>
                <section class="section">
                    <h2 class="section-title">Upcoming Livestreams</h2>
                    <div class="upcoming-grid">
                        <?php foreach ($upcomingStreams as $stream): ?>
                            <div class="upcoming-card">
                                <div class="upcoming-date">
                                    <span class="day"><?= date('d', strtotime($stream['scheduled_at'])) ?></span>
                                    <span class="month"><?= date('M', strtotime($stream['scheduled_at'])) ?></span>
                                </div>
                                <div class="upcoming-info">
                                    <h3><?= e($stream['title']) ?></h3>
                                    <p><?= date('l, g:i A', strtotime($stream['scheduled_at'])) ?></p>
                                </div>
                                <button class="btn btn-outline btn-sm" onclick="setReminder(<?= $stream['id'] ?>)">
                                    Remind Me
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- Latest Sermons -->
            <section class="section">
                <div class="section-header">
                    <h2 class="section-title">Latest Sermons</h2>
                    <a href="/media/sermons.php" class="view-all">View All →</a>
                </div>
                <?php if ($latestSermons): ?>
                    <div class="sermons-grid">
                        <?php foreach ($latestSermons as $sermon): ?>
                            <a href="/media/sermon.php?id=<?= $sermon['id'] ?>" class="sermon-card">
                                <div class="sermon-thumb">
                                    <?php if ($sermon['thumbnail_url']): ?>
                                        <img src="<?= e($sermon['thumbnail_url']) ?>" alt="">
                                    <?php else: ?>
                                        <div class="thumb-placeholder">🎤</div>
                                    <?php endif; ?>
                                    <?php if ($sermon['duration']): ?>
                                        <span class="duration"><?= formatDuration($sermon['duration']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="sermon-info">
                                    <h3><?= e($sermon['title']) ?></h3>
                                    <p class="sermon-speaker"><?= e($sermon['speaker_name'] ?? $sermon['speaker']) ?></p>
                                    <p class="sermon-date"><?= date('M j, Y', strtotime($sermon['sermon_date'])) ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">🎤</div>
                        <h3>No sermons yet</h3>
                        <p>Sermons will appear here once they are uploaded.</p>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Sermon Series -->
            <?php if ($series): ?>
                <section class="section">
                    <div class="section-header">
                        <h2 class="section-title">Sermon Series</h2>
                        <a href="/media/sermons.php?view=series" class="view-all">View All →</a>
                    </div>
                    <div class="series-grid">
                        <?php foreach ($series as $s): ?>
                            <a href="/media/sermons.php?series=<?= $s['id'] ?>" class="series-card">
                                <div class="series-cover">
                                    <?php if ($s['cover_url']): ?>
                                        <img src="<?= e($s['cover_url']) ?>" alt="">
                                    <?php else: ?>
                                        <div class="cover-placeholder"><?= strtoupper(substr($s['name'], 0, 2)) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="series-info">
                                    <h3><?= e($s['name']) ?></h3>
                                    <p><?= $s['sermon_count'] ?> sermon<?= $s['sermon_count'] != 1 ? 's' : '' ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- Browse by Category -->
            <?php if ($categories): ?>
                <section class="section">
                    <h2 class="section-title">Browse by Topic</h2>
                    <div class="categories-list">
                        <?php foreach ($categories as $cat): ?>
                            <a href="/media/sermons.php?category=<?= urlencode($cat['category']) ?>" class="category-tag">
                                <?= e($cat['category']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </main>

    <script src="/media/js/media.js"></script>
</body>
</html>
<?php
function formatDuration($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;

    if ($hours > 0) {
        return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
    }
    return sprintf('%d:%02d', $minutes, $secs);
}
?>
