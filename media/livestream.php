<?php
/**
 * CRC Media - Livestream
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
$streamId = (int)($_GET['id'] ?? 0);

$congId = $primaryCong ? $primaryCong['id'] : 0;

// Get specific stream or active stream
$livestream = null;
if ($streamId) {
    $livestream = Database::fetchOne(
        "SELECT l.*, c.name as congregation_name
         FROM livestreams l
         LEFT JOIN congregations c ON l.congregation_id = c.id
         WHERE l.id = ? AND (l.congregation_id = ? OR l.congregation_id IS NULL)",
        [$streamId, $congId]
    );
} else {
    // Get active livestream
    $livestream = Database::fetchOne(
        "SELECT l.*, c.name as congregation_name
         FROM livestreams l
         LEFT JOIN congregations c ON l.congregation_id = c.id
         WHERE l.congregation_id = ? AND l.status = 'live'
         ORDER BY l.started_at DESC LIMIT 1",
        [$congId]
    );
}

$pageTitle = $livestream ? e($livestream['title']) . " - Livestream" : "Livestream - CRC";

// Get upcoming streams
$upcomingStreams = Database::fetchAll(
    "SELECT * FROM livestreams
     WHERE congregation_id = ? AND status = 'scheduled' AND scheduled_at > NOW()
     ORDER BY scheduled_at ASC LIMIT 5",
    [$congId]
);

// Get past streams (recordings)
$pastStreams = Database::fetchAll(
    "SELECT * FROM livestreams
     WHERE congregation_id = ? AND status = 'ended' AND recording_url IS NOT NULL
     ORDER BY ended_at DESC LIMIT 6",
    [$congId]
);

// Get chat messages if live
$chatMessages = [];
if ($livestream && $livestream['status'] === 'live' && $livestream['chat_enabled']) {
    $chatMessages = Database::fetchAll(
        "SELECT lc.*, u.name, u.avatar_url
         FROM livestream_chat lc
         JOIN users u ON lc.user_id = u.id
         WHERE lc.livestream_id = ?
         ORDER BY lc.created_at DESC LIMIT 50",
        [$livestream['id']]
    );
    $chatMessages = array_reverse($chatMessages);
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
            <a href="/media/" class="back-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
                Back to Media
            </a>

            <?php if ($livestream && ($livestream['status'] === 'live' || $livestream['status'] === 'scheduled')): ?>
                <!-- Active/Scheduled Livestream -->
                <div class="livestream-layout">
                    <div class="livestream-main">
                        <?php if ($livestream['status'] === 'live'): ?>
                            <!-- Live Stream Player -->
                            <div class="stream-player">
                                <?php if ($livestream['embed_url']): ?>
                                    <?php if (strpos($livestream['embed_url'], 'youtube.com') !== false || strpos($livestream['embed_url'], 'youtu.be') !== false): ?>
                                        <?php
                                        preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $livestream['embed_url'], $matches);
                                        $videoId = $matches[1] ?? '';
                                        ?>
                                        <div class="video-embed">
                                            <iframe src="https://www.youtube.com/embed/<?= e($videoId) ?>?autoplay=1"
                                                    frameborder="0"
                                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                                    allowfullscreen></iframe>
                                        </div>
                                    <?php else: ?>
                                        <div class="video-embed">
                                            <iframe src="<?= e($livestream['embed_url']) ?>"
                                                    frameborder="0"
                                                    allowfullscreen></iframe>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="stream-placeholder">
                                        <span class="live-badge">● LIVE</span>
                                        <p>Stream starting soon...</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="stream-header">
                                <div class="stream-info">
                                    <span class="live-indicator">
                                        <span class="live-dot"></span>
                                        LIVE
                                    </span>
                                    <h1><?= e($livestream['title']) ?></h1>
                                    <?php if ($livestream['description']): ?>
                                        <p><?= e($livestream['description']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="stream-meta">
                                    <span class="viewer-count">
                                        👁 <span id="viewer-count"><?= number_format($livestream['viewer_count'] ?? 0) ?></span> watching
                                    </span>
                                    <span>Started <?= timeAgo($livestream['started_at']) ?></span>
                                </div>
                            </div>

                        <?php else: ?>
                            <!-- Scheduled Stream -->
                            <div class="scheduled-stream">
                                <div class="scheduled-info">
                                    <span class="scheduled-badge">📅 Upcoming</span>
                                    <h1><?= e($livestream['title']) ?></h1>
                                    <?php if ($livestream['description']): ?>
                                        <p><?= e($livestream['description']) ?></p>
                                    <?php endif; ?>
                                    <div class="countdown-container">
                                        <p>Starting in:</p>
                                        <div class="countdown" id="countdown" data-time="<?= $livestream['scheduled_at'] ?>">
                                            <div class="countdown-item">
                                                <span class="countdown-value" id="days">--</span>
                                                <span class="countdown-label">Days</span>
                                            </div>
                                            <div class="countdown-item">
                                                <span class="countdown-value" id="hours">--</span>
                                                <span class="countdown-label">Hours</span>
                                            </div>
                                            <div class="countdown-item">
                                                <span class="countdown-value" id="minutes">--</span>
                                                <span class="countdown-label">Minutes</span>
                                            </div>
                                            <div class="countdown-item">
                                                <span class="countdown-value" id="seconds">--</span>
                                                <span class="countdown-label">Seconds</span>
                                            </div>
                                        </div>
                                        <p class="scheduled-time">
                                            <?= date('l, F j, Y \a\t g:i A', strtotime($livestream['scheduled_at'])) ?>
                                        </p>
                                    </div>
                                    <button onclick="setReminder(<?= $livestream['id'] ?>)" class="btn btn-primary">
                                        🔔 Set Reminder
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Chat Sidebar -->
                    <?php if ($livestream['status'] === 'live' && $livestream['chat_enabled']): ?>
                        <aside class="chat-sidebar">
                            <div class="chat-header">
                                <h3>Live Chat</h3>
                                <span class="chat-status">● Online</span>
                            </div>
                            <div class="chat-messages" id="chat-messages">
                                <?php foreach ($chatMessages as $msg): ?>
                                    <div class="chat-message">
                                        <span class="chat-author"><?= e($msg['name']) ?></span>
                                        <span class="chat-text"><?= e($msg['message']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <form class="chat-form" id="chat-form">
                                <input type="text" name="message" placeholder="Send a message..." maxlength="200" autocomplete="off">
                                <button type="submit">Send</button>
                            </form>
                        </aside>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- No Active Stream -->
                <div class="no-stream">
                    <div class="no-stream-content">
                        <span class="no-stream-icon">📺</span>
                        <h1>No Live Stream</h1>
                        <p>There's no active livestream right now. Check back during service times or browse upcoming streams below.</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Upcoming Streams -->
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

            <!-- Past Streams (Recordings) -->
            <?php if ($pastStreams): ?>
                <section class="section">
                    <h2 class="section-title">Past Services</h2>
                    <div class="recordings-grid">
                        <?php foreach ($pastStreams as $stream): ?>
                            <a href="/media/livestream.php?id=<?= $stream['id'] ?>" class="recording-card">
                                <div class="recording-thumb">
                                    <?php if ($stream['thumbnail_url']): ?>
                                        <img src="<?= e($stream['thumbnail_url']) ?>" alt="">
                                    <?php else: ?>
                                        <div class="thumb-placeholder">📺</div>
                                    <?php endif; ?>
                                    <?php if ($stream['duration']): ?>
                                        <span class="duration"><?= formatDuration($stream['duration']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="recording-info">
                                    <h3><?= e($stream['title']) ?></h3>
                                    <p><?= date('M j, Y', strtotime($stream['ended_at'])) ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </main>

    <?php if ($livestream): ?>
    <script>
        const livestreamId = <?= $livestream['id'] ?>;
        const isLive = <?= $livestream['status'] === 'live' ? 'true' : 'false' ?>;
        const chatEnabled = <?= ($livestream['chat_enabled'] ?? false) ? 'true' : 'false' ?>;
    </script>
    <?php endif; ?>
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
