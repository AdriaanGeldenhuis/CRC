<?php
/**
 * Debug Upload Script
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

$debug = [
    'step' => 'start',
    'php_version' => PHP_VERSION,
    'post' => $_POST,
    'files' => isset($_FILES['image']) ? [
        'name' => $_FILES['image']['name'] ?? null,
        'type' => $_FILES['image']['type'] ?? null,
        'size' => $_FILES['image']['size'] ?? null,
        'error' => $_FILES['image']['error'] ?? null,
    ] : 'No file uploaded',
];

try {
    $debug['step'] = 'loading bootstrap';
    require_once __DIR__ . '/../../core/bootstrap.php';

    $debug['step'] = 'checking auth';
    $debug['auth_check'] = Auth::check();

    if (!Auth::check()) {
        $debug['error'] = 'Not authenticated';
        echo json_encode($debug, JSON_PRETTY_PRINT);
        exit;
    }

    $debug['step'] = 'getting user';
    $user = Auth::user();
    $debug['user_id'] = $user['id'] ?? null;
    $debug['user_name'] = $user['name'] ?? null;

    $debug['step'] = 'checking CSRF';
    // Skip CSRF for debug

    $debug['step'] = 'checking upload dir';
    $baseUploadDir = __DIR__ . '/../../uploads';
    $profilesDir = $baseUploadDir . '/profiles';
    $userDir = $profilesDir . '/' . $user['id'];

    $debug['paths'] = [
        'base' => $baseUploadDir,
        'base_exists' => is_dir($baseUploadDir),
        'base_writable' => is_writable($baseUploadDir),
        'profiles' => $profilesDir,
        'profiles_exists' => is_dir($profilesDir),
        'user' => $userDir,
        'user_exists' => is_dir($userDir),
    ];

    // Try to create directories
    if (!is_dir($baseUploadDir)) {
        $debug['mkdir_base'] = @mkdir($baseUploadDir, 0755, true);
    }
    if (!is_dir($profilesDir)) {
        $debug['mkdir_profiles'] = @mkdir($profilesDir, 0755, true);
    }
    if (!is_dir($userDir)) {
        $debug['mkdir_user'] = @mkdir($userDir, 0755, true);
    }

    $debug['after_mkdir'] = [
        'base_exists' => is_dir($baseUploadDir),
        'base_writable' => is_writable($baseUploadDir),
        'profiles_exists' => is_dir($profilesDir),
        'profiles_writable' => is_dir($profilesDir) ? is_writable($profilesDir) : false,
        'user_exists' => is_dir($userDir),
        'user_writable' => is_dir($userDir) ? is_writable($userDir) : false,
    ];

    $debug['step'] = 'complete';
    $debug['success'] = true;

} catch (Exception $e) {
    $debug['error'] = $e->getMessage();
    $debug['trace'] = $e->getTraceAsString();
} catch (Error $e) {
    $debug['fatal_error'] = $e->getMessage();
    $debug['trace'] = $e->getTraceAsString();
}

echo json_encode($debug, JSON_PRETTY_PRINT);
