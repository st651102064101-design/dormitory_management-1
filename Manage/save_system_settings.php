<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin_username'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';

try {
    $pdo = connectDB();

    // สร้าง table ถ้ายังไม่มี
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value LONGTEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // โหลดรูปเก่า
    if (!empty($_POST['load_old_logo'])) {
        $oldLogoFile = trim($_POST['load_old_logo']);
        if ($oldLogoFile === '') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ไม่ได้ระบุไฟล์รูป']);
            exit;
        }

        if (strpos($oldLogoFile, '..') !== false || (substr($oldLogoFile, 0, 1) === '/')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ชื่อไฟล์ไม่ถูกต้อง']);
            exit;
        }

        $uploadsDir = __DIR__ . '/../Public/Assets/Images/';
        $oldLogoPath = $uploadsDir . $oldLogoFile;

        // ตรวจสอบความปลอดภัย - ตรวจสอบว่าไฟล์อยู่ในโฟลเดอร์ที่ถูกต้อง
        if (!file_exists($oldLogoPath) || realpath($oldLogoPath) === false) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ไม่พบไฟล์รูป']);
            exit;
        }

        // ตรวจสอบว่าเป็นไฟล์ในระบบ
        $realPath = realpath($oldLogoPath);
        $realUploadDir = realpath($uploadsDir);
        if ($realUploadDir === false) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ไม่พบโฟลเดอร์รูปภาพในระบบ']);
            exit;
        }

        if (strpos($realPath, $realUploadDir) !== 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ไฟล์ไม่ถูกต้อง']);
            exit;
        }

        // ตรวจสอบนามสกุลไฟล์
        $ext = strtolower(pathinfo($oldLogoFile, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'รองรับเฉพาะไฟล์ JPG, PNG และ WebP']);
            exit;
        }

        // ใช้ไฟล์ที่เลือกโดยตรง เพื่อลดปัญหา copy/permission
        $relativeLogoFile = ltrim(str_replace('\\', '/', substr($realPath, strlen($realUploadDir))), '/');
        if ($relativeLogoFile === '') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ไม่สามารถระบุไฟล์รูปได้']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute(['logo_filename', $relativeLogoFile, $relativeLogoFile]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'โหลดรูปเก่าสำเร็จ', 'filename' => $relativeLogoFile]);
        exit;
    }

    // ลบรูปเก่า
    if (!empty($_POST['delete_old_logo'])) {
        $oldLogoFile = trim($_POST['delete_old_logo']);
        $uploadsDir = __DIR__ . '/../Public/Assets/Images/';
        $oldLogoPath = $uploadsDir . $oldLogoFile;

        // ตรวจสอบความปลอดภัย
        if (!file_exists($oldLogoPath) || realpath($oldLogoPath) === false) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ไม่พบไฟล์รูป']);
            exit;
        }

        $realPath = realpath($oldLogoPath);
        $realUploadDir = realpath($uploadsDir);
        if ($realUploadDir === false) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ไม่พบโฟลเดอร์รูปภาพในระบบ']);
            exit;
        }

        if (strpos($realPath, $realUploadDir) !== 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ไฟล์ไม่ถูกต้อง']);
            exit;
        }

        // ตรวจสอบนามสกุลไฟล์
        $ext = strtolower(pathinfo($oldLogoFile, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'รองรับเฉพาะไฟล์ JPG, PNG และ WebP']);
            exit;
        }

        // ตรวจสอบว่ารูปที่จะลบกำลังถูกใช้อยู่
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'logo_filename'");
        $stmt->execute();
        $currentLogo = $stmt->fetchColumn();

        if ($currentLogo === $oldLogoFile) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ไม่สามารถลบรูปได้ เพราะกำลังใช้อยู่ในระบบ']);
            exit;
        }

        // Ensure directory/file are writable before unlink (common in shared hosting/XAMPP)
        $targetDir = dirname($oldLogoPath);
        if (is_dir($targetDir) && !is_writable($targetDir)) {
            @chmod($targetDir, 0777);
            clearstatcache(true, $targetDir);
        }
        if (file_exists($oldLogoPath) && !is_writable($oldLogoPath)) {
            @chmod($oldLogoPath, 0666);
            clearstatcache(true, $oldLogoPath);
        }

        if (!is_dir($targetDir) || !is_writable($targetDir) || !is_writable($oldLogoPath)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ไม่สามารถลบรูปได้ กรุณาตรวจสอบสิทธิ์ไฟล์/โฟลเดอร์']);
            exit;
        }

        // ลบไฟล์
        if (unlink($oldLogoPath)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'ลบรูปเก่าสำเร็จ']);
            exit;
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ไม่สามารถลบรูปได้ กรุณาตรวจสอบสิทธิ์ไฟล์/โฟลเดอร์']);
            exit;
        }
    }

    // จัดการ Logo Upload
    if (!empty($_FILES['logo'])) {
        $file = $_FILES['logo'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $allowedExtensions = ['jpg', 'jpeg', 'png'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'ไฟล์ใหญ่เกินค่าที่เซิร์ฟเวอร์กำหนด',
            UPLOAD_ERR_FORM_SIZE => 'ไฟล์ใหญ่เกินที่ฟอร์มกำหนด',
            UPLOAD_ERR_PARTIAL => 'อัปโหลดไฟล์ไม่สมบูรณ์',
            UPLOAD_ERR_NO_FILE => 'ไม่พบไฟล์ที่อัปโหลด',
            UPLOAD_ERR_NO_TMP_DIR => 'ไม่พบโฟลเดอร์ชั่วคราวของเซิร์ฟเวอร์',
            UPLOAD_ERR_CANT_WRITE => 'เซิร์ฟเวอร์ไม่สามารถเขียนไฟล์ได้',
            UPLOAD_ERR_EXTENSION => 'อัปโหลดถูกยกเลิกโดยส่วนขยายของระบบ',
        ];

        $uploadErrCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadErrCode !== UPLOAD_ERR_OK) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $uploadErrors[$uploadErrCode] ?? 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์']);
            exit;
        }

        $ext = strtolower((string)pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'รองรับเฉพาะไฟล์ JPG และ PNG']);
            exit;
        }

        $detectedType = strtolower((string)($file['type'] ?? ''));
        if ($detectedType !== '' && !in_array($detectedType, $allowedTypes, true)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'รองรับเฉพาะไฟล์ JPG และ PNG']);
            exit;
        }

        if ($file['size'] > $maxSize) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ขนาดไฟล์ไม่ควรเกิน 5MB']);
            exit;
        }

        $uploadsDir = __DIR__ . '/../Public/Assets/Images/';
        if (!is_dir($uploadsDir) && !@mkdir($uploadsDir, 0755, true)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ไม่สามารถสร้างโฟลเดอร์เก็บรูปภาพได้']);
            exit;
        }

        $targetDir = $uploadsDir;
        $relativePrefix = '';
        if (!is_writable($targetDir)) {
            $fallbackDir = rtrim($uploadsDir, '/\\') . '/Payments/';
            if (!is_dir($fallbackDir)) {
                @mkdir($fallbackDir, 0777, true);
            }

            if (!is_writable($fallbackDir)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'โฟลเดอร์รูปภาพไม่สามารถเขียนไฟล์ได้']);
                exit;
            }

            $targetDir = $fallbackDir;
            $relativePrefix = 'Payments/';
        }

        $isRootTarget = ($relativePrefix === '');

        // เก็บสำรองโลโก้เดิม (เฉพาะกรณีเขียนไฟล์หลักใน root)
        if ($isRootTarget) {
            $existingLogo = null;
            foreach (['jpg', 'jpeg', 'png'] as $extCheck) {
                $candidate = $targetDir . 'Logo.' . $extCheck;
                if (file_exists($candidate)) {
                    $existingLogo = $candidate;
                    break;
                }
            }

            if ($existingLogo) {
                $backupName = 'Logo_backup_' . date('Ymd_His') . '.' . pathinfo($existingLogo, PATHINFO_EXTENSION);
                @copy($existingLogo, $targetDir . $backupName);
            }
        }

        $filename = $isRootTarget ? ('Logo.' . $ext) : ('logo_' . date('Ymd_His') . '.' . $ext);
        $filepath = $targetDir . $filename;
        $settingLogoFilename = $relativePrefix . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Make file deletable by anyone (needed for delete feature)
            @chmod($filepath, 0666);
            
            // จำกัดไฟล์ logo ให้เหลือ 10 ไฟล์ล่าสุด
            $logoFiles = [];
            $dirItems = scandir($targetDir);
            foreach ($dirItems as $item) {
                if ($item === '.' || $item === '..') continue;
                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png'])) continue;
                if (stripos($item, 'logo') !== false) {
                    $logoFiles[] = $item;
                }
            }

            // เรียงไฟล์ตามเวลาแก้ไข (ใหม่สุดอยู่ท้าย)
            usort($logoFiles, function($a, $b) use ($targetDir) {
                return filemtime($targetDir . $a) <=> filemtime($targetDir . $b);
            });

            $deleted = [];
            while (count($logoFiles) > 10) {
                $old = array_shift($logoFiles);
                @unlink($targetDir . $old);
                $deleted[] = $old;
            }

            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute(['logo_filename', $settingLogoFilename, $settingLogoFilename]);

            header('Content-Type: application/json');
            $msg = 'บันทึก Logo สำเร็จ';
            if (!empty($deleted)) {
                $msg .= ' | ลบรูปเก่าอัตโนมัติ ' . count($deleted) . ' ไฟล์';
            }
            echo json_encode(['success' => true, 'message' => $msg, 'filename' => $settingLogoFilename]);
            exit;
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ไม่สามารถอัพโหลดไฟล์']);
            exit;
        }
    }

    // จัดการ Site Name
    if (!empty($_POST['site_name'])) {
        $siteName = trim($_POST['site_name']);
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute(['site_name', $siteName, $siteName]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'บันทึกชื่อสำเร็จ', 'site_name' => $siteName]);
        exit;
    }

    // จัดการ Theme Color
    if (!empty($_POST['theme_color'])) {
        $color = trim($_POST['theme_color']);
        // Validate hex color
        if (!preg_match('/^#[0-9A-F]{6}$/i', $color)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'รูปแบบสีไม่ถูกต้อง']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute(['theme_color', $color, $color]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'บันทึกสีสำเร็จ']);
        exit;
    }

    // จัดการ Font Size
    if (!empty($_POST['font_size'])) {
        $fontSize = trim($_POST['font_size']);
        $allowedSizes = ['0.9', '1', '1.1', '1.25'];
        
        if (!in_array($fontSize, $allowedSizes)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ขนาดข้อความไม่ถูกต้อง']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute(['font_size', $fontSize, $fontSize]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'บันทึกขนาดข้อความสำเร็จ']);
        exit;
    }

    // จัดการ Default View Mode (grid/list)
    if (!empty($_POST['default_view_mode'])) {
        $viewMode = trim($_POST['default_view_mode']);
        $allowedModes = ['grid', 'list'];
        
        if (!in_array($viewMode, $allowedModes)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'รูปแบบการแสดงผลไม่ถูกต้อง']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute(['default_view_mode', $viewMode, $viewMode]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'บันทึกรูปแบบการแสดงผลสำเร็จ']);
        exit;
    }

    // จัดการ FPS Threshold
    if (!empty($_POST['fps_threshold'])) {
        $fpsThreshold = trim($_POST['fps_threshold']);
        $allowedFps = ['30', '45', '60', '90', '120', '180', '240', '300'];
        
        if (!in_array($fpsThreshold, $allowedFps)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ค่า FPS ไม่ถูกต้อง']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute(['fps_threshold', $fpsThreshold, $fpsThreshold]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'บันทึกค่า FPS สำเร็จ']);
        exit;
    }

    // จัดการ System Language (th/en)
    if (!empty($_POST['system_language'])) {
        $language = trim($_POST['system_language']);
        $allowedLanguages = ['th', 'en'];
        
        if (!in_array($language, $allowedLanguages)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ภาษาไม่ถูกต้อง / Invalid language']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $result = $stmt->execute(['system_language', $language, $language]);
            
            if (!$result) {
                throw new Exception('Database update failed');
            }
            
            // Verify the language was saved
            $verifyStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'system_language' LIMIT 1");
            $verifyStmt->execute();
            $savedLanguage = $verifyStmt->fetchColumn();
            
            if ($savedLanguage !== $language) {
                throw new Exception('Language verification failed: saved ' . $savedLanguage . ' expected ' . $language);
            }
            
            // Update session
            $_SESSION['system_language'] = $language;
            
            // Clear any cached language data
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }

            header('Content-Type: application/json');
            $message = $language === 'th' ? 'บันทึกภาษาสำเร็จ' : 'Language saved successfully';
            echo json_encode([
                'success' => true, 
                'message' => $message,
                'language' => $language,
                'timestamp' => time()
            ]);
            exit;
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'บันทึกภาษาล้มเหลว: ' . $e->getMessage()]);
            exit;
        }
    }

    // จัดการ Background Filename (เลือกจาก dropdown)
    if (!empty($_POST['bg_filename'])) {
        $bgFilename = trim($_POST['bg_filename']);
        $uploadsDir = __DIR__ . '/../Public/Assets/Images/';
        $bgPath = $uploadsDir . $bgFilename;

        // ตรวจสอบว่าไฟล์มีอยู่จริง
        if (!file_exists($bgPath)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ไม่พบไฟล์รูป']);
            exit;
        }

        // ตรวจสอบนามสกุลไฟล์
        $ext = strtolower(pathinfo($bgFilename, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'รองรับเฉพาะไฟล์ JPG, PNG และ WebP']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute(['bg_filename', $bgFilename, $bgFilename]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'บันทึกภาพพื้นหลังสำเร็จ', 'filename' => $bgFilename]);
        exit;
    }

    // จัดการ Background Upload
    if (!empty($_FILES['bg'])) {
        $file = $_FILES['bg'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize = 10 * 1024 * 1024; // 10MB

        if (!in_array($file['type'], $allowedTypes)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'รองรับเฉพาะไฟล์ JPG, PNG และ WebP']);
            exit;
        }

        if ($file['size'] > $maxSize) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ขนาดไฟล์ไม่ควรเกิน 10MB']);
            exit;
        }

        $uploadsDir = __DIR__ . '/../Public/Assets/Images/';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        // สร้างชื่อไฟล์ใหม่
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'bg_' . date('Ymd_His') . '.' . $ext;
        $filepath = $uploadsDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute(['bg_filename', $filename, $filename]);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'อัพโหลดภาพพื้นหลังสำเร็จ', 'filename' => $filename]);
            exit;
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ไม่สามารถอัพโหลดไฟล์']);
            exit;
        }
    }

    // จัดการ Signature Upload (ลายเซ็นเจ้าของหอ)
    if (!empty($_FILES['signature'])) {
        $file = $_FILES['signature'];
        $allowedTypes = ['image/png']; // รองรับเฉพาะ PNG เพื่อให้มีพื้นหลังโปร่งใส
        $maxSize = 2 * 1024 * 1024; // 2MB

        if (!in_array($file['type'], $allowedTypes)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'รองรับเฉพาะไฟล์ PNG (แนะนำพื้นหลังโปร่งใส)']);
            exit;
        }

        if ($file['size'] > $maxSize) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ขนาดไฟล์ไม่ควรเกิน 2MB']);
            exit;
        }

        $uploadsDir = __DIR__ . '/../Public/Assets/Images/';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        // สร้างชื่อไฟล์ลายเซ็น
        $filename = 'owner_signature_' . date('Ymd_His') . '.png';
        $filepath = $uploadsDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute(['owner_signature', $filename, $filename]);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'อัพโหลดลายเซ็นสำเร็จ', 'filename' => $filename]);
            exit;
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ไม่สามารถอัพโหลดไฟล์: ' . error_get_last()['message']]);
            exit;
        }
    }

    // ลบลายเซ็น
    if (isset($_POST['delete_signature'])) {
        // ดึงชื่อไฟล์ลายเซ็นปัจจุบัน
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'owner_signature'");
        $stmt->execute();
        $currentSignature = $stmt->fetchColumn();

        if ($currentSignature) {
            $uploadsDir = __DIR__ . '/../Public/Assets/Images/';
            $signaturePath = $uploadsDir . $currentSignature;

            // ลบไฟล์ (ถ้ามี)
            if (file_exists($signaturePath)) {
                @unlink($signaturePath);
            }

            // ลบค่าใน database
            $stmt = $pdo->prepare("DELETE FROM system_settings WHERE setting_key = 'owner_signature'");
            $stmt->execute();
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'ลบลายเซ็นสำเร็จ']);
        exit;
    }

    // บันทึกเบอร์โทร
    if (!empty($_POST['contact_phone'])) {
        $phone = trim($_POST['contact_phone']);
        // ตรวจสอบความถูกต้องของเบอร์โทร
        if (preg_match('/^[0-9\-\+\s()]{8,20}$/', $phone)) {
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute(['contact_phone', $phone, $phone]);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'บันทึกเบอร์โทรสำเร็จ']);
            exit;
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'รูปแบบเบอร์โทรไม่ถูกต้อง']);
            exit;
        }
    }

    // บันทึกอีเมล
    if (!empty($_POST['contact_email'])) {
        $email = trim($_POST['contact_email']);
        // ตรวจสอบความถูกต้องของอีเมล
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute(['contact_email', $email, $email]);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'บันทึกอีเมลสำเร็จ']);
            exit;
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'รูปแบบอีเมลไม่ถูกต้อง']);
            exit;
        }
    }

    // จัดการ Use Background Image Toggle
    if (isset($_POST['use_bg_image'])) {
        $useBgImage = trim($_POST['use_bg_image']);
        
        if (!in_array($useBgImage, ['0', '1'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ค่าไม่ถูกต้อง']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute(['use_bg_image', $useBgImage, $useBgImage]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'บันทึกการตั้งค่ารูปพื้นหลังสำเร็จ']);
        exit;
    }

    // จัดการปุ่มลัดใน page header
    if (isset($_POST['admin_quick_actions'])) {
        $decodedQuickActions = json_decode((string)$_POST['admin_quick_actions'], true);
        if (!is_array($decodedQuickActions) || empty($decodedQuickActions)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ข้อมูลปุ่มลัดไม่ถูกต้อง']);
            exit;
        }

        $normalizedQuickActions = [];
        foreach (array_slice($decodedQuickActions, 0, 5) as $index => $action) {
            if (!is_array($action)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'ข้อมูลปุ่มลัดไม่ถูกต้อง']);
                exit;
            }

            $label = trim((string)($action['label'] ?? ''));
            $href = trim((string)($action['href'] ?? ''));
            $shortcut = trim((string)($action['shortcut'] ?? ''));
            $enabled = !empty($action['enabled']);

            if ($label === '' || mb_strlen($label) > 50) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'ชื่อปุ่มลัดลำดับที่ ' . ($index + 1) . ' ไม่ถูกต้อง']);
                exit;
            }

            if ($href === '' || strlen($href) > 255 || strpos($href, '..') !== false || preg_match('/^(?:https?:|javascript:|\/\/)/i', $href) || !preg_match('/^[A-Za-z0-9_\/.\-?#=&%]+$/', $href)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'ลิงก์ของปุ่มลัดลำดับที่ ' . ($index + 1) . ' ไม่ถูกต้อง']);
                exit;
            }

            if ($shortcut !== '' && mb_strlen($shortcut) > 20) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'คีย์ลัดของปุ่มลัดลำดับที่ ' . ($index + 1) . ' ยาวเกินไป']);
                exit;
            }

            $normalizedQuickActions[] = [
                'label' => $label,
                'href' => $href,
                'shortcut' => $shortcut,
                'enabled' => $enabled,
            ];
        }

        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $jsonValue = json_encode($normalizedQuickActions, JSON_UNESCAPED_UNICODE);
        $stmt->execute(['admin_quick_actions', $jsonValue, $jsonValue]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'บันทึกปุ่มลัดสำเร็จ', 'quick_actions' => $normalizedQuickActions]);
        exit;
    }

    // บันทึกชื่อธนาคาร
    if (isset($_POST['bank_name'])) {
        $bankName = trim($_POST['bank_name']);
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute(['bank_name', $bankName, $bankName]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'บันทึกชื่อธนาคารสำเร็จ']);
        exit;
    }

    // บันทึกชื่อบัญชี
    if (isset($_POST['bank_account_name'])) {
        $bankAccountName = trim($_POST['bank_account_name']);
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute(['bank_account_name', $bankAccountName, $bankAccountName]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'บันทึกชื่อบัญชีสำเร็จ']);
        exit;
    }

    // บันทึกเลขบัญชี
    if (isset($_POST['bank_account_number'])) {
        $bankAccountNumber = trim($_POST['bank_account_number']);
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute(['bank_account_number', $bankAccountNumber, $bankAccountNumber]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'บันทึกเลขบัญชีสำเร็จ']);
        exit;
    }

    // บันทึกพร้อมเพย์
    if (isset($_POST['promptpay_number'])) {
        $promptpayNumber = trim($_POST['promptpay_number']);
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute(['promptpay_number', $promptpayNumber, $promptpayNumber]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'บันทึกพร้อมเพย์สำเร็จ']);
        exit;
    }

    // บันทึกวันครบกำหนดชำระ
    if (isset($_POST['payment_due_day'])) {
        $paymentDueDay = max(1, min(28, (int)$_POST['payment_due_day']));
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute(['payment_due_day', (string)$paymentDueDay, (string)$paymentDueDay]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'บันทึกวันครบกำหนดชำระสำเร็จ']);
        exit;
    }

    // บันทึกวันออกบิลรายเดือน
    if (isset($_POST['billing_generate_day'])) {
        $billingGenerateDay = max(1, min(28, (int)$_POST['billing_generate_day']));
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute(['billing_generate_day', (string)$billingGenerateDay, (string)$billingGenerateDay]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'บันทึกรอบออกบิลสำเร็จ']);
        exit;
    }

    // บันทึกระยะเวลา Session หมดอายุ
    if (isset($_POST['session_timeout_minutes'])) {
        $timeout = (int)$_POST['session_timeout_minutes'];
        
        if ($timeout < 1 || $timeout > 999) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ระยะเวลา Session ต้องอยู่ระหว่าง 1-999 นาที']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute(['session_timeout_minutes', (string)$timeout, (string)$timeout]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'บันทึกระยะเวลา Session สำเร็จ']);
        exit;
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูลที่จะบันทึก']);
    exit;

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    exit;
}
