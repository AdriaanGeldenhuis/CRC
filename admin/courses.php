<?php
/**
 * CRC Global Admin - Courses Management
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();
Auth::requireRole('super_admin');

$user = Auth::user();
$pageTitle = "Courses - Admin";

// Get filters
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Check if courses table exists and get data
$courses = [];
$totalCount = 0;
$totalPages = 0;

try {
    $whereClause = "WHERE 1=1";
    $params = [];

    if ($search) {
        $whereClause .= " AND (c.title LIKE ? OR c.description LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $totalCount = Database::fetchColumn(
        "SELECT COUNT(*) FROM courses c $whereClause",
        $params
    );

    $courses = Database::fetchAll(
        "SELECT c.*,
                (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count,
                (SELECT COUNT(*) FROM user_course_enrollments WHERE course_id = c.id) as enrollment_count
         FROM courses c
         $whereClause
         ORDER BY c.created_at DESC
         LIMIT $perPage OFFSET $offset",
        $params
    ) ?: [];

    $totalPages = ceil($totalCount / $perPage);
} catch (Exception $e) {
    // Table might not exist
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/admin/css/admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        (function() {
            const saved = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', saved);
        })();
    </script>
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <div class="admin-layout">
        <?php include __DIR__ . '/partials/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-header">
                <h1>Courses</h1>
                <div class="header-actions">
                    <button onclick="openAddCourse()" class="btn btn-primary">
                        + Add Course
                    </button>
                </div>
            </header>

            <div class="admin-content">
                <!-- Filters -->
                <div class="filters-bar">
                    <form class="search-form" method="GET">
                        <input type="search" name="search" placeholder="Search courses..."
                               value="<?= e($search) ?>" class="search-input">
                        <button type="submit" class="btn btn-outline">Search</button>
                    </form>
                </div>

                <!-- Courses Table -->
                <div class="card">
                    <div class="card-body">
                        <p class="result-count"><?= number_format($totalCount) ?> course<?= $totalCount != 1 ? 's' : '' ?></p>

                        <?php if ($courses): ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Lessons</th>
                                        <th>Enrollments</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($courses as $c): ?>
                                        <tr>
                                            <td><strong><?= e($c['title']) ?></strong></td>
                                            <td><?= e($c['category'] ?? '-') ?></td>
                                            <td><?= $c['lesson_count'] ?? 0 ?></td>
                                            <td><?= $c['enrollment_count'] ?? 0 ?></td>
                                            <td>
                                                <?php $courseStatus = !empty($c['is_published']) ? 'published' : 'draft'; ?>
                                                <span class="status-badge status-<?= $courseStatus ?>">
                                                    <?= ucfirst($courseStatus) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button onclick="editCourse(<?= $c['id'] ?>)" class="action-btn" title="Edit">✏️</button>
                                                    <button onclick="deleteCourse(<?= $c['id'] ?>)" class="action-btn" title="Delete">🗑️</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <?php if ($totalPages > 1): ?>
                                <div class="pagination">
                                    <?php if ($page > 1): ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-btn">← Previous</a>
                                    <?php endif; ?>
                                    <span class="page-info">Page <?= $page ?> of <?= $totalPages ?></span>
                                    <?php if ($page < $totalPages): ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-btn">Next →</a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <span class="empty-icon">📚</span>
                                <p class="empty-text">No courses found</p>
                                <p class="empty-subtext">Create your first course to get started</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Course Modal -->
    <div id="add-course-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Course</h2>
                <button onclick="closeModal('add-course-modal')" class="modal-close">&times;</button>
            </div>
            <form id="add-course-form">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" required class="form-input">
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" class="form-select">
                        <option value="bible_study">Bible Study</option>
                        <option value="discipleship">Discipleship</option>
                        <option value="leadership">Leadership</option>
                        <option value="ministry">Ministry</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-textarea" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Cover Image URL</label>
                    <input type="url" name="cover_image" class="form-input" placeholder="Image URL">
                </div>
                <div class="form-actions">
                    <button type="button" onclick="closeModal('add-course-modal')" class="btn btn-outline">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Course</button>
                </div>
            </form>
        </div>
    </div>

    <script src="/admin/js/admin.js"></script>
</body>
</html>
