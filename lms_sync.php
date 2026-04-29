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

$success = $error = '';

// EE-only: load their own course access data
if ($_SESSION['role'] === 'ee') {
    $moodleUrl = $pdo->query("SELECT config_value FROM system_config WHERE config_key = 'moodle_url'")->fetchColumn();
    $eeAccessStmt = $pdo->prepare("
        SELECT mi.*, c.course_name, c.course_code, d.dept_name_el, f.faculty_name_el
        FROM moodle_integration mi
        JOIN courses c ON mi.course_id = c.id
        LEFT JOIN departments d ON c.department_id = d.id
        LEFT JOIN faculties f ON d.faculty_id = f.id
        WHERE mi.user_id = ?
        ORDER BY mi.last_sync DESC
    ");
    $eeAccessStmt->execute([$_SESSION['user_id']]);
    $eeAccess = $eeAccessStmt->fetchAll();
}

// Handle sync action (HR/admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['role'] !== 'ee') {
    if (isset($_POST['enable_access'])) {
        $stmt = $pdo->prepare("UPDATE moodle_integration SET access_enabled = 1, last_sync = NOW() WHERE id = ?");
        $stmt->execute([$_POST['sync_id']]);
        $success = "Η πρόσβαση ενεργοποιήθηκε επιτυχώς!";
    } elseif (isset($_POST['disable_access'])) {
        $stmt = $pdo->prepare("UPDATE moodle_integration SET access_enabled = 0, last_sync = NOW() WHERE id = ?");
        $stmt->execute([$_POST['sync_id']]);
        $success = "Η πρόσβαση απενεργοποιήθηκε.";
    } elseif (isset($_POST['sync_now'])) {
        $stmt = $pdo->prepare("UPDATE moodle_integration SET moodle_enrolled = 1, last_sync = NOW() WHERE id = ?");
        $stmt->execute([$_POST['sync_id']]);
        $success = "Ο συγχρονισμός ολοκληρώθηκε!";
    } elseif (isset($_POST['assign_course'])) {
        $user_id = $_POST['user_id'];
        $course_id = $_POST['course_id'];
        
        $check = $pdo->prepare("SELECT id FROM moodle_integration WHERE user_id = ? AND course_id = ?");
        $check->execute([$user_id, $course_id]);
        
        if (!$check->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO moodle_integration (user_id, course_id, moodle_enrolled, access_enabled, last_sync) VALUES (?, ?, 1, 1, NOW())");
            $stmt->execute([$user_id, $course_id]);
            $success = "Δόθηκε πρόσβαση σε άλλο μάθημα για τον Ειδικό Επιστήμονα.";
        } else {
            $error = "Το μάθημα έχει ήδη ανατεθεί.";
        }
    }
}

