<?php
declare(strict_types=1);
session_start();
// catch any uncaught exceptions to avoid blank 500 responses
set_exception_handler(function(Throwable $e) {
    error_log('[manage_utility] ' . $e->getMessage());
    // show basic error page
    http_response_code(500);
    echo '<h1>เกิดข้อผิดพลาดในระบบ</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    exit;
});

if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
require_once __DIR__ . '/../includes/water_calc.php';
$pdo = connectDB();

// เดือน/ปี
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$showMode = $_GET['show'] ?? 'occupied';
$todoOnly = isset($_GET['todo_only']) && $_GET['todo_only'] === '1';
$selectedCtrId = isset($_GET['ctr_id']) ? (int)$_GET['ctr_id'] : 0;
$selectedCtrFilterActive = $selectedCtrId > 0;

// เดือน/ปีที่มีอยู่จริงในฐานข้อมูล (utility) แต่เฉพาะที่ไม่ใช่เดือนอนาคต
$availableYears = [];
$availableMonthsByYear = [];
$currentYear = (int)date('Y');
$currentMonth = (int)date('n');

try {
    // ดึงข้อมูลที่อยู่ก่อนหน้า + เดือนปัจจุบัน + เดือนถัดไป (เพื่อให้สามารถเห็นข้อมูลที่บันทึกล่วงหน้าได้)
    $periodStmt = $pdo->query("\n        SELECT DISTINCT YEAR(utl_date) AS y, MONTH(utl_date) AS m\n        FROM utility\n        WHERE utl_date IS NOT NULL\n        ORDER BY y DESC, m DESC\n    ");
    $periods = $periodStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($periods as $period) {
        $periodYear = (int)$period['y'];
        $periodMonth = (int)$period['m'];
        
        // อนุญาตเดือนปัจจุบัน เดือนถัดไป และเดือนที่ผ่านมา (ไม่ข้ามวันตัดสิน)
        if (!isset($availableMonthsByYear[$periodYear])) {
            $availableMonthsByYear[$periodYear] = [];
            $availableYears[] = $periodYear;
        }
        $availableMonthsByYear[$periodYear][] = $periodMonth;
    }
} catch (PDOException $e) {}

// ตรวจสอบเดือนปัจจุบัน
if (!isset($availableMonthsByYear[$currentYear])) {
    $availableYears[] = $currentYear;
    $availableMonthsByYear[$currentYear] = [];
}
if (!in_array($currentMonth, $availableMonthsByYear[$currentYear], true)) {
    $availableMonthsByYear[$currentYear][] = $currentMonth;
}

// เรียงลำดับ
$availableYears = array_values(array_unique(array_map('intval', $availableYears)));
rsort($availableYears);
foreach ($availableMonthsByYear as $yearKey => $monthsList) {
    $monthsList = array_values(array_unique(array_map('intval', $monthsList)));
    rsort($monthsList);
    $availableMonthsByYear[(int)$yearKey] = $monthsList;
}

if (empty($availableYears)) {
    $availableYears[] = $year;
    $availableMonthsByYear[$year] = [$month];
}

// Check if user explicitly selected month/year (not just auto-defaulted)
$isExplicitSelection = isset($_GET['month']) || isset($_GET['year']);

// Only override year if user didn't explicitly select AND current year not in list
if (!$isExplicitSelection && !in_array($year, $availableYears, true)) {
    $year = $availableYears[0];
}

$yearMonths = $availableMonthsByYear[$year] ?? [];
if (empty($yearMonths)) {
    $yearMonths = [(int)date('n')];
    $availableMonthsByYear[$year] = $yearMonths;
}

