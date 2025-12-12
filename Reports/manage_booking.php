<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

// รับค่า sort จาก query parameter
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$orderBy = 'b.bkg_date DESC';

switch ($sortBy) {
  case 'oldest':
    $orderBy = 'b.bkg_date ASC';
    break;
  case 'room_number':
    $orderBy = 'r.room_number ASC';
    break;
  case 'newest':
  default:
    $orderBy = 'b.bkg_date DESC';
}

// ดึงข้อมูลห้องที่ว่าง (room_status = '0')
$stmt = $pdo->query("
    SELECT r.*, rt.type_name, rt.type_price 
    FROM room r 
    LEFT JOIN roomtype rt ON r.type_id = rt.type_id 
    WHERE r.room_status = '0'
    ORDER BY r.room_number
");
$availableRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงข้อมูลผู้เช่าทั้งหมด (นับทุกสถานะที่มีอยู่)
$stmtAllTenants = $pdo->query("
  SELECT COUNT(*) as total_tenants
  FROM tenant
");
$allTenantsResult = $stmtAllTenants->fetch(PDO::FETCH_ASSOC);
$totalTenants = (int)($allTenantsResult['total_tenants'] ?? 0);

// ดึงข้อมูลผู้เช่าที่เลือกได้ (ดึงทั้งหมดตามชื่อ)
$stmtTenants = $pdo->query("
  SELECT tnt_id, tnt_name, tnt_phone 
  FROM tenant 
  ORDER BY tnt_name ASC
");
$selectableTenants = $stmtTenants->fetchAll(PDO::FETCH_ASSOC);

// ดึงข้อมูลการจองทั้งหมด (รวมยกเลิก)
$stmtBookings = $pdo->query("
  SELECT b.*, r.room_number, rt.type_name, rt.type_price
  FROM booking b
  LEFT JOIN room r ON b.room_id = r.room_id
  LEFT JOIN roomtype rt ON r.type_id = rt.type_id
  ORDER BY $orderBy
");
$bookings = $stmtBookings->fetchAll(PDO::FETCH_ASSOC);

// Auto-cancel bookings that reached check-in date without confirmation
// วันที่เข้าพักถ้าน้อยกว่า/เท่ากับวันนี้ และยังไม่ได้เข้าพัก (status='1') ให้ยกเลิก (status='0')
try {
  $today = date('Y-m-d');
  $autoCancelStmt = $pdo->prepare("
    UPDATE booking 
    SET bkg_status = '0', bkg_notes = CONCAT(COALESCE(bkg_notes, ''), '\nระบบยกเลิกอัตโนมัติ: ถึงวันที่เข้าพักแล้วไม่มีการยืนยัน')
    WHERE bkg_status = '1' 
    AND DATE(bkg_checkin_date) <= ?
    AND bkg_status NOT IN ('2', '0')
  ");
  $autoCancelStmt->execute([$today]);
} catch (PDOException $e) {
  // Log error but don't stop page load
  error_log("Auto-cancel booking error: " . $e->getMessage());
}

// Re-fetch bookings after auto-cancellation (show all statuses including cancelled)
$stmtBookings = $pdo->query("
  SELECT b.*, r.room_number, rt.type_name, rt.type_price, t.tnt_name
  FROM booking b
  LEFT JOIN room r ON b.room_id = r.room_id
  LEFT JOIN roomtype rt ON r.type_id = rt.type_id
  LEFT JOIN tenant t ON b.tnt_id = t.tnt_id
  ORDER BY $orderBy
");
$bookings = $stmtBookings->fetchAll(PDO::FETCH_ASSOC);

// คำนวณสถิติ
$stats = [
  'total' => $totalTenants,
  'reserved' => 0,
  'checkedin' => 0,
];
foreach ($bookings as $bkg) {
  $status = (string)($bkg['bkg_status'] ?? '');
  if ($status !== '0') {
    if ($status === '1') {
      $stats['reserved']++;
    } elseif ($status === '2') {
      $stats['checkedin']++;
    }
  }
}

$statusMap = [
  '0' => 'ยกเลิก',
  '1' => 'จองแล้ว',
  '2' => 'เข้าพักแล้ว',
];

// ดึงค่าตั้งค่าระบบ
$siteName = 'Sangthian Dormitory';
$logoFilename = 'Logo.jpg';
$roomFeatures = ['ไฟฟ้า', 'น้ำประปา', 'WiFi']; // ค่า default
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename', 'room_features')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
        if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
        if ($row['setting_key'] === 'room_features' && !empty($row['setting_value'])) {
            $roomFeatures = array_map('trim', explode(',', $row['setting_value']));
        }
    }
} catch (PDOException $e) {}
?>
<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - จองห้องพัก</title>
    <link rel="icon" type="image/jpeg" href="../Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="../Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="../Assets/Css/main.css" />
    <link rel="stylesheet" href="../Assets/Css/confirm-modal.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4/dist/style.css" />
    <link rel="stylesheet" href="../Assets/Css/datatable-modern.css" />
    <script>
      // Ultra-early fallbacks so buttons always work
      window.__directSidebarToggle = function(event) {
        if (event) event.preventDefault();
        const sidebar = document.querySelector('.app-sidebar');
        if (!sidebar) return false;
        const isMobile = window.innerWidth < 768;
        if (isMobile) {
          sidebar.classList.toggle('mobile-open');
          const expanded = sidebar.classList.contains('mobile-open').toString();
          document.querySelectorAll('#sidebar-toggle, [data-sidebar-toggle]').forEach(b => b.setAttribute('aria-expanded', expanded));
        } else {
          const collapsed = sidebar.classList.toggle('collapsed');
          try { localStorage.setItem('sidebarCollapsed', collapsed.toString()); } catch (e) {}
          document.querySelectorAll('#sidebar-toggle, [data-sidebar-toggle]').forEach(b => b.setAttribute('aria-expanded', (!collapsed).toString()));
        }
        return false;
      };

      // Track if device is low performance
      window.__isLowPerformance = false;
      
      window.__setBookingView = function(mode, event) {
        if (event) event.preventDefault();
        const roomsGrid = document.getElementById('roomsGrid');
        const roomsTable = document.getElementById('roomsTable');
        if (!roomsGrid) return false;
        const normalized = mode === 'list' ? 'list' : 'grid';
        
        // If low performance device tries to use grid, show warning
        if (normalized === 'grid' && window.__isLowPerformance) {
          showPerformanceWarning();
          return false;
        }
        
        if (normalized === 'list') {
          roomsGrid.classList.add('list-view');
          roomsGrid.style.display = 'flex';
          if (roomsTable) roomsTable.style.display = 'none';
          // Show list-view-extra elements
          document.querySelectorAll('.list-view-extra').forEach(el => el.style.display = 'flex');
        } else {
          roomsGrid.classList.remove('list-view');
          roomsGrid.style.display = 'grid';
          if (roomsTable) roomsTable.style.display = 'none';
          // Hide list-view-extra elements
          document.querySelectorAll('.list-view-extra').forEach(el => el.style.display = 'none');
        }
        
        document.querySelectorAll('.toggle-view-btn').forEach(b => b.classList.toggle('active', b.dataset.view === normalized));
        try { localStorage.setItem('bookingViewMode', normalized); } catch (e) {}
        return false;
      };
      
      // Warning when low performance user tries to switch to grid
      function showPerformanceWarning() {
        const overlay = document.createElement('div');
        overlay.id = 'fps-alert-overlay';
        overlay.innerHTML = `
          <div class="fps-alert-backdrop"></div>
          <div class="fps-alert-container">
            <div class="fps-alert-icon warning">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" fill="rgba(239, 68, 68, 0.2)" stroke="#ef4444"/>
              </svg>
            </div>
            <h2 class="fps-alert-title">ไม่แนะนำ</h2>
            <p class="fps-alert-message">
              อุปกรณ์ของคุณมีประสิทธิภาพต่ำ<br>
              โหมด Grid อาจทำให้เครื่องช้าลง<br>
              <strong>แนะนำให้ใช้โหมด List</strong>
            </p>
            <div class="fps-alert-buttons">
              <button class="fps-alert-btn secondary" onclick="forceGridMode()">
                ใช้ Grid ต่อ
              </button>
              <button class="fps-alert-btn primary" onclick="closePerformanceWarning()">
                ใช้ List (แนะนำ)
              </button>
            </div>
          </div>
        `;
        
        document.body.appendChild(overlay);
      }
      
      window.forceGridMode = function() {
        const overlay = document.getElementById('fps-alert-overlay');
        if (overlay) {
          overlay.classList.add('closing');
          setTimeout(() => {
            overlay.remove();
            // Force grid mode
            const roomsGrid = document.getElementById('roomsGrid');
            if (roomsGrid) {
              roomsGrid.classList.remove('list-view');
              roomsGrid.style.display = 'grid';
              document.querySelectorAll('.list-view-extra').forEach(el => el.style.display = 'none');
              document.querySelectorAll('.toggle-view-btn').forEach(b => b.classList.toggle('active', b.dataset.view === 'grid'));
            }
          }, 300);
        }
      };
      
      window.closePerformanceWarning = function() {
        const overlay = document.getElementById('fps-alert-overlay');
        if (overlay) {
          overlay.classList.add('closing');
          setTimeout(() => {
            overlay.remove();
          }, 300);
        }
      };

      window.__openBookingModal = function(btn, event) {
        if (event) event.preventDefault();
        if (!btn) return false;
        const idEl = document.getElementById('modal_room_id');
        const numEl = document.getElementById('modal_room_number');
        const typeEl = document.getElementById('modal_room_type');
        const priceEl = document.getElementById('modal_room_price');
        if (idEl) idEl.value = btn.dataset.roomId;
        if (numEl) numEl.value = 'ห้อง ' + btn.dataset.roomNumber;
        if (typeEl) typeEl.value = btn.dataset.roomType;
        if (priceEl) priceEl.value = '฿' + btn.dataset.roomPrice;
        const bookingModal = document.getElementById('bookingModal');
        if (bookingModal) {
          bookingModal.classList.add('active');
          document.body.classList.add('modal-open');
        }
        return false;
      };

      // Define global functions early (before DOM elements try to call them)
      window.toggleAvailableRooms = function() {
        const section = document.getElementById('availableRoomsSection');
        const icon = document.getElementById('toggleRoomsIcon');
        const text = document.getElementById('toggleRoomsText');
        const isHidden = section.style.display === 'none';
        
        if (isHidden) {
          section.style.display = '';
          icon.textContent = '▼';
          text.textContent = 'ซ่อนห้องพักที่ว่าง';
          localStorage.setItem('availableRoomsVisible', 'true');
        } else {
          section.style.display = 'none';
          icon.textContent = '▶';
          text.textContent = 'แสดงห้องพักที่ว่าง';
          localStorage.setItem('availableRoomsVisible', 'false');
        }
      };

      window.loadMoreRooms = function() {
        const hiddenRooms = document.querySelectorAll('.room-card.hidden-room');
        const totalRooms = document.querySelectorAll('.room-card').length;
        let showCount = 0;
        
        hiddenRooms.forEach((room, index) => {
          if (showCount < 5) {
            room.classList.remove('hidden-room');
            showCount++;
          }
        });
        
        try {
          localStorage.setItem('bookingVisibleRooms', String(Math.min(5 + showCount, totalRooms)));
        } catch (e) {}
        
        const remaining = totalRooms - Math.min(5 + showCount, totalRooms);
        const remainingCountEl = document.getElementById('remainingCount');
        const loadMoreBtn = document.getElementById('loadMoreBtn');
        
        if (remaining > 0 && remainingCountEl) {
          remainingCountEl.textContent = remaining;
        } else if (loadMoreBtn) {
          loadMoreBtn.classList.add('hidden');
        }
      };

      window.closeBookingModal = function() {
        const modal = document.getElementById('bookingModal');
        const form = document.getElementById('bookingForm');
        
        if (modal) modal.classList.remove('active');
        document.body.classList.remove('modal-open');
        if (form) form.reset();
      };

      // ===== FPS CHECKER - Auto switch to List view if low performance =====
      (function() {
        let frameCount = 0;
        let lastTime = performance.now();
        let fpsValues = [];
        let checkDuration = 2000; // Check for 2 seconds
        let hasChecked = false;
        
        function checkFPS(currentTime) {
          frameCount++;
          
          if (currentTime - lastTime >= 1000) {
            const fps = frameCount;
            fpsValues.push(fps);
            frameCount = 0;
            lastTime = currentTime;
          }
          
          if (currentTime < checkDuration && !hasChecked) {
            requestAnimationFrame(checkFPS);
          } else if (!hasChecked) {
            hasChecked = true;
            const avgFPS = fpsValues.length > 0 
              ? fpsValues.reduce((a, b) => a + b, 0) / fpsValues.length 
              : 60;
            
            if (avgFPS < 10) {
              window.__isLowPerformance = true;
              showPerformanceAlert(Math.round(avgFPS));
            }
          }
        }
        
        function showPerformanceAlert(fps) {
          // Create Apple-style alert overlay
          const overlay = document.createElement('div');
          overlay.id = 'fps-alert-overlay';
          overlay.innerHTML = `
            <div class="fps-alert-backdrop"></div>
            <div class="fps-alert-container">
              <div class="fps-alert-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                  <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" fill="rgba(251, 191, 36, 0.2)" stroke="#fbbf24"/>
                </svg>
              </div>
              <h2 class="fps-alert-title">ประสิทธิภาพต่ำ</h2>
              <p class="fps-alert-message">
                ตรวจพบว่าอุปกรณ์ของคุณทำงานที่ <strong>${fps} FPS</strong><br>
                เราจะเปลี่ยนเป็นโหมด List เพื่อประสบการณ์ที่ดีขึ้น
              </p>
              <div class="fps-alert-progress">
                <div class="fps-alert-progress-bar"></div>
              </div>
              <button class="fps-alert-btn" onclick="closeFPSAlert()">
                <span>เข้าใจแล้ว</span>
              </button>
            </div>
          `;
          
          document.body.appendChild(overlay);
          
          // Auto close and switch to list after 3 seconds
          setTimeout(() => {
            if (document.getElementById('fps-alert-overlay')) {
              closeFPSAlert();
            }
          }, 4000);
        }
        
        window.closeFPSAlert = function() {
          const overlay = document.getElementById('fps-alert-overlay');
          if (overlay) {
            overlay.classList.add('closing');
            setTimeout(() => {
              overlay.remove();
              // Switch to list view
              if (window.__setBookingView) {
                window.__setBookingView('list');
              }
            }, 300);
          }
        };
        
        // Start FPS check after page load
        if (document.readyState === 'complete') {
          requestAnimationFrame(checkFPS);
        } else {
          window.addEventListener('load', () => {
            setTimeout(() => requestAnimationFrame(checkFPS), 500);
          });
        }
      })();
    </script>
    <style>
      /* ===== FPS ALERT - Apple Style ===== */
      #fps-alert-overlay {
        position: fixed;
        inset: 0;
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: fpsAlertFadeIn 0.3s ease-out;
      }
      
      #fps-alert-overlay.closing {
        animation: fpsAlertFadeOut 0.3s ease-out forwards;
      }
      
      .fps-alert-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
      }
      
      .fps-alert-container {
        position: relative;
        background: linear-gradient(145deg, rgba(30, 41, 59, 0.98), rgba(15, 23, 42, 0.98));
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 2rem;
        max-width: 340px;
        width: 90%;
        text-align: center;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5),
                    0 0 0 1px rgba(255, 255, 255, 0.05) inset;
        animation: fpsAlertSlideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
      }
      
      .fps-alert-icon {
        width: 60px;
        height: 60px;
        margin: 0 auto 1rem;
        background: rgba(251, 191, 36, 0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: fpsIconPulse 2s ease-in-out infinite;
      }
      
      .fps-alert-icon svg {
        width: 32px;
        height: 32px;
      }
      
      .fps-alert-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #f5f8ff;
        margin: 0 0 0.5rem;
      }
      
      .fps-alert-message {
        font-size: 0.9rem;
        color: #94a3b8;
        margin: 0 0 1.25rem;
        line-height: 1.5;
      }
      
      .fps-alert-message strong {
        color: #fbbf24;
        font-weight: 600;
      }
      
      .fps-alert-progress {
        height: 4px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 2px;
        overflow: hidden;
        margin-bottom: 1.25rem;
      }
      
      .fps-alert-progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #3b82f6, #8b5cf6);
        border-radius: 2px;
        animation: fpsProgressBar 4s linear forwards;
      }
      
      .fps-alert-btn {
        width: 100%;
        padding: 0.875rem;
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        border: none;
        border-radius: 12px;
        color: white;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
      }
      
      .fps-alert-btn:hover {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        transform: scale(1.02);
      }
      
      .fps-alert-btn:active {
        transform: scale(0.98);
      }
      
      @keyframes fpsAlertFadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
      }
      
      @keyframes fpsAlertFadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
      }
      
      @keyframes fpsAlertSlideUp {
        from { 
          opacity: 0;
          transform: translateY(20px) scale(0.95);
        }
        to { 
          opacity: 1;
          transform: translateY(0) scale(1);
        }
      }
      
      @keyframes fpsIconPulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
      }
      
      @keyframes fpsProgressBar {
        from { width: 0%; }
        to { width: 100%; }
      }
      
      /* Light mode FPS alert */
      body.live-light .fps-alert-container {
        background: linear-gradient(145deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.98));
        border-color: rgba(0, 0, 0, 0.1);
      }
      
      body.live-light .fps-alert-title {
        color: #111827;
      }
      
      body.live-light .fps-alert-message {
        color: #6b7280;
      }
      
      /* ===== THEME VARIABLES ===== */
      :root {
        --card-bg: linear-gradient(135deg, rgba(20,30,48,0.95), rgba(8,14,28,0.95));
        --card-border: rgba(255,255,255,0.08);
        --card-shadow: 0 15px 35px rgba(3,7,18,0.4);
        --text-primary: #f5f8ff;
        --text-secondary: rgba(255,255,255,0.7);
        --text-muted: rgba(255,255,255,0.5);
        --accent-blue: #60a5fa;
        --accent-green: #22c55e;
        --accent-red: #ef4444;
        --accent-purple: #a78bfa;
        --stat-bg: linear-gradient(135deg, rgba(18,24,40,0.85), rgba(7,13,26,0.95));
      }
      
      /* Light Theme Variables */
      html.light-theme {
        --card-bg: linear-gradient(135deg, #ffffff, #f8fafc);
        --card-border: rgba(0,0,0,0.1);
        --card-shadow: 0 4px 20px rgba(0,0,0,0.08);
        --text-primary: #111827;
        --text-secondary: #4b5563;
        --text-muted: #9ca3af;
        --accent-blue: #3b82f6;
        --stat-bg: linear-gradient(135deg, #ffffff, #f1f5f9);
      }
      
      body.live-light {
        --card-bg: linear-gradient(135deg, #ffffff, #f8fafc);
        --card-border: rgba(0,0,0,0.1);
        --card-shadow: 0 4px 20px rgba(0,0,0,0.08);
        --text-primary: #111827;
        --text-secondary: #4b5563;
        --text-muted: #9ca3af;
        --accent-blue: #3b82f6;
        --stat-bg: linear-gradient(135deg, #ffffff, #f1f5f9);
      }
      
      /* Purple/Violet Theme */
      html.theme-purple {
        --card-bg: linear-gradient(135deg, rgba(49,46,129,0.95), rgba(30,27,75,0.95));
        --card-border: rgba(167,139,250,0.2);
        --card-shadow: 0 15px 35px rgba(30,27,75,0.5);
        --text-primary: #f5f3ff;
        --text-secondary: rgba(221,214,254,0.8);
        --text-muted: rgba(196,181,253,0.6);
        --accent-blue: #a78bfa;
        --accent-purple: #c4b5fd;
        --stat-bg: linear-gradient(135deg, rgba(49,46,129,0.9), rgba(30,27,75,0.95));
      }
      
      /* Cyan/Teal Theme */
      html.theme-cyan {
        --card-bg: linear-gradient(135deg, rgba(6,78,59,0.95), rgba(4,47,46,0.95));
        --card-border: rgba(45,212,191,0.2);
        --card-shadow: 0 15px 35px rgba(4,47,46,0.5);
        --text-primary: #f0fdfa;
        --text-secondary: rgba(153,246,228,0.8);
        --text-muted: rgba(94,234,212,0.6);
        --accent-blue: #2dd4bf;
        --accent-green: #34d399;
        --stat-bg: linear-gradient(135deg, rgba(6,78,59,0.9), rgba(4,47,46,0.95));
      }
      
      /* Warm/Orange Theme */
      html.theme-warm {
        --card-bg: linear-gradient(135deg, rgba(124,45,18,0.95), rgba(67,20,7,0.95));
        --card-border: rgba(251,146,60,0.2);
        --card-shadow: 0 15px 35px rgba(67,20,7,0.5);
        --text-primary: #fff7ed;
        --text-secondary: rgba(254,215,170,0.8);
        --text-muted: rgba(253,186,116,0.6);
        --accent-blue: #fb923c;
        --accent-green: #fbbf24;
        --accent-red: #f87171;
        --stat-bg: linear-gradient(135deg, rgba(124,45,18,0.9), rgba(67,20,7,0.95));
      }
      
      /* Rose/Pink Theme */
      html.theme-rose {
        --card-bg: linear-gradient(135deg, rgba(136,19,55,0.95), rgba(76,5,25,0.95));
        --card-border: rgba(251,113,133,0.2);
        --card-shadow: 0 15px 35px rgba(76,5,25,0.5);
        --text-primary: #fff1f2;
        --text-secondary: rgba(254,205,211,0.8);
        --text-muted: rgba(253,164,175,0.6);
        --accent-blue: #fb7185;
        --accent-purple: #f472b6;
        --stat-bg: linear-gradient(135deg, rgba(136,19,55,0.9), rgba(76,5,25,0.95));
      }
      
      /* ===== ANIMATIONS ===== */
      @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
      }
      
      @keyframes fadeInScale {
        from { opacity: 0; transform: scale(0.95); }
        to { opacity: 1; transform: scale(1); }
      }
      
      @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
      }
      
      @keyframes shimmer {
        0% { background-position: -200% 0; }
        100% { background-position: 200% 0; }
      }
      
      @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-10px); }
      }
      
      @keyframes glow {
        0%, 100% { box-shadow: 0 0 5px var(--accent-blue), 0 0 10px var(--accent-blue); }
        50% { box-shadow: 0 0 20px var(--accent-blue), 0 0 30px var(--accent-blue); }
      }
      
      @keyframes slideInRight {
        from { opacity: 0; transform: translateX(30px); }
        to { opacity: 1; transform: translateX(0); }
      }
      
      @keyframes bounceIn {
        0% { transform: scale(0.3); opacity: 0; }
        50% { transform: scale(1.05); }
        70% { transform: scale(0.9); }
        100% { transform: scale(1); opacity: 1; }
      }
      
      @keyframes rotateIn {
        from { transform: rotate(-200deg) scale(0); opacity: 0; }
        to { transform: rotate(0) scale(1); opacity: 1; }
      }
      
      @keyframes countUp {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
      }
      
      /* SVG Icon Animations */
      @keyframes svgDraw {
        to { stroke-dashoffset: 0; }
      }
      
      @keyframes svgPulse {
        0%, 100% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.1); opacity: 0.8; }
      }
      
      @keyframes iconBounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-5px); }
      }
      
      /* ===== ADVANCED CARD ANIMATIONS ===== */
      @keyframes cardFloat {
        0%, 100% { transform: translateY(0) rotateX(0deg); }
        50% { transform: translateY(-8px) rotateX(2deg); }
      }
      
      @keyframes glowPulse {
        0%, 100% { 
          box-shadow: 0 0 20px rgba(99, 102, 241, 0.3),
                      0 0 40px rgba(99, 102, 241, 0.1),
                      inset 0 0 20px rgba(99, 102, 241, 0.05);
        }
        50% { 
          box-shadow: 0 0 30px rgba(99, 102, 241, 0.5),
                      0 0 60px rgba(99, 102, 241, 0.2),
                      inset 0 0 30px rgba(99, 102, 241, 0.1);
        }
      }
      
      @keyframes borderGlow {
        0%, 100% { border-color: rgba(99, 102, 241, 0.3); }
        50% { border-color: rgba(99, 102, 241, 0.8); }
      }
      
      @keyframes shimmerSlide {
        0% { transform: translateX(-100%) skewX(-15deg); }
        100% { transform: translateX(200%) skewX(-15deg); }
      }
      
      @keyframes holographicShift {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
      }
      
      @keyframes tiltBounce {
        0%, 100% { transform: perspective(1000px) rotateX(0deg) rotateY(0deg); }
        25% { transform: perspective(1000px) rotateX(2deg) rotateY(-2deg); }
        75% { transform: perspective(1000px) rotateX(-2deg) rotateY(2deg); }
      }
      
      /* ===== ORGANIC LIVING ANIMATIONS ===== */
      @keyframes breathe {
        0%, 100% { 
          transform: scale(1) translateY(0);
          filter: brightness(1);
        }
        50% { 
          transform: scale(1.008) translateY(-3px);
          filter: brightness(1.05);
        }
      }
      
      @keyframes floatOrbit {
        0% { transform: translateY(0) translateX(0) rotate(0deg); }
        25% { transform: translateY(-6px) translateX(3px) rotate(0.5deg); }
        50% { transform: translateY(-2px) translateX(6px) rotate(0deg); }
        75% { transform: translateY(-8px) translateX(3px) rotate(-0.5deg); }
        100% { transform: translateY(0) translateX(0) rotate(0deg); }
      }
      
      @keyframes auroraGlow {
        0%, 100% { 
          background-position: 0% 50%;
          filter: hue-rotate(0deg) blur(15px);
        }
        25% { 
          background-position: 50% 100%;
          filter: hue-rotate(30deg) blur(18px);
        }
        50% { 
          background-position: 100% 50%;
          filter: hue-rotate(60deg) blur(20px);
        }
        75% { 
          background-position: 50% 0%;
          filter: hue-rotate(30deg) blur(18px);
        }
      }
      
      @keyframes pulseRing {
        0% { 
          transform: scale(1);
          opacity: 0.8;
        }
        50% { 
          transform: scale(1.05);
          opacity: 0.4;
        }
        100% { 
          transform: scale(1);
          opacity: 0.8;
        }
      }
      
      @keyframes particleFloat {
        0%, 100% { 
          transform: translateY(0) scale(1);
          opacity: 0.6;
        }
        50% { 
          transform: translateY(-20px) scale(1.2);
          opacity: 1;
        }
      }
      
      @keyframes glitchFlicker {
        0%, 100% { opacity: 1; transform: translateX(0); }
        92% { opacity: 1; transform: translateX(0); }
        93% { opacity: 0.8; transform: translateX(-2px); }
        94% { opacity: 1; transform: translateX(2px); }
        95% { opacity: 0.9; transform: translateX(0); }
      }
      
      @keyframes neonPulse {
        0%, 100% {
          text-shadow: 0 0 5px currentColor,
                       0 0 10px currentColor,
                       0 0 20px currentColor;
        }
        50% {
          text-shadow: 0 0 10px currentColor,
                       0 0 20px currentColor,
                       0 0 40px currentColor,
                       0 0 80px currentColor;
        }
      }
      
      /* ===== STAT CARDS WITH ANIMATIONS ===== */
      .booking-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
      }
      .booking-stat-card {
        background: var(--stat-bg);
        border-radius: 16px;
        padding: 1.25rem;
        border: 1px solid var(--card-border);
        color: var(--text-primary);
        box-shadow: var(--card-shadow);
        position: relative;
        overflow: hidden;
        animation: fadeInUp 0.6s ease-out backwards;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      }
      .booking-stat-card:nth-child(1) { animation-delay: 0.1s; }
      .booking-stat-card:nth-child(2) { animation-delay: 0.2s; }
      .booking-stat-card:nth-child(3) { animation-delay: 0.3s; }
      
      .booking-stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
        transition: left 0.5s;
      }
      .booking-stat-card:hover::before {
        left: 100%;
      }
      .booking-stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0,0,0,0.3);
      }
      
      .booking-stat-card .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 0.75rem;
        animation: iconBounce 2s ease-in-out infinite;
      }
      .booking-stat-card .stat-icon svg {
        width: 24px;
        height: 24px;
        stroke: currentColor;
        stroke-width: 2;
        fill: none;
      }
      .booking-stat-card .stat-icon.blue { background: rgba(96, 165, 250, 0.2); color: #60a5fa; }
      .booking-stat-card .stat-icon.green { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
      .booking-stat-card .stat-icon.red { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
      
      .booking-stat-card h3 {
        margin: 0;
        font-size: 0.95rem;
        color: var(--text-secondary);
      }
      .booking-stat-card .stat-value {
        font-size: 2.5rem;
        font-weight: 700;
        margin-top: 0.5rem;
        animation: countUp 0.8s ease-out backwards;
        background: linear-gradient(135deg, var(--text-primary), var(--accent-blue));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
      }
      .booking-stat-card .stat-chip {
        margin-top: 1rem;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.85rem;
        padding: 0.2rem 0.75rem;
        border-radius: 999px;
        background: rgba(255,255,255,0.1);
      }
      .booking-section { margin-bottom: 2rem; }
      .rooms-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 2.5rem;
        margin-top: 1rem;
      }
      /* Desktop: 5 cards per row for portrait 1:2 cards */
      @media (min-width: 1400px) {
        .rooms-grid {
          grid-template-columns: repeat(5, minmax(0, 1fr));
          gap: 2.5rem;
        }
      }
      @media (min-width: 1200px) and (max-width: 1399px) {
        .rooms-grid {
          grid-template-columns: repeat(4, minmax(0, 1fr));
          gap: 2rem;
        }
      }
      /* Tablet: 3 cards */
      @media (max-width: 1199px) and (min-width: 768px) {
        .rooms-grid {
          grid-template-columns: repeat(3, minmax(0, 1fr));
          gap: 1.5rem;
        }
      }
      /* Mobile: 2 cards */
      @media (max-width: 767px) and (min-width: 481px) {
        .rooms-grid {
          grid-template-columns: repeat(2, minmax(0, 1fr));
          gap: 1.25rem;
        }
      }
      /* Small Mobile: single column */
      @media (max-width: 480px) {
        .rooms-grid {
          grid-template-columns: 1fr;
          max-width: 240px;
          margin-left: auto;
          margin-right: auto;
          gap: 1.5rem;
        }
      }
      .rooms-grid.list-view { display: flex; flex-direction: column; gap: 1rem; }
      .view-toggle { display: inline-flex; gap: 0.5rem; margin-top: 1rem; }
      .view-toggle button {
        padding: 0.5rem 0.9rem;
        border: 1px solid rgba(255,255,255,0.15);
        border-radius: 10px;
        background: rgba(15,23,42,0.85);
        color: #f5f8ff;
        cursor: pointer;
        transition: all 0.2s ease;
      }
      .view-toggle button.active {
        background: linear-gradient(135deg, #60a5fa, #2563eb);
        border-color: rgba(96,165,250,0.8);
        box-shadow: 0 10px 20px rgba(37,99,235,0.35);
      }
      
      /* ===== ENHANCED ROOM CARD WITH FLIP - WEB3 STYLE ===== */
      .room-card {
        position: relative;
        border-radius: 24px;
        background: transparent;
        box-shadow: var(--card-shadow);
        color: var(--text-primary);
        perspective: 1200px;
        /* Web3 Portrait Card Style - Figma size 1137x1606 = ratio ~7:10 (0.708:1) */
        aspect-ratio: 1137 / 1606;
        min-height: 280px;
        transition: all 0.5s cubic-bezier(0.23, 1, 0.32, 1);
        will-change: transform, filter;
        /* Living breathing animation */
        animation: fadeInUp 0.6s ease-out backwards,
                   breathe 4s ease-in-out infinite,
                   floatOrbit 12s ease-in-out infinite;
      }
      
      /* Each card has unique animation timing for organic feel */
      .room-card:nth-child(odd) {
        animation: fadeInUp 0.6s ease-out backwards,
                   breathe 4.5s ease-in-out infinite,
                   floatOrbit 14s ease-in-out infinite reverse;
      }
      
      .room-card:nth-child(3n) {
        animation: fadeInUp 0.6s ease-out backwards,
                   breathe 5s ease-in-out infinite 0.5s,
                   floatOrbit 16s ease-in-out infinite;
      }
      
      .room-card:nth-child(4n) {
        animation: fadeInUp 0.6s ease-out backwards,
                   breathe 3.5s ease-in-out infinite 1s,
                   floatOrbit 10s ease-in-out infinite reverse;
      }
      
      /* Staggered entrance animations */
      .room-card:nth-child(1) { animation-delay: 0.1s, 0s, 0s; }
      .room-card:nth-child(2) { animation-delay: 0.15s, 0.3s, 0.5s; }
      .room-card:nth-child(3) { animation-delay: 0.2s, 0.6s, 1s; }
      .room-card:nth-child(4) { animation-delay: 0.25s, 0.9s, 1.5s; }
      .room-card:nth-child(5) { animation-delay: 0.3s, 1.2s, 2s; }
      .room-card:nth-child(6) { animation-delay: 0.35s, 0.2s, 0.7s; }
      .room-card:nth-child(7) { animation-delay: 0.4s, 0.5s, 1.2s; }
      .room-card:nth-child(8) { animation-delay: 0.45s, 0.8s, 1.7s; }
      .room-card:nth-child(9) { animation-delay: 0.5s, 1.1s, 2.2s; }
      .room-card:nth-child(10) { animation-delay: 0.55s, 0.4s, 0.9s; }
      
      /* Card Glow Effect - Aurora Borealis Style */
      .room-card::before {
        content: '';
        position: absolute;
        inset: -4px;
        border-radius: 28px;
        background: linear-gradient(135deg, 
          #667eea 0%, #764ba2 15%, 
          #f093fb 30%, #f5576c 45%,
          #4facfe 60%, #00f2fe 75%,
          #43e97b 90%, #667eea 100%);
        background-size: 300% 300%;
        opacity: 0.3;
        z-index: -1;
        filter: blur(15px);
        animation: auroraGlow 8s ease infinite;
        transition: opacity 0.4s ease, filter 0.4s ease;
      }
      
      /* Shimmer overlay */
      .room-card::after {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: 24px;
        background: linear-gradient(105deg, 
          transparent 20%, 
          rgba(255,255,255,0.03) 35%,
          rgba(255,255,255,0.15) 50%,
          rgba(255,255,255,0.03) 65%,
          transparent 80%);
        background-size: 300% 100%;
        opacity: 0;
        pointer-events: none;
        z-index: 5;
        transition: opacity 0.3s ease;
        animation: shimmerSlide 3s ease-in-out infinite;
        animation-play-state: paused;
      }
      
      .room-card:hover::before {
        opacity: 0.6;
        filter: blur(18px);
      }
      
      .room-card:hover::after {
        opacity: 0.5;
      }
      
      /* Subtle hover without flip */
      .room-card:hover:not(.flipped) {
        z-index: 10;
      }
      
      /* When flipped, more dramatic effect */
      .room-card.flipped {
        transform: translateY(-12px) scale(1.03);
        animation: none !important;
        filter: brightness(1.1);
        z-index: 10;
      }
      
      .room-card.flipped::before {
        opacity: 1;
        filter: blur(20px);
      }
      
      .room-card.flipped::after {
        opacity: 1;
        animation-play-state: running;
      }
      
      /* Particle effects on cards */
      .room-card .card-particles {
        position: absolute;
        inset: 0;
        pointer-events: none;
        overflow: hidden;
        border-radius: 24px;
        z-index: 1;
      }
      
      .room-card .card-particles::before,
      .room-card .card-particles::after {
        content: '';
        position: absolute;
        width: 6px;
        height: 6px;
        background: rgba(255,255,255,0.6);
        border-radius: 50%;
        animation: particleFloat 3s ease-in-out infinite;
      }
      
      .room-card .card-particles::before {
        top: 20%;
        left: 15%;
        animation-delay: 0s;
      }
      
      .room-card .card-particles::after {
        top: 60%;
        right: 20%;
        animation-delay: 1.5s;
      }
      
      .room-card.removing {
        opacity: 0;
        transform: scale(0.7) rotateY(90deg);
        pointer-events: none;
      }
      .rooms-grid.list-view .room-card { 
        min-height: auto; 
        aspect-ratio: unset;
        animation: none !important;
        transform: none !important;
      }
      .rooms-grid.list-view .room-card::before,
      .rooms-grid.list-view .room-card::after {
        display: none !important;
      }
      .rooms-grid.list-view .room-card:hover {
        transform: none !important;
        filter: none !important;
      }
      .rooms-grid.list-view .card-particles {
        display: none !important;
      }
      .room-card-inner {
        position: relative;
        width: 100%;
        height: 100%;
        border-radius: 24px;
        /* Fast flip back when mouse leaves (no delay) */
        transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1),
                    box-shadow 0.3s ease;
        transform-style: preserve-3d;
      }
      .rooms-grid.list-view .room-card-inner { 
        height: auto; 
        transform: none !important;
        transition: none !important;
      }
      .rooms-grid.list-view .room-card:hover .room-card-inner {
        transform: none !important;
        box-shadow: none !important;
      }
      /* Flip only when .flipped class is added by JavaScript */
      .room-card.flipped .room-card-inner {
        transform: rotateY(180deg);
        box-shadow: 0 25px 60px rgba(99, 102, 241, 0.4),
                    0 10px 30px rgba(0,0,0,0.3);
        transition: transform 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275),
                    box-shadow 0.5s ease;
      }
      /* Subtle hover effect without flip */
      .room-card:hover:not(.flipped) {
        transform: translateY(-8px) scale(1.02);
      }
      /* Prevent flip when modal is open */
      body.modal-open .room-card.flipped .room-card-inner {
        transform: none !important;
      }
      .room-card-face {
        position: absolute;
        inset: 0;
        border-radius: 24px;
        padding: 1.25rem;
        background: #1e293b;
        backface-visibility: hidden;
        -webkit-backface-visibility: hidden;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        gap: 0.5rem;
        border: 1px solid rgba(255,255,255,0.1);
        transition: visibility 0s 0.5s;
        box-shadow: inset 0 0 30px rgba(0,0,0,0.3);
      }
      
      /* Glowing border effect on front card */
      .room-card-face.front {
        pointer-events: auto;
        border: 1px solid rgba(99, 102, 241, 0.2);
        animation: borderGlow 4s ease-in-out infinite;
        z-index: 2;
        backface-visibility: hidden;
        -webkit-backface-visibility: hidden;
      }
      
      /* Only disable pointer on front when flipped */
      .room-card.flipped .room-card-face.front {
        pointer-events: none;
        border-color: rgba(99, 102, 241, 0.6);
      }
      
      /* Shimmer effect removed - conflicts with image gradient overlay */

      /* Light mode overrides */
      body.live-light .booking-stat-card {
        background: #ffffff !important;
        color: #111827 !important;
        border: 1px solid #e5e7eb !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08) !important;
      }
      body.live-light .booking-stat-card h3 { color: #6b7280 !important; }
      body.live-light .booking-stat-card .stat-value { 
        color: #111827 !important; 
        background: linear-gradient(135deg, #111827, #3b82f6) !important;
        -webkit-background-clip: text !important;
        -webkit-text-fill-color: transparent !important;
      }
      body.live-light .booking-stat-card .stat-chip { background: #f3f4f6 !important; color: #374151 !important; }

      body.live-light .room-card { 
        box-shadow: 0 2px 10px rgba(0,0,0,0.06) !important; 
        color: #111827 !important; 
      }
      body.live-light .room-card::before {
        background: linear-gradient(135deg, #3b82f6, #8b5cf6, #22c55e) !important;
      }
      /* Light mode - ONLY back card gets white background */
      body.live-light .room-card-face.back {
        background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%) !important;
        border: 1px solid #e5e7eb !important;
        color: #111827 !important;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05) !important;
      }
      /* Light mode - Front card keeps image-based design */
      body.live-light .room-card-face.front {
        background: linear-gradient(135deg, #4f46e5 0%, #6366f1 50%, #818cf8 100%) !important;
      }
      /* Light mode - back card content colors */
      body.live-light .room-number-back { color: #111827 !important; }
      body.live-light .room-number-back svg { stroke: #2563eb !important; }
      body.live-light .availability-badge { background: #dcfce7 !important; color: #166534 !important; }
      body.live-light .back-header { border-bottom-color: #e5e7eb !important; }
      body.live-light .detail-item { background: #f3f4f6 !important; border-color: #e5e7eb !important; }
      body.live-light .detail-label { color: #6b7280 !important; }
      body.live-light .detail-value { color: #111827 !important; }
      body.live-light .feature-tag { background: #e0e7ff !important; color: #3730a3 !important; border-color: #c7d2fe !important; }
      body.live-light .room-number { color: #111827 !important; }
      body.live-light .room-info,
      body.live-light .room-info-item { color: #6b7280 !important; }
      body.live-light .room-price { color: #2563eb !important; }
      body.live-light .room-status { background: #22c55e !important; color: #ffffff !important; }
      body.live-light .room-image-container { background: #f3f4f6 !important; }
      body.live-light .book-btn { background: #2563eb !important; color: #ffffff !important; }
      body.live-light .book-btn:hover { background: #1d4ed8 !important; }
      body.live-light .view-toggle button { 
        background: #f3f4f6 !important; 
        color: #111827 !important; 
        border: 1px solid #d1d5db !important; 
      }
      body.live-light .view-toggle button.active { 
        background: linear-gradient(135deg, #2563eb, #1d4ed8) !important; 
        color: #ffffff !important;
        border-color: #2563eb !important;
      }
      
      /* ===== WEB3 STYLE CARD FRONT ===== */
      .room-card-face.front { 
        padding: 0 !important;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, #4f46e5 0%, #6366f1 50%, #818cf8 100%);
        overflow: hidden;
        border-radius: 24px;
      }
      
      /* Room image fills entire card */
      .room-card-face.front .room-image-container {
        position: absolute !important;
        inset: 0 !important;
        width: 100% !important;
        height: 100% !important;
        aspect-ratio: unset !important;
        z-index: 0;
        border-radius: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        display: block !important;
      }
      
      .room-card-face.front .room-image-container img {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
        display: block !important;
      }
      
      /* Gradient overlay for text readability */
      .room-card-face.front::before {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 50%;
        background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.5) 40%, transparent 100%);
        pointer-events: none;
        z-index: 1;
        border-radius: 0 0 24px 24px;
      }
      
      /* Info section at bottom left */
      .room-card-face.front .card-info-bottom {
        position: absolute;
        bottom: 1rem;
        left: 1rem;
        right: 1rem;
        z-index: 2;
        display: flex;
        flex-direction: column;
        gap: 0.1rem;
        color: white;
        text-align: left;
      }
      
      .room-card-face.front .card-info-bottom .room-number-web3 {
        font-size: 1.4rem;
        font-weight: 700;
        color: #ffffff;
        text-shadow: 0 2px 8px rgba(0,0,0,0.3);
      }
      
      .room-card-face.front .card-info-bottom .room-type-web3 {
        font-size: 0.9rem;
        color: rgba(255,255,255,0.9);
        text-shadow: 0 1px 4px rgba(0,0,0,0.3);
      }
      
      .room-card-face.front .card-info-bottom .room-price-web3 {
        font-size: 1.15rem;
        font-weight: 600;
        color: #ffffff;
        margin-top: 0.15rem;
        text-shadow: 0 2px 8px rgba(0,0,0,0.3);
      }
      
      /* Status badge top right - with pulse animation */
      .room-card-face.front .status-badge-web3 {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: linear-gradient(135deg, rgba(34, 197, 94, 0.9), rgba(16, 185, 129, 0.9));
        backdrop-filter: blur(10px);
        padding: 0.4rem 1rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        color: white;
        z-index: 3;
        text-shadow: 0 1px 3px rgba(0,0,0,0.2);
        animation: pulseRing 2s ease-in-out infinite;
        box-shadow: 0 0 15px rgba(34, 197, 94, 0.5),
                    0 0 30px rgba(34, 197, 94, 0.3);
        border: 1px solid rgba(255,255,255,0.3);
      }
      
      .room-card-face.front .status-badge-web3::before {
        content: '';
        position: absolute;
        inset: -2px;
        border-radius: 22px;
        background: linear-gradient(135deg, #22c55e, #10b981, #14b8a6);
        z-index: -1;
        opacity: 0.5;
        filter: blur(4px);
        animation: pulseRing 2s ease-in-out infinite 0.5s;
      }
      
      /* Placeholder styling when no image */
      .room-card-face.front .room-image-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        color: rgba(255,255,255,0.5);
      }
      
      .room-card-face.front .room-image-placeholder svg {
        width: 60px;
        height: 60px;
        opacity: 0.4;
      }
      
      .room-card-face.front .room-image-placeholder span {
        font-size: 0.8rem;
        opacity: 0.6;
      }
      
      /* List view - simple row layout */
      .rooms-grid.list-view .room-card-face { 
        position: relative; 
        inset: auto; 
        min-height: auto; 
        height: auto; 
        width: 100%; 
        box-sizing: border-box; 
        display: flex; 
        flex-direction: row; 
        gap: 1rem; 
        align-items: center; 
        padding: 1rem 1.5rem; 
        border-radius: 12px;
        background: rgba(30, 41, 59, 0.8) !important;
        border: 1px solid rgba(255,255,255,0.1) !important;
        animation: none !important;
        backdrop-filter: blur(10px);
      }
      .rooms-grid.list-view .room-card-face:hover {
        background: rgba(30, 41, 59, 0.95) !important;
        border-color: rgba(99, 102, 241, 0.3) !important;
      }
      .rooms-grid.list-view .room-card-face.front {
        position: relative;
        display: flex;
        padding: 1rem 1.5rem !important;
        background: rgba(30, 41, 59, 0.8) !important;
        border: 1px solid rgba(255,255,255,0.1) !important;
        pointer-events: auto !important;
      }
      .rooms-grid.list-view .room-card-face.front::before {
        display: none !important;
      }
      .rooms-grid.list-view .room-card-face.back {
        display: none !important;
      }
      /* Ensure list view cards are fully clickable */
      .rooms-grid.list-view .room-card {
        pointer-events: auto !important;
      }
      .rooms-grid.list-view .room-card * {
        pointer-events: auto;
      }
      .rooms-grid.list-view .room-price { font-size: 1rem; line-height: 1.2; margin: 0.3rem 0; }
      
      /* ===== BACK CARD FACE - Dark background with content ===== */
      .room-card-face.back {
        position: absolute;
        inset: 0;
        transform: rotateY(180deg);
        backface-visibility: hidden;
        -webkit-backface-visibility: hidden;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.4rem;
        padding: 1rem;
        background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%) !important;
        z-index: 1;
        visibility: visible;
        border: 1px solid rgba(255,255,255,0.15) !important;
        border-radius: 24px;
        pointer-events: auto;
      }
      
      /* Hide image on back card */
      .room-card-face.back .room-image-container {
        display: none !important;
      }
      
      /* ===== ENHANCED BACK CARD STYLES ===== */
      .back-card-content {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
        width: 100%;
        flex: 1;
        overflow: hidden;
        min-height: 0;
      }
      
      .back-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 0.4rem;
        border-bottom: 1px solid rgba(255,255,255,0.1);
      }
      
      .room-number-back {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--text-primary);
      }
      
      .room-icon-animated {
        width: 18px;
        height: 18px;
        stroke: var(--accent-blue);
        animation: iconBounce 2s ease-in-out infinite;
      }
      
      .availability-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.2rem 0.5rem;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        background: rgba(34, 197, 94, 0.15);
        color: #22c55e;
        animation: pulse 2s ease-in-out infinite;
      }
      
      .back-details {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
        flex: 1;
        overflow-y: auto;
      }
      
      .detail-item {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.35rem 0.5rem;
        background: rgba(255,255,255,0.03);
        border-radius: 10px;
        transition: all 0.3s ease;
      }
      
      .detail-item:hover {
        background: rgba(255,255,255,0.08);
        transform: translateX(3px);
      }
      
      .detail-item.highlight {
        background: rgba(96, 165, 250, 0.1);
        border: 1px solid rgba(96, 165, 250, 0.2);
      }
      
      .detail-icon {
        width: 26px;
        height: 26px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(96, 165, 250, 0.15);
        border-radius: 6px;
        flex-shrink: 0;
      }
      
      .detail-icon svg {
        width: 14px;
        height: 14px;
        stroke: var(--accent-blue);
      }
      
      .detail-text {
        display: flex;
        flex-direction: column;
        gap: 0;
      }
      
      .detail-label {
        font-size: 0.55rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }
      
      .detail-value {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-primary);
      }
      
      .detail-value.price {
        color: var(--accent-blue);
        font-size: 0.9rem;
      }
      
      .back-features {
        display: flex;
        flex-wrap: wrap;
        gap: 0.3rem;
        margin-top: 0.2rem;
      }
      
      .feature-tag {
        display: inline-flex;
        align-items: center;
        gap: 0.2rem;
        padding: 0.15rem 0.4rem;
        background: rgba(167, 139, 250, 0.15);
        border-radius: 6px;
        font-size: 0.55rem;
        font-weight: 500;
        color: #a78bfa;
      }
      
      .feature-tag svg {
        stroke: currentColor;
        width: 9px;
        height: 9px;
      }
      
      .back-actions {
        width: 100%;
        margin-top: auto;
        padding-top: 0.2rem;
        flex-shrink: 0;
        pointer-events: auto;
      }
      
      .book-btn-back {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        padding: 0.5rem 0.8rem;
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        border: none;
        border-radius: 12px;
        color: white;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        pointer-events: auto;
        position: relative;
        z-index: 100;
      }
      }
      
      .book-btn-back:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
      }
      
      .book-btn-back svg {
        stroke: white;
        width: 14px;
        height: 14px;
      }
      
      /* Light Theme Back Card */
      html.light-theme .back-header,
      body.live-light .back-header {
        border-bottom-color: rgba(0,0,0,0.1);
      }
      
      html.light-theme .detail-item,
      body.live-light .detail-item {
        background: rgba(0,0,0,0.02);
      }
      
      html.light-theme .detail-item:hover,
      body.live-light .detail-item:hover {
        background: rgba(0,0,0,0.05);
      }
      
      html.light-theme .detail-item.highlight,
      body.live-light .detail-item.highlight {
        background: rgba(59, 130, 246, 0.08);
        border-color: rgba(59, 130, 246, 0.15);
      }
      
      html.light-theme .detail-icon,
      body.live-light .detail-icon {
        background: rgba(59, 130, 246, 0.1);
      }
      
      html.light-theme .feature-tag,
      body.live-light .feature-tag {
        background: rgba(139, 92, 246, 0.1);
        color: #7c3aed;
      }
      
      .room-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.25rem;
      }
      .room-face-body { display: flex; flex-direction: column; gap: 0.4rem; width: 100%; flex: 1; }
      .rooms-grid.list-view .room-face-body { flex-direction: row; align-items: center; gap: 1.5rem; flex: 1; }
      .room-face-details { display: flex; flex-direction: column; gap: 0.25rem; flex: 0 0 auto; min-width: 0; }
      .rooms-grid.list-view .room-number { min-width: 80px; }
      .rooms-grid.list-view .room-face-details { width: auto; gap: 0.2rem; }
      .room-number { font-size: 1.35rem; font-weight: 700; color: #f5f8ff; }
      .room-status { padding: 0.2rem 0.5rem; border-radius: 8px; font-size: 0.7rem; background: #4caf50; color: white; }
      
      /* Table badges and styles */
      .price-badge { 
        color: #60a5fa; 
        background: rgba(96, 165, 250, 0.1); 
        padding: 0.25rem 0.75rem; 
        border-radius: 6px; 
        display: inline-block; 
        font-weight: 600; 
      }
      .status-badge { 
        padding: 0.35rem 0.75rem; 
        border-radius: 8px; 
        font-size: 0.85rem; 
        font-weight: 500; 
        display: inline-block; 
      }
      .status-available { 
        background: rgba(34, 197, 94, 0.15); 
        color: #22c55e; 
      }
      .btn-sm { 
        padding: 0.5rem 1rem; 
        font-size: 0.9rem; 
      }
      .btn-primary { 
        background: #007AFF; 
        color: white; 
        border: none; 
        border-radius: 6px; 
        cursor: pointer; 
        transition: all 0.2s; 
      }
      .btn-primary:hover { 
        background: #0A66DB; 
        transform: translateY(-2px); 
        box-shadow: 0 4px 8px rgba(0, 122, 255, 0.3); 
      }
      
      .room-info { margin: 0.2rem 0; color: rgba(255,255,255,0.75); font-size: 0.8rem; }
      .room-info-item { display: flex; justify-content: space-between; padding: 0.1rem 0; font-size: 0.8rem; }
      .room-price { font-size: 1.1rem; font-weight: bold; color: #60a5fa; margin: 0.4rem 0; }
      
      /* Base room-image-container - for list view only */
      .rooms-grid.list-view .room-image-container {
        aspect-ratio: 1 / 1;
        overflow: hidden;
        border-radius: 10px;
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.3), rgba(79, 70, 229, 0.4));
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0; 
        width: 60px; 
        max-width: 60px; 
        min-height: 60px; 
        height: 60px; 
        flex-shrink: 0; 
        position: static !important; 
        inset: auto !important;
      }
      .rooms-grid.list-view .room-image-container img { 
        width: 100%; 
        height: 100%; 
        object-fit: cover; 
      }
      .room-image-container img { width: 100%; height: 100%; object-fit: cover; }
      .room-image-placeholder { display: grid; place-items: center; color: rgba(255,255,255,0.6); gap: 0.25rem; text-align: center; width: 100%; height: 100%; }
      .room-image-placeholder svg { width: 48px; height: 48px; opacity: 0.7; }
      .room-image-placeholder span { font-size: 0.7rem; }
      .book-btn-front { display: none; }
      .rooms-grid.list-view .book-btn-front { display: inline-flex; width: 120px; justify-content: center; flex-shrink: 0; padding: 0.6rem 1rem; font-size: 0.9rem; margin-left: auto; }

      .rooms-grid.list-view .room-info-item { justify-content: flex-start; gap: 0.5rem; font-size: 0.85rem; }
      .rooms-grid.list-view .room-number { font-size: 1.1rem; }
      .rooms-grid.list-view .room-face-details { gap: 0.3rem; }
      .rooms-grid.list-view .list-book-btn { margin-left: auto; }
      
      /* List view - proper layout */
      .rooms-grid.list-view .status-badge-web3 {
        position: static !important;
        order: -1;
        margin-right: 0.5rem;
        animation: none !important;
        box-shadow: none !important;
        padding: 0.3rem 0.8rem;
        font-size: 0.7rem;
        background: linear-gradient(135deg, rgba(34, 197, 94, 0.9), rgba(16, 185, 129, 0.9)) !important;
        flex-shrink: 0;
      }
      .rooms-grid.list-view .status-badge-web3::before {
        display: none !important;
      }
      .rooms-grid.list-view .card-info-bottom {
        position: static !important;
        flex-direction: row !important;
        align-items: center !important;
        gap: 2rem !important;
        flex: 1;
      }
      .rooms-grid.list-view .room-number-web3 {
        font-size: 1.1rem;
        font-weight: 600;
        color: #f5f8ff !important;
        text-shadow: none !important;
        min-width: 100px;
      }
      .rooms-grid.list-view .room-type-web3 {
        font-size: 0.9rem;
        color: #94a3b8 !important;
        text-shadow: none !important;
        min-width: 100px;
        padding: 0.25rem 0.75rem;
        background: rgba(99, 102, 241, 0.15);
        border-radius: 6px;
        border: 1px solid rgba(99, 102, 241, 0.3);
      }
      .rooms-grid.list-view .room-price-web3 {
        font-size: 1.1rem;
        font-weight: 600;
        color: #60a5fa !important;
        text-shadow: none !important;
      }
      
      /* List view - extra info and book button */
      .rooms-grid.list-view .list-view-extra {
        display: flex !important;
        align-items: center;
        gap: 1.5rem;
        margin-left: auto;
        pointer-events: auto;
        position: relative;
        z-index: 50;
      }
      .rooms-grid.list-view .list-view-features {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
      }
      .rooms-grid.list-view .list-feature-tag {
        font-size: 0.7rem;
        padding: 0.2rem 0.5rem;
        background: rgba(255,255,255,0.1);
        border-radius: 4px;
        color: #cbd5e1;
      }
      .rooms-grid.list-view .list-book-btn {
        padding: 0.6rem 1.5rem;
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        border: none;
        border-radius: 8px;
        color: white;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        white-space: nowrap;
        display: flex;
        align-items: center;
        gap: 0.4rem;
        pointer-events: auto !important;
        position: relative;
        z-index: 100;
      }
      .rooms-grid.list-view .list-book-btn:hover {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
      }
      .rooms-grid.list-view .list-book-btn svg {
        width: 16px;
        height: 16px;
      }
      
      /* List view deposit info */
      .rooms-grid.list-view .list-deposit {
        font-size: 0.8rem;
        color: #94a3b8;
        display: flex;
        align-items: center;
        gap: 0.3rem;
      }
      .rooms-grid.list-view .list-deposit svg {
        width: 14px;
        height: 14px;
        color: #f59e0b;
      }
      
      /* Light mode list view */
      body.live-light .rooms-grid.list-view .room-card-face.front {
        background: rgba(255,255,255,0.95) !important;
        border: 1px solid #e5e7eb !important;
      }
      body.live-light .rooms-grid.list-view .room-card-face.front:hover {
        background: #ffffff !important;
        border-color: #c7d2fe !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      }
      body.live-light .rooms-grid.list-view .room-number-web3 {
        color: #111827 !important;
      }
      body.live-light .rooms-grid.list-view .room-type-web3 {
        color: #4f46e5 !important;
        background: rgba(99, 102, 241, 0.1);
      }
      body.live-light .rooms-grid.list-view .room-price-web3 {
        color: #2563eb !important;
      }
      body.live-light .rooms-grid.list-view .list-feature-tag {
        background: #f3f4f6;
        color: #374151;
      }
      body.live-light .rooms-grid.list-view .list-deposit {
        color: #6b7280;
      }
      
      .book-btn {
        width: 100%;
        padding: 0.75rem;
        background: #007AFF;
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 1rem;
        cursor: pointer;
        transition: background 0.3s;
      }
      .book-btn:hover { background: #0A66DB; }
      /* Prevent hover animation when modal is open */
      body.modal-open .room-card:hover .room-card-inner {
        transform: none !important;
      }
      .booking-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.85);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
      }
      .booking-modal.active { display: flex; }
      .booking-modal-content {
        background: radial-gradient(circle at top, #1c2541, #0b0c10 60%);
        border: 1px solid rgba(255,255,255,0.08);
        box-shadow: 0 25px 60px rgba(7, 11, 23, 0.65);
        padding: 2rem;
        border-radius: 16px;
        max-width: 520px;
        width: min(520px, 95vw);
        max-height: 90vh;
        overflow-y: auto;
        overflow-x: hidden;
        color: #f5f8ff;
        scrollbar-width: thin;
        scrollbar-color: rgba(255,255,255,0.2) transparent;
      }
      .booking-modal-content::-webkit-scrollbar {
        width: 8px;
      }
      .booking-modal-content::-webkit-scrollbar-track {
        background: transparent;
      }
      .booking-modal-content::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.2);
        border-radius: 4px;
      }
      .booking-modal-content::-webkit-scrollbar-thumb:hover {
        background: rgba(255,255,255,0.4);
      }
      .booking-modal-content h2 { margin-top: 0; color: #fff; letter-spacing: 0.02em; }
      .booking-form-group { margin-bottom: 1.5rem; }
      .booking-form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: rgba(255,255,255,0.85); }
      .booking-form-group input,
      .booking-form-group select {
        width: 100%;
        padding: 0.8rem 0.9rem;
        border: 1px solid rgba(255,255,255,0.15);
        border-radius: 10px;
        font-size: 1rem;
        background: rgba(12, 17, 29, 0.85);
        color: #f5f5f5;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
      }
      .booking-form-group .tenant-select {
        max-height: 280px;
        overflow-y: auto;
        overscroll-behavior: contain;
        scrollbar-width: thin;
        scrollbar-color: rgba(255,255,255,0.3) rgba(0,0,0,0.2);
      }
      .booking-form-group .tenant-select::-webkit-scrollbar {
        width: 8px;
      }
      .booking-form-group .tenant-select::-webkit-scrollbar-track {
        background: rgba(0,0,0,0.2);
        border-radius: 4px;
      }
      .booking-form-group .tenant-select::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.3);
        border-radius: 4px;
      }
      .booking-form-group .tenant-select::-webkit-scrollbar-thumb:hover {
        background: rgba(255,255,255,0.5);
      }
      .booking-form-group .tenant-select option {
        padding: 8px 12px;
        border-bottom: 1px solid rgba(255,255,255,0.05);
      }
      .booking-form-group .tenant-select option:hover {
        background: rgba(96, 165, 250, 0.2);
      }
      .booking-form-group input[readonly] {
        background: rgba(255,255,255,0.05);
        border-color: rgba(255,255,255,0.12);
        cursor: not-allowed;
        color: rgba(255,255,255,0.85);
      }
      .booking-form-group input:focus,
      .booking-form-group select:focus {
        outline: none;
        border-color: #60a5fa;
        box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.25);
      }
      .booking-form-actions { display: flex; gap: 1rem; margin-top: 2rem; }
      .booking-form-actions button {
        flex: 1;
        padding: 0.75rem;
        border: none;
        border-radius: 6px;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s;
      }
      .btn-submit { background: #007AFF; color: #fff; box-shadow: 0 4px 12px rgba(0, 122, 255, 0.3); }
      .btn-submit:hover { background: #0A66DB; opacity: 0.9; }
      .btn-cancel { background: #FF3B30; color: #fff; border: none; }
      .btn-cancel:hover { background: #E63D32; opacity: 0.9; }
      /* Alert โมดัล แบบเดียวกับ confirm ลบ */
      .booking-alert-modal {
        position: fixed;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0,0,0,0.55);
        backdrop-filter: blur(4px);
        z-index: 4000;
        animation: toastIn 160ms ease-out;
      }
      .booking-alert-modal.hide { animation: toastOut 160ms ease-in forwards; }
      .booking-alert-dialog {
        width: min(520px, 92vw);
        background: #0f172a;
        color: #e2e8f0;
        border-radius: 14px;
        border: 1px solid rgba(255,255,255,0.08);
        box-shadow: 0 20px 50px rgba(0,0,0,0.45);
        padding: 1.6rem 1.4rem;
        text-align: center;
      }
      .booking-alert-dialog.success { border-color: rgba(34,197,94,0.35); }
      .booking-alert-dialog.error { border-color: rgba(239,68,68,0.45); }
      .booking-alert-icon {
        width: 56px;
        height: 56px;
        margin: 0 auto 0.85rem;
        border-radius: 50%;
        display: grid;
        place-items: center;
        font-size: 1.8rem;
        font-weight: 800;
        color: #0b1727;
      }
      .booking-alert-dialog.success .booking-alert-icon { background: #22c55e; }
      .booking-alert-dialog.error .booking-alert-icon { background: #ef4444; color: #fff; }
      .booking-alert-message { font-size: 1.05rem; font-weight: 700; margin-bottom: 1.1rem; }
      .booking-alert-actions { display: flex; justify-content: center; }
      .booking-alert-ok {
        min-width: 110px;
        padding: 0.75rem 1.25rem;
        border-radius: 10px;
        border: none;
        font-weight: 700;
        cursor: pointer;
        background: linear-gradient(135deg, #22c55e, #16a34a);
        color: #0b1727;
      }
      .booking-alert-dialog.error .booking-alert-ok { background: linear-gradient(135deg, #ef4444, #dc2626); color: #fff; }
      @keyframes toastIn { from { opacity: 0; transform: translate(0, -10px); } to { opacity: 1; transform: translate(0, 0); } }
      @keyframes toastOut { from { opacity: 1; transform: translate(0, 0); } to { opacity: 0; transform: translate(0, -8px); } }
      
      /* Hidden rooms and load more button */
      .room-card.hidden-room { display: none; }
      .load-more-container {
        display: flex;
        justify-content: center;
        margin-top: 1.5rem;
        padding-top: 1rem;
      }
      .load-more-btn {
        padding: 0.75rem 2rem;
        background: linear-gradient(135deg, rgba(37,99,235,0.2), rgba(29,78,216,0.2));
        color: #60a5fa;
        border: 1px solid rgba(96,165,250,0.4);
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
      }
      .load-more-btn:hover {
        background: linear-gradient(135deg, rgba(37,99,235,0.3), rgba(29,78,216,0.3));
        border-color: rgba(96,165,250,0.6);
        transform: translateY(-1px);
      }
      .load-more-btn.hidden {
        display: none;
      }

      /* ===== Light Theme Overrides for Booking Page ===== */
      html.light-theme .page-title,
      html.light-theme .section-title,
      html.light-theme h2,
      html.light-theme h3 {
          color: #111827 !important;
      }
      
      /* Booking stat cards */
      html.light-theme .booking-stat-card {
          background: rgba(255, 255, 255, 0.95) !important;
          border: 1px solid rgba(0, 0, 0, 0.1) !important;
          box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06) !important;
          color: #111827 !important;
      }
      html.light-theme .booking-stat-card h3 {
          color: #6b7280 !important;
      }
      html.light-theme .booking-stat-card .stat-value {
          color: #111827 !important;
      }
      html.light-theme .booking-stat-card .stat-chip {
          background: rgba(0, 0, 0, 0.06) !important;
          color: #374151 !important;
      }
      
      /* Room cards in light theme */
      html.light-theme .room-card,
      html.light-theme .booking-card {
          background: rgba(255, 255, 255, 0.95) !important;
          border-color: rgba(0, 0, 0, 0.1) !important;
          box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08) !important;
          color: #111827 !important;
      }
      html.light-theme .room-card:hover,
      html.light-theme .booking-card:hover {
          border-color: rgba(59, 130, 246, 0.4) !important;
      }
      /* Keep Web3 gradient for front card even in light theme */
      html.light-theme .room-card-face.front {
          background: linear-gradient(135deg, #6366f1 0%, #818cf8 50%, #a5b4fc 100%) !important;
          border: 2px solid rgba(99, 102, 241, 0.3) !important;
          color: #ffffff !important;
      }
      html.light-theme .room-card-face,
      html.light-theme .room-card-face.back {
          background: #ffffff !important;
          border: 1px solid rgba(0, 0, 0, 0.1) !important;
          color: #111827 !important;
      }
      html.light-theme .room-card-face.back div,
      html.light-theme .room-card-face.back span,
      html.light-theme .room-card-face.back strong {
          color: #111827 !important;
      }
      html.light-theme .room-card-face.back div[style*="color: rgba(255,255,255"] {
          color: #4b5563 !important;
      }
      html.light-theme .room-number {
          color: #111827 !important;
      }
      html.light-theme .room-info,
      html.light-theme .room-info-item,
      html.light-theme .room-info-item span,
      html.light-theme .room-info-item strong {
          color: #4b5563 !important;
      }
      html.light-theme .room-price,
      html.light-theme .room-price-header {
          color: #2563eb !important;
          background: rgba(59, 130, 246, 0.1) !important;
      }
      html.light-theme .room-status {
          background: #22c55e !important;
          color: #ffffff !important;
      }
      html.light-theme .room-image-container {
          background: rgba(99, 102, 241, 0.2) !important;
      }
      html.light-theme .room-image-placeholder {
          color: #9ca3af !important;
      }
      html.light-theme .room-image-placeholder svg {
          stroke: #9ca3af !important;
      }
      html.light-theme .room-image-placeholder span {
          color: #9ca3af !important;
      }
      
      /* Table styling for light theme - CRITICAL FIX */
      html.light-theme table,
      html.light-theme .datatable-table,
      html.light-theme .table--compact {
          background: rgba(255, 255, 255, 0.98) !important;
      }
      html.light-theme table th,
      html.light-theme .datatable-table th,
      html.light-theme .table--compact th {
          background: #f8fafc !important;
          color: #1f2937 !important;
          border-bottom: 2px solid rgba(0, 0, 0, 0.1) !important;
          font-weight: 600 !important;
      }
      html.light-theme table td,
      html.light-theme .datatable-table td,
      html.light-theme .table--compact td {
          color: #1f2937 !important;
          border-bottom: 1px solid rgba(0, 0, 0, 0.06) !important;
      }
      html.light-theme table tbody tr td,
      html.light-theme .datatable-table tbody tr td,
      html.light-theme .table--compact tbody tr td {
          color: #1f2937 !important;
      }
      html.light-theme table tr:hover td,
      html.light-theme .datatable-table tr:hover td,
      html.light-theme .table--compact tr:hover td {
          background: rgba(59, 130, 246, 0.05) !important;
      }
      
      /* Status text in table - "เข้าพักแล้ว" and "ยกเลิกแล้ว" */
      html.light-theme .crud-column span[style*="color: #34C759"],
      html.light-theme td span[style*="color: #34C759"] {
          color: #15803d !important;
          font-weight: 600 !important;
      }
      html.light-theme .crud-column span[style*="color: #FF3B30"],
      html.light-theme td span[style*="color: #FF3B30"] {
          color: #dc2626 !important;
          font-weight: 600 !important;
      }
      
      /* Action buttons in table */
      html.light-theme .animate-ui-action-btn.btn-success {
          background: linear-gradient(135deg, #22c55e, #16a34a) !important;
          color: #ffffff !important;
          border: none !important;
      }
      html.light-theme .animate-ui-action-btn.btn-success svg {
          stroke: #ffffff !important;
      }
      html.light-theme .animate-ui-action-btn.delete {
          background: linear-gradient(135deg, #ef4444, #dc2626) !important;
          color: #ffffff !important;
          border: none !important;
      }
      html.light-theme .animate-ui-action-btn.delete svg {
          stroke: #ffffff !important;
      }
      
      /* Buttons in light theme */
      html.light-theme .btn-primary,
      html.light-theme .btn-book,
      html.light-theme .btn-checkin,
      html.light-theme .book-btn,
      html.light-theme [class*="btn-save"] {
          background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
          color: #ffffff !important;
      }
      html.light-theme .btn-primary svg,
      html.light-theme .btn-book svg,
      html.light-theme .book-btn svg {
          stroke: #ffffff !important;
      }
      html.light-theme .btn-primary:hover,
      html.light-theme .btn-book:hover,
      html.light-theme .book-btn:hover {
          background: linear-gradient(135deg, #2563eb, #1d4ed8) !important;
      }
      html.light-theme .btn-secondary,
      html.light-theme .btn-outline {
          background: rgba(0, 0, 0, 0.05) !important;
          border-color: rgba(0, 0, 0, 0.15) !important;
          color: #374151 !important;
      }
      html.light-theme .btn-secondary svg,
      html.light-theme .btn-outline svg {
          stroke: #374151 !important;
      }
      html.light-theme .btn-secondary:hover,
      html.light-theme .btn-outline:hover {
          background: rgba(0, 0, 0, 0.1) !important;
      }
      html.light-theme .btn-cancel {
          background: linear-gradient(135deg, #ef4444, #dc2626) !important;
          color: #ffffff !important;
      }
      html.light-theme .btn-cancel svg {
          stroke: #ffffff !important;
      }
      html.light-theme .btn-danger {
          background: linear-gradient(135deg, #ef4444, #dc2626) !important;
          color: #ffffff !important;
      }
      html.light-theme .btn-danger svg {
          stroke: #ffffff !important;
      }
      html.light-theme .btn-submit {
          background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
          color: #ffffff !important;
      }
      
      /* View toggle buttons */
      html.light-theme .view-toggle button,
      html.light-theme .toggle-view-btn {
          background: rgba(0, 0, 0, 0.05) !important;
          border: 1px solid rgba(0, 0, 0, 0.15) !important;
          color: #374151 !important;
      }
      html.light-theme .view-toggle button.active,
      html.light-theme .toggle-view-btn.active {
          background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
          border-color: #3b82f6 !important;
          color: #ffffff !important;
      }
      
      /* Toggle rooms button */
      html.light-theme #toggleRoomsBtn {
          background: rgba(0, 0, 0, 0.05) !important;
          border: 1px solid rgba(0, 0, 0, 0.15) !important;
          color: #374151 !important;
      }
      html.light-theme #toggleRoomsBtn:hover {
          background: rgba(0, 0, 0, 0.1) !important;
          border-color: rgba(0, 0, 0, 0.2) !important;
      }
      
      /* Select dropdowns */
      html.light-theme select,
      html.light-theme .form-select,
      html.light-theme #sortSelect {
          background: rgba(255, 255, 255, 0.98) !important;
          border: 1px solid rgba(0, 0, 0, 0.15) !important;
          color: #111827 !important;
      }
      html.light-theme select:focus,
      html.light-theme .form-select:focus,
      html.light-theme #sortSelect:focus {
          border-color: #3b82f6 !important;
          box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15) !important;
      }
      html.light-theme select option {
          background: #ffffff !important;
          color: #111827 !important;
      }
      
      /* Input fields */
      html.light-theme input[type="text"],
      html.light-theme input[type="number"],
      html.light-theme input[type="date"],
      html.light-theme input[type="search"],
      html.light-theme textarea,
      html.light-theme .form-control {
          background: rgba(255, 255, 255, 0.98) !important;
          border: 1px solid rgba(0, 0, 0, 0.15) !important;
          color: #111827 !important;
      }
      html.light-theme input:focus,
      html.light-theme textarea:focus,
      html.light-theme .form-control:focus {
          border-color: #3b82f6 !important;
          box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15) !important;
      }
      html.light-theme input::placeholder,
      html.light-theme textarea::placeholder {
          color: #9ca3af !important;
      }
      html.light-theme input[readonly] {
          background: #f3f4f6 !important;
          color: #6b7280 !important;
      }
      
      /* DataTable controls */
      html.light-theme .datatable-input,
      html.light-theme .datatable-selector {
          background: rgba(255, 255, 255, 0.98) !important;
          border: 1px solid rgba(0, 0, 0, 0.15) !important;
          color: #111827 !important;
      }
      html.light-theme .datatable-info {
          color: #374151 !important;
      }
      html.light-theme .datatable-pagination a {
          color: #374151 !important;
          background: rgba(0, 0, 0, 0.05) !important;
          border: 1px solid rgba(0, 0, 0, 0.1) !important;
      }
      html.light-theme .datatable-pagination li.active a {
          background: #3b82f6 !important;
          color: #ffffff !important;
          border-color: #3b82f6 !important;
      }
      
      /* Status badges */
      html.light-theme .badge,
      html.light-theme .status-badge {
          font-weight: 600 !important;
      }
      html.light-theme .badge-success,
      html.light-theme .status-success,
      html.light-theme .status-available {
          background: rgba(34, 197, 94, 0.15) !important;
          color: #15803d !important;
      }
      html.light-theme .badge-warning,
      html.light-theme .status-pending {
          background: rgba(251, 191, 36, 0.2) !important;
          color: #b45309 !important;
      }
      html.light-theme .badge-danger,
      html.light-theme .status-cancelled {
          background: rgba(239, 68, 68, 0.15) !important;
          color: #dc2626 !important;
      }
      html.light-theme .badge-secondary,
      html.light-theme .status-empty {
          background: rgba(148, 163, 184, 0.25) !important;
          color: #475569 !important;
      }
      html.light-theme .price-badge {
          background: rgba(59, 130, 246, 0.1) !important;
          color: #2563eb !important;
      }
      
      /* Section header */
      html.light-theme .section-header h1,
      html.light-theme .manage-panel h1 {
          color: #111827 !important;
      }
      html.light-theme .section-header p {
          color: #6b7280 !important;
      }
      
      /* Deposit note box */
      html.light-theme div[style*="border: 1px solid rgba(34,197,94"],
      html.light-theme div[style*="border:1px solid rgba(34,197,94"] {
          background: rgba(34, 197, 94, 0.08) !important;
          border-color: rgba(34, 197, 94, 0.3) !important;
          color: #15803d !important;
      }
      
      /* Load more button */
      html.light-theme .load-more-btn {
          background: rgba(0, 0, 0, 0.05) !important;
          border: 1px solid rgba(0, 0, 0, 0.15) !important;
          color: #374151 !important;
      }
      html.light-theme .load-more-btn:hover {
          background: rgba(59, 130, 246, 0.1) !important;
          border-color: rgba(59, 130, 246, 0.3) !important;
          color: #2563eb !important;
      }
      html.light-theme .load-more-btn svg {
          stroke: currentColor !important;
      }
      
      /* Labels and text */
      html.light-theme label {
          color: #374151 !important;
      }
      html.light-theme .text-muted,
      html.light-theme .text-secondary {
          color: #6b7280 !important;
      }
      
      /* Modal styling */
      html.light-theme .booking-modal-content {
          background: #ffffff !important;
          border: 1px solid rgba(0, 0, 0, 0.1) !important;
          color: #111827 !important;
      }
      html.light-theme .booking-modal-content h2 {
          color: #111827 !important;
      }
      html.light-theme .booking-form-group label {
          color: #374151 !important;
      }
      html.light-theme .booking-form-group label small {
          color: #6b7280 !important;
      }
      html.light-theme .booking-form-group input,
      html.light-theme .booking-form-group select {
          background: #ffffff !important;
          border: 1px solid rgba(0, 0, 0, 0.15) !important;
          color: #111827 !important;
      }
      html.light-theme .booking-form-group select option {
          background: #ffffff !important;
          color: #111827 !important;
      }
      html.light-theme .booking-form-group input[readonly] {
          background: #f3f4f6 !important;
          color: #6b7280 !important;
      }
      html.light-theme .booking-form-group .tenant-select {
          scrollbar-color: rgba(0, 0, 0, 0.2) rgba(0, 0, 0, 0.05) !important;
      }
      html.light-theme .booking-form-group .tenant-select option:hover {
          background: rgba(59, 130, 246, 0.1) !important;
      }
      
      /* Modal close button */
      html.light-theme .booking-modal-content button[aria-label="ปิดหน้าต่าง"] {
          background: #f3f4f6 !important;
          border: 1px solid #e5e7eb !important;
          color: #374151 !important;
      }
      html.light-theme .booking-modal-content button[aria-label="ปิดหน้าต่าง"]:hover {
          background: #e5e7eb !important;
      }
      
      /* Booking modal deposit section */
      html.light-theme .booking-modal-content div[style*="border: 1px dashed"],
      html.light-theme .booking-modal-content div[style*="border:1px dashed"] {
          background: #f9fafb !important;
          border-color: #d1d5db !important;
          color: #374151 !important;
      }
      html.light-theme .booking-modal-content div[style*="color:#cbd5e1"],
      html.light-theme .booking-modal-content div[style*="color: #cbd5e1"] {
          color: #6b7280 !important;
      }
      html.light-theme .booking-modal-content div[style*="color:#f5f5f5"],
      html.light-theme .booking-modal-content div[style*="color: #f5f5f5"] {
          color: #374151 !important;
      }
      html.light-theme .booking-modal-content div[style*="background: rgba(34,197,94"],
      html.light-theme .booking-modal-content div[style*="background:rgba(34,197,94"],
      html.light-theme .booking-modal-content div[style*="color:#f8fafc"],
      html.light-theme .booking-modal-content div[style*="color: #f8fafc"] {
          background: rgba(34, 197, 94, 0.1) !important;
          border-color: rgba(34, 197, 94, 0.35) !important;
          color: #15803d !important;
      }
      
      /* Alert modal light theme */
      html.light-theme .booking-alert-modal {
          background: rgba(0, 0, 0, 0.4) !important;
      }
      html.light-theme .booking-alert-dialog {
          background: #ffffff !important;
          border: 1px solid #e5e7eb !important;
          color: #374151 !important;
      }
      html.light-theme .booking-alert-message {
          color: #111827 !important;
      }
      html.light-theme .booking-alert-dialog.success {
          border-color: rgba(34, 197, 94, 0.4) !important;
      }
      html.light-theme .booking-alert-dialog.error {
          border-color: rgba(239, 68, 68, 0.4) !important;
      }
      
      /* All SVG icons in light theme */
      html.light-theme .page-title svg,
      html.light-theme h1 svg,
      html.light-theme h2 svg,
      html.light-theme .section-header svg {
          stroke: #111827 !important;
      }
      html.light-theme .room-card svg:not(.room-image-placeholder svg) {
          stroke: #374151 !important;
      }
      html.light-theme .manage-panel svg {
          stroke: currentColor !important;
      }
      
      /* Empty state styling */
      html.light-theme .empty-state {
          color: #6b7280 !important;
      }
      html.light-theme .empty-state svg {
          stroke: #9ca3af !important;
      }
      
      /* DataTable wrapper and label */
      html.light-theme .datatable-wrapper .datatable-top label,
      html.light-theme .datatable-wrapper .datatable-bottom label {
          color: #374151 !important;
      }
      html.light-theme .datatable-wrapper .datatable-top span,
      html.light-theme .datatable-wrapper .datatable-bottom span {
          color: #374151 !important;
      }
      
      /* ===== COMPREHENSIVE Light Theme Table Fix ===== */
      html.light-theme .report-table,
      html.light-theme .datatable-wrapper {
          background: #ffffff !important;
          border-radius: 12px !important;
          border: 1px solid #e5e7eb !important;
      }
        /* Mirror styles for body.live-light for dynamic detection */
        body.live-light .report-table,
        body.live-light .datatable-wrapper {
          background: #ffffff !important;
          border-radius: 12px !important;
          border: 1px solid #e5e7eb !important;
        }
      html.light-theme table thead,
      html.light-theme .datatable-table thead {
          background: #f8fafc !important;
      }
        body.live-light table thead,
        body.live-light .datatable-table thead {
          background: #f8fafc !important;
        }
      html.light-theme table thead tr,
      html.light-theme .datatable-table thead tr {
          background: #f8fafc !important;
      }
        body.live-light table thead tr,
        body.live-light .datatable-table thead tr {
          background: #f8fafc !important;
        }
      html.light-theme table thead th,
      html.light-theme .datatable-table thead th {
          background: #f8fafc !important;
          color: #1e293b !important;
          border-bottom: 2px solid #e2e8f0 !important;
          font-weight: 600 !important;
      }
        body.live-light table thead th,
        body.live-light .datatable-table thead th {
          background: #f8fafc !important;
          color: #1e293b !important;
          border-bottom: 2px solid #e2e8f0 !important;
          font-weight: 600 !important;
        }
      html.light-theme table thead th a,
      html.light-theme .datatable-table thead th a {
          color: #1e293b !important;
      }
        body.live-light table thead th a,
        body.live-light .datatable-table thead th a {
          color: #1e293b !important;
        }
      html.light-theme table tbody,
      html.light-theme .datatable-table tbody {
          background: #ffffff !important;
      }
        body.live-light table tbody,
        body.live-light .datatable-table tbody {
          background: #ffffff !important;
        }
      html.light-theme table tbody tr,
      html.light-theme .datatable-table tbody tr {
          background: #ffffff !important;
          color: #1f2937 !important;
      }
        body.live-light table tbody tr,
        body.live-light .datatable-table tbody tr {
          background: #ffffff !important;
          color: #1f2937 !important;
        }
      html.light-theme table tbody tr:nth-child(even),
      html.light-theme .datatable-table tbody tr:nth-child(even) {
          background: #f9fafb !important;
      }
        body.live-light table tbody tr:nth-child(even),
        body.live-light .datatable-table tbody tr:nth-child(even) {
          background: #f9fafb !important;
        }
      html.light-theme table tbody tr:hover,
      html.light-theme .datatable-table tbody tr:hover {
          background: rgba(59, 130, 246, 0.06) !important;
      }
        body.live-light table tbody tr:hover,
        body.live-light .datatable-table tbody tr:hover {
          background: rgba(59, 130, 246, 0.06) !important;
        }
      html.light-theme table tbody td,
      html.light-theme .datatable-table tbody td {
          color: #374151 !important;
          border-bottom: 1px solid #f1f5f9 !important;
      }
        body.live-light table tbody td,
        body.live-light .datatable-table tbody td {
          color: #374151 !important;
          border-bottom: 1px solid #f1f5f9 !important;
        }
      html.light-theme table tbody td a,
      html.light-theme .datatable-table tbody td a {
          color: #2563eb !important;
      }
        body.live-light table tbody td a,
        body.live-light .datatable-table tbody td a {
          color: #2563eb !important;
        }
      html.light-theme table tbody td strong,
      html.light-theme .datatable-table tbody td strong {
          color: #1e293b !important;
      }
        body.live-light table tbody td strong,
        body.live-light .datatable-table tbody td strong {
          color: #1e293b !important;
        }
      
      /* Fix table sort arrows/icons */
      html.light-theme .datatable-sorter::before,
      html.light-theme .datatable-sorter::after {
          border-bottom-color: #6b7280 !important;
          border-top-color: #6b7280 !important;
      }
      html.light-theme .datatable-sorter.asc::after,
      html.light-theme .datatable-sorter.desc::before {
          border-bottom-color: #2563eb !important;
          border-top-color: #2563eb !important;
      }
        body.live-light .datatable-sorter::before,
        body.live-light .datatable-sorter::after {
          border-bottom-color: #6b7280 !important;
          border-top-color: #6b7280 !important;
        }
        body.live-light .datatable-sorter.asc::after,
        body.live-light .datatable-sorter.desc::before {
          border-bottom-color: #2563eb !important;
          border-top-color: #2563eb !important;
        }
      
      /* Fix inline status spans in table */
      html.light-theme table td span[style*="background: #ff9800"],
      html.light-theme table td span[style*="background:#ff9800"] {
          background: #f59e0b !important;
          color: #ffffff !important;
      }
      html.light-theme table td span[style*="background: #4caf50"],
      html.light-theme table td span[style*="background:#4caf50"] {
          background: #22c55e !important;
          color: #ffffff !important;
      }
      html.light-theme table td span[style*="background: #f44336"],
      html.light-theme table td span[style*="background:#f44336"] {
          background: #ef4444 !important;
          color: #ffffff !important;
      }
        body.live-light table td span[style*="background: #ff9800"],
        body.live-light table td span[style*="background:#ff9800"] {
          background: #f59e0b !important;
          color: #ffffff !important;
        }
        body.live-light table td span[style*="background: #4caf50"],
        body.live-light table td span[style*="background:#4caf50"] {
          background: #22c55e !important;
          color: #ffffff !important;
        }
        body.live-light table td span[style*="background: #f44336"],
        body.live-light table td span[style*="background:#f44336"] {
          background: #ef4444 !important;
          color: #ffffff !important;
        }
      
      /* Fix section headers and titles in light theme */
      html.light-theme .manage-panel {
          background: #ffffff !important;
          border: 1px solid #e5e7eb !important;
          color: #111827 !important;
      }
      html.light-theme .manage-panel .section-header h1,
      html.light-theme .manage-panel h1,
      html.light-theme .section-header h1 {
          color: #111827 !important;
      }
      html.light-theme .manage-panel p,
      html.light-theme .section-header p {
          color: #6b7280 !important;
      }
      html.light-theme .manage-panel p[style*="color:#94a3b8"],
      html.light-theme .manage-panel p[style*="color: #94a3b8"] {
          color: #6b7280 !important;
      }
      
      /* Fix deposit note box - the green bordered box */
      html.light-theme div[style*="border:1px solid rgba(34,197,94"],
      html.light-theme div[style*="border: 1px solid rgba(34,197,94"],
      html.light-theme div[style*="color:#e0f2fe"],
      html.light-theme div[style*="color: #e0f2fe"],
      html.light-theme .manage-panel div[style*="background: rgba(34,197,94"],
      html.light-theme .manage-panel div[style*="background:rgba(34,197,94"] {
          background: rgba(34, 197, 94, 0.08) !important;
          border-color: rgba(34, 197, 94, 0.35) !important;
          color: #15803d !important;
      }
      
      /* Fix SVG icons in room image placeholder */
      html.light-theme .room-image-placeholder svg {
          stroke: #9ca3af !important;
          color: #9ca3af !important;
      }
      html.light-theme .room-image-placeholder span {
          color: #9ca3af !important;
      }
      html.light-theme .room-image-placeholder {
          color: #9ca3af !important;
      }
      
      /* Fix action buttons appearance */
      html.light-theme .crud-column .animate-ui-action-btn.btn-success {
          background: linear-gradient(135deg, #22c55e, #16a34a) !important;
          color: #ffffff !important;
          border: none !important;
          box-shadow: 0 2px 4px rgba(34, 197, 94, 0.3) !important;
      }
      html.light-theme .crud-column .animate-ui-action-btn.delete {
          background: linear-gradient(135deg, #ef4444, #dc2626) !important;
          color: #ffffff !important;
          border: none !important;
          box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3) !important;
      }
        body.live-light .crud-column .animate-ui-action-btn.btn-success {
          background: linear-gradient(135deg, #22c55e, #16a34a) !important;
          color: #ffffff !important;
          border: none !important;
          box-shadow: 0 2px 4px rgba(34, 197, 94, 0.3) !important;
        }
        body.live-light .crud-column .animate-ui-action-btn.delete {
          background: linear-gradient(135deg, #ef4444, #dc2626) !important;
          color: #ffffff !important;
          border: none !important;
          box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3) !important;
        }
      
      /* Fix table cell text and IDs */
      html.light-theme table td:first-child,
      html.light-theme .datatable-table td:first-child {
          color: #3b82f6 !important;
          font-weight: 600 !important;
      }
      
      /* Fix toggle rooms button */
      html.light-theme #toggleRoomsBtn {
          background: #ffffff !important;
          border: 1px solid #d1d5db !important;
          color: #374151 !important;
          box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important;
      }
      html.light-theme #toggleRoomsBtn:hover {
          background: #f9fafb !important;
          border-color: #9ca3af !important;
      }
        body.live-light #toggleRoomsBtn {
          background: #ffffff !important;
          border: 1px solid #d1d5db !important;
          color: #374151 !important;
          box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important;
        }
        body.live-light #toggleRoomsBtn:hover {
          background: #f9fafb !important;
          border-color: #9ca3af !important;
        }
      
      /* Fix sort select dropdown */
      html.light-theme #sortSelect {
          background: #ffffff !important;
          border: 1px solid #d1d5db !important;
          color: #374151 !important;
      }
      html.light-theme #sortSelect option {
          background: #ffffff !important;
          color: #374151 !important;
      }
        body.live-light #sortSelect {
          background: #ffffff !important;
          border: 1px solid #d1d5db !important;
          color: #374151 !important;
        }
        body.live-light #sortSelect option {
          background: #ffffff !important;
          color: #374151 !important;
        }
      
      /* Fix DataTable search and pagination */
      html.light-theme .datatable-top,
      html.light-theme .datatable-bottom {
          background: transparent !important;
      }
      html.light-theme .datatable-input {
          background: #ffffff !important;
          border: 1px solid #d1d5db !important;
          color: #374151 !important;
      }
      html.light-theme .datatable-input::placeholder {
          color: #9ca3af !important;
      }
      html.light-theme .datatable-selector {
          background: #ffffff !important;
          border: 1px solid #d1d5db !important;
          color: #374151 !important;
      }
      html.light-theme .datatable-info {
          color: #6b7280 !important;
      }
        body.live-light .datatable-input {
          background: #ffffff !important;
          border: 1px solid #d1d5db !important;
          color: #374151 !important;
        }
        body.live-light .datatable-input::placeholder {
          color: #9ca3af !important;
        }
        body.live-light .datatable-selector {
          background: #ffffff !important;
          border: 1px solid #d1d5db !important;
          color: #374151 !important;
        }
        body.live-light .datatable-info {
          color: #6b7280 !important;
        }
      html.light-theme .datatable-pagination li a {
          background: #ffffff !important;
          border: 1px solid #e5e7eb !important;
          color: #374151 !important;
      }
      html.light-theme .datatable-pagination li a:hover {
          background: #f3f4f6 !important;
          border-color: #d1d5db !important;
      }
      html.light-theme .datatable-pagination li.datatable-active a {
          background: #2563eb !important;
          border-color: #2563eb !important;
          color: #ffffff !important;
      }
        body.live-light .datatable-pagination li a {
          background: #ffffff !important;
          border: 1px solid #e5e7eb !important;
          color: #374151 !important;
        }
        body.live-light .datatable-pagination li a:hover {
          background: #f3f4f6 !important;
          border-color: #d1d5db !important;
        }
        body.live-light .datatable-pagination li.datatable-active a {
          background: #2563eb !important;
          border-color: #2563eb !important;
          color: #ffffff !important;
        }
      
      /* Fix price badge color */
      html.light-theme .price-badge,
      html.light-theme strong.price-badge {
          background: rgba(59, 130, 246, 0.1) !important;
          color: #2563eb !important;
      }
      
      /* Fix load more button SVG */
      html.light-theme .load-more-btn svg {
          stroke: currentColor !important;
      }
      
      /* Fix header SVG icons */
      html.light-theme .page-header svg,
      html.light-theme .section-header svg,
      html.light-theme h1 svg,
      html.light-theme h2 svg {
          stroke: #374151 !important;
      }
    </style>
  </head>
  <body class="reports-page">
    <div class="app-shell">
      <?php include __DIR__ . '/../includes/sidebar.php'; ?>
      <main class="app-main">
        <div>
          <?php 
            $pageTitle = 'จองห้องพัก';
            include __DIR__ . '/../includes/page_header.php'; 
          ?>

          <!-- แสดง Success/Error Messages -->
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

          <!-- ส่วนสถิติการจอง -->
          <section class="manage-panel">
            <div class="section-header">
              <div>
                <h1>
                  <svg class="header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:28px;height:28px;margin-right:8px;vertical-align:middle;">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                  </svg>
                  ภาพรวมผู้เช่า
                </h1>
                <p style="color:#94a3b8;margin-top:0.2rem;">สถิติผู้เช่าปัจจุบัน</p>
              </div>
            </div>
            <div class="booking-stats">
              <div class="booking-stat-card">
                <div class="stat-icon blue">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                  </svg>
                </div>
                <h3>ผู้เช่าทั้งหมด</h3>
                <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                <div class="stat-chip">
                  <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#60a5fa;animation:pulse 2s infinite;"></span>
                  รวมทั้งหมด
                </div>
              </div>
              <div class="booking-stat-card">
                <div class="stat-icon green">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                    <path d="M9 16l2 2 4-4"></path>
                  </svg>
                </div>
                <h3>จองแล้ว</h3>
                <div class="stat-value"><?php echo number_format($stats['reserved']); ?></div>
                <div class="stat-chip">
                  <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#22c55e;animation:pulse 2s infinite;"></span>
                  รอเข้าพัก
                </div>
              </div>
              <div class="booking-stat-card">
                <div class="stat-icon red">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                  </svg>
                </div>
                <h3>พักอยู่</h3>
                <div class="stat-value"><?php echo number_format($stats['checkedin']); ?></div>
                <div class="stat-chip">
                  <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#ef4444;animation:pulse 2s infinite;"></span>
                  กำลังเข้าพัก
                </div>
              </div>
            </div>
          </section>

          <!-- Toggle button for available rooms -->
          <div style="margin:1.5rem 0;">
            <button type="button" id="toggleRoomsBtn" style="white-space:nowrap;padding:0.8rem 1.5rem;cursor:pointer;font-size:1rem;background:#1e293b;border:1px solid #334155;color:#cbd5e1;border-radius:8px;transition:all 0.2s;box-shadow:0 2px 4px rgba(0,0,0,0.1);" onclick="toggleAvailableRooms()" onmouseover="this.style.background='#334155';this.style.borderColor='#475569'" onmouseout="this.style.background='#1e293b';this.style.borderColor='#334155'">
              <span id="toggleRoomsIcon">▼</span> <span id="toggleRoomsText">ซ่อนห้องพักที่ว่าง</span>
            </button>
          </div>

          <!-- ส่วนแสดงห้องว่าง -->
          <section class="manage-panel booking-section" id="availableRoomsSection">
            <div class="section-header">
              <div>
                <h1>ห้องพักที่ว่าง</h1>
              </div>
              <div class="view-toggle">
                <button type="button" class="toggle-view-btn active" data-view="grid" onclick="return window.__setBookingView ? window.__setBookingView('grid', event) : true;">Grid</button>
                <button type="button" class="toggle-view-btn" data-view="list" onclick="return window.__setBookingView ? window.__setBookingView('list', event) : true;">List</button>
              </div>
            </div>

            <div style="margin:0.75rem 0 0; padding:0.9rem 1rem; border-radius:10px; border:1px solid rgba(34,197,94,0.35); background: rgba(34,197,94,0.12); color:#e0f2fe;">
              กรุณาชำระค่ามัดจำ 2,000 บาท ก่อนยืนยันการจอง ระบบจะหักออกจากบิลแรกให้อัตโนมัติ
            </div>
            
            <?php if (empty($availableRooms)): ?>
              <div style="padding: 2rem; text-align: center; color: #666;">
                <p style="font-size: 1.2rem;">ไม่มีห้องว่างในขณะนี้</p>
              </div>
            <?php else: ?>
              <div class="rooms-grid" id="roomsGrid" aria-live="polite">
                <?php foreach($availableRooms as $index => $room): ?>
                  <div class="room-card <?php echo $index >= 5 ? 'hidden-room' : ''; ?>" data-index="<?php echo $index; ?>">
                    <!-- Floating particles -->
                    <div class="card-particles"></div>
                    <div class="room-card-inner">
                      <div class="room-card-face front">
                        <!-- Status Badge Top Right -->
                        <span class="status-badge-web3">ว่าง</span>
                        
                        <!-- Room Image Centered -->
                        <div class="room-image-container">
                          <?php if (!empty($room['room_image'])): 
                            $img = basename($room['room_image']); 
                          ?>
                            <img src="../Assets/Images/Rooms/<?php echo htmlspecialchars($img); ?>" alt="รูปห้อง <?php echo $room['room_number']; ?>">
                          <?php else: ?>
                            <div class="room-image-placeholder" aria-label="ไม่มีรูปห้อง">
                              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="14" rx="2" ry="2"></rect>
                                <circle cx="8.5" cy="9.5" r="1.5"></circle>
                                <path d="M21 15l-4.5-4.5a2 2 0 0 0-3 0L5 19"></path>
                              </svg>
                              <span>ไม่มีรูป</span>
                            </div>
                          <?php endif; ?>
                        </div>
                        
                        <!-- Info at Bottom Left -->
                        <div class="card-info-bottom">
                          <div class="room-number-web3">ห้อง <?php echo htmlspecialchars((string)$room['room_number']); ?></div>
                          <div class="room-type-web3"><?php echo htmlspecialchars($room['type_name']); ?></div>
                          <div class="room-price-web3"><?php echo number_format((int)$room['type_price']); ?>/เดือน</div>
                        </div>
                        
                        <!-- List view extra info -->
                        <div class="list-view-extra" style="display:none;">
                          <div class="list-deposit">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                              <circle cx="12" cy="12" r="10"></circle>
                              <path d="M12 6v6l4 2"></path>
                            </svg>
                            มัดจำ ฿2,000
                          </div>
                          <div class="list-view-features">
                            <?php 
                            $displayFeatures = array_slice($roomFeatures, 0, 3);
                            foreach ($displayFeatures as $feature): ?>
                            <span class="list-feature-tag"><?php echo htmlspecialchars($feature); ?></span>
                            <?php endforeach; ?>
                          </div>
                          <button type="button" class="list-book-btn" 
                                  onclick="return window.__openBookingModal ? window.__openBookingModal(this, event) : true;"
                                  data-room-id="<?php echo $room['room_id']; ?>"
                                  data-room-number="<?php echo htmlspecialchars((string)$room['room_number']); ?>"
                                  data-room-type="<?php echo htmlspecialchars($room['type_name']); ?>"
                                  data-room-price="<?php echo number_format((int)$room['type_price']); ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                              <line x1="16" y1="2" x2="16" y2="6"></line>
                              <line x1="8" y1="2" x2="8" y2="6"></line>
                              <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            จองห้องนี้
                          </button>
                        </div>
                      </div>

                      <div class="room-card-face back">
                        <!-- Enhanced Back Card with More Information -->
                        <div class="back-card-content">
                          <div class="back-header">
                            <div class="room-number-back">
                              <svg class="room-icon-animated" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                <polyline points="9 22 9 12 15 12 15 22"></polyline>
                              </svg>
                              <span>ห้อง <?php echo htmlspecialchars((string)$room['room_number']); ?></span>
                            </div>
                            <span class="availability-badge">
                              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                              </svg>
                              พร้อมให้เช่า
                            </span>
                          </div>
                          
                          <div class="back-details">
                            <div class="detail-item">
                              <div class="detail-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                  <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                  <line x1="3" y1="9" x2="21" y2="9"></line>
                                  <line x1="9" y1="21" x2="9" y2="9"></line>
                                </svg>
                              </div>
                              <div class="detail-text">
                                <span class="detail-label">ประเภทห้อง</span>
                                <span class="detail-value"><?php echo htmlspecialchars($room['type_name']); ?></span>
                              </div>
                            </div>
                            
                            <div class="detail-item highlight">
                              <div class="detail-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                  <line x1="12" y1="1" x2="12" y2="23"></line>
                                  <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                </svg>
                              </div>
                              <div class="detail-text">
                                <span class="detail-label">ค่าเช่ารายเดือน</span>
                                <span class="detail-value price">฿<?php echo number_format((int)$room['type_price']); ?></span>
                              </div>
                            </div>
                            
                            <div class="detail-item">
                              <div class="detail-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                  <circle cx="12" cy="12" r="10"></circle>
                                  <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                              </div>
                              <div class="detail-text">
                                <span class="detail-label">ค่ามัดจำ</span>
                                <span class="detail-value">฿2,000</span>
                              </div>
                            </div>
                            
                            <div class="detail-item">
                              <div class="detail-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                  <polyline points="14 2 14 8 20 8"></polyline>
                                  <line x1="16" y1="13" x2="8" y2="13"></line>
                                  <line x1="16" y1="17" x2="8" y2="17"></line>
                                </svg>
                              </div>
                              <div class="detail-text">
                                <span class="detail-label">สัญญาขั้นต่ำ</span>
                                <span class="detail-value">6 เดือน</span>
                              </div>
                            </div>
                          </div>
                          
                          <div class="back-features">
                            <?php 
                            // SVG icons สำหรับแต่ละ feature
                            $featureIcons = [
                              'ไฟฟ้า' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path></svg>',
                              'น้ำประปา' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path></svg>',
                              'WiFi' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;"><path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"></path></svg>',
                              'แอร์' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;"><path d="M8 16a4 4 0 0 1-4-4 4 4 0 0 1 4-4h8a4 4 0 0 1 4 4 4 4 0 0 1-4 4H8zm0-8v12M16 8v12"></path></svg>',
                              'เฟอร์นิเจอร์' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;"><rect x="2" y="6" width="20" height="8" rx="2"></rect><path d="M4 14v4M20 14v4M2 10h20"></path></svg>',
                              'ที่จอดรถ' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;"><rect x="1" y="3" width="15" height="13" rx="2"></rect><path d="M16 8h3l3 3v5h-2M8 16h8M7 16a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM16 16a2 2 0 1 0 0 4 2 2 0 0 0 0-4z"></path></svg>',
                              'กล้องวงจรปิด' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>',
                              'ตู้เย็น' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;"><rect x="4" y="2" width="16" height="20" rx="2"></rect><line x1="4" y1="10" x2="20" y2="10"></line><line x1="10" y1="6" x2="10" y2="6"></line><line x1="10" y1="14" x2="10" y2="18"></line></svg>',
                              'default' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>'
                            ];
                            foreach ($roomFeatures as $feature): 
                              $icon = $featureIcons[$feature] ?? $featureIcons['default'];
                            ?>
                            <span class="feature-tag"><?php echo $icon; ?> <?php echo htmlspecialchars($feature); ?></span>
                            <?php endforeach; ?>
                          </div>
                        </div>
                        
                        <div class="back-actions">
                          <button type="button" class="book-btn book-btn-back" 
                                  onclick="return window.__openBookingModal ? window.__openBookingModal(this, event) : true;"
                                  data-room-id="<?php echo $room['room_id']; ?>"
                                  data-room-number="<?php echo htmlspecialchars((string)$room['room_number']); ?>"
                                  data-room-type="<?php echo htmlspecialchars($room['type_name']); ?>"
                                  data-room-price="<?php echo number_format((int)$room['type_price']); ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;">
                              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                              <line x1="16" y1="2" x2="16" y2="6"></line>
                              <line x1="8" y1="2" x2="8" y2="6"></line>
                              <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            จองห้องนี้
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <?php if (count($availableRooms) > 5): ?>
              <div class="load-more-container">
                <button type="button" class="load-more-btn" id="loadMoreBtn" onclick="loadMoreRooms()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                  <span id="loadMoreText">โหลดเพิ่มเติม (<span id="remainingCount"><?php echo count($availableRooms) - 5; ?></span> ห้อง)</span>
                </button>
              </div>
              <?php endif; ?>

              <!-- Table view for available rooms -->
              <div class="report-table" id="roomsTable" style="display:none; margin-top: 2rem;">
                <table class="table--compact" id="table-available-rooms">
                  <thead>
                    <tr>
                      <th>หมายเลขห้อง</th>
                      <th>ประเภทห้อง</th>
                      <th>ราคา/เดือน</th>
                      <th>สถานะ</th>
                      <th>การดำเนินการ</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach($availableRooms as $room): ?>
                    <tr>
                      <td data-label="หมายเลขห้อง">
                        <strong>ห้อง <?php echo htmlspecialchars((string)$room['room_number']); ?></strong>
                      </td>
                      <td data-label="ประเภทห้อง">
                        <?php echo htmlspecialchars($room['type_name']); ?>
                      </td>
                      <td data-label="ราคา/เดือน">
                        <strong class="price-badge">฿<?php echo number_format((int)$room['type_price']); ?></strong>
                      </td>
                      <td data-label="สถานะ">
                        <span class="status-badge status-available">ว่าง</span>
                      </td>
                      <td data-label="การดำเนินการ">
                        <button type="button" class="btn btn-primary btn-sm"
                                onclick="return window.__openBookingModal ? window.__openBookingModal(this, event) : true;"
                                data-room-id="<?php echo $room['room_id']; ?>"
                                data-room-number="<?php echo htmlspecialchars((string)$room['room_number']); ?>"
                                data-room-type="<?php echo htmlspecialchars($room['type_name']); ?>"
                                data-room-price="<?php echo number_format((int)$room['type_price']); ?>">
                          จองห้อง
                        </button>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </section>

          <!-- ส่วนแสดงรายการจอง -->
          <section class="manage-panel">
            <div class="section-header" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
              <div>
                <h1>รายการจองทั้งหมด</h1>
              </div>
              <select id="sortSelect" onchange="changeSortBy(this.value)" style="padding:0.6rem 0.85rem;border-radius:8px;border:1px solid rgba(255,255,255,0.2);background:rgba(255,255,255,0.05);color:#f5f8ff;font-size:0.95rem;cursor:pointer;">
                <option value="newest" <?php echo ($sortBy === 'newest' ? 'selected' : ''); ?>>จองล่าสุด</option>
                <option value="oldest" <?php echo ($sortBy === 'oldest' ? 'selected' : ''); ?>>จองเก่าสุด</option>
                <option value="room_number" <?php echo ($sortBy === 'room_number' ? 'selected' : ''); ?>>หมายเลขห้อง</option>
              </select>
            </div>
            <div class="report-table">
              <table class="table--compact" id="table-bookings">
                <thead>
                  <tr>
                    <th>รหัส</th>
                    <th>ผู้เช่า</th>
                    <th>ห้องพัก</th>
                    <th>วันที่จอง</th>
                    <th>วันเข้าพัก</th>
                    <th>สถานะ</th>
                    <th class="crud-column">จัดการ</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($bookings)): ?>
                    <?php foreach($bookings as $bkg): ?>
                      <tr>
                        <td>#<?php echo htmlspecialchars((string)$bkg['bkg_id']); ?></td>
                        <td><?php echo htmlspecialchars($bkg['tnt_name'] ?? 'ยังไม่มีผู้เช่า'); ?></td>
                        <td><?php echo !empty($bkg['room_number']) ? htmlspecialchars((string)$bkg['room_number']) : '-'; ?></td>
                        <td><?php echo !empty($bkg['bkg_date']) ? date('Y-m-d', strtotime($bkg['bkg_date'])) : '-'; ?></td>
                        <td><?php echo !empty($bkg['bkg_checkin_date']) ? date('Y-m-d', strtotime($bkg['bkg_checkin_date'])) : '-'; ?></td>
                        <td>
                          <span style="
                            padding: 0.25rem 0.75rem;
                            border-radius: 12px;
                            font-size: 0.875rem;
                            background: <?php 
                              echo $bkg['bkg_status'] === '0' ? '#f44336' : 
                                  ($bkg['bkg_status'] === '1' ? '#ff9800' : '#4caf50'); 
                            ?>;
                            color: white;
                          ">
                            <?php echo $statusMap[$bkg['bkg_status']] ?? 'ไม่ทราบ'; ?>
                          </span>
                        </td>
                        <td class="crud-column">
                          <?php if ($bkg['bkg_status'] === '1'): ?>
                            <button type="button" 
                                    class="animate-ui-action-btn btn-success" 
                                    onclick="updateBookingStatus(<?php echo $bkg['bkg_id']; ?>, '2')">
                              เข้าพัก
                            </button>
                            <button type="button" 
                                    class="animate-ui-action-btn delete" 
                                    onclick="updateBookingStatus(<?php echo $bkg['bkg_id']; ?>, '0')">
                              ยกเลิก
                            </button>
                          <?php elseif ($bkg['bkg_status'] === '2'): ?>
                            <span style="color: #34C759; font-weight: 500;">เข้าพักแล้ว</span>
                          <?php elseif ($bkg['bkg_status'] === '0'): ?>
                            <span style="color: #FF3B30; font-weight: 500;">ยกเลิกแล้ว</span>
                          <?php endif; ?>
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

    <!-- Booking Modal -->
    <div class="booking-modal" id="bookingModal">
      <div class="booking-modal-content">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;">
          <h2 style="margin:0;">จองห้องพัก</h2>
          <button type="button" aria-label="ปิดหน้าต่าง" onclick="closeBookingModal()" style="background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.12);color:#e2e8f0;width:36px;height:36px;border-radius:10px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;font-size:1.2rem;">×</button>
        </div>
        <form id="bookingForm" method="POST" action="../Manage/process_booking.php">
          <input type="hidden" name="room_id" id="modal_room_id">
          
          <div class="booking-form-group">
            <label>หมายเลขห้อง:</label>
            <input type="text" id="modal_room_number" readonly>
          </div>
          
          <div class="booking-form-group">
            <label>ประเภทห้อง:</label>
            <input type="text" id="modal_room_type" readonly>
          </div>
          
          <div class="booking-form-group">
            <label>ราคา/เดือน:</label>
            <input type="text" id="modal_room_price" readonly>
          </div>

          <div class="booking-form-group">
            <label>ผู้เช่า: <span style="color: red;">*</span> <small style="color: #94a3b8; font-weight: normal;">(ทั้งหมด <?php echo count($selectableTenants); ?> คน)</small></label>
            <select name="tnt_id" id="modal_tenant_id" class="tenant-select" size="8" required style="width: 100%; padding: 0.8rem 0.9rem; border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; font-size: 1rem; background: rgba(12, 17, 29, 0.85); color: #f5f5f5; transition: border-color 0.2s ease, box-shadow 0.2s ease;">
              <option value="">-- เลือกผู้เช่า --</option>
              <?php foreach($selectableTenants as $tenant): ?>
                <option value="<?php echo $tenant['tnt_id']; ?>">
                  <?php echo htmlspecialchars($tenant['tnt_name']); ?> (<?php echo htmlspecialchars($tenant['tnt_phone'] ?? '-'); ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="booking-form-group">
            <label>วันที่จอง: <span style="color: red;">*</span></label>
            <input type="date" id="bkg_date" name="bkg_date" required value="<?php echo date('Y-m-d'); ?>" readonly>
          </div>
          
          <div class="booking-form-group">
            <label>วันที่เข้าพัก: <span style="color: red;">*</span></label>
            <input type="date" name="bkg_checkin_date" required min="<?php echo date('Y-m-d'); ?>">
          </div>

          <div class="booking-form-group" style="margin-top:-0.5rem;">
            <label>ค่ามัดจำ (ชำระล่วงหน้า/หักคืนบิลแรก)</label>
            <div style="padding:0.8rem 0.95rem; border-radius:10px; border:1px dashed rgba(255,255,255,0.25); background: rgba(255,255,255,0.03); color:#f5f5f5; font-weight:700;">
              ฿2,000
            </div>
            <div style="margin-top:0.35rem; color:#cbd5e1; font-size:0.9rem;">จะหักออกจากยอดบิลวันเข้าพักอัตโนมัติ</div>
          </div>

          <div style="margin: -0.25rem 0 0.75rem 0; color:#f8fafc; font-weight:600; font-size:0.95rem; background: rgba(34,197,94,0.15); border:1px solid rgba(34,197,94,0.35); padding:0.65rem 0.8rem; border-radius:10px;">
            กรุณาชำระค่ามัดจำ 2,000 บาท ก่อนยืนยันการจอง
          </div>
          
          <div class="booking-form-actions">
            <button type="submit" class="btn-submit">ยืนยันการจอง</button>
            <button type="button" class="btn-cancel" onclick="closeBookingModal()">ยกเลิก</button>
          </div>
        </form>
      </div>
    </div>

    <script src="../Assets/Javascript/toast-notification.js"></script>
    <script src="../Assets/Javascript/animate-ui.js"></script>
    <script src="../Assets/Javascript/main.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4" defer></script>
    <script>
      // Fallback handlers (keep UI usable even if other scripts fail)
      (() => {
        const SIDEBAR_KEY = 'sidebarCollapsed';
        const VIEW_KEY = 'bookingViewMode';
        const isMobile = () => window.innerWidth < 768;

        const toggleSidebar = (btn) => {
          const sidebar = document.querySelector('.app-sidebar');
          if (!sidebar) return;
          if (isMobile()) {
            sidebar.classList.toggle('mobile-open');
            document.body.classList.toggle('sidebar-open', sidebar.classList.contains('mobile-open'));
            if (btn) {
              btn.setAttribute('aria-expanded', sidebar.classList.contains('mobile-open').toString());
            }
          } else {
            const collapsed = sidebar.classList.toggle('collapsed');
            try { localStorage.setItem(SIDEBAR_KEY, collapsed.toString()); } catch (e) {}
            document.querySelectorAll('#sidebar-toggle, [data-sidebar-toggle]').forEach(b => b.setAttribute('aria-expanded', (!collapsed).toString()));
            // Ensure body state reflects closed sidebar
            if (collapsed) {
              document.body.classList.remove('sidebar-open');
            }
          }
        };

        const applyViewMode = (mode) => {
          const roomsGrid = document.getElementById('roomsGrid');
          if (!roomsGrid) return;
          roomsGrid.classList.toggle('list-view', mode === 'list');
          document.querySelectorAll('.toggle-view-btn').forEach(b => b.classList.toggle('active', b.dataset.view === mode));
          try { localStorage.setItem(VIEW_KEY, mode); } catch (e) {}
        };

        // Global delegation for sidebar + view toggle + book buttons
        document.addEventListener('click', (e) => {
          const sidebarBtn = e.target.closest('#sidebar-toggle, [data-sidebar-toggle]');
          if (sidebarBtn) {
            e.preventDefault();
            toggleSidebar(sidebarBtn);
            return;
          }

          const viewBtn = e.target.closest('.toggle-view-btn');
          if (viewBtn) {
            e.preventDefault();
            applyViewMode(viewBtn.dataset.view === 'list' ? 'list' : 'grid');
            return;
          }

          const bookBtn = e.target.closest('.book-btn');
          if (bookBtn) {
            e.preventDefault();
            const roomId = bookBtn.dataset.roomId;
            const roomNumber = bookBtn.dataset.roomNumber;
            const roomType = bookBtn.dataset.roomType;
            const roomPrice = bookBtn.dataset.roomPrice;
            const idEl = document.getElementById('modal_room_id');
            const numEl = document.getElementById('modal_room_number');
            const typeEl = document.getElementById('modal_room_type');
            const priceEl = document.getElementById('modal_room_price');
            if (idEl) idEl.value = roomId;
            if (numEl) numEl.value = 'ห้อง ' + roomNumber;
            if (typeEl) typeEl.value = roomType;
            if (priceEl) priceEl.value = '฿' + roomPrice;
            const bookingModal = document.getElementById('bookingModal');
            if (bookingModal) {
              bookingModal.classList.add('active');
              document.body.classList.add('modal-open');
            }
            return;
          }
        }, { passive: false });

        // Restore states after DOM ready
        document.addEventListener('DOMContentLoaded', () => {
          // Restore sidebar collapsed state (desktop only)
          if (!isMobile()) {
            try {
              const stored = localStorage.getItem(SIDEBAR_KEY);
              const sidebar = document.querySelector('.app-sidebar');
              if (sidebar) {
                sidebar.classList.toggle('collapsed', stored === 'true');
                document.querySelectorAll('#sidebar-toggle, [data-sidebar-toggle]').forEach(b => b.setAttribute('aria-expanded', (stored !== 'true').toString()));
              }
            } catch (e) {}
          }

          // Restore view mode
          try {
            const savedView = localStorage.getItem(VIEW_KEY) || 'grid';
            applyViewMode(savedView === 'list' ? 'list' : 'grid');
          } catch (e) {}
        });
      })();
    </script>
    <script>
      // Final safety net: explicit bindings after everything loads
      document.addEventListener('DOMContentLoaded', () => {
        const safeGet = (key) => {
          try { return localStorage.getItem(key); } catch (err) { return null; }
        };
        const safeSet = (key, value) => {
          try { localStorage.setItem(key, value); } catch (err) {}
        };
        const isMobile = () => window.innerWidth < 768;

        // Sidebar toggle
        const handleSidebarToggle = (event) => {
          if (event) event.preventDefault();
          const sidebar = document.querySelector('.app-sidebar');
          if (!sidebar) return false;
          if (isMobile()) {
            sidebar.classList.toggle('mobile-open');
            document.body.classList.toggle('sidebar-open', sidebar.classList.contains('mobile-open'));
            const expanded = sidebar.classList.contains('mobile-open').toString();
            document.querySelectorAll('#sidebar-toggle, [data-sidebar-toggle]').forEach(b => b.setAttribute('aria-expanded', expanded));
          } else {
            const collapsed = sidebar.classList.toggle('collapsed');
            safeSet('sidebarCollapsed', collapsed.toString());
            document.querySelectorAll('#sidebar-toggle, [data-sidebar-toggle]').forEach(b => b.setAttribute('aria-expanded', (!collapsed).toString()));
            if (collapsed) {
              document.body.classList.remove('sidebar-open');
            }
          }
          return false;
        };

        const restoreSidebar = () => {
          if (isMobile()) return;
          const sidebar = document.querySelector('.app-sidebar');
          if (!sidebar) return;
          const stored = safeGet('sidebarCollapsed');
          const shouldCollapse = stored === 'true';
          sidebar.classList.toggle('collapsed', shouldCollapse);
          document.querySelectorAll('#sidebar-toggle, [data-sidebar-toggle]').forEach(b => b.setAttribute('aria-expanded', (!shouldCollapse).toString()));
        };

        document.querySelectorAll('#sidebar-toggle, [data-sidebar-toggle]').forEach(btn => {
          btn.onclick = handleSidebarToggle;
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
          const sidebar = document.querySelector('.app-sidebar');
          if (!sidebar) return;
          const isOpenMobile = sidebar.classList.contains('mobile-open');
          if (isMobile() && isOpenMobile) {
            const insideSidebar = e.target.closest('.app-sidebar');
            const toggleBtn = e.target.closest('#sidebar-toggle, [data-sidebar-toggle]');
            if (!insideSidebar && !toggleBtn) {
              sidebar.classList.remove('mobile-open');
              document.body.classList.remove('sidebar-open');
              document.querySelectorAll('#sidebar-toggle, [data-sidebar-toggle]').forEach(b => b.setAttribute('aria-expanded', 'false'));
            }
          }
        }, { passive: true });

        // Close sidebar on ESC
        document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape') {
            const sidebar = document.querySelector('.app-sidebar');
            if (!sidebar) return;
            if (isMobile() && sidebar.classList.contains('mobile-open')) {
              sidebar.classList.remove('mobile-open');
              document.body.classList.remove('sidebar-open');
              document.querySelectorAll('#sidebar-toggle, [data-sidebar-toggle]').forEach(b => b.setAttribute('aria-expanded', 'false'));
            }
          }
        });

        // View toggle buttons
        const roomsGrid = document.getElementById('roomsGrid');
        const applyView = (mode) => {
          if (!roomsGrid) return;
          const normalized = mode === 'list' ? 'list' : 'grid';
          roomsGrid.classList.toggle('list-view', normalized === 'list');
          document.querySelectorAll('.toggle-view-btn').forEach(b => b.classList.toggle('active', b.dataset.view === normalized));
          safeSet('bookingViewMode', normalized);
        };
        document.querySelectorAll('.toggle-view-btn').forEach(btn => {
          btn.onclick = (e) => {
            if (e) e.preventDefault();
            applyView(btn.dataset.view === 'list' ? 'list' : 'grid');
            return false;
          };
        });

        // Book buttons
        const openBookingFromButton = (btn) => {
          if (!btn) return;
          const roomId = btn.dataset.roomId;
          const roomNumber = btn.dataset.roomNumber;
          const roomType = btn.dataset.roomType;
          const roomPrice = btn.dataset.roomPrice;
          const idEl = document.getElementById('modal_room_id');
          const numEl = document.getElementById('modal_room_number');
          const typeEl = document.getElementById('modal_room_type');
          const priceEl = document.getElementById('modal_room_price');
          if (idEl) idEl.value = roomId;
          if (numEl) numEl.value = 'ห้อง ' + roomNumber;
          if (typeEl) typeEl.value = roomType;
          if (priceEl) priceEl.value = '฿' + roomPrice;
          const bookingModal = document.getElementById('bookingModal');
          if (bookingModal) {
            bookingModal.classList.add('active');
            document.body.classList.add('modal-open');
          }
        };

        document.querySelectorAll('.book-btn').forEach(btn => {
          btn.onclick = (e) => {
            if (e) e.preventDefault();
            openBookingFromButton(btn);
            return false;
          };
        });

        restoreSidebar();
        applyView(safeGet('bookingViewMode') || 'grid');
      });
    </script>
    <script>
      // Additional global functions
      window.changeSortBy = function(sortValue) {
        const url = new URL(window.location);
        url.searchParams.set('sort', sortValue);
        window.location.href = url.toString();
      };

      window.updateBookingStatus = async function(bookingId, newStatus) {
        const statusText = newStatus === '2' ? 'เข้าพัก' : 'ยกเลิก';
        const confirmed = await showConfirmDialog(
          `ยืนยันการ${statusText}การจอง`,
          `คุณต้องการเปลี่ยนสถานะการจองนี้เป็น <strong>"${statusText}"</strong> หรือไม่?`
        );
        
        if (confirmed) {
          const formData = new FormData();
          formData.append('bkg_id', bookingId);
          formData.append('bkg_status', newStatus);
          
          fetch('../Manage/update_booking_status.php', {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              showToast('สำเร็จ', data.message, 'success');
              setTimeout(() => location.reload(), 1500);
            } else {
              showToast('ผิดพลาด', data.error || 'ไม่สามารถอัปเดตได้', 'error');
            }
          })
          .catch(error => {
            console.error('Error:', error);
            showToast('ผิดพลาด', 'เกิดข้อผิดพลาดในการส่งข้อมูล', 'error');
          });
        }
      };

      // Helper function to bind event listeners
      window.bindEventListeners = function() {
        // Bind toggle view buttons
        const viewToggleButtons = document.querySelectorAll('.toggle-view-btn');
        const roomsGrid = document.getElementById('roomsGrid');
        const VIEW_KEY = 'bookingViewMode';

        const applyViewMode = (mode) => {
          if (!roomsGrid) return;
          if (mode === 'list') {
            roomsGrid.classList.add('list-view');
          } else {
            roomsGrid.classList.remove('list-view');
          }
          viewToggleButtons.forEach(b => {
            b.classList.toggle('active', b.dataset.view === mode);
          });
        };

        viewToggleButtons.forEach(btn => {
          btn.removeEventListener('click', btn._clickHandler);
          btn._clickHandler = function() {
            const mode = this.dataset.view === 'list' ? 'list' : 'grid';
            applyViewMode(mode);
            localStorage.setItem(VIEW_KEY, mode);
          };
          btn.addEventListener('click', btn._clickHandler);
        });

        // Bind book buttons
        document.querySelectorAll('.book-btn').forEach(btn => {
          btn.removeEventListener('click', btn._clickHandler);
          btn._clickHandler = function(e) {
            e.preventDefault();
            
            const roomId = this.dataset.roomId;
            const roomNumber = this.dataset.roomNumber;
            const roomType = this.dataset.roomType;
            const roomPrice = this.dataset.roomPrice;
            
            document.getElementById('modal_room_id').value = roomId;
            document.getElementById('modal_room_number').value = 'ห้อง ' + roomNumber;
            document.getElementById('modal_room_type').value = roomType;
            document.getElementById('modal_room_price').value = '฿' + roomPrice;
            
            const bookingModal = document.getElementById('bookingModal');
            if (bookingModal) {
              bookingModal.classList.add('active');
              document.body.classList.add('modal-open');
            }
          };
          btn.addEventListener('click', btn._clickHandler);
        });
      };

      // รอให้ DOM โหลดเสร็จ
      let visibleRooms = 5;
      const ROOMS_PER_LOAD = 5;

      document.addEventListener('DOMContentLoaded', function() {
        // ===== CARD FLIP ON HOVER WITH DELAY =====
        // Only flip if mouse stays on card for 400ms
        const flipDelay = 400; // milliseconds
        const flipTimers = new WeakMap();
        
        function setupCardFlip() {
          document.querySelectorAll('.room-card').forEach(card => {
            // Skip if already setup
            if (card.dataset.flipSetup) return;
            card.dataset.flipSetup = 'true';
            
            card.addEventListener('mouseenter', () => {
              // Don't flip in list view
              if (card.closest('.rooms-grid.list-view')) return;
              
              // Clear any existing timer
              const existingTimer = flipTimers.get(card);
              if (existingTimer) clearTimeout(existingTimer);
              
              // Set timer to flip after delay
              const timer = setTimeout(() => {
                card.classList.add('flipped');
              }, flipDelay);
              flipTimers.set(card, timer);
            });
            
            card.addEventListener('mouseleave', () => {
              // Clear timer if mouse leaves before delay
              const timer = flipTimers.get(card);
              if (timer) {
                clearTimeout(timer);
                flipTimers.delete(card);
              }
              // Unflip immediately
              card.classList.remove('flipped');
            });
          });
        }
        
        // Initial setup
        setupCardFlip();
        
        // Re-setup after any dynamic content changes
        const observer = new MutationObserver(() => {
          setupCardFlip();
        });
        const roomsGrid = document.querySelector('.rooms-grid');
        if (roomsGrid) {
          observer.observe(roomsGrid, { childList: true, subtree: true });
        }
        
        // Restore section visibility from localStorage
        const isSectionVisible = localStorage.getItem('availableRoomsVisible') !== 'false';
        const section = document.getElementById('availableRoomsSection');
        const icon = document.getElementById('toggleRoomsIcon');
        const text = document.getElementById('toggleRoomsText');
        if (!isSectionVisible) {
          section.style.display = 'none';
          icon.textContent = '▶';
          text.textContent = 'แสดงห้องพักที่ว่าง';
        }

        // Initialize DataTable with retry for deferred script
        function initDataTable() {
          const bookingTableEl = document.querySelector('#table-bookings');
          const availableRoomsTableEl = document.querySelector('#table-available-rooms');
          
          if (window.simpleDatatables) {
            // DataTable สำหรับรายการจอง
            if (bookingTableEl) {
              try {
                const dt = new simpleDatatables.DataTable(bookingTableEl, {
                  searchable: true,
                  fixedHeight: false,
                  perPage: 10,
                  perPageSelect: [5, 10, 25, 50, 100],
                  labels: {
                    placeholder: 'ค้นหาการจอง...',
                    perPage: '{select} รายการต่อหน้า',
                    noRows: 'ไม่มีข้อมูล',
                    info: 'แสดง {start} ถึง {end} จาก {rows} รายการ'
                  },
                  columns: [
                    { select: 6, sortable: false }
                  ]
                });
                window.__bookingDataTable = dt;
              } catch (err) {
                console.error('Failed to init booking table', err);
              }
            }
            
            // DataTable สำหรับห้องพักที่ว่าง
            if (availableRoomsTableEl) {
              try {
                const dt2 = new simpleDatatables.DataTable(availableRoomsTableEl, {
                  searchable: true,
                  fixedHeight: false,
                  perPage: 10,
                  perPageSelect: [5, 10, 25, 50, 100],
                  labels: {
                    placeholder: 'ค้นหาห้องว่าง...',
                    perPage: '{select} รายการต่อหน้า',
                    noRows: 'ไม่มีห้องว่าง',
                    info: 'แสดง {start} ถึง {end} จาก {rows} ห้อง'
                  },
                  columns: [
                    { select: 4, sortable: false }
                  ]
                });
                window.__availableRoomsDataTable = dt2;
              } catch (err) {
                console.error('Failed to init available rooms table', err);
              }
            }
          } else {
            // Retry after 100ms if simpleDatatables not loaded yet
            setTimeout(initDataTable, 100);
          }
        }
        initDataTable();

        // Restore visible rooms on load
        try {
          const saved = parseInt(localStorage.getItem('bookingVisibleRooms') || '5', 10);
          const target = isNaN(saved) ? 5 : Math.max(5, saved);
          const hiddenRooms = document.querySelectorAll('.room-card.hidden-room');
          const totalRooms = document.querySelectorAll('.room-card').length;
          let toShow = Math.min(target - 5, hiddenRooms.length);
          let shown = 0;
          hiddenRooms.forEach(room => {
            if (shown < toShow) {
              room.classList.remove('hidden-room');
              shown++;
            }
          });
          visibleRooms = Math.min(target, totalRooms);

          const remaining = totalRooms - visibleRooms;
          const remainingCountEl = document.getElementById('remainingCount');
          const loadMoreBtn = document.getElementById('loadMoreBtn');
          if (remainingCountEl) remainingCountEl.textContent = Math.max(remaining, 0);
          if (remaining <= 0 && loadMoreBtn) loadMoreBtn.classList.add('hidden');
        } catch (e) {}

        // Bind all event listeners
        if (window.bindEventListeners) {
          window.bindEventListeners();
        }
        
        // Modal click to close
        const modal = document.getElementById('bookingModal');
        if (modal) {
          modal.addEventListener('click', function(e) {
            if (e.target === this) {
              window.closeBookingModal();
            }
          });
        }
        
        // Booking form submission
        const bookingForm = document.getElementById('bookingForm');
        if (bookingForm) {
          const depositField = document.createElement('input');
          depositField.type = 'hidden';
          depositField.name = 'bkg_deposit';
          depositField.value = '2000';
          bookingForm.appendChild(depositField);

          bookingForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('../Manage/process_booking.php', {
              method: 'POST',
              body: formData,
              headers: {
                'X-Requested-With': 'XMLHttpRequest'
              }
            })
            .then(async response => {
              const raw = await response.text();
              let result;
              try {
                result = JSON.parse(raw);
              } catch (err) {
                console.error('Invalid JSON response:', raw);
                throw new Error('Invalid JSON');
              }
              if (!response.ok || !result) {
                throw new Error(result?.error || 'จองไม่สำเร็จ');
              }
              return result;
            })
            .then(result => {
              console.log('Booking response:', result);
              
              if (result.success) {
                const bookedRoomId = formData.get('room_id');
                window.closeBookingModal();
                if (typeof showSuccessToast === 'function') {
                  showSuccessToast(result.message || 'จองห้องพักสำเร็จ');
                }
                
                setTimeout(() => {
                  window.location.reload();
                }, 500);
              } else {
                if (typeof showErrorToast === 'function') {
                  showErrorToast(result.error || 'เกิดข้อผิดพลาด');
                }
              }
            })
            .catch(error => {
              console.error('Error:', error);
              if (typeof showErrorToast === 'function') {
                showErrorToast('จองไม่สำเร็จ กรุณาลองใหม่');
              }
            });
          });
        }
        
        // Set date defaults
        const today = new Date().toISOString().split('T')[0];
        const dateInputs = document.querySelectorAll('input[type="date"]');
        dateInputs.forEach(input => {
          input.min = today;
          if (!input.value) input.value = today;
          if (input.id === 'bkg_date') {
            input.readOnly = true;
            return;
          }
          const openPicker = () => {
            if (typeof input.showPicker === 'function') {
              try { input.showPicker(); } catch (e) {}
            }
          };
          input.addEventListener('focus', openPicker);
          input.addEventListener('click', openPicker);
        });

        // Theme detection
        function updateCardTheme() {
          const themeColor = getComputedStyle(document.documentElement).getPropertyValue('--theme-bg-color').trim();
          const bodyBg = getComputedStyle(document.body).backgroundColor;
          
          const isLightTheme = themeColor === '#fff' || themeColor === '#ffffff' || 
                               themeColor === 'rgb(255, 255, 255)' || themeColor === 'white' ||
                               bodyBg === 'rgb(255, 255, 255)' || bodyBg === '#fff' || bodyBg === '#ffffff';
          
          if (isLightTheme) {
            document.body.classList.add('live-light');
          } else {
            document.body.classList.remove('live-light');
          }
        }

        updateCardTheme();
        const themeObserver = new MutationObserver(() => {
          updateCardTheme();
        });
        themeObserver.observe(document.documentElement, { 
          attributes: true, 
          attributeFilter: ['style'] 
        });
        themeObserver.observe(document.body, { 
          attributes: true, 
          attributeFilter: ['style'] 
        });
      });
    </script>
    <script src="../Assets/Javascript/confirm-modal.js"></script>
    <script src="../Assets/Javascript/toast-notification.js"></script>
  </body>
</html>
