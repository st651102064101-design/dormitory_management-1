<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../ConnectDB.php';

$pdo = connectDB();

// Apply shared public background and scrollbar styles
include_once __DIR__ . '/../includes/public_theme.php';

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

// ดึงข้อมูล Tenant ที่ล็อกอินแล้ว (ถ้ามี)
$loggedInTenant = null;
if (!empty($_SESSION['tenant_logged_in']) && !empty($_SESSION['tenant_id'])) {
    try {
        $stmt = $pdo->prepare('SELECT tnt_name, tnt_phone FROM tenant WHERE tnt_id = ?');
        $stmt->execute([$_SESSION['tenant_id']]);
        $loggedInTenant = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

// ดึงห้องว่าง
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
    $maxSize = 5 * 1024 * 1024;
    
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
}

// Process form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roomId = (int)($_POST['room_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) > 10) {
        $phone = substr($phone, -10);
    }
    
    // Check if existing tenant was selected
    $existingTenantId = trim($_POST['existing_tenant_id'] ?? '');
    
    $ctrStart = $_POST['ctr_start'] ?? '';
    $ctrEnd = $_POST['ctr_end'] ?? '';
    $deposit = (int)($_POST['deposit'] ?? 2000);
    
    // Optional fields
    $age = (int)($_POST['age'] ?? 0);
    $address = trim($_POST['address'] ?? '');
    $education = trim($_POST['education'] ?? '');
    $faculty = trim($_POST['faculty'] ?? '');
    $year = trim($_POST['year'] ?? '');
    $vehicle = trim($_POST['vehicle'] ?? '');
    $parent = trim($_POST['parent'] ?? '');
    $parentsphone = trim($_POST['parentsphone'] ?? '');
    $parentsphone = preg_replace('/[^0-9]/', '', $parentsphone);
    
    // Validate
    $validationErrors = [];
    if (!$roomId) $validationErrors[] = 'ห้องพัก';
    if (!$name || strlen($name) < 4) $validationErrors[] = 'ชื่อ-นามสกุล';
    if (!$phone || strlen($phone) !== 10) $validationErrors[] = 'เบอร์โทรศัพท์ 10 หลัก';
    
    if (!empty($validationErrors)) {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน: ' . implode(', ', $validationErrors);
    } elseif (!$ctrStart || !$ctrEnd) {
        $error = 'กรุณาระบุวันที่เข้าพักและวันที่ออก';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT room_status, r.type_id, rt.type_price FROM room r LEFT JOIN roomtype rt ON r.type_id = rt.type_id WHERE room_id = ?");
            $stmt->execute([$roomId]);
            $roomData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$roomData || $roomData['room_status'] !== '0') {
                $error = 'ขออภัย ห้องนี้ถูกจองไปแล้ว';
            } else {
                $roomPrice = (int)($roomData['type_price'] ?? 1500);
                
                $pdo->beginTransaction();
                try {
                    // Skip existing booking/contract check if using existing tenant ID
                    $skipCheck = !empty($existingTenantId);
                    
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM booking b JOIN tenant t ON b.tnt_id = t.tnt_id WHERE t.tnt_phone = ? AND b.bkg_status IN ('1','2')");
                    $checkStmt->execute([$phone]);
                    $existing = $skipCheck ? 0 : (int)$checkStmt->fetchColumn();
                    
                    $checkContract = $pdo->prepare("SELECT COUNT(*) FROM contract c JOIN tenant t ON c.tnt_id = t.tnt_id WHERE t.tnt_phone = ? AND c.ctr_status = '0'");
                    $checkContract->execute([$phone]);
                    $existingContract = $skipCheck ? 0 : (int)$checkContract->fetchColumn();
                    
                    if ($existing > 0 || $existingContract > 0) {
                        $pdo->rollBack();
                        $error = 'เบอร์โทรศัพท์นี้มีการจองหรือสัญญาเช่าอยู่แล้ว';
                    } else {
                        // Check if using existing tenant or creating new one
                        if (!empty($existingTenantId)) {
                            // Use existing tenant ID
                            $tenantId = $existingTenantId;
                            
                            // Optionally update tenant info
                            $stmtUpdateTenant = $pdo->prepare("
                                UPDATE tenant SET 
                                    tnt_name = ?, 
                                    tnt_phone = ?,
                                    tnt_age = COALESCE(?, tnt_age),
                                    tnt_education = COALESCE(?, tnt_education),
                                    tnt_parent = COALESCE(?, tnt_parent),
                                    tnt_parentsphone = COALESCE(?, tnt_parentsphone),
                                    tnt_status = '3'
                                WHERE tnt_id = ?
                            ");
                            $stmtUpdateTenant->execute([
                                $name, 
                                $phone, 
                                $age ?: null, 
                                $education ?: null, 
                                $parent ?: null, 
                                $parentsphone ?: null, 
                                $tenantId
                            ]);
                        } else {
                            // Create new tenant
                            $tenantId = 'T' . time();
                            $stmtTenant = $pdo->prepare("
                                INSERT INTO tenant (tnt_id, tnt_name, tnt_age, tnt_address, tnt_phone, tnt_education, tnt_faculty, tnt_year, tnt_vehicle, tnt_parent, tnt_parentsphone, tnt_status, tnt_ceatetime)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '3', NOW())
                            ");
                            $stmtTenant->execute([$tenantId, $name, $age ?: null, $address ?: null, $phone, $education ?: null, $faculty ?: null, $year ?: null, $vehicle ?: null, $parent ?: null, $parentsphone ?: null]);
                        }
                        
                        // Generate booking ID (use last 9 digits to fit INT(11) max: 2147483647)
                        $bookingId = (int)substr((string)time(), -9);
                        $stmtBooking = $pdo->prepare("
                            INSERT INTO booking (bkg_id, room_id, tnt_id, bkg_checkin_date, bkg_status, bkg_date)
                            VALUES (?, ?, ?, ?, '1', NOW())
                        ");
                        $stmtBooking->execute([$bookingId, $roomId, $tenantId, $ctrStart]);
                        
                        // Generate contract ID (integer, add 1 to avoid collision with booking ID)
                        $contractId = (int)substr((string)time(), -9) + 1;
                        $accessToken = md5($tenantId . '-' . $roomId . '-' . time() . '-' . bin2hex(random_bytes(8)));
                        $stmtContract = $pdo->prepare("
                            INSERT INTO contract (ctr_id, ctr_start, ctr_end, ctr_deposit, ctr_status, tnt_id, room_id, access_token)
                            VALUES (?, ?, ?, ?, '0', ?, ?, ?)
                        ");
                        $stmtContract->execute([$contractId, $ctrStart, $ctrEnd, $deposit, $tenantId, $roomId, $accessToken]);
                        
                        // Generate expense ID (integer, add 2 to avoid collision)
                        $expenseId = (int)substr((string)time(), -9) + 2;
                        $stmtExpense = $pdo->prepare("
                            INSERT INTO expense (exp_id, exp_month, exp_elec_unit, exp_water_unit, rate_elec, rate_water, room_price, exp_elec_chg, exp_water, exp_total, exp_status, ctr_id)
                            VALUES (?, ?, 0, 0, ?, ?, ?, 0, 0, ?, '2', ?)
                        ");
                        $stmtExpense->execute([$expenseId, date('Y-m-01'), $rateElec, $rateWater, $roomPrice, $deposit, $contractId]);
                        
                        // อัพโหลดหลักฐานการชำระมัดจำ
                        $paymentDir = __DIR__ . '/Assets/Images/Payments';
                        $payProof = null;
                        if (!empty($_FILES['pay_proof']['name'])) {
                            $payProof = uploadFile($_FILES['pay_proof'], $paymentDir, 'payment');
                        }
                        
                        // บันทึก payment record (ค่ามัดจำ 2000 บาท) - บันทึกทุกครั้งแม้ยังไม่มีสลิป
                        $paymentId = (int)substr((string)time(), -9) + 3;
                        $depositAmount = 2000; // ค่ามัดจำคงที่
                        $payStatus = $payProof ? '0' : '0'; // 0 = รอตรวจสอบ (ไม่ว่าจะมีสลิปหรือไม่)
                        
                        $stmtPayment = $pdo->prepare("
                            INSERT INTO payment (pay_id, pay_date, pay_amount, pay_proof, pay_status, exp_id)
                            VALUES (?, NOW(), ?, ?, ?, ?)
                        ");
                        $stmtPayment->execute([$paymentId, $depositAmount, $payProof, $payStatus, $expenseId]);
                        
                        // exp_status = '2' (กำลังดำเนินการ) ตั้งแต่ตอนสร้างแล้ว ไม่ต้อง update อีก
                        
                        $updateRoom = $pdo->prepare("UPDATE room SET room_status = '1' WHERE room_id = ?");
                        $updateRoom->execute([$roomId]);
                        
                        $pdo->commit();
                        $success = true;
                        // Store IDs for success page display
                        $_SESSION['last_booking_id'] = $bookingId;
                        $_SESSION['last_tenant_id'] = $tenantId;
                    }
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log("Booking error (inner): " . $e->getMessage());
                    $error = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage();
                }
            }
        } catch (PDOException $e) {
            error_log("Booking error (outer): " . $e->getMessage());
            $error = 'เกิดข้อผิดพลาดในการตรวจสอบห้อง: ' . $e->getMessage();
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
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Prompt', system-ui, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            color: #e2e8f0;
        }
        
        /* Header */
        .header {
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #fff;
        }
        
        .logo img {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            object-fit: cover;
        }
        
        .logo-text {
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .back-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            color: #e2e8f0;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .back-btn:hover {
            background: rgba(255,255,255,0.15);
        }
        
        /* Main Container */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 24px;
        }
        
        /* Page Title */
        .page-title {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .page-title h1 {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .page-title p {
            color: #94a3b8;
            font-size: 1rem;
        }
        
        /* Two Column Layout */
        .booking-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 32px;
            align-items: start;
        }
        
        /* Room Selection */
        .room-section {
            background: rgba(30, 41, 59, 0.5);
            border-radius: 20px;
            padding: 28px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-title svg {
            width: 24px;
            height: 24px;
            color: #60a5fa;
        }
        
        .room-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 16px;
            width: 100%;
        }
        
        .room-card {
            background: rgba(15, 23, 42, 0.6);
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        
        .room-card:hover {
            border-color: rgba(96, 165, 250, 0.5);
            transform: translateY(-2px);
        }
        
        .room-card.selected {
            border-color: #22c55e;
            background: rgba(34, 197, 94, 0.1);
        }
        
        .room-card.selected::after {
            content: '✓';
            position: absolute;
            top: 12px;
            right: 12px;
            width: 24px;
            height: 24px;
            background: #22c55e;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: white;
        }
        
        .room-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 8px;
        }
        
        .room-type {
            font-size: 0.85rem;
            color: #94a3b8;
            margin-bottom: 12px;
        }
        
        .room-price {
            font-size: 1.1rem;
            font-weight: 600;
            color: #22c55e;
        }
        
        .room-price span {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 400;
        }
        
        /* No Rooms */
        .no-rooms {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
        
        .no-rooms svg {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        /* Booking Sidebar */
        .booking-sidebar {
            position: sticky;
            top: 100px;
        }
        
        .booking-box {
            background: rgba(30, 41, 59, 0.8);
            border-radius: 20px;
            padding: 28px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .booking-box-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Selected Room Display */
        .selected-room-display {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
            display: none;
        }
        
        .selected-room-display.show {
            display: block;
        }
        
        .selected-room-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .selected-room-name {
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .selected-room-price {
            color: #22c55e;
            font-weight: 600;
        }
        
        /* Form Inputs */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-size: 0.9rem;
            color: #94a3b8;
            margin-bottom: 8px;
        }
        
        .form-label .required {
            color: #ef4444;
        }
        
        .form-input {
            width: 100%;
            padding: 14px 16px;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 12px;
            color: #fff;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #60a5fa;
            background: rgba(15, 23, 42, 0.8);
        }
        
        .form-input::placeholder {
            color: #64748b;
        }
        
        /* Autocomplete Suggestions */
        .autocomplete-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: rgba(15, 23, 42, 0.98);
            border: 1px solid rgba(96, 165, 250, 0.3);
            border-radius: 12px;
            margin-top: 4px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 10px 40px rgba(0,0,0,0.4);
            backdrop-filter: blur(10px);
        }
        
        .autocomplete-item {
            padding: 14px 16px;
            cursor: pointer;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            transition: all 0.2s;
        }
        
        .autocomplete-item:last-child {
            border-bottom: none;
        }
        
        .autocomplete-item:hover {
            background: rgba(96, 165, 250, 0.15);
        }
        
        .autocomplete-item-name {
            font-weight: 600;
            color: #fff;
            font-size: 1rem;
            margin-bottom: 4px;
        }
        
        .autocomplete-item-info {
            font-size: 0.85rem;
            color: #94a3b8;
        }
        
        .autocomplete-item-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            margin-left: 8px;
        }
        
        .autocomplete-item-status.active {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }
        
        .autocomplete-item-status.inactive {
            background: rgba(148, 163, 184, 0.2);
            color: #94a3b8;
        }
        
        .autocomplete-new {
            padding: 14px 16px;
            background: rgba(34, 197, 94, 0.1);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .autocomplete-new:hover {
            background: rgba(34, 197, 94, 0.2);
        }
        
        .autocomplete-new-icon {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #22c55e;
            font-weight: 600;
        }
        
        .tenant-selected-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(34, 197, 94, 0.15);
            color: #22c55e;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-top: 8px;
        }
        
        .tenant-selected-badge button {
            background: none;
            border: none;
            color: #ef4444;
            cursor: pointer;
            padding: 2px;
            display: flex;
            align-items: center;
        }

        /* Duration Pills */
        .duration-label {
            font-size: 0.9rem;
            color: #94a3b8;
            margin-bottom: 12px;
            display: block;
        }
        
        .duration-pills {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .duration-pill {
            padding: 12px 20px;
            background: rgba(15, 23, 42, 0.6);
            border: 2px solid rgba(255,255,255,0.15);
            border-radius: 30px;
            color: #e2e8f0;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s;
            flex: 1;
            text-align: center;
            min-width: 80px;
        }
        
        .duration-pill:hover {
            border-color: rgba(96, 165, 250, 0.5);
        }
        
        .duration-pill.selected {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border-color: #3b82f6;
            color: #fff;
        }
        
        /* Start Month Select */
        .form-select {
            width: 100%;
            padding: 14px 16px;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 12px;
            color: #fff;
            font-size: 1rem;
            font-family: inherit;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 20px;
        }
        
        .form-select:focus {
            outline: none;
            border-color: #60a5fa;
        }
        
        .form-select option {
            background: #1e293b;
            color: #fff;
        }
        
        /* Contract Summary */
        .contract-summary {
            background: rgba(15, 23, 42, 0.4);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 0.9rem;
        }
        
        .summary-row:not(:last-child) {
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .summary-label {
            color: #94a3b8;
        }
        
        .summary-value {
            font-weight: 500;
        }
        
        .summary-total {
            font-size: 1.1rem;
            color: #22c55e;
            font-weight: 600;
        }
        
        /* Form Steps */
        .form-steps {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 30px;
            padding: 0 20px;
        }
        
        .form-step {
            flex: 1;
            max-width: 200px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            transition: all 0.3s;
        }
        
        .form-step.active {
            background: linear-gradient(135deg, rgba(96, 165, 250, 0.2), rgba(59, 130, 246, 0.1));
            border-color: #60a5fa;
        }
        
        .form-step.completed {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(16, 185, 129, 0.1));
            border-color: #22c55e;
        }
        
        .step-number {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
            flex-shrink: 0;
        }
        
        .form-step.active .step-number {
            background: #60a5fa;
            color: #fff;
        }
        
        .form-step.completed .step-number {
            background: #22c55e;
            color: #fff;
        }
        
        .step-label {
            font-size: 0.9rem;
            color: #94a3b8;
        }
        
        .form-step.active .step-label {
            color: #60a5fa;
            font-weight: 600;
        }
        
        .form-step.completed .step-label {
            color: #22c55e;
            font-weight: 600;
        }
        
        .step-content {
            display: none;
            visibility: hidden;
            height: 0;
            overflow: hidden;
            position: absolute;
            pointer-events: none;
        }
        
        .step-content.active {
            display: block;
            visibility: visible;
            height: auto;
            position: relative;
            pointer-events: auto;
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .step-buttons {
            display: flex;
            gap: 12px;
            justify-content: space-between;
            margin-top: 24px;
        }
        
        .btn-prev,
        .btn-next {
            padding: 14px 28px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            border: none;
            font-size: 1rem;
        }
        
        .btn-prev {
            background: rgba(255,255,255,0.1);
            color: #e2e8f0;
        }
        
        .btn-prev:hover {
            background: rgba(255,255,255,0.15);
        }
        
        .btn-next {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            margin-left: auto;
        }
        
        .btn-next:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.4);
        }
        
        @media (max-width: 768px) {
            .form-steps {
                flex-direction: column;
                gap: 8px;
            }
            
            .form-step {
                max-width: 100%;
            }
            
            .step-buttons {
                flex-direction: column;
            }
            
            .btn-next {
                margin-left: 0;
            }
        }

        /* Optional Toggle */
        .optional-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px;
            background: rgba(15, 23, 42, 0.4);
            border-radius: 12px;
            cursor: pointer;
            margin-bottom: 16px;
            transition: all 0.3s;
        }
        
        .optional-toggle:hover {
            background: rgba(15, 23, 42, 0.6);
        }
        
        .optional-toggle svg {
            width: 20px;
            height: 20px;
            color: #60a5fa;
            transition: transform 0.3s;
        }
        
        .optional-toggle.open svg {
            transform: rotate(180deg);
        }
        
        .optional-toggle span {
            font-size: 0.9rem;
            color: #94a3b8;
        }
        
        .optional-fields {
            display: none;
            padding-top: 8px;
        }
        
        .optional-fields.show {
            display: block;
        }
        
        /* Submit Button */
        .submit-btn {
            width: 100%;
            padding: 16px 24px;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            border: none;
            border-radius: 14px;
            color: #fff;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(34, 197, 94, 0.3);
        }
        
        .submit-btn:disabled {
            background: #475569;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .submit-btn svg {
            width: 22px;
            height: 22px;
        }
        
        /* Trust Badges */
        .trust-badges {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .trust-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: #64748b;
        }
        
        .trust-badge svg {
            width: 16px;
            height: 16px;
            color: #22c55e;
        }
        
        /* Messages */
        .message {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .message.error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }
        
        .message.success {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #86efac;
        }
        
        .message svg {
            width: 24px;
            height: 24px;
            flex-shrink: 0;
        }
        
        /* Success Page */
        .success-page {
            text-align: center;
            padding: 60px 20px;
        }
        
        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        
        .success-icon svg {
            width: 50px;
            height: 50px;
            color: white;
        }
        
        .success-title {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 12px;
        }
        
        .success-message {
            color: #94a3b8;
            margin-bottom: 32px;
        }
        
        .success-actions {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .success-btn {
            padding: 14px 28px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .success-btn.primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
        }
        
        .success-btn.secondary {
            background: rgba(255,255,255,0.1);
            color: #e2e8f0;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        /* Mobile Bottom Bar */
        .mobile-booking-bar {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(15, 23, 42, 0.98);
            backdrop-filter: blur(20px);
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 20px;
            z-index: 1000;
            box-shadow: 0 -10px 40px rgba(0,0,0,0.3);
        }
        
        .mobile-bar-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        
        .mobile-bar-info {
            flex: 1;
        }
        
        .mobile-bar-room {
            font-weight: 600;
            font-size: 1rem;
            color: #fff;
        }
        
        .mobile-bar-price {
            font-size: 0.9rem;
            color: #22c55e;
        }
        
        .mobile-bar-btn {
            padding: 14px 28px;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
        }
        
        /* Mobile Form Modal */
        .mobile-form-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(4px);
            z-index: 2000;
            align-items: flex-end;
            justify-content: center;
        }
        
        .mobile-form-overlay.show {
            display: flex;
        }
        
        .mobile-form-sheet {
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            width: 100%;
            max-height: 90vh;
            border-radius: 24px 24px 0 0;
            overflow: hidden;
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from { transform: translateY(100%); }
            to { transform: translateY(0); }
        }
        
        .mobile-form-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            position: sticky;
            top: 0;
            background: inherit;
            z-index: 10;
        }
        
        .mobile-form-title {
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .mobile-form-close {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            border: none;
            color: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .mobile-form-body {
            padding: 24px;
            overflow-y: auto;
            max-height: calc(90vh - 80px);
        }
        
        /* Validation Styles for Mobile Form */
        .form-input.invalid,
        .form-select.invalid {
            border: 2px solid #ef4444 !important;
            background: rgba(239, 68, 68, 0.05) !important;
            animation: shake 0.4s ease-in-out;
        }
        
        .form-input.valid {
            border: 2px solid #10b981 !important;
            background: rgba(16, 185, 129, 0.05) !important;
        }
        
        .error-message {
            color: #ef4444;
            font-size: 12px;
            margin-top: 6px;
            display: none;
            animation: slideDown 0.3s ease-out;
            font-weight: 500;
        }
        
        .error-message.show {
            display: block;
        }
        
        .error-message::before {
            content: '⚠️ ';
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-4px); }
            20%, 40%, 60%, 80% { transform: translateX(4px); }
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .mobile-room-summary {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .mobile-room-summary .room-name {
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .mobile-room-summary .room-price {
            color: #22c55e;
            font-weight: 600;
        }
        
        /* Mobile Styles */
        @media (max-width: 900px) {
            .booking-layout {
                grid-template-columns: 1fr;
            }
            
            .booking-sidebar {
                display: none;
            }
            
            .mobile-booking-bar {
                display: block;
            }
            
            .room-section {
                margin-bottom: 100px;
            }
            
            .room-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 12px;
            }
            
            .room-card {
                padding: 14px 12px;
                border-radius: 12px;
            }
            
            .room-number {
                font-size: 1.5rem;
                margin-bottom: 6px;
            }
            
            .room-type {
                font-size: 0.8rem;
                margin-bottom: 8px;
            }
            
            .room-price {
                font-size: 0.9rem;
            }
            
            .room-price span {
                font-size: 0.7rem;
            }
            
            .page-title h1 {
                font-size: 1.5rem;
            }
            
            .page-title {
                margin-bottom: 24px;
            }
            
            .header {
                padding: 12px 16px;
            }
            
            .header-content {
                padding: 0;
            }
            
            .logo-text {
                display: none;
            }
            
            .back-btn {
                padding: 8px 12px;
                font-size: 0.85rem;
            }
            
            .back-btn span {
                display: none;
            }
            
            .room-section {
                padding: 20px;
                border-radius: 16px;
            }
            
            .section-title {
                font-size: 1.1rem;
                margin-bottom: 16px;
            }
            
            .room-card {
                padding: 16px;
            }
            
            .room-number {
                font-size: 1.3rem;
            }
            
            .main-container {
                padding: 20px 16px;
            }
        }
        
        @media (max-width: 480px) {
            .room-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
                gap: 10px;
            }
            
            .room-card {
                padding: 12px 10px;
                border-radius: 10px;
            }
            
            .room-number {
                font-size: 1.3rem;
                margin-bottom: 4px;
            }
            
            .room-type {
                font-size: 0.75rem;
                margin-bottom: 6px;
            }
            
            .room-price {
                font-size: 0.85rem;
            }
            
            .room-price span {
                font-size: 0.65rem;
            }
                font-size: 1rem;
            }
            
            .mobile-bar-btn {
                padding: 12px 20px;
                font-size: 0.95rem;
            }
        }
        
        /* Hidden inputs */
        input[type="radio"].room-radio {
            display: none;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="/dormitory_management/" class="logo">
                <img src="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="Logo">
                <span class="logo-text"><?php echo htmlspecialchars($siteName); ?></span>
            </a>
            <a href="/dormitory_management/" class="back-btn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                กลับหน้าหลัก
            </a>
        </div>
    </header>

    <div class="main-container">
        <?php if ($success): ?>
        <?php
        $lastBookingId = $_SESSION['last_booking_id'] ?? '';
        $lastTenantId = $_SESSION['last_tenant_id'] ?? '';
        // Clear session data after retrieval
        unset($_SESSION['last_booking_id'], $_SESSION['last_tenant_id']);
        ?>
        <!-- Success Page -->
        <div class="success-page">
            <div class="success-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </div>
            <h1 class="success-title">จองห้องพักสำเร็จ!</h1>
            <p class="success-message">ขอบคุณที่ไว้วางใจ เจ้าหน้าที่จะติดต่อกลับเพื่อยืนยันการจองภายใน 24 ชั่วโมง</p>
            
            <!-- Booking Reference Section -->
            <div style="background: rgba(16, 185, 129, 0.1); border: 2px solid #10b981; border-radius: 16px; padding: 16px; margin: 24px 0; text-align: center;" id="bookingReferenceSection">
                <p style="color: #10b981; font-size: 14px; font-weight: 600; margin: 0 0 12px 0; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <svg class="animated-clipboard" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                    </svg>
                    หมายเลขการจองของคุณ
                </p>
                <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-bottom: 16px; max-width: 100%;">
                    <?php if ($lastBookingId): ?>
                    <div style="background: rgba(255,255,255,0.1); padding: 12px 16px; border-radius: 12px; min-width: 160px; max-width: 100%; position: relative; flex: 1; overflow: hidden;">
                        <div style="font-size: 11px; color: rgba(255,255,255,0.7); margin-bottom: 4px;">เลขที่การจอง</div>
                        <div style="font-size: clamp(18px, 5vw, 28px); font-weight: 700; color: #ffffff; font-family: 'Courier New', monospace; letter-spacing: 1px; word-break: break-all; overflow-wrap: break-word; line-height: 1.2;" id="bookingIdText"><?php echo htmlspecialchars((string)$lastBookingId); ?></div>
                        <button onclick="copyBookingId()" style="position: absolute; top: 6px; right: 6px; background: rgba(16, 185, 129, 0.2); border: none; padding: 6px; border-radius: 6px; cursor: pointer; color: #10b981; font-size: 10px; transition: all 0.2s; display: flex; align-items: center; justify-content: center;">
                            <svg class="animated-clipboard" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                        </button>
                    </div>
                    <?php endif; ?>
                    <?php if ($lastTenantId): ?>
                    <div style="background: rgba(255,255,255,0.1); padding: 12px 16px; border-radius: 12px; min-width: 160px; max-width: 100%; position: relative; flex: 1; overflow: hidden;">
                        <div style="font-size: 11px; color: rgba(255,255,255,0.7); margin-bottom: 4px;">รหัสผู้เช่า</div>
                        <div style="font-size: clamp(18px, 5vw, 28px); font-weight: 700; color: #ffffff; font-family: 'Courier New', monospace; letter-spacing: 1px; word-break: break-all; overflow-wrap: break-word; line-height: 1.2;" id="tenantIdText"><?php echo htmlspecialchars($lastTenantId); ?></div>
                        <button onclick="copyTenantId()" style="position: absolute; top: 6px; right: 6px; background: rgba(16, 185, 129, 0.2); border: none; padding: 6px; border-radius: 6px; cursor: pointer; color: #10b981; font-size: 10px; transition: all 0.2s; display: flex; align-items: center; justify-content: center;">
                            <svg class="animated-clipboard" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Quick Action Buttons -->
                <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin: 20px 0; position: relative; z-index: 9999;">
                    <button type="button" onclick="copyAllInfo(); return false;" style="background: #10b981; color: white; border: none; padding: 12px 20px; border-radius: 10px; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); font-size: 14px; white-space: nowrap; -webkit-tap-highlight-color: transparent; touch-action: manipulation; position: relative; z-index: 9999; pointer-events: auto;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0; pointer-events: none;">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                        </svg>
                        <span style="pointer-events: none;">คัดลอกทั้งหมด</span>
                    </button>
                    
                    <button type="button" onclick="shareBookingInfo(); return false;" style="background: #3b82f6; color: white; border: none; padding: 12px 20px; border-radius: 10px; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); font-size: 14px; white-space: nowrap; -webkit-tap-highlight-color: transparent; touch-action: manipulation; position: relative; z-index: 9999; pointer-events: auto;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0; pointer-events: none;">
                            <circle cx="18" cy="5" r="3"></circle>
                            <circle cx="6" cy="12" r="3"></circle>
                            <circle cx="18" cy="19" r="3"></circle>
                            <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                            <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                        </svg>
                        <span style="pointer-events: none;">แชร์ข้อมูล</span>
                    </button>
                    
                    <button type="button" onclick="saveToNotes(); return false;" style="background: #8b5cf6; color: white; border: none; padding: 12px 20px; border-radius: 10px; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3); font-size: 14px; white-space: nowrap; -webkit-tap-highlight-color: transparent; touch-action: manipulation; position: relative; z-index: 9999; pointer-events: auto;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0; pointer-events: none;">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                            <polyline points="7 3 7 8 15 8"></polyline>
                        </svg>
                        <span style="pointer-events: none;">บันทึกข้อมูล</span>
                    </button>
                </div>
                
                <p style="color: rgba(255,255,255,0.8); font-size: 14px; margin: 16px 0 0 0; line-height: 1.6; display: flex; align-items: flex-start; gap: 6px; flex-wrap: wrap;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 2px;"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/></svg>
                    <span><strong>กรุณาบันทึกหมายเลขนี้</strong> เพื่อใช้ตรวจสอบสถานะการจองและข้อมูลการชำระเงินภายหลัง<br>
                    <a href="/dormitory_management/Public/booking_status.php" style="color: #10b981; text-decoration: underline; font-weight: 600;">คลิกที่นี่เพื่อตรวจสอบสถานะ</a></span>
                </p>
            </div>
            
            <div class="success-actions">
                <a href="/dormitory_management/" class="success-btn primary">กลับหน้าหลัก</a>
                <a href="/dormitory_management/Public/booking.php" class="success-btn secondary">จองห้องอื่น</a>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Page Title -->
        <div class="page-title">
            <h1 style="display: flex; align-items: center; justify-content: center; gap: 12px;">
                <svg class="animated-home" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                จองห้องพัก
            </h1>
            <p>เลือกห้องและกรอกข้อมูลเพียง 3 ขั้นตอนง่ายๆ</p>
        </div>
        
        <?php if ($error): ?>
        <div class="message error">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data" id="bookingForm" novalidate>
            <div class="booking-layout">
                <!-- Left: Room Selection -->
                <div class="room-section">
                    <h2 class="section-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                            <polyline points="9 22 9 12 15 12 15 22"/>
                        </svg>
                        <?php echo $selectedRoom ? 'ห้องที่เลือก' : 'เลือกห้องพัก'; ?>
                    </h2>
                    
                    <?php if (empty($availableRooms)): ?>
                    <div class="no-rooms">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                            <line x1="9" y1="15" x2="15" y2="9"/>
                            <line x1="9" y1="9" x2="15" y2="15"/>
                        </svg>
                        <h3>ไม่มีห้องว่างในขณะนี้</h3>
                        <p>กรุณาติดต่อเจ้าหน้าที่หรือกลับมาใหม่ภายหลัง</p>
                    </div>
                    <?php elseif ($selectedRoom): ?>
                    <!-- แสดงเฉพาะห้องที่เลือก -->
                    <div class="room-grid">
                        <label class="room-card selected" 
                               data-room-id="<?php echo $selectedRoom['room_id']; ?>"
                               data-price="<?php echo $selectedRoom['type_price'] ?? 0; ?>"
                               data-type="<?php echo htmlspecialchars($selectedRoom['type_name'] ?? 'ห้องมาตรฐาน'); ?>">
                            <input type="radio" name="room_id" value="<?php echo $selectedRoom['room_id']; ?>" 
                                   class="room-radio" checked>
                            <div class="room-number"><?php echo htmlspecialchars($selectedRoom['room_number']); ?></div>
                            <div class="room-type"><?php echo htmlspecialchars($selectedRoom['type_name'] ?? 'ห้องมาตรฐาน'); ?></div>
                            <div class="room-price">
                                ฿<?php echo number_format($selectedRoom['type_price'] ?? 0); ?> <span>/เดือน</span>
                            </div>
                        </label>
                    </div>
                    <div style="text-align: center; margin-top: 12px;">
                        <a href="booking.php" style="color: var(--accent-color, #60a5fa); text-decoration: none; font-size: 14px; opacity: 0.8;">
                            <svg style="width: 14px; height: 14px; vertical-align: middle; margin-right: 4px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="15 18 9 12 15 6"/>
                            </svg>
                            ดูห้องอื่น
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="room-grid">
                        <?php foreach ($availableRooms as $room): ?>
                        <label class="room-card <?php echo ($selectedRoom && $selectedRoom['room_id'] == $room['room_id']) ? 'selected' : ''; ?>" 
                               data-room-id="<?php echo $room['room_id']; ?>"
                               data-price="<?php echo $room['type_price'] ?? 0; ?>"
                               data-type="<?php echo htmlspecialchars($room['type_name'] ?? 'ห้องมาตรฐาน'); ?>">
                            <input type="radio" name="room_id" value="<?php echo $room['room_id']; ?>" 
                                   class="room-radio"
                                   <?php echo ($selectedRoom && $selectedRoom['room_id'] == $room['room_id']) ? 'checked' : ''; ?>>
                            <div class="room-number"><?php echo htmlspecialchars($room['room_number']); ?></div>
                            <div class="room-type"><?php echo htmlspecialchars($room['type_name'] ?? 'ห้องมาตรฐาน'); ?></div>
                            <div class="room-price">
                                ฿<?php echo number_format($room['type_price'] ?? 0); ?> <span>/เดือน</span>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Right: Booking Form -->
                <div class="booking-sidebar">
                    <div class="booking-box">
                        <!-- Form Steps Indicator -->
                        <div class="form-steps">
                            <div class="form-step active" data-step="1">
                                <div class="step-number">1</div>
                                <div class="step-label">ข้อมูลการจอง</div>
                            </div>
                            <div class="form-step" data-step="2">
                                <div class="step-number">2</div>
                                <div class="step-label">ชำระเงิน</div>
                            </div>
                        </div>
                        
                        <!-- Step 1: Booking Information -->
                        <div class="step-content active" data-step="1">
                            <h3 class="booking-box-title">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M16 4h2a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h2"/>
                                    <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
                                </svg>
                                ข้อมูลการจอง
                            </h3>
                            
                            <!-- Selected Room Display -->
                            <div class="selected-room-display" id="selectedRoomDisplay">
                                <div class="selected-room-info">
                                    <span class="selected-room-name" id="selectedRoomName">ห้อง -</span>
                                    <span class="selected-room-price" id="selectedRoomPrice">฿0/เดือน</span>
                                </div>
                            </div>
                        
                        <!-- Name with Autocomplete -->
                        <div class="form-group" style="position: relative;">
                            <label class="form-label">ชื่อ-นามสกุล <span class="required">*</span></label>
                            <input type="text" name="name" id="tenantNameInput" class="form-input" placeholder="พิมพ์ชื่อเพื่อค้นหาผู้เช่าเดิม หรือกรอกชื่อใหม่" 
                                   value="<?php echo htmlspecialchars($loggedInTenant['tnt_name'] ?? ''); ?>" required autocomplete="off">
                            <input type="hidden" name="existing_tenant_id" id="existingTenantId" value="">
                            <div id="tenantSuggestions" class="autocomplete-suggestions" style="display: none;"></div>
                            <div class="form-hint" style="font-size: 0.75rem; color: #94a3b8; margin-top: 4px; display: flex; align-items: center; gap: 4px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/></svg>
                                พิมพ์ชื่อเพื่อค้นหาผู้เช่าเดิม หรือกรอกข้อมูลใหม่ทั้งหมด
                                <?php if ($loggedInTenant): ?>
                                <span style="color: #3b82f6;">💡 ข้อมูลจาก Google Account</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Phone -->
                        <div class="form-group">
                            <label class="form-label">เบอร์โทรศัพท์ <span class="required">*</span></label>
                            <input type="tel" name="phone" class="form-input" placeholder="0812345678" maxlength="10" 
                                   value="<?php echo htmlspecialchars($loggedInTenant['tnt_phone'] ?? ''); ?>" required>
                            <?php if ($loggedInTenant): ?>
                            <div class="form-hint" style="font-size: 0.75rem; color: #3b82f6; margin-top: 4px;">💡 ข้อมูลจาก Google Account</div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Start Month -->
                        <div class="form-group">
                            <label class="form-label">เดือนที่เริ่มเข้าพัก</label>
                            <select name="ctr_start_month" id="ctrStartMonth" class="form-select">
                                <?php
                                date_default_timezone_set('Asia/Bangkok');
                                $currentMonth = (int)date('n');
                                $currentYear = (int)date('Y');
                                $thaiMonths = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
                                
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
                            <input type="hidden" name="ctr_start" id="ctrStart" value="<?php echo date('Y-m-01'); ?>">
                        </div>
                        
                        <!-- Duration Pills -->
                        <label class="duration-label">ระยะเวลาสัญญา</label>
                        <div class="duration-pills">
                            <div class="duration-pill" data-months="6">6 เดือน</div>
                            <div class="duration-pill selected" data-months="12">1 ปี</div>
                            <div class="duration-pill" data-months="24">2 ปี</div>
                        </div>
                        <input type="hidden" name="ctr_duration" id="ctrDuration" value="12">
                        <input type="hidden" name="ctr_end" id="ctrEnd" value="<?php echo date('Y-m-d', strtotime('+12 months')); ?>">
                        <input type="hidden" name="deposit" value="<?php echo $defaultDeposit; ?>">
                        
                        <!-- Contract Summary -->
                        <div class="contract-summary">
                            <div class="summary-row">
                                <span class="summary-label">เริ่มสัญญา</span>
                                <span class="summary-value" id="summaryStart">-</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">สิ้นสุดสัญญา</span>
                                <span class="summary-value" id="summaryEnd">-</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">เงินมัดจำ</span>
                                <span class="summary-value summary-total">฿<?php echo number_format($defaultDeposit); ?></span>
                            </div>
                        </div>
                        
                        <!-- Optional Fields Toggle -->
                        <div class="optional-toggle" onclick="toggleOptional()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                            <span>ข้อมูลเพิ่มเติม (ไม่บังคับ)</span>
                        </div>
                        
                        <div class="optional-fields" id="optionalFields">
                            <div class="form-group">
                                <label class="form-label">อายุ</label>
                                <input type="number" name="age" class="form-input" placeholder="เช่น 20" min="15" max="99">
                            </div>
                            <div class="form-group">
                                <label class="form-label">สถานศึกษา</label>
                                <input type="text" name="education" class="form-input" placeholder="ชื่อมหาวิทยาลัย">
                            </div>
                            <div class="form-group">
                                <label class="form-label">ชื่อผู้ปกครอง</label>
                                <input type="text" name="parent" class="form-input" placeholder="ชื่อ-นามสกุลผู้ปกครอง">
                            </div>
                            <div class="form-group">
                                <label class="form-label">เบอร์ผู้ปกครอง</label>
                                <input type="tel" name="parentsphone" class="form-input" placeholder="0812345678" maxlength="10">
                            </div>
                        </div>
                        
                        <!-- Step Buttons -->
                        <div class="step-buttons">
                            <button type="button" class="btn-next" onclick="goToStep(2)">
                                <span>ถัดไป: ชำระเงิน</span>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="9 18 15 12 9 6"/>
                                </svg>
                            </button>
                        </div>
                        </div>
                        
                        <!-- Step 2: Payment -->
                        <div class="step-content" data-step="2">
                            <h3 class="booking-box-title">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                                    <line x1="1" y1="10" x2="23" y2="10"/>
                                </svg>
                                ชำระค่ามัดจำ
                            </h3>
                            
                        <!-- Payment Section -->
                        <div class="payment-section" style="margin: 24px 0; padding: 20px; background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(251, 191, 36, 0.05) 100%); border-radius: 16px; border: 1px solid rgba(245, 158, 11, 0.3);">
                            <!-- Payment Info -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                                <div style="background: rgba(0,0,0,0.2); padding: 12px; border-radius: 10px;">
                                    <div style="font-size: 0.8rem; color: #94a3b8; margin-bottom: 4px;">ค่ามัดจำ</div>
                                    <div id="paymentAmountDisplay" style="font-size: 1.2rem; font-weight: 700; color: #fbbf24;">฿2,000</div>
                                    <input type="hidden" name="payment_amount" id="paymentAmountInput" value="2000">
                                </div>
                                <?php if (!empty($bankAccountNumber)): ?>
                                <div style="background: rgba(0,0,0,0.2); padding: 12px; border-radius: 10px;">
                                    <div style="font-size: 0.8rem; color: #94a3b8; margin-bottom: 4px;">เลขบัญชี</div>
                                    <div style="font-size: 1rem; font-weight: 600; color: #fff; font-family: monospace;"><?php echo htmlspecialchars($bankAccountNumber); ?></div>
                                    <?php if (!empty($bankName)): ?>
                                    <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 2px;"><?php echo htmlspecialchars($bankName); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($promptpayNumber)): ?>
                            <!-- PromptPay QR Code -->
                            <div style="text-align: center; margin-bottom: 16px;">
                                <div style="background: white; padding: 15px; border-radius: 12px; display: inline-block; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                                    <img id="qrCodeImage" 
                                         src="https://promptpay.io/<?php echo urlencode($promptpayNumber); ?>/2000.png" 
                                         alt="PromptPay QR Code" 
                                         style="width: 180px; height: auto; display: block;">
                                </div>
                                <div style="margin-top: 8px; font-size: 0.8rem; color: #94a3b8;">
                                    สแกน QR พร้อมเพย์: <span style="color: #fbbf24; font-weight: 600;"><?php echo htmlspecialchars($promptpayNumber); ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Upload Slip -->
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" style="color: #fbbf24; margin-bottom: 8px;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 6px;">
                                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                                        <polyline points="17 8 12 3 7 8"/>
                                        <line x1="12" y1="3" x2="12" y2="15"/>
                                    </svg>
                                    อัพโหลดสลิปการโอนเงิน (ไม่บังคับ)
                                </label>
                                <div class="upload-zone" id="paymentUploadZone" style="border: 2px dashed rgba(245, 158, 11, 0.4); border-radius: 12px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.3s; background: rgba(0,0,0,0.2);">
                                    <input type="file" name="pay_proof" id="payProofInput" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" style="display: none;">
                                    <div id="uploadPlaceholder">
                                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="1.5" style="margin-bottom: 10px;">
                                            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                                            <polyline points="17 8 12 3 7 8"/>
                                            <line x1="12" y1="3" x2="12" y2="15"/>
                                        </svg>
                                        <p style="color: #e2e8f0; font-size: 0.9rem; margin-bottom: 4px;">คลิกเพื่อเลือกไฟล์</p>
                                        <p style="color: #64748b; font-size: 0.75rem;">รองรับ JPG, PNG, PDF (ไม่เกิน 5MB)</p>
                                    </div>
                                    <div id="uploadPreview" style="display: none;">
                                        <div style="display: flex; align-items: center; justify-content: center; gap: 12px;">
                                            <img id="slipPreviewImg" src="" style="max-width: 120px; max-height: 120px; border-radius: 8px; display: none;">
                                            <div id="pdfPreview" style="display: none; background: rgba(239, 68, 68, 0.1); padding: 15px 20px; border-radius: 8px;">
                                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2">
                                                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                                    <polyline points="14 2 14 8 20 8"/>
                                                </svg>
                                                <div style="color: #ef4444; font-size: 0.75rem; margin-top: 4px;">PDF</div>
                                            </div>
                                        </div>
                                        <div style="margin-top: 10px;">
                                            <span id="uploadFileName" style="color: #22c55e; font-size: 0.85rem;"></span>
                                            <button type="button" onclick="removePaymentFile()" style="background: none; border: none; color: #ef4444; cursor: pointer; margin-left: 10px; font-size: 0.8rem;">ลบ</button>
                                        </div>
                                    </div>
                                </div>
                                <p style="font-size: 0.75rem; color: #64748b; margin-top: 8px;">
                                    💡 ถ้ายังไม่มีสลิป สามารถอัพโหลดทีหลังผ่านหน้า "ตรวจสอบสถานะการจอง" ได้
                                </p>
                            </div>
                        </div>
                        
                        <!-- Submit Button -->
                        <button type="submit" class="submit-btn" id="submitBtn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                            ยืนยันการจอง
                        </button>
                        
                        <!-- Trust Badges -->
                        <div class="trust-badges">
                            <div class="trust-badge">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                </svg>
                                ปลอดภัย
                            </div>
                            <div class="trust-badge">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                                ตอบกลับ 24 ชม.
                            </div>
                        </div>
                        
                        <!-- Step Buttons -->
                        <div class="step-buttons">
                            <button type="button" class="btn-prev" onclick="goToStep(1)">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="15 18 9 12 15 6"/>
                                </svg>
                                <span>กลับ</span>
                            </button>
                        </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        
        <!-- Mobile Booking Bar -->
        <div class="mobile-booking-bar" id="mobileBookingBar">
            <div class="mobile-bar-content">
                <div class="mobile-bar-info">
                    <div class="mobile-bar-room" id="mobileBarRoom">เลือกห้องพัก</div>
                    <div class="mobile-bar-price" id="mobileBarPrice">฿0/เดือน</div>
                </div>
                <button type="button" class="mobile-bar-btn" onclick="openMobileForm()">
                    จองเลย
                </button>
            </div>
        </div>
        
        <!-- Mobile Form Modal -->
        <div class="mobile-form-overlay" id="mobileFormOverlay">
            <div class="mobile-form-sheet">
                <div class="mobile-form-header">
                    <h3 class="mobile-form-title">กรอกข้อมูลจอง</h3>
                    <button type="button" class="mobile-form-close" onclick="closeMobileForm()">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>
                <div class="mobile-form-body">
                    <div class="mobile-room-summary">
                        <span class="room-name" id="mobileFormRoom">ห้อง -</span>
                        <span class="room-price" id="mobileFormPrice">฿0/เดือน</span>
                    </div>
                    
                    <!-- Mobile Form Fields -->
                    <div class="form-group">
                        <label class="form-label">ชื่อ-นามสกุล <span class="required">*</span></label>
                        <input type="text" id="mobileNameInput" class="form-input" placeholder="เช่น สมชาย ใจดี" autocomplete="name">
                        <div class="error-message" id="mobileNameError"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">เบอร์โทรศัพท์ <span class="required">*</span></label>
                        <input type="tel" id="mobilePhoneInput" class="form-input" placeholder="0812345678" maxlength="10" autocomplete="tel" inputmode="numeric">
                        <div class="error-message" id="mobilePhoneError"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">เดือนที่เริ่มเข้าพัก</label>
                        <select id="mobileStartMonth" class="form-select">
                            <?php
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
                    </div>
                    
                    <label class="duration-label">ระยะเวลาสัญญา</label>
                    <div class="duration-pills">
                        <div class="duration-pill mobile-duration" data-months="6">6 เดือน</div>
                        <div class="duration-pill mobile-duration selected" data-months="12">1 ปี</div>
                        <div class="duration-pill mobile-duration" data-months="24">2 ปี</div>
                    </div>
                    
                    <div class="contract-summary" style="margin-top: 20px;">
                        <div class="summary-row">
                            <span class="summary-label">เริ่มสัญญา</span>
                            <span class="summary-value" id="mobileSummaryStart">-</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">สิ้นสุดสัญญา</span>
                            <span class="summary-value" id="mobileSummaryEnd">-</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">เงินมัดจำ</span>
                            <span class="summary-value summary-total">฿<?php echo number_format($defaultDeposit); ?></span>
                        </div>
                    </div>
                    
                    <button type="button" class="submit-btn" id="mobileSubmitBtn" style="margin-top: 24px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                        ยืนยันการจอง
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Room Selection
        document.querySelectorAll('.room-card').forEach(card => {
            card.addEventListener('click', function() {
                // Remove selected from all
                document.querySelectorAll('.room-card').forEach(c => c.classList.remove('selected'));
                
                // Add selected to clicked
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;
                
                // Update display
                const roomNumber = this.querySelector('.room-number').textContent;
                const price = this.dataset.price;
                
                document.getElementById('selectedRoomDisplay').classList.add('show');
                document.getElementById('selectedRoomName').textContent = 'ห้อง ' + roomNumber;
                document.getElementById('selectedRoomPrice').textContent = '฿' + parseInt(price).toLocaleString() + '/เดือน';
                
                // Update payment amount
                updatePaymentAmount();
            });
        });
        
        // Duration Pills
        document.querySelectorAll('.duration-pill').forEach(pill => {
            pill.addEventListener('click', function() {
                document.querySelectorAll('.duration-pill').forEach(p => p.classList.remove('selected'));
                this.classList.add('selected');
                
                const months = parseInt(this.dataset.months);
                document.getElementById('ctrDuration').value = months;
                updateContractDates();
            });
        });
        
        // Start Month Change
        document.getElementById('ctrStartMonth').addEventListener('change', function() {
            document.getElementById('ctrStart').value = this.value;
            updateContractDates();
        });
        
        // Update Contract Dates
        function updateContractDates() {
            const startValue = document.getElementById('ctrStartMonth').value;
            const duration = parseInt(document.getElementById('ctrDuration').value);
            
            const startDate = new Date(startValue);
            const endDate = new Date(startDate);
            endDate.setMonth(endDate.getMonth() + duration);
            endDate.setDate(endDate.getDate() - 1);
            
            // Format for hidden input
            const endValue = endDate.toISOString().split('T')[0];
            document.getElementById('ctrEnd').value = endValue;
            
            // Format for display
            const thaiMonths = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
            
            const startDisplay = `1 ${thaiMonths[startDate.getMonth()]} ${startDate.getFullYear() + 543}`;
            const endDisplay = `${endDate.getDate()} ${thaiMonths[endDate.getMonth()]} ${endDate.getFullYear() + 543}`;
            
            document.getElementById('summaryStart').textContent = startDisplay;
            document.getElementById('summaryEnd').textContent = endDisplay;
        }
        
        // Toggle Optional Fields
        function toggleOptional() {
            const toggle = document.querySelector('.optional-toggle');
            const fields = document.getElementById('optionalFields');
            
            toggle.classList.toggle('open');
            fields.classList.toggle('show');
        }
        
        // ==========================================
        // Tenant Autocomplete Feature
        // ==========================================
        let searchTimeout = null;
        let selectedTenant = null;
        
        const tenantNameInput = document.getElementById('tenantNameInput');
        const tenantSuggestions = document.getElementById('tenantSuggestions');
        const existingTenantIdInput = document.getElementById('existingTenantId');
        
        if (tenantNameInput) {
            // Input event for autocomplete
            tenantNameInput.addEventListener('input', function() {
                const query = this.value.trim();
                
                // Clear previous timeout
                if (searchTimeout) {
                    clearTimeout(searchTimeout);
                }
                
                // Reset selected tenant when typing
                if (selectedTenant && query !== selectedTenant.name) {
                    clearSelectedTenant();
                }
                
                if (query.length < 2) {
                    hideSuggestions();
                    return;
                }
                
                // Debounce search
                searchTimeout = setTimeout(() => {
                    searchTenants(query);
                }, 300);
            });
            
            // Close suggestions when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.form-group')) {
                    hideSuggestions();
                }
            });
        }
        
        async function searchTenants(query) {
            try {
                const response = await fetch(`/dormitory_management/Public/api_search_tenant.php?q=${encodeURIComponent(query)}`);
                const result = await response.json();
                
                if (result.success && result.data.length > 0) {
                    showSuggestions(result.data, query);
                } else {
                    showNoResults(query);
                }
            } catch (error) {
                console.error('Search error:', error);
                hideSuggestions();
            }
        }
        
        function showSuggestions(tenants, query) {
            let html = `
                <div class="autocomplete-new" onclick="selectNewTenant('${escapeHtml(query)}')">
                    <div class="autocomplete-new-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        เพิ่มผู้เช่าใหม่: "${escapeHtml(query)}"
                    </div>
                </div>
            `;
            
            tenants.forEach(tenant => {
                const statusClass = tenant.status === '1' ? 'active' : 'inactive';
                html += `
                    <div class="autocomplete-item" onclick='selectTenant(${JSON.stringify(tenant)})'>
                        <div class="autocomplete-item-name">
                            ${escapeHtml(tenant.name)}
                            <span class="autocomplete-item-status ${statusClass}">${escapeHtml(tenant.statusText)}</span>
                        </div>
                        <div class="autocomplete-item-info" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <span style="display: inline-flex; align-items: center; gap: 4px;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                                ${tenant.phone || '-'}
                            </span>
                            ${tenant.education ? '<span style="display: inline-flex; align-items: center; gap: 4px;">• <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg> ' + escapeHtml(tenant.education) + '</span>' : ''}
                        </div>
                    </div>
                `;
            });
            
            tenantSuggestions.innerHTML = html;
            tenantSuggestions.style.display = 'block';
        }
        
        function showNoResults(query) {
            tenantSuggestions.innerHTML = `
                <div class="autocomplete-new" onclick="selectNewTenant('${escapeHtml(query)}')">
                    <div class="autocomplete-new-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        เพิ่มผู้เช่าใหม่: "${escapeHtml(query)}"
                    </div>
                </div>
                <div class="autocomplete-item" style="color: #94a3b8; cursor: default;">
                    <div class="autocomplete-item-info">ไม่พบผู้เช่าที่ตรงกับ "${escapeHtml(query)}"</div>
                </div>
            `;
            tenantSuggestions.style.display = 'block';
        }
        
        function hideSuggestions() {
            if (tenantSuggestions) {
                tenantSuggestions.style.display = 'none';
            }
        }
        
        function selectTenant(tenant) {
            selectedTenant = tenant;
            
            // Fill form fields
            tenantNameInput.value = tenant.name;
            existingTenantIdInput.value = tenant.id;
            
            // Fill phone
            const phoneInput = document.querySelector('input[name="phone"]');
            if (phoneInput && tenant.phone) {
                phoneInput.value = tenant.phone;
            }
            
            // Fill optional fields if available
            const ageInput = document.querySelector('input[name="age"]');
            if (ageInput && tenant.age) ageInput.value = tenant.age;
            
            const educationInput = document.querySelector('input[name="education"]');
            if (educationInput && tenant.education) educationInput.value = tenant.education;
            
            const parentInput = document.querySelector('input[name="parent"]');
            if (parentInput && tenant.parent) parentInput.value = tenant.parent;
            
            const parentsphoneInput = document.querySelector('input[name="parentsphone"]');
            if (parentsphoneInput && tenant.parentsphone) parentsphoneInput.value = tenant.parentsphone;
            
            // Open optional fields if they have data
            if (tenant.age || tenant.education || tenant.parent || tenant.parentsphone) {
                const optionalFields = document.getElementById('optionalFields');
                const toggle = document.querySelector('.optional-toggle');
                if (optionalFields && !optionalFields.classList.contains('show')) {
                    optionalFields.classList.add('show');
                    toggle?.classList.add('open');
                }
            }
            
            // Show selected badge
            showSelectedBadge(tenant);
            
            hideSuggestions();
            
            showAppleAlert(`เลือกผู้เช่า: ${tenant.name}\\n\\nข้อมูลถูกกรอกอัตโนมัติแล้ว`, 'สำเร็จ');
        }
        
        function selectNewTenant(name) {
            clearSelectedTenant();
            tenantNameInput.value = name;
            hideSuggestions();
        }
        
        function clearSelectedTenant() {
            selectedTenant = null;
            existingTenantIdInput.value = '';
            
            // Remove badge
            const badge = document.querySelector('.tenant-selected-badge');
            if (badge) badge.remove();
        }
        
        function showSelectedBadge(tenant) {
            // Remove existing badge
            const existingBadge = document.querySelector('.tenant-selected-badge');
            if (existingBadge) existingBadge.remove();
            
            const badge = document.createElement('div');
            badge.className = 'tenant-selected-badge';
            badge.innerHTML = `
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                ผู้เช่าเดิม: ${escapeHtml(tenant.name)}
                <button type="button" onclick="clearSelectedTenant(); this.parentElement.remove();" title="ยกเลิกการเลือก">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            `;
            
            tenantNameInput.parentElement.appendChild(badge);
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // ==========================================
        // Payment Upload Feature
        // ==========================================
        const paymentUploadZone = document.getElementById('paymentUploadZone');
        const payProofInput = document.getElementById('payProofInput');
        const uploadPlaceholder = document.getElementById('uploadPlaceholder');
        const uploadPreview = document.getElementById('uploadPreview');
        const slipPreviewImg = document.getElementById('slipPreviewImg');
        const pdfPreview = document.getElementById('pdfPreview');
        const uploadFileName = document.getElementById('uploadFileName');
        
        if (paymentUploadZone && payProofInput) {
            // Click to upload
            paymentUploadZone.addEventListener('click', () => payProofInput.click());
            
            // Drag and drop
            paymentUploadZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                paymentUploadZone.style.borderColor = '#f59e0b';
                paymentUploadZone.style.background = 'rgba(245, 158, 11, 0.1)';
            });
            
            paymentUploadZone.addEventListener('dragleave', () => {
                paymentUploadZone.style.borderColor = 'rgba(245, 158, 11, 0.4)';
                paymentUploadZone.style.background = 'rgba(0,0,0,0.2)';
            });
            
            paymentUploadZone.addEventListener('drop', (e) => {
                e.preventDefault();
                paymentUploadZone.style.borderColor = 'rgba(245, 158, 11, 0.4)';
                paymentUploadZone.style.background = 'rgba(0,0,0,0.2)';
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    handlePaymentFile(files[0]);
                }
            });
            
            // File input change
            payProofInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    handlePaymentFile(e.target.files[0]);
                }
            });
        }
        
        function handlePaymentFile(file) {
            const maxSize = 5 * 1024 * 1024; // 5MB
            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
            
            if (!allowedTypes.includes(file.type)) {
                showAppleAlert('รองรับเฉพาะไฟล์ JPG, PNG, WEBP หรือ PDF', 'แจ้งเตือน');
                return;
            }
            
            if (file.size > maxSize) {
                showAppleAlert('ขนาดไฟล์ต้องไม่เกิน 5MB', 'แจ้งเตือน');
                return;
            }
            
            // Create DataTransfer to set file to input
            const dt = new DataTransfer();
            dt.items.add(file);
            payProofInput.files = dt.files;
            
            // Show preview
            uploadPlaceholder.style.display = 'none';
            uploadPreview.style.display = 'block';
            uploadFileName.textContent = file.name;
            
            if (file.type === 'application/pdf') {
                slipPreviewImg.style.display = 'none';
                pdfPreview.style.display = 'block';
            } else {
                pdfPreview.style.display = 'none';
                slipPreviewImg.style.display = 'block';
                
                const reader = new FileReader();
                reader.onload = (e) => {
                    slipPreviewImg.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
            
            paymentUploadZone.style.borderColor = '#22c55e';
        }
        
        function removePaymentFile() {
            payProofInput.value = '';
            uploadPlaceholder.style.display = 'block';
            uploadPreview.style.display = 'none';
            slipPreviewImg.src = '';
            slipPreviewImg.style.display = 'none';
            pdfPreview.style.display = 'none';
            paymentUploadZone.style.borderColor = 'rgba(245, 158, 11, 0.4)';
        }

        // Form Validation
        document.getElementById('bookingForm')?.addEventListener('submit', function(e) {
            const roomSelected = document.querySelector('input[name="room_id"]:checked');
            const name = document.querySelector('input[name="name"]').value.trim();
            const phone = document.querySelector('input[name="phone"]').value.trim();
            
            if (!roomSelected) {
                e.preventDefault();
                alert('กรุณาเลือกห้องพัก');
                return;
            }
            
            if (!name || name.length < 4) {
                e.preventDefault();
                alert('กรุณากรอกชื่อ-นามสกุล');
                return;
            }
            
            if (!phone || phone.length !== 10) {
                e.preventDefault();
                alert('กรุณากรอกเบอร์โทรศัพท์ 10 หลัก');
                return;
            }
        });
        
        // Phone number format
        document.querySelector('input[name="phone"]')?.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
        });
        
        document.querySelector('input[name="parentsphone"]')?.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
        });
        
        // Mobile phone input format with validation
        document.getElementById('mobilePhoneInput')?.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
            validateMobilePhone();
        });
        
        // Step Navigation Functions
        let currentStep = 1;
        
        function goToStep(stepNumber) {
            // Validate before going to step 2
            if (stepNumber === 2) {
                if (!validateStep1()) {
                    return;
                }
            }
            
            // Hide all steps
            document.querySelectorAll('.step-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Show target step
            const targetStep = document.querySelector(`.step-content[data-step="${stepNumber}"]`);
            if (targetStep) {
                targetStep.classList.add('active');
            }
            
            // Update step indicators
            document.querySelectorAll('.form-step').forEach(step => {
                const stepNum = parseInt(step.dataset.step);
                step.classList.remove('active', 'completed');
                
                if (stepNum === stepNumber) {
                    step.classList.add('active');
                } else if (stepNum < stepNumber) {
                    step.classList.add('completed');
                }
            });
            
            currentStep = stepNumber;
            
            // Scroll to top of form
            document.querySelector('.booking-box')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        
        function validateStep1() {
            // Check if room is selected
            const selectedRoom = document.querySelector('input[name="room_id"]:checked');
            if (!selectedRoom) {
                alert('กรุณาเลือกห้องพัก');
                return false;
            }
            
            // Check tenant name
            const tenantName = document.getElementById('tenantNameInput')?.value.trim();
            if (!tenantName) {
                alert('กรุณากรอกชื่อ-นามสกุล');
                document.getElementById('tenantNameInput')?.focus();
                return false;
            }
            
            // Check phone
            const phone = document.querySelector('input[name="phone"]')?.value.trim();
            if (!phone || phone.length !== 10) {
                alert('กรุณากรอกเบอร์โทรศัพท์ 10 หลัก');
                document.querySelector('input[name="phone"]')?.focus();
                return false;
            }
            
            // Check contract dates
            const ctrStart = document.getElementById('ctrStart')?.value;
            const ctrEnd = document.getElementById('ctrEnd')?.value;
            if (!ctrStart || !ctrEnd) {
                alert('กรุณาเลือกวันที่เข้าพักและระยะเวลาสัญญา');
                return false;
            }
            
            return true;
        }
        
        // Update payment amount based on room price
        function updatePaymentAmount() {
            // ค่ามัดจำคงที่ 2000 บาท - ไม่เปลี่ยนตามห้องที่เลือก
            const depositAmount = 2000;
            
            // Update display (already fixed at 2000)
            const amountDisplay = document.getElementById('paymentAmountDisplay');
            const amountInput = document.getElementById('paymentAmountInput');
            if (amountDisplay) {
                amountDisplay.textContent = '฿2,000';
            }
            if (amountInput) {
                amountInput.value = depositAmount;
            }
            
            // Update QR Code with fixed amount
            const qrImage = document.getElementById('qrCodeImage');
            if (qrImage) {
                const promptpayNumber = '<?php echo addslashes($promptpayNumber ?? ''); ?>';
                if (promptpayNumber) {
                    qrImage.src = `https://promptpay.io/${encodeURIComponent(promptpayNumber)}/2000.png`;
                }
            }
        }
        
        // Mobile name input validation
        document.getElementById('mobileNameInput')?.addEventListener('input', function() {
            validateMobileName();
        });
        
        // Validate on blur
        document.getElementById('mobileNameInput')?.addEventListener('blur', function() {
            if (this.value.trim()) validateMobileName();
        });
        
        document.getElementById('mobilePhoneInput')?.addEventListener('blur', function() {
            if (this.value.trim()) validateMobilePhone();
        });
        
        // Validation Functions
        function validateMobileName() {
            const input = document.getElementById('mobileNameInput');
            const error = document.getElementById('mobileNameError');
            const value = input.value.trim();
            
            if (!value) {
                input.classList.remove('valid', 'invalid');
                error.classList.remove('show');
                return false;
            }
            
            if (value.length < 4) {
                input.classList.add('invalid');
                input.classList.remove('valid');
                error.textContent = 'กรุณากรอกชื่อ-นามสกุลอย่างน้อย 4 ตัวอักษร';
                error.classList.add('show');
                return false;
            }
            
            if (!/^[\u0E00-\u0E7Fa-zA-Z\s]+$/.test(value)) {
                input.classList.add('invalid');
                input.classList.remove('valid');
                error.textContent = 'กรุณากรอกชื่อเป็นภาษาไทยหรืออังกฤษเท่านั้น';
                error.classList.add('show');
                return false;
            }
            
            input.classList.add('valid');
            input.classList.remove('invalid');
            error.classList.remove('show');
            return true;
        }
        
        function validateMobilePhone() {
            const input = document.getElementById('mobilePhoneInput');
            const error = document.getElementById('mobilePhoneError');
            const value = input.value.trim();
            
            if (!value) {
                input.classList.remove('valid', 'invalid');
                error.classList.remove('show');
                return false;
            }
            
            if (value.length < 10) {
                input.classList.add('invalid');
                input.classList.remove('valid');
                error.textContent = `กรุณากรอกเบอร์โทรศัพท์ให้ครบ 10 หลัก (ป้อนแล้ว ${value.length} หลัก)`;
                error.classList.add('show');
                return false;
            }
            
            if (!/^0[0-9]{9}$/.test(value)) {
                input.classList.add('invalid');
                input.classList.remove('valid');
                error.textContent = 'เบอร์โทรศัพท์ต้องขึ้นต้นด้วย 0 และมี 10 หลัก';
                error.classList.add('show');
                return false;
            }
            
            input.classList.add('valid');
            input.classList.remove('invalid');
            error.classList.remove('show');
            return true;
        }
        
        // ==========================================
        // Mobile Functions
        // ==========================================
        
        let selectedRoomData = null;
        let mobileDuration = 12;
        
        // Update room selection for mobile
        document.querySelectorAll('.room-card').forEach(card => {
            card.addEventListener('click', function() {
                selectedRoomData = {
                    id: this.dataset.roomId,
                    number: this.querySelector('.room-number').textContent,
                    price: this.dataset.price,
                    type: this.dataset.type
                };
                
                // Update mobile bar
                document.getElementById('mobileBarRoom').textContent = 'ห้อง ' + selectedRoomData.number;
                document.getElementById('mobileBarPrice').textContent = '฿' + parseInt(selectedRoomData.price).toLocaleString() + '/เดือน';
                
                // Update mobile form
                document.getElementById('mobileFormRoom').textContent = 'ห้อง ' + selectedRoomData.number;
                document.getElementById('mobileFormPrice').textContent = '฿' + parseInt(selectedRoomData.price).toLocaleString() + '/เดือน';
            });
        });
        
        // Mobile duration pills
        document.querySelectorAll('.mobile-duration').forEach(pill => {
            pill.addEventListener('click', function() {
                document.querySelectorAll('.mobile-duration').forEach(p => p.classList.remove('selected'));
                this.classList.add('selected');
                mobileDuration = parseInt(this.dataset.months);
                updateMobileContractDates();
            });
        });
        
        // Mobile start month change
        document.getElementById('mobileStartMonth')?.addEventListener('change', updateMobileContractDates);
        
        function updateMobileContractDates() {
            const startSelect = document.getElementById('mobileStartMonth');
            if (!startSelect) return;
            
            const startValue = startSelect.value;
            const startDate = new Date(startValue);
            const endDate = new Date(startDate);
            endDate.setMonth(endDate.getMonth() + mobileDuration);
            endDate.setDate(endDate.getDate() - 1);
            
            const thaiMonths = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
            
            const startDisplay = `1 ${thaiMonths[startDate.getMonth()]} ${startDate.getFullYear() + 543}`;
            const endDisplay = `${endDate.getDate()} ${thaiMonths[endDate.getMonth()]} ${endDate.getFullYear() + 543}`;
            
            document.getElementById('mobileSummaryStart').textContent = startDisplay;
            document.getElementById('mobileSummaryEnd').textContent = endDisplay;
        }
        
        function openMobileForm() {
            if (!selectedRoomData) {
                alert('กรุณาเลือกห้องพักก่อน');
                return;
            }
            updateMobileContractDates();
            document.getElementById('mobileFormOverlay').classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        
        function closeMobileForm() {
            document.getElementById('mobileFormOverlay').classList.remove('show');
            document.body.style.overflow = '';
        }
        
        function submitMobileForm() {
            const nameInput = document.getElementById('mobileNameInput');
            const phoneInput = document.getElementById('mobilePhoneInput');
            const name = nameInput.value.trim();
            const phone = phoneInput.value.trim();
            
            // Check room selection
            if (!selectedRoomData) {
                alert('กรุณาเลือกห้องพักก่อนทำการจอง');
                return;
            }
            
            // Validate name
            if (!name) {
                nameInput.classList.add('invalid');
                document.getElementById('mobileNameError').textContent = 'กรุณากรอกชื่อ-นามสกุล';
                document.getElementById('mobileNameError').classList.add('show');
                nameInput.focus();
                alert('กรุณากรอกชื่อ-นามสกุล');
                return;
            }
            
            if (name.length < 4) {
                nameInput.classList.add('invalid');
                document.getElementById('mobileNameError').textContent = 'ชื่อ-นามสกุลต้องมีอย่างน้อย 4 ตัวอักษร';
                document.getElementById('mobileNameError').classList.add('show');
                nameInput.focus();
                alert('กรุณากรอกชื่อ-นามสกุลให้ครบถ้วน (อย่างน้อย 4 ตัวอักษร)');
                return;
            }
            
            if (!/^[\u0E00-\u0E7Fa-zA-Z\s]+$/.test(name)) {
                nameInput.classList.add('invalid');
                document.getElementById('mobileNameError').textContent = 'กรุณากรอกชื่อเป็นภาษาไทยหรืออังกฤษเท่านั้น';
                document.getElementById('mobileNameError').classList.add('show');
                nameInput.focus();
                alert('กรุณากรอกชื่อเป็นภาษาไทยหรืออังกฤษเท่านั้น');
                return;
            }
            
            // Validate phone
            if (!phone) {
                phoneInput.classList.add('invalid');
                document.getElementById('mobilePhoneError').textContent = 'กรุณากรอกเบอร์โทรศัพท์';
                document.getElementById('mobilePhoneError').classList.add('show');
                phoneInput.focus();
                alert('กรุณากรอกเบอร์โทรศัพท์');
                return;
            }
            
            if (phone.length !== 10) {
                phoneInput.classList.add('invalid');
                document.getElementById('mobilePhoneError').textContent = `กรุณากรอกเบอร์โทรศัพท์ให้ครบ 10 หลัก (ป้อนแล้ว ${phone.length} หลัก)`;
                document.getElementById('mobilePhoneError').classList.add('show');
                phoneInput.focus();
                alert('กรุณากรอกเบอร์โทรศัพท์ให้ครบ 10 หลัก');
                return;
            }
            
            if (!/^0[0-9]{9}$/.test(phone)) {
                phoneInput.classList.add('invalid');
                document.getElementById('mobilePhoneError').textContent = 'เบอร์โทรศัพท์ต้องขึ้นต้นด้วย 0 และมี 10 หลัก';
                document.getElementById('mobilePhoneError').classList.add('show');
                phoneInput.focus();
                alert('เบอร์โทรศัพท์ไม่ถูกต้อง กรุณากรอกเบอร์ที่ขึ้นต้นด้วย 0 และมี 10 หลัก');
                return;
            }
            
            // All validations passed
            nameInput.classList.add('valid');
            nameInput.classList.remove('invalid');
            phoneInput.classList.add('valid');
            phoneInput.classList.remove('invalid');
            
            // Sync data to main form and submit
            const form = document.getElementById('bookingForm');
            
            // Set values
            document.querySelector('input[name="name"]').value = name;
            document.querySelector('input[name="phone"]').value = phone;
            document.querySelector(`input[name="room_id"][value="${selectedRoomData.id}"]`).checked = true;
            
            // Set start date
            const startValue = document.getElementById('mobileStartMonth').value;
            document.getElementById('ctrStart').value = startValue;
            document.getElementById('ctrStartMonth').value = startValue;
            
            // Calculate end date
            const startDate = new Date(startValue);
            const endDate = new Date(startDate);
            endDate.setMonth(endDate.getMonth() + mobileDuration);
            endDate.setDate(endDate.getDate() - 1);
            document.getElementById('ctrEnd').value = endDate.toISOString().split('T')[0];
            document.getElementById('ctrDuration').value = mobileDuration;
            
            // Submit
            closeMobileForm();
            form.submit();
        }
        
        // Close modal on backdrop click
        document.getElementById('mobileFormOverlay')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeMobileForm();
            }
        });
        
        // Mobile submit button event listener
        document.getElementById('mobileSubmitBtn')?.addEventListener('click', function(e) {
            e.preventDefault();
            submitMobileForm();
        });
        
        // Initialize
        updateContractDates();
        updateMobileContractDates();
        
        // Pre-select room if any
        const preselected = document.querySelector('.room-card.selected');
        if (preselected) {
            preselected.click();
        }
        
        // Fallback copy function for HTTP context
        function fallbackCopyToClipboard(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
            } catch (err) {
                console.error('Fallback: Could not copy text', err);
            }
            document.body.removeChild(textArea);
        }
        
        // Booking Reference Functions
        function copyBookingId() {
            const text = document.getElementById('bookingIdText')?.textContent;
            if (text) {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(() => {
                        showAppleAlert('คัดลอกเลขที่การจองแล้ว!', 'สำเร็จ');
                    }).catch(() => {
                        fallbackCopyToClipboard(text);
                        showAppleAlert('คัดลอกแล้ว!', 'สำเร็จ');
                    });
                } else {
                    fallbackCopyToClipboard(text);
                    showAppleAlert('คัดลอกแล้ว! กรุณาตรวจสอบ', 'สำเร็จ');
                }
            }
        }
        
        function copyTenantId() {
            const text = document.getElementById('tenantIdText')?.textContent;
            if (text) {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(() => {
                        showAppleAlert('คัดลอกรหัสผู้เช่าแล้ว!', 'สำเร็จ');
                    }).catch(() => {
                        fallbackCopyToClipboard(text);
                        showAppleAlert('คัดลอกแล้ว!', 'สำเร็จ');
                    });
                } else {
                    fallbackCopyToClipboard(text);
                    showAppleAlert('คัดลอกแล้ว! กรุณาตรวจสอบ', 'สำเร็จ');
                }
            }
        }
        
        function copyAllInfo() {
            const bookingId = document.getElementById('bookingIdText')?.textContent || '';
            const tenantId = document.getElementById('tenantIdText')?.textContent || '';
            const text = `📋 ข้อมูลการจองห้องพัก - Sangthian Dormitory\n\n` +
                         `� เลขที่การจอง: ${bookingId}\n` +
                         `👤 รหัสผู้เช่า: ${tenantId}\n\n` +
                         `⚠️ กรุณาเก็บข้อมูลนี้ไว้สำหรับตรวจสอบสถานะการจอง\n` +
                         `🔗 ตรวจสอบสถานะ: ${window.location.origin}/dormitory_management/Public/booking_status.php`;
            
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => {
                    showAppleAlert('คัดลอกข้อมูลทั้งหมดแล้ว!\n\nสามารถนำไปวางใน Notes, LINE หรือแอปอื่นๆ ได้เลย', 'สำเร็จ');
                }).catch(() => {
                    fallbackCopyToClipboard(text);
                    showAppleAlert('คัดลอกแล้ว! กรุณาตรวจสอบ', 'สำเร็จ');
                });
            } else {
                fallbackCopyToClipboard(text);
                showAppleAlert('คัดลอกแล้ว!\n\nกรุณาตรวจสอบข้อมูล:\n\n' + 
                              `เลขที่การจอง: ${bookingId}\n` +
                              `รหัสผู้เช่า: ${tenantId}`, 'สำเร็จ');
            }
        }
        
        function shareBookingInfo() {
            const bookingId = document.getElementById('bookingIdText')?.textContent || '';
            const tenantId = document.getElementById('tenantIdText')?.textContent || '';
            const shareText = `📋 ข้อมูลการจองห้องพัก - Sangthian Dormitory\n\n` +
                         `� เลขที่การจอง: ${bookingId}\n` +
                         `👤 รหัสผู้เช่า: ${tenantId}\n\n` +
                         `🔗 ตรวจสอบสถานะที่:\n${window.location.origin}/dormitory_management/Public/booking_status.php`;
            
            // ตรวจสอบว่าเบราว์เซอร์รองรับ Web Share API หรือไม่
            if (navigator.share) {
                navigator.share({
                    title: '📋 ข้อมูลการจองห้องพัก',
                    text: shareText
                }).then(() => {
                    console.log('✅ แชร์สำเร็จ');
                }).catch((error) => {
                    // ถ้ายกเลิกหรือ error ไม่แสดงอะไร
                    if (error.name !== 'AbortError') {
                        console.log('Share cancelled or failed:', error);
                    }
                });
            } else {
                // เบราว์เซอร์ไม่รองรับการแชร์
                showAppleAlert('เบราว์เซอร์ไม่รองรับการแชร์\n\nกรุณาใช้ปุ่ม "คัดลอกทั้งหมด" แทน แล้วนำไปวางในแอปที่ต้องการแชร์', 'แจ้งเตือน');
            }
        }
        
        function saveToNotes() {
            const bookingId = document.getElementById('bookingIdText')?.textContent || '';
            const tenantId = document.getElementById('tenantIdText')?.textContent || '';
            const text = `📋 ข้อมูลการจองห้องพัก - Sangthian Dormitory\n\n` +
                         `� เลขที่การจอง: ${bookingId}\n` +
                         `👤 รหัสผู้เช่า: ${tenantId}\n\n` +
                         `📅 วันที่จอง: ${new Date().toLocaleDateString('th-TH', {year: 'numeric', month: 'long', day: 'numeric'})}\n` +
                         `⏰ เวลา: ${new Date().toLocaleTimeString('th-TH')}\n\n` +
                         `⚠️ สำคัญ: กรุณาเก็บข้อมูลนี้ไว้สำหรับตรวจสอบสถานะการจอง\n\n` +
                         `🔗 ลิงก์ตรวจสอบสถานะ:\n${window.location.origin}/dormitory_management/Public/booking_status.php`;
            
            // Create a downloadable text file
            const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `booking_${bookingId}_${Date.now()}.txt`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            showAppleAlert('บันทึกไฟล์แล้ว!\n\nไฟล์ข้อมูลการจองถูกบันทึกในอุปกรณ์ของคุณแล้ว', 'สำเร็จ');
        }
        
        // Auto-save to localStorage when success page loads
        document.addEventListener('DOMContentLoaded', function() {
            const bookingId = document.getElementById('bookingIdText')?.textContent;
            const tenantId = document.getElementById('tenantIdText')?.textContent;
            
            if (bookingId && tenantId) {
                // Save to localStorage as backup
                const bookingData = {
                    bookingId: bookingId,
                    tenantId: tenantId,
                    timestamp: Date.now(),
                    date: new Date().toISOString()
                };
                
                // Keep last 5 bookings
                let savedBookings = JSON.parse(localStorage.getItem('dormitory_bookings') || '[]');
                savedBookings.unshift(bookingData);
                savedBookings = savedBookings.slice(0, 5);
                localStorage.setItem('dormitory_bookings', JSON.stringify(savedBookings));
                
                console.log('✅ ข้อมูลการจองถูกบันทึกอัตโนมัติแล้ว');
                
                // Show alert and redirect to booking_status.php after 3 seconds
                setTimeout(function() {
                    if (confirm('✅ จองห้องพักสำเร็จ!\n\n📋 กรุณาบันทึกหมายเลขการจอง:\n' + bookingId + '\n\n🔔 คลิก "ตกลง" เพื่อไปยังหน้าตรวจสอบสถานะการจอง')) {
                        window.location.href = '/dormitory_management/Public/booking_status.php';
                    }
                }, 2000);
            }
        });
    </script>
    
    <style>
        /* CSS Animations for SVG Icons */
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        
        @keyframes wiggle {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(-5deg); }
            75% { transform: rotate(5deg); }
        }
        
        .animated-home {
            animation: bounce 2s ease-in-out infinite;
        }
        
        .animated-clipboard {
            animation: pulse 2s ease-in-out infinite;
        }
        
        button:hover .animated-clipboard {
            animation: wiggle 0.5s ease-in-out;
        }
    </style>
    
    <?php include_once __DIR__ . '/../includes/apple_alert.php'; ?>
</body>
</html>