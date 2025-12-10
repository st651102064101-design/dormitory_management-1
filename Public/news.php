<?php
declare(strict_types=1);
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
} catch (PDOException $e) {}

// ดึงข่าวทั้งหมด (เรียงตามวันที่ล่าสุด)
$news = [];
try {
    $stmt = $pdo->query("
        SELECT * FROM news 
        ORDER BY news_date DESC
    ");
    $news = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข่าวประชาสัมพันธ์ - <?php echo htmlspecialchars($siteName); ?></title>
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
            max-width: 900px;
            margin: 0 auto;
            padding: 6rem 1rem 2rem;
        }
        
        .page-title {
            text-align: center;
            margin-bottom: 2rem;
        }
        .page-title h2 { font-size: 2rem; margin-bottom: 0.5rem; }
        .page-title p { color: #94a3b8; }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #60a5fa;
            text-decoration: none;
            margin-bottom: 2rem;
        }
        .back-link:hover { text-decoration: underline; }
        
        .news-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .news-card {
            background: #1e293b;
            border-radius: 16px;
            padding: 2rem;
            border: 1px solid #334155;
            transition: all 0.3s;
        }
        .news-card:hover {
            border-color: #3b82f6;
        }
        
        .news-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .news-date {
            color: #60a5fa;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .news-category {
            background: #3b82f620;
            color: #60a5fa;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .news-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #fff;
            line-height: 1.4;
        }
        
        .news-content {
            color: #94a3b8;
            line-height: 1.8;
            font-size: 1rem;
        }
        
        .news-content p {
            margin-bottom: 1rem;
        }
        
        .news-image {
            width: 100%;
            max-height: 400px;
            object-fit: cover;
            border-radius: 12px;
            margin: 1rem 0;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: #1e293b;
            border-radius: 16px;
        }
        .empty-state p {
            color: #94a3b8;
            font-size: 1.1rem;
        }
        
        @media (max-width: 768px) {
            .header { flex-direction: column; gap: 1rem; padding: 1rem; }
            .nav-links { flex-wrap: wrap; justify-content: center; }
            .news-card { padding: 1.5rem; }
            .news-title { font-size: 1.25rem; }
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
            <h2>📰 ข่าวประชาสัมพันธ์</h2>
            <p>ข่าวสารและประกาศจากหอพัก</p>
        </div>
        
        <?php if (count($news) > 0): ?>
        <div class="news-list">
            <?php foreach ($news as $item): ?>
            <article class="news-card">
                <div class="news-header">
                    <div class="news-date">
                        📅 <?php echo date('d/m/Y', strtotime($item['news_date'])); ?>
                        <?php if (!empty($item['news_time'])): ?>
                        - <?php echo date('H:i', strtotime($item['news_time'])); ?> น.
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($item['news_category'])): ?>
                    <span class="news-category"><?php echo htmlspecialchars($item['news_category']); ?></span>
                    <?php endif; ?>
                </div>
                
                <h3 class="news-title"><?php echo htmlspecialchars($item['news_title'] ?? ''); ?></h3>
                
                <div class="news-content">
                    <?php echo nl2br(htmlspecialchars($item['news_details'] ?? '')); ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <p>📭 ยังไม่มีข่าวประชาสัมพันธ์</p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
