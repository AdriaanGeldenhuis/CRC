<?php
/**
 * CRC Bible Data API
 * Serves the Bible JSON file
 * GET /bible/api/bible_data.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();

$bibleFile = __DIR__ . '/../bibles/en_kjv.json';

if (!file_exists($bibleFile)) {
    Response::error('Bible file not found', 404);
}

$content = file_get_contents($bibleFile);

if ($content === false) {
    Response::error('Failed to read Bible file', 500);
}

$data = json_decode($content, true);

if ($data === null) {
    Response::error('Invalid Bible JSON', 500);
}

header('Content-Type: application/json');
echo $content;
exit;
