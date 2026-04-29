<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/db.php";

function requireRole(array $roles) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit;
    }
    $userRole = strtolower($_SESSION['role']);
    $allowed  = array_map('strtolower', $roles);
    if (!in_array($userRole, $allowed)) {
        header("Location: ../auth/login.php");
        exit;
    }
}