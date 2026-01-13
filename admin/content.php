<?php
/**
 * CRC Global Admin - Content Management
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();
Auth::requireRole('super_admin');

$user = Auth::user();
$pageTitle = "Content - Admin";

// Get filters
$search = trim($_GET['search'] ?? '');
$type = $_GET['type'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Check if content table exists and get data
$content = [];
$totalCount = 0;
$totalPages = 0;

try {
    $whereClause = "WHERE 1=1";
    $params = [];

    if ($search) {
        $whereClause .= " AND (title LIKE ? OR body LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    if ($type) {
        $whereClause .= " AND type = ?";
        $params[] = $type;
    }

    $totalCount = Database::fetchColumn(
        "SELECT COUNT(*) FROM content $whereClause",
        $params
    );

    $content = Database::fetchAll(
        "SELECT * FROM content
         $whereClause
         ORDER BY created_at DESC
         LIMIT $perPage OFFSET $offset",
        $params
    ) ?: [];

    $totalPages = ceil($totalCount / $perPage);
} catch (Exception $e) {
    // Table might not exist
}

$contentTypes = [
    'page' => 'Page',
    'article' => 'Article',
    'announcement' => 'Announcement',
    'devotional' => 'Devotional',
    'resource' => 'Resource'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
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
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <a href="/admin/" class="admin-logo">CRC Admin</a>
            </div>
            <nav class="sidebar-nav">
                <a href="/admin/" class="nav-item">
                    <span class="nav-icon">📊</span>
                    Dashboard
                </a>
                <a href="/admin/users.php" class="nav-item">
                    <span class="nav-icon">👥</span>
                    Users
                </a>
                <a href="/admin/congregations.php" class="nav-item">
                    <span class="nav-icon">⛪</span>
                    Congregations
                </a>
                <a href="/admin/sermons.php" class="nav-item">
                    <span class="nav-icon">🎤</span>
                    Sermons
                </a>
                <a href="/admin/courses.php" class="nav-item">
                    <span class="nav-icon">📚</span>
                    Courses
                </a>
                <a href="/admin/content.php" class="nav-item active">
                    <span class="nav-icon">📝</span>
                    Content
                </a>
                <a href="/admin/settings.php" class="nav-item">
                    <span class="nav-icon">⚙️</span>
                    Settings
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="/home/" class="nav-item">
                    <span class="nav-icon">🏠</span>
                    Back to App
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-header">
                <h1>Content</h1>
                <div class="header-actions">
                    <button onclick="openModal('add-content-modal')" class="btn btn-primary">
                        + Add Content
                    </button>
                </div>
            </header>

            <div class="admin-content">
                <!-- Filters -->
                <div class="filters-bar">
                    <form class="search-form" method="GET">
                        <input type="search" name="search" placeholder="Search content..."
                               value="<?= e($search) ?>" class="search-input">
                        <button type="submit" class="btn btn-outline">Search</button>
                    </form>

                    <div class="filter-group">
                        <select name="type" onchange="applyFilter('type', this.value)" class="filter-select">
                            <option value="">All Types</option>
                            <?php foreach ($contentTypes as $val => $label): ?>
                                <option value="<?= $val ?>" <?= $type === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Content Table -->
                <div class="card">
                    <div class="card-body">
                        <p class="result-count"><?= number_format($totalCount) ?> item<?= $totalCount != 1 ? 's' : '' ?></p>

                        <?php if ($content): ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Type</th>
                                        <th>Author</th>
                                        <th>Created</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($content as $c): ?>
                                        <tr>
                                            <td><strong><?= e($c['title']) ?></strong></td>
                                            <td>
                                                <span class="type-badge">
                                                    <?= $contentTypes[$c['type']] ?? ucfirst($c['type']) ?>
                                                </span>
                                            </td>
                                            <td><?= e($c['author'] ?? '-') ?></td>
                                            <td><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
                                            <td>
                                                <span class="status-badge status-<?= $c['status'] ?? 'draft' ?>">
                                                    <?= ucfirst($c['status'] ?? 'draft') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button onclick="editContent(<?= $c['id'] ?>)" class="action-btn" title="Edit">✏️</button>
                                                    <button onclick="deleteContent(<?= $c['id'] ?>)" class="action-btn" title="Delete">🗑️</button>
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
                                <span class="empty-icon">📝</span>
                                <p class="empty-text">No content found</p>
                                <p class="empty-subtext">Create your first content item to get started</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Content Modal -->
    <div id="add-content-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Content</h2>
                <button onclick="closeModal('add-content-modal')" class="modal-close">&times;</button>
            </div>
            <form id="add-content-form">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" required class="form-input">
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" class="form-select">
                        <?php foreach ($contentTypes as $val => $label): ?>
                            <option value="<?= $val ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Body</label>
                    <textarea name="body" class="form-textarea" rows="6"></textarea>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-select">
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" onclick="closeModal('add-content-modal')" class="btn btn-outline">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Content</button>
                </div>
            </form>
        </div>
    </div>

    <script src="/admin/js/admin.js"></script>
</body>
</html>
