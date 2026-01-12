<?php
/**
 * CRC Diary - Entry View/Edit
 * Premium OAC-style dark theme
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

function getMoodColor($mood) {
    $colors = [
        'grateful' => '#8B5CF6',
        'joyful' => '#F59E0B',
        'peaceful' => '#06B6D4',
        'hopeful' => '#10B981',
        'anxious' => '#EF4444',
        'sad' => '#6366F1',
        'angry' => '#DC2626',
        'confused' => '#F97316'
    ];
    return $colors[$mood] ?? '#8B5CF6';
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Parisienne&family=Noto+Color+Emoji&display=swap" rel="stylesheet">
    <style>
        /* Emoji font support */
        .mood-icon, .mood-emoji {
            font-family: 'Noto Color Emoji', 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', sans-serif;
        }
        /* AI Button styling */
        .btn-ai {
            background: linear-gradient(135deg, #8B5CF6 0%, #6366F1 100%);
            color: #fff;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-ai:hover {
            background: linear-gradient(135deg, #7C3AED 0%, #4F46E5 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
        }
        .btn-ai:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .btn-ai svg {
            width: 18px;
            height: 18px;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="entry-page">
                <!-- Header -->
                <div class="entry-header">
                    <a href="/diary/" class="back-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                        Back to Diary
                    </a>

                    <?php if ($entry): ?>
                        <div class="entry-actions">
                            <button type="button" class="btn btn-outline btn-danger" onclick="deleteEntry(<?= $entryId ?>)">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                </svg>
                                Delete
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Entry Form -->
                <form id="entry-form" class="entry-form" data-entry-id="<?= $entryId ?>">
                    <div class="form-card">
                        <div class="form-card-header">
                            <h2 class="display-title"><?= $entry ? 'Edit Entry' : 'New Entry' ?></h2>
                            <p class="subtitle">Record your thoughts, prayers, and reflections</p>
                        </div>

                        <div class="form-card-body">
                            <!-- Title and Date Row -->
                            <div class="form-row-inline">
                                <div class="form-group flex-grow">
                                    <label for="title">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                        Title
                                    </label>
                                    <input type="text" id="title" name="title" value="<?= e($entry['title'] ?? '') ?>"
                                           placeholder="Give your entry a title (optional)" class="title-input">
                                </div>
                                <div class="form-group date-group">
                                    <label for="entry_date">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                            <line x1="16" y1="2" x2="16" y2="6"></line>
                                            <line x1="8" y1="2" x2="8" y2="6"></line>
                                            <line x1="3" y1="10" x2="21" y2="10"></line>
                                        </svg>
                                        Date
                                    </label>
                                    <input type="date" id="entry_date" name="entry_date"
                                           value="<?= $entry ? date('Y-m-d', strtotime($entry['entry_date'])) : date('Y-m-d') ?>">
                                </div>
                            </div>

                            <!-- Mood Selector -->
                            <div class="form-group">
                                <label>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
                                        <line x1="9" y1="9" x2="9.01" y2="9"></line>
                                        <line x1="15" y1="9" x2="15.01" y2="9"></line>
                                    </svg>
                                    How are you feeling?
                                </label>
                                <div class="mood-selector">
                                    <?php foreach ($moods as $m):
                                        $emoji = getMoodEmoji($m);
                                        $color = getMoodColor($m);
                                    ?>
                                        <label class="mood-option" style="--mood-color: <?= $color ?>">
                                            <input type="radio" name="mood" value="<?= $m ?>"
                                                   <?= ($entry['mood'] ?? '') === $m ? 'checked' : '' ?>>
                                            <span class="mood-icon" title="<?= ucfirst($m) ?>"><?= $emoji ?></span>
                                            <span class="mood-label"><?= ucfirst($m) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="form-group">
                                <label for="content">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                        <line x1="17" y1="10" x2="3" y2="10"></line>
                                        <line x1="21" y1="6" x2="3" y2="6"></line>
                                        <line x1="21" y1="14" x2="3" y2="14"></line>
                                        <line x1="17" y1="18" x2="3" y2="18"></line>
                                    </svg>
                                    Your Thoughts
                                </label>
                                <textarea id="content" name="content" rows="12"
                                          placeholder="What's on your mind today? Share your thoughts, prayers, and reflections..."><?= e($entry['content'] ?? '') ?></textarea>
                            </div>

                            <!-- Scripture Reference -->
                            <div class="form-group">
                                <label for="scripture_ref">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                        <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                    </svg>
                                    Scripture Reference
                                </label>
                                <input type="text" id="scripture_ref" name="scripture_ref"
                                       value="<?= e($entry['scripture_ref'] ?? '') ?>"
                                       placeholder="e.g., Psalm 23:1-6, John 3:16">
                            </div>

                            <!-- Tags -->
                            <div class="form-group">
                                <label>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                        <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                                        <line x1="7" y1="7" x2="7.01" y2="7"></line>
                                    </svg>
                                    Tags
                                </label>
                                <div class="tags-input-wrapper">
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
                                    <?php if (!empty($allTags)): ?>
                                        <div class="tag-suggestions" id="tag-suggestions">
                                            <?php foreach ($allTags as $t): ?>
                                                <button type="button" class="tag-suggestion" data-id="<?= $t['id'] ?>" data-name="<?= e($t['name']) ?>">
                                                    <?= e($t['name']) ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Privacy -->
                            <div class="form-group">
                                <label>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                    </svg>
                                    Privacy
                                </label>
                                <div class="privacy-options">
                                    <label class="privacy-option">
                                        <input type="radio" name="is_private" value="1" <?= ($entry['is_private'] ?? 1) == 1 ? 'checked' : '' ?>>
                                        <span class="privacy-card">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                            </svg>
                                            <span class="privacy-label">Private</span>
                                            <span class="privacy-desc">Only visible to you</span>
                                        </span>
                                    </label>
                                    <label class="privacy-option">
                                        <input type="radio" name="is_private" value="0" <?= ($entry['is_private'] ?? 1) == 0 ? 'checked' : '' ?>>
                                        <span class="privacy-card">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                                <circle cx="9" cy="7" r="4"></circle>
                                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                            </svg>
                                            <span class="privacy-label">Share with leaders</span>
                                            <span class="privacy-desc">Visible to church leaders</span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="form-card-footer">
                            <a href="/diary/" class="btn btn-secondary">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                                Cancel
                            </a>
                            <button type="button" class="btn btn-ai" id="aiAssistBtn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                                </svg>
                                AI Assist
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                    <polyline points="7 3 7 8 15 8"></polyline>
                                </svg>
                                <?= $entry ? 'Save Changes' : 'Save Entry' ?>
                            </button>
                        </div>
                    </div>
                </form>

                <?php if ($entry): ?>
                    <div class="entry-meta">
                        <span class="meta-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                            Created: <?= date('M j, Y \a\t g:i A', strtotime($entry['created_at'])) ?>
                        </span>
                        <?php if ($entry['updated_at'] !== $entry['created_at']): ?>
                            <span class="meta-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                                Updated: <?= date('M j, Y \a\t g:i A', strtotime($entry['updated_at'])) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        const allTags = <?= json_encode(array_map(fn($t) => ['id' => $t['id'], 'name' => $t['name']], $allTags ?? [])) ?>;
        const CSRF_TOKEN = '<?= CSRF::token() ?>';

        // AI Assist functionality
        document.getElementById('aiAssistBtn')?.addEventListener('click', async function() {
            const contentEl = document.getElementById('content');
            const text = contentEl?.value?.trim();

            if (!text) {
                alert('Please write something first');
                return;
            }

            const btn = this;
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<svg class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><circle cx="12" cy="12" r="10" stroke-dasharray="32" stroke-dashoffset="32"></circle></svg> Enhancing...';

            try {
                const response = await fetch('/diary/api/ai_enhance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({ text: text })
                });

                const data = await response.json();

                if (data.success && data.enhanced_text) {
                    contentEl.value = data.enhanced_text;
                    contentEl.style.borderColor = '#10B981';
                    setTimeout(() => contentEl.style.borderColor = '', 2000);
                } else {
                    alert(data.error || 'AI enhancement failed. Please try again.');
                }
            } catch (error) {
                console.error('AI enhance error:', error);
                alert('Failed to connect to AI service. Please try again.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        });
    </script>
    <style>.spin { animation: spin 1s linear infinite; } @keyframes spin { to { transform: rotate(360deg); } }</style>
    <script src="/diary/js/diary.js"></script>
</body>
</html>
