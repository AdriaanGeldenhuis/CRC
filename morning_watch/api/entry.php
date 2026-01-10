<?php
/**
 * CRC Morning Study Entry API
 * POST /morning_watch/api/entry.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();
Response::requirePost();
CSRF::require();

$user = Auth::user();
$action = input('action', 'save');

switch ($action) {
    case 'save':
        $sessionId = (int)input('session_id');
        $reflection = input('reflection');
        $prayer = input('prayer');           // maps to prayer_notes
        $application = input('application'); // maps to personal_notes

        if (!$sessionId) {
            Response::error('Session ID required');
        }

        // Verify session exists and is accessible
        $session = Database::fetchOne(
            "SELECT * FROM morning_sessions WHERE id = ? AND published_at IS NOT NULL",
            [$sessionId]
        );

        if (!$session) {
            Response::error('Session not found');
        }

        // Validate content
        if (!$reflection && !$prayer && !$application) {
            Response::error('Please fill in at least one field');
        }

        // Check if entry exists
        $existing = Database::fetchOne(
            "SELECT * FROM morning_user_entries WHERE user_id = ? AND session_id = ?",
            [$user['id'], $sessionId]
        );

        if ($existing) {
            // Update
            Database::update('morning_user_entries', [
                'reflection' => $reflection,
                'prayer_notes' => $prayer,
                'personal_notes' => $application,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$existing['id']]);

            $entryId = $existing['id'];
        } else {
            // Insert
            $entryId = Database::insert('morning_user_entries', [
                'user_id' => $user['id'],
                'session_id' => $sessionId,
                'reflection' => $reflection,
                'prayer_notes' => $prayer,
                'personal_notes' => $application,
                'completed_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // Update streak
            updateStreak($user['id']);
        }

        Response::success(['id' => $entryId], 'Entry saved');
        break;

    case 'get':
        $sessionId = (int)input('session_id');

        if (!$sessionId) {
            Response::error('Session ID required');
        }

        // Return with aliases for backward compatibility
        $entry = Database::fetchOne(
            "SELECT id, user_id, session_id,
                    personal_notes, prayer_notes, reflection,
                    personal_notes as application, prayer_notes as prayer,
                    completed_at, created_at, updated_at
             FROM morning_user_entries
             WHERE user_id = ? AND session_id = ?",
            [$user['id'], $sessionId]
        );

        Response::success(['entry' => $entry]);
        break;

    case 'history':
        $limit = min((int)input('limit', 30), 100);
        $offset = (int)input('offset', 0);

        $entries = Database::fetchAll(
            "SELECT e.id, e.user_id, e.session_id,
                    e.personal_notes, e.prayer_notes, e.reflection,
                    e.personal_notes as application, e.prayer_notes as prayer,
                    e.completed_at, e.created_at, e.updated_at,
                    ms.title, ms.session_date, ms.scripture_ref
             FROM morning_user_entries e
             JOIN morning_sessions ms ON e.session_id = ms.id
             WHERE e.user_id = ?
             ORDER BY ms.session_date DESC
             LIMIT ? OFFSET ?",
            [$user['id'], $limit, $offset]
        );

        Response::success(['entries' => $entries]);
        break;

    case 'streak':
        $streak = getOrCreateStreak($user['id']);
        // Return with aliases for backward compatibility
        $streak['total_entries'] = $streak['total_completions'] ?? 0;
        Response::success(['streak' => $streak]);
        break;

    default:
        Response::error('Invalid action');
}

function updateStreak($userId) {
    $streak = getOrCreateStreak($userId);

    // Get dates with entries in last 30 days
    $recentEntries = Database::fetchAll(
        "SELECT DISTINCT DATE(ms.session_date) as entry_date
         FROM morning_user_entries e
         JOIN morning_sessions ms ON e.session_id = ms.id
         WHERE e.user_id = ?
         AND ms.session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         ORDER BY entry_date DESC",
        [$userId]
    );

    $dates = array_column($recentEntries, 'entry_date');

    // Calculate current streak
    $currentStreak = 0;
    $checkDate = date('Y-m-d');

    while (in_array($checkDate, $dates)) {
        $currentStreak++;
        $checkDate = date('Y-m-d', strtotime($checkDate . ' -1 day'));
    }

    // Update total entries
    $totalEntries = Database::fetchColumn(
        "SELECT COUNT(*) FROM morning_user_entries WHERE user_id = ?",
        [$userId]
    );

    // Update streak record
    $longestStreak = max($streak['longest_streak'], $currentStreak);

    Database::update('morning_streaks', [
        'current_streak' => $currentStreak,
        'longest_streak' => $longestStreak,
        'total_completions' => $totalEntries,
        'last_completed_date' => date('Y-m-d'),
        'updated_at' => date('Y-m-d H:i:s')
    ], 'user_id = ?', [$userId]);
}

function getOrCreateStreak($userId) {
    $streak = Database::fetchOne(
        "SELECT * FROM morning_streaks WHERE user_id = ?",
        [$userId]
    );

    if (!$streak) {
        Database::insert('morning_streaks', [
            'user_id' => $userId,
            'current_streak' => 0,
            'longest_streak' => 0,
            'total_completions' => 0,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $streak = [
            'current_streak' => 0,
            'longest_streak' => 0,
            'total_completions' => 0
        ];
    }

    return $streak;
}
