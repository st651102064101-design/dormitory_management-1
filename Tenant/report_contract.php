<?php
/**
 * Tenant Report - สัญญาเช่า
 */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/thai_date_helper.php';

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

$terminationAllowed = false;
$terminationReason = '';

try {
    $termCheckStmt = $pdo->prepare("
        SELECT 
           (
              SELECT CASE WHEN COALESCE(step_5_confirmed, 0) = 1 OR COALESCE(current_step, 0) >= 5 THEN 1 ELSE 0 END
              FROM tenant_workflow
              WHERE tnt_id = c.tnt_id
              ORDER BY id DESC LIMIT 1
           ) AS is_step5_complete,
           (
              SELECT COUNT(*)
              FROM expense e
              WHERE e.ctr_id = c.ctr_id
                AND DATE_FORMAT(e.exp_month, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
           ) AS has_current_month_bill,
           (
              SELECT COUNT(*)
              FROM expense e
              WHERE e.ctr_id = c.ctr_id
                AND e.exp_total > COALESCE((
                    SELECT SUM(p.pay_amount) FROM payment p
                    WHERE p.exp_id = e.exp_id AND p.pay_status = '1'
                ), 0)
           ) AS unpaid_bills_count,
           (
              SELECT COUNT(*)
              FROM payment p
              JOIN expense e ON p.exp_id = e.exp_id
              WHERE e.ctr_id = c.ctr_id AND p.pay_status = '0'
           ) AS unverified_payments_count
        FROM contract c
        WHERE c.ctr_id = ?
    ");
    $termCheckStmt->execute([$contract['ctr_id']]);
    $termData = $termCheckStmt->fetch(PDO::FETCH_ASSOC);

    if ($termData) {
        if ((int)$termData['is_step5_complete'] !== 1) {
            $terminationReason = 'รอเจ้าหน้าที่ตรวจสอบการเข้าพักและสร้างรอบบิลเดือนแรกให้เรียบร้อย จึงจะสามารถใช้งานระบบนี้ได้';
        } elseif ((int)$termData['has_current_month_bill'] === 0) {
            $terminationReason = 'กรุณารอให้เจ้าหน้าที่จดมิเตอร์และออกบิลค่าใช้จ่ายของเดือนล่าสุดให้เรียบร้อยก่อนแจ้งยกเลิกสัญญา';
        } elseif ((int)$termData['unpaid_bills_count'] > 0) {
            $terminationReason = 'ไม่สามารถแจ้งยกเลิกสัญญาได้ เนื่องจากมียอดค้างชำระจำนวน ' . $termData['unpaid_bills_count'] . ' รายการ หรือมีบิลใหม่ที่เพิ่งออก กรุณาชำระค่าห้องให้ครบก่อน';
        } elseif ((int)$termData['unverified_payments_count'] > 0) {
            $terminationReason = 'มีสลิปการชำระเงินที่รอให้เจ้าหน้าที่ตรวจสอบ กรุณารอเจ้าหน้าที่ตรวจสอบความถูกต้องก่อนจึงจะสามารถแจ้งยกเลิกสัญญาได้';
        } else {
            $terminationAllowed = true;
        }
    }
} catch (PDOException $e) { 
    error_log("PDOException checking termination eligibility: " . $e->getMessage()); 
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>สัญญาเช่า - <?php echo htmlspecialchars($settings['site_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($settings['logo_filename']); ?>">
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
            position: relative;
        }
        .nav-item.active, .nav-item:hover { color: #3b82f6; }
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
        .btn-icon svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
            margin-right: 4px;
        }
    </style>
    <?php if (($settings['public_theme'] ?? '') === 'light'): ?>
    <link rel="stylesheet" href="tenant-light-theme.css">
    <?php endif; ?>
</head>
<body class="<?= ($settings['public_theme'] ?? '') === 'light' ? 'light-theme' : '' ?>">
    <header class="header">
        <div class="header-content">
            <a href="index.php?token=<?php echo urlencode($token); ?>" class="back-btn">←</a>
            <h1 class="header-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span> สัญญาเช่า</h1>
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
            <div class="info-card-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span> ระยะเวลาสัญญา</div>
            <div class="info-row">
                <span class="info-label">วันที่เริ่มต้น</span>
                <span class="info-value"><?php echo !empty($contract['ctr_start']) ? thaiDate($contract['ctr_start'], 'long') : '-'; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">วันที่สิ้นสุด</span>
                <span class="info-value"><?php echo !empty($contract['ctr_end']) ? thaiDate($contract['ctr_end'], 'long') : '-'; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">ระยะเวลา</span>
                <span class="info-value"><?php echo $totalMonths; ?> เดือน</span>
            </div>
        </div>
        
        <div class="info-card">
            <div class="info-card-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span> ค่าใช้จ่าย</div>
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
            <div class="info-card-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span> ข้อมูลผู้เช่า</div>
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
            <?php if (!$terminationAllowed): ?>
            <a href="#" onclick="alert('<?= htmlspecialchars($terminationReason, ENT_QUOTES, 'UTF-8') ?>'); return false;" class="btn-terminate" style="opacity: 0.5;"><span class="btn-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/></svg></span> แจ้งยกเลิกสัญญา</a>
            <?php else: ?>
            <a href="termination.php?token=<?php echo urlencode($token); ?>" class="btn-terminate"><span class="btn-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/></svg></span> แจ้งยกเลิกสัญญา</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <?php
    $repairCount = 0;
    try {
        $repairStmt = $pdo->prepare("SELECT COUNT(*) FROM repair WHERE ctr_id IN (SELECT ctr_id FROM contract WHERE tnt_id = ?) AND repair_status = '0'");
        $repairStmt->execute([$contract['tnt_id']]);
        $repairCount = (int)($repairStmt->fetchColumn() ?? 0);
    } catch (Exception $e) { error_log("Exception in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }
    $homeBadgeCount = 0;
    try {
        $homeBadgeStmt = $pdo->prepare("
            SELECT 1 
            FROM contract c
            LEFT JOIN signature_logs sl ON c.ctr_id = sl.contract_id AND sl.signer_type = 'tenant'
            WHERE c.ctr_id = ? AND c.ctr_status != '1' AND sl.id IS NULL
              AND (
                  SELECT step_3_confirmed 
                  FROM tenant_workflow 
                  WHERE tnt_id = c.tnt_id 
                  ORDER BY id DESC LIMIT 1
              ) = 1
            LIMIT 1
        ");
        $homeBadgeStmt->execute([$contract['ctr_id'] ?? 0]);
        if ($homeBadgeStmt->fetchColumn()) {
            $homeBadgeCount = 1;
        }
    } catch (Exception $e) { error_log("Exception calculating home badge count in " . __FILE__ . ": " . $e->getMessage()); }

    $billCount = getTenantBillBadgeCount($pdo, $contract);
    ?>
    <nav class="bottom-nav">
        <div class="bottom-nav-content">
            <a href="index.php?token=<?php echo urlencode($token); ?>" class="nav-item">
                <div class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
                                หน้าหลัก<?php if ($homeBadgeCount > 0): ?><span class="nav-badge">1</span><?php endif; ?>
            </a>
            <a href="report_bills.php?token=<?php echo urlencode($token); ?>&_ts=<?php echo time(); ?>" class="nav-item">
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
</body>
</html>
