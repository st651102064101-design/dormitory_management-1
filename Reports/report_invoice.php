<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

// ดึงค่า default_view_mode จาก database
$defaultViewMode = 'grid';
try {
    $viewStmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'default_view_mode' LIMIT 1");
    $viewRow = $viewStmt->fetch(PDO::FETCH_ASSOC);
    if ($viewRow && strtolower($viewRow['setting_value']) === 'list') {
        $defaultViewMode = 'list';
    }
} catch (PDOException $e) {}

$monthNames = [
    1 => 'มกราคม',   2 => 'กุมภาพันธ์', 3 => 'มีนาคม',    4 => 'เมษายน',
    5 => 'พฤษภาคม',  6 => 'มิถุนายน',   7 => 'กรกฎาคม',   8 => 'สิงหาคม',
    9 => 'กันยายน',  10 => 'ตุลาคม',    11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
];

// ดึงข้อมูลทั้งหมด ไม่รวมเดือนในอนาคต
$stmt = $pdo->query("SELECT e.*, c.ctr_id, t.tnt_name, r.room_number
    FROM expense e
    LEFT JOIN contract c ON e.ctr_id = c.ctr_id
    LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
    LEFT JOIN room r ON c.room_id = r.room_id
    WHERE DATE_FORMAT(e.exp_month, '%Y-%m') <= DATE_FORMAT(CURDATE(), '%Y-%m')
    ORDER BY e.exp_month DESC");
error_reporting(E_ALL);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// สร้างตัวเลือก dropdown สำหรับ client-side filter
$filterRoomOptions     = [];
$availableMonthOptions = [];
$availableYearOptions  = [];
foreach ($rows as $row) {
    $rn = $row['room_number'] ?? '';
    if ($rn !== '' && !in_array($rn, $filterRoomOptions, true)) $filterRoomOptions[] = $rn;
    if (!empty($row['exp_month'])) {
        $m = (int)date('n', strtotime($row['exp_month']));
        $y = (int)date('Y', strtotime($row['exp_month']));
        if (!in_array($m, $availableMonthOptions, true)) $availableMonthOptions[] = $m;
        if (!in_array($y, $availableYearOptions, true))  $availableYearOptions[]  = $y;
    }
}
natsort($filterRoomOptions);
$filterRoomOptions = array_values($filterRoomOptions);
sort($availableMonthOptions);
rsort($availableYearOptions);

$statusLabels = [
  '0' => 'รอชำระ',
  '1' => 'ชำระแล้ว',
  '2' => 'รอตรวจสอบ',
  '3' => 'ชำระยังไม่ครบ',
  '4' => 'ค้างชำระ',
];

function renderField(?string $value, string $fallback = '—'): string
{
  return htmlspecialchars(($value === null || $value === '') ? $fallback : $value, ENT_QUOTES, 'UTF-8');
}

function renderNumber(mixed $value): string
{
  if ($value === null || $value === '') {
    return '0';
  }
  return number_format((int)$value);
}

// ฟังก์ชันแสดงเวลาที่ผ่านมา (relative time)
function getRelativeTime(?string $datetime): string
{
  if (!$datetime) return 'ยังไม่ระบุ';
  
  try {
    $date = new DateTime($datetime);
    $now = new DateTime();
    $interval = $now->diff($date);
    
    if ($interval->y > 0) {
      return $interval->y . ' ปีที่แล้ว';
    }
    if ($interval->m > 0) {
      return $interval->m . ' เดือนที่แล้ว';
    }
    if ($interval->d > 0) {
      return $interval->d . ' วันที่แล้ว';
    }
    if ($interval->h > 0) {
      return $interval->h . ' ชั่วโมงที่แล้ว';
    }
    if ($interval->i > 0) {
      return $interval->i . ' นาทีที่แล้ว';
    }
    if ($interval->s > 0) {
      return $interval->s . ' วินาทีที่แล้ว';
    }
    return 'เพิ่งเดี๋ยวนี้';
  } catch (Exception $e) {
    return 'เวลาไม่ถูกต้อง';
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
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - รายงานใบแจ้งชำระเงิน</title>
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/particle-effects.css">
    <script src="/dormitory_management/Public/Assets/Javascript/particle-effects.js"></script>
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/lottie-icons.css" />
    <!-- DataTable Modern -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4/dist/style.css" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/datatable-modern.css" />
    <style>
      .reports-container {
        width: 100%;
        max-width: 100%;
        padding: 0;
      }
      .reports-container .container {
        max-width: 100%;
        width: 100%;
          padding: 0 1.5rem 1.5rem;
      }
      .invoice-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
      }
      .stat-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
        transition: transform 0.2s, box-shadow 0.2s;
      }
      .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 26px rgba(15, 23, 42, 0.12);
      }
      .stat-icon {
        font-size: 2.5rem;
        margin-bottom: 0.5rem;
      }
      .stat-label {
        font-size: 0.85rem;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 600;
      }
      .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0.5rem 0;
      }
      .view-toggle {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 2rem;
      }
      .view-toggle-btn {
        padding: 0.75rem 1.5rem;
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        color: #64748b;
        cursor: pointer;
        transition: all 0.2s;
        font-weight: 600;
      }
      .view-toggle-btn.active {
        background: #60a5fa;
        border-color: #60a5fa;
        color: #fff;
      }
      .view-toggle-btn:hover:not(.active) {
        background: #f1f5f9;
        color: #1f2937;
      }
      .invoice-cards {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
        gap: 1.5rem;
      }
      .invoice-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 1.5rem;
        transition: all 0.2s;
      }
      .invoice-card:hover {
        transform: translateY(-2px);
        border-color: #93c5fd;
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12);
      }
      .invoice-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        padding-bottom: 1rem;
        border-bottom: 1px solid #e5e7eb;
        margin-bottom: 1rem;
      }
      .invoice-month {
        font-size: 1.3rem;
        font-weight: 700;
        color: #0f172a;
      }
      .invoice-status {
        padding: 0.4rem 0.8rem;
        border-radius: 6px;
        font-size: 0.75rem;
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
      .status-overdue {
        background: rgba(153, 27, 27, 0.15);
        color: #dc2626;
        border: 1px solid rgba(153, 27, 27, 0.3);
      }
      .invoice-info {
        background: #f8fafc;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
      }
      .invoice-charges {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
        margin-bottom: 1rem;
      }
      .charge-item {
        background: #f8fafc;
        padding: 0.75rem;
        border-radius: 8px;
        text-align: center;
      }
      .charge-label {
        font-size: 0.75rem;
        color: #64748b;
        margin-bottom: 0.3rem;
      }
      .charge-value {
        font-size: 1.1rem;
        font-weight: 700;
      }
      .invoice-total {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        padding: 1rem;
        border-radius: 8px;
        text-align: center;
      }
      .total-label {
        font-size: 0.85rem;
        color: #1e40af;
        margin-bottom: 0.3rem;
      }
      .total-value {
        font-size: 2rem;
        font-weight: 700;
        color: #1d4ed8;
      }
      .invoice-table {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        overflow: hidden;
      }
      .invoice-table table {
        width: 100%;
        border-collapse: collapse;
      }
      .invoice-table th,
      .invoice-table td {
        padding: 1rem;
        text-align: left;
        border-bottom: 1px solid #eef2f7;
      }
      .invoice-table th {
        background: #f8fafc;
        color: #334155;
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
      }
      .invoice-table td {
        color: #1f2937;
      }
      .invoice-table tbody tr:hover {
        background: #f8fafc;
      }
      .filter-section {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
      }
      .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
      }
      .filter-item label {
        display: block;
        color: #475569;
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
      }
      .filter-item select {
        width: 100%;
        padding: 0.75rem;
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        color: #0f172a;
        font-size: 0.9rem;
      }
      .filter-item select:focus {
        outline: none;
        border-color: #60a5fa;
        background: #ffffff;
      }
      .filter-btn {
        padding: 0.75rem 1.5rem;
        background: #60a5fa;
        color: #fff;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 0.9rem;
      }
      .filter-btn:hover {
        background: #3b82f6;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(96, 165, 250, 0.4);
      }
      .filter-btn:active {
        transform: translateY(0);
      }
      .clear-btn {
        padding: 0.75rem 1.5rem;
        background: rgba(239, 68, 68, 0.15);
        color: #ef4444;
        border: 1px solid rgba(239, 68, 68, 0.3);
        border-radius: 8px;
        font-weight: 600;
        text-decoration: none;
        display: inline-block;
        transition: all 0.2s;
        text-align: center;
      }
      .clear-btn:hover {
        background: rgba(239, 68, 68, 0.25);
      }

      .invoice-table .datatable-wrapper .datatable-input {
        background: #f8fafc !important;
        color: #475569 !important;
        border: 1px solid #cbd5e1 !important;
      }

      .invoice-table .datatable-wrapper .datatable-input::placeholder {
        color: #94a3b8 !important;
      }

      .invoice-table .datatable-wrapper .datatable-input:focus {
        background: #ffffff !important;
        color: #334155 !important;
        border-color: #93c5fd !important;
      }

      .invoice-table .datatable-wrapper .datatable-selector {
        background: #f8fafc !important;
        color: #475569 !important;
        border: 1px solid #cbd5e1 !important;
      }

      .invoice-table .datatable-wrapper .datatable-selector:focus {
        background: #ffffff !important;
        color: #334155 !important;
        border-color: #93c5fd !important;
      }

      .invoice-table .datatable-wrapper .datatable-selector option {
        background: #ffffff !important;
        color: #334155 !important;
      }

      .invoice-table .datatable-wrapper table thead,
      .invoice-table .datatable-wrapper table thead tr {
        background: #f8fafc !important;
      }

      .invoice-table .datatable-wrapper table thead th {
        background: #f8fafc !important;
        color: #334155 !important;
        border-bottom: 1px solid #e2e8f0 !important;
      }

      .invoice-table .datatable-wrapper table thead th .datatable-sorter {
        color: inherit !important;
        background: transparent !important;
      }

      .invoice-table .datatable-wrapper table tbody td {
        color: #1f2937 !important;
      }

      .invoice-table .datatable-wrapper table tbody td:nth-child(1) {
        color: #334155 !important;
        font-weight: 600 !important;
      }

      .invoice-table .datatable-wrapper table tbody td:nth-child(1) div:last-child {
        color: #64748b !important;
        font-weight: 500 !important;
      }

      .invoice-table .datatable-wrapper table tbody td:nth-child(2),
      .invoice-table .datatable-wrapper table tbody td:nth-child(3),
      .invoice-table .datatable-wrapper table tbody td:nth-child(4) {
        color: #334155 !important;
        font-weight: 600 !important;
      }

      .invoice-table .datatable-wrapper table tbody td:nth-child(5) div {
        color: #475569 !important;
        font-weight: 500 !important;
      }

      .invoice-table .datatable-wrapper table tbody td:nth-child(6) {
        color: #0369a1 !important;
        font-weight: 700 !important;
      }
    </style>
  </head>
  <body class="reports-page">
    <div class="app-shell">
      <?php 
      $currentPage = 'report_invoice.php';
      include __DIR__ . '/../includes/sidebar.php'; 
      ?>
      <div class="app-main">
        <main class="reports-container">
          <div class="container">
            <?php 
              $pageTitle = 'รายงานใบแจ้งชำระเงิน';
              include __DIR__ . '/../includes/page_header.php'; 
            ?>

            <!-- สถิติภาพรวม -->
            <?php
              $totalInvoices = count($rows);
              $totalAmount = array_sum(array_column($rows, 'exp_total'));
              $pendingCount = count(array_filter($rows, fn($r) => in_array($r['exp_status'] ?? '', ['0', '3', '4'], true)));
              $verifiedCount = count(array_filter($rows, fn($r) => ($r['exp_status'] ?? '') === '1'));
            ?>
            <div class="invoice-stats-grid">
              <div class="stat-card particle-wrapper">
                <div class="particle-container" data-particles="3"></div>
                <div class="lottie-icon blue">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                </div>
                <div class="stat-label">ใบแจ้งทั้งหมด</div>
                <div class="stat-value"><?php echo number_format($totalInvoices); ?></div>
              </div>
              <div class="stat-card particle-wrapper">
                <div class="particle-container" data-particles="3"></div>
                <div class="lottie-icon orange">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="stat-label">รอตรวจสอบ</div>
                <div class="stat-value" style="color:#fbbf24;"><?php echo number_format($pendingCount); ?></div>
              </div>
              <div class="stat-card particle-wrapper">
                <div class="particle-container" data-particles="3"></div>
                <div class="lottie-icon green">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <div class="stat-label">ตรวจสอบแล้ว</div>
                <div class="stat-value" style="color:#22c55e;"><?php echo number_format($verifiedCount); ?></div>
              </div>
              <div class="stat-card">
                <div class="lottie-icon yellow">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </div>
                <div class="stat-label">ยอดรวมทั้งหมด</div>
                <div class="stat-value" style="color:#60a5fa;">฿<?php echo number_format($totalAmount); ?></div>
              </div>
            </div>

            <!-- ตัวกรอง -->
            <div class="filter-section">
              <div class="filter-grid">
                <div class="filter-item">
                  <label for="filterRoom">ห้อง</label>
                  <select id="filterRoom" onchange="applyFilters()">
                    <option value="">ทุกห้อง</option>
                    <?php foreach ($filterRoomOptions as $rn): ?>
                      <option value="<?php echo htmlspecialchars($rn, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($rn, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="filter-item">
                  <label for="filterStatus">สถานะ</label>
                  <select id="filterStatus" onchange="applyFilters()">
                    <option value="">ทุกสถานะ</option>
                    <option value="0">รอตรวจสอบ</option>
                    <option value="1">ตรวจสอบแล้ว</option>
                  </select>
                </div>
                <div class="filter-item">
                  <label for="filterMonth">เดือน</label>
                  <select id="filterMonth" onchange="applyFilters()">
                    <option value="">ทุกเดือน</option>
                    <?php foreach ($availableMonthOptions as $m): ?>
                      <option value="<?php echo $m; ?>"><?php echo $monthNames[$m] ?? $m; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="filter-item">
                  <label for="filterYear">ปี</label>
                  <select id="filterYear" onchange="applyFilters()">
                    <option value="">ทุกปี</option>
                    <?php foreach ($availableYearOptions as $y): ?>
                      <option value="<?php echo $y; ?>"><?php echo $y + 543; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="filter-item" style="display:flex;align-items:flex-end;">
                  <button type="button" class="clear-btn" onclick="clearFilters()" style="width:100%;min-height:2.5rem;display:flex;align-items:center;justify-content:center;gap:0.4rem;">
                    ✕ ล้างตัวกรอง
                  </button>
                </div>
              </div>
            </div>

            <!-- ปุ่มสลับมุมมอง -->
            <div style="display:flex;justify-content:flex-end;margin-bottom:2rem;">
              <div class="view-toggle">
                <button id="toggle-view-btn" class="view-toggle-btn" onclick="toggleView()">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                  การ์ด
                </button>
              </div>
            </div>

            <!-- Card View -->
            <div id="card-view" class="invoice-cards">
