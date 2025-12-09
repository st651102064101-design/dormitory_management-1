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
    
    $bkg_id = $_POST['bkg_id'] ?? null;
    $bkg_status = $_POST['bkg_status'] ?? null;
    
    if ($bkg_id === null || $bkg_status === null || $bkg_id === '' || $bkg_status === '') {
        $_SESSION['error'] = 'ข้อมูลไม่ครบถ้วน';
        header('Location: ../Reports/manage_booking.php');
        exit;
    }
    
    // ดึงข้อมูลการจอง
    $stmt = $pdo->prepare("SELECT room_id, bkg_status FROM booking WHERE bkg_id = ?");
    $stmt->execute([$bkg_id]);
    $booking = $stmt->fetch();
    
    if (!$booking) {
        $_SESSION['error'] = 'ไม่พบข้อมูลการจอง';
        header('Location: ../Reports/manage_booking.php');
        exit;
    }
    
    // เริ่ม transaction
    $pdo->beginTransaction();
    
    // ถ้ายกเลิกการจอง (status = 0) -> ลบบันทึกการจองออกจาก DB
    // ถ้าเข้าพักแล้ว (status = 2) -> เพียงปรับปรุงสถานะห้อง
    if ($bkg_status === '0') {
        // ยกเลิก -> ลบจากฐานข้อมูล และกำหนดห้องเป็นว่าง
        $stmt = $pdo->prepare("DELETE FROM booking WHERE bkg_id = ?");
        $stmt->execute([$bkg_id]);
        
        $stmt = $pdo->prepare("UPDATE room SET room_status = '0' WHERE room_id = ?");
        $stmt->execute([$booking['room_id']]);
        $message = 'ยกเลิกการจองเรียบร้อยแล้ว';
    } else if ($bkg_status === '2') {
        // เข้าพักแล้ว -> อัพเดทสถานะและห้องไม่ว่าง
        $stmt = $pdo->prepare("UPDATE booking SET bkg_status = ? WHERE bkg_id = ?");
        $stmt->execute([$bkg_status, $bkg_id]);
        
        $stmt = $pdo->prepare("UPDATE room SET room_status = '1' WHERE room_id = ?");
        $stmt->execute([$booking['room_id']]);
        $message = 'แก้ไขสถานะเป็นเข้าพักแล้วเรียบร้อยแล้ว';
    } else {
        // สำหรับสถานะอื่นๆ เพียงอัพเดทสถานะ
        $stmt = $pdo->prepare("UPDATE booking SET bkg_status = ? WHERE bkg_id = ?");
        $stmt->execute([$bkg_status, $bkg_id]);
        $message = 'แก้ไขสถานะเรียบร้อยแล้ว';
    }
    
    // commit transaction
    $pdo->commit();
    
    $_SESSION['success'] = $message ?? 'แก้ไขสถานะเรียบร้อยแล้ว';
    header('Location: ../Reports/manage_booking.php');
    exit;
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    header('Location: ../Reports/manage_booking.php');
    exit;
}
