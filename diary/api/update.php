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

// Verify ownership and get calendar_event_id
try {
    $checkStmt = $pdo->prepare("SELECT calendar_event_id FROM diaries WHERE id = ? AND user_id = ?");
    $checkStmt->execute([$diaryId, $userId]);
    $existing = $checkStmt->fetch();
    if (!$existing) {
        http_response_code(403);
        echo json_encode(['error'=>'forbidden']);
        exit;
    }
    $existingCalendarEventId = $existing['calendar_event_id'];
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error'=>'verification_failed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$date = trim((string)($input['date'] ?? ''));
$time = trim((string)($input['time'] ?? '00:00'));
$title = trim((string)($input['title'] ?? ''));
$body = trim((string)($input['body'] ?? ''));
$mood = trim((string)($input['mood'] ?? ''));
$weather = trim((string)($input['weather'] ?? ''));
$tags = $input['tags'] ?? [];
$reminderMinutes = (int)($input['reminder_minutes'] ?? 60);
$syncToCalendar = (bool)($input['sync_to_calendar'] ?? true);

if (empty($date)) {
    http_response_code(400);
    echo json_encode(['error'=>'date_required']);
    exit;
}

// Validate formats
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error'=>'invalid_date_format']);
    exit;
}

if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
    $time = '00:00:00';
} elseif (strlen($time) === 5) {
    $time .= ':00';
}

// Process tags
$tagsJson = null;
if (is_array($tags) && count($tags) > 0) {
    $tags = array_map('trim', $tags);
    $tags = array_filter($tags, fn($t) => strlen($t) > 0);
    $tagsJson = json_encode(array_values($tags), JSON_UNESCAPED_UNICODE);
}

try {
    $pdo->beginTransaction();

    // 1. Update diary
    $stmt = $pdo->prepare("
        UPDATE diaries 
        SET date = :date,
            time = :time,
            title = :title,
            body = :body,
            mood = :mood,
            weather = :weather,
            tags = :tags,
            reminder_minutes = :reminder_minutes,
            updated_at = NOW()
        WHERE id = :id AND user_id = :user_id
    ");
    
    $stmt->execute([
        ':date' => $date,
        ':time' => $time,
        ':title' => $title ?: null,
        ':body' => $body ?: null,
        ':mood' => $mood ?: null,
        ':weather' => $weather ?: null,
        ':tags' => $tagsJson,
        ':reminder_minutes' => $reminderMinutes,
        ':id' => $diaryId,
        ':user_id' => $userId
    ]);

    // 2. Sync to calendar if linked
    if ($syncToCalendar && $existingCalendarEventId && $title) {
        $startAt = $date . ' ' . $time;
        $endAt = date('Y-m-d H:i:s', strtotime($startAt . ' +1 hour'));
        
        $description = '';
        if ($mood) $description .= "Gemoed: $mood\n";
        if ($weather) $description .= "Weer: $weather\n";
        if ($body) $description .= "\n" . substr($body, 0, 200) . (strlen($body) > 200 ? '...' : '');
        
        $calUpdate = $pdo->prepare("
            UPDATE calendar_events 
            SET title = :title,
                description = :description,
                start_at = :start_at,
                end_at = :end_at
            WHERE id = :cal_id AND user_id = :user_id
        ");
        
        $calUpdate->execute([
            ':title' => '📔 ' . $title,
            ':description' => $description,
            ':start_at' => $startAt,
            ':end_at' => $endAt,
            ':cal_id' => $existingCalendarEventId,
            ':user_id' => $userId
        ]);
    }

    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Diary entry updated successfully'
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Diary update error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'server_error',
        'message' => 'Could not update diary entry'
    ]);
}
