<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . "/includes/db.php";

$configRows = $pdo->query("SELECT config_key, config_value FROM system_config WHERE config_key IN ('brand_label', 'theme_logo_url', 'primary_color', 'accent_color', 'background_color', 'footer_text')")->fetchAll();
$config = [];
foreach ($configRows as $row) {
    $config[$row['config_key']] = $row['config_value'];
}
$brandLabel = $config['brand_label'] ?? 'ΤΕΠΑΚ';
$themeLogoUrl = trim($config['theme_logo_url'] ?? '');
$primaryColor = $config['primary_color'] ?? '#1b4f78';
$accentColor = $config['accent_color'] ?? '#7a4f2e';
$backgroundColor = $config['background_color'] ?? '#ece4da';
$footerText = $config['footer_text'] ?? 'Σύστημα Διαχείρισης Ειδικών Επιστημόνων';

$primaryColor = preg_match('/^#[0-9a-fA-F]{6}$/', $primaryColor) ? $primaryColor : '#1b4f78';
$accentColor = preg_match('/^#[0-9a-fA-F]{6}$/', $accentColor) ? $accentColor : '#7a4f2e';
$backgroundColor = preg_match('/^#[0-9a-fA-F]{6}$/', $backgroundColor) ? $backgroundColor : '#ece4da';

