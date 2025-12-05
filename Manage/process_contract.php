<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../Reports/manage_contracts.php');
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';

try {
    $pdo = connectDB();

    $tnt_id = trim($_POST['tnt_id'] ?? '');
    $room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
    $ctr_start = $_POST['ctr_start'] ?? '';
    $ctr_end = $_POST['ctr_end'] ?? '';
    $ctr_deposit = $_POST['ctr_deposit'] ?? '';

    if ($tnt_id === '' || $room_id <= 0 || $ctr_start === '' || $ctr_end === '') {
        $_SESSION['error'] = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        header('Location: ../Reports/manage_contracts.php');
        exit;
    }

    if ($ctr_end < $ctr_start) {
        $_SESSION['error'] = 'วันที่สิ้นสุดต้องไม่น้อยกว่าวันที่เริ่มสัญญา';
        header('Location: ../Reports/manage_contracts.php');
        exit;
    }

    $depositValue = ($ctr_deposit === '' ? 0 : (int)$ctr_deposit);

    // ตรวจสอบห้องพัก
    $roomStmt = $pdo->prepare('SELECT room_status FROM room WHERE room_id = ?');
    $roomStmt->execute([$room_id]);
    $room = $roomStmt->fetch(PDO::FETCH_ASSOC);
    if (!$room) {
        $_SESSION['error'] = 'ไม่พบข้อมูลห้องพัก';
        header('Location: ../Reports/manage_contracts.php');
        exit;
    }

    // ตรวจสอบสัญญาที่ทับซ้อนกันของห้องนี้
    $activeContractStmt = $pdo->prepare("SELECT COUNT(*) FROM contract WHERE room_id = ? AND ctr_status IN ('0','2')");
    $activeContractStmt->execute([$room_id]);
    if ((int)$activeContractStmt->fetchColumn() > 0) {
        $_SESSION['error'] = 'ห้องนี้มีสัญญาอยู่แล้ว';
        header('Location: ../Reports/manage_contracts.php');
        exit;
    }

    $pdo->beginTransaction();

    $insert = $pdo->prepare("INSERT INTO contract (ctr_start, ctr_end, ctr_deposit, ctr_status, tnt_id, room_id) VALUES (?, ?, ?, '0', ?, ?)");
    $insert->execute([$ctr_start, $ctr_end, $depositValue, $tnt_id, $room_id]);

    // ห้องที่มีสัญญาใหม่ให้เป็นไม่ว่าง (1)
    $updateRoom = $pdo->prepare("UPDATE room SET room_status = '1' WHERE room_id = ?");
    $updateRoom->execute([$room_id]);

    $pdo->commit();

    // ตรวจสอบว่าเป็น AJAX request หรือไม่
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'เพิ่มสัญญาเรียบร้อยแล้ว']);
        exit;
    }

    $_SESSION['success'] = 'เพิ่มสัญญาเรียบร้อยแล้ว';
    header('Location: ../Reports/manage_contracts.php');
    exit;
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $errorMsg = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    
    // ตรวจสอบว่าเป็น AJAX request หรือไม่
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $errorMsg]);
        exit;
    }
    
    $_SESSION['error'] = $errorMsg;
    header('Location: ../Reports/manage_contracts.php');
    exit;
}
