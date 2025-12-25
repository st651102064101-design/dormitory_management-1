<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

// --- อัตโนมัติสร้างรายการค่าใช้จ่ายใหม่เมื่อถึงเดือนใหม่ ---
$today = new DateTime();
$currentMonth = $today->format('Y-m');
// ดึงสัญญาที่ยังไม่หมดอายุ
$contractsStmt = $pdo->query("SELECT ctr_id, ctr_start, ctr_end FROM contract WHERE ctr_status = '0'");
$contracts = $contractsStmt ? $contractsStmt->fetchAll(PDO::FETCH_ASSOC) : [];
foreach ($contracts as $contract) {
  $ctr_id = (int)$contract['ctr_id'];
  $ctr_start = (new DateTime($contract['ctr_start']))->format('Y-m');
  $ctr_end = (new DateTime($contract['ctr_end']))->format('Y-m');
  // ตรวจสอบเดือนล่าสุดที่บันทึก
  $lastExpStmt = $pdo->prepare("SELECT MAX(DATE_FORMAT(exp_month, '%Y-%m')) AS last_month FROM expense WHERE ctr_id = ?");
  $lastExpStmt->execute([$ctr_id]);
  $lastMonth = $lastExpStmt->fetchColumn();
  // ถ้าไม่มีเลย ให้เริ่มจาก ctr_start
  $nextMonth = $lastMonth ? (new DateTime($lastMonth . '-01'))->modify('+1 month')->format('Y-m') : $ctr_start;
  // ถ้า nextMonth <= currentMonth และ nextMonth <= ctr_end ให้สร้างรายการใหม่
  while ($nextMonth <= $currentMonth && $nextMonth <= $ctr_end) {
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
      $insert = $pdo->prepare("INSERT INTO expense (exp_month, exp_elec_unit, exp_water_unit, rate_elec, rate_water, room_price, exp_elec_chg, exp_water, exp_total, exp_status, ctr_id) VALUES (?, 0, 0, ?, ?, ?, 0, 0, ?, '0', ?)");
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

// ดึงข้อมูลค่าใช้จ่าย
$expStmt = $pdo->query("
  SELECT e.*,
         c.ctr_id, c.ctr_start, c.ctr_end,
         t.tnt_name, t.tnt_phone,
         r.room_number, r.room_id,
         rt.type_name
  FROM expense e
  LEFT JOIN contract c ON e.ctr_id = c.ctr_id
  LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
  LEFT JOIN room r ON c.room_id = r.room_id
  LEFT JOIN roomtype rt ON r.type_id = rt.type_id
  ORDER BY $orderBy
");
$expenses = $expStmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงข้อมูล payment สำหรับแต่ละ expense (เฉพาะที่อนุมัติแล้ว pay_status = '1')
$paymentsByExp = [];
$paymentStmt = $pdo->query("
  SELECT exp_id, pay_id, pay_date, pay_amount, pay_status
  FROM payment
  WHERE pay_status = '1'
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
  '0' => 'ยังไม่ชำระ',
  '1' => 'ชำระแล้ว',
  '2' => 'รอตรวจสอบ',
  '3' => 'ชำระยังไม่ครบ',
];
$statusColors = [
  '0' => '#ef4444',
  '1' => '#22c55e',
  '2' => '#ff9800',
  '3' => '#f59e0b',
];

// คำนวณสถิติ
$stats = [
  'unpaid' => 0,
  'paid' => 0,
  'pending' => 0,
  'partial' => 0,
  'total_unpaid' => 0,
  'total_paid' => 0,
  'total_pending' => 0,
  'total_partial' => 0,
];
foreach ($expenses as $exp) {
    $expStatus = (string)($exp['exp_status'] ?? '0');
    $expTotal = (int)($exp['exp_total'] ?? 0);
    if ($expStatus === '0') {
        $stats['unpaid']++;
        $stats['total_unpaid'] += $expTotal;
    } elseif ($expStatus === '1') {
        $stats['paid']++;
        $stats['total_paid'] += $expTotal;
    } elseif ($expStatus === '2') {
        $stats['pending']++;
        $stats['total_pending'] += $expTotal;
    } elseif ($expStatus === '3') {
        $stats['partial']++;
        $stats['total_partial'] += $expTotal;
    }
}

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
?>
<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - จัดการค่าใช้จ่าย</title>
    <link rel="icon" type="image/jpeg" href="..//Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="..//Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="..//Assets/Css/main.css" />
    <link rel="stylesheet" href="..//Assets/Css/confirm-modal.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4/dist/style.css" />
    <link rel="stylesheet" href="..//Assets/Css/datatable-modern.css" />
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
      .expense-stat-card {
        background: linear-gradient(135deg, rgba(18,24,40,0.85), rgba(7,13,26,0.95));
        border-radius: 16px;
        padding: 1.25rem;
        border: 1px solid rgba(255,255,255,0.08);
        color: #f5f8ff;
        box-shadow: 0 15px 35px rgba(3,7,18,0.4);
      }
      .expense-stat-card h3 {
        margin: 0;
        font-size: 0.95rem;
        color: rgba(255,255,255,0.7);
      }
      .expense-stat-card .stat-value {
        font-size: 2.2rem;
        font-weight: 700;
        margin-top: 0.5rem;
      }
      .expense-stat-card .stat-money {
        font-size: 1.1rem;
        color: rgba(255,255,255,0.6);
        margin-top: 0.5rem;
      }
      
      /* Light theme overrides for expense stat cards */
      @media (prefers-color-scheme: light) {
        .expense-stat-card {
          background: linear-gradient(135deg, rgba(243,244,246,0.95), rgba(229,231,235,0.85)) !important;
          border: 1px solid rgba(0,0,0,0.1) !important;
          color: #374151 !important;
          box-shadow: 0 2px 8px rgba(0,0,0,0.08) !important;
        }
        .expense-stat-card h3 {
          color: rgba(0,0,0,0.7) !important;
        }
        .expense-stat-card .stat-money {
          color: rgba(0,0,0,0.6) !important;
        }
      }
      
      /* JavaScript-detected light theme class */
      html.light-theme .expense-stat-card {
        background: linear-gradient(135deg, rgba(243,244,246,0.95), rgba(229,231,235,0.85)) !important;
        border: 1px solid rgba(0,0,0,0.1) !important;
        color: #374151 !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08) !important;
      }
      
      html.light-theme .expense-stat-card h3 {
        color: rgba(0,0,0,0.7) !important;
      }
      
      html.light-theme .expense-stat-card .stat-money {
        color: rgba(0,0,0,0.6) !important;
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
      .reports-page .manage-panel { margin-top: 1.4rem; margin-bottom: 1.4rem; background: #0f172a; border: 1px solid rgba(148,163,184,0.2); box-shadow: 0 12px 30px rgba(0,0,0,0.2); }
      .reports-page .manage-panel:first-of-type { margin-top: 0.2rem; }
      
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
                <div style="margin-top:0.5rem;padding:0.75rem 1.25rem;background:rgba(56,189,248,0.12);border-radius:8px;color:#0369a1;font-size:1rem;">
                  <strong>ระบบจะเพิ่มรายการค่าใช้จ่ายใหม่ให้อัตโนมัติทุกเดือน</strong> สำหรับสัญญาที่กำลังใช้งาน โดยไม่ต้องบันทึกเอง<br>
                  เมื่อถึงเดือนใหม่ ระบบจะสร้างรายการค่าใช้จ่ายของแต่ละห้องพักตามสัญญา จนถึงเดือนสุดท้ายของสัญญา โดยใช้ราคาห้องและอัตราค่าน้ำ/ไฟล่าสุด (หน่วยน้ำ/ไฟเริ่มต้นเป็น 0)<br>
                  ผู้ดูแลสามารถแก้ไขรายละเอียดแต่ละรายการได้ภายหลังตามจริง
                </div>
              </div>
            </div>
            <div class="expense-stats">
              <div class="expense-stat-card">
                <h3>ยังไม่ชำระ</h3>
                <div class="stat-value" style="color:#ef4444;"><?php echo number_format($stats['unpaid']); ?></div>
                <div class="stat-money">฿<?php echo number_format($stats['total_unpaid']); ?></div>
              </div>
              <div class="expense-stat-card">
                <h3>ชำระแล้ว</h3>
                <div class="stat-value" style="color:#22c55e;"><?php echo number_format($stats['paid']); ?></div>
                <div class="stat-money">฿<?php echo number_format($stats['total_paid']); ?></div>
              </div>
              <div class="expense-stat-card">
                <h3>ยอดรวมทั้งหมด</h3>
                <div class="stat-value"><?php echo number_format($stats['unpaid'] + $stats['paid']); ?></div>
                <div class="stat-money">฿<?php echo number_format($stats['total_unpaid'] + $stats['total_paid']); ?></div>
              </div>
            </div>
          </section>

          <!-- Toggle button for expense form -->
          <div style="margin:1.5rem 0;">
            <button type="button" id="toggleExpenseFormBtn" style="white-space:nowrap;padding:0.8rem 1.5rem;cursor:pointer;font-size:1rem;background:#1e293b;border:1px solid #334155;color:#cbd5e1;border-radius:8px;transition:all 0.2s;box-shadow:0 2px 4px rgba(0,0,0,0.1);" onclick="toggleExpenseForm()" onmouseover="this.style.background='#334155';this.style.borderColor='#475569'" onmouseout="this.style.background='#1e293b';this.style.borderColor='#334155'">
              <span id="toggleExpenseFormIcon">▼</span> <span id="toggleExpenseFormText">ซ่อนฟอร์ม</span>
            </button>
          </div>

          <section class="manage-panel" style="background:linear-gradient(135deg, rgba(15,23,42,0.95), rgba(2,6,23,0.95)); color:#f8fafc;" id="addExpenseSection">
            <div class="section-header">
              <div>
                <h1>บันทึกค่าใช้จ่ายใหม่</h1>
                <p style="margin-top:0.25rem;color:rgba(255,255,255,0.7);">เลือกสัญญาและระบุค่าใช้จ่ายประจำเดือน</p>
              </div>
            </div>
            <form action="../Manage/process_expense.php" method="post" id="expenseForm" data-allow-submit="true">
              <div class="expense-form">
                <div class="expense-form-group">
                  <label for="ctr_id">สัญญา / ห้องพัก <span style="color:#f87171;">*</span></label>
                  <select name="ctr_id" id="ctr_id" required>
                    <option value="">-- เลือกสัญญา --</option>
                    <?php foreach ($activeContracts as $ctr): ?>
                      <option value="<?php echo (int)$ctr['ctr_id']; ?>" 
                              data-room-price="<?php echo (int)($ctr['type_price'] ?? 0); ?>">
                        ห้อง <?php echo htmlspecialchars((string)($ctr['room_number'] ?? '-')); ?> - 
                        <?php echo htmlspecialchars($ctr['tnt_name'] ?? 'ไม่ระบุ'); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="expense-form-group">
                  <label for="exp_month">เดือน/ปีที่เรียกเก็บ <span style="color:#f87171;">*</span></label>
                  <input type="month" id="exp_month" name="exp_month" required value="<?php echo date('Y-m'); ?>" />
                </div>
                <div class="expense-form-group">
                  <label for="exp_elec_unit">หน่วยไฟ <span style="color:#f87171;">*</span></label>
                  <input type="number" id="exp_elec_unit" name="exp_elec_unit" min="0" step="1" required value="0" />
                </div>
                <div class="expense-form-group">
                  <label for="rate_elec">อัตราค่าไฟ/หน่วย (บาท) <span style="color:#f87171;">*</span></label>
                  <select id="rate_elec" name="rate_elec" required>
                    <?php foreach ($electricRates as $rate): ?>
                      <option value="<?php echo (float)($rate['rate_price'] ?? 0); ?>" data-rate-id="<?php echo (int)($rate['rate_id'] ?? 0); ?>">
                        ฿<?php echo number_format((float)($rate['rate_price'] ?? 0), 2); ?> / หน่วย
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="expense-form-group">
                  <label for="exp_water_unit">หน่วยน้ำ <span style="color:#f87171;">*</span></label>
                  <input type="number" id="exp_water_unit" name="exp_water_unit" min="0" step="1" required value="0" />
                </div>
                <div class="expense-form-group">
                  <div class="add-type-row">
                    <label for="rate_water" style="margin:0;">อัตราค่าน้ำ/หน่วย (บาท) <span style="color:#f87171;">*</span></label>
                    <div style="display:flex; gap:0.35rem;">
                      <button type="button" class="add-type-btn" id="addRateBtn">+ เพิ่มเรต</button>
                      <button type="button" class="add-type-btn delete-type-btn" id="deleteRateBtn">ลบเรต</button>
                    </div>
                  </div>
                  <select id="rate_water" name="rate_water" required>
                    <?php foreach ($waterRates as $rate): ?>
                      <option value="<?php echo (float)($rate['rate_price'] ?? 0); ?>" data-rate-id="<?php echo (int)($rate['rate_id'] ?? 0); ?>">
                        ฿<?php echo number_format((float)($rate['rate_price'] ?? 0), 2); ?> / หน่วย
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                
                <div class="calc-preview" id="calcPreview" style="display:none;">
                  <h4><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;vertical-align:middle;margin-right:6px;"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>สรุปค่าใช้จ่าย</h4>
                  <div class="calc-row">
                    <span>ค่าห้องพัก:</span>
                    <span id="preview_room">฿0</span>
                  </div>
                  <div class="calc-row">
                    <span>ค่าไฟ (<span id="preview_elec_unit">0</span> หน่วย × <span id="preview_elec_rate">0</span>):</span>
                    <span id="preview_elec">฿0</span>
                  </div>
                  <div class="calc-row">
                    <span>ค่าน้ำ (<span id="preview_water_unit">0</span> หน่วย × <span id="preview_water_rate">0</span>):</span>
                    <span id="preview_water">฿0</span>
                  </div>
                  <div class="calc-row total">
                    <span>ยอดรวมทั้งหมด:</span>
                    <span id="preview_total" style="color:#22c55e;">฿0</span>
                  </div>
                </div>

                <div class="expense-form-actions">
                  <button type="submit" class="animate-ui-add-btn" style="flex:2;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></svg>
                    บันทึกค่าใช้จ่าย
                  </button>
                  <button type="reset" class="animate-ui-action-btn delete" style="flex:1;">ล้างข้อมูล</button>
                </div>
              </div>
            </form>
          </section>

          <section class="manage-panel">
            <div class="section-header" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
              <div>
                <h1>รายการค่าใช้จ่ายทั้งหมด</h1>
                <p style="color:#94a3b8;margin-top:0.2rem;">ประวัติการเรียกเก็บค่าใช้จ่ายแต่ละห้อง</p>
              </div>
              <div style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
                <select id="sortSelect" onchange="changeSortBy(this.value)" style="padding:0.6rem 0.85rem;border-radius:8px;border:1px solid rgba(255,255,255,0.2);background:rgba(255,255,255,0.05);color:#f5f8ff;font-size:0.95rem;cursor:pointer;">
                  <option value="newest" <?php echo ($sortBy === 'newest' ? 'selected' : ''); ?>>เพิ่มล่าสุด</option>
                  <option value="oldest" <?php echo ($sortBy === 'oldest' ? 'selected' : ''); ?>>เพิ่มเก่าสุด</option>
                  <option value="room_number" <?php echo ($sortBy === 'room_number' ? 'selected' : ''); ?>>หมายเลขห้อง</option>
                </select>
                <label for="monthFilter" style="color:#94a3b8;font-size:0.9rem;font-weight:600;">กรองตามเดือน:</label>
                <select id="monthFilter" style="padding:0.5rem 0.75rem;border-radius:8px;background:rgba(15,23,42,0.9);color:#e2e8f0;border:1px solid rgba(148,163,184,0.35);font-size:0.9rem;min-width:150px;">
                  <option value="">ทั้งหมด</option>
                  <?php
                    // สร้างรายการเดือนที่มีข้อมูล
                    $months = [];
                    $thaiMonths = [
                      '01' => 'มกราคม', '02' => 'กุมภาพันธ์', '03' => 'มีนาคม', '04' => 'เมษายน',
                      '05' => 'พฤษภาคม', '06' => 'มิถุนายน', '07' => 'กรกฎาคม', '08' => 'สิงหาคม',
                      '09' => 'กันยายน', '10' => 'ตุลาคม', '11' => 'พฤศจิกายน', '12' => 'ธันวาคม'
                    ];
                    
                    foreach ($expenses as $exp) {
                      if ($exp['exp_month']) {
                        $monthKey = date('Y-m', strtotime($exp['exp_month']));
                        $month = date('m', strtotime($exp['exp_month']));
                        $year = date('Y', strtotime($exp['exp_month']));
                        $yearThai = (int)$year + 543; // แปลงเป็น พ.ศ.
                        $monthText = $thaiMonths[$month] . ' ' . $yearThai;
                        
                        if (!isset($months[$monthKey])) {
                          $months[$monthKey] = $monthText;
                        }
                      }
                    }
                    krsort($months); // เรียงจากใหม่ไปเก่า
                    
                    $isFirst = true;
                    foreach ($months as $key => $text) {
                      $selected = $isFirst ? ' selected' : '';
                      echo '<option value="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</option>';
                      $isFirst = false;
                    }
                  ?>
                </select>
              </div>
            </div>
            <div class="report-table">
              <table class="table--compact" id="table-expenses">
                <thead>
                  <tr>
                    <th>รหัส</th>
                    <th>ห้อง/ผู้เช่า</th>
                    <th>เดือน/ปี</th>
                    <th style="text-align:right;">ค่าห้อง</th>
                    <th style="text-align:right;">ค่าไฟ</th>
                    <th style="text-align:right;">ค่าน้ำ</th>
                    <th style="text-align:right;">ยอดรวม</th>
                    <th>สถานะ</th>
                    <th>การชำระเงิน</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($expenses)): ?>
                    <tr>
                      <td colspan="9" style="text-align:center;padding:2rem;color:#64748b;">ยังไม่มีข้อมูลค่าใช้จ่าย</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($expenses as $exp): ?>
                      <tr>
                        <td>#<?php echo str_pad((string)$exp['exp_id'], 4, '0', STR_PAD_LEFT); ?></td>
                        <td>
                          <div class="expense-table-room">
                            <span>ห้อง <?php echo htmlspecialchars((string)($exp['room_number'] ?? '-')); ?></span>
                            <span class="expense-meta"><?php echo htmlspecialchars($exp['tnt_name'] ?? '-'); ?></span>
                          </div>
                        </td>
                        <td><?php echo $exp['exp_month'] ? date('m/Y', strtotime($exp['exp_month'])) : '-'; ?></td>
                        <td style="text-align:right;">
                          ฿<?php echo number_format((int)($exp['room_price'] ?? 0)); ?>
                          <div class="expense-meta">ประเภท: <?php echo htmlspecialchars($exp['type_name'] ?? '-'); ?></div>
                        </td>
                        <td style="text-align:right;">
                          <?php
                            $elecUnits = (int)($exp['exp_elec_unit'] ?? 0);
                            $elecTotal = (int)($exp['exp_elec_chg'] ?? 0);
                            $elecRate = $elecUnits > 0 ? $elecTotal / $elecUnits : 0;
                          ?>
                          <div><strong>ใช้ไฟ <?php echo number_format($elecUnits); ?></strong> หน่วย</div>
                          <div class="expense-meta">฿<?php echo number_format($elecRate, 2); ?> / หน่วย</div>
                          <div class="expense-meta">ยอดการใช้ไฟ: ฿<?php echo number_format($elecTotal); ?></div>
                        </td>
                        <td style="text-align:right;">
                          <?php
                            $waterUnits = (int)($exp['exp_water_unit'] ?? 0);
                            $waterTotal = (int)($exp['exp_water'] ?? 0);
                            $waterRate = $waterUnits > 0 ? $waterTotal / $waterUnits : 0;
                          ?>
                          <div><strong><?php echo number_format($waterUnits); ?></strong> หน่วย</div>
                          <div class="expense-meta">฿<?php echo number_format($waterRate, 2); ?> / หน่วย</div>
                          <div class="expense-meta">ยอดการใช้น้ำ: ฿<?php echo number_format($waterTotal); ?></div>
                        </td>
                        <td style="text-align:right;"><strong style="color:#22c55e;">฿<?php echo number_format((int)($exp['exp_total'] ?? 0)); ?></strong></td>
                        <td>
                          <?php $status = (string)($exp['exp_status'] ?? ''); ?>
                          <span class="status-badge" style="background: <?php echo $statusColors[$status] ?? '#94a3b8'; ?>;">
                            <?php echo $statusMap[$status] ?? 'ไม่ระบุ'; ?>
                          </span>
                        </td>
                        <td class="crud-column">
                          <?php
                            // ใช้ข้อมูล payment ที่ดึงมาแล้ว
                            $expId = (int)$exp['exp_id'];
                            $payData = $paymentsByExp[$expId] ?? ['total_paid' => 0, 'count' => 0, 'payments' => []];
                            $paidAmount = $payData['total_paid'];
                            $paymentCount = $payData['count'];
                            $expTotal = (int)($exp['exp_total'] ?? 0);
                            $remainAmount = $expTotal - $paidAmount;
                          ?>
                          <div style="font-size:0.85rem;line-height:1.6;">
                            <div style="display:flex;align-items:center;gap:0.35rem;">
                              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                              ชำระแล้ว: <strong style="color:#22c55e;">฿<?php echo number_format($paidAmount); ?></strong>
                            </div>
                            <div style="color:#94a3b8;margin-top:0.2rem;display:flex;align-items:center;gap:0.35rem;">
                              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                              ค้างชำระ: <strong style="color:<?php echo $remainAmount > 0 ? '#ef4444' : '#22c55e'; ?>;">฿<?php echo number_format($remainAmount); ?></strong>
                            </div>
                            <?php if ($paymentCount > 0): ?>
                            <div style="margin-top:0.35rem;padding:0.35rem 0.5rem;background:rgba(34,197,94,0.1);border-radius:6px;display:inline-flex;align-items:center;gap:0.3rem;">
                              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
                              <span style="color:#22c55e;font-size:0.8rem;"><?php echo $paymentCount; ?> ครั้ง</span>
                            </div>
                            <?php else: ?>
                            <div style="margin-top:0.35rem;color:#64748b;font-size:0.8rem;">ยังไม่มีการชำระ</div>
                            <?php endif; ?>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </section>
        </div>
      </main>
    </div>

    <!-- Proof Modal -->
    <div id="paymentProofModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
      background:rgba(0,0,0,0);z-index:9999;
      transition:background 0.45s cubic-bezier(0.32, 0.72, 0, 1);opacity:0;
      padding:2rem;box-sizing:border-box;align-items:center;justify-content:center;">
      
      <button id="closePaymentProofModal" style="position:fixed;top:1rem;right:1rem;
        background:rgba(255,255,255,0.15);color:#fff;border:none;width:3rem;height:3rem;
        border-radius:50%;cursor:pointer;font-size:1.5rem;font-weight:600;
        transition:all 0.4s cubic-bezier(0.32, 0.72, 0, 1);backdrop-filter:blur(20px) saturate(180%);
        transform:scale(0.7);opacity:0;box-shadow:0 8px 32px rgba(0,0,0,0.3);z-index:10000;">✕</button>
        
      <div id="paymentProofContent" style="width:75vw;max-width:75vw;max-height:80vh;overflow-y:auto;
        background:rgba(15,23,42,0.75);border-radius:20px;padding:1.5rem;
        backdrop-filter:blur(40px) saturate(180%);box-shadow:0 25px 50px -12px rgba(0,0,0,0.6),
        0 0 1px rgba(255,255,255,0.1) inset;transition:all 0.5s cubic-bezier(0.32, 0.72, 0, 1);
        opacity:0;border:1px solid rgba(255,255,255,0.08);
        text-align:center;position:absolute;left:50%;top:50%;transform:translate(-50%,-50%) scale(0.85);">
        
        <img id="paymentProofImage" src="" alt="หลักฐานการชำระเงิน" 
          style="width:100%;height:auto;border-radius:12px;display:none;
          transition:opacity 0.5s cubic-bezier(0.32, 0.72, 0, 1),transform 0.5s cubic-bezier(0.32, 0.72, 0, 1);
          transform:scale(0.98);" />
        
        <embed id="paymentProofEmbed" src="" type="application/pdf" 
          style="width:80vw;height:80vh;border-radius:12px;display:none;
          transition:opacity 0.5s cubic-bezier(0.32, 0.72, 0, 1);" />
        
        <div id="paymentProofError" style="display:none;color:#f87171;padding:2rem;text-align:center;
          font-size:1.1rem;transition:opacity 0.5s cubic-bezier(0.32, 0.72, 0, 1);">
          ไม่สามารถแสดงไฟล์ได้
        </div>

        <div id="paymentProofList" style="display:none;">
          <!-- Payment list will be populated here -->
        </div>
      </div>
    </div>

    <script src="..//Assets/Javascript/toast-notification.js"></script>
    <script src="..//Assets/Javascript/confirm-modal.js"></script>
    <script src="..//Assets/Javascript/animate-ui.js"></script>
    <script src="..//Assets/Javascript/main.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4" defer></script>
    <script>
      function changeSortBy(sortValue) {
        const url = new URL(window.location);
        url.searchParams.set('sort', sortValue);
        window.location.href = url.toString();
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
        const statusText = statusNum === 1 ? 'ชำระแล้ว' : 'ยังไม่ชำระ';
        
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

      // Load expense list and stats
      function loadExpenseList() {
        fetch(window.location.href)
          .then(response => response.text())
          .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // Update table
            const newTable = doc.querySelector('#table-expenses tbody');
            const currentTable = document.querySelector('#table-expenses tbody');
            if (newTable && currentTable) {
              currentTable.innerHTML = newTable.innerHTML;
              // Re-setup status buttons after updating table
              setupStatusButtons();
            }
            
            // Update stats cards
            const statsCards = doc.querySelectorAll('.expense-stat-card .stat-value');
            const currentStats = document.querySelectorAll('.expense-stat-card .stat-value');
            if (statsCards.length === currentStats.length) {
              statsCards.forEach((stat, i) => {
                if (currentStats[i]) {
                  currentStats[i].textContent = stat.textContent;
                }
              });
            }
            
            // Update stat money values
            const statsMoney = doc.querySelectorAll('.expense-stat-card .stat-money');
            const currentMoney = document.querySelectorAll('.expense-stat-card .stat-money');
            if (statsMoney.length === currentMoney.length) {
              statsMoney.forEach((money, i) => {
                if (currentMoney[i]) {
                  currentMoney[i].textContent = money.textContent;
                }
              });
            }
          })
          .catch(error => {
            console.error('Error loading expense list:', error);
          });
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
                  { select: [7, 8], sortable: false }
                ]
              });
              window.__expenseDataTable = dt;
            } catch (err) {
              console.error('Failed to init expense table', err);
            }
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
      }

      (function setupExpenseCalculator() {
        const ctrSelect = document.getElementById('ctr_id');
        const elecUnit = document.getElementById('exp_elec_unit');
        const elecRate = document.getElementById('rate_elec');
        const waterUnit = document.getElementById('exp_water_unit');
        const waterRate = document.getElementById('rate_water');
        const preview = document.getElementById('calcPreview');
        
        function updatePreview() {
          const selectedOpt = ctrSelect.options[ctrSelect.selectedIndex];
          const roomPrice = selectedOpt ? parseInt(selectedOpt.dataset.roomPrice || '0') : 0;
          const elecU = parseInt(elecUnit.value || '0');
          const elecR = parseFloat(elecRate.value || '0');
          const waterU = parseInt(waterUnit.value || '0');
          const waterR = parseFloat(waterRate.value || '0');
          
          const elecChg = Math.round(elecU * elecR);
          const waterChg = Math.round(waterU * waterR);
          const total = roomPrice + elecChg + waterChg;
          
          if (roomPrice > 0 || elecU > 0 || waterU > 0) {
            preview.style.display = 'block';
            document.getElementById('preview_room').textContent = '฿' + roomPrice.toLocaleString();
            document.getElementById('preview_elec_unit').textContent = elecU;
            document.getElementById('preview_elec_rate').textContent = elecR.toFixed(2);
            document.getElementById('preview_elec').textContent = '฿' + elecChg.toLocaleString();
            document.getElementById('preview_water_unit').textContent = waterU;
            document.getElementById('preview_water_rate').textContent = waterR.toFixed(2);
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

                // ฟังก์ชันสำหรับตรวจจับและปรับสี input fields ตาม theme
                function updateInputTheme() {
                  const themeColor = getComputedStyle(document.documentElement).getPropertyValue('--theme-bg-color').trim();
                  const bodyBg = getComputedStyle(document.body).backgroundColor;
                  
                  // ตรวจสอบว่าเป็นสีขาวหรือสีอ่อน
                  const isLightTheme = themeColor === '#fff' || themeColor === '#ffffff' || 
                                       themeColor === 'rgb(255, 255, 255)' || themeColor === 'white' ||
                                       bodyBg === 'rgb(255, 255, 255)' || bodyBg === '#fff' || bodyBg === '#ffffff';
                  
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
      const paymentProofImage = document.getElementById('paymentProofImage');
      const paymentProofEmbed = document.getElementById('paymentProofEmbed');
      const paymentProofError = document.getElementById('paymentProofError');
      const paymentProofContent = document.getElementById('paymentProofContent');
      const paymentProofList = document.getElementById('paymentProofList');

      // Open modal when clicking view payment button
      document.querySelectorAll('.view-payment-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
          const expenseId = this.getAttribute('data-expense-id');
          if (!expenseId) return;

          try {
            // Fetch payment records for this expense
            console.log('Fetching payments for expense:', expenseId);
            const response = await fetch('/Dormitory_Management/Reports/get_payment_proofs.php?exp_id=' + encodeURIComponent(expenseId), {
              credentials: 'include'
            });

            console.log('Response status:', response.status);
            const responseText = await response.text();
            console.log('Response text:', responseText);
            
            if (!response.ok) throw new Error('Failed to fetch payments: ' + response.status);

            const data = JSON.parse(responseText);
            console.log('Fetched data:', data);

            if (data.success && data.payments.length > 0) {
              // Show first payment
              showPaymentProof(data.payments[0]);

              // Build payment list if multiple proofs
              if (data.payments.length > 1) {
                buildPaymentList(data.payments);
              }
            } else {
              paymentProofError.textContent = 'ไม่พบหลักฐานการชำระเงิน';
              paymentProofError.style.display = 'block';
              paymentProofImage.style.display = 'none';
              paymentProofEmbed.style.display = 'none';
            }

            // Show modal
            requestAnimationFrame(() => {
              paymentProofModal.style.display = 'flex';
              requestAnimationFrame(() => {
                paymentProofModal.style.background = 'rgba(0,0,0,0.75)';
                paymentProofModal.style.opacity = '1';
                paymentProofContent.style.transform = 'translate(-50%,-50%) scale(1)';
                paymentProofContent.style.opacity = '1';
                closePaymentProofModal.style.transform = 'scale(1)';
                closePaymentProofModal.style.opacity = '1';
              });
            });
          } catch (error) {
            console.error('Error fetching payments:', error);
            paymentProofError.textContent = 'เกิดข้อผิดพลาดในการดึงข้อมูล';
            paymentProofError.style.display = 'block';
            paymentProofModal.style.display = 'block';
            paymentProofModal.style.background = 'rgba(0,0,0,0.75)';
            paymentProofModal.style.opacity = '1';
          }
        });
      });

      function showPaymentProof(payment) {
        // Hide all elements first
        paymentProofImage.style.display = 'none';
        paymentProofEmbed.style.display = 'none';
        paymentProofError.style.display = 'none';

        if (!payment.pay_proof) {
          paymentProofError.textContent = 'ไม่มีหลักฐานสำหรับการชำระนี้';
          paymentProofError.style.display = 'block';
          return;
        }

        const ext = payment.pay_proof.toLowerCase().split('.').pop();
        const isPdf = ext === 'pdf';
        const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);

        if (isPdf) {
          paymentProofEmbed.src = '..//Assets/Images/Payments/' + payment.pay_proof;
          paymentProofEmbed.style.display = 'block';
        } else if (isImage) {
          paymentProofImage.src = '..//Assets/Images/Payments/' + payment.pay_proof;
          paymentProofImage.style.display = 'block';
          paymentProofImage.style.opacity = '0';
          paymentProofImage.style.transform = 'scale(0.98)';
          
          paymentProofImage.onload = () => {
            paymentProofImage.style.transition = 'opacity 0.5s cubic-bezier(0.32, 0.72, 0, 1), transform 0.5s cubic-bezier(0.32, 0.72, 0, 1)';
            paymentProofImage.style.opacity = '1';
            paymentProofImage.style.transform = 'scale(1)';
          };
        } else {
          paymentProofError.textContent = 'ประเภทไฟล์ไม่รองรับ';
          paymentProofError.style.display = 'block';
        }
      }

      function buildPaymentList(payments) {
        paymentProofList.innerHTML = '<div style="margin-top:2rem;padding-top:1.5rem;border-top:1px solid rgba(255,255,255,0.1);"><p style="color:#94a3b8;font-size:0.9rem;margin-bottom:1rem;">หลักฐานการชำระอื่นๆ:</p>';
        
        payments.slice(1).forEach((payment, index) => {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.textContent = `หลักฐาน ${index + 2}`;
          btn.style.cssText = `
            display: inline-block;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(255,255,255,0.1);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.85rem;
            font-weight: 500;
          `;
          
          btn.addEventListener('mouseover', function() {
            this.style.background = 'rgba(255,255,255,0.15)';
            this.style.borderColor = 'rgba(255,255,255,0.3)';
          });
          
          btn.addEventListener('mouseout', function() {
            this.style.background = 'rgba(255,255,255,0.1)';
            this.style.borderColor = 'rgba(255,255,255,0.2)';
          });
          
          btn.addEventListener('click', () => {
            showPaymentProof(payment);
          });
          
          paymentProofList.appendChild(btn);
        });
        
        paymentProofList.innerHTML += '</div>';
        paymentProofList.style.display = 'block';
      }

      // Close modal
      function closeModal() {
        paymentProofModal.style.background = 'rgba(0,0,0,0)';
        paymentProofModal.style.opacity = '0';
        paymentProofContent.style.transform = 'translate(-50%,-50%) scale(0.85)';
        paymentProofContent.style.opacity = '0';
        closePaymentProofModal.style.transform = 'scale(0.7)';
        closePaymentProofModal.style.opacity = '0';

        setTimeout(() => {
          paymentProofModal.style.display = 'none';
        }, 450);
      }

      closePaymentProofModal.addEventListener('click', closeModal);

      paymentProofModal.addEventListener('click', function(e) {
        if (e.target === this) closeModal();
      });

      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && paymentProofModal.style.display !== 'none') {
          closeModal();
        }
      });
    </script>

    <!-- Month Filter Script -->
    <script>
      (function() {
        const monthFilter = document.getElementById('monthFilter');
        const tableBody = document.querySelector('#table-expenses tbody');
        
        if (!monthFilter || !tableBody) return;
        
        // Trigger filter on page load
        function filterByMonth() {
          const selectedMonth = monthFilter.value;
          const rows = tableBody.querySelectorAll('tr');
          
          rows.forEach(row => {
            // ข้าม row ที่เป็นข้อความ "ยังไม่มีข้อมูล"
            if (row.cells.length === 1) {
              row.style.display = '';
              return;
            }
            
            if (!selectedMonth) {
              // แสดงทั้งหมด
              row.style.display = '';
            } else {
              // ดึงข้อมูลเดือน/ปีจากคอลัมน์ที่ 3 (index 2)
              const dateCell = row.cells[2];
              if (dateCell) {
                const dateText = dateCell.textContent.trim(); // format: MM/YYYY
                const [month, year] = dateText.split('/');
                const rowMonth = `${year}-${month}`; // format: YYYY-MM
                
                if (rowMonth === selectedMonth) {
                  row.style.display = '';
                } else {
                  row.style.display = 'none';
                }
              }
            }
          });
          
          // ตรวจสอบว่ามีแถวที่แสดงหรือไม่
          const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
          if (visibleRows.length === 0 && rows.length > 0) {
            // ถ้าไม่มีแถวที่แสดง แสดงข้อความ
            const noDataRow = document.createElement('tr');
            noDataRow.id = 'no-data-filtered';
            noDataRow.innerHTML = '<td colspan="9" style="text-align:center;padding:2rem;color:#64748b;">ไม่พบข้อมูลในเดือนที่เลือก</td>';
            
            // ลบ row เก่าถ้ามี
            const oldNoData = document.getElementById('no-data-filtered');
            if (oldNoData) oldNoData.remove();
            
            tableBody.appendChild(noDataRow);
          } else {
            // ลบข้อความ "ไม่พบข้อมูล" ถ้ามี
            const noDataRow = document.getElementById('no-data-filtered');
            if (noDataRow) noDataRow.remove();
          }
        }
        
        monthFilter.addEventListener('change', filterByMonth);
        
        // เรียกใช้ filter ทันทีเมื่อโหลดหน้า
        if (monthFilter.value) {
          filterByMonth();
        }
        
        // เพิ่มการจัดการ theme สำหรับ select
        function updateSelectTheme() {
          const themeColor = getComputedStyle(document.documentElement).getPropertyValue('--theme-bg-color').trim();
          const bodyBg = getComputedStyle(document.body).backgroundColor;
          
          const isLightTheme = themeColor === '#fff' || themeColor === '#ffffff' || 
                               themeColor === 'rgb(255, 255, 255)' || themeColor === 'white' ||
                               bodyBg === 'rgb(255, 255, 255)' || bodyBg === '#fff' || bodyBg === '#ffffff';
          
          if (isLightTheme) {
            monthFilter.style.background = '#ffffff';
            monthFilter.style.color = '#111827';
            monthFilter.style.borderColor = '#d1d5db';
          } else {
            monthFilter.style.background = 'rgba(15,23,42,0.9)';
            monthFilter.style.color = '#e2e8f0';
            monthFilter.style.borderColor = 'rgba(148,163,184,0.35)';
          }
        }
        
        updateSelectTheme();
        
        // Light theme detection - apply class to html element if theme color is light
        function applyThemeClass() {
          const themeColor = getComputedStyle(document.documentElement).getPropertyValue('--theme-bg-color').trim().toLowerCase();
          // ตรวจสอบว่า theme color เป็นสีขาวหรือสีอ่อนเบา (light colors)
          const isLight = /^(#fff|#ffffff|rgb\(25[0-5],\s*25[0-5],\s*25[0-5]\)|rgb\(\s*255\s*,\s*255\s*,\s*255\s*\))$/i.test(themeColor.trim());
          if (isLight) {
            document.documentElement.classList.add('light-theme');
          } else {
            document.documentElement.classList.remove('light-theme');
          }
          console.log('Theme color:', themeColor, 'Is light:', isLight);
        }
        applyThemeClass();
        
        const themeObserver = new MutationObserver(updateSelectTheme);
        themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['style'] });
        themeObserver.observe(document.body, { attributes: true, attributeFilter: ['style'] });
      })();
    </script>
  </body>
</html>
