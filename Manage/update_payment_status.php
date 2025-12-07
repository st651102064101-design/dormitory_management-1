<?php
declare(strict_types=1);
session_start();

// ตรวจสอบสิทธิ์การเข้าถึง
if (empty($_SESSION['admin_username'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

header('Content-Type: application/json');

// รับข้อมูลจาก POST
$payId = $_POST['pay_id'] ?? '';
$payStatus = $_POST['pay_status'] ?? '';

// ตรวจสอบข้อมูลที่จำเป็น
if (empty($payId)) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบรหัสการชำระเงิน']);
    exit;
}

if (!in_array($payStatus, ['0', '1'], true)) {
    echo json_encode(['success' => false, 'error' => 'สถานะไม่ถูกต้อง']);
    exit;
}

try {
    // ตรวจสอบว่ามีรายการชำระเงินนี้อยู่จริง
    $checkStmt = $pdo->prepare("SELECT pay_id FROM payment WHERE pay_id = ?");
    $checkStmt->execute([$payId]);
    
    if (!$checkStmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบรายการชำระเงินนี้']);
        exit;
    }

    // อัปเดตสถานะ
    $updateStmt = $pdo->prepare("UPDATE payment SET pay_status = ? WHERE pay_id = ?");
    $updateStmt->execute([$payStatus, $payId]);

    $statusText = $payStatus === '1' ? 'ตรวจสอบแล้ว' : 'รอตรวจสอบ';
    echo json_encode([
        'success' => true,
        'message' => 'อัปเดตสถานะเป็น "' . $statusText . '" สำเร็จ'
    ]);
    exit;

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    exit;
}
?>
