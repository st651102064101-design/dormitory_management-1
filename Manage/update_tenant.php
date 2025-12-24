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

    $tnt_id_original = trim($_POST['tnt_id_original'] ?? '');
    $tnt_id = trim($_POST['tnt_id'] ?? '');
    $tnt_name = trim($_POST['tnt_name'] ?? '');
    $tnt_age = isset($_POST['tnt_age']) && $_POST['tnt_age'] !== '' ? (int)$_POST['tnt_age'] : null;
    $tnt_address = trim($_POST['tnt_address'] ?? '') ?: null;
    $tnt_phone = trim($_POST['tnt_phone'] ?? '') ?: null;
    $tnt_education = trim($_POST['tnt_education'] ?? '') ?: null;
    $tnt_faculty = trim($_POST['tnt_faculty'] ?? '') ?: null;
    $tnt_year = trim($_POST['tnt_year'] ?? '') ?: null;
    $tnt_vehicle = trim($_POST['tnt_vehicle'] ?? '') ?: null;
    $tnt_parent = trim($_POST['tnt_parent'] ?? '') ?: null;
    $tnt_parentsphone = trim($_POST['tnt_parentsphone'] ?? '') ?: null;

    if ($tnt_id_original === '' || $tnt_id === '' || !preg_match('/^\d{13}$/', $tnt_id) || $tnt_name === '') {
        $_SESSION['error'] = 'กรุณากรอกข้อมูลให้ครบถ้วนและระบุเลขบัตร 13 หลัก';
        header('Location: ../Reports/manage_tenants.php');
        exit;
    }

    $update = $pdo->prepare('UPDATE tenant SET tnt_name = ?, tnt_age = ?, tnt_address = ?, tnt_phone = ?, tnt_education = ?, tnt_faculty = ?, tnt_year = ?, tnt_vehicle = ?, tnt_parent = ?, tnt_parentsphone = ? WHERE tnt_id = ?');
    $update->execute([
        $tnt_name,
        $tnt_age,
        $tnt_address,
        $tnt_phone,
        $tnt_education,
        $tnt_faculty,
        $tnt_year,
        $tnt_vehicle,
        $tnt_parent,
        $tnt_parentsphone,
        $tnt_id_original,
    ]);

    $_SESSION['success'] = 'แก้ไขข้อมูลผู้เช่าเรียบร้อยแล้ว';
    header('Location: ../Reports/manage_tenants.php');
    exit;
} catch (PDOException $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    header('Location: ../Reports/manage_tenants.php');
    exit;
}
