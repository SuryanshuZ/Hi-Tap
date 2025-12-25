<?php
// Database Configuration - CHANGE THESE TO YOUR ACTUAL VALUES!
define('DB_HOST', 'localhost');
define('DB_USER', 'u552823944_Dzeuo');        // ← Replace with YOUR username
define('DB_PASS', 'dctXbb5@1407');      // ← Replace with YOUR password
define('DB_NAME', 'u552823944_8M9qP');       // ← Replace with YOUR database name

// Base URL
define('BASE_URL', 'https://sandybrown-flamingo-618618.hostingersite.com/');     // ← Replace with YOUR domain

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS

// Error Reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('UTC');

// Database Connection Function
function getDBConnection() {
    static $conn = null;
    
    if ($conn === null) {
        try {
            $conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    return $conn;
}

// Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication Helper Functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        header('Location: login.php');
        exit;
    }
}

function logout() {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Utility Functions
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function calculateDiscountedPrice($price, $discount_percentage) {
    return $price - ($price * $discount_percentage / 100);
}
?>
