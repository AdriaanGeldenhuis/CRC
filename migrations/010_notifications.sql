-- CRC Notifications Migration
-- Notifications, notification settings, push subscriptions

-- Notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT DEFAULT NULL,
    payload JSON DEFAULT NULL,
    link VARCHAR(255) DEFAULT NULL,
    icon VARCHAR(100) DEFAULT NULL,
    priority ENUM('low', 'normal', 'high') DEFAULT 'normal',
    is_read TINYINT(1) DEFAULT 0,
    read_at TIMESTAMP NULL DEFAULT NULL,
    is_sent TINYINT(1) DEFAULT 0,
    sent_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notification types reference
CREATE TABLE IF NOT EXISTS notification_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type_key VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    default_enabled TINYINT(1) DEFAULT 1,
    channels JSON DEFAULT NULL,
    template_title VARCHAR(255) DEFAULT NULL,
    template_message TEXT DEFAULT NULL,
    icon VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User notification settings
CREATE TABLE IF NOT EXISTS notification_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type_key VARCHAR(50) NOT NULL,
    in_app_enabled TINYINT(1) DEFAULT 1,
    email_enabled TINYINT(1) DEFAULT 0,
    push_enabled TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_type (user_id, type_key),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Push subscriptions (for web push)
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    endpoint TEXT NOT NULL,
    p256dh_key VARCHAR(255) NOT NULL,
    auth_key VARCHAR(255) NOT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email notification queue
CREATE TABLE IF NOT EXISTS email_queue (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    to_email VARCHAR(255) NOT NULL,
    to_name VARCHAR(255) DEFAULT NULL,
    subject VARCHAR(255) NOT NULL,
    body_html TEXT NOT NULL,
    body_text TEXT DEFAULT NULL,
    priority TINYINT UNSIGNED DEFAULT 5,
    attempts INT UNSIGNED DEFAULT 0,
    max_attempts INT UNSIGNED DEFAULT 3,
    status ENUM('pending', 'sending', 'sent', 'failed') DEFAULT 'pending',
    error_message TEXT DEFAULT NULL,
    scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_scheduled_at (scheduled_at),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default notification types
INSERT INTO notification_types (type_key, name, description, default_enabled, icon) VALUES
('event_reminder', 'Event Reminders', 'Reminders for upcoming events', 1, 'calendar'),
('new_comment', 'New Comments', 'When someone comments on your post', 1, 'comment'),
('new_reaction', 'New Reactions', 'When someone reacts to your content', 0, 'heart'),
('approval_request', 'Approval Requests', 'When someone requests to join', 1, 'user-plus'),
('approval_status', 'Approval Status', 'Updates on your join requests', 1, 'check'),
('new_message', 'New Messages', 'New direct messages', 1, 'message'),
('morning_watch', 'Morning Watch', 'Daily morning watch reminders', 1, 'sunrise'),
('course_update', 'Course Updates', 'Updates to enrolled courses', 1, 'book'),
('homecell_meeting', 'Homecell Meetings', 'Homecell meeting reminders', 1, 'home'),
('prayer_answered', 'Answered Prayers', 'When a prayer is marked answered', 1, 'prayer'),
('admin_announcement', 'Announcements', 'Important announcements', 1, 'megaphone');
