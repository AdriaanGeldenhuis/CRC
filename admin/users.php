<?php
/**
 * CRC Global Admin - Users Management
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();
Auth::requireRole('super_admin');

$user = Auth::user();
$pageTitle = "Users - Admin";

// Get filters
$search = trim($_GET['search'] ?? '');
$role = $_GET['role'] ?? '';
$status = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
$whereClause = "WHERE 1=1";
$params = [];

if ($search) {
    $whereClause .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($role) {
    $whereClause .= " AND u.global_role = ?";
    $params[] = $role;
}

if ($status === 'active') {
    $whereClause .= " AND u.email_verified_at IS NOT NULL AND u.deleted_at IS NULL";
} elseif ($status === 'unverified') {
    $whereClause .= " AND u.email_verified_at IS NULL";
} elseif ($status === 'deleted') {
    $whereClause .= " AND u.deleted_at IS NOT NULL";
}

// Get total count
$totalCount = Database::fetchColumn(
    "SELECT COUNT(*) FROM users u $whereClause",
    $params
);

// Get users
$users = Database::fetchAll(
    "SELECT u.*,
            (SELECT COUNT(*) FROM user_congregations WHERE user_id = u.id) as congregation_count
     FROM users u
     $whereClause
     ORDER BY u.created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

$totalPages = ceil($totalCount / $perPage);

$roles = [
    'user' => 'User',
    'moderator' => 'Moderator',
    'admin' => 'Admin',
    'super_admin' => 'Super Admin'
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
                <a href="/admin/users.php" class="nav-item active">
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
                <h1>Users</h1>
                <div class="header-actions">
                    <button onclick="openModal('add-user-modal')" class="btn btn-primary">
                        + Add User
                    </button>
                </div>
            </header>

            <div class="admin-content">
                <!-- Filters -->
                <div class="filters-bar">
                    <form class="search-form" method="GET">
                        <input type="search" name="search" placeholder="Search users..."
                               value="<?= e($search) ?>" class="search-input">
                        <button type="submit" class="btn btn-outline">Search</button>
                    </form>

                    <div class="filter-group">
                        <select name="role" onchange="applyFilter('role', this.value)" class="filter-select">
                            <option value="">All Roles</option>
                            <?php foreach ($roles as $val => $label): ?>
                                <option value="<?= $val ?>" <?= $role === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select name="status" onchange="applyFilter('status', this.value)" class="filter-select">
                            <option value="">All Status</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="unverified" <?= $status === 'unverified' ? 'selected' : '' ?>>Unverified</option>
                            <option value="deleted" <?= $status === 'deleted' ? 'selected' : '' ?>>Deleted</option>
                        </select>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="card">
                    <div class="card-body">
                        <p class="result-count"><?= number_format($totalCount) ?> user<?= $totalCount != 1 ? 's' : '' ?></p>

                        <?php if ($users): ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Congregations</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $u): ?>
                                        <tr>
                                            <td>
                                                <div class="user-cell">
                                                    <?php if (!empty($u['avatar'])): ?>
                                                        <img src="<?= e($u['avatar']) ?>" alt="" class="user-avatar">
                                                    <?php else: ?>
                                                        <div class="user-avatar-placeholder">
                                                            <?= strtoupper(substr($u['name'], 0, 1)) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <span><?= e($u['name']) ?></span>
                                                </div>
                                            </td>
                                            <td><?= e($u['email']) ?></td>
                                            <td>
                                                <span class="role-badge role-<?= $u['global_role'] ?>">
                                                    <?= $roles[$u['global_role']] ?? $u['global_role'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($u['deleted_at']): ?>
                                                    <span class="status-badge status-deleted">Deleted</span>
                                                <?php elseif ($u['email_verified_at']): ?>
                                                    <span class="status-badge status-active">Active</span>
                                                <?php else: ?>
                                                    <span class="status-badge status-pending">Unverified</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $u['congregation_count'] ?></td>
                                            <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button onclick="editUser(<?= $u['id'] ?>)" class="action-btn" title="Edit">✏️</button>
                                                    <?php if (!$u['deleted_at'] && $u['id'] !== $user['id']): ?>
                                                        <button onclick="deleteUser(<?= $u['id'] ?>)" class="action-btn" title="Delete">🗑️</button>
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
                            <p class="empty-text">No users found</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add User Modal -->
    <div id="add-user-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add User</h2>
                <button onclick="closeModal('add-user-modal')" class="modal-close">&times;</button>
            </div>
            <form id="add-user-form">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" required class="form-input">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required class="form-input">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required minlength="8" class="form-input">
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="global_role" class="form-select">
                        <?php foreach ($roles as $val => $label): ?>
                            <option value="<?= $val ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" onclick="closeModal('add-user-modal')" class="btn btn-outline">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="edit-user-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit User</h2>
                <button onclick="closeModal('edit-user-modal')" class="modal-close">&times;</button>
            </div>
            <form id="edit-user-form">
                <input type="hidden" name="user_id" id="edit-user-id">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" id="edit-user-name" required class="form-input">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="edit-user-email" required class="form-input">
                </div>
                <div class="form-group">
                    <label>New Password (leave blank to keep current)</label>
                    <input type="password" name="password" minlength="8" class="form-input">
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="global_role" id="edit-user-role" class="form-select">
                        <?php foreach ($roles as $val => $label): ?>
                            <option value="<?= $val ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" onclick="closeModal('edit-user-modal')" class="btn btn-outline">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script src="/admin/js/admin.js"></script>
</body>
</html>
