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

// Get filters
$search = trim((string)($_GET['search'] ?? ''));
$sort = trim((string)($_GET['sort'] ?? 'newest'));
$filter = trim((string)($_GET['filter'] ?? 'all'));
$start = trim((string)($_GET['start'] ?? ''));
$end = trim((string)($_GET['end'] ?? ''));
$view = trim((string)($_GET['view'] ?? 'list'));

// Build query
$sql = "
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
    WHERE user_id = :user_id
";

$params = [':user_id' => $userId];

// Apply search filter
if ($search !== '') {
    $sql .= " AND (title LIKE :search OR body LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

// Apply date range filter
if ($start !== '' && $end !== '') {
    $sql .= " AND date BETWEEN :start AND :end";
    $params[':start'] = $start;
    $params[':end'] = $end;
}

// Apply time filter
if ($filter !== 'all' && $start === '' && $end === '') {
    $today = date('Y-m-d');
    switch($filter) {
        case 'today':
            $sql .= " AND date = :today";
            $params[':today'] = $today;
            break;
        case 'week':
            $weekStart = date('Y-m-d', strtotime('monday this week'));
            $weekEnd = date('Y-m-d', strtotime('sunday this week'));
            $sql .= " AND date BETWEEN :week_start AND :week_end";
            $params[':week_start'] = $weekStart;
            $params[':week_end'] = $weekEnd;
            break;
        case 'month':
            $monthStart = date('Y-m-01');
            $monthEnd = date('Y-m-t');
            $sql .= " AND date BETWEEN :month_start AND :month_end";
            $params[':month_start'] = $monthStart;
            $params[':month_end'] = $monthEnd;
            break;
        case 'year':
            $yearStart = date('Y-01-01');
            $yearEnd = date('Y-12-31');
            $sql .= " AND date BETWEEN :year_start AND :year_end";
            $params[':year_start'] = $yearStart;
            $params[':year_end'] = $yearEnd;
            break;
    }
}

// Apply sorting
switch($sort) {
    case 'oldest':
        $sql .= " ORDER BY date ASC, time ASC";
        break;
    case 'title':
        $sql .= " ORDER BY title ASC, date DESC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY date DESC, time DESC";
        break;
}

// Limit for performance
$sql .= " LIMIT 500";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process entries
    foreach ($entries as &$entry) {
        // Decode tags
        if ($entry['tags']) {
            $entry['tags'] = json_decode($entry['tags'], true) ?: [];
        } else {
            $entry['tags'] = [];
        }
        
        // Format dates for JSON
        $entry['created_at'] = $entry['created_at'] ?: null;
        $entry['updated_at'] = $entry['updated_at'] ?: null;
    }
    unset($entry);
    
    echo json_encode([
        'success' => true,
        'entries' => $entries,
        'count' => count($entries)
    ]);

} catch (Throwable $e) {
    error_log('Diary list error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'server_error',
        'message' => 'Could not fetch diary entries'
    ]);
}

