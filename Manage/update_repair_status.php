<?php
// อัปเดตสถานะการแจ้งซ่อม (ฝั่งแอดมิน: ตั้งเป็นกำลังซ่อม หรือ ซ่อมเสร็จแล้ว)
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../Reports/manage_repairs.php');
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

try {
    $pdo = connectDB();

    $repair_id = isset($_POST['repair_id']) ? (int)$_POST['repair_id'] : 0;
    $repair_status = isset($_POST['repair_status']) ? (string)$_POST['repair_status'] : '';

    // ตรวจสอบข้อมูล
    if ($repair_id <= 0 || empty($repair_status)) {
        $msg = 'ข้อมูลไม่ถูกต้อง';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $msg]);
            exit;
        }
        $_SESSION['error'] = $msg;
        header('Location: ../Reports/manage_repairs.php');
        exit;
    }

    // ตรวจสอบว่าสถานะใหม่เป็นค่าที่ถูกต้อง (1 = กำลังซ่อม, 2 = ซ่อมเสร็จแล้ว, 3 = ยกเลิก)
    if ($repair_status !== '1' && $repair_status !== '2' && $repair_status !== '3') {
        $msg = 'สถานะไม่ถูกต้อง';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $msg]);
            exit;
        }
        $_SESSION['error'] = $msg;
        header('Location: ../Reports/manage_repairs.php');
        exit;
    }

    // ตรวจสอบว่ารายการแจ้งซ่อมนี้มีอยู่หรือไม่
    $existsStmt = $pdo->prepare('SELECT repair_status FROM repair WHERE repair_id = ?');
    $existsStmt->execute([$repair_id]);
    $current = $existsStmt->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
        $msg = 'ไม่พบรายการแจ้งซ่อม';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $msg]);
            exit;
        }
        $_SESSION['error'] = $msg;
        header('Location: ../Reports/manage_repairs.php');
        exit;
    }

    // อัปเดตสถานะ
    $update = $pdo->prepare('UPDATE repair SET repair_status = ? WHERE repair_id = ?');
    $update->execute([$repair_status, $repair_id]);

    // ข้อความสำเร็จตามสถานะ
    $statusMessages = [
        '1' => 'อัปเดตสถานะเป็น "ทำการซ่อม" แล้ว',
        '2' => 'อัปเดตสถานะเป็น "ซ่อมเสร็จแล้ว" แล้ว',
        '3' => 'ยกเลิกการแจ้งซ่อมแล้ว',
    ];
    $message = $statusMessages[$repair_status] ?? 'อัปเดตสถานะเรียบร้อย';

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => $message]);
        exit;
    }

    $_SESSION['success'] = $message;
    header('Location: ../Reports/manage_repairs.php');
    exit;
} catch (PDOException $e) {
    $msg = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $msg]);
        exit;
    }
    $_SESSION['error'] = $msg;
    header('Location: ../Reports/manage_repairs.php');
    exit;
}
