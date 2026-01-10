<?php
/**
 * CRC Comments API
 * GET/POST /gospel_media/api/comments.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get comments for post
    $postId = (int) $_GET['post_id'];

    if (!$postId) {
        Response::error('Post ID required');
    }

    $comments = Database::fetchAll(
        "SELECT c.*, u.name as author_name, u.avatar as author_avatar
         FROM comments c
         JOIN users u ON c.user_id = u.id
         WHERE c.post_id = ? AND c.status = 'active' AND c.parent_id IS NULL
         ORDER BY c.created_at ASC
         LIMIT 50",
        [$postId]
    );

    // Add time_ago
    foreach ($comments as &$comment) {
        $comment['time_ago'] = time_ago($comment['created_at']);
    }

    Response::success(['comments' => $comments]);

} else {
    // Create comment
    Response::requirePost();
    CSRF::require();

    $postId = (int) input('post_id');
    $parentId = (int) input('parent_id') ?: null;
    $content = trim(input('content'));

    if (!$postId) {
        Response::error('Post ID required');
    }

    if (empty($content)) {
        Response::error('Comment content required');
    }

    if (strlen($content) > 1000) {
        Response::error('Comment too long');
    }

    // Check post exists
    $post = Database::fetchOne(
        "SELECT id, user_id FROM posts WHERE id = ? AND status = 'active'",
        [$postId]
    );

    if (!$post) {
        Response::notFound('Post not found');
    }

    // Create comment
    $commentId = Database::insert('comments', [
        'post_id' => $postId,
        'user_id' => Auth::id(),
        'parent_id' => $parentId,
        'content' => $content,
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    // Create notification for post owner
    if ($post['user_id'] !== Auth::id()) {
        $user = Auth::user();
        Database::insert('notifications', [
            'user_id' => $post['user_id'],
            'type' => 'new_comment',
            'title' => 'New Comment',
            'message' => $user['name'] . ' commented on your post',
            'link' => '/gospel_media/post.php?id=' . $postId,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    Response::success(['comment_id' => $commentId], 'Comment posted');
}
