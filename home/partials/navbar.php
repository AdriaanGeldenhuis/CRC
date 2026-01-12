<?php
/**
 * CRC Navbar Partial - Ultra Glossy 3D Premium Design
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
/* ===== ULTRA GLOSSY 3D NAVBAR - Premium Design ===== */
:root {
  /* Primary Palette */
  --nav-primary: #7C3AED;
  --nav-primary-light: #A78BFA;
  --nav-primary-glow: rgba(124, 58, 237, 0.5);
  --nav-primary-intense: rgba(124, 58, 237, 0.8);

  /* Accent - Electric Cyan */
  --nav-accent: #06B6D4;
  --nav-accent-glow: rgba(6, 182, 212, 0.5);

  /* Secondary - Hot Pink */
  --nav-secondary: #EC4899;
  --nav-secondary-glow: rgba(236, 72, 153, 0.4);

  /* Danger */
  --nav-danger: #EF4444;
  --nav-danger-glow: rgba(239, 68, 68, 0.5);

  /* Backgrounds */
  --nav-bg-base: #030014;
  --nav-glass-bg: rgba(255, 255, 255, 0.03);
  --nav-glass-bg-strong: rgba(255, 255, 255, 0.12);
  --nav-glass-bg-hover: rgba(255, 255, 255, 0.08);
  --nav-glass-border: rgba(255, 255, 255, 0.1);
  --nav-glass-border-hover: rgba(255, 255, 255, 0.2);

  /* Text */
  --nav-text-primary: #FFFFFF;
  --nav-text-secondary: #A1A1C7;
  --nav-text-muted: #6B6B8D;

  /* Shadows */
  --nav-shadow-md: 0 8px 24px rgba(0, 0, 0, 0.5);
  --nav-shadow-xl: 0 24px 64px rgba(0, 0, 0, 0.7);
  --nav-shadow-glow: 0 0 40px var(--nav-primary-glow), 0 0 80px rgba(124, 58, 237, 0.2);

  /* 3D Button Shadows */
  --nav-btn-shadow: 0 4px 0 rgba(0, 0, 0, 0.3), 0 8px 24px rgba(0, 0, 0, 0.4);
  --nav-btn-shadow-hover: 0 6px 0 rgba(0, 0, 0, 0.3), 0 12px 32px rgba(0, 0, 0, 0.5);
  --nav-btn-shadow-active: 0 2px 0 rgba(0, 0, 0, 0.3), 0 4px 12px rgba(0, 0, 0, 0.3);

  /* Effects */
  --nav-blur: blur(20px);
  --nav-radius-md: 16px;
  --nav-radius-lg: 24px;
  --nav-radius-xl: 32px;
  --nav-radius-full: 9999px;

  /* Transitions */
  --nav-ease-spring: cubic-bezier(0.175, 0.885, 0.32, 1.275);
  --nav-ease-expo: cubic-bezier(0.16, 1, 0.3, 1);
  --nav-transition: all 0.5s var(--nav-ease-spring);
  --nav-transition-fast: all 0.2s var(--nav-ease-expo);

  /* Font */
  --nav-font: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;

  /* Gradient */
  --nav-gradient-primary: linear-gradient(135deg, var(--nav-primary) 0%, var(--nav-primary-light) 50%, var(--nav-accent) 100%);
}

