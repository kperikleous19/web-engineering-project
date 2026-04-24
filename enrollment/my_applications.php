<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$host = "127.0.0.1";
$dbname = "tepak_ee_db";
$username = "root";
$password = "oTem333!";

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

// Handle new application submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    $course_name = trim($_POST['course_name']);
    $department_name = trim($_POST['department_name']);
    $faculty_name = trim($_POST['faculty_name']);
    
    if (empty($course_name)) {
        $error = "Παρακαλώ συμπληρώστε το μάθημα/θέση";
    } else {
        $stmt = $pdo->prepare("INSERT INTO applications (user_id, course_name, department_name, school_name, status, completion_percentage) VALUES (?, ?, ?, ?, 'draft', 0)");
        if ($stmt->execute([$_SESSION['user_id'], $course_name, $department_name, $faculty_name])) {
            $success = "Η αίτηση δημιουργήθηκε επιτυχώς!";
        } else {
            $error = "Σφάλμα κατά τη δημιουργία.";
        }
    }
}

// Handle application deletion
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM applications WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['delete'], $_SESSION['user_id']]);
    $success = "Η αίτηση διαγράφηκε.";
}

// Get user's applications
$stmt = $pdo->prepare("SELECT * FROM applications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$applications = $stmt->fetchAll();

// TEPAK Faculties and Departments (based on official structure)
$tepak_faculties = [
    [
        'name' => 'Σχολή Μηχανικής και Τεχνολογίας',
        'departments' => [
            'Τμήμα Ηλεκτρολόγων Μηχανικών και Μηχανικών Υπολογιστών και Πληροφορικής',
            'Τμήμα Μηχανολόγων Μηχανικών και Επιστήμης και Μηχανικής Υλικών',
            'Τμήμα Πολιτικών Μηχανικών και Μηχανικών Γεωπληροφορικής'
        ],
        'courses' => [
            'Μηχανική Ιστού (Web Engineering)',
            'Τεχνητή Νοημοσύνη',
            'Ασφάλεια Υπολογιστών',
            'Δίκτυα Υπολογιστών',
            'Βάσεις Δεδομένων',
            'Ανάλυση Κυκλωμάτων',
            'Ψηφιακή Επεξεργασία Σήματος',
            'Μηχανική Στερεού',
            'Θερμοδυναμική',
            'Ανάλυση Κατασκευών',
            'Γεωτεχνική Μηχανική'
        ]
    ],
    [
        'name' => 'Σχολή Γεωτεχνικών Επιστημών και Διαχείρισης Περιβάλλοντος',
        'departments' => [
            'Τμήμα Γεωπονικών Επιστημών, Βιοτεχνολογίας και Επιστήμης Τροφίμων',
            'Τμήμα Χημικών Μηχανικών'
        ],
        'courses' => [
            'Γεωργική Βιοτεχνολογία',
            'Επιστήμη Τροφίμων',
            'Χημική Μηχανική',
            'Περιβαλλοντική Μηχανική'
        ]
    ],
    [
        'name' => 'Σχολή Διοίκησης και Οικονομίας',
        'departments' => [
            'Τμήμα Χρηματοοικονομικής, Λογιστικής και Διοικητικής Επιστήμης',
            'Τμήμα Ναυτιλιακών'
        ],
        'courses' => [
            'Τραπεζική και Χρηματοοικονομικά',
            'Λογιστική',
            'Διοίκηση Επιχειρήσεων',
            'Ναυτιλιακές Σπουδές',
            'Ναυτιλιακή Διοίκηση'
        ]
    ],
    [
        'name' => 'Σχολή Επικοινωνίας και Μέσων Ενημέρωσης',
        'departments' => [
            'Τμήμα Επικοινωνίας και Σπουδών Διαδικτύου',
            'Τμήμα Πολυμέσων και Γραφικών Τεχνών'
        ],
        'courses' => [
            'Ψηφιακά Μέσα',
            'Τεχνολογίες Διαδικτύου',
            'Σχεδιασμός Ιστοσελίδων',
            'Γραφικός Σχεδιασμός',
            'Τρισδιάστατα Γραφικά',
            'Παραγωγή Πολυμέσων'
        ]
    ],
    [
        'name' => 'Σχολή Επιστημών Υγείας',
        'departments' => [
            'Τμήμα Νοσηλευτικής',
            'Τμήμα Επιστημών Αποκατάστασης'
        ],
        'courses' => [
            'Εισαγωγή στη Νοσηλευτική',
            'Κλινική Νοσηλευτική',
            'Διοίκηση Μονάδων Υγείας',
            'Φυσικοθεραπεία',
            'Εργοθεραπεία',
            'Λογοθεραπεία'
        ]
    ],
    [
        'name' => 'Σχολή Καλών και Εφαρμοσμένων Τεχνών',
        'departments' => [
            'Τμήμα Καλών Τεχνών'
        ],
        'courses' => [
            'Ιστορία Τέχνης',
            'Σχεδιασμός',
            'Ψηφιακή Τέχνη',
            'Γλυπτική',
            'Ζωγραφική'
        ]
    ],
    [
        'name' => 'Σχολή Διοίκησης Τουρισμού, Φιλοξενίας και Επιχειρηματικότητας (Πάφος)',
        'departments' => [
            'Τμήμα Διοίκησης Τουρισμού και Φιλοξενίας',
            'Τμήμα Διοίκησης, Επιχειρηματικότητας και Ψηφιακού Επιχειρείν'
        ],
        'courses' => [
            'Διοίκηση Τουριστικών Επιχειρήσεων',
            'Μάρκετινγκ Τουρισμού',
            'Hotel Management',
            'Επιχειρηματικότητα',
            'Ψηφιακό Μάρκετινγκ'
        ]
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
        body { font-family: 'Inter', sans-serif; background: #f5f0eb; }
        .container { max-width: 1200px; margin: 0 auto; padding: 24px; }
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
        .card-header { padding: 20px 28px; border-bottom: 1px solid #e9dfd7; background: #faf9f7; }
        .card-header h3 { margin: 0; font-size: 1.1rem; font-weight: 600; color: #2c2c2c; }
        .card-header h3 i { color: #8b6b4d; margin-right: 8px; }
        .card-body { padding: 28px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #5a4a40; font-size: 13px; }
        .form-group input, .form-group select { width: 100%; padding: 12px 16px; border: 1px solid #e2dcd5; border-radius: 30px; font-family: 'Inter', sans-serif; }
        .btn-primary { background: #e6d9d0; border: none; padding: 12px 28px; border-radius: 40px; font-weight: 500; color: #5a4a40; cursor: pointer; transition: 0.2s; }
        .btn-primary:hover { background: #dccfc4; }
        .btn-danger { background: #fdeaea; color: #c62828; padding: 6px 15px; border-radius: 30px; text-decoration: none; font-size: 12px; margin-left: 10px; }
        .application-row { display: flex; justify-content: space-between; align-items: center; padding: 18px 20px; border-bottom: 1px solid #f0ece7; flex-wrap: wrap; gap: 15px; }
        .application-row:last-child { border-bottom: none; }
        .app-title { font-size: 1rem; font-weight: 600; color: #2c2c2c; }
        .app-meta { font-size: 12px; color: #8a7163; margin-top: 5px; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 30px; font-size: 11px; font-weight: 600; }
        .badge-draft { background: #f0f0f0; color: #666; }
        .badge-pending { background: #fff3e0; color: #e67e22; }
        .badge-review { background: #e3f2fd; color: #2c5f8a; }
        .badge-approved { background: #e8f5e9; color: #4caf50; }
        .progress { width: 100px; height: 6px; background: #e9dfd7; border-radius: 3px; overflow: hidden; }
        .progress-bar { background: #2c5f8a; height: 100%; }
        .alert-success { background: #e8f5e9; border-left: 4px solid #4caf50; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; color: #2e7d32; }
        .alert-error { background: #ffebee; border-left: 4px solid #dc3545; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; color: #c62828; }
        .footer { text-align: center; padding: 25px; border-top: 1px solid #e9dfd7; color: #8a8a8a; font-size: 12px; }
        .row-select { display: flex; gap: 20px; flex-wrap: wrap; }
        @media (max-width: 768px) { .application-row { flex-direction: column; text-align: center; } .container { padding: 16px; } }
    </style>
    <script>
        // Department and course data based on selected faculty
        const facultyData = <?= json_encode($tepak_faculties) ?>;
        
        function updateDepartments() {
            const facultySelect = document.getElementById('faculty_name');
            const departmentSelect = document.getElementById('department_name');
            const courseSelect = document.getElementById('course_name');
            const selectedFaculty = facultySelect.value;
            
            // Clear and reset department dropdown
            departmentSelect.innerHTML = '<option value="">Επιλέξτε Τμήμα</option>';
            courseSelect.innerHTML = '<option value="">Επιλέξτε Μάθημα</option>';
            
            if (selectedFaculty) {
                const faculty = facultyData.find(f => f.name === selectedFaculty);
                if (faculty) {
                    faculty.departments.forEach(dept => {
                        const option = document.createElement('option');
                        option.value = dept;
                        option.textContent = dept;
                        departmentSelect.appendChild(option);
                    });
                }
            }
        }
        
        function updateCourses() {
            const facultySelect = document.getElementById('faculty_name');
            const courseSelect = document.getElementById('course_name');
            const selectedFaculty = facultySelect.value;
            
            courseSelect.innerHTML = '<option value="">Επιλέξτε Μάθημα</option>';
            
            if (selectedFaculty) {
                const faculty = facultyData.find(f => f.name === selectedFaculty);
                if (faculty && faculty.courses) {
                    faculty.courses.forEach(course => {
                        const option = document.createElement('option');
                        option.value = course;
                        option.textContent = course;
                        courseSelect.appendChild(option);
                    });
                }
            }
        }
        
        // Alternative: manual course entry toggle
        function toggleCustomCourse() {
            const customCheckbox = document.getElementById('customCourse');
            const courseSelect = document.getElementById('course_name');
            const customCourseInput = document.getElementById('custom_course');
            
            if (customCheckbox.checked) {
                courseSelect.style.display = 'none';
                customCourseInput.style.display = 'block';
                customCourseInput.required = true;
                courseSelect.required = false;
            } else {
                courseSelect.style.display = 'block';
                customCourseInput.style.display = 'none';
                customCourseInput.required = false;
                courseSelect.required = true;
            }
        }
    </script>
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
            <a href="my_applications.php" class="active"><i class="fas fa-file-alt"></i> My Applications</a>
            <a href="application_status.php"><i class="fas fa-chart-line"></i> Κατάσταση Αιτήσεων</a>
        </div>
        
        <?php if ($success): ?><div class="alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div><?php endif; ?>
        
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
                    
                    <button type="submit" name="submit_application" class="btn-primary"><i class="fas fa-save"></i> Δημιουργία Αίτησης</button>
                </form>
            </div>
        </div>
        
        <div class="content-card">
            <div class="card-header"><h3><i class="fas fa-list"></i> Οι Αιτήσεις μου</h3></div>
            <div class="card-body">
                <?php if (empty($applications)): ?>
                    <div style="text-align: center; padding: 40px;"><i class="fas fa-inbox" style="font-size: 48px; color: #ccc; margin-bottom: 16px; display: block;"></i><p>Δεν υπάρχουν αιτήσεις</p><p style="font-size: 13px; color: #8a7163;">Χρησιμοποιήστε την παραπάνω φόρμα για να υποβάλετε αίτηση για θέση Ειδικού Επιστήμονα στο ΤΕΠΑΚ.</p></div>
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
                            <div><a href="edit_application.php?id=<?= $app['id'] ?>" class="btn-primary" style="padding: 8px 20px; text-decoration: none; display: inline-block;"><i class="fas fa-edit"></i> Επεξεργασία</a><a href="?delete=<?= $app['id'] ?>" class="btn-danger" onclick="return confirm('Σίγουρα θέλετε να διαγράψετε;')"><i class="fas fa-trash"></i> Διαγραφή</a></div>
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
    
    <script>
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Set default handlers
            const customCheckbox = document.getElementById('customCourse');
            if (customCheckbox) {
                customCheckbox.addEventListener('change', toggleCustomCourse);
            }
        });
    </script>
</body>
</html>