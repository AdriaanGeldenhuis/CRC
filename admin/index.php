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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0F172A; color: #E2E8F0; min-height: 100vh; }

        .admin-layout { display: flex; min-height: 100vh; }

        /* Sidebar */
        .admin-sidebar { width: 260px; background: #1E293B; border-right: 1px solid #334155; display: flex; flex-direction: column; position: fixed; height: 100vh; }
        .sidebar-header { padding: 1.5rem; border-bottom: 1px solid #334155; }
        .admin-logo { color: #F59E0B; font-size: 1.25rem; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; }
        .admin-logo::before { content: ''; font-size: 1.5rem; }
        .sidebar-nav { flex: 1; padding: 1rem 0; overflow-y: auto; }
        .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1.5rem; color: #94A3B8; text-decoration: none; transition: all 0.2s; }
        .nav-item:hover { background: #334155; color: #E2E8F0; }
        .nav-item.active { background: #334155; color: #F59E0B; border-left: 3px solid #F59E0B; }
        .nav-icon { width: 20px; text-align: center; }
        .sidebar-footer { padding: 1rem; border-top: 1px solid #334155; }

        /* Main Content */
        .admin-main { flex: 1; margin-left: 260px; }
        .admin-header { background: #1E293B; border-bottom: 1px solid #334155; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 10; }
        .admin-header h1 { font-size: 1.5rem; font-weight: 600; }
        .admin-user { display: flex; align-items: center; gap: 0.5rem; color: #94A3B8; font-size: 0.9rem; }
        .admin-user .badge { background: #F59E0B; color: #0F172A; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }

        .admin-content { padding: 2rem; }

        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: #1E293B; border-radius: 12px; padding: 1.5rem; border: 1px solid #334155; }
        .stat-card:hover { border-color: #475569; }
        .stat-icon { font-size: 2rem; margin-bottom: 0.75rem; display: block; }
        .stat-value { font-size: 2rem; font-weight: 700; color: white; display: block; }
        .stat-label { font-size: 0.85rem; color: #94A3B8; }

        /* Cards */
        .dashboard-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
        .card { background: #1E293B; border-radius: 12px; border: 1px solid #334155; overflow: hidden; }
        .card.full-width { grid-column: 1 / -1; }
        .card-header { padding: 1rem 1.5rem; border-bottom: 1px solid #334155; display: flex; justify-content: space-between; align-items: center; }
        .card-header h2 { font-size: 1rem; font-weight: 600; }
        .view-all { color: #F59E0B; text-decoration: none; font-size: 0.85rem; }
        .view-all:hover { text-decoration: underline; }
        .card-body { padding: 1.5rem; }

        /* Tables */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #334155; }
        .data-table th { color: #94A3B8; font-weight: 500; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .data-table td { font-size: 0.9rem; }
        .data-table tr:last-child td { border-bottom: none; }
        .data-table tr:hover td { background: rgba(51, 65, 85, 0.5); }

        /* Badges */
        .role-badge { padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 500; }
        .role-badge.super_admin { background: #F59E0B; color: #0F172A; }
        .role-badge.admin { background: #6366F1; color: white; }
        .role-badge.moderator { background: #8B5CF6; color: white; }
        .role-badge.user { background: #475569; color: #E2E8F0; }
        .status-badge { padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 500; }
        .status-badge.active { background: #10B981; color: white; }
        .status-badge.pending { background: #F59E0B; color: #0F172A; }
        .status-badge.suspended { background: #EF4444; color: white; }

        /* Health */
        .health-item { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid #334155; }
        .health-item:last-child { border-bottom: none; }
        .health-label { color: #94A3B8; font-size: 0.9rem; }
        .health-value { color: #E2E8F0; font-weight: 500; }
        .disk-bar { width: 100%; height: 8px; background: #334155; border-radius: 4px; margin: 0.5rem 0; overflow: hidden; }
        .disk-used { height: 100%; background: linear-gradient(90deg, #10B981, #F59E0B); border-radius: 4px; transition: width 0.3s; }

        /* Empty State */
        .empty-text { color: #64748B; text-align: center; padding: 2rem; }

        /* Quick Actions */
        .quick-actions { display: flex; gap: 1rem; flex-wrap: wrap; }
        .action-btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.25rem; background: #334155; border: 1px solid #475569; border-radius: 8px; color: #E2E8F0; text-decoration: none; font-size: 0.9rem; transition: all 0.2s; }
        .action-btn:hover { background: #475569; border-color: #64748B; }
        .action-btn.primary { background: #F59E0B; border-color: #F59E0B; color: #0F172A; }
        .action-btn.primary:hover { background: #D97706; }

        /* Responsive */
        @media (max-width: 1024px) {
            .dashboard-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .admin-sidebar { width: 100%; height: auto; position: relative; }
            .admin-main { margin-left: 0; }
            .admin-layout { flex-direction: column; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <a href="/admin/" class="admin-logo">CRC Super Admin</a>
            </div>
            <nav class="sidebar-nav">
                <a href="/admin/" class="nav-item active">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    </span>
                    Dashboard
                </a>
                <a href="/admin/news.php" class="nav-item">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
                    </span>
                    News
                </a>
                <a href="/admin/users.php" class="nav-item">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    </span>
                    Users
                </a>
                <a href="/admin/congregations.php" class="nav-item">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"></path><path d="M5 21V7l7-4 7 4v14"></path><path d="M9 21v-4h6v4"></path></svg>
                    </span>
                    Congregations
                </a>
                <a href="/admin/morning_study.php" class="nav-item">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line></svg>
                    </span>
                    Morning Study
                </a>
                <a href="/admin/settings.php" class="nav-item">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4"></path></svg>
                    </span>
                    Settings
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="/admin_congregation/" class="nav-item">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                    </span>
                    Congregation Admin
                </a>
                <a href="/home/" class="nav-item">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                    </span>
                    Back to App
                </a>
            </div>
        </aside>

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
                        <div class="quick-actions">
                            <a href="/admin_congregation/" class="action-btn primary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg>
                                Manage Congregations
                            </a>
                            <a href="/admin/users.php" class="action-btn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                                Add User
                            </a>
                            <a href="/admin/congregations.php" class="action-btn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
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
