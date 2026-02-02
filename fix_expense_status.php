<?php
/**
 * Script สำหรับแก้ไขสถานะ expense ที่คำนวณผิดเพราะนับรวมมัดจำ
 * รันครั้งเดียวเพื่อแก้ข้อมูลเก่า
 */
declare(strict_types=1);
session_start();

// ตรวจสอบสิทธิ์
if (empty($_SESSION['admin_username'])) {
    die('กรุณา Login ก่อน');
}

require_once __DIR__ . '/ConnectDB.php';
$pdo = connectDB();

echo "<h2>แก้ไขสถานะ expense (ไม่นับรวมมัดจำ)</h2>";
echo "<pre>";

try {
    // ดึง expense ทั้งหมด
    $expenses = $pdo->query("SELECT exp_id, exp_total, exp_status FROM expense")->fetchAll(PDO::FETCH_ASSOC);
    
    $updated = 0;
    foreach ($expenses as $exp) {
        $expId = (int)$exp['exp_id'];
        $expTotal = (int)$exp['exp_total'];
        $oldStatus = $exp['exp_status'];
        
        // คำนวณยอดชำระใหม่ (ไม่รวมมัดจำ)
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(pay_amount), 0) as total_paid 
            FROM payment 
            WHERE exp_id = ? 
              AND pay_status = '1' 
              AND (pay_remark IS NULL OR pay_remark != 'มัดจำ')
        ");
        $stmt->execute([$expId]);
        $totalPaid = (int)$stmt->fetchColumn();
        
        // กำหนดสถานะใหม่
        if ($totalPaid >= $expTotal && $expTotal > 0) {
            $newStatus = '1'; // ชำระแล้ว
        } elseif ($totalPaid > 0) {
            $newStatus = '3'; // ชำระยังไม่ครบ
        } else {
            $newStatus = '0'; // ยังไม่ชำระ
        }
        
        // อัปเดตถ้าสถานะเปลี่ยน
        if ($oldStatus !== $newStatus) {
            $update = $pdo->prepare("UPDATE expense SET exp_status = ? WHERE exp_id = ?");
            $update->execute([$newStatus, $expId]);
            
            $statusNames = [
                '0' => 'ยังไม่ชำระ',
                '1' => 'ชำระแล้ว',
                '2' => 'รอตรวจสอบ',
                '3' => 'ชำระยังไม่ครบ'
            ];
            
            echo "Expense #{$expId}: เปลี่ยนสถานะจาก [{$oldStatus}] {$statusNames[$oldStatus]} -> [{$newStatus}] {$statusNames[$newStatus]}\n";
            echo "  - ยอดรวม: {$expTotal} บาท, ชำระแล้ว (ไม่รวมมัดจำ): {$totalPaid} บาท\n\n";
            $updated++;
        }
    }
    
    echo "\n===========================================\n";
    echo "สรุป: อัปเดตสถานะทั้งหมด {$updated} รายการ\n";
    echo "===========================================\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><a href='Reports/manage_expenses.php'>กลับไปหน้าจัดการค่าใช้จ่าย</a></p>";
?>
