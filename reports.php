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

// Statistics
$totalEE = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id = (SELECT id FROM roles WHERE role_name = 'ee')")->fetchColumn();
$totalCourses = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$totalAssignments = $pdo->query("SELECT COUNT(*) FROM moodle_integration")->fetchColumn();
$syncedAssignments = $pdo->query("SELECT COUNT(*) FROM moodle_integration WHERE moodle_enrolled = 1")->fetchColumn();
$pendingAssignments = $totalAssignments - $syncedAssignments;

// Courses without assigned professor
$coursesWithoutProf = $pdo->query("
    SELECT c.*, d.dept_name_el 
    FROM courses c
    LEFT JOIN moodle_integration mi ON c.id = mi.course_id
    LEFT JOIN departments d ON c.department_id = d.id
    WHERE mi.id IS NULL
")->fetchAll();

// EE without any course assignment
$eeWithoutCourses = $pdo->query("
    SELECT u.* 
    FROM users u
    LEFT JOIN moodle_integration mi ON u.id = mi.user_id
    WHERE u.role_id = (SELECT id FROM roles WHERE role_name = 'ee')
    AND mi.id IS NULL
")->fetchAll();

// Top professors by course count
$topProfessors = $pdo->query("
    SELECT u.first_name, u.last_name, COUNT(mi.course_id) as course_count
    FROM moodle_integration mi
    JOIN users u ON mi.user_id = u.id
    WHERE u.role_id = (SELECT id FROM roles WHERE role_name = 'ee')
    GROUP BY u.id
    ORDER BY course_count DESC
    LIMIT 10
")->fetchAll();

// Sync status by department
$syncByDepartment = $pdo->query("
    SELECT 
        d.dept_name_el as department,
        COUNT(mi.id) as total,
        SUM(CASE WHEN mi.moodle_enrolled = 1 THEN 1 ELSE 0 END) as synced
    FROM moodle_integration mi
    JOIN courses c ON mi.course_id = c.id
    JOIN departments d ON c.department_id = d.id
    GROUP BY d.id
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Αναφορές | ΤΕΠΑΚ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f0eb; }
        .container { max-width: 1400px; margin: 0 auto; padding: 24px; }
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
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 12px 15px; background: #f9f8f6; font-weight: 600; font-size: 12px; border-bottom: 1px solid #e9dfd7; }
        .data-table td { padding: 12px 15px; border-bottom: 1px solid #f0ece7; font-size: 13px; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 30px; font-size: 11px; font-weight: 600; }
        .badge-success { background: #e8f5e9; color: #4caf50; }
        .badge-warning { background: #fff3e0; color: #e67e22; }
        .badge-danger { background: #ffebee; color: #dc3545; }
        .progress { height: 8px; border-radius: 10px; }
        .btn-print { background: #e6d9d0; border: none; padding: 10px 20px; border-radius: 40px; cursor: pointer; }
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
            <a href="full_sync.php"><i class="fas fa-exchange-alt"></i> Full Sync</a>
            <a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> Reports</a>
        </div>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 style="color: #6c4f3a;"><i class="fas fa-chart-pie"></i> Αναφορές & Στατιστικά</h2>
            <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Εκτύπωση</button>
        </div>
        
        <div class="stats-row">
            <div class="stat-box"><div class="stat-number"><?= $totalEE ?></div><div class="stat-label">Ειδικοί Επιστήμονες</div></div>
            <div class="stat-box"><div class="stat-number"><?= $totalCourses ?></div><div class="stat-label">Σύνολο Μαθημάτων</div></div>
            <div class="stat-box"><div class="stat-number"><?= $totalAssignments ?></div><div class="stat-label">Αναθέσεις</div></div>
            <div class="stat-box"><div class="stat-number"><?= $syncedAssignments ?></div><div class="stat-label">Συγχρονισμένες</div></div>
            <div class="stat-box"><div class="stat-number"><?= $pendingAssignments ?></div><div class="stat-label">Εκκρεμείς</div></div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="content-card">
                    <div class="card-header"><h3><i class="fas fa-chart-line"></i> Πρόοδος Συγχρονισμού</h3></div>
                    <div class="card-body">
                        <canvas id="syncChart" height="200"></canvas>
                        <div class="progress mt-3" style="height: 25px;">
                            <div class="progress-bar bg-success" style="width: <?= $totalAssignments > 0 ? round(($syncedAssignments / $totalAssignments) * 100) : 0 ?>%;">
                                <?= $totalAssignments > 0 ? round(($syncedAssignments / $totalAssignments) * 100) : 0 ?>%
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="content-card">
                    <div class="card-header"><h3><i class="fas fa-trophy"></i> Κορυφαίοι Ειδικοί Επιστήμονες</h3></div>
                    <div class="card-body">
                        <table class="data-table">
                            <thead><tr><th>Ονοματεπώνυμο</th><th>Αριθμός Μαθημάτων</th></tr></thead>
                            <tbody>
                                <?php foreach ($topProfessors as $prof): ?>
                                <tr><td><?= htmlspecialchars($prof['first_name'] . ' ' . $prof['last_name']) ?></td><td><?= $prof['course_count'] ?></td></tr>
                                <?php endforeach; ?>
                                <?php if (empty($topProfessors)): ?>
                                <tr><td colspan="2" class="text-center">Δεν υπάρχουν δεδομένα</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="content-card">
                    <div class="card-header"><h3><i class="fas fa-exclamation-triangle"></i> Μαθήματα Χωρίς Διδάσκοντα</h3></div>
                    <div class="card-body">
                        <?php if (empty($coursesWithoutProf)): ?>
                            <p class="text-center py-3 text-success">Όλα τα μαθήματα έχουν διδάσκοντα!</p>
                        <?php else: ?>
                            <table class="data-table">
                                <thead><tr><th>Μάθημα</th><th>Τμήμα</th></tr></thead>
                                <tbody>
                                    <?php foreach ($coursesWithoutProf as $course): ?>
                                    <tr><td><?= htmlspecialchars($course['course_name_el']) ?></td><td><?= htmlspecialchars($course['dept_name_el'] ?? '—') ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="content-card">
                    <div class="card-header"><h3><i class="fas fa-user-slash"></i> Ειδικοί Επιστήμονες Χωρίς Ανάθεση</h3></div>
                    <div class="card-body">
                        <?php if (empty($eeWithoutCourses)): ?>
                            <p class="text-center py-3 text-success">Όλοι οι Ειδικοί Επιστήμονες έχουν αναθέσεις!</p>
                        <?php else: ?>
                            <table class="data-table">
                                <thead><tr><th>Ονοματεπώνυμο</th><th>Email</th></tr></thead>
                                <tbody>
                                    <?php foreach ($eeWithoutCourses as $ee): ?>
                                    <tr><td><?= htmlspecialchars($ee['first_name'] . ' ' . $ee['last_name']) ?></td><td><?= htmlspecialchars($ee['email']) ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="content-card">
            <div class="card-header"><h3><i class="fas fa-building"></i> Κατάσταση Συγχρονισμού ανά Τμήμα</h3></div>
            <div class="card-body">
                <table class="data-table">
                    <thead><tr><th>Τμήμα</th><th>Σύνολο Αναθέσεων</th><th>Συγχρονισμένες</th><th>Πρόοδος</th></tr></thead>
                    <tbody>
                        <?php foreach ($syncByDepartment as $dept): ?>
                        <tr>
                            <td><?= htmlspecialchars($dept['department']) ?></td>
                            <td><?= $dept['total'] ?></td>
                            <td><?= $dept['synced'] ?></td>
                            <td><div class="progress"><div class="progress-bar bg-success" style="width: <?= $dept['total'] > 0 ? round(($dept['synced'] / $dept['total']) * 100) : 0 ?>%"></div></div></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="footer"><p>© <?= date('Y') ?> Τεχνολογικό Πανεπιστήμιο Κύπρου</p></div>
    </div>
    
    <script>
        new Chart(document.getElementById('syncChart'), {
            type: 'doughnut',
            data: {
                labels: ['Συγχρονισμένες', 'Εκκρεμείς'],
                datasets: [{
                    data: [<?= $syncedAssignments ?>, <?= $pendingAssignments ?>],
                    backgroundColor: ['#4caf50', '#e67e22']
                }]
            }
        });
    </script>
</body>
</html>