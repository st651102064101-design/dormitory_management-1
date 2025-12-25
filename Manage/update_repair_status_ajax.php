<?php
declare(strict_types=1);
session_start();

header('Content-Type: application/json');

if (empty($_SESSION['admin_username']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';

try {
    $pdo = connectDB();
    
    $repair_id = isset($_POST['repair_id']) ? (int)$_POST['repair_id'] : 0;
    $repair_status = isset($_POST['repair_status']) ? (string)$_POST['repair_status'] : '';
    
    // ตรวจสอบข้อมูล
    if ($repair_id <= 0 || empty($repair_status)) {
        echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ถูกต้อง']);
        exit;
    }
    
    // ตรวจสอบว่าสถานะใหม่เป็นค่าที่ถูกต้อง (1 = กำลังซ่อม, 2 = ซ่อมเสร็จแล้ว, 3 = ยกเลิก)
    if ($repair_status !== '1' && $repair_status !== '2' && $repair_status !== '3') {
        echo json_encode(['success' => false, 'error' => 'สถานะไม่ถูกต้อง']);
        exit;
    }
    
    // ตรวจสอบว่ารายการแจ้งซ่อมนี้มีอยู่หรือไม่ และดึงวันที่นัดหมาย
    $checkStmt = $pdo->prepare('SELECT repair_id, scheduled_date FROM repair WHERE repair_id = ?');
    $checkStmt->execute([$repair_id]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบรายการแจ้งซ่อม']);
        exit;
    }
    // หากจะเปลี่ยนสถานะเป็น 'กำลังซ่อม' (1) ต้องมีการนัดหมายก่อน
    if ($repair_status === '1') {
        $scheduledDate = $existing['scheduled_date'] ?? null;
        if (empty($scheduledDate)) {
            echo json_encode(['success' => false, 'error' => 'ต้องกำหนดนัดหมายก่อนจึงจะทำการซ่อมได้']);
            exit;
        }
    }
    
    // อัปเดตสถานะ
    $update = $pdo->prepare('UPDATE repair SET repair_status = ? WHERE repair_id = ?');
    $result = $update->execute([$repair_status, $repair_id]);
    
    // ข้อความสำเร็จตามสถานะ
    $statusMessages = [
        '1' => 'อัปเดตสถานะเป็น "ทำการซ่อม" แล้ว',
        '2' => 'อัปเดตสถานะเป็น "ซ่อมเสร็จแล้ว" แล้ว',
        '3' => 'ยกเลิกการแจ้งซ่อมแล้ว',
    ];
    $message = $statusMessages[$repair_status] ?? 'อัปเดตสถานะเรียบร้อย';
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode(['success' => false, 'error' => 'ไม่สามารถอัปเดตได้']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
exit;
