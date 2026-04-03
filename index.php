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

<p>Παρακαλώ επιλέξτε το module που θέλετε να χρησιμοποιήσετε:</p>

<ul>
    <li><a href="auth/login.php?module=admin">Admin Module</a> — Διαχείριση χρηστών, προσλήψεων, ρυθμίσεων</li>
    <li><a href="auth/login.php?module=recruitment">Recruitment Module</a> — Υποβολή και παρακολούθηση αιτήσεων</li>
    <li><a href="auth/login.php?module=enrollment">Enrollment Module</a> — Συγχρονισμός με LMS Moodle</li>
</ul>

<p><a href="auth/register.php">Εγγραφή νέου χρήστη</a></p>

</body>
</html>
