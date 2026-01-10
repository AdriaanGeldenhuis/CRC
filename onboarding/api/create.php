<?php
/**
 * CRC Create Congregation API
 * POST /onboarding/api/create.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

// Require POST and auth
Response::requirePost();
Auth::requireAuth();
CSRF::require();

// Rate limit
Security::requireRateLimit('create_congregation', 3, 3600);

$userId = Auth::id();

// Get input
$name = input('name');
$city = input('city');
$province = input('province');
$description = input('description');
$joinMode = input('join_mode', 'approval');

// Validate
$validator = validate([
    'name' => $name,
    'city' => $city,
    'province' => $province,
    'join_mode' => $joinMode
])
    ->required('name', 'Congregation name')
    ->length('name', 3, 255, 'Congregation name')
    ->required('city', 'City')
    ->required('province', 'Province')
    ->in('join_mode', ['open', 'approval', 'invite_only']);

if ($validator->fails()) {
    Response::validationError($validator->errors());
}

// Generate slug
$slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($name));
$slug = trim($slug, '-');

// Ensure unique slug
$existingSlug = Database::fetchColumn(
    "SELECT COUNT(*) FROM congregations WHERE slug = ?",
    [$slug]
);

if ($existingSlug) {
    $slug = $slug . '-' . substr(uniqid(), -4);
}

try {
    Database::beginTransaction();

    // Create congregation
    $congregationId = Database::insert('congregations', [
        'name' => $name,
        'slug' => $slug,
        'description' => $description ?: null,
        'city' => $city,
        'province' => $province,
        'join_mode' => $joinMode,
        'status' => 'active',
        'created_by' => $userId,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    // Add creator as pastor (highest role)
    Database::insert('user_congregations', [
        'user_id' => $userId,
        'congregation_id' => $congregationId,
        'role' => 'pastor',
        'status' => 'active',
        'is_primary' => 1,
        'approved_by' => $userId,
        'approved_at' => date('Y-m-d H:i:s'),
        'joined_at' => date('Y-m-d H:i:s'),
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    Database::commit();

    Logger::audit($userId, 'created_congregation', [
        'congregation_id' => $congregationId,
        'name' => $name
    ]);

    Response::success([
        'congregation_id' => $congregationId,
        'redirect' => '/home/'
    ], 'Congregation created successfully');

} catch (Exception $e) {
    Database::rollback();
    Logger::error('Failed to create congregation', ['error' => $e->getMessage()]);
    Response::error('Failed to create congregation. Please try again.');
}
