<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (!in_array($_SESSION['role'], ['admin', 'hr'])) {
    header("Location: ../index.php");
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

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$success = '';
$error = '';
$autoSyncEnabled = true;

// Get current auto-sync setting
$configStmt = $pdo->prepare("SELECT config_value FROM system_config WHERE config_key = 'auto_sync_enabled'");
$configStmt->execute();
$autoSyncEnabled = $configStmt->fetchColumn() === '1';

// Handle full sync
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['run_full_sync'])) {
        // Perform full synchronization
        $stmt = $pdo->prepare("UPDATE moodle_integration SET moodle_enrolled = 1, last_sync = NOW() WHERE moodle_enrolled = 0");
        $stmt->execute();
        $syncedCount = $stmt->rowCount();
        $success = "Πλήρης συγχρονισμός ολοκληρώθηκε! $syncedCount εγγραφές ενημερώθηκαν.";
    } elseif (isset($_POST['toggle_auto_sync'])) {
        $newValue = $autoSyncEnabled ? '0' : '1';
        $stmt = $pdo->prepare("INSERT INTO system_config (config_key, config_value) VALUES ('auto_sync_enabled', ?) ON DUPLICATE KEY UPDATE config_value = ?");
        $stmt->execute([$newValue, $newValue]);
        $autoSyncEnabled = !$autoSyncEnabled;
        $success = "Ο αυτόματος συγχρονισμός " . ($autoSyncEnabled ? "ενεργοποιήθηκε" : "απενεργοποιήθηκε");
    } elseif (isset($_POST['sync_all_users'])) {
        // Sync all EE users
        $stmt = $pdo->prepare("
            UPDATE moodle_integration mi 
            JOIN users u ON mi.user_id = u.id 
            SET mi.moodle_enrolled = 1, mi.last_sync = NOW() 
            WHERE u.role_id = (SELECT id FROM roles WHERE role_name = 'ee')
        ");
        $stmt->execute();
        $success = "Όλοι οι Ειδικοί Επιστήμονες συγχρονίστηκαν!";
    } elseif (isset($_POST['reset_sync'])) {
        $stmt = $pdo->prepare("UPDATE moodle_integration SET moodle_enrolled = 0, last_sync = NULL");
        $stmt->execute();
        $success = "Ο συγχρονισμός επαναφέρθηκε. Όλες οι εγγραφές είναι σε εκκρεμότητα.";
    }
}

// Get statistics
$totalAssignments = $pdo->query("SELECT COUNT(*) FROM moodle_integration")->fetchColumn();
$syncedAssignments = $pdo->query("SELECT COUNT(*) FROM moodle_integration WHERE moodle_enrolled = 1")->fetchColumn();
$pendingAssignments = $totalAssignments - $syncedAssignments;
$totalEE = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id = (SELECT id FROM roles WHERE role_name = 'ee')")->fetchColumn();
$totalCourses = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();

