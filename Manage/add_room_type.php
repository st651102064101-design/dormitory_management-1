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

$typeName = trim($_POST['type_name'] ?? '');
$typePrice = preg_replace('/[^0-9]/', '', $_POST['type_price'] ?? '');

if ($typeName === '' || $typePrice === '') {
    echo json_encode(['success' => false, 'message' => 'กรอกข้อมูลไม่ครบ']);
    exit;
}

try {
    $pdo = connectDB();
    $stmt = $pdo->prepare('INSERT INTO roomtype (type_name, type_price) VALUES (?, ?)');
    $stmt->execute([$typeName, $typePrice]);
    $id = $pdo->lastInsertId();
    $label = sprintf('%s (%s บาท/เดือน)', $typeName, number_format((int)$typePrice));
    echo json_encode(['success' => true, 'id' => $id, 'label' => $label]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด']);
}
