<?php
/**
 * CRC Local Configuration Template
 *
 * Copy this file to core/config.local.php on the server and fill in the real
 * values. config.local.php is gitignored and must NEVER be committed.
 * Anything defined here takes precedence over .env values and the defaults
 * in core/config.php.
 */

// Prevent direct access
if (!defined('CRC_LOADED')) {
    die('Direct access not permitted');
}

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'your-database-name');
define('DB_USER', 'your-database-user');
define('DB_PASS', 'your-database-password');

// Optional overrides
// define('CRC_ENV', 'production');
// define('APP_URL', 'https://crc.org.za');
// define('AI_API_KEY', '');
// define('DIARY_OPENAI_API_KEY', '');
// define('SMARTBIBLE_OPENAI_API_KEY', '');
// define('VAPID_PUBLIC_KEY', '');
// define('VAPID_PRIVATE_KEY', '');
