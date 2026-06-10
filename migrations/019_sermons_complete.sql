-- =====================================================================
-- 019_sermons_complete.sql
-- Completes the Sermons feature end-to-end. Brings the `sermons` table up
-- to the full schema the Media + Admin modules expect, adds the columns
-- that the front-end reads (scripture_references, timestamps) and creates
-- every supporting table (series / notes / saved / progress).
--
-- Fully idempotent and safe to run multiple times. Uses only top-level
-- statements (no stored procedures / DELIMITER) so it runs identically via
-- the `mysql` CLI and via PDO::exec() in _scripts/migrate.php.
-- =====================================================================

-- ---------- Core table (created complete if it does not exist yet) ----------
CREATE TABLE IF NOT EXISTS sermons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    congregation_id INT UNSIGNED DEFAULT NULL,
    series_id INT UNSIGNED DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    speaker VARCHAR(255) DEFAULT NULL,
    speaker_user_id INT UNSIGNED DEFAULT NULL,
    sermon_date DATE DEFAULT NULL,
    description TEXT DEFAULT NULL,
    video_url VARCHAR(500) DEFAULT NULL,
    audio_url VARCHAR(500) DEFAULT NULL,
    thumbnail VARCHAR(500) DEFAULT NULL,
    duration INT UNSIGNED DEFAULT NULL,
    category VARCHAR(100) DEFAULT NULL,
    scripture_references TEXT DEFAULT NULL,
    `timestamps` TEXT DEFAULT NULL,
    scope ENUM('global', 'congregation') DEFAULT 'congregation',
    views INT UNSIGNED DEFAULT 0,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_congregation (congregation_id),
    INDEX idx_status (status),
    INDEX idx_sermon_date (sermon_date),
    INDEX idx_category (category),
    INDEX idx_series (series_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Self-heal: add any column the app needs that is missing ----------
-- (covers older / partial `sermons` tables created before this migration)

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sermons' AND COLUMN_NAME='congregation_id');
SET @s := IF(@c=0, 'ALTER TABLE sermons ADD COLUMN congregation_id INT UNSIGNED DEFAULT NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sermons' AND COLUMN_NAME='series_id');
SET @s := IF(@c=0, 'ALTER TABLE sermons ADD COLUMN series_id INT UNSIGNED DEFAULT NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sermons' AND COLUMN_NAME='speaker');
SET @s := IF(@c=0, 'ALTER TABLE sermons ADD COLUMN speaker VARCHAR(255) DEFAULT NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sermons' AND COLUMN_NAME='speaker_user_id');
SET @s := IF(@c=0, 'ALTER TABLE sermons ADD COLUMN speaker_user_id INT UNSIGNED DEFAULT NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sermons' AND COLUMN_NAME='sermon_date');
SET @s := IF(@c=0, 'ALTER TABLE sermons ADD COLUMN sermon_date DATE DEFAULT NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sermons' AND COLUMN_NAME='description');
SET @s := IF(@c=0, 'ALTER TABLE sermons ADD COLUMN description TEXT DEFAULT NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sermons' AND COLUMN_NAME='video_url');
SET @s := IF(@c=0, 'ALTER TABLE sermons ADD COLUMN video_url VARCHAR(500) DEFAULT NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sermons' AND COLUMN_NAME='audio_url');
SET @s := IF(@c=0, 'ALTER TABLE sermons ADD COLUMN audio_url VARCHAR(500) DEFAULT NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sermons' AND COLUMN_NAME='thumbnail');
SET @s := IF(@c=0, 'ALTER TABLE sermons ADD COLUMN thumbnail VARCHAR(500) DEFAULT NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sermons' AND COLUMN_NAME='duration');
SET @s := IF(@c=0, 'ALTER TABLE sermons ADD COLUMN duration INT UNSIGNED DEFAULT NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sermons' AND COLUMN_NAME='category');
SET @s := IF(@c=0, 'ALTER TABLE sermons ADD COLUMN category VARCHAR(100) DEFAULT NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sermons' AND COLUMN_NAME='scripture_references');
SET @s := IF(@c=0, 'ALTER TABLE sermons ADD COLUMN scripture_references TEXT DEFAULT NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sermons' AND COLUMN_NAME='timestamps');
SET @s := IF(@c=0, 'ALTER TABLE sermons ADD COLUMN `timestamps` TEXT DEFAULT NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sermons' AND COLUMN_NAME='scope');
SET @s := IF(@c=0, "ALTER TABLE sermons ADD COLUMN scope ENUM('global','congregation') DEFAULT 'congregation'", 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sermons' AND COLUMN_NAME='views');
SET @s := IF(@c=0, 'ALTER TABLE sermons ADD COLUMN views INT UNSIGNED DEFAULT 0', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sermons' AND COLUMN_NAME='status');
SET @s := IF(@c=0, "ALTER TABLE sermons ADD COLUMN status ENUM('draft','published','archived') DEFAULT 'draft'", 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------- Sermon series (cover image lives in `image`) ----------
CREATE TABLE IF NOT EXISTS sermon_series (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    congregation_id INT UNSIGNED DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    image VARCHAR(500) DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_congregation (congregation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sermon_series' AND COLUMN_NAME='image');
SET @s := IF(@c=0, 'ALTER TABLE sermon_series ADD COLUMN image VARCHAR(500) DEFAULT NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------- Per-user notes (one per user per sermon) ----------
CREATE TABLE IF NOT EXISTS sermon_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sermon_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    content TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sermon_user (sermon_id, user_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Saved / bookmarked sermons ----------
CREATE TABLE IF NOT EXISTS user_saved_sermons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    sermon_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_sermon (user_id, sermon_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Playback progress (resume where you left off) ----------
CREATE TABLE IF NOT EXISTS sermon_progress (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    sermon_id INT UNSIGNED NOT NULL,
    position INT UNSIGNED DEFAULT 0,
    completed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_sermon (user_id, sermon_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
