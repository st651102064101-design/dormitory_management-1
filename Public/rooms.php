<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../ConnectDB.php';

$pdo = connectDB();

// ดึงค่าตั้งค่าระบบ
$siteName = 'Sangthian Dormitory';
$logoFilename = 'Logo.jpg';
$themeColor = '#1e40af';
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename', 'theme_color')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
        if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
        if ($row['setting_key'] === 'theme_color') $themeColor = $row['setting_value'];
    }
} catch (PDOException $e) { echo "Settings Error: " . $e->getMessage(); }

// ดึงข้อมูลห้องทั้งหมด
$rooms = [];
$roomTypes = [];
$debugInfo = '';
try {
    // ดึงประเภทห้อง
    $stmt = $pdo->query("SELECT * FROM roomtype ORDER BY type_name");
    $roomTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $debugInfo .= "RoomTypes: " . count($roomTypes) . " | ";
    
    // ดึงห้องทั้งหมด
    $stmt = $pdo->query("SELECT r.*, rt.type_name, rt.type_price FROM room r LEFT JOIN roomtype rt ON r.type_id = rt.type_id ORDER BY CAST(r.room_number AS UNSIGNED) ASC");
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $debugInfo .= "Rooms: " . count($rooms);
} catch (PDOException $e) { 
    $debugInfo = "Query Error: " . $e->getMessage(); 
}

// Filter by type
$filterType = $_GET['type'] ?? '';
if ($filterType !== '') {
    $rooms = array_filter($rooms, fn($r) => $r['type_id'] == $filterType);
    $rooms = array_values($rooms); // reindex array
}

