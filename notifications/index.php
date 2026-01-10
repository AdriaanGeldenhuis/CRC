<?php
/**
 * CRC Notifications - Main Page
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$pageTitle = "Notifications - CRC";

// Get filter
$filter = $_GET['filter'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query based on filter
$whereClause = "WHERE user_id = ?";
$params = [$user['id']];

if ($filter === 'unread') {
    $whereClause .= " AND read_at IS NULL";
} elseif ($filter === 'read') {
    $whereClause .= " AND read_at IS NOT NULL";
}

// Get total count
$totalCount = Database::fetchColumn(
    "SELECT COUNT(*) FROM notifications $whereClause",
    $params
);

// Get notifications
$notifications = Database::fetchAll(
    "SELECT * FROM notifications $whereClause ORDER BY created_at DESC LIMIT $perPage OFFSET $offset",
    $params
);

// Get unread count
$unreadCount = Database::fetchColumn(
    "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL",
    [$user['id']]
);

$totalPages = ceil($totalCount / $perPage);

// Group notifications by date
$grouped = [];
foreach ($notifications as $notif) {
    $date = date('Y-m-d', strtotime($notif['created_at']));
    $grouped[$date][] = $notif;
}

// Notification type icons
$typeIcons = [
    'event_reminder' => '📅',
    'prayer_answered' => '🙏',
    'homecell_join' => '🏠',
    'homecell_meeting' => '🏠',
    'course_complete' => '📚',
    'lesson_unlock' => '🔓',
    'new_sermon' => '🎤',
    'livestream_start' => '📺',
    'announcement' => '📢',
    'system' => '⚙️',
    'welcome' => '👋',
    'invite_accepted' => '✅',
    'default' => '🔔'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/notifications/css/notifications.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="page-header">
                <div class="page-title">
                    <h1>Notifications</h1>
                    <p><?= $unreadCount ?> unread notification<?= $unreadCount != 1 ? 's' : '' ?></p>
                </div>
                <div class="page-actions">
                    <?php if ($unreadCount > 0): ?>
                        <button onclick="markAllRead()" class="btn btn-outline">Mark All Read</button>
                    <?php endif; ?>
                    <a href="/notifications/settings.php" class="btn btn-outline">
                        <span class="icon">⚙️</span>
                        Settings
                    </a>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters">
                <a href="?filter=all" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
                    All
                    <span class="count"><?= $totalCount ?></span>
                </a>
                <a href="?filter=unread" class="filter-btn <?= $filter === 'unread' ? 'active' : '' ?>">
                    Unread
                    <span class="count"><?= $unreadCount ?></span>
                </a>
                <a href="?filter=read" class="filter-btn <?= $filter === 'read' ? 'active' : '' ?>">
                    Read
                </a>
            </div>

            <!-- Notifications List -->
            <?php if ($grouped): ?>
                <div class="notifications-container">
                    <?php foreach ($grouped as $date => $dayNotifs): ?>
                        <div class="date-group">
                            <h3 class="date-header">
                                <?php
                                $today = date('Y-m-d');
                                $yesterday = date('Y-m-d', strtotime('-1 day'));
                                if ($date === $today) {
                                    echo 'Today';
                                } elseif ($date === $yesterday) {
                                    echo 'Yesterday';
                                } else {
                                    echo date('l, F j', strtotime($date));
                                }
                                ?>
                            </h3>
                            <div class="notifications-list">
                                <?php foreach ($dayNotifs as $notif): ?>
                                    <div class="notification-item <?= $notif['read_at'] ? 'read' : 'unread' ?>"
                                         data-id="<?= $notif['id'] ?>"
                                         onclick="handleNotificationClick(<?= $notif['id'] ?>, '<?= e($notif['link'] ?? '') ?>')">
                                        <div class="notif-icon">
                                            <?= $typeIcons[$notif['type']] ?? $typeIcons['default'] ?>
                                        </div>
                                        <div class="notif-content">
                                            <h4><?= e($notif['title']) ?></h4>
                                            <p><?= e($notif['message']) ?></p>
                                            <span class="notif-time">
                                                <?= timeAgo($notif['created_at']) ?>
                                            </span>
                                        </div>
                                        <div class="notif-actions">
                                            <?php if (!$notif['read_at']): ?>
                                                <button onclick="event.stopPropagation(); markRead(<?= $notif['id'] ?>)"
                                                        class="action-btn" title="Mark as read">
                                                    ✓
                                                </button>
                                            <?php endif; ?>
                                            <button onclick="event.stopPropagation(); deleteNotification(<?= $notif['id'] ?>)"
                                                    class="action-btn delete" title="Delete">
                                                ×
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?filter=<?= $filter ?>&page=<?= $page - 1 ?>" class="page-btn">← Previous</a>
                            <?php endif; ?>

                            <span class="page-info">Page <?= $page ?> of <?= $totalPages ?></span>

                            <?php if ($page < $totalPages): ?>
                                <a href="?filter=<?= $filter ?>&page=<?= $page + 1 ?>" class="page-btn">Next →</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">🔔</div>
                    <h3>No notifications</h3>
                    <p>
                        <?php if ($filter === 'unread'): ?>
                            You're all caught up! No unread notifications.
                        <?php elseif ($filter === 'read'): ?>
                            No read notifications yet.
                        <?php else: ?>
                            You don't have any notifications yet.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="/notifications/js/notifications.js"></script>
</body>
</html>
