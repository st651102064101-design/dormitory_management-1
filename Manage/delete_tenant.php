<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../Reports/manage_tenants.php');
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';

try {
    $pdo = connectDB();
    $tnt_id = trim($_POST['tnt_id'] ?? '');

    if ($tnt_id === '') {
        $_SESSION['error'] = 'ข้อมูลไม่ถูกต้อง';
        header('Location: ../Reports/manage_tenants.php');
        exit;
    }

    $delete = $pdo->prepare('DELETE FROM tenant WHERE tnt_id = ?');
    $delete->execute([$tnt_id]);

    $_SESSION['success'] = 'ลบผู้เช่าเรียบร้อยแล้ว';
    header('Location: ../Reports/manage_tenants.php');
    exit;
} catch (PDOException $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    header('Location: ../Reports/manage_tenants.php');
    exit;
}
