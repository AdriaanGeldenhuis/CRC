<?php
/**
 * CRC Morning Watch - View Session
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
$sessionId = (int)($_GET['id'] ?? 0);

if (!$sessionId) {
    Response::redirect('/morning_watch/');
}

// Get session
$session = Database::fetchOne(
    "SELECT ms.*, u.name as author_name
     FROM morning_sessions ms
     LEFT JOIN users u ON ms.created_by = u.id
     WHERE ms.id = ?
     AND (ms.scope = 'global' OR ms.congregation_id = ?)
     AND ms.published_at IS NOT NULL",
    [$sessionId, $primaryCong['id'] ?? 0]
);

if (!$session) {
    Response::redirect('/morning_watch/');
}

$pageTitle = e($session['title']) . " - Morning Watch";

// Get user's entry for this session
$userEntry = Database::fetchOne(
    "SELECT *, personal_notes as application, prayer_notes as prayer
     FROM morning_user_entries
     WHERE user_id = ? AND session_id = ?",
    [$user['id'], $sessionId]
);

// Parse prayer points
$prayerPoints = [];
if ($session['prayer_points']) {
    $prayerPoints = json_decode($session['prayer_points'], true) ?? [];
}

$isToday = $session['session_date'] === date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/morning_watch/css/morning_watch.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <!-- Header -->
            <div class="mw-header">
                <div class="mw-title">
                    <a href="/morning_watch/archive.php" class="back-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                        Back to Archive
                    </a>
                    <p class="session-date"><?= date('l, F j, Y', strtotime($session['session_date'])) ?></p>
                </div>
            </div>

            <!-- Devotional -->
            <div class="devotional-card">
                <div class="devotional-header">
                    <span class="devotional-badge"><?= ucfirst($session['scope']) ?></span>
                    <?php if ($session['theme']): ?>
                        <span class="devotional-theme"><?= e($session['theme']) ?></span>
                    <?php endif; ?>
                </div>

                <h2 class="devotional-title"><?= e($session['title']) ?></h2>

                <!-- Scripture -->
                <div class="scripture-section">
                    <div class="scripture-ref">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        <?= e($session['scripture_ref']) ?>
                        <span class="version-badge"><?= e($session['version_code'] ?? 'KJV') ?></span>
                    </div>
                    <blockquote class="scripture-text">
                        <?= nl2br(e($session['scripture_text'])) ?>
                    </blockquote>
                </div>

                <!-- Devotional Content -->
                <div class="devotional-content">
                    <h3>Reflection</h3>
                    <div class="content-text">
                        <?= nl2br(e($session['devotional'])) ?>
                    </div>
                </div>

                <!-- Prayer Points -->
                <?php if ($prayerPoints): ?>
                    <div class="prayer-section">
                        <h3>Prayer Points</h3>
                        <ul class="prayer-list">
                            <?php foreach ($prayerPoints as $point): ?>
                                <li><?= e($point) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($session['author_name']): ?>
                    <div class="devotional-author">
                        Written by <?= e($session['author_name']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- User Entry -->
            <?php if ($userEntry): ?>
                <div class="entry-card view-only">
                    <h3>Your Response</h3>

                    <?php if ($userEntry['reflection']): ?>
                        <div class="entry-section">
                            <h4>My Reflection</h4>
                            <p><?= nl2br(e($userEntry['reflection'])) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($userEntry['prayer']): ?>
                        <div class="entry-section">
                            <h4>My Prayer</h4>
                            <p><?= nl2br(e($userEntry['prayer'])) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($userEntry['application']): ?>
                        <div class="entry-section">
                            <h4>Application</h4>
                            <p><?= nl2br(e($userEntry['application'])) ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="entry-meta">
                        Completed on <?= date('M j, Y \a\t g:i A', strtotime($userEntry['created_at'])) ?>
                    </div>
                </div>
            <?php elseif ($isToday): ?>
                <div class="cta-card">
                    <p>You haven't completed today's devotional yet.</p>
                    <a href="/morning_watch/" class="btn btn-primary">Complete Today's Entry</a>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
