<?php
/**
 * CRC Learning - Course Detail
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
$courseId = (int)($_GET['id'] ?? 0);

if (!$courseId) {
    Response::redirect('/learning/');
}

// Get course
$course = Database::fetchOne(
    "SELECT c.*, u.name as instructor_name,
            (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count,
            (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count
     FROM courses c
     LEFT JOIN users u ON c.instructor_id = u.id
     WHERE c.id = ?
     AND c.status = 'published'
     AND (c.scope = 'global' OR c.congregation_id = ?)",
    [$courseId, $primaryCong['id'] ?? 0]
);

if (!$course) {
    Response::redirect('/learning/');
}

$pageTitle = e($course['title']) . " - Bible School";

// Get enrollment
$enrollment = Database::fetchOne(
    "SELECT * FROM enrollments WHERE course_id = ? AND user_id = ?",
    [$courseId, $user['id']]
);

// Get lessons
$lessons = Database::fetchAll(
    "SELECT l.*,
            (SELECT completed_at FROM lesson_progress WHERE lesson_id = l.id AND user_id = ?) as completed_at
     FROM lessons l
     WHERE l.course_id = ?
     ORDER BY l.sort_order ASC, l.id ASC",
    [$user['id'], $courseId]
);

// Calculate progress
$completedCount = 0;
$lastCompletedLesson = null;
foreach ($lessons as $lesson) {
    if ($lesson['completed_at']) {
        $completedCount++;
        $lastCompletedLesson = $lesson;
    }
}
$progressPercent = count($lessons) > 0 ? round(($completedCount / count($lessons)) * 100) : 0;

// Find next lesson
$nextLesson = null;
foreach ($lessons as $lesson) {
    if (!$lesson['completed_at']) {
        $nextLesson = $lesson;
        break;
    }
}

// Parse requirements/outcomes
$requirements = $course['requirements'] ? json_decode($course['requirements'], true) : [];
$outcomes = $course['learning_outcomes'] ? json_decode($course['learning_outcomes'], true) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/learning/css/learning.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <!-- Course Header -->
        <div class="course-header">
            <div class="container">
                <a href="/learning/" class="back-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                    Back to Courses
                </a>

                <div class="course-header-content">
                    <div class="course-header-info">
                        <div class="course-badges">
                            <span class="badge level-<?= $course['level'] ?>"><?= ucfirst($course['level']) ?></span>
                            <span class="badge category"><?= ucwords(str_replace('_', ' ', $course['category'])) ?></span>
                        </div>
                        <h1><?= e($course['title']) ?></h1>
                        <p class="course-description"><?= e($course['description']) ?></p>

                        <div class="course-meta-row">
                            <?php if ($course['instructor_name']): ?>
                                <span class="meta-item">👤 <?= e($course['instructor_name']) ?></span>
                            <?php endif; ?>
                            <span class="meta-item">📖 <?= $course['lesson_count'] ?> lessons</span>
                            <span class="meta-item">👥 <?= $course['student_count'] ?> students</span>
                            <?php if ($course['duration_hours']): ?>
                                <span class="meta-item">⏱️ <?= $course['duration_hours'] ?> hours</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="course-header-action">
                        <?php if ($enrollment): ?>
                            <div class="progress-card">
                                <div class="progress-circle" style="--progress: <?= $progressPercent ?>%">
                                    <span><?= $progressPercent ?>%</span>
                                </div>
                                <p>Course Progress</p>
                                <?php if ($nextLesson): ?>
                                    <a href="/learning/lesson.php?id=<?= $nextLesson['id'] ?>" class="btn btn-primary">
                                        Continue Learning
                                    </a>
                                <?php else: ?>
                                    <span class="completed-badge">✓ Course Completed</span>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <button onclick="enrollCourse(<?= $courseId ?>)" class="btn btn-primary btn-lg">
                                Enroll Now - Free
                            </button>
                            <p class="enroll-note">Start learning immediately</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <div class="course-layout">
                <!-- Main Content -->
                <div class="course-main">
                    <!-- Learning Outcomes -->
                    <?php if ($outcomes): ?>
                        <section class="content-section">
                            <h2>What You'll Learn</h2>
                            <ul class="outcomes-list">
                                <?php foreach ($outcomes as $outcome): ?>
                                    <li>✓ <?= e($outcome) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                    <?php endif; ?>

                    <!-- Course Content -->
                    <section class="content-section">
                        <h2>Course Content</h2>
                        <div class="lessons-list">
                            <?php foreach ($lessons as $index => $lesson):
                                $isLocked = !$enrollment && $index > 0;
                                $isCompleted = !empty($lesson['completed_at']);
                            ?>
                                <div class="lesson-item <?= $isCompleted ? 'completed' : '' ?> <?= $isLocked ? 'locked' : '' ?>">
                                    <div class="lesson-number"><?= $index + 1 ?></div>
                                    <div class="lesson-info">
                                        <h4><?= e($lesson['title']) ?></h4>
                                        <div class="lesson-meta">
                                            <?php if ($lesson['duration_minutes']): ?>
                                                <span>⏱️ <?= $lesson['duration_minutes'] ?> min</span>
                                            <?php endif; ?>
                                            <?php if ($lesson['type']): ?>
                                                <span><?= getLessonTypeIcon($lesson['type']) ?> <?= ucfirst($lesson['type']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="lesson-status">
                                        <?php if ($isLocked): ?>
                                            <span class="status-locked">🔒</span>
                                        <?php elseif ($isCompleted): ?>
                                            <span class="status-completed">✓</span>
                                        <?php else: ?>
                                            <a href="/learning/lesson.php?id=<?= $lesson['id'] ?>" class="btn btn-sm">Start</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <!-- Requirements -->
                    <?php if ($requirements): ?>
                        <section class="content-section">
                            <h2>Requirements</h2>
                            <ul class="requirements-list">
                                <?php foreach ($requirements as $req): ?>
                                    <li><?= e($req) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <aside class="course-sidebar">
                    <?php if ($course['thumbnail']): ?>
                        <img src="<?= e($course['thumbnail']) ?>" alt="" class="course-image">
                    <?php endif; ?>

                    <div class="sidebar-card">
                        <h3>Course Details</h3>
                        <dl class="details-list">
                            <dt>Level</dt>
                            <dd><?= ucfirst($course['level']) ?></dd>
                            <dt>Category</dt>
                            <dd><?= ucwords(str_replace('_', ' ', $course['category'])) ?></dd>
                            <dt>Lessons</dt>
                            <dd><?= $course['lesson_count'] ?></dd>
                            <?php if ($course['duration_hours']): ?>
                                <dt>Total Duration</dt>
                                <dd><?= $course['duration_hours'] ?> hours</dd>
                            <?php endif; ?>
                            <dt>Language</dt>
                            <dd>English</dd>
                        </dl>
                    </div>

                    <?php if ($course['instructor_name']): ?>
                        <div class="sidebar-card instructor-card">
                            <h3>Instructor</h3>
                            <div class="instructor-info">
                                <div class="instructor-avatar">
                                    <?= strtoupper(substr($course['instructor_name'], 0, 1)) ?>
                                </div>
                                <span><?= e($course['instructor_name']) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </aside>
            </div>
        </div>
    </main>

    <script src="/learning/js/learning.js"></script>
</body>
</html>
<?php

function getLessonTypeIcon($type) {
    $icons = [
        'video' => '🎥',
        'text' => '📄',
        'quiz' => '❓',
        'assignment' => '📝',
        'discussion' => '💬'
    ];
    return $icons[$type] ?? '📖';
}
