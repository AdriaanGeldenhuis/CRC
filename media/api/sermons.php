<?php
/**
 * CRC Sermons API
 * POST /media/api/sermons.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();
Response::requirePost();
CSRF::require();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
$congId = $primaryCong ? $primaryCong['id'] : 0;

$action = input('action', 'list');

switch ($action) {
    case 'list':
        $page = max(1, (int)input('page', 1));
        $perPage = min(50, max(10, (int)input('per_page', 12)));
        $offset = ($page - 1) * $perPage;

        $search = trim(input('search', ''));
        $category = input('category', '');
        $seriesId = (int)input('series_id', 0);
        $speaker = input('speaker', '');

        $whereClause = "WHERE s.status = 'published' AND (s.congregation_id = ? OR s.congregation_id IS NULL)";
        $params = [$congId];

        if ($search) {
            $whereClause .= " AND (s.title LIKE ? OR s.description LIKE ? OR s.speaker LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if ($category) {
            $whereClause .= " AND s.category = ?";
            $params[] = $category;
        }

        if ($seriesId) {
            $whereClause .= " AND s.series_id = ?";
            $params[] = $seriesId;
        }

        if ($speaker) {
            $whereClause .= " AND s.speaker = ?";
            $params[] = $speaker;
        }

        $sermons = Database::fetchAll(
            "SELECT s.*, u.name as speaker_name, ss.name as series_name
             FROM sermons s
             LEFT JOIN users u ON s.speaker_user_id = u.id
             LEFT JOIN sermon_series ss ON s.series_id = ss.id
             $whereClause
             ORDER BY s.sermon_date DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        $total = Database::fetchColumn(
            "SELECT COUNT(*) FROM sermons s $whereClause",
            $params
        );

        Response::success([
            'sermons' => $sermons,
            'total' => (int)$total,
            'page' => $page,
            'per_page' => $perPage
        ]);
        break;

    case 'get':
        $sermonId = (int)input('sermon_id');

        if (!$sermonId) {
            Response::error('Sermon ID required');
        }

        $sermon = Database::fetchOne(
            "SELECT s.*, u.name as speaker_name, ss.name as series_name
             FROM sermons s
             LEFT JOIN users u ON s.speaker_user_id = u.id
             LEFT JOIN sermon_series ss ON s.series_id = ss.id
             WHERE s.id = ? AND s.status = 'published' AND (s.congregation_id = ? OR s.congregation_id IS NULL)",
            [$sermonId, $congId]
        );

        if (!$sermon) {
            Response::error('Sermon not found');
        }

        // Increment view count
        Database::query(
            "UPDATE sermons SET view_count = view_count + 1 WHERE id = ?",
            [$sermonId]
        );

        Response::success(['sermon' => $sermon]);
        break;

    case 'save':
        $sermonId = (int)input('sermon_id');

        if (!$sermonId) {
            Response::error('Sermon ID required');
        }

        // Check if already saved
        $existing = Database::fetchColumn(
            "SELECT id FROM user_saved_sermons WHERE user_id = ? AND sermon_id = ?",
            [$user['id'], $sermonId]
        );

        if ($existing) {
            Response::error('Sermon already saved');
        }

        Database::insert('user_saved_sermons', [
            'user_id' => $user['id'],
            'sermon_id' => $sermonId,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        Response::success([], 'Sermon saved');
        break;

    case 'unsave':
        $sermonId = (int)input('sermon_id');

        if (!$sermonId) {
            Response::error('Sermon ID required');
        }

        Database::delete(
            'user_saved_sermons',
            'user_id = ? AND sermon_id = ?',
            [$user['id'], $sermonId]
        );

        Response::success([], 'Sermon removed from saved');
        break;

    case 'get_saved':
        $page = max(1, (int)input('page', 1));
        $perPage = 12;
        $offset = ($page - 1) * $perPage;

        $sermons = Database::fetchAll(
            "SELECT s.*, u.name as speaker_name, ss.name as series_name
             FROM user_saved_sermons uss
             JOIN sermons s ON uss.sermon_id = s.id
             LEFT JOIN users u ON s.speaker_user_id = u.id
             LEFT JOIN sermon_series ss ON s.series_id = ss.id
             WHERE uss.user_id = ? AND s.status = 'published'
             ORDER BY uss.created_at DESC
             LIMIT $perPage OFFSET $offset",
            [$user['id']]
        );

        $total = Database::fetchColumn(
            "SELECT COUNT(*) FROM user_saved_sermons uss
             JOIN sermons s ON uss.sermon_id = s.id
             WHERE uss.user_id = ? AND s.status = 'published'",
            [$user['id']]
        );

        Response::success([
            'sermons' => $sermons,
            'total' => (int)$total
        ]);
        break;

    case 'save_notes':
        $sermonId = (int)input('sermon_id');
        $content = trim(input('content', ''));

        if (!$sermonId) {
            Response::error('Sermon ID required');
        }

        // Check if notes exist
        $existing = Database::fetchOne(
            "SELECT id FROM sermon_notes WHERE user_id = ? AND sermon_id = ?",
            [$user['id'], $sermonId]
        );

        if ($existing) {
            Database::update('sermon_notes', [
                'content' => $content,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$existing['id']]);
        } else {
            Database::insert('sermon_notes', [
                'user_id' => $user['id'],
                'sermon_id' => $sermonId,
                'content' => $content,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }

        Response::success([], 'Notes saved');
        break;

    case 'get_notes':
        $sermonId = (int)input('sermon_id');

        if (!$sermonId) {
            Response::error('Sermon ID required');
        }

        $notes = Database::fetchOne(
            "SELECT * FROM sermon_notes WHERE user_id = ? AND sermon_id = ?",
            [$user['id'], $sermonId]
        );

        Response::success(['notes' => $notes]);
        break;

    case 'get_series':
        $series = Database::fetchAll(
            "SELECT ss.*,
                    (SELECT COUNT(*) FROM sermons WHERE series_id = ss.id AND status = 'published') as sermon_count
             FROM sermon_series ss
             WHERE ss.congregation_id = ? OR ss.congregation_id IS NULL
             ORDER BY ss.created_at DESC",
            [$congId]
        );

        Response::success(['series' => $series]);
        break;

    case 'get_categories':
        $categories = Database::fetchAll(
            "SELECT DISTINCT category, COUNT(*) as count FROM sermons
             WHERE status = 'published' AND category IS NOT NULL AND category != ''
             AND (congregation_id = ? OR congregation_id IS NULL)
             GROUP BY category ORDER BY category ASC",
            [$congId]
        );

        Response::success(['categories' => $categories]);
        break;

    case 'get_speakers':
        $speakers = Database::fetchAll(
            "SELECT DISTINCT speaker, COUNT(*) as count FROM sermons
             WHERE status = 'published' AND speaker IS NOT NULL AND speaker != ''
             AND (congregation_id = ? OR congregation_id IS NULL)
             GROUP BY speaker ORDER BY speaker ASC",
            [$congId]
        );

        Response::success(['speakers' => $speakers]);
        break;

    case 'track_progress':
        $sermonId = (int)input('sermon_id');
        $position = (int)input('position');
        $completed = input('completed') === '1';

        if (!$sermonId) {
            Response::error('Sermon ID required');
        }

        $existing = Database::fetchOne(
            "SELECT id FROM sermon_progress WHERE user_id = ? AND sermon_id = ?",
            [$user['id'], $sermonId]
        );

        if ($existing) {
            Database::update('sermon_progress', [
                'position' => $position,
                'completed' => $completed ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$existing['id']]);
        } else {
            Database::insert('sermon_progress', [
                'user_id' => $user['id'],
                'sermon_id' => $sermonId,
                'position' => $position,
                'completed' => $completed ? 1 : 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }

        Response::success([], 'Progress saved');
        break;

    default:
        Response::error('Invalid action');
}
