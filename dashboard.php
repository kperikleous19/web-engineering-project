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

// Get statistics
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalApplications = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
$pendingApplications = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'pending' OR status = 'under_review'")->fetchColumn();
$approvedApplications = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'approved'")->fetchColumn();
$totalRecruitments = $pdo->query("SELECT COUNT(*) FROM recruitment_periods WHERE is_active = 1 AND end_date >= CURDATE()")->fetchColumn();

// Get recent applications
$recentApps = $pdo->query("
    SELECT a.*, u.first_name, u.last_name 
    FROM applications a 
    JOIN users u ON a.user_id = u.id 
    ORDER BY a.created_at DESC 
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | ΤΕΠΑΚ Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .main-content {
            margin-left: 260px;
            padding: 30px;
            min-height: calc(100vh - 70px);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid #e2dcd5;
            transition: all 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.05);
        }
        .stat-card .stat-icon { font-size: 28px; color: #8b6b4d; margin-bottom: 12px; }
        .stat-card .stat-number { font-size: 28px; font-weight: 700; color: #2c5f8a; margin-bottom: 5px; }
        .stat-card .stat-label { font-size: 13px; color: #8a8a8a; text-transform: uppercase; letter-spacing: 0.5px; }
        .content-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2dcd5;
            overflow: hidden;
            margin-bottom: 30px;
        }
        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e2dcd5;
            background: #faf9f7;
        }
        .card-header h3 { margin: 0; font-size: 1.2rem; font-weight: 600; color: #2c2c2c; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            text-align: left;
            padding: 15px 20px;
            background: #f9f8f6;
            font-weight: 600;
            font-size: 13px;
            color: #5a5a5a;
            border-bottom: 1px solid #e2dcd5;
        }
        .data-table td {
            padding: 15px 20px;
            border-bottom: 1px solid #f0ece7;
            font-size: 14px;
            color: #3a3a3a;
        }
        .badge-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-pending { background: #fff3e0; color: #e67e22; }
        .badge-approved { background: #e8f5e9; color: #4caf50; }
        .badge-review { background: #e3f2fd; color: #2196f3; }
        .chart-container { padding: 20px; height: 300px; }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <div class="top-bar">
        <div class="logo-area">
            <h2><i class="fas fa-university"></i> ΤΕΠΑΚ <span>| Admin Portal</span></h2>
        </div>
        <div class="user-menu">
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <span><?= htmlspecialchars($_SESSION['username'] ?? 'Administrator') ?></span>
            </div>
            <a href="../auth/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Αποσύνδεση</a>
        </div>
    </div>

    <div class="sidebar">
        <div class="sidebar-nav">
            <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="users.php"><i class="fas fa-users"></i> Διαχείριση Χρηστών</a>
            <a href="recruitment.php"><i class="fas fa-bullhorn"></i> Διαχείριση Προκηρύξεων</a>
            <a href="system.php"><i class="fas fa-cog"></i> Ρυθμίσεις Συστήματος</a>
            <a href="reports.php"><i class="fas fa-chart-bar"></i> Αναφορές</a>
        </div>
    </div>

    <div class="main-content">
        <div style="margin-bottom: 25px;">
            <h1 style="font-size: 1.8rem; font-weight: 600; color: #2c2c2c;">Καλωσήρθατε, <?= htmlspecialchars($_SESSION['first_name'] ?? 'Διαχειριστή') ?>!</h1>
            <p style="color: #8a8a8a; margin-top: 5px;">Εδώ μπορείτε να δείτε μια επισκόπηση του συστήματος</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?= $totalUsers ?></div>
                <div class="stat-label">Σύνολο Χρηστών</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                <div class="stat-number"><?= $totalApplications ?></div>
                <div class="stat-label">Σύνολο Αιτήσεων</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?= $pendingApplications ?></div>
                <div class="stat-label">Εκκρεμείς Αιτήσεις</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?= $approvedApplications ?></div>
                <div class="stat-label">Εγκεκριμένες</div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="content-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-pie"></i> Κατανομή Αιτήσεων</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="content-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-line"></i> Στατιστικά Ρόλων</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="rolesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Πρόσφατες Αιτήσεις</h3>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr><th>ID</th><th>Υποψήφιος</th><th>Μάθημα</th><th>Τμήμα</th><th>Κατάσταση</th><th>Ημερομηνία</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentApps as $app): ?>
                        <tr>
                            <td><?= $app['id'] ?></td>
                            <td><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></td>
                            <td><?= htmlspecialchars($app['course_name'] ?? $app['course']) ?></td>
                            <td><?= htmlspecialchars($app['department_name'] ?? $app['department']) ?></td>
                            <td>
                                <span class="badge-status badge-<?= $app['status'] == 'approved' ? 'approved' : ($app['status'] == 'under_review' ? 'review' : 'pending') ?>">
                                    <?= ucfirst($app['status'] ?? 'pending') ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($app['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Εκκρεμείς', 'Υπό Αξιολόγηση', 'Εγκεκριμένες', 'Απορριφθείσες'],
                datasets: [{
                    data: [<?= $pendingApplications ?>, 5, <?= $approvedApplications ?>, 3],
                    backgroundColor: ['#e67e22', '#2196f3', '#4caf50', '#dc3545'],
                    borderWidth: 0
                }]
            },
            options: { responsive: true, maintainAspectRatio: true }
        });

        // Roles Chart
        const rolesCtx = document.getElementById('rolesChart').getContext('2d');
        new Chart(rolesCtx, {
            type: 'bar',
            data: {
                labels: ['Διαχειριστές', 'HR', 'Υποψήφιοι', 'Αξιολογητές', 'ΕΕ'],
                datasets: [{
                    label: 'Αριθμός Χρηστών',
                    data: [<?= $totalAdmins ?? 1 ?>, 1, <?= $totalCandidates ?? 0 ?>, 1, 0],
                    backgroundColor: '#2c5f8a',
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { position: 'top' } }
            }
        });
    </script>
</body>
</html>