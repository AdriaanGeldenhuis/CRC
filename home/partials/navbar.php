<?php
/**
 * CRC Navbar Partial
 * Include: <?php include __DIR__ . '/../home/partials/navbar.php'; ?>
 */

$navUser = Auth::user();
$navCong = Auth::primaryCongregation();
$navUnread = 0;

if ($navUser) {
    try {
        $navUnread = Database::fetchColumn(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL",
            [$navUser['id']]
        ) ?: 0;
    } catch (Exception $e) {
        $navUnread = 0;
    }
}

// Helper to check current path
$currentPath = $_SERVER['REQUEST_URI'] ?? '';
$isActive = function($path) use ($currentPath) {
    return strpos($currentPath, $path) === 0;
};
?>
<nav class="navbar">
    <div class="nav-container">
        <a href="/" class="nav-logo">CRC</a>

        <div class="nav-links">
            <a href="/gospel_media/" class="nav-link <?= $isActive('/gospel_media') ? 'active' : '' ?>">Feed</a>
            <a href="/bible/" class="nav-link <?= $isActive('/bible') ? 'active' : '' ?>">Bible</a>
            <a href="/morning_watch/" class="nav-link <?= $isActive('/morning_watch') ? 'active' : '' ?>">Morning Watch</a>
            <a href="/calendar/" class="nav-link <?= $isActive('/calendar') ? 'active' : '' ?>">Calendar</a>
            <a href="/media/" class="nav-link <?= $isActive('/media') ? 'active' : '' ?>">Media</a>
        </div>

        <div class="nav-actions">
            <a href="/notifications/" class="nav-icon-btn" title="Notifications">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <?php if ($navUnread > 0): ?>
                    <span class="notification-badge"><?= $navUnread > 9 ? '9+' : $navUnread ?></span>
                <?php endif; ?>
            </a>

            <div class="user-menu">
                <button class="user-menu-btn" onclick="toggleUserMenu()">
                    <?php if ($navUser['avatar']): ?>
                        <img src="<?= e($navUser['avatar']) ?>" alt="" class="user-avatar">
                    <?php else: ?>
                        <div class="user-avatar-placeholder"><?= strtoupper(substr($navUser['name'], 0, 1)) ?></div>
                    <?php endif; ?>
                </button>
                <div class="user-dropdown" id="userDropdown">
                    <div class="user-dropdown-header">
                        <strong><?= e($navUser['name']) ?></strong>
                        <?php if ($navCong): ?>
                            <span><?= e($navCong['name']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="user-dropdown-divider"></div>
                    <a href="/profile/" class="user-dropdown-item">Profile</a>
                    <a href="/diary/" class="user-dropdown-item">My Diary</a>
                    <a href="/homecells/" class="user-dropdown-item">Homecells</a>
                    <a href="/learning/" class="user-dropdown-item">Courses</a>
                    <?php if ($navCong && Auth::isCongregationAdmin($navCong['id'])): ?>
                        <div class="user-dropdown-divider"></div>
                        <a href="/admin_congregation/" class="user-dropdown-item">Manage Congregation</a>
                    <?php endif; ?>
                    <?php if (Auth::isAdmin()): ?>
                        <a href="/admin/" class="user-dropdown-item">Admin Panel</a>
                    <?php endif; ?>
                    <div class="user-dropdown-divider"></div>
                    <a href="/auth/logout.php" class="user-dropdown-item logout">Logout</a>
                </div>
            </div>
        </div>
    </div>
</nav>

<script>
function toggleUserMenu() {
    document.getElementById('userDropdown').classList.toggle('show');
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.user-menu')) {
        document.getElementById('userDropdown')?.classList.remove('show');
    }
});
</script>
