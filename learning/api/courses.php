<?php
/**
 * CRC Learning Courses API
 * POST /learning/api/courses.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();
Response::requirePost();
CSRF::require();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
$action = input('action', 'list');

switch ($action) {
    case 'enroll':
        $courseId = (int)input('course_id');

        if (!$courseId) {
            Response::error('Course ID required');
        }

        // Verify course exists and is accessible
        $course = Database::fetchOne(
            "SELECT * FROM courses
             WHERE id = ? AND status = 'published'
             AND (scope = 'global' OR congregation_id = ?)",
            [$courseId, $primaryCong['id'] ?? 0]
        );

        if (!$course) {
            Response::error('Course not found');
        }

        // Check if already enrolled
        $existing = Database::fetchOne(
            "SELECT * FROM enrollments WHERE course_id = ? AND user_id = ?",
            [$courseId, $user['id']]
        );

        if ($existing) {
            Response::success(['enrollment_id' => $existing['id']], 'Already enrolled');
        }

        // Create enrollment
        $enrollmentId = Database::insert('enrollments', [
            'user_id' => $user['id'],
            'course_id' => $courseId,
            'enrolled_at' => date('Y-m-d H:i:s'),
            'progress_percent' => 0,
            'last_accessed_at' => date('Y-m-d H:i:s')
        ]);

        Response::success(['enrollment_id' => $enrollmentId], 'Successfully enrolled');
        break;

    case 'unenroll':
        $courseId = (int)input('course_id');

        if (!$courseId) {
            Response::error('Course ID required');
        }

        // Delete enrollment
        Database::delete(
            'enrollments',
            'course_id = ? AND user_id = ?',
            [$courseId, $user['id']]
        );

        // Delete lesson progress
        Database::query(
            "DELETE lp FROM lesson_progress lp
             JOIN lessons l ON lp.lesson_id = l.id
             WHERE l.course_id = ? AND lp.user_id = ?",
            [$courseId, $user['id']]
        );

        Response::success([], 'Unenrolled from course');
        break;

    case 'get':
        $courseId = (int)input('course_id');

        if (!$courseId) {
            Response::error('Course ID required');
        }

        $course = Database::fetchOne(
            "SELECT c.*, u.name as instructor_name
             FROM courses c
             LEFT JOIN users u ON c.instructor_id = u.id
             WHERE c.id = ? AND c.status = 'published'",
            [$courseId]
        );

        if (!$course) {
            Response::error('Course not found');
        }

        // Get lessons
        $lessons = Database::fetchAll(
            "SELECT l.id, l.title, l.type, l.duration_minutes, l.sort_order,
                    (SELECT completed_at FROM lesson_progress WHERE lesson_id = l.id AND user_id = ?) as completed_at
             FROM lessons l
             WHERE l.course_id = ?
             ORDER BY l.sort_order ASC",
            [$user['id'], $courseId]
        );

        $course['lessons'] = $lessons;

        Response::success(['course' => $course]);
        break;

    case 'list':
        $category = input('category');
        $level = input('level');
        $limit = min((int)input('limit', 20), 100);

        $where = ["c.status = 'published'", "(c.scope = 'global' OR c.congregation_id = ?)"];
        $params = [$primaryCong['id'] ?? 0];

        if ($category) {
            $where[] = "c.category = ?";
            $params[] = $category;
        }

        if ($level) {
            $where[] = "c.level = ?";
            $params[] = $level;
        }

        $whereClause = implode(' AND ', $where);

        $courses = Database::fetchAll(
            "SELECT c.id, c.title, c.description, c.category, c.level, c.thumbnail, c.featured,
                    (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count,
                    (SELECT id FROM enrollments WHERE course_id = c.id AND user_id = ?) as enrolled
             FROM courses c
             WHERE $whereClause
             ORDER BY c.featured DESC, c.created_at DESC
             LIMIT ?",
            array_merge([$user['id']], $params, [$limit])
        );

        Response::success(['courses' => $courses]);
        break;

    case 'my_courses':
        $courses = Database::fetchAll(
            "SELECT c.*, e.progress_percent, e.enrolled_at, e.last_accessed_at,
                    (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count
             FROM enrollments e
             JOIN courses c ON e.course_id = c.id
             WHERE e.user_id = ?
             ORDER BY e.last_accessed_at DESC",
            [$user['id']]
        );

        Response::success(['courses' => $courses]);
        break;

    default:
        Response::error('Invalid action');
}
