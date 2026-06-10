-- =====================================================================
-- 020_livestreams_complete.sql
-- Completes the Livestream feature. The Media pages read recording_url,
-- thumbnail_url and duration which were never in the schema (causing the
-- "past services" query to throw). This brings `livestreams` up to the
-- full schema and ensures every supporting table exists.
--
-- Idempotent / self-healing. Top-level statements only so it runs the same
-- via the mysql CLI and via PDO::exec() in _scripts/migrate.php.
-- =====================================================================

-- ---------- Core table (created complete if it does not exist yet) ----------
CREATE TABLE IF NOT EXISTS livestreams (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    congregation_id INT UNSIGNED DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    embed_url VARCHAR(500) DEFAULT NULL,
    recording_url VARCHAR(500) DEFAULT NULL,
    thumbnail_url VARCHAR(500) DEFAULT NULL,
    duration INT UNSIGNED DEFAULT NULL,
    status ENUM('scheduled', 'live', 'ended') DEFAULT 'scheduled',
    scheduled_at DATETIME DEFAULT NULL,
    started_at DATETIME DEFAULT NULL,
    ended_at DATETIME DEFAULT NULL,
    viewer_count INT UNSIGNED DEFAULT 0,
    chat_enabled TINYINT(1) DEFAULT 1,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_congregation (congregation_id),
    INDEX idx_status (status),
    INDEX idx_scheduled_at (scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Self-heal: add any column the app needs that is missing ----------
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='livestreams' AND COLUMN_NAME='description');
SET @s := IF(@c=0, 'ALTER TABLE livestreams ADD COLUMN description TEXT DEFAULT NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='livestreams' AND COLUMN_NAME='embed_url');
SET @s := IF(@c=0, 'ALTER TABLE livestreams ADD COLUMN embed_url VARCHAR(500) DEFAULT NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='livestreams' AND COLUMN_NAME='recording_url');
SET @s := IF(@c=0, 'ALTER TABLE livestreams ADD COLUMN recording_url VARCHAR(500) DEFAULT NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='livestreams' AND COLUMN_NAME='thumbnail_url');
SET @s := IF(@c=0, 'ALTER TABLE livestreams ADD COLUMN thumbnail_url VARCHAR(500) DEFAULT NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='livestreams' AND COLUMN_NAME='duration');
SET @s := IF(@c=0, 'ALTER TABLE livestreams ADD COLUMN duration INT UNSIGNED DEFAULT NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='livestreams' AND COLUMN_NAME='status');
SET @s := IF(@c=0, "ALTER TABLE livestreams ADD COLUMN status ENUM('scheduled','live','ended') DEFAULT 'scheduled'", 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='livestreams' AND COLUMN_NAME='scheduled_at');
SET @s := IF(@c=0, 'ALTER TABLE livestreams ADD COLUMN scheduled_at DATETIME DEFAULT NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='livestreams' AND COLUMN_NAME='started_at');
SET @s := IF(@c=0, 'ALTER TABLE livestreams ADD COLUMN started_at DATETIME DEFAULT NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='livestreams' AND COLUMN_NAME='ended_at');
SET @s := IF(@c=0, 'ALTER TABLE livestreams ADD COLUMN ended_at DATETIME DEFAULT NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='livestreams' AND COLUMN_NAME='viewer_count');
SET @s := IF(@c=0, 'ALTER TABLE livestreams ADD COLUMN viewer_count INT UNSIGNED DEFAULT 0', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='livestreams' AND COLUMN_NAME='chat_enabled');
SET @s := IF(@c=0, 'ALTER TABLE livestreams ADD COLUMN chat_enabled TINYINT(1) DEFAULT 1', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='livestreams' AND COLUMN_NAME='created_by');
SET @s := IF(@c=0, 'ALTER TABLE livestreams ADD COLUMN created_by INT UNSIGNED DEFAULT NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------- Live chat ----------
CREATE TABLE IF NOT EXISTS livestream_chat (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    livestream_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_livestream (livestream_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Reminders ----------
CREATE TABLE IF NOT EXISTS livestream_reminders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    livestream_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_stream_user (livestream_id, user_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Scheduled notifications (livestream reminders, etc.) ----------
CREATE TABLE IF NOT EXISTS scheduled_notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'system',
    title VARCHAR(255) NOT NULL,
    message TEXT DEFAULT NULL,
    link VARCHAR(500) DEFAULT NULL,
    send_at DATETIME NOT NULL,
    sent_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_send_at (send_at),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
