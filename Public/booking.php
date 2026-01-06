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
                // Generate shorter tenant ID: T + timestamp (10 digits)
                $tenantId = 'T' . time();
                
                $pdo->beginTransaction();
                try {
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM booking b JOIN tenant t ON b.tnt_id = t.tnt_id WHERE t.tnt_phone = ? AND b.bkg_status IN ('1','2')");
                    $checkStmt->execute([$phone]);
                    $existing = (int)$checkStmt->fetchColumn();
                    
                    $checkContract = $pdo->prepare("SELECT COUNT(*) FROM contract c JOIN tenant t ON c.tnt_id = t.tnt_id WHERE t.tnt_phone = ? AND c.ctr_status = '0'");
                    $checkContract->execute([$phone]);
                    $existingContract = (int)$checkContract->fetchColumn();
                    
                    if ($existing > 0 || $existingContract > 0) {
                        $pdo->rollBack();
                        $error = 'เบอร์โทรศัพท์นี้มีการจองหรือสัญญาเช่าอยู่แล้ว';
                    } else {
                        // Insert tenant without document files (simplified booking)
                        $stmtTenant = $pdo->prepare("
                            INSERT INTO tenant (tnt_id, tnt_name, tnt_age, tnt_address, tnt_phone, tnt_education, tnt_faculty, tnt_year, tnt_vehicle, tnt_parent, tnt_parentsphone, tnt_status, tnt_ceatetime)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '3', NOW())
                        ");
                        $stmtTenant->execute([$tenantId, $name, $age ?: null, $address ?: null, $phone, $education ?: null, $faculty ?: null, $year ?: null, $vehicle ?: null, $parent ?: null, $parentsphone ?: null]);
                        
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
                            VALUES (?, ?, 0, 0, ?, ?, ?, 0, 0, ?, '0', ?)
                        ");
                        $stmtExpense->execute([$expenseId, date('Y-m-01'), $rateElec, $rateWater, $roomPrice, $deposit, $contractId]);
                        
                        // Note: Payment proof upload removed for simplified booking
                        // Can be added later through admin panel if needed
                        
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
                grid-template-columns: repeat(2, 1fr);
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
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            
            .room-card {
                padding: 14px;
            }
            
            .room-number {
                font-size: 1.2rem;
            }
            
            .room-type {
                font-size: 0.8rem;
            }
            
            .room-price {
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
            <div style="background: rgba(16, 185, 129, 0.1); border: 2px solid #10b981; border-radius: 16px; padding: 24px; margin: 24px 0; text-align: center;" id="bookingReferenceSection">
                <p style="color: #10b981; font-size: 14px; font-weight: 600; margin: 0 0 12px 0; text-transform: uppercase; letter-spacing: 1px;">📋 หมายเลขการจองของคุณ</p>
                <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; margin-bottom: 16px;">
                    <?php if ($lastBookingId): ?>
                    <div style="background: rgba(255,255,255,0.1); padding: 16px 24px; border-radius: 12px; min-width: 200px; position: relative;">
                        <div style="font-size: 12px; color: rgba(255,255,255,0.7); margin-bottom: 4px;">เลขที่การจอง</div>
                        <div style="font-size: 28px; font-weight: 700; color: #ffffff; font-family: 'Courier New', monospace; letter-spacing: 2px;" id="bookingIdText"><?php echo htmlspecialchars((string)$lastBookingId); ?></div>
                        <button onclick="copyBookingId()" style="position: absolute; top: 8px; right: 8px; background: rgba(16, 185, 129, 0.2); border: none; padding: 6px 10px; border-radius: 6px; cursor: pointer; color: #10b981; font-size: 11px; transition: all 0.2s;">
                            📋 คัดลอก
                        </button>
                    </div>
                    <?php endif; ?>
                    <?php if ($lastTenantId): ?>
                    <div style="background: rgba(255,255,255,0.1); padding: 16px 24px; border-radius: 12px; min-width: 200px; position: relative;">
                        <div style="font-size: 12px; color: rgba(255,255,255,0.7); margin-bottom: 4px;">รหัสผู้เช่า</div>
                        <div style="font-size: 28px; font-weight: 700; color: #ffffff; font-family: 'Courier New', monospace; letter-spacing: 2px;" id="tenantIdText"><?php echo htmlspecialchars($lastTenantId); ?></div>
                        <button onclick="copyTenantId()" style="position: absolute; top: 8px; right: 8px; background: rgba(16, 185, 129, 0.2); border: none; padding: 6px 10px; border-radius: 6px; cursor: pointer; color: #10b981; font-size: 11px; transition: all 0.2s;">
                            📋 คัดลอก
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Quick Action Buttons -->
                <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin: 20px 0;">
                    <button onclick="copyAllInfo()" style="background: #10b981; color: white; border: none; padding: 12px 24px; border-radius: 10px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.3s; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                        </svg>
                        คัดลอกทั้งหมด
                    </button>
                    
                    <button onclick="shareBookingInfo()" style="background: #3b82f6; color: white; border: none; padding: 12px 24px; border-radius: 10px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.3s; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="18" cy="5" r="3"></circle>
                            <circle cx="6" cy="12" r="3"></circle>
                            <circle cx="18" cy="19" r="3"></circle>
                            <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                            <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                        </svg>
                        แชร์ข้อมูล
                    </button>
                    
                    <button onclick="saveToNotes()" style="background: #8b5cf6; color: white; border: none; padding: 12px 24px; border-radius: 10px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.3s; box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                            <polyline points="7 3 7 8 15 8"></polyline>
                        </svg>
                        บันทึกข้อมูล
                    </button>
                </div>
                
                <p style="color: rgba(255,255,255,0.8); font-size: 14px; margin: 16px 0 0 0; line-height: 1.6;">
                    💡 <strong>กรุณาบันทึกหมายเลขนี้</strong> เพื่อใช้ตรวจสอบสถานะการจองและข้อมูลการชำระเงินภายหลัง<br>
                    <a href="/dormitory_management/Public/booking_status.php" style="color: #10b981; text-decoration: underline; font-weight: 600;">คลิกที่นี่เพื่อตรวจสอบสถานะ</a>
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
            <h1>🏠 จองห้องพัก</h1>
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
                        เลือกห้องพัก
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
                        
                        <!-- Name -->
                        <div class="form-group">
                            <label class="form-label">ชื่อ-นามสกุล <span class="required">*</span></label>
                            <input type="text" name="name" class="form-input" placeholder="เช่น สมชาย ใจดี" required>
                        </div>
                        
                        <!-- Phone -->
                        <div class="form-group">
                            <label class="form-label">เบอร์โทรศัพท์ <span class="required">*</span></label>
                            <input type="tel" name="phone" class="form-input" placeholder="0812345678" maxlength="10" required>
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
        
        // Booking Reference Functions
        function copyBookingId() {
            const text = document.getElementById('bookingIdText')?.textContent;
            if (text) {
                navigator.clipboard.writeText(text).then(() => {
                    showAppleAlert('คัดลอกเลขที่การจองแล้ว!', 'สำเร็จ');
                }).catch(() => {
                    showAppleAlert('ไม่สามารถคัดลอกได้ กรุณาจดบันทึกด้วยตนเอง', 'แจ้งเตือน');
                });
            }
        }
        
        function copyTenantId() {
            const text = document.getElementById('tenantIdText')?.textContent;
            if (text) {
                navigator.clipboard.writeText(text).then(() => {
                    showAppleAlert('คัดลอกรหัสผู้เช่าแล้ว!', 'สำเร็จ');
                }).catch(() => {
                    showAppleAlert('ไม่สามารถคัดลอกได้ กรุณาจดบันทึกด้วยตนเอง', 'แจ้งเตือน');
                });
            }
        }
        
        function copyAllInfo() {
            const bookingId = document.getElementById('bookingIdText')?.textContent || '';
            const tenantId = document.getElementById('tenantIdText')?.textContent || '';
            const text = `📋 ข้อมูลการจองห้องพัก - Sangthian Dormitory\n\n` +
                         `🔢 เลขที่การจอง: ${bookingId}\n` +
                         `👤 รหัสผู้เช่า: ${tenantId}\n\n` +
                         `⚠️ กรุณาเก็บข้อมูลนี้ไว้สำหรับตรวจสอบสถานะการจอง\n` +
                         `🔗 ตรวจสอบสถานะ: ${window.location.origin}/dormitory_management/Public/booking_status.php`;
            
            navigator.clipboard.writeText(text).then(() => {
                showAppleAlert('คัดลอกข้อมูลทั้งหมดแล้ว!\n\nสามารถนำไปวางใน Notes, LINE หรือแอปอื่นๆ ได้เลย', 'สำเร็จ');
            }).catch(() => {
                showAppleAlert('ไม่สามารถคัดลอกได้\n\nกรุณาจดบันทึกข้อมูลด้วยตนเอง:\n\n' + 
                              `เลขที่การจอง: ${bookingId}\n` +
                              `รหัสผู้เช่า: ${tenantId}`, 'แจ้งเตือน');
            });
        }
        
        function shareBookingInfo() {
            const bookingId = document.getElementById('bookingIdText')?.textContent || '';
            const tenantId = document.getElementById('tenantIdText')?.textContent || '';
            const text = `📋 ข้อมูลการจองห้องพัก\n\n` +
                         `เลขที่การจอง: ${bookingId}\n` +
                         `รหัสผู้เช่า: ${tenantId}\n\n` +
                         `ตรวจสอบสถานะ: ${window.location.origin}/dormitory_management/Public/booking_status.php`;
            
            if (navigator.share) {
                navigator.share({
                    title: 'ข้อมูลการจองห้องพัก - Sangthian Dormitory',
                    text: text
                }).catch(() => {
                    // User cancelled, do nothing
                });
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(text).then(() => {
                    showAppleAlert('คัดลอกข้อมูลแล้ว!\n\nสามารถแชร์ไปยัง LINE, Facebook หรือแอปอื่นๆ ได้', 'สำเร็จ');
                }).catch(() => {
                    showAppleAlert('เบราว์เซอร์ไม่รองรับการแชร์\n\nกรุณาจดบันทึกข้อมูลด้วยตนเอง', 'แจ้งเตือน');
                });
            }
        }
        
        function saveToNotes() {
            const bookingId = document.getElementById('bookingIdText')?.textContent || '';
            const tenantId = document.getElementById('tenantIdText')?.textContent || '';
            const text = `📋 ข้อมูลการจองห้องพัก - Sangthian Dormitory\n\n` +
                         `🔢 เลขที่การจอง: ${bookingId}\n` +
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
            }
        });
    </script>
    
    <?php include_once __DIR__ . '/../includes/apple_alert.php'; ?>
</body>
</html>