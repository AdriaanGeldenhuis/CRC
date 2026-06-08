<?php
/**
 * CRC Congregation Admin Dashboard
 */

require_once __DIR__ . '/../core/bootstrap.php';

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

$pageTitle = 'Manage ' . $congregation['name'] . ' - CRC';

// Build query string for super admin navigation
$congQuery = $isSuperAdmin ? '?cong_id=' . $congregation['id'] : '';

// Get statistics
$stats = [
    'members' => Database::fetchColumn(
        "SELECT COUNT(*) FROM user_congregations WHERE congregation_id = ? AND status = 'active'",
        [$congregation['id']]
    ),
    'pending' => Database::fetchColumn(
        "SELECT COUNT(*) FROM user_congregations WHERE congregation_id = ? AND status = 'pending'",
        [$congregation['id']]
    ),
    'events' => Database::fetchColumn(
        "SELECT COUNT(*) FROM events WHERE congregation_id = ? AND start_datetime >= NOW() AND status = 'published'",
        [$congregation['id']]
    ),
    'posts' => Database::fetchColumn(
        "SELECT COUNT(*) FROM posts WHERE congregation_id = ? AND status = 'active'",
        [$congregation['id']]
    )
];

// Get pending members
$pendingMembers = Database::fetchAll(
    "SELECT uc.*, u.name, u.email, u.avatar
     FROM user_congregations uc
     JOIN users u ON uc.user_id = u.id
     WHERE uc.congregation_id = ? AND uc.status = 'pending'
     ORDER BY uc.created_at DESC
     LIMIT 10",
    [$congregation['id']]
);

