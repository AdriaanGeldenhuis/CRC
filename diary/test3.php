<?php
/**
 * Diary Test 3 - With Database Queries
 */

require_once __DIR__ . '/../core/bootstrap.php';

header('Content-Type: text/html; charset=UTF-8');

Auth::requireAuth();

$user = Auth::user();

// Test database queries
$totalEntries = 0;
$dbError = null;

try {
    $totalEntries = Database::fetchColumn(
        "SELECT COUNT(*) FROM diary_entries WHERE user_id = ?",
        [$user['id']]
    ) ?: 0;
} catch (Exception $e) {
    $dbError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test 3 - Database</title>
</head>
<body>
    <h1>Test 3 - Database Queries</h1>
    <p>User: <?= e($user['name']) ?></p>
    <p>Total Entries: <?= $totalEntries ?></p>
    <?php if ($dbError): ?>
        <p style="color:red">DB Error: <?= e($dbError) ?></p>
    <?php endif; ?>
</body>
</html>
