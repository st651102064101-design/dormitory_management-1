<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

// รับค่า status filter - Default แสดงกำลังเข้าพัก (สถานะ 0 = ปกติ)
$selectedStatus = isset($_GET['status']) ? $_GET['status'] : '0';

// Query with status filter - แสดงเฉพาะกำลังเข้าพัก (1) และยกเลิก/สิ้นสุด (2)
$whereClause = '';
if ($selectedStatus !== '') {
  $whereClause = "WHERE c.ctr_status = " . $pdo->quote($selectedStatus);
}

try {
  $stmt = $pdo->query("SELECT c.ctr_id, c.ctr_start, c.ctr_end, c.ctr_deposit, c.ctr_status, c.tnt_id, t.tnt_name, r.room_number, c.room_id
FROM contract c
LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
LEFT JOIN room r ON c.room_id = r.room_id
$whereClause
ORDER BY c.ctr_start DESC");
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  error_log('Contract query error: ' . $e->getMessage());
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

$statusLabels = [
  '0' => 'กำลังเข้าพัก',
  '1' => 'ยกเลิกสัญญา',
  '2' => 'แจ้งยกเลิก',
];

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
$totalContracts = count($rows);
try {
  // 0 = ปกติ (กำลังเข้าพัก), 1 = ยกเลิกสัญญา, 2 = แจ้งยกเลิก
  $stmt = $pdo->query("SELECT COUNT(*) as total FROM contract WHERE ctr_status = 0");
  $contractsActive = $stmt->fetch()['total'] ?? 0;
  
  $stmt = $pdo->query("SELECT COUNT(*) as total FROM contract WHERE ctr_status = 1");
  $contractsCancelled = $stmt->fetch()['total'] ?? 0;
  
  $stmt = $pdo->query("SELECT COUNT(*) as total FROM contract WHERE ctr_status = 2");
  $contractsPendingCancel = $stmt->fetch()['total'] ?? 0;
} catch (PDOException $e) {
  $contractsActive = $contractsCancelled = $contractsPendingCancel = 0;
}
?>
<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - รายงานข้อมูลการเข้าพัก</title>
    <link rel="icon" type="image/jpeg" href="../Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="../Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="../Assets/Css/main.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4/dist/style.css" />
    <link rel="stylesheet" href="../Assets/Css/datatable-modern.css" />
    <style>
      .reports-container { width: 100%; max-width: 100%; padding: 0; }
      .reports-container .container { max-width: 100%; width: 100%; padding: 1.5rem; }
      .stay-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
      .stat-card { background: linear-gradient(135deg, rgba(18,24,40,0.85), rgba(7,13,26,0.95)); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px; padding: 1.5rem; box-shadow: 0 15px 35px rgba(3,7,18,0.4); transition: transform 0.3s cubic-bezier(0.2, 0.55, 0.45, 0.8), box-shadow 0.3s; }
      .stat-card:hover { transform: translateY(-6px); box-shadow: 0 20px 40px rgba(3,7,18,0.5); }
      .stat-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
      .stat-label { font-size: 0.85rem; color: rgba(255,255,255,0.7); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
      .stat-value { font-size: 2.2rem; font-weight: 700; color: #f8fafc; margin: 0.5rem 0; }
      .view-toggle { display: flex; gap: 0.5rem; margin-bottom: 2rem; }
      .view-toggle-btn { padding: 0.75rem 1.5rem; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 10px; color: #94a3b8; cursor: pointer; transition: all 0.3s cubic-bezier(0.2, 0.55, 0.45, 0.8); font-weight: 600; }
      .view-toggle-btn.active { background: linear-gradient(135deg, #3b82f6, #60a5fa); border-color: transparent; color: #fff; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4); }
      .view-toggle-btn:hover:not(.active) { background: rgba(255, 255, 255, 0.1); color: #e2e8f0; transform: translateY(-2px); }
      .status-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 2rem; }
      .status-btn { padding: 0.75rem 1.5rem; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 10px; color: #94a3b8; cursor: pointer; transition: all 0.3s cubic-bezier(0.2, 0.55, 0.45, 0.8); font-weight: 600; text-decoration: none; display: inline-block; }
      .status-btn.active { background: linear-gradient(135deg, #3b82f6, #60a5fa); border-color: transparent; color: #fff; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4); }
      .status-btn:hover:not(.active) { background: rgba(255, 255, 255, 0.1); color: #e2e8f0; transform: translateY(-2px); }
      .stay-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem; }
      .stay-card { background: linear-gradient(135deg, rgba(18,24,40,0.85), rgba(7,13,26,0.95)); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px; padding: 1.5rem; transition: all 0.3s cubic-bezier(0.2, 0.55, 0.45, 0.8); }
      .stay-card:hover { transform: translateY(-4px); box-shadow: 0 15px 35px rgba(3,7,18,0.5); border-color: rgba(96, 165, 250, 0.3); }
      .stay-time-badge { display: inline-block; background: linear-gradient(135deg, #10b981, #34d399); color: #fff; padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; margin-bottom: 10px; box-shadow: 0 2px 10px rgba(16, 185, 129, 0.3); }
      .stay-date { color: #94a3b8; font-size: 0.8rem; margin-bottom: 15px; }
      .stay-info { color: #cbd5e1; font-size: 0.95rem; line-height: 1.8; margin: 15px 0; }
      .stay-status { padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.85rem; font-weight: 600; display: inline-block; margin-top: 10px; }
      .status-pending { background: rgba(251, 191, 36, 0.15); color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.3); }
      .status-active { background: rgba(34, 197, 94, 0.15); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3); }
      .status-cancelled { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }

      /* Modern DataTable Styles */
      .stay-table { 
        background: linear-gradient(135deg, rgba(18,24,40,0.9), rgba(7,13,26,0.95)); 
        border: 1px solid rgba(255, 255, 255, 0.1); 
        border-radius: 16px; 
        overflow: hidden; 
        padding: 1.5rem;
        box-shadow: 0 15px 35px rgba(3,7,18,0.4);
      }
      
      /* DataTable Wrapper */
      .datatable-wrapper {
        background: transparent !important;
      }
      .datatable-wrapper .datatable-top,
      .datatable-wrapper .datatable-bottom {
        padding: 1rem 0;
      }
      
      /* Search Input */
      .datatable-wrapper .datatable-input {
        padding: 0.75rem 1rem;
        background: rgba(15, 23, 42, 0.8) !important;
        border: 1px solid rgba(148, 163, 184, 0.2) !important;
        border-radius: 12px !important;
        color: #e2e8f0 !important;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        min-width: 250px;
      }
      .datatable-wrapper .datatable-input:focus {
        border-color: #60a5fa !important;
        box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.2) !important;
        outline: none !important;
      }
      .datatable-wrapper .datatable-input::placeholder {
        color: #64748b;
      }
      
      /* Per Page Select */
      .datatable-wrapper .datatable-selector {
        padding: 0.6rem 2rem 0.6rem 1rem;
        background: rgba(15, 23, 42, 0.8) !important;
        border: 1px solid rgba(148, 163, 184, 0.2) !important;
        border-radius: 10px !important;
        color: #e2e8f0 !important;
        font-size: 0.9rem;
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E") !important;
        background-repeat: no-repeat !important;
        background-position: right 0.5rem center !important;
        background-size: 1.2rem !important;
      }
      .datatable-wrapper .datatable-selector:focus {
        border-color: #60a5fa !important;
        outline: none !important;
      }
      
      /* Info Text */
      .datatable-wrapper .datatable-info {
        color: #94a3b8 !important;
        font-size: 0.9rem;
      }
      
      /* Table */
      .datatable-wrapper table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
      }
      .datatable-wrapper table thead {
        background: linear-gradient(135deg, rgba(30, 41, 59, 0.8), rgba(15, 23, 42, 0.9)) !important;
      }
      .datatable-wrapper table thead th {
        padding: 1rem 1.25rem !important;
        color: #f1f5f9 !important;
        font-weight: 600 !important;
        font-size: 0.85rem !important;
        text-transform: uppercase !important;
        letter-spacing: 0.05em !important;
        border-bottom: 2px solid rgba(96, 165, 250, 0.3) !important;
        background: transparent !important;
        white-space: nowrap;
        cursor: pointer;
        transition: all 0.2s;
      }
      .datatable-wrapper table thead th:hover {
        color: #60a5fa !important;
      }
      .datatable-wrapper table thead th.datatable-ascending::after,
      .datatable-wrapper table thead th.datatable-descending::after {
        border-color: #60a5fa transparent !important;
      }
      
      /* Table Body */
      .datatable-wrapper table tbody tr {
        transition: all 0.2s ease;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
      }
      .datatable-wrapper table tbody tr:hover {
        background: rgba(96, 165, 250, 0.08) !important;
      }
      .datatable-wrapper table tbody td {
        padding: 1rem 1.25rem !important;
        color: #e2e8f0 !important;
        font-size: 0.95rem !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
        vertical-align: middle;
      }
      
      /* Pagination */
      .datatable-wrapper .datatable-pagination {
        margin-top: 1rem;
      }
      .datatable-wrapper .datatable-pagination-list {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        justify-content: center;
        list-style: none;
        padding: 0;
        margin: 0;
      }
      .datatable-wrapper .datatable-pagination-list-item {
        margin: 0;
      }
      .datatable-wrapper .datatable-pagination-list-item a,
      .datatable-wrapper .datatable-pagination-list-item button {
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 40px;
        height: 40px;
        padding: 0 0.75rem;
        background: rgba(30, 41, 59, 0.6) !important;
        border: 1px solid rgba(148, 163, 184, 0.2) !important;
        border-radius: 10px !important;
        color: #94a3b8 !important;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.2s ease;
        cursor: pointer;
      }
      .datatable-wrapper .datatable-pagination-list-item a:hover,
      .datatable-wrapper .datatable-pagination-list-item button:hover {
        background: rgba(96, 165, 250, 0.2) !important;
        border-color: rgba(96, 165, 250, 0.4) !important;
        color: #60a5fa !important;
        transform: translateY(-2px);
      }
      .datatable-wrapper .datatable-pagination-list-item.datatable-active a,
      .datatable-wrapper .datatable-pagination-list-item.datatable-active button {
        background: linear-gradient(135deg, #3b82f6, #60a5fa) !important;
        border-color: transparent !important;
        color: #fff !important;
        box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
      }
      .datatable-wrapper .datatable-pagination-list-item.datatable-disabled a,
      .datatable-wrapper .datatable-pagination-list-item.datatable-disabled button {
        opacity: 0.4;
        cursor: not-allowed;
        pointer-events: none;
      }

      .empty-state { text-align: center; padding: 3rem 1rem; color: #94a3b8; }
      .empty-icon { font-size: 4rem; margin-bottom: 1rem; opacity: 0.5; }
      .empty-text { font-size: 1.1rem; font-weight: 600; margin-bottom: 0.5rem; }
    </style>
  </head>
  <body class="reports-page">
    <div class="app-shell">
      <?php include __DIR__ . '/../includes/sidebar.php'; ?>
      <main class="app-main">
        <div class="reports-container">
          <?php include __DIR__ . '/../includes/page_header.php'; ?>
          <div class="container">
            <h1 style="font-size:2rem;font-weight:700;margin-bottom:2rem;color:#f8fafc;">📊 รายงานข้อมูลการเข้าพัก</h1>
            
            <!-- Stat Cards -->
            <div class="stay-stats-grid">
              <div class="stat-card">
                <div class="stat-icon">🏠</div>
                <div class="stat-label">กำลังเข้าพัก</div>
                <div class="stat-value"><?php echo $contractsActive; ?></div>
              </div>
              <div class="stat-card">
                <div class="stat-icon">❌</div>
                <div class="stat-label">ยกเลิกสัญญา</div>
                <div class="stat-value"><?php echo $contractsCancelled; ?></div>
              </div>
              <div class="stat-card">
                <div class="stat-icon">⚠️</div>
                <div class="stat-label">แจ้งยกเลิก</div>
                <div class="stat-value"><?php echo $contractsPendingCancel; ?></div>
              </div>
            </div>

            <!-- ปุ่มสถานะ -->
            <div class="status-buttons">
              <a href="manage_stay.php?status=0" class="status-btn <?php echo !isset($_GET['status']) || $_GET['status'] === '0' ? 'active' : ''; ?>">กำลังเข้าพัก</a>
              <a href="manage_stay.php?status=1" class="status-btn <?php echo isset($_GET['status']) && $_GET['status'] === '1' ? 'active' : ''; ?>">ยกเลิกสัญญา</a>
              <a href="manage_stay.php?status=2" class="status-btn <?php echo isset($_GET['status']) && $_GET['status'] === '2' ? 'active' : ''; ?>">แจ้งยกเลิก</a>
            </div>

            <!-- ปุ่มเปลี่ยนมุมมอง -->
            <div class="view-toggle">
              <button type="button" class="view-toggle-btn active" onclick="switchView('card')">📇 มุมมองการ์ด</button>
              <button type="button" class="view-toggle-btn" onclick="switchView('table')">📋 มุมมองตาราง</button>
            </div>

            <!-- Card View -->
            <div id="card-view" class="stay-cards">
<?php if (count($rows) > 0): ?>
<?php foreach ($rows as $r): 
  $statusClass = match($r['ctr_status']) {
    '0' => 'status-active',
    '1' => 'status-cancelled',
    '2' => 'status-pending',
    default => 'status-active'
  };
  $statusLabel = $statusLabels[$r['ctr_status']] ?? 'ไม่ทราบ';
?>
              <div class="stay-card">
                <div class="stay-time-badge"><?php echo getRelativeTime($r['ctr_start']); ?></div>
                <div class="stay-date">📅 เริ่ม: <?php echo getRelativeTime($r['ctr_start']); ?></div>
                <div class="stay-info">
                  <div><strong>ผู้เช่า:</strong> <?php echo renderField($r['tnt_name'], 'ไม่ระบุ'); ?></div>
                  <div><strong>ห้องพัก:</strong> <?php echo renderField($r['room_number'], 'ไม่ระบุ'); ?></div>
                  <div><strong>สิ้นสุด:</strong> <?php echo getRelativeTime($r['ctr_end']); ?></div>
                  <div><strong>มัดจำ:</strong> <?php echo number_format((int)($r['ctr_deposit'] ?? 0)); ?> บาท</div>
                  <div><strong>รหัส:</strong> #<?php echo renderField((string)$r['ctr_id'], 'ไม่ระบุ'); ?></div>
                </div>
                <span class="stay-status <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
              </div>
<?php endforeach; ?>
<?php else: ?>
              <div class="empty-state">
                <div class="empty-icon">📭</div>
                <div class="empty-text">ไม่มีข้อมูลการเข้าพัก</div>
              </div>
<?php endif; ?>
            </div>

            <!-- Table View -->
            <div id="table-view" class="stay-table" style="display:none;">
<?php if (count($rows) > 0): ?>
              <table id="stayTable">
                <thead>
                  <tr>
                    <th>รหัสสัญญา</th>
                    <th>ผู้เช่า</th>
                    <th>ห้อง</th>
                    <th>ช่วงเข้าพัก</th>
                    <th>มัดจำ</th>
                    <th>สถานะ</th>
                  </tr>
                </thead>
                <tbody>
<?php foreach ($rows as $r): 
  $statusClass = match($r['ctr_status']) {
    '0' => 'status-active',
    '1' => 'status-cancelled',
    '2' => 'status-pending',
    default => 'status-active'
  };
  $statusLabel = $statusLabels[$r['ctr_status']] ?? 'ไม่ทราบ';
?>
                  <tr>
                    <td>#<?php echo renderField((string)$r['ctr_id'], '—'); ?></td>
                    <td><?php echo renderField($r['tnt_name'], '—'); ?></td>
                    <td><?php echo renderField($r['room_number'], '—'); ?></td>
                    <td><?php echo renderField($r['ctr_start'], '—'); ?> → <?php echo renderField($r['ctr_end'], '—'); ?></td>
                    <td><?php echo number_format((int)($r['ctr_deposit'] ?? 0)); ?></td>
                    <td><span class="stay-status <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                  </tr>
<?php endforeach; ?>
                </tbody>
              </table>
<?php else: ?>
              <div class="empty-state">
                <div class="empty-icon">📭</div>
                <div class="empty-text">ไม่มีข้อมูลการเข้าพัก</div>
              </div>
<?php endif; ?>
            </div>
          </div>
        </div>
      </main>
    </div>
    <script src="../Assets/Javascript/animate-ui.js" defer></script>
    <script src="../Assets/Javascript/main.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4" type="text/javascript"></script>
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

      let dataTable = null;

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
          localStorage.setItem('stayViewMode', 'card');
        } else {
          cardView.style.display = 'none';
          tableView.style.display = 'block';
          buttons[1].classList.add('active');
          localStorage.setItem('stayViewMode', 'table');
          
          // Initialize DataTable when switching to table view
          if (!dataTable) {
            const stayTable = document.getElementById('stayTable');
            if (stayTable) {
              dataTable = new simpleDatatables.DataTable(stayTable, {
                searchable: true,
                fixedHeight: false,
                perPage: 10,
                perPageSelect: [5, 10, 25, 50, 100],
                labels: {
                  placeholder: 'ค้นหา...',
                  perPage: 'รายการต่อหน้า',
                  noRows: 'ไม่พบข้อมูล',
                  info: 'แสดง {start} ถึง {end} จาก {rows} รายการ'
                }
              });
            }
          }
        }
      }

      window.addEventListener('load', function() {
        // Restore saved view
        const savedView = localStorage.getItem('stayViewMode') || 'card';
        switchView(savedView);
      });
    </script>
  </body>
</html>
