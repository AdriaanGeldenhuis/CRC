<?php
/**
 * CRC Logout Handler
 */

require_once __DIR__ . '/../core/bootstrap.php';

// Logout user
Auth::logout();

// Redirect to login
Session::flash('success', 'You have been logged out successfully');
Response::redirect('/auth/');
