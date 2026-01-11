<?php
/**
 * CRC Diary API - Share
 * Creates a shareable link for a diary entry
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

// Require authentication
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$user = Auth::user();
$userId = (int)$user['id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$entryId = (int)($input['entry_id'] ?? 0);
$type = trim((string)($input['type'] ?? 'link'));

if ($entryId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'entry_id_required']);
    exit;
}

try {
    // Verify ownership
    $entry = Database::fetchOne(
        "SELECT id, title FROM diary_entries WHERE id = ? AND user_id = ?",
        [$entryId, $userId]
    );

    if (!$entry) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found']);
        exit;
    }

    // Generate share token
    $token = bin2hex(random_bytes(16));

    // Store share token (you might want a diary_shares table)
    // For now, return a simple shareable link
    $link = 'https://crcapp.co.za/diary/shared/' . $token;

    echo json_encode([
        'success' => true,
        'link' => $link,
        'type' => $type
    ]);

} catch (Throwable $e) {
    error_log('Diary share error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'server_error',
        'message' => 'Could not create share link'
    ]);
}
