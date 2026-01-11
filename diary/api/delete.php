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
    $pdo->beginTransaction();
    
    // 1. Get calendar_event_id before deleting
    $getCalStmt = $pdo->prepare("
        SELECT calendar_event_id 
        FROM diaries 
        WHERE id = :id AND user_id = :user_id
    ");
    $getCalStmt->execute([':id' => $diaryId, ':user_id' => $userId]);
    $diary = $getCalStmt->fetch();
    
    if (!$diary) {
        http_response_code(404);
        echo json_encode(['error'=>'not_found']);
        exit;
    }
    
    $calendarEventId = $diary['calendar_event_id'];
    
    // 2. Delete diary entry
    $stmt = $pdo->prepare("
        DELETE FROM diaries 
        WHERE id = :id AND user_id = :user_id
    ");
    
    $stmt->execute([
        ':id' => $diaryId,
        ':user_id' => $userId
    ]);
    
    // 3. Delete linked calendar event
    if ($calendarEventId) {
        $delCalStmt = $pdo->prepare("
            DELETE FROM calendar_events 
            WHERE id = :cal_id AND user_id = :user_id
        ");
        $delCalStmt->execute([
            ':cal_id' => $calendarEventId,
            ':user_id' => $userId
        ]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Diary entry deleted successfully'
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Diary delete error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'server_error',
        'message' => 'Could not delete diary entry'
    ]);
}

