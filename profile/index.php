<?php
/**
 * CRC Profile Page
 * View and edit user profile
 */

require_once __DIR__ . '/../core/bootstrap.php';

header('Content-Type: text/html; charset=UTF-8');

Auth::requireAuth();

$currentUser = Auth::user();
$pageTitle = 'My Profile - CRC';

// Check if viewing another user's profile
$viewUserId = (int)($_GET['id'] ?? $currentUser['id']);
$isOwnProfile = ($viewUserId === (int)$currentUser['id']);

// Get profile user
if ($isOwnProfile) {
    $user = $currentUser;
} else {
    $user = Database::fetchOne(
        "SELECT * FROM users WHERE id = ? AND status = 'active'",
        [$viewUserId]
    );
    if (!$user) {
        Response::redirect('/home/');
    }
    $pageTitle = e($user['name']) . ' - CRC';
}

// Get user's primary congregation
$congregation = null;
$congregationRole = null;
try {
    $membership = Database::fetchOne(
        "SELECT uc.*, c.name as congregation_name, c.id as congregation_id
         FROM user_congregations uc
         JOIN congregations c ON uc.congregation_id = c.id
         WHERE uc.user_id = ? AND uc.is_primary = 1 AND uc.status = 'active'",
        [$user['id']]
    );
    if ($membership) {
        $congregation = $membership;
        $congregationRole = $membership['role'];
    }
} catch (Exception $e) {}

// Get user's church positions
$positions = [];
try {
    $positions = Database::fetchAll(
        "SELECT ucp.*, cp.name as position_name, cp.description
         FROM user_church_positions ucp
         JOIN church_positions cp ON ucp.position_id = cp.id
         WHERE ucp.user_id = ? AND ucp.is_active = 1
         ORDER BY cp.display_order ASC",
        [$user['id']]
    ) ?: [];
} catch (Exception $e) {}

// Get all available positions for editing (if own profile)
$availablePositions = [];
if ($isOwnProfile && $congregation) {
    try {
        $availablePositions = Database::fetchAll(
            "SELECT * FROM church_positions
             WHERE congregation_id = ? AND is_active = 1
             ORDER BY display_order ASC",
            [$congregation['congregation_id']]
        ) ?: [];
    } catch (Exception $e) {}
}

// Calculate age
$age = null;
$birthdayDisplay = null;
if (!empty($user['date_of_birth'])) {
    $birthDate = new DateTime($user['date_of_birth']);
    $today = new DateTime();
    $age = $birthDate->diff($today)->y;
    $birthdayDisplay = $birthDate->format('j F');
}

