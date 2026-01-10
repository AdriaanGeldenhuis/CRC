<?php
/**
 * CRC Database Migration Script
 * Runs all migrations in order
 *
 * Usage: php _scripts/migrate.php
 * Options:
 *   --fresh    Drop all tables and re-run migrations
 *   --status   Show migration status
 *   --rollback Roll back last migration (not implemented yet)
 */

// Can run from CLI only
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line.');
}

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║           CRC Database Migration Tool                        ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Define CRC_LOADED to allow including config
define('CRC_LOADED', true);

// Load configuration
require_once __DIR__ . '/../core/config.php';

// Parse command line arguments
$options = getopt('', ['fresh', 'status', 'rollback']);
$fresh = isset($options['fresh']);
$statusOnly = isset($options['status']);

// Connect to database
try {
    $dsn = sprintf('mysql:host=%s;charset=%s', DB_HOST, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    echo "✓ Connected to database server\n";

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");

    echo "✓ Using database: " . DB_NAME . "\n\n";

} catch (PDOException $e) {
    die("✗ Database connection failed: " . $e->getMessage() . "\n");
}

// Fresh migration - drop all tables
if ($fresh) {
    echo "⚠ FRESH MIGRATION - Dropping all tables...\n";
    echo "  This will DELETE ALL DATA. Continue? (yes/no): ";

    $confirm = trim(fgets(STDIN));
    if ($confirm !== 'yes') {
        die("Migration cancelled.\n");
    }

    // Disable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Get all tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        echo "  Dropped: {$table}\n";
    }

    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "✓ All tables dropped\n\n";
}

// Get migration files
$migrationPath = __DIR__ . '/../migrations/';
$files = glob($migrationPath . '*.sql');
sort($files);

if (empty($files)) {
    die("No migration files found in {$migrationPath}\n");
}

echo "Found " . count($files) . " migration files\n\n";

// Create migrations tracking table if not exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS migrations (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Get already executed migrations
$executed = $pdo->query("SELECT migration FROM migrations")->fetchAll(PDO::FETCH_COLUMN);

// Status only - show which migrations have run
if ($statusOnly) {
    echo "Migration Status:\n";
    echo str_repeat('-', 60) . "\n";

    foreach ($files as $file) {
        $name = basename($file);
        $status = in_array($name, $executed) ? '✓ Executed' : '○ Pending';
        echo sprintf("  %s  %s\n", $status, $name);
    }

    echo str_repeat('-', 60) . "\n";
    echo "Total: " . count($files) . " migrations, " . count($executed) . " executed\n";
    exit(0);
}

// Run pending migrations
$pending = 0;
$success = 0;
$failed = 0;

foreach ($files as $file) {
    $name = basename($file);

    // Skip if already executed
    if (in_array($name, $executed)) {
        continue;
    }

    $pending++;
    echo "Running: {$name}... ";

    try {
        // Read and execute SQL
        $sql = file_get_contents($file);

        // Split by semicolon but be careful with strings
        $pdo->exec($sql);

        // Record migration
        $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
        $stmt->execute([$name]);

        echo "✓ Done\n";
        $success++;

    } catch (PDOException $e) {
        echo "✗ Failed\n";
        echo "  Error: " . $e->getMessage() . "\n";
        $failed++;

        // Ask to continue or abort
        echo "  Continue with next migration? (yes/no): ";
        $continue = trim(fgets(STDIN));
        if ($continue !== 'yes') {
            break;
        }
    }
}

// Summary
echo "\n" . str_repeat('=', 60) . "\n";
echo "Migration Complete\n";
echo str_repeat('=', 60) . "\n";
echo "  Pending:   {$pending}\n";
echo "  Success:   {$success}\n";
echo "  Failed:    {$failed}\n";
echo str_repeat('=', 60) . "\n";

if ($failed > 0) {
    exit(1);
}

echo "\n✓ All migrations completed successfully!\n";
