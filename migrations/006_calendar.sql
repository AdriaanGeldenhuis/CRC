-- CRC Calendar Migration
-- Calendar events, reminders, recurring rules

-- Calendar events (personal + linked)
CREATE TABLE IF NOT EXISTS calendar_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    congregation_id INT UNSIGNED DEFAULT NULL,
    event_id INT UNSIGNED DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME DEFAULT NULL,
    is_all_day TINYINT(1) DEFAULT 0,
    event_type ENUM('personal', 'congregation', 'global', 'linked') DEFAULT 'personal',
    color VARCHAR(20) DEFAULT 'blue',
    recurrence_rule VARCHAR(255) DEFAULT NULL,
    recurrence_end_date DATE DEFAULT NULL,
    parent_id INT UNSIGNED DEFAULT NULL,
    is_recurring_instance TINYINT(1) DEFAULT 0,
    status ENUM('active', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (congregation_id) REFERENCES congregations(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES calendar_events(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_congregation_id (congregation_id),
    INDEX idx_event_id (event_id),
    INDEX idx_start_datetime (start_datetime),
    INDEX idx_event_type (event_type),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event links (connect gospel_media events to calendar)
CREATE TABLE IF NOT EXISTS calendar_event_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    calendar_event_id INT UNSIGNED NOT NULL,
    source_type ENUM('event', 'morning_session', 'homecell_meeting', 'course_session') NOT NULL,
    source_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (calendar_event_id) REFERENCES calendar_events(id) ON DELETE CASCADE,
    UNIQUE KEY unique_link (calendar_event_id, source_type, source_id),
    INDEX idx_source (source_type, source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reminders
CREATE TABLE IF NOT EXISTS calendar_reminders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    calendar_event_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    minutes_before INT UNSIGNED DEFAULT 30,
    method ENUM('in_app', 'email', 'both') DEFAULT 'in_app',
    is_sent TINYINT(1) DEFAULT 0,
    sent_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (calendar_event_id) REFERENCES calendar_events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_calendar_event_id (calendar_event_id),
    INDEX idx_user_id (user_id),
    INDEX idx_is_sent (is_sent)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User calendar settings
CREATE TABLE IF NOT EXISTS calendar_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    default_view ENUM('month', 'week', 'day', 'agenda') DEFAULT 'month',
    week_starts_on TINYINT UNSIGNED DEFAULT 0,
    default_reminder_minutes INT UNSIGNED DEFAULT 30,
    show_congregation_events TINYINT(1) DEFAULT 1,
    show_global_events TINYINT(1) DEFAULT 1,
    timezone VARCHAR(50) DEFAULT 'Africa/Johannesburg',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_settings (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
