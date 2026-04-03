<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<header>
  <h1>Admin Module</h1>
  <div class="user-info"><?= htmlspecialchars($_SESSION['username']) ?> &mdash; admin</div>
</header>

<nav>
  <a href="dashboard.php">Dashboard</a>
  <a href="../auth/logout.php">Αποσύνδεση</a>
</nav>

<main>
  <section class="cards">

    <div class="card">
      <div class="card-icon">👥</div>
      <h2>Manage Users</h2>
      <p>Προβολή και διαχείριση όλων των χρηστών της εφαρμογής.</p>
      <button>Άνοιγμα</button>
    </div>

    <div class="card">
      <div class="card-icon">📋</div>
      <h2>Manage Recruitment</h2>
      <p>Διαχείριση αιτήσεων, προκηρύξεων και αξιολογητών.</p>
      <button>Άνοιγμα</button>
    </div>

    <div class="card">
      <div class="card-icon">⚙️</div>
      <h2>Configure System</h2>
      <p>Ρυθμίσεις συστήματος, theme και σύνδεση Moodle.</p>
      <button>Άνοιγμα</button>
    </div>

    <div class="card">
      <div class="card-icon">📊</div>
      <h2>Report</h2>
      <p>Στατιστικά και αναφορές αιτήσεων ανά μάθημα.</p>
      <button>Άνοιγμα</button>
    </div>

  </section>
</main>

<footer>
  <p>Σύστημα Διαχείρισης Ειδικών Επιστημόνων &mdash; ΤΕΠΑΚ</p>
</footer>

</body>
</html>