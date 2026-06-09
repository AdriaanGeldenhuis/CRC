<?php
/**
 * CRC Navbar Partial - Home topbar (matches the home dashboard exactly)
 * Include: <?php include __DIR__ . '/../home/partials/navbar.php'; ?>
 * Self-contained: embeds its own styles + JS so it looks identical on every
 * page, regardless of that page's stylesheet.
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
?>
<script>
// Load saved theme before paint to prevent flash
(function() {
    var saved = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-theme', saved);
})();
</script>
<style>
/* ===== HOME TOPBAR (self-contained, token fallbacks) ===== */
.topbar {
  position: sticky;
  top: 0;
  z-index: 50;
  backdrop-filter: blur(var(--blur, 18px));
  -webkit-backdrop-filter: blur(var(--blur, 18px));
  background: var(--bg-glass, rgba(9, 15, 31, 0.85));
  border-bottom: 1px solid var(--line, rgba(255,255,255,0.10));
}
.topbar .inner {
  max-width: var(--max, 1080px);
  margin: 0 auto;
  padding: 14px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.topbar .brand {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
  text-decoration: none;
  color: inherit;
}
.topbar .logo {
  width: 38px;
  height: 38px;
  border-radius: 14px;
  background: linear-gradient(135deg, var(--accent, #7C3AED), var(--accent2, #22D3EE));
  box-shadow: 0 14px 30px rgba(124,58,237,0.22);
  position: relative;
  flex-shrink: 0;
}
.topbar .logo::before {
  content: "";
  position: absolute; inset: 0;
  border-radius: 14px;
  background: radial-gradient(circle at 30% 30%, rgba(255,255,255,0.35), transparent 45%);
}
.topbar .logo::after {
  content: "";
  position: absolute; inset: 10px;
  border-radius: 8px;
  border: 1px solid rgba(255,255,255,0.22);
  background: rgba(255,255,255,0.06);
}
.topbar .brand h1 {
  font-size: 14px;
  line-height: 1.15;
  margin: 0;
  font-weight: 700;
  letter-spacing: 0.2px;
  color: var(--text, #EAF0FF);
}
.topbar .brand span {
  display: block;
  font-size: 12px;
  color: var(--muted, rgba(234,240,255,0.72));
  font-weight: 500;
  margin-top: 2px;
}
.topbar .actions {
  display: flex;
  align-items: center;
  gap: 10px;
}
.topbar .chip {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border-radius: 999px;
  border: 1px solid var(--line, rgba(255,255,255,0.10));
  background: var(--card, rgba(255,255,255,0.06));
  color: var(--muted, rgba(234,240,255,0.72));
  font-size: 12px;
  user-select: none;
}
.topbar .dot {
  width: 8px;
  height: 8px;
  border-radius: 99px;
  background: linear-gradient(135deg, var(--accent, #7C3AED), var(--accent2, #22D3EE));
  box-shadow: 0 0 0 4px var(--accent2-subtle, rgba(34,211,238,0.14));
  flex-shrink: 0;
}
.topbar .theme-toggle,
.topbar .nav-icon-btn,
.topbar .more-menu-btn {
  width: 44px;
  height: 44px;
  border: 1px solid var(--line, rgba(255,255,255,0.10));
  background: var(--btn-bg, rgba(255,255,255,0.07));
  border-radius: 14px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--muted, rgba(234,240,255,0.72));
  transition: transform 0.12s ease, box-shadow 0.12s ease, background 0.12s ease, color 0.12s ease;
  position: relative;
  text-decoration: none;
}
.topbar .theme-toggle:hover,
.topbar .nav-icon-btn:hover,
.topbar .more-menu-btn:hover {
  transform: translateY(-1px);
  box-shadow: var(--shadow2, 0 14px 40px rgba(0,0,0,0.45));
  background: var(--btn-bg-hover, rgba(255,255,255,0.12));
  color: var(--text, #EAF0FF);
}
.topbar .theme-toggle svg,
.topbar .nav-icon-btn svg,
.topbar .more-menu-btn svg {
  width: 20px;
  height: 20px;
}
.topbar .theme-toggle .sun-icon {
  position: absolute;
  opacity: 0;
  transform: rotate(-90deg) scale(0);
  transition: all 0.3s ease;
}
.topbar .theme-toggle .moon-icon {
  position: absolute;
  opacity: 1;
  transform: rotate(0deg) scale(1);
  transition: all 0.3s ease;
}
[data-theme="light"] .topbar .theme-toggle .sun-icon { opacity: 1; transform: rotate(0deg) scale(1); }
[data-theme="light"] .topbar .theme-toggle .moon-icon { opacity: 0; transform: rotate(90deg) scale(0); }
.topbar .notification-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  background: linear-gradient(135deg, var(--accent, #7C3AED), var(--accent2, #22D3EE));
  color: white;
  font-size: 10px;
  font-weight: 700;
  border-radius: 999px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(124,58,237,0.3);
}
.topbar .more-menu,
.topbar .user-menu { position: relative; }
.topbar .more-dropdown,
.topbar .user-dropdown {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  min-width: 220px;
  background: var(--bg1, #090F1F);
  backdrop-filter: blur(var(--blur, 18px));
  -webkit-backdrop-filter: blur(var(--blur, 18px));
  border: 1px solid var(--line, rgba(255,255,255,0.10));
  border-radius: var(--radius, 16px);
  box-shadow: var(--shadow, 0 24px 60px rgba(0,0,0,0.55));
  opacity: 0;
  visibility: hidden;
  transform: translateY(-10px) scale(0.95);
  transition: all 0.15s ease;
  z-index: 100;
  overflow: hidden;
}
.topbar .more-dropdown.show,
.topbar .user-dropdown.show {
  opacity: 1;
  visibility: visible;
  transform: translateY(0) scale(1);
}
.topbar .more-dropdown-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  color: var(--muted, rgba(234,240,255,0.72));
  text-decoration: none;
  font-size: 13px;
  font-weight: 500;
  transition: all 0.12s ease;
}
.topbar .more-dropdown-item:hover {
  background: var(--card, rgba(255,255,255,0.06));
  color: var(--text, #EAF0FF);
}
.topbar .more-dropdown-item svg { width: 18px; height: 18px; opacity: 0.7; }
.topbar .more-dropdown-divider { height: 1px; background: var(--line, rgba(255,255,255,0.10)); margin: 6px 0; }
.topbar .user-menu-btn {
  background: none;
  border: 2px solid var(--line, rgba(255,255,255,0.10));
  border-radius: 999px;
  cursor: pointer;
  padding: 2px;
  transition: all 0.12s ease;
}
.topbar .user-menu-btn:hover { border-color: var(--accent, #7C3AED); }
.topbar .user-avatar {
  width: 36px; height: 36px;
  border-radius: 999px;
  object-fit: cover;
  display: block;
}
.topbar .user-avatar-placeholder {
  width: 36px; height: 36px;
  border-radius: 999px;
  background: linear-gradient(135deg, var(--accent, #7C3AED), var(--accent2, #22D3EE));
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 14px;
}
.topbar .user-dropdown-header {
  padding: 14px 16px;
  background: linear-gradient(135deg, var(--accent, #7C3AED), var(--accent2, #22D3EE));
}
.topbar .user-dropdown-header strong { display: block; color: white; font-weight: 700; font-size: 14px; margin-bottom: 2px; }
.topbar .user-dropdown-header span { font-size: 12px; color: rgba(255,255,255,0.8); }
.topbar .user-dropdown-divider { height: 1px; background: var(--line, rgba(255,255,255,0.10)); }
.topbar .user-dropdown-item {
  display: block;
  padding: 12px 16px;
  color: var(--muted, rgba(234,240,255,0.72));
  text-decoration: none;
  font-size: 13px;
  font-weight: 500;
  transition: all 0.12s ease;
}
.topbar .user-dropdown-item:hover { background: var(--card, rgba(255,255,255,0.06)); color: var(--text, #EAF0FF); }
.topbar .user-dropdown-item.logout { color: var(--bad, #EF4444); }
.topbar .user-dropdown-item.logout:hover { background: rgba(239, 68, 68, 0.1); }
@media (max-width: 640px) {
  .topbar .chip { display: none; }
  .topbar .inner { padding: 12px 14px; }
}
</style>
<div class="topbar">
    <div class="inner">
        <a href="/home/" class="brand">
            <div class="logo" aria-hidden="true"></div>
            <div>
                <h1>CRC App</h1>
                <?php if ($navCong): ?><span><?= e($navCong['name']) ?></span><?php endif; ?>
            </div>
        </a>

        <div class="actions">
            <?php if ($navUser): ?>
            <div class="chip" title="Status">
                <span class="dot"></span>
                <?= e(explode(' ', $navUser['name'] ?? 'User')[0]) ?>
            </div>
            <?php endif; ?>

            <button class="theme-toggle" onclick="toggleTheme()" title="Toggle theme">
                <svg class="sun-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 3v2m0 14v2M3 12h2m14 0h2M5.2 5.2l1.4 1.4m10.8 10.8l1.4 1.4M18.8 5.2l-1.4 1.4M6.6 17.4l-1.4 1.4"></path>
                    <circle cx="12" cy="12" r="5"></circle>
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

            <div class="more-menu">
                <button class="more-menu-btn" onclick="toggleMoreMenu()" title="More">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                        <circle cx="12" cy="5" r="2"></circle><circle cx="12" cy="12" r="2"></circle><circle cx="12" cy="19" r="2"></circle>
                    </svg>
                </button>
                <div class="more-dropdown" id="moreDropdown">
                    <a href="/home/" class="more-dropdown-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                        Home
                    </a>
                    <a href="/gospel_media/" class="more-dropdown-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 11a9 9 0 0 1 9 9"></path><path d="M4 4a16 16 0 0 1 16 16"></path><circle cx="5" cy="19" r="1"></circle></svg>
                        Feed
                    </a>
                    <a href="/bible/" class="more-dropdown-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path><path d="M12 6v7"></path><path d="M8 9h8"></path></svg>
                        Bible
                    </a>
                    <a href="/ai_smartbible/" class="more-dropdown-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path><circle cx="19" cy="5" r="3" fill="currentColor"></circle></svg>
                        AI SmartBible
                    </a>
                    <a href="/morning_watch/" class="more-dropdown-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line></svg>
                        Morning Study
                    </a>
                    <a href="/calendar/" class="more-dropdown-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        Calendar
                    </a>
                    <a href="/media/" class="more-dropdown-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>
                        Media
                    </a>
                    <div class="more-dropdown-divider"></div>
                    <a href="/diary/" class="more-dropdown-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                        My Diary
                    </a>
                    <a href="/homecells/" class="more-dropdown-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                        Homecells
                    </a>
                    <a href="/learning/" class="more-dropdown-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>
                        Courses
                    </a>
                </div>
            </div>

            <div class="user-menu">
                <button class="user-menu-btn" onclick="toggleUserMenu()">
                    <?php if (!empty($navUser['avatar'])): ?>
                        <img src="<?= e($navUser['avatar']) ?>" alt="" class="user-avatar" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <div class="user-avatar-placeholder" style="display:none;"><?= strtoupper(substr($navUser['name'] ?? 'U', 0, 1)) ?></div>
                    <?php else: ?>
                        <div class="user-avatar-placeholder"><?= strtoupper(substr($navUser['name'] ?? 'U', 0, 1)) ?></div>
                    <?php endif; ?>
                </button>
                <div class="user-dropdown" id="userDropdown">
                    <div class="user-dropdown-header">
                        <strong><?= e($navUser['name'] ?? 'User') ?></strong>
                        <?php if ($navCong): ?><span><?= e($navCong['name']) ?></span><?php endif; ?>
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
</div>

<script>
function toggleTheme() {
    var html = document.documentElement;
    var next = (html.getAttribute('data-theme') || 'dark') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    if (document.body) document.body.setAttribute('data-theme', next);
    localStorage.setItem('theme', next);
}
function toggleMoreMenu() {
    document.getElementById('moreDropdown').classList.toggle('show');
    document.getElementById('userDropdown')?.classList.remove('show');
}
function toggleUserMenu() {
    document.getElementById('userDropdown').classList.toggle('show');
    document.getElementById('moreDropdown')?.classList.remove('show');
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('.user-menu')) document.getElementById('userDropdown')?.classList.remove('show');
    if (!e.target.closest('.more-menu')) document.getElementById('moreDropdown')?.classList.remove('show');
});
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'L' || e.key === 'l')) { e.preventDefault(); toggleTheme(); }
});
</script>
<script>
// Web Push: expose the VAPID public key and register the service worker so
// push notifications can be received across the whole origin.
window.VAPID_PUBLIC_KEY = '<?= defined('VAPID_PUBLIC_KEY') ? e(VAPID_PUBLIC_KEY) : '' ?>';
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(function () {});
}
</script>
