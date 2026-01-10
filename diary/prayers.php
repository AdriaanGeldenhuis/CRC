<?php
/**
 * CRC Prayer Journal
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

$categories = ['personal', 'family', 'health', 'work', 'relationships', 'spiritual', 'financial', 'world', 'other'];
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="diary-header">
                <div class="diary-title">
                    <h1>Prayer Journal</h1>
                    <p>Keep track of your prayers and testimonies</p>
                </div>
                <div class="diary-actions">
                    <a href="/diary/" class="btn btn-outline">Back to Diary</a>
                    <button type="button" class="btn btn-primary" onclick="openPrayerModal()">+ Add Prayer</button>
                </div>
            </div>

            <div class="diary-stats">
                <div class="stat-card">
                    <span class="stat-value"><?= number_format($totalPrayers) ?></span>
                    <span class="stat-label">Total Prayers</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?= number_format($answeredCount) ?></span>
                    <span class="stat-label">Answered</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?= $totalPrayers > 0 ? round(($answeredCount / $totalPrayers) * 100) : 0 ?>%</span>
                    <span class="stat-label">Answer Rate</span>
                </div>
            </div>

            <!-- Filters -->
            <div class="prayer-filters">
                <div class="filter-tabs">
                    <a href="?status=active" class="filter-tab <?= $status === 'active' ? 'active' : '' ?>">Active</a>
                    <a href="?status=answered" class="filter-tab <?= $status === 'answered' ? 'active' : '' ?>">Answered</a>
                    <a href="?status=all" class="filter-tab <?= $status === 'all' ? 'active' : '' ?>">All</a>
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
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                        </svg>
                        <h3>No prayer requests</h3>
                        <p>Start your prayer journal by adding your first request</p>
                        <button type="button" class="btn btn-primary" onclick="openPrayerModal()">Add Your First Prayer</button>
                    </div>
                <?php else: ?>
                    <?php foreach ($prayers as $prayer): ?>
                        <div class="prayer-card <?= $prayer['answered_at'] ? 'answered' : '' ?> <?= $prayer['is_pinned'] ? 'pinned' : '' ?>">
                            <div class="prayer-header">
                                <span class="prayer-category"><?= ucfirst($prayer['category'] ?? 'general') ?></span>
                                <?php if ($prayer['is_pinned']): ?>
                                    <span class="prayer-pinned">📌 Pinned</span>
                                <?php endif; ?>
                                <?php if ($prayer['answered_at']): ?>
                                    <span class="prayer-answered">✓ Answered</span>
                                <?php endif; ?>
                            </div>

                            <h3 class="prayer-title"><?= e($prayer['title']) ?></h3>
                            <p class="prayer-content"><?= nl2br(e(truncate($prayer['request'], 200))) ?></p>

                            <?php if (!empty($prayer['scripture_ref'])): ?>
                                <p class="prayer-scripture">📖 <?= e($prayer['scripture_ref']) ?></p>
                            <?php endif; ?>

                            <?php if ($prayer['answered_at'] && $prayer['testimony']): ?>
                                <div class="prayer-testimony">
                                    <strong>Testimony:</strong>
                                    <p><?= nl2br(e($prayer['testimony'])) ?></p>
                                </div>
                            <?php endif; ?>

                            <div class="prayer-footer">
                                <span class="prayer-date"><?= date('M j, Y', strtotime($prayer['created_at'])) ?></span>
                                <div class="prayer-actions-menu">
                                    <?php if (!$prayer['answered_at']): ?>
                                        <button onclick="markAnswered(<?= $prayer['id'] ?>)" title="Mark as Answered">✓</button>
                                        <button onclick="togglePin(<?= $prayer['id'] ?>)" title="<?= $prayer['is_pinned'] ? 'Unpin' : 'Pin' ?>">📌</button>
                                    <?php endif; ?>
                                    <button onclick="editPrayer(<?= $prayer['id'] ?>)" title="Edit">✏️</button>
                                    <button onclick="deletePrayer(<?= $prayer['id'] ?>)" title="Delete">🗑️</button>
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
                <h2 id="modal-title">Add Prayer Request</h2>
                <button type="button" class="modal-close" onclick="closePrayerModal()">&times;</button>
            </div>
            <form id="prayer-form">
                <input type="hidden" id="prayer-id" name="id">

                <div class="form-group">
                    <label for="prayer-title">Title *</label>
                    <input type="text" id="prayer-title" name="title" required maxlength="200">
                </div>

                <div class="form-group">
                    <label for="prayer-request">Prayer Request *</label>
                    <textarea id="prayer-request" name="request" rows="4" required></textarea>
                </div>

                <div class="form-group">
                    <label for="prayer-category">Category</label>
                    <select id="prayer-category" name="category">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat ?>"><?= ucfirst($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="prayer-scripture">Scripture Reference</label>
                    <input type="text" id="prayer-scripture" name="scripture_ref" placeholder="e.g., Philippians 4:6">
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closePrayerModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Prayer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Answered Modal -->
    <div id="answered-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Mark as Answered</h2>
                <button type="button" class="modal-close" onclick="closeAnsweredModal()">&times;</button>
            </div>
            <form id="answered-form">
                <input type="hidden" id="answered-prayer-id" name="prayer_id">

                <div class="form-group">
                    <label for="testimony">Testimony (How was your prayer answered?)</label>
                    <textarea id="testimony" name="testimony" rows="4" placeholder="Share your testimony..."></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeAnsweredModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Mark as Answered</button>
                </div>
            </form>
        </div>
    </div>

    <script src="/diary/js/diary.js"></script>
</body>
</html>
