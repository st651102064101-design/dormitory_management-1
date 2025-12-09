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

// ตรวจสอบว่าเป็น AJAX request หรือไม่ (ใช้หลายจุด)
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

try {
    $pdo = connectDB();
    
    // รับข้อมูลจากฟอร์ม
    $room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
    $bkg_date = $_POST['bkg_date'] ?? null;
    $bkg_checkin_date = $_POST['bkg_checkin_date'] ?? null;
    
    // ตรวจสอบข้อมูล
    if ($room_id <= 0 || empty($bkg_date) || empty($bkg_checkin_date)) {
        $msg = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $msg]);
            exit;
        }
        $_SESSION['error'] = $msg . ' (room_id: ' . $room_id . ')';
        header('Location: ../Reports/manage_booking.php');
        exit;
    }
    
    // ตรวจสอบว่าห้องว่างหรือไม่ (room_status: 0 = ว่าง, 1 = ไม่ว่าง)
    $stmt = $pdo->prepare("SELECT room_status FROM room WHERE room_id = ?");
    $stmt->execute([$room_id]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$room) {
        $msg = 'ไม่พบห้องพักนี้';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $msg]);
            exit;
        }
        $_SESSION['error'] = $msg;
        header('Location: ../Reports/manage_booking.php');
        exit;
    }
    // ต้องเป็นห้องว่างเท่านั้น (room_status = 0)
    if ($room['room_status'] !== '0') {
        $msg = 'ห้องพักนี้ไม่ว่าง ไม่สามารถจองได้';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $msg]);
            exit;
        }
        $_SESSION['error'] = $msg;
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
    
    // อัพเดทสถานะห้องเป็นไม่ว่าง (1) เพื่อกันการจองซ้ำ (schema: 1 = ไม่ว่าง, 0 = ว่าง)
    $updateStmt = $pdo->prepare("UPDATE room SET room_status = '1' WHERE room_id = ?");
    $updateStmt->execute([$room_id]);
    
    // commit transaction
    $pdo->commit();
    
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
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $msg = 'เกิดข้อผิดพลาดฐานข้อมูล: ' . $e->getMessage();
    if ($isAjax) {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $msg]);
        exit;
    }
    $_SESSION['error'] = $msg;
    header('Location: ../Reports/manage_booking.php');
    exit;
} catch (Exception $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $msg = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    if ($isAjax) {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $msg]);
        exit;
    }
    $_SESSION['error'] = $msg;
    header('Location: ../Reports/manage_booking.php');
    exit;
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $msg = 'ข้อผิดพลาดระบบ: ' . $e->getMessage();
    if ($isAjax) {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $msg]);
        exit;
    }
    $_SESSION['error'] = $msg;
    header('Location: ../Reports/manage_booking.php');
    exit;
}
