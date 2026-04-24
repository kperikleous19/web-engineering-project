<?php
// includes/functions.php - All helper functions in one place

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
$host = "127.0.0.1";
$dbname = "tepak_ee_db";
$username = "root";
$password = "oTem333!";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Helper function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Helper function to check user role
function hasRole($role) {
    return isset($_SESSION['role']) && strtolower($_SESSION['role']) === strtolower($role);
}

// Helper function to check if user has any of the given roles
function hasAnyRole($roles) {
    if (!isset($_SESSION['role'])) return false;
    return in_array($_SESSION['role'], $roles);
}

// Helper function to require login
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: ../auth/login.php");
        exit();
    }
}

// Helper function to require specific role
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header("Location: ../index.php");
        exit();
    }
}

// Helper function to require any of the given roles
function requireAnyRole($roles) {
    requireLogin();
    if (!hasAnyRole($roles)) {
        header("Location: ../index.php");
        exit();
    }
}

// Helper function to get user role name from database
function getUserRole($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT r.role_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    return $result ? $result['role_name'] : 'candidate';
}

// Helper function to get user full name
function getUserFullName($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    return $user ? $user['first_name'] . ' ' . $user['last_name'] : '';
}

// Helper function to redirect
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Helper function to sanitize input
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}
?>