// Only override month if user didn't explicitly select AND current month not in list
if (!$isExplicitSelection && !in_array($month, $yearMonths, true)) {
    $month = $yearMonths[0];
}

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
    try {
        $saved = 0;
        $lockedRooms = 0;
        foreach ($_POST['meter'] as $roomId => $data) {
        if (empty($data['ctr_id'])) continue;

        $waterInput = (isset($data['water']) && $data['water'] !== '') ? (int)$data['water'] : null;
        $elecInput = (isset($data['electric']) && $data['electric'] !== '') ? (int)$data['electric'] : null;

        if ($waterInput === null && $elecInput === null) continue;
        
        $ctrId = (int)$data['ctr_id'];
        $meterDate = $year . '-' . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . '-' . date('d');
        
        try {
            $prevStmt = $pdo->prepare("SELECT utl_water_end, utl_elec_end FROM utility WHERE ctr_id = ? ORDER BY utl_date DESC LIMIT 1");
            $prevStmt->execute([$ctrId]);
            $prev = $prevStmt->fetch(PDO::FETCH_ASSOC);

            $checkStmt = $pdo->prepare("SELECT utl_id, utl_water_start, utl_water_end, utl_elec_start, utl_elec_end FROM utility WHERE ctr_id = ? AND MONTH(utl_date) = ? AND YEAR(utl_date) = ?");
            $checkStmt->execute([$ctrId, $month, $year]);
            $existing = $checkStmt->fetch();

            if ($existing) {
                $lockedRooms++;
                continue;
            }

            $waterOld = isset($data['water_old']) ? (int)$data['water_old'] : ($existing ? (int)$existing['utl_water_start'] : (int)($prev['utl_water_end'] ?? 0));
            $elecOld = isset($data['elec_old']) ? (int)$data['elec_old'] : ($existing ? (int)$existing['utl_elec_start'] : (int)($prev['utl_elec_end'] ?? 0));

            $waterNew = $waterInput ?? ($existing ? (int)$existing['utl_water_end'] : $waterOld);
            $elecNew = $elecInput ?? ($existing ? (int)$existing['utl_elec_end'] : $elecOld);
            
            $insertStmt = $pdo->prepare("INSERT INTO utility (ctr_id, utl_water_start, utl_water_end, utl_elec_start, utl_elec_end, utl_date) VALUES (?, ?, ?, ?, ?, ?)");
            $insertStmt->execute([$ctrId, $waterOld, $waterNew, $elecOld, $elecNew, $meterDate]);
            
            $waterUsed = $waterNew - $waterOld;
            $elecUsed = $elecNew - $elecOld;
            
            // ตรวจสอบว่าเป็นการจดมิเตอร์ครั้งแรก (ไม่มี utility record ก่อนหน้า)
            $isFirstReading = !$prev;
            
            // คำนวณค่าน้ำแบบเหมาจ่าย - ครั้งแรกไม่เสียตัง
            if ($isFirstReading) {
                $waterCost = 0;
                $elecCost = 0;
            } else {
                $waterCost = calculateWaterCost($waterUsed);
                $elecCost = $elecUsed * $electricRate;
            }
            
            $updateExpStmt = $pdo->prepare("
                UPDATE expense SET 
                    exp_elec_unit = ?, exp_water_unit = ?,
                    rate_elec = ?, rate_water = ?,
                    exp_elec_chg = ?, exp_water = ?,
                    exp_total = room_price + ? + ?
                WHERE ctr_id = ? AND MONTH(exp_month) = ? AND YEAR(exp_month) = ?
            ");
            $updateExpStmt->execute([
                $elecUsed, $waterUsed, $electricRate, $waterRate,
                $elecCost, $waterCost,
                $elecCost, $waterCost,
                $ctrId, $month, $year
            ]);
            
            
            $saved++;
        } catch (PDOException $e) {
            $error = $e->getMessage();
        }
    }
} catch (Throwable $e) {
    error_log('[manage_utility][POST] ' . $e->getMessage());
    $error = 'เกิดข้อผิดพลาดในการบันทึกมิเตอร์: ' . $e->getMessage();
}
    if ($saved > 0) {
        $_SESSION['success'] = "บันทึกสำเร็จ {$saved} ห้อง";
        if ($lockedRooms > 0) {
            $_SESSION['success'] .= " (ข้าม {$lockedRooms} ห้องที่บันทึกเดือนนี้แล้ว)";
        }
        $redirectQuery = "month=$month&year=$year&show=$showMode";
        if ($selectedCtrFilterActive) {
            $redirectQuery .= "&todo_only=1&ctr_id=" . $selectedCtrId;
        }
        header("Location: manage_utility.php?$redirectQuery");
        exit;
    }
    if ($lockedRooms > 0 && $saved === 0) {
        $error = "ไม่สามารถแก้ไขข้อมูลเดือนนี้ได้: มี {$lockedRooms} ห้องที่บันทึกแล้ว";
    }
}

// ดึงห้อง
if ($showMode === 'occupied') {
    $occupiedSql = "
        SELECT r.room_id, r.room_number, c.ctr_id, t.tnt_name, COALESCE(tw.current_step, 1) AS workflow_step
        FROM room r
        JOIN (
            SELECT room_id, MAX(ctr_id) AS ctr_id
            FROM contract
            WHERE ctr_status = '0'
            GROUP BY room_id
        ) lc ON r.room_id = lc.room_id
        JOIN contract c ON c.ctr_id = lc.ctr_id
        LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
        LEFT JOIN tenant_workflow tw ON c.tnt_id = tw.tnt_id
        WHERE c.ctr_start <= LAST_DAY(STR_TO_DATE(CONCAT(?, '-', ?), '%Y-%m'))
        AND c.ctr_end >= STR_TO_DATE(CONCAT(?, '-', '01'), '%Y-%m-%d')
    ";

    $occupiedParams = [$year, $month, $year];
    if ($selectedCtrFilterActive) {
        $occupiedSql .= "\n        AND c.ctr_id = ?";
        $occupiedParams[] = $selectedCtrId;
    }

    $occupiedSql .= "\n        ORDER BY CAST(r.room_number AS UNSIGNED) ASC";
    $occupiedStmt = $pdo->prepare($occupiedSql);
    $occupiedStmt->execute($occupiedParams);
    $rooms = $occupiedStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $allSql = "
        SELECT r.room_id, r.room_number, c.ctr_id, COALESCE(t.tnt_name, '') as tnt_name, COALESCE(tw.current_step, 1) AS workflow_step
        FROM room r
        LEFT JOIN (
            SELECT room_id, MAX(ctr_id) AS ctr_id
            FROM contract
            WHERE ctr_status = '0'
            AND ctr_start <= LAST_DAY(STR_TO_DATE(CONCAT(?, '-', ?), '%Y-%m'))
            AND ctr_end >= STR_TO_DATE(CONCAT(?, '-', '01'), '%Y-%m-%d')
            GROUP BY room_id
        ) lc ON r.room_id = lc.room_id
        LEFT JOIN contract c ON c.ctr_id = lc.ctr_id
        LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
        LEFT JOIN tenant_workflow tw ON c.tnt_id = tw.tnt_id
    ";

    $allParams = [$year, $month, $year];
    if ($selectedCtrFilterActive) {
        $allSql .= "\n        WHERE c.ctr_id = ?";
        $allParams[] = $selectedCtrId;
    }

    $allSql .= "\n        ORDER BY CAST(r.room_number AS UNSIGNED) ASC";
    $allStmt = $pdo->prepare($allSql);
    $allStmt->execute($allParams);
    $rooms = $allStmt->fetchAll(PDO::FETCH_ASSOC);
}

