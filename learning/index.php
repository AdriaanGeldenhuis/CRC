<?php
/**
 * CRC Learning - Courses List
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
$pageTitle = "Bible School - CRC";

// Filters
$category = input('category');
$level = input('level');
$search = input('search');

// Build query
$where = ["c.status = 'published'"];
$params = [];

// Show global courses or congregation-specific
$where[] = "(c.scope = 'global' OR c.congregation_id = ?)";
$params[] = $primaryCong['id'] ?? 0;

if ($category) {
    $where[] = "c.category = ?";
    $params[] = $category;
}

if ($level) {
    $where[] = "c.level = ?";
    $params[] = $level;
}

if ($search) {
    $where[] = "(c.title LIKE ? OR c.description LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$whereClause = implode(' AND ', $where);

// Get courses
$courses = Database::fetchAll(
    "SELECT c.*,
            u.name as instructor_name,
            (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count,
            (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count,
            (SELECT id FROM enrollments WHERE course_id = c.id AND user_id = ?) as enrollment_id,
            (SELECT COUNT(*) FROM lesson_progress lp
             JOIN lessons l ON lp.lesson_id = l.id
             WHERE l.course_id = c.id AND lp.user_id = ? AND lp.completed_at IS NOT NULL) as completed_lessons
     FROM courses c
     LEFT JOIN users u ON c.instructor_id = u.id
     WHERE $whereClause
     ORDER BY c.featured DESC, c.created_at DESC",
    array_merge([$user['id'], $user['id']], $params)
);

// Get user's enrolled courses
$enrolledCourses = Database::fetchAll(
    "SELECT c.*, e.progress_percent, e.enrolled_at,
            (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count
     FROM enrollments e
     JOIN courses c ON e.course_id = c.id
     WHERE e.user_id = ?
     ORDER BY e.last_accessed_at DESC
     LIMIT 4",
    [$user['id']]
);

$categories = ['biblical_studies', 'theology', 'discipleship', 'leadership', 'evangelism', 'family', 'other'];
$levels = ['beginner', 'intermediate', 'advanced'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/learning/css/learning.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <!-- Header -->
            <div class="page-header">
                <div class="page-title">
                    <h1>Bible School</h1>
                    <p>Grow in faith through structured learning</p>
                </div>
            </div>

            <!-- Continue Learning -->
            <?php if ($enrolledCourses): ?>
                <section class="section">
                    <h2 class="section-title">Continue Learning</h2>
                    <div class="enrolled-grid">
                        <?php foreach ($enrolledCourses as $course): ?>
                            <a href="/learning/course.php?id=<?= $course['id'] ?>" class="enrolled-card">
                                <?php if ($course['thumbnail']): ?>
                                    <img src="<?= e($course['thumbnail']) ?>" alt="" class="enrolled-thumb">
                                <?php else: ?>
                                    <div class="enrolled-thumb placeholder">📚</div>
                                <?php endif; ?>
                                <div class="enrolled-info">
                                    <h3><?= e($course['title']) ?></h3>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?= $course['progress_percent'] ?>%"></div>
                                    </div>
                                    <span class="progress-text"><?= $course['progress_percent'] ?>% complete</span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- Filters -->
            <div class="filters-bar">
                <form class="search-form" method="get">
                    <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search courses...">
                    <button type="submit">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </button>
                </form>
                <div class="filter-group">
                    <select onchange="applyFilter('category', this.value)">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat ?>" <?= $category === $cat ? 'selected' : '' ?>>
                                <?= ucwords(str_replace('_', ' ', $cat)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select onchange="applyFilter('level', this.value)">
                        <option value="">All Levels</option>
                        <?php foreach ($levels as $lvl): ?>
                            <option value="<?= $lvl ?>" <?= $level === $lvl ? 'selected' : '' ?>>
                                <?= ucfirst($lvl) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Course Grid -->
            <section class="section">
                <h2 class="section-title">All Courses</h2>
                <?php if ($courses): ?>
                    <div class="courses-grid">
                        <?php foreach ($courses as $course): ?>
                            <div class="course-card <?= $course['featured'] ? 'featured' : '' ?>">
                                <?php if ($course['thumbnail']): ?>
                                    <img src="<?= e($course['thumbnail']) ?>" alt="" class="course-thumb">
                                <?php else: ?>
                                    <div class="course-thumb placeholder">
                                        <?= getCategoryIcon($course['category']) ?>
                                    </div>
                                <?php endif; ?>

                                <div class="course-content">
                                    <div class="course-meta">
                                        <span class="course-level <?= $course['level'] ?>"><?= ucfirst($course['level']) ?></span>
                                        <?php if ($course['featured']): ?>
                                            <span class="course-featured">⭐ Featured</span>
                                        <?php endif; ?>
                                    </div>

                                    <h3 class="course-title"><?= e($course['title']) ?></h3>
                                    <p class="course-desc"><?= e(truncate($course['description'], 100)) ?></p>

                                    <div class="course-stats">
                                        <span>📖 <?= $course['lesson_count'] ?> lessons</span>
                                        <span>👥 <?= $course['student_count'] ?> students</span>
                                    </div>

                                    <?php if ($course['instructor_name']): ?>
                                        <div class="course-instructor">
                                            By <?= e($course['instructor_name']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="course-actions">
                                        <?php if ($course['enrollment_id']): ?>
                                            <a href="/learning/course.php?id=<?= $course['id'] ?>" class="btn btn-secondary">Continue</a>
                                            <div class="enrolled-badge">✓ Enrolled</div>
                                        <?php else: ?>
                                            <a href="/learning/course.php?id=<?= $course['id'] ?>" class="btn btn-primary">View Course</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">📚</div>
                        <h3>No courses found</h3>
                        <p>Try adjusting your filters or check back later for new courses.</p>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <script>
        function applyFilter(name, value) {
            const url = new URL(window.location);
            if (value) {
                url.searchParams.set(name, value);
            } else {
                url.searchParams.delete(name);
            }
            window.location = url;
        }
    </script>
</body>
</html>
<?php

function getCategoryIcon($category) {
    $icons = [
        'biblical_studies' => '📖',
        'theology' => '⛪',
        'discipleship' => '🙏',
        'leadership' => '👑',
        'evangelism' => '🌍',
        'family' => '👨‍👩‍👧',
        'other' => '📚'
    ];
    return $icons[$category] ?? '📚';
}
