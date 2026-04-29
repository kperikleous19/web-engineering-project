<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: auth/login.php");
    exit;
}

require_once __DIR__ . "/includes/db.php";

$success = '';
$error = '';

// Load all config keys into an associative array
$configRows = $pdo->query("SELECT config_key, config_value FROM system_config")->fetchAll();
$config = [];
foreach ($configRows as $row) {
    $config[$row['config_key']] = $row['config_value'];
}

// Ensure required keys exist with defaults
$defaults = [
    'system_name'              => 'Σύστημα Διαχείρισης Ειδικών Επιστημόνων',
    'institution_name'         => 'Τεχνολογικό Πανεπιστήμιο Κύπρου',
    'brand_label'              => 'ΤΕΠΑΚ',
    'theme_logo_url'           => '',
    'primary_color'            => '#1b4f78',
    'accent_color'             => '#7a4f2e',
    'background_color'         => '#ece4da',
    'footer_text'              => 'Σύστημα Διαχείρισης Ειδικών Επιστημόνων',
    'max_applications_per_period' => '3',
    'moodle_url'               => '',
    'moodle_token'             => '',
    'moodle_service'           => 'moodle_mobile_app',
    'auto_sync_enabled'        => '0',
];
foreach ($defaults as $key => $val) {
    if (!isset($config[$key])) $config[$key] = $val;
}

function normalizeColor($value, $fallback) {
    $value = trim($value ?? '');
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : $fallback;
}

$config['primary_color'] = normalizeColor($config['primary_color'], $defaults['primary_color']);
$config['accent_color'] = normalizeColor($config['accent_color'], $defaults['accent_color']);
$config['background_color'] = normalizeColor($config['background_color'], $defaults['background_color']);

// Load admin's own profile
$adminStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$adminStmt->execute([$_SESSION['user_id']]);
$admin = $adminStmt->fetch();

// Handle system settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $keys = [
        'system_name',
        'institution_name',
        'brand_label',
        'theme_logo_url',
        'primary_color',
        'accent_color',
        'background_color',
        'footer_text',
        'max_applications_per_period',
        'moodle_url',
        'moodle_token',
        'moodle_service',
        'auto_sync_enabled'
    ];
    $stmt = $pdo->prepare("INSERT INTO system_config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)");
    foreach ($keys as $key) {
        $value = $_POST[$key] ?? '';
        if ($key === 'auto_sync_enabled') $value = isset($_POST['auto_sync_enabled']) ? '1' : '0';
        if ($key === 'primary_color') $value = normalizeColor($value, $defaults['primary_color']);
        if ($key === 'accent_color') $value = normalizeColor($value, $defaults['accent_color']);
        if ($key === 'background_color') $value = normalizeColor($value, $defaults['background_color']);
        $stmt->execute([$key, $value]);
        $config[$key] = $value;
    }
    $success = "Οι ρυθμίσεις αποθηκεύτηκαν επιτυχώς.";
}