[data-theme="light"] {
  --nav-bg-base: #F0F4FF;
  --nav-glass-bg: rgba(255, 255, 255, 0.7);
  --nav-glass-bg-strong: rgba(255, 255, 255, 0.95);
  --nav-glass-bg-hover: rgba(255, 255, 255, 0.9);
  --nav-glass-border: rgba(124, 58, 237, 0.15);
  --nav-glass-border-hover: rgba(124, 58, 237, 0.3);

  --nav-text-primary: #1E1B4B;
  --nav-text-secondary: #4C4687;
  --nav-text-muted: #8B85B1;

  --nav-shadow-md: 0 8px 24px rgba(124, 58, 237, 0.12);
  --nav-shadow-xl: 0 24px 64px rgba(124, 58, 237, 0.18);
  --nav-shadow-glow: 0 0 40px rgba(124, 58, 237, 0.2);

  --nav-btn-shadow: 0 4px 0 rgba(124, 58, 237, 0.2), 0 8px 24px rgba(124, 58, 237, 0.15);
  --nav-btn-shadow-hover: 0 6px 0 rgba(124, 58, 237, 0.2), 0 12px 32px rgba(124, 58, 237, 0.2);
  --nav-btn-shadow-active: 0 2px 0 rgba(124, 58, 237, 0.2), 0 4px 12px rgba(124, 58, 237, 0.1);
}

/* ===== NAVBAR ===== */
.navbar {
  position: sticky;
  top: 0;
  z-index: 1000;
  background: rgba(0, 0, 0, 0.9);
  backdrop-filter: var(--nav-blur) saturate(180%);
  -webkit-backdrop-filter: var(--nav-blur) saturate(180%);
  border-bottom: 1px solid var(--nav-glass-border);
  font-family: var(--nav-font);
  transition: var(--nav-transition);
}

[data-theme="light"] .navbar {
  background: rgba(255, 255, 255, 0.9);
}

/* Top glossy shine line */
.navbar::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.3) 50%, transparent 100%);
}

.nav-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 0 2rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 80px;
}

/* ===== LOGO ===== */
.nav-logo {
  font-size: 2rem;
  font-weight: 900;
  background: var(--nav-gradient-primary);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  text-decoration: none;
  letter-spacing: -1px;
  transition: var(--nav-transition);
  position: relative;
}

.nav-logo::after {
  content: '';
  position: absolute;
  bottom: -4px;
  left: 0;
  width: 100%;
  height: 3px;
  background: var(--nav-gradient-primary);
  border-radius: var(--nav-radius-full);
  transform: scaleX(0);
  transition: var(--nav-transition-fast);
}

.nav-logo:hover {
  transform: scale(1.1) rotate(-2deg);
  filter: drop-shadow(0 0 20px var(--nav-primary-glow));
}

.nav-logo:hover::after {
  transform: scaleX(1);
}

.nav-actions {
  display: flex;
  align-items: center;
  gap: 1rem;
}

/* ===== 3D GLOSSY BUTTONS ===== */
.theme-toggle,
.nav-icon-btn,
.more-menu-btn {
  position: relative;
  width: 52px;
  height: 52px;
  background: var(--nav-glass-bg-strong);
  border: 1px solid var(--nav-glass-border);
  border-radius: var(--nav-radius-md);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--nav-text-secondary);
  text-decoration: none;
  transition: var(--nav-transition);
  overflow: hidden;
  box-shadow: var(--nav-btn-shadow);
  transform-style: preserve-3d;
}

/* Glossy shine effect on top */
.theme-toggle::before,
.nav-icon-btn::before,
.more-menu-btn::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 50%;
  background: linear-gradient(180deg, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 100%);
  border-radius: var(--nav-radius-md) var(--nav-radius-md) 0 0;
  pointer-events: none;
}

/* Inner glow effect */
.theme-toggle::after,
.nav-icon-btn::after,
.more-menu-btn::after {
  content: '';
  position: absolute;
  inset: 2px;
  border-radius: calc(var(--nav-radius-md) - 2px);
  background: transparent;
  box-shadow: inset 0 0 20px rgba(255,255,255,0.05);
  pointer-events: none;
}

.theme-toggle:hover,
.nav-icon-btn:hover,
.more-menu-btn:hover {
  transform: translateY(-4px) scale(1.08);
  background: var(--nav-glass-bg-strong);
  border-color: var(--nav-primary);
  color: var(--nav-primary);
  box-shadow: var(--nav-btn-shadow-hover), var(--nav-shadow-glow);
}

