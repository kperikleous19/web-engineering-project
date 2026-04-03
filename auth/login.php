<?php
session_start();
require_once "../includes/db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = $_POST["email"];
    $password = $_POST["password"];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);

    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {

        $_SESSION["user_id"] = $user["id"];
        $_SESSION["username"] = $user["username"];
        $_SESSION["role"] = $user["role"];

        switch ($user["role"]) {
            case "admin":
                header("Location: ../admin/dashboard.php");
                break;
            case "candidate":
            case "evaluator":
                header("Location: ../recruitment/dashboard.php");
                break;
            case "hr":
                header("Location: ../enrollment/dashboard.php");
                break;
            default:
                header("Location: ../modules/dashboard.php");
        }
        exit;

    } else {
        $error = "Λανθασμένα στοιχεία σύνδεσης.";
    }
}

$registered = isset($_GET['registered']) && $_GET['registered'] === '1';
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Σύνδεση</title>
</head>
<body>

<h2>Σύνδεση</h2>

<?php if ($registered): ?>
    <p style="color:green;">Εγγραφή επιτυχής, συνδεθείτε τώρα.</p>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <p style="color:red;"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="POST">
    <label>Email:</label><br>
    <input type="email" name="email" required><br><br>

    <label>Κωδικός:</label><br>
    <input type="password" name="password" required><br><br>

    <button type="submit">Σύνδεση</button>
</form>

<p>Δεν έχετε λογαριασμό; <a href="register.php">Εγγραφή</a></p>

</body>
</html>

