<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'ไม่ได้รับอนุญาต']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';

try {
    $pdo = connectDB();
    
    $room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
    
    if ($room_id <= 0) {
        throw new Exception('ไม่พบรหัสห้องพัก');
    }
    
    // Check if file is uploaded
    if (!isset($_FILES['room_image']) || $_FILES['room_image']['error'] !== UPLOAD_ERR_OK) {
        $error_code = isset($_FILES['room_image']) ? $_FILES['room_image']['error'] : 'no file';
        throw new Exception('ไม่มีไฟล์หรือเกิดข้อผิดพลาดในการอัปโหลด (รหัส: ' . $error_code . ')');
    }
    
    $file = $_FILES['room_image'];
    
    // Check if temp file exists and is readable
    if (!file_exists($file['tmp_name']) || !is_readable($file['tmp_name'])) {
        throw new Exception('ไฟล์ชั่วคราวไม่มีอยู่หรือไม่สามารถอ่านได้');
    }
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('ประเภทไฟล์ไม่ถูกต้อง อนุญาตเฉพาะ JPG, PNG, GIF, WebP');
    }
    
    // Validate file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('ขนาดไฟล์ต้องไม่เกิน 5MB');
    }
    
    // Create upload directory if not exists
    $upload_dir = __DIR__ . '/../Public/Assets/Images/Rooms';
    
    // Check if directory exists, if not create it
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            throw new Exception('ไม่สามารถสร้าง directory สำหรับเก็บรูปภาพ');
        }
        // Make sure it's writable
        chmod($upload_dir, 0777);
    }
    
    // Check if directory is writable
    if (!is_writable($upload_dir)) {
        chmod($upload_dir, 0777);
        if (!is_writable($upload_dir)) {
            throw new Exception('ไม่มีสิทธิ์เขียนลงใน directory: ' . $upload_dir);
        }
    }
    
    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'room_' . $room_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $filepath = $upload_dir . '/' . $filename;
    
    // Move uploaded file
    // Use either move_uploaded_file (for normal uploads) or copy (for testing/direct files)
    $move_success = false;
    
    if (is_uploaded_file($file['tmp_name'])) {
        $move_success = move_uploaded_file($file['tmp_name'], $filepath);
    } else if (file_exists($file['tmp_name']) && is_readable($file['tmp_name'])) {
        // Fallback for non-standard uploads (e.g., testing, direct file operations)
        $move_success = copy($file['tmp_name'], $filepath);
        @unlink($file['tmp_name']); // Try to delete original
    }
    
    if (!$move_success) {
        $error_msg = 'ไม่สามารถบันทึกไฟล์';
        
        // Debug info
        $dir_writable = is_writable(dirname($filepath));
        $temp_exists = file_exists($file['tmp_name']);
        $temp_readable = is_readable($file['tmp_name']);
        
        if (!$dir_writable) {
            $error_msg .= ' (ไม่มีสิทธิ์เขียน)';
        } else if (!$temp_exists) {
            $error_msg .= ' (ไฟล์ต้นฉบับหายไป)';
        } else if (!$temp_readable) {
            $error_msg .= ' (ไม่สามารถอ่านไฟล์)';
        }
        
        throw new Exception($error_msg);
    }
    
    // Update database - delete old image first
    $stmt = $pdo->prepare("SELECT room_image FROM room WHERE room_id = ?");
    $stmt->execute([$room_id]);
    $old_room = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($old_room && !empty($old_room['room_image'])) {
        $old_filepath = $upload_dir . '/' . $old_room['room_image'];
        if (file_exists($old_filepath)) {
            @unlink($old_filepath);
        }
    }
    
    // Update room image in database
    $stmt = $pdo->prepare("UPDATE room SET room_image = ? WHERE room_id = ?");
    $stmt->execute([$filename, $room_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'อัปโหลดรูปภาพสำเร็จ',
        'filename' => $filename
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
