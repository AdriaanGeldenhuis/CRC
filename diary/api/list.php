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

    $entries = Database::fetchAll(
        "SELECT id, title, content, entry_date, mood, weather, created_at, updated_at
         FROM diary_entries
         WHERE {$whereClause}
         ORDER BY {$orderBy}
         LIMIT 500",
        $params
    ) ?: [];

    // Process entries and get tags
    foreach ($entries as &$entry) {
        // Map to expected field names for JS
        $entry['date'] = $entry['entry_date'];
        $entry['time'] = date('H:i', strtotime($entry['created_at']));
        $entry['body'] = $entry['content']; // JS expects 'body'

        // Get tags for this entry
        $tags = Database::fetchAll(
            "SELECT t.name FROM diary_tags t
             JOIN diary_tag_links l ON t.id = l.tag_id
             WHERE l.entry_id = ?",
            [$entry['id']]
        ) ?: [];
        $entry['tags'] = array_column($tags, 'name');
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
