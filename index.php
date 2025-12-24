<?php
// Landing Page - Redirect to appropriate page based on user role
session_start();

// If not logged in, go to login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// If logged in, go to social feed (home.php)
header('Location: home.php');
exit;
