<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

if (!in_array($_SESSION['role'], ['admin', 'hr', 'ee'])) {
    header("Location: index.php");
    exit;
}

require_once __DIR__ . "/includes/db.php";

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get role name for this user
$roleStmt = $pdo->prepare("SELECT r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
$roleStmt->execute([$_SESSION['user_id']]);
$userRole = $roleStmt->fetchColumn();

if ($_SESSION['role'] === 'ee') {
    // EE only sees their own data
    $eeCourses = $pdo->prepare("
        SELECT mi.*, c.course_name, c.course_code, d.dept_name_el, f.faculty_name_el
        FROM moodle_integration mi
        JOIN courses c ON mi.course_id = c.id
        LEFT JOIN departments d ON c.department_id = d.id
        LEFT JOIN faculties f ON d.faculty_id = f.id
        WHERE mi.user_id = ?
        ORDER BY mi.last_sync DESC
    ");
    $eeCourses->execute([$_SESSION['user_id']]);
    $eeCourses = $eeCourses->fetchAll();

    $moodleUrl = $pdo->query("SELECT config_value FROM system_config WHERE config_key = 'moodle_url'")->fetchColumn();
} else {
    // HR / Admin statistics
    $totalEE = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id = (SELECT id FROM roles WHERE role_name = 'ee')")->fetchColumn();
    $totalCourses = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
    $moodleSynced = $pdo->query("SELECT COUNT(*) FROM moodle_integration WHERE moodle_enrolled = 1")->fetchColumn();
    $pendingSync = $pdo->query("SELECT COUNT(*) FROM moodle_integration WHERE moodle_enrolled = 0")->fetchColumn();
    $activeEE = $pdo->query("
        SELECT COUNT(*) FROM users u
        JOIN moodle_integration mi ON u.id = mi.user_id
        WHERE u.role_id = (SELECT id FROM roles WHERE role_name = 'ee') AND mi.access_enabled = 1
    ")->fetchColumn();

    $recentSync = $pdo->query("
        SELECT mi.*, u.first_name, u.last_name, u.email, c.course_name
        FROM moodle_integration mi
        JOIN users u ON mi.user_id = u.id
        JOIN courses c ON mi.course_id = c.id
        ORDER BY mi.last_sync DESC LIMIT 5
    ")->fetchAll();
}
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
        .nav-desc { font-size: 11px; font-weight: 400; opacity: 0.7; display: block; margin-top: 2px; line-height: 1.3; }
        .nav-section-label { font-size: 10px; font-weight: 700; color: #a08070; text-transform: uppercase; letter-spacing: 1px; padding: 10px 22px 4px; margin-top: 6px; display: block; }
        .main-content { margin-left: 250px; margin-top: 64px; padding: 28px 32px; min-height: calc(100vh - 64px); }
        .welcome-banner { background: linear-gradient(135deg, #1b4f78 0%, #1e4668 100%); border-radius: 20px; padding: 30px; margin-bottom: 30px; color: white; }
        .welcome-banner h2 { font-size: 1.6rem; margin-bottom: 8px; }
        .welcome-banner p { opacity: 0.9; margin: 0; }
        .stats-row { display: flex; gap: 20px; margin-bottom: 35px; flex-wrap: wrap; }
        .stat-card { background: white; border-radius: 16px; padding: 20px; flex: 1; min-width: 160px; text-align: center; border: 1px solid #c9b5a5; transition: 0.2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .stat-number { font-size: 32px; font-weight: 700; color: #1b4f78; }
        .stat-label { font-size: 12px; color: #6e4e3a; margin-top: 5px; text-transform: uppercase; letter-spacing: 0.5px; }
        .action-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; margin-bottom: 35px; }
        .action-card { background: white; border-radius: 20px; padding: 30px 25px; text-align: center; border: 1px solid #c9b5a5; cursor: pointer; transition: 0.3s; }
        .action-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.08); border-color: #c9b5a5; }
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
        .badge-success { background: #e8f5e9; color: #4caf50; }
        .badge-warning { background: #fff3e0; color: #e67e22; }
        .badge-info { background: #e3f2fd; color: #1b4f78; }
        .footer { text-align: center; padding: 25px; border-top: 1px solid #c9b5a5; color: #6e4e3a; font-size: 12px; margin-top: 30px; }
        @media (max-width: 900px) { .action-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .stats-row { flex-direction: column; } .container { padding: 16px; } }
    </style>
</head>
<body>
<div class="topbar">
    <div class="topbar-logo"><i class="fas fa-graduation-cap"></i> ΤΕΠΑΚ <span>| Enrollment Module</span></div>
    <div class="topbar-right">
        <span class="user-badge"><i class="fas fa-user-circle"></i> <?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['username'] ?? 'Χρήστης')) ?></span>
        <a href="auth/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Αποσύνδεση</a>
    </div>
</div>
<?php if ($_SESSION['role'] === 'ee'): ?>
<div class="sidebar">
    <div class="sidebar-nav">
        <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="enrollment/my_profile.php"><i class="fas fa-user"></i> Το Προφίλ μου</a>
        <a href="lms_sync.php"><i class="fas fa-sync-alt"></i> LMS Sync</a>
        <a href="full_sync.php"><i class="fas fa-exchange-alt"></i> Full Sync</a>
        <a href="reports.php"><i class="fas fa-chart-bar"></i> Αναφορές</a>
    </div>
</div>
<?php elseif ($_SESSION['role'] === 'admin'): ?>
<div class="sidebar">
    <div class="sidebar-nav">
        <a href="admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Admin Dashboard</a>
        <a href="users.php"><i class="fas fa-users"></i> Διαχείριση Χρηστών</a>
        <a href="recruitment.php"><i class="fas fa-bullhorn"></i> Διαχείριση Προκηρύξεων</a>
        <a href="system.php"><i class="fas fa-cog"></i> Ρυθμίσεις Συστήματος</a>
        <a href="reports.php"><i class="fas fa-chart-bar"></i> Αναφορές</a>
        <span class="nav-section-label">Enrollment</span>
        <a href="dashboard.php" class="active"><i class="fas fa-chalkboard-teacher"></i> Enrollment Dashboard</a>
        <a href="lms_sync.php"><i class="fas fa-sync-alt"></i> LMS Sync</a>
        <a href="full_sync.php"><i class="fas fa-database"></i> Full Sync</a>
    </div>
</div>
<?php else: ?>
<div class="sidebar">
    <div class="sidebar-nav">
        <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="lms_sync.php"><i class="fas fa-sync-alt"></i> LMS Sync</a>
        <a href="full_sync.php"><i class="fas fa-exchange-alt"></i> Full Sync</a>
        <a href="reports.php"><i class="fas fa-chart-bar"></i> Αναφορές</a>
    </div>
</div>
<?php endif; ?>
<div class="main-content">

<?php if ($_SESSION['role'] === 'ee'): ?>

        <div class="welcome-banner">
            <h2>Καλωσήρθατε, <?= htmlspecialchars($user['first_name'] ?? $user['username'] ?? 'Χρήστη') ?>!</h2>
            <p>Σύστημα Διαχείρισης Ειδικών Επιστημόνων — Τεχνολογικό Πανεπιστήμιο Κύπρου</p>
        </div>

        <div class="action-grid">
            <div class="action-card" onclick="location.href='lms_sync.php'">
                <i class="fas fa-sync-alt"></i>
                <h3>LMS Sync</h3>
                <p>Ελέγξτε την πρόσβασή σας στο Moodle και δείτε τα μαθήματά σας</p>
                <span class="action-btn">Μετάβαση →</span>
            </div>
            <div class="action-card" onclick="location.href='full_sync.php'">
                <i class="fas fa-database"></i>
                <h3>Full Sync</h3>
                <p>Πλήρης συγχρονισμός δεδομένων πρόσβασης με το σύστημα</p>
                <span class="action-btn">Μετάβαση →</span>
            </div>
            <div class="action-card" onclick="location.href='reports.php'">
                <i class="fas fa-chart-pie"></i>
                <h3>Αναφορές</h3>
                <p>Προβολή στατιστικών και αναφορών πρόσβασης LMS</p>
                <span class="action-btn">Μετάβαση →</span>
            </div>
        </div>

<?php else: ?>

        <div class="welcome-banner">
            <h2>Καλωσήρθατε, <?= htmlspecialchars($user['first_name'] ?? $user['username'] ?? 'Χρήστη') ?>!</h2>
            <p>Σύστημα Διαχείρισης Εγγραφών και Συγχρονισμού με LMS Moodle — Τεχνολογικό Πανεπιστήμιο Κύπρου</p>
        </div>

        <div class="stats-row">
            <div class="stat-card"><div class="stat-number"><?= $totalEE ?></div><div class="stat-label">Ειδικοί Επιστήμονες</div></div>
            <div class="stat-card"><div class="stat-number"><?= $totalCourses ?></div><div class="stat-label">Σύνολο Μαθημάτων</div></div>
            <div class="stat-card"><div class="stat-number"><?= $moodleSynced ?></div><div class="stat-label">Συγχρονισμένοι</div></div>
            <div class="stat-card"><div class="stat-number"><?= $pendingSync ?></div><div class="stat-label">Εκκρεμείς Συγχρονισμοί</div></div>
            <div class="stat-card"><div class="stat-number"><?= $activeEE ?></div><div class="stat-label">Ενεργές Προσβάσεις</div></div>
        </div>

        <div class="action-grid">
            <div class="action-card" onclick="location.href='lms_sync.php'"><i class="fas fa-sync-alt"></i><h3>LMS Sync</h3><p>Ανάθεση μαθημάτων σε ΕΕ και διαχείριση πρόσβασης στο Moodle</p><span class="action-btn">Μετάβαση →</span></div>
            <div class="action-card" onclick="location.href='full_sync.php'"><i class="fas fa-database"></i><h3>Full Sync</h3><p>Πλήρης συγχρονισμός χρηστών και ρυθμίσεις αυτόματου συγχρονισμού</p><span class="action-btn">Μετάβαση →</span></div>
            <div class="action-card" onclick="location.href='reports.php'"><i class="fas fa-chart-pie"></i><h3>Αναφορές</h3><p>Στατιστικά πρόσβασης στο Moodle και αναφορές ΕΕ</p><span class="action-btn">Μετάβαση →</span></div>
        </div>

        <div class="recent-table-container">
            <div class="table-title"><h4><i class="fas fa-history"></i> Πρόσφατες Δραστηριότητες Συγχρονισμού</h4></div>
            <?php if (empty($recentSync)): ?>
                <div style="padding: 40px; text-align: center; color: #6e4e3a;"><i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>Δεν υπάρχουν πρόσφατες δραστηριότητες</div>
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

<?php endif; ?>

        <div class="footer"><p>© <?= date('Y') ?> Τεχνολογικό Πανεπιστήμιο Κύπρου — Σύστημα Διαχείρισης Ειδικών Επιστημόνων</p></div>
    </div>
</div>
</body>
