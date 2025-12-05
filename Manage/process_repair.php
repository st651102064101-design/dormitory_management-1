<?php
declare(strict_types=1);
session_start();

// ตั้ง timezone เป็น กรุงเทพ
date_default_timezone_set('Asia/Bangkok');

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
    if (!$pdo) {
        throw new Exception('ไม่สามารถเชื่อมต่อฐานข้อมูลได้');
    }

    $ctr_id = isset($_POST['ctr_id']) ? (int)$_POST['ctr_id'] : 0;
    $repair_date = $_POST['repair_date_hidden'] ?? $_POST['repair_date'] ?? '';
    $repair_time = $_POST['repair_time_hidden'] ?? $_POST['repair_time'] ?? '';
    $repair_desc = trim($_POST['repair_desc'] ?? '');
    $repair_status = $_POST['repair_status'] ?? '0';

    error_log("DEBUG repair: ctr_id=$ctr_id, date=$repair_date, time=$repair_time, desc=$repair_desc, status=$repair_status");

    if ($ctr_id <= 0 || $repair_date === '' || $repair_time === '' || $repair_desc === '') {
        $_SESSION['error'] = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        error_log("DEBUG repair: Validation failed");
        header('Location: ../Reports/manage_repairs.php');
        exit;
    }

    $ctrStmt = $pdo->prepare('SELECT ctr_id FROM contract WHERE ctr_id = ?');
    if (!$ctrStmt) {
        throw new Exception('ข้อผิดพลาด prepare: ' . implode(', ', $pdo->errorInfo()));
    }
    $ctrStmt->execute([$ctr_id]);
    if (!$ctrStmt->fetchColumn()) {
        $_SESSION['error'] = 'ไม่พบสัญญา (ctr_id=' . $ctr_id . ')';
        error_log("DEBUG repair: Contract not found");
        header('Location: ../Reports/manage_repairs.php');
        exit;
    }

    error_log("DEBUG repair: Contract found, attempting INSERT");

    $insert = $pdo->prepare('INSERT INTO repair (ctr_id, repair_date, repair_time, repair_desc, repair_status) VALUES (?, ?, ?, ?, ?)');
    if (!$insert) {
        throw new Exception('ข้อผิดพลาด prepare INSERT: ' . implode(', ', $pdo->errorInfo()));
    }
    
    $result = $insert->execute([$ctr_id, $repair_date, $repair_time, $repair_desc, $repair_status]);
    if (!$result) {
        throw new Exception('ข้อผิดพลาด execute: ' . implode(', ', $insert->errorInfo()));
    }

    $lastId = $pdo->lastInsertId();
    error_log("DEBUG repair: INSERT success, ID=$lastId");
    $_SESSION['success'] = 'บันทึกการแจ้งซ่อมเรียบร้อยแล้ว';
    
    // ตรวจสอบว่าเป็น AJAX request หรือไม่
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'บันทึกการแจ้งซ่อมเรียบร้อยแล้ว', 'id' => $lastId]);
        exit;
    }
    
    header('Location: ../Reports/manage_repairs.php');
    exit;
} catch (Exception $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    error_log('Repair Error: ' . $e->getMessage());
    
    // ตรวจสอบว่าเป็น AJAX request หรือไม่
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
        exit;
    }
    
    header('Location: ../Reports/manage_repairs.php');
    exit;
}

