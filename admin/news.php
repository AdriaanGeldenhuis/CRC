<?php
/**
 * CRC Admin - News Management
 * Super admin can upload and manage news images for the homepage
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();
Auth::requireRole('super_admin');

$user = Auth::user();
$pageTitle = "News Management - CRC Admin";

// Check if table exists and create if not
$tableExists = false;
try {
    Database::fetchOne("SELECT 1 FROM news_items LIMIT 1");
    $tableExists = true;
} catch (Exception $e) {
    // Table doesn't exist, create it
    try {
        Database::query("
            CREATE TABLE IF NOT EXISTS news_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                image_path VARCHAR(500) NOT NULL,
                description TEXT DEFAULT NULL,
                link_url VARCHAR(500) DEFAULT NULL,
                display_order INT DEFAULT 0,
                is_active BOOLEAN DEFAULT TRUE,
                created_by INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $tableExists = true;
    } catch (Exception $e2) {
        // Can't create table
    }
}

// Handle form submissions
$message = '';
$messageType = '';

if ($tableExists && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        CSRF::verify($_POST['csrf_token'] ?? '');

        $action = $_POST['action'] ?? '';

        if ($action === 'upload') {
            // Handle image upload
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $linkUrl = trim($_POST['link_url'] ?? '');

            if (empty($title)) {
                $message = 'Title is required';
                $messageType = 'error';
            } elseif (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                $message = 'Please select an image to upload';
                $messageType = 'error';
            } else {
                $file = $_FILES['image'];
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

                if (!in_array($file['type'], $allowedTypes)) {
                    $message = 'Only JPG, PNG, GIF, and WebP images are allowed';
                    $messageType = 'error';
                } elseif ($file['size'] > 5 * 1024 * 1024) {
                    $message = 'Image must be less than 5MB';
                    $messageType = 'error';
                } else {
                    // Create uploads directory if needed
                    $uploadDir = __DIR__ . '/../uploads/news/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    // Generate unique filename
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = uniqid('news_') . '.' . $ext;
                    $filepath = $uploadDir . $filename;

                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        // Get max display order
                        $maxOrder = Database::fetchColumn("SELECT MAX(display_order) FROM news_items") ?? 0;

                        Database::query(
                            "INSERT INTO news_items (title, image_path, description, link_url, display_order, created_by)
                             VALUES (?, ?, ?, ?, ?, ?)",
                            [$title, '/uploads/news/' . $filename, $description ?: null, $linkUrl ?: null, $maxOrder + 1, $user['id']]
                        );

                        $message = 'News item added successfully';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to upload image';
                        $messageType = 'error';
                    }
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);

            // Get image path before deleting
            $item = Database::fetchOne("SELECT image_path FROM news_items WHERE id = ?", [$id]);

            if ($item) {
                // Delete the file
                $filepath = __DIR__ . '/..' . $item['image_path'];
                if (file_exists($filepath)) {
                    unlink($filepath);
                }

                Database::query("DELETE FROM news_items WHERE id = ?", [$id]);
                $message = 'News item deleted';
                $messageType = 'success';
            }
        } elseif ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            Database::query("UPDATE news_items SET is_active = NOT is_active WHERE id = ?", [$id]);
            $message = 'Status updated';
            $messageType = 'success';
        } elseif ($action === 'reorder') {
            $order = json_decode($_POST['order'] ?? '[]', true);
            if (is_array($order)) {
                foreach ($order as $index => $id) {
                    Database::query("UPDATE news_items SET display_order = ? WHERE id = ?", [$index, (int)$id]);
                }
                $message = 'Order updated';
                $messageType = 'success';
            }
        }
    } catch (Exception $e) {
        $message = 'An error occurred: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Get all news items
$newsItems = [];
if ($tableExists) {
    try {
        $newsItems = Database::fetchAll(
            "SELECT n.*, u.name as creator_name
             FROM news_items n
             LEFT JOIN users u ON n.created_by = u.id
             ORDER BY n.display_order ASC"
        ) ?: [];
    } catch (Exception $e) {
        $newsItems = [];
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --bg-base: #0a0a0a;
            --bg-card: #161616;
            --bg-surface: #1c1c1c;
            --primary: #8B5CF6;
            --primary-light: #A78BFA;
            --accent: #06B6D4;
            --text-primary: #f5f5f5;
            --text-secondary: #c8c8c8;
            --text-muted: #7a7a7a;
            --success: #10B981;
            --danger: #EF4444;
            --border: rgba(139, 92, 246, 0.15);
            --glow-primary: rgba(139, 92, 246, 0.3);
            --radius: 16px;
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.8);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-base);
            color: var(--text-primary);
            min-height: 100vh;
        }

        /* Background glow */
        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background:
                radial-gradient(ellipse 80% 50% at 20% 20%, var(--glow-primary) 0%, transparent 50%),
                radial-gradient(ellipse 60% 40% at 80% 80%, rgba(6, 182, 212, 0.2) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        .admin-layout { display: flex; min-height: 100vh; position: relative; z-index: 1; }

        /* Sidebar */
        .admin-sidebar {
            width: 260px;
            background: var(--bg-card);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
        }

        .admin-logo {
            color: var(--primary);
            font-size: 1.25rem;
            font-weight: 700;
            text-decoration: none;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .sidebar-nav { flex: 1; padding: 1rem 0; overflow-y: auto; }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s;
        }

        .nav-item:hover { background: var(--bg-surface); color: var(--text-primary); }
        .nav-item.active { background: var(--bg-surface); color: var(--primary); border-left: 3px solid var(--primary); }
        .nav-item svg { width: 18px; height: 18px; }

        .sidebar-footer { padding: 1rem; border-top: 1px solid var(--border); }

        /* Main */
        .admin-main { flex: 1; margin-left: 260px; }

        .admin-header {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .admin-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .admin-content { padding: 2rem; }

        /* Alert */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert.success { background: rgba(16, 185, 129, 0.15); border: 1px solid var(--success); color: var(--success); }
        .alert.error { background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: var(--danger); }

        /* Cards */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            margin-bottom: 2rem;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h2 {
            font-size: 1.25rem;
            color: var(--text-primary);
        }

        .card-body { padding: 1.5rem; }

        /* Form */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .form-group { margin-bottom: 1.5rem; }
        .form-group.full-width { grid-column: 1 / -1; }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-secondary);
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--glow-primary);
        }

        textarea.form-control { resize: vertical; min-height: 100px; }

        /* File upload */
        .file-upload {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem 2rem;
            background: var(--bg-surface);
            border: 2px dashed var(--border);
            border-radius: var(--radius);
            cursor: pointer;
            transition: all 0.3s;
        }

        .file-upload:hover { border-color: var(--primary); background: rgba(139, 92, 246, 0.05); }

        .file-upload input {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }

        .file-upload svg { color: var(--primary); margin-bottom: 1rem; }
        .file-upload span { color: var(--text-muted); font-size: 0.9rem; }
        .file-upload .preview { max-width: 200px; max-height: 150px; border-radius: 8px; margin-top: 1rem; }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.875rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: #fff;
        }

        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 24px var(--glow-primary); }

        .btn-danger { background: var(--danger); color: #fff; }
        .btn-danger:hover { background: #dc2626; }

        .btn-ghost {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border);
        }

        .btn-ghost:hover { background: var(--bg-surface); color: var(--text-primary); }

        .btn-sm { padding: 0.5rem 1rem; font-size: 0.875rem; }

        /* News Grid */
        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .news-item {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            transition: all 0.3s;
            position: relative;
        }

        .news-item:hover { border-color: var(--primary); transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0, 0, 0, 0.5); }
        .news-item.inactive { opacity: 0.5; }

        .news-item-image {
            aspect-ratio: 16/9;
            overflow: hidden;
            position: relative;
        }

        .news-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .news-item:hover .news-item-image img { transform: scale(1.05); }

        .news-item-badge {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .news-item-badge.active { background: var(--success); color: #fff; }
        .news-item-badge.inactive { background: var(--danger); color: #fff; }

        .news-item-content { padding: 1.25rem; }

        .news-item-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .news-item-desc {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .news-item-actions {
            display: flex;
            gap: 0.5rem;
        }

        .news-item-order {
            position: absolute;
            top: 0.75rem;
            left: 0.75rem;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 50%;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--primary);
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-muted);
        }

        .empty-state svg { margin-bottom: 1rem; opacity: 0.5; }
        .empty-state p { margin-bottom: 1.5rem; }

        /* Responsive */
        @media (max-width: 1024px) {
            .form-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .admin-sidebar { display: none; }
            .admin-main { margin-left: 0; }
            .news-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <a href="/admin/" class="admin-logo">CRC Admin</a>
            </div>
            <nav class="sidebar-nav">
                <a href="/admin/" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    Dashboard
                </a>
                <a href="/admin/news.php" class="nav-item active">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
                    News
                </a>
                <a href="/admin/users.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                    Users
                </a>
                <a href="/admin/congregations.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/></svg>
                    Congregations
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="/home/" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                    Back to App
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-header">
                <h1>News Management</h1>
                <span style="color: var(--text-muted);">Manage homepage news images</span>
            </header>

            <div class="admin-content">
                <?php if ($message): ?>
                    <div class="alert <?= $messageType ?>"><?= e($message) ?></div>
                <?php endif; ?>

                <!-- Upload Form -->
                <div class="card">
                    <div class="card-header">
                        <h2>Add News Item</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <?= CSRF::field() ?>
                            <input type="hidden" name="action" value="upload">

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Title *</label>
                                    <input type="text" name="title" class="form-control" required placeholder="Enter news title">
                                </div>

                                <div class="form-group">
                                    <label>Link URL (optional)</label>
                                    <input type="url" name="link_url" class="form-control" placeholder="https://...">
                                </div>

                                <div class="form-group full-width">
                                    <label>Description (optional)</label>
                                    <textarea name="description" class="form-control" rows="2" placeholder="Brief description..."></textarea>
                                </div>

                                <div class="form-group full-width">
                                    <label>Image *</label>
                                    <div class="file-upload" id="fileUpload">
                                        <input type="file" name="image" accept="image/*" required id="imageInput">
                                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>
                                        </svg>
                                        <span>Click or drag image here (JPG, PNG, GIF, WebP - max 5MB)</span>
                                        <img id="preview" class="preview" style="display:none;">
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Add News Item
                            </button>
                        </form>
                    </div>
                </div>

                <!-- News Items List -->
                <div class="card">
                    <div class="card-header">
                        <h2>Current News Items</h2>
                        <span style="color: var(--text-muted);"><?= count($newsItems) ?> items</span>
                    </div>
                    <div class="card-body">
                        <?php if ($newsItems): ?>
                            <div class="news-grid" id="newsGrid">
                                <?php foreach ($newsItems as $index => $item): ?>
                                    <div class="news-item <?= $item['is_active'] ? '' : 'inactive' ?>" data-id="<?= $item['id'] ?>">
                                        <span class="news-item-order"><?= $index + 1 ?></span>
                                        <div class="news-item-image">
                                            <img src="<?= e($item['image_path']) ?>" alt="<?= e($item['title']) ?>">
                                            <span class="news-item-badge <?= $item['is_active'] ? 'active' : 'inactive' ?>">
                                                <?= $item['is_active'] ? 'Active' : 'Hidden' ?>
                                            </span>
                                        </div>
                                        <div class="news-item-content">
                                            <h3 class="news-item-title"><?= e($item['title']) ?></h3>
                                            <?php if ($item['description']): ?>
                                                <p class="news-item-desc"><?= e($item['description']) ?></p>
                                            <?php endif; ?>
                                            <div class="news-item-actions">
                                                <form method="POST" style="display:inline;">
                                                    <?= CSRF::field() ?>
                                                    <input type="hidden" name="action" value="toggle">
                                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                    <button type="submit" class="btn btn-ghost btn-sm">
                                                        <?= $item['is_active'] ? 'Hide' : 'Show' ?>
                                                    </button>
                                                </form>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this news item?');">
                                                    <?= CSRF::field() ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/>
                                </svg>
                                <p>No news items yet. Add your first one above!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Image preview
        const imageInput = document.getElementById('imageInput');
        const preview = document.getElementById('preview');
        const fileUpload = document.getElementById('fileUpload');

        imageInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        });

        // Drag and drop
        fileUpload.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.borderColor = 'var(--primary)';
            this.style.background = 'rgba(139, 92, 246, 0.1)';
        });

        fileUpload.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.style.borderColor = 'var(--border)';
            this.style.background = 'var(--bg-surface)';
        });

        fileUpload.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.borderColor = 'var(--border)';
            this.style.background = 'var(--bg-surface)';

            const file = e.dataTransfer.files[0];
            if (file && file.type.startsWith('image/')) {
                imageInput.files = e.dataTransfer.files;
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>
