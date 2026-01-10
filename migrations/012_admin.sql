-- CRC Admin Migration
-- Audit logs, settings, admin tools

-- Audit logs
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    congregation_id INT UNSIGNED DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) DEFAULT NULL,
    entity_id INT UNSIGNED DEFAULT NULL,
    old_values JSON DEFAULT NULL,
    new_values JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    extra_data JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (congregation_id) REFERENCES congregations(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_congregation_id (congregation_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System settings
CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    congregation_id INT UNSIGNED DEFAULT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT DEFAULT NULL,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    is_public TINYINT(1) DEFAULT 0,
    description TEXT DEFAULT NULL,
    updated_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (congregation_id) REFERENCES congregations(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_setting (congregation_id, setting_key),
    INDEX idx_congregation_id (congregation_id),
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin announcements
CREATE TABLE IF NOT EXISTS announcements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    congregation_id INT UNSIGNED DEFAULT NULL,
    scope ENUM('global', 'congregation') DEFAULT 'congregation',
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    type ENUM('info', 'warning', 'success', 'urgent') DEFAULT 'info',
    target_roles JSON DEFAULT NULL,
    is_dismissible TINYINT(1) DEFAULT 1,
    start_date DATETIME DEFAULT NULL,
    end_date DATETIME DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (congregation_id) REFERENCES congregations(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_congregation_id (congregation_id),
    INDEX idx_scope (scope),
    INDEX idx_is_active (is_active),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User announcement dismissals
CREATE TABLE IF NOT EXISTS announcement_dismissals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    dismissed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_dismissal (announcement_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Feature flags
CREATE TABLE IF NOT EXISTS feature_flags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    flag_key VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    is_enabled TINYINT(1) DEFAULT 0,
    rollout_percentage INT UNSIGNED DEFAULT 100,
    target_roles JSON DEFAULT NULL,
    target_congregations JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_flag_key (flag_key),
    INDEX idx_is_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System maintenance
CREATE TABLE IF NOT EXISTS maintenance_mode (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    is_active TINYINT(1) DEFAULT 0,
    message TEXT DEFAULT NULL,
    allowed_ips JSON DEFAULT NULL,
    allowed_roles JSON DEFAULT NULL,
    started_at TIMESTAMP NULL DEFAULT NULL,
    ends_at TIMESTAMP NULL DEFAULT NULL,
    started_by INT UNSIGNED DEFAULT NULL,
    FOREIGN KEY (started_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default maintenance mode record
INSERT INTO maintenance_mode (is_active, message) VALUES (0, 'The site is currently under maintenance. Please check back soon.');

-- Insert default settings
INSERT INTO settings (congregation_id, setting_key, setting_value, setting_type, is_public, description) VALUES
(NULL, 'app_name', 'CRC', 'string', 1, 'Application name'),
(NULL, 'default_timezone', 'Africa/Johannesburg', 'string', 1, 'Default timezone'),
(NULL, 'default_language', 'en', 'string', 1, 'Default language'),
(NULL, 'registration_enabled', 'true', 'boolean', 0, 'Allow new user registration'),
(NULL, 'email_verification_required', 'false', 'boolean', 0, 'Require email verification'),
(NULL, 'max_upload_size_mb', '10', 'integer', 0, 'Maximum file upload size in MB'),
(NULL, 'morning_watch_time', '05:00', 'string', 1, 'Default morning watch reminder time'),
(NULL, 'ai_explain_enabled', 'true', 'boolean', 0, 'Enable AI Bible explanations'),
(NULL, 'ai_daily_limit', '50', 'integer', 0, 'AI requests per user per day');

-- Insert default feature flags
INSERT INTO feature_flags (flag_key, name, description, is_enabled) VALUES
('ai_explain', 'AI Bible Explain', 'Enable AI-powered verse explanations', 1),
('sell_groups', 'Sell Groups', 'Enable marketplace functionality', 1),
('live_streaming', 'Live Streaming', 'Enable live stream integration', 0),
('push_notifications', 'Push Notifications', 'Enable web push notifications', 0),
('dark_mode', 'Dark Mode', 'Enable dark mode theme option', 1);
