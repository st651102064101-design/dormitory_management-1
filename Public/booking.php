<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../ConnectDB.php';

$pdo = connectDB();

// ดึงค่าตั้งค่าระบบ
$siteName = 'Sangthian Dormitory';
$logoFilename = 'Logo.jpg';
$themeColor = '#1e40af';
$publicTheme = 'dark';
$roomFeatures = ['ไฟฟ้า', 'น้ำประปา', 'WiFi', 'เฟอร์นิเจอร์', 'แอร์', 'ตู้เย็น'];
$bankName = '';
$bankAccountName = '';
$bankAccountNumber = '';
$promptpayNumber = '';
$defaultDeposit = 2000;

try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename', 'theme_color', 'public_theme', 'room_features', 'bank_name', 'bank_account_name', 'bank_account_number', 'promptpay_number', 'default_deposit')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
        if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
        if ($row['setting_key'] === 'theme_color') $themeColor = $row['setting_value'];
        if ($row['setting_key'] === 'public_theme') $publicTheme = $row['setting_value'];
        if ($row['setting_key'] === 'room_features' && !empty($row['setting_value'])) {
            $roomFeatures = array_map('trim', explode(',', $row['setting_value']));
        }
        if ($row['setting_key'] === 'bank_name') $bankName = $row['setting_value'];
        if ($row['setting_key'] === 'bank_account_name') $bankAccountName = $row['setting_value'];
        if ($row['setting_key'] === 'bank_account_number') $bankAccountNumber = $row['setting_value'];
        if ($row['setting_key'] === 'promptpay_number') $promptpayNumber = $row['setting_value'];
        if ($row['setting_key'] === 'default_deposit') $defaultDeposit = (int)$row['setting_value'];
    }
} catch (PDOException $e) {}

