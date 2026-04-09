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
} catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }

// รับค่าเดือน/ปี ที่เลือก (รูปแบบ YYYY-MM)
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : '';

// ดึงรายการเดือนที่มีในระบบ (format เป็น YYYY-MM)
$availableMonths = [];
$monthNames = [
  '01' => 'มกราคม', '02' => 'กุมภาพันธ์', '03' => 'มีนาคม', '04' => 'เมษายน',
  '05' => 'พฤษภาคม', '06' => 'มิถุนายน', '07' => 'กรกฎาคม', '08' => 'สิงหาคม',
  '09' => 'กันยายน', '10' => 'ตุลาคม', '11' => 'พฤศจิกายน', '12' => 'ธันวาคม'
];
try {
  $monthsStmt = $pdo->query("SELECT DISTINCT DATE_FORMAT(news_date, '%Y-%m') as month_key FROM news WHERE news_date IS NOT NULL ORDER BY month_key DESC");
  $availableMonths = $monthsStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }

// Query news data
$whereClause = '';
if ($selectedMonth) {
  $whereClause = "WHERE DATE_FORMAT(news_date, '%Y-%m') = " . $pdo->quote($selectedMonth);
}

try {
  $stmt = $pdo->query("SELECT * FROM news $whereClause ORDER BY news_date DESC");
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $rows = [];
}

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

