<?php
/**
 * API สำหรับบันทึกธีมหน้าสาธารณะ
 */
session_start();
require_once '../ConnectDB.php';

header('Content-Type: application/json');

// ตรวจสอบ login
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

// รับค่า theme
$theme = $_POST['theme'] ?? '';

// ตรวจสอบค่าที่อนุญาต
$allowedThemes = ['dark', 'light'];
if (!in_array($theme, $allowedThemes)) {
    echo json_encode(['success' => false, 'message' => 'ธีมไม่ถูกต้อง']);
    exit;
}

try {
    $pdo = connectDB();
    
    // อัพเดทหรือเพิ่ม setting
    $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) 
                           VALUES ('public_theme', :theme) 
                           ON DUPLICATE KEY UPDATE setting_value = :theme2");
    $stmt->execute(['theme' => $theme, 'theme2' => $theme]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'บันทึกธีมเรียบร้อย',
        'theme' => $theme
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
