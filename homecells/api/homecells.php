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

        if (!$homecell['is_accepting_members']) {
            Response::error('This homecell is not accepting new members right now');
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

        // Enforce capacity if a limit is set
        if (!empty($homecell['max_members'])) {
            $memberCount = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM homecell_members WHERE homecell_id = ? AND status = 'active'",
                [$homecellId]
            );
            if ($memberCount >= (int)$homecell['max_members']) {
                Response::error('This homecell is full');
            }
        }

        // Join homecell (auto-approve). Re-activate a prior row if one exists,
        // since (homecell_id, user_id) is unique and a 'left' row is kept.
        $prior = Database::fetchOne(
            "SELECT id FROM homecell_members WHERE homecell_id = ? AND user_id = ?",
            [$homecellId, $user['id']]
        );

        if ($prior) {
            Database::update('homecell_members', [
                'role' => 'member',
                'status' => 'active',
                'joined_at' => date('Y-m-d H:i:s'),
                'left_at' => null
            ], 'id = ?', [$prior['id']]);
        } else {
            Database::insert('homecell_members', [
                'homecell_id' => $homecellId,
                'user_id' => $user['id'],
                'role' => 'member',
                'status' => 'active',
                'joined_at' => date('Y-m-d H:i:s')
            ]);
        }

        // Notify leader
        Notify::send(
            (int) $homecell['leader_user_id'],
            'homecell_join',
            'New Member',
            $user['name'] . ' joined ' . $homecell['name'],
            '/homecells/view.php?id=' . $homecellId
        );

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
            "SELECT hm.role, u.id, u.name, u.avatar
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

        // Verify the meeting belongs to this homecell
        $meeting = Database::fetchOne(
            "SELECT id FROM homecell_meetings WHERE id = ? AND homecell_id = ?",
            [$meetingId, $homecellId]
        );
        if (!$meeting) {
            Response::error('Meeting not found');
        }

        // Clear existing attendance for this meeting
        Database::delete('homecell_attendance', 'meeting_id = ?', [$meetingId]);

        // Record new attendance
        foreach ($attendees as $userId) {
            Database::insert('homecell_attendance', [
                'meeting_id' => $meetingId,
                'user_id' => (int)$userId,
                'status' => 'present',
                'recorded_by' => $user['id'],
                'recorded_at' => date('Y-m-d H:i:s')
            ]);
        }

        Response::success([], 'Attendance recorded');
        break;

    default:
        Response::error('Invalid action');
}
