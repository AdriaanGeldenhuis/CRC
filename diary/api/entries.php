<?php
/**
 * CRC Diary Entries API
 * POST /diary/api/entries.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();
Response::requirePost();
CSRF::require();

$user = Auth::user();
$action = input('action', 'create');

switch ($action) {
    case 'create':
    case 'update':
        $entryId = (int)input('entry_id');
        $title = input('title');
        $content = input('content');
        $entryDate = input('entry_date', date('Y-m-d'));
        $mood = input('mood');
        $scriptureRef = input('scripture_ref');
        $isPrivate = (int)input('is_private', 1);
        $tags = $_POST['tags'] ?? [];

        if (!$content) {
            Response::error('Content is required');
        }

        if (strlen($content) > 50000) {
            Response::error('Content too long');
        }

        $validMoods = ['grateful', 'joyful', 'peaceful', 'hopeful', 'anxious', 'sad', 'angry', 'confused', ''];
        if (!in_array($mood, $validMoods)) {
            $mood = null;
        }

        $data = [
            'title' => $title,
            'content' => $content,
            'entry_date' => $entryDate,
            'mood' => $mood ?: null,
            'scripture_ref' => $scriptureRef ?: null,
            'is_private' => $isPrivate,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($entryId) {
            // Update existing entry
            $existing = Database::fetchOne(
                "SELECT * FROM diary_entries WHERE id = ? AND user_id = ?",
                [$entryId, $user['id']]
            );

            if (!$existing) {
                Response::error('Entry not found');
            }

            Database::update('diary_entries', $data, 'id = ?', [$entryId]);
        } else {
            // Create new entry
            $data['user_id'] = $user['id'];
            $data['created_at'] = date('Y-m-d H:i:s');
            $entryId = Database::insert('diary_entries', $data);
        }

        // Handle tags
        if ($entryId) {
            // Remove existing tag associations
            Database::delete('diary_entry_tags', 'entry_id = ?', [$entryId]);

            // Add new tags
            if (!empty($tags)) {
                foreach ($tags as $tagName) {
                    $tagName = trim($tagName);
                    if (empty($tagName)) continue;

                    // Get or create tag
                    $tag = Database::fetchOne(
                        "SELECT * FROM diary_tags WHERE name = ? AND user_id = ?",
                        [$tagName, $user['id']]
                    );

                    if (!$tag) {
                        $tagId = Database::insert('diary_tags', [
                            'user_id' => $user['id'],
                            'name' => $tagName,
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    } else {
                        $tagId = $tag['id'];
                    }

                    // Associate tag with entry
                    Database::insert('diary_entry_tags', [
                        'entry_id' => $entryId,
                        'tag_id' => $tagId
                    ]);
                }
            }
        }

        Response::success(['id' => $entryId], $action === 'create' ? 'Entry created' : 'Entry updated');
        break;

    case 'delete':
        $entryId = (int)input('entry_id');

        if (!$entryId) {
            Response::error('Entry ID required');
        }

        $existing = Database::fetchOne(
            "SELECT * FROM diary_entries WHERE id = ? AND user_id = ?",
            [$entryId, $user['id']]
        );

        if (!$existing) {
            Response::error('Entry not found');
        }

        // Delete tag associations
        Database::delete('diary_entry_tags', 'entry_id = ?', [$entryId]);
        // Delete entry
        Database::delete('diary_entries', 'id = ?', [$entryId]);

        Response::success([], 'Entry deleted');
        break;

    case 'get':
        $entryId = (int)input('entry_id');

        if (!$entryId) {
            Response::error('Entry ID required');
        }

        $entry = Database::fetchOne(
            "SELECT * FROM diary_entries WHERE id = ? AND user_id = ?",
            [$entryId, $user['id']]
        );

        if (!$entry) {
            Response::error('Entry not found');
        }

        // Get tags
        $tags = Database::fetchAll(
            "SELECT t.* FROM diary_tags t
             JOIN diary_entry_tags det ON t.id = det.tag_id
             WHERE det.entry_id = ?",
            [$entryId]
        );

        $entry['tags'] = $tags;

        Response::success(['entry' => $entry]);
        break;

    case 'search':
        $query = input('query');
        $limit = min((int)input('limit', 20), 100);

        if (!$query || strlen($query) < 2) {
            Response::error('Search query required (min 2 characters)');
        }

        $entries = Database::fetchAll(
            "SELECT id, title, entry_date, mood,
                    SUBSTRING(content, 1, 150) as excerpt
             FROM diary_entries
             WHERE user_id = ?
             AND (title LIKE ? OR content LIKE ?)
             ORDER BY entry_date DESC
             LIMIT ?",
            [$user['id'], '%' . $query . '%', '%' . $query . '%', $limit]
        );

        Response::success(['entries' => $entries]);
        break;

    default:
        Response::error('Invalid action');
}
