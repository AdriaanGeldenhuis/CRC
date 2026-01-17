<?php
/**
 * CRC Gospel Media - Create Post Page
 * Native app friendly create post experience
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$primaryCong = Auth::primaryCongregation();
if (!$primaryCong) {
    Response::redirect('/onboarding/');
}

$user = Auth::user();
$pageTitle = 'Create Post - CRC';
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
        .create-page {
            min-height: 100vh;
            padding-top: 56px;
        }
        .create-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 56px;
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--glass-border);
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
            color: var(--text-primary);
            text-decoration: none;
        }
        .back-btn svg { width: 24px; height: 24px; }
        .create-header h1 {
            font-size: 1.1rem;
            font-weight: 600;
        }
        .post-btn {
            padding: 0.5rem 1.25rem;
            background: var(--gradient-primary);
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
        }
        .post-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .create-body {
            padding: 1rem;
        }
        .author-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        .author-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
        }
        .author-avatar-placeholder {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--gradient-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.1rem;
        }
        .author-details strong {
            display: block;
            font-size: 0.95rem;
        }
        .scope-select {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            color: var(--text-secondary);
            font-size: 0.85rem;
            padding: 0.35rem 0.75rem;
            margin-top: 0.25rem;
            cursor: pointer;
        }
        .scope-select option {
            background: var(--bg-card);
            color: var(--text-primary);
        }
        .post-textarea {
            width: 100%;
            min-height: 200px;
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 1.1rem;
            line-height: 1.6;
            resize: none;
            font-family: inherit;
            padding: 0;
        }
        .post-textarea::placeholder { color: var(--text-muted); }
        .post-textarea:focus { outline: none; }
        .media-toolbar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--bg-card);
            border-top: 1px solid var(--glass-border);
            padding: 1rem;
            display: flex;
            gap: 0.75rem;
        }
        .toolbar-btn {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 50%;
            color: var(--primary);
            cursor: pointer;
            transition: var(--transition);
        }
        .toolbar-btn:hover {
            background: rgba(139, 92, 246, 0.15);
            border-color: var(--primary);
        }
        .toolbar-btn svg { width: 24px; height: 24px; }
        .media-preview-grid {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 1rem;
            padding-bottom: 80px;
        }
        .preview-item {
            position: relative;
            width: 100px;
            height: 100px;
        }
        .preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: var(--radius);
        }
        .remove-media {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 24px;
            height: 24px;
            background: var(--danger);
            border: none;
            border-radius: 50%;
            color: white;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="create-page">
        <header class="create-header">
            <a href="/gospel_media/" class="back-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </a>
            <h1>Create Post</h1>
            <button type="submit" form="createPostForm" class="post-btn" id="postBtn">Post</button>
        </header>

        <div class="create-body">
            <form id="createPostForm" onsubmit="submitPost(event)">
                <div class="author-row">
                    <?php if ($user['avatar']): ?>
                        <img src="<?= e($user['avatar']) ?>" alt="" class="author-avatar">
                    <?php else: ?>
                        <div class="author-avatar-placeholder"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
                    <?php endif; ?>
                    <div class="author-details">
                        <strong><?= e($user['name']) ?></strong>
                        <select name="scope" class="scope-select">
                            <option value="congregation"><?= e($primaryCong['name']) ?></option>
                            <?php if (Auth::isAdmin()): ?>
                                <option value="global">Global (All Congregations)</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <textarea
                    name="content"
                    class="post-textarea"
                    placeholder="What's on your heart, <?= e(explode(' ', $user['name'])[0]) ?>?"
                    required
                    oninput="updatePostButton()"
                ></textarea>

                <div class="media-preview-grid" id="mediaPreview"></div>
            </form>
        </div>

        <div class="media-toolbar">
            <label class="toolbar-btn">
                <input type="file" id="mediaInput" accept="image/*" multiple style="display:none" onchange="handleMediaSelect(this)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                    <polyline points="21 15 16 10 5 21"></polyline>
                </svg>
            </label>
            <label class="toolbar-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="23 7 16 12 23 17 23 7"></polygon>
                    <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
                </svg>
            </label>
            <label class="toolbar-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
                    <line x1="9" y1="9" x2="9.01" y2="9"></line>
                    <line x1="15" y1="9" x2="15.01" y2="9"></line>
                </svg>
            </label>
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        let selectedFiles = [];

        function updatePostButton() {
            const content = document.querySelector('.post-textarea').value.trim();
            document.getElementById('postBtn').disabled = content.length === 0;
        }

        function handleMediaSelect(input) {
            const files = Array.from(input.files);
            selectedFiles = selectedFiles.concat(files);
            renderPreviews();
        }

        function removeMedia(index) {
            selectedFiles.splice(index, 1);
            renderPreviews();
        }

        function renderPreviews() {
            const container = document.getElementById('mediaPreview');
            container.innerHTML = '';

            selectedFiles.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'preview-item';
                    div.innerHTML = `
                        <img src="${e.target.result}" alt="">
                        <button type="button" class="remove-media" onclick="removeMedia(${index})">&times;</button>
                    `;
                    container.appendChild(div);
                };
                reader.readAsDataURL(file);
            });
        }

        async function submitPost(e) {
            e.preventDefault();

            const form = e.target;
            const btn = document.getElementById('postBtn');
            const content = form.content.value.trim();
            const scope = form.scope.value;

            if (!content) return;

            btn.disabled = true;
            btn.textContent = 'Posting...';

            try {
                const formData = new FormData();
                formData.append('action', 'create');
                formData.append('content', content);
                formData.append('scope', scope);
                formData.append('csrf_token', csrfToken);

                selectedFiles.forEach((file, i) => {
                    formData.append('media[]', file);
                });

                const response = await fetch('/gospel_media/api/posts.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.ok) {
                    window.location.href = '/gospel_media/';
                } else {
                    alert(data.error || 'Failed to create post');
                    btn.disabled = false;
                    btn.textContent = 'Post';
                }
            } catch (error) {
                alert('Error creating post');
                btn.disabled = false;
                btn.textContent = 'Post';
            }
        }

        // Initialize
        updatePostButton();
    </script>
</body>
</html>