// ดึงค่าเดิม
$readings = [];
foreach ($rooms as $room) {
    if (!$room['ctr_id']) {
        $readings[$room['room_id']] = ['water_old' => 0, 'elec_old' => 0, 'water_new' => '', 'elec_new' => '', 'saved' => false, 'workflow_step' => 1, 'meter_blocked' => false];
        continue;
    }
    
    // Check if meter recording is blocked:
    // 1. Workflow step <= 3 (not reached checkin step)
    // 2. OR no actual checkin_record exists (checkin not truly completed)
    $workflowStep = (int)($room['workflow_step'] ?? 1);
    
    // Verify checkin_record actually exists
    $checkinCheckStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM checkin_record WHERE ctr_id = ?");
    $checkinCheckStmt->execute([$room['ctr_id']]);
    $checkinCheck = $checkinCheckStmt->fetch(PDO::FETCH_ASSOC);
    $hasCheckinRecord = ($checkinCheck['cnt'] ?? 0) > 0;
    
    // Allow meter recording anytime - no day restrictions
    $meterBlocked = false;

    $targetMonthStart = sprintf('%04d-%02d-01', $year, $month);

    // คำนวณเดือน/ปีที่แล้ว
    $prevMonth = $month > 1 ? $month - 1 : 12;
    $prevYear = $month > 1 ? $year : $year - 1;

    // ดึงค่า "ก่อนหน้า" จากเดือนก่อนหน้า (ไม่ว่า utl_date จะเป็นวันไหนก็ตาม)
    $prevStmt = $pdo->prepare("SELECT utl_water_end, utl_elec_end FROM utility WHERE ctr_id = ? AND MONTH(utl_date) = ? AND YEAR(utl_date) = ? ORDER BY utl_date DESC LIMIT 1");
    $prevStmt->execute([$room['ctr_id'], $prevMonth, $prevYear]);
    $prev = $prevStmt->fetch(PDO::FETCH_ASSOC);
    
    $currentStmt = $pdo->prepare("SELECT utl_water_start, utl_water_end, utl_elec_start, utl_elec_end FROM utility WHERE ctr_id = ? AND MONTH(utl_date) = ? AND YEAR(utl_date) = ?");
    $currentStmt->execute([$room['ctr_id'], $month, $year]);
    $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if เคยบันทึกจริง ๆ (มิเตอร์มีค่า != 0) หรือเพียง placeholder (0→0)
    $hasRealData = $current && (
        ((int)$current['utl_water_end'] !== (int)$current['utl_water_start']) ||
        ((int)$current['utl_elec_end'] !== (int)$current['utl_elec_start'])
    );
    
    // ให้สามารถจดมิเตอร์ได้ทันทีจากขั้นตอนใด ๆ ของดof workflow
    // ถ้า hasRealData = true แสดงค่าสิ้นสุด ถ้า false แสดง empty string (input field ว่าง)
    $water_new = $hasRealData ? (int)$current['utl_water_end'] : '';
    $elec_new = $hasRealData ? (int)$current['utl_elec_end'] : '';
    $saved = $hasRealData ? true : false;
    
    // สำหรับ row ที่ครั้งแรก ต้องแสดง water_old ให้ถูกต้อง
    $water_old_value = $prev ? (int)$prev['utl_water_end'] : 0;
    $elec_old_value = $prev ? (int)$prev['utl_elec_end'] : 0;
    
    $readings[$room['room_id']] = [
        'water_old' => $water_old_value,
        'elec_old' => $elec_old_value,
        'water_new' => $water_new,
        'elec_new' => $elec_new,
        'saved' => $saved,
        'workflow_step' => $workflowStep,
        'meter_blocked' => $meterBlocked,
        'isFirstReading' => !$prev  // ไม่มี record ก่อนหน้า = ครั้งแรก
    ];
    
    // สำหรับ non-first reading เช็คว่า current บันทึกแล้ว ดึง water_start/elec_start มาแสดง
    if ($hasRealData && !$meterBlocked) {
        $readings[$room['room_id']]['water_old'] = (int)$current['utl_water_start'];
        $readings[$room['room_id']]['elec_old'] = (int)$current['utl_elec_start'];
    }
}

$totalRooms = count($rooms);
$totalRecorded = 0;
foreach ($readings as $r) {
    if ($r['saved']) $totalRecorded++;
}
$totalPending = $totalRooms - $totalRecorded;

// ค่าตั้งค่าระบบ
$siteName = 'Sangthian Dormitory';
$logoFilename = 'Logo.jpg';
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
        if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
    }
} catch (PDOException $e) {}

$thaiMonthsFull = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];

// จัดกลุ่มห้องตามชั้น
$floors = [];
foreach ($rooms as $room) {
    $num = (int)$room['room_number'];
    $floorNum = ($num >= 100) ? (int)floor($num / 100) : 1;
    $floors[$floorNum][] = $room;
}
ksort($floors);

