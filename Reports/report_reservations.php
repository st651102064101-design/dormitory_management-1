<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

// ดึง theme color จากการตั้งค่าระบบ
$themeColor = '#0f172a'; // ค่า default (dark mode)
$defaultViewMode = 'grid';
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('theme_color', 'default_view_mode')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'theme_color') $themeColor = htmlspecialchars($row['setting_value'], ENT_QUOTES, 'UTF-8');
        if ($row['setting_key'] === 'default_view_mode') $defaultViewMode = strtolower($row['setting_value']) === 'list' ? 'list' : 'grid';
    }
} catch (PDOException $e) {}

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
  $monthsStmt = $pdo->query("SELECT DISTINCT DATE_FORMAT(bkg_date, '%Y-%m') as month_key FROM booking WHERE bkg_date IS NOT NULL ORDER BY month_key DESC");
  $availableMonths = $monthsStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}

// Query booking data - get all records first
// เชื่อมความสัมพันธ์: booking -> room -> contract -> tenant
try {
  $query = "SELECT b.*, rm.room_number, 
            COALESCE(t.tnt_name, 'ยังไม่มีผู้เช่า') as tnt_name,
            COALESCE(t.tnt_status, '') as tnt_status,
            t.tnt_id
            FROM booking b 
            LEFT JOIN room rm ON b.room_id = rm.room_id 
            LEFT JOIN contract c ON rm.room_id = c.room_id AND c.ctr_status IN ('0', '1')
            LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
            ORDER BY b.bkg_date DESC";
  $stmt = $pdo->query($query);
  $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  error_log('Booking query error: ' . $e->getMessage());
  $allRows = [];
}

// Filter results based on selections
$rows = [];
foreach ($allRows as $row) {
  $includeRow = true;
  
  if (!empty($selectedMonth)) {
    $bookingMonth = date('Y-m', strtotime($row['bkg_date']));
    if ($bookingMonth !== $selectedMonth) {
      $includeRow = false;
    }
  }
  
  if ($includeRow && !empty($selectedStatus)) {
    if ((string)$row['bkg_status'] !== $selectedStatus) {
      $includeRow = false;
    }
  }
  
  if ($includeRow) {
    $rows[] = $row;
  }
}

$statusLabels = [
  '0' => 'ยกเลิก',
  '1' => 'จองแล้ว',
  '2' => 'เข้าพักแล้ว',
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
} catch (PDOException $e) {}

