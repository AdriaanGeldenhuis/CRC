<?php
/**
 * CRC Prayer Journal
 * Premium OAC-style dark theme
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
$pageTitle = 'Prayer Journal - CRC';

$unreadNotifications = 0;
try {
    $unreadNotifications = Database::fetchColumn(
        "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL",
        [$user['id']]
    ) ?: 0;
} catch (Exception $e) {}

// Filters
$status = input('status', 'active');
$category = input('category');

// Get prayer requests
$where = ['user_id = ?'];
$params = [$user['id']];

if ($status === 'answered') {
    $where[] = "answered_at IS NOT NULL";
} elseif ($status === 'active') {
    $where[] = "answered_at IS NULL";
}

if ($category) {
    $where[] = "category = ?";
    $params[] = $category;
}

$whereClause = implode(' AND ', $where);

// Defaults so the page never crashes on a schema/availability issue
$prayers = [];
$totalPrayers = 0;
$answeredCount = 0;
$activeCount = 0;

try {
    $prayers = Database::fetchAll(
        "SELECT * FROM prayer_requests
         WHERE $whereClause
         ORDER BY created_at DESC",
        $params
    ) ?: [];

    $totalPrayers = (int) Database::fetchColumn(
        "SELECT COUNT(*) FROM prayer_requests WHERE user_id = ?",
        [$user['id']]
    );
    $answeredCount = (int) Database::fetchColumn(
        "SELECT COUNT(*) FROM prayer_requests WHERE user_id = ? AND answered_at IS NOT NULL",
        [$user['id']]
    );
    $activeCount = (int) Database::fetchColumn(
        "SELECT COUNT(*) FROM prayer_requests WHERE user_id = ? AND answered_at IS NULL",
        [$user['id']]
    );
} catch (Exception $e) {
    // Leave defaults; render an empty state instead of a 500 error
}

$categories = ['personal', 'family', 'health', 'work', 'relationships', 'spiritual', 'financial', 'world', 'other'];

function getCategoryIcon($cat) {
    $icons = [
        'personal' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>',
        'family' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
        'health' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>',
        'work' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>',
        'relationships' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>',
        'spiritual' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>',
        'financial' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>',
        'world' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>',
        'other' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>'
    ];
    return $icons[$cat] ?? $icons['other'];
}
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
        /* ===== Prayer Journal — aligned with app (home) design system ===== */
        .feed-container { padding-bottom: 100px; }

        /* Header */
        .diary-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .diary-title h1,
        .diary-title .display-title {
            font-family: Inter, system-ui, -apple-system, sans-serif;
            font-size: 1.75rem;
            font-weight: 800;
            background: linear-gradient(135deg, #7C3AED, #22D3EE);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }
        .diary-title p,
        .diary-title .subtitle {
            color: var(--muted);
            font-size: 0.9rem;
            margin: 0.25rem 0 0;
        }
        .diary-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            font-family: inherit;
        }
        .btn-outline { background: var(--card); border: 1px solid var(--line); color: var(--text); }
        .btn-outline:hover { background: var(--card2); border-color: var(--accent); }
        .btn-secondary { background: var(--card); border: 1px solid var(--line); color: var(--text); }
        .btn-secondary:hover { background: var(--card2); }
        .btn-primary {
            background: linear-gradient(135deg, #7C3AED, #22D3EE);
            color: #fff;
            box-shadow: 0 4px 15px rgba(124, 58, 237, 0.3);
            transition: transform 0.25s cubic-bezier(0.2, 0.8, 0.25, 1.4), box-shadow 0.25s ease;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(124, 58, 237, 0.4); }
        .btn-primary:active { transform: translateY(0) scale(0.98); }
        .btn-success, .btn-primary.btn-success {
            background: linear-gradient(135deg, #10B981, #22D3EE);
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        .btn-success:hover, .btn-primary.btn-success:hover { box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4); }

        /* Stats */
        .diary-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            padding: 0 1rem 1rem;
        }
        .stat-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 1.25rem;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform 0.35s cubic-bezier(0.2, 0.8, 0.25, 1.2), box-shadow 0.35s ease, border-color 0.35s ease;
        }
        .stat-card::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, #7C3AED, #22D3EE);
        }
        .stat-card:hover {
            transform: translateY(-4px);
            border-color: rgba(124, 58, 237, 0.40);
            box-shadow: 0 26px 54px rgba(0, 0, 0, 0.50), 0 0 28px rgba(124, 58, 237, 0.16);
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: rgba(124, 58, 237, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.75rem;
        }
        .stat-icon svg { width: 24px; height: 24px; color: #7C3AED; }
        .stat-icon.accent { background: rgba(34, 211, 238, 0.15); }
        .stat-icon.accent svg { color: #22D3EE; }
        .stat-icon.success { background: rgba(16, 185, 129, 0.15); }
        .stat-icon.success svg { color: #10B981; }
        .stat-icon.warning { background: rgba(245, 158, 11, 0.15); }
        .stat-icon.warning svg { color: #F59E0B; }
        .stat-value {
            display: block;
            font-size: 1.75rem;
            font-weight: 800;
            background: linear-gradient(135deg, #7C3AED, #22D3EE);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            filter: drop-shadow(0 2px 8px rgba(124, 58, 237, 0.35));
        }
        .stat-label { display: block; font-size: 0.8rem; color: var(--muted); margin-top: 0.25rem; }

        /* Filters */
        .prayer-filters {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 0 1rem 1rem;
            flex-wrap: wrap;
        }
        .filter-tabs { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .filter-tab {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.6rem 1rem;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 20px;
            color: var(--muted);
            font-size: 0.85rem;
            font-weight: 500;
            text-decoration: none;
            white-space: nowrap;
            transition: transform 0.2s ease, background 0.2s ease, color 0.2s ease;
        }
        .filter-tab:hover { background: var(--card2); color: var(--text); transform: translateY(-1px); }
        .filter-tab.active {
            color: #fff;
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.85), rgba(34, 211, 238, 0.75));
            border-color: rgba(34, 211, 238, 0.30);
            box-shadow: 0 8px 18px rgba(124, 58, 237, 0.28);
        }
        .filter-categories select {
            padding: 0.6rem 2.25rem 0.6rem 1rem;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 12px;
            color: var(--text);
            font-size: 0.85rem;
            font-family: inherit;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.85rem center;
        }
        .filter-categories select:focus { outline: none; border-color: var(--accent); }

        /* Prayer list */
        .prayer-list { display: grid; gap: 1rem; padding: 0 1rem; }
        .prayer-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 1.25rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform 0.35s cubic-bezier(0.2, 0.8, 0.25, 1.2), box-shadow 0.35s ease, border-color 0.35s ease;
        }
        .prayer-card:hover {
            transform: translateY(-4px);
            border-color: rgba(124, 58, 237, 0.40);
            box-shadow: 0 26px 54px rgba(0, 0, 0, 0.50), 0 0 28px rgba(124, 58, 237, 0.16);
        }
        .prayer-card.answered { border-color: rgba(16, 185, 129, 0.35); }
        .prayer-card.answered::before { background: linear-gradient(90deg, #10B981, #22D3EE); }
        .prayer-card::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, #7C3AED, #22D3EE);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .prayer-card:hover::before, .prayer-card.answered::before { opacity: 1; }
        .prayer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        .prayer-category {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.75rem;
            background: rgba(124, 58, 237, 0.15);
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text);
            text-transform: capitalize;
        }
        .prayer-category svg { width: 14px; height: 14px; color: #7C3AED; }
        .prayer-badges { display: flex; gap: 0.5rem; }
        .prayer-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.3rem 0.65rem;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 600;
        }
        .prayer-badge.answered { background: rgba(16, 185, 129, 0.15); color: #10B981; }
        .prayer-title { margin: 0 0 0.5rem; font-size: 1.05rem; font-weight: 700; color: var(--text); }
        .prayer-content { margin: 0 0 0.75rem; font-size: 0.9rem; color: var(--muted); line-height: 1.6; }
        .prayer-scripture {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.8rem;
            color: #22D3EE;
            margin: 0 0 0.75rem;
            font-style: italic;
        }
        .prayer-scripture svg { width: 16px; height: 16px; }
        .prayer-testimony {
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: 12px;
            padding: 0.85rem 1rem;
            margin: 0 0 0.75rem;
        }
        .testimony-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.4rem;
            color: #10B981;
            font-size: 0.8rem;
        }
        .testimony-header svg { width: 16px; height: 16px; }
        .testimony-header .answered-date { margin-left: auto; color: var(--muted); font-weight: 500; font-size: 0.72rem; }
        .prayer-testimony p { margin: 0; font-size: 0.85rem; color: var(--text); line-height: 1.5; }
        .prayer-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid var(--line);
        }
        .prayer-date { display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.78rem; color: var(--muted); }
        .prayer-date svg { color: var(--muted); }
        .prayer-actions-menu { display: flex; gap: 0.4rem; }
        .action-btn {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 10px;
            color: var(--muted);
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .action-btn svg { width: 16px; height: 16px; }
        .action-btn:hover { background: var(--card2); color: var(--text); transform: translateY(-1px); }
        .action-btn.success:hover { color: #10B981; border-color: rgba(16, 185, 129, 0.4); }
        .action-btn.danger:hover { color: #EF4444; border-color: rgba(239, 68, 68, 0.4); }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            box-shadow: var(--shadow);
        }
        .empty-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.15), rgba(34, 211, 238, 0.1));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .empty-icon svg { width: 40px; height: 40px; color: #7C3AED; }
        .empty-state h3 { margin: 0 0 0.5rem; font-size: 1.25rem; color: var(--text); }
        .empty-state p { margin: 0 0 1.5rem; color: var(--muted); }

        /* Modal */
        .modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            padding: 1rem;
        }
        .modal.show { display: flex; }
        .modal-content {
            background: var(--bg1);
            border: 1px solid var(--line);
            border-radius: 18px;
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 520px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--line);
        }
        .modal-header h2,
        .modal-header .display-title {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text);
            background: none;
            -webkit-text-fill-color: currentColor;
            font-family: inherit;
        }
        .modal-close {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 10px;
            color: var(--muted);
            cursor: pointer;
            transition: all 0.12s ease;
        }
        .modal-close svg { width: 18px; height: 18px; }
        .modal-close:hover { background: var(--card2); color: var(--text); }
        #prayer-form, #answered-form { padding: 1.5rem; }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--muted);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-group label svg { width: 16px; height: 16px; color: var(--accent); }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.85rem 1rem;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: var(--card);
            color: var(--text);
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.12s ease;
        }
        .form-group textarea { resize: vertical; min-height: 110px; }
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus { outline: none; border-color: var(--accent); background: var(--card2); }
        .form-group input::placeholder,
        .form-group textarea::placeholder { color: var(--muted2); }
        .form-group select {
            appearance: none;
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            padding-right: 2.5rem;
        }
        .form-actions { display: flex; gap: 0.75rem; justify-content: flex-end; padding-top: 0.5rem; }

        /* Responsive */
        @media (max-width: 640px) {
            .diary-header { flex-direction: column; align-items: flex-start; }
            .diary-stats { grid-template-columns: repeat(2, 1fr); }
            .prayer-filters { flex-direction: column; align-items: stretch; }
            .filter-categories select { width: 100%; }
        }
        @media (prefers-reduced-motion: reduce) {
            .stat-card, .prayer-card, .btn-primary, .filter-tab, .action-btn { transition: none !important; }
        }
    </style>
</head>
<body data-theme="dark">
    <!-- Top Bar / Navigation -->
    <div class="topbar">
        <div class="inner">
            <a href="/home/" class="brand">
                <div class="logo" aria-hidden="true"></div>
                <div>
                    <h1>CRC App</h1>
                    <span><?= e($primaryCong['name'] ?? 'My Diary') ?></span>
                </div>
            </a>

            <div class="actions">
                <div class="chip" title="Status">
                    <span class="dot"></span>
                    <?= e(explode(' ', $user['name'])[0]) ?>
                </div>

                <button class="theme-toggle" onclick="toggleTheme()" title="Toggle theme" data-ripple>
                    <svg class="sun-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 3v2m0 14v2M3 12h2m14 0h2M5.2 5.2l1.4 1.4m10.8 10.8l1.4 1.4M18.8 5.2l-1.4 1.4M6.6 17.4l-1.4 1.4"></path>
                        <circle cx="12" cy="12" r="5"></circle>
                    </svg>
                    <svg class="moon-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                    </svg>
                </button>

                <a href="/notifications/" class="nav-icon-btn" title="Notifications" data-ripple>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="notification-badge"><?= $unreadNotifications > 9 ? '9+' : $unreadNotifications ?></span>
                    <?php endif; ?>
                </a>

                <div class="more-menu">
                    <button class="more-menu-btn" onclick="toggleMoreMenu()" title="More" data-ripple>
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                            <circle cx="12" cy="5" r="2"></circle>
                            <circle cx="12" cy="12" r="2"></circle>
                            <circle cx="12" cy="19" r="2"></circle>
                        </svg>
                    </button>
                    <div class="more-dropdown" id="moreDropdown">
                        <a href="/home/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                            Home
                        </a>
                        <a href="/gospel_media/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 11a9 9 0 0 1 9 9"></path><path d="M4 4a16 16 0 0 1 16 16"></path><circle cx="5" cy="19" r="1"></circle></svg>
                            Feed
                        </a>
                        <a href="/bible/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path><path d="M12 6v7"></path><path d="M8 9h8"></path></svg>
                            Bible
                        </a>
                        <a href="/ai_smartbible/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path><circle cx="19" cy="5" r="3" fill="currentColor"></circle></svg>
                            AI SmartBible
                        </a>
                        <a href="/morning_watch/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line></svg>
                            Morning Study
                        </a>
                        <a href="/calendar/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                            Calendar
                        </a>
                        <a href="/media/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>
                            Media
                        </a>
                        <div class="more-dropdown-divider"></div>
                        <a href="/diary/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                            My Diary
                        </a>
                        <a href="/homecells/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            Homecells
                        </a>
                        <a href="/learning/" class="more-dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>
                            Courses
                        </a>
                    </div>
                </div>

                <div class="user-menu">
                    <button class="user-menu-btn" onclick="toggleUserMenu()">
                        <?php if ($user['avatar']): ?>
                            <img src="<?= e($user['avatar']) ?>" alt="" class="user-avatar" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                            <div class="user-avatar-placeholder" style="display:none;"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
                        <?php else: ?>
                            <div class="user-avatar-placeholder"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
                        <?php endif; ?>
                    </button>
                    <div class="user-dropdown" id="userDropdown">
                        <div class="user-dropdown-header">
                            <strong><?= e($user['name']) ?></strong>
                            <span><?= e($primaryCong['name'] ?? '') ?></span>
                        </div>
                        <div class="user-dropdown-divider"></div>
                        <a href="/profile/" class="user-dropdown-item">Profile</a>
                        <?php if ($primaryCong && Auth::isCongregationAdmin($primaryCong['id'])): ?>
                            <div class="user-dropdown-divider"></div>
                            <a href="/admin_congregation/" class="user-dropdown-item">Manage Congregation</a>
                        <?php endif; ?>
                        <?php if (Auth::isAdmin()): ?>
                            <a href="/admin/" class="user-dropdown-item">Admin Panel</a>
                        <?php endif; ?>
                        <div class="user-dropdown-divider"></div>
                        <a href="/auth/logout.php" class="user-dropdown-item logout">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Diary Tabs -->
    <nav class="feed-tabs">
        <a href="/diary/" class="feed-tab">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
            </svg>
            <span>Entries</span>
        </a>
        <a href="/diary/prayers.php" class="feed-tab active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
            </svg>
            <span>Prayers</span>
        </a>
    </nav>

    <main class="feed-container">
            <!-- Header -->
            <div class="diary-header">
                <div class="diary-title">
                    <h1 class="display-title">Prayer Journal</h1>
                    <p class="subtitle">Keep track of your prayers, testimonies, and answered prayers</p>
                </div>
                <div class="diary-actions">
                    <a href="/diary/" class="btn btn-outline">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                        Back to Diary
                    </a>
                    <button type="button" class="btn btn-primary" onclick="openPrayerModal()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Add Prayer
                    </button>
                </div>
            </div>

            <!-- Stats -->
            <div class="diary-stats">
                <div class="stat-card">
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                        </svg>
                    </div>
                    <span class="stat-value"><?= number_format($totalPrayers) ?></span>
                    <span class="stat-label">Total Prayers</span>
                </div>
                <div class="stat-card">
                    <div class="stat-icon accent">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </div>
                    <span class="stat-value"><?= number_format($activeCount) ?></span>
                    <span class="stat-label">Active</span>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </div>
                    <span class="stat-value"><?= number_format($answeredCount) ?></span>
                    <span class="stat-label">Answered</span>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </div>
                    <span class="stat-value"><?= $totalPrayers > 0 ? round(($answeredCount / $totalPrayers) * 100) : 0 ?>%</span>
                    <span class="stat-label">Answer Rate</span>
                </div>
            </div>

            <!-- Filters -->
            <div class="prayer-filters">
                <div class="filter-tabs">
                    <a href="?status=active" class="filter-tab <?= $status === 'active' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        Active
                    </a>
                    <a href="?status=answered" class="filter-tab <?= $status === 'answered' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        Answered
                    </a>
                    <a href="?status=all" class="filter-tab <?= $status === 'all' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <rect x="3" y="3" width="7" height="7"></rect>
                            <rect x="14" y="3" width="7" height="7"></rect>
                            <rect x="14" y="14" width="7" height="7"></rect>
                            <rect x="3" y="14" width="7" height="7"></rect>
                        </svg>
                        All
                    </a>
                </div>
                <div class="filter-categories">
                    <select onchange="window.location.href='?status=<?= $status ?>&category='+this.value">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat ?>" <?= $category === $cat ? 'selected' : '' ?>>
                                <?= ucfirst($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Prayer List -->
            <div class="prayer-list">
                <?php if (empty($prayers)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                            </svg>
                        </div>
                        <h3>No prayer requests</h3>
                        <p>Start your prayer journal by adding your first request</p>
                        <button type="button" class="btn btn-primary" onclick="openPrayerModal()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Add Your First Prayer
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($prayers as $prayer): ?>
                        <div class="prayer-card <?= $prayer['answered_at'] ? 'answered' : '' ?>">
                            <div class="prayer-header">
                                <span class="prayer-category">
                                    <?= getCategoryIcon($prayer['category'] ?? 'personal') ?>
                                    <?= ucfirst($prayer['category'] ?? 'personal') ?>
                                </span>
                                <div class="prayer-badges">
                                    <?php if ($prayer['answered_at']): ?>
                                        <span class="prayer-badge answered">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                                <polyline points="20 6 9 17 4 12"></polyline>
                                            </svg>
                                            Answered
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <h3 class="prayer-title"><?= e($prayer['title']) ?></h3>
                            <p class="prayer-content"><?= nl2br(e(truncate($prayer['description'] ?? '', 200))) ?></p>

                            <?php if (!empty($prayer['scripture_ref'])): ?>
                                <p class="prayer-scripture">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                        <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                    </svg>
                                    <?= e($prayer['scripture_ref']) ?>
                                </p>
                            <?php endif; ?>

                            <?php if ($prayer['answered_at'] && !empty($prayer['answered_notes'])): ?>
                                <div class="prayer-testimony">
                                    <div class="testimony-header">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                        </svg>
                                        <strong>Testimony</strong>
                                        <span class="answered-date">Answered <?= date('M j, Y', strtotime($prayer['answered_at'])) ?></span>
                                    </div>
                                    <p><?= nl2br(e($prayer['answered_notes'] ?? '')) ?></p>
                                </div>
                            <?php endif; ?>

                            <div class="prayer-footer">
                                <span class="prayer-date">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                        <line x1="16" y1="2" x2="16" y2="6"></line>
                                        <line x1="8" y1="2" x2="8" y2="6"></line>
                                        <line x1="3" y1="10" x2="21" y2="10"></line>
                                    </svg>
                                    <?= date('M j, Y', strtotime($prayer['created_at'])) ?>
                                </span>
                                <div class="prayer-actions-menu">
                                    <?php if (!$prayer['answered_at']): ?>
                                        <button onclick="markAnswered(<?= $prayer['id'] ?>)" class="action-btn success" title="Mark as Answered">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="20 6 9 17 4 12"></polyline>
                                            </svg>
                                        </button>
                                    <?php endif; ?>
                                    <button onclick="editPrayer(<?= $prayer['id'] ?>)" class="action-btn" title="Edit">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                    </button>
                                    <button onclick="deletePrayer(<?= $prayer['id'] ?>)" class="action-btn danger" title="Delete">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3 6 5 6 21 6"></polyline>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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
        <a href="#" class="bottom-nav-item create-btn" onclick="openPrayerModal(); return false;" title="Add Prayer">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        </a>
        <a href="/diary/" class="bottom-nav-item active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
            <span>Diary</span>
        </a>
        <a href="/profile/" class="bottom-nav-item">
            <?php if ($user['avatar']): ?>
                <img src="<?= e($user['avatar']) ?>" alt="" class="bottom-nav-avatar" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <div class="bottom-nav-avatar-placeholder" style="display:none;"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
            <?php else: ?>
                <div class="bottom-nav-avatar-placeholder"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
            <?php endif; ?>
            <span>Me</span>
        </a>
    </nav>

    <!-- Prayer Modal -->
    <div id="prayer-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title" class="display-title">Add Prayer Request</h2>
                <button type="button" class="modal-close" onclick="closePrayerModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <form id="prayer-form">
                <input type="hidden" id="prayer-id" name="id">

                <div class="form-group">
                    <label for="prayer-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                        Title *
                    </label>
                    <input type="text" id="prayer-title" name="title" required maxlength="200" placeholder="Give your prayer a title">
                </div>

                <div class="form-group">
                    <label for="prayer-request">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                        </svg>
                        Prayer Request *
                    </label>
                    <textarea id="prayer-request" name="request" rows="4" required placeholder="Write your prayer request..."></textarea>
                </div>

                <div class="form-group">
                    <label for="prayer-category">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                            <line x1="7" y1="7" x2="7.01" y2="7"></line>
                        </svg>
                        Category
                    </label>
                    <select id="prayer-category" name="category">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat ?>"><?= ucfirst($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="prayer-scripture">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        Scripture Reference
                    </label>
                    <input type="text" id="prayer-scripture" name="scripture_ref" placeholder="e.g., Philippians 4:6">
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closePrayerModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                            <polyline points="7 3 7 8 15 8"></polyline>
                        </svg>
                        Save Prayer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Answered Modal -->
    <div id="answered-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="display-title">Mark as Answered</h2>
                <button type="button" class="modal-close" onclick="closeAnsweredModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <form id="answered-form">
                <input type="hidden" id="answered-prayer-id" name="prayer_id">

                <div class="form-group">
                    <label for="testimony">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                        Testimony (How was your prayer answered?)
                    </label>
                    <textarea id="testimony" name="testimony" rows="4" placeholder="Share your testimony and how God answered your prayer..."></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeAnsweredModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-success">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        Mark as Answered
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Theme Toggle
        function toggleTheme() {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme') || 'dark';
            const next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            document.body.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
        }
        function toggleMoreMenu() {
            document.getElementById('moreDropdown').classList.toggle('show');
            document.getElementById('userDropdown')?.classList.remove('show');
        }
        function toggleUserMenu() {
            document.getElementById('userDropdown').classList.toggle('show');
            document.getElementById('moreDropdown')?.classList.remove('show');
        }
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.user-menu')) {
                document.getElementById('userDropdown')?.classList.remove('show');
            }
            if (!e.target.closest('.more-menu')) {
                document.getElementById('moreDropdown')?.classList.remove('show');
            }
        });
    </script>
    <script src="/diary/js/diary.js"></script>
</body>
</html>
