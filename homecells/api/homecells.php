<?php
/**
 * CRC Homecells API
 * POST /homecells/api/homecells.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();
Response::requirePost();
CSRF::require();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
if (!$primaryCong) {
    Response::error('No congregation membership');
}

$action = input('action', 'list');

switch ($action) {
    case 'join':
        $homecellId = (int)input('homecell_id');

        if (!$homecellId) {
            Response::error('Homecell ID required');
        }

        // Verify homecell exists in congregation
        $homecell = Database::fetchOne(
            "SELECT * FROM homecells WHERE id = ? AND congregation_id = ? AND status = 'active'",
            [$homecellId, $primaryCong['id']]
        );

        if (!$homecell) {
            Response::error('Homecell not found');
        }

        // Check if already member of any homecell
        $existing = Database::fetchOne(
            "SELECT hm.* FROM homecell_members hm
             JOIN homecells h ON hm.homecell_id = h.id
             WHERE hm.user_id = ? AND h.congregation_id = ? AND hm.status = 'active'",
            [$user['id'], $primaryCong['id']]
        );

        if ($existing) {
            Response::error('You are already a member of a homecell. Leave your current homecell first.');
        }

        // Check for pending request
        $pending = Database::fetchOne(
            "SELECT * FROM homecell_members WHERE homecell_id = ? AND user_id = ? AND status = 'pending'",
            [$homecellId, $user['id']]
        );

        if ($pending) {
            Response::error('You have a pending request for this homecell');
        }

        // Join homecell (auto-approve for now)
        Database::insert('homecell_members', [
            'homecell_id' => $homecellId,
            'user_id' => $user['id'],
            'role' => 'member',
            'status' => 'active',
            'joined_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Notify leader
        Database::insert('notifications', [
            'user_id' => $homecell['leader_user_id'],
            'type' => 'homecell_join',
            'title' => 'New Member',
            'message' => $user['name'] . ' joined ' . $homecell['name'],
            'link' => '/homecells/view.php?id=' . $homecellId,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        Response::success([], 'Successfully joined homecell');
        break;

    case 'leave':
        $homecellId = (int)input('homecell_id');

        if (!$homecellId) {
            Response::error('Homecell ID required');
        }

        // Check membership
        $membership = Database::fetchOne(
            "SELECT hm.*, h.leader_user_id FROM homecell_members hm
             JOIN homecells h ON hm.homecell_id = h.id
             WHERE hm.homecell_id = ? AND hm.user_id = ? AND hm.status = 'active'",
            [$homecellId, $user['id']]
        );

        if (!$membership) {
            Response::error('You are not a member of this homecell');
        }

        // Leaders cannot leave
        if ($membership['leader_user_id'] == $user['id']) {
            Response::error('Leaders cannot leave. Transfer leadership first.');
        }

        // Update status to left
        Database::update('homecell_members', [
            'status' => 'left',
            'left_at' => date('Y-m-d H:i:s')
        ], 'homecell_id = ? AND user_id = ?', [$homecellId, $user['id']]);

        Response::success([], 'You have left the homecell');
        break;

    case 'list':
        $homecells = Database::fetchAll(
            "SELECT h.*, u.name as leader_name,
                    (SELECT COUNT(*) FROM homecell_members WHERE homecell_id = h.id AND status = 'active') as member_count,
                    (SELECT id FROM homecell_members WHERE homecell_id = h.id AND user_id = ? AND status = 'active') as is_member
             FROM homecells h
             LEFT JOIN users u ON h.leader_user_id = u.id
             WHERE h.congregation_id = ? AND h.status = 'active'
             ORDER BY h.name ASC",
            [$user['id'], $primaryCong['id']]
        );

        Response::success(['homecells' => $homecells]);
        break;

    case 'get_members':
        $homecellId = (int)input('homecell_id');

        if (!$homecellId) {
            Response::error('Homecell ID required');
        }

        // Check if user is member
        $isMember = Database::fetchColumn(
            "SELECT COUNT(*) FROM homecell_members WHERE homecell_id = ? AND user_id = ? AND status = 'active'",
            [$homecellId, $user['id']]
        );

        if (!$isMember) {
            Response::error('You must be a member to see the member list');
        }

        $members = Database::fetchAll(
            "SELECT hm.role, u.id, u.name, u.avatar_url
             FROM homecell_members hm
             JOIN users u ON hm.user_id = u.id
             WHERE hm.homecell_id = ? AND hm.status = 'active'
             ORDER BY hm.role DESC, u.name ASC",
            [$homecellId]
        );

        Response::success(['members' => $members]);
        break;

    case 'record_attendance':
        // Leader only
        $homecellId = (int)input('homecell_id');
        $meetingId = (int)input('meeting_id');
        $attendees = $_POST['attendees'] ?? [];

        $homecell = Database::fetchOne(
            "SELECT * FROM homecells WHERE id = ? AND leader_user_id = ?",
            [$homecellId, $user['id']]
        );

        if (!$homecell) {
            Response::forbidden('Only the leader can record attendance');
        }

        // Clear existing attendance for this meeting
        Database::delete('homecell_attendance', 'meeting_id = ?', [$meetingId]);

        // Record new attendance
        foreach ($attendees as $userId) {
            Database::insert('homecell_attendance', [
                'meeting_id' => $meetingId,
                'user_id' => (int)$userId,
                'status' => 'present',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }

        Response::success([], 'Attendance recorded');
        break;

    default:
        Response::error('Invalid action');
}
