<?php
/**
 * CRC Profile Image Upload API
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
$uploadDir = __DIR__ . '/../../uploads/profiles/' . $user['id'];
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$extension = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp'
][$mimeType];

$filename = $type . '_' . time() . '.' . $extension;
$filepath = $uploadDir . '/' . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    Response::json(['success' => false, 'error' => 'Failed to save file']);
}

// Generate public URL
$publicUrl = '/uploads/profiles/' . $user['id'] . '/' . $filename;

// Update database
$field = $type === 'avatar' ? 'avatar' : 'cover_image';

try {
    // Delete old file if exists
    $oldUrl = $user[$field] ?? null;
    if ($oldUrl && strpos($oldUrl, '/uploads/profiles/') === 0) {
        $oldPath = __DIR__ . '/../..' . $oldUrl;
        if (file_exists($oldPath)) {
            unlink($oldPath);
        }
    }

    Database::query(
        "UPDATE users SET $field = ? WHERE id = ?",
        [$publicUrl, $user['id']]
    );

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
    // Clean up uploaded file on error
    if (file_exists($filepath)) {
        unlink($filepath);
    }

    Logger::error('Profile image upload failed', [
        'user_id' => $user['id'],
        'error' => $e->getMessage()
    ]);

    Response::json(['success' => false, 'error' => 'Failed to save image'], 500);
}
