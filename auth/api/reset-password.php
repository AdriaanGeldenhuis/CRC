<?php
/**
 * CRC Reset Password API
 * POST /auth/api/reset-password.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

// Require POST
Response::requirePost();

// Validate CSRF
CSRF::require();

// Rate limit
Security::requireRateLimit('reset_password', 5, 60);

// Get input
$token = input('token');
$password = input('password');
$passwordConfirm = input('password_confirm');

// Validate
$validator = validate([
    'token' => $token,
    'password' => $password,
    'password_confirm' => $passwordConfirm
])
    ->required('token', 'Reset token')
    ->password('password')
    ->matches('password_confirm', 'password', 'Password confirmation');

if ($validator->fails()) {
    Response::validationError($validator->errors());
}

// Reset password
$result = Auth::resetPassword($token, $password);

if ($result['ok']) {
    Response::success([
        'message' => $result['message'],
        'redirect' => '/auth/'
    ]);
} else {
    Response::error($result['error']);
}
