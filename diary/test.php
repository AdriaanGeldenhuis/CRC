<?php
/**
 * Diary Test Page - Debug
 */

require_once __DIR__ . '/../core/bootstrap.php';

// Force HTML content type
header('Content-Type: text/html; charset=UTF-8');

Auth::requireAuth();

$user = Auth::user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Diary Test</title>
</head>
<body>
    <h1>Diary Test Page</h1>
    <p>If you can see this rendered correctly, the basic setup works.</p>
    <p>User: <?= e($user['name']) ?></p>
    <p><a href="/diary/">Go to Diary</a></p>
</body>
</html>
