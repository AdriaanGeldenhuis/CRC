-- CRC Diary & Prayer Journal Migration
-- Diary entries, tags, prayer requests

-- Diary entries
CREATE TABLE IF NOT EXISTS diary_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) DEFAULT NULL,
    content TEXT NOT NULL,
    mood VARCHAR(50) DEFAULT NULL,
    weather VARCHAR(50) DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    scripture_refs JSON DEFAULT NULL,
    media JSON DEFAULT NULL,
    is_private TINYINT(1) DEFAULT 1,
    is_locked TINYINT(1) DEFAULT 0,
    lock_pin_hash VARCHAR(255) DEFAULT NULL,
    entry_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_entry_date (entry_date),
    INDEX idx_is_private (is_private),
    FULLTEXT idx_content (title, content)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Diary tags
CREATE TABLE IF NOT EXISTS diary_tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(50) NOT NULL,
    color VARCHAR(20) DEFAULT 'gray',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_tag (user_id, name),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Diary tag links
CREATE TABLE IF NOT EXISTS diary_tag_links (
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

-- Prayer requests
CREATE TABLE IF NOT EXISTS prayer_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    homecell_id INT UNSIGNED DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    scripture_ref VARCHAR(100) DEFAULT NULL,
    category VARCHAR(50) DEFAULT 'general',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    visibility ENUM('private', 'homecell', 'congregation') DEFAULT 'private',
    status ENUM('active', 'answered', 'archived') DEFAULT 'active',
    answered_at TIMESTAMP NULL DEFAULT NULL,
    answered_notes TEXT DEFAULT NULL,
    reminder_date DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_homecell_id (homecell_id),
    INDEX idx_status (status),
    INDEX idx_visibility (visibility),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Prayer entries (daily prayer log)
CREATE TABLE IF NOT EXISTS prayer_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    prayer_request_id INT UNSIGNED DEFAULT NULL,
    content TEXT DEFAULT NULL,
    prayed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    duration_minutes INT UNSIGNED DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (prayer_request_id) REFERENCES prayer_requests(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_prayer_request_id (prayer_request_id),
    INDEX idx_prayed_at (prayed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
