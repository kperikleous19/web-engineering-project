<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (!in_array($_SESSION['role'], ['candidate', 'evaluator', 'hr'])) {
    header("Location: ../index.php");
    exit;
}

header("Location: ../enrollment/dashboard.php");
exit;
?>
