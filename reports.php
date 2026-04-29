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

if ($_SESSION['role'] === 'admin') {
    // Admin: application statistics
    $appTotal = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
    $appPending = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'pending'")->fetchColumn();
    $appDraft = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'draft'")->fetchColumn();
    $appReview = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'under_review'")->fetchColumn();
    $appApproved = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'approved'")->fetchColumn();
    $appRejected = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'rejected'")->fetchColumn();

    $appsByCourse = $pdo->query("
        SELECT course_name, COUNT(*) as total,
            SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status='under_review' THEN 1 ELSE 0 END) as under_review,
            SUM(CASE WHEN status IN ('pending','draft') THEN 1 ELSE 0 END) as pending
        FROM applications
        WHERE course_name IS NOT NULL AND course_name != ''
        GROUP BY course_name
        ORDER BY total DESC
        LIMIT 20
    ")->fetchAll();

    $appsByAnnouncement = $pdo->query("
        SELECT COALESCE(ann.title_el, 'Χωρίς συνδεδεμένη προκήρυξη') AS announcement_title, COUNT(a.id) as total,
            SUM(CASE WHEN a.status='approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN a.status='rejected' THEN 1 ELSE 0 END) as rejected
        FROM applications a
        LEFT JOIN announcements ann ON a.announcement_id = ann.id
        GROUP BY ann.id, ann.title_el
        ORDER BY total DESC
    ")->fetchAll();

    $appsByDept = $pdo->query("
        SELECT department_name, COUNT(*) as total,
            SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved
        FROM applications
        WHERE department_name IS NOT NULL AND department_name != ''
        GROUP BY department_name
        ORDER BY total DESC
    ")->fetchAll();

    $appsByMonth = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total
        FROM applications
        GROUP BY month
        ORDER BY month DESC
        LIMIT 12
    ")->fetchAll();
} else {
    // HR: LMS statistics
    $totalEE = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id = (SELECT id FROM roles WHERE role_name = 'ee')")->fetchColumn();
    $totalCourses = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
    $totalAssignments = $pdo->query("SELECT COUNT(*) FROM moodle_integration")->fetchColumn();
    $syncedAssignments = $pdo->query("SELECT COUNT(*) FROM moodle_integration WHERE moodle_enrolled = 1")->fetchColumn();
    $pendingAssignments = $totalAssignments - $syncedAssignments;

    $coursesWithoutProf = $pdo->query("
        SELECT c.*, d.dept_name_el
        FROM courses c
        LEFT JOIN moodle_integration mi ON c.id = mi.course_id
        LEFT JOIN departments d ON c.department_id = d.id
        WHERE mi.id IS NULL
    ")->fetchAll();

    $eeWithoutCourses = $pdo->query("
        SELECT u.*
        FROM users u
        LEFT JOIN moodle_integration mi ON u.id = mi.user_id
        WHERE u.role_id = (SELECT id FROM roles WHERE role_name = 'ee')
        AND mi.id IS NULL
    ")->fetchAll();

    $topProfessors = $pdo->query("
        SELECT u.first_name, u.last_name, COUNT(mi.course_id) as course_count
        FROM moodle_integration mi
        JOIN users u ON mi.user_id = u.id
        WHERE u.role_id = (SELECT id FROM roles WHERE role_name = 'ee')
        GROUP BY u.id
        ORDER BY course_count DESC
        LIMIT 10
    ")->fetchAll();

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
}
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
        .content-card { background: white; border-radius: 20px; border: 1px solid #c9b5a5; overflow: hidden; margin-bottom: 30px; }
        .card-header { padding: 20px 28px; border-bottom: 1px solid #c9b5a5; background: #efe6db; }
        .card-header h3 { margin: 0; font-size: 1.1rem; font-weight: 600; color: #2c2c2c; }
        .card-header h3 i { color: #7a4f2e; margin-right: 8px; }
        .card-body { padding: 28px; }
        .stats-row { display: flex; gap: 20px; margin-bottom: 25px; flex-wrap: wrap; }
        .stat-box { background: white; border-radius: 16px; padding: 20px; flex: 1; text-align: center; border: 1px solid #c9b5a5; }
        .stat-number { font-size: 32px; font-weight: 700; color: #1b4f78; }
        .stat-label { font-size: 12px; color: #6e4e3a; margin-top: 5px; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 12px 15px; background: #f9f8f6; font-weight: 600; font-size: 12px; border-bottom: 1px solid #c9b5a5; }
        .data-table td { padding: 12px 15px; border-bottom: 1px solid #f0ece7; font-size: 13px; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 30px; font-size: 11px; font-weight: 600; }
        .badge-success { background: #e8f5e9; color: #4caf50; }
        .badge-warning { background: #fff3e0; color: #e67e22; }
        .badge-danger { background: #ffebee; color: #dc3545; }
        .progress { height: 8px; border-radius: 10px; }
        .btn-print { background: #e4d0bf; border: none; padding: 10px 20px; border-radius: 40px; cursor: pointer; }
        .footer { text-align: center; padding: 25px; border-top: 1px solid #c9b5a5; color: #6e4e3a; font-size: 12px; margin-top: 30px; }
        @media (max-width: 768px) { .container { padding: 16px; } .stats-row { flex-direction: column; } }
    </style>
</head>
<body>
<div class="topbar">
    <div class="topbar-logo"><i class="fas fa-graduation-cap"></i> ΤΕΠΑΚ <span>| <?= $_SESSION['role'] === 'admin' ? 'Admin Module' : 'Enrollment Module' ?></span></div>
    <div class="topbar-right">
        <span class="user-badge"><i class="fas fa-user-circle"></i> <?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['username'] ?? 'Χρήστης')) ?></span>
        <a href="auth/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Αποσύνδεση</a>
    </div>
</div>
<?php if ($_SESSION['role'] === 'admin'): ?>
<div class="sidebar">
    <div class="sidebar-nav">
        <a href="admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="users.php"><i class="fas fa-users"></i> Διαχείριση Χρηστών</a>
        <a href="recruitment.php"><i class="fas fa-bullhorn"></i> Διαχείριση Προκηρύξεων</a>
        <a href="system.php"><i class="fas fa-cog"></i> Ρυθμίσεις Συστήματος</a>
        <a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> Αναφορές</a>
        <span class="nav-section-label">Enrollment</span>
        <a href="dashboard.php"><i class="fas fa-chalkboard-teacher"></i> Enrollment Dashboard</a>
        <a href="lms_sync.php"><i class="fas fa-sync-alt"></i> LMS Sync</a>
        <a href="full_sync.php"><i class="fas fa-database"></i> Full Sync</a>
    </div>
</div>
<?php else: ?>
<div class="sidebar">
    <div class="sidebar-nav">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <?php if ($_SESSION['role'] === 'ee'): ?>
        <a href="enrollment/my_profile.php"><i class="fas fa-user"></i> Το Προφίλ μου</a>
        <?php endif; ?>
        <a href="lms_sync.php"><i class="fas fa-sync-alt"></i> LMS Sync</a>
        <a href="full_sync.php"><i class="fas fa-exchange-alt"></i> Full Sync</a>
        <a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> Αναφορές</a>
    </div>
</div>
<?php endif; ?>
<div class="main-content">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 style="color: #6c4f3a;"><i class="fas fa-chart-pie"></i> Αναφορές & Στατιστικά</h2>
            <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Εκτύπωση</button>
        </div>

<?php if ($_SESSION['role'] === 'admin'): ?>

        <!-- Admin: Application Statistics -->
        <div class="stats-row">
            <div class="stat-box"><div class="stat-number"><?= $appTotal ?></div><div class="stat-label">Σύνολο Αιτήσεων</div></div>
            <div class="stat-box"><div class="stat-number"><?= $appPending + $appDraft ?></div><div class="stat-label">Εκκρεμείς</div></div>
            <div class="stat-box"><div class="stat-number"><?= $appReview ?></div><div class="stat-label">Υπό Αξιολόγηση</div></div>
            <div class="stat-box"><div class="stat-number"><?= $appApproved ?></div><div class="stat-label">Εγκεκριμένες</div></div>
            <div class="stat-box"><div class="stat-number"><?= $appRejected ?></div><div class="stat-label">Απορριφθείσες</div></div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="content-card">
                    <div class="card-header"><h3><i class="fas fa-chart-pie"></i> Κατανομή Αιτήσεων ανά Κατάσταση</h3></div>
                    <div class="card-body">
                        <canvas id="appStatusChart" height="220"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="content-card">
                    <div class="card-header"><h3><i class="fas fa-calendar-alt"></i> Αιτήσεις ανά Μήνα</h3></div>
                    <div class="card-body">
                        <canvas id="appMonthChart" height="220"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header"><h3><i class="fas fa-book"></i> Αιτήσεις ανά Μάθημα</h3></div>
            <div class="card-body">
                <?php if (empty($appsByCourse)): ?>
                    <p class="text-center py-3" style="color:#6e4e3a">Δεν υπάρχουν δεδομένα</p>
                <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Μάθημα</th><th>Σύνολο</th><th>Εκκρεμείς</th><th>Υπό Αξιολόγηση</th><th>Εγκεκριμένες</th><th>Απορριφθείσες</th></tr></thead>
                    <tbody>
                        <?php foreach ($appsByCourse as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['course_name']) ?></td>
                            <td><strong><?= $row['total'] ?></strong></td>
                            <td><?= $row['pending'] ?></td>
                            <td><?= $row['under_review'] ?></td>
                            <td><span class="badge badge-success"><?= $row['approved'] ?></span></td>
                            <td><span class="badge badge-danger"><?= $row['rejected'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="content-card">
                    <div class="card-header"><h3><i class="fas fa-bullhorn"></i> Αιτήσεις ανά Προκήρυξη</h3></div>
                    <div class="card-body">
                        <?php if (empty($appsByAnnouncement)): ?>
                            <p class="text-center py-3" style="color:#6e4e3a">Δεν υπάρχουν δεδομένα</p>
                        <?php else: ?>
                        <table class="data-table">
                            <thead><tr><th>Προκήρυξη</th><th>Σύνολο</th><th>Εγκεκριμένες</th><th>Απορριφθείσες</th></tr></thead>
                            <tbody>
                                <?php foreach ($appsByAnnouncement as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['announcement_title']) ?></td>
                                    <td><?= $row['total'] ?></td>
                                    <td><?= $row['approved'] ?></td>
                                    <td><?= $row['rejected'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="content-card">
                    <div class="card-header"><h3><i class="fas fa-building"></i> Αιτήσεις ανά Τμήμα</h3></div>
                    <div class="card-body">
                        <?php if (empty($appsByDept)): ?>
                            <p class="text-center py-3" style="color:#6e4e3a">Δεν υπάρχουν δεδομένα</p>
                        <?php else: ?>
                        <table class="data-table">
                            <thead><tr><th>Τμήμα</th><th>Σύνολο</th><th>Εγκεκριμένες</th></tr></thead>
                            <tbody>
                                <?php foreach ($appsByDept as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['department_name']) ?></td>
                                    <td><?= $row['total'] ?></td>
                                    <td><?= $row['approved'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

<?php else: ?>

        <!-- HR: LMS Statistics -->
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
                                <tr><td><?= htmlspecialchars(($prof['first_name'] ?? '') . ' ' . ($prof['last_name'] ?? '')) ?></td><td><?= $prof['course_count'] ?></td></tr>
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
                                    <tr><td><?= htmlspecialchars($course['course_name'] ?? '—') ?></td><td><?= htmlspecialchars($course['dept_name_el'] ?? '—') ?></td></tr>
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
                                    <tr><td><?= htmlspecialchars(($ee['first_name'] ?? '') . ' ' . ($ee['last_name'] ?? '')) ?></td><td><?= htmlspecialchars($ee['email'] ?? '') ?></td></tr>
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

<?php endif; ?>

        <div class="footer"><p>© <?= date('Y') ?> Τεχνολογικό Πανεπιστήμιο Κύπρου</p></div>
    </div>

    <script>
    <?php if ($_SESSION['role'] === 'admin'): ?>
        new Chart(document.getElementById('appStatusChart'), {
            type: 'doughnut',
            data: {
                labels: ['Εκκρεμείς', 'Υπό Αξιολόγηση', 'Εγκεκριμένες', 'Απορριφθείσες'],
                datasets: [{ data: [<?= ($appPending + $appDraft) ?>, <?= $appReview ?>, <?= $appApproved ?>, <?= $appRejected ?>], backgroundColor: ['#e67e22','#1b4f78','#4caf50','#dc3545'] }]
            }
        });
        new Chart(document.getElementById('appMonthChart'), {
            type: 'bar',
            data: {
                labels: [<?= implode(',', array_map(fn($r) => '"' . $r['month'] . '"', array_reverse($appsByMonth))) ?>],
                datasets: [{ label: 'Αιτήσεις', data: [<?= implode(',', array_map(fn($r) => $r['total'], array_reverse($appsByMonth))) ?>], backgroundColor: '#1b4f78' }]
            },
            options: { plugins: { legend: { display: false } } }
        });
    <?php else: ?>
        new Chart(document.getElementById('syncChart'), {
            type: 'doughnut',
            data: {
                labels: ['Συγχρονισμένες', 'Εκκρεμείς'],
                datasets: [{ data: [<?= $syncedAssignments ?>, <?= $pendingAssignments ?>], backgroundColor: ['#4caf50', '#e67e22'] }]
            }
        });
    <?php endif; ?>
    </script>
</body>
</html>
