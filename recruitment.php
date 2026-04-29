<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: auth/login.php");
    exit;
}

require_once __DIR__ . "/includes/db.php";

$success = '';
$error = '';

$allowedPeriodOrders = ['period_name', 'start_date', 'end_date', 'created_at'];
$allowedAppOrders = ['id', 'created_at', 'status', 'course_name', 'department_name'];
$periodSearch = trim($_GET['period_search'] ?? '');
$periodStatus = $_GET['period_status'] ?? '';
$periodOrder = in_array($_GET['period_order'] ?? '', $allowedPeriodOrders, true) ? $_GET['period_order'] : 'created_at';
$periodDir = ($_GET['period_dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$appSearch = trim($_GET['app_search'] ?? '');
$appStatus = $_GET['app_status'] ?? '';
$appOrder = in_array($_GET['app_order'] ?? '', $allowedAppOrders, true) ? $_GET['app_order'] : 'created_at';
$appDir = ($_GET['app_dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

function redirectRecruitment($messageType, $message) {
    header('Location: recruitment.php?' . $messageType . '=' . urlencode($message));
    exit;
}

function promoteApprovedCandidateToEe($pdo, $applicationId) {
    $appStmt = $pdo->prepare("
        SELECT a.*, u.role AS current_role
        FROM applications a
        JOIN users u ON a.user_id = u.id
        WHERE a.id = ?
    ");
    $appStmt->execute([$applicationId]);
    $application = $appStmt->fetch();
    if (!$application) {
        return 'Η αίτηση εγκρίθηκε, αλλά δεν βρέθηκε ο χρήστης για μετατροπή σε ΕΕ.';
    }

    $eeRoleId = $pdo->query("SELECT id FROM roles WHERE role_name = 'ee'")->fetchColumn();
    if (!$eeRoleId) {
        $pdo->prepare("INSERT INTO roles (role_name) VALUES ('ee')")->execute();
        $eeRoleId = $pdo->lastInsertId();
    }

    $pdo->prepare("UPDATE users SET role = 'ee', role_id = ? WHERE id = ?")->execute([$eeRoleId, $application['user_id']]);

    $courseId = null;
    if (!empty($application['announcement_id'])) {
        $courseStmt = $pdo->prepare("SELECT course_id FROM announcements WHERE id = ?");
        $courseStmt->execute([$application['announcement_id']]);
        $courseId = $courseStmt->fetchColumn() ?: null;
    }
    if (!$courseId && !empty($application['course_name'])) {
        $courseStmt = $pdo->prepare("SELECT id FROM courses WHERE course_name = ? OR course_code = ? LIMIT 1");
        $courseStmt->execute([$application['course_name'], $application['course_name']]);
        $courseId = $courseStmt->fetchColumn() ?: null;
    }
    if (!$courseId && !empty($application['course'])) {
        $courseStmt = $pdo->prepare("SELECT id FROM courses WHERE course_name = ? OR course_code = ? LIMIT 1");
        $courseStmt->execute([$application['course'], $application['course']]);
        $courseId = $courseStmt->fetchColumn() ?: null;
    }

    if ($courseId) {
        $existing = $pdo->prepare("SELECT id FROM moodle_integration WHERE user_id = ? AND course_id = ?");
        $existing->execute([$application['user_id'], $courseId]);
        if ($syncId = $existing->fetchColumn()) {
            $pdo->prepare("UPDATE moodle_integration SET moodle_enrolled = 1, access_enabled = 1, last_sync = NOW() WHERE id = ?")->execute([$syncId]);
        } else {
            $pdo->prepare("INSERT INTO moodle_integration (user_id, course_id, moodle_enrolled, access_enabled, last_sync) VALUES (?, ?, 1, 1, NOW())")->execute([$application['user_id'], $courseId]);
        }
        return 'Η αίτηση εγκρίθηκε, ο υποψήφιος έγινε ΕΕ και ενεργοποιήθηκε πρόσβαση στο LMS.';
    }

    return 'Η αίτηση εγκρίθηκε και ο υποψήφιος έγινε ΕΕ. Δεν βρέθηκε αντίστοιχο μάθημα για αυτόματη LMS ανάθεση.';
}

if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $appId = $_POST['application_id'];
    $newStatus = $_POST['status'];
    $comments = $_POST['comments'] ?? '';
    
    $stmt = $pdo->prepare("UPDATE applications SET status = ?, reviewer_comments = ?, updated_at = NOW() WHERE id = ?");
    if ($stmt->execute([$newStatus, $comments, $appId])) {
        $success = "Η κατάσταση της αίτησης ενημερώθηκε επιτυχώς!";
        if ($newStatus === 'approved') {
            $success = promoteApprovedCandidateToEe($pdo, $appId);
        }
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

// Handle school / department / course CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['catalog_action'])) {
    try {
        $action = $_POST['catalog_action'];

        if ($action === 'add_faculty') {
            $nameEl = trim($_POST['faculty_name_el'] ?? '');
            $name = trim($_POST['faculty_name'] ?? '') ?: $nameEl;
            if ($nameEl === '') {
                redirectRecruitment('error', 'Συμπληρώστε το όνομα της σχολής.');
            }
            $stmt = $pdo->prepare("INSERT INTO faculties (faculty_name, faculty_name_el) VALUES (?, ?)");
            $stmt->execute([$name, $nameEl]);
            redirectRecruitment('success', 'Η σχολή δημιουργήθηκε επιτυχώς.');
        }

        if ($action === 'edit_faculty') {
            $id = (int)($_POST['faculty_id'] ?? 0);
            $nameEl = trim($_POST['faculty_name_el'] ?? '');
            $name = trim($_POST['faculty_name'] ?? '') ?: $nameEl;
            if ($id <= 0 || $nameEl === '') {
                redirectRecruitment('error', 'Δεν ήταν δυνατή η ενημέρωση της σχολής.');
            }
            $stmt = $pdo->prepare("UPDATE faculties SET faculty_name = ?, faculty_name_el = ? WHERE id = ?");
            $stmt->execute([$name, $nameEl, $id]);
            redirectRecruitment('success', 'Η σχολή ενημερώθηκε.');
        }

        if ($action === 'delete_faculty') {
            $id = (int)($_POST['faculty_id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM faculties WHERE id = ?");
            $stmt->execute([$id]);
            redirectRecruitment('success', 'Η σχολή διαγράφηκε.');
        }

        if ($action === 'add_department') {
            $facultyId = (int)($_POST['faculty_id'] ?? 0);
            $nameEl = trim($_POST['dept_name_el'] ?? '');
            $name = trim($_POST['dept_name'] ?? '') ?: $nameEl;
            if ($facultyId <= 0 || $nameEl === '') {
                redirectRecruitment('error', 'Επιλέξτε σχολή και συμπληρώστε το τμήμα.');
            }
            $stmt = $pdo->prepare("INSERT INTO departments (dept_name, dept_name_el, faculty_id) VALUES (?, ?, ?)");
            $stmt->execute([$name, $nameEl, $facultyId]);
            redirectRecruitment('success', 'Το τμήμα δημιουργήθηκε επιτυχώς.');
        }

        if ($action === 'edit_department') {
            $id = (int)($_POST['department_id'] ?? 0);
            $facultyId = (int)($_POST['faculty_id'] ?? 0);
            $nameEl = trim($_POST['dept_name_el'] ?? '');
            $name = trim($_POST['dept_name'] ?? '') ?: $nameEl;
            if ($id <= 0 || $facultyId <= 0 || $nameEl === '') {
                redirectRecruitment('error', 'Δεν ήταν δυνατή η ενημέρωση του τμήματος.');
            }
            $stmt = $pdo->prepare("UPDATE departments SET dept_name = ?, dept_name_el = ?, faculty_id = ? WHERE id = ?");
            $stmt->execute([$name, $nameEl, $facultyId, $id]);
            redirectRecruitment('success', 'Το τμήμα ενημερώθηκε.');
        }

        if ($action === 'delete_department') {
            $id = (int)($_POST['department_id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
            $stmt->execute([$id]);
            redirectRecruitment('success', 'Το τμήμα διαγράφηκε.');
        }

        if ($action === 'add_course') {
            $departmentId = (int)($_POST['department_id'] ?? 0);
            $code = trim($_POST['course_code'] ?? '');
            $name = trim($_POST['course_name'] ?? '');
            if ($departmentId <= 0 || $code === '' || $name === '') {
                redirectRecruitment('error', 'Συμπληρώστε κωδικό, όνομα και τμήμα μαθήματος.');
            }
            $stmt = $pdo->prepare("INSERT INTO courses (course_code, course_name, department_id) VALUES (?, ?, ?)");
            $stmt->execute([$code, $name, $departmentId]);
            redirectRecruitment('success', 'Το μάθημα δημιουργήθηκε επιτυχώς.');
        }

        if ($action === 'edit_course') {
            $id = (int)($_POST['course_id'] ?? 0);
            $departmentId = (int)($_POST['department_id'] ?? 0);
            $code = trim($_POST['course_code'] ?? '');
            $name = trim($_POST['course_name'] ?? '');
            if ($id <= 0 || $departmentId <= 0 || $code === '' || $name === '') {
                redirectRecruitment('error', 'Δεν ήταν δυνατή η ενημέρωση του μαθήματος.');
            }
            $stmt = $pdo->prepare("UPDATE courses SET course_code = ?, course_name = ?, department_id = ? WHERE id = ?");
            $stmt->execute([$code, $name, $departmentId, $id]);
            redirectRecruitment('success', 'Το μάθημα ενημερώθηκε.');
        }

        if ($action === 'delete_course') {
            $id = (int)($_POST['course_id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
            $stmt->execute([$id]);
            redirectRecruitment('success', 'Το μάθημα διαγράφηκε.');
        }
    } catch (PDOException $e) {
        redirectRecruitment('error', 'Δεν είναι δυνατή η διαγραφή/ενημέρωση επειδή υπάρχουν συνδεδεμένα δεδομένα.');
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
$appWhere = [];
$appParams = [];
if ($appSearch !== '') {
    $appWhere[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR a.course_name LIKE ? OR a.course LIKE ? OR a.department_name LIKE ? OR a.department LIKE ?)";
    $like = '%' . $appSearch . '%';
    $appParams = array_merge($appParams, [$like, $like, $like, $like, $like, $like, $like]);
}
if ($appStatus !== '') {
    $appWhere[] = "a.status = ?";
    $appParams[] = $appStatus;
}
$appWhereSql = $appWhere ? 'WHERE ' . implode(' AND ', $appWhere) : '';
$appStmt = $pdo->prepare("
    SELECT a.*, u.first_name, u.last_name, u.email
    FROM applications a
    JOIN users u ON a.user_id = u.id
    $appWhereSql
    ORDER BY a.$appOrder $appDir
");
$appStmt->execute($appParams);
$applications = $appStmt->fetchAll();

// Get recruitment periods
$periodWhere = [];
$periodParams = [];
if ($periodSearch !== '') {
    $periodWhere[] = "(period_name LIKE ? OR description LIKE ?)";
    $like = '%' . $periodSearch . '%';
    $periodParams = [$like, $like];
}
if ($periodStatus === 'active') {
    $periodWhere[] = "is_active = 1 AND end_date >= CURDATE()";
} elseif ($periodStatus === 'inactive') {
    $periodWhere[] = "(is_active = 0 OR end_date < CURDATE())";
}
$periodWhereSql = $periodWhere ? 'WHERE ' . implode(' AND ', $periodWhere) : '';
$periodStmt = $pdo->prepare("SELECT * FROM recruitment_periods $periodWhereSql ORDER BY $periodOrder $periodDir");
$periodStmt->execute($periodParams);
$recruitmentPeriods = $periodStmt->fetchAll();

$faculties = $pdo->query("
    SELECT f.id, f.faculty_name, f.faculty_name_el, f.created_at,
           COUNT(DISTINCT d.id) AS department_count, COUNT(DISTINCT c.id) AS course_count
    FROM faculties f
    LEFT JOIN departments d ON d.faculty_id = f.id
    LEFT JOIN courses c ON c.department_id = d.id
    GROUP BY f.id, f.faculty_name, f.faculty_name_el, f.created_at
    ORDER BY f.faculty_name_el
")->fetchAll();
$departments = $pdo->query("
    SELECT d.id, d.dept_name, d.dept_name_el, d.faculty_id, d.created_at,
           f.faculty_name_el, COUNT(c.id) AS course_count
    FROM departments d
    JOIN faculties f ON d.faculty_id = f.id
    LEFT JOIN courses c ON c.department_id = d.id
    GROUP BY d.id, d.dept_name, d.dept_name_el, d.faculty_id, d.created_at, f.faculty_name_el
    ORDER BY f.faculty_name_el, d.dept_name_el
")->fetchAll();
$courses = $pdo->query("
    SELECT c.*, d.dept_name_el, f.faculty_name_el
    FROM courses c
    JOIN departments d ON c.department_id = d.id
    JOIN faculties f ON d.faculty_id = f.id
    ORDER BY f.faculty_name_el, d.dept_name_el, c.course_code
")->fetchAll();

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
            background: #ece4da;
            color: #2c2c2c;
        }
        .topbar { background: white; border-bottom: 1px solid #c9b5a5; height: 64px; position: fixed; top: 0; left: 0; right: 0; z-index: 100; display: flex; align-items: center; justify-content: space-between; padding: 0 28px; }
        .topbar-logo { color: #1b4f78; font-weight: 700; font-size: 1.15rem; }
        .topbar-logo span { color: #7a4f2e; font-weight: 400; }
        .topbar-right { display: flex; align-items: center; gap: 10px; }
        .user-badge { background: #e4d0bf; padding: 7px 16px; border-radius: 40px; color: #3d2510; font-size: 13px; }
        .logout-btn { background: #e4d0bf; padding: 7px 16px; border-radius: 40px; text-decoration: none; color: #3d2510; font-size: 13px; transition: 0.15s; }
        .logout-btn:hover { background: #d9c4b2; }
        .sidebar {
            width: 250px;
            background: white;
            border-right: 1px solid #c9b5a5;
            height: calc(100vh - 64px);
            position: fixed;
            left: 0;
            top: 64px;
            overflow-y: auto;
        }
        .sidebar-nav { padding: 12px 0; }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 11px 22px;
            color: #5a5a5a;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            margin: 2px 10px;
            border-radius: 10px;
            transition: 0.15s;
        }
        .sidebar-nav a i { width: 18px; font-size: 15px; flex-shrink: 0; }
        .sidebar-nav a:hover { background: #f4f1ec; color: #1b4f78; }
        .sidebar-nav a.active { background: #1b4f78; color: white; }
        .nav-desc { font-size: 11px; font-weight: 400; opacity: 0.7; display: block; margin-top: 2px; line-height: 1.3; }
        .nav-section-label { font-size: 10px; font-weight: 700; color: #a08070; text-transform: uppercase; letter-spacing: 1px; padding: 10px 22px 4px; margin-top: 6px; display: block; }
        .main-content {
            margin-left: 250px;
            margin-top: 64px;
            padding: 28px 32px;
            min-height: calc(100vh - 64px);
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
            border: 1px solid #c9b5a5;
            text-align: center;
            transition: all 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.05); }
        .stat-card .stat-number { font-size: 28px; font-weight: 700; color: #1b4f78; }
        .stat-card .stat-label { font-size: 12px; color: #6e4e3a; margin-top: 5px; }
        .content-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #c9b5a5;
            overflow: hidden;
            margin-bottom: 30px;
        }
        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid #c9b5a5;
            background: #efe6db;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .card-header h3 { margin: 0; font-size: 1.2rem; font-weight: 600; color: #2c2c2c; }
        .card-header h3 i { color: #7a4f2e; margin-right: 8px; }
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
            border-bottom: 1px solid #c9b5a5;
        }
        .data-table td {
            padding: 15px 20px;
            border-bottom: 1px solid #f0ece7;
            font-size: 14px;
            color: #3a3a3a;
        }
        .data-table tr:hover { background: #efe6db; }
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-pending { background: #fff3e0; color: #e67e22; }
        .badge-review { background: #e3f2fd; color: #1b4f78; }
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
        .btn-edit { background: #f0e6df; color: #7a4f2e; }
        .btn-edit:hover { background: #e4d0bf; transform: translateY(-1px); }
        .btn-add { background: #1b4f78; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; font-size: 14px; border: none; cursor: pointer; }
        .btn-add:hover { background: #1e4668; transform: translateY(-1px); }
        .btn-delete { background: #fdeaea; color: #dc3545; }
        .btn-delete:hover { background: #fcd5d5; transform: translateY(-1px); }
        .btn-toggle { background: #e3f2fd; color: #1b4f78; }
        .btn-toggle:hover { background: #bbdef5; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; color: #2c2c2c; font-size: 13px; }
        .form-group label i { margin-right: 6px; color: #7a4f2e; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1px solid #e2dcd5; border-radius: 10px; font-family: 'Inter', sans-serif; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #1b4f78; box-shadow: 0 0 0 3px rgba(44,95,138,0.1); }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        .table-tools { display: flex; gap: 10px; padding: 15px 20px; background: #f9f8f6; border-bottom: 1px solid #c9b5a5; flex-wrap: wrap; }
        .tool-input, .tool-select { padding: 9px 12px; border: 1px solid #c9b5a5; border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 13px; background: white; }
        .tool-input { flex: 1; min-width: 220px; }
        .btn-tool { background: #1b4f78; color: white; border: 0; border-radius: 10px; padding: 9px 16px; font-size: 13px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-clear { background: #e4d0bf; color: #3d2510; }
        .inline-form { display: grid; grid-template-columns: minmax(160px, 1fr) minmax(160px, 1fr) auto; gap: 8px; align-items: center; }
        .inline-form.compact { grid-template-columns: minmax(120px, 0.7fr) minmax(180px, 1fr) minmax(170px, 1fr) auto; }
        .inline-form input, .inline-form select { width: 100%; padding: 8px 10px; border: 1px solid #e2dcd5; border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 12px; }
        .actions-stack { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
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
            color: #6e4e3a;
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

<div class="topbar">
    <div class="topbar-logo"><i class="fas fa-graduation-cap"></i> ΤΕΠΑΚ <span>| Admin Module</span></div>
    <div class="topbar-right">
        <span class="user-badge"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'Administrator') ?></span>
        <a href="auth/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Αποσύνδεση</a>
    </div>
</div>

    <div class="sidebar">
        <div class="sidebar-nav">
            <a href="admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="users.php"><i class="fas fa-users"></i> Διαχείριση Χρηστών</a>
            <a href="recruitment.php" class="active"><i class="fas fa-bullhorn"></i> Διαχείριση Προκηρύξεων</a>
            <a href="system.php"><i class="fas fa-cog"></i> Ρυθμίσεις Συστήματος</a>
            <a href="reports.php"><i class="fas fa-chart-bar"></i> Αναφορές</a>
            <span class="nav-section-label">Enrollment</span>
            <a href="dashboard.php"><i class="fas fa-chalkboard-teacher"></i> Enrollment Dashboard</a>
            <a href="lms_sync.php"><i class="fas fa-sync-alt"></i> LMS Sync</a>
            <a href="full_sync.php"><i class="fas fa-database"></i> Full Sync</a>
        </div>
    </div>

    <div class="main-content">
        <div style="margin-bottom: 25px;">
            <h1 style="font-size: 1.8rem; font-weight: 600; color: #2c2c2c;">Διαχείριση Προκηρύξεων & Αιτήσεων</h1>
            <p style="color: #6e4e3a; margin-top: 5px;">Διαχειριστείτε τις προκηρύξεις και τις αιτήσεις των υποψηφίων</p>
        </div>

        <?php if ($success): ?>
            <div class="alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
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
            <form method="GET" class="table-tools">
                <input type="text" name="period_search" class="tool-input" placeholder="Αναζήτηση προκήρυξης..." value="<?= htmlspecialchars($periodSearch) ?>">
                <select name="period_status" class="tool-select">
                    <option value="">Όλες οι καταστάσεις</option>
                    <option value="active" <?= $periodStatus === 'active' ? 'selected' : '' ?>>Ενεργές</option>
                    <option value="inactive" <?= $periodStatus === 'inactive' ? 'selected' : '' ?>>Ανενεργές/Ληγμένες</option>
                </select>
                <select name="period_order" class="tool-select">
                    <option value="created_at" <?= $periodOrder === 'created_at' ? 'selected' : '' ?>>Ταξινόμηση: Δημιουργία</option>
                    <option value="period_name" <?= $periodOrder === 'period_name' ? 'selected' : '' ?>>Ταξινόμηση: Τίτλος</option>
                    <option value="start_date" <?= $periodOrder === 'start_date' ? 'selected' : '' ?>>Ταξινόμηση: Έναρξη</option>
                    <option value="end_date" <?= $periodOrder === 'end_date' ? 'selected' : '' ?>>Ταξινόμηση: Λήξη</option>
                </select>
                <select name="period_dir" class="tool-select">
                    <option value="desc" <?= $periodDir === 'DESC' ? 'selected' : '' ?>>Φθίνουσα</option>
                    <option value="asc" <?= $periodDir === 'ASC' ? 'selected' : '' ?>>Αύξουσα</option>
                </select>
                <button type="submit" class="btn-tool"><i class="fas fa-search"></i> Εφαρμογή</button>
                <?php if ($periodSearch || $periodStatus): ?><a href="recruitment.php" class="btn-tool btn-clear"><i class="fas fa-times"></i> Καθαρισμός</a><?php endif; ?>
            </form>
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

        <!-- Σχολές, Τμήματα, Μαθήματα -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-sitemap"></i> CRUD Σχολών, Τμημάτων & Μαθημάτων</h3>
                <span class="badge badge-review"><?= count($faculties) ?> Σχολές · <?= count($departments) ?> Τμήματα · <?= count($courses) ?> Μαθήματα</span>
            </div>
            <div class="card-body" style="padding:25px;">
                <div class="form-row">
                    <form method="POST" class="form-group">
                        <input type="hidden" name="catalog_action" value="add_faculty">
                        <label><i class="fas fa-university"></i> Νέα Σχολή</label>
                        <input type="text" name="faculty_name_el" required placeholder="Όνομα σχολής">
                        <input type="text" name="faculty_name" placeholder="English/internal name" style="margin-top:8px;">
                        <button type="submit" class="btn-add" style="margin-top:10px;"><i class="fas fa-plus"></i> Προσθήκη Σχολής</button>
                    </form>
                    <form method="POST" class="form-group">
                        <input type="hidden" name="catalog_action" value="add_department">
                        <label><i class="fas fa-building"></i> Νέο Τμήμα</label>
                        <select name="faculty_id" required>
                            <option value="">Επιλέξτε σχολή</option>
                            <?php foreach ($faculties as $faculty): ?>
                                <option value="<?= $faculty['id'] ?>"><?= htmlspecialchars($faculty['faculty_name_el']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="dept_name_el" required placeholder="Όνομα τμήματος" style="margin-top:8px;">
                        <input type="text" name="dept_name" placeholder="English/internal name" style="margin-top:8px;">
                        <button type="submit" class="btn-add" style="margin-top:10px;"><i class="fas fa-plus"></i> Προσθήκη Τμήματος</button>
                    </form>
                    <form method="POST" class="form-group">
                        <input type="hidden" name="catalog_action" value="add_course">
                        <label><i class="fas fa-book"></i> Νέο Μάθημα</label>
                        <select name="department_id" required>
                            <option value="">Επιλέξτε τμήμα</option>
                            <?php foreach ($departments as $department): ?>
                                <option value="<?= $department['id'] ?>"><?= htmlspecialchars($department['faculty_name_el'] . ' · ' . $department['dept_name_el']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="course_code" required placeholder="Κωδικός μαθήματος" style="margin-top:8px;">
                        <input type="text" name="course_name" required placeholder="Όνομα μαθήματος" style="margin-top:8px;">
                        <button type="submit" class="btn-add" style="margin-top:10px;"><i class="fas fa-plus"></i> Προσθήκη Μαθήματος</button>
                    </form>
                </div>
            </div>

            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead><tr><th>Σχολές</th><th>Τμήματα</th><th>Μαθήματα</th><th>Ενέργειες</th></tr></thead>
                    <tbody>
                        <?php foreach ($faculties as $faculty): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($faculty['faculty_name_el']) ?></strong><br><small><?= $faculty['department_count'] ?> τμήματα · <?= $faculty['course_count'] ?> μαθήματα</small></td>
                            <td colspan="2">
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="catalog_action" value="edit_faculty">
                                    <input type="hidden" name="faculty_id" value="<?= $faculty['id'] ?>">
                                    <input type="text" name="faculty_name_el" value="<?= htmlspecialchars($faculty['faculty_name_el']) ?>" required>
                                    <input type="text" name="faculty_name" value="<?= htmlspecialchars($faculty['faculty_name']) ?>">
                                    <button type="submit" class="btn-sm btn-edit"><i class="fas fa-save"></i> Αποθήκευση</button>
                                </form>
                            </td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Διαγραφή σχολής; Θα αποτύχει αν υπάρχουν τμήματα.');">
                                    <input type="hidden" name="catalog_action" value="delete_faculty">
                                    <input type="hidden" name="faculty_id" value="<?= $faculty['id'] ?>">
                                    <button type="submit" class="btn-sm btn-delete"><i class="fas fa-trash"></i> Διαγραφή</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="overflow-x:auto; border-top:1px solid #c9b5a5;">
                <table class="data-table">
                    <thead><tr><th>Τμήμα / Σχολή</th><th>Μαθήματα</th><th>Ενέργειες</th></tr></thead>
                    <tbody>
                        <?php foreach ($departments as $department): ?>
                        <tr>
                            <td>
                                <form method="POST" class="inline-form compact">
                                    <input type="hidden" name="catalog_action" value="edit_department">
                                    <input type="hidden" name="department_id" value="<?= $department['id'] ?>">
                                    <input type="text" name="dept_name_el" value="<?= htmlspecialchars($department['dept_name_el']) ?>" required>
                                    <input type="text" name="dept_name" value="<?= htmlspecialchars($department['dept_name']) ?>">
                                    <select name="faculty_id" required>
                                        <?php foreach ($faculties as $faculty): ?>
                                            <option value="<?= $faculty['id'] ?>" <?= $faculty['id'] == $department['faculty_id'] ? 'selected' : '' ?>><?= htmlspecialchars($faculty['faculty_name_el']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn-sm btn-edit"><i class="fas fa-save"></i></button>
                                </form>
                            </td>
                            <td><?= $department['course_count'] ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Διαγραφή τμήματος; Θα αποτύχει αν υπάρχουν μαθήματα.');">
                                    <input type="hidden" name="catalog_action" value="delete_department">
                                    <input type="hidden" name="department_id" value="<?= $department['id'] ?>">
                                    <button type="submit" class="btn-sm btn-delete"><i class="fas fa-trash"></i> Διαγραφή</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="overflow-x:auto; border-top:1px solid #c9b5a5;">
                <table class="data-table">
                    <thead><tr><th>Κωδικός</th><th>Μάθημα</th><th>Τμήμα</th><th>Σχολή</th><th>Ενέργειες</th></tr></thead>
                    <tbody>
                        <?php foreach ($courses as $course): ?>
                        <tr>
                            <td colspan="2">
                                <form method="POST" class="inline-form compact">
                                    <input type="hidden" name="catalog_action" value="edit_course">
                                    <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                    <input type="text" name="course_code" value="<?= htmlspecialchars($course['course_code']) ?>" required>
                                    <input type="text" name="course_name" value="<?= htmlspecialchars($course['course_name']) ?>" required>
                                    <select name="department_id" required>
                                        <?php foreach ($departments as $department): ?>
                                            <option value="<?= $department['id'] ?>" <?= $department['id'] == $course['department_id'] ? 'selected' : '' ?>><?= htmlspecialchars($department['dept_name_el']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn-sm btn-edit"><i class="fas fa-save"></i></button>
                                </form>
                            </td>
                            <td><?= htmlspecialchars($course['dept_name_el']) ?></td>
                            <td><?= htmlspecialchars($course['faculty_name_el']) ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Διαγραφή μαθήματος; Θα αποτύχει αν υπάρχουν αναθέσεις ή αιτήσεις.');">
                                    <input type="hidden" name="catalog_action" value="delete_course">
                                    <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                    <button type="submit" class="btn-sm btn-delete"><i class="fas fa-trash"></i> Διαγραφή</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
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
            <form method="GET" class="table-tools">
                <input type="text" name="app_search" class="tool-input" placeholder="Αναζήτηση υποψηφίου, email, μαθήματος ή τμήματος..." value="<?= htmlspecialchars($appSearch) ?>">
                <select name="app_status" class="tool-select">
                    <option value="">Όλες οι καταστάσεις</option>
                    <option value="draft" <?= $appStatus === 'draft' ? 'selected' : '' ?>>Προσχέδιο</option>
                    <option value="pending" <?= $appStatus === 'pending' ? 'selected' : '' ?>>Σε εξέλιξη</option>
                    <option value="under_review" <?= $appStatus === 'under_review' ? 'selected' : '' ?>>Υπό αξιολόγηση</option>
                    <option value="approved" <?= $appStatus === 'approved' ? 'selected' : '' ?>>Εγκεκριμένες</option>
                    <option value="rejected" <?= $appStatus === 'rejected' ? 'selected' : '' ?>>Απορριφθείσες</option>
                </select>
                <select name="app_order" class="tool-select">
                    <option value="created_at" <?= $appOrder === 'created_at' ? 'selected' : '' ?>>Ταξινόμηση: Ημερομηνία</option>
                    <option value="status" <?= $appOrder === 'status' ? 'selected' : '' ?>>Ταξινόμηση: Κατάσταση</option>
                    <option value="course_name" <?= $appOrder === 'course_name' ? 'selected' : '' ?>>Ταξινόμηση: Μάθημα</option>
                    <option value="department_name" <?= $appOrder === 'department_name' ? 'selected' : '' ?>>Ταξινόμηση: Τμήμα</option>
                </select>
                <select name="app_dir" class="tool-select">
                    <option value="desc" <?= $appDir === 'DESC' ? 'selected' : '' ?>>Φθίνουσα</option>
                    <option value="asc" <?= $appDir === 'ASC' ? 'selected' : '' ?>>Αύξουσα</option>
                </select>
                <button type="submit" class="btn-tool"><i class="fas fa-search"></i> Εφαρμογή</button>
                <?php if ($appSearch || $appStatus): ?><a href="recruitment.php" class="btn-tool btn-clear"><i class="fas fa-times"></i> Καθαρισμός</a><?php endif; ?>
            </form>
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
                                                <div class="progress-bar" style="width: <?= $app['completion_percentage'] ?? 0 ?>%; background: #1b4f78;"></div>
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
            <h3 style="margin-bottom: 20px; color: #1b4f78;"><i class="fas fa-edit"></i> Ενημέρωση Κατάστασης Αίτησης</h3>
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
