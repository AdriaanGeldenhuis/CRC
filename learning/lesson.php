<?php
/**
 * CRC Learning - Lesson View
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$lessonId = (int)($_GET['id'] ?? 0);

if (!$lessonId) {
    Response::redirect('/learning/');
}

// Get lesson with course info
$lesson = Database::fetchOne(
    "SELECT l.*, c.id as course_id, c.title as course_title
     FROM lessons l
     JOIN courses c ON l.course_id = c.id
     WHERE l.id = ?",
    [$lessonId]
);

if (!$lesson) {
    Response::redirect('/learning/');
}

// Check enrollment
$enrollment = Database::fetchOne(
    "SELECT * FROM enrollments WHERE course_id = ? AND user_id = ?",
    [$lesson['course_id'], $user['id']]
);

if (!$enrollment) {
    // Auto-enroll and continue
    Database::insert('enrollments', [
        'user_id' => $user['id'],
        'course_id' => $lesson['course_id'],
        'enrolled_at' => date('Y-m-d H:i:s'),
        'last_accessed_at' => date('Y-m-d H:i:s')
    ]);
}

// Get progress
$progress = Database::fetchOne(
    "SELECT * FROM lesson_progress WHERE lesson_id = ? AND user_id = ?",
    [$lessonId, $user['id']]
);

// Get all lessons for navigation
$allLessons = Database::fetchAll(
    "SELECT id, title, sort_order,
            (SELECT completed_at FROM lesson_progress WHERE lesson_id = lessons.id AND user_id = ?) as completed_at
     FROM lessons
     WHERE course_id = ?
     ORDER BY sort_order ASC, id ASC",
    [$user['id'], $lesson['course_id']]
);

// Find current position and prev/next
$currentIndex = 0;
$prevLesson = null;
$nextLesson = null;

foreach ($allLessons as $i => $l) {
    if ($l['id'] == $lessonId) {
        $currentIndex = $i;
        if ($i > 0) $prevLesson = $allLessons[$i - 1];
        if ($i < count($allLessons) - 1) $nextLesson = $allLessons[$i + 1];
        break;
    }
}

$pageTitle = e($lesson['title']) . " - " . e($lesson['course_title']);

// Mark as started if not already
if (!$progress) {
    Database::insert('lesson_progress', [
        'user_id' => $user['id'],
        'lesson_id' => $lessonId,
        'started_at' => date('Y-m-d H:i:s'),
        'created_at' => date('Y-m-d H:i:s')
    ]);
}

// Update last accessed
Database::update('enrollments', [
    'last_accessed_at' => date('Y-m-d H:i:s'),
    'current_lesson_id' => $lessonId
], 'course_id = ? AND user_id = ?', [$lesson['course_id'], $user['id']]);
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
    <!-- Lesson Header -->
    <header class="lesson-header">
        <a href="/learning/course.php?id=<?= $lesson['course_id'] ?>" class="back-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
            <?= e($lesson['course_title']) ?>
        </a>
        <div class="lesson-progress-bar">
            <?php foreach ($allLessons as $i => $l): ?>
                <div class="progress-dot <?= $l['completed_at'] ? 'completed' : '' ?> <?= $l['id'] == $lessonId ? 'active' : '' ?>"></div>
            <?php endforeach; ?>
        </div>
    </header>

    <main class="lesson-layout">
        <!-- Sidebar - Lesson List -->
        <aside class="lesson-sidebar">
            <h3>Course Content</h3>
            <div class="sidebar-lessons">
                <?php foreach ($allLessons as $i => $l): ?>
                    <a href="/learning/lesson.php?id=<?= $l['id'] ?>"
                       class="sidebar-lesson <?= $l['id'] == $lessonId ? 'active' : '' ?> <?= $l['completed_at'] ? 'completed' : '' ?>">
                        <span class="lesson-num"><?= $i + 1 ?></span>
                        <span class="lesson-title"><?= e($l['title']) ?></span>
                        <?php if ($l['completed_at']): ?>
                            <span class="lesson-check">✓</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="lesson-main">
            <div class="lesson-content-wrapper">
                <h1 class="lesson-title"><?= e($lesson['title']) ?></h1>

                <?php if ($lesson['duration_minutes']): ?>
                    <p class="lesson-meta">⏱️ <?= $lesson['duration_minutes'] ?> minutes</p>
                <?php endif; ?>

                <!-- Video Content -->
                <?php if ($lesson['video_url']): ?>
                    <div class="video-container">
                        <iframe src="<?= e($lesson['video_url']) ?>" allowfullscreen></iframe>
                    </div>
                <?php endif; ?>

                <!-- Text Content -->
                <?php if ($lesson['content']): ?>
                    <div class="lesson-text">
                        <?= nl2br(e($lesson['content'])) ?>
                    </div>
                <?php endif; ?>

                <!-- Resources -->
                <?php if ($lesson['resources']): ?>
                    <?php $resources = json_decode($lesson['resources'], true); ?>
                    <?php if ($resources): ?>
                        <div class="lesson-resources">
                            <h3>Resources</h3>
                            <ul>
                                <?php foreach ($resources as $resource): ?>
                                    <li>
                                        <a href="<?= e($resource['url']) ?>" target="_blank">
                                            📎 <?= e($resource['title']) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Mark Complete / Navigation -->
                <div class="lesson-actions">
                    <?php if (!$progress || !$progress['completed_at']): ?>
                        <button onclick="markComplete(<?= $lessonId ?>)" class="btn btn-primary btn-lg" id="complete-btn">
                            Mark as Complete
                        </button>
                    <?php else: ?>
                        <div class="completed-message">
                            ✓ Completed on <?= date('M j, Y', strtotime($progress['completed_at'])) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="lesson-navigation">
                    <?php if ($prevLesson): ?>
                        <a href="/learning/lesson.php?id=<?= $prevLesson['id'] ?>" class="nav-btn prev">
                            ← Previous Lesson
                        </a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>

                    <?php if ($nextLesson): ?>
                        <a href="/learning/lesson.php?id=<?= $nextLesson['id'] ?>" class="nav-btn next">
                            Next Lesson →
                        </a>
                    <?php else: ?>
                        <a href="/learning/course.php?id=<?= $lesson['course_id'] ?>" class="nav-btn next">
                            Finish Course
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        const lessonId = <?= $lessonId ?>;
        const courseId = <?= $lesson['course_id'] ?>;
        const nextLessonId = <?= $nextLesson ? $nextLesson['id'] : 'null' ?>;
    </script>
    <script src="/learning/js/learning.js"></script>
</body>
</html>
