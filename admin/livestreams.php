<?php
/**
 * CRC Global Admin - Livestreams Management
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();
Auth::requireRole('super_admin');

$user = Auth::user();
$pageTitle = "Livestreams - Admin";

$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$streams = [];
$totalCount = 0;
$totalPages = 0;
$congregations = [];

try {
    $whereClause = "WHERE 1=1";
    $params = [];

    if ($search) {
        $whereClause .= " AND l.title LIKE ?";
        $params[] = "%$search%";
    }

    $totalCount = Database::fetchColumn(
        "SELECT COUNT(*) FROM livestreams l $whereClause",
        $params
    );

    $streams = Database::fetchAll(
        "SELECT l.*, c.name AS congregation_name
         FROM livestreams l
         LEFT JOIN congregations c ON l.congregation_id = c.id
         $whereClause
         ORDER BY (l.status = 'live') DESC, COALESCE(l.scheduled_at, l.started_at, l.created_at) DESC
         LIMIT $perPage OFFSET $offset",
        $params
    ) ?: [];

    $totalPages = ceil($totalCount / $perPage);

    $congregations = Database::fetchAll(
        "SELECT id, name FROM congregations WHERE status = 'active' ORDER BY name ASC"
    ) ?: [];
} catch (Exception $e) {
    // Table might not exist yet
}

function adminStreamDate($stream): string {
    $when = $stream['scheduled_at'] ?? $stream['started_at'] ?? $stream['created_at'] ?? null;
    return $when ? date('M j, Y g:i A', strtotime($when)) : '—';
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

        <main class="admin-main">
            <header class="admin-header">
                <h1>Livestreams</h1>
                <div class="header-actions">
                    <button onclick="openAddLivestream()" class="btn btn-primary">
                        + Add Livestream
                    </button>
                </div>
            </header>

            <div class="admin-content">
                <div class="filters-bar">
                    <form class="search-form" method="GET">
                        <input type="search" name="search" placeholder="Search livestreams..."
                               value="<?= e($search) ?>" class="search-input">
                        <button type="submit" class="btn btn-outline">Search</button>
                    </form>
                </div>

                <div class="card">
                    <div class="card-body">
                        <p class="result-count"><?= number_format($totalCount) ?> livestream<?= $totalCount != 1 ? 's' : '' ?></p>

                        <?php if ($streams): ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Congregation</th>
                                        <th>Status</th>
                                        <th>When</th>
                                        <th>Viewers</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($streams as $s): ?>
                                        <tr>
                                            <td><strong><?= e($s['title']) ?></strong></td>
                                            <td><?= e($s['congregation_name'] ?? 'Global') ?></td>
                                            <td>
                                                <span class="status-badge status-<?= e($s['status'] ?? 'scheduled') ?>">
                                                    <?= ucfirst($s['status'] ?? 'scheduled') ?>
                                                </span>
                                            </td>
                                            <td><?= e(adminStreamDate($s)) ?></td>
                                            <td><?= number_format((int)($s['viewer_count'] ?? 0)) ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button onclick="editLivestream(<?= (int)$s['id'] ?>)" class="action-btn" title="Edit">✏️</button>
                                                    <button onclick="deleteLivestream(<?= (int)$s['id'] ?>)" class="action-btn" title="Delete">🗑️</button>
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
                                <span class="empty-icon">📺</span>
                                <p class="empty-text">No livestreams found</p>
                                <p class="empty-subtext">Schedule your first livestream to get started</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add/Edit Livestream Modal -->
    <div id="add-livestream-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Livestream</h2>
                <button onclick="closeModal('add-livestream-modal')" class="modal-close">&times;</button>
            </div>
            <form id="add-livestream-form">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" required class="form-input">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-input">
                            <option value="scheduled">Scheduled</option>
                            <option value="live">Live</option>
                            <option value="ended">Ended</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Congregation</label>
                        <select name="congregation_id" class="form-input">
                            <option value="">Global (all congregations)</option>
                            <?php foreach ($congregations as $cg): ?>
                                <option value="<?= (int)$cg['id'] ?>"><?= e($cg['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Scheduled Date &amp; Time</label>
                    <input type="datetime-local" name="scheduled_at" class="form-input">
                </div>
                <div class="form-group">
                    <label>Live Embed URL</label>
                    <input type="url" name="embed_url" class="form-input" placeholder="YouTube/Vimeo or embed URL (for live)">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-textarea" rows="3"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Recording URL</label>
                        <input type="url" name="recording_url" class="form-input" placeholder="For ended streams (replay)">
                    </div>
                    <div class="form-group">
                        <label>Thumbnail URL</label>
                        <input type="url" name="thumbnail_url" class="form-input" placeholder="Cover image URL">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Duration (minutes)</label>
                        <input type="number" name="duration_minutes" min="0" step="1" class="form-input" placeholder="e.g. 60">
                    </div>
                    <div class="form-group checkbox-group">
                        <label><input type="checkbox" name="chat_enabled" value="1" checked> Enable live chat</label>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" onclick="closeModal('add-livestream-modal')" class="btn btn-outline">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Livestream</button>
                </div>
            </form>
        </div>
    </div>

    <script src="/admin/js/admin.js"></script>
</body>
</html>
