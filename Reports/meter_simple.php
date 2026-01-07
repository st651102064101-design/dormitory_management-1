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
    if ($saved > 0) $success = "บันทึกสำเร็จ {$saved} ห้อง";
}

// ดึงห้องที่มีผู้เช่า
$rooms = $pdo->query("
    SELECT r.room_id, r.room_number, c.ctr_id, t.tnt_name
    FROM room r
    JOIN contract c ON r.room_id = c.room_id AND c.ctr_status = '0'
    JOIN tenant t ON c.tnt_id = t.tnt_id
    ORDER BY CAST(r.room_number AS UNSIGNED) ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ดึงค่าเดิมของแต่ละห้อง
$readings = [];
foreach ($rooms as $room) {
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
}

$thaiMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จดมิเตอร์</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0f172a; 
            color: #f1f5f9;
            min-height: 100vh;
            padding: 1rem;
        }
        .container { max-width: 600px; margin: 0 auto; }
        
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #334155;
        }
        h1 { font-size: 1.25rem; font-weight: 600; }
        .back-btn {
            color: #60a5fa;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .month-select {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .month-select select {
            flex: 1;
            padding: 0.75rem;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 8px;
            color: #f1f5f9;
            font-size: 1rem;
        }
        
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        .alert-success { background: rgba(34, 197, 94, 0.2); color: #22c55e; border: 1px solid #22c55e; }
        .alert-error { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid #ef4444; }
        
        .room-list { display: flex; flex-direction: column; gap: 0.5rem; }
        
        .room-row {
            display: grid;
            grid-template-columns: 60px 1fr 1fr;
            gap: 0.5rem;
            align-items: center;
            background: #1e293b;
            padding: 0.75rem;
            border-radius: 10px;
            border: 1px solid #334155;
        }
        .room-row.saved { border-color: #22c55e; background: rgba(34, 197, 94, 0.1); }
        
        .room-num {
            font-weight: 700;
            font-size: 1.1rem;
            text-align: center;
            color: #60a5fa;
        }
        
        .input-group {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .input-group label {
            font-size: 0.7rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .input-group input {
            width: 100%;
            padding: 0.6rem;
            background: #0f172a;
            border: 1px solid #475569;
            border-radius: 6px;
            color: #f1f5f9;
            font-size: 1rem;
            text-align: center;
        }
        .input-group input:focus {
            outline: none;
            border-color: #3b82f6;
        }
        .input-group input.water { border-left: 3px solid #3b82f6; }
        .input-group input.electric { border-left: 3px solid #eab308; }
        .input-group .old-val { font-size: 0.65rem; color: #94a3b8; text-align: center; }
        
        .save-btn {
            width: 100%;
            padding: 1rem;
            margin-top: 1rem;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            position: sticky;
            bottom: 1rem;
            box-shadow: 0 4px 20px rgba(34, 197, 94, 0.4);
        }
        .save-btn:active { transform: scale(0.98); }
        
        .rate-info {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-bottom: 1rem;
            font-size: 0.8rem;
            color: #94a3b8;
        }
        .rate-info span { display: flex; align-items: center; gap: 4px; }
        .water-dot { width: 8px; height: 8px; border-radius: 50%; background: #3b82f6; }
        .elec-dot { width: 8px; height: 8px; border-radius: 50%; background: #eab308; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>จดมิเตอร์ <?php echo $thaiMonths[(int)$month] . ' ' . ((int)$year + 543); ?></h1>
            <a href="manage_utility.php" class="back-btn">← กลับ</a>
        </header>
        
        <form method="get" class="month-select">
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
        
        <div class="rate-info">
            <span><span class="water-dot"></span> น้ำ <?php echo $waterRate; ?>฿/หน่วย</span>
            <span><span class="elec-dot"></span> ไฟ <?php echo $electricRate; ?>฿/หน่วย</span>
        </div>
        
        <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="post">
            <input type="hidden" name="save" value="1">
            
            <div class="room-list">
                <?php foreach ($rooms as $room): 
                    $r = $readings[$room['room_id']];
                ?>
                <div class="room-row <?php echo $r['saved'] ? 'saved' : ''; ?>">
                    <div class="room-num"><?php echo $room['room_number']; ?></div>
                    <div class="input-group">
                        <label><span class="water-dot"></span> น้ำ</label>
                        <input type="number" name="meter[<?php echo $room['room_id']; ?>][water]" 
                               class="water" placeholder="<?php echo $r['water_old']; ?>" 
                               value="<?php echo $r['water_new']; ?>"
                               min="<?php echo $r['water_old']; ?>">
                        <div class="old-val">เดิม: <?php echo number_format($r['water_old']); ?></div>
                        <input type="hidden" name="meter[<?php echo $room['room_id']; ?>][water_old]" value="<?php echo $r['water_old']; ?>">
                        <input type="hidden" name="meter[<?php echo $room['room_id']; ?>][ctr_id]" value="<?php echo $room['ctr_id']; ?>">
                    </div>
                    <div class="input-group">
                        <label><span class="elec-dot"></span> ไฟ</label>
                        <input type="number" name="meter[<?php echo $room['room_id']; ?>][electric]" 
                               class="electric" placeholder="<?php echo $r['elec_old']; ?>"
                               value="<?php echo $r['elec_new']; ?>"
                               min="<?php echo $r['elec_old']; ?>">
                        <div class="old-val">เดิม: <?php echo number_format($r['elec_old']); ?></div>
                        <input type="hidden" name="meter[<?php echo $room['room_id']; ?>][elec_old]" value="<?php echo $r['elec_old']; ?>">
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <button type="submit" class="save-btn">
                บันทึกทั้งหมด (<?php echo count($rooms); ?> ห้อง)
            </button>
        </form>
    </div>
</body>
</html>
