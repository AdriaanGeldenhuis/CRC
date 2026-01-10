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
.nav-icon-btn{position:relative;width:40px;height:40px;display:flex;align-items:center;justify-content:center;color:#4B5563;border-radius:8px;transition:all 0.2s;text-decoration:none;background:none;border:none;cursor:pointer;}
.nav-icon-btn:hover{background:#F3F4F6;color:#1F2937;}
.notification-badge{position:absolute;top:4px;right:4px;min-width:18px;height:18px;padding:0 4px;background:#EF4444;color:#fff;font-size:0.75rem;font-weight:600;border-radius:9px;display:flex;align-items:center;justify-content:center;}

/* 3-dot More Menu */
.more-menu{position:relative;}
.more-menu-btn{background:none;border:none;cursor:pointer;width:40px;height:40px;display:flex;align-items:center;justify-content:center;color:#4B5563;border-radius:8px;transition:all 0.2s;}
.more-menu-btn:hover{background:#F3F4F6;color:#1F2937;}
.more-dropdown{position:absolute;top:100%;right:0;margin-top:0.5rem;min-width:200px;background:#fff;border-radius:12px;box-shadow:0 10px 15px -3px rgba(0,0,0,0.1);border:1px solid #E5E7EB;opacity:0;visibility:hidden;transform:translateY(-10px);transition:all 0.2s;z-index:1000;}
.more-dropdown.show{opacity:1;visibility:visible;transform:translateY(0);}
.more-dropdown-item{display:flex;align-items:center;gap:0.75rem;padding:0.75rem 1rem;color:#374151;text-decoration:none;font-size:0.875rem;transition:all 0.2s;}
.more-dropdown-item:hover{background:#F9FAFB;}
.more-dropdown-item svg{width:18px;height:18px;color:#6B7280;}
.more-dropdown-divider{height:1px;background:#E5E7EB;margin:0.25rem 0;}

/* User Menu */
.user-menu{position:relative;}
.user-menu-btn{background:none;border:none;cursor:pointer;padding:4px;border-radius:50%;transition:all 0.2s;}
.user-menu-btn:hover{background:#F3F4F6;}
.user-avatar{width:36px;height:36px;border-radius:50%;object-fit:cover;}
.user-avatar-placeholder{width:36px;height:36px;border-radius:50%;background:#4F46E5;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:14px;}
.user-dropdown{position:absolute;top:100%;right:0;margin-top:0.5rem;min-width:200px;background:#fff;border-radius:12px;box-shadow:0 10px 15px -3px rgba(0,0,0,0.1);border:1px solid #E5E7EB;opacity:0;visibility:hidden;transform:translateY(-10px);transition:all 0.2s;z-index:1000;}
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

            <!-- 3-dot More Menu -->
            <div class="more-menu">
                <button class="more-menu-btn" onclick="toggleMoreMenu()" title="More">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                        <circle cx="12" cy="5" r="2"></circle>
                        <circle cx="12" cy="12" r="2"></circle>
                        <circle cx="12" cy="19" r="2"></circle>
                    </svg>
                </button>
                <div class="more-dropdown" id="moreDropdown">
                    <a href="/gospel_media/" class="more-dropdown-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 11a9 9 0 0 1 9 9"></path>
                            <path d="M4 4a16 16 0 0 1 16 16"></path>
                            <circle cx="5" cy="19" r="1"></circle>
                        </svg>
                        Feed
                    </a>
                    <a href="/bible/" class="more-dropdown-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                            <path d="M12 6v7"></path>
                            <path d="M8 9h8"></path>
                        </svg>
                        Bible
                    </a>
                    <a href="/morning_watch/" class="more-dropdown-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="5"></circle>
                            <line x1="12" y1="1" x2="12" y2="3"></line>
                            <line x1="12" y1="21" x2="12" y2="23"></line>
                            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                            <line x1="1" y1="12" x2="3" y2="12"></line>
                            <line x1="21" y1="12" x2="23" y2="12"></line>
                            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                        </svg>
                        Morning Study
                    </a>
                    <a href="/calendar/" class="more-dropdown-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        Calendar
                    </a>
                    <a href="/media/" class="more-dropdown-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="23 7 16 12 23 17 23 7"></polygon>
                            <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
                        </svg>
                        Media
                    </a>
                    <div class="more-dropdown-divider"></div>
                    <a href="/diary/" class="more-dropdown-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                        </svg>
                        My Diary
                    </a>
                    <a href="/homecells/" class="more-dropdown-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                        Homecells
                    </a>
                    <a href="/learning/" class="more-dropdown-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                            <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                        </svg>
                        Courses
                    </a>
                </div>
            </div>

            <!-- User Profile Menu -->
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
    document.getElementById('moreDropdown')?.classList.remove('show');
    document.getElementById('userDropdown').classList.toggle('show');
}

function toggleMoreMenu() {
    document.getElementById('userDropdown')?.classList.remove('show');
    document.getElementById('moreDropdown').classList.toggle('show');
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.user-menu')) {
        document.getElementById('userDropdown')?.classList.remove('show');
    }
    if (!e.target.closest('.more-menu')) {
        document.getElementById('moreDropdown')?.classList.remove('show');
    }
});
</script>
