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

    $exp_id = isset($_POST['exp_id']) ? (int)$_POST['exp_id'] : 0;
    $pay_date = $_POST['pay_date'] ?? '';
    $pay_amount = isset($_POST['pay_amount']) ? (int)$_POST['pay_amount'] : 0;

    if ($exp_id <= 0 || $pay_date === '' || $pay_amount <= 0) {
        die(json_encode(['success' => false, 'error' => 'กรุณากรอกข้อมูลให้ครบถ้วน']));
    }

    // ตรวจสอบว่า expense มีอยู่จริง
    $expStmt = $pdo->prepare("SELECT exp_id, exp_total, exp_status FROM expense WHERE exp_id = ?");
    $expStmt->execute([$exp_id]);
    $expense = $expStmt->fetch(PDO::FETCH_ASSOC);

    if (!$expense) {
        die(json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลค่าใช้จ่าย']));
    }

    // จัดการอัปโหลดหลักฐาน
    $pay_proof = null;
    if (empty($_FILES['pay_proof']['name'])) {
        die(json_encode(['success' => false, 'error' => 'กรุณาแนบหลักฐานการชำระ']));
    }
    if (!empty($_FILES['pay_proof']['name'])) {
        $uploadError = (int)($_FILES['pay_proof']['error'] ?? 0);
        if ($uploadError !== UPLOAD_ERR_OK) {
            $errorMap = [
                UPLOAD_ERR_INI_SIZE   => 'ไฟล์ใหญ่เกินกำหนด (php.ini)',
                UPLOAD_ERR_FORM_SIZE  => 'ไฟล์ใหญ่เกินกำหนด (ฟอร์ม)',
                UPLOAD_ERR_PARTIAL    => 'อัปโหลดไฟล์ไม่สมบูรณ์',
                UPLOAD_ERR_NO_FILE    => 'ไม่พบไฟล์ที่อัปโหลด',
                UPLOAD_ERR_NO_TMP_DIR => 'เซิร์ฟเวอร์ไม่มีโฟลเดอร์ชั่วคราว',
                UPLOAD_ERR_CANT_WRITE => 'ไม่สามารถเขียนไฟล์ลงดิสก์',
                UPLOAD_ERR_EXTENSION  => 'อัปโหลดถูกบล็อกโดยส่วนขยาย PHP'
            ];
            $msg = $errorMap[$uploadError] ?? ('อัปโหลดผิดพลาด (code ' . $uploadError . ')');
            die(json_encode(['success' => false, 'error' => $msg]));
        }

        $uploadDir = __DIR__ . '/..//Assets/Images/Payments/';

        // สร้างโฟลเดอร์ถ้ายังไม่มี
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        // เช็คสิทธิ์เขียน
        if (!is_writable($uploadDir)) {
            @chmod($uploadDir, 0755);
        }
        if (!is_writable($uploadDir)) {
            die(json_encode(['success' => false, 'error' => 'โฟลเดอร์จัดเก็บหลักฐานไม่สามารถเขียนได้: ' . $uploadDir]));
        }

        $fileExt = strtolower(pathinfo($_FILES['pay_proof']['name'], PATHINFO_EXTENSION));
        $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];

        if (!in_array($fileExt, $allowedExt, true)) {
            die(json_encode(['success' => false, 'error' => 'ประเภทไฟล์ไม่รองรับ (รองรับ: jpg, png, gif, webp, pdf)']));
        }

        // สร้างชื่อไฟล์ใหม่
        $newFilename = 'payment_' . time() . '_' . uniqid('', true) . '.' . $fileExt;
        $targetPath = $uploadDir . $newFilename;

        if (move_uploaded_file($_FILES['pay_proof']['tmp_name'], $targetPath)) {
            $pay_proof = $newFilename;
        } else {
            die(json_encode(['success' => false, 'error' => 'ไม่สามารถอัปโหลดไฟล์ได้ (ตรวจสอบสิทธิ์โฟลเดอร์หรือขนาดไฟล์)']));
        }
    }

    // บันทึกข้อมูลการชำระเงิน (สถานะ 0 = รอตรวจสอบ)
    $insert = $pdo->prepare("
        INSERT INTO payment (pay_date, pay_amount, pay_proof, pay_status, exp_id)
        VALUES (?, ?, ?, '0', ?)
    ");
    $insert->execute([
        $pay_date,
        $pay_amount,
        $pay_proof,
        $exp_id
    ]);

    // อัพเดทสถานะของ expense เป็น '2' (รอตรวจสอบ)
    $updateExpense = $pdo->prepare("UPDATE expense SET exp_status = '2' WHERE exp_id = ?");
    $updateExpense->execute([$exp_id]);

    echo json_encode([
        'success' => true,
        'message' => 'บันทึกการชำระเงินเรียบร้อยแล้ว (฿' . number_format($pay_amount) . ')'
    ]);
    exit;
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Process Payment Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดในฐานข้อมูล: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    error_log('Process Payment Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    exit;
}
?>
