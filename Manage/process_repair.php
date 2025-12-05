<?php
// เพิ่มรายการแจ้งซ่อม
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

    $ctr_id = isset($_POST['ctr_id']) ? (int)$_POST['ctr_id'] : 0;
    $repair_date = $_POST['repair_date'] ?? '';
    $repair_desc = trim($_POST['repair_desc'] ?? '');

    if ($ctr_id <= 0 || $repair_date === '' || $repair_desc === '') {
        $_SESSION['error'] = 'กรุณากรอกข้อมูลให้ครบถ้วน';
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

    $insert = $pdo->prepare('INSERT INTO repair (ctr_id, repair_date, repair_desc, repair_status) VALUES (?, ?, ?, ?)');
    $insert->execute([$ctr_id, $repair_date, $repair_desc, '0']);

    $_SESSION['success'] = 'บันทึกการแจ้งซ่อมเรียบร้อยแล้ว';
    header('Location: ../Reports/manage_repairs.php');
    exit;
} catch (PDOException $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    header('Location: ../Reports/manage_repairs.php');
    exit;
}
