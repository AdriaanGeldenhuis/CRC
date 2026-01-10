<?php
/**
 * CRC Navbar Partial - Modern Glass Morphism Design with Theme Toggle
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
<script>
// Load saved theme before page renders to prevent flash
(function() {
    const saved = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-theme', saved);
})();
</script>
<style>
/* ===== CSS VARIABLES FOR THEMING ===== */
:root {
  --nav-primary: #6366F1;
  --nav-primary-light: #818CF8;
  --nav-primary-glow: rgba(99, 102, 241, 0.4);
  --nav-accent-glow: rgba(34, 211, 238, 0.3);
  --nav-danger: #EF4444;
  --nav-bg-glass: rgba(255, 255, 255, 0.05);
  --nav-bg-glass-hover: rgba(255, 255, 255, 0.08);
  --nav-bg-card: rgba(26, 26, 46, 0.9);
  --nav-text-primary: #F8FAFC;
  --nav-text-secondary: #94A3B8;
  --nav-text-muted: #64748B;
  --nav-border-primary: rgba(255, 255, 255, 0.1);
  --nav-border-accent: rgba(99, 102, 241, 0.3);
  --nav-shadow-md: 0 4px 16px rgba(0, 0, 0, 0.4);
  --nav-shadow-xl: 0 16px 48px rgba(0, 0, 0, 0.6);
  --nav-blur: blur(16px);
  --nav-radius-md: 12px;
  --nav-radius-lg: 16px;
  --nav-radius-full: 9999px;
  --nav-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  --nav-font: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

[data-theme="light"] {
  --nav-bg-glass: rgba(255, 255, 255, 0.7);
  --nav-bg-glass-hover: rgba(255, 255, 255, 0.9);
  --nav-bg-card: rgba(255, 255, 255, 0.95);
  --nav-text-primary: #0F172A;
  --nav-text-secondary: #475569;
  --nav-text-muted: #94A3B8;
  --nav-border-primary: rgba(0, 0, 0, 0.1);
  --nav-shadow-md: 0 4px 16px rgba(0, 0, 0, 0.1);
  --nav-shadow-xl: 0 16px 48px rgba(0, 0, 0, 0.15);
}

/* ===== NAVBAR STYLES ===== */
.navbar {
  position: sticky;
  top: 0;
  z-index: 1000;
  background: var(--nav-bg-glass);
  backdrop-filter: var(--nav-blur);
  -webkit-backdrop-filter: var(--nav-blur);
  border-bottom: 1px solid var(--nav-border-primary);
  font-family: var(--nav-font);
  transition: var(--nav-transition);
}

.nav-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 0 1.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 70px;
}

.nav-logo {
  font-size: 1.75rem;
  font-weight: 800;
  background: linear-gradient(135deg, var(--nav-primary) 0%, var(--nav-primary-light) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  text-decoration: none;
  letter-spacing: -0.5px;
  transition: var(--nav-transition);
}

.nav-logo:hover {
  transform: scale(1.05);
  filter: brightness(1.2);
}

.nav-actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

/* Theme Toggle Button */
.theme-toggle {
  position: relative;
  width: 44px;
  height: 44px;
  background: var(--nav-bg-glass);
  border: 1px solid var(--nav-border-primary);
  border-radius: var(--nav-radius-md);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--nav-text-secondary);
  transition: var(--nav-transition);
  overflow: hidden;
}

.theme-toggle:hover {
  background: var(--nav-bg-glass-hover);
  border-color: var(--nav-border-accent);
  color: var(--nav-primary);
  transform: translateY(-2px);
  box-shadow: var(--nav-shadow-md), 0 0 20px var(--nav-primary-glow);
}

.theme-toggle svg {
  width: 20px;
  height: 20px;
  transition: var(--nav-transition);
}

.theme-toggle .sun-icon {
  position: absolute;
  opacity: 0;
  transform: rotate(-90deg) scale(0);
}

.theme-toggle .moon-icon {
  position: absolute;
  opacity: 1;
  transform: rotate(0deg) scale(1);
}

[data-theme="light"] .theme-toggle .sun-icon {
  opacity: 1;
  transform: rotate(0deg) scale(1);
}

[data-theme="light"] .theme-toggle .moon-icon {
  opacity: 0;
  transform: rotate(90deg) scale(0);
}

/* Nav Icon Button */
.nav-icon-btn {
  position: relative;
  width: 44px;
  height: 44px;
  background: var(--nav-bg-glass);
  border: 1px solid var(--nav-border-primary);
  border-radius: var(--nav-radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--nav-text-secondary);
  text-decoration: none;
  transition: var(--nav-transition);
}

.nav-icon-btn:hover {
  background: var(--nav-bg-glass-hover);
  border-color: var(--nav-border-accent);
  color: var(--nav-primary);
  transform: translateY(-2px);
  box-shadow: var(--nav-shadow-md), 0 0 20px var(--nav-primary-glow);
}

.nav-icon-btn svg {
  width: 20px;
  height: 20px;
}

.notification-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  background: var(--nav-danger);
  color: white;
  font-size: 0.7rem;
  font-weight: 700;
  border-radius: var(--nav-radius-full);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 0 10px rgba(239, 68, 68, 0.4);
  animation: pulse-badge 2s ease-in-out infinite;
}

@keyframes pulse-badge {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.1); }
}

/* More Menu */
.more-menu {
  position: relative;
}

.more-menu-btn {
  width: 44px;
  height: 44px;
  background: var(--nav-bg-glass);
  border: 1px solid var(--nav-border-primary);
  border-radius: var(--nav-radius-md);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--nav-text-secondary);
  transition: var(--nav-transition);
}

