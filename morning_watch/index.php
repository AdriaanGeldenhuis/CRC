<?php
/**
 * CRC Morning Study - Today's Session
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
$pageTitle = "Morning Study - CRC";

$today = date('Y-m-d');

// Initialize with defaults
$session = null;
$userEntry = null;
$streak = null;
$prayerPoints = [];
$studyQuestions = [];
$hasRecap = false;

// Get today's session (global or congregation-specific)
try {
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
} catch (Exception $e) {}

// Get user's entry for today
if ($session) {
    try {
        $userEntry = Database::fetchOne(
            "SELECT *, personal_notes as application, prayer_notes as prayer
             FROM morning_user_entries
             WHERE user_id = ? AND session_id = ?",
            [$user['id'], $session['id']]
        );
    } catch (Exception $e) {}

    // Check if recap exists
    try {
        $recap = Database::fetchOne(
            "SELECT id FROM morning_study_recaps WHERE session_id = ?",
            [$session['id']]
        );
        $hasRecap = $recap ? true : false;
    } catch (Exception $e) {}
}

// Get user's streak
try {
    $streak = Database::fetchOne(
        "SELECT current_streak, longest_streak, total_completions as total_entries
         FROM morning_streaks
         WHERE user_id = ?",
        [$user['id']]
    );
} catch (Exception $e) {}

// Parse prayer points and study questions
if ($session && !empty($session['prayer_points'])) {
    $prayerPoints = json_decode($session['prayer_points'], true) ?? [];
}
if ($session && !empty($session['study_questions'])) {
    $studyQuestions = json_decode($session['study_questions'], true) ?? [];
}

// Check if this is a live study session
$isStudyMode = $session && ($session['content_mode'] ?? 'watch') === 'study';
$isLive = $session && ($session['live_status'] ?? 'scheduled') === 'live';
$isEnded = $session && ($session['live_status'] ?? 'scheduled') === 'ended';
$hasStream = $session && ($session['stream_url'] || $session['replay_url']);
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
    <style>
        .live-study-card { background: linear-gradient(135deg, #1E1B4B 0%, #312E81 100%); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; color: white; }
        .live-study-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        .live-badge { display: inline-flex; align-items: center; gap: 0.5rem; background: #EF4444; padding: 0.375rem 0.75rem; border-radius: 6px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; }
        .live-badge.ended { background: #6B7280; }
        .live-badge.scheduled { background: #F59E0B; }
        .live-badge::before { content: ''; width: 8px; height: 8px; background: white; border-radius: 50%; animation: pulse 1.5s infinite; }
        .live-badge.ended::before, .live-badge.scheduled::before { animation: none; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .live-study-title { font-size: 1.5rem; font-weight: 700; margin: 0 0 0.5rem; }
        .live-study-scripture { color: #A5B4FC; font-size: 0.95rem; }
        .live-study-key-verse { background: rgba(255,255,255,0.1); padding: 0.75rem 1rem; border-radius: 8px; margin-top: 1rem; border-left: 3px solid #6366F1; font-style: italic; }
        .live-study-questions { margin-top: 1rem; }
        .live-study-questions h4 { font-size: 0.8rem; text-transform: uppercase; color: #A5B4FC; margin: 0 0 0.5rem; letter-spacing: 0.05em; }
        .live-study-questions ul { margin: 0; padding-left: 1.25rem; }
        .live-study-questions li { margin-bottom: 0.375rem; color: #E0E7FF; font-size: 0.9rem; }
        .live-study-actions { display: flex; gap: 0.75rem; margin-top: 1.25rem; flex-wrap: wrap; }
        .btn-live { background: white; color: #312E81; font-weight: 600; }
        .btn-live:hover { background: #E0E7FF; }
        .btn-recap { background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.3); }
        .btn-recap:hover { background: rgba(255,255,255,0.25); }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <!-- Header -->
            <div class="mw-header">
                <div class="mw-title">
                    <h1>Morning Study</h1>
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
                <?php if ($isStudyMode && $hasStream): ?>
                    <!-- Live Study Card -->
                    <div class="live-study-card">
                        <div class="live-study-header">
                            <?php if ($isLive): ?>
                                <span class="live-badge">Live Now</span>
                            <?php elseif ($isEnded): ?>
                                <span class="live-badge ended">Replay Available</span>
                            <?php else: ?>
                                <span class="live-badge scheduled">
                                    <?= $session['live_starts_at'] ? 'Starts ' . date('g:i A', strtotime($session['live_starts_at'])) : 'Coming Soon' ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <h2 class="live-study-title"><?= e($session['title']) ?></h2>
                        <p class="live-study-scripture"><?= e($session['scripture_ref']) ?></p>

                        <?php if ($session['key_verse']): ?>
                            <div class="live-study-key-verse">
                                <strong>Key Verse:</strong> <?= e($session['key_verse']) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($studyQuestions): ?>
                            <div class="live-study-questions">
                                <h4>Today's Study Questions</h4>
                                <ul>
                                    <?php foreach (array_slice($studyQuestions, 0, 5) as $q): ?>
                                        <li><?= e($q) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <div class="live-study-actions">
                            <a href="/morning_watch/room.php?session_id=<?= $session['id'] ?>" class="btn btn-live">
                                <?php if ($isLive): ?>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                                    Join Live Room
                                <?php elseif ($isEnded): ?>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                                    Watch Replay
                                <?php else: ?>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                    Enter Waiting Room
                                <?php endif; ?>
                            </a>
                            <?php if ($hasRecap): ?>
                                <a href="/morning_watch/recap.php?session_id=<?= $session['id'] ?>" class="btn btn-recap">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                                    View Recap
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Today's Devotional -->
                <div class="devotional-card">
                    <div class="devotional-header">
                        <span class="devotional-badge"><?= $isStudyMode ? 'Study' : ucfirst($session['scope']) ?></span>
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
                        <?php if ($session['scripture_text']): ?>
                            <blockquote class="scripture-text">
                                <?= nl2br(e($session['scripture_text'])) ?>
                            </blockquote>
                        <?php endif; ?>
                    </div>

                    <!-- Devotional Content (for watch mode) -->
                    <?php if ($session['devotional']): ?>
                        <div class="devotional-content">
                            <h3>Today's Reflection</h3>
                            <div class="content-text">
                                <?= nl2br(e($session['devotional'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>

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

                <!-- User Entry Form (Private Notes) -->
                <div class="entry-card">
                    <h3><?= $userEntry ? 'Your Private Notes' : 'My Notes (Private)' ?></h3>
                    <p class="entry-subtitle">Record what God is speaking to you today</p>

                    <form id="entry-form" data-session-id="<?= $session['id'] ?>">
                        <div class="form-group">
                            <label for="reflection">My Reflection</label>
                            <textarea id="reflection" name="reflection" rows="4"
                                      placeholder="What stood out to you from today's reading?"><?= e($userEntry['reflection'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="prayer">My Prayer Notes</label>
                            <textarea id="prayer" name="prayer" rows="3"
                                      placeholder="Write your prayer response..."><?= e($userEntry['prayer'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="application">Personal Application</label>
                            <textarea id="application" name="application" rows="2"
                                      placeholder="How will you apply this today?"><?= e($userEntry['application'] ?? '') ?></textarea>
                        </div>

                        <div class="form-actions">
                            <?php if ($userEntry): ?>
                                <span class="saved-indicator">✓ Saved at <?= date('g:i A', strtotime($userEntry['updated_at'])) ?></span>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary">
                                <?= $userEntry ? 'Update Notes' : 'Save Notes' ?>
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- No Session Today -->
                <div class="empty-state">
                    <div class="empty-icon">☀️</div>
                    <h2>No study session for today yet</h2>
                    <p>Check back later or explore the archive for past sessions.</p>
                    <a href="/morning_watch/archive.php" class="btn btn-primary">View Archive</a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="/morning_watch/js/morning_watch.js"></script>
</body>
</html>
