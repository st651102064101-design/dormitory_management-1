<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

// เดือน/ปี
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$showMode = $_GET['show'] ?? 'occupied';

// อัตราค่าน้ำค่าไฟ
$waterRate = 18;
$electricRate = 8;
try {
    $rateStmt = $pdo->query("SELECT * FROM rate ORDER BY effective_date DESC LIMIT 1");
    $rate = $rateStmt->fetch(PDO::FETCH_ASSOC);
    if ($rate) {
        $waterRate = (int)$rate['rate_water'];
        $electricRate = (int)$rate['rate_elec'];
    }
} catch (PDOException $e) {}

// บันทึกมิเตอร์
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $saved = 0;
    foreach ($_POST['meter'] as $roomId => $data) {
        if (empty($data['water']) || empty($data['electric']) || empty($data['ctr_id'])) continue;
        
        $ctrId = (int)$data['ctr_id'];
        $waterNew = (int)$data['water'];
        $elecNew = (int)$data['electric'];
        $waterOld = (int)($data['water_old'] ?? 0);
        $elecOld = (int)($data['elec_old'] ?? 0);
        $meterDate = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . date('d');
        
        try {
            // ตรวจสอบว่ามีแล้วหรือยัง
            $checkStmt = $pdo->prepare("SELECT utl_id FROM utility WHERE ctr_id = ? AND MONTH(utl_date) = ? AND YEAR(utl_date) = ?");
            $checkStmt->execute([$ctrId, $month, $year]);
            $existing = $checkStmt->fetch();
            
            if ($existing) {
                $updateStmt = $pdo->prepare("UPDATE utility SET utl_water_end = ?, utl_elec_end = ?, utl_date = ? WHERE utl_id = ?");
                $updateStmt->execute([$waterNew, $elecNew, $meterDate, $existing['utl_id']]);
            } else {
                $insertStmt = $pdo->prepare("INSERT INTO utility (ctr_id, utl_water_start, utl_water_end, utl_elec_start, utl_elec_end, utl_date) VALUES (?, ?, ?, ?, ?, ?)");
                $insertStmt->execute([$ctrId, $waterOld, $waterNew, $elecOld, $elecNew, $meterDate]);
            }
            
            // อัพเดต expense
            $waterUsed = $waterNew - $waterOld;
            $elecUsed = $elecNew - $elecOld;
            $expMonth = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01';
            
            $updateExpStmt = $pdo->prepare("
                UPDATE expense SET 
                    exp_elec_unit = ?, exp_water_unit = ?,
                    rate_elec = ?, rate_water = ?,
                    exp_elec_chg = ? * ?, exp_water = ? * ?,
                    exp_total = room_price + (? * ?) + (? * ?)
                WHERE ctr_id = ? AND MONTH(exp_month) = ? AND YEAR(exp_month) = ?
            ");
            $updateExpStmt->execute([
                $elecUsed, $waterUsed, $electricRate, $waterRate,
                $elecUsed, $electricRate, $waterUsed, $waterRate,
                $elecUsed, $electricRate, $waterUsed, $waterRate,
                $ctrId, $month, $year
            ]);
            
            $saved++;
        } catch (PDOException $e) {
            $error = $e->getMessage();
        }
    }
    if ($saved > 0) {
        $_SESSION['success'] = "บันทึกสำเร็จ {$saved} ห้อง";
        header("Location: manage_utility.php?month=$month&year=$year&show=$showMode");
        exit;
    }
}

