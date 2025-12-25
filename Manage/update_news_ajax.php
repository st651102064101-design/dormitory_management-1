<?php
session_start();

header('Content-Type: application/json');

if (empty($_SESSION['admin_username']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]);
    exit;
}

try {
    $pdo = new PDO("mysql:host=localhost:3306;dbname=dormitory_management_db;charset=utf8mb4", 'root', '12345678');
    
    $id = (int)($_POST['news_id'] ?? 0);
    $title = trim($_POST['news_title'] ?? '');
    $details = trim($_POST['news_details'] ?? '');
    $date = $_POST['news_date'] ?? '';
    $by = trim($_POST['news_by'] ?? '');
    
    if ($id <= 0 || !$title || !$details || !$date) {
        echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบ']);
        exit;
    }
    
    $stmt = $pdo->prepare("UPDATE news SET news_title=?, news_details=?, news_date=?, news_by=? WHERE news_id=?");
    $result = $stmt->execute([$title, $details, $date, $by ?: null, $id]);
    
    echo json_encode(['success' => $result, 'message' => 'แก้ไขสำเร็จ']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'ผิดพลาด']);
}
exit;