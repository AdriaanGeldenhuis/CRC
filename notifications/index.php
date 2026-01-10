<?php
/**
 * CRC Notifications - Dashboard Layout
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$pageTitle = "Notifications - CRC";

$filter = $_GET['filter'] ?? 'all';
$notifications = [];
$unreadCount = 0;
$totalCount = 0;

// Get unread count
try {
    $unreadCount = Database::fetchColumn(
        "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL",
        [$user['id']]
    ) ?: 0;
} catch (Exception $e) {}

// Get total count
try {
    $totalCount = Database::fetchColumn(
        "SELECT COUNT(*) FROM notifications WHERE user_id = ?",
        [$user['id']]
    ) ?: 0;
} catch (Exception $e) {}

// Get notifications
$whereClause = "WHERE user_id = ?";
$params = [$user['id']];
if ($filter === 'unread') {
    $whereClause .= " AND read_at IS NULL";
}

try {
    $notifications = Database::fetchAll(
        "SELECT * FROM notifications $whereClause ORDER BY created_at DESC LIMIT 20",
        $params
    ) ?: [];
} catch (Exception $e) {}

// Group by date
$grouped = [];
foreach ($notifications as $notif) {
    $date = date('Y-m-d', strtotime($notif['created_at']));
    $grouped[$date][] = $notif;
}

$typeIcons = [
    'event_reminder' => '📅', 'prayer_answered' => '🙏', 'homecell_join' => '🏠',
    'course_complete' => '📚', 'new_sermon' => '🎤', 'livestream_start' => '📺',
    'announcement' => '📢', 'system' => '⚙️', 'welcome' => '👋', 'default' => '🔔'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/home/css/home.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .notif-card {
            background: linear-gradient(135deg, #3B82F6 0%, #60A5FA 100%);
            color: var(--white);
        }
        .notif-card .card-header h2 { color: var(--white); }
        .stats-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .stat-box {
            flex: 1;
            background: rgba(255,255,255,0.15);
            padding: 1rem;
            border-radius: var(--radius);
            text-align: center;
        }
        .stat-box .value { font-size: 1.5rem; font-weight: 700; }
        .stat-box .label { font-size: 0.75rem; opacity: 0.9; }
        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .filter-tab {
            flex: 1;
            padding: 0.75rem;
            background: var(--gray-100);
            border: none;
            border-radius: var(--radius);
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            color: var(--gray-600);
        }
        .filter-tab.active { background: var(--primary); color: white; }
        .notif-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .notif-item {
            display: flex;
            gap: 0.75rem;
            padding: 0.75rem;
            background: var(--gray-50);
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
        }
        .notif-item:hover { background: var(--gray-100); }
        .notif-item.unread { border-left: 3px solid var(--primary); background: rgba(79, 70, 229, 0.05); }
        .notif-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            background: var(--gray-100);
            border-radius: 50%;
        }
        .notif-content h4 { font-size: 0.875rem; color: var(--gray-800); margin-bottom: 0.25rem; }
        .notif-content p { font-size: 0.75rem; color: var(--gray-500); }
        .notif-time { font-size: 0.65rem; color: var(--gray-400); margin-top: 0.25rem; }
        .date-header {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-500);
            margin: 1rem 0 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--gray-200);
        }
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }
        .quick-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem;
            background: var(--gray-50);
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--gray-700);
            transition: var(--transition);
        }
        .quick-action:hover { background: var(--primary); color: white; }
        .quick-action-icon { font-size: 1.5rem; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <section class="welcome-section">
                <div class="welcome-content">
                    <h1>Notifications</h1>
                    <p><?= $unreadCount ?> unread notification<?= $unreadCount != 1 ? 's' : '' ?></p>
                </div>
            </section>

            <div class="dashboard-grid">
                <!-- Stats Card -->
                <div class="dashboard-card notif-card">
                    <div class="card-header">
                        <h2>Overview</h2>
                    </div>
                    <div class="stats-row">
                        <div class="stat-box">
                            <div class="value"><?= $unreadCount ?></div>
                            <div class="label">Unread</div>
                        </div>
                        <div class="stat-box">
                            <div class="value"><?= $totalCount ?></div>
                            <div class="label">Total</div>
                        </div>
                    </div>
                    <?php if ($unreadCount > 0): ?>
                        <button onclick="markAllRead()" class="btn" style="width: 100%; background: white; color: #3B82F6;">Mark All Read</button>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-card">
                    <h2 style="margin-bottom: 1rem;">Settings</h2>
                    <div class="quick-actions-grid">
                        <a href="/notifications/settings.php" class="quick-action">
                            <span class="quick-action-icon">⚙️</span>
                            <span>Preferences</span>
                        </a>
                        <a href="/notifications/?filter=unread" class="quick-action">
                            <span class="quick-action-icon">📬</span>
                            <span>Unread</span>
                        </a>
                        <a href="/notifications/archive.php" class="quick-action">
                            <span class="quick-action-icon">📦</span>
                            <span>Archive</span>
                        </a>
                        <a href="/" class="quick-action">
                            <span class="quick-action-icon">🏠</span>
                            <span>Home</span>
                        </a>
                    </div>
                </div>

                <!-- Notifications List -->
                <div class="dashboard-card" style="grid-column: span 2;">
                    <div class="card-header">
                        <h2>All Notifications</h2>
                    </div>
                    <div class="filter-tabs">
                        <a href="?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">All</a>
                        <a href="?filter=unread" class="filter-tab <?= $filter === 'unread' ? 'active' : '' ?>">Unread (<?= $unreadCount ?>)</a>
                    </div>
                    <?php if ($grouped): ?>
                        <?php foreach ($grouped as $date => $dayNotifs): ?>
                            <div class="date-header">
                                <?php
                                $today = date('Y-m-d');
                                $yesterday = date('Y-m-d', strtotime('-1 day'));
                                if ($date === $today) echo 'Today';
                                elseif ($date === $yesterday) echo 'Yesterday';
                                else echo date('l, M j', strtotime($date));
                                ?>
                            </div>
                            <div class="notif-list">
                                <?php foreach ($dayNotifs as $notif): ?>
                                    <div class="notif-item <?= $notif['read_at'] ? '' : 'unread' ?>" onclick="handleNotif(<?= $notif['id'] ?>, '<?= e($notif['link'] ?? '') ?>')">
                                        <div class="notif-icon"><?= $typeIcons[$notif['type']] ?? $typeIcons['default'] ?></div>
                                        <div class="notif-content">
                                            <h4><?= e($notif['title']) ?></h4>
                                            <p><?= e($notif['message']) ?></p>
                                            <div class="notif-time"><?= time_ago($notif['created_at']) ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 3rem; color: var(--gray-500);">
                            <p style="font-size: 2rem; margin-bottom: 0.5rem;">🔔</p>
                            <p>No notifications</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        function markAllRead() {
            fetch('/notifications/api/mark-all-read.php', { method: 'POST' })
                .then(() => location.reload());
        }
        function handleNotif(id, link) {
            fetch('/notifications/api/mark-read.php?id=' + id, { method: 'POST' })
                .then(() => { if (link) window.location = link; else location.reload(); });
        }
    </script>
</body>
</html>