// Privacy settings
$showBirthday = $isOwnProfile || (!empty($user['show_birthday']) && $user['show_birthday']);
$showAge = $isOwnProfile || (!empty($user['show_age']) && $user['show_age']);
$showEmail = $isOwnProfile || (!empty($user['show_email']) && $user['show_email']);
$showPhone = $isOwnProfile || (!empty($user['show_phone']) && $user['show_phone']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/profile/css/profile.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        (function() {
            const saved = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', saved);
        })();
    </script>
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="profile-container">
        <!-- Cover Image -->
        <div class="profile-cover">
            <?php if (!empty($user['cover_image'])): ?>
                <img src="<?= e($user['cover_image']) ?>" alt="Cover">
            <?php endif; ?>
            <?php if ($isOwnProfile): ?>
                <button class="profile-cover-edit" id="coverUpload">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                        <circle cx="12" cy="13" r="4"></circle>
                    </svg>
                    Edit Cover
                </button>
                <input type="file" id="coverFile" class="file-upload" accept="image/*">
            <?php endif; ?>
        </div>

        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-header-content">
                <div class="profile-avatar-container">
                    <?php if (!empty($user['avatar'])): ?>
                        <img src="<?= e($user['avatar']) ?>" alt="<?= e($user['name']) ?>" class="profile-avatar">
                    <?php else: ?>
                        <div class="profile-avatar-placeholder">
                            <?= strtoupper(substr($user['name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($isOwnProfile): ?>
                        <button class="profile-avatar-edit" id="avatarUpload">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                <circle cx="12" cy="13" r="4"></circle>
                            </svg>
                        </button>
                        <input type="file" id="avatarFile" class="file-upload" accept="image/*">
                    <?php endif; ?>
                </div>

                <div class="profile-info">
                    <h1 class="profile-name"><?= e($user['name']) ?></h1>
                    <?php if ($congregation): ?>
                        <p class="profile-role">
                            <?= ucfirst($congregationRole) ?> at <?= e($congregation['congregation_name']) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($positions)): ?>
                        <div class="profile-positions">
                            <?php foreach ($positions as $pos): ?>
                                <span class="position-badge">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/>
                                    </svg>
                                    <?= e($pos['position_name']) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($isOwnProfile): ?>
                    <div class="profile-actions">
                        <button class="btn btn-primary" id="editProfileBtn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                            Edit Profile
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Profile Content -->
        <div class="profile-grid">
            <!-- Left Sidebar -->
            <div class="profile-sidebar">
                <!-- View Mode -->
                <div id="profileView">
                    <!-- About Card -->
                    <div class="profile-card">
                        <h3>About</h3>

                        <?php if (!empty($user['bio'])): ?>
                            <p class="profile-bio"><?= e($user['bio']) ?></p>
                        <?php elseif ($isOwnProfile): ?>
                            <p class="profile-bio-empty">Add a bio to tell people about yourself</p>
                        <?php endif; ?>

                        <div style="margin-top: 1rem;">
                            <?php if (!empty($user['occupation'])): ?>
                                <div class="about-item">
                                    <svg class="about-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                        <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                                    </svg>
                                    <div class="about-content">
                                        <div class="about-label">Occupation</div>
                                        <div class="about-value"><?= e($user['occupation']) ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($user['location'])): ?>
                                <div class="about-item">
                                    <svg class="about-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                        <circle cx="12" cy="10" r="3"></circle>
                                    </svg>
                                    <div class="about-content">
                                        <div class="about-label">Location</div>
                                        <div class="about-value"><?= e($user['location']) ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($showEmail && !empty($user['email'])): ?>
                                <div class="about-item">
                                    <svg class="about-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                        <polyline points="22,6 12,13 2,6"></polyline>
                                    </svg>
                                    <div class="about-content">
                                        <div class="about-label">Email</div>
                                        <div class="about-value"><?= e($user['email']) ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($showPhone && !empty($user['phone'])): ?>
                                <div class="about-item">
                                    <svg class="about-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                    </svg>
                                    <div class="about-content">
                                        <div class="about-label">Phone</div>
                                        <div class="about-value"><?= e($user['phone']) ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($congregation): ?>
                                <div class="about-item">
                                    <svg class="about-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M3 21h18M9 8h1M9 12h1M9 16h1M14 8h1M14 12h1M14 16h1M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"></path>
                                    </svg>
                                    <div class="about-content">
                                        <div class="about-label">Congregation</div>
                                        <div class="about-value"><?= e($congregation['congregation_name']) ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Birthday Card -->
                    <?php if ($showBirthday && $birthdayDisplay): ?>
                        <div class="profile-card birthday-card">
                            <h3>🎂 Birthday</h3>
                            <div class="birthday-display">
                                <div class="birthday-icon">🎈</div>
                                <div class="birthday-info">
                                    <div class="birthday-date"><?= $birthdayDisplay ?></div>
                                    <?php if ($showAge && $age): ?>
                                        <div class="birthday-age"><?= $age ?> years old</div>
                                    <?php endif; ?>
                                    <?php if ($isOwnProfile): ?>
                                        <div class="birthday-age" id="birthdayCountdown" data-birthday="<?= e($user['date_of_birth']) ?>"></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Church Positions Card -->
                    <?php if (!empty($positions)): ?>
                        <div class="profile-card">
                            <h3>Church Positions</h3>
                            <?php foreach ($positions as $pos): ?>
                                <div class="position-item">
                                    <div class="position-icon">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/>
                                        </svg>
                                    </div>
                                    <div class="position-details">
                                        <div class="position-name"><?= e($pos['position_name']) ?></div>
                                        <?php if (!empty($pos['appointed_at'])): ?>
                                            <div class="position-since">Since <?= date('F Y', strtotime($pos['appointed_at'])) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Edit Mode -->
                <?php if ($isOwnProfile): ?>
                    <div id="profileEdit" style="display: none;">
                        <div class="profile-card">
                            <h3>Edit Profile</h3>
                            <form id="profileForm">
                                <div class="form-group">
                                    <label for="name">Full Name</label>
                                    <input type="text" id="name" name="name" value="<?= e($user['name']) ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="bio">Bio</label>
                                    <textarea id="bio" name="bio" placeholder="Tell us about yourself..."><?= e($user['bio'] ?? '') ?></textarea>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="occupation">Occupation</label>
                                        <input type="text" id="occupation" name="occupation" value="<?= e($user['occupation'] ?? '') ?>" placeholder="What do you do?">
                                    </div>
                                    <div class="form-group">
                                        <label for="location">Location</label>
                                        <input type="text" id="location" name="location" value="<?= e($user['location'] ?? '') ?>" placeholder="City, Country">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="phone">Phone Number</label>
                                        <input type="tel" id="phone" name="phone" value="<?= e($user['phone'] ?? '') ?>" placeholder="+27 XX XXX XXXX">
                                    </div>
                                    <div class="form-group">
                                        <label for="date_of_birth">Date of Birth</label>
                                        <input type="date" id="date_of_birth" name="date_of_birth" value="<?= e($user['date_of_birth'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Privacy Settings</label>
                                    <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-top: 0.5rem;">
                                        <label class="form-checkbox">
                                            <input type="checkbox" name="show_birthday" value="1" <?= !empty($user['show_birthday']) ? 'checked' : '' ?>>
                                            Show birthday to others
                                        </label>
                                        <label class="form-checkbox">
                                            <input type="checkbox" name="show_age" value="1" <?= !empty($user['show_age']) ? 'checked' : '' ?>>
                                            Show age to others
                                        </label>
                                        <label class="form-checkbox">
                                            <input type="checkbox" name="show_email" value="1" <?= !empty($user['show_email']) ? 'checked' : '' ?>>
                                            Show email to others
                                        </label>
                                        <label class="form-checkbox">
                                            <input type="checkbox" name="show_phone" value="1" <?= !empty($user['show_phone']) ? 'checked' : '' ?>>
                                            Show phone to others
                                        </label>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                    <button type="button" class="btn btn-secondary" id="cancelEditBtn">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Main Content Area -->
            <div class="profile-main">
                <div class="profile-card">
                    <h3>Activity</h3>
                    <p style="color: #6B7280; text-align: center; padding: 2rem;">
                        Activity feed coming soon...
                    </p>
                </div>
            </div>
        </div>
    </main>

    <script src="/profile/js/profile.js"></script>
</body>
</html>
