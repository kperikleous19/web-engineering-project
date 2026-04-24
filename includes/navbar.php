<nav class="navbar navbar-expand-lg" style="background: white; border-bottom: 1px solid #e9dfd7;">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../index.php" style="color: #2c5f8a;">
            <i class="fas fa-graduation-cap"></i> ΤΕΠΑΚ
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav me-auto">
                <?php $role = strtolower($_SESSION['role'] ?? ''); ?>

                <?php if ($role === 'hr' || $role === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="../enrollment/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="../enrollment/applications.php"><i class="fas fa-file-alt"></i> Αιτήσεις</a></li>
                    <li class="nav-item"><a class="nav-link" href="../lms_sync.php"><i class="fas fa-sync"></i> LMS Sync</a></li>
                    <li class="nav-item"><a class="nav-link" href="../reports.php"><i class="fas fa-chart-bar"></i> Αναφορές</a></li>
                <?php endif; ?>

                <?php if ($role === 'candidate'): ?>
                    <li class="nav-item"><a class="nav-link" href="../recruitment/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="../enrollment/my_applications.php"><i class="fas fa-file-alt"></i> Οι Αιτήσεις μου</a></li>
                    <li class="nav-item"><a class="nav-link" href="../enrollment/application_status.php"><i class="fas fa-chart-line"></i> Κατάσταση</a></li>
                    <li class="nav-item"><a class="nav-link" href="../enrollment/my_profile.php"><i class="fas fa-user"></i> Προφίλ</a></li>
                <?php endif; ?>

                <?php if ($role === 'evaluator'): ?>
                    <li class="nav-item"><a class="nav-link" href="../recruitment/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="../enrollment/applications.php"><i class="fas fa-file-alt"></i> Αιτήσεις</a></li>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item">
                    <span class="nav-link" style="color: #8a7163;">
                        <i class="fas fa-user-circle"></i>
                        <?= htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? $_SESSION['username'] ?? '')) ?>
                    </span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../auth/logout.php" style="color: #dc3545;">
                        <i class="fas fa-sign-out-alt"></i> Αποσύνδεση
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>