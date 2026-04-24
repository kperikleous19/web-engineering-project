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

// GET - list applications
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    if (in_array($role, ['admin', 'evaluator', 'hr'])) {
        $stmt = $pdo->query("
            SELECT a.*, u.first_name, u.last_name, u.email
            FROM applications a
            JOIN users u ON a.user_id = u.id
            ORDER BY a.created_at DESC
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT a.*, u.first_name, u.last_name, u.email
            FROM applications a
            JOIN users u ON a.user_id = u.id
            WHERE a.user_id = ?
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$user_id]);
    }

    $applications = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'count'   => count($applications),
        'data'    => $applications
    ]);
    exit;
}

// POST - submit new application
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($role !== 'candidate') {
        http_response_code(403);
        echo json_encode(['error' => 'Only candidates can submit applications']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['course_name']) || empty($input['department_name']) || empty($input['school_name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'course_name, department_name and school_name are required']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO applications (user_id, course, department, course_name, department_name, school_name, status, completion_percentage)
        VALUES (?, ?, ?, ?, ?, ?, 'pending', 0)
    ");

    $stmt->execute([
        $user_id,
        $input['course_name'],
        $input['department_name'],
        $input['course_name'],
        $input['department_name'],
        $input['school_name']
    ]);

    $new_id = $pdo->lastInsertId();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Η αίτηση υποβλήθηκε επιτυχώς',
        'application_id' => $new_id
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);