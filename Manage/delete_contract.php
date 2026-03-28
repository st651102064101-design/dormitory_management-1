<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

// Check authorization
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
    
    $ctrId = isset($_POST['ctr_id']) ? (int)$_POST['ctr_id'] : 0;
    
    if ($ctrId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid contract ID']);
        exit;
    }
    
    // ตรวจสอบสัญญามีอยู่จริง
    $checkStmt = $pdo->prepare("SELECT ctr_id, ctr_status, ctr_start, tnt_id, room_id FROM contract WHERE ctr_id = ?");
    $checkStmt->execute([$ctrId]);
    $contract = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$contract) {
        echo json_encode(['success' => false, 'error' => 'Contract not found']);
        exit;
    }
    
    // ตรวจสอบ: สามารถลบได้เฉพาะสัญญาที่สถานะ = 0 (สร้างใหม่) หรือ 1 (ยกเลิก)
    // ป้องกันการลบสัญญาที่มีการทำรายการแล้ว
    $status = (int)$contract['ctr_status'];
    if (!in_array($status, [0, 1])) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete contracts with status: ' . $status . '. Only draft or cancelled contracts can be deleted.']);
        exit;
    }
    
    // ตรวจสอบเพิ่มเติม: ห้ามมีข้อมูลอื่นเชื่อมโยง
    // ตรวจสอบ: มีการชำระเงินขึ้นอยู่กับสัญญานี้หรือไม่
    // booking_payment relates through: booking → tenant_workflow → contract
    $paymentStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM booking_payment bp 
        INNER JOIN booking b ON bp.bkg_id = b.bkg_id 
        INNER JOIN tenant_workflow tw ON b.bkg_id = tw.bkg_id 
        WHERE tw.ctr_id = ?");
    $paymentStmt->execute([$ctrId]);
    $paymentCount = $paymentStmt->fetch()['cnt'] ?? 0;
    
    if ($paymentCount > 0) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete contract with payment records. (' . $paymentCount . ' payments found)']);
        exit;
    }
    
    // ตรวจสอบ: มีข้อมูล utility (มิเตอร์) เชื่อมโยงหรือไม่
    $utilityStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM utility WHERE ctr_id = ?");
    $utilityStmt->execute([$ctrId]);
    $utilityCount = $utilityStmt->fetch()['cnt'] ?? 0;
    
    if ($utilityCount > 0) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete contract with meter records. (' . $utilityCount . ' records found)']);
        exit;
    }
    
    // ตรวจสอบ: มีข้อมูล expense เชื่อมโยงหรือไม่
    $expenseStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM expense WHERE ctr_id = ?");
    $expenseStmt->execute([$ctrId]);
    $expenseCount = $expenseStmt->fetch()['cnt'] ?? 0;
    
    if ($expenseCount > 0) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete contract with expense records. (' . $expenseCount . ' records found)']);
        exit;
    }
    
    // เริ่มต้น transaction
    $pdo->beginTransaction();
    
    try {
        // ลบสัญญา
        $deleteStmt = $pdo->prepare("DELETE FROM contract WHERE ctr_id = ?");
        $deleteStmt->execute([$ctrId]);
        
        // อัปเดต room status กลับเป็น 0 (available)
        if ($contract['room_id']) {
            $updateRoomStmt = $pdo->prepare("UPDATE room SET room_status = 0 WHERE room_id = ?");
            $updateRoomStmt->execute([$contract['room_id']]);
        }
        
        // อัปเดต tenant status กลับเป็น 2 (waiting for check-in)
        if ($contract['tnt_id']) {
            $updateTntStmt = $pdo->prepare("UPDATE tenant SET tnt_status = 2 WHERE tnt_id = ?");
            $updateTntStmt->execute([$contract['tnt_id']]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Contract deleted successfully',
            'ctr_id' => $ctrId
        ]);
        
    } catch (Exception $e) {
        // Rollback ถ้า error
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
