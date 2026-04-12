<?php
/**
 * Tenant Payment - แจ้งชำระเงิน
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/thai_date_helper.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config.php';

$auth = checkTenantAuth();
$pdo = $auth['pdo'];
$token = $auth['token'];
$contract = $auth['contract'];
$settings = getSystemSettings($pdo);

$success = '';
$error = '';
$isAjaxRequest = (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' &&
    (
        strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest' ||
        stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false
    )
);

$currentBillMonth = (new DateTime('first day of this month'))->format('Y-m');
$firstBillMonth = $currentBillMonth;
if (!empty($contract['ctr_start'])) {
    try {
        $ctrStartDate = new DateTime((string)$contract['ctr_start']);
        $firstBillMonth = $ctrStartDate->format('Y-m');
    } catch (Exception $e) {
        $firstBillMonth = $currentBillMonth;
    }
}

// Get unpaid expenses
$unpaidExpenses = [];
$selectedExpense = null;
$selectedExpId = isset($_GET['exp_id']) ? (int)$_GET['exp_id'] : 0;

try {
    $stmt = $pdo->prepare("
        SELECT e.*, r.room_number,
               COALESCE(ps.submitted_amount, 0) AS paid_amount,
               COALESCE(p_dep.deposit_paid, 0) AS deposit_paid_amount
        FROM expense e
        JOIN (
            SELECT MAX(exp_id) AS exp_id
            FROM expense
            WHERE ctr_id = ?
              AND (
                  DATE_FORMAT(expense.exp_month, '%Y-%m') = ?
                  OR EXISTS (
                      SELECT 1
                      FROM utility u
                      WHERE u.ctr_id = expense.ctr_id
                          AND YEAR(u.utl_date) = YEAR(expense.exp_month)
                          AND MONTH(u.utl_date) = MONTH(expense.exp_month)
                          AND u.utl_water_end IS NOT NULL
                          AND u.utl_elec_end IS NOT NULL
                  )
              )
              AND DATE_FORMAT(exp_month, '%Y-%m') >= ?
              AND DATE_FORMAT(exp_month, '%Y-%m') <= ?
            GROUP BY exp_month
        ) latest ON e.exp_id = latest.exp_id
        LEFT JOIN (
            SELECT exp_id, COALESCE(SUM(pay_amount), 0) AS submitted_amount
            FROM payment
            WHERE pay_status IN ('0', '1')
              AND TRIM(COALESCE(pay_remark, '')) <> 'มัดจำ'
            GROUP BY exp_id
        ) ps ON ps.exp_id = e.exp_id
        LEFT JOIN (
            SELECT exp_id, COALESCE(SUM(pay_amount), 0) AS deposit_paid
            FROM payment
            WHERE pay_status IN ('0', '1')
              AND TRIM(COALESCE(pay_remark, '')) = 'มัดจำ'
            GROUP BY exp_id
        ) p_dep ON p_dep.exp_id = e.exp_id
        JOIN contract c ON e.ctr_id = c.ctr_id
        JOIN room r ON c.room_id = r.room_id
        WHERE (e.exp_total - COALESCE(ps.submitted_amount, 0) - COALESCE(p_dep.deposit_paid, 0)) > 0.00001
        ORDER BY e.exp_month DESC
    ");
    $stmt->execute([$contract['ctr_id'], $firstBillMonth, $firstBillMonth, $currentBillMonth]);
    $unpaidExpensesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $unpaidExpenses = [];
    $ctrDeposit = (float)($contract['ctr_deposit'] ?? 2000);
    foreach ($unpaidExpensesRaw as $exp) {
        $expTotal = (float)$exp['exp_total'];
        $roomPrice = (float)($exp['room_price'] ?? 0);
        $elecChg = (float)($exp['exp_elec_chg'] ?? 0);
        $waterChg = (float)($exp['exp_water'] ?? 0);
        $otherFee = $expTotal - ($roomPrice + $elecChg + $waterChg);
        
        $isDepositOnly = ($expTotal > 0 && $expTotal == $ctrDeposit && $elecChg == 0 && $waterChg == 0 && $otherFee > 0);
        
        if ($isDepositOnly) {
            $exp['paid_amount'] = (float)$exp['paid_amount'] + (float)$exp['deposit_paid_amount'];
        } else {
            $depositPaid = (float)($exp['deposit_paid_amount'] ?? 0);
            if ($depositPaid > 0) {
                // Deduct the deposit paid from the displayed bill total
                $expTotal -= $depositPaid;
                $exp['exp_total'] = $expTotal;
            }
        }
        
        if (($expTotal - (float)$exp['paid_amount']) > 0) {
            $unpaidExpenses[] = $exp;
        }
    }
    
    // หา expense ที่เลือก
    if ($selectedExpId > 0) {
        foreach ($unpaidExpenses as $exp) {
            if ($exp['exp_id'] == $selectedExpId) {
                $selectedExpense = $exp;
                break;
            }
        }
    }
} catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }

// ถ้า exp_id ถูกระบุมาแต่ไม่พบในรายการค้างชำระ ให้ตรวจสอบว่าบิลนี้ถูกส่งชำระไปหมดแล้วหรือรอตรวจสอบอยู่
$completedExpense = null;
if ($selectedExpId > 0 && $selectedExpense === null) {
    try {
        $compStmt = $pdo->prepare("
            SELECT e.exp_id, e.exp_month, e.exp_total, r.room_number, e.exp_elec_chg, e.exp_water, e.room_price,
                   COALESCE(SUM(CASE WHEN p.pay_status = '0' AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ' THEN p.pay_amount ELSE 0 END), 0) AS pending_amount,
                   COALESCE(SUM(CASE WHEN p.pay_status = '1' AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ' THEN p.pay_amount ELSE 0 END), 0) AS paid_amount,
                   COALESCE(SUM(CASE WHEN p.pay_status = '0' AND TRIM(COALESCE(p.pay_remark, '')) = 'มัดจำ' THEN p.pay_amount ELSE 0 END), 0) AS deposit_pending_amount,
                   COALESCE(SUM(CASE WHEN p.pay_status = '1' AND TRIM(COALESCE(p.pay_remark, '')) = 'มัดจำ' THEN p.pay_amount ELSE 0 END), 0) AS deposit_paid_amount,
                   MAX(CASE WHEN p.pay_status IN ('0', '1') AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ' THEN p.pay_date END) AS last_pay_date_normal,
                   MAX(CASE WHEN p.pay_status IN ('0', '1') AND TRIM(COALESCE(p.pay_remark, '')) = 'มัดจำ' THEN p.pay_date END) AS last_pay_date_deposit,
                   (SELECT pay_proof FROM payment WHERE exp_id = e.exp_id AND pay_status IN ('0', '1') AND pay_proof IS NOT NULL AND TRIM(COALESCE(pay_remark, '')) <> 'มัดจำ' ORDER BY pay_date DESC LIMIT 1) AS pay_proof_normal,
                   (SELECT pay_proof FROM payment WHERE exp_id = e.exp_id AND pay_status IN ('0', '1') AND pay_proof IS NOT NULL AND TRIM(COALESCE(pay_remark, '')) = 'มัดจำ' ORDER BY pay_date DESC LIMIT 1) AS pay_proof_deposit
            FROM expense e
            JOIN contract c ON e.ctr_id = c.ctr_id
            JOIN room r ON c.room_id = r.room_id
            LEFT JOIN payment p ON p.exp_id = e.exp_id
            WHERE e.exp_id = ? AND e.ctr_id = ?
            GROUP BY e.exp_id, e.exp_month, e.exp_total, r.room_number, e.exp_elec_chg, e.exp_water, e.room_price
        ");
        $compStmt->execute([$selectedExpId, $contract['ctr_id']]);
        $completedExpense = $compStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        
        if ($completedExpense) {
            $eTotal = (float)$completedExpense['exp_total'];
            $rPrice = (float)($completedExpense['room_price'] ?? 0);
            $eChg = (float)($completedExpense['exp_elec_chg'] ?? 0);
            $wChg = (float)($completedExpense['exp_water'] ?? 0);
            $oFee = $eTotal - ($rPrice + $eChg + $wChg);
            $cDep = (float)($contract['ctr_deposit'] ?? 2000);
            
            $isDeposit = ($eTotal > 0 && $eTotal == $cDep && $eChg == 0 && $wChg == 0 && $oFee > 0);
            if ($isDeposit) {
                $completedExpense['pending_amount'] += $completedExpense['deposit_pending_amount'];
                $completedExpense['paid_amount'] += $completedExpense['deposit_paid_amount'];
                $completedExpense['last_pay_date'] = max($completedExpense['last_pay_date_normal'], $completedExpense['last_pay_date_deposit']);
                // Use whichever proof is available/newer
                if (empty($completedExpense['pay_proof_normal'])) {
                    $completedExpense['pay_proof'] = $completedExpense['pay_proof_deposit'];
                } else {
                    $completedExpense['pay_proof'] = $completedExpense['pay_proof_normal'];
                }
            } else {
                $completedExpense['last_pay_date'] = $completedExpense['last_pay_date_normal'];
                $completedExpense['pay_proof'] = $completedExpense['pay_proof_normal'];
            }
        }
    } catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetPath = '';
    try {
        $exp_id = (int)($_POST['exp_id'] ?? 0);
        $pay_amount = (int)($_POST['pay_amount'] ?? 0);
        
        if ($exp_id <= 0) {
            $error = 'กรุณาเลือกบิลที่ต้องการชำระ';
        } elseif (empty($_FILES['pay_proof']['name'])) {
            $error = 'กรุณาแนบหลักฐานการชำระเงิน';
        } else {
            // Handle file upload
            $file = $_FILES['pay_proof'];
            $maxFileSize = 5 * 1024 * 1024; // 5MB
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            
            if ($file['size'] > $maxFileSize) {
                throw new Exception('ไฟล์ใหญ่เกินไป (ไม่เกิน 5MB)');
            }
            
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedMimes)) {
                throw new Exception('ประเภทไฟล์ไม่ถูกต้อง (สนับสนุน JPG, PNG, WebP)');
            }
            
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $uploadsDir = dirname(__DIR__) . '/Public/Assets/Images/Payments';
            if (!is_dir($uploadsDir)) {
                if (!mkdir($uploadsDir, 0777, true) && !is_dir($uploadsDir)) {
                    throw new Exception('ไม่สามารถสร้างโฟลเดอร์อัพโหลดได้');
                }
            }

            // XAMPP/Apache may run with a different user than project owner.
            if (!is_writable($uploadsDir)) {
                @chmod($uploadsDir, 0777);
            }
            if (!is_writable($uploadsDir)) {
                throw new Exception('โฟลเดอร์อัพโหลดไม่มีสิทธิ์เขียนไฟล์');
            }
            
            $filename = 'payment_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $targetPath = $uploadsDir . '/' . $filename;
            
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                throw new Exception('ไม่สามารถอัพโหลดไฟล์ได้');
            }

            // Lock expense row + related payments to prevent duplicate submissions after refresh or rapid retries.
            $pdo->beginTransaction();

            $checkStmt = $pdo->prepare("
                SELECT e.*, c.ctr_deposit 
                FROM expense e 
                JOIN contract c ON e.ctr_id = c.ctr_id 
                WHERE e.exp_id = ? AND e.ctr_id = ? 
                FOR UPDATE
            ");
            $checkStmt->execute([$exp_id, $contract['ctr_id']]);
            $expense = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if (!$expense) {
                throw new Exception('ไม่พบบิลที่ระบุ');
            }

            // ข้ามการตรวจมิเตอร์สำหรับบิลเดือนแรก (ctr_start) เพราะใช้ค่าจาก checkin record
            $expMonthYm = date('Y-m', strtotime((string)$expense['exp_month']));
            $isFirstBill = ($expMonthYm === $firstBillMonth);
            if (!$isFirstBill) {
                $meterCheckStmt = $pdo->prepare("
                    SELECT 1
                    FROM utility
                    WHERE ctr_id = ?
                      AND YEAR(utl_date) = YEAR(?)
                      AND MONTH(utl_date) = MONTH(?)
                      AND utl_water_end IS NOT NULL
                      AND utl_elec_end IS NOT NULL
                    LIMIT 1
                ");
                $meterCheckStmt->execute([$contract['ctr_id'], (string)$expense['exp_month'], (string)$expense['exp_month']]);
                if (!$meterCheckStmt->fetchColumn()) {
                    throw new Exception('บิลนี้ยังไม่ได้จดมิเตอร์ครบ จึงยังไม่สามารถชำระได้');
                }
            }

            $sumRowsStmt = $pdo->prepare("SELECT pay_amount, pay_remark FROM payment WHERE exp_id = ? AND pay_status IN ('0', '1') FOR UPDATE");
            $sumRowsStmt->execute([$exp_id]);
            $submittedAmount = 0;
            $depositPaidAmount = 0;
            foreach ($sumRowsStmt->fetchAll(PDO::FETCH_ASSOC) as $payRow) {
                if (trim((string)($payRow['pay_remark'] ?? '')) === 'มัดจำ') {
                    $depositPaidAmount += (float)($payRow['pay_amount'] ?? 0);
                } else {
                    $submittedAmount += (float)($payRow['pay_amount'] ?? 0);
                }
            }

            $ctrDeposit = (float)($expense['ctr_deposit'] ?? 2000);
            $expTotalRaw = (float)($expense['exp_total'] ?? 0);
            $roomPrice = (float)($expense['room_price'] ?? 0);
            $elecChg = (float)($expense['exp_elec_chg'] ?? 0);
            $waterChg = (float)($expense['exp_water'] ?? 0);
            $otherFee = $expTotalRaw - ($roomPrice + $elecChg + $waterChg);
            
            $isDepositOnly = ($expTotalRaw > 0 && $expTotalRaw == $ctrDeposit && $elecChg == 0 && $waterChg == 0 && $otherFee > 0);
            
            $expTotal = $expTotalRaw;
            if ($isDepositOnly) {
                // If it is deposit only, count deposit payment as normal payment
                $submittedAmount += $depositPaidAmount;
            } else {
                // Deduct the deposit paid from the bill total
                if ($depositPaidAmount > 0) {
                    $expTotal -= $depositPaidAmount;
                }
            }

            // ปกป้องการส่งซ้ำ: ตรวจสอบว่ามีการแจ้งชำระเงินที่ยังรอตรวจสอบอยู่สำหรับบิลนี้หรือไม่ (ไม่นับมัดจำ)
            $checkPendingStmt = $pdo->prepare("SELECT COUNT(*) FROM payment WHERE exp_id = ? AND pay_status = '0' AND TRIM(COALESCE(pay_remark, '')) <> 'มัดจำ'");
            $checkPendingStmt->execute([$exp_id]);
            if ($checkPendingStmt->fetchColumn() > 0) {
                throw new Exception('มีการแจ้งชำระเงินที่รอการตรวจสอบอยู่แล้ว กรุณารอผู้ดูแลตรวจสอบก่อนส่งใหม่');
            }

            $remainingAmount = max(0, $expTotal - $submittedAmount);
            if ($remainingAmount <= 0) {
                throw new Exception('บิลนี้ถูกส่งชำระครบแล้ว ไม่สามารถส่งซ้ำได้');
            }

            $recordAmount = $pay_amount > 0 ? $pay_amount : $remainingAmount;
            if ($recordAmount <= 0) {
                throw new Exception('จำนวนเงินไม่ถูกต้อง');
            }
            if ($recordAmount > $remainingAmount) {
                throw new Exception('จำนวนเงินเกินยอดคงเหลือของบิลนี้');
            }
            
            // Insert payment record
            $stmt = $pdo->prepare("
                INSERT INTO payment (pay_date, pay_amount, pay_proof, pay_status, exp_id)
                VALUES (CURDATE(), ?, ?, '0', ?)
            ");
            $stmt->execute([$recordAmount, $filename, $exp_id]);

            $pdo->commit();

            require_once __DIR__ . '/../LineHelper.php';
            if (function_exists('sendLineToContract')) {
                $tenantName = $contract['tnt_name'] ?? 'ผู้เช่า';
                $roomNumberText = $contract['room_number'] ?? 'ไม่ทราบห้อง';
                $monthStr = isset($expense['exp_month']) ? date('m/Y', strtotime((string)$expense['exp_month'])) : '';
                $msg = "💰 แจ้งการชำระเงินใหม่!\n";
                $msg .= "------------------------\n";
                $msg .= "ผู้เช่า: {$tenantName}\n";
                $msg .= "ห้อง: {$roomNumberText}\n";
                if ($monthStr) {
                    $msg .= "บิลประจำเดือน: {$monthStr}\n";
                }
                $msg .= "ยอดโอน: ฿" . number_format((float)$recordAmount, 2) . "\n";
                $msg .= "------------------------\n";
                $msg .= "กรุณาตรวจสอบหลักฐานในระบบ\n";
                $msg .= getBaseUrl('/Manage/index.php');
                sendLineToContract($pdo, (int)$contract['ctr_id'], $msg);
            }

            $remainingAfterSubmit = max(0, $remainingAmount - $recordAmount);
            
            $success = 'แจ้งชำระเงินเรียบร้อยแล้ว รอการตรวจสอบจากผู้ดูแล';
            
            // Refresh unpaid expenses
            $stmt = $pdo->prepare("
                SELECT e.*, r.room_number,
                       COALESCE(ps.submitted_amount, 0) AS paid_amount,
                       COALESCE(p_dep.deposit_paid, 0) AS deposit_paid
                FROM expense e
                JOIN (
                    SELECT MAX(exp_id) AS exp_id
                    FROM expense
                    WHERE ctr_id = ?
                      AND (
                          DATE_FORMAT(exp_month, '%Y-%m') = ?
                          OR EXISTS (
                              SELECT 1
                              FROM utility u
                              WHERE u.ctr_id = expense.ctr_id
                                  AND YEAR(u.utl_date) = YEAR(expense.exp_month)
                                  AND MONTH(u.utl_date) = MONTH(expense.exp_month)
                                  AND u.utl_water_end IS NOT NULL
                                  AND u.utl_elec_end IS NOT NULL
                          )
                      )
                      AND DATE_FORMAT(exp_month, '%Y-%m') >= ?
                      AND DATE_FORMAT(exp_month, '%Y-%m') <= ?
                    GROUP BY exp_month
                ) latest ON e.exp_id = latest.exp_id
                LEFT JOIN (
                    SELECT exp_id, COALESCE(SUM(pay_amount), 0) AS submitted_amount
                    FROM payment
                    WHERE pay_status IN ('0', '1')
                      AND TRIM(COALESCE(pay_remark, '')) <> 'มัดจำ'
                    GROUP BY exp_id
                ) ps ON ps.exp_id = e.exp_id
                LEFT JOIN (
                    SELECT exp_id, COALESCE(SUM(pay_amount), 0) AS deposit_paid
                    FROM payment
                    WHERE pay_status IN ('0', '1')
                      AND TRIM(COALESCE(pay_remark, '')) = 'มัดจำ'
                    GROUP BY exp_id
                ) p_dep ON p_dep.exp_id = e.exp_id
                JOIN contract c ON e.ctr_id = c.ctr_id
                JOIN room r ON c.room_id = r.room_id
                WHERE (e.exp_total - COALESCE(ps.submitted_amount, 0) - COALESCE(p_dep.deposit_paid, 0)) > 0.00001
                ORDER BY e.exp_month DESC
            ");
            $stmt->execute([$contract['ctr_id'], $firstBillMonth, $firstBillMonth, $currentBillMonth]);
            $unpaidExpensesRaw2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $unpaidExpenses = [];
            foreach ($unpaidExpensesRaw2 as $exp) {
                $expTotal = (float)$exp['exp_total'];
                $depositPaid = (float)($exp['deposit_paid'] ?? 0);
                if ($depositPaid > 0 && !($expTotal > 0 && $expTotal == $ctrDeposit && (float)($exp['exp_elec_chg']??0) == 0 && (float)($exp['exp_water']??0) == 0)) {
                    $expTotal -= $depositPaid;
                    $exp['exp_total'] = $expTotal;
                }
                if (($expTotal - (float)($exp['paid_amount']??0)) > 0) {
                    $unpaidExpenses[] = $exp;
                }
            }
        }
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($targetPath !== '' && is_file($targetPath)) {
            @unlink($targetPath);
        }
        $error = $e->getMessage();
    }

    if ($isAjaxRequest) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $error === '',
            'message' => $error === '' ? $success : $error,
            'error' => $error,
            'exp_id' => isset($exp_id) ? (int)$exp_id : 0,
            'submitted_amount' => isset($submittedAmount, $recordAmount) ? (int)($submittedAmount + $recordAmount) : null,
            'remaining_amount' => isset($remainingAfterSubmit) ? (int)$remainingAfterSubmit : null,
            'pay_amount' => isset($recordAmount) ? (int)$recordAmount : null,
            'pay_date' => isset($recordAmount) ? date('Y-m-d') : null,
            'exp_month' => isset($expense['exp_month']) ? (string)$expense['exp_month'] : null,
            'pay_proof' => isset($filename) ? (string)$filename : null,
            'pay_proof_url' => isset($filename) ? '/dormitory_management/Public/Assets/Images/Payments/' . rawurlencode((string)$filename) : null,
            'pay_remark' => '',
            'pay_status' => isset($recordAmount) ? '0' : null,
            'pay_status_label' => 'รอตรวจสอบ',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Get payment history
$payments = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.*, e.exp_month, e.exp_total 
        FROM payment p
        JOIN expense e ON p.exp_id = e.exp_id
        WHERE e.ctr_id = ?
          
        ORDER BY p.pay_date DESC
    ");
    $stmt->execute([$contract['ctr_id']]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }

$unpaidReportItems = [];
$unpaidReportTotal = 0.0;
foreach ($unpaidExpenses as $exp) {
    $total = (float)($exp['exp_total'] ?? 0);
    $submitted = (float)($exp['paid_amount'] ?? 0);
    $remaining = max(0, $total - $submitted);
    if ($remaining <= 0) {
        continue;
    }

    $unpaidReportItems[] = [
        'exp_id' => (int)($exp['exp_id'] ?? 0),
        'exp_month' => (string)($exp['exp_month'] ?? ''),
        'total' => $total,
        'submitted' => $submitted,
        'remaining' => $remaining,
        'status' => $submitted > 0 ? 'ชำระบางส่วน' : 'รอชำระ',
    ];
    $unpaidReportTotal += $remaining;
}

$paymentStatusMap = [
    '0' => ['label' => 'รอตรวจสอบ', 'color' => '#f59e0b', 'bg' => 'rgba(245, 158, 11, 0.2)'],
    '1' => ['label' => 'อนุมัติแล้ว', 'color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.2)'],
    '2' => ['label' => 'ตีกลับ', 'color' => '#ef4444', 'bg' => 'rgba(239, 68, 68, 0.2)']
];
$defaultPaymentStatus = ['label' => 'ไม่ทราบสถานะ', 'color' => '#94a3b8', 'bg' => 'rgba(148, 163, 184, 0.2)'];
$paymentProofBaseUrl = '/dormitory_management/Public/Assets/Images/Payments/';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>แจ้งชำระเงิน - <?php echo htmlspecialchars($settings['site_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($settings['logo_filename']); ?>">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Prompt', system-ui, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            color: #e2e8f0;
            padding-bottom: 80px;
        }
        .header {
            background: rgba(15, 23, 42, 0.95);
            padding: 1rem;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
        }
        .header-content {
            max-width: 600px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .back-btn {
            color: #94a3b8;
            text-decoration: none;
            font-size: 1.5rem;
            padding: 0.5rem;
        }
        .header-title { font-size: 1.1rem; color: #f8fafc; }
        .container { max-width: 600px; margin: 0 auto; padding: 1rem; }
        .form-section {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .section-title {
            font-size: 1rem;
            color: #f8fafc;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .form-group { margin-bottom: 1rem; }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            color: #94a3b8;
            margin-bottom: 0.5rem;
        }
        .form-group select, .form-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: #f8fafc;
            font-size: 0.95rem;
            font-family: inherit;
        }
        .form-group select:focus, .form-group input:focus {
            outline: none;
            border-color: #3b82f6;
        }
        .file-upload {
            position: relative;
            width: 100%;
            height: 120px;
            background: rgba(15, 23, 42, 0.8);
            border: 2px dashed rgba(255,255,255,0.2);
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: border-color 0.2s;
        }
        .file-upload:hover { border-color: #3b82f6; }
        .file-upload input {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        .file-upload-icon { font-size: 2rem; margin-bottom: 0.5rem; }
        .file-upload-text { font-size: 0.85rem; color: #94a3b8; }
        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }
        .btn-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
            .payment-form-response.success {
                background: rgba(16, 185, 129, 0.14);
                border: 1px solid rgba(16, 185, 129, 0.35);
                color: #34d399;
            }
            .payment-form-response.error {
                background: rgba(239, 68, 68, 0.14);
                border: 1px solid rgba(239, 68, 68, 0.35);
                color: #fca5a5;
            }
        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
        }
        .bill-card {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            cursor: pointer;
            transition: border-color 0.2s;
        }
        .bill-card:hover { border-color: #3b82f6; }
        .bill-card.selected { border-color: #10b981; background: rgba(16, 185, 129, 0.1); }
        .bill-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .bill-month { font-weight: 600; color: #f8fafc; }
        .bill-total { font-size: 1.2rem; font-weight: 700; color: #f59e0b; }
        .bill-details { font-size: 0.8rem; color: #94a3b8; }
        .payment-history { margin-top: 2rem; }
        .unpaid-report {
            margin-top: 1.25rem;
            margin-bottom: 1.25rem;
        }
        .unpaid-report-card {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(239, 68, 68, 0.25);
            border-radius: 12px;
            padding: 0.95rem 1rem;
            margin-bottom: 0.75rem;
        }
        .unpaid-report-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.4rem;
        }
        .unpaid-report-month {
            font-weight: 600;
            color: #f8fafc;
        }
        .unpaid-report-status {
            font-size: 0.75rem;
            color: #fbbf24;
        }
        .unpaid-report-row {
            display: flex;
            justify-content: space-between;
            color: #94a3b8;
            font-size: 0.82rem;
            margin-bottom: 0.25rem;
        }
        .unpaid-report-row strong {
            color: #ef4444;
        }
        .unpaid-report-total {
            margin-bottom: 0.75rem;
            color: #ef4444;
            font-weight: 700;
        }
        .payment-item {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.75rem;
        }
        .payment-item.payment-item-clickable {
            cursor: pointer;
            transition: transform 0.18s ease, border-color 0.18s ease, background 0.18s ease;
        }
        .payment-item.payment-item-clickable:hover {
            transform: translateY(-1px);
            border-color: rgba(59, 130, 246, 0.55);
            background: rgba(30, 41, 59, 0.95);
        }
        .payment-item.payment-item-clickable:focus-visible {
            outline: 2px solid rgba(96, 165, 250, 0.85);
            outline-offset: 2px;
        }
        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .payment-date { font-size: 0.8rem; color: #64748b; }
        .payment-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .payment-amount { font-size: 1rem; font-weight: 600; color: #f8fafc; }
        .payment-status-detail { margin-top: 0.3rem; font-size: 0.78rem; color: #94a3b8; }
        body.sheet-open { overflow: hidden; }
        .history-sheet-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.36);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.22s ease;
            z-index: 1700;
        }
        .history-sheet-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }
        .history-sheet {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            width: min(600px, 100%);
            margin: 0 auto;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-bottom: none;
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
            transform: translateY(105%);
            transition: transform 0.25s ease;
            padding: 0 1rem 1.2rem;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 -22px 44px rgba(15, 23, 42, 0.22);
        }
        .history-sheet-overlay.active .history-sheet {
            transform: translateY(0);
        }
        .history-sheet-handle {
            width: 48px;
            height: 5px;
            border-radius: 999px;
            background: rgba(148, 163, 184, 0.55);
            margin: 0.75rem auto 0.85rem;
            cursor: pointer;
            touch-action: none;
        }
        .history-sheet-handle:hover {
            cursor: pointer;
        }
        .history-sheet-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.7rem;
            gap: 0.75rem;
        }
        .history-sheet-title {
            font-size: 1rem;
            color: #0f172a;
            font-weight: 600;
        }
        .history-sheet-close {
            border: none;
            background: #e2e8f0;
            color: #334155;
            width: 34px;
            height: 34px;
            border-radius: 999px;
            font-size: 1.05rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .history-sheet-close:hover {
            background: #cbd5e1;
        }
        .history-sheet-details {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 0.8rem 0.9rem;
        }
        .history-sheet-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 0.75rem;
            font-size: 0.88rem;
            color: #334155;
            margin-bottom: 0.45rem;
        }
        .history-sheet-row:last-child {
            margin-bottom: 0;
        }
        .history-sheet-label {
            color: #64748b;
        }
        .history-sheet-value {
            color: #0f172a;
            font-weight: 600;
            text-align: right;
        }
        .history-sheet-status {
            margin: 0.75rem 0;
        }
        .history-sheet-proof {
            margin-top: 0.35rem;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            padding: 0.7rem;
        }
        .history-sheet-proof-title {
            font-size: 0.82rem;
            color: #64748b;
            margin-bottom: 0.55rem;
        }
        .history-sheet-proof-image {
            width: 100%;
            border-radius: 10px;
            max-height: 48vh;
            object-fit: contain;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            display: none;
        }
        .history-sheet-no-proof {
            border-radius: 10px;
            border: 1px dashed #cbd5e1;
            text-align: center;
            padding: 1.15rem 0.9rem;
            color: #64748b;
            background: #f8fafc;
            font-size: 0.84rem;
        }
        .history-sheet-proof-link {
            margin-top: 0.6rem;
            display: none;
            width: 100%;
            text-align: center;
            text-decoration: none;
            padding: 0.62rem 0.8rem;
            border-radius: 10px;
            font-size: 0.86rem;
            font-weight: 600;
            color: #0f172a;
            background: linear-gradient(135deg, #38bdf8, #2563eb);
        }
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #64748b;
        }
        .empty-state-icon { font-size: 3rem; margin-bottom: 0.5rem; }
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(15, 23, 42, 0.98);
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 0.5rem;
            backdrop-filter: blur(10px);
        }
        .bottom-nav-content {
            max-width: 600px;
            margin: 0 auto;
            display: flex;
            justify-content: space-around;
        }
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: #64748b;
            padding: 0.5rem 1rem;
            font-size: 0.7rem;
            transition: color 0.2s;
            position: relative;
        }
        .nav-item.active, .nav-item:hover { color: #3b82f6; }
        .nav-badge {
            position: absolute;
            top: -2px;
            right: -6px;
            background: #ef4444;
            color: white;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 12px;
            min-width: 20px;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .nav-icon { font-size: 1.3rem; margin-bottom: 0.25rem; }
        .nav-icon svg {
            width: 22px;
            height: 22px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }
        .section-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .section-icon svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }
        .alert-icon svg {
            width: 20px;
            height: 20px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }
        .file-upload-icon svg {
            width: 32px;
            height: 32px;
            stroke: #64748b;
            stroke-width: 2;
            fill: none;
        }
        .btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
        }
        .btn-icon svg {
            width: 18px;
            height: 18px;
            stroke: white;
            stroke-width: 2;
            fill: none;
        }
        .empty-state-icon svg {
            width: 48px;
            height: 48px;
            stroke: #64748b;
            stroke-width: 1.5;
            fill: none;
        }
        .date-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .date-icon svg {
            width: 12px;
            height: 12px;
            stroke: #64748b;
            stroke-width: 2;
            fill: none;
            margin-right: 4px;
        }
        .no-unpaid-icon svg {
            width: 32px;
            height: 32px;
            stroke: #34d399;
            stroke-width: 2;
            fill: none;
        }
        .amount-icon svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
            margin-right: 4px;
            vertical-align: middle;
        }
        #preview-container { display: none; margin-top: 0.5rem; }
        #preview-container img { max-width: 100%; max-height: 150px; border-radius: 8px; }
        .no-unpaid {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            color: #34d399;
        }
        /* Bank Info Styles */
        .bank-info-section {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.9) 0%, rgba(51, 65, 85, 0.9) 100%);
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        .bank-info-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.85rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .bank-info-item:last-child {
            border-bottom: none;
        }
        .bank-info-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .bank-info-icon.bank {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }
        .bank-info-icon.account {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }
        .bank-info-icon.number {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        .bank-info-icon.promptpay {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }
        .bank-info-icon svg {
            width: 22px;
            height: 22px;
            stroke: white;
            stroke-width: 2;
            fill: none;
        }
        .bank-info-content {
            flex: 1;
        }
        .bank-info-label {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-bottom: 2px;
        }
        .bank-info-value {
            font-size: 1rem;
            font-weight: 600;
            color: #f8fafc;
        }
        .copy-text {
            cursor: pointer;
            transition: color 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .copy-text:hover {
            color: #3b82f6;
        }
        .copy-text:active {
            transform: scale(0.98);
        }
        .copy-icon {
            font-size: 0.9rem;
            opacity: 0.6;
        }
        .copy-toast {
            position: fixed;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(16, 185, 129, 0.95);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 30px;
            font-size: 0.9rem;
            font-weight: 600;
            z-index: 1000;
            animation: toastIn 0.3s ease, toastOut 0.3s ease 1.5s forwards;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        @keyframes toastIn {
            from { opacity: 0; transform: translateX(-50%) translateY(20px); }
            to { opacity: 1; transform: translateX(-50%) translateY(0); }
        }
        @keyframes toastOut {
            from { opacity: 1; transform: translateX(-50%) translateY(0); }
            to { opacity: 0; transform: translateX(-50%) translateY(-20px); }
        }
    </style>
    <?php if (($settings['public_theme'] ?? '') === 'light'): ?>
    <link rel="stylesheet" href="tenant-light-theme.css">
    <?php endif; ?>
</head>
<body class="<?= ($settings['public_theme'] ?? '') === 'light' ? 'light-theme' : '' ?>">
    <header class="header">
        <div class="header-content">
            <a href="index.php?token=<?php echo urlencode($token); ?>" class="back-btn">←</a>
            <h1 class="header-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span> แจ้งชำระเงิน</h1>
        </div>
    </header>
    
    <div class="container">
        <?php if ($success): ?>
        <div class="alert alert-success">
            <span class="alert-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>
            <span><?php echo htmlspecialchars($success); ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-error">
            <span class="alert-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></span>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
        <?php endif; ?>
        
        <!-- Bank Information Section -->
        <?php if ((!empty($settings['bank_name']) || !empty($settings['promptpay_number'])) && !$pendingExpense && !empty($unpaidExpenses)): ?>
        <div class="form-section bank-info-section">
            <div class="section-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></span> ข้อมูลการชำระเงิน</div>
            
            <?php if (!empty($settings['bank_name'])): ?>
            <div class="bank-info-item">
                <div class="bank-info-icon bank"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M3 10h18"/><path d="M5 6l7-3 7 3"/><path d="M4 10v11"/><path d="M20 10v11"/><path d="M8 14v3"/><path d="M12 14v3"/><path d="M16 14v3"/></svg></div>
                <div class="bank-info-content">
                    <div class="bank-info-label">ธนาคาร</div>
                    <div class="bank-info-value"><?php echo htmlspecialchars($settings['bank_name']); ?></div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($settings['bank_account_name'])): ?>
            <div class="bank-info-item">
                <div class="bank-info-icon account"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                <div class="bank-info-content">
                    <div class="bank-info-label">ชื่อบัญชี</div>
                    <div class="bank-info-value"><?php echo htmlspecialchars($settings['bank_account_name']); ?></div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($settings['bank_account_number'])): ?>
            <div class="bank-info-item">
                <div class="bank-info-icon number"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M7 15h0M2 9.5h20"/></svg></div>
                <div class="bank-info-content">
                    <div class="bank-info-label">เลขบัญชี</div>
                    <div class="bank-info-value copy-text" onclick="copyToClipboard('<?php echo htmlspecialchars($settings['bank_account_number']); ?>')"><?php echo htmlspecialchars($settings['bank_account_number']); ?> <span class="copy-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;vertical-align:middle;opacity:0.75;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></span></div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($settings['promptpay_number'])): ?>
            <div class="bank-info-item" style="flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 1rem; width: 100%;">
                    <div class="bank-info-icon promptpay"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg></div>
                    <div class="bank-info-content">
                        <div class="bank-info-label">พร้อมเพย์</div>
                        <div class="bank-info-value copy-text" onclick="copyToClipboard('<?php echo htmlspecialchars($settings['promptpay_number']); ?>')"><?php echo htmlspecialchars($settings['promptpay_number']); ?> <span class="copy-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;vertical-align:middle;opacity:0.75;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></span></div>
                    </div>
                </div>
                <div style="width: 100%; display: flex; justify-content: center; margin-top: 1rem; flex-direction: column; align-items: center;">
                    <img id="promptpay-qr" src="https://promptpay.io/<?php echo urlencode($settings['promptpay_number']); ?>.png" alt="PromptPay QR Code" style="max-width: 180px; background: white; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                    <small style="color:#94a3b8; margin-top: 8px;">สแกนเพื่อชำระเงิน <span id="promptpay-qr-amount-text" style="display:none; color:#ef4444; font-weight:bold;"></span></small>
                </div>
            </div>
            <script>
                // เก็บค่า promptpay number ไว้ใช้ใน Javascript
                window.tenantPromptpayNumber = <?php echo json_encode($settings['promptpay_number']); ?>;
            </script>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php 
        $isFullyApproved = false;
        $isCompletedOrPending = false;
        if ($completedExpense) {
            $totalPaidOrPending = (float)($completedExpense['pending_amount'] ?? 0) + (float)($completedExpense['paid_amount'] ?? 0);
            if ($totalPaidOrPending > 0 && $totalPaidOrPending >= (float)$completedExpense['exp_total']) {
                $isCompletedOrPending = true;
                if ((float)($completedExpense['paid_amount'] ?? 0) >= (float)$completedExpense['exp_total']) {
                    $isFullyApproved = true;
                }
            }
        }
        $shouldShowPaymentSection = (!empty($unpaidExpenses)) || ($completedExpense && $isCompletedOrPending && !$isFullyApproved);
        ?>
        
        <?php if ($shouldShowPaymentSection): ?>
        <div class="form-section">
            <div class="section-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg></span> แจ้งชำระเงิน</div>
            
            <?php if ($isCompletedOrPending && !$isFullyApproved): ?>
            <div id="paymentStatusCard" data-state="pending" style="background:rgba(245,158,11,0.12);border:1px solid rgba(245,158,11,0.35);border-radius:14px;padding:1.25rem;text-align:center;">
                <div style="font-size:2rem;margin-bottom:0.5rem;">⏳</div>
                <div style="color:#fbbf24;font-weight:600;font-size:1rem;margin-bottom:0.4rem;">รออนุมัติการชำระเงิน</div>
                <div style="color:#92400e;font-size:0.88rem;font-weight:600;margin-bottom:0.75rem;">
                    บิล<?php echo thaiMonthYear($completedExpense['exp_month']); ?> — ยอด <?php echo number_format((float)$completedExpense['exp_total']); ?> บาท<br>
                    ส่งสลิปแล้วรวม <?php echo number_format((float)$totalPaidOrPending); ?> บาท
                </div>
                <?php if (!empty($completedExpense['pay_proof'])): ?>
                <div style="margin: 1rem 0;">
                    <img src="/dormitory_management/Public/Assets/Images/Payments/<?php echo htmlspecialchars($completedExpense['pay_proof']); ?>" alt="หลักฐานการชำระเงิน" style="max-width: 100%; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); max-height: 300px; object-fit: contain;">
                </div>
                <?php endif; ?>
                <div style="font-size:0.82rem;color:#94a3b8;">หากมีข้อสงสัยกรุณาติดต่อผู้ดูแลหอพัก</div>
            </div>
            <?php else: ?>
            <form method="POST" enctype="multipart/form-data" id="paymentForm" data-state="form">
                <div id="paymentFormResponse" class="payment-form-response" style="display:none; margin-bottom: 1rem; padding: 0.9rem 1rem; border-radius: 12px; font-size: 0.92rem; line-height: 1.45;"></div>
                <div class="form-group" style="<?php echo (count($unpaidExpenses) == 1) ? 'display:none;' : ''; ?>">
                    <label>เลือกบิลที่ต้องการชำระ *</label>
                    <select name="exp_id" id="exp_id" <?php echo (count($unpaidExpenses) != 1) ? 'required' : ''; ?> onchange="updatePaymentAmount()">
                        <?php if (count($unpaidExpenses) != 1): ?>
                        <option value="">-- เลือกบิล --</option>
                        <?php endif; ?>
                        <?php foreach ($unpaidExpenses as $expense): ?>
                        <?php 
                            $paidAmount = (float)($expense['paid_amount'] ?? 0);
                            $expTotal = (float)$expense['exp_total'];
                            $remaining = $expTotal - $paidAmount;
                        ?>
                        <option value="<?php echo $expense['exp_id']; ?>" 
                                data-total="<?php echo $expTotal; ?>"
                                data-paid="<?php echo $paidAmount; ?>"
                                data-remaining="<?php echo $remaining; ?>"
                                <?php echo ($selectedExpId == $expense['exp_id'] || count($unpaidExpenses) == 1) ? 'selected' : ''; ?>>
                            <?php echo thaiMonthYear($expense['exp_month']); ?> - 
                            ยอดรวม <?php echo number_format($expTotal); ?> บาท
                            <?php if ($paidAmount > 0): ?>
                                (ส่งแล้ว <?php echo number_format($paidAmount); ?> / คงเหลือ <?php echo number_format($remaining); ?>)
                            <?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if (count($unpaidExpenses) == 1): ?>
                <?php 
                    $singleExp = $unpaidExpenses[0];
                    $singlePaid = (float)($singleExp['paid_amount'] ?? 0);
                    $singleTotal = (float)$singleExp['exp_total'];
                    $singleRemain = $singleTotal - $singlePaid;
                ?>
                <div class="form-group" id="single-expense-group">
                    <label>บิลที่ต้องการชำระ</label>
                    <div id="single-expense-info" 
                         data-expid="<?php echo $unpaidExpenses[0]['exp_id']; ?>" 
                         data-total="<?php echo $singleTotal; ?>" 
                         data-paid="<?php echo $singlePaid; ?>" 
                         data-remaining="<?php echo $singleRemain; ?>"
                         style="background: #f8fafc; padding: 1rem; border-radius: 8px; border: 1px solid #e2e8f0; color: #000; font-weight: 500;">
                        <?php 
                            echo thaiMonthYear($singleExp['exp_month']) . " - ยอดรวม " . number_format($singleTotal) . " บาท";
                            if ($singlePaid > 0) {
                                echo " (ส่งแล้ว " . number_format($singlePaid) . " / คงเหลือ " . number_format($singleRemain) . ")";
                            }
                        ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="form-group" id="payment-summary" style="display: none; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="color: #94a3b8;">ยอดรวมทั้งหมด:</span>
                        <span id="summary-total" style="color: #ef4444; font-weight: 600;">0 บาท</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="color: #10b981;">ส่งแล้ว:</span>
                        <span id="summary-paid" style="color: #10b981; font-weight: 600;">0 บาท</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding-top: 0.5rem; border-top: 1px solid rgba(255,255,255,0.1);">
                        <span style="color: #ef4444; font-weight: 600;">คงเหลือ:</span>
                        <span id="summary-remaining" style="color: #ef4444; font-weight: 700; font-size: 1.1rem;">0 บาท</span>
                    </div>
                </div>
                
                <div class="form-group" id="pay-amount-group" style="display: none;">
                    <label>จำนวนเงินที่ต้องการชำระ (บาท) *</label>
                    <input type="number" name="pay_amount" id="pay_amount" min="1" step="1" placeholder="กรอกจำนวนเงิน" required>
                    <small style="color: #94a3b8; font-size: 0.75rem; display: block; margin-top: 0.25rem;">
                        สามารถชำระเต็มจำนวนหรือบางส่วนได้
                    </small>
                </div>
                
                <div class="form-group" id="pay-proof-group" style="display: none;">
                    <label>หลักฐานการชำระเงิน (สลิป) *</label>
                    <div class="file-upload">
                        <input type="file" name="pay_proof" id="pay_proof" accept="image/jpeg,image/png,image/webp" onchange="previewImage(this)" required>
                        <div class="file-upload-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></div>
                        <div class="file-upload-text">แตะเพื่อเลือกรูปสลิป</div>
                    </div>
                    <div id="preview-container">
                        <img id="preview-image" src="" alt="Preview">
                    </div>
                </div>
                
                <button type="submit" class="btn-submit" id="submitBtn"><span class="btn-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg></span> ส่งแจ้งชำระเงิน</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="unpaid-report">
            <div class="section-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></span> รายงานรอชำระ</div>
            <?php if (empty($unpaidReportItems)): ?>
                <div class="empty-state" style="padding:1rem 0;color:#34d399;">ไม่มียอดค้างชำระ</div>
            <?php else: ?>
                <div class="unpaid-report-total">ยอดค้างรวม <?php echo number_format($unpaidReportTotal); ?> บาท</div>
                <?php foreach ($unpaidReportItems as $item): ?>
                    <div class="unpaid-report-card">
                        <div class="unpaid-report-top">
                            <div class="unpaid-report-month"><?php echo $item['exp_month'] ? thaiMonthYear($item['exp_month']) : '-'; ?></div>
                            <div class="unpaid-report-status"><?php echo htmlspecialchars($item['status']); ?></div>
                        </div>
                        <div class="unpaid-report-row"><span>ยอดบิล</span><span><?php echo number_format($item['total']); ?> บาท</span></div>
                        <div class="unpaid-report-row"><span>ส่งแล้ว</span><span><?php echo number_format($item['submitted']); ?> บาท</span></div>
                        <div class="unpaid-report-row"><span>คงเหลือ</span><span><strong><?php echo number_format($item['remaining']); ?> บาท</strong></span></div>
                        <a class="btn-pay" href="payment.php?token=<?php echo urlencode($token); ?>&exp_id=<?php echo (int)$item['exp_id']; ?>">ชำระบิลนี้</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="payment-history" id="paymentHistorySection">
            <div class="section-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span> ประวัติการแจ้งชำระเงิน</div>
            
            <?php if (empty($payments)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 12H16c-.7 2-2 3-4 3s-3.3-1-4-3H2.5"/><path d="M5.5 5.1L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.4-6.9A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.8 1.1z"/></svg></div>
                <p>ยังไม่มีประวัติการชำระเงิน</p>
            </div>
            <?php else: ?>
            <?php foreach ($payments as $payment): ?>
            <?php
                $statusKeyRaw = trim((string)($payment['pay_status'] ?? ''));
                $statusKey = $statusKeyRaw === '' ? '0' : $statusKeyRaw;
                $statusInfo = $paymentStatusMap[$statusKey] ?? $defaultPaymentStatus;
                $payProofFile = trim((string)($payment['pay_proof'] ?? ''));
                $payProofUrl = $payProofFile !== '' ? $paymentProofBaseUrl . rawurlencode($payProofFile) : '';
            ?>
            <div class="payment-item payment-item-clickable"
                 role="button"
                 tabindex="0"
                 aria-label="ดูรายละเอียดการแจ้งชำระ"
                 data-pay-date="<?php echo htmlspecialchars((string)($payment['pay_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                 data-exp-month="<?php echo htmlspecialchars((string)($payment['exp_month'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                 data-pay-amount="<?php echo (int)($payment['pay_amount'] ?? 0); ?>"
                 data-pay-status="<?php echo htmlspecialchars($statusKey, ENT_QUOTES, 'UTF-8'); ?>"
                 data-pay-status-label="<?php echo htmlspecialchars($statusInfo['label'], ENT_QUOTES, 'UTF-8'); ?>"
                 data-pay-proof-url="<?php echo htmlspecialchars($payProofUrl, ENT_QUOTES, 'UTF-8'); ?>"
                 data-pay-remark="<?php echo htmlspecialchars((string)($payment['pay_remark'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="payment-header">
                    <span class="payment-date"><span class="date-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span> <?php echo $payment['pay_date'] ?? '-'; ?></span>
                    <span class="payment-status" style="background: <?php echo htmlspecialchars($statusInfo['bg']); ?>; color: <?php echo htmlspecialchars($statusInfo['color']); ?>;">
                        <?php echo htmlspecialchars($statusInfo['label']); ?>
                    </span>
                </div>
                <div class="payment-amount"><span class="amount-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></span> <?php echo number_format($payment['pay_amount'] ?? 0); ?> บาท</div>
                <div class="bill-details">บิลเดือน <?php echo thaiMonthYearLong($payment['exp_month']); ?><?php echo (!empty(trim((string)($payment['pay_remark'] ?? '')))) ? ' <span style="font-size: 0.8rem; color: #d97706; background: rgba(245, 158, 11, 0.1); padding: 0.1rem 0.4rem; border-radius: 4px; margin-left: 0.4rem;">' . htmlspecialchars(trim((string)$payment['pay_remark'])) . '</span>' : ''; ?></div>
                <div class="payment-status-detail">สถานะล่าสุด: <?php echo htmlspecialchars($statusInfo['label']); ?></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <?php
    $repairCount = 0;
    try {
        $repairStmt = $pdo->prepare("SELECT COUNT(*) FROM repair WHERE ctr_id IN (SELECT ctr_id FROM contract WHERE tnt_id = ?) AND repair_status = '0'");
        $repairStmt->execute([$contract['tnt_id']]);
        $repairCount = (int)($repairStmt->fetchColumn() ?? 0);
    } catch (Exception $e) { error_log("Exception in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }
    $homeBadgeCount = 0;
    try {
        $homeBadgeStmt = $pdo->prepare("
            SELECT 1 
            FROM contract c
            LEFT JOIN signature_logs sl ON c.ctr_id = sl.contract_id AND sl.signer_type = 'tenant'
            WHERE c.ctr_id = ? AND c.ctr_status != '1' AND sl.id IS NULL
              AND (
                  SELECT step_3_confirmed 
                  FROM tenant_workflow 
                  WHERE tnt_id = c.tnt_id 
                  ORDER BY id DESC LIMIT 1
              ) = 1
            LIMIT 1
        ");
        $homeBadgeStmt->execute([$contract['ctr_id'] ?? 0]);
        if ($homeBadgeStmt->fetchColumn()) {
            $homeBadgeCount = 1;
        }
    } catch (Exception $e) { error_log("Exception calculating home badge count in " . __FILE__ . ": " . $e->getMessage()); }

    if (function_exists('getTenantBillBadgeCount')) {
        $billCount = getTenantBillBadgeCount($pdo, $contract);
    } else {
        $billCount = 0;
    }
    ?>
    <nav class="bottom-nav">
        <div class="bottom-nav-content">
            <a href="index.php?token=<?php echo urlencode($token); ?>" class="nav-item">
                <div class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
                                หน้าหลัก<?php if ($homeBadgeCount > 0): ?><span class="nav-badge">1</span><?php endif; ?>
            </a>
            <a href="report_bills.php?token=<?php echo urlencode($token); ?>&_ts=<?php echo time(); ?>" class="nav-item">
                <div class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1z"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="12" y2="14"/></svg></div>
                บิล<?php if ($billCount > 0): ?><span class="nav-badge"><?php echo $billCount > 99 ? '99+' : $billCount; ?></span><?php endif; ?>
            </a>
            <a href="repair.php?token=<?php echo urlencode($token); ?>" class="nav-item">
                <div class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></div>
                แจ้งซ่อม<?php if ($repairCount > 0): ?><span class="nav-badge"><?php echo $repairCount > 99 ? '99+' : $repairCount; ?></span><?php endif; ?></a>
            <a href="profile.php?token=<?php echo urlencode($token); ?>" class="nav-item">
                <div class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                โปรไฟล์
            </a>
        </div>
    </nav>

    <div class="history-sheet-overlay" id="paymentHistorySheetOverlay" aria-hidden="true">
        <div class="history-sheet" role="dialog" aria-modal="true" aria-labelledby="historySheetTitle">
            <div class="history-sheet-handle"></div>
            <div class="history-sheet-header">
                <div class="history-sheet-title" id="historySheetTitle">รายละเอียดการแจ้งชำระ</div>
                <button type="button" class="history-sheet-close" id="historySheetCloseBtn" aria-label="ปิดหน้าต่างรายละเอียด">✕</button>
            </div>
            <div class="history-sheet-details">
                <div class="history-sheet-row">
                    <span class="history-sheet-label">วันที่แจ้งชำระ</span>
                    <span class="history-sheet-value" id="historySheetPayDate">-</span>
                </div>
                <div class="history-sheet-row">
                    <span class="history-sheet-label">บิลเดือน</span>
                    <span class="history-sheet-value" id="historySheetBillMonth">-</span>
                </div>
                <div class="history-sheet-row">
                    <span class="history-sheet-label">จำนวนเงิน</span>
                    <span class="history-sheet-value" id="historySheetPayAmount">-</span>
                </div>
                <div class="history-sheet-row" id="historySheetPayRemarkRow" style="display:none; color: #d97706; font-weight: 500;">
                    <span class="history-sheet-label">ประเภท</span>
                    <span class="history-sheet-value" id="historySheetPayRemark">-</span>
                </div>
            </div>
            <div class="history-sheet-status">
                <span class="payment-status" id="historySheetStatusBadge">-</span>
            </div>
            <div class="history-sheet-proof">
                <div class="history-sheet-proof-title">สลิปที่แนบไว้</div>
                <img class="history-sheet-proof-image" id="historySheetProofImage" alt="สลิปการชำระเงิน">
                <div class="history-sheet-no-proof" id="historySheetNoProof">รายการนี้ไม่มีสลิปแนบ หรือไฟล์ถูกลบออกจากระบบ</div>
                <a class="history-sheet-proof-link" id="historySheetProofLink" href="#" target="_blank" rel="noopener">เปิดรูปสลิปแบบเต็มหน้าจอ</a>
            </div>
        </div>
    </div>
    
    <script>
    function syncUnpaidUI(result) {
        if (!result.exp_id || result.remaining_amount === undefined) return;
        const expId = parseInt(result.exp_id, 10);
        const remaining = parseFloat(result.remaining_amount) || 0;
        const paidAmount = parseFloat(result.pay_amount) || 0;
        
        // 1. Update Select Dropdown
        const select = document.getElementById('exp_id');
        if (select) {
            const option = Array.from(select.options).find(opt => parseInt(opt.value, 10) === expId);
            if (option) {
                if (remaining <= 0) {
                    option.remove();
                    if (select.options.length === 0 || (select.options.length === 1 && select.options[0].value === '')) {
                        select.selectedIndex = 0;
                        const selectGroup = select.closest('.form-group');
                        if (selectGroup) {
                            selectGroup.style.display = 'none';
                        }
                    } else {
                        select.selectedIndex = 0; // reset
                    }
                } else {
                    const total = parseFloat(option.dataset.total) || 0;
                    const prevPaid = parseFloat(option.dataset.paid) || 0;
                    const newPaid = prevPaid + paidAmount;
                    option.dataset.paid = newPaid;
                    option.dataset.remaining = remaining;
                    
                    const expMonthText = result.exp_month ? new Date(result.exp_month).toLocaleDateString('th-TH', { month: 'long', year: 'numeric' }) : '';
                    option.text = `${expMonthText ? expMonthText + ' - ' : ''}ยอดรวม ${total.toLocaleString()} บาท (ส่งแล้ว ${newPaid.toLocaleString()} / คงเหลือ ${remaining.toLocaleString()})`;
                }
            }
        }
        
        // 2. Update Single Expense Info Display
        const singleInfo = document.getElementById('single-expense-info');
        if (singleInfo && parseInt(singleInfo.dataset.expid, 10) === expId) {
            if (remaining <= 0) {
                const group = document.getElementById('single-expense-group');
                if (group) group.style.display = 'none';
            } else {
                const total = parseFloat(singleInfo.dataset.total) || 0;
                const prevPaid = parseFloat(singleInfo.dataset.paid) || 0;
                const newPaid = prevPaid + paidAmount;
                singleInfo.dataset.paid = newPaid;
                singleInfo.dataset.remaining = remaining;
                const expMonthText = result.exp_month ? new Date(result.exp_month).toLocaleDateString('th-TH', { month: 'long', year: 'numeric' }) : '';
                singleInfo.innerHTML = `${expMonthText ? expMonthText + ' - ' : ''}ยอดรวม ${total.toLocaleString()} บาท (ส่งแล้ว ${newPaid.toLocaleString()} / คงเหลือ ${remaining.toLocaleString()})`;
            }
        }

        // 3. Update Report Cards
        const cards = document.querySelectorAll('.unpaid-report-card');
        cards.forEach(card => {
            const btnPay = card.querySelector('a.btn-pay');
            if (btnPay && btnPay.href.includes(`exp_id=${expId}`)) {
                if (remaining <= 0) {
                    card.remove();
                } else {
                    const rows = card.querySelectorAll('.unpaid-report-row');
                    if (rows.length >= 3) {
                        const currentSentText = rows[1].children[1].textContent.replace(/[^0-9]/g, '');
                        const newSent = (parseFloat(currentSentText) || 0) + paidAmount;
                        rows[1].children[1].textContent = `${newSent.toLocaleString()} บาท`;
                        rows[2].children[1].innerHTML = `<strong>${remaining.toLocaleString()} บาท</strong>`;
                    }
                }
            }
        });

        // Update Total Unpaid Amount
        const reportTotalElem = document.querySelector('.unpaid-report-total');
        if (reportTotalElem) {
            const currentTotalText = reportTotalElem.textContent.replace(/[^0-9]/g, '');
            let currentTotalTextNum = parseFloat(currentTotalText) || 0;
            const newTotalTextNum = Math.max(0, currentTotalTextNum - paidAmount);
            if (newTotalTextNum <= 0) {
                reportTotalElem.remove();
                const reportContainer = document.querySelector('.unpaid-report');
                if (reportContainer && !reportContainer.querySelector('.empty-state')) {
                    const html = `<div class="section-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></span> รายงานรอชำระ</div>
                <div class="empty-state" style="padding:1rem 0;color:#34d399;">ไม่มียอดค้างชำระ</div>`;
                    reportContainer.innerHTML = html;
                }
            } else {
                reportTotalElem.textContent = `ยอดค้างรวม ${newTotalTextNum.toLocaleString()} บาท`;
            }
        }
    }

    function showPaymentFormResponse(message, type) {
        const response = document.getElementById('paymentFormResponse');
        if (!response) return;

        response.className = `payment-form-response ${type === 'success' ? 'success' : 'error'}`;
        response.textContent = message;
        response.style.display = 'block';
    }

    function replacePaymentFormWithPendingCard(result) {
        const form = document.getElementById('paymentForm');
        if (!form) return;

        const formSection = form.closest('.form-section');
        if (!formSection) return;

        const billMonthText = result.exp_month ? formatBillMonth(result.exp_month) : '-';
        const expTotal = Number(result.exp_total || result.pay_amount || 0);
        const totalPaidOrPending = Number(result.submitted_amount || result.pay_amount || 0);
        const proofUrl = typeof result.pay_proof_url === 'string' ? result.pay_proof_url : '';

        formSection.innerHTML = `
            <div class="section-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg></span> แจ้งชำระเงิน</div>
            <div style="background:rgba(245,158,11,0.12);border:1px solid rgba(245,158,11,0.35);border-radius:14px;padding:1.25rem;text-align:center;">
                <div style="font-size:2rem;margin-bottom:0.5rem;">⏳</div>
                <div style="color:#fbbf24;font-weight:600;font-size:1rem;margin-bottom:0.4rem;">รออนุมัติการชำระเงิน</div>
                <div style="color:#92400e;font-size:0.88rem;font-weight:600;margin-bottom:0.75rem;">
                    บิล${billMonthText} — ยอด ${expTotal.toLocaleString()} บาท<br>
                    ส่งสลิปแล้วรวม ${totalPaidOrPending.toLocaleString()} บาท
                </div>
                ${proofUrl ? `<div style="margin: 1rem 0;"><img src="${proofUrl}" alt="หลักฐานการชำระเงิน" style="max-width: 100%; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); max-height: 300px; object-fit: contain;"></div>` : ''}
                <div style="font-size:0.82rem;color:#94a3b8;">หากมีข้อสงสัยกรุณาติดต่อผู้ดูแลหอพัก</div>
            </div>
        `;
    }

    function updateSubmitState() {
        const select = document.getElementById('exp_id');
        const singleInfo = document.getElementById('single-expense-info');
        const payAmount = document.getElementById('pay_amount');
        const payProofInput = document.getElementById('pay_proof');
        const submitBtn = document.getElementById('submitBtn');

        if (!submitBtn || !payAmount || !payProofInput) {
            return;
        }

        const selectedOption = select && select.options ? select.options[select.selectedIndex] : null;
        const hasSelectExpense = !!(selectedOption && selectedOption.value);
        const singleExpenseId = singleInfo ? parseInt(singleInfo.dataset.expid || '0', 10) : 0;
        const singleRemaining = singleInfo ? (parseFloat(singleInfo.dataset.remaining) || 0) : 0;
        const remaining = hasSelectExpense
            ? (parseFloat(selectedOption.dataset.remaining) || 0)
            : singleRemaining;
        const amount = parseFloat(payAmount.value) || 0;
        const hasProof = !!(payProofInput.files && payProofInput.files.length > 0);
        const validAmount = amount > 0 && (remaining <= 0 || amount <= remaining);
        const hasExpense = hasSelectExpense || singleExpenseId > 0;

        submitBtn.disabled = !(hasExpense && validAmount && hasProof);
        
        // Update QR code if PromptPay is available
        const qrImage = document.getElementById('promptpay-qr');
        const qrAmountText = document.getElementById('promptpay-qr-amount-text');
        if (qrImage && window.tenantPromptpayNumber) {
            if (amount > 0) {
                qrImage.src = `https://promptpay.io/${encodeURIComponent(window.tenantPromptpayNumber)}/${amount}.png`;
                qrAmountText.textContent = `(ยอด ${amount.toLocaleString()} บาท)`;
                qrAmountText.style.display = 'inline';
            } else {
                qrImage.src = `https://promptpay.io/${encodeURIComponent(window.tenantPromptpayNumber)}.png`;
                qrAmountText.style.display = 'none';
            }
        }
    }

    function updatePaymentAmount() {
        const select = document.getElementById('exp_id');
        const option = select && select.options ? select.options[select.selectedIndex] : null;
        const summary = document.getElementById('payment-summary');
        
        const payAmountGroup = document.getElementById('pay-amount-group');
        const payProofGroup = document.getElementById('pay-proof-group');
        const previewContainer = document.getElementById('preview-container');
        const payProofInput = document.getElementById('pay_proof');

        if (!option) {
            return;
        }
        
        if (option.value) {
            const total = parseFloat(option.dataset.total) || 0;
            const paid = parseFloat(option.dataset.paid) || 0;
            const remaining = parseFloat(option.dataset.remaining) || 0;
            
            document.getElementById('summary-total').textContent = total.toLocaleString() + ' บาท';
            document.getElementById('summary-paid').textContent = paid.toLocaleString() + ' บาท';
            document.getElementById('summary-remaining').textContent = remaining.toLocaleString() + ' บาท';
            
            // ตั้งค่าจำนวนเงินเป็นยอดคงเหลือ
            document.getElementById('pay_amount').value = remaining;
            document.getElementById('pay_amount').max = remaining;
            
            summary.style.display = 'block';
            payAmountGroup.style.display = 'block';
            payProofGroup.style.display = 'block';
        } else {
            summary.style.display = 'none';
            document.getElementById('pay_amount').value = '';
            document.getElementById('pay_amount').removeAttribute('max');
            payAmountGroup.style.display = 'none';
            payProofGroup.style.display = 'none';
            if (payProofInput) {
                payProofInput.value = '';
            }
            if (previewContainer) {
                previewContainer.style.display = 'none';
            }
        }

        updateSubmitState();
    }
    
    // เรียกใช้ทันทีถ้ามีการเลือกบิลมาแล้ว
    const initialExpenseSelect = document.getElementById('exp_id');
    if (initialExpenseSelect && initialExpenseSelect.value) {
        updatePaymentAmount();
    }
    
    function previewImage(input) {
        const container = document.getElementById('preview-container');
        const preview = document.getElementById('preview-image');
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                container.style.display = 'block';
            };
            reader.readAsDataURL(input.files[0]);
        }

        updateSubmitState();
    }

    document.addEventListener('DOMContentLoaded', function() {
        const expSelect = document.getElementById('exp_id');
        const payAmount = document.getElementById('pay_amount');
        const payProofInput = document.getElementById('pay_proof');

        if (expSelect) {
            expSelect.addEventListener('change', updateSubmitState);
        }
        if (payAmount) {
            payAmount.addEventListener('input', updateSubmitState);
            payAmount.addEventListener('change', updateSubmitState);
        }
        if (payProofInput) {
            payProofInput.addEventListener('change', updateSubmitState);
        }

        updateSubmitState();
    });
    
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            showCopyToast('คัดลอกแล้ว ✓');
        }).catch(() => {
            // Fallback for older browsers
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            showCopyToast('คัดลอกแล้ว ✓');
        });
    }
    
    function showCopyToast(message) {
        // Remove existing toast
        const existingToast = document.querySelector('.copy-toast');
        if (existingToast) existingToast.remove();
        
        const toast = document.createElement('div');
        toast.className = 'copy-toast';
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 2000);
    }

    function showFormAlert(message, type) {
        showPaymentFormResponse(message, type);
    }

    function formatPayDate(isoDate) {
        if (!isoDate) return '-';
        const dt = new Date(isoDate);
        if (Number.isNaN(dt.getTime())) return isoDate;
        const dd = String(dt.getDate()).padStart(2, '0');
        const mm = String(dt.getMonth() + 1).padStart(2, '0');
        const yyyy = dt.getFullYear() + 543;
        return `${dd}/${mm}/${yyyy}`;
    }

    function formatBillMonth(isoMonth) {
        if (!isoMonth) return '-';
        const dt = new Date(isoMonth);
        if (Number.isNaN(dt.getTime())) return isoMonth;
        const thaiMonths = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
        const yyyy = dt.getFullYear() + 543;
        return `${thaiMonths[dt.getMonth()]} ${yyyy}`;
    }

    const paymentStatusMap = <?php echo json_encode($paymentStatusMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const defaultPaymentStatus = { label: 'ไม่ทราบสถานะ', color: '#94a3b8', bg: 'rgba(148, 163, 184, 0.2)' };

    function inferStatusCodeFromLabel(statusLabel) {
        const label = String(statusLabel || '').trim().toLowerCase();
        if (!label) return '';
        if (label.includes('ตีกลับ') || label.includes('ปฏิเสธ') || label.includes('reject')) return '2';
        if (label.includes('อนุมัติ') || label.includes('ตรวจสอบแล้ว') || label.includes('approved')) return '1';
        if (label.includes('รอตรวจสอบ') || label.includes('pending') || label.includes('รอ')) return '0';
        return '';
    }

    function getPaymentStatusInfo(statusCode, fallbackLabel) {
        const fallback = typeof fallbackLabel === 'string' ? fallbackLabel.trim() : '';
        let key = '';
        if (statusCode !== undefined && statusCode !== null) {
            key = String(statusCode).trim();
        }
        if (!key && fallback) {
            key = inferStatusCodeFromLabel(fallback);
        }

        const mapInfo = (key && paymentStatusMap[key]) ? paymentStatusMap[key] : defaultPaymentStatus;
        const label = fallback || mapInfo.label;

        return {
            label: label,
            color: mapInfo.color,
            bg: mapInfo.bg,
            code: key,
        };
    }

    function extractLegacyText(element, selector) {
        const target = element ? element.querySelector(selector) : null;
        if (!target) return '';
        const raw = (target.textContent || '').replace(/\s+/g, ' ').trim();
        return raw;
    }

    function enhancePaymentHistoryItems() {
        const section = document.getElementById('paymentHistorySection');
        if (!section) {
            return;
        }

        section.querySelectorAll('.payment-item').forEach(function(item) {
            item.classList.add('payment-item-clickable');
            if (!item.hasAttribute('role')) item.setAttribute('role', 'button');
            if (!item.hasAttribute('tabindex')) item.setAttribute('tabindex', '0');
            if (!item.hasAttribute('aria-label')) item.setAttribute('aria-label', 'ดูรายละเอียดการแจ้งชำระ');

            if (!item.dataset.payDate) {
                const dateText = extractLegacyText(item, '.payment-date').replace(/^📅\s*/, '').trim();
                item.dataset.payDate = dateText;
            }

            if (!item.dataset.expMonth) {
                const monthText = extractLegacyText(item, '.bill-details').replace(/^บิลเดือน\s*/, '').trim();
                item.dataset.expMonth = monthText;
            }

            if (!item.dataset.payAmount) {
                const amountText = extractLegacyText(item, '.payment-amount');
                const amountNum = parseInt((amountText.match(/[\d,]+/) || ['0'])[0].replace(/,/g, ''), 10) || 0;
                item.dataset.payAmount = String(amountNum);
            }

            const statusBadge = item.querySelector('.payment-status');
            const statusLabelText = statusBadge ? (statusBadge.textContent || '').trim() : '';
            const statusInfo = getPaymentStatusInfo(item.dataset.payStatus, item.dataset.payStatusLabel || statusLabelText);

            item.dataset.payStatus = statusInfo.code || item.dataset.payStatus || '';
            item.dataset.payStatusLabel = statusInfo.label;

            if (statusBadge) {
                statusBadge.textContent = statusInfo.label;
                statusBadge.style.background = statusInfo.bg;
                statusBadge.style.color = statusInfo.color;
            }

            if (!item.dataset.payProofUrl) {
                item.dataset.payProofUrl = '';
            }
        });
    }

    function prependPaymentHistoryItem(result) {
        const section = document.getElementById('paymentHistorySection');
        if (!section) return;

        const empty = section.querySelector('.empty-state');
        if (empty) {
            empty.remove();
        }

        const item = document.createElement('div');
        item.className = 'payment-item payment-item-clickable';
        item.setAttribute('role', 'button');
        item.setAttribute('tabindex', '0');
        item.setAttribute('aria-label', 'ดูรายละเอียดการแจ้งชำระ');

        const amount = Number(result.pay_amount || 0);
        const statusInfo = getPaymentStatusInfo(result.pay_status, result.pay_status_label);
        const payDateText = formatPayDate(result.pay_date || '');
        const billMonthText = formatBillMonth(result.exp_month || '');
        const payProofUrl = typeof result.pay_proof_url === 'string' ? result.pay_proof_url : '';

        item.dataset.payDate = result.pay_date || '';
        item.dataset.expMonth = result.exp_month || '';
        item.dataset.payAmount = String(amount);
        item.dataset.payStatus = result.pay_status != null ? String(result.pay_status) : '0';
        item.dataset.payStatusLabel = statusInfo.label;
        item.dataset.payProofUrl = payProofUrl;
        item.dataset.payRemark = typeof result.pay_remark === 'string' ? result.pay_remark : '';

        item.innerHTML = `
            <div class="payment-header">
                <span class="payment-date"><span class="date-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span> ${payDateText}</span>
                <span class="payment-status" style="background: ${statusInfo.bg}; color: ${statusInfo.color};">${statusInfo.label}</span>
            </div>
            <div class="payment-amount"><span class="amount-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></span> ${amount.toLocaleString()} บาท</div>
            <div class="bill-details">บิลเดือน ${billMonthText}${item.dataset.payRemark ? ' <span style="font-size: 0.8rem; color: #d97706; background: rgba(245, 158, 11, 0.1); padding: 0.1rem 0.4rem; border-radius: 4px; margin-left: 0.4rem;">' + item.dataset.payRemark.replace(/</g, "&lt;").replace(/>/g, "&gt;") + '</span>' : ''}</div>
            <div class="payment-status-detail">สถานะล่าสุด: ${statusInfo.label}</div>
        `;

        const firstItem = section.querySelector('.payment-item');
        const sectionTitle = section.querySelector('.section-title');
        if (firstItem) {
            section.insertBefore(item, firstItem);
        } else if (sectionTitle && sectionTitle.nextSibling) {
            section.insertBefore(item, sectionTitle.nextSibling);
        } else {
            section.appendChild(item);
        }
    }

    function setHistorySheetProof(proofUrl) {
        const proofImage = document.getElementById('historySheetProofImage');
        const noProof = document.getElementById('historySheetNoProof');
        const proofLink = document.getElementById('historySheetProofLink');
        if (!proofImage || !noProof || !proofLink) return;

        if (typeof proofUrl === 'string' && proofUrl.trim() !== '') {
            const normalizedUrl = proofUrl.trim();
            proofImage.src = normalizedUrl;
            proofImage.style.display = 'block';
            noProof.style.display = 'none';
            proofLink.href = normalizedUrl;
            proofLink.style.display = 'inline-block';
        } else {
            proofImage.removeAttribute('src');
            proofImage.style.display = 'none';
            noProof.style.display = 'block';
            proofLink.removeAttribute('href');
            proofLink.style.display = 'none';
        }
    }

    function openPaymentHistorySheetFromItem(item) {
        const overlay = document.getElementById('paymentHistorySheetOverlay');
        if (!overlay || !item) return;

        const statusInfo = getPaymentStatusInfo(item.dataset.payStatus, item.dataset.payStatusLabel);
        const payDate = formatPayDate(item.dataset.payDate || '');
        const billMonth = formatBillMonth(item.dataset.expMonth || '');
        const payAmount = Number(item.dataset.payAmount || 0).toLocaleString() + ' บาท';

        const payDateEl = document.getElementById('historySheetPayDate');
        const billMonthEl = document.getElementById('historySheetBillMonth');
        const payAmountEl = document.getElementById('historySheetPayAmount');
        const statusBadge = document.getElementById('historySheetStatusBadge');
        
        const payRemark = (item.dataset.payRemark || '').trim();
        const payRemarkRowEl = document.getElementById('historySheetPayRemarkRow');
        const payRemarkEl = document.getElementById('historySheetPayRemark');

        if (payDateEl) payDateEl.textContent = payDate;
        if (billMonthEl) billMonthEl.textContent = billMonth;
        if (payAmountEl) payAmountEl.textContent = payAmount;
        
        if (payRemarkRowEl && payRemarkEl) {
            if (payRemark !== '') {
                payRemarkEl.textContent = payRemark;
                payRemarkRowEl.style.display = 'flex';
            } else {
                payRemarkRowEl.style.display = 'none';
                payRemarkEl.textContent = '-';
            }
        }
        
        if (statusBadge) {
            statusBadge.textContent = statusInfo.label;
            statusBadge.style.background = statusInfo.bg;
            statusBadge.style.color = statusInfo.color;
        }

        setHistorySheetProof(item.dataset.payProofUrl || '');

        overlay.classList.add('active');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('sheet-open');
    }

    function closePaymentHistorySheet() {
        const overlay = document.getElementById('paymentHistorySheetOverlay');
        if (!overlay) return;
        overlay.classList.remove('active');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('sheet-open');
    }

    function bindHistorySheetHandleDragClose() {
        const overlay = document.getElementById('paymentHistorySheetOverlay');
        if (!overlay) {
            return;
        }

        const sheet = overlay.querySelector('.history-sheet');
        const handle = overlay.querySelector('.history-sheet-handle');
        if (!sheet || !handle || handle.dataset.dragCloseBound === '1') {
            return;
        }

        handle.dataset.dragCloseBound = '1';

        let startY = 0;
        let deltaY = 0;
        let dragging = false;

        function closeThreshold() {
            const height = sheet.getBoundingClientRect().height || sheet.offsetHeight || 0;
            if (height > 0) {
                return Math.round(height * 0.5);
            }
            return 72;
        }

        function beginDrag(clientY) {
            if (!overlay.classList.contains('active')) {
                return false;
            }

            startY = clientY;
            deltaY = 0;
            dragging = true;
            sheet.style.transition = 'none';
            sheet.style.willChange = 'transform';
            return true;
        }

        function updateDrag(clientY) {
            if (!dragging) {
                return;
            }
            deltaY = Math.max(0, clientY - startY);
            sheet.style.transform = `translateY(${deltaY}px)`;
        }

        function finishDrag() {
            if (!dragging) {
                return;
            }

            const shouldClose = deltaY >= closeThreshold();
            dragging = false;
            sheet.style.willChange = '';
            sheet.style.transition = '';
            sheet.style.transform = '';

            if (shouldClose) {
                closePaymentHistorySheet();
            }
        }

        if (window.PointerEvent) {
            handle.addEventListener('pointerdown', function(event) {
                if (event.button !== 0) {
                    return;
                }

                if (!beginDrag(event.clientY)) {
                    return;
                }

                event.preventDefault();
                try {
                    handle.setPointerCapture(event.pointerId);
                } catch (captureError) {}
            });

            handle.addEventListener('pointermove', function(event) {
                updateDrag(event.clientY);
            });

            handle.addEventListener('pointerup', function() {
                finishDrag();
            });

            handle.addEventListener('pointercancel', function() {
                finishDrag();
            });
            return;
        }

        const onMouseMove = function(event) {
            updateDrag(event.clientY);
        };
        const onMouseUp = function() {
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
            finishDrag();
        };

        handle.addEventListener('mousedown', function(event) {
            if (event.button !== 0) {
                return;
            }

            if (!beginDrag(event.clientY)) {
                return;
            }

            event.preventDefault();
            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        });

        handle.addEventListener('touchstart', function(event) {
            if (!event.touches || !event.touches.length) {
                return;
            }

            if (!beginDrag(event.touches[0].clientY)) {
                return;
            }

            event.preventDefault();
        }, { passive: false });

        handle.addEventListener('touchmove', function(event) {
            if (!event.touches || !event.touches.length) {
                return;
            }

            event.preventDefault();
            updateDrag(event.touches[0].clientY);
        }, { passive: false });

        handle.addEventListener('touchend', function() {
            finishDrag();
        });

        handle.addEventListener('touchcancel', function() {
            finishDrag();
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const historySection = document.getElementById('paymentHistorySection');
        const historyOverlay = document.getElementById('paymentHistorySheetOverlay');
        const closeBtn = document.getElementById('historySheetCloseBtn');

        if (!historySection || !historyOverlay) {
            return;
        }

        enhancePaymentHistoryItems();
    bindHistorySheetHandleDragClose();

        historySection.addEventListener('click', function(e) {
            const item = e.target.closest('.payment-item-clickable, .payment-item');
            if (!item || !historySection.contains(item)) {
                return;
            }
            openPaymentHistorySheetFromItem(item);
        });

        historySection.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter' && e.key !== ' ') {
                return;
            }
            const item = e.target.closest('.payment-item-clickable, .payment-item');
            if (!item || !historySection.contains(item)) {
                return;
            }
            e.preventDefault();
            openPaymentHistorySheetFromItem(item);
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', closePaymentHistorySheet);
        }

        historyOverlay.addEventListener('click', function(e) {
            if (e.target === historyOverlay) {
                closePaymentHistorySheet();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && historyOverlay.classList.contains('active')) {
                closePaymentHistorySheet();
            }
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        updateSubmitState();
    });

    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('paymentForm');
        const submitBtn = document.getElementById('submitBtn');

        if (!form || !submitBtn) {
            return;
        }

        form.addEventListener('submit', async function(event) {
            event.preventDefault();
            updateSubmitState();

            if (submitBtn.disabled) {
                return;
            }

            const originalContent = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="btn-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg></span> กำลังส่ง...';

            try {
                const formData = new FormData(form);
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                const rawText = await response.text();
                let result = null;

                try {
                    const jsonStart = rawText.indexOf('{');
                    const jsonText = jsonStart >= 0 ? rawText.slice(jsonStart) : rawText;
                    result = JSON.parse(jsonText);
                } catch (parseError) {
                    throw new Error('ไม่สามารถอ่านผลลัพธ์จากเซิร์ฟเวอร์ได้');
                }

                if (response.ok && result && result.success) {
                    showFormAlert(result.message || 'แจ้งชำระเงินเรียบร้อยแล้ว', 'success');
                    prependPaymentHistoryItem(result);
                    syncUnpaidUI(result);
                    const hasUnpaidCards = !!document.querySelector('.unpaid-report-card');
                    if (!hasUnpaidCards) {
                        replacePaymentFormWithPendingCard(result);
                    } else {
                        form.reset();
                        if (typeof updatePaymentAmount === 'function') {
                            updatePaymentAmount();
                        }
                        const previewContainer = document.getElementById('preview-container');
                        const previewImage = document.getElementById('preview-image');
                        if (previewContainer) {
                            previewContainer.style.display = 'none';
                        }
                        if (previewImage) {
                            previewImage.src = '';
                        }
                    }
                } else {
                    throw new Error((result && result.message) ? result.message : 'ไม่สามารถบันทึกข้อมูลได้');
                }
            } catch (error) {
                console.error('Payment submit error:', error);
                showFormAlert(error.message || 'ไม่สามารถส่งข้อมูลได้ กรุณาลองใหม่', 'error');
            } finally {
                submitBtn.innerHTML = originalContent;
                updateSubmitState();
            }
        });
    });
    </script>
</body>
</html>
