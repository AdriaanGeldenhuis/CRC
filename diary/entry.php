<?php
/**
 * CRC Diary - Entry View/Edit
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$entryId = (int)($_GET['id'] ?? 0);
$entry = null;
$entryTags = [];

if ($entryId) {
    $entry = Database::fetchOne(
        "SELECT * FROM diary_entries WHERE id = ? AND user_id = ?",
        [$entryId, $user['id']]
    );

    if (!$entry) {
        Response::redirect('/diary/');
    }

    // Get tags for this entry
    $entryTags = Database::fetchAll(
        "SELECT t.* FROM diary_tags t
         JOIN diary_entry_tags det ON t.id = det.tag_id
         WHERE det.entry_id = ?",
        [$entryId]
    );

    $pageTitle = ($entry['title'] ?: 'Entry') . ' - My Diary';
} else {
    $pageTitle = 'New Entry - My Diary';
}

// Get all user's tags for autocomplete
$allTags = Database::fetchAll(
    "SELECT * FROM diary_tags WHERE user_id = ? ORDER BY name ASC",
    [$user['id']]
);

$moods = ['grateful', 'joyful', 'peaceful', 'hopeful', 'anxious', 'sad', 'angry', 'confused'];
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
            <div class="entry-page">
                <div class="entry-header">
                    <a href="/diary/" class="back-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                        Back to Diary
                    </a>

                    <?php if ($entry): ?>
                        <div class="entry-actions">
                            <button type="button" class="btn btn-danger" onclick="deleteEntry(<?= $entryId ?>)">Delete</button>
                        </div>
                    <?php endif; ?>
                </div>

                <form id="entry-form" class="entry-form" data-entry-id="<?= $entryId ?>">
                    <div class="form-row-inline">
                        <div class="form-group flex-grow">
                            <input type="text" id="title" name="title" value="<?= e($entry['title'] ?? '') ?>"
                                   placeholder="Entry Title (optional)" class="title-input">
                        </div>
                        <div class="form-group">
                            <input type="date" id="entry_date" name="entry_date"
                                   value="<?= $entry ? date('Y-m-d', strtotime($entry['entry_date'])) : date('Y-m-d') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>How are you feeling?</label>
                        <div class="mood-selector">
                            <?php foreach ($moods as $m):
                                $emoji = getMoodEmoji($m);
                            ?>
                                <label class="mood-option">
                                    <input type="radio" name="mood" value="<?= $m ?>"
                                           <?= ($entry['mood'] ?? '') === $m ? 'checked' : '' ?>>
                                    <span class="mood-icon" title="<?= ucfirst($m) ?>"><?= $emoji ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <textarea id="content" name="content" rows="15"
                                  placeholder="What's on your mind today?"><?= e($entry['content'] ?? '') ?></textarea>
                    </div>

                    <!-- Scripture Reference -->
                    <div class="form-group">
                        <label for="scripture_ref">Scripture Reference</label>
                        <input type="text" id="scripture_ref" name="scripture_ref"
                               value="<?= e($entry['scripture_ref'] ?? '') ?>"
                               placeholder="e.g., Psalm 23:1-6">
                    </div>

                    <!-- Tags -->
                    <div class="form-group">
                        <label>Tags</label>
                        <div class="tags-input" id="tags-input">
                            <div class="tags-list" id="tags-list">
                                <?php foreach ($entryTags as $t): ?>
                                    <span class="tag-item" data-id="<?= $t['id'] ?>">
                                        <?= e($t['name']) ?>
                                        <button type="button" class="tag-remove">&times;</button>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            <input type="text" id="tag-input" placeholder="Add tags...">
                        </div>
                        <div class="tag-suggestions" id="tag-suggestions">
                            <?php foreach ($allTags as $t): ?>
                                <button type="button" class="tag-suggestion" data-id="<?= $t['id'] ?>" data-name="<?= e($t['name']) ?>">
                                    <?= e($t['name']) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Privacy -->
                    <div class="form-group">
                        <label>Privacy</label>
                        <div class="privacy-options">
                            <label class="privacy-option">
                                <input type="radio" name="is_private" value="1" <?= ($entry['is_private'] ?? 1) == 1 ? 'checked' : '' ?>>
                                <span>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                    </svg>
                                    Private
                                </span>
                            </label>
                            <label class="privacy-option">
                                <input type="radio" name="is_private" value="0" <?= ($entry['is_private'] ?? 1) == 0 ? 'checked' : '' ?>>
                                <span>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                        <path d="M7 11V7a5 5 0 0 1 9.9-1"></path>
                                    </svg>
                                    Share with leaders
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="/diary/" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <?= $entry ? 'Save Changes' : 'Save Entry' ?>
                        </button>
                    </div>
                </form>

                <?php if ($entry): ?>
                    <div class="entry-meta">
                        <small>Created: <?= date('M j, Y \a\t g:i A', strtotime($entry['created_at'])) ?></small>
                        <?php if ($entry['updated_at'] !== $entry['created_at']): ?>
                            <small>• Updated: <?= date('M j, Y \a\t g:i A', strtotime($entry['updated_at'])) ?></small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        const allTags = <?= json_encode(array_map(fn($t) => ['id' => $t['id'], 'name' => $t['name']], $allTags)) ?>;
    </script>
    <script src="/diary/js/diary.js"></script>
</body>
</html>
<?php

function getMoodEmoji($mood) {
    $emojis = [
        'grateful' => '🙏',
        'joyful' => '😊',
        'peaceful' => '😌',
        'hopeful' => '🌟',
        'anxious' => '😰',
        'sad' => '😢',
        'angry' => '😤',
        'confused' => '😕'
    ];
    return $emojis[$mood] ?? '📝';
}
