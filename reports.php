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

// Get statistics
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalApps = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();

// Get applications by status (using status column)
$appsByStatus = $pdo->query("SELECT status, COUNT(*) as count FROM applications GROUP BY status")->fetchAll();

// Get applications by course name (instead of department)
$appsByCourse = $pdo->query("SELECT course_name, COUNT(*) as count FROM applications WHERE course_name IS NOT NULL AND course_name != '' GROUP BY course_name ORDER BY count DESC LIMIT 5")->fetchAll();

// Get monthly applications (using created_at)
$monthlyApps = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count FROM applications GROUP BY month ORDER BY month DESC LIMIT 6")->fetchAll();

// Get user registrations by month
$monthlyUsers = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count FROM users GROUP BY month ORDER BY month DESC LIMIT 6")->fetchAll();

// Get applications by school/faculty
$appsBySchool = $pdo->query("SELECT school_name, COUNT(*) as count FROM applications WHERE school_name IS NOT NULL AND school_name != '' GROUP BY school_name ORDER BY count DESC LIMIT 5")->fetchAll();

// Calculate percentages
$approvedApps = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'approved'")->fetchColumn();
$pendingApps = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'pending' OR status = 'draft'")->fetchColumn();
$reviewApps = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'under_review'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Αναφορές | ΤΕΠΑΚ Admin</title>
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
        .user-info i { font-size: 18px; color: #8b6b4d; }
        .logout-btn { color: #8b6b4d; text-decoration: none; padding: 8px 16px; border-radius: 8px; transition: all 0.2s; }
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
            margin: 4px 12px;
            border-radius: 12px;
            transition: all 0.2s;
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
            text-align: center;
            transition: all 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.05); }
        .stat-card .stat-number { font-size: 32px; font-weight: 700; color: #2c5f8a; }
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
        .card-header h3 i { color: #8b6b4d; margin-right: 8px; }
        .chart-container { padding: 20px; height: 300px; }
        .btn-export {
            background: #2c5f8a;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        .btn-export:hover { background: #1e4668; transform: translateY(-1px); }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th {
            text-align: left;
            padding: 12px 15px;
            background: #f9f8f6;
            font-weight: 600;
            font-size: 13px;
            color: #5a5a5a;
            border-bottom: 1px solid #e2dcd5;
        }
        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0ece7;
            font-size: 14px;
        }
        .progress-bar-custom {
            height: 8px;
            background: #e2dcd5;
            border-radius: 10px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: #2c5f8a;
            border-radius: 10px;
        }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo-area">
            <h2><i class="fas fa-university"></i> ΤΕΠΑΚ <span>| Admin Portal</span></h2>
        </div>
        <div style="display: flex; align-items: center; gap: 15px;">
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <span><?= htmlspecialchars($_SESSION['username'] ?? 'Administrator') ?></span>
            </div>
            <a href="../auth/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Αποσύνδεση</a>
        </div>
    </div>

    <div class="sidebar">
        <div class="sidebar-nav">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="users.php"><i class="fas fa-users"></i> Διαχείριση Χρηστών</a>
            <a href="recruitment.php"><i class="fas fa-bullhorn"></i> Διαχείριση Προκηρύξεων</a>
            <a href="system.php"><i class="fas fa-cog"></i> Ρυθμίσεις Συστήματος</a>
            <a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> Αναφορές</a>
        </div>
    </div>

    <div class="main-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h1 style="font-size: 1.8rem; font-weight: 600; margin-bottom: 5px; color: #2c2c2c;">Αναφορές & Στατιστικά</h1>
                <p style="color: #8a8a8a;">Αναλυτικά στατιστικά στοιχεία του συστήματος</p>
            </div>
            <button class="btn-export" onclick="window.print()">
                <i class="fas fa-print"></i> Εκτύπωση Αναφοράς
            </button>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $totalUsers ?></div>
                <div class="stat-label">Σύνολο Χρηστών</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $totalApps ?></div>
                <div class="stat-label">Σύνολο Αιτήσεων</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $approvedApps ?></div>
                <div class="stat-label">Εγκεκριμένες Αιτήσεις</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= date('d/m/Y') ?></div>
                <div class="stat-label">Τελευταία Ενημέρωση</div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row">
            <div class="col-md-6">
                <div class="content-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-pie"></i> Κατάσταση Αιτήσεων</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="content-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-line"></i> Αιτήσεις ανά Μήνα</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Applications by Course -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-graduation-cap"></i> Δημοφιλέστερα Μαθήματα / Θέσεις</h3>
            </div>
            <div style="padding: 20px;">
                <?php if (count($appsByCourse) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr><th>Μάθημα / Θέση</th><th>Αριθμός Αιτήσεων</th><th>Ποσοστό</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appsByCourse as $course): ?>
                            <tr>
                                <td><?= htmlspecialchars($course['course_name']) ?></td>
                                <td><?= $course['count'] ?></td>
                                <td><?= round(($course['count'] / $totalApps) * 100, 1) ?>%</td>
                                <td style="width: 40%;">
                                    <div class="progress-bar-custom">
                                        <div class="progress-fill" style="width: <?= round(($course['count'] / $totalApps) * 100) ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center" style="color: #8a8a8a; padding: 40px;">Δεν υπάρχουν δεδομένα αιτήσεων</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Applications by School -->
        <?php if (count($appsBySchool) > 0): ?>
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-university"></i> Αιτήσεις ανά Σχολή</h3>
            </div>
            <div style="padding: 20px;">
                <table class="data-table">
                    <thead>
                        <tr><th>Σχολή</th><th>Αριθμός Αιτήσεων</th><th>Ποσοστό</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appsBySchool as $school): ?>
                        <tr>
                            <td><?= htmlspecialchars($school['school_name']) ?></td>
                            <td><?= $school['count'] ?></td>
                            <td><?= round(($school['count'] / $totalApps) * 100, 1) ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Monthly User Registrations -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-user-plus"></i> Εγγραφές Χρηστών ανά Μήνα</h3>
            </div>
            <div style="padding: 20px;">
                <canvas id="usersChart" height="200"></canvas>
            </div>
        </div>
    </div>

    <script>
        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusData = <?= json_encode($appsByStatus) ?>;
        const statusLabels = statusData.map(s => {
            switch(s.status) {
                case 'approved': return 'Εγκεκριμένες';
                case 'pending': return 'Σε Εξέλιξη';
                case 'draft': return 'Προσχέδια';
                case 'under_review': return 'Υπό Αξιολόγηση';
                case 'rejected': return 'Απορριφθείσες';
                default: return s.status;
            }
        });
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusData.map(s => s.count),
                    backgroundColor: ['#4caf50', '#e67e22', '#9e9e9e', '#2196f3', '#dc3545'],
                    borderWidth: 0
                }]
            },
            options: { responsive: true, maintainAspectRatio: true }
        });

        // Monthly Applications Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyData = <?= json_encode($monthlyApps) ?>;
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: monthlyData.map(m => m.month),
                datasets: [{
                    label: 'Αριθμός Αιτήσεων',
                    data: monthlyData.map(m => m.count),
                    backgroundColor: '#2c5f8a',
                    borderRadius: 8
                }]
            },
            options: { responsive: true, maintainAspectRatio: true }
        });

        // Monthly Users Chart
        const usersCtx = document.getElementById('usersChart').getContext('2d');
        const usersData = <?= json_encode($monthlyUsers) ?>;
        new Chart(usersCtx, {
            type: 'line',
            data: {
                labels: usersData.map(u => u.month),
                datasets: [{
                    label: 'Νέοι Χρήστες',
                    data: usersData.map(u => u.count),
                    borderColor: '#8b6b4d',
                    backgroundColor: 'rgba(139, 107, 77, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: { responsive: true, maintainAspectRatio: true }
        });
    </script>
</body>
</html>