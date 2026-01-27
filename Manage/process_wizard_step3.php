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

    // สร้างสัญญา
    $stmt = $pdo->prepare("
        INSERT INTO contract (ctr_start, ctr_end, ctr_deposit, ctr_status, tnt_id, room_id, contract_created_date)
        VALUES (?, ?, ?, '0', ?, ?, NOW())
    ");
    $stmt->execute([$ctr_start, $ctr_end, $ctr_deposit, $tnt_id, $room_id]);

    $ctr_id = (int)$pdo->lastInsertId();

    // อัปเดตสถานะห้อง เป็น "ไม่ว่าง" (1)
    $stmt = $pdo->prepare("UPDATE room SET room_status = '1' WHERE room_id = ?");
    $stmt->execute([$room_id]);

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
    error_log("Process Wizard Step 3 Error: " . $e->getMessage());
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    header('Location: ../Reports/tenant_wizard.php');
    exit;
}
