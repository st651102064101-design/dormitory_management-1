<?php
declare(strict_types=1);
session_start();

// ตรวจสอบสิทธิ์การเข้าถึง
if (empty($_SESSION['admin_username'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

header('Content-Type: application/json');

function hasPayRemarkColumn(PDO $pdo): bool
{
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM payment LIKE 'pay_remark'");
        $cached = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $cached = false;
    }

    return $cached;
}

function recalculateExpenseStatus(PDO $pdo, int $expId): void
{
    if ($expId <= 0) {
        return;
    }

    $expenseStmt = $pdo->prepare("SELECT exp_total, exp_status FROM expense WHERE exp_id = ? LIMIT 1");
    $expenseStmt->execute([$expId]);
    $expense = $expenseStmt->fetch(PDO::FETCH_ASSOC);
    if (!$expense) {
        return;
    }

    $expTotal = (float)($expense['exp_total'] ?? 0);
    $existingStatus = (string)($expense['exp_status'] ?? '0');
    $hasPayRemark = hasPayRemarkColumn($pdo);

    if (true) {
        $approvedSql = "SELECT COALESCE(SUM(pay_amount), 0) FROM payment WHERE exp_id = ? AND pay_status = '1'";
        $pendingSql = "SELECT COUNT(*) FROM payment WHERE exp_id = ? AND pay_status = '0'";
    } else {
        $approvedSql = "SELECT COALESCE(SUM(pay_amount), 0) FROM payment WHERE exp_id = ? AND pay_status = '1'";
        $pendingSql = "SELECT COUNT(*) FROM payment WHERE exp_id = ? AND pay_status = '0'";
    }

    $approvedStmt = $pdo->prepare($approvedSql);
    $approvedStmt->execute([$expId]);
    $approvedAmount = (float)($approvedStmt->fetchColumn() ?: 0);

    $pendingStmt = $pdo->prepare($pendingSql);
    $pendingStmt->execute([$expId]);
    $pendingCount = (int)($pendingStmt->fetchColumn() ?: 0);

    // ตรวจสอบว่ามี payment ที่ถูกตีกลับหรือไม่
    $rejectedSql = "SELECT COUNT(*) FROM payment WHERE exp_id = ? AND pay_status = '2'";
    $rejectedStmt = $pdo->prepare($rejectedSql);
    $rejectedStmt->execute([$expId]);
    $rejectedCount = (int)($rejectedStmt->fetchColumn() ?: 0);

    // ถ้ามี payment ที่ถูกตีกลับ ให้ status เป็น 4 (ตีกลับ)
    if ($rejectedCount > 0) {
        $nextStatus = '4';
    } elseif ($expTotal > 0 && $approvedAmount >= ($expTotal - 0.00001)) {
        $nextStatus = '1';
    } elseif ($approvedAmount > 0) {
        $nextStatus = '3';
    } elseif ($pendingCount > 0) {
        $nextStatus = '2';
    } elseif ($existingStatus === '4') {
        $nextStatus = '4';
    } else {
        $nextStatus = '0';
    }

    $updateExpStmt = $pdo->prepare("UPDATE expense SET exp_status = ? WHERE exp_id = ?");
    $updateExpStmt->execute([$nextStatus, $expId]);
}

// CSRF validation
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'CSRF token ไม่ถูกต้อง']);
    exit;
}

// รับข้อมูลจาก POST
$payId = trim((string)($_POST['pay_id'] ?? ''));
$payStatus = (string)($_POST['pay_status'] ?? '');

// ตรวจสอบข้อมูลที่จำเป็น
if (empty($payId) || !ctype_digit($payId)) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบรหัสการชำระเงิน หรือรูปแบบไม่ถูกต้อง']);
    exit;
}

$payId = (int)$payId;

if (!in_array($payStatus, ['0', '1', '2'], true)) {
    echo json_encode(['success' => false, 'error' => 'สถานะไม่ถูกต้อง']);
    exit;
}

// รับ exp_id จาก POST (ถ้ามี)
$expId = trim((string)($_POST['exp_id'] ?? ''));
if (!empty($expId) && !ctype_digit($expId)) {
    echo json_encode(['success' => false, 'error' => 'รหัสค่าใช้จ่ายไม่ถูกต้อง']);
    exit;
}
$expId = !empty($expId) ? (int)$expId : 0;

