<?php
/**
 * CRC Diary - Dashboard Layout
 */

require_once __DIR__ . '/../core/bootstrap.php';

header('Content-Type: text/html; charset=UTF-8');

Auth::requireAuth();

function getMoodEmoji($mood) {
    $emojis = [
        'grateful' => '🙏', 'joyful' => '😊', 'peaceful' => '😌', 'hopeful' => '🌟',
        'anxious' => '😰', 'sad' => '😢', 'angry' => '😤', 'confused' => '😕'
    ];
    return $emojis[$mood] ?? '📝';
}

$user = Auth::user();
$pageTitle = 'My Diary - CRC';

$entries = [];
$recentEntries = [];
$totalEntries = 0;
$streak = 0;
$thisWeekCount = 0;
$moods = ['grateful', 'joyful', 'peaceful', 'hopeful', 'anxious', 'sad', 'angry', 'confused'];

// Get total entries count
try {
    $totalEntries = Database::fetchColumn("SELECT COUNT(*) FROM diary_entries WHERE user_id = ?", [$user['id']]) ?: 0;
} catch (Exception $e) {}

// Get this week entries
try {
    $thisWeekCount = Database::fetchColumn(
        "SELECT COUNT(*) FROM diary_entries WHERE user_id = ? AND entry_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
        [$user['id']]
    ) ?: 0;
} catch (Exception $e) {}

// Get recent entries
try {
    $recentEntries = Database::fetchAll(
        "SELECT * FROM diary_entries WHERE user_id = ? ORDER BY entry_date DESC, created_at DESC LIMIT 5",
        [$user['id']]
    ) ?: [];
} catch (Exception $e) {}

// Get mood distribution this month
$moodStats = [];
try {
    $moodStats = Database::fetchAll(
        "SELECT mood, COUNT(*) as count FROM diary_entries
         WHERE user_id = ? AND entry_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND mood IS NOT NULL
         GROUP BY mood ORDER BY count DESC LIMIT 4",
        [$user['id']]
    ) ?: [];
} catch (Exception $e) {}

// Get prayer requests
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
    <style>
        .diary-card {
            background: linear-gradient(135deg, #7C3AED 0%, #A78BFA 100%);
            color: var(--white);
        }
        .diary-card .card-header h2 { color: var(--white); }
        .stats-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .stat-box {
            flex: 1;
            background: rgba(255,255,255,0.15);
            padding: 1rem;
            border-radius: var(--radius);
            text-align: center;
        }
        .stat-box .value { font-size: 1.5rem; font-weight: 700; }
        .stat-box .label { font-size: 0.75rem; opacity: 0.9; }
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
        .entry-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .entry-item {
            display: flex;
            gap: 1rem;
            padding: 0.75rem;
            background: var(--gray-50);
            border-radius: var(--radius);
            text-decoration: none;
            transition: var(--transition);
        }
        .entry-item:hover { background: var(--gray-100); }
        .entry-date {
            min-width: 48px;
            padding: 0.5rem;
            background: var(--primary);
            color: white;
            border-radius: var(--radius);
            text-align: center;
        }
        .entry-date .day { font-size: 1.25rem; font-weight: 700; line-height: 1; }
        .entry-date .month { font-size: 0.7rem; text-transform: uppercase; }
        .entry-info h4 { font-size: 0.875rem; color: var(--gray-800); margin-bottom: 0.25rem; }
        .entry-info p { font-size: 0.75rem; color: var(--gray-500); }
        .mood-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
        }
        .mood-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            background: var(--gray-50);
            border-radius: var(--radius);
        }
        .mood-emoji { font-size: 1.25rem; }
        .mood-count { font-weight: 600; color: var(--primary); }
        .prayer-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .prayer-item {
            padding: 0.75rem;
            background: var(--gray-50);
            border-radius: var(--radius);
            font-size: 0.875rem;
            color: var(--gray-700);
        }
        .prayer-item.answered { border-left: 3px solid var(--success); }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <section class="welcome-section">
                <div class="welcome-content">
                    <h1>My Diary</h1>
                    <p>Your private space for reflection and journaling</p>
                </div>
            </section>

            <div class="dashboard-grid">
                <!-- Stats Card -->
                <div class="dashboard-card diary-card">
                    <div class="card-header">
                        <h2>Your Journey</h2>
                    </div>
                    <div class="stats-row">
                        <div class="stat-box">
                            <div class="value"><?= number_format($totalEntries) ?></div>
                            <div class="label">Total Entries</div>
                        </div>
                        <div class="stat-box">
                            <div class="value"><?= $thisWeekCount ?></div>
                            <div class="label">This Week</div>
                        </div>
                    </div>
                    <a href="/diary/entry.php" class="btn btn-primary" style="width: 100%; background: white; color: #7C3AED;">+ Write New Entry</a>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-card">
                    <h2 style="margin-bottom: 1rem;">Quick Actions</h2>
                    <div class="quick-actions-grid">
                        <a href="/diary/entry.php" class="quick-action">
                            <span class="quick-action-icon">✏️</span>
                            <span>New Entry</span>
                        </a>
                        <a href="/diary/prayers.php" class="quick-action">
                            <span class="quick-action-icon">🙏</span>
                            <span>Prayers</span>
                        </a>
                        <a href="/diary/archive.php" class="quick-action">
                            <span class="quick-action-icon">📚</span>
                            <span>Archive</span>
                        </a>
                        <a href="/diary/gratitude.php" class="quick-action">
                            <span class="quick-action-icon">💜</span>
                            <span>Gratitude</span>
                        </a>
                    </div>
                </div>

                <!-- Recent Entries -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>Recent Entries</h2>
                        <a href="/diary/all.php" class="view-all-link">View All</a>
                    </div>
                    <?php if ($recentEntries): ?>
                        <div class="entry-list">
                            <?php foreach ($recentEntries as $entry): ?>
                                <a href="/diary/entry.php?id=<?= $entry['id'] ?>" class="entry-item">
                                    <div class="entry-date">
                                        <div class="day"><?= date('d', strtotime($entry['entry_date'])) ?></div>
                                        <div class="month"><?= date('M', strtotime($entry['entry_date'])) ?></div>
                                    </div>
                                    <div class="entry-info">
                                        <h4><?= e($entry['title'] ?: 'Untitled Entry') ?></h4>
                                        <p><?= e(truncate(strip_tags($entry['content'] ?? ''), 60)) ?></p>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 2rem; color: var(--gray-500);">
                            <p>No entries yet</p>
                            <a href="/diary/entry.php" class="btn btn-primary" style="margin-top: 0.5rem;">Write First Entry</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Mood & Prayers -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>This Month</h2>
                    </div>
                    <?php if ($moodStats): ?>
                        <h4 style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 0.5rem;">Mood Tracker</h4>
                        <div class="mood-grid">
                            <?php foreach ($moodStats as $ms): ?>
                                <div class="mood-item">
                                    <span class="mood-emoji"><?= getMoodEmoji($ms['mood']) ?></span>
                                    <span><?= ucfirst($ms['mood']) ?></span>
                                    <span class="mood-count"><?= $ms['count'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($prayers): ?>
                        <h4 style="font-size: 0.875rem; color: var(--gray-600); margin: 1rem 0 0.5rem;">Recent Prayers</h4>
                        <div class="prayer-list">
                            <?php foreach ($prayers as $p): ?>
                                <div class="prayer-item <?= $p['answered_at'] ? 'answered' : '' ?>">
                                    <?= e(truncate($p['content'], 50)) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 1rem; color: var(--gray-500);">
                            <a href="/diary/prayers.php" class="btn btn-outline">Add Prayer Request</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
