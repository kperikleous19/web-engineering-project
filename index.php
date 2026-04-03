<?php
// If user is already logged in, redirect to dashboard
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: modules/dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TEPAK - Σύστημα Διαχείρισης Ειδικών Επιστημόνων</title>
</head>
<body>

<h1>Σύστημα Διαχείρισης Ειδικών Επιστημόνων</h1>
<h2>Τεχνολογικό Πανεπιστήμιο Κύπρου</h2>

<p>Παρακαλώ επιλέξτε ενέργεια:</p>

<ul>
    <li><a href="auth/login.php">Σύνδεση</a></li>
    <li><a href="auth/register.php">Εγγραφή</a></li>
</ul>

</body>
</html>
