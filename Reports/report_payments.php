<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

// โหลดค่าตั้งค่าระบบ
$siteName = 'Sangthian Dormitory';
$logoFilename = 'Logo.jpg';
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
        if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
    }
} catch (PDOException $e) {}

// Utility helpers
function renderCell(mixed $value): string {
  if ($value === null || $value === '') return '—';
  if (is_numeric($value)) return number_format((float)$value, 2);
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatThaiDate(?string $dateStr): string {
  if (!$dateStr) return '—';
  try {
    $dt = new DateTime($dateStr);
  } catch (Exception $e) {
    return htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8');
  }
  $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
  $day = $dt->format('j');
  $month = $months[(int)$dt->format('n') - 1] ?? $dt->format('m');
  $year = ((int)$dt->format('Y')) + 543 - 2500; // ให้ได้รูปแบบ 2 หลักแบบ พ.ศ. เช่น 68
  return $day . ' ' . $month . ' ' . str_pad((string)$year, 2, '0', STR_PAD_LEFT);
}

function timeAgoThai(?string $dateStr): string {
  if (!$dateStr) return '';
  try {
    $dt = new DateTime($dateStr, new DateTimeZone('Asia/Bangkok'));
    $now = new DateTime('now', new DateTimeZone('Asia/Bangkok'));
  } catch (Exception $e) {
    return '';
  }

  $diff = $now->getTimestamp() - $dt->getTimestamp();
  if ($diff < 0) return '';

  $units = [
    ['sec', 60, 'วินาที'],
    ['min', 3600, 'นาที'],
    ['hour', 86400, 'ชม.'],
    ['day', 2592000, 'วัน'],
    ['month', 31104000, 'เดือน'],
    ['year', PHP_INT_MAX, 'ปี'],
  ];

  if ($diff < 60) {
    return $diff . ' วินาทีที่แล้ว';
  }
  if ($diff < 3600) {
    $m = floor($diff / 60);
    return $m . ' นาทีที่แล้ว';
  }
  if ($diff < 86400) {
    $h = floor($diff / 3600);
    $m = floor(($diff % 3600) / 60);
    return $h . ' ชม.' . ($m > 0 ? ' ' . $m . ' นาที' : '') . 'ที่แล้ว';
  }
  if ($diff < 2592000) {
    $d = floor($diff / 86400);
    return $d . ' วันที่แล้ว';
  }
  if ($diff < 31104000) {
    $mo = floor($diff / 2592000);
    return $mo . ' เดือนที่แล้ว';
  }
  $y = floor($diff / 31104000);
  return $y . ' ปีที่แล้ว';
}

$rows = [];
$errorMessage = '';
$hasPayDate = true;
$hasPayStatus = true;
$hasPayAmount = true;
$hasPayProof = true;
$hasCtr = false;
$hasTnt = false;
$hasRoom = true;  // แสดงคอลัมน์ห้องเสมอเพราะดึงจาก JOIN
$hasNote = false;

// รับค่าเดือน/ปี ที่เลือก
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : '';
$selectedYear = isset($_GET['year']) ? $_GET['year'] : '';

// mapping column ชื่อ → ภาษาไทย
$columnLabels = [
  'pay_id'    => 'รหัส',
  'ctr_id'    => 'สัญญา',
  'tnt_id'    => 'ผู้เช่า',
  'room_id'   => 'ห้อง',
  'pay_amount'=> 'ยอดชำระ',
  'pay_date'  => 'วันที่ชำระ',
  'pay_status'=> 'สถานะ',
  'pay_proof' => 'หลักฐาน',
  'pay_note'  => 'หมายเหตุ',
];

try {
  $stmt = $pdo->query("SHOW COLUMNS FROM payment");
  $existingCols = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
  $hasPayDate = in_array('pay_date', $existingCols, true);
  $hasPayStatus = in_array('pay_status', $existingCols, true);
  $hasPayAmount = in_array('pay_amount', $existingCols, true);
  $hasPayProof = in_array('pay_proof', $existingCols, true);
  $hasCtr = false; // ไม่มีใน payment แต่จะดึงจาก expense
  $hasTnt = false; // ไม่มีใน payment
  $hasRoom = true;  // แสดงเสมอ - ดึงจาก JOIN
  $hasNote = in_array('pay_note', $existingCols, true);

  // สร้าง WHERE clause สำหรับกรองเดือน/ปี
  $whereClause = '';
  if ($selectedMonth && $selectedYear) {
    $whereClause = "WHERE YEAR(p.pay_date) = " . (int)$selectedYear . " AND MONTH(p.pay_date) = " . (int)$selectedMonth;
  } elseif ($selectedYear) {
    $whereClause = "WHERE YEAR(p.pay_date) = " . (int)$selectedYear;
  }

  $order = $hasPayDate ? 'ORDER BY p.pay_date DESC' : '';
  $sql = "SELECT p.*, e.exp_id, e.ctr_id as exp_ctr_id, c.room_id as contract_room_id, r.room_number 
          FROM payment p 
          LEFT JOIN expense e ON p.exp_id = e.exp_id
          LEFT JOIN contract c ON e.ctr_id = c.ctr_id
          LEFT JOIN room r ON c.room_id = r.room_id 
          $whereClause
          $order";
  $stmt = $pdo->query($sql);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // Debug: แสดงข้อมูลแถวแรก
  if (!empty($rows)) {
    error_log("Sample row: " . print_r($rows[0], true));
    // Debug output
    echo "<!-- DEBUG: Found " . count($rows) . " payment records -->";
  } else {
    echo "<!-- DEBUG: No payment records found -->";
  }
} catch (PDOException $e) {
  $errorMessage = $e->getMessage();
  echo "<!-- DEBUG ERROR: " . htmlspecialchars($errorMessage) . " -->";
}

// สรุปสถานะและยอดรวม (หากมีคอลัมน์ที่เกี่ยวข้อง)
$summary = [
  'pending' => null,
  'verified' => null,
  'total' => null,
  'range' => null,
];
try {
  // สร้าง WHERE สำหรับ summary query
  $summaryWhere = '';
  if ($selectedMonth && $selectedYear) {
    $summaryWhere = " WHERE YEAR(pay_date) = " . (int)$selectedYear . " AND MONTH(pay_date) = " . (int)$selectedMonth;
  } elseif ($selectedYear) {
    $summaryWhere = " WHERE YEAR(pay_date) = " . (int)$selectedYear;
  }

  if ($hasPayStatus) {
    $summary['pending'] = (int)($pdo->query("SELECT COUNT(*) FROM payment $summaryWhere" . ($summaryWhere ? " AND" : " WHERE") . " pay_status = 0")->fetchColumn());
    $summary['verified'] = (int)($pdo->query("SELECT COUNT(*) FROM payment $summaryWhere" . ($summaryWhere ? " AND" : " WHERE") . " pay_status = 1")->fetchColumn());
  }
  if ($hasPayAmount) {
    $summary['total'] = (float)($pdo->query("SELECT SUM(pay_amount) FROM payment $summaryWhere")->fetchColumn());
  }
  if ($hasPayDate) {
    $rangeStmt = $pdo->query("SELECT MIN(pay_date) as dmin, MAX(pay_date) as dmax FROM payment $summaryWhere");
    $range = $rangeStmt->fetch(PDO::FETCH_ASSOC);
    if (!empty($range['dmin']) && !empty($range['dmax'])) {
      $d1 = new DateTime($range['dmin']);
      $d2 = new DateTime($range['dmax']);
      $diffDays = (int)$d1->diff($d2)->format('%a') + 1;
      $summary['range'] = [
        'days' => $diffDays,
        'start' => $range['dmin'],
        'end' => $range['dmax'],
      ];
    }
  }
} catch (PDOException $e) {}

$statusLabels = [
  '0' => 'รอตรวจสอบ',
  '1' => 'ตรวจสอบแล้ว',
];

// ดึงรายการปีและเดือนที่มีในระบบ
$availableYears = [];
$availableMonths = [];
try {
  // ดึงปีที่มีข้อมูล
  $yearsStmt = $pdo->query("SELECT DISTINCT YEAR(pay_date) as year FROM payment WHERE pay_date IS NOT NULL ORDER BY year DESC");
  $availableYears = $yearsStmt->fetchAll(PDO::FETCH_COLUMN);
  
  // ดึงเดือนที่มีข้อมูล (ถ้าเลือกปีแล้ว)
  if ($selectedYear) {
    $monthsStmt = $pdo->query("SELECT DISTINCT MONTH(pay_date) as month FROM payment WHERE YEAR(pay_date) = " . (int)$selectedYear . " ORDER BY month ASC");
    $availableMonths = $monthsStmt->fetchAll(PDO::FETCH_COLUMN);
  } else {
    // ถ้าไม่เลือกปี แสดงทุกเดือนที่มีข้อมูล
    $monthsStmt = $pdo->query("SELECT DISTINCT MONTH(pay_date) as month FROM payment WHERE pay_date IS NOT NULL ORDER BY month ASC");
    $availableMonths = $monthsStmt->fetchAll(PDO::FETCH_COLUMN);
  }
} catch (PDOException $e) {}
?>
<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - รายงานการชำระเงิน</title>
    <link rel="icon" type="image/jpeg" href="..//Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="..//Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="..//Assets/Css/main.css" />
    <link rel="stylesheet" href="..//Assets/Css/lottie-icons.css" />
    <!-- DataTable Modern -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4/dist/style.css" />
    <link rel="stylesheet" href="..//Assets/Css/datatable-modern.css" />
    <style>
      .reports-container {
        width: 100%;
        max-width: 100%;
        padding: 0;
      }
      .reports-container .container {
        max-width: 100%;
        width: 100%;
        padding: 1.5rem;
      }
      .payment-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
      }
      .stat-card {
        background: rgba(15, 23, 42, 0.6);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        transition: transform 0.2s, box-shadow 0.2s;
      }
      .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
      }
      .stat-card-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
      }
      .stat-icon {
        font-size: 2.5rem;
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 12px;
      }
      .stat-label {
        font-size: 0.9rem;
        color: #cbd5e1;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 600;
      }
      .stat-value {
        font-size: 2.5rem;
        font-weight: 700;
        color: #f8fafc;
        margin: 0.5rem 0;
      }
      .stat-subtitle {
        font-size: 0.85rem;
        color: #94a3b8;
      }
      .payments-table-container {
        background: rgba(15, 23, 42, 0.6);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        overflow: hidden;
        margin-top: 2rem;
      }
      .table-header {
        padding: 1.5rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      }
      .table-title {
        font-size: 1.2rem;
        font-weight: 600;
        color: #f8fafc;
        margin: 0;
      }
      .payments-table {
        width: 100%;
        border-collapse: collapse;
      }
      .payments-table th,
      .payments-table td {
        padding: 1rem;
        text-align: left;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
      }
      .payments-table th {
        background: rgba(255, 255, 255, 0.05);
        color: #cbd5e1;
        font-weight: 600;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
      }
      .payments-table td {
        color: #e2e8f0;
      }
      .payments-table tbody tr:hover {
        background: rgba(255, 255, 255, 0.03);
      }
      .payments-table tbody tr:last-child td {
        border-bottom: none;
      }
      .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.4rem 0.8rem;
        border-radius: 6px;
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
      .proof-badge {
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
        transition: all 0.2s;
        cursor: pointer;
      }
      .proof-badge:hover {
        background: rgba(96, 165, 250, 0.25);
        transform: scale(1.05);
      }
      /* Modal Styles */
      .proof-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.85);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        animation: fadeIn 0.2s ease;
      }
      .proof-modal.active {
        display: flex;
      }
      .proof-modal-content {
        position: relative;
        max-width: 90vw;
        max-height: 90vh;
        background: rgba(15, 23, 42, 0.95);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.1);
        animation: slideUp 0.3s ease;
      }
      .proof-modal-header {
        padding: 1.5rem;
        background: rgba(255, 255, 255, 0.05);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
      }
      .proof-modal-title {
        font-size: 1.2rem;
        font-weight: 600;
        color: #f8fafc;
        margin: 0;
      }
      .proof-modal-close {
        width: 36px;
        height: 36px;
        border: none;
        background: rgba(255, 255, 255, 0.1);
        color: #f8fafc;
        font-size: 1.5rem;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
      }
      .proof-modal-close:hover {
        background: rgba(239, 68, 68, 0.2);
        color: #ef4444;
        transform: rotate(90deg);
      }
      .proof-modal-body {
        padding: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        max-height: calc(90vh - 100px);
        overflow: auto;
      }
      .proof-modal-body img {
        max-width: 100%;
        max-height: calc(90vh - 120px);
        object-fit: contain;
        border-radius: 8px;
      }
      @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
      }
      @keyframes slideUp {
        from {
          opacity: 0;
          transform: translateY(30px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }
      .amount-cell {
        font-weight: 700;
        color: #22c55e;
        font-size: 1.05rem;
      }
      .no-data {
        text-align: center;
        padding: 3rem;
        color: #94a3b8;
        font-size: 1.1rem;
      }
      .filter-section {
        background: rgba(15, 23, 42, 0.6);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
      }
      .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
      }
      .filter-item label {
        display: block;
        color: #cbd5e1;
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
      }
      .filter-item select,
      .filter-item input {
        width: 100%;
        padding: 0.75rem;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        color: #f8fafc;
        font-size: 0.9rem;
      }
      .filter-item select:focus,
      .filter-item input:focus {
        outline: none;
        border-color: #60a5fa;
        background: rgba(255, 255, 255, 0.08);
      }
    </style>
  </head>
  <body class="reports-page">
    <div class="app-shell">
      <?php 
      $currentPage = 'report_payments.php';
      include __DIR__ . '/../includes/sidebar.php'; 
      ?>
      <div class="app-main">
        <main class="reports-container">
          <div class="container">
            <?php 
              $pageTitle = 'รายงานการชำระเงิน';
              include __DIR__ . '/../includes/page_header.php'; 
            ?>

            <!-- ตัวกรองเดือน/ปี -->
            <div class="filter-section">
              <form method="GET" action="">
                <div class="filter-grid">
                  <div class="filter-item">
                    <label for="filterMonth">เดือน</label>
                    <select name="month" id="filterMonth">
                      <option value="">ทุกเดือน</option>
                      <?php
                        $thaiMonths = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 
                                       'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
                        foreach ($availableMonths as $m):
                          $selected = ($selectedMonth == $m) ? 'selected' : '';
                      ?>
                        <option value="<?php echo $m; ?>" <?php echo $selected; ?>>
                          <?php echo $thaiMonths[$m - 1]; ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="filter-item">
                    <label for="filterYear">ปี พ.ศ.</label>
                    <select name="year" id="filterYear" onchange="this.form.submit()">
                      <option value="">ทุกปี</option>
                      <?php
                        foreach ($availableYears as $dbYear):
                          $thaiYear = (int)$dbYear + 543;
                          $selected = ($selectedYear == $dbYear) ? 'selected' : '';
                      ?>
                        <option value="<?php echo $dbYear; ?>" <?php echo $selected; ?>>
                          <?php echo $thaiYear; ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="filter-item" style="display:flex;align-items:flex-end;gap:0.5rem;">
                    <button type="submit" style="flex:1;padding:0.75rem;background:#60a5fa;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;transition:all 0.2s;display:inline-flex;align-items:center;justify-content:center;gap:0.4rem;" onmouseover="this.style.background='#3b82f6'" onmouseout="this.style.background='#60a5fa'">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                      กรองข้อมูล
                    </button>
                    <?php if ($selectedMonth || $selectedYear): ?>
                      <a href="?" style="flex:1;padding:0.75rem;background:rgba(239,68,68,0.15);color:#ef4444;border:1px solid rgba(239,68,68,0.3);border-radius:8px;font-weight:600;text-align:center;text-decoration:none;transition:all 0.2s;" onmouseover="this.style.background='rgba(239,68,68,0.25)'" onmouseout="this.style.background='rgba(239,68,68,0.15)'">
                        ✕ ล้างตัวกรอง
                      </a>
                    <?php endif; ?>
                  </div>
                </div>
              </form>
            </div>

            <!-- สถิติภาพรวม -->
            <div class="payment-stats-grid">
              <div class="stat-card">
                <div class="stat-card-header">
                  <div class="lottie-icon purple">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                  </div>
                  <div class="stat-label">ทั้งหมด</div>
                </div>
                <div class="stat-value"><?php echo number_format(count($rows)); ?></div>
                <div class="stat-subtitle">รายการทั้งหมด</div>
              </div>

              <?php if ($hasPayStatus): ?>
              <div class="stat-card">
                <div class="stat-card-header">
                  <div class="lottie-icon orange">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  </div>
                  <div class="stat-label">รอตรวจสอบ</div>
                </div>
                <div class="stat-value" style="color:#fbbf24;"><?php echo number_format($summary['pending'] ?? 0); ?></div>
                <div class="stat-subtitle">รอดำเนินการ</div>
              </div>

              <div class="stat-card">
                <div class="stat-card-header">
                  <div class="lottie-icon green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                  </div>
                  <div class="stat-label">ตรวจสอบแล้ว</div>
                </div>
                <div class="stat-value" style="color:#22c55e;"><?php echo number_format($summary['verified'] ?? 0); ?></div>
                <div class="stat-subtitle">ยืนยันแล้ว</div>
              </div>
              <?php endif; ?>

              <?php if ($hasPayAmount && $summary['total'] !== null): ?>
              <div class="stat-card">
                <div class="stat-card-header">
                  <div class="lottie-icon yellow">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                  </div>
                  <div class="stat-label">ยอดรวม</div>
                </div>
                <div class="stat-value" style="color:#60a5fa;">฿<?php echo number_format($summary['total'], 2); ?></div>
                <div class="stat-subtitle">รายได้ทั้งหมด</div>
              </div>
              <?php endif; ?>
            </div>

            <!-- ตารางรายการชำระเงิน -->
            <div class="payments-table-container">
              <div class="table-header">
                <h2 class="table-title">รายการชำระเงินทั้งหมด</h2>
              </div>

              <?php if ($errorMessage): ?>
                <div class="no-data">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;vertical-align:middle;margin-right:6px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>เกิดข้อผิดพลาด: <?php echo htmlspecialchars($errorMessage); ?>
                </div>
              <?php elseif (empty($rows)): ?>
                <div class="no-data">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;vertical-align:middle;margin-right:6px;"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>ไม่มีข้อมูลการชำระเงิน
                </div>
              <?php else: ?>
                <table id="table-payments-report" class="payments-table">
                  <thead>
                    <tr>
                      <th>รหัส</th>
                      <th>ห้อง</th>
                      <?php if ($hasPayDate): ?><th>วันที่ชำระ</th><?php endif; ?>
                      <?php if ($hasPayAmount): ?><th style="text-align:right;">ยอดชำระ</th><?php endif; ?>
                      <?php if ($hasPayStatus): ?><th style="text-align:center;">สถานะ</th><?php endif; ?>
                      <?php if ($hasPayProof): ?><th style="text-align:center;">หลักฐาน</th><?php endif; ?>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($rows as $row): ?>
                      <tr>
                        <td><strong>#<?php echo htmlspecialchars((string)($row['pay_id'] ?? '')); ?></strong></td>
                        <td>
                          <?php if (!empty($row['room_number'])): ?>
                            <strong>ห้อง <?php echo htmlspecialchars((string)$row['room_number']); ?></strong>
                          <?php else: ?>
                            <span style="color:#94a3b8;">—</span>
                          <?php endif; ?>
                        </td>
                        <?php if ($hasPayDate): ?>
                          <td>
                            <?php 
                              echo formatThaiDate($row['pay_date'] ?? null);
                              $ago = timeAgoThai($row['pay_date'] ?? null);
                              if ($ago) echo '<br><small style="color:#94a3b8;">' . $ago . '</small>';
                            ?>
                          </td>
                        <?php endif; ?>
                        <?php if ($hasPayAmount): ?>
                          <td style="text-align:right;" class="amount-cell">
                            ฿<?php echo number_format((float)($row['pay_amount'] ?? 0), 2); ?>
                          </td>
                        <?php endif; ?>
                        <?php if ($hasPayStatus): ?>
                          <td style="text-align:center;">
                            <?php
                              $status = $row['pay_status'] ?? '';
                              if ($status === '0' || $status === 0):
                            ?>
                              <span class="status-badge status-pending"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:3px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>รอตรวจสอบ</span>
                            <?php elseif ($status === '1' || $status === 1): ?>
                              <span class="status-badge status-verified">✓ ตรวจสอบแล้ว</span>
                            <?php else: ?>
                              <span style="color:#94a3b8;">—</span>
                            <?php endif; ?>
                          </td>
                        <?php endif; ?>
                        <?php if ($hasPayProof): ?>
                          <td style="text-align:center;">
                            <?php if (!empty($row['pay_proof'])): ?>
                              <button type="button" 
                                      class="proof-badge" 
                                      onclick="openProofModal('<?php echo htmlspecialchars((string)$row['pay_proof']); ?>', '<?php echo htmlspecialchars((string)$row['pay_id']); ?>')"
                                      style="display:inline-flex;align-items:center;gap:4px;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                ดูหลักฐาน
                              </button>
                            <?php else: ?>
                              <span style="color:#94a3b8;">—</span>
                            <?php endif; ?>
                          </td>
                        <?php endif; ?>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php endif; ?>
            </div>

          </div>
        </main>
      </div>
    </div>

    <!-- Proof Modal -->
    <div id="proofModal" class="proof-modal" onclick="closeProofModal(event)">
      <div class="proof-modal-content" onclick="event.stopPropagation()">
        <div class="proof-modal-header">
          <h3 class="proof-modal-title" id="proofModalTitle">หลักฐานการชำระเงิน</h3>
          <button type="button" class="proof-modal-close" onclick="closeProofModal()">&times;</button>
        </div>
        <div class="proof-modal-body">
          <img id="proofImage" src="" alt="หลักฐานการชำระเงิน">
        </div>
      </div>
    </div>

    <script src="..//Assets/Javascript/animate-ui.js" defer></script>
    <script src="..//Assets/Javascript/main.js" defer></script>
    <script>
      function openProofModal(filename, payId) {
        const modal = document.getElementById('proofModal');
        const image = document.getElementById('proofImage');
        const title = document.getElementById('proofModalTitle');
        
        image.src = '..//Assets/Images/Payments/' + filename;
        title.textContent = 'หลักฐานการชำระเงิน #' + payId;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
      }

      function closeProofModal(event) {
        if (!event || event.target.id === 'proofModal') {
          const modal = document.getElementById('proofModal');
          modal.classList.remove('active');
          document.body.style.overflow = '';
        }
      }

      // Close modal with ESC key
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          closeProofModal();
        }
      });
    </script>
    
    <!-- DataTable Initialization -->
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4" defer></script>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        const paymentsTable = document.getElementById('table-payments-report');
        if (paymentsTable && typeof simpleDatatables !== 'undefined') {
          new simpleDatatables.DataTable(paymentsTable, {
            searchable: true,
            fixedHeight: false,
            perPage: 10,
            perPageSelect: [5, 10, 25, 50, 100],
            labels: {
              placeholder: 'ค้นหาการชำระเงิน...',
              perPage: 'รายการต่อหน้า',
              noRows: 'ไม่พบข้อมูลการชำระเงิน',
              info: 'แสดง {start} ถึง {end} จาก {rows} รายการ'
            }
          });
        }
      });
    </script>
  </body>
</html>
