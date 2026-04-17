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
    // ดึงเดือนจากสัญญา (ctr_start-ctr_end) + utility records
    // รวมกันและตัด <= วันปัจจุบัน เพื่อให้จดมิเตอร์ได้ตั้งแต่เดือนเริ่มสัญญา
    $contractStmt = $pdo->query("\n        SELECT ctr_start, ctr_end FROM contract WHERE ctr_start IS NOT NULL\n    ");
    $contracts = $contractStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $periodSet = [];  // เก็บเดือนที่เหมาะสม เป็น 'Y-m' string
    
    // ดึงเดือนจากสัญญา: ทุกเดือนระหว่าง ctr_start และ ctr_end
    foreach ($contracts as $ctr) {
        $startDt = new DateTime((string)$ctr['ctr_start']);
        $endDt = new DateTime((string)$ctr['ctr_end']);
        $currentDt = clone $startDt;
        $today = new DateTime('today');
        
        while ($currentDt <= $endDt && $currentDt <= $today) {
            $periodSet[$currentDt->format('Y-m')] = 1;
            $currentDt->modify('+1 month');
        }
    }
    
    // เพิ่มเดือนจาก utility records (ที่มีจดมิเตอร์แล้ว)
    $utilityStmt = $pdo->query("\n        SELECT DISTINCT DATE_FORMAT(utl_date, '%Y-%m') AS ym\n        FROM utility\n        WHERE utl_date IS NOT NULL\n        AND DATE_FORMAT(utl_date, '%Y-%m') <= DATE_FORMAT(CURDATE(), '%Y-%m')\n    ");
    $utilityPeriods = $utilityStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($utilityPeriods as $p) {
        $periodSet[$p['ym']] = 1;
    }
    
    // แปลง periodSet ให้เป็น $periods format
    $periodList = array_keys($periodSet);
    rsort($periodList);
    
    foreach ($periodList as $ym) {
        $dt = new DateTime($ym . '-01');
        $periodYear = (int)$dt->format('Y');
        $periodMonth = (int)$dt->format('m');
        
        if (!isset($availableMonthsByYear[$periodYear])) {
            $availableMonthsByYear[$periodYear] = [];
            $availableYears[] = $periodYear;
        }
        if (!in_array($periodMonth, $availableMonthsByYear[$periodYear], true)) {
            $availableMonthsByYear[$periodYear][] = $periodMonth;
        }
    }
    
} catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }
catch (Exception $e) {}

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
    $rateStmt = $pdo->query("SELECT * FROM rate WHERE effective_date <= CURDATE() ORDER BY effective_date DESC, rate_id DESC LIMIT 1");
    $rate = $rateStmt->fetch(PDO::FETCH_ASSOC);
    if ($rate) {
        $waterRate = (int)$rate['rate_water'];
        $electricRate = (int)$rate['rate_elec'];
    }
} catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }

