<?php
/**
 * Diary Test 4 - Full structure test
 */

require_once __DIR__ . '/../core/bootstrap.php';

header('Content-Type: text/html; charset=UTF-8');

Auth::requireAuth();

function getMoodEmoji($mood) {
    $emojis = [
        'grateful' => '🙏',
        'joyful' => '😊',
        'peaceful' => '😌',
        'hopeful' => '🌟',
        'anxious' => '😰',
        'sad' => '😢',
        'angry' => '😤',
        'confused' => '😕'
    ];
    return $emojis[$mood] ?? '📝';
}

$user = Auth::user();
$pageTitle = 'My Diary - CRC';

$search = input('search');
$tag = input('tag');
$mood = input('mood');

$entries = [];
$tags = [];
$totalEntries = 0;
$moods = ['grateful', 'joyful', 'peaceful', 'hopeful', 'anxious', 'sad', 'angry', 'confused'];

try {
    $totalEntries = Database::fetchColumn(
        "SELECT COUNT(*) FROM diary_entries WHERE user_id = ?",
        [$user['id']]
    ) ?: 0;
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/diary/css/diary.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="diary-header">
                <div class="diary-title">
                    <h1>My Diary (TEST 4)</h1>
                    <p>Your private space for reflection and journaling</p>
                </div>
            </div>

            <div class="diary-stats">
                <div class="stat-card">
                    <span class="stat-value"><?= number_format($totalEntries) ?></span>
                    <span class="stat-label">Total Entries</span>
                </div>
            </div>

            <div class="diary-layout">
                <aside class="diary-sidebar">
                    <div class="sidebar-section">
                        <h3>Filter by Mood</h3>
                        <div class="mood-filters">
                            <a href="?" class="mood-btn <?= !$mood ? 'active' : '' ?>">All</a>
                            <?php foreach ($moods as $m): ?>
                                <a href="?mood=<?= $m ?>" class="mood-btn <?= $mood === $m ? 'active' : '' ?>">
                                    <?= getMoodEmoji($m) ?> <?= ucfirst($m) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </aside>

                <div class="diary-main">
                    <div class="empty-state">
                        <h3>Test 4 Works!</h3>
                        <p>This page has: navbar, database, emojis, CSRF, and mood filters</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="/diary/js/diary.js"></script>
</body>
</html>
