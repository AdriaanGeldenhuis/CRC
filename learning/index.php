<?php
/**
 * CRC Learning - Dashboard Layout
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
$pageTitle = "Bible School - CRC";

$enrolledCourses = [];
$courses = [];
$completedCourses = 0;

try {
    $enrolledCourses = Database::fetchAll(
        "SELECT c.*, e.progress_percent, (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count
         FROM enrollments e JOIN courses c ON e.course_id = c.id
         WHERE e.user_id = ? ORDER BY e.last_accessed_at DESC LIMIT 5",
        [$user['id']]
    ) ?: [];
} catch (Exception $e) {}

try {
    $completedCourses = Database::fetchColumn(
        "SELECT COUNT(*) FROM enrollments WHERE user_id = ? AND progress_percent = 100",
        [$user['id']]
    ) ?: 0;
} catch (Exception $e) {}

try {
    $courses = Database::fetchAll(
        "SELECT c.*, (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count
         FROM courses c WHERE c.status = 'published' AND (c.scope = 'global' OR c.congregation_id = ?)
         ORDER BY c.featured DESC, c.created_at DESC LIMIT 5",
        [$primaryCong['id'] ?? 0]
    ) ?: [];
} catch (Exception $e) {}
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
    <script>
        (function() {
            var theme = localStorage.getItem('theme');
            if (theme === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
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
                <!-- Learning Progress Card (Featured) -->
                <div class="dashboard-card morning-watch-card">
                    <div class="card-header">
                        <h2>Your Progress</h2>
                        <span class="streak-badge"><?= count($enrolledCourses) ?> enrolled</span>
                    </div>
                    <div class="morning-watch-preview">
                        <?php if ($enrolledCourses): ?>
                            <h3><?= e($enrolledCourses[0]['title']) ?></h3>
                            <p class="scripture-ref"><?= $enrolledCourses[0]['progress_percent'] ?? 0 ?>% complete - <?= $enrolledCourses[0]['lesson_count'] ?> lessons</p>
                            <a href="/learning/course.php?id=<?= $enrolledCourses[0]['id'] ?>" class="btn btn-primary">Continue Learning</a>
                        <?php else: ?>
                            <h3>Start Learning</h3>
                            <p class="scripture-ref">Browse courses and start your journey</p>
                            <a href="/learning/browse.php" class="btn btn-primary">Browse Courses</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-card quick-actions-card">
                    <h2>Categories</h2>
                    <div class="quick-actions-grid">
                        <a href="/learning/?category=biblical_studies" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                                </svg>
                            </div>
                            <span>Biblical</span>
                        </a>
                        <a href="/learning/?category=theology" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                </svg>
                            </div>
                            <span>Theology</span>
                        </a>
                        <a href="/learning/?category=discipleship" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                            </div>
                            <span>Discipleship</span>
                        </a>
                        <a href="/learning/?category=leadership" class="quick-action">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                                </svg>
                            </div>
                            <span>Leadership</span>
                        </a>
                    </div>
                </div>

                <!-- Continue Learning -->
                <div class="dashboard-card events-card">
                    <div class="card-header">
                        <h2>Continue Learning</h2>
                        <a href="/learning/my-courses.php" class="view-all-link">View All</a>
                    </div>
                    <?php if ($enrolledCourses): ?>
                        <div class="events-list">
                            <?php foreach (array_slice($enrolledCourses, 0, 3) as $course): ?>
                                <a href="/learning/course.php?id=<?= $course['id'] ?>" class="event-item">
                                    <div class="event-date">
                                        <span class="event-day"><?= $course['progress_percent'] ?? 0 ?></span>
                                        <span class="event-month">%</span>
                                    </div>
                                    <div class="event-info">
                                        <h4><?= e(truncate($course['title'], 30)) ?></h4>
                                        <p><?= $course['lesson_count'] ?> lessons</p>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-content">
                            <p>No courses enrolled yet</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recommended Courses -->
                <div class="dashboard-card posts-card">
                    <div class="card-header">
                        <h2>Recommended</h2>
                        <a href="/learning/browse.php" class="view-all-link">Browse All</a>
                    </div>
                    <?php if ($courses): ?>
                        <div class="posts-list">
                            <?php foreach ($courses as $course): ?>
                                <a href="/learning/course.php?id=<?= $course['id'] ?>" class="post-item">
                                    <div class="post-author">
                                        <div class="author-avatar-placeholder">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                                            </svg>
                                        </div>
                                        <span><?= e($course['title']) ?></span>
                                    </div>
                                    <p class="post-content"><?= $course['lesson_count'] ?> lessons</p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-content">
                            <p>No courses available yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
