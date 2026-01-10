<?php
/**
 * CRC Congregation Positions API
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Response::requirePost();
Auth::requireAuth();
CSRF::require();

$primaryCong = Auth::primaryCongregation();
if (!$primaryCong || !Auth::isCongregationAdmin($primaryCong['id'])) {
    Response::forbidden('Admin access required');
}

$congregationId = $primaryCong['id'];
$adminId = Auth::id();

$action = input('action');
$userId = (int) input('user_id');
$positionId = (int) input('position_id');

switch ($action) {
    case 'assign':
        if (!$userId || !$positionId) {
            Response::error('User ID and Position ID required');
        }

        // Verify position belongs to this congregation
        $position = Database::fetchOne(
            "SELECT * FROM church_positions WHERE id = ? AND congregation_id = ?",
            [$positionId, $congregationId]
        );

        if (!$position) {
            Response::error('Invalid position');
        }

        // Verify user is a member
        $membership = Database::fetchOne(
            "SELECT * FROM user_congregations WHERE user_id = ? AND congregation_id = ? AND status = 'active'",
            [$userId, $congregationId]
        );

        if (!$membership) {
            Response::error('User is not an active member');
        }

        // Check if already assigned
        $existing = Database::fetchOne(
            "SELECT * FROM user_church_positions WHERE user_id = ? AND position_id = ? AND congregation_id = ?",
            [$userId, $positionId, $congregationId]
        );

        if ($existing) {
            // Reactivate if inactive
            if (!$existing['is_active']) {
                Database::update(
                    'user_church_positions',
                    ['is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')],
                    'id = ?',
                    [$existing['id']]
                );
                Response::success(['message' => 'Position reactivated']);
            }
            Response::error('User already has this position');
        }

        // Assign position
        Database::insert('user_church_positions', [
            'user_id' => $userId,
            'position_id' => $positionId,
            'congregation_id' => $congregationId,
            'appointed_at' => date('Y-m-d'),
            'appointed_by' => $adminId,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        Logger::audit($adminId, 'assigned_position', [
            'user_id' => $userId,
            'position_id' => $positionId,
            'position_name' => $position['name']
        ]);

        Response::success(['message' => 'Position assigned']);
        break;

    case 'remove':
        if (!$userId || !$positionId) {
            Response::error('User ID and Position ID required');
        }

        $assignment = Database::fetchOne(
            "SELECT * FROM user_church_positions WHERE user_id = ? AND position_id = ? AND congregation_id = ? AND is_active = 1",
            [$userId, $positionId, $congregationId]
        );

        if (!$assignment) {
            Response::error('Position assignment not found');
        }

        Database::update(
            'user_church_positions',
            ['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$assignment['id']]
        );

        Logger::audit($adminId, 'removed_position', [
            'user_id' => $userId,
            'position_id' => $positionId
        ]);

        Response::success(['message' => 'Position removed']);
        break;

    default:
        Response::error('Invalid action');
}
