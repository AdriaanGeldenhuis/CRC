<?php
/**
 * CRC Learning - Dashboard Layout
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
$pageTitle = "Bible School - CRC";

$courses = [];
$enrolledCourses = [];
$totalCourses = 0;
$completedCourses = 0;

// Get user's enrolled courses
try {
    $enrolledCourses = Database::fetchAll(
        "SELECT c.*, e.progress_percent, e.enrolled_at,
                (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count
         FROM enrollments e
         JOIN courses c ON e.course_id = c.id
         WHERE e.user_id = ?
         ORDER BY e.last_accessed_at DESC LIMIT 4",
        [$user['id']]
    ) ?: [];
} catch (Exception $e) {}

// Get completed courses count
try {
    $completedCourses = Database::fetchColumn(
        "SELECT COUNT(*) FROM enrollments WHERE user_id = ? AND progress_percent = 100",
        [$user['id']]
    ) ?: 0;
} catch (Exception $e) {}

// Get featured/recommended courses
try {
    $courses = Database::fetchAll(
        "SELECT c.*, u.name as instructor_name,
                (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count,
                (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count
         FROM courses c
         LEFT JOIN users u ON c.instructor_id = u.id
         WHERE c.status = 'published' AND (c.scope = 'global' OR c.congregation_id = ?)
         ORDER BY c.featured DESC, c.created_at DESC LIMIT 4",
        [$primaryCong['id'] ?? 0]
    ) ?: [];
} catch (Exception $e) {}

// Get categories
$categories = [
    ['name' => 'biblical_studies', 'icon' => '📖', 'label' => 'Biblical Studies'],
    ['name' => 'theology', 'icon' => '⛪', 'label' => 'Theology'],
    ['name' => 'discipleship', 'icon' => '🙏', 'label' => 'Discipleship'],
    ['name' => 'leadership', 'icon' => '👑', 'label' => 'Leadership'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/home/css/home.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .learning-card {
            background: linear-gradient(135deg, #F59E0B 0%, #FBBF24 100%);
            color: var(--white);
        }
        .learning-card .card-header h2 { color: var(--white); }
        .stats-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .stat-box {
            flex: 1;
            background: rgba(255,255,255,0.15);
            padding: 1rem;
            border-radius: var(--radius);
            text-align: center;
        }
        .stat-box .value { font-size: 1.5rem; font-weight: 700; }
        .stat-box .label { font-size: 0.75rem; opacity: 0.9; }
        .category-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }
        .category-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem;
            background: var(--gray-50);
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--gray-700);
            transition: var(--transition);
        }
        .category-item:hover { background: var(--primary); color: white; }
        .category-icon { font-size: 1.5rem; }
        .course-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .course-item {
            display: flex;
            gap: 1rem;
            padding: 0.75rem;
            background: var(--gray-50);
            border-radius: var(--radius);
            text-decoration: none;
            transition: var(--transition);
        }
        .course-item:hover { background: var(--gray-100); }
        .course-thumb {
            width: 60px;
            height: 60px;
            background: var(--primary);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        .course-info h4 { font-size: 0.875rem; color: var(--gray-800); margin-bottom: 0.25rem; }
        .course-info p { font-size: 0.75rem; color: var(--gray-500); }
        .progress-bar {
            height: 6px;
            background: var(--gray-200);
            border-radius: 3px;
            overflow: hidden;
            margin-top: 0.5rem;
        }
        .progress-fill {
            height: 100%;
            background: var(--primary);
            border-radius: 3px;
        }
        .enrolled-item { border-left: 3px solid var(--primary); }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <section class="welcome-section">
                <div class="welcome-content">
                    <h1>Bible School</h1>
                    <p>Grow in faith through structured learning</p>
                </div>
            </section>

            <div class="dashboard-grid">
                <!-- Stats Card -->
                <div class="dashboard-card learning-card">
                    <div class="card-header">
                        <h2>Your Progress</h2>
                    </div>
                    <div class="stats-row">
                        <div class="stat-box">
                            <div class="value"><?= count($enrolledCourses) ?></div>
                            <div class="label">Enrolled</div>
                        </div>
                        <div class="stat-box">
                            <div class="value"><?= $completedCourses ?></div>
                            <div class="label">Completed</div>
                        </div>
                    </div>
                    <a href="/learning/my-courses.php" class="btn" style="width: 100%; background: white; color: #F59E0B;">View My Courses</a>
                </div>

                <!-- Categories -->
                <div class="dashboard-card">
                    <h2 style="margin-bottom: 1rem;">Categories</h2>
                    <div class="category-grid">
                        <?php foreach ($categories as $cat): ?>
                            <a href="/learning/?category=<?= $cat['name'] ?>" class="category-item">
                                <span class="category-icon"><?= $cat['icon'] ?></span>
                                <span><?= $cat['label'] ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Continue Learning -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>Continue Learning</h2>
                        <a href="/learning/my-courses.php" class="view-all-link">View All</a>
                    </div>
                    <?php if ($enrolledCourses): ?>
                        <div class="course-list">
                            <?php foreach (array_slice($enrolledCourses, 0, 3) as $course): ?>
                                <a href="/learning/course.php?id=<?= $course['id'] ?>" class="course-item enrolled-item">
                                    <div class="course-thumb">📚</div>
                                    <div class="course-info" style="flex: 1;">
                                        <h4><?= e($course['title']) ?></h4>
                                        <p><?= $course['lesson_count'] ?> lessons</p>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?= $course['progress_percent'] ?>%"></div>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 2rem; color: var(--gray-500);">
                            <p>No courses enrolled yet</p>
                            <a href="/learning/browse.php" class="btn btn-primary" style="margin-top: 0.5rem;">Browse Courses</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recommended Courses -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>Recommended</h2>
                        <a href="/learning/browse.php" class="view-all-link">Browse All</a>
                    </div>
                    <?php if ($courses): ?>
                        <div class="course-list">
                            <?php foreach (array_slice($courses, 0, 3) as $course): ?>
                                <a href="/learning/course.php?id=<?= $course['id'] ?>" class="course-item">
                                    <div class="course-thumb">📖</div>
                                    <div class="course-info">
                                        <h4><?= e($course['title']) ?></h4>
                                        <p><?= $course['lesson_count'] ?> lessons • <?= $course['student_count'] ?> students</p>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 2rem; color: var(--gray-500);">
                            <p>No courses available yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
