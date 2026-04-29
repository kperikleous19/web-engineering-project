<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (!in_array($_SESSION['role'], ['candidate', 'evaluator', 'hr'])) {
    header("Location: ../index.php");
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM applications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$applications = $stmt->fetchAll();

$totalApps = count($applications);
$draftApps = $pendingApps = $reviewApps = $approvedApps = 0;
foreach ($applications as $app) {
    if ($app['status'] == 'draft') $draftApps++;
    elseif ($app['status'] == 'pending') $pendingApps++;
    elseif ($app['status'] == 'under_review') $reviewApps++;
    elseif ($app['status'] == 'approved') $approvedApps++;
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recruitment Dashboard | ΤΕΠΑΚ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #ece4da; }
        .topbar { background: white; border-bottom: 1px solid #c9b5a5; height: 64px; position: fixed; top: 0; left: 0; right: 0; z-index: 100; display: flex; align-items: center; justify-content: space-between; padding: 0 28px; }
        .topbar-logo { color: #1b4f78; font-weight: 700; font-size: 1.15rem; }
        .topbar-logo span { color: #7a4f2e; font-weight: 400; }
        .topbar-right { display: flex; align-items: center; gap: 10px; }
        .user-badge { background: #e4d0bf; padding: 7px 16px; border-radius: 40px; color: #3d2510; font-size: 13px; }
        .logout-btn { background: #e4d0bf; padding: 7px 16px; border-radius: 40px; text-decoration: none; color: #3d2510; font-size: 13px; transition: 0.15s; }
        .logout-btn:hover { background: #d9c4b2; }
        .sidebar { width: 250px; background: white; border-right: 1px solid #c9b5a5; height: calc(100vh - 64px); position: fixed; left: 0; top: 64px; overflow-y: auto; }
        .sidebar-nav { padding: 12px 0; }
        .sidebar-nav a { display: flex; align-items: center; gap: 11px; padding: 11px 22px; color: #5a5a5a; text-decoration: none; margin: 2px 10px; border-radius: 10px; font-size: 13.5px; font-weight: 500; transition: 0.15s; }
        .sidebar-nav a i { width: 18px; font-size: 15px; flex-shrink: 0; }
        .sidebar-nav a:hover { background: #f4f1ec; color: #1b4f78; }
        .sidebar-nav a.active { background: #1b4f78; color: white; }
        .main-content { margin-left: 250px; margin-top: 64px; padding: 28px 32px; min-height: calc(100vh - 64px); }
        .welcome-banner { background: linear-gradient(135deg, #1b4f78 0%, #1e4668 100%); border-radius: 20px; padding: 30px; margin-bottom: 30px; color: white; }
        .welcome-banner h2 { font-size: 1.6rem; margin-bottom: 8px; }
        .welcome-banner p { opacity: 0.9; margin: 0; }
        .stats-row { display: flex; gap: 20px; margin-bottom: 35px; flex-wrap: wrap; }
        .stat-card { background: white; border-radius: 16px; padding: 20px; flex: 1; min-width: 140px; text-align: center; border: 1px solid #c9b5a5; transition: 0.2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .stat-number { font-size: 32px; font-weight: 700; color: #1b4f78; }
        .stat-label { font-size: 12px; color: #6e4e3a; margin-top: 5px; text-transform: uppercase; letter-spacing: 0.5px; }
        .action-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; margin-bottom: 35px; }
        .action-card { background: white; border-radius: 20px; padding: 30px 25px; text-align: center; border: 1px solid #c9b5a5; cursor: pointer; transition: 0.3s; }
        .action-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.08); }
        .action-card i { font-size: 48px; color: #7a4f2e; margin-bottom: 15px; }
        .action-card h3 { font-size: 1.2rem; font-weight: 600; color: #2c2c2c; margin-bottom: 10px; }
        .action-card p { font-size: 13px; color: #6e4e3a; margin-bottom: 20px; line-height: 1.5; }
        .action-btn { background: #e4d0bf; padding: 10px 25px; border-radius: 40px; font-size: 13px; font-weight: 500; color: #3d2510; display: inline-block; }
        .recent-table-container { background: white; border-radius: 20px; border: 1px solid #c9b5a5; overflow: hidden; }
        .table-title { padding: 18px 24px; border-bottom: 1px solid #c9b5a5; background: #efe6db; }
        .table-title h4 { margin: 0; font-size: 1rem; font-weight: 600; color: #2c2c2c; }
        .table-title h4 i { color: #7a4f2e; margin-right: 8px; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 14px 20px; background: #f9f8f6; font-weight: 600; font-size: 12px; color: #6e4e3a; border-bottom: 1px solid #c9b5a5; }
        .data-table td { padding: 14px 20px; border-bottom: 1px solid #f0ece7; font-size: 13px; color: #3a3a3a; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 30px; font-size: 11px; font-weight: 600; }
        .badge-draft { background: #f0f0f0; color: #666; }
        .badge-pending { background: #fff3e0; color: #e67e22; }
        .badge-review { background: #e3f2fd; color: #1b4f78; }
        .badge-approved { background: #e8f5e9; color: #4caf50; }
        .progress { width: 100px; height: 6px; background: #c9b5a5; border-radius: 3px; overflow: hidden; }
        .progress-bar { background: #1b4f78; height: 100%; border-radius: 3px; }
        .footer { text-align: center; padding: 25px; border-top: 1px solid #c9b5a5; color: #6e4e3a; font-size: 12px; margin-top: 30px; }
        @media (max-width: 900px) { .action-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 16px; } .sidebar { display: none; } .stats-row { flex-direction: column; } }
    </style>
</head>
<body>

<div class="topbar">
    <div class="topbar-logo"><i class="fas fa-graduation-cap"></i> ΤΕΠΑΚ <span>| Recruitment Module</span></div>
    <div class="topbar-right">
        <span class="user-badge"><i class="fas fa-user-circle"></i> <?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['username'] ?? 'Χρήστης')) ?></span>
        <a href="../auth/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Αποσύνδεση</a>
    </div>
</div>

<div class="sidebar">
    <div class="sidebar-nav">
        <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="my_profile.php"><i class="fas fa-user"></i> My Profile</a>
        <?php if ($_SESSION['role'] === 'candidate'): ?>
        <a href="my_applications.php"><i class="fas fa-file-alt"></i> My Applications</a>
        <a href="application_status.php"><i class="fas fa-chart-line"></i> Κατάσταση Αιτήσεων</a>
        <?php elseif ($_SESSION['role'] === 'evaluator'): ?>
        <a href="application_status.php"><i class="fas fa-star"></i> Αξιολόγηση Αιτήσεων</a>
        <?php elseif ($_SESSION['role'] === 'hr'): ?>
        <a href="application_status.php"><i class="fas fa-chart-line"></i> Κατάσταση Αιτήσεων</a>
        <a href="hr_dashboard.php"><i class="fas fa-clipboard-check"></i> Διαχείριση Αιτήσεων</a>
        <?php endif; ?>
    </div>
</div>

<div class="main-content">

    <div class="welcome-banner">
        <h2>Καλωσήρθατε, <?= htmlspecialchars($user['first_name'] ?? $user['username'] ?? 'Χρήστη') ?>!</h2>
        <p>Σύστημα Διαχείρισης Ειδικών Επιστημόνων — Τεχνολογικό Πανεπιστήμιο Κύπρου</p>
    </div>

    <?php if ($_SESSION['role'] === 'candidate'): ?>
    <div class="stats-row">
        <div class="stat-card"><div class="stat-number"><?= $totalApps ?></div><div class="stat-label">Σύνολο Αιτήσεων</div></div>
        <div class="stat-card"><div class="stat-number"><?= $draftApps ?></div><div class="stat-label">Προσχέδια</div></div>
        <div class="stat-card"><div class="stat-number"><?= $pendingApps ?></div><div class="stat-label">Σε Εξέλιξη</div></div>
        <div class="stat-card"><div class="stat-number"><?= $reviewApps ?></div><div class="stat-label">Υπό Αξιολόγηση</div></div>
        <div class="stat-card"><div class="stat-number"><?= $approvedApps ?></div><div class="stat-label">Εγκεκριμένες</div></div>
    </div>
    <div class="action-grid">
        <div class="action-card" onclick="location.href='my_profile.php'"><i class="fas fa-id-card"></i><h3>My Profile</h3><p>Διαχειριστείτε τα προσωπικά σας στοιχεία και αλλάξτε κωδικό</p><span class="action-btn">Μετάβαση →</span></div>
        <div class="action-card" onclick="location.href='my_applications.php'"><i class="fas fa-file-signature"></i><h3>My Applications</h3><p>Υποβολή και διαχείριση αιτήσεων για θέσεις ΕΕ</p><span class="action-btn">Μετάβαση →</span></div>
        <div class="action-card" onclick="location.href='application_status.php'"><i class="fas fa-chart-line"></i><h3>Κατάσταση Αιτήσεων</h3><p>Παρακολούθηση της πορείας των αιτήσεών σας</p><span class="action-btn">Μετάβαση →</span></div>
    </div>
    <?php elseif ($_SESSION['role'] === 'evaluator'): ?>
    <div class="action-grid">
        <div class="action-card" onclick="location.href='my_profile.php'"><i class="fas fa-id-card"></i><h3>My Profile</h3><p>Διαχείριση προσωπικών στοιχείων και κωδικού</p><span class="action-btn">Μετάβαση →</span></div>
        <div class="action-card" onclick="location.href='application_status.php'"><i class="fas fa-star" style="color:#7a4f2e"></i><h3>Αξιολόγηση Αιτήσεων</h3><p>Αξιολογήστε τις αιτήσεις που σας έχουν ανατεθεί</p><span class="action-btn">Μετάβαση →</span></div>
    </div>
    <?php else: // hr ?>
    <div class="action-grid">
        <div class="action-card" onclick="location.href='my_profile.php'"><i class="fas fa-id-card"></i><h3>My Profile</h3><p>Διαχείριση προσωπικών στοιχείων και κωδικού</p><span class="action-btn">Μετάβαση →</span></div>
        <div class="action-card" onclick="location.href='application_status.php'"><i class="fas fa-chart-line"></i><h3>Κατάσταση Αιτήσεων</h3><p>Επισκόπηση όλων των αιτήσεων υποψηφίων</p><span class="action-btn">Μετάβαση →</span></div>
        <div class="action-card" onclick="location.href='hr_dashboard.php'"><i class="fas fa-clipboard-check" style="color:#1b4f78"></i><h3>Διαχείριση Αιτήσεων</h3><p>Έγκριση, απόρριψη και αξιολόγηση αιτήσεων υποψηφίων</p><span class="action-btn">Μετάβαση →</span></div>
    </div>
    <?php endif; ?>

    <div class="recent-table-container">
        <div class="table-title"><h4><i class="fas fa-history"></i> Πρόσφατες Αιτήσεις</h4></div>
        <?php if (empty($applications)): ?>
            <div style="padding: 40px; text-align: center; color: #6e4e3a;"><i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>Δεν υπάρχουν αιτήσεις</div>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Θέση / Μάθημα</th><th>Τμήμα</th><th>Κατάσταση</th><th>Πρόοδος</th><th>Ημερομηνία</th></tr></thead>
                <tbody>
                    <?php foreach (array_slice($applications, 0, 5) as $app): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars(substr($app['course_name'] ?? $app['course'] ?? 'Αίτηση', 0, 45)) ?></strong></td>
                        <td><?= htmlspecialchars($app['department_name'] ?? $app['department'] ?? '—') ?></td>
                        <td><span class="badge badge-<?= $app['status'] ?>"><?= $app['status'] == 'approved' ? 'Εγκρίθηκε' : ($app['status'] == 'under_review' ? 'Υπό Αξιολόγηση' : ($app['status'] == 'draft' ? 'Προσχέδιο' : 'Σε Εξέλιξη')) ?></span></td>
                        <td><div class="progress"><div class="progress-bar" style="width: <?= $app['completion_percentage'] ?? 0 ?>%"></div></div><small><?= $app['completion_percentage'] ?? 0 ?>%</small></td>
                        <td><?= date('d/m/Y', strtotime($app['created_at'])) ?></td>
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
