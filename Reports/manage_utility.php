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
    
} catch (PDOException $e) {}
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
    $rateStmt = $pdo->query("SELECT * FROM rate ORDER BY effective_date DESC LIMIT 1");
    $rate = $rateStmt->fetch(PDO::FETCH_ASSOC);
    if ($rate) {
        $waterRate = (int)$rate['rate_water'];
        $electricRate = (int)$rate['rate_elec'];
    }
} catch (PDOException $e) {}

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
        LEFT JOIN tenant_workflow tw ON c.tnt_id = tw.tnt_id
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

        /* ===== Visual Meter View ===== */
        .view-toggle-bar {
            display: flex;
            justify-content: center;
            margin: 0.75rem 1rem 0;
        }
        .view-toggle-btn {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.65rem 1.8rem 0.65rem 1.5rem;
            border: none;
            border-radius: 50px;
            background: linear-gradient(135deg, rgba(255,255,255,0.92) 0%, rgba(240,245,255,0.96) 100%);
            color: #4f46e5;
            font-size: 0.87rem;
            font-weight: 800;
            letter-spacing: 0.4px;
            cursor: pointer;
            overflow: hidden;
            transition: color 0.2s, transform 0.18s, box-shadow 0.2s;
            box-shadow:
                0 0 0 2.5px #a5b4fc,
                0 4px 18px rgba(99,102,241,0.22),
                0 1px 4px rgba(0,0,0,0.08);
            outline: none;
            z-index: 0;
        }
        /* spinning rainbow border */
        .view-toggle-btn::before {
            content: '';
            position: absolute;
            inset: -3px;
            border-radius: 54px;
            background: conic-gradient(from var(--vtb-angle, 0deg),
                #6366f1 0%, #8b5cf6 20%, #ec4899 40%, #f59e0b 60%, #10b981 80%, #6366f1 100%);
            z-index: -1;
            animation: vtbSpin 3s linear infinite;
        }
        @property --vtb-angle {
            syntax: '<angle>';
            initial-value: 0deg;
            inherits: false;
        }
        @keyframes vtbSpin {
            to { --vtb-angle: 360deg; }
        }
        /* inner fill sits on top of border */
        .view-toggle-btn::after {
            content: '';
            position: absolute;
            inset: 2.5px;
            border-radius: 48px;
            background: linear-gradient(135deg, #ffffff 0%, #f0f4ff 100%);
            z-index: -1;
        }
        .view-toggle-btn:hover {
            transform: translateY(-2px) scale(1.03);
            box-shadow:
                0 0 0 2.5px #818cf8,
                0 8px 28px rgba(99,102,241,0.35),
                0 2px 8px rgba(0,0,0,0.1);
        }
        .view-toggle-btn:hover::before {
            animation-duration: 1.4s;
        }
        .view-toggle-btn.active {
            color: #7c3aed;
            box-shadow:
                0 0 0 2.5px #a78bfa,
                0 0 20px rgba(139,92,246,0.45),
                0 0 45px rgba(139,92,246,0.18),
                0 4px 18px rgba(0,0,0,0.1);
        }
        .view-toggle-btn.active::before {
            background: conic-gradient(from var(--vtb-angle, 0deg),
                #7c3aed 0%, #a855f7 25%, #ec4899 55%, #7c3aed 100%);
            animation-duration: 2s;
        }
        .view-toggle-btn.active::after {
            background: linear-gradient(135deg, #fdf4ff 0%, #ede9fe 100%);
        }
        .view-toggle-btn svg {
            width: 16px; height: 16px;
            flex-shrink: 0;
            transition: transform 0.4s cubic-bezier(.34,1.56,.64,1);
            filter: drop-shadow(0 0 3px rgba(99,102,241,0.4));
            stroke: #374151 !important;
        }
        .view-toggle-btn:hover svg { transform: rotate(22deg) scale(1.2); stroke: #374151 !important; }
        .view-toggle-btn.active svg { filter: drop-shadow(0 0 5px rgba(168,85,247,0.75)); stroke: #374151 !important; }
        /* shimmer sweep */
        .view-toggle-btn .vtb-shimmer {
            position: absolute;
            top: 0; left: -80%;
            width: 55%; height: 100%;
            background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.55) 50%, transparent 100%);
            z-index: 1;
            animation: vtbShimmer 2.8s ease-in-out infinite;
            pointer-events: none;
        }
        @keyframes vtbShimmer {
            0%   { left: -80%; opacity: 0; }
            20%  { opacity: 1; }
            60%  { left: 130%; opacity: 0; }
            100% { left: 130%; opacity: 0; }
        }
        .view-toggle-btn .vtb-label {
            position: relative;
            z-index: 2;
            background: linear-gradient(90deg, #4f46e5, #7c3aed, #ec4899);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .view-toggle-btn.active .vtb-label {
            background: linear-gradient(90deg, #7c3aed, #a855f7, #ec4899);
            -webkit-background-clip: text;
            background-clip: text;
        }

        .vm-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1rem;
            padding: 0.75rem 1rem;
        }
        @keyframes vmPendingPulse {
            0%,100% { box-shadow: 0 0 0 0 rgba(239,68,68,0.1); }
            50%      { box-shadow: 0 0 10px 4px rgba(239,68,68,0.12); }
        }
        @keyframes vmRibbonBlink {
            0%,100% { opacity: 1; } 50% { opacity: 0.78; }
        }
        @keyframes vmSavedGlow {
            0%,100% { box-shadow: 0 0 0 0 rgba(34,197,94,0.1); }
            50%      { box-shadow: 0 0 10px 3px rgba(34,197,94,0.2); }
        }
        .vm-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 1rem;
            transition: box-shadow 0.2s, border-color 0.2s;
            position: relative;
            overflow: hidden;
        }
        .vm-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.08); }

        /* ===== PENDING — ยังไม่จด (ต้องการความสนใจ) ===== */
        .vm-card.vm-pending {
            border: 2px solid #fca5a5;
            background: linear-gradient(135deg, #fff5f5 0%, #fff 40%, #fff5f5 100%);
            background-size: 600px 100%;
            border-left: 4px solid #ef4444;
            animation: vmPendingPulse 2.2s ease-in-out infinite;
            position: relative;
        }
        .vm-card.vm-pending::before {
            content: '⚠ ยังไม่จด';
            position: absolute;
            top: 0; right: 0;
            background: linear-gradient(90deg, #ef4444, #f97316);
            color: #fff;
            font-size: 0.62rem;
            font-weight: 700;
            padding: 3px 10px 3px 8px;
            border-radius: 0 14px 0 10px;
            letter-spacing: 0.4px;
            box-shadow: 0 2px 6px rgba(239,68,68,0.3);
            animation: vmRibbonBlink 2.4s ease-in-out infinite;
            z-index: 2;
        }
        .vm-card.vm-pending .vm-room-num { color: #dc2626; }
        .vm-card.vm-pending .vm-card-header { border-bottom-color: #fecaca; }
        .vm-card.vm-pending .vm-dial-water {
            border-color: #ef4444;
            box-shadow: 0 0 0 1px rgba(239,68,68,0.4), inset 0 2px 6px rgba(0,0,0,0.3), 0 0 12px rgba(239,68,68,0.2), 0 6px 20px rgba(239,68,68,0.15);
        }
        .vm-card.vm-pending .vm-dial-face {
            box-shadow: 0 0 0 1px #b71c1c, 0 0 0 5px #ef4444, 0 0 0 5.5px #f87171, 0 0 0 6px #dc2626, inset 0 2px 8px rgba(0,0,0,0.08);
        }
        .vm-card.vm-pending .vm-elec-frame {
            border-color: #f87171;
            box-shadow: inset 0 1px 4px rgba(255,255,255,0.7), 0 0 12px rgba(239,68,68,0.2), 0 5px 18px rgba(239,68,68,0.1);
        }

        /* ===== SAVED — บันทึกแล้ว (เรียบร้อย) ===== */
        .vm-card.vm-saved {
            border: 2px solid #86efac;
            border-left: 4px solid #22c55e;
            background: linear-gradient(135deg, #f0fdf4 0%, #fff 50%, #f0fdf4 100%);
            animation: vmSavedGlow 3s ease-in-out infinite;
        }
        .vm-card.vm-saved .vm-room-num { color: #15803d; }
        .vm-card.vm-saved .vm-card-header { border-bottom-color: #bbf7d0; }
        .vm-card.vm-saved .vm-dial-water {
            border-color: #22c55e;
            box-shadow: 0 0 0 1px rgba(34,197,94,0.3), inset 0 2px 6px rgba(0,0,0,0.2), 0 0 10px rgba(34,197,94,0.15), 0 6px 16px rgba(34,197,94,0.1);
        }
        .vm-card.vm-saved .vm-dial-face {
            box-shadow: 0 0 0 1px #15803d, 0 0 0 5px #22c55e, 0 0 0 5.5px #4ade80, 0 0 0 6px #16a34a, inset 0 2px 8px rgba(0,0,0,0.06);
        }
        .vm-card.vm-saved .vm-elec-frame {
            border-color: #86efac;
            box-shadow: inset 0 1px 4px rgba(255,255,255,0.7), 0 0 10px rgba(34,197,94,0.18), 0 5px 16px rgba(34,197,94,0.1);
        }
        .vm-card.vm-saved .vm-dial-deco { color: #4ade80; opacity: 0.9; }

        .vm-card.vm-empty { opacity: 0.45; }
        .vm-card.vm-empty .vm-digit { cursor: default; }
        .vm-card.vm-empty .vm-card-header::after { content: 'ห้องว่าง'; font-size: 0.65rem; color: #94a3b8; font-weight: 500; background: #f1f5f9; padding: 2px 8px; border-radius: 8px; }
        .vm-card.vm-blocked { border: 2px solid #fbbf24; border-left: 4px solid #f59e0b; background: #fffbeb; }
        .vm-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #f3f4f6;
        }
        .vm-room-num { font-size: 1.15rem; font-weight: 800; color: #1e293b; }
        .vm-first-badge { font-size: 0.7rem; background: #fef3c7; color: #92400e; padding: 0.15rem 0.5rem; border-radius: 10px; font-weight: 600; }
        .vm-saved-badge { font-size: 0.7rem; background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 10px; font-weight: 700; border: 1px solid #86efac; }
        .vm-meters { display: flex; gap: 0.75rem; }
        .vm-meter-section { flex: 1; }

        /* ===============================================================
           HYPER-REALISTIC SKEUOMORPHIC WATER METER — ASAHI STYLE
           =============================================================== */

        .vm-water-body {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            max-width: 280px;
            position: relative;
            filter: drop-shadow(0 6px 12px rgba(0,0,0,0.35));
        }

        /* ── Industrial Blue Pipes ── */
        .vm-pipe-left, .vm-pipe-right {
            width: 42px;
            height: 48px;
            flex-shrink: 0;
            position: relative;
            z-index: 2;
            /* Cylindrical 3D pipe — multi-stop gradient simulates round tube */
            background:
                linear-gradient(180deg,
                    rgba(255,255,255,0.08) 0%,
                    transparent 8%,
                    transparent 92%,
                    rgba(0,0,0,0.10) 100%),
                linear-gradient(180deg,
                    #52c8da 0%,
                    #3cb8cc 5%,
                    #2ea0b6 12%,
                    #2290a8 22%,
                    #1a7d96 35%,
                    #146d84 50%,
                    #0f5c72 65%,
                    #0b4e62 78%,
                    #084454 90%,
                    #063a4a 100%);
            border-top: 1px solid rgba(100,210,230,0.35);
            border-bottom: 1.5px solid #042830;
        }
        .vm-pipe-left {
            border-radius: 8px 0 0 8px;
            border-right: none;
            margin-right: -3px;
            border-left: 1.5px solid #073e4c;
            box-shadow:
                inset 0 4px 8px rgba(255,255,255,0.15),
                inset 0 -4px 8px rgba(0,0,0,0.25),
                inset -3px 0 6px rgba(0,0,0,0.10),
                -2px 0 4px rgba(0,0,0,0.12);
        }
        .vm-pipe-right {
            border-radius: 0 8px 8px 0;
            border-left: none;
            margin-left: -3px;
            border-right: 1.5px solid #073e4c;
            box-shadow:
                inset 0 4px 8px rgba(255,255,255,0.15),
                inset 0 -4px 8px rgba(0,0,0,0.25),
                inset 3px 0 6px rgba(0,0,0,0.10),
                2px 0 4px rgba(0,0,0,0.12);
        }

        /* ── Coupling nut / Flange ── */
        .vm-pipe-flange {
            position: absolute;
            width: 14px;
            height: 110%;
            top: -5%;
            z-index: 3;
            background:
                linear-gradient(180deg,
                    #6ad0e0 0%,
                    #48bcd0 8%,
                    #30a4ba 20%,
                    #228c9e 38%,
                    #187888 55%,
                    #106878 70%,
                    #0c5868 85%,
                    #084a5a 100%);
            border-top: 1px solid rgba(120,220,240,0.4);
            border-bottom: 1.5px solid #042830;
            border-radius: 2px;
            box-shadow:
                inset 0 3px 5px rgba(255,255,255,0.20),
                inset 0 -3px 5px rgba(0,0,0,0.20);
        }
        .vm-pipe-left .vm-pipe-flange {
            right: -2px;
            border-right: 2px solid #0a3540;
            border-left: 1px solid #0a3540;
            box-shadow:
                3px 0 6px rgba(0,0,0,0.25),
                inset 0 3px 5px rgba(255,255,255,0.20),
                inset 0 -3px 5px rgba(0,0,0,0.20);
        }
        .vm-pipe-right .vm-pipe-flange {
            left: -2px;
            border-left: 2px solid #0a3540;
            border-right: 1px solid #0a3540;
            box-shadow:
                -3px 0 6px rgba(0,0,0,0.25),
                inset 0 3px 5px rgba(255,255,255,0.20),
                inset 0 -3px 5px rgba(0,0,0,0.20);
        }

        /* ── Hex bolt on flange ── */
        .vm-pipe-bolt {
            position: absolute;
            width: 12px;
            height: 12px;
            background:
                radial-gradient(circle at 38% 30%,
                    #a0e4ef 0%,
                    #60c8d8 20%,
                    #38a8bc 40%,
                    #1e8ca0 60%,
                    #0f6a7c 80%,
                    #084a5a 100%);
            border-radius: 50%;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            box-shadow:
                inset 0 2px 3px rgba(255,255,255,0.50),
                inset 0 -2px 3px rgba(0,0,0,0.35),
                0 1.5px 4px rgba(0,0,0,0.45);
            border: 0.5px solid #063a4a;
        }
        .vm-pipe-bolt::after {
            content: '+';
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            font-size: 7px;
            font-weight: 900;
            color: rgba(0,0,0,0.22);
            line-height: 1;
            text-shadow: 0 0.5px 0 rgba(255,255,255,0.2);
        }

        /* ── Meter Housing — cast blue body ── */
        .vm-dial-water {
            width: 190px;
            height: 190px;
            border-radius: 50%;
            flex-shrink: 0;
            position: relative;
            z-index: 1;
            /* Deep multi-layer industrial blue body */
            background:
                radial-gradient(ellipse at 38% 25%,
                    rgba(80,200,220,0.20) 0%,
                    transparent 50%),
                radial-gradient(circle at 50% 50%,
                    #1e8fa2 0%,
                    #1a8496 12%,
                    #157688 25%,
                    #10687a 38%,
                    #0c5a6c 52%,
                    #094e60 65%,
                    #074456 78%,
                    #053a4c 90%,
                    #043242 100%);
            border: 4.5px solid #053545;
            box-shadow:
                /* outer rim highlight */
                0 0 0 1px rgba(60,180,210,0.25),
                /* top light catch */
                inset 0 4px 10px rgba(80,200,230,0.12),
                /* bottom shadow depth */
                inset 0 -6px 14px rgba(0,0,0,0.30),
                /* left/right ambient occlusion */
                inset 4px 0 8px rgba(0,0,0,0.10),
                inset -4px 0 8px rgba(0,0,0,0.10),
                /* drop shadow on surface */
                0 10px 30px rgba(0,0,0,0.35),
                0 3px 10px rgba(0,0,0,0.20);
        }
        /* Subtle top-light sheen on blue body */
        .vm-dial-water::before {
            content: '';
            position: absolute;
            top: 0; left: 10%; right: 10%;
            height: 40%;
            border-radius: 50%;
            background: radial-gradient(ellipse at 50% 0%,
                rgba(100,210,230,0.15) 0%,
                transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        /* ── Aged Brass / Gold Bezel Ring ── */
        .vm-dial-face {
            position: absolute;
            top: 9px; left: 9px; right: 9px; bottom: 9px;
            border-radius: 50%;
            /* Cream-white aged dial face */
            background:
                radial-gradient(ellipse at 45% 35%,
                    #ffffff 0%,
                    #fefcf6 15%,
                    #faf6ec 30%,
                    #f4efe2 48%,
                    #eee8d8 65%,
                    #e6dfce 80%,
                    #ddd6c4 100%);
            border: 6px solid transparent;
            background-clip: padding-box;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1px;
            overflow: hidden;
            /* Multi-ring brass bezel with depth */
            box-shadow:
                /* inner recessed shadow under glass */
                inset 0 3px 10px rgba(0,0,0,0.12),
                inset 0 -2px 6px rgba(0,0,0,0.06),
                inset 2px 0 4px rgba(0,0,0,0.04),
                inset -2px 0 4px rgba(0,0,0,0.04),
                /* brass ring layers — dark to bright to dark */
                0 0 0 1px #6b5504,
                0 0 0 2.5px #8c6d08,
                0 0 0 4px #b8920e,
                0 0 0 5.5px #d4aa18,
                0 0 0 6.5px #e4bc28,
                0 0 0 7.5px #d4aa18,
                0 0 0 8.5px #b08010,
                0 0 0 9px #8c6d08,
                0 0 0 9.5px #6b5504;
        }
        /* Brass ring metallic gradient overlay */
        .vm-dial-face::before {
            content: '';
            position: absolute;
            top: -10px; left: -10px; right: -10px; bottom: -10px;
            border-radius: 50%;
            border: 9px solid transparent;
            background:
                linear-gradient(145deg,
                    rgba(255,240,160,0.80) 0%,
                    rgba(230,200,80,0.50) 15%,
                    rgba(200,160,30,0.70) 30%,
                    rgba(160,120,10,0.80) 45%,
                    rgba(140,105,8,0.60) 55%,
                    rgba(180,140,20,0.70) 65%,
                    rgba(220,180,50,0.50) 78%,
                    rgba(255,230,120,0.75) 88%,
                    rgba(200,160,30,0.60) 100%);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
            z-index: 5;
        }

        /* ── Convex Glass Dome Reflection ── */
        .vm-dial-face::after {
            content: '';
            position: absolute;
            top: 2%; left: 6%;
            width: 65%; height: 40%;
            background:
                radial-gradient(ellipse at 40% 30%,
                    rgba(255,255,255,0.55) 0%,
                    rgba(255,255,255,0.30) 25%,
                    rgba(255,255,255,0.10) 50%,
                    transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: 10;
            transform: rotate(-8deg);
        }

        /* ── Unit label (m³) ── */
        .vm-dial-unit-top {
            font-size: 0.65rem;
            font-weight: 900;
            color: #1a1a1a;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
            position: relative;
            z-index: 2;
            text-shadow: 0 0.5px 0 rgba(255,255,255,0.6);
        }

        /* ── Rotating star deco ── */
        .vm-dial-deco {
            font-size: 1rem;
            color: #555;
            margin: 1px 0;
            line-height: 1;
            opacity: 0.45;
            animation: waterMeterSpin 2.5s linear infinite;
            position: relative;
            z-index: 2;
        }
        @keyframes waterMeterSpin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* ── Spec line ── */
        .vm-dial-specs {
            font-size: 0.36rem;
            color: #888;
            letter-spacing: 0.3px;
            margin: 0;
            white-space: nowrap;
            position: relative;
            z-index: 2;
        }

        /* ── WATER METER label ── */
        .vm-dial-label {
            font-size: 0.42rem;
            font-weight: 800;
            color: #555;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            position: relative;
            z-index: 2;
        }

        /* ── Small Red Sub-Dial Gauge ── */
        .vm-sub-dial {
            position: absolute;
            bottom: 13%;
            right: 16%;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            z-index: 3;
            /* Tick marks ring using conic-gradient */
            background:
                conic-gradient(
                    from 0deg,
                    #ccc 0deg, #ccc 1deg, transparent 1deg, transparent 36deg,
                    #ccc 36deg, #ccc 37deg, transparent 37deg, transparent 72deg,
                    #ccc 72deg, #ccc 73deg, transparent 73deg, transparent 108deg,
                    #ccc 108deg, #ccc 109deg, transparent 109deg, transparent 144deg,
                    #ccc 144deg, #ccc 145deg, transparent 145deg, transparent 180deg,
                    #ccc 180deg, #ccc 181deg, transparent 181deg, transparent 216deg,
                    #ccc 216deg, #ccc 217deg, transparent 217deg, transparent 252deg,
                    #ccc 252deg, #ccc 253deg, transparent 253deg, transparent 288deg,
                    #ccc 288deg, #ccc 289deg, transparent 289deg, transparent 324deg,
                    #ccc 324deg, #ccc 325deg, transparent 325deg, transparent 360deg
                ),
                radial-gradient(circle at 45% 38%,
                    #fff 0%, #fcf8f8 40%, #f5eaea 65%, #eedede 100%);
            border: 2px solid #c62828;
            box-shadow:
                inset 0 1.5px 4px rgba(0,0,0,0.15),
                inset 0 -1px 2px rgba(0,0,0,0.05),
                0 1.5px 4px rgba(0,0,0,0.18);
        }
        /* Needle */
        .vm-sub-dial::before {
            content: '';
            position: absolute;
            top: 50%; left: 50%;
            width: 8px; height: 1.5px;
            background: linear-gradient(90deg, #c62828 0%, #e53935 100%);
            transform-origin: 0 50%;
            transform: translate(0, -50%) rotate(-30deg);
            border-radius: 1px;
            animation: subDialSpin 8s linear infinite;
            box-shadow: 0 0.5px 1px rgba(0,0,0,0.3);
        }
        /* Center cap */
        .vm-sub-dial::after {
            content: '';
            position: absolute;
            top: 50%; left: 50%;
            width: 4px; height: 4px;
            background: radial-gradient(circle at 40% 35%, #f44336, #b71c1c);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            box-shadow:
                inset 0 0.5px 1px rgba(255,255,255,0.4),
                0 0.5px 1px rgba(0,0,0,0.3);
        }
        @keyframes subDialSpin {
            from { transform: translate(0, -50%) rotate(-30deg); }
            to { transform: translate(0, -50%) rotate(330deg); }
        }

        /* ===== Realistic Electric Meter ===== */
        .vm-elec-body {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 0 auto;
            max-width: 180px;
        }
        .vm-elec-frame {
            width: 155px;
            min-height: 175px;
            background: linear-gradient(160deg, #f8f8f8 0%, #ededed 25%, #e0e0e0 50%, #d5d5d5 80%, #ccc 100%);
            border: 2.5px solid #a0a0a0;
            border-radius: 14px 14px 8px 8px;
            position: relative;
            padding: 1.1rem 0.6rem 0.8rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            box-shadow:
                inset 0 1px 4px rgba(255,255,255,0.7),
                inset 0 -2px 5px rgba(0,0,0,0.06),
                0 5px 18px rgba(0,0,0,0.2),
                0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .vm-elec-frame::before {
            content: '';
            position: absolute;
            top: 7px; left: 7px; right: 7px; bottom: 7px;
            border: 1.5px solid rgba(255,255,255,0.5);
            border-radius: 12px 12px 6px 6px;
            pointer-events: none;
        }
        .vm-elec-frame::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.15) 0%, transparent 50%, rgba(0,0,0,0.03) 100%);
            border-radius: inherit;
            pointer-events: none;
        }
        .vm-elec-screw {
            position: absolute;
            width: 11px;
            height: 11px;
            background: radial-gradient(circle at 35% 35%, #e8e8e8, #aaa 50%, #888);
            border-radius: 50%;
            border: 1px solid #888;
            box-shadow: inset 0 1px 1px rgba(255,255,255,0.5), 0 1px 2px rgba(0,0,0,0.3);
            z-index: 2;
        }
        .vm-elec-screw::after {
            content: '';
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%) rotate(30deg);
            width: 6px; height: 1.2px;
            background: #666;
        }
        .vm-screw-tl { top: 5px; left: 5px; }
        .vm-screw-tr { top: 5px; right: 5px; }
        .vm-screw-bl { bottom: 5px; left: 5px; }
        .vm-screw-br { bottom: 5px; right: 5px; }
        .vm-elec-title {
            font-size: 0.48rem;
            font-weight: 700;
            color: #555;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin-top: 2px;
        }
        .vm-elec-counter {
            display: flex;
            align-items: center;
            gap: 5px;
            background: linear-gradient(180deg, #1a1a1a 0%, #2d2d2d 100%);
            padding: 5px 8px;
            border-radius: 4px;
            border: 1.5px solid #444;
            box-shadow: inset 0 2px 5px rgba(0,0,0,0.6), 0 1px 3px rgba(0,0,0,0.2);
        }
        .vm-elec-kwh {
            font-size: 0.55rem;
            font-weight: 700;
            color: #ccc;
            letter-spacing: 0.5px;
        }
        .vm-elec-disc-area {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: radial-gradient(circle, #1a1a1a 0%, #111 100%);
            border: 2px solid #555;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: inset 0 1px 4px rgba(0,0,0,0.6);
        }
        .vm-elec-disc {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: conic-gradient(#d0d0d0 0deg, #888 90deg, #d0d0d0 180deg, #888 270deg, #d0d0d0 360deg);
            border: 1px solid #666;
            animation: elecSpin 3s linear infinite;
        }
        @keyframes elecSpin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .vm-elec-specs {
            font-size: 0.42rem;
            color: #999;
            letter-spacing: 0.5px;
        }
        .vm-elec-base {
            width: 115px;
            height: 20px;
            background: linear-gradient(180deg, #c8c8c8 0%, #adadad 30%, #999 70%, #888 100%);
            border-radius: 0 0 10px 10px;
            border: 1.5px solid #888;
            border-top: 1px solid #bbb;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }

        /* ===============================================================
           MECHANICAL ODOMETER — Rotating Number Drums
           =============================================================== */
        .vm-digits {
            display: flex;
            gap: 0px;
            /* Black plastic odometer window housing */
            background:
                linear-gradient(180deg,
                    #050505 0%,
                    #111 8%,
                    #1a1a1a 15%,
                    #222 50%,
                    #1a1a1a 85%,
                    #111 92%,
                    #050505 100%);
            padding: 3px 4px;
            border-radius: 4px;
            border: 1.5px solid #333;
            box-shadow:
                inset 0 3px 8px rgba(0,0,0,0.85),
                inset 0 -2px 6px rgba(0,0,0,0.60),
                inset 2px 0 4px rgba(0,0,0,0.40),
                inset -2px 0 4px rgba(0,0,0,0.40),
                0 1px 3px rgba(0,0,0,0.2);
            position: relative;
            z-index: 2;
        }
        /* Plastic window shine on top edge */
        .vm-digits::before {
            content: '';
            position: absolute;
            top: 0; left: 4px; right: 4px;
            height: 3px;
            background: linear-gradient(180deg,
                rgba(255,255,255,0.08) 0%,
                transparent 100%);
            border-radius: 4px 4px 0 0;
            pointer-events: none;
            z-index: 1;
        }

        .vm-digit {
            width: 18px;
            height: 24px;
            text-align: center;
            font-family: 'Courier New', 'Lucida Console', monospace;
            font-size: 0.85rem;
            font-weight: 900;
            border: none;
            padding: 0;
            -moz-appearance: textfield;
            appearance: textfield;
            transition: background 0.15s, box-shadow 0.15s;
            position: relative;
            /* White drum — curved roller effect */
            background:
                linear-gradient(180deg,
                    #999 0%,
                    #bbb 3%,
                    #d8d8d8 7%,
                    #eee 14%,
                    #f6f6f6 22%,
                    #fafafa 35%,
                    #fff 50%,
                    #fafafa 65%,
                    #f6f6f6 78%,
                    #eee 86%,
                    #d8d8d8 93%,
                    #bbb 97%,
                    #999 100%);
            color: #0a0a0a;
            text-shadow: 0 0.5px 0 rgba(255,255,255,0.5);
            border-radius: 2px;
            border-left: 0.5px solid rgba(0,0,0,0.10);
            border-right: 0.5px solid rgba(0,0,0,0.10);
            /* Deep inset to look recessed behind window */
            box-shadow:
                inset 0 3px 5px rgba(0,0,0,0.20),
                inset 0 -3px 5px rgba(0,0,0,0.15),
                inset 1px 0 2px rgba(0,0,0,0.10),
                inset -1px 0 2px rgba(0,0,0,0.10),
                0 0 0 0.5px rgba(0,0,0,0.12);
        }
        .vm-digit::-webkit-outer-spin-button,
        .vm-digit::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .vm-digit:focus {
            outline: 2.5px solid #3b82f6;
            outline-offset: -1px;
            z-index: 3;
            background:
                linear-gradient(180deg,
                    #aaa 0%, #ccc 5%, #e8e8e8 12%, #f5f5f5 25%,
                    #fff 50%, #f5f5f5 75%, #e8e8e8 88%, #ccc 95%, #aaa 100%);
        }
        /* ── Red digits — last 2 slots ── */
        .vm-digit.vm-digit-red {
            background:
                linear-gradient(180deg,
                    #7f1d1d 0%,
                    #991b1b 4%,
                    #b91c1c 8%,
                    #d42a2a 15%,
                    #e53935 25%,
                    #ef4444 38%,
                    #f44840 50%,
                    #ef4444 62%,
                    #e53935 75%,
                    #d42a2a 85%,
                    #b91c1c 92%,
                    #991b1b 96%,
                    #7f1d1d 100%);
            color: #fff;
            text-shadow: 0 1px 2px rgba(0,0,0,0.45);
            box-shadow:
                inset 0 3px 5px rgba(0,0,0,0.30),
                inset 0 -3px 5px rgba(0,0,0,0.20),
                inset 1px 0 2px rgba(0,0,0,0.15),
                inset -1px 0 2px rgba(0,0,0,0.15),
                0 0 0 0.5px rgba(100,0,0,0.20);
        }
        .vm-digit:disabled {
            background:
                linear-gradient(180deg,
                    #8a8a8a 0%, #a0a0a0 5%, #bbb 12%, #d0d0d0 25%,
                    #ddd 50%, #d0d0d0 75%, #bbb 88%, #a0a0a0 95%, #8a8a8a 100%);
            color: #666;
            cursor: not-allowed;
        }
        .vm-digit.vm-digit-red:disabled {
            background:
                linear-gradient(180deg,
                    #5c1515 0%, #701a1a 5%, #881e1e 12%, #9e2222 25%,
                    #a82828 50%, #9e2222 75%, #881e1e 88%, #701a1a 95%, #5c1515 100%);
            color: #e8a0a0;
        }

        .vm-old-reading {
            text-align: center;
            font-size: 0.7rem;
            color: #94a3b8;
            margin-bottom: 4px;
            font-family: 'Courier New', monospace;
        }
        .vm-old-reading span { letter-spacing: 2px; }
        .vm-meter-info {
            text-align: center;
            margin-top: 0.5rem;
            font-size: 0.78rem;
        }
        .vm-usage { font-weight: 700; }
        .vm-usage.water { color: #0284c7; }
        .vm-usage.electric { color: #ea580c; }
        .vm-cost { color: #6b7280; font-size: 0.72rem; }

        @media (max-width: 480px) {
            .vm-grid { grid-template-columns: 1fr; padding: 0.5rem; gap: 0.75rem; }
            .vm-meters { flex-direction: column; }
            .vm-water-body { max-width: 230px; }
            .vm-dial-water { width: 155px; height: 155px; }
            .vm-dial-face { top: 7px; left: 7px; right: 7px; bottom: 7px; }
            .vm-elec-frame { width: 130px; min-height: 150px; }
            .vm-digit { width: 15px; height: 20px; font-size: 0.72rem; }
            .vm-pipe-left, .vm-pipe-right { width: 32px; height: 38px; }
            .vm-sub-dial { width: 18px; height: 18px; bottom: 12%; right: 14%; }
            .vm-pipe-flange { width: 11px; }
            .vm-pipe-bolt { width: 10px; height: 10px; }
        }
    </style>
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/futuristic-bright.css">
</head>
<body class="reports-page" data-meter-tab="<?php echo htmlspecialchars($activeTab, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="app-shell">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="app-main">
            <div class="meter-page">
                <?php $pageTitle = 'จดมิเตอร์'; include __DIR__ . '/../includes/page_header.php'; ?>

                <?php if (isset($_SESSION['success'])): ?>
                <div class="toast-msg success" id="toast"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <script>setTimeout(function(){var t=document.getElementById('toast');if(t)t.remove();},5000);</script>
                <?php endif; ?>
                <?php if ($error): ?>
                <div class="toast-msg error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if (isset($_SESSION['first_bills'])) unset($_SESSION['first_bills']); ?>

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

                    <?php if ($selectedCtrFilterActive): ?>
                    <?php
                        // หาชื่อผู้เช่า/ห้องจาก $rooms (ถ้ามีข้อมูล)
                        $filterRoomLabel = '';
                        foreach ($rooms as $_fr) {
                            if (!empty($_fr['room_number'])) {
                                $filterRoomLabel = 'ห้อง ' . htmlspecialchars($_fr['room_number'], ENT_QUOTES, 'UTF-8');
                                if (!empty($_fr['tnt_name'])) $filterRoomLabel .= ' – ' . htmlspecialchars($_fr['tnt_name'], ENT_QUOTES, 'UTF-8');
                                break;
                            }
                        }
                        $clearUrl = '?month=' . $month . '&year=' . $year . '&tab=' . htmlspecialchars($activeTab, ENT_QUOTES, 'UTF-8') . '&show=' . htmlspecialchars($showMode, ENT_QUOTES, 'UTF-8');
                    ?>
                    <div style="margin:0.5rem 0; display:flex; align-items:center; gap:0.6rem; flex-wrap:wrap; padding:0.5rem 0.75rem; background:rgba(245,158,11,0.10); border:1px solid rgba(245,158,11,0.35); border-radius:8px; font-size:0.85rem;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" style="width:15px;height:15px;flex-shrink:0;"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                        <span style="color:#fbbf24; font-weight:600;">กรองเฉพาะ<?php echo $filterRoomLabel ?: 'สัญญา #' . (int)$selectedCtrId; ?></span>
                        <a href="<?php echo $clearUrl; ?>" style="margin-left:auto; display:inline-flex; align-items:center; gap:0.3rem; background:rgba(239,68,68,0.15); border:1px solid #ef4444; color:#fca5a5; border-radius:20px; padding:0.2rem 0.65rem; text-decoration:none; font-size:0.8rem; font-weight:600; white-space:nowrap;">
                            ✕ ล้าง Filter
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    
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

                    <!-- View Toggle -->
                    <div class="view-toggle-bar">
                        <button type="button" id="viewToggleBtn" class="view-toggle-btn" onclick="toggleMeterView()">
                            <div class="vtb-shimmer"></div>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                            <span class="vtb-label">มุมมองมิเตอร์ (BETA)</span>
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

                        <div id="tableView">
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
                                <?php $needsWater = $hasCtr && !$r['water_saved'] && ($r['water_new'] === '' || $r['water_new'] === null); ?>
                                <tr class="<?php echo $r['water_saved']?'saved-row':''; ?> <?php echo !$hasCtr?'empty-row':''; ?> <?php echo $needsWater ? 'needs-meter needs-water' : ''; ?>">
                                    <td class="room-num-cell">
                                        <?php echo htmlspecialchars($room['room_number']); ?>
                                        <?php if ($needsWater): ?>
                                            <span class="needs-meter-badge">⚠ ยังไม่จด</span>
                                        <?php endif; ?>
                                        <?php if ($r['isFirstReading']): ?>
                                            <span style="color:#f59e0b;font-weight:700;margin-left:0.3rem;">(ครั้งแรก)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="status-icon"><?php if($hasCtr): ?><svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg><?php endif; ?></td>
                                    <td><?php echo $hasCtr ? str_pad((string)$r['water_old'], 7, '0', STR_PAD_LEFT) : '-'; ?></td>
                                    <td><?php if($hasCtr): ?>
                                        <?php 
                                            $tooltipMsg = '';
                                            $waterLocked = $r['water_saved'] && !$r['can_edit_saved'];
                                            if ($r['water_saved'] && !$r['can_edit_saved']) {
                                                $tooltipMsg = $r['bill_paid']
                                                    ? 'ชำระบิลแล้ว ไม่สามารถแก้ไขได้'
                                                    : 'บันทึกเดือนนี้แล้ว ไม่สามารถแก้ไขได้';
                                            } elseif ($r['meter_blocked']) {
                                                if ($isPastMonth) {
                                                    $tooltipMsg = 'ไม่สามารถแก้ไขเดือนที่ผ่านมาแล้วได้';
                                                } elseif ($r['workflow_step'] < 4) {
                                                    $tooltipMsg = "ต้องผ่านขั้นตอน 4 เช็คอิน ก่อน (ขั้นตอนปัจจุบัน: {$r['workflow_step']}/5)";
                                                } else {
                                                    $tooltipMsg = "ยังไม่ได้เช็คอิน";
                                                }
                                            }
                                            $waterDisabled = $waterLocked || ($r['meter_blocked'] && !$r['can_edit_saved']);
                                        ?>
                                        <input type="number" name="meter[<?php echo $room['room_id']; ?>][water]" class="meter-input-field meter-input <?php echo $waterLocked ? 'locked' : ''; ?> <?php echo $r['meter_blocked'] ? 'blocked-by-step' : ''; ?> <?php echo ($r['water_saved'] && $r['can_edit_saved']) ? 'editable-saved' : ''; ?>" data-type="water" data-room="<?php echo $room['room_id']; ?>" data-old="<?php echo $r['water_old']; ?>" data-first-reading="<?php echo $r['isFirstReading'] ? '1' : '0'; ?>" placeholder="<?php echo str_pad((string)$r['water_old'], 7, '0', STR_PAD_LEFT); ?>" value="<?php echo ($r['water_new'] !== '' && $r['water_new'] !== null) ? str_pad((string)(int)$r['water_new'], 7, '0', STR_PAD_LEFT) : ''; ?>" min="<?php echo $r['water_old']; ?>" max="9999999" oninput="if(this.value.length > 7) this.value = this.value.slice(0, 7); (function(el){var v=parseInt(el.value,10),old=parseInt(el.dataset.old,10),isFirst=el.dataset.firstReading==='1',errId='meter-err-'+el.dataset.room+'-water'; if(!isFirst&&!isNaN(v)&&v<old){el.style.borderColor='#ef4444';el.style.background='rgba(239,68,68,0.08)';var e=document.getElementById(errId);if(!e){e=document.createElement('div');e.id=errId;e.style.cssText='color:#ef4444;font-size:0.72rem;margin-top:2px;';e.textContent='ค่าใหม่ต้องไม่น้อยกว่าค่าเดิม ('+String(old).padStart(7,'0')+')';el.parentNode.appendChild(e);}}else{el.style.borderColor='';el.style.background='';var e2=document.getElementById(errId);if(e2)e2.remove();}})(this)" <?php echo $waterDisabled ? 'disabled data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-title="' . htmlspecialchars($tooltipMsg) . '"' : ''; ?>>
                                        <input type="hidden" name="meter[<?php echo $room['room_id']; ?>][water_old]" value="<?php echo $r['water_old']; ?>">
                                        <input type="hidden" name="meter[<?php echo $room['room_id']; ?>][ctr_id]" value="<?php echo $room['ctr_id']; ?>">
                                        <input type="hidden" name="meter[<?php echo $room['room_id']; ?>][workflow_step]" value="<?php echo $r['workflow_step']; ?>">
                                        <input type="hidden" name="meter[<?php echo $room['room_id']; ?>][is_first_reading]" value="<?php echo $r['isFirstReading'] ? '1' : '0'; ?>">
                                    <?php else: ?>-<?php endif; ?></td>
                                    <td class="usage-cell" data-room="<?php echo $room['room_id']; ?>" data-usage="water"><?php echo $hasCtr ? ($r['isFirstReading'] ? 0 : $wUsed) : '-'; ?></td>
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
                                <?php $needsElec = $hasCtr && !$r['elec_saved'] && ($r['elec_new'] === '' || $r['elec_new'] === null); ?>
                                <tr class="<?php echo $r['elec_saved']?'saved-row':''; ?> <?php echo !$hasCtr?'empty-row':''; ?> <?php echo $needsElec ? 'needs-meter needs-electric' : ''; ?>">
                                    <td class="room-num-cell">
                                        <?php echo htmlspecialchars($room['room_number']); ?>
                                        <?php if ($needsElec): ?>
                                            <span class="needs-meter-badge">⚠ ยังไม่จด</span>
                                        <?php endif; ?>
                                        <?php if ($r['isFirstReading']): ?>
                                            <span style="color:#f59e0b;font-weight:700;margin-left:0.3rem;">(ครั้งแรก)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="status-icon"><?php if($hasCtr): ?><svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg><?php endif; ?></td>
                                    <td><?php echo $hasCtr ? str_pad((string)$r['elec_old'], 5, '0', STR_PAD_LEFT) : '-'; ?></td>
                                    <td><?php if($hasCtr): ?>
                                        <?php 
                                            $tooltipMsg = '';
                                            $elecLocked = $r['elec_saved'] && !$r['can_edit_saved'];
                                            if ($r['elec_saved'] && !$r['can_edit_saved']) {
                                                $tooltipMsg = $r['bill_paid']
                                                    ? 'ชำระบิลแล้ว ไม่สามารถแก้ไขได้'
                                                    : 'บันทึกเดือนนี้แล้ว ไม่สามารถแก้ไขได้';
                                            } elseif ($r['meter_blocked']) {
                                                if ($isPastMonth) {
                                                    $tooltipMsg = 'ไม่สามารถแก้ไขเดือนที่ผ่านมาแล้วได้';
                                                } elseif ($r['workflow_step'] < 4) {
                                                    $tooltipMsg = "ต้องผ่านขั้นตอน 4 เช็คอิน ก่อน (ขั้นตอนปัจจุบัน: {$r['workflow_step']}/5)";
                                                } else {
                                                    $tooltipMsg = "ยังไม่ได้เช็คอิน";
                                                }
                                            }
                                            $elecDisabled = $elecLocked || ($r['meter_blocked'] && !$r['can_edit_saved']);
                                        ?>
                                        <input type="number" name="meter[<?php echo $room['room_id']; ?>][electric]" class="meter-input-field elec-input meter-input <?php echo $elecLocked ? 'locked' : ''; ?> <?php echo $r['meter_blocked'] ? 'blocked-by-step' : ''; ?> <?php echo ($r['elec_saved'] && $r['can_edit_saved']) ? 'editable-saved' : ''; ?>" data-type="electric" data-room="<?php echo $room['room_id']; ?>" data-old="<?php echo $r['elec_old']; ?>" data-first-reading="<?php echo $r['isFirstReading'] ? '1' : '0'; ?>" placeholder="<?php echo str_pad((string)$r['elec_old'], 5, '0', STR_PAD_LEFT); ?>" value="<?php echo ($r['elec_new'] !== '' && $r['elec_new'] !== null) ? str_pad((string)(int)$r['elec_new'], 5, '0', STR_PAD_LEFT) : ''; ?>" min="<?php echo $r['elec_old']; ?>" max="99999" oninput="if(this.value.length > 5) this.value = this.value.slice(0, 5); (function(el){var v=parseInt(el.value,10),old=parseInt(el.dataset.old,10),isFirst=el.dataset.firstReading==='1',errId='meter-err-'+el.dataset.room+'-electric'; if(!isFirst&&!isNaN(v)&&v<old){el.style.borderColor='#ef4444';el.style.background='rgba(239,68,68,0.08)';var e=document.getElementById(errId);if(!e){e=document.createElement('div');e.id=errId;e.style.cssText='color:#ef4444;font-size:0.72rem;margin-top:2px;';e.textContent='ค่าใหม่ต้องไม่น้อยกว่าค่าเดิม ('+String(old).padStart(5,'0')+')';el.parentNode.appendChild(e);}}else{el.style.borderColor='';el.style.background='';var e2=document.getElementById(errId);if(e2)e2.remove();}})(this)" <?php echo $elecDisabled ? 'disabled data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-title="' . htmlspecialchars($tooltipMsg) . '"' : ''; ?>>
                                        <input type="hidden" name="meter[<?php echo $room['room_id']; ?>][elec_old]" value="<?php echo $r['elec_old']; ?>">
                                        <input type="hidden" name="meter[<?php echo $room['room_id']; ?>][ctr_id]" value="<?php echo $room['ctr_id']; ?>">
                                        <input type="hidden" name="meter[<?php echo $room['room_id']; ?>][workflow_step]" value="<?php echo $r['workflow_step']; ?>">
                                    <?php else: ?>-<?php endif; ?></td>
                                    <td class="usage-cell elec-usage" data-room="<?php echo $room['room_id']; ?>" data-usage="electric"><?php echo $hasCtr ? ($r['isFirstReading'] ? 0 : $eUsed) : '-'; ?></td>
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
                        </div><!-- /tableView -->

                        <!-- VISUAL METER VIEW -->
                        <div id="meterView" style="display:none">
                            <!-- WATER METER PANEL -->
                            <div id="waterMeterPanel" style="<?php echo $activeTab!=='water'?'display:none':''; ?>">
                            <?php foreach ($floors as $floorNum => $floorRooms): ?>
                            <div class="floor-header">ชั้นที่ <?php echo $floorNum; ?></div>
                            <div class="vm-grid">
                                <?php foreach ($floorRooms as $room):
                                    $r = $readings[$room['room_id']];
                                    $hasCtr = !empty($room['ctr_id']);
                                    $wOld = (int)$r['water_old'];
                                    $wNew = ($r['water_new'] !== '' && $r['water_new'] !== null) ? (int)$r['water_new'] : null;
                                    $wUsedVm = $wNew !== null ? max(0, $wNew - $wOld) : 0;
                                    $cardClass = 'vm-card';
                                    if (!$hasCtr) $cardClass .= ' vm-empty';
                                    elseif ($r['water_saved']) $cardClass .= ' vm-saved';
                                    elseif ($r['meter_blocked']) $cardClass .= ' vm-blocked';
                                    else $cardClass .= ' vm-pending';
                                    $isVmDisabled = !$hasCtr || ($r['water_saved'] && !$r['can_edit_saved']) || $r['meter_blocked'];
                                ?>
                                <div class="<?php echo $cardClass; ?>">
                                    <div class="vm-card-header">
                                        <span class="vm-room-num">ห้อง <?php echo htmlspecialchars($room['room_number']); ?></span>
                                        <?php if ($r['isFirstReading']): ?>
                                            <span class="vm-first-badge">ครั้งแรก</span>
                                        <?php elseif ($r['water_saved'] && !$r['can_edit_saved']): ?>
                                            <span class="vm-saved-badge">✓ บันทึกแล้ว</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="vm-meters">
                                        <div class="vm-meter-section">
                                            <div class="vm-old-reading">เดิม: <span><?php echo str_pad((string)$wOld, 7, '0', STR_PAD_LEFT); ?></span></div>
                                            <div class="vm-water-body">
                                                <div class="vm-pipe-left"><div class="vm-pipe-flange"></div><div class="vm-pipe-bolt"></div></div>
                                                <div class="vm-dial-water">
                                                    <div class="vm-dial-face">
                                                        <div class="vm-dial-unit-top">m³</div>
                                                        <div class="vm-digits">
                                                            <?php
                                                            $wStr = $wNew !== null ? str_pad((string)$wNew, 7, '0', STR_PAD_LEFT) : '';
                                                            for ($d = 0; $d < 7; $d++):
                                                                $dClass = 'vm-digit vm-digit-input';
                                                                if ($d >= 5) $dClass .= ' vm-digit-red';
                                                                $dVal = ($wNew !== null && isset($wStr[$d])) ? $wStr[$d] : '';
                                                            ?>
                                                            <input type="text" inputmode="numeric" maxlength="1" class="<?php echo $dClass; ?>" data-meter-type="water" data-room-id="<?php echo $room['room_id']; ?>" data-digit-index="<?php echo $d; ?>" data-total-digits="7" value="<?php echo $dVal; ?>" <?php echo $isVmDisabled ? 'disabled' : ''; ?>>
                                                            <?php endfor; ?>
                                                        </div>
                                                        <div class="vm-dial-specs">20 mm · Qn 2.5 m³/hB</div>
                                                        <div class="vm-dial-deco">✻</div>
                                                        <div class="vm-dial-label">WATER METER</div>
                                                        <div class="vm-sub-dial"></div>
                                                    </div>
                                                </div>
                                                <div class="vm-pipe-right"><div class="vm-pipe-flange"></div><div class="vm-pipe-bolt"></div></div>
                                            </div>
                                            <div class="vm-meter-info">
                                                <div class="vm-usage water" data-vm-usage="water" data-vm-room="<?php echo $room['room_id']; ?>"><?php echo $wUsedVm; ?> หน่วย</div>
                                                <div class="vm-cost" data-vm-cost="water" data-vm-room="<?php echo $room['room_id']; ?>"><?php echo ($r['isFirstReading'] ?? false) ? 0 : calculateWaterCost($wUsedVm); ?> ฿</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                            </div>

                            <!-- ELECTRIC METER PANEL -->
                            <div id="electricMeterPanel" style="<?php echo $activeTab!=='electric'?'display:none':''; ?>">
                            <?php foreach ($floors as $floorNum => $floorRooms): ?>
                            <div class="floor-header">ชั้นที่ <?php echo $floorNum; ?></div>
                            <div class="vm-grid">
                                <?php foreach ($floorRooms as $room):
                                    $r = $readings[$room['room_id']];
                                    $hasCtr = !empty($room['ctr_id']);
                                    $eOld = (int)$r['elec_old'];
                                    $eNew = ($r['elec_new'] !== '' && $r['elec_new'] !== null) ? (int)$r['elec_new'] : null;
                                    $eUsedVm = $eNew !== null ? max(0, $eNew - $eOld) : 0;
                                    $cardClass = 'vm-card';
                                    if (!$hasCtr) $cardClass .= ' vm-empty';
                                    elseif ($r['elec_saved']) $cardClass .= ' vm-saved';
                                    elseif ($r['meter_blocked']) $cardClass .= ' vm-blocked';
                                    else $cardClass .= ' vm-pending';
                                    $isVmDisabled = !$hasCtr || ($r['elec_saved'] && !$r['can_edit_saved']) || $r['meter_blocked'];
                                ?>
                                <div class="<?php echo $cardClass; ?>">
                                    <div class="vm-card-header">
                                        <span class="vm-room-num">ห้อง <?php echo htmlspecialchars($room['room_number']); ?></span>
                                        <?php if ($r['isFirstReading']): ?>
                                            <span class="vm-first-badge">ครั้งแรก</span>
                                        <?php elseif ($r['elec_saved'] && !$r['can_edit_saved']): ?>
                                            <span class="vm-saved-badge">✓ บันทึกแล้ว</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="vm-meters">
                                        <div class="vm-meter-section">
                                            <div class="vm-old-reading">เดิม: <span><?php echo str_pad((string)$eOld, 5, '0', STR_PAD_LEFT); ?></span></div>
                                            <div class="vm-elec-body">
                                                <div class="vm-elec-frame">
                                                    <div class="vm-elec-screw vm-screw-tl"></div>
                                                    <div class="vm-elec-screw vm-screw-tr"></div>
                                                    <div class="vm-elec-title">KILOWATT-HOUR METER</div>
                                                    <div class="vm-elec-counter">
                                                        <div class="vm-digits">
                                                            <?php
                                                            $eStr = $eNew !== null ? str_pad((string)$eNew, 5, '0', STR_PAD_LEFT) : '';
                                                            for ($d = 0; $d < 5; $d++):
                                                                $dClass = 'vm-digit vm-digit-input';
                                                                if ($d >= 4) $dClass .= ' vm-digit-red';
                                                                $dVal = ($eNew !== null && isset($eStr[$d])) ? $eStr[$d] : '';
                                                            ?>
                                                            <input type="text" inputmode="numeric" maxlength="1" class="<?php echo $dClass; ?>" data-meter-type="electric" data-room-id="<?php echo $room['room_id']; ?>" data-digit-index="<?php echo $d; ?>" data-total-digits="5" value="<?php echo $dVal; ?>" <?php echo $isVmDisabled ? 'disabled' : ''; ?>>
                                                            <?php endfor; ?>
                                                        </div>
                                                        <span class="vm-elec-kwh">kWh</span>
                                                    </div>
                                                    <div class="vm-elec-disc-area"><div class="vm-elec-disc"></div></div>
                                                    <div class="vm-elec-specs">220V 50Hz</div>
                                                    <div class="vm-elec-screw vm-screw-bl"></div>
                                                    <div class="vm-elec-screw vm-screw-br"></div>
                                                </div>
                                                <div class="vm-elec-base"></div>
                                            </div>
                                            <div class="vm-meter-info">
                                                <div class="vm-usage electric" data-vm-usage="electric" data-vm-room="<?php echo $room['room_id']; ?>"><?php echo $eUsedVm; ?> หน่วย</div>
                                                <div class="vm-cost" data-vm-cost="electric" data-vm-room="<?php echo $room['room_id']; ?>"><?php echo ($r['isFirstReading'] ?? false) ? 0 : ($eUsedVm * $electricRate); ?> ฿</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                            </div>
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

    // mgt = Meter Management utility object
    // clearMarks() ลบ badge "ยังไม่จด" ออกจากแถวที่กรอกเลขมิเตอร์แล้ว
    window.mgt = window.mgt || {};
    window.mgt.clearMarks = function(type) {
        var selector = type ? '.meter-input[data-type="' + type + '"]' : '.meter-input';
        document.querySelectorAll(selector).forEach(function(input) {
            if (!input.value || input.disabled) return;
            var row = input.closest('tr.needs-meter');
            if (!row) return;
            if (type === 'water' || input.dataset.type === 'water') {
                row.classList.remove('needs-water');
                if (!row.classList.contains('needs-electric')) {
                    row.classList.remove('needs-meter');
                }
            } else if (type === 'electric' || input.dataset.type === 'electric') {
                row.classList.remove('needs-electric');
                if (!row.classList.contains('needs-water')) {
                    row.classList.remove('needs-meter');
                }
            } else {
                row.classList.remove('needs-meter', 'needs-water', 'needs-electric');
            }
            var badge = row.querySelector('.needs-meter-badge');
            if (badge && !row.classList.contains('needs-meter')) badge.remove();
        });
    };
    window.mgt.markAll = function() {
        document.querySelectorAll('tr.needs-meter').forEach(function(row) {
            var badge = row.querySelector('.needs-meter-badge');
            if (!badge) {
                var roomCell = row.querySelector('.room-num-cell');
                if (roomCell) {
                    var b = document.createElement('span');
                    b.className = 'needs-meter-badge';
                    b.textContent = '⚠ ยังไม่จด';
                    roomCell.appendChild(b);
                }
            }
        });
    };

    function switchTab(tab, shouldSyncUrl) {
        if (shouldSyncUrl === undefined) shouldSyncUrl = true;
        var safeTab = tab === 'electric' ? 'electric' : 'water';

        var waterPanel = document.getElementById('waterPanel');
        var electricPanel = document.getElementById('electricPanel');
        if (waterPanel) waterPanel.style.display = safeTab === 'water' ? '' : 'none';
        if (electricPanel) electricPanel.style.display = safeTab === 'electric' ? '' : 'none';

        var waterMeterPanel = document.getElementById('waterMeterPanel');
        var electricMeterPanel = document.getElementById('electricMeterPanel');
        if (waterMeterPanel) waterMeterPanel.style.display = safeTab === 'water' ? '' : 'none';
        if (electricMeterPanel) electricMeterPanel.style.display = safeTab === 'electric' ? '' : 'none';

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
                rd[rid].wu=isFirstReading ? 0 : u; 
                rd[rid].water=isFirstReading ? 0 : calculateWaterCost(u);  // ครั้งแรก = 0 บาท
                rd[rid].hw=true; 
            }
            if (t==='electric' && i.value) { 
                var u=Math.max(0,newV-oldV); 
                rd[rid].eu=isFirstReading ? 0 : u; 
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

    document.querySelectorAll('.meter-input').forEach(function(i) {
        i.addEventListener('input', function() {
            updateTotals();
            if (window.mgt && typeof window.mgt.clearMarks === 'function') {
                window.mgt.clearMarks(this.dataset.type);
            }
        });
    });

    // ===== AJAX Save =====
    function showToast(msg, type) {
        var existing = document.getElementById('toast');
        if (existing) existing.remove();
        var toast = document.createElement('div');
        toast.className = 'toast-msg ' + (type || 'success');
        toast.id = 'toast';
        toast.textContent = msg;
        document.body.appendChild(toast);
        setTimeout(function(){ if (toast.parentNode) toast.remove(); }, 5000);
    }

    function updateStats() {
        var waterInputs = document.querySelectorAll('#waterPanel .meter-input[data-type="water"]');
        var roomData = {};
        waterInputs.forEach(function(input) {
            roomData[input.dataset.room] = { hasWater: !!input.value, hasElec: false };
        });
        var elecInputs = document.querySelectorAll('#electricPanel .meter-input[data-type="electric"]');
        elecInputs.forEach(function(input) {
            var rid = input.dataset.room;
            if (roomData[rid]) roomData[rid].hasElec = !!input.value;
        });
        // Total rooms = all <tr> in waterPanel (unique per room)
        var totalRows = document.querySelectorAll('#waterPanel .meter-table tbody tr');
        var totalRooms = totalRows.length;
        var totalRecorded = 0;
        Object.keys(roomData).forEach(function(rid) {
            if (roomData[rid].hasWater && roomData[rid].hasElec) totalRecorded++;
        });
        var totalPending = Math.max(0, totalRooms - totalRecorded);
        document.querySelectorAll('.stat-badge').forEach(function(b) {
            if (b.classList.contains('rooms')) b.textContent = totalRooms + ' ห้อง';
            else if (b.classList.contains('done')) b.textContent = totalRecorded + ' บันทึกแล้ว';
            else if (b.classList.contains('pending')) b.textContent = totalPending + ' รอ';
        });
    }

    function updateSavedRows(tab, rooms) {
        var usageType = tab;
        var padLen = usageType === 'water' ? 7 : 5;
        var panelId = usageType === 'water' ? 'waterPanel' : 'electricPanel';
        var meterPanelId = usageType === 'water' ? 'waterMeterPanel' : 'electricMeterPanel';

        Object.keys(rooms).forEach(function(roomId) {
            var room = rooms[roomId];

            // === Table view ===
            var input = document.querySelector('#' + panelId + ' .meter-input[data-room="' + roomId + '"]');
            if (!input) return;
            var row = input.closest('tr');
            if (!row) return;

            // Update row classes
            row.classList.add('saved-row');
            row.classList.remove('needs-meter', 'needs-water', 'needs-electric');
            var badge = row.querySelector('.needs-meter-badge');
            if (badge) badge.remove();

            // Update input: show new value, mark as editable-saved
            input.value = String(room['new']).padStart(padLen, '0');
            input.classList.remove('blocked-by-step');
            input.classList.add('editable-saved');
            input.disabled = false;

            // For first reading: update data-old and the "old value" cell
            if (room.is_first_reading) {
                input.dataset.old = room['new'];
                input.setAttribute('min', room['new']);
                input.placeholder = String(room['new']).padStart(padLen, '0');
                var cells = row.querySelectorAll('td');
                if (cells.length >= 3) {
                    cells[2].textContent = String(room.old).padStart(padLen, '0');
                }
                // Update hidden water_old/elec_old
                var oldHidden = row.querySelector('input[name*="' + (usageType === 'water' ? 'water_old' : 'elec_old') + '"]');
                if (oldHidden) oldHidden.value = room['new'];
            }

            // Update usage cell
            var usageCells = document.querySelectorAll('[data-room="' + roomId + '"][data-usage="' + usageType + '"]');
            usageCells.forEach(function(el) { el.textContent = room.usage; });

            // Update cost cell
            var costCells = document.querySelectorAll('[data-room="' + roomId + '"][data-amount="' + usageType + '"]');
            costCells.forEach(function(el) { el.textContent = room.cost; });

            // === Visual meter view ===
            var vmCard = document.querySelector('#' + meterPanelId + ' .vm-digit-input[data-room-id="' + roomId + '"]');
            if (vmCard) {
                var card = vmCard.closest('.vm-card');
                if (card) {
                    card.classList.remove('vm-pending');
                    card.classList.add('vm-saved');
                }
                // Update digit values
                var newStr = String(room['new']).padStart(usageType === 'water' ? 7 : 5, '0');
                var digits = document.querySelectorAll('#' + meterPanelId + ' .vm-digit-input[data-room-id="' + roomId + '"]');
                digits.forEach(function(d, i) { d.value = newStr[i] || ''; });
                // Update vm usage/cost
                var vmUsage = document.querySelector('[data-vm-usage="' + usageType + '"][data-vm-room="' + roomId + '"]');
                var vmCost = document.querySelector('[data-vm-cost="' + usageType + '"][data-vm-room="' + roomId + '"]');
                if (vmUsage) vmUsage.textContent = room.usage + ' หน่วย';
                if (vmCost) vmCost.textContent = room.cost + ' ฿';
            }
        });
    }

    function saveMeterAjax(form) {
        // ตรวจสอบก่อนบันทึก: ห้ามกรอกค่าน้อยกว่าค่าเดิม
        var hasError = false;
        form.querySelectorAll('.meter-input[data-old]').forEach(function(inp) {
            if (inp.disabled) return;
            var val = inp.value.trim();
            if (val === '') return;
            var newVal = parseInt(val, 10);
            var oldVal = parseInt(inp.dataset.old, 10);
            var isFirst = inp.dataset.firstReading === '1';
            if (!isFirst && !isNaN(newVal) && !isNaN(oldVal) && newVal < oldVal) {
                inp.style.borderColor = '#ef4444';
                inp.style.background = 'rgba(239,68,68,0.08)';
                var errId = 'meter-err-' + inp.dataset.room + '-' + inp.dataset.type;
                var existing = document.getElementById(errId);
                if (!existing) {
                    var msg = document.createElement('div');
                    msg.id = errId;
                    msg.style.cssText = 'color:#ef4444;font-size:0.72rem;margin-top:2px;';
                    msg.textContent = 'ค่าใหม่ต้องไม่น้อยกว่าค่าเดิม (' + String(oldVal).padStart(inp.dataset.type==='water'?7:5,'0') + ')';
                    inp.parentNode.appendChild(msg);
                }
                hasError = true;
            } else {
                inp.style.borderColor = '';
                inp.style.background = '';
                var e2 = document.getElementById('meter-err-' + inp.dataset.room + '-' + inp.dataset.type);
                if (e2) e2.remove();
            }
        });
        if (hasError) {
            showToast('กรุณาแก้ไขค่ามิเตอร์ที่ติดลบก่อนบันทึก', 'error');
            return;
        }
        var meterView = document.getElementById('meterView');
        if (meterView && meterView.style.display !== 'none') {
            syncMeterToTable();
        }

        var formData = new FormData(form);
        var saveBtn = form.querySelector('.save-btn');
        var saveBtnOriginal = saveBtn ? saveBtn.innerHTML : '';

        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span style="display:inline-flex;align-items:center;gap:6px;"><svg width="16" height="16" viewBox="0 0 24 24" style="animation:spin 1s linear infinite"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="32" stroke-linecap="round"/></svg>กำลังบันทึก...</span>';
        }

        fetch(window.location.pathname + window.location.search, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = saveBtnOriginal;
            }
            showToast(data.message, data.success ? 'success' : 'error');
            if (data.success && data.rooms) {
                updateSavedRows(data.tab, data.rooms);
            }
            updateTotals();
            updateStats();
        })
        .catch(function(err) {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = saveBtnOriginal;
            }
            showToast('เกิดข้อผิดพลาดในการบันทึก: ' + err.message, 'error');
        });
    }

    // Intercept form submit
    var meterForm = document.getElementById('meterForm');
    if (meterForm) {
        meterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveMeterAjax(this);
        });
    }

    switchTab(initialTab, false);
    updateTotals();

    // ===== Visual Meter View =====
    function toggleMeterView() {
        var tableView = document.getElementById('tableView');
        var meterView = document.getElementById('meterView');
        var btn = document.getElementById('viewToggleBtn');
        if (!tableView || !meterView) return;

        var showingMeter = meterView.style.display !== 'none';
        if (showingMeter) {
            syncMeterToTable();
            meterView.style.display = 'none';
            tableView.style.display = '';
            btn.classList.remove('active');
            btn.innerHTML = '<div class="vtb-shimmer"></div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg><span class="vtb-label">มุมมองมิเตอร์ (BETA)</span>';
            localStorage.setItem('utilityViewMode', 'table');
        } else {
            syncTableToMeter();
            tableView.style.display = 'none';
            meterView.style.display = '';
            btn.classList.add('active');
            btn.innerHTML = '<div class="vtb-shimmer"></div><svg viewBox="0 0 24 24" fill="none" stroke="#374151" stroke-width="2"><path d="M3 10h18M3 14h18M3 6h18M3 18h18"/></svg><span class="vtb-label">มุมมองตาราง</span>';
            localStorage.setItem('utilityViewMode', 'meter');
        }
    }

    function syncTableToMeter() {
        document.querySelectorAll('.meter-input').forEach(function(input) {
            var rid = input.dataset.room;
            var type = input.dataset.type;
            var val = input.value;
            if (!val) return;
            var totalDigits = type === 'water' ? 7 : 5;
            var padded = String(val).padStart(totalDigits, '0');
            var digits = document.querySelectorAll('.vm-digit-input[data-room-id="'+rid+'"][data-meter-type="'+type+'"]');
            digits.forEach(function(d, i) {
                d.value = padded[i] || '';
            });
        });
    }

    function syncMeterToTable() {
        var rooms = {};
        document.querySelectorAll('.vm-digit-input').forEach(function(d) {
            var rid = d.dataset.roomId;
            var type = d.dataset.meterType;
            var key = rid + '_' + type;
            if (!rooms[key]) rooms[key] = { rid: rid, type: type, digits: [] };
            rooms[key].digits[parseInt(d.dataset.digitIndex)] = d.value;
        });
        Object.keys(rooms).forEach(function(key) {
            var r = rooms[key];
            var allFilled = r.digits.length > 0 && r.digits.every(function(v) { return v !== '' && v !== undefined; });
            if (!allFilled) return;
            var val = parseInt(r.digits.join(''), 10);
            var tableInput = document.querySelector('.meter-input[data-room="'+r.rid+'"][data-type="'+r.type+'"]');
            if (tableInput && !tableInput.disabled) {
                var totalDigits = r.type === 'water' ? 7 : 5;
                tableInput.value = String(val).padStart(totalDigits, '0');
            }
        });
        updateTotals();
    }

    function initDigitInputs() {
        document.querySelectorAll('.vm-digit-input').forEach(function(input) {
            input.addEventListener('input', function() {
                var val = this.value.replace(/[^0-9]/g, '');
                this.value = val.slice(-1);
                if (this.value) {
                    var idx = parseInt(this.dataset.digitIndex);
                    var total = parseInt(this.dataset.totalDigits);
                    var rid = this.dataset.roomId;
                    var type = this.dataset.meterType;
                    if (idx < total - 1) {
                        var next = document.querySelector('.vm-digit-input[data-room-id="'+rid+'"][data-meter-type="'+type+'"][data-digit-index="'+(idx+1)+'"]');
                        if (next && !next.disabled) next.focus();
                    }
                }
                updateVisualMeter(this.dataset.roomId, this.dataset.meterType);
            });

            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && !this.value) {
                    var idx = parseInt(this.dataset.digitIndex);
                    if (idx > 0) {
                        var prev = document.querySelector('.vm-digit-input[data-room-id="'+this.dataset.roomId+'"][data-meter-type="'+this.dataset.meterType+'"][data-digit-index="'+(idx-1)+'"]');
                        if (prev && !prev.disabled) { prev.focus(); prev.value = ''; }
                    }
                    e.preventDefault();
                }
                if (e.key === 'ArrowLeft') {
                    var idx2 = parseInt(this.dataset.digitIndex);
                    if (idx2 > 0) {
                        var prev2 = document.querySelector('.vm-digit-input[data-room-id="'+this.dataset.roomId+'"][data-meter-type="'+this.dataset.meterType+'"][data-digit-index="'+(idx2-1)+'"]');
                        if (prev2) prev2.focus();
                    }
                }
                if (e.key === 'ArrowRight') {
                    var idx3 = parseInt(this.dataset.digitIndex);
                    var total3 = parseInt(this.dataset.totalDigits);
                    if (idx3 < total3 - 1) {
                        var next3 = document.querySelector('.vm-digit-input[data-room-id="'+this.dataset.roomId+'"][data-meter-type="'+this.dataset.meterType+'"][data-digit-index="'+(idx3+1)+'"]');
                        if (next3) next3.focus();
                    }
                }
            });

            input.addEventListener('focus', function() { this.select(); });
        });
    }

    function updateVisualMeter(rid, type) {
        var digits = document.querySelectorAll('.vm-digit-input[data-room-id="'+rid+'"][data-meter-type="'+type+'"]');
        var vals = [];
        digits.forEach(function(d) { vals.push(d.value || ''); });
        var allFilled = vals.every(function(v) { return v !== ''; });

        var tableInput = document.querySelector('.meter-input[data-room="'+rid+'"][data-type="'+type+'"]');
        if (!tableInput) return;
        var oldVal = parseInt(tableInput.dataset.old) || 0;
        var isFirstReading = tableInput.dataset.firstReading === '1';

        if (allFilled) {
            var newVal = parseInt(vals.join(''), 10);
            if (!tableInput.disabled) {
                var totalDigits = type === 'water' ? 7 : 5;
                tableInput.value = String(newVal).padStart(totalDigits, '0');
            }
            var used = Math.max(0, newVal - oldVal);
            var cost = 0;
            if (!isFirstReading) {
                cost = type === 'water' ? calculateWaterCost(used) : (used * electricRate);
            }
            var usageEl = document.querySelector('[data-vm-usage="'+type+'"][data-vm-room="'+rid+'"]');
            var costEl = document.querySelector('[data-vm-cost="'+type+'"][data-vm-room="'+rid+'"]');
            if (usageEl) usageEl.textContent = used + ' หน่วย';
            if (costEl) costEl.textContent = cost + ' ฿';
        }
        updateTotals();
    }

    // Restore view mode + init
    (function() {
        var mode = localStorage.getItem('utilityViewMode');
        if (mode === 'meter') {
            setTimeout(function() { toggleMeterView(); }, 50);
        }
        initDigitInputs();
    })();
    </script>
    <script src="/dormitory_management/Public/Assets/Javascript/animate-ui.js"></script>
<script src="/dormitory_management/Public/Assets/Js/futuristic-bright.js"></script>
</body>
</html>
