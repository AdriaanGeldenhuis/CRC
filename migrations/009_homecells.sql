-- CRC Homecells Migration
-- Homecells, members, meetings, attendance, notes, prayer requests

-- Homecells
CREATE TABLE IF NOT EXISTS homecells (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    congregation_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    cover_image VARCHAR(255) DEFAULT NULL,
    leader_user_id INT UNSIGNED NOT NULL,
    assistant_leader_id INT UNSIGNED DEFAULT NULL,
    meeting_day ENUM('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday') DEFAULT NULL,
    meeting_time TIME DEFAULT NULL,
    meeting_frequency ENUM('weekly', 'biweekly', 'monthly') DEFAULT 'weekly',
    location VARCHAR(255) DEFAULT NULL,
    location_address TEXT DEFAULT NULL,
    location_coords VARCHAR(50) DEFAULT NULL,
    max_members INT UNSIGNED DEFAULT NULL,
    is_accepting_members TINYINT(1) DEFAULT 1,
    settings JSON DEFAULT NULL,
    status ENUM('active', 'inactive', 'merged') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (congregation_id) REFERENCES congregations(id) ON DELETE CASCADE,
    FOREIGN KEY (leader_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assistant_leader_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_congregation_id (congregation_id),
    INDEX idx_leader_user_id (leader_user_id),
    INDEX idx_status (status),
    INDEX idx_meeting_day (meeting_day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Homecell members
CREATE TABLE IF NOT EXISTS homecell_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    homecell_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    role ENUM('member', 'host', 'assistant_leader', 'leader') DEFAULT 'member',
    status ENUM('active', 'inactive', 'left') DEFAULT 'active',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    left_at TIMESTAMP NULL DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    FOREIGN KEY (homecell_id) REFERENCES homecells(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_member (homecell_id, user_id),
    INDEX idx_homecell_id (homecell_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Homecell meetings
CREATE TABLE IF NOT EXISTS homecell_meetings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    homecell_id INT UNSIGNED NOT NULL,
    meeting_date DATE NOT NULL,
    meeting_time TIME DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    topic VARCHAR(255) DEFAULT NULL,
    scripture_ref VARCHAR(100) DEFAULT NULL,
    agenda TEXT DEFAULT NULL,
    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
    cancelled_reason TEXT DEFAULT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (homecell_id) REFERENCES homecells(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_homecell_id (homecell_id),
    INDEX idx_meeting_date (meeting_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Homecell attendance
CREATE TABLE IF NOT EXISTS homecell_attendance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    status ENUM('present', 'absent', 'excused', 'late') DEFAULT 'present',
    arrived_at TIME DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    recorded_by INT UNSIGNED NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_id) REFERENCES homecell_meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (meeting_id, user_id),
    INDEX idx_meeting_id (meeting_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Homecell meeting notes
CREATE TABLE IF NOT EXISTS homecell_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    note_type ENUM('summary', 'personal', 'action_item', 'testimonial') DEFAULT 'summary',
    content TEXT NOT NULL,
    is_shared TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_id) REFERENCES homecell_meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_meeting_id (meeting_id),
    INDEX idx_user_id (user_id),
    INDEX idx_note_type (note_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Homecell prayer requests (shared within homecell)
CREATE TABLE IF NOT EXISTS homecell_prayer_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    homecell_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    prayer_request_id INT UNSIGNED DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    is_anonymous TINYINT(1) DEFAULT 0,
    status ENUM('active', 'answered', 'archived') DEFAULT 'active',
    answered_at TIMESTAMP NULL DEFAULT NULL,
    testimonial TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (homecell_id) REFERENCES homecells(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (prayer_request_id) REFERENCES prayer_requests(id) ON DELETE SET NULL,
    INDEX idx_homecell_id (homecell_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Homecell visitor tracking
CREATE TABLE IF NOT EXISTS homecell_visitors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    homecell_id INT UNSIGNED NOT NULL,
    meeting_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    invited_by INT UNSIGNED DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    follow_up_status ENUM('pending', 'contacted', 'joined', 'not_interested') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (homecell_id) REFERENCES homecells(id) ON DELETE CASCADE,
    FOREIGN KEY (meeting_id) REFERENCES homecell_meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_homecell_id (homecell_id),
    INDEX idx_meeting_id (meeting_id),
    INDEX idx_follow_up_status (follow_up_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
