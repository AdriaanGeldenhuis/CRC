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

// Log that a reset was requested (without exposing the token)
Logger::info('Password reset requested', [
    'email' => $email
]);

Response::success([
    'message' => 'If an account exists with that email, a reset link has been sent.'
]);
