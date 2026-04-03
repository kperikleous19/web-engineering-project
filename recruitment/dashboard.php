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
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <h1>Recruitment Module</h1>
    <nav>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="../auth/logout.php">Αποσύνδεση</a></li>
        </ul>
    </nav>
</header>

<p>Καλώς ήρθατε, <strong><?= htmlspecialchars($_SESSION['username']) ?></strong> (<?= htmlspecialchars($_SESSION['role']) ?>)</p>

<main>
    <section class="dashboard">
        <h2>Dashboard</h2>

        <div class="cards">

            <div class="card">
                <h3>My Profile</h3>
                <p>Προβολή και επεξεργασία στοιχείων</p>
                <button>Άνοιγμα</button>
            </div>

            <div class="card">
                <h3>My Applications</h3>
                <p>Δημιουργία και επεξεργασία αιτήσεων</p>
                <button>Άνοιγμα</button>
            </div>

            <div class="card">
                <h3>Application Status</h3>
                <p>Παρακολούθηση κατάστασης αιτήσεων</p>
                <button>Άνοιγμα</button>
            </div>

        </div>
    </section>
</main>

<footer>
    <p>Recruitment Module</p>
</footer>

</body>
</html>