$activeTab = $_POST['tab'] ?? ($_GET['tab'] ?? 'water');
if (!in_array($activeTab, ['water', 'electric'], true)) {
    $activeTab = 'water';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteName); ?> - จดมิเตอร์</title>
    <?php include __DIR__ . '/../includes/sidebar_toggle.php'; ?>
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>">
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/animate-ui.css">
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css">
    <style>
        :root {
            --meter-accent: #f97316;
            --meter-accent-dark: #ea580c;
            --meter-accent-shadow: rgba(249,115,22,0.25);
        }

        body[data-meter-tab="water"] {
            --meter-accent: #0ea5e9;
            --meter-accent-dark: #0284c7;
            --meter-accent-shadow: rgba(14,165,233,0.25);
        }

        body[data-meter-tab="electric"] {
            --meter-accent: #f97316;
            --meter-accent-dark: #ea580c;
            --meter-accent-shadow: rgba(249,115,22,0.25);
        }

        /* === Clean Light Theme === */
        html, body, .app-shell, .app-main, .reports-page {
            background: #f0f0f0 !important;
        }
        .page-header-bar {
            background: rgba(255,255,255,0.97) !important;
            border-bottom: 1px solid #e0e0e0 !important;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06) !important;
            margin-top: 0.75rem !important;
        }
        .page-header-bar h2 { color: #222 !important; }
        .sidebar-toggle-btn svg { stroke: #333 !important; }

        .meter-page {
            width: 100%;
            max-width: none;
            margin: 0;
            padding: 0 !important;
        }
        .app-main > .meter-page {
            padding-left: 0 !important;
            padding-right: 1rem !important;
        }
        .meter-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.07);
            margin: 0;
            overflow: hidden;
        }
        .meter-card-title {
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
            padding: 1.25rem 1rem 0.5rem;
        }
        .month-selector {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.25rem 1rem 0.75rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .month-selector select {
            padding: 0.4rem 0.7rem;
            border: 1px solid #d0d0d0;
            border-radius: 8px;
            font-size: 0.9rem;
            color: #333;
            background: #fff;
        }
        .mode-link {
            padding: 0.35rem 0.7rem;
            border-radius: 8px;
            font-size: 0.82rem;
            text-decoration: none;
            color: #666;
            border: 1px solid #d0d0d0;
            background: #fff;
            transition: all 0.2s;
        }
        .mode-link.active {
            background: var(--meter-accent);
            color: #fff;
            border-color: var(--meter-accent);
        }
        .stats-row {
            display: flex;
            justify-content: center;
            gap: 0.6rem;
            padding: 0 1rem 0.5rem;
            flex-wrap: wrap;
        }
        .stat-badge {
            padding: 0.3rem 0.7rem;
            border-radius: 16px;
            font-size: 0.78rem;
            font-weight: 600;
        }
        .stat-badge.rooms { background: #e3f2fd; color: #1565c0; }
        .stat-badge.done { background: #e8f5e9; color: #2e7d32; }
        .stat-badge.pending { background: #fff3e0; color: #e65100; }
        
        .meter-schedule-text .highlight {
            background: #fff59d;
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            font-weight: 600;
        }
        
        .rate-info {
            display: flex;
            justify-content: center;
            gap: 1.25rem;
            padding: 0.25rem 1rem 0.5rem;
            font-size: 0.78rem;
            color: #999;
        }
        .rate-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 3px; vertical-align: middle; }
        .rate-dot.water { background: #4fc3f7; }
        .rate-dot.elec { background: #f48fb1; }

        /* Tabs */
        .meter-tabs {
            display: flex;
            margin: 0 1rem;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            gap: 0;
        }
        .meter-tab {
            flex: 1;
            text-align: center;
            padding: 0.8rem;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            border: none !important;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            transition: all 0.2s ease;
            color: #64748b !important;
            background: transparent !important;
            position: relative;
        }
        .meter-tab:hover {
            background: #eef2f7 !important;
            color: #334155 !important;
        }
        .meter-tab.water-tab.active {
            background: linear-gradient(135deg, #0ea5e9, #0284c7) !important;
            color: #ffffff !important;
            font-weight: 700;
            box-shadow: inset 0 -3px 0 rgba(255,255,255,0.25) !important;
        }
        .meter-tab.elec-tab.active {
            background: linear-gradient(135deg, #f97316, #ea580c) !important;
            color: #ffffff !important;
            font-weight: 700;
            box-shadow: inset 0 -3px 0 rgba(255,255,255,0.25) !important;
        }
        .meter-tab svg { width: 18px; height: 18px; }

        /* Floor Header */
        .floor-header {
            padding: 0.5rem 1rem;
            font-weight: 600;
            font-size: 0.9rem;
            color: #555;
            background: #fafafa;
            border-bottom: 1px solid #eee;
            border-top: 1px solid #eee;
        }

        /* Table */
        .meter-table { width: 100%; border-collapse: collapse; }
        .meter-table thead th {
            background: var(--meter-accent);
            color: #fff;
            font-weight: 600;
            font-size: 0.82rem;
            padding: 0.7rem 0.4rem;
            text-align: center;
            white-space: nowrap;
        }
        .meter-table tbody tr { border-bottom: 1px solid #f0f0f0; transition: background 0.12s; }
        .meter-table tbody tr:hover { background: #fffde7; }
        .meter-table tbody tr.saved-row { background: #f1f8e9; }
        .meter-table tbody tr.empty-row { opacity: 0.4; }
        .meter-table td {
            padding: 0.6rem 0.4rem;
            text-align: center;
            font-size: 0.95rem;
            color: #333;
            vertical-align: middle;
        }
        .room-num-cell { font-weight: 700; font-size: 1.05rem; color: #222; }
        .status-icon svg { width: 22px; height: 22px; fill: #666; }

        /* Meter Input */
        .meter-input-field {
            width: 100%;
            max-width: 120px;
            padding: 0.45rem 0.3rem;
            text-align: center;
            border: 1px solid #b3e5fc;
            border-radius: 6px;
            background: #e1f5fe;
            font-size: 1rem;
            font-weight: 600;
            color: #333;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .meter-input-field:focus {
            outline: none;
            border-color: #0288d1;
            box-shadow: 0 0 0 3px rgba(2,136,209,0.12);
            background: #fff;
        }
        .meter-input-field.elec-input {
            border-color: #f8bbd0;
            background: #fce4ec;
        }
        .meter-input-field.elec-input:focus {
            border-color: #d81b60;
            box-shadow: 0 0 0 3px rgba(216,27,96,0.12);
            background: #fff;
        }
        .meter-input-field:disabled,
        .meter-input-field.locked {
            background: #f3f4f6 !important;
            border-color: #d1d5db !important;
            color: #6b7280 !important;
            cursor: not-allowed;
        }
        .meter-input-field.blocked-by-step {
            background: #fed7aa !important;
            border-color: #f59e0b !important;
            color: #92400e !important;
            cursor: not-allowed;
        }
        .meter-input-field.blocked-by-step::placeholder {
            color: #d97706 !important;
        }
        .usage-cell { font-weight: 700; color: #0277bd; }
        .usage-cell.elec-usage { color: #c2185b; }

        /* Highlight rows that still need meter entries */
        .meter-table tbody tr.needs-meter { 
            transition: background 0.15s ease, border-left 0.15s ease;
        }
        /* Water missing: blue accent */
        .meter-table tbody tr.needs-meter.needs-water {
            background: linear-gradient(90deg, rgba(235,249,255,1) 0%, rgba(229,246,255,1) 100%);
            border-left: 6px solid #0288d1;
        }
        .meter-table tbody tr.needs-meter.needs-water .room-num-cell { color: #01579b; }
        .meter-table tbody tr.needs-meter.needs-water .usage-cell { color: #01579b; font-weight: 800; }
        .meter-table tbody tr.needs-meter.needs-water .meter-input-field { background: #e1f5fe; border-color: #81d4fa; }

        /* Electric missing: orange accent */
        .meter-table tbody tr.needs-meter.needs-electric {
            background: linear-gradient(90deg, rgba(255,249,236,1) 0%, rgba(255,244,229,1) 100%);
            border-left: 6px solid #fb923c;
        }
        .meter-table tbody tr.needs-meter.needs-electric .room-num-cell { color: #b45309; }
        .meter-table tbody tr.needs-meter.needs-electric .usage-cell { color: #b45309; font-weight: 800; }
        .meter-table tbody tr.needs-meter.needs-electric .meter-input-field { background: #fff7ed; border-color: #fb923c; }

        /* Save Bar */
        .save-bar {
            position: sticky;
            bottom: 0;
            background: #fff;
            border-top: 1px solid #eee;
            padding: 0.75rem 1rem;
            display: flex;
            justify-content: center;
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: center;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.04);
            z-index: 10;
        }
        .save-bar .pill { padding: 0.35rem 0.75rem; border-radius: 16px; font-size: 0.82rem; font-weight: 500; }
        .save-bar .pill.water { background: #e1f5fe; color: #0277bd; }
        .save-bar .pill.elec { background: #fce4ec; color: #c2185b; }
        .save-bar .pill.total { background: #e8f5e9; color: #2e7d32; font-weight: 700; }
        .save-btn {
            padding: 0.6rem 1.75rem;
            background: var(--meter-accent);
            border: none;
            border-radius: 10px;
            color: #fff;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 3px 10px var(--meter-accent-shadow);
            transition: all 0.2s;
        }
        .save-btn:hover { background: var(--meter-accent-dark); transform: translateY(-1px); }

        /* Toast */
        .toast-msg { position: fixed; top: 1rem; right: 1rem; padding: 0.7rem 1.2rem; border-radius: 10px; font-weight: 600; z-index: 9999; color: #fff; animation: toastIn 0.3s ease; }
        .toast-msg.success { background: #43a047; }
        .toast-msg.error { background: #e53935; }
        @keyframes toastIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* Responsive */
        @media (max-width: 768px) {
            .meter-page { max-width: 100%; }
            .meter-card { margin: 0; border-radius: 0; }
            .meter-table td, .meter-table th { padding: 0.45rem 0.25rem; font-size: 0.82rem; }
            .meter-input-field { max-width: 90px; font-size: 0.88rem; }
            .table-responsive { overflow-x: auto; }
        }
        @media (max-width: 480px) {
            .meter-table td, .meter-table th { padding: 0.35rem 0.15rem; font-size: 0.75rem; }
            .meter-input-field { max-width: 68px; font-size: 0.78rem; padding: 0.35rem 0.2rem; }
            .meter-card-title { font-size: 1.2rem; }
            .meter-tab { font-size: 0.82rem; padding: 0.65rem 0.4rem; }
        }
    </style>
</head>
<body class="reports-page" data-meter-tab="<?php echo htmlspecialchars($activeTab, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="app-shell">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="app-main">
            <div class="meter-page">
                <?php $pageTitle = 'จดมิเตอร์'; include __DIR__ . '/../includes/page_header.php'; ?>

                <?php if (isset($_SESSION['success'])): ?>
                <div class="toast-msg success" id="toast"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <script>setTimeout(function(){var t=document.getElementById('toast');if(t)t.remove();},3000);</script>
                <?php endif; ?>
                <?php if ($error): ?>
                <div class="toast-msg error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="meter-card">
                    <div class="meter-card-title">จดมิเตอร์</div>

                    <div class="month-selector">
                        <form method="get" style="display:flex;gap:0.4rem;align-items:center;flex-wrap:wrap;justify-content:center;">
                            <input type="hidden" name="show" value="<?php echo htmlspecialchars($showMode); ?>">
                            <input type="hidden" name="tab" class="tab-hidden-input" value="<?php echo htmlspecialchars($activeTab); ?>">
                            <?php if ($selectedCtrFilterActive): ?>
                            <input type="hidden" name="todo_only" value="1">
                            <input type="hidden" name="ctr_id" value="<?php echo (int)$selectedCtrId; ?>">
                            <?php endif; ?>
                            <select name="month" onchange="this.form.submit()">
                                <?php foreach (($availableMonthsByYear[$year] ?? []) as $m): ?>
                                <option value="<?php echo $m; ?>" <?php echo $month === (int)$m ? 'selected' : ''; ?>><?php echo $thaiMonthsFull[(int)$m]; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="year" onchange="this.form.submit()">
                                <?php foreach ($availableYears as $y): ?>
                                <option value="<?php echo $y; ?>" <?php echo $year === (int)$y ? 'selected' : ''; ?>><?php echo ((int)$y) + 543; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <a href="?month=<?php echo $month; ?>&year=<?php echo $year; ?>&tab=<?php echo $activeTab; ?>&show=occupied<?php echo $selectedCtrFilterActive ? '&todo_only=1&ctr_id=' . (int)$selectedCtrId : ''; ?>" class="mode-link <?php echo $showMode === 'occupied' ? 'active' : ''; ?>">มีผู้เช่า</a>
                            <a href="?month=<?php echo $month; ?>&year=<?php echo $year; ?>&tab=<?php echo $activeTab; ?>&show=all<?php echo $selectedCtrFilterActive ? '&todo_only=1&ctr_id=' . (int)$selectedCtrId : ''; ?>" class="mode-link <?php echo $showMode === 'all' ? 'active' : ''; ?>">ทั้งหมด</a>
                        </form>
                    </div>

                    <div class="stats-row">
                        <span class="stat-badge rooms"><?php echo $totalRooms; ?> ห้อง</span>
                        <span class="stat-badge done"><?php echo $totalRecorded; ?> บันทึกแล้ว</span>
                        <span class="stat-badge pending"><?php echo max(0, $totalPending); ?> รอ</span>
                    </div>
                    
                    
                    <div class="rate-info">
                        <span><span class="rate-dot water"></span>น้ำ เหมาจ่าย <?php echo getWaterBasePrice(); ?>฿ (≤<?php echo getWaterBaseUnits(); ?> หน่วย) เกินหน่วยละ <?php echo getWaterExcessRate(); ?>฿</span>
                        <span><span class="rate-dot elec"></span>ไฟ <?php echo $electricRate; ?>฿/หน่วย</span>
                    </div>

                    <!-- Tabs -->
                    <div class="meter-tabs">
                        <button type="button" class="meter-tab water-tab <?php echo $activeTab==='water'?'active':''; ?>" onclick="switchTab('water')">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/></svg>
                            จดมิเตอร์ค่าน้ำ
                        </button>
                        <button type="button" class="meter-tab elec-tab <?php echo $activeTab==='electric'?'active':''; ?>" onclick="switchTab('electric')">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                            จดมิเตอร์ค่าไฟ
                        </button>
                    </div>

                    <?php if (empty($rooms)): ?>
                    <div style="text-align:center;padding:3rem;color:#aaa;">
                        <p>ไม่พบห้องพัก</p>
                    </div>
                    <?php else: ?>

                    <form method="post" id="meterForm" data-allow-submit>
                        <input type="hidden" name="save" value="1">
                        <input type="hidden" name="tab" class="tab-hidden-input" value="<?php echo htmlspecialchars($activeTab); ?>">

                        <!-- WATER TAB -->
                        <div id="waterPanel" style="<?php echo $activeTab!=='water'?'display:none':''; ?>">
                            <?php foreach ($floors as $floorNum => $floorRooms): ?>
                            <div class="floor-header">ชั้นที่ <?php echo $floorNum; ?></div>
                            <div class="table-responsive">
                            <table class="meter-table">
                                <thead><tr>
                                    <th>ห้อง</th><th>สถานะ</th><th>เลขมิเตอร์เดือนก่อนหน้า</th><th>เลขมิเตอร์เดือนล่าสุด</th><th>หน่วยที่ใช้</th><th>จำนวนเงินที่ต้องจ่าย</th>
                                </tr></thead>
                                <tbody>
                                <?php foreach ($floorRooms as $room):
                                    $r = $readings[$room['room_id']];
                                    $hasCtr = !empty($room['ctr_id']);
                                    $wUsed = ($r['water_new']!==''&&$r['water_new']!==null) ? ((int)$r['water_new']-$r['water_old']) : 0;
                                ?>
                                <?php $needsWater = $hasCtr && !$r['saved'] && ($r['water_new'] === '' || $r['water_new'] === null); ?>
                                <tr class="<?php echo $r['saved']?'saved-row':''; ?> <?php echo !$hasCtr?'empty-row':''; ?> <?php echo $needsWater ? 'needs-meter needs-water' : ''; ?>">
                                    <td class="room-num-cell">
                                        <?php echo htmlspecialchars($room['room_number']); ?>
                                        <?php if ($r['isFirstReading']): ?>
                                            <span style="color:#f59e0b;font-weight:700;margin-left:0.3rem;">(ครั้งแรก)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="status-icon"><?php if($hasCtr): ?><svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg><?php endif; ?></td>
                                    <td><?php echo $hasCtr ? number_format($r['water_old']) : '-'; ?></td>
                                    <td><?php if($hasCtr): ?>
                                        <?php 
                                            $tooltipMsg = '';
                                            if ($r['saved']) {
                                                $tooltipMsg = 'บันทึกเดือนนี้แล้ว ไม่สามารถแก้ไขได้';
                                            } elseif ($r['meter_blocked']) {
                                                if ($r['workflow_step'] < 4) {
                                                    $tooltipMsg = "ต้องผ่านขั้นตอน 4 เช็คอิน ก่อน (ขั้นตอนปัจจุบัน: {$r['workflow_step']}/5)";
                                                } else {
                                                    $tooltipMsg = "ยังไม่ได้เช็คอิน";
                                                }
                                            }
                                        ?>
                                        <input type="number" name="meter[<?php echo $room['room_id']; ?>][water]" class="meter-input-field meter-input <?php echo $r['saved'] ? 'locked' : ''; ?> <?php echo $r['meter_blocked'] ? 'blocked-by-step' : ''; ?>" data-type="water" data-room="<?php echo $room['room_id']; ?>" data-old="<?php echo $r['water_old']; ?>" data-first-reading="<?php echo $r['isFirstReading'] ? '1' : '0'; ?>" placeholder="<?php echo $r['water_old']; ?>" value="<?php echo $r['water_new']; ?>" min="<?php echo $r['water_old']; ?>" <?php echo ($r['saved'] || $r['meter_blocked']) ? 'disabled data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-title="' . htmlspecialchars($tooltipMsg) . '"' : ''; ?>
                                        <input type="hidden" name="meter[<?php echo $room['room_id']; ?>][water_old]" value="<?php echo $r['water_old']; ?>">
                                        <input type="hidden" name="meter[<?php echo $room['room_id']; ?>][ctr_id]" value="<?php echo $room['ctr_id']; ?>">
                                        <input type="hidden" name="meter[<?php echo $room['room_id']; ?>][workflow_step]" value="<?php echo $r['workflow_step']; ?>">
                                        <input type="hidden" name="meter[<?php echo $room['room_id']; ?>][is_first_reading]" value="<?php echo $r['isFirstReading'] ? '1' : '0'; ?>">
                                    <?php else: ?>-<?php endif; ?></td>
                                    <td class="usage-cell" data-room="<?php echo $room['room_id']; ?>" data-usage="water"><?php echo $hasCtr ? $wUsed : '-'; ?></td>
                                    <td class="amount-to-pay" data-room="<?php echo $room['room_id']; ?>" data-amount="water">
                                        <?php if ($hasCtr): ?>
                                            <?php echo $r['isFirstReading'] ? '0' : calculateWaterCost($wUsed); ?>
                                        <?php else: ?>-<?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- ELECTRIC TAB -->
                        <div id="electricPanel" style="<?php echo $activeTab!=='electric'?'display:none':''; ?>">
                            <?php foreach ($floors as $floorNum => $floorRooms): ?>
                            <div class="floor-header">ชั้นที่ <?php echo $floorNum; ?></div>
                            <div class="table-responsive">
                            <table class="meter-table">
                                <thead><tr>
                                    <th>ห้อง</th><th>สถานะ</th><th>เลขมิเตอร์เดือนก่อนหน้า</th><th>เลขมิเตอร์เดือนล่าสุด</th><th>หน่วยที่ใช้</th><th>จำนวนเงินที่ต้องจ่าย</th>
                                </tr></thead>
                                <tbody>
                                <?php foreach ($floorRooms as $room):
                                    $r = $readings[$room['room_id']];
                                    $hasCtr = !empty($room['ctr_id']);
                                    $eUsed = ($r['elec_new']!==''&&$r['elec_new']!==null) ? ((int)$r['elec_new']-$r['elec_old']) : 0;
                                ?>
                                <?php $needsElec = $hasCtr && !$r['saved'] && ($r['elec_new'] === '' || $r['elec_new'] === null); ?>
                                <tr class="<?php echo $r['saved']?'saved-row':''; ?> <?php echo !$hasCtr?'empty-row':''; ?> <?php echo $needsElec ? 'needs-meter needs-electric' : ''; ?>">
                                    <td class="room-num-cell">
                                        <?php echo htmlspecialchars($room['room_number']); ?>
                                        <?php if ($r['isFirstReading']): ?>
                                            <span style="color:#f59e0b;font-weight:700;margin-left:0.3rem;">(ครั้งแรก)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="status-icon"><?php if($hasCtr): ?><svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg><?php endif; ?></td>
                                    <td><?php echo $hasCtr ? number_format($r['elec_old']) : '-'; ?></td>
                                    <td><?php if($hasCtr): ?>
                                        <?php 
                                            $tooltipMsg = '';
                                            if ($r['saved']) {
                                                $tooltipMsg = 'บันทึกเดือนนี้แล้ว ไม่สามารถแก้ไขได้';
                                            } elseif ($r['meter_blocked']) {
                                                if ($r['workflow_step'] < 4) {
                                                    $tooltipMsg = "ต้องผ่านขั้นตอน 4 เช็คอิน ก่อน (ขั้นตอนปัจจุบัน: {$r['workflow_step']}/5)";
                                                } else {
                                                    $tooltipMsg = "ยังไม่ได้เช็คอิน";
                                                }
                                            }
                                        ?>
                                        <input type="number" name="meter[<?php echo $room['room_id']; ?>][electric]" class="meter-input-field elec-input meter-input <?php echo $r['saved'] ? 'locked' : ''; ?> <?php echo $r['meter_blocked'] ? 'blocked-by-step' : ''; ?>" data-type="electric" data-room="<?php echo $room['room_id']; ?>" data-old="<?php echo $r['elec_old']; ?>" data-first-reading="<?php echo $r['isFirstReading'] ? '1' : '0'; ?>" placeholder="<?php echo $r['elec_old']; ?>" value="<?php echo $r['elec_new']; ?>" min="<?php echo $r['elec_old']; ?>" <?php echo ($r['saved'] || $r['meter_blocked']) ? 'disabled data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-title="' . htmlspecialchars($tooltipMsg) . '"' : ''; ?>
                                        <input type="hidden" name="meter[<?php echo $room['room_id']; ?>][elec_old]" value="<?php echo $r['elec_old']; ?>">
                                        <input type="hidden" name="meter[<?php echo $room['room_id']; ?>][ctr_id]" value="<?php echo $room['ctr_id']; ?>">
                                        <input type="hidden" name="meter[<?php echo $room['room_id']; ?>][workflow_step]" value="<?php echo $r['workflow_step']; ?>">
                                    <?php else: ?>-<?php endif; ?></td>
                                    <td class="usage-cell elec-usage" data-room="<?php echo $room['room_id']; ?>" data-usage="electric"><?php echo $hasCtr ? $eUsed : '-'; ?></td>
                                    <td class="amount-to-pay" data-room="<?php echo $room['room_id']; ?>" data-amount="electric">
                                        <?php if ($hasCtr): ?>
                                            <?php echo $r['isFirstReading'] ? '0' : ($eUsed * $electricRate); ?>
                                        <?php else: ?>-<?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="save-bar">
                            <span class="pill water">💧 ค่าน้ำ <strong id="totalWater">0</strong> ฿</span>
                            <span class="pill elec">⚡ ค่าไฟ <strong id="totalElec">0</strong> ฿</span>
                            <span class="pill total">รวม <strong id="grandTotal">0</strong> ฿</span>
                            <button type="submit" class="save-btn">บันทึก (<span id="readyCount">0</span> ห้อง)</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
    var electricRate = <?php echo $electricRate; ?>;
    <?php echo getWaterCalcJS(); ?>
    var initialTab = <?php echo json_encode($activeTab, JSON_UNESCAPED_UNICODE); ?>;

    function switchTab(tab, shouldSyncUrl) {
        if (shouldSyncUrl === undefined) shouldSyncUrl = true;
        var safeTab = tab === 'electric' ? 'electric' : 'water';

        var waterPanel = document.getElementById('waterPanel');
        var electricPanel = document.getElementById('electricPanel');
        if (waterPanel) waterPanel.style.display = safeTab === 'water' ? '' : 'none';
        if (electricPanel) electricPanel.style.display = safeTab === 'electric' ? '' : 'none';

        var waterBtn = document.querySelector('.water-tab');
        var elecBtn = document.querySelector('.elec-tab');
        if (waterBtn) waterBtn.classList.toggle('active', safeTab === 'water');
        if (elecBtn) elecBtn.classList.toggle('active', safeTab === 'electric');
        if (document.body) document.body.setAttribute('data-meter-tab', safeTab);

        document.querySelectorAll('.tab-hidden-input').forEach(function(input) {
            input.value = safeTab;
        });

        if (shouldSyncUrl && window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            url.searchParams.set('tab', safeTab);
            window.history.replaceState({}, '', url.toString());
        }
    }

    function updateTotals() {
        var inputs = document.querySelectorAll('.meter-input');
        var rd = {};
        inputs.forEach(function(i) {
            var rid = i.dataset.room, t = i.dataset.type;
            var oldV = parseInt(i.dataset.old)||0, newV = parseInt(i.value)||0;
            var isFirstReading = i.dataset.firstReading === '1';  // ตรวจสอบว่าเป็นครั้งแรก
            if (!rd[rid]) rd[rid] = {water:0,electric:0,wu:0,eu:0,hw:false,he:false,firstReading:isFirstReading};
            if (t==='water' && i.value) { 
                var u=Math.max(0,newV-oldV); 
                rd[rid].wu=u; 
                rd[rid].water=isFirstReading ? 0 : calculateWaterCost(u);  // ครั้งแรก = 0 บาท
                rd[rid].hw=true; 
            }
            if (t==='electric' && i.value) { 
                var u=Math.max(0,newV-oldV); 
                rd[rid].eu=u; 
                rd[rid].electric=isFirstReading ? 0 : (u*electricRate);  // ครั้งแรก = 0 บาท
                rd[rid].he=true; 
            }
        });
        var tw=0, te=0, rc=0;
        Object.keys(rd).forEach(function(rid) {
            var d = rd[rid];
            document.querySelectorAll('[data-room="'+rid+'"][data-usage="water"]').forEach(function(el) { if(d.hw) el.textContent=d.wu; });
            document.querySelectorAll('[data-room="'+rid+'"][data-usage="electric"]').forEach(function(el) { if(d.he) el.textContent=d.eu; });
            if (d.hw) tw += d.water;
            if (d.he) te += d.electric;
            if (d.hw || d.he) rc++;
        });
        var totalWater = document.getElementById('totalWater');
        var totalElec = document.getElementById('totalElec');
        var grandTotal = document.getElementById('grandTotal');
        var readyCount = document.getElementById('readyCount');
        if (totalWater) totalWater.textContent = tw.toLocaleString();
        if (totalElec) totalElec.textContent = te.toLocaleString();
        if (grandTotal) grandTotal.textContent = (tw+te).toLocaleString();
        if (readyCount) readyCount.textContent = rc;
    }

    document.querySelectorAll('.meter-input').forEach(function(i) { i.addEventListener('input', updateTotals); });
    switchTab(initialTab, false);
    updateTotals();
    </script>
    <script src="/dormitory_management/Public/Assets/Javascript/animate-ui.js"></script>
</body>
</html>
