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

    $pdo->beginTransaction();

    // 1) เก็บรายการสัญญาและห้องที่เกี่ยวข้อง
    $ctrRows = [];
    $ctrIds = [];
    $roomIds = [];
    $stmtCtr = $pdo->prepare("SELECT ctr_id, room_id FROM contract WHERE tnt_id = ?");
    $stmtCtr->execute([$tnt_id]);
    $ctrRows = $stmtCtr->fetchAll(PDO::FETCH_ASSOC);
    foreach ($ctrRows as $row) {
        if (!empty($row['ctr_id'])) $ctrIds[] = (int)$row['ctr_id'];
        if (!empty($row['room_id'])) $roomIds[] = (int)$row['room_id'];
    }
    $ctrIds = array_values(array_unique($ctrIds));
    $roomIds = array_values(array_unique($roomIds));

    // 2) เก็บรายการ booking และห้องที่เกี่ยวข้อง
    $bkgRows = [];
    $bkgIds = [];
    $stmtBkg = $pdo->prepare("SELECT bkg_id, room_id FROM booking WHERE tnt_id = ?");
    $stmtBkg->execute([$tnt_id]);
    $bkgRows = $stmtBkg->fetchAll(PDO::FETCH_ASSOC);
    foreach ($bkgRows as $row) {
        if (!empty($row['bkg_id'])) $bkgIds[] = (int)$row['bkg_id'];
        if (!empty($row['room_id'])) $roomIds[] = (int)$row['room_id'];
    }
    $bkgIds = array_values(array_unique($bkgIds));
    $roomIds = array_values(array_unique($roomIds));

    // 3) ดึง expense ids จากสัญญา
    $expIds = [];
    if (!empty($ctrIds)) {
        $placeholders = implode(',', array_fill(0, count($ctrIds), '?'));
        $stmtExp = $pdo->prepare("SELECT exp_id FROM expense WHERE ctr_id IN ($placeholders)");
        $stmtExp->execute($ctrIds);
        $expIds = $stmtExp->fetchAll(PDO::FETCH_COLUMN);
    }

    // 4) ลบ payment ที่เกี่ยวข้องกับ expense
    if (!empty($expIds)) {
        $placeholders = implode(',', array_fill(0, count($expIds), '?'));
        $stmtPay = $pdo->prepare("DELETE FROM payment WHERE exp_id IN ($placeholders)");
        $stmtPay->execute($expIds);
    }

    // 5) ลบ booking_payment
    if (!empty($bkgIds)) {
        $placeholders = implode(',', array_fill(0, count($bkgIds), '?'));
        $stmtBP = $pdo->prepare("DELETE FROM booking_payment WHERE bkg_id IN ($placeholders)");
        $stmtBP->execute($bkgIds);
    }

    // 6) ลบ utility และ checkin_record ที่ผูกกับสัญญา
    if (!empty($ctrIds)) {
        $placeholders = implode(',', array_fill(0, count($ctrIds), '?'));
        $stmtUtil = $pdo->prepare("DELETE FROM utility WHERE ctr_id IN ($placeholders)");
        $stmtUtil->execute($ctrIds);
        $stmtCheckin = $pdo->prepare("DELETE FROM checkin_record WHERE ctr_id IN ($placeholders)");
        $stmtCheckin->execute($ctrIds);
    }

    // 7) ลบ expense
    if (!empty($ctrIds)) {
        $placeholders = implode(',', array_fill(0, count($ctrIds), '?'));
        $stmtDelExp = $pdo->prepare("DELETE FROM expense WHERE ctr_id IN ($placeholders)");
        $stmtDelExp->execute($ctrIds);
    }

    // 8) ลบ tenant_workflow
    $stmtWorkflow = $pdo->prepare("DELETE FROM tenant_workflow WHERE tnt_id = ?");
    $stmtWorkflow->execute([$tnt_id]);

    // 9) ลบ contract
    if (!empty($ctrIds)) {
        $placeholders = implode(',', array_fill(0, count($ctrIds), '?'));
        $stmtDelCtr = $pdo->prepare("DELETE FROM contract WHERE ctr_id IN ($placeholders)");
        $stmtDelCtr->execute($ctrIds);
    }

    // 10) ลบ booking
    $stmtDelBkg = $pdo->prepare("DELETE FROM booking WHERE tnt_id = ?");
    $stmtDelBkg->execute([$tnt_id]);

    // 11) ลบ oauth ของผู้เช่า (ถ้ามี)
    $stmtOauth = $pdo->prepare("DELETE FROM tenant_oauth WHERE tnt_id = ?");
    $stmtOauth->execute([$tnt_id]);

    // 12) ลบ tenant
    $delete = $pdo->prepare('DELETE FROM tenant WHERE tnt_id = ?');
    $delete->execute([$tnt_id]);

    // 13) อัพเดทห้องให้ว่าง
    if (!empty($roomIds)) {
        $placeholders = implode(',', array_fill(0, count($roomIds), '?'));
        $stmtRoom = $pdo->prepare("UPDATE room SET room_status = '0' WHERE room_id IN ($placeholders)");
        $stmtRoom->execute($roomIds);
    }

    $pdo->commit();

    $_SESSION['success'] = 'ลบผู้เช่าเรียบร้อยแล้ว';
    header('Location: ../Reports/manage_tenants.php');
    exit;
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    header('Location: ../Reports/manage_tenants.php');
    exit;
}
