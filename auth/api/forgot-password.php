<?php
/**
 * CRC Forgot Password API
 * POST /auth/api/forgot-password.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

// Require POST
Response::requirePost();

// Validate CSRF
CSRF::require();

// Rate limit (strict)
Security::requireRateLimit('forgot_password', 3, 300);

// Get input
$email = input('email');

// Validate
$validator = validate(['email' => $email])
    ->email('email');

if ($validator->fails()) {
    Response::validationError($validator->errors());
}

// Request password reset
$result = Auth::requestPasswordReset($email);

// Always return success to prevent enumeration
// The actual email sending would happen here in production
// For now, just log it

if (isset($result['_token'])) {
    // In development, log the reset link
    Logger::info('Password reset link generated', [
        'email' => $email,
        'reset_url' => APP_URL . '/auth/reset-password.php?token=' . $result['_token']
    ]);
}

Response::success([
    'message' => 'If an account exists with that email, a reset link has been sent.'
]);
