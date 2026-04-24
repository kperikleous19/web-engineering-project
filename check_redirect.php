<?php
session_start();
echo "Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "<br>";
echo "Session role: " . ($_SESSION['role'] ?? 'NOT SET') . "<br>";
echo "<br><a href='index.php'>Go to index</a>";
echo "<br><a href='auth/logout.php'>Logout</a>";
?>