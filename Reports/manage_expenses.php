<?php
declare(strict_types=1);
session_start();
// global exception handler to avoid blank 500 pages
set_exception_handler(function(Throwable $e) {
    error_log('[manage_expenses] ' . $e->getMessage());
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
require_once __DIR__ . '/../includes/thai_date_helper.php';
require_once __DIR__ . '/../includes/water_calc.php';
$pdo = connectDB();

// --- อัตโนมัติสร้างรายการค่าใช้จ่ายใหม่เมื่อถึงเดือนใหม่ ---
$today = new DateTime();
$currentMonth = $today->format('Y-m');
$todayDay = (int)$today->format('j'); // วันที่ปัจจุบัน (1-31)

// ดึงวันที่ออกบิลจาก settings (default = 1 = ทุกวันที่ 1 เหมือนเดิม)
$billingGenerateDaySetting = (int)($pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'billing_generate_day' LIMIT 1")->fetchColumn() ?: 1);
// ถ้าวันนี้ยังไม่ถึงวันที่กำหนดออกบิล → ไม่สร้างบิลเดือนปัจจุบัน ใช้เดือนก่อนหน้าแทน
$effectiveCurrentMonth = ($todayDay >= $billingGenerateDaySetting)
    ? $currentMonth
    : (new DateTime($currentMonth . '-01'))->modify('-1 month')->format('Y-m');

// Auto-cleanup: ลบบิลที่ถูกสร้างก่อนกำหนด (ยังไม่ถึงวันออกบิล)
// กฎ: ลบเฉพาะบิลที่ (1) เดือน > effectiveCurrentMonth
//         (2) ไม่มีรายการชำระเงินใดๆ (NOT EXISTS)
//         (3) ยังไม่ชำระ/ไม่ชำระบางส่วน (ไม่ใช่ status 1=ชำระแล้ว, 3=ชำระยังไม่ครบ)
// ป้องกัน: บิลที่ผู้เช่าชำระแล้ว (status=1 หรือ 3) จะไม่ถูกลบไม่ว่ากรณีใด
if ($todayDay < $billingGenerateDaySetting) {
    $pdo->exec("
        DELETE e FROM expense e
        WHERE DATE_FORMAT(e.exp_month,'%Y-%m') > '{$effectiveCurrentMonth}'
          AND e.exp_status NOT IN ('1', '3')
          AND NOT EXISTS (
              SELECT 1 FROM payment p WHERE p.exp_id = e.exp_id
          )
    ");
}
// ดึงเฉพาะสัญญาที่ยังไม่หมดอายุและผ่าน Wizard ถึง Step 5 แล้ว
$contractsStmt = $pdo->query("\n  SELECT DISTINCT c.ctr_id, c.ctr_start, c.ctr_end\n  FROM contract c\n  INNER JOIN tenant_workflow tw ON tw.ctr_id = c.ctr_id\n  WHERE c.ctr_status = '0'\n    AND (COALESCE(tw.step_5_confirmed, 0) = 1 OR COALESCE(tw.current_step, 0) >= 5)\n");
$contracts = $contractsStmt ? $contractsStmt->fetchAll(PDO::FETCH_ASSOC) : [];
foreach ($contracts as $contract) {
  $ctr_id = (int)$contract['ctr_id'];
  $ctrStartDate = new DateTime($contract['ctr_start']);
  $ctrEndDate = new DateTime($contract['ctr_end']);
  $ctr_start = $ctrStartDate->format('Y-m');
  $ctr_end = $ctrEndDate->format('Y-m');
  // เริ่มคิดบิลรายเดือนตั้งแต่เดือนที่เริ่มสัญญา (ไม่ใช่เดือนถัดไป)
  $firstBillMonth = $ctrStartDate->format('Y-m');
  // วนสร้างทุกเดือนตั้งแต่ firstBillMonth ถึง effectiveCurrentMonth
  // (ใช้ firstBillMonth เสมอ ไม่ใช่ last_month+1 เพื่อป้องกันเดือนที่หายไป)
  $nextMonth = $firstBillMonth;
  while ($nextMonth <= $effectiveCurrentMonth && $nextMonth <= $ctr_end) {
    // ตรวจสอบว่ามีรายการแล้วหรือยัง
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM expense WHERE ctr_id = ? AND DATE_FORMAT(exp_month, '%Y-%m') = ?");
    $checkStmt->execute([$ctr_id, $nextMonth]);
    if ((int)$checkStmt->fetchColumn() === 0) {
      // ดึงราคาห้อง
      $roomStmt = $pdo->prepare("SELECT r.room_id, rt.type_price FROM contract c LEFT JOIN room r ON c.room_id = r.room_id LEFT JOIN roomtype rt ON r.type_id = rt.type_id WHERE c.ctr_id = ?");
      $roomStmt->execute([$ctr_id]);
      $room = $roomStmt->fetch(PDO::FETCH_ASSOC);
      $room_price = (int)($room['type_price'] ?? 0);
      // ดึงเรตไฟ/น้ำ ล่าสุด
      $rateRow = $pdo->query("SELECT rate_water, rate_elec FROM rate ORDER BY rate_id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
      $rate_elec = (int)($rateRow['rate_elec'] ?? 7);
      $rate_water = (int)($rateRow['rate_water'] ?? 20);
      // สร้างรายการใหม่ (หน่วยไฟ/น้ำ = 0)
      // IMPORTANT: ยอดรวม = ค่าห้อง ไม่หักมัดจำ 2000 บาท (มัดจำเป็นเรื่องแยกต่างหาก)
      $insert = $pdo->prepare("INSERT INTO expense (exp_month, exp_elec_unit, exp_water_unit, rate_elec, rate_water, room_price, exp_elec_chg, exp_water, exp_total, exp_status, ctr_id) VALUES (?, 0, 0, ?, ?, ?, 0, 0, ?, '2', ?)");
      $exp_total = $room_price;
      $insert->execute([
        $nextMonth . '-01',
        $rate_elec,
        $rate_water,
        $room_price,
        $exp_total,
        $ctr_id
      ]);
    }
    // ไปเดือนถัดไป
    $nextMonth = (new DateTime($nextMonth . '-01'))->modify('+1 month')->format('Y-m');
  }
}
// --- END อัตโนมัติ ---

// --- อัปเดตสถานะค้างชำระอัตโนมัติ ---
include __DIR__ . '/../Manage/auto_update_overdue.php';
// --- END ค้างชำระ ---

// ดึง theme color จากการตั้งค่าระบบ
$settingsStmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'theme_color' LIMIT 1");
$themeColor = '#0f172a'; // ค่า default (dark mode)
if ($settingsStmt) {
    $theme = $settingsStmt->fetch(PDO::FETCH_ASSOC);
    if ($theme && !empty($theme['setting_value'])) {
        $themeColor = htmlspecialchars($theme['setting_value'], ENT_QUOTES, 'UTF-8');
    }
}

// รับค่า sort จาก query parameter
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$orderBy = 'e.exp_id DESC';

switch ($sortBy) {
  case 'oldest':
    $orderBy = 'e.exp_id ASC';
    break;
  case 'room_number':
    $orderBy = 'r.room_number ASC';
    break;
  case 'newest':
  default:
    $orderBy = 'e.exp_id DESC';
}

$availableMonths = [];
try {
  $monthStmt = $pdo->prepare("\n    SELECT DISTINCT DATE_FORMAT(e.exp_month, '%Y-%m') AS month_key\n    FROM expense e\n    LEFT JOIN contract c ON e.ctr_id = c.ctr_id\n    LEFT JOIN tenant_workflow tw ON tw.ctr_id = c.ctr_id\n    WHERE c.ctr_status = '0'\n      AND (COALESCE(tw.step_5_confirmed, 0) = 1 OR COALESCE(tw.current_step, 0) >= 5)\n      AND e.exp_month IS NOT NULL\n      AND DATE_FORMAT(e.exp_month, '%Y-%m') <= :currentMonth\n    ORDER BY month_key DESC\n  ");
  $monthStmt->execute([':currentMonth' => $currentMonth]);
  $availableMonths = $monthStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}

// Always include the actual calendar month so admin can view/select it even before billing day
if (!in_array($currentMonth, $availableMonths, true)) {
  array_unshift($availableMonths, $currentMonth);
}

$selectedMonth = isset($_GET['filter_month']) ? trim((string)$_GET['filter_month']) : '';
if ($selectedMonth === '' && !empty($availableMonths)) {
  $selectedMonth = (string)$availableMonths[0];
}
if ($selectedMonth !== '' && !in_array($selectedMonth, $availableMonths, true)) {
  $selectedMonth = !empty($availableMonths) ? (string)$availableMonths[0] : '';
}

// Sync utility readings -> expense (for month being viewed) so amounts match manage_utility
if ($selectedMonth !== '' && preg_match('/^\d{4}-\d{2}$/', $selectedMonth) === 1) {
  [$syncYear, $syncMonth] = explode('-', $selectedMonth);
  $syncYearInt = (int)$syncYear;
  $syncMonthInt = (int)$syncMonth;

  try {
    $syncStmt = $pdo->prepare("\n      SELECT\n        e.exp_id,\n        e.room_price,\n        e.rate_elec,\n        e.exp_status,\n        u.utl_water_start,\n        u.utl_water_end,\n        u.utl_elec_start,\n        u.utl_elec_end\n      FROM expense e\n      LEFT JOIN utility u\n        ON u.ctr_id = e.ctr_id\n       AND YEAR(u.utl_date) = :syncYear\n       AND MONTH(u.utl_date) = :syncMonth\n      LEFT JOIN contract c ON e.ctr_id = c.ctr_id\n      LEFT JOIN tenant_workflow tw ON tw.ctr_id = c.ctr_id\n      WHERE DATE_FORMAT(e.exp_month, '%Y-%m') = :syncMonthKey\n        AND c.ctr_status = '0'\n        AND (COALESCE(tw.step_5_confirmed, 0) = 1 OR COALESCE(tw.current_step, 0) >= 5)\n    ");
    $syncStmt->execute([
      ':syncYear' => $syncYearInt,
      ':syncMonth' => $syncMonthInt,
      ':syncMonthKey' => $selectedMonth,
    ]);

    $updateExpenseStmt = $pdo->prepare("\n      UPDATE expense\n      SET\n        exp_elec_unit = :expElecUnit,\n        exp_water_unit = :expWaterUnit,\n        exp_elec_chg = :expElecChg,\n        exp_water = :expWater,\n        exp_total = :expTotal,\n        exp_status = :expStatus\n      WHERE exp_id = :expId\n    ");

    while ($row = $syncStmt->fetch(PDO::FETCH_ASSOC)) {
      $hasCompleteUtilityReading = ($row['utl_water_end'] !== null && $row['utl_elec_end'] !== null);

      $waterUsed = 0;
      $elecUsed = 0;
      if ($hasCompleteUtilityReading) {
        $waterUsed = max(0, (int)$row['utl_water_end'] - (int)$row['utl_water_start']);
        $elecUsed = max(0, (int)$row['utl_elec_end'] - (int)$row['utl_elec_start']);
      }

      $rateElec = (int)($row['rate_elec'] ?? 0);
      $elecCost = (int)round($elecUsed * $rateElec);
      $waterCost = (int)calculateWaterCost($waterUsed);
      $roomPrice = (int)($row['room_price'] ?? 0);
      $expTotal = $roomPrice + $elecCost + $waterCost;
      $currentStatus = (string)($row['exp_status'] ?? '0');
      $nextStatus = $currentStatus;

      // ถ้ายังจดมิเตอร์ไม่ครบ ให้คงสถานะรอตรวจสอบไว้ (ยังไม่ออกบิลผู้เช่า)
      if (!$hasCompleteUtilityReading) {
        if ($currentStatus === '0' || $currentStatus === '2') {
          $nextStatus = '2';
        }
      } elseif ($currentStatus === '2') {
        // เมื่อจดมิเตอร์ครบแล้ว ให้กลับมาเป็นรอชำระเพื่อเปิดรอบบิล
        $nextStatus = '0';
      }

      $updateExpenseStmt->execute([
        ':expElecUnit' => $elecUsed,
        ':expWaterUnit' => $waterUsed,
        ':expElecChg' => $elecCost,
        ':expWater' => $waterCost,
        ':expTotal' => $expTotal,
        ':expStatus' => $nextStatus,
        ':expId' => (int)$row['exp_id'],
      ]);
    }
  } catch (PDOException $e) {
    // Keep page usable even if sync fails.
  }
}

// ดึงข้อมูลค่าใช้จ่าย - เฉพาะสัญญาที่ active (ctr_status = '0')
// และแยกตามเดือนที่เลือกเท่านั้น
$expenseSql = "\n  SELECT e.*,
         c.ctr_id, c.tnt_id, c.ctr_start, c.ctr_end, c.ctr_status,
         t.tnt_name, t.tnt_phone,
         r.room_number, r.room_id,
         rt.type_name
  FROM expense e
  LEFT JOIN contract c ON e.ctr_id = c.ctr_id
  LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
  LEFT JOIN room r ON c.room_id = r.room_id
  LEFT JOIN roomtype rt ON r.type_id = rt.type_id
  WHERE c.ctr_status = '0'
    AND EXISTS (
      SELECT 1
      FROM tenant_workflow tw
      WHERE tw.ctr_id = c.ctr_id
        AND (COALESCE(tw.step_5_confirmed, 0) = 1 OR COALESCE(tw.current_step, 0) >= 5)
    )";

$expenseParams = [];
if ($selectedMonth !== '') {
  $expenseSql .= "\n  AND DATE_FORMAT(e.exp_month, '%Y-%m') = :selectedMonth";
  $expenseParams[':selectedMonth'] = $selectedMonth;
}

$expenseSql .= "
  GROUP BY e.exp_id
  ORDER BY $orderBy
";

$expStmt = $pdo->prepare($expenseSql);
$expStmt->execute($expenseParams);
$expenses = $expStmt->fetchAll(PDO::FETCH_ASSOC);

// ตรวจสอบห้องที่ยังไม่ได้จดมิเตอร์เดือนที่กำลังดูอยู่
$meterMissingByExp = []; // exp_id => true
$meterMissingRooms = []; // [['room_number'=>, 'tnt_name'=>, 'month'=>]]
if ($selectedMonth !== '' && !empty($expenses)) {
  [$filterYear, $filterMon] = explode('-', $selectedMonth);
  $filterYearInt = (int)$filterYear;
  $filterMonInt  = (int)$filterMon;

  $expByCtr = [];
  foreach ($expenses as $exp) {
    $expByCtr[(int)$exp['ctr_id']] = $exp;
  }

  if (!empty($expByCtr)) {
    $ctrIds = array_keys($expByCtr);
    $ph = implode(',', array_fill(0, count($ctrIds), '?'));
    $utilChkStmt = $pdo->prepare(
      "SELECT DISTINCT ctr_id FROM utility
       WHERE ctr_id IN ($ph)
         AND MONTH(utl_date) = ? AND YEAR(utl_date) = ?
         AND COALESCE(utl_water_end, 0) > 0
         AND COALESCE(utl_elec_end, 0) > 0"
    );
    $utilChkStmt->execute([...$ctrIds, $filterMonInt, $filterYearInt]);

    $hasUtilSet = [];
    foreach ($utilChkStmt->fetchAll(PDO::FETCH_COLUMN) as $cid) {
      $hasUtilSet[(int)$cid] = true;
    }

    foreach ($expByCtr as $ctrId => $exp) {
      if (empty($hasUtilSet[$ctrId])) {
        $meterMissingByExp[(int)$exp['exp_id']] = true;
        $meterMissingRooms[] = [
          'room_number' => $exp['room_number'] ?? '-',
          'tnt_name'    => $exp['tnt_name'] ?? '-',
          'month'       => date('m/Y', strtotime((string)$exp['exp_month'])),
        ];
      }
    }
  }
}

// ดึงข้อมูล payment สำหรับแต่ละ expense (เฉพาะที่อนุมัติแล้ว pay_status = '1')
// สำคัญ: ไม่นับรวม payment ที่มี pay_remark = 'มัดจำ' เพราะมัดจำไม่ใช่การชำระค่าใช้จ่ายรายเดือน
$paymentsByExp = [];
$paymentStmt = $pdo->query("
  SELECT exp_id, pay_id, pay_date, pay_amount, pay_status, pay_remark
  FROM payment
  WHERE pay_status = '1' AND TRIM(COALESCE(pay_remark, '')) <> 'มัดจำ'
  ORDER BY pay_date ASC
");
while ($pay = $paymentStmt->fetch(PDO::FETCH_ASSOC)) {
    $expId = (int)$pay['exp_id'];
    if (!isset($paymentsByExp[$expId])) {
        $paymentsByExp[$expId] = ['total_paid' => 0, 'count' => 0, 'payments' => []];
    }
    $paymentsByExp[$expId]['total_paid'] += (int)$pay['pay_amount'];
    $paymentsByExp[$expId]['count']++;
    $paymentsByExp[$expId]['payments'][] = $pay;
}

$paymentFlagsByExp = [];
try {
  $paymentFlagStmt = $pdo->query("\n    SELECT\n      exp_id,\n      SUM(CASE WHEN pay_status = '0' AND pay_proof IS NOT NULL AND pay_proof <> '' AND TRIM(COALESCE(pay_remark, '')) <> 'มัดจำ' THEN 1 ELSE 0 END) AS pending_count,\n      SUM(CASE WHEN pay_status = '1' AND TRIM(COALESCE(pay_remark, '')) <> 'มัดจำ' THEN pay_amount ELSE 0 END) AS approved_amount\n    FROM payment\n    GROUP BY exp_id\n  ");
  while ($row = $paymentFlagStmt->fetch(PDO::FETCH_ASSOC)) {
    $paymentFlagsByExp[(int)$row['exp_id']] = [
      'pending_count' => (int)($row['pending_count'] ?? 0),
      'approved_amount' => (int)($row['approved_amount'] ?? 0),
    ];
  }
} catch (PDOException $e) {}

// ดึงสัญญาที่ใช้งานอยู่สำหรับฟอร์ม
$activeContracts = $pdo->query("
  SELECT c.ctr_id, c.room_id,
         t.tnt_name,
         r.room_number,
         rt.type_price
  FROM contract c
  LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
  LEFT JOIN room r ON c.room_id = r.room_id
  LEFT JOIN roomtype rt ON r.type_id = rt.type_id
  WHERE c.ctr_status = '0'
    AND EXISTS (
      SELECT 1
      FROM tenant_workflow tw
      WHERE tw.ctr_id = c.ctr_id
        AND (COALESCE(tw.step_5_confirmed, 0) = 1 OR COALESCE(tw.current_step, 0) >= 5)
    )
  ORDER BY r.room_number
")->fetchAll(PDO::FETCH_ASSOC);

// ดึงค่าไฟ/ค่าน้ำ จากตาราง rate (คอลัมน์ rate_water, rate_elec)
$rateRows = [];
try {
  $rateRows = $pdo->query("SELECT rate_id, rate_water, rate_elec FROM rate ORDER BY rate_id")
          ->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  // ใช้ค่า fallback หากไม่มีตาราง rate หรือคอลัมน์ไม่ตรง
  $rateRows = [];
}

$electricRates = array_map(function(array $row) {
  return ['rate_id' => $row['rate_id'] ?? null, 'rate_price' => $row['rate_elec'] ?? null];
}, $rateRows);
$waterRates = array_map(function(array $row) {
  return ['rate_id' => $row['rate_id'] ?? null, 'rate_price' => $row['rate_water'] ?? null];
}, $rateRows);

// fallback ค่าเดิมถ้าไม่มีข้อมูลจากตาราง rate
if (empty($electricRates)) {
  $electricRates = [ ['rate_id' => null, 'rate_price' => 7] ];
}
if (empty($waterRates)) {
  $waterRates = [ ['rate_id' => null, 'rate_price' => 20] ];
}

$statusMap = [
  '0' => 'รอชำระ',
  '1' => 'ชำระแล้ว',
  '2' => 'รอตรวจสอบ',
  '3' => 'ชำระยังไม่ครบ',
  '4' => 'ค้างชำระ',
];
$statusColors = [
  '0' => '#ef4444',
  '1' => '#22c55e',
  '2' => '#ff9800',
  '3' => '#f59e0b',
  '4' => '#dc2626',
];

$buildExpenseStatus = function(array $exp) use (
  $meterMissingByExp,
  $paymentFlagsByExp,
  $statusMap,
  $statusColors
) {
  $expId = (int)($exp['exp_id'] ?? 0);
  $hasMeterMissing = !empty($meterMissingByExp[$expId]);

  $chargesPaid = (int)($paymentFlagsByExp[$expId]['approved_amount'] ?? 0);
  $pendingCount = (int)($paymentFlagsByExp[$expId]['pending_count'] ?? 0);
  $chargesTotal = (int)($exp['exp_total'] ?? 0);

  if ($hasMeterMissing) {
    $chargesRemain = max(0, $chargesTotal - $chargesPaid);
    return [
      'status' => '2',
      'statusText' => 'ยังไม่ได้จดมิเตอร์',
      'statusColor' => '#ef4444',
      'totalColor' => '#ef4444',
      'chargesPaid' => $chargesPaid,
      'chargesTotal' => $chargesTotal,
      'chargesRemain' => $chargesRemain,
    ];
  }

  if ($pendingCount > 0) {
    $status = '2';
  } elseif ($chargesPaid >= $chargesTotal) {
    $status = '1';
  } elseif ($chargesPaid > 0) {
    $status = '3';
  } else {
    $status = '0';
  }

  $chargesRemain = max(0, $chargesTotal - $chargesPaid);

  $statusText = $statusMap[$status] ?? 'ไม่ระบุ';
  // ตรวจสอบสถานะค้างชำระจากค่าใน DB (exp_status = '4')
  $dbStatus = (string)($exp['exp_status'] ?? '0');
  if ($dbStatus === '4' && in_array($status, ['0', '3'], true)) {
    $status = '4';
  }

  if ($status === '0') {
    $statusText = 'รอชำระ';
  } elseif ($status === '3') {
    $statusText = 'ชำระยังไม่ครบ';
  } elseif ($status === '4') {
    $statusText = 'ค้างชำระ';
  }

  return [
    'status' => $status,
    'statusText' => $statusText,
    'statusColor' => $statusColors[$status] ?? '#94a3b8',
    'totalColor' => in_array($status, ['0', '3', '4'], true) ? '#ef4444' : '#22c55e',
    'chargesPaid' => $chargesPaid,
    'chargesTotal' => $chargesTotal,
    'chargesRemain' => $chargesRemain,
  ];
};

// คำนวณสถิติ
$stats = [
  'unpaid' => 0,
  'paid' => 0,
  'pending' => 0,
  'partial' => 0,
  'overdue' => 0,
  'total_unpaid' => 0,
  'total_paid' => 0,
  'total_pending' => 0,
  'total_partial' => 0,
  'total_overdue' => 0,
];
foreach ($expenses as $exp) {
    $rs = $buildExpenseStatus($exp);
    $status = $rs['status'];
    $expTotal = (int)($exp['exp_total'] ?? 0);
    if ($status === '0') {
        $stats['unpaid']++;
        $stats['total_unpaid'] += $expTotal;
    } elseif ($status === '1') {
        $stats['paid']++;
        $stats['total_paid'] += $expTotal;
    } elseif ($status === '2') {
        $stats['pending']++;
        $stats['total_pending'] += $expTotal;
    } elseif ($status === '3') {
        $stats['partial']++;
        $stats['total_partial'] += $expTotal;
    } elseif ($status === '4') {
        $stats['overdue']++;
        $stats['total_overdue'] += $expTotal;
    }
}

// ดึงค่าตั้งค่าระบบ
$siteName = 'Sangthian Dormitory';

// คำนวณ collection rate
$totalAll = $stats['total_unpaid'] + $stats['total_paid'] + $stats['total_pending'] + $stats['total_partial'] + $stats['total_overdue'];
$collectionPct = $totalAll > 0 ? round(($stats['total_paid'] / $totalAll) * 100) : 0;
$pendingPartialCount = $stats['pending'] + $stats['partial'];
$pendingPartialTotal = $stats['total_pending'] + $stats['total_partial'];
$totalExpenseCount = $stats['unpaid'] + $stats['paid'] + $stats['pending'] + $stats['partial'] + $stats['overdue'];

$selectedStatusFilter = isset($_GET['filter_status']) ? trim((string)$_GET['filter_status']) : 'all';
$allowedStatusFilters = ['all', '0', '1', '2', '3', '4'];
if (!in_array($selectedStatusFilter, $allowedStatusFilters, true)) {
  $selectedStatusFilter = 'all';
}

$displayExpenses = $expenses;
if ($selectedStatusFilter !== 'all') {
  $displayExpenses = array_values(array_filter($expenses, static function(array $exp) use ($buildExpenseStatus, $selectedStatusFilter): bool {
    $statusInfo = $buildExpenseStatus($exp);
    return (string)($statusInfo['status'] ?? '') === $selectedStatusFilter;
  }));
}

// --- END ตรวจสอบมิเตอร์ ---

$logoFilename = 'Logo.jpg';
$settings = [
  'bank_name' => '',
  'bank_account_number' => '',
];
try {
  $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename', 'bank_name', 'bank_account_number')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
        if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
    if ($row['setting_key'] === 'bank_name') $settings['bank_name'] = (string)$row['setting_value'];
    if ($row['setting_key'] === 'bank_account_number') $settings['bank_account_number'] = (string)$row['setting_value'];
    }
} catch (PDOException $e) {}
?>
<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - จัดการค่าใช้จ่าย</title>
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/particle-effects.css">
    <script src="/dormitory_management/Public/Assets/Javascript/particle-effects.js"></script>
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/confirm-modal.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4/dist/style.css" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/datatable-modern.css" />
    <style>
      :root {
        --theme-bg-color: <?php echo $themeColor; ?>;
      }
      
      .expense-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
      }
      .expense-stats-note {
        margin-top: 0.75rem;
        padding: 0.65rem 0.9rem;
        border-radius: 10px;
        border: 1px solid rgba(56, 189, 248, 0.35);
        background: rgba(56, 189, 248, 0.12);
        color: #0369a1;
        font-size: 0.88rem;
        font-weight: 600;
      }
      .meter-alert-banner {
        margin-bottom: 1.1rem;
        padding: 1rem 1.2rem;
        border-radius: 12px;
        border: 1px solid rgba(239,68,68,0.45);
        background: rgba(239,68,68,0.08);
        color: #b91c1c;
      }
      .meter-alert-banner .meter-alert-title {
        display: flex; align-items: center; gap: 0.55rem;
        font-size: 0.98rem; font-weight: 700; margin-bottom: 0.55rem;
      }
      .meter-alert-banner .meter-alert-list {
        list-style: none; margin: 0; padding: 0;
        display: flex; flex-wrap: wrap; gap: 0.45rem;
      }
      .meter-alert-banner .meter-alert-list li {
        background: rgba(239,68,68,0.12);
        border: 1px solid rgba(239,68,68,0.3);
        border-radius: 20px;
        padding: 0.2rem 0.65rem;
        font-size: 0.82rem; font-weight: 600;
      }
      html.light-theme .meter-alert-banner { color: #991b1b; }
      .meter-missing-badge {
        display: inline-flex; align-items: center; gap: 0.25rem;
        background: rgba(239,68,68,0.12);
        border: 1px solid rgba(239,68,68,0.3);
        border-radius: 12px; padding: 0.1rem 0.45rem;
        font-size: 0.74rem; font-weight: 700; color: #dc2626;
        white-space: nowrap; margin-top: 0.2rem;
      }
      .expense-stat-card {
        background: linear-gradient(135deg, rgba(12, 22, 42, 0.92), rgba(10, 16, 30, 0.96));
        border-radius: 16px;
        padding: 1.25rem;
        border: 1px solid rgba(148, 163, 184, 0.22);
        color: #f5f8ff;
        box-shadow: 0 10px 26px rgba(3, 7, 18, 0.34);
      }
      .expense-stat-card .stat-head {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.65rem;
      }
      .expense-stat-card .status-dot {
        width: 10px;
        height: 10px;
        border-radius: 999px;
        box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.08);
      }
      .expense-stat-card h3 {
        margin: 0;
        font-size: 0.95rem;
        color: rgba(226, 232, 240, 0.92);
      }
      .expense-stat-card .stat-kpi-label {
        font-size: 0.82rem;
        color: rgba(148, 163, 184, 0.95);
        margin-top: 0.45rem;
      }
      .expense-stat-card .stat-value {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1.1;
        margin-top: 0.2rem;
      }
      .expense-stat-card .stat-money {
        font-size: 1.25rem;
        font-weight: 700;
        margin-top: 0.15rem;
      }
      .expense-stat-card.is-unpaid .status-dot {
        background: #ef4444;
      }
      .expense-stat-card.is-unpaid .stat-value,
      .expense-stat-card.is-unpaid .stat-money {
        color: #f87171;
      }
      .expense-stat-card.is-paid .status-dot {
        background: #22c55e;
      }
      .expense-stat-card.is-paid .stat-value,
      .expense-stat-card.is-paid .stat-money {
        color: #4ade80;
      }
      .expense-stat-card.is-total .status-dot {
        background: #60a5fa;
      }
      .expense-stat-card.is-total .stat-value,
      .expense-stat-card.is-total .stat-money {
        color: #93c5fd;
      }
      .expense-stat-card.is-overdue .status-dot {
        background: #dc2626;
      }
      .expense-stat-card.is-overdue .stat-value,
      .expense-stat-card.is-overdue .stat-money {
        color: #fca5a5;
      }
      
      /* Light theme overrides for expense stat cards */
      @media (prefers-color-scheme: light) {
        .expense-stat-card {
          background: #ffffff !important;
          border: 1px solid rgba(148, 163, 184, 0.35) !important;
          color: #374151 !important;
          box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08) !important;
        }
        .expense-stat-card h3 {
          color: #0f172a !important;
        }
        .expense-stat-card .stat-kpi-label {
          color: #64748b !important;
        }
        .expense-stat-card.is-unpaid .stat-value,
        .expense-stat-card.is-unpaid .stat-money {
          color: #dc2626 !important;
        }
        .expense-stat-card.is-paid .stat-value,
        .expense-stat-card.is-paid .stat-money {
          color: #16a34a !important;
        }
        .expense-stat-card.is-total .stat-value,
        .expense-stat-card.is-total .stat-money {
          color: #2563eb !important;
        }
        .expense-stat-card.is-overdue .stat-value,
        .expense-stat-card.is-overdue .stat-money {
          color: #b91c1c !important;
        }
      }
      
      /* JavaScript-detected light theme class */
      html.light-theme .expense-stat-card {
        background: #ffffff !important;
        border: 1px solid rgba(148, 163, 184, 0.35) !important;
        color: #374151 !important;
        box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08) !important;
      }
      
      html.light-theme .expense-stat-card h3 {
        color: #0f172a !important;
      }
      
      html.light-theme .expense-stat-card .stat-kpi-label {
        color: #64748b !important;
      }
      html.light-theme .expense-stat-card.is-unpaid .stat-value,
      html.light-theme .expense-stat-card.is-unpaid .stat-money {
        color: #dc2626 !important;
      }
      html.light-theme .expense-stat-card.is-paid .stat-value,
      html.light-theme .expense-stat-card.is-paid .stat-money {
        color: #16a34a !important;
      }
      html.light-theme .expense-stat-card.is-total .stat-value,
      html.light-theme .expense-stat-card.is-total .stat-money {
        color: #2563eb !important;
      }
      html.light-theme .expense-stat-card.is-overdue .stat-value,
      html.light-theme .expense-stat-card.is-overdue .stat-money {
        color: #b91c1c !important;
      }
      html.light-theme .expense-stats-note {
        border-color: rgba(14, 116, 144, 0.35) !important;
        background: rgba(14, 165, 233, 0.12) !important;
        color: #075985 !important;
      }
      .expense-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-top: 1.5rem;
      }
      .expense-form-group label {
        color: rgba(255,255,255,0.8);
        font-weight: 600;
        display: block;
        margin-bottom: 0.4rem;
        font-size: 0.9rem;
      }
      /* Default: Dark mode inputs */
      .expense-form-group input,
      .expense-form-group select {
        width: 100%;
        padding: 0.75rem 0.85rem;
        border-radius: 10px;
        background: rgba(15,23,42,0.9);
        color: #e2e8f0;
        border: 1px solid rgba(148,163,184,0.35);
        font-size: 0.95rem;
        transition: background 0.35s ease, color 0.35s ease, border-color 0.35s ease;
      }
      .expense-form-group input::placeholder,
      .expense-form-group select::placeholder {
        color: rgba(226,232,240,0.7);
      }
      .expense-form-group input:focus,
      .expense-form-group select:focus {
        outline: none;
        border-color: #60a5fa;
        box-shadow: 0 0 0 2px rgba(96,165,250,0.25);
      }
      
      /* Light theme overrides for form inputs */
      @media (prefers-color-scheme: light) {
        .expense-form-group input,
        .expense-form-group select {
          background: #ffffff !important;
          color: #1f2937 !important;
          border: 1px solid #e5e7eb !important;
        }
        .expense-form-group input::placeholder,
        .expense-form-group select::placeholder {
          color: #9ca3af !important;
        }
        .expense-form-group label {
          color: #374151 !important;
        }
      }
      
      /* JavaScript-detected light theme class */
      html.light-theme .expense-form-group input,
      html.light-theme .expense-form-group select {
        background: #ffffff !important;
        color: #1f2937 !important;
        border: 1px solid #e5e7eb !important;
      }
      
      html.light-theme .expense-form-group input::placeholder,
      html.light-theme .expense-form-group select::placeholder {
        color: #9ca3af !important;
      }
      
      html.light-theme .expense-form-group label {
        color: #374151 !important;
      }
      .add-type-row { display:flex; align-items:center; gap:0.5rem; justify-content:space-between; }
      .add-type-btn {
        padding: 0.5rem 0.85rem;
        border-radius: 10px;
        border: 1px dashed rgba(96,165,250,0.6);
        background: rgba(15,23,42,0.7);
        color: #60a5fa;
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
        transition: background 0.2s ease, border-color 0.2s ease;
      }
      .add-type-btn:hover { background: rgba(37,99,235,0.15); border-color: rgba(96,165,250,0.95); }
      .delete-type-btn { border-color: rgba(248,113,113,0.45); color: #fca5a5; }
      .delete-type-btn:hover { background: rgba(248,113,113,0.12); border-color: rgba(248,113,113,0.75); }
      .expense-form-actions {
        grid-column: 1 / -1;
        display: flex;
        gap: 0.75rem;
        margin-top: 1rem;
      }
      .calc-preview {
        grid-column: 1 / -1;
        padding: 1rem;
        background: rgba(59,130,246,0.1);
        border: 1px solid rgba(59,130,246,0.3);
        border-radius: 10px;
        color: #93c5fd;
      }
      .calc-preview h4 {
        margin: 0 0 0.5rem 0;
        font-size: 1rem;
      }
      .calc-row {
        display: flex;
        justify-content: space-between;
        padding: 0.25rem 0;
        font-size: 0.9rem;
      }
      .calc-row.total {
        margin-top: 0.5rem;
        padding-top: 0.5rem;
        border-top: 1px solid rgba(59,130,246,0.3);
        font-weight: 700;
        font-size: 1.1rem;
      }
      .expense-table-room {
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
      }
      .expense-meta {
        font-size: 0.75rem;
        color: #64748b;
      }
      .status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 80px;
        padding: 0.25rem 0.75rem;
        border-radius: 999px;
        font-size: 0.85rem;
        font-weight: 600;
        color: #fff;
      }
      .reports-page .manage-panel { margin-top: 1.4rem; margin-bottom: 1.4rem; background: #ffffff; border: none; box-shadow: 0 12px 24px rgba(15,23,42,0.08); color: #111827; }
      .reports-page .manage-panel:first-of-type { margin-top: 0.2rem; }

      .reports-page .manage-panel .section-header h2,
      .reports-page .manage-panel .section-header h3,
      .reports-page .manage-panel .section-header h4,
      .reports-page .manage-panel .section-header span,
      .reports-page .manage-panel .manage-table td,
      .reports-page .manage-panel .manage-table th {
        color: #111827 !important;
      }

      .reports-page .manage-panel .section-header p,
      .reports-page .manage-panel .expense-meta,
      .reports-page .manage-panel .datatable-info {
        color: #64748b !important;
      }

      .reports-page .manage-panel .manage-table th {
        background: #f8fafc !important;
      }

      .reports-page .manage-panel .manage-table td {
        background: #ffffff !important;
      }

      .reports-page .manage-panel .datatable-input,
      .reports-page .manage-panel .datatable-selector {
        background: #ffffff !important;
        color: #111827 !important;
        border: 1px solid #d1d5db !important;
      }

      .reports-page .manage-panel .datatable-wrapper.datatable-loading,
      .reports-page .manage-panel .datatable-wrapper.datatable-loading .datatable-container {
        opacity: 1 !important;
      }
      
      /* ตารางแสดงทุกคอลัมน์ และเลื่อนแนวนอนได้ */
      .report-table {
        overflow-x: auto;
        overflow-y: visible;
      }
      .report-table table {
        min-width: 100%;
        table-layout: auto;
      }
      .report-table th,
      .report-table td {
        white-space: nowrap;
        min-width: fit-content;
      }

      .report-table tbody tr.payment-preview-trigger {
        cursor: pointer !important;
        transition: background-color 0.15s ease;
      }

      .payment-preview-trigger,
      .payment-preview-trigger *,
      .report-table tbody tr.payment-preview-trigger td,
      .report-table tbody tr.payment-preview-trigger td *,
      .expense-row-card.payment-preview-trigger,
      .expense-row-card.payment-preview-trigger * {
        cursor: pointer !important;
      }

      .payment-preview-trigger:hover,
      .payment-preview-trigger:hover *,
      .payment-preview-trigger:focus,
      .payment-preview-trigger:focus *,
      .payment-preview-trigger:focus-visible,
      .payment-preview-trigger:focus-visible * {
        cursor: pointer !important;
      }

      .report-table tbody tr.payment-preview-trigger:hover td {
        background: rgba(37, 99, 235, 0.04) !important;
      }

      .report-table tbody tr.payment-preview-trigger:focus-visible {
        outline: none;
      }

      .report-table tbody tr.payment-preview-trigger:focus-visible td {
        background: rgba(37, 99, 235, 0.08) !important;
      }

      .table-open-hint {
        margin-top: 0.35rem;
        font-size: 0.75rem;
        color: #0369a1;
        font-weight: 600;
      }

      .payment-cell-wrap {
        font-size: 0.85rem;
        line-height: 1.55;
      }

      .table-click-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        margin-top: 0.35rem;
        padding: 0.18rem 0.45rem;
        border-radius: 999px;
        font-size: 0.74rem;
        font-weight: 700;
        color: #1d4ed8;
        background: rgba(37, 99, 235, 0.1);
      }

      .payment-compact {
        display: grid;
        gap: 0.22rem;
      }

      .payment-compact-row {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 0.5rem;
      }

      .payment-compact-label {
        color: #64748b;
        font-size: 0.79rem;
        font-weight: 600;
      }

      .payment-compact-value {
        color: #0f172a;
        font-weight: 700;
      }

      .payment-compact-value.warn {
        color: #ef4444;
      }
      
      /* Override animate-ui.css: แสดงทุกคอลัมน์ในหน้านี้ */
      #table-expenses.table--compact th,
      #table-expenses.table--compact td {
        display: table-cell !important;
      }
      
      /* คอลัมน์ที่ต้องการให้กว้างพอ */
      .report-table th:nth-child(5),
      .report-table td:nth-child(5),
      .report-table th:nth-child(6),
      .report-table td:nth-child(6) {
        min-width: 100px;
      }

      .expenses-view-toggle {
        padding: 0.6rem 0.95rem;
        border-radius: 10px;
          border: 1px solid #1d4ed8;
          background: linear-gradient(135deg, #2563eb, #1d4ed8);
          color: #ffffff !important;
        cursor: pointer;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        position: relative;
        z-index: 20;
        pointer-events: auto !important;
        user-select: none;
        -webkit-user-select: none;
      }

      .expenses-view-toggle:hover {
        background: linear-gradient(135deg, #1d4ed8, #1e40af);
        border-color: #1e40af;
      }

      .expenses-view-toggle[aria-pressed="true"] {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: #ffffff !important;
        border-color: #1d4ed8;
      }

      .expenses-view-toggle[aria-pressed="true"]:hover {
        background: linear-gradient(135deg, #1d4ed8, #1e40af);
        border-color: #1e40af;
      }

      .expenses-view-toggle svg {
        width: 16px;
        height: 16px;
          stroke: currentColor !important;
          color: currentColor !important;
        fill: none !important;
        pointer-events: none;
      }

      .expenses-view-toggle svg * {
          stroke: currentColor !important;
          color: currentColor !important;
        fill: none !important;
        pointer-events: none;
      }

      .expenses-view-toggle[aria-pressed="true"] svg,
      .expenses-view-toggle[aria-pressed="true"] svg * {
        stroke: #ffffff !important;
        color: #ffffff !important;
      }

      .expenses-view-toggle[aria-pressed="true"] span {
        color: #ffffff !important;
      }

      .expenses-view-toggle span {
        color: #ffffff !important;
        -webkit-text-fill-color: #ffffff !important;
        pointer-events: none;
      }

      .expenses-actions {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.55rem;
        position: relative;
        z-index: 12;
      }

      .expenses-filters-line {
        display: flex;
        gap: 0.75rem;
        align-items: center;
        flex-wrap: wrap;
        justify-content: flex-end;
      }

      .is-hidden {
        display: none !important;
      }

      .expenses-row-view {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 0.9rem;
      }

      .expense-row-card {
        background: #ffffff;
        border: none;
        border-radius: 14px;
        padding: 1rem 1.1rem;
        cursor: pointer;
        box-shadow: 0 6px 16px rgba(15,23,42,0.08);
        transition: box-shadow 0.16s ease, transform 0.16s ease;
      }

      .expense-row-card:hover {
        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.12);
        transform: translateY(-1px);
      }

      .expense-row-card:focus-visible {
        outline: none;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.18), 0 10px 22px rgba(15, 23, 42, 0.12);
      }

      .expense-row-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.7rem;
        margin-bottom: 0.55rem;
      }

      .expense-row-main {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
        color: #0f172a;
      }

      .expense-row-id {
        font-size: 1.02rem;
        font-weight: 700;
        color: #0f172a;
      }

      .expense-row-sub {
        color: #64748b;
        font-size: 0.9rem;
      }

      .expense-row-meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 0.45rem 0.8rem;
        color: #334155;
        font-size: 0.9rem;
      }

      .expense-row-meta-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.55rem;
        padding-bottom: 0.2rem;
      }

      .expense-row-meta-label {
        color: #64748b;
        font-weight: 500;
      }

      .expense-row-meta-value {
        color: #0f172a;
        font-weight: 700;
      }

      .expense-row-meta-value.total {
        font-size: 1rem;
      }

      html.light-theme .expenses-view-toggle {
        background: linear-gradient(135deg, #2563eb, #1d4ed8) !important;
        color: #ffffff !important;
        border-color: #1d4ed8 !important;
      }

      html.light-theme .expenses-view-toggle[aria-pressed="true"] {
        background: linear-gradient(135deg, #2563eb, #1d4ed8) !important;
        color: #ffffff !important;
        border-color: #1d4ed8 !important;
      }

      html.light-theme .expenses-view-toggle svg,
      html.light-theme .expenses-view-toggle svg * {
        stroke: currentColor !important;
        color: currentColor !important;
        fill: none !important;
      }

      html.light-theme .expenses-view-toggle[aria-pressed="true"] svg,
      html.light-theme .expenses-view-toggle[aria-pressed="true"] svg * {
        stroke: #ffffff !important;
        color: #ffffff !important;
        fill: none !important;
      }

      html.light-theme .expenses-view-toggle[aria-pressed="true"] span {
        color: #ffffff !important;
      }

      html.light-theme .expense-row-card {
        background: #ffffff !important;
        border: none !important;
      }

      html.light-theme .expense-row-main {
        color: #0f172a !important;
      }

      html.light-theme .expense-row-sub {
        color: #64748b !important;
      }

      html.light-theme .expense-row-meta {
        color: #334155 !important;
      }

      html.light-theme .report-table th {
        background: #f1f5f9 !important;
        color: #0f172a !important;
        border-color: #dbe3ee !important;
      }

      html.light-theme .report-table td {
        background: #ffffff !important;
        color: #1f2937 !important;
        border-color: #e5e7eb !important;
      }

      html.light-theme .report-table td .expense-meta,
      html.light-theme .report-table td .expense-table-room .expense-meta {
        color: #64748b !important;
      }

      html.light-theme .reports-page .section-header p,
      html.light-theme .reports-page .expenses-filters-line label {
        color: #64748b !important;
      }

      html.light-theme .reports-page .expenses-filters-line #sortSelect {
        background: #ffffff !important;
        color: #111827 !important;
        border-color: #d1d5db !important;
      }

      .payment-modal-overlay {
        position: fixed;
        inset: 0;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(2, 6, 23, 0.55);
        z-index: 9999;
        padding: 1rem;
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
      }

      .page-header-bar.modal-open-hidden {
        opacity: 0 !important;
        visibility: hidden !important;
        pointer-events: none !important;
      }

      .payment-modal-card {
        position: relative;
        width: min(620px, 92vw);
        max-height: min(84vh, 760px);
        overflow: visible;
        background: #ffffff;
        border-radius: 18px;
        border: 1px solid #dbe7e2;
        box-shadow: 0 24px 56px rgba(15, 23, 42, 0.28);
        padding: 0;
      }

      .payment-modal-head {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #ffffff;
        padding: 1.55rem 1.05rem 0.55rem;
        border-top-left-radius: 18px;
        border-top-right-radius: 18px;
      }

      .payment-modal-body {
        overflow-y: auto;
        overflow-x: hidden;
        max-height: calc(84vh - 135px);
        padding: 0.35rem 1.05rem 0.95rem;
      }

      .payment-modal-close {
        position: absolute;
        top: 0.7rem;
        right: 0.75rem;
        border: none;
        background: transparent;
        color: #64748b;
        font-size: 1.4rem;
        cursor: pointer;
      }

      .payment-modal-check {
        position: absolute;
        top: 0;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 3;
        width: 56px;
        height: 56px;
        margin: 0;
        border-radius: 999px;
        display: grid;
        place-items: center;
        background: linear-gradient(135deg, #22c55e, #16a34a);
        color: #fff;
        box-shadow: 0 10px 30px rgba(34, 197, 94, 0.35);
      }

      .payment-modal-check.pending {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        box-shadow: 0 10px 30px rgba(245, 158, 11, 0.35);
      }

      .payment-modal-check.partial {
        background: linear-gradient(135deg, #f59e0b, #ea580c);
        box-shadow: 0 10px 30px rgba(234, 88, 12, 0.35);
      }

      .payment-modal-check.unpaid {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        box-shadow: 0 10px 30px rgba(239, 68, 68, 0.35);
      }

      .payment-modal-check.overdue {
        background: linear-gradient(135deg, #dc2626, #991b1b);
        box-shadow: 0 10px 30px rgba(153, 27, 27, 0.4);
      }

      .payment-modal-check svg {
        width: 30px;
        height: 30px;
      }

      .payment-modal-title {
        margin: 0.35rem 0 0.55rem;
        font-size: 1.5rem;
        font-weight: 700;
        color: #1f2937;
        text-align: left;
      }

      .payment-modal-grid {
        display: grid;
        grid-template-columns: 1fr 1.6fr;
        gap: 0.75rem 1rem;
        align-items: start;
      }

      .payment-modal-summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.55rem;
        margin-bottom: 0.8rem;
      }

      .payment-modal-summary-item {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.55rem 0.6rem;
      }

      .payment-modal-summary-label {
        color: #64748b;
        font-size: 0.86rem;
        font-weight: 600;
        margin-bottom: 0.22rem;
      }

      .payment-modal-summary-value {
        color: #0f172a;
        font-size: 1.02rem;
        font-weight: 700;
        line-height: 1.25;
      }

      .payment-modal-section {
        margin-top: 0.65rem;
      }

      .payment-modal-section-title {
        margin: 0 0 0.5rem;
        color: #1e293b;
        font-size: 1rem;
        font-weight: 700;
      }

      .payment-modal-label {
        color: #334155;
        font-weight: 600;
        font-size: 1rem;
        line-height: 1.35;
      }

      .payment-modal-value {
        color: #1f2937;
        font-size: 1rem;
        line-height: 1.35;
      }

      .payment-modal-status {
        color: #22c55e;
        font-weight: 700;
      }

      .payment-modal-status.pending {
        color: #f59e0b;
      }

      .payment-modal-status.partial {
        color: #f59e0b;
      }

      .payment-modal-status.unpaid {
        color: #ef4444;
      }

      .payment-modal-status.overdue {
        color: #991b1b;
      }

      .payment-proof-thumb {
        margin-top: 0.3rem;
        width: 100%;
        max-width: 260px;
        max-height: 250px;
        object-fit: contain;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
      }

      body.payment-modal-open {
        overflow: hidden;
      }

      @media (max-width: 768px) {
        .payment-modal-title,
        .payment-modal-label,
        .payment-modal-value {
          font-size: 1rem;
        }

        .payment-modal-summary {
          grid-template-columns: 1fr;
          gap: 0.45rem;
        }

        .payment-modal-grid {
          grid-template-columns: 1fr 1.35fr;
          gap: 0.45rem 0.65rem;
        }

        .expenses-actions,
        .expenses-filters-line {
          width: 100%;
          align-items: flex-start;
          justify-content: flex-start;
        }
      }

      /* === WORLD-CLASS UX: Collection Progress Bar === */
      .collection-progress {
        margin-top: 1rem;
        padding: 1rem 1.25rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
      }
      .collection-progress-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem;
      }
      .collection-progress-label {
        font-size: 0.88rem;
        font-weight: 700;
        color: #334155;
      }
      .collection-progress-pct {
        font-size: 0.88rem;
        font-weight: 800;
        color: #2563eb;
      }
      .collection-bar {
        height: 10px;
        background: #e2e8f0;
        border-radius: 999px;
        overflow: hidden;
        position: relative;
      }
      .collection-bar-fill {
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, #22c55e, #16a34a);
        transition: width 0.8s cubic-bezier(0.4,0,0.2,1);
        min-width: 0;
      }
      .collection-bar-fill.low { background: linear-gradient(90deg, #ef4444, #dc2626); }
      .collection-bar-fill.mid { background: linear-gradient(90deg, #f59e0b, #d97706); }
      .collection-segments {
        display: flex;
        gap: 1rem;
        margin-top: 0.55rem;
        flex-wrap: wrap;
      }
      .collection-segment {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.8rem;
        color: #64748b;
        font-weight: 600;
      }
      .collection-segment-dot {
        width: 8px;
        height: 8px;
        border-radius: 999px;
        flex-shrink: 0;
      }

      /* === WORLD-CLASS UX: Status Filter Tabs === */
      .expense-controls-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        flex-wrap: wrap;
        margin: 0 0 1rem;
      }
      .expense-filter-tabs {
        display: flex;
        gap: 0.4rem;
        padding: 0;
        margin: 0;
        flex-wrap: nowrap;
        flex: 1;
        min-width: 0;
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
        scrollbar-color: rgba(148,163,184,0.4) transparent;
        cursor: grab;
        padding-bottom: 2px;
      }
      .expense-filter-tabs::-webkit-scrollbar { height: 4px; }
      .expense-filter-tabs::-webkit-scrollbar-thumb { background: rgba(148,163,184,0.45); border-radius: 999px; }
      .expense-filter-tabs::-webkit-scrollbar-track { background: transparent; }
      .expense-filter-tab {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.5rem 1rem;
        border-radius: 999px;
        border: 1.5px solid #e2e8f0;
        background: #ffffff;
        color: #64748b;
        font-size: 0.88rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.18s ease;
        user-select: none;
        white-space: nowrap;
      }
      .expense-filter-tab:hover {
        border-color: #cbd5e1;
        background: #f1f5f9;
        color: #334155;
      }
      .expense-filter-tab.active {
        background: #2563eb;
        border-color: #2563eb;
        color: #ffffff;
        box-shadow: 0 2px 8px rgba(37,99,235,0.25);
      }
      .expense-filter-tab .tab-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        padding: 0 5px;
        border-radius: 999px;
        background: rgba(0,0,0,0.08);
        font-size: 0.75rem;
        font-weight: 700;
        line-height: 1;
      }
      .expense-filter-tab.active .tab-count {
        background: rgba(255,255,255,0.25);
        color: #ffffff;
      }
      .expense-filter-tab[data-status="0"] .tab-count { color: #dc2626; }
      .expense-filter-tab[data-status="2"] .tab-count { color: #d97706; }
      .expense-filter-tab[data-status="3"] .tab-count { color: #ea580c; }
      .expense-filter-tab[data-status="4"] .tab-count { color: #b91c1c; }
      .expense-filter-tab[data-status="1"] .tab-count { color: #16a34a; }
      .expense-filter-tab.active .tab-count { color: #ffffff; }

      /* === WORLD-CLASS UX: Unified Toolbar === */
      .expense-toolbar {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        flex-wrap: wrap;
        margin-bottom: 0;
        flex-shrink: 0;
      }
      .expense-toolbar-search {
        position: relative;
        flex: 1;
        min-width: 180px;
        max-width: 320px;
      }
      .expense-toolbar-search input {
        width: 100%;
        padding: 0.55rem 0.75rem 0.55rem 2.4rem;
        border-radius: 10px;
        border: 1.5px solid #e2e8f0;
        background: #ffffff;
        color: #1f2937;
        font-size: 0.9rem;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
      }
      .expense-toolbar-search input:focus {
        outline: none;
        border-color: #60a5fa;
        box-shadow: 0 0 0 3px rgba(96,165,250,0.15);
      }
      .expense-toolbar-search input::placeholder { color: #94a3b8; }
      .expense-toolbar-search .search-icon {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        width: 16px;
        height: 16px;
        color: #94a3b8;
        pointer-events: none;
      }
      .expense-toolbar select {
        padding: 0.55rem 0.75rem;
        border-radius: 10px;
        border: 1.5px solid #e2e8f0;
        background: #ffffff;
        color: #1f2937;
        font-size: 0.9rem;
        cursor: pointer;
        font-weight: 500;
      }
      .expense-toolbar select:focus {
        outline: none;
        border-color: #60a5fa;
        box-shadow: 0 0 0 3px rgba(96,165,250,0.15);
      }
      .expense-toolbar .toolbar-divider {
        width: 1px;
        height: 28px;
        background: #e2e8f0;
        flex-shrink: 0;
      }

      /* === WORLD-CLASS UX: Card Progress Bar === */
      .expense-card-progress {
        margin-top: 0.6rem;
        padding-top: 0.5rem;
        border-top: 1px solid #f1f5f9;
      }
      .expense-card-progress-bar {
        height: 6px;
        background: #e2e8f0;
        border-radius: 999px;
        overflow: hidden;
      }
      .expense-card-progress-fill {
        height: 100%;
        border-radius: 999px;
        transition: width 0.6s ease;
      }
      .expense-card-progress-fill.full { background: #22c55e; }
      .expense-card-progress-fill.partial { background: #f59e0b; }
      .expense-card-progress-fill.none { background: #ef4444; width: 0 !important; }
      .expense-card-progress-text {
        display: flex;
        justify-content: space-between;
        margin-top: 0.3rem;
        font-size: 0.78rem;
        color: #94a3b8;
        font-weight: 600;
      }

      /* === Stats card: pending === */
      .expense-stat-card.is-pending .status-dot { background: #f59e0b; }
      .expense-stat-card.is-pending .stat-value,
      .expense-stat-card.is-pending .stat-money { color: #fbbf24; }
      html.light-theme .expense-stat-card.is-pending .stat-value,
      html.light-theme .expense-stat-card.is-pending .stat-money { color: #d97706 !important; }

      /* Info chip */
      .expense-info-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.4rem 0.85rem;
        border-radius: 999px;
        background: rgba(56,189,248,0.1);
        border: 1px solid rgba(56,189,248,0.25);
        color: #0369a1;
        font-size: 0.82rem;
        font-weight: 600;
        margin-top: 0.55rem;
      }
      .expense-info-chip svg {
        width: 14px; height: 14px;
        flex-shrink: 0;
      }

      /* Empty state */
      .expense-empty-state {
        text-align: center;
        padding: 3rem 1.5rem;
        color: #94a3b8;
      }
      .expense-empty-state svg {
        width: 56px;
        height: 56px;
        color: #cbd5e1;
        margin-bottom: 0.75rem;
      }
      .expense-empty-state p {
        font-size: 1rem;
        font-weight: 600;
        color: #64748b;
        margin: 0;
      }
      .expense-empty-state small {
        font-size: 0.85rem;
        color: #94a3b8;
      }

      /* Responsive: filter tabs */
      @media (max-width: 768px) {
        .expense-controls-row {
          flex-direction: column;
          align-items: stretch;
        }
        .expense-filter-tabs {
          gap: 0.3rem;
        }
        .expense-filter-tab {
          padding: 0.4rem 0.75rem;
          font-size: 0.82rem;
        }
        .expense-toolbar {
          flex-direction: column;
          align-items: stretch;
        }
        .expense-toolbar-search {
          max-width: none;
        }
        .expense-toolbar .toolbar-divider {
          display: none;
        }
        .collection-segments {
          gap: 0.6rem;
        }
      }
    </style>
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/futuristic-bright.css" />
    <style>
      /* Bright clean visual refresh for this page only */
      body.reports-page {
        font-family: "IBM Plex Sans Thai", "Sarabun", "Prompt", "Noto Sans Thai", sans-serif;
        background:
          radial-gradient(circle at 12% 10%, rgba(37, 99, 235, 0.08), transparent 38%),
          radial-gradient(circle at 88% 0%, rgba(14, 165, 233, 0.08), transparent 30%),
          linear-gradient(180deg, #f7f9fc 0%, #eef3f9 100%) !important;
        color: #0f172a;
      }

      .reports-page {
        --surface: #ffffff;
        --surface-muted: #f7f9fc;
        --surface-alt: #f1f5f9;
        --border: #e2e8f0;
        --border-strong: #d2deef;
        --text: #0f172a;
        --text-muted: #5b6b83;
        --accent: #1d4ed8;
        --accent-soft: #e8efff;
        --success: #16a34a;
        --warning: #d97706;
        --danger: #dc2626;
      }

      .reports-page .app-main {
        padding-bottom: 2rem;
      }

      .reports-page .manage-panel {
        border: 1px solid var(--border) !important;
        border-radius: 18px !important;
        background: var(--surface) !important;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08), 0 2px 8px rgba(15, 23, 42, 0.04) !important;
      }

      .reports-page .section-header h1,
      .reports-page .section-header h2,
      .reports-page .section-header h3,
      .reports-page .section-header h4 {
        color: var(--text) !important;
        letter-spacing: 0.01em;
      }

      .reports-page .section-header p,
      .reports-page .expense-meta,
      .reports-page .datatable-info {
        color: var(--text-muted) !important;
      }

      .reports-page .expense-info-chip {
        background: var(--accent-soft);
        border: 1px solid #c7dcff;
        color: #17408b;
      }

      .reports-page .meter-alert-banner {
        border-color: #fecaca;
        background: #fff5f5;
        color: #b91c1c;
      }

      .reports-page .expense-stats {
        gap: 0.9rem;
      }

      .reports-page .expense-stat-card {
        background: var(--surface) !important;
        border: 1px solid var(--border) !important;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06) !important;
        color: var(--text) !important;
        border-radius: 16px;
        position: relative;
        overflow: hidden;
      }

      .reports-page .expense-stat-card h3 {
        color: #334155 !important;
      }

      .reports-page .expense-stat-card .stat-value {
        font-size: 1.85rem;
        letter-spacing: 0.01em;
      }

      .reports-page .expense-stat-card .stat-money {
        font-size: 1.15rem;
      }

      .reports-page .expense-stat-card.is-unpaid .stat-value,
      .reports-page .expense-stat-card.is-unpaid .stat-money {
        color: var(--danger) !important;
      }

      .reports-page .expense-stat-card.is-paid .stat-value,
      .reports-page .expense-stat-card.is-paid .stat-money {
        color: var(--success) !important;
      }

      .reports-page .expense-stat-card.is-pending .stat-value,
      .reports-page .expense-stat-card.is-pending .stat-money {
        color: var(--warning) !important;
      }

      .reports-page .expense-stat-card.is-overdue .stat-value,
      .reports-page .expense-stat-card.is-overdue .stat-money {
        color: #b91c1c !important;
      }

      .reports-page .expense-stat-card.is-total .stat-value,
      .reports-page .expense-stat-card.is-total .stat-money {
        color: var(--accent) !important;
      }

      .reports-page .collection-progress {
        border: 1px solid var(--border);
        border-radius: 16px;
        background: linear-gradient(180deg, #ffffff 0%, #f7faff 100%);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.75);
      }

      .reports-page .collection-progress-label {
        color: #334155;
        font-weight: 700;
      }

      .reports-page .collection-progress-pct {
        color: var(--accent);
        font-weight: 800;
      }

      .reports-page .collection-bar {
        background: #e8effa;
      }

      .reports-page .collection-bar-fill {
        box-shadow: 0 0 10px rgba(34, 197, 94, 0.25);
      }

      .reports-page .expense-controls-row {
        border: 1px solid var(--border);
        background: var(--surface-muted);
        border-radius: 14px;
        padding: 0.7rem;
      }

      .reports-page .expense-filter-tab {
        background: var(--surface);
        border: 1px solid var(--border);
        color: #334155;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        transition: all 0.2s ease;
      }

      .reports-page .expense-filter-tab:hover {
        transform: translateY(-1px);
        border-color: #bcd3ff;
        box-shadow: 0 6px 14px rgba(37, 99, 235, 0.1);
      }

      .reports-page .expense-filter-tab.active {
        background: linear-gradient(135deg, #e8efff 0%, #dbeafe 100%);
        border-color: #b6cbff;
        color: var(--accent);
        box-shadow: 0 8px 16px rgba(37, 99, 235, 0.15);
      }

      .reports-page .expense-toolbar select,
      .reports-page .datatable-input,
      .reports-page .datatable-selector {
        border: 1px solid var(--border-strong) !important;
        background: #ffffff !important;
        color: var(--text) !important;
        border-radius: 10px;
      }

      .reports-page .expense-toolbar select:focus,
      .reports-page .datatable-input:focus,
      .reports-page .datatable-selector:focus {
        border-color: #93c5fd !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        outline: none;
      }

      .reports-page .report-table {
        border: 1px solid var(--border);
        border-radius: 14px;
        background: var(--surface);
      }

      .reports-page #table-expenses thead th {
        background: var(--surface-alt) !important;
        color: #1e3a8a !important;
        border-bottom: 1px solid var(--border);
        font-weight: 700;
      }

      .reports-page #table-expenses tbody tr:nth-child(even) td {
        background: #f9fbff !important;
      }

      .reports-page #table-expenses tbody td {
        color: var(--text) !important;
        border-bottom: 1px solid #edf2fb;
        padding-top: 0.85rem;
        padding-bottom: 0.85rem;
      }

      .reports-page .report-table tbody tr.payment-preview-trigger:hover td {
        background: #eef5ff !important;
      }

      .reports-page .report-table tbody tr.payment-preview-trigger:focus-visible td {
        background: #e6f0ff !important;
      }

      .reports-page .status-badge {
        min-width: 84px;
        font-weight: 700;
        letter-spacing: 0.01em;
        box-shadow: 0 6px 12px rgba(15, 23, 42, 0.12);
      }

      .reports-page .payment-compact-label {
        color: var(--text-muted);
      }

      .reports-page .payment-compact-value {
        color: var(--text);
      }

      .reports-page .expenses-row-view {
        gap: 0.8rem;
      }

      .reports-page .expense-row-card {
        border: 1px solid var(--border);
        background: var(--surface);
        box-shadow: 0 8px 16px rgba(15, 23, 42, 0.06);
      }

      .reports-page .expense-row-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 14px 24px rgba(30, 64, 175, 0.12);
      }

      .reports-page .expense-row-meta-value.total {
        color: var(--accent) !important;
      }

      .reports-page .datatable-pagination a {
        border-radius: 10px;
        border: 1px solid var(--border-strong);
        color: #334155;
      }

      .reports-page .datatable-pagination .datatable-active a {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        border-color: #1d4ed8;
        color: #ffffff;
        box-shadow: 0 8px 14px rgba(37, 99, 235, 0.25);
      }

      .reports-page .expenses-view-toggle {
        background: linear-gradient(135deg, #2563eb, #1d4ed8) !important;
        border-color: #1d4ed8 !important;
        color: #ffffff !important;
      }

      @media (max-width: 900px) {
        .reports-page .manage-panel {
          border-radius: 14px !important;
        }

        .reports-page .expense-stat-card {
          padding: 1rem;
        }

        .reports-page #table-expenses tbody td {
          padding-top: 0.72rem;
          padding-bottom: 0.72rem;
        }

        .reports-page .expense-controls-row {
          padding: 0.55rem;
        }
      }
    </style>
  </head>
  <body class="reports-page">
    <div class="app-shell">
      <?php include __DIR__ . '/../includes/sidebar.php'; ?>
      <main class="app-main">
        <div>
          <?php 
            $pageTitle = 'จัดการค่าใช้จ่าย';
            include __DIR__ . '/../includes/page_header.php'; 
          ?>

          <?php if (isset($_SESSION['success'])): ?>
            <script>
              document.addEventListener('DOMContentLoaded', () => {
                showSuccessToast('<?php echo addslashes($_SESSION['success']); ?>');
              });
            </script>
            <?php unset($_SESSION['success']); ?>
          <?php endif; ?>
          <?php if (isset($_SESSION['error'])): ?>
            <script>
              document.addEventListener('DOMContentLoaded', () => {
                showErrorToast('<?php echo addslashes($_SESSION['error']); ?>');
              });
            </script>
            <?php unset($_SESSION['error']); ?>
          <?php endif; ?>

          <section class="manage-panel">
            <div class="section-header">
              <div>
                <p style="color:#94a3b8;margin-top:0.2rem;">สรุปยอดค่าใช้จ่ายและสถานะการชำระเงิน</p>
                <div class="expense-info-chip">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                  ระบบสร้างรายการค่าใช้จ่ายอัตโนมัติทุกเดือน ตามสัญญาที่ใช้งาน
                </div>
              </div>
            </div>

            <?php if (!empty($meterMissingRooms)): ?>
            <div class="meter-alert-banner">
              <div class="meter-alert-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                ⚠ ยังไม่ได้จดมิเตอร์ <?php echo count($meterMissingRooms); ?> ห้อง — ยอดค่าน้ำ/ไฟยังเป็น 0 กรุณาจดมิเตอร์ในผู้เช่าที่จัดการ ก่อนบิลจะคำนวณถูกต้อง
              </div>
              <ul class="meter-alert-list">
                <?php foreach ($meterMissingRooms as $mr): ?>
                  <li>💧⚡ ห้อง <?php echo htmlspecialchars($mr['room_number'], ENT_QUOTES, 'UTF-8'); ?> • <?php echo htmlspecialchars($mr['tnt_name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($mr['month'], ENT_QUOTES, 'UTF-8'); ?>)</li>
                <?php endforeach; ?>
              </ul>
            </div>
            <?php endif; ?>

            <div class="expense-stats">
              <div class="expense-stat-card is-unpaid">
                <div class="stat-head">
                  <span class="status-dot" aria-hidden="true"></span>
                  <h3>รอชำระ</h3>
                </div>
                <div class="stat-value"><?php echo number_format($stats['unpaid']); ?> <span style="font-size:0.85rem;font-weight:500;opacity:0.7;">รายการ</span></div>
                <div class="stat-money">฿<?php echo number_format($stats['total_unpaid']); ?></div>
              </div>
              <?php if ($stats['overdue'] > 0): ?>
              <div class="expense-stat-card is-overdue">
                <div class="stat-head">
                  <span class="status-dot" aria-hidden="true"></span>
                  <h3>ค้างชำระ</h3>
                </div>
                <div class="stat-value"><?php echo number_format($stats['overdue']); ?> <span style="font-size:0.85rem;font-weight:500;opacity:0.7;">รายการ</span></div>
                <div class="stat-money">฿<?php echo number_format($stats['total_overdue']); ?></div>
              </div>
              <?php endif; ?>
              <?php if ($pendingPartialCount > 0): ?>
              <div class="expense-stat-card is-pending">
                <div class="stat-head">
                  <span class="status-dot" aria-hidden="true"></span>
                  <h3>รอดำเนินการ</h3>
                </div>
                <div class="stat-value"><?php echo number_format($pendingPartialCount); ?> <span style="font-size:0.85rem;font-weight:500;opacity:0.7;">รายการ</span></div>
                <div class="stat-money">฿<?php echo number_format($pendingPartialTotal); ?></div>
              </div>
              <?php endif; ?>
              <div class="expense-stat-card is-paid">
                <div class="stat-head">
                  <span class="status-dot" aria-hidden="true"></span>
                  <h3>ชำระแล้ว</h3>
                </div>
                <div class="stat-value"><?php echo number_format($stats['paid']); ?> <span style="font-size:0.85rem;font-weight:500;opacity:0.7;">รายการ</span></div>
                <div class="stat-money">฿<?php echo number_format($stats['total_paid']); ?></div>
              </div>
              <div class="expense-stat-card is-total">
                <div class="stat-head">
                  <span class="status-dot" aria-hidden="true"></span>
                  <h3>รวมทั้งหมด</h3>
                </div>
                <div class="stat-value"><?php echo number_format($totalExpenseCount); ?> <span style="font-size:0.85rem;font-weight:500;opacity:0.7;">รายการ</span></div>
                <div class="stat-money">฿<?php echo number_format($totalAll); ?></div>
              </div>
            </div>

            <!-- Collection Progress Bar -->
            <div class="collection-progress">
              <div class="collection-progress-header">
                <span class="collection-progress-label">อัตราการเก็บเงินได้</span>
                <span class="collection-progress-pct"><?php echo $collectionPct; ?>%</span>
              </div>
              <div class="collection-bar">
                <div class="collection-bar-fill<?php echo $collectionPct < 30 ? ' low' : ($collectionPct < 70 ? ' mid' : ''); ?>" style="width:<?php echo $collectionPct; ?>%;"></div>
              </div>
              <div class="collection-segments">
                <div class="collection-segment"><span class="collection-segment-dot" style="background:#22c55e;"></span> ชำระแล้ว ฿<?php echo number_format($stats['total_paid']); ?></div>
                <?php if ($pendingPartialTotal > 0): ?>
                <div class="collection-segment"><span class="collection-segment-dot" style="background:#f59e0b;"></span> รอดำเนินการ ฿<?php echo number_format($pendingPartialTotal); ?></div>
                <?php endif; ?>
                <div class="collection-segment"><span class="collection-segment-dot" style="background:#ef4444;"></span> รอชำระ ฿<?php echo number_format($stats['total_unpaid']); ?></div>
                <?php if ($stats['total_overdue'] > 0): ?>
                <div class="collection-segment"><span class="collection-segment-dot" style="background:#dc2626;"></span> ค้างชำระ ฿<?php echo number_format($stats['total_overdue']); ?></div>
                <?php endif; ?>
              </div>
            </div>
          </section>



          <section class="manage-panel">
            <div class="section-header" style="margin-bottom:0.75rem;">
              <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;width:100%;">
                <div>
                  <h1 style="margin:0;">รายการค่าใช้จ่าย</h1>
                  <p style="color:#94a3b8;margin:0.15rem 0 0;font-size:0.88rem;">กดที่รายการเพื่อดูรายละเอียดและหลักฐานการชำระ</p>
                </div>
                <button type="button" id="expensesViewToggle" class="expenses-view-toggle" onclick="toggleExpensesView()">
                  <svg id="expensesViewToggleIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                  <span id="expensesViewToggleText">ตาราง</span>
                </button>
              </div>
            </div>

            <!-- Controls Row: filter tabs + toolbar -->
            <div class="expense-controls-row">
            <div class="expense-filter-tabs" id="expenseFilterTabs">
              <button type="button" class="expense-filter-tab<?php echo $selectedStatusFilter === 'all' ? ' active' : ''; ?>" data-status="all">
                ทั้งหมด <span class="tab-count"><?php echo $totalExpenseCount; ?></span>
              </button>
              <?php if ($stats['unpaid'] > 0): ?>
              <button type="button" class="expense-filter-tab<?php echo $selectedStatusFilter === '0' ? ' active' : ''; ?>" data-status="0">
                รอชำระ <span class="tab-count"><?php echo $stats['unpaid']; ?></span>
              </button>
              <?php endif; ?>
              <?php if ($stats['pending'] > 0): ?>
              <button type="button" class="expense-filter-tab<?php echo $selectedStatusFilter === '2' ? ' active' : ''; ?>" data-status="2">
                รอตรวจสอบ <span class="tab-count"><?php echo $stats['pending']; ?></span>
              </button>
              <?php endif; ?>
              <?php if ($stats['partial'] > 0): ?>
              <button type="button" class="expense-filter-tab<?php echo $selectedStatusFilter === '3' ? ' active' : ''; ?>" data-status="3">
                ชำระยังไม่ครบ <span class="tab-count"><?php echo $stats['partial']; ?></span>
              </button>
              <?php endif; ?>
              <?php if ($stats['overdue'] > 0): ?>
              <button type="button" class="expense-filter-tab<?php echo $selectedStatusFilter === '4' ? ' active' : ''; ?>" data-status="4">
                ค้างชำระ <span class="tab-count"><?php echo $stats['overdue']; ?></span>
              </button>
              <?php endif; ?>
              <?php if ($stats['paid'] > 0): ?>
              <button type="button" class="expense-filter-tab<?php echo $selectedStatusFilter === '1' ? ' active' : ''; ?>" data-status="1">
                ชำระแล้ว <span class="tab-count"><?php echo $stats['paid']; ?></span>
              </button>
              <?php endif; ?>
            </div>

            <!-- Unified Toolbar -->
            <div class="expense-toolbar">
              <select id="monthFilter" class="expense-toolbar-month">
                <?php if (empty($availableMonths)): ?>
                  <option value="">ไม่มีข้อมูลเดือน</option>
                <?php endif; ?>
                <?php
                  $thaiMonths = [
                    '01' => 'ม.ค.', '02' => 'ก.พ.', '03' => 'มี.ค.', '04' => 'เม.ย.',
                    '05' => 'พ.ค.', '06' => 'มิ.ย.', '07' => 'ก.ค.', '08' => 'ส.ค.',
                    '09' => 'ก.ย.', '10' => 'ต.ค.', '11' => 'พ.ย.', '12' => 'ธ.ค.'
                  ];
                  foreach ($availableMonths as $monthKey) {
                    $month = substr((string)$monthKey, 5, 2);
                    $year = substr((string)$monthKey, 0, 4);
                    $yearThai = (int)$year + 543;
                    $monthText = ($thaiMonths[$month] ?? $month) . ' ' . $yearThai;
                    $selectedAttr = ((string)$selectedMonth === (string)$monthKey) ? ' selected' : '';
                    echo '<option value="' . htmlspecialchars((string)$monthKey, ENT_QUOTES, 'UTF-8') . '"' . $selectedAttr . '>' . htmlspecialchars($monthText, ENT_QUOTES, 'UTF-8') . '</option>';
                  }
                ?>
              </select>
              <select id="sortSelect" onchange="changeSortBy(this.value)">
                <option value="newest" <?php echo ($sortBy === 'newest' ? 'selected' : ''); ?>>ล่าสุด</option>
                <option value="oldest" <?php echo ($sortBy === 'oldest' ? 'selected' : ''); ?>>เก่าสุด</option>
                <option value="room_number" <?php echo ($sortBy === 'room_number' ? 'selected' : ''); ?>>ห้อง</option>
              </select>
            </div>
            </div><!-- /.expense-controls-row -->
            <div id="expensesTableWrap" class="report-table is-hidden">
              <table class="table--compact" id="table-expenses">
                <thead>
                  <tr>
                    <th>รหัส</th>
                    <th>ห้อง/ผู้เช่า</th>
                    <th>เดือน/ปี</th>
                    <th style="text-align:right;">ค่าใช้จ่าย</th>
                    <th style="text-align:right;">ยอดรวม</th>
                    <th>สถานะ</th>
                    <th>การชำระเงิน</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($displayExpenses)): ?>
                    <tr>
                      <td colspan="7" style="text-align:center;padding:2rem;color:#64748b;">ยังไม่มีข้อมูลค่าใช้จ่าย</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($displayExpenses as $exp): ?>
                      <?php $rowStatus = $buildExpenseStatus($exp); ?>
                      <tr class="payment-preview-trigger"
                        role="button"
                        tabindex="0"
                        aria-label="เปิดรายละเอียดการชำระเงินรายการ #<?php echo str_pad((string)$exp['exp_id'], 4, '0', STR_PAD_LEFT); ?> ห้อง <?php echo htmlspecialchars((string)($exp['room_number'] ?? '-')); ?>"
                        data-expense-id="<?php echo (int)$exp['exp_id']; ?>"
                        data-status="<?php echo htmlspecialchars((string)$rowStatus['status'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-room="<?php echo htmlspecialchars((string)($exp['room_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-tenant="<?php echo htmlspecialchars((string)($exp['tnt_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-tenant-name="<?php echo htmlspecialchars((string)($exp['tnt_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>"
                        data-status-text="<?php echo htmlspecialchars((string)$rowStatus['statusText'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-charges-paid="<?php echo (int)$rowStatus['chargesPaid']; ?>"
                        data-charges-remain="<?php echo (int)$rowStatus['chargesRemain']; ?>">
                        <td>
                          #<?php echo str_pad((string)$exp['exp_id'], 4, '0', STR_PAD_LEFT); ?>
                        </td>
                        <td>
                          <div class="expense-table-room">
                            <span>ห้อง <?php echo htmlspecialchars((string)($exp['room_number'] ?? '-')); ?></span>
                            <span class="expense-meta"><?php echo htmlspecialchars($exp['tnt_name'] ?? '-'); ?></span>
                            <?php if (!empty($meterMissingByExp[(int)$exp['exp_id']])): ?>
                              <span class="meter-missing-badge">⚠ ยังไม่จดมิเตอร์</span>
                            <?php endif; ?>
                          </div>
                        </td>
                        <td><?php echo $exp['exp_month'] ? thaiMonthYear($exp['exp_month']) : '-'; ?></td>
                        <td style="text-align:right;">
                          <?php
                            $roomPrice = (int)($exp['room_price'] ?? 0);
                            $elecUnits = (int)($exp['exp_elec_unit'] ?? 0);
                            $elecTotal = (int)($exp['exp_elec_chg'] ?? 0);
                            $waterUnits = (int)($exp['exp_water_unit'] ?? 0);
                            $waterTotal = (int)($exp['exp_water'] ?? 0);
                          ?>
                          <div style="color:#111827;font-weight:700;">ห้อง ฿<?php echo number_format($roomPrice); ?></div>
                          <div class="expense-meta">น้ำ ฿<?php echo number_format($waterTotal); ?> • ไฟ ฿<?php echo number_format($elecTotal); ?></div>
                          <div class="expense-meta"><?php echo number_format($waterUnits); ?> หน่วยน้ำ • <?php echo number_format($elecUnits); ?> หน่วยไฟ</div>
                        </td>
                        <td style="text-align:right;">
                          <strong style="color:<?php echo $rowStatus['totalColor']; ?>;">฿<?php echo number_format((int)($exp['exp_total'] ?? 0)); ?></strong>
                        </td>
                        <td>
                          <span class="status-badge" style="background: <?php echo $rowStatus['statusColor']; ?>;">
                            <?php echo $rowStatus['statusText']; ?>
                          </span>
                        </td>
                        <td class="crud-column">
                          <?php
                            $chargesPaid = (int)$rowStatus['chargesPaid'];
                            $chargesRemain = (int)$rowStatus['chargesRemain'];
                          ?>
                          <div class="payment-cell-wrap">
                            <div class="payment-compact">
                              <?php if ($chargesPaid > 0): ?>
                              <div class="payment-compact-row">
                                <span class="payment-compact-label">ชำระแล้ว</span>
                                <span class="payment-compact-value">฿<?php echo number_format($chargesPaid); ?></span>
                              </div>
                              <?php endif; ?>
                              <?php if ($chargesRemain > 0): ?>
                              <div class="payment-compact-row">
                                <span class="payment-compact-label">ค้างชำระ</span>
                                <span class="payment-compact-value<?php echo $chargesRemain > 0 ? ' warn' : ''; ?>">฿<?php echo number_format($chargesRemain); ?></span>
                              </div>
                              <?php endif; ?>
                            </div>

                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

            <div id="expensesRowView" class="expenses-row-view">
              <?php if (empty($displayExpenses)): ?>
                <div class="expense-row-card" style="text-align:center;color:#64748b;grid-column:1/-1;width:100%;">ยังไม่มีข้อมูลค่าใช้จ่าย</div>
              <?php else: ?>
                <?php foreach ($displayExpenses as $exp): ?>
                  <?php
                    $rowStatus = $buildExpenseStatus($exp);
                  ?>
                     <div class="expense-row-card payment-preview-trigger"
                       role="button"
                       tabindex="0"
                       aria-label="ดูรายละเอียดค่าใช้จ่าย #<?php echo str_pad((string)$exp['exp_id'], 4, '0', STR_PAD_LEFT); ?> ห้อง <?php echo htmlspecialchars((string)($exp['room_number'] ?? '-')); ?>"
                       data-month="<?php echo $exp['exp_month'] ? date('Y-m', strtotime((string)$exp['exp_month'])) : ''; ?>"
                       data-expense-id="<?php echo (int)$exp['exp_id']; ?>"
                       data-status="<?php echo htmlspecialchars((string)$rowStatus['status'], ENT_QUOTES, 'UTF-8'); ?>"
                       data-room="<?php echo htmlspecialchars((string)($exp['room_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                       data-tenant="<?php echo htmlspecialchars((string)($exp['tnt_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                       data-tenant-name="<?php echo htmlspecialchars((string)($exp['tnt_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>"
                       data-status-text="<?php echo htmlspecialchars((string)$rowStatus['statusText'], ENT_QUOTES, 'UTF-8'); ?>"
                       data-charges-paid="<?php echo (int)$rowStatus['chargesPaid']; ?>"
                       data-charges-remain="<?php echo (int)$rowStatus['chargesRemain']; ?>">
                    <div class="expense-row-top">
                      <div class="expense-row-main">
                        <strong class="expense-row-id">#<?php echo str_pad((string)$exp['exp_id'], 4, '0', STR_PAD_LEFT); ?></strong>
                        <span class="expense-row-sub">ห้อง <?php echo htmlspecialchars((string)($exp['room_number'] ?? '-')); ?> • <?php echo htmlspecialchars($exp['tnt_name'] ?? '-'); ?></span>
                        <?php if (!empty($meterMissingByExp[(int)$exp['exp_id']])): ?>
                          <span class="meter-missing-badge">⚠ ยังไม่จดมิเตอร์</span>
                        <?php endif; ?>
                      </div>
                      <span class="status-badge" style="background: <?php echo htmlspecialchars($rowStatus['statusColor']); ?>;"><?php echo htmlspecialchars($rowStatus['statusText']); ?></span>
                    </div>
                    <div class="expense-row-meta">
                      <div class="expense-row-meta-item">
                        <span class="expense-row-meta-label">เดือน/ปี</span>
                        <span class="expense-row-meta-value"><?php echo $exp['exp_month'] ? date('m/Y', strtotime((string)$exp['exp_month'])) : '-'; ?></span>
                      </div>
                      <div class="expense-row-meta-item">
                        <span class="expense-row-meta-label">ค่าห้อง</span>
                        <span class="expense-row-meta-value">฿<?php echo number_format((int)($exp['room_price'] ?? 0)); ?></span>
                      </div>
                      <div class="expense-row-meta-item">
                        <span class="expense-row-meta-label">ค่าไฟ</span>
                        <span class="expense-row-meta-value">฿<?php echo number_format((int)($exp['exp_elec_chg'] ?? 0)); ?></span>
                      </div>
                      <div class="expense-row-meta-item">
                        <span class="expense-row-meta-label">ค่าน้ำ</span>
                        <span class="expense-row-meta-value">฿<?php echo number_format((int)($exp['exp_water'] ?? 0)); ?></span>
                      </div>
                      <div class="expense-row-meta-item">
                        <span class="expense-row-meta-label">ยอดรวม</span>
                        <span class="expense-row-meta-value total" style="color:<?php echo htmlspecialchars($rowStatus['totalColor']); ?>;">฿<?php echo number_format((int)($exp['exp_total'] ?? 0)); ?></span>
                      </div>
                    </div>
                    <?php
                      $cardTotal = (int)($exp['exp_total'] ?? 0);
                      $cardPaid = (int)$rowStatus['chargesPaid'];
                      $cardPct = $cardTotal > 0 ? min(100, round(($cardPaid / $cardTotal) * 100)) : 0;
                      $cardFillClass = $cardPct >= 100 ? 'full' : ($cardPct > 0 ? 'partial' : 'none');
                    ?>
                    <div class="expense-card-progress">
                      <div class="expense-card-progress-bar"><div class="expense-card-progress-fill <?php echo $cardFillClass; ?>" style="width:<?php echo $cardPct; ?>%;"></div></div>
                      <div class="expense-card-progress-text">
                        <span>ชำระ ฿<?php echo number_format($cardPaid); ?></span>
                        <span>คงเหลือ ฿<?php echo number_format(max(0, $cardTotal - $cardPaid)); ?></span>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </section>
        </div>
      </main>
    </div>

    <div id="paymentProofModal" class="payment-modal-overlay" aria-hidden="true">
      <div class="payment-modal-card" role="dialog" aria-modal="true" aria-label="รายละเอียดการชำระเงิน">
        <button id="closePaymentProofModal" class="payment-modal-close" type="button" aria-label="ปิด">✕</button>
        <div id="paymentProofContent"></div>
      </div>
    </div>

    <script src="/dormitory_management/Public/Assets/Javascript/toast-notification.js"></script>
    <script src="/dormitory_management/Public/Assets/Javascript/confirm-modal.js"></script>
    <script src="/dormitory_management/Public/Assets/Javascript/animate-ui.js"></script>
    <script src="/dormitory_management/Public/Assets/Javascript/main.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4" defer></script>
    <script>
      const expensesToggleIcons = {
          list: '<circle cx="5" cy="6" r="1.25"></circle><circle cx="5" cy="12" r="1.25"></circle><circle cx="5" cy="18" r="1.25"></circle><line x1="9" y1="6" x2="20" y2="6"></line><line x1="9" y1="12" x2="20" y2="12"></line><line x1="9" y1="18" x2="20" y2="18"></line>',
        grid: '<rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect>'
      };

      function applyExpensesView(mode) {
        const tableWrap = document.getElementById('expensesTableWrap');
        const rowWrap = document.getElementById('expensesRowView');
        const toggleText = document.getElementById('expensesViewToggleText');
        const toggleBtn = document.getElementById('expensesViewToggle');
        const toggleIcon = document.getElementById('expensesViewToggleIcon');
        if (!tableWrap || !rowWrap) return;

        const normalized = mode === 'table' ? 'table' : 'grid';
        tableWrap.classList.toggle('is-hidden', normalized !== 'table');
        rowWrap.classList.toggle('is-hidden', normalized === 'table');

        if (toggleText) {
          toggleText.textContent = normalized === 'table' ? 'การ์ด' : 'ตาราง';
        }

        if (toggleBtn) {
          toggleBtn.setAttribute('aria-pressed', normalized === 'table' ? 'true' : 'false');
        }

        if (toggleIcon) {
          toggleIcon.innerHTML = normalized === 'table' ? expensesToggleIcons.grid : expensesToggleIcons.list;
        }

        try { localStorage.setItem('expensesViewMode', normalized); } catch (e) {}
      }

      function toggleExpensesView() {
        const tableWrap = document.getElementById('expensesTableWrap');
        const nextMode = tableWrap && tableWrap.classList.contains('is-hidden') ? 'table' : 'grid';
        applyExpensesView(nextMode);
      }

      // Current filter state (managed via AJAX, no page reloads)
      let _ajaxSort = '<?php echo htmlspecialchars($sortBy, ENT_QUOTES, "UTF-8"); ?>';
      let _ajaxMonth = '<?php echo htmlspecialchars($selectedMonth, ENT_QUOTES, "UTF-8"); ?>';

      function applyStatusFilterSelection(statusValue) {
        const normalizedStatus = String(statusValue || 'all');
        _activeFilterStatus = normalizedStatus;
        const nextUrl = new URL(window.location.href);

        if (normalizedStatus === 'all') {
          nextUrl.searchParams.delete('filter_status');
        } else {
          nextUrl.searchParams.set('filter_status', normalizedStatus);
        }

        if (_ajaxMonth) nextUrl.searchParams.set('filter_month', _ajaxMonth);
        if (_ajaxSort) nextUrl.searchParams.set('sort', _ajaxSort);

        window.location.href = nextUrl.pathname + (nextUrl.searchParams.toString() ? '?' + nextUrl.searchParams.toString() : '');
      }

      function changeSortBy(sortValue) {
        _ajaxSort = sortValue;
        reloadExpensesAjax();
      }

      // AJAX delete expense
      async function deleteExpense(expenseId) {
        if (!expenseId) return;
        
        const confirmed = await showConfirmDialog(
          'ยืนยันการลบ',
          `คุณต้องการลบรายการค่าใช้จ่ายนี้ <strong>ถาวร</strong> หรือไม่?`,
          'delete'
        );
        
        if (!confirmed) return;
        
        try {
          const formData = new FormData();
          formData.append('exp_id', expenseId);
          
          const response = await fetch('../Manage/delete_expense.php', {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          });
          
          const result = await response.json();
          
          if (result.success) {
            showSuccessToast(result.message);
            
            // Reload expense list after 500ms
            setTimeout(() => {
              loadExpenseList();
            }, 500);
          } else {
            showErrorToast(result.error || 'เกิดข้อผิดพลาด');
          }
        } catch (error) {
          console.error('Error:', error);
          showErrorToast('เกิดข้อผิดพลาดในการเชื่อมต่อ');
        }
      }

      // AJAX update expense status
      async function updateExpenseStatus(expenseId, newStatus) {
        console.log('updateExpenseStatus called:', expenseId, newStatus);
        
        if (!expenseId) {
          console.error('No expenseId provided');
          return;
        }
        
        const statusNum = parseInt(newStatus);
        const statusText = statusNum === 1 ? 'ชำระแล้ว' : 'รอชำระ';
        
        // ใช้ custom confirm modal
        console.log('Showing confirm dialog...');
        console.log('showConfirmDialog available:', typeof showConfirmDialog);
        let confirmed = false;
        
        if (typeof showConfirmDialog === 'function') {
          try {
            console.log('Calling showConfirmDialog with:', {
              title: 'ยืนยันการเปลี่ยนสถานะ',
              message: `คุณต้องการเปลี่ยนสถานะเป็น <strong>"${statusText}"</strong> หรือไม่?`,
              type: 'warning'
            });
            confirmed = await showConfirmDialog(
              'ยืนยันการเปลี่ยนสถานะ',
              `คุณต้องการเปลี่ยนสถานะเป็น <strong>"${statusText}"</strong> หรือไม่?`,
              'warning'
            );
            console.log('showConfirmDialog returned:', confirmed);
          } catch (error) {
            console.error('showConfirmDialog error:', error);
            confirmed = confirm('คุณต้องการเปลี่ยนสถานะเป็น "' + statusText + '" หรือไม่?');
          }
        } else {
          console.warn('showConfirmDialog not a function, using browser confirm');
          confirmed = confirm('คุณต้องการเปลี่ยนสถานะเป็น "' + statusText + '" หรือไม่?');
        }
        
        console.log('Confirm result:', confirmed);
        
        if (!confirmed) {
          console.log('User cancelled');
          return;
        }
        
        console.log('User confirmed, proceeding...');
        
        try {
          console.log('Sending request to update status...');
          const formData = new FormData();
          formData.append('exp_id', expenseId);
          formData.append('exp_status', statusNum);
          
          const response = await fetch('../Manage/update_expense_status_ajax.php', {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          });
          
          console.log('Response received:', response.status);
          const result = await response.json();
          console.log('Result:', result);
          
          if (result.success) {
            if (typeof showSuccessToast === 'function') {
              showSuccessToast(result.message);
            } else {
              alert(result.message);
            }
            
            // Reload expense list after 500ms
            setTimeout(() => {
              loadExpenseList();
            }, 500);
          } else {
            if (typeof showErrorToast === 'function') {
              showErrorToast(result.error || 'เกิดข้อผิดพลาด');
            } else {
              alert(result.error || 'เกิดข้อผิดพลาด');
            }
          }
        } catch (error) {
          console.error('Error:', error);
          if (typeof showErrorToast === 'function') {
            showErrorToast('เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + error.message);
          } else {
            alert('เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + error.message);
          }
        }
      }

      // Load expense list and stats via JSON API (no page reload)
      function loadExpenseList() {
        reloadExpensesAjax();
      }

      function reloadExpensesAjax() {
        const params = new URLSearchParams();
        if (_ajaxMonth) params.set('filter_month', _ajaxMonth);
        if (_ajaxSort) params.set('sort', _ajaxSort);
        if (_activeFilterStatus && _activeFilterStatus !== 'all') params.set('filter_status', _activeFilterStatus);

        fetch('/dormitory_management/Reports/get_expenses_ajax.php?' + params.toString(), {
          credentials: 'include'
        })
          .then(r => r.json())
          .then(data => {
            if (!data.success) throw new Error(data.error || 'AJAX error');
            rebuildExpensePage(data);
          })
          .catch(error => {
            console.error('Error loading expense list:', error);
            if (typeof showErrorToast === 'function') showErrorToast('โหลดข้อมูลล้มเหลว: ' + error.message);
          });
      }

      function fmtBaht(n) { return '฿' + Number(n || 0).toLocaleString('th-TH'); }
      function padId(id) { return '#' + String(id).padStart(4, '0'); }

      function rebuildExpensePage(data) {
        _hasLoadedAjaxExpenses = true;
        // Update month filter options
        rebuildMonthOptions(data.monthOptions);
        // Update stats cards
        rebuildStats(data);
        // Update collection bar
        rebuildCollectionBar(data);
        // Update meter alert
        rebuildMeterAlert(data.meterMissingRooms);
        // Update filter tabs
        rebuildFilterTabs(data);
        // Keep source rows and render filtered rows before DataTable paginates
        _allExpenseRows = Array.isArray(data.expenses) ? data.expenses : [];
        reapplyActiveFilter();
      }

      function rebuildMonthOptions(monthOptions) {
        const sel = document.getElementById('monthFilter');
        if (!sel || !monthOptions) return;
        sel.innerHTML = '';
        if (monthOptions.length === 0) {
          sel.innerHTML = '<option value="">ไม่มีข้อมูลเดือน</option>';
          return;
        }
        monthOptions.forEach(function(opt) {
          const o = document.createElement('option');
          o.value = opt.value;
          o.textContent = opt.label;
          if (opt.selected) o.selected = true;
          sel.appendChild(o);
        });
      }

      function rebuildStats(data) {
        const s = data.stats;
        const statsWrap = document.querySelector('.expense-stats');
        if (!statsWrap) return;

        let html = '';
        // unpaid - always show
        html += buildStatCard('is-unpaid', 'รอชำระ', s.unpaid, s.total_unpaid);
        // overdue
        if (s.overdue > 0) {
          html += buildStatCard('is-overdue', 'ค้างชำระ', s.overdue, s.total_overdue);
        }
        // pending+partial
        if (data.pendingPartialCount > 0) {
          html += buildStatCard('is-pending', 'รอดำเนินการ', data.pendingPartialCount, data.pendingPartialTotal);
        }
        // paid
        html += buildStatCard('is-paid', 'ชำระแล้ว', s.paid, s.total_paid);
        // total
        html += buildStatCard('is-total', 'รวมทั้งหมด', data.totalExpenseCount, data.totalAll);

        statsWrap.innerHTML = html;
      }

      function buildStatCard(cls, label, count, total) {
        return '<div class="expense-stat-card ' + cls + '">' +
          '<div class="stat-head"><span class="status-dot" aria-hidden="true"></span><h3>' + label + '</h3></div>' +
          '<div class="stat-value">' + Number(count).toLocaleString() + ' <span style="font-size:0.85rem;font-weight:500;opacity:0.7;">รายการ</span></div>' +
          '<div class="stat-money">' + fmtBaht(total) + '</div></div>';
      }

      function rebuildCollectionBar(data) {
        const wrap = document.querySelector('.collection-progress');
        if (!wrap) return;
        const pct = data.collectionPct;
        const s = data.stats;
        let fillClass = pct < 30 ? ' low' : (pct < 70 ? ' mid' : '');
        let segmentsHtml = '<div class="collection-segment"><span class="collection-segment-dot" style="background:#22c55e;"></span> ชำระแล้ว ' + fmtBaht(s.total_paid) + '</div>';
        if (data.pendingPartialTotal > 0) {
          segmentsHtml += '<div class="collection-segment"><span class="collection-segment-dot" style="background:#f59e0b;"></span> รอดำเนินการ ' + fmtBaht(data.pendingPartialTotal) + '</div>';
        }
        segmentsHtml += '<div class="collection-segment"><span class="collection-segment-dot" style="background:#ef4444;"></span> รอชำระ ' + fmtBaht(s.total_unpaid) + '</div>';
        if (s.total_overdue > 0) {
          segmentsHtml += '<div class="collection-segment"><span class="collection-segment-dot" style="background:#dc2626;"></span> ค้างชำระ ' + fmtBaht(s.total_overdue) + '</div>';
        }
        wrap.innerHTML =
          '<div class="collection-progress-header"><span class="collection-progress-label">อัตราการเก็บเงินได้</span><span class="collection-progress-pct">' + pct + '%</span></div>' +
          '<div class="collection-bar"><div class="collection-bar-fill' + fillClass + '" style="width:' + pct + '%;"></div></div>' +
          '<div class="collection-segments">' + segmentsHtml + '</div>';
      }

      function rebuildMeterAlert(rooms) {
        let banner = document.querySelector('.meter-alert-banner');
        if (!rooms || rooms.length === 0) {
          if (banner) banner.remove();
          return;
        }
        let html = '<div class="meter-alert-title">' +
          '<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>' +
          ' ⚠ ยังไม่ได้จดมิเตอร์ ' + rooms.length + ' ห้อง — ยอดค่าน้ำ/ไฟยังเป็น 0 กรุณาจดมิเตอร์ในผู้เช่าที่จัดการ ก่อนบิลจะคำนวณถูกต้อง</div>';
        html += '<ul class="meter-alert-list">';
        rooms.forEach(function(mr) {
          html += '<li>💧⚡ ห้อง ' + escHtml(mr.room_number) + ' • ' + escHtml(mr.tnt_name) + ' (' + escHtml(mr.month) + ')</li>';
        });
        html += '</ul>';
        if (!banner) {
          banner = document.createElement('div');
          banner.className = 'meter-alert-banner';
          const statsEl = document.querySelector('.expense-stats');
          if (statsEl) statsEl.parentNode.insertBefore(banner, statsEl);
          else document.querySelector('.manage-panel')?.prepend(banner);
        }
        banner.innerHTML = html;
      }

      function rebuildFilterTabs(data) {
        const tabsWrap = document.getElementById('expenseFilterTabs');
        if (!tabsWrap) return;
        const s = data.stats;
        // Remember current active status
        const activeTab = tabsWrap.querySelector('.expense-filter-tab.active');
        const currentFilter = activeTab ? activeTab.dataset.status : 'all';
        let html = '<button type="button" class="expense-filter-tab' + (currentFilter === 'all' ? ' active' : '') + '" data-status="all">ทั้งหมด <span class="tab-count">' + data.totalExpenseCount + '</span></button>';
        if (s.unpaid > 0) html += '<button type="button" class="expense-filter-tab' + (currentFilter === '0' ? ' active' : '') + '" data-status="0">รอชำระ <span class="tab-count">' + s.unpaid + '</span></button>';
        if (s.pending > 0) html += '<button type="button" class="expense-filter-tab' + (currentFilter === '2' ? ' active' : '') + '" data-status="2">รอตรวจสอบ <span class="tab-count">' + s.pending + '</span></button>';
        if (s.partial > 0) html += '<button type="button" class="expense-filter-tab' + (currentFilter === '3' ? ' active' : '') + '" data-status="3">ชำระยังไม่ครบ <span class="tab-count">' + s.partial + '</span></button>';
        if (s.overdue > 0) html += '<button type="button" class="expense-filter-tab' + (currentFilter === '4' ? ' active' : '') + '" data-status="4">ค้างชำระ <span class="tab-count">' + s.overdue + '</span></button>';
        if (s.paid > 0) html += '<button type="button" class="expense-filter-tab' + (currentFilter === '1' ? ' active' : '') + '" data-status="1">ชำระแล้ว <span class="tab-count">' + s.paid + '</span></button>';
        tabsWrap.innerHTML = html;
        // Re-bind click handlers
        tabsWrap.querySelectorAll('.expense-filter-tab').forEach(function(tab) {
          tab.addEventListener('click', function() {
            tabsWrap.querySelectorAll('.expense-filter-tab').forEach(function(t) { t.classList.remove('active'); });
            this.classList.add('active');
            _activeFilterStatus = this.dataset.status;
            applyStatusFilterSelection(this.dataset.status);
          });
        });
        // If active filter tab no longer exists, fall back to 'all'
        if (!tabsWrap.querySelector('.expense-filter-tab.active')) {
          const allTab = tabsWrap.querySelector('[data-status="all"]');
          if (allTab) { allTab.classList.add('active'); _activeFilterStatus = 'all'; }
        }
      }

      var _activeFilterStatus = 'all';
      var _allExpenseRows = [];
      var _hasLoadedAjaxExpenses = false;

      function getFilteredExpenses() {
        if (!Array.isArray(_allExpenseRows)) return [];
        if (_activeFilterStatus === 'all') return _allExpenseRows.slice();
        return _allExpenseRows.filter(function(expense) {
          return String(expense.status) === String(_activeFilterStatus);
        });
      }

      function reapplyActiveFilter() {
        if (!_hasLoadedAjaxExpenses) {
          // Bootstrap from API once so filtering and pagination stay in sync.
          reloadExpensesAjax();
          return;
        }

        const filteredExpenses = getFilteredExpenses();
        rebuildTable(filteredExpenses);
        rebuildCards(filteredExpenses);
        bindPaymentPreviewTriggers();
        reinitDataTable();
      }

      function escHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
      }

      function rebuildTable(expenses) {
        const tbody = document.querySelector('#table-expenses tbody');
        if (!tbody) return;
        if (!expenses || expenses.length === 0) {
          tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:2rem;color:#64748b;">ยังไม่มีข้อมูลค่าใช้จ่าย</td></tr>';
          return;
        }
        let html = '';
        expenses.forEach(function(e) {
          html += '<tr class="payment-preview-trigger" role="button" tabindex="0"' +
            ' aria-label="เปิดรายละเอียดการชำระเงินรายการ ' + padId(e.exp_id) + ' ห้อง ' + escHtml(e.room_number) + '"' +
            ' data-expense-id="' + e.exp_id + '"' +
            ' data-status="' + escHtml(e.status) + '"' +
            ' data-room="' + escHtml(e.room_number) + '"' +
            ' data-tenant="' + escHtml(e.tnt_name) + '"' +
            ' data-tenant-name="' + escHtml(e.tnt_name) + '"' +
            ' data-status-text="' + escHtml(e.statusText) + '"' +
            ' data-charges-paid="' + e.chargesPaid + '"' +
            ' data-charges-remain="' + e.chargesRemain + '">';
          html += '<td>' + padId(e.exp_id) + '</td>';
          html += '<td><div class="expense-table-room"><span>ห้อง ' + escHtml(e.room_number) + '</span><span class="expense-meta">' + escHtml(e.tnt_name) + '</span>';
          if (e.meterMissing) html += '<span class="meter-missing-badge">⚠ ยังไม่จดมิเตอร์</span>';
          html += '</div></td>';
          html += '<td>' + escHtml(e.exp_month_display) + '</td>';
          html += '<td style="text-align:right;">' +
            '<div style="color:#111827;font-weight:700;">ห้อง ' + fmtBaht(e.room_price) + '</div>' +
            '<div class="expense-meta">น้ำ ' + fmtBaht(e.exp_water) + ' • ไฟ ' + fmtBaht(e.exp_elec_chg) + '</div>' +
            '<div class="expense-meta">' + Number(e.exp_water_unit).toLocaleString() + ' หน่วยน้ำ • ' + Number(e.exp_elec_unit).toLocaleString() + ' หน่วยไฟ</div></td>';
          html += '<td style="text-align:right;"><strong style="color:' + e.totalColor + ';">' + fmtBaht(e.exp_total) + '</strong></td>';
          html += '<td><span class="status-badge" style="background: ' + e.statusColor + ';">' + escHtml(e.statusText) + '</span></td>';
          html += '<td class="crud-column"><div class="payment-cell-wrap"><div class="payment-compact">';
          if (e.chargesPaid > 0) {
            html += '<div class="payment-compact-row"><span class="payment-compact-label">ชำระแล้ว</span><span class="payment-compact-value">' + fmtBaht(e.chargesPaid) + '</span></div>';
          }
          if (e.chargesRemain > 0) {
            html += '<div class="payment-compact-row"><span class="payment-compact-label">ค้างชำระ</span><span class="payment-compact-value' + (e.chargesRemain > 0 ? ' warn' : '') + '">' + fmtBaht(e.chargesRemain) + '</span></div>';
          }
          html += '</div></div></td></tr>';
        });
        tbody.innerHTML = html;
      }

      function rebuildCards(expenses) {
        const rowView = document.getElementById('expensesRowView');
        if (!rowView) return;
        // Remove old cards and empty state
        rowView.querySelectorAll('.expense-row-card, .expense-empty-state').forEach(function(el) { el.remove(); });
        if (!expenses || expenses.length === 0) {
          rowView.innerHTML = '<div class="expense-row-card" style="text-align:center;color:#64748b;grid-column:1/-1;width:100%;">ยังไม่มีข้อมูลค่าใช้จ่าย</div>';
          return;
        }
        expenses.forEach(function(e) {
          const pct = e.chargesTotal > 0 ? Math.min(100, Math.round((e.chargesPaid / e.chargesTotal) * 100)) : 0;
          const fillClass = pct >= 100 ? 'full' : (pct > 0 ? 'partial' : 'none');
          const card = document.createElement('div');
          card.className = 'expense-row-card payment-preview-trigger';
          card.setAttribute('role', 'button');
          card.setAttribute('tabindex', '0');
          card.setAttribute('aria-label', 'ดูรายละเอียดค่าใช้จ่าย ' + padId(e.exp_id) + ' ห้อง ' + e.room_number);
          card.dataset.month = e.exp_month_key;
          card.dataset.expenseId = e.exp_id;
          card.dataset.status = e.status;
          card.dataset.room = e.room_number;
          card.dataset.tenant = e.tnt_name;
          card.dataset.tenantName = e.tnt_name;
          card.dataset.statusText = e.statusText;
          card.dataset.chargesPaid = e.chargesPaid;
          card.dataset.chargesRemain = e.chargesRemain;

          card.innerHTML =
            '<div class="expense-row-top"><div class="expense-row-main">' +
              '<strong class="expense-row-id">' + padId(e.exp_id) + '</strong>' +
              '<span class="expense-row-sub">ห้อง ' + escHtml(e.room_number) + ' • ' + escHtml(e.tnt_name) + '</span>' +
              (e.meterMissing ? '<span class="meter-missing-badge">⚠ ยังไม่จดมิเตอร์</span>' : '') +
            '</div><span class="status-badge" style="background: ' + e.statusColor + ';">' + escHtml(e.statusText) + '</span></div>' +
            '<div class="expense-row-meta">' +
              '<div class="expense-row-meta-item"><span class="expense-row-meta-label">เดือน/ปี</span><span class="expense-row-meta-value">' + escHtml(e.exp_month_short) + '</span></div>' +
              '<div class="expense-row-meta-item"><span class="expense-row-meta-label">ค่าห้อง</span><span class="expense-row-meta-value">' + fmtBaht(e.room_price) + '</span></div>' +
              '<div class="expense-row-meta-item"><span class="expense-row-meta-label">ค่าไฟ</span><span class="expense-row-meta-value">' + fmtBaht(e.exp_elec_chg) + '</span></div>' +
              '<div class="expense-row-meta-item"><span class="expense-row-meta-label">ค่าน้ำ</span><span class="expense-row-meta-value">' + fmtBaht(e.exp_water) + '</span></div>' +
              '<div class="expense-row-meta-item"><span class="expense-row-meta-label">ยอดรวม</span><span class="expense-row-meta-value total" style="color:' + e.totalColor + ';">' + fmtBaht(e.exp_total) + '</span></div>' +
            '</div>' +
            '<div class="expense-card-progress">' +
              '<div class="expense-card-progress-bar"><div class="expense-card-progress-fill ' + fillClass + '" style="width:' + pct + '%;"></div></div>' +
              '<div class="expense-card-progress-text"><span>ชำระ ' + fmtBaht(e.chargesPaid) + '</span><span>คงเหลือ ' + fmtBaht(Math.max(0, e.chargesTotal - e.chargesPaid)) + '</span></div>' +
            '</div>';
          rowView.appendChild(card);
        });
      }

      function reinitDataTable() {
        // Destroy old DataTable instance if it exists
        if (window.__expenseDataTable) {
          try { window.__expenseDataTable.destroy(); } catch (e) {}
          window.__expenseDataTable = null;
        }
        const expenseTableEl = document.querySelector('#table-expenses');
        if (expenseTableEl && window.simpleDatatables) {
          try {
            window.__expenseDataTable = new simpleDatatables.DataTable(expenseTableEl, {
              searchable: true,
              fixedHeight: false,
              perPage: 6,
              perPageSelect: [6, 10, 25, 50, 100],
              labels: {
                placeholder: 'ค้นหา...',
                perPage: '{select} แถวต่อหน้า',
                noRows: 'ไม่มีข้อมูล',
                info: 'แสดง {start}–{end} จาก {rows} รายการ'
              },
              columns: [{ select: [5, 6], sortable: false }]
            });
          } catch (err) {
            console.error('Failed to reinit expense table', err);
          }
        }
      }

      // Setup event listeners for update status buttons
      function setupStatusButtons() {
        console.log('Setting up status buttons...');
        const buttons = document.querySelectorAll('.update-status-btn');
        console.log('Found buttons:', buttons.length);
        
        buttons.forEach(button => {
          button.addEventListener('click', function(e) {
            e.preventDefault();
            const expenseId = parseInt(this.getAttribute('data-expense-id'));
            const newStatus = this.getAttribute('data-new-status');
            console.log('Button clicked:', expenseId, newStatus);
            updateExpenseStatus(expenseId, newStatus);
          });
        });
      }

      // Toggle expense form visibility
      function toggleExpenseForm() {
        const section = document.getElementById('addExpenseSection');
        const icon = document.getElementById('toggleExpenseFormIcon');
        const text = document.getElementById('toggleExpenseFormText');
        const isHidden = section.style.display === 'none';
        
        if (isHidden) {
          section.style.display = '';
          icon.textContent = '▼';
          text.textContent = 'ซ่อนฟอร์ม';
          localStorage.setItem('expenseFormVisible', 'true');
        } else {
          section.style.display = 'none';
          icon.textContent = '▶';
          text.textContent = 'แสดงฟอร์ม';
          localStorage.setItem('expenseFormVisible', 'false');
        }
      }

      // Call setup when DOM is ready
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupStatusButtons);
        document.addEventListener('DOMContentLoaded', function() {
          // Restore form visibility from localStorage
          const isFormVisible = localStorage.getItem('expenseFormVisible') !== 'false';
          const section = document.getElementById('addExpenseSection');
          const icon = document.getElementById('toggleExpenseFormIcon');
          const text = document.getElementById('toggleExpenseFormText');
          if (!isFormVisible) {
            section.style.display = 'none';
            icon.textContent = '▶';
            text.textContent = 'แสดงฟอร์ม';
          }

          // Initialize DataTable
          const expenseTableEl = document.querySelector('#table-expenses');
          if (expenseTableEl && window.simpleDatatables) {
            try {
              const dt = new simpleDatatables.DataTable(expenseTableEl, {
                searchable: true,
                fixedHeight: false,
                perPage: 6,
                perPageSelect: [6, 10, 25, 50, 100],
                labels: {
                  placeholder: 'ค้นหา...',
                  perPage: '{select} แถวต่อหน้า',
                  noRows: 'ไม่มีข้อมูล',
                  info: 'แสดง {start}–{end} จาก {rows} รายการ'
                },
                columns: [
                  { select: [5, 6], sortable: false }
                ]
              });
              window.__expenseDataTable = dt;
            } catch (err) {
              console.error('Failed to init expense table', err);
            }
          }

          try {
            const savedViewMode = localStorage.getItem('expensesViewMode') || 'grid';
            applyExpensesView(savedViewMode);
          } catch (e) {
            applyExpensesView('grid');
          }
        });
      } else {
        setupStatusButtons();
        // Restore form visibility immediately
        const isFormVisible = localStorage.getItem('expenseFormVisible') !== 'false';
        const section = document.getElementById('addExpenseSection');
        const icon = document.getElementById('toggleExpenseFormIcon');
        const text = document.getElementById('toggleExpenseFormText');
        if (!isFormVisible) {
          section.style.display = 'none';
          icon.textContent = '▶';
          text.textContent = 'แสดงฟอร์ม';
        }

        try {
          const savedViewMode = localStorage.getItem('expensesViewMode') || 'grid';
          applyExpensesView(savedViewMode);
        } catch (e) {
          applyExpensesView('grid');
        }
      }

      (function setupExpenseCalculator() {
        const ctrSelect = document.getElementById('ctr_id');
        const elecUnit = document.getElementById('exp_elec_unit');
        const elecRate = document.getElementById('rate_elec');
        const waterUnit = document.getElementById('exp_water_unit');
        const waterRate = document.getElementById('rate_water');
        const preview = document.getElementById('calcPreview');

        // ค่าน้ำแบบเหมาจ่าย (ค่าตั้งค่าปรับได้)
        var WATER_BASE_UNITS = <?php echo getWaterBaseUnits(); ?>;
        var WATER_BASE_PRICE = <?php echo getWaterBasePrice(); ?>;
        var WATER_EXCESS_RATE = <?php echo getWaterExcessRate(); ?>;
        function calcWaterCost(units) {
          if (units <= 0) return 0;
          if (units <= WATER_BASE_UNITS) return WATER_BASE_PRICE;
          return WATER_BASE_PRICE + (units - WATER_BASE_UNITS) * WATER_EXCESS_RATE;
        }
        
        function updatePreview() {
          const selectedOpt = ctrSelect.options[ctrSelect.selectedIndex];
          const roomPrice = selectedOpt ? parseInt(selectedOpt.dataset.roomPrice || '0') : 0;
          const elecU = parseInt(elecUnit.value || '0');
          const elecR = parseFloat(elecRate.value || '0');
          const waterU = parseInt(waterUnit.value || '0');
          
          const elecChg = Math.round(elecU * elecR);
          const waterChg = calcWaterCost(waterU);
          const total = roomPrice + elecChg + waterChg;
          
          if (roomPrice > 0 || elecU > 0 || waterU > 0) {
            preview.style.display = 'block';
            document.getElementById('preview_room').textContent = '฿' + roomPrice.toLocaleString();
            document.getElementById('preview_elec_unit').textContent = elecU;
            document.getElementById('preview_elec_rate').textContent = elecR.toFixed(2);
            document.getElementById('preview_elec').textContent = '฿' + elecChg.toLocaleString();
            document.getElementById('preview_water_unit').textContent = waterU;
            document.getElementById('preview_water_rate').textContent = 'เหมาจ่าย';
            document.getElementById('preview_water').textContent = '฿' + waterChg.toLocaleString();
            document.getElementById('preview_total').textContent = '฿' + total.toLocaleString();
          } else {
            preview.style.display = 'none';
          }
        }
        
        if (ctrSelect && elecUnit && elecRate && waterUnit && waterRate) {
          ctrSelect.addEventListener('change', updatePreview);
          elecUnit.addEventListener('input', updatePreview);
          elecRate.addEventListener('change', updatePreview);
          waterUnit.addEventListener('input', updatePreview);
          waterRate.addEventListener('change', updatePreview);
          
          const expenseForm = document.getElementById('expenseForm');
          
          expenseForm.addEventListener('reset', () => {
            setTimeout(updatePreview, 10);
          });
          
          // Submit form with AJAX
          expenseForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const submitBtn = expenseForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'กำลังบันทึก...';
            
            try {
              const response = await fetch('../Manage/process_expense.php', {
                method: 'POST',
                body: new FormData(expenseForm),
                headers: {
                  'X-Requested-With': 'XMLHttpRequest'
                }
              });
              
              const result = await response.json();
              
              if (result.success) {
                showSuccessToast(result.message || 'บันทึกค่าใช้จ่ายเรียบร้อยแล้ว');
                expenseForm.reset();
                setTimeout(() => {
                  loadExpenseList();
                }, 500);
              } else {
                showErrorToast(result.error || 'เกิดข้อผิดพลาด');
              }
            } catch (error) {
              console.error('Error:', error);
              showErrorToast('เกิดข้อผิดพลาดในการเชื่อมต่อ');
            } finally {
              submitBtn.disabled = false;
              submitBtn.textContent = originalText;
            }
          });

          updatePreview();
        }
      })();

                // Custom Input Dialog
                function showInputDialog(title, label, placeholder = '', type = 'number') {
                  return new Promise((resolve) => {
                    const overlay = document.createElement('div');
                    overlay.className = 'confirm-overlay';
                    overlay.innerHTML = `
                      <div class="confirm-modal" style="border-color: rgba(96, 165, 250, 0.3);">
                        <div class="confirm-header">
                          <div class="confirm-icon" style="background: linear-gradient(135deg, #3b82f6, #2563eb);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                          </div>
                          <h3 class="confirm-title" style="color: #60a5fa;">${title}</h3>
                        </div>
                        <div class="confirm-message">
                          <label style="display: block; margin-bottom: 0.75rem; color: #cbd5e1; font-weight: 500;">${label}</label>
                          <input type="${type}" class="custom-input" placeholder="${placeholder}" 
                                 style="width: 100%; padding: 0.85rem; border-radius: 10px; border: 1px solid rgba(96, 165, 250, 0.3); 
                                        background: rgba(15, 23, 42, 0.8); color: #f5f8ff; font-size: 1rem;" />
                        </div>
                        <div class="confirm-actions">
                          <button class="confirm-btn confirm-btn-cancel" data-action="cancel">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                              <line x1="18" y1="6" x2="6" y2="18"/>
                              <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                            ยกเลิก
                          </button>
                          <button class="confirm-btn" data-action="confirm" style="background: #3b82f6;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                              <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            ตกลง
                          </button>
                        </div>
                      </div>
                    `;
                    
                    const input = overlay.querySelector('.custom-input');
                    const cancelBtn = overlay.querySelector('[data-action="cancel"]');
                    const confirmBtn = overlay.querySelector('[data-action="confirm"]');
                    
                    // Focus input
                    setTimeout(() => input.focus(), 100);
                    
                    // Handle Enter key
                    input.addEventListener('keydown', (e) => {
                      if (e.key === 'Enter') {
                        e.preventDefault();
                        overlay.remove();
                        resolve(input.value);
                      }
                    });
                    
                    // Handle buttons
                    cancelBtn.addEventListener('click', () => {
                      overlay.remove();
                      resolve(null);
                    });
                    
                    confirmBtn.addEventListener('click', () => {
                      overlay.remove();
                      resolve(input.value);
                    });
                    
                    // Close on overlay click
                    overlay.addEventListener('click', (e) => {
                      if (e.target === overlay) {
                        overlay.remove();
                        resolve(null);
                      }
                    });
                    
                    // ESC key
                    const handleKeyDown = (e) => {
                      if (e.key === 'Escape') {
                        overlay.remove();
                        resolve(null);
                        document.removeEventListener('keydown', handleKeyDown);
                      }
                    };
                    document.addEventListener('keydown', handleKeyDown);
                    
                    document.body.appendChild(overlay);
                  });
                }

                // เพิ่ม/ลบเรตน้ำไฟ
                async function addRateFlow() {
                  const water = await showInputDialog('เพิ่มอัตราค่าน้ำ', 'กรอกอัตราค่าน้ำ/หน่วย (บาท)', '20', 'number');
                  if (water === null || water === '') return;
                  const waterVal = parseFloat(water);
                  if (Number.isNaN(waterVal) || waterVal < 0) { 
                    showErrorToast('กรุณากรอกตัวเลขค่าน้ำให้ถูกต้อง'); 
                    return; 
                  }

                  const elec = await showInputDialog('เพิ่มอัตราค่าไฟ', 'กรอกอัตราค่าไฟ/หน่วย (บาท)', '7', 'number');
                  if (elec === null || elec === '') return;
                  const elecVal = parseFloat(elec);
                  if (Number.isNaN(elecVal) || elecVal < 0) { 
                    showErrorToast('กรุณากรอกตัวเลขค่าไฟให้ถูกต้อง'); 
                    return; 
                  }

                  const formData = new FormData();
                  formData.append('rate_water', waterVal.toString());
                  formData.append('rate_elec', elecVal.toString());

                  fetch('../Manage/add_rate.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                      if (!data.success) throw new Error(data.message || 'เพิ่มเรตไม่สำเร็จ');
                      const waterSel = document.getElementById('rate_water');
                      const elecSel = document.getElementById('rate_elec');
                      const optWater = document.createElement('option');
                      optWater.value = waterVal;
                      optWater.dataset.rateId = data.rate_id;
                      optWater.textContent = `฿${waterVal.toFixed(2)} / หน่วย`;
                      const optElec = document.createElement('option');
                      optElec.value = elecVal;
                      optElec.dataset.rateId = data.rate_id;
                      optElec.textContent = `฿${elecVal.toFixed(2)} / หน่วย`;
                      waterSel.appendChild(optWater);
                      elecSel.appendChild(optElec);
                      waterSel.value = waterVal;
                      elecSel.value = elecVal;
                      showSuccessToast('เพิ่มเรตเรียบร้อยแล้ว');
                      if (typeof updatePreview === 'function') updatePreview();
                    })
                    .catch(err => {
                      console.error(err);
                      showErrorToast(err.message || 'เพิ่มเรตไม่สำเร็จ');
                    });
                }

                function deleteRateFlow() {
                  const waterSel = document.getElementById('rate_water');
                  const elecSel = document.getElementById('rate_elec');
                  const selected = waterSel.options[waterSel.selectedIndex];
                  const rateId = selected ? parseInt(selected.dataset.rateId || '0') : 0;
                  if (!rateId) { 
                    showErrorToast('เรตนี้ลบไม่ได้ (ไม่มีรหัส)'); 
                    return; 
                  }
                  
                  const formData = new FormData();
                  formData.append('rate_id', rateId.toString());

                  fetch('../Manage/delete_rate.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                      if (!data.success) throw new Error(data.message || 'ลบเรตไม่สำเร็จ');
                      // remove matching rate_id in both selects
                      [waterSel, elecSel].forEach(sel => {
                        [...sel.options].forEach((o, idx) => {
                          if (parseInt(o.dataset.rateId || '0') === rateId) {
                            sel.remove(idx);
                          }
                        });
                      });
                      // เลือก option แรกที่เหลืออยู่
                      if (waterSel.options.length) waterSel.selectedIndex = 0;
                      if (elecSel.options.length) elecSel.selectedIndex = 0;
                      showSuccessToast('ลบเรตเรียบร้อยแล้ว');
                      if (typeof updatePreview === 'function') updatePreview();
                    })
                    .catch(err => {
                      console.error(err);
                      showErrorToast(err.message || 'ลบเรตไม่สำเร็จ');
                    });
                }

                document.getElementById('addRateBtn')?.addEventListener('click', addRateFlow);
                document.getElementById('deleteRateBtn')?.addEventListener('click', deleteRateFlow);

                function detectReportsLightTheme() {
                  const themeColor = getComputedStyle(document.documentElement).getPropertyValue('--theme-bg-color').trim().toLowerCase();
                  const bodyBg = getComputedStyle(document.body).backgroundColor;
                  return themeColor === '#fff' || themeColor === '#ffffff' ||
                         themeColor === 'rgb(255, 255, 255)' || themeColor === 'white' ||
                         bodyBg === 'rgb(255, 255, 255)' || bodyBg === '#fff' || bodyBg === '#ffffff';
                }

                function syncReportsThemeClass() {
                  const isLightTheme = detectReportsLightTheme();
                  document.documentElement.classList.toggle('light-theme', isLightTheme);
                  document.body.classList.toggle('light-theme', isLightTheme);
                  return isLightTheme;
                }

                window.detectReportsLightTheme = detectReportsLightTheme;
                window.syncReportsThemeClass = syncReportsThemeClass;

                // ฟังก์ชันสำหรับตรวจจับและปรับสี input fields ตาม theme
                function updateInputTheme() {
                  const isLightTheme = syncReportsThemeClass();
                  
                  // เลือก input และ select ทั้งหมดในฟอร์ม
                  const inputs = document.querySelectorAll('.expense-form-group input, .expense-form-group select');
                  
                  inputs.forEach(input => {
                    if (isLightTheme) {
                      // โหมดสีขาว
                      input.classList.add('light-mode-input');
                    } else {
                      // โหมดมืด
                      input.classList.remove('light-mode-input');
                    }
                  });
                }

                // เรียกใช้เมื่อโหลดหน้า
                updateInputTheme();
                
                // ตรวจสอบการเปลี่ยน theme color (ใช้ MutationObserver)
                const themeObserver = new MutationObserver(() => {
                  updateInputTheme();
                });
                themeObserver.observe(document.documentElement, { 
                  attributes: true, 
                  attributeFilter: ['style'] 
                });
                themeObserver.observe(document.body, { 
                  attributes: true, 
                  attributeFilter: ['style'] 
                });
    </script>

    <!-- Payment Proof Modal Script -->
    <script>
      const paymentProofModal = document.getElementById('paymentProofModal');
      const closePaymentProofModal = document.getElementById('closePaymentProofModal');
      const paymentProofContent = document.getElementById('paymentProofContent');
      const pageHeaderBar = document.querySelector('.page-header-bar');
      const ownerBankName = <?php echo json_encode((string)($settings['bank_name'] ?? ''), JSON_UNESCAPED_UNICODE); ?>;
      const ownerAccountNumber = <?php echo json_encode((string)($settings['bank_account_number'] ?? ''), JSON_UNESCAPED_UNICODE); ?>;

      function renderPaymentModalCard(payment, context) {
        const toNumber = (value) => Number(value || 0);
        const formatBaht = (value) => `฿${toNumber(value).toLocaleString('th-TH')}`;
        const proofSrc = payment.pay_proof
          ? '/dormitory_management/Public/Assets/Images/Payments/' + payment.pay_proof
          : '';
        const ext = String(payment.pay_proof || '').toLowerCase().split('.').pop();
        const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);
        const paidDate = payment.pay_date ? new Date(payment.pay_date).toLocaleDateString('th-TH') : '-';
        const isVerified = String(payment.pay_status) === '1';
        const fallbackStatusText = isVerified ? 'ชำระแล้ว' : 'รอตรวจสอบ';
        const statusText = String(context.statusText || '').trim() || fallbackStatusText;
        let statusClass = isVerified ? 'paid' : 'pending';
        if (statusText.startsWith('รอชำระ') || statusText.startsWith('ยังไม่ได้จดมิเตอร์')) {
          statusClass = 'unpaid';
        } else if (statusText.startsWith('ค้างชำระ')) {
          statusClass = 'overdue';
        } else if (statusText.startsWith('ชำระยังไม่ครบ')) {
          statusClass = 'partial';
        } else if (statusText.startsWith('รอตรวจสอบ')) {
          statusClass = 'pending';
        } else if (statusText.startsWith('ชำระแล้ว')) {
          statusClass = 'paid';
        }

        const statusIconMap = {
          paid: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.7" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>',
          pending: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><polyline points="12 7 12 12 15 14"></polyline></svg>',
          partial: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
          unpaid: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><line x1="9" y1="9" x2="15" y2="15"></line><line x1="15" y1="9" x2="9" y2="15"></line></svg>',
          overdue: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>'
        };
        const statusIcon = statusIconMap[statusClass] || statusIconMap.pending;
        const paymentRemark = String(payment.pay_remark || '').trim();
        const paymentType = paymentRemark || 'ค่าห้อง/ค่าน้ำ/ค่าไฟ';
        const chargesPaid = toNumber(context.chargesPaid);
        const chargesRemain = toNumber(context.chargesRemain);
        const totalPaid = chargesPaid;
        const totalRemain = chargesRemain;
        const totalRemainColor = totalRemain > 0 ? '#ef4444' : '#22c55e';
        const showTotalPaid = totalPaid > 0;
        const showTotalRemain = totalRemain > 0;
        const showChargesPaid = chargesPaid > 0;
        const showChargesRemain = chargesRemain > 0;
        const proofHtml = !proofSrc
          ? '<div class="payment-modal-value" style="color:#94a3b8;">-</div>'
          : (isImage
              ? '<img class="payment-proof-thumb" src="' + proofSrc + '" alt="หลักฐานการโอน" />'
              : '<a class="payment-modal-value" style="color:#2563eb;font-weight:700;" href="' + proofSrc + '" target="_blank" rel="noopener">เปิดไฟล์หลักฐาน</a>');

        paymentProofContent.innerHTML = `
          <div class="payment-modal-head">
            <div class="payment-modal-check ${statusClass}">
              ${statusIcon}
            </div>
            <h3 class="payment-modal-title">แจ้งชำระเงิน</h3>
          </div>
          <div class="payment-modal-body">
            <div class="payment-modal-summary">
              <div class="payment-modal-summary-item">
                <div class="payment-modal-summary-label">สถานะ</div>
                <div class="payment-modal-summary-value payment-modal-status ${statusClass}">${statusText}</div>
              </div>
              ${showTotalPaid ? `<div class="payment-modal-summary-item">
                <div class="payment-modal-summary-label">จ่ายแล้วรวม</div>
                <div class="payment-modal-summary-value">${formatBaht(totalPaid)}</div>
              </div>` : ''}
              ${showTotalRemain ? `<div class="payment-modal-summary-item">
                <div class="payment-modal-summary-label">ยอดค้างรวม</div>
                <div class="payment-modal-summary-value" style="color:${totalRemainColor};">${formatBaht(totalRemain)}</div>
              </div>` : ''}
            </div>

            <div class="payment-modal-section">
              <h4 class="payment-modal-section-title">ข้อมูลการชำระ</h4>
              <div class="payment-modal-grid">
                <div class="payment-modal-label">ผู้เช่า</div>
                <div class="payment-modal-value">${context.tenantName || '-'}</div>

                <div class="payment-modal-label">ธนาคารปลายทาง</div>
                <div class="payment-modal-value">${ownerBankName || '-'}<br>${ownerAccountNumber || '-'}</div>

                <div class="payment-modal-label">วันที่ชำระ</div>
                <div class="payment-modal-value">${paidDate}</div>

                <div class="payment-modal-label">จำนวนเงิน</div>
                <div class="payment-modal-value">${formatBaht(payment.pay_amount)}</div>

                <div class="payment-modal-label">ประเภทรายการ</div>
                <div class="payment-modal-value">${paymentType}</div>
              </div>
            </div>

            ${(showChargesPaid || showChargesRemain) ? `<div class="payment-modal-section">
              <h4 class="payment-modal-section-title">สรุปย่อย</h4>
              <div class="payment-modal-grid">
                ${showChargesPaid ? `<div class="payment-modal-label">ชำระแล้ว</div>
                <div class="payment-modal-value">${formatBaht(chargesPaid)}</div>` : ''}

                ${showChargesRemain ? `<div class="payment-modal-label">ค้างชำระ</div>
                <div class="payment-modal-value" style="color:#ef4444;font-weight:700;">${formatBaht(chargesRemain)}</div>` : ''}
              </div>
            </div>` : ''}

            <div class="payment-modal-section">
              <h4 class="payment-modal-section-title">หลักฐานการโอน</h4>
              <div>${proofHtml}</div>
            </div>
          </div>
        `;
      }

      function showPaymentModal() {
        if (!paymentProofModal) return;
        paymentProofModal.style.display = 'flex';
        paymentProofModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('payment-modal-open');
        if (pageHeaderBar) pageHeaderBar.classList.add('modal-open-hidden');
      }

      function closeModal() {
        if (!paymentProofModal) return;
        paymentProofModal.style.display = 'none';
        paymentProofModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('payment-modal-open');
        if (pageHeaderBar) pageHeaderBar.classList.remove('modal-open-hidden');
      }

      function notifyPaymentError(message) {
        if (typeof showErrorToast === 'function') {
          showErrorToast(message);
        } else {
          alert(message);
        }
      }

      async function openPaymentPreview(expenseId, context, options) {
        const silentError = !!(options && options.silentError);
        if (!expenseId) {
          if (!silentError) notifyPaymentError('ไม่พบรหัสรายการค่าใช้จ่าย จึงไม่สามารถเปิดรายละเอียดได้');
          return false;
        }

        try {
          const response = await fetch('/dormitory_management/Reports/get_payment_proofs.php?exp_id=' + encodeURIComponent(expenseId), {
            credentials: 'include'
          });
          if (!response.ok) throw new Error('ไม่สามารถโหลดข้อมูลการชำระเงินได้');
          const data = await response.json();
          if (!data.success) {
            throw new Error(data.message || 'ไม่สามารถโหลดข้อมูลการชำระเงินได้');
          }

          const firstPayment = (Array.isArray(data.payments) && data.payments.length > 0)
            ? data.payments[0]
            : {
                pay_proof: '',
                pay_date: '',
                pay_amount: 0,
                pay_remark: '',
                pay_status: '0'
              };

          renderPaymentModalCard(firstPayment, context);
          showPaymentModal();
          return true;
        } catch (error) {
          if (!silentError) notifyPaymentError(error.message || 'เกิดข้อผิดพลาด');
          closeModal();
          return false;
        }
      }

      function attachPaymentPreviewTrigger(card) {
        if (!card) return;

        // บังคับ cursor แบบ inline กันสไตล์ภายนอกทับ
        card.style.cursor = 'pointer';
        card.querySelectorAll('*').forEach(function(el) {
          el.style.cursor = 'pointer';
        });

        card.__paymentPreviewBound = true;
        card.dataset.boundPreview = '1';
      }

      async function openPaymentFromTrigger(card) {
        if (!card || card.dataset.previewBusy === '1') return;
        card.dataset.previewBusy = '1';

        try {
          const opened = await openPaymentPreview(card.dataset.expenseId, {
            tenantName: card.dataset.tenantName || '-',
            statusText: card.dataset.statusText || 'รอตรวจสอบ',
            chargesPaid: card.dataset.chargesPaid || 0,
            chargesRemain: card.dataset.chargesRemain || 0
          }, {
            silentError: true
          });

          if (!opened || !paymentProofModal || paymentProofModal.getAttribute('aria-hidden') !== 'false') {
            alert('ไม่สามารถเปิด Modal รายละเอียดได้ กรุณาลองใหม่อีกครั้ง');
          }
        } finally {
          card.dataset.previewBusy = '0';
        }
      }

      function bindPaymentPreviewTriggers() {
        document.querySelectorAll('.payment-preview-trigger').forEach(attachPaymentPreviewTrigger);
      }

      bindPaymentPreviewTriggers();

      // Fallback แบบ delegation: ครอบคลุมแถว/การ์ดที่ถูก render ใหม่จาก DataTable
      document.addEventListener('click', function(e) {
        const trigger = e.target.closest('.payment-preview-trigger');
        if (!trigger) return;
        openPaymentFromTrigger(trigger);
      });

      document.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const active = document.activeElement;
        if (!active || !active.closest) return;
        const trigger = active.closest('.payment-preview-trigger');
        if (!trigger) return;
        e.preventDefault();
        openPaymentFromTrigger(trigger);
      });

      // Fallback ระดับเมาส์: บังคับให้เป็นรูปมือเมื่อชี้บนแถว/การ์ดที่กดเปิด modal ได้
      let __hoverPaymentTrigger = null;
      document.addEventListener('mousemove', function(e) {
        const trigger = e.target && e.target.closest ? e.target.closest('.payment-preview-trigger') : null;
        if (trigger) {
          if (__hoverPaymentTrigger !== trigger) {
            __hoverPaymentTrigger = trigger;
            document.body.style.cursor = 'pointer';
          }
        } else if (__hoverPaymentTrigger) {
          __hoverPaymentTrigger = null;
          document.body.style.cursor = '';
        }
      });

      document.addEventListener('mouseleave', function() {
        __hoverPaymentTrigger = null;
        document.body.style.cursor = '';
      });

      document.querySelectorAll('.view-payment-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          const expenseId = this.getAttribute('data-expense-id');
          openPaymentPreview(expenseId, { tenantName: '-', statusText: 'รอตรวจสอบ' });
        });
      });

      closePaymentProofModal?.addEventListener('click', closeModal);

      paymentProofModal.addEventListener('click', function(e) {
        if (e.target === this) {
          closeModal();
        }
      });

      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && paymentProofModal && paymentProofModal.style.display !== 'none') {
          closeModal();
        }
      });
    </script>

    <!-- Month Filter Script (AJAX, no reload) -->
    <script>
      (function() {
        const monthFilter = document.getElementById('monthFilter');
        if (!monthFilter) return;

        monthFilter.addEventListener('change', function() {
          _ajaxMonth = this.value || '';
          reloadExpensesAjax();
        });
      })();
    </script>

    <!-- Status Filter Tabs + Search (client-side, AJAX-compatible) -->
    <script>
    (function() {
      const searchParams = new URLSearchParams(window.location.search);
      const urlFilter = searchParams.get('filter_status') || searchParams.get('filter');
      _activeFilterStatus = (urlFilter !== null) ? urlFilter : 'all';

      const filterTabs = document.querySelectorAll('#expenseFilterTabs .expense-filter-tab');

      // Tab click handlers (initial page load only; rebuilt tabs get handlers via rebuildFilterTabs)
      filterTabs.forEach(tab => {
        tab.addEventListener('click', function() {
          document.querySelectorAll('#expenseFilterTabs .expense-filter-tab').forEach(t => t.classList.remove('active'));
          this.classList.add('active');
          _activeFilterStatus = this.dataset.status;
          applyStatusFilterSelection(this.dataset.status);
        });
      });

      // Auto-activate tab from URL ?filter= param
      if (urlFilter !== null) {
        const targetTab = Array.from(filterTabs).find(t => t.dataset.status === urlFilter);
        if (targetTab) {
          filterTabs.forEach(t => t.classList.remove('active'));
          targetTab.classList.add('active');
        }
        reapplyActiveFilter();
      }
    })();
    </script>
    <script src="/dormitory_management/Public/Assets/Js/futuristic-bright.js"></script>
  </body>
</html>
