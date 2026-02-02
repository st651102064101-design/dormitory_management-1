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
    $bp_id = isset($_POST['bp_id']) ? (int)$_POST['bp_id'] : 0;
    $bkg_id = isset($_POST['bkg_id']) ? (int)$_POST['bkg_id'] : 0;
    $tnt_id = trim($_POST['tnt_id'] ?? '');

    if ($bp_id <= 0 || $bkg_id <= 0 || empty($tnt_id)) {
        throw new Exception('ข้อมูลไม่ครบถ้วน');
    }

    $pdo->beginTransaction();

    // สร้างเลขที่ใบเสร็จ
    $receiptNo = generateReceiptNumber();

    // อัปเดตสถานะการชำระเงินจอง
    $stmt = $pdo->prepare("
        UPDATE booking_payment
        SET bp_status = '1',
            bp_payment_date = NOW(),
            bp_receipt_no = ?
        WHERE bp_id = ?
    ");
    $stmt->execute([$receiptNo, $bp_id]);

    // Update Main Payment System (payment table)
    // 1. Get ctr_id
    $stmt = $pdo->prepare("SELECT ctr_id FROM tenant_workflow WHERE bkg_id = ?");
    $stmt->execute([$bkg_id]);
    $workflow = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($workflow && $workflow['ctr_id']) {
        $ctr_id = $workflow['ctr_id'];
        
        // 2. Find the deposit expense (created during booking)
        $stmt = $pdo->prepare("SELECT exp_id FROM expense WHERE ctr_id = ? AND exp_status = '2' ORDER BY exp_id ASC LIMIT 1");
        $stmt->execute([$ctr_id]);
        $expense = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($expense) {
            $exp_id = $expense['exp_id'];
            
            // 3. Update payment record to '1' (Confirmed)
            $stmt = $pdo->prepare("
                UPDATE payment 
                SET pay_status = '1', 
                    pay_date = NOW()
                WHERE exp_id = ?
            ");
            $stmt->execute([$exp_id]);
            
            // 4. Update expense status to '1' (Paid)
            $stmt = $pdo->prepare("UPDATE expense SET exp_status = '1' WHERE exp_id = ?");
            $stmt->execute([$exp_id]);
        }
    }

    // อัปเดต Workflow Step 2
    updateWorkflowStep($pdo, $tnt_id, 2, $_SESSION['admin_username']);

    $pdo->commit();

    $_SESSION['success'] = "ยืนยันการชำระเงินเรียบร้อย! เลขที่ใบเสร็จ: {$receiptNo} | ขั้นตอนถัดไป: สร้างสัญญา";
    header('Location: ../Reports/tenant_wizard.php');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Process Wizard Step 2 Error: " . $e->getMessage());
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    header('Location: ../Reports/tenant_wizard.php');
    exit;
}
