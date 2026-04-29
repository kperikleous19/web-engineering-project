<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (!in_array($_SESSION['role'], ['candidate', 'hr', 'evaluator'])) {
    header("Location: ../index.php");
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

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$success = $error = '';

if ($_SESSION['role'] === 'evaluator' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_evaluation'])) {
    $app_id = (int) $_POST['application_id'];
    $score  = $_POST['evaluator_score'] !== '' ? (float) $_POST['evaluator_score'] : null;
    $notes  = trim($_POST['evaluator_notes'] ?? '');
    $check = $pdo->prepare("SELECT id FROM applications WHERE id = ? AND evaluator_id = ?");
    $check->execute([$app_id, $_SESSION['user_id']]);
    if ($check->fetch()) {
        $stmt = $pdo->prepare("UPDATE applications SET evaluator_score = ?, evaluator_notes = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$score, $notes, $app_id]);
        $success = "Η αξιολόγηση αποθηκεύτηκε επιτυχώς.";
    } else {
        $error = "Δεν έχετε δικαίωμα αξιολόγησης αυτής της αίτησης.";
    }
}

$statusLabels = [
    'draft'        => ['label' => 'Προσχέδιο',       'class' => 'badge-draft'],
    'pending'      => ['label' => 'Σε Εξέλιξη',      'class' => 'badge-pending'],
    'under_review' => ['label' => 'Υπό Αξιολόγηση',  'class' => 'badge-review'],
    'review'       => ['label' => 'Υπό Αξιολόγηση',  'class' => 'badge-review'],
    'approved'     => ['label' => 'Εγκρίθηκε',       'class' => 'badge-approved'],
    'rejected'     => ['label' => 'Απορρίφθηκε',     'class' => 'badge-rejected'],
];

if ($_SESSION['role'] === 'candidate') {
    $stmt = $pdo->prepare("SELECT * FROM applications WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $applications = $stmt->fetchAll();
    $total    = count($applications);
    $approved = count(array_filter($applications, fn($a) => $a['status'] === 'approved'));
    $review   = count(array_filter($applications, fn($a) => in_array($a['status'], ['under_review','review'])));
    $pending  = count(array_filter($applications, fn($a) => $a['status'] === 'pending'));

} elseif ($_SESSION['role'] === 'hr') {
    $filterStatus = $_GET['status'] ?? '';
    $filterEval   = $_GET['evaluator'] ?? '';
    $where = '1=1';
    $params = [];
    if ($filterStatus) { $where .= ' AND a.status = ?'; $params[] = $filterStatus; }
    if ($filterEval)   { $where .= ' AND a.evaluator_id = ?'; $params[] = $filterEval; }
    $stmt = $pdo->prepare("
        SELECT a.*, u.first_name, u.last_name, u.email,
               ev.first_name AS eval_first, ev.last_name AS eval_last
        FROM applications a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN users ev ON a.evaluator_id = ev.id
        WHERE $where ORDER BY a.created_at DESC
    ");
    $stmt->execute($params);
    $applications = $stmt->fetchAll();
    $allStats  = $pdo->query("SELECT status, COUNT(*) as cnt FROM applications GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
    $evaluators = $pdo->query("
        SELECT u.id, u.first_name, u.last_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.role = 'evaluator' OR r.role_name = 'evaluator'
        ORDER BY u.last_name, u.first_name
    ")->fetchAll();

} elseif ($_SESSION['role'] === 'evaluator') {
    $stmt = $pdo->prepare("
        SELECT a.*, u.first_name AS cand_first, u.last_name AS cand_last, u.email AS cand_email
        FROM applications a JOIN users u ON a.user_id = u.id
        WHERE a.evaluator_id = ? ORDER BY a.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $applications = $stmt->fetchAll();
    $total     = count($applications);
    $evaluated = count(array_filter($applications, fn($a) => $a['evaluator_score'] !== null));
    $pending   = $total - $evaluated;
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Κατάσταση Αιτήσεων | ΤΕΠΑΚ</title>
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
        .stat-box { background: white; border-radius: 16px; padding: 20px; flex: 1; min-width: 130px; text-align: center; border: 1px solid #c9b5a5; }
        .stat-number { font-size: 30px; font-weight: 700; color: #1b4f78; }
        .stat-label { font-size: 11px; color: #6e4e3a; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
        .content-card { background: white; border-radius: 20px; border: 1px solid #c9b5a5; overflow: hidden; margin-bottom: 24px; }
        .card-header { padding: 18px 24px; border-bottom: 1px solid #c9b5a5; background: #efe6db; }
        .card-header h3 { margin: 0; font-size: 1rem; font-weight: 600; color: #2c2c2c; }
        .card-header h3 i { color: #7a4f2e; margin-right: 8px; }
        .card-body { padding: 24px; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 30px; font-size: 11px; font-weight: 600; }
        .badge-draft    { background: #f0f0f0; color: #666; }
        .badge-pending  { background: #fff3e0; color: #e67e22; }
        .badge-review   { background: #e3f2fd; color: #1b4f78; }
        .badge-approved { background: #e8f5e9; color: #4caf50; }
        .badge-rejected { background: #ffebee; color: #dc3545; }
        .status-table { width: 100%; border-collapse: collapse; }
        .status-table th { text-align: left; padding: 13px 20px; background: #f9f8f6; font-weight: 600; font-size: 12px; color: #6e4e3a; border-bottom: 1px solid #c9b5a5; }
        .status-table td { padding: 14px 20px; border-bottom: 1px solid #f0ece7; font-size: 13px; vertical-align: middle; }
        .status-table tr:last-child td { border-bottom: none; }
        .stepper { display: flex; align-items: flex-start; margin: 24px 0 12px; }
        .step { display: flex; flex-direction: column; align-items: center; flex: 1; position: relative; }
        .step-circle { width: 40px; height: 40px; border-radius: 50%; border: 2px solid #c9b5a5; background: white; display: flex; align-items: center; justify-content: center; font-size: 15px; color: #c9b5a5; z-index: 1; transition: all 0.2s; }
        .step-circle.done { background: #1b4f78; border-color: #1b4f78; color: white; }
        .step-circle.active { background: white; border-color: #1b4f78; color: #1b4f78; box-shadow: 0 0 0 4px rgba(27,79,120,0.15); }
        .step-circle.rejected { background: #dc3545; border-color: #dc3545; color: white; }
        .step-circle.approved { background: #4caf50; border-color: #4caf50; color: white; }
        .step-label { font-size: 11px; color: #6e4e3a; margin-top: 7px; text-align: center; max-width: 70px; line-height: 1.3; font-weight: 500; }
        .step-label.active { color: #1b4f78; font-weight: 600; }
        .step-label.rejected { color: #dc3545; font-weight: 600; }
        .step-label.approved { color: #4caf50; font-weight: 600; }
        .step-line { position: absolute; top: 19px; left: 50%; width: 100%; height: 2px; background: #e2dcd5; z-index: 0; }
        .step-line.done { background: #1b4f78; }
        .step:last-child .step-line { display: none; }
        .app-card { border: 1px solid #c9b5a5; border-radius: 16px; padding: 20px; margin-bottom: 16px; background: #fafafa; }
        .app-card:last-child { margin-bottom: 0; }
        .eval-form { background: #f4f1ec; border-radius: 12px; padding: 16px; margin-top: 14px; }
        .score-input { width: 80px; padding: 8px 12px; border: 1px solid #c9b5a5; border-radius: 10px; font-size: 14px; text-align: center; }
        .notes-input { width: 100%; padding: 10px 14px; border: 1px solid #c9b5a5; border-radius: 10px; font-size: 13px; margin-top: 8px; resize: vertical; min-height: 70px; font-family: inherit; }
        .btn-submit { background: #1b4f78; color: white; border: none; padding: 9px 24px; border-radius: 30px; font-size: 13px; cursor: pointer; transition: 0.15s; margin-top: 10px; }
        .btn-submit:hover { background: #163f62; }
        .filter-bar { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; align-items: center; }
        .filter-bar select { padding: 8px 14px; border: 1px solid #c9b5a5; border-radius: 30px; font-size: 13px; font-family: inherit; background: white; }
        .filter-bar a { background: #e4d0bf; padding: 8px 18px; border-radius: 30px; text-decoration: none; color: #3d2510; font-size: 13px; }
        .score-chip { display: inline-block; background: #e3f2fd; color: #1b4f78; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .alert-success { background: #e8f5e9; border-left: 4px solid #4caf50; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; color: #2e7d32; font-size: 14px; }
        .alert-error   { background: #ffebee; border-left: 4px solid #dc3545; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; color: #c62828; font-size: 14px; }
        .empty-state { text-align: center; padding: 50px 20px; color: #6e4e3a; }
        .empty-state i { font-size: 44px; opacity: 0.35; display: block; margin-bottom: 14px; }
        .footer { text-align: center; padding: 24px; color: #6e4e3a; font-size: 12px; margin-top: 8px; }
        @media (max-width: 768px) { .stats-row { flex-direction: column; } .main-content { margin-left: 0; padding: 16px; } .sidebar { display: none; } }
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
        <?php if ($_SESSION['role'] === 'candidate'): ?>
        <a href="my_applications.php"><i class="fas fa-file-alt"></i> My Applications</a>
        <a href="application_status.php" class="active"><i class="fas fa-chart-line"></i> Κατάσταση Αιτήσεων</a>
        <?php elseif ($_SESSION['role'] === 'evaluator'): ?>
        <a href="application_status.php" class="active"><i class="fas fa-star"></i> Αξιολόγηση Αιτήσεων</a>
        <?php elseif ($_SESSION['role'] === 'hr'): ?>
        <a href="application_status.php" class="active"><i class="fas fa-chart-line"></i> Κατάσταση Αιτήσεων</a>
        <a href="hr_dashboard.php"><i class="fas fa-clipboard-check"></i> Διαχείριση Αιτήσεων</a>
        <?php endif; ?>
    </div>
</div>

<div class="main-content">

    <?php if ($success): ?><div class="alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($_SESSION['role'] === 'candidate'): ?>

    <div class="stats-row">
        <div class="stat-box"><div class="stat-number"><?= $total ?></div><div class="stat-label">Σύνολο</div></div>
        <div class="stat-box"><div class="stat-number"><?= $pending ?></div><div class="stat-label">Σε Εξέλιξη</div></div>
        <div class="stat-box"><div class="stat-number"><?= $review ?></div><div class="stat-label">Υπό Αξιολόγηση</div></div>
        <div class="stat-box"><div class="stat-number"><?= $approved ?></div><div class="stat-label">Εγκεκριμένες</div></div>
    </div>

    <?php if (empty($applications)): ?>
        <div class="content-card"><div class="card-body">
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <p>Δεν έχετε υποβάλει αιτήσεις ακόμη.</p>
                <a href="my_applications.php" style="display:inline-block; margin-top:14px; background:#e4d0bf; padding:9px 22px; border-radius:30px; text-decoration:none; color:#3d2510; font-size:13px;">Υποβολή Αίτησης →</a>
            </div>
        </div></div>
    <?php else: ?>
        <?php foreach ($applications as $app):
            $s = $statusLabels[$app['status']] ?? ['label' => $app['status'], 'class' => 'badge-pending'];
            $currentStep = match($app['status']) {
                'pending'                => 0,
                'under_review', 'review' => 1,
                'approved', 'rejected'   => 2,
                default                  => 0,
            };
        ?>
        <div class="app-card">
            <div style="display:flex; justify-content:space-between; align-items:start; flex-wrap:wrap; gap:10px;">
                <div>
                    <div style="font-weight:600; font-size:1rem; color:#2c2c2c;"><?= htmlspecialchars($app['course_name'] ?? $app['course'] ?? 'Αίτηση') ?></div>
                    <div style="color:#6e4e3a; font-size:13px; margin-top:3px;">
                        <?= htmlspecialchars($app['department_name'] ?? $app['department'] ?? '—') ?>
                        <?php if ($app['school_name'] ?? null): ?> · <?= htmlspecialchars($app['school_name']) ?><?php endif; ?>
                    </div>
                </div>
                <div style="text-align:right;">
                    <span class="badge <?= $s['class'] ?>"><?= $s['label'] ?></span>
                    <div style="font-size:11px; color:#8a7060; margin-top:4px;"><?= date('d/m/Y', strtotime($app['created_at'])) ?></div>
                </div>
            </div>
            <div class="stepper">
                <?php
                $isRejected = $app['status'] === 'rejected';
                $isApproved = $app['status'] === 'approved';
                $steps = [
                    ['label' => 'Υποβολή',         'icon' => 'fa-paper-plane'],
                    ['label' => 'Επεξεργασία',      'icon' => 'fa-cog'],
                    ['label' => 'Αξιολόγηση',       'icon' => 'fa-search'],
                    ['label' => $isRejected ? 'Απορρίφθηκε' : 'Εγκρίθηκε', 'icon' => $isRejected ? 'fa-times' : 'fa-check'],
                ];
                $currentStep = match($app['status']) {
                    'draft'                  => 0,
                    'pending'                => 1,
                    'under_review', 'review' => 2,
                    'approved', 'rejected'   => 3,
                    default                  => 1,
                };
                foreach ($steps as $i => $step):
                    $isFinal  = $i === 3 && in_array($app['status'], ['approved','rejected']);
                    $isDone   = $i < $currentStep;
                    $isActive = $i === $currentStep && !$isFinal;
                    if ($isFinal) {
                        $circleClass = $isRejected ? 'rejected' : 'approved';
                        $labelClass  = $isRejected ? 'rejected' : 'approved';
                    } else {
                        $circleClass = $isDone ? 'done' : ($isActive ? 'active' : '');
                        $labelClass  = $isActive ? 'active' : '';
                    }
                    $lineClass = $isDone || $isFinal ? 'done' : '';
                ?>
                <div class="step">
                    <div class="step-line <?= $lineClass ?>"></div>
                    <div class="step-circle <?= $circleClass ?>"><i class="fas <?= $step['icon'] ?>"></i></div>
                    <div class="step-label <?= $labelClass ?>"><?= $step['label'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if ($app['reviewer_comments']): ?>
            <div style="background:#f0ece7; border-radius:10px; padding:10px 14px; margin-top:12px; font-size:13px; color:#3d2510;">
                <strong><i class="fas fa-comment"></i> Σχόλιο HR:</strong> <?= htmlspecialchars($app['reviewer_comments']) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

<?php elseif ($_SESSION['role'] === 'hr'): ?>

    <div class="stats-row">
        <?php
        $statMap = ['pending' => 'Σε Εξέλιξη', 'under_review' => 'Υπό Αξιολόγηση', 'approved' => 'Εγκεκριμένες', 'rejected' => 'Απορριφθείσες'];
        $total = array_sum($allStats);
        ?>
        <div class="stat-box"><div class="stat-number"><?= $total ?></div><div class="stat-label">Σύνολο</div></div>
        <?php foreach ($statMap as $key => $label): ?>
        <div class="stat-box"><div class="stat-number"><?= $allStats[$key] ?? 0 ?></div><div class="stat-label"><?= $label ?></div></div>
        <?php endforeach; ?>
    </div>

    <div class="content-card">
        <div class="card-header"><h3><i class="fas fa-filter"></i> Φίλτρα</h3></div>
        <div class="card-body" style="padding:18px 24px;">
            <form method="GET" class="filter-bar">
                <select name="status" onchange="this.form.submit()">
                    <option value="">Όλες οι καταστάσεις</option>
                    <?php foreach ($statMap as $key => $label): ?>
                    <option value="<?= $key ?>" <?= ($filterStatus === $key) ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="evaluator" onchange="this.form.submit()">
                    <option value="">Όλοι οι αξιολογητές</option>
                    <option value="0" <?= ($filterEval === '0') ? 'selected' : '' ?>>Χωρίς αξιολογητή</option>
                    <?php foreach ($evaluators as $ev): ?>
                    <option value="<?= $ev['id'] ?>" <?= ($filterEval == $ev['id']) ? 'selected' : '' ?>><?= htmlspecialchars($ev['first_name'] . ' ' . $ev['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($filterStatus || $filterEval): ?><a href="application_status.php">Καθαρισμός</a><?php endif; ?>
            </form>
        </div>
    </div>

    <div class="content-card">
        <div class="card-header"><h3><i class="fas fa-list"></i> Αιτήσεις (<?= count($applications) ?>)</h3></div>
        <?php if (empty($applications)): ?>
            <div class="empty-state"><i class="fas fa-inbox"></i><p>Δεν βρέθηκαν αιτήσεις.</p></div>
        <?php else: ?>
        <table class="status-table">
            <thead><tr><th>Υποψήφιος</th><th>Μάθημα / Τμήμα</th><th>Κατάσταση</th><th>Αξιολογητής</th><th>Βαθμός</th><th>Ημερομηνία</th></tr></thead>
            <tbody>
                <?php foreach ($applications as $app):
                    $s = $statusLabels[$app['status']] ?? ['label' => $app['status'], 'class' => 'badge-pending'];
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></strong><br><small style="color:#6e4e3a;"><?= htmlspecialchars($app['email']) ?></small></td>
                    <td><?= htmlspecialchars($app['course_name'] ?? $app['course'] ?? '—') ?><br><small style="color:#6e4e3a;"><?= htmlspecialchars($app['department_name'] ?? $app['department'] ?? '—') ?></small></td>
                    <td><span class="badge <?= $s['class'] ?>"><?= $s['label'] ?></span></td>
                    <td><?php if ($app['eval_first']): ?><?= htmlspecialchars($app['eval_first'] . ' ' . $app['eval_last']) ?><?php else: ?><span style="color:#aaa; font-size:12px;">Δεν έχει οριστεί</span><?php endif; ?></td>
                    <td><?php if ($app['evaluator_score'] !== null): ?><span class="score-chip"><?= number_format($app['evaluator_score'], 1) ?> / 10</span><?php else: ?><span style="color:#aaa; font-size:12px;">—</span><?php endif; ?></td>
                    <td style="font-size:12px; color:#6e4e3a;"><?= date('d/m/Y', strtotime($app['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

<?php elseif ($_SESSION['role'] === 'evaluator'): ?>

    <div class="stats-row">
        <div class="stat-box"><div class="stat-number"><?= $total ?></div><div class="stat-label">Ανατεθειμένες</div></div>
        <div class="stat-box"><div class="stat-number"><?= $evaluated ?></div><div class="stat-label">Αξιολογήθηκαν</div></div>
        <div class="stat-box"><div class="stat-number"><?= $pending ?></div><div class="stat-label">Εκκρεμείς</div></div>
    </div>

    <?php
    $pendingApps   = array_filter($applications, fn($a) => $a['evaluator_score'] === null);
    $completedApps = array_filter($applications, fn($a) => $a['evaluator_score'] !== null);
    ?>

    <?php if (empty($applications)): ?>
        <div class="content-card"><div class="card-body">
            <div class="empty-state">
                <i class="fas fa-clipboard"></i>
                <p>Δεν έχουν ανατεθεί αιτήσεις για αξιολόγηση ακόμη.</p>
                <p style="font-size:13px; margin-top:6px;">Ο διαχειριστής ή το HR θα σας αναθέσει αιτήσεις.</p>
            </div>
        </div></div>
    <?php else: ?>

    <?php if (!empty($pendingApps)): ?>
    <div class="content-card" style="margin-bottom:24px;">
        <div class="card-header"><h3><i class="fas fa-clock"></i> Εκκρεμείς Αξιολογήσεις <span style="background:#fff3e0;color:#e67e22;padding:3px 10px;border-radius:20px;font-size:12px;margin-left:8px;"><?= count($pendingApps) ?></span></h3></div>
        <div class="card-body">
        <?php foreach ($pendingApps as $app):
            $s = $statusLabels[$app['status']] ?? ['label' => $app['status'], 'class' => 'badge-pending'];
        ?>
        <div class="app-card">
            <div style="display:flex; justify-content:space-between; align-items:start; flex-wrap:wrap; gap:10px;">
                <div>
                    <div style="font-weight:600; font-size:1rem; color:#2c2c2c;"><?= htmlspecialchars($app['cand_first'] . ' ' . $app['cand_last']) ?></div>
                    <div style="color:#6e4e3a; font-size:13px;"><?= htmlspecialchars($app['cand_email']) ?></div>
                    <div style="margin-top:6px;">
                        <strong style="font-size:14px;"><?= htmlspecialchars($app['course_name'] ?? $app['course'] ?? '—') ?></strong>
                        <span style="color:#6e4e3a; font-size:13px;"> · <?= htmlspecialchars($app['department_name'] ?? $app['department'] ?? '—') ?></span>
                    </div>
                </div>
                <div style="text-align:right;">
                    <span class="badge <?= $s['class'] ?>"><?= $s['label'] ?></span>
                    <div style="font-size:11px; color:#8a7060; margin-top:4px;"><?= date('d/m/Y', strtotime($app['created_at'])) ?></div>
                </div>
            </div>
            <?php if ($app['reviewer_comments']): ?>
            <div style="background:#f0ece7; border-radius:10px; padding:10px 14px; margin-top:12px; font-size:13px; color:#3d2510;">
                <strong><i class="fas fa-comment"></i> Σχόλιο HR:</strong> <?= htmlspecialchars($app['reviewer_comments']) ?>
            </div>
            <?php endif; ?>
            <div class="eval-form">
                <strong style="font-size:13px; color:#2c2c2c;"><i class="fas fa-star" style="color:#7a4f2e;"></i> Αξιολόγηση</strong>
                <form method="POST">
                    <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                    <div style="display:flex; align-items:center; gap:12px; margin-top:10px; flex-wrap:wrap;">
                        <div>
                            <label style="font-size:12px; color:#6e4e3a; display:block; margin-bottom:4px;">Βαθμός (0–10)</label>
                            <input type="number" name="evaluator_score" class="score-input" min="0" max="10" step="0.5" placeholder="π.χ. 8.5">
                        </div>
                        <div style="flex:1; min-width:200px;">
                            <label style="font-size:12px; color:#6e4e3a; display:block; margin-bottom:4px;">Σχόλια αξιολογητή</label>
                            <textarea name="evaluator_notes" class="notes-input" placeholder="Παρατηρήσεις για την αίτηση..."><?= htmlspecialchars($app['evaluator_notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <button type="submit" name="submit_evaluation" class="btn-submit"><i class="fas fa-save"></i> Αποθήκευση Αξιολόγησης</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($completedApps)): ?>
    <div class="content-card">
        <div class="card-header" style="cursor:pointer;" onclick="document.getElementById('completed-list').style.display = document.getElementById('completed-list').style.display === 'none' ? 'block' : 'none'">
            <h3><i class="fas fa-check-circle" style="color:#4caf50;"></i> Ολοκληρωμένες Αξιολογήσεις
                <span style="background:#e8f5e9;color:#4caf50;padding:3px 10px;border-radius:20px;font-size:12px;margin-left:8px;"><?= count($completedApps) ?></span>
                <span style="float:right; font-size:12px; font-weight:400; color:#6e4e3a;">κλικ για εμφάνιση/απόκρυψη</span>
            </h3>
        </div>
        <div id="completed-list" style="display:none;">
        <table class="status-table">
            <thead><tr><th>Υποψήφιος</th><th>Μάθημα</th><th>Βαθμός</th><th>Σχόλια</th><th>Ημερομηνία</th></tr></thead>
            <tbody>
            <?php foreach ($completedApps as $app): ?>
            <tr>
                <td><strong><?= htmlspecialchars($app['cand_first'] . ' ' . $app['cand_last']) ?></strong><br><small style="color:#6e4e3a;"><?= htmlspecialchars($app['cand_email']) ?></small></td>
                <td><?= htmlspecialchars($app['course_name'] ?? $app['course'] ?? '—') ?><br><small style="color:#6e4e3a;"><?= htmlspecialchars($app['department_name'] ?? $app['department'] ?? '—') ?></small></td>
                <td><span class="score-chip"><i class="fas fa-star"></i> <?= number_format($app['evaluator_score'], 1) ?> / 10</span></td>
                <td style="font-size:12px; color:#3d2510; max-width:200px;"><?= htmlspecialchars($app['evaluator_notes'] ?? '—') ?></td>
                <td style="font-size:12px; color:#6e4e3a;"><?= date('d/m/Y', strtotime($app['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>

<?php endif; ?>

    <div class="footer"><p>© <?= date('Y') ?> Τεχνολογικό Πανεπιστήμιο Κύπρου — Σύστημα Διαχείρισης Ειδικών Επιστημόνων</p></div>
</div>
</body>
</html>
