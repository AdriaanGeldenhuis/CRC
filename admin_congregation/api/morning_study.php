<?php
/**
 * CRC Congregation Morning Study API
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();

$primaryCong = Auth::primaryCongregation();
if (!$primaryCong || !Auth::isCongregationAdmin($primaryCong['id'])) {
    Response::forbidden('Admin access required');
}

Response::requirePost();
CSRF::require();

$congregationId = $primaryCong['id'];
$userId = Auth::id();
$user = Auth::user();
$action = input('action');
$today = date('Y-m-d');

switch ($action) {
    case 'save_today':
        $sessionId = (int) input('session_id');
        $title = trim(input('title'));
        $scriptureRef = trim(input('scripture_ref'));
        $versionCode = input('version_code') ?: 'KJV';
        $keyVerse = trim(input('key_verse'));
        $scriptureText = trim(input('scripture_text'));
        $studyQuestions = $_POST['study_questions'] ?? [];
        $streamUrl = trim(input('stream_url'));
        $liveStartsAt = input('live_starts_at');
        $contentMode = input('content_mode') ?: 'watch';

        if (!$title) {
            Response::error('Title is required');
        }

        if (!$scriptureRef) {
            Response::error('Scripture reference is required');
        }

        // Validate content_mode
        if (!in_array($contentMode, ['watch', 'study'])) {
            $contentMode = 'watch';
        }

        $sessionData = [
            'title' => $title,
            'scripture_ref' => $scriptureRef,
            'version_code' => $versionCode,
            'key_verse' => $keyVerse ?: null,
            'scripture_text' => $scriptureText ?: null,
            'study_questions' => $studyQuestions ? json_encode($studyQuestions) : null,
            'stream_url' => $streamUrl ?: null,
            'live_starts_at' => $liveStartsAt ?: null,
            'content_mode' => $contentMode,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($sessionId) {
            // Update existing session - verify it belongs to this congregation
            $existing = Database::fetchOne(
                "SELECT * FROM morning_sessions WHERE id = ? AND congregation_id = ?",
                [$sessionId, $congregationId]
            );

            if (!$existing) {
                Response::error('Session not found');
            }

            Database::update('morning_sessions', $sessionData, 'id = ?', [$sessionId]);

            Logger::audit($userId, 'updated_morning_study', [
                'session_id' => $sessionId,
                'congregation_id' => $congregationId
            ]);

            Response::success(['session_id' => $sessionId], 'Session updated');
        } else {
            // Create new session for today
            $sessionData['scope'] = 'congregation';
            $sessionData['congregation_id'] = $congregationId;
            $sessionData['session_date'] = $today;
            $sessionData['created_by'] = $userId;
            $sessionData['live_status'] = 'scheduled';
            $sessionData['published_at'] = date('Y-m-d H:i:s');
            $sessionData['created_at'] = date('Y-m-d H:i:s');

            $newId = Database::insert('morning_sessions', $sessionData);

            Logger::audit($userId, 'created_morning_study', [
                'session_id' => $newId,
                'congregation_id' => $congregationId
            ]);

            Response::success(['session_id' => $newId], 'Session created');
        }
        break;

    case 'set_live':
        $sessionId = (int) input('session_id');

        if (!$sessionId) {
            Response::error('Session ID required');
        }

        $session = Database::fetchOne(
            "SELECT * FROM morning_sessions WHERE id = ? AND congregation_id = ?",
            [$sessionId, $congregationId]
        );

        if (!$session) {
            Response::error('Session not found');
        }

        Database::update('morning_sessions', [
            'live_status' => 'live',
            'live_starts_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$sessionId]);

        Logger::audit($userId, 'morning_study_went_live', [
            'session_id' => $sessionId,
            'congregation_id' => $congregationId
        ]);

        Response::success([], 'Session is now live');
        break;

    case 'end_session':
        $sessionId = (int) input('session_id');

        if (!$sessionId) {
            Response::error('Session ID required');
        }

        $session = Database::fetchOne(
            "SELECT * FROM morning_sessions WHERE id = ? AND congregation_id = ?",
            [$sessionId, $congregationId]
        );

        if (!$session) {
            Response::error('Session not found');
        }

        Database::update('morning_sessions', [
            'live_status' => 'ended',
            'live_ended_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$sessionId]);

        // Mark all attendees as left
        Database::query(
            "UPDATE morning_study_attendance SET left_at = NOW() WHERE session_id = ? AND left_at IS NULL",
            [$sessionId]
        );

        Logger::audit($userId, 'morning_study_ended', [
            'session_id' => $sessionId,
            'congregation_id' => $congregationId
        ]);

        Response::success([], 'Session ended');
        break;

    case 'delete':
        $sessionId = (int) input('session_id');

        if (!$sessionId) {
            Response::error('Session ID required');
        }

        $session = Database::fetchOne(
            "SELECT * FROM morning_sessions WHERE id = ? AND congregation_id = ?",
            [$sessionId, $congregationId]
        );

        if (!$session) {
            Response::error('Session not found');
        }

        // Delete related data first
        Database::query("DELETE FROM morning_study_attendance WHERE session_id = ?", [$sessionId]);
        Database::query("DELETE FROM morning_study_messages WHERE session_id = ?", [$sessionId]);
        Database::query("DELETE FROM morning_study_notes WHERE session_id = ?", [$sessionId]);
        Database::query("DELETE FROM morning_study_prayers WHERE session_id = ?", [$sessionId]);
        Database::query("DELETE FROM morning_study_recaps WHERE session_id = ?", [$sessionId]);
        Database::query("DELETE FROM morning_user_entries WHERE session_id = ?", [$sessionId]);

        // Delete the session
        Database::delete('morning_sessions', 'id = ?', [$sessionId]);

        Logger::audit($userId, 'deleted_morning_study', [
            'session_id' => $sessionId,
            'session_title' => $session['title'],
            'congregation_id' => $congregationId
        ]);

        Response::success([], 'Session deleted');
        break;

    default:
        Response::error('Invalid action');
}