// ดึงห้องที่มีผู้เช่า
if ($showMode === 'occupied') {
    $rooms = $pdo->query("
        SELECT r.room_id, r.room_number, c.ctr_id, t.tnt_name
        FROM room r
        JOIN contract c ON r.room_id = c.room_id AND c.ctr_status = '0'
        JOIN tenant t ON c.tnt_id = t.tnt_id
        ORDER BY CAST(r.room_number AS UNSIGNED) ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $rooms = $pdo->query("
        SELECT r.room_id, r.room_number, c.ctr_id, COALESCE(t.tnt_name, '') as tnt_name
        FROM room r
        LEFT JOIN contract c ON r.room_id = c.room_id AND c.ctr_status = '0'
        LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
        ORDER BY CAST(r.room_number AS UNSIGNED) ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// ดึงค่าเดิมของแต่ละห้อง
$readings = [];
foreach ($rooms as $room) {
    if (!$room['ctr_id']) {
        $readings[$room['room_id']] = ['water_old' => 0, 'elec_old' => 0, 'water_new' => '', 'elec_new' => '', 'saved' => false];
        continue;
    }
    
    $prevStmt = $pdo->prepare("SELECT utl_water_end, utl_elec_end FROM utility WHERE ctr_id = ? ORDER BY utl_date DESC LIMIT 1");
    $prevStmt->execute([$room['ctr_id']]);
    $prev = $prevStmt->fetch(PDO::FETCH_ASSOC);
    
    $currentStmt = $pdo->prepare("SELECT utl_water_end, utl_elec_end FROM utility WHERE ctr_id = ? AND MONTH(utl_date) = ? AND YEAR(utl_date) = ?");
    $currentStmt->execute([$room['ctr_id'], $month, $year]);
    $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
    
    $readings[$room['room_id']] = [
        'water_old' => $prev ? (int)$prev['utl_water_end'] : 0,
        'elec_old' => $prev ? (int)$prev['utl_elec_end'] : 0,
        'water_new' => $current ? (int)$current['utl_water_end'] : '',
        'elec_new' => $current ? (int)$current['utl_elec_end'] : '',
        'saved' => $current ? true : false
    ];
    
    // ถ้ามีค่าปัจจุบัน ใช้ค่า start เป็นค่าเดิม
    if ($current) {
        $startStmt = $pdo->prepare("SELECT utl_water_start, utl_elec_start FROM utility WHERE ctr_id = ? AND MONTH(utl_date) = ? AND YEAR(utl_date) = ?");
        $startStmt->execute([$room['ctr_id'], $month, $year]);
        $startData = $startStmt->fetch(PDO::FETCH_ASSOC);
        if ($startData) {
            $readings[$room['room_id']]['water_old'] = (int)$startData['utl_water_start'];
            $readings[$room['room_id']]['elec_old'] = (int)$startData['utl_elec_start'];
        }
    }
}

// คำนวณสถิติ
$totalRooms = count($rooms);
$totalRecorded = 0;
foreach ($readings as $r) {
    if ($r['saved']) $totalRecorded++;
}
$totalPending = $totalRooms - $totalRecorded;

// ดึงค่าตั้งค่าระบบ
$siteName = 'Sangthian Dormitory';
$logoFilename = 'Logo.jpg';
$themeColor = '#0f172a';
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename', 'theme_color')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
        if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
        if ($row['setting_key'] === 'theme_color') $themeColor = $row['setting_value'];
    }
} catch (PDOException $e) {}

// ตรวจสอบว่าเป็น light theme หรือไม่
$isLightTheme = false;
$lightThemeClass = '';
if (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $themeColor)) {
    $hex = ltrim($themeColor, '#');
    if (strlen($hex) == 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
    if ($brightness > 180) {
        $isLightTheme = true;
        $lightThemeClass = 'light-theme';
    }
}

$thaiMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
$thaiMonthsFull = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
?>
<!DOCTYPE html>
<html lang="th" class="<?php echo $lightThemeClass; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteName); ?> - จดมิเตอร์</title>
    <?php include __DIR__ . '/../includes/sidebar_toggle.php'; ?>
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>">
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/animate-ui.css">
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css">
    <style>
        .meter-page { padding: 0; }
        .meter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .meter-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #f1f5f9;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .meter-controls {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .meter-controls select {
            padding: 0.5rem 0.75rem;
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 8px;
            color: #f1f5f9;
            font-size: 0.9rem;
        }
        .mode-btn {
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255,255,255,0.15);
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        .mode-btn.active {
            background: rgba(59, 130, 246, 0.3);
            border-color: #3b82f6;
            color: #60a5fa;
        }
        
        /* Stats Bar */
        .stats-bar {
            display: flex;
            gap: 1rem;
            padding: 0.75rem 1rem;
            background: rgba(30, 41, 59, 0.5);
            flex-wrap: wrap;
            justify-content: center;
        }
        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        .stat-item.blue { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
        .stat-item.green { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
        .stat-item.yellow { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }
        .stat-item strong { font-size: 1.1rem; }
        
        /* Rate Info */
        .rate-bar {
            display: flex;
            gap: 1.5rem;
            padding: 0.5rem 1rem;
            justify-content: center;
            font-size: 0.8rem;
            color: #94a3b8;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .rate-bar span { display: flex; align-items: center; gap: 4px; }
        .water-dot { width: 10px; height: 10px; border-radius: 50%; background: #3b82f6; }
        .elec-dot { width: 10px; height: 10px; border-radius: 50%; background: #eab308; }
        
        /* Room List */
        .room-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            padding: 1rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        .room-row {
            display: grid;
            grid-template-columns: 90px 1fr 1fr auto;
            gap: 0.75rem;
            align-items: center;
            background: rgba(30, 41, 59, 0.8);
            padding: 1rem;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .room-row.saved {
            border-color: rgba(34, 197, 94, 0.5);
            background: rgba(34, 197, 94, 0.08);
        }
        .room-row.no-contract {
            opacity: 0.5;
        }
        .room-num {
            font-weight: 700;
            font-size: 1.3rem;
            text-align: center;
            color: #60a5fa;
        }
        .room-tenant {
            font-size: 0.85rem;
            color: #64748b;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .input-group {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .input-group label {
            font-size: 0.75rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 3px;
        }
        .input-group input {
            width: 100%;
            padding: 0.65rem;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 6px;
            color: #f1f5f9;
            font-size: 1.05rem;
            text-align: center;
        }
        .input-group input:focus {
            outline: none;
            border-color: #3b82f6;
        }
        .input-group input.water { border-left: 3px solid #3b82f6; }
        .input-group input.electric { border-left: 3px solid #eab308; }
        .input-group .old-val {
            font-size: 0.7rem;
            color: #64748b;
            text-align: center;
        }
        .room-status {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .room-status.saved { background: #22c55e; }
        .room-status.pending { background: rgba(251, 191, 36, 0.3); border: 2px solid #fbbf24; }
        
        /* Save Button */
        .save-container {
            position: sticky;
            bottom: 0;
            background: linear-gradient(to top, rgba(15, 23, 42, 1), rgba(15, 23, 42, 0.95));
            padding: 1rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .save-summary {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-bottom: 0.75rem;
            flex-wrap: wrap;
            font-size: 0.85rem;
        }
        .save-summary span {
            padding: 0.4rem 0.75rem;
            border-radius: 6px;
        }
        .save-summary .water-cost { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
        .save-summary .elec-cost { background: rgba(234, 179, 8, 0.15); color: #eab308; }
        .save-summary .total-cost { background: rgba(34, 197, 94, 0.2); color: #22c55e; font-weight: 600; }
        .save-btn {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            display: block;
            padding: 0.875rem;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(34, 197, 94, 0.4);
        }
        .save-btn:hover { transform: translateY(-1px); }
        .save-btn:active { transform: scale(0.98); }
        
        /* Toast */
        .toast {
            position: fixed;
            top: 1rem;
            right: 1rem;
            padding: 0.75rem 1.25rem;
            border-radius: 10px;
            font-weight: 500;
            z-index: 9999;
            animation: slideIn 0.3s ease;
        }
        .toast.success { background: #22c55e; color: white; }
        .toast.error { background: #ef4444; color: white; }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        /* Light Theme */
        html.light-theme .meter-header { border-color: rgba(0,0,0,0.1); }
        html.light-theme .meter-title { color: #111827; }
        html.light-theme .meter-controls select { background: rgba(0,0,0,0.05); border-color: rgba(0,0,0,0.1); color: #111827; }
        html.light-theme .mode-btn { background: rgba(0,0,0,0.05); border-color: rgba(0,0,0,0.1); color: #374151; }
        html.light-theme .mode-btn.active { background: rgba(59, 130, 246, 0.15); color: #2563eb; }
        html.light-theme .stats-bar { background: rgba(0,0,0,0.03); }
        html.light-theme .rate-bar { color: #64748b; border-color: rgba(0,0,0,0.1); }
        html.light-theme .room-row { background: rgba(255,255,255,0.9); border-color: rgba(0,0,0,0.1); }
        html.light-theme .room-row.saved { border-color: rgba(34, 197, 94, 0.4); background: rgba(34, 197, 94, 0.05); }
        html.light-theme .room-num { color: #2563eb; }
        html.light-theme .input-group label { color: #64748b; }
        html.light-theme .input-group input { background: rgba(255,255,255,0.95); border-color: rgba(0,0,0,0.15); color: #111827; }
        html.light-theme .save-container { background: linear-gradient(to top, rgba(255,255,255,1), rgba(255,255,255,0.95)); border-color: rgba(0,0,0,0.1); }
        
        @media (max-width: 500px) {
            .room-row { grid-template-columns: 50px 1fr 1fr 24px; padding: 0.5rem; }
            .room-num { font-size: 0.95rem; }
            .input-group input { padding: 0.4rem; font-size: 0.85rem; }
            .meter-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body class="reports-page">
    <div class="app-shell">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="app-main">
            <div class="meter-page">
                
                <?php if (isset($_SESSION['success'])): ?>
                <div class="toast success" id="toast"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <script>setTimeout(() => document.getElementById('toast')?.remove(), 3000);</script>
                <?php endif; ?>
                <?php if ($error): ?>
                <div class="toast error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Header -->
                <div class="meter-header">
                    <div class="meter-title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                        จดมิเตอร์ <?php echo $thaiMonths[(int)$month] . ' ' . ((int)$year + 543); ?>
                    </div>
                    <div class="meter-controls">
                        <form method="get" style="display: flex; gap: 0.5rem;">
                            <input type="hidden" name="show" value="<?php echo $showMode; ?>">
                            <select name="month" onchange="this.form.submit()">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $month == $m ? 'selected' : ''; ?>><?php echo $thaiMonths[$m]; ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="year" onchange="this.form.submit()">
                                <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y + 543; ?></option>
                                <?php endfor; ?>
                            </select>
                        </form>
                        <a href="?month=<?php echo $month; ?>&year=<?php echo $year; ?>&show=occupied" class="mode-btn <?php echo $showMode === 'occupied' ? 'active' : ''; ?>">มีผู้เช่า</a>
                        <a href="?month=<?php echo $month; ?>&year=<?php echo $year; ?>&show=all" class="mode-btn <?php echo $showMode === 'all' ? 'active' : ''; ?>">ทั้งหมด</a>
                    </div>
                </div>
                
                <!-- Stats -->
                <div class="stats-bar">
                    <div class="stat-item blue">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                        <strong><?php echo $totalRooms; ?></strong> ห้อง
                    </div>
                    <div class="stat-item green">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <strong><?php echo $totalRecorded; ?></strong> บันทึกแล้ว
                    </div>
                    <div class="stat-item yellow">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <strong><?php echo max(0, $totalPending); ?></strong> รอบันทึก
                    </div>
                </div>
                
                <!-- Rate Info -->
                <div class="rate-bar">
                    <span><span class="water-dot"></span> น้ำ <?php echo $waterRate; ?>฿/หน่วย</span>
                    <span><span class="elec-dot"></span> ไฟ <?php echo $electricRate; ?>฿/หน่วย</span>
                </div>
                
                <?php if (empty($rooms)): ?>
                <div style="text-align: center; padding: 3rem; color: #94a3b8;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                    <p style="margin-top: 1rem;">ไม่พบห้องพัก</p>
                </div>
                <?php else: ?>
                
                <!-- Room List -->
                <form method="post" id="meterForm" data-allow-submit>
                    <input type="hidden" name="save" value="1">
                    
                    <div class="room-list">
                        <?php foreach ($rooms as $room): 
                            $r = $readings[$room['room_id']];
                            $hasCtr = !empty($room['ctr_id']);
                        ?>
                        <div class="room-row <?php echo $r['saved'] ? 'saved' : ''; ?> <?php echo !$hasCtr ? 'no-contract' : ''; ?>">
                            <div>
                                <div class="room-num"><?php echo $room['room_number']; ?></div>
                                <div class="room-tenant"><?php echo $room['tnt_name'] ?: 'ว่าง'; ?></div>
                            </div>
                            
                            <?php if ($hasCtr): ?>
                            <div class="input-group">
                                <label><span class="water-dot" style="width:6px;height:6px"></span> น้ำ</label>
                                <input type="number" name="meter[<?php echo $room['room_id']; ?>][water]" 
                                       class="water meter-input" 
                                       data-type="water" data-room="<?php echo $room['room_id']; ?>"
                                       data-old="<?php echo $r['water_old']; ?>"
                                       placeholder="<?php echo $r['water_old']; ?>" 
                                       value="<?php echo $r['water_new']; ?>"
                                       min="<?php echo $r['water_old']; ?>">
                                <div class="old-val">เดิม <?php echo number_format($r['water_old']); ?></div>
                                <input type="hidden" name="meter[<?php echo $room['room_id']; ?>][water_old]" value="<?php echo $r['water_old']; ?>">
                                <input type="hidden" name="meter[<?php echo $room['room_id']; ?>][ctr_id]" value="<?php echo $room['ctr_id']; ?>">
                            </div>
                            <div class="input-group">
                                <label><span class="elec-dot" style="width:6px;height:6px"></span> ไฟ</label>
                                <input type="number" name="meter[<?php echo $room['room_id']; ?>][electric]" 
                                       class="electric meter-input"
                                       data-type="electric" data-room="<?php echo $room['room_id']; ?>"
                                       data-old="<?php echo $r['elec_old']; ?>"
                                       placeholder="<?php echo $r['elec_old']; ?>"
                                       value="<?php echo $r['elec_new']; ?>"
                                       min="<?php echo $r['elec_old']; ?>">
                                <div class="old-val">เดิม <?php echo number_format($r['elec_old']); ?></div>
                                <input type="hidden" name="meter[<?php echo $room['room_id']; ?>][elec_old]" value="<?php echo $r['elec_old']; ?>">
                            </div>
                            <?php else: ?>
                            <div style="grid-column: span 2; text-align: center; color: #64748b; font-size: 0.8rem;">ห้องว่าง</div>
                            <?php endif; ?>
                            
                            <div class="room-status <?php echo $r['saved'] ? 'saved' : 'pending'; ?>">
                                <?php if ($r['saved']): ?>
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Save Button -->
                    <div class="save-container">
                        <div class="save-summary">
                            <span class="water-cost">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;"><path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/></svg>
                                ค่าน้ำ <strong id="totalWater">0</strong> ฿
                            </span>
                            <span class="elec-cost">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                                ค่าไฟ <strong id="totalElec">0</strong> ฿
                            </span>
                            <span class="total-cost">
                                รวม <strong id="grandTotal">0</strong> ฿
                            </span>
                        </div>
                        <button type="submit" class="save-btn" id="saveBtn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            บันทึกทั้งหมด (<span id="readyCount">0</span> ห้อง)
                        </button>
                    </div>
                </form>
                <?php endif; ?>
                
            </div>
        </main>
    </div>

    <script>
    const waterRate = <?php echo $waterRate; ?>;
    const electricRate = <?php echo $electricRate; ?>;
    
    function updateTotals() {
        const inputs = document.querySelectorAll('.meter-input');
        const roomsData = {};
        
        inputs.forEach(input => {
            const roomId = input.dataset.room;
            const type = input.dataset.type;
            const oldVal = parseInt(input.dataset.old) || 0;
            const newVal = parseInt(input.value) || 0;
            
            if (!roomsData[roomId]) roomsData[roomId] = { water: 0, electric: 0, hasWater: false, hasElec: false };
            
            if (type === 'water' && input.value) {
                roomsData[roomId].water = Math.max(0, newVal - oldVal) * waterRate;
                roomsData[roomId].hasWater = true;
            }
            if (type === 'electric' && input.value) {
                roomsData[roomId].electric = Math.max(0, newVal - oldVal) * electricRate;
                roomsData[roomId].hasElec = true;
            }
        });
        
        let totalWater = 0, totalElec = 0, readyCount = 0;
        
        Object.values(roomsData).forEach(data => {
            if (data.hasWater && data.hasElec) {
                totalWater += data.water;
                totalElec += data.electric;
                readyCount++;
            }
        });
        
        document.getElementById('totalWater').textContent = totalWater.toLocaleString();
        document.getElementById('totalElec').textContent = totalElec.toLocaleString();
        document.getElementById('grandTotal').textContent = (totalWater + totalElec).toLocaleString();
        document.getElementById('readyCount').textContent = readyCount;
    }
    
    document.querySelectorAll('.meter-input').forEach(input => {
        input.addEventListener('input', updateTotals);
    });
    
    // Initial calculation
    updateTotals();
    </script>
    <script src="/dormitory_management/Public/Assets/Javascript/animate-ui.js"></script>
</body>
</html>
