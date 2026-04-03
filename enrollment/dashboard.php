<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (!in_array($_SESSION['role'], ['admin', 'hr'])) {
    header("Location: ../auth/login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8">
  <title>Enrollment Dashboard</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
  <h1>Enrollment Dashboard</h1>
  <p>Καλώς ήρθατε, <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></p>
</header>

<nav>
  <a href="dashboard.php">Dashboard</a>
  <a href="../auth/logout.php">Αποσύνδεση</a>
</nav>

<main>
  <section class="cards">

    <div class="card">
      <h2>LMS Sync</h2>
      <p>Έλεγχος πρόσβασης στο Moodle και διαχείριση πρόσβασης σε μαθήματα.</p>
      <button>Άνοιγμα</button>
    </div>

    <div class="card">
      <h2>Full Sync</h2>
      <p>Πλήρης συγχρονισμός χρηστών και ρυθμίσεις αυτόματου συγχρονισμού.</p>
      <button>Άνοιγμα</button>
    </div>

    <div class="card">
      <h2>Report</h2>
      <p>Στατιστικά πρόσβασης στο Moodle και αναφορές μαθημάτων χωρίς διδάσκοντα.</p>
      <button>Άνοιγμα</button>
    </div>

  </section>
</main>

<footer>
  <p>Web Engineering Project – Enrollment Module</p>
</footer>

</body>
</html>