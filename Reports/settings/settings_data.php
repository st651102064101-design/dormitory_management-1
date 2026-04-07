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
$defaultAdminQuickActions = [
    ['label' => 'การชำระเงิน', 'href' => 'manage_payments.php', 'shortcut' => 'Ctrl+1', 'enabled' => true],
    ['label' => 'จองห้อง', 'href' => 'manage_booking.php', 'shortcut' => 'Ctrl+2', 'enabled' => true],
    ['label' => 'ค่าใช้จ่าย', 'href' => 'manage_expenses.php', 'shortcut' => 'Ctrl+3', 'enabled' => true],
    ['label' => 'สัญญา', 'href' => 'manage_contracts.php', 'shortcut' => 'Ctrl+4', 'enabled' => true],
    ['label' => 'ตัวช่วยผู้เช่า', 'href' => 'tenant_wizard.php', 'shortcut' => 'Ctrl+5', 'enabled' => true],
];
$adminQuickActions = $defaultAdminQuickActions;

// วันครบกำหนดชำระ (วันที่ N ของแต่ละเดือน)
$paymentDueDay = '5';

// วันที่ออกบิลรายเดือน (bills will only be generated on/after this day each month)
$billingGenerateDay = '1';

// ข้อมูลบัญชีธนาคาร
$bankName = '';
$bankAccountName = '';
$bankAccountNumber = '';
$promptpayNumber = '';

// Google OAuth settings
$googleClientId = '';
$googleClientSecret = '';
$googleRedirectUri = '/dormitory_management/google_callback.php';

// Owner signature for contracts
$ownerSignature = '';

// System language (th or en)
$systemLanguage = 'th';

// Session timeout (in minutes) - ระยะเวลา session หมดอายุระหว่างล็อกอินอยู่
$sessionTimeoutMinutes = '30';

// ดึงค่าตั้งค่าระบบจาก database
try {
    $settingsStmt = $pdo->query("SELECT * FROM system_settings WHERE setting_key IN ('site_name', 'theme_color', 'font_size', 'logo_filename', 'bg_filename', 'contact_phone', 'contact_email', 'public_theme', 'use_bg_image', 'bank_name', 'bank_account_name', 'bank_account_number', 'promptpay_number', 'default_view_mode', 'fps_threshold', 'google_client_id', 'google_client_secret', 'google_redirect_uri', 'owner_signature', 'admin_quick_actions', 'payment_due_day', 'billing_generate_day', 'system_language', 'session_timeout_minutes')");
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
    
    // วันครบกำหนดชำระ
    $paymentDueDay = $settings['payment_due_day'] ?? $paymentDueDay;

    // วันที่ออกบิลรายเดือน
    $billingGenerateDay = $settings['billing_generate_day'] ?? $billingGenerateDay;
    
    // System language
    $systemLanguage = $settings['system_language'] ?? $systemLanguage;

    // Session timeout (minutes)
    $sessionTimeoutMinutes = $settings['session_timeout_minutes'] ?? $sessionTimeoutMinutes;

    // ข้อมูลบัญชีธนาคาร
    $bankName = $settings['bank_name'] ?? $bankName;
    $bankAccountName = $settings['bank_account_name'] ?? $bankAccountName;
    $bankAccountNumber = $settings['bank_account_number'] ?? $bankAccountNumber;
    $promptpayNumber = $settings['promptpay_number'] ?? $promptpayNumber;
    
    // Google OAuth settings
    $googleClientId = $settings['google_client_id'] ?? $googleClientId;
    $googleClientSecret = $settings['google_client_secret'] ?? $googleClientSecret;
    $googleRedirectUri = $settings['google_redirect_uri'] ?? $googleRedirectUri;
    
    // Owner signature
    $ownerSignature = $settings['owner_signature'] ?? $ownerSignature;

    if (!empty($settings['admin_quick_actions'])) {
        $decodedQuickActions = json_decode((string)$settings['admin_quick_actions'], true);
        if (is_array($decodedQuickActions)) {
            $normalizedQuickActions = [];
            foreach ($defaultAdminQuickActions as $index => $defaultAction) {
                $savedAction = isset($decodedQuickActions[$index]) && is_array($decodedQuickActions[$index]) ? $decodedQuickActions[$index] : [];
                $normalizedQuickActions[] = [
                    'label' => trim((string)($savedAction['label'] ?? $defaultAction['label'])),
                    'href' => trim((string)($savedAction['href'] ?? $defaultAction['href'])),
                    'shortcut' => trim((string)($savedAction['shortcut'] ?? $defaultAction['shortcut'])),
                    'enabled' => array_key_exists('enabled', $savedAction) ? (bool)$savedAction['enabled'] : (bool)$defaultAction['enabled'],
                ];
            }
            $adminQuickActions = $normalizedQuickActions;
        }
    }

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
        $insertStmt->execute(['admin_quick_actions', json_encode($adminQuickActions, JSON_UNESCAPED_UNICODE)]);
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
        $effDateStr = trim($rate['effective_date'] ?? '');
        $currentRateDate = (!empty($effDateStr) && strpos($effDateStr, '0000-00') === false) ? $effDateStr : date('Y-m-d');
    }
    
    // ดึงประวัติทั้งหมด
    $allRatesStmt = $pdo->query("SELECT * FROM rate ORDER BY effective_date DESC, rate_id DESC");
    $allRates = $allRatesStmt->fetchAll(PDO::FETCH_ASSOC);

    // กรองแถวซ้ำ: เก็บแค่แถวล่าสุด (rate_id สูงสุด) ต่อวันที่เดียวกัน
    $dedup = [];
    foreach ($allRates as $row) {
        $dateKey = $row['effective_date'] ?? '';
        if (!isset($dedup[$dateKey]) || (int)$row['rate_id'] > (int)$dedup[$dateKey]['rate_id']) {
            $dedup[$dateKey] = $row;
        }
    }
    $allRates = array_values($dedup);
    
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
$imagesDirPath = __DIR__ . '/../../Public/Assets/Images/';
$logoDir = realpath($imagesDirPath);
if ($logoDir === false && is_dir($imagesDirPath)) {
    $logoDir = $imagesDirPath;
}

