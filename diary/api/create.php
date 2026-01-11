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

$date = trim((string)($input['date'] ?? ''));
$time = trim((string)($input['time'] ?? '00:00'));
$title = trim((string)($input['title'] ?? ''));
$body = trim((string)($input['body'] ?? ''));
$mood = trim((string)($input['mood'] ?? ''));
$weather = trim((string)($input['weather'] ?? ''));
$tags = $input['tags'] ?? [];
$reminderMinutes = (int)($input['reminder_minutes'] ?? 60);
$addToCalendar = (bool)($input['add_to_calendar'] ?? true); // NEW

if (empty($date)) {
    http_response_code(400);
    echo json_encode(['error'=>'date_required']);
    exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error'=>'invalid_date_format']);
    exit;
}

// Validate time format
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

    // 1. Insert diary entry
    $stmt = $pdo->prepare("
        INSERT INTO diaries (user_id, date, time, title, body, mood, weather, tags, reminder_minutes, created_at, updated_at)
        VALUES (:user_id, :date, :time, :title, :body, :mood, :weather, :tags, :reminder_minutes, NOW(), NOW())
    ");
    
    $stmt->execute([
        ':user_id' => $userId,
        ':date' => $date,
        ':time' => $time,
        ':title' => $title ?: null,
        ':body' => $body ?: null,
        ':mood' => $mood ?: null,
        ':weather' => $weather ?: null,
        ':tags' => $tagsJson,
        ':reminder_minutes' => $reminderMinutes
    ]);
    
    $diaryId = (int)$pdo->lastInsertId();

    // 2. Add to your existing Calendar system
    $calendarEventId = null;
    if ($addToCalendar && $title) {
        $startAt = $date . ' ' . $time;
        
        // Calculate end_at (1 hour later by default)
        $endAt = date('Y-m-d H:i:s', strtotime($startAt . ' +1 hour'));
        
        // Build description with mood/weather
        $description = '';
        if ($mood) $description .= "Gemoed: $mood\n";
        if ($weather) $description .= "Weer: $weather\n";
        if ($body) $description .= "\n" . substr($body, 0, 200) . (strlen($body) > 200 ? '...' : '');
        
        $calStmt = $pdo->prepare("
            INSERT INTO calendar_events (user_id, title, description, start_at, end_at, all_day, visibility, created_at)
            VALUES (:user_id, :title, :description, :start_at, :end_at, 0, 'private', NOW())
        ");
        
        $calStmt->execute([
            ':user_id' => $userId,
            ':title' => '📔 ' . $title, // Diary icon prefix
            ':description' => $description,
            ':start_at' => $startAt,
            ':end_at' => $endAt
        ]);
        
        $calendarEventId = (int)$pdo->lastInsertId();
        
        // Link back to diary
        $updateDiary = $pdo->prepare("
            UPDATE diaries 
            SET calendar_event_id = :cal_id 
            WHERE id = :diary_id
        ");
        $updateDiary->execute([
            ':cal_id' => $calendarEventId,
            ':diary_id' => $diaryId
        ]);
    }

    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'diary_id' => $diaryId,
        'calendar_event_id' => $calendarEventId,
        'message' => 'Diary entry created successfully'
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Diary create error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'server_error',
        'message' => 'Could not create diary entry'
    ]);
}

