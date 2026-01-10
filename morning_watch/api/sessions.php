<?php
/**
 * CRC Morning Watch Sessions API
 * POST /morning_watch/api/sessions.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();
Response::requirePost();
CSRF::require();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
$action = input('action', 'list');

switch ($action) {
    case 'today':
        $session = Database::fetchOne(
            "SELECT ms.*, u.name as author_name
             FROM morning_sessions ms
             LEFT JOIN users u ON ms.created_by = u.id
             WHERE ms.session_date = CURDATE()
             AND (ms.scope = 'global' OR ms.congregation_id = ?)
             AND ms.published_at IS NOT NULL
             ORDER BY ms.scope = 'congregation' DESC
             LIMIT 1",
            [$primaryCong['id'] ?? 0]
        );

        Response::success(['session' => $session]);
        break;

    case 'get':
        $sessionId = (int)input('session_id');

        if (!$sessionId) {
            Response::error('Session ID required');
        }

        $session = Database::fetchOne(
            "SELECT ms.*, u.name as author_name
             FROM morning_sessions ms
             LEFT JOIN users u ON ms.created_by = u.id
             WHERE ms.id = ?
             AND (ms.scope = 'global' OR ms.congregation_id = ?)
             AND ms.published_at IS NOT NULL",
            [$sessionId, $primaryCong['id'] ?? 0]
        );

        if (!$session) {
            Response::error('Session not found');
        }

        Response::success(['session' => $session]);
        break;

    case 'list':
        $month = (int)input('month', date('n'));
        $year = (int)input('year', date('Y'));
        $limit = min((int)input('limit', 31), 100);

        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        $sessions = Database::fetchAll(
            "SELECT ms.id, ms.session_date, ms.title, ms.scripture_ref, ms.theme, ms.scope,
                    (SELECT 1 FROM morning_watch_entries WHERE user_id = ? AND session_id = ms.id LIMIT 1) as completed
             FROM morning_sessions ms
             WHERE ms.session_date BETWEEN ? AND ?
             AND (ms.scope = 'global' OR ms.congregation_id = ?)
             AND ms.published_at IS NOT NULL
             ORDER BY ms.session_date DESC
             LIMIT ?",
            [$user['id'], $startDate, $endDate, $primaryCong['id'] ?? 0, $limit]
        );

        Response::success(['sessions' => $sessions]);
        break;

    case 'create':
        // Admin only - create new session
        if (!Auth::isCongregationAdmin($primaryCong['id']) && Auth::globalRole() !== 'super_admin') {
            Response::forbidden('Admin access required');
        }

        $sessionDate = input('session_date');
        $title = input('title');
        $theme = input('theme');
        $scriptureRef = input('scripture_ref');
        $scriptureText = input('scripture_text');
        $versionCode = input('version_code', 'KJV');
        $devotional = input('devotional');
        $prayerPoints = $_POST['prayer_points'] ?? [];
        $scope = input('scope', 'congregation');

        if (!$sessionDate || !$title || !$scriptureRef || !$devotional) {
            Response::error('Date, title, scripture, and devotional are required');
        }

        // Super admin can create global sessions
        if ($scope === 'global' && Auth::globalRole() !== 'super_admin') {
            $scope = 'congregation';
        }

        $sessionId = Database::insert('morning_sessions', [
            'scope' => $scope,
            'congregation_id' => $scope === 'congregation' ? $primaryCong['id'] : null,
            'session_date' => $sessionDate,
            'title' => $title,
            'theme' => $theme,
            'scripture_ref' => $scriptureRef,
            'scripture_text' => $scriptureText,
            'version_code' => $versionCode,
            'devotional' => $devotional,
            'prayer_points' => json_encode($prayerPoints),
            'created_by' => $user['id'],
            'published_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        Response::success(['id' => $sessionId], 'Session created');
        break;

    case 'update':
        $sessionId = (int)input('session_id');

        if (!$sessionId) {
            Response::error('Session ID required');
        }

        // Check permission
        $session = Database::fetchOne("SELECT * FROM morning_sessions WHERE id = ?", [$sessionId]);

        if (!$session) {
            Response::error('Session not found');
        }

        $canEdit = ($session['created_by'] == $user['id']) ||
                   ($session['congregation_id'] && Auth::isCongregationAdmin($session['congregation_id'])) ||
                   (Auth::globalRole() === 'super_admin');

        if (!$canEdit) {
            Response::forbidden('Permission denied');
        }

        $title = input('title');
        $theme = input('theme');
        $scriptureRef = input('scripture_ref');
        $scriptureText = input('scripture_text');
        $devotional = input('devotional');
        $prayerPoints = $_POST['prayer_points'] ?? [];

        Database::update('morning_sessions', [
            'title' => $title,
            'theme' => $theme,
            'scripture_ref' => $scriptureRef,
            'scripture_text' => $scriptureText,
            'devotional' => $devotional,
            'prayer_points' => json_encode($prayerPoints),
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$sessionId]);

        Response::success([], 'Session updated');
        break;

    default:
        Response::error('Invalid action');
}
