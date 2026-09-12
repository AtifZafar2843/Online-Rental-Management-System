<?php
/**
 * Online Rental Management System (ORMS)
 * Logout Endpoint
 * 
 * Safely clears session state, destroys session cookies, and redirects.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/BaseUser.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear all session variables
$_SESSION = [];

// Invalidate session cookie in browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Start fresh temporary session just for the flash message
session_start();
set_flash('info', 'You have been successfully signed out.');

header("Location: " . base_url('auth/login.php'));
exit;
