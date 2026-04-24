<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$host = "127.0.0.1";
$dbname = "tepak_ee_db";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode([]);
    exit;
}

$faculty_id = $_GET['faculty_id'] ?? 0;
$stmt = $pdo->prepare("SELECT id, dept_name, dept_name_el FROM departments WHERE faculty_id = ? ORDER BY dept_name_el");
$stmt->execute([$faculty_id]);
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($departments);
?>