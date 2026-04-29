<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$success = $error = '';

// PRG: apply from an open announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_announcement'])) {
    $ann_id = (int) $_POST['announcement_id'];
    $ann = $pdo->prepare("
        SELECT ann.*, c.course_name, d.dept_name_el AS department_name
        FROM announcements ann
        LEFT JOIN courses c ON ann.course_id = c.id
        LEFT JOIN departments d ON c.department_id = d.id
        WHERE ann.id = ? AND ann.application_end >= CURDATE()
    ");
    $ann->execute([$ann_id]);
    $row = $ann->fetch();

    if (!$row) {
        $error = "Η αγγελία δεν βρέθηκε ή έχει λήξει.";
    } else {
        // prevent duplicate application to the same announcement
        $dup = $pdo->prepare("SELECT id FROM applications WHERE user_id = ? AND announcement_id = ?");
        $dup->execute([$_SESSION['user_id'], $ann_id]);
        if ($dup->fetch()) {
            $error = "Έχετε ήδη υποβάλει αίτηση για αυτή τη θέση.";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO applications
                  (user_id, course, department, course_name, department_name, school_name, announcement_id, status, completion_percentage)
                VALUES (?, ?, ?, ?, ?, 'ΤΕΠΑΚ', ?, 'pending', 100)
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $row['course_name'] ?? '',
                $row['department_name'] ?? '',
                $row['course_name'] ?? '',
                $row['department_name'] ?? '',
                $ann_id
            ]);
            header("Location: my_applications.php?msg=applied");
            exit;
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'applied') {
    $success = "Η αίτησή σας υποβλήθηκε επιτυχώς για τη θέση της αγγελίας.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    $course_name = trim($_POST['course_name']);
    $department_name = trim($_POST['department_name']);
    $faculty_name = trim($_POST['faculty_name']);

    if (!empty($_POST['custom_course'])) {
        $course_name = trim($_POST['custom_course']);
    }

    if (empty($course_name)) {
        $error = "Παρακαλώ συμπληρώστε το μάθημα/θέση";
    } else {
        $stmt = $pdo->prepare("INSERT INTO applications (user_id, course, department, course_name, department_name, school_name, status, completion_percentage) VALUES (?, ?, ?, ?, ?, ?, 'pending', 100)");
        if ($stmt->execute([$_SESSION['user_id'], $course_name, $department_name, $course_name, $department_name, $faculty_name])) {
            $success = "Η αίτηση υποβλήθηκε επιτυχώς και είναι διαθέσιμη για έλεγχο από το HR.";
        } else {
            $error = "Σφάλμα κατά τη δημιουργία.";
        }
    }
}

if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM applications WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['delete'], $_SESSION['user_id']]);
    $success = "Η αίτηση διαγράφηκε.";
}

