<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SESSION['role'] !== 'hr') {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../includes/db.php';

function ensureApplicationColumn($pdo, $column, $definition) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'applications'
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$column]);
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE applications ADD COLUMN $column $definition");
    }
}

ensureApplicationColumn($pdo, 'evaluator_id', 'INT NULL AFTER reviewer_comments');
ensureApplicationColumn($pdo, 'evaluator_score', 'DECIMAL(4,1) NULL AFTER evaluator_id');
ensureApplicationColumn($pdo, 'evaluator_notes', 'TEXT NULL AFTER evaluator_score');

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

    $userStmt = $pdo->prepare("UPDATE users SET role = 'ee', role_id = ? WHERE id = ?");
    $userStmt->execute([$eeRoleId, $application['user_id']]);

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

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $app_id = (int) ($_POST['application_id'] ?? 0);

    if (isset($_POST['assign_evaluator'])) {
        $evaluator_id = $_POST['evaluator_id'] === '' ? null : (int) $_POST['evaluator_id'];
        $stmt = $pdo->prepare("UPDATE applications SET evaluator_id = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$evaluator_id, $app_id]);
        $msg = $evaluator_id ? "Αξιολογητής ανατέθηκε επιτυχώς." : "Ο αξιολογητής αφαιρέθηκε.";
    } elseif (isset($_POST['status'])) {
        $status  = $_POST['status'];
        $comment = trim($_POST['hr_comments'] ?? '');
        $allowed = ['pending', 'under_review', 'approved', 'rejected'];
        if (in_array($status, $allowed)) {
            $stmt = $pdo->prepare("UPDATE applications SET status = ?, reviewer_comments = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $comment, $app_id]);
            $msg = "Η κατάσταση αίτησης ενημερώθηκε.";
            if ($status === 'approved') {
                $msg = promoteApprovedCandidateToEe($pdo, $app_id);
            }
        }
    }

    $filter = $_POST['current_filter'] ?? 'pending';
    $search = $_POST['current_search'] ?? '';
    $qs = http_build_query(array_filter(['filter' => $filter, 'search' => $search, 'msg' => $msg ?? '']));
    header("Location: hr_dashboard.php?" . $qs);
    exit;
}

$message = htmlspecialchars($_GET['msg'] ?? '');
$filterStatus = $_GET['filter'] ?? 'pending';
$search = trim($_GET['search'] ?? '');
$orderBy = in_array($_GET['order'] ?? '', ['created_at', 'course_name', 'department_name', 'first_name'], true) ? $_GET['order'] : 'created_at';
$orderDir = ($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

$evaluators = $pdo->query("
    SELECT u.id, u.first_name, u.last_name
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    WHERE u.role = 'evaluator' OR r.role_name = 'evaluator'
    ORDER BY u.last_name, u.first_name
")->fetchAll();

$allApps = $pdo->query("SELECT status FROM applications")->fetchAll();
$total    = count($allApps);
$pending  = count(array_filter($allApps, fn($a) => in_array($a['status'], ['pending', 'draft'], true)));
$review   = count(array_filter($allApps, fn($a) => $a['status'] === 'under_review'));
$approved = count(array_filter($allApps, fn($a) => $a['status'] === 'approved'));
$rejected = count(array_filter($allApps, fn($a) => $a['status'] === 'rejected'));

$conditions = [];
$params = [];
if ($filterStatus === 'pending') {
    $conditions[] = "a.status IN ('pending', 'draft')";
} elseif ($filterStatus !== 'all') {
    $conditions[] = 'a.status = ?';
    $params[] = $filterStatus;
}
if ($search !== '') {
    $conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR a.course_name LIKE ? OR a.department_name LIKE ?)";
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}
$whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$orderCol = $orderBy === 'first_name' ? 'u.first_name' : "a.$orderBy";
$stmt = $pdo->prepare("
    SELECT a.*, u.first_name, u.last_name, u.email,
           ev.first_name AS eval_first, ev.last_name AS eval_last
    FROM applications a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN users ev ON a.evaluator_id = ev.id
    $whereClause
    ORDER BY $orderCol $orderDir
");
$stmt->execute($params);
$applications = $stmt->fetchAll();

$statusLabels = [
    'pending'      => ['label' => 'Εκκρεμεί',        'color' => 'secondary'],
    'draft'        => ['label' => 'Πρόχειρο',         'color' => 'secondary'],
    'under_review' => ['label' => 'Υπό Αξιολόγηση',  'color' => 'warning'],
    'approved'     => ['label' => 'Εγκρίθηκε',       'color' => 'success'],
    'rejected'     => ['label' => 'Απορρίφθηκε',     'color' => 'danger'],
];
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Διαχείριση Αιτήσεων | ΤΕΠΑΚ</title>
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
        .stats-row { display: flex; gap: 16px; margin-bottom: 28px; flex-wrap: wrap; }
        .stat-card { background: white; border-radius: 16px; padding: 20px; flex: 1; min-width: 120px; text-align: center; border: 1px solid #c9b5a5; }
        .stat-number { font-size: 30px; font-weight: 700; color: #1b4f78; }
        .stat-label { font-size: 11px; color: #6e4e3a; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
        .content-card { background: white; border-radius: 20px; border: 1px solid #c9b5a5; overflow: hidden; margin-bottom: 24px; }
        .card-header-bar { padding: 20px 28px; border-bottom: 1px solid #c9b5a5; background: #efe6db; }
        .card-header-bar h3 { margin: 0; font-size: 1.1rem; font-weight: 600; color: #2c2c2c; }
        .card-header-bar h3 i { color: #7a4f2e; margin-right: 8px; }
        .card-body-pad { padding: 28px; }
        .app-row { border: 1px solid #c9b5a5; border-radius: 16px; padding: 20px; margin-bottom: 16px; background: #fafafa; }
        .app-row:last-child { margin-bottom: 0; }
        .app-meta { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 14px; align-items: flex-start; }
        .app-name { font-weight: 600; color: #2c2c2c; font-size: 1rem; }
        .app-email { color: #6e4e3a; font-size: 0.8rem; }
        .app-course { color: #3d2510; font-size: 0.9rem; margin-top: 2px; }
        .app-dept { color: #6e4e3a; font-size: 0.85rem; }
        .app-date { color: #6e4e3a; font-size: 0.8rem; }
        .quick-btns { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
        .btn-approve { background: #1b4f78; color: white; border: none; padding: 7px 18px; border-radius: 30px; font-size: 0.82rem; cursor: pointer; transition: 0.2s; }
        .btn-approve:hover { background: #245080; }
        .btn-reject  { background: #c0392b; color: white; border: none; padding: 7px 18px; border-radius: 30px; font-size: 0.82rem; cursor: pointer; transition: 0.2s; }
        .btn-reject:hover  { background: #a93226; }
        .btn-review  { background: #7a4f2e; color: white; border: none; padding: 7px 18px; border-radius: 30px; font-size: 0.82rem; cursor: pointer; transition: 0.2s; }
        .btn-review:hover  { background: #6b4427; }
        .comment-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .comment-input { flex: 1; min-width: 180px; border: 1px solid #e2dcd5; border-radius: 12px; padding: 7px 14px; font-size: 0.82rem; }
        .alert-success { background: #e8f5e9; border-left: 4px solid #4caf50; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; color: #2e7d32; }
        .alert-error   { background: #ffebee; border-left: 4px solid #dc3545; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; color: #c62828; }
        .empty-state   { text-align: center; padding: 60px 20px; color: #6e4e3a; }
        .empty-state i { font-size: 48px; margin-bottom: 16px; opacity: 0.4; display: block; }
        .footer { text-align: center; padding: 25px; color: #6e4e3a; font-size: 12px; }
        .search-bar { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
        .search-input { flex: 1; min-width: 200px; padding: 9px 14px; border: 1px solid #c9b5a5; border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 13px; }
        .search-input:focus { outline: none; border-color: #1b4f78; }
        .filter-select { padding: 9px 14px; border: 1px solid #c9b5a5; border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 13px; background: white; }
        .btn-search { background: #1b4f78; color: white; border: none; padding: 9px 18px; border-radius: 10px; cursor: pointer; font-size: 13px; }
        .btn-clear { background: #e4d0bf; color: #3d2510; border: none; padding: 9px 14px; border-radius: 10px; cursor: pointer; font-size: 13px; text-decoration: none; }
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
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="my_profile.php"><i class="fas fa-user"></i> My Profile</a>
        <a href="application_status.php"><i class="fas fa-chart-line"></i> Κατάσταση Αιτήσεων</a>
        <a href="hr_dashboard.php" class="active"><i class="fas fa-clipboard-check"></i> Διαχείριση Αιτήσεων</a>
    </div>
</div>

<div class="main-content">

    <div class="stats-row">
        <div class="stat-card"><div class="stat-number"><?= $total ?></div><div class="stat-label">Σύνολο</div></div>
        <div class="stat-card"><div class="stat-number"><?= $pending ?></div><div class="stat-label">Εκκρεμείς</div></div>
        <div class="stat-card"><div class="stat-number"><?= $review ?></div><div class="stat-label">Υπό Αξιολόγηση</div></div>
        <div class="stat-card"><div class="stat-number" style="color:#2e7d32;"><?= $approved ?></div><div class="stat-label">Εγκεκριμένες</div></div>
        <div class="stat-card"><div class="stat-number" style="color:#c62828;"><?= $rejected ?></div><div class="stat-label">Απορριφθείσες</div></div>
    </div>

    <?php if ($message): ?><div class="alert-success"><i class="fas fa-check-circle"></i> <?= $message ?></div><?php endif; ?>

    <form method="GET" action="hr_dashboard.php" class="search-bar">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filterStatus) ?>">
        <input type="text" name="search" class="search-input" placeholder="Αναζήτηση ονόματος, email, μαθήματος..." value="<?= htmlspecialchars($search) ?>">
        <select name="order" class="filter-select">
            <option value="created_at" <?= $orderBy === 'created_at' ? 'selected' : '' ?>>Ταξινόμηση: Ημερομηνία</option>
            <option value="first_name" <?= $orderBy === 'first_name' ? 'selected' : '' ?>>Ταξινόμηση: Όνομα</option>
            <option value="course_name" <?= $orderBy === 'course_name' ? 'selected' : '' ?>>Ταξινόμηση: Μάθημα</option>
            <option value="department_name" <?= $orderBy === 'department_name' ? 'selected' : '' ?>>Ταξινόμηση: Τμήμα</option>
        </select>
        <select name="dir" class="filter-select">
            <option value="desc" <?= $orderDir === 'DESC' ? 'selected' : '' ?>>Φθίνουσα</option>
            <option value="asc" <?= $orderDir === 'ASC' ? 'selected' : '' ?>>Αύξουσα</option>
        </select>
        <button type="submit" class="btn-search"><i class="fas fa-search"></i></button>
        <?php if ($search): ?>
        <a href="hr_dashboard.php?filter=<?= htmlspecialchars($filterStatus) ?>" class="btn-clear"><i class="fas fa-times"></i></a>
        <?php endif; ?>
    </form>

    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:24px;">
        <?php
        $tabs = ['pending' => 'Νέες / Εκκρεμείς', 'under_review' => 'Υπό Αξιολόγηση', 'approved' => 'Εγκεκριμένες', 'rejected' => 'Απορριφθείσες', 'all' => 'Όλες'];
        $baseParams = http_build_query(array_filter(['search' => $search, 'order' => $orderBy, 'dir' => strtolower($orderDir)]));
        foreach ($tabs as $val => $label):
            $active = $filterStatus === $val;
            $bg = $active ? 'background:#1b4f78; color:white;' : 'background:white; color:#3d2510;';
        ?>
        <a href="hr_dashboard.php?filter=<?= $val ?>&<?= $baseParams ?>" style="<?= $bg ?> padding:8px 20px; border-radius:30px; text-decoration:none; font-size:13px; font-weight:500; border:1px solid #c9b5a5; transition:0.15s;">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="content-card">
        <div class="card-header-bar">
            <h3><i class="fas fa-clipboard-list"></i> <?= $tabs[$filterStatus] ?? 'Αιτήσεις' ?> Υποψηφίων
                <span class="badge bg-secondary ms-2" style="font-size: 0.75rem;"><?= count($applications) ?></span>
            </h3>
        </div>
        <div class="card-body-pad">
            <?php if (empty($applications)): ?>
                <div class="empty-state"><i class="fas fa-inbox"></i><p>Δεν υπάρχουν αιτήσεις.</p></div>
            <?php else: ?>
                <?php foreach ($applications as $app): ?>
                    <?php $s = $statusLabels[$app['status']] ?? ['label' => $app['status'], 'color' => 'secondary']; ?>
                    <div class="app-row">
                        <div class="app-meta">
                            <div style="flex:1;">
                                <div class="app-name"><i class="fas fa-user"></i> <?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></div>
                                <div class="app-email"><?= htmlspecialchars($app['email']) ?></div>
                                <div class="app-course"><i class="fas fa-book"></i> <?= htmlspecialchars($app['course_name'] ?? $app['course'] ?? '—') ?></div>
                                <div class="app-dept"><i class="fas fa-building"></i> <?= htmlspecialchars($app['department_name'] ?? $app['department'] ?? '—') ?></div>
                            </div>
                            <div style="text-align:right;">
                                <span class="badge bg-<?= $s['color'] ?>"><?= $s['label'] ?></span>
                                <div class="app-date mt-1"><?= date('d/m/Y', strtotime($app['created_at'])) ?></div>
                            </div>
                        </div>

                        <?php if ($app['reviewer_comments']): ?>
                            <div style="background:#f0ece7; border-radius:10px; padding:8px 14px; margin-bottom:12px; font-size:0.82rem; color:#3d2510;">
                                <strong>Σχόλια:</strong> <?= htmlspecialchars($app['reviewer_comments']) ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" style="margin-bottom:10px;">
                            <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                            <input type="hidden" name="current_filter" value="<?= htmlspecialchars($filterStatus) ?>">
                            <input type="hidden" name="current_search" value="<?= htmlspecialchars($search) ?>">
                            <div class="quick-btns">
                                <button type="submit" name="status" value="approved"     class="btn-approve"><i class="fas fa-check"></i> Έγκριση</button>
                                <button type="submit" name="status" value="rejected"     class="btn-reject"><i class="fas fa-times"></i> Απόρριψη</button>
                                <button type="submit" name="status" value="under_review" class="btn-review"><i class="fas fa-eye"></i> Υπό Αξιολόγηση</button>
                            </div>
                            <div class="comment-row">
                                <input type="text" name="hr_comments" class="comment-input"
                                       placeholder="Σχόλιο (προαιρετικό)..."
                                       value="<?= htmlspecialchars($app['reviewer_comments'] ?? '') ?>">
                            </div>
                        </form>

                        <form method="post" style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                            <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                            <input type="hidden" name="current_filter" value="<?= htmlspecialchars($filterStatus) ?>">
                            <input type="hidden" name="current_search" value="<?= htmlspecialchars($search) ?>">
                            <i class="fas fa-user-check" style="color:#7a4f2e; font-size:13px;"></i>
                            <span style="font-size:0.82rem; color:#3d2510; font-weight:500;">Αξιολογητής:</span>
                            <?php if ($app['eval_first']): ?>
                                <span style="background:#e3f2fd; color:#1b4f78; padding:3px 10px; border-radius:20px; font-size:0.8rem; font-weight:600;">
                                    <?= htmlspecialchars($app['eval_first'] . ' ' . $app['eval_last']) ?>
                                </span>
                            <?php endif; ?>
                            <select name="evaluator_id" style="border:1px solid #e2dcd5; border-radius:10px; padding:5px 10px; font-size:0.82rem; background:white; color:#3d2510;">
                                <option value="">— Χωρίς ανάθεση —</option>
                                <?php foreach ($evaluators as $ev): ?>
                                    <option value="<?= $ev['id'] ?>" <?= $app['evaluator_id'] == $ev['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($ev['first_name'] . ' ' . $ev['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="assign_evaluator" value="1" style="background:#7a4f2e; color:white; border:none; padding:5px 16px; border-radius:20px; font-size:0.82rem; cursor:pointer;">
                                <i class="fas fa-save"></i> Ανάθεση
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer"><p>© <?= date('Y') ?> Τεχνολογικό Πανεπιστήμιο Κύπρου</p></div>
</div>
</body>
</html>
