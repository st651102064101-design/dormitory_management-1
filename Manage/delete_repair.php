<?php
// ลบรายการแจ้งซ่อม
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

    if ($repair_id <= 0) {
        $_SESSION['error'] = 'ข้อมูลไม่ถูกต้อง';
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

    $del = $pdo->prepare('DELETE FROM repair WHERE repair_id = ?');
    $del->execute([$repair_id]);

    $_SESSION['success'] = 'ลบรายการแจ้งซ่อมเรียบร้อยแล้ว';
    header('Location: ../Reports/manage_repairs.php');
    exit;
} catch (PDOException $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    header('Location: ../Reports/manage_repairs.php');
    exit;
}
