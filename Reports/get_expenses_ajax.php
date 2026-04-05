<?php
declare(strict_types=1);
session_start();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';
require_once __DIR__ . '/../includes/thai_date_helper.php';
require_once __DIR__ . '/../includes/water_calc.php';
$pdo = connectDB();

// --- อัตโนมัติสร้างรายการค่าใช้จ่ายใหม่เมื่อถึงเดือนใหม่ ---
$today = new DateTime();
$currentMonth = $today->format('Y-m');
$todayDay = (int)$today->format('j');

$billingGenerateDaySetting = (int)($pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'billing_generate_day' LIMIT 1")->fetchColumn() ?: 1);
$effectiveCurrentMonth = ($todayDay >= $billingGenerateDaySetting)
    ? $currentMonth
    : (new DateTime($currentMonth . '-01'))->modify('-1 month')->format('Y-m');

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

$contractsStmt = $pdo->query("
  SELECT DISTINCT c.ctr_id, c.ctr_start, c.ctr_end
  FROM contract c
  INNER JOIN tenant_workflow tw ON tw.ctr_id = c.ctr_id
  WHERE c.ctr_status = '0'
    AND (COALESCE(tw.step_5_confirmed, 0) = 1 OR COALESCE(tw.current_step, 0) >= 5)
");
$contracts = $contractsStmt ? $contractsStmt->fetchAll(PDO::FETCH_ASSOC) : [];
foreach ($contracts as $contract) {
    $ctr_id = (int)$contract['ctr_id'];
    $ctrStartDate = new DateTime($contract['ctr_start']);
    $ctrEndDate = new DateTime($contract['ctr_end']);
    $ctr_end = $ctrEndDate->format('Y-m');
    $firstBillMonth = $ctrStartDate->format('Y-m');
    $nextMonth = $firstBillMonth;
    while ($nextMonth <= $effectiveCurrentMonth && $nextMonth <= $ctr_end) {
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM expense WHERE ctr_id = ? AND DATE_FORMAT(exp_month, '%Y-%m') = ?");
        $checkStmt->execute([$ctr_id, $nextMonth]);
        if ((int)$checkStmt->fetchColumn() === 0) {
            $roomStmt = $pdo->prepare("SELECT r.room_id, rt.type_price FROM contract c LEFT JOIN room r ON c.room_id = r.room_id LEFT JOIN roomtype rt ON r.type_id = rt.type_id WHERE c.ctr_id = ?");
            $roomStmt->execute([$ctr_id]);
            $room = $roomStmt->fetch(PDO::FETCH_ASSOC);
            $room_price = (int)($room['type_price'] ?? 0);
            $rateRow = $pdo->query("SELECT rate_water, rate_elec FROM rate ORDER BY rate_id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $rate_elec = (int)($rateRow['rate_elec'] ?? 7);
            $rate_water = (int)($rateRow['rate_water'] ?? 20);
            $insert = $pdo->prepare("INSERT INTO expense (exp_month, exp_elec_unit, exp_water_unit, rate_elec, rate_water, room_price, exp_elec_chg, exp_water, exp_total, exp_status, ctr_id) VALUES (?, 0, 0, ?, ?, ?, 0, 0, ?, '2', ?)");
            $exp_total = $room_price;
            $insert->execute([$nextMonth . '-01', $rate_elec, $rate_water, $room_price, $exp_total, $ctr_id]);
        }
        $nextMonth = (new DateTime($nextMonth . '-01'))->modify('+1 month')->format('Y-m');
    }
}

// --- อัปเดตสถานะค้างชำระอัตโนมัติ ---
include __DIR__ . '/../Manage/auto_update_overdue.php';

// รับค่า sort
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$filterStatus = isset($_GET['filter_status']) ? trim((string)$_GET['filter_status']) : 'all';
$allowedStatuses = ['all', '0', '1', '2', '3', '4'];
if (!in_array($filterStatus, $allowedStatuses, true)) {
    $filterStatus = 'all';
}
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

// Available months
$availableMonths = [];
try {
    $monthStmt = $pdo->prepare("
      SELECT DISTINCT DATE_FORMAT(e.exp_month, '%Y-%m') AS month_key
      FROM expense e
      LEFT JOIN contract c ON e.ctr_id = c.ctr_id
      LEFT JOIN tenant_workflow tw ON tw.ctr_id = c.ctr_id
      WHERE c.ctr_status = '0'
        AND (COALESCE(tw.step_5_confirmed, 0) = 1 OR COALESCE(tw.current_step, 0) >= 5)
        AND e.exp_month IS NOT NULL
        AND DATE_FORMAT(e.exp_month, '%Y-%m') <= :currentMonth
      ORDER BY month_key DESC
    ");
    $monthStmt->execute([':currentMonth' => $currentMonth]);
    $availableMonths = $monthStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}

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

// Sync utility readings
if ($selectedMonth !== '' && preg_match('/^\d{4}-\d{2}$/', $selectedMonth) === 1) {
    [$syncYear, $syncMonth] = explode('-', $selectedMonth);
    $syncYearInt = (int)$syncYear;
    $syncMonthInt = (int)$syncMonth;

    try {
        $syncStmt = $pdo->prepare("
          SELECT e.exp_id, e.room_price, e.rate_elec, e.exp_status,
                 u.utl_water_start, u.utl_water_end, u.utl_elec_start, u.utl_elec_end
          FROM expense e
          LEFT JOIN utility u ON u.ctr_id = e.ctr_id AND YEAR(u.utl_date) = :syncYear AND MONTH(u.utl_date) = :syncMonth
          LEFT JOIN contract c ON e.ctr_id = c.ctr_id
          LEFT JOIN tenant_workflow tw ON tw.ctr_id = c.ctr_id
          WHERE DATE_FORMAT(e.exp_month, '%Y-%m') = :syncMonthKey
            AND c.ctr_status = '0'
            AND (COALESCE(tw.step_5_confirmed, 0) = 1 OR COALESCE(tw.current_step, 0) >= 5)
        ");
        $syncStmt->execute([':syncYear' => $syncYearInt, ':syncMonth' => $syncMonthInt, ':syncMonthKey' => $selectedMonth]);

        $updateExpenseStmt = $pdo->prepare("
          UPDATE expense SET
            exp_elec_unit = :expElecUnit, exp_water_unit = :expWaterUnit,
            exp_elec_chg = :expElecChg, exp_water = :expWater,
            exp_total = :expTotal, exp_status = :expStatus
          WHERE exp_id = :expId
        ");

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
            if (!$hasCompleteUtilityReading) {
                if ($currentStatus === '0' || $currentStatus === '2') {
                    $nextStatus = '2';
                }
            } elseif ($currentStatus === '2') {
                $nextStatus = '0';
            }
            $updateExpenseStmt->execute([
                ':expElecUnit' => $elecUsed, ':expWaterUnit' => $waterUsed,
                ':expElecChg' => $elecCost, ':expWater' => $waterCost,
                ':expTotal' => $expTotal, ':expStatus' => $nextStatus, ':expId' => (int)$row['exp_id'],
            ]);
        }
    } catch (PDOException $e) {}
}

// Query expenses
$expenseSql = "
  SELECT e.*, c.ctr_id, c.tnt_id, c.ctr_start, c.ctr_end, c.ctr_status,
         t.tnt_name, t.tnt_phone, r.room_number, r.room_id, rt.type_name
  FROM expense e
  LEFT JOIN contract c ON e.ctr_id = c.ctr_id
  LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
  LEFT JOIN room r ON c.room_id = r.room_id
  LEFT JOIN roomtype rt ON r.type_id = rt.type_id
  WHERE c.ctr_status = '0'
    AND EXISTS (
      SELECT 1 FROM tenant_workflow tw
      WHERE tw.ctr_id = c.ctr_id
        AND (COALESCE(tw.step_5_confirmed, 0) = 1 OR COALESCE(tw.current_step, 0) >= 5)
    )";

$expenseParams = [];
if ($selectedMonth !== '') {
    $expenseSql .= " AND DATE_FORMAT(e.exp_month, '%Y-%m') = :selectedMonth";
    $expenseParams[':selectedMonth'] = $selectedMonth;
}
$expenseSql .= " GROUP BY e.exp_id ORDER BY $orderBy";

$expStmt = $pdo->prepare($expenseSql);
$expStmt->execute($expenseParams);
$expenses = $expStmt->fetchAll(PDO::FETCH_ASSOC);

// Check meter missing
$meterMissingByExp = [];
$meterMissingRooms = [];
if ($selectedMonth !== '' && !empty($expenses)) {
    [$filterYear, $filterMon] = explode('-', $selectedMonth);
    $filterYearInt = (int)$filterYear;
    $filterMonInt = (int)$filterMon;

    $expByCtr = [];
    foreach ($expenses as $exp) {
        $expByCtr[(int)$exp['ctr_id']] = $exp;
    }

    if (!empty($expByCtr)) {
        $ctrIds = array_keys($expByCtr);
        $ph = implode(',', array_fill(0, count($ctrIds), '?'));
        $utilChkStmt = $pdo->prepare(
            "SELECT DISTINCT ctr_id FROM utility
             WHERE ctr_id IN ($ph) AND MONTH(utl_date) = ? AND YEAR(utl_date) = ?
             AND COALESCE(utl_water_end, 0) > 0 AND COALESCE(utl_elec_end, 0) > 0"
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
                    'tnt_name' => $exp['tnt_name'] ?? '-',
                    'month' => date('m/Y', strtotime((string)$exp['exp_month'])),
                ];
            }
        }
    }
}

// Payments
$paymentsByExp = [];
$paymentStmt = $pdo->query("
  SELECT exp_id, pay_id, pay_date, pay_amount, pay_status, pay_remark
  FROM payment WHERE pay_status = '1' AND TRIM(COALESCE(pay_remark, '')) <> 'มัดจำ'
  ORDER BY pay_date ASC
");
while ($pay = $paymentStmt->fetch(PDO::FETCH_ASSOC)) {
    $expId = (int)$pay['exp_id'];
    if (!isset($paymentsByExp[$expId])) {
        $paymentsByExp[$expId] = ['total_paid' => 0, 'count' => 0];
    }
    $paymentsByExp[$expId]['total_paid'] += (int)$pay['pay_amount'];
    $paymentsByExp[$expId]['count']++;
}

$paymentFlagsByExp = [];
try {
    $paymentFlagStmt = $pdo->query("
      SELECT exp_id,
        SUM(CASE WHEN pay_status = '0' AND pay_proof IS NOT NULL AND pay_proof <> '' AND TRIM(COALESCE(pay_remark, '')) <> 'มัดจำ' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN pay_status = '1' AND TRIM(COALESCE(pay_remark, '')) <> 'มัดจำ' THEN pay_amount ELSE 0 END) AS approved_amount
      FROM payment GROUP BY exp_id
    ");
    while ($row = $paymentFlagStmt->fetch(PDO::FETCH_ASSOC)) {
        $paymentFlagsByExp[(int)$row['exp_id']] = [
            'pending_count' => (int)($row['pending_count'] ?? 0),
            'approved_amount' => (int)($row['approved_amount'] ?? 0),
        ];
    }
} catch (PDOException $e) {}

// Status map
$statusMap = [
    '0' => 'รอชำระ', '1' => 'ชำระแล้ว', '2' => 'รอตรวจสอบ',
    '3' => 'ชำระยังไม่ครบ', '4' => 'ค้างชำระ',
];
$statusColors = [
    '0' => '#ef4444', '1' => '#22c55e', '2' => '#ff9800',
    '3' => '#f59e0b', '4' => '#dc2626',
];

$buildExpenseStatus = function(array $exp) use ($meterMissingByExp, $paymentFlagsByExp, $statusMap, $statusColors) {
    $expId = (int)($exp['exp_id'] ?? 0);
    $hasMeterMissing = !empty($meterMissingByExp[$expId]);
    $chargesPaid = (int)($paymentFlagsByExp[$expId]['approved_amount'] ?? 0);
    $pendingCount = (int)($paymentFlagsByExp[$expId]['pending_count'] ?? 0);
    $chargesTotal = (int)($exp['exp_total'] ?? 0);

    if ($hasMeterMissing) {
        $chargesRemain = max(0, $chargesTotal - $chargesPaid);
        return [
            'status' => '2', 'statusText' => 'ยังไม่ได้จดมิเตอร์',
            'statusColor' => '#ef4444', 'totalColor' => '#ef4444',
            'chargesPaid' => $chargesPaid, 'chargesTotal' => $chargesTotal, 'chargesRemain' => $chargesRemain,
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
    $dbStatus = (string)($exp['exp_status'] ?? '0');
    if ($dbStatus === '4' && in_array($status, ['0', '3'], true)) {
        $status = '4';
    }

    $statusText = $statusMap[$status] ?? 'ไม่ระบุ';
    if ($status === '0') $statusText = 'รอชำระ';
    elseif ($status === '3') $statusText = 'ชำระยังไม่ครบ';
    elseif ($status === '4') $statusText = 'ค้างชำระ';

    return [
        'status' => $status, 'statusText' => $statusText,
        'statusColor' => $statusColors[$status] ?? '#94a3b8',
        'totalColor' => in_array($status, ['0', '3', '4'], true) ? '#ef4444' : '#22c55e',
        'chargesPaid' => $chargesPaid, 'chargesTotal' => $chargesTotal, 'chargesRemain' => $chargesRemain,
    ];
};

// Build stats
$stats = [
    'unpaid' => 0, 'paid' => 0, 'pending' => 0, 'partial' => 0, 'overdue' => 0,
    'total_unpaid' => 0, 'total_paid' => 0, 'total_pending' => 0, 'total_partial' => 0, 'total_overdue' => 0,
];

// Build expense rows with computed status
$expenseRows = [];
foreach ($expenses as $exp) {
    $rs = $buildExpenseStatus($exp);
    $status = $rs['status'];
    $expTotal = (int)($exp['exp_total'] ?? 0);

    if ($status === '0') { $stats['unpaid']++; $stats['total_unpaid'] += $expTotal; }
    elseif ($status === '1') { $stats['paid']++; $stats['total_paid'] += $expTotal; }
    elseif ($status === '2') { $stats['pending']++; $stats['total_pending'] += $expTotal; }
    elseif ($status === '3') { $stats['partial']++; $stats['total_partial'] += $expTotal; }
    elseif ($status === '4') { $stats['overdue']++; $stats['total_overdue'] += $expTotal; }

    $expenseRows[] = [
        'exp_id' => (int)$exp['exp_id'],
        'room_number' => $exp['room_number'] ?? '-',
        'tnt_name' => $exp['tnt_name'] ?? '-',
        'exp_month' => $exp['exp_month'] ?? '',
        'exp_month_display' => $exp['exp_month'] ? thaiMonthYear($exp['exp_month']) : '-',
        'exp_month_key' => $exp['exp_month'] ? date('Y-m', strtotime((string)$exp['exp_month'])) : '',
        'exp_month_short' => $exp['exp_month'] ? date('m/Y', strtotime((string)$exp['exp_month'])) : '-',
        'room_price' => (int)($exp['room_price'] ?? 0),
        'exp_elec_unit' => (int)($exp['exp_elec_unit'] ?? 0),
        'exp_elec_chg' => (int)($exp['exp_elec_chg'] ?? 0),
        'exp_water_unit' => (int)($exp['exp_water_unit'] ?? 0),
        'exp_water' => (int)($exp['exp_water'] ?? 0),
        'exp_total' => $expTotal,
        'meterMissing' => !empty($meterMissingByExp[(int)$exp['exp_id']]),
        'status' => $rs['status'],
        'statusText' => $rs['statusText'],
        'statusColor' => $rs['statusColor'],
        'totalColor' => $rs['totalColor'],
        'chargesPaid' => $rs['chargesPaid'],
        'chargesTotal' => $rs['chargesTotal'],
        'chargesRemain' => $rs['chargesRemain'],
    ];
}

if ($filterStatus !== 'all') {
    $expenseRows = array_values(array_filter($expenseRows, static function(array $row) use ($filterStatus): bool {
        return (string)($row['status'] ?? '') === $filterStatus;
    }));
}

$totalAll = $stats['total_unpaid'] + $stats['total_paid'] + $stats['total_pending'] + $stats['total_partial'] + $stats['total_overdue'];
$collectionPct = $totalAll > 0 ? round(($stats['total_paid'] / $totalAll) * 100) : 0;
$pendingPartialCount = $stats['pending'] + $stats['partial'];
$pendingPartialTotal = $stats['total_pending'] + $stats['total_partial'];
$totalExpenseCount = $stats['unpaid'] + $stats['paid'] + $stats['pending'] + $stats['partial'] + $stats['overdue'];

// Build available months with Thai display
$monthOptions = [];
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
    $monthOptions[] = [
        'value' => (string)$monthKey,
        'label' => $monthText,
        'selected' => ((string)$selectedMonth === (string)$monthKey),
    ];
}

echo json_encode([
    'success' => true,
    'expenses' => $expenseRows,
    'stats' => $stats,
    'totalAll' => $totalAll,
    'collectionPct' => $collectionPct,
    'pendingPartialCount' => $pendingPartialCount,
    'pendingPartialTotal' => $pendingPartialTotal,
    'totalExpenseCount' => $totalExpenseCount,
    'selectedMonth' => $selectedMonth,
    'monthOptions' => $monthOptions,
    'meterMissingRooms' => $meterMissingRooms,
], JSON_UNESCAPED_UNICODE);
