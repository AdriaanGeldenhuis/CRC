<?php
/**
 * CRC Authentication & Authorization
 * User management, roles, and guards
 */

// Prevent direct access
if (!defined('CRC_LOADED')) {
    die('Direct access not permitted');
}

class Auth {
    // Global roles
    const ROLE_USER = 'user';
    const ROLE_MODERATOR = 'moderator';
    const ROLE_ADMIN = 'admin';
    const ROLE_SUPER_ADMIN = 'super_admin';

    // Congregation roles
    const CONG_MEMBER = 'member';
    const CONG_LEADER = 'leader';
    const CONG_ADMIN = 'admin';
    const CONG_PASTOR = 'pastor';

    // User statuses
    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_BANNED = 'banned';

    /**
     * Get current user
     */
    public static function user(): ?array {
        return Session::user();
    }

    /**
     * Get current user ID
     */
    public static function id(): ?int {
        return Session::userId();
    }

    /**
     * Check if user is logged in
     */
    public static function check(): bool {
        return Session::isLoggedIn();
    }

    /**
     * Check if user is a guest
     */
    public static function guest(): bool {
        return !self::check();
    }

    /**
     * Register new user
     */
    public static function register(array $data): array {
        // Validate email uniqueness
        $existing = Database::fetchOne(
            "SELECT id FROM users WHERE email = ?",
            [$data['email']]
        );

        if ($existing) {
            return ['ok' => false, 'error' => 'Email already registered'];
        }

        // Hash password
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

        // Create user
        $userId = Database::insert('users', [
            'email' => strtolower(trim($data['email'])),
            'password_hash' => $passwordHash,
            'name' => trim($data['name']),
            'phone' => $data['phone'] ?? null,
            'status' => self::STATUS_ACTIVE,
            'global_role' => self::ROLE_USER,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        Logger::info('User registered', ['user_id' => $userId, 'email' => $data['email']]);

        return ['ok' => true, 'user_id' => $userId];
    }

    /**
     * Attempt login
     */
    public static function attempt(string $email, string $password, bool $rememberMe = false): array {
        $email = strtolower(trim($email));

        // Check rate limiting
        if (Security::isLoginBlocked($email)) {
            return ['ok' => false, 'error' => 'Too many login attempts. Please try again later.'];
        }

        // Find user
        $user = Database::fetchOne(
            "SELECT id, password_hash, status FROM users WHERE email = ?",
            [$email]
        );

        // Generic error to prevent enumeration
        if (!$user || !password_verify($password, $user['password_hash'])) {
            Security::recordFailedLogin($email);
            return ['ok' => false, 'error' => 'Invalid email or password'];
        }

        // Check status
        if ($user['status'] !== self::STATUS_ACTIVE) {
            return ['ok' => false, 'error' => 'Your account is not active. Please contact support.'];
        }

        // Clear failed attempts
        Security::clearFailedLogins($email);

        // Create session
        Session::create($user['id'], $rememberMe);

        // Update last login
        Database::update(
            'users',
            ['last_login_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$user['id']]
        );

        Logger::info('User logged in', ['user_id' => $user['id']]);

        return ['ok' => true, 'user_id' => $user['id']];
    }

    /**
     * Logout current user
     */
    public static function logout(): void {
        $userId = self::id();
        Session::destroy();
        if ($userId) {
            Logger::info('User logged out', ['user_id' => $userId]);
        }
    }

    /**
     * Request password reset
     */
    public static function requestPasswordReset(string $email): array {
        $email = strtolower(trim($email));

        $user = Database::fetchOne(
            "SELECT id FROM users WHERE email = ? AND status = ?",
            [$email, self::STATUS_ACTIVE]
        );

        // Always return success to prevent enumeration
        if (!$user) {
            return ['ok' => true, 'message' => 'If the email exists, a reset link will be sent.'];
        }

        // Generate token
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

        // Delete old reset requests
        Database::delete('password_resets', 'user_id = ?', [$user['id']]);

        // Create new reset request
        Database::insert('password_resets', [
            'user_id' => $user['id'],
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        Logger::info('Password reset requested', ['user_id' => $user['id']]);

        // Return token for email sending (in production, send email here)
        return [
            'ok' => true,
            'message' => 'If the email exists, a reset link will be sent.',
            '_token' => $token // Remove in production - only for testing
        ];
    }

    /**
     * Reset password with token
     */
    public static function resetPassword(string $token, string $newPassword): array {
        $tokenHash = hash('sha256', $token);

        $reset = Database::fetchOne(
            "SELECT user_id FROM password_resets WHERE token_hash = ? AND expires_at > NOW()",
            [$tokenHash]
        );

        if (!$reset) {
            return ['ok' => false, 'error' => 'Invalid or expired reset token'];
        }

        // Update password
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        Database::update(
            'users',
            ['password_hash' => $passwordHash, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$reset['user_id']]
        );

        // Delete reset token
        Database::delete('password_resets', 'user_id = ?', [$reset['user_id']]);

        // Invalidate all sessions
        Session::destroyAll($reset['user_id']);

        Logger::info('Password reset completed', ['user_id' => $reset['user_id']]);

        return ['ok' => true, 'message' => 'Password has been reset. Please login.'];
    }

    /**
     * Change password (authenticated user)
     */
    public static function changePassword(string $currentPassword, string $newPassword): array {
        $user = Database::fetchOne(
            "SELECT password_hash FROM users WHERE id = ?",
            [self::id()]
        );

        if (!password_verify($currentPassword, $user['password_hash'])) {
            return ['ok' => false, 'error' => 'Current password is incorrect'];
        }

        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        Database::update(
            'users',
            ['password_hash' => $passwordHash, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [self::id()]
        );

        Logger::audit(self::id(), 'password_changed');

        return ['ok' => true, 'message' => 'Password changed successfully'];
    }

    /**
     * Check if user has global role
     */
    public static function hasRole(string $role): bool {
        $user = self::user();
        if (!$user) return false;

        $roleHierarchy = [
            self::ROLE_USER => 1,
            self::ROLE_MODERATOR => 2,
            self::ROLE_ADMIN => 3,
            self::ROLE_SUPER_ADMIN => 4
        ];

        $userLevel = $roleHierarchy[$user['global_role']] ?? 0;
        $requiredLevel = $roleHierarchy[$role] ?? 0;

        return $userLevel >= $requiredLevel;
    }

    /**
     * Check if user is global admin
     */
    public static function isAdmin(): bool {
        return self::hasRole(self::ROLE_ADMIN);
    }

    /**
     * Check if user is super admin
     */
    public static function isSuperAdmin(): bool {
        return self::hasRole(self::ROLE_SUPER_ADMIN);
    }

    /**
     * Get user's primary congregation
     */
    public static function primaryCongregation(): ?array {
        if (!self::check()) return null;

        return Database::fetchOne(
            "SELECT c.*, uc.role as user_role, uc.status as membership_status
             FROM user_congregations uc
             JOIN congregations c ON uc.congregation_id = c.id
             WHERE uc.user_id = ? AND uc.is_primary = 1 AND uc.status = 'active'",
            [self::id()]
        );
    }

    /**
     * Get user's role in a congregation
     */
    public static function congregationRole(int $congregationId): ?string {
        if (!self::check()) return null;

        $membership = Database::fetchOne(
            "SELECT role FROM user_congregations
             WHERE user_id = ? AND congregation_id = ? AND status = 'active'",
            [self::id(), $congregationId]
        );

        return $membership['role'] ?? null;
    }

    /**
     * Check if user has role in congregation
     */
    public static function hasCongregationRole(int $congregationId, string $role): bool {
        $userRole = self::congregationRole($congregationId);
        if (!$userRole) return false;

        $roleHierarchy = [
            self::CONG_MEMBER => 1,
            self::CONG_LEADER => 2,
            self::CONG_ADMIN => 3,
            self::CONG_PASTOR => 4
        ];

        $userLevel = $roleHierarchy[$userRole] ?? 0;
        $requiredLevel = $roleHierarchy[$role] ?? 0;

        return $userLevel >= $requiredLevel;
    }

    /**
     * Check if user is congregation admin
     */
    public static function isCongregationAdmin(int $congregationId): bool {
        return self::hasCongregationRole($congregationId, self::CONG_ADMIN);
    }

    /**
     * Require authentication (guard)
     */
    public static function requireAuth(): void {
        if (!self::check()) {
            Response::unauthorized('Please login to continue');
        }
    }

    /**
     * Require specific global role (guard)
     */
    public static function requireRole(string $role): void {
        self::requireAuth();
        if (!self::hasRole($role)) {
            Response::forbidden('You do not have permission to access this resource');
        }
    }

    /**
     * Require congregation membership (guard)
     */
    public static function requireCongregationMember(int $congregationId): void {
        self::requireAuth();
        if (!self::congregationRole($congregationId)) {
            Response::forbidden('You are not a member of this congregation');
        }
    }

    /**
     * Require congregation role (guard)
     */
    public static function requireCongregationRole(int $congregationId, string $role): void {
        self::requireAuth();
        if (!self::hasCongregationRole($congregationId, $role)) {
            Response::forbidden('You do not have the required role in this congregation');
        }
    }

    /**
     * Require active primary congregation (guard)
     */
    public static function requirePrimaryCongregation(): array {
        self::requireAuth();
        $congregation = self::primaryCongregation();
        if (!$congregation) {
            Response::json(['ok' => false, 'error' => 'No primary congregation', 'redirect' => '/onboarding/'], 403);
        }
        return $congregation;
    }
}