try {
    // ตรวจสอบว่ามีรายการชำระเงินนี้อยู่จริง
    $checkStmt = $pdo->prepare("SELECT pay_id, exp_id FROM payment WHERE pay_id = ? LIMIT 1");
    $checkStmt->execute([$payId]);
    $payment = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        error_log("[update_payment_status] Payment not found: pay_id={$payId}");
        echo json_encode(['success' => false, 'error' => 'ไม่พบรายการชำระเงินนี้']);
        exit;
    }

    // ใช้ exp_id จาก payment ถ้าไม่ได้ส่งมา
    if ($expId === 0 && !empty($payment['exp_id'])) {
        $expId = (int)$payment['exp_id'];
    }

    // ตรวจสอบสถานะปัจจุบัน
    $currentStatusStmt = $pdo->prepare("SELECT pay_status FROM payment WHERE pay_id = ? LIMIT 1");
    $currentStatusStmt->execute([$payId]);
    $currentPayment = $currentStatusStmt->fetch(PDO::FETCH_ASSOC);
    $currentStatus = (string)($currentPayment['pay_status'] ?? '');

    // อัปเดตสถานะ payment
    $updateStmt = $pdo->prepare("UPDATE payment SET pay_status = ? WHERE pay_id = ?");
    $updateResult = $updateStmt->execute([$payStatus, $payId]);
    
    if (!$updateResult) {
        error_log("[update_payment_status] Update failed for pay_id={$payId}");
        echo json_encode(['success' => false, 'error' => 'ไม่สามารถอัปเดตสถานะได้']);
        exit;
    }

    // ตรวจสอบว่า update สำเร็จ
    $verifyStmt = $pdo->prepare("SELECT pay_status FROM payment WHERE pay_id = ? LIMIT 1");
    $verifyStmt->execute([$payId]);
    $verified = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    $verifiedStatus = (string)($verified['pay_status'] ?? '');
    
    error_log("[update_payment_status] Updated pay_id={$payId}: {$currentStatus} -> {$payStatus} (verified: {$verifiedStatus})");

    if ($expId > 0) {
        recalculateExpenseStatus($pdo, $expId);
    }

    $statusTextMap = [
        '0' => 'รอตรวจสอบ',
        '1' => 'ตรวจสอบแล้ว',
        '2' => 'ตีกลับแล้ว',
    ];

    require_once __DIR__ . '/../LineHelper.php';
    if (in_array($payStatus, ['1', '2'], true) && function_exists('sendLineToTenant')) {
        try {
            // ดึงข้อมูลห้องและบิล
            // ดึง room_id และเลขห้องก่อน
            // payment -> expense -> contract -> room
            $stmtInfo = $pdo->prepare("
                SELECT r.room_number, e.exp_month, e.exp_total, c.tnt_id, t.tnt_name, p.pay_amount
                FROM payment p
                JOIN expense e ON p.exp_id = e.exp_id
                JOIN contract c ON e.ctr_id = c.ctr_id
                JOIN room r ON c.room_id = r.room_id
                JOIN tenant t ON c.tnt_id = t.tnt_id
                WHERE p.pay_id = ?
            ");
            $stmtInfo->execute([$payId]);
            $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);

            if ($info) {
                $roomName = $info['room_number'] ?? 'ไม่ทราบห้อง';
                $tenantName = $info['tnt_name'] ?? 'ผู้เช่า';
                $payAmount = (float)($info['pay_amount'] ?? 0);
                $monthStr = $info['exp_month'] ? date('m/Y', strtotime((string)$info['exp_month'])) : '';


                if ($payStatus === '1') {
                    $msg = "✅ อนุมัติการชำระเงินเรียบร้อย\n";
                    $msg .= "------------------------\n";
                    $msg .= "ผู้เช่า: {$tenantName}\n";
                    $msg .= "ห้อง: {$roomName}\n";
                    if ($monthStr) {
                        $msg .= "บิลประจำเดือน: {$monthStr}\n";
                    }
                    $msg .= "ยอดที่อนุมัติ: ฿" . number_format($payAmount, 2) . "\n";
                    $msg .= "------------------------\n";
                    $msg .= "ขอบคุณที่ใช้บริการ Sangthian Dormitory 😊\n";
                } else {
                    // แจ้งเฉพาะรายการจ่ายที่ถูกตีกลับ (pay_id เดียว = ห้องเดียว)
                    $msg = "❌ รายการชำระถูกตีกลับ\n";
                    $msg .= "------------------------\n";
                    $msg .= "ผู้เช่า: {$tenantName}\n";
                    $msg .= "ห้องที่ตีกลับ: {$roomName}\n";
                    if ($monthStr) {
                        $msg .= "บิลประจำเดือน: {$monthStr}\n";
                    }
                    $msg .= "ยอดที่ตีกลับ: ฿" . number_format($payAmount, 2) . "\n";
                    $msg .= "------------------------\n";
                    $msg .= "กรุณาตรวจสอบรายการและแนบสลิปใหม่อีกครั้ง";
                }

                sendLineToTenant($pdo, (string)($info['tnt_id'] ?? ''), $msg);
            }
        } catch (Exception $e) {
            error_log("Line Notification Error (Payment Status Update): " . $e->getMessage());
        }
    }

    $statusText = $statusTextMap[$payStatus] ?? 'อัปเดตแล้ว';
    echo json_encode([
        'success' => true,
        'message' => 'อัปเดตสถานะเป็น "' . $statusText . '" สำเร็จ'
    ]);
    exit;

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    exit;
}
?>
