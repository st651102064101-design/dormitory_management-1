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

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// CSRF validation
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'CSRF token ไม่ถูกต้อง กรุณาลองใหม่'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $_SESSION['wizard_error'] = 'CSRF token ไม่ถูกต้อง กรุณาลองใหม่';
    header('Location: ../Reports/tenant_wizard.php');
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';
require_once __DIR__ . '/../includes/wizard_helper.php';

$pdo = connectDB();

try {
    $ctr_id = isset($_POST['ctr_id']) ? (int)$_POST['ctr_id'] : 0;
    $tnt_id = trim($_POST['tnt_id'] ?? '');
    $room_price = isset($_POST['room_price']) ? (float)$_POST['room_price'] : 0;
    $rate_water = isset($_POST['rate_water']) ? (float)$_POST['rate_water'] : 0;
    $rate_elec = isset($_POST['rate_elec']) ? (float)$_POST['rate_elec'] : 0;

    if ($ctr_id <= 0 || empty($tnt_id)) {
        throw new Exception('ข้อมูลไม่ครบถ้วน');
    }

    // ตรวจสอบว่าผู้เช่าเซ็นสัญญาหรือยัง (step 3)
    $stmtSig = $pdo->prepare("SELECT COUNT(*) FROM signature_logs WHERE contract_id = ? AND signer_type = 'tenant'");
    $stmtSig->execute([$ctr_id]);
    $tenantSigned = (int)$stmtSig->fetchColumn() > 0;
    if (!$tenantSigned) {
        throw new Exception('ผู้เช่ายังไม่ได้เซ็นสัญญาออนไลน์ ข้อมูลสัญญายังไม่เสร็จสมบูรณ์');
    }

    // ดึง ctr_start เพื่อสร้างบิลในเดือนที่เริ่มสัญญา (ไม่ใช่เดือนถัดไปจากวันนี้)
    $ctrStartStmt = $pdo->prepare('SELECT ctr_start FROM contract WHERE ctr_id = ? LIMIT 1');
    $ctrStartStmt->execute([$ctr_id]);
    $ctrStartVal = $ctrStartStmt->fetchColumn();
    $firstBillMonth = ($ctrStartVal && strtotime((string)$ctrStartVal) !== false)
        ? date('Y-m-01', strtotime((string)$ctrStartVal))
        : date('Y-m-01'); // fallback = เดือนปัจจุบัน

    $pdo->beginTransaction();

        // สร้างบิลรายเดือนแรกแบบ idempotent (ไม่ให้ซ้ำเดือน ctr_start แต่ต้องไม่นับบิลมัดจำ)
        $checkStmt = $pdo->prepare("
            SELECT e.exp_id 
            FROM expense e
            WHERE e.ctr_id = ? 
              AND DATE_FORMAT(e.exp_month, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')
              AND NOT EXISTS (
                  SELECT 1 FROM payment p WHERE p.exp_id = e.exp_id AND TRIM(COALESCE(p.pay_remark, '')) = 'มัดจำ'
              )
            LIMIT 1
        ");
        $checkStmt->execute([$ctr_id, $firstBillMonth]);
        $existingExpenseId = $checkStmt->fetchColumn();

        if (!$existingExpenseId) {
            // หา exp_month ที่ไม่ซ้ำ (เลี่ยง unique key dup)
            $dayCounter = 1;
            $targetMonth = date('Y-m', strtotime($firstBillMonth));
            $firstBillMonth = $targetMonth . '-01';
            while (true) {
                $checkUniq = $pdo->prepare("SELECT 1 FROM expense WHERE ctr_id = ? AND exp_month = ?");
                $checkUniq->execute([$ctr_id, $firstBillMonth]);
                if (!$checkUniq->fetchColumn()) break;
                $dayCounter++;
                $firstBillMonth = $targetMonth . '-' . str_pad((string)$dayCounter, 2, '0', STR_PAD_LEFT);
                if ($dayCounter > 28) break;
            }

            $stmt = $pdo->prepare("
                INSERT INTO expense
                (exp_month, exp_elec_unit, exp_water_unit, rate_elec, rate_water, room_price, exp_elec_chg, exp_water, exp_total, exp_status, ctr_id)
                VALUES (?, 0, 0, ?, ?, ?, 0, 0, ?, '2', ?)
            ");

            $stmt->execute([
                $firstBillMonth,
                $rate_elec,
                $rate_water,
                $room_price,
                $room_price, // exp_total = room_price initially (ยังไม่มีค่าน้ำ-ไฟ)
                $ctr_id
            ]);
        }

    // อัปเดต Workflow Step 5 (ขั้นตอนสุดท้าย)
        updateWorkflowStep($pdo, $tnt_id, 5, $_SESSION['admin_username'], $ctr_id);

    $pdo->commit();

    $successMsg = "🎉 เสร็จสิ้นกระบวนการทั้งหมด! ผู้เช่าพร้อมเข้าพักและเริ่มบิลรายเดือนแล้ว";
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => $successMsg], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $_SESSION['success'] = $successMsg;
    header('Location: ../Reports/tenant_wizard.php?completed=1');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Process Wizard Step 5 Error: " . $e->getMessage());
    $errorMsg = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $errorMsg], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $_SESSION['error'] = $errorMsg;
    header('Location: ../Reports/tenant_wizard.php');
    exit;
}
