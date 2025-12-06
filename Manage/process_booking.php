<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../Reports/manage_booking.php');
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';

try {
    $pdo = connectDB();
    
    // รับข้อมูลจากฟอร์ม
    $room_id = $_POST['room_id'] ?? null;
    $bkg_date = $_POST['bkg_date'] ?? null;
    $bkg_checkin_date = $_POST['bkg_checkin_date'] ?? null;
    
    // ตรวจสอบข้อมูล
    if (empty($room_id) || empty($bkg_date) || empty($bkg_checkin_date)) {
        $_SESSION['error'] = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        header('Location: ../Reports/manage_booking.php');
        exit;
    }
    
    // ตรวจสอบว่าห้องว่างหรือไม่
    $stmt = $pdo->prepare("SELECT room_status FROM room WHERE room_id = ?");
    $stmt->execute([$room_id]);
    $room = $stmt->fetch();
    
    if (!$room) {
        $_SESSION['error'] = 'ไม่พบห้องพักนี้';
        header('Location: ../Reports/manage_booking.php');
        exit;
    }
    
    if ($room['room_status'] !== '0') {
        $_SESSION['error'] = 'ห้องพักนี้ไม่ว่าง ไม่สามารถจองได้';
        header('Location: ../Reports/manage_booking.php');
        exit;
    }
    
    // เริ่ม transaction
    $pdo->beginTransaction();
    
    // บันทึกข้อมูลการจอง (สถานะ 1 = จองแล้ว)
    $stmt = $pdo->prepare("
        INSERT INTO booking (bkg_date, bkg_checkin_date, bkg_status, room_id) 
        VALUES (?, ?, '1', ?)
    ");
    $stmt->execute([$bkg_date, $bkg_checkin_date, $room_id]);
    
    // อัพเดทสถานะห้องเป็นไม่ว่าง (1)
    $stmt = $pdo->prepare("UPDATE room SET room_status = '1' WHERE room_id = ?");
    $stmt->execute([$room_id]);
    
    // commit transaction
    $pdo->commit();
    
    // ตรวจสอบว่าเป็น AJAX request หรือไม่
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'เพิ่มการจองห้องพักเรียบร้อยแล้ว'
        ]);
        exit;
    }
    
    $_SESSION['success'] = 'เพิ่มการจองห้องพักเรียบร้อยแล้ว';
    header('Location: ../Reports/manage_booking.php');
    exit;
    
} catch (PDOException $e) {
    // rollback ถ้ามีข้อผิดพลาด
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // ตรวจสอบว่าเป็น AJAX request หรือไม่
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
        ]);
        exit;
    }
    
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    header('Location: ../Reports/manage_booking.php');
    exit;
}
