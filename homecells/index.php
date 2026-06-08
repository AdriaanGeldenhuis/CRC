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

// Initialize defaults
$myHomecell = null;
$homecells = [];

// Get user's homecell membership
try {
    $myHomecell = Database::fetchOne(
        "SELECT h.*, u.name as leader_name,
                (SELECT COUNT(*) FROM homecell_members WHERE homecell_id = h.id AND status = 'active') as member_count
         FROM homecell_members hm
         JOIN homecells h ON hm.homecell_id = h.id
         LEFT JOIN users u ON h.leader_user_id = u.id
         WHERE hm.user_id = ? AND hm.status = 'active' AND h.congregation_id = ?",
        [$user['id'], $primaryCong['id']]
    );
} catch (Exception $e) {}

// Get all active homecells in congregation
try {
    $homecells = Database::fetchAll(
        "SELECT h.*, u.name as leader_name,
                (SELECT COUNT(*) FROM homecell_members WHERE homecell_id = h.id AND status = 'active') as member_count
         FROM homecells h
         LEFT JOIN users u ON h.leader_user_id = u.id
         WHERE h.congregation_id = ? AND h.status = 'active'
         ORDER BY h.name ASC",
        [$primaryCong['id']]
    ) ?: [];
} catch (Exception $e) {}

