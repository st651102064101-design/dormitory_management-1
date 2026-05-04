<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
require_once __DIR__ . '/../includes/thai_date_helper.php';
$pdo = connectDB();

// ดึงค่า default_view_mode จาก database
$defaultViewMode = 'grid';
try {
    $viewStmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'default_view_mode' LIMIT 1");
    $viewRow = $viewStmt->fetch(PDO::FETCH_ASSOC);
    if ($viewRow && strtolower($viewRow['setting_value']) === 'list') {
        $defaultViewMode = 'list';
    }
} catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }

// รับค่าเดือน/ปี ที่เลือก (รูปแบบ YYYY-MM)
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : '';
$selectedStatus = isset($_GET['status']) ? $_GET['status'] : '';

// ดึงรายการเดือนที่มีในระบบ (format เป็น YYYY-MM)
$availableMonths = [];
$monthNames = [
  '01' => 'มกราคม', '02' => 'กุมภาพันธ์', '03' => 'มีนาคม', '04' => 'เมษายน',
  '05' => 'พฤษภาคม', '06' => 'มิถุนายน', '07' => 'กรกฎาคม', '08' => 'สิงหาคม',
  '09' => 'กันยายน', '10' => 'ตุลาคม', '11' => 'พฤศจิกายน', '12' => 'ธันวาคม'
];
try {
  $monthsStmt = $pdo->query("SELECT DISTINCT DATE_FORMAT(repair_date, '%Y-%m') as month_key FROM repair WHERE repair_date IS NOT NULL ORDER BY month_key DESC");
  $availableMonths = $monthsStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }

// Query repair data with contract, tenant and room
$whereClause = '';
if ($selectedMonth || $selectedStatus !== '') {
  $conditions = [];
  if ($selectedMonth) {
    $conditions[] = "DATE_FORMAT(r.repair_date, '%Y-%m') = " . $pdo->quote($selectedMonth);
  }
  if ($selectedStatus !== '') {
    $conditions[] = "r.repair_status = " . $pdo->quote($selectedStatus);
  }
  $whereClause = 'WHERE ' . implode(' AND ', $conditions);
}

$stmt = $pdo->query("SELECT r.*, c.ctr_id, t.tnt_name, rm.room_number FROM repair r LEFT JOIN contract c ON r.ctr_id = c.ctr_id LEFT JOIN tenant t ON c.tnt_id = t.tnt_id LEFT JOIN room rm ON c.room_id = rm.room_id $whereClause ORDER BY r.repair_date DESC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$statusLabels = [
  '0' => 'รอดำเนินการ',
  '1' => 'กำลังซ่อม',
  '2' => 'เสร็จสิ้น',
];

