<?php
/**
 * CRC Congregation Admin - Members Management
 */

require_once __DIR__ . '/../core/bootstrap.php';

header('Content-Type: text/html; charset=UTF-8');

Auth::requireAuth();

$primaryCong = Auth::primaryCongregation();
if (!$primaryCong) {
    Response::redirect('/onboarding/');
}

if (!Auth::isCongregationAdmin($primaryCong['id'])) {
    Session::flash('error', 'You do not have admin access');
    Response::redirect('/home/');
}

$congregation = $primaryCong;
$pageTitle = 'Members - ' . $congregation['name'] . ' - CRC';
$currentUser = Auth::user();

// Filters
$status = input('status') ?: 'active';
$role = input('role') ?: '';
$search = input('search') ?: '';

// Build query
$where = ['uc.congregation_id = ?'];
$params = [$congregation['id']];

if ($status) {
    $where[] = 'uc.status = ?';
    $params[] = $status;
}

if ($role) {
    $where[] = 'uc.role = ?';
    $params[] = $role;
}

if ($search) {
    $where[] = '(u.name LIKE ? OR u.email LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$whereClause = implode(' AND ', $where);

// Get members
$members = Database::fetchAll(
    "SELECT uc.*, u.name, u.email, u.phone, u.avatar, u.date_of_birth,
            u.created_at as user_created_at
     FROM user_congregations uc
     JOIN users u ON uc.user_id = u.id
     WHERE $whereClause
     ORDER BY
        CASE uc.role
            WHEN 'pastor' THEN 1
            WHEN 'admin' THEN 2
            WHEN 'leader' THEN 3
            ELSE 4
        END,
        u.name ASC",
    $params
) ?: [];

// Get counts
$counts = [
    'active' => Database::fetchColumn(
        "SELECT COUNT(*) FROM user_congregations WHERE congregation_id = ? AND status = 'active'",
        [$congregation['id']]
    ),
    'pending' => Database::fetchColumn(
        "SELECT COUNT(*) FROM user_congregations WHERE congregation_id = ? AND status = 'pending'",
        [$congregation['id']]
    ),
    'suspended' => Database::fetchColumn(
        "SELECT COUNT(*) FROM user_congregations WHERE congregation_id = ? AND status = 'suspended'",
        [$congregation['id']]
    ),
];

// Get church positions for the congregation
$churchPositions = Database::fetchAll(
    "SELECT * FROM church_positions WHERE congregation_id = ? AND is_active = 1 ORDER BY display_order",
    [$congregation['id']]
) ?: [];
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
    <style>
        .filters { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem; align-items: center; }
        .filter-tabs { display: flex; gap: 0.5rem; }
        .filter-tab { padding: 0.5rem 1rem; background: var(--gray-100); border: none; border-radius: var(--radius); cursor: pointer; font-size: 0.875rem; color: var(--gray-600); transition: var(--transition); }
        .filter-tab:hover { background: var(--gray-200); }
        .filter-tab.active { background: var(--primary); color: white; }
        .filter-tab .count { margin-left: 0.5rem; opacity: 0.7; }
        .search-box { flex: 1; max-width: 300px; }
        .search-box input { width: 100%; padding: 0.5rem 1rem; border: 1px solid var(--gray-300); border-radius: var(--radius); font-size: 0.875rem; }
        .search-box input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79,70,229,0.1); }
        .role-select { padding: 0.5rem 1rem; border: 1px solid var(--gray-300); border-radius: var(--radius); font-size: 0.875rem; background: white; }

        .member-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem; }
        .member-card { background: white; border-radius: var(--radius-lg); padding: 1.5rem; box-shadow: var(--shadow); transition: var(--transition); }
        .member-card:hover { box-shadow: var(--shadow-lg); }
        .member-card-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
        .member-avatar-lg { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; }
        .member-avatar-lg-placeholder { width: 60px; height: 60px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 600; }
        .member-name { font-weight: 600; color: var(--gray-800); }
        .member-email { font-size: 0.8rem; color: var(--gray-500); }
        .member-meta { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem; }
        .role-tag { font-size: 0.7rem; padding: 0.2rem 0.5rem; border-radius: 100px; font-weight: 500; }
        .role-tag.pastor { background: #FEF3C7; color: #92400E; }
        .role-tag.admin { background: #DBEAFE; color: #1E40AF; }
        .role-tag.leader { background: #D1FAE5; color: #065F46; }
        .role-tag.member { background: var(--gray-100); color: var(--gray-600); }
        .status-tag { font-size: 0.7rem; padding: 0.2rem 0.5rem; border-radius: 100px; }
        .status-tag.pending { background: #FEF3C7; color: #92400E; }
        .status-tag.suspended { background: #FEE2E2; color: #991B1B; }

        .member-details { font-size: 0.8rem; color: var(--gray-500); margin-bottom: 1rem; }
        .member-details p { margin: 0.25rem 0; display: flex; align-items: center; gap: 0.5rem; }
        .member-details svg { width: 14px; height: 14px; }

        .member-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn-xs { padding: 0.25rem 0.5rem; font-size: 0.7rem; }
        .btn-outline { background: transparent; border: 1px solid var(--gray-300); color: var(--gray-600); }
        .btn-outline:hover { background: var(--gray-50); }
        .btn-primary { background: var(--primary); color: white; border: none; }
        .btn-primary:hover { background: var(--primary-dark); }

        .modal { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; }
        .modal.show { display: flex; }
        .modal-content { background: white; border-radius: var(--radius-lg); padding: 1.5rem; max-width: 400px; width: 90%; }
        .modal-header { font-weight: 600; margin-bottom: 1rem; }
        .modal-body { margin-bottom: 1.5rem; }
        .modal-footer { display: flex; gap: 0.5rem; justify-content: flex-end; }

        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; }
        .form-group select, .form-group input { width: 100%; padding: 0.5rem; border: 1px solid var(--gray-300); border-radius: var(--radius); }

        .positions-list { display: flex; flex-wrap: wrap; gap: 0.25rem; }
        .position-tag { font-size: 0.65rem; padding: 0.125rem 0.375rem; background: #EEF2FF; color: #4F46E5; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="/home/" class="sidebar-logo">CRC</a>
                <span class="congregation-badge"><?= e($congregation['name']) ?></span>
            </div>
            <nav class="sidebar-nav">
                <a href="/admin_congregation/" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    Dashboard
                </a>
                <a href="/admin_congregation/members.php" class="nav-item active">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    Members
                    <?php if ($counts['pending'] > 0): ?><span class="badge"><?= $counts['pending'] ?></span><?php endif; ?>
                </a>
                <a href="/admin_congregation/invites.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                    Invites
                </a>
                <a href="/admin_congregation/events.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    Events
                </a>
                <a href="/admin_congregation/morning_study.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                    Morning Study
                </a>
                <a href="/admin_congregation/settings.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
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
                <h1>Members</h1>
                <p>Manage congregation members and their roles</p>
            </header>

            <!-- Filters -->
            <div class="filters">
                <div class="filter-tabs">
                    <a href="?status=active" class="filter-tab <?= $status === 'active' ? 'active' : '' ?>">
                        Active <span class="count"><?= $counts['active'] ?></span>
                    </a>
                    <a href="?status=pending" class="filter-tab <?= $status === 'pending' ? 'active' : '' ?>">
                        Pending <span class="count"><?= $counts['pending'] ?></span>
                    </a>
                    <a href="?status=suspended" class="filter-tab <?= $status === 'suspended' ? 'active' : '' ?>">
                        Suspended <span class="count"><?= $counts['suspended'] ?></span>
                    </a>
                </div>

                <form method="get" class="search-box">
                    <input type="hidden" name="status" value="<?= e($status) ?>">
                    <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search members...">
                </form>

                <select class="role-select" onchange="filterByRole(this.value)">
                    <option value="">All Roles</option>
                    <option value="pastor" <?= $role === 'pastor' ? 'selected' : '' ?>>Pastor</option>
                    <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="leader" <?= $role === 'leader' ? 'selected' : '' ?>>Leader</option>
                    <option value="member" <?= $role === 'member' ? 'selected' : '' ?>>Member</option>
                </select>
            </div>

            <!-- Members Grid -->
            <?php if (empty($members)): ?>
                <div class="card">
                    <div class="empty-state">
                        <p>No members found.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="member-grid">
                    <?php foreach ($members as $member):
                        $isCurrentUser = $member['user_id'] == $currentUser['id'];
                        $isPastor = $member['role'] === 'pastor';

                        // Get member's church positions
                        $memberPositions = Database::fetchAll(
                            "SELECT cp.name FROM user_church_positions ucp
                             JOIN church_positions cp ON ucp.position_id = cp.id
                             WHERE ucp.user_id = ? AND ucp.congregation_id = ? AND ucp.is_active = 1",
                            [$member['user_id'], $congregation['id']]
                        ) ?: [];
                    ?>
                        <div class="member-card" id="member-<?= $member['user_id'] ?>">
                            <div class="member-card-header">
                                <?php if ($member['avatar']): ?>
                                    <img src="<?= e($member['avatar']) ?>" alt="" class="member-avatar-lg">
                                <?php else: ?>
                                    <div class="member-avatar-lg-placeholder"><?= strtoupper(substr($member['name'], 0, 1)) ?></div>
                                <?php endif; ?>
                                <div>
                                    <div class="member-name"><?= e($member['name']) ?></div>
                                    <div class="member-email"><?= e($member['email']) ?></div>
                                    <div class="member-meta">
                                        <span class="role-tag <?= $member['role'] ?>"><?= ucfirst($member['role']) ?></span>
                                        <?php if ($member['status'] !== 'active'): ?>
                                            <span class="status-tag <?= $member['status'] ?>"><?= ucfirst($member['status']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($memberPositions)): ?>
                                <div class="positions-list" style="margin-bottom: 0.75rem;">
                                    <?php foreach ($memberPositions as $pos): ?>
                                        <span class="position-tag"><?= e($pos['name']) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="member-details">
                                <?php if ($member['phone']): ?>
                                    <p><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72"></path></svg> <?= e($member['phone']) ?></p>
                                <?php endif; ?>
                                <p><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> Joined <?= $member['joined_at'] ? date('M Y', strtotime($member['joined_at'])) : 'N/A' ?></p>
                            </div>

                            <div class="member-actions">
                                <?php if ($member['status'] === 'pending'): ?>
                                    <button class="btn btn-success btn-xs" onclick="approveMember(<?= $member['user_id'] ?>)">Approve</button>
                                    <button class="btn btn-danger btn-xs" onclick="rejectMember(<?= $member['user_id'] ?>)">Reject</button>
                                <?php elseif ($member['status'] === 'active'): ?>
                                    <?php if (!$isCurrentUser && !$isPastor): ?>
                                        <button class="btn btn-outline btn-xs" onclick="openRoleModal(<?= $member['user_id'] ?>, '<?= e($member['name']) ?>', '<?= $member['role'] ?>')">Change Role</button>
                                        <button class="btn btn-outline btn-xs" onclick="openPositionModal(<?= $member['user_id'] ?>, '<?= e($member['name']) ?>')">Assign Position</button>
                                        <button class="btn btn-danger btn-xs" onclick="removeMember(<?= $member['user_id'] ?>, '<?= e($member['name']) ?>')">Remove</button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <a href="/profile/?id=<?= $member['user_id'] ?>" class="btn btn-outline btn-xs">View Profile</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Role Modal -->
    <div class="modal" id="roleModal">
        <div class="modal-content">
            <div class="modal-header">Change Role</div>
            <div class="modal-body">
                <p id="roleModalText"></p>
                <div class="form-group">
                    <label>New Role</label>
                    <select id="newRole">
                        <option value="member">Member</option>
                        <option value="leader">Leader</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('roleModal')">Cancel</button>
                <button class="btn btn-primary" onclick="updateRole()">Save</button>
            </div>
        </div>
    </div>

    <!-- Position Modal -->
    <div class="modal" id="positionModal">
        <div class="modal-content">
            <div class="modal-header">Assign Church Position</div>
            <div class="modal-body">
                <p id="positionModalText"></p>
                <div class="form-group">
                    <label>Position</label>
                    <select id="newPosition">
                        <option value="">Select a position...</option>
                        <?php foreach ($churchPositions as $pos): ?>
                            <option value="<?= $pos['id'] ?>"><?= e($pos['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('positionModal')">Cancel</button>
                <button class="btn btn-primary" onclick="assignPosition()">Assign</button>
            </div>
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <script>
        let selectedUserId = null;

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

        function filterByRole(role) {
            const url = new URL(window.location);
            if (role) {
                url.searchParams.set('role', role);
            } else {
                url.searchParams.delete('role');
            }
            window.location = url;
        }

        function openRoleModal(userId, name, currentRole) {
            selectedUserId = userId;
            document.getElementById('roleModalText').textContent = 'Change role for ' + name;
            document.getElementById('newRole').value = currentRole;
            document.getElementById('roleModal').classList.add('show');
        }

        function openPositionModal(userId, name) {
            selectedUserId = userId;
            document.getElementById('positionModalText').textContent = 'Assign church position to ' + name;
            document.getElementById('newPosition').value = '';
            document.getElementById('positionModal').classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
            selectedUserId = null;
        }

        async function updateRole() {
            const role = document.getElementById('newRole').value;
            try {
                const response = await fetch('/admin_congregation/api/members.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCSRFToken() },
                    body: JSON.stringify({ action: 'update_role', user_id: selectedUserId, role: role })
                });
                const data = await response.json();
                if (data.ok) {
                    showToast('Role updated');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.error || 'Failed to update role', 'error');
                }
            } catch (error) {
                showToast('Network error', 'error');
            }
            closeModal('roleModal');
        }

        async function assignPosition() {
            const positionId = document.getElementById('newPosition').value;
            if (!positionId) {
                showToast('Please select a position', 'error');
                return;
            }
            try {
                const response = await fetch('/admin_congregation/api/positions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCSRFToken() },
                    body: JSON.stringify({ action: 'assign', user_id: selectedUserId, position_id: positionId })
                });
                const data = await response.json();
                if (data.ok) {
                    showToast('Position assigned');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.error || 'Failed to assign position', 'error');
                }
            } catch (error) {
                showToast('Network error', 'error');
            }
            closeModal('positionModal');
        }

        async function approveMember(userId) {
            try {
                const response = await fetch('/admin_congregation/api/members.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCSRFToken() },
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

        async function rejectMember(userId) {
            if (!confirm('Are you sure you want to reject this request?')) return;
            try {
                const response = await fetch('/admin_congregation/api/members.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCSRFToken() },
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

        async function removeMember(userId, name) {
            if (!confirm('Are you sure you want to remove ' + name + ' from the congregation?')) return;
            try {
                const response = await fetch('/admin_congregation/api/members.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCSRFToken() },
                    body: JSON.stringify({ action: 'remove', user_id: userId })
                });
                const data = await response.json();
                if (data.ok) {
                    showToast('Member removed');
                    document.getElementById('member-' + userId).remove();
                } else {
                    showToast(data.error || 'Failed to remove', 'error');
                }
            } catch (error) {
                showToast('Network error', 'error');
            }
        }

        // Close modal on outside click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeModal(modal.id);
            });
        });
    </script>
</body>
</html>
