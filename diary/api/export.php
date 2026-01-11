<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/security/auth_gate.php';

if (!isset($pdo) || !($pdo instanceof PDO)) { 
    http_response_code(500); 
    die('Database unavailable'); 
}

ini_set('display_errors', '0');
ini_set('log_errors', '1');

$userId = (int)($_SESSION['user_id'] ?? 0);

if ($userId <= 0) { 
    http_response_code(400); 
    die('Unauthorized'); 
}

$format = trim((string)($_GET['format'] ?? 'pdf'));
$entryId = (int)($_GET['id'] ?? 0);

// Get entries
try {
    if ($entryId > 0) {
        // Single entry
        $stmt = $pdo->prepare("
            SELECT * FROM diaries 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$entryId, $userId]);
        $entries = [$stmt->fetch(PDO::FETCH_ASSOC)];
        
        if (!$entries[0]) {
            http_response_code(404);
            die('Entry not found');
        }
    } else {
        // All entries
        $stmt = $pdo->prepare("
            SELECT * FROM diaries 
            WHERE user_id = ? 
            ORDER BY date DESC, time DESC
        ");
        $stmt->execute([$userId]);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if (empty($entries)) {
        http_response_code(404);
        die('No entries found');
    }
    
} catch (Exception $e) {
    error_log('Export fetch error: ' . $e->getMessage());
    http_response_code(500);
    die('Could not fetch entries');
}

// Export based on format
switch($format) {
    case 'json':
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="diary_export_' . date('Y-m-d') . '.json"');
        
        foreach ($entries as &$entry) {
            if ($entry['tags']) {
                $entry['tags'] = json_decode($entry['tags'], true);
            }
        }
        
        echo json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        break;
        
    case 'csv':
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="diary_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Header
        fputcsv($output, ['Date', 'Time', 'Title', 'Body', 'Mood', 'Weather', 'Tags']);
        
        foreach ($entries as $entry) {
            $tags = '';
            if ($entry['tags']) {
                $tagsArr = json_decode($entry['tags'], true);
                $tags = implode(', ', $tagsArr);
            }
            
            fputcsv($output, [
                $entry['date'],
                $entry['time'],
                $entry['title'],
                $entry['body'],
                $entry['mood'],
                $entry['weather'],
                $tags
            ]);
        }
        
        fclose($output);
        break;
        
    case 'txt':
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="diary_export_' . date('Y-m-d') . '.txt"');
        
        foreach ($entries as $entry) {
            echo "========================================\n";
            echo "Date: {$entry['date']} {$entry['time']}\n";
            if ($entry['title']) echo "Title: {$entry['title']}\n";
            if ($entry['mood']) echo "Mood: {$entry['mood']}\n";
            if ($entry['weather']) echo "Weather: {$entry['weather']}\n";
            if ($entry['tags']) {
                $tags = json_decode($entry['tags'], true);
                echo "Tags: " . implode(', ', $tags) . "\n";
            }
            echo "----------------------------------------\n";
            echo $entry['body'] . "\n";
            echo "========================================\n\n";
        }
        break;
        
    case 'pdf':
        // Simple PDF export (requires additional library like TCPDF or FPDF)
        // For now, output HTML that can be printed to PDF
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Diary Export - <?= date('Y-m-d') ?></title>
            <style>
                body {
                    font-family: 'Georgia', serif;
                    max-width: 800px;
                    margin: 0 auto;
                    padding: 20px;
                    line-height: 1.6;
                }
                .entry {
                    page-break-inside: avoid;
                    margin-bottom: 40px;
                    border-bottom: 2px solid #333;
                    padding-bottom: 20px;
                }
                .entry-header {
                    background: #f5f5f5;
                    padding: 15px;
                    margin-bottom: 15px;
                }
                .entry-title {
                    font-size: 24px;
                    font-weight: bold;
                    margin-bottom: 10px;
                }
                .entry-meta {
                    color: #666;
                    font-size: 14px;
                }
                .entry-body {
                    white-space: pre-wrap;
                }
                @media print {
                    body { margin: 0; padding: 15mm; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="no-print" style="margin-bottom: 20px;">
                <button onclick="window.print()">Print to PDF</button>
                <button onclick="window.close()">Close</button>
            </div>
            
            <h1>My Diary</h1>
            <p>Exported: <?= date('Y-m-d H:i:s') ?></p>
            
            <?php foreach ($entries as $entry): ?>
                <div class="entry">
                    <div class="entry-header">
                        <?php if ($entry['title']): ?>
                            <div class="entry-title"><?= htmlspecialchars($entry['title']) ?></div>
                        <?php endif; ?>
                        <div class="entry-meta">
                            📅 <?= htmlspecialchars($entry['date']) ?> 
                            ⏰ <?= htmlspecialchars($entry['time']) ?>
                            <?php if ($entry['mood']): ?>
                                | Mood: <?= htmlspecialchars($entry['mood']) ?>
                            <?php endif; ?>
                            <?php if ($entry['weather']): ?>
                                | Weather: <?= htmlspecialchars($entry['weather']) ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($entry['tags']): ?>
                            <?php $tags = json_decode($entry['tags'], true); ?>
                            <div class="entry-meta">
                                Tags: <?= htmlspecialchars(implode(', ', $tags)) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="entry-body">
                        <?= htmlspecialchars($entry['body']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <script>
                // Auto-print on load (optional)
                // window.onload = function() { window.print(); };
            </script>
        </body>
        </html>
        <?php
        break;
        
    default:
        http_response_code(400);
        die('Invalid format');
}

