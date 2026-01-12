-- CRC News/Media feature for homepage
-- Super admin can upload images that display in a carousel

CREATE TABLE IF NOT EXISTS news_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    description TEXT DEFAULT NULL,
    link_url VARCHAR(500) DEFAULT NULL,
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI Message of the Day table
CREATE TABLE IF NOT EXISTS ai_daily_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_date DATE NOT NULL UNIQUE,
    message_content TEXT NOT NULL,
    scripture_ref VARCHAR(255) DEFAULT NULL,
    mood VARCHAR(50) DEFAULT 'inspirational',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index for quick lookups
CREATE INDEX idx_news_active_order ON news_items(is_active, display_order);
CREATE INDEX idx_ai_messages_date ON ai_daily_messages(message_date);
