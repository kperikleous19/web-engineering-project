<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

require_once __DIR__ . '/includes/db.php';

$faculty_id = $_GET['faculty_id'] ?? 0;
$stmt = $pdo->prepare("SELECT id, dept_name, dept_name_el FROM departments WHERE faculty_id = ? ORDER BY dept_name_el");
$stmt->execute([$faculty_id]);
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($departments);
?>