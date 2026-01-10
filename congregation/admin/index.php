<?php
/**
 * CRC Congregation Admin - Dashboard
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();

// Check if user is congregation admin
$membership = Database::fetchOne(
    "SELECT * FROM congregation_members WHERE user_id = ? AND congregation_id = ? AND status = 'active'",
    [$user['id'], $primaryCong['id']]
);

if (!$membership || !in_array($membership['role'], ['admin', 'leader', 'pastor'])) {
    Response::redirect('/home/');
}

$pageTitle = "Congregation Admin - " . e($primaryCong['name']);

// Get statistics
$stats = [
    'members' => Database::fetchColumn(
        "SELECT COUNT(*) FROM congregation_members WHERE congregation_id = ? AND status = 'active'",
        [$primaryCong['id']]
    ),
    'pending_members' => Database::fetchColumn(
        "SELECT COUNT(*) FROM congregation_members WHERE congregation_id = ? AND status = 'pending'",
        [$primaryCong['id']]
    ),
    'events' => Database::fetchColumn(
        "SELECT COUNT(*) FROM events WHERE congregation_id = ? AND event_date >= CURDATE()",
        [$primaryCong['id']]
    ),
    'homecells' => Database::fetchColumn(
        "SELECT COUNT(*) FROM homecells WHERE congregation_id = ? AND status = 'active'",
        [$primaryCong['id']]
    ),
    'sermons' => Database::fetchColumn(
        "SELECT COUNT(*) FROM sermons WHERE congregation_id = ? AND status = 'published'",
        [$primaryCong['id']]
    )
];

// Recent members
$recentMembers = Database::fetchAll(
    "SELECT u.name, u.email, cm.created_at, cm.role
     FROM congregation_members cm
     JOIN users u ON cm.user_id = u.id
     WHERE cm.congregation_id = ? AND cm.status = 'active'
     ORDER BY cm.created_at DESC LIMIT 5",
    [$primaryCong['id']]
);

// Pending invites
$pendingInvites = Database::fetchAll(
    "SELECT * FROM congregation_invites WHERE congregation_id = ? AND status = 'pending' AND expires_at > NOW()
     ORDER BY created_at DESC LIMIT 5",
    [$primaryCong['id']]
);

// Upcoming events
$upcomingEvents = Database::fetchAll(
    "SELECT * FROM events WHERE congregation_id = ? AND event_date >= CURDATE()
     ORDER BY event_date ASC, start_time ASC LIMIT 5",
    [$primaryCong['id']]
);
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
                <a href="/congregation/admin/" class="nav-item active">
                    <span class="nav-icon">📊</span>
                    Dashboard
                </a>
                <a href="/congregation/admin/members.php" class="nav-item">
                    <span class="nav-icon">👥</span>
                    Members
                    <?php if ($stats['pending_members'] > 0): ?>
                        <span class="nav-badge"><?= $stats['pending_members'] ?></span>
                    <?php endif; ?>
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
                <h1>Dashboard</h1>
                <div class="header-actions">
                    <span class="admin-user"><?= e($user['name']) ?> (<?= ucfirst($membership['role']) ?>)</span>
                </div>
            </header>

            <div class="admin-content">
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="stat-icon">👥</span>
                        <div class="stat-info">
                            <span class="stat-value"><?= number_format($stats['members']) ?></span>
                            <span class="stat-label">Members</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-icon">⏳</span>
                        <div class="stat-info">
                            <span class="stat-value"><?= number_format($stats['pending_members']) ?></span>
                            <span class="stat-label">Pending</span>
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
                    <!-- Recent Members -->
                    <div class="card">
                        <div class="card-header">
                            <h2>Recent Members</h2>
                            <a href="/congregation/admin/members.php" class="view-all">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if ($recentMembers): ?>
                                <div class="member-list">
                                    <?php foreach ($recentMembers as $m): ?>
                                        <div class="member-item">
                                            <div class="member-avatar-placeholder">
                                                <?= strtoupper(substr($m['name'], 0, 1)) ?>
                                            </div>
                                            <div class="member-info">
                                                <span class="member-name"><?= e($m['name']) ?></span>
                                                <span class="member-role"><?= ucfirst($m['role']) ?></span>
                                            </div>
                                            <span class="member-date"><?= timeAgo($m['created_at']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="empty-text">No members yet</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Upcoming Events -->
                    <div class="card">
                        <div class="card-header">
                            <h2>Upcoming Events</h2>
                            <a href="/congregation/admin/events.php" class="view-all">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if ($upcomingEvents): ?>
                                <div class="event-list">
                                    <?php foreach ($upcomingEvents as $event): ?>
                                        <div class="event-item">
                                            <div class="event-date-badge">
                                                <span class="day"><?= date('d', strtotime($event['event_date'])) ?></span>
                                                <span class="month"><?= date('M', strtotime($event['event_date'])) ?></span>
                                            </div>
                                            <div class="event-info">
                                                <span class="event-title"><?= e($event['title']) ?></span>
                                                <span class="event-time"><?= date('g:i A', strtotime($event['start_time'])) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="empty-text">No upcoming events</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card full-width">
                    <div class="card-header">
                        <h2>Quick Actions</h2>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions-grid">
                            <a href="/congregation/admin/members.php?action=invite" class="quick-action">
                                <span class="action-icon">✉️</span>
                                <span>Invite Member</span>
                            </a>
                            <a href="/congregation/admin/events.php?action=add" class="quick-action">
                                <span class="action-icon">📅</span>
                                <span>Create Event</span>
                            </a>
                            <a href="/congregation/admin/sermons.php?action=add" class="quick-action">
                                <span class="action-icon">🎤</span>
                                <span>Add Sermon</span>
                            </a>
                            <a href="/congregation/admin/announcements.php?action=add" class="quick-action">
                                <span class="action-icon">📢</span>
                                <span>Post Announcement</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="/admin/js/admin.js"></script>
</body>
</html>
