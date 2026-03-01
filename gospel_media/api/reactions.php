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

// Validate reaction type
$validReactions = ['like', 'love', 'pray', 'amen'];
if (!in_array($reactionType, $validReactions)) {
    $reactionType = 'like';
}

if (!in_array($type, ['post', 'comment'])) {
    Response::error('Invalid type');
}

if (!$id) {
    Response::error('ID required');
}

// Check if already reacted
$existing = Database::fetchOne(
    "SELECT id, reaction_type FROM reactions WHERE user_id = ? AND reactable_type = ? AND reactable_id = ?",
    [Auth::id(), $type, $id]
);

if ($existing) {
    if ($existing['reaction_type'] === $reactionType) {
        // Same reaction type = remove (toggle off)
        Database::delete('reactions', 'id = ?', [$existing['id']]);

        // Get updated reaction summary
        $summary = getReactionSummary($type, $id);
        Response::success(['action' => 'removed', 'summary' => $summary], 'Reaction removed');
    } else {
        // Different reaction type = update to new type
        Database::query(
            "UPDATE reactions SET reaction_type = ? WHERE id = ?",
            [$reactionType, $existing['id']]
        );

        $summary = getReactionSummary($type, $id);
        Response::success(['action' => 'changed', 'reaction_type' => $reactionType, 'summary' => $summary], 'Reaction updated');
    }
} else {
    // Add new reaction
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
            $reactionLabels = ['like' => 'liked', 'love' => 'loved', 'pray' => 'is praying for', 'amen' => 'said Amen to'];
            $actionText = $reactionLabels[$reactionType] ?? 'reacted to';
            Database::insert('notifications', [
                'user_id' => $post['user_id'],
                'type' => 'new_reaction',
                'title' => 'New Reaction',
                'message' => $user['name'] . ' ' . $actionText . ' your post',
                'link' => '/gospel_media/post.php?id=' . $id,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }

    $summary = getReactionSummary($type, $id);
    Response::success(['action' => 'added', 'reaction_type' => $reactionType, 'summary' => $summary], 'Reaction added');
}

function getReactionSummary($type, $id) {
    $rows = Database::fetchAll(
        "SELECT reaction_type, COUNT(*) as count FROM reactions WHERE reactable_type = ? AND reactable_id = ? GROUP BY reaction_type ORDER BY count DESC",
        [$type, $id]
    ) ?: [];

    $summary = [];
    $total = 0;
    foreach ($rows as $row) {
        $summary[$row['reaction_type']] = (int)$row['count'];
        $total += (int)$row['count'];
    }
    $summary['total'] = $total;
    return $summary;
}
