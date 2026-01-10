<?php
/**
 * CRC Homecells - List
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
if (!$primaryCong) {
    Response::redirect('/onboarding/');
}

$pageTitle = "Homecells - CRC";

// Get user's homecell membership
$myHomecell = Database::fetchOne(
    "SELECT h.*, u.name as leader_name,
            (SELECT COUNT(*) FROM homecell_members WHERE homecell_id = h.id AND status = 'active') as member_count
     FROM homecell_members hm
     JOIN homecells h ON hm.homecell_id = h.id
     LEFT JOIN users u ON h.leader_user_id = u.id
     WHERE hm.user_id = ? AND hm.status = 'active' AND h.congregation_id = ?",
    [$user['id'], $primaryCong['id']]
);

// Get all active homecells in congregation
$homecells = Database::fetchAll(
    "SELECT h.*, u.name as leader_name,
            (SELECT COUNT(*) FROM homecell_members WHERE homecell_id = h.id AND status = 'active') as member_count
     FROM homecells h
     LEFT JOIN users u ON h.leader_user_id = u.id
     WHERE h.congregation_id = ? AND h.status = 'active'
     ORDER BY h.name ASC",
    [$primaryCong['id']]
);

$days = ['monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
         'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/homecells/css/homecells.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="page-header">
                <div class="page-title">
                    <h1>Homecells</h1>
                    <p>Connect with believers in your area</p>
                </div>
            </div>

            <!-- My Homecell -->
            <?php if ($myHomecell): ?>
                <section class="my-homecell">
                    <h2>My Homecell</h2>
                    <a href="/homecells/view.php?id=<?= $myHomecell['id'] ?>" class="my-homecell-card">
                        <div class="card-icon">🏠</div>
                        <div class="card-info">
                            <h3><?= e($myHomecell['name']) ?></h3>
                            <p>Led by <?= e($myHomecell['leader_name']) ?></p>
                            <div class="card-meta">
                                <span>📅 <?= ucfirst($myHomecell['meeting_day']) ?>s at <?= date('g:i A', strtotime($myHomecell['meeting_time'])) ?></span>
                                <span>👥 <?= $myHomecell['member_count'] ?> members</span>
                            </div>
                        </div>
                        <div class="card-arrow">→</div>
                    </a>
                </section>
            <?php endif; ?>

            <!-- All Homecells -->
            <section class="all-homecells">
                <h2>All Homecells</h2>
                <?php if ($homecells): ?>
                    <div class="homecells-grid">
                        <?php foreach ($homecells as $hc): ?>
                            <div class="homecell-card">
                                <div class="card-header">
                                    <h3><?= e($hc['name']) ?></h3>
                                    <?php if ($myHomecell && $myHomecell['id'] == $hc['id']): ?>
                                        <span class="my-badge">My Cell</span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($hc['description']): ?>
                                    <p class="card-desc"><?= e(truncate($hc['description'], 100)) ?></p>
                                <?php endif; ?>

                                <div class="card-details">
                                    <div class="detail-item">
                                        <span class="icon">👤</span>
                                        <span>Led by <?= e($hc['leader_name']) ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="icon">📅</span>
                                        <span><?= ucfirst($hc['meeting_day']) ?>s at <?= date('g:i A', strtotime($hc['meeting_time'])) ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="icon">📍</span>
                                        <span><?= e($hc['location'] ?: 'Contact leader') ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="icon">👥</span>
                                        <span><?= $hc['member_count'] ?> members</span>
                                    </div>
                                </div>

                                <div class="card-actions">
                                    <a href="/homecells/view.php?id=<?= $hc['id'] ?>" class="btn btn-outline">View Details</a>
                                    <?php if (!$myHomecell): ?>
                                        <button onclick="joinHomecell(<?= $hc['id'] ?>)" class="btn btn-primary">Join</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">🏠</div>
                        <h3>No homecells yet</h3>
                        <p>Homecells haven't been set up for this congregation yet.</p>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <script src="/homecells/js/homecells.js"></script>
</body>
</html>
