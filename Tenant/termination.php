<?php
/**
 * Tenant Termination - แจ้งยกเลิกสัญญา
 */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$auth = checkTenantAuth();
$pdo = $auth['pdo'];
$token = $auth['token'];
$contract = $auth['contract'];
$settings = getSystemSettings($pdo);

$success = '';
$error = '';

// Check if already requested termination
$hasTermination = false;
$termination = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM termination WHERE ctr_id = ? ORDER BY term_date DESC LIMIT 1");
    $stmt->execute([$contract['ctr_id']]);
    $termination = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($termination) {
        $hasTermination = true;
    }
} catch (PDOException $e) {}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$hasTermination) {
    try {
        $term_date = $_POST['term_date'] ?? '';
        
        if (empty($term_date)) {
            $error = 'กรุณาระบุวันที่ต้องการยกเลิกสัญญา';
        } else {
            // Insert termination request
            $stmt = $pdo->prepare("INSERT INTO termination (ctr_id, term_date) VALUES (?, ?)");
            $stmt->execute([$contract['ctr_id'], $term_date]);
            
            // Update contract status to "แจ้งยกเลิก" (2)
            $updateStmt = $pdo->prepare("UPDATE contract SET ctr_status = '2' WHERE ctr_id = ?");
            $updateStmt->execute([$contract['ctr_id']]);
            
            $success = 'ส่งคำร้องแจ้งยกเลิกสัญญาเรียบร้อยแล้ว';
            $hasTermination = true;
            
            // Refresh termination data
            $stmt = $pdo->prepare("SELECT * FROM termination WHERE ctr_id = ? ORDER BY term_date DESC LIMIT 1");
            $stmt->execute([$contract['ctr_id']]);
            $termination = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
    } catch (Exception $e) {
        $error = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }
}

$contractStatusMap = [
    '0' => ['label' => 'ปกติ', 'color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.2)'],
    '1' => ['label' => 'ยกเลิกแล้ว', 'color' => '#ef4444', 'bg' => 'rgba(239, 68, 68, 0.2)'],
    '2' => ['label' => 'แจ้งยกเลิก', 'color' => '#f59e0b', 'bg' => 'rgba(245, 158, 11, 0.2)']
];

// Calculate minimum date (7 days from now)
$minDate = date('Y-m-d', strtotime('+7 days'));
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>แจ้งยกเลิกสัญญา - <?php echo htmlspecialchars($settings['site_name']); ?></title>
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
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .contract-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .contract-title { font-size: 1rem; color: #f8fafc; font-weight: 600; }
        .contract-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .contract-info { display: grid; gap: 0.75rem; }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #94a3b8; font-size: 0.85rem; }
        .info-value { color: #f8fafc; font-size: 0.9rem; font-weight: 500; }
        .form-section {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .section-title {
            font-size: 1rem;
            color: #f8fafc;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .form-group { margin-bottom: 1rem; }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            color: #94a3b8;
            margin-bottom: 0.5rem;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: #f8fafc;
            font-size: 0.95rem;
            font-family: inherit;
        }
        .form-group input:focus {
            outline: none;
            border-color: #3b82f6;
        }
        .warning-box {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .warning-box h4 {
            color: #f87171;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .warning-box ul {
            margin-left: 1.5rem;
            font-size: 0.85rem;
            color: #fca5a5;
        }
        .warning-box li { margin-bottom: 0.25rem; }
        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }
        .btn-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
        }
        .termination-status {
            background: rgba(245, 158, 11, 0.2);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
        }
        .termination-status h3 {
            color: #fbbf24;
            margin-bottom: 0.5rem;
        }
        .termination-status p { color: #fcd34d; }
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
            <h1 class="header-title">📄 แจ้งยกเลิกสัญญา</h1>
        </div>
    </header>
    
    <div class="container">
        <?php if ($success): ?>
        <div class="alert alert-success">
            <span>✅</span>
            <span><?php echo htmlspecialchars($success); ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-error">
            <span>❌</span>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
        <?php endif; ?>
        
        <!-- Current Contract Info -->
        <div class="contract-card">
            <div class="contract-header">
                <span class="contract-title">📋 สัญญาปัจจุบัน</span>
                <span class="contract-status" style="background: <?php echo $contractStatusMap[$contract['ctr_status'] ?? '0']['bg']; ?>; color: <?php echo $contractStatusMap[$contract['ctr_status'] ?? '0']['color']; ?>">
                    <?php echo $contractStatusMap[$contract['ctr_status'] ?? '0']['label']; ?>
                </span>
            </div>
            <div class="contract-info">
                <div class="info-row">
                    <span class="info-label">ห้องพัก</span>
                    <span class="info-value"><?php echo htmlspecialchars($contract['room_number']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">วันที่เริ่มสัญญา</span>
                    <span class="info-value"><?php echo $contract['ctr_start'] ?? '-'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">วันที่สิ้นสุดสัญญา</span>
                    <span class="info-value"><?php echo $contract['ctr_end'] ?? '-'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">เงินมัดจำ</span>
                    <span class="info-value"><?php echo number_format($contract['ctr_deposit'] ?? 0); ?> บาท</span>
                </div>
            </div>
        </div>
        
        <?php if ($hasTermination): ?>
        <!-- Already requested termination -->
        <div class="termination-status">
            <h3>⏳ รออนุมัติการยกเลิกสัญญา</h3>
            <p>วันที่ต้องการย้ายออก: <?php echo $termination['term_date'] ?? '-'; ?></p>
        </div>
        <?php else: ?>
        <!-- Termination Form -->
        <div class="form-section">
            <div class="section-title">📝 แจ้งยกเลิกสัญญา</div>
            
            <div class="warning-box">
                <h4>⚠️ ข้อควรทราบ</h4>
                <ul>
                    <li>กรุณาแจ้งล่วงหน้าอย่างน้อย 7 วัน</li>
                    <li>ต้องชำระค่าใช้จ่ายค้างทั้งหมดก่อนย้ายออก</li>
                    <li>เงินมัดจำจะคืนหลังตรวจสอบห้องพักเรียบร้อย</li>
                    <li>หากมีความเสียหายจะหักจากเงินมัดจำ</li>
                </ul>
            </div>
            
            <form method="POST" onsubmit="return confirmTermination()">
                <div class="form-group">
                    <label>วันที่ต้องการย้ายออก *</label>
                    <input type="date" name="term_date" min="<?php echo $minDate; ?>" required>
                </div>
                
                <button type="submit" class="btn-submit">📤 ส่งคำร้องยกเลิกสัญญา</button>
            </form>
        </div>
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
    
    <script>
    function confirmTermination() {
        return confirm('⚠️ คุณแน่ใจหรือไม่ที่จะยกเลิกสัญญา?\n\nการดำเนินการนี้ไม่สามารถย้อนกลับได้');
    }
    </script>
</body>
</html>
