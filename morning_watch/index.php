<?php
/**
 * CRC Morning Watch - Today's Devotional
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
$pageTitle = "Morning Watch - CRC";

$today = date('Y-m-d');

// Get today's session (global or congregation-specific)
$session = Database::fetchOne(
    "SELECT ms.*, u.name as author_name
     FROM morning_sessions ms
     LEFT JOIN users u ON ms.created_by = u.id
     WHERE ms.session_date = ?
     AND (ms.scope = 'global' OR ms.congregation_id = ?)
     AND ms.published_at IS NOT NULL
     ORDER BY ms.scope = 'congregation' DESC
     LIMIT 1",
    [$today, $primaryCong['id'] ?? 0]
);

// Get user's entry for today
$userEntry = null;
if ($session) {
    $userEntry = Database::fetchOne(
        "SELECT * FROM morning_watch_entries
         WHERE user_id = ? AND session_id = ?",
        [$user['id'], $session['id']]
    );
}

// Get user's streak
$streak = Database::fetchOne(
    "SELECT current_streak, longest_streak, total_entries
     FROM morning_watch_streaks
     WHERE user_id = ?",
    [$user['id']]
);

// Parse prayer points
$prayerPoints = [];
if ($session && $session['prayer_points']) {
    $prayerPoints = json_decode($session['prayer_points'], true) ?? [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
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
                    <h1>Morning Watch</h1>
                    <p><?= date('l, F j, Y') ?></p>
                </div>
                <div class="mw-actions">
                    <a href="/morning_watch/archive.php" class="btn btn-outline">View Archive</a>
                </div>
            </div>

            <!-- Streak Info -->
            <div class="streak-bar">
                <div class="streak-item">
                    <span class="streak-icon">🔥</span>
                    <span class="streak-value"><?= $streak['current_streak'] ?? 0 ?></span>
                    <span class="streak-label">Day Streak</span>
                </div>
                <div class="streak-item">
                    <span class="streak-icon">🏆</span>
                    <span class="streak-value"><?= $streak['longest_streak'] ?? 0 ?></span>
                    <span class="streak-label">Best Streak</span>
                </div>
                <div class="streak-item">
                    <span class="streak-icon">📖</span>
                    <span class="streak-value"><?= $streak['total_entries'] ?? 0 ?></span>
                    <span class="streak-label">Total Days</span>
                </div>
            </div>

            <?php if ($session): ?>
                <!-- Today's Devotional -->
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
                        <h3>Today's Reflection</h3>
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

                <!-- User Entry Form -->
                <div class="entry-card">
                    <h3><?= $userEntry ? 'Your Response' : 'Your Turn' ?></h3>
                    <p class="entry-subtitle">Record what God is speaking to you today</p>

                    <form id="entry-form" data-session-id="<?= $session['id'] ?>">
                        <div class="form-group">
                            <label for="reflection">My Reflection</label>
                            <textarea id="reflection" name="reflection" rows="4"
                                      placeholder="What stood out to you from today's reading?"><?= e($userEntry['reflection'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="prayer">My Prayer</label>
                            <textarea id="prayer" name="prayer" rows="3"
                                      placeholder="Write your prayer response..."><?= e($userEntry['prayer'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="application">Application</label>
                            <textarea id="application" name="application" rows="2"
                                      placeholder="How will you apply this today?"><?= e($userEntry['application'] ?? '') ?></textarea>
                        </div>

                        <div class="form-actions">
                            <?php if ($userEntry): ?>
                                <span class="saved-indicator">✓ Saved at <?= date('g:i A', strtotime($userEntry['updated_at'])) ?></span>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary">
                                <?= $userEntry ? 'Update Entry' : 'Save Entry' ?>
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- No Devotional Today -->
                <div class="empty-state">
                    <div class="empty-icon">☀️</div>
                    <h2>No devotional for today yet</h2>
                    <p>Check back later or explore the archive for past devotionals.</p>
                    <a href="/morning_watch/archive.php" class="btn btn-primary">View Archive</a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="/morning_watch/js/morning_watch.js"></script>
</body>
</html>
