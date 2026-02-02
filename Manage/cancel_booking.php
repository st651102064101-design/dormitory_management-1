<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['admin_username'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'ไม่ได้รับอนุญาต']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'error' => 'Method not allowed']));
}

require_once __DIR__ . '/../ConnectDB.php';

try {
    $pdo = connectDB();
    
    $bkg_id = isset($_POST['bkg_id']) ? (int)$_POST['bkg_id'] : 0;
    $tnt_id = $_POST['tnt_id'] ?? '';
    
    if ($bkg_id <= 0) {
        die(json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลการจอง']));
    }
    
    $pdo->beginTransaction();
    
    // 1. ดึงข้อมูลที่เกี่ยวข้องจาก booking
    $stmtBooking = $pdo->prepare("SELECT room_id, tnt_id FROM booking WHERE bkg_id = ?");
    $stmtBooking->execute([$bkg_id]);
    $booking = $stmtBooking->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        $pdo->rollBack();
        die(json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลการจอง']));
    }
    
    $room_id = $booking['room_id'];
    $tnt_id = $tnt_id ?: $booking['tnt_id']; // ใช้จาก booking ถ้าไม่ได้ส่งมา
    
    // 2. ดึง contract_id จาก tenant_workflow (ถ้ามี)
    $ctr_id = null;
    $stmtWorkflow = $pdo->prepare("SELECT id, ctr_id FROM tenant_workflow WHERE bkg_id = ?");
    $stmtWorkflow->execute([$bkg_id]);
    $workflow = $stmtWorkflow->fetch(PDO::FETCH_ASSOC);
    if ($workflow && $workflow['ctr_id']) {
        $ctr_id = $workflow['ctr_id'];
    }
    
    // 2.1 ถ้าไม่มีใน workflow ลองหาจาก contract โดยตรง (ผ่าน tnt_id และ room_id)
    if (!$ctr_id && $tnt_id && $room_id) {
        $stmtContract = $pdo->prepare("SELECT ctr_id FROM contract WHERE tnt_id = ? AND room_id = ? ORDER BY ctr_id DESC LIMIT 1");
        $stmtContract->execute([$tnt_id, $room_id]);
        $contract = $stmtContract->fetch(PDO::FETCH_ASSOC);
        if ($contract) {
            $ctr_id = $contract['ctr_id'];
        }
    }
    
    // 3. ดึง expense_id จาก contract (ถ้ามี)
    $exp_ids = [];
    if ($ctr_id) {
        $stmtExpense = $pdo->prepare("SELECT exp_id FROM expense WHERE ctr_id = ?");
        $stmtExpense->execute([$ctr_id]);
        $expenses = $stmtExpense->fetchAll(PDO::FETCH_COLUMN);
        $exp_ids = $expenses;
    }
    
    // ========== เริ่มลบข้อมูลตามลำดับ (เพื่อไม่ให้ติด foreign key constraint) ==========
    
    // 4.0 ดึงข้อมูลไฟล์รูปภาพสลิปเพื่อลบ
    $filesToDelete = [];
    
    // ดึงไฟล์จาก payment
    if (!empty($exp_ids)) {
        $placeholders = implode(',', array_fill(0, count($exp_ids), '?'));
        $stmtGetPayFiles = $pdo->prepare("SELECT pay_proof FROM payment WHERE exp_id IN ($placeholders) AND pay_proof IS NOT NULL");
        $stmtGetPayFiles->execute($exp_ids);
        $payFiles = $stmtGetPayFiles->fetchAll(PDO::FETCH_COLUMN);
        $filesToDelete = array_merge($filesToDelete, $payFiles);
    }
    
    // ดึงไฟล์จาก booking_payment
    $stmtGetBPFiles = $pdo->prepare("SELECT bp_proof FROM booking_payment WHERE bkg_id = ? AND bp_proof IS NOT NULL");
    $stmtGetBPFiles->execute([$bkg_id]);
    $bpFiles = $stmtGetBPFiles->fetchAll(PDO::FETCH_COLUMN);
    $filesToDelete = array_merge($filesToDelete, $bpFiles);
    
    // 4.1 ลบ payment ที่เกี่ยวข้องกับ expense
    if (!empty($exp_ids)) {
        $placeholders = implode(',', array_fill(0, count($exp_ids), '?'));
        $stmtDelPayment = $pdo->prepare("DELETE FROM payment WHERE exp_id IN ($placeholders)");
        $stmtDelPayment->execute($exp_ids);
    }
    
    // 4.2 ลบ booking_payment
    $stmtDelBP = $pdo->prepare("DELETE FROM booking_payment WHERE bkg_id = ?");
    $stmtDelBP->execute([$bkg_id]);
    
    // 4.3 ลบ checkin_record (ถ้ามี ctr_id)
    if ($ctr_id) {
        $stmtDelCheckin = $pdo->prepare("DELETE FROM checkin_record WHERE ctr_id = ?");
        $stmtDelCheckin->execute([$ctr_id]);
    }
    
    // 4.4 ลบ expense ที่เกี่ยวข้อง
    if ($ctr_id) {
        $stmtDelExpense = $pdo->prepare("DELETE FROM expense WHERE ctr_id = ?");
        $stmtDelExpense->execute([$ctr_id]);
    }
    
    // 4.5 ลบ tenant_workflow
    $stmtDelWorkflow = $pdo->prepare("DELETE FROM tenant_workflow WHERE bkg_id = ?");
    $stmtDelWorkflow->execute([$bkg_id]);
    
    // 4.6 ลบ contract (ถ้ามี)
    if ($ctr_id) {
        $stmtDelContract = $pdo->prepare("DELETE FROM contract WHERE ctr_id = ?");
        $stmtDelContract->execute([$ctr_id]);
    }
    
    // 4.7 ลบ booking
    $stmtDelBooking = $pdo->prepare("DELETE FROM booking WHERE bkg_id = ?");
    $stmtDelBooking->execute([$bkg_id]);
    
    // 4.8 ลบ tenant (ถ้าไม่มี booking อื่น)
    if (!empty($tnt_id)) {
        // ตรวจสอบว่า tenant มี booking อื่นหรือไม่
        $stmtCheckTenant = $pdo->prepare("SELECT COUNT(*) FROM booking WHERE tnt_id = ?");
        $stmtCheckTenant->execute([$tnt_id]);
        $otherBookings = (int)$stmtCheckTenant->fetchColumn();
        
        // ลบ tenant เฉพาะเมื่อไม่มี booking อื่น
        if ($otherBookings === 0) {
            $stmtDelTenant = $pdo->prepare("DELETE FROM tenant WHERE tnt_id = ?");
            $stmtDelTenant->execute([$tnt_id]);
        }
    }
    
    // 5. อัพเดทสถานะห้องเป็นว่าง
    $stmtUpdateRoom = $pdo->prepare("UPDATE room SET room_status = '0' WHERE room_id = ?");
    $stmtUpdateRoom->execute([$room_id]);
    
    $pdo->commit();
    
    // 6. ลบไฟล์รูปภาพสลิป (หลัง commit แล้วถึงลบไฟล์)
    $uploadBaseDir = __DIR__ . '/../';
    foreach ($filesToDelete as $filePath) {
        if (!empty($filePath)) {
            $fullPath = $uploadBaseDir . $filePath;
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'ยกเลิกการจองเรียบร้อยแล้ว'
    ]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Cancel Booking Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดในฐานข้อมูล: ' . $e->getMessage()]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Cancel Booking Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
?>
