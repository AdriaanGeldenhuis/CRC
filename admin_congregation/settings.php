<?php
/**
 * CRC Congregation Admin - Settings
 */

require_once __DIR__ . '/../core/bootstrap.php';

// Require authentication
Auth::requireAuth();

// Get primary congregation and check admin access
$primaryCong = Auth::primaryCongregation();
if (!$primaryCong) {
    Response::redirect('/onboarding/');
}

if (!Auth::isCongregationAdmin($primaryCong['id'])) {
    Session::flash('error', 'You do not have admin access');
    Response::redirect('/home/');
}

$congregation = $primaryCong;
$pageTitle = 'Settings - ' . $congregation['name'] . ' - CRC';
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
        .settings-grid { display: grid; gap: 1.5rem; }
        .settings-section { background: white; border-radius: var(--radius-lg); box-shadow: var(--shadow); }
        .settings-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--gray-100); }
        .settings-header h2 { font-size: 1rem; font-weight: 600; color: var(--gray-800); margin: 0; }
        .settings-header p { font-size: 0.8rem; color: var(--gray-500); margin: 0.25rem 0 0; }
        .settings-body { padding: 1.5rem; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-group { margin-bottom: 1.25rem; }
        .form-group:last-child { margin-bottom: 0; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; color: var(--gray-700); }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.625rem; border: 1px solid var(--gray-300); border-radius: var(--radius); font-size: 0.875rem; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79,70,229,0.1); }
        .form-group textarea { min-height: 80px; resize: vertical; }
        .form-group small { font-size: 0.75rem; color: var(--gray-500); margin-top: 0.25rem; display: block; }
        .form-actions { display: flex; gap: 0.75rem; padding-top: 1rem; border-top: 1px solid var(--gray-100); margin-top: 1rem; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.625rem 1.25rem; font-size: 0.875rem; font-weight: 500; border: none; border-radius: var(--radius); cursor: pointer; transition: var(--transition); text-decoration: none; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        @media (max-width: 768px) { .form-row { grid-template-columns: 1fr; } }
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
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                    Dashboard
                </a>
                <a href="/admin_congregation/members.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                    </svg>
                    Members
                </a>
                <a href="/admin_congregation/invites.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="8.5" cy="7" r="4"></circle>
                        <line x1="20" y1="8" x2="20" y2="14"></line>
                        <line x1="23" y1="11" x2="17" y2="11"></line>
                    </svg>
                    Invites
                </a>
                <a href="/admin_congregation/events.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    Events
                </a>
                <a href="/admin_congregation/morning_study.php" class="nav-item">
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
                <a href="/admin_congregation/settings.php" class="nav-item active">
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
                <h1>Settings</h1>
                <p>Manage congregation settings and configuration</p>
            </header>

            <div class="settings-grid">
                <!-- General Settings -->
                <section class="settings-section">
                    <div class="settings-header">
                        <h2>General Information</h2>
                        <p>Basic information about your congregation</p>
                    </div>
                    <div class="settings-body">
                        <form id="generalForm">
                            <input type="hidden" name="action" value="update_general">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Congregation Name</label>
                                    <input type="text" name="name" value="<?= e($congregation['name']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>URL Slug</label>
                                    <input type="text" value="<?= e($congregation['slug'] ?? '') ?>" readonly>
                                    <small>Used in URLs - cannot be changed</small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="description" placeholder="Tell people about your congregation..."><?= e($congregation['description'] ?? '') ?></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Phone</label>
                                    <input type="tel" name="phone" value="<?= e($congregation['phone'] ?? '') ?>" placeholder="+27 XX XXX XXXX">
                                </div>
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" name="email" value="<?= e($congregation['email'] ?? '') ?>" placeholder="contact@church.org">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Website</label>
                                <input type="url" name="website" value="<?= e($congregation['website'] ?? '') ?>" placeholder="https://www.church.org">
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </section>

                <!-- Location -->
                <section class="settings-section">
                    <div class="settings-header">
                        <h2>Location</h2>
                        <p>Where your congregation meets</p>
                    </div>
                    <div class="settings-body">
                        <form id="locationForm">
                            <input type="hidden" name="action" value="update_location">
                            <div class="form-group">
                                <label>Address</label>
                                <textarea name="address" placeholder="Street address..."><?= e($congregation['address'] ?? '') ?></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>City</label>
                                    <input type="text" name="city" value="<?= e($congregation['city'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label>Province</label>
                                    <input type="text" name="province" value="<?= e($congregation['province'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Save Location</button>
                            </div>
                        </form>
                    </div>
                </section>

                <!-- Membership Settings -->
                <section class="settings-section">
                    <div class="settings-header">
                        <h2>Membership Settings</h2>
                        <p>How people can join your congregation</p>
                    </div>
                    <div class="settings-body">
                        <form id="membershipForm">
                            <input type="hidden" name="action" value="update_membership">
                            <div class="form-group">
                                <label>Join Mode</label>
                                <select name="join_mode">
                                    <option value="open" <?= ($congregation['join_mode'] ?? '') === 'open' ? 'selected' : '' ?>>Open - Anyone can join</option>
                                    <option value="approval" <?= ($congregation['join_mode'] ?? 'approval') === 'approval' ? 'selected' : '' ?>>Approval Required - Admin must approve</option>
                                    <option value="invite_only" <?= ($congregation['join_mode'] ?? '') === 'invite_only' ? 'selected' : '' ?>>Invite Only - Must have invite link</option>
                                </select>
                                <small>Controls how new members can join your congregation</small>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Save Settings</button>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
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

        // General Form
        document.getElementById('generalForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);

            try {
                const response = await fetch('/admin_congregation/api/settings.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': getCSRFToken() },
                    body: formData
                });
                const data = await response.json();
                if (data.ok) {
                    showToast('Settings saved');
                } else {
                    showToast(data.error || 'Failed to save', 'error');
                }
            } catch (error) {
                showToast('Network error', 'error');
            }
        });

        // Location Form
        document.getElementById('locationForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);

            try {
                const response = await fetch('/admin_congregation/api/settings.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': getCSRFToken() },
                    body: formData
                });
                const data = await response.json();
                if (data.ok) {
                    showToast('Location saved');
                } else {
                    showToast(data.error || 'Failed to save', 'error');
                }
            } catch (error) {
                showToast('Network error', 'error');
            }
        });

        // Membership Form
        document.getElementById('membershipForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);

            try {
                const response = await fetch('/admin_congregation/api/settings.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': getCSRFToken() },
                    body: formData
                });
                const data = await response.json();
                if (data.ok) {
                    showToast('Membership settings saved');
                } else {
                    showToast(data.error || 'Failed to save', 'error');
                }
            } catch (error) {
                showToast('Network error', 'error');
            }
        });
    </script>
</body>
</html>
