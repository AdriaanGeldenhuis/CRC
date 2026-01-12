-- CRC Diary Enhanced Features Migration
-- Adds time, reminder, calendar integration, and sharing support

-- Add new columns to diary_entries
ALTER TABLE diary_entries
    ADD COLUMN entry_time TIME DEFAULT '00:00:00' AFTER entry_date,
    ADD COLUMN reminder_minutes INT UNSIGNED DEFAULT 60 AFTER is_locked,
    ADD COLUMN calendar_event_id INT UNSIGNED DEFAULT NULL AFTER reminder_minutes,
    ADD COLUMN share_token VARCHAR(64) DEFAULT NULL AFTER calendar_event_id,
    ADD INDEX idx_calendar_event_id (calendar_event_id),
    ADD INDEX idx_share_token (share_token);

-- Create diary_shares table for shared entries
CREATE TABLE IF NOT EXISTS diary_shares (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    share_token VARCHAR(64) NOT NULL,
    share_type ENUM('link', 'friend') DEFAULT 'link',
    shared_with_user_id INT UNSIGNED DEFAULT NULL,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    views INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (entry_id) REFERENCES diary_entries(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_share_token (share_token),
    INDEX idx_entry_id (entry_id),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create diary_entry_tags as an alias table (some code uses this name instead of diary_tag_links)
-- Check if the table already exists first
CREATE TABLE IF NOT EXISTS diary_entry_tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (entry_id) REFERENCES diary_entries(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES diary_tags(id) ON DELETE CASCADE,
    UNIQUE KEY unique_entry_tag (entry_id, tag_id),
    INDEX idx_entry_id (entry_id),
    INDEX idx_tag_id (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