// Get recent members
$recentMembers = Database::fetchAll(
    "SELECT uc.*, u.name, u.email, u.avatar
     FROM user_congregations uc
     JOIN users u ON uc.user_id = u.id
     WHERE uc.congregation_id = ? AND uc.status = 'active'
     ORDER BY uc.joined_at DESC
     LIMIT 5",
    [$congregation['id']]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/admin_congregation/css/admin_congregation.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        (function() {
            const saved = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', saved);
        })();
    </script>
    <style>
        .congregation-select { width: 100%; padding: 0.5rem; margin-top: 0.5rem; border: 1px solid rgba(255,255,255,0.2); border-radius: 6px; background: rgba(255,255,255,0.1); color: white; font-size: 0.8rem; cursor: pointer; }
        .congregation-select option { background: #1F2937; color: white; }
        .super-admin-link { display: inline-block; margin-top: 0.5rem; padding: 0.25rem 0.5rem; background: var(--primary); color: white; border-radius: 4px; text-decoration: none; font-size: 0.75rem; }
        .super-admin-link:hover { background: var(--primary-dark); }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <div class="admin-layout">
        <!-- Sidebar -->
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
                    <a href="/admin/" class="super-admin-link" title="Super Admin">⚡</a>
                <?php endif; ?>
            </div>
            <nav class="sidebar-nav">
                <a href="/admin_congregation/<?= $congQuery ?>" class="nav-item active">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                    Dashboard
                </a>
                <a href="/admin_congregation/members.php<?= $congQuery ?>" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    Members
                    <?php if ($stats['pending'] > 0): ?>
                        <span class="badge"><?= $stats['pending'] ?></span>
                    <?php endif; ?>
                </a>
                <a href="/admin_congregation/invites.php<?= $congQuery ?>" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="8.5" cy="7" r="4"></circle>
                        <line x1="20" y1="8" x2="20" y2="14"></line>
                        <line x1="23" y1="11" x2="17" y2="11"></line>
                    </svg>
                    Invites
                </a>
                <a href="/admin_congregation/events.php<?= $congQuery ?>" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    Events
                </a>
                <a href="/admin_congregation/morning_study.php<?= $congQuery ?>" class="nav-item">
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
                <a href="/admin_congregation/settings.php<?= $congQuery ?>" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                    </svg>
                    Settings
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="/home/" class="back-link">← Back to Home</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="page-header">
                <h1>Dashboard</h1>
                <p>Manage your congregation</p>
            </header>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <span class="stat-value"><?= $stats['members'] ?></span>
                        <span class="stat-label">Active Members</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon yellow">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <span class="stat-value"><?= $stats['pending'] ?></span>
                        <span class="stat-label">Pending Requests</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <span class="stat-value"><?= $stats['events'] ?></span>
                        <span class="stat-label">Upcoming Events</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon purple">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <span class="stat-value"><?= $stats['posts'] ?></span>
                        <span class="stat-label">Posts</span>
                    </div>
                </div>
            </div>

            <!-- Pending Requests -->
            <?php if ($pendingMembers): ?>
            <section class="card">
                <div class="card-header">
                    <h2>Pending Requests</h2>
                    <a href="/admin_congregation/members.php?status=pending" class="view-all">View All</a>
                </div>
                <div class="member-list">
                    <?php foreach ($pendingMembers as $member): ?>
                    <div class="member-item" id="member-<?= $member['user_id'] ?>">
                        <div class="member-info">
                            <?php if ($member['avatar']): ?>
                                <img src="<?= e($member['avatar']) ?>" alt="" class="member-avatar">
                            <?php else: ?>
                                <div class="member-avatar-placeholder"><?= strtoupper(substr($member['name'], 0, 1)) ?></div>
                            <?php endif; ?>
                            <div>
                                <strong><?= e($member['name']) ?></strong>
                                <span><?= e($member['email']) ?></span>
                                <span class="time"><?= time_ago($member['created_at']) ?></span>
                            </div>
                        </div>
                        <div class="member-actions">
                            <button class="btn btn-success btn-sm" onclick="approveRequest(<?= $member['user_id'] ?>)">Approve</button>
                            <button class="btn btn-danger btn-sm" onclick="rejectRequest(<?= $member['user_id'] ?>)">Reject</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- Recent Members -->
            <section class="card">
                <div class="card-header">
                    <h2>Recent Members</h2>
                    <a href="/admin_congregation/members.php" class="view-all">View All</a>
                </div>
                <?php if ($recentMembers): ?>
                <div class="member-list">
                    <?php foreach ($recentMembers as $member): ?>
                    <div class="member-item">
                        <div class="member-info">
                            <?php if ($member['avatar']): ?>
                                <img src="<?= e($member['avatar']) ?>" alt="" class="member-avatar">
                            <?php else: ?>
                                <div class="member-avatar-placeholder"><?= strtoupper(substr($member['name'], 0, 1)) ?></div>
                            <?php endif; ?>
                            <div>
                                <strong><?= e($member['name']) ?></strong>
                                <span class="role-badge"><?= ucfirst($member['role']) ?></span>
                                <span class="time">Joined <?= time_ago($member['joined_at']) ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <p>No members yet.</p>
                </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <div id="toast" class="toast"></div>

    <script>
        function getCSRFToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        }

        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast ' + type + ' show';
            setTimeout(() => toast.classList.remove('show'), 3000);
        }

        async function approveRequest(userId) {
            try {
                const response = await fetch('/admin_congregation/api/members.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': getCSRFToken()
                    },
                    body: JSON.stringify({ action: 'approve', user_id: userId })
                });

                const data = await response.json();

                if (data.ok) {
                    showToast('Member approved');
                    document.getElementById('member-' + userId).remove();
                } else {
                    showToast(data.error || 'Failed to approve', 'error');
                }
            } catch (error) {
                showToast('Network error', 'error');
            }
        }

        async function rejectRequest(userId) {
            if (!confirm('Are you sure you want to reject this request?')) return;

            try {
                const response = await fetch('/admin_congregation/api/members.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': getCSRFToken()
                    },
                    body: JSON.stringify({ action: 'reject', user_id: userId })
                });

                const data = await response.json();

                if (data.ok) {
                    showToast('Request rejected');
                    document.getElementById('member-' + userId).remove();
                } else {
                    showToast(data.error || 'Failed to reject', 'error');
                }
            } catch (error) {
                showToast('Network error', 'error');
            }
        }
    </script>
</body>
</html>
