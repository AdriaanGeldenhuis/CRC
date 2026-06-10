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
$category = trim($_GET['category'] ?? '');
$seriesId = (int)($_GET['series'] ?? 0);
$speaker = trim($_GET['speaker'] ?? '');
$view = ($_GET['view'] ?? 'list') === 'series' ? 'series' : 'list';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

$congId = $primaryCong ? (int)$primaryCong['id'] : 0;

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
    $whereClause .= " AND s.speaker = ?";
    $params[] = $speaker;
}

// Defaults so the page always renders, even if the schema is mid-migration.
$totalCount = 0;
$sermons = [];
$totalPages = 0;
$allSeries = [];
$categories = [];
$speakers = [];
$seriesList = [];
$loadError = false;

try {
    // Get total count
    $totalCount = (int)Database::fetchColumn(
        "SELECT COUNT(*) FROM sermons s $whereClause",
        $params
    );

    // Get sermons for the current page
    $sermons = Database::fetchAll(
        "SELECT s.*, u.name AS speaker_name, c.name AS congregation_name, ss.name AS series_name
         FROM sermons s
         LEFT JOIN users u ON s.speaker_user_id = u.id
         LEFT JOIN congregations c ON s.congregation_id = c.id
         LEFT JOIN sermon_series ss ON s.series_id = ss.id
         $whereClause
         ORDER BY s.sermon_date DESC, s.id DESC
         LIMIT $perPage OFFSET $offset",
        $params
    );

    $totalPages = (int)ceil($totalCount / $perPage);

    // Get all series for the filter dropdown (only those with published sermons)
    $allSeries = Database::fetchAll(
        "SELECT ss.id, ss.name
         FROM sermon_series ss
         WHERE (ss.congregation_id = ? OR ss.congregation_id IS NULL)
           AND EXISTS (SELECT 1 FROM sermons sx WHERE sx.series_id = ss.id AND sx.status = 'published')
         ORDER BY ss.name ASC",
        [$congId]
    );

    // Get all categories for the filter
    $categories = Database::fetchAll(
        "SELECT DISTINCT category FROM sermons
         WHERE status = 'published' AND category IS NOT NULL AND category != ''
           AND (congregation_id = ? OR congregation_id IS NULL)
         ORDER BY category ASC",
        [$congId]
    );

    // Get speakers for the filter
    $speakers = Database::fetchAll(
        "SELECT DISTINCT speaker FROM sermons
         WHERE status = 'published' AND speaker IS NOT NULL AND speaker != ''
           AND (congregation_id = ? OR congregation_id IS NULL)
         ORDER BY speaker ASC",
        [$congId]
    );

    // If series view, get the series list with cover + count
    if ($view === 'series') {
        $seriesList = Database::fetchAll(
            "SELECT ss.*,
                    (SELECT COUNT(*) FROM sermons WHERE series_id = ss.id AND status = 'published') AS sermon_count,
                    (SELECT thumbnail FROM sermons
                       WHERE series_id = ss.id AND status = 'published' AND thumbnail IS NOT NULL
                       ORDER BY sermon_date DESC LIMIT 1) AS latest_thumb
             FROM sermon_series ss
             WHERE ss.congregation_id = ? OR ss.congregation_id IS NULL
             HAVING sermon_count > 0
             ORDER BY ss.created_at DESC",
            [$congId]
        );
    }
} catch (Throwable $e) {
    // Degrade gracefully rather than 500 the whole page if the schema is not
    // yet migrated. The error is still logged by the global handler upstream.
    Logger::error('Sermons archive load failed: ' . $e->getMessage());
    $loadError = true;
}

/**
 * Build a query string for filter/view links, preserving active filters.
 */
function sermonsQuery(array $overrides = []): string {
    $base = [
        'view'     => $_GET['view'] ?? 'list',
        'search'   => $_GET['search'] ?? '',
        'category' => $_GET['category'] ?? '',
        'series'   => $_GET['series'] ?? '',
        'speaker'  => $_GET['speaker'] ?? '',
    ];
    $merged = array_merge($base, $overrides);
    $merged = array_filter($merged, fn($v) => $v !== '' && $v !== null);
    return $merged ? '?' . http_build_query($merged) : '?';
}

