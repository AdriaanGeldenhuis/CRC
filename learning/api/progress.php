<?php
/**
 * CRC Learning Progress API
 * POST /learning/api/progress.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();
Response::requirePost();
CSRF::require();

$user = Auth::user();
$action = input('action', 'complete');

switch ($action) {
    case 'complete':
        $lessonId = (int)input('lesson_id');

        if (!$lessonId) {
            Response::error('Lesson ID required');
        }

        // Verify lesson exists and user is enrolled
        $lesson = Database::fetchOne(
            "SELECT l.*, c.id as course_id
             FROM lessons l
             JOIN courses c ON l.course_id = c.id
             WHERE l.id = ?",
            [$lessonId]
        );

        if (!$lesson) {
            Response::error('Lesson not found');
        }

        // Check enrollment
        $enrollment = Database::fetchOne(
            "SELECT * FROM enrollments WHERE course_id = ? AND user_id = ?",
            [$lesson['course_id'], $user['id']]
        );

        if (!$enrollment) {
            // Auto-enroll
            Database::insert('enrollments', [
                'user_id' => $user['id'],
                'course_id' => $lesson['course_id'],
                'enrolled_at' => date('Y-m-d H:i:s'),
                'progress_percent' => 0,
                'last_accessed_at' => date('Y-m-d H:i:s')
            ]);
        }

        // Check if already completed
        $progress = Database::fetchOne(
            "SELECT * FROM lesson_progress WHERE lesson_id = ? AND user_id = ?",
            [$lessonId, $user['id']]
        );

        if ($progress && $progress['completed_at']) {
            Response::success(['already_completed' => true], 'Already completed');
        }

        if ($progress) {
            // Update existing
            Database::update('lesson_progress', [
                'completed_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$progress['id']]);
        } else {
            // Create new
            Database::insert('lesson_progress', [
                'user_id' => $user['id'],
                'lesson_id' => $lessonId,
                'started_at' => date('Y-m-d H:i:s'),
                'completed_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }

        // Update course progress
        updateCourseProgress($lesson['course_id'], $user['id']);

        Response::success([], 'Lesson completed');
        break;

    case 'uncomplete':
        $lessonId = (int)input('lesson_id');

        if (!$lessonId) {
            Response::error('Lesson ID required');
        }

        $lesson = Database::fetchOne("SELECT course_id FROM lessons WHERE id = ?", [$lessonId]);

        if (!$lesson) {
            Response::error('Lesson not found');
        }

        Database::update('lesson_progress', [
            'completed_at' => null
        ], 'lesson_id = ? AND user_id = ?', [$lessonId, $user['id']]);

        updateCourseProgress($lesson['course_id'], $user['id']);

        Response::success([], 'Progress reset');
        break;

    case 'save_quiz':
        $lessonId = (int)input('lesson_id');
        $answers = $_POST['answers'] ?? [];
        $score = (int)input('score');

        if (!$lessonId) {
            Response::error('Lesson ID required');
        }

        // Get or create progress record
        $progress = Database::fetchOne(
            "SELECT * FROM lesson_progress WHERE lesson_id = ? AND user_id = ?",
            [$lessonId, $user['id']]
        );

        if ($progress) {
            Database::update('lesson_progress', [
                'quiz_answers' => json_encode($answers),
                'quiz_score' => $score,
                'completed_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$progress['id']]);
        } else {
            Database::insert('lesson_progress', [
                'user_id' => $user['id'],
                'lesson_id' => $lessonId,
                'quiz_answers' => json_encode($answers),
                'quiz_score' => $score,
                'started_at' => date('Y-m-d H:i:s'),
                'completed_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }

        // Update course progress
        $lesson = Database::fetchOne("SELECT course_id FROM lessons WHERE id = ?", [$lessonId]);
        if ($lesson) {
            updateCourseProgress($lesson['course_id'], $user['id']);
        }

        Response::success(['score' => $score], 'Quiz saved');
        break;

    case 'get':
        $courseId = (int)input('course_id');

        if (!$courseId) {
            Response::error('Course ID required');
        }

        $enrollment = Database::fetchOne(
            "SELECT * FROM enrollments WHERE course_id = ? AND user_id = ?",
            [$courseId, $user['id']]
        );

        $lessonProgress = Database::fetchAll(
            "SELECT lp.*, l.title as lesson_title
             FROM lesson_progress lp
             JOIN lessons l ON lp.lesson_id = l.id
             WHERE l.course_id = ? AND lp.user_id = ?
             ORDER BY l.sort_order ASC",
            [$courseId, $user['id']]
        );

        Response::success([
            'enrollment' => $enrollment,
            'lessons' => $lessonProgress
        ]);
        break;

    case 'stats':
        // User's learning stats
        $totalEnrolled = Database::fetchColumn(
            "SELECT COUNT(*) FROM enrollments WHERE user_id = ?",
            [$user['id']]
        );

        $completed = Database::fetchColumn(
            "SELECT COUNT(*) FROM enrollments WHERE user_id = ? AND progress_percent = 100",
            [$user['id']]
        );

        $totalLessons = Database::fetchColumn(
            "SELECT COUNT(DISTINCT lp.lesson_id)
             FROM lesson_progress lp
             JOIN lessons l ON lp.lesson_id = l.id
             JOIN enrollments e ON l.course_id = e.course_id AND e.user_id = lp.user_id
             WHERE lp.user_id = ? AND lp.completed_at IS NOT NULL",
            [$user['id']]
        );

        Response::success([
            'courses_enrolled' => $totalEnrolled,
            'courses_completed' => $completed,
            'lessons_completed' => $totalLessons
        ]);
        break;

    default:
        Response::error('Invalid action');
}

function updateCourseProgress($courseId, $userId) {
    // Count total and completed lessons
    $total = Database::fetchColumn(
        "SELECT COUNT(*) FROM lessons WHERE course_id = ?",
        [$courseId]
    );

    $completed = Database::fetchColumn(
        "SELECT COUNT(*) FROM lesson_progress lp
         JOIN lessons l ON lp.lesson_id = l.id
         WHERE l.course_id = ? AND lp.user_id = ? AND lp.completed_at IS NOT NULL",
        [$courseId, $userId]
    );

    $percent = $total > 0 ? round(($completed / $total) * 100) : 0;

    Database::update('enrollments', [
        'progress_percent' => $percent,
        'last_accessed_at' => date('Y-m-d H:i:s'),
        'completed_at' => $percent >= 100 ? date('Y-m-d H:i:s') : null
    ], 'course_id = ? AND user_id = ?', [$courseId, $userId]);
}
