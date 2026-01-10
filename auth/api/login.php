<?php
/**
 * CRC Login API
 * POST /auth/api/login.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

// Require POST
Response::requirePost();

// Validate CSRF
CSRF::require();

// Rate limit
Security::requireRateLimit('login', 10, 60);

// Get input
$email = input('email');
$password = input('password');
$rememberMe = (bool) input('remember_me', false);

// Validate
$validator = validate([
    'email' => $email,
    'password' => $password
])
    ->email('email')
    ->required('password');

if ($validator->fails()) {
    Response::validationError($validator->errors());
}

// Attempt login
$result = Auth::attempt($email, $password, $rememberMe);

if ($result['ok']) {
    // Determine redirect based on user state
    $redirect = '/';

    // Check for intended URL
    $intended = Session::getFlash('intended_url');
    if ($intended) {
        $redirect = $intended;
    }

    Response::success([
        'redirect' => $redirect
    ], 'Login successful');
} else {
    Response::error($result['error'], 401);
}
