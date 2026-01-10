<?php
/**
 * CRC Congregation Invites API
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

switch ($action) {
    case 'create':
        $role = input('role');
        $maxUses = input('max_uses');
        $expiresAt = input('expires_at');

        // Validate role
        $allowedRoles = ['member', 'leader', 'admin'];
        if (!in_array($role, $allowedRoles)) {
            Response::error('Invalid role');
        }

        // Generate unique token
        $token = bin2hex(random_bytes(16));
        $tokenHash = hash('sha256', $token);

        // Format expiry date
        $expiryTimestamp = null;
        if ($expiresAt) {
            $expiryTimestamp = date('Y-m-d 23:59:59', strtotime($expiresAt));
        }

        Database::insert('congregation_invites', [
            'congregation_id' => $congregationId,
            'token_hash' => $token, // Store readable token for URL
            'role' => $role,
            'max_uses' => $maxUses ?: null,
            'use_count' => 0,
            'expires_at' => $expiryTimestamp,
            'created_by' => $adminId,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $inviteUrl = APP_URL . '/join/' . $token;

        Logger::audit($adminId, 'created_invite', [
            'congregation_id' => $congregationId,
            'role' => $role,
            'max_uses' => $maxUses
        ]);

        Response::success([
            'message' => 'Invite created',
            'invite_url' => $inviteUrl,
            'token' => $token
        ]);
        break;

    case 'revoke':
        $inviteId = (int) input('invite_id');

        if (!$inviteId) {
            Response::error('Invite ID required');
        }

        $invite = Database::fetchOne(
            "SELECT * FROM congregation_invites WHERE id = ? AND congregation_id = ?",
            [$inviteId, $congregationId]
        );

        if (!$invite) {
            Response::error('Invite not found');
        }

        if ($invite['revoked_at']) {
            Response::error('Invite already revoked');
        }

        Database::update(
            'congregation_invites',
            ['revoked_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$inviteId]
        );

        Logger::audit($adminId, 'revoked_invite', [
            'invite_id' => $inviteId,
            'congregation_id' => $congregationId
        ]);

        Response::success(['message' => 'Invite revoked']);
        break;

    default:
        Response::error('Invalid action');
}
