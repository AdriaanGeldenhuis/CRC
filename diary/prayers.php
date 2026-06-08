<?php
/**
 * CRC Prayer Journal
 * Premium OAC-style dark theme
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$pageTitle = 'Prayer Journal - CRC';

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

$prayers = Database::fetchAll(
    "SELECT * FROM prayer_requests
     WHERE $whereClause
     ORDER BY is_pinned DESC, created_at DESC",
    $params
);

// Stats
$totalPrayers = Database::fetchColumn(
    "SELECT COUNT(*) FROM prayer_requests WHERE user_id = ?",
    [$user['id']]
);
$answeredCount = Database::fetchColumn(
    "SELECT COUNT(*) FROM prayer_requests WHERE user_id = ? AND answered_at IS NOT NULL",
    [$user['id']]
);
$activeCount = Database::fetchColumn(
    "SELECT COUNT(*) FROM prayer_requests WHERE user_id = ? AND answered_at IS NULL",
    [$user['id']]
);

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/diary/css/diary.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Parisienne&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
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
                        <div class="prayer-card <?= $prayer['answered_at'] ? 'answered' : '' ?> <?= $prayer['is_pinned'] ? 'pinned' : '' ?>">
                            <div class="prayer-header">
                                <span class="prayer-category">
                                    <?= getCategoryIcon($prayer['category'] ?? 'personal') ?>
                                    <?= ucfirst($prayer['category'] ?? 'personal') ?>
                                </span>
                                <div class="prayer-badges">
                                    <?php if ($prayer['is_pinned']): ?>
                                        <span class="prayer-badge pinned">
                                            <svg viewBox="0 0 24 24" fill="currentColor" stroke="none" width="14" height="14">
                                                <path d="M16 2L18 4L16.5 5.5L19 8L21 6L22 7L17 12L15.5 10.5L12 14V17L10 15L6 19L5 18L9 14L7 12H10L13.5 8.5L12 7L17 2L18 3L16 5L18.5 7.5L20 6L19 5L16 2Z"></path>
                                            </svg>
                                            Pinned
                                        </span>
                                    <?php endif; ?>
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
                            <p class="prayer-content"><?= nl2br(e(truncate($prayer['request'], 200))) ?></p>

                            <?php if (!empty($prayer['scripture_ref'])): ?>
                                <p class="prayer-scripture">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                        <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                    </svg>
                                    <?= e($prayer['scripture_ref']) ?>
                                </p>
                            <?php endif; ?>

                            <?php if ($prayer['answered_at'] && $prayer['testimony']): ?>
                                <div class="prayer-testimony">
                                    <div class="testimony-header">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                        </svg>
                                        <strong>Testimony</strong>
                                        <span class="answered-date">Answered <?= date('M j, Y', strtotime($prayer['answered_at'])) ?></span>
                                    </div>
                                    <p><?= nl2br(e($prayer['testimony'])) ?></p>
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
                                        <button onclick="togglePin(<?= $prayer['id'] ?>)" class="action-btn <?= $prayer['is_pinned'] ? 'active' : '' ?>" title="<?= $prayer['is_pinned'] ? 'Unpin' : 'Pin' ?>">
                                            <svg viewBox="0 0 24 24" fill="<?= $prayer['is_pinned'] ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2">
                                                <path d="M16 2L18 4L16.5 5.5L19 8L21 6L22 7L17 12L15.5 10.5L12 14V17L10 15L6 19L5 18L9 14L7 12H10L13.5 8.5L12 7L17 2L18 3L16 5L18.5 7.5L20 6L19 5L16 2Z"></path>
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
        </div>
    </main>

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

    <script src="/diary/js/diary.js"></script>
</body>
</html>
