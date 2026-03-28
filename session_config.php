<?php
/**
 * Session Configuration for EMS1
 */

// Set a unique session name to prevent collision
session_name('EMS1_SESSION');

// Use root path for session cookie to ensure it works across all pages and subdirectories
$cookie_path = '/';

// Restrict session cookie parameters
session_set_cookie_params([
    'lifetime' => 0,
    'path' => $cookie_path, 
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Implement 1-hour session timeout
$timeout_duration = 3600; // 3600 seconds = 1 hour

if (isset($_SESSION['LAST_ACTIVITY'])) {
    if (time() - $_SESSION['LAST_ACTIVITY'] > $timeout_duration) {
        // Session expired
        session_unset();
        session_destroy();
        session_start(); // Start new guest session
    }
}
$_SESSION['LAST_ACTIVITY'] = time();
