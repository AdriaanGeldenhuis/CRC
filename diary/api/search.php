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
        $conditions[] = 'e.title LIKE ?';
        $params[] = '%' . $query . '%';
    }
    if ($searchBody) {
        $conditions[] = 'e.content LIKE ?';
        $params[] = '%' . $query . '%';
    }

    // For tag search, we need to join the tags table
    $tagJoin = '';
    if ($searchTags) {
        $tagJoin = 'LEFT JOIN diary_tag_links tl ON e.id = tl.entry_id LEFT JOIN diary_tags t ON tl.tag_id = t.id';
        $conditions[] = 't.name LIKE ?';
        $params[] = '%' . $query . '%';
    }

    if (empty($conditions)) {
        echo json_encode(['success' => true, 'results' => []]);
        exit;
    }

    $searchCondition = '(' . implode(' OR ', $conditions) . ')';

    $results = Database::fetchAll(
        "SELECT DISTINCT e.id, e.title, e.content, e.entry_date, e.mood, e.weather, e.created_at
         FROM diary_entries e
         {$tagJoin}
         WHERE e.user_id = ? AND {$searchCondition}
         ORDER BY e.entry_date DESC, e.created_at DESC
         LIMIT 50",
        $params
    ) ?: [];

    // Process results and get tags
    foreach ($results as &$row) {
        $row['date'] = $row['entry_date'];
        $row['time'] = date('H:i', strtotime($row['created_at']));
        $row['body'] = $row['content'];

        // Get tags for this entry
        $tags = Database::fetchAll(
            "SELECT t.name FROM diary_tags t
             JOIN diary_tag_links l ON t.id = l.tag_id
             WHERE l.entry_id = ?",
            [$row['id']]
        ) ?: [];
        $row['tags'] = array_column($tags, 'name');
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
