<?php
/**
 * CRC Congregation Members API
 * POST /admin_congregation/api/members.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

// Require POST and auth
Response::requirePost();
Auth::requireAuth();
CSRF::require();

// Get primary congregation and check admin access
$primaryCong = Auth::primaryCongregation();
if (!$primaryCong || !Auth::isCongregationAdmin($primaryCong['id'])) {
    Response::forbidden('Admin access required');
}

$congregationId = $primaryCong['id'];
$adminId = Auth::id();

$action = input('action');
$userId = (int) input('user_id');

if (!$userId) {
    Response::error('User ID required');
}

switch ($action) {
    case 'approve':
        // Approve pending member
        $membership = Database::fetchOne(
            "SELECT * FROM user_congregations WHERE user_id = ? AND congregation_id = ? AND status = 'pending'",
            [$userId, $congregationId]
        );

        if (!$membership) {
            Response::error('No pending request found');
        }

        Database::update(
            'user_congregations',
            [
                'status' => 'active',
                'approved_by' => $adminId,
                'approved_at' => date('Y-m-d H:i:s'),
                'joined_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            'id = ?',
            [$membership['id']]
        );

        // Create notification for user
        Database::insert('notifications', [
            'user_id' => $userId,
            'type' => 'approval_status',
            'title' => 'Membership Approved',
            'message' => 'Your request to join ' . $primaryCong['name'] . ' has been approved!',
            'link' => '/home/',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        Logger::audit($adminId, 'approved_member', [
            'congregation_id' => $congregationId,
            'member_user_id' => $userId
        ]);

        Response::success(['message' => 'Member approved']);
        break;

    case 'reject':
        // Reject pending member
        $membership = Database::fetchOne(
            "SELECT * FROM user_congregations WHERE user_id = ? AND congregation_id = ? AND status = 'pending'",
            [$userId, $congregationId]
        );

        if (!$membership) {
            Response::error('No pending request found');
        }

        Database::delete(
            'user_congregations',
            'id = ?',
            [$membership['id']]
        );

        // Create notification for user
        Database::insert('notifications', [
            'user_id' => $userId,
            'type' => 'approval_status',
            'title' => 'Membership Request',
            'message' => 'Your request to join ' . $primaryCong['name'] . ' was not approved.',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        Logger::audit($adminId, 'rejected_member', [
            'congregation_id' => $congregationId,
            'member_user_id' => $userId
        ]);

        Response::success(['message' => 'Request rejected']);
        break;

    case 'update_role':
        // Update member role
        $newRole = input('role');
        $allowedRoles = ['member', 'leader', 'admin'];

        if (!in_array($newRole, $allowedRoles)) {
            Response::error('Invalid role');
        }

        // Check membership exists
        $membership = Database::fetchOne(
            "SELECT * FROM user_congregations WHERE user_id = ? AND congregation_id = ? AND status = 'active'",
            [$userId, $congregationId]
        );

        if (!$membership) {
            Response::error('Member not found');
        }

        // Cannot change own role
        if ($userId === $adminId) {
            Response::error('Cannot change your own role');
        }

        // Pastor role can only be assigned by super admin
        if ($membership['role'] === 'pastor') {
            Response::error('Cannot change pastor role');
        }

        Database::update(
            'user_congregations',
            ['role' => $newRole, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$membership['id']]
        );

        Logger::audit($adminId, 'updated_member_role', [
            'congregation_id' => $congregationId,
            'member_user_id' => $userId,
            'old_role' => $membership['role'],
            'new_role' => $newRole
        ]);

        Response::success(['message' => 'Role updated']);
        break;

    case 'remove':
        // Remove member
        $membership = Database::fetchOne(
            "SELECT * FROM user_congregations WHERE user_id = ? AND congregation_id = ? AND status = 'active'",
            [$userId, $congregationId]
        );

        if (!$membership) {
            Response::error('Member not found');
        }

        // Cannot remove self
        if ($userId === $adminId) {
            Response::error('Cannot remove yourself');
        }

        // Cannot remove pastor
        if ($membership['role'] === 'pastor') {
            Response::error('Cannot remove pastor');
        }

        Database::update(
            'user_congregations',
            [
                'status' => 'left',
                'is_primary' => 0,
                'updated_at' => date('Y-m-d H:i:s')
            ],
            'id = ?',
            [$membership['id']]
        );

        Logger::audit($adminId, 'removed_member', [
            'congregation_id' => $congregationId,
            'member_user_id' => $userId
        ]);

        Response::success(['message' => 'Member removed']);
        break;

    default:
        Response::error('Invalid action');
}