<?php foreach($rows as $r): ?>
              <?php 
                $statusKey = (string)($r['exp_status'] ?? '');
                $statusLabel = $statusLabels[$statusKey] ?? 'ยังไม่ระบุสถานะ';
                $statusClass = match($statusKey) {
                  '1' => 'status-verified',
                  '4' => 'status-overdue',
                  default => 'status-pending',
                };
                $cardTotal = (int)($r['exp_total'] ?? 0);
                $filterMonth_ = !empty($r['exp_month']) ? (int)date('n', strtotime($r['exp_month'])) : '';
                $filterYear_  = !empty($r['exp_month']) ? date('Y', strtotime($r['exp_month'])) : '';
              ?>
              <div class="invoice-card" data-filter-item="invoice"
                data-room="<?php echo htmlspecialchars($r['room_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                data-status="<?php echo htmlspecialchars((string)($r['exp_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                data-month="<?php echo $filterMonth_; ?>"
                data-year="<?php echo $filterYear_; ?>">
                <div class="invoice-header">
                  <div>
                    <div style="display:flex;flex-direction:column;gap:0.5rem;">
                      <div style="background:#a7f3d0;color:#065f46;padding:0.5rem 1rem;border-radius:20px;font-weight:600;font-size:0.9rem;text-align:center;white-space:nowrap;">
                        <?php echo getRelativeTime($r['exp_month'] ?? null); ?>
                      </div>
                      <div style="font-size:0.75rem;color:#64748b;text-align:center;">
                        <?php 
                          $expMonth = $r['exp_month'] ?? '';
                          if ($expMonth) {
                            $date = new DateTime($expMonth);
                            echo $date->format('Y-m-d H:i:s');
                          }
                        ?>
                      </div>
                    </div>
                  </div>
                  <span class="invoice-status <?php echo $statusClass; ?>">
                    <?php echo $statusLabel; ?>
                  </span>
                </div>

                <div class="invoice-info">
                  <div style="font-size:0.85rem;color:#94a3b8;margin-bottom:0.5rem;">ผู้เช่า</div>
                  <div style="font-size:1.05rem;font-weight:600;color:#0f172a;margin-bottom:0.75rem;">
                    <?php echo renderField($r['tnt_name'], 'ยังไม่ระบุ'); ?>
                  </div>
                  <div style="display:flex;gap:1.5rem;font-size:0.9rem;">
                    <div>
                      <span style="color:#64748b;">ห้อง:</span>
                      <span style="color:#0f172a;font-weight:600;"><?php echo renderField($r['room_number'], '-'); ?></span>
                    </div>
                    <div>
                      <span style="color:#64748b;">สัญญา:</span>
                      <span style="color:#0f172a;font-weight:600;">#<?php echo renderField((string)($r['ctr_id'] ?? ''), '-'); ?></span>
                    </div>
                  </div>
                </div>

                <div class="invoice-charges">
                  <div class="charge-item">
                    <div class="charge-label">ค่าไฟ</div>
                    <div class="charge-value" style="color:#3b82f6;">฿<?php echo renderNumber($r['exp_elec_chg']); ?></div>
                  </div>
                  <div class="charge-item">
                    <div class="charge-label">ค่าน้ำ</div>
                    <div class="charge-value" style="color:#22c55e;">฿<?php echo renderNumber($r['exp_water']); ?></div>
                  </div>
                  <div class="charge-item">
                    <div class="charge-label">ค่าห้อง</div>
                    <div class="charge-value" style="color:#f59e0b;">฿<?php echo renderNumber($r['room_price']); ?></div>
                  </div>
                  <div class="charge-item">
                    <div class="charge-label">อื่นๆ</div>
                    <div class="charge-value" style="color:#8b5cf6;">฿<?php echo renderNumber($r['exp_other'] ?? 0); ?></div>
                  </div>
                </div>

                <div class="invoice-total">
                  <div class="total-label">ยอดรวมทั้งสิ้น</div>
                  <div class="total-value">฿<?php echo renderNumber($cardTotal); ?></div>
                </div>
              </div>
<?php endforeach; ?>
            </div>

            <!-- Table View -->
            <div id="table-view" class="invoice-table" style="display:none;overflow-x:auto;">
              <table id="table-invoice">
                <thead>
                  <tr>
                    <th>วันที่</th>
                    <th>สัญญา</th>
                    <th>ผู้เช่า</th>
                    <th>ห้อง</th>
                    <th style="text-align:right;">ค่าใช้</th>
                    <th style="text-align:right;">ยอดรวม</th>
                    <th style="text-align:center;">สถานะ</th>
                  </tr>
                </thead>
                <tbody>
<?php foreach($rows as $r): ?>
                  <?php 
                    $statusKey = (string)($r['exp_status'] ?? '');
                    $statusLabel = $statusLabels[$statusKey] ?? 'ยังไม่ระบุสถานะ';
                    $statusClass = match($statusKey) {
                      '1' => 'status-verified',
                      '4' => 'status-overdue',
                      default => 'status-pending',
                    };
                    $trMonth = !empty($r['exp_month']) ? (int)date('n', strtotime($r['exp_month'])) : '';
                    $trYear  = !empty($r['exp_month']) ? date('Y', strtotime($r['exp_month'])) : '';
                  ?>
                  <tr data-filter-item="invoice"
                    data-room="<?php echo htmlspecialchars($r['room_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    data-status="<?php echo htmlspecialchars($statusKey, ENT_QUOTES, 'UTF-8'); ?>"
                    data-month="<?php echo $trMonth; ?>"
                    data-year="<?php echo $trYear; ?>">
                    <td>
                      <div style="display:flex;flex-direction:column;gap:0.3rem;">
                        <div style="background:#a7f3d0;color:#065f46;padding:0.4rem 0.8rem;border-radius:16px;font-weight:600;font-size:0.85rem;text-align:center;white-space:nowrap;display:inline-block;width:fit-content;">
                          <?php echo getRelativeTime($r['exp_month'] ?? null); ?>
                        </div>
                        <div style="font-size:0.75rem;color:#64748b;">
                          <?php 
                            $expMonth = $r['exp_month'] ?? '';
                            if ($expMonth) {
                              $date = new DateTime($expMonth);
                              echo $date->format('Y-m-d');
                            }
                          ?>
                        </div>
                      </div>
                    </td>
                    <td>#<?php echo renderField((string)($r['ctr_id'] ?? ''), '-'); ?></td>
                    <td><?php echo renderField($r['tnt_name'], '-'); ?></td>
                    <td><strong><?php echo renderField($r['room_number'], '-'); ?></strong></td>
                    <td style="text-align:right;font-weight:600;">
                      <div style="font-size:0.85rem;color:#64748b;">ไฟ ฿<?php echo renderNumber($r['exp_elec_chg']); ?></div>
                      <div style="font-size:0.85rem;color:#64748b;">น้ำ ฿<?php echo renderNumber($r['exp_water']); ?></div>
                      <div style="font-size:0.85rem;color:#64748b;">ห้อง ฿<?php echo renderNumber($r['room_price']); ?></div>
                      <?php if ((int)($r['exp_other'] ?? 0) > 0): ?>
                      <div style="font-size:0.85rem;color:#64748b;">อื่นๆ ฿<?php echo renderNumber($r['exp_other']); ?></div>
                      <?php endif; ?>
                    </td>
                    <td style="text-align:right;font-weight:700;font-size:1.1rem;">฿<?php echo renderNumber($r['exp_total']); ?></td>
                    <td style="text-align:center;">
                      <span class="invoice-status <?php echo $statusClass; ?>">
                        <?php echo $statusLabel; ?>
                      </span>
                    </td>
                  </tr>
<?php endforeach; ?>
                </tbody>
              </table>
            </div>

          </div>
        </main>
      </div>
    </div>

    <script src="/dormitory_management/Public/Assets/Javascript/animate-ui.js" defer></script>
    <script src="/dormitory_management/Public/Assets/Javascript/main.js" defer></script>
    <script>
      const safeGet = (key) => {
        try { return localStorage.getItem(key); } catch (e) { return null; }
      };

      // โหลดค่า view mode จาก database
      window.addEventListener('load', function() {
        console.log('Window Load: dbDefaultView =', '<?php echo $defaultViewMode === "list" ? "table" : "card"; ?>');
        // Get default view mode from database (list -> table, grid -> card)
        const dbDefaultView = '<?php echo $defaultViewMode === "list" ? "table" : "card"; ?>';
        console.log('Window Load: Calling switchView with:', dbDefaultView);
        switchView(dbDefaultView);
      });

      let currentView = 'card';

      function switchView(view) {
        const cardView = document.getElementById('card-view');
        const tableView = document.getElementById('table-view');
        const toggleButton = document.getElementById('toggle-view-btn');
        currentView = view;
        
        if (view === 'card') {
          cardView.style.display = 'grid';
          tableView.style.display = 'none';
          if (toggleButton) {
            toggleButton.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg> การ์ด';
          }
          localStorage.setItem('invoiceViewMode', 'card');
        } else {
          cardView.style.display = 'none';
          tableView.style.display = 'block';
          if (toggleButton) {
            toggleButton.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/></svg> ตาราง';
          }
          localStorage.setItem('invoiceViewMode', 'table');
        }
      }

      function toggleView() {
        switchView(currentView === 'card' ? 'table' : 'card');
      }

      function applyFilters() {
        const room   = document.getElementById('filterRoom')?.value   ?? '';
        const status = document.getElementById('filterStatus')?.value ?? '';
        const month  = document.getElementById('filterMonth')?.value  ?? '';
        const year   = document.getElementById('filterYear')?.value   ?? '';
        document.querySelectorAll('[data-filter-item="invoice"]').forEach(el => {
          const matchRoom   = !room   || el.dataset.room   === room;
          const matchStatus = !status || el.dataset.status === status;
          const matchMonth  = !month  || el.dataset.month  === month;
          const matchYear   = !year   || el.dataset.year   === year;
          el.style.display  = (matchRoom && matchStatus && matchMonth && matchYear) ? '' : 'none';
        });
      }

      function clearFilters() {
        const room   = document.getElementById('filterRoom');
        const status = document.getElementById('filterStatus');
        const month  = document.getElementById('filterMonth');
        const year   = document.getElementById('filterYear');
        if (room)   room.value   = '';
        if (status) status.value = '';
        if (month)  month.value  = '';
        if (year)   year.value   = '';
        applyFilters();
      }
    </script>
    
    <!-- DataTable Initialization -->
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4" defer></script>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        const invoiceTable = document.getElementById('table-invoice');
        if (invoiceTable && typeof simpleDatatables !== 'undefined') {
          new simpleDatatables.DataTable(invoiceTable, {
            searchable: true,
            fixedHeight: false,
            perPage: 10,
            perPageSelect: [5, 10, 25, 50, 100],
            labels: {
              placeholder: 'ค้นหาใบแจ้งค่าใช้จ่าย...',
              perPage: 'รายการต่อหน้า',
              noRows: 'ไม่พบข้อมูลใบแจ้งค่าใช้จ่าย',
              info: 'แสดง {start} ถึง {end} จาก {rows} รายการ'
            }
          });
        }
      });
    </script>
  </body>
</html>
