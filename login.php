<?php
session_start();
require_once "../includes/functions.php";

// If already logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    if (hasRole('admin')) {
        redirect("../admin/dashboard.php");
    } elseif (hasRole('hr')) {
        redirect("../enrollment/dashboard.php");
    } else {
        redirect("../recruitment/dashboard.php");
    }
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    $stmt = $pdo->prepare("
        SELECT u.*, r.role_name 
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.email = :email AND u.is_active = 1
    ");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["username"] = $user["username"];
        $_SESSION["first_name"] = $user['first_name'];
        $_SESSION["last_name"] = $user['last_name'];
        $_SESSION["full_name"] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION["role"] = strtolower($user['role_name'] ?? 'candidate');;
        
        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);
        
        if ($_SESSION['role'] == 'admin') {
            redirect("../admin/dashboard.php");
        } elseif ($_SESSION['role'] == 'hr') {
            redirect("../enrollment/dashboard.php");
        } else {
            redirect("../recruitment/dashboard.php");
        }
        exit;
    } else {
        $error = "Λανθασμένο email ή κωδικός πρόσβασης.";
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Σύνδεση - ΤΕΠΑΚ Σύστημα Διαχείρισης Ειδικών Επιστημόνων</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f0eb;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            width: 100%;
            max-width: 450px;
            margin: 20px;
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

        .login-card {
            background: white;
            border-radius: 28px;
            box-shadow: 0 12px 28px rgba(90, 70, 60, 0.08);
            overflow: hidden;
            border: 1px solid #e9dfd7;
        }

        .login-header {
            background: #fffaf5;
            color: #5a4a40;
            padding: 40px 30px;
            text-align: center;
            border-bottom: 1px solid #e9dfd7;
        }

        .login-header i {
            font-size: 55px;
            margin-bottom: 15px;
            color: #9c806e;
        }

        .login-header h1 {
            font-size: 24px;
            margin-bottom: 5px;
            font-weight: 600;
            color: #6c4f3a;
        }

        .login-header p {
            font-size: 13px;
            color: #8a7163;
            margin: 0;
        }

        .login-body {
            padding: 40px 30px;
            background: #fffcf9;
        }

        .form-group {
            margin-bottom: 25px;
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

        .input-group {
            position: relative;
        }

        .input-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1.5px solid #e6dbd2;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
            background: white;
        }

        .input-group input:focus {
            outline: none;
            border-color: #b8a18f;
            box-shadow: 0 0 0 3px rgba(184, 161, 143, 0.1);
        }

        .input-group .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #c4b5a8;
            transition: color 0.3s;
        }

        .input-group .toggle-password:hover {
            color: #9c806e;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: #e6d9d0;
            border: 1px solid #dacbc1;
            color: #4d4038;
            border-radius: 50px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
            font-family: 'Inter', sans-serif;
        }

        .btn-login:hover {
            background: #dccfc4;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(90, 70, 60, 0.12);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .register-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #ece2da;
        }

        .register-link a {
            color: #9c806e;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }

        .register-link a:hover {
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

        .demo-credentials {
            background: #faf6f2;
            border-radius: 16px;
            padding: 18px;
            margin-top: 25px;
            border: 1px solid #e9dfd7;
        }

        .demo-credentials strong {
            color: #6c4f3a;
            display: block;
            margin-bottom: 12px;
            font-size: 13px;
        }

        .demo-credentials .cred-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e1d6ce;
            font-size: 12px;
        }

        .demo-credentials .cred-row:last-child {
            border-bottom: none;
        }

        .demo-credentials .role-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 50px;
            font-size: 10px;
            font-weight: 600;
        }

        .role-admin { background: #dc3545; color: white; }
        .role-candidate { background: #28a745; color: white; }
        .role-hr { background: #e6d9d0; color: #5a4a40; }

        .demo-credentials .cred-email {
            color: #7f675b;
            font-family: monospace;
            font-size: 11px;
        }

        .demo-credentials .cred-pass {
            color: #9c806e;
            font-family: monospace;
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

        @media (max-width: 480px) {
            .login-header { padding: 30px 20px; }
            .login-body { padding: 30px 20px; }
            .login-header i { font-size: 45px; }
            .login-header h1 { font-size: 20px; }
            .demo-credentials .cred-row { flex-direction: column; align-items: flex-start; gap: 5px; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <i class="fas fa-university"></i>
                <h1>ΤΕΠΑΚ</h1>
                <p>Σύστημα Διαχείρισης Ειδικών Επιστημόνων</p>
            </div>
            
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Διεύθυνση Email</label>
                        <div class="input-group">
                            <input type="email" name="email" placeholder="your@email.com" required autocomplete="email">
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Κωδικός Πρόσβασης</label>
                        <div class="input-group">
                            <input type="password" name="password" id="password" placeholder="••••••••" required>
                            <span class="toggle-password" onclick="togglePassword()">
                                <i class="far fa-eye" id="toggleIcon"></i>
                            </span>
                        </div>
                    </div>

                    <button type="submit" class="btn-login">
                        <i class="fas fa-sign-in-alt"></i> Σύνδεση
                    </button>
                </form>

                <div class="demo-credentials">
                    <strong><i class="fas fa-key"></i> Δοκιμαστικοί Λογαριασμοί</strong>
                    <div class="cred-row">
                        <span><span class="role-badge role-admin">Admin</span></span>
                        <span class="cred-email">admin@tepak.edu.cy</span>
                        <span class="cred-pass">admin123</span>
                    </div>
                    <div class="cred-row">
                        <span><span class="role-badge role-candidate">Υποψήφιος</span></span>
                        <span class="cred-email">candidate1@example.com</span>
                        <span class="cred-pass">candidate123</span>
                    </div>
                    <div class="cred-row">
                        <span><span class="role-badge role-hr">HR</span></span>
                        <span class="cred-email">hr@tepak.edu.cy</span>
                        <span class="cred-pass">admin123</span>
                    </div>
                </div>

                <div class="register-link">
                    <i class="fas fa-user-plus"></i> Δεν έχετε λογαριασμό; 
                    <a href="register.php">Εγγραφή εδώ</a>
                </div>
            </div>
        </div>
        
        <a href="../index.php" class="back-home">
            <i class="fas fa-arrow-left"></i> Επιστροφή στην Αρχική
        </a>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'far fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'far fa-eye';
            }
        }
    </script>
</body>
</html>