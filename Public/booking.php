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
} catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }

// ดึงข้อมูล Tenant ที่ล็อกอินแล้ว (ถ้ามี)
$loggedInTenant = null;
if (!empty($_SESSION['tenant_logged_in']) && !empty($_SESSION['tenant_id'])) {
    try {
        $stmt = $pdo->prepare('SELECT tnt_name, tnt_phone FROM tenant WHERE tnt_id = ?');
        $stmt->execute([$_SESSION['tenant_id']]);
        $loggedInTenant = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }

    if (!$loggedInTenant) {
        $loggedInTenant = [
            'tnt_name' => $_SESSION['tenant_name'] ?? '',
            'tnt_phone' => $_SESSION['tenant_phone'] ?? ''
        ];
    } else {
        if (empty($loggedInTenant['tnt_name']) && !empty($_SESSION['tenant_name'])) {
            $loggedInTenant['tnt_name'] = $_SESSION['tenant_name'];
        }
        if (empty($loggedInTenant['tnt_phone']) && !empty($_SESSION['tenant_phone'])) {
            $loggedInTenant['tnt_phone'] = $_SESSION['tenant_phone'];
        }
    }
}

// ถ้ามีการเลือกห้องมา
$selectedRoom = null;
$availableRooms = [];

if (!empty($_GET['room'])) {
    // มีการเลือกห้องจากหน้า rooms.php
    $roomId = (int)$_GET['room'];
    $_SESSION['last_selected_room'] = $roomId;
    
    // ดึงข้อมูลห้องที่เลือกโดยตรง ไม่สนใจว่าว่างหรือไม่
    try {
        $stmt = $pdo->prepare("
            SELECT r.*, rt.type_name, rt.type_price
            FROM room r
            LEFT JOIN roomtype rt ON r.type_id = rt.type_id
            WHERE r.room_id = ?
        ");
        $stmt->execute([$roomId]);
        $selectedRoom = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }
} elseif (!empty($_SESSION['last_selected_room'])) {
    // ถ้าไม่มี ?room= parameter แต่มีห้องที่เคยเลือกไว้ใน session
    // ให้ redirect กลับไปที่ห้องนั้น
    header('Location: booking.php?room=' . $_SESSION['last_selected_room']);
    exit;
} else {
    // ถ้าไม่มีการเลือกห้องเลย ให้กลับไปหน้าเลือกห้อง
    header('Location: rooms.php');
    exit;
}

// ดึงห้องว่างจากสถานะจริง (เหมือนหน้า rooms.php)
try {
    $stmt = $pdo->prepare("
        SELECT r.*, rt.type_name, rt.type_price
        FROM room r
        LEFT JOIN roomtype rt ON r.type_id = rt.type_id
        WHERE NOT EXISTS (
                SELECT 1 FROM contract c WHERE c.room_id = r.room_id AND c.ctr_status = '0'
            )
          AND NOT EXISTS (
                SELECT 1 FROM contract c 
                LEFT JOIN termination tm ON tm.ctr_id = c.ctr_id
                WHERE c.room_id = r.room_id AND c.ctr_status = '2' AND (tm.term_date IS NULL OR tm.term_date >= CURDATE())
            )
          AND NOT EXISTS (
                SELECT 1 FROM booking b WHERE b.room_id = r.room_id AND b.bkg_status = '1'
                AND NOT EXISTS (
                    SELECT 1 FROM contract c2 WHERE c2.room_id = b.room_id AND c2.tnt_id = b.tnt_id
                )
            )
        ORDER BY CAST(r.room_number AS UNSIGNED) ASC
    ");
    $stmt->execute();
    $availableRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $stmt = $pdo->query("
            SELECT r.*, rt.type_name, rt.type_price
            FROM room r
            LEFT JOIN roomtype rt ON r.type_id = rt.type_id
            WHERE NOT EXISTS (
                    SELECT 1 FROM contract c WHERE c.room_id = r.room_id AND c.ctr_status = '0'
                )
              AND NOT EXISTS (
                    SELECT 1 FROM contract c 
                    LEFT JOIN termination tm ON tm.ctr_id = c.ctr_id
                    WHERE c.room_id = r.room_id AND c.ctr_status = '2' AND (tm.term_date IS NULL OR tm.term_date >= CURDATE())
                )
              AND NOT EXISTS (
                    SELECT 1 FROM booking b WHERE b.room_id = r.room_id AND b.bkg_status = '1'
                    AND NOT EXISTS (
                        SELECT 1 FROM contract c2 WHERE c2.room_id = b.room_id AND c2.tnt_id = b.tnt_id
                    )
                )
            ORDER BY CAST(r.room_number AS UNSIGNED) ASC
        ");
        $availableRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }
}

$success = false;
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success = true;
}
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
} catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }

