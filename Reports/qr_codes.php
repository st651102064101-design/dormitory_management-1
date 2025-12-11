<?php
/**
 * Admin - สร้าง QR Code สำหรับผู้เช่า (Apple Style)
 */
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
require_once __DIR__ . '/../config.php';
$pdo = connectDB();

// ดึงรายการสัญญาที่ยังใช้งานอยู่
$contracts = [];
try {
    // ตรวจสอบว่ามีคอลัมน์ access_token หรือยัง
    $checkColumn = $pdo->query("SHOW COLUMNS FROM contract LIKE 'access_token'");
    $hasColumn = $checkColumn->fetch();
    
    if (!$hasColumn) {
        $pdo->exec("ALTER TABLE `contract` ADD COLUMN `access_token` VARCHAR(64) DEFAULT NULL COMMENT 'Token สำหรับเข้าถึง Tenant Portal' AFTER `room_id`");
        $pdo->exec("ALTER TABLE `contract` ADD UNIQUE KEY `access_token_unique` (`access_token`)");
    }
    
    // สร้าง token สำหรับสัญญาที่ยังไม่มี
    $pdo->exec("UPDATE `contract` SET `access_token` = MD5(CONCAT(ctr_id, '-', tnt_id, '-', room_id, '-', NOW(), '-', RAND())) WHERE `access_token` IS NULL AND `ctr_status` IN ('0', '2')");
    
    $stmt = $pdo->query("
        SELECT c.*, 
               t.tnt_name, t.tnt_phone,
               r.room_number,
               rt.type_name
        FROM contract c
        JOIN tenant t ON c.tnt_id = t.tnt_id
        JOIN room r ON c.room_id = r.room_id
        LEFT JOIN roomtype rt ON r.type_id = rt.type_id
        WHERE c.ctr_status IN ('0', '2')
        ORDER BY CAST(r.room_number AS UNSIGNED) ASC
    ");
    $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
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

$baseUrl = getTenantPortalUrl();
$totalContracts = count($contracts);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สร้าง QR Code ผู้เช่า - <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="icon" type="image/jpeg" href="../Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Assets/Css/main.css">
    <link rel="stylesheet" href="../Assets/Css/animate-ui.css">
    <style>
        :root {
            --apple-blue: #007aff;
            --apple-green: #34c759;
            --apple-purple: #af52de;
            --apple-pink: #ff2d55;
            --apple-orange: #ff9500;
            --apple-teal: #5ac8fa;
            --apple-bg: #000000;
            --apple-card: rgba(28, 28, 30, 0.9);
            --apple-card-hover: rgba(44, 44, 46, 0.95);
            --apple-text: #ffffff;
            --apple-text-secondary: #8e8e93;
            --apple-separator: rgba(84, 84, 88, 0.65);
            --apple-radius: 20px;
        }

        body.qr-page {
            background: linear-gradient(135deg, #0a0a0f 0%, #1a1a2e 50%, #0a0a0f 100%);
            min-height: 100vh;
            font-family: 'Prompt', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes scaleIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        @keyframes glow {
            0%, 100% { box-shadow: 0 0 20px rgba(0, 122, 255, 0.3); }
            50% { box-shadow: 0 0 40px rgba(0, 122, 255, 0.6); }
        }

        @keyframes floatParticle {
            0% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10% { opacity: 0.3; }
            90% { opacity: 0.3; }
            100% { transform: translateY(-100vh) rotate(720deg); opacity: 0; }
        }

        .qr-wrapper {
            max-width: 1600px;
            margin: 0 auto;
            padding: 30px;
            animation: fadeInUp 0.6s ease;
        }

        .qr-header {
            text-align: center;
            margin-bottom: 40px;
            animation: fadeInUp 0.6s ease;
        }

        .qr-header-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, var(--apple-blue), var(--apple-purple));
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: float 3s ease-in-out infinite;
            box-shadow: 0 10px 40px rgba(0, 122, 255, 0.3);
        }

        .qr-header-icon svg {
            width: 44px;
            height: 44px;
            stroke: white;
            stroke-width: 1.5;
            fill: none;
        }

        .qr-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #fff 0%, #94a3b8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }

        .qr-header p {
            color: var(--apple-text-secondary);
            font-size: 1.1rem;
        }

        .stats-bar {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 40px;
            flex-wrap: wrap;
        }

        .stat-item {
            background: var(--apple-card);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--apple-separator);
            border-radius: 16px;
            padding: 20px 30px;
            text-align: center;
            animation: scaleIn 0.5s ease;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .stat-item:hover {
            transform: translateY(-5px) scale(1.02);
            border-color: var(--apple-blue);
            box-shadow: 0 10px 40px rgba(0, 122, 255, 0.2);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            margin: 0 auto 12px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-icon.blue { background: linear-gradient(135deg, #007aff, #5856d6); }
        .stat-icon.green { background: linear-gradient(135deg, #34c759, #30d158); }
        .stat-icon.purple { background: linear-gradient(135deg, #af52de, #bf5af2); }

        .stat-icon svg {
            width: 26px;
            height: 26px;
            stroke: white;
            stroke-width: 2;
            fill: none;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--apple-text);
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 0.85rem;
            color: var(--apple-text-secondary);
        }

        .action-bar {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 40px;
            flex-wrap: wrap;
        }

        .btn-apple {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 28px;
            border: none;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            font-family: inherit;
        }

        .btn-apple svg {
            width: 22px;
            height: 22px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
            transition: transform 0.3s;
        }

        .btn-apple:hover svg {
            transform: scale(1.1);
        }

        .btn-apple.primary {
            background: linear-gradient(135deg, var(--apple-blue), #5856d6);
            color: white;
            box-shadow: 0 4px 20px rgba(0, 122, 255, 0.4);
        }

        .btn-apple.primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(0, 122, 255, 0.5);
        }

        .btn-apple.secondary {
            background: var(--apple-card);
            color: var(--apple-text);
            border: 1px solid var(--apple-separator);
        }

        .btn-apple.secondary:hover {
            background: var(--apple-card-hover);
            border-color: var(--apple-blue);
        }

        .qr-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
        }

        @media (max-width: 1400px) { .qr-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 1000px) { .qr-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 600px) { .qr-grid { grid-template-columns: 1fr; } }

        .qr-card {
            background: var(--apple-card);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--apple-separator);
            border-radius: var(--apple-radius);
            padding: 24px;
            text-align: center;
            animation: fadeInUp 0.6s ease backwards;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            overflow: hidden;
        }

        .qr-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--apple-blue), var(--apple-purple), var(--apple-pink));
            opacity: 0;
            transition: opacity 0.3s;
        }

        .qr-card:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: var(--apple-blue);
            box-shadow: 0 20px 60px rgba(0, 122, 255, 0.2);
        }

        .qr-card:hover::before {
            opacity: 1;
        }

        .qr-card:nth-child(1) { animation-delay: 0.1s; }
        .qr-card:nth-child(2) { animation-delay: 0.15s; }
        .qr-card:nth-child(3) { animation-delay: 0.2s; }
        .qr-card:nth-child(4) { animation-delay: 0.25s; }
        .qr-card:nth-child(5) { animation-delay: 0.3s; }
        .qr-card:nth-child(6) { animation-delay: 0.35s; }
        .qr-card:nth-child(7) { animation-delay: 0.4s; }
        .qr-card:nth-child(8) { animation-delay: 0.45s; }

        .room-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--apple-blue), var(--apple-purple));
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 16px;
            box-shadow: 0 4px 15px rgba(0, 122, 255, 0.3);
            transition: all 0.3s;
        }

        .qr-card:hover .room-badge {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(0, 122, 255, 0.4);
        }

        .room-badge svg {
            width: 20px;
            height: 20px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }

        .tenant-info {
            margin-bottom: 20px;
        }

        .tenant-name {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--apple-text);
            margin-bottom: 8px;
        }

        .tenant-name svg {
            width: 18px;
            height: 18px;
            stroke: var(--apple-blue);
            stroke-width: 2;
            fill: none;
        }

        .tenant-phone {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 0.9rem;
            color: var(--apple-text-secondary);
        }

        .tenant-phone svg {
            width: 16px;
            height: 16px;
            stroke: var(--apple-green);
            stroke-width: 2;
            fill: none;
        }

        .qr-container {
            background: white;
            padding: 16px;
            border-radius: 16px;
            display: inline-block;
            margin-bottom: 20px;
            position: relative;
            transition: all 0.3s;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .qr-card:hover .qr-container {
            transform: scale(1.02);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
        }

        .qr-container img {
            display: block;
            width: 160px;
            height: 160px;
            border-radius: 8px;
        }

        .qr-container::before,
        .qr-container::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 3px solid var(--apple-blue);
            transition: all 0.3s;
        }

        .qr-container::before {
            top: 8px;
            left: 8px;
            border-right: none;
            border-bottom: none;
            border-radius: 8px 0 0 0;
        }

        .qr-container::after {
            bottom: 8px;
            right: 8px;
            border-left: none;
            border-top: none;
            border-radius: 0 0 8px 0;
        }

        .qr-card:hover .qr-container::before,
        .qr-card:hover .qr-container::after {
            border-color: var(--apple-purple);
        }

        .card-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .btn-card {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 16px;
            border: none;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            font-family: inherit;
        }

        .btn-card svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }

        .btn-card.download {
            background: rgba(52, 199, 89, 0.15);
            color: var(--apple-green);
        }

        .btn-card.download:hover {
            background: var(--apple-green);
            color: white;
            transform: translateY(-2px);
        }

        .btn-card.print {
            background: rgba(0, 122, 255, 0.15);
            color: var(--apple-blue);
        }

        .btn-card.print:hover {
            background: var(--apple-blue);
            color: white;
            transform: translateY(-2px);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            animation: fadeInUp 0.6s ease;
        }

        .empty-icon {
            width: 120px;
            height: 120px;
            margin: 0 auto 24px;
            background: var(--apple-card);
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: float 3s ease-in-out infinite;
        }

        .empty-icon svg {
            width: 60px;
            height: 60px;
            stroke: var(--apple-text-secondary);
            stroke-width: 1.5;
            fill: none;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            color: var(--apple-text);
            margin-bottom: 10px;
        }

        .empty-state p {
            color: var(--apple-text-secondary);
            font-size: 1rem;
        }

        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: var(--apple-blue);
            border-radius: 50%;
            opacity: 0.3;
            animation: floatParticle 15s infinite linear;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .qr-wrapper { padding: 10px; }
            .qr-header, .stats-bar, .action-bar, .particles { display: none !important; }
            .qr-card {
                break-inside: avoid;
                page-break-inside: avoid;
                border: 1px solid #ccc !important;
                background: white !important;
                margin-bottom: 15px;
                box-shadow: none !important;
            }
            .qr-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 15px;
            }
            .room-badge {
                background: #007aff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .tenant-name, .tenant-phone { color: #333 !important; }
            .qr-container::before, .qr-container::after { display: none; }
        }
    </style>
