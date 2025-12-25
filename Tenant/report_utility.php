<?php
/**
 * Tenant Report - ค่าน้ำ-ค่าไฟ
 */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$auth = checkTenantAuth();
$pdo = $auth['pdo'];
$token = $auth['token'];
$contract = $auth['contract'];
$settings = getSystemSettings($pdo);

// Get utility data for this contract
$utilities = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.*, e.exp_month, e.exp_elec_unit, e.exp_water_unit, e.exp_elec_chg, e.exp_water, e.rate_elec, e.rate_water
        FROM utility u
        LEFT JOIN expense e ON u.ctr_id = e.ctr_id AND DATE_FORMAT(u.utl_date, '%Y-%m') = DATE_FORMAT(e.exp_month, '%Y-%m')
        WHERE u.ctr_id = ?
        ORDER BY u.utl_date DESC
    ");
    $stmt->execute([$contract['ctr_id']]);
    $utilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Get expense data if no utility records
if (empty($utilities)) {
    try {
        $stmt = $pdo->prepare("
            SELECT *, exp_month as utl_date
            FROM expense
            WHERE ctr_id = ?
            ORDER BY exp_month DESC
        ");
        $stmt->execute([$contract['ctr_id']]);
        $utilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ค่าน้ำ-ค่าไฟ - <?php echo htmlspecialchars($settings['site_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/jpeg" href="..//Assets/Images/<?php echo htmlspecialchars($settings['logo_filename']); ?>">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
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
        .back-btn {
            color: #94a3b8;
            text-decoration: none;
            font-size: 1.5rem;
            padding: 0.5rem;
        }
        .header-title { font-size: 1.1rem; color: #f8fafc; }
        .container { max-width: 600px; margin: 0 auto; padding: 1rem; }
        .utility-card {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 1rem;
        }
        .utility-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .utility-month {
            font-size: 1rem;
            font-weight: 600;
            color: #f8fafc;
        }
        .utility-date {
            font-size: 0.75rem;
            color: #64748b;
        }
        .utility-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        .utility-item {
            background: rgba(15, 23, 42, 0.5);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
        }
        .utility-item.electric { border-left: 3px solid #f59e0b; }
        .utility-item.water { border-left: 3px solid #3b82f6; }
        .utility-icon { font-size: 1.5rem; margin-bottom: 0.5rem; }
        .utility-label { font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.25rem; }
        .utility-value { font-size: 1.3rem; font-weight: 700; }
        .utility-item.electric .utility-value { color: #f59e0b; }
        .utility-item.water .utility-value { color: #3b82f6; }
        .utility-detail { font-size: 0.7rem; color: #64748b; margin-top: 0.25rem; }
        .utility-summary {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
            margin-top: 1rem;
            padding-top: 0.75rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
        }
        .summary-label { color: #94a3b8; }
        .summary-value { color: #f8fafc; font-weight: 500; }
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #64748b;
        }
        .empty-state-icon { font-size: 4rem; margin-bottom: 1rem; }
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
        .nav-icon svg {
            width: 22px;
            height: 22px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }
        .section-icon svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }
        .empty-state-icon svg {
            width: 48px;
            height: 48px;
            stroke: #64748b;
            stroke-width: 1.5;
            fill: none;
        }
        .date-icon svg {
            width: 14px;
            height: 14px;
            stroke: #f8fafc;
            stroke-width: 2;
            fill: none;
            margin-right: 4px;
        }
        .utility-icon svg {
            width: 24px;
            height: 24px;
            stroke-width: 2;
            fill: none;
        }
        .utility-item.electric .utility-icon svg { stroke: #f59e0b; }
        .utility-item.water .utility-icon svg { stroke: #3b82f6; }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <a href="index.php?token=<?php echo urlencode($token); ?>" class="back-btn">←</a>
            <h1 class="header-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 0 1 8.91 14"/></svg></span> ค่าน้ำ-ค่าไฟ</h1>
        </div>
    </header>
    
    <div class="container">
        <?php if (empty($utilities)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 12H16c-.7 2-2 3-4 3s-3.3-1-4-3H2.5"/><path d="M5.5 5.1L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.4-6.9A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.8 1.1z"/></svg></div>
            <p>ยังไม่มีข้อมูลค่าน้ำ-ค่าไฟ</p>
        </div>
        <?php else: ?>
        <?php foreach ($utilities as $util): ?>
        <div class="utility-card">
            <div class="utility-header">
                <span class="utility-month"><span class="date-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span> <?php echo date('F Y', strtotime($util['utl_date'] ?? $util['exp_month'])); ?></span>
                <span class="utility-date">จดเมื่อ <?php echo $util['utl_date'] ?? $util['exp_month']; ?></span>
            </div>
            <div class="utility-grid">
                <div class="utility-item electric">
                    <div class="utility-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></div>
                    <div class="utility-label">ค่าไฟฟ้า</div>
                    <div class="utility-value"><?php echo number_format($util['exp_elec_chg'] ?? 0); ?> ฿</div>
                    <div class="utility-detail"><?php echo $util['exp_elec_unit'] ?? 0; ?> หน่วย × <?php echo $util['rate_elec'] ?? 0; ?> บาท</div>
                </div>
                <div class="utility-item water">
                    <div class="utility-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg></div>
                    <div class="utility-label">ค่าน้ำประปา</div>
                    <div class="utility-value"><?php echo number_format($util['exp_water'] ?? 0); ?> ฿</div>
                    <div class="utility-detail"><?php echo $util['exp_water_unit'] ?? 0; ?> หน่วย × <?php echo $util['rate_water'] ?? 0; ?> บาท</div>
                </div>
            </div>
            <?php if (isset($util['utl_elec_start']) || isset($util['utl_water_start'])): ?>
            <div class="utility-summary">
                <?php if (isset($util['utl_elec_start'])): ?>
                <div class="summary-item">
                    <span class="summary-label">มิเตอร์ไฟเริ่ม</span>
                    <span class="summary-value"><?php echo number_format($util['utl_elec_start']); ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">มิเตอร์ไฟสิ้นสุด</span>
                    <span class="summary-value"><?php echo number_format($util['utl_elec_end'] ?? 0); ?></span>
                </div>
                <?php endif; ?>
                <?php if (isset($util['utl_water_start'])): ?>
                <div class="summary-item">
                    <span class="summary-label">มิเตอร์น้ำเริ่ม</span>
                    <span class="summary-value"><?php echo number_format($util['utl_water_start']); ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">มิเตอร์น้ำสิ้นสุด</span>
                    <span class="summary-value"><?php echo number_format($util['utl_water_end'] ?? 0); ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <nav class="bottom-nav">
        <div class="bottom-nav-content">
            <a href="index.php?token=<?php echo urlencode($token); ?>" class="nav-item">
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
