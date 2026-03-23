<?php
/**
 * CRC Diary Helpers
 * Shared functions for diary operations
 */

/**
 * Sync tags for a diary entry.
 * Creates tags if they don't exist, links them to the entry.
 * For updates, caller should delete existing tag links first.
 */
function syncEntryTags(int $entryId, int $userId, array $tags): void {
    foreach ($tags as $tagName) {
        $tagName = trim($tagName);
        if (empty($tagName)) continue;

        // Get or create tag
        $tag = Database::fetchOne(
            "SELECT id FROM diary_tags WHERE user_id = ? AND name = ?",
            [$userId, $tagName]
        );

        if (!$tag) {
            $tagId = Database::insert('diary_tags', [
                'user_id' => $userId,
                'name' => $tagName
            ]);
        } else {
            $tagId = $tag['id'];
        }

        // Link tag to entry
        if ($tagId) {
            try {
                Database::insert('diary_tag_links', [
                    'entry_id' => $entryId,
                    'tag_id' => $tagId
                ]);
            } catch (Throwable $e) {
                // Ignore duplicate tag links
            }
        }
    }
}
