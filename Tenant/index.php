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

// รับ token จาก URL
$token = $_GET['token'] ?? '';
$contractData = null;

// ก่อนอื่นตรวจสอบ token (QR Code / Token)
if (!empty($token)) {
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   t.tnt_id, t.tnt_name, t.tnt_phone, t.tnt_address, t.tnt_education, t.tnt_faculty, t.tnt_year, t.tnt_vehicle, t.tnt_parent, t.tnt_parentsphone, t.tnt_age,
                   r.room_id, r.room_number, r.room_image,
                   rt.type_name, rt.type_price
            FROM contract c
            JOIN tenant t ON c.tnt_id = t.tnt_id
            JOIN room r ON c.room_id = r.room_id
            LEFT JOIN roomtype rt ON r.type_id = rt.type_id
            WHERE c.access_token = ? AND c.ctr_status IN ('0', '2')
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
                       t.tnt_id, t.tnt_name, t.tnt_phone, t.tnt_address, t.tnt_education, t.tnt_faculty, t.tnt_year, t.tnt_vehicle, t.tnt_parent, t.tnt_parentsphone, t.tnt_age,
                       r.room_id, r.room_number, r.room_image,
                       rt.type_name, rt.type_price
                FROM contract c
                JOIN tenant t ON c.tnt_id = t.tnt_id
                JOIN room r ON c.room_id = r.room_id
                LEFT JOIN roomtype rt ON r.type_id = rt.type_id
                WHERE c.tnt_id = ? AND c.ctr_status IN ('0', '1', '2')
                ORDER BY c.ctr_id DESC
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
    if (!empty($token)) {
        http_response_code(403);
        die('<div style="text-align:center;padding:50px;font-family:sans-serif;"><h1>⚠️ Token ไม่ถูกต้องหรือหมดอายุ</h1><p>กรุณาติดต่อผู้ดูแลหอพัก</p></div>');
    } else {
        http_response_code(403);
        die('<div style="text-align:center;padding:50px;font-family:sans-serif;"><h1>⚠️ ไม่พบ Token</h1><p>กรุณาสแกน QR Code หรือเข้าสู่ระบบก่อน</p></div>');
    }
}

$contract = $contractData;

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
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename', 'public_theme')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
        if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
        if ($row['setting_key'] === 'public_theme') $publicTheme = $row['setting_value'];
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
    $unpaidStmt = $pdo->prepare("\n+        SELECT
            e.*,
            COALESCE(ps.submitted_amount, 0) AS submitted_amount,
            (e.exp_total - COALESCE(ps.submitted_amount, 0)) AS remaining_amount
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
            SELECT exp_id, COALESCE(SUM(pay_amount), 0) AS submitted_amount
            FROM payment
            WHERE pay_status IN ('0', '1')
            GROUP BY exp_id
        ) ps ON ps.exp_id = e.exp_id
        WHERE (e.exp_total - COALESCE(ps.submitted_amount, 0)) > 0
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
            <a href="../Reports/print_contract.php?ctr_id=<?php echo (int)$contract['ctr_id']; ?>&from_tenant=1" target="_blank" style="display: inline-block; margin-top: 0.75rem; padding: 0.5rem 1rem; background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.2)); border: 1px solid rgba(99, 102, 241, 0.3); border-radius: 8px; color: #a5b4fc; text-decoration: none; font-size: 0.875rem; transition: all 0.3s ease;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;">
                    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                    <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
                    <line x1="12" y1="11" x2="12" y2="17"/>
                    <line x1="9" y1="14" x2="15" y2="14"/>
                </svg>
                ดูใบสัญญา
            </a>
        </div>
        
        <!-- Sign Contract Banner -->
        <?php if (!$tenantSigned && ($contract['ctr_status'] ?? '0') !== '1'): ?>
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
            <a href="termination.php?token=<?php echo urlencode($token); ?>" class="menu-item">
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
            <a href="report_bills.php?token=<?php echo urlencode($token); ?>" class="menu-item">
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
            AND COALESCE((
                SELECT SUM(p.pay_amount) FROM payment p
                WHERE p.exp_id = e.exp_id AND p.pay_status IN ('0','1')
                AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ'
            ), 0) < e.exp_total
        ");
        $billStmt->execute([$contract['ctr_id'], $contract['ctr_id'], $contract['ctr_start'] ?? date('Y-m-d')]);
        $billCount = (int)($billStmt->fetchColumn() ?? 0);
    } catch (Exception $e) { error_log("Exception in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }
    ?>
    
    <nav class="bottom-nav">
        <div class="bottom-nav-content">
            <a href="index.php?token=<?php echo urlencode($token); ?>" class="nav-item active">
                <div class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
                หน้าหลัก
            </a>
            <a href="report_bills.php?token=<?php echo urlencode($token); ?>" class="nav-item">
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
