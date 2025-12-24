<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['admin_username'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'ไม่ได้รับอนุญาต']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'error' => 'Method not allowed']));
}

require_once __DIR__ . '/../ConnectDB.php';

try {
    $pdo = connectDB();

    $pay_id = isset($_POST['pay_id']) ? (int)$_POST['pay_id'] : 0;

    if ($pay_id <= 0) {
        die(json_encode(['success' => false, 'error' => 'ไม่พบรหัสการชำระเงิน (pay_id ไม่ถูกต้อง)']));
    }

    // ดึงข้อมูล payment เพื่อลบไฟล์หลักฐาน
    $payStmt = $pdo->prepare("SELECT pay_proof, exp_id, pay_status FROM payment WHERE pay_id = ?");
    $payStmt->execute([$pay_id]);
    $payment = $payStmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        die(json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลการชำระเงิน']));
    }

    // ลบไฟล์หลักฐานถ้ามี (ไม่บล็อกการลบ record แม้ไฟล์ลบไม่สำเร็จ)
    if (!empty($payment['pay_proof'])) {
        $filePath = __DIR__ . '/../Assets/Images/Payments/' . $payment['pay_proof'];
        if (file_exists($filePath)) {
            if (!is_writable($filePath)) {
                @chmod($filePath, 0644);
            }
            if (!@unlink($filePath)) {
                error_log('Warning: cannot delete payment proof file: ' . $filePath);
            }
        }
    }

    // ถ้าการชำระนี้ถูกยืนยันแล้ว ให้เปลี่ยนสถานะ expense กลับเป็นยังไม่ชำระ
    if ($payment['pay_status'] === '1' && !empty($payment['exp_id'])) {
        // ตรวจสอบว่ามีการชำระอื่นที่ยืนยันแล้วหรือไม่
        $otherPayments = $pdo->prepare("SELECT COUNT(*) FROM payment WHERE exp_id = ? AND pay_id != ? AND pay_status = '1'");
        $otherPayments->execute([$payment['exp_id'], $pay_id]);
        if ((int)$otherPayments->fetchColumn() === 0) {
            // ไม่มีการชำระอื่นที่ยืนยัน -> เปลี่ยน expense status กลับเป็น 0
            $updateExp = $pdo->prepare("UPDATE expense SET exp_status = '0' WHERE exp_id = ?");
            $updateExp->execute([$payment['exp_id']]);
        }
    }

    // ลบข้อมูล
    $delete = $pdo->prepare("DELETE FROM payment WHERE pay_id = ?");
    $delete->execute([$pay_id]);

    if ($delete->rowCount() < 1) {
        echo json_encode(['success' => false, 'error' => 'ลบไม่สำเร็จ: ไม่พบรายการนี้']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'ลบรายการชำระเงินเรียบร้อยแล้ว'
    ]);
    exit;
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Delete Payment Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดในฐานข้อมูล: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    error_log('Delete Payment Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    exit;
}
?>
