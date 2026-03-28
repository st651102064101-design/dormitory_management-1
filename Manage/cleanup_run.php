<?php
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

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
$deletedCount = 0;
$deletedDetails = [];

try {
    $pdo->beginTransaction();
    
    foreach ($duplicates as $dup) {
        $ctrIds = explode(',', $dup['ctr_ids']);
        sort($ctrIds);
        $keepId = (int)$ctrIds[0];
        $deleteIds = array_slice($ctrIds, 1);
        
        foreach ($deleteIds as $delId) {
            $delId = (int)$delId;
            
            // ตรวจสอบ related records
            // booking_payment relates through: booking → tenant_workflow → contract
            $payStmt = $pdo->prepare('SELECT COUNT(*) FROM booking_payment bp 
                INNER JOIN booking b ON bp.bkg_id = b.bkg_id 
                INNER JOIN tenant_workflow tw ON b.bkg_id = tw.bkg_id 
                WHERE tw.ctr_id = ?');
            $payStmt->execute([$delId]);
            $payCount = $payStmt->fetchColumn();
            
            $utilStmt = $pdo->prepare('SELECT COUNT(*) FROM utility WHERE ctr_id = ?');
            $utilStmt->execute([$delId]);
            $utilCount = $utilStmt->fetchColumn();
            
            $expStmt = $pdo->prepare('SELECT COUNT(*) FROM expense WHERE ctr_id = ?');
            $expStmt->execute([$delId]);
            $expCount = $expStmt->fetchColumn();
            
            if ($payCount == 0 && $utilCount == 0 && $expCount == 0) {
                $deleteStmt = $pdo->prepare('DELETE FROM contract WHERE ctr_id = ?');
                $deleteStmt->execute([$delId]);
                $deletedCount++;
                $deletedDetails[] = [
                    'ctr_id' => $delId,
                    'status' => 'deleted',
                    'room_id' => $dup['room_id'],
                    'tnt_id' => $dup['tnt_id'],
                    'kept_id' => $keepId
                ];
            } else {
                $deletedDetails[] = [
                    'ctr_id' => $delId,
                    'status' => 'skipped',
                    'reason' => "payment=$payCount, utility=$utilCount, expense=$expCount"
                ];
            }
        }
    }
    
    $pdo->commit();
    
    echo "✅ ลบสัญญาซ้ำ $deletedCount รายการ\n\n";
    
    foreach ($deletedDetails as $detail) {
        if ($detail['status'] === 'deleted') {
            echo "• ลบ สัญญา {$detail['ctr_id']} (ผู้เช่า: {$detail['tnt_id']}, ห้อง: {$detail['room_id']}, เก็บไว้: {$detail['kept_id']})\n";
        } else {
            echo "• ข้าม สัญญา {$detail['ctr_id']} ({$detail['reason']})\n";
        }
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ Error: " . $e->getMessage();
}
?>