// Filter by status (0 = ว่าง, 1 = ไม่ว่าง)
$filterStatus = $_GET['status'] ?? '';
if ($filterStatus !== '') {
    $rooms = array_filter($rooms, fn($r) => (string)$r['room_status'] === $filterStatus);
    $rooms = array_values($rooms); // reindex array
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อมูลห้องพัก - <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="icon" type="image/jpeg" href="../Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            color: #fff;
        }
        
        .header {
            background: rgba(15, 23, 42, 0.95);
            padding: 1rem 2rem;
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #334155;
        }
        
        .logo { display: flex; align-items: center; gap: 1rem; text-decoration: none; }
        .logo img { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; }
        .logo h1 { font-size: 1.25rem; color: #fff; }
        
        .nav-links { display: flex; gap: 1rem; align-items: center; }
        .nav-links a {
            color: #94a3b8;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .nav-links a:hover { color: #fff; background: #334155; }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 6rem 1rem 2rem;
        }
        
        .page-title {
            text-align: center;
            margin-bottom: 2rem;
        }
        .page-title h2 { font-size: 2rem; margin-bottom: 0.5rem; }
        .page-title p { color: #94a3b8; }
        
        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            justify-content: center;
        }
        .filters select {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 1px solid #334155;
            background: #1e293b;
            color: #fff;
            font-size: 1rem;
        }
        
        .room-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        
        .room-card {
            background: #1e293b;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #334155;
            transition: all 0.3s;
        }
        .room-card:hover {
            transform: translateY(-5px);
            border-color: #3b82f6;
        }
        
        .room-header {
            padding: 1.5rem;
            text-align: center;
        }
        .room-header[data-status="0"] { background: linear-gradient(135deg, #22c55e, #16a34a); }
        .room-header[data-status="1"] { background: linear-gradient(135deg, #ef4444, #dc2626); }
        
        .room-number { font-size: 1.75rem; font-weight: 700; }
        .room-type { opacity: 0.9; font-size: 0.9rem; }
        
        .room-body { padding: 1.5rem; }
        .room-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: #22c55e;
            margin-bottom: 1rem;
        }
        .room-price span { font-size: 0.9rem; color: #94a3b8; font-weight: 400; }
        
        .room-info { list-style: none; margin-bottom: 1rem; }
        .room-info li {
            padding: 0.5rem 0;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-bottom: 1px solid #334155;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .status-badge[data-status="0"] { background: #22c55e20; color: #22c55e; }
        .status-badge[data-status="1"] { background: #ef444420; color: #ef4444; }
        
        .btn-book {
            display: block;
            width: 100%;
            padding: 0.75rem;
            background: <?php echo $themeColor; ?>;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-book:hover { opacity: 0.9; }
        .btn-book:disabled, .btn-book.disabled {
            background: #475569;
            cursor: not-allowed;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #60a5fa;
            text-decoration: none;
            margin-bottom: 2rem;
        }
        .back-link:hover { text-decoration: underline; }
        
        @media (max-width: 768px) {
            .header { flex-direction: column; gap: 1rem; padding: 1rem; }
            .nav-links { flex-wrap: wrap; justify-content: center; }
            .filters { flex-direction: column; }
        }
    </style>
</head>
<body>
    <header class="header">
        <a href="../index.php" class="logo">
            <img src="../Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="">
            <h1><?php echo htmlspecialchars($siteName); ?></h1>
        </a>
        <nav class="nav-links">
            <a href="../index.php">🏠 หน้าแรก</a>
            <a href="rooms.php">🏠 ห้องพัก</a>
            <a href="news.php">📰 ข่าวสาร</a>
            <a href="booking.php">📅 จองห้อง</a>
        </nav>
    </header>

    <div class="container">
        <a href="../index.php" class="back-link">← กลับหน้าแรก</a>
        
        <div class="page-title">
            <h2>🏠 รายงานข้อมูลห้องพัก</h2>
            <p>รายละเอียดห้องพักทั้งหมด <?php echo count($rooms); ?> ห้อง</p>
            <p style="font-size:0.8rem;color:#f59e0b;">Debug: <?php echo $debugInfo; ?></p>
        </div>
        
        <form class="filters" method="get">
            <select name="type" onchange="this.form.submit()">
                <option value="">-- ประเภทห้องทั้งหมด --</option>
                <?php foreach ($roomTypes as $type): ?>
                <option value="<?php echo $type['type_id']; ?>" <?php echo $filterType == $type['type_id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($type['type_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select name="status" onchange="this.form.submit()">
                <option value="">-- สถานะทั้งหมด --</option>
                <option value="0" <?php echo $filterStatus === '0' ? 'selected' : ''; ?>>ว่าง</option>
                <option value="1" <?php echo $filterStatus === '1' ? 'selected' : ''; ?>>มีผู้เช่า</option>
            </select>
        </form>
        
        <div class="room-grid">
            <?php foreach ($rooms as $room): ?>
            <div class="room-card">
                <div class="room-header" data-status="<?php echo $room['room_status']; ?>">
                    <div class="room-number">ห้อง <?php echo htmlspecialchars($room['room_number']); ?></div>
                    <div class="room-type"><?php echo htmlspecialchars($room['type_name'] ?? 'มาตรฐาน'); ?></div>
                </div>
                <div class="room-body">
                    <div class="room-price">
                        ฿<?php echo number_format($room['type_price'] ?? 0); ?> <span>/เดือน</span>
                    </div>
                    <ul class="room-info">
                        <li>📍 ชั้น <?php echo htmlspecialchars($room['room_floor'] ?? '-'); ?></li>
                        <li>📋 <?php echo htmlspecialchars($room['type_name'] ?? 'ห้องพักมาตรฐาน'); ?></li>
                        <li>
                            สถานะ: 
                            <span class="status-badge" data-status="<?php echo $room['room_status']; ?>">
                                <?php 
                                $statusText = ['0' => 'ว่าง', '1' => 'มีผู้เช่า'];
                                echo $statusText[$room['room_status']] ?? 'ไม่ทราบ';
                                ?>
                            </span>
                        </li>
                    </ul>
                    <?php if ($room['room_status'] === '0'): ?>
                    <a href="booking.php?room=<?php echo $room['room_id']; ?>" class="btn-book">📅 จองห้องนี้</a>
                    <?php else: ?>
                    <button class="btn-book disabled" disabled>ไม่ว่าง</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
