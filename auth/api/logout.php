<?php
/**
 * CRC Logout API
 * POST /auth/api/logout.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

// Require POST
Response::requirePost();

// Validate CSRF
CSRF::require();

// Logout
Auth::logout();

Response::success([
    'redirect' => '/auth/'
], 'Logged out successfully');