.more-menu-btn:hover {
  background: var(--nav-bg-glass-hover);
  border-color: var(--nav-border-accent);
  color: var(--nav-primary);
  transform: translateY(-2px);
  box-shadow: var(--nav-shadow-md), 0 0 20px var(--nav-primary-glow);
}

.more-menu-btn svg {
  width: 20px;
  height: 20px;
}

.more-dropdown {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  min-width: 220px;
  background: var(--nav-bg-card);
  backdrop-filter: var(--nav-blur);
  -webkit-backdrop-filter: var(--nav-blur);
  border: 1px solid var(--nav-border-primary);
  border-radius: var(--nav-radius-lg);
  box-shadow: var(--nav-shadow-xl);
  opacity: 0;
  visibility: hidden;
  transform: translateY(-10px) scale(0.95);
  transition: var(--nav-transition);
  z-index: 1000;
  overflow: hidden;
}

.more-dropdown.show {
  opacity: 1;
  visibility: visible;
  transform: translateY(0) scale(1);
}

.more-dropdown-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  color: var(--nav-text-secondary);
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 500;
  transition: all 0.15s ease;
}

.more-dropdown-item:hover {
  background: var(--nav-bg-glass-hover);
  color: var(--nav-text-primary);
}

.more-dropdown-item:hover svg {
  color: var(--nav-primary);
  transform: scale(1.1);
}

.more-dropdown-item svg {
  width: 18px;
  height: 18px;
  color: var(--nav-text-muted);
  transition: all 0.15s ease;
}

.more-dropdown-divider {
  height: 1px;
  background: var(--nav-border-primary);
  margin: 4px 0;
}

/* User Menu */
.user-menu {
  position: relative;
}

.user-menu-btn {
  background: none;
  border: 2px solid var(--nav-border-primary);
  border-radius: var(--nav-radius-full);
  cursor: pointer;
  padding: 2px;
  transition: var(--nav-transition);
}

.user-menu-btn:hover {
  border-color: var(--nav-primary);
  box-shadow: 0 0 20px var(--nav-primary-glow);
  transform: scale(1.05);
}

.user-avatar {
  width: 40px;
  height: 40px;
  border-radius: var(--nav-radius-full);
  object-fit: cover;
}

.user-avatar-placeholder {
  width: 40px;
  height: 40px;
  border-radius: var(--nav-radius-full);
  background: linear-gradient(135deg, var(--nav-primary) 0%, var(--nav-primary-light) 100%);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 1rem;
}

.user-dropdown {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  min-width: 220px;
  background: var(--nav-bg-card);
  backdrop-filter: var(--nav-blur);
  -webkit-backdrop-filter: var(--nav-blur);
  border: 1px solid var(--nav-border-primary);
  border-radius: var(--nav-radius-lg);
  box-shadow: var(--nav-shadow-xl);
  opacity: 0;
  visibility: hidden;
  transform: translateY(-10px) scale(0.95);
  transition: var(--nav-transition);
  z-index: 1000;
  overflow: hidden;
}

.user-dropdown.show {
  opacity: 1;
  visibility: visible;
  transform: translateY(0) scale(1);
}

.user-dropdown-header {
  padding: 16px;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
  border-bottom: 1px solid var(--nav-border-primary);
}

[data-theme="light"] .user-dropdown-header {
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.7) 100%);
}

.user-dropdown-header strong {
  display: block;
  color: var(--nav-text-primary);
  font-weight: 600;
  margin-bottom: 2px;
}

.user-dropdown-header span {
  font-size: 0.8rem;
  color: var(--nav-text-muted);
}

.user-dropdown-divider {
  height: 1px;
  background: var(--nav-border-primary);
}

.user-dropdown-item {
  display: block;
  padding: 12px 16px;
  color: var(--nav-text-secondary);
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 500;
  transition: all 0.15s ease;
}

.user-dropdown-item:hover {
  background: var(--nav-bg-glass-hover);
  color: var(--nav-text-primary);
}

.user-dropdown-item.logout {
  color: var(--nav-danger);
}

.user-dropdown-item.logout:hover {
  background: rgba(239, 68, 68, 0.1);
}

/* Responsive */
@media (max-width: 768px) {
  .nav-container {
    padding: 0 1rem;
    height: 60px;
  }
  .nav-logo {
    font-size: 1.5rem;
  }
  .nav-icon-btn, .more-menu-btn, .theme-toggle {
    width: 40px;
    height: 40px;
  }
  .user-avatar, .user-avatar-placeholder {
    width: 36px;
    height: 36px;
  }
  .nav-actions {
    gap: 0.5rem;
  }
}
</style>
<nav class="navbar">
    <div class="nav-container">
        <a href="/" class="nav-logo">CRC</a>

        <div class="nav-actions">
            <!-- Theme Toggle -->
            <button class="theme-toggle" onclick="toggleTheme()" title="Toggle theme">
                <svg class="sun-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
                <svg class="moon-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                </svg>
            </button>

            <a href="/notifications/" class="nav-icon-btn" title="Notifications">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
// Theme toggle function
function toggleTheme() {
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-theme') || 'dark';
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);

    // Add animation class
    document.body.style.transition = 'background 0.5s ease, color 0.3s ease';
}

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

// Keyboard shortcut for theme toggle (Ctrl/Cmd + Shift + L)
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'L') {
        e.preventDefault();
        toggleTheme();
    }
});
</script>
