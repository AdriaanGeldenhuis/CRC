-- CRC Bible Migration
-- Bible versions, verses, highlights, notes, tags, bookmarks, collections, AI cache

-- Bible versions (metadata)
CREATE TABLE IF NOT EXISTS bible_versions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    language VARCHAR(50) DEFAULT 'en',
    description TEXT DEFAULT NULL,
    copyright TEXT DEFAULT NULL,
    source_type ENUM('api', 'json', 'database') DEFAULT 'api',
    source_url VARCHAR(255) DEFAULT NULL,
    is_default TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bible books reference
CREATE TABLE IF NOT EXISTS bible_books (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    version_id INT UNSIGNED NOT NULL,
    book_number TINYINT UNSIGNED NOT NULL,
    name VARCHAR(50) NOT NULL,
    abbreviation VARCHAR(10) NOT NULL,
    testament ENUM('old', 'new') NOT NULL,
    chapters TINYINT UNSIGNED NOT NULL,
    FOREIGN KEY (version_id) REFERENCES bible_versions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_version_book (version_id, book_number),
    INDEX idx_version_id (version_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bible verses (optional - for offline/cached versions)
CREATE TABLE IF NOT EXISTS bible_verses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    version_id INT UNSIGNED NOT NULL,
    book_number TINYINT UNSIGNED NOT NULL,
    chapter SMALLINT UNSIGNED NOT NULL,
    verse SMALLINT UNSIGNED NOT NULL,
    text TEXT NOT NULL,
    FOREIGN KEY (version_id) REFERENCES bible_versions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_verse (version_id, book_number, chapter, verse),
    INDEX idx_reference (version_id, book_number, chapter),
    FULLTEXT idx_text (text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User highlights
CREATE TABLE IF NOT EXISTS bible_highlights (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    version_code VARCHAR(20) NOT NULL,
    book_number TINYINT UNSIGNED NOT NULL,
    chapter SMALLINT UNSIGNED NOT NULL,
    verse_start SMALLINT UNSIGNED NOT NULL,
    verse_end SMALLINT UNSIGNED DEFAULT NULL,
    color VARCHAR(20) DEFAULT 'yellow',
    style VARCHAR(20) DEFAULT 'highlight',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_reference (version_code, book_number, chapter)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User notes
CREATE TABLE IF NOT EXISTS bible_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    version_code VARCHAR(20) DEFAULT NULL,
    book_number TINYINT UNSIGNED DEFAULT NULL,
    chapter SMALLINT UNSIGNED DEFAULT NULL,
    verse_start SMALLINT UNSIGNED DEFAULT NULL,
    verse_end SMALLINT UNSIGNED DEFAULT NULL,
    note_type ENUM('verse', 'passage', 'topic', 'general') DEFAULT 'verse',
    title VARCHAR(255) DEFAULT NULL,
    content TEXT NOT NULL,
    is_private TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_reference (version_code, book_number, chapter),
    INDEX idx_note_type (note_type),
    FULLTEXT idx_content (title, content)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User tags
CREATE TABLE IF NOT EXISTS bible_tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(50) NOT NULL,
    color VARCHAR(20) DEFAULT 'blue',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_tag (user_id, name),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tag links (verses/notes linked to tags)
CREATE TABLE IF NOT EXISTS bible_tag_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tag_id INT UNSIGNED NOT NULL,
    taggable_type ENUM('verse', 'note', 'passage') NOT NULL,
    version_code VARCHAR(20) DEFAULT NULL,
    book_number TINYINT UNSIGNED DEFAULT NULL,
    chapter SMALLINT UNSIGNED DEFAULT NULL,
    verse_start SMALLINT UNSIGNED DEFAULT NULL,
    verse_end SMALLINT UNSIGNED DEFAULT NULL,
    note_id INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tag_id) REFERENCES bible_tags(id) ON DELETE CASCADE,
    FOREIGN KEY (note_id) REFERENCES bible_notes(id) ON DELETE CASCADE,
    INDEX idx_tag_id (tag_id),
    INDEX idx_reference (version_code, book_number, chapter)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User bookmarks
CREATE TABLE IF NOT EXISTS bible_bookmarks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    collection_id INT UNSIGNED DEFAULT NULL,
    version_code VARCHAR(20) NOT NULL,
    book_number TINYINT UNSIGNED NOT NULL,
    chapter SMALLINT UNSIGNED NOT NULL,
    verse_start SMALLINT UNSIGNED DEFAULT NULL,
    verse_end SMALLINT UNSIGNED DEFAULT NULL,
    title VARCHAR(255) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_collection_id (collection_id),
    INDEX idx_reference (version_code, book_number, chapter)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User collections (folders for bookmarks)
CREATE TABLE IF NOT EXISTS bible_collections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    color VARCHAR(20) DEFAULT 'blue',
    is_default TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key for bookmarks.collection_id
ALTER TABLE bible_bookmarks ADD FOREIGN KEY (collection_id) REFERENCES bible_collections(id) ON DELETE SET NULL;

-- AI explanation cache
CREATE TABLE IF NOT EXISTS bible_ai_cache (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cache_key VARCHAR(64) NOT NULL UNIQUE,
    version_code VARCHAR(20) NOT NULL,
    reference VARCHAR(100) NOT NULL,
    mode ENUM('explain_verse', 'explain_context', 'why_chosen') NOT NULL,
    context_hash VARCHAR(64) DEFAULT NULL,
    response TEXT NOT NULL,
    tokens_used INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_cache_key (cache_key),
    INDEX idx_reference (version_code, reference),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User AI usage tracking
CREATE TABLE IF NOT EXISTS bible_ai_usage (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    request_count INT UNSIGNED DEFAULT 0,
    tokens_used INT UNSIGNED DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_date (user_id, date),
    INDEX idx_user_id (user_id),
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default Bible versions
INSERT INTO bible_versions (code, name, language, description, is_default, is_active) VALUES
('KJV', 'King James Version', 'en', 'Traditional English translation', 1, 1),
('NIV', 'New International Version', 'en', 'Modern English translation', 0, 1),
('ESV', 'English Standard Version', 'en', 'Essentially literal translation', 0, 1),
('NLT', 'New Living Translation', 'en', 'Thought-for-thought translation', 0, 1),
('AFR83', 'Afrikaans 1983', 'af', 'Afrikaanse vertaling 1983', 0, 1),
('AFR53', 'Afrikaans 1953', 'af', 'Afrikaanse vertaling 1953', 0, 1);
