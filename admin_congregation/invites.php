<?php
/**
 * CRC Congregation Admin - Invite Links Management
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
$pageTitle = 'Invites - ' . $congregation['name'] . ' - CRC';

// Get active invite links
$invites = Database::fetchAll(
    "SELECT ci.*, u.name as created_by_name
     FROM congregation_invites ci
     JOIN users u ON ci.created_by = u.id
     WHERE ci.congregation_id = ?
       AND ci.revoked_at IS NULL
       AND (ci.expires_at IS NULL OR ci.expires_at > NOW())
     ORDER BY ci.created_at DESC",
    [$congregation['id']]
) ?: [];

// Get expired/revoked invites
$expiredInvites = Database::fetchAll(
    "SELECT ci.*, u.name as created_by_name
     FROM congregation_invites ci
     JOIN users u ON ci.created_by = u.id
     WHERE ci.congregation_id = ?
       AND (ci.revoked_at IS NOT NULL OR (ci.expires_at IS NOT NULL AND ci.expires_at <= NOW()))
     ORDER BY ci.created_at DESC
     LIMIT 10",
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
        .page-actions { margin-bottom: 1.5rem; }
        .invite-grid { display: grid; gap: 1rem; }
        .invite-card { background: white; border-radius: var(--radius-lg); padding: 1.5rem; box-shadow: var(--shadow); }
        .invite-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; }
        .invite-role { font-size: 0.75rem; padding: 0.25rem 0.75rem; border-radius: 100px; font-weight: 500; }
        .invite-role.member { background: var(--gray-100); color: var(--gray-600); }
        .invite-role.leader { background: #D1FAE5; color: #065F46; }
        .invite-role.admin { background: #DBEAFE; color: #1E40AF; }
        .invite-link-box { background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 0.75rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .invite-link-text { flex: 1; font-family: monospace; font-size: 0.8rem; color: var(--gray-600); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .copy-btn { background: var(--primary); color: white; border: none; padding: 0.5rem 1rem; border-radius: var(--radius); font-size: 0.75rem; cursor: pointer; }
        .copy-btn:hover { background: var(--primary-dark); }
        .invite-stats { display: flex; gap: 2rem; font-size: 0.875rem; color: var(--gray-600); margin-bottom: 1rem; }
        .invite-stat { display: flex; align-items: center; gap: 0.5rem; }
        .invite-stat svg { width: 16px; height: 16px; }
        .invite-meta { font-size: 0.75rem; color: var(--gray-400); }
        .invite-actions { display: flex; gap: 0.5rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--gray-100); }
        .btn-xs { padding: 0.25rem 0.75rem; font-size: 0.75rem; }
        .btn-outline { background: transparent; border: 1px solid var(--gray-300); color: var(--gray-600); }
        .btn-primary { background: var(--primary); color: white; border: none; }
        .expired-badge { font-size: 0.7rem; padding: 0.125rem 0.5rem; background: var(--gray-100); color: var(--gray-500); border-radius: 100px; }
        .revoked-badge { font-size: 0.7rem; padding: 0.125rem 0.5rem; background: #FEE2E2; color: #991B1B; border-radius: 100px; }

        .modal { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; }
        .modal.show { display: flex; }
        .modal-content { background: white; border-radius: var(--radius-lg); padding: 1.5rem; max-width: 450px; width: 90%; }
        .modal-header { font-weight: 600; margin-bottom: 1rem; font-size: 1.1rem; }
        .modal-body { margin-bottom: 1.5rem; }
        .modal-footer { display: flex; gap: 0.5rem; justify-content: flex-end; }

        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; color: var(--gray-700); }
        .form-group select, .form-group input { width: 100%; padding: 0.625rem; border: 1px solid var(--gray-300); border-radius: var(--radius); font-size: 0.875rem; }
        .form-group small { font-size: 0.75rem; color: var(--gray-500); margin-top: 0.25rem; display: block; }

        .empty-state { text-align: center; padding: 3rem; }
        .empty-state svg { width: 64px; height: 64px; color: var(--gray-300); margin-bottom: 1rem; }
        .empty-state h3 { color: var(--gray-700); margin-bottom: 0.5rem; }
        .empty-state p { color: var(--gray-500); margin-bottom: 1.5rem; }
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
                <a href="/admin_congregation/members.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                    Members
                </a>
                <a href="/admin_congregation/invites.php" class="nav-item active">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                    Invites
                </a>
                <a href="/admin_congregation/events.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    Events
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
                <h1>Invite Links</h1>
                <p>Create and manage invite links for new members</p>
            </header>

            <div class="page-actions">
                <button class="btn btn-primary" onclick="openCreateModal()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Create Invite Link
                </button>
            </div>

            <!-- Active Invites -->
            <?php if (empty($invites)): ?>
                <div class="card">
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <line x1="20" y1="8" x2="20" y2="14"></line>
                            <line x1="23" y1="11" x2="17" y2="11"></line>
                        </svg>
                        <h3>No active invite links</h3>
                        <p>Create an invite link to allow new members to join your congregation.</p>
                        <button class="btn btn-primary" onclick="openCreateModal()">Create Invite Link</button>
                    </div>
                </div>
            <?php else: ?>
                <div class="invite-grid">
                    <?php foreach ($invites as $invite):
                        $inviteUrl = APP_URL . '/join/' . $invite['token_hash'];
                    ?>
                        <div class="invite-card" id="invite-<?= $invite['id'] ?>">
                            <div class="invite-header">
                                <span class="invite-role <?= $invite['role'] ?>"><?= ucfirst($invite['role']) ?> Role</span>
                                <?php if ($invite['max_uses']): ?>
                                    <span style="font-size: 0.75rem; color: var(--gray-500);">
                                        <?= $invite['use_count'] ?>/<?= $invite['max_uses'] ?> uses
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="invite-link-box">
                                <span class="invite-link-text" id="link-<?= $invite['id'] ?>"><?= e($inviteUrl) ?></span>
                                <button class="copy-btn" onclick="copyLink('<?= e($inviteUrl) ?>')">Copy</button>
                            </div>

                            <div class="invite-stats">
                                <div class="invite-stat">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                                    <?= $invite['use_count'] ?> joined
                                </div>
                                <?php if ($invite['expires_at']): ?>
                                    <div class="invite-stat">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                        Expires <?= date('M j, Y', strtotime($invite['expires_at'])) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="invite-stat">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                        Never expires
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="invite-meta">
                                Created by <?= e($invite['created_by_name']) ?> on <?= date('M j, Y', strtotime($invite['created_at'])) ?>
                            </div>

                            <div class="invite-actions">
                                <button class="btn btn-outline btn-xs" onclick="copyLink('<?= e($inviteUrl) ?>')">Copy Link</button>
                                <button class="btn btn-danger btn-xs" onclick="revokeInvite(<?= $invite['id'] ?>)">Revoke</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Expired/Revoked Invites -->
            <?php if (!empty($expiredInvites)): ?>
                <section class="card" style="margin-top: 2rem;">
                    <div class="card-header">
                        <h2>Expired/Revoked Invites</h2>
                    </div>
                    <div class="member-list">
                        <?php foreach ($expiredInvites as $invite): ?>
                            <div class="member-item">
                                <div class="member-info">
                                    <div>
                                        <strong><?= ucfirst($invite['role']) ?> invite</strong>
                                        <span><?= $invite['use_count'] ?> people joined</span>
                                        <span class="time">Created <?= time_ago($invite['created_at']) ?></span>
                                    </div>
                                </div>
                                <?php if ($invite['revoked_at']): ?>
                                    <span class="revoked-badge">Revoked</span>
                                <?php else: ?>
                                    <span class="expired-badge">Expired</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </div>

    <!-- Create Modal -->
    <div class="modal" id="createModal">
        <div class="modal-content">
            <div class="modal-header">Create Invite Link</div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Role for new members</label>
                    <select id="inviteRole">
                        <option value="member">Member</option>
                        <option value="leader">Leader</option>
                        <option value="admin">Admin</option>
                    </select>
                    <small>New members who join using this link will get this role.</small>
                </div>

                <div class="form-group">
                    <label>Maximum uses (optional)</label>
                    <input type="number" id="maxUses" placeholder="Unlimited" min="1">
                    <small>Leave empty for unlimited uses.</small>
                </div>

                <div class="form-group">
                    <label>Expiry date (optional)</label>
                    <input type="date" id="expiryDate" min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                    <small>Leave empty to never expire.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('createModal')">Cancel</button>
                <button class="btn btn-primary" onclick="createInvite()">Create Link</button>
            </div>
        </div>
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

        function openCreateModal() {
            document.getElementById('inviteRole').value = 'member';
            document.getElementById('maxUses').value = '';
            document.getElementById('expiryDate').value = '';
            document.getElementById('createModal').classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        async function createInvite() {
            const role = document.getElementById('inviteRole').value;
            const maxUses = document.getElementById('maxUses').value;
            const expiryDate = document.getElementById('expiryDate').value;

            try {
                const response = await fetch('/admin_congregation/api/invites.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCSRFToken() },
                    body: JSON.stringify({
                        action: 'create',
                        role: role,
                        max_uses: maxUses || null,
                        expires_at: expiryDate || null
                    })
                });
                const data = await response.json();
                if (data.ok) {
                    showToast('Invite link created');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.error || 'Failed to create invite', 'error');
                }
            } catch (error) {
                showToast('Network error', 'error');
            }
            closeModal('createModal');
        }

        async function revokeInvite(inviteId) {
            if (!confirm('Are you sure you want to revoke this invite link? People will no longer be able to use it.')) return;

            try {
                const response = await fetch('/admin_congregation/api/invites.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCSRFToken() },
                    body: JSON.stringify({ action: 'revoke', invite_id: inviteId })
                });
                const data = await response.json();
                if (data.ok) {
                    showToast('Invite revoked');
                    document.getElementById('invite-' + inviteId).remove();
                } else {
                    showToast(data.error || 'Failed to revoke', 'error');
                }
            } catch (error) {
                showToast('Network error', 'error');
            }
        }

        function copyLink(url) {
            navigator.clipboard.writeText(url).then(() => {
                showToast('Link copied to clipboard');
            }).catch(() => {
                showToast('Failed to copy', 'error');
            });
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
