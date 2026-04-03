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
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<header>
  <h1>Enrollment Module</h1>
  <div class="user-info"><?= htmlspecialchars($_SESSION['username']) ?> &mdash; <?= htmlspecialchars($_SESSION['role']) ?></div>
</header>

<nav>
  <a href="dashboard.php">Dashboard</a>
  <a href="../auth/logout.php">Αποσύνδεση</a>
</nav>

<main>
  <div class="cards">

    <div class="card">
      <div class="card-icon">🔗</div>
      <h2>LMS Sync</h2>
      <p>Έλεγχος πρόσβασης στο Moodle και διαχείριση πρόσβασης σε μαθήματα.</p>
      <button>Άνοιγμα</button>
    </div>

    <div class="card">
      <div class="card-icon">🔄</div>
      <h2>Full Sync</h2>
      <p>Πλήρης συγχρονισμός χρηστών και ρυθμίσεις αυτόματου συγχρονισμού.</p>
      <button>Άνοιγμα</button>
    </div>

    <div class="card">
      <div class="card-icon">📊</div>
      <h2>Report</h2>
      <p>Στατιστικά πρόσβασης στο Moodle και αναφορές μαθημάτων χωρίς διδάσκοντα.</p>
      <button>Άνοιγμα</button>
    </div>

  </div>
</main>

<footer>
  <p>Σύστημα Διαχείρισης Ειδικών Επιστημόνων &mdash; ΤΕΠΑΚ</p>
</footer>

</body>
</html>