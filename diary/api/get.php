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
$diaryId = (int)($_GET['id'] ?? 0);

if ($userId <= 0 || $diaryId <= 0) { 
    http_response_code(400); 
    echo json_encode(['error'=>'bad_request']); 
    exit; 
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            date,
            time,
            title,
            body,
            mood,
            weather,
            tags,
            reminder_minutes,
            created_at,
            updated_at
        FROM diaries 
        WHERE id = :id AND user_id = :user_id
    ");
    
    $stmt->execute([
        ':id' => $diaryId,
        ':user_id' => $userId
    ]);
    
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$entry) {
        http_response_code(404);
        echo json_encode(['error'=>'not_found']);
        exit;
    }
    
    // Decode tags
    if ($entry['tags']) {
        $entry['tags'] = json_decode($entry['tags'], true) ?: [];
    } else {
        $entry['tags'] = [];
    }
    
    echo json_encode([
        'success' => true,
        'entry' => $entry
    ]);

} catch (Throwable $e) {
    error_log('Diary get error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'server_error',
        'message' => 'Could not fetch diary entry'
    ]);
}

