<?php
/**
 * Auto Update Overdue - ตรวจสอบและอัปเดตสถานะค้างชำระอัตโนมัติ
 * 
 * เรียกใช้โดย include จากหน้าที่แสดงค่าใช้จ่าย
 * ตรวจสอบว่า expense ที่รอชำระ/ชำระไม่ครบ เลยกำหนดชำระหรือไม่
 * ถ้าเลยกำหนด → เปลี่ยนสถานะเป็น '4' (ค้างชำระ)
 * 
 * ต้องมีตัวแปร $pdo (PDO connection) ก่อน include
 */

if (!isset($pdo)) {
    return;
}

// ดึงวันครบกำหนดชำระจาก system_settings (default = 5)
$paymentDueDay = 5;
try {
    $dueDayStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'payment_due_day' LIMIT 1");
    $dueDayStmt->execute();
    $dueDayVal = $dueDayStmt->fetchColumn();
    if ($dueDayVal !== false && is_numeric($dueDayVal)) {
        $paymentDueDay = max(1, min(28, (int)$dueDayVal));
    }
} catch (PDOException $e) {
    // ใช้ค่าเริ่มต้น
}

// อัปเดตสถานะค้างชำระ: expense ที่ exp_status IN ('0', '3') และเลยกำหนดชำระ
// กำหนดชำระ = วันที่ $paymentDueDay ของเดือนที่ exp_month ระบุ
// เช่น exp_month = 2026-03-01 และ payment_due_day = 5 → กำหนดชำระ = 2026-03-05
try {
    $today = date('Y-m-d');

    // ก่อนอัปเดตสถานะ ดึงรายการที่จะกลายเป็นค้างชำระ (exp_status 0 หรือ 3 ที่เกินกำหนด) เพื่อส่ง LINE Broadcast
    $findStmt = $pdo->prepare("
        SELECT e.exp_id, e.exp_month, r.room_number, e.exp_total, e.exp_status,
               (e.exp_total - COALESCE((SELECT SUM(pay_amount) FROM payment p WHERE p.exp_id = e.exp_id AND p.pay_status IN ('0','1')), 0)) AS chargesRemain
        FROM expense e
        JOIN contract c ON e.ctr_id = c.ctr_id
        JOIN room r ON c.room_id = r.room_id
        WHERE e.exp_status IN ('0', '3')
          AND DATE_ADD(DATE_FORMAT(e.exp_month, '%Y-%m-01'), INTERVAL (? - 1) DAY) < ?
    ");
    $findStmt->execute([$paymentDueDay, $today]);
    $newlyOverdues = $findStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($newlyOverdues)) {
        require_once __DIR__ . '/../LineHelper.php';
        $domain = rtrim($_SERVER['HTTP_HOST'] ?? 'localhost', '/');
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $url = $protocol . $domain . "/dormitory_management/Reports/tenant_wizard.php"; // หรือลิงก์ที่เหมาะสม
        
        foreach ($newlyOverdues as $row) {
            $monthTxt = date('m/Y', strtotime($row['exp_month']));
            $amount = number_format((float)$row['chargesRemain'], 2);
            $msg = "⚠️ แจ้งเตือน: บิลค้างชำระ ห้อง {$row['room_number']}\n";
            $msg .= "ประจำเดือน: {$monthTxt}\n";
            $msg .= "------------------------\n";
            $msg .= "ยอดคงเหลือที่ต้องชำระ: ฿{$amount}\n";
            $msg .= "❗️ เลยกำหนดชำระแล้ว กรุณาชำระเงินเพื่อหลีกเลี่ยงค่าปรับ\n";
            $msg .= "\nสามารถตรวจสอบและชำระเงินได้ที่:\n{$url}";
            
            sendLineBroadcast($pdo, $msg);
        }
    }
    
    // ดำเนินการอัปเดตเป็นสถานะ 4 (ค้างชำระ)
    $updateOverdueStmt = $pdo->prepare("
        UPDATE expense 
        SET exp_status = '4'
        WHERE exp_status IN ('0', '3')
          AND DATE_ADD(
                DATE_FORMAT(exp_month, '%Y-%m-01'),
                INTERVAL (? - 1) DAY
              ) < ?
    ");
    $updateOverdueStmt->execute([$paymentDueDay, $today]);
} catch (PDOException $e) {
    error_log('auto_update_overdue error: ' . $e->getMessage());
}