// คำนวณสถิติ
$totalBookings = count($rows);
try {
  // ยกเลิก
  $stmt = $pdo->query("SELECT COUNT(*) as total FROM booking WHERE bkg_status = '0'");
  $bookingCancelled = $stmt->fetch()['total'] ?? 0;
  
  // จองแล้ว
  $stmt = $pdo->query("SELECT COUNT(*) as total FROM booking WHERE bkg_status = '1'");
  $bookingConfirmed = $stmt->fetch()['total'] ?? 0;
  
  // เข้าพักแล้ว
  $stmt = $pdo->query("SELECT COUNT(*) as total FROM booking WHERE bkg_status = '2'");
  $bookingCompleted = $stmt->fetch()['total'] ?? 0;
} catch (PDOException $e) {
  $bookingCancelled = $bookingConfirmed = $bookingCompleted = 0;
}
?>
<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - รายงานการจอง</title>
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/lottie-icons.css" />
    <!-- DataTable Modern -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4/dist/style.css" />
    <link rel="stylesheet" href="..//Assets/Css/datatable-modern.css" />
    <style>
      :root {
        --theme-bg-color: <?php echo $themeColor; ?>;
      }
      
      .reports-container { width: 100%; max-width: 100%; padding: 0; }
      .reports-container .container { max-width: 100%; width: 100%; padding: 1.5rem; }
      .reservation-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
      .stat-card { background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); transition: transform 0.2s, box-shadow 0.2s; }
      .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3); }
      .stat-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
      .stat-label { font-size: 0.85rem; color: #cbd5e1; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
      .stat-value { font-size: 2rem; font-weight: 700; color: #f8fafc; margin: 0.5rem 0; }
      
      /* Light theme overrides for stat cards */
      @media (prefers-color-scheme: light) {
        .stat-card {
          background: rgba(243, 244, 246, 0.8) !important;
          border: 1px solid rgba(0, 0, 0, 0.1) !important;
          box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08) !important;
        }
        .stat-card:hover {
          box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12) !important;
        }
        .stat-label {
          color: rgba(0, 0, 0, 0.7) !important;
        }
        .stat-value {
          color: #1f2937 !important;
        }
      }
      
      /* JavaScript-detected light theme class */
      html.light-theme .stat-card {
        background: rgba(243, 244, 246, 0.8) !important;
        border: 1px solid rgba(0, 0, 0, 0.1) !important;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08) !important;
      }
      
      html.light-theme .stat-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12) !important;
      }
      
      html.light-theme .stat-label {
        color: rgba(0, 0, 0, 0.7) !important;
      }
      
      html.light-theme .stat-value {
        color: #1f2937 !important;
      }
      
      /* Light theme for reservation cards */
      html.light-theme .reservation-card {
        background: #ffffff !important;
        border: 1px solid rgba(0, 0, 0, 0.1) !important;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08) !important;
      }
      html.light-theme .reservation-card:hover {
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12) !important;
      }
      html.light-theme .reservation-date {
        color: #6b7280 !important;
      }
      html.light-theme .reservation-info {
        color: #374151 !important;
      }
      html.light-theme .reservation-info strong {
        color: #111827 !important;
      }
      
      /* Light theme for status buttons */
      html.light-theme .status-btn {
        background: #f3f4f6 !important;
        border: 1px solid #e5e7eb !important;
        color: #374151 !important;
      }
      html.light-theme .status-btn:hover:not(.active) {
        background: #e5e7eb !important;
        color: #1f2937 !important;
      }
      html.light-theme .status-btn.active {
        background: #3b82f6 !important;
        border-color: #3b82f6 !important;
        color: #ffffff !important;
      }
      
      /* Light theme for view toggle buttons */
      html.light-theme .view-toggle-btn {
        background: #f3f4f6 !important;
        border: 1px solid #e5e7eb !important;
        color: #374151 !important;
      }
      html.light-theme .view-toggle-btn:hover:not(.active) {
        background: #e5e7eb !important;
        color: #1f2937 !important;
      }
      html.light-theme .view-toggle-btn.active {
        background: #3b82f6 !important;
        border-color: #3b82f6 !important;
        color: #ffffff !important;
      }
      
      /* Light theme for table */
      html.light-theme .reservation-table {
        background: #ffffff !important;
        border: 1px solid rgba(0, 0, 0, 0.1) !important;
      }
      html.light-theme .reservation-table th {
        background: #f8fafc !important;
        color: #374151 !important;
        border-bottom: 2px solid rgba(0, 0, 0, 0.1) !important;
      }
      html.light-theme .reservation-table td {
        color: #1f2937 !important;
        border-bottom: 1px solid rgba(0, 0, 0, 0.06) !important;
      }
      html.light-theme .reservation-table tr:hover {
        background: rgba(59, 130, 246, 0.05) !important;
      }
      
      /* Light theme for page title */
      html.light-theme h1 {
        color: #1f2937 !important;
      }
      
      /* Light theme for empty state */
      html.light-theme .empty-state {
        color: #6b7280 !important;
      }
      
      .view-toggle { display: flex; gap: 0.5rem; margin-bottom: 2rem; }
      .view-toggle-btn { padding: 0.75rem 1.5rem; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #94a3b8; cursor: pointer; transition: all 0.2s; font-weight: 600; }
      .view-toggle-btn.active { background: #60a5fa; border-color: #60a5fa; color: #fff; }
      .view-toggle-btn:hover:not(.active) { background: rgba(255, 255, 255, 0.08); color: #e2e8f0; }
      .status-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 2rem; }
      .status-btn { padding: 0.75rem 1.5rem; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #94a3b8; cursor: pointer; transition: all 0.2s; font-weight: 600; text-decoration: none; display: inline-block; }
      .status-btn.active { background: #60a5fa; border-color: #60a5fa; color: #fff; }
      .status-btn:hover:not(.active) { background: rgba(255, 255, 255, 0.08); color: #e2e8f0; }
      .reservation-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem; }
      .reservation-card { background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 1.5rem; transition: all 0.2s; }
      .reservation-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3); }
      .reservation-time-badge { display: inline-block; background: #a7f3d0; color: #0f172a; padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; margin-bottom: 10px; }
      .reservation-date { color: #94a3b8; font-size: 0.8rem; margin-bottom: 15px; }
      .reservation-info { color: #cbd5e1; font-size: 0.95rem; line-height: 1.6; margin: 15px 0; }
      .reservation-status { padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; display: inline-block; margin-top: 10px; }
      .status-pending { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }
      .status-confirmed { background: rgba(96, 165, 250, 0.15); color: #60a5fa; }
      .status-completed { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
      .reservation-table { background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; overflow: hidden; }
      .reservation-table table { width: 100%; border-collapse: collapse; }
      .reservation-table th, .reservation-table td { padding: 1rem; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
      .reservation-table th { background: rgba(255, 255, 255, 0.05); color: #cbd5e1; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; }
      .reservation-table td { color: #e2e8f0; font-size: 0.95rem; }
      .reservation-table tr:hover { background: rgba(255, 255, 255, 0.02); }
      .empty-state { text-align: center; padding: 3rem 1rem; color: #94a3b8; }
      .empty-icon { font-size: 4rem; margin-bottom: 1rem; opacity: 0.5; }
      .empty-text { font-size: 1.1rem; font-weight: 600; margin-bottom: 0.5rem; }
      .page-title-icon { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); margin-right: 12px; vertical-align: middle; }
      .page-title-icon svg { width: 22px; height: 22px; stroke: #fff; }
    </style>
  </head>
  <body class="reports-page">
    <div class="app-shell">
      <?php include __DIR__ . '/../includes/sidebar.php'; ?>
      <main class="app-main">
        <div class="reports-container">
          <?php include __DIR__ . '/../includes/page_header.php'; ?>
          <div class="container">
            <h1 style="font-size:2rem;font-weight:700;margin-bottom:2rem;color:#f8fafc;display:flex;align-items:center;"><span class="page-title-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></span> รายงานการจอง</h1>
            
            <!-- Stat Cards -->
            <div class="reservation-stats-grid">
              <div class="stat-card">
                <div class="lottie-icon indigo">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
                </div>
                <div class="stat-label">จองแล้ว</div>
                <div class="stat-value"><?php echo $bookingConfirmed; ?></div>
              </div>
              <div class="stat-card">
                <div class="lottie-icon green">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <div class="stat-label">เข้าพักแล้ว</div>
                <div class="stat-value"><?php echo $bookingCompleted; ?></div>
              </div>
              <div class="stat-card">
                <div class="lottie-icon red">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                </div>
                <div class="stat-label">ยกเลิก</div>
                <div class="stat-value"><?php echo $bookingCancelled; ?></div>
              </div>
            </div>

            <!-- ปุ่มสถานะ -->
            <div class="status-buttons">
              <a href="report_reservations.php" class="status-btn <?php echo !isset($_GET['status']) ? 'active' : ''; ?>">ทั้งหมด</a>
              <a href="report_reservations.php?status=1" class="status-btn <?php echo isset($_GET['status']) && $_GET['status'] === '1' ? 'active' : ''; ?>">จองแล้ว</a>
              <a href="report_reservations.php?status=2" class="status-btn <?php echo isset($_GET['status']) && $_GET['status'] === '2' ? 'active' : ''; ?>">เข้าพักแล้ว</a>
              <a href="report_reservations.php?status=0" class="status-btn <?php echo isset($_GET['status']) && $_GET['status'] === '0' ? 'active' : ''; ?>">ยกเลิก</a>
            </div>

            <!-- ปุ่มเปลี่ยนมุมมอง -->
            <div class="view-toggle">
              <button type="button" class="view-toggle-btn active" onclick="switchView('card')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;vertical-align:middle;margin-right:4px;"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>มุมมองการ์ด</button>
              <button type="button" class="view-toggle-btn" onclick="switchView('table')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;vertical-align:middle;margin-right:4px;"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>มุมมองตาราง</button>
            </div>

            <!-- Card View -->
            <div id="card-view" class="reservation-cards">
<?php if (count($rows) > 0): ?>
<?php foreach ($rows as $r): 
  $statusClass = match($r['bkg_status']) {
    '0' => 'status-pending',
    '1' => 'status-confirmed',
    '2' => 'status-completed',
    default => 'status-pending'
  };
  $statusLabel = $statusLabels[$r['bkg_status']] ?? 'ไม่ทราบ';
?>
              <div class="reservation-card">
                <div class="reservation-time-badge"><?php echo getRelativeTime($r['bkg_date']); ?></div>
                <div class="reservation-date"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;vertical-align:middle;margin-right:4px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>จอง: <?php echo getRelativeTime($r['bkg_date']); ?></div>
                <div class="reservation-info">
                  <div><strong>ห้องพัก:</strong> <?php echo renderField($r['room_number'], 'ไม่ระบุ'); ?></div>
                  <div><strong>เข้าพัก:</strong> <?php echo getRelativeTime($r['bkg_checkin_date']); ?></div>
                  <div><strong>รหัส:</strong> #<?php echo renderField((string)$r['bkg_id'], 'ไม่ระบุ'); ?></div>
                </div>
                <span class="reservation-status <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
              </div>
<?php endforeach; ?>
<?php else: ?>
              <div class="empty-state">
                <div class="empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:48px;height:48px;opacity:0.5;"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg></div>
                <div class="empty-text">ไม่มีข้อมูลการจอง</div>
              </div>
<?php endif; ?>
            </div>

            <!-- Table View -->
            <div id="table-view" class="reservation-table" style="display:none;">
<?php if (count($rows) > 0): ?>
              <table id="table-reservations">
                <thead>
                  <tr>
                    <th>รหัส</th>
                    <th>ผู้เช่า</th>
                    <th>ห้องพัก</th>
                    <th>วันที่จอง</th>
                    <th>วันเข้าพัก</th>
                    <th>สถานะการเข้าพัก</th>
                    <th>สถานะการจอง</th>
                  </tr>
                </thead>
                <tbody>
<?php foreach ($rows as $r): 
  $statusClass = match($r['bkg_status']) {
    '0' => 'status-pending',
    '1' => 'status-confirmed',
    '2' => 'status-completed',
    default => 'status-pending'
  };
  $statusLabel = $statusLabels[$r['bkg_status']] ?? 'ไม่ทราบ';
  
  // สถานะผู้เช่า
  $tenantStatusLabels = [
    '0' => 'ย้ายออก',
    '1' => 'พักอยู่',
    '2' => 'รอการเข้าพัก',
    '3' => 'จองห้อง',
    '4' => 'ยกเลิกจองห้อง'
  ];
  $tenantStatus = $tenantStatusLabels[$r['tnt_status'] ?? ''] ?? 'ไม่ทราบ';
  $tenantStatusClass = ($r['tnt_status'] === '2') ? 'status-pending' : 'status-confirmed';
?>
                  <tr>
                    <td>#<?php echo renderField((string)$r['bkg_id'], '—'); ?></td>
                    <td><?php echo renderField($r['tnt_name'] ?? '', '—'); ?></td>
                    <td><strong><?php echo renderField($r['room_number'], '—'); ?></strong></td>
                    <td><?php echo renderField($r['bkg_date'], '—'); ?></td>
                    <td><?php echo renderField($r['bkg_checkin_date'], '—'); ?></td>
                    <td><span class="reservation-status <?php echo $tenantStatusClass; ?>"><?php echo $tenantStatus; ?></span></td>
                    <td><span class="reservation-status <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                  </tr>
<?php endforeach; ?>
                </tbody>
              </table>
<?php else: ?>
              <div class="empty-state">
                <div class="empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:48px;height:48px;opacity:0.5;"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg></div>
                <div class="empty-text">ไม่มีข้อมูลการจอง</div>
              </div>
<?php endif; ?>
            </div>
          </div>
        </div>
      </main>
    </div>

    <script src="/dormitory_management/Public/Assets/Javascript/animate-ui.js" defer></script>
    <script src="/dormitory_management/Public/Assets/Javascript/main.js" defer></script>
    <script>
      // Ultra-early sidebar toggle fallback
      window.__directSidebarToggle = function(event) {
        if (event) { event.preventDefault(); event.stopPropagation(); }
        const sidebar = document.querySelector('.app-sidebar');
        if (!sidebar) return false;
        const isMobile = window.innerWidth < 768;
        if (isMobile) {
          sidebar.classList.toggle('mobile-open');
        } else {
          sidebar.classList.toggle('collapsed');
          try { localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed')); } catch(e) {}
        }
        return false;
      };

      const safeGet = (key) => {
        try { return localStorage.getItem(key); } catch (e) { return null; }
      };

      function switchView(view) {
        const cardView = document.getElementById('card-view');
        const tableView = document.getElementById('table-view');
        const buttons = document.querySelectorAll('.view-toggle-btn');
        
        if (!cardView || !tableView) return;
        
        // Remove active class from all buttons
        buttons.forEach(btn => btn.classList.remove('active'));
        
        if (view === 'card') {
          cardView.style.display = 'grid';
          tableView.style.display = 'none';
          buttons[0].classList.add('active');
          localStorage.setItem('reservationViewMode', 'card');
        } else {
          cardView.style.display = 'none';
          tableView.style.display = 'block';
          buttons[1].classList.add('active');
          localStorage.setItem('reservationViewMode', 'table');
        }
      }

      window.addEventListener('load', function() {
        console.log('Window Load: dbDefaultView =', '<?php echo $defaultViewMode === "list" ? "table" : "card"; ?>');
        // Get default view mode from database (list -> table, grid -> card)
        const dbDefaultView = '<?php echo $defaultViewMode === "list" ? "table" : "card"; ?>';
        console.log('Window Load: Calling switchView with:', dbDefaultView);
        switchView(dbDefaultView);
        
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
      });
    </script>
    
    <!-- DataTable Initialization -->
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4" defer></script>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        const reservationsTable = document.getElementById('table-reservations');
        if (reservationsTable && typeof simpleDatatables !== 'undefined') {
          new simpleDatatables.DataTable(reservationsTable, {
            searchable: true,
            fixedHeight: false,
            perPage: 10,
            perPageSelect: [5, 10, 25, 50, 100],
            labels: {
              placeholder: 'ค้นหาการจอง...',
              perPage: 'รายการต่อหน้า',
              noRows: 'ไม่พบข้อมูลการจอง',
              info: 'แสดง {start} ถึง {end} จาก {rows} รายการ'
            }
          });
        }
      });
    </script>
  </body>
</html>
