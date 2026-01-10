<?php
/**
 * CRC Navbar Partial - Self-contained with inline styles
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
<style>
/* Navbar Styles - Inline for reliability */
.navbar{background:#fff;border-bottom:1px solid #E5E7EB;position:sticky;top:0;z-index:100;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;}
.nav-container{max-width:1280px;margin:0 auto;padding:0 1.5rem;display:flex;align-items:center;justify-content:space-between;height:64px;}
.nav-logo{font-size:1.5rem;font-weight:800;color:#4F46E5;text-decoration:none;}
.nav-links{display:none;gap:0.5rem;}
@media(min-width:768px){.nav-links{display:flex;}}
.nav-link{padding:0.5rem 1rem;color:#4B5563;text-decoration:none;font-weight:500;border-radius:8px;transition:all 0.2s;}
.nav-link:hover,.nav-link.active{color:#4F46E5;background:rgba(79,70,229,0.05);}
.nav-actions{display:flex;align-items:center;gap:0.5rem;}
.nav-icon-btn{position:relative;width:40px;height:40px;display:flex;align-items:center;justify-content:center;color:#4B5563;border-radius:8px;transition:all 0.2s;text-decoration:none;}
.nav-icon-btn:hover{background:#F3F4F6;color:#1F2937;}
.notification-badge{position:absolute;top:4px;right:4px;min-width:18px;height:18px;padding:0 4px;background:#EF4444;color:#fff;font-size:0.75rem;font-weight:600;border-radius:9px;display:flex;align-items:center;justify-content:center;}
.user-menu{position:relative;}
.user-menu-btn{background:none;border:none;cursor:pointer;padding:4px;border-radius:50%;transition:all 0.2s;}
.user-menu-btn:hover{background:#F3F4F6;}
.user-avatar{width:36px;height:36px;border-radius:50%;object-fit:cover;}
.user-avatar-placeholder{width:36px;height:36px;border-radius:50%;background:#4F46E5;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:14px;}
.user-dropdown{position:absolute;top:100%;right:0;margin-top:0.5rem;min-width:200px;background:#fff;border-radius:12px;box-shadow:0 10px 15px -3px rgba(0,0,0,0.1);border:1px solid #E5E7EB;opacity:0;visibility:hidden;transform:translateY(-10px);transition:all 0.2s;}
.user-dropdown.show{opacity:1;visibility:visible;transform:translateY(0);}
.user-dropdown-header{padding:1rem;display:flex;flex-direction:column;}
.user-dropdown-header strong{color:#1F2937;}
.user-dropdown-header span{font-size:0.875rem;color:#6B7280;}
.user-dropdown-divider{height:1px;background:#E5E7EB;margin:0.25rem 0;}
.user-dropdown-item{display:block;padding:0.75rem 1rem;color:#374151;text-decoration:none;font-size:0.875rem;transition:all 0.2s;}
.user-dropdown-item:hover{background:#F9FAFB;}
.user-dropdown-item.logout{color:#EF4444;}
</style>
<nav class="navbar">
    <div class="nav-container">
        <a href="/" class="nav-logo">CRC</a>

        <div class="nav-links">
            <a href="/gospel_media/" class="nav-link <?= $isActive('/gospel_media') ? 'active' : '' ?>">Feed</a>
            <a href="/bible/" class="nav-link <?= $isActive('/bible') ? 'active' : '' ?>">Bible</a>
            <a href="/morning_watch/" class="nav-link <?= $isActive('/morning_watch') ? 'active' : '' ?>">Morning Study</a>
            <a href="/calendar/" class="nav-link <?= $isActive('/calendar') ? 'active' : '' ?>">Calendar</a>
            <a href="/media/" class="nav-link <?= $isActive('/media') ? 'active' : '' ?>">Media</a>
        </div>

        <div class="nav-actions">
            <a href="/notifications/" class="nav-icon-btn" title="Notifications">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <?php if ($navUnread > 0): ?>
                    <span class="notification-badge"><?= $navUnread > 9 ? '9+' : $navUnread ?></span>
                <?php endif; ?>
            </a>

            <div class="user-menu">
                <button class="user-menu-btn" onclick="toggleUserMenu()">
                    <?php if (!empty($navUser['avatar'])): ?>
                        <img src="<?= e($navUser['avatar']) ?>" alt="" class="user-avatar">
                    <?php else: ?>
                        <div class="user-avatar-placeholder"><?= strtoupper(substr($navUser['name'] ?? 'U', 0, 1)) ?></div>
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