// ดึงห้องว่าง (room_status = '0' คือห้องว่าง)
// ไม่แสดงห้องที่มีการจองอยู่แล้ว (bkg_status = '1' หรือ '2')
// และไม่แสดงห้องที่มีสัญญาใช้งาน (ctr_status = '0') — ถือว่าเป็นกรณีชำระมัดจำแล้ว/มีสัญญา
$availableRooms = [];
try {
        $stmt = $pdo->prepare("
                SELECT r.*, rt.type_name, rt.type_price
                FROM room r
                LEFT JOIN roomtype rt ON r.type_id = rt.type_id
                WHERE r.room_status = '0'
                    AND NOT EXISTS (
                        SELECT 1 FROM booking b WHERE b.room_id = r.room_id AND b.bkg_status IN ('1','2')
                    )
                    AND NOT EXISTS (
                        SELECT 1 FROM contract c WHERE c.room_id = r.room_id AND c.ctr_status = '0'
                    )
                ORDER BY CAST(r.room_number AS UNSIGNED) ASC
        ");
        $stmt->execute();
        $availableRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
        // ถ้ามีข้อผิดพลาด ให้ fallback ไปยัง query เดิม — แสดงเฉพาะ room_status = '0'
        try {
                $stmt = $pdo->query("SELECT r.*, rt.type_name, rt.type_price FROM room r LEFT JOIN roomtype rt ON r.type_id = rt.type_id WHERE r.room_status = '0' ORDER BY CAST(r.room_number AS UNSIGNED) ASC");
                $availableRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {}
}

// ถ้ามีการเลือกห้องมา
$selectedRoom = null;
if (!empty($_GET['room'])) {
    $roomId = (int)$_GET['room'];
    foreach ($availableRooms as $room) {
        if ($room['room_id'] == $roomId) {
            $selectedRoom = $room;
            break;
        }
    }
}

$success = false;
$error = '';

// ดึงอัตราค่าน้ำค่าไฟปัจจุบัน
$rateElec = 4;
$rateWater = 5;
try {
    $rateStmt = $pdo->query("SELECT rate_elec, rate_water FROM rate ORDER BY rate_id DESC LIMIT 1");
    $rateData = $rateStmt->fetch(PDO::FETCH_ASSOC);
    if ($rateData) {
        $rateElec = (int)$rateData['rate_elec'];
        $rateWater = (int)$rateData['rate_water'];
    }
} catch (PDOException $e) {}

// ฟังก์ชันสำหรับอัพโหลดไฟล์
function uploadFile($file, $uploadDir, $prefix = 'file') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    // Check by extension instead of MIME type (more reliable)
    if (!in_array($ext, $allowedExt)) {
        return null;
    }
    
    if ($file['size'] > $maxSize) {
        return null;
    }
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $filepath = $uploadDir . '/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    }
    return null;
    
    return null;
}

// Process form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ข้อมูลพื้นฐาน
    $roomId = (int)($_POST['room_id'] ?? 0);
    $idCard = trim($_POST['id_card'] ?? '');
    $idCard = preg_replace('/[^0-9]/', '', $idCard);
    // ตรวจสอบความยาวก่อน substr เพื่อหลีกเลี่ยงปัญหา
    if (strlen($idCard) > 13) {
        $idCard = substr($idCard, -13);
    }
    
    $name = trim($_POST['name'] ?? '');
    $age = (int)($_POST['age'] ?? 0);
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $phone = preg_replace('/[^0-9]/', '', $phone);
    // ตรวจสอบความยาวก่อน substr เพื่อหลีกเลี่ยงปัญหา
    if (strlen($phone) > 10) {
        $phone = substr($phone, -10);
    }
    
    // ข้อมูลการศึกษา
    $education = trim($_POST['education'] ?? '');
    $faculty = trim($_POST['faculty'] ?? '');
    $year = trim($_POST['year'] ?? '');
    $vehicle = trim($_POST['vehicle'] ?? '');
    
    // ข้อมูลผู้ปกครอง
    $parent = trim($_POST['parent'] ?? '');
    $parentsphone = trim($_POST['parentsphone'] ?? '');
    $parentsphone = preg_replace('/[^0-9]/', '', $parentsphone);
    if (strlen($parentsphone) > 10) {
        $parentsphone = substr($parentsphone, -10);
    }
    
    // วันที่สัญญา
    $ctrStart = $_POST['ctr_start'] ?? '';
    $ctrEnd = $_POST['ctr_end'] ?? '';
    $deposit = (int)($_POST['deposit'] ?? 2000);
    
    // Validate - ตรวจสอบแต่ละฟิลด์และให้ error message ที่ชัดเจน
    $validationErrors = [];
    if (!$roomId) {
        $validationErrors[] = 'ห้องพัก';
    }
    if (!$idCard || strlen($idCard) !== 13) {
        $validationErrors[] = 'เลขบัตรประชาชน 13 หลัก';
    }
    if (!$name || strlen($name) < 4) {
        $validationErrors[] = 'ชื่อ-นามสกุล';
    }
    if (!$phone || strlen($phone) !== 10) {
        $validationErrors[] = 'เบอร์โทรศัพท์ 10 หลัก';
    }
    
    if (!empty($validationErrors)) {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน: ' . implode(', ', $validationErrors);
    } elseif (!$ctrStart || !$ctrEnd) {
        $error = 'กรุณาระบุวันที่เข้าพักและวันที่ออก';
    } else {
        try {
            // ตรวจสอบว่าห้องยังว่างอยู่
            $stmt = $pdo->prepare("SELECT room_status, r.type_id, rt.type_price FROM room r LEFT JOIN roomtype rt ON r.type_id = rt.type_id WHERE room_id = ?");
            $stmt->execute([$roomId]);
            $roomData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$roomData || $roomData['room_status'] !== '0') {
                $error = 'ขออภัย ห้องนี้ถูกจองไปแล้ว';
            } else {
                $roomPrice = (int)($roomData['type_price'] ?? 1500);
                $tenantId = $idCard;
                
                // เริ่ม transaction
                $pdo->beginTransaction();
                try {
                    // ตรวจสอบว่าเลขบัตรประชาชนนี้มีการจองหรือเข้าพักแล้วหรือไม่
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM booking WHERE tnt_id = ? AND bkg_status IN ('1','2')");
                    $checkStmt->execute([$tenantId]);
                    $existing = (int)$checkStmt->fetchColumn();
                    
                    // ตรวจสอบสัญญาที่ยังใช้งานอยู่
                    $checkContract = $pdo->prepare("SELECT COUNT(*) FROM contract WHERE tnt_id = ? AND ctr_status = '0'");
                    $checkContract->execute([$tenantId]);
                    $existingContract = (int)$checkContract->fetchColumn();
                    
                    if ($existing > 0 || $existingContract > 0) {
                        $pdo->rollBack();
                        $error = 'หมายเลขบัตรประชาชนนี้มีการจองหรือสัญญาเช่าอยู่แล้ว';
                    } else {
                        // อัพโหลดไฟล์เอกสาร
                        $uploadDir = __DIR__ . '/../Assets/Images/Documents';
                        $idcardCopy = null;
                        $houseCopy = null;
                        $payProof = null;
                        
                        if (!empty($_FILES['idcard_copy']['name'])) {
                            $idcardCopy = uploadFile($_FILES['idcard_copy'], $uploadDir, 'idcard');
                        }
                        if (!empty($_FILES['house_copy']['name'])) {
                            $houseCopy = uploadFile($_FILES['house_copy'], $uploadDir, 'house');
                        }
                        
                        // อัพโหลดหลักฐานการชำระมัดจำ
                        $paymentDir = __DIR__ . '/../Assets/Images/Payments';
                        if (!empty($_FILES['pay_proof']['name'])) {
                            $payProof = uploadFile($_FILES['pay_proof'], $paymentDir, 'deposit');
                        }
                        
                        // 1. บันทึกข้อมูลผู้เช่าลงตาราง tenant
                        $stmtTenant = $pdo->prepare("
                            INSERT INTO tenant (tnt_id, tnt_name, tnt_age, tnt_address, tnt_phone, tnt_education, tnt_faculty, tnt_year, tnt_vehicle, tnt_parent, tnt_parentsphone, tnt_idcard_copy, tnt_house_copy, tnt_status, tnt_ceatetime)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '3', NOW())
                            ON DUPLICATE KEY UPDATE 
                            tnt_name = VALUES(tnt_name),
                            tnt_age = VALUES(tnt_age),
                            tnt_address = VALUES(tnt_address),
                            tnt_phone = VALUES(tnt_phone),
                            tnt_education = VALUES(tnt_education),
                            tnt_faculty = VALUES(tnt_faculty),
                            tnt_year = VALUES(tnt_year),
                            tnt_vehicle = VALUES(tnt_vehicle),
                            tnt_parent = VALUES(tnt_parent),
                            tnt_parentsphone = VALUES(tnt_parentsphone),
                            tnt_idcard_copy = VALUES(tnt_idcard_copy),
                            tnt_house_copy = VALUES(tnt_house_copy),
                            tnt_status = '3'
                        ");
                        $stmtTenant->execute([$tenantId, $name, $age ?: null, $address ?: null, $phone, $education ?: null, $faculty ?: null, $year ?: null, $vehicle ?: null, $parent ?: null, $parentsphone ?: null, $idcardCopy, $houseCopy]);
                        
                        // 2. บันทึกการจองลงตาราง booking (สถานะ 1 = จองแล้ว)
                        $stmtBooking = $pdo->prepare("
                            INSERT INTO booking (room_id, tnt_id, bkg_checkin_date, bkg_status, bkg_date)
                            VALUES (?, ?, ?, '1', NOW())
                        ");
                        $stmtBooking->execute([$roomId, $tenantId, $ctrStart]);
                        
                        // 3. สร้างสัญญา contract พร้อม access_token
                        $accessToken = md5($tenantId . '-' . $roomId . '-' . time() . '-' . bin2hex(random_bytes(8)));
                        $stmtContract = $pdo->prepare("
                            INSERT INTO contract (ctr_start, ctr_end, ctr_deposit, ctr_status, tnt_id, room_id, access_token)
                            VALUES (?, ?, ?, '0', ?, ?, ?)
                        ");
                        $stmtContract->execute([$ctrStart, $ctrEnd, $deposit, $tenantId, $roomId, $accessToken]);
                        $contractId = $pdo->lastInsertId();
                        
                        // 4. สร้าง expense สำหรับค่ามัดจำ (น้ำไฟ = 0)
                        $stmtExpense = $pdo->prepare("
                            INSERT INTO expense (exp_month, exp_elec_unit, exp_water_unit, rate_elec, rate_water, room_price, exp_elec_chg, exp_water, exp_total, exp_status, ctr_id)
                            VALUES (?, 0, 0, ?, ?, ?, 0, 0, ?, '0', ?)
                        ");
                        $stmtExpense->execute([date('Y-m-01'), $rateElec, $rateWater, $roomPrice, $deposit, $contractId]);
                        $expenseId = $pdo->lastInsertId();
                        
                        // 5. สร้าง payment สำหรับค่ามัดจำ (ถ้ามีหลักฐาน)
                        if ($payProof) {
                            $stmtPayment = $pdo->prepare("
                                INSERT INTO payment (pay_date, pay_amount, pay_proof, pay_status, exp_id)
                                VALUES (NOW(), ?, ?, '0', ?)
                            ");
                            $stmtPayment->execute([$deposit, $payProof, $expenseId]);
                        }
                        
                        // 6. อัพเดทสถานะห้องเป็นไม่ว่าง (1)
                        $updateRoom = $pdo->prepare("UPDATE room SET room_status = '1' WHERE room_id = ?");
                        $updateRoom->execute([$roomId]);
                        
                        $pdo->commit();
                        $success = true;
                    }
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log("Booking transaction error: " . $e->getMessage());
                    $error = 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง (' . $e->getMessage() . ')';
                }
            }
        } catch (PDOException $e) {
            error_log("Booking error: " . $e->getMessage());
            $error = 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จองห้องพัก - <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="icon" type="image/jpeg" href="../Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Prompt', system-ui, sans-serif;
            background: #0a0a0f;
            min-height: 100vh;
            color: #fff;
            overflow-x: hidden;
        }

        /* Animated Background */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .bg-gradient {
            position: absolute;
            width: 100%;
            height: 100%;
            background: linear-gradient(125deg, #0a0a0f 0%, #1a1a2e 25%, #16213e 50%, #0f3460 75%, #1a1a2e 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .floating-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.5;
            animation: floatOrb 20s ease-in-out infinite;
        }

        .orb-1 {
            width: 400px;
            height: 400px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            top: -100px;
            right: -100px;
            animation-delay: 0s;
        }

        .orb-2 {
            width: 300px;
            height: 300px;
            background: linear-gradient(135deg, #f093fb, #f5576c);
            bottom: -50px;
            left: -50px;
            animation-delay: -7s;
        }

        .orb-3 {
            width: 250px;
            height: 250px;
            background: linear-gradient(135deg, #4facfe, #00f2fe);
            top: 50%;
            left: 50%;
            animation-delay: -14s;
        }

        @keyframes floatOrb {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(50px, -50px) scale(1.1); }
            50% { transform: translate(-30px, 30px) scale(0.9); }
            75% { transform: translate(-50px, -30px) scale(1.05); }
        }

        .grid-lines {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            z-index: -1;
        }

        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 50%;
            animation: particleFloat 15s linear infinite;
        }

        .particle:nth-child(1) { left: 10%; animation-delay: 0s; animation-duration: 12s; }
        .particle:nth-child(2) { left: 20%; animation-delay: -2s; animation-duration: 14s; }
        .particle:nth-child(3) { left: 30%; animation-delay: -4s; animation-duration: 16s; }
        .particle:nth-child(4) { left: 40%; animation-delay: -6s; animation-duration: 13s; }
        .particle:nth-child(5) { left: 50%; animation-delay: -8s; animation-duration: 15s; }
        .particle:nth-child(6) { left: 60%; animation-delay: -10s; animation-duration: 11s; }
        .particle:nth-child(7) { left: 70%; animation-delay: -12s; animation-duration: 17s; }
        .particle:nth-child(8) { left: 80%; animation-delay: -14s; animation-duration: 14s; }
        .particle:nth-child(9) { left: 90%; animation-delay: -16s; animation-duration: 12s; }

        @keyframes particleFloat {
            0% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-100vh) rotate(720deg); opacity: 0; }
        }

        /* Header */
        .header {
            background: rgba(10, 10, 15, 0.8);
            backdrop-filter: blur(20px);
            padding: 1rem 2rem;
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s ease;
        }

        .header.scrolled {
            background: rgba(10, 10, 15, 0.95);
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }

        .logo { 
            display: flex; 
            align-items: center; 
            gap: 1rem; 
            text-decoration: none; 
        }
        .logo img { 
            width: 45px; 
            height: 45px; 
            border-radius: 12px; 
            object-fit: cover;
            border: 2px solid rgba(255,255,255,0.2);
            box-shadow: 0 0 20px rgba(102, 126, 234, 0.3);
        }
        .logo h1 { 
            font-size: 1.25rem; 
            color: #fff;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-links { display: flex; gap: 0.5rem; align-items: center; }
        .nav-links a {
            color: #94a3b8;
            text-decoration: none;
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            transition: all 0.3s;
            font-size: 0.95rem;
            position: relative;
            overflow: hidden;
        }
        .nav-links a::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s;
        }
        .nav-links a:hover::before {
            left: 100%;
        }
        .nav-links a:hover { 
            color: #fff; 
            background: rgba(255,255,255,0.1);
            transform: translateY(-2px);
        }

        .nav-icon {
            width: 18px;
            height: 18px;
            vertical-align: middle;
            margin-right: 4px;
            transition: all 0.3s ease;
        }

        .nav-links a:hover .nav-icon {
            transform: scale(1.2);
            filter: drop-shadow(0 0 8px currentColor);
        }

        /* Container */
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 7rem 1.5rem 3rem;
            position: relative;
            z-index: 1;
        }

        /* Back Link */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #60a5fa;
            text-decoration: none;
            margin-bottom: 2rem;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            background: rgba(96, 165, 250, 0.1);
            border: 1px solid rgba(96, 165, 250, 0.2);
            transition: all 0.3s;
        }
        .back-link:hover {
            background: rgba(96, 165, 250, 0.2);
            transform: translateX(-5px);
        }

        /* Page Title */
        .page-title {
            text-align: center;
            margin-bottom: 3rem;
        }
        .page-title .label {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1.5rem;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2), rgba(118, 75, 162, 0.2));
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 50px;
            font-size: 0.9rem;
            color: #a78bfa;
            margin-bottom: 1rem;
        }

        .label-icon {
            width: 16px;
            height: 16px;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        .page-title h2 { 
            font-size: 2.5rem; 
            margin-bottom: 0.75rem;
            background: linear-gradient(135deg, #fff 0%, #a78bfa 50%, #60a5fa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .page-title p { 
            color: #94a3b8;
            font-size: 1.1rem;
        }

        /* Booking Form Card */
        .booking-form {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 2.5rem;
            border: 1px solid rgba(255,255,255,0.1);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease forwards;
        }

        .booking-form::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2, #f093fb);
        }

        /* Section Titles */
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #fff;
            font-size: 1.1rem;
            font-weight: 600;
            margin: 2rem 0 1.25rem 0;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .section-title:first-of-type {
            margin-top: 0;
        }
        .section-title svg {
            width: 24px;
            height: 24px;
            color: #667eea;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            animation: iconFloat 3s ease-in-out infinite;
        }
        
        .section-title:hover svg {
            color: #8b5cf6;
            animation: none;
            transform: scale(1.15) rotate(5deg);
        }
        
        @keyframes iconFloat {
            0%, 100% {
                transform: translateY(0) scale(1);
                color: #667eea;
            }
            50% {
                transform: translateY(-4px) scale(1.05);
                color: #8b5cf6;
            }
        }
        
        /* Form Row (side by side) */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            max-width: 100%;
        }
        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        /* Apple-Style File Upload */
        .file-upload-group {
            margin-bottom: 1.5rem;
            width: 100%;
            min-width: 0;
            overflow: hidden;
        }
        
        .file-upload-group.has-error .apple-upload-zone,
        .file-upload-group.has-error .apple-preview-zone {
            border-color: rgba(239, 68, 68, 0.5) !important;
            background: rgba(239, 68, 68, 0.05);
        }
        
        .apple-upload-container {
            position: relative;
            width: 100%;
            max-width: 100%;
            overflow: hidden;
        }
        
        /* Upload Zone */
        .apple-upload-zone {
            position: relative;
            padding: 3rem 2rem;
            background: rgba(17, 24, 39, 0.6);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 2px dashed rgba(102, 126, 234, 0.3);
            border-radius: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }
        
        .apple-upload-zone::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
            opacity: 0;
            transition: opacity 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .apple-upload-zone:hover {
            border-color: rgba(102, 126, 234, 0.6);
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(102, 126, 234, 0.15);
        }
        
        .apple-upload-zone:hover::before {
            opacity: 1;
        }
        
        .apple-upload-zone.drag-over {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.15);
            transform: scale(1.02);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1), 0 20px 60px rgba(102, 126, 234, 0.2);
        }
        
        .upload-icon-wrapper {
            position: relative;
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2), rgba(118, 75, 162, 0.2));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: floatUpDown 3s ease-in-out infinite;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .upload-icon-wrapper:hover {
            animation: none;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.35), rgba(118, 75, 162, 0.35));
            transform: scale(1.08);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
        }
        
        @keyframes floatUpDown {
            0%, 100% { transform: translateY(0px) scale(1); }
            50% { transform: translateY(-12px) scale(1.02); }
        }
        
        .upload-icon {
            width: 40px;
            height: 40px;
            color: #a5b4fc;
            stroke-width: 1.5;
            animation: uploadBounce 2.5s cubic-bezier(0.4, 0, 0.2, 1) infinite;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        @keyframes uploadBounce {
            0%, 100% { 
                opacity: 1; 
                transform: scale(1) translateY(0);
                color: #a5b4fc;
            }
            25% { 
                opacity: 0.8; 
                transform: scale(1.08) translateY(-3px);
                color: #8b5cf6;
            }
            50% { 
                opacity: 1;
                transform: scale(1.1) translateY(-6px);
                color: #667eea;
            }
            75% { 
                opacity: 0.8;
                transform: scale(1.08) translateY(-3px);
                color: #8b5cf6;
            }
        }
        
        .upload-title {
            color: #e2e8f0;
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0 0 0.5rem 0;
            letter-spacing: -0.02em;
        }
        
        .upload-subtitle {
            color: #94a3b8;
            font-size: 0.95rem;
            margin: 0 0 1.5rem 0;
            font-weight: 400;
        }
        
        .upload-formats {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-bottom: 1rem;
        }
        
        .format-badge {
            display: inline-block;
            padding: 6px 14px;
            background: rgba(102, 126, 234, 0.15);
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 8px;
            color: #a5b4fc;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        .upload-limit {
            color: #64748b;
            font-size: 0.8rem;
            margin: 0;
        }
        
        /* Preview Zone */
        .apple-preview-zone {
            padding: 1rem;
            background: rgba(17, 24, 39, 0.6);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 2px solid rgba(34, 197, 94, 0.3);
            border-radius: 20px;
            animation: slideIn 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            max-width: 100%;
            box-sizing: border-box;
            overflow: hidden;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .preview-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(100, 116, 139, 0.2);
            gap: 12px;
            flex-wrap: nowrap;
            min-width: 0;
        }
        
        .preview-info {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            flex: 1;
            min-width: 0;
            overflow: hidden;
        }
        
        .file-icon {
            width: 40px;
            height: 40px;
            padding: 8px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2), rgba(118, 75, 162, 0.2));
            border-radius: 10px;
            color: #a5b4fc;
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        .file-details {
            flex: 1;
            min-width: 0;
        }
        
        .file-name-preview {
            color: #e2e8f0;
            font-size: 0.95rem;
            font-weight: 500;
            margin: 0 0 4px 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 100%;
            display: block;
        }
        
        .file-size {
            color: #94a3b8;
            font-size: 0.8rem;
            margin: 0;
        }
        
        .remove-file-btn {
            width: 36px;
            height: 36px;
            padding: 8px;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 10px;
            color: #ef4444;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            flex-shrink: 0;
        }
        
        .remove-file-btn:hover {
            background: rgba(239, 68, 68, 0.2);
            border-color: #ef4444;
            transform: rotate(90deg) scale(1.1);
        }
        
        .remove-file-btn svg {
            width: 100%;
            height: 100%;
        }
        
        .preview-content {
            margin: 0.5rem auto;
            border-radius: 12px;
            overflow: hidden;
            background: rgba(10, 10, 15, 0.5);
            height: 150px;
            width: 100%;
            max-width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            box-sizing: border-box;
        }
        
        .preview-content::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        .preview-content::-webkit-scrollbar-track {
            background: rgba(17, 24, 39, 0.5);
            border-radius: 10px;
        }
        
        .preview-content::-webkit-scrollbar-thumb {
            background: rgba(102, 126, 234, 0.5);
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .preview-content::-webkit-scrollbar-thumb:hover {
            background: rgba(102, 126, 234, 0.8);
        }
        
        .preview-content img {
            max-width: 100%;
            max-height: 140px;
            width: auto;
            height: auto;
            object-fit: contain;
            display: block;
            margin: auto;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .preview-content .pdf-placeholder {
            padding: 3rem 2rem;
            text-align: center;
            color: #94a3b8;
        }
        
        .preview-content .pdf-placeholder svg {
            width: 60px;
            height: 60px;
            margin: 0 auto 1rem;
            color: #ef4444;
        }
        
        .preview-success {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            border-radius: 10px;
            color: #22c55e;
            font-size: 0.9rem;
            font-weight: 500;
            animation: successPulse 0.6s ease;
        }
        
        @keyframes successPulse {
            0% { transform: scale(0.95); opacity: 0; }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .checkmark {
            width: 22px;
            height: 22px;
            animation: checkmarkPop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            color: #34d399;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .checkmark:hover {
            animation: none;
            transform: scale(1.15) rotate(10deg);
            color: #06b6d4;
        }
        
        @keyframes checkmarkPop {
            0% { 
                stroke-dasharray: 0 50;
                transform: scale(0) rotate(-45deg);
                opacity: 0;
            }
            60% { 
                transform: scale(1.2) rotate(10deg);
            }
            100% { 
                stroke-dasharray: 50 50;
                transform: scale(1) rotate(0deg);
                opacity: 1;
            }
        }
        
        /* Hint text */
        .hint {
            color: #64748b;
            font-size: 0.85rem;
            margin-top: 6px;
        }
        
        /* Age Input Styles */
        .age-input-tabs {
            display: flex;
            gap: 8px;
            background: rgba(17, 24, 39, 0.4);
            padding: 4px;
            border-radius: 12px;
            width: fit-content;
        }
        
        .age-tab-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: transparent;
            border: none;
            border-radius: 8px;
            color: #94a3b8;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .age-tab-btn svg {
            width: 16px;
            height: 16px;
        }
        
        .age-tab-btn:hover {
            color: #e2e8f0;
            background: rgba(102, 126, 234, 0.1);
        }
        
        .age-tab-btn.active {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.3), rgba(118, 75, 162, 0.3));
            color: #fff;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }
        
        .age-input-method {
            animation: fadeInUp 0.4s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Quick Age Select Buttons */
        .age-quick-select {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .age-quick-btn {
            padding: 8px 16px;
            background: rgba(102, 126, 234, 0.1);
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 10px;
            color: #a5b4fc;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .age-quick-btn:hover {
            background: rgba(102, 126, 234, 0.2);
            border-color: #667eea;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .age-quick-btn:active {
            transform: scale(0.95);
        }
        
        .age-quick-btn.selected {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-color: #667eea;
            color: #fff;
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.4);
        }
        
        /* Age Stepper */
        .age-stepper-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(17, 24, 39, 0.6);
            backdrop-filter: blur(20px);
            padding: 12px 16px;
            border-radius: 16px;
            border: 2px solid rgba(102, 126, 234, 0.2);
            transition: all 0.3s;
        }
        
        .age-stepper-wrapper:focus-within {
            border-color: rgba(102, 126, 234, 0.5);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .age-stepper-btn {
            width: 40px;
            height: 40px;
            padding: 8px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2), rgba(118, 75, 162, 0.2));
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 10px;
            color: #a5b4fc;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            flex-shrink: 0;
        }
        
        .age-stepper-btn:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.3), rgba(118, 75, 162, 0.3));
            border-color: #667eea;
            color: #fff;
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .age-stepper-btn:active {
            transform: scale(0.95);
        }
        
        .age-stepper-btn svg {
            width: 100%;
            height: 100%;
        }
        
        .age-display-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            justify-content: center;
        }
        
        .age-input {
            width: 80px;
            background: transparent;
            border: none;
            color: #fff;
            font-size: 2rem;
            font-weight: 700;
            text-align: center;
            padding: 0;
            -moz-appearance: textfield;
        }
        
        .age-input::-webkit-outer-spin-button,
        .age-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        
        .age-input:focus {
            outline: none;
        }
        
        .age-label {
            color: #94a3b8;
            font-size: 1.2rem;
            font-weight: 500;
        }
        
        /* Age Result Display */
        .age-result {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            border-radius: 12px;
            color: #22c55e;
            margin-top: 12px;
            animation: slideIn 0.4s ease;
        }
        
        .age-result svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }
        
        .age-result span {
            font-size: 0.95rem;
        }
        
        .age-result strong {
            font-size: 1.2rem;
            color: #34d399;
        }
        
        /* Date Input Enhancements */
        .date-quick-select,
        .date-duration-select {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }
        
        .date-quick-btn,
        .duration-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            background: rgba(102, 126, 234, 0.1);
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 10px;
            color: #a5b4fc;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .date-quick-btn svg {
            width: 14px;
            height: 14px;
        }
        
        .date-quick-btn:hover,
        .duration-btn:hover {
            background: rgba(102, 126, 234, 0.2);
            border-color: #667eea;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .date-quick-btn:active,
        .duration-btn:active {
            transform: scale(0.95);
        }
        
        .date-quick-btn.selected,
        .duration-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-color: #667eea;
            color: #fff;
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.4);
        }
        
        .date-input-enhanced {
            position: relative;
            padding-left: 45px;
        }
        
        .date-input-enhanced .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            color: #667eea;
            pointer-events: none;
            z-index: 1;
        }
        
        .date-input-enhanced input[type="date"] {
            padding-left: 45px;
        }
        
        /* Contract Duration Display */
        .contract-duration-display {
            margin-top: 20px;
            padding: 20px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border: 2px solid rgba(102, 126, 234, 0.3);
            border-radius: 16px;
            animation: slideIn 0.5s ease;
        }
        
        .duration-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(100, 116, 139, 0.2);
        }
        
        .duration-info svg {
            width: 50px;
            height: 50px;
            padding: 8px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2), rgba(118, 75, 162, 0.2));
            border-radius: 16px;
            color: #a5b4fc;
            flex-shrink: 0;
            animation: clockAnimation 4s linear infinite;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .duration-info svg:hover {
            animation: none;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.4), rgba(118, 75, 162, 0.4));
            color: #06b6d4;
            transform: scale(1.1);
            padding: 6px;
        }
        
        @keyframes clockAnimation {
            0% {
                transform: rotate(0deg) scale(1);
            }
            25% {
                transform: rotate(90deg) scale(1.02);
            }
            50% {
                transform: rotate(180deg) scale(1);
            }
            75% {
                transform: rotate(270deg) scale(1.02);
            }
            100% {
                transform: rotate(360deg) scale(1);
            }
        }
        
        .duration-text {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .duration-label {
            color: #94a3b8;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .duration-value {
            color: #fff;
            font-size: 1.3rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .duration-breakdown {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        
        .breakdown-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 12px;
            background: rgba(10, 10, 15, 0.5);
            border-radius: 10px;
        }
        
        .breakdown-label {
            color: #64748b;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .breakdown-value {
            color: #e2e8f0;
            font-size: 0.95rem;
            font-weight: 600;
        }
        
        /* Payment Info Box */
        .payment-info-box {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 16px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
        }
        .payment-info-box p {
            color: #94a3b8;
            margin: 0.5rem 0;
            font-size: 0.95rem;
        }
        .payment-info-box p:first-child {
            margin-top: 0;
        }
        .payment-info-box p:last-child {
            margin-bottom: 0;
        }
        .payment-info-box strong {
            color: #fff;
        }
        .payment-info-box #totalPayment {
            color: #34d399;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        /* Address Grid */
        .address-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }
        @media (max-width: 768px) {
            .address-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 480px) {
            .address-grid {
                grid-template-columns: 1fr;
            }
        }
        .address-field {
            position: relative;
        }
        .address-field input,
        .address-field select {
            width: 100%;
            padding: 0.9rem 1rem;
            padding-top: 1.4rem;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.05);
            color: #fff;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        .address-field select {
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 36px;
        }
        .address-field select:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background-color: rgba(255,255,255,0.02);
        }
        .address-field select option {
            background: #1e293b;
            color: #fff;
            padding: 10px;
        }
        .address-field input:focus,
        .address-field select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
            background: rgba(255,255,255,0.08);
        }
        .address-field input::placeholder {
            color: transparent;
        }
        .address-field .field-label {
            position: absolute;
            top: 8px;
            left: 12px;
            font-size: 0.7rem;
            color: #64748b;
            pointer-events: none;
            transition: all 0.2s;
        }
        .address-field input:focus + .field-label,
        .address-field input:not(:placeholder-shown) + .field-label,
        .address-field select:focus + .field-label,
        .address-field select:not([value=""]) + .field-label {
            color: #667eea;
        }

        /* Toggle Buttons */
        .toggle-buttons {
            display: flex;
            gap: 8px;
            background: rgba(255,255,255,0.05);
            padding: 4px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .toggle-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border: none;
            background: transparent;
            color: #94a3b8;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s;
            font-family: 'Prompt', sans-serif;
        }
        .toggle-btn:hover {
            color: #fff;
            background: rgba(255,255,255,0.05);
        }
        .toggle-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        .toggle-btn svg {
            width: 16px;
            height: 16px;
        }
        .address-mode {
            grid-column: 1 / -1;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        /* Autocomplete Styles */
        .autocomplete-wrapper {
            position: relative;
        }
        .autocomplete-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #1e293b;
            border: 1px solid rgba(255,255,255,0.1);
            border-top: none;
            border-radius: 0 0 10px 10px;
            max-height: 250px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        .autocomplete-list.show {
            display: block;
        }
        .autocomplete-item {
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            transition: all 0.2s;
            color: #e2e8f0;
        }
        .autocomplete-item:hover,
        .autocomplete-item.active {
            background: rgba(102, 126, 234, 0.2);
            color: #fff;
        }
        .autocomplete-item:last-child {
            border-bottom: none;
        }
        .autocomplete-item mark {
            background: rgba(102, 126, 234, 0.4);
            color: #fff;
            font-weight: 600;
            padding: 0 2px;
        }
        .autocomplete-list::-webkit-scrollbar {
            width: 8px;
        }
        .autocomplete-list::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.05);
        }
        .autocomplete-list::-webkit-scrollbar-thumb {
            background: rgba(102, 126, 234, 0.5);
            border-radius: 4px;
        }
        .autocomplete-list::-webkit-scrollbar-thumb:hover {
            background: rgba(102, 126, 234, 0.7);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Form Groups */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.6rem;
            color: #94a3b8;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .form-group label .required {
            color: #f472b6;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 1rem 1.25rem;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.05);
            color: #fff;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        /* Name fields with label */
        #firstName, #nickName, #lastName {
            padding-top: 1.4rem;
            padding-bottom: 0.6rem;
        }
        
        #firstName::placeholder, #nickName::placeholder, #lastName::placeholder {
            color: transparent;
        }
        
        #firstName:focus + .field-label,
        #firstName:not(:placeholder-shown) + .field-label,
        #nickName:focus + .field-label,
        #nickName:not(:placeholder-shown) + .field-label,
        #lastName:focus + .field-label,
        #lastName:not(:placeholder-shown) + .field-label {
            color: #667eea;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2), 0 0 20px rgba(102, 126, 234, 0.1);
            background: rgba(255,255,255,0.08);
        }

        .form-group select option {
            background: #1a1a2e;
            color: #fff;
        }
        
        /* Select with icon wrapper */
        .input-wrapper select {
            width: 100%;
            padding: 1rem 2.5rem 1rem 3rem;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%23667eea' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 18px;
        }
        
        .input-wrapper select:hover {
            border-color: rgba(102, 126, 234, 0.5);
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: #64748b;
        }

        /* ═══════════════════════════════════════════════════════
           Apple-Style Modern Validation System
           ═══════════════════════════════════════════════════════ */
        
        /* Form group wrapper */
        .form-group {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        /* Deposit Display (Read-only) */
        .deposit-display {
            padding: 1.25rem;
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.1) 0%, rgba(59, 130, 246, 0.1) 100%);
            border: 2px solid rgba(34, 197, 94, 0.3);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 50px;
            margin-bottom: 0.75rem;
            transition: all 0.3s;
        }
        
        .deposit-display:hover {
            border-color: rgba(34, 197, 94, 0.5);
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.15) 0%, rgba(59, 130, 246, 0.15) 100%);
        }
        
        .deposit-amount {
            font-size: 1.8rem;
            font-weight: 700;
            color: #34d399;
            text-shadow: 0 2px 8px rgba(52, 211, 153, 0.2);
        }
        
        /* Input wrapper for icons */
        .input-wrapper {
            position: relative;
        }
        
        /* Modern animated icons */
        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 22px;
            height: 22px;
            color: #667eea;
            pointer-events: none;
            z-index: 1;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Calendar Icon Animation */
        @keyframes calendarPulse {
            0% {
                transform: translateY(-50%) scale(1);
                color: #667eea;
            }
            50% {
                transform: translateY(-50%) scale(1.1);
                color: #8b5cf6;
            }
            100% {
                transform: translateY(-50%) scale(1);
                color: #667eea;
            }
        }
        
        .calendar-icon {
            animation: calendarPulse 3s ease-in-out infinite;
        }
        
        .calendar-icon:hover {
            animation: none;
            color: #8b5cf6;
            transform: translateY(-50%) scale(1.15);
        }
        
        /* Clock Icon Animation - rotating */
        @keyframes clockTick {
            0% {
                transform: translateY(-50%) rotate(0deg);
                color: #667eea;
            }
            50% {
                color: #06b6d4;
            }
            100% {
                transform: translateY(-50%) rotate(360deg);
                color: #667eea;
            }
        }
        
        .clock-icon {
            animation: clockTick 8s linear infinite;
        }
        
        .clock-icon:hover {
            animation: none;
            color: #06b6d4;
            transform: translateY(-50%) scale(1.15);
        }
        
        /* Validation icon container */
        .validation-icon {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .validation-icon svg {
            width: 100%;
            height: 100%;
        }
        .validation-icon.success { color: #34d399; }
        .validation-icon.error { color: #f87171; }
        
        /* Success state */
        .form-group.is-valid .validation-icon.success {
            opacity: 1;
            transform: translateY(-50%) scale(1);
            animation: successBounce 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .form-group.is-valid input,
        .form-group.is-valid select,
        .form-group.is-valid textarea {
            border-color: rgba(52, 211, 153, 0.5);
            background: linear-gradient(135deg, rgba(52, 211, 153, 0.03), rgba(16, 185, 129, 0.05));
            padding-right: 48px;
        }
        .form-group.is-valid input:focus,
        .form-group.is-valid select:focus,
        .form-group.is-valid textarea:focus {
            border-color: #34d399;
            box-shadow: 0 0 0 4px rgba(52, 211, 153, 0.15), 0 2px 8px rgba(52, 211, 153, 0.1);
        }
        
        /* Error state */
        .form-group.has-error .validation-icon.error {
            opacity: 1;
            transform: translateY(-50%) scale(1);
            animation: errorShake 0.5s cubic-bezier(0.36, 0, 0.66, -0.56);
        }
        .form-group.has-error input,
        .form-group.has-error select,
        .form-group.has-error textarea {
            border-color: rgba(248, 113, 113, 0.5);
            background: linear-gradient(135deg, rgba(248, 113, 113, 0.03), rgba(239, 68, 68, 0.05));
            padding-right: 48px;
        }
        .form-group.has-error input:focus,
        .form-group.has-error select:focus,
        .form-group.has-error textarea:focus {
            border-color: #f87171;
            box-shadow: 0 0 0 4px rgba(248, 113, 113, 0.15), 0 2px 8px rgba(248, 113, 113, 0.1);
        }
        
        /* Inline error message */
        .error-message {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: #f87171;
            margin-top: 8px;
            padding: 8px 12px;
            background: rgba(248, 113, 113, 0.1);
            border-radius: 8px;
            opacity: 0;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .error-message svg {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
        }
        .form-group.has-error .error-message {
            opacity: 1;
            max-height: 60px;
            animation: slideDown 0.3s ease;
        }
        
        /* Animations */
        @keyframes popIn {
            0% { transform: translateY(-50%) scale(0); opacity: 0; }
            50% { transform: translateY(-50%) scale(1.2); }
            100% { transform: translateY(-50%) scale(1); opacity: 1; }
        }
        
        @keyframes successBounce {
            0% { transform: translateY(-50%) scale(0); opacity: 0; }
            50% { transform: translateY(-50%) scale(1.3); }
            100% { transform: translateY(-50%) scale(1); opacity: 1; }
        }
        
        @keyframes errorShake {
            0%, 100% { transform: translateY(-50%) translateX(0) scale(1); }
            10%, 30%, 50%, 70%, 90% { transform: translateY(-50%) translateX(-8px) scale(1.05); }
            20%, 40%, 60%, 80% { transform: translateY(-50%) translateX(8px) scale(1.05); }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateY(-50%) translateX(0); }
            20% { transform: translateY(-50%) translateX(-6px); }
            40% { transform: translateY(-50%) translateX(6px); }
            60% { transform: translateY(-50%) translateX(-4px); }
            80% { transform: translateY(-50%) translateX(4px); }
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); max-height: 0; }
            to { opacity: 1; transform: translateY(0); max-height: 60px; }
        }
        
        /* Toast notification */
        .toast {
            position: fixed;
            top: 100px;
            left: 50%;
            transform: translateX(-50%) translateY(-20px);
            padding: 14px 24px;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            color: #f8fafc;
            font-size: 0.95rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 10000;
            opacity: 0;
            pointer-events: none;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3), 0 0 40px rgba(102, 126, 234, 0.1);
        }
        .toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
            pointer-events: auto;
        }
        .toast.success {
            border-color: rgba(52, 211, 153, 0.3);
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.95), rgba(16, 185, 129, 0.1));
        }
        .toast.error {
            border-color: rgba(248, 113, 113, 0.3);
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.95), rgba(239, 68, 68, 0.1));
        }
        .toast.warning {
            border-color: rgba(251, 191, 36, 0.3);
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.95), rgba(245, 158, 11, 0.1));
        }
        .toast-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .toast.success .toast-icon { background: linear-gradient(135deg, #34d399, #10b981); }
        .toast.error .toast-icon { background: linear-gradient(135deg, #f87171, #ef4444); }
        .toast.warning .toast-icon { background: linear-gradient(135deg, #fbbf24, #f59e0b); }
        .toast-icon svg {
            width: 14px;
            height: 14px;
            stroke: white;
            stroke-width: 2.5;
        }
        
        /* Room selection error */
        .room-selection-error {
            display: none;
            padding: 12px 16px;
            background: linear-gradient(135deg, rgba(248, 113, 113, 0.1), rgba(239, 68, 68, 0.05));
            border: 1px solid rgba(248, 113, 113, 0.2);
            border-radius: 12px;
            color: #f87171;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
        }
        .room-selection-error.show {
            display: flex;
        }
        .room-selection-error svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }
        
        /* Payment Information Section */
        .payment-info-section {
            background: rgba(59, 130, 246, 0.08);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .payment-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .payment-header svg {
            width: 28px;
            height: 28px;
            color: #3b82f6;
            flex-shrink: 0;
            animation: cardSlide 2s ease-in-out infinite;
        }
        
        @keyframes cardSlide {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(4px); }
        }
        
        .payment-header h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #fff;
        }
        
        .payment-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .payment-info-item {
            display: flex;
            flex-direction: column;
            padding: 1.25rem;
            background: rgba(59, 130, 246, 0.05);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 12px;
            transition: all 0.3s;
        }
        
        .payment-info-item:hover {
            background: rgba(59, 130, 246, 0.1);
            border-color: rgba(59, 130, 246, 0.4);
        }
        
        .payment-info-item .label {
            display: block;
            color: #94a3b8;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.75rem;
            font-weight: 500;
        }
        
        .payment-info-item .value {
            display: block;
            color: #fff;
            font-size: 1.05rem;
            font-weight: 600;
            word-break: break-all;
        }
        
        /* Terms & Conditions Section */
        .terms-section {
            background: rgba(102, 126, 234, 0.08);
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .terms-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .terms-header svg {
            width: 28px;
            height: 28px;
            color: #667eea;
            flex-shrink: 0;
        }
        
        .terms-header h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #fff;
        }
        
        .terms-content {
            margin-bottom: 1.5rem;
            border-left: 3px solid rgba(102, 126, 234, 0.5);
            padding-left: 1.5rem;
        }
        
        .term-item {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .term-item:last-child {
            margin-bottom: 0;
        }
        
        .term-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: rgba(102, 126, 234, 0.15);
            border: 1px solid rgba(102, 126, 234, 0.3);
            flex-shrink: 0;
        }
        
        .term-icon-svg {
            width: 28px;
            height: 28px;
            color: #667eea;
            animation: iconPulse 2.5s ease-in-out infinite;
        }
        
        .term-item:nth-child(2) .term-icon-svg {
            animation-delay: 0.3s;
        }
        
        .term-item:nth-child(3) .term-icon-svg {
            animation-delay: 0.6s;
        }
        
        @keyframes iconPulse {
            0%, 100% {
                opacity: 1;
                transform: scale(1);
            }
            50% {
                opacity: 0.7;
                transform: scale(1.1);
            }
        }
        
        .term-text strong {
            display: block;
            color: #fff;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .term-text p {
            color: #cbd5e1;
            font-size: 0.95rem;
            line-height: 1.5;
            margin: 0;
        }
        
        .terms-checkbox {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            background: rgba(52, 211, 153, 0.1);
            border: 1px solid rgba(52, 211, 153, 0.3);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .terms-checkbox:hover {
            background: rgba(52, 211, 153, 0.15);
            border-color: rgba(52, 211, 153, 0.5);
        }
        
        .terms-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #34d399;
        }
        
        .terms-checkbox span {
            color: #cbd5e1;
            font-size: 0.95rem;
            flex: 1;
        }
        
        .terms-checkbox input:checked ~ span {
            color: #34d399;
        }
        
        /* Submit button states */
        .btn-submit {
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .btn-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        .btn-submit:not(:disabled):hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(102, 126, 234, 0.4);
        }
        .btn-submit:not(:disabled):active {
            transform: translateY(0) scale(0.98);
        }
        .btn-submit.loading {
            pointer-events: none;
        }
        .btn-submit.loading .btn-text {
            opacity: 0;
        }
        .btn-submit .loading-spinner {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 24px;
            height: 24px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            opacity: 0;
            transition: opacity 0.2s;
        }
        .btn-submit.loading .loading-spinner {
            opacity: 1;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: translate(-50%, -50%) rotate(360deg); }
        }

        /* Selected Room Card */
        .selected-room {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border: 1px solid rgba(102, 126, 234, 0.3);
            padding: 1.5rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .selected-room .room-info h4 {
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
            color: #fff;
        }

        .selected-room .room-info p {
            color: #94a3b8;
        }

        .selected-room .room-price {
            font-size: 1.75rem;
            font-weight: 700;
            background: linear-gradient(135deg, #22c55e, #4ade80);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Submit Button */
        .btn-submit {
            width: 100%;
            padding: 1.1rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            border: none;
            border-radius: 14px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-submit:hover::before {
            left: 100%;
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
        }

        /* Alert Messages */
        .alert {
            padding: 1.25rem;
            border-radius: 14px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #4ade80;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
        }

        /* Success Box */
        .success-box {
            text-align: center;
            padding: 3rem 2rem;
        }
        .success-box .icon {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            display: block;
            animation: successPulse 2s ease-in-out infinite;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            margin-bottom: 1.5rem;
            animation: successPulse 2s ease-in-out infinite;
            filter: drop-shadow(0 0 20px rgba(34, 197, 94, 0.5));
        }

        .empty-icon {
            width: 80px;
            height: 80px;
            margin-bottom: 1rem;
            animation: floatIcon 3s ease-in-out infinite;
        }

        @keyframes floatIcon {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .btn-icon-sm {
            width: 18px;
            height: 18px;
            vertical-align: middle;
            margin-right: 6px;
        }

        .btn-icon {
            width: 20px;
            height: 20px;
            vertical-align: middle;
            margin-right: 8px;
            transition: all 0.3s ease;
        }

        .btn-submit:hover .btn-icon {
            transform: scale(1.2);
            filter: drop-shadow(0 0 8px currentColor);
        }

        .alert-icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .room-icon {
            width: 24px;
            height: 24px;
            vertical-align: middle;
            margin-right: 4px;
            transition: all 0.3s ease;
        }

        .room-option:hover .room-icon,
        .room-option.selected .room-icon {
            transform: scale(1.15);
            filter: drop-shadow(0 0 8px currentColor);
        }

        @keyframes successPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .success-box h3 {
            font-size: 2rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #22c55e, #4ade80);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .success-box p {
            color: #94a3b8;
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
            line-height: 1.8;
        }
        .success-box a {
            display: inline-block;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .success-box a:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        /* No Rooms State */
        .no-rooms {
            text-align: center;
            padding: 3rem 2rem;
        }
        .no-rooms .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            display: block;
        }
        .no-rooms p {
            color: #94a3b8;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }
        .no-rooms a {
            display: inline-block;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .no-rooms a:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        /* Room Selection Cards */
        .room-selection {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .room-option {
            background: rgba(255,255,255,0.03);
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 1.25rem;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }

        .room-option:hover {
            border-color: rgba(102, 126, 234, 0.5);
            background: rgba(102, 126, 234, 0.1);
            transform: translateY(-3px);
        }

        .room-option.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2), rgba(118, 75, 162, 0.2));
            box-shadow: 0 0 30px rgba(102, 126, 234, 0.3);
        }

        /* Keep radios focusable for browser validation while visually hiding them */
        .room-option input {
            position: absolute;
            opacity: 0;
            width: 1px;
            height: 1px;
            margin: -1px;
            padding: 0;
            border: 0;
            clip: rect(0 0 0 0);
            clip-path: inset(50%);
            overflow: hidden;
            white-space: nowrap;
        }

        .room-option .room-num {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #fff;
        }

        .room-option .room-type {
            font-size: 0.85rem;
            color: #94a3b8;
            margin-bottom: 0.5rem;
        }

        .room-option .room-price {
            font-size: 1.1rem;
            font-weight: 600;
            color: #4ade80;
        }

        /* Visual checkmark for selected room */
        .room-option { position: relative; }
        .room-option .checkmark {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: rgba(255,255,255,0.06);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transform: scale(0.8);
            transition: all 0.18s ease;
        }
        .room-option.selected .checkmark {
            opacity: 1;
            transform: scale(1);
            background: linear-gradient(135deg,#22c55e,#4ade80);
            box-shadow: 0 6px 20px rgba(34,197,94,0.25);
        }
        .room-option .checkmark svg { width: 14px; height: 14px; stroke: white; stroke-width: 2; }

        /* Room Features in Booking */
        .room-features-mini {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 0.35rem;
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        .feature-tag-mini {
            display: inline-flex;
            align-items: center;
            gap: 0.2rem;
            padding: 0.2rem 0.4rem;
            background: rgba(102, 126, 234, 0.15);
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 500;
            color: #a5b4fc;
        }

        .feature-tag-mini svg {
            width: 10px;
            height: 10px;
            stroke: currentColor;
        }

        body.theme-light .feature-tag-mini {
            background: rgba(102, 126, 234, 0.1);
            color: #6366f1;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header { 
                flex-direction: column; 
                gap: 1rem; 
                padding: 1rem; 
            }
            .nav-links { 
                flex-wrap: wrap; 
                justify-content: center;
                gap: 0.5rem;
            }
            .nav-links a {
                padding: 0.5rem 0.8rem;
                font-size: 0.85rem;
            }
            .container {
                padding-top: 9rem;
            }
            .booking-form { 
                padding: 1.5rem; 
            }
            .selected-room { 
                flex-direction: column; 
                gap: 1rem; 
                text-align: center; 
            }
            .page-title h2 {
                font-size: 1.75rem;
            }
            .room-selection {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* ===== LIGHT THEME ===== */
        body.theme-light {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
        }
        body.theme-light .bg-animation {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
        }
        body.theme-light .bg-gradient {
            background: radial-gradient(ellipse at 30% 20%, rgba(59, 130, 246, 0.08), transparent 50%),
                        radial-gradient(ellipse at 70% 60%, rgba(139, 92, 246, 0.06), transparent 50%);
        }
        body.theme-light .grid-lines {
            background-image: linear-gradient(rgba(148, 163, 184, 0.1) 1px, transparent 1px),
                             linear-gradient(90deg, rgba(148, 163, 184, 0.1) 1px, transparent 1px);
        }
        body.theme-light .floating-orb {
            opacity: 0.15;
        }
        body.theme-light .particle {
            background: rgba(59, 130, 246, 0.3);
        }
        body.theme-light .header {
            background: rgba(255, 255, 255, 0.9);
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
        }
        body.theme-light .logo-text {
            color: #1e293b;
        }
        body.theme-light .nav-links a {
            color: #475569;
        }
        body.theme-light .page-title h2 {
            color: #1e293b;
        }
        body.theme-light .page-title p {
            color: #64748b;
        }
        body.theme-light .booking-form {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(148, 163, 184, 0.2);
        }
        body.theme-light .booking-form h3 {
            color: #1e293b;
        }
        body.theme-light .form-group label {
            color: #475569;
        }
        body.theme-light .form-group input,
        body.theme-light .form-group select,
        body.theme-light .form-group textarea {
            background: rgba(248, 250, 252, 0.9);
            border-color: rgba(148, 163, 184, 0.3);
            color: #1e293b;
        }
        body.theme-light .room-option {
            background: rgba(248, 250, 252, 0.9);
            border-color: rgba(148, 163, 184, 0.3);
            color: #1e293b;
        }
        body.theme-light .room-option:hover {
            border-color: var(--primary);
        }
        body.theme-light .selected-room {
            background: rgba(59, 130, 246, 0.1);
            border-color: rgba(59, 130, 246, 0.3);
        }
        body.theme-light .footer {
            background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
            border-top: 1px solid rgba(148, 163, 184, 0.2);
        }
        body.theme-light .footer-links a {
            color: #64748b;
        }
        body.theme-light .footer-copyright {
            color: #94a3b8;
        }
        /* All texts in light theme */
        body.theme-light .room-info h4 {
            color: #1e293b;
        }
        body.theme-light .room-info p {
            color: #64748b;
        }
        body.theme-light .room-price {
            color: #1e293b;
        }
        body.theme-light .success-message h3 {
            color: #1e293b;
        }
        body.theme-light .success-message p {
            color: #64748b;
        }
        body.theme-light .error-message {
            color: #dc2626;
        }
        body.theme-light,
        body.theme-light p,
        body.theme-light span,
        body.theme-light div,
        body.theme-light li,
        body.theme-light label {
            --text-primary: #1e293b;
            --text-secondary: #64748b;
        }
        /* Force all text to dark */
        body.theme-light .logo-text,
        body.theme-light .nav-links a,
        body.theme-light h1,
        body.theme-light h2,
        body.theme-light h3,
        body.theme-light h4 {
            color: #1e293b !important;
        }
        body.theme-light .page-title p,
        body.theme-light .booking-form p,
        body.theme-light label {
            color: #475569 !important;
        }
        body.theme-light .nav-links a {
            color: #475569 !important;
        }
        body.theme-light .nav-links a:hover {
            color: var(--primary) !important;
        }
        
        /* ============================================= */
        /* ULTIMATE LIGHT THEME - ALL TEXT BLACK        */
        /* ============================================= */
        body.theme-light,
        body.theme-light *,
        body.theme-light *::before,
        body.theme-light *::after {
            color: #1e293b !important;
            -webkit-text-fill-color: #1e293b !important;
            text-shadow: none !important;
            opacity: 1 !important;
        }
        
        /* Remove all gradient text effects */
        body.theme-light h1,
        body.theme-light h2,
        body.theme-light h3,
        body.theme-light h4,
        body.theme-light h5,
        body.theme-light h6,
        body.theme-light p,
        body.theme-light span,
        body.theme-light a,
        body.theme-light div,
        body.theme-light li,
        body.theme-light label,
        body.theme-light .gradient-text,
        body.theme-light .logo h1,
        body.theme-light .nav-links a {
            color: #1e293b !important;
            -webkit-text-fill-color: #1e293b !important;
            background: none !important;
            -webkit-background-clip: unset !important;
            background-clip: unset !important;
            text-shadow: none !important;
        }
        
        /* ============ EXCEPTIONS - WHITE TEXT ============ */
        body.theme-light .btn-primary,
        body.theme-light .btn-primary *,
        body.theme-light .btn-login,
        body.theme-light .btn-login *,
        body.theme-light .btn-submit,
        body.theme-light .btn-submit *,
        body.theme-light button[type="submit"],
        body.theme-light button[type="submit"] * {
            color: #1e293b !important;
            -webkit-text-fill-color: #1e293b !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
        }
        
        /* ============ SECONDARY BUTTON - DARK TEXT ============ */
        body.theme-light .btn-secondary,
        body.theme-light .btn-secondary *,
        body.theme-light a.btn-secondary,
        body.theme-light a.btn-secondary * {
            color: #1e293b !important;
            -webkit-text-fill-color: #1e293b !important;
            background: rgba(255, 255, 255, 0.95) !important;
            stroke: #1e293b !important;
        }
        body.theme-light .btn-secondary {
            border: 2px solid #1e293b !important;
        }
        
        /* Force site name to dark */
        body.theme-light .logo h1 {
            color: #1e293b !important;
            background: transparent !important;
            background-clip: unset !important;
            -webkit-background-clip: unset !important;
            -webkit-text-fill-color: #1e293b !important;
            text-shadow: none !important;
        }
        /* Force gradient text to dark */
        body.theme-light .gradient-text {
            color: #1e293b !important;
            background: none !important;
            -webkit-background-clip: unset !important;
            background-clip: unset !important;
            -webkit-text-fill-color: unset !important;
            text-shadow: none !important;
        }
        body.theme-light,
        body.theme-light a,
        body.theme-light p,
        body.theme-light span,
        body.theme-light div,
        body.theme-light h1,
        body.theme-light h2,
        body.theme-light h3,
        body.theme-light h4,
        body.theme-light li,
        body.theme-light label,
        body.theme-light .logo-text,
        body.theme-light .nav-links a,
        body.theme-light .page-title,
        body.theme-light .booking-form {
            color: #1e293b !important;
            opacity: 1 !important;
        }
        body.theme-light h1,
        body.theme-light h2,
        body.theme-light h3,
        body.theme-light h4 {
            background: none !important;
            -webkit-background-clip: unset !important;
            -webkit-text-fill-color: unset !important;
            background-clip: unset !important;
        }
        /* Except buttons - keep white text */
        body.theme-light .btn-primary,
        body.theme-light .btn-login,
        body.theme-light .btn-submit,
        body.theme-light button[type="submit"],
        body.theme-light a[class*="btn"] {
            color: #fff !important;
        }
    </style>
</head>
<?php
// กำหนด theme class
$themeClass = '';
if ($publicTheme === 'light') {
    $themeClass = 'theme-light';
} elseif ($publicTheme === 'auto') {
    $themeClass = '';
}
?>
<body class="<?php echo $themeClass; ?>" data-theme-mode="<?php echo $publicTheme; ?>">
    <?php if ($publicTheme === 'auto'): ?>
    <script>
      (function() {
        const hour = new Date().getHours();
        const isDay = hour >= 6 && hour < 18;
        if (isDay) {
          document.body.classList.add('theme-light');
        }
      })();
    </script>
    <?php endif; ?>
    <!-- Animated Background -->
    <div class="bg-animation">
        <div class="bg-gradient"></div>
        <div class="floating-orb orb-1"></div>
        <div class="floating-orb orb-2"></div>
        <div class="floating-orb orb-3"></div>
    </div>
    <div class="grid-lines"></div>
    <div class="particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>

    <!-- Header -->
    <header class="header" id="header">
        <a href="../index.php" class="logo">
            <img src="../Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="">
            <h1><?php echo htmlspecialchars($siteName); ?></h1>
        </a>
        <nav class="nav-links">
            <a href="../index.php"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg> หน้าแรก</a>
            <a href="rooms.php"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg> ห้องพัก</a>
            <a href="news.php"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6m-6-4h6"/></svg> ข่าวสาร</a>
            <a href="booking.php"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> จองห้อง</a>
        </nav>
    </header>

    <div class="container">
        <a href="../index.php" class="back-link">← กลับหน้าแรก</a>
        
        <div class="page-title">
            <span class="label"><svg class="label-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> การจอง</span>
            <h2>จองห้องพักออนไลน์</h2>
            <p>กรอกข้อมูลเพื่อจองห้องพักที่คุณต้องการ</p>
        </div>
        
        <?php if ($success): ?>
        <div class="booking-form">
            <div class="success-box">
                <svg class="success-icon" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <h3>จองห้องพักสำเร็จ!</h3>
                <p>ทางหอพักจะติดต่อกลับเพื่อยืนยันการจองภายใน 24 ชั่วโมง<br>กรุณารอการติดต่อจากเจ้าหน้าที่</p>
                <a href="../index.php"><svg class="btn-icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg> กลับหน้าแรก</a>
            </div>
        </div>
        <script>
            // Clear localStorage after successful booking
            localStorage.removeItem('bookingForm_payProof');
            localStorage.removeItem('bookingForm_idcard');
            localStorage.removeItem('bookingForm_house');
        </script>
        
        <?php elseif (count($availableRooms) === 0): ?>
        <div class="booking-form">
            <div class="no-rooms">
                <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M16 16s-1.5-2-4-2-4 2-4 2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                <p>ขณะนี้ห้องพักเต็มทุกห้อง</p>
                <p>กรุณาติดต่อสอบถามหรือลองใหม่ภายหลัง</p>
                <a href="../index.php"><svg class="btn-icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg> กลับหน้าแรก</a>
            </div>
        </div>
        
        <?php else: ?>
        <div class="booking-form">
            <?php if ($error): ?>
            <div class="alert alert-error"><svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="post" id="bookingForm" novalidate enctype="multipart/form-data">
                <!-- ส่วนที่ 1: เลือกห้องพัก -->
                <h3 class="section-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg> เลือกห้องพัก</h3>
                <div class="form-group">
                    <label>เลือกห้องพัก</label>
                    <div class="room-selection-error" id="roomError">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span>กรุณาเลือกห้องพักที่ต้องการจอง</span>
                    </div>
                    <div class="room-selection">
                        <?php foreach ($availableRooms as $room): ?>
                                <label class="room-option <?php echo $selectedRoom && $selectedRoom['room_id'] == $room['room_id'] ? 'selected' : ''; ?>">
                            <input type="radio" name="room_id" value="<?php echo $room['room_id']; ?>" required 
                                data-price="<?php echo $room['type_price'] ?? 1500; ?>"
                                <?php echo $selectedRoom && $selectedRoom['room_id'] == $room['room_id'] ? 'checked' : ''; ?>>
                            <div class="room-num"><svg class="room-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg> <?php echo htmlspecialchars($room['room_number']); ?></div>
                            <div class="room-type"><?php echo htmlspecialchars($room['type_name'] ?? 'มาตรฐาน'); ?></div>
                            <div class="room-price">฿<?php echo number_format($room['type_price'] ?? 0); ?>/เดือน</div>
                            <div class="room-features-mini">
                                <?php foreach (array_slice($roomFeatures, 0, 4) as $feature): ?>
                                <span class="feature-tag-mini">
                                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    <?php echo htmlspecialchars($feature); ?>
                                </span>
                                <?php endforeach; ?>
                                <?php if (count($roomFeatures) > 4): ?>
                                <span class="feature-tag-mini">+<?php echo count($roomFeatures) - 4; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="checkmark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- ส่วนที่ 2: ข้อมูลส่วนตัว -->
                <h3 class="section-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> ข้อมูลส่วนตัว</h3>
                
                <div class="form-row">
                    <div class="form-group" data-field="id_card">
                        <label>เลขบัตรประชาชน</label>
                        <div class="input-wrapper">
                            <input type="text" name="id_card" maxlength="13" placeholder="1234567890123" inputmode="numeric" required>
                            <span class="validation-icon success"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg></span>
                            <span class="validation-icon error"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></span>
                        </div>
                        <div class="error-message">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <span>กรุณากรอกเลขบัตรประชาชน 13 หลัก</span>
                        </div>
                    </div>
                    <div class="form-group" data-field="age">
                        <label>อายุ</label>
                        
                        <!-- Age Input Methods Toggle -->
                        <div class="age-input-tabs" style="display: flex; gap: 8px; margin-bottom: 15px;">
                            <button type="button" class="age-tab-btn active" data-tab="direct" id="directAgeTab">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>
                                </svg>
                                กรอกอายุตรง
                            </button>
                            <button type="button" class="age-tab-btn" data-tab="birthdate" id="birthdateTab">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                    <line x1="16" y1="2" x2="16" y2="6"/>
                                    <line x1="8" y1="2" x2="8" y2="6"/>
                                    <line x1="3" y1="10" x2="21" y2="10"/>
                                </svg>
                                คำนวณจากวันเกิด
                            </button>
                        </div>
                        
                        <!-- Direct Age Input -->
                        <div id="directAgeInput" class="age-input-method">
                            <!-- Quick Age Select Buttons -->
                            <div class="age-quick-select" style="display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap;">
                                <button type="button" class="age-quick-btn" data-age="18">18</button>
                                <button type="button" class="age-quick-btn" data-age="19">19</button>
                                <button type="button" class="age-quick-btn" data-age="20">20</button>
                                <button type="button" class="age-quick-btn" data-age="21">21</button>
                                <button type="button" class="age-quick-btn" data-age="22">22</button>
                                <button type="button" class="age-quick-btn" data-age="23">23</button>
                                <button type="button" class="age-quick-btn" data-age="25">25</button>
                            </div>
                            
                            <!-- Age Input with +/- Buttons -->
                            <div class="age-stepper-wrapper">
                                <button type="button" class="age-stepper-btn" id="ageDecrement" aria-label="ลดอายุ">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <line x1="5" y1="12" x2="19" y2="12"/>
                                    </svg>
                                </button>
                                <div class="age-display-wrapper">
                                    <input type="number" name="age" id="ageInput" min="15" max="99" placeholder="20" class="age-input">
                                    <span class="age-label">ปี</span>
                                </div>
                                <button type="button" class="age-stepper-btn" id="ageIncrement" aria-label="เพิ่มอายุ">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <line x1="12" y1="5" x2="12" y2="19"/>
                                        <line x1="5" y1="12" x2="19" y2="12"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Birthdate Input -->
                        <div id="birthdateInput" class="age-input-method" style="display: none;">
                            <div class="input-wrapper">
                                <input type="date" id="birthdateField" max="<?php echo date('Y-m-d'); ?>" placeholder="วัน/เดือน/ปีเกิด">
                            </div>
                            <div class="age-result" id="ageResult" style="display: none;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                    <polyline points="22 4 12 14.01 9 11.01"/>
                                </svg>
                                <span>อายุของคุณคือ <strong id="calculatedAge">0</strong> ปี</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group" data-field="name">
                    <label>ชื่อ-นามสกุล</label>
                    <div class="form-row" style="gap: 1rem;">
                        <div class="input-wrapper" style="flex: 2;">
                            <input type="text" id="firstName" required placeholder="ชื่อจริง" autocomplete="given-name">
                            <span class="field-label" style="position: absolute; top: 8px; left: 12px; font-size: 0.7rem; color: #64748b;">ชื่อจริง</span>
                        </div>
                        <div class="input-wrapper" style="flex: 1;">
                            <input type="text" id="nickName" placeholder="ชื่อเล่น">
                            <span class="field-label" style="position: absolute; top: 8px; left: 12px; font-size: 0.7rem; color: #64748b;">ชื่อเล่น</span>
                        </div>
                        <div class="input-wrapper" style="flex: 2;">
                            <input type="text" id="lastName" required placeholder="นามสกุล" autocomplete="family-name">
                            <span class="field-label" style="position: absolute; top: 8px; left: 12px; font-size: 0.7rem; color: #64748b;">นามสกุล</span>
                        </div>
                    </div>
                    <input type="hidden" name="name" id="fullName" required>
                    <div class="error-message">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span>กรุณากรอกชื่อจริงและนามสกุล</span>
                    </div>
                </div>
                
                <div class="form-group" data-field="address">
                    <label>ที่อยู่ตามบัตรประชาชน</label>
                    
                    <!-- Toggle Switch for Input Mode -->
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                        <span style="color: #94a3b8; font-size: 0.9rem;">วิธีกรอกข้อมูล:</span>
                        <div class="toggle-buttons">
                            <button type="button" class="toggle-btn active" data-mode="search" id="searchModeBtn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <path d="m21 21-4.35-4.35"></path>
                                </svg>
                                พิมพ์ค้นหา
                            </button>
                            <button type="button" class="toggle-btn" data-mode="dropdown" id="dropdownModeBtn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                                เลือกจากรายการ
                            </button>
                        </div>
                    </div>
                    
                    <div class="address-grid">
                        <div class="address-field">
                            <input type="text" name="addr_house" placeholder="บ้านเลขที่" id="addrHouse">
                            <span class="field-label">บ้านเลขที่</span>
                        </div>
                        <div class="address-field autocomplete-wrapper">
                            <input type="text" name="addr_moo" placeholder="หมู่" id="addrMoo" autocomplete="off">
                            <span class="field-label">หมู่</span>
                            <div class="autocomplete-list" id="mooList"></div>
                        </div>
                        <div class="address-field autocomplete-wrapper">
                            <input type="text" name="addr_soi" placeholder="ซอย" id="addrSoi" autocomplete="off">
                            <span class="field-label">ซอย</span>
                            <div class="autocomplete-list" id="soiList"></div>
                        </div>
                        <div class="address-field autocomplete-wrapper">
                            <input type="text" name="addr_road" placeholder="ถนน" id="addrRoad" autocomplete="off">
                            <span class="field-label">ถนน</span>
                            <div class="autocomplete-list" id="roadList"></div>
                        </div>
                        
                        <!-- Search Mode (Default) -->
                        <div id="searchMode" class="address-mode">
                            <div class="address-field autocomplete-wrapper" style="grid-column: 1 / -1;">
                                <input type="text" name="addr_subdistrict_search" id="addrSubdistrictSearch" placeholder="พิมพ์ชื่อ ตำบล/แขวง ที่อยู่ของคุณ..." autocomplete="off">
                                <span class="field-label">ตำบล/แขวง</span>
                                <div class="autocomplete-list" id="subdistrictList"></div>
                            </div>
                            <div class="address-field">
                                <input type="text" id="addrDistrictDisplay" placeholder="อำเภอ/เขต" readonly>
                                <span class="field-label">อำเภอ/เขต</span>
                            </div>
                            <div class="address-field">
                                <input type="text" id="addrProvinceDisplay" placeholder="จังหวัด" readonly>
                                <span class="field-label">จังหวัด</span>
                            </div>
                            <div class="address-field">
                                <input type="text" id="addrZipcodeDisplay" placeholder="รหัสไปรษณีย์" readonly>
                                <span class="field-label">รหัสไปรษณีย์</span>
                            </div>
                        </div>
                        
                        <!-- Dropdown Mode -->
                        <div id="dropdownMode" class="address-mode" style="display: none;">
                            <div class="address-field">
                                <select id="addrProvinceSelect">
                                    <option value="">-- เลือกจังหวัด --</option>
                                </select>
                                <span class="field-label">จังหวัด</span>
                            </div>
                            <div class="address-field">
                                <select id="addrDistrictSelect" disabled>
                                    <option value="">-- เลือกอำเภอ/เขต --</option>
                                </select>
                                <span class="field-label">อำเภอ/เขต</span>
                            </div>
                            <div class="address-field">
                                <select id="addrSubdistrictSelect" disabled>
                                    <option value="">-- เลือกตำบล/แขวง --</option>
                                </select>
                                <span class="field-label">ตำบล/แขวง</span>
                            </div>
                            <div class="address-field">
                                <select id="addrZipcodeSelect" disabled>
                                    <option value="">-- รหัสไปรษณีย์ --</option>
                                </select>
                                <span class="field-label">รหัสไปรษณีย์</span>
                            </div>
                        </div>
                        
                        <!-- Hidden inputs for form submission -->
                        <input type="hidden" name="addr_province" id="addrProvince" required>
                        <input type="hidden" name="addr_district" id="addrDistrict">
                        <input type="hidden" name="addr_subdistrict" id="addrSubdistrict">
                        <input type="hidden" name="addr_zipcode" id="addrZipcode">
                    </div>
                    <input type="hidden" name="address" id="fullAddress">
                    
                    <!-- แสดงที่อยู่เต็ม -->
                    <div id="addressPreview" style="display: none; margin-top: 15px; padding: 15px; background: rgba(102, 126, 234, 0.1); border-left: 3px solid #667eea; border-radius: 8px;">
                        <div style="display: flex; align-items: flex-start; gap: 10px;">
                            <svg style="flex-shrink: 0; margin-top: 2px; opacity: 0.7;" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            <div style="flex: 1;">
                                <div style="font-size: 0.75rem; color: #94a3b8; margin-bottom: 5px; font-weight: 500;">ที่อยู่เต็ม:</div>
                                <div id="addressPreviewText" style="color: #e2e8f0; line-height: 1.6; font-size: 0.9rem;"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group" data-field="phone">
                    <label>เบอร์โทรศัพท์</label>
                    <div class="input-wrapper">
                        <input type="tel" name="phone" required placeholder="0812345678" autocomplete="tel" maxlength="10">
                        <span class="validation-icon success"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg></span>
                        <span class="validation-icon error"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></span>
                    </div>
                    <div class="error-message">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span>กรุณากรอกเบอร์โทรศัพท์ 10 หลัก</span>
                    </div>
                </div>
                
                <!-- ส่วนที่ 3: ข้อมูลการศึกษา -->
                <h3 class="section-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg> ข้อมูลการศึกษา</h3>
                
                <div class="form-group" data-field="education">
                    <label>สถานศึกษา</label>
                    <div class="input-wrapper autocomplete-wrapper">
                        <input type="text" name="education" id="educationInput" placeholder="ชื่อมหาวิทยาลัย/สถาบัน" autocomplete="off">
                        <div class="autocomplete-list" id="educationList"></div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group" data-field="faculty">
                        <label>คณะ</label>
                        <div class="input-wrapper autocomplete-wrapper">
                            <input type="text" name="faculty" id="facultyInput" placeholder="คณะ/สาขา" autocomplete="off">
                            <div class="autocomplete-list" id="facultyList"></div>
                        </div>
                    </div>
                    <div class="form-group" data-field="year">
                        <label>ชั้นปี</label>
                        <div class="input-wrapper">
                            <select name="year">
                                <option value="">เลือกชั้นปี</option>
                                <option value="ปี 1">ปี 1</option>
                                <option value="ปี 2">ปี 2</option>
                                <option value="ปี 3">ปี 3</option>
                                <option value="ปี 4">ปี 4</option>
                                <option value="ปี 5">ปี 5</option>
                                <option value="ปี 6">ปี 6</option>
                                <option value="ปริญญาโท">ปริญญาโท</option>
                                <option value="ปริญญาเอก">ปริญญาเอก</option>
                                <option value="อื่นๆ">อื่นๆ</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group" data-field="vehicle">
                    <label>ทะเบียนรถ (ถ้ามี)</label>
                    <div class="input-wrapper">
                        <input type="text" name="vehicle" placeholder="เช่น กข 1234 กรุงเทพมหานคร">
                    </div>
                </div>
                
                <!-- ส่วนที่ 4: ข้อมูลผู้ปกครอง -->
                <h3 class="section-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg> ข้อมูลผู้ปกครอง</h3>
                
                <div class="form-group" data-field="parent">
                    <label>ชื่อ-นามสกุลผู้ปกครอง</label>
                    <div class="input-wrapper">
                        <input type="text" name="parent" placeholder="ชื่อ-นามสกุลผู้ปกครอง">
                    </div>
                </div>
                
                <div class="form-group" data-field="parentsphone">
                    <label>เบอร์โทรผู้ปกครอง</label>
                    <div class="input-wrapper">
                        <input type="tel" name="parentsphone" placeholder="0812345678" maxlength="10">
                    </div>
                </div>
                
                <!-- ส่วนที่ 5: ระยะเวลาสัญญา -->
                <h3 class="section-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> ระยะเวลาสัญญา</h3>
                
                <div class="form-row">
                    <div class="form-group" data-field="ctr_start">
                        <label>เดือนที่เริ่มเข้าพัก</label>
                        <p class="field-hint" style="font-size: 12px; color: rgba(255,255,255,0.5); margin: 0 0 8px 0;">เริ่มวันที่ 1 ของเดือนที่เลือก</p>
                        
                        <div class="input-wrapper">
                            <svg class="input-icon calendar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                                <line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                            <select name="ctr_start_month" id="ctrStartMonth" required>
                                <?php
                                date_default_timezone_set('Asia/Bangkok');
                                $currentMonth = (int)date('n');
                                $currentYear = (int)date('Y');
                                $thaiMonths = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
                                
                                // Show next 12 months
                                for ($i = 0; $i < 12; $i++) {
                                    $month = (($currentMonth - 1 + $i) % 12) + 1;
                                    $year = $currentYear + floor(($currentMonth - 1 + $i) / 12);
                                    $value = sprintf('%04d-%02d-01', $year, $month);
                                    $thaiYear = $year + 543;
                                    $label = $thaiMonths[$month] . ' ' . $thaiYear;
                                    $selected = ($i == 0) ? 'selected' : '';
                                    echo "<option value=\"$value\" $selected>$label</option>";
                                }
                                ?>
                            </select>
                            <!-- Hidden input for actual date value -->
                            <input type="hidden" name="ctr_start" id="ctrStart" value="<?php echo date('Y-m-01'); ?>">
                        </div>
                    </div>
                    <div class="form-group" data-field="ctr_end">
                        <label>ระยะเวลาสัญญา</label>
                        <p class="field-hint" style="font-size: 12px; color: rgba(255,255,255,0.5); margin: 0 0 8px 0;">ขั้นต่ำ 3 เดือน</p>
                        
                        <div class="input-wrapper">
                            <svg class="input-icon clock-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                            <select name="ctr_duration" id="ctrDuration" required>
                                <option value="3">3 เดือน</option>
                                <option value="6" selected>6 เดือน</option>
                                <option value="9">9 เดือน</option>
                                <option value="12">1 ปี</option>
                                <option value="18">1 ปี 6 เดือน</option>
                                <option value="24">2 ปี</option>
                            </select>
                            <!-- Hidden input for actual end date value -->
                            <input type="hidden" name="ctr_end" id="ctrEnd" value="<?php echo date('Y-m-d', strtotime('+6 months', strtotime(date('Y-m-01')))); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Contract Duration Display -->
                <div class="contract-duration-display" id="durationDisplay" style="display: none;">
                    <div class="duration-info">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                        <div class="duration-text">
                            <span class="duration-label">ระยะเวลาสัญญา</span>
                            <span class="duration-value" id="durationValue">6 เดือน (180 วัน)</span>
                        </div>
                    </div>
                    <div class="duration-breakdown" id="durationBreakdown">
                        <div class="breakdown-item">
                            <span class="breakdown-label">เริ่ม:</span>
                            <span class="breakdown-value" id="startDateText">-</span>
                        </div>
                        <div class="breakdown-item">
                            <span class="breakdown-label">สิ้นสุด:</span>
                            <span class="breakdown-value" id="endDateText">-</span>
                        </div>
                    </div>
                </div>
                
                <div class="form-group" data-field="deposit">
                    <label>เงินมัดจำ (บาท)</label>
                    <div class="deposit-display">
                        <div class="deposit-amount">฿<?php echo number_format($defaultDeposit); ?></div>
                    </div>
                    <input type="hidden" name="deposit" value="<?php echo $defaultDeposit; ?>" id="depositAmount">
                    <div class="hint">* เงินมัดจำจะได้รับคืนเมื่อย้ายออกและไม่มีความเสียหาย</div>
                </div>
                
                <!-- ส่วนที่ 6: เอกสารประกอบ -->
                <h3 class="section-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg> เอกสารประกอบ</h3>
                
                <div class="form-row">
                    <div class="form-group file-upload-group">
                        <label>สำเนาบัตรประชาชน</label>
                        <div class="apple-upload-container" id="idcardUploadContainer">
                            <input type="file" name="idcard_copy" accept="image/*,.pdf" id="idcardFile" hidden required>
                            
                            <!-- Upload Zone -->
                            <div class="apple-upload-zone" id="idcardUploadZone">
                                <div class="upload-icon-wrapper">
                                    <svg class="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <rect x="3" y="4" width="18" height="16" rx="2"/>
                                        <circle cx="8.5" cy="8.5" r="1.5"/>
                                        <path d="M21 15l-5-5L5 21"/>
                                    </svg>
                                </div>
                                <h4 class="upload-title">อัพโหลดบัตรประชาชน</h4>
                                <p class="upload-subtitle">คลิกหรือลากไฟล์มาวางที่นี่</p>
                                <div class="upload-formats">
                                    <span class="format-badge">JPG</span>
                                    <span class="format-badge">PNG</span>
                                    <span class="format-badge">PDF</span>
                                </div>
                                <p class="upload-limit">ไฟล์ไม่เกิน 5MB</p>
                            </div>
                            
                            <!-- Preview Zone -->
                            <div class="apple-preview-zone" id="idcardPreviewZone" style="display: none;">
                                <div class="preview-header">
                                    <div class="preview-info">
                                        <svg class="file-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/>
                                            <polyline points="13 2 13 9 20 9"/>
                                        </svg>
                                        <div class="file-details">
                                            <p class="file-name-preview" id="idcardFileNamePreview">filename.jpg</p>
                                            <p class="file-size" id="idcardFileSizePreview">2.5 MB</p>
                                        </div>
                                    </div>
                                    <button type="button" class="remove-file-btn" id="idcardRemoveBtn">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="18" y1="6" x2="6" y2="18"/>
                                            <line x1="6" y1="6" x2="18" y2="18"/>
                                        </svg>
                                    </button>
                                </div>
                                <div class="preview-content" id="idcardPreviewContent"></div>
                                <div class="preview-success">
                                    <svg class="checkmark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    <span>อัพโหลดสำเร็จ</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group file-upload-group">
                        <label>สำเนาทะเบียนบ้าน</label>
                        <div class="apple-upload-container" id="houseUploadContainer">
                            <input type="file" name="house_copy" accept="image/*,.pdf" id="houseFile" hidden required>
                            
                            <!-- Upload Zone -->
                            <div class="apple-upload-zone" id="houseUploadZone">
                                <div class="upload-icon-wrapper">
                                    <svg class="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                                        <polyline points="9 22 9 12 15 12 15 22"/>
                                    </svg>
                                </div>
                                <h4 class="upload-title">อัพโหลดทะเบียนบ้าน</h4>
                                <p class="upload-subtitle">คลิกหรือลากไฟล์มาวางที่นี่</p>
                                <div class="upload-formats">
                                    <span class="format-badge">JPG</span>
                                    <span class="format-badge">PNG</span>
                                    <span class="format-badge">PDF</span>
                                </div>
                                <p class="upload-limit">ไฟล์ไม่เกิน 5MB</p>
                            </div>
                            
                            <!-- Preview Zone -->
                            <div class="apple-preview-zone" id="housePreviewZone" style="display: none;">
                                <div class="preview-header">
                                    <div class="preview-info">
                                        <svg class="file-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/>
                                            <polyline points="13 2 13 9 20 9"/>
                                        </svg>
                                        <div class="file-details">
                                            <p class="file-name-preview" id="houseFileNamePreview">filename.jpg</p>
                                            <p class="file-size" id="houseFileSizePreview">2.5 MB</p>
                                        </div>
                                    </div>
                                    <button type="button" class="remove-file-btn" id="houseRemoveBtn">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="18" y1="6" x2="6" y2="18"/>
                                            <line x1="6" y1="6" x2="18" y2="18"/>
                                        </svg>
                                    </button>
                                </div>
                                <div class="preview-content" id="housePreviewContent"></div>
                                <div class="preview-success">
                                    <svg class="checkmark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    <span>อัพโหลดสำเร็จ</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- ส่วนที่ 7: หลักฐานการชำระมัดจำ -->
                <h3 class="section-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg> หลักฐานการชำระมัดจำ</h3>
                
            
                
                <div class="form-group file-upload-group">
                    <label>หลักฐานการโอนเงิน</label>
                    <div class="apple-upload-container" id="appleUploadContainer">
                        <input type="file" name="pay_proof" accept="image/*,.pdf" id="payProofFile" hidden required>
                        
                        <!-- Upload Zone -->
                        <div class="apple-upload-zone" id="uploadZone">
                            <div class="upload-icon-wrapper">
                                <svg class="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                                    <polyline points="17 8 12 3 7 8"/>
                                    <line x1="12" y1="3" x2="12" y2="15"/>
                                </svg>
                            </div>
                            <h4 class="upload-title">อัพโหลดสลิปโอนเงิน</h4>
                            <p class="upload-subtitle">คลิกเพื่อเลือกไฟล์ หรือลากไฟล์มาวางที่นี่</p>
                            <div class="upload-formats">
                                <span class="format-badge">JPG</span>
                                <span class="format-badge">PNG</span>
                                <span class="format-badge">PDF</span>
                            </div>
                            <p class="upload-limit">ขนาดไฟล์ไม่เกิน 5MB</p>
                        </div>
                        
                        <!-- Preview Zone -->
                        <div class="apple-preview-zone" id="previewZone" style="display: none;">
                            <div class="preview-header">
                                <div class="preview-info">
                                    <svg class="file-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/>
                                        <polyline points="13 2 13 9 20 9"/>
                                    </svg>
                                    <div class="file-details">
                                        <p class="file-name-preview" id="fileNamePreview">filename.jpg</p>
                                        <p class="file-size" id="fileSizePreview">2.5 MB</p>
                                    </div>
                                </div>
                                <button type="button" class="remove-file-btn" id="removeFileBtn" aria-label="ลบไฟล์">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="18" y1="6" x2="6" y2="18"/>
                                        <line x1="6" y1="6" x2="18" y2="18"/>
                                    </svg>
                                </button>
                            </div>
                            <div class="preview-content" id="previewContent">
                                <!-- Image/PDF preview will be inserted here -->
                            </div>
                            <div class="preview-success">
                                <svg class="checkmark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                <span>อัพโหลดสำเร็จ</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Information Section -->
                <?php if (!empty($bankName) || !empty($bankAccountName) || !empty($bankAccountNumber) || !empty($promptpayNumber)): ?>
                <div class="payment-info-section">
                    <div class="payment-header">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                            <line x1="1" y1="10" x2="23" y2="10"/>
                            <circle cx="6" cy="15" r="1.5"/>
                        </svg>
                        <h3>ข้อมูลการโอนเงินมัดจำ</h3>
                    </div>
                    
                    <div class="payment-info-grid" style="<?php echo !empty($promptpayNumber) ? 'grid-template-columns: 1fr 1fr;' : ''; ?>">
                        <div class="payment-info-item">
                            <span class="label">เงินมัดจำ:</span>
                            <span class="value">฿<?php echo number_format($defaultDeposit); ?> บาท</span>
                        </div>
                        <?php if (!empty($bankName)): ?>
                        <div class="payment-info-item">
                            <span class="label">ธนาคาร:</span>
                            <span class="value"><?php echo htmlspecialchars($bankName); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($bankAccountName)): ?>
                        <div class="payment-info-item">
                            <span class="label">ชื่อบัญชี:</span>
                            <span class="value"><?php echo htmlspecialchars($bankAccountName); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($bankAccountNumber)): ?>
                        <div class="payment-info-item">
                            <span class="label">เลขบัญชี:</span>
                            <span class="value"><?php echo htmlspecialchars($bankAccountNumber); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($promptpayNumber)): ?>
                        <!-- PromptPay Section -->
                        <div class="promptpay-section" style="grid-column: 1 / -1; margin-top: 20px; padding: 20px; background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(251, 191, 36, 0.05) 100%); border-radius: 12px; border: 1px solid rgba(245, 158, 11, 0.2);">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2">
                                    <rect x="2" y="5" width="20" height="14" rx="2"/>
                                    <path d="M2 10h20"/>
                                    <circle cx="7" cy="15" r="1" fill="#f59e0b"/>
                                    <circle cx="12" cy="15" r="1" fill="#f59e0b"/>
                                </svg>
                                <h4 style="color: #fbbf24; font-size: 1.1rem; margin: 0;">สแกน QR Code พร้อมเพย์</h4>
                            </div>
                            
                            <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                                <div style="flex-shrink: 0;">
                                    <div style="background: white; padding: 15px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                                        <img src="https://promptpay.io/<?php echo urlencode($promptpayNumber); ?>/<?php echo $defaultDeposit; ?>.png" 
                                             alt="PromptPay QR Code" 
                                             style="width: 200px; height: 200px; display: block;">
                                    </div>
                                    <div style="text-align: center; margin-top: 10px; padding: 8px; background: rgba(245, 158, 11, 0.15); border-radius: 8px;">
                                        <div style="font-size: 0.75rem; color: #94a3b8; margin-bottom: 3px;">หมายเลขพร้อมเพย์</div>
                                        <div style="color: #fbbf24; font-weight: 600; font-size: 1rem; font-family: monospace;"><?php echo htmlspecialchars($promptpayNumber); ?></div>
                                    </div>
                                </div>
                                
                                <div style="flex: 1; min-width: 250px;">
                                    <div style="background: rgba(10, 10, 15, 0.5); padding: 15px; border-radius: 10px; margin-bottom: 15px;">
                                        <div style="color: #94a3b8; font-size: 0.85rem; margin-bottom: 8px;">💡 วิธีชำระเงิน:</div>
                                        <ol style="color: #e2e8f0; font-size: 0.9rem; line-height: 1.8; margin: 0; padding-left: 20px;">
                                            <li>เปิดแอปธนาคารของคุณ</li>
                                            <li>เลือก "สแกน QR Code"</li>
                                            <li>สแกน QR Code ด้านซ้าย</li>
                                            <li>ตรวจสอบยอดเงิน <strong style="color: #fbbf24;">฿<?php echo number_format($defaultDeposit); ?></strong></li>
                                            <li>ยืนยันการโอน</li>
                                        </ol>
                                    </div>
                                    <div style="padding: 12px; background: rgba(245, 158, 11, 0.1); border-left: 3px solid #f59e0b; border-radius: 6px;">
                                        <div style="color: #fbbf24; font-size: 0.85rem; display: flex; align-items: center; gap: 8px;">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="10"/>
                                                <line x1="12" y1="16" x2="12" y2="12"/>
                                                <line x1="12" y1="8" x2="12.01" y2="8"/>
                                            </svg>
                                            <span>QR Code นี้มีจำนวนเงินฝังอยู่แล้ว ไม่ต้องใส่จำนวนเงินเอง</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Terms & Conditions Section -->
                <div class="terms-section">
                    <div class="terms-header">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2c5.523 0 10 4.477 10 10s-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2z"/>
                            <path d="M12 6v6l4 2"/>
                        </svg>
                        <h3>เงื่อนไขการจองห้องพัก</h3>
                    </div>
                    
                    <div class="terms-content">
                        <div class="term-item">
                            <div class="term-icon">
                                <svg class="term-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <path d="M12 6v6m-3-3h6"/>
                                    <circle cx="12" cy="12" r="2.5" fill="currentColor"/>
                                </svg>
                            </div>
                            <div class="term-text">
                                <strong>ค่าห้องพักเริ่มจากวันที่เข้าพัก</strong>
                                <p>ค่าห้องพักจะเริ่มคิดตั้งแต่วันที่เข้าพัก (Check-in Date) ที่ท่านระบุไว้ โดยไม่มีการคืนค่าเช่า แม้ว่าท่านจะเข้าพักช้ากว่าวันที่ระบุก็ตาม</p>
                            </div>
                        </div>
                        
                        <div class="term-item">
                            <div class="term-icon">
                                <svg class="term-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                            </div>
                            <div class="term-text">
                                <strong>กำหนดเวลาชำระมัดจำ</strong>
                                <p>ท่านต้องชำระเงินมัดจำภายใน <strong>7 วัน</strong> นับจากการยืนยันการจอง หากไม่ชำระในกำหนดเวลา การจองอาจถูกยกเลิกโดยอัตโนมัติ</p>
                            </div>
                        </div>
                        
                        <div class="term-item">
                            <div class="term-icon">
                                <svg class="term-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <line x1="16" y1="13" x2="8" y2="13"/>
                                    <line x1="16" y1="17" x2="8" y2="17"/>
                                </svg>
                            </div>
                            <div class="term-text">
                                <strong>สัญญามีผล</strong>
                                <p>สัญญาการเช่าห้องพักจะมีผลใช้บังคับ เมื่อท่านชำระเงินมัดจำครบถ้วนแล้ว</p>
                            </div>
                        </div>
                    </div>
                    
                    <label class="terms-checkbox">
                        <input type="checkbox" name="accept_terms" id="acceptTerms" checked required>
                        <span>ฉันยอมรับเงื่อนไขการจองห้องพัก</span>
                    </label>
                    <div class="error-message" id="termsError" style="display: none;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        <span>กรุณายอมรับเงื่อนไขการจอง</span>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit" id="submitBtn">
                    <span class="btn-text">
                        <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M9 16l2 2 4-4"/></svg>
                        ยืนยันการจองห้องพัก
                    </span>
                    <div class="loading-spinner"></div>
                </button>
            </form>
        </div>
        
        <!-- Toast notification container -->
        <div class="toast" id="toast">
            <span class="toast-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="20 6 9 17 4 12"></polyline></svg></span>
            <span class="toast-message">Message</span>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Header scroll effect
        window.addEventListener('scroll', function() {
            const header = document.getElementById('header');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // ═══════════════════════════════════════════════════════
        // Apple-Style Validation System
        // ═══════════════════════════════════════════════════════
        
        // Toast notification
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            if (!toast) return;
            
            const msgEl = toast.querySelector('.toast-message');
            const iconEl = toast.querySelector('.toast-icon');
            
            toast.className = 'toast ' + type;
            msgEl.textContent = message;
            
            // Update icon based on type
            const icons = {
                success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="20 6 9 17 4 12"></polyline></svg>',
                error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>',
                warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>'
            };
            iconEl.innerHTML = icons[type] || icons.success;
            
            // Show toast
            requestAnimationFrame(() => {
                toast.classList.add('show');
            });
            
            // Hide after delay
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }
        
        // Validation rules
        const validators = {
            name: {
                validate: (val) => val.trim().length >= 4,
                message: 'กรุณากรอกชื่อ-นามสกุล (อย่างน้อย 4 ตัวอักษร)'
            },
            phone: {
                validate: (val) => /^[0-9]{10}$/.test(val.replace(/[^0-9]/g, '')),
                message: 'กรุณากรอกเบอร์โทรศัพท์ 10 หลัก'
            },
            email: {
                validate: (val) => !val || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val),
                message: 'กรุณากรอกอีเมลที่ถูกต้อง'
            },
            id_card: {
                validate: (val) => /^[0-9]{13}$/.test(val.replace(/[^0-9]/g, '')),
                message: 'กรุณากรอกเลขบัตรประชาชน 13 หลัก'
            }
        };
        
        // Form elements
        const form = document.getElementById('bookingForm');
        const submitBtn = document.getElementById('submitBtn');
        const roomError = document.getElementById('roomError');
        
        if (form) {
            // Room selection
            function selectRoom(radio) {
                document.querySelectorAll('.room-option').forEach(o => o.classList.remove('selected'));
                const card = radio.closest('.room-option');
                if (card) card.classList.add('selected');
                radio.checked = true;
                if (roomError) roomError.classList.remove('show');
            }
            
            // Attach room listeners
            document.querySelectorAll('input[name="room_id"]').forEach(radio => {
                radio.addEventListener('change', () => selectRoom(radio));
                
                const label = radio.closest('.room-option');
                if (label) {
                    label.addEventListener('click', (e) => {
                        if (e.target !== radio) {
                            e.preventDefault();
                            selectRoom(radio);
                        }
                    });
                }
            });
            
            // Validate single field
            function validateField(input, showError = false) {
                const group = input.closest('.form-group');
                const fieldName = input.name;
                const value = input.value.trim();
                const validator = validators[fieldName];
                
                if (!group) return true;
                
                // Optional empty field
                if (!value && !input.required) {
                    group.classList.remove('has-error', 'is-valid');
                    return true;
                }
                
                // Required empty field
                if (!value && input.required) {
                    if (showError) {
                        group.classList.add('has-error');
                        group.classList.remove('is-valid');
                    }
                    return false;
                }
                
                // Has value, check validation
                const isValid = !validator || validator.validate(value);
                
                if (isValid) {
                    group.classList.remove('has-error');
                    group.classList.add('is-valid');
                } else if (showError || group.classList.contains('is-valid')) {
                    group.classList.add('has-error');
                    group.classList.remove('is-valid');
                }
                
                return isValid;
            }
            
            // Validate all fields
            function validateAll(showErrors = false) {
                let allValid = true;
                
                // Check room
                const roomSelected = form.querySelector('input[name="room_id"]:checked');
                if (!roomSelected) {
                    allValid = false;
                    if (showErrors && roomError) roomError.classList.add('show');
                }
                
                // Check required text fields
                form.querySelectorAll('input[required][name]:not([type="radio"]):not([type="file"]), textarea[required][name]').forEach(input => {
                    if (!validateField(input, showErrors)) {
                        allValid = false;
                    }
                });
                
                // Check required file uploads
                // ต้องเช็คทั้ง input.files และ preview zone (สำหรับไฟล์ที่โหลดจาก localStorage)
                form.querySelectorAll('input[required][type="file"]').forEach(input => {
                    const hasRealFile = input.files && input.files.length > 0;
                    const container = input.closest('.apple-upload-container');
                    const previewZone = container ? container.querySelector('.apple-preview-zone') : null;
                    const hasPreview = previewZone && previewZone.style.display !== 'none' && previewZone.querySelector('img');
                    
                    // ถ้าไม่มี file จริง และไม่มี preview = ยังไม่ได้อัพโหลด
                    if (!hasRealFile && !hasPreview) {
                        allValid = false;
                        if (showErrors) {
                            const group = input.closest('.file-upload-group');
                            if (group) {
                                group.classList.add('has-error');
                            }
                        }
                    } else {
                        // มีไฟล์แล้ว ลบ error state
                        const group = input.closest('.file-upload-group');
                        if (group) {
                            group.classList.remove('has-error');
                        }
                    }
                });
                
                // Check if address (hidden field) has value
                const addrProvinceField = form.querySelector('input[name="addr_province"]');
                if (addrProvinceField && addrProvinceField.hasAttribute('required')) {
                    if (!addrProvinceField.value) {
                        allValid = false;
                    }
                }
                
                return allValid;
            }
            
            // Update submit button state (ให้ปุ่มกดได้ตลอด)
            function updateSubmitState() {
                // ไม่ได้ปิดปุ่มแล้ว ให้ user กดได้ตลอดแล้ว validate เมื่อกด submit
            }
            
            // Attach field listeners
            form.querySelectorAll('input[required][name]:not([type="radio"]):not([type="file"]), textarea[required][name]').forEach(input => {
                // Real-time validation on input
                input.addEventListener('input', () => {
                    const group = input.closest('.form-group');
                    // Only show valid state while typing, don't show error until blur
                    if (group?.classList.contains('has-error')) {
                        validateField(input, true);
                    } else {
                        validateField(input, false);
                    }
                });
                
                // Full validation on blur
                input.addEventListener('blur', () => {
                    if (input.value.trim() || input.required) {
                        validateField(input, true);
                    }
                });
                
                // Format phone number
                if (input.name === 'phone') {
                    input.addEventListener('input', () => {
                        input.value = input.value.replace(/[^0-9]/g, '').slice(0, 10);
                    });
                }
                
                // Format ID card
                if (input.name === 'id_card') {
                    input.addEventListener('input', () => {
                        input.value = input.value.replace(/[^0-9]/g, '').slice(0, 13);
                    });
                }
            });
            
            // Form submit
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Combine name fields before validation
                if (typeof combineNameFields === 'function') {
                    combineNameFields();
                }
                
                // Combine address fields before validation
                if (typeof combineAddress === 'function') {
                    combineAddress();
                }
                
                // Save form data before validation to prevent data loss
                if (typeof saveFormData === 'function') {
                    saveFormData();
                }
                
                // Validate all with errors shown
                if (!validateAll(true)) {
                    // Find what's missing and show specific message
                    let missingFields = [];
                    
                    if (!form.querySelector('input[name="room_id"]:checked')) {
                        missingFields.push('ห้องพัก');
                    }
                    
                    // Check text fields that are actually empty (not just has-error class)
                    const requiredTextFields = {
                        'id_card': 'เลขประจำตัว 13 หลัก',
                        'name': 'ชื่อ-นามสกุล',
                        'phone': 'เบอร์โทรศัพท์',
                        'ctr_start_month': 'เดือนที่เริ่มเข้าพัก',
                        'ctr_duration': 'ระยะเวลาสัญญา'
                    };
                    
                    Object.keys(requiredTextFields).forEach(fieldName => {
                        const input = form.querySelector(`input[name="${fieldName}"], select[name="${fieldName}"]`);
                        if (input && input.hasAttribute('required')) {
                            const value = input.value.trim();
                            
                            // Validate based on field type
                            let isValid = false;
                            if (fieldName === 'id_card') {
                                // ID card must be 13 digits
                                isValid = /^[0-9]{13}$/.test(value.replace(/[^0-9]/g, ''));
                            } else if (fieldName === 'phone') {
                                // Phone must be 10 digits
                                isValid = /^[0-9]{10}$/.test(value.replace(/[^0-9]/g, ''));
                            } else {
                                // Other fields just need to be non-empty
                                isValid = value.length > 0;
                            }
                            
                            if (!isValid) {
                                missingFields.push(requiredTextFields[fieldName]);
                            }
                        }
                    });
                    
                    const fileInputs = ['idcard_copy', 'house_copy', 'pay_proof'];
                    fileInputs.forEach(name => {
                        const input = form.querySelector(`input[name="${name}"]`);
                        if (input && input.hasAttribute('required')) {
                            const hasRealFile = input.files && input.files.length > 0;
                            const container = input.closest('.apple-upload-container');
                            const previewZone = container ? container.querySelector('.apple-preview-zone') : null;
                            const hasPreview = previewZone && previewZone.style.display !== 'none' && previewZone.querySelector('img');
                            
                            if (!hasRealFile && !hasPreview) {
                                if (name === 'idcard_copy') missingFields.push('สำเนาบัตรประชาชน');
                                if (name === 'house_copy') missingFields.push('สำเนาทะเบียนบ้าน');
                                if (name === 'pay_proof') missingFields.push('หลักฐานการชำระมัดจำ');
                            }
                        }
                    });
                    
                    const addrProvinceField = form.querySelector('input[name="addr_province"]');
                    if (addrProvinceField && addrProvinceField.hasAttribute('required') && !addrProvinceField.value) {
                        missingFields.push('ที่อยู่');
                    }
                    
                    // Find first error and scroll to it
                    const firstError = form.querySelector('.form-group.has-error, .room-selection-error.show, .file-upload-group.has-error');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    
                    // Remove duplicates from missingFields
                    const uniqueFields = [...new Set(missingFields)];
                    
                    const message = uniqueFields.length > 0 
                        ? `กรุณากรอก: ${uniqueFields.join(', ')}`
                        : 'กรุณากรอกข้อมูลให้ครบถ้วน';
                    
                    showToast(message, 'warning');
                    return;
                }
                
                // Show loading state
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
                
                // เรียก combine functions อีกครั้งก่อนสร้าง FormData เพื่อให้แน่ใจว่าข้อมูลถูกต้อง
                if (typeof combineNameFields === 'function') {
                    combineNameFields();
                }
                if (typeof combineAddress === 'function') {
                    combineAddress();
                }
                
                // Debug: ตรวจสอบค่าก่อนส่ง
                console.log('Form values before submit:', {
                    id_card: form.querySelector('input[name="id_card"]')?.value,
                    name: form.querySelector('input[name="name"]')?.value,
                    phone: form.querySelector('input[name="phone"]')?.value,
                    firstName: document.getElementById('firstName')?.value,
                    lastName: document.getElementById('lastName')?.value
                });
                
                // Submit via AJAX to prevent page reload
                const formData = new FormData(form);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    // Parse response to check for success or error
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    // Check if there's a success message in the response
                    const successBox = doc.querySelector('.success-box');
                    const errorAlert = doc.querySelector('.alert-error');
                    
                    if (successBox) {
                        // Success - show success message
                        showToast('จองห้องพักสำเร็จ!', 'success');
                        
                        // Clear localStorage และ form data เฉพาะเมื่อสำเร็จ
                        localStorage.removeItem('bookingForm_payProof');
                        localStorage.removeItem('bookingForm_idcard');
                        localStorage.removeItem('bookingForm_house');
                        if (typeof clearFormData === 'function') {
                            clearFormData();
                        }
                        
                        // Replace the form content with success box
                        const bookingForm = document.querySelector('.booking-form');
                        if (bookingForm && successBox.parentElement) {
                            bookingForm.innerHTML = successBox.parentElement.innerHTML;
                        }
                        
                        // Scroll to top
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        
                    } else if (errorAlert) {
                        // Error from server - ไม่ล้างข้อมูล ให้ user แก้ไขได้
                        const errorText = errorAlert.textContent || 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง';
                        showToast(errorText.trim(), 'error');
                        submitBtn.classList.remove('loading');
                        submitBtn.disabled = false;
                    } else {
                        // Unknown response - ไม่ reload ให้แสดง error แทน
                        showToast('เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุ กรุณาลองใหม่อีกครั้ง', 'error');
                        submitBtn.classList.remove('loading');
                        submitBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Submit error:', error);
                    showToast('เกิดข้อผิดพลาดในการส่งข้อมูล กรุณาลองใหม่อีกครั้ง', 'error');
                    submitBtn.classList.remove('loading');
                    submitBtn.disabled = false;
                });
            });
            
            // ปุ่มกดได้ตลอด ไม่ต้อง initial state update
        }
        
        // File upload handlers
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function() {
                const fileNameSpan = document.getElementById(this.id + 'Name');
                if (fileNameSpan && this.files.length > 0) {
                    fileNameSpan.textContent = this.files[0].name;
                    fileNameSpan.classList.add('has-file');
                } else if (fileNameSpan) {
                    fileNameSpan.textContent = 'ยังไม่ได้เลือกไฟล์';
                    fileNameSpan.classList.remove('has-file');
                }
            });
        });
        
        // Update total payment when deposit changes
        const depositInput = document.getElementById('depositAmount');
        const totalPaymentSpan = document.getElementById('totalPayment');
        if (depositInput && totalPaymentSpan) {
            depositInput.addEventListener('input', function() {
                const value = parseInt(this.value) || 0;
                totalPaymentSpan.textContent = value.toLocaleString();
            });
        }
        
        // Auto-set end date when start date is selected (default 6 months)
        const startDateInput = document.querySelector('input[name="ctr_start"]');
        const endDateInput = document.querySelector('input[name="ctr_end"]');
        if (startDateInput && endDateInput) {
            startDateInput.addEventListener('change', function() {
                if (this.value && !endDateInput.value) {
                    const start = new Date(this.value);
                    start.setMonth(start.getMonth() + 6);
                    endDateInput.value = start.toISOString().split('T')[0];
                }
            });
        }
        
        // Thai Address Data (ข้อมูลที่อยู่ประเทศไทย)
        let thaiAddressData = null;
        let allSubdistricts = [];
        let currentMode = 'search';
        
        // ข้อมูลสถานศึกษาในประเทศไทย
        const thaiUniversities = [
            // มหาวิทยาลัยของรัฐ
            'จุฬาลงกรณ์มหาวิทยาลัย', 'มหาวิทยาลัยเชียงใหม่', 'มหาวิทยาลัยขอนแก่น', 'มหาวิทยาลัยมหิดล', 'มหาวิทยาลัยธรรมศาสตร์',
            'มหาวิทยาลัยเกษตรศาสตร์', 'มหาวิทยาลัยศิลปากร', 'มหาวิทยาลัยสงขลานครินทร์', 'มหาวิทยาลัยบูรพา', 'มหาวิทยาลัยนเรศวร',
            'มหาวิทยาลัยแม่โจ้', 'มหาวิทยาลัยเทคโนโลยีสุรนารี', 'มหาวิทยาลัยวลัยลักษณ์', 'มหาวิทยาลัยมหาสารคาม', 'มหาวิทยาลัยทักษิณ',
            'มหาวิทยาลัยอุบลราชธานี', 'มหาวิทยาลัยราชภัฏ', 'มหาวิทยาลัยราชภัฏเชียงใหม่', 'มหาวิทยาลัยราชภัฏเชียงราย', 'มหาวิทยาลัยราชภัฏนครราชสีมา',
            'มหาวิทยาลัยราชภัฏอุบลราชธานี', 'มหาวิทยาลัยราชภัฏสุรินทร์', 'มหาวิทยาลัยราชภัฏมหาสารคาม', 'มหาวิทยาลัยราชภัฏขอนแก่น',
            'มหาวิทยาลัยราชภัฏพิบูลสงคราม', 'มหาวิทยาลัยราชภัฏกำแพงเพชร', 'มหาวิทยาลัยราชภัฏนครสวรรค์', 'มหาวิทยาลัยราชภัฏลำปาง',
            'มหาวิทยาลัยราชภัฏอุตรดิตถ์', 'มหาวิทยาลัยราชภัฏเพชรบูรณ์', 'มหาวิทยาลัยราชภัฏเทพสตรี', 'มหาวิทยาลัยราชภัฏภูเก็ต',
            'มหาวิทยาลัยราชภัฏสงขลา', 'มหาวิทยาลัยราชภัฏยะลา', 'มหาวิทยาลัยราชภัฏนครศรีธรรมราช', 'มหาวิทยาลัยราชภัฏสุราษฎร์ธานี',
            'สถาบันเทคโนโลยีพระจอมเกล้าเจ้าคุณทหารลาดกระบัง', 'สถาบันเทคโนโลยีพระจอมเกล้าพระนครเหนือ', 'สถาบันเทคโนโลยีพระจอมเกล้าเจ้าคุณทหารลาดกระบัง',
            'มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าธนบุรี', 'มหาวิทยาลัยเทคโนโลยีราชมงคลธัญบุรี', 'มหาวิทยาลัยเทคโนโลยีราชมงคลกรุงเทพ',
            'มหาวิทยาลัยเทคโนโลยีราชมงคลพระนคร', 'มหาวิทยาลัยเทคโนโลยีราชมงคลตะวันออก', 'มหาวิทยาลัยเทคโนโลยีราชมงคลล้านนา',
            'มหาวิทยาลัยเทคโนโลยีราชมงคลอีสาน', 'มหาวิทยาลัยเทคโนโลยีราชมงคลศรีวิชัย', 'มหาวิทยาลัยเทคโนโลยีราชมงคลรัตนโกสินทร์',
            'มหาวิทยาลัยเทคโนโลยีราชมงคลสุวรรณภูมิ', 'มหาวิทยาลัยพะเยา', 'มหาวิทยาลัยแม่ฟ้าหลวง', 'มหาวิทยาลัยนราธิวาสราชนครินทร์',
            'มหาวิทยาลัยราชธานี', 'มหาวิทยาลัยกาฬสินธุ์', 'มหาวิทยาลัยบุรีรัมย์', 'มหาวิทยาลัยนครพนม',
            // มหาวิทยาลัยเอกชน
            'มหาวิทยาลัยกรุงเทพ', 'มหาวิทยาลัยหอการค้าไทย', 'มหาวิทยาลัยอัสสัมชัญ', 'มหาวิทยาลัยรังสิต', 'มหาวิทยาลัยศรีปทุม',
            'มหาวิทยาลัยกรุงเทพธนบุรี', 'มหาวิทยาลัยธุรกิจบัณฑิตย์', 'มหาวิทยาลัยเกริก', 'มหาวิทยาลัยสยาม', 'มหาวิทยาลัยหัวเฉียวเฉลิมพระเกียรติ',
            'มหาวิทยาลัยเซนต์จอห์น', 'มหาวิทยาลัยเทคโนโลยีมหานคร', 'มหาวิทยาลัยฟาร์อีสเทอร์น', 'มหาวิทยาลัยเอเชียอาคเนย์', 'มหาวิทยาลัยราชพฤกษ์',
            'มหาวิทยาลัยศรีนครินทรวิโรฒ', 'มหาวิทยาลัยเชียงใหม่ มหาวิทยาลัยเชียงใหม่', 'มหาวิทยาลัยพายัพ', 'มหาวิทยาลัยแม่ฟ้าหลวง',
            // วิทยาลัย
            'วิทยาลัยเทคนิคกรุงเทพ', 'วิทยาลัยเทคนิคเชียงใหม่', 'วิทยาลัยเทคนิคขอนแก่น', 'วิทยาลัยเทคนิคนครราชสีมา', 'วิทยาลัยเทคนิคอุบลราชธานี',
            'วิทยาลัยเทคนิคสงขลา', 'วิทยาลัยเทคนิคภูเก็ต', 'วิทยาลัยเทคโนโลยีพณิชยการราชดำเนิน', 'วิทยาลัยอาชีวศึกษา',
            'วิทยาลัยการอาชีพ', 'วิทยาลัยเกษตรและเทคโนโลยี', 'วิทยาลัยพยาบาล', 'วิทยาลัยสารพัดช่าง'
        ];
        
        const thaiFaculties = [
            // คณะวิทยาศาสตร์และเทคโนโลยี
            'คณะวิทยาศาสตร์', 'คณะวิศวกรรมศาสตร์', 'คณะเทคโนโลยีสารสนเทศ', 'คณะวิทยาศาสตร์และเทคโนโลยี', 'คณะเทคโนโลยีการเกษตร',
            'คณะสถาปัตยกรรมศาสตร์', 'คณะเทคโนโลยีอุตสาหกรรม', 'คณะวิทยาการคณนา', 'คณะวิทยาศาสตร์ประยุกต์',
            // คณะสังคมศาสตร์และมนุษยศาสตร์
            'คณะมนุษยศาสตร์', 'คณะมนุษยศาสตร์และสังคมศาสตร์', 'คณะสังคมศาสตร์', 'คณะรัฐศาสตร์', 'คณะนิติศาสตร์', 'คณะเศรษฐศาสตร์',
            'คณะศิลปศาสตร์', 'คณะอักษรศาสตร์', 'คณะโบราณคดี', 'คณะปรัชญา',
            // คณะบริหารธุรกิจและบัญชี
            'คณะบริหารธุรกิจ', 'คณะพาณิชยศาสตร์และการบัญชี', 'คณะการบัญชี', 'คณะบัญชี', 'คณะเศรษฐศาสตร์ประยุกต์',
            // คณะการศึกษา
            'คณะครุศาสตร์', 'คณะศึกษาศาสตร์', 'คณะศึกษาศาสตร์และพัฒนศาสตร์', 'คณะพลศึกษา',
            // คณะแพทยศาสตร์และสาธารณสุข
            'คณะแพทยศาสตร์', 'คณะทันตแพทยศาสตร์', 'คณะเภสัชศาสตร์', 'คณะสาธารณสุขศาสตร์', 'คณะพยาบาลศาสตร์',
            'คณะสัตวแพทยศาสตร์', 'คณะเทคนิคการแพทย์', 'คณะกายภาพบำบัด', 'คณะวิทยาศาสตร์การแพทย์',
            // คณะอื่นๆ
            'คณะนิเทศศาสตร์', 'คณะวารสารศาสตร์และสื่อสารมวลชน', 'คณะศิลปกรรมศาสตร์', 'คณะดนตรี', 'คณะนาฏศิลป์',
            'คณะการท่องเที่ยวและการโรงแรม', 'คณะการจัดการการท่องเที่ยว', 'คณะวิทยาการจัดการ', 'คณะสิ่งแวดล้อมและทรัพยากรศาสตร์',
            'คณะเกษตรศาสตร์', 'คณะประมง', 'คณะวนศาสตร์', 'คณะอุตสาหกรรมเกษตร', 'คณะสังคมวิทยาและมานุษยวิทยา',
            'คณะจิตวิทยา', 'คณะรัฐประศาสนศาสตร์', 'คณะเทคโนโลยีการจัดการ', 'คณะโลจิสติกส์', 'คณะการบินและอวกาศ'
        ];
        
        // ข้อมูลหมู่ ซอย ถนน ที่ใช้บ่อยในไทย
        const commonMoo = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20'];
        const commonSoi = [
            'สุขุมวิท', 'ลาดพร้าว', 'พระราม 2', 'พระราม 3', 'พระราม 4', 'พระราม 5', 'พระราม 6', 'พระราม 9',
            'รัชดาภิเษก', 'เพชรบุรี', 'สาทร', 'สีลม', 'วิภาวดีรังสิต', 'บางนา-ตราด', 'รามอินทรา',
            'ประชาชื่น', 'งามวงศ์วาน', 'พหลโยธิน', 'เจริญกรุง', 'บรมราชชนนี', 'กาญจนาภิเษก',
            'พัฒนาการ', 'บางแค', 'อ่อนนุช', 'อุดมสุข', 'ทองหล่อ', 'เอกมัย', 'พร้อมพงษ์', 'อารีย์',
            'สะพานควาย', 'นวมินทร์', 'ศรีนครินทร์', 'วุฒากาศ', 'สุขสวัสดิ์', 'ประชาอุทิศ'
        ];
        const commonRoads = [
            'สุขุมวิท', 'ลาดพร้าว', 'พระราม 2', 'พระราม 3', 'พระราม 4', 'พระราม 5', 'พระราม 6', 'พระราม 9',
            'รัชดาภิเษก', 'เพชรบุรี', 'สาทร', 'สีลม', 'วิภาวดีรังสิต', 'บางนา-ตราด', 'รามอินทรา',
            'ประชาชื่น', 'งามวงศ์วาน', 'พหลโยธิน', 'เจริญกรุง', 'บรมราชชนนี', 'กาญจนาภิเษก',
            'พัฒนาการ', 'บางแค', 'อ่อนนุช', 'ศรีนครินทร์', 'นวมินทร์', 'รามคำแหง', 'เสรีไทย',
            'วุฒากาศ', 'สุขสวัสดิ์', 'ประชาอุทิศ', 'พญาไท', 'ราชดำริ', 'ราชวิถี', 'เจริญนคร',
            'บรมราชชนนี', 'ติวานนท์', 'จรัญสนิทวงศ์', 'เพชรเกษม', 'บางขุนเทียน', 'เศรษฐกิจ 1',
            'ลพบุรีราเมศวร์', 'นิพัทธ์สงเคราะห์', 'พระสุเมรุ', 'เจ้าฟ้า', 'ดิบุก', 'ตะกั่วป่า',
            'ราษฎร์อุทิศ', 'นิมมานเหมินท์', 'ห้วยแก้ว', 'สุเทพ', 'มหิดล', 'สุโขทัย'
        ];
        
        // ดึงข้อมูลที่อยู่ประเทศไทย
        async function loadThaiAddressData() {
            try {
                const response = await fetch('../thai_provinces.json');
                const data = await response.json();
                thaiAddressData = data.provinces;
                
                // สร้าง flat list ของตำบลทั้งหมด พร้อมข้อมูลอำเภอและจังหวัด
                allSubdistricts = [];
                thaiAddressData.forEach(province => {
                    province.districts.forEach(district => {
                        district.subdistricts.forEach(subdistrict => {
                            allSubdistricts.push({
                                subdistrict: subdistrict.name_th,
                                district: district.name_th,
                                province: province.name_th,
                                zipcode: subdistrict.zipcode,
                                provinceId: province.id,
                                districtId: district.id
                            });
                        });
                    });
                });
                
                setupModeToggle();
                setupAutocomplete();
                setupDropdownMode();
                console.log('✓ Thai address data loaded:', allSubdistricts.length, 'ตำบล');
                
                // Setup autocomplete for Moo, Soi, Road after data is loaded
                setupAddressFieldsAutocomplete();
                
                // Setup autocomplete for Education and Faculty
                setupEducationAutocomplete();
                
                // Setup age input enhancements
                setupAgeInput();
            } catch (error) {
                console.error('Error loading Thai address data:', error);
            }
        }
        
        // Setup mode toggle buttons
        function setupModeToggle() {
            const searchBtn = document.getElementById('searchModeBtn');
            const dropdownBtn = document.getElementById('dropdownModeBtn');
            const searchMode = document.getElementById('searchMode');
            const dropdownMode = document.getElementById('dropdownMode');
            
            searchBtn.addEventListener('click', function() {
                currentMode = 'search';
                searchBtn.classList.add('active');
                dropdownBtn.classList.remove('active');
                searchMode.style.display = 'grid';
                dropdownMode.style.display = 'none';
                clearAllFields();
            });
            
            dropdownBtn.addEventListener('click', function() {
                currentMode = 'dropdown';
                dropdownBtn.classList.add('active');
                searchBtn.classList.remove('active');
                dropdownMode.style.display = 'grid';
                searchMode.style.display = 'none';
                clearAllFields();
            });
        }
        
        // Clear all address fields
        function clearAllFields() {
            // Hidden inputs
            document.getElementById('addrProvince').value = '';
            document.getElementById('addrDistrict').value = '';
            document.getElementById('addrSubdistrict').value = '';
            document.getElementById('addrZipcode').value = '';
            
            // Search mode
            document.getElementById('addrSubdistrictSearch').value = '';
            document.getElementById('addrDistrictDisplay').value = '';
            document.getElementById('addrProvinceDisplay').value = '';
            document.getElementById('addrZipcodeDisplay').value = '';
            
            // Dropdown mode
            document.getElementById('addrProvinceSelect').selectedIndex = 0;
            document.getElementById('addrDistrictSelect').selectedIndex = 0;
            document.getElementById('addrDistrictSelect').disabled = true;
            document.getElementById('addrSubdistrictSelect').selectedIndex = 0;
            document.getElementById('addrSubdistrictSelect').disabled = true;
            document.getElementById('addrZipcodeSelect').selectedIndex = 0;
            document.getElementById('addrZipcodeSelect').disabled = true;
            
            combineAddress();
        }
        
        // Setup dropdown mode
        function setupDropdownMode() {
            const provinceSelect = document.getElementById('addrProvinceSelect');
            const districtSelect = document.getElementById('addrDistrictSelect');
            const subdistrictSelect = document.getElementById('addrSubdistrictSelect');
            const zipcodeSelect = document.getElementById('addrZipcodeSelect');
            
            // จัดกลุ่มจังหวัดตามภาค
            const regions = {
                'กรุงเทพและปริมณฑล': ['กรุงเทพมหานคร', 'นนทบุรี', 'ปทุมธานี', 'สมุทรปราการ', 'สมุทรสาคร', 'นครปฐม'],
                'ภาคกลาง': ['พระนครศรีอยุธยา', 'อ่างทอง', 'ลพบุรี', 'สิงห์บุรี', 'ชัยนาท', 'สระบุรี', 'ฉะเชิงเทรา', 'ปราจีนบุรี', 'นครนายก', 'สมุทรสงคราม', 'สุพรรณบุรี'],
                'ภาคเหนือ': ['เชียงใหม่', 'เชียงราย', 'แม่ฮ่องสอน', 'ลำปาง', 'ลำพูน', 'อุตรดิตถ์', 'แพร่', 'น่าน', 'พะเยา', 'เพชรบูรณ์', 'พิษณุโลก', 'สุโขทัย', 'ตาก', 'กำแพงเพชร', 'นครสวรรค์', 'อุทัยธานี'],
                'ภาคตะวันออกเฉียงเหนือ': ['นครราชสีมา', 'บุรีรัมย์', 'สุรินทร์', 'ศรีสะเกษ', 'อุบลราชธานี', 'ยโสธร', 'ชัยภูมิ', 'อำนาจเจริญ', 'หนองบัวลำภู', 'ขอนแก่น', 'อุดรธานี', 'เลย', 'หนองคาย', 'มหาสารคาม', 'ร้อยเอ็ด', 'กาฬสินธุ์', 'สกลนคร', 'นครพนม', 'มุกดาหาร', 'บึงกาฬ'],
                'ภาคตะวันออก': ['ชลบุรี', 'ระยอง', 'จันทบุรี', 'ตราด', 'สระแก้ว'],
                'ภาคตะวันตก': ['กาญจนบุรี', 'ราชบุรี', 'ประจวบคีรีขันธ์', 'เพชรบุรี', 'สมุทรสงคราม'],
                'ภาคใต้': ['นครศรีธรรมราช', 'กระบี่', 'พังงา', 'ภูเก็ต', 'สุราษฎร์ธานี', 'ระนอง', 'ชุมพร', 'สงขลา', 'สตูล', 'ตรัง', 'พัทลุง', 'ปัตตานี', 'ยะลา', 'นราธิวาส']
            };
            
            // Populate provinces
            for (const [regionName, provinceNames] of Object.entries(regions)) {
                const optgroup = document.createElement('optgroup');
                optgroup.label = regionName;
                provinceNames.forEach(provinceName => {
                    const province = thaiAddressData.find(p => p.name_th === provinceName);
                    if (province) {
                        const option = document.createElement('option');
                        option.value = province.name_th;
                        option.dataset.id = province.id;
                        option.textContent = province.name_th;
                        optgroup.appendChild(option);
                    }
                });
                provinceSelect.appendChild(optgroup);
            }
            
            // Province change
            provinceSelect.addEventListener('change', function() {
                districtSelect.innerHTML = '<option value="">-- เลือกอำเภอ/เขต --</option>';
                subdistrictSelect.innerHTML = '<option value="">-- เลือกตำบล/แขวง --</option>';
                zipcodeSelect.innerHTML = '<option value="">-- รหัสไปรษณีย์ --</option>';
                
                districtSelect.disabled = true;
                subdistrictSelect.disabled = true;
                zipcodeSelect.disabled = true;
                
                const selectedOption = this.options[this.selectedIndex];
                const provinceId = selectedOption?.dataset?.id;
                
                if (provinceId) {
                    const province = thaiAddressData.find(p => p.id == provinceId);
                    if (province) {
                        province.districts.forEach(district => {
                            const option = document.createElement('option');
                            option.value = district.name_th;
                            option.dataset.id = district.id;
                            option.textContent = district.name_th;
                            districtSelect.appendChild(option);
                        });
                        districtSelect.disabled = false;
                    }
                }
                updateHiddenFields();
            });
            
            // District change
            districtSelect.addEventListener('change', function() {
                subdistrictSelect.innerHTML = '<option value="">-- เลือกตำบล/แขวง --</option>';
                zipcodeSelect.innerHTML = '<option value="">-- รหัสไปรษณีย์ --</option>';
                subdistrictSelect.disabled = true;
                zipcodeSelect.disabled = true;
                
                const provinceId = provinceSelect.options[provinceSelect.selectedIndex]?.dataset?.id;
                const districtId = this.options[this.selectedIndex]?.dataset?.id;
                
                if (provinceId && districtId) {
                    const province = thaiAddressData.find(p => p.id == provinceId);
                    if (province) {
                        const district = province.districts.find(d => d.id == districtId);
                        if (district) {
                            district.subdistricts.forEach(subdistrict => {
                                const option = document.createElement('option');
                                option.value = subdistrict.name_th;
                                option.dataset.zipcode = subdistrict.zipcode;
                                option.textContent = subdistrict.name_th;
                                subdistrictSelect.appendChild(option);
                            });
                            subdistrictSelect.disabled = false;
                        }
                    }
                }
                updateHiddenFields();
            });
            
            // Subdistrict change
            subdistrictSelect.addEventListener('change', function() {
                zipcodeSelect.innerHTML = '<option value="">-- รหัสไปรษณีย์ --</option>';
                const zipcode = this.options[this.selectedIndex]?.dataset?.zipcode;
                if (zipcode) {
                    const option = document.createElement('option');
                    option.value = zipcode;
                    option.textContent = zipcode;
                    option.selected = true;
                    zipcodeSelect.appendChild(option);
                    zipcodeSelect.disabled = false;
                }
                updateHiddenFields();
            });
            
            function updateHiddenFields() {
                document.getElementById('addrProvince').value = provinceSelect.value;
                document.getElementById('addrDistrict').value = districtSelect.value;
                document.getElementById('addrSubdistrict').value = subdistrictSelect.value;
                document.getElementById('addrZipcode').value = zipcodeSelect.value;
                combineAddress();
            }
        }
        
        // Setup autocomplete for subdistrict (ค้นหาตำบล)
        function setupAutocomplete() {
            const subdistrictInput = document.getElementById('addrSubdistrictSearch');
            const districtDisplay = document.getElementById('addrDistrictDisplay');
            const provinceDisplay = document.getElementById('addrProvinceDisplay');
            const zipcodeDisplay = document.getElementById('addrZipcodeDisplay');
            const subdistrictList = document.getElementById('subdistrictList');
            
            if (!subdistrictInput) return;
            
            subdistrictInput.addEventListener('input', function() {
                const query = this.value.trim();
                
                // Clear other fields when typing
                districtDisplay.value = '';
                provinceDisplay.value = '';
                zipcodeDisplay.value = '';
                document.getElementById('addrProvince').value = '';
                document.getElementById('addrDistrict').value = '';
                document.getElementById('addrSubdistrict').value = '';
                document.getElementById('addrZipcode').value = '';
                
                if (query.length < 2) {
                    subdistrictList.classList.remove('show');
                    return;
                }
                
                // ค้นหาตำบลที่ตรงกัน
                const filtered = allSubdistricts.filter(item => 
                    item.subdistrict.includes(query) ||
                    item.district.includes(query) ||
                    item.province.includes(query)
                ).slice(0, 15);
                
                if (filtered.length > 0) {
                    subdistrictList.innerHTML = filtered.map(item => 
                        `<div class="autocomplete-item" 
                              data-subdistrict="${item.subdistrict}"
                              data-district="${item.district}"
                              data-province="${item.province}"
                              data-zipcode="${item.zipcode}">
                            <strong>${highlightMatch(item.subdistrict, query)}</strong>
                            <small style="color: #94a3b8; display: block; font-size: 0.85em; margin-top: 2px;">
                                ${highlightMatch(item.district, query)}, ${highlightMatch(item.province, query)} 
                                <span style="color: #667eea;">${item.zipcode}</span>
                            </small>
                        </div>`
                    ).join('');
                    subdistrictList.classList.add('show');
                } else {
                    subdistrictList.innerHTML = '<div class="autocomplete-item" style="pointer-events: none; color: #64748b;">ไม่พบตำบล</div>';
                    subdistrictList.classList.add('show');
                }
            });
            
            // เมื่อเลือกตำบล ให้เติมข้อมูลอื่นๆ อัตโนมัติ
            subdistrictList.addEventListener('click', function(e) {
                const item = e.target.closest('.autocomplete-item');
                if (item && item.dataset.subdistrict) {
                    subdistrictInput.value = item.dataset.subdistrict;
                    districtDisplay.value = item.dataset.district;
                    provinceDisplay.value = item.dataset.province;
                    zipcodeDisplay.value = item.dataset.zipcode;
                    
                    // Update hidden fields
                    document.getElementById('addrSubdistrict').value = item.dataset.subdistrict;
                    document.getElementById('addrDistrict').value = item.dataset.district;
                    document.getElementById('addrProvince').value = item.dataset.province;
                    document.getElementById('addrZipcode').value = item.dataset.zipcode;
                    
                    subdistrictList.classList.remove('show');
                    combineAddress();
                }
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.autocomplete-wrapper')) {
                    subdistrictList.classList.remove('show');
                }
            });
            
            // Support keyboard navigation
            let currentFocus = -1;
            subdistrictInput.addEventListener('keydown', function(e) {
                const items = subdistrictList.querySelectorAll('.autocomplete-item[data-subdistrict]');
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    currentFocus++;
                    if (currentFocus >= items.length) currentFocus = 0;
                    setActive(items);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    currentFocus--;
                    if (currentFocus < 0) currentFocus = items.length - 1;
                    setActive(items);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (currentFocus > -1 && items[currentFocus]) {
                        items[currentFocus].click();
                    }
                }
            });
            
            function setActive(items) {
                items.forEach((item, index) => {
                    item.classList.toggle('active', index === currentFocus);
                });
                if (items[currentFocus]) {
                    items[currentFocus].scrollIntoView({ block: 'nearest' });
                }
            }
        }
        
        // Highlight matching text
        function highlightMatch(text, query) {
            if (!query) return text;
            const regex = new RegExp('(' + query + ')', 'gi');
            return text.replace(regex, '<mark>$1</mark>');
        }
        
        // Load Thai address data when page loads
        loadThaiAddressData();
        
        // ฟังก์ชันสำหรับ save และ load ภาพจาก localStorage
        function saveFileToLocalStorage(fileInputId, storageKey) {
            const fileInput = document.getElementById(fileInputId);
            if (!fileInput) return;
            
            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (!file) return;
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    try {
                        localStorage.setItem(storageKey, JSON.stringify({
                            name: file.name,
                            type: file.type,
                            size: file.size,
                            data: e.target.result
                        }));
                    } catch(e) {
                        console.warn('localStorage เต็มแล้ว หรืออื่นๆ', e);
                    }
                };
                reader.readAsDataURL(file);
            });
        }
        
        function loadFileFromLocalStorage(storageKey) {
            try {
                const data = localStorage.getItem(storageKey);
                if (!data) return null;
                return JSON.parse(data);
            } catch(e) {
                console.warn('ไม่สามารถโหลดไฟล์จาก localStorage', e);
                return null;
            }
        }
        
        // Save uploaded files to localStorage
        saveFileToLocalStorage('payProofFile', 'bookingForm_payProof');
        saveFileToLocalStorage('idcardFile', 'bookingForm_idcard');
        saveFileToLocalStorage('houseFile', 'bookingForm_house');
        
        // Global function to format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
        
        // Apple-style File Upload Handler
        (function() {
            const fileInput = document.getElementById('payProofFile');
            const uploadZone = document.getElementById('uploadZone');
            const previewZone = document.getElementById('previewZone');
            const removeBtn = document.getElementById('removeFileBtn');
            const fileNamePreview = document.getElementById('fileNamePreview');
            const fileSizePreview = document.getElementById('fileSizePreview');
            const previewContent = document.getElementById('previewContent');
            
            if (!fileInput || !uploadZone || !previewZone) return;
            
            // Load saved file from localStorage on page load
            const savedFile = loadFileFromLocalStorage('bookingForm_payProof');
            if (savedFile) {
                fileNamePreview.textContent = savedFile.name;
                fileSizePreview.textContent = formatFileSize(savedFile.size);
                previewContent.innerHTML = '';
                
                if (savedFile.type.startsWith('image/')) {
                    previewContent.innerHTML = `<img src="${savedFile.data}" alt="Preview">`;
                } else if (savedFile.type === 'application/pdf') {
                    previewContent.innerHTML = `
                        <div class="pdf-placeholder">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <path d="M10 12h4M10 16h4"/>
                            </svg>
                            <p style="margin: 0; font-size: 1rem; font-weight: 500;">ไฟล์ PDF</p>
                            <p style="margin: 0.5rem 0 0 0; font-size: 0.85rem;">${savedFile.name}</p>
                        </div>
                    `;
                }
                
                uploadZone.style.display = 'none';
                previewZone.style.display = 'block';
            }
            
            // Click to upload
            uploadZone.addEventListener('click', () => fileInput.click());
            
            // File input change
            fileInput.addEventListener('change', handleFileSelect);
            
            // Drag and drop
            uploadZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadZone.classList.add('drag-over');
            });
            
            uploadZone.addEventListener('dragleave', () => {
                uploadZone.classList.remove('drag-over');
            });
            
            uploadZone.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadZone.classList.remove('drag-over');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    handleFileSelect();
                }
            });
            
            // Remove file
            removeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                fileInput.value = '';
                uploadZone.style.display = 'block';
                previewZone.style.display = 'none';
                previewContent.innerHTML = '';
            });
            
            function handleFileSelect() {
                const file = fileInput.files[0];
                if (!file) return;
                
                // Validate file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('ไฟล์มีขนาดใหญ่เกิน 5MB');
                    fileInput.value = '';
                    return;
                }
                
                // Validate file type
                const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
                if (!validTypes.includes(file.type)) {
                    alert('รองรับเฉพาะไฟล์ JPG, PNG หรือ PDF เท่านั้น');
                    fileInput.value = '';
                    return;
                }
                
                // Update file info
                fileNamePreview.textContent = file.name;
                fileSizePreview.textContent = formatFileSize(file.size);
                
                // Show preview
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        previewContent.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                    };
                    reader.readAsDataURL(file);
                } else if (file.type === 'application/pdf') {
                    previewContent.innerHTML = `
                        <div class="pdf-placeholder">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <path d="M10 12h4M10 16h4"/>
                            </svg>
                            <p style="margin: 0; font-size: 1rem; font-weight: 500;">ไฟล์ PDF</p>
                            <p style="margin: 0.5rem 0 0 0; font-size: 0.85rem;">${file.name}</p>
                        </div>
                    `;
                }
                
                // Switch views with animation
                uploadZone.style.display = 'none';
                previewZone.style.display = 'block';
            }
        })();
        
        // Prevent default drag behavior on document
        document.addEventListener('dragover', (e) => e.preventDefault());
        document.addEventListener('drop', (e) => e.preventDefault());
        
        // ID Card Upload Handler
        (function() {
            setupFileUpload(
                'idcardFile',
                'idcardUploadZone',
                'idcardPreviewZone',
                'idcardRemoveBtn',
                'idcardFileNamePreview',
                'idcardFileSizePreview',
                'idcardPreviewContent'
            );
        })();
        
        // House Registration Upload Handler
        (function() {
            setupFileUpload(
                'houseFile',
                'houseUploadZone',
                'housePreviewZone',
                'houseRemoveBtn',
                'houseFileNamePreview',
                'houseFileSizePreview',
                'housePreviewContent'
            );
        })();
        
        // Generic file upload setup function
        function setupFileUpload(fileInputId, uploadZoneId, previewZoneId, removeBtnId, fileNamePreviewId, fileSizePreviewId, previewContentId) {
            const fileInput = document.getElementById(fileInputId);
            const uploadZone = document.getElementById(uploadZoneId);
            const previewZone = document.getElementById(previewZoneId);
            const removeBtn = document.getElementById(removeBtnId);
            const fileNamePreview = document.getElementById(fileNamePreviewId);
            const fileSizePreview = document.getElementById(fileSizePreviewId);
            const previewContent = document.getElementById(previewContentId);
            
            if (!fileInput || !uploadZone || !previewZone) return;
            
            // Map file input ID to localStorage key
            const storageKeyMap = {
                'idcardFile': 'bookingForm_idcard',
                'houseFile': 'bookingForm_house'
            };
            const storageKey = storageKeyMap[fileInputId];
            
            // Load saved file from localStorage on page load
            if (storageKey) {
                const savedFile = loadFileFromLocalStorage(storageKey);
                if (savedFile) {
                    fileNamePreview.textContent = savedFile.name;
                    fileSizePreview.textContent = formatFileSize(savedFile.size);
                    previewContent.innerHTML = '';
                    
                    if (savedFile.type.startsWith('image/')) {
                        previewContent.innerHTML = `<img src="${savedFile.data}" alt="Preview">`;
                    } else if (savedFile.type === 'application/pdf') {
                        previewContent.innerHTML = `
                            <div class="pdf-placeholder">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <path d="M10 12h4M10 16h4"/>
                                </svg>
                                <p style="margin: 0; font-size: 1rem; font-weight: 500;">ไฟล์ PDF</p>
                                <p style="margin: 0.5rem 0 0 0; font-size: 0.85rem;">${savedFile.name}</p>
                            </div>
                        `;
                    }
                    
                    uploadZone.style.display = 'none';
                    previewZone.style.display = 'block';
                }
            }
            
            // Click to upload
            uploadZone.addEventListener('click', () => fileInput.click());
            
            // File input change
            fileInput.addEventListener('change', handleFileSelect);
            
            // Drag and drop
            uploadZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadZone.classList.add('drag-over');
            });
            
            uploadZone.addEventListener('dragleave', () => {
                uploadZone.classList.remove('drag-over');
            });
            
            uploadZone.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadZone.classList.remove('drag-over');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    handleFileSelect();
                }
            });
            
            // Remove file
            removeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                fileInput.value = '';
                uploadZone.style.display = 'block';
                previewZone.style.display = 'none';
                previewContent.innerHTML = '';
            });
            
            function handleFileSelect() {
                const file = fileInput.files[0];
                if (!file) return;
                
                // Validate file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('ไฟล์มีขนาดใหญ่เกิน 5MB');
                    fileInput.value = '';
                    return;
                }
                
                // Validate file type
                const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
                if (!validTypes.includes(file.type)) {
                    alert('รองรับเฉพาะไฟล์ JPG, PNG หรือ PDF เท่านั้น');
                    fileInput.value = '';
                    return;
                }
                
                // Update file info
                fileNamePreview.textContent = file.name;
                fileSizePreview.textContent = formatFileSize(file.size);
                
                // Show preview
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        previewContent.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                    };
                    reader.readAsDataURL(file);
                } else if (file.type === 'application/pdf') {
                    previewContent.innerHTML = `
                        <div class="pdf-placeholder">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <path d="M10 12h4M10 16h4"/>
                            </svg>
                            <p style="margin: 0; font-size: 1rem; font-weight: 500;">ไฟล์ PDF</p>
                            <p style="margin: 0.5rem 0 0 0; font-size: 0.85rem;">${file.name}</p>
                        </div>
                    `;
                }
                
                // Switch views with animation
                uploadZone.style.display = 'none';
                previewZone.style.display = 'block';
            }
        }
        
        // Setup autocomplete for Moo, Soi, Road fields
        function setupAddressFieldsAutocomplete() {
            setupSimpleAutocomplete('addrMoo', 'mooList', commonMoo);
            setupSimpleAutocomplete('addrSoi', 'soiList', commonSoi);
            setupSimpleAutocomplete('addrRoad', 'roadList', commonRoads);
        }
        
        // Setup autocomplete for Education and Faculty fields
        function setupEducationAutocomplete() {
            setupSimpleAutocomplete('educationInput', 'educationList', thaiUniversities);
            setupSimpleAutocomplete('facultyInput', 'facultyList', thaiFaculties);
        }
        
        // Setup Age Input Enhancements
        function setupAgeInput() {
            const directTab = document.getElementById('directAgeTab');
            const birthdateTab = document.getElementById('birthdateTab');
            const directInput = document.getElementById('directAgeInput');
            const birthdateInput = document.getElementById('birthdateInput');
            const ageInput = document.getElementById('ageInput');
            const ageIncrement = document.getElementById('ageIncrement');
            const ageDecrement = document.getElementById('ageDecrement');
            const birthdateField = document.getElementById('birthdateField');
            const ageResult = document.getElementById('ageResult');
            const calculatedAge = document.getElementById('calculatedAge');
            
            if (!ageInput) return;
            
            // Tab switching
            directTab.addEventListener('click', function() {
                directTab.classList.add('active');
                birthdateTab.classList.remove('active');
                directInput.style.display = 'block';
                birthdateInput.style.display = 'none';
            });
            
            birthdateTab.addEventListener('click', function() {
                birthdateTab.classList.add('active');
                directTab.classList.remove('active');
                birthdateInput.style.display = 'block';
                directInput.style.display = 'none';
            });
            
            // Quick age select buttons
            const quickBtns = document.querySelectorAll('.age-quick-btn');
            quickBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const age = parseInt(this.dataset.age);
                    ageInput.value = age;
                    
                    // Remove selected class from all buttons
                    quickBtns.forEach(b => b.classList.remove('selected'));
                    // Add selected class to clicked button
                    this.classList.add('selected');
                    
                    // Trigger input event for validation
                    ageInput.dispatchEvent(new Event('input', { bubbles: true }));
                });
            });
            
            // Increment button
            ageIncrement.addEventListener('click', function() {
                let currentAge = parseInt(ageInput.value) || 18;
                if (currentAge < 99) {
                    ageInput.value = currentAge + 1;
                    updateQuickSelectHighlight();
                    ageInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
            
            // Decrement button
            ageDecrement.addEventListener('click', function() {
                let currentAge = parseInt(ageInput.value) || 18;
                if (currentAge > 15) {
                    ageInput.value = currentAge - 1;
                    updateQuickSelectHighlight();
                    ageInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
            
            // Update quick select highlight when typing
            ageInput.addEventListener('input', function() {
                updateQuickSelectHighlight();
            });
            
            function updateQuickSelectHighlight() {
                const currentAge = parseInt(ageInput.value);
                quickBtns.forEach(btn => {
                    if (parseInt(btn.dataset.age) === currentAge) {
                        btn.classList.add('selected');
                    } else {
                        btn.classList.remove('selected');
                    }
                });
            }
            
            // Birthdate calculation
            birthdateField.addEventListener('change', function() {
                const birthdate = new Date(this.value);
                const today = new Date();
                
                if (!this.value) {
                    ageResult.style.display = 'none';
                    return;
                }
                
                let age = today.getFullYear() - birthdate.getFullYear();
                const monthDiff = today.getMonth() - birthdate.getMonth();
                
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthdate.getDate())) {
                    age--;
                }
                
                if (age >= 15 && age <= 99) {
                    calculatedAge.textContent = age;
                    ageInput.value = age;
                    ageResult.style.display = 'flex';
                    ageInput.dispatchEvent(new Event('input', { bubbles: true }));
                } else {
                    ageResult.style.display = 'none';
                    alert('อายุต้องอยู่ระหว่าง 15-99 ปี');
                }
            });
            
            // Set default age if empty
            if (!ageInput.value) {
                ageInput.value = 20;
                updateQuickSelectHighlight();
            }
        }
        
        // Setup Date Input Enhancements
        function setupDateInputs() {
            const startDateInput = document.getElementById('ctrStart');
            const endDateInput = document.getElementById('ctrEnd');
            const durationDisplay = document.getElementById('durationDisplay');
            const durationValue = document.getElementById('durationValue');
            const startDateText = document.getElementById('startDateText');
            const endDateText = document.getElementById('endDateText');
            
            if (!startDateInput || !endDateInput) return;
            
            // Month and Duration select dropdowns
            const startMonthSelect = document.getElementById('ctrStartMonth');
            const durationSelect = document.getElementById('ctrDuration');
            
            // Update dates when start month changes
            if (startMonthSelect) {
                startMonthSelect.addEventListener('change', function() {
                    startDateInput.value = this.value;
                    updateEndDate();
                    updateDurationDisplay();
                });
            }
            
            // Update end date when duration changes
            if (durationSelect) {
                durationSelect.addEventListener('change', function() {
                    updateEndDate();
                    updateDurationDisplay();
                });
            }
            
            // Calculate end date based on start month and duration
            function updateEndDate() {
                if (!startMonthSelect || !durationSelect) return;
                
                const startValue = startMonthSelect.value; // YYYY-MM-01
                const months = parseInt(durationSelect.value);
                
                // Parse start date
                const [startYear, startMonth, startDay] = startValue.split('-').map(Number);
                const endDate = new Date(startYear, startMonth - 1 + months, 1);
                
                // Set end date to last day of the month before (so it's exactly N months)
                endDate.setDate(0); // Goes to last day of previous month
                
                // Format end date
                const endYear = endDate.getFullYear();
                const endMonth = String(endDate.getMonth() + 1).padStart(2, '0');
                const endDay = String(endDate.getDate()).padStart(2, '0');
                endDateInput.value = `${endYear}-${endMonth}-${endDay}`;
            }
            
            // Initialize end date
            updateEndDate();
            
            function updateDurationDisplay() {
                if (!startMonthSelect || !durationSelect) {
                    durationDisplay.style.display = 'none';
                    return;
                }
                
                const startValue = startMonthSelect.value; // YYYY-MM-01
                const months = parseInt(durationSelect.value);
                
                // Parse start date
                const [startYear, startMonth, startDay] = startValue.split('-').map(Number);
                const start = new Date(startYear, startMonth - 1, 1);
                
                // End date is last day of the N-th month
                const endDate = new Date(startYear, startMonth - 1 + months, 0);
                
                // Format display
                const durationText = `${months} เดือน`;
                
                // Thai date format
                const thaiMonths = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 
                                   'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
                
                const formatThaiDate = (date) => {
                    const day = date.getDate();
                    const month = thaiMonths[date.getMonth()];
                    const year = date.getFullYear() + 543;
                    return `${day} ${month} ${year}`;
                };
                
                durationValue.textContent = durationText;
                startDateText.textContent = formatThaiDate(start);
                endDateText.textContent = formatThaiDate(endDate);
                
                durationDisplay.style.display = 'block';
            }
            
            // Initialize with default 6 months
            updateEndDate();
            updateDurationDisplay();
        }
        
        // Initialize date inputs on page load
        if (document.getElementById('ctrStartMonth')) {
            setupDateInputs();
        }
        
        // ========================================
        // Auto-Save Form Data System
        // ========================================
        
        const FORM_STORAGE_KEY = 'booking_form_data';
        const STORAGE_EXPIRY_DAYS = 7; // เก็บข้อมูลไว้ 7 วัน
        
        // Save form data to localStorage
        function saveFormData() {
            console.log('🔥 saveFormData() called');
            
            const formData = {};
            const form = document.getElementById('bookingForm');
            
            if (!form) {
                console.warn('❌ Form not found');
                return;
            }
            
            console.log('💾 Starting to save form data...');
            
            // Save all text inputs, textareas, select, date inputs (name attributes)
            form.querySelectorAll('input[name], select[name], textarea[name]').forEach(input => {
                const name = input.name;
                const type = input.type;
                
                if (type === 'radio') {
                    if (input.checked) {
                        formData[name] = input.value;
                    }
                } else if (type === 'checkbox') {
                    formData[name] = input.checked;
                } else if (type === 'file') {
                    // Skip file inputs for now - they're too complex
                    return;
                } else {
                    formData[name] = input.value;
                }
            });
            
            // เก็บข้อมูล ID fields ที่สำคัญ (รวมที่อยู่ทั้งหมด)
            const specialFields = ['firstName', 'nickName', 'lastName', 'fullName', 'ageInput', 
                                  'addrHouse', 'addrMoo', 'addrSoi', 'addrRoad', 'fullAddress',
                                  'addrProvince', 'addrDistrict', 'addrSubdistrict', 'addrZipcode',
                                  'educationInput', 'facultyInput'];
            
            console.log('🔍 Checking special fields...');
            specialFields.forEach(id => {
                const field = document.getElementById(id);
                if (field) {
                    console.log('  ' + id + ': found, value=' + (field.value || '(empty)'));
                    if (field.value) {
                        formData[`field_${id}`] = field.value;
                        console.log('  ✓ Saved:', id, '=', field.value);
                    }
                } else {
                    console.log('  ' + id + ': NOT FOUND');
                }
            });
            
            // เก็บค่า SELECT สำหรับที่อยู่ (dropdown mode)
            const addressSelects = ['addrProvinceSelect', 'addrDistrictSelect', 'addrSubdistrictSelect', 'addrZipcodeSelect'];
            console.log('🔍 Checking address selects...');
            addressSelects.forEach(id => {
                const select = document.getElementById(id);
                console.log('  ' + id + ':', select ? 'found' : 'NOT FOUND', select ? ('value=' + select.value) : '');
                if (select && select.value) {
                    formData[`select_${id}`] = select.value;
                    console.log('  ✓ Saved select:', id, '=', select.value);
                }
            });
            
            // เก็บค่าจากโหมดค้นหา (Search Mode)
            const searchModeFields = {
                'addrSubdistrictSearch': 'search_subdistrict',
                'addrProvinceDisplay': 'search_province',
                'addrDistrictDisplay': 'search_district',
                'addrZipcodeDisplay': 'search_zipcode'
            };
            console.log('🔍 Checking search mode fields...');
            Object.keys(searchModeFields).forEach(id => {
                const field = document.getElementById(id);
                if (field && field.value) {
                    formData[searchModeFields[id]] = field.value;
                    console.log('  ✓ Saved search field:', id, '=', field.value);
                }
            });
            
            // เก็บโหมดที่อยู่ที่เลือก
            const searchMode = document.getElementById('searchMode');
            const dropdownMode = document.getElementById('dropdownMode');
            if (searchMode && searchMode.style.display !== 'none') {
                formData._addressMode = 'search';
            } else if (dropdownMode && dropdownMode.style.display !== 'none') {
                formData._addressMode = 'dropdown';
            }
            
            // เก็บปุ่ม date-quick-btn ที่ active
            const selectedDateBtn = document.querySelector('.date-quick-btn.selected');
            if (selectedDateBtn && selectedDateBtn.dataset.days) {
                formData._selectedDateDays = selectedDateBtn.dataset.days;
                console.log('✓ Saved selected date button:', selectedDateBtn.dataset.days, 'days');
            }
            
            // เก็บปุ่ม duration-btn ที่ active
            const activeDurationBtn = document.querySelector('.duration-btn.active');
            if (activeDurationBtn && activeDurationBtn.dataset.months) {
                formData._selectedDurationMonths = activeDurationBtn.dataset.months;
                console.log('✓ Saved active duration button:', activeDurationBtn.dataset.months, 'months');
            }
            
            // เพิ่ม timestamp
            formData._timestamp = new Date().getTime();
            
            try {
                localStorage.setItem(FORM_STORAGE_KEY, JSON.stringify(formData));
                console.log('✓ Form data auto-saved (' + Object.keys(formData).length + ' fields)');
                
                // Show clear button after saving
                setTimeout(() => {
                    addClearDataButton();
                }, 100);
                
            } catch (e) {
                console.error('Error saving form data:', e);
            }
        }
        
        // Load form data from localStorage
        function loadFormData() {
            try {
                console.log('📂 Attempting to load form data...');
                
                // Check if user just cleared data
                if (sessionStorage.getItem('formDataCleared') === 'true') {
                    sessionStorage.removeItem('formDataCleared');
                    console.log('⊘ Skip loading - data was just cleared');
                    return false;
                }
                
                const savedData = localStorage.getItem(FORM_STORAGE_KEY);
                
                if (!savedData) {
                    console.log('⊘ No saved data found in localStorage');
                    return false;
                }
                
                console.log('✓ Found saved data, parsing...');
                const formData = JSON.parse(savedData);
                
                // Check if data is expired
                if (formData._timestamp) {
                    const expiryTime = STORAGE_EXPIRY_DAYS * 24 * 60 * 60 * 1000;
                    const now = new Date().getTime();
                    
                    if (now - formData._timestamp > expiryTime) {
                        console.log('⚠ Form data expired, clearing...');
                        localStorage.removeItem(FORM_STORAGE_KEY);
                        return false;
                    }
                }
                
                const form = document.getElementById('bookingForm');
                if (!form) return false;
                
                let fieldsRestored = 0;
                
                // Restore address mode first
                if (formData._addressMode === 'dropdown') {
                    const dropdownBtn = document.getElementById('dropdownModeBtn');
                    if (dropdownBtn) dropdownBtn.click();
                } else if (formData._addressMode === 'search') {
                    const searchBtn = document.getElementById('searchModeBtn');
                    if (searchBtn) searchBtn.click();
                    
                    // Restore search mode fields immediately
                    setTimeout(() => {
                        if (formData.search_subdistrict) {
                            const subdistrictInput = document.getElementById('addrSubdistrictSearch');
                            if (subdistrictInput) {
                                subdistrictInput.value = formData.search_subdistrict;
                                console.log('✓ Restored search_subdistrict:', formData.search_subdistrict);
                            }
                        }
                        if (formData.search_province) {
                            const provinceDisplay = document.getElementById('addrProvinceDisplay');
                            if (provinceDisplay) {
                                provinceDisplay.value = formData.search_province;
                                console.log('✓ Restored search_province:', formData.search_province);
                            }
                        }
                        if (formData.search_district) {
                            const districtDisplay = document.getElementById('addrDistrictDisplay');
                            if (districtDisplay) {
                                districtDisplay.value = formData.search_district;
                                console.log('✓ Restored search_district:', formData.search_district);
                            }
                        }
                        if (formData.search_zipcode) {
                            const zipcodeDisplay = document.getElementById('addrZipcodeDisplay');
                            if (zipcodeDisplay) {
                                zipcodeDisplay.value = formData.search_zipcode;
                                console.log('✓ Restored search_zipcode:', formData.search_zipcode);
                            }
                        }
                    }, 100);
                }
                
                // Restore address selects with proper cascade
                const restoreAddressSelects = () => {
                    // Restore province first
                    if (formData.select_addrProvinceSelect) {
                        const provinceSelect = document.getElementById('addrProvinceSelect');
                        if (provinceSelect && provinceSelect.options.length > 1) {
                            provinceSelect.value = formData.select_addrProvinceSelect;
                            provinceSelect.dispatchEvent(new Event('change', { bubbles: true }));
                            console.log('✓ Restored province select:', formData.select_addrProvinceSelect);
                            
                            // Wait for district to load, then restore
                            setTimeout(() => {
                                if (formData.select_addrDistrictSelect) {
                                    const districtSelect = document.getElementById('addrDistrictSelect');
                                    if (districtSelect && districtSelect.options.length > 1) {
                                        districtSelect.value = formData.select_addrDistrictSelect;
                                        districtSelect.dispatchEvent(new Event('change', { bubbles: true }));
                                        console.log('✓ Restored district select:', formData.select_addrDistrictSelect);
                                        
                                        // Wait for subdistrict to load, then restore
                                        setTimeout(() => {
                                            if (formData.select_addrSubdistrictSelect) {
                                                const subdistrictSelect = document.getElementById('addrSubdistrictSelect');
                                                if (subdistrictSelect && subdistrictSelect.options.length > 1) {
                                                    subdistrictSelect.value = formData.select_addrSubdistrictSelect;
                                                    subdistrictSelect.dispatchEvent(new Event('change', { bubbles: true }));
                                                    console.log('✓ Restored subdistrict select:', formData.select_addrSubdistrictSelect);
                                                }
                                            }
                                            
                                            // Finally restore zipcode
                                            if (formData.select_addrZipcodeSelect) {
                                                const zipcodeSelect = document.getElementById('addrZipcodeSelect');
                                                if (zipcodeSelect && zipcodeSelect.options.length > 1) {
                                                    zipcodeSelect.value = formData.select_addrZipcodeSelect;
                                                    zipcodeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                                                    console.log('✓ Restored zipcode select:', formData.select_addrZipcodeSelect);
                                                }
                                            }
                                        }, 300);
                                    }
                                }
                            }, 300);
                        }
                    }
                };
                
                // Wait for thaiAddressData to be ready, then restore address selects
                if (formData._addressMode === 'dropdown') {
                    let attempts = 0;
                    const waitForData = setInterval(() => {
                        attempts++;
                        if (typeof thaiAddressData !== 'undefined' && thaiAddressData.length > 0) {
                            clearInterval(waitForData);
                            setTimeout(restoreAddressSelects, 500);
                        } else if (attempts > 50) { // 5 seconds max
                            clearInterval(waitForData);
                            console.log('⚠ Timeout waiting for address data');
                        }
                    }, 100);
                }
                
                // Restore all other form fields
                Object.keys(formData).forEach(key => {
                    // Skip select dropdowns (handled above with cascade)
                    if (key.startsWith('select_')) {
                        return;
                    }
                    if (key === '_timestamp' || key === '_addressMode') return;
                    
                    // Handle file uploads
                    if (key.startsWith('file_')) {
                        const fieldName = key.replace('file_', '');
                        const fileInput = document.querySelector(`input[name="${fieldName}"]`);
                        
                        if (fileInput && formData[key]) {
                            const fileData = formData[key];
                            
                            // Create a File object from saved data
                            fetch(fileData.data)
                                .then(res => res.blob())
                                .then(blob => {
                                    const file = new File([blob], fileData.name, { type: fileData.type });
                                    const dataTransfer = new DataTransfer();
                                    dataTransfer.items.add(file);
                                    fileInput.files = dataTransfer.files;
                                    
                                    // Trigger change event to update UI
                                    const event = new Event('change', { bubbles: true });
                                    fileInput.dispatchEvent(event);
                                    
                                    fieldsRestored++;
                                    console.log('✓ Restored file:', fileData.name);
                                })
                                .catch(err => console.error('Error restoring file:', err));
                        }
                        return;
                    }
                    
                    // Handle special ID fields
                    if (key.startsWith('field_')) {
                        const fieldId = key.replace('field_', '');
                        const field = document.getElementById(fieldId);
                        
                        // Debug address fields
                        if (fieldId.includes('addr')) {
                            console.log('🔄 Restoring address field:', fieldId, 'value=', formData[key]);
                        }
                        
                        if (field && formData[key]) {
                            field.value = formData[key];
                            console.log('  ✓ Restored:', fieldId, '=', formData[key]);
                            
                            // Trigger change event for dropdowns
                            if (field.tagName === 'SELECT') {
                                const event = new Event('change', { bubbles: true });
                                field.dispatchEvent(event);
                            }
                            
                            fieldsRestored++;
                        } else if (fieldId.includes('addr')) {
                            console.log('  ❌ Not restored:', fieldId, 'field=', field ? 'found' : 'NOT FOUND', 'data=', formData[key] || '(empty)');
                        }
                        return;
                    }
                    
                    // Handle named fields
                    const inputs = form.querySelectorAll(`[name="${key}"]`);
                    
                    inputs.forEach(input => {
                        const type = input.type;
                        
                        if (type === 'radio') {
                            if (input.value === formData[key]) {
                                input.checked = true;
                                fieldsRestored++;
                            }
                        } else if (type === 'checkbox') {
                            input.checked = formData[key];
                            if (formData[key]) fieldsRestored++;
                        } else if (type !== 'file') {
                            input.value = formData[key];
                            if (formData[key]) fieldsRestored++;
                        }
                    });
                });
                
                if (fieldsRestored > 0) {
                    console.log(`✓ Restored ${fieldsRestored} form fields from auto-save`);
                    
                    // Show clear button after restoring data
                    addClearDataButton();
                    
                    // Trigger events to update UI
                    setTimeout(() => {
                        // Update name fields
                        if (document.getElementById('firstName')) {
                            combineNameFields();
                        }
                        
                        // Update address
                        if (document.getElementById('addrHouse')) {
                            combineAddress();
                        }
                        
                        // Trigger province change to load districts (for dropdown mode)
                        if (formData._addressMode === 'dropdown') {
                            const provinceSelect = document.getElementById('addrProvinceSelect');
                            const districtSelect = document.getElementById('addrDistrictSelect');
                            const subdistrictSelect = document.getElementById('addrSubdistrictSelect');
                            const zipcodeSelect = document.getElementById('addrZipcodeSelect');
                            
                            console.log('🔄 Restoring address dropdowns...');
                            console.log('Province:', formData.select_addrProvinceSelect);
                            console.log('District:', formData.select_addrDistrictSelect);
                            console.log('Subdistrict:', formData.select_addrSubdistrictSelect);
                            console.log('Zipcode:', formData.select_addrZipcodeSelect);
                            
                            if (provinceSelect && formData.select_addrProvinceSelect) {
                                // Set province
                                setTimeout(() => {
                                    console.log('Setting province...');
                                    provinceSelect.value = formData.select_addrProvinceSelect;
                                    const event = new Event('change', { bubbles: true });
                                    provinceSelect.dispatchEvent(event);
                                    console.log('✓ Province set:', provinceSelect.value);
                                    
                                    // After province loads districts, set district value
                                    setTimeout(() => {
                                        if (districtSelect && formData.select_addrDistrictSelect) {
                                            console.log('Setting district...');
                                            districtSelect.value = formData.select_addrDistrictSelect;
                                            const districtEvent = new Event('change', { bubbles: true });
                                            districtSelect.dispatchEvent(districtEvent);
                                            console.log('✓ District set:', districtSelect.value);
                                            
                                            // After district loads subdistricts, set subdistrict value
                                            setTimeout(() => {
                                                if (subdistrictSelect && formData.select_addrSubdistrictSelect) {
                                                    console.log('Setting subdistrict...');
                                                    subdistrictSelect.value = formData.select_addrSubdistrictSelect;
                                                    const subdistrictEvent = new Event('change', { bubbles: true });
                                                    subdistrictSelect.dispatchEvent(subdistrictEvent);
                                                    console.log('✓ Subdistrict set:', subdistrictSelect.value);
                                                    
                                                    // Set zipcode
                                                    setTimeout(() => {
                                                        if (zipcodeSelect && formData.select_addrZipcodeSelect) {
                                                            console.log('Setting zipcode...');
                                                            zipcodeSelect.value = formData.select_addrZipcodeSelect;
                                                            console.log('✓ Zipcode set:', zipcodeSelect.value);
                                                        }
                                                    }, 300);
                                                }
                                            }, 500);
                                        }
                                    }, 500);
                                }, 200);
                            }
                        }
                        
                        // Update date duration display
                        if (document.getElementById('ctrStart') && document.getElementById('ctrEnd')) {
                            const event = new Event('change');
                            document.getElementById('ctrStart').dispatchEvent(event);
                        }
                        
                        // Restore date-quick-btn selected state
                        if (formData._selectedDateDays) {
                            const dateBtn = document.querySelector(`.date-quick-btn[data-days="${formData._selectedDateDays}"]`);
                            if (dateBtn) {
                                document.querySelectorAll('.date-quick-btn').forEach(b => b.classList.remove('selected'));
                                dateBtn.classList.add('selected');
                                console.log('✓ Restored date button selection:', formData._selectedDateDays, 'days');
                            }
                        }
                        
                        // Restore duration-btn active state
                        if (formData._selectedDurationMonths) {
                            const durationBtn = document.querySelector(`.duration-btn[data-months="${formData._selectedDurationMonths}"]`);
                            if (durationBtn) {
                                document.querySelectorAll('.duration-btn').forEach(b => b.classList.remove('active'));
                                durationBtn.classList.add('active');
                                console.log('✓ Restored duration button:', formData._selectedDurationMonths, 'months');
                            }
                        }
                        
                        // Show notification
                        showAutoSaveNotification();
                        
                        // Trigger validation update for all fields
                        const form = document.getElementById('bookingForm');
                        if (form) {
                            form.querySelectorAll('input[required][name]:not([type="radio"]):not([type="file"]), textarea[required][name]').forEach(input => {
                                if (input.value) {
                                    const event = new Event('input', { bubbles: true });
                                    input.dispatchEvent(event);
                                }
                            });
                            
                            // Check if room is selected and trigger click
                            const selectedRoom = form.querySelector('input[name="room_id"]:checked');
                            if (selectedRoom) {
                                const event = new Event('change', { bubbles: true });
                                selectedRoom.dispatchEvent(event);
                            }
                        }
                    }, 800);
                    
                    return true;
                }
                
            } catch (e) {
                console.error('Error loading form data:', e);
            }
            
            return false;
        }
        
        // Clear saved form data
        function clearFormData() {
            // Remove from localStorage
            localStorage.removeItem(FORM_STORAGE_KEY);
            console.log('✓ Form data cleared from localStorage');
            
            // Set flag to prevent restore on next load
            sessionStorage.setItem('formDataCleared', 'true');
            
            // Clear the actual form manually (don't use form.reset() to preserve event listeners)
            const form = document.getElementById('bookingForm');
            if (form) {
                // Clear all text inputs, textareas
                form.querySelectorAll('input[type="text"], input[type="tel"], input[type="date"], input[type="number"], textarea').forEach(input => {
                    input.value = '';
                });
                
                // Uncheck all radio buttons and checkboxes (except room selection)
                form.querySelectorAll('input[type="radio"], input[type="checkbox"]').forEach(input => {
                    // Don't clear room selection radio buttons
                    if (input.name !== 'room_id') {
                        input.checked = false;
                    }
                });
                
                // Reset all select elements
                form.querySelectorAll('select').forEach(select => {
                    select.selectedIndex = 0;
                });
                
                // Clear file inputs
                form.querySelectorAll('input[type="file"]').forEach(input => {
                    input.value = '';
                });
                
                // Clear hidden fields
                const specialFields = ['fullName', 'fullAddress', 'addrProvince', 'addrDistrict', 'addrSubdistrict', 'addrZipcode'];
                specialFields.forEach(id => {
                    const field = document.getElementById(id);
                    if (field) field.value = '';
                });
                
                // Clear name fields
                ['firstName', 'nickName', 'lastName'].forEach(id => {
                    const field = document.getElementById(id);
                    if (field) field.value = '';
                });
                
                // Clear address fields
                ['addrHouse', 'addrMoo', 'addrSoi', 'addrRoad'].forEach(id => {
                    const field = document.getElementById(id);
                    if (field) field.value = '';
                });
                
                // Clear dropdown mode selects
                ['addrProvinceSelect', 'addrDistrictSelect', 'addrSubdistrictSelect', 'addrZipcodeSelect'].forEach(id => {
                    const select = document.getElementById(id);
                    if (select) {
                        select.selectedIndex = 0;
                        select.disabled = id !== 'addrProvinceSelect'; // Re-disable dependent selects
                    }
                });
                
                // Clear education fields
                const educationInput = document.getElementById('educationInput');
                const facultyInput = document.getElementById('facultyInput');
                if (educationInput) educationInput.value = '';
                if (facultyInput) facultyInput.value = '';
                
                // Clear age input
                const ageInput = document.getElementById('ageInput');
                if (ageInput) ageInput.value = '';
                
                // Clear file previews
                const uploadZones = document.querySelectorAll('.apple-upload-zone');
                const previewZones = document.querySelectorAll('.apple-preview-zone');
                uploadZones.forEach(zone => zone.style.display = 'block');
                previewZones.forEach(zone => zone.style.display = 'none');
                
                // Clear preview contents
                document.querySelectorAll('.preview-content').forEach(preview => {
                    preview.innerHTML = '';
                });
                
                // Clear address preview
                const addressPreview = document.getElementById('addressPreview');
                if (addressPreview) addressPreview.style.display = 'none';
                
                // Clear duration display
                const durationDisplay = document.querySelector('.contract-duration-display');
                if (durationDisplay) durationDisplay.style.display = 'none';
                
                console.log('✓ Form cleared');
            }
            
            // Remove clear button
            const clearBtn = document.querySelector('.clear-saved-data-btn');
            if (clearBtn) {
                clearBtn.remove();
                console.log('✓ Clear button removed');
            }
        }
        
        // Show auto-save notification
        function showAutoSaveNotification() {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: linear-gradient(135deg, rgba(34, 197, 94, 0.95), rgba(22, 163, 74, 0.95));
                color: white;
                padding: 16px 24px;
                border-radius: 12px;
                box-shadow: 0 8px 32px rgba(34, 197, 94, 0.4);
                z-index: 10000;
                display: flex;
                align-items: center;
                gap: 12px;
                font-size: 0.95rem;
                font-weight: 500;
                animation: slideInRight 0.5s ease, fadeOut 0.5s ease 3.5s forwards;
                backdrop-filter: blur(10px);
            `;
            
            notification.innerHTML = `
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                <span>กู้คืนข้อมูลที่เคยกรอกไว้สำเร็จ</span>
            `;
            
            // Add animations
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideInRight {
                    from {
                        transform: translateX(400px);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
                @keyframes fadeOut {
                    to {
                        opacity: 0;
                        transform: translateX(400px);
                    }
                }
            `;
            document.head.appendChild(style);
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
                style.remove();
            }, 4000);
        }
        
        // Initialize Auto-Save System
        function initAutoSave() {
            const form = document.getElementById('bookingForm');
            if (!form) {
                console.warn('Form not found, auto-save disabled');
                return;
            }
            
            // Wait for Thai address data to load before restoring (with timeout)
            let waitCount = 0;
            const maxWait = 50; // Max 10 seconds (50 * 200ms)
            
            function waitForDataAndRestore() {
                waitCount++;
                
                if (thaiAddressData && thaiAddressData.length > 0) {
                    // Data is ready, restore form
                    console.log('✓ Thai address data ready, restoring form...');
                    setTimeout(() => {
                        try {
                            loadFormData();
                        } catch (e) {
                            console.error('Error restoring form data:', e);
                        }
                    }, 1000); // Give extra time for UI to initialize
                } else if (waitCount < maxWait) {
                    // Data not ready yet, wait and try again
                    setTimeout(waitForDataAndRestore, 200);
                } else {
                    // Timeout - proceed without restore
                    console.warn('⚠ Thai address data load timeout, skipping restore');
                }
            }
            
            // Start waiting for data
            waitForDataAndRestore();
            
            // Auto-save on input changes (debounced)
            let saveTimeout;
            const debouncedSave = () => {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(saveFormData, 1000); // Save after 1 second of inactivity
            };
            
            // Listen to all form inputs
            form.addEventListener('input', debouncedSave);
            form.addEventListener('change', debouncedSave);
            
            // Save before page unload
            window.addEventListener('beforeunload', saveFormData);
            
            // Note: We DON'T clear data on form submit event
            // because the main form handler uses e.preventDefault() for validation
            // We will clear data only when form is actually successfully submitted
            // via the form.submit() call which will trigger page navigation
            
            console.log('✓ Auto-save system initialized');
        }
        
        // Add clear button to form (optional)
        function addClearDataButton() {
            const form = document.getElementById('bookingForm');
            if (!form) return;
            
            // Remove existing button first
            const existingBtn = document.querySelector('.clear-saved-data-btn');
            if (existingBtn) {
                existingBtn.remove();
            }
            
            // Check if there's saved data
            const savedData = localStorage.getItem(FORM_STORAGE_KEY);
            
            // Don't show button if no saved data or data was just cleared
            if (!savedData || sessionStorage.getItem('formDataCleared') === 'true') {
                return;
            }
            
            const clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.className = 'clear-saved-data-btn';
            clearBtn.innerHTML = `
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                    <line x1="10" y1="11" x2="10" y2="17"/>
                    <line x1="14" y1="11" x2="14" y2="17"/>
                </svg>
                ล้างข้อมูลที่บันทึกไว้
            `;
            
            clearBtn.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: rgba(239, 68, 68, 0.1);
                border: 1px solid rgba(239, 68, 68, 0.3);
                color: #ef4444;
                padding: 12px 20px;
                border-radius: 12px;
                font-size: 0.85rem;
                font-weight: 500;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 8px;
                transition: all 0.3s;
                z-index: 9999;
                backdrop-filter: blur(10px);
            `;
            
            clearBtn.addEventListener('mouseenter', function() {
                this.style.background = 'rgba(239, 68, 68, 0.2)';
                this.style.transform = 'scale(1.05)';
            });
            
            clearBtn.addEventListener('mouseleave', function() {
                this.style.background = 'rgba(239, 68, 68, 0.1)';
                this.style.transform = 'scale(1)';
            });
            
            clearBtn.addEventListener('click', function(e) {
                e.preventDefault();
                showAppleConfirm(
                    'ล้างข้อมูลที่บันทึกไว้?',
                    'ข้อมูลที่คุณกรอกไว้ทั้งหมดจะถูกลบออก และไม่สามารถกู้คืนได้',
                    function() {
                        clearFormData();
                        location.reload();
                    }
                );
            });
            
            document.body.appendChild(clearBtn);
        }
        
        // Apple-style Confirm Dialog
        function showAppleConfirm(title, message, onConfirm) {
            // Create backdrop
            const backdrop = document.createElement('div');
            backdrop.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                z-index: 99999;
                display: flex;
                align-items: center;
                justify-content: center;
                animation: fadeIn 0.3s ease;
            `;
            
            // Create dialog
            const dialog = document.createElement('div');
            dialog.style.cssText = `
                background: linear-gradient(135deg, rgba(30, 41, 59, 0.98), rgba(17, 24, 39, 0.98));
                border: 1px solid rgba(148, 163, 184, 0.2);
                border-radius: 24px;
                padding: 0;
                max-width: 420px;
                width: 90%;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 100px rgba(239, 68, 68, 0.3);
                animation: scaleIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
                overflow: hidden;
            `;
            
            dialog.innerHTML = `
                <div style="padding: 2.5rem 2rem 1.5rem;">
                    <div style="display: flex; flex-direction: column; align-items: center; text-align: center;">
                        <div style="width: 80px; height: 80px; margin-bottom: 1.5rem; position: relative;">
                            <svg viewBox="0 0 100 100" style="width: 100%; height: 100%; animation: iconPulse 2s ease-in-out infinite;">
                                <circle cx="50" cy="50" r="45" fill="none" stroke="rgba(239, 68, 68, 0.2)" stroke-width="2"/>
                                <circle cx="50" cy="50" r="45" fill="none" stroke="#ef4444" stroke-width="3" stroke-linecap="round"
                                    style="stroke-dasharray: 283; stroke-dashoffset: 283; animation: drawCircle 1s ease-out forwards;"/>
                                <g style="animation: scaleIcon 0.5s ease-out 0.5s both;">
                                    <path d="M50 30 L50 55" stroke="#ef4444" stroke-width="4" stroke-linecap="round"
                                        style="stroke-dasharray: 25; stroke-dashoffset: 25; animation: drawLine 0.3s ease-out 0.8s forwards;"/>
                                    <circle cx="50" cy="65" r="3" fill="#ef4444" style="opacity: 0; animation: fadeIn 0.3s ease-out 1.1s forwards;"/>
                                </g>
                            </svg>
                        </div>
                        <h3 style="margin: 0 0 0.75rem 0; color: #f1f5f9; font-size: 1.5rem; font-weight: 600; letter-spacing: -0.02em;">
                            ${title}
                        </h3>
                        <p style="margin: 0; color: #cbd5e1; font-size: 1rem; line-height: 1.6; font-weight: 400;">
                            ${message}
                        </p>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; border-top: 1px solid rgba(148, 163, 184, 0.15);">
                    <button id="cancelBtn" style="
                        padding: 1.25rem;
                        background: transparent;
                        border: none;
                        border-right: 1px solid rgba(148, 163, 184, 0.15);
                        color: #94a3b8;
                        font-size: 1.05rem;
                        font-weight: 600;
                        cursor: pointer;
                        transition: all 0.2s;
                    ">ยกเลิก</button>
                    <button id="confirmBtn" style="
                        padding: 1.25rem;
                        background: transparent;
                        border: none;
                        color: #ef4444;
                        font-size: 1.05rem;
                        font-weight: 600;
                        cursor: pointer;
                        transition: all 0.2s;
                    ">ล้างข้อมูล</button>
                </div>
            `;
            
            backdrop.appendChild(dialog);
            document.body.appendChild(backdrop);
            
            // Add CSS animations
            const style = document.createElement('style');
            style.textContent = `
                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
                @keyframes scaleIn {
                    from {
                        opacity: 0;
                        transform: scale(0.8) translateY(20px);
                    }
                    to {
                        opacity: 1;
                        transform: scale(1) translateY(0);
                    }
                }
                @keyframes iconPulse {
                    0%, 100% { transform: scale(1); }
                    50% { transform: scale(1.05); }
                }
                @keyframes drawCircle {
                    to { stroke-dashoffset: 0; }
                }
                @keyframes drawLine {
                    to { stroke-dashoffset: 0; }
                }
                @keyframes scaleIcon {
                    from { transform: scale(0); }
                    to { transform: scale(1); }
                }
                @keyframes fadeOut {
                    to {
                        opacity: 0;
                        transform: scale(0.9) translateY(10px);
                    }
                }
            `;
            document.head.appendChild(style);
            
            // Button hover effects
            const cancelBtn = dialog.querySelector('#cancelBtn');
            const confirmBtn = dialog.querySelector('#confirmBtn');
            
            cancelBtn.addEventListener('mouseenter', function() {
                this.style.background = 'rgba(148, 163, 184, 0.1)';
            });
            cancelBtn.addEventListener('mouseleave', function() {
                this.style.background = 'transparent';
            });
            
            confirmBtn.addEventListener('mouseenter', function() {
                this.style.background = 'rgba(239, 68, 68, 0.1)';
            });
            confirmBtn.addEventListener('mouseleave', function() {
                this.style.background = 'transparent';
            });
            
            // Close function
            const closeDialog = () => {
                dialog.style.animation = 'fadeOut 0.3s ease forwards';
                backdrop.style.animation = 'fadeIn 0.3s ease reverse';
                setTimeout(() => {
                    backdrop.remove();
                    style.remove();
                }, 300);
            };
            
            // Event listeners
            cancelBtn.addEventListener('click', closeDialog);
            confirmBtn.addEventListener('click', () => {
                closeDialog();
                setTimeout(() => {
                    if (onConfirm) onConfirm();
                }, 300);
            });
            
            // Click outside to close
            backdrop.addEventListener('click', (e) => {
                if (e.target === backdrop) closeDialog();
            });
            
            // ESC key to close
            const escHandler = (e) => {
                if (e.key === 'Escape') {
                    closeDialog();
                    document.removeEventListener('keydown', escHandler);
                }
            };
            document.addEventListener('keydown', escHandler);
        }
        
        // Initialize auto-save when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initAutoSave);
        } else {
            initAutoSave();
        }
        
        // Add clear button after auto-save loads
        setTimeout(addClearDataButton, 1000);
        
        // Generic autocomplete for simple lists
        function setupSimpleAutocomplete(inputId, listId, dataArray) {
            const input = document.getElementById(inputId);
            const list = document.getElementById(listId);
            let selectedIndex = -1;
            
            if (!input || !list) return;
            
            input.addEventListener('input', function() {
                const value = this.value.trim().toLowerCase();
                list.innerHTML = '';
                selectedIndex = -1;
                
                if (value === '') {
                    list.style.display = 'none';
                    return;
                }
                
                // Filter และเรียงลำดับผลลัพธ์
                const filtered = dataArray.filter(item => {
                    return item.toString().toLowerCase().includes(value);
                }).sort((a, b) => {
                    const aStr = a.toString().toLowerCase();
                    const bStr = b.toString().toLowerCase();
                    // ให้ผลที่ขึ้นต้นด้วยคำค้นหามาก่อน
                    const aStarts = aStr.startsWith(value);
                    const bStarts = bStr.startsWith(value);
                    if (aStarts && !bStarts) return -1;
                    if (!aStarts && bStarts) return 1;
                    return aStr.localeCompare(bStr, 'th');
                }).slice(0, 10); // จำกัดแค่ 10 รายการ
                
                if (filtered.length === 0) {
                    list.style.display = 'none';
                    return;
                }
                
                filtered.forEach((item, index) => {
                    const div = document.createElement('div');
                    div.className = 'autocomplete-item';
                    div.textContent = item;
                    div.addEventListener('click', function() {
                        input.value = item;
                        list.innerHTML = '';
                        list.style.display = 'none';
                    });
                    list.appendChild(div);
                });
                
                list.style.display = 'block';
            });
            
            // Keyboard navigation
            input.addEventListener('keydown', function(e) {
                const items = list.querySelectorAll('.autocomplete-item');
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
                    updateSelection(items);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    selectedIndex = Math.max(selectedIndex - 1, -1);
                    updateSelection(items);
                } else if (e.key === 'Enter' && selectedIndex >= 0) {
                    e.preventDefault();
                    items[selectedIndex].click();
                } else if (e.key === 'Escape') {
                    list.innerHTML = '';
                    list.style.display = 'none';
                    selectedIndex = -1;
                }
            });
            
            function updateSelection(items) {
                items.forEach((item, index) => {
                    if (index === selectedIndex) {
                        item.classList.add('active');
                    } else {
                        item.classList.remove('active');
                    }
                });
            }
            
            // ปิด autocomplete เมื่อคลิกข้างนอก
            document.addEventListener('click', function(e) {
                if (e.target !== input && !list.contains(e.target)) {
                    list.style.display = 'none';
                    selectedIndex = -1;
                }
            });
        }
        
        // Combine name fields (ชื่อจริง (ชื่อเล่น) นามสกุล)
        function combineNameFields() {
            const firstNameEl = document.getElementById('firstName');
            const nickNameEl = document.getElementById('nickName');
            const lastNameEl = document.getElementById('lastName');
            const fullNameEl = document.getElementById('fullName');
            
            if (!fullNameEl) {
                console.error('fullName element not found');
                return;
            }
            
            const firstName = firstNameEl?.value?.trim() || '';
            const nickName = nickNameEl?.value?.trim() || '';
            const lastName = lastNameEl?.value?.trim() || '';
            
            let fullName = '';
            if (firstName) {
                fullName = firstName;
                if (nickName) {
                    fullName += ' (' + nickName + ')';
                }
                if (lastName) {
                    fullName += ' ' + lastName;
                }
            }
            
            fullNameEl.value = fullName;
            console.log('combineNameFields called:', { firstName, nickName, lastName, fullName });
        }
        
        // Add event listeners for name fields
        ['firstName', 'nickName', 'lastName'].forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.addEventListener('input', combineNameFields);
                field.addEventListener('blur', combineNameFields);
            }
        });
        
        // Combine address fields into single hidden field before submit
        function combineAddress() {
            const parts = [];
            const house = document.getElementById('addrHouse')?.value?.trim();
            const moo = document.getElementById('addrMoo')?.value?.trim();
            const soi = document.getElementById('addrSoi')?.value?.trim();
            const road = document.getElementById('addrRoad')?.value?.trim();
            
            // สำหรับ select ใช้ value โดยตรง
            const subdistrictEl = document.getElementById('addrSubdistrict');
            const districtEl = document.getElementById('addrDistrict');
            const provinceEl = document.getElementById('addrProvince');
            const zipcodeEl = document.getElementById('addrZipcode');
            
            const subdistrict = subdistrictEl?.value?.trim() || '';
            const district = districtEl?.value?.trim() || '';
            const province = provinceEl?.value?.trim() || '';
            const zipcode = zipcodeEl?.value?.trim() || '';
            
            if (house) parts.push(house);
            if (moo) parts.push('หมู่ ' + moo);
            if (soi) parts.push('ซอย ' + soi);
            if (road) parts.push('ถนน ' + road);
            if (subdistrict) parts.push('ต.' + subdistrict);
            if (district) parts.push('อ.' + district);
            if (province) parts.push('จ.' + province);
            if (zipcode) parts.push(zipcode);
            
            const fullAddr = parts.join(' ');
            document.getElementById('fullAddress').value = fullAddr;
            
            // แสดงที่อยู่เต็มในพื้นที่แสดงผล
            const previewBox = document.getElementById('addressPreview');
            const previewText = document.getElementById('addressPreviewText');
            
            if (fullAddr) {
                // จัดรูปแบบที่อยู่ให้อ่านง่าย พร้อมเยื้อง
                let formattedAddr = '';
                if (house || moo || soi || road) {
                    const line1Parts = [];
                    if (house) line1Parts.push(house);
                    if (moo) line1Parts.push('หมู่ ' + moo);
                    if (line1Parts.length > 0) formattedAddr += line1Parts.join(' ');
                    
                    const line2Parts = [];
                    if (soi) line2Parts.push('ซอย ' + soi);
                    if (road) line2Parts.push('ถนน ' + road);
                    if (line2Parts.length > 0) {
                        if (formattedAddr) formattedAddr += '<br>';
                        formattedAddr += line2Parts.join(' ');
                    }
                }
                
                if (subdistrict || district || province || zipcode) {
                    if (formattedAddr) formattedAddr += '<br>';
                    const line3Parts = [];
                    if (subdistrict) line3Parts.push('ต.' + subdistrict);
                    if (district) line3Parts.push('อ.' + district);
                    if (province) line3Parts.push('จ.' + province);
                    if (zipcode) line3Parts.push(zipcode);
                    formattedAddr += line3Parts.join(' ');
                }
                
                previewText.innerHTML = formattedAddr;
                previewBox.style.display = 'block';
            } else {
                previewBox.style.display = 'none';
            }
        }
        
        // Combine address on any field change (input fields)
        document.querySelectorAll('.address-field input[type="text"]:not(#addrProvince):not(#addrDistrict):not(#addrSubdistrict)').forEach(input => {
            input.addEventListener('input', combineAddress);
        });
        
        // Also combine before form submit
        const bookingForm = document.getElementById('bookingForm');
        if (bookingForm) {
            bookingForm.addEventListener('submit', function(e) {
                // Validate terms checkbox
                const acceptTerms = document.getElementById('acceptTerms');
                const termsError = document.getElementById('termsError');
                
                if (!acceptTerms.checked) {
                    e.preventDefault();
                    termsError.style.display = 'flex';
                    acceptTerms.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return false;
                }
                
                termsError.style.display = 'none';
                combineAddress();
                
                // Note: localStorage for images will be cleared when success page loads
            }, true);
            
            // Hide error when checkbox is checked
            const acceptTerms = document.getElementById('acceptTerms');
            const termsError = document.getElementById('termsError');
            if (acceptTerms) {
                acceptTerms.addEventListener('change', function() {
                    if (this.checked) {
                        termsError.style.display = 'none';
                    }
                });
            }
        }
    </script>
</body>
</html>
