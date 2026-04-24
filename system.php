<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

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

// Get system settings
$settings = $pdo->query("SELECT * FROM system_config")->fetchAll();
$settingsMap = [];
foreach ($settings as $setting) {
    $settingsMap[$setting['config_key']] = $setting['config_value'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $key => $value) {
        if ($key !== 'submit') {
            $stmt = $pdo->prepare("INSERT INTO system_config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = ?");
            $stmt->execute([$key, $value, $value]);
        }
    }
    $success = "Οι ρυθμίσεις αποθηκεύτηκαν επιτυχώς!";
    // Refresh settings
    $settings = $pdo->query("SELECT * FROM system_config")->fetchAll();
    $settingsMap = [];
    foreach ($settings as $setting) {
        $settingsMap[$setting['config_key']] = $setting['config_value'];
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ρυθμίσεις Συστήματος | ΤΕΠΑΚ Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f4f1ec;
            color: #2c2c2c;
        }
        .top-bar {
            background: white;
            padding: 0 30px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #e2dcd5;
        }
        .logo-area h2 { font-size: 1.3rem; font-weight: 600; color: #2c5f8a; margin: 0; }
        .logo-area span { font-weight: 400; color: #8b6b4d; }
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            background: #f4f1ec;
            border-radius: 40px;
        }
        .logout-btn { color: #8b6b4d; text-decoration: none; padding: 8px 16px; border-radius: 8px; }
        .logout-btn:hover { background: #f4f1ec; color: #2c5f8a; }
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
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 24px;
            color: #5a5a5a;
            text-decoration: none;
            margin: 4px 12px;
            border-radius: 12px;
        }
        .sidebar-nav a:hover { background: #f4f1ec; color: #2c5f8a; }
        .sidebar-nav a.active { background: #2c5f8a; color: white; }
        .main-content {
            margin-left: 260px;
            padding: 30px;
            min-height: calc(100vh - 70px);
        }
        .content-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2dcd5;
            overflow: hidden;
            max-width: 800px;
        }
        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e2dcd5;
            background: #faf9f7;
        }
        .card-header h3 { margin: 0; font-size: 1.2rem; font-weight: 600; }
        .card-body { padding: 30px; }
        .form-group { margin-bottom: 25px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c2c2c;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e2dcd5;
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #2c5f8a;
            box-shadow: 0 0 0 3px rgba(44, 95, 138, 0.1);
        }
        .btn-submit {
            background: #2c5f8a;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-submit:hover { background: #1e4668; }
        .alert-success {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo-area"><h2><i class="fas fa-university"></i> ΤΕΠΑΚ <span>| Admin Portal</span></h2></div>
        <div style="display: flex; align-items: center; gap: 15px;">
            <div class="user-info"><i class="fas fa-user-circle"></i><span><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span></div>
            <a href="../auth/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Αποσύνδεση</a>
        </div>
    </div>
    <div class="sidebar">
        <div class="sidebar-nav">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="users.php"><i class="fas fa-users"></i> Διαχείριση Χρηστών</a>
            <a href="recruitment.php"><i class="fas fa-bullhorn"></i> Διαχείριση Προκηρύξεων</a>
            <a href="system.php" class="active"><i class="fas fa-cog"></i> Ρυθμίσεις Συστήματος</a>
            <a href="reports.php"><i class="fas fa-chart-bar"></i> Αναφορές</a>
        </div>
    </div>
    <div class="main-content">
        <h1 style="font-size: 1.8rem; font-weight: 600; margin-bottom: 5px;">Ρυθμίσεις Συστήματος</h1>
        <p style="color: #8a8a8a; margin-bottom: 25px;">Διαχειριστείτε τις παραμέτρους του συστήματος</p>
        <?php if (isset($success)): ?>
            <div class="alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
        <?php endif; ?>
        <div class="content-card">
            <div class="card-header"><h3><i class="fas fa-sliders-h"></i> Γενικές Ρυθμίσεις</h3></div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label><i class="fas fa-globe"></i> Όνομα Ιστότοπου</label>
                        <input type="text" name="site_name" value="<?= htmlspecialchars($settingsMap['site_name'] ?? 'ΤΕΠΑΚ - Σύστημα Διαχείρισης Ειδικών Επιστημόνων') ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-palette"></i> Χρωματικό Θέμα</label>
                        <select name="theme">
                            <option value="pastel_brown" selected>Pastel Brown</option>
                            <option value="light">Light</option>
                            <option value="dark">Dark</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-chalkboard-teacher"></i> Moodle API URL</label>
                        <input type="text" name="moodle_api_url" value="<?= htmlspecialchars($settingsMap['moodle_api_url'] ?? 'https://moodle.cut.ac.cy/webservice/rest/server.php') ?>" placeholder="https://moodle.cut.ac.cy/webservice/rest/server.php">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-sync"></i> Αυτόματος Συγχρονισμός</label>
                        <select name="sync_enabled">
                            <option value="true" <?= ($settingsMap['sync_enabled'] ?? 'true') == 'true' ? 'selected' : '' ?>>Ενεργός</option>
                            <option value="false" <?= ($settingsMap['sync_enabled'] ?? 'true') == 'false' ? 'selected' : '' ?>>Ανενεργός</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email Επικοινωνίας</label>
                        <input type="email" name="contact_email" value="<?= htmlspecialchars($settingsMap['contact_email'] ?? 'admin@tepak.edu.cy') ?>">
                    </div>
                    <button type="submit" name="submit" class="btn-submit"><i class="fas fa-save"></i> Αποθήκευση Ρυθμίσεων</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>