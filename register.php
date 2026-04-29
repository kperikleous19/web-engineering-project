<?php
session_start();
require_once "includes/db.php";

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$errors = [];
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $first_name = trim($_POST["first_name"]);
    $last_name = trim($_POST["last_name"]);
    $phone = trim($_POST["phone"]);
    $password = $_POST["password"];
    $confirm = $_POST["confirm"];

    // Validation
    if (empty($username) || empty($email) || empty($first_name) || empty($last_name) || empty($password)) {
        $errors[] = "Όλα τα υποχρεωτικά πεδία πρέπει να συμπληρωθούν.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Μη έγκυρη μορφή email.";
    }

    if (strlen($password) < 6) {
        $errors[] = "Ο κωδικός πρέπει να έχει τουλάχιστον 6 χαρακτήρες.";
    }

    if ($password !== $confirm) {
        $errors[] = "Οι κωδικοί δεν ταιριάζουν.";
    }

    // Check if email exists
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            $errors[] = "Το email χρησιμοποιείται ήδη.";
        }
    }

    // Check if username exists
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        if ($stmt->fetch()) {
            $errors[] = "Το username χρησιμοποιείται ήδη.";
        }
    }

    // Create user
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $role = 'candidate'; // Default role for new registrations

        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, first_name, last_name, phone, password_hash, role, role_id)
            SELECT :username, :email, :first_name, :last_name, :phone, :password, :role, id
            FROM roles WHERE role_name = 'candidate'
        ");

        $result = $stmt->execute([
            'username' => $username,
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'phone' => $phone,
            'password' => $hash,
            'role' => $role
        ]);

        if ($result) {
            $success = "Η εγγραφή σας ολοκληρώθηκε επιτυχώς! Μπορείτε τώρα να συνδεθείτε.";
            // Clear form
            $_POST = [];
        } else {
            $errors[] = "Σφάλμα κατά την εγγραφή. Παρακαλώ δοκιμάστε ξανά.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Εγγραφή - ΤΕΠΑΚ Σύστημα Διαχείρισης Ειδικών Επιστημόνων</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #ece4da;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .register-container {
            width: 100%;
            max-width: 550px;
            margin: 40px 20px;
            animation: fadeInUp 0.6s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .register-card {
            background: white;
            border-radius: 28px;
            box-shadow: 0 12px 28px rgba(90, 70, 60, 0.08);
            overflow: hidden;
            border: 1px solid #c9b5a5;
        }

        .register-header {
            background: #fffaf5;
            color: #3d2510;
            padding: 35px 30px;
            text-align: center;
            border-bottom: 1px solid #c9b5a5;
        }

        .register-header i {
            font-size: 55px;
            margin-bottom: 15px;
            color: #9c806e;
        }

        .register-header h1 {
            font-size: 24px;
            margin-bottom: 5px;
            font-weight: 600;
            color: #6c4f3a;
        }

        .register-header p {
            font-size: 13px;
            color: #6e4e3a;
            margin: 0;
        }

        .register-body {
            padding: 35px 30px;
            background: #fffcf9;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #5e5047;
            font-size: 13px;
            letter-spacing: 0.3px;
        }

        .form-group label i {
            margin-right: 8px;
            color: #9c806e;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1.5px solid #e6dbd2;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
            background: white;
        }

        .form-group input:focus {
            outline: none;
            border-color: #b8a18f;
            box-shadow: 0 0 0 3px rgba(184, 161, 143, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .btn-register {
            width: 100%;
            padding: 14px;
            background: #e4d0bf;
            border: 1px solid #c9b5a5;
            color: #4d4038;
            border-radius: 50px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
            font-family: 'Inter', sans-serif;
        }

        .btn-register:hover {
            background: #e0cfc0;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(90, 70, 60, 0.12);
        }

        .btn-register:active {
            transform: translateY(0);
        }

        .login-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #ece2da;
        }

        .login-link a {
            color: #9c806e;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }

        .login-link a:hover {
            color: #6c4f3a;
            text-decoration: underline;
        }

        .alert-error {
            background: #faf3ef;
            border-left: 4px solid #dc2626;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #b91c1c;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error ul {
            margin: 0;
            padding-left: 20px;
        }

        .alert-success {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            color: #2e7d32;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .back-home {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #9c806e;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .back-home:hover {
            color: #6c4f3a;
            text-decoration: none;
        }

        .required-field::after {
            content: " *";
            color: #dc3545;
        }

        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .register-header { padding: 30px 20px; }
            .register-body { padding: 30px 20px; }
            .register-header i { font-size: 45px; }
            .register-header h1 { font-size: 20px; }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <i class="fas fa-user-plus"></i>
                <h1>Δημιουργία Λογαριασμού</h1>
                <p>Εγγραφείτε στο Σύστημα Διαχείρισης Ειδικών Επιστημόνων ΤΕΠΑΚ</p>
            </div>

            <div class="register-body">
                <?php if ($success): ?>
                    <div class="alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?= $success ?>
                    </div>
                    <div style="text-align: center; margin-top: 15px;">
                        <a href="login.php" class="btn-register" style="display: inline-block; width: auto; padding: 10px 30px; text-decoration: none;">
                            <i class="fas fa-sign-in-alt"></i> Μετάβαση στη Σύνδεση
                        </a>
                    </div>
                <?php else: ?>
                    <?php if (!empty($errors)): ?>
                        <div class="alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-user"></i> Όνομα <span class="required-field"></span></label>
                                <input type="text" name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required placeholder="π.χ. Ιωάννης">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-user-tie"></i> Επίθετο <span class="required-field"></span></label>
                                <input type="text" name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required placeholder="π.χ. Παπαδόπουλος">
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-user-circle"></i> Username <span class="required-field"></span></label>
                            <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required placeholder="π.χ. ipapadopoulos">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Διεύθυνση Email <span class="required-field"></span></label>
                            <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required placeholder="your@email.com">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Τηλέφωνο</label>
                            <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="π.χ. 99 1234567">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-lock"></i> Κωδικός <span class="required-field"></span></label>
                                <input type="password" name="password" required placeholder="Ελάχιστο 6 χαρακτήρες">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-lock"></i> Επιβεβαίωση Κωδικού <span class="required-field"></span></label>
                                <input type="password" name="confirm" required placeholder="Επιβεβαίωση">
                            </div>
                        </div>

                        <button type="submit" class="btn-register">
                            <i class="fas fa-user-plus"></i> Δημιουργία Λογαριασμού
                        </button>
                    </form>

                    <div class="login-link">
                        <i class="fas fa-sign-in-alt"></i> Έχετε ήδη λογαριασμό;
                        <a href="login.php">Σύνδεση εδώ</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <a href="index.php" class="back-home">
            <i class="fas fa-arrow-left"></i> Επιστροφή στην Αρχική
        </a>
    </div>
</body>
</html>
