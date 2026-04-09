<?php
session_start();
require_once __DIR__ . '/../ConnectDB.php';
require_once __DIR__ . '/../includes/thai_date_helper.php';

$debugMode = isset($_GET['debug']) && $_GET['debug'] === '1';
ini_set('display_errors', $debugMode ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../booking_status_debug.log');
error_reporting(E_ALL);

try {
    $pdo = connectDB();
} catch (Throwable $e) {
    error_log('booking_status.php DB error: ' . $e->getMessage());
    if ($debugMode) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'DB Error: ' . $e->getMessage();
    }
    exit;
}

// ดึงค่าตั้งค่าระบบ
$siteName = 'Sangthian Dormitory';
$logoFilename = 'Logo.jpg';
$themeColor = '#1e40af';
$bankName = '';
$bankAccountName = '';
$bankAccountNumber = '';
$promptpayNumber = '';
$contactPhone = '';

try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
        if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
        if ($row['setting_key'] === 'theme_color' && !empty($row['setting_value'])) $themeColor = $row['setting_value'];
        if ($row['setting_key'] === 'bank_name') $bankName = $row['setting_value'] ?? '';
        if ($row['setting_key'] === 'bank_account_name') $bankAccountName = $row['setting_value'] ?? '';
        if ($row['setting_key'] === 'bank_account_number') $bankAccountNumber = $row['setting_value'] ?? '';
        if ($row['setting_key'] === 'promptpay_number') $promptpayNumber = $row['setting_value'] ?? '';
        if ($row['setting_key'] === 'contact_phone' && !empty($row['setting_value'])) $contactPhone = $row['setting_value'];
        if ($row['setting_key'] === 'dormitory_phone' && !empty($row['setting_value']) && empty($contactPhone)) $contactPhone = $row['setting_value'];
    }
    // ถ้ายังไม่มีเบอร์โทร ใช้ promptpay number
    if (empty($contactPhone) && !empty($promptpayNumber)) {
        $contactPhone = $promptpayNumber;
    }
} catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }

// Thai date formatter
function thaiDateFormat($dateStr) {
    if (empty($dateStr)) return '-';
    $ts = strtotime($dateStr);
    if ($ts === false) return '-';
    $day = date('j', $ts);
    $monthNum = (int)date('n', $ts);
    $yearBE = (int)date('Y', $ts) + 543;
    $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    return $day . ' ' . $months[$monthNum - 1] . ' ' . $yearBE;
}

