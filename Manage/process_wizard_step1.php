<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../Reports/tenant_wizard.php');
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';
require_once __DIR__ . '/../includes/wizard_helper.php';

$pdo = connectDB();

try {
    $bkg_id = isset($_POST['bkg_id']) ? (int)$_POST['bkg_id'] : 0;
    $tnt_id = trim($_POST['tnt_id'] ?? '');
    $room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;

    if ($bkg_id <= 0 || empty($tnt_id) || $room_id <= 0) {
        throw new Exception('ข้อมูลไม่ครบถ้วน');
    }

    // ตรวจสอบว่ามีการจองนี้อยู่จริง
    $booking = getBookingDetails($pdo, $bkg_id);
    if (!$booking) {
        throw new Exception('ไม่พบข้อมูลการจอง');
    }

    // ตรวจสอบสถานะห้อง
    $roomStmt = $pdo->prepare("SELECT room_status FROM room WHERE room_id = ?");
    $roomStmt->execute([$room_id]);
    $room = $roomStmt->fetch(PDO::FETCH_ASSOC);

    if (!$room) {
        throw new Exception('ไม่พบข้อมูลห้องพัก');
    }

    $pdo->beginTransaction();

    // 1. อัปเดตสถานะการจอง
    $stmt = $pdo->prepare("UPDATE booking SET bkg_status = '1' WHERE bkg_id = ?");
    $stmt->execute([$bkg_id]);

    // 2. อัปเดตสถานะห้อง เป็น "จองแล้ว" (2)
    // NOTE: ตาม Requirement ให้ห้องยังคงสถานะเดิมจนกว่าจะ Check-in
    // $stmt = $pdo->prepare("UPDATE room SET room_status = '2' WHERE room_id = ?");
    // $stmt->execute([$room_id]);

    // 3. อัปเดตสถานะผู้เช่า เป็น "จองห้อง" (3)
    $stmt = $pdo->prepare("UPDATE tenant SET tnt_status = '3' WHERE tnt_id = ?");
    $stmt->execute([$tnt_id]);

    // 4. สร้างรายการชำระเงินจอง (ตรวจสอบก่อนว่ามีอยู่แล้วหรือไม่)
    $stmt = $pdo->prepare("SELECT bp_id FROM booking_payment WHERE bkg_id = ? LIMIT 1");
    $stmt->execute([$bkg_id]);
    $existingPayment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingPayment) {
        $stmt = $pdo->prepare("
            INSERT INTO booking_payment (bp_amount, bp_status, bkg_id)
            VALUES (2000.00, '0', ?)
        ");
        $stmt->execute([$bkg_id]);
    }

    // 5. สร้างหรืออัปเดต Workflow
    if (!workflowExists($pdo, $tnt_id)) {
        createWorkflow($pdo, $tnt_id, $bkg_id);
    }

    // 6. อัปเดตสถานะ Step 1 เป็นเสร็จสิ้น
    updateWorkflowStep($pdo, $tnt_id, 1, $_SESSION['admin_username']);

    $pdo->commit();

    $_SESSION['success'] = 'ยืนยันการจองเรียบร้อย! ขั้นตอนถัดไป: ยืนยันชำระเงินจอง';
    header('Location: ../Reports/tenant_wizard.php');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Process Wizard Step 1 Error: " . $e->getMessage());
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    header('Location: ../Reports/tenant_wizard.php');
    exit;
}
