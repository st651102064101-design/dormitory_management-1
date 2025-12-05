<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_username'])) {
    echo json_encode(['success' => false, 'message' => 'unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'invalid method']);
    exit;
}

$rate_id = isset($_POST['rate_id']) ? (int)$_POST['rate_id'] : 0;
if ($rate_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ระบุเรตไม่ถูกต้อง']);
    exit;
}

try {
    require_once __DIR__ . '/../ConnectDB.php';
    $pdo = connectDB();

    $stmt = $pdo->prepare('DELETE FROM rate WHERE rate_id = ?');
    $stmt->execute([$rate_id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
