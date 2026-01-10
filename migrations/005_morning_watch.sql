-- CRC Morning Watch Migration
-- Morning sessions, user entries, streaks

-- Morning watch sessions (daily content)
CREATE TABLE IF NOT EXISTS morning_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    congregation_id INT UNSIGNED DEFAULT NULL,
    scope ENUM('global', 'congregation') DEFAULT 'global',
    session_date DATE NOT NULL,
    title VARCHAR(255) DEFAULT NULL,
    theme VARCHAR(255) DEFAULT NULL,
    scripture_ref VARCHAR(100) NOT NULL,
    scripture_text TEXT DEFAULT NULL,
    version_code VARCHAR(20) DEFAULT 'KJV',
    devotional TEXT DEFAULT NULL,
    prayer_points JSON DEFAULT NULL,
    leader_notes TEXT DEFAULT NULL,
    media_url VARCHAR(255) DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    published_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (congregation_id) REFERENCES congregations(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_session_date (congregation_id, session_date, scope),
    INDEX idx_session_date (session_date),
    INDEX idx_scope (scope),
    INDEX idx_congregation_id (congregation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User morning watch entries
CREATE TABLE IF NOT EXISTS morning_user_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    session_id INT UNSIGNED NOT NULL,
    personal_notes TEXT DEFAULT NULL,
    prayer_notes TEXT DEFAULT NULL,
    reflection TEXT DEFAULT NULL,
    verse_highlights JSON DEFAULT NULL,
    focus_verse VARCHAR(100) DEFAULT NULL,
    mood VARCHAR(50) DEFAULT NULL,
    started_at TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    duration_minutes INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES morning_sessions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_session (user_id, session_id),
    INDEX idx_user_id (user_id),
    INDEX idx_session_id (session_id),
    INDEX idx_completed_at (completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User streaks tracking
CREATE TABLE IF NOT EXISTS morning_streaks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    current_streak INT UNSIGNED DEFAULT 0,
    longest_streak INT UNSIGNED DEFAULT 0,
    total_completions INT UNSIGNED DEFAULT 0,
    last_completed_date DATE DEFAULT NULL,
    streak_start_date DATE DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_streak (user_id),
    INDEX idx_user_id (user_id),
    INDEX idx_current_streak (current_streak)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scheduled reminders
CREATE TABLE IF NOT EXISTS morning_reminders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    reminder_time TIME DEFAULT '05:00:00',
    days_of_week JSON DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    method ENUM('in_app', 'email', 'both') DEFAULT 'in_app',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_reminder (user_id),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