function renderField(?string $value, string $fallback = '—'): string
{
  return htmlspecialchars(($value === null || $value === '') ? $fallback : $value, ENT_QUOTES, 'UTF-8');
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
} catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }
?>
<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - รายงานการแจ้งซ่อม</title>
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/particle-effects.css" />
    <script src="/dormitory_management/Public/Assets/Javascript/particle-effects.js"></script>
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/lottie-icons.css" />
    <!-- DataTable Modern -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4/dist/style.css" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/datatable-modern.css" />
    <style>
      :root {
        --font-apple: -apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", "Helvetica Neue", sans-serif;
      }

      /* Typography Foundation */
      * {
        font-family: var(--font-apple);
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
      }

      .reports-container { width: 100%; max-width: 100%; padding: 0; }
      .reports-container .container { max-width: 100%; width: 100%; padding: 0 1.5rem 1.5rem; }
      
      .repair-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
      .stat-card { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 16px; padding: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05); transition: transform 0.2s, box-shadow 0.2s; }
      .stat-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1); border-color: #e2e8f0; }
      .stat-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
      .stat-label { font-size: 0.85rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; margin-bottom: 0.2rem; }
      .stat-value { font-size: 2.25rem; font-weight: 800; color: #0f172a; margin: 0.5rem 0; letter-spacing: -0.025em; }
      
      .view-toggle { display: flex; gap: 0.5rem; }
      .view-toggle-btn { padding: 0.75rem 1.5rem; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; color: #64748b; cursor: pointer; transition: all 0.2s; font-weight: 600; }
      .view-toggle-btn.active { background: #60a5fa; border-color: #60a5fa; color: #fff; }
      .view-toggle-btn:hover:not(.active) { background: #f1f5f9; color: #1f2937; }
      
      .repair-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 1.5rem; }
      .repair-card { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 16px; padding: 1.5rem; transition: all 0.2s; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05); }
      .repair-card:hover { transform: translateY(-2px); border-color: #bae6fd; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1); }
      .repair-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #e5e7eb; }
      
      .repair-status { padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-align: center; }
      .status-pending { background: rgba(251, 191, 36, 0.15); color: #d97706; border: 1px solid rgba(251, 191, 36, 0.3); }
      .status-progress { background: rgba(96, 165, 250, 0.15); color: #0284c7; border: 1px solid rgba(96, 165, 250, 0.3); }
      .status-completed { background: rgba(34, 197, 94, 0.15); color: #16a34a; border: 1px solid rgba(34, 197, 94, 0.3); }
      
      .repair-info { margin-bottom: 1rem; background: #f8fafc; padding: 1rem; border-radius: 8px; }
      .repair-desc { background: #f8fafc; padding: 1rem; border-radius: 8px; margin: 1rem 0; border-left: 3px solid #60a5fa; color: #334155; }
      
      .repair-image-preview { width: 100%; height: 200px; object-fit: cover; border-radius: 8px; margin-top: 1rem; cursor: pointer; transition: transform 0.2s; }
      .repair-image-preview:hover { transform: scale(1.02); }
      
      .repair-table { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05); }
      .repair-table table { width: 100%; border-collapse: collapse; }
      .repair-table th, .repair-table td { padding: 1rem 1.25rem; text-align: left; border-bottom: 1px solid #f1f5f9; }
      .repair-table th { background: #f8fafc; color: #475569; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #e2e8f0; }
      .repair-table td { color: #334155; font-weight: 500; }
      .repair-table tbody tr { transition: background-color 0.2s; }
      .repair-table tbody tr:hover { background: #f8fafc; }
      
      #table-view .datatable-wrapper { background: #ffffff !important; color: #0f172a !important; border: none !important; }
      #table-view .datatable-wrapper .datatable-input,
      #table-view .datatable-wrapper .datatable-selector { background: #ffffff !important; border: 1px solid #e2e8f0 !important; color: #334155 !important; border-radius: 8px !important; }
      #table-view .datatable-wrapper .datatable-input::placeholder { color: #94a3b8 !important; }
      #table-view .datatable-wrapper table thead { background: #f8fafc !important; }
      #table-view .datatable-wrapper table thead th { color: #475569 !important; border-bottom: 2px solid #e2e8f0 !important; font-weight: 600 !important; font-size: 0.8rem !important; text-transform: uppercase !important; letter-spacing: 0.05em !important; }
      #table-view .datatable-wrapper table tbody tr { background: #ffffff !important; border-bottom: 1px solid #f1f5f9 !important; transition: background-color 0.2s; }
      #table-view .datatable-wrapper table tbody td { color: #334155 !important; font-weight: 500 !important; border-bottom: 1px solid #f1f5f9 !important; }
      #table-view .datatable-wrapper table tbody tr:hover { background: #f8fafc !important; }
      #table-view .datatable-wrapper .datatable-info { color: #64748b !important; }
      #table-view .datatable-wrapper .datatable-pagination-list-item a,
      #table-view .datatable-wrapper .datatable-pagination-list-item button,
      #table-view .datatable-wrapper .datatable-pagination-list-item .datatable-pagination-list-item-link { background: #ffffff !important; border: 1px solid #e2e8f0 !important; color: #64748b !important; border-radius: 6px !important; }
      #table-view .datatable-wrapper .datatable-pagination-list-item a:hover,
      #table-view .datatable-wrapper .datatable-pagination-list-item button:hover,
      #table-view .datatable-wrapper .datatable-pagination-list-item .datatable-pagination-list-item-link:hover { background: #f1f5f9 !important; color: #0ea5e9 !important; }

      .filter-section { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 16px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05); }
      .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.25rem; }
      .filter-item label { display: block; color: #475569; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; letter-spacing: 0.025em; }
      .filter-item select { width: 100%; padding: 0.75rem 2.5rem 0.75rem 1rem; background: #f8fafc url('data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'%2364748b\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M19 9l-7 7-7-7\'/></svg>') no-repeat right 0.75rem center/16px 16px; border: 1px solid #e2e8f0; border-radius: 8px; color: #334155; font-size: 0.95rem; font-weight: 500; appearance: none; -webkit-appearance: none; cursor: pointer; box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05); transition: border-color 0.2s, box-shadow 0.2s; }
      .filter-item select:focus { outline: none; border-color: #7dd3fc; background-color: #ffffff; box-shadow: 0 0 0 3px rgba(125, 211, 252, 0.3); }
      
      .filter-btn { padding: 0.75rem 1.5rem; background: #60a5fa; color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 0.9rem; }
      .filter-btn:hover { background: #3b82f6; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(96, 165, 250, 0.4); }
      .filter-btn:active { transform: translateY(0); }
      .clear-btn { padding: 0.75rem 1.5rem; background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-block; transition: all 0.2s; text-align: center; }
      .clear-btn:hover { background: rgba(239, 68, 68, 0.25); }
      
      .image-modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.9); justify-content: center; align-items: center; }
      .image-modal img { max-width: 90%; max-height: 90%; border-radius: 12px; }
      .image-modal.show { display: flex; }

      /* ===== MOBILE RESPONSIVE ===== */
      @media (max-width: 767px) {
        .reports-container .container { padding: 0 0.75rem 1rem; }

        /* Stats grid: 2 columns */
        .repair-stats-grid {
          grid-template-columns: repeat(2, 1fr);
          gap: 0.75rem;
          margin-bottom: 1.25rem;
        }
        .stat-card { padding: 1rem; }
        .stat-icon { font-size: 1.8rem; }
        .stat-value { font-size: 1.5rem; }
        .stat-label { font-size: 0.75rem; }

        /* Filter section */
        .filter-section { padding: 1rem; margin-bottom: 1.25rem; }
        .filter-grid { grid-template-columns: 1fr; gap: 0.75rem; }

        /* Status filter buttons */
        .filter-btn { padding: 0.5rem 0.75rem !important; font-size: 0.8rem; }
        .filter-btn svg { width: 14px !important; height: 14px !important; }

        /* Card view */
        .repair-cards {
          grid-template-columns: 1fr;
          gap: 1rem;
        }
        .repair-card { padding: 1rem; }
        .repair-header { flex-direction: column; gap: 0.75rem; }

        /* View toggle */
        .view-toggle-btn { padding: 0.5rem 1rem; font-size: 0.85rem; }

        /* Table view - card layout */
        #table-repairs-report thead {
          display: none;
        }
        #table-repairs-report tbody tr {
          display: flex;
          flex-direction: column;
          padding: 0.75rem;
          margin-bottom: 0.5rem;
          border-radius: 10px;
          border: 1px solid #e5e7eb;
          background: #ffffff;
        }
        #table-repairs-report tbody td {
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding: 0.4rem 0 !important;
          border-bottom: 1px solid #f1f5f9 !important;
          font-size: 0.88rem !important;
          gap: 0.5rem;
          max-width: none !important;
          text-align: left !important;
        }
        #table-repairs-report tbody td:last-child {
          border-bottom: none !important;
          padding-top: 0.6rem !important;
        }
        #table-repairs-report tbody td::before {
          content: attr(data-label);
          font-weight: 600;
          font-size: 0.8rem;
          color: #6b7280;
          flex-shrink: 0;
          min-width: 80px;
        }
        #table-repairs-report .datatable-sorter {
          pointer-events: none;
        }
        .repair-table {
          overflow-x: hidden !important;
        }
        .repair-table .datatable-container {
          overflow-x: hidden !important;
        }
      }

      @media (max-width: 480px) {
        .reports-container .container { padding: 0 0.5rem 0.75rem; }

        /* Stats grid: still 2 columns but tighter */
        .repair-stats-grid { gap: 0.5rem; }
        .stat-card { padding: 0.75rem; }
        .stat-value { font-size: 1.25rem; }
        .stat-label { font-size: 0.7rem; }

        /* Filter buttons: wrap nicely */
        .filter-btn { padding: 0.4rem 0.6rem !important; font-size: 0.75rem; }

        /* Table card layout */
        #table-repairs-report tbody tr {
          padding: 0.6rem;
        }
        #table-repairs-report tbody td {
          font-size: 0.82rem !important;
          padding: 0.35rem 0 !important;
        }
        #table-repairs-report tbody td::before {
          font-size: 0.75rem;
          min-width: 70px;
        }

        /* Card view */
        .repair-card { padding: 0.75rem; }
        .repair-desc { padding: 0.75rem; }
        .repair-image-preview { height: 150px; }
      }
    </style>
  



<style>
/* Fix margins all report pages v2 */
main > div:first-of-type,
.app-main > div:first-of-type, 
.main-content > div:first-of-type, 
.reports-container > div:first-of-type {
    max-width: 1280px !important;
    margin: 0 auto !important;
    padding: 20px !important;
    box-sizing: border-box;
}
@media (max-width: 768px) {
    main > div:first-of-type,
    .app-main > div:first-of-type, 
    .main-content > div:first-of-type, 
    .reports-container > div:first-of-type {
        padding: 10px !important;
    }
}
</style>

</head>
  <body class="reports-page">
    <div class="app-shell">
      <?php 
      $currentPage = 'report_repairs.php';
      include __DIR__ . '/../includes/sidebar.php'; 
      ?>
      <div class="app-main">
        <main class="reports-container">
          <div class="container">
            <?php 
              $pageTitle = 'รายงานการแจ้งซ่อม';
              include __DIR__ . '/../includes/page_header.php'; 
            ?>

            <!-- ตัวกรองเดือน -->
            <div class="filter-section">
              <form method="GET" action="report_repairs.php" id="filterForm">
                <div class="filter-grid">
                  <div class="filter-item">
                    <label for="filterMonth">เดือน</label>
                    <select name="month" id="filterMonth">
                      <option value="">ทุกเดือน</option>
                      <?php 
                        if (!empty($availableMonths)) {
                          foreach ($availableMonths as $month): 
                            $selected = ($selectedMonth === $month) ? 'selected' : '';
                            list($year, $monthNum) = explode('-', $month);
                            $thaiYear = (int)$year + 543;
                            $monthName = $monthNames[$monthNum] ?? $monthNum;
                            $displayText = "$monthName $thaiYear";
                      ?>
                        <option value="<?php echo htmlspecialchars($month); ?>" <?php echo $selected; ?>>
                          <?php echo htmlspecialchars($displayText); ?>
                        </option>
                      <?php endforeach; } ?>
                    </select>
                  </div>
                  <div class="filter-item" style="display:flex;align-items:flex-end;gap:0.5rem;">
                    <button type="button" class="filter-btn" onclick="document.getElementById('filterForm').submit();" style="flex:1;min-height:2.5rem;width:100%;display:inline-flex;align-items:center;justify-content:center;gap:0.4rem;">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                      กรองข้อมูล
                    </button>
                    <?php if ($selectedMonth): ?>
                      <a href="report_repairs.php" class="clear-btn" style="flex:1;min-height:2.5rem;width:100%;display:flex;align-items:center;justify-content:center;">✕ ล้างตัวกรอง</a>
                    <?php endif; ?>
                  </div>
                </div>
              </form>
            </div>

            <!-- สถิติภาพรวม -->
            <?php
              $totalRepairs = count($rows);
              $pendingCount = count(array_filter($rows, fn($r) => ($r['repair_status'] ?? '') === '0'));
              $progressCount = count(array_filter($rows, fn($r) => ($r['repair_status'] ?? '') === '1'));
              $completedCount = count(array_filter($rows, fn($r) => ($r['repair_status'] ?? '') === '2'));
            ?>
            <div class="repair-stats-grid">
              <div class="stat-card particle-wrapper"><div class="particle-container" data-particles="3"></div><div class="lottie-icon purple"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></div><div class="stat-label">แจ้งซ่อมทั้งหมด</div><div class="stat-value"><?php echo number_format($totalRepairs); ?></div></div>
              <div class="stat-card particle-wrapper"><div class="particle-container" data-particles="3"></div><div class="lottie-icon yellow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div><div class="stat-label">รอดำเนินการ</div><div class="stat-value" style="color:#d97706;"><?php echo number_format($pendingCount); ?></div></div>
              <div class="stat-card particle-wrapper"><div class="particle-container" data-particles="3"></div><div class="lottie-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9"/></svg></div><div class="stat-label">กำลังซ่อม</div><div class="stat-value" style="color:#0284c7;"><?php echo number_format($progressCount); ?></div></div>
              <div class="stat-card particle-wrapper"><div class="particle-container" data-particles="3"></div><div class="lottie-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div><div class="stat-label">เสร็จสิ้น</div><div class="stat-value" style="color:#16a34a;"><?php echo number_format($completedCount); ?></div></div>
            </div>

            <!-- ปุ่มแยกตามสถานะ และ ปุ่มสลับมุมมอง -->
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;flex-wrap:wrap;gap:1rem;">
              <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                <a href="report_repairs.php<?php echo $selectedMonth ? '?month=' . htmlspecialchars($selectedMonth) : ''; ?>" class="filter-btn" style="padding:0.75rem 1.5rem;text-decoration:none;display:inline-flex;align-items:center;gap:0.5rem;background:<?php echo (!isset($_GET['status'])) ? '#0ea5e9' : '#ffffff'; ?>;color:<?php echo (!isset($_GET['status'])) ? '#ffffff' : '#64748b'; ?>;border:<?php echo (!isset($_GET['status'])) ? '1px solid #0ea5e9' : '1px solid #cbd5e1'; ?>;border-radius:8px;font-weight:600;transition:all 0.2s;"><svg viewBox="0 0 24 24" fill="none" stroke="<?php echo (!isset($_GET['status'])) ? '#ffffff' : 'currentColor'; ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg> ทั้งหมด</a>
                <a href="report_repairs.php?status=0<?php echo $selectedMonth ? '&month=' . htmlspecialchars($selectedMonth) : ''; ?>" class="filter-btn" style="padding:0.75rem 1.5rem;text-decoration:none;display:inline-flex;align-items:center;gap:0.5rem;background:<?php echo $selectedStatus === '0' ? '#0ea5e9' : '#ffffff'; ?>;color:<?php echo $selectedStatus === '0' ? '#ffffff' : '#64748b'; ?>;border:<?php echo $selectedStatus === '0' ? '1px solid #0ea5e9' : '1px solid #cbd5e1'; ?>;border-radius:8px;font-weight:600;transition:all 0.2s;"><svg viewBox="0 0 24 24" fill="none" stroke="<?php echo ($selectedStatus === '0') ? '#ffffff' : 'currentColor'; ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> รอดำเนินการ</a>
                <a href="report_repairs.php?status=1<?php echo $selectedMonth ? '&month=' . htmlspecialchars($selectedMonth) : ''; ?>" class="filter-btn" style="padding:0.75rem 1.5rem;text-decoration:none;display:inline-flex;align-items:center;gap:0.5rem;background:<?php echo $selectedStatus === '1' ? '#0ea5e9' : '#ffffff'; ?>;color:<?php echo $selectedStatus === '1' ? '#ffffff' : '#64748b'; ?>;border:<?php echo $selectedStatus === '1' ? '1px solid #0ea5e9' : '1px solid #cbd5e1'; ?>;border-radius:8px;font-weight:600;transition:all 0.2s;"><svg viewBox="0 0 24 24" fill="none" stroke="<?php echo ($selectedStatus === '1') ? '#ffffff' : 'currentColor'; ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg> กำลังซ่อม</a>
                <a href="report_repairs.php?status=2<?php echo $selectedMonth ? '&month=' . htmlspecialchars($selectedMonth) : ''; ?>" class="filter-btn" style="padding:0.75rem 1.5rem;text-decoration:none;display:inline-flex;align-items:center;gap:0.5rem;background:<?php echo $selectedStatus === '2' ? '#0ea5e9' : '#ffffff'; ?>;color:<?php echo $selectedStatus === '2' ? '#ffffff' : '#64748b'; ?>;border:<?php echo $selectedStatus === '2' ? '1px solid #0ea5e9' : '1px solid #cbd5e1'; ?>;border-radius:8px;font-weight:600;transition:all 0.2s;"><svg viewBox="0 0 24 24" fill="none" stroke="<?php echo ($selectedStatus === '2') ? '#ffffff' : 'currentColor'; ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> เสร็จสิ้น</a>
              </div>
              <div class="view-toggle">
                <button id="toggle-view-btn" class="view-toggle-btn active" onclick="toggleView()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;vertical-align:middle;margin-right:4px;"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>ตาราง</button>
              </div>
            </div>

            <!-- Card View -->
            <div id="card-view" class="repair-cards">
<?php foreach($rows as $r): ?>
              <?php 
                $statusKey = (string)($r['repair_status'] ?? '');
                $statusLabel = $statusLabels[$statusKey] ?? 'ยังไม่ระบุสถานะ';
                $statusClass = $statusKey === '2' ? 'status-completed' : ($statusKey === '1' ? 'status-progress' : 'status-pending');
              ?>
              <div class="repair-card">
                <div class="repair-header">
                  <div>
                    <div style="display:flex;flex-direction:column;gap:0.5rem;">
                      <div style="background:#d1fae5;color:#065f46;padding:0.5rem 1rem;border-radius:20px;font-weight:600;font-size:0.9rem;text-align:center;white-space:nowrap;"><?php echo getRelativeTime($r['repair_date'] ?? null); ?></div>
                      <div style="font-size:0.75rem;color:#64748b;text-align:center;"><?php if ($repairDate = $r['repair_date'] ?? '') { $date = new DateTime($repairDate); echo thaiDate($date->format('Y-m-d H:i:s'), 'short_time'); } ?></div>
                    </div>
                  </div>
                  <span class="repair-status <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
                </div>
                <div class="repair-info">
                  <div style="font-size:0.85rem;color:#64748b;margin-bottom:0.5rem;">ผู้แจ้ง</div>
                  <div style="font-size:1.05rem;font-weight:600;color:#0f172a;margin-bottom:0.75rem;"><?php echo renderField($r['tnt_name'], 'ยังไม่ระบุ'); ?></div>
                  <div style="display:flex;gap:1.5rem;font-size:0.9rem;">
                    <div><span style="color:#64748b;">ห้อง:</span> <span style="color:#0f172a;font-weight:600;"><?php echo renderField($r['room_number'], '-'); ?></span></div>
                    <div><span style="color:#64748b;">สัญญา:</span> <span style="color:#0f172a;font-weight:600;">#<?php echo renderField((string)($r['ctr_id'] ?? ''), '-'); ?></span></div>
                  </div>
                </div>
                <div class="repair-desc">
                  <div style="font-size:0.85rem;color:#64748b;margin-bottom:0.5rem;display:flex;align-items:center;gap:0.5rem;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>รายละเอียด</div>
                  <div style="color:#0f172a;font-weight:500;"><?php echo renderField($r['repair_desc'], 'ไม่มีรายละเอียด'); ?></div>
                </div>
                <?php if (!empty($r['repair_image'])): ?>
                  <img src="/dormitory_management/Public/Assets/Images/Repairs/<?php echo htmlspecialchars($r['repair_image']); ?>" alt="Repair" class="repair-image-preview" onclick="showImage('<?php echo htmlspecialchars($r['repair_image']); ?>')">
                <?php endif; ?>
              </div>
<?php endforeach; ?>
            </div>

            <!-- Table View -->
            <div id="table-view" class="repair-table" style="display:none;overflow-x:auto;">
              <table id="table-repairs-report">
                <thead><tr><th>วันที่</th><th>สัญญา</th><th>ผู้แจ้ง</th><th>ห้อง</th><th>รายละเอียด</th><th style="text-align:center;">รูปภาพ</th><th style="text-align:center;">สถานะ</th></tr></thead>
                <tbody>
<?php foreach($rows as $r): ?>
                  <?php $statusKey = (string)($r['repair_status'] ?? ''); $statusLabel = $statusLabels[$statusKey] ?? 'ยังไม่ระบุสถานะ'; $statusClass = $statusKey === '2' ? 'status-completed' : ($statusKey === '1' ? 'status-progress' : 'status-pending'); ?>
                  <tr>
                    <td data-label="วันที่"><div style="display:flex;flex-direction:column;gap:0.3rem;"><div style="background:#d1fae5;color:#065f46;padding:0.4rem 0.8rem;border-radius:16px;font-weight:600;font-size:0.85rem;text-align:center;white-space:nowrap;display:inline-block;width:fit-content;"><?php echo getRelativeTime($r['repair_date'] ?? null); ?></div><div style="font-size:0.75rem;color:#64748b;"><?php if ($repairDate = $r['repair_date'] ?? '') { $date = new DateTime($repairDate); echo thaiDate($date->format('Y-m-d')); } ?></div></div></td>
                    <td data-label="สัญญา">#<?php echo renderField((string)($r['ctr_id'] ?? ''), '-'); ?></td>
                    <td data-label="ผู้แจ้ง"><?php echo renderField($r['tnt_name'], '-'); ?></td>
                    <td data-label="ห้อง"><strong><?php echo renderField($r['room_number'], '-'); ?></strong></td>
                    <td data-label="รายละเอียด" style="max-width:300px;"><?php echo renderField($r['repair_desc'], '-'); ?></td>
                    <td data-label="รูปภาพ" style="text-align:center;"><?php if (!empty($r['repair_image'])): ?><img src="/dormitory_management/Public/Assets/Images/Repairs/<?php echo htmlspecialchars($r['repair_image']); ?>" alt="Repair" style="width:60px;height:60px;object-fit:cover;border-radius:8px;cursor:pointer;" onclick="showImage('<?php echo htmlspecialchars($r['repair_image']); ?>')"><?php else: ?><span style="color:#64748b;">ไม่มีรูป</span><?php endif; ?></td>
                    <td data-label="สถานะ" style="text-align:center;"><span class="repair-status <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                  </tr>
<?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </main>
      </div>
    </div>

    <div id="imageModal" class="image-modal" onclick="closeImage()"><img id="modalImage" src="" alt="Repair Image"></div>

    <script src="/dormitory_management/Public/Assets/Javascript/animate-ui.js" defer></script>
    <script src="/dormitory_management/Public/Assets/Javascript/main.js" defer></script>
    <script>
      const safeGet = (key) => { try { return localStorage.getItem(key); } catch (e) { return null; } };

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
        const toggleBtn = document.getElementById('toggle-view-btn');

        if (view === 'card') {
          cardView.style.display = 'grid';
          tableView.style.display = 'none';
          if (toggleBtn) {
            toggleBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;vertical-align:middle;margin-right:4px;"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>ตาราง';
          }
          currentView = 'card';
          localStorage.setItem('repairViewMode', 'card');
        } else {
          cardView.style.display = 'none';
          tableView.style.display = 'block';
          if (toggleBtn) {
            toggleBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;vertical-align:middle;margin-right:4px;"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>การ์ด';
          }
          currentView = 'table';
          localStorage.setItem('repairViewMode', 'table');
        }
      }

      function toggleView() {
        switchView(currentView === 'card' ? 'table' : 'card');
      }
      function showImage(imageName) { const modal = document.getElementById('imageModal'); const modalImg = document.getElementById('modalImage'); modalImg.src = '/dormitory_management/Public/Assets/Images/Repairs/' + imageName; modal.classList.add('show'); }
      function closeImage() { document.getElementById('imageModal').classList.remove('show'); }
      document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeImage(); });
    </script>
    
    <!-- DataTable Initialization -->
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4" defer></script>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        const repairsTable = document.getElementById('table-repairs-report');
        if (repairsTable && typeof simpleDatatables !== 'undefined') {
          new simpleDatatables.DataTable(repairsTable, {
            searchable: true,
            fixedHeight: false,
            perPage: 10,
            perPageSelect: [5, 10, 25, 50, 100],
            labels: {
              placeholder: 'ค้นหาการซ่อม...',
              perPage: 'รายการต่อหน้า',
              noRows: 'ไม่พบข้อมูลการซ่อม',
              info: 'แสดง {start} ถึง {end} จาก {rows} รายการ'
            }
          });
        }
      });
    </script>
  </body>
</html>