$totalUsers        = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalCandidates   = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id = (SELECT id FROM roles WHERE role_name = 'candidate')")->fetchColumn();
$totalApplications = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
$pendingApplications = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'pending'")->fetchColumn();
$totalEE = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id = (SELECT id FROM roles WHERE role_name = 'ee')")->fetchColumn();
$activeMoodleAccess = $pdo->query("SELECT COUNT(*) FROM moodle_integration WHERE access_enabled = 1")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | ΤΕΠΑΚ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --primary: <?= htmlspecialchars($primaryColor) ?>; --accent: <?= htmlspecialchars($accentColor) ?>; --page-bg: <?= htmlspecialchars($backgroundColor) ?>; }
        body { font-family: 'Inter', sans-serif; background: var(--page-bg); }
        .topbar { background: white; border-bottom: 1px solid #c9b5a5; height: 64px; position: fixed; top: 0; left: 0; right: 0; z-index: 100; display: flex; align-items: center; justify-content: space-between; padding: 0 28px; }
        .topbar-logo { color: var(--primary); font-weight: 700; font-size: 1.15rem; display:flex; align-items:center; gap:9px; }
        .topbar-logo img { width: 30px; height: 30px; object-fit: contain; }
        .topbar-logo span { color: var(--accent); font-weight: 400; }
        .topbar-right { display: flex; align-items: center; gap: 10px; }
        .user-badge { background: #e4d0bf; padding: 7px 16px; border-radius: 40px; color: #3d2510; font-size: 13px; }
        .logout-btn { background: #e4d0bf; padding: 7px 16px; border-radius: 40px; text-decoration: none; color: #3d2510; font-size: 13px; transition: 0.15s; }
        .logout-btn:hover { background: #d9c4b2; }
        .sidebar { width: 250px; background: white; border-right: 1px solid #c9b5a5; height: calc(100vh - 64px); position: fixed; left: 0; top: 64px; overflow-y: auto; }
        .sidebar-nav { padding: 12px 0; }
        .sidebar-nav a { display: flex; align-items: center; gap: 11px; padding: 11px 22px; color: #5a5a5a; text-decoration: none; margin: 2px 10px; border-radius: 10px; font-size: 13.5px; font-weight: 500; transition: 0.15s; }
        .sidebar-nav a i { width: 18px; font-size: 15px; flex-shrink: 0; }
        .sidebar-nav a:hover { background: #f4f1ec; color: var(--primary); }
        .sidebar-nav a.active { background: var(--primary); color: white; }
        .nav-desc { font-size: 11px; font-weight: 400; opacity: 0.7; display: block; margin-top: 2px; line-height: 1.3; }
        .nav-section-label { font-size: 10px; font-weight: 700; color: #a08070; text-transform: uppercase; letter-spacing: 1px; padding: 10px 22px 4px; margin-top: 6px; }
        .main-content { margin-left: 250px; margin-top: 64px; padding: 28px 32px; min-height: calc(100vh - 64px); }
        .welcome-banner { background: var(--primary); border-radius: 20px; padding: 28px 32px; margin-bottom: 28px; color: white; }
        .welcome-banner h2 { font-size: 1.5rem; margin-bottom: 6px; }
        .welcome-banner p { opacity: 0.85; margin: 0; font-size: 14px; }
        .stats-row { display: flex; gap: 16px; margin-bottom: 28px; flex-wrap: wrap; }
        .stat-card { background: white; border-radius: 16px; padding: 20px; flex: 1; min-width: 140px; text-align: center; border: 1px solid #c9b5a5; }
        .stat-number { font-size: 30px; font-weight: 700; color: var(--primary); }
        .stat-label { font-size: 11px; color: #6e4e3a; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
        .action-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .action-card { background: white; border-radius: 20px; padding: 28px; text-align: center; border: 1px solid #c9b5a5; cursor: pointer; transition: 0.25s; text-decoration: none; display: block; }
        .action-card:hover { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(0,0,0,0.07); }
        .action-card .icon { font-size: 40px; color: var(--accent); margin-bottom: 14px; }
        .action-card h3 { font-size: 1.1rem; font-weight: 600; color: #2c2c2c; margin-bottom: 8px; }
        .action-card p { font-size: 13px; color: #6e4e3a; margin: 0; line-height: 1.5; }
        .module-access { margin: 28px 0 20px; background: white; border: 1px solid #c9b5a5; border-radius: 16px; padding: 22px 24px; display: flex; justify-content: space-between; align-items: center; gap: 18px; flex-wrap: wrap; }
        .module-access h3 { margin: 0 0 5px; font-size: 1.05rem; color: #2c2c2c; }
        .module-access p { margin: 0; color: #6e4e3a; font-size: 13px; }
        .module-btn { background: var(--primary); color: white; text-decoration: none; padding: 10px 20px; border-radius: 40px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; }
        .module-btn:hover { color: white; filter: brightness(0.95); }
        .footer { text-align: center; padding: 24px; color: #6e4e3a; font-size: 12px; margin-top: 8px; }
        @media (max-width: 768px) { .action-grid { grid-template-columns: 1fr; } .stats-row { flex-direction: column; } }
    </style>
</head>
<body>
<div class="topbar">
    <div class="topbar-logo">
        <?php if ($themeLogoUrl): ?><img src="<?= htmlspecialchars($themeLogoUrl) ?>" alt="Logo"><?php else: ?><i class="fas fa-graduation-cap"></i><?php endif; ?>
        <?= htmlspecialchars($brandLabel) ?> <span>| Admin Module</span>
    </div>
    <div class="topbar-right">
        <span class="user-badge"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?></span>
        <a href="../auth/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Αποσύνδεση</a>
    </div>
</div>
<div class="sidebar">
    <div class="sidebar-nav">
        <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="../users.php"><i class="fas fa-users"></i> Διαχείριση Χρηστών</a>
        <a href="../recruitment.php"><i class="fas fa-bullhorn"></i> Διαχείριση Προκηρύξεων</a>
        <a href="../system.php"><i class="fas fa-cog"></i> Ρυθμίσεις Συστήματος</a>
        <a href="../reports.php"><i class="fas fa-chart-bar"></i> Αναφορές</a>
        <span class="nav-section-label">Enrollment</span>
        <a href="../dashboard.php"><i class="fas fa-chalkboard-teacher"></i> Enrollment Dashboard</a>
        <a href="../lms_sync.php"><i class="fas fa-sync-alt"></i> LMS Sync</a>
        <a href="../full_sync.php"><i class="fas fa-database"></i> Full Sync</a>
    </div>
</div>
<div class="main-content">
    <div class="welcome-banner">
        <h2>Καλωσήρθατε, <?= htmlspecialchars($_SESSION['username']) ?>!</h2>
        <p>Πίνακας Διαχείρισης — Σύστημα Διαχείρισης Ειδικών Επιστημόνων ΤΕΠΑΚ</p>
    </div>

    <div class="stats-row">
        <div class="stat-card"><div class="stat-number"><?= $totalUsers ?></div><div class="stat-label">Σύνολο Χρηστών</div></div>
        <div class="stat-card"><div class="stat-number"><?= $totalCandidates ?></div><div class="stat-label">Υποψήφιοι</div></div>
        <div class="stat-card"><div class="stat-number"><?= $totalApplications ?></div><div class="stat-label">Σύνολο Αιτήσεων</div></div>
        <div class="stat-card"><div class="stat-number"><?= $pendingApplications ?></div><div class="stat-label">Εκκρεμείς Αιτήσεις</div></div>
        <div class="stat-card"><div class="stat-number"><?= $totalEE ?></div><div class="stat-label">Ειδικοί Επιστήμονες</div></div>
        <div class="stat-card"><div class="stat-number"><?= $activeMoodleAccess ?></div><div class="stat-label">Ενεργές LMS Προσβάσεις</div></div>
    </div>

    <div class="action-grid">
        <a href="../users.php" class="action-card">
            <div class="icon"><i class="fas fa-users"></i></div>
            <h3>Manage Users</h3>
            <p>Προβολή, επεξεργασία, διαγραφή χρηστών και ανάθεση ρόλων</p>
        </a>
        <a href="../recruitment.php" class="action-card">
            <div class="icon"><i class="fas fa-bullhorn"></i></div>
            <h3>Manage Recruitment</h3>
            <p>Διαχείριση προκηρύξεων, περιόδων, σχολών, τμημάτων και μαθημάτων</p>
        </a>
        <a href="../system.php" class="action-card">
            <div class="icon"><i class="fas fa-cog"></i></div>
            <h3>Configure System</h3>
            <p>Ρυθμίσεις θέματος, στοιχεία σύνδεσης με Moodle και παράμετροι συστήματος</p>
        </a>
        <a href="../reports.php" class="action-card">
            <div class="icon"><i class="fas fa-chart-bar"></i></div>
            <h3>Report</h3>
            <p>Στατιστικά αιτήσεων ανά μάθημα, ανά περίοδο και συγκεντρωτικά</p>
        </a>
    </div>

    <div class="module-access">
        <div>
            <h3><i class="fas fa-chalkboard-teacher" style="color:<?= htmlspecialchars($accentColor) ?>; margin-right:8px;"></i> Enrollment Module</h3>
            <p>Άμεση πρόσβαση του admin σε LMS Sync, Full Sync και αναφορές πρόσβασης Moodle.</p>
        </div>
        <a href="../dashboard.php" class="module-btn"><i class="fas fa-arrow-right"></i> Άνοιγμα Enrollment</a>
    </div>

    <div class="footer"><p>© <?= date('Y') ?> Τεχνολογικό Πανεπιστήμιο Κύπρου — <?= htmlspecialchars($footerText) ?></p></div>
</div>
</body>
</html>
