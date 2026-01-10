<?php
/**
 * CRC Homecells - Detail View
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
$homecellId = (int)($_GET['id'] ?? 0);

if (!$homecellId) {
    Response::redirect('/homecells/');
}

// Get homecell
$homecell = Database::fetchOne(
    "SELECT h.*, u.name as leader_name, u.email as leader_email
     FROM homecells h
     LEFT JOIN users u ON h.leader_user_id = u.id
     WHERE h.id = ? AND h.congregation_id = ?",
    [$homecellId, $primaryCong['id']]
);

if (!$homecell) {
    Response::redirect('/homecells/');
}

$pageTitle = e($homecell['name']) . " - Homecells";

// Check membership
$membership = Database::fetchOne(
    "SELECT * FROM homecell_members WHERE homecell_id = ? AND user_id = ?",
    [$homecellId, $user['id']]
);
$isMember = $membership && $membership['status'] === 'active';
$isLeader = $homecell['leader_user_id'] == $user['id'];

// Get members
$members = Database::fetchAll(
    "SELECT hm.*, u.name, u.avatar_url, u.email
     FROM homecell_members hm
     JOIN users u ON hm.user_id = u.id
     WHERE hm.homecell_id = ? AND hm.status = 'active'
     ORDER BY hm.role DESC, u.name ASC",
    [$homecellId]
);

// Get upcoming meetings
$meetings = Database::fetchAll(
    "SELECT * FROM homecell_meetings
     WHERE homecell_id = ? AND meeting_date >= CURDATE()
     ORDER BY meeting_date ASC, meeting_time ASC
     LIMIT 5",
    [$homecellId]
);

$days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/homecells/css/homecells.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <a href="/homecells/" class="back-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
                Back to Homecells
            </a>

            <!-- Homecell Header -->
            <div class="homecell-header">
                <div class="header-info">
                    <h1><?= e($homecell['name']) ?></h1>
                    <?php if ($homecell['description']): ?>
                        <p class="header-desc"><?= e($homecell['description']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="header-actions">
                    <?php if ($isMember): ?>
                        <span class="member-badge">✓ Member</span>
                        <?php if (!$isLeader): ?>
                            <button onclick="leaveHomecell(<?= $homecellId ?>)" class="btn btn-outline btn-danger">Leave</button>
                        <?php endif; ?>
                    <?php else: ?>
                        <button onclick="joinHomecell(<?= $homecellId ?>)" class="btn btn-primary">Join Homecell</button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="homecell-layout">
                <!-- Main Content -->
                <div class="homecell-main">
                    <!-- Meeting Info -->
                    <div class="info-card">
                        <h2>Meeting Information</h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Day & Time</span>
                                <span class="info-value">
                                    <?= ucfirst($homecell['meeting_day']) ?>s at <?= date('g:i A', strtotime($homecell['meeting_time'])) ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Frequency</span>
                                <span class="info-value"><?= ucfirst($homecell['meeting_frequency']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Location</span>
                                <span class="info-value"><?= e($homecell['location'] ?: 'Contact leader for address') ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Leader</span>
                                <span class="info-value"><?= e($homecell['leader_name']) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Meetings -->
                    <?php if ($meetings && $isMember): ?>
                        <div class="info-card">
                            <h2>Upcoming Meetings</h2>
                            <div class="meetings-list">
                                <?php foreach ($meetings as $meeting): ?>
                                    <div class="meeting-item">
                                        <div class="meeting-date">
                                            <span class="day"><?= date('d', strtotime($meeting['meeting_date'])) ?></span>
                                            <span class="month"><?= date('M', strtotime($meeting['meeting_date'])) ?></span>
                                        </div>
                                        <div class="meeting-info">
                                            <h4><?= e($meeting['topic'] ?: 'Regular Meeting') ?></h4>
                                            <p><?= date('l', strtotime($meeting['meeting_date'])) ?> at <?= date('g:i A', strtotime($meeting['meeting_time'])) ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Members -->
                    <?php if ($isMember || $isLeader): ?>
                        <div class="info-card">
                            <h2>Members (<?= count($members) ?>)</h2>
                            <div class="members-grid">
                                <?php foreach ($members as $member): ?>
                                    <div class="member-card">
                                        <?php if ($member['avatar_url']): ?>
                                            <img src="<?= e($member['avatar_url']) ?>" alt="" class="member-avatar">
                                        <?php else: ?>
                                            <div class="member-avatar placeholder">
                                                <?= strtoupper(substr($member['name'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="member-info">
                                            <h4><?= e($member['name']) ?></h4>
                                            <?php if ($member['role'] === 'leader'): ?>
                                                <span class="role-badge leader">Leader</span>
                                            <?php elseif ($member['role'] === 'assistant'): ?>
                                                <span class="role-badge assistant">Assistant</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <aside class="homecell-sidebar">
                    <!-- Leader Contact -->
                    <div class="sidebar-card">
                        <h3>Contact Leader</h3>
                        <div class="leader-info">
                            <div class="leader-avatar">
                                <?= strtoupper(substr($homecell['leader_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <h4><?= e($homecell['leader_name']) ?></h4>
                                <?php if ($isMember && $homecell['leader_email']): ?>
                                    <a href="mailto:<?= e($homecell['leader_email']) ?>" class="leader-email">
                                        <?= e($homecell['leader_email']) ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="sidebar-card">
                        <h3>Quick Stats</h3>
                        <div class="stats-list">
                            <div class="stat-item">
                                <span class="stat-value"><?= count($members) ?></span>
                                <span class="stat-label">Members</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value"><?= ucfirst($homecell['meeting_frequency']) ?></span>
                                <span class="stat-label">Meetings</span>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </main>

    <script>
        const homecellId = <?= $homecellId ?>;
    </script>
    <script src="/homecells/js/homecells.js"></script>
</body>
</html>
