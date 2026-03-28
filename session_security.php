<?php
// Set secure session cookie parameters BEFORE starting the session
// This prevents JavaScript access to cookies (HttpOnly) and enforces Strict mode
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => 0, // Session lasts until browser closes (unless timed out server-side)
    'path' => '/', // Use root path for maximum compatibility
    'secure' => isset($_SERVER['HTTPS']), // Only send over HTTPS if enabled
    'httponly' => true, // Prevent JavaScript access (XSS protection)
    'samesite' => 'Strict' // Prevent CSRF
]);

// Start the session
require_once __DIR__ . '/config_session.php';

// ==========================================
// SESSION TIMEOUT LOGIC
// ==========================================
$timeout_duration = 1800; // 30 minutes in seconds

if (isset($_SESSION['LAST_ACTIVITY'])) {
    // Calculate time since last activity
    if ((time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
        // Session has expired
        session_unset();     // Unset $_SESSION variable for the run-time 
        session_destroy();   // Destroy session data in storage
        
        // Redirect to login with a timeout message
        header("Location: login.php?timeout=1");
        exit;
    }
}

// Update last activity time stamp
$_SESSION['LAST_ACTIVITY'] = time();
?>
