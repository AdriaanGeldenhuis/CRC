<?php
/**
 * CRC Bible Load All User Data API
 * GET/POST /bible/api/load_all.php
 *
 * Loads all user data in one request:
 * - Highlights
 * - Notes
 * - Bookmarks
 * - Reading plan progress
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();

// Get all highlights (capped at 2000 for safety)
$highlights = Database::fetchAll(
    "SELECT id, book_number, chapter, verse_start as verse, color
     FROM bible_highlights
     WHERE user_id = ?
     ORDER BY book_number, chapter, verse_start
     LIMIT 2000",
    [$user['id']]
);

// Get all notes (capped at 2000 for safety)
$notes = Database::fetchAll(
    "SELECT id, book_number, chapter, verse_start as verse, content, created_at
     FROM bible_notes
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 2000",
    [$user['id']]
);

// Get all bookmarks (capped at 2000 for safety)
$bookmarks = Database::fetchAll(
    "SELECT id, book_number, chapter, verse_start as verse, created_at
     FROM bible_bookmarks
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 2000",
    [$user['id']]
);

// Get reading plan progress (if reading plan tables exist)
$readingPlan = null;
$readingProgress = [];

// Try to get reading plan - this may fail if table doesn't exist
try {
    $readingPlan = Database::fetchOne(
        "SELECT * FROM bible_reading_plans
         WHERE user_id = ? AND is_active = 1
         ORDER BY created_at DESC LIMIT 1",
        [$user['id']]
    );

    if ($readingPlan) {
        $readingProgress = Database::fetchAll(
            "SELECT * FROM bible_reading_progress
             WHERE plan_id = ?
             ORDER BY reading_date DESC",
            [$readingPlan['id']]
        );
    }
} catch (Exception $e) {
    // Reading plan tables may not exist yet - that's OK
    $readingPlan = null;
    $readingProgress = [];
}

// Return all data
Response::success([
    'highlights' => $highlights,
    'notes' => $notes,
    'bookmarks' => $bookmarks,
    'readingPlan' => $readingPlan,
    'readingProgress' => $readingProgress
]);
