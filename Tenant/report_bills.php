<?php
/**
 * Tenant Report - บิลค่าใช้จ่าย
 */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$auth = checkTenantAuth();
$pdo = $auth['pdo'];
$token = $auth['token'];
$contract = $auth['contract'];
$settings = getSystemSettings($pdo);

// Get all expenses for this contract
$expenses = [];
try {
    $stmt = $pdo->prepare("
        SELECT e.*, 
               (SELECT COUNT(*) FROM payment p WHERE p.exp_id = e.exp_id) as payment_count,
               (SELECT p.pay_status FROM payment p WHERE p.exp_id = e.exp_id ORDER BY p.pay_date DESC LIMIT 1) as last_payment_status
        FROM expense e
        WHERE e.ctr_id = ?
        ORDER BY e.exp_month DESC
    ");
    $stmt->execute([$contract['ctr_id']]);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$expenseStatusMap = [
    '0' => ['label' => 'ค้างชำระ', 'color' => '#ef4444', 'bg' => 'rgba(239, 68, 68, 0.2)'],
    '1' => ['label' => 'ชำระแล้ว', 'color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.2)']
];

// Calculate totals
$totalPaid = 0;
$totalUnpaid = 0;
foreach ($expenses as $exp) {
    if ($exp['exp_status'] === '1') {
        $totalPaid += $exp['exp_total'];
    } else {
        $totalUnpaid += $exp['exp_total'];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>บิลค่าใช้จ่าย - <?php echo htmlspecialchars($settings['site_name']); ?></title>
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
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .summary-card {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
        }
        .summary-card.unpaid { border-color: rgba(239, 68, 68, 0.3); }
        .summary-card.paid { border-color: rgba(16, 185, 129, 0.3); }
        .summary-label { font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.25rem; }
        .summary-value { font-size: 1.3rem; font-weight: 700; }
        .summary-card.unpaid .summary-value { color: #ef4444; }
        .summary-card.paid .summary-value { color: #10b981; }
        .bill-card {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 1rem;
        }
        .bill-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .bill-month {
            font-size: 1rem;
            font-weight: 600;
            color: #f8fafc;
        }
        .bill-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .bill-details { display: grid; gap: 0.5rem; }
        .bill-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
        }
        .bill-label { color: #94a3b8; }
        .bill-value { color: #e2e8f0; }
        .bill-total {
            display: flex;
            justify-content: space-between;
            padding-top: 0.75rem;
            margin-top: 0.75rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            font-weight: 600;
        }
        .bill-total .bill-value { font-size: 1.1rem; color: #3b82f6; }
        .btn-pay {
            display: block;
            text-align: center;
            padding: 0.75rem;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 8px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
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
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <a href="index.php?token=<?php echo urlencode($token); ?>" class="back-btn">←</a>
            <h1 class="header-title">🧾 บิลค่าใช้จ่าย</h1>
        </div>
    </header>
    
    <div class="container">
        <!-- Summary -->
        <div class="summary-grid">
            <div class="summary-card unpaid">
                <div class="summary-label">ค้างชำระ</div>
                <div class="summary-value"><?php echo number_format($totalUnpaid); ?> ฿</div>
            </div>
            <div class="summary-card paid">
                <div class="summary-label">ชำระแล้ว</div>
                <div class="summary-value"><?php echo number_format($totalPaid); ?> ฿</div>
            </div>
        </div>
        
        <?php if (empty($expenses)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📭</div>
            <p>ยังไม่มีบิลค่าใช้จ่าย</p>
        </div>
        <?php else: ?>
        <?php foreach ($expenses as $exp): ?>
        <div class="bill-card">
            <div class="bill-header">
                <span class="bill-month">📅 <?php echo date('F Y', strtotime($exp['exp_month'])); ?></span>
                <span class="bill-status" style="background: <?php echo $expenseStatusMap[$exp['exp_status'] ?? '0']['bg']; ?>; color: <?php echo $expenseStatusMap[$exp['exp_status'] ?? '0']['color']; ?>">
                    <?php echo $expenseStatusMap[$exp['exp_status'] ?? '0']['label']; ?>
                </span>
            </div>
            <div class="bill-details">
                <div class="bill-row">
                    <span class="bill-label">ค่าห้อง</span>
                    <span class="bill-value"><?php echo number_format($exp['room_price'] ?? 0); ?> บาท</span>
                </div>
                <div class="bill-row">
                    <span class="bill-label">ค่าไฟ (<?php echo $exp['exp_elec_unit'] ?? 0; ?> หน่วย × <?php echo $exp['rate_elec'] ?? 0; ?> บาท)</span>
                    <span class="bill-value"><?php echo number_format($exp['exp_elec_chg'] ?? 0); ?> บาท</span>
                </div>
                <div class="bill-row">
                    <span class="bill-label">ค่าน้ำ (<?php echo $exp['exp_water_unit'] ?? 0; ?> หน่วย × <?php echo $exp['rate_water'] ?? 0; ?> บาท)</span>
                    <span class="bill-value"><?php echo number_format($exp['exp_water'] ?? 0); ?> บาท</span>
                </div>
                <div class="bill-total">
                    <span class="bill-label">รวมทั้งสิ้น</span>
                    <span class="bill-value"><?php echo number_format($exp['exp_total'] ?? 0); ?> บาท</span>
                </div>
            </div>
            <?php if ($exp['exp_status'] === '0'): ?>
            <a href="payment.php?token=<?php echo urlencode($token); ?>" class="btn-pay">💳 ชำระเงิน</a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <nav class="bottom-nav">
        <div class="bottom-nav-content">
            <a href="index.php?token=<?php echo urlencode($token); ?>" class="nav-item">
                <div class="nav-icon">🏠</div>
                หน้าหลัก
            </a>
            <a href="report_bills.php?token=<?php echo urlencode($token); ?>" class="nav-item active">
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
