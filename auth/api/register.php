<?php
/**
 * CRC Register API
 * POST /auth/api/register.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

// Require POST
Response::requirePost();

// Validate CSRF
CSRF::require();

// Rate limit
Security::requireRateLimit('register', 5, 60);

// Get input
$name = input('name');
$email = input('email');
$phone = input('phone');
$password = input('password');
$passwordConfirm = input('password_confirm');

// Validate
$validator = validate([
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'password' => $password,
    'password_confirm' => $passwordConfirm
])
    ->required('name', 'Name')
    ->length('name', 2, 100, 'Name')
    ->email('email')
    ->phone('phone', false)
    ->password('password')
    ->matches('password_confirm', 'password', 'Password confirmation');

if ($validator->fails()) {
    Response::validationError($validator->errors());
}

// Register user
$result = Auth::register([
    'name' => $name,
    'email' => $email,
    'phone' => $phone ?: null,
    'password' => $password
]);

if ($result['ok']) {
    // Auto login after registration
    Auth::attempt($email, $password, false);

    Response::success([
        'user_id' => $result['user_id'],
        'redirect' => '/onboarding/'
    ], 'Account created successfully');
} else {
    Response::error($result['error']);
}
