<?php
header('Content-Type: application/json');
session_start();

if (empty($_SESSION['admin_username'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$logosDir = '..//Assets/Images/';
$files = [];

try {
    if (is_dir($logosDir)) {
        $allFiles = scandir($logosDir);
        foreach ($allFiles as $file) {
            // ข้ามไฟล์และโฟลเดอร์พิเศษ
            if ($file === '.' || $file === '..' || is_dir($logosDir . $file)) {
                continue;
            }
            
            // ข้าม Logo.jpg และ Logo.png (ไฟล์ปัจจุบัน)
            if (strtolower($file) === 'logo.jpg' || strtolower($file) === 'logo.png') {
                continue;
            }
            
            // ตรวจสอบเฉพาะไฟล์ jpg, jpeg, png
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $files[] = $file;
            }
        }
    }
    
    // เรียงลำดับตามชื่อ
    sort($files);
    
    echo json_encode(['success' => true, 'files' => $files]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
