<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../includes/db.php';

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

if (!in_array($role, ['admin', 'evaluator'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Evaluator or admin access required']);
    exit;
}

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

// GET - list evaluations
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    if ($role === 'evaluator') {
        $stmt = $pdo->prepare("
            SELECT a.id AS application_id, a.course_name, a.department_name, a.status,
                   a.reviewer_comments, a.evaluator_score, a.evaluator_notes, a.updated_at,
                   u.first_name, u.last_name, u.email
            FROM applications a
            JOIN users u ON a.user_id = u.id
            WHERE a.evaluator_id = ?
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$user_id]);
    } else {
        $stmt = $pdo->query("
            SELECT a.id AS application_id, a.course_name, a.department_name, a.status,
                   a.reviewer_comments, a.evaluator_score, a.evaluator_notes, a.updated_at,
                   u.first_name, u.last_name, u.email
            FROM applications a
            JOIN users u ON a.user_id = u.id
            ORDER BY a.created_at DESC
        ");
    }

    $evaluations = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'count'   => count($evaluations),
        'data'    => $evaluations
    ]);
    exit;
}

// POST - submit evaluation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($role !== 'evaluator') {
        http_response_code(403);
        echo json_encode(['error' => 'Only evaluators can submit evaluations']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['application_id']) || empty($input['status'])) {
        http_response_code(400);
        echo json_encode(['error' => 'application_id and status are required']);
        exit;
    }

    $allowed_statuses = ['pending', 'under_review'];
    if (!in_array($input['status'], $allowed_statuses)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid status. Evaluators can only mark assigned applications as pending or under_review. HR approves or rejects.']);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE applications
        SET status = ?, evaluator_notes = ?, evaluator_score = ?, updated_at = NOW()
        WHERE id = ? AND evaluator_id = ?
    ");

    $stmt->execute([
        $input['status'],
        $input['evaluator_notes'] ?? ($input['reviewer_comments'] ?? null),
        $input['evaluator_score'] ?? null,
        $input['application_id'],
        $user_id
    ]);

    if ($stmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Application is not assigned to this evaluator']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Η αξιολόγηση καταχωρήθηκε επιτυχώς',
        'application_id' => $input['application_id'],
        'new_status' => $input['status']
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