</head>
<body class="reports-page qr-page">
    <div class="particles no-print">
        <?php for ($i = 0; $i < 20; $i++): ?>
        <div class="particle" style="left: <?php echo rand(0, 100); ?>%; animation-delay: <?php echo rand(0, 15); ?>s; animation-duration: <?php echo rand(12, 20); ?>s;"></div>
        <?php endfor; ?>
    </div>

    <div class="app-shell">
        <?php 
        $currentPage = 'qr_codes.php';
        include __DIR__ . '/../includes/sidebar.php'; 
        ?>
        <div class="app-main">
            <main>
                <div class="qr-wrapper">
                    <div class="qr-header">
                        <div class="qr-header-icon">
                            <svg viewBox="0 0 24 24">
                                <rect x="3" y="3" width="7" height="7" rx="1"/>
                                <rect x="14" y="3" width="7" height="7" rx="1"/>
                                <rect x="3" y="14" width="7" height="7" rx="1"/>
                                <rect x="14" y="14" width="3" height="3"/>
                                <rect x="18" y="14" width="3" height="3"/>
                                <rect x="14" y="18" width="3" height="3"/>
                                <rect x="18" y="18" width="3" height="3"/>
                            </svg>
                        </div>
                        <h1>QR Code ผู้เช่า</h1>
                        <p>สร้างและจัดการ QR Code สำหรับเข้าสู่ระบบผู้เช่า</p>
                    </div>

                    <div class="stats-bar no-print">
                        <div class="stat-item">
                            <div class="stat-icon blue">
                                <svg viewBox="0 0 24 24">
                                    <rect x="3" y="3" width="7" height="7" rx="1"/>
                                    <rect x="14" y="3" width="7" height="7" rx="1"/>
                                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                                    <path d="M14 14h7v7h-7z"/>
                                </svg>
                            </div>
                            <div class="stat-value"><?php echo $totalContracts; ?></div>
                            <div class="stat-label">QR Code ทั้งหมด</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon green">
                                <svg viewBox="0 0 24 24">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                            </div>
                            <div class="stat-value"><?php echo $totalContracts; ?></div>
                            <div class="stat-label">ผู้เช่าใช้งาน</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon purple">
                                <svg viewBox="0 0 24 24">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                    <polyline points="22 4 12 14.01 9 11.01"/>
                                </svg>
                            </div>
                            <div class="stat-value">100%</div>
                            <div class="stat-label">พร้อมใช้งาน</div>
                        </div>
                    </div>

                    <?php if (!empty($contracts)): ?>
                    <div class="action-bar no-print">
                        <button class="btn-apple primary" onclick="window.print()">
                            <svg viewBox="0 0 24 24">
                                <polyline points="6 9 6 2 18 2 18 9"/>
                                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                                <rect x="6" y="14" width="12" height="8"/>
                            </svg>
                            พิมพ์ทั้งหมด
                        </button>
                        <button class="btn-apple secondary" onclick="downloadAll()">
                            <svg viewBox="0 0 24 24">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="7 10 12 15 17 10"/>
                                <line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                            ดาวน์โหลดทั้งหมด
                        </button>
                    </div>

                    <div class="qr-grid">
                        <?php foreach ($contracts as $index => $contract): ?>
                        <?php 
                            $tenantUrl = $baseUrl . '?token=' . urlencode($contract['access_token']);
                            $qrId = 'qr_' . $contract['ctr_id'];
                            $qrImageUrl = '../qr_generate.php?data=' . urlencode($tenantUrl);
                        ?>
                        <div class="qr-card" style="animation-delay: <?php echo ($index % 8) * 0.05; ?>s;">
                            <div class="room-badge">
                                <svg viewBox="0 0 24 24">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                    <polyline points="9 22 9 12 15 12 15 22"/>
                                </svg>
                                ห้อง <?php echo htmlspecialchars($contract['room_number']); ?>
                            </div>
                            
                            <div class="tenant-info">
                                <div class="tenant-name">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                        <circle cx="12" cy="7" r="4"/>
                                    </svg>
                                    <?php echo htmlspecialchars($contract['tnt_name']); ?>
                                </div>
                                <div class="tenant-phone">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                    </svg>
                                    <?php echo htmlspecialchars($contract['tnt_phone'] ?? '-'); ?>
                                </div>
                            </div>
                            
                            <div class="qr-container">
                                <img id="<?php echo $qrId; ?>" src="<?php echo $qrImageUrl; ?>" alt="QR Code ห้อง <?php echo htmlspecialchars($contract['room_number']); ?>">
                            </div>
                            
                            <div class="card-actions no-print">
                                <button class="btn-card download" onclick="downloadQR('<?php echo $qrImageUrl; ?>', '<?php echo $contract['room_number']; ?>')">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                        <polyline points="7 10 12 15 17 10"/>
                                        <line x1="12" y1="15" x2="12" y2="3"/>
                                    </svg>
                                    ดาวน์โหลด
                                </button>
                                <button class="btn-card print" onclick="printSingleQR('<?php echo $qrImageUrl; ?>', '<?php echo htmlspecialchars($contract['room_number']); ?>', '<?php echo htmlspecialchars(addslashes($contract['tnt_name'])); ?>')">
                                    <svg viewBox="0 0 24 24">
                                        <polyline points="6 9 6 2 18 2 18 9"/>
                                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                                        <rect x="6" y="14" width="12" height="8"/>
                                    </svg>
                                    พิมพ์
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <svg viewBox="0 0 24 24">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                <line x1="9" y1="9" x2="15" y2="15"/>
                                <line x1="15" y1="9" x2="9" y2="15"/>
                            </svg>
                        </div>
                        <h3>ไม่มี QR Code</h3>
                        <p>กรุณาเพิ่มสัญญาเช่าก่อนสร้าง QR Code</p>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <script src="../Assets/Javascript/animate-ui.js" defer></script>
    <script src="../Assets/Javascript/main.js" defer></script>
    <script>
    function downloadQR(imageUrl, roomNumber) {
        const link = document.createElement('a');
        link.download = 'QR_Room_' + roomNumber + '.png';
        link.href = imageUrl;
        link.target = '_blank';
        link.click();
    }

    function downloadAll() {
        const cards = document.querySelectorAll('.qr-card');
        let delay = 0;
        cards.forEach(card => {
            const img = card.querySelector('.qr-container img');
            const roomBadge = card.querySelector('.room-badge');
            const roomNumber = roomBadge.textContent.replace('ห้อง', '').trim();
            
            setTimeout(() => {
                downloadQR(img.src, roomNumber);
            }, delay);
            delay += 500;
        });
    }
    
    function printSingleQR(imageUrl, roomNumber, tenantName) {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(\`
            <!DOCTYPE html>
            <html>
            <head>
                <title>QR Code ห้อง \${roomNumber}</title>
                <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700&display=swap" rel="stylesheet">
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body {
                        font-family: 'Prompt', sans-serif;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        min-height: 100vh;
                        background: #f5f5f7;
                    }
                    .print-card {
                        background: white;
                        border-radius: 20px;
                        padding: 40px;
                        text-align: center;
                        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
                    }
                    .room-badge {
                        display: inline-block;
                        background: linear-gradient(135deg, #007aff, #5856d6);
                        color: white;
                        padding: 12px 30px;
                        border-radius: 30px;
                        font-size: 1.5rem;
                        font-weight: 700;
                        margin-bottom: 15px;
                    }
                    .tenant-name {
                        font-size: 1.2rem;
                        font-weight: 600;
                        color: #1d1d1f;
                        margin-bottom: 20px;
                    }
                    .qr-image {
                        width: 200px;
                        height: 200px;
                        border-radius: 12px;
                    }
                    .instructions {
                        margin-top: 20px;
                        font-size: 0.95rem;
                        color: #86868b;
                    }
                    @media print {
                        body { background: white; }
                        .print-card { box-shadow: none; border: 2px solid #e5e5e5; }
                    }
                </style>
            </head>
            <body>
                <div class="print-card">
                    <div class="room-badge">🏠 ห้อง \${roomNumber}</div>
                    <div class="tenant-name">👤 \${tenantName}</div>
                    <img src="\${imageUrl}" class="qr-image" alt="QR Code">
                    <div class="instructions">📱 สแกน QR Code เพื่อเข้าสู่ระบบผู้เช่า</div>
                </div>
                <script>
                    window.onload = function() {
                        setTimeout(function() {
                            window.print();
                            window.close();
                        }, 500);
                    };
                <\/script>
            </body>
            </html>
        \`);
        printWindow.document.close();
    }
    </script>
</body>
</html>
