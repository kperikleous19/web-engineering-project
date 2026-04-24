<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$host = "127.0.0.1";
$dbname = "tepak_ee";
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

$stmt = $pdo->prepare("SELECT * FROM applications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$applications = $stmt->fetchAll();

$totalApps = count($applications);
$approvedApps = $pendingApps = $reviewApps = 0;
foreach ($applications as $app) {
    if ($app['status'] == 'approved') $approvedApps++;
    elseif ($app['status'] == 'pending') $pendingApps++;
    elseif ($app['status'] == 'under_review') $reviewApps++;
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Κατάσταση Αιτήσεων | ΤΕΠΑΚ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f0eb; }
        .container { max-width: 1100px; margin: 0 auto; padding: 24px; }
        .header { background: white; border-radius: 20px; padding: 20px 30px; margin-bottom: 25px; border: 1px solid #e9dfd7; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .logo h2 { color: #2c5f8a; font-size: 1.4rem; margin: 0; }
        .logo span { color: #8b6b4d; }
        .user-badge { background: #e8ded5; padding: 8px 18px; border-radius: 40px; color: #5a4a40; }
        .logout-btn { background: #e6d9d0; padding: 8px 18px; border-radius: 40px; text-decoration: none; color: #5a4a40; margin-left: 12px; }
        .nav-menu { background: white; border-radius: 50px; padding: 8px 20px; margin-bottom: 30px; display: flex; flex-wrap: wrap; gap: 8px; border: 1px solid #e9dfd7; }
        .nav-menu a { text-decoration: none; color: #8a8a8a; font-weight: 500; padding: 10px 24px; border-radius: 40px; transition: 0.2s; }
        .nav-menu a i { margin-right: 8px; }
        .nav-menu a:hover, .nav-menu a.active { background: #e6d9d0; color: #5a4a40; }
        
        .stats-row { display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap; }
        .stat-box { background: white; border-radius: 16px; padding: 20px; flex: 1; min-width: 140px; text-align: center; border: 1px solid #e9dfd7; }
        .stat-number { font-size: 32px; font-weight: 700; color: #2c5f8a; }
        .stat-label { font-size: 12px; color: #8a7163; margin-top: 5px; }
        
        .table-container { background: white; border-radius: 20px; border: 1px solid #e9dfd7; overflow: hidden; }
        .table-header { padding: 18px 24px; border-bottom: 1px solid #e9dfd7; background: #faf9f7; }
        .table-header h3 { margin: 0; font-size: 1rem; font-weight: 600; color: #2c2c2c; }
        .table-header h3 i { color: #8b6b4d; margin-right: 8px; }
        .status-table { width: 100%; border-collapse: collapse; }
        .status-table th { text-align: left; padding: 14px 20px; background: #f9f8f6; font-weight: 600; font-size: 12px; color: #8a8a8a; border-bottom: 1px solid #e9dfd7; }
        .status-table td { padding: 16px 20px; border-bottom: 1px solid #f0ece7; font-size: 14px; }
        .status-table tr:last-child td { border-bottom: none; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 30px; font-size: 11px; font-weight: 600; }
        .badge-draft { background: #f0f0f0; color: #666; }
        .badge-pending { background: #fff3e0; color: #e67e22; }
        .badge-review { background: #e3f2fd; color: #2c5f8a; }
        .badge-approved { background: #e8f5e9; color: #4caf50; }
        .badge-rejected { background: #ffebee; color: #dc3545; }
        .progress { width: 100px; height: 6px; background: #e9dfd7; border-radius: 3px; overflow: hidden; }
        .progress-bar { background: #2c5f8a; height: 100%; }
        .footer { text-align: center; padding: 25px; border-top: 1px solid #e9dfd7; color: #8a8a8a; font-size: 12px; margin-top: 30px; }
        .empty-state { text-align: center; padding: 60px; color: #8a8a8a; }
        @media (max-width: 768px) { .stats-row { flex-direction: column; } .container { padding: 16px; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo"><h2><i class="fas fa-graduation-cap"></i> ΤΕΠΑΚ <span>| Recruitment Module</span></h2></div>
            <div><span class="user-badge"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span> <a href="../auth/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Αποσύνδεση</a></div>
        </div>
        
        <div class="nav-menu">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="my_profile.php"><i class="fas fa-user"></i> My Profile</a>
            <a href="my_applications.php"><i class="fas fa-file-alt"></i> My Applications</a>
            <a href="application_status.php" class="active"><i class="fas fa-chart-line"></i> Κατάσταση Αιτήσεων</a>
        </div>
        
        <div class="stats-row">
            <div class="stat-box"><div class="stat-number"><?= $totalApps ?></div><div class="stat-label">Σύνολο Αιτήσεων</div></div>
            <div class="stat-box"><div class="stat-number"><?= $approvedApps ?></div><div class="stat-label">Εγκεκριμένες</div></div>
            <div class="stat-box"><div class="stat-number"><?= $reviewApps ?></div><div class="stat-label">Υπό Αξιολόγηση</div></div>
            <div class="stat-box"><div class="stat-number"><?= $pendingApps ?></div><div class="stat-label">Σε Εξέλιξη</div></div>
        </div>
        
        <div class="table-container">
            <div class="table-header"><h3><i class="fas fa-chart-simple"></i> Πρόοδος Αιτήσεων</h3></div>
            <?php if (empty($applications)): ?>
                <div class="empty-state"><i class="fas fa-folder-open" style="font-size: 48px; margin-bottom: 16px; display: block;"></i><p>Δεν υπάρχουν αιτήσεις προς εμφάνιση</p><a href="my_applications.php" class="btn-primary" style="display: inline-block; margin-top: 16px; padding: 10px 24px; background: #e6d9d0; border-radius: 40px; text-decoration: none; color: #5a4a40;">Δημιουργία Αίτησης</a></div>
            <?php else: ?>
                <table class="status-table">
                    <thead><tr><th>Αίτηση</th><th>Τμήμα</th><th>Κατάσταση</th><th>Πρόοδος</th><th>Ημερομηνία</th><th>Σχόλια</th></tr></thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($app['course_name'] ?? $app['course'] ?? 'Αίτηση') ?></strong></td>
                            <td><?= htmlspecialchars($app['department_name'] ?? $app['department'] ?? '—') ?></td>
                            <td><span class="badge badge-<?= $app['status'] ?>"><?= $app['status'] == 'approved' ? 'Εγκρίθηκε' : ($app['status'] == 'under_review' ? 'Υπό Αξιολόγηση' : ($app['status'] == 'draft' ? 'Προσχέδιο' : 'Σε Εξέλιξη')) ?></span></td>
                            <td><div class="progress"><div class="progress-bar" style="width: <?= $app['completion_percentage'] ?? 0 ?>%"></div></div><small><?= $app['completion_percentage'] ?? 0 ?>%</small></td>
                            <td><?= date('d/m/Y', strtotime($app['created_at'])) ?></td>
                            <td><?= $app['reviewer_comments'] ? '<i class="fas fa-comment" style="color: #8b6b4d;"></i> ' . htmlspecialchars(substr($app['reviewer_comments'], 0, 30)) . (strlen($app['reviewer_comments']) > 30 ? '...' : '') : '—' ?></td>
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