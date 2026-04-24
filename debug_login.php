<?php
echo "<h1>Debug Login Test</h1>";
echo "<p>File is working! Path: " . __FILE__ . "</p>";

// Include database connection
require_once "includes/functions.php";

echo "<h2>Checking Database Connection</h2>";

// Test query
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
$result = $stmt->fetch();
echo "<p>Total users in database: " . $result['count'] . "</p>";

// Get all users
$stmt = $pdo->query("SELECT id, email, username, first_name, last_name FROM users");
$users = $stmt->fetchAll();

echo "<h2>Users in Database:</h2>";
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>ID</th><th>Email</th><th>Username</th><th>Name</th></tr>";
foreach ($users as $user) {
    echo "<tr>";
    echo "<td>" . $user['id'] . "</td>";
    echo "<td>" . $user['email'] . "</td>";
    echo "<td>" . $user['username'] . "</td>";
    echo "<td>" . $user['first_name'] . " " . ($user['last_name'] ?? '') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>Fix Passwords</h2>";
echo "<form method='POST'>";
echo "<button type='submit' name='fix' style='background:green; color:white; padding:10px 20px;'>Fix All Passwords (set to admin123)</button>";
echo "</form>";

if (isset($_POST['fix'])) {
    $new_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?");
    $stmt->execute([$new_hash]);
    echo "<p style='color:green;'>✓ All passwords have been reset to: <strong>admin123</strong></p>";
    echo "<p><a href='auth/login.php'>Go to Login →</a></p>";
}
?>