$days = ['monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
         'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#7C3AED">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/home/css/home.css?v=<?= filemtime(__DIR__ . '/../home/css/home.css') ?>">
    <link rel="stylesheet" href="/gospel_media/css/gospel_media.css?v=<?= filemtime(__DIR__ . '/../gospel_media/css/gospel_media.css') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        (function() {
            const saved = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', saved);
        })();
    </script>
    <style>
        /* ===== Homecells — home design components ===== */
        .page-header { padding: 6px 2px 14px; }
        .page-title { font-size: 22px; font-weight: 800; letter-spacing: -0.3px; }
        .page-subtitle { font-size: 13px; color: var(--muted); margin-top: 4px; }
        .section { margin-bottom: 18px; }
        .section-title { font-size: 16px; font-weight: 700; margin: 4px 2px 12px; }

        .my-cell-card {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow2);
            text-decoration: none;
            color: var(--text);
            position: relative;
            transition: transform 0.3s cubic-bezier(0.2,0.8,0.25,1.2), box-shadow 0.3s ease, border-color 0.3s ease;
        }
        .my-cell-card:hover {
            transform: translateY(-3px);
            border-color: rgba(124,58,237,0.40);
            box-shadow: 0 22px 44px rgba(0,0,0,0.45), 0 0 26px rgba(124,58,237,0.16);
        }
        .cell-icon {
            width: 52px; height: 52px;
            flex-shrink: 0;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            display: flex; align-items: center; justify-content: center;
            color: #fff;
            box-shadow: 0 10px 24px rgba(124,58,237,0.32), inset 0 1px 2px rgba(255,255,255,0.45);
        }
        .cell-info { flex: 1; min-width: 0; }
        .cell-name { font-size: 16px; font-weight: 700; }
        .cell-leader { font-size: 13px; color: var(--muted); margin: 2px 0 6px; }
        .cell-meta { display: flex; flex-wrap: wrap; gap: 14px; font-size: 12px; color: var(--muted); }
        .cell-meta span { display: inline-flex; align-items: center; gap: 5px; }
        .cell-meta svg { color: var(--accent); }
        .cell-arrow { color: var(--muted); flex-shrink: 0; }
        .cell-arrow svg { width: 20px; height: 20px; }

        .cells-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; }

        .cell-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow2);
            padding: 16px;
            position: relative;
            transition: transform 0.35s cubic-bezier(0.2,0.8,0.25,1.2), box-shadow 0.35s ease, border-color 0.35s ease;
        }
        .cell-card::after {
            content: ""; position: absolute; top: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.40), transparent);
            pointer-events: none;
        }
        .cell-card:hover {
            transform: translateY(-4px);
            border-color: rgba(124,58,237,0.40);
            box-shadow: 0 26px 54px rgba(0,0,0,0.50), 0 0 28px rgba(124,58,237,0.16);
        }
        .card-header { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 8px; }
        .card-name { font-size: 16px; font-weight: 700; }
        .my-badge {
            font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 999px; color: #fff;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
        }
        .card-desc { font-size: 14px; color: var(--muted); line-height: 1.5; margin-bottom: 12px; }
        .card-details { display: flex; flex-direction: column; gap: 9px; margin-bottom: 14px; }
        .detail-item { display: flex; align-items: center; gap: 10px; font-size: 13px; color: var(--muted); }
        .detail-icon {
            width: 30px; height: 30px; flex-shrink: 0;
            border-radius: 9px;
            background: var(--card2);
            border: 1px solid var(--line);
            display: flex; align-items: center; justify-content: center;
            color: var(--accent);
        }
        .detail-icon svg { width: 15px; height: 15px; }
        .card-actions { display: flex; gap: 10px; }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 10px 14px; border-radius: 12px; font-size: 13px; font-weight: 600; font-family: inherit;
            text-decoration: none; cursor: pointer; border: 1px solid var(--line); color: var(--text); flex: 1;
            transition: transform 0.2s ease, box-shadow 0.25s ease, border-color 0.25s ease, background 0.2s ease;
        }
        .btn-outline { background: var(--card2); }
        .btn-outline:hover { border-color: var(--accent); transform: translateY(-1px); }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: #fff; border: none;
            box-shadow: 0 10px 24px rgba(124,58,237,0.35);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 16px 34px rgba(124,58,237,0.45); }

        .empty-state {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow2);
            padding: 48px 24px;
            text-align: center;
        }
        .empty-icon {
            width: 80px; height: 80px; margin: 0 auto 16px;
            border-radius: 999px;
            background: linear-gradient(135deg, rgba(124,58,237,0.18), rgba(34,211,238,0.12));
            display: flex; align-items: center; justify-content: center;
        }
        .empty-icon svg { width: 38px; height: 38px; color: var(--accent); }
        .empty-title { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
        .empty-desc { font-size: 14px; color: var(--muted); }

        @media (prefers-reduced-motion: reduce) {
            .my-cell-card, .cell-card, .btn { transition: none !important; }
        }
    </style>
</head>
<body data-theme="dark">
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <!-- Tabs -->
    <nav class="feed-tabs">
        <a href="/homecells/" class="feed-tab active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            <span>Homecells</span>
        </a>
        <a href="/gospel_media/groups.php" class="feed-tab">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M2 12h20"></path><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
            <span>Groups</span>
        </a>
    </nav>

    <main class="feed-container">
        <div class="page-header">
            <h1 class="page-title">Homecells</h1>
            <p class="page-subtitle">Connect with believers in your area</p>
        </div>

        <?php if ($myHomecell): ?>
            <section class="section">
                <h2 class="section-title">My Homecell</h2>
                <a href="/homecells/view.php?id=<?= $myHomecell['id'] ?>" class="my-cell-card">
                    <div class="cell-icon">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="26" height="26">
                            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                            <polyline points="9 22 9 12 15 12 15 22"/>
                        </svg>
                    </div>
                    <div class="cell-info">
                        <div class="cell-name"><?= e($myHomecell['name']) ?></div>
                        <div class="cell-leader">Led by <?= e($myHomecell['leader_name']) ?></div>
                        <div class="cell-meta">
                            <span>
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="14" height="14"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                                <?= ucfirst($myHomecell['meeting_day']) ?>s at <?= date('g:i A', strtotime($myHomecell['meeting_time'])) ?>
                            </span>
                            <span>
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="14" height="14"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                                <?= $myHomecell['member_count'] ?> members
                            </span>
                        </div>
                    </div>
                    <div class="cell-arrow">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                    </div>
                </a>
            </section>
        <?php endif; ?>

        <section class="section">
            <h2 class="section-title">All Homecells</h2>
            <?php if ($homecells): ?>
                <div class="cells-grid">
                    <?php foreach ($homecells as $hc): ?>
                        <div class="cell-card">
                            <div class="card-header">
                                <h3 class="card-name"><?= e($hc['name']) ?></h3>
                                <?php if ($myHomecell && $myHomecell['id'] == $hc['id']): ?>
                                    <span class="my-badge">My Cell</span>
                                <?php endif; ?>
                            </div>

                            <?php if ($hc['description']): ?>
                                <p class="card-desc"><?= e(truncate($hc['description'], 100)) ?></p>
                            <?php endif; ?>

                            <div class="card-details">
                                <div class="detail-item">
                                    <div class="detail-icon">
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    </div>
                                    <span>Led by <?= e($hc['leader_name']) ?></span>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-icon">
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                                    </div>
                                    <span><?= ucfirst($hc['meeting_day']) ?>s at <?= date('g:i A', strtotime($hc['meeting_time'])) ?></span>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-icon">
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                    </div>
                                    <span><?= e($hc['location'] ?: 'Contact leader') ?></span>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-icon">
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                                    </div>
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
                    <div class="empty-icon">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                            <polyline points="9 22 9 12 15 12 15 22"/>
                        </svg>
                    </div>
                    <h3 class="empty-title">No homecells yet</h3>
                    <p class="empty-desc">Homecells haven't been set up for this congregation yet.</p>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="/home/" class="bottom-nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
            <span>Home</span>
        </a>
        <a href="/gospel_media/" class="bottom-nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 11a9 9 0 0 1 9 9"></path><path d="M4 4a16 16 0 0 1 16 16"></path><circle cx="5" cy="19" r="1"></circle></svg>
            <span>Feed</span>
        </a>
        <a href="/bible/" class="bottom-nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
            <span>Bible</span>
        </a>
        <a href="/homecells/" class="bottom-nav-item active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            <span>Homecells</span>
        </a>
        <a href="/profile/" class="bottom-nav-item">
            <?php if (!empty($user['avatar'])): ?>
                <img src="<?= e($user['avatar']) ?>" alt="" class="bottom-nav-avatar" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <div class="bottom-nav-avatar-placeholder" style="display:none;"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
            <?php else: ?>
                <div class="bottom-nav-avatar-placeholder"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
            <?php endif; ?>
            <span>Me</span>
        </a>
    </nav>

    <script src="/homecells/js/homecells.js"></script>
</body>
</html>
