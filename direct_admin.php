<?php
session_start();

// Force set admin session
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'admin';
$_SESSION['first_name'] = 'System';
$_SESSION['last_name'] = 'Administrator';
$_SESSION['full_name'] = 'System Administrator';

// Go directly to admin dashboard
header("Location: admin/dashboard.php");
exit;
?>