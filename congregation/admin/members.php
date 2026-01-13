<?php
/**
 * CRC Congregation Admin - Members Management
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();

// Check if user is congregation admin
$membership = Database::fetchOne(
    "SELECT * FROM user_congregations WHERE user_id = ? AND congregation_id = ? AND status = 'active'",
    [$user['id'], $primaryCong['id']]
);

if (!$membership || !in_array($membership['role'], ['admin', 'leader', 'pastor'])) {
    Response::redirect('/home/');
}

$pageTitle = "Members - " . e($primaryCong['name']);

// Get filters
$search = trim($_GET['search'] ?? '');
$role = $_GET['role'] ?? '';
$status = $_GET['status'] ?? 'active';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
$whereClause = "WHERE cm.congregation_id = ?";
$params = [$primaryCong['id']];

if ($search) {
    $whereClause .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($role) {
    $whereClause .= " AND cm.role = ?";
    $params[] = $role;
}

if ($status) {
    $whereClause .= " AND cm.status = ?";
    $params[] = $status;
}

// Get total count
$totalCount = Database::fetchColumn(
    "SELECT COUNT(*) FROM user_congregations cm JOIN users u ON cm.user_id = u.id $whereClause",
    $params
);

// Get members
$members = Database::fetchAll(
    "SELECT cm.*, u.name, u.email, u.avatar, u.phone,
            (SELECT name FROM homecells WHERE id = (SELECT homecell_id FROM homecell_members WHERE user_id = cm.user_id AND status = 'active' LIMIT 1)) as homecell_name
     FROM user_congregations cm
     JOIN users u ON cm.user_id = u.id
     $whereClause
     ORDER BY cm.created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

$totalPages = ceil($totalCount / $perPage);

$roles = ['member', 'leader', 'deacon', 'elder', 'pastor', 'admin'];
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
                <a href="/congregation/admin/" class="admin-logo"><?= e(truncate($primaryCong['name'], 15)) ?></a>
            </div>
            <nav class="sidebar-nav">
                <a href="/congregation/admin/" class="nav-item">
                    <span class="nav-icon">📊</span>
                    Dashboard
                </a>
                <a href="/congregation/admin/members.php" class="nav-item active">
                    <span class="nav-icon">👥</span>
                    Members
                </a>
                <a href="/congregation/admin/events.php" class="nav-item">
                    <span class="nav-icon">📅</span>
                    Events
                </a>
                <a href="/congregation/admin/homecells.php" class="nav-item">
                    <span class="nav-icon">🏠</span>
                    Homecells
                </a>
                <a href="/congregation/admin/sermons.php" class="nav-item">
                    <span class="nav-icon">🎤</span>
                    Sermons
                </a>
                <a href="/congregation/admin/announcements.php" class="nav-item">
                    <span class="nav-icon">📢</span>
                    Announcements
                </a>
                <a href="/congregation/admin/settings.php" class="nav-item">
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
                <h1>Members</h1>
                <div class="header-actions">
                    <button onclick="openModal('invite-modal')" class="btn btn-primary">
                        + Invite Member
                    </button>
                </div>
            </header>

            <div class="admin-content">
                <!-- Filters -->
                <div class="filters-bar">
                    <form class="search-form" method="GET">
                        <input type="search" name="search" placeholder="Search members..."
                               value="<?= e($search) ?>" class="search-input">
                        <button type="submit" class="btn btn-outline">Search</button>
                    </form>

                    <div class="filter-group">
                        <select name="role" onchange="applyFilter('role', this.value)" class="filter-select">
                            <option value="">All Roles</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r ?>" <?= $role === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select name="status" onchange="applyFilter('status', this.value)" class="filter-select">
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>

                <!-- Members Table -->
                <div class="card">
                    <div class="card-body">
                        <p class="result-count"><?= number_format($totalCount) ?> member<?= $totalCount != 1 ? 's' : '' ?></p>

                        <?php if ($members): ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Homecell</th>
                                        <th>Status</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($members as $m): ?>
                                        <tr>
                                            <td>
                                                <div class="user-cell">
                                                    <?php if ($m['avatar']): ?>
                                                        <img src="<?= e($m['avatar']) ?>" alt="" class="user-avatar">
                                                    <?php else: ?>
                                                        <div class="user-avatar-placeholder">
                                                            <?= strtoupper(substr($m['name'], 0, 1)) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <span><?= e($m['name']) ?></span>
                                                </div>
                                            </td>
                                            <td><?= e($m['email']) ?></td>
                                            <td>
                                                <span class="role-badge role-<?= $m['role'] ?>">
                                                    <?= ucfirst($m['role']) ?>
                                                </span>
                                            </td>
                                            <td><?= e($m['homecell_name'] ?? '-') ?></td>
                                            <td>
                                                <span class="status-badge status-<?= $m['status'] ?>">
                                                    <?= ucfirst($m['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M j, Y', strtotime($m['created_at'])) ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button onclick="editMember(<?= $m['id'] ?>)" class="action-btn" title="Edit">✏️</button>
                                                    <?php if ($m['user_id'] !== $user['id']): ?>
                                                        <button onclick="removeMember(<?= $m['id'] ?>)" class="action-btn" title="Remove">🗑️</button>
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
                            <p class="empty-text">No members found</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Invite Modal -->
    <div id="invite-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Invite Member</h2>
                <button onclick="closeModal('invite-modal')" class="modal-close">&times;</button>
            </div>
            <form id="invite-form">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required class="form-input" placeholder="Enter email address">
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" class="form-select">
                        <option value="member">Member</option>
                        <option value="leader">Leader</option>
                        <option value="deacon">Deacon</option>
                        <option value="elder">Elder</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Personal Message (Optional)</label>
                    <textarea name="message" class="form-textarea" rows="3" placeholder="Add a personal message..."></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" onclick="closeModal('invite-modal')" class="btn btn-outline">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Invite</button>
                </div>
            </form>
            <div class="invite-code-section">
                <p>Or share the congregation code:</p>
                <div class="code-display">
                    <span class="code"><?= e($primaryCong['code']) ?></span>
                    <button onclick="copyCode('<?= e($primaryCong['code']) ?>')" class="copy-btn">Copy</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const congregationId = <?= $primaryCong['id'] ?>;
    </script>
    <script src="/admin/js/admin.js"></script>
    <script src="/congregation/admin/js/congregation-admin.js"></script>
</body>
</html>
