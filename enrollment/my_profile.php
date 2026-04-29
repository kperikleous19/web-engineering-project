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

$profile_message = $pass_message = '';
$pass_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, address = ? WHERE id = ?");
    if ($stmt->execute([$first_name, $last_name, $phone, $address, $_SESSION['user_id']])) {
        $_SESSION['first_name'] = $first_name;
        $profile_message = "Τα στοιχεία ενημερώθηκαν επιτυχώς!";
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new     = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if (!password_verify($current, $user['password_hash'])) {
        $pass_error = "Ο τρέχων κωδικός είναι λανθασμένος.";
    } elseif (strlen($new) < 6) {
        $pass_error = "Ο νέος κωδικός πρέπει να έχει τουλάχιστον 6 χαρακτήρες.";
    } elseif ($new !== $confirm) {
        $pass_error = "Οι νέοι κωδικοί δεν ταιριάζουν.";
    } else {
        $new_hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$new_hash, $_SESSION['user_id']]);
        if ($stmt->rowCount() > 0) {
            $pass_message = "Ο κωδικός άλλαξε επιτυχώς!";
        } else {
            $pass_error = "Σφάλμα: δεν ενημερώθηκε ο κωδικός. Δοκιμάστε ξανά.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | ΤΕΠΑΚ</title>
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
        .profile-row { display: flex; flex-wrap: wrap; padding: 14px 0; border-bottom: 1px solid #f0ece7; align-items: center; }
        .profile-label { width: 140px; font-weight: 600; color: #3d2510; }
        .profile-value { flex: 1; }
        .profile-value input, .profile-value textarea { width: 100%; max-width: 350px; padding: 10px 16px; border: 1px solid #e2dcd5; border-radius: 30px; font-family: 'Inter', sans-serif; }
        .email-locked { background: #e4d0bf; padding: 5px 15px; border-radius: 30px; font-size: 12px; color: #3d2510; display: inline-block; margin-left: 12px; }
        .password-section { background: #efe6db; border-radius: 20px; padding: 25px; margin-top: 20px; }
        .btn-primary { background: #1b4f78; border: none; padding: 12px 28px; border-radius: 40px; font-weight: 500; color: white; cursor: pointer; transition: 0.2s; }
        .btn-primary:hover { background: #153d5e; }
        .alert-success { background: #e8f5e9; border-left: 4px solid #4caf50; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; color: #2e7d32; }
        .alert-error { background: #ffebee; border-left: 4px solid #dc3545; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; color: #c62828; }
        .footer { text-align: center; padding: 25px; border-top: 1px solid #c9b5a5; color: #6e4e3a; font-size: 12px; margin-top: 10px; }
        @media (max-width: 768px) { .profile-label { width: 100%; margin-bottom: 8px; } .main-content { margin-left: 0; padding: 16px; } .sidebar { display: none; } }
    </style>
</head>
<body>

<div class="topbar">
    <div class="topbar-logo"><i class="fas fa-graduation-cap"></i> ΤΕΠΑΚ <span>| <?= $_SESSION['role'] === 'ee' ? 'Enrollment Module' : 'Recruitment Module' ?></span></div>
    <div class="topbar-right">
        <span class="user-badge"><i class="fas fa-user-circle"></i> <?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['username'] ?? 'Χρήστης')) ?></span>
        <a href="../auth/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Αποσύνδεση</a>
    </div>
</div>

<?php if ($_SESSION['role'] === 'ee'): ?>
<div class="sidebar">
    <div class="sidebar-nav">
        <a href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="my_profile.php" class="active"><i class="fas fa-user"></i> Το Προφίλ μου</a>
        <a href="../lms_sync.php"><i class="fas fa-sync-alt"></i> LMS Sync</a>
        <a href="../full_sync.php"><i class="fas fa-exchange-alt"></i> Full Sync</a>
        <a href="../reports.php"><i class="fas fa-chart-bar"></i> Αναφορές</a>
    </div>
</div>
<?php else: ?>
<div class="sidebar">
    <div class="sidebar-nav">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="my_profile.php" class="active"><i class="fas fa-user"></i> My Profile</a>
        <?php if ($_SESSION['role'] === 'candidate'): ?>
        <a href="my_applications.php"><i class="fas fa-file-alt"></i> My Applications</a>
        <?php endif; ?>
        <?php if ($_SESSION['role'] === 'evaluator'): ?>
        <a href="application_status.php"><i class="fas fa-star"></i> Αξιολόγηση Αιτήσεων</a>
        <?php elseif ($_SESSION['role'] === 'hr'): ?>
        <a href="application_status.php"><i class="fas fa-chart-line"></i> Κατάσταση Αιτήσεων</a>
        <a href="hr_dashboard.php"><i class="fas fa-clipboard-check"></i> Διαχείριση Αιτήσεων</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="main-content">

    <div class="content-card">
        <div class="card-header"><h3><i class="fas fa-id-card"></i> Προσωπικά Στοιχεία</h3></div>
        <div class="card-body">
            <?php if ($profile_message): ?><div class="alert-success"><i class="fas fa-check-circle"></i> <?= $profile_message ?></div><?php endif; ?>
            <form method="post">
                <div class="profile-row"><div class="profile-label">Όνομα</div><div class="profile-value"><input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>"></div></div>
                <div class="profile-row"><div class="profile-label">Επίθετο</div><div class="profile-value"><input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>"></div></div>
                <div class="profile-row"><div class="profile-label">Τηλέφωνο</div><div class="profile-value"><input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>"></div></div>
                <div class="profile-row"><div class="profile-label">Διεύθυνση</div><div class="profile-value"><textarea name="address" rows="3"><?= htmlspecialchars($user['address'] ?? '') ?></textarea></div></div>
                <div class="profile-row"><div class="profile-label">Email</div><div class="profile-value"><strong><?= htmlspecialchars($user['email']) ?></strong><span class="email-locked"><i class="fas fa-lock"></i> Μη μεταβλητό</span></div></div>
                <div style="text-align: right; margin-top: 25px;"><button type="submit" name="update_profile" class="btn-primary"><i class="fas fa-save"></i> Αποθήκευση</button></div>
            </form>

            <div class="password-section">
                <h4 style="margin-bottom: 20px; color: #1b4f78;"><i class="fas fa-key"></i> Αλλαγή Κωδικού</h4>
                <?php if ($pass_message): ?><div class="alert-success"><?= $pass_message ?></div><?php endif; ?>
                <?php if ($pass_error): ?><div class="alert-error"><?= $pass_error ?></div><?php endif; ?>
                <form method="post">
                    <div style="display: flex; flex-direction: column; gap: 15px; max-width: 400px;">
                        <input type="password" name="current_password" placeholder="Τρέχων κωδικός" style="padding: 12px 18px; border: 1px solid #e2dcd5; border-radius: 30px;">
                        <input type="password" name="new_password" placeholder="Νέος κωδικός" style="padding: 12px 18px; border: 1px solid #e2dcd5; border-radius: 30px;">
                        <input type="password" name="confirm_password" placeholder="Επιβεβαίωση νέου κωδικού" style="padding: 12px 18px; border: 1px solid #e2dcd5; border-radius: 30px;">
                        <button type="submit" name="change_password" class="btn-primary" style="width: fit-content;"><i class="fas fa-key"></i> Αλλαγή Κωδικού</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="footer"><p>© <?= date('Y') ?> Τεχνολογικό Πανεπιστήμιο Κύπρου</p></div>
</div>
</body>
</html>
