
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
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications List</title>
</head>
<body>

<h1>Applications</h1>

<p>
    Welcome, <strong><?= htmlspecialchars($_SESSION["username"]) ?></strong>
</p>

<a href="dashboard.php">⬅ Back to Dashboard</a> |
<a href="../auth/logout.php">Logout</a>

<hr>


<form method="GET">
    <input 
        type="text" 
        name="keyword" 
        placeholder="Search by course or department..." 
        value="<?= htmlspecialchars($keyword) ?>"
    >
    <button type="submit">Search</button>
</form>

<br>


<?php if (empty($applications)): ?>
    <p>No results found.</p>
<?php else: ?>

<table border="1" cellpadding="10">
    <tr>
        <th>Course</th>
        <th>Department</th>
        <th>Status</th>
    </tr>

    <?php foreach ($applications as $app): ?>
    <tr>
        <td><?= htmlspecialchars($app["course"]) ?></td>
        <td><?= htmlspecialchars($app["department"]) ?></td>
        <td><?= htmlspecialchars($app["status"]) ?></td>
    </tr>
    <?php endforeach; ?>

</table>

<?php endif; ?>

</body>
</html>
