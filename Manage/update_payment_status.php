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

    if ($hasPayRemark) {
        $approvedSql = "SELECT COALESCE(SUM(pay_amount), 0) FROM payment WHERE exp_id = ? AND pay_status = '1' AND (pay_remark IS NULL OR pay_remark != 'มัดจำ')";
        $pendingSql = "SELECT COUNT(*) FROM payment WHERE exp_id = ? AND pay_status = '0' AND (pay_remark IS NULL OR pay_remark != 'มัดจำ')";
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
