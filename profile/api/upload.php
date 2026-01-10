<?php
/**
 * CRC Profile Image Upload API
 */

require_once __DIR__ . '/../../core/bootstrap.php';

header('Content-Type: application/json');

try {
    // Require authentication
    if (!Auth::check()) {
        Response::json(['success' => false, 'error' => 'Not authenticated'], 401);
    }

    // Validate CSRF token
    if (!CSRF::validate()) {
        Response::json(['success' => false, 'error' => 'Invalid security token. Please refresh the page.'], 403);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::json(['success' => false, 'error' => 'Invalid request method'], 405);
    }

    $user = Auth::user();
    $type = $_POST['type'] ?? '';

    if (!in_array($type, ['avatar', 'cover'])) {
        Response::json(['success' => false, 'error' => 'Invalid upload type']);
    }

    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form limit',
            UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by extension'
        ];
        $error = $errorMessages[$_FILES['image']['error'] ?? 0] ?? 'Upload failed';
        Response::json(['success' => false, 'error' => $error]);
    }

    $file = $_FILES['image'];

    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        Response::json(['success' => false, 'error' => 'Invalid file type. Please upload JPG, PNG, GIF, or WebP']);
    }

    // Validate file size (5MB max)
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        Response::json(['success' => false, 'error' => 'File too large. Maximum size is 5MB']);
    }

    // Create upload directory
    $baseUploadDir = __DIR__ . '/../../uploads';
    $profilesDir = $baseUploadDir . '/profiles';
    $userDir = $profilesDir . '/' . $user['id'];

    // Create directories if they don't exist
    if (!is_dir($baseUploadDir)) {
        mkdir($baseUploadDir, 0755, true);
    }
    if (!is_dir($profilesDir)) {
        mkdir($profilesDir, 0755, true);
    }
    if (!is_dir($userDir)) {
        mkdir($userDir, 0755, true);
    }

    // Generate unique filename
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    $extension = $extensions[$mimeType] ?? 'jpg';

    $filename = $type . '_' . time() . '.' . $extension;
    $filepath = $userDir . '/' . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        Response::json(['success' => false, 'error' => 'Failed to save file']);
    }

    // Generate public URL
    $publicUrl = '/uploads/profiles/' . $user['id'] . '/' . $filename;

    // Update database - only update avatar field (cover_image might not exist yet)
    if ($type === 'avatar') {
        Database::query(
            "UPDATE users SET avatar = ? WHERE id = ?",
            [$publicUrl, $user['id']]
        );
    } else {
        // Try to update cover_image, but don't fail if column doesn't exist
        try {
            Database::query(
                "UPDATE users SET cover_image = ? WHERE id = ?",
                [$publicUrl, $user['id']]
            );
        } catch (Exception $e) {
            // Column might not exist yet - that's OK, the image is still uploaded
            Logger::warning('cover_image column may not exist', ['error' => $e->getMessage()]);
        }
    }

    Logger::info('Profile image uploaded', [
        'user_id' => $user['id'],
        'type' => $type,
        'file' => $filename
    ]);

    Response::json([
        'success' => true,
        'message' => ucfirst($type) . ' uploaded successfully',
        'url' => $publicUrl
    ]);

} catch (Exception $e) {
    Logger::error('Profile image upload failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    Response::json(['success' => false, 'error' => 'Upload failed: ' . $e->getMessage()], 500);
}
