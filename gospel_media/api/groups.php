<?php
/**
 * CRC Groups API
 * GET/POST /gospel_media/api/groups.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

switch ($action) {
    case 'list':
        // GET - List groups
        $filter = $_GET['filter'] ?? 'all';
        $primaryCong = Auth::primaryCongregation();

        $params = [];
        $conditions = ["g.status = 'active'"];

        if ($filter === 'my') {
            $conditions[] = "gm.user_id = ?";
            $params[] = Auth::id();
        } elseif ($filter === 'community') {
            $conditions[] = "g.group_type = 'community'";
        } elseif ($filter === 'sell') {
            $conditions[] = "g.group_type = 'sell'";
        }

        // Only show global groups and user's congregation groups
        if ($primaryCong) {
            $conditions[] = "(g.scope = 'global' OR g.congregation_id = ?)";
            $params[] = $primaryCong['id'];
        } else {
            $conditions[] = "g.scope = 'global'";
        }

        // Privacy enforcement: only show private groups if user is a member (unless admin)
        if (!Auth::isAdmin()) {
            $conditions[] = "(g.privacy = 'public' OR EXISTS (SELECT 1 FROM group_members gm2 WHERE gm2.group_id = g.id AND gm2.user_id = ? AND gm2.status = 'active'))";
            $params[] = Auth::id();
        }

        $where = implode(' AND ', $conditions);

        $joinClause = $filter === 'my'
            ? "JOIN group_members gm ON g.id = gm.group_id AND gm.status = 'active'"
            : "LEFT JOIN group_members gm ON g.id = gm.group_id AND gm.user_id = " . Auth::id() . " AND gm.status = 'active'";

        $groups = Database::fetchAll(
            "SELECT g.*,
                    u.name as creator_name,
                    c.name as congregation_name,
                    (SELECT COUNT(*) FROM group_members WHERE group_id = g.id AND status = 'active') as member_count,
                    (SELECT COUNT(*) FROM posts WHERE group_id = g.id AND status = 'active') as post_count,
                    gm.role as user_role,
                    gm.status as user_status
             FROM `groups` g
             {$joinClause}
             JOIN users u ON g.created_by = u.id
             LEFT JOIN congregations c ON g.congregation_id = c.id
             WHERE {$where}
             GROUP BY g.id
             ORDER BY g.created_at DESC",
            $params
        );

        Response::success(['groups' => $groups]);
        break;

    case 'get':
        // GET - Get single group
        $groupId = (int)($_GET['id'] ?? 0);

        if (!$groupId) {
            Response::error('Group ID required');
        }

        $group = Database::fetchOne(
            "SELECT g.*,
                    u.name as creator_name,
                    c.name as congregation_name,
                    (SELECT COUNT(*) FROM group_members WHERE group_id = g.id AND status = 'active') as member_count,
                    (SELECT COUNT(*) FROM posts WHERE group_id = g.id AND status = 'active') as post_count
             FROM `groups` g
             JOIN users u ON g.created_by = u.id
             LEFT JOIN congregations c ON g.congregation_id = c.id
             WHERE g.id = ? AND g.status = 'active'",
            [$groupId]
        );

        if (!$group) {
            Response::notFound('Group not found');
        }

        // Check if user is member
        $membership = Database::fetchOne(
            "SELECT * FROM group_members WHERE group_id = ? AND user_id = ?",
            [$groupId, Auth::id()]
        );

        // Privacy enforcement: non-members can't view private group details
        if ($group['privacy'] === 'private' && !$membership && !Auth::isAdmin()) {
            // Return limited info for private groups
            Response::success(['group' => [
                'id' => $group['id'],
                'name' => $group['name'],
                'description' => $group['description'],
                'group_type' => $group['group_type'],
                'privacy' => $group['privacy'],
                'member_count' => $group['member_count'],
                'cover_image' => $group['cover_image'],
                'user_role' => null,
                'user_status' => null,
                'is_private' => true
            ]]);
            break;
        }

        $group['user_role'] = $membership['role'] ?? null;
        $group['user_status'] = $membership['status'] ?? null;

        Response::success(['group' => $group]);
        break;

    case 'join':
        // POST - Join group
        Response::requirePost();
        CSRF::require();

        $groupId = (int) input('group_id');

        $group = Database::fetchOne(
            "SELECT * FROM `groups` WHERE id = ? AND status = 'active'",
            [$groupId]
        );

        if (!$group) {
            Response::notFound('Group not found');
        }

        // Check if already member
        $existing = Database::fetchOne(
            "SELECT * FROM group_members WHERE group_id = ? AND user_id = ?",
            [$groupId, Auth::id()]
        );

        if ($existing) {
            if ($existing['status'] === 'active') {
                Response::error('Already a member');
            } elseif ($existing['status'] === 'banned') {
                Response::forbidden('You are banned from this group');
            } elseif ($existing['status'] === 'pending') {
                Response::error('Your request is pending approval');
            }
        }

        // Check privacy
        $status = $group['privacy'] === 'private' ? 'pending' : 'active';

        Database::insert('group_members', [
            'group_id' => $groupId,
            'user_id' => Auth::id(),
            'role' => 'member',
            'status' => $status,
            'joined_at' => date('Y-m-d H:i:s')
        ]);

        Logger::audit(Auth::id(), 'joined_group', ['group_id' => $groupId]);

        $message = $status === 'pending' ? 'Join request sent' : 'Joined group';
        Response::success(['status' => $status], $message);
        break;

    case 'leave':
        // POST - Leave group
        Response::requirePost();
        CSRF::require();

        $groupId = (int) input('group_id');

        $membership = Database::fetchOne(
            "SELECT * FROM group_members WHERE group_id = ? AND user_id = ?",
            [$groupId, Auth::id()]
        );

        if (!$membership) {
            Response::error('Not a member');
        }

        // Can't leave if you're the only admin
        if ($membership['role'] === 'admin') {
            $adminCount = Database::fetchOne(
                "SELECT COUNT(*) as cnt FROM group_members WHERE group_id = ? AND role = 'admin' AND status = 'active'",
                [$groupId]
            )['cnt'];

            if ($adminCount <= 1) {
                Response::error('Cannot leave: you are the only admin. Transfer ownership first.');
            }
        }

        Database::delete('group_members', 'group_id = ? AND user_id = ?', [$groupId, Auth::id()]);

        Logger::audit(Auth::id(), 'left_group', ['group_id' => $groupId]);

        Response::success([], 'Left group');
        break;

    case 'create':
        // POST - Create group (admin only)
        Response::requirePost();
        CSRF::require();

        if (!Auth::isAdmin()) {
            $primaryCong = Auth::primaryCongregation();
            if (!$primaryCong || !Auth::isCongregationAdmin($primaryCong['id'])) {
                Response::forbidden('Only admins can create groups');
            }
        }

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $groupType = $_POST['group_type'] ?? 'community';
        $scope = $_POST['scope'] ?? 'congregation';
        $privacy = $_POST['privacy'] ?? 'public';

        if (empty($name)) {
            Response::error('Name is required');
        }

        if (strlen($name) > 255) {
            Response::error('Name too long');
        }

        // Generate slug
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($name));
        $slug = trim($slug, '-');

        $primaryCong = Auth::primaryCongregation();
        $congregationId = $scope === 'congregation' && $primaryCong ? $primaryCong['id'] : null;

        // Check for duplicate slug
        $existing = Database::fetchOne(
            "SELECT id FROM `groups` WHERE slug = ? AND congregation_id <=> ?",
            [$slug, $congregationId]
        );

        if ($existing) {
            $slug .= '-' . time();
        }

        $groupId = Database::insert('groups', [
            'congregation_id' => $congregationId,
            'name' => $name,
            'slug' => $slug,
            'description' => $description ?: null,
            'group_type' => in_array($groupType, ['community', 'sell']) ? $groupType : 'community',
            'scope' => in_array($scope, ['global', 'congregation']) ? $scope : 'congregation',
            'privacy' => in_array($privacy, ['public', 'private']) ? $privacy : 'public',
            'status' => 'active',
            'created_by' => Auth::id(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Add creator as admin
        Database::insert('group_members', [
            'group_id' => $groupId,
            'user_id' => Auth::id(),
            'role' => 'admin',
            'status' => 'active',
            'joined_at' => date('Y-m-d H:i:s')
        ]);

        Logger::audit(Auth::id(), 'created_group', ['group_id' => $groupId]);

        Response::success(['group_id' => $groupId], 'Group created');
        break;

    case 'update':
        // POST - Update group
        Response::requirePost();
        CSRF::require();

        $groupId = (int) input('group_id');
        $name = trim(input('name'));
        $description = trim(input('description'));
        $privacy = input('privacy');
        $coverImage = input('cover_image');

        if (!$groupId) {
            Response::error('Group ID required');
        }

        $group = Database::fetchOne(
            "SELECT * FROM `groups` WHERE id = ? AND status = 'active'",
            [$groupId]
        );

        if (!$group) {
            Response::notFound('Group not found');
        }

        // Check if user is admin of this group
        $membership = Database::fetchOne(
            "SELECT * FROM group_members WHERE group_id = ? AND user_id = ? AND status = 'active'",
            [$groupId, Auth::id()]
        );

        if (!$membership || !in_array($membership['role'], ['admin', 'moderator'])) {
            if (!Auth::isAdmin()) {
                Response::forbidden('Only group admins can edit this group');
            }
        }

        $updateData = ['updated_at' => date('Y-m-d H:i:s')];

        if (!empty($name)) {
            if (strlen($name) > 255) {
                Response::error('Name too long');
            }
            $updateData['name'] = $name;
        }

        if ($description !== null) {
            $updateData['description'] = $description ?: null;
        }

        if ($privacy && in_array($privacy, ['public', 'private'])) {
            $updateData['privacy'] = $privacy;
        }

        if ($coverImage !== null) {
            $updateData['cover_image'] = $coverImage ?: null;
        }

        Database::update('groups', $updateData, 'id = ?', [$groupId]);

        Logger::audit(Auth::id(), 'updated_group', ['group_id' => $groupId]);

        Response::success(['group_id' => $groupId], 'Group updated');
        break;

    case 'delete':
        // POST - Delete group (admin only)
        Response::requirePost();
        CSRF::require();

        $groupId = (int) input('group_id');

        if (!$groupId) {
            Response::error('Group ID required');
        }

        $group = Database::fetchOne(
            "SELECT * FROM `groups` WHERE id = ? AND status = 'active'",
            [$groupId]
        );

        if (!$group) {
            Response::notFound('Group not found');
        }

        // Check if user is admin of this group or site admin
        $membership = Database::fetchOne(
            "SELECT * FROM group_members WHERE group_id = ? AND user_id = ? AND role = 'admin' AND status = 'active'",
            [$groupId, Auth::id()]
        );

        if (!$membership && !Auth::isAdmin()) {
            Response::forbidden('Only group admins can delete this group');
        }

        // Soft delete the group (set to inactive)
        Database::update(
            'groups',
            [
                'status' => 'inactive',
                'updated_at' => date('Y-m-d H:i:s')
            ],
            'id = ?',
            [$groupId]
        );

        Logger::audit(Auth::id(), 'deleted_group', ['group_id' => $groupId]);

        Response::success([], 'Group deleted');
        break;

    case 'members':
        // GET - List group members
        $groupId = (int)($_GET['group_id'] ?? 0);

        if (!$groupId) {
            Response::error('Group ID required');
        }

        $group = Database::fetchOne(
            "SELECT * FROM `groups` WHERE id = ? AND status = 'active'",
            [$groupId]
        );

        if (!$group) {
            Response::notFound('Group not found');
        }

        $members = Database::fetchAll(
            "SELECT gm.*, u.name, u.avatar, u.email
             FROM group_members gm
             JOIN users u ON gm.user_id = u.id
             WHERE gm.group_id = ? AND gm.status = 'active'
             ORDER BY gm.role = 'admin' DESC, gm.role = 'moderator' DESC, gm.joined_at ASC",
            [$groupId]
        );

        Response::success(['members' => $members]);
        break;

    case 'update_member':
        // POST - Update member role
        Response::requirePost();
        CSRF::require();

        $groupId = (int) input('group_id');
        $memberId = (int) input('user_id');
        $newRole = input('role');
        $newStatus = input('status');

        if (!$groupId || !$memberId) {
            Response::error('Group ID and User ID required');
        }

        // Check if current user is admin of this group
        $currentMembership = Database::fetchOne(
            "SELECT * FROM group_members WHERE group_id = ? AND user_id = ? AND status = 'active'",
            [$groupId, Auth::id()]
        );

        if (!$currentMembership || $currentMembership['role'] !== 'admin') {
            if (!Auth::isAdmin()) {
                Response::forbidden('Only group admins can manage members');
            }
        }

        $targetMembership = Database::fetchOne(
            "SELECT * FROM group_members WHERE group_id = ? AND user_id = ?",
            [$groupId, $memberId]
        );

        if (!$targetMembership) {
            Response::notFound('Member not found');
        }

        $updateData = [];

        if ($newRole && in_array($newRole, ['member', 'moderator', 'admin'])) {
            $updateData['role'] = $newRole;
        }

        if ($newStatus && in_array($newStatus, ['active', 'banned'])) {
            $updateData['status'] = $newStatus;
        }

        if (empty($updateData)) {
            Response::error('No changes specified');
        }

        Database::update('group_members', $updateData, 'group_id = ? AND user_id = ?', [$groupId, $memberId]);

        Logger::audit(Auth::id(), 'updated_group_member', [
            'group_id' => $groupId,
            'target_user_id' => $memberId,
            'changes' => $updateData
        ]);

        Response::success([], 'Member updated');
        break;

    case 'remove_member':
        // POST - Remove/kick member from group
        Response::requirePost();
        CSRF::require();

        $groupId = (int) input('group_id');
        $memberId = (int) input('user_id');

        if (!$groupId || !$memberId) {
            Response::error('Group ID and User ID required');
        }

        // Check if current user is admin/moderator of this group
        $currentMembership = Database::fetchOne(
            "SELECT * FROM group_members WHERE group_id = ? AND user_id = ? AND status = 'active'",
            [$groupId, Auth::id()]
        );

        if (!$currentMembership || !in_array($currentMembership['role'], ['admin', 'moderator'])) {
            if (!Auth::isAdmin()) {
                Response::forbidden('Only group admins/moderators can remove members');
            }
        }

        // Can't remove yourself
        if ($memberId == Auth::id()) {
            Response::error('Use leave action to leave the group');
        }

        $targetMembership = Database::fetchOne(
            "SELECT * FROM group_members WHERE group_id = ? AND user_id = ?",
            [$groupId, $memberId]
        );

        if (!$targetMembership) {
            Response::notFound('Member not found');
        }

        // Moderators can't remove admins
        if ($currentMembership && $currentMembership['role'] === 'moderator' && $targetMembership['role'] === 'admin') {
            Response::forbidden('Moderators cannot remove admins');
        }

        Database::delete('group_members', 'group_id = ? AND user_id = ?', [$groupId, $memberId]);

        Logger::audit(Auth::id(), 'removed_group_member', [
            'group_id' => $groupId,
            'removed_user_id' => $memberId
        ]);

        Response::success([], 'Member removed');
        break;

    default:
        Response::error('Invalid action');
}
