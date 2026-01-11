<?php
/**
 * CRC Diary API - Stats
 * Returns diary statistics for the current user
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

try {
    // Total entries
    $total = (int)Database::fetchColumn(
        "SELECT COUNT(*) FROM diary_entries WHERE user_id = ?",
        [$userId]
    );

    // This month
    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');
    $month = (int)Database::fetchColumn(
        "SELECT COUNT(*) FROM diary_entries WHERE user_id = ? AND entry_date BETWEEN ? AND ?",
        [$userId, $monthStart, $monthEnd]
    );

    // Calculate streak
    $dates = Database::fetchAll(
        "SELECT DISTINCT entry_date FROM diary_entries WHERE user_id = ? ORDER BY entry_date DESC LIMIT 365",
        [$userId]
    ) ?: [];

    $streak = 0;
    $today = new DateTime();
    $today->setTime(0, 0, 0);

    foreach ($dates as $row) {
        $date = new DateTime($row['entry_date']);
        $date->setTime(0, 0, 0);
        $diff = $today->diff($date)->days;

        if ($diff === $streak) {
            $streak++;
        } else {
            break;
        }
    }

    // Total words
    $contents = Database::fetchAll(
        "SELECT content FROM diary_entries WHERE user_id = ? AND content IS NOT NULL",
        [$userId]
    ) ?: [];

    $totalWords = 0;
    foreach ($contents as $row) {
        $words = preg_split('/\s+/', trim($row['content'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
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
