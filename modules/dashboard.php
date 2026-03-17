<?php
session_start();


if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
</head>
<body>

<h1>Dashboard</h1>

<p>
    Welcome, 
    <strong><?= htmlspecialchars($_SESSION["username"]) ?></strong>
</p>

<p>
    Role: 
    <strong><?= htmlspecialchars($_SESSION["role"]) ?></strong>
</p>

<hr>

<h3>Menu</h3>

<ul>
    <li><a href="list.php">📋 View Applications</a></li>
    <li><a href="../auth/logout.php">🚪 Logout</a></li>
</ul>

</body>
</html>
