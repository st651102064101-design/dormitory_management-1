<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/ConnectDB.php';

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
} catch (PDOException $e) {}

// ดึงข้อมูลห้องว่าง (room_status = '0' คือห้องว่าง)
$availableRooms = [];
try {
    $stmt = $pdo->query("
        SELECT r.*, rt.type_name, rt.type_price 
        FROM room r 
        LEFT JOIN roomtype rt ON r.type_id = rt.type_id 
        WHERE r.room_status = '0' 
        ORDER BY CAST(r.room_number AS UNSIGNED) ASC
        LIMIT 6
    ");
    $availableRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ดึงข่าวประชาสัมพันธ์ (ดึงทั้งหมดเรียงตามวันที่)
$news = [];
try {
    $stmt = $pdo->query("SELECT * FROM news ORDER BY news_date DESC LIMIT 4");
    $news = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // debug: echo $e->getMessage();
}

// นับจำนวนห้องว่าง (room_status = '0' คือห้องว่าง)
$roomStats = ['total' => 0, 'available' => 0];
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM room");
    $roomStats['total'] = (int)$stmt->fetchColumn();
    $stmt = $pdo->query("SELECT COUNT(*) as available FROM room WHERE room_status = '0'");
    $roomStats['available'] = (int)$stmt->fetchColumn();
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteName); ?> - หอพักคุณภาพ</title>
    <link rel="icon" type="image/jpeg" href="Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            color: #fff;
        }
        
        /* Header */
        .header {
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #334155;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .logo img {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            object-fit: cover;
        }
        
        .logo h1 {
            font-size: 1.5rem;
            color: #fff;
        }
        
        .nav-links {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .nav-links a {
            color: #94a3b8;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .nav-links a:hover {
            color: #fff;
            background: #334155;
        }
        
        .btn-login {
            background: <?php echo $themeColor; ?>;
            color: #fff !important;
            padding: 0.75rem 1.5rem !important;
            border-radius: 8px;
            font-weight: 600;
        }
        
        .btn-login:hover {
            opacity: 0.9;
            background: <?php echo $themeColor; ?> !important;
        }
        
        /* Hero Section */
        .hero {
            padding: 8rem 2rem 4rem;
            text-align: center;
            background: linear-gradient(135deg, rgba(30, 64, 175, 0.2) 0%, rgba(15, 23, 42, 0.8) 100%);
        }
        
        .hero h2 {
            font-size: 3rem;
            margin-bottom: 1rem;
            background: linear-gradient(90deg, #60a5fa, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .hero p {
            font-size: 1.25rem;
            color: #94a3b8;
            margin-bottom: 2rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 3rem;
            margin-top: 3rem;
        }
        
        .stat-box {
            text-align: center;
        }
        
        .stat-box .number {
            font-size: 3rem;
            font-weight: 700;
            color: #60a5fa;
        }
        
        .stat-box .label {
            color: #94a3b8;
            font-size: 1rem;
        }
        
        .hero-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }
        
        .btn {
            padding: 1rem 2rem;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: <?php echo $themeColor; ?>;
            color: #fff;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(59, 130, 246, 0.3);
        }
        
        .btn-secondary {
            background: #334155;
            color: #fff;
            border: 1px solid #475569;
        }
        
        .btn-secondary:hover {
            background: #475569;
        }
        
        /* Sections */
        .section {
            padding: 4rem 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .section-title h3 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .section-title p {
            color: #94a3b8;
        }
        
        /* Room Cards */
        .room-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }
        
        .room-card-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            background: #334155;
        }
        
        .room-card-header {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            padding: 1rem 1.5rem;
            text-align: center;
        }
        
        .room-number {
            font-size: 2rem;
            font-weight: 700;
        }
        
        .room-type {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .room-card-body {
            padding: 1.5rem;
        }
        
        .room-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: #22c55e;
            margin-bottom: 1rem;
        }
        
        .room-price span {
            font-size: 0.9rem;
            color: #94a3b8;
            font-weight: 400;
        }
        
        .room-features {
            list-style: none;
            margin-bottom: 1.5rem;
        }
        
        .room-features li {
            padding: 0.5rem 0;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .room-features li::before {
            content: '✓';
            color: #22c55e;
        }
        
        .btn-book {
            width: 100%;
            padding: 0.75rem;
            background: <?php echo $themeColor; ?>;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: block;
            text-align: center;
            transition: all 0.3s;
        }
        
        .btn-book:hover {
            opacity: 0.9;
        }
        
        /* News Section */
        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        
        .news-card {
            background: #1e293b;
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid #334155;
            transition: all 0.3s;
        }
        
        .news-card:hover {
            border-color: #3b82f6;
        }
        
        .news-date {
            color: #60a5fa;
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
        }
        
        .news-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: #fff;
        }
        
        .news-excerpt {
            color: #94a3b8;
            font-size: 0.9rem;
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Quick Links */
        .quick-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .quick-link-card {
            background: #1e293b;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            border: 1px solid #334155;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s;
        }
        
        .quick-link-card:hover {
            transform: translateY(-5px);
            border-color: #3b82f6;
        }
        
        .quick-link-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .quick-link-card h4 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
            color: #fff;
        }
        
        .quick-link-card p {
            color: #94a3b8;
            font-size: 0.9rem;
        }
        
        /* Footer */
        .footer {
            background: #0f172a;
            padding: 3rem 2rem;
            text-align: center;
            border-top: 1px solid #334155;
            margin-top: 4rem;
        }
        
        .footer p {
            color: #64748b;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .hero {
                padding: 7rem 1rem 3rem;
            }
            
            .hero h2 {
                font-size: 2rem;
            }
            
            .hero-stats {
                flex-direction: column;
                gap: 1.5rem;
            }
            
            .hero-buttons {
                flex-direction: column;
            }
            
            .section {
                padding: 2rem 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="logo">
            <img src="Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="<?php echo htmlspecialchars($siteName); ?>">
            <h1><?php echo htmlspecialchars($siteName); ?></h1>
        </div>
        <nav class="nav-links">
            <a href="#rooms">🏠 ห้องพัก</a>
            <a href="#news">📰 ข่าวสาร</a>
            <a href="Public/booking.php">📅 จองห้องพัก</a>
            <a href="Login.php" class="btn-login">🔐 เข้าสู่ระบบ</a>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <h2>🏢 <?php echo htmlspecialchars($siteName); ?></h2>
        <p>หอพักคุณภาพ สะอาด ปลอดภัย ใกล้แหล่งสิ่งอำนวยความสะดวก พร้อมบริการดูแลตลอด 24 ชั่วโมง</p>
        
        <div class="hero-buttons">
            <a href="Public/booking.php" class="btn btn-primary">📅 จองห้องพักเลย</a>
            <a href="#rooms" class="btn btn-secondary">🔍 ดูห้องว่าง</a>
        </div>
        
        <div class="hero-stats">
            <div class="stat-box">
                <div class="number"><?php echo $roomStats['total']; ?></div>
                <div class="label">ห้องพักทั้งหมด</div>
            </div>
            <div class="stat-box">
                <div class="number"><?php echo $roomStats['available']; ?></div>
                <div class="label">ห้องว่าง</div>
            </div>
        </div>
    </section>

    <!-- Quick Links for Public -->
    <section class="section">
        <div class="section-title">
            <h3>🎯 บริการสำหรับบุคคลทั่วไป</h3>
            <p>เลือกบริการที่ต้องการ</p>
        </div>
        
        <div class="quick-links">
            <a href="Public/booking.php" class="quick-link-card">
                <div class="quick-link-icon">📅</div>
                <h4>จองห้องพัก</h4>
                <p>จองห้องพักออนไลน์ ง่าย สะดวก รวดเร็ว</p>
            </a>
            
            <a href="Public/rooms.php" class="quick-link-card">
                <div class="quick-link-icon">🏠</div>
                <h4>รายงานข้อมูลห้องพัก</h4>
                <p>ดูรายละเอียดห้องพัก ประเภท และราคา</p>
            </a>
            
            <a href="Public/news.php" class="quick-link-card">
                <div class="quick-link-icon">📰</div>
                <h4>ข่าวประชาสัมพันธ์</h4>
                <p>ติดตามข่าวสารและประกาศจากหอพัก</p>
            </a>
        </div>
    </section>

    <!-- Available Rooms -->
    <section class="section" id="rooms">
        <div class="section-title">
            <h3>🏠 ห้องพักว่าง</h3>
            <p>ห้องพักที่พร้อมให้เข้าพักได้ทันที</p>
        </div>
        
        <?php if (count($availableRooms) > 0): ?>
        <div class="room-grid">
            <?php foreach ($availableRooms as $room): ?>
            <div class="room-card">
                <?php if (!empty($room['room_image'])): ?>
                <img src="Assets/Images/rooms/<?php echo htmlspecialchars($room['room_image']); ?>" alt="ห้อง <?php echo htmlspecialchars($room['room_number']); ?>" class="room-card-image">
                <?php else: ?>
                <div class="room-card-image" style="display:flex;align-items:center;justify-content:center;font-size:3rem;color:#64748b;">🏠</div>
                <?php endif; ?>
                <div class="room-card-header">
                    <div class="room-number">ห้อง <?php echo htmlspecialchars($room['room_number']); ?></div>
                    <div class="room-type"><?php echo htmlspecialchars($room['type_name'] ?? 'ห้องมาตรฐาน'); ?></div>
                </div>
                <div class="room-card-body">
                    <div class="room-price">
                        ฿<?php echo number_format($room['type_price'] ?? 0); ?> <span>/เดือน</span>
                    </div>
                    <ul class="room-features">
                        <li>ชั้น <?php echo htmlspecialchars($room['room_floor'] ?? '-'); ?></li>
                        <li>เฟอร์นิเจอร์ครบ</li>
                        <li>พร้อมเข้าอยู่</li>
                    </ul>
                    <a href="Public/booking.php?room=<?php echo $room['room_id']; ?>" class="btn-book">📅 จองห้องนี้</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div style="text-align: center; margin-top: 2rem;">
            <a href="Public/rooms.php" class="btn btn-secondary">ดูห้องพักทั้งหมด →</a>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 3rem; background: #1e293b; border-radius: 12px;">
            <p style="color: #94a3b8; font-size: 1.1rem;">😔 ขณะนี้ห้องพักเต็มทุกห้อง</p>
            <p style="color: #64748b; margin-top: 0.5rem;">กรุณาติดต่อสอบถามหรือจองล่วงหน้า</p>
        </div>
        <?php endif; ?>
    </section>

    <!-- News Section -->
    <section class="section" id="news">
        <div class="section-title">
            <h3>📰 ข่าวประชาสัมพันธ์</h3>
            <p>ข่าวสารและประกาศล่าสุดจากหอพัก</p>
        </div>
        
        <?php if (count($news) > 0): ?>
        <div class="news-grid">
            <?php foreach ($news as $item): ?>
            <div class="news-card">
                <div class="news-date">📅 <?php echo date('d/m/Y', strtotime($item['news_date'])); ?></div>
                <div class="news-title"><?php echo htmlspecialchars($item['news_title'] ?? ''); ?></div>
                <div class="news-excerpt"><?php echo htmlspecialchars(strip_tags($item['news_details'] ?? '')); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div style="text-align: center; margin-top: 2rem;">
            <a href="Public/news.php" class="btn btn-secondary">ดูข่าวทั้งหมด →</a>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 3rem; background: #1e293b; border-radius: 12px;">
            <p style="color: #94a3b8;">📭 ยังไม่มีข่าวประชาสัมพันธ์</p>
        </div>
        <?php endif; ?>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <p>© <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName); ?> - สงวนลิขสิทธิ์</p>
    </footer>

</body>
</html>
