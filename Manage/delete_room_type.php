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

require_once __DIR__ . '/../ConnectDB.php';

$typeId = $_POST['type_id'] ?? '';
if ($typeId === '') {
    echo json_encode(['success' => false, 'message' => 'ไม่พบรหัสประเภทห้อง']);
    exit;
}

try {
    $pdo = connectDB();
    // ลองลบประเภทห้อง หากติด constraint จะโยน exception
    $stmt = $pdo->prepare('DELETE FROM roomtype WHERE type_id = ?');
    $stmt->execute([$typeId]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบประเภทห้องนี้']);
        exit;
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    // ถ้าลบไม่ได้เพราะมีห้องใช้งาน ให้แจ้งเตือน
    $message = 'ลบไม่ได้: มีห้องใช้งานประเภทนี้อยู่';
    echo json_encode(['success' => false, 'message' => $message]);
}
