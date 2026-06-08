<?php
/**
 * CRC Media - Sermons Archive
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
$pageTitle = "Sermons - CRC";

// Get filters
$search = trim($_GET['search'] ?? '');
$category = $_GET['category'] ?? '';
$seriesId = (int)($_GET['series'] ?? 0);
$speaker = $_GET['speaker'] ?? '';
$view = $_GET['view'] ?? 'list';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

$congId = $primaryCong ? $primaryCong['id'] : 0;

// Build query
$whereClause = "WHERE s.status = 'published' AND (s.congregation_id = ? OR s.congregation_id IS NULL)";
$params = [$congId];

if ($search) {
    $whereClause .= " AND (s.title LIKE ? OR s.description LIKE ? OR s.speaker LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($category) {
    $whereClause .= " AND s.category = ?";
    $params[] = $category;
}

if ($seriesId) {
    $whereClause .= " AND s.series_id = ?";
    $params[] = $seriesId;
}

if ($speaker) {
    $whereClause .= " AND (s.speaker = ? OR s.speaker_user_id = ?)";
    $params[] = $speaker;
    $params[] = (int)$speaker;
}

// Get total count
$totalCount = Database::fetchColumn(
    "SELECT COUNT(*) FROM sermons s $whereClause",
    $params
);

// Get sermons
$sermons = Database::fetchAll(
    "SELECT s.*, u.name as speaker_name, c.name as congregation_name, ss.name as series_name
     FROM sermons s
     LEFT JOIN users u ON s.speaker_user_id = u.id
     LEFT JOIN congregations c ON s.congregation_id = c.id
     LEFT JOIN sermon_series ss ON s.series_id = ss.id
     $whereClause
     ORDER BY s.sermon_date DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

$totalPages = ceil($totalCount / $perPage);

// Get all series for filter
$allSeries = Database::fetchAll(
    "SELECT id, name FROM sermon_series WHERE congregation_id = ? OR congregation_id IS NULL ORDER BY name ASC",
    [$congId]
);

// Get all categories for filter
$categories = Database::fetchAll(
    "SELECT DISTINCT category FROM sermons
     WHERE status = 'published' AND category IS NOT NULL AND category != ''
     ORDER BY category ASC"
);

// Get speakers for filter
$speakers = Database::fetchAll(
    "SELECT DISTINCT speaker FROM sermons
     WHERE status = 'published' AND speaker IS NOT NULL AND speaker != ''
     ORDER BY speaker ASC"
);

// If series view, get series list
$seriesList = [];
if ($view === 'series') {
    $seriesList = Database::fetchAll(
        "SELECT ss.*,
                (SELECT COUNT(*) FROM sermons WHERE series_id = ss.id AND status = 'published') as sermon_count,
                (SELECT thumbnail_url FROM sermons WHERE series_id = ss.id AND status = 'published' AND thumbnail_url IS NOT NULL ORDER BY sermon_date DESC LIMIT 1) as latest_thumb
         FROM sermon_series ss
         WHERE ss.congregation_id = ? OR ss.congregation_id IS NULL
         HAVING sermon_count > 0
         ORDER BY ss.created_at DESC",
        [$congId]
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/media/css/media.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <a href="/media/" class="back-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
                Back to Media
            </a>

            <div class="page-header">
                <div class="page-title">
                    <h1>Sermons</h1>
                    <p><?= number_format($totalCount) ?> sermon<?= $totalCount != 1 ? 's' : '' ?></p>
                </div>
            </div>

            <!-- View Toggle & Search -->
            <div class="toolbar">
                <div class="view-toggle">
                    <a href="?view=list<?= $search ? "&search=$search" : '' ?><?= $category ? "&category=$category" : '' ?>"
                       class="toggle-btn <?= $view === 'list' ? 'active' : '' ?>">
                        List
                    </a>
                    <a href="?view=series" class="toggle-btn <?= $view === 'series' ? 'active' : '' ?>">
                        Series
                    </a>
                </div>

                <?php if ($view === 'list'): ?>
                    <form class="search-form" method="GET">
                        <input type="hidden" name="view" value="list">
                        <input type="search" name="search" placeholder="Search sermons..."
                               value="<?= e($search) ?>" class="search-input">
                        <button type="submit" class="search-btn">Search</button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if ($view === 'list'): ?>
                <!-- Filters -->
                <div class="filters-row">
                    <select name="category" onchange="applyFilter('category', this.value)" class="filter-select">
                        <option value="">All Topics</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= e($cat['category']) ?>" <?= $category === $cat['category'] ? 'selected' : '' ?>>
                                <?= e($cat['category']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="series" onchange="applyFilter('series', this.value)" class="filter-select">
                        <option value="">All Series</option>
                        <?php foreach ($allSeries as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $seriesId == $s['id'] ? 'selected' : '' ?>>
                                <?= e($s['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="speaker" onchange="applyFilter('speaker', this.value)" class="filter-select">
                        <option value="">All Speakers</option>
                        <?php foreach ($speakers as $sp): ?>
                            <option value="<?= e($sp['speaker']) ?>" <?= $speaker === $sp['speaker'] ? 'selected' : '' ?>>
                                <?= e($sp['speaker']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if ($search || $category || $seriesId || $speaker): ?>
                        <a href="/media/sermons.php" class="clear-filters">Clear Filters</a>
                    <?php endif; ?>
                </div>

                <!-- Sermons List -->
                <?php if ($sermons): ?>
                    <div class="sermons-grid">
                        <?php foreach ($sermons as $sermon): ?>
                            <a href="/media/sermon.php?id=<?= $sermon['id'] ?>" class="sermon-card">
                                <div class="sermon-thumb">
                                    <?php if ($sermon['thumbnail_url']): ?>
                                        <img src="<?= e($sermon['thumbnail_url']) ?>" alt="">
                                    <?php else: ?>
                                        <div class="thumb-placeholder">🎤</div>
                                    <?php endif; ?>
                                    <?php if ($sermon['duration']): ?>
                                        <span class="duration"><?= formatDuration($sermon['duration']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($sermon['video_url']): ?>
                                        <span class="media-type video">Video</span>
                                    <?php elseif ($sermon['audio_url']): ?>
                                        <span class="media-type audio">Audio</span>
                                    <?php endif; ?>
                                </div>
                                <div class="sermon-info">
                                    <h3><?= e($sermon['title']) ?></h3>
                                    <p class="sermon-speaker"><?= e($sermon['speaker_name'] ?? $sermon['speaker']) ?></p>
                                    <div class="sermon-meta">
                                        <span><?= date('M j, Y', strtotime($sermon['sermon_date'])) ?></span>
                                        <?php if ($sermon['series_name']): ?>
                                            <span class="series-badge"><?= e($sermon['series_name']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-btn">← Previous</a>
                            <?php endif; ?>

                            <span class="page-info">Page <?= $page ?> of <?= $totalPages ?></span>

                            <?php if ($page < $totalPages): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-btn">Next →</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">🎤</div>
                        <h3>No sermons found</h3>
                        <p>
                            <?php if ($search): ?>
                                No results for "<?= e($search) ?>". Try a different search term.
                            <?php else: ?>
                                No sermons match your filters.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Series View -->
                <?php if ($seriesList): ?>
                    <div class="series-list-grid">
                        <?php foreach ($seriesList as $s): ?>
                            <a href="/media/sermons.php?series=<?= $s['id'] ?>&view=list" class="series-list-card">
                                <div class="series-cover-large">
                                    <?php if ($s['cover_url']): ?>
                                        <img src="<?= e($s['cover_url']) ?>" alt="">
                                    <?php elseif ($s['latest_thumb']): ?>
                                        <img src="<?= e($s['latest_thumb']) ?>" alt="">
                                    <?php else: ?>
                                        <div class="cover-placeholder-large"><?= strtoupper(substr($s['name'], 0, 2)) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="series-details">
                                    <h3><?= e($s['name']) ?></h3>
                                    <?php if ($s['description']): ?>
                                        <p class="series-desc"><?= e(truncate($s['description'], 150)) ?></p>
                                    <?php endif; ?>
                                    <span class="series-count"><?= $s['sermon_count'] ?> sermon<?= $s['sermon_count'] != 1 ? 's' : '' ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">📚</div>
                        <h3>No sermon series</h3>
                        <p>Sermon series will appear here once created.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <script src="/media/js/media.js"></script>
    <script>
        function applyFilter(name, value) {
            const url = new URL(window.location);
            if (value) {
                url.searchParams.set(name, value);
            } else {
                url.searchParams.delete(name);
            }
            url.searchParams.set('view', 'list');
            url.searchParams.delete('page');
            window.location = url;
        }
    </script>
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
