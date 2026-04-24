<?php
session_start();

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// Database connection
$host = "127.0.0.1";
$dbname = "tepak_ee";
$username = "root";
$password = "oTem333!";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get statistics
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalAdmins = $pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE r.role_name = 'admin'")->fetchColumn();
$totalCandidates = $pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE r.role_name = 'candidate'")->fetchColumn();

// Get all users
$stmt = $pdo->query("
    SELECT u.*, r.role_name 
    FROM users u 
    LEFT JOIN roles r ON u.role_id = r.id 
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Διαχείριση Χρηστών | ΤΕΠΑΚ Admin</title>
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

        .logo-area span {
            font-weight: 400;
            color: #8b6b4d;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            background: #f4f1ec;
            border-radius: 40px;
        }

        .user-info i {
            font-size: 18px;
            color: #8b6b4d;
        }

        .user-info span {
            font-weight: 500;
            color: #2c2c2c;
        }

        .logout-btn {
            color: #8b6b4d;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .logout-btn:hover {
            background: #f4f1ec;
            color: #2c5f8a;
        }

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

        .sidebar-nav {
            padding: 20px 0;
        }

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

        .sidebar-nav a i {
            width: 22px;
            font-size: 18px;
        }

        .sidebar-nav a:hover {
            background: #f4f1ec;
            color: #2c5f8a;
        }

        .sidebar-nav a.active {
            background: #2c5f8a;
            color: white;
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 30px;
            min-height: calc(100vh - 70px);
        }

        /* Stats Cards */
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

        .stat-card .stat-icon {
            font-size: 28px;
            color: #8b6b4d;
            margin-bottom: 12px;
        }

        .stat-card .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #2c5f8a;
            margin-bottom: 5px;
        }

        .stat-card .stat-label {
            font-size: 13px;
            color: #8a8a8a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Content Card */
        .content-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2dcd5;
            overflow: hidden;
        }

        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e2dcd5;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .card-header h3 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c2c2c;
        }

        /* Table */
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

        .data-table tr:hover {
            background: #faf9f7;
        }

        /* Badges */
        .badge-role {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-admin { background: #2c5f8a; color: white; }
        .badge-hr { background: #8b6b4d; color: white; }
        .badge-candidate { background: #6c9ebf; color: white; }
        .badge-evaluator { background: #a8c4a0; color: #2c2c2c; }

        /* Action Buttons */
        .action-btns {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-edit {
            background: #f0e6df;
            color: #8b6b4d;
        }

        .btn-edit:hover {
            background: #e6d9d0;
            transform: translateY(-1px);
        }

        .btn-delete {
            background: #fdeaea;
            color: #dc3545;
        }

        .btn-delete:hover {
            background: #fcd5d5;
            transform: translateY(-1px);
        }

        .btn-add {
            background: #2c5f8a;
            color: white;
            padding: 8px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-add:hover {
            background: #1e4668;
            transform: translateY(-1px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px;
            color: #a0a0a0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }
            .main-content {
                margin-left: 0;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
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
            <a href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="users.php" class="active">
                <i class="fas fa-users"></i> Διαχείριση Χρηστών
            </a>
            <a href="recruitment.php">
                <i class="fas fa-bullhorn"></i> Διαχείριση Προκηρύξεων
            </a>
            <a href="system.php">
                <i class="fas fa-cog"></i> Ρυθμίσεις Συστήματος
            </a>
            <a href="reports.php">
                <i class="fas fa-chart-bar"></i> Αναφορές
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        
        <!-- Page Title -->
        <div style="margin-bottom: 25px;">
            <h1 style="font-size: 1.8rem; font-weight: 600; color: #2c2c2c;">Διαχείριση Χρηστών</h1>
            <p style="color: #8a8a8a; margin-top: 5px;">Διαχειριστείτε τους λογαριασμούς χρηστών του συστήματος</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?= $totalUsers ?></div>
                <div class="stat-label">Σύνολο Χρηστών</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-shield"></i></div>
                <div class="stat-number"><?= $totalAdmins ?></div>
                <div class="stat-label">Διαχειριστές</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-number"><?= $totalCandidates ?></div>
                <div class="stat-label">Υποψήφιοι</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-calendar"></i></div>
                <div class="stat-number"><?= date('d/m/Y') ?></div>
                <div class="stat-label">Τελευταία Ενημέρωση</div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Λίστα Χρηστών</h3>
                <a href="add_user.php" class="btn-add">
                    <i class="fas fa-plus"></i> Νέος Χρήστης
                </a>
            </div>
            
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Ονοματεπώνυμο</th>
                            <th>Τηλέφωνο</th>
                            <th>Ρόλος</th>
                            <th>Ημ. Εγγραφής</th>
                            <th>Ενέργειες</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $user['id'] ?></td>
                                <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                                <td><?= htmlspecialchars($user['phone'] ?? '-') ?></td>
                                <td>
                                    <span class="badge-role badge-<?= $user['role_name'] ?? 'candidate' ?>">
                                        <?= ucfirst($user['role_name'] ?? 'Candidate') ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                                <td>
                                    <div class="action-btns">
                                        <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn-icon btn-edit" title="Επεξεργασία">
                                            <i class="fas fa-edit"></i> Επεξεργασία
                                        </a>
                                        <?php if ($user['role_name'] !== 'admin'): ?>
                                        <a href="delete_user.php?id=<?= $user['id'] ?>" class="btn-icon btn-delete" title="Διαγραφή" onclick="return confirm('Σίγουρα θέλετε να διαγράψετε αυτόν τον χρήστη;')">
                                            <i class="fas fa-trash"></i> Διαγραφή
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="empty-state">
                                    <i class="fas fa-inbox" style="font-size: 48px; color: #ccc;"></i>
                                    <p>Δεν βρέθηκαν χρήστες</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>