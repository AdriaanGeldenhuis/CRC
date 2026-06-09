-- CRC Notifications Settings Fix
-- The original notification_settings table (010) modelled one row per
-- (user, type_key) and required a NOT NULL type_key, but the settings API and
-- UI were written against a flat one-row-per-user model with per-category
-- boolean toggles. As a result every save_settings call failed with an
-- "unknown column" / missing type_key error and no preferences could be stored.
--
-- This migration rebuilds notification_settings to match the API/UI exactly.
-- The old table can only ever have been empty (saves always errored), so the
-- DROP is safe.

DROP TABLE IF EXISTS notification_settings;

CREATE TABLE notification_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    email_enabled       TINYINT(1) DEFAULT 1,
    push_enabled        TINYINT(1) DEFAULT 1,
    event_reminders     TINYINT(1) DEFAULT 1,
    prayer_updates      TINYINT(1) DEFAULT 1,
    homecell_updates    TINYINT(1) DEFAULT 1,
    course_updates      TINYINT(1) DEFAULT 1,
    sermon_updates      TINYINT(1) DEFAULT 1,
    livestream_alerts   TINYINT(1) DEFAULT 1,
    announcements       TINYINT(1) DEFAULT 1,
    social_updates      TINYINT(1) DEFAULT 1,   -- comments & reactions on your posts
    digest_frequency    ENUM('none', 'daily', 'weekly') DEFAULT 'daily',
    quiet_hours_start   TIME NULL DEFAULT NULL,
    quiet_hours_end     TIME NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
