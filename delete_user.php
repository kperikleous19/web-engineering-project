<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: auth/login.php");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: users.php");
    exit;
}

$id = (int) $_GET['id'];

if ($id === (int) $_SESSION['user_id']) {
    header("Location: users.php?error=self_delete");
    exit;
}

require_once __DIR__ . "/includes/db.php";

$stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
$stmt->execute([$id]);

header("Location: users.php?deleted=1");
exit;