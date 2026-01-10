<?php
/**
 * CRC Notifications - Dashboard Layout
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$pageTitle = "Notifications - CRC";

$unreadCount = 0;
$notifications = [];

try {
    $unreadCount = Database::fetchColumn("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL", [$user['id']]) ?: 0;
} catch (Exception $e) {}

try {
    $notifications = Database::fetchAll("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20", [$user['id']]) ?: [];
} catch (Exception $e) {}
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
                <!-- Overview Card (Featured) -->
                <div class="dashboard-card morning-watch-card">
                    <div class="card-header">
                        <h2>Overview</h2>
                        <span class="streak-badge"><?= $unreadCount ?> new</span>
                    </div>
                    <div class="morning-watch-preview">
                        <h3><?= $unreadCount > 0 ? 'You have new notifications' : 'All caught up!' ?></h3>
                        <p class="scripture-ref"><?= $unreadCount > 0 ? 'Check your latest updates below' : 'No new notifications' ?></p>
                        <?php if ($unreadCount > 0): ?>
                            <button onclick="markAllRead()" class="btn btn-primary">Mark All Read</button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-card quick-actions-card">
                    <h2>Quick Actions</h2>
                    <div class="quick-actions-grid">
                        <a href="/notifications/?filter=unread" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                                </svg>
                            </div>
                            <span>Unread</span>
                        </a>
                        <a href="/notifications/settings.php" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="3"></circle>
                                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                                </svg>
                            </div>
                            <span>Settings</span>
                        </a>
                        <a href="/notifications/archive.php" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 8v13H3V8"></path>
                                    <path d="M1 3h22v5H1z"></path>
                                </svg>
                            </div>
                            <span>Archive</span>
                        </a>
                        <a href="/" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                </svg>
                            </div>
                            <span>Home</span>
                        </a>
                    </div>
                </div>

                <!-- Recent Notifications -->
                <div class="dashboard-card posts-card" style="grid-column: span 2;">
                    <div class="card-header">
                        <h2>All Notifications</h2>
                    </div>
                    <?php if ($notifications): ?>
                        <div class="posts-list">
                            <?php foreach ($notifications as $notif): ?>
                                <div class="post-item" onclick="handleNotif(<?= $notif['id'] ?>, '<?= e($notif['link'] ?? '') ?>')" style="cursor: pointer; <?= !$notif['read_at'] ? 'border-left: 3px solid var(--primary);' : '' ?>">
                                    <div class="post-author">
                                        <div class="author-avatar-placeholder">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                            </svg>
                                        </div>
                                        <span><?= e($notif['title']) ?></span>
                                        <span class="post-time"><?= time_ago($notif['created_at']) ?></span>
                                    </div>
                                    <p class="post-content"><?= e($notif['message']) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-content">
                            <p>No notifications yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        function markAllRead() {
            fetch('/notifications/api/mark-all-read.php', { method: 'POST' }).then(() => location.reload());
        }
        function handleNotif(id, link) {
            fetch('/notifications/api/mark-read.php?id=' + id, { method: 'POST' }).then(() => {
                if (link) window.location = link;
                else location.reload();
            });
        }
    </script>
</body>
</html>
