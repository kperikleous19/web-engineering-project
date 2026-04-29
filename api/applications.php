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

// PATCH - update application status / evaluation
if ($_SERVER['REQUEST_METHOD'] === 'PATCH' || $_SERVER['REQUEST_METHOD'] === 'PUT') {

    if (!in_array($role, ['admin', 'evaluator', 'hr'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Insufficient permissions']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Application id is required']);
        exit;
    }

    $id = (int) $input['id'];

    // Build SET clauses based on provided fields
    $allowed_status = ['draft', 'pending', 'under_review', 'approved', 'rejected'];
    $sets = [];
    $params = [];

    if (isset($input['status'])) {
        if (!in_array($input['status'], $allowed_status)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid status. Allowed: ' . implode(', ', $allowed_status)]);
            exit;
        }
        $sets[] = 'status = ?';
        $params[] = $input['status'];
    }

    if (isset($input['reviewer_comments'])) {
        $sets[] = 'reviewer_comments = ?';
        $params[] = $input['reviewer_comments'];
    }

    if (in_array($role, ['evaluator', 'admin']) && isset($input['evaluator_score'])) {
        $sets[] = 'evaluator_score = ?';
        $params[] = (int) $input['evaluator_score'];
    }

    if (in_array($role, ['evaluator', 'admin']) && isset($input['evaluator_notes'])) {
        $sets[] = 'evaluator_notes = ?';
        $params[] = $input['evaluator_notes'];
    }

    if (empty($sets)) {
        http_response_code(400);
        echo json_encode(['error' => 'No updatable fields provided (status, reviewer_comments, evaluator_score, evaluator_notes)']);
        exit;
    }

    $sets[] = 'updated_at = NOW()';
    $params[] = $id;

    $stmt = $pdo->prepare('UPDATE applications SET ' . implode(', ', $sets) . ' WHERE id = ?');
    $stmt->execute($params);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Application not found']);
        exit;
    }

    $stmt2 = $pdo->prepare('SELECT a.*, u.first_name, u.last_name, u.email FROM applications a JOIN users u ON a.user_id = u.id WHERE a.id = ?');
    $stmt2->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'Application updated',
        'data'    => $stmt2->fetch()
    ]);
    exit;
}

// DELETE - remove application (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

    if ($role !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Only admins can delete applications via API']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int) ($input['id'] ?? 0);

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Application id is required']);
        exit;
    }

    $stmt = $pdo->prepare('DELETE FROM applications WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Application not found']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Application deleted']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed. Supported: GET, POST, PATCH, DELETE']);