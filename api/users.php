<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

require_once '../includes/db.php';

$stmt = $pdo->query("
    SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.phone, u.role, r.role_name, u.created_at, u.last_login
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    ORDER BY u.created_at DESC
");

$users = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'count'   => count($users),
    'data'    => $users
]);