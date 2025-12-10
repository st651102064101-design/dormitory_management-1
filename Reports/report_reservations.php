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

// ดึง theme color จากการตั้งค่าระบบ
$settingsStmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'theme_color' LIMIT 1");
$themeColor = '#0f172a'; // ค่า default (dark mode)
if ($settingsStmt) {
    $theme = $settingsStmt->fetch(PDO::FETCH_ASSOC);
    if ($theme && !empty($theme['setting_value'])) {
        $themeColor = htmlspecialchars($theme['setting_value'], ENT_QUOTES, 'UTF-8');
    }
}

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
  '0' => 'รอการเข้าพัก',
  '1' => 'จองแล้ว',
  '2' => 'ยืนยันแล้ว',
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
  // รอการเข้าพัก - นับจาก tenant ที่มี tnt_status = 2
  $stmt = $pdo->query("SELECT COUNT(*) as total FROM tenant WHERE tnt_status = 2");
  $bookingPending = $stmt->fetch()['total'] ?? 0;
  
  $stmt = $pdo->query("SELECT COUNT(*) as total FROM booking WHERE bkg_status = 1");
  $bookingConfirmed = $stmt->fetch()['total'] ?? 0;
  
  $stmt = $pdo->query("SELECT COUNT(*) as total FROM booking WHERE bkg_status = 2");
  $bookingCompleted = $stmt->fetch()['total'] ?? 0;
} catch (PDOException $e) {
  $bookingPending = $bookingConfirmed = $bookingCompleted = 0;
}
?>
<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - รายงานการจอง</title>
    <link rel="icon" type="image/jpeg" href="../Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="../Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="../Assets/Css/main.css" />
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
    </style>
  </head>
  <body class="reports-page">
    <div class="app-shell">
      <?php include __DIR__ . '/../includes/sidebar.php'; ?>
      <main class="app-main">
        <div class="reports-container">
          <div class="container">
            <h1 style="font-size:2rem;font-weight:700;margin-bottom:2rem;color:#f8fafc;">📝 รายงานการจอง</h1>
            
            <!-- Stat Cards -->
            <div class="reservation-stats-grid">
              <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-label">รอการเข้าพัก</div>
                <div class="stat-value"><?php echo $bookingPending; ?></div>
              </div>
              <div class="stat-card">
                <div class="stat-icon">📌</div>
                <div class="stat-label">จองแล้ว</div>
                <div class="stat-value"><?php echo $bookingConfirmed; ?></div>
              </div>
              <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-label">ยืนยันแล้ว</div>
                <div class="stat-value"><?php echo $bookingCompleted; ?></div>
              </div>
            </div>

            <!-- ปุ่มสถานะ -->
            <div class="status-buttons">
              <a href="report_reservations.php" class="status-btn <?php echo !isset($_GET['status']) ? 'active' : ''; ?>">ทั้งหมด</a>
              <a href="report_reservations.php?status=0" class="status-btn <?php echo isset($_GET['status']) && $_GET['status'] === '0' ? 'active' : ''; ?>">รอการเข้าพัก</a>
              <a href="report_reservations.php?status=1" class="status-btn <?php echo isset($_GET['status']) && $_GET['status'] === '1' ? 'active' : ''; ?>">จองแล้ว</a>
              <a href="report_reservations.php?status=2" class="status-btn <?php echo isset($_GET['status']) && $_GET['status'] === '2' ? 'active' : ''; ?>">ยืนยันแล้ว</a>
            </div>

            <!-- ปุ่มเปลี่ยนมุมมอง -->
            <div class="view-toggle">
              <button type="button" class="view-toggle-btn active" onclick="switchView('card')">📇 มุมมองการ์ด</button>
              <button type="button" class="view-toggle-btn" onclick="switchView('table')">📋 มุมมองตาราง</button>
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
                <div class="reservation-date">📅 จอง: <?php echo getRelativeTime($r['bkg_date']); ?></div>
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
                <div class="empty-icon">📭</div>
                <div class="empty-text">ไม่มีข้อมูลการจอง</div>
              </div>
<?php endif; ?>
            </div>

            <!-- Table View -->
            <div id="table-view" class="reservation-table" style="display:none;">
<?php if (count($rows) > 0): ?>
              <table>
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
                <div class="empty-icon">📭</div>
                <div class="empty-text">ไม่มีข้อมูลการจอง</div>
              </div>
<?php endif; ?>
            </div>
          </div>
        </div>
      </main>
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
          localStorage.setItem('reservationViewMode', 'card');
        } else {
          cardView.style.display = 'none';
          tableView.style.display = 'block';
          buttons[1].classList.add('active');
          localStorage.setItem('reservationViewMode', 'table');
        }
      }

      window.addEventListener('load', function() {
        // Restore saved view
        const savedView = localStorage.getItem('reservationViewMode') || 'card';
        switchView(savedView);
        
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
  </body>
</html>
