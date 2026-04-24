<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (!in_array($_SESSION['role'], ['candidate', 'evaluator', 'hr'])) {
    header("Location: ../auth/login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Recruitment Dashboard</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<header>
    <h1>Recruitment Module</h1>
    <div class="user-info"><?= htmlspecialchars($_SESSION['username']) ?> &mdash; <?= htmlspecialchars($_SESSION['role']) ?></div>
</header>

<nav>
    <a href="dashboard.php">Dashboard</a>
    <a href="../auth/logout.php">Αποσύνδεση</a>
</nav>

<main>
    <div class="cards">

        <div class="card">
            <div class="card-icon">👤</div>
            <h3>My Profile</h3>
            <p>Προβολή και επεξεργασία των προσωπικών σας στοιχείων.</p>
            <a href="../enrollment/my_profile.php" class="btn">Άνοιγμα</a>
        </div>

        <div class="card">
            <div class="card-icon">📝</div>
            <h3>My Applications</h3>
            <p>Δημιουργία, επεξεργασία και υποβολή αιτήσεων πρόσληψης.</p>
            <a href="../enrollment/my_applications.php" class="btn">Άνοιγμα</a>
        </div>

        <div class="card">
            <div class="card-icon">🔍</div>
            <h3>Κατάσταση Αιτήσεων</h3>
            <p>Παρακολούθηση της τρέχουσας κατάστασης των αιτήσεών σας.</p>
            <a href="../enrollment/application_status.php" class="btn">Άνοιγμα</a>
        </div>

    </div>
</main>

<footer>
    <p>Σύστημα Διαχείρισης Ειδικών Επιστημόνων &mdash; ΤΕΠΑΚ</p>
</footer>

</body>
</html>