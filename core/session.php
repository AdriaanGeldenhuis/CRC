<?php
/**
 * CRC Session Handler
 * Database-backed sessions with secure cookie handling
 */

// Prevent direct access
if (!defined('CRC_LOADED')) {
    die('Direct access not permitted');
}

class Session {
    private static bool $started = false;
    private static ?array $currentUser = null;
    private static ?string $sessionToken = null;

    /**
     * Initialize session
     */
    public static function init(): void {
        if (self::$started) {
            return;
        }

        // Check for session token in cookie
        self::$sessionToken = $_COOKIE[SESSION_NAME] ?? null;

        if (self::$sessionToken) {
            self::validateSession();
        }

        self::$started = true;
    }

    /**
     * Create new session for user
     */
    public static function create(int $userId, bool $rememberMe = false): string {
        // Generate secure token
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        // Calculate expiry
        $lifetime = $rememberMe ? SESSION_LIFETIME : 86400; // 30 days or 1 day
        $expiresAt = date('Y-m-d H:i:s', time() + $lifetime);

        // Store session in database
        Database::insert('sessions', [
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
            'last_activity' => date('Y-m-d H:i:s')
        ]);

        // Set cookie
        self::setCookie($token, $lifetime);

        // Load user
        self::$sessionToken = $token;
        self::loadUser($userId);

        Logger::audit($userId, 'session_created', ['remember_me' => $rememberMe]);

        return $token;
    }

    /**
     * Validate existing session
     */
    private static function validateSession(): bool {
        $tokenHash = hash('sha256', self::$sessionToken);

        $session = Database::fetchOne(
            "SELECT s.*, u.id as user_id, u.status as user_status
             FROM sessions s
             JOIN users u ON s.user_id = u.id
             WHERE s.token_hash = ? AND s.expires_at > NOW()",
            [$tokenHash]
        );

        if (!$session) {
            self::destroyCookie();
            return false;
        }

        // Check if user is still active
        if ($session['user_status'] !== 'active') {
            self::destroy();
            return false;
        }

        // Update last activity
        Database::update(
            'sessions',
            ['last_activity' => date('Y-m-d H:i:s')],
            'token_hash = ?',
            [$tokenHash]
        );

        // Load user data
        self::loadUser($session['user_id']);

        return true;
    }

    /**
     * Load user data into session
     */
    private static function loadUser(int $userId): void {
        self::$currentUser = Database::fetchOne(
            "SELECT id, email, name, phone, avatar, status, global_role,
                    email_verified_at, created_at, updated_at
             FROM users WHERE id = ?",
            [$userId]
        );
    }

    /**
     * Get current logged in user
     */
    public static function user(): ?array {
        return self::$currentUser;
    }

    /**
     * Get current user ID
     */
    public static function userId(): ?int {
        return self::$currentUser['id'] ?? null;
    }

    /**
     * Check if user is logged in
     */
    public static function isLoggedIn(): bool {
        return self::$currentUser !== null;
    }

    /**
     * Refresh user data from database
     */
    public static function refreshUser(): void {
        if (self::$currentUser) {
            self::loadUser(self::$currentUser['id']);
        }
    }

    /**
     * Destroy current session
     */
    public static function destroy(): void {
        if (self::$sessionToken) {
            $tokenHash = hash('sha256', self::$sessionToken);

            // Delete from database
            Database::delete('sessions', 'token_hash = ?', [$tokenHash]);

            if (self::$currentUser) {
                Logger::audit(self::$currentUser['id'], 'session_destroyed');
            }
        }

        self::destroyCookie();
        self::$currentUser = null;
        self::$sessionToken = null;
    }

    /**
     * Destroy all sessions for user (logout everywhere)
     */
    public static function destroyAll(int $userId): void {
        Database::delete('sessions', 'user_id = ?', [$userId]);
        Logger::audit($userId, 'all_sessions_destroyed');

        if (self::$currentUser && self::$currentUser['id'] === $userId) {
            self::destroyCookie();
            self::$currentUser = null;
            self::$sessionToken = null;
        }
    }

    /**
     * Get all active sessions for user
     */
    public static function getActiveSessions(int $userId): array {
        return Database::fetchAll(
            "SELECT id, ip_address, user_agent, created_at, last_activity
             FROM sessions
             WHERE user_id = ? AND expires_at > NOW()
             ORDER BY last_activity DESC",
            [$userId]
        );
    }

    /**
     * Set session cookie
     */
    private static function setCookie(string $token, int $lifetime): void {
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        setcookie(SESSION_NAME, $token, [
            'expires' => time() + $lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => SESSION_HTTPONLY,
            'samesite' => SESSION_SAMESITE
        ]);
    }

    /**
     * Destroy session cookie
     */
    private static function destroyCookie(): void {
        if (isset($_COOKIE[SESSION_NAME])) {
            setcookie(SESSION_NAME, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => '',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            unset($_COOKIE[SESSION_NAME]);
        }
    }

    /**
     * Clean expired sessions from database
     */
    public static function cleanExpired(): int {
        $result = Database::query("DELETE FROM sessions WHERE expires_at < NOW()");
        return $result->rowCount();
    }

    /**
     * Store flash message
     */
    public static function flash(string $key, mixed $value): void {
        if (!isset($_SESSION)) {
            session_start();
        }
        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * Get and remove flash message
     */
    public static function getFlash(string $key): mixed {
        if (!isset($_SESSION)) {
            session_start();
        }
        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }
}
