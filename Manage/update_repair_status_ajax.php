<?php
session_start();

header('Content-Type: application/json');

if (empty($_SESSION['admin_username']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $pdo = new PDO("mysql:host=localhost:3306;dbname=dormitory_management_db;charset=utf8mb4", 'root', '12345678');
    
    $repair_id = isset($_POST['repair_id']) ? (int)$_POST['repair_id'] : 0;
    $repair_status = $_POST['repair_status'] ?? '';
    
    if ($repair_id <= 0 || $repair_status !== '1') {
        echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ถูกต้อง']);
        exit;
    }
    
    $update = $pdo->prepare('UPDATE repair SET repair_status = ? WHERE repair_id = ?');
    $result = $update->execute(['1', $repair_id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'อัปเดตสถานะเป็น "ทำการซ่อม" แล้ว']);
    } else {
        echo json_encode(['success' => false, 'error' => 'ไม่สามารถอัปเดตได้']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
exit;
