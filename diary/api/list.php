<?php
/**
 * CRC Diary API - List Entries
 * Returns list of diary entries with optional filtering
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

// Get filters
$search = trim((string)($_GET['search'] ?? ''));
$sort = trim((string)($_GET['sort'] ?? 'newest'));
$filter = trim((string)($_GET['filter'] ?? 'all'));
$start = trim((string)($_GET['start'] ?? ''));
$end = trim((string)($_GET['end'] ?? ''));
$view = trim((string)($_GET['view'] ?? 'list'));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(100, max(10, (int)($_GET['per_page'] ?? 50)));
$offset = ($page - 1) * $perPage;

try {
    // Build query parts
    $where = ['user_id = ?'];
    $params = [$userId];

    // Apply search filter
    if ($search !== '') {
        $where[] = '(title LIKE ? OR content LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    // Apply date range filter
    if ($start !== '' && $end !== '') {
        $where[] = 'entry_date BETWEEN ? AND ?';
        $params[] = $start;
        $params[] = $end;
    }

    // Apply time filter
    if ($filter !== 'all' && $start === '' && $end === '') {
        $today = date('Y-m-d');
        switch($filter) {
            case 'today':
                $where[] = 'entry_date = ?';
                $params[] = $today;
                break;
            case 'week':
                $weekStart = date('Y-m-d', strtotime('monday this week'));
                $weekEnd = date('Y-m-d', strtotime('sunday this week'));
                $where[] = 'entry_date BETWEEN ? AND ?';
                $params[] = $weekStart;
                $params[] = $weekEnd;
                break;
            case 'month':
                $monthStart = date('Y-m-01');
                $monthEnd = date('Y-m-t');
                $where[] = 'entry_date BETWEEN ? AND ?';
                $params[] = $monthStart;
                $params[] = $monthEnd;
                break;
            case 'year':
                $yearStart = date('Y-01-01');
                $yearEnd = date('Y-12-31');
                $where[] = 'entry_date BETWEEN ? AND ?';
                $params[] = $yearStart;
                $params[] = $yearEnd;
                break;
        }
    }

    // Build ORDER BY
    $orderBy = match($sort) {
        'oldest' => 'entry_date ASC, created_at ASC',
        'title' => 'title ASC, entry_date DESC',
        default => 'entry_date DESC, created_at DESC'
    };

    $whereClause = implode(' AND ', $where);

    $totalCount = (int)(Database::fetchColumn(
        "SELECT COUNT(*) FROM diary_entries WHERE {$whereClause}",
        $params
    ) ?: 0);

    $entries = Database::fetchAll(
        "SELECT id, title, content, entry_date, mood, weather, created_at, updated_at
         FROM diary_entries
         WHERE {$whereClause}
         ORDER BY {$orderBy}
         LIMIT ? OFFSET ?",
        array_merge($params, [$perPage, $offset])
    ) ?: [];

    // Batch-load all tags in a single query (instead of N+1)
    $tagsByEntry = [];
    if ($entries) {
        $entryIds = array_column($entries, 'id');
        $placeholders = implode(',', array_fill(0, count($entryIds), '?'));
        $allTags = Database::fetchAll(
            "SELECT l.entry_id, t.name FROM diary_tags t
             JOIN diary_tag_links l ON t.id = l.tag_id
             WHERE l.entry_id IN ($placeholders)",
            $entryIds
        ) ?: [];
        foreach ($allTags as $tag) {
            $tagsByEntry[$tag['entry_id']][] = $tag['name'];
        }
    }

    // Process entries
    foreach ($entries as &$entry) {
        // Map to expected field names for JS
        $entry['date'] = $entry['entry_date'];
        $entry['time'] = date('H:i', strtotime($entry['created_at']));
        $entry['body'] = $entry['content']; // JS expects 'body'
        $entry['tags'] = $tagsByEntry[$entry['id']] ?? [];
    }
    unset($entry);

    echo json_encode([
        'success' => true,
        'entries' => $entries,
        'count' => count($entries),
        'total' => $totalCount,
        'page' => $page,
        'per_page' => $perPage
    ]);

} catch (Throwable $e) {
    error_log('Diary list error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'server_error',
        'message' => 'Could not fetch diary entries'
    ]);
}
