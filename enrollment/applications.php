<?php
require_once '../includes/auth.php';
requireRole(['Candidate', 'HR', 'Evaluator']);
require_once '../includes/functions.php';

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("
    SELECT a.*, ann.title_el, ann.application_start, ann.application_end,
           c.course_code, COALESCE(a.course_name, c.course_name, a.course) AS display_course
    FROM applications a
    LEFT JOIN announcements ann ON a.announcement_id = ann.id
    LEFT JOIN courses c ON ann.course_id = c.id
    WHERE a.user_id = ?
    ORDER BY a.created_at DESC
");
$stmt->execute([$user_id]);
$applications = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <title>Οι Αιτήσεις μου</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container mt-4">
        <h2>Οι Αιτήσεις μου</h2>
        <?php if (count($applications) == 0): ?>
            <div class="alert alert-info">Δεν έχετε υποβάλει καμία αίτηση.</div>
            <a href="my_applications.php" class="btn btn-primary">Υποβολή νέας αίτησης</a>
        <?php else: ?>
            <a href="my_applications.php" class="btn btn-primary mb-3">Νέα αίτηση</a>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Προκήρυξη</th>
                        <th>Μάθημα</th>
                        <th>Κατάσταση</th>
                        <th>Πρόοδος</th>
                        <th>Ημ. Υποβολής</th>
                        <th>Ενέργεια</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($applications as $app): ?>
                    <tr>
                        <td><?= sanitize($app['title_el'] ?? 'Χωρίς προκήρυξη') ?></td>
                        <td><?= sanitize(trim(($app['course_code'] ?? '') . ' - ' . $app['display_course'], ' -')) ?></td>
                        <td>
                            <?php
                            $statusClass = [
                                'draft' => 'secondary',
                                'pending' => 'info',
                                'under_review' => 'warning',
                                'approved' => 'success',
                                'rejected' => 'danger',
                                'withdrawn' => 'dark'
                            ];
                            $status = $app['status'];
                            ?>
                            <span class="badge bg-<?= $statusClass[$status] ?? 'secondary' ?>">
                                <?= $status ?>
                            </span>
                        </td>
                        <td>
                            <div class="progress">
                                <div class="progress-bar" role="progressbar" style="width: <?= $app['completion_percentage'] ?>%;" aria-valuenow="<?= $app['completion_percentage'] ?>" aria-valuemin="0" aria-valuemax="100">
                                    <?= $app['completion_percentage'] ?>%
                                </div>
                            </div>
                        </td>
                        <td><?= $app['created_at'] ? date('d/m/Y', strtotime($app['created_at'])) : '-' ?></td>
                        <td>
                            <?php if ($app['status'] == 'draft'): ?>
                                <a href="my_applications.php" class="btn btn-sm btn-primary">Συνέχεια</a>
                            <?php else: ?>
                                <a href="application_status.php" class="btn btn-sm btn-info">Προβολή</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
