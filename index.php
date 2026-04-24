<?php
// If user is already logged in, redirect to dashboard
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: modules/dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ΤΕΠΑΚ - Σύστημα Διαχείρισης Ειδικών Επιστημόνων</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="landing-hero">
    <h1>Σύστημα Διαχείρισης Ειδικών Επιστημόνων</h1>
    <p>Τεχνολογικό Πανεπιστήμιο Κύπρου</p>
</div>

<div class="landing-modules">
    <h2>Επιλέξτε Module</h2>
    <div class="cards">

        <div class="card">
            <h2>Admin Module</h2>
            <p>Διαχείριση χρηστών, προσλήψεων και ρυθμίσεων συστήματος.</p>
            <a href="auth/login.php?module=admin" class="btn">Είσοδος</a>
        </div>

        <div class="card">
            <h2>Recruitment Module</h2>
            <p>Υποβολή και παρακολούθηση αιτήσεων πρόσληψης.</p>
            <a href="auth/login.php?module=recruitment" class="btn">Είσοδος</a>
        </div>

        <div class="card">
            <h2>Enrollment Module</h2>
            <p>Συγχρονισμός ειδικών επιστημόνων με το LMS Moodle.</p>
            <a href="auth/login.php?module=enrollment" class="btn">Είσοδος</a>
        </div>

    </div>

    <div class="landing-register">
        Νέος χρήστης; <a href="auth/register.php">Εγγραφή εδώ</a>
    </div>
</div>

<footer>
    <p>Σύστημα Διαχείρισης Ειδικών Επιστημόνων &mdash; ΤΕΠΑΚ &copy; <?= date('Y') ?></p>
</footer>

</body>
</html>
