
<?php
session_start();
require_once "../includes/db.php";


if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

//  (search)
$keyword = $_GET["keyword"] ?? "";

// QUERY με prepared statement
$sql = "SELECT * FROM applications WHERE title LIKE :kw";
$stmt = $pdo->prepare($sql);

$stmt->execute([
    'kw' => "%" . $keyword . "%"
]);

$applications = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
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
        placeholder="Search by title..." 
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
        <th>Title</th>
        <th>Description</th>
        <th>Status</th>
    </tr>

    <?php foreach ($applications as $app): ?>
    <tr>
        <td><?= htmlspecialchars($app["title"]) ?></td>
        <td><?= htmlspecialchars($app["description"]) ?></td>
        <td><?= htmlspecialchars($app["status"]) ?></td>
    </tr>
    <?php endforeach; ?>

</table>

<?php endif; ?>

</body>
</html>
