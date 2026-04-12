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

    if ($expTotal > 0 && $approvedAmount >= ($expTotal - 0.00001)) {
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
$payId = $_POST['pay_id'] ?? '';
$payStatus = (string)($_POST['pay_status'] ?? '');

// ตรวจสอบข้อมูลที่จำเป็น
if (empty($payId)) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบรหัสการชำระเงิน']);
    exit;
}

if (!in_array($payStatus, ['0', '1', '2'], true)) {
    echo json_encode(['success' => false, 'error' => 'สถานะไม่ถูกต้อง']);
    exit;
}

// รับ exp_id จาก POST (ถ้ามี)
$expId = $_POST['exp_id'] ?? '';

try {
    // ตรวจสอบว่ามีรายการชำระเงินนี้อยู่จริง
    $checkStmt = $pdo->prepare("SELECT pay_id, exp_id FROM payment WHERE pay_id = ?");
    $checkStmt->execute([$payId]);
    $payment = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบรายการชำระเงินนี้']);
        exit;
    }

    // ใช้ exp_id จาก payment ถ้าไม่ได้ส่งมา
    if (empty($expId) && !empty($payment['exp_id'])) {
        $expId = $payment['exp_id'];
    }

    // อัปเดตสถานะ payment
    $updateStmt = $pdo->prepare("UPDATE payment SET pay_status = ? WHERE pay_id = ?");
    $updateStmt->execute([$payStatus, $payId]);

    if (!empty($expId)) {
        recalculateExpenseStatus($pdo, (int)$expId);
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
