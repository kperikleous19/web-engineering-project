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
    SELECT rp.*, u.first_name, u.last_name
    FROM recruitment_periods rp
    JOIN users u ON rp.created_by = u.id
    ORDER BY rp.created_at DESC
");

$periods = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'count'   => count($periods),
    'data'    => $periods
]);