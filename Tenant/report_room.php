<?php
/**
 * Tenant Report - ข้อมูลห้องพัก
 */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$auth = checkTenantAuth();
$pdo = $auth['pdo'];
$token = $auth['token'];
$contract = $auth['contract'];
$settings = getSystemSettings($pdo);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ข้อมูลห้องพัก - <?php echo htmlspecialchars($settings['site_name']); ?></title>
    <link rel="icon" type="image/jpeg" href="../Assets/Images/<?php echo htmlspecialchars($settings['logo_filename']); ?>">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Sarabun', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
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
        .back-btn {
            color: #94a3b8;
            text-decoration: none;
            font-size: 1.5rem;
            padding: 0.5rem;
        }
        .header-title { font-size: 1.1rem; color: #f8fafc; }
        .container { max-width: 600px; margin: 0 auto; padding: 1rem; }
        .room-image {
            width: 100%;
            height: 200px;
            background: rgba(30, 41, 59, 0.8);
            border-radius: 16px;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        .room-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .room-image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: #475569;
        }
        .info-card {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 1rem;
        }
        .info-card-title {
            font-size: 1rem;
            color: #f8fafc;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #94a3b8; font-size: 0.85rem; }
        .info-value { color: #f8fafc; font-size: 0.9rem; font-weight: 500; text-align: right; }
        .highlight-value { color: #3b82f6; font-size: 1.1rem; }
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
        .nav-item.active, .nav-item:hover { color: #3b82f6; }
        .nav-icon { font-size: 1.3rem; margin-bottom: 0.25rem; }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <a href="index.php?token=<?php echo urlencode($token); ?>" class="back-btn">←</a>
            <h1 class="header-title">🏠 ข้อมูลห้องพัก</h1>
        </div>
    </header>
    
    <div class="container">
        <!-- Room Image -->
        <div class="room-image">
            <?php if (!empty($contract['room_image'])): ?>
            <img src="../Assets/Images/<?php echo htmlspecialchars($contract['room_image']); ?>" alt="Room Image">
            <?php else: ?>
            <div class="room-image-placeholder">🏠</div>
            <?php endif; ?>
        </div>
        
        <!-- Room Info -->
        <div class="info-card">
            <div class="info-card-title">🏠 ข้อมูลห้องพัก</div>
            <div class="info-row">
                <span class="info-label">หมายเลขห้อง</span>
                <span class="info-value highlight-value"><?php echo htmlspecialchars($contract['room_number']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">ประเภทห้อง</span>
                <span class="info-value"><?php echo htmlspecialchars($contract['type_name'] ?? '-'); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">ค่าเช่า/เดือน</span>
                <span class="info-value highlight-value"><?php echo number_format($contract['type_price'] ?? 0); ?> บาท</span>
            </div>
        </div>
        
        <!-- Tenant Info -->
        <div class="info-card">
            <div class="info-card-title">👤 ผู้เช่า</div>
            <div class="info-row">
                <span class="info-label">ชื่อ-นามสกุล</span>
                <span class="info-value"><?php echo htmlspecialchars($contract['tnt_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">เบอร์โทรศัพท์</span>
                <span class="info-value"><?php echo htmlspecialchars($contract['tnt_phone'] ?? '-'); ?></span>
            </div>
        </div>
        
        <!-- Contract Info -->
        <div class="info-card">
            <div class="info-card-title">📋 สัญญาเช่า</div>
            <div class="info-row">
                <span class="info-label">เลขที่สัญญา</span>
                <span class="info-value">#<?php echo $contract['ctr_id']; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">วันที่เริ่มสัญญา</span>
                <span class="info-value"><?php echo $contract['ctr_start'] ?? '-'; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">วันที่สิ้นสุด</span>
                <span class="info-value"><?php echo $contract['ctr_end'] ?? '-'; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">เงินมัดจำ</span>
                <span class="info-value"><?php echo number_format($contract['ctr_deposit'] ?? 0); ?> บาท</span>
            </div>
        </div>
    </div>
    
    <nav class="bottom-nav">
        <div class="bottom-nav-content">
            <a href="index.php?token=<?php echo urlencode($token); ?>" class="nav-item">
                <div class="nav-icon">🏠</div>
                หน้าหลัก
            </a>
            <a href="report_bills.php?token=<?php echo urlencode($token); ?>" class="nav-item">
                <div class="nav-icon">🧾</div>
                บิล
            </a>
            <a href="repair.php?token=<?php echo urlencode($token); ?>" class="nav-item">
                <div class="nav-icon">🔧</div>
                แจ้งซ่อม
            </a>
            <a href="profile.php?token=<?php echo urlencode($token); ?>" class="nav-item">
                <div class="nav-icon">👤</div>
                โปรไฟล์
            </a>
        </div>
    </nav>
</body>
</html>
