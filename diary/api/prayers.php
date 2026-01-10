<?php
/**
 * CRC Prayer Journal API
 * POST /diary/api/prayers.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();
Response::requirePost();
CSRF::require();

$user = Auth::user();
$action = input('action', 'create');

switch ($action) {
    case 'create':
    case 'update':
        $prayerId = (int)input('id');
        $title = input('title');
        $request = input('request');
        $category = input('category', 'personal');
        $scriptureRef = input('scripture_ref');

        if (!$title || !$request) {
            Response::error('Title and request are required');
        }

        if (strlen($title) > 200) {
            Response::error('Title too long (max 200 characters)');
        }

        $validCategories = ['personal', 'family', 'health', 'work', 'relationships', 'spiritual', 'financial', 'world', 'other'];
        if (!in_array($category, $validCategories)) {
            $category = 'other';
        }

        $data = [
            'title' => $title,
            'request' => $request,
            'category' => $category,
            'scripture_ref' => $scriptureRef ?: null,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($prayerId) {
            $existing = Database::fetchOne(
                "SELECT * FROM prayer_requests WHERE id = ? AND user_id = ?",
                [$prayerId, $user['id']]
            );

            if (!$existing) {
                Response::error('Prayer request not found');
            }

            Database::update('prayer_requests', $data, 'id = ?', [$prayerId]);
        } else {
            $data['user_id'] = $user['id'];
            $data['created_at'] = date('Y-m-d H:i:s');
            $prayerId = Database::insert('prayer_requests', $data);
        }

        Response::success(['id' => $prayerId], $action === 'create' ? 'Prayer added' : 'Prayer updated');
        break;

    case 'delete':
        $prayerId = (int)input('id');

        if (!$prayerId) {
            Response::error('Prayer ID required');
        }

        $existing = Database::fetchOne(
            "SELECT * FROM prayer_requests WHERE id = ? AND user_id = ?",
            [$prayerId, $user['id']]
        );

        if (!$existing) {
            Response::error('Prayer request not found');
        }

        Database::delete('prayer_requests', 'id = ?', [$prayerId]);
        Response::success([], 'Prayer deleted');
        break;

    case 'mark_answered':
        $prayerId = (int)input('prayer_id');
        $testimony = input('testimony');

        if (!$prayerId) {
            Response::error('Prayer ID required');
        }

        $existing = Database::fetchOne(
            "SELECT * FROM prayer_requests WHERE id = ? AND user_id = ?",
            [$prayerId, $user['id']]
        );

        if (!$existing) {
            Response::error('Prayer request not found');
        }

        Database::update('prayer_requests', [
            'answered_at' => date('Y-m-d H:i:s'),
            'testimony' => $testimony ?: null,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$prayerId]);

        Response::success([], 'Prayer marked as answered');
        break;

    case 'toggle_pin':
        $prayerId = (int)input('prayer_id');

        if (!$prayerId) {
            Response::error('Prayer ID required');
        }

        $existing = Database::fetchOne(
            "SELECT * FROM prayer_requests WHERE id = ? AND user_id = ?",
            [$prayerId, $user['id']]
        );

        if (!$existing) {
            Response::error('Prayer request not found');
        }

        Database::update('prayer_requests', [
            'is_pinned' => $existing['is_pinned'] ? 0 : 1,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$prayerId]);

        Response::success(['pinned' => !$existing['is_pinned']], 'Pin toggled');
        break;

    case 'get':
        $prayerId = (int)input('id');

        if (!$prayerId) {
            Response::error('Prayer ID required');
        }

        $prayer = Database::fetchOne(
            "SELECT * FROM prayer_requests WHERE id = ? AND user_id = ?",
            [$prayerId, $user['id']]
        );

        if (!$prayer) {
            Response::error('Prayer request not found');
        }

        Response::success(['prayer' => $prayer]);
        break;

    default:
        Response::error('Invalid action');
}
