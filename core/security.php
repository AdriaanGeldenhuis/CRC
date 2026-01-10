<?php
/**
 * CRC Security
 * Rate limiting, sanitization, security headers
 */

// Prevent direct access
if (!defined('CRC_LOADED')) {
    die('Direct access not permitted');
}

class Security {
    /**
     * Set security headers
     */
    public static function setHeaders(): void {
        // Prevent clickjacking
        header('X-Frame-Options: SAMEORIGIN');

        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');

        // XSS protection
        header('X-XSS-Protection: 1; mode=block');

        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Content Security Policy (light version)
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; frame-src 'self' https://www.youtube.com https://youtube.com;");

        // Strict Transport Security (only on HTTPS)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        // Permissions Policy
        header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
    }

    /**
     * Sanitize string input
     */
    public static function sanitize(string $input): string {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize array recursively
     */
    public static function sanitizeArray(array $input): array {
        $sanitized = [];
        foreach ($input as $key => $value) {
            $key = self::sanitize($key);
            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeArray($value);
            } elseif (is_string($value)) {
                $sanitized[$key] = self::sanitize($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    /**
     * Clean HTML (allow safe tags)
     */
    public static function cleanHtml(string $input): string {
        $allowed = '<p><br><b><i><u><strong><em><ul><ol><li><a><blockquote>';
        $cleaned = strip_tags($input, $allowed);

        // Clean attributes except href on links
        $cleaned = preg_replace('/<a[^>]*href="([^"]*)"[^>]*>/', '<a href="$1">', $cleaned);

        return $cleaned;
    }

    /**
     * Check if login is blocked due to rate limiting
     */
    public static function isLoginBlocked(string $email): bool {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        $attempts = Database::fetchOne(
            "SELECT COUNT(*) as count, MAX(attempted_at) as last_attempt
             FROM login_attempts
             WHERE (email = ? OR ip_address = ?)
               AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
               AND success = 0",
            [$email, $ip, LOGIN_LOCKOUT_TIME]
        );

        return ($attempts['count'] ?? 0) >= MAX_LOGIN_ATTEMPTS;
    }

    /**
     * Record failed login attempt
     */
    public static function recordFailedLogin(string $email): void {
        Database::insert('login_attempts', [
            'email' => $email,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'success' => 0,
            'attempted_at' => date('Y-m-d H:i:s')
        ]);

        Logger::security('Failed login attempt', ['email' => $email]);
    }

    /**
     * Clear failed login attempts
     */
    public static function clearFailedLogins(string $email): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        Database::delete(
            'login_attempts',
            '(email = ? OR ip_address = ?) AND success = 0',
            [$email, $ip]
        );
    }

    /**
     * Record successful login
     */
    public static function recordSuccessfulLogin(string $email): void {
        Database::insert('login_attempts', [
            'email' => $email,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'success' => 1,
            'attempted_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * General rate limiting
     */
    public static function rateLimit(string $key, int $maxRequests = null, int $window = null): bool {
        $maxRequests = $maxRequests ?? RATE_LIMIT_REQUESTS;
        $window = $window ?? RATE_LIMIT_WINDOW;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        $cacheKey = "rate_limit:{$key}:{$ip}";

        // Use file-based rate limiting (could be replaced with Redis)
        $rateFile = sys_get_temp_dir() . '/crc_rate_' . md5($cacheKey);

        $data = ['count' => 0, 'start' => time()];

        if (file_exists($rateFile)) {
            $content = file_get_contents($rateFile);
            $data = json_decode($content, true) ?: $data;
        }

        // Reset if window expired
        if (time() - $data['start'] > $window) {
            $data = ['count' => 0, 'start' => time()];
        }

        // Increment count
        $data['count']++;

        // Save
        file_put_contents($rateFile, json_encode($data));

        // Check limit
        if ($data['count'] > $maxRequests) {
            Logger::security('Rate limit exceeded', ['key' => $key, 'ip' => $ip]);
            return false;
        }

        return true;
    }

    /**
     * Require rate limit or fail
     */
    public static function requireRateLimit(string $key, int $maxRequests = null, int $window = null): void {
        if (!self::rateLimit($key, $maxRequests, $window)) {
            Response::tooManyRequests('Too many requests. Please try again later.');
        }
    }

    /**
     * Generate secure random token
     */
    public static function generateToken(int $length = 32): string {
        return bin2hex(random_bytes($length));
    }

    /**
     * Verify request origin
     */
    public static function verifyOrigin(): bool {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';

        $allowedHost = parse_url(APP_URL, PHP_URL_HOST);

        if ($origin) {
            $originHost = parse_url($origin, PHP_URL_HOST);
            return $originHost === $allowedHost;
        }

        if ($referer) {
            $refererHost = parse_url($referer, PHP_URL_HOST);
            return $refererHost === $allowedHost;
        }

        // No origin/referer - might be direct API call
        return true;
    }

    /**
     * Clean old login attempts
     */
    public static function cleanOldAttempts(int $days = 7): int {
        $result = Database::query(
            "DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );
        return $result->rowCount();
    }

    /**
     * Hash for storage (not passwords)
     */
    public static function hash(string $value): string {
        return hash('sha256', $value);
    }

    /**
     * Constant time string comparison
     */
    public static function compare(string $known, string $user): bool {
        return hash_equals($known, $user);
    }
}
