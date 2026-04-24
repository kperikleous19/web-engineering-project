<?php
session_start();

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// Database connection
$host = "127.0.0.1";
$dbname = "tepak_ee_db";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get all roles for dropdown
$roles = $pdo->query("SELECT * FROM roles ORDER BY role_name")->fetchAll();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone'] ?? '');
    $role_id = $_POST['role_id'];
    $temp_password = bin2hex(random_bytes(4)); // Generate temp password like "a3f8d2e1"
    $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);
    
    // Check if email exists
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        $error = "Το email χρησιμοποιείται ήδη.";
    }
    
    // Check if username exists
    $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $check->execute([$username]);
    if ($check->fetch()) {
        $error = "Το username χρησιμοποιείται ήδη.";
    }
    
    if (empty($error)) {
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password_hash, first_name, last_name, phone, role_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$username, $email, $password_hash, $first_name, $last_name, $phone, $role_id])) {
            $success = "Ο χρήστης δημιουργήθηκε επιτυχώς!<br>
                        <strong>Προσωρινός κωδικός:</strong> " . $temp_password . "<br>
                        <small>Παρακαλώ ενημερώστε τον χρήστη να αλλάξει τον κωδικό του κατά την πρώτη σύνδεση.</small>";
            
            // Clear form
            $_POST = [];
        } else {
            $error = "Σφάλμα κατά τη δημιουργία του χρήστη.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Προσθήκη Χρήστη | ΤΕΠΑΚ Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f4f1ec;
            color: #2c2c2c;
        }

        /* Top Navigation Bar */
        .top-bar {
            background: white;
            padding: 0 30px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #e2dcd5;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .logo-area h2 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2c5f8a;
            margin: 0;
        }
        .logo-area span { font-weight: 400; color: #8b6b4d; }
        .user-menu { display: flex; align-items: center; gap: 20px; }
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            background: #f4f1ec;
            border-radius: 40px;
        }
        .user-info i { font-size: 18px; color: #8b6b4d; }
        .user-info span { font-weight: 500; color: #2c2c2c; }
        .logout-btn {
            color: #8b6b4d;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .logout-btn:hover { background: #f4f1ec; color: #2c5f8a; }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: white;
            border-right: 1px solid #e2dcd5;
            height: calc(100vh - 70px);
            position: fixed;
            left: 0;
            top: 70px;
            overflow-y: auto;
        }
        .sidebar-nav { padding: 20px 0; }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 24px;
            color: #5a5a5a;
            text-decoration: none;
            transition: all 0.2s;
            margin: 4px 12px;
            border-radius: 12px;
        }
        .sidebar-nav a i { width: 22px; font-size: 18px; }
        .sidebar-nav a:hover { background: #f4f1ec; color: #2c5f8a; }
        .sidebar-nav a.active { background: #2c5f8a; color: white; }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 30px;
            min-height: calc(100vh - 70px);
        }

        /* Content Card */
        .content-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2dcd5;
            overflow: hidden;
            max-width: 700px;
            margin: 0 auto;
        }
        .card-header {
            padding: 25px 30px;
            border-bottom: 1px solid #e2dcd5;
            background: #faf9f7;
        }
        .card-header h3 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
            color: #2c5f8a;
        }
        .card-header p {
            margin: 8px 0 0;
            color: #8a8a8a;
            font-size: 14px;
        }
        .card-body { padding: 30px; }

        /* Form Styles */
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c2c2c;
            font-size: 14px;
        }
        .form-group label i {
            margin-right: 8px;
            color: #8b6b4d;
            width: 20px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e2dcd5;
            border-radius: 12px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
            background: white;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #2c5f8a;
            box-shadow: 0 0 0 3px rgba(44, 95, 138, 0.1);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        /* Buttons */
        .btn-submit {
            background: #2c5f8a;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
            font-size: 15px;
        }
        .btn-submit:hover {
            background: #1e4668;
            transform: translateY(-1px);
        }
        .btn-cancel {
            background: #f4f1ec;
            color: #5a5a5a;
            border: 1px solid #e2dcd5;
            padding: 12px 25px;
            border-radius: 12px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.2s;
        }
        .btn-cancel:hover {
            background: #e8e4df;
        }
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        .button-group .btn-cancel { flex: 1; }
        .button-group .btn-submit { flex: 2; }

        /* Alerts */
        .alert-success {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            color: #2e7d32;
        }
        .alert-error {
            background: #ffebee;
            border-left: 4px solid #f44336;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            color: #c62828;
        }
        .temp-password {
            background: #f4f1ec;
            padding: 10px 15px;
            border-radius: 10px;
            font-family: monospace;
            font-size: 16px;
            margin-top: 10px;
        }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .button-group { flex-direction: column; }
        }
    </style>
</head>
<body>

    <!-- Top Navigation -->
    <div class="top-bar">
        <div class="logo-area">
            <h2><i class="fas fa-university"></i> ΤΕΠΑΚ <span>| Admin Portal</span></h2>
        </div>
        <div class="user-menu">
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <span><?= htmlspecialchars($_SESSION['username'] ?? 'Administrator') ?></span>
            </div>
            <a href="../auth/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Αποσύνδεση
            </a>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-nav">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="users.php"><i class="fas fa-users"></i> Διαχείριση Χρηστών</a>
            <a href="recruitment.php"><i class="fas fa-bullhorn"></i> Διαχείριση Προκηρύξεων</a>
            <a href="system.php"><i class="fas fa-cog"></i> Ρυθμίσεις Συστήματος</a>
            <a href="reports.php"><i class="fas fa-chart-bar"></i> Αναφορές</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div style="margin-bottom: 25px;">
            <a href="users.php" style="color: #8b6b4d; text-decoration: none; margin-bottom: 15px; display: inline-block;">
                <i class="fas fa-arrow-left"></i> Επιστροφή στη Λίστα Χρηστών
            </a>
            <h1 style="font-size: 1.8rem; font-weight: 600; color: #2c2c2c;">Προσθήκη Νέου Χρήστη</h1>
            <p style="color: #8a8a8a; margin-top: 5px;">Δημιουργήστε έναν νέο λογαριασμό χρήστη στο σύστημα</p>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-user-plus"></i> Στοιχεία Χρήστη</h3>
                <p>Συμπληρώστε τα παρακάτω πεδία για να δημιουργήσετε έναν νέο χρήστη</p>
            </div>
            <div class="card-body">
                
                <?php if ($success): ?>
                    <div class="alert-success">
                        <i class="fas fa-check-circle"></i> <?= $success ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Username *</label>
                            <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required placeholder="π.χ. john_doe">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email *</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required placeholder="π.χ. john@example.com">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-user-tie"></i> Όνομα *</label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required placeholder="Όνομα">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-user-tie"></i> Επίθετο *</label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required placeholder="Επίθετο">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Τηλέφωνο</label>
                            <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="π.χ. 99 123456">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Ρόλος *</label>
                            <select name="role_id" required>
                                <option value="">Επιλέξτε ρόλο</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role['id'] ?>" <?= ($_POST['role_id'] ?? '') == $role['id'] ? 'selected' : '' ?>>
                                        <?= ucfirst($role['role_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="button-group">
                        <a href="users.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Ακύρωση
                        </a>
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-save"></i> Δημιουργία Χρήστη
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>