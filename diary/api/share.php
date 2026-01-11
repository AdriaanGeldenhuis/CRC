<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/security/auth_gate.php';

if (!isset($pdo) || !($pdo instanceof PDO)) { 
    http_response_code(500); 
    echo json_encode(['error'=>'db_unavailable']); 
    exit; 
}

ini_set('display_errors', '0');
ini_set('log_errors', '1');

$userId = (int)($_SESSION['user_id'] ?? 0);

if ($userId <= 0) { 
    http_response_code(400); 
    echo json_encode(['error'=>'unauthorized']); 
    exit; 
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$entryId = (int)($input['entry_id'] ?? 0);
$type = trim((string)($input['type'] ?? 'link'));

if ($entryId <= 0) {
    http_response_code(400);
    echo json_encode(['error'=>'invalid_entry_id']);
    exit;
}

// Verify ownership
try {
    $stmt = $pdo->prepare("SELECT id FROM diaries WHERE id = ? AND user_id = ?");
    $stmt->execute([$entryId, $userId]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error'=>'forbidden']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error'=>'verification_failed']);
    exit;
}

try {
    switch($type) {
        case 'link':
            // Generate shareable link (simple token-based)
            $token = bin2hex(random_bytes(16));
            
            // Store share token in database (you'll need a diary_shares table)
            // For now, return a simple link
            $shareLink = 'https://' . $_SERVER['HTTP_HOST'] . '/diary/view.php?token=' . $token;
            
            // TODO: Store in diary_shares table with expiry
            
            echo json_encode([
                'success' => true,
                'link' => $shareLink,
                'token' => $token
            ]);
            break;
            
        case 'friend':
            // TODO: Implement friend sharing
            echo json_encode([
                'success' => true,
                'message' => 'Friend sharing coming soon'
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error'=>'invalid_share_type']);
            break;
    }
} catch (Throwable $e) {
    error_log('Diary share error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'server_error',
        'message' => 'Could not share entry'
    ]);
}

