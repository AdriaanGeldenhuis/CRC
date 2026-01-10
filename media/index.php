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

// Get active livestream
if ($primaryCong) {
    try {
        $activeLivestream = Database::fetchOne(
            "SELECT * FROM livestreams WHERE congregation_id = ? AND status = 'live' ORDER BY started_at DESC LIMIT 1",
            [$primaryCong['id']]
        );
    } catch (Exception $e) {}
}

// Get latest sermons
try {
    $latestSermons = Database::fetchAll(
        "SELECT s.*, u.name as speaker_name FROM sermons s
         LEFT JOIN users u ON s.speaker_user_id = u.id
         WHERE s.status = 'published' AND (s.congregation_id = ? OR s.congregation_id IS NULL)
         ORDER BY s.sermon_date DESC LIMIT 5",
        [$congId]
    ) ?: [];
} catch (Exception $e) {}

// Get sermon series
try {
    $series = Database::fetchAll(
        "SELECT ss.*, (SELECT COUNT(*) FROM sermons WHERE series_id = ss.id AND status = 'published') as sermon_count
         FROM sermon_series ss
         WHERE ss.congregation_id = ? OR ss.congregation_id IS NULL
         ORDER BY ss.created_at DESC LIMIT 4",
        [$congId]
    ) ?: [];
} catch (Exception $e) {}

function formatDuration($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    if ($hours > 0) return sprintf('%d:%02d', $hours, $minutes);
    return sprintf('%d min', $minutes);
}
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
        .media-card {
            background: linear-gradient(135deg, #EF4444 0%, #F87171 100%);
            color: var(--white);
        }
        .media-card .card-header h2 { color: var(--white); }
        .live-banner {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: rgba(255,255,255,0.15);
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            text-decoration: none;
            color: white;
        }
        .live-dot {
            width: 12px;
            height: 12px;
            background: white;
            border-radius: 50%;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
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
        .sermon-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .sermon-item {
            display: flex;
            gap: 1rem;
            padding: 0.75rem;
            background: var(--gray-50);
            border-radius: var(--radius);
            text-decoration: none;
            transition: var(--transition);
        }
        .sermon-item:hover { background: var(--gray-100); }
        .sermon-thumb {
            width: 60px;
            height: 60px;
            background: var(--gray-200);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .sermon-info h4 { font-size: 0.875rem; color: var(--gray-800); margin-bottom: 0.25rem; }
        .sermon-info p { font-size: 0.75rem; color: var(--gray-500); }
        .series-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
        }
        .series-item {
            padding: 0.75rem;
            background: var(--gray-50);
            border-radius: var(--radius);
            text-decoration: none;
            text-align: center;
        }
        .series-item:hover { background: var(--gray-100); }
        .series-icon { font-size: 1.5rem; margin-bottom: 0.25rem; }
        .series-name { font-size: 0.75rem; color: var(--gray-700); font-weight: 500; }
        .series-count { font-size: 0.65rem; color: var(--gray-500); }
    </style>
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
                <div class="dashboard-card media-card">
                    <div class="card-header">
                        <h2><?= $activeLivestream ? 'Live Now' : 'Watch' ?></h2>
                    </div>
                    <?php if ($activeLivestream): ?>
                        <a href="/media/livestream.php?id=<?= $activeLivestream['id'] ?>" class="live-banner">
                            <span class="live-dot"></span>
                            <div style="flex: 1;">
                                <strong><?= e($activeLivestream['title']) ?></strong>
                                <p style="font-size: 0.875rem; opacity: 0.9;">Join the live service</p>
                            </div>
                        </a>
                    <?php else: ?>
                        <div style="text-align: center; padding: 1rem;">
                            <p style="margin-bottom: 1rem;">No live stream right now</p>
                        </div>
                    <?php endif; ?>
                    <a href="/media/livestream.php" class="btn" style="width: 100%; background: white; color: #EF4444;">
                        <?= $activeLivestream ? 'Watch Live' : 'View Schedule' ?>
                    </a>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-card">
                    <h2 style="margin-bottom: 1rem;">Browse</h2>
                    <div class="quick-actions-grid">
                        <a href="/media/sermons.php" class="quick-action">
                            <span class="quick-action-icon">🎤</span>
                            <span>Sermons</span>
                        </a>
                        <a href="/media/livestream.php" class="quick-action">
                            <span class="quick-action-icon">📺</span>
                            <span>Live</span>
                        </a>
                        <a href="/media/sermons.php?view=series" class="quick-action">
                            <span class="quick-action-icon">📚</span>
                            <span>Series</span>
                        </a>
                        <a href="/media/music.php" class="quick-action">
                            <span class="quick-action-icon">🎵</span>
                            <span>Music</span>
                        </a>
                    </div>
                </div>

                <!-- Latest Sermons -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>Latest Sermons</h2>
                        <a href="/media/sermons.php" class="view-all-link">View All</a>
                    </div>
                    <?php if ($latestSermons): ?>
                        <div class="sermon-list">
                            <?php foreach (array_slice($latestSermons, 0, 3) as $sermon): ?>
                                <a href="/media/sermon.php?id=<?= $sermon['id'] ?>" class="sermon-item">
                                    <div class="sermon-thumb">🎤</div>
                                    <div class="sermon-info">
                                        <h4><?= e($sermon['title']) ?></h4>
                                        <p><?= e($sermon['speaker_name'] ?? $sermon['speaker']) ?> • <?= date('M j', strtotime($sermon['sermon_date'])) ?></p>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 2rem; color: var(--gray-500);">
                            <p>No sermons yet</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Series -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>Sermon Series</h2>
                        <a href="/media/sermons.php?view=series" class="view-all-link">View All</a>
                    </div>
                    <?php if ($series): ?>
                        <div class="series-grid">
                            <?php foreach ($series as $s): ?>
                                <a href="/media/sermons.php?series=<?= $s['id'] ?>" class="series-item">
                                    <div class="series-icon">📚</div>
                                    <div class="series-name"><?= e(truncate($s['name'], 15)) ?></div>
                                    <div class="series-count"><?= $s['sermon_count'] ?> sermons</div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 1rem; color: var(--gray-500);">
                            <p>No series yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
