<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: auth/login.php");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: users.php");
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

$id = (int) $_GET['id'];
$stmt = $pdo->prepare("SELECT u.*, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: users.php");
    exit;
}

$roles = $pdo->query("SELECT * FROM roles ORDER BY role_name")->fetchAll();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $email      = trim($_POST['email']);
    $phone      = trim($_POST['phone']);
    $role_id    = (int) $_POST['role_id'];
    $role_name  = $_POST['role_name'];

    $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check->execute([$email, $id]);
    if ($check->fetch()) {
        $error = "Το email χρησιμοποιείται ήδη από άλλον χρήστη.";
    } else {
        $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, role_id = ?, role = ? WHERE id = ?");
        $stmt->execute([$first_name, $last_name, $email, $phone, $role_id, $role_name, $id]);
        $message = "Ο χρήστης ενημερώθηκε επιτυχώς.";
        $stmt = $pdo->prepare("SELECT u.*, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Επεξεργασία Χρήστη | ΤΕΠΑΚ Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f0eb; }
        .container { max-width: 700px; margin: 0 auto; padding: 24px; }
        .header { background: white; border-radius: 20px; padding: 20px 30px; margin-bottom: 25px; border: 1px solid #e9dfd7; display: flex; justify-content: space-between; align-items: center; }
        .logo h2 { color: #2c5f8a; font-size: 1.4rem; margin: 0; }
        .logo span { color: #8b6b4d; }
        .content-card { background: white; border-radius: 20px; border: 1px solid #e9dfd7; overflow: hidden; }
        .card-header { padding: 20px 28px; border-bottom: 1px solid #e9dfd7; background: #faf9f7; }
        .card-header h3 { margin: 0; font-size: 1.1rem; font-weight: 600; color: #2c2c2c; }
        .card-header h3 i { color: #8b6b4d; margin-right: 8px; }
        .card-body { padding: 28px; }
        .form-label { font-weight: 600; color: #5a4a40; }
        .form-control, .form-select { border-radius: 30px; border: 1px solid #e2dcd5; padding: 10px 18px; font-family: 'Inter', sans-serif; }
        .form-control:focus, .form-select:focus { border-color: #2c5f8a; box-shadow: 0 0 0 3px rgba(44,95,138,0.1); }
        .btn-save { background: #2c5f8a; color: white; border: none; padding: 12px 30px; border-radius: 40px; font-weight: 500; cursor: pointer; transition: 0.2s; }
        .btn-save:hover { background: #245080; }
        .btn-back { background: #e6d9d0; color: #5a4a40; border: none; padding: 12px 30px; border-radius: 40px; font-weight: 500; text-decoration: none; transition: 0.2s; }
        .btn-back:hover { background: #dccfc4; color: #5a4a40; }
        .alert-success { background: #e8f5e9; border-left: 4px solid #4caf50; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; color: #2e7d32; }
        .alert-error { background: #ffebee; border-left: 4px solid #dc3545; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; color: #c62828; }
        .footer { text-align: center; padding: 25px; color: #8a8a8a; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo"><h2><i class="fas fa-shield-alt"></i> ΤΕΠΑΚ <span>| Admin</span></h2></div>
            <a href="users.php" class="btn-back"><i class="fas fa-arrow-left"></i> Πίσω</a>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-user-edit"></i> Επεξεργασία Χρήστη: <?= htmlspecialchars($user['username']) ?></h3>
            </div>
            <div class="card-body">
                <?php if ($message): ?><div class="alert-success"><i class="fas fa-check-circle"></i> <?= $message ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div><?php endif; ?>

                <form method="post">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Όνομα</label>
                            <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Επίθετο</label>
                            <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Τηλέφωνο</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ρόλος</label>
                            <select name="role_id" class="form-select" onchange="this.form.role_name.value = this.options[this.selectedIndex].text">
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>" <?= $r['id'] == $user['role_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($r['role_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="role_name" value="<?= htmlspecialchars($user['role_name'] ?? $user['role']) ?>">
                        </div>
                        <div class="col-12 d-flex gap-3 mt-3">
                            <button type="submit" class="btn-save"><i class="fas fa-save"></i> Αποθήκευση</button>
                            <a href="users.php" class="btn-back"><i class="fas fa-times"></i> Ακύρωση</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="footer"><p>© <?= date('Y') ?> Τεχνολογικό Πανεπιστήμιο Κύπρου</p></div>
    </div>
</body>
</html>