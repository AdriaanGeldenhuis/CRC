<?php
/**
 * CRC Application Configuration
 * All app settings in one place
 */

// Prevent direct access
if (!defined('CRC_LOADED')) {
    die('Direct access not permitted');
}

// Environment detection
define('CRC_ENV', getenv('CRC_ENV') ?: 'production');
define('CRC_DEBUG', CRC_ENV === 'development');

// Database Configuration
define('DB_HOST', 'dedi321.cpt1.host-h.net');
define('DB_NAME', 'crcapupvtk_db1');
define('DB_USER', 'crcapupvtk_1');
define('DB_PASS', '92x54KF8O959o6');
define('DB_CHARSET', 'utf8mb4');

// Application Settings
define('APP_NAME', 'CRC');
define('APP_URL', getenv('APP_URL') ?: 'https://crc.org.za');
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
define('AI_API_KEY', getenv('AI_API_KEY') ?: '');

// Diary AI Settings (OpenAI)
define('DIARY_OPENAI_API_KEY', getenv('DIARY_OPENAI_API_KEY') ?: '');
define('DIARY_AI_MODEL', 'gpt-4o-mini');
define('DIARY_AI_MAX_TOKENS', 1000);
define('DIARY_AI_TEMPERATURE', 0.7);
define('DIARY_AI_TIMEOUT', 30);
define('DIARY_AI_ENHANCE_PROMPT_EN', 'You are a helpful writing assistant. Enhance the following diary entry to make it more expressive, clear, and engaging while maintaining the original meaning and personal voice. Keep it in the same language as the original. Only return the enhanced text, nothing else.');
define('DIARY_AI_ENHANCE_PROMPT_AF', 'Jy is \'n hulpvaardige skryfassistent. Verbeter die volgende dagboekinskrywing om dit meer ekspressief, duidelik en boeiend te maak terwyl jy die oorspronklike betekenis en persoonlike stem behou. Hou dit in dieselfde taal as die oorspronklike. Gee net die verbeterde teks terug, niks anders nie.');

// Email Settings
define('MAIL_FROM', 'noreply@crc.org.za');
define('MAIL_FROM_NAME', 'CRC App');

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
