<?php
/**
 * CRC Posts API
 * GET/POST /gospel_media/api/posts.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

switch ($action) {
    case 'list':
        // GET - List posts
        $scope = $_GET['scope'] ?? 'all';
        $groupId = (int)($_GET['group_id'] ?? 0);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(50, (int)($_GET['per_page'] ?? DEFAULT_PAGE_SIZE));
        $offset = ($page - 1) * $perPage;

        $primaryCong = Auth::primaryCongregation();
        $userId = Auth::id();

        $params = [$userId];
        $countParams = [];
        $conditions = ["p.status = 'active'"];

        // Handle group posts
        if ($groupId) {
            // Check group exists and user has access
            $group = Database::fetchOne(
                "SELECT * FROM `groups` WHERE id = ? AND status = 'active'",
                [$groupId]
            );

            if (!$group) {
                Response::notFound('Group not found');
            }

            // Check membership for private groups
            if ($group['privacy'] === 'private' && !Auth::isAdmin()) {
                $membership = Database::fetchOne(
                    "SELECT * FROM group_members WHERE group_id = ? AND user_id = ? AND status = 'active'",
                    [$groupId, Auth::id()]
                );
                if (!$membership) {
                    Response::forbidden('You must be a member to view this group\'s posts');
                }
            }

            $conditions[] = "p.group_id = ?";
            $params[] = $groupId;
            $countParams[] = $groupId;
        } else {
            $conditions[] = "p.group_id IS NULL";

            if ($scope === 'global') {
                $conditions[] = "p.scope = 'global'";
            } elseif ($scope === 'congregation' && $primaryCong) {
                $conditions[] = "p.congregation_id = ?";
                $params[] = $primaryCong['id'];
                $countParams[] = $primaryCong['id'];
            } else {
                // All - show global and user's congregation
                if ($primaryCong) {
                    $conditions[] = "(p.scope = 'global' OR p.congregation_id = ?)";
                    $params[] = $primaryCong['id'];
                    $countParams[] = $primaryCong['id'];
                } else {
                    $conditions[] = "p.scope = 'global'";
                }
            }
        }

        $where = implode(' AND ', $conditions);

        $posts = Database::fetchAll(
            "SELECT p.*,
                    u.name as author_name, u.avatar as author_avatar,
                    c.name as congregation_name,
                    (SELECT COUNT(*) FROM reactions WHERE reactable_type = 'post' AND reactable_id = p.id) as reaction_count,
                    (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND status = 'active') as comment_count,
                    (SELECT reaction_type FROM reactions WHERE reactable_type = 'post' AND reactable_id = p.id AND user_id = ?) as user_reaction
             FROM posts p
             JOIN users u ON p.user_id = u.id
             LEFT JOIN congregations c ON p.congregation_id = c.id
             WHERE {$where}
             ORDER BY p.is_pinned DESC, p.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        // Get total count for pagination
        $totalPosts = Database::fetchColumn(
            "SELECT COUNT(*) FROM posts p WHERE {$where}",
            $countParams
        );

        // Add time_ago and ownership info
        foreach ($posts as &$post) {
            $post['time_ago'] = time_ago($post['created_at']);
            $post['is_owner'] = ($post['user_id'] == Auth::id());
            $post['can_edit'] = ($post['user_id'] == Auth::id() || Auth::isAdmin());
        }

        Response::success([
            'posts' => $posts,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => (int)$totalPosts,
                'total_pages' => ceil($totalPosts / $perPage)
            ]
        ]);
        break;

    case 'create':
        // POST - Create post
        Response::requirePost();
        CSRF::require();

        $content = trim($_POST['content'] ?? '');
        $scope = $_POST['scope'] ?? 'congregation';
        $groupId = (int)($_POST['group_id'] ?? 0) ?: null;

        if (empty($content)) {
            Response::error('Content is required');
        }

        if (strlen($content) > 5000) {
            Response::error('Content too long');
        }

        $primaryCong = Auth::primaryCongregation();

        // Validate scope
        if ($scope === 'global' && !Auth::isAdmin()) {
            Response::forbidden('Only admins can post globally');
        }

        $congregationId = null;
        if ($scope === 'congregation') {
            if (!$primaryCong) {
                Response::error('No congregation');
            }
            $congregationId = $primaryCong['id'];
        }

        // Validate group membership if posting to a group
        if ($groupId) {
            $group = Database::fetchOne(
                "SELECT * FROM `groups` WHERE id = ? AND status = 'active'",
                [$groupId]
            );

            if (!$group) {
                Response::notFound('Group not found');
            }

            // Check if user is a member of the group
            $membership = Database::fetchOne(
                "SELECT * FROM group_members WHERE group_id = ? AND user_id = ? AND status = 'active'",
                [$groupId, Auth::id()]
            );

            if (!$membership && !Auth::isAdmin()) {
                Response::forbidden('You must be a member of this group to post');
            }

            // Set scope to group's scope and congregation if applicable
            $scope = 'group';
            $congregationId = $group['congregation_id'];
        }

        // Handle media uploads
        $media = [];
        if (!empty($_FILES['media'])) {
            $files = $_FILES['media'];
            $count = is_array($files['name']) ? count($files['name']) : 1;

            for ($i = 0; $i < min($count, 5); $i++) {
                $file = [
                    'name' => is_array($files['name']) ? $files['name'][$i] : $files['name'],
                    'type' => is_array($files['type']) ? $files['type'][$i] : $files['type'],
                    'tmp_name' => is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'],
                    'error' => is_array($files['error']) ? $files['error'][$i] : $files['error'],
                    'size' => is_array($files['size']) ? $files['size'][$i] : $files['size'],
                ];

                if ($file['error'] === UPLOAD_ERR_OK) {
                    $upload = new Upload($file);
                    $upload->validateImage()->validateSize();

                    if ($upload->isValid()) {
                        $path = $upload->saveImage('posts/' . date('Y/m'));
                        if ($path) {
                            $media[] = [
                                'type' => 'image',
                                'url' => '/uploads/' . $path
                            ];
                        }
                    }
                }
            }
        }

        // Create post
        $postId = Database::insert('posts', [
            'user_id' => Auth::id(),
            'congregation_id' => $congregationId,
            'group_id' => $groupId,
            'scope' => $scope,
            'content' => $content,
            'media' => !empty($media) ? json_encode($media) : null,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        Logger::audit(Auth::id(), 'created_post', ['post_id' => $postId]);

        Response::success(['post_id' => $postId], 'Post created');
        break;

    case 'delete':
        // POST - Delete post
        Response::requirePost();
        CSRF::require();

        $postId = (int) input('post_id');

        $post = Database::fetchOne(
            "SELECT * FROM posts WHERE id = ?",
            [$postId]
        );

        if (!$post) {
            Response::notFound('Post not found');
        }

        // Check ownership or admin
        if ($post['user_id'] !== Auth::id() && !Auth::isAdmin()) {
            $primaryCong = Auth::primaryCongregation();
            if (!$primaryCong || !Auth::isCongregationAdmin($primaryCong['id'])) {
                Response::forbidden('Cannot delete this post');
            }
        }

        Database::update(
            'posts',
            ['status' => 'deleted', 'updated_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$postId]
        );

        Logger::audit(Auth::id(), 'deleted_post', ['post_id' => $postId]);

        Response::success([], 'Post deleted');
        break;

    case 'update':
        // POST - Update post
        Response::requirePost();
        CSRF::require();

        $postId = (int) input('post_id');
        $content = trim(input('content'));
        $scope = input('scope');

        if (!$postId) {
            Response::error('Post ID required');
        }

        if (empty($content)) {
            Response::error('Content is required');
        }

        if (strlen($content) > 5000) {
            Response::error('Content too long');
        }

        $post = Database::fetchOne(
            "SELECT * FROM posts WHERE id = ? AND status = 'active'",
            [$postId]
        );

        if (!$post) {
            Response::notFound('Post not found');
        }

        // Check ownership or admin
        if ($post['user_id'] !== Auth::id() && !Auth::isAdmin()) {
            $primaryCong = Auth::primaryCongregation();
            if (!$primaryCong || !Auth::isCongregationAdmin($primaryCong['id'])) {
                Response::forbidden('Cannot edit this post');
            }
        }

        // Validate scope - only admin can set global
        if ($scope === 'global' && !Auth::isAdmin()) {
            $scope = $post['scope']; // Keep original scope
        }

        $updateData = [
            'content' => $content,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($scope && in_array($scope, ['global', 'congregation'])) {
            $updateData['scope'] = $scope;
        }

        Database::update('posts', $updateData, 'id = ?', [$postId]);

        Logger::audit(Auth::id(), 'updated_post', ['post_id' => $postId]);

        Response::success(['post_id' => $postId], 'Post updated');
        break;

    case 'pin':
        // POST - Pin/Unpin post (admin only)
        Response::requirePost();
        CSRF::require();

        if (!Auth::isAdmin()) {
            $primaryCong = Auth::primaryCongregation();
            if (!$primaryCong || !Auth::isCongregationAdmin($primaryCong['id'])) {
                Response::forbidden('Only admins can pin posts');
            }
        }

        $postId = (int) input('post_id');

        $post = Database::fetchOne(
            "SELECT * FROM posts WHERE id = ? AND status = 'active'",
            [$postId]
        );

        if (!$post) {
            Response::notFound('Post not found');
        }

        $isPinned = !$post['is_pinned'];

        Database::update(
            'posts',
            [
                'is_pinned' => $isPinned ? 1 : 0,
                'pinned_by' => $isPinned ? Auth::id() : null,
                'pinned_at' => $isPinned ? date('Y-m-d H:i:s') : null,
                'updated_at' => date('Y-m-d H:i:s')
            ],
            'id = ?',
            [$postId]
        );

        Logger::audit(Auth::id(), $isPinned ? 'pinned_post' : 'unpinned_post', ['post_id' => $postId]);

        Response::success(['pinned' => $isPinned], $isPinned ? 'Post pinned' : 'Post unpinned');
        break;

    default:
        Response::error('Invalid action');
}
