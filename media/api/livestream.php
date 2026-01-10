<?php
/**
 * CRC Livestream API
 * POST /media/api/livestream.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();
Response::requirePost();
CSRF::require();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
$congId = $primaryCong ? $primaryCong['id'] : 0;

$action = input('action', 'get_active');

switch ($action) {
    case 'get_active':
        $livestream = Database::fetchOne(
            "SELECT l.*, c.name as congregation_name
             FROM livestreams l
             LEFT JOIN congregations c ON l.congregation_id = c.id
             WHERE l.congregation_id = ? AND l.status = 'live'
             ORDER BY l.started_at DESC LIMIT 1",
            [$congId]
        );

        Response::success(['livestream' => $livestream]);
        break;

    case 'get':
        $streamId = (int)input('stream_id');

        if (!$streamId) {
            Response::error('Stream ID required');
        }

        $livestream = Database::fetchOne(
            "SELECT l.*, c.name as congregation_name
             FROM livestreams l
             LEFT JOIN congregations c ON l.congregation_id = c.id
             WHERE l.id = ? AND (l.congregation_id = ? OR l.congregation_id IS NULL)",
            [$streamId, $congId]
        );

        if (!$livestream) {
            Response::error('Livestream not found');
        }

        Response::success(['livestream' => $livestream]);
        break;

    case 'get_upcoming':
        $limit = min(20, max(1, (int)input('limit', 5)));

        $streams = Database::fetchAll(
            "SELECT * FROM livestreams
             WHERE congregation_id = ? AND status = 'scheduled' AND scheduled_at > NOW()
             ORDER BY scheduled_at ASC LIMIT $limit",
            [$congId]
        );

        Response::success(['streams' => $streams]);
        break;

    case 'get_recordings':
        $page = max(1, (int)input('page', 1));
        $perPage = 12;
        $offset = ($page - 1) * $perPage;

        $recordings = Database::fetchAll(
            "SELECT * FROM livestreams
             WHERE congregation_id = ? AND status = 'ended' AND recording_url IS NOT NULL
             ORDER BY ended_at DESC LIMIT $perPage OFFSET $offset",
            [$congId]
        );

        $total = Database::fetchColumn(
            "SELECT COUNT(*) FROM livestreams
             WHERE congregation_id = ? AND status = 'ended' AND recording_url IS NOT NULL",
            [$congId]
        );

        Response::success([
            'recordings' => $recordings,
            'total' => (int)$total
        ]);
        break;

    case 'set_reminder':
        $streamId = (int)input('stream_id');

        if (!$streamId) {
            Response::error('Stream ID required');
        }

        // Verify stream exists and is scheduled
        $stream = Database::fetchOne(
            "SELECT * FROM livestreams WHERE id = ? AND status = 'scheduled'",
            [$streamId]
        );

        if (!$stream) {
            Response::error('Scheduled stream not found');
        }

        // Check if reminder already set
        $existing = Database::fetchColumn(
            "SELECT id FROM livestream_reminders WHERE user_id = ? AND livestream_id = ?",
            [$user['id'], $streamId]
        );

        if ($existing) {
            Response::error('Reminder already set');
        }

        Database::insert('livestream_reminders', [
            'user_id' => $user['id'],
            'livestream_id' => $streamId,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Create notification for reminder
        $reminderTime = date('Y-m-d H:i:s', strtotime($stream['scheduled_at']) - 900); // 15 min before
        Database::insert('scheduled_notifications', [
            'user_id' => $user['id'],
            'type' => 'livestream_reminder',
            'title' => 'Livestream Starting Soon',
            'message' => $stream['title'] . ' starts in 15 minutes',
            'link' => '/media/livestream.php?id=' . $streamId,
            'send_at' => $reminderTime,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        Response::success([], 'Reminder set');
        break;

    case 'cancel_reminder':
        $streamId = (int)input('stream_id');

        if (!$streamId) {
            Response::error('Stream ID required');
        }

        Database::delete(
            'livestream_reminders',
            'user_id = ? AND livestream_id = ?',
            [$user['id'], $streamId]
        );

        Response::success([], 'Reminder cancelled');
        break;

    case 'send_chat':
        $streamId = (int)input('stream_id');
        $message = trim(input('message', ''));

        if (!$streamId) {
            Response::error('Stream ID required');
        }

        if (!$message || strlen($message) > 200) {
            Response::error('Message required (max 200 characters)');
        }

        // Verify stream is live and chat enabled
        $stream = Database::fetchOne(
            "SELECT * FROM livestreams WHERE id = ? AND status = 'live' AND chat_enabled = 1",
            [$streamId]
        );

        if (!$stream) {
            Response::error('Chat not available');
        }

        // Rate limit: 1 message per 3 seconds
        $recentMessage = Database::fetchOne(
            "SELECT id FROM livestream_chat
             WHERE user_id = ? AND livestream_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 3 SECOND)",
            [$user['id'], $streamId]
        );

        if ($recentMessage) {
            Response::error('Please wait before sending another message');
        }

        // Basic profanity filter (simplified)
        $message = preg_replace('/\b(fuck|shit|damn|ass|bitch)\b/i', '***', $message);

        Database::insert('livestream_chat', [
            'livestream_id' => $streamId,
            'user_id' => $user['id'],
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        Response::success([
            'message' => [
                'user_id' => $user['id'],
                'name' => $user['name'],
                'message' => $message,
                'created_at' => date('Y-m-d H:i:s')
            ]
        ]);
        break;

    case 'get_chat':
        $streamId = (int)input('stream_id');
        $lastId = (int)input('last_id', 0);

        if (!$streamId) {
            Response::error('Stream ID required');
        }

        $whereClause = "WHERE lc.livestream_id = ?";
        $params = [$streamId];

        if ($lastId) {
            $whereClause .= " AND lc.id > ?";
            $params[] = $lastId;
        }

        $messages = Database::fetchAll(
            "SELECT lc.id, lc.message, lc.created_at, u.id as user_id, u.name, u.avatar_url
             FROM livestream_chat lc
             JOIN users u ON lc.user_id = u.id
             $whereClause
             ORDER BY lc.created_at ASC LIMIT 100",
            $params
        );

        Response::success(['messages' => $messages]);
        break;

    case 'report_viewer':
        $streamId = (int)input('stream_id');

        if (!$streamId) {
            Response::error('Stream ID required');
        }

        // Update viewer count (simplified - in production use Redis/real-time)
        Database::query(
            "UPDATE livestreams SET viewer_count = viewer_count + 1, updated_at = NOW() WHERE id = ?",
            [$streamId]
        );

        $viewerCount = Database::fetchColumn(
            "SELECT viewer_count FROM livestreams WHERE id = ?",
            [$streamId]
        );

        Response::success(['viewer_count' => (int)$viewerCount]);
        break;

    case 'check_status':
        $streamId = (int)input('stream_id');

        if (!$streamId) {
            Response::error('Stream ID required');
        }

        $stream = Database::fetchOne(
            "SELECT id, status, viewer_count FROM livestreams WHERE id = ?",
            [$streamId]
        );

        if (!$stream) {
            Response::error('Stream not found');
        }

        Response::success([
            'status' => $stream['status'],
            'viewer_count' => (int)$stream['viewer_count']
        ]);
        break;

    default:
        Response::error('Invalid action');
}
