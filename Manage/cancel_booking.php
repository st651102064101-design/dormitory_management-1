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
    $stmtBooking = $pdo->prepare("SELECT room_id FROM booking WHERE bkg_id = ?");
    $stmtBooking->execute([$bkg_id]);
    $booking = $stmtBooking->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        $pdo->rollBack();
        die(json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลการจอง']));
    }
    
    $room_id = $booking['room_id'];
    
    // 2. ดึง contract_id จาก tenant_workflow (ถ้ามี)
    $stmtWorkflow = $pdo->prepare("SELECT id, ctr_id FROM tenant_workflow WHERE bkg_id = ?");
    $stmtWorkflow->execute([$bkg_id]);
    $workflow = $stmtWorkflow->fetch(PDO::FETCH_ASSOC);
    $ctr_id = $workflow['ctr_id'] ?? null;
    
    // 3. ดึง expense_id จาก contract (ถ้ามี)
    $exp_ids = [];
    if ($ctr_id) {
        $stmtExpense = $pdo->prepare("SELECT exp_id FROM expense WHERE ctr_id = ?");
        $stmtExpense->execute([$ctr_id]);
        $expenses = $stmtExpense->fetchAll(PDO::FETCH_COLUMN);
        $exp_ids = $expenses;
    }
    
    // 4. ลบข้อมูลตามลำดับ (เพื่อไม่ให้ติด foreign key constraint)
    
    // ลบ payment ที่เกี่ยวข้อง
    if (!empty($exp_ids)) {
        $placeholders = implode(',', array_fill(0, count($exp_ids), '?'));
        $stmtDelPayment = $pdo->prepare("DELETE FROM payment WHERE exp_id IN ($placeholders)");
        $stmtDelPayment->execute($exp_ids);
    }
    
    // ลบ booking_payment
    $stmtDelBP = $pdo->prepare("DELETE FROM booking_payment WHERE bkg_id = ?");
    $stmtDelBP->execute([$bkg_id]);
    
    // ลบ expense ที่เกี่ยวข้อง
    if ($ctr_id) {
        $stmtDelExpense = $pdo->prepare("DELETE FROM expense WHERE ctr_id = ?");
        $stmtDelExpense->execute([$ctr_id]);
    }
    
    // ลบ tenant_workflow
    $stmtDelWorkflow = $pdo->prepare("DELETE FROM tenant_workflow WHERE bkg_id = ?");
    $stmtDelWorkflow->execute([$bkg_id]);
    
    // ลบ contract (ถ้ามี)
    if ($ctr_id) {
        $stmtDelContract = $pdo->prepare("DELETE FROM contract WHERE ctr_id = ?");
        $stmtDelContract->execute([$ctr_id]);
    }
    
    // ลบ booking
    $stmtDelBooking = $pdo->prepare("DELETE FROM booking WHERE bkg_id = ?");
    $stmtDelBooking->execute([$bkg_id]);
    
    // ลบ tenant (ถ้าต้องการ - optional, อาจเก็บไว้สำหรับประวัติ)
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
