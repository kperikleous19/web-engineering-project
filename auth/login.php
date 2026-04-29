<?php
session_start();
require_once "../includes/db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = $_POST["email"];
    $password = $_POST["password"];

    $stmt = $pdo->prepare("
        SELECT u.*, COALESCE(r.role_name, u.role) AS effective_role
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.email = :email
    ");
    $stmt->execute(['email' => $email]);

    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {

        session_regenerate_id(true);

        $_SESSION["user_id"] = $user["id"];
        $_SESSION["username"] = $user["username"];
        $_SESSION["role"] = strtolower($user["effective_role"] ?? $user["role"] ?? 'candidate');

        switch ($_SESSION["role"]) {
            case "admin":
                header("Location: ../admin/dashboard.php");
                break;
            case "candidate":
            case "evaluator":
            case "hr":
                header("Location: ../enrollment/dashboard.php");
                break;
            case "ee":
                header("Location: ../dashboard.php");
                break;
            default:
                header("Location: ../index.php");
        }
        exit;

    } else {
        $error = "Λανθασμένα στοιχεία σύνδεσης.";
    }
}

$registered = isset($_GET['registered']) && $_GET['registered'] === '1';
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Σύνδεση</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-box">
        <h2>Σύνδεση</h2>

        <?php if ($registered): ?>
            <div class="alert-success">Εγγραφή επιτυχής! Συνδεθείτε τώρα.</div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label>Email:</label>
            <input type="email" name="email" required>

            <label>Κωδικός:</label>
            <input type="password" name="password" required>

            <button type="submit">Σύνδεση</button>
        </form>

        <div class="auth-link">
            Δεν έχετε λογαριασμό; <a href="register.php">Εγγραφή</a>
        </div>
    </div>
</div>

</body>
</html>