// Get sync history
$syncHistory = $pdo->query("
    SELECT mi.*, u.first_name, u.last_name, c.course_name 
    FROM moodle_integration mi
    JOIN users u ON mi.user_id = u.id
    JOIN courses c ON mi.course_id = c.id
    WHERE mi.last_sync IS NOT NULL
    ORDER BY mi.last_sync DESC LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Πλήρης Συγχρονισμός | ΤΕΠΑΚ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f0eb; }
        .container { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .header { background: white; border-radius: 20px; padding: 20px 30px; margin-bottom: 25px; border: 1px solid #e9dfd7; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .logo h2 { color: #2c5f8a; font-size: 1.4rem; margin: 0; }
        .logo span { color: #8b6b4d; }
        .user-badge { background: #e8ded5; padding: 8px 18px; border-radius: 40px; color: #5a4a40; }
        .logout-btn { background: #e6d9d0; padding: 8px 18px; border-radius: 40px; text-decoration: none; color: #5a4a40; margin-left: 12px; }
        .nav-menu { background: white; border-radius: 50px; padding: 8px 20px; margin-bottom: 30px; display: flex; flex-wrap: wrap; gap: 8px; border: 1px solid #e9dfd7; }
        .nav-menu a { text-decoration: none; color: #8a8a8a; font-weight: 500; padding: 10px 24px; border-radius: 40px; transition: 0.2s; }
        .nav-menu a i { margin-right: 8px; }
        .nav-menu a:hover, .nav-menu a.active { background: #e6d9d0; color: #5a4a40; }
        .content-card { background: white; border-radius: 20px; border: 1px solid #e9dfd7; overflow: hidden; margin-bottom: 30px; }
        .card-header { padding: 20px 28px; border-bottom: 1px solid #e9dfd7; background: #faf9f7; }
        .card-header h3 { margin: 0; font-size: 1.1rem; font-weight: 600; color: #2c2c2c; }
        .card-header h3 i { color: #8b6b4d; margin-right: 8px; }
        .card-body { padding: 28px; }
        .stats-row { display: flex; gap: 20px; margin-bottom: 25px; flex-wrap: wrap; }
        .stat-box { background: white; border-radius: 16px; padding: 20px; flex: 1; text-align: center; border: 1px solid #e9dfd7; }
        .stat-number { font-size: 32px; font-weight: 700; color: #2c5f8a; }
        .stat-label { font-size: 12px; color: #8a7163; margin-top: 5px; }
        .btn-primary { background: #e6d9d0; border: none; padding: 12px 24px; border-radius: 40px; font-weight: 500; cursor: pointer; margin: 5px; }
        .btn-primary:hover { background: #dccfc4; }
        .btn-danger { background: #ffebee; color: #dc3545; border: none; padding: 12px 24px; border-radius: 40px; font-weight: 500; cursor: pointer; }
        .btn-danger:hover { background: #fcd5d5; }
        .btn-success { background: #e8f5e9; color: #4caf50; border: none; padding: 12px 24px; border-radius: 40px; font-weight: 500; cursor: pointer; }
        .btn-success:hover { background: #c8e6c9; }
        .toggle-switch { position: relative; display: inline-block; width: 60px; height: 34px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 26px; width: 26px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #4caf50; }
        input:checked + .slider:before { transform: translateX(26px); }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 12px 15px; background: #f9f8f6; font-weight: 600; font-size: 12px; border-bottom: 1px solid #e9dfd7; }
        .data-table td { padding: 12px 15px; border-bottom: 1px solid #f0ece7; font-size: 13px; }
        .alert-success { background: #e8f5e9; border-left: 4px solid #4caf50; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; color: #2e7d32; }
        .footer { text-align: center; padding: 25px; border-top: 1px solid #e9dfd7; color: #8a8a8a; font-size: 12px; margin-top: 30px; }
        @media (max-width: 768px) { .container { padding: 16px; } .stats-row { flex-direction: column; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo"><h2><i class="fas fa-chalkboard-teacher"></i> ΤΕΠΑΚ <span>| Enrollment Module</span></h2></div>
            <div><span class="user-badge"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span> <a href="../auth/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Αποσύνδεση</a></div>
        </div>
        
        <div class="nav-menu">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="lms_sync.php"><i class="fas fa-sync"></i> LMS Sync</a>
            <a href="full_sync.php" class="active"><i class="fas fa-exchange-alt"></i> Full Sync</a>
            <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
        </div>
        
        <?php if ($success): ?>
            <div class="alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
        <?php endif; ?>
        
        <div class="stats-row">
            <div class="stat-box"><div class="stat-number"><?= $totalEE ?></div><div class="stat-label">Ειδικοί Επιστήμονες</div></div>
            <div class="stat-box"><div class="stat-number"><?= $totalCourses ?></div><div class="stat-label">Σύνολο Μαθημάτων</div></div>
            <div class="stat-box"><div class="stat-number"><?= $totalAssignments ?></div><div class="stat-label">Σύνολο Αναθέσεων</div></div>
            <div class="stat-box"><div class="stat-number"><?= $syncedAssignments ?></div><div class="stat-label">Συγχρονισμένες</div></div>
            <div class="stat-box"><div class="stat-number"><?= $pendingAssignments ?></div><div class="stat-label">Σε Εκκρεμότητα</div></div>
        </div>
        
        <div class="content-card">
            <div class="card-header"><h3><i class="fas fa-sliders-h"></i> Ρυθμίσεις Αυτόματου Συγχρονισμού</h3></div>
            <div class="card-body">
                <form method="POST" class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div>
                        <strong>Αυτόματος Συγχρονισμός Moodle:</strong>
                        <span class="ms-2 badge <?= $autoSyncEnabled ? 'badge-success' : 'badge-secondary' ?>">
                            <?= $autoSyncEnabled ? 'Ενεργός' : 'Ανενεργός' ?>
                        </span>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" <?= $autoSyncEnabled ? 'checked' : '' ?> onchange="this.form.submit()" name="toggle_auto_sync">
                        <span class="slider"></span>
                    </label>
                </form>
                <p class="text-muted mt-3 small">Όταν είναι ενεργός, ο συγχρονισμός γίνεται αυτόματα κάθε 24 ώρες.</p>
            </div>
        </div>
        
        <div class="row g-4">
            <div class="col-md-6">
                <div class="content-card">
                    <div class="card-header"><h3><i class="fas fa-play-circle"></i> Ενέργειες Συγχρονισμού</h3></div>
                    <div class="card-body">
                        <form method="POST">
                            <button type="submit" name="run_full_sync" class="btn-primary w-100 mb-3">
                                <i class="fas fa-sync-alt"></i> Εκτέλεση Πλήρους Συγχρονισμού
                            </button>
                            <button type="submit" name="sync_all_users" class="btn-success w-100 mb-3">
                                <i class="fas fa-users"></i> Συγχρονισμός Όλων των Ειδικών Επιστημόνων
                            </button>
                            <button type="submit" name="reset_sync" class="btn-danger w-100" onclick="return confirm('Επαναφορά όλων των συγχρονισμών;')">
                                <i class="fas fa-undo-alt"></i> Επαναφορά Συγχρονισμού
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="content-card">
                    <div class="card-header"><h3><i class="fas fa-chart-pie"></i> Πρόοδος Συγχρονισμού</h3></div>
                    <div class="card-body">
                        <div class="progress mb-3" style="height: 30px;">
                            <div class="progress-bar bg-success" style="width: <?= $totalAssignments > 0 ? round(($syncedAssignments / $totalAssignments) * 100) : 0 ?>%;">
                                <?= $totalAssignments > 0 ? round(($syncedAssignments / $totalAssignments) * 100) : 0 ?>%
                            </div>
                        </div>
                        <p class="text-center"><?= $syncedAssignments ?> / <?= $totalAssignments ?> αναθέσεις συγχρονίστηκαν</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="content-card">
            <div class="card-header"><h3><i class="fas fa-history"></i> Ιστορικό Συγχρονισμού</h3></div>
            <div class="card-body" style="overflow-x: auto;">
                <?php if (empty($syncHistory)): ?>
                    <p class="text-center py-4">Δεν υπάρχουν καταγεγραμμένοι συγχρονισμοί</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr><th>Ειδικός Επιστήμονας</th><th>Μάθημα</th><th>Κατάσταση</th><th>Ημερομηνία Συγχρονισμού</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($syncHistory as $sync): ?>
                            <tr>
                                <td><?= htmlspecialchars($sync['first_name'] . ' ' . $sync['last_name']) ?></td>
                                <td><?= htmlspecialchars($sync['course_name']) ?></td>
                                <td><span class="badge badge-success">Συγχρονισμένο</span></td>
                                <td><?= date('d/m/Y H:i:s', strtotime($sync['last_sync'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="footer"><p>© <?= date('Y') ?> Τεχνολογικό Πανεπιστήμιο Κύπρου — Σύστημα Διαχείρισης Ειδικών Επιστημόνων</p></div>
    </div>
</body>
</html>