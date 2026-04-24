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

$host = "127.0.0.1";
$dbname = "tepak_ee";
$username = "root";
$password = "oTem333!";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database connection failed.");
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_id'])) {
    $app_id  = (int) $_POST['application_id'];
    $status  = $_POST['status'];
    $comment = trim($_POST['reviewer_comments']);

    $allowed = ['pending', 'under_review', 'approved', 'rejected'];
    if (!in_array($status, $allowed)) {
        $error = "Μη έγκυρη κατάσταση.";
    } else {
        $stmt = $pdo->prepare("UPDATE applications SET status = ?, reviewer_comments = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $comment, $app_id]);
        $message = "Η αξιολόγηση καταχωρήθηκε επιτυχώς.";
    }
}

$stmt = $pdo->query("
    SELECT a.*, u.first_name, u.last_name, u.email
    FROM applications a
    JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC
");
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
        body { font-family: 'Inter', sans-serif; background: #f5f0eb; }
        .container { max-width: 1100px; margin: 0 auto; padding: 24px; }
        .header { background: white; border-radius: 20px; padding: 20px 30px; margin-bottom: 25px; border: 1px solid #e9dfd7; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .logo h2 { color: #2c5f8a; font-size: 1.4rem; margin: 0; }
        .logo span { color: #8b6b4d; }
        .user-badge { background: #e8ded5; padding: 8px 18px; border-radius: 40px; color: #5a4a40; font-size: 14px; }
        .logout-btn { background: #e6d9d0; padding: 8px 18px; border-radius: 40px; text-decoration: none; color: #5a4a40; margin-left: 10px; font-size: 14px; }
        .nav-menu { background: white; border-radius: 50px; padding: 8px 20px; margin-bottom: 30px; display: flex; flex-wrap: wrap; gap: 8px; border: 1px solid #e9dfd7; }
        .nav-menu a { text-decoration: none; color: #8a8a8a; font-weight: 500; padding: 10px 24px; border-radius: 40px; transition: 0.2s; }
        .nav-menu a:hover, .nav-menu a.active { background: #e6d9d0; color: #5a4a40; }
        .content-card { background: white; border-radius: 20px; border: 1px solid #e9dfd7; overflow: hidden; margin-bottom: 24px; }
        .card-header-bar { padding: 20px 28px; border-bottom: 1px solid #e9dfd7; background: #faf9f7; }
        .card-header-bar h3 { margin: 0; font-size: 1.1rem; font-weight: 600; color: #2c2c2c; }
        .card-header-bar h3 i { color: #8b6b4d; margin-right: 8px; }
        .card-body-pad { padding: 28px; }
        .app-row { border: 1px solid #e9dfd7; border-radius: 16px; padding: 20px; margin-bottom: 16px; background: #fafafa; }
        .app-row:last-child { margin-bottom: 0; }
        .app-meta { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 14px; align-items: center; }
        .app-name { font-weight: 600; color: #2c2c2c; font-size: 1rem; }
        .app-course { color: #5a4a40; font-size: 0.9rem; }
        .app-dept { color: #8a7163; font-size: 0.85rem; }
        .form-select-sm { border-radius: 20px; border: 1px solid #e2dcd5; padding: 6px 14px; font-size: 0.85rem; }
        .form-control-sm { border-radius: 12px; border: 1px solid #e2dcd5; padding: 8px 14px; font-size: 0.85rem; }
        .btn-evaluate { background: #2c5f8a; color: white; border: none; padding: 8px 22px; border-radius: 30px; font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: 0.2s; }
        .btn-evaluate:hover { background: #245080; }
        .alert-success { background: #e8f5e9; border-left: 4px solid #4caf50; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; color: #2e7d32; }
        .alert-error { background: #ffebee; border-left: 4px solid #dc3545; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; color: #c62828; }
        .footer { text-align: center; padding: 25px; color: #8a8a8a; font-size: 12px; }
        .empty-state { text-align: center; padding: 60px 20px; color: #8a7163; }
        .empty-state i { font-size: 48px; margin-bottom: 16px; opacity: 0.4; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="logo"><h2><i class="fas fa-graduation-cap"></i> ΤΕΠΑΚ <span>| Recruitment Module</span></h2></div>
        <div>
            <span class="user-badge"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?></span>
            <a href="../auth/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Αποσύνδεση</a>
        </div>
    </div>

    <div class="nav-menu">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="evaluate.php" class="active"><i class="fas fa-star"></i> Αξιολόγηση</a>
        <a href="../enrollment/my_profile.php"><i class="fas fa-user"></i> My Profile</a>
    </div>

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
                            <div style="background: #f0ece7; border-radius: 10px; padding: 10px 14px; margin-bottom: 12px; font-size: 0.85rem; color: #5a4a40;">
                                <strong>Προηγούμενα σχόλια:</strong> <?= htmlspecialchars($app['reviewer_comments']) ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" class="d-flex flex-wrap gap-2 align-items-end">
                            <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                            <div>
                                <label style="font-size: 0.8rem; font-weight: 600; color: #5a4a40;">Κατάσταση</label>
                                <select name="status" class="form-select-sm d-block mt-1">
                                    <option value="pending"      <?= $app['status'] === 'pending'      ? 'selected' : '' ?>>Εκκρεμεί</option>
                                    <option value="under_review" <?= $app['status'] === 'under_review' ? 'selected' : '' ?>>Υπό Αξιολόγηση</option>
                                    <option value="approved"     <?= $app['status'] === 'approved'     ? 'selected' : '' ?>>Εγκρίθηκε</option>
                                    <option value="rejected"     <?= $app['status'] === 'rejected'     ? 'selected' : '' ?>>Απορρίφθηκε</option>
                                </select>
                            </div>
                            <div style="flex: 1; min-width: 200px;">
                                <label style="font-size: 0.8rem; font-weight: 600; color: #5a4a40;">Σχόλια Αξιολογητή</label>
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
</div>
</body>
</html>