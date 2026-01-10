<?php
/**
 * CRC Global Admin - Congregations Management
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();
Auth::requireRole('super_admin');

$user = Auth::user();
$pageTitle = "Congregations - Admin";

// Get filters
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
$whereClause = "WHERE 1=1";
$params = [];

if ($search) {
    $whereClause .= " AND (c.name LIKE ? OR c.city LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($status) {
    $whereClause .= " AND c.status = ?";
    $params[] = $status;
}

// Get total count
$totalCount = Database::fetchColumn(
    "SELECT COUNT(*) FROM congregations c $whereClause",
    $params
);

// Get congregations
$congregations = Database::fetchAll(
    "SELECT c.*,
            (SELECT COUNT(*) FROM congregation_members WHERE congregation_id = c.id AND status = 'active') as member_count,
            (SELECT name FROM users WHERE id = c.created_by) as created_by_name
     FROM congregations c
     $whereClause
     ORDER BY c.created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

$totalPages = ceil($totalCount / $perPage);
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
</head>
<body>
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
                <a href="/admin/congregations.php" class="nav-item active">
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
                <a href="/admin/content.php" class="nav-item">
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
                <h1>Congregations</h1>
                <div class="header-actions">
                    <button onclick="openModal('add-congregation-modal')" class="btn btn-primary">
                        + Add Congregation
                    </button>
                </div>
            </header>

            <div class="admin-content">
                <!-- Filters -->
                <div class="filters-bar">
                    <form class="search-form" method="GET">
                        <input type="search" name="search" placeholder="Search congregations..."
                               value="<?= e($search) ?>" class="search-input">
                        <button type="submit" class="btn btn-outline">Search</button>
                    </form>

                    <div class="filter-group">
                        <select name="status" onchange="applyFilter('status', this.value)" class="filter-select">
                            <option value="">All Status</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                        </select>
                    </div>
                </div>

                <!-- Congregations Table -->
                <div class="card">
                    <div class="card-body">
                        <p class="result-count"><?= number_format($totalCount) ?> congregation<?= $totalCount != 1 ? 's' : '' ?></p>

                        <?php if ($congregations): ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Location</th>
                                        <th>Members</th>
                                        <th>Status</th>
                                        <th>Created By</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($congregations as $c): ?>
                                        <tr>
                                            <td>
                                                <div class="congregation-cell">
                                                    <strong><?= e($c['name']) ?></strong>
                                                    <?php if ($c['code']): ?>
                                                        <span class="code-badge"><?= e($c['code']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?= e($c['city']) ?>, <?= e($c['country']) ?></td>
                                            <td><?= number_format($c['member_count']) ?></td>
                                            <td>
                                                <span class="status-badge status-<?= $c['status'] ?>">
                                                    <?= ucfirst($c['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= e($c['created_by_name'] ?? 'System') ?></td>
                                            <td><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button onclick="editCongregation(<?= $c['id'] ?>)" class="action-btn" title="Edit">✏️</button>
                                                    <a href="/congregation/admin/?id=<?= $c['id'] ?>" class="action-btn" title="Manage">⚙️</a>
                                                    <?php if ($c['status'] === 'active'): ?>
                                                        <button onclick="suspendCongregation(<?= $c['id'] ?>)" class="action-btn" title="Suspend">⏸️</button>
                                                    <?php elseif ($c['status'] === 'suspended'): ?>
                                                        <button onclick="activateCongregation(<?= $c['id'] ?>)" class="action-btn" title="Activate">▶️</button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

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
                            <p class="empty-text">No congregations found</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Congregation Modal -->
    <div id="add-congregation-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Congregation</h2>
                <button onclick="closeModal('add-congregation-modal')" class="modal-close">&times;</button>
            </div>
            <form id="add-congregation-form">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" required class="form-input">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city" required class="form-input">
                    </div>
                    <div class="form-group">
                        <label>Country</label>
                        <input type="text" name="country" required class="form-input" value="South Africa">
                    </div>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" class="form-textarea" rows="2"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Code (Optional)</label>
                        <input type="text" name="code" maxlength="10" class="form-input" placeholder="e.g., JHB-NORTH">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-select">
                            <option value="active">Active</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" onclick="closeModal('add-congregation-modal')" class="btn btn-outline">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Congregation</button>
                </div>
            </form>
        </div>
    </div>

    <script src="/admin/js/admin.js"></script>
</body>
</html>