// คำนวณสถิติ
$totalNews = count($rows);
try {
  $stmt = $pdo->query("SELECT COUNT(*) as total FROM news");
  $allNewsCount = $stmt->fetch()['total'] ?? 0;
} catch (PDOException $e) {
  $allNewsCount = 0;
}
?>
<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - รายงานข่าวประชาสัมพันธ์</title>
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
      body.reports-page,
      body.reports-page .app-main,
      body.reports-page .reports-container,
      body.reports-page .reports-container .container {
        background: #ffffff !important;
        color: #0f172a !important;
      }
      .reports-container { width: 100%; max-width: 100%; padding: 0; }
      .reports-container .container { max-width: 100%; width: 100%; padding: 0 1.5rem 1.5rem; }
      .news-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
      .stat-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06); transition: transform 0.2s, box-shadow 0.2s; }
      .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12); }
      .stat-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
      .stat-label { font-size: 0.85rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
      .stat-value { font-size: 2rem; font-weight: 700; color: #0f172a; margin: 0.5rem 0; }
      .view-toggle { display: flex; gap: 0.5rem; margin-bottom: 2rem; }
      .view-toggle-btn { padding: 0.75rem 1.5rem; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; color: #475569; cursor: pointer; transition: all 0.2s; font-weight: 600; }
      .view-toggle-btn.active { background: #60a5fa; border-color: #60a5fa; color: #fff; }
      .view-toggle-btn:hover:not(.active) { background: #f1f5f9; color: #334155; }
      .news-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem; }
      .news-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; transition: all 0.2s; display: flex; flex-direction: column; }
      .news-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12); }
      .news-time-badge { display: inline-block; background: #a7f3d0; color: #0f172a; padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; margin-bottom: 10px; margin-left: 0.5rem; width: fit-content; }
      .news-date { color: #64748b; font-size: 0.8rem; margin-bottom: 15px; padding-left: 0.5rem; }
      .news-title { font-size: 1.2rem; font-weight: 600; color: #0f172a; margin: 10px 0; padding-left: 0.5rem; }
      .news-content { color: #334155; font-size: 0.95rem; line-height: 1.6; margin: 15px 0; padding-left: 0.5rem; }
      .news-table { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
      .news-table table { width: 100%; border-collapse: collapse; }
      .news-table th, .news-table td { padding: 1rem; text-align: left; border-bottom: 1px solid #e2e8f0; }
      .news-table th { background: #f8fafc; color: #475569; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; }
      .news-table td { color: #1e293b; }
      .news-table tbody tr:hover { background: #f8fafc; }
      .filter-section { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; }
      .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; }
      .filter-item label { display: block; color: #334155; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; }
      .filter-item select { width: 100%; padding: 0.75rem; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; color: #0f172a; font-size: 0.9rem; }
      .filter-item select:focus { outline: none; border-color: #60a5fa; background: #ffffff; }
      .filter-item select option { color: #0f172a; background: #ffffff; }
      .filter-btn { padding: 0.75rem 1.5rem; background: #60a5fa; color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 0.9rem; }
      .filter-btn:hover { background: #3b82f6; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(96, 165, 250, 0.4); }
      .filter-btn:active { transform: translateY(0); }
      .clear-btn { padding: 0.75rem 1.5rem; background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-block; transition: all 0.2s; text-align: center; }
      .clear-btn:hover { background: #fecaca; }
      .empty-state { text-align: center; padding: 40px 20px; color: #64748b; }
      .empty-state-icon { font-size: 3rem; margin-bottom: 10px; }

      #table-view .datatable-top,
      #table-view .datatable-bottom {
        background: #ffffff !important;
      }
      #table-view .datatable-input {
        background: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
        color: #0f172a !important;
      }
      #table-view .datatable-input::placeholder {
        color: #64748b !important;
      }
      #table-view .datatable-selector {
        background: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
        color: #0f172a !important;
      }
      #table-view .datatable-info,
      #table-view .datatable-dropdown label {
        color: #475569 !important;
      }
      #table-view .datatable-table thead th,
      #table-view .datatable-table thead td,
      #table-view .datatable-table th {
        background: #f8fafc !important;
        color: #334155 !important;
        border-color: #e2e8f0 !important;
      }
      #table-view .datatable-table tbody tr,
      #table-view .datatable-table tbody td,
      #table-view .datatable-table td {
        background: #ffffff !important;
        color: #1e293b !important;
        border-color: #e2e8f0 !important;
      }
      #table-view .datatable-table tbody tr:hover,
      #table-view .datatable-table tbody tr:hover td {
        background: #f8fafc !important;
      }
      #table-view .datatable-pagination a,
      #table-view .datatable-pagination button {
        background: #ffffff !important;
        color: #334155 !important;
        border: 1px solid #cbd5e1 !important;
      }
      #table-view .datatable-pagination .active a,
      #table-view .datatable-pagination .active button {
        background: #60a5fa !important;
        color: #ffffff !important;
        border-color: #60a5fa !important;
      }

      /* ===== Mobile Responsive ===== */
      @media (max-width: 768px) {
        .reports-container .container { padding: 0 0.75rem 1rem; }
        .news-stats-grid { grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1.25rem; }
        .stat-card { padding: 1rem; }
        .stat-value { font-size: 1.5rem; }
        .stat-label { font-size: 0.75rem; }
        .filter-section { padding: 1rem; margin-bottom: 1.25rem; }
        .filter-grid { grid-template-columns: 1fr; gap: 0.75rem; }
        .news-cards { grid-template-columns: 1fr; gap: 1rem; }
        .news-card { padding: 1rem; }
        .news-title { font-size: 1.05rem; }
        .news-content { font-size: 0.88rem; }
        .view-toggle { margin-bottom: 1.25rem; }
        .view-toggle-btn { padding: 0.6rem 1rem; font-size: 0.85rem; }

        /* Table View: แปลง table เป็น card layout บนมือถือ */
        #table-view .datatable-table thead { display: none !important; }
        #table-view .datatable-table,
        #table-view .datatable-table tbody,
        #table-view .datatable-table tr,
        #table-view .datatable-table td {
          display: block !important;
          width: 100% !important;
        }
        #table-view .datatable-table tbody tr {
          border: 1px solid #e2e8f0 !important;
          border-radius: 10px !important;
          margin-bottom: 0.75rem !important;
          padding: 0.75rem 1rem !important;
          box-shadow: 0 1px 4px rgba(15,23,42,0.06);
        }
        #table-view .datatable-table tbody td {
          border-bottom: none !important;
          padding: 0.3rem 0 !important;
          text-align: left !important;
          max-width: 100% !important;
        }
        #table-view .datatable-table tbody td::before {
          content: attr(data-label);
          display: block;
          font-size: 0.7rem;
          font-weight: 700;
          color: #64748b !important;
          text-transform: uppercase;
          letter-spacing: 0.05em;
          margin-bottom: 0.15rem;
        }
        /* วันที่ cell */
        #table-view .datatable-table tbody td:first-child {
          font-size: 0.8rem;
          color: #60a5fa !important;
          font-weight: 600;
          padding-bottom: 0.4rem !important;
        }
        /* หัวข้อข่าว cell */
        #table-view .datatable-table tbody td:nth-child(2) {
          font-size: 1rem !important;
          padding-bottom: 0.4rem !important;
        }
        /* เนื้อหา cell */
        #table-view .datatable-table tbody td:last-child {
          font-size: 0.85rem !important;
          color: #475569 !important;
          line-height: 1.5;
        }

        #table-view .datatable-top,
        #table-view .datatable-bottom {
          flex-direction: column !important;
          align-items: stretch !important;
          gap: 0.75rem !important;
        }
        #table-view .datatable-search { margin-left: 0 !important; }
        #table-view .datatable-input { min-width: 100% !important; width: 100% !important; }
      }

      @media (max-width: 480px) {
        .news-stats-grid { grid-template-columns: 1fr; gap: 0.6rem; }
        .stat-card { padding: 0.85rem; display: flex; align-items: center; gap: 0.75rem; }
        .stat-card .lottie-icon { margin-bottom: 0; flex-shrink: 0; }
        .stat-value { font-size: 1.3rem; margin: 0; }
        .stat-label { font-size: 0.7rem; margin: 0; }
        .news-card { padding: 0.85rem; }
        .news-title { font-size: 1rem; margin: 6px 0; }
        .news-content { font-size: 0.85rem; margin: 8px 0; }
        .news-time-badge { font-size: 0.75rem; padding: 4px 10px; }
        .news-date { font-size: 0.75rem; }
      }
    </style>
  </head>
  <body class="reports-page">
    <div class="app-shell">
      <?php 
      $currentPage = 'report_news.php';
      include __DIR__ . '/../includes/sidebar.php'; 
      ?>
      <div class="app-main">
        <main class="reports-container">
          <div class="container">
            <?php 
              $pageTitle = 'รายงานข่าวประชาสัมพันธ์';
              include __DIR__ . '/../includes/page_header.php'; 
            ?>

            <!-- ตัวกรองเดือน -->
            <div class="filter-section">
              <form method="GET" action="report_news.php" id="filterForm">
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
                      <a href="report_news.php" class="clear-btn" style="flex:1;min-height:2.5rem;width:100%;display:flex;align-items:center;justify-content:center;">✕ ล้างตัวกรอง</a>
                    <?php endif; ?>
                  </div>
                </div>
              </form>
            </div>

            <!-- สถิติภาพรวม -->
            <div class="news-stats-grid">
              <div class="stat-card particle-wrapper">
                <div class="particle-container" data-particles="3"></div>
                <div class="lottie-icon cyan">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                </div>
                <div class="stat-label">ข่าวทั้งหมด</div>
                <div class="stat-value"><?php echo $allNewsCount; ?></div>
              </div>
              <div class="stat-card particle-wrapper">
                <div class="particle-container" data-particles="3"></div>
                <div class="lottie-icon indigo">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
                </div>
                <div class="stat-label">ในช่วงเวลาที่เลือก</div>
                <div class="stat-value"><?php echo $totalNews; ?></div>
              </div>
            </div>

            <!-- ปุ่มเปลี่ยนมุมมอง -->
            <div class="view-toggle">
              <button id="toggle-view-btn" class="view-toggle-btn" onclick="toggleView()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;vertical-align:middle;margin-right:4px;"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>มุมมองการ์ด</button>
            </div>

            <!-- Card View -->
            <div id="card-view" class="news-cards">
