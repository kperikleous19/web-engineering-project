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

// GET - list evaluations
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $stmt = $pdo->query("
        SELECT a.id AS application_id, a.course_name, a.department_name, a.status,
               a.reviewer_comments, a.updated_at,
               u.first_name, u.last_name, u.email
        FROM applications a
        JOIN users u ON a.user_id = u.id
        ORDER BY a.created_at DESC
    ");

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

    $allowed_statuses = ['pending', 'under_review', 'approved', 'rejected'];
    if (!in_array($input['status'], $allowed_statuses)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid status. Allowed: ' . implode(', ', $allowed_statuses)]);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE applications
        SET status = ?, reviewer_comments = ?, updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $input['status'],
        $input['reviewer_comments'] ?? null,
        $input['application_id']
    ]);

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