$appSearch   = trim($_GET['app_search'] ?? '');
$appStatus   = $_GET['app_status'] ?? '';
$appOrder    = in_array($_GET['app_order'] ?? '', ['created_at', 'course_name', 'status'], true) ? $_GET['app_order'] : 'created_at';
$appDir      = ($_GET['app_dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

$appConds  = ['a.user_id = ?'];
$appParams = [$_SESSION['user_id']];
if ($appSearch !== '') {
    $appConds[] = "(a.course_name LIKE ? OR a.department_name LIKE ? OR a.course LIKE ? OR a.department LIKE ?)";
    $like = '%' . $appSearch . '%';
    $appParams = array_merge($appParams, [$like, $like, $like, $like]);
}
if ($appStatus !== '') {
    $appConds[] = "a.status = ?";
    $appParams[] = $appStatus;
}
$appWhere = 'WHERE ' . implode(' AND ', $appConds);
$stmt = $pdo->prepare("SELECT a.* FROM applications a $appWhere ORDER BY a.$appOrder $appDir");
$stmt->execute($appParams);
$applications = $stmt->fetchAll();

// Open announcements (active application window)
$openAnn = $pdo->query("
    SELECT ann.*, c.course_name, d.dept_name_el AS department_name
    FROM announcements ann
    LEFT JOIN courses c ON ann.course_id = c.id
    LEFT JOIN departments d ON c.department_id = d.id
    WHERE ann.application_end >= CURDATE()
    ORDER BY ann.application_end ASC
")->fetchAll();

// IDs of announcements this user already applied to
$appliedAnnIds = [];
$appliedCheck = $pdo->prepare("SELECT announcement_id FROM applications WHERE user_id = ? AND announcement_id IS NOT NULL");
$appliedCheck->execute([$_SESSION['user_id']]);
foreach ($appliedCheck->fetchAll() as $r) {
    $appliedAnnIds[] = (int) $r['announcement_id'];
}

$tepak_faculties = [
    [
        'name' => 'Σχολή Μηχανικής και Τεχνολογίας',
        'departments' => [
            'Τμήμα Ηλεκτρολόγων Μηχανικών και Μηχανικών Υπολογιστών και Πληροφορικής',
            'Τμήμα Μηχανολόγων Μηχανικών και Επιστήμης και Μηχανικής Υλικών',
            'Τμήμα Πολιτικών Μηχανικών και Μηχανικών Γεωπληροφορικής'
        ],
        'courses' => [
            'Μηχανική Ιστού (Web Engineering)', 'Τεχνητή Νοημοσύνη', 'Ασφάλεια Υπολογιστών',
            'Δίκτυα Υπολογιστών', 'Βάσεις Δεδομένων', 'Ανάλυση Κυκλωμάτων',
            'Ψηφιακή Επεξεργασία Σήματος', 'Μηχανική Στερεού', 'Θερμοδυναμική',
            'Ανάλυση Κατασκευών', 'Γεωτεχνική Μηχανική'
        ]
    ],
    [
        'name' => 'Σχολή Γεωτεχνικών Επιστημών και Διαχείρισης Περιβάλλοντος',
        'departments' => [
            'Τμήμα Γεωπονικών Επιστημών, Βιοτεχνολογίας και Επιστήμης Τροφίμων',
            'Τμήμα Χημικών Μηχανικών'
        ],
        'courses' => ['Γεωργική Βιοτεχνολογία', 'Επιστήμη Τροφίμων', 'Χημική Μηχανική', 'Περιβαλλοντική Μηχανική']
    ],
    [
        'name' => 'Σχολή Διοίκησης και Οικονομίας',
        'departments' => [
            'Τμήμα Χρηματοοικονομικής, Λογιστικής και Διοικητικής Επιστήμης',
            'Τμήμα Ναυτιλιακών'
        ],
        'courses' => ['Τραπεζική και Χρηματοοικονομικά', 'Λογιστική', 'Διοίκηση Επιχειρήσεων', 'Ναυτιλιακές Σπουδές', 'Ναυτιλιακή Διοίκηση']
    ],
    [
        'name' => 'Σχολή Επικοινωνίας και Μέσων Ενημέρωσης',
        'departments' => [
            'Τμήμα Επικοινωνίας και Σπουδών Διαδικτύου',
            'Τμήμα Πολυμέσων και Γραφικών Τεχνών'
        ],
        'courses' => ['Ψηφιακά Μέσα', 'Τεχνολογίες Διαδικτύου', 'Σχεδιασμός Ιστοσελίδων', 'Γραφικός Σχεδιασμός', 'Τρισδιάστατα Γραφικά', 'Παραγωγή Πολυμέσων']
    ],
    [
        'name' => 'Σχολή Επιστημών Υγείας',
        'departments' => ['Τμήμα Νοσηλευτικής', 'Τμήμα Επιστημών Αποκατάστασης'],
        'courses' => ['Εισαγωγή στη Νοσηλευτική', 'Κλινική Νοσηλευτική', 'Διοίκηση Μονάδων Υγείας', 'Φυσικοθεραπεία', 'Εργοθεραπεία', 'Λογοθεραπεία']
    ],
    [
        'name' => 'Σχολή Καλών και Εφαρμοσμένων Τεχνών',
        'departments' => ['Τμήμα Καλών Τεχνών'],
        'courses' => ['Ιστορία Τέχνης', 'Σχεδιασμός', 'Ψηφιακή Τέχνη', 'Γλυπτική', 'Ζωγραφική']
    ],
    [
        'name' => 'Σχολή Διοίκησης Τουρισμού, Φιλοξενίας και Επιχειρηματικότητας (Πάφος)',
        'departments' => [
            'Τμήμα Διοίκησης Τουρισμού και Φιλοξενίας',
            'Τμήμα Διοίκησης, Επιχειρηματικότητας και Ψηφιακού Επιχειρείν'
        ],
        'courses' => ['Διοίκηση Τουριστικών Επιχειρήσεων', 'Μάρκετινγκ Τουρισμού', 'Hotel Management', 'Επιχειρηματικότητα', 'Ψηφιακό Μάρκετινγκ']
    ]
];
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Applications | ΤΕΠΑΚ</title>
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
        .content-card { background: white; border-radius: 20px; border: 1px solid #c9b5a5; overflow: hidden; margin-bottom: 30px; }
        .card-header { padding: 20px 28px; border-bottom: 1px solid #c9b5a5; background: #efe6db; }
        .card-header h3 { margin: 0; font-size: 1.1rem; font-weight: 600; color: #2c2c2c; }
        .card-header h3 i { color: #7a4f2e; margin-right: 8px; }
        .card-body { padding: 28px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #3d2510; font-size: 13px; }
        .form-group input, .form-group select { width: 100%; padding: 12px 16px; border: 1px solid #e2dcd5; border-radius: 30px; font-family: 'Inter', sans-serif; }
        .btn-primary { background: #1b4f78; border: none; padding: 12px 28px; border-radius: 40px; font-weight: 500; color: white; cursor: pointer; transition: 0.2s; }
        .btn-primary:hover { background: #153d5e; }
        .btn-danger { background: #fdeaea; color: #c62828; padding: 6px 15px; border-radius: 30px; text-decoration: none; font-size: 12px; margin-left: 10px; }
        .application-row { display: flex; justify-content: space-between; align-items: center; padding: 18px 20px; border-bottom: 1px solid #f0ece7; flex-wrap: wrap; gap: 15px; }
        .application-row:last-child { border-bottom: none; }
        .app-title { font-size: 1rem; font-weight: 600; color: #2c2c2c; }
        .app-meta { font-size: 12px; color: #6e4e3a; margin-top: 5px; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 30px; font-size: 11px; font-weight: 600; }
        .badge-draft { background: #f0f0f0; color: #666; }
        .badge-pending { background: #fff3e0; color: #e67e22; }
        .badge-review { background: #e3f2fd; color: #1b4f78; }
        .badge-approved { background: #e8f5e9; color: #4caf50; }
        .progress { width: 100px; height: 6px; background: #c9b5a5; border-radius: 3px; overflow: hidden; }
        .progress-bar { background: #1b4f78; height: 100%; }
        .alert-success { background: #e8f5e9; border-left: 4px solid #4caf50; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; color: #2e7d32; }
        .alert-error { background: #ffebee; border-left: 4px solid #dc3545; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; color: #c62828; }
        .footer { text-align: center; padding: 25px; border-top: 1px solid #c9b5a5; color: #6e4e3a; font-size: 12px; margin-top: 10px; }
        .row-select { display: flex; gap: 20px; flex-wrap: wrap; }
        .ann-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
        .ann-card { border: 1px solid #c9b5a5; border-radius: 14px; padding: 18px 20px; background: #fffdf9; }
        .ann-card h5 { font-size: 0.95rem; font-weight: 600; color: #1b4f78; margin-bottom: 6px; }
        .ann-card .ann-meta { font-size: 12px; color: #6e4e3a; margin-bottom: 12px; }
        .ann-card .deadline { font-size: 11px; background: #fff3e0; color: #b35900; display: inline-block; padding: 3px 10px; border-radius: 20px; margin-bottom: 12px; }
        .btn-apply { background: #1b4f78; color: white; border: none; padding: 8px 20px; border-radius: 30px; font-size: 13px; cursor: pointer; font-family: 'Inter', sans-serif; font-weight: 500; transition: 0.15s; }
        .btn-apply:hover { background: #153d5e; }
        .btn-apply:disabled { background: #a0b4c4; cursor: default; }
        @media (max-width: 768px) { .application-row { flex-direction: column; text-align: center; } .main-content { margin-left: 0; padding: 16px; } .sidebar { display: none; } }
    </style>
    <script>
        const facultyData = <?= json_encode($tepak_faculties) ?>;

        function updateDepartments() {
            const facultySelect = document.getElementById('faculty_name');
            const departmentSelect = document.getElementById('department_name');
            const courseSelect = document.getElementById('course_name');
            departmentSelect.innerHTML = '<option value="">Επιλέξτε Τμήμα</option>';
            courseSelect.innerHTML = '<option value="">Επιλέξτε Μάθημα</option>';
            if (facultySelect.value) {
                const faculty = facultyData.find(f => f.name === facultySelect.value);
                if (faculty) {
                    faculty.departments.forEach(dept => {
                        const opt = document.createElement('option');
                        opt.value = dept; opt.textContent = dept;
                        departmentSelect.appendChild(opt);
                    });
                }
            }
        }

        function updateCourses() {
            const facultySelect = document.getElementById('faculty_name');
            const courseSelect = document.getElementById('course_name');
            courseSelect.innerHTML = '<option value="">Επιλέξτε Μάθημα</option>';
            if (facultySelect.value) {
                const faculty = facultyData.find(f => f.name === facultySelect.value);
                if (faculty && faculty.courses) {
                    faculty.courses.forEach(course => {
                        const opt = document.createElement('option');
                        opt.value = course; opt.textContent = course;
                        courseSelect.appendChild(opt);
                    });
                }
            }
        }

        function toggleCustomCourse() {
            const cb = document.getElementById('customCourse');
            const courseSelect = document.getElementById('course_name');
            const customInput = document.getElementById('custom_course');
            if (cb.checked) {
                courseSelect.style.display = 'none';
                customInput.style.display = 'block';
                customInput.required = true;
                courseSelect.required = false;
            } else {
                courseSelect.style.display = 'block';
                customInput.style.display = 'none';
                customInput.required = false;
                courseSelect.required = true;
            }
        }
    </script>
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
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="my_profile.php"><i class="fas fa-user"></i> My Profile</a>
        <a href="my_applications.php" class="active"><i class="fas fa-file-alt"></i> My Applications</a>
        <a href="application_status.php"><i class="fas fa-chart-line"></i> Κατάσταση Αιτήσεων</a>
    </div>
</div>

<div class="main-content">

    <?php if ($success): ?><div class="alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div><?php endif; ?>

    <?php if (!empty($openAnn)): ?>
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-bullhorn"></i> Ανοιχτές Αγγελίες Θέσεων
                <span style="font-size:12px; font-weight:400; margin-left:10px; color:#6e4e3a;"><?= count($openAnn) ?> διαθέσιμες θέσεις</span>
            </h3>
        </div>
        <div class="card-body">
            <p style="font-size:13px; color:#6e4e3a; margin-bottom:20px;">Οι παρακάτω θέσεις δέχονται αιτήσεις. Κάντε κλικ στο «Αίτηση» για να υποβάλετε αίτηση για μια θέση.</p>
            <div class="ann-grid">
                <?php foreach ($openAnn as $ann): ?>
                <div class="ann-card">
                    <h5><?= htmlspecialchars($ann['title_el'] ?? ($ann['course_name'] ?? 'Θέση Ε.Ε.')) ?></h5>
                    <div class="ann-meta">
                        <?php if ($ann['course_name']): ?><i class="fas fa-book"></i> <?= htmlspecialchars($ann['course_name']) ?><br><?php endif; ?>
                        <?php if ($ann['department_name']): ?><i class="fas fa-building"></i> <?= htmlspecialchars($ann['department_name']) ?><?php endif; ?>
                    </div>
                    <div class="deadline"><i class="fas fa-calendar-alt"></i> Έως <?= date('d/m/Y', strtotime($ann['application_end'])) ?></div><br>
                    <?php if (in_array((int)$ann['id'], $appliedAnnIds)): ?>
                        <button class="btn-apply" disabled><i class="fas fa-check"></i> Έχετε υποβάλει αίτηση</button>
                    <?php else: ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="announcement_id" value="<?= (int)$ann['id'] ?>">
                            <button type="submit" name="apply_announcement" class="btn-apply"><i class="fas fa-paper-plane"></i> Αίτηση</button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="content-card">
        <div class="card-header"><h3><i class="fas fa-plus-circle"></i> Νέα Αίτηση για Ειδικό Επιστήμονα ΤΕΠΑΚ</h3></div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group">
                    <label><i class="fas fa-university"></i> Σχολή ΤΕΠΑΚ *</label>
                    <select name="faculty_name" id="faculty_name" required onchange="updateDepartments(); updateCourses();">
                        <option value="">Επιλέξτε Σχολή</option>
                        <?php foreach ($tepak_faculties as $faculty): ?>
                            <option value="<?= htmlspecialchars($faculty['name']) ?>"><?= htmlspecialchars($faculty['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row-select">
                    <div class="form-group" style="flex: 1;">
                        <label><i class="fas fa-building"></i> Τμήμα *</label>
                        <select name="department_name" id="department_name" required>
                            <option value="">Επιλέξτε Τμήμα</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-book"></i> Μάθημα / Θέση *</label>
                    <select name="course_name" id="course_name" required>
                        <option value="">Επιλέξτε Μάθημα</option>
                    </select>
                    <div style="margin-top: 8px;">
                        <input type="checkbox" id="customCourse" onclick="toggleCustomCourse()">
                        <label for="customCourse" style="font-size: 12px; font-weight: normal;">Δεν βρίσκεται στη λίστα; Εισαγωγή χειροκίνητα</label>
                    </div>
                    <input type="text" name="custom_course" id="custom_course" placeholder="Εισάγετε το μάθημα/θέση" style="display: none; margin-top: 10px;">
                </div>
                <button type="submit" name="submit_application" class="btn-primary"><i class="fas fa-paper-plane"></i> Υποβολή Αίτησης</button>
            </form>
        </div>
    </div>

    <div class="content-card">
        <div class="card-header"><h3><i class="fas fa-list"></i> Οι Αιτήσεις μου</h3></div>
        <form method="GET" style="display:flex; gap:10px; padding:14px 20px; background:#f9f8f6; border-bottom:1px solid #c9b5a5; flex-wrap:wrap;">
            <input type="text" name="app_search" placeholder="Αναζήτηση μαθήματος ή τμήματος..." value="<?= htmlspecialchars($appSearch) ?>" style="flex:1; min-width:180px; padding:9px 14px; border:1px solid #c9b5a5; border-radius:10px; font-family:inherit; font-size:13px;">
            <select name="app_status" style="padding:9px 12px; border:1px solid #c9b5a5; border-radius:10px; font-family:inherit; font-size:13px; background:white;">
                <option value="">Όλες οι καταστάσεις</option>
                <option value="pending"      <?= $appStatus === 'pending'      ? 'selected' : '' ?>>Σε Εξέλιξη</option>
                <option value="under_review" <?= $appStatus === 'under_review' ? 'selected' : '' ?>>Υπό Αξιολόγηση</option>
                <option value="approved"     <?= $appStatus === 'approved'     ? 'selected' : '' ?>>Εγκρίθηκε</option>
                <option value="draft"        <?= $appStatus === 'draft'        ? 'selected' : '' ?>>Προσχέδιο</option>
            </select>
            <select name="app_order" style="padding:9px 12px; border:1px solid #c9b5a5; border-radius:10px; font-family:inherit; font-size:13px; background:white;">
                <option value="created_at"  <?= $appOrder === 'created_at'  ? 'selected' : '' ?>>Ταξινόμηση: Ημερομηνία</option>
                <option value="course_name" <?= $appOrder === 'course_name' ? 'selected' : '' ?>>Ταξινόμηση: Μάθημα</option>
                <option value="status"      <?= $appOrder === 'status'      ? 'selected' : '' ?>>Ταξινόμηση: Κατάσταση</option>
            </select>
            <select name="app_dir" style="padding:9px 12px; border:1px solid #c9b5a5; border-radius:10px; font-family:inherit; font-size:13px; background:white;">
                <option value="desc" <?= $appDir === 'DESC' ? 'selected' : '' ?>>Φθίνουσα</option>
                <option value="asc"  <?= $appDir === 'ASC'  ? 'selected' : '' ?>>Αύξουσα</option>
            </select>
            <button type="submit" style="background:#1b4f78; color:white; border:none; padding:9px 16px; border-radius:10px; font-size:13px; cursor:pointer;"><i class="fas fa-search"></i> Εφαρμογή</button>
            <?php if ($appSearch || $appStatus): ?>
            <a href="my_applications.php" style="background:#e4d0bf; color:#3d2510; border:none; padding:9px 14px; border-radius:10px; font-size:13px; text-decoration:none; display:inline-flex; align-items:center; gap:5px;"><i class="fas fa-times"></i> Καθαρισμός</a>
            <?php endif; ?>
        </form>
        <div style="padding:8px 20px; font-size:12px; color:#6e4e3a; background:#fffdf9; border-bottom:1px solid #f0ece7;">
            <?= count($applications) ?> αίτηση/εις<?= $appSearch ? ' για "' . htmlspecialchars($appSearch) . '"' : '' ?><?= $appStatus ? ' · Κατάσταση: ' . htmlspecialchars($appStatus) : '' ?>
        </div>
        <div class="card-body">
            <?php if (empty($applications)): ?>
                <div style="text-align: center; padding: 40px;"><i class="fas fa-inbox" style="font-size: 48px; color: #ccc; margin-bottom: 16px; display: block;"></i><p>Δεν υπάρχουν αιτήσεις</p><p style="font-size: 13px; color: #6e4e3a;">Χρησιμοποιήστε την παραπάνω φόρμα για να υποβάλετε αίτηση για θέση Ειδικού Επιστήμονα στο ΤΕΠΑΚ.</p></div>
            <?php else: ?>
                <?php foreach ($applications as $app): ?>
                    <div class="application-row">
                        <div>
                            <div class="app-title"><?= htmlspecialchars($app['course_name'] ?? $app['course'] ?? 'Αίτηση') ?></div>
                            <div class="app-meta"><?= htmlspecialchars($app['department_name'] ?? $app['department'] ?? '—') ?> | <?= htmlspecialchars($app['school_name'] ?? $app['school'] ?? '—') ?><br><small><?= date('d/m/Y', strtotime($app['created_at'])) ?></small></div>
                            <div style="margin-top: 8px;"><span class="badge badge-<?= $app['status'] ?>"><?= $app['status'] == 'approved' ? 'Εγκρίθηκε' : ($app['status'] == 'under_review' ? 'Υπό Αξιολόγηση' : ($app['status'] == 'draft' ? 'Προσχέδιο' : 'Σε Εξέλιξη')) ?></span></div>
                        </div>
                        <div>
                            <div class="progress" style="margin-bottom: 8px;"><div class="progress-bar" style="width: <?= $app['completion_percentage'] ?? 0 ?>%"></div></div>
                            <small><?= $app['completion_percentage'] ?? 0 ?>% συμπληρωμένο</small>
                        </div>
                        <div>
                            <a href="edit_application.php?id=<?= $app['id'] ?>" class="btn-primary" style="padding: 8px 20px; text-decoration: none; display: inline-block;"><i class="fas fa-edit"></i> Επεξεργασία</a>
                            <a href="?delete=<?= $app['id'] ?>" class="btn-danger" onclick="return confirm('Σίγουρα θέλετε να διαγράψετε;')"><i class="fas fa-trash"></i> Διαγραφή</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer">
        <p><i class="fas fa-info-circle"></i> Οι αιτήσεις υποβάλλονται για θέσεις Ειδικών Επιστημόνων στο ΤΕΠΑΚ</p>
        <p>© <?= date('Y') ?> Τεχνολογικό Πανεπιστήμιο Κύπρου — Σύστημα Διαχείρισης Ειδικών Επιστημόνων</p>
    </div>
</div>

</body>
</html>
