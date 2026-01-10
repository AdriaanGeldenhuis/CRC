<?php
/**
 * CRC Application Entry Point
 * Redirects to appropriate section based on auth status
 */

require_once __DIR__ . '/core/bootstrap.php';

// Check if user is logged in
if (Auth::check()) {
    // Check if user has primary congregation
    $primaryCong = Auth::primaryCongregation();

    if (!$primaryCong) {
        // User needs to complete onboarding
        Response::redirect('/onboarding/');
    } else {
        // User is fully set up - go to home
        Response::redirect('/home/');
    }
} else {
    // Not logged in - go to auth
    Response::redirect('/auth/');
}
