-- CRC Media Migration
-- Media items (sermons, podcasts, videos), categories, playlists

-- Media categories
CREATE TABLE IF NOT EXISTS media_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    congregation_id INT UNSIGNED DEFAULT NULL,
    parent_id INT UNSIGNED DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    cover_image VARCHAR(255) DEFAULT NULL,
    sort_order INT UNSIGNED DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (congregation_id) REFERENCES congregations(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES media_categories(id) ON DELETE SET NULL,
    UNIQUE KEY unique_slug (congregation_id, slug),
    INDEX idx_congregation_id (congregation_id),
    INDEX idx_parent_id (parent_id),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Media items
CREATE TABLE IF NOT EXISTS media_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    congregation_id INT UNSIGNED DEFAULT NULL,
    category_id INT UNSIGNED DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    media_type ENUM('video', 'audio', 'pdf', 'youtube', 'vimeo', 'external_link') NOT NULL,
    media_url VARCHAR(500) NOT NULL,
    thumbnail_url VARCHAR(255) DEFAULT NULL,
    duration_seconds INT UNSIGNED DEFAULT NULL,
    file_size INT UNSIGNED DEFAULT NULL,
    speaker VARCHAR(255) DEFAULT NULL,
    scripture_refs JSON DEFAULT NULL,
    tags JSON DEFAULT NULL,
    series_id INT UNSIGNED DEFAULT NULL,
    series_order INT UNSIGNED DEFAULT NULL,
    scope ENUM('global', 'congregation') DEFAULT 'congregation',
    is_featured TINYINT(1) DEFAULT 0,
    is_published TINYINT(1) DEFAULT 0,
    published_at TIMESTAMP NULL DEFAULT NULL,
    view_count INT UNSIGNED DEFAULT 0,
    download_count INT UNSIGNED DEFAULT 0,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (congregation_id) REFERENCES congregations(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES media_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_slug (congregation_id, slug),
    INDEX idx_congregation_id (congregation_id),
    INDEX idx_category_id (category_id),
    INDEX idx_media_type (media_type),
    INDEX idx_scope (scope),
    INDEX idx_is_published (is_published),
    INDEX idx_published_at (published_at),
    INDEX idx_series_id (series_id),
    FULLTEXT idx_search (title, description, speaker)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Media series
CREATE TABLE IF NOT EXISTS media_series (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    congregation_id INT UNSIGNED DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    cover_image VARCHAR(255) DEFAULT NULL,
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (congregation_id) REFERENCES congregations(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_slug (congregation_id, slug),
    INDEX idx_congregation_id (congregation_id),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key for series
ALTER TABLE media_items ADD FOREIGN KEY (series_id) REFERENCES media_series(id) ON DELETE SET NULL;

-- User playlists
CREATE TABLE IF NOT EXISTS media_playlists (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    cover_image VARCHAR(255) DEFAULT NULL,
    is_public TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_public (is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Playlist items
CREATE TABLE IF NOT EXISTS media_playlist_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    playlist_id INT UNSIGNED NOT NULL,
    media_item_id INT UNSIGNED NOT NULL,
    sort_order INT UNSIGNED DEFAULT 0,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (playlist_id) REFERENCES media_playlists(id) ON DELETE CASCADE,
    FOREIGN KEY (media_item_id) REFERENCES media_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_playlist_item (playlist_id, media_item_id),
    INDEX idx_playlist_id (playlist_id),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User media history (watch/listen history)
CREATE TABLE IF NOT EXISTS media_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    media_item_id INT UNSIGNED NOT NULL,
    progress_seconds INT UNSIGNED DEFAULT 0,
    progress_percent INT UNSIGNED DEFAULT 0,
    completed TINYINT(1) DEFAULT 0,
    last_watched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    watch_count INT UNSIGNED DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (media_item_id) REFERENCES media_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_media (user_id, media_item_id),
    INDEX idx_user_id (user_id),
    INDEX idx_last_watched_at (last_watched_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User saved items (favorites)
CREATE TABLE IF NOT EXISTS media_saved (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    media_item_id INT UNSIGNED NOT NULL,
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (media_item_id) REFERENCES media_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_saved (user_id, media_item_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default categories
INSERT INTO media_categories (congregation_id, name, slug, sort_order) VALUES
(NULL, 'Sermons', 'sermons', 1),
(NULL, 'Worship', 'worship', 2),
(NULL, 'Bible Studies', 'bible-studies', 3),
(NULL, 'Testimonies', 'testimonies', 4),
(NULL, 'Events', 'events', 5),
(NULL, 'Youth', 'youth', 6),
(NULL, 'Kids', 'kids', 7);
