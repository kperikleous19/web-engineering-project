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

$success = '';
$error = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $appId = $_POST['application_id'];
    $newStatus = $_POST['status'];
    $comments = $_POST['comments'] ?? '';
    
    $stmt = $pdo->prepare("UPDATE applications SET status = ?, reviewer_comments = ?, updated_at = NOW() WHERE id = ?");
    if ($stmt->execute([$newStatus, $comments, $appId])) {
        $success = "Η κατάσταση της αίτησης ενημερώθηκε επιτυχώς!";
    } else {
        $error = "Σφάλμα κατά την ενημέρωση της αίτησης.";
    }
}

// Handle recruitment period creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_period'])) {
    $period_name = trim($_POST['period_name']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $description = trim($_POST['description'] ?? '');
    
    if (empty($period_name) || empty($start_date) || empty($end_date)) {
        $error = "Παρακαλώ συμπληρώστε όλα τα υποχρεωτικά πεδία.";
    } elseif ($start_date > $end_date) {
        $error = "Η ημερομηνία λήξης πρέπει να είναι μετά την ημερομηνία έναρξης.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO recruitment_periods (period_name, start_date, end_date, description, is_active, created_by) VALUES (?, ?, ?, ?, 1, ?)");
        if ($stmt->execute([$period_name, $start_date, $end_date, $description, $_SESSION['user_id']])) {
            $success = "Η νέα προκήρυξη δημιουργήθηκε επιτυχώς!";
        } else {
            $error = "Σφάλμα κατά τη δημιουργία της προκήρυξης.";
        }
    }
}

// Handle period deletion
if (isset($_GET['delete_period'])) {
    $periodId = $_GET['delete_period'];
    $stmt = $pdo->prepare("DELETE FROM recruitment_periods WHERE id = ?");
    if ($stmt->execute([$periodId])) {
        $success = "Η προκήρυξη διαγράφηκε επιτυχώς!";
    }
}

