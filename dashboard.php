<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (!in_array($_SESSION['role'], ['admin', 'hr', 'ee'])) {
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

// Get role name for this user
$roleStmt = $pdo->prepare("SELECT r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
$roleStmt->execute([$_SESSION['user_id']]);
$userRole = $roleStmt->fetchColumn();

// Statistics - using role_id instead of role column
$totalEE = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id = (SELECT id FROM roles WHERE role_name = 'ee')")->fetchColumn();
$totalCourses = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$moodleSynced = $pdo->query("SELECT COUNT(*) FROM moodle_integration WHERE moodle_enrolled = 1")->fetchColumn();
$pendingSync = $pdo->query("SELECT COUNT(*) FROM moodle_integration WHERE moodle_enrolled = 0")->fetchColumn();
$activeEE = $pdo->query("
    SELECT COUNT(*) FROM users u 
    JOIN moodle_integration mi ON u.id = mi.user_id 
    WHERE u.role_id = (SELECT id FROM roles WHERE role_name = 'ee') AND mi.access_enabled = 1
")->fetchColumn();

// Recent sync activities
$recentSync = $pdo->query("
    SELECT mi.*, u.first_name, u.last_name, u.email, c.course_name 
    FROM moodle_integration mi
    JOIN users u ON mi.user_id = u.id
    JOIN courses c ON mi.course_id = c.id
    ORDER BY mi.last_sync DESC LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Dashboard | ΤΕΠΑΚ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f0eb; }
        .container { max-width: 1400px; margin: 0 auto; padding: 24px; }
        .header { background: white; border-radius: 20px; padding: 20px 30px; margin-bottom: 25px; border: 1px solid #e9dfd7; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .logo h2 { color: #2c5f8a; font-size: 1.4rem; margin: 0; }
        .logo span { color: #8b6b4d; font-weight: 400; }
        .user-badge { background: #e8ded5; padding: 8px 18px; border-radius: 40px; color: #5a4a40; }
        .logout-btn { background: #e6d9d0; padding: 8px 18px; border-radius: 40px; text-decoration: none; color: #5a4a40; margin-left: 12px; transition: 0.2s; }
        .logout-btn:hover { background: #dccfc4; }
        .nav-menu { background: white; border-radius: 50px; padding: 8px 20px; margin-bottom: 30px; display: flex; flex-wrap: wrap; gap: 8px; border: 1px solid #e9dfd7; }
        .nav-menu a { text-decoration: none; color: #8a8a8a; font-weight: 500; padding: 10px 24px; border-radius: 40px; transition: 0.2s; font-size: 14px; }
        .nav-menu a i { margin-right: 8px; }
        .nav-menu a:hover, .nav-menu a.active { background: #e6d9d0; color: #5a4a40; }
        .welcome-banner { background: linear-gradient(135deg, #2c5f8a 0%, #1e4668 100%); border-radius: 20px; padding: 30px; margin-bottom: 30px; color: white; }
        .welcome-banner h2 { font-size: 1.6rem; margin-bottom: 8px; }
        .welcome-banner p { opacity: 0.9; margin: 0; }
        .stats-row { display: flex; gap: 20px; margin-bottom: 35px; flex-wrap: wrap; }
        .stat-card { background: white; border-radius: 16px; padding: 20px; flex: 1; min-width: 160px; text-align: center; border: 1px solid #e9dfd7; transition: 0.2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .stat-number { font-size: 32px; font-weight: 700; color: #2c5f8a; }
        .stat-label { font-size: 12px; color: #8a7163; margin-top: 5px; text-transform: uppercase; letter-spacing: 0.5px; }
        .action-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; margin-bottom: 35px; }
        .action-card { background: white; border-radius: 20px; padding: 30px 25px; text-align: center; border: 1px solid #e9dfd7; cursor: pointer; transition: 0.3s; }
        .action-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.08); border-color: #dacbc1; }
        .action-card i { font-size: 48px; color: #8b6b4d; margin-bottom: 15px; }
        .action-card h3 { font-size: 1.2rem; font-weight: 600; color: #2c2c2c; margin-bottom: 10px; }
        .action-card p { font-size: 13px; color: #8a7163; margin-bottom: 20px; line-height: 1.5; }
        .action-btn { background: #e6d9d0; padding: 10px 25px; border-radius: 40px; font-size: 13px; font-weight: 500; color: #5a4a40; display: inline-block; }
        .recent-table-container { background: white; border-radius: 20px; border: 1px solid #e9dfd7; overflow: hidden; }
        .table-title { padding: 18px 24px; border-bottom: 1px solid #e9dfd7; background: #faf9f7; }
        .table-title h4 { margin: 0; font-size: 1rem; font-weight: 600; color: #2c2c2c; }
        .table-title h4 i { color: #8b6b4d; margin-right: 8px; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 14px 20px; background: #f9f8f6; font-weight: 600; font-size: 12px; color: #8a8a8a; border-bottom: 1px solid #e9dfd7; }
        .data-table td { padding: 14px 20px; border-bottom: 1px solid #f0ece7; font-size: 13px; color: #3a3a3a; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 30px; font-size: 11px; font-weight: 600; }
        .badge-success { background: #e8f5e9; color: #4caf50; }
        .badge-warning { background: #fff3e0; color: #e67e22; }
        .badge-info { background: #e3f2fd; color: #2c5f8a; }
        .footer { text-align: center; padding: 25px; border-top: 1px solid #e9dfd7; color: #8a8a8a; font-size: 12px; margin-top: 30px; }
        @media (max-width: 900px) { .action-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .stats-row { flex-direction: column; } .container { padding: 16px; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo"><h2><i class="fas fa-chalkboard-teacher"></i> ΤΕΠΑΚ <span>| Enrollment Module</span></h2></div>
            <div><span class="user-badge"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?> (<?= htmlspecialchars($userRole) ?>)</span> <a href="../auth/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Αποσύνδεση</a></div>
        </div>
        
        <div class="nav-menu">
            <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="lms_sync.php"><i class="fas fa-sync"></i> LMS Sync</a>
            <a href="full_sync.php"><i class="fas fa-exchange-alt"></i> Full Sync</a>
            <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
        </div>
        
        <div class="welcome-banner">
            <h2>Καλωσήρθατε, <?= htmlspecialchars($user['first_name']) ?>!</h2>
            <p>Σύστημα Διαχείρισης Εγγραφών και Συγχρονισμού με LMS Moodle - Τεχνολογικό Πανεπιστήμιο Κύπρου</p>
        </div>
        
        <div class="stats-row">
            <div class="stat-card"><div class="stat-number"><?= $totalEE ?></div><div class="stat-label">Ειδικοί Επιστήμονες</div></div>
            <div class="stat-card"><div class="stat-number"><?= $totalCourses ?></div><div class="stat-label">Σύνολο Μαθημάτων</div></div>
            <div class="stat-card"><div class="stat-number"><?= $moodleSynced ?></div><div class="stat-label">Συγχρονισμένοι</div></div>
            <div class="stat-card"><div class="stat-number"><?= $pendingSync ?></div><div class="stat-label">Εκκρεμείς Συγχρονισμοί</div></div>
            <div class="stat-card"><div class="stat-number"><?= $activeEE ?></div><div class="stat-label">Ενεργές Προσβάσεις</div></div>
        </div>
        
        <div class="action-grid">
            <div class="action-card" onclick="location.href='lms_sync.php'"><i class="fas fa-sync-alt"></i><h3>LMS Sync</h3><p>Έλεγχος πρόσβασης στο Moodle και διαχείριση προσβάσεων σε μαθήματα</p><span class="action-btn">Μετάβαση →</span></div>
            <div class="action-card" onclick="location.href='full_sync.php'"><i class="fas fa-database"></i><h3>Full Sync</h3><p>Πλήρης συγχρονισμός χρηστών και ρυθμίσεις αυτόματου συγχρονισμού</p><span class="action-btn">Μετάβαση →</span></div>
            <div class="action-card" onclick="location.href='reports.php'"><i class="fas fa-chart-pie"></i><h3>Reports</h3><p>Στατιστικά πρόσβασης στο Moodle και αναφορές</p><span class="action-btn">Μετάβαση →</span></div>
        </div>
        
        <div class="recent-table-container">
            <div class="table-title"><h4><i class="fas fa-history"></i> Πρόσφατες Δραστηριότητες Συγχρονισμού</h4></div>
            <?php if (empty($recentSync)): ?>
                <div style="padding: 40px; text-align: center; color: #8a8a8a;"><i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>Δεν υπάρχουν πρόσφατες δραστηριότητες</div>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>ΕΕ</th><th>Μάθημα</th><th>Κατάσταση</th><th>Τελευταίος Συγχρονισμός</th><th>Πρόσβαση</th></tr></thead>
                    <tbody>
                        <?php foreach ($recentSync as $sync): ?>
                        <tr>
                            <td><?= htmlspecialchars($sync['first_name'] . ' ' . $sync['last_name']) ?><br><small><?= htmlspecialchars($sync['email']) ?></small></td>
                            <td><?= htmlspecialchars($sync['course_name']) ?></td>
                            <td><span class="badge badge-<?= $sync['moodle_enrolled'] ? 'success' : 'warning' ?>"><?= $sync['moodle_enrolled'] ? 'Συγχρονισμένος' : 'Εκκρεμεί' ?></span></td>
                            <td><?= $sync['last_sync'] ? date('d/m/Y H:i', strtotime($sync['last_sync'])) : '—' ?></td>
                            <td><span class="badge badge-<?= $sync['access_enabled'] ? 'success' : 'warning' ?>"><?= $sync['access_enabled'] ? 'Ενεργή' : 'Ανενεργή' ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div class="footer"><p>© <?= date('Y') ?> Τεχνολογικό Πανεπιστήμιο Κύπρου — Σύστημα Διαχείρισης Ειδικών Επιστημόνων</p></div>
    </div>
</body>
</html>