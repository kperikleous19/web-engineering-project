<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

require_once __DIR__ . '/includes/db.php';

$department_id = $_GET['department_id'] ?? 0;
$stmt = $pdo->prepare("SELECT id, course_code, course_name FROM courses WHERE department_id = ? ORDER BY course_code");
$stmt->execute([$department_id]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($courses);
?>