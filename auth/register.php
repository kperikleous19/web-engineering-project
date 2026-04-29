<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: ../modules/dashboard.php");
    exit;
}

require_once "../includes/db.php";

$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirm = $_POST["confirm"];

    // VALIDATION
    if (empty($username) || empty($email) || empty($password) || empty($confirm)) {
        $errors[] = "Όλα τα πεδία είναι υποχρεωτικά.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Μη έγκυρη μορφή email.";
    }

    if (strlen($password) < 8) {
        $errors[] = "Ο κωδικός πρέπει να έχει τουλάχιστον 8 χαρακτήρες.";
    }

    if ($password !== $confirm) {
        $errors[] = "Οι κωδικοί δεν ταιριάζουν.";
    }

    // INSERT USER
    if (empty($errors)) {

        // CHECK IF EMAIL EXISTS (only runs when all basic validation passes)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);

        if ($stmt->fetch()) {
            $errors[] = "Το email χρησιμοποιείται ήδη.";
        }
    }

    if (empty($errors)) {

        $hash = password_hash($password, PASSWORD_DEFAULT);

        // safest behavior: assign explicit default role for new registrations
        // and avoid the NOT NULL constraint failure on users.role.
        $defaultRole = 'candidate';

        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password_hash, role, role_id)
            SELECT :username, :email, :password, :role, id
            FROM roles
            WHERE role_name = :role_lookup
        ");

        $stmt->execute([
            'username' => $username,
            'email' => $email,
            'password' => $hash,
            'role' => $defaultRole,
            'role_lookup' => $defaultRole
        ]);

        header("Location: login.php?registered=1");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Εγγραφή</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-box">
        <h2>Εγγραφή</h2>

        <?php if (!empty($errors)): ?>
            <div class="alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label>Όνομα χρήστη:</label>
            <input type="text" name="username" required>

            <label>Email:</label>
            <input type="email" name="email" required>

            <label>Κωδικός:</label>
            <input type="password" name="password" required>

            <label>Επιβεβαίωση κωδικού:</label>
            <input type="password" name="confirm" required>

            <button type="submit">Εγγραφή</button>
        </form>

        <div class="auth-link">
            Έχετε ήδη λογαριασμό; <a href="login.php">Σύνδεση</a>
        </div>
    </div>
</div>

</body>
</html>
