<?php
/**
 * CRC Application Bootstrap
 * Central entry point - loads all core components
 *
 * Usage: require_once __DIR__."/../core/bootstrap.php";
 */

// Prevent multiple loads
if (defined('CRC_LOADED')) {
    return;
}
define('CRC_LOADED', true);

// Start output buffering
ob_start();

// Start PHP session for flash messages and CSRF
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load core files in correct order
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/validators.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/upload.php';

// Set security headers
Security::setHeaders();

// Initialize session
Session::init();

// Global exception handler
set_exception_handler(function (Throwable $e) {
    Logger::exception($e);

    if (CRC_DEBUG) {
        Response::json([
            'ok' => false,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ], 500);
    } else {
        Response::serverError('An unexpected error occurred. Please try again.');
    }
});

// Global error handler
set_error_handler(function (int $severity, string $message, string $file, int $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

/**
 * Helper: Get current user
 */
function user(): ?array {
    return Auth::user();
}

/**
 * Helper: Get current user ID
 */
function user_id(): ?int {
    return Auth::id();
}

/**
 * Helper: Check if logged in
 */
function logged_in(): bool {
    return Auth::check();
}

/**
 * Helper: Escape HTML output
 */
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Helper: Asset URL with cache busting
 */
function asset(string $path): string {
    $fullPath = __DIR__ . '/../' . ltrim($path, '/');
    $version = file_exists($fullPath) ? filemtime($fullPath) : time();
    return '/' . ltrim($path, '/') . '?v=' . $version;
}

/**
 * Helper: Format date for display
 */
function format_date(string $date, string $format = 'd M Y'): string {
    return date($format, strtotime($date));
}

/**
 * Helper: Format datetime for display
 */
function format_datetime(string $datetime, string $format = 'd M Y H:i'): string {
    return date($format, strtotime($datetime));
}

/**
 * Helper: Time ago
 */
function time_ago(string $datetime): string {
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return format_date($datetime);
    }
}

/**
 * Helper: Truncate text
 */
function truncate(string $text, int $length = 100, string $suffix = '...'): string {
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . $suffix;
}

/**
 * Helper: Generate URL
 */
function url(string $path): string {
    return APP_URL . '/' . ltrim($path, '/');
}

/**
 * Helper: Check if current path matches
 */
function is_current_path(string $path): bool {
    $current = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    return $current === $path || str_starts_with($current, $path . '/');
}

/**
 * Helper: Get flash message and display
 */
function flash_message(): string {
    $success = Session::getFlash('success');
    $error = Session::getFlash('error');

    $html = '';

    if ($success) {
        $html .= '<div class="alert alert-success">' . e($success) . '</div>';
    }

    if ($error) {
        $html .= '<div class="alert alert-error">' . e($error) . '</div>';
    }

    return $html;
}
