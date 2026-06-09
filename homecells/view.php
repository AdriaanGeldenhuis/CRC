<?php
/**
 * CRC Homecells - Detail View
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
if (!$primaryCong) {
    Response::redirect('/onboarding/');
}
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

$pageTitle = $homecell['name'] . " - Homecells";

// Check membership
$membership = Database::fetchOne(
    "SELECT * FROM homecell_members WHERE homecell_id = ? AND user_id = ?",
    [$homecellId, $user['id']]
);
$isMember = $membership && $membership['status'] === 'active';
$isLeader = $homecell['leader_user_id'] == $user['id'];

// Get members
$members = Database::fetchAll(
    "SELECT hm.role, u.name, u.avatar, u.email
     FROM homecell_members hm
     JOIN users u ON hm.user_id = u.id
     WHERE hm.homecell_id = ? AND hm.status = 'active'
     ORDER BY hm.role DESC, u.name ASC",
    [$homecellId]
) ?: [];

// Get upcoming meetings
$meetings = Database::fetchAll(
    "SELECT * FROM homecell_meetings
     WHERE homecell_id = ? AND meeting_date >= CURDATE() AND status != 'cancelled'
     ORDER BY meeting_date ASC, meeting_time ASC
     LIMIT 5",
    [$homecellId]
) ?: [];

// Safely format a homecell's meeting schedule (day/time may be unset).
function homecellSchedule(?string $day, ?string $time): string {
    $dayPart = $day ? ucfirst($day) . 's' : '';
    $timePart = $time ? date('g:i A', strtotime($time)) : '';
    if ($dayPart && $timePart) return $dayPart . ' at ' . $timePart;
    return $dayPart ?: ($timePart ?: 'Schedule to be announced');
}

$roleLabels = ['leader' => 'Leader', 'assistant_leader' => 'Assistant', 'host' => 'Host'];
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
        /* ===== Homecell detail — home design components ===== */
        .back-link {
            display: inline-flex; align-items: center; gap: 8px;
            color: var(--muted); text-decoration: none; font-size: 13px; font-weight: 600;
            padding: 8px 12px; border: 1px solid var(--line); border-radius: 12px; background: var(--card2);
            margin: 6px 0 16px;
            transition: border-color 0.2s ease, transform 0.2s ease;
        }
        .back-link:hover { border-color: var(--accent); transform: translateY(-1px); }
        .back-link svg { width: 16px; height: 16px; }

        .hc-header {
            display: flex; align-items: flex-start; justify-content: space-between; gap: 16px;
            flex-wrap: wrap; margin-bottom: 18px;
        }
        .hc-header h1 { font-size: 24px; font-weight: 800; letter-spacing: -0.4px; }
        .hc-header .header-desc { font-size: 14px; color: var(--muted); margin-top: 6px; line-height: 1.5; max-width: 640px; }
        .header-actions { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .member-badge {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 12px; font-weight: 700; padding: 8px 14px; border-radius: 999px;
            color: var(--good); background: rgba(34,197,94,0.12); border: 1px solid rgba(34,197,94,0.3);
        }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 10px 16px; border-radius: 12px; font-size: 13px; font-weight: 600; font-family: inherit;
            text-decoration: none; cursor: pointer; border: 1px solid var(--line); color: var(--text);
            transition: transform 0.2s ease, box-shadow 0.25s ease, border-color 0.25s ease, background 0.2s ease;
        }
        .btn-outline { background: var(--card2); }
        .btn-outline:hover { border-color: var(--accent); transform: translateY(-1px); }
        .btn-danger { color: var(--bad); }
        .btn-danger:hover { border-color: var(--bad); background: rgba(239,68,68,0.1); }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: #fff; border: none; box-shadow: 0 10px 24px rgba(124,58,237,0.35);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 16px 34px rgba(124,58,237,0.45); }

        .hc-layout { display: grid; grid-template-columns: 1fr; gap: 16px; }
        @media (min-width: 860px) { .hc-layout { grid-template-columns: 1fr 300px; align-items: start; } }
        .hc-sidebar { display: flex; flex-direction: column; gap: 16px; }

        .info-card {
            background: var(--card); border: 1px solid var(--line); border-radius: var(--radius);
            box-shadow: var(--shadow2); padding: 18px; margin-bottom: 16px; position: relative;
        }
        .info-card::after {
            content: ""; position: absolute; top: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.40), transparent); pointer-events: none;
        }
        .info-card h2, .sidebar-card h3 { font-size: 15px; font-weight: 800; margin-bottom: 14px; letter-spacing: -0.2px; }

        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 14px; }
        .info-item { display: flex; flex-direction: column; gap: 3px; }
        .info-label { font-size: 11px; font-weight: 700; color: var(--muted2); text-transform: uppercase; letter-spacing: 0.5px; }
        .info-value { font-size: 14px; font-weight: 600; color: var(--text); }

        .meetings-list { display: flex; flex-direction: column; gap: 10px; }
        .meeting-item { display: flex; align-items: center; gap: 14px; padding: 10px; border: 1px solid var(--line); border-radius: 14px; background: var(--card2); }
        .meeting-date {
            width: 50px; height: 50px; flex-shrink: 0; border-radius: 12px;
            background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #fff;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            box-shadow: 0 8px 18px rgba(124,58,237,0.3);
        }
        .meeting-date .day { font-size: 18px; font-weight: 800; line-height: 1; }
        .meeting-date .month { font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .meeting-info h4 { font-size: 14px; font-weight: 700; }
        .meeting-info p { font-size: 12px; color: var(--muted); margin-top: 2px; }

        .members-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
        .member-card { display: flex; align-items: center; gap: 10px; padding: 10px; border: 1px solid var(--line); border-radius: 14px; background: var(--card2); }
        .member-avatar {
            width: 40px; height: 40px; flex-shrink: 0; border-radius: 999px; object-fit: cover;
            border: 2px solid var(--line);
        }
        .member-avatar.placeholder {
            display: flex; align-items: center; justify-content: center; border: none;
            background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #fff; font-weight: 700; font-size: 15px;
            box-shadow: 0 6px 16px rgba(124,58,237,0.3), inset 0 1px 2px rgba(255,255,255,0.4);
        }
        .member-info { min-width: 0; }
        .member-info h4 { font-size: 13px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .role-badge { font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 999px; display: inline-block; margin-top: 3px; }
        .role-badge.leader { color: var(--accent); background: rgba(124,58,237,0.15); }
        .role-badge.assistant_leader, .role-badge.host { color: var(--accent2); background: rgba(34,211,238,0.14); }

        .sidebar-card {
            background: var(--card); border: 1px solid var(--line); border-radius: var(--radius);
            box-shadow: var(--shadow2); padding: 18px; position: relative;
        }
        .leader-info { display: flex; align-items: center; gap: 12px; }
        .leader-avatar {
            width: 46px; height: 46px; flex-shrink: 0; border-radius: 999px;
            background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #fff;
            display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 18px;
            box-shadow: 0 8px 18px rgba(124,58,237,0.3);
        }
        .leader-info h4 { font-size: 14px; font-weight: 700; }
        .leader-email { font-size: 12px; color: var(--accent); text-decoration: none; word-break: break-all; }
        .leader-email:hover { text-decoration: underline; }

        .stats-list { display: flex; gap: 12px; }
        .stat-item {
            flex: 1; text-align: center; padding: 14px 8px; border: 1px solid var(--line);
            border-radius: 14px; background: var(--card2);
        }
        .stat-value { display: block; font-size: 20px; font-weight: 800; }
        .stat-label { display: block; font-size: 11px; color: var(--muted); margin-top: 2px; }

        @media (prefers-reduced-motion: reduce) { .back-link, .btn { transition: none !important; } }
    </style>
</head>
<body data-theme="dark">
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="feed-container">
        <a href="/homecells/" class="back-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
            Back to Homecells
        </a>

        <!-- Homecell Header -->
        <div class="hc-header">
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
                <?php elseif ($homecell['is_accepting_members']): ?>
                    <button onclick="joinHomecell(<?= $homecellId ?>)" class="btn btn-primary">Join Homecell</button>
                <?php else: ?>
                    <span class="member-badge" style="color:var(--muted);background:var(--card2);border-color:var(--line);">Not accepting members</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="hc-layout">
            <!-- Main Content -->
            <div class="hc-main">
                <!-- Meeting Info -->
                <div class="info-card">
                    <h2>Meeting Information</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Day &amp; Time</span>
                            <span class="info-value"><?= e(homecellSchedule($homecell['meeting_day'], $homecell['meeting_time'])) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Frequency</span>
                            <span class="info-value"><?= ucfirst($homecell['meeting_frequency'] ?? 'weekly') ?></span>
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
                                        <p>
                                            <?= date('l', strtotime($meeting['meeting_date'])) ?>
                                            <?php if ($meeting['meeting_time']): ?> at <?= date('g:i A', strtotime($meeting['meeting_time'])) ?><?php endif; ?>
                                        </p>
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
                                    <?php if (!empty($member['avatar'])): ?>
                                        <img src="<?= e($member['avatar']) ?>" alt="" class="member-avatar" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                        <div class="member-avatar placeholder" style="display:none;"><?= strtoupper(substr($member['name'], 0, 1)) ?></div>
                                    <?php else: ?>
                                        <div class="member-avatar placeholder"><?= strtoupper(substr($member['name'], 0, 1)) ?></div>
                                    <?php endif; ?>
                                    <div class="member-info">
                                        <h4><?= e($member['name']) ?></h4>
                                        <?php if (isset($roleLabels[$member['role']])): ?>
                                            <span class="role-badge <?= e($member['role']) ?>"><?= $roleLabels[$member['role']] ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <aside class="hc-sidebar">
                <!-- Leader Contact -->
                <div class="sidebar-card">
                    <h3>Contact Leader</h3>
                    <div class="leader-info">
                        <div class="leader-avatar"><?= strtoupper(substr($homecell['leader_name'] ?? '?', 0, 1)) ?></div>
                        <div>
                            <h4><?= e($homecell['leader_name']) ?></h4>
                            <?php if ($isMember && $homecell['leader_email']): ?>
                                <a href="mailto:<?= e($homecell['leader_email']) ?>" class="leader-email"><?= e($homecell['leader_email']) ?></a>
                            <?php elseif (!$isMember): ?>
                                <span style="font-size:12px;color:var(--muted);">Join to see contact details</span>
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
                            <span class="stat-value"><?= ucfirst($homecell['meeting_frequency'] ?? 'Weekly') ?></span>
                            <span class="stat-label">Meetings</span>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
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

    <script>
        const homecellId = <?= $homecellId ?>;
    </script>
    <script src="/homecells/js/homecells.js"></script>
</body>
</html>
