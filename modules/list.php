
<?php
session_start();
require_once "../includes/db.php";


if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

//  (search)
$keyword = $_GET["keyword"] ?? "";

// Οι application columns στο schema είναι course/department/status
// γι' αυτό ο / η keyword search γίνεται σε course και department.
$sql = "SELECT * FROM applications WHERE course LIKE :kw1 OR department LIKE :kw2";
$stmt = $pdo->prepare($sql);

$kw = "%" . $keyword . "%";
$stmt->execute([
    'kw1' => $kw,
    'kw2' => $kw
]);

$applications = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Λίστα Αιτήσεων</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<header>
    <h1>Λίστα Αιτήσεων</h1>
    <div class="user-info"><?= htmlspecialchars($_SESSION["username"]) ?></div>
</header>

<nav>
    <a href="dashboard.php">Dashboard</a>
    <a href="../auth/logout.php">Αποσύνδεση</a>
</nav>

<main>
    <form method="GET" class="search-bar">
        <input
            type="text"
            name="keyword"
            placeholder="Αναζήτηση με μάθημα ή τμήμα..."
            value="<?= htmlspecialchars($keyword) ?>"
        >
        <button type="submit">Αναζήτηση</button>
    </form>

    <?php if (empty($applications)): ?>
        <p style="margin-top:20px; color:#555;">Δεν βρέθηκαν αποτελέσματα.</p>
    <?php else: ?>
    <div class="table-wrapper">
        <table>
            <tr>
                <th>Μάθημα</th>
                <th>Τμήμα</th>
                <th>Κατάσταση</th>
            </tr>
            <?php foreach ($applications as $app): ?>
            <tr>
                <td><?= htmlspecialchars($app["course"]) ?></td>
                <td><?= htmlspecialchars($app["department"]) ?></td>
                <td>
                    <span class="badge badge-<?= htmlspecialchars($app["status"]) ?>">
                        <?= htmlspecialchars($app["status"]) ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>
</main>

<footer>
    <p>Σύστημα Διαχείρισης Ειδικών Επιστημόνων &mdash; ΤΕΠΑΚ</p>
</footer>

</body>
</html>
