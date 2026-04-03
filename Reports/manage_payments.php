<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Auto-heal: ถ้า Wizard ยืนยัน Step 2 แล้ว แต่ booking_payment ยังไม่ถูกอัปเดตสถานะ ให้ปรับเป็นยืนยันอัตโนมัติ
try {
  $pdo->exec("\n    UPDATE booking_payment bp\n    INNER JOIN tenant_workflow tw ON tw.bkg_id = bp.bkg_id\n    SET bp.bp_status = '1',\n        bp.bp_payment_date = COALESCE(bp.bp_payment_date, NOW())\n    WHERE COALESCE(tw.step_2_confirmed, 0) = 1\n      AND COALESCE(bp.bp_status, '0') <> '1'\n  ");
} catch (PDOException $e) {
  error_log('manage_payments auto-heal booking_payment error: ' . $e->getMessage());
}

// กรณีหมายเหตุเป็นมัดจำ ให้ยืนยันอัตโนมัติ ไม่ต้องให้ผู้ใช้กดยืนยันซ้ำ
try {
  $pdo->exec("\n    UPDATE payment\n    SET pay_status = '1'\n    WHERE COALESCE(pay_status, '0') = '0'\n      AND REPLACE(TRIM(COALESCE(pay_remark, '')), ' ', '') LIKE '%มัดจำ%'\n  ");
} catch (PDOException $e) {
  error_log('manage_payments auto-verify deposit error: ' . $e->getMessage());
}

// ดึง theme color จากการตั้งค่าระบบ
$settingsStmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'theme_color' LIMIT 1");
$themeColor = '#0f172a'; // ค่า default (dark mode)
if ($settingsStmt) {
    $theme = $settingsStmt->fetch(PDO::FETCH_ASSOC);
    if ($theme && !empty($theme['setting_value'])) {
        $themeColor = htmlspecialchars($theme['setting_value'], ENT_QUOTES, 'UTF-8');
    }
}

// ตรวจสอบว่าเป็น light theme หรือไม่ (คำนวณจากความสว่างของสี)
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

