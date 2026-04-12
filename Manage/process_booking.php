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
    $tnt_id = $_POST['tnt_id'] ?? null;
    $bkg_date = $_POST['bkg_date'] ?? null;
    $bkg_checkin_date = $_POST['bkg_checkin_date'] ?? null;
    
    // ตรวจสอบข้อมูล
    if ($room_id <= 0 || empty($tnt_id) || empty($bkg_date) || empty($bkg_checkin_date)) {
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
        INSERT INTO booking (bkg_date, bkg_checkin_date, bkg_status, room_id, tnt_id) 
        VALUES (?, ?, '1', ?, ?)
    ");
    $stmt->execute([$bkg_date, $bkg_checkin_date, $room_id, $tnt_id]);
    
    // NOTE: ห้องจะเปลี่ยนเป็น "ไม่ว่าง" เมื่อเช็คอิน เท่านั้น
    // $updateStmt = $pdo->prepare("UPDATE room SET room_status = '1' WHERE room_id = ?");
    // $updateStmt->execute([$room_id]);
    
    // อัพเดทสถานะผู้เช่าเป็น '3' (จองห้อง)
    $updateTenant = $pdo->prepare("UPDATE tenant SET tnt_status = '3' WHERE tnt_id = ?");
    $updateTenant->execute([$tnt_id]);
    
    // commit transaction
    $pdo->commit();
    
    // ส่งแจ้งเตือนการจองใหม่เข้า LINE OA
    require_once __DIR__ . '/../LineHelper.php';
    try {
        $stmtRoomInfo = $pdo->prepare("SELECT room_number FROM room WHERE room_id = ?");
        $stmtRoomInfo->execute([$room_id]);
        $roomName = $stmtRoomInfo->fetchColumn() ?: $room_id;
        
        $stmtTenant = $pdo->prepare("SELECT tnt_name, tnt_phone FROM tenant WHERE tnt_id = ?");
        $stmtTenant->execute([$tnt_id]);
        $tenantMatch = $stmtTenant->fetch(PDO::FETCH_ASSOC);

        $tName = $tenantMatch ? $tenantMatch['tnt_name'] : 'เจ้าหน้าที่เพิ่ม';
        $tPhone = $tenantMatch ? $tenantMatch['tnt_phone'] : '-';
        
        $msg = "🔔 เจ้าหน้าที่เพิ่มการจองห้องพักใหม่!\n";
        $msg .= "👤 ผู้เช่า: {$tName}\n";
        $msg .= "📞 เบอร์ติดต่อ: {$tPhone}\n";
        $msg .= "🏠 ห้องที่จอง: {$roomName}\n";
        $msg .= "📅 วันที่เข้าพัก: " . date('d/m/Y', strtotime($bkg_checkin_date)) . "\n";

        if (function_exists('sendLineToTenant')) {
            sendLineToTenant($pdo, (string)$tnt_id, $msg);
        }
    } catch (Exception $e) {
        error_log("Line Notification Error: " . $e->getMessage());
    }

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
