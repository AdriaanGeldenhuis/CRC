<?php
/**
 * CRC Media - Single Sermon View
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
$sermonId = (int)($_GET['id'] ?? 0);

if (!$sermonId) {
    Response::redirect('/media/sermons.php');
}

$congId = $primaryCong ? $primaryCong['id'] : 0;

// Get sermon
$sermon = Database::fetchOne(
    "SELECT s.*, u.name as speaker_name, u.avatar_url as speaker_avatar, c.name as congregation_name, ss.name as series_name
     FROM sermons s
     LEFT JOIN users u ON s.speaker_user_id = u.id
     LEFT JOIN congregations c ON s.congregation_id = c.id
     LEFT JOIN sermon_series ss ON s.series_id = ss.id
     WHERE s.id = ? AND s.status = 'published' AND (s.congregation_id = ? OR s.congregation_id IS NULL)",
    [$sermonId, $congId]
);

if (!$sermon) {
    Response::redirect('/media/sermons.php');
}

$pageTitle = e($sermon['title']) . " - Sermons";

// Track view
Database::query(
    "UPDATE sermons SET view_count = view_count + 1 WHERE id = ?",
    [$sermonId]
);

// Get related sermons (same series or same speaker)
$relatedSermons = [];
if ($sermon['series_id']) {
    $relatedSermons = Database::fetchAll(
        "SELECT s.*, u.name as speaker_name
         FROM sermons s
         LEFT JOIN users u ON s.speaker_user_id = u.id
         WHERE s.series_id = ? AND s.id != ? AND s.status = 'published'
         ORDER BY s.sermon_date DESC LIMIT 4",
        [$sermon['series_id'], $sermonId]
    );
} else {
    $relatedSermons = Database::fetchAll(
        "SELECT s.*, u.name as speaker_name
         FROM sermons s
         LEFT JOIN users u ON s.speaker_user_id = u.id
         WHERE s.speaker = ? AND s.id != ? AND s.status = 'published'
         ORDER BY s.sermon_date DESC LIMIT 4",
        [$sermon['speaker'], $sermonId]
    );
}

// Check if user has saved/bookmarked
$isSaved = Database::fetchColumn(
    "SELECT COUNT(*) FROM user_saved_sermons WHERE user_id = ? AND sermon_id = ?",
    [$user['id'], $sermonId]
);

// Get sermon notes if any
$notes = Database::fetchOne(
    "SELECT * FROM sermon_notes WHERE user_id = ? AND sermon_id = ?",
    [$user['id'], $sermonId]
);

// Get scripture references
$scriptures = [];
if ($sermon['scripture_references']) {
    $scriptures = json_decode($sermon['scripture_references'], true) ?: [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/media/css/media.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container-wide">
            <a href="/media/sermons.php" class="back-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
                Back to Sermons
            </a>

            <div class="sermon-layout">
                <!-- Main Content -->
                <div class="sermon-main">
                    <!-- Video/Audio Player -->
                    <div class="sermon-player">
                        <?php if ($sermon['video_url']): ?>
                            <?php if (strpos($sermon['video_url'], 'youtube.com') !== false || strpos($sermon['video_url'], 'youtu.be') !== false): ?>
                                <?php
                                preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $sermon['video_url'], $matches);
                                $videoId = $matches[1] ?? '';
                                ?>
                                <div class="video-embed">
                                    <iframe src="https://www.youtube.com/embed/<?= e($videoId) ?>"
                                            frameborder="0"
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                            allowfullscreen></iframe>
                                </div>
                            <?php elseif (strpos($sermon['video_url'], 'vimeo.com') !== false): ?>
                                <?php
                                preg_match('/vimeo\.com\/(\d+)/', $sermon['video_url'], $matches);
                                $videoId = $matches[1] ?? '';
                                ?>
                                <div class="video-embed">
                                    <iframe src="https://player.vimeo.com/video/<?= e($videoId) ?>"
                                            frameborder="0"
                                            allow="autoplay; fullscreen; picture-in-picture"
                                            allowfullscreen></iframe>
                                </div>
                            <?php else: ?>
                                <video controls class="video-player">
                                    <source src="<?= e($sermon['video_url']) ?>" type="video/mp4">
                                    Your browser does not support video playback.
                                </video>
                            <?php endif; ?>
                        <?php elseif ($sermon['audio_url']): ?>
                            <div class="audio-player-container">
                                <?php if ($sermon['thumbnail_url']): ?>
                                    <img src="<?= e($sermon['thumbnail_url']) ?>" alt="" class="audio-cover">
                                <?php else: ?>
                                    <div class="audio-cover-placeholder">🎤</div>
                                <?php endif; ?>
                                <audio controls class="audio-player" id="audio-player">
                                    <source src="<?= e($sermon['audio_url']) ?>" type="audio/mpeg">
                                    Your browser does not support audio playback.
                                </audio>
                            </div>
                        <?php else: ?>
                            <div class="no-media">
                                <?php if ($sermon['thumbnail_url']): ?>
                                    <img src="<?= e($sermon['thumbnail_url']) ?>" alt="">
                                <?php else: ?>
                                    <div class="no-media-placeholder">🎤</div>
                                <?php endif; ?>
                                <p>No audio or video available</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Sermon Info -->
                    <div class="sermon-header">
                        <h1><?= e($sermon['title']) ?></h1>
                        <div class="sermon-actions">
                            <button onclick="toggleSave(<?= $sermonId ?>)" class="action-btn <?= $isSaved ? 'saved' : '' ?>" id="save-btn">
                                <span class="icon"><?= $isSaved ? '★' : '☆' ?></span>
                                <span class="label"><?= $isSaved ? 'Saved' : 'Save' ?></span>
                            </button>
                            <button onclick="shareSermon()" class="action-btn">
                                <span class="icon">↗</span>
                                <span class="label">Share</span>
                            </button>
                            <?php if ($sermon['audio_url']): ?>
                                <a href="<?= e($sermon['audio_url']) ?>" download class="action-btn">
                                    <span class="icon">↓</span>
                                    <span class="label">Download</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="sermon-meta-bar">
                        <div class="speaker-info">
                            <?php if ($sermon['speaker_avatar']): ?>
                                <img src="<?= e($sermon['speaker_avatar']) ?>" alt="" class="speaker-avatar">
                            <?php else: ?>
                                <div class="speaker-avatar-placeholder">
                                    <?= strtoupper(substr($sermon['speaker_name'] ?? $sermon['speaker'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <span class="speaker-name"><?= e($sermon['speaker_name'] ?? $sermon['speaker']) ?></span>
                                <span class="sermon-date"><?= date('F j, Y', strtotime($sermon['sermon_date'])) ?></span>
                            </div>
                        </div>
                        <div class="sermon-stats">
                            <?php if ($sermon['duration']): ?>
                                <span>⏱ <?= formatDuration($sermon['duration']) ?></span>
                            <?php endif; ?>
                            <span>👁 <?= number_format($sermon['view_count']) ?> views</span>
                        </div>
                    </div>

                    <?php if ($sermon['series_name']): ?>
                        <a href="/media/sermons.php?series=<?= $sermon['series_id'] ?>" class="series-link">
                            📚 Part of: <strong><?= e($sermon['series_name']) ?></strong>
                        </a>
                    <?php endif; ?>

                    <!-- Description -->
                    <?php if ($sermon['description']): ?>
                        <div class="sermon-description">
                            <h2>About This Sermon</h2>
                            <div class="description-content">
                                <?= nl2br(e($sermon['description'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Scripture References -->
                    <?php if ($scriptures): ?>
                        <div class="scripture-refs">
                            <h2>Scripture References</h2>
                            <div class="scripture-list">
                                <?php foreach ($scriptures as $ref): ?>
                                    <a href="/bible/?ref=<?= urlencode($ref) ?>" class="scripture-tag">
                                        <?= e($ref) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Notes Section -->
                    <div class="sermon-notes">
                        <h2>My Notes</h2>
                        <textarea id="sermon-notes" placeholder="Take notes while listening..."
                                  class="notes-textarea"><?= e($notes['content'] ?? '') ?></textarea>
                        <div class="notes-actions">
                            <span class="save-status" id="notes-status"></span>
                            <button onclick="saveNotes()" class="btn btn-primary btn-sm">Save Notes</button>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <aside class="sermon-sidebar">
                    <!-- Related Sermons -->
                    <?php if ($relatedSermons): ?>
                        <div class="sidebar-card">
                            <h3><?= $sermon['series_name'] ? 'More in This Series' : 'More from ' . e($sermon['speaker_name'] ?? $sermon['speaker']) ?></h3>
                            <div class="related-list">
                                <?php foreach ($relatedSermons as $related): ?>
                                    <a href="/media/sermon.php?id=<?= $related['id'] ?>" class="related-item">
                                        <div class="related-thumb">
                                            <?php if ($related['thumbnail_url']): ?>
                                                <img src="<?= e($related['thumbnail_url']) ?>" alt="">
                                            <?php else: ?>
                                                <div class="thumb-mini">🎤</div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="related-info">
                                            <h4><?= e($related['title']) ?></h4>
                                            <span><?= date('M j, Y', strtotime($related['sermon_date'])) ?></span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Timestamps/Chapters if available -->
                    <?php if ($sermon['timestamps']): ?>
                        <?php $timestamps = json_decode($sermon['timestamps'], true) ?: []; ?>
                        <?php if ($timestamps): ?>
                            <div class="sidebar-card">
                                <h3>Chapters</h3>
                                <div class="timestamps-list">
                                    <?php foreach ($timestamps as $ts): ?>
                                        <button onclick="seekTo(<?= (int)$ts['time'] ?>)" class="timestamp-item">
                                            <span class="ts-time"><?= formatDuration($ts['time']) ?></span>
                                            <span class="ts-label"><?= e($ts['label']) ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </aside>
            </div>
        </div>
    </main>

    <script>
        const sermonId = <?= $sermonId ?>;
    </script>
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