// Handle toggle period status
if (isset($_GET['toggle_status'])) {
    $periodId = $_GET['toggle_status'];
    $stmt = $pdo->prepare("UPDATE recruitment_periods SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$periodId]);
    $success = "Η κατάσταση της προκήρυξης ενημερώθηκε.";
}

// Get all applications with user info
$applications = $pdo->query("
    SELECT a.*, u.first_name, u.last_name, u.email 
    FROM applications a 
    JOIN users u ON a.user_id = u.id 
    ORDER BY a.created_at DESC
")->fetchAll();

// Get recruitment periods
$recruitmentPeriods = $pdo->query("SELECT * FROM recruitment_periods ORDER BY created_at DESC")->fetchAll();

// Get statistics
$totalApps = count($applications);
$pendingApps = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'pending' OR status = 'draft'")->fetchColumn();
$reviewApps = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'under_review'")->fetchColumn();
$approvedApps = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'approved'")->fetchColumn();
$activePeriods = $pdo->query("SELECT COUNT(*) FROM recruitment_periods WHERE is_active = 1 AND end_date >= CURDATE()")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Διαχείριση Προκηρύξεων | ΤΕΠΑΚ Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f5f0eb;
            color: #2c2c2c;
        }
        .top-bar {
            background: white;
            padding: 0 30px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #e9dfd7;
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
            border-right: 1px solid #e9dfd7;
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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid #e9dfd7;
            text-align: center;
            transition: all 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.05); }
        .stat-card .stat-number { font-size: 28px; font-weight: 700; color: #2c5f8a; }
        .stat-card .stat-label { font-size: 12px; color: #8a7163; margin-top: 5px; }
        .content-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e9dfd7;
            overflow: hidden;
            margin-bottom: 30px;
        }
        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e9dfd7;
            background: #faf9f7;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .card-header h3 { margin: 0; font-size: 1.2rem; font-weight: 600; color: #2c2c2c; }
        .card-header h3 i { color: #8b6b4d; margin-right: 8px; }
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
            border-bottom: 1px solid #e9dfd7;
        }
        .data-table td {
            padding: 15px 20px;
            border-bottom: 1px solid #f0ece7;
            font-size: 14px;
            color: #3a3a3a;
        }
        .data-table tr:hover { background: #faf9f7; }
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-pending { background: #fff3e0; color: #e67e22; }
        .badge-review { background: #e3f2fd; color: #2c5f8a; }
        .badge-approved { background: #e8f5e9; color: #4caf50; }
        .badge-draft { background: #f0f0f0; color: #666; }
        .badge-active { background: #e8f5e9; color: #4caf50; }
        .badge-inactive { background: #ffebee; color: #dc3545; }
        .btn-sm {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-edit { background: #f0e6df; color: #8b6b4d; }
        .btn-edit:hover { background: #e6d9d0; transform: translateY(-1px); }
        .btn-add { background: #2c5f8a; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; font-size: 14px; border: none; cursor: pointer; }
        .btn-add:hover { background: #1e4668; transform: translateY(-1px); }
        .btn-delete { background: #fdeaea; color: #dc3545; }
        .btn-delete:hover { background: #fcd5d5; transform: translateY(-1px); }
        .btn-toggle { background: #e3f2fd; color: #2c5f8a; }
        .btn-toggle:hover { background: #bbdef5; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; color: #2c2c2c; font-size: 13px; }
        .form-group label i { margin-right: 6px; color: #8b6b4d; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1px solid #e2dcd5; border-radius: 10px; font-family: 'Inter', sans-serif; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #2c5f8a; box-shadow: 0 0 0 3px rgba(44,95,138,0.1); }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; border-radius: 20px; padding: 25px; width: 500px; max-width: 90%; }
        .alert-success {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            color: #2e7d32;
        }
        .alert-error {
            background: #ffebee;
            border-left: 4px solid #f44336;
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            color: #c62828;
        }
        .empty-row td {
            text-align: center;
            padding: 50px !important;
            color: #8a8a8a;
        }
        .empty-row i {
            font-size: 48px;
            margin-bottom: 16px;
            display: block;
            color: #ccc;
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
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="users.php"><i class="fas fa-users"></i> Διαχείριση Χρηστών</a>
            <a href="recruitment.php" class="active"><i class="fas fa-bullhorn"></i> Διαχείριση Προκηρύξεων</a>
            <a href="system.php"><i class="fas fa-cog"></i> Ρυθμίσεις Συστήματος</a>
            <a href="reports.php"><i class="fas fa-chart-bar"></i> Αναφορές</a>
        </div>
    </div>

    <div class="main-content">
        <div style="margin-bottom: 25px;">
            <h1 style="font-size: 1.8rem; font-weight: 600; color: #2c2c2c;">Διαχείριση Προκηρύξεων & Αιτήσεων</h1>
            <p style="color: #8a8a8a; margin-top: 5px;">Διαχειριστείτε τις προκηρύξεις και τις αιτήσεις των υποψηφίων</p>
        </div>

        <?php if ($success): ?>
            <div class="alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-number"><?= $totalApps ?></div><div class="stat-label">Σύνολο Αιτήσεων</div></div>
            <div class="stat-card"><div class="stat-number"><?= $pendingApps ?></div><div class="stat-label">Εκκρεμείς Αιτήσεις</div></div>
            <div class="stat-card"><div class="stat-number"><?= $reviewApps ?></div><div class="stat-label">Υπό Αξιολόγηση</div></div>
            <div class="stat-card"><div class="stat-number"><?= $approvedApps ?></div><div class="stat-label">Εγκεκριμένες</div></div>
            <div class="stat-card"><div class="stat-number"><?= $activePeriods ?></div><div class="stat-label">Ενεργές Προκηρύξεις</div></div>
        </div>

        <!-- Νέα Προκήρυξη Form - VISIBLE BY DEFAULT -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-plus-circle"></i> Νέα Προκήρυξη</h3>
            </div>
            <div class="card-body" style="padding: 25px;">
                <form method="POST">
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Τίτλος Προκήρυξης *</label>
                        <input type="text" name="period_name" required placeholder="π.χ. Χειμερινό Εξάμηνο 2025 - Θέσεις Ειδικών Επιστημόνων">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-calendar-alt"></i> Ημερομηνία Έναρξης *</label>
                            <input type="date" name="start_date" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-calendar-check"></i> Ημερομηνία Λήξης *</label>
                            <input type="date" name="end_date" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-align-left"></i> Περιγραφή (προαιρετικό)</label>
                        <textarea name="description" rows="3" placeholder="Περιγραφή της προκήρυξης, απαιτούμενα προσόντα, αριθμός θέσεων..."></textarea>
                    </div>
                    <button type="submit" name="add_period" class="btn-add"><i class="fas fa-save"></i> Δημιουργία Προκήρυξης</button>
                </form>
            </div>
        </div>

        <!-- Υπάρχουσες Προκηρύξεις -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Υπάρχουσες Προκηρύξεις</h3>
                <span class="badge badge-active"><?= $activePeriods ?> Ενεργές</span>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr><th>ID</th><th>Τίτλος</th><th>Ημ. Έναρξης</th><th>Ημ. Λήξης</th><th>Κατάσταση</th><th>Ενέργειες</th></tr>
                    </thead>
                    <tbody>
                        <?php if (count($recruitmentPeriods) > 0): ?>
                            <?php foreach ($recruitmentPeriods as $period): ?>
                                <?php
                                $isActive = $period['is_active'] && $period['end_date'] >= date('Y-m-d');
                                ?>
                                <tr>
                                    <td><?= $period['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($period['period_name']) ?></strong></td>
                                    <td><?= date('d/m/Y', strtotime($period['start_date'])) ?></td>
                                    <td><?= date('d/m/Y', strtotime($period['end_date'])) ?></td>
                                    <td>
                                        <span class="badge <?= $isActive ? 'badge-active' : 'badge-inactive' ?>">
                                            <?= $isActive ? 'Ενεργή' : 'Λήξη' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?toggle_status=<?= $period['id'] ?>" class="btn-sm btn-toggle" onclick="return confirm('Αλλαγή κατάστασης αυτής της προκήρυξης;')">
                                            <i class="fas <?= $period['is_active'] ? 'fa-pause' : 'fa-play' ?>"></i>
                                        </a>
                                        <a href="?delete_period=<?= $period['id'] ?>" class="btn-sm btn-delete" onclick="return confirm('Σίγουρα θέλετε να διαγράψετε αυτή την προκήρυξη;')">
                                            <i class="fas fa-trash"></i> Διαγραφή
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="empty-row"><td colspan="6"><i class="fas fa-inbox"></i> Δεν υπάρχουν προκηρύξεις. Δημιουργήστε την πρώτη σας προκήρυξη.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Λίστα Αιτήσεων -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-file-alt"></i> Λίστα Αιτήσεων</h3>
                <span class="badge badge-pending"><?= $pendingApps ?> Εκκρεμείς</span>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Υποψήφιος</th>
                            <th>Email</th>
                            <th>Θέση/Μάθημα</th>
                            <th>Τμήμα</th>
                            <th>Κατάσταση</th>
                            <th>Πρόοδος</th>
                            <th>Ημερομηνία</th>
                            <th>Ενέργειες</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($applications) > 0): ?>
                            <?php foreach ($applications as $app): ?>
                                <?php
                                $statusClass = 'badge-pending';
                                $statusText = 'Σε Εξέλιξη';
                                if ($app['status'] == 'approved') {
                                    $statusClass = 'badge-approved';
                                    $statusText = 'Εγκρίθηκε';
                                } elseif ($app['status'] == 'under_review') {
                                    $statusClass = 'badge-review';
                                    $statusText = 'Υπό Αξιολόγηση';
                                } elseif ($app['status'] == 'draft') {
                                    $statusClass = 'badge-draft';
                                    $statusText = 'Προσχέδιο';
                                } elseif ($app['status'] == 'rejected') {
                                    $statusClass = 'badge-inactive';
                                    $statusText = 'Απορρίφθηκε';
                                }
                                ?>
                                <tr>
                                    <td><?= $app['id'] ?></td>
                                    <td><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></td>
                                    <td><?= htmlspecialchars($app['email']) ?></td>
                                    <td><?= htmlspecialchars($app['course_name'] ?? $app['course'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($app['department_name'] ?? $app['department'] ?? '-') ?></td>
                                    <td><span class="badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                                    <td>
                                        <div style="width: 80px;">
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar" style="width: <?= $app['completion_percentage'] ?? 0 ?>%; background: #2c5f8a;"></div>
                                            </div>
                                            <small><?= $app['completion_percentage'] ?? 0 ?>%</small>
                                        </div>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($app['created_at'])) ?></td>
                                    <td>
                                        <button class="btn-sm btn-edit" onclick="openModal(<?= $app['id'] ?>, '<?= $app['status'] ?>', '<?= htmlspecialchars(addslashes($app['reviewer_comments'] ?? '')) ?>')">
                                            <i class="fas fa-edit"></i> Αλλαγή
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="empty-row"><td colspan="9"><i class="fas fa-inbox"></i> Δεν υπάρχουν αιτήσεις προς εμφάνιση</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal for status update -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 20px; color: #2c5f8a;"><i class="fas fa-edit"></i> Ενημέρωση Κατάστασης Αίτησης</h3>
            <form method="POST">
                <input type="hidden" name="application_id" id="modal_app_id">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Κατάσταση:</label>
                    <select name="status" id="modal_status" class="form-control">
                        <option value="pending">Σε Εξέλιξη</option>
                        <option value="under_review">Υπό Αξιολόγηση</option>
                        <option value="approved">Εγκεκριμένη</option>
                        <option value="rejected">Απορριπτέα</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-comment"></i> Σχόλια Αξιολογητή:</label>
                    <textarea name="comments" id="modal_comments" rows="4" class="form-control" placeholder="Προσθέστε σχόλια για τον υποψήφιο..."></textarea>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="update_status" class="btn-add" style="border: none;">Αποθήκευση</button>
                    <button type="button" onclick="closeModal()" style="background: #f4f1ec; border: 1px solid #e2dcd5; padding: 10px 20px; border-radius: 10px; cursor: pointer;">Ακύρωση</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id, status, comments) {
            document.getElementById('modal_app_id').value = id;
            document.getElementById('modal_status').value = status;
            document.getElementById('modal_comments').value = comments;
            document.getElementById('statusModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('statusModal').style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('statusModal')) {
                closeModal();
            }
        }
    </script>
</body>
</html>