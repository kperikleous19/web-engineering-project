<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../includes/db.php';

$stmt = $pdo->query("
    SELECT c.id, c.course_code, c.course_name, c.created_at,
           d.dept_name_el AS department
    FROM courses c
    JOIN departments d ON c.department_id = d.id
    ORDER BY d.dept_name_el, c.course_code
");

$courses = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'count'   => count($courses),
    'data'    => $courses
]);