<?php
/**
 * Tenant Portal - หน้าหลักสำหรับผู้เช่า
 * เข้าถึงผ่าน QR Code พร้อม access token
 */
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

// รับ token จาก URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    http_response_code(403);
    die('<div style="text-align:center;padding:50px;font-family:sans-serif;"><h1>⚠️ ไม่พบ Token</h1><p>กรุณาสแกน QR Code ที่ได้รับจากหอพัก</p></div>');
}

// ตรวจสอบ token และดึงข้อมูลสัญญา
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
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$contract) {
        http_response_code(403);
        die('<div style="text-align:center;padding:50px;font-family:sans-serif;"><h1>⚠️ Token ไม่ถูกต้องหรือหมดอายุ</h1><p>กรุณาติดต่อผู้ดูแลหอพัก</p></div>');
    }
    
    // เก็บข้อมูลใน session สำหรับหน้าอื่นๆ
    $_SESSION['tenant_token'] = $token;
    $_SESSION['tenant_ctr_id'] = $contract['ctr_id'];
    $_SESSION['tenant_tnt_id'] = $contract['tnt_id'];
    $_SESSION['tenant_room_id'] = $contract['room_id'];
    $_SESSION['tenant_room_number'] = $contract['room_number'];
    $_SESSION['tenant_name'] = $contract['tnt_name'];
    
} catch (PDOException $e) {
    http_response_code(500);
    die('<div style="text-align:center;padding:50px;font-family:sans-serif;"><h1>❌ เกิดข้อผิดพลาด</h1><p>กรุณาลองใหม่อีกครั้ง</p></div>');
}

// ดึงค่าตั้งค่าระบบ
$siteName = 'Sangthian Dormitory';
$logoFilename = 'Logo.jpg';
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
        if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
    }
} catch (PDOException $e) {}

// ดึงข้อมูลบิลค่าใช้จ่ายล่าสุด
$latestExpense = null;
try {
    $expStmt = $pdo->prepare("SELECT * FROM expense WHERE ctr_id = ? ORDER BY exp_month DESC LIMIT 1");
    $expStmt->execute([$contract['ctr_id']]);
    $latestExpense = $expStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ดึงข่าวประชาสัมพันธ์ล่าสุด
$latestNews = [];
try {
    $newsStmt = $pdo->query("SELECT * FROM news ORDER BY news_date DESC LIMIT 3");
    $latestNews = $newsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ดึงสถานะการแจ้งซ่อมล่าสุด
$latestRepair = null;
try {
    $repairStmt = $pdo->prepare("SELECT * FROM repair WHERE ctr_id = ? ORDER BY repair_date DESC LIMIT 1");
    $repairStmt->execute([$contract['ctr_id']]);
    $latestRepair = $repairStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

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
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($siteName); ?> - Tenant Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/jpeg" href="..//Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>">
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
        
        .view-all-link {
            display: block;
            text-align: center;
            color: #3b82f6;
            text-decoration: none;
            font-size: 0.85rem;
            padding: 0.75rem;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <img src="..//Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="Logo" class="logo">
            <div class="header-info">
                <h1><?php echo htmlspecialchars($siteName); ?></h1>
                <p>Tenant Portal</p>
            </div>
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
        </div>
        
        <!-- Alert for unpaid bill -->
        <?php if ($latestExpense && $latestExpense['exp_status'] === '0'): ?>
        <div class="alert-unpaid">
            <div class="alert-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
            <div class="alert-content">
                <h3>มีบิลค้างชำระ</h3>
                <p>ยอดรวม <?php echo number_format($latestExpense['exp_total']); ?> บาท</p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Menu Grid -->
        <div class="section-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></span> บริการ</div>
        <div class="menu-grid">
            <a href="profile.php?token=<?php echo urlencode($token); ?>" class="menu-item">
                <div class="menu-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                <div class="menu-label">ข้อมูลส่วนตัว</div>
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
                <div class="menu-icon red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/></svg></div>
                <div class="menu-label">แจ้งยกเลิกสัญญา</div>
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
                <span class="info-value"><?php echo $latestRepair['repair_date'] ?? '-'; ?></span>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Latest News -->
        <?php if (!empty($latestNews)): ?>
        <div class="section-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><line x1="10" y1="6" x2="18" y2="6"/><line x1="10" y1="10" x2="18" y2="10"/><line x1="10" y1="14" x2="18" y2="14"/></svg></span> ข่าวล่าสุด</div>
        <?php foreach ($latestNews as $news): ?>
        <div class="news-item">
            <div class="news-date"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:12px;height:12px;vertical-align:middle;margin-right:4px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> <?php echo $news['news_date'] ?? '-'; ?></div>
            <div class="news-title"><?php echo htmlspecialchars($news['news_title'] ?? '-'); ?></div>
        </div>
        <?php endforeach; ?>
        <a href="report_news.php?token=<?php echo urlencode($token); ?>" class="view-all-link">ดูข่าวทั้งหมด →</a>
        <?php endif; ?>
    </div>
    
    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <div class="bottom-nav-content">
            <a href="index.php?token=<?php echo urlencode($token); ?>" class="nav-item active">
                <div class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
                หน้าหลัก
            </a>
            <a href="report_bills.php?token=<?php echo urlencode($token); ?>" class="nav-item">
                <div class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1z"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="12" y2="14"/></svg></div>
                บิล
            </a>
            <a href="repair.php?token=<?php echo urlencode($token); ?>" class="nav-item">
                <div class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></div>
                แจ้งซ่อม
            </a>
            <a href="profile.php?token=<?php echo urlencode($token); ?>" class="nav-item">
                <div class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                โปรไฟล์
            </a>
        </div>
    </nav>
</body>
</html>
