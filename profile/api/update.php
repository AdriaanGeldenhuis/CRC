<?php
/**
 * CRC Profile Update API
 */

require_once __DIR__ . '/../../core/bootstrap.php';

header('Content-Type: application/json');

// Require authentication
Auth::requireAuth();
CSRF::verify();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::json(['success' => false, 'error' => 'Invalid request method'], 405);
}

$user = Auth::user();

// Allowed fields to update
$allowedFields = [
    'name' => 'string',
    'bio' => 'string',
    'phone' => 'string',
    'location' => 'string',
    'occupation' => 'string',
    'date_of_birth' => 'date',
    'show_birthday' => 'bool',
    'show_age' => 'bool',
    'show_email' => 'bool',
    'show_phone' => 'bool'
];

$updates = [];

foreach ($allowedFields as $field => $type) {
    if (isset($_POST[$field])) {
        $value = $_POST[$field];

        switch ($type) {
            case 'string':
                $value = trim($value);
                if ($field === 'name' && empty($value)) {
                    Response::json(['success' => false, 'error' => 'Name is required']);
                }
                if ($field === 'bio' && strlen($value) > 500) {
                    Response::json(['success' => false, 'error' => 'Bio must be less than 500 characters']);
                }
                break;

            case 'date':
                if (!empty($value)) {
                    $date = DateTime::createFromFormat('Y-m-d', $value);
                    if (!$date) {
                        Response::json(['success' => false, 'error' => 'Invalid date format']);
                    }
                    // Ensure date is in the past
                    if ($date > new DateTime()) {
                        Response::json(['success' => false, 'error' => 'Date of birth must be in the past']);
                    }
                } else {
                    $value = null;
                }
                break;

            case 'bool':
                $value = !empty($value) ? 1 : 0;
                break;
        }

        $updates[$field] = $value;
    }
}

// Handle checkbox fields that might not be sent when unchecked
foreach (['show_birthday', 'show_age', 'show_email', 'show_phone'] as $checkboxField) {
    if (!isset($_POST[$checkboxField])) {
        $updates[$checkboxField] = 0;
    }
}

if (empty($updates)) {
    Response::json(['success' => false, 'error' => 'No changes to save']);
}

try {
    // Build update query
    $setParts = [];
    $params = [];

    foreach ($updates as $field => $value) {
        $setParts[] = "$field = ?";
        $params[] = $value;
    }

    $params[] = $user['id'];

    $sql = "UPDATE users SET " . implode(', ', $setParts) . " WHERE id = ?";

    Database::query($sql, $params);

    Logger::info('Profile updated', ['user_id' => $user['id'], 'fields' => array_keys($updates)]);

    Response::json(['success' => true, 'message' => 'Profile updated successfully']);

} catch (Exception $e) {
    Logger::error('Profile update failed', ['user_id' => $user['id'], 'error' => $e->getMessage()]);
    Response::json(['success' => false, 'error' => 'Failed to update profile'], 500);
}
