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

try {
    // Total entries
    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM diaries WHERE user_id = ?");
    $totalStmt->execute([$userId]);
    $total = (int)$totalStmt->fetchColumn();
    
    // This month
    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');
    $monthStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM diaries 
        WHERE user_id = ? AND date BETWEEN ? AND ?
    ");
    $monthStmt->execute([$userId, $monthStart, $monthEnd]);
    $month = (int)$monthStmt->fetchColumn();
    
    // Calculate streak
    $streakStmt = $pdo->prepare("
        SELECT DISTINCT date 
        FROM diaries 
        WHERE user_id = ? 
        ORDER BY date DESC 
        LIMIT 365
    ");
    $streakStmt->execute([$userId]);
    $dates = $streakStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $streak = 0;
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    
    foreach ($dates as $dateStr) {
        $date = new DateTime($dateStr);
        $date->setTime(0, 0, 0);
        $diff = $today->diff($date)->days;
        
        if ($diff === $streak) {
            $streak++;
        } else {
            break;
        }
    }
    
    // Total words
    $wordsStmt = $pdo->prepare("
        SELECT body FROM diaries WHERE user_id = ? AND body IS NOT NULL
    ");
    $wordsStmt->execute([$userId]);
    $bodies = $wordsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $totalWords = 0;
    foreach ($bodies as $body) {
        $words = preg_split('/\s+/', trim($body), -1, PREG_SPLIT_NO_EMPTY);
        $totalWords += count($words);
    }
    
    echo json_encode([
        'success' => true,
        'total' => $total,
        'month' => $month,
        'streak' => $streak,
        'words' => $totalWords
    ]);

} catch (Throwable $e) {
    error_log('Diary stats error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'server_error',
        'message' => 'Could not fetch statistics'
    ]);
}

