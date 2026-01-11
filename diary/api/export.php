<?php
/**
 * CRC Diary API - Export
 * Exports diary entries in various formats
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

// Require authentication
if (!Auth::check()) {
    http_response_code(401);
    die('Unauthorized');
}

$user = Auth::user();
$userId = (int)$user['id'];

$format = trim((string)($_GET['format'] ?? 'json'));
$entryId = (int)($_GET['id'] ?? 0);

try {
    if ($entryId > 0) {
        // Single entry
        $entries = Database::fetchAll(
            "SELECT * FROM diary_entries WHERE id = ? AND user_id = ?",
            [$entryId, $userId]
        ) ?: [];
    } else {
        // All entries
        $entries = Database::fetchAll(
            "SELECT * FROM diary_entries WHERE user_id = ? ORDER BY entry_date DESC",
            [$userId]
        ) ?: [];
    }

    if (empty($entries)) {
        http_response_code(404);
        die('No entries found');
    }

    // Process entries and get tags
    foreach ($entries as &$entry) {
        // Get tags for this entry
        $tags = Database::fetchAll(
            "SELECT t.name FROM diary_tags t
             JOIN diary_tag_links l ON t.id = l.tag_id
             WHERE l.entry_id = ?",
            [$entry['id']]
        ) ?: [];
        $entry['tags'] = array_column($tags, 'name');
    }

    switch ($format) {
        case 'json':
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="diary_export_' . date('Y-m-d') . '.json"');
            echo json_encode($entries, JSON_PRETTY_PRINT);
            break;

        case 'csv':
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="diary_export_' . date('Y-m-d') . '.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date', 'Title', 'Content', 'Mood', 'Weather', 'Tags']);
            foreach ($entries as $entry) {
                fputcsv($output, [
                    $entry['entry_date'],
                    $entry['title'],
                    $entry['content'],
                    $entry['mood'],
                    $entry['weather'],
                    implode(', ', $entry['tags'])
                ]);
            }
            fclose($output);
            break;

        case 'txt':
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="diary_export_' . date('Y-m-d') . '.txt"');
            foreach ($entries as $entry) {
                echo "=== " . $entry['entry_date'] . " ===\n";
                echo "Title: " . ($entry['title'] ?: 'Untitled') . "\n";
                if ($entry['mood']) echo "Mood: " . $entry['mood'] . "\n";
                if ($entry['weather']) echo "Weather: " . $entry['weather'] . "\n";
                echo "\n" . $entry['content'] . "\n\n";
                echo "---\n\n";
            }
            break;

        case 'pdf':
        default:
            // Simple HTML-based PDF (browser print)
            header('Content-Type: text/html');
            echo '<!DOCTYPE html><html><head><title>Diary Export</title>';
            echo '<style>body{font-family:Georgia,serif;max-width:800px;margin:0 auto;padding:20px;}';
            echo '.entry{margin-bottom:30px;page-break-inside:avoid;border-bottom:1px solid #ccc;padding-bottom:20px;}';
            echo '.date{color:#666;font-size:14px;}.title{font-size:20px;margin:10px 0;}';
            echo '.meta{color:#888;font-size:12px;margin-bottom:10px;}.body{line-height:1.6;}</style></head><body>';
            echo '<h1>My Diary</h1>';
            foreach ($entries as $entry) {
                echo '<div class="entry">';
                echo '<div class="date">' . $entry['entry_date'] . '</div>';
                echo '<h2 class="title">' . htmlspecialchars($entry['title'] ?: 'Untitled') . '</h2>';
                if ($entry['mood'] || $entry['weather']) {
                    echo '<div class="meta">';
                    if ($entry['mood']) echo 'Mood: ' . $entry['mood'] . ' ';
                    if ($entry['weather']) echo 'Weather: ' . $entry['weather'];
                    echo '</div>';
                }
                echo '<div class="body">' . nl2br(htmlspecialchars($entry['content'] ?? '')) . '</div>';
                echo '</div>';
            }
            echo '<script>window.print();</script></body></html>';
            break;
    }

} catch (Throwable $e) {
    error_log('Diary export error: ' . $e->getMessage());
    http_response_code(500);
    die('Export failed');
}
