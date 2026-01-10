<?php
/**
 * CRC Admin Congregation - Shared Authentication & Authorization
 * Include this at the top of all admin_congregation pages
 */

// Require authentication
Auth::requireAuth();

$isSuperAdmin = Auth::isSuperAdmin();
$allCongregations = $isSuperAdmin ? Auth::allCongregations() : [];

// Get congregation to manage
$congregation = null;

// Super admin can select any congregation via query param
if ($isSuperAdmin && isset($_GET['cong_id'])) {
    $selectedCongId = (int)$_GET['cong_id'];
    $congregation = Auth::getCongregation($selectedCongId);
}

// Fall back to primary congregation
if (!$congregation) {
    $primaryCong = Auth::primaryCongregation();
    if ($primaryCong) {
        $congregation = $primaryCong;
    } elseif ($isSuperAdmin && !empty($allCongregations)) {
        // Super admin without primary congregation - use first available
        $congregation = $allCongregations[0];
    }
}

if (!$congregation) {
    Response::redirect('/onboarding/');
}

if (!Auth::isCongregationAdmin($congregation['id'])) {
    Session::flash('error', 'You do not have admin access');
    Response::redirect('/home/');
}

// Build query string for super admin navigation
$congQuery = $isSuperAdmin ? '?cong_id=' . $congregation['id'] : '';

/**
 * Render the sidebar with super admin support
 */
function renderAdminSidebar($congregation, $isSuperAdmin, $allCongregations, $congQuery, $activePage = 'dashboard') {
    $pages = [
        'dashboard' => ['url' => '/admin_congregation/', 'icon' => '<rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect>', 'label' => 'Dashboard'],
        'members' => ['url' => '/admin_congregation/members.php', 'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle>', 'label' => 'Members'],
        'invites' => ['url' => '/admin_congregation/invites.php', 'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line>', 'label' => 'Invites'],
        'events' => ['url' => '/admin_congregation/events.php', 'icon' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line>', 'label' => 'Events'],
        'morning_study' => ['url' => '/admin_congregation/morning_study.php', 'icon' => '<circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>', 'label' => 'Morning Study'],
        'settings' => ['url' => '/admin_congregation/settings.php', 'icon' => '<circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>', 'label' => 'Settings'],
    ];
    ?>
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="/home/" class="sidebar-logo">CRC</a>
            <?php if ($isSuperAdmin && count($allCongregations) > 1): ?>
                <select class="congregation-select" onchange="window.location.href='?cong_id='+this.value">
                    <?php foreach ($allCongregations as $cong): ?>
                        <option value="<?= $cong['id'] ?>" <?= $cong['id'] == $congregation['id'] ? 'selected' : '' ?>>
                            <?= e($cong['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <span class="congregation-badge"><?= e($congregation['name']) ?></span>
            <?php endif; ?>
            <?php if ($isSuperAdmin): ?>
                <a href="/admin/" class="super-admin-link" title="Super Admin Panel">⚡ Super Admin</a>
            <?php endif; ?>
        </div>
        <nav class="sidebar-nav">
            <?php foreach ($pages as $key => $page): ?>
                <a href="<?= $page['url'] . $congQuery ?>" class="nav-item <?= $activePage === $key ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $page['icon'] ?></svg>
                    <?= $page['label'] ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <a href="/home/" class="back-link">← Back to Home</a>
        </div>
    </aside>
    <?php
}

/**
 * Get super admin CSS styles
 */
function getSuperAdminStyles() {
    return <<<CSS
<style>
.congregation-select { width: 100%; padding: 0.5rem; margin-top: 0.5rem; border: 1px solid rgba(255,255,255,0.2); border-radius: 6px; background: rgba(255,255,255,0.1); color: white; font-size: 0.8rem; cursor: pointer; }
.congregation-select option { background: #1F2937; color: white; }
.super-admin-link { display: inline-block; margin-top: 0.5rem; padding: 0.25rem 0.5rem; background: #F59E0B; color: white; border-radius: 4px; text-decoration: none; font-size: 0.75rem; }
.super-admin-link:hover { background: #D97706; }
</style>
CSS;
}
