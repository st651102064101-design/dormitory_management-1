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