function formatDuration($seconds): string {
    $seconds = (int)$seconds;
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $secs = $seconds % 60;

    if ($hours > 0) {
        return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
    }
    return sprintf('%d:%02d', $minutes, $secs);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#7C3AED">
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
                    <a href="<?= e(sermonsQuery(['view' => 'list', 'page' => ''])) ?>"
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
                        <?php if ($category): ?><input type="hidden" name="category" value="<?= e($category) ?>"><?php endif; ?>
                        <?php if ($seriesId): ?><input type="hidden" name="series" value="<?= (int)$seriesId ?>"><?php endif; ?>
                        <?php if ($speaker): ?><input type="hidden" name="speaker" value="<?= e($speaker) ?>"><?php endif; ?>
                        <input type="search" name="search" placeholder="Search sermons..."
                               value="<?= e($search) ?>" class="search-input">
                        <button type="submit" class="search-btn">Search</button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if ($loadError): ?>
                <div class="empty-state">
                    <div class="empty-icon">⚠️</div>
                    <h3>Sermons are temporarily unavailable</h3>
                    <p>We couldn't load the sermon archive right now. Please try again shortly.</p>
                </div>
            <?php elseif ($view === 'list'): ?>
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
                            <option value="<?= (int)$s['id'] ?>" <?= $seriesId === (int)$s['id'] ? 'selected' : '' ?>>
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
                            <?php $speakerLabel = $sermon['speaker_name'] ?? $sermon['speaker'] ?? 'Unknown speaker'; ?>
                            <a href="/media/sermon.php?id=<?= (int)$sermon['id'] ?>" class="sermon-card">
                                <div class="sermon-thumb">
                                    <?php if (!empty($sermon['thumbnail'])): ?>
                                        <img src="<?= e($sermon['thumbnail']) ?>" alt="" loading="lazy">
                                    <?php else: ?>
                                        <div class="thumb-placeholder">🎤</div>
                                    <?php endif; ?>
                                    <?php if (!empty($sermon['duration'])): ?>
                                        <span class="duration"><?= formatDuration($sermon['duration']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($sermon['video_url'])): ?>
                                        <span class="media-type video">Video</span>
                                    <?php elseif (!empty($sermon['audio_url'])): ?>
                                        <span class="media-type audio">Audio</span>
                                    <?php endif; ?>
                                </div>
                                <div class="sermon-info">
                                    <h3><?= e($sermon['title']) ?></h3>
                                    <p class="sermon-speaker"><?= e($speakerLabel) ?></p>
                                    <div class="sermon-meta">
                                        <?php if (!empty($sermon['sermon_date'])): ?>
                                            <span><?= e(date('M j, Y', strtotime($sermon['sermon_date']))) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($sermon['series_name'])): ?>
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
                                <a href="<?= e(sermonsQuery(['page' => $page - 1])) ?>" class="page-btn">← Previous</a>
                            <?php endif; ?>

                            <span class="page-info">Page <?= $page ?> of <?= $totalPages ?></span>

                            <?php if ($page < $totalPages): ?>
                                <a href="<?= e(sermonsQuery(['page' => $page + 1])) ?>" class="page-btn">Next →</a>
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
                            <?php elseif ($category || $seriesId || $speaker): ?>
                                No sermons match your filters.
                            <?php else: ?>
                                Sermons will appear here once they are published.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Series View -->
                <?php if ($seriesList): ?>
                    <div class="series-list-grid">
                        <?php foreach ($seriesList as $s): ?>
                            <?php $cover = $s['image'] ?? null; if (empty($cover)) { $cover = $s['latest_thumb'] ?? null; } ?>
                            <a href="/media/sermons.php?series=<?= (int)$s['id'] ?>&view=list" class="series-list-card">
                                <div class="series-cover-large">
                                    <?php if (!empty($cover)): ?>
                                        <img src="<?= e($cover) ?>" alt="" loading="lazy">
                                    <?php else: ?>
                                        <div class="cover-placeholder-large"><?= e(strtoupper(mb_substr($s['name'], 0, 2))) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="series-details">
                                    <h3><?= e($s['name']) ?></h3>
                                    <?php if (!empty($s['description'])): ?>
                                        <p class="series-desc"><?= e(truncate($s['description'], 150)) ?></p>
                                    <?php endif; ?>
                                    <span class="series-count"><?= (int)$s['sermon_count'] ?> sermon<?= $s['sermon_count'] != 1 ? 's' : '' ?></span>
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