<?php if (count($rows) > 0): ?>
<?php foreach ($rows as $r): ?>
              <div class="news-card">
                <div class="news-time-badge"><?php echo getRelativeTime($r['news_date']); ?></div>
                <div class="news-date"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;vertical-align:middle;margin-right:4px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><?php echo renderField($r['news_date'], 'ยังไม่ระบุ'); ?></div>
                <div class="news-title"><?php echo renderField($r['news_title'], 'ไม่มีหัวข้อ'); ?></div>
                <div class="news-content"><?php echo renderField($r['news_details'], 'ไม่มีรายละเอียด'); ?></div>
              </div>
<?php endforeach; ?>
<?php else: ?>
              <div class="empty-state" style="grid-column: 1 / -1;">
                <div class="empty-state-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:48px;height:48px;opacity:0.5;"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8z"/></svg></div>
                <p>ไม่มีข่าวประชาสัมพันธ์ในช่วงเวลานี้</p>
              </div>
<?php endif; ?>
            </div>

            <!-- Table View -->
            <div id="table-view" class="news-table" style="display: none;">
<?php if (count($rows) > 0): ?>
              <table id="table-news-report">
                <thead>
                  <tr>
                    <th style="width: 120px;">วันที่</th>
                    <th>หัวข้อข่าว</th>
                    <th>เนื้อหา</th>
                  </tr>
                </thead>
                <tbody>
<?php foreach ($rows as $r): ?>
                  <tr>
                    <td data-label="วันที่"><?php echo renderField($r['news_date'], '-'); ?></td>
                    <td data-label="หัวข้อข่าว" style="font-weight: 600;"><?php echo renderField($r['news_title'], '-'); ?></td>
                    <td data-label="เนื้อหา" style="max-width: 400px;"><?php echo renderField($r['news_details'], '-'); ?></td>
                  </tr>
<?php endforeach; ?>
                </tbody>
              </table>
<?php else: ?>
              <div class="empty-state">
                <div class="empty-state-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:48px;height:48px;opacity:0.5;"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8z"/></svg></div>
                <p>ไม่มีข่าวประชาสัมพันธ์ในช่วงเวลานี้</p>
              </div>
<?php endif; ?>
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
            toggleButton.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;vertical-align:middle;margin-right:4px;"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>มุมมองการ์ด';
          }
          localStorage.setItem('newsViewMode', 'card');
        } else {
          cardView.style.display = 'none';
          tableView.style.display = 'block';
          if (toggleButton) {
            toggleButton.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;vertical-align:middle;margin-right:4px;"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>มุมมองตาราง';
          }
          localStorage.setItem('newsViewMode', 'table');
        }
      }

      function toggleView() {
        switchView(currentView === 'card' ? 'table' : 'card');
      }
    </script>
    
    <!-- DataTable Initialization -->
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4" defer></script>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        const newsTable = document.getElementById('table-news-report');
        if (newsTable && typeof simpleDatatables !== 'undefined') {
          new simpleDatatables.DataTable(newsTable, {
            searchable: true,
            fixedHeight: false,
            perPage: 10,
            perPageSelect: [5, 10, 25, 50],
            labels: {
              placeholder: 'ค้นหาข่าว...',
              perPage: 'รายการต่อหน้า',
              noRows: 'ไม่พบข้อมูลข่าว',
              info: 'แสดง {start} ถึง {end} จาก {rows} รายการ'
            }
          });
        }
      });
    </script>
  </body>
</html>
