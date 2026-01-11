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

$query = trim((string)($input['query'] ?? ''));
$searchTitle = (bool)($input['searchTitle'] ?? true);
$searchBody = (bool)($input['searchBody'] ?? true);
$searchTags = (bool)($input['searchTags'] ?? true);

if ($query === '') {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

// Build search query
$conditions = [];
$params = [':user_id' => $userId];

if ($searchTitle) {
    $conditions[] = "title LIKE :query_title";
    $params[':query_title'] = '%' . $query . '%';
}

if ($searchBody) {
    $conditions[] = "body LIKE :query_body";
    $params[':query_body'] = '%' . $query . '%';
}

if ($searchTags) {
    $conditions[] = "tags LIKE :query_tags";
    $params[':query_tags'] = '%' . $query . '%';
}

if (empty($conditions)) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

$whereClause = '(' . implode(' OR ', $conditions) . ')';

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
        created_at
    FROM diaries 
    WHERE user_id = :user_id AND $whereClause
    ORDER BY date DESC, time DESC
    LIMIT 100
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process results
    foreach ($results as &$result) {
        if ($result['tags']) {
            $result['tags'] = json_decode($result['tags'], true) ?: [];
        } else {
            $result['tags'] = [];
        }
    }
    unset($result);
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'count' => count($results)
    ]);

} catch (Throwable $e) {
    error_log('Diary search error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'server_error',
        'message' => 'Search failed'
    ]);
}