.theme-toggle:active,
.nav-icon-btn:active,
.more-menu-btn:active {
  transform: translateY(2px) scale(0.98);
  box-shadow: var(--nav-btn-shadow-active);
}

.theme-toggle svg,
.nav-icon-btn svg,
.more-menu-btn svg {
  width: 22px;
  height: 22px;
  transition: var(--nav-transition);
  filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
}

/* ===== THEME TOGGLE ICONS ===== */
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

/* ===== NOTIFICATION BADGE ===== */
.notification-badge {
  position: absolute;
  top: -6px;
  right: -6px;
  min-width: 22px;
  height: 22px;
  padding: 0 6px;
  background: linear-gradient(135deg, var(--nav-secondary) 0%, var(--nav-primary) 100%);
  color: white;
  font-size: 0.7rem;
  font-weight: 800;
  border-radius: var(--nav-radius-full);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px var(--nav-secondary-glow), 0 0 0 3px var(--nav-bg-base);
  animation: pulse-badge 2s ease-in-out infinite;
  z-index: 10;
}

@keyframes pulse-badge {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.15); }
}

/* ===== DROPDOWN MENUS ===== */
.more-menu,
.user-menu {
  position: relative;
}

.more-dropdown,
.user-dropdown {
  position: absolute;
  top: calc(100% + 12px);
  right: 0;
  min-width: 260px;
  background: var(--nav-glass-bg-strong);
  backdrop-filter: var(--nav-blur) saturate(200%);
  -webkit-backdrop-filter: var(--nav-blur) saturate(200%);
  border: 1px solid var(--nav-glass-border);
  border-radius: var(--nav-radius-xl);
  box-shadow: var(--nav-shadow-xl), var(--nav-shadow-glow);
  opacity: 0;
  visibility: hidden;
  transform: translateY(-20px) scale(0.9) rotateX(-10deg);
  transform-origin: top right;
  transition: var(--nav-transition);
  z-index: 1000;
  overflow: hidden;
}

/* Glossy top shine on dropdowns */
.more-dropdown::before,
.user-dropdown::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.4) 50%, transparent 100%);
}

.more-dropdown.show,
.user-dropdown.show {
  opacity: 1;
  visibility: visible;
  transform: translateY(0) scale(1) rotateX(0deg);
}

/* ===== DROPDOWN ITEMS ===== */
.more-dropdown-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 20px;
  color: var(--nav-text-secondary);
  text-decoration: none;
  font-size: 0.95rem;
  font-weight: 500;
  transition: var(--nav-transition-fast);
  position: relative;
  overflow: hidden;
}

/* Left accent bar on hover */
.more-dropdown-item::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  background: var(--nav-gradient-primary);
  transform: scaleY(0);
  transition: var(--nav-transition-fast);
}

.more-dropdown-item:hover {
  background: var(--nav-glass-bg-hover);
  color: var(--nav-text-primary);
  padding-left: 28px;
}

.more-dropdown-item:hover::before {
  transform: scaleY(1);
}

.more-dropdown-item:hover svg {
  color: var(--nav-primary);
  transform: scale(1.2) rotate(5deg);
}

.more-dropdown-item svg {
  width: 20px;
  height: 20px;
  color: var(--nav-text-muted);
  transition: var(--nav-transition);
}

.more-dropdown-divider {
  height: 1px;
  background: linear-gradient(90deg, transparent 0%, var(--nav-glass-border) 50%, transparent 100%);
  margin: 8px 20px;
}

/* ===== USER MENU ===== */
.user-menu-btn {
  background: none;
  border: 3px solid var(--nav-glass-border);
  border-radius: var(--nav-radius-full);
  cursor: pointer;
  padding: 3px;
  transition: var(--nav-transition);
  box-shadow: var(--nav-btn-shadow);
}

.user-menu-btn:hover {
  border-color: var(--nav-primary);
  box-shadow: var(--nav-btn-shadow-hover), var(--nav-shadow-glow);
  transform: scale(1.1);
}

