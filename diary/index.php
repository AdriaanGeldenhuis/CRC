<?php
/**
 * CRC Diary - Main Page
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$pageTitle = 'My Diary - CRC';

// Filters
$search = input('search');
$tag = input('tag');
$mood = input('mood');
$year = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? 0);

// Build query
$where = ['user_id = ?'];
$params = [$user['id']];

if ($search) {
    $where[] = "(title LIKE ? OR content LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($mood) {
    $where[] = "mood = ?";
    $params[] = $mood;
}

if ($month) {
    $where[] = "YEAR(entry_date) = ? AND MONTH(entry_date) = ?";
    $params[] = $year;
    $params[] = $month;
} else {
    $where[] = "YEAR(entry_date) = ?";
    $params[] = $year;
}

$whereClause = implode(' AND ', $where);

// Get entries
$entries = Database::fetchAll(
    "SELECT * FROM diary_entries
     WHERE $whereClause
     ORDER BY entry_date DESC, created_at DESC",
    $params
);

// Get tags for filter
$tags = Database::fetchAll(
    "SELECT DISTINCT t.name, COUNT(*) as count
     FROM diary_entry_tags det
     JOIN diary_tags t ON det.tag_id = t.id
     JOIN diary_entries e ON det.entry_id = e.id
     WHERE e.user_id = ?
     GROUP BY t.id
     ORDER BY count DESC
     LIMIT 20",
    [$user['id']]
);

// Get user's tags for entry
if ($tag) {
    $tagData = Database::fetchOne(
        "SELECT * FROM diary_tags WHERE name = ? AND user_id = ?",
        [$tag, $user['id']]
    );
    if ($tagData) {
        $entries = Database::fetchAll(
            "SELECT e.* FROM diary_entries e
             JOIN diary_entry_tags det ON e.id = det.entry_id
             WHERE det.tag_id = ? AND e.user_id = ?
             ORDER BY e.entry_date DESC",
            [$tagData['id'], $user['id']]
        );
    }
}

// Stats
$totalEntries = Database::fetchColumn(
    "SELECT COUNT(*) FROM diary_entries WHERE user_id = ?",
    [$user['id']]
);

$streak = Database::fetchColumn(
    "SELECT COUNT(DISTINCT DATE(entry_date)) FROM diary_entries
     WHERE user_id = ? AND entry_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
    [$user['id']]
);

$moods = ['grateful', 'joyful', 'peaceful', 'hopeful', 'anxious', 'sad', 'angry', 'confused'];
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
                    <h1>My Diary</h1>
                    <p>Your private space for reflection and journaling</p>
                </div>
                <div class="diary-actions">
                    <a href="/diary/prayers.php" class="btn btn-outline">Prayer Journal</a>
                    <a href="/diary/entry.php" class="btn btn-primary">+ New Entry</a>
                </div>
            </div>

            <div class="diary-stats">
                <div class="stat-card">
                    <span class="stat-value"><?= number_format($totalEntries) ?></span>
                    <span class="stat-label">Total Entries</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?= $streak ?></span>
                    <span class="stat-label">Days This Week</span>
                </div>
            </div>

            <div class="diary-layout">
                <aside class="diary-sidebar">
                    <!-- Search -->
                    <div class="sidebar-section">
                        <form method="get" class="search-form">
                            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search entries...">
                            <button type="submit">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                </svg>
                            </button>
                        </form>
                    </div>

                    <!-- Filter by Mood -->
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

                    <!-- Tags -->
                    <?php if ($tags): ?>
                        <div class="sidebar-section">
                            <h3>Tags</h3>
                            <div class="tag-cloud">
                                <?php foreach ($tags as $t): ?>
                                    <a href="?tag=<?= urlencode($t['name']) ?>" class="tag <?= $tag === $t['name'] ? 'active' : '' ?>">
                                        <?= e($t['name']) ?>
                                        <span class="tag-count"><?= $t['count'] ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Archive -->
                    <div class="sidebar-section">
                        <h3>Archive</h3>
                        <div class="archive-links">
                            <?php
                            $archives = Database::fetchAll(
                                "SELECT YEAR(entry_date) as year, MONTH(entry_date) as month, COUNT(*) as count
                                 FROM diary_entries WHERE user_id = ?
                                 GROUP BY year, month
                                 ORDER BY year DESC, month DESC
                                 LIMIT 12",
                                [$user['id']]
                            );
                            foreach ($archives as $archive):
                                $monthName = date('F', mktime(0, 0, 0, $archive['month'], 1));
                            ?>
                                <a href="?year=<?= $archive['year'] ?>&month=<?= $archive['month'] ?>">
                                    <?= $monthName ?> <?= $archive['year'] ?>
                                    <span><?= $archive['count'] ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </aside>

                <div class="diary-main">
                    <?php if (empty($entries)): ?>
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                            <h3>No entries yet</h3>
                            <p>Start writing to capture your thoughts and reflections</p>
                            <a href="/diary/entry.php" class="btn btn-primary">Write Your First Entry</a>
                        </div>
                    <?php else: ?>
                        <div class="entries-grid">
                            <?php foreach ($entries as $entry): ?>
                                <a href="/diary/entry.php?id=<?= $entry['id'] ?>" class="entry-card">
                                    <div class="entry-date">
                                        <span class="day"><?= date('d', strtotime($entry['entry_date'])) ?></span>
                                        <span class="month"><?= date('M', strtotime($entry['entry_date'])) ?></span>
                                    </div>
                                    <div class="entry-content">
                                        <h3><?= e($entry['title'] ?: 'Untitled Entry') ?></h3>
                                        <p><?= e(truncate(strip_tags($entry['content']), 120)) ?></p>
                                        <?php if ($entry['mood']): ?>
                                            <span class="entry-mood"><?= getMoodEmoji($entry['mood']) ?> <?= ucfirst($entry['mood']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="/diary/js/diary.js"></script>
</body>
</html>
<?php

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
