<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../Reports/tenant_wizard.php');
    exit;
}

// CSRF validation
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    $_SESSION['wizard_error'] = 'CSRF token ไม่ถูกต้อง กรุณาลองใหม่';
    header('Location: ../Reports/tenant_wizard.php');
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';
require_once __DIR__ . '/../includes/wizard_helper.php';

$pdo = connectDB();

try {
    $tnt_id = trim($_POST['tnt_id'] ?? '');
    $room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
    $ctr_start = $_POST['ctr_start'] ?? '';
    $contract_duration = isset($_POST['contract_duration']) ? (int)$_POST['contract_duration'] : 6;
    $ctr_deposit = isset($_POST['ctr_deposit']) ? (float)$_POST['ctr_deposit'] : 2000;

    if (empty($tnt_id) || $room_id <= 0 || empty($ctr_start)) {
        throw new Exception('ข้อมูลไม่ครบถ้วน');
    }

    // คำนวณวันสิ้นสุด
    $ctr_end = date('Y-m-d', strtotime("+{$contract_duration} months", strtotime($ctr_start)));

    $pdo->beginTransaction();

    // ตรวจสอบการมีสัญญาในห้องที่ยังถือว่าไม่ว่าง
    // - ctr_status = '0' และ ctr_end >= วันนี้ (ยังใช้งาน)
    // - ctr_status = '2' และ term_date (แจ้งยกเลิก) ยังไม่ถึง หรือยังไม่มี term_date
    $activeContractStmt = $pdo->prepare(
        "SELECT c.ctr_id FROM contract c\n" .
        "LEFT JOIN termination t ON c.ctr_id = t.ctr_id\n" .
        "WHERE c.room_id = ? AND (\n" .
        "    (c.ctr_status = '0' AND (c.ctr_end IS NULL OR c.ctr_end >= CURDATE())) OR \n" .
        "    (c.ctr_status = '2' AND (t.term_date IS NULL OR t.term_date >= CURDATE()))\n" .
        ") LIMIT 1 FOR UPDATE"
    );
    $activeContractStmt->execute([$room_id]);
    $existingContractId = $activeContractStmt->fetchColumn();

    if ($existingContractId) {
        // ถ้ามีสัญญาปัจจุบันของห้องนี้ ให้อัปเดตค่าสัญญาเท่านั้น
        $updateStmt = $pdo->prepare(
            "UPDATE contract SET ctr_start = ?, ctr_end = ?, ctr_deposit = ?, ctr_status = '0', tnt_id = ? WHERE ctr_id = ?"
        );
        $updateStmt->execute([$ctr_start, $ctr_end, $ctr_deposit, $tnt_id, $existingContractId]);
        $ctr_id = (int)$existingContractId;
    } else {
        // สร้างสัญญาใหม่
        $stmt = $pdo->prepare(
            "INSERT INTO contract (ctr_start, ctr_end, ctr_deposit, ctr_status, tnt_id, room_id, contract_created_date) VALUES (?, ?, ?, '0', ?, ?, NOW())"
        );
        $stmt->execute([$ctr_start, $ctr_end, $ctr_deposit, $tnt_id, $room_id]);
        $ctr_id = (int)$pdo->lastInsertId();
    }

    // NOTE: ห้องจะเปลี่ยนเป็น "ไม่ว่าง" เมื่อเช็คอิน (Step 4) เท่านั้น
    // $stmt = $pdo->prepare("UPDATE room SET room_status = '1' WHERE room_id = ?");
    // $stmt->execute([$room_id]);

    // อัปเดตสถานะผู้เช่า เป็น "รอเข้าพัก" (2)
    $stmt = $pdo->prepare("UPDATE tenant SET tnt_status = '2' WHERE tnt_id = ?");
    $stmt->execute([$tnt_id]);

    // อัปเดต Workflow Step 3
    updateWorkflowStep($pdo, $tnt_id, 3, $_SESSION['admin_username'], $ctr_id);

    $pdo->commit();

    $_SESSION['success'] = "สร้างสัญญาเรียบร้อย! รหัสสัญญา: {$ctr_id} | ขั้นตอนถัดไป: เช็คอิน";
    header('Location: ../Reports/tenant_wizard.php');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $rawErrorMsg = $e->getMessage();
    error_log("Process Wizard Step 3 Error: " . $rawErrorMsg);
    
    // แปล database trigger errors เป็นภาษาไทย
    $errorMsg = 'เกิดข้อผิดพลาด: ' . $rawErrorMsg;
    
    // Extract MESSAGE_TEXT from trigger (format: "1644 Message Text")
    if (preg_match('/\b\d+\s+(.+)$/i', $rawErrorMsg, $matches)) {
        $triggerMsg = trim($matches[1]);
    } else {
        $triggerMsg = $rawErrorMsg;
    }
    
    // ตรวจสอบ trigger error messages และแปล
    if (stripos($triggerMsg, 'Room already has active contract') !== false) {
        $errorMsg = '❌ ห้องนี้มีสัญญาที่ยังใช้อยู่แล้ว - ไม่สามารถสร้างสัญญาซ้ำได้';
    } elseif (stripos($triggerMsg, 'Tenant already has active contract') !== false) {
        $errorMsg = '❌ ผู้เช่าคนนี้มีสัญญาที่ยังใช้อยู่แล้ว - ไม่สามารถสร้างสัญญาซ้ำได้';
    } elseif (stripos($triggerMsg, 'Duplicate') !== false) {
        $errorMsg = '❌ สัญญาซ้ำ - ไม่สามารถสร้างสัญญาซ้ำสำหรับห้องหรือผู้เช่าเดียวกันได้';
    }
    
    $_SESSION['error'] = $errorMsg;
    header('Location: ../Reports/tenant_wizard.php');
    exit;
}
