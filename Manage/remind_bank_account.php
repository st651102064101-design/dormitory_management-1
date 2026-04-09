<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_username'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';
require_once __DIR__ . '/../SmsHelper.php'; // เปลี่ยนมาใช้ SMS

header('Content-Type: application/json');

try {
    $pdo = connectDB();
    
    $ctr_id = $_POST['ctr_id'] ?? null;
    $room_number = $_POST['room_number'] ?? '-';
    $tnt_name = $_POST['tnt_name'] ?? '-';
    
    if (!$ctr_id) {
        echo json_encode(['success' => false, 'error' => 'รหัสสัญญาไม่ถูกต้อง']);
        exit;
    }
    
    // ดึงเบอร์โทรศัพท์ของผู้เช่า
    $stmt = $pdo->prepare("SELECT t.tnt_phone FROM contract c JOIN tenant t ON c.tnt_id = t.tnt_id WHERE c.ctr_id = ?");
    $stmt->execute([$ctr_id]);
    $tnt_phone = $stmt->fetchColumn();

    if (!$tnt_phone) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบเบอร์โทรศัพท์ของผู้เช่า']);
        exit;
    }

    if (function_exists('sendSms')) {
        $msg = "📢 แจ้งเตือนผู้เช่าห้อง " . $room_number . "\n";
        $msg .= "คุณ " . $tnt_name . " ระบบพบว่าท่านแจ้งยกเลิกสัญญา แต่ยังไม่ได้ระบุบัญชีธนาคารเพื่อรับเงินประกันคืน กรุณาเข้าระบบไประบุข้อมูลครับ";

        $result = sendSms($pdo, $tnt_phone, $msg);
        
        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            // ถ้าระบบยังไม่ตั้ง Token ให้หลอกว่าสำเร็จไปก่อน (MOCK SMS) สำหรับการทดสอบระบบ
            $stmtTest = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'sms_api_token'");
            $token = trim((string)$stmtTest->fetchColumn());
            if (empty($token)) {
                 echo json_encode(['success' => true, 'mock' => true, 'message' => 'Simulated SMS success (No API Token configured yet).']);
            } else {
                 echo json_encode(['success' => false, 'error' => 'ไม่สามารถส่งข้อความ SMS ได้ กรุณาตรวจสอบแพ็กเกจ']);
            }
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'ไม่พบฟังก์ชันส่ง SMS']);
    }
} catch (Exception $e) {
    error_log("Error in remind_bank_account.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
