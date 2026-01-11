<?php
/**
 * CRC Diary - Main Page
 * Premium OAC-style dark theme with mini calendar
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

function getMoodColor($mood) {
    $colors = [
        'grateful' => '#8B5CF6',
        'joyful' => '#F59E0B',
        'peaceful' => '#06B6D4',
        'hopeful' => '#10B981',
        'anxious' => '#EF4444',
        'sad' => '#6366F1',
        'angry' => '#DC2626',
        'confused' => '#F97316'
    ];
    return $colors[$mood] ?? '#8B5CF6';
}

$user = Auth::user();
$pageTitle = 'My Diary - CRC';

// Get filters from URL
$search = input('search');
$tag = input('tag');
$mood = input('mood');
$calYear = (int)($_GET['cal_year'] ?? date('Y'));
$calMonth = (int)($_GET['cal_month'] ?? date('n'));
$filterYear = (int)($_GET['year'] ?? 0);
$filterMonth = (int)($_GET['month'] ?? 0);

// Initialize variables
$entries = [];
$tags = [];
$totalEntries = 0;
$streak = 0;
$longestStreak = 0;
$archives = [];
$entryDates = [];
$moods = ['grateful', 'joyful', 'peaceful', 'hopeful', 'anxious', 'sad', 'angry', 'confused'];

// Get total entries count
try {
    $totalEntries = Database::fetchColumn(
        "SELECT COUNT(*) FROM diary_entries WHERE user_id = ?",
        [$user['id']]
    ) ?: 0;
} catch (Exception $e) {
    $totalEntries = 0;
}

// Get streak (days this week)
try {
    $streak = Database::fetchColumn(
        "SELECT COUNT(DISTINCT DATE(entry_date)) FROM diary_entries
         WHERE user_id = ? AND entry_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
        [$user['id']]
    ) ?: 0;
} catch (Exception $e) {
    $streak = 0;
}

// Get longest streak
try {
    $longestStreak = Database::fetchColumn(
        "SELECT COUNT(DISTINCT DATE(entry_date)) FROM diary_entries
         WHERE user_id = ? AND entry_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
        [$user['id']]
    ) ?: 0;
} catch (Exception $e) {
    $longestStreak = 0;
}

// Get entry dates for calendar highlighting
try {
    $entryDatesRaw = Database::fetchAll(
        "SELECT DATE(entry_date) as date FROM diary_entries
         WHERE user_id = ? AND YEAR(entry_date) = ? AND MONTH(entry_date) = ?",
        [$user['id'], $calYear, $calMonth]
    ) ?: [];
    foreach ($entryDatesRaw as $d) {
        $entryDates[] = $d['date'];
    }
} catch (Exception $e) {
    $entryDates = [];
}

// Get entries based on filters
try {
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

    if ($filterMonth && $filterYear) {
        $where[] = "YEAR(entry_date) = ? AND MONTH(entry_date) = ?";
        $params[] = $filterYear;
        $params[] = $filterMonth;
    } elseif ($filterYear) {
        $where[] = "YEAR(entry_date) = ?";
        $params[] = $filterYear;
    }

    $whereClause = implode(' AND ', $where);

    $entries = Database::fetchAll(
        "SELECT * FROM diary_entries WHERE $whereClause ORDER BY entry_date DESC, created_at DESC LIMIT 50",
        $params
    ) ?: [];
} catch (Exception $e) {
    $entries = [];
}

// Get tags if filtering by tag
if ($tag) {
    try {
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
            ) ?: [];
        }
    } catch (Exception $e) {
        // Keep existing entries
    }
}

// Get user's tags for sidebar
try {
    $tags = Database::fetchAll(
        "SELECT DISTINCT t.name, COUNT(*) as count
         FROM diary_entry_tags det
         JOIN diary_tags t ON det.tag_id = t.id
         JOIN diary_entries e ON det.entry_id = e.id
         WHERE e.user_id = ?
         GROUP BY t.id, t.name
         ORDER BY count DESC
         LIMIT 15",
        [$user['id']]
    ) ?: [];
} catch (Exception $e) {
    $tags = [];
}

// Get archives for sidebar
try {
    $archives = Database::fetchAll(
        "SELECT YEAR(entry_date) as year, MONTH(entry_date) as month, COUNT(*) as count
         FROM diary_entries WHERE user_id = ?
         GROUP BY YEAR(entry_date), MONTH(entry_date)
         ORDER BY year DESC, month DESC
         LIMIT 12",
        [$user['id']]
    ) ?: [];
} catch (Exception $e) {
    $archives = [];
}

// Calendar helper
function buildCalendar($year, $month, $entryDates) {
    $firstDay = mktime(0, 0, 0, $month, 1, $year);
    $daysInMonth = date('t', $firstDay);
    $startDay = date('w', $firstDay); // 0=Sunday
    $today = date('Y-m-d');

    $prevMonth = $month - 1;
    $prevYear = $year;
    if ($prevMonth < 1) {
        $prevMonth = 12;
        $prevYear--;
    }

    $nextMonth = $month + 1;
    $nextYear = $year;
    if ($nextMonth > 12) {
        $nextMonth = 1;
        $nextYear++;
    }

    $html = '<div class="mini-calendar">';
    $html .= '<div class="calendar-header">';
    $html .= '<a href="?cal_year=' . $prevYear . '&cal_month=' . $prevMonth . '" class="calendar-nav">&lsaquo;</a>';
    $html .= '<span class="calendar-title">' . date('F Y', $firstDay) . '</span>';
    $html .= '<a href="?cal_year=' . $nextYear . '&cal_month=' . $nextMonth . '" class="calendar-nav">&rsaquo;</a>';
    $html .= '</div>';

    $html .= '<div class="calendar-grid">';
    $html .= '<div class="calendar-weekdays">';
    foreach (['S', 'M', 'T', 'W', 'T', 'F', 'S'] as $day) {
        $html .= '<span>' . $day . '</span>';
    }
    $html .= '</div>';

    $html .= '<div class="calendar-days">';

    // Empty cells before first day
    for ($i = 0; $i < $startDay; $i++) {
        $html .= '<span class="calendar-day empty"></span>';
    }

    // Days of month
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $classes = ['calendar-day'];

        if ($date === $today) {
            $classes[] = 'today';
        }
        if (in_array($date, $entryDates)) {
            $classes[] = 'has-entry';
        }

        $html .= '<a href="?year=' . $year . '&month=' . $month . '&day=' . $day . '" class="' . implode(' ', $classes) . '">' . $day . '</a>';
    }

    $html .= '</div></div></div>';

    return $html;
}
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
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Parisienne&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <!-- Premium Header -->
            <div class="diary-header">
                <div class="diary-title">
                    <h1 class="display-title">My Diary</h1>
                    <p class="subtitle">Your private space for reflection and spiritual journaling</p>
                </div>
                <div class="diary-actions">
                    <a href="/diary/prayers.php" class="btn btn-outline">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                        </svg>
                        Prayer Journal
                    </a>
                    <a href="/diary/entry.php" class="btn btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        New Entry
                    </a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="diary-stats">
                <div class="stat-card">
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                    </div>
                    <span class="stat-value"><?= number_format($totalEntries) ?></span>
                    <span class="stat-label">Total Entries</span>
                </div>
                <div class="stat-card">
                    <div class="stat-icon accent">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <span class="stat-value"><?= $streak ?></span>
                    <span class="stat-label">Days This Week</span>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </div>
                    <span class="stat-value"><?= $longestStreak ?></span>
                    <span class="stat-label">This Month</span>
                </div>
            </div>

            <div class="diary-layout">
                <!-- Sidebar -->
                <aside class="diary-sidebar">
                    <!-- Mini Calendar -->
                    <div class="sidebar-section">
                        <h3>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            Calendar
                        </h3>
                        <?= buildCalendar($calYear, $calMonth, $entryDates) ?>
                    </div>

                    <!-- Search -->
                    <div class="sidebar-section">
                        <h3>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            Search
                        </h3>
                        <form method="get" class="search-form">
                            <input type="text" name="search" value="<?= e($search ?? '') ?>" placeholder="Search entries...">
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
                        <h3>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                <circle cx="12" cy="12" r="10"></circle>
                                <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
                                <line x1="9" y1="9" x2="9.01" y2="9"></line>
                                <line x1="15" y1="9" x2="15.01" y2="9"></line>
                            </svg>
                            Filter by Mood
                        </h3>
                        <div class="mood-filters">
                            <a href="?" class="mood-btn <?= !$mood ? 'active' : '' ?>">All</a>
                            <?php foreach ($moods as $m): ?>
                                <a href="?mood=<?= $m ?>" class="mood-btn <?= $mood === $m ? 'active' : '' ?>" style="--mood-color: <?= getMoodColor($m) ?>">
                                    <?= getMoodEmoji($m) ?> <?= ucfirst($m) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Tags -->
                    <?php if (!empty($tags)): ?>
                        <div class="sidebar-section">
                            <h3>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                                    <line x1="7" y1="7" x2="7.01" y2="7"></line>
                                </svg>
                                Tags
                            </h3>
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
                    <?php if (!empty($archives)): ?>
                        <div class="sidebar-section">
                            <h3>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                    <polyline points="22 12 16 12 14 15 10 15 8 12 2 12"></polyline>
                                    <path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"></path>
                                </svg>
                                Archive
                            </h3>
                            <div class="archive-links">
                                <?php foreach ($archives as $archive): ?>
                                    <?php $monthName = date('F', mktime(0, 0, 0, $archive['month'], 1)); ?>
                                    <a href="?year=<?= $archive['year'] ?>&month=<?= $archive['month'] ?>" class="<?= ($filterYear == $archive['year'] && $filterMonth == $archive['month']) ? 'active' : '' ?>">
                                        <span class="archive-date"><?= $monthName ?> <?= $archive['year'] ?></span>
                                        <span class="archive-count"><?= $archive['count'] ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Quick Links -->
                    <div class="sidebar-section">
                        <h3>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                                <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                            </svg>
                            Quick Links
                        </h3>
                        <div class="quick-links">
                            <a href="/calendar/" class="quick-link">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                Full Calendar
                            </a>
                            <a href="/diary/prayers.php" class="quick-link">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                </svg>
                                Prayer Journal
                            </a>
                        </div>
                    </div>
                </aside>

                <!-- Main Content -->
                <div class="diary-main">
                    <?php if ($search || $tag || $mood || $filterMonth): ?>
                        <div class="filter-banner">
                            <span>
                                <?php if ($search): ?>
                                    Searching: "<?= e($search) ?>"
                                <?php elseif ($tag): ?>
                                    Tag: <?= e($tag) ?>
                                <?php elseif ($mood): ?>
                                    Mood: <?= getMoodEmoji($mood) ?> <?= ucfirst($mood) ?>
                                <?php elseif ($filterMonth): ?>
                                    <?= date('F Y', mktime(0, 0, 0, $filterMonth, 1, $filterYear)) ?>
                                <?php endif; ?>
                            </span>
                            <a href="/diary/" class="clear-filter">Clear Filter</a>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($entries)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                </svg>
                            </div>
                            <h3>No entries yet</h3>
                            <p>Start writing to capture your thoughts, prayers, and spiritual reflections</p>
                            <a href="/diary/entry.php" class="btn btn-primary">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                                Write Your First Entry
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="entries-grid">
                            <?php foreach ($entries as $entry): ?>
                                <a href="/diary/entry.php?id=<?= $entry['id'] ?>" class="entry-card">
                                    <div class="entry-date">
                                        <span class="day"><?= date('d', strtotime($entry['entry_date'])) ?></span>
                                        <span class="month"><?= date('M', strtotime($entry['entry_date'])) ?></span>
                                        <span class="year"><?= date('Y', strtotime($entry['entry_date'])) ?></span>
                                    </div>
                                    <div class="entry-content">
                                        <h3><?= e($entry['title'] ?: 'Untitled Entry') ?></h3>
                                        <p><?= e(truncate(strip_tags($entry['content'] ?? ''), 120)) ?></p>
                                        <div class="entry-footer">
                                            <?php if (!empty($entry['mood'])): ?>
                                                <span class="entry-mood" style="--mood-color: <?= getMoodColor($entry['mood']) ?>">
                                                    <?= getMoodEmoji($entry['mood']) ?> <?= ucfirst($entry['mood']) ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($entry['scripture_ref'])): ?>
                                                <span class="entry-scripture">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                                        <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                                    </svg>
                                                    <?= e($entry['scripture_ref']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ($entry['is_private']): ?>
                                        <span class="entry-private" title="Private">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                            </svg>
                                        </span>
                                    <?php endif; ?>
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
