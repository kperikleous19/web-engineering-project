<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: auth/login.php");
    exit;
}

require_once __DIR__ . "/includes/db.php";

$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalAdmins = $pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE r.role_name = 'admin'")->fetchColumn();
$totalCandidates = $pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE r.role_name = 'candidate'")->fetchColumn();

$search = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$orderBy = in_array($_GET['order'] ?? '', ['username', 'email', 'created_at']) ? $_GET['order'] : 'created_at';
$orderDir = ($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(u.username LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like, $like]);
}
if ($roleFilter !== '') {
    $where[] = "r.role_name = ?";
    $params[] = $roleFilter;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = $pdo->prepare("
    SELECT u.*, r.role_name
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    $whereSQL
    ORDER BY u.$orderBy $orderDir
");
$stmt->execute($params);
$users = $stmt->fetchAll();

$allRoles = $pdo->query("SELECT DISTINCT role_name FROM roles ORDER BY role_name")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Διαχείριση Χρηστών | ΤΕΠΑΚ Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #ece4da; color: #2c2c2c; }
        .topbar { background: white; border-bottom: 1px solid #c9b5a5; height: 64px; position: fixed; top: 0; left: 0; right: 0; z-index: 100; display: flex; align-items: center; justify-content: space-between; padding: 0 28px; }
        .topbar-logo { color: #1b4f78; font-weight: 700; font-size: 1.15rem; }
        .topbar-logo span { color: #7a4f2e; font-weight: 400; }
        .topbar-right { display: flex; align-items: center; gap: 10px; }
        .user-badge { background: #e4d0bf; padding: 7px 16px; border-radius: 40px; color: #3d2510; font-size: 13px; }
        .logout-btn { background: #e4d0bf; padding: 7px 16px; border-radius: 40px; text-decoration: none; color: #3d2510; font-size: 13px; transition: 0.15s; }
        .logout-btn:hover { background: #d9c4b2; }
        .sidebar { width: 250px; background: white; border-right: 1px solid #c9b5a5; height: calc(100vh - 64px); position: fixed; left: 0; top: 64px; overflow-y: auto; }
        .sidebar-nav { padding: 12px 0; }
        .sidebar-nav a { display: flex; align-items: center; gap: 11px; padding: 11px 22px; color: #5a5a5a; text-decoration: none; font-size: 13.5px; font-weight: 500; transition: 0.15s; margin: 2px 10px; border-radius: 10px; }
        .sidebar-nav a i { width: 18px; font-size: 15px; flex-shrink: 0; }
        .sidebar-nav a:hover { background: #f4f1ec; color: #1b4f78; }
        .sidebar-nav a.active { background: #1b4f78; color: white; }
        .nav-section-label { font-size: 10px; font-weight: 700; color: #a08070; text-transform: uppercase; letter-spacing: 1px; padding: 10px 22px 4px; margin-top: 6px; display: block; }
        .main-content { margin-left: 250px; margin-top: 64px; padding: 28px 32px; min-height: calc(100vh - 64px); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 16px; padding: 20px; border: 1px solid #c9b5a5; transition: all 0.2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.05); }
        .stat-card .stat-icon { font-size: 28px; color: #7a4f2e; margin-bottom: 12px; }
        .stat-card .stat-number { font-size: 28px; font-weight: 700; color: #1b4f78; margin-bottom: 5px; }
        .stat-card .stat-label { font-size: 13px; color: #6e4e3a; text-transform: uppercase; letter-spacing: 0.5px; }
        .content-card { background: white; border-radius: 20px; border: 1px solid #c9b5a5; overflow: hidden; }
        .card-header { padding: 20px 25px; border-bottom: 1px solid #c9b5a5; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; background: #efe6db; }
        .card-header h3 { margin: 0; font-size: 1.1rem; font-weight: 600; color: #2c2c2c; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 14px 20px; background: #f9f8f6; font-weight: 600; font-size: 12px; color: #5a5a5a; border-bottom: 1px solid #c9b5a5; }
        .data-table th a { color: inherit; text-decoration: none; }
        .data-table th a:hover { color: #1b4f78; }
        .data-table td { padding: 14px 20px; border-bottom: 1px solid #f0ece7; font-size: 13px; color: #3a3a3a; }
        .data-table tr:hover { background: #f9f8f6; }
        .badge-role { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge-admin { background: #1b4f78; color: white; }
        .badge-hr { background: #7a4f2e; color: white; }
        .badge-candidate { background: #6c9ebf; color: white; }
        .badge-evaluator { background: #a8c4a0; color: #2c2c2c; }
        .badge-ee { background: #e67e22; color: white; }
        .action-btns { display: flex; gap: 8px; }
        .btn-icon { padding: 6px 12px; border-radius: 8px; font-size: 12px; text-decoration: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .btn-edit { background: #f0e6df; color: #7a4f2e; }
        .btn-edit:hover { background: #e4d0bf; color: #7a4f2e; transform: translateY(-1px); }
        .btn-delete { background: #fdeaea; color: #dc3545; }
        .btn-delete:hover { background: #fcd5d5; color: #dc3545; transform: translateY(-1px); }
        .btn-add { background: #1b4f78; color: white; padding: 9px 20px; border-radius: 10px; text-decoration: none; font-size: 13px; font-weight: 500; transition: all 0.2s; display: inline-flex; align-items: center; gap: 7px; }
        .btn-add:hover { background: #1e4668; color: white; transform: translateY(-1px); }
        .search-bar { display: flex; gap: 10px; padding: 15px 20px; background: #f9f8f6; border-bottom: 1px solid #c9b5a5; flex-wrap: wrap; }
        .search-input { flex: 1; min-width: 200px; padding: 9px 14px; border: 1px solid #c9b5a5; border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 13px; }
        .search-input:focus { outline: none; border-color: #1b4f78; box-shadow: 0 0 0 3px rgba(27,79,120,0.1); }
        .filter-select { padding: 9px 14px; border: 1px solid #c9b5a5; border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 13px; background: white; }
        .btn-search { background: #1b4f78; color: white; border: none; padding: 9px 18px; border-radius: 10px; cursor: pointer; font-size: 13px; }
        .btn-clear { background: #e4d0bf; color: #3d2510; border: none; padding: 9px 14px; border-radius: 10px; cursor: pointer; font-size: 13px; text-decoration: none; }
        .results-info { padding: 10px 20px; font-size: 12px; color: #6e4e3a; background: #fffdf9; border-bottom: 1px solid #f0ece7; }
        @media (max-width: 768px) { .sidebar { display: none; } .main-content { margin-left: 0; } }
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
        <a href="users.php" class="active"><i class="fas fa-users"></i> Διαχείριση Χρηστών</a>
        <a href="recruitment.php"><i class="fas fa-bullhorn"></i> Διαχείριση Προκηρύξεων</a>
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
        <h1 style="font-size: 1.8rem; font-weight: 600; color: #2c2c2c;">Διαχείριση Χρηστών</h1>
        <p style="color: #6e4e3a; margin-top: 5px;">Διαχειριστείτε τους λογαριασμούς χρηστών του συστήματος</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-number"><?= $totalUsers ?></div>
            <div class="stat-label">Σύνολο Χρηστών</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-user-shield"></i></div>
            <div class="stat-number"><?= $totalAdmins ?></div>
            <div class="stat-label">Διαχειριστές</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
            <div class="stat-number"><?= $totalCandidates ?></div>
            <div class="stat-label">Υποψήφιοι</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar"></i></div>
            <div class="stat-number"><?= date('d/m/Y') ?></div>
            <div class="stat-label">Τελευταία Ενημέρωση</div>
        </div>
    </div>

    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-list" style="color:#7a4f2e;margin-right:8px"></i> Λίστα Χρηστών</h3>
            <a href="add_user.php" class="btn-add"><i class="fas fa-plus"></i> Νέος Χρήστης</a>
        </div>

        <form method="GET" action="users.php" class="search-bar">
            <input type="text" name="search" class="search-input" placeholder="Αναζήτηση ονόματος, email, username..." value="<?= htmlspecialchars($search) ?>">
            <select name="role" class="filter-select">
                <option value="">Όλοι οι ρόλοι</option>
                <?php foreach ($allRoles as $r): ?>
                <option value="<?= htmlspecialchars($r) ?>" <?= $roleFilter === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="order" class="filter-select">
                <option value="created_at" <?= $orderBy === 'created_at' ? 'selected' : '' ?>>Ταξινόμηση: Ημερομηνία</option>
                <option value="username" <?= $orderBy === 'username' ? 'selected' : '' ?>>Ταξινόμηση: Username</option>
                <option value="email" <?= $orderBy === 'email' ? 'selected' : '' ?>>Ταξινόμηση: Email</option>
            </select>
            <select name="dir" class="filter-select">
                <option value="desc" <?= $orderDir === 'DESC' ? 'selected' : '' ?>>Φθίνουσα</option>
                <option value="asc" <?= $orderDir === 'ASC' ? 'selected' : '' ?>>Αύξουσα</option>
            </select>
            <button type="submit" class="btn-search"><i class="fas fa-search"></i> Αναζήτηση</button>
            <?php if ($search || $roleFilter): ?>
            <a href="users.php" class="btn-clear"><i class="fas fa-times"></i> Καθαρισμός</a>
            <?php endif; ?>
        </form>

        <div class="results-info">
            <?= count($users) ?> χρήστες<?= $search ? ' για "' . htmlspecialchars($search) . '"' : '' ?><?= $roleFilter ? ' · Ρόλος: ' . ucfirst($roleFilter) : '' ?>
        </div>

        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th><a href="?search=<?= urlencode($search) ?>&role=<?= urlencode($roleFilter) ?>&order=username&dir=<?= $orderBy === 'username' && $orderDir === 'ASC' ? 'desc' : 'asc' ?>">Username <?= $orderBy === 'username' ? ($orderDir === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                        <th><a href="?search=<?= urlencode($search) ?>&role=<?= urlencode($roleFilter) ?>&order=email&dir=<?= $orderBy === 'email' && $orderDir === 'ASC' ? 'desc' : 'asc' ?>">Email <?= $orderBy === 'email' ? ($orderDir === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                        <th>Ονοματεπώνυμο</th>
                        <th>Τηλέφωνο</th>
                        <th>Ρόλος</th>
                        <th><a href="?search=<?= urlencode($search) ?>&role=<?= urlencode($roleFilter) ?>&order=created_at&dir=<?= $orderBy === 'created_at' && $orderDir === 'ASC' ? 'desc' : 'asc' ?>">Ημ. Εγγραφής <?= $orderBy === 'created_at' ? ($orderDir === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                        <th>Ενέργειες</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                            <td><?= htmlspecialchars($user['email'] ?? '—') ?></td>
                            <td><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: '—') ?></td>
                            <td><?= htmlspecialchars($user['phone'] ?? '—') ?></td>
                            <td>
                                <span class="badge-role badge-<?= htmlspecialchars($user['role_name'] ?? 'candidate') ?>">
                                    <?= ucfirst($user['role_name'] ?? 'Candidate') ?>
                                </span>
                            </td>
                            <td><?= $user['created_at'] ? date('d/m/Y', strtotime($user['created_at'])) : '—' ?></td>
                            <td>
                                <div class="action-btns">
                                    <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn-icon btn-edit">
                                        <i class="fas fa-edit"></i> Επεξεργασία
                                    </a>
                                    <?php if ($user['role_name'] !== 'admin'): ?>
                                    <a href="delete_user.php?id=<?= $user['id'] ?>" class="btn-icon btn-delete" onclick="return confirm('Σίγουρα θέλετε να διαγράψετε αυτόν τον χρήστη;')">
                                        <i class="fas fa-trash"></i> Διαγραφή
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align:center; padding: 50px; color: #6e4e3a;">
                                <i class="fas fa-search" style="font-size: 40px; margin-bottom: 12px; display: block; color: #ccc;"></i>
                                Δεν βρέθηκαν χρήστες<?= $search ? ' για "' . htmlspecialchars($search) . '"' : '' ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
