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
require_once __DIR__ . '/../LineHelper.php';

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

    if (function_exists('sendLineBroadcast')) {
        $msg = "📢 แจ้งเตือนผู้เช่าห้อง " . $room_number . "\n";
        $msg .= "คุณ " . $tnt_name . "\n";
        $msg .= "ระบบพบว่าท่านแจ้งยกเลิกสัญญาแล้ว แต่ยังไม่ได้ระบุบัญชีธนาคาร\n";
        $msg .= "เพื่อการโอนเงินประกันคืน กรุณาเข้าสู่ระบบ Tenant Portal เพื่อระบุข้อมูลครับ";

        $result = sendLineBroadcast($pdo, $msg);
        
        if ($result) {
            echo json_encode(['success' => true]);
        } else {
             echo json_encode(['success' => false, 'error' => 'ไม่สามารถส่งข้อความ LINE แจ้งเตือนได้']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'ไม่พบฟังก์ชันส่ง LINE']);
    }
} catch (Exception $e) {
    error_log("Error in remind_bank_account.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
