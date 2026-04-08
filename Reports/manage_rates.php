<?php
/**
 * Manage Rates - หน้าจัดการค่าน้ำค่าไฟ
 */

declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    header("Location: ../Login.php");
    exit;
}

require_once '../ConnectDB.php';
$pdo = connectDB();

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_rate') {
    $waterRate = (float)$_POST['water_rate'];
    $elecRate = (float)$_POST['elec_rate'];
    $waterBaseUnits = isset($_POST['water_base_units']) ? (int)$_POST['water_base_units'] : 10;
    $waterBasePrice = $waterRate;
    $waterExcessRate = isset($_POST['water_excess_rate']) ? (float)$_POST['water_excess_rate'] : 25;
    $effectiveDate = $_POST['effective_date'];
    
    // Convert dd/mm/yyyy to yyyy-mm-dd if needed
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $effectiveDate, $m)) {
        $effectiveDate = "{$m[3]}-{$m[2]}-{$m[1]}";
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO rate (rate_water, rate_elec, effective_date, water_base_units, water_base_price, water_excess_rate) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$waterBasePrice, $elecRate, $effectiveDate, $waterBaseUnits, $waterBasePrice, $waterExcessRate]);
        $message = '<div class="alert success" style="margin-bottom:20px;padding:12px 16px;border-radius:8px;background:#dcfce7;color:#166534;font-weight:500;">บันทึกอัตราค่าน้ำค่าไฟใหม่สำเร็จแล้วครับ</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert error" style="margin-bottom:20px;padding:12px 16px;border-radius:8px;background:#fee2e2;color:#991b1b;font-weight:500;">เกิดข้อผิดพลาด: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Handle rate deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_rate' && !empty($_POST['rate_id'])) {
    $rateIdToDelete = (int)$_POST['rate_id'];
    
    // Safety check
    $stmt = $pdo->prepare("SELECT * FROM rate WHERE rate_id = ?");
    $stmt->execute([$rateIdToDelete]);
    $rateToDelete = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check current active rate
    $stmt = $pdo->query("SELECT * FROM rate WHERE effective_date <= CURDATE() ORDER BY effective_date DESC, rate_id DESC LIMIT 1");
    $currentActiveRate = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check count
    $stmt = $pdo->query("SELECT COUNT(*) FROM rate");
    $count = $stmt->fetchColumn();

    if ($count <= 1) {
        $message = '<div class="alert error" style="margin-bottom:20px;padding:12px 16px;border-radius:8px;background:#fee2e2;color:#991b1b;font-weight:500;">ไม่สามารถลบอัตราสุดท้ายในระบบได้ครับ</div>';
    } elseif ($currentActiveRate && $currentActiveRate['rate_id'] == $rateIdToDelete) {
        $message = '<div class="alert error" style="margin-bottom:20px;padding:12px 16px;border-radius:8px;background:#fee2e2;color:#991b1b;font-weight:500;">ไม่สามารถลบอัตราที่กำลังใช้งานอยู่ในปัจจุบันได้ครับ</div>';
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM rate WHERE rate_id = ?");
            $stmt->execute([$rateIdToDelete]);
            $message = '<div class="alert success" style="margin-bottom:20px;padding:12px 16px;border-radius:8px;background:#dcfce7;color:#166534;font-weight:500;">ลบข้อมูลอัตราค่าน้ำค่าไฟสำเร็จเรียบร้อยแล้ว</div>';
        } catch (PDOException $e) {
            $message = '<div class="alert error" style="margin-bottom:20px;padding:12px 16px;border-radius:8px;background:#fee2e2;color:#991b1b;font-weight:500;">เกิดข้อผิดพลาดในการลบข้อมูล: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}
// Fetch current rate
$stmt = $pdo->query("SELECT * FROM rate WHERE effective_date <= CURDATE() ORDER BY effective_date DESC, rate_id DESC LIMIT 1");
$currentRate = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch rate history
$stmt = $pdo->query("SELECT * FROM rate ORDER BY effective_date DESC, rate_id DESC");
$rateHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

$siteName = 'Sangthian Dormitory';
$stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'site_name'");
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $siteName = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการค่าน้ำไฟ - <?php echo htmlspecialchars($siteName); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #f8fafc; margin: 0; padding: 0; color: #334155; }
        .container { max-width: 1000px; margin: 30px auto; padding: 20px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-title { font-size: 24px; font-weight: 700; color: #1e293b; margin: 0; display: inline-flex; align-items: center; gap: 10px; }
        .card { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); margin-bottom: 24px; }
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-weight: 600; margin-bottom: 8px; color: #475569; }
        .form-control { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 15px; box-sizing: border-box; }
        .form-control:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .btn { padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; font-family: inherit; color: white; display: inline-block; text-align: center; }
        .btn-primary { background: #3b82f6; }
        .btn-primary:hover { background: #2563eb; }
        .btn-back { background: #e2e8f0; text-decoration: none; color: #475569; display: inline-flex; align-items: center; gap: 6px; padding: 10px 16px; border-radius: 8px; font-weight: 600; font-size: 14px; transition: all 0.2s; }
        .btn-back:hover { background: #cbd5e1; color: #1e293b; }
        .history-table { width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
        .history-table th, .history-table td { padding: 12px 16px; text-align: left; }
        .history-table th { background: #f8fafc; font-weight: 600; color: #475569; border-bottom: 1px solid #e2e8f0; }
        .history-table td { border-bottom: 1px solid #f1f5f9; }
        .history-table tr:last-child td { border-bottom: none; }
        .current-badge { background: #22c55e; color: white; padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
        @media (max-width: 768px) {
            .grid-4 { grid-template-columns: 1fr 1fr; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">
                <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                จัดการหน้าค่าน้ำค่าไฟ
            </h1>
            <a href="system_settings.php" class="btn-back">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                กลับไปหน้าตั้งค่า
            </a>
        </div>

        <?php echo $message; ?>

        <div class="card">
            <h2 style="margin-top: 0; font-size: 18px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; margin-bottom: 20px; color: #1e293b;">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#3b82f6" stroke-width="2" style="vertical-align:-4px; margin-right:6px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                เพิ่มอัตราใหม่
            </h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="save_rate">
                
                <div class="grid-4" style="margin-bottom: 20px;">
                    <div class="form-group">
                        <label class="form-label">ค่าน้ำเหมาจ่าย (บาท)</label>
                        <input type="number" step="0.01" min="0" name="water_rate" class="form-control" value="<?php echo $currentRate ? $currentRate['rate_water'] : 200; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">หน่วยฐานค่าน้ำ (หน่วย)</label>
                        <input type="number" name="water_base_units" class="form-control" value="<?php echo $currentRate ? $currentRate['water_base_units'] : 10; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ค่าเกินอัตรา (บาท/ต่อหน่วย)</label>
                        <input type="number" step="0.01" min="0" name="water_excess_rate" class="form-control" value="<?php echo $currentRate ? $currentRate['water_excess_rate'] : 25; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ค่าไฟ (บาท/หน่วย)</label>
                        <input type="number" step="0.01" min="0" name="elec_rate" class="form-control" value="<?php echo $currentRate ? $currentRate['rate_elec'] : 8; ?>" required>
                    </div>
                </div>

                <div class="form-group" style="max-width: 400px; margin-bottom: 24px;">
                    <label class="form-label">วันที่มีผลบังคับใช้ระบบอัตโนมัติ</label>
                    <input type="date" name="effective_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    <small style="color: #64748b; margin-top: 6px; display: block;">* ระบบจะเริ่มใช้อัตรานี้โดยอัตโนมัติเมื่อถึงวันที่กำหนด</small>
                </div>

                <div style="padding-top: 10px; border-top: 1px solid #f1f5f9;">
                    <button type="submit" class="btn btn-primary" style="padding: 12px 24px;">บันทึกอัตราค่าน้ำค่าไฟสำหรับรอบบิลใหม่</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h2 style="margin-top: 0; font-size: 18px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; margin-bottom: 20px; color: #1e293b;">
                ประวัติอัตราค่าน้ำค่าไฟและการปรับปรุง
            </h2>
            <div style="overflow-x: auto;">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>วันที่มีผล</th>
                            <th>ค่าน้ำเหมาจ่าย</th>
                            <th>หน่วยฐาน</th>
                            <th>ค่าน้ำส่วนเกิน</th>
                            <th>ค่าไฟฟ้า (ต่อหน่วย)</th>
                            <th>สถานะการใช้งาน</th>
                            <th style="text-align:center;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rateHistory as $index => $rate): ?>
                            <?php 
                                $isCurrent = ($currentRate && $currentRate['rate_id'] == $rate['rate_id']);
                                $isFuture = strtotime($rate['effective_date']) > time();
                            ?>
                            <tr style="<?php echo $isCurrent ? 'background-color:#f0fdf4;' : ''; ?>">
                                <td><?php echo date('d/m/Y', strtotime($rate['effective_date'])); ?></td>
                                <td>฿<?php echo number_format((float)$rate['rate_water'], 2); ?></td>
                                <td><?php echo $rate['water_base_units']; ?> หน่วย</td>
                                <td>฿<?php echo number_format((float)$rate['water_excess_rate'], 2); ?></td>
                                <td>฿<?php echo number_format((float)$rate['rate_elec'], 2); ?></td>
                                <td>
                                    <?php if ($isCurrent): ?>
                                        <span class="current-badge">ใช้งานปัจจุบัน</span>
                                    <?php elseif ($isFuture): ?>
                                        <span class="current-badge" style="background:#f59e0b;">รอระบบอัปเดต</span>
                                    <?php else: ?>
                                        <span style="color:#94a3b8; font-weight:500;">หมดอายุ (อดีต)</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php if (!$isCurrent): ?>
                                    <form method="POST" action="" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบอัตรานี้?');" style="display:inline-block; margin:0;">
                                        <input type="hidden" name="action" value="delete_rate">
                                        <input type="hidden" name="rate_id" value="<?php echo $rate['rate_id']; ?>">
                                        <button type="submit" style="background:none; border:none; padding:8px; cursor:pointer; color:#ef4444; border-radius:6px; transition:0.2s;" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='none'" title="ลบข้อมูลอัตรานี้">
                                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($rateHistory)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #94a3b8; padding: 30px;">ยังไม่มีข้อมูลประวัติอัตราค่าน้ำค่าไฟในระบบ</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
