<?php
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

echo "🧹 Aggressive Duplicate Cleanup - ลบข้อมูลเชื่อมโยง + สัญญาซ้ำ\n";
echo "========================================================\n\n";

// ค้นหา duplicates
$findDupesStmt = $pdo->query(
    "SELECT tnt_id, room_id, COUNT(*) as cnt, GROUP_CONCAT(ctr_id ORDER BY ctr_id) as ctr_ids
     FROM contract
     WHERE ctr_status IN ('0','2')
     GROUP BY tnt_id, room_id
     HAVING cnt > 1
     ORDER BY cnt DESC"
);

$duplicates = $findDupesStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($duplicates)) {
    echo "✅ ไม่มีสัญญาซ้ำ - ระบบปกติ\n";
    exit;
}

$deletedCount = 0;
$deletedDetails = [];

try {
    $pdo->beginTransaction();
    
    foreach ($duplicates as $dup) {
        $ctrIds = explode(',', $dup['ctr_ids']);
        sort($ctrIds);
        $keepId = (int)$ctrIds[0];
        $deleteIds = array_slice($ctrIds, 1);
        
        echo "📌 Tenant: {$dup['tnt_id']}, Room: {$dup['room_id']}\n";
        echo "   Keeping: $keepId | Deleting: " . implode(',', $deleteIds) . "\n";
        
        foreach ($deleteIds as $delId) {
            $delId = (int)$delId;
            
            // ขั้นที่ 1: ลบ booking_payment จาก contracts ที่ต้องลบ
            $delBookingPaymentStmt = $pdo->prepare(
                "DELETE bp FROM booking_payment bp
                 INNER JOIN booking b ON bp.bkg_id = b.bkg_id
                 INNER JOIN tenant_workflow tw ON b.bkg_id = tw.bkg_id
                 WHERE tw.ctr_id = ?"
            );
            $delBookingPaymentStmt->execute([$delId]);
            $bpDeleted = $delBookingPaymentStmt->rowCount();
            
            // ขั้นที่ 2: ลบ utility (meter readings) เชื่อมโยงกับสัญญาที่ลบ
            $delUtilityStmt = $pdo->prepare("DELETE FROM utility WHERE ctr_id = ?");
            $delUtilityStmt->execute([$delId]);
            $utilDeleted = $delUtilityStmt->rowCount();
            
            // ขั้นที่ 3: ลบ expense เชื่อมโยงกับสัญญาที่ลบ
            $delExpenseStmt = $pdo->prepare("DELETE FROM expense WHERE ctr_id = ?");
            $delExpenseStmt->execute([$delId]);
            $expDeleted = $delExpenseStmt->rowCount();
            
            // ขั้นที่ 4: ลบ checkin_record ถ้ามี
            $delCheckinStmt = $pdo->prepare("DELETE FROM checkin_record WHERE ctr_id = ?");
            $delCheckinStmt->execute([$delId]);
            $checkinDeleted = $delCheckinStmt->rowCount();
            
            // ขั้นที่ 5: ลบ tenant_workflow entries ที่ชี้ไปยังสัญญาที่ลบ
            $delWorkflowStmt = $pdo->prepare("DELETE FROM tenant_workflow WHERE ctr_id = ?");
            $delWorkflowStmt->execute([$delId]);
            
            // ขั้นที่ 6: ลบสัญญาเอง
            $delContractStmt = $pdo->prepare("DELETE FROM contract WHERE ctr_id = ?");
            $delContractStmt->execute([$delId]);
            
            $deletedCount++;
            $deletedDetails[] = [
                'ctr_id' => $delId,
                'payments' => $bpDeleted,
                'utilities' => $utilDeleted,
                'expenses' => $expDeleted,
                'checkins' => $checkinDeleted
            ];
            
            echo "   ✓ Deleted ctr_id=$delId (payments=$bpDeleted, utilities=$utilDeleted, expenses=$expDeleted)\n";
        }
        echo "\n";
    }
    
    $pdo->commit();
    
    echo "========================================================\n";
    echo "✅ CLEANUP COMPLETE - ลบสัญญาซ้ำ $deletedCount รายการ\n";
    echo "========================================================\n\n";
    
    echo "📊 Deleted Records Summary:\n";
    $totalPayments = array_sum(array_column($deletedDetails, 'payments'));
    $totalUtilities = array_sum(array_column($deletedDetails, 'utilities'));
    $totalExpenses = array_sum(array_column($deletedDetails, 'expenses'));
    
    echo "  • Contracts: $deletedCount\n";
    echo "  • Payments: $totalPayments\n";
    echo "  • Utilities/Meters: $totalUtilities\n";
    echo "  • Expenses: $totalExpenses\n";
    echo "  • Checkins: " . array_sum(array_column($deletedDetails, 'checkins')) . "\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
