<?php
/**
 * Settings Data - ดึงข้อมูลการตั้งค่าระบบทั้งหมด
 */

// สร้าง table ก่อน ถ้าไม่มี
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value LONGTEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    // Ignore if table creation fails
}

// ค่า default
$siteName = 'Sangthian Dormitory';
$themeColor = '#0f172a';
$fontSize = '1';
$logoFilename = 'Logo.jpg';
$bgFilename = 'bg.jpg';
$contactPhone = '0895656083';
$contactEmail = 'test@gmail.com';
$publicTheme = 'dark';
$useBgImage = '0';
$defaultViewMode = 'grid'; // ค่าเริ่มต้นการแสดงผล grid หรือ list
$fpsThreshold = '60'; // FPS threshold สำหรับแจ้งเตือนประสิทธิภาพต่ำ

// ข้อมูลบัญชีธนาคาร
$bankName = '';
$bankAccountName = '';
$bankAccountNumber = '';
$promptpayNumber = '';

// ดึงค่าตั้งค่าระบบจาก database
try {
    $settingsStmt = $pdo->query("SELECT * FROM system_settings WHERE setting_key IN ('site_name', 'theme_color', 'font_size', 'logo_filename', 'bg_filename', 'contact_phone', 'contact_email', 'public_theme', 'use_bg_image', 'bank_name', 'bank_account_name', 'bank_account_number', 'promptpay_number', 'default_view_mode', 'fps_threshold')");
    $rawSettings = $settingsStmt->fetchAll(PDO::FETCH_ASSOC);
    $settings = [];
    foreach ($rawSettings as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
    }

    // อัพเดท default values ด้วยค่าจาก database
    $siteName = $settings['site_name'] ?? $siteName;
    $themeColor = $settings['theme_color'] ?? $themeColor;
    $fontSize = $settings['font_size'] ?? $fontSize;
    $logoFilename = $settings['logo_filename'] ?? $logoFilename;
    $bgFilename = $settings['bg_filename'] ?? $bgFilename;
    $contactPhone = $settings['contact_phone'] ?? $contactPhone;
    $contactEmail = $settings['contact_email'] ?? $contactEmail;
    $publicTheme = $settings['public_theme'] ?? $publicTheme;
    $useBgImage = $settings['use_bg_image'] ?? $useBgImage;
    $defaultViewMode = $settings['default_view_mode'] ?? $defaultViewMode;
    $fpsThreshold = $settings['fps_threshold'] ?? $fpsThreshold;
    
    // ข้อมูลบัญชีธนาคาร
    $bankName = $settings['bank_name'] ?? $bankName;
    $bankAccountName = $settings['bank_account_name'] ?? $bankAccountName;
    $bankAccountNumber = $settings['bank_account_number'] ?? $bankAccountNumber;
    $promptpayNumber = $settings['promptpay_number'] ?? $promptpayNumber;

    // ถ้า table ว่าง ให้ insert default
    $checkStmt = $pdo->query("SELECT COUNT(*) as cnt FROM system_settings");
    if ((int)$checkStmt->fetchColumn() === 0) {
        $insertStmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
        $insertStmt->execute(['site_name', $siteName]);
        $insertStmt->execute(['theme_color', $themeColor]);
        $insertStmt->execute(['font_size', $fontSize]);
        $insertStmt->execute(['logo_filename', $logoFilename]);
        $insertStmt->execute(['bg_filename', $bgFilename]);
        $insertStmt->execute(['contact_phone', $contactPhone]);
        $insertStmt->execute(['contact_email', $contactEmail]);
    }
} catch (PDOException $e) {
    // Use default values if query fails
}

// ดึงอัตราค่าน้ำค่าไฟ (ล่าสุด)
$waterRate = 18;
$electricRate = 8;
$currentRateDate = date('Y-m-d');
$allRates = [];
$rateUsage = []; // เก็บจำนวนการใช้งานของแต่ละอัตรา
try {
    // ดึงอัตราล่าสุด
    $rateStmt = $pdo->query("SELECT rate_id, rate_water, rate_elec, effective_date FROM rate ORDER BY effective_date DESC, rate_id DESC LIMIT 1");
    $rate = $rateStmt->fetch(PDO::FETCH_ASSOC);
    if ($rate) {
        $waterRate = (int)$rate['rate_water'];
        $electricRate = (int)$rate['rate_elec'];
        $currentRateDate = $rate['effective_date'] ?? date('Y-m-d');
    }
    
    // ดึงประวัติทั้งหมด
    $allRatesStmt = $pdo->query("SELECT * FROM rate ORDER BY effective_date DESC, rate_id DESC");
    $allRates = $allRatesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ดึงข้อมูลการใช้งานของแต่ละอัตรา (จาก expense)
    $usageStmt = $pdo->query("
        SELECT e.rate_water, e.rate_elec, 
               COUNT(DISTINCT e.exp_id) as expense_count,
               COUNT(DISTINCT c.ctr_id) as contract_count,
               COUNT(DISTINCT c.room_id) as room_count,
               GROUP_CONCAT(DISTINCT r.room_number ORDER BY r.room_number SEPARATOR ', ') as rooms
        FROM expense e
        LEFT JOIN contract c ON e.ctr_id = c.ctr_id
        LEFT JOIN room r ON c.room_id = r.room_id
        GROUP BY e.rate_water, e.rate_elec
    ");
    while ($usage = $usageStmt->fetch(PDO::FETCH_ASSOC)) {
        $key = $usage['rate_water'] . '_' . $usage['rate_elec'];
        $rateUsage[$key] = $usage;
    }
} catch (PDOException $e) {}

// นับสถิติ
$totalRooms = 0;
$totalTenants = 0;
$totalBookings = 0;
try {
    $totalRooms = (int)$pdo->query("SELECT COUNT(*) FROM room")->fetchColumn();
    $totalTenants = (int)$pdo->query("SELECT COUNT(*) FROM tenant")->fetchColumn(); // นับทั้งหมด
    $totalBookings = (int)$pdo->query("SELECT COUNT(*) FROM booking WHERE bkg_status = 1")->fetchColumn();
} catch (PDOException $e) {}

// ดึงรายการรูปภาพจากโฟลเดอร์
$imageFiles = [];
$logoDir = realpath(__DIR__ . '/../..//Assets/Images/');
if ($logoDir && is_dir($logoDir)) {
    $files = scandir($logoDir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        if (is_dir($logoDir . '/' . $file)) continue;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $imageFiles[] = $file;
        }
    }
}
