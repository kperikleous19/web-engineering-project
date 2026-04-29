<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SESSION['role'] !== 'evaluator') {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$columnCheck = $pdo->prepare("
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'applications'
      AND COLUMN_NAME = 'evaluator_id'
");
$columnCheck->execute();
if ((int)$columnCheck->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE applications ADD COLUMN evaluator_id INT NULL AFTER reviewer_comments");
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_id'])) {
    $app_id  = (int) $_POST['application_id'];
    $status  = $_POST['status'];
    $comment = trim($_POST['reviewer_comments']);

    $allowed = ['pending', 'under_review'];
    if (!in_array($status, $allowed)) {
        $error = "Μη έγκυρη κατάσταση.";
    } else {
        $stmt = $pdo->prepare("UPDATE applications SET status = ?, reviewer_comments = ?, updated_at = NOW() WHERE id = ? AND evaluator_id = ?");
        $stmt->execute([$status, $comment, $app_id, $_SESSION['user_id']]);
        if ($stmt->rowCount() > 0) {
            $message = "Η αξιολόγηση καταχωρήθηκε επιτυχώς.";
        } else {
            $error = "Δεν έχετε δικαίωμα αξιολόγησης αυτής της αίτησης.";
        }
    }
}

$stmt = $pdo->prepare("
    SELECT a.*, u.first_name, u.last_name, u.email
    FROM applications a
    JOIN users u ON a.user_id = u.id
    WHERE a.evaluator_id = ?
    ORDER BY a.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$applications = $stmt->fetchAll();

$statusLabels = [
    'pending'      => ['label' => 'Εκκρεμεί',       'color' => 'secondary'],
    'draft'        => ['label' => 'Πρόχειρο',        'color' => 'secondary'],
    'under_review' => ['label' => 'Υπό Αξιολόγηση', 'color' => 'warning'],
    'approved'     => ['label' => 'Εγκρίθηκε',      'color' => 'success'],
    'rejected'     => ['label' => 'Απορρίφθηκε',    'color' => 'danger'],
];
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Αξιολόγηση Αιτήσεων | ΤΕΠΑΚ</title>
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
        .content-card { background: white; border-radius: 20px; border: 1px solid #c9b5a5; overflow: hidden; margin-bottom: 24px; }
        .card-header-bar { padding: 20px 28px; border-bottom: 1px solid #c9b5a5; background: #efe6db; }
        .card-header-bar h3 { margin: 0; font-size: 1.1rem; font-weight: 600; color: #2c2c2c; }
        .card-header-bar h3 i { color: #7a4f2e; margin-right: 8px; }
        .card-body-pad { padding: 28px; }
        .app-row { border: 1px solid #c9b5a5; border-radius: 16px; padding: 20px; margin-bottom: 16px; background: #fafafa; }
        .app-row:last-child { margin-bottom: 0; }
        .app-meta { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 14px; align-items: center; }
        .app-name { font-weight: 600; color: #2c2c2c; font-size: 1rem; }
        .app-course { color: #3d2510; font-size: 0.9rem; }
        .app-dept { color: #6e4e3a; font-size: 0.85rem; }
        .form-select-sm { border-radius: 20px; border: 1px solid #e2dcd5; padding: 6px 14px; font-size: 0.85rem; }
        .form-control-sm { border-radius: 12px; border: 1px solid #e2dcd5; padding: 8px 14px; font-size: 0.85rem; }
        .btn-evaluate { background: #1b4f78; color: white; border: none; padding: 8px 22px; border-radius: 30px; font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: 0.2s; }
        .btn-evaluate:hover { background: #245080; }
        .alert-success { background: #e8f5e9; border-left: 4px solid #4caf50; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; color: #2e7d32; }
        .alert-error { background: #ffebee; border-left: 4px solid #dc3545; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; color: #c62828; }
        .footer { text-align: center; padding: 25px; color: #6e4e3a; font-size: 12px; }
        .empty-state { text-align: center; padding: 60px 20px; color: #6e4e3a; }
        .empty-state i { font-size: 48px; margin-bottom: 16px; opacity: 0.4; }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 16px; } .sidebar { display: none; } }
    </style>
</head>
<body>
<div class="topbar">
    <div class="topbar-logo"><i class="fas fa-graduation-cap"></i> ΤΕΠΑΚ <span>| Recruitment Module</span></div>
    <div class="topbar-right">
        <span class="user-badge"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?></span>
        <a href="../auth/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Αποσύνδεση</a>
    </div>
</div>
<div class="sidebar">
    <div class="sidebar-nav">
        <a href="../enrollment/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="../enrollment/my_profile.php"><i class="fas fa-user"></i> My Profile</a>
        <a href="../enrollment/application_status.php"><i class="fas fa-star"></i> Αξιολόγηση Αιτήσεων</a>
    </div>
</div>
<div class="main-content">
    <?php if ($message): ?><div class="alert-success"><i class="fas fa-check-circle"></i> <?= $message ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div><?php endif; ?>

    <div class="content-card">
        <div class="card-header-bar">
            <h3><i class="fas fa-clipboard-check"></i> Αξιολόγηση Αιτήσεων
                <span class="badge bg-secondary ms-2" style="font-size: 0.75rem;"><?= count($applications) ?></span>
            </h3>
        </div>
        <div class="card-body-pad">
            <?php if (empty($applications)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox d-block"></i>
                    <p>Δεν υπάρχουν αιτήσεις προς αξιολόγηση.</p>
                </div>
            <?php else: ?>
                <?php foreach ($applications as $app): ?>
                    <?php $s = $statusLabels[$app['status']] ?? ['label' => $app['status'], 'color' => 'secondary']; ?>
                    <div class="app-row">
                        <div class="app-meta">
                            <div>
                                <div class="app-name"><i class="fas fa-user"></i> <?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></div>
                                <div class="app-course"><i class="fas fa-book"></i> <?= htmlspecialchars($app['course_name'] ?? $app['course']) ?></div>
                                <div class="app-dept"><i class="fas fa-building"></i> <?= htmlspecialchars($app['department_name'] ?? $app['department']) ?></div>
                            </div>
                            <span class="badge bg-<?= $s['color'] ?> ms-auto"><?= $s['label'] ?></span>
                        </div>

                        <?php if ($app['reviewer_comments']): ?>
                            <div style="background: #f0ece7; border-radius: 10px; padding: 10px 14px; margin-bottom: 12px; font-size: 0.85rem; color: #3d2510;">
                                <strong>Προηγούμενα σχόλια:</strong> <?= htmlspecialchars($app['reviewer_comments']) ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" class="d-flex flex-wrap gap-2 align-items-end">
                            <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                            <div>
                                <label style="font-size: 0.8rem; font-weight: 600; color: #3d2510;">Κατάσταση</label>
                                <select name="status" class="form-select-sm d-block mt-1">
                                    <option value="pending"      <?= $app['status'] === 'pending'      ? 'selected' : '' ?>>Εκκρεμεί</option>
                                    <option value="under_review" <?= $app['status'] === 'under_review' ? 'selected' : '' ?>>Υπό Αξιολόγηση</option>
                                </select>
                            </div>
                            <div style="flex: 1; min-width: 200px;">
                                <label style="font-size: 0.8rem; font-weight: 600; color: #3d2510;">Σχόλια Αξιολογητή</label>
                                <input type="text" name="reviewer_comments" class="form-control-sm d-block mt-1 w-100"
                                       placeholder="Προαιρετικά σχόλια..."
                                       value="<?= htmlspecialchars($app['reviewer_comments'] ?? '') ?>">
                            </div>
                            <button type="submit" class="btn-evaluate"><i class="fas fa-save"></i> Αποθήκευση</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer"><p>© <?= date('Y') ?> Τεχνολογικό Πανεπιστήμιο Κύπρου</p></div>
</div><!-- main-content -->
</body>
</html>
