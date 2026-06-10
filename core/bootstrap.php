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
require_once __DIR__ . '/push.php';
require_once __DIR__ . '/notifications.php';

// Set security headers
Security::setHeaders();

// Initialize session
Session::init();

// Global exception handler
set_exception_handler(function (Throwable $e) {
    Logger::exception($e);

    // Determine if this is an API request
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $isApi = str_contains($uri, '/api/');
    $acceptsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

    if ($isApi || $acceptsJson) {
        // API requests get JSON error responses
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
    } else {
        // HTML pages get a friendly error page
        ob_end_clean();
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>Error - CRC</title>';
        echo '<style>body{font-family:Inter,system-ui,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;text-align:center}';
        echo '.box{background:#1e293b;border-radius:16px;padding:40px;max-width:480px;box-shadow:0 4px 24px rgba(0,0,0,.3)}';
        echo 'h1{color:#f87171;font-size:1.5rem;margin:0 0 12px}p{color:#94a3b8;line-height:1.6;margin:0 0 24px}';
        echo 'a{display:inline-block;background:#3b82f6;color:#fff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:600;transition:background .2s}a:hover{background:#2563eb}';
        echo '.debug{margin-top:24px;text-align:left;background:#0f172a;padding:16px;border-radius:8px;font-size:0.8rem;color:#f87171;overflow-x:auto;white-space:pre-wrap;word-break:break-all}</style></head>';
        echo '<body><div class="box"><h1>Something went wrong</h1>';
        echo '<p>We encountered an unexpected error. Please try again or return to the home page.</p>';
        echo '<a href="/">Go Home</a>';
        if (CRC_DEBUG) {
            echo '<div class="debug"><strong>' . htmlspecialchars($e->getMessage()) . '</strong><br>';
            echo htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '<br><br>';
            echo htmlspecialchars($e->getTraceAsString()) . '</div>';
        }
        echo '</div></body></html>';
        exit;
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
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
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
function format_date(?string $date, string $format = 'd M Y'): string {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

/**
 * Helper: Format datetime for display
 */
function format_datetime(?string $datetime, string $format = 'd M Y H:i'): string {
    if (empty($datetime)) return '';
    return date($format, strtotime($datetime));
}

/**
 * Helper: Time ago
 */
function time_ago(?string $datetime): string {
    if (empty($datetime)) return '';
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
function truncate(?string $text, int $length = 100, string $suffix = '...'): string {
    $text = $text ?? '';
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
