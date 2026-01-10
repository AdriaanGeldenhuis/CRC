<?php
/**
 * CRC CSRF Protection
 * Token generation and validation
 */

// Prevent direct access
if (!defined('CRC_LOADED')) {
    die('Direct access not permitted');
}

class CSRF {
    private static string $tokenName = 'csrf_token';

    /**
     * Generate CSRF token
     */
    public static function generate(): string {
        if (!isset($_SESSION)) {
            session_start();
        }

        // Generate new token
        $token = bin2hex(random_bytes(32));
        $expiry = time() + CSRF_TOKEN_LIFETIME;

        // Store token with expiry
        $_SESSION['_csrf'] = [
            'token' => $token,
            'expiry' => $expiry
        ];

        return $token;
    }

    /**
     * Get current token or generate new one
     */
    public static function token(): string {
        if (!isset($_SESSION)) {
            session_start();
        }

        // Check if token exists and is valid
        if (isset($_SESSION['_csrf']) && $_SESSION['_csrf']['expiry'] > time()) {
            return $_SESSION['_csrf']['token'];
        }

        return self::generate();
    }

    /**
     * Validate CSRF token
     */
    public static function validate(?string $token = null): bool {
        if (!isset($_SESSION)) {
            session_start();
        }

        // Get token from parameter or request
        if ($token === null) {
            $token = $_POST[self::$tokenName] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        }

        if (!$token || !isset($_SESSION['_csrf'])) {
            return false;
        }

        // Check expiry
        if ($_SESSION['_csrf']['expiry'] < time()) {
            unset($_SESSION['_csrf']);
            return false;
        }

        // Constant time comparison
        return hash_equals($_SESSION['_csrf']['token'], $token);
    }

    /**
     * Validate and throw error if invalid
     */
    public static function require(): void {
        if (!self::validate()) {
            Logger::security('CSRF validation failed', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
            ]);
            Response::forbidden('Invalid security token. Please refresh and try again.');
        }
    }

    /**
     * Get hidden input field for forms
     */
    public static function field(): string {
        $token = self::token();
        return '<input type="hidden" name="' . self::$tokenName . '" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Get meta tag for JavaScript
     */
    public static function meta(): string {
        $token = self::token();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
    }

    /**
     * Refresh token (after sensitive actions)
     */
    public static function refresh(): string {
        return self::generate();
    }
}
