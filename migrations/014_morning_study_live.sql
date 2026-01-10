-- CRC Morning Study Live Migration
-- Adds live streaming, chat, shared notes, and recap features

-- Add study/live fields to morning_sessions
ALTER TABLE morning_sessions
    ADD COLUMN content_mode ENUM('watch','study') NOT NULL DEFAULT 'watch' AFTER theme,
    ADD COLUMN key_verse VARCHAR(100) DEFAULT NULL AFTER content_mode,
    ADD COLUMN study_questions JSON DEFAULT NULL AFTER key_verse,
    ADD COLUMN stream_url VARCHAR(255) DEFAULT NULL AFTER media_url,
    ADD COLUMN replay_url VARCHAR(255) DEFAULT NULL AFTER stream_url,
    ADD COLUMN live_status ENUM('scheduled','live','ended') NOT NULL DEFAULT 'scheduled' AFTER replay_url,
    ADD COLUMN live_starts_at DATETIME DEFAULT NULL AFTER live_status,
    ADD COLUMN live_ended_at DATETIME DEFAULT NULL AFTER live_starts_at;

-- Live study attendance tracking
CREATE TABLE IF NOT EXISTS morning_study_attendance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    mode ENUM('live','replay') NOT NULL DEFAULT 'live',
    joined_at TIMESTAMP NULL DEFAULT NULL,
    left_at TIMESTAMP NULL DEFAULT NULL,
    duration_minutes INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES morning_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_session_user_mode (session_id, user_id, mode),
    INDEX idx_session_id (session_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Live chat messages
CREATE TABLE IF NOT EXISTS morning_study_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    message_type ENUM('chat','question','announcement') NOT NULL DEFAULT 'chat',
    is_answered TINYINT(1) DEFAULT 0,
    answered_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES morning_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session_id_created (session_id, created_at),
    INDEX idx_session_type (session_id, message_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shared notes (group board)
CREATE TABLE IF NOT EXISTS morning_study_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    content TEXT NOT NULL,
    is_pinned TINYINT(1) DEFAULT 0,
    is_hidden TINYINT(1) DEFAULT 0,
    pinned_by INT UNSIGNED DEFAULT NULL,
    pinned_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES morning_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (pinned_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_session_pinned (session_id, is_pinned),
    INDEX idx_session_id (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session recaps (auto-generated summary)
CREATE TABLE IF NOT EXISTS morning_study_recaps (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL,
    recap_text LONGTEXT NOT NULL,
    recap_json JSON DEFAULT NULL,
    action_point VARCHAR(500) DEFAULT NULL,
    memory_verse VARCHAR(255) DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES morning_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_session_recap (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bookmarks/timestamps in sessions
CREATE TABLE IF NOT EXISTS morning_study_bookmarks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    timestamp_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    label VARCHAR(255) NOT NULL,
    is_public TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES morning_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session_id (session_id),
    INDEX idx_session_public (session_id, is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Prayer requests after study
CREATE TABLE IF NOT EXISTS morning_study_prayers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    prayer_request TEXT NOT NULL,
    is_private TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES morning_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session_id (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add notification types for morning study
INSERT INTO notification_types (type_key, name, description, default_enabled, icon)
VALUES
    ('morning_study_live', 'Morning Study Live', 'When morning study goes live', 1, 'sunrise'),
    ('morning_study_recap', 'Morning Study Recap', 'When recap is published', 1, 'book'),
    ('morning_study_reminder', 'Morning Study Reminder', 'Reminder before study starts', 1, 'clock')
ON DUPLICATE KEY UPDATE name=VALUES(name);
