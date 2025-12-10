<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
// ปิดหน้ารายงานการจองชั่วคราว
$_SESSION['error'] = 'หน้ารายงานการจองถูกปิดใช้งานชั่วคราว';
header('Location: dashboard.php');
exit;
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

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
  $monthsStmt = $pdo->query("SELECT DISTINCT DATE_FORMAT(ctr_start, '%Y-%m') as month_key FROM contract WHERE ctr_start IS NOT NULL ORDER BY month_key DESC");
  $availableMonths = $monthsStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}

// Query contract data (actual stays) with tenant and room
$whereClause = '';
if ($selectedMonth || $selectedStatus !== '') {
  $conditions = [];
  if ($selectedMonth) {
    $conditions[] = "DATE_FORMAT(c.ctr_start, '%Y-%m') = " . $pdo->quote($selectedMonth);
  }
  if ($selectedStatus !== '') {
    $conditions[] = "c.ctr_status = " . $pdo->quote($selectedStatus);
  }
  $whereClause = 'WHERE ' . implode(' AND ', $conditions);
}

try {
  $stmt = $pdo->query("SELECT c.ctr_id, c.ctr_start, c.ctr_end, c.ctr_deposit, c.ctr_status, c.tnt_id, c.room_id, t.tnt_name, r.room_number 
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
$statusLabels = [
  '0' => 'รอเข้าพัก',
  '1' => 'กำลังเข้าพัก',
  '2' => 'ยกเลิก/สิ้นสุด',
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
$totalContracts = count($rows);
try {
  $stmt = $pdo->query("SELECT COUNT(*) as total FROM contract");
  $allContractsCount = $stmt->fetch()['total'] ?? 0;
  
  // ดึง "รอการเข้าพัก" จากจำนวนผู้เช่า (tnt_status = 2)
  $stmt = $pdo->query("SELECT COUNT(*) as total FROM tenant WHERE tnt_status = 2");
  $contractsPending = $stmt->fetch()['total'] ?? 0;
  
  $stmt = $pdo->query("SELECT COUNT(*) as total FROM contract WHERE ctr_status = 1");
  $contractsActive = $stmt->fetch()['total'] ?? 0;
  
  $stmt = $pdo->query("SELECT COUNT(*) as total FROM contract WHERE ctr_status = 2");
  $contractsCancelled = $stmt->fetch()['total'] ?? 0;
} catch (PDOException $e) {
  $allContractsCount = $contractsPending = $contractsActive = $contractsCancelled = 0;
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
    <style>
      .reports-container { width: 100%; max-width: 100%; padding: 0; }
      .reports-container .container { max-width: 100%; width: 100%; padding: 1.5rem; }
      .booking-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
      .stat-card { background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); transition: transform 0.2s, box-shadow 0.2s; }
      .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3); }
      .stat-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
      .stat-label { font-size: 0.85rem; color: #cbd5e1; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
      .stat-value { font-size: 2rem; font-weight: 700; color: #f8fafc; margin: 0.5rem 0; }
      .view-toggle { display: flex; gap: 0.5rem; margin-bottom: 2rem; }
      .view-toggle-btn { padding: 0.75rem 1.5rem; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #94a3b8; cursor: pointer; transition: all 0.2s; font-weight: 600; }
      .view-toggle-btn.active { background: #60a5fa; border-color: #60a5fa; color: #fff; }
      .view-toggle-btn:hover:not(.active) { background: rgba(255, 255, 255, 0.08); color: #e2e8f0; }
      .status-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 2rem; }
      .status-btn { padding: 0.75rem 1.5rem; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #94a3b8; cursor: pointer; transition: all 0.2s; font-weight: 600; text-decoration: none; display: inline-block; }
      .status-btn.active { background: #60a5fa; border-color: #60a5fa; color: #fff; }
      .status-btn:hover:not(.active) { background: rgba(255, 255, 255, 0.08); color: #e2e8f0; }
      .booking-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem; }
      .booking-card { background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 1.5rem; transition: all 0.2s; }
      .booking-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3); }
      .booking-time-badge { display: inline-block; background: #a7f3d0; color: #0f172a; padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; margin-bottom: 10px; }
      .booking-date { color: #94a3b8; font-size: 0.8rem; margin-bottom: 15px; }
      .booking-info { color: #cbd5e1; font-size: 0.95rem; line-height: 1.6; margin: 15px 0; }
      .booking-status { padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; display: inline-block; margin-top: 10px; }
      .status-pending { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }
      .status-reserved { background: rgba(96, 165, 250, 0.15); color: #60a5fa; }
      .status-checked-in { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
      .booking-table { background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; overflow: hidden; }
      .booking-table table { width: 100%; border-collapse: collapse; }
      .booking-table th, .booking-table td { padding: 1rem; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
      .booking-table th { background: rgba(255, 255, 255, 0.05); color: #cbd5e1; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; }
      .booking-table td { color: #e2e8f0; }
      .booking-table tbody tr:hover { background: rgba(255, 255, 255, 0.03); }
      .filter-section { background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; }
      .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; }
      .filter-item label { display: block; color: #cbd5e1; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; }
      .filter-item select { width: 100%; padding: 0.75rem; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #f8fafc; font-size: 0.9rem; }
      .filter-item select:focus { outline: none; border-color: #60a5fa; background: rgba(255, 255, 255, 0.08); }
      .filter-btn { padding: 0.75rem 1.5rem; background: #60a5fa; color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 0.9rem; }
      .filter-btn:hover { background: #3b82f6; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(96, 165, 250, 0.4); }
      .filter-btn:active { transform: translateY(0); }
      .clear-btn { padding: 0.75rem 1.5rem; background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-block; transition: all 0.2s; text-align: center; }
      .clear-btn:hover { background: rgba(239, 68, 68, 0.25); }
      .empty-state { text-align: center; padding: 40px 20px; color: #94a3b8; }
      .empty-state-icon { font-size: 3rem; margin-bottom: 10px; }
    </style>
  </head>
  <body class="reports-page">
    <div class="app-shell">
      <?php 
      $currentPage = 'report_booking.php';
      include __DIR__ . '/../includes/sidebar.php'; 
      ?>
      <div class="app-main">
        <main class="reports-container">
          <div class="container">
            <?php 
              $pageTitle = 'รายงานข้อมูลการเข้าพัก';
              include __DIR__ . '/../includes/page_header.php'; 
            ?>

            <!-- ตัวกรองเดือน -->
            <div class="filter-section">
              <form method="GET" action="report_booking.php" id="filterForm">
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
                    <button type="button" class="filter-btn" onclick="document.getElementById('filterForm').submit();" style="flex:1;min-height:2.5rem;width:100%;">🔍 กรองข้อมูล</button>
                    <?php if ($selectedMonth): ?>
                      <a href="report_booking.php" class="clear-btn" style="flex:1;min-height:2.5rem;width:100%;display:flex;align-items:center;justify-content:center;">✕ ล้างตัวกรอง</a>
                    <?php endif; ?>
                  </div>
                </div>
              </form>
            </div>

            <!-- สถิติภาพรวม -->
            <div class="booking-stats-grid">
              <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-label">รอเข้าพัก</div>
                <div class="stat-value"><?php echo $contractsPending; ?></div>
              </div>
              <div class="stat-card">
                <div class="stat-icon">🏠</div>
                <div class="stat-label">กำลังเข้าพัก</div>
                <div class="stat-value"><?php echo $contractsActive; ?></div>
              </div>
              <div class="stat-card">
                <div class="stat-icon">❌</div>
                <div class="stat-label">ยกเลิก/สิ้นสุด</div>
                <div class="stat-value"><?php echo $contractsCancelled; ?></div>
              </div>
            </div>

            <!-- ปุ่มสถานะ -->
            <div class="status-buttons">
              <a href="report_booking.php" class="status-btn <?php echo !isset($_GET['status']) ? 'active' : ''; ?>">ทั้งหมด</a>
              <a href="report_booking.php?status=0" class="status-btn <?php echo isset($_GET['status']) && $_GET['status'] === '0' ? 'active' : ''; ?>">รอเข้าพัก</a>
              <a href="report_booking.php?status=1" class="status-btn <?php echo isset($_GET['status']) && $_GET['status'] === '1' ? 'active' : ''; ?>">กำลังเข้าพัก</a>
              <a href="report_booking.php?status=2" class="status-btn <?php echo isset($_GET['status']) && $_GET['status'] === '2' ? 'active' : ''; ?>">ยกเลิก/สิ้นสุด</a>
            </div>

            <!-- ปุ่มเปลี่ยนมุมมอง -->
            <div class="view-toggle">
              <button type="button" class="view-toggle-btn active" onclick="switchView('card')">📇 มุมมองการ์ด</button>
              <button type="button" class="view-toggle-btn" onclick="switchView('table')">📋 มุมมองตาราง</button>
            </div>

            <!-- Card View -->
            <div id="card-view" class="booking-cards">
<?php if (count($rows) > 0): ?>
<?php foreach ($rows as $r): 
  $statusClass = match($r['ctr_status']) {
    '0' => 'status-pending',
    '1' => 'status-reserved',
    '2' => 'status-checked-in',
    default => 'status-pending'
  };
  $statusLabel = $statusLabels[$r['ctr_status']] ?? 'ไม่ทราบ';
?>
              <div class="booking-card">
                <div class="booking-time-badge"><?php echo getRelativeTime($r['ctr_start']); ?></div>
                <div class="booking-date">📅 เริ่ม: <?php echo getRelativeTime($r['ctr_start']); ?></div>
                <div class="booking-info">
                  <div><strong>ผู้เช่า:</strong> <?php echo renderField($r['tnt_name'], 'ไม่ระบุ'); ?></div>
                  <div><strong>ห้องพัก:</strong> <?php echo renderField($r['room_number'], 'ไม่ระบุ'); ?></div>
                  <div><strong>สิ้นสุด:</strong> <?php echo getRelativeTime($r['ctr_end']); ?></div>
                  <div><strong>มัดจำ:</strong> <?php echo number_format((int)($r['ctr_deposit'] ?? 0)); ?> บาท</div>
                  <div><strong>รหัส:</strong> #<?php echo renderField((string)$r['ctr_id'], 'ไม่ระบุ'); ?></div>
                </div>
                <div class="booking-status <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></div>
              </div>
<?php endforeach; ?>
<?php else: ?>
              <div class="empty-state" style="grid-column: 1 / -1;">
                <div class="empty-state-icon">📭</div>
                <p>ไม่มีข้อมูลการเข้าพัก</p>
              </div>
<?php endif; ?>
            </div>

            <!-- Table View -->
            <div id="table-view" class="booking-table" style="display: none;">
<?php if (count($rows) > 0): ?>
              <table>
                <thead>
                  <tr>
                    <th style="width: 80px;">รหัส</th>
                    <th>ผู้เช่า</th>
                    <th>ห้องพัก</th>
                    <th style="width: 200px;">ช่วงเข้าพัก</th>
                    <th style="width: 100px;">มัดจำ</th>
                    <th style="width: 120px;">สถานะ</th>
                  </tr>
                </thead>
                <tbody>
<?php foreach ($rows as $r): 
  $statusClass = match($r['ctr_status']) {
    '0' => 'status-pending',
    '1' => 'status-reserved',
    '2' => 'status-checked-in',
    default => 'status-pending'
  };
  $statusLabel = $statusLabels[$r['ctr_status']] ?? 'ไม่ทราบ';
?>
                  <tr>
                    <td>#<?php echo renderField((string)$r['ctr_id'], '-'); ?></td>
                    <td><?php echo renderField($r['tnt_name'], '-'); ?></td>
                    <td><strong><?php echo renderField($r['room_number'], '-'); ?></strong></td>
                    <td><?php echo renderField($r['ctr_start'], '-'); ?> → <?php echo renderField($r['ctr_end'], '-'); ?></td>
                    <td><?php echo number_format((int)($r['ctr_deposit'] ?? 0)); ?></td>
                    <td><span class="booking-status <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                  </tr>
<?php endforeach; ?>
                </tbody>
              </table>
<?php else: ?>
              <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <p>ไม่มีข้อมูลการเข้าพัก</p>
              </div>
<?php endif; ?>
            </div>
          </div>
        </main>
      </div>
    </div>

    <script src="../Assets/Javascript/animate-ui.js" defer></script>
    <script src="../Assets/Javascript/main.js" defer></script>
    <script>
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
          localStorage.setItem('bookingViewMode', 'card');
        } else {
          cardView.style.display = 'none';
          tableView.style.display = 'block';
          buttons[1].classList.add('active');
          localStorage.setItem('bookingViewMode', 'table');
        }
      }

      window.addEventListener('load', function() {
        // Restore saved view
        const savedView = localStorage.getItem('bookingViewMode') || 'card';
        switchView(savedView);
      });
    </script>
  </body>
</html>
