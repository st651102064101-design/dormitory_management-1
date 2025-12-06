<?php
header('Content-Type: application/json');
session_start();

if (empty($_SESSION['admin_username'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$logosDir = '../Assets/Images/';
$files = [];

try {
    if (is_dir($logosDir)) {
        $allFiles = scandir($logosDir);
        foreach ($allFiles as $file) {
            // ข้ามไฟล์และโฟลเดอร์พิเศษ
            if ($file === '.' || $file === '..' || is_dir($logosDir . $file)) {
                continue;
            }
            
            // ตรวจสอบเฉพาะไฟล์ logo ที่ลงท้ายด้วย jpg, jpeg, png
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                // ดึงไฟล์ที่เกี่ยวข้องกับ logo
                if (stripos($file, 'logo') !== false || preg_match('/^\d+\.(jpg|jpeg|png)$/i', $file)) {
                    $files[] = $file;
                }
            }
        }
    }
    
    // เรียงลำดับตามชื่อ (Logo.jpg อันดับแรก)
    usort($files, function($a, $b) {
        if (stripos($a, 'logo') !== false && stripos($b, 'logo') === false) return -1;
        if (stripos($a, 'logo') === false && stripos($b, 'logo') !== false) return 1;
        return strcmp($a, $b);
    });
    
    echo json_encode(['success' => true, 'files' => $files]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