// ===== Global auto-fix: แก้บิลเดือนแรกที่มีค่าน้ำ/ค่าไฟสูงเกินจริง =====
// รันทุกครั้งที่โหลดหน้า — หาบิลเดือนแรกของทุกสัญญาที่ expense มีค่าน้ำ/ไฟ > 0
// แล้วแก้ให้ exp_water=0, exp_elec_chg=0, exp_total=room_price
try {
    $globalFixStmt = $pdo->query("
        SELECT c.ctr_id,
               MONTH(c.ctr_start) AS start_month,
               YEAR(c.ctr_start)  AS start_year
        FROM contract c
        WHERE c.ctr_start IS NOT NULL
    ");
    $allContracts = $globalFixStmt->fetchAll(PDO::FETCH_ASSOC);
    $fixExpGlobal  = $pdo->prepare("UPDATE expense
        SET exp_water = 0, exp_elec_chg = 0,
            exp_elec_unit = 0, exp_water_unit = 0,
            exp_total = room_price
        WHERE ctr_id = ?
          AND MONTH(exp_month) = ?
          AND YEAR(exp_month)  = ?
          AND (exp_water > 0 OR exp_elec_chg > 0)");
    $fixUtilGlobal = $pdo->prepare("UPDATE utility
        SET utl_water_start = utl_water_end,
            utl_elec_start  = utl_elec_end
        WHERE ctr_id = ?
          AND MONTH(utl_date) = ?
          AND YEAR(utl_date)  = ?
          AND utl_water_start != utl_water_end");
    foreach ($allContracts as $ac) {
        $fixExpGlobal->execute([$ac['ctr_id'], $ac['start_month'], $ac['start_year']]);
        $fixUtilGlobal->execute([$ac['ctr_id'], $ac['start_month'], $ac['start_year']]);
    }
} catch (PDOException $e) {
    error_log('[manage_utility] global first-bill fix error: ' . $e->getMessage());
}

// บันทึกมิเตอร์
$success = '';
$error = '';
$firstBillRooms = []; // Track rooms with first meter reading
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    // แท็บที่กำลังบันทึก: บันทึกเฉพาะมิเตอร์ประเภทนั้น ไม่ปนกัน
    $postTab = (isset($_POST['tab']) && $_POST['tab'] === 'electric') ? 'electric' : 'water';

    try {
        $saved = 0;
        $skipped = 0;
        $lockedRooms = 0;
        $savedRoomsData = [];
        foreach ($_POST['meter'] as $roomId => $data) {
        if (empty($data['ctr_id'])) continue;

        // ห้ามบันทึกมิเตอร์สำหรับห้องที่ยังไม่ผ่าน workflow ถึงขั้นตอนเช็คอิน (step >= 4)
        if ((int)($data['workflow_step'] ?? 0) < 4) continue;

        $waterInput = (isset($data['water']) && $data['water'] !== '') ? (int)$data['water'] : null;
        $elecInput = (isset($data['electric']) && $data['electric'] !== '') ? (int)$data['electric'] : null;

        // บันทึกเฉพาะมิเตอร์ของแท็บที่กด — ไม่บันทึกอีกฝั่งพร้อมกัน
        if ($postTab === 'water') {
            $elecInput = null;
        } else {
            $waterInput = null;
        }

        if ($waterInput === null && $elecInput === null) continue;

        // ตรวจสอบขนาดหลัก: น้ำ 7 หลัก, ไฟ 5 หลัก
        if ($waterInput !== null && ($waterInput < 0 || $waterInput > 9999999)) continue;
        if ($elecInput  !== null && ($elecInput  < 0 || $elecInput  > 99999))  continue;

        // ป้องกันการแก้ไขเดือนที่ผ่านมาแล้ว เฉพาะกรณี: มี record จริง (ไม่ใช่ 0-0 placeholder) + บิลชำระแล้ว
        $postYm = sprintf('%04d-%02d', $year, $month);
        if ($postYm < date('Y-m')) {
            $ctrIdCheck = (int)$data['ctr_id'];
            // ตรวจว่ามี utility record ที่มีค่าจริง (water_end > 0 หรือ elec_end > 0)
            $existChk = $pdo->prepare("SELECT utl_water_end, utl_elec_end FROM utility WHERE ctr_id = ? AND MONTH(utl_date) = ? AND YEAR(utl_date) = ? LIMIT 1");
            $existChk->execute([$ctrIdCheck, $month, $year]);
            $existRow = $existChk->fetch(PDO::FETCH_ASSOC);
            if ($existRow) {
                $hasRealMeterData = (int)($existRow['utl_water_end'] ?? 0) > 0 || (int)($existRow['utl_elec_end'] ?? 0) > 0;
                // ตรวจว่าบิลชำระแล้วหรือยัง
                $pastBillPaid = false;
                $pastBillChk = $pdo->prepare("
                    SELECT e.exp_total,
                           COALESCE((SELECT SUM(p.pay_amount) FROM payment p WHERE p.exp_id = e.exp_id AND p.pay_status = '1' AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ'), 0) AS approved_paid
                    FROM expense e WHERE e.ctr_id = ? AND MONTH(e.exp_month) = ? AND YEAR(e.exp_month) = ? LIMIT 1
                ");
                $pastBillChk->execute([$ctrIdCheck, $month, $year]);
                $pastBillRow = $pastBillChk->fetch(PDO::FETCH_ASSOC);
                if ($pastBillRow && (float)$pastBillRow['approved_paid'] >= (float)$pastBillRow['exp_total'] && (float)$pastBillRow['exp_total'] > 0) {
                    $pastBillPaid = true;
                }
                // ห้ามแก้ไขถ้า: มีค่ามิเตอร์จริงอยู่แล้ว + บิลชำระแล้ว
                if ($hasRealMeterData && $pastBillPaid) continue;
            }
        }
        
        $ctrId = (int)$data['ctr_id'];
        $meterDate = $year . '-' . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . '-' . date('d');
        
        try {
            // prev: ดึงจาก record สมบูรณ์ล่าสุด (ทั้งน้ำและไฟ IS NOT NULL)
            $prevStmt = $pdo->prepare("SELECT utl_water_end, utl_elec_end FROM utility WHERE ctr_id = ? AND utl_water_end IS NOT NULL AND utl_elec_end IS NOT NULL ORDER BY utl_date DESC, utl_id DESC LIMIT 1");
            $prevStmt->execute([$ctrId]);
            $prev = $prevStmt->fetch(PDO::FETCH_ASSOC);

            // Fallback: ถ้าไม่มี utility ของสัญญานี้ → ดึงค่ามิเตอร์ล่าสุดของห้องเดียวกันจากทุกสัญญา
            if (!$prev) {
                $roomPrevPostStmt = $pdo->prepare(
                    "SELECT u.utl_water_end, u.utl_elec_end
                     FROM utility u
                     INNER JOIN contract c ON u.ctr_id = c.ctr_id
                     WHERE c.room_id = ? AND u.utl_water_end IS NOT NULL AND u.utl_elec_end IS NOT NULL
                     ORDER BY u.utl_date DESC, u.utl_id DESC
                     LIMIT 1"
                );
                $roomPrevPostStmt->execute([(int)$roomId]);
                $roomPrevPost = $roomPrevPostStmt->fetch(PDO::FETCH_ASSOC);
                if ($roomPrevPost) {
                    $prev = $roomPrevPost;
                }
            }

            $checkStmt = $pdo->prepare("SELECT utl_id, utl_water_start, utl_water_end, utl_elec_start, utl_elec_end FROM utility WHERE ctr_id = ? AND MONTH(utl_date) = ? AND YEAR(utl_date) = ?");
            $checkStmt->execute([$ctrId, $month, $year]);
            $existing = $checkStmt->fetch();

            // ตรวจสอบว่าเป็นการจดมิเตอร์ครั้งแรกจาก ctr_start
            $ctrStartStmt = $pdo->prepare('SELECT ctr_start FROM contract WHERE ctr_id = ? LIMIT 1');
            $ctrStartStmt->execute([$ctrId]);
            $ctrStartRow = $ctrStartStmt->fetch(PDO::FETCH_ASSOC);
            $ctrStartYmPost = $ctrStartRow ? date('Y-m', strtotime((string)$ctrStartRow['ctr_start'])) : null;
            $currentYmPost = sprintf('%04d-%02d', $year, $month);
            $isFirstReading = $ctrStartYmPost !== null && $currentYmPost === $ctrStartYmPost;

            $doInsert = !$existing;

            if ($existing) {
                // ตรวจว่าฝั่งที่กำลังบันทึกถูกบันทึกแล้วหรือยัง (ค่า > 0 ถือว่าบันทึกแล้ว)
                $thisTabAlreadySaved = ($postTab === 'water')
                    ? ($existing['utl_water_end'] !== null && (int)$existing['utl_water_end'] > 0)
                    : ($existing['utl_elec_end']  !== null && (int)$existing['utl_elec_end']  > 0);

                if ($thisTabAlreadySaved) {
                    // อนุญาตแก้ไขถ้าบิลยังไม่ได้ชำระ (ทั้งเดือนปัจจุบันและเดือนก่อน)
                    $editBillPaid = false;
                    $editBillChk = $pdo->prepare("
                        SELECT e.exp_total,
                               COALESCE((SELECT SUM(p.pay_amount) FROM payment p WHERE p.exp_id = e.exp_id AND p.pay_status = '1' AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ'), 0) AS approved_paid
                        FROM expense e
                        WHERE e.ctr_id = ? AND MONTH(e.exp_month) = ? AND YEAR(e.exp_month) = ?
                        LIMIT 1
                    ");
                    $editBillChk->execute([$ctrId, $month, $year]);
                    $editBillRow = $editBillChk->fetch(PDO::FETCH_ASSOC);
                    if ($editBillRow && (float)$editBillRow['approved_paid'] >= (float)$editBillRow['exp_total'] && (float)$editBillRow['exp_total'] > 0) {
                        $editBillPaid = true;
                    }
                    if ($editBillPaid) {
                        $lockedRooms++;
                        continue;
                    }
                    // บิลยังไม่ชำระ → อนุญาตแก้ไข (partial UPDATE)
                }
                // Record มีอยู่แล้วแต่ฝั่งนี้ยังไม่ได้บันทึก (อีกฝั่งบันทึกก่อน) หรือแก้ไขมิเตอร์เดือนปัจจุบัน → partial UPDATE
                $doInsert = false;
            }

            // fallback: เมื่อไม่มี prev utility และไม่ใช่เดือนแรก ให้ใช้ checkin_record
            $prevWaterEnd = (int)($prev['utl_water_end'] ?? 0);
            $prevElecEnd  = (int)($prev['utl_elec_end']  ?? 0);
            if (!$prev && !$isFirstReading) {
                $chkPost = $pdo->prepare('SELECT water_meter_start, elec_meter_start FROM checkin_record WHERE ctr_id = ? LIMIT 1');
                $chkPost->execute([$ctrId]);
                $chkRowPost = $chkPost->fetch(PDO::FETCH_ASSOC);
                if ($chkRowPost && $chkRowPost['water_meter_start'] !== null) {
                    $prevWaterEnd = (int)$chkRowPost['water_meter_start'];
                    $prevElecEnd  = (int)$chkRowPost['elec_meter_start'];
                }
            }

            $waterOld = isset($data['water_old']) ? (int)$data['water_old'] : $prevWaterEnd;
            $elecOld  = isset($data['elec_old'])  ? (int)$data['elec_old']  : $prevElecEnd;

            // ป้องกัน Partial-Save Bug:
            // กรณีที่อีกฝั่งบันทึกก่อน (เช่น น้ำบันทึกก่อน) ทำให้
            // utl_elec_start/utl_water_start ใน record ปัจจุบันเป็น NULL
            // ค่า hidden form field จะเป็น 0 (จาก (int)NULL) → เกิดข้อมูลผิดพลาด
            // แก้: ถ้า submitted old = 0 แต่ prev month มีค่าจริง → ใช้ prev แทน
            if ($postTab === 'electric' && $elecOld === 0 && $prevElecEnd > 0 && !$isFirstReading) {
                $elecOld = $prevElecEnd;
            }
            if ($postTab === 'water' && $waterOld === 0 && $prevWaterEnd > 0 && !$isFirstReading) {
                $waterOld = $prevWaterEnd;
            }
            // ตรวจจาก existing record: ถ้า start เป็น NULL/0 แต่ prev มีค่า → override
            if ($existing) {
                if ($postTab === 'electric' && (int)($existing['utl_elec_start'] ?? 0) === 0 && $prevElecEnd > 0 && !$isFirstReading) {
                    $elecOld = $prevElecEnd;
                }
                if ($postTab === 'water' && (int)($existing['utl_water_start'] ?? 0) === 0 && $prevWaterEnd > 0 && !$isFirstReading) {
                    $waterOld = $prevWaterEnd;
                }
            }

            if ($postTab === 'water') {
                $waterNew = $waterInput;
                if ($isFirstReading) {
                    if ($waterNew <= 0) continue; // ห้ามบันทึก 0 สำหรับมิเตอร์ครั้งแรก
                    $waterOld = $waterNew;
                }
                // ป้องกัน: ห้ามบันทึกค่าใหม่ต่ำกว่าค่าเดิม (ยกเว้นครั้งแรก)
                if (!$isFirstReading && $waterNew < $waterOld) {
                    $skipped++;
                    continue;
                }
                $waterUsed = $waterNew - $waterOld;
                $waterCost = $isFirstReading ? 0 : calculateWaterCost($waterUsed);
            } else {
                $elecNew = $elecInput;
                if ($isFirstReading) {
                    if ($elecNew <= 0) continue; // ห้ามบันทึก 0 สำหรับมิเตอร์ครั้งแรก
                    $elecOld = $elecNew;
                }
                // ป้องกัน: ห้ามบันทึกค่าใหม่ต่ำกว่าค่าเดิม (ยกเว้นครั้งแรก)
                if (!$isFirstReading && $elecNew < $elecOld) {
                    $skipped++;
                    continue;
                }
                $elecUsed = $elecNew - $elecOld;
                $elecCost = $isFirstReading ? 0 : ($elecUsed * $electricRate);
            }

            if ($doInsert) {
                // INSERT: บันทึกเฉพาะฝั่งที่ active, อีกฝั่ง = NULL
                $insertStmt = $pdo->prepare("INSERT INTO utility (ctr_id, utl_water_start, utl_water_end, utl_elec_start, utl_elec_end, utl_date) VALUES (?, ?, ?, ?, ?, ?)");
                if ($postTab === 'water') {
                    $insertStmt->execute([$ctrId, $waterOld, $waterNew, null, null, $meterDate]);
                } else {
                    $insertStmt->execute([$ctrId, null, null, $elecOld, $elecNew, $meterDate]);
                }
            } else {
                // Partial UPDATE: อัปเดตเฉพาะคอลัมน์ฝั่งที่กำลังบันทึก
                if ($postTab === 'water') {
                    $pdo->prepare("UPDATE utility SET utl_water_start = ?, utl_water_end = ? WHERE utl_id = ?")
                        ->execute([$waterOld, $waterNew, $existing['utl_id']]);
                } else {
                    $pdo->prepare("UPDATE utility SET utl_elec_start = ?, utl_elec_end = ? WHERE utl_id = ?")
                        ->execute([$elecOld, $elecNew, $existing['utl_id']]);
                }
            }

            // UPDATE expense: อัปเดตเฉพาะฝั่งที่บันทึก, คงค่าอีกฝั่งไว้ด้วย COALESCE
            if ($postTab === 'water') {
                $pdo->prepare("
                    UPDATE expense SET
                        exp_water_unit = ?, rate_water = ?, exp_water = ?,
                        exp_total = room_price + ? + COALESCE(exp_elec_chg, 0)
                    WHERE ctr_id = ? AND MONTH(exp_month) = ? AND YEAR(exp_month) = ?
                ")->execute([$waterUsed, $waterRate, $waterCost, $waterCost, $ctrId, $month, $year]);
            } else {
                $pdo->prepare("
                    UPDATE expense SET
                        exp_elec_unit = ?, rate_elec = ?, exp_elec_chg = ?,
                        exp_total = room_price + COALESCE(exp_water, 0) + ?
                    WHERE ctr_id = ? AND MONTH(exp_month) = ? AND YEAR(exp_month) = ?
                ")->execute([$elecUsed, $electricRate, $elecCost, $elecCost, $ctrId, $month, $year]);
            }

            if ($isFirstReading) {
                $firstBillRooms[] = $ctrId;
            }

            // Cascade: อัปเดต utility ของเดือนถัดไป (start = ค่า end ที่เพิ่งบันทึก)
            $nextMo = $month < 12 ? $month + 1 : 1;
            $nextYr = $month < 12 ? $year : $year + 1;
            $cascadeStmt = $pdo->prepare("SELECT utl_id FROM utility WHERE ctr_id = ? AND MONTH(utl_date) = ? AND YEAR(utl_date) = ? LIMIT 1");
            $cascadeStmt->execute([$ctrId, $nextMo, $nextYr]);
            $nextUtl = $cascadeStmt->fetch(PDO::FETCH_ASSOC);
            if ($nextUtl) {
                if ($postTab === 'water') {
                    $pdo->prepare("UPDATE utility SET utl_water_start = ? WHERE utl_id = ?")
                        ->execute([$waterNew, $nextUtl['utl_id']]);
                    // คำนวณค่าน้ำใหม่สำหรับเดือนถัดไป
                    $nextUtlFull = $pdo->prepare("SELECT utl_water_end FROM utility WHERE utl_id = ?");
                    $nextUtlFull->execute([$nextUtl['utl_id']]);
                    $nextUtlRow = $nextUtlFull->fetch(PDO::FETCH_ASSOC);
                    if ($nextUtlRow && $nextUtlRow['utl_water_end'] !== null && (int)$nextUtlRow['utl_water_end'] > 0) {
                        $nextWaterUsed = (int)$nextUtlRow['utl_water_end'] - $waterNew;
                        $nextWaterCost = calculateWaterCost($nextWaterUsed);
                        $pdo->prepare("UPDATE expense SET exp_water_unit = ?, exp_water = ?, exp_total = room_price + ? + COALESCE(exp_elec_chg, 0) WHERE ctr_id = ? AND MONTH(exp_month) = ? AND YEAR(exp_month) = ?")
                            ->execute([$nextWaterUsed, $nextWaterCost, $nextWaterCost, $ctrId, $nextMo, $nextYr]);
                    }
                } else {
                    $pdo->prepare("UPDATE utility SET utl_elec_start = ? WHERE utl_id = ?")
                        ->execute([$elecNew, $nextUtl['utl_id']]);
                    // คำนวณค่าไฟใหม่สำหรับเดือนถัดไป
                    $nextUtlFull = $pdo->prepare("SELECT utl_elec_end FROM utility WHERE utl_id = ?");
                    $nextUtlFull->execute([$nextUtl['utl_id']]);
                    $nextUtlRow = $nextUtlFull->fetch(PDO::FETCH_ASSOC);
                    if ($nextUtlRow && $nextUtlRow['utl_elec_end'] !== null && (int)$nextUtlRow['utl_elec_end'] > 0) {
                        $nextElecUsed = (int)$nextUtlRow['utl_elec_end'] - $elecNew;
                        $nextElecCost = $nextElecUsed * $electricRate;
                        $pdo->prepare("UPDATE expense SET exp_elec_unit = ?, exp_elec_chg = ?, exp_total = room_price + COALESCE(exp_water, 0) + ? WHERE ctr_id = ? AND MONTH(exp_month) = ? AND YEAR(exp_month) = ?")
                            ->execute([$nextElecUsed, $nextElecCost, $nextElecCost, $ctrId, $nextMo, $nextYr]);
                    }
                }
            }
            
            // เก็บข้อมูลห้องที่บันทึกสำเร็จ
            $savedRoomsData[$roomId] = [
                'room_id' => (int)$roomId,
                'tab' => $postTab,
                'old' => $postTab === 'water' ? $waterOld : $elecOld,
                'new' => $postTab === 'water' ? $waterNew : $elecNew,
                'usage' => $postTab === 'water' ? ($isFirstReading ? 0 : $waterUsed) : ($isFirstReading ? 0 : $elecUsed),
                'cost' => $postTab === 'water' ? ($isFirstReading ? 0 : $waterCost) : ($isFirstReading ? 0 : $elecCost),
                'is_first_reading' => $isFirstReading,
            ];

            $saved++;
        } catch (PDOException $e) {
            $error = $e->getMessage();
        }
    }
} catch (Throwable $e) {
    error_log('[manage_utility][POST] ' . $e->getMessage());
    $error = 'เกิดข้อผิดพลาดในการบันทึกมิเตอร์: ' . $e->getMessage();
}
    // AJAX request → ส่ง JSON กลับโดยไม่ redirect
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        $msg = '';
        if ($saved > 0) {
            $msg = "บันทึกสำเร็จ {$saved} ห้อง";
            if ($lockedRooms > 0) {
                $msg .= " (ข้าม {$lockedRooms} ห้องที่บิลชำระแล้ว)";
            }
        } elseif ($lockedRooms > 0) {
            $msg = "ไม่สามารถแก้ไขได้: {$lockedRooms} ห้องที่บิลชำระแล้ว";
        } elseif ($error) {
            $msg = $error;
        } else {
            $msg = 'ไม่มีข้อมูลที่บันทึก';
        }
        echo json_encode([
            'success' => $saved > 0,
            'message' => $msg,
            'saved' => $saved,
            'lockedRooms' => $lockedRooms,
            'tab' => $postTab,
            'rooms' => $savedRoomsData,
            'error' => $error ?: null,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Non-AJAX fallback → redirect ตามเดิม
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
        SELECT r.room_id, r.room_number, c.ctr_id, c.ctr_start, t.tnt_name, COALESCE(tw.current_step, 1) AS workflow_step
        FROM room r
        JOIN (
            SELECT room_id, MAX(ctr_id) AS ctr_id
            FROM contract
            WHERE ctr_status = '0'
            GROUP BY room_id
        ) lc ON r.room_id = lc.room_id
        JOIN contract c ON c.ctr_id = lc.ctr_id
        LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
        LEFT JOIN (
            SELECT tnt_id, MAX(id) as max_tw_id
            FROM tenant_workflow
            GROUP BY tnt_id
        ) latest_tw ON t.tnt_id = latest_tw.tnt_id
        LEFT JOIN tenant_workflow tw ON latest_tw.max_tw_id = tw.id
        WHERE c.ctr_start <= LAST_DAY(STR_TO_DATE(CONCAT(?, '-', ?), '%Y-%m'))
        AND c.ctr_end >= STR_TO_DATE(CONCAT(?, '-', '01'), '%Y-%m-%d')
        AND EXISTS (SELECT 1 FROM checkin_record cr WHERE cr.ctr_id = c.ctr_id
            AND cr.water_meter_start IS NOT NULL AND cr.elec_meter_start IS NOT NULL)
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
        SELECT r.room_id, r.room_number, c.ctr_id, c.ctr_start, COALESCE(t.tnt_name, '') as tnt_name, COALESCE(tw.current_step, 1) AS workflow_step
        FROM room r
        LEFT JOIN (
            SELECT room_id, MAX(ctr_id) AS ctr_id
            FROM contract
            WHERE ctr_status = '0'
            AND ctr_start <= LAST_DAY(STR_TO_DATE(CONCAT(?, '-', ?), '%Y-%m'))
            AND ctr_end >= STR_TO_DATE(CONCAT(?, '-', '01'), '%Y-%m-%d')
            AND EXISTS (SELECT 1 FROM checkin_record cr WHERE cr.ctr_id = contract.ctr_id
            AND cr.water_meter_start IS NOT NULL AND cr.elec_meter_start IS NOT NULL)
            GROUP BY room_id
        ) lc ON r.room_id = lc.room_id
        LEFT JOIN contract c ON c.ctr_id = lc.ctr_id
        LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
        LEFT JOIN (
            SELECT tnt_id, MAX(id) as max_tw_id
            FROM tenant_workflow
            GROUP BY tnt_id
        ) latest_tw ON t.tnt_id = latest_tw.tnt_id
        LEFT JOIN tenant_workflow tw ON latest_tw.max_tw_id = tw.id
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
$isPastMonth = sprintf('%04d-%02d', $year, $month) < date('Y-m');
foreach ($rooms as $room) {
    if (!$room['ctr_id']) {
        $readings[$room['room_id']] = ['water_old' => 0, 'elec_old' => 0, 'water_new' => '', 'elec_new' => '', 'saved' => false, 'water_saved' => false, 'elec_saved' => false, 'workflow_step' => 1, 'meter_blocked' => false, 'isFirstReading' => false];
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
    
    // (meterBlocked will be set after $hasRealData is determined below)

    $targetMonthStart = sprintf('%04d-%02d-01', $year, $month);

    // คำนวณเดือน/ปีที่แล้ว
    $prevMonth = $month > 1 ? $month - 1 : 12;
    $prevYear = $month > 1 ? $year : $year - 1;

    // ดึงค่า "ก่อนหน้า" จากเดือนก่อนหน้า (ไม่ว่า utl_date จะเป็นวันไหนก็ตาม)
    $prevStmt = $pdo->prepare("SELECT utl_water_end, utl_elec_end FROM utility WHERE ctr_id = ? AND MONTH(utl_date) = ? AND YEAR(utl_date) = ? ORDER BY utl_date DESC LIMIT 1");
    $prevStmt->execute([$room['ctr_id'], $prevMonth, $prevYear]);
    $prev = $prevStmt->fetch(PDO::FETCH_ASSOC);

    // Fallback: ถ้าไม่มี utility เดือนก่อนของสัญญานี้ → ดึงค่ามิเตอร์ล่าสุดของห้องเดียวกันจากทุกสัญญา
    // รองรับกรณีผู้เช่าเก่าคืนห้อง แล้วผู้เช่าใหม่เข้า — ค่ามิเตอร์ต้องต่อเนื่อง
    if (!$prev) {
        $roomPrevStmt = $pdo->prepare(
            "SELECT u.utl_water_end, u.utl_elec_end
             FROM utility u
             INNER JOIN contract c ON u.ctr_id = c.ctr_id
             WHERE c.room_id = ? AND u.utl_date < ?
             ORDER BY u.utl_date DESC, u.utl_id DESC
             LIMIT 1"
        );
        $roomPrevStmt->execute([$room['room_id'], $targetMonthStart]);
        $roomPrev = $roomPrevStmt->fetch(PDO::FETCH_ASSOC);
        if ($roomPrev) {
            $prev = $roomPrev;
        }
    }
    
    $currentStmt = $pdo->prepare("SELECT utl_water_start, utl_water_end, utl_elec_start, utl_elec_end FROM utility WHERE ctr_id = ? AND MONTH(utl_date) = ? AND YEAR(utl_date) = ?");
    $currentStmt->execute([$room['ctr_id'], $month, $year]);
    $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if มี utility record สำหรับเดือนนี้ (รวมถึง first reading ที่ start == end)
    $hasRecord = ($current !== false);
    // Check if มีค่าจริง (start != end) — ใช้สำหรับ water_old override
    $hasRealData = $current && (
        ((int)$current['utl_water_end'] !== (int)$current['utl_water_start']) ||
        ((int)$current['utl_elec_end'] !== (int)$current['utl_elec_start'])
    );
    
    // การจดมิเตอร์ครั้งแรก = เดือนปัจจุบันตรงกับเดือนเริ่มสัญญาเท่านั้น (ต้องกำหนดก่อน isAllZeroFirstRecord)
    $ctrStartYm = !empty($room['ctr_start']) ? date('Y-m', strtotime((string)$room['ctr_start'])) : null;
    $currentYm = sprintf('%04d-%02d', $year, $month);
    $isFirstReading = $ctrStartYm !== null && $currentYm === $ctrStartYm;
    // แสดงค่า input จาก utility record — ตรวจ NULL แยกต่างหากเพื่อรองรับ partial save
    $water_new = ($hasRecord && $current['utl_water_end'] !== null) ? (int)$current['utl_water_end'] : '';
    $elec_new  = ($hasRecord && $current['utl_elec_end']  !== null) ? (int)$current['utl_elec_end']  : '';
    // สำหรับเดือนแรก: ถ้าค่า end ทั้งน้ำและไฟเป็น 0 ทั้งคู่ → ถือว่ายังไม่ได้จดจริง (อาจถูกสร้างอัตโนมัติตอน checkin ด้วยค่า 0)
    $isAllZeroFirstRecord = $isFirstReading && $hasRecord
        && (int)($current['utl_water_end'] ?? 0) === 0
        && (int)($current['utl_elec_end']  ?? 0) === 0;
    // ถ้าเป็น all-zero first record → แสดง input ว่าง เพื่อให้กรอกค่าจริงได้
    if ($isAllZeroFirstRecord) {
        $water_new = '';
        $elec_new  = '';
    }
    // saved ต้องมีค่า > 0 — ค่า 0 ถือว่ายังไม่ได้จดมิเตอร์จริง (ยกเว้น first reading ที่ start=end=0 ซึ่งถือว่า valid ถ้าทั้งคู่ 0)
    $water_saved = $hasRecord && (int)($current['utl_water_end'] ?? 0) > 0 && !$isAllZeroFirstRecord;
    $elec_saved  = $hasRecord && (int)($current['utl_elec_end']  ?? 0) > 0 && !$isAllZeroFirstRecord;
    // ถ้า input ที่แสดงเป็น 0 แต่ค่า start ก็ 0 ด้วย (first reading, ไม่ได้ใช้จริง) → ซ่อน input 0 ออก
    if (!$water_saved && $water_new === 0) $water_new = '';
    if (!$elec_saved  && $elec_new  === 0) $elec_new  = '';
    $saved = $water_saved && $elec_saved;  // ทั้งสองฝั่งบันทึกแล้ว
    // ล็อคเดือนที่ผ่านมาเฉพาะเมื่อ saved ครบทั้งคู่
    $meterBlocked = $isPastMonth && $saved;
    
    // Fallback: ถ้าเดือนปัจจุบันยังไม่บันทึก แต่เดือนถัดไปบันทึกแล้ว → ใช้ค่าจากเดือนถัดไป
    if (!$hasRecord && $water_new === '') {
        $nextMonth = $month < 12 ? $month + 1 : 1;
        $nextYear = $month < 12 ? $year : $year + 1;
        $nextStmt = $pdo->prepare("SELECT utl_water_end, utl_elec_end FROM utility WHERE ctr_id = ? AND MONTH(utl_date) = ? AND YEAR(utl_date) = ? ORDER BY utl_date DESC LIMIT 1");
        $nextStmt->execute([$room['ctr_id'], $nextMonth, $nextYear]);
        $next = $nextStmt->fetch(PDO::FETCH_ASSOC);
        if ($next) {
            $water_new = (int)$next['utl_water_end'];
            $elec_new = (int)$next['utl_elec_end'];
        }
    }
    
    // Auto-fix: บิลครั้งแรกที่บันทึกโดยคิดค่าน้ำ/ค่าไฟผิด — แก้ไขอัตโนมัติเมื่อโหลดหน้า
    if ($isFirstReading && $hasRecord && $current) {
        $needsFix = ((int)$current['utl_water_start'] !== (int)$current['utl_water_end']) ||
                    ((int)$current['utl_elec_start']  !== (int)$current['utl_elec_end']);
        if (!$needsFix) {
            $expChkStmt = $pdo->prepare("SELECT exp_water, exp_elec_chg FROM expense WHERE ctr_id = ? AND MONTH(exp_month) = ? AND YEAR(exp_month) = ? LIMIT 1");
            $expChkStmt->execute([$room['ctr_id'], $month, $year]);
            $expChkRow = $expChkStmt->fetch(PDO::FETCH_ASSOC);
            $needsFix = $expChkRow && ((float)$expChkRow['exp_water'] > 0 || (float)$expChkRow['exp_elec_chg'] > 0);
        }
        if ($needsFix) {
            // แก้ utility: start = end เพื่อให้หน่วยที่ใช้ = 0
            $autoFixUtil = $pdo->prepare("UPDATE utility SET utl_water_start = utl_water_end, utl_elec_start = utl_elec_end WHERE ctr_id = ? AND MONTH(utl_date) = ? AND YEAR(utl_date) = ?");
            $autoFixUtil->execute([$room['ctr_id'], $month, $year]);
            // แก้ expense: ค่าน้ำ/ค่าไฟ = 0, ยอดรวม = ค่าห้องอย่างเดียว
            $autoFixExp = $pdo->prepare("UPDATE expense SET exp_water = 0, exp_elec_chg = 0, exp_elec_unit = 0, exp_water_unit = 0, exp_total = room_price WHERE ctr_id = ? AND MONTH(exp_month) = ? AND YEAR(exp_month) = ?");
            $autoFixExp->execute([$room['ctr_id'], $month, $year]);
            // Re-fetch current เพื่อให้ water_old แสดงถูกต้อง (start = end = ค่ามิเตอร์ปัจจุบัน)
            $currentStmt->execute([$room['ctr_id'], $month, $year]);
            $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    // สำหรับเดือนแรก: ถ้ายังไม่มี utility record ให้ตรวจสอบ checkin_record
    // ถ้า checkin_record มีค่ามิเตอร์ครบทั้งน้ำและไฟ → ถือว่าจดมิเตอร์แล้ว
    if ($isFirstReading && !$saved) {
        $chkFirst = $pdo->prepare('SELECT water_meter_start, elec_meter_start FROM checkin_record WHERE ctr_id = ? ORDER BY checkin_id DESC LIMIT 1');
        $chkFirst->execute([$room['ctr_id']]);
        $chkFirstRow = $chkFirst->fetch(PDO::FETCH_ASSOC);
        if ($chkFirstRow && (int)$chkFirstRow['water_meter_start'] > 0 && (int)$chkFirstRow['elec_meter_start'] > 0) {
            $saved = true;
            $water_saved = true;
            $elec_saved  = true;
            $water_new = (int)$chkFirstRow['water_meter_start'];
            $elec_new  = (int)$chkFirstRow['elec_meter_start'];
            $meterBlocked = $isPastMonth; // ปิดกั้นถ้าเป็นเดือนที่ผ่านแล้ว
        }
    }

    // ค่าเริ่มต้น: ดึงจาก utility เดือนก่อน หรือ checkin_record (ถ้าไม่มี utility เดือนก่อน)
    $water_old_value = $prev ? (int)$prev['utl_water_end'] : 0;
    $elec_old_value  = $prev ? (int)$prev['utl_elec_end']  : 0;
    if (!$prev && !$isFirstReading) {
        // ไม่มี utility เดือนก่อน แต่ไม่ใช่เดือนแรก — ดึงค่า checkin เป็น prev
        $chkFallback = $pdo->prepare('SELECT water_meter_start, elec_meter_start FROM checkin_record WHERE ctr_id = ? LIMIT 1');
        $chkFallback->execute([$room['ctr_id']]);
        $chkRow = $chkFallback->fetch(PDO::FETCH_ASSOC);
        if ($chkRow && $chkRow['water_meter_start'] !== null) {
            $water_old_value = (int)$chkRow['water_meter_start'];
            $elec_old_value  = (int)$chkRow['elec_meter_start'];
        }
    }

    // ตรวจสอบว่าบิลเดือนนี้ชำระแล้วหรือยัง (approved non-deposit payments >= exp_total)
    $billPaid = false;
    if ($room['ctr_id']) {
        $billPaidStmt = $pdo->prepare("
            SELECT e.exp_total,
                   COALESCE((SELECT SUM(p.pay_amount) FROM payment p WHERE p.exp_id = e.exp_id AND p.pay_status = '1' AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ'), 0) AS approved_paid
            FROM expense e
            WHERE e.ctr_id = ? AND MONTH(e.exp_month) = ? AND YEAR(e.exp_month) = ?
            LIMIT 1
        ");
        $billPaidStmt->execute([$room['ctr_id'], $month, $year]);
        $billPaidRow = $billPaidStmt->fetch(PDO::FETCH_ASSOC);
        if ($billPaidRow && (float)$billPaidRow['approved_paid'] >= (float)$billPaidRow['exp_total'] && (float)$billPaidRow['exp_total'] > 0) {
            $billPaid = true;
        }
    }

    // อนุญาตแก้ไขมิเตอร์: บิลยังไม่ได้ชำระ → ปลดล็อค (ทั้งเดือนปัจจุบันและเดือนที่ผ่านมา)
    $canEditSaved = !$billPaid;

    $readings[$room['room_id']] = [
        'water_old' => $water_old_value,
        'elec_old' => $elec_old_value,
        'water_new' => $water_new,
        'elec_new' => $elec_new,
        'saved' => $saved,
        'water_saved' => $water_saved ?? $saved,
        'elec_saved'  => $elec_saved  ?? $saved,
        'workflow_step' => $workflowStep,
        'meter_blocked' => $meterBlocked,
        'isFirstReading' => $isFirstReading,
        'bill_paid' => $billPaid,
        'can_edit_saved' => $canEditSaved,
    ];

    // เมื่อบันทึกแล้ว ใช้ water_start/elec_start จาก utility record ปัจจุบัน
    // ถ้า start เป็น NULL หรือ 0 แต่ค่า prev มีค่าจริง → ใช้ค่า prev แทน (ป้องกัน partial-save override)
    if ($hasRealData && !$meterBlocked) {
        $curWaterStart = ($current['utl_water_start'] !== null && (int)$current['utl_water_start'] > 0)
            ? (int)$current['utl_water_start'] : $water_old_value;
        $curElecStart  = ($current['utl_elec_start']  !== null && (int)$current['utl_elec_start']  > 0)
            ? (int)$current['utl_elec_start']  : $elec_old_value;
        $readings[$room['room_id']]['water_old'] = $curWaterStart;
        $readings[$room['room_id']]['elec_old']  = $curElecStart;

        // Self-heal: แก้ DB ถ้า start ถูกบันทึกเป็น 0/NULL แต่มีค่า prev ที่ถูกต้อง
        if ($curWaterStart > 0 && (int)($current['utl_water_start'] ?? 0) === 0) {
            $pdo->prepare("UPDATE utility SET utl_water_start = ? WHERE ctr_id = ? AND MONTH(utl_date) = ? AND YEAR(utl_date) = ?")
                ->execute([$curWaterStart, $room['ctr_id'], $month, $year]);
        }
        if ($curElecStart > 0 && (int)($current['utl_elec_start'] ?? 0) === 0) {
            $pdo->prepare("UPDATE utility SET utl_elec_start = ? WHERE ctr_id = ? AND MONTH(utl_date) = ? AND YEAR(utl_date) = ?")
                ->execute([$curElecStart, $room['ctr_id'], $month, $year]);
        }
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
} catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }

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
            z-index: 2;
            pointer-events: auto !important;
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
        .meter-input-field.editable-saved {
            background: #fffbeb !important;
            border-color: #f59e0b !important;
            color: #1e293b !important;
            cursor: text;
        }
        .meter-input-field.blocked-by-step::placeholder {
            color: #d97706 !important;
        }
        .usage-cell { font-weight: 700; color: #0277bd; }
        .usage-cell.elec-usage { color: #c2185b; }

        /* Highlight rows that still need meter entries */
        @keyframes needsMeterPulse {
            0%, 100% { box-shadow: inset 4px 0 0 0 currentColor; opacity: 1; }
            50% { box-shadow: inset 8px 0 0 0 currentColor; opacity: 0.85; }
        }
        @keyframes needsBorderGlow {
            0%, 100% { border-left-width: 5px; }
            50% { border-left-width: 8px; }
        }
        @keyframes needsShimmer {
            0% { background-position: -200% center; }
            100% { background-position: 200% center; }
        }
        @keyframes needsInputPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(2,136,209,0.3); }
            50% { box-shadow: 0 0 0 5px rgba(2,136,209,0); }
        }
        @keyframes needsInputPulseElec {
            0%, 100% { box-shadow: 0 0 0 0 rgba(251,146,60,0.35); }
            50% { box-shadow: 0 0 0 5px rgba(251,146,60,0); }
        }
        @keyframes badgeBounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.08); }
        }

        .meter-table tbody tr.needs-meter {
            transition: background 0.2s ease;
            position: relative;
        }

        /* Badge "ยังไม่จด" */
        .needs-meter-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 0.68rem;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 20px;
            margin-left: 6px;
            vertical-align: middle;
            animation: badgeBounce 1.8s ease-in-out infinite;
            white-space: nowrap;
        }
        .needs-water .needs-meter-badge {
            background: #fff;
            color: #0277bd;
            border: 1.5px solid #0288d1;
            box-shadow: 0 0 6px rgba(2,136,209,0.25);
        }
        .needs-electric .needs-meter-badge {
            background: #fff;
            color: #c2410c;
            border: 1.5px solid #fb923c;
            box-shadow: 0 0 6px rgba(251,146,60,0.3);
        }

        /* Water missing: blue accent */
        .meter-table tbody tr.needs-meter.needs-water {
            background: linear-gradient(
                100deg,
                rgba(219,242,255,0.95) 0%,
                rgba(240,250,255,0.85) 40%,
                rgba(219,242,255,0.95) 80%,
                rgba(240,250,255,0.85) 100%
            );
            background-size: 200% auto;
            animation: needsShimmer 3.5s linear infinite, needsBorderGlow 1.6s ease-in-out infinite;
            border-left: 5px solid #0288d1;
        }
        .meter-table tbody tr.needs-meter.needs-water .room-num-cell {
            color: #01579b;
            font-weight: 800;
        }
        .meter-table tbody tr.needs-meter.needs-water .usage-cell { color: #01579b; font-weight: 800; }
        .meter-table tbody tr.needs-meter.needs-water .meter-input-field {
            background: linear-gradient(135deg, #e1f5fe 0%, #f0fbff 100%);
            border-color: #0288d1;
            border-width: 2px;
            animation: needsInputPulse 2s ease-in-out infinite;
        }

        /* Electric missing: orange accent */
        .meter-table tbody tr.needs-meter.needs-electric {
            background: linear-gradient(
                100deg,
                rgba(255,239,214,0.95) 0%,
                rgba(255,249,235,0.85) 40%,
                rgba(255,239,214,0.95) 80%,
                rgba(255,249,235,0.85) 100%
            );
            background-size: 200% auto;
            animation: needsShimmer 3.5s linear infinite, needsBorderGlow 1.6s ease-in-out infinite;
            border-left: 5px solid #fb923c;
        }
        .meter-table tbody tr.needs-meter.needs-electric .room-num-cell {
            color: #b45309;
            font-weight: 800;
        }
        .meter-table tbody tr.needs-meter.needs-electric .usage-cell { color: #b45309; font-weight: 800; }
        .meter-table tbody tr.needs-meter.needs-electric .meter-input-field {
            background: linear-gradient(135deg, #fff3e0 0%, #fffbf0 100%);
            border-color: #fb923c;
            border-width: 2px;
            animation: needsInputPulseElec 2s ease-in-out infinite;
        }

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
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

        /* Responsive */
        @media (max-width: 768px) {
