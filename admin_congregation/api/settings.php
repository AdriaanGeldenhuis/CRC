<?php
/**
 * CRC Congregation Settings API
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();

$primaryCong = Auth::primaryCongregation();
if (!$primaryCong || !Auth::isCongregationAdmin($primaryCong['id'])) {
    Response::forbidden('Admin access required');
}

$congregationId = $primaryCong['id'];
$userId = Auth::id();

Response::requirePost();
CSRF::require();

$action = input('action');

switch ($action) {
    case 'update_general':
        $name = trim(input('name'));
        $description = trim(input('description'));
        $phone = trim(input('phone'));
        $email = trim(input('email'));
        $website = trim(input('website'));

        if (!$name) {
            Response::error('Congregation name is required');
        }

        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Invalid email address');
        }

        if ($website && !filter_var($website, FILTER_VALIDATE_URL)) {
            // Try adding https:// if missing
            if (filter_var('https://' . $website, FILTER_VALIDATE_URL)) {
                $website = 'https://' . $website;
            } else {
                Response::error('Invalid website URL');
            }
        }

        Database::update('congregations', [
            'name' => $name,
            'description' => $description ?: null,
            'phone' => $phone ?: null,
            'email' => $email ?: null,
            'website' => $website ?: null,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$congregationId]);

        Logger::audit($userId, 'updated_congregation_general', [
            'congregation_id' => $congregationId
        ]);

        Response::success(['message' => 'General information updated']);
        break;

    case 'update_location':
        $address = trim(input('address'));
        $city = trim(input('city'));
        $province = trim(input('province'));

        Database::update('congregations', [
            'address' => $address ?: null,
            'city' => $city ?: null,
            'province' => $province ?: null,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$congregationId]);

        Logger::audit($userId, 'updated_congregation_location', [
            'congregation_id' => $congregationId
        ]);

        Response::success(['message' => 'Location updated']);
        break;

    case 'update_membership':
        $joinMode = input('join_mode');

        if (!in_array($joinMode, ['open', 'approval', 'invite'])) {
            Response::error('Invalid join mode');
        }

        Database::update('congregations', [
            'join_mode' => $joinMode,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$congregationId]);

        Logger::audit($userId, 'updated_congregation_membership', [
            'congregation_id' => $congregationId,
            'join_mode' => $joinMode
        ]);

        Response::success(['message' => 'Membership settings updated']);
        break;

    case 'create_position':
        $name = trim(input('name'));
        $description = trim(input('description'));

        if (!$name) {
            Response::error('Position name is required');
        }

        // Check if position already exists
        $existing = Database::fetchOne(
            "SELECT id FROM church_positions WHERE congregation_id = ? AND name = ?",
            [$congregationId, $name]
        );

        if ($existing) {
            Response::error('A position with this name already exists');
        }

        $positionId = Database::insert('church_positions', [
            'congregation_id' => $congregationId,
            'name' => $name,
            'description' => $description ?: null,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        Logger::audit($userId, 'created_church_position', [
            'congregation_id' => $congregationId,
            'position_id' => $positionId,
            'name' => $name
        ]);

        Response::success([
            'message' => 'Position created',
            'position' => [
                'id' => $positionId,
                'name' => $name,
                'description' => $description
            ]
        ]);
        break;

    case 'update_position':
        $positionId = (int) input('id');
        $name = trim(input('name'));
        $description = trim(input('description'));

        if (!$positionId) {
            Response::error('Position ID required');
        }

        if (!$name) {
            Response::error('Position name is required');
        }

        // Verify position belongs to this congregation
        $position = Database::fetchOne(
            "SELECT * FROM church_positions WHERE id = ? AND congregation_id = ?",
            [$positionId, $congregationId]
        );

        if (!$position) {
            Response::error('Position not found');
        }

        // Check for duplicate name (excluding current position)
        $existing = Database::fetchOne(
            "SELECT id FROM church_positions WHERE congregation_id = ? AND name = ? AND id != ?",
            [$congregationId, $name, $positionId]
        );

        if ($existing) {
            Response::error('A position with this name already exists');
        }

        Database::update('church_positions', [
            'name' => $name,
            'description' => $description ?: null
        ], 'id = ?', [$positionId]);

        Logger::audit($userId, 'updated_church_position', [
            'congregation_id' => $congregationId,
            'position_id' => $positionId,
            'name' => $name
        ]);

        Response::success(['message' => 'Position updated']);
        break;

    case 'delete_position':
        $positionId = (int) input('id');

        if (!$positionId) {
            Response::error('Position ID required');
        }

        // Verify position belongs to this congregation
        $position = Database::fetchOne(
            "SELECT * FROM church_positions WHERE id = ? AND congregation_id = ?",
            [$positionId, $congregationId]
        );

        if (!$position) {
            Response::error('Position not found');
        }

        // Check if position is assigned to any members
        $assignedCount = Database::fetchOne(
            "SELECT COUNT(*) as count FROM user_church_positions WHERE position_id = ?",
            [$positionId]
        );

        if ($assignedCount && $assignedCount['count'] > 0) {
            Response::error('Cannot delete position that is assigned to members. Remove assignments first.');
        }

        Database::delete('church_positions', 'id = ?', [$positionId]);

        Logger::audit($userId, 'deleted_church_position', [
            'congregation_id' => $congregationId,
            'position_id' => $positionId,
            'position_name' => $position['name']
        ]);

        Response::success(['message' => 'Position deleted']);
        break;

    case 'get_positions':
        $positions = Database::fetchAll(
            "SELECT cp.*,
                    (SELECT COUNT(*) FROM user_church_positions ucp WHERE ucp.position_id = cp.id) as member_count
             FROM church_positions cp
             WHERE cp.congregation_id = ?
             ORDER BY cp.name",
            [$congregationId]
        );

        Response::success(['positions' => $positions]);
        break;

    default:
        Response::error('Invalid action');
}