$imagesBaseDir = $logoDir ?: $imagesDirPath;
$imagesBaseDir = rtrim((string)$imagesBaseDir, '/\\') . '/';

if (!is_file($imagesBaseDir . $logoFilename) && is_file($imagesBaseDir . 'Logo.jpg')) {
    $logoFilename = 'Logo.jpg';
}

if (!is_file($imagesBaseDir . $bgFilename) && is_file($imagesBaseDir . 'bg.jpg')) {
    $bgFilename = 'bg.jpg';
}

if ($logoDir && is_dir($logoDir)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($logoDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $item) {
        if (!$item->isFile()) {
            continue;
        }

        $ext = strtolower((string)$item->getExtension());
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            continue;
        }

        $fullPath = (string)$item->getPathname();
        $relativePath = ltrim(str_replace('\\', '/', substr($fullPath, strlen($imagesBaseDir))), '/');
        if ($relativePath === '') {
            continue;
        }

        // Limit selector options to image root and Payments subfolder only.
        if (strpos($relativePath, '/') !== false && stripos($relativePath, 'Payments/') !== 0) {
            continue;
        }

        $baseNameLower = strtolower(basename($relativePath));
        if (strpos($baseNameLower, 'logo') === false && strpos($baseNameLower, 'bg') !== 0) {
            continue;
        }

        // Signature files should not appear in logo/background selectors.
        if (stripos(basename($relativePath), 'owner_signature_') === 0) {
            continue;
        }

        $imageFiles[] = $relativePath;
    }
}

$imageFiles = array_values(array_unique($imageFiles));

if (!empty($logoFilename) && !in_array($logoFilename, $imageFiles, true)) {
    $imageFiles[] = $logoFilename;
}

if (!empty($bgFilename) && !in_array($bgFilename, $imageFiles, true)) {
    $imageFiles[] = $bgFilename;
}

sort($imageFiles, SORT_NATURAL | SORT_FLAG_CASE);
