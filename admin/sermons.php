<?php
/**
 * CRC Global Admin - Sermons Management
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();
Auth::requireRole('super_admin');

$user = Auth::user();
$pageTitle = "Sermons - Admin";

// Get filters
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Check if sermons table exists and get data
$sermons = [];
$totalCount = 0;
$totalPages = 0;
$allSeries = [];
$congregations = [];

try {
    $whereClause = "WHERE 1=1";
    $params = [];

    if ($search) {
        $whereClause .= " AND (s.title LIKE ? OR s.speaker LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $totalCount = Database::fetchColumn(
        "SELECT COUNT(*) FROM sermons s $whereClause",
        $params
    );

    $sermons = Database::fetchAll(
        "SELECT s.*, c.name as congregation_name, ss.name as series_name
         FROM sermons s
         LEFT JOIN congregations c ON s.congregation_id = c.id
         LEFT JOIN sermon_series ss ON s.series_id = ss.id
         $whereClause
         ORDER BY s.created_at DESC
         LIMIT $perPage OFFSET $offset",
        $params
    ) ?: [];

    $totalPages = ceil($totalCount / $perPage);

    $allSeries = Database::fetchAll(
        "SELECT id, name FROM sermon_series ORDER BY name ASC"
    ) ?: [];

    $congregations = Database::fetchAll(
        "SELECT id, name FROM congregations WHERE status = 'active' ORDER BY name ASC"
    ) ?: [];
} catch (Exception $e) {
    // Table might not exist
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
    <link rel="stylesheet" href="/admin/css/admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        (function() {
            const saved = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', saved);
        })();
    </script>
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <div class="admin-layout">
        <?php include __DIR__ . '/partials/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-header">
                <h1>Sermons</h1>
                <div class="header-actions">
                    <button onclick="openAddSermon()" class="btn btn-primary">
                        + Add Sermon
                    </button>
                </div>
            </header>

            <div class="admin-content">
                <!-- Filters -->
                <div class="filters-bar">
                    <form class="search-form" method="GET">
                        <input type="search" name="search" placeholder="Search sermons..."
                               value="<?= e($search) ?>" class="search-input">
                        <button type="submit" class="btn btn-outline">Search</button>
                    </form>
                </div>

                <!-- Sermons Table -->
                <div class="card">
                    <div class="card-body">
                        <p class="result-count"><?= number_format($totalCount) ?> sermon<?= $totalCount != 1 ? 's' : '' ?></p>

                        <?php if ($sermons): ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Speaker</th>
                                        <th>Series</th>
                                        <th>Congregation</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sermons as $s): ?>
                                        <tr>
                                            <td><strong><?= e($s['title']) ?></strong></td>
                                            <td><?= e($s['speaker'] ?? '-') ?></td>
                                            <td><?= e($s['series_name'] ?? '—') ?></td>
                                            <td><?= e($s['congregation_name'] ?? 'Global') ?></td>
                                            <td><?= e(date('M j, Y', strtotime($s['created_at']))) ?></td>
                                            <td>
                                                <span class="status-badge status-<?= $s['status'] ?? 'draft' ?>">
                                                    <?= ucfirst($s['status'] ?? 'draft') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button onclick="editSermon(<?= $s['id'] ?>)" class="action-btn" title="Edit">✏️</button>
                                                    <button onclick="deleteSermon(<?= $s['id'] ?>)" class="action-btn" title="Delete">🗑️</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

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
                                <span class="empty-icon">🎤</span>
                                <p class="empty-text">No sermons found</p>
                                <p class="empty-subtext">Add your first sermon to get started</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Sermon Modal -->
    <div id="add-sermon-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Sermon</h2>
                <button onclick="closeModal('add-sermon-modal')" class="modal-close">&times;</button>
            </div>
            <form id="add-sermon-form">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" required class="form-input">
                </div>
                <div class="form-group">
                    <label>Speaker</label>
                    <input type="text" name="speaker" required class="form-input">
                </div>
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="sermon_date" required class="form-input">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Category</label>
                        <input type="text" name="category" class="form-input" placeholder="e.g. Faith, Prayer, Hope">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-input">
                            <option value="published">Published</option>
                            <option value="draft">Draft</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Congregation</label>
                        <select name="congregation_id" class="form-input">
                            <option value="">Global (all congregations)</option>
                            <?php foreach ($congregations as $cg): ?>
                                <option value="<?= (int)$cg['id'] ?>"><?= e($cg['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Series</label>
                        <select name="series_id" class="form-input">
                            <option value="">— None —</option>
                            <?php foreach ($allSeries as $sr): ?>
                                <option value="<?= (int)$sr['id'] ?>"><?= e($sr['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Or create a new series</label>
                    <input type="text" name="new_series" class="form-input" placeholder="Leave blank to use the selection above">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-textarea" rows="3"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Video URL</label>
                        <input type="url" name="video_url" class="form-input" placeholder="YouTube or Vimeo URL">
                    </div>
                    <div class="form-group">
                        <label>Audio URL</label>
                        <input type="url" name="audio_url" class="form-input" placeholder="MP3 or audio file URL">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Thumbnail URL</label>
                        <input type="url" name="thumbnail" class="form-input" placeholder="Cover image URL">
                    </div>
                    <div class="form-group">
                        <label>Duration (minutes)</label>
                        <input type="number" name="duration_minutes" min="0" step="1" class="form-input" placeholder="e.g. 45">
                    </div>
                </div>
                <div class="form-group">
                    <label>Scripture References</label>
                    <textarea name="scripture_references" class="form-textarea" rows="2" placeholder="One reference per line, e.g. John 3:16"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" onclick="closeModal('add-sermon-modal')" class="btn btn-outline">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Sermon</button>
                </div>
            </form>
        </div>
    </div>

    <script src="/admin/js/admin.js"></script>
</body>
</html>
