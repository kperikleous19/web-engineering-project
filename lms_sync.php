<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (!in_array($_SESSION['role'], ['admin', 'hr'])) {
    header("Location: ../index.php");
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

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$success = $error = '';

// Handle sync action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $stmt = $pdo->prepare("INSERT INTO moodle_integration (user_id, course_id, moodle_enrolled, last_sync) VALUES (?, ?, 1, NOW())");
            $stmt->execute([$user_id, $course_id]);
            $success = "Το μάθημα ανατέθηκε στον Ειδικό Επιστήμονα.";
        } else {
            $error = "Το μάθημα έχει ήδη ανατεθεί.";
        }
    }
}

// Get all professors (EE) - simplified query without department_id
$professors = $pdo->query("
    SELECT DISTINCT u.id, u.first_name, u.last_name, u.email, u.phone, u.username,
           r.role_name
    FROM users u
    JOIN roles r ON u.role_id = r.id
    WHERE r.role_name IN ('ee', 'candidate')
    ORDER BY u.last_name ASC
")->fetchAll();

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
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS Sync | ΤΕΠΑΚ - Ειδικοί Επιστήμονες</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f0eb; }
        .container { max-width: 1400px; margin: 0 auto; padding: 24px; }
        .header { background: white; border-radius: 20px; padding: 20px 30px; margin-bottom: 25px; border: 1px solid #e9dfd7; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .logo h2 { color: #2c5f8a; font-size: 1.4rem; margin: 0; }
        .logo span { color: #8b6b4d; }
        .user-badge { background: #e8ded5; padding: 8px 18px; border-radius: 40px; color: #5a4a40; }
        .logout-btn { background: #e6d9d0; padding: 8px 18px; border-radius: 40px; text-decoration: none; color: #5a4a40; margin-left: 12px; }
        .nav-menu { background: white; border-radius: 50px; padding: 8px 20px; margin-bottom: 30px; display: flex; flex-wrap: wrap; gap: 8px; border: 1px solid #e9dfd7; }
        .nav-menu a { text-decoration: none; color: #8a8a8a; font-weight: 500; padding: 10px 24px; border-radius: 40px; transition: 0.2s; }
        .nav-menu a i { margin-right: 8px; }
        .nav-menu a:hover, .nav-menu a.active { background: #e6d9d0; color: #5a4a40; }
        .content-card { background: white; border-radius: 20px; border: 1px solid #e9dfd7; overflow: hidden; margin-bottom: 30px; }
        .card-header { padding: 20px 28px; border-bottom: 1px solid #e9dfd7; background: #faf9f7; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .card-header h3 { margin: 0; font-size: 1.1rem; font-weight: 600; color: #2c2c2c; }
        .card-header h3 i { color: #8b6b4d; margin-right: 8px; }
        .card-body { padding: 28px; }
        .faculty-section { margin-bottom: 30px; }
        .faculty-title { background: #2c5f8a; color: white; padding: 12px 20px; border-radius: 12px; margin-bottom: 15px; font-weight: 600; }
        .department-box { background: #faf9f7; border-radius: 12px; padding: 15px; margin-bottom: 15px; border-left: 4px solid #8b6b4d; }
        .professor-card { background: white; border-radius: 12px; padding: 15px; margin-bottom: 12px; border: 1px solid #e9dfd7; transition: 0.2s; }
        .professor-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .course-badge { display: inline-block; background: #e6d9d0; padding: 4px 10px; border-radius: 15px; font-size: 11px; margin: 3px; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 30px; font-size: 11px; font-weight: 600; }
        .badge-success { background: #e8f5e9; color: #4caf50; }
        .badge-warning { background: #fff3e0; color: #e67e22; }
        .badge-danger { background: #ffebee; color: #dc3545; }
        .badge-info { background: #e3f2fd; color: #2c5f8a; }
        .btn-sm { background: #e6d9d0; border: none; padding: 5px 12px; border-radius: 20px; font-size: 11px; cursor: pointer; transition: 0.2s; margin: 2px; }
        .btn-sm:hover { background: #dccfc4; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #5a4a40; font-size: 13px; }
        .form-group select { width: 100%; padding: 12px 16px; border: 1px solid #e2dcd5; border-radius: 30px; font-family: 'Inter', sans-serif; }
        .stats-row { display: flex; gap: 20px; margin-bottom: 25px; flex-wrap: wrap; }
        .stat-box { background: white; border-radius: 16px; padding: 15px 20px; flex: 1; text-align: center; border: 1px solid #e9dfd7; }
        .stat-number { font-size: 28px; font-weight: 700; color: #2c5f8a; }
        .stat-label { font-size: 11px; color: #8a7163; }
        .alert-success { background: #e8f5e9; border-left: 4px solid #4caf50; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; color: #2e7d32; }
        .alert-error { background: #ffebee; border-left: 4px solid #dc3545; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; color: #c62828; }
        .footer { text-align: center; padding: 25px; border-top: 1px solid #e9dfd7; color: #8a8a8a; font-size: 12px; margin-top: 30px; }
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
    <div class="container">
        <div class="header">
            <div class="logo"><h2><i class="fas fa-chalkboard-teacher"></i> ΤΕΠΑΚ <span>| Enrollment Module</span></h2></div>
            <div><span class="user-badge"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span> <a href="../auth/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Αποσύνδεση</a></div>
        </div>
        
        <div class="nav-menu">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="lms_sync.php" class="active"><i class="fas fa-sync"></i> LMS Sync</a>
            <a href="full_sync.php"><i class="fas fa-exchange-alt"></i> Full Sync</a>
            <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
        </div>
        
        <?php if ($success): ?>
            <div class="alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-box"><div class="stat-number"><?= count($professors) ?></div><div class="stat-label">Ειδικοί Επιστήμονες</div></div>
            <div class="stat-box"><div class="stat-number"><?= count($courses) ?></div><div class="stat-label">Σύνολο Μαθημάτων</div></div>
            <div class="stat-box"><div class="stat-number"><?= count($faculties) ?></div><div class="stat-label">Σχολές ΤΕΠΑΚ</div></div>
        </div>
        
        <!-- Assign New Course -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-plus-circle"></i> Ανάθεση Μαθήματος σε Ειδικό Επιστήμονα</h3>
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
                        <button type="submit" name="assign_course" class="btn-sm" style="padding: 12px; width: 100%; background: #2c5f8a; color: white;">Ανάθεση Μαθήματος</button>
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
            <div class="card-header">
                <h3><i class="fas fa-chalkboard-user"></i> Ειδικοί Επιστήμονες και Ανατεθειμένα Μαθήματα</h3>
            </div>
            <div class="card-body">
                <?php foreach ($professors as $prof): ?>
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
    </div>
</body>
</html>