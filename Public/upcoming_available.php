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

try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename', 'theme_color', 'public_theme')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
        if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
        if ($row['setting_key'] === 'theme_color') $themeColor = $row['setting_value'];
        if ($row['setting_key'] === 'public_theme') $publicTheme = $row['setting_value'];
    }
} catch (PDOException $e) {}

// รับค่าเดือนที่เลือก (ค่าเริ่มต้นคือเดือนปัจจุบัน)
$selectedMonth = $_GET['month'] ?? date('Y-m');
$selectedYear = (int)substr($selectedMonth, 0, 4);
$selectedMonthNum = (int)substr($selectedMonth, 5, 2);

// สร้างช่วงวันที่สำหรับเดือนที่เลือก
$monthStart = $selectedMonth . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));

// ชื่อเดือนภาษาไทย
$thaiMonths = [
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
];
$thaiYear = $selectedYear + 543;
$displayMonth = $thaiMonths[$selectedMonthNum] . ' ' . $thaiYear;

// ดึงห้องที่สัญญาจะหมดในเดือนที่เลือก
$upcomingRooms = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            r.room_id, r.room_number, r.room_status, r.room_image,
            rt.type_name, rt.type_price,
            c.ctr_id, c.ctr_end, c.ctr_status,
            t.tnt_name
        FROM contract c
        INNER JOIN room r ON c.room_id = r.room_id
        LEFT JOIN roomtype rt ON r.type_id = rt.type_id
        LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
        WHERE c.ctr_status = '0' 
            AND c.ctr_end >= :month_start 
            AND c.ctr_end <= :month_end
        ORDER BY c.ctr_end ASC, CAST(r.room_number AS UNSIGNED) ASC
    ");
    $stmt->execute([
        ':month_start' => $monthStart,
        ':month_end' => $monthEnd
    ]);
    $upcomingRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // error handling
}

