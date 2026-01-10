-- CRC Profile Migration
-- Adds profile fields to users and church positions system

-- Add profile fields to users table
ALTER TABLE users
    ADD COLUMN date_of_birth DATE DEFAULT NULL AFTER phone,
    ADD COLUMN bio TEXT DEFAULT NULL AFTER date_of_birth,
    ADD COLUMN location VARCHAR(255) DEFAULT NULL AFTER bio,
    ADD COLUMN occupation VARCHAR(100) DEFAULT NULL AFTER location,
    ADD COLUMN cover_image VARCHAR(255) DEFAULT NULL AFTER avatar,
    ADD COLUMN show_birthday TINYINT(1) DEFAULT 1 AFTER cover_image,
    ADD COLUMN show_age TINYINT(1) DEFAULT 0 AFTER show_birthday,
    ADD COLUMN show_email TINYINT(1) DEFAULT 0 AFTER show_age,
    ADD COLUMN show_phone TINYINT(1) DEFAULT 0 AFTER show_email;

-- Church positions/roles (servants) in congregation
CREATE TABLE IF NOT EXISTS church_positions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    congregation_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (congregation_id) REFERENCES congregations(id) ON DELETE CASCADE,
    INDEX idx_congregation_id (congregation_id),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User church positions (many-to-many)
CREATE TABLE IF NOT EXISTS user_church_positions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    position_id INT UNSIGNED NOT NULL,
    congregation_id INT UNSIGNED NOT NULL,
    appointed_at DATE DEFAULT NULL,
    appointed_by INT UNSIGNED DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (position_id) REFERENCES church_positions(id) ON DELETE CASCADE,
    FOREIGN KEY (congregation_id) REFERENCES congregations(id) ON DELETE CASCADE,
    FOREIGN KEY (appointed_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_position (user_id, position_id, congregation_id),
    INDEX idx_user_id (user_id),
    INDEX idx_position_id (position_id),
    INDEX idx_congregation_id (congregation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default church positions
INSERT INTO church_positions (congregation_id, name, description, display_order)
SELECT id, 'Pastor', 'Lead pastor of the congregation', 1 FROM congregations;

INSERT INTO church_positions (congregation_id, name, description, display_order)
SELECT id, 'Elder', 'Church elder providing spiritual oversight', 2 FROM congregations;

INSERT INTO church_positions (congregation_id, name, description, display_order)
SELECT id, 'Deacon', 'Serves the practical needs of the congregation', 3 FROM congregations;

INSERT INTO church_positions (congregation_id, name, description, display_order)
SELECT id, 'Worship Leader', 'Leads worship and praise', 4 FROM congregations;

INSERT INTO church_positions (congregation_id, name, description, display_order)
SELECT id, 'Youth Leader', 'Leads and mentors the youth ministry', 5 FROM congregations;

INSERT INTO church_positions (congregation_id, name, description, display_order)
SELECT id, 'Sunday School Teacher', 'Teaches children and youth', 6 FROM congregations;

INSERT INTO church_positions (congregation_id, name, description, display_order)
SELECT id, 'Homecell Leader', 'Leads a homecell/small group', 7 FROM congregations;

INSERT INTO church_positions (congregation_id, name, description, display_order)
SELECT id, 'Usher', 'Welcomes and assists congregation members', 8 FROM congregations;

INSERT INTO church_positions (congregation_id, name, description, display_order)
SELECT id, 'Prayer Team', 'Dedicated intercessory prayer ministry', 9 FROM congregations;

INSERT INTO church_positions (congregation_id, name, description, display_order)
SELECT id, 'Media/Tech Team', 'Handles audio, video and technical needs', 10 FROM congregations;

-- Birthday tracking view (for birthday notifications)
CREATE OR REPLACE VIEW upcoming_birthdays AS
SELECT
    u.id,
    u.name,
    u.avatar,
    u.date_of_birth,
    u.show_birthday,
    TIMESTAMPDIFF(YEAR, u.date_of_birth, CURDATE()) as age,
    DAYOFYEAR(DATE_FORMAT(u.date_of_birth, CONCAT(YEAR(CURDATE()), '-%m-%d'))) as birthday_day_of_year,
    DAYOFYEAR(CURDATE()) as today_day_of_year,
    CASE
        WHEN DAYOFYEAR(DATE_FORMAT(u.date_of_birth, CONCAT(YEAR(CURDATE()), '-%m-%d'))) >= DAYOFYEAR(CURDATE())
        THEN DAYOFYEAR(DATE_FORMAT(u.date_of_birth, CONCAT(YEAR(CURDATE()), '-%m-%d'))) - DAYOFYEAR(CURDATE())
        ELSE 365 - DAYOFYEAR(CURDATE()) + DAYOFYEAR(DATE_FORMAT(u.date_of_birth, CONCAT(YEAR(CURDATE()), '-%m-%d')))
    END as days_until_birthday
FROM users u
WHERE u.date_of_birth IS NOT NULL
  AND u.show_birthday = 1
  AND u.status = 'active';