.user-avatar {
  width: 44px;
  height: 44px;
  border-radius: var(--nav-radius-full);
  object-fit: cover;
}

.user-avatar-placeholder {
  width: 44px;
  height: 44px;
  border-radius: var(--nav-radius-full);
  background: var(--nav-gradient-primary);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 1.1rem;
  text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

/* User dropdown header with gradient */
.user-dropdown-header {
  padding: 20px;
  background: var(--nav-gradient-primary);
  position: relative;
  overflow: hidden;
}

/* Glossy shine on header */
.user-dropdown-header::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(135deg, rgba(255,255,255,0.25) 0%, rgba(255,255,255,0) 50%);
}

.user-dropdown-header strong {
  display: block;
  color: white;
  font-weight: 700;
  font-size: 1.1rem;
  margin-bottom: 4px;
  position: relative;
  text-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.user-dropdown-header span {
  font-size: 0.85rem;
  color: rgba(255,255,255,0.85);
  position: relative;
}

.user-dropdown-divider {
  height: 1px;
  background: linear-gradient(90deg, transparent 0%, var(--nav-glass-border) 50%, transparent 100%);
}

.user-dropdown-item {
  display: block;
  padding: 14px 20px;
  color: var(--nav-text-secondary);
  text-decoration: none;
  font-size: 0.95rem;
  font-weight: 500;
  transition: var(--nav-transition-fast);
  position: relative;
}

.user-dropdown-item:hover {
  background: var(--nav-glass-bg-hover);
  color: var(--nav-text-primary);
  padding-left: 28px;
}

.user-dropdown-item.logout {
  color: var(--nav-danger);
}

.user-dropdown-item.logout:hover {
  background: rgba(239, 68, 68, 0.1);
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
  .nav-container {
    padding: 0 1.25rem;
    height: 70px;
  }
  .nav-logo {
    font-size: 1.75rem;
  }
  .nav-actions {
    gap: 0.75rem;
  }
  .theme-toggle, .nav-icon-btn, .more-menu-btn {
    width: 46px;
    height: 46px;
  }
  .user-avatar, .user-avatar-placeholder {
    width: 40px;
    height: 40px;
  }
}

@media (max-width: 480px) {
  .nav-actions {
    gap: 0.5rem;
  }
  .theme-toggle, .nav-icon-btn, .more-menu-btn {
    width: 42px;
    height: 42px;
  }
  .theme-toggle svg, .nav-icon-btn svg, .more-menu-btn svg {
    width: 18px;
    height: 18px;
  }
}
</style>
<nav class="navbar">
    <div class="nav-container">
        <a href="/" class="nav-logo">CRC</a>

        <div class="nav-actions">
            <!-- Theme Toggle -->
            <button class="theme-toggle" onclick="toggleTheme()" title="Toggle theme (Ctrl+Shift+L)">
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
                <button class="more-menu-btn" onclick="toggleMoreMenu()" title="More options">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                        <circle cx="12" cy="5" r="2.5"></circle>
                        <circle cx="12" cy="12" r="2.5"></circle>
                        <circle cx="12" cy="19" r="2.5"></circle>
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
                    <a href="/ai_smartbible/ai_smartbible.php" class="more-dropdown-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            <circle cx="19" cy="5" r="3" fill="currentColor"></circle>
                        </svg>
                        AI SmartBible
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
// Theme toggle function with smooth animation
function toggleTheme() {
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-theme') || 'dark';
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

    // Add smooth transition to body
    document.body.style.transition = 'background 0.6s cubic-bezier(0.16, 1, 0.3, 1), color 0.4s ease';

    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
}

function toggleUserMenu() {
    document.getElementById('moreDropdown')?.classList.remove('show');
    document.getElementById('userDropdown').classList.toggle('show');
}

function toggleMoreMenu() {
    document.getElementById('userDropdown')?.classList.remove('show');
    document.getElementById('moreDropdown').classList.toggle('show');
}

// Close dropdowns when clicking outside
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