// ฟังก์ชันสำหรับอัพโหลดไฟล์
function uploadFile($file, $uploadDir, $prefix = 'file') {
    // ถ้าไม่มีการอัพโหลดไฟล์เลย
    if (!isset($file) || !isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return null;
    }
    
    // ตรวจสอบ error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log("Upload error for {$prefix}: " . $file['error']);
        return null;
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    $maxSize = 5 * 1024 * 1024;
    
    if (!in_array($ext, $allowedExt)) {
        error_log("Invalid file extension: {$ext}");
        return null;
    }
    
    if ($file['size'] > $maxSize) {
        error_log("File too large: {$file['size']} bytes");
        return null;
    }
    
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            error_log("Failed to create directory: {$uploadDir}");
            return null;
        }
        chmod($uploadDir, 0777); // Ensure directory has full permissions
    }
    
    // Ensure directory is writable
    if (!is_writable($uploadDir)) {
        chmod($uploadDir, 0777);
        if (!is_writable($uploadDir)) {
            error_log("Directory is not writable: {$uploadDir}");
            return null;
        }
    }
    
    $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $filepath = $uploadDir . '/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Return only filename (relative path will be built by display layer)
        return $filename;
    }
    
    error_log("Failed to move uploaded file to: {$filepath}");
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
    if (empty($existingTenantId) && !empty($_SESSION['tenant_logged_in']) && !empty($_SESSION['tenant_id'])) {
        $existingTenantId = $_SESSION['tenant_id'];
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
            $stmt = $pdo->prepare("
                SELECT
                    r.room_id,
                    r.type_id,
                    rt.type_price,
                    CASE
                        WHEN EXISTS (SELECT 1 FROM contract c WHERE c.room_id = r.room_id AND c.ctr_status = '0') THEN 0
                        WHEN EXISTS (
                            SELECT 1 FROM contract c 
                            LEFT JOIN termination tm ON tm.ctr_id = c.ctr_id
                            WHERE c.room_id = r.room_id AND c.ctr_status = '2' AND (tm.term_date IS NULL OR tm.term_date >= CURDATE())
                        ) THEN 0
                        WHEN EXISTS (
                            SELECT 1 FROM booking b 
                            WHERE b.room_id = r.room_id AND b.bkg_status = '1'
                            AND NOT EXISTS (
                                SELECT 1 FROM contract c2 WHERE c2.room_id = b.room_id AND c2.tnt_id = b.tnt_id
                            )
                        ) THEN 0
                        ELSE 1
                    END AS is_available
                FROM room r
                LEFT JOIN roomtype rt ON r.type_id = rt.type_id
                WHERE r.room_id = ?
            ");
            $stmt->execute([$roomId]);
            $roomData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$roomData || (int)$roomData['is_available'] !== 1) {
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
                            // Create new tenant (อนุญาตให้เบอร์ซ้ำได้ตามความต้องการใหม่)
                            $tenantId = (string)time();
                            try {
                                $stmtTenant = $pdo->prepare("
                                    INSERT INTO tenant (tnt_id, tnt_name, tnt_age, tnt_address, tnt_phone, tnt_education, tnt_faculty, tnt_year, tnt_vehicle, tnt_parent, tnt_parentsphone, tnt_status, tnt_ceatetime)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '3', NOW())
                                ");
                                $stmtTenant->execute([$tenantId, $name, $age ?: null, $address ?: null, $phone, $education ?: null, $faculty ?: null, $year ?: null, $vehicle ?: null, $parent ?: null, $parentsphone ?: null]);
                            } catch (PDOException $e) {
                                $error = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage();
                            }
                        }

                        if (!empty($error)) {
                            $pdo->rollBack();
                        } else {
                        
                        // ตรวจสอบว่าห้องนี้มีสัญญา active อยู่แล้วหรือไม่
                        $activeContractCheck = $pdo->prepare("SELECT COUNT(*) FROM contract WHERE room_id = ? AND ctr_status = '0'");
                        $activeContractCheck->execute([$roomId]);
                        if ((int)$activeContractCheck->fetchColumn() > 0) {
                            $error = 'ห้องนี้มีสัญญาที่ใช้งานอยู่แล้ว กรุณายกเลิกสัญญาเก่าก่อนสร้างสัญญาใหม่';
                            $pdo->rollBack();
                        } else {
                        
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
                            INSERT INTO payment (pay_id, pay_date, pay_amount, pay_proof, pay_status, exp_id, pay_remark)
                            VALUES (?, NOW(), ?, ?, ?, ?, 'มัดจำ')
                        ");
                        $stmtPayment->execute([$paymentId, $depositAmount, $payProof, $payStatus, $expenseId]);

                        // บันทึก booking_payment สำหรับระบบ Wizard
                        $bookingPaymentId = (int)substr((string)time(), -9) + 5;
                        $bookingPaymentStatus = $payProof ? '0' : '0'; // 0 = รอตรวจสอบ
                        $stmtBookingPayment = $pdo->prepare("
                            INSERT INTO booking_payment (bp_id, bp_amount, bp_status, bp_payment_date, bp_proof, bkg_id, bp_remark)
                            VALUES (?, ?, ?, NOW(), ?, ?, 'มัดจำ')
                        ");
                        $stmtBookingPayment->execute([$bookingPaymentId, $depositAmount, $bookingPaymentStatus, $payProof, $bookingId]);
                        
                        // exp_status = '2' (กำลังดำเนินการ) ตั้งแต่ตอนสร้างแล้ว ไม่ต้อง update อีก
                        
                        // NOTE: ยังไม่ปรับสถานะห้องเป็น '1' (ไม่ว่าง) จนกว่าจะ Check-in ตาม Requirement
                        // $updateRoom = $pdo->prepare("UPDATE room SET room_status = '1' WHERE room_id = ?");
                        // $updateRoom->execute([$roomId]);

                        // สร้าง tenant_workflow สำหรับระบบ Wizard
                        // ข้าม step 1 โดยอัตโนมัติ เริ่มต้นที่ step 2 (รอเจ้าหน้าที่ยืนยันการชำระเงิน)
                        $workflowId = (int)substr((string)time(), -9) + 4;
                        $stmtWorkflow = $pdo->prepare("
                            INSERT INTO tenant_workflow (
                                id, tnt_id, bkg_id, ctr_id,
                                step_1_confirmed, step_1_date, step_1_by,
                                step_2_confirmed, step_2_date, step_2_by,
                                step_3_confirmed, step_3_date, step_3_by,
                                current_step, completed
                            ) VALUES (?, ?, ?, ?, 1, NOW(), 'system', 0, NULL, NULL, 0, NULL, NULL, 2, 0)
                        ");
                        $stmtWorkflow->execute([$workflowId, $tenantId, $bookingId, $contractId]);

                        $pdo->commit();
                        $success = true;

                        // ส่งแจ้งเตือนการจองใหม่เข้า LINE OA
                        require_once __DIR__ . '/../LineHelper.php';
                        try {
                            $stmtRoomInfo = $pdo->prepare("SELECT room_number FROM room WHERE room_id = ?");
                            $stmtRoomInfo->execute([$roomId]);
                            $roomName = $stmtRoomInfo->fetchColumn() ?: $roomId;
                            
                            $msg = "🔔 มีการจองห้องพักใหม่!\n";
                            $msg .= "👤 ผู้เช่า: " . $name . "\n";
                            $msg .= "📞 เบอร์ติดต่อ: " . $phone . "\n";
                            $msg .= "🏠 ห้องที่จอง: " . $roomName . "\n";
                            if (!empty($ctrStart)) {
                                $msg .= "📅 วันที่เข้าพัก: " . date('d/m/Y', strtotime($ctrStart)) . "\n";
                            }
                            $msg .= "สถานะ: รอตรวจสอบการชำระเงิน\n\n";
                            $msg .= "⚠️ กรุณาชำระเงินเงินมัดจำหรือค่าจอง ทันที เพื่อยืนยันการจองของท่าน\n\n";
                            
                            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' || isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                            $domainName = $_SERVER['HTTP_HOST'] ?? 'localhost';
                            $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
                            $statusUrl = $protocol . $domainName . $scriptDir . "/booking_status.php?ref=" . urlencode((string)$bookingId) . "&phone=" . urlencode((string)$phone) . "&auto=1";
                            
                            $msg .= "🔍 ตรวจสอบการจองทึ่นี่เลย:\n" . $statusUrl;
                            
                            sendLineBroadcast($pdo, $msg);
                        } catch (Exception $e) {
                            error_log("Line Notification Error: " . $e->getMessage());
                        }

                        // Store IDs for success page display
                        $_SESSION['last_booking_id'] = $bookingId;
                        $_SESSION['last_tenant_id'] = $tenantId;
                        $_SESSION['last_phone_number'] = $phone;
                        $_SESSION['last_booking_time'] = time();
                        
                        // Redirect to the same page with success flag in GET
                        header("Location: booking.php?room=" . urlencode((string)$roomId) . "&success=1");
                        exit;
                        } // end else (active contract check)
                        } // end else (error check)
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
        
        /* === Light Mode (Default) === */
        :root {
            --bg-primary: #f8fafc;
            --bg-secondary: #f1f5f9;
            --bg-tertiary: rgba(241, 245, 249, 0.85);
            --bg-input: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #475569;
            --text-muted: #64748b;
            --border-color: rgba(0,0,0,0.1);
            --border-color-focus: rgba(0,0,0,0.2);
            --accent-primary: #0284c7;
            --accent-success: #16a34a;
            --accent-warning: #ea580c;
            --accent-error: #dc2626;
            --header-bg: rgba(248, 250, 252, 0.95);
        }
        
        /* === Dark Mode === */
        :root[data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: rgba(30, 41, 59, 0.5);
            --bg-input: rgba(15, 23, 42, 0.6);
            --text-primary: #e2e8f0;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --border-color: rgba(255,255,255,0.1);
            --border-color-focus: rgba(255,255,255,0.15);
            --accent-primary: #60a5fa;
            --accent-success: #22c55e;
            --accent-warning: #f59e0b;
            --accent-error: #ef4444;
            --header-bg: rgba(15, 23, 42, 0.95);
        }
        
        body {
            font-family: 'Prompt', system-ui, sans-serif;
            background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
            min-height: 100vh;
            color: var(--text-primary);
            transition: background 0.3s, color 0.3s;
        }
        
        /* Header */
        .header {
            background: var(--header-bg, rgba(248, 250, 252, 0.95));
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-color);
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
            color: var(--text-primary);
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
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .back-btn:hover {
            background: var(--border-color-focus);
        }
        
        /* Theme Toggle Button */
        .theme-toggle {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background: var(--bg-input);
            color: var(--text-primary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            padding: 0;
        }
        
        .theme-toggle:hover {
            background: var(--border-color-focus);
        }
        
        .theme-toggle svg {
            width: 20px;
            height: 20px;
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
            color: var(--text-primary);
        }
        
        .page-title p {
            color: var(--text-secondary);
            font-size: 1rem;
        }
        
        /* Two Column Layout */
        .booking-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 32px;
            align-items: start;
        }
        
        /* Fullscreen Layout (No Room Selected) */
        .booking-layout-fullscreen {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 60vh;
            padding: 40px 20px;
        }
        
        .room-section-fullscreen {
            background: var(--bg-tertiary);
            border-radius: 24px;
            padding: 48px;
            border: 1px solid var(--border-color);
            max-width: 600px;
            width: 100%;
            text-align: center;
        }
        
        .room-section-fullscreen .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            color: var(--text-primary);
        }
        
        .room-section-fullscreen .section-title svg {
            width: 28px;
            height: 28px;
            color: var(--accent-primary);
        }
        
        .room-section-fullscreen .no-rooms {
            padding: 40px 20px;
        }
        
        .room-section-fullscreen .no-rooms svg {
            width: 80px;
            height: 80px;
            color: var(--text-secondary);
        }
        
        .room-section-fullscreen .no-rooms h3 {
            font-size: 1.5rem;
            margin-top: 24px;
            color: var(--text-primary);
        }
        
        .room-section-fullscreen .no-rooms p {
            font-size: 1rem;
            margin-top: 12px;
            color: var(--text-secondary);
        }
        
        /* Room Selection */
        .room-section {
            background: var(--bg-tertiary);
            border-radius: 20px;
            padding: 28px;
            border: 1px solid var(--border-color);
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-primary);
        }
        
        .section-title svg {
            width: 24px;
            height: 24px;
            color: var(--accent-primary);
        }
        
        .room-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 16px;
            width: 100%;
        }
        
        .room-card {
            background: var(--bg-input);
            border: 2px solid var(--border-color);
            border-radius: 16px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        
        .room-card:hover {
            border-color: var(--accent-primary);
            transform: translateY(-2px);
        }
        
        .room-card.selected {
            border-color: var(--accent-success);
            background: rgba(34, 197, 94, 0.1);
        }
        
        .room-card.selected::after {
            content: '✓';
            position: absolute;
            top: 12px;
            right: 12px;
            width: 24px;
            height: 24px;
            background: var(--accent-success);
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
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .room-type {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 12px;
        }
        
        .room-price {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--accent-success);
        }
        
        .room-price span {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 400;
        }
        
        /* No Rooms */
        .no-rooms {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        .no-rooms svg {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            opacity: 0.5;
            color: var(--text-secondary);
        }
        
        /* Booking Sidebar */
        .booking-sidebar {
            position: sticky;
            top: 100px;
        }
        
        .booking-box {
            background: var(--bg-tertiary);
            border-radius: 20px;
            padding: 28px;
            border: 1px solid var(--border-color);
        }
        
        .booking-box-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-primary);
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
            color: var(--text-primary);
        }
        
        .selected-room-price {
            color: var(--accent-success);
            font-weight: 600;
        }
        
        /* Form Inputs */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }
        
        .form-label .required {
            color: var(--accent-error);
        }
        
        .form-input {
            width: 100%;
            padding: 14px 16px;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--accent-primary);
            background: var(--bg-secondary);
        }
        
        .form-input::placeholder {
            color: var(--text-muted);
        }
        
        /* Autocomplete Suggestions */
        .autocomplete-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--bg-primary);
            border: 1px solid var(--accent-primary);
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
            border-bottom: 1px solid var(--border-color);
            transition: all 0.2s;
        }
        
        .autocomplete-item:last-child {
            border-bottom: none;
        }
        
        .autocomplete-item:hover {
            background: var(--bg-secondary);
        }
        
        .autocomplete-item-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1rem;
            margin-bottom: 4px;
        }
        
        .autocomplete-item-info {
            font-size: 0.85rem;
            color: var(--text-secondary);
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
            color: var(--accent-success);
        }
        
        .autocomplete-item-status.inactive {
            background: rgba(148, 163, 184, 0.2);
            color: var(--text-secondary);
        }
        
        .autocomplete-new {
            padding: 14px 16px;
            background: rgba(34, 197, 94, 0.1);
            border-bottom: 1px solid var(--border-color);
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
            color: var(--accent-success);
            font-weight: 600;
        }
        
        .tenant-selected-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(34, 197, 94, 0.15);
            color: var(--accent-success);
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-top: 8px;
        }
        
        .tenant-selected-badge button {
            background: none;
            border: none;
            color: var(--accent-error);
            cursor: pointer;
            padding: 2px;
            display: flex;
            align-items: center;
        }

        /* Duration Pills */
        .duration-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
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
            background: var(--bg-input);
            border: 2px solid var(--border-color);
            border-radius: 30px;
            color: var(--text-primary);
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s;
            flex: 1;
            text-align: center;
            min-width: 80px;
        }
        
        .duration-pill:hover {
            border-color: var(--accent-primary);
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
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-primary);
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
            border-color: var(--accent-primary);
        }
        
        .form-select option {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }
        
        /* Contract Summary */
        .contract-summary {
            background: var(--bg-input);
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
            border-bottom: 1px solid var(--border-color);
        }
        
        .summary-label {
            color: var(--text-secondary);
        }
        
        .summary-value {
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .summary-total {
            font-size: 1.1rem;
            color: var(--accent-success);
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
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            transition: all 0.3s;
        }
        
        .form-step.active {
            background: linear-gradient(135deg, rgba(96, 165, 250, 0.2), rgba(59, 130, 246, 0.1));
            border-color: var(--accent-primary);
        }
        
        .form-step.completed {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(16, 185, 129, 0.1));
            border-color: var(--accent-success);
        }
        
        .step-number {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
            flex-shrink: 0;
            color: var(--text-secondary);
        }
        
        .form-step.active .step-number {
            background: var(--accent-primary);
            color: #fff;
        }
        
        .form-step.completed .step-number {
            background: var(--accent-success);
            color: #fff;
        }
        
        .step-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .form-step.active .step-label {
            color: var(--accent-primary);
            font-weight: 600;
        }
        
        .form-step.completed .step-label {
            color: var(--accent-success);
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
            background: var(--bg-input);
            color: var(--text-primary);
        }
        
        .btn-prev:hover {
            background: var(--border-color-focus);
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
            background: var(--bg-input);
            border-radius: 12px;
            cursor: pointer;
            margin-bottom: 16px;
            transition: all 0.3s;
        }
        
        .optional-toggle:hover {
            background: var(--bg-secondary);
        }
        
        .optional-toggle svg {
            width: 20px;
            height: 20px;
            color: var(--accent-primary);
            transition: transform 0.3s;
        }
        
        .optional-toggle.open svg {
            transform: rotate(180deg);
        }
        
        .optional-toggle span {
            font-size: 0.9rem;
            color: var(--text-secondary);
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
            background: linear-gradient(135deg, var(--accent-success), #16a34a);
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
            border-top: 1px solid var(--border-color);
        }
        
        .trust-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        
        .trust-badge svg {
            width: 16px;
            height: 16px;
            color: var(--accent-success);
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
            color: #ef4444;
        }
        
        @media (prefers-color-scheme: light) {
            .message.error {
                color: #991b1b;
            }
        }
        
        .message.success {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: var(--accent-success);
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
            background: linear-gradient(135deg, var(--accent-success), #16a34a);
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
            color: var(--text-primary);
        }
        
        .success-message {
            color: var(--text-secondary);
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
            background: var(--bg-input);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .success-btn.secondary:hover {
            background: var(--bg-secondary);
        }
        
        /* Mobile Bottom Bar */
        .mobile-booking-bar {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--bg-primary);
            backdrop-filter: blur(20px);
            border-top: 1px solid var(--border-color);
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
            color: var(--text-primary);
        }
        
        .mobile-bar-price {
            font-size: 0.9rem;
            color: var(--accent-success);
        }
        
        .mobile-bar-btn {
            padding: 14px 28px;
            background: linear-gradient(135deg, var(--accent-success), #16a34a);
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
            background: linear-gradient(180deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);
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
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            background: inherit;
            z-index: 10;
        }
        
        .mobile-form-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .mobile-form-close {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--bg-input);
            border: none;
            color: var(--text-primary);
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
            color: var(--text-primary);
        }
        
        .mobile-room-summary .room-price {
            color: var(--accent-success);
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
            
            .mobile-bar-btn {
                padding: 12px 20px;
                font-size: 0.95rem;
            }
        }
        
        /* Hidden inputs */
        input[type="radio"].room-radio {
            display: none;
        }
        
        /* Form Hint */
        .form-hint {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        /* Payment Info Box */
        .payment-info-box {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            padding: 12px;
            border-radius: 10px;
            color: var(--text-primary);
        }
        
        .payment-info-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }
        
        .payment-info-value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            font-family: monospace;
        }
        
        .payment-info-sub {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 2px;
        }
        
        /* Upload Zone */
        .upload-zone-inner {
            border: 2px dashed rgba(245, 158, 11, 0.4);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: var(--bg-secondary);
        }
        
        .upload-placeholder-text {
            color: var(--text-primary);
            font-size: 0.9rem;
            margin-bottom: 4px;
        }
        
        .upload-placeholder-sub {
            color: var(--text-muted);
            font-size: 0.75rem;
        }
        
        .upload-hint {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 8px;
        }
        
        /* Message error fix for light mode */
        .message.error {
            color: var(--accent-error);
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
            <div style="display: flex; align-items: center; gap: 12px;">
                <button class="theme-toggle" id="themeToggleBtn" type="button" aria-label="สลับโหมด">
                    <!-- Moon icon: shown in light mode to indicate click = go dark -->
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" id="themeIcon">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                    </svg>
                </button>
                <a href="/dormitory_management/" class="back-btn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    กลับหน้าหลัก
                </a>
            </div>
        </div>
    </header>

    <div class="main-container">
        <?php if (!empty($_GET['line_linked']) && $_GET['line_linked'] == '1'): ?>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (typeof showAppleAlert === 'function') {
                        showAppleAlert('ผูกบัญชี LINE กับผู้เช่าเรียบร้อยแล้ว หากจองสำเร็จจะได้รับการแจ้งเตือนผ่าน LINE', 'ผูกบัญชีสำเร็จ');
                    } else {
                        alert('ผูกบัญชี LINE สำเร็จแล้ว');
                    }
                });
            </script>
        <?php endif; ?>
        <?php if ($success): ?>
        <?php
        $lastBookingId = $_SESSION['last_booking_id'] ?? '';
        $lastTenantId = $_SESSION['last_tenant_id'] ?? '';
        $lastPhoneNumber = $_SESSION['last_phone_number'] ?? '';
        $lastBookingTime = $_SESSION['last_booking_time'] ?? time();
        
        // Clear session data if older than 10 minutes
        if (time() - $lastBookingTime > 600) {
            unset($_SESSION['last_booking_id'], $_SESSION['last_tenant_id'], $_SESSION['last_phone_number'], $_SESSION['last_booking_time']);
            $lastBookingId = '';
            $lastTenantId = '';
            $lastPhoneNumber = '';
        }
        
        $statusLink = "/dormitory_management/Public/booking_status.php?ref=" . urlencode((string)$lastBookingId) . "&phone=" . urlencode((string)$lastPhoneNumber) . "&auto=1";
        ?>
        <!-- Success Page -->
        <div class="success-page" style="text-align:center; padding:40px 20px; max-width:600px; margin:0 auto;">
            <div style="width:80px;height:80px;background:#10b981;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;box-shadow:0 4px 14px rgba(16,185,129,0.4);">
                <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="width:40px;height:40px;"><polyline points="20 6 9 17 4 12"></polyline></svg>
            </div>
            
            <h1 style="font-size:24px;font-weight:700;color:var(--text-primary);margin-bottom:8px;">จองห้องพักสำเร็จ!</h1>
            <p style="color:var(--text-secondary);font-size:15px;margin-bottom:32px;">ระบบได้รับข้อมูลการจองของคุณแล้ว กรุณารอเจ้าหน้าที่ติดต่อกลับเพื่อยืนยันภายใน 24 ชั่วโมง</p>
            
            <div style="background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:16px;padding:24px;margin-bottom:32px;box-shadow:0 4px 20px rgba(0,0,0,0.05);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;border-bottom:1px solid var(--border-color);padding-bottom:16px;">
                    <div style="font-weight:600;color:var(--text-primary);font-size:16px;">หมายเลขการจองของคุณ</div>
                    <div style="background:rgba(245,158,11,0.1);color:#d97706;padding:6px 12px;border-radius:20px;font-size:12px;font-weight:600;">รอการยืนยัน</div>
                </div>
                
                <div id="bookingIdText" style="font-size:32px;font-weight:700;color:var(--text-primary);letter-spacing:2px;margin-bottom:8px;font-family:'Courier New', monospace;"><?php echo htmlspecialchars((string)$lastBookingId); ?></div>
                
                <button onclick="copyBookingId()" style="background:rgba(16,185,129,0.1);color:#10b981;border:none;padding:8px 16px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:all 0.2s;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                    คัดลอกหมายเลข
                </button>
                
                <?php if ($lastTenantId): ?>
                <div style="margin-top:20px;padding-top:16px;border-top:1px dashed var(--border-color);display:flex;justify-content:space-between;color:var(--text-secondary);font-size:14px;">
                    <span>รหัสผู้เช่า:</span>
                    <strong style="color:var(--text-primary);"><?php echo htmlspecialchars($lastTenantId); ?></strong>
                </div>
                <?php endif; ?>
            </div>
            
            <?php
            try {
                $stmtLine = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('line_add_friend_url', 'line_qr_code_image', 'line_bot_basic_id', 'line_login_channel_id')");
                $lineSettings = $stmtLine->fetchAll(PDO::FETCH_KEY_PAIR);
                $lineAddFriendUrl = $lineSettings['line_add_friend_url'] ?? '';
                $lineQrCodeImage = $lineSettings['line_qr_code_image'] ?? '';
                $lineBotBasicId = $lineSettings['line_bot_basic_id'] ?? '';
                $lineLoginChannelId = $lineSettings['line_login_channel_id'] ?? '';
            } catch (Exception $e) { 
                $lineAddFriendUrl = ''; 
                $lineQrCodeImage = '';
                $lineBotBasicId = '';
                $lineLoginChannelId = '';
            }
            
            if (!empty($_GET['line_linked']) && $_GET['line_linked'] == '1'):
            ?>
            <div style="background:var(--bg-secondary);border:1px solid #10b981;border-radius:16px;padding:24px;margin-bottom:32px;box-shadow:0 4px 20px rgba(0,0,0,0.05);text-align:center;">
                <div style="width:60px;height:60px;background:rgba(16,185,129,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="width:30px;height:30px;"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </div>
                <h3 style="margin-top:0;margin-bottom:12px;color:#10b981;font-size:1.2rem;">ผูกบัญชี LINE เรียบร้อยแล้ว!</h3>
                <p style="color:var(--text-secondary);font-size:0.95rem;margin-bottom:0;">
                    คุณจะได้รับการแจ้งเตือนสถานะการจอง ใบแจ้งหนี้ และข่าวสารต่างๆ ผ่านทาง LINE อัตโนมัติ
                </p>
            </div>
            <?php
            elseif ($lineLoginChannelId):
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
                    $protocol = 'https';
                }
                $httpHost = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
                $basePath = dirname(dirname($_SERVER['PHP_SELF']));
                if ($basePath === '/' || $basePath === '\\') {
                    $basePath = '';
                }
                $linkLineUrl = $protocol . '://' . $httpHost . $basePath . '/line_login.php?action=link&tenant_id=' . urlencode((string)$lastTenantId) . '&room=' . urlencode($_GET['room'] ?? '');
            ?>
            <div style="background:var(--bg-secondary);border:1px solid #06c755;border-radius:16px;padding:24px;margin-bottom:32px;box-shadow:0 4px 20px rgba(0,0,0,0.05);text-align:center;">
                <h3 style="margin-top:0;margin-bottom:12px;color:#06c755;font-size:1.2rem;">เปิดรับการแจ้งเตือนบิลฟรี! ขั้นตอนเดียว</h3>
                <p style="color:var(--text-secondary);font-size:0.95rem;margin-bottom:20px;">
                    ผูกบัญชีผู้เช่าด้วย LINE เพื่อรับใบแจ้งหนี้, แจ้งซ่อม, และพยากรณ์อากาศรายวัน ง่ายๆ ในคลิกเดียว
                </p>
                <div style="margin-bottom:24px;">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($linkLineUrl); ?>" alt="Scan to Link LINE" style="width:150px; height:150px; border-radius:8px; border:1px solid var(--border-color); padding:4px; background:#fff; margin:0 auto;">
                    <p style="font-size:0.85rem; color:var(--text-secondary); margin-top:8px;">สแกนเพื่อผูกบัญชีผ่านมือถือ</p>
                </div>
                
                <a href="../line_login.php?action=link&tenant_id=<?php echo urlencode((string)$lastTenantId); ?>&room=<?php echo urlencode($_GET['room'] ?? ''); ?>" style="display:inline-flex; align-items:center; justify-content:center; text-decoration:none; color:#fff; font-weight:bold; font-size:1.1rem; background-color:#06c755; border-radius:12px; padding:12px 24px; box-shadow:0 4px 10px rgba(6,199,85,0.3); transition:all 0.2s;">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor" style="margin-right:8px;"><path d="M22.5 10.4c0-4.3-4.7-7.8-10.5-7.8S1.5 6.1 1.5 10.4c0 3.8 3.7 7 8.5 7.7.4.1.9.3 1 .6l.3 1.8c0 .2.2.3.4.2 1.6-1.1 5.9-4 8.2-6.2 1.5-1.3 2.6-2.7 2.6-4.1zM9.5 12.3c0 .3-.2.5-.5.5H6.2c-.3 0-.5-.2-.5-.5V8.5c0-.3.2-.5.5-.5h2.8c.3 0 .5.2.5.5s-.2.5-.5.5H7.2v1h1.8c.3 0 .5.2.5.5s-.2.5-.5.5H7.2v1h1.8c.3 0 .5.2.5.5s-.2.5-.5.5H7.2v1h1.8c.3 0 .5.2.5.5zM13.6 12.3c0 .3-.2.5-.5.5h-1c-.3 0-.5-.2-.5-.5V8.5c0-.3.2-.5.5-.5h1c.3 0 .5.2.5.5v3.8zM17.4 12.3c0 .3-.2.5-.5.5h-2.1c-.3 0-.5-.2-.5-.5V8.5c0-.3.2-.5.5-.5h.6c.3 0 .5.2.5.5v2.8h1.5c.3 0 .5.2.5.5z"/></svg>
                    ผูกบัญชีด้วย LINE ทันทีบนอุปกรณ์นี้
                </a>
            </div>
            
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const tntId = "<?php echo addslashes((string)$lastTenantId); ?>";
                    const roomId = "<?php echo addslashes($_GET['room'] ?? ''); ?>";
                    if (tntId) {
                        const checkInterval = setInterval(() => {
                            fetch(`../check_line_linked.php?tenant_id=${encodeURIComponent(tntId)}`)
                                .then(res => res.json())
                                .then(data => {
                                    if (data.linked) {
                                        clearInterval(checkInterval);
                                        window.location.href = `?room=${encodeURIComponent(roomId)}&success=1&line_linked=1`;
                                    }
                                })
                                .catch(err => console.error('Error polling LINE link status:', err));
                        }, 3000); // เช็คทุก 3 วินาที
                    }
                });
            </script>
            <?php elseif ($lineAddFriendUrl): ?>
            <div style="background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:16px;padding:24px;margin-bottom:32px;box-shadow:0 4px 20px rgba(0,0,0,0.05);text-align:center;">
                <div style="margin-bottom:10px;">
                    <?php if (!empty($lineQrCodeImage) && file_exists(__DIR__ . '/Assets/Images/' . $lineQrCodeImage)): ?>
                        <img src="Assets/Images/<?php echo htmlspecialchars($lineQrCodeImage); ?>" alt="LINE QR Code" style="width:120px; height:120px; border-radius:8px; border:1px solid var(--border-color); padding:4px; background:#fff; object-fit: contain;">
                    <?php else: ?>
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=<?php echo urlencode($lineAddFriendUrl); ?>" alt="LINE QR Code" style="width:120px; height:120px; border-radius:8px; border:1px solid var(--border-color); padding:4px; background:#fff; object-fit: contain;">
                    <?php endif; ?>
                </div>
                <a href="<?php echo htmlspecialchars($lineAddFriendUrl); ?>" target="_blank" style="display:inline-block; text-decoration:none; color:#06c755; font-weight:600; margin-bottom:8px; border:1px solid #06c755; padding:6px 12px; border-radius:24px; background-color: #f6fff9;">
                    <span style="font-size:1.1rem; vertical-align:middle; margin-right:4px;">💬</span> <span style="vertical-align:middle;">เพิ่มเพื่อน LINE หอพักคลิกที่นี่</span>
                </a>
                <br><span style="color:var(--text-secondary);font-size:0.85rem;">หรือสแกน QR Code ด้านบน จากนั้นพิมพ์ใน LINE:</span><br>
                <code style="background:rgba(59,130,246,0.1); padding:4px 8px; border-radius:6px; color:#3b82f6; font-weight:600;display:inline-block;margin-top:8px;">ลงทะเบียน <?php echo htmlspecialchars((string)($lastPhoneNumber ?? 'เบอร์โทรศัพท์ของคุณ')); ?></code><br>
                <span style="font-size:0.75rem; color:var(--text-secondary); display:inline-block; margin-top:8px;">เพื่อรับการแจ้งเตือนสถานะการจองและบิลผ่าน LINE ได้ทันที!</span>
            </div>
            <?php endif; ?>
            
            <div style="display:flex;flex-direction:column;gap:12px;">
                <a href="<?php echo htmlspecialchars($statusLink); ?>" style="background:#3b82f6;color:white;display:flex;align-items:center;justify-content:center;padding:16px;border-radius:12px;font-size:16px;font-weight:600;text-decoration:none;transition:all 0.3s;gap:8px;box-shadow:0 4px 12px rgba(59,130,246,0.3);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    ดูสถานะการจองทันที
                </a>
                
                <div style="display:flex;gap:12px;">
                    <a href="/dormitory_management/" style="flex:1;background:transparent;color:var(--text-primary);border:1px solid var(--border-color);display:flex;align-items:center;justify-content:center;padding:16px;border-radius:12px;font-size:16px;font-weight:600;text-decoration:none;">กลับหน้าหลัก</a>
                    <a href="/dormitory_management/Public/booking.php" style="flex:1;background:transparent;color:var(--text-primary);border:1px solid var(--border-color);display:flex;align-items:center;justify-content:center;padding:16px;border-radius:12px;font-size:16px;font-weight:600;text-decoration:none;">จองเพิ่ม</a>
                </div>
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
        
        <form method="POST" enctype="multipart/form-data" id="bookingForm" onsubmit="preventDoubleSubmit(this)" novalidate>
            <script>
                function preventDoubleSubmit(form) {
                    var btn = form.querySelector('button[type="submit"]');
                    if (btn) {
                        btn.innerHTML = 'กำลังดำเนินการ...';
                        btn.style.pointerEvents = 'none';
                        btn.style.opacity = '0.7';
                        setTimeout(function() { btn.disabled = true; }, 50); // Delay so form definitely submits
                    }
                }
            </script>
            <?php if (!isset($_GET['room'])): ?>
            <!-- Fullscreen Layout: No Room Selected -->
            <div class="booking-layout-fullscreen">
                <div class="room-section-fullscreen">
                    <h2 class="section-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                            <polyline points="9 22 9 12 15 12 15 22"/>
                        </svg>
                        เลือกห้องพัก
                    </h2>
                    
                    <div class="no-rooms">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                            <path d="M9 21V12h6v9"/>
                        </svg>
                        <h3>ไม่มีห้องที่คุณเลือก</h3>
                        <p>กรุณาเลือกห้องพักที่ต้องการจองก่อน</p>
                        <a href="rooms.php" class="btn-primary" style="margin-top: 24px; text-decoration: none; display: inline-block; padding: 14px 32px; background: var(--accent-color, #60a5fa); color: white; border-radius: 12px; font-weight: 600; font-size: 1rem; transition: all 0.3s; box-shadow: 0 4px 12px rgba(96, 165, 250, 0.3);">
                            <svg style="width: 20px; height: 20px; vertical-align: middle; margin-right: 8px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                                <polyline points="9 22 9 12 15 12 15 22"/>
                            </svg>
                            ดูห้องพัก
                        </a>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Two Column Layout -->
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
                        <a href="rooms.php" style="color: var(--accent-color, #60a5fa); text-decoration: none; font-size: 14px; opacity: 0.8;">
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
                <?php if (isset($_GET['room']) && $selectedRoom): ?>
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
                            <input type="hidden" name="existing_tenant_id" id="existingTenantId" value="<?php echo htmlspecialchars($_SESSION['tenant_id'] ?? ''); ?>">
                            <div id="tenantSuggestions" class="autocomplete-suggestions" style="display: none;"></div>
                            <div class="form-hint">
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
                            <input type="hidden" name="ctr_start" id="ctrStart" value="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <!-- Start Day -->
                        <div class="form-group">
                            <label class="form-label">วันที่เริ่มเข้าพัก</label>
                            <input type="number" id="ctrStartDay" class="form-input" min="<?php echo date('j'); ?>" max="<?php echo date('t'); ?>" value="<?php echo date('j'); ?>" placeholder="วันที่ เช่น 5">
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
                        <div class="payment-section" style="margin: 24px 0; padding: 20px; background: linear-gradient(135deg, rgba(245, 158, 11, 0.08) 0%, rgba(251, 191, 36, 0.04) 100%); border-radius: 16px; border: 1px solid rgba(245, 158, 11, 0.3);">
                            <!-- Payment Info -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                                <div class="payment-info-box">
                                    <div class="payment-info-label">ค่ามัดจำ</div>
                                    <div id="paymentAmountDisplay" style="font-size: 1.2rem; font-weight: 700; color: #d97706;">฿2,000</div>
                                    <input type="hidden" name="payment_amount" id="paymentAmountInput" value="2000">
                                </div>
                                <?php if (!empty($bankAccountNumber)): ?>
                                <div class="payment-info-box">
                                    <div class="payment-info-label">เลขบัญชี</div>
                                    <div class="payment-info-value"><?php echo htmlspecialchars($bankAccountNumber); ?></div>
                                    <?php if (!empty($bankName)): ?>
                                    <div class="payment-info-sub"><?php echo htmlspecialchars($bankName); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($promptpayNumber)): ?>
                            <!-- PromptPay QR Code -->
                            <div style="text-align: center; margin-bottom: 16px;">
                                <div style="background: white; padding: 15px; border-radius: 12px; display: inline-block; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                                    <img id="qrCodeImage" 
                                         src="https://promptpay.io/<?php echo urlencode($promptpayNumber); ?>/2000.png" 
                                         alt="PromptPay QR Code" 
                                         style="width: 180px; height: auto; display: block;">
                                </div>
                                <div style="margin-top: 8px; font-size: 0.8rem; color: var(--text-secondary);">
                                    สแกน QR พร้อมเพย์: <span style="color: #d97706; font-weight: 600;"><?php echo htmlspecialchars($promptpayNumber); ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Upload Slip -->
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" style="color: #b45309; margin-bottom: 8px;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 6px;">
                                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                                        <polyline points="17 8 12 3 7 8"/>
                                        <line x1="12" y1="3" x2="12" y2="15"/>
                                    </svg>
                                    อัพโหลดสลิปการโอนเงิน <span style="color: var(--accent-error);">*</span>
                                </label>
                                <div class="upload-zone upload-zone-inner" id="paymentUploadZone">
                                    <input type="file" name="pay_proof" id="payProofInput" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" style="display: none;">
                                    <div id="uploadPlaceholder">
                                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="1.5" style="margin-bottom: 10px;">
                                            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                                            <polyline points="17 8 12 3 7 8"/>
                                            <line x1="12" y1="3" x2="12" y2="15"/>
                                        </svg>
                                        <p class="upload-placeholder-text">คลิกเพื่อเลือกไฟล์</p>
                                        <p class="upload-placeholder-sub">รองรับ JPG, PNG, PDF (ไม่เกิน 5MB)</p>
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
                                            <span id="uploadFileName" style="color: var(--accent-success); font-size: 0.85rem;"></span>
                                            <button type="button" onclick="removePaymentFile()" style="background: none; border: none; color: var(--accent-error); cursor: pointer; margin-left: 10px; font-size: 0.8rem;">ลบ</button>
                                        </div>
                                    </div>
                                </div>
                                <p class="upload-hint">
                                    💡 กรุณาอัพโหลดสลิปการโอนเงินเพื่อยืนยันการจอง
                                </p>
                            </div>
                        </div>
                        
                        <!-- Line Notification Info -->
                        <div style="background: rgba(0, 195, 0, 0.08); border: 1px solid rgba(0, 195, 0, 0.2); padding: 12px 14px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 10px; box-shadow: 0 2px 8px rgba(0, 195, 0, 0.04);">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="#00c300" style="flex-shrink: 0; margin-top: 1px;">
                                <path d="M21.5 10.5C21.5 5.8 17.2 2 12 2C6.8 2 2.5 5.8 2.5 10.5C2.5 14.7 5.2 18.2 9.2 19L8.6 21.2C8.5 21.6 8.9 21.9 9.3 21.7L14.7 18.2C15.3 18.4 16 18.5 16.7 18.6C17.4 18.7 18.2 18.8 18.9 18.8C19.6 18.8 21.5 18 21.5 10.5Z" />
                            </svg>
                            <div style="font-size: 13.5px; color: #166534; line-height: 1.5;">
                                <strong>แจ้งเตือนผ่าน LINE:</strong> เมื่อกดยืนยัน ระบบจะส่งข้อความแจ้งเตือนการจองนี้ไปยัง <strong>LINE</strong> ทันที เพื่อความรวดเร็วในการติดต่อกลับ
                            </div>
                        </div>
                        
                        <!-- Submit Button -->
                        <button type="submit" class="submit-btn" id="submitBtn" disabled style="opacity: 0.5; cursor: not-allowed;">
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
                <?php endif; ?>
            </div>
            <?php endif; ?>
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

                    <div class="form-group">
                        <label class="form-label">วันที่เริ่มเข้าพัก</label>
                        <input type="number" id="mobileStartDay" class="form-input" min="1" max="31" value="<?php echo date('j'); ?>" placeholder="วันที่ เช่น 5">
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
                    
                    <div style="background: rgba(0, 195, 0, 0.08); border: 1px solid rgba(0, 195, 0, 0.2); padding: 12px 14px; border-radius: 10px; margin-top: 20px; display: flex; align-items: flex-start; gap: 10px; box-shadow: 0 2px 8px rgba(0, 195, 0, 0.04);">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="#00c300" style="flex-shrink: 0; margin-top: 1px;">
                            <path d="M21.5 10.5C21.5 5.8 17.2 2 12 2C6.8 2 2.5 5.8 2.5 10.5C2.5 14.7 5.2 18.2 9.2 19L8.6 21.2C8.5 21.6 8.9 21.9 9.3 21.7L14.7 18.2C15.3 18.4 16 18.5 16.7 18.6C17.4 18.7 18.2 18.8 18.9 18.8C19.6 18.8 21.5 18 21.5 10.5Z" />
                        </svg>
                        <div style="font-size: 13.5px; color: #166534; line-height: 1.5;">
                            <strong>แจ้งเตือนผ่าน LINE:</strong> เมื่อกดยืนยัน ระบบจะส่งข้อความแจ้งเตือนการจองนี้ไปยัง <strong>LINE</strong> ทันที เพื่อความรวดเร็วในการติดต่อกลับ
                        </div>
                    </div>
                    
                    <button type="button" class="submit-btn" id="mobileSubmitBtn" style="margin-top: 20px;">
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
        document.getElementById('ctrStartMonth')?.addEventListener('change', function() {
            updateContractDates();
        });

        document.getElementById('ctrStartDay')?.addEventListener('input', function() {
            updateContractDates();
        });
        
        // Update Contract Dates
        function updateContractDates() {
            const startMonthEl = document.getElementById('ctrStartMonth');
            const durationEl = document.getElementById('ctrDuration');
            const dayInput = document.getElementById('ctrStartDay');
            
            if (!startMonthEl || !durationEl || !dayInput) return;
            
            const startMonthValue = startMonthEl.value;
            const duration = parseInt(durationEl.value);

            const [year, month] = startMonthValue.split('-').map(Number);
            const today = new Date();
            const isCurrentMonth = (year === today.getFullYear() && month === today.getMonth() + 1);
            const daysInMonth = new Date(year, month, 0).getDate();
            const minDay = isCurrentMonth ? today.getDate() : 1;

            dayInput.min = minDay;
            dayInput.max = daysInMonth;
            if (parseInt(dayInput.value) < minDay) dayInput.value = minDay;
            if (parseInt(dayInput.value) > daysInMonth) dayInput.value = daysInMonth;

            const day = parseInt(dayInput.value) || minDay;
            const startDate = new Date(year, month - 1, day);
            const endDate = new Date(startDate);
            endDate.setMonth(endDate.getMonth() + duration);
            
            // Update hidden inputs
            const pad = n => String(n).padStart(2, '0');
            document.getElementById('ctrStart').value = `${year}-${pad(month)}-${pad(day)}`;
            document.getElementById('ctrEnd').value = `${endDate.getFullYear()}-${pad(endDate.getMonth()+1)}-${pad(endDate.getDate())}`;
            
            // Format for display
            const thaiMonths = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
            document.getElementById('summaryStart').textContent = `${day} ${thaiMonths[startDate.getMonth()]} พ.ศ. ${startDate.getFullYear() + 543}`;
            document.getElementById('summaryEnd').textContent = `${endDate.getDate()} ${thaiMonths[endDate.getMonth()]} พ.ศ. ${endDate.getFullYear() + 543}`;
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
        const submitBtn = document.getElementById('submitBtn');
        
        // Configuration
        const PAYMENT_VALIDATION = {
            maxSize: 5 * 1024 * 1024, // 5MB
            allowedTypes: ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
            allowedExtensions: ['.jpg', '.jpeg', '.png', '.webp', '.pdf']
        };
        
        // Create error message container
        function createErrorContainer() {
            const container = document.createElement('div');
            container.id = 'paymentFileError';
            container.style.cssText = `
                display: none;
                margin-top: 8px;
                padding: 10px 12px;
                background: rgba(239, 68, 68, 0.1);
                border: 1px solid rgba(239, 68, 68, 0.3);
                border-radius: 8px;
                color: #ef4444;
                font-size: 0.8rem;
                border-left: 3px solid #ef4444;
            `;
            if (paymentUploadZone?.parentElement) {
                paymentUploadZone.parentElement.appendChild(container);
            }
            return container;
        }
        
        const errorContainer = createErrorContainer();
        
        function showPaymentError(message) {
            if (errorContainer) {
                errorContainer.textContent = message;
                errorContainer.style.display = 'block';
            }
        }
        
        function clearPaymentError() {
            if (errorContainer) {
                errorContainer.style.display = 'none';
                errorContainer.textContent = '';
            }
        }
        
        function updateSubmitButtonState() {
            if (submitBtn) {
                const hasFile = payProofInput?.files && payProofInput.files.length > 0;
                submitBtn.disabled = !hasFile;
                submitBtn.style.opacity = hasFile ? '1' : '0.5';
                submitBtn.style.cursor = hasFile ? 'pointer' : 'not-allowed';
            }
        }
        
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
            
            // Initialize button state
            updateSubmitButtonState();
        }
        
        function validatePaymentFile(file) {
            // Check file type
            if (!PAYMENT_VALIDATION.allowedTypes.includes(file.type)) {
                showPaymentError('❌ รองรับเฉพาะไฟล์: JPG, PNG, WEBP หรือ PDF เท่านั้น');
                showAppleAlert('รองรับเฉพาะไฟล์: JPG, PNG, WEBP หรือ PDF เท่านั้น', 'รูปแบบไฟล์ไม่ถูกต้อง');
                return false;
            }
            
            // Check file size
            if (file.size > PAYMENT_VALIDATION.maxSize) {
                const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
                showPaymentError(`❌ ขนาดไฟล์ ${fileSizeMB}MB เกินขีดจำกัด 5MB`);
                showAppleAlert(`ขนาดไฟล์ ${fileSizeMB}MB เกินขีดจำกัด 5MB\n\nกรุณาเลือกไฟล์ที่มีขนาดไม่เกิน 5MB`, 'ขนาดไฟล์ใหญ่เกินไป');
                return false;
            }
            
            // Check file size minimum (at least 1KB)
            if (file.size < 1024) {
                showPaymentError('⚠️ ไฟล์มีขนาดน้อยเกินไป กรุณาเลือกไฟล์อื่น');
                showAppleAlert('ไฟล์มีขนาดน้อยเกินไป\n\nกรุณาเลือกไฟล์อื่นที่สมบูรณ์', 'ขนาดไฟล์ผิดปกติ');
                return false;
            }
            
            clearPaymentError();
            return true;
        }
        
        function handlePaymentFile(file) {
            // Validate file
            if (!validatePaymentFile(file)) {
                payProofInput.value = ''; // Clear invalid file
                updateSubmitButtonState();
                return;
            }
            
            // Create DataTransfer to set file to input
            const dt = new DataTransfer();
            dt.items.add(file);
            payProofInput.files = dt.files;
            
            // Show preview
            uploadPlaceholder.style.display = 'none';
            uploadPreview.style.display = 'block';
            
            // Format file size
            const fileSizeKB = (file.size / 1024).toFixed(2);
            const fileSizeDisplay = file.size > 1024 * 1024 
                ? ((file.size / (1024 * 1024)).toFixed(2) + ' MB')
                : (fileSizeKB + ' KB');
            
            uploadFileName.innerHTML = `
                <span style="color: #22c55e; font-weight: 600;">✓ ${escapeHtml(file.name)}</span>
                <span style="color: #94a3b8; font-size: 0.75rem; margin-left: 6px;">(${fileSizeDisplay})</span>
            `;
            
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
            paymentUploadZone.style.background = 'rgba(34, 197, 94, 0.05)';
            
            clearPaymentError();
            updateSubmitButtonState();
        }
        
        function removePaymentFile() {
            payProofInput.value = '';
            uploadPlaceholder.style.display = 'block';
            uploadPreview.style.display = 'none';
            slipPreviewImg.src = '';
            slipPreviewImg.style.display = 'none';
            pdfPreview.style.display = 'none';
            paymentUploadZone.style.borderColor = 'rgba(245, 158, 11, 0.4)';
            paymentUploadZone.style.background = 'rgba(0,0,0,0.2)';
            clearPaymentError();
            updateSubmitButtonState();
        }

        // Form Validation
        document.getElementById('bookingForm')?.addEventListener('submit', function(e) {
            // Prevent double submission
            if (this.dataset.submitting === 'true') {
                e.preventDefault();
                return;
            }
            
            const roomSelected = document.querySelector('input[name="room_id"]:checked');
            const name = document.querySelector('input[name="name"]').value.trim();
            const phone = document.querySelector('input[name="phone"]').value.trim();
            const payProof = document.getElementById('payProofInput');

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

            if (!payProof || !payProof.files || payProof.files.length === 0) {
                e.preventDefault();
                alert('กรุณาอัพโหลดสลิปการโอนเงิน');
                // Scroll to upload zone
                document.getElementById('paymentUploadZone')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            // Mark as submitting
            this.dataset.submitting = 'true';
            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn) {
                submitBtn.disabled = true;
            }

            // Clear saved step and allow form submission
            clearSavedStep();
            // Allow form to submit normally (no preventDefault)
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
        
        // Function to save current step to localStorage
        function saveCurrentStep(stepNumber) {
            try {
                localStorage.setItem('bookingCurrentStep', stepNumber.toString());
            } catch (e) {
                console.warn('Cannot save to localStorage:', e);
            }
        }
        
        // Function to load saved step from localStorage
        function loadSavedStep() {
            try {
                const savedStep = localStorage.getItem('bookingCurrentStep');
                return savedStep ? parseInt(savedStep) : 1;
            } catch (e) {
                console.warn('Cannot load from localStorage:', e);
                return 1;
            }
        }
        
        // Function to clear saved step (on successful submission)
        function clearSavedStep() {
            try {
                localStorage.removeItem('bookingCurrentStep');
            } catch (e) {
                console.warn('Cannot clear localStorage:', e);
            }
        }
        
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
            saveCurrentStep(stepNumber);
            
            // Scroll to top of form
            document.querySelector('.booking-box')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        
        // Load saved step on page load
        window.addEventListener('load', function() {
            const savedStep = loadSavedStep();
            if (savedStep > 1) {
                // Only restore if it's a valid step (1 or 2)
                if (savedStep <= 2) {
                    goToStep(savedStep);
                }
            }
            
            // Reset form submission state after page load
            const bookingForm = document.getElementById('bookingForm');
            if (bookingForm) {
                bookingForm.dataset.submitting = 'false';
            }
            
            // Update button state
            updateSubmitButtonState();
            
            // Re-enable button if there's an error message (form validation failed at backend)
            const errorMessage = document.querySelector('.message.error');
            if (errorMessage && bookingForm) {
                bookingForm.dataset.submitting = 'false';
                updateSubmitButtonState();
            }
        });
        
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
        
        // Mobile start month/day change
        document.getElementById('mobileStartMonth')?.addEventListener('change', updateMobileContractDates);
        document.getElementById('mobileStartDay')?.addEventListener('input', updateMobileContractDates);
        
        function updateMobileContractDates() {
            const startSelect = document.getElementById('mobileStartMonth');
            if (!startSelect) return;
            
            const startMonthValue = startSelect.value;
            const startDate = new Date(startMonthValue);
            const maxDay = new Date(startDate.getFullYear(), startDate.getMonth() + 1, 0).getDate();
            
            // คำนวณ minDay: ถ้าเป็นเดือนปัจจุบัน ห้ามก่อนวันนี้
            const today = new Date();
            const isCurrentMonth = startDate.getFullYear() === today.getFullYear() && startDate.getMonth() === today.getMonth();
            const minDay = isCurrentMonth ? today.getDate() : 1;
            
            const startDayInput = document.getElementById('mobileStartDay');
            if (startDayInput) {
                startDayInput.min = minDay;
                startDayInput.max = maxDay;
            }
            
            let startDay = parseInt(startDayInput?.value) || minDay;
            if (startDay < minDay) startDay = minDay;
            if (startDay > maxDay) startDay = maxDay;
            if (startDayInput) startDayInput.value = startDay;
            startDate.setDate(startDay);
            
            const endDate = new Date(startDate);
            endDate.setMonth(endDate.getMonth() + mobileDuration);
            
            const thaiMonths = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
            
            const startDisplay = `${startDay} ${thaiMonths[startDate.getMonth()]} พ.ศ. ${startDate.getFullYear() + 543}`;
            const endDisplay = `${endDate.getDate()} ${thaiMonths[endDate.getMonth()]} พ.ศ. ${endDate.getFullYear() + 543}`;
            
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
            const startMonthValue = document.getElementById('mobileStartMonth').value;
            const startDate = new Date(startMonthValue);
            const maxDay = new Date(startDate.getFullYear(), startDate.getMonth() + 1, 0).getDate();
            let startDay = parseInt(document.getElementById('mobileStartDay')?.value) || 1;
            if (startDay < 1) startDay = 1;
            if (startDay > maxDay) startDay = maxDay;
            startDate.setDate(startDay);
            const startValue = startDate.toISOString().split('T')[0];
            document.getElementById('ctrStart').value = startValue;
            document.getElementById('ctrStartMonth').value = startMonthValue;
            
            // Calculate end date
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
            }
        });
        
        // ==========================================
        // Theme Toggle Feature
        // ==========================================
        const THEME_KEY = 'dormitory_theme_preference';
        
        // Initialize theme on page load
        function initTheme() {
            const savedTheme = localStorage.getItem(THEME_KEY);
            let currentTheme = savedTheme || 'light';
            
            // Apply saved theme
            applyTheme(currentTheme);
        }
        
        // Apply theme to document
        function applyTheme(theme) {
            const html = document.documentElement;
            
            if (theme === 'dark') {
                html.style.colorScheme = 'dark';
                html.setAttribute('data-theme', 'dark');
                updateThemeIcon('dark');
            } else {
                // Light mode (default)
                html.style.colorScheme = 'light';
                html.removeAttribute('data-theme');
                updateThemeIcon('light');
            }
            
            localStorage.setItem(THEME_KEY, theme);
        }
        
        // Update theme icon based on current theme
        function updateThemeIcon(theme) {
            const icon = document.getElementById('themeIcon');
            if (!icon) return;
            
            if (theme === 'dark') {
                // Show sun icon (click to go light)
                icon.innerHTML = '<circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>';
            } else {
                // Show moon icon (click to go dark)
                icon.innerHTML = '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>';
            }
        }
        
        // Toggle theme on button click
        document.getElementById('themeToggleBtn')?.addEventListener('click', function() {
            const currentTheme = localStorage.getItem(THEME_KEY) || 'light';
            let nextTheme;
            
            if (currentTheme === 'light') {
                nextTheme = 'dark';
            } else {
                nextTheme = 'light';
            }
            
            applyTheme(nextTheme);
            showThemeToast(nextTheme);
        });
        
        // Show notification about theme change
        function showThemeToast(theme) {
            const messages = {
                'light': '🌞 สลับเป็นโหมด Light',
                'dark': '🌙 สลับเป็นโหมด Dark'
            };
            
            // Create toast element
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                bottom: 24px;
                left: 24px;
                background: rgba(0,0,0,0.8);
                color: white;
                padding: 12px 16px;
                border-radius: 8px;
                font-size: 14px;
                font-weight: 500;
                z-index: 3000;
                animation: slideInUp 0.3s ease;
            `;
            toast.textContent = messages[theme];
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOutDown 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 2000);
        }
        
        // Add animation styles
        const styleSheet = document.createElement('style');
        styleSheet.textContent = `
            @keyframes slideInUp {
                from { transform: translateY(100px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
            @keyframes slideOutDown {
                from { transform: translateY(0); opacity: 1; }
                to { transform: translateY(100px); opacity: 0; }
            }
        `;
        document.head.appendChild(styleSheet);
        
        // Initialize theme when page loads
        window.addEventListener('DOMContentLoaded', initTheme);
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initTheme);
        } else {
            initTheme();
        }
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