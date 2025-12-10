<?php
/**
 * Tenant Report - สัญญาเช่า
 */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$auth = checkTenantAuth();
$pdo = $auth['pdo'];
$token = $auth['token'];
$contract = $auth['contract'];
$settings = getSystemSettings($pdo);

$contractStatusMap = [
    '0' => ['label' => 'ปกติ', 'color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.2)'],
    '1' => ['label' => 'ยกเลิกแล้ว', 'color' => '#ef4444', 'bg' => 'rgba(239, 68, 68, 0.2)'],
    '2' => ['label' => 'แจ้งยกเลิก', 'color' => '#f59e0b', 'bg' => 'rgba(245, 158, 11, 0.2)']
];

// Calculate contract duration
$startDate = new DateTime($contract['ctr_start']);
$endDate = new DateTime($contract['ctr_end']);
$now = new DateTime();
$remainingDays = $now->diff($endDate)->days;
$isExpired = $now > $endDate;
$totalMonths = $startDate->diff($endDate)->m + ($startDate->diff($endDate)->y * 12);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>สัญญาเช่า - <?php echo htmlspecialchars($settings['site_name']); ?></title>
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
        .contract-card {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .contract-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        }
        .contract-number {
            font-size: 0.85rem;
            opacity: 0.8;
            margin-bottom: 0.5rem;
        }
        .contract-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .contract-room {
            font-size: 1rem;
            opacity: 0.9;
        }
        .status-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            background: rgba(255,255,255,0.2);
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
        .remaining-days {
            text-align: center;
            padding: 1.5rem;
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            margin-bottom: 1rem;
        }
        .remaining-number {
            font-size: 3rem;
            font-weight: 700;
            color: <?php echo $isExpired ? '#ef4444' : ($remainingDays < 30 ? '#f59e0b' : '#10b981'); ?>;
        }
        .remaining-label {
            color: #94a3b8;
            font-size: 0.9rem;
        }
        .btn-terminate {
            display: block;
            text-align: center;
            padding: 1rem;
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 12px;
            color: #f87171;
            text-decoration: none;
            font-weight: 600;
            margin-top: 1rem;
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
        .nav-item.active, .nav-item:hover { color: #3b82f6; }
        .nav-icon { font-size: 1.3rem; margin-bottom: 0.25rem; }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <a href="index.php?token=<?php echo urlencode($token); ?>" class="back-btn">←</a>
            <h1 class="header-title">📋 สัญญาเช่า</h1>
        </div>
    </header>
    
    <div class="container">
        <!-- Contract Card -->
        <div class="contract-card">
            <span class="status-badge" style="background: <?php echo $contractStatusMap[$contract['ctr_status'] ?? '0']['bg']; ?>; color: <?php echo $contractStatusMap[$contract['ctr_status'] ?? '0']['color']; ?>">
                <?php echo $contractStatusMap[$contract['ctr_status'] ?? '0']['label']; ?>
            </span>
            <div class="contract-number">สัญญาเลขที่ #<?php echo $contract['ctr_id']; ?></div>
            <div class="contract-title"><?php echo htmlspecialchars($contract['tnt_name']); ?></div>
            <div class="contract-room">ห้อง <?php echo htmlspecialchars($contract['room_number']); ?> - <?php echo htmlspecialchars($contract['type_name'] ?? '-'); ?></div>
        </div>
        
        <!-- Remaining Days -->
        <div class="remaining-days">
            <div class="remaining-number">
                <?php echo $isExpired ? 'หมดอายุ' : $remainingDays; ?>
            </div>
            <div class="remaining-label">
                <?php echo $isExpired ? 'สัญญาหมดอายุแล้ว' : 'วันที่เหลือ'; ?>
            </div>
        </div>
        
        <!-- Contract Details -->
        <div class="info-card">
            <div class="info-card-title">📅 ระยะเวลาสัญญา</div>
            <div class="info-row">
                <span class="info-label">วันที่เริ่มต้น</span>
                <span class="info-value"><?php echo $contract['ctr_start'] ?? '-'; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">วันที่สิ้นสุด</span>
                <span class="info-value"><?php echo $contract['ctr_end'] ?? '-'; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">ระยะเวลา</span>
                <span class="info-value"><?php echo $totalMonths; ?> เดือน</span>
            </div>
        </div>
        
        <div class="info-card">
            <div class="info-card-title">💰 ค่าใช้จ่าย</div>
            <div class="info-row">
                <span class="info-label">ค่าเช่า/เดือน</span>
                <span class="info-value"><?php echo number_format($contract['type_price'] ?? 0); ?> บาท</span>
            </div>
            <div class="info-row">
                <span class="info-label">เงินมัดจำ</span>
                <span class="info-value"><?php echo number_format($contract['ctr_deposit'] ?? 0); ?> บาท</span>
            </div>
        </div>
        
        <div class="info-card">
            <div class="info-card-title">👤 ข้อมูลผู้เช่า</div>
            <div class="info-row">
                <span class="info-label">ชื่อ-นามสกุล</span>
                <span class="info-value"><?php echo htmlspecialchars($contract['tnt_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">เลขบัตรประชาชน</span>
                <span class="info-value"><?php echo htmlspecialchars($contract['tnt_id']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">เบอร์โทรศัพท์</span>
                <span class="info-value"><?php echo htmlspecialchars($contract['tnt_phone'] ?? '-'); ?></span>
            </div>
        </div>
        
        <?php if ($contract['ctr_status'] === '0'): ?>
        <a href="termination.php?token=<?php echo urlencode($token); ?>" class="btn-terminate">📄 แจ้งยกเลิกสัญญา</a>
        <?php endif; ?>
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
