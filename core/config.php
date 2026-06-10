<?php
/**
 * CRC Application Configuration
 * All app settings in one place
 */

// Prevent direct access
if (!defined('CRC_LOADED')) {
    die('Direct access not permitted');
}

// Load .env from the project root so getenv() works on shared hosting
$crcEnvFile = __DIR__ . '/../.env';
if (is_file($crcEnvFile) && is_readable($crcEnvFile)) {
    foreach (file($crcEnvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $crcEnvLine) {
        $crcEnvLine = trim($crcEnvLine);
        if ($crcEnvLine === '' || str_starts_with($crcEnvLine, '#') || !str_contains($crcEnvLine, '=')) {
            continue;
        }
        [$crcEnvKey, $crcEnvVal] = explode('=', $crcEnvLine, 2);
        $crcEnvKey = trim($crcEnvKey);
        $crcEnvVal = trim(trim($crcEnvVal), "\"'");
        if ($crcEnvKey !== '' && getenv($crcEnvKey) === false) {
            putenv($crcEnvKey . '=' . $crcEnvVal);
            $_ENV[$crcEnvKey] = $crcEnvVal;
        }
    }
}
unset($crcEnvFile, $crcEnvLine, $crcEnvKey, $crcEnvVal);

// Server-local overrides (gitignored) — copy config.local.example.php to config.local.php
$crcLocalConfig = __DIR__ . '/config.local.php';
if (is_file($crcLocalConfig)) {
    require $crcLocalConfig;
}
unset($crcLocalConfig);

// Environment detection
if (!defined('CRC_ENV')) define('CRC_ENV', getenv('CRC_ENV') ?: 'production');
define('CRC_DEBUG', CRC_ENV === 'development');

// Database Configuration — set in .env or core/config.local.php; never commit real credentials
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'crc');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Application Settings
define('APP_NAME', 'CRC');
if (!defined('APP_URL')) define('APP_URL', getenv('APP_URL') ?: 'https://crc.org.za');
define('APP_TIMEZONE', 'Africa/Johannesburg');

// Session Settings
define('SESSION_NAME', 'crc_session');
define('SESSION_LIFETIME', 86400 * 30); // 30 days
define('SESSION_SECURE', true);
define('SESSION_HTTPONLY', true);
define('SESSION_SAMESITE', 'Lax');

// Security Settings
define('CSRF_TOKEN_NAME', 'csrf_token');
define('CSRF_TOKEN_LIFETIME', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
define('RATE_LIMIT_REQUESTS', 100);
define('RATE_LIMIT_WINDOW', 60); // per minute

// Upload Settings
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('UPLOAD_PATH', __DIR__ . '/../uploads');
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_DOC_TYPES', ['application/pdf']);

// AI Settings (for Bible explain)
define('AI_CACHE_ENABLED', true);
define('AI_RATE_LIMIT_PER_DAY', 50);
if (!defined('AI_API_KEY')) define('AI_API_KEY', getenv('AI_API_KEY') ?: '');

// Diary AI Settings (OpenAI)
if (!defined('DIARY_OPENAI_API_KEY')) define('DIARY_OPENAI_API_KEY', getenv('DIARY_OPENAI_API_KEY') ?: '');
define('DIARY_AI_MODEL', 'gpt-4o-mini');
define('DIARY_AI_MAX_TOKENS', 1000);
define('DIARY_AI_TEMPERATURE', 0.7);
define('DIARY_AI_TIMEOUT', 30);
define('DIARY_AI_ENHANCE_PROMPT_EN', 'You are a helpful writing assistant. Enhance the following diary entry to make it more expressive, clear, and engaging while maintaining the original meaning and personal voice. Keep it in the same language as the original. Only return the enhanced text, nothing else.');

// AI SmartBible Settings
if (!defined('SMARTBIBLE_OPENAI_API_KEY')) define('SMARTBIBLE_OPENAI_API_KEY', getenv('SMARTBIBLE_OPENAI_API_KEY') ?: '');
if (!defined('SMARTBIBLE_MODEL')) define('SMARTBIBLE_MODEL', 'gpt-4o-mini');
if (!defined('SMARTBIBLE_TEMPERATURE')) define('SMARTBIBLE_TEMPERATURE', 0.3);

// Email Settings
define('MAIL_FROM', 'noreply@crc.org.za');
define('MAIL_FROM_NAME', 'CRC App');

// Web Push (VAPID) Settings — generate with: php _scripts/generate_vapid.php
if (!defined('VAPID_PUBLIC_KEY')) define('VAPID_PUBLIC_KEY', getenv('VAPID_PUBLIC_KEY') ?: '');
if (!defined('VAPID_PRIVATE_KEY')) define('VAPID_PRIVATE_KEY', getenv('VAPID_PRIVATE_KEY') ?: '');
if (!defined('VAPID_SUBJECT')) define('VAPID_SUBJECT', getenv('VAPID_SUBJECT') ?: 'mailto:' . MAIL_FROM);

// Pagination
define('DEFAULT_PAGE_SIZE', 20);
define('MAX_PAGE_SIZE', 100);

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

// Error reporting based on environment
if (CRC_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}
