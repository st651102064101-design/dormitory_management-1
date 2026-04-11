<?php
/**
 * Tenant Portal - หน้าหลักสำหรับผู้เช่า
 * เข้าถึงผ่าน QR Code พร้อม access token
 */
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../ConnectDB.php';
require_once __DIR__ . '/../includes/thai_date_helper.php';
$pdo = connectDB();

function getTenantTokenCookiePath(): string {
    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = rtrim(dirname($scriptName), '/');

    if ($dir === '' || $dir === '.') {
        return '/';
    }

    return $dir;
}

function persistTenantPortalToken(string $token): void {
    if ($token === '' || headers_sent()) {
        return;
    }

    setcookie('tenant_portal_token', $token, [
        'expires' => time() + (180 * 24 * 60 * 60),
        'path' => getTenantTokenCookiePath(),
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function clearTenantPortalToken(): void {
    if (headers_sent()) {
        return;
    }

    setcookie('tenant_portal_token', '', [
        'expires' => time() - 3600,
        'path' => getTenantTokenCookiePath(),
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// รับ token จาก URL ก่อน แล้วค่อย fallback ไปที่ session/cookie
$tokenFromQuery = trim((string)($_GET['token'] ?? ''));
$tokenFromSession = trim((string)($_SESSION['tenant_token'] ?? ''));
$tokenFromCookie = trim((string)($_COOKIE['tenant_portal_token'] ?? ''));
$token = $tokenFromQuery !== ''
    ? $tokenFromQuery
    : ($tokenFromSession !== '' ? $tokenFromSession : $tokenFromCookie);

if ($tokenFromQuery !== '') {
    persistTenantPortalToken($tokenFromQuery);
}

$contractData = null;

// ก่อนอื่นตรวจสอบ token (QR Code / Token)
if (!empty($token)) {
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   t.tnt_id, t.tnt_name, t.tnt_phone, t.tnt_address, t.tnt_education, t.tnt_faculty, t.tnt_year, t.tnt_vehicle, t.tnt_parent, t.tnt_parentsphone, t.tnt_age, t.line_user_id, t.is_weather_alert_enabled,
                   r.room_id, r.room_number, r.room_image,
                   rt.type_name, rt.type_price,
                   tw.current_step, tw.step_3_confirmed
            FROM contract c
            JOIN tenant t ON c.tnt_id = t.tnt_id
            JOIN room r ON c.room_id = r.room_id
            LEFT JOIN roomtype rt ON r.type_id = rt.type_id
            LEFT JOIN tenant_workflow tw ON t.tnt_id = tw.tnt_id
            WHERE c.access_token = ? AND c.ctr_status IN ('0', '2')
            ORDER BY tw.id DESC
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $contractData = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }
}

// ถ้าไม่มี token หรือ token ไม่ถูกต้อง ลองเอาจาก session ของ Google OAuth
if (!$contractData && !empty($_SESSION['tenant_logged_in'])) {
    try {
        $tenantId = $_SESSION['tenant_id'] ?? '';
        if (!empty($tenantId)) {
            $stmt = $pdo->prepare("
                SELECT c.*, 
                       t.tnt_id, t.tnt_name, t.tnt_phone, t.tnt_address, t.tnt_education, t.tnt_faculty, t.tnt_year, t.tnt_vehicle, t.tnt_parent, t.tnt_parentsphone, t.tnt_age, t.line_user_id, t.is_weather_alert_enabled,
                       r.room_id, r.room_number, r.room_image,
                       rt.type_name, rt.type_price,
                       tw.current_step, tw.step_3_confirmed
                FROM contract c
                JOIN tenant t ON c.tnt_id = t.tnt_id
                JOIN room r ON c.room_id = r.room_id
                LEFT JOIN roomtype rt ON r.type_id = rt.type_id
                LEFT JOIN tenant_workflow tw ON t.tnt_id = tw.tnt_id
                WHERE c.tnt_id = ? AND c.ctr_status IN ('0', '1', '2')
                ORDER BY c.ctr_id DESC, tw.id DESC
                LIMIT 1
            ");
            $stmt->execute([$tenantId]);
            $contractData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // ถ้าพบสัญญา ให้สร้าง token หรือเก็บ token ที่มีอยู่
            if ($contractData && !empty($contractData['access_token'])) {
                $token = $contractData['access_token'];
            }
        }
    } catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }
}

if (!$contractData) {
    if ($tokenFromCookie !== '' && $token === $tokenFromCookie) {
        clearTenantPortalToken();
    }

    if (!empty($token)) {
        http_response_code(403);
        die('<div style="text-align:center;padding:50px;font-family:sans-serif;"><h1>⚠️ Token ไม่ถูกต้องหรือหมดอายุ</h1><p>กรุณาติดต่อผู้ดูแลหอพัก</p></div>');
    } else {
        http_response_code(403);
        die('<div style="text-align:center;padding:50px;font-family:sans-serif;"><h1>⚠️ ไม่พบ Token</h1><p>กรุณาสแกน QR Code หรือเข้าสู่ระบบก่อน</p></div>');
    }
}

$contract = $contractData;
$token = trim((string)($contract['access_token'] ?? $token));
if ($token !== '') {
    persistTenantPortalToken($token);
}

// จัดการการยกเลิกผูก LINE
if (isset($_GET['action']) && $_GET['action'] === 'unlink_line' && !empty($contract['tnt_id'])) {
    try {
        $undoStmt = $pdo->prepare("UPDATE tenant SET line_user_id = NULL, is_weather_alert_enabled = 0 WHERE tnt_id = ?");
        $undoStmt->execute([$contract['tnt_id']]);
        header("Location: index.php" . (!empty($token) ? "?token=" . urlencode($token) : ""));
        exit;
    } catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }
}

// เก็บข้อมูลใน session สำหรับหน้าอื่นๆ
$_SESSION['tenant_token'] = $token;
$_SESSION['tenant_ctr_id'] = $contract['ctr_id'];
$_SESSION['tenant_tnt_id'] = $contract['tnt_id'];
$_SESSION['tenant_room_id'] = $contract['room_id'];
$_SESSION['tenant_room_number'] = $contract['room_number'];
$_SESSION['tenant_name'] = $contract['tnt_name'];

// ดึงค่าตั้งค่าระบบ
$siteName = 'Sangthian Dormitory';
$logoFilename = 'Logo.jpg';
$publicTheme = 'dark';
$settings = [];
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename', 'public_theme', 'openweathermap_api_key', 'openweathermap_city', 'google_maps_embed', 'line_add_friend_url', 'line_qr_code_image', 'line_login_channel_id')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
        if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
        if ($row['setting_key'] === 'public_theme') $publicTheme = $row['setting_value'];
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }

// ดึงข้อมูลบิลค่าใช้จ่ายล่าสุด
$latestExpense = null;
try {
    $expStmt = $pdo->prepare("SELECT * FROM expense WHERE ctr_id = ? ORDER BY exp_month DESC LIMIT 1");
    $expStmt->execute([$contract['ctr_id']]);
    $latestExpense = $expStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }

// ดึงบิลค้างชำระสำหรับชำระค่าเช่าเดือนแรก
$firstUnpaidExpense = null;
try {
    $unpaidStmt = $pdo->prepare("\n        SELECT
            e.*,
            (COALESCE(ps.submitted_rent_amount, 0) + COALESCE(ps.submitted_deposit_amount, 0)) AS submitted_amount,
            (e.exp_total - (COALESCE(ps.submitted_rent_amount, 0) + COALESCE(ps.submitted_deposit_amount, 0))) AS remaining_amount
        FROM expense e
        JOIN (
            SELECT MAX(exp_id) AS exp_id
            FROM expense
            WHERE ctr_id = ?
              AND exp_status IN ('0', '3', '4')
              AND DATE_FORMAT(exp_month, '%Y-%m') <= DATE_FORMAT(CURDATE(), '%Y-%m')
            GROUP BY DATE_FORMAT(exp_month, '%Y-%m')
        ) latest ON latest.exp_id = e.exp_id
        LEFT JOIN (
            SELECT 
                exp_id, 
                COALESCE(SUM(CASE WHEN TRIM(COALESCE(pay_remark, '')) <> 'มัดจำ' THEN pay_amount ELSE 0 END), 0) AS submitted_rent_amount,
                COALESCE(SUM(CASE WHEN TRIM(COALESCE(pay_remark, '')) = 'มัดจำ' THEN pay_amount ELSE 0 END), 0) AS submitted_deposit_amount
            FROM payment
            WHERE pay_status IN ('0', '1')
            GROUP BY exp_id
        ) ps ON ps.exp_id = e.exp_id
        WHERE (
            GREATEST(0, (COALESCE(e.room_price, 0) + COALESCE(e.exp_elec_chg, 0) + COALESCE(e.exp_water, 0)) - COALESCE(ps.submitted_rent_amount, 0))
            +
            GREATEST(0, (e.exp_total - (COALESCE(e.room_price, 0) + COALESCE(e.exp_elec_chg, 0) + COALESCE(e.exp_water, 0))) - COALESCE(ps.submitted_deposit_amount, 0))
        ) > 0
        ORDER BY e.exp_month ASC
        LIMIT 1
    ");
    $unpaidStmt->execute([$contract['ctr_id']]);
    $firstUnpaidExpense = $unpaidStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }

// ดึงข่าวประชาสัมพันธ์ล่าสุด
$latestNews = [];
try {
    $newsStmt = $pdo->query("SELECT * FROM news ORDER BY news_date DESC LIMIT 3");
    $latestNews = $newsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }

// ดึงสถานะการแจ้งซ่อมล่าสุด
$latestRepair = null;
try {
    $repairStmt = $pdo->prepare("SELECT * FROM repair WHERE ctr_id = ? ORDER BY repair_date DESC LIMIT 1");
    $repairStmt->execute([$contract['ctr_id']]);
    $latestRepair = $repairStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }

$repairStatusMap = [
    '0' => ['label' => 'รอซ่อม', 'color' => '#f59e0b'],
    '1' => ['label' => 'กำลังซ่อม', 'color' => '#3b82f6'],
    '2' => ['label' => 'ซ่อมเสร็จ', 'color' => '#10b981']
];

$contractStatusMap = [
    '0' => ['label' => 'ปกติ', 'color' => '#10b981'],
    '1' => ['label' => 'ยกเลิกแล้ว', 'color' => '#ef4444'],
    '2' => ['label' => 'แจ้งยกเลิก', 'color' => '#f59e0b']
];

// ตรวจสอบว่าผู้เช่าเซ็นสัญญาแล้วหรือยัง
$tenantSigned = false;
try {
    $sigCheckStmt = $pdo->prepare("SELECT id FROM signature_logs WHERE contract_id = ? AND signer_type = 'tenant' LIMIT 1");
    $sigCheckStmt->execute([$contract['ctr_id']]);
    $tenantSigned = (bool)$sigCheckStmt->fetchColumn();
} catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }

// ดึงข้อมูลเงินมัดจำคืน (สำหรับผู้เช่าที่สัญญาสิ้นสุดแล้ว)
$depositRefund = null;
if (($contract['ctr_status'] ?? '0') === '1') {
    try {
        $drStmt = $pdo->prepare("SELECT * FROM deposit_refund WHERE ctr_id = ? ORDER BY refund_id DESC LIMIT 1");
        $drStmt->execute([$contract['ctr_id']]);
        $depositRefund = $drStmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }
}

$terminationAllowed = false;
$terminationReason = '';

try {
    $termCheckStmt = $pdo->prepare("
        SELECT 
           (
              SELECT CASE WHEN COALESCE(step_5_confirmed, 0) = 1 OR COALESCE(current_step, 0) >= 5 THEN 1 ELSE 0 END
              FROM tenant_workflow
              WHERE tnt_id = c.tnt_id
              ORDER BY id DESC LIMIT 1
           ) AS is_step5_complete,
           (
              SELECT COUNT(*)
              FROM expense e
              WHERE e.ctr_id = c.ctr_id
                AND DATE_FORMAT(e.exp_month, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
           ) AS has_current_month_bill,
           (
              SELECT COUNT(*)
              FROM expense e
              WHERE e.ctr_id = c.ctr_id
                AND (
                    GREATEST(0, (COALESCE(e.room_price, 0) + COALESCE(e.exp_elec_chg, 0) + COALESCE(e.exp_water, 0)) - COALESCE((
                        SELECT SUM(p.pay_amount) FROM payment p
                        WHERE p.exp_id = e.exp_id AND p.pay_status = '1'
                        AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ'
                    ), 0))
                    +
                    GREATEST(0, (e.exp_total - (COALESCE(e.room_price, 0) + COALESCE(e.exp_elec_chg, 0) + COALESCE(e.exp_water, 0))) - COALESCE((
                        SELECT SUM(p.pay_amount) FROM payment p
                        WHERE p.exp_id = e.exp_id AND p.pay_status = '1'
                        AND TRIM(COALESCE(p.pay_remark, '')) = 'มัดจำ'
                    ), 0))
                ) > 0
           ) AS unpaid_bills_count,
           (
              SELECT COUNT(*)
              FROM payment p
              JOIN expense e ON p.exp_id = e.exp_id
              WHERE e.ctr_id = c.ctr_id AND p.pay_status = '0'
           ) AS unverified_payments_count
        FROM contract c
        WHERE c.ctr_id = ?
    ");
    $termCheckStmt->execute([$contract['ctr_id']]);
    $termData = $termCheckStmt->fetch(PDO::FETCH_ASSOC);

    if ($termData) {
        if ((int)$termData['is_step5_complete'] !== 1) {
            $terminationReason = 'รอให้เจ้าหน้าที่ดำเนินการข้อมูลการเข้าพักของคุณให้เสร็จสิ้น (ขั้นตอนที่ 5)';
        } elseif ((int)$termData['has_current_month_bill'] === 0) {
            $terminationReason = 'กรุณารอให้เจ้าหน้าที่จดมิเตอร์และออกบิลค่าใช้จ่ายของเดือนล่าสุดให้เรียบร้อยก่อนแจ้งยกเลิกสัญญา';
        } elseif ((int)$termData['unpaid_bills_count'] > 0) {
            $terminationReason = 'ไม่สามารถแจ้งยกเลิกสัญญาได้ เนื่องจากมียอดค้างชำระจำนวน ' . $termData['unpaid_bills_count'] . ' รายการ หรือมีบิลใหม่ที่เพิ่งออก กรุณาชำระค่าห้องให้ครบก่อน';
        } elseif ((int)$termData['unverified_payments_count'] > 0) {
            $terminationReason = 'มีสลิปการชำระเงินที่รอให้เจ้าหน้าที่ตรวจสอบ กรุณารอเจ้าหน้าที่ตรวจสอบความถูกต้องก่อนจึงจะสามารถแจ้งยกเลิกสัญญาได้';
        } else {
            $terminationAllowed = true;
        }
    }
} catch (PDOException $e) { 
    error_log("PDOException checking termination eligibility: " . $e->getMessage()); 
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($siteName); ?> - Tenant Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Prompt', system-ui, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            color: #e2e8f0;
            padding-bottom: 80px;
        }
        
        .header {
            background: rgba(15, 23, 42, 0.95);
            padding: 1rem;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
        }
        
        .header-content {
            max-width: 600px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .logo {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            object-fit: cover;
        }
        
        .header-info h1 {
            font-size: 1.1rem;
            color: #f8fafc;
        }
        
        .header-info p {
            font-size: 0.85rem;
            color: #94a3b8;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        .room-card {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .room-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        }
        
        .room-number {
            font-size: 3rem;
            font-weight: 700;
            display: flex;
            align-items: baseline;
            gap: 0.5rem;
        }
        
        .room-number span {
            font-size: 1rem;
            opacity: 0.8;
        }
        
        .tenant-name {
            font-size: 1.2rem;
            margin-top: 0.5rem;
            opacity: 0.95;
        }
        
        .room-type {
            margin-top: 0.5rem;
            padding: 0.25rem 0.75rem;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            display: inline-block;
            font-size: 0.85rem;
        }
        
        .section-title {
            font-size: 1rem;
            color: #94a3b8;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .menu-item {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 1.25rem;
            text-decoration: none;
            color: #e2e8f0;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 0.75rem;
        }
        
        .menu-item:hover, .menu-item:active {
            transform: translateY(-2px);
            background: rgba(59, 130, 246, 0.2);
            border-color: rgba(59, 130, 246, 0.5);
        }
        
        .menu-icon {
            font-size: 2rem;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(59, 130, 246, 0.2);
            border-radius: 12px;
        }
        
        .menu-icon svg {
            width: 28px;
            height: 28px;
            stroke: #3b82f6;
            stroke-width: 2;
            fill: none;
            transition: all 0.3s ease;
        }
        
        .menu-item:hover .menu-icon svg {
            transform: scale(1.1);
            stroke: #60a5fa;
        }
        
        .menu-icon.green { background: rgba(34, 197, 94, 0.2); }
        .menu-icon.green svg { stroke: #22c55e; }
        .menu-icon.orange { background: rgba(249, 115, 22, 0.2); }
        .menu-icon.orange svg { stroke: #f97316; }
        .menu-icon.red { background: rgba(239, 68, 68, 0.2); }
        .menu-icon.red svg { stroke: #ef4444; }
        .menu-icon.purple { background: rgba(168, 85, 247, 0.2); }
        .menu-icon.purple svg { stroke: #a855f7; }
        .menu-icon.teal { background: rgba(20, 184, 166, 0.2); }
        .menu-icon.teal svg { stroke: #14b8a6; }
        .menu-icon.yellow { background: rgba(234, 179, 8, 0.2); }
        .menu-icon.yellow svg { stroke: #eab308; }
        
        .nav-icon svg {
            width: 22px;
            height: 22px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }
        .nav-badge {
            position: absolute;
            top: -2px;
            right: -6px;
            background: #ef4444;
            color: white;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 12px;
            min-width: 20px;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .nav-item {
            position: relative;
        }
        .section-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .section-icon svg {
            width: 18px;
            height: 18px;
            stroke: #94a3b8;
            stroke-width: 2;
            fill: none;
        }
        
        .alert-icon svg {
            width: 32px;
            height: 32px;
            stroke: white;
            stroke-width: 2;
            fill: none;
        }
        
        .menu-label {
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .info-card {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 1rem;
        }
        
        .info-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .info-card-title {
            font-size: 1rem;
            color: #f8fafc;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: #94a3b8;
            font-size: 0.85rem;
        }
        
        .info-value {
            color: #f8fafc;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .news-item {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.75rem;
        }
        
        .news-date {
            font-size: 0.75rem;
            color: #64748b;
            margin-bottom: 0.25rem;
        }
        
        .news-title {
            font-size: 0.95rem;
            color: #f8fafc;
            font-weight: 500;
        }
        
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(15, 23, 42, 0.98);
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 0.5rem;
            backdrop-filter: blur(10px);
        }
        
        .bottom-nav-content {
            max-width: 600px;
            margin: 0 auto;
            display: flex;
            justify-content: space-around;
        }
        
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: #64748b;
            padding: 0.5rem 1rem;
            font-size: 0.7rem;
            transition: color 0.2s;
        }
        
        .nav-item.active, .nav-item:hover {
            color: #3b82f6;
        }
        
        .nav-icon {
            font-size: 1.3rem;
            margin-bottom: 0.25rem;
        }
        
        .alert-unpaid {
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .alert-icon {
            font-size: 2rem;
        }
        
        .alert-content h3 {
            font-size: 0.95rem;
            margin-bottom: 0.25rem;
        }
        
        .alert-content p {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .alert-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 10px;
            padding: 8px 14px;
            background: #ef4444;
            color: #fff;
            border-radius: 10px;
            font-size: 0.8rem;
            text-decoration: none;
            font-weight: 600;
        }

        .alert-btn:hover {
            background: #dc2626;
        }
        
        .view-all-link {
            display: block;
            text-align: center;
            color: #3b82f6;
            text-decoration: none;
            font-size: 0.85rem;
            padding: 0.75rem;
            margin-top: 0.5rem;
        }

        .cancelled-banner {
            background: linear-gradient(135deg, #7f1d1d 0%, #450a0a 100%);
            border: 1px solid rgba(239, 68, 68, 0.4);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .cancelled-banner svg {
            flex-shrink: 0;
            width: 28px;
            height: 28px;
            stroke: #f87171;
            stroke-width: 2;
            fill: none;
        }
        .cancelled-banner-text h3 {
            font-size: 0.95rem;
            color: #fca5a5;
            margin-bottom: 0.2rem;
        }
        .cancelled-banner-text p {
            font-size: 0.8rem;
            color: #fca5a5;
            opacity: 0.8;
        }

        .refund-card {
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 1.25rem;
        }
        .refund-card.pending {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15) 0%, rgba(180, 83, 9, 0.15) 100%);
            border: 1px solid rgba(245, 158, 11, 0.4);
        }
        .refund-card.transferred {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(5, 150, 105, 0.15) 100%);
            border: 1px solid rgba(16, 185, 129, 0.4);
        }
        .refund-card.no-record {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(255,255,255,0.08);
        }
        .refund-card-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .refund-card-title svg {
            width: 20px;
            height: 20px;
            stroke-width: 2;
            fill: none;
        }
        .refund-card.pending .refund-card-title { color: #fcd34d; }
        .refund-card.pending .refund-card-title svg { stroke: #fcd34d; }
        .refund-card.transferred .refund-card-title { color: #6ee7b7; }
        .refund-card.transferred .refund-card-title svg { stroke: #6ee7b7; }
        .refund-card.no-record .refund-card-title { color: #94a3b8; }
        .refund-card.no-record .refund-card-title svg { stroke: #94a3b8; }
        .refund-view-link {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-top: 0.75rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            text-decoration: none;
        }
        .refund-card.pending .refund-view-link { background: rgba(245,158,11,0.2); color: #fcd34d; }
        .refund-card.transferred .refund-view-link { background: rgba(16,185,129,0.2); color: #6ee7b7; }
        .refund-card.no-record .refund-view-link { background: rgba(59,130,246,0.2); color: #93c5fd; }
        .menu-icon.teal-dark { background: rgba(6,182,212,0.2); }
        .menu-icon.teal-dark svg { stroke: #22d3ee; }

        /* Sign Contract Banner */
        @keyframes signPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(250, 204, 21, 0.45); }
            50% { box-shadow: 0 0 0 10px rgba(250, 204, 21, 0); }
        }
        .sign-alert {
            background: linear-gradient(135deg, #78350f 0%, #451a03 100%);
            border: 1.5px solid rgba(250, 204, 21, 0.55);
            border-radius: 16px;
            padding: 1.1rem 1.25rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: signPulse 2s infinite;
        }
        .sign-alert-icon {
            flex-shrink: 0;
            width: 48px;
            height: 48px;
            background: rgba(250, 204, 21, 0.18);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sign-alert-icon svg {
            width: 26px;
            height: 26px;
            stroke: #fde047;
            stroke-width: 2;
            fill: none;
        }
        .sign-alert-body { flex: 1; }
        .sign-alert-body h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #fef08a;
            margin-bottom: 0.3rem;
        }
        .sign-alert-body p {
            font-size: 0.78rem;
            color: #fde68a;
            opacity: 0.9;
            line-height: 1.4;
        }
        .sign-alert-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 0.6rem;
            padding: 8px 16px;
            background: linear-gradient(135deg, #eab308, #ca8a04);
            color: #1c1917;
            border-radius: 10px;
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: none;
            transition: opacity 0.2s;
        }
        .sign-alert-btn:hover { opacity: 0.85; }
        .sign-alert-btn svg {
            width: 15px;
            height: 15px;
            stroke: #1c1917;
            stroke-width: 2.5;
            fill: none;
        }
    </style>
    <?php if (($publicTheme ?? '') === 'light'): ?>
    <link rel="stylesheet" href="tenant-light-theme.css">
    <?php endif; ?>
</head>
<body class="<?= ($publicTheme ?? '') === 'light' ? 'light-theme' : '' ?>">
    <header class="header">
        <div class="header-content">
            <img src="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="Logo" class="logo">
            <div class="header-info">
                <h1><?php echo htmlspecialchars($siteName); ?></h1>
                <p>Tenant Portal</p>
            </div>
            <?php if (!empty($_SESSION['tenant_logged_in'])): ?>
            <div style="margin-left: auto; display: flex; gap: 0.5rem;">
                <a href="../tenant_logout.php" style="padding: 0.5rem 1rem; background: rgba(239, 68, 68, 0.2); color: #f87171; border-radius: 8px; text-decoration: none; font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    ออกจากระบบ
                </a>
            </div>
            <?php endif; ?>
        </div>
    </header>
    
    <div class="container">
        <!-- Room Card -->
        <div class="room-card">
            <div class="room-number">
                <?php echo htmlspecialchars($contract['room_number']); ?>
                <span>ห้องพัก</span>
            </div>
            <div class="tenant-name"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;vertical-align:middle;margin-right:4px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> <?php echo htmlspecialchars($contract['tnt_name']); ?></div>
            <div class="room-type"><?php echo htmlspecialchars($contract['type_name'] ?? 'ไม่ระบุประเภท'); ?> - <?php echo number_format($contract['type_price'] ?? 0); ?> บาท/เดือน</div>
            <a href="../Reports/print_contract.php?ctr_id=<?php echo (int)$contract['ctr_id']; ?>&from_tenant=1" target="_blank" style="display: inline-flex; align-items: center; margin-top: 1rem; padding: 0.6rem 1.25rem; background: #ffffff; border: 1px solid rgba(255,255,255,0.5); border-radius: 50px; color: #3b82f6; text-decoration: none; font-size: 0.95rem; font-weight: 600; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15); transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(0, 0, 0, 0.2)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(0, 0, 0, 0.15)';">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;vertical-align:middle;margin-right:6px;">
                    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                    <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
                    <line x1="12" y1="11" x2="12" y2="17"/>
                    <line x1="9" y1="14" x2="15" y2="14"/>
                </svg>
                เปิดดูลายละเอียดใบสัญญา
            </a>
        </div>
        
        <!-- Sign Contract Banner (Only show if at least in step 3: create contract) -->
        <?php if (!$tenantSigned && ($contract['ctr_status'] ?? '0') !== '1' && (int)($contract['step_3_confirmed'] ?? 0) === 1): ?>
        <div class="sign-alert">
            <div class="sign-alert-icon">
                <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 20h9"/>
                    <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                </svg>
            </div>
            <div class="sign-alert-body">
                <h3>⚠️ ยังไม่ได้เซ็นสัญญาเช่า!</h3>
                <p>กรุณาเซ็นสัญญาให้เรียบร้อยก่อนเข้าพักอาศัย เพื่อยืนยันข้อตกลงระหว่างผู้เช่าและผู้ให้เช่า</p>
                <a class="sign-alert-btn" href="../Reports/print_contract.php?ctr_id=<?php echo (int)$contract['ctr_id']; ?>&from_tenant=1" target="_blank">
                    <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                    เซ็นสัญญาเลย
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Alert for unpaid bill -->
        <?php if ($firstUnpaidExpense): ?>
        <div class="alert-unpaid">
            <div class="alert-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
            <div class="alert-content">
                <h3>มีบิลค้างชำระ</h3>
                <p>ยอดคงเหลือ <?php echo number_format((int)($firstUnpaidExpense['remaining_amount'] ?? $firstUnpaidExpense['exp_total'] ?? 0)); ?> บาท</p>
                <a class="alert-btn" href="payment.php?token=<?php echo urlencode($token); ?>&exp_id=<?php echo (int)$firstUnpaidExpense['exp_id']; ?>">
                    ชำระค่าเช่าเดือนแรก
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (($contract['ctr_status'] ?? '0') === '1'): ?>
        <!-- Cancelled contract banner -->
        <div class="cancelled-banner">
            <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <div class="cancelled-banner-text">
                <h3>สัญญาเช่าสิ้นสุดแล้ว</h3>
                <p>ระบบจำกัดการเข้าถึงบางฟีเจอร์ กรุณาตรวจสอบสถานะคืนเงินมัดจำด้านล่าง</p>
            </div>
        </div>

        <!-- Deposit Refund Status Card for cancelled tenants -->
        <?php
        $refundCardClass = 'no-record';
        $refundTitle = 'สถานะคืนเงินมัดจำ';
        $refundIcon = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
        if ($depositRefund) {
            $refundCardClass = ($depositRefund['refund_status'] ?? '0') === '1' ? 'transferred' : 'pending';
        }
        ?>
        <div class="section-title">
            <span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>
            เงินมัดจำ
        </div>
        <div class="refund-card <?php echo $refundCardClass; ?>">
            <?php if (!$depositRefund): ?>
            <div class="refund-card-title">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                รอดำเนินการ
            </div>
            <div class="info-row"><span class="info-label">เงินมัดจำ</span><span class="info-value"><?php echo number_format((float)($contract['ctr_deposit'] ?? 0)); ?> บาท</span></div>
            <div class="info-row" style="border:none"><span class="info-label">สถานะ</span><span class="info-value" style="color:#f59e0b">รอแอดมินดำเนินการคืนเงิน</span></div>
            <?php elseif (($depositRefund['refund_status'] ?? '0') === '1'): ?>
            <div class="refund-card-title">
                <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                โอนเงินคืนแล้ว
            </div>
            <div class="info-row"><span class="info-label">เงินมัดจำเดิม</span><span class="info-value"><?php echo number_format((float)($depositRefund['deposit_amount'] ?? 0)); ?> บาท</span></div>
            <?php if ((float)($depositRefund['deduction_amount'] ?? 0) > 0): ?>
            <div class="info-row"><span class="info-label">หักค่าเสียหาย</span><span class="info-value" style="color:#f87171">-<?php echo number_format((float)$depositRefund['deduction_amount']); ?> บาท</span></div>
            <?php endif; ?>
            <div class="info-row"><span class="info-label">ยอดคืนสุทธิ</span><span class="info-value" style="color:#6ee7b7;font-weight:600"><?php echo number_format((float)($depositRefund['refund_amount'] ?? 0)); ?> บาท</span></div>
            <?php if (!empty($depositRefund['refund_date'])): ?>
            <div class="info-row" style="border:none"><span class="info-label">วันที่โอน</span><span class="info-value"><?php echo thaiDate($depositRefund['refund_date']); ?></span></div>
            <?php endif; ?>
            <?php else: ?>
            <div class="refund-card-title">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                รอโอนเงินคืน
            </div>
            <div class="info-row"><span class="info-label">เงินมัดจำเดิม</span><span class="info-value"><?php echo number_format((float)($depositRefund['deposit_amount'] ?? 0)); ?> บาท</span></div>
            <?php if ((float)($depositRefund['deduction_amount'] ?? 0) > 0): ?>
            <div class="info-row"><span class="info-label">หักค่าเสียหาย</span><span class="info-value" style="color:#f87171">-<?php echo number_format((float)$depositRefund['deduction_amount']); ?> บาท</span></div>
            <?php endif; ?>
            <div class="info-row"><span class="info-label">ยอดคืนสุทธิ</span><span class="info-value"><?php echo number_format((float)($depositRefund['refund_amount'] ?? 0)); ?> บาท</span></div>
            <div class="info-row" style="border:none"><span class="info-label">สถานะ</span><span class="info-value" style="color:#fcd34d">รอโอนเงิน</span></div>
            <?php endif; ?>
            <a href="termination.php?token=<?php echo urlencode($token); ?>" class="refund-view-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                ดูรายละเอียด
            </a>
        </div>
        <?php endif; ?>

        <!-- Menu Grid -->
        <div class="section-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></span> บริการ</div>
        <div class="menu-grid">
            <a href="profile.php?token=<?php echo urlencode($token); ?>" class="menu-item">
                <div class="menu-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                <div class="menu-label">ข้อมูลส่วนตัว</div>
            </a>
            <a href="checkin.php?token=<?php echo urlencode($token); ?>" class="menu-item">
                <div class="menu-icon teal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                <div class="menu-label">ข้อมูลเช็คอิน</div>
            </a>
            <a href="repair.php?token=<?php echo urlencode($token); ?>" class="menu-item">
                <div class="menu-icon orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></div>
                <div class="menu-label">แจ้งซ่อม</div>
            </a>
            <a href="payment.php?token=<?php echo urlencode($token); ?>" class="menu-item">
                <div class="menu-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
                <div class="menu-label">แจ้งชำระเงิน</div>
            </a>
            <?php if (($contract['ctr_status'] ?? '0') !== '1' && !$terminationAllowed): ?>
            <a href="#" onclick="alert('<?= htmlspecialchars($terminationReason, ENT_QUOTES, 'UTF-8') ?>'); return false;" class="menu-item" style="opacity: 0.5;">
            <?php else: ?>
            <a href="termination.php?token=<?php echo urlencode($token); ?>" class="menu-item">
            <?php endif; ?>
                <?php if (($contract['ctr_status'] ?? '0') === '1'): ?>
                <div class="menu-icon teal-dark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
                <div class="menu-label">สถานะคืนเงินมัดจำ</div>
                <?php else: ?>
                <div class="menu-icon red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/></svg></div>
                <div class="menu-label">แจ้งยกเลิกสัญญา</div>
                <?php endif; ?>
            </a>
        </div>
        
        <!-- Reports Menu -->
        <div class="section-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span> รายงาน</div>
        <div class="menu-grid">
            <a href="report_room.php?token=<?php echo urlencode($token); ?>" class="menu-item">
                <div class="menu-icon purple"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
                <div class="menu-label">ข้อมูลห้องพัก</div>
            </a>
            <a href="report_news.php?token=<?php echo urlencode($token); ?>" class="menu-item">
                <div class="menu-icon teal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><line x1="10" y1="6" x2="18" y2="6"/><line x1="10" y1="10" x2="18" y2="10"/><line x1="10" y1="14" x2="18" y2="14"/></svg></div>
                <div class="menu-label">ข่าวประชาสัมพันธ์</div>
            </a>
            <a href="report_bills.php?token=<?php echo urlencode($token); ?>&_ts=<?php echo time(); ?>" class="menu-item">
                <div class="menu-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1z"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="12" y2="14"/></svg></div>
                <div class="menu-label">บิลค่าใช้จ่าย</div>
            </a>
            <a href="report_contract.php?token=<?php echo urlencode($token); ?>" class="menu-item">
                <div class="menu-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/></svg></div>
                <div class="menu-label">สัญญาเช่า</div>
            </a>
            <a href="report_utility.php?token=<?php echo urlencode($token); ?>" class="menu-item">
                <div class="menu-icon yellow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 0 1 8.91 14"/></svg></div>
                <div class="menu-label">ค่าน้ำ-ค่าไฟ</div>
            </a>
        </div>
        
        <!-- Latest Repair Status -->
        <?php if ($latestRepair): ?>
        <div class="section-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></span> สถานะแจ้งซ่อมล่าสุด</div>
        <div class="info-card">
            <div class="info-card-header">
                <div class="info-card-title">
                    <?php echo htmlspecialchars($latestRepair['repair_desc']); ?>
                </div>
                <span class="status-badge" style="background: <?php echo $repairStatusMap[$latestRepair['repair_status'] ?? '0']['color']; ?>">
                    <?php echo $repairStatusMap[$latestRepair['repair_status'] ?? '0']['label']; ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">วันที่แจ้ง</span>
                <span class="info-value"><?php echo !empty($latestRepair['repair_date']) ? thaiDate($latestRepair['repair_date']) : '-'; ?></span>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- OpenWeatherMap Widget -->
        <?php if (!empty($settings['openweathermap_api_key']) && !empty($settings['openweathermap_city'])): ?>
        <div id="weather-wrapper" style="display: none;">
            <div class="section-title" style="display:flex; justify-content:space-between; align-items:center;">
                <div><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;vertical-align:middle;margin-top:-2px;"><circle cx="12" cy="12" r="5" /><path d="M12 1v2m0 18v2M4.22 4.22l1.42 1.42m12.72 12.72l1.42 1.42M1 12h2m18 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" /></svg></span> สภาพอากาศวันนี้</div>
                <button type="button" onclick="closeWeatherWidget()" style="background:none; border:none; cursor:pointer; color:#94a3b8; display:flex; align-items:center; padding:4px; border-radius:50%; transition:background 0.2s;" onmouseover="this.style.background='rgba(0,0,0,0.05)'" onmouseout="this.style.background='none'" title="ปิดแจ้งเตือน"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>
            </div>
            <div class="info-card" id="weather-widget">
                <div style="display:flex; justify-content:center; align-items:center; padding:20px;">
                    <span style="color:#64748b; font-size:0.9rem;">⏳ กำลังเรียกข้อมูลสภาพอากาศ...</span>
                </div>
            </div>
        </div>
        <script>
        function closeWeatherWidget() {
            const wrapper = document.getElementById('weather-wrapper');
            if(wrapper) wrapper.style.display = 'none';
            localStorage.setItem('hide_weather_date', new Date().toDateString());
        }

        document.addEventListener('DOMContentLoaded', function() {
            const hideDate = localStorage.getItem('hide_weather_date');
            const today = new Date().toDateString();
            if (hideDate === today) {
                return; // Hide widget today
            }
            const wrapper = document.getElementById('weather-wrapper');
            if(wrapper) wrapper.style.display = 'block';

            const apiKey = <?php echo json_encode($settings['openweathermap_api_key']); ?>;
            const city = <?php echo json_encode($settings['openweathermap_city']); ?>;
            const url = `https://api.openweathermap.org/data/2.5/weather?q=${encodeURIComponent(city)}&appid=${encodeURIComponent(apiKey)}&units=metric&lang=th`;
            
            fetch(url)
            .then(res => res.json())
            .then(data => {
                if(data.cod === 200) {
                    const temp = Math.round(data.main.temp);
                    const desc = data.weather[0].description;
                    const icon = data.weather[0].icon;
                    
                    let laundryTip = '';
                    const weatherMain = data.weather[0].main.toLowerCase();
                    const isNight = icon.includes('n');
                    
                    if(weatherMain.includes('rain') || weatherMain.includes('drizzle') || weatherMain.includes('thunderstorm')) {
                        laundryTip = '<div style="margin-top:10px; font-size:0.85rem; color:#ef4444; background:rgba(239,68,68,0.1); padding:8px 12px; border-radius:8px; display:flex; gap:6px;"><span>🌧️</span> <span>โอกาสฝนตก แนะนำให้ตากผ้าในร่ม</span></div>';
                    } else if(data.main.humidity > 80) {
                        laundryTip = '<div style="margin-top:10px; font-size:0.85rem; color:#f59e0b; background:rgba(245,158,11,0.1); padding:8px 12px; border-radius:8px; display:flex; gap:6px;"><span>☁️</span> <span>อากาศค่อนข้างชื้น ผ้าอาจแห้งช้าซักนิด</span></div>';
                    } else if(isNight) {
                        laundryTip = '<div style="margin-top:10px; font-size:0.85rem; color:#6366f1; background:rgba(99,102,241,0.1); padding:8px 12px; border-radius:8px; display:flex; gap:6px;"><span>🌙</span> <span>เวลากลางคืน พักผ่อนให้สบายพรุ่งนี้ค่อยว่ากัน</span></div>';
                    } else {
                        laundryTip = '<div style="margin-top:10px; font-size:0.85rem; color:#10b981; background:rgba(16,185,129,0.1); padding:8px 12px; border-radius:8px; display:flex; gap:6px;"><span>☀️</span> <span>ท้องฟ้าโปร่ง เหมาะกับการซักผ้าตากแดด</span></div>';
                    }
                    
                    document.getElementById('weather-widget').innerHTML = `
                        <div style="display:flex; align-items:center; gap:16px;">
                            <img src="https://openweathermap.org/img/wn/${icon}@2x.png" style="width:64px; height:64px; filter:drop-shadow(0 4px 6px rgba(0,0,0,0.1));" alt="${desc}">
                            <div style="flex:1;">
                                <div style="font-size:1.8rem; font-weight:700; color:#1e293b; line-height:1;">${temp}°C</div>
                                <div style="font-size:0.95rem; color:#64748b; margin-top:4px; text-transform:capitalize;">${desc} (${data.name})</div>
                            </div>
                        </div>
                        ${laundryTip}
                        ${
                            <?php if (empty($contractData['line_user_id']) || empty($contractData['is_weather_alert_enabled'])): ?>
                            <?php if (!empty($settings['line_login_channel_id'])): ?>
                            `<div style="margin-top:12px; font-size:0.85rem; color:#475569; border-top:1px solid #f1f5f9; padding-top:12px; text-align:center;">
                                <a href="../line_login.php?action=link&tenant_id=<?php echo urlencode((string)$contractData['tnt_id']); ?>" style="display:inline-flex; align-items:center; justify-content:center; text-decoration:none; color:#fff; font-weight:bold; font-size:1.0rem; background-color:#06c755; border-radius:12px; padding:10px 20px; box-shadow:0 4px 10px rgba(6,199,85,0.3); transition:all 0.2s;">
                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" style="margin-right:8px;"><path d="M22.5 10.4c0-4.3-4.7-7.8-10.5-7.8S1.5 6.1 1.5 10.4c0 3.8 3.7 7 8.5 7.7.4.1.9.3 1 .6l.3 1.8c0 .2.2.3.4.2 1.6-1.1 5.9-4 8.2-6.2 1.5-1.3 2.6-2.7 2.6-4.1zM9.5 12.3c0 .3-.2.5-.5.5H6.2c-.3 0-.5-.2-.5-.5V8.5c0-.3.2-.5.5-.5h2.8c.3 0 .5.2.5.5s-.2.5-.5.5H7.2v1h1.8c.3 0 .5.2.5.5s-.2.5-.5.5H7.2v1h1.8c.3 0 .5.2.5.5zM13.6 12.3c0 .3-.2.5-.5.5h-1c-.3 0-.5-.2-.5-.5V8.5c0-.3.2-.5.5-.5h1c.3 0 .5.2.5.5v3.8zM17.4 12.3c0 .3-.2.5-.5.5h-2.1c-.3 0-.5-.2-.5-.5V8.5c0-.3.2-.5.5-.5h.6c.3 0 .5.2.5.5v2.8h1.5c.3 0 .5.2.5.5z"/></svg>
                                    ผูกบัญชีด้วย LINE ทันที
                                </a>
                                <br><span style="font-size:0.75rem; color:#94a3b8; display:inline-block; margin-top:8px;">ผูกด้วยคลิกเดียว เพื่อรับบิลและพยากรณ์อากาศฟรี!</span>
                            </div>`
                            <?php else: ?>
                            `<div style="margin-top:12px; font-size:0.85rem; color:#475569; border-top:1px solid #f1f5f9; padding-top:12px; text-align:center;">
                                <?php if(!empty($settings['line_qr_code_image']) && file_exists(__DIR__ . '/../Public/Assets/Images/' . $settings['line_qr_code_image'])): ?>
                                <div style="margin-bottom:10px;">
                                    <img src="../Public/Assets/Images/<?php echo htmlspecialchars($settings['line_qr_code_image']); ?>" alt="LINE QR Code" style="width:120px; height:120px; border-radius:8px; border:1px solid #e2e8f0; padding:4px; background:#fff;">
                                </div>
                                <?php elseif(!empty($settings['line_add_friend_url'])): ?>
                                <div style="margin-bottom:10px;">
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=<?php echo urlencode($settings['line_add_friend_url']); ?>" alt="LINE QR Code" style="width:120px; height:120px; border-radius:8px; border:1px solid #e2e8f0; padding:4px; background:#fff;">
                                </div>
                                <?php endif; ?>
                                <a href="<?php echo htmlspecialchars($settings['line_add_friend_url'] ?? '#', ENT_QUOTES, 'UTF-8'); ?>" target="_blank" style="display:inline-block; text-decoration:none; color:#06c755; font-weight:600; margin-bottom:8px; border:1px solid #06c755; padding:6px 12px; border-radius:24px; background-color: #f6fff9;">
                                    <span style="font-size:1.1rem; vertical-align:middle; margin-right:4px;">💬</span> <span style="vertical-align:middle;">เพิ่มเพื่อน LINE หอพักคลิกที่นี่</span>
                                </a>
                                <br>หรือสแกน QR Code ด้านบน จากนั้นพิมพ์ใน LINE: <br><code style="background:#f1f5f9; padding:2px 6px; border-radius:4px; color:#1d4ed8; font-weight:600;">ลงทะเบียน <?php echo htmlspecialchars($contractData['tnt_phone'] ?? 'เบอร์โทรศัพท์ของคุณ', ENT_QUOTES, 'UTF-8'); ?></code><br><span style="font-size:0.75rem; color:#94a3b8; display:inline-block; margin-top:4px;">เพื่อรับการแจ้งเตือนบิลและพยากรณ์อากาศฟรี!</span>
                            </div>`
                            <?php endif; ?>
                            <?php else: ?>
                            `<div style="margin-top:12px; font-size:0.8rem; color:#10b981; border-top:1px solid rgba(16,185,129,0.2); padding-top:12px; text-align:center;">
                                ✓ แจ้งเตือนทาง LINE ทำงานอยู่<br>
                                <a href="?<?php echo !empty($token) ? 'token=' . urlencode($token) . '&' : ''; ?>action=unlink_line" onclick="return confirm('คุณต้องการยกเลิกการแจ้งเตือนและผูกบัญชี LINE ใช่หรือไม่?');" style="display:inline-block; margin-top:8px; padding:4px 12px; border-radius:16px; background:#fef2f2; color:#ef4444; border:1px solid rgba(239,68,68,0.3); text-decoration:none; font-weight:600;">
                                    ❌ ยกเลิกผูกบัญชี LINE
                                </a>
                            </div>`
                            <?php endif; ?>
                        }
                    `;
                } else {
                    console.error("OpenWeatherMap API Error:", data);
                    document.getElementById('weather-widget').innerHTML = `<div style="color:#ef4444; text-align:center; font-size:0.9rem; padding:10px;">❌ ไม่สามารถตรวจสอบอากาศได้<br><small style="color:#94a3b8;">${data.message || 'รหัสข้อผิดพลาด: ' + data.cod}</small></div>`;
                }
            })
            .catch(err => {
                document.getElementById('weather-widget').innerHTML = '<div style="color:#ef4444; text-align:center; font-size:0.9rem; padding:10px;">❌ ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์อากาศได้</div>';
            });
        });
        </script>
        <?php endif; ?>

        <!-- Google Maps Content -->
        <?php if (!empty($settings['google_maps_embed'])): ?>
        <div class="section-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></span> แผนที่หอพัก</div>
        <div class="info-card" style="padding:0; overflow:hidden; border-radius:16px; margin-bottom:20px;">
            <iframe src="<?php echo htmlspecialchars($settings['google_maps_embed']); ?>" width="100%" height="250" style="border:0; margin-bottom:-5px;" allowfullscreen="" loading="lazy"></iframe>
        </div>
        <?php endif; ?>

        <!-- Latest News -->
        <?php if (!empty($latestNews)): ?>
        <div class="section-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><line x1="10" y1="6" x2="18" y2="6"/><line x1="10" y1="10" x2="18" y2="10"/><line x1="10" y1="14" x2="18" y2="14"/></svg></span> ข่าวล่าสุด</div>
        <?php foreach ($latestNews as $news): ?>
        <div class="news-item">
            <div class="news-date"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:12px;height:12px;vertical-align:middle;margin-right:4px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> <?php echo !empty($news['news_date']) ? thaiDate($news['news_date']) : '-'; ?></div>
            <div class="news-title"><?php echo htmlspecialchars($news['news_title'] ?? '-'); ?></div>
        </div>
        <?php endforeach; ?>
        <a href="report_news.php?token=<?php echo urlencode($token); ?>" class="view-all-link">ดูข่าวทั้งหมด →</a>
        <?php endif; ?>
    </div>
    
    <!-- Bottom Navigation -->
    <?php
    // นับรายการแจ้งซ่อมที่ยังไม่เสร็จ
    $repairCount = 0;
    try {
        $repairStmt = $pdo->prepare("
            SELECT COUNT(*) as cnt FROM repair
            WHERE ctr_id IN (SELECT ctr_id FROM contract WHERE tnt_id = ?)
            AND repair_status = '0'
        ");
        $repairStmt->execute([$contract['tnt_id']]);
        $repairCount = (int)($repairStmt->fetchColumn() ?? 0);
    } catch (Exception $e) { error_log("Exception in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }
    
    // นับรายการบิลที่ยังไม่ชำระ
    $homeBadgeCount = 0;
    try {
        $homeBadgeStmt = $pdo->prepare("
            SELECT 1 
            FROM contract c
            LEFT JOIN signature_logs sl ON c.ctr_id = sl.contract_id AND sl.signer_type = 'tenant'
            WHERE c.ctr_id = ? AND c.ctr_status != '1' AND sl.id IS NULL
              AND (
                  SELECT step_3_confirmed 
                  FROM tenant_workflow 
                  WHERE tnt_id = c.tnt_id 
                  ORDER BY id DESC LIMIT 1
              ) = 1
            LIMIT 1
        ");
        $homeBadgeStmt->execute([$contract['ctr_id'] ?? 0]);
        if ($homeBadgeStmt->fetchColumn()) {
            $homeBadgeCount = 1;
        }
    } catch (Exception $e) { error_log("Exception calculating home badge count in " . __FILE__ . ": " . $e->getMessage()); }

    $billCount = 0;
    try {
        $billStmt = $pdo->prepare("
            SELECT COUNT(*) FROM expense e
            INNER JOIN (
                SELECT MAX(exp_id) AS exp_id FROM expense WHERE ctr_id = ? GROUP BY exp_month
            ) latest ON e.exp_id = latest.exp_id
            WHERE e.ctr_id = ?
            AND DATE_FORMAT(e.exp_month, '%Y-%m') >= DATE_FORMAT(?, '%Y-%m')
            AND DATE_FORMAT(e.exp_month, '%Y-%m') <= DATE_FORMAT(CURDATE(), '%Y-%m')
            AND (
                e.exp_month = (SELECT MIN(e2.exp_month) FROM expense e2 WHERE e2.ctr_id = e.ctr_id)
                OR EXISTS (
                    SELECT 1
                    FROM utility u
                    WHERE u.ctr_id = e.ctr_id
                        AND YEAR(u.utl_date) = YEAR(e.exp_month)
                        AND MONTH(u.utl_date) = MONTH(e.exp_month)
                        AND u.utl_water_end IS NOT NULL
                        AND u.utl_elec_end IS NOT NULL
                )
            )
            AND (
                GREATEST(0, (COALESCE(e.room_price, 0) + COALESCE(e.exp_elec_chg, 0) + COALESCE(e.exp_water, 0)) - COALESCE((
                    SELECT SUM(p.pay_amount) FROM payment p
                    WHERE p.exp_id = e.exp_id AND p.pay_status IN ('0','1')
                    AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ'
                ), 0))
                +
                GREATEST(0, (e.exp_total - (COALESCE(e.room_price, 0) + COALESCE(e.exp_elec_chg, 0) + COALESCE(e.exp_water, 0))) - COALESCE((
                    SELECT SUM(p.pay_amount) FROM payment p
                    WHERE p.exp_id = e.exp_id AND p.pay_status IN ('0','1')
                    AND TRIM(COALESCE(p.pay_remark, '')) = 'มัดจำ'
                ), 0))
            ) > 0
        ");
        $billStmt->execute([$contract['ctr_id'], $contract['ctr_id'], $contract['ctr_start'] ?? date('Y-m-d')]);
        $billCount = (int)($billStmt->fetchColumn() ?? 0);
    } catch (Exception $e) { error_log("Exception calculating bill count in " . __FILE__ . ": " . $e->getMessage()); }
    ?>
    
    <nav class="bottom-nav">
        <div class="bottom-nav-content">
            <a href="index.php?token=<?php echo urlencode($token); ?>" class="nav-item active">
                <div class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
                                หน้าหลัก<?php if ($homeBadgeCount > 0): ?><span class="nav-badge">1</span><?php endif; ?>
            </a>
            <a href="report_bills.php?token=<?php echo urlencode($token); ?>&_ts=<?php echo time(); ?>" class="nav-item">
                <div class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1z"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="12" y2="14"/></svg></div>
                บิล<?php if ($billCount > 0): ?><span class="nav-badge"><?php echo $billCount > 99 ? '99+' : $billCount; ?></span><?php endif; ?>
            </a>
            <a href="repair.php?token=<?php echo urlencode($token); ?>" class="nav-item">
                <div class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></div>
                แจ้งซ่อม<?php if ($repairCount > 0): ?><span class="nav-badge"><?php echo $repairCount > 99 ? '99+' : $repairCount; ?></span><?php endif; ?></a>
            <a href="profile.php?token=<?php echo urlencode($token); ?>" class="nav-item">
                <div class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                โปรไฟล์
            </a>
        </div>
    </nav>
    
    <?php include_once __DIR__ . '/../includes/apple_alert.php'; ?>
</body>
</html>
