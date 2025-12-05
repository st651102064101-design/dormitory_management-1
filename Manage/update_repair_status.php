<?php
// อัปเดตสถานะการแจ้งซ่อม (ฝั่งแอดมิน: ตั้งเป็นกำลังซ่อม)
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

try {
    $pdo = connectDB();

    $repair_id = isset($_POST['repair_id']) ? (int)$_POST['repair_id'] : 0;
    $repair_status = $_POST['repair_status'] ?? '';

    if ($repair_id <= 0 || $repair_status !== '1') {
        $_SESSION['error'] = 'ข้อมูลไม่ถูกต้อง';
        header('Location: ../Reports/manage_repairs.php');
        exit;
    }

    $existsStmt = $pdo->prepare('SELECT repair_status FROM repair WHERE repair_id = ?');
    $existsStmt->execute([$repair_id]);
    $current = $existsStmt->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
        $_SESSION['error'] = 'ไม่พบรายการแจ้งซ่อม';
        header('Location: ../Reports/manage_repairs.php');
        exit;
    }

    // อนุญาตเปลี่ยนจากสถานะใดก็ได้เป็นกำลังซ่อม ตามคำขอ
    $update = $pdo->prepare('UPDATE repair SET repair_status = ? WHERE repair_id = ?');
    $update->execute(['1', $repair_id]);

    $_SESSION['success'] = 'อัปเดตสถานะเป็น "ทำการซ่อม" แล้ว';
    header('Location: ../Reports/manage_repairs.php');
    exit;
} catch (PDOException $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    header('Location: ../Reports/manage_repairs.php');
    exit;
}