$eeSearch = trim($_GET['ee_search'] ?? '');
$eeAccess = $_GET['ee_access'] ?? '';
$eeOrder = in_array($_GET['ee_order'] ?? '', ['last_name', 'first_name', 'email'], true) ? $_GET['ee_order'] : 'last_name';
$eeDir = ($_GET['ee_dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

$professors = $syncStatus = $courses = $faculties = $departmentsByFaculty = $coursesByDepartment = [];

if ($_SESSION['role'] !== 'ee') {
$eeWhere = ["r.role_name IN ('ee', 'candidate')"];
$eeParams = [];
if ($eeSearch !== '') {
    $eeWhere[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
    $like = '%' . $eeSearch . '%';
    $eeParams = array_merge($eeParams, [$like, $like, $like, $like]);
}
$eeWhereSQL = 'WHERE ' . implode(' AND ', $eeWhere);

$profStmt = $pdo->prepare("
    SELECT DISTINCT u.id, u.first_name, u.last_name, u.email, u.phone, u.username, r.role_name
    FROM users u
    JOIN roles r ON u.role_id = r.id
    $eeWhereSQL
    ORDER BY u.$eeOrder $eeDir
");
$profStmt->execute($eeParams);
$professors = $profStmt->fetchAll();

// Apply access filter after fetching sync status (done below)
$filterByAccess = $eeAccess;

// Get all courses with department info
$courses = $pdo->query("
    SELECT c.*, 
           d.id as department_id,
           d.dept_name,
           d.dept_name_el,
           f.id as faculty_id,
           f.faculty_name,
           f.faculty_name_el
    FROM courses c
    LEFT JOIN departments d ON c.department_id = d.id
    LEFT JOIN faculties f ON d.faculty_id = f.id
    ORDER BY f.faculty_name_el, d.dept_name_el, c.course_code
")->fetchAll();

// Get all faculties with their departments
$faculties = $pdo->query("
    SELECT f.*, 
           (SELECT COUNT(*) FROM departments WHERE faculty_id = f.id) as department_count
    FROM faculties f
    ORDER BY f.faculty_name_el
")->fetchAll();

// Get departments by faculty
$departmentsByFaculty = [];
foreach ($faculties as $fac) {
    $depts = $pdo->prepare("
        SELECT d.*, (SELECT COUNT(*) FROM courses WHERE department_id = d.id) as course_count
        FROM departments d
        WHERE d.faculty_id = ?
        ORDER BY d.dept_name_el
    ");
    $depts->execute([$fac['id']]);
    $departmentsByFaculty[$fac['id']] = $depts->fetchAll();
}

// Get courses by department
$coursesByDepartment = [];
foreach ($departmentsByFaculty as $facId => $depts) {
    foreach ($depts as $dept) {
        $crs = $pdo->prepare("
            SELECT c.* FROM courses c WHERE c.department_id = ? ORDER BY c.course_code
        ");
        $crs->execute([$dept['id']]);
        $coursesByDepartment[$dept['id']] = $crs->fetchAll();
    }
}

// Get moodle sync status for all professors
$syncStatus = [];
foreach ($professors as $prof) {
    $stmt = $pdo->prepare("
        SELECT mi.*, c.course_name, c.course_code, d.dept_name, d.dept_name_el
        FROM moodle_integration mi
        JOIN courses c ON mi.course_id = c.id
        LEFT JOIN departments d ON c.department_id = d.id
        WHERE mi.user_id = ?
    ");
    $stmt->execute([$prof['id']]);
    $syncStatus[$prof['id']] = $stmt->fetchAll();
}
} // end if role !== ee
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Αναθέσεις Μαθημάτων | ΤΕΠΑΚ</title>
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
        .content-card { background: white; border-radius: 20px; border: 1px solid #c9b5a5; overflow: hidden; margin-bottom: 30px; }
        .card-header { padding: 20px 28px; border-bottom: 1px solid #c9b5a5; background: #efe6db; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .card-header h3 { margin: 0; font-size: 1.1rem; font-weight: 600; color: #2c2c2c; }
        .card-header h3 i { color: #7a4f2e; margin-right: 8px; }
        .card-body { padding: 28px; }
        .faculty-section { margin-bottom: 30px; }
        .faculty-title { background: #1b4f78; color: white; padding: 12px 20px; border-radius: 12px; margin-bottom: 15px; font-weight: 600; }
        .department-box { background: #efe6db; border-radius: 12px; padding: 15px; margin-bottom: 15px; border-left: 4px solid #7a4f2e; }
        .professor-card { background: white; border-radius: 12px; padding: 15px; margin-bottom: 12px; border: 1px solid #c9b5a5; transition: 0.2s; }
        .professor-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .course-badge { display: inline-block; background: #e4d0bf; padding: 4px 10px; border-radius: 15px; font-size: 11px; margin: 3px; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 30px; font-size: 11px; font-weight: 600; }
        .badge-success { background: #e8f5e9; color: #4caf50; }
        .badge-warning { background: #fff3e0; color: #e67e22; }
        .badge-danger { background: #ffebee; color: #dc3545; }
        .badge-info { background: #e3f2fd; color: #1b4f78; }
        .btn-sm { background: #e4d0bf; border: none; padding: 5px 12px; border-radius: 20px; font-size: 11px; cursor: pointer; transition: 0.2s; margin: 2px; }
        .btn-sm:hover { background: #e0cfc0; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #3d2510; font-size: 13px; }
        .form-group select { width: 100%; padding: 12px 16px; border: 1px solid #e2dcd5; border-radius: 30px; font-family: 'Inter', sans-serif; }
        .stats-row { display: flex; gap: 20px; margin-bottom: 25px; flex-wrap: wrap; }
        .stat-box { background: white; border-radius: 16px; padding: 15px 20px; flex: 1; text-align: center; border: 1px solid #c9b5a5; }
        .stat-number { font-size: 28px; font-weight: 700; color: #1b4f78; }
        .stat-label { font-size: 11px; color: #6e4e3a; }
        .alert-success { background: #e8f5e9; border-left: 4px solid #4caf50; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; color: #2e7d32; }
        .alert-error { background: #ffebee; border-left: 4px solid #dc3545; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; color: #c62828; }
        .footer { text-align: center; padding: 25px; border-top: 1px solid #c9b5a5; color: #6e4e3a; font-size: 12px; margin-top: 30px; }
        @media (max-width: 768px) { .container { padding: 16px; } }
    </style>
    <script>
        function loadDepartments(facultyId) {
            const departmentSelect = document.getElementById('department_id');
            const courseSelect = document.getElementById('course_id');
            
            departmentSelect.innerHTML = '<option value="">Επιλέξτε Τμήμα</option>';
            courseSelect.innerHTML = '<option value="">Πρώτα επιλέξτε τμήμα</option>';
            
            if (facultyId) {
                fetch(`ajax_get_departments.php?faculty_id=${facultyId}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(dept => {
                            const option = document.createElement('option');
                            option.value = dept.id;
                            option.textContent = dept.dept_name_el || dept.dept_name;
                            departmentSelect.appendChild(option);
                        });
                    })
                    .catch(error => console.error('Error:', error));
            }
        }
        
        function loadCourses(departmentId) {
            const courseSelect = document.getElementById('course_id');
            courseSelect.innerHTML = '<option value="">Επιλέξτε Μάθημα</option>';
            
            if (departmentId) {
                fetch(`ajax_get_courses.php?department_id=${departmentId}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(course => {
                            const option = document.createElement('option');
                            option.value = course.id;
                            option.textContent = `${course.course_code} - ${course.course_name}`;
                            courseSelect.appendChild(option);
                        });
                    })
                    .catch(error => console.error('Error:', error));
            }
        }
    </script>
</head>
<body>
<div class="topbar">
    <div class="topbar-logo"><i class="fas fa-graduation-cap"></i> ΤΕΠΑΚ <span>| <?= $_SESSION['role'] === 'admin' ? 'Admin Module' : 'Enrollment Module' ?></span></div>
    <div class="topbar-right">
        <span class="user-badge"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?></span>
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
        <a href="reports.php"><i class="fas fa-chart-bar"></i> Αναφορές</a>
        <span class="nav-section-label">Enrollment</span>
        <a href="dashboard.php"><i class="fas fa-chalkboard-teacher"></i> Enrollment Dashboard</a>
        <a href="lms_sync.php" class="active"><i class="fas fa-sync-alt"></i> LMS Sync</a>
        <a href="full_sync.php"><i class="fas fa-database"></i> Full Sync</a>
    </div>
</div>
<?php elseif ($_SESSION['role'] === 'ee'): ?>
<div class="sidebar">
    <div class="sidebar-nav">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="enrollment/my_profile.php"><i class="fas fa-user"></i> Το Προφίλ μου</a>
        <a href="lms_sync.php" class="active"><i class="fas fa-sync-alt"></i> LMS Sync</a>
        <a href="full_sync.php"><i class="fas fa-exchange-alt"></i> Full Sync</a>
        <a href="reports.php"><i class="fas fa-chart-bar"></i> Αναφορές</a>
    </div>
</div>
<?php else: ?>
<div class="sidebar">
    <div class="sidebar-nav">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="lms_sync.php" class="active"><i class="fas fa-sync-alt"></i> LMS Sync</a>
        <a href="full_sync.php"><i class="fas fa-exchange-alt"></i> Full Sync</a>
        <a href="reports.php"><i class="fas fa-chart-bar"></i> Αναφορές</a>
    </div>
</div>
<?php endif; ?>
<div class="main-content">

<?php if ($_SESSION['role'] === 'ee'): ?>

        <!-- EE Personal LMS View -->
        <div style="margin-bottom: 24px;">
            <h2 style="font-size:1.6rem; font-weight:600; color:#2c2c2c;">LMS Sync — Η Πρόσβασή σας στο Moodle</h2>
            <p style="color:#6e4e3a; margin-top:4px; font-size:14px;">Εδώ μπορείτε να ελέγξετε την κατάσταση πρόσβασής σας στην πλατφόρμα Moodle του ΤΕΠΑΚ.</p>
        </div>

        <?php if ($moodleUrl): ?>
        <div style="background:#e8f4fd; border-left:4px solid #1b4f78; border-radius:12px; padding:16px 20px; margin-bottom:24px; display:flex; align-items:center; gap:14px;">
            <i class="fas fa-link" style="color:#1b4f78; font-size:20px; flex-shrink:0;"></i>
            <div>
                <strong style="color:#1b4f78;">Σύνδεσμος Moodle:</strong>
                <a href="<?= htmlspecialchars($moodleUrl) ?>" target="_blank" style="color:#1b4f78; margin-left:8px;"><?= htmlspecialchars($moodleUrl) ?> <i class="fas fa-external-link-alt" style="font-size:11px;"></i></a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($eeAccess)): ?>
            <div class="content-card">
                <div class="card-body" style="text-align:center; padding:50px 20px; color:#6e4e3a;">
                    <i class="fas fa-inbox" style="font-size:48px; opacity:0.4; display:block; margin-bottom:16px;"></i>
                    <p>Δεν έχουν ανατεθεί μαθήματα στον λογαριασμό σας ακόμη.</p>
                    <p style="font-size:13px; margin-top:6px;">Επικοινωνήστε με το HR για να ολοκληρωθεί η ανάθεση.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($eeAccess as $course): ?>
            <div class="content-card" style="margin-bottom:16px;">
                <div class="card-body" style="padding:20px 28px;">
                    <div style="display:flex; justify-content:space-between; align-items:start; flex-wrap:wrap; gap:12px;">
                        <div>
                            <div style="font-weight:600; font-size:1rem; color:#2c2c2c;">
                                <?= htmlspecialchars($course['course_code'] ?? '') ?> — <?= htmlspecialchars($course['course_name']) ?>
                            </div>
                            <?php if ($course['dept_name_el'] ?? null): ?>
                            <div style="color:#6e4e3a; font-size:13px; margin-top:3px;">
                                <i class="fas fa-building"></i> <?= htmlspecialchars($course['dept_name_el']) ?>
                                <?php if ($course['faculty_name_el'] ?? null): ?>
                                 · <?= htmlspecialchars($course['faculty_name_el']) ?>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                            <?php if ($course['moodle_enrolled']): ?>
                                <span class="badge badge-success"><i class="fas fa-check"></i> Εγγεγραμμένος στο Moodle</span>
                            <?php else: ?>
                                <span class="badge badge-warning"><i class="fas fa-clock"></i> Εκκρεμεί συγχρονισμός</span>
                            <?php endif; ?>
                            <?php if ($course['access_enabled']): ?>
                                <span class="badge badge-success"><i class="fas fa-unlock"></i> Πρόσβαση Ενεργή</span>
                            <?php else: ?>
                                <span class="badge badge-danger"><i class="fas fa-lock"></i> Πρόσβαση Ανενεργή</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($course['last_sync']): ?>
                    <div style="margin-top:10px; font-size:12px; color:#8a7060;">
                        <i class="fas fa-clock"></i> Τελευταίος συγχρονισμός: <?= date('d/m/Y H:i', strtotime($course['last_sync'])) ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($course['access_enabled'] && $moodleUrl): ?>
                    <div style="margin-top:12px;">
                        <a href="<?= htmlspecialchars($moodleUrl) ?>" target="_blank"
                           style="display:inline-flex; align-items:center; gap:8px; background:#1b4f78; color:white; padding:8px 20px; border-radius:30px; text-decoration:none; font-size:13px; font-weight:500;">
                            <i class="fas fa-sign-in-alt"></i> Είσοδος στο Moodle
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

<?php else: ?>

        <?php if ($success): ?>
            <div class="alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
        <?php endif; ?>

        <!-- Info Box -->
        <div class="content-card" style="margin-bottom: 24px;">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> Αναθέσεις Μαθημάτων & LMS Συγχρονισμός</h3>
            </div>
            <div class="card-body">
                <p style="color:#3d2510; font-size:14px; line-height:1.75; margin-bottom:10px;">
                    Σε αυτή τη σελίδα μπορείτε να <strong>αναθέσετε μαθήματα σε Ειδικούς Επιστήμονες (ΕΕ)</strong> και να διαχειριστείτε την πρόσβασή τους στο <strong>LMS (Moodle)</strong> του ΤΕΠΑΚ.
                </p>
                <ul style="color:#3d2510; font-size:14px; line-height:1.9; margin-left:20px;">
                    <li>Επιλέξτε Σχολή → Τμήμα → Μάθημα → Ειδικό Επιστήμονα για να κάνετε ανάθεση</li>
                    <li>Μετά την ανάθεση, ο ΕΕ εμφανίζεται στον πίνακα με την κατάσταση συγχρονισμού</li>
                    <li>Πατήστε <strong>Συγχρονισμός</strong> για να επισημανθεί το μάθημα ως συγχρονισμένο με το Moodle</li>
                    <li>Πατήστε <strong>Ενεργοποίηση / Απενεργοποίηση</strong> για να ελέγξετε την πρόσβαση στο LMS</li>
                </ul>
                <div style="background:#e8f4fd; border-left:4px solid #1b4f78; border-radius:8px; padding:12px 16px; margin-top:14px; font-size:13px; color:#1b4f78;">
                    <i class="fas fa-circle-info"></i> <strong>Σχετικά με τον LMS Συγχρονισμό:</strong>
                    Το Moodle είναι η πλατφόρμα e-learning του ΤΕΠΑΚ. Στο παρόν σύστημα ο συγχρονισμός είναι <em>προσομοιωμένος</em> —
                    δεν απαιτείται ζωντανή σύνδεση με Moodle server. Τα δεδομένα (enrolled, access_enabled) αποθηκεύονται τοπικά στη βάση και αναπαριστούν την κατάσταση που θα υπήρχε σε πραγματικό περιβάλλον.
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-box"><div class="stat-number"><?= count($professors) ?></div><div class="stat-label">Ειδικοί Επιστήμονες</div></div>
            <div class="stat-box"><div class="stat-number"><?= count($courses) ?></div><div class="stat-label">Σύνολο Μαθημάτων</div></div>
            <div class="stat-box"><div class="stat-number"><?= count($faculties) ?></div><div class="stat-label">Σχολές ΤΕΠΑΚ</div></div>
        </div>
        
        <!-- Assign New Course -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-plus-circle"></i> Πρόσβαση ΕΕ σε άλλο μάθημα</h3>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>1. Σχολή</label>
                            <select id="faculty_id" class="form-control" onchange="loadDepartments(this.value)">
                                <option value="">Επιλέξτε Σχολή</option>
                                <?php foreach ($faculties as $fac): ?>
                                    <option value="<?= $fac['id'] ?>"><?= htmlspecialchars($fac['faculty_name_el'] ?? $fac['faculty_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>2. Τμήμα</label>
                            <select id="department_id" class="form-control" onchange="loadCourses(this.value)">
                                <option value="">Επιλέξτε Τμήμα</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>3. Μάθημα</label>
                            <select name="course_id" id="course_id" required class="form-control">
                                <option value="">Επιλέξτε Μάθημα</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>4. Επιστήμονας</label>
                            <select name="user_id" required class="form-control">
                                <option value="">Επιλέξτε Επιστήμονα</option>
                                <?php foreach ($professors as $prof): ?>
                                    <option value="<?= $prof['id'] ?>"><?= htmlspecialchars($prof['first_name'] . ' ' . $prof['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <button type="submit" name="assign_course" class="btn-sm" style="padding: 12px; width: 100%; background: #1b4f78; color: white;">Παροχή Πρόσβασης σε Μάθημα</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Faculty-Department-Course Hierarchy -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-sitemap"></i> Ιεραρχία Σχολών - Τμημάτων - Μαθημάτων</h3>
            </div>
            <div class="card-body">
                <?php foreach ($faculties as $fac): ?>
                    <div class="faculty-section">
                        <div class="faculty-title">
                            <i class="fas fa-university"></i> <?= htmlspecialchars($fac['faculty_name_el'] ?? $fac['faculty_name']) ?>
                        </div>
                        <?php if (isset($departmentsByFaculty[$fac['id']])): ?>
                            <?php foreach ($departmentsByFaculty[$fac['id']] as $dept): ?>
                                <div class="department-box">
                                    <strong><i class="fas fa-building"></i> <?= htmlspecialchars($dept['dept_name_el'] ?? $dept['dept_name']) ?></strong>
                                    <div style="margin-top: 10px;">
                                        <?php if (isset($coursesByDepartment[$dept['id']])): ?>
                                            <?php foreach ($coursesByDepartment[$dept['id']] as $course): ?>
                                                <span class="course-badge">
                                                    <?= htmlspecialchars($course['course_code'] ?? '') ?> - <?= htmlspecialchars($course['course_name'] ?? '') ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Professors and Their Assigned Courses -->
        <div class="content-card">
            <div class="card-header" style="flex-direction:column; align-items:stretch; gap:12px;">
                <h3><i class="fas fa-chalkboard-user"></i> Ειδικοί Επιστήμονες και Ανατεθειμένα Μαθήματα
                    <span class="badge badge-info ms-2" style="font-size:0.75rem;"><?= count($professors) ?></span>
                </h3>
                <form method="GET" action="lms_sync.php" style="display:flex; gap:8px; flex-wrap:wrap;">
                    <input type="text" name="ee_search" value="<?= htmlspecialchars($eeSearch) ?>"
                           placeholder="Αναζήτηση ονόματος, email..."
                           style="flex:1; min-width:180px; padding:8px 14px; border:1px solid #c9b5a5; border-radius:10px; font-family:inherit; font-size:13px;">
                    <select name="ee_access" style="padding:8px 12px; border:1px solid #c9b5a5; border-radius:10px; font-size:13px; background:white;">
                        <option value="">Όλες οι προσβάσεις</option>
                        <option value="active" <?= $eeAccess === 'active' ? 'selected' : '' ?>>Ενεργή Πρόσβαση</option>
                        <option value="inactive" <?= $eeAccess === 'inactive' ? 'selected' : '' ?>>Ανενεργή Πρόσβαση</option>
                        <option value="none" <?= $eeAccess === 'none' ? 'selected' : '' ?>>Χωρίς Ανάθεση</option>
                    </select>
                    <select name="ee_order" style="padding:8px 12px; border:1px solid #c9b5a5; border-radius:10px; font-size:13px; background:white;">
                        <option value="last_name" <?= $eeOrder === 'last_name' ? 'selected' : '' ?>>Ταξινόμηση: Επώνυμο</option>
                        <option value="first_name" <?= $eeOrder === 'first_name' ? 'selected' : '' ?>>Ταξινόμηση: Όνομα</option>
                        <option value="email" <?= $eeOrder === 'email' ? 'selected' : '' ?>>Ταξινόμηση: Email</option>
                    </select>
                    <select name="ee_dir" style="padding:8px 12px; border:1px solid #c9b5a5; border-radius:10px; font-size:13px; background:white;">
                        <option value="asc" <?= $eeDir === 'ASC' ? 'selected' : '' ?>>Αύξουσα</option>
                        <option value="desc" <?= $eeDir === 'DESC' ? 'selected' : '' ?>>Φθίνουσα</option>
                    </select>
                    <button type="submit" style="background:#1b4f78; color:white; border:none; padding:8px 16px; border-radius:10px; cursor:pointer; font-size:13px;"><i class="fas fa-search"></i></button>
                    <?php if ($eeSearch || $eeAccess): ?>
                    <a href="lms_sync.php" style="background:#e4d0bf; color:#3d2510; border:none; padding:8px 14px; border-radius:10px; font-size:13px; text-decoration:none;"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="card-body">
                <?php
                // Apply access filter
                $displayProfessors = $professors;
                if ($filterByAccess !== '') {
                    $displayProfessors = array_filter($professors, function($prof) use ($syncStatus, $filterByAccess) {
                        $courses = $syncStatus[$prof['id']] ?? [];
                        if ($filterByAccess === 'none') return empty($courses);
                        if ($filterByAccess === 'active') return !empty(array_filter($courses, fn($c) => $c['access_enabled']));
                        if ($filterByAccess === 'inactive') return !empty($courses) && empty(array_filter($courses, fn($c) => $c['access_enabled']));
                        return true;
                    });
                }
                ?>
                <?php foreach ($displayProfessors as $prof): ?>
                    <?php $profCourses = $syncStatus[$prof['id']] ?? []; ?>
                    <div class="professor-card">
                        <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap;">
                            <div>
                                <strong><?= htmlspecialchars($prof['first_name'] . ' ' . $prof['last_name']) ?></strong><br>
                                <small><i class="fas fa-envelope"></i> <?= htmlspecialchars($prof['email']) ?></small>
                            </div>
                            <div>
                                <span class="badge badge-info"><?= count($profCourses) ?> Μαθήματα</span>
                                <span class="badge badge-success"><?= htmlspecialchars($prof['role_name'] ?? 'ΕΕ') ?></span>
                            </div>
                        </div>
                        <div style="margin-top: 12px;">
                            <strong><i class="fas fa-graduation-cap"></i> Ανατεθειμένα Μαθήματα:</strong>
                            <div style="margin-top: 8px;">
                                <?php if (empty($profCourses)): ?>
                                    <span class="badge badge-warning">Δεν έχουν ανατεθεί μαθήματα</span>
                                <?php else: ?>
                                    <?php foreach ($profCourses as $course): ?>
                                        <div class="course-badge">
                                            <strong><?= htmlspecialchars($course['course_code'] ?? '') ?></strong> <?= htmlspecialchars($course['course_name'] ?? '') ?>
                                            <small>(<?= htmlspecialchars($course['dept_name_el'] ?? $course['dept_name'] ?? '—') ?>)</small>
                                            <?php if ($course['moodle_enrolled']): ?>
                                                <span class="badge badge-success">Συγχρονισμένο</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">Εκκρεμεί</span>
                                            <?php endif; ?>
                                            <?php if ($course['access_enabled']): ?>
                                                <span class="badge badge-success">Πρόσβαση ✓</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Πρόσβαση ✗</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="margin-top: 12px; text-align: right;">
                            <?php foreach ($profCourses as $course): ?>
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="sync_id" value="<?= $course['id'] ?>">
                                    <?php if (!$course['moodle_enrolled']): ?>
                                        <button type="submit" name="sync_now" class="btn-sm" style="background: #e8f5e9; color: #4caf50;"><i class="fas fa-sync"></i> Συγχρονισμός</button>
                                    <?php endif; ?>
                                    <?php if ($course['access_enabled']): ?>
                                        <button type="submit" name="disable_access" class="btn-sm" style="background: #ffebee; color: #dc3545;"><i class="fas fa-ban"></i> Απενεργοποίηση</button>
                                    <?php else: ?>
                                        <button type="submit" name="enable_access" class="btn-sm" style="background: #e8f5e9;"><i class="fas fa-check"></i> Ενεργοποίηση</button>
                                    <?php endif; ?>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="footer">
            <p>© <?= date('Y') ?> Τεχνολογικό Πανεπιστήμιο Κύπρου — Σύστημα Διαχείρισης Ειδικών Επιστημόνων</p>
        </div>

<?php endif; // end ee vs hr/admin view ?>

    </div>
</div>
</body>
