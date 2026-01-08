<?php
session_start();
require_once 'ConnectDB.php';

header('Content-Type: application/json');

// ตรวจสอบว่าล็อกอินอยู่หรือไม่
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$adminId = $_SESSION['admin_id'];

try {
    $pdo = connectDB();
    
    // ตรวจสอบว่ามีการเชื่อม Google อยู่หรือไม่
    $checkStmt = $pdo->prepare("SELECT oauth_id FROM admin_oauth WHERE admin_id = ? AND provider = 'google'");
    $checkStmt->execute([$adminId]);
    
    if ($checkStmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบการเชื่อมต่อบัญชี Google']);
        exit;
    }
    
    // ลบการเชื่อมต่อ Google
    $deleteStmt = $pdo->prepare("DELETE FROM admin_oauth WHERE admin_id = ? AND provider = 'google'");
    $deleteStmt->execute([$adminId]);
    
    // ลบ picture จาก session
    if (isset($_SESSION['admin_picture'])) {
        unset($_SESSION['admin_picture']);
    }
    
    echo json_encode(['success' => true, 'message' => 'ถอนการเชื่อมต่อบัญชี Google เรียบร้อยแล้ว']);
    exit;
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    exit;
}