// ดึงข้อมูลการตั้งค่าธนาคาร (bank information)
$settings = [
    'bank_name' => '',
    'bank_account_name' => '',
    'bank_account_number' => '',
    'promptpay_number' => ''
];
try {
    $settingsQuery = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('bank_name','bank_account_name','bank_account_number','promptpay_number')");
    while ($row = $settingsQuery->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    // Handle error silently
}

$defaultViewMode = 'grid';
try {
  $viewStmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'default_view_mode' LIMIT 1");
  $viewValue = $viewStmt ? $viewStmt->fetchColumn() : false;
  if (is_string($viewValue) && strtolower($viewValue) === 'list') {
    $defaultViewMode = 'list';
  }
} catch (PDOException $e) {}

// รับค่า filter จาก query parameter — default to '' (ทั้งหมด = unverified)
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';
$filterMonth = isset($_GET['filter_month']) ? $_GET['filter_month'] : '';
$filterYear = isset($_GET['filter_year']) ? $_GET['filter_year'] : '';
$filterRoom = isset($_GET['room']) ? $_GET['room'] : '';

$isMonthAllRequest = isset($_GET['filter_month']) && (
  $_GET['filter_month'] === 'all' || trim((string)$_GET['filter_month']) === ''
);
if ($isMonthAllRequest) {
  $filterMonth = '';
}

$currentYearString = date('Y');
if ($filterYear === '') {
  $filterYear = $currentYearString;
}

// ดึงวันที่ออกบิลจาก settings (เหมือน manage_expenses.php)
$billingGenerateDaySetting = 1;
try {
  $bgdStmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'billing_generate_day' LIMIT 1");
  $bgdVal = $bgdStmt ? $bgdStmt->fetchColumn() : false;
  if ($bgdVal !== false && (int)$bgdVal > 0) {
    $billingGenerateDaySetting = (int)$bgdVal;
  }
} catch (PDOException $e) {}

$todayDay = (int)date('j');
$currentMonth = date('Y-m');
// ถ้ายังไม่ถึงวันออกบิล → ใช้เดือนก่อนหน้าเป็นเดือนล่าสุดที่แสดงได้
$effectiveCurrentMonth = ($todayDay >= $billingGenerateDaySetting)
    ? $currentMonth
    : (new DateTime($currentMonth . '-01'))->modify('-1 month')->format('Y-m');

$latestMonthKey = '';
try {
  $latestMonthStmt = $pdo->query("
    SELECT month_key
    FROM (
      SELECT DATE_FORMAT(p.pay_date, '%Y-%m') AS month_key
      FROM payment p
      WHERE p.pay_date IS NOT NULL
      UNION
      SELECT DATE_FORMAT(c.ctr_start, '%Y-%m') AS month_key
      FROM booking_payment bp
      INNER JOIN booking b ON b.bkg_id = bp.bkg_id
      LEFT JOIN tenant_workflow tw ON tw.bkg_id = b.bkg_id
      LEFT JOIN contract c ON c.ctr_id = tw.ctr_id
      WHERE COALESCE(bp.bp_status, '0') = '1'
        AND bp.bp_id <> 770043117
        AND c.ctr_start IS NOT NULL
      UNION
      SELECT DATE_FORMAT(e.exp_month, '%Y-%m') AS month_key
      FROM expense e
      INNER JOIN contract c ON e.ctr_id = c.ctr_id
      WHERE c.ctr_status = '0'
        AND e.exp_month IS NOT NULL
        AND DATE_FORMAT(e.exp_month, '%Y-%m') > DATE_FORMAT(c.ctr_start, '%Y-%m')
        AND DATE_FORMAT(e.exp_month, '%Y-%m') <= '{$effectiveCurrentMonth}'
    ) latest_months
    WHERE month_key IS NOT NULL AND month_key <> ''
    ORDER BY month_key DESC
    LIMIT 1
  ");
  $latestMonthKey = (string)($latestMonthStmt ? $latestMonthStmt->fetchColumn() : '');
} catch (PDOException $e) {
  $latestMonthKey = '';
}

if (!$isMonthAllRequest && $filterMonth === '' && preg_match('/^\d{4}-\d{2}$/', $latestMonthKey) === 1) {
  [$latestYear, $latestMonth] = explode('-', $latestMonthKey);
  if ($filterYear === $latestYear || $filterYear === '') {
    $filterYear = $latestYear;
    $filterMonth = (string)((int)$latestMonth);
  }
}

// ดึงข้อมูลการชำระเงิน
$sql = "
    SELECT p.*,
           e.exp_id, e.exp_month, e.exp_total, e.exp_status,
           c.ctr_id, c.room_id,
           t.tnt_id, t.tnt_name, t.tnt_phone,
           r.room_number,
           rt.type_name
    FROM payment p
    LEFT JOIN expense e ON p.exp_id = e.exp_id
    LEFT JOIN contract c ON e.ctr_id = c.ctr_id
    LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
    LEFT JOIN room r ON c.room_id = r.room_id
    LEFT JOIN roomtype rt ON r.type_id = rt.type_id
    ORDER BY p.pay_id DESC
";

$stmt = $pdo->prepare($sql);
  $stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Merge verified booking deposits (ค่ามัดจำ) จาก booking_payment เพื่อให้แสดงในหน้า manage_payments
try {
  // รวบรวม room_number ที่มี payment.pay_remark = 'มัดจำ' อยู่แล้ว เพื่อกัน duplicate
  $existingDepositRooms = [];
  foreach ($payments as $p) {
    if (!empty($p['room_number']) && strpos((string)($p['pay_remark'] ?? ''), 'มัดจำ') !== false) {
      $existingDepositRooms[$p['room_number']] = true;
    }
  }

  $depositRows = $pdo->query("
      SELECT
        bp.bp_id,
        bp.bp_amount,
        bp.bp_status,
        bp.bp_proof,
        b.bkg_date,
        c.ctr_start,
        t.tnt_id,
        t.tnt_name,
        t.tnt_phone,
        r.room_number
      FROM booking_payment bp
      INNER JOIN booking b ON b.bkg_id = bp.bkg_id
      LEFT JOIN tenant_workflow tw ON tw.bkg_id = b.bkg_id
      LEFT JOIN contract c ON c.ctr_id = tw.ctr_id
      LEFT JOIN tenant t ON b.tnt_id = t.tnt_id
      LEFT JOIN room r ON b.room_id = r.room_id
      WHERE COALESCE(bp.bp_status, '0') = '1'
        AND bp.bp_id <> 770043117
      ORDER BY bp.bp_id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

  foreach ($depositRows as $dep) {
    // ข้าม BP entry ถ้า room นั้นมี payment.pay_remark = 'มัดจำ' อยู่แล้ว (ป้องกัน duplicate)
    $depRoomNumber = (string)($dep['room_number'] ?? '');
    if ($depRoomNumber !== '' && !empty($existingDepositRooms[$depRoomNumber])) {
      continue;
    }

    $depDate = !empty($dep['ctr_start']) ? (string)$dep['ctr_start'] : (string)($dep['bkg_date'] ?? '');
    if ($depDate === '') {
      $depDate = date('Y-m-d H:i:s');
    }

    $depMonth = date('n', strtotime($depDate));
    $depYear = date('Y', strtotime($depDate));

    $payments[] = [
      'pay_id' => -(int)$dep['bp_id'],
      'display_pay_id' => 'BP' . (int)$dep['bp_id'],
      'exp_id' => null,
      'exp_month' => null,
      'exp_total' => (int)($dep['bp_amount'] ?? 0),
      'exp_status' => '1',
      'ctr_id' => null,
      'room_id' => null,
      'tnt_id' => $dep['tnt_id'] ?? null,
      'tnt_name' => $dep['tnt_name'] ?? '-',
      'tnt_phone' => $dep['tnt_phone'] ?? '-',
      'room_number' => $dep['room_number'] ?? '-',
      'type_name' => null,
      'pay_date' => $depDate,
      'pay_amount' => (int)($dep['bp_amount'] ?? 0),
      'pay_proof' => $dep['bp_proof'] ?? null,
      'pay_status' => '1',
      'pay_remark' => 'ค่ามัดจำ (เงินจอง)',
      'payment_source' => 'booking_deposit',
    ];
  }
} catch (PDOException $e) {
  error_log('manage_payments merge booking deposit error: ' . $e->getMessage());
}

// Merge unpaid monthly bills so rooms appear in the payments page even before tenants submit proof.
try {
  $unpaidBillRows = $pdo->query("\n      SELECT
        e.exp_id,
        e.exp_month,
        e.exp_total,
        e.exp_status,
        c.ctr_id,
        c.room_id,
        t.tnt_id,
        t.tnt_name,
        t.tnt_phone,
        r.room_number,
        rt.type_name
      FROM expense e
      INNER JOIN (
        SELECT MAX(exp_id) AS exp_id
        FROM expense
        GROUP BY ctr_id, DATE_FORMAT(exp_month, '%Y-%m')
      ) latest_expense ON latest_expense.exp_id = e.exp_id
      INNER JOIN contract c ON e.ctr_id = c.ctr_id
      LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
      LEFT JOIN room r ON c.room_id = r.room_id
      LEFT JOIN roomtype rt ON r.type_id = rt.type_id
      LEFT JOIN payment p ON p.exp_id = e.exp_id
      WHERE c.ctr_status = '0'
        AND p.exp_id IS NULL
        AND DATE_FORMAT(e.exp_month, '%Y-%m') > DATE_FORMAT(c.ctr_start, '%Y-%m')
        AND DATE_FORMAT(e.exp_month, '%Y-%m') <= '{$effectiveCurrentMonth}'
      ORDER BY e.exp_month DESC, CAST(r.room_number AS UNSIGNED) ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

  foreach ($unpaidBillRows as $row) {
    $expMonth = (string)($row['exp_month'] ?? '');
    $monthTs = $expMonth !== '' ? strtotime($expMonth) : false;
    $expMonthInt = $monthTs ? (int)date('n', $monthTs) : null;
    $expYearInt = $monthTs ? (int)date('Y', $monthTs) : null;

    $payments[] = [
      'pay_id' => -1000000 - (int)$row['exp_id'],
      'display_pay_id' => 'EXP' . (int)$row['exp_id'],
      'exp_id' => (int)$row['exp_id'],
      'exp_month' => $row['exp_month'],
      'exp_total' => (int)($row['exp_total'] ?? 0),
      'exp_status' => (string)($row['exp_status'] ?? '0'),
      'ctr_id' => (int)($row['ctr_id'] ?? 0),
      'room_id' => (int)($row['room_id'] ?? 0),
      'tnt_id' => $row['tnt_id'] ?? null,
      'tnt_name' => $row['tnt_name'] ?? '-',
      'tnt_phone' => $row['tnt_phone'] ?? '-',
      'room_number' => $row['room_number'] ?? '-',
      'type_name' => $row['type_name'] ?? null,
      'pay_date' => null,
      'pay_amount' => (int)($row['exp_total'] ?? 0),
      'pay_proof' => null,
      'pay_status' => 'unpaid',
      'pay_remark' => 'ยังไม่มีการแจ้งชำระ',
      'payment_source' => 'unpaid_expense',
    ];
  }
} catch (PDOException $e) {
  error_log('manage_payments merge unpaid bills error: ' . $e->getMessage());
}

usort($payments, static function(array $a, array $b): int {
  $aTime = strtotime((string)($a['pay_date'] ?? $a['exp_month'] ?? '')) ?: 0;
  $bTime = strtotime((string)($b['pay_date'] ?? $b['exp_month'] ?? '')) ?: 0;
  if ($aTime === $bTime) {
    return ((int)($b['pay_id'] ?? 0)) <=> ((int)($a['pay_id'] ?? 0));
  }
  return $bTime <=> $aTime;
});

// ดึงค่าใช้จ่ายที่รอชำระและชำระยังไม่ครบ (สำหรับ dropdown ในฟอร์ม)
$unpaidExpenses = $pdo->query("
    SELECT e.exp_id, e.exp_month, e.exp_total, e.exp_status,
       e.room_price, e.exp_water, e.exp_elec_chg,
           c.ctr_id,
           t.tnt_name,
           r.room_number
    FROM expense e
    LEFT JOIN contract c ON e.ctr_id = c.ctr_id
    LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
    LEFT JOIN room r ON c.room_id = r.room_id
    WHERE e.exp_status IN ('0', '3', '4')
    ORDER BY r.room_number, e.exp_month DESC
")->fetchAll(PDO::FETCH_ASSOC);

$statusMap = [
    '0' => 'รอตรวจสอบ',
    '1' => 'ตรวจสอบแล้ว',
  'unpaid' => 'รอชำระ',
];
$statusColors = [
    '0' => '#fbbf24',
    '1' => '#22c55e',
];

// คำนวณสถิติ
$stats = [
    'pending' => 0,
    'verified' => 0,
    'total_pending' => 0,
    'total_verified' => 0,
];
foreach ($payments as $pay) {
  if ($pay['pay_status'] === '1') {
        $stats['verified']++;
        $stats['total_verified'] += (int)($pay['pay_amount'] ?? 0);
  } else {
    $stats['pending']++;
    $stats['total_pending'] += (int)($pay['pay_amount'] ?? 0);
    }
}

// Helper: ดึงเดือน/ปีของ payment (ใช้ exp_month ก่อน ถ้าไม่มีใช้ pay_date)
$getPayMonthYear = static function(array $pay): array {
    $src = !empty($pay['exp_month']) ? (string)$pay['exp_month'] : (string)($pay['pay_date'] ?? '');
    $ts  = $src !== '' ? strtotime($src) : false;
    return $ts !== false
        ? [(string)((int)date('n', $ts)), date('Y', $ts)]
        : ['', ''];
};

// ตัวเลขแยกตามสถานะ — กรองเดือน/ปีด้วยถ้ามี filter ใช้งานอยู่
$pendingOnlyCount = 0;
$unpaidOnlyCount  = 0;
$pendingOnlyTotal = 0;
$unpaidOnlyTotal  = 0;
$verifiedFilteredCount = 0;
foreach ($payments as $pay) {
    [$payM, $payY] = $getPayMonthYear($pay);
    $monthMatch = ($filterMonth === '' || $payM === $filterMonth);
    $yearMatch  = ($filterYear  === '' || $payY === $filterYear);
    if (!$monthMatch || !$yearMatch) continue;

    if (($pay['pay_status'] ?? '') === '0') {
        $pendingOnlyCount++;
        $pendingOnlyTotal += (int)($pay['pay_amount'] ?? 0);
    } elseif (($pay['pay_status'] ?? '') === 'unpaid') {
        $unpaidOnlyCount++;
        $unpaidOnlyTotal += (int)($pay['pay_amount'] ?? 0);
    } elseif (($pay['pay_status'] ?? '') === '1') {
        $verifiedFilteredCount++;
    }
}
$totalPaymentCount = count($payments);
$verifiedPct = $totalPaymentCount > 0 ? round(($stats['verified'] / $totalPaymentCount) * 100) : 0;

// ดึงค่าตั้งค่าระบบ
$siteName = 'Sangthian Dormitory';
$logoFilename = 'Logo.jpg';
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
        if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
    }
} catch (PDOException $e) {}

// ดึงเดือนที่มีข้อมูล
$availableMonths = $pdo->query("
    SELECT DISTINCT month_key
    FROM (
      SELECT DATE_FORMAT(p.pay_date, '%Y-%m') AS month_key
      FROM payment p
      WHERE p.pay_date IS NOT NULL
      UNION
      SELECT DATE_FORMAT(c.ctr_start, '%Y-%m') AS month_key
      FROM booking_payment bp
      INNER JOIN booking b ON b.bkg_id = bp.bkg_id
      LEFT JOIN tenant_workflow tw ON tw.bkg_id = b.bkg_id
      LEFT JOIN contract c ON c.ctr_id = tw.ctr_id
      WHERE COALESCE(bp.bp_status, '0') = '1'
        AND bp.bp_id <> 770043117
        AND c.ctr_start IS NOT NULL
      UNION
      SELECT DATE_FORMAT(e.exp_month, '%Y-%m') AS month_key
      FROM expense e
      INNER JOIN contract c ON e.ctr_id = c.ctr_id
      WHERE c.ctr_status = '0'
        AND e.exp_month IS NOT NULL
        AND DATE_FORMAT(e.exp_month, '%Y-%m') > DATE_FORMAT(c.ctr_start, '%Y-%m')
        AND DATE_FORMAT(e.exp_month, '%Y-%m') <= '{$effectiveCurrentMonth}'
    ) m
    ORDER BY month_key DESC
")->fetchAll(PDO::FETCH_COLUMN);

  $availableYearOptions = [];
  $availableMonthOptions = [];
  foreach ($availableMonths as $monthKey) {
    if (!is_string($monthKey) || preg_match('/^\d{4}-\d{2}$/', $monthKey) !== 1) {
      continue;
    }

    [$yearPart, $monthPart] = explode('-', $monthKey);
    $yearPart = (string)((int)$yearPart);
    $monthInt = (int)$monthPart;

    $availableYearOptions[$yearPart] = true;
    $availableMonthOptions[(string)$monthInt] = true;
  }

  $availableYearOptions = array_keys($availableYearOptions);
  rsort($availableYearOptions, SORT_NUMERIC);

  $availableMonthOptions = array_keys($availableMonthOptions);
  sort($availableMonthOptions, SORT_NUMERIC);

// ดึงสรุปการชำระเงินแยกตามห้อง
$roomPaymentSummary = $pdo->query("
    SELECT 
        r.room_id,
        r.room_number,
        MAX(t.tnt_name) as tnt_name,
        COUNT(p.pay_id) as payment_count,
        SUM(CASE WHEN p.pay_status = '1' THEN p.pay_amount ELSE 0 END) as total_verified,
        SUM(CASE WHEN p.pay_status = '0' THEN p.pay_amount ELSE 0 END) as total_pending,
        MAX(p.pay_date) as last_payment_date
    FROM room r
    LEFT JOIN contract c ON r.room_id = c.room_id AND c.ctr_status = '0'
    LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
    LEFT JOIN expense e ON c.ctr_id = e.ctr_id
    LEFT JOIN payment p ON e.exp_id = p.exp_id
    WHERE c.ctr_id IS NOT NULL
    GROUP BY r.room_id, r.room_number
    ORDER BY r.room_number ASC
")->fetchAll(PDO::FETCH_ASSOC);

$filterRoomOptions = [];
foreach ($roomPaymentSummary as $room) {
  $roomNumber = trim((string)($room['room_number'] ?? ''));
  if ($roomNumber === '') {
    continue;
  }
  $filterRoomOptions[$roomNumber] = true;
}
$filterRoomOptions = array_keys($filterRoomOptions);
natsort($filterRoomOptions);
$filterRoomOptions = array_values($filterRoomOptions);
?>
<!doctype html>
<html lang="th" class="<?php echo $lightThemeClass; ?>">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - จัดการการชำระเงิน</title>
    <?php include __DIR__ . '/../includes/sidebar_toggle.php'; ?>
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

      /* ===== Comprehensive Light Theme Overrides ===== */
      html.light-theme .manage-panel,
      html.light-theme .section-header,
      html.light-theme .panel-title h1,
      html.light-theme .panel-title p,
      html.light-theme .section-title h3,
      html.light-theme .section-title p,
      html.light-theme .title-block h1,
      html.light-theme .title-block p,
      html.light-theme .report-table,
      html.light-theme .report-table th,
      html.light-theme .report-table td,
      html.light-theme label,
      html.light-theme select,
      html.light-theme input:not([type="submit"]):not([type="button"]),
      html.light-theme textarea {
        color: #111827 !important;
      }

      /* Buttons with gradient background - keep white text - HIGH SPECIFICITY */
      html.light-theme button[type="submit"],
      html.light-theme button[type="submit"] *,
      html.light-theme button.submit-btn-animated,
      html.light-theme button.submit-btn-animated *,
      html.light-theme .btn.btn-primary,
      html.light-theme .btn.btn-primary * {
        color: #ffffff !important;
      }
      html.light-theme button[type="submit"] svg,
      html.light-theme button.submit-btn-animated svg,
      html.light-theme .btn.btn-primary svg {
        stroke: #ffffff !important;
        color: #ffffff !important;
      }

      html.light-theme .manage-panel {
        background: rgba(255,255,255,0.7) !important;
        border-color: rgba(0,0,0,0.08) !important;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08) !important;
      }

      html.light-theme .manage-panel svg {
        stroke: #111827 !important;
      }

      /* Payment form section - keep dark background and white text */
      html.light-theme .payment-form-section {
        background: linear-gradient(135deg, rgba(15,23,42,0.95), rgba(2,6,23,0.95)) !important;
      }
      html.light-theme .payment-form-section,
      html.light-theme .payment-form-section *,
      html.light-theme .payment-form-section label,
      html.light-theme .payment-form-section span,
      html.light-theme .payment-form-section h3 {
        color: #f8fafc !important;
      }
      html.light-theme .payment-form-section svg {
        stroke: #f8fafc !important;
      }
      html.light-theme .payment-form-section input:not([type="submit"]):not([type="button"]),
      html.light-theme .payment-form-section select {
        color: #111827 !important;
        background: rgba(255,255,255,0.9) !important;
      }
      /* Submit button - force green background and white text */
      html.light-theme .payment-form-section button[type="submit"],
      html.light-theme .payment-form-section #submitPaymentBtn,
      html.light-theme #submitPaymentBtn {
        background: linear-gradient(135deg,#22c55e,#16a34a) !important;
        color: #ffffff !important;
      }
      html.light-theme .payment-form-section button[type="submit"] span,
      html.light-theme .payment-form-section button[type="submit"] *,
      html.light-theme #submitPaymentBtn span,
      html.light-theme #submitPaymentBtn * {
        color: #ffffff !important;
      }
      html.light-theme .payment-form-section button[type="submit"] svg,
      html.light-theme #submitPaymentBtn svg {
        stroke: #ffffff !important;
      }

      /* HIGHEST SPECIFICITY: Target by ID */
      html.light-theme #addPaymentSection button[type="submit"],
      html.light-theme #addPaymentSection button[type="submit"] *,
      html.light-theme #addPaymentSection .submit-btn-animated,
      html.light-theme #addPaymentSection .submit-btn-animated * {
        color: #ffffff !important;
      }
      html.light-theme #addPaymentSection button[type="submit"] svg,
      html.light-theme #addPaymentSection .submit-btn-animated svg {
        stroke: #ffffff !important;
        fill: none !important;
      }

      /* Exception: buttons with colored background keep white icons */
      html.light-theme .manage-panel button[type="submit"] svg,
      html.light-theme .manage-panel button.submit-btn-animated svg,
      html.light-theme .manage-panel .btn.btn-primary svg,
      html.light-theme .manage-panel button[style*="background:linear-gradient"] svg,
      html.light-theme .manage-panel button[style*="background: linear-gradient"] svg,
      html.light-theme .manage-panel .status-badge svg {
        stroke: #ffffff !important;
      }
      html.light-theme .manage-panel button[type="submit"],
      html.light-theme .manage-panel button[type="submit"] span,
      html.light-theme .manage-panel button.submit-btn-animated,
      html.light-theme .manage-panel button.submit-btn-animated span,
      html.light-theme .manage-panel .btn.btn-primary,
      html.light-theme .manage-panel .btn.btn-primary span {
        color: #ffffff !important;
      }

      /* Keep white icons on colored backgrounds */
      html.light-theme .panel-icon-modern svg,
      html.light-theme .panel-icon-animated svg,
      html.light-theme .payment-info-icon svg,
      html.light-theme .stat-card-icon svg,
      html.light-theme .icon-circle svg {
        stroke: #ffffff !important;
      }

      /* Section header styles */
      html.light-theme .section-header .title-block h1 { color: #1e293b !important; }
      html.light-theme .section-header .title-block p { color: #64748b !important; }

      /* Filter inputs */
      html.light-theme .filter-select,
      html.light-theme select {
        background: rgba(0,0,0,0.05) !important;
        border-color: rgba(0,0,0,0.12) !important;
        color: #111827 !important;
      }

      /* Action buttons - keep text white on colored backgrounds */
      html.light-theme .btn-modern,
      html.light-theme .btn-action,
      html.light-theme button[style*="background:linear-gradient"],
      html.light-theme button[style*="background: linear-gradient"] {
        color: #ffffff !important;
      }
      html.light-theme .btn-modern svg,
      html.light-theme .btn-action svg,
      html.light-theme button[style*="background:linear-gradient"] svg,
      html.light-theme button[style*="background: linear-gradient"] svg {
        stroke: #ffffff !important;
      }

      /* Outline/ghost buttons - dark text */
      html.light-theme button[style*="background:rgba(239,68,68"],
      html.light-theme button[style*="background: rgba(239,68,68"] {
        color: #dc2626 !important;
      }
      html.light-theme button[style*="background:rgba(239,68,68"] svg,
      html.light-theme button[style*="background: rgba(239,68,68"] svg {
        stroke: #dc2626 !important;
      }

      /* Status badges - solid background with white text in light theme */
      html.light-theme .status-badge.status-pending {
        background: linear-gradient(135deg, #f59e0b, #d97706) !important;
        color: #ffffff !important;
        border: none !important;
      }
      html.light-theme .status-badge.status-verified {
        background: linear-gradient(135deg, #22c55e, #16a34a) !important;
        color: #ffffff !important;
        border: none !important;
      }
      html.light-theme .status-badge svg {
        stroke: #ffffff !important;
      }

      /* Submit button - ensure visible */
      html.light-theme .submit-btn-animated,
      html.light-theme button[type="submit"] {
        color: #ffffff !important;
      }
      html.light-theme .submit-btn-animated svg,
      html.light-theme button[type="submit"] svg {
        stroke: #ffffff !important;
      }

      /* Table action buttons with specific styling */
      html.light-theme .crud-actions button {
        color: #ffffff !important;
      }
      html.light-theme .crud-actions button svg {
        stroke: #ffffff !important;
      }

      /* DataTable specific */
      html.light-theme .datatable-table th,
      html.light-theme .datatable-table td {
        color: #111827 !important;
      }
      html.light-theme .datatable-input,
      html.light-theme .datatable-selector {
        background: rgba(0,0,0,0.03) !important;
        border-color: rgba(0,0,0,0.1) !important;
        color: #111827 !important;
      }
      html.light-theme .datatable-info,
      html.light-theme .datatable-pagination a {
        color: #374151 !important;
      }

      /* Modern panel + animations */
      .manage-panel {
        background: linear-gradient(135deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));
        border-radius: 14px;
        padding: 1rem 1.25rem;
        margin-bottom: 1rem;
        border: 1px solid rgba(255,255,255,0.04);
        box-shadow: 0 8px 30px rgba(2,6,23,0.6);
        backdrop-filter: blur(6px);
      }

      .section-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        justify-content: space-between;
      }

      .section-header .title-block {
        display:flex; align-items:center; gap:0.75rem;
      }

      .panel-icon-modern {
        width:56px; height:56px; display:flex; align-items:center; justify-content:center; border-radius:12px;
        background: linear-gradient(135deg,#8b5cf6,#7c3aed);
        box-shadow: 0 8px 25px rgba(124,58,237,0.2);
        flex-shrink:0;
      }

      .panel-icon-modern svg { width:28px; height:28px; color:white; }

      /* small animated coin */
      .coin-animated { transform-origin: center; transition: transform 0.4s ease; }
      .coin-animated.spin { animation: spin 2.5s linear infinite; }

      @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
      }

      /* Entrance animations for cards */
      .fade-in-up { opacity:0; transform: translateY(10px); animation: fadeInUp 0.6s forwards; }
      @keyframes fadeInUp { to { opacity:1; transform: translateY(0); } }


      /* Toast fallback สำหรับหน้านี้ */
      #toast-container {
        position: fixed;
        top: 1.25rem;
        right: 1.25rem;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        z-index: 99999;
      }
      .toast {
        min-width: 240px;
        max-width: 320px;
        padding: 0.9rem 1rem;
        border-radius: 10px;
        color: #0f172a;
        background: #f8fafc;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        display: flex;
        align-items: center;
        gap: 0.6rem;
        font-weight: 600;
      }
      .toast-success { border-left: 6px solid #22c55e; }
      .toast-error { border-left: 6px solid #ef4444; }
      .toast small { font-weight: 500; color: #1f2937; }

      /* ===== Modern Payment Info Section with Animations ===== */
      .payment-info-section {
        position: relative;
        background: linear-gradient(135deg, rgba(99,102,241,0.08), rgba(139,92,246,0.05));
        border: 1px solid rgba(99,102,241,0.15);
        border-radius: 20px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        overflow: hidden;
      }

      .payment-info-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.25rem;
        position: relative;
        z-index: 2;
      }

      .payment-info-icon {
        width: 52px;
        height: 52px;
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 8px 25px rgba(139,92,246,0.35);
        flex-shrink: 0;
      }

      .payment-info-icon svg {
        width: 26px;
        height: 26px;
        stroke: white;
      }

      /* Wallet animation */
      .wallet-animated {
        animation: walletBounce 3s ease-in-out infinite;
      }

      @keyframes walletBounce {
        0%, 100% { transform: translateY(0) rotate(0deg); }
        25% { transform: translateY(-3px) rotate(-3deg); }
        75% { transform: translateY(-3px) rotate(3deg); }
      }

      .coin-pulse {
        animation: coinPulse 2s ease-in-out infinite;
        transform-origin: center;
      }

      @keyframes coinPulse {
        0%, 100% { r: 2; opacity: 1; }
        50% { r: 2.5; opacity: 0.7; }
      }

      .payment-info-title h4 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 700;
        color: #f8fafc;
        letter-spacing: -0.01em;
      }

      .payment-info-title p {
        margin: 0.25rem 0 0 0;
        font-size: 0.85rem;
        color: rgba(255,255,255,0.5);
      }

      .payment-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
        position: relative;
        z-index: 2;
      }

      .payment-info-card {
        position: relative;
        background: rgba(255,255,255,0.03);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 16px;
        padding: 1rem 1.25rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        overflow: hidden;
      }

      .payment-info-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--card-accent, #3b82f6);
        opacity: 0;
        transition: opacity 0.3s ease;
      }

      .payment-info-card:hover {
        transform: translateY(-4px) scale(1.02);
        border-color: rgba(255,255,255,0.15);
        box-shadow: 0 15px 35px rgba(0,0,0,0.2);
      }

      .payment-info-card:hover::before {
        opacity: 1;
      }

      .payment-info-card.copyable {
        cursor: pointer;
      }

      .payment-info-card.copyable:hover {
        background: rgba(255,255,255,0.06);
      }

      .payment-info-card.copyable:active {
        transform: translateY(-2px) scale(1);
      }

      .card-icon-wrapper {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: transform 0.3s ease;
      }

      .payment-info-card:hover .card-icon-wrapper {
        transform: scale(1.1) rotate(-5deg);
      }

      .card-icon-wrapper svg {
        width: 22px;
        height: 22px;
        stroke: white;
      }

      .card-icon-wrapper.blue {
        background: linear-gradient(135deg, #3b82f6, #60a5fa);
        box-shadow: 0 6px 15px rgba(59,130,246,0.3);
      }

      .card-icon-wrapper.purple {
        background: linear-gradient(135deg, #8b5cf6, #a78bfa);
        box-shadow: 0 6px 15px rgba(139,92,246,0.3);
      }

      .card-icon-wrapper.green {
        background: linear-gradient(135deg, #10b981, #34d399);
        box-shadow: 0 6px 15px rgba(16,185,129,0.3);
      }

      .card-icon-wrapper.orange {
        background: linear-gradient(135deg, #f59e0b, #fbbf24);
        box-shadow: 0 6px 15px rgba(245,158,11,0.3);
      }

      /* Icon float animation */
      .icon-float {
        animation: iconFloat 3s ease-in-out infinite;
      }

      @keyframes iconFloat {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-3px); }
      }

      .card-content {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
        flex: 1;
        min-width: 0;
      }

      .card-label {
        font-size: 0.75rem;
        font-weight: 500;
        color: rgba(255,255,255,0.5);
        text-transform: uppercase;
        letter-spacing: 0.05em;
      }

      .card-value {
        font-size: 1rem;
        font-weight: 600;
        color: #f1f5f9;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        word-break: break-all;
      }

      .card-value .not-set {
        color: rgba(255,255,255,0.3);
        font-weight: 500;
        font-style: italic;
      }

      .copy-icon {
        width: 16px;
        height: 16px;
        stroke: rgba(255,255,255,0.4);
        flex-shrink: 0;
        transition: all 0.2s ease;
      }

      .payment-info-card.copyable:hover .copy-icon {
        stroke: rgba(255,255,255,0.8);
        transform: scale(1.1);
      }

      /* Card accent colors */
      .bank-card { --card-accent: #3b82f6; }
      .account-card { --card-accent: #8b5cf6; }
      .number-card { --card-accent: #10b981; }
      .promptpay-card { --card-accent: #f59e0b; }

      /* Card glow effect */
      .card-glow {
        position: absolute;
        bottom: -50%;
        right: -50%;
        width: 100%;
        height: 100%;
        border-radius: 50%;
        opacity: 0;
        transition: opacity 0.3s ease;
        pointer-events: none;
      }

      .card-glow.blue { background: radial-gradient(circle, rgba(59,130,246,0.15), transparent 70%); }
      .card-glow.purple { background: radial-gradient(circle, rgba(139,92,246,0.15), transparent 70%); }
      .card-glow.green { background: radial-gradient(circle, rgba(16,185,129,0.15), transparent 70%); }
      .card-glow.orange { background: radial-gradient(circle, rgba(245,158,11,0.15), transparent 70%); }

      .payment-info-card:hover .card-glow {
        opacity: 1;
      }

      /* Info section floating particles */
      .info-particles {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        overflow: hidden;
        z-index: 1;
      }

      .info-particles span {
        position: absolute;
        width: 6px;
        height: 6px;
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        border-radius: 50%;
        opacity: 0.3;
        animation: particleFloat 5s ease-in-out infinite;
      }

      .info-particles span:nth-child(1) { left: 10%; top: 20%; animation-delay: 0s; }
      .info-particles span:nth-child(2) { left: 30%; top: 70%; animation-delay: 1.2s; }
      .info-particles span:nth-child(3) { left: 70%; top: 30%; animation-delay: 2.4s; }
      .info-particles span:nth-child(4) { left: 85%; top: 60%; animation-delay: 3.6s; }

      @keyframes particleFloat {
        0%, 100% { transform: translateY(0) scale(1); opacity: 0.3; }
        50% { transform: translateY(-15px) scale(1.2); opacity: 0.6; }
      }

      /* Light theme overrides for payment info section */
      html.light-theme .payment-info-section {
        background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(139,92,246,0.03));
        border-color: rgba(99,102,241,0.1);
      }

      html.light-theme .payment-info-title h4 {
        color: #1e293b;
      }

      html.light-theme .payment-info-title p {
        color: rgba(0,0,0,0.5);
      }

      html.light-theme .payment-info-card {
        background: rgba(255,255,255,0.7);
        border-color: rgba(0,0,0,0.06);
      }

      html.light-theme .card-label {
        color: rgba(0,0,0,0.5);
      }

      html.light-theme .card-value {
        color: #1e293b;
      }

      html.light-theme .card-value .not-set {
        color: rgba(0,0,0,0.3);
      }

      /* ===== Panel Icon Animations ===== */
      .panel-header {
        display: flex;
        align-items: center;
        gap: 1rem;
      }

      .panel-icon-animated {
        width: 56px;
        height: 56px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: transform 0.3s ease;
      }

      .panel-icon-animated:hover {
        transform: scale(1.1) rotate(-5deg);
      }

      .panel-icon-animated svg {
        width: 28px;
        height: 28px;
        stroke: white;
      }

      .panel-icon-animated.add-payment {
        background: linear-gradient(135deg, #22c55e, #16a34a);
        box-shadow: 0 8px 25px rgba(34,197,94,0.35);
      }

      /* Plus icon animation */
      .plus-animated {
        animation: plusPulse 2.5s ease-in-out infinite;
      }

      @keyframes plusPulse {
        0%, 100% { transform: scale(1) rotate(0deg); }
        25% { transform: scale(1.1) rotate(90deg); }
        50% { transform: scale(1) rotate(90deg); }
        75% { transform: scale(1.1) rotate(0deg); }
      }

      .panel-title-content h2 {
        letter-spacing: -0.01em;
      }

      /* Payment form section styling */
      .payment-form-section {
        position: relative;
        overflow: hidden;
      }

      .payment-form-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #22c55e, #10b981, #14b8a6);
        opacity: 0;
        transition: opacity 0.3s ease;
      }

      .payment-form-section:hover::before {
        opacity: 1;
      }

      /* ===== End Panel Icon Animations ===== */

      /* Toggle Form Button Styling */
      .toggle-form-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.85rem 1.5rem;
        background: rgba(255,255,255,0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 12px;
        color: rgba(255,255,255,0.8);
        font-size: 0.95rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        white-space: nowrap;
      }

      .toggle-form-btn:hover {
        background: rgba(255,255,255,0.1);
        border-color: rgba(255,255,255,0.2);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.2);
      }

      .toggle-form-btn .toggle-icon {
        width: 18px;
        height: 18px;
        transition: transform 0.3s ease;
      }

      .toggle-form-btn.collapsed .toggle-icon {
        transform: rotate(-90deg);
      }

      /* Locked (cannot re-open) */
      .toggle-form-btn.locked {
        opacity: 0.5;
        cursor: default;
        box-shadow: none;
        pointer-events: none;
      }

      /* Light theme toggle button */
      html.light-theme .toggle-form-btn {
        background: rgba(0,0,0,0.05);
        border-color: rgba(0,0,0,0.1);
        color: rgba(0,0,0,0.7);
      }

      html.light-theme .toggle-form-btn:hover {
        background: rgba(0,0,0,0.08);
        color: rgba(0,0,0,0.9);
      }

      /* ===== Animated SVG Icons for Stat Cards ===== */
      
      /* Clock animation for pending */
      .clock-animated .clock-hands {
        transform-origin: 12px 12px;
        animation: clockTick 4s linear infinite;
      }

      @keyframes clockTick {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
      }

      /* Check mark animation */
      .check-animated .check-path {
        stroke-dasharray: 30;
        stroke-dashoffset: 30;
        animation: checkDraw 1.5s ease-out forwards, checkPulse 3s ease-in-out 1.5s infinite;
      }

      @keyframes checkDraw {
        to { stroke-dashoffset: 0; }
      }

      @keyframes checkPulse {
        0%, 100% { stroke-width: 2; }
        50% { stroke-width: 2.5; }
      }

      /* Coins animation */
      .coins-animated {
        animation: coinsRotate 5s ease-in-out infinite;
      }

      .coins-animated .coin-orbit {
        transform-origin: center;
        animation: orbitPulse 2s ease-in-out infinite;
      }

      @keyframes coinsRotate {
        0%, 100% { transform: rotate(0deg); }
        25% { transform: rotate(-5deg); }
        75% { transform: rotate(5deg); }
      }

      @keyframes orbitPulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.6; }
      }

      /* Header coin animation */
      .coin-animated {
        animation: coinSpin 3s ease-in-out infinite;
      }

      @keyframes coinSpin {
        0%, 100% { transform: rotateY(0deg); }
        50% { transform: rotateY(180deg); }
      }

      /* ===== End Animated SVG Icons ===== */

      /* Modern Copy Toast */
      .copy-toast-modern {
        position: fixed;
        bottom: 2rem;
        left: 50%;
        transform: translateX(-50%) translateY(20px);
        background: linear-gradient(135deg, #22c55e, #16a34a);
        color: white;
        padding: 0.9rem 1.5rem;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 0.6rem;
        box-shadow: 0 15px 40px rgba(34,197,94,0.3);
        z-index: 99999;
        opacity: 0;
        transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
      }

      .copy-toast-modern.show {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
      }

      .copy-toast-modern .toast-check {
        width: 20px;
        height: 20px;
        stroke: white;
      }

      .copy-toast-modern .toast-check path {
        stroke-dasharray: 30;
        stroke-dashoffset: 30;
        animation: toastCheckDraw 0.4s ease-out 0.1s forwards;
      }

      @keyframes toastCheckDraw {
        to { stroke-dashoffset: 0; }
      }

      /* ===== Header Animation ===== */
      .money-animated {
        animation: moneyFloat 3s ease-in-out infinite;
      }

      .money-animated .center-coin {
        animation: coinPulseCenter 2s ease-in-out infinite;
        transform-origin: center;
      }

      .money-animated .left-dot,
      .money-animated .right-dot {
        animation: dotBlink 1.5s ease-in-out infinite;
      }

      .money-animated .right-dot {
        animation-delay: 0.75s;
      }

      @keyframes moneyFloat {
        0%, 100% { transform: translateY(0) rotate(0deg); }
        25% { transform: translateY(-2px) rotate(-2deg); }
        75% { transform: translateY(-2px) rotate(2deg); }
      }

      @keyframes coinPulseCenter {
        0%, 100% { r: 3; }
        50% { r: 3.5; }
      }

      @keyframes dotBlink {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.3; }
      }

      /* Header Stats Wrapper */
      .header-stats-wrapper {
        display: flex;
        align-items: center;
        gap: 1rem;
      }

      .header-stat-item {
        text-align: right;
        padding: 0.5rem 1rem;
        background: rgba(255,255,255,0.03);
        border-radius: 10px;
        border: 1px solid rgba(255,255,255,0.05);
        transition: all 0.3s ease;
      }

      .header-stat-item:hover {
        background: rgba(255,255,255,0.06);
        transform: translateY(-2px);
      }

      .header-stat-item .stat-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: rgba(255,255,255,0.5);
        margin-bottom: 0.2rem;
      }

      .header-stat-item .stat-amount {
        font-size: 1.1rem;
        font-weight: 700;
      }

      .header-stat-item .stat-amount.pending {
        color: #fbbf24;
      }

      .header-stat-item .stat-amount.verified {
        color: #22c55e;
      }

      /* Light theme header stats */
      html.light-theme .header-stat-item {
        background: rgba(0,0,0,0.03);
        border-color: rgba(0,0,0,0.05);
      }

      html.light-theme .header-stat-item .stat-label {
        color: rgba(0,0,0,0.5);
      }

      /* Responsive header stats */
      @media (max-width: 768px) {
        .header-stats-wrapper {
          flex-direction: column;
          gap: 0.5rem;
        }
        .header-stat-item {
          text-align: center;
          width: 100%;
        }
      }

      /* ===== End Header Animation ===== */

      /* ===== Action Button SVG Animations ===== */
      
      /* Save icon animation */
      .save-icon-animated {
        animation: saveFloat 2s ease-in-out infinite;
      }

      @keyframes saveFloat {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-2px); }
      }

      .submit-btn-animated:hover .save-icon-animated {
        animation: savePulse 0.5s ease-in-out;
      }

      @keyframes savePulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.2); }
      }

      /* List icon animation */
      .list-icon-animated {
        animation: listBounce 3s ease-in-out infinite;
      }

      .list-icon-animated .list-line-1 {
        stroke-dasharray: 6;
        stroke-dashoffset: 6;
        animation: lineAppear 1.5s ease-out forwards;
      }

      .list-icon-animated .list-line-2 {
        stroke-dasharray: 6;
        stroke-dashoffset: 6;
        animation: lineAppear 1.5s ease-out 0.3s forwards;
      }

      @keyframes listBounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-2px); }
      }

      @keyframes lineAppear {
        to { stroke-dashoffset: 0; }
      }

      /* Copy icon SVG styling */
      .copy-icon-svg {
        width: 14px;
        height: 14px;
        stroke: rgba(255,255,255,0.5);
        vertical-align: middle;
        margin-left: 0.3rem;
        transition: all 0.2s ease;
      }

      .copy-text:hover .copy-icon-svg {
        stroke: rgba(255,255,255,0.9);
        transform: scale(1.1);
      }

      /* Status icons animation */
      .status-icon-check {
        stroke: currentColor;
      }

      .status-icon-check polyline {
        stroke-dasharray: 30;
        stroke-dashoffset: 30;
        animation: checkAppear 0.5s ease-out forwards;
      }

      @keyframes checkAppear {
        to { stroke-dashoffset: 0; }
      }

      .status-icon-pending {
        stroke: currentColor;
      }

      .status-icon-pending polyline {
        transform-origin: 12px 12px;
        animation: pendingTick 3s linear infinite;
      }

      @keyframes pendingTick {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
      }

      /* Action button SVG styling */
      .action-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
      }

      .action-btn svg {
        transition: transform 0.2s ease;
      }

      .action-btn:hover svg {
        transform: scale(1.15);
      }

      .btn-verify:hover svg {
        animation: verifyPop 0.3s ease;
      }

      @keyframes verifyPop {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.3); }
      }

      .btn-delete:hover svg {
        animation: deleteBounce 0.3s ease;
      }

      @keyframes deleteBounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-3px); }
      }

      .btn-reject:hover svg {
        animation: rejectSpin 0.5s ease;
      }

      @keyframes rejectSpin {
        from { transform: rotate(0deg); }
        to { transform: rotate(-360deg); }
      }

      /* ===== End Action Button SVG Animations ===== */

      /* ===== End Payment Info Section ===== */

      .payment-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
      }

      .payment-stat-card {
        position: relative;
        background: linear-gradient(135deg, rgba(30,41,59,0.8), rgba(15,23,42,0.95));
        border-radius: 20px;
        padding: 1.5rem;
        border: 1px solid rgba(255,255,255,0.08);
        box-shadow: 0 15px 40px rgba(3,7,18,0.5);
        color: #f5f8ff;
        transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        overflow: hidden;
      }

      .payment-stat-card::before {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.05), transparent);
        opacity: 0;
        transition: opacity 0.3s ease;
      }

      .payment-stat-card::after {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: 20px;
        padding: 1px;
        background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent);
        -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
        mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
        -webkit-mask-composite: xor;
        mask-composite: exclude;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.3s ease;
      }

      .payment-stat-card:hover {
        transform: translateY(-6px) scale(1.02);
        box-shadow: 0 25px 50px rgba(0,0,0,0.25), 0 0 0 1px rgba(96,165,250,0.1);
      }

      .payment-stat-card:hover::before,
      .payment-stat-card:hover::after {
        opacity: 1;
      }

      .stat-card-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
      }

      .stat-card-icon {
        width: 52px;
        height: 52px;
        background: linear-gradient(135deg, var(--stat-accent, #3b82f6), var(--stat-accent-end, #8b5cf6));
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        transition: transform 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
      }

      .payment-stat-card:hover .stat-card-icon {
        transform: scale(1.1) rotate(-5deg);
      }

      .stat-card-icon svg {
        width: 26px;
        height: 26px;
        color: white;
        stroke: white;
        animation: iconPulse 2s ease-in-out infinite;
      }

      @keyframes iconPulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.08); }
      }

      .payment-stat-card h3 {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 500;
        color: rgba(255,255,255,0.6);
        letter-spacing: 0.02em;
      }

      .payment-stat-card .stat-value {
        font-size: 2.5rem;
        font-weight: 700;
        margin-top: 0.5rem;
        background: linear-gradient(135deg, var(--stat-accent, #fff), var(--stat-accent-end, #fff));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        animation: numberGlow 3s ease-in-out infinite;
      }

      @keyframes numberGlow {
        0%, 100% { filter: brightness(1); }
        50% { filter: brightness(1.2); }
      }

      .payment-stat-card .stat-money {
        font-size: 1.1rem;
        color: rgba(255,255,255,0.6);
        margin-top: 0.5rem;
      }

      /* Color variants for stat cards */
      .payment-stat-card.pending { --stat-accent: #fbbf24; --stat-accent-end: #fcd34d; }
      .payment-stat-card.verified { --stat-accent: #22c55e; --stat-accent-end: #4ade80; }
      .payment-stat-card.total { --stat-accent: #8b5cf6; --stat-accent-end: #a855f7; }

      /* Floating particles animation */
      .stat-particles {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        overflow: hidden;
        pointer-events: none;
        opacity: 0.5;
      }

      .stat-particles span {
        position: absolute;
        width: 4px;
        height: 4px;
        background: var(--stat-accent, #3b82f6);
        border-radius: 50%;
        animation: floatUp 4s ease-in-out infinite;
      }

      .stat-particles span:nth-child(1) { left: 20%; animation-delay: 0s; }
      .stat-particles span:nth-child(2) { left: 40%; animation-delay: 1s; }
      .stat-particles span:nth-child(3) { left: 60%; animation-delay: 2s; }
      .stat-particles span:nth-child(4) { left: 80%; animation-delay: 3s; }

      @keyframes floatUp {
        0% { transform: translateY(100px) scale(0); opacity: 0; }
        50% { opacity: 0.6; }
        100% { transform: translateY(-20px) scale(1); opacity: 0; }
      }

      /* Light theme overrides */
      @media (prefers-color-scheme: light) {
        .payment-stat-card {
          background: rgba(255,255,255,0.8) !important;
          border: 1px solid rgba(0,0,0,0.06) !important;
          box-shadow: 0 4px 20px rgba(0,0,0,0.08) !important;
        }
        .payment-stat-card h3 {
          color: rgba(0,0,0,0.5) !important;
        }
      }

      html.light-theme .payment-stat-card {
        background: rgba(255,255,255,0.8) !important;
        border: 1px solid rgba(0,0,0,0.06) !important;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08) !important;
      }

      html.light-theme .payment-stat-card h3 {
        color: rgba(0,0,0,0.5) !important;
      }

      /* Room Payment Summary */
      .room-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
      }

      .room-summary-grid.list-mode {
        grid-template-columns: 1fr;
      }

      .room-summary-grid.list-mode .room-card {
        border-radius: 14px;
        padding: 1rem 1.2rem;
      }

      .room-summary-grid.list-mode .room-card-header {
        margin-bottom: 0.65rem;
        padding-bottom: 0.55rem;
      }

      .room-card {
        position: relative;
        background: linear-gradient(135deg, rgba(30,41,59,0.8), rgba(15,23,42,0.95));
        border-radius: 20px;
        padding: 1.5rem;
        border: 1px solid rgba(255,255,255,0.08);
        transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        cursor: pointer;
        overflow: hidden;
      }

      .room-card::before {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.05), transparent);
        opacity: 0;
        transition: opacity 0.3s ease;
      }

      .room-card:hover {
        transform: translateY(-6px) scale(1.02);
        box-shadow: 0 25px 50px rgba(0,0,0,0.25), 0 0 0 1px rgba(96,165,250,0.1);
      }

      .room-card:hover::before {
        opacity: 1;
      }

      .room-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid rgba(255,255,255,0.08);
        position: relative;
        z-index: 1;
      }

      .room-number {
        font-size: 1.4rem;
        font-weight: 700;
        color: #60a5fa;
      }

      .payment-count {
        display: flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.3rem 0.7rem;
        background: rgba(34,197,94,0.15);
        color: #22c55e;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 600;
      }

      .room-card-body {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        position: relative;
        z-index: 1;
      }

      .room-tenant {
        font-size: 0.95rem;
        color: rgba(255,255,255,0.8);
      }

      .room-stats {
        display: flex;
        gap: 1rem;
        margin-top: 0.5rem;
      }

      .room-stat {
        flex: 1;
      }

      .room-stat-label {
        font-size: 0.75rem;
        color: rgba(255,255,255,0.5);
        margin-bottom: 0.2rem;
      }

      .room-stat-value {
        font-size: 1rem;
        font-weight: 600;
      }

      .room-stat-value.verified { color: #22c55e; }
      .room-stat-value.pending { color: #fbbf24; }

      .room-card.is-active-filter {
        border-color: rgba(96,165,250,0.8);
        box-shadow: 0 18px 40px rgba(37,99,235,0.28);
        transform: translateY(-4px);
      }

      .room-last-payment {
        font-size: 0.8rem;
        color: rgba(255,255,255,0.5);
        margin-top: 0.5rem;
        position: relative;
        z-index: 1;
      }

      /* Light theme overrides for room cards */
      @media (prefers-color-scheme: light) {
        .room-card {
          background: rgba(255,255,255,0.8) !important;
          border: 1px solid rgba(0,0,0,0.06) !important;
          box-shadow: 0 4px 20px rgba(0,0,0,0.08) !important;
        }
        .room-card:hover {
          box-shadow: 0 4px 30px rgba(0,0,0,0.12) !important;
        }
        .room-number { color: #2563eb !important; }
        .room-tenant { color: #374151 !important; }
        .room-stat-label { color: #6b7280 !important; }
        .room-last-payment { color: #9ca3af !important; }
      }

      html.light-theme .room-card {
        background: rgba(255,255,255,0.8) !important;
        border: 1px solid rgba(0,0,0,0.06) !important;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08) !important;
      }

      html.light-theme .room-card:hover {
        box-shadow: 0 4px 30px rgba(0,0,0,0.12) !important;
      }

      html.light-theme .room-number { color: #2563eb !important; }
      html.light-theme .room-tenant { color: #374151 !important; }
      html.light-theme .room-stat-label { color: #6b7280 !important; }
      html.light-theme .room-last-payment { color: #9ca3af !important; }
          color: #6b7280 !important;
        }
        .payment-stat-card .stat-money {
          color: #9ca3af !important;
        }
      }
      html.light-theme .payment-stat-card {
        background: linear-gradient(135deg, rgba(243,244,246,0.95), rgba(229,231,235,0.85)) !important;
        border: 1px solid rgba(0,0,0,0.1) !important;
        color: #374151 !important;
      }
      html.light-theme .payment-stat-card h3 {
        color: #6b7280 !important;
      }
      html.light-theme .payment-stat-card .stat-money {
        color: #9ca3af !important;
      }

      /* Bank Info Styles */
      .bank-info-section {
        background: linear-gradient(135deg, rgba(30, 41, 59, 0.9) 0%, rgba(51, 65, 85, 0.9) 100%);
        border: 1px solid rgba(59, 130, 246, 0.3) !important;
        border-radius: 20px;
        padding: 1.5rem !important;
        margin-bottom: 1.5rem;
      }

      .bank-info-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem 0;
        border-bottom: 1px solid rgba(255,255,255,0.08);
      }

      .bank-info-item:last-child {
        border-bottom: none;
      }

      .bank-info-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
      }

      .bank-info-icon.bank {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
      }

      .bank-info-icon.account {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
      }

      .bank-info-icon.number {
        background: linear-gradient(135deg, #10b981, #059669);
      }

      .bank-info-icon.promptpay {
        background: linear-gradient(135deg, #f59e0b, #d97706);
      }

      .bank-info-icon svg {
        width: 24px;
        height: 24px;
        stroke: white;
        stroke-width: 2;
        fill: none;
      }

      .bank-info-content {
        flex: 1;
      }

      .bank-info-label {
        font-size: 0.8rem;
        color: #94a3b8;
        margin-bottom: 4px;
        font-weight: 500;
      }

      .bank-info-value {
        font-size: 1.05rem;
        font-weight: 600;
        color: #f8fafc;
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }

      .copy-text {
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.25rem 0.5rem;
        border-radius: 6px;
      }

      .copy-text:hover {
        color: #60a5fa;
        background: rgba(96, 165, 250, 0.1);
      }

      .copy-text:active {
        transform: scale(0.98);
      }

      .copy-icon {
        font-size: 0.9rem;
        opacity: 0.7;
      }

      .section-title {
        font-size: 1.15rem;
        font-weight: 700;
        color: #f1f5f9;
        margin-bottom: 1.25rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
      }

      .section-icon {
        display: inline-flex;
        width: 24px;
        height: 24px;
        align-items: center;
        justify-content: center;
      }

      .section-icon svg {
        width: 20px;
        height: 20px;
        stroke: currentColor;
        stroke-width: 2;
        fill: none;
      }

      /* Copy toast notification */
      .copy-toast {
        position: fixed;
        bottom: 100px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(16, 185, 129, 0.95);
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 30px;
        font-size: 0.9rem;
        font-weight: 600;
        z-index: 10000;
        animation: toastIn 0.3s ease, toastOut 0.3s ease 1.7s forwards;
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
      }

      @keyframes toastIn {
        from { opacity: 0; transform: translateX(-50%) translateY(20px); }
        to { opacity: 1; transform: translateX(-50%) translateY(0); }
      }

      @keyframes toastOut {
        from { opacity: 1; transform: translateX(-50%) translateY(0); }
        to { opacity: 0; transform: translateX(-50%) translateY(-20px); }
      }

      /* Light theme for bank info */
      @media (prefers-color-scheme: light) {
        .bank-info-section {
          background: rgba(255, 255, 255, 0.8) !important;
          border: 1px solid rgba(0,0,0,0.06) !important;
        }
        .bank-info-label {
          color: #6b7280 !important;
        }
        .bank-info-value {
          color: #1f2937 !important;
        }
      }

      html.light-theme .bank-info-section {
        background: rgba(255, 255, 255, 0.8) !important;
        border: 1px solid rgba(0,0,0,0.06) !important;
      }

      html.light-theme .bank-info-label {
        color: #6b7280 !important;
      }

      html.light-theme .bank-info-value {
        color: #1f2937 !important;
      }

      .payment-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        padding: 1.5rem 0;
      }
      .payment-form-group label {
        display: block;
        margin-bottom: 0.4rem;
        font-weight: 600;
        color: rgba(255,255,255,0.85);
        font-size: 0.95rem;
      }
      .payment-form-group input,
      .payment-form-group select {
        width: 100%;
        padding: 0.75rem 0.85rem;
        border-radius: 10px;
        border: 1px solid rgba(148,163,184,0.35);
        background: rgba(15,23,42,0.9);
        color: #e2e8f0;
        font-size: 1rem;
        transition: all 0.25s ease;
      }
      .payment-form-group input:focus,
      .payment-form-group select:focus {
        border-color: #38bdf8;
        outline: none;
        box-shadow: 0 0 0 3px rgba(56,189,248,0.2);
      }

      .filter-section {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        align-items: flex-end;
        margin-bottom: 1rem;
        padding: 1rem;
        background: rgba(15,23,42,0.5);
        border-radius: 12px;
        border: 1px solid rgba(255,255,255,0.08);
      }
      .filter-group {
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
      }
      .filter-group label {
        font-size: 0.85rem;
        color: rgba(255,255,255,0.7);
      }
      .filter-group select {
        padding: 0.5rem 0.75rem;
        border-radius: 8px;
        border: 1px solid rgba(148,163,184,0.35);
        background: rgba(15,23,42,0.9);
        color: #e2e8f0;
        font-size: 0.9rem;
        min-width: 150px;
      }

      .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.35rem 0.75rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
      }
      .status-pending {
        background: rgba(251, 191, 36, 0.15);
        color: #fbbf24;
        border: 1px solid rgba(251, 191, 36, 0.3);
      }
      .status-verified {
        background: rgba(34, 197, 94, 0.15);
        color: #22c55e;
        border: 1px solid rgba(34, 197, 94, 0.3);
      }
      .status-unpaid {
        background: rgba(245, 158, 11, 0.12);
        color: #f59e0b;
        border: 1px solid rgba(245, 158, 11, 0.24);
      }

      .proof-link {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.3rem 0.6rem;
        background: rgba(96, 165, 250, 0.15);
        color: #60a5fa;
        border: 1px solid rgba(96, 165, 250, 0.3);
        border-radius: 6px;
        font-size: 0.8rem;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s;
      }
      .proof-link:hover {
        background: rgba(96, 165, 250, 0.25);
        transform: scale(1.05);
      }

      .action-btn {
        padding: 0.4rem 0.8rem;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
      }
      .btn-verify {
        background: rgba(34, 197, 94, 0.15);
        color: #22c55e;
        border: 1px solid rgba(34, 197, 94, 0.3);
      }
      .btn-verify:hover {
        background: rgba(34, 197, 94, 0.25);
      }
      .btn-reject {
        background: rgba(239, 68, 68, 0.15);
        color: #ef4444;
        border: 1px solid rgba(239, 68, 68, 0.3);
      }
      .btn-reject:hover {
        background: rgba(239, 68, 68, 0.25);
      }
      .btn-delete {
        background: rgba(239, 68, 68, 0.15);
        color: #ef4444;
        border: 1px solid rgba(239, 68, 68, 0.3);
      }
      .btn-delete:hover {
        background: rgba(239, 68, 68, 0.25);
      }

      /* Modal styles */
      .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: background-color 0.5s cubic-bezier(0.2, 0.55, 0.45, 0.8), opacity 0.5s cubic-bezier(0.2, 0.55, 0.45, 0.8);
        backdrop-filter: blur(0px);
      }
      .modal-overlay.active {
        display: flex;
        opacity: 1;
        background: rgba(0,0,0,0.4);
        backdrop-filter: blur(10px);
      }
      .modal-content {
        background: linear-gradient(135deg, rgba(15,23,42,0.98), rgba(2,6,23,0.98));
        border-radius: 20px;
        padding: 2rem;
        max-width: 90vw;
        max-height: 90vh;
        overflow: auto;
        border: 1px solid rgba(255,255,255,0.12);
        box-shadow: 0 20px 60px rgba(0,0,0,0.3), inset 0 1px 0 rgba(255,255,255,0.1);
        transform: scale(0.9) translateY(30px) opacity(0.8);
        opacity: 0;
        transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.5s cubic-bezier(0.16, 1, 0.3, 1);
      }
      .modal-overlay.active .modal-content {
        transform: scale(1) translateY(0) opacity(1);
        opacity: 1;
      }
      .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(255,255,255,0.08);
      }
      .modal-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #f8fafc;
      }
      .modal-close {
        width: 36px;
        height: 36px;
        border: none;
        background: rgba(255,255,255,0.08);
        color: #f8fafc;
        font-size: 1.5rem;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s cubic-bezier(0.2, 0.55, 0.45, 0.8);
      }
      .modal-close:hover {
        background: rgba(239, 68, 68, 0.15);
        color: #ef4444;
        transform: rotate(90deg);
      }
      .modal-body {
        animation: fadeInUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) 0.15s backwards;
      }
      @keyframes fadeInUp {
        from {
          opacity: 0;
          transform: translateY(20px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }

      .info-box {
        margin-top: 0.75rem;
        padding: 1rem 1.25rem;
        background: rgba(56,189,248,0.12);
        border-radius: 8px;
        color: #0369a1;
        font-size: 1rem;
        border-left: 4px solid #38bdf8;
      }
      .info-box strong {
        display: block;
        margin-bottom: 0.3rem;
      }

      .payments-view-toggle {
        padding: 0.6rem 0.95rem;
        border-radius: 10px;
        border: 1px solid rgba(148,163,184,0.28);
        background: rgba(255,255,255,0.05);
        color: #cbd5e1;
        cursor: pointer;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
      }

      .payments-view-toggle:hover {
        background: rgba(255,255,255,0.1);
      }

      .payments-view-toggle svg {
        width: 16px;
        height: 16px;
        stroke: currentColor;
      }

      .is-hidden {
        display: none !important;
      }

      .payments-row-view {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
        gap: 0.85rem;
      }

      .payment-row-card {
        background: rgba(255,255,255,0.03);
        border: 1px solid rgba(148,163,184,0.2);
        border-radius: 12px;
        padding: 0.95rem 1rem;
      }

      .payment-row-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.55rem;
      }

      .payment-row-main {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
      }

      .payment-row-main strong {
        color: #f8fafc;
      }

      .payment-row-sub {
        color: #94a3b8;
        font-size: 0.9rem;
      }

      .payment-row-meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 0.45rem 0.8rem;
        margin-bottom: 0.65rem;
        color: #cbd5e1;
        font-size: 0.9rem;
      }

      .payment-row-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.7rem;
        flex-wrap: wrap;
      }

      body.live-light .payments-view-toggle,
      html.light-theme .payments-view-toggle {
        background: #ffffff !important;
        color: #0f172a !important;
        border-color: rgba(15,23,42,0.15) !important;
      }

      body.live-light .payment-row-card,
      html.light-theme .payment-row-card {
        background: #ffffff !important;
        border-color: rgba(15,23,42,0.12) !important;
      }

      body.live-light .payment-row-main strong,
      html.light-theme .payment-row-main strong {
        color: #0f172a !important;
      }

      body.live-light .payment-row-sub,
      html.light-theme .payment-row-sub {
        color: #64748b !important;
      }

      body.live-light .payment-row-meta,
      html.light-theme .payment-row-meta {
        color: #334155 !important;
      }

      /* responsive table */
      @media (max-width: 768px) {
        .manage-table {
          display: block;
          overflow-x: auto;
        }
        }
      
        /* Responsive table for manage-table (mobile) */
        @media (max-width: 900px) {
          .manage-table, .manage-table thead, .manage-table tbody, .manage-table th, .manage-table td, .manage-table tr {
            display: block;
          }
          .manage-table thead {
            display: none;
          }
          .manage-table tr {
            margin-bottom: 1.2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            background: #fff;
            border: none;
          }
          .manage-table td {
            padding: 0.8rem 1rem;
            border: none;
            position: relative;
            font-size: 1rem;
          }
          .manage-table td:before {
            content: attr(data-label);
            font-weight: 600;
            color: #64748b;
            display: block;
            margin-bottom: 0.3rem;
            font-size: 0.95rem;
          }
        }
      }

      /* ===== Collection Progress Bar ===== */
      .payment-collection-progress { margin-top: 1.25rem; }
      .pcp-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem; }
      .pcp-label { font-size: 0.85rem; color: rgba(255,255,255,0.5); font-weight: 500; }
      .pcp-pct { font-size: 1.1rem; font-weight: 700; color: #22c55e; }
      .pcp-bar-track { height: 10px; background: rgba(255,255,255,0.08); border-radius: 6px; overflow: hidden; }
      .pcp-bar-fill { height: 100%; background: linear-gradient(90deg, #22c55e, #4ade80); border-radius: 6px; transition: width 1s cubic-bezier(.4,0,.2,1); }
      .pcp-legend { display: flex; flex-wrap: wrap; gap: 0.5rem 1.5rem; margin-top: 0.6rem; font-size: 0.8rem; color: rgba(255,255,255,0.5); align-items: center; }
      .pcp-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 0.3rem; vertical-align: middle; }
      .pcp-dot.verified { background: #22c55e; }
      .pcp-dot.pending { background: #fbbf24; }
      .pcp-dot.unpaid { background: #94a3b8; }
      html.light-theme .pcp-label { color: rgba(0,0,0,0.5); }
      html.light-theme .pcp-pct { color: #16a34a; }
      html.light-theme .pcp-bar-track { background: rgba(0,0,0,0.08); }
      html.light-theme .pcp-legend { color: rgba(0,0,0,0.5); }

      /* ===== Payment Filter Tabs ===== */
      .payment-filter-tabs { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 1rem; }
      .payment-filter-tab { padding: 0.45rem 1rem; border-radius: 24px; border: 1px solid rgba(255,255,255,0.12); background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.6); font-size: 0.88rem; font-weight: 600; cursor: pointer; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 0.4rem; }
      .payment-filter-tab:hover { background: rgba(255,255,255,0.09); color: rgba(255,255,255,0.9); }
      .payment-filter-tab.active { background: rgba(99,102,241,0.2); border-color: rgba(99,102,241,0.5); color: #a5b4fc; }
      .payment-filter-tab .tab-count { font-size: 0.75rem; background: rgba(255,255,255,0.1); padding: 0.1rem 0.45rem; border-radius: 12px; font-weight: 700; }
      .payment-filter-tab.active .tab-count { background: rgba(99,102,241,0.3); color: #c7d2fe; }
      html.light-theme .payment-filter-tab { border-color: rgba(0,0,0,0.1); background: rgba(0,0,0,0.04); color: rgba(0,0,0,0.55); }
      html.light-theme .payment-filter-tab:hover { background: rgba(0,0,0,0.07); color: rgba(0,0,0,0.85); }
      html.light-theme .payment-filter-tab.active { background: rgba(99,102,241,0.12); border-color: rgba(99,102,241,0.35); color: #4f46e5; }

      /* ===== Payment Toolbar ===== */
      .payment-toolbar { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
      .payment-toolbar select { padding: 0.45rem 0.75rem; border-radius: 8px; border: 1px solid rgba(148,163,184,0.25); background: rgba(15,23,42,0.9); color: #e2e8f0; font-size: 0.88rem; cursor: pointer; }
      .payment-toolbar-clear { padding: 0.45rem 0.9rem; border-radius: 8px; border: 1px solid rgba(148,163,184,0.25); background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.6); font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.3rem; transition: all 0.2s ease; }
      .payment-toolbar-clear:hover { background: rgba(255,255,255,0.1); color: rgba(255,255,255,0.9); }
      html.light-theme .payment-toolbar select { background: rgba(0,0,0,0.04); border-color: rgba(0,0,0,0.12); color: #111827; }
      html.light-theme .payment-toolbar-clear { background: rgba(0,0,0,0.04); border-color: rgba(0,0,0,0.12); color: rgba(0,0,0,0.6); }
      html.light-theme .payment-toolbar-clear:hover { background: rgba(0,0,0,0.08); color: rgba(0,0,0,0.9); }
    </style>
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/futuristic-bright.css" />
  </head>
  <body class="reports-page">
    <div class="app-shell">
      <?php include __DIR__ . '/../includes/sidebar.php'; ?>

      <main class="app-main">
        <div>
          <?php
            $pageTitle = 'จัดการการชำระเงิน';
            include __DIR__ . '/../includes/page_header.php';
          ?>
          <div class="container" style="max-width:100%;padding:0;">

          <!-- Header -->
          <section class="manage-panel fade-in-up">
            <div class="section-header">
              <div class="title-block">
                <div class="panel-icon-modern">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="money-animated" xmlns="http://www.w3.org/2000/svg">
                    <rect x="2" y="6" width="20" height="12" rx="2" stroke="white"/>
                    <circle cx="12" cy="12" r="3" class="center-coin"/>
                    <circle cx="6" cy="12" r="1" fill="white" class="left-dot"/>
                    <circle cx="18" cy="12" r="1" fill="white" class="right-dot"/>
                  </svg>
                </div>
                <div>
                  <h1 style="margin:0;font-size:1.6rem;line-height:1;">จัดการการชำระเงิน</h1>
                  <p style="color:#94a3b8;margin-top:0.25rem;">ตรวจสอบและยืนยันการชำระเงินของผู้เช่า</p>
                </div>
              </div>
              <div class="header-stats-wrapper">
                <div class="header-stat-item pending-stat">
                  <div class="stat-label">รอตรวจสอบ</div>
                  <div class="stat-amount pending">฿<?php echo number_format($stats['total_pending']); ?></div>
                </div>
                <div class="header-stat-item verified-stat">
                  <div class="stat-label">ตรวจสอบแล้ว</div>
                  <div class="stat-amount verified">฿<?php echo number_format($stats['total_verified']); ?></div>
                </div>
              </div>
            </div>
          </section>

          <!-- Stats -->
          <section class="manage-panel">
            <div class="section-header">
              <div>
                <p style="color:#94a3b8;margin-top:0.2rem;">สรุปสถานะการชำระเงิน</p>
              </div>
            </div>
            <div class="payment-stats">
              <div class="payment-stat-card pending fade-in-up particle-wrapper" style="animation-delay: 0s;">
                <div class="particle-container" data-particles="3"></div>
                <div class="stat-particles">
                  <span></span><span></span><span></span><span></span>
                </div>
                <div class="stat-card-header">
                  <div class="stat-card-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="clock-animated">
                      <circle cx="12" cy="12" r="10"/>
                      <polyline points="12 6 12 12 16 14" class="clock-hands"/>
                    </svg>
                  </div>
                  <h3>รอตรวจสอบ</h3>
                </div>
                <div class="stat-value"><?php echo number_format($stats['pending']); ?></div>
                <div class="stat-money">฿<?php echo number_format($stats['total_pending']); ?></div>
              </div>

              <div class="payment-stat-card verified fade-in-up particle-wrapper" style="animation-delay: 0.1s;">
                <div class="particle-container" data-particles="3"></div>
                <div class="stat-particles">
                  <span></span><span></span><span></span><span></span>
                </div>
                <div class="stat-card-header">
                  <div class="stat-card-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="check-animated">
                      <polyline points="20 6 9 17 4 12" class="check-path"/>
                    </svg>
                  </div>
                  <h3>ตรวจสอบแล้ว</h3>
                </div>
                <div class="stat-value"><?php echo number_format($stats['verified']); ?></div>
                <div class="stat-money">฿<?php echo number_format($stats['total_verified']); ?></div>
              </div>

              <div class="payment-stat-card total fade-in-up particle-wrapper" style="animation-delay: 0.2s;">
                <div class="particle-container" data-particles="3"></div>
                <div class="stat-particles">
                  <span></span><span></span><span></span><span></span>
                </div>
                <div class="stat-card-header">
                  <div class="stat-card-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="coins-animated">
                      <circle cx="8" cy="8" r="6"/>
                      <path d="M18.09 10.37A6 6 0 1 1 10.34 18" class="coin-orbit"/>
                      <path d="M7 6h2v4"/>
                      <path d="M15 12h2v4"/>
                    </svg>
                  </div>
                  <h3>รวมทั้งหมด</h3>
                </div>
                <div class="stat-value"><?php echo number_format($stats['pending'] + $stats['verified']); ?></div>
                <div class="stat-money">฿<?php echo number_format($stats['total_pending'] + $stats['total_verified']); ?></div>
              </div>
            </div>

            <!-- Collection Progress Bar -->
            <div class="payment-collection-progress">
              <div class="pcp-header">
                <span class="pcp-label">อัตราการตรวจสอบ</span>
                <span class="pcp-pct"><?php echo $verifiedPct; ?>%</span>
              </div>
              <div class="pcp-bar-track">
                <div class="pcp-bar-fill" id="pcpBarFill" style="width:0%" data-target="<?php echo $verifiedPct; ?>"></div>
              </div>
              <div class="pcp-legend">
                <?php if ($pendingOnlyCount > 0): ?>
                  <span><span class="pcp-dot pending"></span>รอตรวจสอบ <?php echo number_format($pendingOnlyCount); ?> รายการ</span>
                <?php endif; ?>
                <?php if ($unpaidOnlyCount > 0): ?>
                  <span><span class="pcp-dot unpaid"></span>รอชำระ <?php echo number_format($unpaidOnlyCount); ?> รายการ</span>
                <?php endif; ?>
              </div>
            </div>
          </section>

          <!-- Bank Payment Destination Section removed -->

          <!-- Filter Section -->
          <section class="manage-panel">
            <!-- Status Filter Tabs -->
            <div class="payment-filter-tabs" id="paymentFilterTabs">
              <button type="button" class="payment-filter-tab <?php echo $filterStatus === '' ? 'active' : ''; ?>" data-status="">ทั้งหมด <span class="tab-count"><?php echo $pendingOnlyCount + $unpaidOnlyCount; ?></span></button>
              <button type="button" class="payment-filter-tab <?php echo $filterStatus === '0' ? 'active' : ''; ?>" data-status="0">รอตรวจสอบ <span class="tab-count"><?php echo $pendingOnlyCount; ?></span></button>
              <button type="button" class="payment-filter-tab <?php echo $filterStatus === 'unpaid' ? 'active' : ''; ?>" data-status="unpaid">รอชำระ <span class="tab-count"><?php echo $unpaidOnlyCount; ?></span></button>
              <button type="button" class="payment-filter-tab <?php echo $filterStatus === '1' ? 'active' : ''; ?>" data-status="1">ตรวจสอบแล้ว <span class="tab-count"><?php echo $verifiedFilteredCount; ?></span></button>
            </div>
            <!-- Hidden status input for filter state -->
            <input type="hidden" id="filterStatus" value="<?php echo htmlspecialchars($filterStatus, ENT_QUOTES, 'UTF-8'); ?>">
            <!-- Compact Toolbar -->
            <div class="payment-toolbar">
              <?php $thaiMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.']; ?>
              <select id="filterRoom" onchange="applyFilters()">
                <option value="">ทุกห้อง</option>
                <?php foreach ($filterRoomOptions as $roomNumber): ?>
                  <option value="<?php echo htmlspecialchars((string)$roomNumber, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filterRoom === (string)$roomNumber ? 'selected' : ''; ?>>ห้อง <?php echo htmlspecialchars((string)$roomNumber, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
              <select id="filterMonth" onchange="applyFilters()">
                <option value="" <?php echo $filterMonth === '' ? 'selected' : ''; ?>>ทุกเดือน</option>
                <?php foreach ($availableMonthOptions as $monthOption): $m = (int)$monthOption; ?>
                  <option value="<?php echo $m; ?>" <?php echo $filterMonth === (string)$m ? 'selected' : ''; ?>><?php echo $thaiMonths[$m]; ?></option>
                <?php endforeach; ?>
              </select>
              <select id="filterYear" onchange="applyFilters()">
                <option value="">ทุกปี</option>
                <?php foreach ($availableYearOptions as $yearOption): ?>
                  <option value="<?php echo htmlspecialchars((string)$yearOption, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filterYear === (string)$yearOption ? 'selected' : ''; ?>><?php echo (int)$yearOption + 543; ?></option>
                <?php endforeach; ?>
              </select>
              <button type="button" onclick="clearFilters()" class="payment-toolbar-clear">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px;"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>ล้างตัวกรอง
              </button>
            </div>
            <div id="paymentsRoomFilterNotice" style="margin-top:0.75rem;padding:0.75rem 1rem;background:rgba(59,130,246,0.15);border:1px solid rgba(59,130,246,0.3);border-radius:8px;color:#60a5fa;display:<?php echo $filterRoom !== '' ? 'flex' : 'none'; ?>;align-items:center;gap:0.5rem;">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
              กำลังแสดงเฉพาะห้อง <strong id="paymentsRoomFilterValue"><?php echo htmlspecialchars($filterRoom, ENT_QUOTES, 'UTF-8'); ?></strong>
              <button type="button" onclick="clearRoomFilter()" style="margin-left:auto;color:#f59e0b;background:none;border:none;cursor:pointer;padding:0;font:inherit;">✕ ยกเลิก</button>
            </div>
          </section>

          <!-- Payments Table -->
          <section class="manage-panel">
            <div class="section-header">
              <div>
                <h2 style="margin:0;display:flex;align-items:center;gap:0.5rem;">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="list-icon-animated" style="width:24px;height:24px;">
                    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                    <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
                    <line x1="9" y1="12" x2="15" y2="12" class="list-line-1"/>
                    <line x1="9" y1="16" x2="15" y2="16" class="list-line-2"/>
                  </svg>
                  รายการการชำระเงิน
                </h2>
                <p id="paymentsResultsSummary" style="color:#94a3b8;margin-top:0.2rem;">พบ <?php echo count($payments); ?> รายการ<?php echo $filterRoom !== '' ? ' (ห้อง ' . htmlspecialchars($filterRoom) . ')' : ''; ?></p>
              </div>
              <button type="button" id="paymentsViewToggle" class="payments-view-toggle" onclick="togglePaymentsView()">
                <svg viewBox="0 0 24 24" fill="none" stroke="#111827" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#111827;"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                <span id="paymentsViewToggleText">มุมมองกริด</span>
              </button>
            </div>
            <div id="paymentsTableWrap" style="overflow-x:auto;display:block !important;visibility:visible !important;opacity:1 !important;">
              <table class="manage-table" id="paymentsTable" style="display:table !important;width:100%;visibility:visible !important;opacity:1 !important;">
                <thead>
                  <tr>
                    <th>รหัส</th>
                    <th>ห้อง</th>
                    <th>ผู้เช่า</th>
                    <th>เดือนค่าใช้จ่าย</th>
                    <th>วันที่ชำระ</th>
                    <th>จำนวนเงิน</th>
                    <th>หลักฐาน</th>
                    <th>หมายเหตุ</th>
                    <th>สถานะ</th>
                    <th>การดำเนินการ</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($payments)): ?>
                    <tr>
                      <td colspan="10" style="text-align:center;padding:2rem;color:#64748b;">ยังไม่มีข้อมูลการชำระเงิน</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($payments as $pay): ?>
                      <?php $isDepositRemark = strpos((string)($pay['pay_remark'] ?? ''), 'มัดจำ') !== false; ?>
                      <?php $hasExpenseLink = !empty($pay['exp_id']) && (int)$pay['exp_id'] > 0; ?>
                      <?php $filterDateSource = !empty($pay['exp_month']) ? (string)$pay['exp_month'] : (string)($pay['pay_date'] ?? ''); ?>
                      <?php $filterTimestamp = $filterDateSource !== '' ? strtotime($filterDateSource) : false; ?>
                      <?php $filterMonthValue = $filterTimestamp ? (string)((int)date('n', $filterTimestamp)) : ''; ?>
                      <?php $filterYearValue = $filterTimestamp ? (string)date('Y', $filterTimestamp) : ''; ?>
                      <tr data-pay-id="<?php echo (int)$pay['pay_id']; ?>" data-filter-item="payment" data-room="<?php echo htmlspecialchars((string)($pay['room_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-status="<?php echo htmlspecialchars((string)($pay['pay_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-month="<?php echo htmlspecialchars($filterMonthValue, ENT_QUOTES, 'UTF-8'); ?>" data-year="<?php echo htmlspecialchars($filterYearValue, ENT_QUOTES, 'UTF-8'); ?>">
                        <td><?php echo htmlspecialchars((string)($pay['display_pay_id'] ?? (string)((int)$pay['pay_id']))); ?></td>
                        <td><?php echo htmlspecialchars((string)($pay['room_number'] ?? '-')); ?></td>
                        <td><?php echo htmlspecialchars($pay['tnt_name'] ?? '-'); ?></td>
                        <td><?php echo $pay['exp_month'] ? date('m/Y', strtotime($pay['exp_month'])) : '-'; ?></td>
                        <td><?php echo $pay['pay_date'] ? date('d/m/Y', strtotime($pay['pay_date'])) : '-'; ?></td>
                        <td style="text-align:right;font-weight:700;color:#22c55e;">฿<?php echo number_format((int)($pay['pay_amount'] ?? 0)); ?></td>
                        <td>
                          <?php if (!empty($pay['pay_proof'])): ?>
                            <span class="proof-link" onclick="showProof('<?php echo htmlspecialchars($pay['pay_proof'], ENT_QUOTES, 'UTF-8'); ?>')">
                              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:3px;"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>ดูหลักฐาน
                            </span>
                          <?php else: ?>
                            <span style="color:#64748b;">-</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php if (!empty($pay['pay_remark'])): ?>
                            <span style="color:#f59e0b;font-weight:500;"><?php echo htmlspecialchars($pay['pay_remark']); ?></span>
                          <?php else: ?>
                            <span style="color:#64748b;">ค่าเช่า</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php 
                          $statusClass = $pay['pay_status'] === '1' ? 'status-verified' : (($pay['pay_status'] ?? '') === 'unpaid' ? 'status-unpaid' : 'status-pending');
                          $statusText = $statusMap[$pay['pay_status']] ?? 'ไม่ทราบ';
                          ?>
                          <span class="status-badge <?php echo $statusClass; ?>">
                            <?php if ($pay['pay_status'] === '1'): ?>
                              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="status-icon-check" style="width:14px;height:14px;"><polyline points="20 6 9 17 4 12"/></svg>
                            <?php elseif (($pay['pay_status'] ?? '') === 'unpaid'): ?>
                              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                            <?php else: ?>
                              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="status-icon-pending" style="width:14px;height:14px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <?php endif; ?>
                            <?php echo $statusText; ?>
                          </span>
                        </td>
                        <td>
                          <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                            <?php if ($pay['pay_status'] === '0' && !$isDepositRemark && $hasExpenseLink): ?>
                              <button type="button" class="action-btn btn-verify" onclick="updatePaymentStatus(<?php echo (int)$pay['pay_id']; ?>, '1', <?php echo (int)$pay['exp_id']; ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;"><polyline points="20 6 9 17 4 12"/></svg> ยืนยัน</button>
                            <?php endif; ?>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    <tr id="paymentsNoResultsRow" style="display:none;">
                      <td colspan="10" style="text-align:center;padding:2rem;color:#64748b;">ไม่พบข้อมูลตามตัวกรองที่เลือก</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

            <div id="paymentsRowView" class="payments-row-view is-hidden">
              <?php if (empty($payments)): ?>
                <div class="payment-row-card" style="text-align:center;color:#64748b;">ยังไม่มีข้อมูลการชำระเงิน</div>
              <?php else: ?>
                <?php foreach ($payments as $pay): ?>
                  <?php 
                    $statusClass = $pay['pay_status'] === '1' ? 'status-verified' : (($pay['pay_status'] ?? '') === 'unpaid' ? 'status-unpaid' : 'status-pending');
                    $statusText = $statusMap[$pay['pay_status']] ?? 'ไม่ทราบ';
                    $isDepositRemark = strpos((string)($pay['pay_remark'] ?? ''), 'มัดจำ') !== false;
                    $hasExpenseLink = !empty($pay['exp_id']) && (int)$pay['exp_id'] > 0;
                    $filterDateSource = !empty($pay['exp_month']) ? (string)$pay['exp_month'] : (string)($pay['pay_date'] ?? '');
                    $filterTimestamp = $filterDateSource !== '' ? strtotime($filterDateSource) : false;
                    $filterMonthValue = $filterTimestamp ? (string)((int)date('n', $filterTimestamp)) : '';
                    $filterYearValue = $filterTimestamp ? (string)date('Y', $filterTimestamp) : '';
                  ?>
                  <div class="payment-row-card" data-pay-id="<?php echo (int)$pay['pay_id']; ?>" data-filter-item="payment" data-room="<?php echo htmlspecialchars((string)($pay['room_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-status="<?php echo htmlspecialchars((string)($pay['pay_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-month="<?php echo htmlspecialchars($filterMonthValue, ENT_QUOTES, 'UTF-8'); ?>" data-year="<?php echo htmlspecialchars($filterYearValue, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="payment-row-top">
                      <div class="payment-row-main">
                        <strong>#<?php echo htmlspecialchars((string)($pay['display_pay_id'] ?? (string)((int)$pay['pay_id']))); ?></strong>
                        <span class="payment-row-sub">ห้อง <?php echo htmlspecialchars((string)($pay['room_number'] ?? '-')); ?> • <?php echo htmlspecialchars($pay['tnt_name'] ?? '-'); ?></span>
                      </div>
                      <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                    </div>
                    <div class="payment-row-meta">
                      <div>เดือนค่าใช้จ่าย: <?php echo $pay['exp_month'] ? date('m/Y', strtotime($pay['exp_month'])) : '-'; ?></div>
                      <div>วันที่ชำระ: <?php echo $pay['pay_date'] ? date('d/m/Y', strtotime($pay['pay_date'])) : '-'; ?></div>
                      <div>จำนวนเงิน: <strong style="color:#22c55e;">฿<?php echo number_format((int)($pay['pay_amount'] ?? 0)); ?></strong></div>
                      <div>หมายเหตุ: <?php echo !empty($pay['pay_remark']) ? htmlspecialchars($pay['pay_remark']) : 'ค่าเช่า'; ?></div>
                    </div>
                    <div class="payment-row-actions">
                      <div>
                        <?php if (!empty($pay['pay_proof'])): ?>
                          <span class="proof-link" onclick="showProof('<?php echo htmlspecialchars($pay['pay_proof'], ENT_QUOTES, 'UTF-8'); ?>')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:3px;"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>ดูหลักฐาน
                          </span>
                        <?php else: ?>
                          <span style="color:#64748b;">-</span>
                        <?php endif; ?>
                      </div>
                      <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                        <?php if ($pay['pay_status'] === '0' && !$isDepositRemark && $hasExpenseLink): ?>
                          <button type="button" class="action-btn btn-verify" onclick="updatePaymentStatus(<?php echo (int)$pay['pay_id']; ?>, '1', <?php echo (int)$pay['exp_id']; ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;"><polyline points="20 6 9 17 4 12"/></svg> ยืนยัน</button>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
                <div id="paymentsRowNoResults" class="payment-row-card" style="text-align:center;color:#64748b;display:none;">ไม่พบข้อมูลตามตัวกรองที่เลือก</div>
              <?php endif; ?>
            </div>
          </section>

          </div>
        </div>
      </main>
    </div>

    <!-- Proof Modal -->
    <div class="modal-overlay" id="proofModal">
      <div class="modal-content" style="max-width:80vw;">
        <div class="modal-header">
          <h3 class="modal-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;vertical-align:-4px;margin-right:6px;"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>หลักฐานการชำระเงิน</h3>
          <button class="modal-close" onclick="closeProofModal()">×</button>
        </div>
        <div class="modal-body" id="proofModalBody" style="text-align:center;">
          <!-- Content will be loaded here -->
        </div>
      </div>
    </div>

    <script src="/dormitory_management/Public/Assets/Javascript/animate-ui.js"></script>
    <script src="/dormitory_management/Public/Assets/Javascript/confirm-modal.js"></script>
    <script>
      // Toast fallback (ถ้าไม่มีประกาศไว้จากไฟล์อื่น)
      if (typeof showSuccessToast !== 'function' || typeof showErrorToast !== 'function') {
        const ensureToastContainer = () => {
          let c = document.getElementById('toast-container');
          if (!c) {
            c = document.createElement('div');
            c.id = 'toast-container';
            document.body.appendChild(c);
          }
          return c;
        };
        const makeToast = (message, type) => {
          const c = ensureToastContainer();
          const t = document.createElement('div');
          t.className = `toast ${type === 'error' ? 'toast-error' : 'toast-success'}`;
          t.textContent = message;
          c.appendChild(t);
          setTimeout(() => {
            t.style.opacity = '0';
            t.style.transition = 'opacity 0.3s ease';
            setTimeout(() => t.remove(), 300);
          }, 2500);
        };
        if (typeof showSuccessToast !== 'function') {
          window.showSuccessToast = (msg) => makeToast(msg || 'สำเร็จ', 'success');
        }
        if (typeof showErrorToast !== 'function') {
          window.showErrorToast = (msg) => makeToast(msg || 'เกิดข้อผิดพลาด', 'error');
        }
      }

      const paymentsDefaultFilters = {
        room: <?php echo json_encode($filterRoom, JSON_UNESCAPED_UNICODE); ?>,
        status: <?php echo json_encode($filterStatus, JSON_UNESCAPED_UNICODE); ?>,
        month: <?php echo json_encode($filterMonth, JSON_UNESCAPED_UNICODE); ?>,
        year: <?php echo json_encode($filterYear, JSON_UNESCAPED_UNICODE); ?>
      };

      // store the current status filter in a JS variable instead of a hidden input
      let paymentsActiveStatus = paymentsDefaultFilters.status !== undefined && paymentsDefaultFilters.status !== '' ? paymentsDefaultFilters.status : '';

      let paymentsDataTable = null;
      let paymentsSourceRows = [];

      function getPaymentsFilterState() {
        return {
          room: document.getElementById('filterRoom')?.value || '',
          status: paymentsActiveStatus,
          month: document.getElementById('filterMonth')?.value || '',
          year: document.getElementById('filterYear')?.value || ''
        };
      }

      function updatePaymentsFilterHistory(filters) {
        const url = new URL(window.location.href);
        if (filters.room) {
          url.searchParams.set('room', filters.room);
        } else {
          url.searchParams.delete('room');
        }

        if (filters.status) {
          url.searchParams.set('status', filters.status);
        } else {
          url.searchParams.delete('status');
        }

        if (filters.month) {
          url.searchParams.set('filter_month', filters.month);
        } else {
          url.searchParams.set('filter_month', 'all');
        }

        if (filters.year) {
          url.searchParams.set('filter_year', filters.year);
        } else {
          url.searchParams.delete('filter_year');
        }

        url.hash = '';
        window.history.replaceState({}, '', url.toString());
      }

      function matchesPaymentFilters(element, filters) {
        if (!element || element.dataset.filterItem !== 'payment') {
          return false;
        }

        if (filters.room && (element.dataset.room || '') !== filters.room) {
          return false;
        }

        if (filters.status === '') {
          // 'ทั้งหมด' tab: show only unverified rows (pending + unpaid), exclude verified
          if ((element.dataset.status || '') === '1') return false;
        } else {
          // specific status filter
          const rowStatus = element.dataset.status || '';
          if (rowStatus !== filters.status) return false;
        }

        if (filters.month && (element.dataset.month || '') !== filters.month) {
          return false;
        }

        if (filters.year && (element.dataset.year || '') !== filters.year) {
          return false;
        }

        return true;
      }

      function updatePaymentsSummary(visibleCount, filters) {
        const summary = document.getElementById('paymentsResultsSummary');
        if (!summary) {
          return;
        }

        let text = `พบ ${visibleCount} รายการ`;
        if (filters.room) {
          text += ` (ห้อง ${filters.room})`;
        }
        summary.textContent = text;
      }

      function updatePaymentsRoomNotice(filters) {
        const notice = document.getElementById('paymentsRoomFilterNotice');
        const value = document.getElementById('paymentsRoomFilterValue');
        if (!notice || !value) {
          return;
        }

        if (filters.room) {
          value.textContent = filters.room;
          notice.style.display = 'flex';
        } else {
          notice.style.display = 'none';
        }
      }

      function updateRoomCardState(filters) {
        const roomCards = document.querySelectorAll('.room-card[data-room-number]');
        roomCards.forEach((card) => {
          const isActive = filters.room !== '' && card.dataset.roomNumber === filters.room;
          card.classList.toggle('is-active-filter', isActive);
        });
      }

      function snapshotPaymentsRows() {
        if (paymentsSourceRows.length > 0) {
          return;
        }

        const tbody = document.querySelector('#paymentsTable tbody');
        if (!tbody) {
          return;
        }

        paymentsSourceRows = Array.from(tbody.querySelectorAll('tr[data-filter-item="payment"]')).map((row) => row.cloneNode(true));
      }

      function cleanupPaymentsTableDom() {
        const paymentsTable = document.getElementById('paymentsTable');
        if (!paymentsTable) {
          return;
        }

        const wrapper = paymentsTable.closest('.datatable-wrapper');
        if (wrapper && wrapper.parentNode) {
          wrapper.parentNode.insertBefore(paymentsTable, wrapper);
          wrapper.remove();
        }

        paymentsTable.classList.remove('datatable-table');
        paymentsTable.style.display = 'table';
        paymentsTable.style.width = '100%';
        paymentsTable.style.visibility = 'visible';
        paymentsTable.style.opacity = '1';
      }

      function initPaymentsDataTable() {
        const paymentsTable = document.getElementById('paymentsTable');
        if (!paymentsTable) return;
        if (typeof simpleDatatables === 'undefined' || typeof simpleDatatables.DataTable !== 'function') return;

        paymentsDataTable = new simpleDatatables.DataTable(paymentsTable, {
          searchable: true,
          fixedHeight: false,
          perPage: 10,
          perPageSelect: [5, 10, 25, 50],
          labels: {
            placeholder: 'ค้นหา...',
            perPage: 'รายการต่อหน้า',
            noRows: 'ไม่พบข้อมูล',
            info: 'แสดง {start} ถึง {end} จาก {rows} รายการ'
          }
        });
      }

      function destroyPaymentsDataTable() {
        if (paymentsDataTable) {
          try { paymentsDataTable.destroy(); } catch (e) {}
          paymentsDataTable = null;
        }
        cleanupPaymentsTableDom();
      }

      function applyFilters(options = {}) {
        const filters = getPaymentsFilterState();
        // debug: log new filter state
        console.debug('applyFilters called, filters=', filters);

        if (options.skipReload !== true) {
          const url = new URL(window.location.href);

          if (filters.room) {
            url.searchParams.set('room', filters.room);
          } else {
            url.searchParams.delete('room');
          }

          if (filters.status) {
            url.searchParams.set('status', filters.status);
          } else {
            url.searchParams.delete('status');
          }

          if (filters.month) {
            url.searchParams.set('filter_month', filters.month);
          } else {
            url.searchParams.set('filter_month', 'all');
          }

          if (filters.year) {
            url.searchParams.set('filter_year', filters.year);
          } else {
            url.searchParams.delete('filter_year');
          }

          url.hash = '';
          window.location.href = url.toString();
          return;
        }

        const tbody = document.querySelector('#paymentsTable tbody');
        const rowCards = document.querySelectorAll('#paymentsRowView .payment-row-card[data-filter-item="payment"]');
        const noResultsRow = document.getElementById('paymentsNoResultsRow');
        const rowNoResults = document.getElementById('paymentsRowNoResults');

        snapshotPaymentsRows();
        destroyPaymentsDataTable();

        let visibleCount = 0;
        if (tbody) {
          if (noResultsRow && noResultsRow.parentNode === tbody) {
            noResultsRow.remove();
          }

          Array.from(tbody.querySelectorAll('tr[data-filter-item="payment"]')).forEach((row) => row.remove());

          paymentsSourceRows.forEach((row) => {
            if (matchesPaymentFilters(row, filters)) {
              tbody.appendChild(row.cloneNode(true));
              visibleCount += 1;
            }
          });

          if (visibleCount === 0 && noResultsRow) {
            tbody.appendChild(noResultsRow);
          }
        }

        if (noResultsRow) {
          noResultsRow.style.display = visibleCount === 0 ? '' : 'none';
        }

        if (visibleCount > 0) {
          initPaymentsDataTable();
        }

        // --- Grid/row view: plain show/hide (no DataTable involved) ---
        rowCards.forEach((card) => {
          card.style.display = matchesPaymentFilters(card, filters) ? '' : 'none';
        });

        if (rowNoResults) {
          rowNoResults.style.display = visibleCount === 0 ? '' : 'none';
        }

        updatePaymentsSummary(visibleCount, filters);
        updatePaymentsRoomNotice(filters);
        updateRoomCardState(filters);

        if (options.updateHistory !== false) {
          updatePaymentsFilterHistory(filters);
        }
      }

      function clearFilters() {
        const room = document.getElementById('filterRoom');
        const month = document.getElementById('filterMonth');
        const year = document.getElementById('filterYear');

        if (room) room.value = '';
        if (month) month.value = paymentsDefaultFilters.month || '';
        if (year) year.value = paymentsDefaultFilters.year || '';

        paymentsActiveStatus = '';

        // Reset tab UI to "ทั้งหมด"
        document.querySelectorAll('.payment-filter-tab').forEach(function(t) {
          t.classList.toggle('active', t.dataset.status === '');
        });

        applyFilters();
      }

      function clearRoomFilter() {
        const room = document.getElementById('filterRoom');
        if (room) {
          room.value = '';
        }
        applyFilters();
      }

      // Show proof modal
      function showProof(filename) {
        const modal = document.getElementById('proofModal');
        const body = document.getElementById('proofModalBody');
        
        const ext = filename.toLowerCase().split('.').pop();
        const isPdf = ext === 'pdf';
        const path = '/dormitory_management/Public/Assets/Images/Payments/' + filename;
        
        if (isPdf) {
          body.innerHTML = '<embed src="' + path + '" type="application/pdf" width="100%" height="600px" />';
        } else {
          body.innerHTML = '<img src="' + path + '" alt="หลักฐานการชำระเงิน" style="max-width:100%;max-height:70vh;border-radius:8px;" />';
        }
        
        modal.classList.add('active');
      }

      function closeProofModal() {
        document.getElementById('proofModal').classList.remove('active');
      }

      // Close modal on overlay click
      document.getElementById('proofModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeProofModal();
      });

      // Copy to clipboard function with animated toast
      function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
          // Show animated toast notification
          const toast = document.createElement('div');
          toast.className = 'copy-toast-modern';
          toast.innerHTML = `
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="toast-check">
              <path d="M20 6L9 17l-5-5"/>
            </svg>
            <span>คัดลอกสำเร็จ!</span>
          `;
          document.body.appendChild(toast);
          
          // Trigger animation
          requestAnimationFrame(() => {
            toast.classList.add('show');
          });
          
          setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
          }, 2000);
        }).catch(function(err) {
          console.error('Failed to copy: ', err);
        });
      }

      // Filter by room without page reload
      function filterByRoom(roomNumber) {
        const roomSelect = document.getElementById('filterRoom');
        if (roomSelect) {
          roomSelect.value = roomNumber;
        }
        applyFilters();

        const table = document.getElementById('paymentsTable');
        if (table) {
          table.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }

      // Update payment status
      async function updatePaymentStatus(payId, newStatus, expId) {
        const statusText = newStatus === '1' ? 'ยืนยันการชำระเงิน' : 'ยกเลิกการยืนยัน';
        
        let confirmed = false;
        if (typeof showConfirmDialog === 'function') {
          confirmed = await showConfirmDialog(
            'ยืนยันการดำเนินการ',
            `คุณต้องการ${statusText}นี้หรือไม่?`,
            'warning'
          );
        } else {
          confirmed = confirm(`คุณต้องการ${statusText}นี้หรือไม่?`);
        }
        if (confirmed) {
          await doUpdatePaymentStatus(payId, newStatus, expId);
        }
      }

      async function doUpdatePaymentStatus(payId, newStatus, expId) {
        // Find the verify button to show loading state
        const btnEl = document.querySelector(`[data-pay-id="${payId}"] .btn-verify`);
        const origText = btnEl ? btnEl.innerHTML : '';
        if (btnEl) {
          btnEl.disabled = true;
          btnEl.innerHTML = '⏳ กำลังดำเนินการ...';
        }

        // capture current status from DOM for count adjustment
        const rowElement = document.querySelector(`[data-pay-id="${payId}"]`);
        const oldStatus = rowElement ? (rowElement.dataset.status || '') : '';

        try {
          const formData = new FormData();
          formData.append('pay_id', payId);
          formData.append('pay_status', newStatus);
          formData.append('exp_id', expId);
          formData.append('csrf_token', '<?php echo $csrfToken; ?>');

          const response = await fetch('../Manage/update_payment_status.php', {
            method: 'POST',
            body: formData
          });

          const responseText = await response.text();
          let data;
          try {
            data = JSON.parse(responseText);
          } catch (parseErr) {
            throw new Error('Server error (HTTP ' + response.status + '): ' + responseText.substring(0, 200));
          }

          if (!response.ok || !data.success) {
            throw new Error(data.error || 'HTTP ' + response.status);
          }

          // Success — reload page to guarantee fresh state
          if (typeof showSuccessToast === 'function') {
            showSuccessToast(data.message || 'อัปเดตสถานะเรียบร้อย');
          }
          setTimeout(() => window.location.reload(), 800);

        } catch (err) {
          console.error('doUpdatePaymentStatus error:', err);
          if (btnEl) {
            btnEl.disabled = false;
            btnEl.innerHTML = origText;
          }
          alert('เกิดข้อผิดพลาด: ' + (err.message || 'ไม่สามารถเชื่อมต่อได้'));
        }
      }

      // utility: adjust tab count by status delta
      function adjustTabCount(status, delta) {
        // `` status '' corresponds to "ทั้งหมด" tab
        const selector = status === '' ? '.payment-filter-tab[data-status=""]' : `.payment-filter-tab[data-status="${status}"]`;
        const tab = document.querySelector(selector);
        if (!tab) return;
        const countEl = tab.querySelector('.tab-count');
        if (!countEl) return;
        const n = parseInt(countEl.textContent, 10) || 0;
        countEl.textContent = Math.max(0, n + delta);
      }

      // utility: update DOM row/card badge and counts
      function updatePaymentRowStatus(payId, newStatus, oldStatus) {
        function patchRowEl(el) {
          el.dataset.status = newStatus;
          const badge = el.querySelector('.status-badge');
          if (badge) {
            const statusClass = newStatus === '1' ? 'status-verified' : (newStatus === '0' ? 'status-pending' : '');
            const statusText = newStatus === '1' ? 'ตรวจสอบแล้ว' : 'รอตรวจสอบ';
            badge.className = 'status-badge ' + statusClass;
            badge.textContent = statusText;
          }
          // Remove verify button so it doesn't re-appear after re-filter
          const verifyBtn = el.querySelector('.btn-verify');
          if (verifyBtn) verifyBtn.remove();
        }

        // Patch live DOM rows/cards
        document.querySelectorAll(`[data-pay-id="${payId}"]`).forEach(patchRowEl);

        // Patch the snapshot array so applyFilters rebuilds with updated data
        paymentsSourceRows = paymentsSourceRows.map(function(row) {
          if (String(row.getAttribute('data-pay-id')) === String(payId)) {
            const clone = row.cloneNode(true);
            patchRowEl(clone);
            return clone;
          }
          return row;
        });

        // update tab counts
        if (oldStatus !== newStatus) {
          if (oldStatus !== '') adjustTabCount(oldStatus, -1);
          if (newStatus !== '') adjustTabCount(newStatus, +1);
        }

        // Switch to "ทั้งหมด" tab so the verified row stays visible
        paymentsActiveStatus = '';
        document.querySelectorAll('.payment-filter-tab').forEach(function(t) {
          t.classList.toggle('active', t.dataset.status === '');
        });

        // Re-render table with updated snapshot (no page navigation, preserve URL)
        applyFilters({ skipReload: true, updateHistory: false });
      }

      // Delete payment
      async function deletePayment(payId) {
        let confirmed = false;
        if (typeof showConfirmDialog === 'function') {
          confirmed = await showConfirmDialog(
            'ยืนยันการลบ',
            'คุณต้องการลบรายการชำระเงินนี้หรือไม่? การดำเนินการนี้ไม่สามารถย้อนกลับได้',
            'delete'
          );
        } else {
          confirmed = confirm('คุณต้องการลบรายการชำระเงินนี้หรือไม่?');
        }
        if (confirmed) {
          await doDeletePayment(payId);
        }
      }

      async function doDeletePayment(payId) {
        try {
          const formData = new FormData();
          formData.append('pay_id', payId);

          const response = await fetch('../Manage/delete_payment.php', {
            method: 'POST',
            body: formData
          });

          let data;
          try {
            data = await response.json();
          } catch (err) {
            console.error('Delete payment: invalid JSON', err);
            if (typeof showErrorToast === 'function') {
              showErrorToast('ลบไม่สำเร็จ: ตอบกลับไม่ถูกต้อง');
            } else {
              alert('ลบไม่สำเร็จ: ตอบกลับไม่ถูกต้อง');
            }
            return;
          }

          if (data.success) {
            if (typeof showSuccessToast === 'function') {
              showSuccessToast(data.message || 'ลบรายการเรียบร้อย');
            }
            setTimeout(() => location.reload(), 500);
          } else {
            console.error('Delete payment error:', data.error);
            if (typeof showErrorToast === 'function') {
              showErrorToast(data.error || 'เกิดข้อผิดพลาด');
            } else {
              alert(data.error || 'เกิดข้อผิดพลาด');
            }
          }
        } catch (err) {
          console.error(err);
          if (typeof showErrorToast === 'function') {
            showErrorToast('เกิดข้อผิดพลาดในการเชื่อมต่อ');
          } else {
            alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
          }
        }
      }

      // Keyboard shortcut to close modal
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          closeProofModal();
        }
      });
    </script>
    <script>
      // Small UI animations for payments page
      document.addEventListener('DOMContentLoaded', function() {
        // animate coins
        const coin = document.querySelector('.coin-animated');
        if (coin) {
          coin.classList.add('spin');
          setTimeout(() => coin.classList.remove('spin'), 8000);
        }

        // animate stat cards
        const statCards = document.querySelectorAll('.payment-stat-card');
        statCards.forEach((card, i) => {
          card.style.animation = 'fadeInUp 0.6s cubic-bezier(.16,1,.3,1) forwards';
          card.style.animationDelay = (i * 0.08) + 's';
        });
      });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4" type="text/javascript"></script>
    <script>
      function applyPaymentsView(mode) {
        const tableWrap = document.getElementById('paymentsTableWrap');
        const rowWrap = document.getElementById('paymentsRowView');
        const toggleText = document.getElementById('paymentsViewToggleText');
        if (!tableWrap || !rowWrap) return;

        const normalized = mode === 'row' ? 'row' : 'table';
        tableWrap.classList.toggle('is-hidden', normalized === 'row');
        rowWrap.classList.toggle('is-hidden', normalized !== 'row');
        tableWrap.style.display = normalized === 'row' ? 'none' : 'block';
        rowWrap.style.display = normalized === 'row' ? 'grid' : 'none';
        tableWrap.style.visibility = 'visible';
        tableWrap.style.opacity = '1';
        rowWrap.style.visibility = 'visible';
        rowWrap.style.opacity = '1';

        if (toggleText) {
          toggleText.textContent = normalized === 'row' ? 'มุมมองตาราง' : 'มุมมองกริด';
        }

        try { localStorage.setItem('paymentsViewMode', normalized); } catch (e) {}
      }

      function ensurePaymentsViewVisible() {
        const tableWrap = document.getElementById('paymentsTableWrap');
        const rowWrap = document.getElementById('paymentsRowView');
        if (!tableWrap || !rowWrap) return;

        const tableHidden = tableWrap.classList.contains('is-hidden');
        const rowHidden = rowWrap.classList.contains('is-hidden');

        // Safety fallback: never allow both payment views to be hidden.
        if (tableHidden && rowHidden) {
          tableWrap.classList.remove('is-hidden');
          rowWrap.classList.add('is-hidden');
          tableWrap.style.display = 'block';
          rowWrap.style.display = 'none';
          const toggleText = document.getElementById('paymentsViewToggleText');
          if (toggleText) toggleText.textContent = 'มุมมองกริด';
        }

        tableWrap.style.visibility = 'visible';
        tableWrap.style.opacity = '1';
        rowWrap.style.visibility = 'visible';
        rowWrap.style.opacity = '1';

        const paymentsTable = document.getElementById('paymentsTable');
        if (paymentsTable) {
          paymentsTable.style.display = 'table';
          paymentsTable.style.visibility = 'visible';
          paymentsTable.style.opacity = '1';
        }
      }

      function togglePaymentsView() {
        const rowWrap = document.getElementById('paymentsRowView');
        const nextMode = rowWrap && rowWrap.classList.contains('is-hidden') ? 'row' : 'table';
        applyPaymentsView(nextMode);
      }

      // Initialize DataTable for payments table
      document.addEventListener('DOMContentLoaded', function() {
        // Always start with table view to avoid hidden-table state from stale localStorage.
        applyPaymentsView('table');

        ensurePaymentsViewVisible();

        ensurePaymentsViewVisible();
        applyFilters({ skipReload: true, updateHistory: false });

        // Status filter tabs – use delegated click handler for robustness
        const tabsContainer = document.getElementById('paymentFilterTabs');
        // clicking a filter tab should reload the page with the new status
        if (tabsContainer) {
          tabsContainer.addEventListener('click', function(e) {
            const tab = e.target.closest('.payment-filter-tab');
            if (!tab) return;
            e.preventDefault();
            tabsContainer.querySelectorAll('.payment-filter-tab').forEach(function(t) { t.classList.remove('active'); });
            tab.classList.add('active');
            paymentsActiveStatus = tab.dataset.status || '';
            // navigate to updated URL (applyFilters without skipReload)
            applyFilters();
          });
        } else {
          // Fallback: bind directly if container not present
          document.querySelectorAll('.payment-filter-tab').forEach(function(tab) {
            tab.addEventListener('click', function(e) {
              e.preventDefault();
              document.querySelectorAll('.payment-filter-tab').forEach(function(t) { t.classList.remove('active'); });
              this.classList.add('active');
              paymentsActiveStatus = tab.dataset.status || '';
              applyFilters();
            });
          });
        }

        // Animate progress bar fill
        const fill = document.getElementById('pcpBarFill');
        if (fill) {
          requestAnimationFrame(function() {
            setTimeout(function() {
              fill.style.width = (fill.dataset.target || '0') + '%';
            }, 250);
          });
        }
      });
    </script>
    <script src="/dormitory_management/Public/Assets/Js/futuristic-bright.js"></script>
  </body>
</html>
