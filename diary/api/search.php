<?php
/**
 * CRC Diary API - Search
 * Searches diary entries
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

$query = trim((string)($input['query'] ?? ''));
$searchTitle = (bool)($input['searchTitle'] ?? true);
$searchBody = (bool)($input['searchBody'] ?? true);
$searchTags = (bool)($input['searchTags'] ?? true);

if (empty($query)) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

try {
    $conditions = [];
    $params = [$userId];

    if ($searchTitle) {
        $conditions[] = 'title LIKE ?';
        $params[] = '%' . $query . '%';
    }
    if ($searchBody) {
        $conditions[] = 'body LIKE ?';
        $params[] = '%' . $query . '%';
    }
    if ($searchTags) {
        $conditions[] = 'tags LIKE ?';
        $params[] = '%' . $query . '%';
    }

    if (empty($conditions)) {
        echo json_encode(['success' => true, 'results' => []]);
        exit;
    }

    $searchCondition = '(' . implode(' OR ', $conditions) . ')';

    $results = Database::fetchAll(
        "SELECT id, title, body, entry_date, entry_time, mood, weather, tags
         FROM diary_entries
         WHERE user_id = ? AND {$searchCondition}
         ORDER BY entry_date DESC, entry_time DESC
         LIMIT 50",
        $params
    ) ?: [];

    // Process results
    foreach ($results as &$row) {
        $row['date'] = $row['entry_date'];
        $row['time'] = $row['entry_time'];
        $row['tags'] = $row['tags'] ? json_decode($row['tags'], true) : [];
    }

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
