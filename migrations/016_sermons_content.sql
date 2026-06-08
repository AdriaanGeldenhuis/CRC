-- =====================================================================
-- 016_sermons_content.sql
-- Adds the `sermons` and `content` tables used by the Admin panel
-- (and the Media module for sermons). Safe to run multiple times.
-- =====================================================================

-- ---------- Sermons ----------
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
    scope ENUM('global', 'congregation') DEFAULT 'congregation',
    views INT UNSIGNED DEFAULT 0,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (congregation_id) REFERENCES congregations(id) ON DELETE CASCADE,
    FOREIGN KEY (speaker_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_congregation (congregation_id),
    INDEX idx_status (status),
    INDEX idx_sermon_date (sermon_date),
    INDEX idx_category (category),
    INDEX idx_series (series_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Content (pages / articles / announcements / devotionals / resources) ----------
CREATE TABLE IF NOT EXISTS content (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'page',
    body LONGTEXT DEFAULT NULL,
    author VARCHAR(255) DEFAULT NULL,
    status ENUM('draft', 'published') DEFAULT 'draft',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_type (type),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