// Handle admin profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    if (empty($first_name) || empty($last_name)) {
        $error = "Το όνομα και το επώνυμο είναι υποχρεωτικά.";
    } else {
        $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ? WHERE id = ?");
        $stmt->execute([$first_name, $last_name, $_SESSION['user_id']]);
        $admin['first_name'] = $first_name;
        $admin['last_name']  = $last_name;
        $success = "Τα στοιχεία σας ενημερώθηκαν επιτυχώς.";
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current  = $_POST['current_password'] ?? '';
    $new      = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $admin['password_hash'])) {
        $error = "Ο τρέχων κωδικός δεν είναι σωστός.";
    } elseif (strlen($new) < 8) {
        $error = "Ο νέος κωδικός πρέπει να έχει τουλάχιστον 8 χαρακτήρες.";
    } elseif ($new !== $confirm) {
        $error = "Οι νέοι κωδικοί δεν ταιριάζουν.";
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $_SESSION['user_id']]);
        $success = "Ο κωδικός άλλαξε επιτυχώς.";
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ρυθμίσεις Συστήματος | ΤΕΠΑΚ Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: <?= htmlspecialchars($config['primary_color']) ?>;
            --accent: <?= htmlspecialchars($config['accent_color']) ?>;
            --page-bg: <?= htmlspecialchars($config['background_color']) ?>;
        }
        body { font-family: 'Inter', sans-serif; background: var(--page-bg); color: #2c2c2c; }
        .topbar { background: white; border-bottom: 1px solid #c9b5a5; height: 64px; position: fixed; top: 0; left: 0; right: 0; z-index: 100; display: flex; align-items: center; justify-content: space-between; padding: 0 28px; }
        .topbar-logo { color: var(--primary); font-weight: 700; font-size: 1.15rem; display:flex; align-items:center; gap:9px; }
        .topbar-logo img { width: 30px; height: 30px; object-fit: contain; }
        .topbar-logo span { color: var(--accent); font-weight: 400; }
        .topbar-right { display: flex; align-items: center; gap: 10px; }
        .user-badge { background: #e4d0bf; padding: 7px 16px; border-radius: 40px; color: #3d2510; font-size: 13px; }
        .logout-btn { background: #e4d0bf; padding: 7px 16px; border-radius: 40px; text-decoration: none; color: #3d2510; font-size: 13px; transition: 0.15s; }
        .logout-btn:hover { background: #d9c4b2; }
        .sidebar { width: 250px; background: white; border-right: 1px solid #c9b5a5; height: calc(100vh - 64px); position: fixed; left: 0; top: 64px; overflow-y: auto; }
        .sidebar-nav { padding: 12px 0; }
        .sidebar-nav a { display: flex; align-items: center; gap: 11px; padding: 11px 22px; color: #5a5a5a; text-decoration: none; margin: 2px 10px; border-radius: 10px; font-size: 13.5px; font-weight: 500; transition: 0.15s; }
        .sidebar-nav a i { width: 18px; font-size: 15px; flex-shrink: 0; }
        .sidebar-nav a:hover { background: #f4f1ec; color: var(--primary); }
        .sidebar-nav a.active { background: var(--primary); color: white; }
        .nav-section-label { font-size: 10px; font-weight: 700; color: #a08070; text-transform: uppercase; letter-spacing: 1px; padding: 10px 22px 4px; margin-top: 6px; display: block; }
        .main-content { margin-left: 250px; margin-top: 64px; padding: 28px 32px; min-height: calc(100vh - 64px); }
        .page-header { margin-bottom: 28px; }
        .page-header h1 { font-size: 1.6rem; font-weight: 600; color: #2c2c2c; }
        .page-header p { color: #6e4e3a; margin-top: 4px; font-size: 14px; }
        .settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .settings-card { background: white; border-radius: 16px; border: 1px solid #c9b5a5; overflow: hidden; }
        .settings-card.full-width { grid-column: 1 / -1; }
        .card-header { background: #efe6db; padding: 16px 24px; border-bottom: 1px solid #c9b5a5; display: flex; align-items: center; gap: 10px; }
        .card-header h3 { font-size: 1rem; font-weight: 600; color: #2c2c2c; margin: 0; }
        .card-header i { color: var(--accent); font-size: 16px; }
        .card-body { padding: 24px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #3d2510; margin-bottom: 6px; }
        .form-group input[type="text"],
        .form-group input[type="url"],
        .form-group input[type="number"],
        .form-group input[type="password"],
        .form-group input[type="color"] { width: 100%; padding: 10px 14px; border: 1px solid #c9b5a5; border-radius: 10px; font-size: 14px; font-family: inherit; color: #2c2c2c; background: #faf8f5; transition: 0.15s; }
        .form-group input[type="color"] { height: 44px; padding: 5px; cursor: pointer; }
        .form-group input:focus { outline: none; border-color: var(--primary); background: white; }
        .form-group input[readonly] { background: #f0ece7; color: #7a6a5a; cursor: not-allowed; }
        .form-group .hint { font-size: 11px; color: #8a7060; margin-top: 4px; }
        .toggle-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f0ece7; }
        .toggle-row:last-child { border-bottom: none; }
        .toggle-label { font-size: 14px; font-weight: 500; color: #2c2c2c; }
        .toggle-label small { display: block; font-size: 12px; color: #7a6a5a; font-weight: 400; }
        .toggle-switch { position: relative; width: 44px; height: 24px; flex-shrink: 0; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; inset: 0; background: #c9b5a5; border-radius: 24px; cursor: pointer; transition: 0.2s; }
        .toggle-slider::before { content: ''; position: absolute; width: 18px; height: 18px; left: 3px; top: 3px; background: white; border-radius: 50%; transition: 0.2s; }
        .toggle-switch input:checked + .toggle-slider { background: var(--primary); }
        .toggle-switch input:checked + .toggle-slider::before { transform: translateX(20px); }
        .btn-save { background: var(--primary); color: white; border: none; padding: 10px 28px; border-radius: 40px; font-size: 14px; font-weight: 500; cursor: pointer; transition: 0.15s; }
        .btn-save:hover { background: #163f62; }
        .btn-secondary { background: #e4d0bf; color: #3d2510; border: none; padding: 10px 24px; border-radius: 40px; font-size: 14px; font-weight: 500; cursor: pointer; transition: 0.15s; text-decoration: none; display: inline-block; }
        .btn-secondary:hover { background: #d9c4b2; color: #3d2510; }
        .alert { padding: 12px 18px; border-radius: 10px; font-size: 14px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .alert-error { background: #fdecea; color: #c62828; border: 1px solid #f5c6cb; }
        .moodle-status { display: flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 10px; background: #f0ece7; font-size: 13px; margin-bottom: 18px; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; background: #c9b5a5; flex-shrink: 0; }
        .status-dot.connected { background: #4caf50; }
        .theme-preview { border: 1px solid #c9b5a5; border-radius: 14px; overflow: hidden; background: <?= htmlspecialchars($config['background_color']) ?>; margin-top: 18px; }
        .theme-preview-top { background: white; border-bottom: 1px solid #c9b5a5; padding: 13px 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .theme-preview-brand { color: <?= htmlspecialchars($config['primary_color']) ?>; font-weight: 700; display: flex; align-items: center; gap: 8px; }
        .theme-preview-brand img { width: 26px; height: 26px; object-fit: contain; }
        .theme-preview-pill { background: #e4d0bf; color: #3d2510; border-radius: 30px; padding: 6px 12px; font-size: 12px; }
        .theme-preview-body { padding: 18px; }
        .theme-preview-button { background: <?= htmlspecialchars($config['primary_color']) ?>; color: white; border-radius: 30px; padding: 8px 14px; display: inline-flex; align-items: center; gap: 7px; font-size: 12px; margin-top: 10px; }
        .color-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
        .footer { text-align: center; padding: 24px; color: #6e4e3a; font-size: 12px; margin-top: 8px; }
        @media (max-width: 900px) { .settings-grid { grid-template-columns: 1fr; } .settings-card.full-width { grid-column: 1; } .color-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="topbar">
    <div class="topbar-logo">
        <?php if (trim($config['theme_logo_url'])): ?><img src="<?= htmlspecialchars($config['theme_logo_url']) ?>" alt="Logo"><?php else: ?><i class="fas fa-graduation-cap"></i><?php endif; ?>
        <?= htmlspecialchars($config['brand_label']) ?> <span>| Admin Module</span>
    </div>
    <div class="topbar-right">
        <span class="user-badge"><i class="fas fa-user-circle"></i> <?= htmlspecialchars(trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? '')) ?: ($admin['username'] ?? 'Administrator')) ?></span>
        <a href="auth/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Αποσύνδεση</a>
    </div>
</div>
<div class="sidebar">
    <div class="sidebar-nav">
        <a href="admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="users.php"><i class="fas fa-users"></i> Διαχείριση Χρηστών</a>
        <a href="recruitment.php"><i class="fas fa-bullhorn"></i> Διαχείριση Προκηρύξεων</a>
        <a href="system.php" class="active"><i class="fas fa-cog"></i> Ρυθμίσεις Συστήματος</a>
        <a href="reports.php"><i class="fas fa-chart-bar"></i> Αναφορές</a>
        <span class="nav-section-label">Enrollment</span>
        <a href="dashboard.php"><i class="fas fa-chalkboard-teacher"></i> Enrollment Dashboard</a>
        <a href="lms_sync.php"><i class="fas fa-sync-alt"></i> LMS Sync</a>
        <a href="full_sync.php"><i class="fas fa-database"></i> Full Sync</a>
    </div>
</div>
<div class="main-content">

    <div class="page-header">
        <h1><i class="fas fa-cog" style="color:<?= htmlspecialchars($config['accent_color']) ?>; margin-right:8px;"></i> Configure System</h1>
        <p>Βασικές παράμετροι συστήματος, σύνδεση με Moodle και διαχείριση λογαριασμού διαχειριστή</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
    <div class="settings-grid">

        <!-- General Settings -->
        <div class="settings-card">
            <div class="card-header">
                <i class="fas fa-university"></i>
                <h3>Γενικές Ρυθμίσεις</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label>Όνομα Συστήματος</label>
                    <input type="text" name="system_name" value="<?= htmlspecialchars($config['system_name']) ?>">
                </div>
                <div class="form-group">
                    <label>Όνομα Ιδρύματος</label>
                    <input type="text" name="institution_name" value="<?= htmlspecialchars($config['institution_name']) ?>">
                </div>
                <div class="form-group">
                    <label>Μέγιστος Αριθμός Αιτήσεων ανά Περίοδο</label>
                    <input type="number" name="max_applications_per_period" min="1" max="99" value="<?= htmlspecialchars($config['max_applications_per_period']) ?>">
                    <p class="hint">Πόσες αιτήσεις μπορεί να υποβάλει ένας υποψήφιος σε μία περίοδο προκήρυξης</p>
                </div>
                <div class="toggle-row">
                    <div class="toggle-label">
                        Αυτόματος Συγχρονισμός
                        <small>Αυτόματος συγχρονισμός με Moodle</small>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="auto_sync_enabled" <?= $config['auto_sync_enabled'] ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Theme Settings -->
        <div class="settings-card full-width">
            <div class="card-header">
                <i class="fas fa-palette"></i>
                <h3>Theme Settings</h3>
            </div>
            <div class="card-body">
                <div class="settings-grid">
                    <div>
                        <div class="form-group">
                            <label>Brand Label</label>
                            <input type="text" name="brand_label" value="<?= htmlspecialchars($config['brand_label']) ?>" placeholder="ΤΕΠΑΚ">
                            <p class="hint">Το σύντομο λεκτικό που εμφανίζεται στο topbar.</p>
                        </div>
                        <div class="form-group">
                            <label>Logo URL</label>
                            <input type="url" name="theme_logo_url" value="<?= htmlspecialchars($config['theme_logo_url']) ?>" placeholder="https://.../logo.png">
                            <p class="hint">Προαιρετικό λογότυπο. Αν μείνει κενό, εμφανίζεται το υπάρχον εικονίδιο.</p>
                        </div>
                        <div class="form-group">
                            <label>Footer Text</label>
                            <input type="text" name="footer_text" value="<?= htmlspecialchars($config['footer_text']) ?>">
                        </div>
                        <div class="color-row">
                            <div class="form-group">
                                <label>Primary Color</label>
                                <input type="color" name="primary_color" value="<?= htmlspecialchars($config['primary_color']) ?>">
                            </div>
                            <div class="form-group">
                                <label>Accent Color</label>
                                <input type="color" name="accent_color" value="<?= htmlspecialchars($config['accent_color']) ?>">
                            </div>
                            <div class="form-group">
                                <label>Background</label>
                                <input type="color" name="background_color" value="<?= htmlspecialchars($config['background_color']) ?>">
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="theme-preview">
                            <div class="theme-preview-top">
                                <div class="theme-preview-brand">
                                    <?php if (trim($config['theme_logo_url'])): ?><img src="<?= htmlspecialchars($config['theme_logo_url']) ?>" alt="Logo preview"><?php else: ?><i class="fas fa-graduation-cap"></i><?php endif; ?>
                                    <?= htmlspecialchars($config['brand_label']) ?> <span style="color:<?= htmlspecialchars($config['accent_color']) ?>; font-weight:400;">| Preview</span>
                                </div>
                                <div class="theme-preview-pill">Admin</div>
                            </div>
                            <div class="theme-preview-body">
                                <strong style="color:#2c2c2c; display:block;">Theme preview</strong>
                                <span style="color:#6e4e3a; font-size:13px;">Τα χρώματα και το λογότυπο εφαρμόζονται στις σελίδες που διαβάζουν τις ρυθμίσεις συστήματος.</span>
                                <div class="theme-preview-button"><i class="fas fa-check"></i> Primary action</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Moodle Connection -->
        <div class="settings-card">
            <div class="card-header">
                <i class="fas fa-plug"></i>
                <h3>Σύνδεση με Moodle</h3>
            </div>
            <div class="card-body">
                <?php
                $moodleOk = !empty($config['moodle_url']) && !empty($config['moodle_token']);
                ?>
                <div class="moodle-status">
                    <span class="status-dot <?= $moodleOk ? 'connected' : '' ?>"></span>
                    <?= $moodleOk ? 'Ρυθμίσεις σύνδεσης ορισμένες' : 'Δεν έχουν οριστεί στοιχεία σύνδεσης' ?>
                </div>
                <div class="form-group">
                    <label>Moodle URL</label>
                    <input type="url" name="moodle_url" placeholder="https://moodle.tepak.ac.cy" value="<?= htmlspecialchars($config['moodle_url']) ?>">
                    <p class="hint">Η διεύθυνση της εγκατεστημένης πλατφόρμας Moodle</p>
                </div>
                <div class="form-group">
                    <label>API Token</label>
                    <input type="password" name="moodle_token" placeholder="<?= $config['moodle_token'] ? '••••••••••••' : 'Εισάγετε token' ?>" value="<?= htmlspecialchars($config['moodle_token']) ?>">
                    <p class="hint">Web Service token από Moodle → Site Administration → Plugins → Web Services</p>
                </div>
                <div class="form-group">
                    <label>Service Name</label>
                    <input type="text" name="moodle_service" value="<?= htmlspecialchars($config['moodle_service']) ?>">
                    <p class="hint">Το όνομα του ενεργοποιημένου Web Service (π.χ. moodle_mobile_app)</p>
                </div>
            </div>
        </div>

    </div>

    <div style="margin-top: 20px; text-align: right;">
        <button type="submit" name="save_settings" class="btn-save"><i class="fas fa-save"></i> Αποθήκευση Ρυθμίσεων</button>
    </div>
    </form>

    <!-- Admin Profile & Password -->
    <div class="settings-grid" style="margin-top: 24px;">

        <div class="settings-card">
            <div class="card-header">
                <i class="fas fa-user-edit"></i>
                <h3>Στοιχεία Λογαριασμού</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                <div class="form-group">
                    <label>Email</label>
                    <input type="text" value="<?= htmlspecialchars($admin['email']) ?>" readonly>
                    <p class="hint">Το email δεν μπορεί να αλλάξει</p>
                </div>
                <div class="form-group">
                    <label>Όνομα</label>
                    <input type="text" name="first_name" value="<?= htmlspecialchars($admin['first_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Επώνυμο</label>
                    <input type="text" name="last_name" value="<?= htmlspecialchars($admin['last_name'] ?? '') ?>" required>
                </div>
                <button type="submit" name="save_profile" class="btn-save"><i class="fas fa-save"></i> Αποθήκευση</button>
                </form>
            </div>
        </div>

        <div class="settings-card">
            <div class="card-header">
                <i class="fas fa-lock"></i>
                <h3>Αλλαγή Κωδικού</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                <div class="form-group">
                    <label>Τρέχων Κωδικός</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label>Νέος Κωδικός</label>
                    <input type="password" name="new_password" required minlength="8">
                    <p class="hint">Τουλάχιστον 8 χαρακτήρες</p>
                </div>
                <div class="form-group">
                    <label>Επιβεβαίωση Νέου Κωδικού</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <button type="submit" name="change_password" class="btn-save"><i class="fas fa-key"></i> Αλλαγή Κωδικού</button>
                </form>
            </div>
        </div>

    </div>

    <div class="footer"><p>© <?= date('Y') ?> Τεχνολογικό Πανεπιστήμιο Κύπρου — <?= htmlspecialchars($config['footer_text']) ?></p></div>
</div>
</body>
</html>
