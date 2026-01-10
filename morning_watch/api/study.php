<?php
/**
 * CRC Morning Study Live API
 * POST /morning_watch/api/study.php
 *
 * Actions:
 * - today: Get today's study session
 * - join: Join live/replay session
 * - leave: Leave session
 * - post_message: Send chat message
 * - fetch_messages: Get recent messages (polling)
 * - add_note: Add shared note
 * - list_notes: Get shared notes
 * - pin_note / unpin_note: Admin pin/unpin notes
 * - hide_note: Admin hide note
 * - add_prayer: Add prayer request
 * - list_prayers: Get prayer requests
 * - add_bookmark: Add timestamp bookmark
 * - get_recap: Get session recap
 * - generate_recap: Admin generate recap
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();
Response::requirePost();
CSRF::require();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
$action = input('action', 'today');

switch ($action) {
    case 'today':
        // Get today's session with study fields
        $session = Database::fetchOne(
            "SELECT ms.*, u.name as author_name,
                    (SELECT COUNT(*) FROM morning_study_attendance WHERE session_id = ms.id AND mode = 'live') as live_count
             FROM morning_sessions ms
             LEFT JOIN users u ON ms.created_by = u.id
             WHERE ms.session_date = CURDATE()
             AND (ms.scope = 'global' OR ms.congregation_id = ?)
             AND ms.published_at IS NOT NULL
             ORDER BY ms.scope = 'congregation' DESC
             LIMIT 1",
            [$primaryCong['id'] ?? 0]
        );

        if ($session && $session['study_questions']) {
            $session['study_questions'] = json_decode($session['study_questions'], true);
        }

        // Check if recap exists
        $recap = null;
        if ($session) {
            $recap = Database::fetchOne(
                "SELECT id FROM morning_study_recaps WHERE session_id = ?",
                [$session['id']]
            );
        }

        Response::success([
            'session' => $session,
            'has_recap' => $recap ? true : false
        ]);
        break;

    case 'join':
        $sessionId = (int)input('session_id');
        $mode = input('mode', 'live');

        if (!$sessionId) {
            Response::error('Session ID required');
        }

        if (!in_array($mode, ['live', 'replay'])) {
            $mode = 'live';
        }

        // Verify session exists
        $session = Database::fetchOne(
            "SELECT id FROM morning_sessions WHERE id = ?",
            [$sessionId]
        );

        if (!$session) {
            Response::error('Session not found');
        }

        // Upsert attendance
        $existing = Database::fetchOne(
            "SELECT id FROM morning_study_attendance WHERE session_id = ? AND user_id = ? AND mode = ?",
            [$sessionId, $user['id'], $mode]
        );

        if ($existing) {
            Database::update('morning_study_attendance', [
                'joined_at' => date('Y-m-d H:i:s'),
                'left_at' => null,
                'duration_minutes' => null,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$existing['id']]);
        } else {
            Database::insert('morning_study_attendance', [
                'session_id' => $sessionId,
                'user_id' => $user['id'],
                'mode' => $mode,
                'joined_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }

        // Get current attendee count
        $count = Database::fetchColumn(
            "SELECT COUNT(DISTINCT user_id) FROM morning_study_attendance
             WHERE session_id = ? AND mode = 'live' AND left_at IS NULL",
            [$sessionId]
        );

        Response::success(['attendee_count' => $count]);
        break;

    case 'leave':
        $sessionId = (int)input('session_id');
        $mode = input('mode', 'live');

        if (!$sessionId) {
            Response::error('Session ID required');
        }

        // Update attendance with leave time and calculate duration
        Database::query(
            "UPDATE morning_study_attendance
             SET left_at = NOW(),
                 duration_minutes = TIMESTAMPDIFF(MINUTE, joined_at, NOW()),
                 updated_at = NOW()
             WHERE session_id = ? AND user_id = ? AND mode = ? AND left_at IS NULL",
            [$sessionId, $user['id'], $mode]
        );

        Response::success([]);
        break;

    case 'post_message':
        $sessionId = (int)input('session_id');
        $message = trim(input('message'));
        $messageType = input('message_type', 'chat');

        if (!$sessionId) {
            Response::error('Session ID required');
        }

        if (!$message || strlen($message) > 500) {
            Response::error('Message required (max 500 characters)');
        }

        if (!in_array($messageType, ['chat', 'question'])) {
            $messageType = 'chat';
        }

        // Anti-spam: check last message time (min 2 seconds between messages)
        $lastMessage = Database::fetchOne(
            "SELECT created_at FROM morning_study_messages
             WHERE session_id = ? AND user_id = ?
             ORDER BY created_at DESC LIMIT 1",
            [$sessionId, $user['id']]
        );

        if ($lastMessage && strtotime($lastMessage['created_at']) > time() - 2) {
            Response::error('Please wait before sending another message');
        }

        $msgId = Database::insert('morning_study_messages', [
            'session_id' => $sessionId,
            'user_id' => $user['id'],
            'message' => $message,
            'message_type' => $messageType,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        Response::success(['id' => $msgId]);
        break;

    case 'fetch_messages':
        $sessionId = (int)input('session_id');
        $afterId = (int)input('after_id', 0);

        if (!$sessionId) {
            Response::error('Session ID required');
        }

        $messages = Database::fetchAll(
            "SELECT m.id, m.message, m.message_type, m.created_at,
                    u.id as user_id, u.name as user_name, u.avatar as user_avatar
             FROM morning_study_messages m
             JOIN users u ON m.user_id = u.id
             WHERE m.session_id = ? AND m.id > ?
             ORDER BY m.id ASC
             LIMIT 50",
            [$sessionId, $afterId]
        );

        // Get current attendee count
        $count = Database::fetchColumn(
            "SELECT COUNT(DISTINCT user_id) FROM morning_study_attendance
             WHERE session_id = ? AND mode = 'live' AND left_at IS NULL",
            [$sessionId]
        );

        Response::success([
            'messages' => $messages,
            'attendee_count' => $count
        ]);
        break;

    case 'add_note':
        $sessionId = (int)input('session_id');
        $content = trim(input('content'));

        if (!$sessionId) {
            Response::error('Session ID required');
        }

        if (!$content || strlen($content) > 2000) {
            Response::error('Note content required (max 2000 characters)');
        }

        $noteId = Database::insert('morning_study_notes', [
            'session_id' => $sessionId,
            'user_id' => $user['id'],
            'content' => $content,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        Response::success(['id' => $noteId]);
        break;

    case 'list_notes':
        $sessionId = (int)input('session_id');

        if (!$sessionId) {
            Response::error('Session ID required');
        }

        $notes = Database::fetchAll(
            "SELECT n.id, n.content, n.is_pinned, n.created_at,
                    u.id as user_id, u.name as user_name, u.avatar as user_avatar
             FROM morning_study_notes n
             JOIN users u ON n.user_id = u.id
             WHERE n.session_id = ? AND n.is_hidden = 0
             ORDER BY n.is_pinned DESC, n.created_at DESC
             LIMIT 100",
            [$sessionId]
        );

        Response::success(['notes' => $notes]);
        break;

    case 'pin_note':
    case 'unpin_note':
        $noteId = (int)input('note_id');

        if (!$noteId) {
            Response::error('Note ID required');
        }

        // Get note and check permission
        $note = Database::fetchOne(
            "SELECT n.*, ms.congregation_id
             FROM morning_study_notes n
             JOIN morning_sessions ms ON n.session_id = ms.id
             WHERE n.id = ?",
            [$noteId]
        );

        if (!$note) {
            Response::error('Note not found');
        }

        // Check admin permission
        $isAdmin = Auth::isCongregationAdmin($note['congregation_id']) ||
                   Auth::globalRole() === 'super_admin';

        if (!$isAdmin) {
            Response::forbidden('Admin access required');
        }

        $isPinned = $action === 'pin_note' ? 1 : 0;

        Database::update('morning_study_notes', [
            'is_pinned' => $isPinned,
            'pinned_by' => $isPinned ? $user['id'] : null,
            'pinned_at' => $isPinned ? date('Y-m-d H:i:s') : null,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$noteId]);

        Response::success([]);
        break;

    case 'hide_note':
        $noteId = (int)input('note_id');

        if (!$noteId) {
            Response::error('Note ID required');
        }

        // Get note and check permission
        $note = Database::fetchOne(
            "SELECT n.*, ms.congregation_id
             FROM morning_study_notes n
             JOIN morning_sessions ms ON n.session_id = ms.id
             WHERE n.id = ?",
            [$noteId]
        );

        if (!$note) {
            Response::error('Note not found');
        }

        $isAdmin = Auth::isCongregationAdmin($note['congregation_id']) ||
                   Auth::globalRole() === 'super_admin';

        if (!$isAdmin) {
            Response::forbidden('Admin access required');
        }

        Database::update('morning_study_notes', [
            'is_hidden' => 1,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$noteId]);

        Response::success([]);
        break;

    case 'add_prayer':
        $sessionId = (int)input('session_id');
        $prayerRequest = trim(input('prayer_request'));
        $isPrivate = input('is_private') ? 1 : 0;

        if (!$sessionId) {
            Response::error('Session ID required');
        }

        if (!$prayerRequest || strlen($prayerRequest) > 1000) {
            Response::error('Prayer request required (max 1000 characters)');
        }

        $prayerId = Database::insert('morning_study_prayers', [
            'session_id' => $sessionId,
            'user_id' => $user['id'],
            'prayer_request' => $prayerRequest,
            'is_private' => $isPrivate,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        Response::success(['id' => $prayerId]);
        break;

    case 'list_prayers':
        $sessionId = (int)input('session_id');

        if (!$sessionId) {
            Response::error('Session ID required');
        }

        // Get public prayers + user's own private prayers
        $prayers = Database::fetchAll(
            "SELECT p.id, p.prayer_request, p.is_private, p.created_at,
                    u.id as user_id, u.name as user_name
             FROM morning_study_prayers p
             JOIN users u ON p.user_id = u.id
             WHERE p.session_id = ? AND (p.is_private = 0 OR p.user_id = ?)
             ORDER BY p.created_at DESC
             LIMIT 50",
            [$sessionId, $user['id']]
        );

        Response::success(['prayers' => $prayers]);
        break;

    case 'add_bookmark':
        $sessionId = (int)input('session_id');
        $timestampSeconds = (int)input('timestamp_seconds', 0);
        $label = trim(input('label'));

        if (!$sessionId) {
            Response::error('Session ID required');
        }

        if (!$label || strlen($label) > 255) {
            Response::error('Bookmark label required (max 255 characters)');
        }

        $bookmarkId = Database::insert('morning_study_bookmarks', [
            'session_id' => $sessionId,
            'user_id' => $user['id'],
            'timestamp_seconds' => $timestampSeconds,
            'label' => $label,
            'is_public' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        Response::success(['id' => $bookmarkId]);
        break;

    case 'get_recap':
        $sessionId = (int)input('session_id');

        if (!$sessionId) {
            Response::error('Session ID required');
        }

        $recap = Database::fetchOne(
            "SELECT r.*, ms.title, ms.scripture_ref, ms.key_verse, ms.session_date
             FROM morning_study_recaps r
             JOIN morning_sessions ms ON r.session_id = ms.id
             WHERE r.session_id = ?",
            [$sessionId]
        );

        if (!$recap) {
            Response::error('Recap not found');
        }

        if ($recap['recap_json']) {
            $recap['recap_json'] = json_decode($recap['recap_json'], true);
        }

        Response::success(['recap' => $recap]);
        break;

    case 'generate_recap':
        $sessionId = (int)input('session_id');

        if (!$sessionId) {
            Response::error('Session ID required');
        }

        // Get session and check admin permission
        $session = Database::fetchOne(
            "SELECT * FROM morning_sessions WHERE id = ?",
            [$sessionId]
        );

        if (!$session) {
            Response::error('Session not found');
        }

        $isAdmin = Auth::isCongregationAdmin($session['congregation_id']) ||
                   Auth::globalRole() === 'super_admin';

        if (!$isAdmin) {
            Response::forbidden('Admin access required');
        }

        // Get pinned notes
        $pinnedNotes = Database::fetchAll(
            "SELECT n.content, u.name as user_name
             FROM morning_study_notes n
             JOIN users u ON n.user_id = u.id
             WHERE n.session_id = ? AND n.is_pinned = 1 AND n.is_hidden = 0
             ORDER BY n.pinned_at ASC",
            [$sessionId]
        );

        // Get recent messages (for Q&A highlights)
        $messages = Database::fetchAll(
            "SELECT m.message, m.message_type, u.name as user_name
             FROM morning_study_messages m
             JOIN users u ON m.user_id = u.id
             WHERE m.session_id = ? AND m.message_type = 'question'
             ORDER BY m.created_at DESC
             LIMIT 5",
            [$sessionId]
        );

        // Get attendance count
        $attendeeCount = Database::fetchColumn(
            "SELECT COUNT(DISTINCT user_id) FROM morning_study_attendance WHERE session_id = ?",
            [$sessionId]
        );

        // Build recap text
        $recapParts = [];
        $recapParts[] = "MORNING STUDY RECAP";
        $recapParts[] = "Date: " . date('l, F j, Y', strtotime($session['session_date']));
        $recapParts[] = "Title: " . $session['title'];
        $recapParts[] = "Scripture: " . $session['scripture_ref'];

        if ($session['key_verse']) {
            $recapParts[] = "\nKey Verse: " . $session['key_verse'];
        }

        if ($pinnedNotes) {
            $recapParts[] = "\n--- KEY POINTS ---";
            foreach ($pinnedNotes as $note) {
                $recapParts[] = "• " . $note['content'];
            }
        }

        if ($messages) {
            $recapParts[] = "\n--- QUESTIONS DISCUSSED ---";
            foreach ($messages as $msg) {
                $recapParts[] = "Q: " . $msg['message'];
            }
        }

        $recapParts[] = "\nAttendees: " . $attendeeCount;

        $recapText = implode("\n", $recapParts);

        // Build JSON structure
        $recapJson = [
            'date' => $session['session_date'],
            'title' => $session['title'],
            'scripture' => $session['scripture_ref'],
            'key_verse' => $session['key_verse'],
            'key_points' => array_column($pinnedNotes, 'content'),
            'questions' => array_column($messages, 'message'),
            'attendee_count' => $attendeeCount
        ];

        // Upsert recap
        $existing = Database::fetchOne(
            "SELECT id FROM morning_study_recaps WHERE session_id = ?",
            [$sessionId]
        );

        if ($existing) {
            Database::update('morning_study_recaps', [
                'recap_text' => $recapText,
                'recap_json' => json_encode($recapJson),
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$existing['id']]);
        } else {
            Database::insert('morning_study_recaps', [
                'session_id' => $sessionId,
                'recap_text' => $recapText,
                'recap_json' => json_encode($recapJson),
                'created_by' => $user['id'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }

        // Update session status to ended
        Database::update('morning_sessions', [
            'live_status' => 'ended',
            'live_ended_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$sessionId]);

        Response::success(['recap_text' => $recapText]);
        break;

    default:
        Response::error('Invalid action');
}
