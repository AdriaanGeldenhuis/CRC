<?php
/**
 * CRC Reactions API
 * POST /gospel_media/api/reactions.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();
Response::requirePost();
CSRF::require();

$type = input('type'); // 'post' or 'comment'
$id = (int) input('id');
$reactionType = input('reaction_type', 'like');

if (!in_array($type, ['post', 'comment'])) {
    Response::error('Invalid type');
}

if (!$id) {
    Response::error('ID required');
}

// Check if already reacted
$existing = Database::fetchOne(
    "SELECT id FROM reactions WHERE user_id = ? AND reactable_type = ? AND reactable_id = ?",
    [Auth::id(), $type, $id]
);

if ($existing) {
    // Remove reaction
    Database::delete('reactions', 'id = ?', [$existing['id']]);
    Response::success(['action' => 'removed'], 'Reaction removed');
} else {
    // Add reaction
    Database::insert('reactions', [
        'user_id' => Auth::id(),
        'reactable_type' => $type,
        'reactable_id' => $id,
        'reaction_type' => $reactionType,
        'created_at' => date('Y-m-d H:i:s')
    ]);

    // Notify content owner
    if ($type === 'post') {
        $post = Database::fetchOne("SELECT user_id FROM posts WHERE id = ?", [$id]);
        if ($post && $post['user_id'] !== Auth::id()) {
            $user = Auth::user();
            Database::insert('notifications', [
                'user_id' => $post['user_id'],
                'type' => 'new_reaction',
                'title' => 'New Reaction',
                'message' => $user['name'] . ' liked your post',
                'link' => '/gospel_media/post.php?id=' . $id,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }

    Response::success(['action' => 'added'], 'Reaction added');
}
