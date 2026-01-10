<?php
/**
 * CRC Logger
 * Simple file-based logging system
 */

// Prevent direct access
if (!defined('CRC_LOADED')) {
    die('Direct access not permitted');
}

class Logger {
    private static string $logPath = __DIR__ . '/../logs/';

    /**
     * Log an error message
     */
    public static function error(string $message, array $context = []): void {
        self::log('ERROR', $message, $context);
    }

    /**
     * Log a warning message
     */
    public static function warning(string $message, array $context = []): void {
        self::log('WARNING', $message, $context);
    }

    /**
     * Log an info message
     */
    public static function info(string $message, array $context = []): void {
        self::log('INFO', $message, $context);
    }

    /**
     * Log a debug message (only in development)
     */
    public static function debug(string $message, array $context = []): void {
        if (defined('CRC_DEBUG') && CRC_DEBUG) {
            self::log('DEBUG', $message, $context);
        }
    }

    /**
     * Log security events
     */
    public static function security(string $message, array $context = []): void {
        self::log('SECURITY', $message, $context, 'security.log');
    }

    /**
     * Log audit events (user actions)
     */
    public static function audit(int $userId, string $action, array $context = []): void {
        $context['user_id'] = $userId;
        $context['ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $context['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        self::log('AUDIT', $action, $context, 'audit.log');
    }

    /**
     * Core logging function
     */
    private static function log(string $level, string $message, array $context = [], string $filename = 'app.log'): void {
        // Ensure log directory exists
        if (!is_dir(self::$logPath)) {
            mkdir(self::$logPath, 0755, true);
        }

        $date = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | ' . json_encode($context) : '';

        $logLine = "[{$date}] [{$level}] {$message}{$contextStr}" . PHP_EOL;

        // Daily log rotation
        $logFile = self::$logPath . date('Y-m-d') . '-' . $filename;

        error_log($logLine, 3, $logFile);
    }

    /**
     * Log exception
     */
    public static function exception(Throwable $e, array $context = []): void {
        $context['file'] = $e->getFile();
        $context['line'] = $e->getLine();
        $context['trace'] = $e->getTraceAsString();

        self::error($e->getMessage(), $context);
    }

    /**
     * Clean old log files (older than 30 days)
     */
    public static function cleanOldLogs(int $days = 30): int {
        $count = 0;
        $cutoff = time() - ($days * 86400);

        foreach (glob(self::$logPath . '*.log') as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
                $count++;
            }
        }

        return $count;
    }
}
