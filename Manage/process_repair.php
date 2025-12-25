<?php
declare(strict_types=1);
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

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
    $repair_date = $_POST['repair_date_hidden'] ?? $_POST['repair_date'] ?? date('Y-m-d');
    $repair_time = $_POST['repair_time_hidden'] ?? $_POST['repair_time'] ?? date('H:i:s');
    $repair_desc = trim($_POST['repair_desc'] ?? '');
    $repair_status = $_POST['repair_status'] ?? '0';
    $repair_image = null;

    // Debug POST data
    $ctr_id_val = $_POST['ctr_id'] ?? 'EMPTY';
    $repair_desc_val = $_POST['repair_desc'] ?? 'EMPTY';
    $repair_image_name = $_FILES['repair_image']['name'] ?? 'NONE';
    error_log("DEBUG repair POST: ctr_id=$ctr_id_val, repair_desc=$repair_desc_val, repair_image=$repair_image_name");
    error_log("DEBUG repair: ctr_id=$ctr_id, date=$repair_date, time=$repair_time, desc=$repair_desc, status=$repair_status");

    if ($ctr_id <= 0 || $repair_desc === '') {
        $_SESSION['error'] = 'กรุณากรอกข้อมูลให้ครบถ้วน (เลือกห้อง และ ระบุรายละเอียด)';
        error_log("DEBUG repair: Validation failed - ctr_id=$ctr_id, desc length=" . strlen($repair_desc));
        header('Location: ../Reports/manage_repairs.php');
        exit;
    }

    // ประมวลผลไฟล์รูปภาพ
    error_log("DEBUG repair: FILES keys=" . json_encode(array_keys($_FILES)));
    error_log("DEBUG repair: repair_image exists=" . (isset($_FILES['repair_image']) ? 'YES' : 'NO'));
    error_log("DEBUG repair: repair_image empty=" . (empty($_FILES['repair_image']['name']) ? 'YES' : 'NO'));
    
    if (!empty($_FILES['repair_image']['name'])) {
        error_log("DEBUG repair: Processing image upload");
        $file = $_FILES['repair_image'];
        error_log("DEBUG repair: File info - name={$file['name']}, size={$file['size']}, tmp_name={$file['tmp_name']}, error={$file['error']}");
        
        $maxFileSize = 5 * 1024 * 1024; // 5MB
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        
        // ตรวจสอบขนาดไฟล์
        if ($file['size'] > $maxFileSize) {
            $_SESSION['error'] = 'ไฟล์รูปภาพใหญ่เกินไป (ไม่เกิน 5MB)';
            error_log("DEBUG repair: File too large");
            header('Location: ../Reports/manage_repairs.php');
            exit;
        }
        
        // ตรวจสอบ MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        error_log("DEBUG repair: MIME type = $mimeType");
        
        if (!in_array($mimeType, $allowedMimes)) {
            $_SESSION['error'] = 'ประเภทไฟล์ไม่ถูกต้อง (สนับสนุน JPG, PNG, WebP)';
            error_log("DEBUG repair: Invalid MIME type");
            header('Location: ../Reports/manage_repairs.php');
            exit;
        }
        
        // ตรวจสอบนามสกุลไฟล์
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions)) {
            $_SESSION['error'] = 'นามสกุลไฟล์ไม่ถูกต้อง';
            error_log("DEBUG repair: Invalid extension");
            header('Location: ../Reports/manage_repairs.php');
            exit;
        }
        
        // สร้างชื่อไฟล์ใหม่
        $uploadsDir = __DIR__ . '/..//Assets/Images/Repairs';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }
        
        $filename = 'repair_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $filepath = $uploadsDir . '/' . $filename;
        error_log("DEBUG repair: Attempting to move {$file['tmp_name']} to $filepath");
        
        // อัปโหลดไฟล์
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            $_SESSION['error'] = 'ไม่สามารถอัปโหลดรูปภาพได้';
            error_log("DEBUG repair: move_uploaded_file FAILED");
            header('Location: ../Reports/manage_repairs.php');
            exit;
        }
        
        error_log("DEBUG repair: move_uploaded_file SUCCESS, filename=$filename");
        $repair_image = $filename;
    } else {
        error_log("DEBUG repair: No image uploaded");
    }

    $ctrStmt = $pdo->prepare('SELECT ctr_id FROM contract WHERE ctr_id = ?');
    if (!$ctrStmt) {
        throw new Exception('ข้อผิดพลาด prepare: ' . implode(', ', $pdo->errorInfo()));
    }
    $ctrResult = $ctrStmt->execute([$ctr_id]);
    if (!$ctrResult) {
        throw new Exception('ข้อผิดพลาด execute contract check: ' . implode(', ', $ctrStmt->errorInfo()));
    }
    if (!$ctrStmt->fetchColumn()) {
        $_SESSION['error'] = 'ไม่พบสัญญา (ctr_id=' . $ctr_id . ')';
        error_log("DEBUG repair: Contract not found for ctr_id=$ctr_id");
        header('Location: ../Reports/manage_repairs.php');
        exit;
    }

    error_log("DEBUG repair: Contract found, attempting INSERT");

    $insert = $pdo->prepare('INSERT INTO repair (ctr_id, repair_date, repair_time, repair_desc, repair_status, repair_image) VALUES (?, ?, ?, ?, ?, ?)');
    if (!$insert) {
        throw new Exception('ข้อผิดพลาด prepare INSERT: ' . implode(', ', $pdo->errorInfo()));
    }
    
    $result = $insert->execute([$ctr_id, $repair_date, $repair_time, $repair_desc, $repair_status, $repair_image]);
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

