<?php
/**
 * CRC Comments API
 * GET/POST /gospel_media/api/comments.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();

$action = $_GET['action'] ?? $_POST['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get comments for post
    $postId = (int) $_GET['post_id'];
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(50, (int)($_GET['per_page'] ?? 50));
    $offset = ($page - 1) * $perPage;

    if (!$postId) {
        Response::error('Post ID required');
    }

    $comments = Database::fetchAll(
        "SELECT c.*, u.name as author_name, u.avatar as author_avatar,
                (SELECT COUNT(*) FROM comments WHERE parent_id = c.id AND status = 'active') as reply_count
         FROM comments c
         JOIN users u ON c.user_id = u.id
         WHERE c.post_id = ? AND c.status = 'active' AND c.parent_id IS NULL
         ORDER BY c.created_at ASC
         LIMIT ? OFFSET ?",
        [$postId, $perPage, $offset]
    );

    // Get total count for pagination
    $totalComments = Database::fetchColumn(
        "SELECT COUNT(*) FROM comments WHERE post_id = ? AND status = 'active' AND parent_id IS NULL",
        [$postId]
    );

    // Add time_ago and check if user owns each comment
    foreach ($comments as &$comment) {
        $comment['time_ago'] = time_ago($comment['created_at']);
        $comment['is_owner'] = ($comment['user_id'] == Auth::id());
        $comment['can_edit'] = ($comment['user_id'] == Auth::id() || Auth::isAdmin());
    }

    Response::success([
        'comments' => $comments,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => (int)$totalComments,
            'total_pages' => ceil($totalComments / $perPage)
        ]
    ]);

} else {
    // POST actions
    Response::requirePost();
    CSRF::require();

    // Determine action from input
    if (!$action) {
        $action = 'create'; // Default action for backwards compatibility
    }

    switch ($action) {
        case 'create':
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
            break;

        case 'update':
            $commentId = (int) input('comment_id');
            $content = trim(input('content'));

            if (!$commentId) {
                Response::error('Comment ID required');
            }

            if (empty($content)) {
                Response::error('Comment content required');
            }

            if (strlen($content) > 1000) {
                Response::error('Comment too long');
            }

            $comment = Database::fetchOne(
                "SELECT * FROM comments WHERE id = ? AND status = 'active'",
                [$commentId]
            );

            if (!$comment) {
                Response::notFound('Comment not found');
            }

            // Check ownership or admin
            if ($comment['user_id'] !== Auth::id() && !Auth::isAdmin()) {
                Response::forbidden('Cannot edit this comment');
            }

            Database::update(
                'comments',
                [
                    'content' => $content,
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                'id = ?',
                [$commentId]
            );

            Logger::audit(Auth::id(), 'updated_comment', ['comment_id' => $commentId]);

            Response::success(['comment_id' => $commentId], 'Comment updated');
            break;

        case 'delete':
            $commentId = (int) input('comment_id');

            if (!$commentId) {
                Response::error('Comment ID required');
            }

            $comment = Database::fetchOne(
                "SELECT * FROM comments WHERE id = ? AND status = 'active'",
                [$commentId]
            );

            if (!$comment) {
                Response::notFound('Comment not found');
            }

            // Check ownership or admin
            if ($comment['user_id'] !== Auth::id() && !Auth::isAdmin()) {
                $primaryCong = Auth::primaryCongregation();
                if (!$primaryCong || !Auth::isCongregationAdmin($primaryCong['id'])) {
                    Response::forbidden('Cannot delete this comment');
                }
            }

            // Soft delete
            Database::update(
                'comments',
                [
                    'status' => 'deleted',
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                'id = ?',
                [$commentId]
            );

            Logger::audit(Auth::id(), 'deleted_comment', ['comment_id' => $commentId]);

            Response::success([], 'Comment deleted');
            break;

        default:
            Response::error('Invalid action');
    }
}
