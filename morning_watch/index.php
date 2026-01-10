<?php
/**
 * CRC Morning Study - Dashboard Layout
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
$pageTitle = "Morning Study - CRC";

$today = date('Y-m-d');
$session = null;
$userEntry = null;
$streak = null;
$recentSessions = [];

// Get today's session
try {
    $session = Database::fetchOne(
        "SELECT ms.*, u.name as author_name FROM morning_sessions ms
         LEFT JOIN users u ON ms.created_by = u.id
         WHERE ms.session_date = ? AND (ms.scope = 'global' OR ms.congregation_id = ?)
         AND ms.published_at IS NOT NULL
         ORDER BY ms.scope = 'congregation' DESC LIMIT 1",
        [$today, $primaryCong['id'] ?? 0]
    );
} catch (Exception $e) {}

// Get user's entry for today
if ($session) {
    try {
        $userEntry = Database::fetchOne(
            "SELECT * FROM morning_user_entries WHERE user_id = ? AND session_id = ?",
            [$user['id'], $session['id']]
        );
    } catch (Exception $e) {}
}

// Get user's streak
try {
    $streak = Database::fetchOne(
        "SELECT current_streak, longest_streak, total_completions FROM morning_streaks WHERE user_id = ?",
        [$user['id']]
    );
} catch (Exception $e) {}

// Get recent sessions
try {
    $recentSessions = Database::fetchAll(
        "SELECT * FROM morning_sessions
         WHERE (scope = 'global' OR congregation_id = ?) AND published_at IS NOT NULL
         AND session_date < CURDATE()
         ORDER BY session_date DESC LIMIT 5",
        [$primaryCong['id'] ?? 0]
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
    <style>
        .study-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: var(--white);
        }
        .study-card .card-header h2 { color: var(--white); }
        .streak-row {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .streak-box {
            flex: 1;
            background: rgba(255,255,255,0.15);
            padding: 0.75rem;
            border-radius: var(--radius);
            text-align: center;
        }
        .streak-box .icon { font-size: 1.25rem; }
        .streak-box .value { font-size: 1.25rem; font-weight: 700; }
        .streak-box .label { font-size: 0.65rem; opacity: 0.9; }
        .scripture-box {
            background: rgba(255,255,255,0.1);
            padding: 1rem;
            border-radius: var(--radius);
            margin-top: 1rem;
            font-family: 'Merriweather', serif;
            font-style: italic;
            line-height: 1.6;
        }
        .scripture-ref {
            font-size: 0.875rem;
            font-style: normal;
            opacity: 0.9;
            margin-top: 0.5rem;
        }
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
        .session-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .session-item {
            display: flex;
            gap: 1rem;
            padding: 0.75rem;
            background: var(--gray-50);
            border-radius: var(--radius);
            text-decoration: none;
            transition: var(--transition);
        }
        .session-item:hover { background: var(--gray-100); }
        .session-date {
            min-width: 48px;
            padding: 0.5rem;
            background: var(--primary);
            color: white;
            border-radius: var(--radius);
            text-align: center;
        }
        .session-date .day { font-size: 1.25rem; font-weight: 700; line-height: 1; }
        .session-date .month { font-size: 0.7rem; text-transform: uppercase; }
        .session-info h4 { font-size: 0.875rem; color: var(--gray-800); margin-bottom: 0.25rem; }
        .session-info p { font-size: 0.75rem; color: var(--gray-500); }
        .notes-form textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            resize: none;
            font-family: inherit;
            margin-bottom: 0.5rem;
        }
        .completed-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255,255,255,0.2);
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <section class="welcome-section">
                <div class="welcome-content">
                    <h1>Morning Study</h1>
                    <p><?= date('l, F j, Y') ?></p>
                </div>
            </section>

            <div class="dashboard-grid">
                <!-- Today's Study Card -->
                <div class="dashboard-card study-card">
                    <div class="card-header">
                        <h2><?= $session ? e($session['title']) : "Today's Study" ?></h2>
                        <?php if ($userEntry && $userEntry['completed_at']): ?>
                            <span class="completed-badge">✓ Done</span>
                        <?php endif; ?>
                    </div>
                    <div class="streak-row">
                        <div class="streak-box">
                            <div class="icon">🔥</div>
                            <div class="value"><?= $streak['current_streak'] ?? 0 ?></div>
                            <div class="label">Streak</div>
                        </div>
                        <div class="streak-box">
                            <div class="icon">🏆</div>
                            <div class="value"><?= $streak['longest_streak'] ?? 0 ?></div>
                            <div class="label">Best</div>
                        </div>
                        <div class="streak-box">
                            <div class="icon">📖</div>
                            <div class="value"><?= $streak['total_completions'] ?? 0 ?></div>
                            <div class="label">Total</div>
                        </div>
                    </div>
                    <?php if ($session): ?>
                        <div class="scripture-box">
                            <?= e(truncate($session['scripture_text'] ?? '', 200)) ?>
                            <div class="scripture-ref">— <?= e($session['scripture_ref']) ?></div>
                        </div>
                        <a href="/morning_watch/session.php?id=<?= $session['id'] ?>" class="btn" style="width: 100%; margin-top: 1rem; background: white; color: #667eea;">
                            <?= $userEntry ? 'Continue Study' : 'Start Today\'s Study' ?>
                        </a>
                    <?php else: ?>
                        <div style="text-align: center; padding: 2rem;">
                            <p>No study session for today yet.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-card">
                    <h2 style="margin-bottom: 1rem;">Quick Actions</h2>
                    <div class="quick-actions-grid">
                        <a href="/morning_watch/archive.php" class="quick-action">
                            <span class="quick-action-icon">📚</span>
                            <span>Archive</span>
                        </a>
                        <a href="/morning_watch/my-notes.php" class="quick-action">
                            <span class="quick-action-icon">📝</span>
                            <span>My Notes</span>
                        </a>
                        <a href="/bible/" class="quick-action">
                            <span class="quick-action-icon">📖</span>
                            <span>Bible</span>
                        </a>
                        <a href="/morning_watch/stats.php" class="quick-action">
                            <span class="quick-action-icon">📊</span>
                            <span>Stats</span>
                        </a>
                    </div>
                </div>

                <!-- My Notes Card -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>Quick Notes</h2>
                    </div>
                    <form id="quick-notes-form">
                        <div class="notes-form">
                            <textarea placeholder="What is God speaking to you today?" rows="4"><?= e($userEntry['reflection'] ?? '') ?></textarea>
                            <button type="submit" class="btn btn-primary" style="width: 100%;">Save Notes</button>
                        </div>
                    </form>
                </div>

                <!-- Recent Sessions -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>Past Sessions</h2>
                        <a href="/morning_watch/archive.php" class="view-all-link">View All</a>
                    </div>
                    <?php if ($recentSessions): ?>
                        <div class="session-list">
                            <?php foreach ($recentSessions as $s): ?>
                                <a href="/morning_watch/session.php?id=<?= $s['id'] ?>" class="session-item">
                                    <div class="session-date">
                                        <div class="day"><?= date('d', strtotime($s['session_date'])) ?></div>
                                        <div class="month"><?= date('M', strtotime($s['session_date'])) ?></div>
                                    </div>
                                    <div class="session-info">
                                        <h4><?= e($s['title']) ?></h4>
                                        <p><?= e($s['scripture_ref']) ?></p>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 2rem; color: var(--gray-500);">
                            <p>No past sessions yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
