<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

// Check authorization - admin only
if (empty($_SESSION['admin_username'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';

try {
    $pdo = connectDB();
    
    // Confirm action
    $confirm = isset($_POST['confirm']) ? ($_POST['confirm'] === '1') : false;
    if (!$confirm) {
        echo json_encode(['success' => false, 'error' => 'Action not confirmed']);
        exit;
    }
    
    // ค้นหา duplicate: ผู้เช่า + ห้อง ซ้ำกันมากกว่า 1 สัญญา
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
        echo json_encode([
            'success' => true,
            'message' => 'ไม่มีสัญญาซ้ำ',
            'deleted_count' => 0
        ]);
        exit;
    }
    
    $pdo->beginTransaction();
    $deletedCount = 0;
    $deletedDetails = [];
    
    try {
        foreach ($duplicates as $dup) {
            $ctrIds = explode(',', $dup['ctr_ids']);
            sort($ctrIds);
            $keepId = (int)$ctrIds[0];
            $deleteIds = array_slice($ctrIds, 1);
            
            foreach ($deleteIds as $delId) {
                $delId = (int)$delId;
                
                // ตรวจสอบ: ไม่มีข้อมูล payment, utility, expense ก่อนลบ
                // booking_payment relates through: booking → tenant_workflow → contract
                $payStmt = $pdo->prepare("SELECT COUNT(*) FROM booking_payment bp 
                    INNER JOIN booking b ON bp.bkg_id = b.bkg_id 
                    INNER JOIN tenant_workflow tw ON b.bkg_id = tw.bkg_id 
                    WHERE tw.ctr_id = ?");
                $payStmt->execute([$delId]);
                $payCount = $payStmt->fetchColumn();
                
                $utilStmt = $pdo->prepare("SELECT COUNT(*) FROM utility WHERE ctr_id = ?");
                $utilStmt->execute([$delId]);
                $utilCount = $utilStmt->fetchColumn();
                
                $expStmt = $pdo->prepare("SELECT COUNT(*) FROM expense WHERE ctr_id = ?");
                $expStmt->execute([$delId]);
                $expCount = $expStmt->fetchColumn();
                
                // ถ้าไม่มีข้อมูลเชื่อมโยง ให้ลบได้
                if ($payCount == 0 && $utilCount == 0 && $expCount == 0) {
                    $deleteStmt = $pdo->prepare("DELETE FROM contract WHERE ctr_id = ?");
                    $deleteStmt->execute([$delId]);
                    $deletedCount++;
                    $deletedDetails[] = [
                        'ctr_id' => $delId,
                        'status' => 'deleted'
                    ];
                } else {
                    $deletedDetails[] = [
                        'ctr_id' => $delId,
                        'status' => 'skipped',
                        'reason' => "มี payment=$payCount, utility=$utilCount, expense=$expCount"
                    ];
                }
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "ลบสัญญาซ้ำ $deletedCount รายการ",
            'deleted_count' => $deletedCount,
            'details' => $deletedDetails
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
    exit;
}
?>