// ดึงห้องว่างปัจจุบัน
$availableNow = [];
try {
    $stmt = $pdo->query("
        SELECT r.room_id, r.room_number, r.room_image, rt.type_name, rt.type_price
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
    $availableNow = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// สร้างรายการเดือนสำหรับ dropdown (12 เดือนข้างหน้า)
$monthOptions = [];
for ($i = 0; $i < 12; $i++) {
    $monthDate = date('Y-m', strtotime("+$i months"));
    $y = (int)substr($monthDate, 0, 4);
    $m = (int)substr($monthDate, 5, 2);
    $monthOptions[] = [
        'value' => $monthDate,
        'label' => $thaiMonths[$m] . ' ' . ($y + 543)
    ];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ห้องที่จะว่าง - <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include_once __DIR__ . '/../includes/public_theme.php'; ?>
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
            width: 150%;
            height: 150%;
            top: -25%;
            left: -25%;
            background: radial-gradient(ellipse at 30% 20%, <?php echo $themeColor; ?>20 0%, transparent 50%),
                        radial-gradient(ellipse at 80% 80%, #6366f115 0%, transparent 40%),
                        radial-gradient(ellipse at 50% 50%, #0a0a0f 0%, #0a0a0f 100%);
            animation: bgMove 20s ease-in-out infinite alternate;
        }

        @keyframes bgMove {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(-5%, -5%) rotate(2deg); }
        }

        /* Header */
        .header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(10, 10, 15, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255,255,255,0.06);
            padding: 1rem 2rem;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo-img {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            object-fit: cover;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }

        .logo-text h1 {
            font-size: 1.3rem;
            font-weight: 600;
            background: linear-gradient(135deg, #fff, <?php echo $themeColor; ?>);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-links {
            display: flex;
            gap: 0.5rem;
        }

        .nav-link {
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            text-decoration: none;
            color: rgba(255,255,255,0.7);
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-link:hover {
            background: rgba(255,255,255,0.08);
            color: #fff;
        }

        .nav-link.active {
            background: <?php echo $themeColor; ?>;
            color: #fff;
        }

        .nav-link svg {
            width: 18px;
            height: 18px;
        }

        /* Main Content */
        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Page Title */
        .page-title {
            text-align: center;
            margin-bottom: 2rem;
        }

        .page-title h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #fff, <?php echo $themeColor; ?>);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .page-title p {
            color: rgba(255,255,255,0.6);
            font-size: 1rem;
        }

        /* Quick Stats */
        .quick-stats {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .quick-stat {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 1rem 2rem;
            text-align: center;
            min-width: 140px;
        }

        .quick-stat.green { border-color: rgba(34, 197, 94, 0.3); background: rgba(34, 197, 94, 0.1); }
        .quick-stat.orange { border-color: rgba(249, 115, 22, 0.3); background: rgba(249, 115, 22, 0.1); }

        .quick-stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .quick-stat.green .quick-stat-number { color: #22c55e; }
        .quick-stat.orange .quick-stat-number { color: #f97316; }

        .quick-stat-label {
            font-size: 0.85rem;
            color: rgba(255,255,255,0.6);
        }

        /* Month Buttons */
        .month-buttons {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            padding: 0 1rem;
        }

        .month-btn {
            padding: 0.6rem 1rem;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.15);
            background: rgba(255,255,255,0.05);
            color: rgba(255,255,255,0.7);
            font-size: 0.85rem;
            font-family: 'Prompt', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .month-btn:hover {
            background: rgba(255,255,255,0.1);
            border-color: <?php echo $themeColor; ?>;
            color: #fff;
        }

        .month-btn.active {
            background: <?php echo $themeColor; ?>;
            border-color: <?php echo $themeColor; ?>;
            color: #fff;
            font-weight: 600;
        }

        /* Month Selector (fallback for more months) */
        .month-selector {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .month-selector label {
            font-size: 1rem;
            color: rgba(255,255,255,0.8);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .month-selector label svg {
            width: 20px;
            height: 20px;
            color: <?php echo $themeColor; ?>;
        }

        .month-select {
            padding: 0.8rem 1.5rem;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.15);
            background: rgba(255,255,255,0.05);
            color: #fff;
            font-size: 1rem;
            font-family: 'Prompt', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 200px;
        }

        .month-select:hover {
            border-color: <?php echo $themeColor; ?>;
        }

        .month-select:focus {
            outline: none;
            border-color: <?php echo $themeColor; ?>;
            box-shadow: 0 0 0 3px <?php echo $themeColor; ?>30;
        }

        .month-select option {
            background: #1a1a2e;
            color: #fff;
        }

        /* Section Cards */
        .section-card {
            background: rgba(255,255,255,0.03);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        .section-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .section-icon.green {
            background: linear-gradient(135deg, #22c55e, #16a34a);
        }

        .section-icon.orange {
            background: linear-gradient(135deg, #f97316, #ea580c);
        }

        .section-icon svg {
            width: 24px;
            height: 24px;
            color: #fff;
        }

        .section-title h3 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .section-title p {
            color: rgba(255,255,255,0.5);
            font-size: 0.85rem;
        }

        .section-count {
            margin-left: auto;
            background: rgba(255,255,255,0.1);
            padding: 0.5rem 1rem;
            border-radius: 100px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        /* Room Grid */
        .room-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        /* Room Card */
        .room-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .room-card:hover {
            transform: translateY(-5px);
            border-color: <?php echo $themeColor; ?>50;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }

        .room-image {
            width: 100%;
            height: 160px;
            object-fit: cover;
            background: linear-gradient(135deg, #1a1a2e, #2d2d44);
        }

        .room-info {
            padding: 1.25rem;
        }

        .room-number {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .room-number svg {
            width: 22px;
            height: 22px;
            color: <?php echo $themeColor; ?>;
        }

        .room-type {
            color: rgba(255,255,255,0.6);
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
        }

        .room-price {
            font-size: 1.1rem;
            font-weight: 600;
            color: <?php echo $themeColor; ?>;
            margin-bottom: 1rem;
        }

        .room-meta {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(255,255,255,0.06);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: rgba(255,255,255,0.7);
        }

        .meta-item svg {
            width: 16px;
            height: 16px;
            color: rgba(255,255,255,0.5);
        }

        .meta-item.highlight {
            color: #f97316;
        }

        .meta-item.highlight svg {
            color: #f97316;
        }

        .btn-book {
            display: block;
            width: 100%;
            padding: 0.8rem;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, <?php echo $themeColor; ?>, <?php echo $themeColor; ?>dd);
            color: #fff;
            font-size: 0.95rem;
            font-weight: 600;
            font-family: 'Prompt', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
            margin-top: 1rem;
        }

        .btn-book:hover {
            transform: scale(1.02);
            box-shadow: 0 5px 20px <?php echo $themeColor; ?>40;
        }

        .btn-book.disabled {
            background: rgba(255,255,255,0.1);
            cursor: not-allowed;
            pointer-events: none;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: rgba(255,255,255,0.5);
        }

        .empty-state svg {
            width: 60px;
            height: 60px;
            margin-bottom: 1rem;
            opacity: 0.4;
        }

        .empty-state h4 {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: rgba(255,255,255,0.7);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header {
                padding: 1rem;
            }

            .header-content {
                flex-direction: column;
                gap: 1rem;
            }

            .nav-links {
                width: 100%;
                justify-content: center;
            }

            .main-content {
                padding: 1rem;
            }

            .page-title h2 {
                font-size: 1.5rem;
            }

            .month-selector {
                flex-direction: column;
            }

            .month-select {
                width: 100%;
            }

            .room-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="bg-animation">
        <div class="bg-gradient"></div>
    </div>

    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo-section">
                <img src="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="Logo" class="logo-img">
                <div class="logo-text">
                    <h1><?php echo htmlspecialchars($siteName); ?></h1>
                </div>
            </div>
            <nav class="nav-links">
                <a href="/dormitory_management/" class="nav-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg>
                    หน้าแรก
                </a>
                <a href="/dormitory_management/Public/rooms.php" class="nav-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    ห้องพัก
                </a>
                <a href="/dormitory_management/Public/upcoming_available.php" class="nav-link active">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    ห้องใกล้ว่าง
                </a>
                <a href="/dormitory_management/Public/booking.php" class="nav-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                    จองห้อง
                </a>
            </nav>
        </div>
    </header>

    <main class="main-content">
        <!-- Page Title -->
        <div class="page-title">
            <h2>🗓️ ห้องที่จะว่างเร็วๆ นี้</h2>
            <p>ดูห้องที่สัญญาใกล้หมดและพร้อมให้จองในแต่ละเดือน</p>
        </div>

        <!-- Quick Stats -->
        <div class="quick-stats">
            <div class="quick-stat green">
                <div class="quick-stat-number"><?php echo count($availableNow); ?></div>
                <div class="quick-stat-label">ว่างตอนนี้</div>
            </div>
            <div class="quick-stat orange">
                <div class="quick-stat-number"><?php echo count($upcomingRooms); ?></div>
                <div class="quick-stat-label">ว่างใน<?php echo $thaiMonths[$selectedMonthNum]; ?></div>
            </div>
        </div>

        <!-- Month Quick Buttons -->
        <div class="month-buttons">
            <?php 
            // แสดงปุ่ม 6 เดือนแรก
            for ($i = 0; $i < 6; $i++): 
                $monthDate = date('Y-m', strtotime("+$i months"));
                $y = (int)substr($monthDate, 0, 4);
                $m = (int)substr($monthDate, 5, 2);
                $isActive = $monthDate === $selectedMonth;
                $shortMonth = $thaiMonths[$m];
            ?>
            <a href="?month=<?php echo $monthDate; ?>" class="month-btn <?php echo $isActive ? 'active' : ''; ?>">
                <?php echo $shortMonth . ' ' . ($y + 543 - 2500); ?>
            </a>
            <?php endfor; ?>
        </div>

        <!-- Month Dropdown (for more months) -->
        <div class="month-selector">
            <label>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                หรือเลือกเดือนอื่น:
            </label>
            <select class="month-select" onchange="window.location.href='?month=' + this.value">
                <?php foreach ($monthOptions as $option): ?>
                <option value="<?php echo $option['value']; ?>" <?php echo $option['value'] === $selectedMonth ? 'selected' : ''; ?>>
                    <?php echo $option['label']; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Currently Available Rooms -->
        <section class="section-card">
            <div class="section-header">
                <div class="section-icon green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                </div>
                <div class="section-title">
                    <h3>ห้องว่างพร้อมจองทันที</h3>
                    <p>ห้องที่สามารถจองได้เลยตอนนี้</p>
                </div>
                <div class="section-count"><?php echo count($availableNow); ?> ห้อง</div>
            </div>

            <?php if (empty($availableNow)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <h4>ไม่มีห้องว่างในขณะนี้</h4>
                <p>กรุณาดูห้องที่จะว่างในเดือนถัดไป</p>
            </div>
            <?php else: ?>
            <div class="room-grid">
                <?php foreach ($availableNow as $room): ?>
                <div class="room-card">
                    <?php 
                    $roomImage = !empty($room['room_image']) 
                        ? '/dormitory_management/Public/Assets/Images/Rooms/' . htmlspecialchars($room['room_image'])
                        : '/dormitory_management/Public/Assets/Images/room-placeholder.jpg';
                    ?>
                    <img src="<?php echo $roomImage; ?>" alt="ห้อง <?php echo htmlspecialchars($room['room_number']); ?>" class="room-image" onerror="this.src='/dormitory_management/Public/Assets/Images/room-placeholder.jpg'">
                    <div class="room-info">
                        <div class="room-number">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            </svg>
                            ห้อง <?php echo htmlspecialchars($room['room_number']); ?>
                        </div>
                        <div class="room-type"><?php echo htmlspecialchars($room['type_name'] ?? 'ห้องมาตรฐาน'); ?></div>
                        <div class="room-price">฿<?php echo number_format((float)($room['type_price'] ?? 0)); ?>/เดือน</div>
                        <a href="/dormitory_management/Public/booking.php?room=<?php echo $room['room_id']; ?>" class="btn-book">
                            จองห้องนี้
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <!-- Upcoming Available Rooms -->
        <section class="section-card">
            <div class="section-header">
                <div class="section-icon orange">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                </div>
                <div class="section-title">
                    <h3>ห้องที่จะว่างใน<?php echo $displayMonth; ?></h3>
                    <p>ห้องที่สัญญาจะหมดในเดือนที่เลือก</p>
                </div>
                <div class="section-count"><?php echo count($upcomingRooms); ?> ห้อง</div>
            </div>

            <?php if (empty($upcomingRooms)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <h4>ไม่มีห้องที่จะว่างในเดือนนี้</h4>
                <p>ลองเลือกดูเดือนอื่นได้ครับ</p>
            </div>
            <?php else: ?>
            <div class="room-grid">
                <?php foreach ($upcomingRooms as $room): 
                    $endDate = new DateTime($room['ctr_end']);
                    $thaiEndDate = $endDate->format('j') . ' ' . $thaiMonths[(int)$endDate->format('n')] . ' ' . ($endDate->format('Y') + 543);
                ?>
                <div class="room-card">
                    <?php 
                    $roomImage = !empty($room['room_image']) 
                        ? '/dormitory_management/Public/Assets/Images/Rooms/' . htmlspecialchars($room['room_image'])
                        : '/dormitory_management/Public/Assets/Images/room-placeholder.jpg';
                    ?>
                    <img src="<?php echo $roomImage; ?>" alt="ห้อง <?php echo htmlspecialchars($room['room_number']); ?>" class="room-image" onerror="this.src='/dormitory_management/Public/Assets/Images/room-placeholder.jpg'">
                    <div class="room-info">
                        <div class="room-number">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            </svg>
                            ห้อง <?php echo htmlspecialchars($room['room_number']); ?>
                        </div>
                        <div class="room-type"><?php echo htmlspecialchars($room['type_name'] ?? 'ห้องมาตรฐาน'); ?></div>
                        <div class="room-price">฿<?php echo number_format((float)($room['type_price'] ?? 0)); ?>/เดือน</div>
                        
                        <div class="room-meta">
                            <div class="meta-item highlight">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                ว่างวันที่: <?php echo $thaiEndDate; ?>
                            </div>
                            <?php if (!empty($room['tnt_name'])): ?>
                            <div class="meta-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                                ผู้เช่าปัจจุบัน: <?php echo htmlspecialchars($room['tnt_name']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <button class="btn-book disabled">
                            รอห้องว่าง
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>
    </main>

    <script>
        // Add smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.room-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>
