<?php
/**
 * API สำหรับบันทึกธีมหน้าสาธารณะ
 */
session_start();
require_once __DIR__ . '/../ConnectDB.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// ตรวจสอบ login
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['admin_username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

// รับค่า theme
if (!array_key_exists('theme', $_POST)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลธีม']);
    exit;
}

$theme = trim((string) $_POST['theme']);

// ตรวจสอบค่าที่อนุญาต
$allowedThemes = ['dark', 'light', 'auto'];
if (!in_array($theme, $allowedThemes, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ธีมไม่ถูกต้อง']);
    exit;
}

try {
    $pdo = connectDB();
    
    // อัพเดทหรือเพิ่ม setting
    $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) 
                           VALUES ('public_theme', :theme) 
                           ON DUPLICATE KEY UPDATE setting_value = :theme2");
    $stmt->execute(['theme' => $theme, 'theme2' => $theme]);

    // ล้าง cache sidebar หากมีการ snapshot ค่า settings ไว้
    unset($_SESSION['__sidebar_snapshot_v2']);
    
    echo json_encode([
        'success' => true, 
        'message' => 'บันทึกธีมเรียบร้อย',
        'theme' => $theme
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log('save_public_theme.php DB error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดในการบันทึกธีม']);
}
