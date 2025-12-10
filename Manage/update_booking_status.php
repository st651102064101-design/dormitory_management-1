<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin_username'])) {
    if ($_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    } else {
        header('Location: ../Login.php');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    } else {
        header('Location: ../Reports/manage_booking.php');
    }
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';

$isAjax = $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

try {
    $pdo = connectDB();
    
    $bkg_id = $_POST['bkg_id'] ?? null;
    $bkg_status = $_POST['bkg_status'] ?? null;
    
    if ($bkg_id === null || $bkg_status === null || $bkg_id === '' || $bkg_status === '') {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบถ้วน']);
        } else {
            $_SESSION['error'] = 'ข้อมูลไม่ครบถ้วน';
            header('Location: ../Reports/manage_booking.php');
        }
        exit;
    }
    
    // ดึงข้อมูลการจอง (รวม tnt_id)
    $stmt = $pdo->prepare("SELECT room_id, bkg_status, tnt_id FROM booking WHERE bkg_id = ?");
    $stmt->execute([$bkg_id]);
    $booking = $stmt->fetch();
    
    if (!$booking) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลการจอง']);
        } else {
            $_SESSION['error'] = 'ไม่พบข้อมูลการจอง';
            header('Location: ../Reports/manage_booking.php');
        }
        exit;
    }
    
    // เริ่ม transaction
    $pdo->beginTransaction();
    
    // ถ้ายกเลิกการจอง (status = 0) -> เปลี่ยนสถานะเป็นยกเลิก
    // ถ้าเข้าพักแล้ว (status = 2) -> เพียงปรับปรุงสถานะห้อง
    if ($bkg_status === '0') {
        // ยกเลิก -> อัพเดทสถานะเป็นยกเลิก และกำหนดห้องเป็นว่าง
        $stmt = $pdo->prepare("UPDATE booking SET bkg_status = '0' WHERE bkg_id = ?");
        $stmt->execute([$bkg_id]);
        
        $stmt = $pdo->prepare("UPDATE room SET room_status = '0' WHERE room_id = ?");
        $stmt->execute([$booking['room_id']]);
        
        // อัพเดทสถานะผู้เช่าเป็น '4' (ยกเลิกจองห้อง)
        if (!empty($booking['tnt_id'])) {
            $stmt = $pdo->prepare("UPDATE tenant SET tnt_status = '4' WHERE tnt_id = ?");
            $stmt->execute([$booking['tnt_id']]);
        }
        
        $message = 'ยกเลิกการจองเรียบร้อยแล้ว';
    } else if ($bkg_status === '2') {
        // เข้าพักแล้ว -> อัพเดทสถานะและห้องไม่ว่าง
        $stmt = $pdo->prepare("UPDATE booking SET bkg_status = ? WHERE bkg_id = ?");
        $stmt->execute([$bkg_status, $bkg_id]);
        
        $stmt = $pdo->prepare("UPDATE room SET room_status = '1' WHERE room_id = ?");
        $stmt->execute([$booking['room_id']]);
        
        // อัพเดทสถานะผู้เช่าเป็น '1' (กำลังเช่า)
        if (!empty($booking['tnt_id'])) {
            $stmt = $pdo->prepare("UPDATE tenant SET tnt_status = '1' WHERE tnt_id = ?");
            $stmt->execute([$booking['tnt_id']]);
        }
        
        $message = 'แก้ไขสถานะเป็นเข้าพักแล้วเรียบร้อยแล้ว';
    } else {
        // สำหรับสถานะอื่นๆ เพียงอัพเดทสถานะ
        $stmt = $pdo->prepare("UPDATE booking SET bkg_status = ? WHERE bkg_id = ?");
        $stmt->execute([$bkg_status, $bkg_id]);
        $message = 'แก้ไขสถานะเรียบร้อยแล้ว';
    }
    
    // commit transaction
    $pdo->commit();
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => $message ?? 'แก้ไขสถานะเรียบร้อยแล้ว']);
    } else {
        $_SESSION['success'] = $message ?? 'แก้ไขสถานะเรียบร้อยแล้ว';
        header('Location: ../Reports/manage_booking.php');
    }
    exit;
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    } else {
        $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        header('Location: ../Reports/manage_booking.php');
    }
    exit;
}
