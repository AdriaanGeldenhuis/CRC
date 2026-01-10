<?php
/**
 * Diary Test 2 - With Navbar
 */

require_once __DIR__ . '/../core/bootstrap.php';

header('Content-Type: text/html; charset=UTF-8');

Auth::requireAuth();

$user = Auth::user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test 2 - With Navbar</title>
    <link rel="stylesheet" href="/diary/css/diary.css">
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <h1>Test 2 - With Navbar</h1>
            <p>If you see this with the navbar, the navbar include works.</p>
            <p>User: <?= e($user['name']) ?></p>
        </div>
    </main>
</body>
</html>
