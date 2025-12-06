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
        $uploadsDir = __DIR__ . '/../Assets/Images/';
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
        if (strpos($realPath, $realUploadDir) !== 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ไฟล์ไม่ถูกต้อง']);
            exit;
        }

        // ตรวจสอบนามสกุลไฟล์
        $ext = strtolower(pathinfo($oldLogoFile, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'รองรับเฉพาะไฟล์ JPG และ PNG']);
            exit;
        }

        // คัดลอกไฟล์เก่ามาเป็น Logo.jpg
        $newLogoFile = 'Logo.' . $ext;
        $newLogoPath = $uploadsDir . $newLogoFile;

        // หากไฟล์เดิมคือไฟล์ปัจจุบันอยู่แล้ว ให้ถือว่าสำเร็จทันที
        if (realpath($oldLogoPath) === realpath($newLogoPath)) {
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute(['logo_filename', $newLogoFile, $newLogoFile]);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'โหลดรูปเก่าสำเร็จ']);
            exit;
        }

        if (copy($oldLogoPath, $newLogoPath)) {
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute(['logo_filename', $newLogoFile, $newLogoFile]);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'โหลดรูปเก่าสำเร็จ']);
            exit;
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ไม่สามารถโหลดรูป']);
            exit;
        }
    }

    // จัดการ Logo Upload
    if (!empty($_FILES['logo'])) {
        $file = $_FILES['logo'];
        $allowedTypes = ['image/jpeg', 'image/png'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowedTypes)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'รองรับเฉพาะไฟล์ JPG และ PNG']);
            exit;
        }

        if ($file['size'] > $maxSize) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ขนาดไฟล์ไม่ควรเกิน 5MB']);
            exit;
        }

        $uploadsDir = __DIR__ . '/../Assets/Images/';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        // เก็บสำรองโลโก้เดิม (ถ้ามี) โดยไม่ลบ จนกว่าจะเกิน 10 ไฟล์
        $existingLogo = null;
        foreach (['jpg', 'jpeg', 'png'] as $extCheck) {
            $candidate = $uploadsDir . 'Logo.' . $extCheck;
            if (file_exists($candidate)) {
                $existingLogo = $candidate;
                break;
            }
        }

        if ($existingLogo) {
            $backupName = 'Logo_backup_' . date('Ymd_His') . '.' . pathinfo($existingLogo, PATHINFO_EXTENSION);
            @copy($existingLogo, $uploadsDir . $backupName);
        }

        $filename = 'Logo.' . pathinfo($file['name'], PATHINFO_EXTENSION);
        $filepath = $uploadsDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // จำกัดไฟล์ logo ให้เหลือ 10 ไฟล์ล่าสุด
            $logoFiles = [];
            $dirItems = scandir($uploadsDir);
            foreach ($dirItems as $item) {
                if ($item === '.' || $item === '..') continue;
                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png'])) continue;
                if (stripos($item, 'logo') !== false) {
                    $logoFiles[] = $item;
                }
            }

            // เรียงไฟล์ตามเวลาแก้ไข (ใหม่สุดอยู่ท้าย)
            usort($logoFiles, function($a, $b) use ($uploadsDir) {
                return filemtime($uploadsDir . $a) <=> filemtime($uploadsDir . $b);
            });

            $deleted = [];
            while (count($logoFiles) > 10) {
                $old = array_shift($logoFiles);
                @unlink($uploadsDir . $old);
                $deleted[] = $old;
            }

            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute(['logo_filename', $filename, $filename]);

            header('Content-Type: application/json');
            $msg = 'บันทึก Logo สำเร็จ';
            if (!empty($deleted)) {
                $msg .= ' | ลบรูปเก่าอัตโนมัติ ' . count($deleted) . ' ไฟล์';
            }
            echo json_encode(['success' => true, 'message' => $msg, 'filename' => $filename]);
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

    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูลที่จะบันทึก']);
    exit;

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    exit;
}
