<?php
/**
 * CRC Join Congregation API
 * POST /onboarding/api/join.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

// Require POST and auth
Response::requirePost();
Auth::requireAuth();
CSRF::require();

$action = input('action');
$userId = Auth::id();

switch ($action) {
    case 'join':
        // Join open congregation
        $congregationId = (int) input('congregation_id');

        // Get congregation
        $congregation = Database::fetchOne(
            "SELECT * FROM congregations WHERE id = ? AND status = 'active'",
            [$congregationId]
        );

        if (!$congregation) {
            Response::error('Congregation not found');
        }

        if ($congregation['join_mode'] !== 'open') {
            Response::error('This congregation requires approval to join');
        }

        // Check if already a member
        $existing = Database::fetchOne(
            "SELECT id, status FROM user_congregations WHERE user_id = ? AND congregation_id = ?",
            [$userId, $congregationId]
        );

        if ($existing && $existing['status'] === 'active') {
            Response::error('You are already a member of this congregation');
        }

        // Join
        if ($existing) {
            Database::update(
                'user_congregations',
                [
                    'status' => 'active',
                    'is_primary' => 1,
                    'joined_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                'id = ?',
                [$existing['id']]
            );
        } else {
            Database::insert('user_congregations', [
                'user_id' => $userId,
                'congregation_id' => $congregationId,
                'role' => 'member',
                'status' => 'active',
                'is_primary' => 1,
                'joined_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }

        Logger::audit($userId, 'joined_congregation', ['congregation_id' => $congregationId]);
        Response::success(['redirect' => '/home/'], 'Successfully joined congregation');
        break;

    case 'request':
        // Request to join (approval required)
        $congregationId = (int) input('congregation_id');

        // Get congregation
        $congregation = Database::fetchOne(
            "SELECT * FROM congregations WHERE id = ? AND status = 'active'",
            [$congregationId]
        );

        if (!$congregation) {
            Response::error('Congregation not found');
        }

        if ($congregation['join_mode'] === 'invite_only') {
            Response::error('This congregation is invite only');
        }

        // Check if already a member or pending
        $existing = Database::fetchOne(
            "SELECT id, status FROM user_congregations WHERE user_id = ? AND congregation_id = ?",
            [$userId, $congregationId]
        );

        if ($existing) {
            if ($existing['status'] === 'active') {
                Response::error('You are already a member of this congregation');
            }
            if ($existing['status'] === 'pending') {
                Response::error('You already have a pending request');
            }
        }

        // Create pending membership
        if ($existing) {
            Database::update(
                'user_congregations',
                [
                    'status' => 'pending',
                    'is_primary' => 1,
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                'id = ?',
                [$existing['id']]
            );
        } else {
            Database::insert('user_congregations', [
                'user_id' => $userId,
                'congregation_id' => $congregationId,
                'role' => 'member',
                'status' => 'pending',
                'is_primary' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }

        // TODO: Notify congregation admins

        Logger::audit($userId, 'requested_to_join', ['congregation_id' => $congregationId]);
        Response::success(['message' => 'Join request submitted']);
        break;

    case 'accept_invite':
        // Accept invite
        $inviteToken = input('invite_token');

        if (!$inviteToken) {
            Response::error('Invalid invite token');
        }

        $tokenHash = hash('sha256', $inviteToken);

        // Get invite
        $invite = Database::fetchOne(
            "SELECT ci.*, c.id as congregation_id, c.status as cong_status
             FROM congregation_invites ci
             JOIN congregations c ON ci.congregation_id = c.id
             WHERE ci.token_hash = ?
               AND (ci.expires_at IS NULL OR ci.expires_at > NOW())
               AND ci.revoked_at IS NULL
               AND (ci.max_uses IS NULL OR ci.use_count < ci.max_uses)",
            [$tokenHash]
        );

        if (!$invite) {
            Response::error('Invalid or expired invite');
        }

        if ($invite['cong_status'] !== 'active') {
            Response::error('This congregation is not active');
        }

        // Check if already a member
        $existing = Database::fetchOne(
            "SELECT id, status FROM user_congregations WHERE user_id = ? AND congregation_id = ?",
            [$userId, $invite['congregation_id']]
        );

        if ($existing && $existing['status'] === 'active') {
            Response::error('You are already a member of this congregation');
        }

        // Join with invite role
        if ($existing) {
            Database::update(
                'user_congregations',
                [
                    'role' => $invite['role'],
                    'status' => 'active',
                    'is_primary' => 1,
                    'joined_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                'id = ?',
                [$existing['id']]
            );
        } else {
            Database::insert('user_congregations', [
                'user_id' => $userId,
                'congregation_id' => $invite['congregation_id'],
                'role' => $invite['role'],
                'status' => 'active',
                'is_primary' => 1,
                'joined_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }

        // Increment invite use count
        Database::query(
            "UPDATE congregation_invites SET use_count = use_count + 1 WHERE id = ?",
            [$invite['id']]
        );

        Logger::audit($userId, 'accepted_invite', [
            'congregation_id' => $invite['congregation_id'],
            'invite_id' => $invite['id']
        ]);

        Response::success(['redirect' => '/home/'], 'Invitation accepted');
        break;

    default:
        Response::error('Invalid action');
}
