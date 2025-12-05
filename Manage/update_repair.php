<?php
// อัปเดตรายการแจ้งซ่อม
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
    $ctr_id = isset($_POST['ctr_id']) ? (int)$_POST['ctr_id'] : 0;
    $repair_date = $_POST['repair_date'] ?? '';
    $repair_desc = trim($_POST['repair_desc'] ?? '');
    $repair_status = $_POST['repair_status'] ?? '0';

    if ($repair_id <= 0 || $ctr_id <= 0 || $repair_date === '' || $repair_desc === '') {
        $_SESSION['error'] = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        header('Location: ../Reports/manage_repairs.php');
        exit;
    }

    $existsStmt = $pdo->prepare('SELECT repair_id FROM repair WHERE repair_id = ?');
    $existsStmt->execute([$repair_id]);
    if (!$existsStmt->fetchColumn()) {
        $_SESSION['error'] = 'ไม่พบรายการแจ้งซ่อม';
        header('Location: ../Reports/manage_repairs.php');
        exit;
    }

    $ctrStmt = $pdo->prepare('SELECT ctr_id FROM contract WHERE ctr_id = ?');
    $ctrStmt->execute([$ctr_id]);
    if (!$ctrStmt->fetchColumn()) {
        $_SESSION['error'] = 'ไม่พบสัญญา';
        header('Location: ../Reports/manage_repairs.php');
        exit;
    }

    $status = in_array($repair_status, ['0','1','2'], true) ? $repair_status : '0';

    $update = $pdo->prepare('UPDATE repair SET ctr_id = ?, repair_date = ?, repair_desc = ?, repair_status = ? WHERE repair_id = ?');
    $update->execute([$ctr_id, $repair_date, $repair_desc, $status, $repair_id]);

    $_SESSION['success'] = 'อัปเดตข้อมูลแจ้งซ่อมเรียบร้อยแล้ว';
    header('Location: ../Reports/manage_repairs.php');
    exit;
} catch (PDOException $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    header('Location: ../Reports/manage_repairs.php');
    exit;
}
