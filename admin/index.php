<?php
/**
 * CRC Super Admin Dashboard
 * Global administration panel for super admins
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();
Auth::requireRole('super_admin');

$user = Auth::user();
$pageTitle = "Super Admin - CRC";

// Initialize stats with defaults
$stats = [
    'users' => 0,
    'congregations' => 0,
    'members' => 0,
    'sessions' => 0,
    'events' => 0,
    'invites' => 0
];

// Safely get statistics
try {
    $stats['users'] = Database::fetchColumn("SELECT COUNT(*) FROM users") ?? 0;
} catch (Exception $e) {}

try {
    $stats['congregations'] = Database::fetchColumn("SELECT COUNT(*) FROM congregations WHERE status = 'active'") ?? 0;
} catch (Exception $e) {}

try {
    $stats['members'] = Database::fetchColumn("SELECT COUNT(*) FROM user_congregations WHERE status = 'active'") ?? 0;
} catch (Exception $e) {}

try {
    $stats['sessions'] = Database::fetchColumn("SELECT COUNT(*) FROM morning_sessions") ?? 0;
} catch (Exception $e) {}

try {
    $stats['events'] = Database::fetchColumn("SELECT COUNT(*) FROM events WHERE status = 'published'") ?? 0;
} catch (Exception $e) {}

try {
    $stats['invites'] = Database::fetchColumn("SELECT COUNT(*) FROM congregation_invites WHERE status = 'pending'") ?? 0;
} catch (Exception $e) {}

// Get recent users
$recentUsers = [];
try {
    $recentUsers = Database::fetchAll(
        "SELECT id, name, email, global_role, status, created_at
         FROM users
         ORDER BY created_at DESC
         LIMIT 10"
    ) ?: [];
} catch (Exception $e) {}

// Get all congregations
$congregations = [];
try {
    $congregations = Database::fetchAll(
        "SELECT c.*,
                (SELECT COUNT(*) FROM user_congregations uc WHERE uc.congregation_id = c.id AND uc.status = 'active') as member_count
         FROM congregations c
         ORDER BY c.name ASC"
    ) ?: [];
} catch (Exception $e) {}

// Get system health
$systemHealth = [
    'php_version' => phpversion(),
    'mysql_version' => 'Unknown',
    'disk_free' => 0,
    'disk_total' => 1
];

try {
    $systemHealth['mysql_version'] = Database::fetchColumn("SELECT VERSION()") ?? 'Unknown';
} catch (Exception $e) {}

try {
    $systemHealth['disk_free'] = disk_free_space('/') ?: 0;
    $systemHealth['disk_total'] = disk_total_space('/') ?: 1;
} catch (Exception $e) {}

// Helper function for formatting bytes
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}

// Helper function for time ago
function timeAgoAdmin($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', $time);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/admin/css/admin.css">
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
                <h1>Super Admin Dashboard</h1>
                <div class="admin-user">
                    <?= e($user['name']) ?>
                    <span class="badge">Super Admin</span>
                </div>
            </header>

            <div class="admin-content">
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="stat-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#60A5FA" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                        </span>
                        <span class="stat-value"><?= number_format($stats['users']) ?></span>
                        <span class="stat-label">Total Users</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#34D399" stroke-width="2"><path d="M3 21h18"></path><path d="M5 21V7l7-4 7 4v14"></path></svg>
                        </span>
                        <span class="stat-value"><?= number_format($stats['congregations']) ?></span>
                        <span class="stat-label">Congregations</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#A78BFA" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                        </span>
                        <span class="stat-value"><?= number_format($stats['members']) ?></span>
                        <span class="stat-label">Memberships</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#FBBF24" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line></svg>
                        </span>
                        <span class="stat-value"><?= number_format($stats['sessions']) ?></span>
                        <span class="stat-label">Study Sessions</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#F472B6" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line></svg>
                        </span>
                        <span class="stat-value"><?= number_format($stats['events']) ?></span>
                        <span class="stat-label">Events</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#FB923C" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        </span>
                        <span class="stat-value"><?= number_format($stats['invites']) ?></span>
                        <span class="stat-label">Pending Invites</span>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-header">
                        <h2>Quick Actions</h2>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions-grid">
                            <a href="/admin_congregation/" class="quick-action">
                                <span class="action-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg>
                                </span>
                                Manage Congregations
                            </a>
                            <a href="/admin/users.php" class="quick-action">
                                <span class="action-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                                </span>
                                Add User
                            </a>
                            <a href="/admin/congregations.php" class="quick-action">
                                <span class="action-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                </span>
                                Add Congregation
                            </a>
                        </div>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <!-- Recent Users -->
                    <div class="card">
                        <div class="card-header">
                            <h2>Recent Users</h2>
                            <a href="/admin/users.php" class="view-all">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if ($recentUsers): ?>
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Role</th>
                                            <th>Joined</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentUsers as $u): ?>
                                            <tr>
                                                <td>
                                                    <div><?= e($u['name']) ?></div>
                                                    <div style="font-size: 0.8rem; color: #64748B;"><?= e($u['email']) ?></div>
                                                </td>
                                                <td>
                                                    <span class="role-badge <?= e($u['global_role']) ?>"><?= e($u['global_role']) ?></span>
                                                </td>
                                                <td><?= timeAgoAdmin($u['created_at']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="empty-text">No users found</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- System Health -->
                    <div class="card">
                        <div class="card-header">
                            <h2>System Health</h2>
                        </div>
                        <div class="card-body">
                            <div class="health-item">
                                <span class="health-label">PHP Version</span>
                                <span class="health-value"><?= e($systemHealth['php_version']) ?></span>
                            </div>
                            <div class="health-item">
                                <span class="health-label">MySQL Version</span>
                                <span class="health-value"><?= e($systemHealth['mysql_version']) ?></span>
                            </div>
                            <div class="health-item">
                                <span class="health-label">Server Time</span>
                                <span class="health-value"><?= date('Y-m-d H:i:s') ?></span>
                            </div>
                            <div class="health-item">
                                <span class="health-label">Disk Space</span>
                                <span class="health-value">
                                    <?= formatBytes($systemHealth['disk_free']) ?> free
                                </span>
                            </div>
                            <div class="disk-bar">
                                <?php
                                $diskUsed = $systemHealth['disk_total'] - $systemHealth['disk_free'];
                                $diskPercent = $systemHealth['disk_total'] > 0 ? ($diskUsed / $systemHealth['disk_total']) * 100 : 0;
                                ?>
                                <div class="disk-used" style="width: <?= min($diskPercent, 100) ?>%"></div>
                            </div>
                            <div style="text-align: center; color: #64748B; font-size: 0.85rem;">
                                <?= formatBytes($diskUsed) ?> used of <?= formatBytes($systemHealth['disk_total']) ?>
                            </div>
                        </div>
                    </div>

                    <!-- Congregations Overview -->
                    <div class="card full-width">
                        <div class="card-header">
                            <h2>All Congregations</h2>
                            <a href="/admin/congregations.php" class="view-all">Manage</a>
                        </div>
                        <div class="card-body">
                            <?php if ($congregations): ?>
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Status</th>
                                            <th>Members</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($congregations as $cong): ?>
                                            <tr>
                                                <td>
                                                    <div><?= e($cong['name']) ?></div>
                                                    <?php if (!empty($cong['city'])): ?>
                                                        <div style="font-size: 0.8rem; color: #64748B;"><?= e($cong['city']) ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="status-badge <?= e($cong['status']) ?>"><?= e($cong['status']) ?></span>
                                                </td>
                                                <td><?= number_format($cong['member_count'] ?? 0) ?></td>
                                                <td>
                                                    <a href="/admin_congregation/?cong_id=<?= $cong['id'] ?>" class="action-btn" style="padding: 0.5rem 0.75rem; font-size: 0.8rem;">
                                                        Manage
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="empty-text">No congregations found</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
