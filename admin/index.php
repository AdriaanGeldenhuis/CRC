<?php
/**
 * CRC Global Admin - Dashboard
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();
Auth::requireRole('super_admin');

$user = Auth::user();
$pageTitle = "Admin Dashboard - CRC";

// Get statistics
$stats = [
    'users' => Database::fetchColumn("SELECT COUNT(*) FROM users"),
    'congregations' => Database::fetchColumn("SELECT COUNT(*) FROM congregations WHERE status = 'active'"),
    'sermons' => Database::fetchColumn("SELECT COUNT(*) FROM sermons WHERE status = 'published'"),
    'courses' => Database::fetchColumn("SELECT COUNT(*) FROM courses WHERE status = 'published'"),
    'events' => Database::fetchColumn("SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()"),
    'homecells' => Database::fetchColumn("SELECT COUNT(*) FROM homecells WHERE status = 'active'")
];

// Get recent users
$recentUsers = Database::fetchAll(
    "SELECT id, name, email, created_at FROM users ORDER BY created_at DESC LIMIT 5"
);

// Get recent activity
$recentActivity = Database::fetchAll(
    "SELECT al.*, u.name as user_name
     FROM activity_log al
     LEFT JOIN users u ON al.user_id = u.id
     ORDER BY al.created_at DESC LIMIT 10"
);

// Get system health
$systemHealth = [
    'php_version' => phpversion(),
    'mysql_version' => Database::fetchColumn("SELECT VERSION()"),
    'disk_free' => disk_free_space('/'),
    'disk_total' => disk_total_space('/')
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
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <a href="/admin/" class="admin-logo">CRC Admin</a>
            </div>
            <nav class="sidebar-nav">
                <a href="/admin/" class="nav-item active">
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
                <h1>Dashboard</h1>
                <div class="header-actions">
                    <span class="admin-user">
                        <?= e($user['name']) ?> (Super Admin)
                    </span>
                </div>
            </header>

            <div class="admin-content">
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="stat-icon">👥</span>
                        <div class="stat-info">
                            <span class="stat-value"><?= number_format($stats['users']) ?></span>
                            <span class="stat-label">Total Users</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-icon">⛪</span>
                        <div class="stat-info">
                            <span class="stat-value"><?= number_format($stats['congregations']) ?></span>
                            <span class="stat-label">Congregations</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-icon">🎤</span>
                        <div class="stat-info">
                            <span class="stat-value"><?= number_format($stats['sermons']) ?></span>
                            <span class="stat-label">Sermons</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-icon">📚</span>
                        <div class="stat-info">
                            <span class="stat-value"><?= number_format($stats['courses']) ?></span>
                            <span class="stat-label">Courses</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-icon">📅</span>
                        <div class="stat-info">
                            <span class="stat-value"><?= number_format($stats['events']) ?></span>
                            <span class="stat-label">Upcoming Events</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-icon">🏠</span>
                        <div class="stat-info">
                            <span class="stat-value"><?= number_format($stats['homecells']) ?></span>
                            <span class="stat-label">Homecells</span>
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
                                            <th>Email</th>
                                            <th>Joined</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentUsers as $u): ?>
                                            <tr>
                                                <td><?= e($u['name']) ?></td>
                                                <td><?= e($u['email']) ?></td>
                                                <td><?= timeAgo($u['created_at']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="empty-text">No users yet</p>
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
                                <span class="health-value"><?= $systemHealth['php_version'] ?></span>
                            </div>
                            <div class="health-item">
                                <span class="health-label">MySQL Version</span>
                                <span class="health-value"><?= $systemHealth['mysql_version'] ?></span>
                            </div>
                            <div class="health-item">
                                <span class="health-label">Disk Space</span>
                                <div class="disk-bar">
                                    <?php
                                    $diskUsed = $systemHealth['disk_total'] - $systemHealth['disk_free'];
                                    $diskPercent = ($diskUsed / $systemHealth['disk_total']) * 100;
                                    ?>
                                    <div class="disk-used" style="width: <?= $diskPercent ?>%"></div>
                                </div>
                                <span class="health-value">
                                    <?= formatBytes($systemHealth['disk_free']) ?> free of <?= formatBytes($systemHealth['disk_total']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card full-width">
                    <div class="card-header">
                        <h2>Recent Activity</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($recentActivity): ?>
                            <div class="activity-list">
                                <?php foreach ($recentActivity as $activity): ?>
                                    <div class="activity-item">
                                        <span class="activity-icon">●</span>
                                        <div class="activity-content">
                                            <span class="activity-text">
                                                <strong><?= e($activity['user_name'] ?? 'System') ?></strong>
                                                <?= e($activity['action']) ?>
                                            </span>
                                            <span class="activity-time"><?= timeAgo($activity['created_at']) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="empty-text">No recent activity</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="/admin/js/admin.js"></script>
</body>
</html>
<?php
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}
?>
