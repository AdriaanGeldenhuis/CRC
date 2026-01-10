<?php
/**
 * CRC Diary Tags API
 * POST /diary/api/tags.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();
Response::requirePost();
CSRF::require();

$user = Auth::user();
$action = input('action', 'list');

switch ($action) {
    case 'list':
        $tags = Database::fetchAll(
            "SELECT t.*, COUNT(det.entry_id) as entry_count
             FROM diary_tags t
             LEFT JOIN diary_entry_tags det ON t.id = det.tag_id
             WHERE t.user_id = ?
             GROUP BY t.id
             ORDER BY entry_count DESC, t.name ASC",
            [$user['id']]
        );

        Response::success(['tags' => $tags]);
        break;

    case 'create':
        $name = trim(input('name'));

        if (!$name) {
            Response::error('Tag name required');
        }

        if (strlen($name) > 50) {
            Response::error('Tag name too long (max 50 characters)');
        }

        // Check if exists
        $existing = Database::fetchOne(
            "SELECT * FROM diary_tags WHERE name = ? AND user_id = ?",
            [$name, $user['id']]
        );

        if ($existing) {
            Response::success(['id' => $existing['id'], 'name' => $existing['name']], 'Tag already exists');
        }

        $tagId = Database::insert('diary_tags', [
            'user_id' => $user['id'],
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        Response::success(['id' => $tagId, 'name' => $name], 'Tag created');
        break;

    case 'rename':
        $tagId = (int)input('tag_id');
        $newName = trim(input('name'));

        if (!$tagId || !$newName) {
            Response::error('Tag ID and name required');
        }

        $tag = Database::fetchOne(
            "SELECT * FROM diary_tags WHERE id = ? AND user_id = ?",
            [$tagId, $user['id']]
        );

        if (!$tag) {
            Response::error('Tag not found');
        }

        // Check if new name already exists
        $existing = Database::fetchOne(
            "SELECT * FROM diary_tags WHERE name = ? AND user_id = ? AND id != ?",
            [$newName, $user['id'], $tagId]
        );

        if ($existing) {
            Response::error('Tag with this name already exists');
        }

        Database::update('diary_tags', ['name' => $newName], 'id = ?', [$tagId]);

        Response::success([], 'Tag renamed');
        break;

    case 'delete':
        $tagId = (int)input('tag_id');

        if (!$tagId) {
            Response::error('Tag ID required');
        }

        $tag = Database::fetchOne(
            "SELECT * FROM diary_tags WHERE id = ? AND user_id = ?",
            [$tagId, $user['id']]
        );

        if (!$tag) {
            Response::error('Tag not found');
        }

        // Remove associations
        Database::delete('diary_entry_tags', 'tag_id = ?', [$tagId]);
        // Delete tag
        Database::delete('diary_tags', 'id = ?', [$tagId]);

        Response::success([], 'Tag deleted');
        break;

    case 'search':
        $query = trim(input('query'));

        if (strlen($query) < 1) {
            Response::error('Search query required');
        }

        $tags = Database::fetchAll(
            "SELECT * FROM diary_tags
             WHERE user_id = ? AND name LIKE ?
             ORDER BY name ASC
             LIMIT 10",
            [$user['id'], '%' . $query . '%']
        );

        Response::success(['tags' => $tags]);
        break;

    default:
        Response::error('Invalid action');
}
