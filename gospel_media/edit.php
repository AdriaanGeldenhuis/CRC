<?php
/**
 * CRC Gospel Media - Edit Post Page
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$postId = (int)($_GET['id'] ?? 0);
if (!$postId) {
    Response::redirect('/gospel_media/');
}

// Get the post
$post = Database::fetchOne(
    "SELECT * FROM posts WHERE id = ? AND status = 'active'",
    [$postId]
);

if (!$post) {
    Response::redirect('/gospel_media/');
}

// Check permission - only owner or admin can edit
if ($post['user_id'] != Auth::id() && !Auth::isAdmin()) {
    Response::redirect('/gospel_media/');
}

$primaryCong = Auth::primaryCongregation();
$user = Auth::user();
$pageTitle = 'Edit Post - CRC';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::require();

    $content = trim($_POST['content'] ?? '');
    $scope = $_POST['scope'] ?? $post['scope'];

    if (empty($content)) {
        $error = 'Content is required';
    } elseif (strlen($content) > 5000) {
        $error = 'Content too long';
    } else {
        // Only admin can change to global scope
        if ($scope === 'global' && !Auth::isAdmin()) {
            $scope = 'congregation';
        }

        Database::update(
            'posts',
            [
                'content' => $content,
                'scope' => $scope,
                'updated_at' => date('Y-m-d H:i:s')
            ],
            'id = ?',
            [$postId]
        );

        Logger::audit(Auth::id(), 'edited_post', ['post_id' => $postId]);

        Response::redirect('/gospel_media/');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/gospel_media/css/gospel_media.css?v=<?= filemtime(__DIR__ . '/css/gospel_media.css') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        (function() {
            const saved = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', saved);
        })();
    </script>
    <style>
        .edit-page {
            min-height: 100vh;
            padding-top: 56px;
        }
        .edit-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 56px;
            background: var(--bg-glass);
            backdrop-filter: blur(var(--blur));
            -webkit-backdrop-filter: blur(var(--blur));
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1rem;
            z-index: 100;
        }
        .back-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: none;
            border: none;
            color: var(--text);
            text-decoration: none;
        }
        .back-btn svg { width: 24px; height: 24px; }
        .edit-header h1 {
            font-size: 1.1rem;
            font-weight: 600;
        }
        .save-btn {
            padding: 0.5rem 1.25rem;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            font-family: inherit;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            box-shadow: 0 8px 20px var(--accent-glow);
            transition: all 0.12s ease;
        }
        .save-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px var(--accent-glow);
        }
        .edit-body {
            padding: 1rem;
            max-width: var(--max);
            margin: 0 auto;
        }
        .error-msg {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--bad);
            color: var(--bad);
            padding: 0.75rem 1rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 0.5rem;
        }
        .scope-select {
            width: 100%;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius-sm);
            color: var(--text);
            font-size: 0.95rem;
            font-family: inherit;
            padding: 0.75rem 1rem;
            cursor: pointer;
            transition: all 0.12s ease;
        }
        .scope-select:focus {
            outline: none;
            border-color: var(--accent);
        }
        .scope-select option {
            background: var(--bg1);
            color: var(--text);
        }
        .post-textarea {
            width: 100%;
            min-height: 200px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius-sm);
            color: var(--text);
            font-size: 1rem;
            line-height: 1.6;
            resize: vertical;
            font-family: inherit;
            padding: 1rem;
            transition: all 0.12s ease;
        }
        .post-textarea::placeholder { color: var(--muted2); }
        .post-textarea:focus {
            outline: none;
            border-color: var(--accent);
        }
        .media-preview-grid {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        .preview-item img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: var(--radius-sm);
        }
    </style>
</head>
<body>
    <div class="edit-page">
        <header class="edit-header">
            <a href="/gospel_media/" class="back-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </a>
            <h1>Edit Post</h1>
            <button type="submit" form="editPostForm" class="save-btn">Save</button>
        </header>

        <div class="edit-body">
            <?php if (!empty($error)): ?>
                <div class="error-msg"><?= e($error) ?></div>
            <?php endif; ?>

            <form id="editPostForm" method="POST">
                <?= CSRF::field() ?>

                <div class="form-group">
                    <label>Post to</label>
                    <select name="scope" class="scope-select">
                        <option value="congregation" <?= $post['scope'] === 'congregation' ? 'selected' : '' ?>>
                            <?= e($primaryCong['name'] ?? 'My Congregation') ?>
                        </option>
                        <?php if (Auth::isAdmin()): ?>
                            <option value="global" <?= $post['scope'] === 'global' ? 'selected' : '' ?>>
                                Global (All Congregations)
                            </option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Content</label>
                    <textarea name="content" class="post-textarea" required><?= e($post['content']) ?></textarea>
                </div>

                <?php if ($post['media']): ?>
                    <?php $media = json_decode($post['media'], true); ?>
                    <?php if ($media): ?>
                        <div class="form-group">
                            <label>Attached Media</label>
                            <div class="media-preview-grid">
                                <?php foreach ($media as $item): ?>
                                    <div class="preview-item">
                                        <img src="<?= e($item['url']) ?>" alt="">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </form>
        </div>
    </div>
</body>
</html>
