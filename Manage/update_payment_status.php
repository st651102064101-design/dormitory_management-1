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

// รับข้อมูลจาก POST
$payId = $_POST['pay_id'] ?? '';
$payStatus = $_POST['pay_status'] ?? '';

// ตรวจสอบข้อมูลที่จำเป็น
if (empty($payId)) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบรหัสการชำระเงิน']);
    exit;
}

if (!in_array($payStatus, ['0', '1'], true)) {
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

    // ถ้ายืนยันการชำระ (status = 1) และมี exp_id ให้ตรวจสอบยอดชำระ
    if ($payStatus === '1' && !empty($expId)) {
        // ดึงยอดรวมของ expense
        $expStmt = $pdo->prepare("SELECT exp_total FROM expense WHERE exp_id = ?");
        $expStmt->execute([$expId]);
        $expense = $expStmt->fetch(PDO::FETCH_ASSOC);
        $expTotal = (int)($expense['exp_total'] ?? 0);
        
        // คำนวณยอดที่ชำระแล้วทั้งหมด (เฉพาะที่ยืนยันแล้ว pay_status='1')
        $paidStmt = $pdo->prepare("SELECT SUM(pay_amount) as total_paid FROM payment WHERE exp_id = ? AND pay_status = '1'");
        $paidStmt->execute([$expId]);
        $paidResult = $paidStmt->fetch(PDO::FETCH_ASSOC);
        $totalPaid = (int)($paidResult['total_paid'] ?? 0);
        
        // ตรวจสอบว่าชำระครบหรือไม่
        if ($totalPaid >= $expTotal) {
            // ชำระครบแล้ว -> exp_status = '1' (ชำระแล้ว)
            $updateExpStmt = $pdo->prepare("UPDATE expense SET exp_status = '1' WHERE exp_id = ?");
            $updateExpStmt->execute([$expId]);
        } else {
            // ชำระยังไม่ครบ -> exp_status = '3' (ชำระยังไม่ครบ)
            $updateExpStmt = $pdo->prepare("UPDATE expense SET exp_status = '3' WHERE exp_id = ?");
            $updateExpStmt->execute([$expId]);
        }
    }
    
    // ถ้ายกเลิกการยืนยัน (status = 0) และมี exp_id ให้ตรวจสอบว่ามีการชำระอื่นที่ยืนยันแล้วหรือไม่
    if ($payStatus === '0' && !empty($expId)) {
        $otherPayments = $pdo->prepare("SELECT COUNT(*) FROM payment WHERE exp_id = ? AND pay_id != ? AND pay_status = '1'");
        $otherPayments->execute([$expId, $payId]);
        if ((int)$otherPayments->fetchColumn() === 0) {
            // ไม่มีการชำระอื่นที่ยืนยัน -> เปลี่ยน expense status กลับเป็น '2' (รอตรวจสอบ)
            $updateExpStmt = $pdo->prepare("UPDATE expense SET exp_status = '2' WHERE exp_id = ?");
            $updateExpStmt->execute([$expId]);
        }
    }

    $statusText = $payStatus === '1' ? 'ตรวจสอบแล้ว' : 'รอตรวจสอบ';
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
