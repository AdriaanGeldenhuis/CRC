<?php
/**
 * CRC Congregation Admin API
 * POST /congregation/admin/api/congregation.php
 */

require_once __DIR__ . '/../../../core/bootstrap.php';

Auth::requireAuth();
Response::requirePost();
CSRF::require();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();

if (!$primaryCong) {
    Response::error('No congregation found');
}

// Check if user is congregation admin
$membership = Database::fetchOne(
    "SELECT * FROM congregation_members WHERE user_id = ? AND congregation_id = ? AND status = 'active'",
    [$user['id'], $primaryCong['id']]
);

if (!$membership || !in_array($membership['role'], ['admin', 'leader', 'pastor'])) {
    Response::forbidden('Admin access required');
}

$action = input('action');

switch ($action) {
    // Member management
    case 'invite_member':
        $email = trim(input('email'));
        $role = input('role', 'member');
        $message = trim(input('message', ''));

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Valid email required');
        }

        // Check if already a member
        $existingUser = Database::fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existingUser) {
            $existingMember = Database::fetchOne(
                "SELECT id FROM congregation_members WHERE user_id = ? AND congregation_id = ?",
                [$existingUser['id'], $primaryCong['id']]
            );
            if ($existingMember) {
                Response::error('This user is already a member');
            }
        }

        // Check for existing invite
        $existingInvite = Database::fetchOne(
            "SELECT id FROM congregation_invites WHERE email = ? AND congregation_id = ? AND status = 'pending'",
            [$email, $primaryCong['id']]
        );
        if ($existingInvite) {
            Response::error('An invite has already been sent to this email');
        }

        // Create invite
        $token = bin2hex(random_bytes(32));
        Database::insert('congregation_invites', [
            'congregation_id' => $primaryCong['id'],
            'email' => $email,
            'role' => $role,
            'token' => $token,
            'message' => $message,
            'invited_by' => $user['id'],
            'status' => 'pending',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // TODO: Send email with invite link

        Response::success([], 'Invite sent successfully');
        break;

    case 'update_member_role':
        $memberId = (int)input('member_id');
        $newRole = input('role');

        if (!$memberId || !$newRole) {
            Response::error('Member ID and role required');
        }

        // Verify member belongs to this congregation
        $member = Database::fetchOne(
            "SELECT * FROM congregation_members WHERE id = ? AND congregation_id = ?",
            [$memberId, $primaryCong['id']]
        );

        if (!$member) {
            Response::error('Member not found');
        }

        // Cannot change own role
        if ($member['user_id'] == $user['id']) {
            Response::error('Cannot change your own role');
        }

        Database::update('congregation_members', [
            'role' => $newRole,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$memberId]);

        Response::success([], 'Role updated successfully');
        break;

    case 'remove_member':
        $memberId = (int)input('member_id');

        $member = Database::fetchOne(
            "SELECT * FROM congregation_members WHERE id = ? AND congregation_id = ?",
            [$memberId, $primaryCong['id']]
        );

        if (!$member) {
            Response::error('Member not found');
        }

        if ($member['user_id'] == $user['id']) {
            Response::error('Cannot remove yourself');
        }

        Database::update('congregation_members', [
            'status' => 'removed',
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$memberId]);

        Response::success([], 'Member removed');
        break;

    case 'approve_member':
        $memberId = (int)input('member_id');

        $member = Database::fetchOne(
            "SELECT * FROM congregation_members WHERE id = ? AND congregation_id = ? AND status = 'pending'",
            [$memberId, $primaryCong['id']]
        );

        if (!$member) {
            Response::error('Pending member not found');
        }

        Database::update('congregation_members', [
            'status' => 'active',
            'approved_by' => $user['id'],
            'approved_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$memberId]);

        Response::success([], 'Member approved');
        break;

    // Event management
    case 'add_event':
        $title = trim(input('title'));
        $description = trim(input('description'));
        $eventDate = input('event_date');
        $startTime = input('start_time');
        $endTime = input('end_time');
        $location = trim(input('location'));
        $isPublic = input('is_public') ? 1 : 0;

        if (!$title || !$eventDate || !$startTime) {
            Response::error('Title, date, and start time are required');
        }

        $eventId = Database::insert('events', [
            'title' => $title,
            'description' => $description,
            'event_date' => $eventDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'location' => $location,
            'congregation_id' => $primaryCong['id'],
            'is_public' => $isPublic,
            'created_by' => $user['id'],
            'created_at' => date('Y-m-d H:i:s')
        ]);

        Response::success(['event_id' => $eventId], 'Event created');
        break;

    case 'update_event':
        $eventId = (int)input('event_id');
        $title = trim(input('title'));
        $description = trim(input('description'));
        $eventDate = input('event_date');
        $startTime = input('start_time');
        $endTime = input('end_time');
        $location = trim(input('location'));
        $isPublic = input('is_public') ? 1 : 0;

        $event = Database::fetchOne(
            "SELECT * FROM events WHERE id = ? AND congregation_id = ?",
            [$eventId, $primaryCong['id']]
        );

        if (!$event) {
            Response::error('Event not found');
        }

        Database::update('events', [
            'title' => $title,
            'description' => $description,
            'event_date' => $eventDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'location' => $location,
            'is_public' => $isPublic,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$eventId]);

        Response::success([], 'Event updated');
        break;

    case 'delete_event':
        $eventId = (int)input('event_id');

        $deleted = Database::delete('events', 'id = ? AND congregation_id = ?', [$eventId, $primaryCong['id']]);

        if (!$deleted) {
            Response::error('Event not found');
        }

        Response::success([], 'Event deleted');
        break;

    // Announcement management
    case 'add_announcement':
        $title = trim(input('title'));
        $content = trim(input('content'));
        $priority = input('priority', 'normal');

        if (!$title || !$content) {
            Response::error('Title and content are required');
        }

        $announcementId = Database::insert('announcements', [
            'title' => $title,
            'content' => $content,
            'priority' => $priority,
            'congregation_id' => $primaryCong['id'],
            'created_by' => $user['id'],
            'published_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Notify members
        $memberIds = Database::fetchAll(
            "SELECT user_id FROM congregation_members WHERE congregation_id = ? AND status = 'active' AND user_id != ?",
            [$primaryCong['id'], $user['id']]
        );

        foreach ($memberIds as $member) {
            Database::insert('notifications', [
                'user_id' => $member['user_id'],
                'type' => 'announcement',
                'title' => 'New Announcement',
                'message' => $title,
                'link' => '/announcements/?id=' . $announcementId,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }

        Response::success(['announcement_id' => $announcementId], 'Announcement posted');
        break;

    // Homecell management
    case 'add_homecell':
        $name = trim(input('name'));
        $description = trim(input('description'));
        $leaderId = (int)input('leader_id');
        $location = trim(input('location'));
        $meetingDay = input('meeting_day');
        $meetingTime = input('meeting_time');

        if (!$name || !$leaderId || !$meetingDay) {
            Response::error('Name, leader, and meeting day are required');
        }

        $homecellId = Database::insert('homecells', [
            'name' => $name,
            'description' => $description,
            'leader_user_id' => $leaderId,
            'congregation_id' => $primaryCong['id'],
            'location' => $location,
            'meeting_day' => $meetingDay,
            'meeting_time' => $meetingTime,
            'meeting_frequency' => 'weekly',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Add leader as member
        Database::insert('homecell_members', [
            'homecell_id' => $homecellId,
            'user_id' => $leaderId,
            'role' => 'leader',
            'status' => 'active',
            'joined_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        Response::success(['homecell_id' => $homecellId], 'Homecell created');
        break;

    // Settings
    case 'update_settings':
        $name = trim(input('name'));
        $address = trim(input('address'));
        $city = trim(input('city'));
        $country = trim(input('country'));
        $phone = trim(input('phone'));
        $email = trim(input('email'));
        $website = trim(input('website'));

        if (!$name) {
            Response::error('Name is required');
        }

        Database::update('congregations', [
            'name' => $name,
            'address' => $address,
            'city' => $city,
            'country' => $country,
            'phone' => $phone,
            'email' => $email,
            'website' => $website,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$primaryCong['id']]);

        Response::success([], 'Settings updated');
        break;

    case 'get_stats':
        $stats = [
            'members' => Database::fetchColumn(
                "SELECT COUNT(*) FROM congregation_members WHERE congregation_id = ? AND status = 'active'",
                [$primaryCong['id']]
            ),
            'pending' => Database::fetchColumn(
                "SELECT COUNT(*) FROM congregation_members WHERE congregation_id = ? AND status = 'pending'",
                [$primaryCong['id']]
            ),
            'events' => Database::fetchColumn(
                "SELECT COUNT(*) FROM events WHERE congregation_id = ? AND event_date >= CURDATE()",
                [$primaryCong['id']]
            ),
            'homecells' => Database::fetchColumn(
                "SELECT COUNT(*) FROM homecells WHERE congregation_id = ? AND status = 'active'",
                [$primaryCong['id']]
            )
        ];

        Response::success(['stats' => $stats]);
        break;

    default:
        Response::error('Invalid action');
}
