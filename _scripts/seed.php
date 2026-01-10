<?php
/**
 * CRC Database Seeder
 * Seeds the database with sample data for development
 *
 * Usage: php _scripts/seed.php
 */

// Can run from CLI only
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line.');
}

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║           CRC Database Seeder                                ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

define('CRC_LOADED', true);
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/logger.php';
require_once __DIR__ . '/../core/db.php';

echo "⚠ This will add sample data to your database.\n";
echo "  Continue? (yes/no): ";

$confirm = trim(fgets(STDIN));
if ($confirm !== 'yes') {
    die("Seeding cancelled.\n");
}

try {
    echo "\n";

    // Create admin user
    echo "Creating admin user... ";
    $adminPassword = password_hash('Admin@123', PASSWORD_DEFAULT);
    Database::query("
        INSERT INTO users (email, password_hash, name, status, global_role, email_verified_at, created_at, updated_at)
        VALUES ('admin@crc.org.za', ?, 'System Admin', 'active', 'super_admin', NOW(), NOW(), NOW())
        ON DUPLICATE KEY UPDATE name = name
    ", [$adminPassword]);
    echo "✓\n";

    // Get admin user ID
    $adminId = Database::fetchColumn("SELECT id FROM users WHERE email = 'admin@crc.org.za'");

    // Create sample congregation
    echo "Creating sample congregation... ";
    Database::query("
        INSERT INTO congregations (name, slug, description, city, province, join_mode, status, created_by, created_at, updated_at)
        VALUES ('CRC Main', 'crc-main', 'Die hoof gemeente van CRC', 'Cape Town', 'Western Cape', 'open', 'active', ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE name = name
    ", [$adminId]);
    echo "✓\n";

    // Get congregation ID
    $congId = Database::fetchColumn("SELECT id FROM congregations WHERE slug = 'crc-main'");

    // Add admin to congregation
    echo "Adding admin to congregation... ";
    Database::query("
        INSERT INTO user_congregations (user_id, congregation_id, role, status, is_primary, approved_by, approved_at, joined_at, created_at, updated_at)
        VALUES (?, ?, 'pastor', 'active', 1, ?, NOW(), NOW(), NOW(), NOW())
        ON DUPLICATE KEY UPDATE role = 'pastor'
    ", [$adminId, $congId, $adminId]);
    echo "✓\n";

    // Create sample test user
    echo "Creating test user... ";
    $testPassword = password_hash('Test@123', PASSWORD_DEFAULT);
    Database::query("
        INSERT INTO users (email, password_hash, name, status, global_role, email_verified_at, created_at, updated_at)
        VALUES ('test@crc.org.za', ?, 'Test User', 'active', 'user', NOW(), NOW(), NOW())
        ON DUPLICATE KEY UPDATE name = name
    ", [$testPassword]);
    echo "✓\n";

    // Get test user ID
    $testUserId = Database::fetchColumn("SELECT id FROM users WHERE email = 'test@crc.org.za'");

    // Add test user to congregation
    echo "Adding test user to congregation... ";
    Database::query("
        INSERT INTO user_congregations (user_id, congregation_id, role, status, is_primary, approved_by, approved_at, joined_at, created_at, updated_at)
        VALUES (?, ?, 'member', 'active', 1, ?, NOW(), NOW(), NOW(), NOW())
        ON DUPLICATE KEY UPDATE role = 'member'
    ", [$testUserId, $congId, $adminId]);
    echo "✓\n";

    // Create a sample morning watch session
    echo "Creating sample morning watch session... ";
    Database::query("
        INSERT INTO morning_sessions (scope, session_date, title, theme, scripture_ref, scripture_text, version_code, devotional, prayer_points, created_by, published_at, created_at, updated_at)
        VALUES ('global', CURDATE(), 'Begin die Dag met God', 'Vertroue op God', 'Psalm 23:1-6', 'Die HERE is my herder; niks sal my ontbreek nie.', 'AFR53', 'Vandag se teks herinner ons dat God ons Herder is. Hy lei ons, verskaf aan ons behoeftes, en beskerm ons. Laat ons vandag in daardie vertroue leef.', '[\"Dank die Here vir Sy voorsiening\", \"Bid vir leiding in vandag se besluite\", \"Bid vir dié wat sukkel met vertroue\"]', ?, NOW(), NOW(), NOW())
        ON DUPLICATE KEY UPDATE title = title
    ", [$adminId]);
    echo "✓\n";

    // Create a sample homecell
    echo "Creating sample homecell... ";
    Database::query("
        INSERT INTO homecells (congregation_id, name, description, leader_user_id, meeting_day, meeting_time, meeting_frequency, location, status, created_at, updated_at)
        VALUES (?, 'Gardens Homecell', 'Homecell vir die Gardens area', ?, 'wednesday', '19:00:00', 'weekly', '123 Gardens Road, Cape Town', 'active', NOW(), NOW())
        ON DUPLICATE KEY UPDATE name = name
    ", [$congId, $adminId]);
    echo "✓\n";

    echo "\n" . str_repeat('=', 60) . "\n";
    echo "✓ Seeding Complete!\n";
    echo str_repeat('=', 60) . "\n";
    echo "\nTest Accounts:\n";
    echo "  Admin: admin@crc.org.za / Admin@123\n";
    echo "  User:  test@crc.org.za / Test@123\n";
    echo str_repeat('=', 60) . "\n";

} catch (PDOException $e) {
    echo "✗ Failed\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
