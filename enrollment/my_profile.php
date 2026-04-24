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
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    if (password_verify($current, $user['password_hash'])) {
        if ($new === $confirm && strlen($new) >= 6) {
            $new_hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            if ($stmt->execute([$new_hash, $_SESSION['user_id']])) $pass_message = "Ο κωδικός άλλαξε επιτυχώς!";
        } else $pass_error = "Ο νέος κωδικός πρέπει να έχει 6+ χαρακτήρες και να ταιριάζει.";
    } else $pass_error = "Ο τρέχων κωδικός είναι λανθασμένος.";
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
        body { font-family: 'Inter', sans-serif; background: #f5f0eb; }
        .container { max-width: 1000px; margin: 0 auto; padding: 24px; }
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
        .profile-row { display: flex; flex-wrap: wrap; padding: 14px 0; border-bottom: 1px solid #f0ece7; align-items: center; }
        .profile-label { width: 140px; font-weight: 600; color: #5a4a40; }
        .profile-value { flex: 1; }
        .profile-value input, .profile-value textarea { width: 100%; max-width: 350px; padding: 10px 16px; border: 1px solid #e2dcd5; border-radius: 30px; font-family: 'Inter', sans-serif; }
        .email-locked { background: #e8ded5; padding: 5px 15px; border-radius: 30px; font-size: 12px; color: #5a4a40; display: inline-block; margin-left: 12px; }
        .password-section { background: #faf9f7; border-radius: 20px; padding: 25px; margin-top: 20px; }
        .btn-primary { background: #e6d9d0; border: none; padding: 12px 28px; border-radius: 40px; font-weight: 500; color: #5a4a40; cursor: pointer; transition: 0.2s; }
        .btn-primary:hover { background: #dccfc4; }
        .alert-success { background: #e8f5e9; border-left: 4px solid #4caf50; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; color: #2e7d32; }
        .alert-error { background: #ffebee; border-left: 4px solid #dc3545; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; color: #c62828; }
        .footer { text-align: center; padding: 25px; border-top: 1px solid #e9dfd7; color: #8a8a8a; font-size: 12px; }
        @media (max-width: 768px) { .profile-label { width: 100%; margin-bottom: 8px; } .container { padding: 16px; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo"><h2><i class="fas fa-graduation-cap"></i> ΤΕΠΑΚ <span>| Recruitment Module</span></h2></div>
            <div><span class="user-badge"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span> <a href="../auth/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Αποσύνδεση</a></div>
        </div>
        
        <div class="nav-menu">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="my_profile.php" class="active"><i class="fas fa-user"></i> My Profile</a>
            <a href="my_applications.php"><i class="fas fa-file-alt"></i> My Applications</a>
            <a href="application_status.php"><i class="fas fa-chart-line"></i> Κατάσταση Αιτήσεων</a>
        </div>
        
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
                    <h4 style="margin-bottom: 20px; color: #2c5f8a;"><i class="fas fa-key"></i> Αλλαγή Κωδικού</h4>
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