$bookingInfo = null;
$error = '';
$showResult = false;
$autoFilled = false;
$resolvedTenantId = $_SESSION['tenant_id'] ?? '';
if (empty($resolvedTenantId) && !empty($_SESSION['tenant_email'])) {
    try {
        error_log("Resolving tenant_id from email: " . $_SESSION['tenant_email']);
        $stmtOauth = $pdo->prepare("SELECT tnt_id FROM tenant_oauth WHERE provider = 'google' AND provider_email = ? LIMIT 1");
        $stmtOauth->execute([$_SESSION['tenant_email']]);
        $oauthRow = $stmtOauth->fetch(PDO::FETCH_ASSOC);
        if ($oauthRow && !empty($oauthRow['tnt_id'])) {
            $resolvedTenantId = $oauthRow['tnt_id'];
            $_SESSION['tenant_id'] = $resolvedTenantId;
            error_log("Resolved tenant_id: $resolvedTenantId");
        } else {
            error_log("Could not resolve tenant_id from email");
        }
    } catch (PDOException $e) {
        error_log('Booking status resolve tenant_id error: ' . $e->getMessage());
    }
}
$autoRedirect = !empty($_GET['auto']);
$autoRedirect = !empty($_GET['auto']);
if (empty($resolvedTenantId) && $autoRedirect && !empty($_SESSION['tenant_logged_in']) && !empty($_SESSION['tenant_name'])) {
    try {
        $stmtTenantByName = $pdo->prepare("\
            SELECT t.tnt_id
            FROM tenant t
            INNER JOIN booking b ON t.tnt_id = b.tnt_id
            WHERE t.tnt_name = ? AND b.bkg_status IN ('1','2')
            ORDER BY b.bkg_id DESC
            LIMIT 1
        ");
        $stmtTenantByName->execute([$_SESSION['tenant_name']]);
        $tenantByName = $stmtTenantByName->fetch(PDO::FETCH_ASSOC);
        if ($tenantByName && !empty($tenantByName['tnt_id'])) {
            $resolvedTenantId = $tenantByName['tnt_id'];
            $_SESSION['tenant_id'] = $resolvedTenantId;
        }
    } catch (PDOException $e) {
        error_log('Booking status resolve tenant_id by name error: ' . $e->getMessage());
    }
}
// ย้ายการเช็ค isTenantLoggedIn มาหลังจากค้นหา tenant_id เสร็จแล้ว
if (empty($resolvedTenantId) && $autoRedirect && !empty($_SESSION['tenant_logged_in']) && !empty($_SESSION['tenant_phone'])) {
    try {
        $stmtTenantByPhone = $pdo->prepare("SELECT tnt_id FROM tenant WHERE tnt_phone = ? LIMIT 1");
        $stmtTenantByPhone->execute([$_SESSION['tenant_phone']]);
        $tenantByPhone = $stmtTenantByPhone->fetch(PDO::FETCH_ASSOC);
        if ($tenantByPhone && !empty($tenantByPhone['tnt_id'])) {
            $resolvedTenantId = $tenantByPhone['tnt_id'];
            $_SESSION['tenant_id'] = $resolvedTenantId;
        }
    } catch (PDOException $e) {
        error_log('Booking status resolve tenant_id by phone error: ' . $e->getMessage());
    }
}

// ตั้งค่า isTenantLoggedIn หลังจากค้นหา tenant_id ทั้งหมดเสร็จแล้ว
$isTenantLoggedIn = !empty($resolvedTenantId) || !empty($_SESSION['tenant_logged_in']);

$tenantPhone = '';
$tenantBookings = [];
$autoFillError = false;
$noBookingForTenant = false;

// DEBUG
error_log("booking_status.php DEBUG - Session:");
error_log("  tenant_id: " . ($_SESSION['tenant_id'] ?? 'NOT SET'));
error_log("  tenant_email: " . ($_SESSION['tenant_email'] ?? 'NOT SET'));
error_log("  resolvedTenantId: " . $resolvedTenantId);
error_log("  isTenantLoggedIn: " . ($isTenantLoggedIn ? 'YES' : 'NO'));

// รับค่าจาก GET หรือ POST
$bookingRef = trim($_GET['id'] ?? $_POST['booking_ref'] ?? $_GET['ref'] ?? '');
$contactInfo = trim($_GET['phone'] ?? $_POST['contact_info'] ?? '');

// ถ้าเป็นผู้เช่าที่ล็อกอิน ให้ดึงข้อมูลอัตโนมัติและรองรับหลายการจอง
if ($isTenantLoggedIn) {
    try {
        $tenantId = $resolvedTenantId ?: $_SESSION['tenant_id'] ?? '';
        error_log("Fetching tenant data for: $tenantId");

        $stmtTenant = $pdo->prepare("SELECT tnt_phone FROM tenant WHERE tnt_id = ? LIMIT 1");
        $stmtTenant->execute([$tenantId]);
        $tenantRow = $stmtTenant->fetch(PDO::FETCH_ASSOC);
        if ($tenantRow && !empty($tenantRow['tnt_phone'])) {
            $tenantPhone = $tenantRow['tnt_phone'];
            if (empty($contactInfo)) {
                $contactInfo = $tenantPhone;
            }
        }
        
        // ถ้าไม่มี phone จาก tenant ให้ใช้จาก session
        if (empty($tenantPhone) && !empty($_SESSION['tenant_phone'])) {
            $tenantPhone = $_SESSION['tenant_phone'];
            $contactInfo = $tenantPhone;
        }

        // ค้นหา booking จาก tenant_id หรือ phone number
        $stmtBookings = $pdo->prepare("
            SELECT DISTINCT b.bkg_id, b.bkg_date, b.bkg_checkin_date, b.bkg_status
            FROM booking b
            JOIN tenant t ON b.tnt_id = t.tnt_id
            WHERE (b.tnt_id = ? OR t.tnt_phone = ?) AND b.bkg_status IN ('1','2')
            ORDER BY b.bkg_id DESC
        ");
        error_log("SQL: SELECT bkg_id FROM booking WHERE tnt_id = '$tenantId' OR phone = '$tenantPhone'");
        $stmtBookings->execute([$tenantId, $tenantPhone]);
        $tenantBookings = $stmtBookings->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        error_log("Found " . count($tenantBookings) . " bookings for tenant: $tenantId");
        error_log("Bookings: " . json_encode($tenantBookings, JSON_UNESCAPED_UNICODE));

        if (empty($bookingRef)) {
            if (count($tenantBookings) === 1) {
                $bookingRef = (string)$tenantBookings[0]['bkg_id'];
                $autoFilled = true;
                error_log("Auto-filled bookingRef: $bookingRef");
                error_log("Single booking found, will auto-load");
            } elseif (count($tenantBookings) > 1) {
                error_log("Multiple bookings found: " . count($tenantBookings));
            } elseif (count($tenantBookings) === 0 && !empty($contactInfo)) {
                $noBookingForTenant = true;
                error_log("No bookings found for tenant: $tenantId");
            }
        }
    } catch (PDOException $e) {
        error_log('Booking status auto-fill error: ' . $e->getMessage());
        $autoFillError = true;
    }
}

// ถ้ามีการจองเพียงรายเดียว ให้โหลดข้อมูลโดยอัตโนมัติ
if ($isTenantLoggedIn && !empty($bookingRef) && empty($bookingInfo) && $autoFilled) {
    error_log("Auto-loading booking for logged-in user: $bookingRef, phone: $tenantPhone");
    $bookingRef = preg_replace('/[^0-9a-zA-Z]/', '', $bookingRef);

    try {
        // ค้นหา booking โดยใช้ bkg_id โดยตรง (เพราะ bkg_id มาจากการค้นหาก่อนหน้าแล้ว)
        $stmt = $pdo->prepare("
            SELECT 
                t.tnt_id, t.tnt_name, t.tnt_phone,
                b.bkg_id, b.bkg_date, b.bkg_checkin_date, b.bkg_status,
                r.room_number, rt.type_name, rt.type_price,
                c.ctr_id, c.ctr_deposit, c.access_token, c.ctr_start, c.ctr_end,
                e.exp_status,
                COALESCE(tw.current_step, 1) as current_step,
                COALESCE(tw.completed, 0) as workflow_completed,
                COALESCE(tw.step_2_confirmed, 0) as step_2_confirmed,
                (SELECT COALESCE(SUM(CASE WHEN bp_status = '1' THEN bp_amount ELSE 0 END), 0) FROM booking_payment WHERE bkg_id = b.bkg_id) as paid_amount,
                (SELECT COUNT(*) FROM signature_logs WHERE contract_id = c.ctr_id AND signer_type = 'tenant') as has_signature
            FROM booking b
            JOIN tenant t ON b.tnt_id = t.tnt_id
            LEFT JOIN room r ON b.room_id = r.room_id
            LEFT JOIN roomtype rt ON r.type_id = rt.type_id
            LEFT JOIN contract c ON t.tnt_id = c.tnt_id AND c.room_id = b.room_id AND c.ctr_status = '0'
            LEFT JOIN expense e ON c.ctr_id = e.ctr_id
            LEFT JOIN tenant_workflow tw ON b.bkg_id = tw.bkg_id
            WHERE b.bkg_id = ?
            GROUP BY t.tnt_id, b.bkg_id, r.room_id, rt.type_id, c.ctr_id, e.exp_id, tw.current_step, tw.completed, tw.step_2_confirmed
            LIMIT 1
        ");
        $stmt->execute([$bookingRef]);
        $bookingInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($bookingInfo && !empty($bookingInfo['bkg_id'])) {
            $showResult = true;
            error_log("✓ Booking info loaded: " . $bookingInfo['bkg_id']);
        } else {
            error_log("✗ Failed to load booking info for bkg_id: $bookingRef");
        }
    } catch (PDOException $e) {
        error_log('Auto-load booking error: ' . $e->getMessage());
    }
}

// ถ้าเป็นผู้ใช้ที่ล็อกอินและมีหมายเลขการจอง ให้โหลดข้อมูลอัตโนมัติ
if ($isTenantLoggedIn && !empty($bookingRef) && empty($bookingInfo)) {
    $bookingRef = preg_replace('/[^0-9a-zA-Z]/', '', $bookingRef);

    try {
        $stmt = $pdo->prepare("
            SELECT 
                t.tnt_id, t.tnt_name, t.tnt_phone,
                b.bkg_id, b.bkg_date, b.bkg_checkin_date, b.bkg_status,
                r.room_number, rt.type_name, rt.type_price,
                c.ctr_id, c.ctr_deposit, c.access_token, c.ctr_start, c.ctr_end,
                (SELECT exp_status FROM expense WHERE ctr_id = c.ctr_id ORDER BY exp_month DESC LIMIT 1) as exp_status,
                COALESCE(tw.current_step, 1) as current_step,
                COALESCE(tw.completed, 0) as workflow_completed,
                COALESCE(tw.step_2_confirmed, 0) as step_2_confirmed,
                (SELECT COALESCE(SUM(CASE WHEN bp_status = '1' THEN bp_amount ELSE 0 END), 0) FROM booking_payment WHERE bkg_id = b.bkg_id) as paid_amount,
                (SELECT COUNT(*) FROM signature_logs WHERE contract_id = c.ctr_id AND signer_type = 'tenant') as has_signature
            FROM tenant t
                    INNER JOIN booking b ON t.tnt_id = b.tnt_id AND b.bkg_status IN ('1', '2')
            LEFT JOIN room r ON b.room_id = r.room_id
            LEFT JOIN roomtype rt ON r.type_id = rt.type_id
            LEFT JOIN contract c ON t.tnt_id = c.tnt_id AND c.room_id = b.room_id AND c.ctr_status = '0'
            LEFT JOIN tenant_workflow tw ON b.bkg_id = tw.bkg_id
            WHERE t.tnt_id = ? AND b.bkg_id = ?
            LIMIT 1
        ");
        $stmt->execute([$resolvedTenantId, $bookingRef]);
        $bookingInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($bookingInfo && !empty($bookingInfo['bkg_id'])) {
            $showResult = true;
        }
    } catch (PDOException $e) {
        error_log('Auto-load booking error: ' . $e->getMessage());
    }
}

// ถ้ามีการส่งข้อมูลมา (ผู้ใช้ทั่วไปค้นหาได้ด้วยเบอร์โทรอย่างเดียว)
if ((!empty($bookingRef) || !empty($contactInfo)) || $isTenantLoggedIn) {
    $bookingRef = preg_replace('/[^0-9a-zA-Z]/', '', $bookingRef);
    $contactInfo = preg_replace('/[^0-9]/', '', $contactInfo);

    try {
        if ($isTenantLoggedIn) {
            $stmt = $pdo->prepare("
                SELECT 
                    t.tnt_id, t.tnt_name, t.tnt_phone,
                    b.bkg_id, b.bkg_date, b.bkg_checkin_date, b.bkg_status,
                    r.room_number, rt.type_name, rt.type_price,
                    c.ctr_id, c.ctr_deposit, c.access_token,
                    (SELECT exp_status FROM expense WHERE ctr_id = c.ctr_id ORDER BY exp_month DESC LIMIT 1) as exp_status,
                    COALESCE(tw.current_step, 1) as current_step,
                    COALESCE(tw.completed, 0) as workflow_completed,
                    COALESCE(tw.step_2_confirmed, 0) as step_2_confirmed,
                    (SELECT COALESCE(SUM(CASE WHEN bp_status = '1' THEN bp_amount ELSE 0 END), 0) FROM booking_payment WHERE bkg_id = b.bkg_id) as paid_amount,
                    (SELECT COUNT(*) FROM signature_logs WHERE contract_id = c.ctr_id AND signer_type = 'tenant') as has_signature
                FROM tenant t
                LEFT JOIN booking b ON t.tnt_id = b.tnt_id AND b.bkg_status IN ('1', '2')
                LEFT JOIN room r ON b.room_id = r.room_id
                LEFT JOIN roomtype rt ON r.type_id = rt.type_id
                LEFT JOIN contract c ON t.tnt_id = c.tnt_id AND c.room_id = b.room_id AND c.ctr_status = '0'
                LEFT JOIN tenant_workflow tw ON b.bkg_id = tw.bkg_id
                WHERE t.tnt_id = ? AND b.bkg_id = ?
                LIMIT 1
            ");
            $stmt->execute([$resolvedTenantId, $bookingRef]);
        } else {
            if (!empty($bookingRef)) {
                $stmt = $pdo->prepare("
                    SELECT 
                        t.tnt_id, t.tnt_name, t.tnt_phone,
                        b.bkg_id, b.bkg_date, b.bkg_checkin_date, b.bkg_status,
                        r.room_number, rt.type_name, rt.type_price,
                        c.ctr_id, c.ctr_deposit, c.access_token,
                        (SELECT exp_status FROM expense WHERE ctr_id = c.ctr_id ORDER BY exp_month DESC LIMIT 1) as exp_status,
                        COALESCE(tw.current_step, 1) as current_step,
                        COALESCE(tw.completed, 0) as workflow_completed,
                        COALESCE(tw.step_2_confirmed, 0) as step_2_confirmed,
                        (SELECT COALESCE(SUM(CASE WHEN bp_status = '1' THEN bp_amount ELSE 0 END), 0) FROM booking_payment WHERE bkg_id = b.bkg_id) as paid_amount,
                        (SELECT COUNT(*) FROM signature_logs WHERE contract_id = c.ctr_id AND signer_type = 'tenant') as has_signature
                    FROM tenant t
                    LEFT JOIN booking b ON t.tnt_id = b.tnt_id AND b.bkg_status IN ('1', '2')
                    LEFT JOIN room r ON b.room_id = r.room_id
                    LEFT JOIN roomtype rt ON r.type_id = rt.type_id
                    LEFT JOIN contract c ON t.tnt_id = c.tnt_id AND c.room_id = b.room_id AND c.ctr_status = '0'
                    LEFT JOIN tenant_workflow tw ON b.bkg_id = tw.bkg_id
                    WHERE (b.bkg_id = ? OR t.tnt_id = ?) AND t.tnt_phone = ?
                    LIMIT 1
                ");
                $stmt->execute([$bookingRef, $bookingRef, $contactInfo]);
            } else {
                // เบอร์โทรอย่างเดียว: เลือกรายการจองล่าสุดของเบอร์นั้น
                $stmt = $pdo->prepare("
                    SELECT 
                        t.tnt_id, t.tnt_name, t.tnt_phone,
                        b.bkg_id, b.bkg_date, b.bkg_checkin_date, b.bkg_status,
                        r.room_number, rt.type_name, rt.type_price,
                        c.ctr_id, c.ctr_deposit, c.access_token,
                        (SELECT exp_status FROM expense WHERE ctr_id = c.ctr_id ORDER BY exp_month DESC LIMIT 1) as exp_status,
                        COALESCE(tw.current_step, 1) as current_step,
                        COALESCE(tw.completed, 0) as workflow_completed,
                        COALESCE(tw.step_2_confirmed, 0) as step_2_confirmed,
                        (SELECT COALESCE(SUM(CASE WHEN bp_status = '1' THEN bp_amount ELSE 0 END), 0) FROM booking_payment WHERE bkg_id = b.bkg_id) as paid_amount,
                        (SELECT COUNT(*) FROM signature_logs WHERE contract_id = c.ctr_id AND signer_type = 'tenant') as has_signature
                    FROM tenant t
                    LEFT JOIN booking b ON t.tnt_id = b.tnt_id AND b.bkg_status IN ('1', '2')
                    LEFT JOIN room r ON b.room_id = r.room_id
                    LEFT JOIN roomtype rt ON r.type_id = rt.type_id
                    LEFT JOIN contract c ON t.tnt_id = c.tnt_id AND c.room_id = b.room_id AND c.ctr_status = '0'
                    LEFT JOIN tenant_workflow tw ON b.bkg_id = tw.bkg_id
                    WHERE t.tnt_phone = ?
                    ORDER BY b.bkg_id DESC
                    LIMIT 1
                ");
                $stmt->execute([$contactInfo]);
            }
        }
        $bookingInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($bookingInfo && !empty($bookingInfo['bkg_id'])) {
            $showResult = true;
        } else {
            $error = 'ไม่พบข้อมูลการจอง กรุณาตรวจสอบหมายเลขการจองและเบอร์โทรศัพท์';
        }
    } catch (PDOException $e) {
        $error = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($contactInfo) && !$isTenantLoggedIn) {
        $error = 'กรุณากรอกเบอร์โทรศัพท์';
    }
}

// คำนวณค่ามัดจำ
$deposit = floatval($bookingInfo['ctr_deposit'] ?? 2000);
if ($deposit <= 0) {
    $deposit = 2000;
}
$paid = floatval($bookingInfo['paid_amount'] ?? 0);
$step2Confirmed = intval($bookingInfo['step_2_confirmed'] ?? 0);
// ถ้า workflow ยืนยันขั้นตอนมัดจำแล้ว แต่ยอดยังไม่ซิงก์ ให้ถือว่ามัดจำครบตามจริง
if ($step2Confirmed === 1 && $paid <= 0) {
    $paid = $deposit;
}
$remaining = max(0, $deposit - $paid);
$paidColor = $paid > 0 ? 'var(--success)' : 'var(--danger)';

// สถานะการจอง
$statusLabels = [
    '0' => ['text' => 'ยกเลิก', 'color' => '#ef4444'],
    '1' => ['text' => 'รอยืนยัน', 'color' => '#f59e0b'],
    '2' => ['text' => 'เข้าพักแล้ว', 'color' => '#22c55e']
];
$currentStatus = $bookingInfo['bkg_status'] ?? '1';
$expStatus = $bookingInfo['exp_status'] ?? '0';
$currentStepForStatus = intval($bookingInfo['current_step'] ?? 1);
$statusDetail = '';

if ($currentStatus === '1') {
    if ($step2Confirmed === 0) {
        $statusLabels['1'] = ['text' => 'รอยืนยันชำระมัดจำ', 'color' => '#f59e0b'];
        $statusDetail = 'รอยืนยันการชำระค่ามัดจำจากผู้ดูแลระบบ กรุณาตรวจสอบสลิปหรือรายการชำระให้ถูกต้อง';
    } elseif ($currentStepForStatus < 4) {
        $statusLabels['1'] = ['text' => 'จองสำเร็จ (รอเตรียมเช็คอิน)', 'color' => '#0ea5e9'];
        $statusDetail = 'ยืนยันมัดจำแล้ว ระบบกำลังเตรียมสัญญาและขั้นตอนก่อนเช็คอิน';
    } else {
        $statusLabels['1'] = ['text' => 'รอเช็คอิน', 'color' => '#22c55e'];
        $statusDetail = 'รอการยืนยันขั้นตอนสุดท้ายก่อนเปลี่ยนสถานะเป็นเข้าพักแล้ว';
    }
} elseif ($currentStatus === '2') {
    $statusDetail = 'ยืนยันการจองและเข้าพักเรียบร้อยแล้ว';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <title>ตรวจสอบสถานะการจอง - <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="icon" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --primary: <?php echo $themeColor; ?>;
            --action-bg: #2563eb;
            --action-bg-hover: #1d4ed8;
            --action-fg: #ffffff;
            --bg: #0f172a;
            --card: #1e293b;
            --card-hover: #334155;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --text-secondary: #94a3b8;
            --border: #334155;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
        }

        body.theme-light {
            --action-bg: #1d4ed8;
            --action-bg-hover: #1e40af;
            --bg: #f3f6fb;
            --card: #ffffff;
            --card-hover: #eef2ff;
            --text: #0f172a;
            --text-muted: #64748b;
            --text-secondary: #64748b;
            --border: #dbe3ef;
        }

        @media (prefers-color-scheme: light) {
            body:not(.theme-dark) {
                --action-bg: #1d4ed8;
                --action-bg-hover: #1e40af;
                --bg: #f3f6fb;
                --card: #ffffff;
                --card-hover: #eef2ff;
                --text: #0f172a;
                --text-muted: #64748b;
                --text-secondary: #64748b;
                --border: #dbe3ef;
            }
        }
        
        /* Animations */
        @keyframes pulse-warning {
            0%, 100% {
                box-shadow: 0 8px 24px rgba(245, 158, 11, 0.2);
            }
            50% {
                box-shadow: 0 8px 32px rgba(245, 158, 11, 0.4);
            }
        }
        
        @keyframes bounce-gentle {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-5px);
            }
        }
        
        body {
            font-family: 'Prompt', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            line-height: 1.6;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header */
        .header {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 0;
            margin-bottom: 24px;
        }
        
        .back-btn {
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            color: var(--text);
            text-decoration: none;
        }
        
        .logo {
            width: 40px;
            height: 40px;
            border-radius: 10px;
        }
        
        .site-name {
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        /* Hero */
        .hero {
            text-align: center;
            padding: 40px 20px;
        }
        
        .hero-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: rgba(37, 99, 235, 0.14);
            border: 1px solid rgba(37, 99, 235, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--action-bg);
        }

        body.theme-light .hero-icon {
            background: rgba(37, 99, 235, 0.12);
            border-color: rgba(37, 99, 235, 0.24);
        }
        
        .hero h1 {
            font-size: 1.5rem;
            margin-bottom: 8px;
        }
        
        .hero p {
            color: var(--text-muted);
        }
        
        /* Card */
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 16px;
        }

        .readonly-field {
            background: var(--bg);
            color: var(--text-muted);
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Form */
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-bottom: 8px;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 16px;
            background: var(--bg);
            border: 2px solid var(--border);
            border-radius: 12px;
            color: var(--text);
            font-size: 1rem;
            font-family: inherit;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(180deg, var(--action-bg), var(--action-bg-hover));
            color: var(--action-fg);
            border: 1px solid rgba(29, 78, 216, 0.35);
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 8px 18px rgba(29, 78, 216, 0.22);
            transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            filter: brightness(1.02);
            box-shadow: 0 10px 22px rgba(29, 78, 216, 0.28);
        }

        .btn:active {
            transform: translateY(0);
        }
        
        /* Alert */
        .alert {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--danger);
            color: #fca5a5;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        
        .info-item {
            padding: 12px;
            background: var(--bg);
            border-radius: 10px;
        }
        
        .info-item.full {
            grid-column: 1 / -1;
        }
        
        .info-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        
        .info-value {
            font-size: 1rem;
            font-weight: 500;
        }
        
        .info-value.large {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .info-value.success { color: var(--success); }
        .info-value.warning { color: var(--warning); }
        .info-value.danger { color: var(--danger); }
        
        /* Progress */
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin: 24px 0;
            position: relative;
        }
        
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 16px;
            left: 20px;
            right: 20px;
            height: 3px;
            background: var(--border);
        }
        
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 1;
        }
        
        .step-dot {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--card);
            border: 3px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .step.completed .step-dot {
            background: var(--success);
            border-color: var(--success);
            color: white;
        }
        
        .step.active .step-dot {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        
        .step-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            text-align: center;
            max-width: 60px;
        }
        
        .step.completed .step-label,
        .step.active .step-label {
            color: var(--text);
        }
        
        /* Bank Info */
        .bank-info {
            background: var(--bg);
            border-radius: 12px;
            padding: 16px;
            margin-top: 16px;
        }
        
        .bank-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .bank-row:last-child {
            border-bottom: none;
        }
        
        .copy-btn {
            padding: 8px 12px;
            background: var(--card);
            border: none;
            border-radius: 8px;
            color: var(--text);
            font-size: 0.75rem;
            cursor: pointer;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 16px;
        }
        
        .quick-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            padding: 20px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            text-decoration: none;
            color: var(--text);
        }
        
        .quick-action:hover {
            background: var(--card-hover);
        }

        .cta-card.contract-cta {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.12), transparent);
            border-color: var(--success);
        }

        .cta-card.payment-cta {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(15, 23, 42, 0.04));
            border-color: #3b82f6;
        }

        body.theme-light .cta-card.contract-cta {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.18), rgba(255, 255, 255, 0.9));
        }

        body.theme-light .cta-card.payment-cta {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.16), rgba(239, 246, 255, 0.96));
            border-color: #93c5fd;
        }

        .payment-cta-wrap {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .payment-cta-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #2563eb;
            color: #ffffff;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.28);
            flex-shrink: 0;
        }

        body.theme-light .payment-cta-icon {
            background: #1d4ed8;
        }

        .payment-cta-body {
            flex: 1;
        }

        .payment-cta-title {
            font-weight: 700;
            color: var(--text);
        }

        .payment-cta-subtitle {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin: 4px 0 8px;
        }

        .cta-link {
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .cta-link.contract-link {
            color: var(--success);
        }

        .cta-link.payment-link {
            color: #1d4ed8;
        }

        .cta-link:hover {
            text-decoration: underline;
            text-underline-offset: 3px;
        }
        
        /* Warning Alert Button Hover */
        a[href*="contract.php"][style*="linear-gradient"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(245, 158, 11, 0.5) !important;
        }
        
        @media (max-width: 480px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            .step-label {
                font-size: 0.6rem;
                max-width: 50px;
            }
        }
    </style>
</head>
<body class="theme-light">
    <div class="container">
        <!-- Header -->
        <div class="header">
            <a href="../index.php" class="back-btn">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
            </a>
            <img src="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="" class="logo">
            <span class="site-name"><?php echo htmlspecialchars($siteName); ?></span>
        </div>
        
        <?php if (!$showResult): ?>
        <!-- Search Form -->
        <div class="hero">
            <div class="hero-icon">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="m21 21-4.35-4.35"/>
                </svg>
            </div>
            <h1>ตรวจสอบสถานะการจอง</h1>
            <p>กรอกข้อมูลเพื่อดูสถานะการจองของคุณ</p>
        </div>
        
        <?php if ($error || $noBookingForTenant): ?>
        <div class="alert alert-error">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <?php echo htmlspecialchars($noBookingForTenant ? 'ยังไม่มีรายการจองในบัญชีนี้' : $error); ?>
        </div>
        <?php endif; ?>

        <?php if ($noBookingForTenant): ?>
        <div style="text-align: center; margin-bottom: 16px;">
            <a href="../Public/booking.php" class="btn" style="display: inline-flex; align-items: center; gap: 8px;">
                ไปหน้าจองห้อง
            </a>
        </div>
        <?php endif; ?>
        
        <?php if (!$noBookingForTenant): ?>
        <div class="card">
            <form method="post" id="bookingForm">
                <div class="form-group">
                    <label>หมายเลขการจอง (ไม่บังคับ)</label>
                    <?php if ($isTenantLoggedIn && !empty($tenantBookings)): ?>
                        <select name="booking_ref" class="form-control" required onchange="this.form.submit()">
                            <option value="">เลือกหมายเลขการจอง</option>
                            <?php foreach ($tenantBookings as $tb): ?>
                                <option value="<?php echo htmlspecialchars($tb['bkg_id']); ?>" <?php echo ((string)$bookingRef === (string)$tb['bkg_id']) ? 'selected' : ''; ?> >
                                    <?php echo htmlspecialchars($tb['bkg_id']); ?>
                                    <?php if (!empty($tb['bkg_checkin_date'])): ?> (เข้าพัก <?php echo htmlspecialchars(thaiDate($tb['bkg_checkin_date'])); ?>)<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (count($tenantBookings) === 1 && !$showResult): ?>
                        <script>
                            // Auto-select first booking if only one exists
                            document.addEventListener('DOMContentLoaded', function() {
                                const select = document.querySelector('select[name="booking_ref"]');
                                if (select) {
                                    select.value = '<?php echo htmlspecialchars($tenantBookings[0]['bkg_id']); ?>';
                                    select.form.submit();
                                }
                            });
                        </script>
                        <?php endif; ?>
                    <?php else: ?>
                        <input type="text" name="booking_ref" class="form-control" placeholder="เช่น 770004930 (ไม่กรอกก็ได้)" value="<?php echo htmlspecialchars($bookingRef); ?>" id="bookingRefInput">
                        <?php if (!empty($bookingRef) && $isTenantLoggedIn && !$showResult): ?>
                        <script>
                            // Auto-submit form when booking ref is auto-filled for logged-in user
                            document.addEventListener('DOMContentLoaded', function() {
                                const input = document.getElementById('bookingRefInput');
                                const form = input?.form;
                                if (input && input.value && form) {
                                    console.log('Auto-submitting form with booking ref: ' + input.value);
                                    setTimeout(function() {
                                        form.submit();
                                    }, 100);
                                }
                            });
                        </script>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>เบอร์โทรศัพท์</label>
                    <?php if ($isTenantLoggedIn): ?>
                        <input type="hidden" name="contact_info" value="<?php echo htmlspecialchars($contactInfo); ?>">
                        <div class="form-control readonly-field">
                            <?php echo !empty($contactInfo) ? htmlspecialchars($contactInfo) : 'ไม่พบเบอร์โทรในระบบ'; ?>
                        </div>
                    <?php else: ?>
                        <input type="tel" name="contact_info" class="form-control" placeholder="เช่น 0812345678" value="<?php echo htmlspecialchars($contactInfo); ?>" maxlength="10" required>
                    <?php endif; ?>
                </div>
                <?php if ($isTenantLoggedIn && !empty($bookingRef)): ?>
                    <input type="hidden" name="auto_submit" value="1">
                <?php endif; ?>
                <button type="submit" class="btn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.35-4.35"/>
                    </svg>
                    ค้นหาการจอง
                </button>
            </form>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <!-- Booking Result -->
        
        <!-- Status Card -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <div>
                    <div style="color: var(--text-muted); font-size: 0.875rem;">📋 หมายเลขการจอง</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--text); letter-spacing: 1px;">
                        <?php echo htmlspecialchars($bookingInfo['bkg_id']); ?>
                    </div>
                </div>
                <span class="status-badge" style="background: <?php echo $statusLabels[$currentStatus]['color']; ?>20; color: <?php echo $statusLabels[$currentStatus]['color']; ?>;">
                    <?php echo $statusLabels[$currentStatus]['text']; ?>
                </span>
            </div>
            <?php if ($statusDetail !== ''): ?>
            <div style="margin-top: -6px; margin-bottom: 14px; color: var(--text-muted); font-size: 0.86rem; line-height: 1.5;">
                <?php echo htmlspecialchars($statusDetail); ?>
            </div>
            <?php endif; ?>
            
            <!-- Booking Details Alert -->
            <?php if ($isTenantLoggedIn): ?>
            <div style="background: var(--bg); padding: 14px; border-radius: 10px; margin-bottom: 16px; border-left: 4px solid var(--primary);">
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">🎫 เลขที่การจองของคุณ</div>
                <div style="font-size: 1.1rem; font-weight: 700; color: var(--text); font-family: 'Courier New', monospace; letter-spacing: 2px;">
                    <?php echo htmlspecialchars($bookingInfo['bkg_id']); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Progress Steps -->
            <?php
            $steps = ['ยืนยันจอง', 'ยืนยันชำระเงินจอง', 'สร้างสัญญา', 'เช็คอิน', 'เริ่มบิลรายเดือน'];
            // Use current_step from tenant_workflow table (which is now fetched from database)
            $currentStep = intval($bookingInfo['current_step'] ?? 1);
            $workflowCompleted = intval($bookingInfo['workflow_completed'] ?? 0);
            // Ensure currentStep doesn't exceed 5 if workflow is completed
            if ($workflowCompleted === 1 && $currentStep > 5) {
                $currentStep = 5;
            }
            ?>
            <div class="progress-steps">
                <?php foreach ($steps as $idx => $step): 
                    $stepNum = $idx + 1;
                    // Step เป็น completed เมื่อ: stepNum น้อยกว่า currentStep หรือ workflow completed และ stepNum <= currentStep
                    $isCompleted = $stepNum < $currentStep || ($workflowCompleted === 1 && $stepNum <= $currentStep);
                    $isActive = $stepNum === $currentStep && $workflowCompleted !== 1;
                ?>
                <div class="step <?php echo $isCompleted ? 'completed' : ($isActive ? 'current' : ''); ?>">
                    <div class="step-dot">
                        <?php if ($isCompleted): ?>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        <?php else: ?>
                        <?php echo $stepNum; ?>
                        <?php endif; ?>
                    </div>
                    <span class="step-label"><?php echo $step; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Alert สำหรับเตือนให้เซ็นสัญญา (แสดงเมื่อ step >= 4 คือสร้างสัญญาเสร็จแล้ว และยังไม่มีลายเซ็น) -->
            <?php 
            $hasSignature = !empty($bookingInfo['has_signature']) && intval($bookingInfo['has_signature']) > 0;
            $needsSignature = $currentStep >= 4 && !empty($bookingInfo['access_token']) && $expStatus === '1' && !$hasSignature;
            if ($needsSignature): 
            ?>
            <div style="margin-top: 20px; padding: 20px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border: 2px solid #f59e0b; border-radius: 16px; box-shadow: 0 8px 24px rgba(245, 158, 11, 0.2); animation: pulse-warning 2s ease-in-out infinite;">
                <div style="display: flex; align-items: start; gap: 16px;">
                    <div style="width: 48px; height: 48px; background: #f59e0b; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; animation: bounce-gentle 1.5s ease-in-out infinite;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                            <line x1="12" y1="9" x2="12" y2="13"/>
                            <line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-size: 1.125rem; font-weight: 700; color: #92400e; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                            <span>⚠️ <?php echo $currentStep > 3 ? 'คุณยังไม่ได้เซ็นสัญญา!' : 'จำเป็นต้องเซ็นสัญญา!'; ?></span>
                        </div>
                        <div style="font-size: 0.9375rem; color: #78350f; margin-bottom: 16px; line-height: 1.6;">
                            <?php if ($currentStep > 3): ?>
                                <strong>สำคัญ!</strong> แม้ว่าระบบจะดำเนินการไปถึงขั้นตอนที่ <?php echo $currentStep; ?> แล้ว แต่คุณ<strong>ยังไม่ได้เซ็นสัญญา</strong> กรุณาคลิกปุ่มด้านล่างเพื่อเข้าไปเซ็นสัญญาให้เรียบร้อย
                            <?php else: ?>
                                คุณต้องเข้าไปเพิ่ม<strong>ลายเซ็นของคุณ</strong>ในสัญญาเช่าก่อนที่จะดำเนินการขั้นตอนต่อไป กรุณาคลิกปุ่มด้านล่างเพื่อไปยังหน้าสัญญาและเซ็นชื่อ
                            <?php endif; ?>
                        </div>
                        <a href="../Tenant/contract.php?token=<?php echo urlencode($bookingInfo['access_token']); ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 10px; padding: 14px 24px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; text-decoration: none; border-radius: 12px; font-weight: 600; font-size: 0.9375rem; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3); transition: all 0.3s ease; border: 2px solid #b45309;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                                <polyline points="10 9 9 9 8 9"/>
                            </svg>
                            <span><?php echo $currentStep > 3 ? 'เซ็นสัญญาด่วน!' : 'เข้าไปเซ็นสัญญาเลย'; ?></span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
                                <polyline points="9 18 15 12 9 6"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Payment Card -->
        <div class="card">
            <div class="card-title" style="color: var(--text-muted);">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="1" y="4" width="22" height="16" rx="2"/>
                    <line x1="1" y1="10" x2="23" y2="10"/>
                </svg>
                ค่ามัดจำ
            </div>
            
            <div style="text-align: center; padding: 20px 0; border-top: 1px dashed var(--border); border-bottom: 1px dashed var(--border); margin: 16px 0;">
                <div style="color: var(--text-muted); margin-bottom: 8px;">ยอดคงเหลือ</div>
                <div class="info-value large <?php echo $remaining > 0 ? 'danger' : 'success'; ?>">
                    ฿<?php echo number_format($remaining); ?>
                </div>
            </div>
            
            <div style="display: flex; justify-content: space-between; font-size: 0.875rem;">
                <div>
                    <span style="color: var(--text-muted);">ค่ามัดจำ</span>
                    <div style="font-weight: 600;">฿<?php echo number_format($deposit); ?></div>
                </div>
                <div style="text-align: right;">
                    <span style="color: var(--text-muted);">ชำระแล้ว</span>
                    <div style="font-weight: 600; color: <?php echo $paidColor; ?>;">฿<?php echo number_format($paid); ?></div>
                </div>
            </div>
            
            <?php if ($remaining > 0 && (!empty($bankName) || !empty($promptpayNumber))): ?>
            <div class="bank-info">
                <div style="font-weight: 600; margin-bottom: 12px;">ช่องทางชำระเงิน</div>
                <?php if (!empty($bankName)): ?>
                <div class="bank-row">
                    <span style="color: var(--text-muted);">ธนาคาร</span>
                    <span><?php echo htmlspecialchars($bankName); ?></span>
                </div>
                <?php if (!empty($bankAccountName)): ?>
                <div class="bank-row">
                    <span style="color: var(--text-muted);">ชื่อบัญชี</span>
                    <span><?php echo htmlspecialchars($bankAccountName); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($bankAccountNumber)): ?>
                <div class="bank-row">
                    <span style="color: var(--text-muted);">เลขบัญชี</span>
                    <span style="display: flex; align-items: center; gap: 8px;">
                        <?php echo htmlspecialchars($bankAccountNumber); ?>
                        <button class="copy-btn" onclick="copyText('<?php echo htmlspecialchars($bankAccountNumber); ?>')">คัดลอก</button>
                    </span>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                <?php if (!empty($promptpayNumber)): ?>
                <div class="bank-row">
                    <span style="color: var(--text-muted);">พร้อมเพย์</span>
                    <span style="display: flex; align-items: center; gap: 8px; color: var(--success);">
                        <?php echo htmlspecialchars($promptpayNumber); ?>
                        <button class="copy-btn" onclick="copyText('<?php echo htmlspecialchars($promptpayNumber); ?>')">คัดลอก</button>
                    </span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Room Info -->
        <div class="card">
            <div class="card-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="<?php echo $themeColor; ?>" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                ข้อมูลห้องพัก
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">ห้อง</div>
                    <div class="info-value"><?php echo htmlspecialchars($bookingInfo['room_number'] ?? '-'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">ประเภท</div>
                    <div class="info-value"><?php echo htmlspecialchars($bookingInfo['type_name'] ?? '-'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">ค่าห้อง/เดือน</div>
                    <div class="info-value success">฿<?php echo number_format(floatval($bookingInfo['type_price'] ?? 0)); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">วันเข้าพัก</div>
                    <div class="info-value"><?php echo thaiDateFormat($bookingInfo['bkg_checkin_date'] ?? ''); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Tenant Info -->
        <div class="card">
            <div class="card-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="<?php echo $themeColor; ?>" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                ข้อมูลผู้จอง
            </div>
            <div class="info-grid">
                <div class="info-item full">
                    <div class="info-label">ชื่อ-นามสกุล</div>
                    <div class="info-value"><?php echo htmlspecialchars($bookingInfo['tnt_name'] ?? '-'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">เบอร์โทร</div>
                    <div class="info-value"><?php echo htmlspecialchars($bookingInfo['tnt_phone'] ?? '-'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">วันที่จอง</div>
                    <div class="info-value"><?php echo thaiDateFormat($bookingInfo['bkg_date'] ?? ''); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Contract Link -->
        <?php if (!empty($bookingInfo['access_token']) && $expStatus === '1'): ?>
        <div class="card cta-card contract-cta">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div style="width: 48px; height: 48px; background: var(--success); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                </div>
                <div style="flex: 1;">
                    <div style="font-weight: 600;">สัญญาเช่าพร้อมแล้ว</div>
                    <div style="font-size: 0.75rem; color: var(--text-secondary); margin: 4px 0 8px;">
                        กรุณาคลิกเพื่อดูและเพิ่มลายเซ็นของคุณในสัญญา
                    </div>
                    <a href="../Tenant/contract.php?token=<?php echo urlencode($bookingInfo['access_token']); ?>" target="_blank" class="cta-link contract-link">
                        ดูสัญญาและเซ็นชื่อ →
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Payment Link (แสดงเมื่อเริ่มบิลเดือนแรกแล้วเท่านั้น) -->
        <?php $hasFirstBillingCycle = isset($bookingInfo['exp_status']) && $bookingInfo['exp_status'] !== null && $bookingInfo['exp_status'] !== ''; ?>
        <?php if (!empty($bookingInfo['access_token']) && $currentStep >= 5 && $hasFirstBillingCycle): ?>
        <div class="card cta-card payment-cta">
            <div class="payment-cta-wrap">
                <div class="payment-cta-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="1" y="4" width="22" height="16" rx="2"/>
                        <line x1="1" y1="10" x2="23" y2="10"/>
                    </svg>
                </div>
                <div class="payment-cta-body">
                    <div class="payment-cta-title">ชำระค่าเช่ารายเดือน</div>
                    <div class="payment-cta-subtitle">
                        แจ้งชำระเงินค่าเช่าและค่าบริการรายเดือน
                    </div>
                    <a href="../Tenant/payment.php?token=<?php echo urlencode($bookingInfo['access_token']); ?>" target="_blank" class="cta-link payment-link">
                        เข้าสู่หน้าชำระเงิน →
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="tel:<?php echo htmlspecialchars($contactPhone); ?>" class="quick-action">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72"/>
                </svg>
                <span style="font-size: 0.875rem;">โทรหาเรา</span>
            </a>
            <a href="https://maps.google.com/?q=<?php echo urlencode($siteName); ?>" target="_blank" class="quick-action">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                    <circle cx="12" cy="10" r="3"/>
                </svg>
                <span style="font-size: 0.875rem;">นำทาง</span>
            </a>
        </div>
        
        <!-- Search Again -->
        <div style="text-align: center; margin-top: 24px;">
            <a href="booking_status.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.875rem;">
                ← ค้นหาการจองอื่น
            </a>
        </div>
        
        <?php endif; ?>
    </div>
    
    <script>
        function copyText(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('คัดลอกแล้ว: ' + text);
            });
        }
        
        // Auto-submit form when user selects from dropdown (for multi-booking case)
        document.addEventListener('DOMContentLoaded', function() {
            const select = document.querySelector('select[name="booking_ref"]');
            if (select) {
                select.addEventListener('change', function() {
                    if (this.value) {
                        this.form.submit();
                    }
                });
            }
            
            // Auto-show result if redirected from Google login with single booking
            const form = document.querySelector('form');
            const autoSubmitField = form ? form.querySelector('input[name="auto_submit"]') : null;
            
            if (autoSubmitField && form) {
                console.log('Auto-submitting form for single booking...');
                setTimeout(() => {
                    form.submit();
                }, 100);
            }
        });
    </script>
</body>
</html>
