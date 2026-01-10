<?php
/**
 * CRC Onboarding Page
 * Forces user to join or create a congregation
 */

require_once __DIR__ . '/../core/bootstrap.php';

// Require authentication
Auth::requireAuth();

// Check if user already has primary congregation
$primaryCong = Auth::primaryCongregation();
if ($primaryCong) {
    Response::redirect('/home/');
}

$user = Auth::user();
$pageTitle = 'Join a Congregation - CRC';

// Check for invite token
$inviteToken = $_GET['invite'] ?? null;
$invite = null;
$inviteCongregation = null;

if ($inviteToken) {
    $tokenHash = hash('sha256', $inviteToken);
    $invite = Database::fetchOne(
        "SELECT ci.*, c.name as congregation_name, c.id as congregation_id
         FROM congregation_invites ci
         JOIN congregations c ON ci.congregation_id = c.id
         WHERE ci.token_hash = ?
           AND (ci.expires_at IS NULL OR ci.expires_at > NOW())
           AND ci.revoked_at IS NULL
           AND (ci.max_uses IS NULL OR ci.use_count < ci.max_uses)
           AND c.status = 'active'",
        [$tokenHash]
    );

    if ($invite) {
        $inviteCongregation = Database::fetchOne(
            "SELECT * FROM congregations WHERE id = ?",
            [$invite['congregation_id']]
        );
    }
}

// Get list of open/approval congregations
$congregations = Database::fetchAll(
    "SELECT c.*,
            (SELECT COUNT(*) FROM user_congregations WHERE congregation_id = c.id AND status = 'active') as member_count
     FROM congregations c
     WHERE c.status = 'active'
       AND c.join_mode IN ('open', 'approval')
     ORDER BY c.name ASC
     LIMIT 50"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/onboarding/css/onboarding.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="onboarding-container">
        <div class="onboarding-card">
            <div class="onboarding-header">
                <div class="logo">CRC</div>
                <h1>Welcome, <?= e(explode(' ', $user['name'])[0]) ?>!</h1>
                <p>To get started, join a congregation or create your own.</p>
            </div>

            <?php if ($invite && $inviteCongregation): ?>
                <!-- Invite Acceptance -->
                <div class="invite-section">
                    <div class="invite-card">
                        <div class="invite-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                        </div>
                        <h2>You've been invited!</h2>
                        <p>You've been invited to join <strong><?= e($inviteCongregation['name']) ?></strong></p>
                        <?php if ($inviteCongregation['city']): ?>
                            <span class="congregation-location"><?= e($inviteCongregation['city']) ?>, <?= e($inviteCongregation['province']) ?></span>
                        <?php endif; ?>
                        <button class="btn btn-primary btn-block" onclick="acceptInvite('<?= e($inviteToken) ?>')">
                            Accept Invitation
                        </button>
                        <button class="btn btn-outline btn-block" onclick="showCongregationList()">
                            Browse Other Congregations
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tab Navigation -->
            <div class="tabs" id="tabsNav" <?= ($invite && $inviteCongregation) ? 'style="display:none;"' : '' ?>>
                <button class="tab active" data-tab="join">Join Congregation</button>
                <button class="tab" data-tab="create">Create New</button>
            </div>

            <!-- Join Congregation Tab -->
            <div class="tab-content active" id="join-tab" <?= ($invite && $inviteCongregation) ? 'style="display:none;"' : '' ?>>
                <?php if ($congregations): ?>
                    <div class="search-box">
                        <input type="text" id="searchInput" placeholder="Search congregations..." autocomplete="off">
                    </div>
                    <div class="congregation-list" id="congregationList">
                        <?php foreach ($congregations as $cong): ?>
                            <div class="congregation-item" data-name="<?= e(strtolower($cong['name'])) ?>">
                                <div class="congregation-info">
                                    <h3><?= e($cong['name']) ?></h3>
                                    <p>
                                        <?php if ($cong['city']): ?>
                                            <?= e($cong['city']) ?>, <?= e($cong['province']) ?>
                                        <?php endif; ?>
                                        <span class="member-count"><?= $cong['member_count'] ?> members</span>
                                    </p>
                                </div>
                                <div class="congregation-action">
                                    <?php if ($cong['join_mode'] === 'open'): ?>
                                        <button class="btn btn-primary btn-sm" onclick="joinCongregation(<?= $cong['id'] ?>)">Join</button>
                                    <?php else: ?>
                                        <button class="btn btn-outline btn-sm" onclick="requestToJoin(<?= $cong['id'] ?>)">Request to Join</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-congregations">
                        <p>No congregations available to join yet.</p>
                        <p>You can create your own congregation.</p>
                    </div>
                <?php endif; ?>

                <div class="invite-code-section">
                    <p>Have an invite code?</p>
                    <div class="invite-code-input">
                        <input type="text" id="inviteCode" placeholder="Enter invite code">
                        <button class="btn btn-primary" onclick="useInviteCode()">Apply</button>
                    </div>
                </div>
            </div>

            <!-- Create Congregation Tab -->
            <div class="tab-content" id="create-tab">
                <form id="createForm" class="create-form">
                    <div class="form-group">
                        <label for="congName">Congregation Name *</label>
                        <input type="text" id="congName" name="name" required placeholder="e.g., Grace Community Church">
                        <span class="error-message" id="name-error"></span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="congCity">City *</label>
                            <input type="text" id="congCity" name="city" required placeholder="Cape Town">
                            <span class="error-message" id="city-error"></span>
                        </div>
                        <div class="form-group">
                            <label for="congProvince">Province *</label>
                            <select id="congProvince" name="province" required>
                                <option value="">Select province</option>
                                <option value="Eastern Cape">Eastern Cape</option>
                                <option value="Free State">Free State</option>
                                <option value="Gauteng">Gauteng</option>
                                <option value="KwaZulu-Natal">KwaZulu-Natal</option>
                                <option value="Limpopo">Limpopo</option>
                                <option value="Mpumalanga">Mpumalanga</option>
                                <option value="North West">North West</option>
                                <option value="Northern Cape">Northern Cape</option>
                                <option value="Western Cape">Western Cape</option>
                            </select>
                            <span class="error-message" id="province-error"></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="congDescription">Description <span class="optional">(optional)</span></label>
                        <textarea id="congDescription" name="description" rows="3" placeholder="Tell us about your congregation..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="congJoinMode">How can people join?</label>
                        <select id="congJoinMode" name="join_mode">
                            <option value="approval">Require approval (recommended)</option>
                            <option value="open">Open - anyone can join</option>
                            <option value="invite_only">Invite only</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" id="createBtn">
                        <span class="btn-text">Create Congregation</span>
                        <span class="btn-loading" style="display:none;">Creating...</span>
                    </button>
                </form>
            </div>

            <div class="onboarding-footer">
                <a href="/auth/logout.php">Sign out</a>
            </div>
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <script src="/onboarding/js/onboarding.js"></script>
</body>
</html>
