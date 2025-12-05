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
    $tnt_status = ($_POST['tnt_status'] ?? '1') === '0' ? '0' : '1';

    if ($tnt_id === '' || !preg_match('/^\d{13}$/', $tnt_id) || $tnt_name === '') {
        $_SESSION['error'] = 'กรุณากรอกเลขบัตรประชาชน 13 หลักและชื่อผู้เช่า';
        header('Location: ../Reports/manage_tenants.php');
        exit;
    }

    $stmtCheck = $pdo->prepare('SELECT COUNT(*) FROM tenant WHERE tnt_id = ?');
    $stmtCheck->execute([$tnt_id]);
    if ((int)$stmtCheck->fetchColumn() > 0) {
        $_SESSION['error'] = 'มีผู้เช่ารหัสนี้อยู่แล้ว';
        header('Location: ../Reports/manage_tenants.php');
        exit;
    }

    $insert = $pdo->prepare('INSERT INTO tenant (tnt_id, tnt_name, tnt_age, tnt_address, tnt_phone, tnt_education, tnt_faculty, tnt_year, tnt_vehicle, tnt_parent, tnt_parentsphone, tnt_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insert->execute([
        $tnt_id,
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
        $tnt_status,
    ]);

    $_SESSION['success'] = 'เพิ่มผู้เช่าเรียบร้อยแล้ว';
    header('Location: ../Reports/manage_tenants.php');
    exit;
} catch (PDOException $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    header('Location: ../Reports/manage_tenants.php');
    exit;
}
