<?php
/**
 * Logout Handler
 * Destroys session and redirects to login page
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Store user name for goodbye message (optional)
$user_name = $_SESSION['name'] ?? 'User';
$user_role = $_SESSION['role'] ?? '';

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to login with optional message
header('Location: login.php?logged_out=1');
exit;
?>
