<?php
session_start();


if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<header>
    <h1>Dashboard</h1>
    <div class="user-info">
        <?= htmlspecialchars($_SESSION["username"]) ?> &mdash; <?= htmlspecialchars($_SESSION["role"]) ?>
    </div>
</header>

<nav>
    <a href="list.php">Λίστα Αιτήσεων</a>
    <a href="../auth/logout.php">Αποσύνδεση</a>
</nav>

<main>
    <div class="cards">
        <div class="card">
            <h2>Λίστα Αιτήσεων</h2>
            <p>Προβολή και αναζήτηση αιτήσεων.</p>
            <a href="list.php" class="btn">Άνοιγμα</a>
        </div>
    </div>
</main>

<footer>
    <p>Σύστημα Διαχείρισης Ειδικών Επιστημόνων &mdash; ΤΕΠΑΚ</p>
</footer>

</body>
</html>
