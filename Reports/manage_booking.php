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
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - จองห้องพัก</title>
    <link rel="icon" type="image/jpeg" href="../Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="../Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="../Assets/Css/main.css" />
    <link rel="stylesheet" href="../Assets/Css/confirm-modal.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4/dist/style.css" />
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

      window.__setBookingView = function(mode, event) {
        if (event) event.preventDefault();
        const roomsGrid = document.getElementById('roomsGrid');
        const roomsTable = document.getElementById('roomsTable');
        if (!roomsGrid) return false;
        const normalized = mode === 'list' ? 'list' : 'grid';
        
        if (normalized === 'list') {
          roomsGrid.style.display = 'none';
          if (roomsTable) roomsTable.style.display = 'block';
        } else {
          roomsGrid.style.display = 'grid';
          if (roomsTable) roomsTable.style.display = 'none';
        }
        
        document.querySelectorAll('.toggle-view-btn').forEach(b => b.classList.toggle('active', b.dataset.view === normalized));
        try { localStorage.setItem('bookingViewMode', normalized); } catch (e) {}
        return false;
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
    </script>
    <style>
      .booking-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
      }
      .booking-stat-card {
        background: linear-gradient(135deg, rgba(18,24,40,0.85), rgba(7,13,26,0.95));
        border-radius: 16px;
        padding: 1.25rem;
        border: 1px solid rgba(255,255,255,0.08);
        color: #f5f8ff;
        box-shadow: 0 15px 35px rgba(3,7,18,0.4);
      }
      .booking-stat-card h3 {
        margin: 0;
        font-size: 0.95rem;
        color: rgba(255,255,255,0.7);
      }
      .booking-stat-card .stat-value {
        font-size: 2.5rem;
        font-weight: 700;
        margin-top: 0.5rem;
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
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
        margin-top: 1rem;
      }
      /* Desktop: fix 3 cards per row */
      @media (min-width: 1024px) {
        .rooms-grid {
          grid-template-columns: repeat(3, minmax(0, 1fr));
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
      .room-card {
        position: relative;
        border-radius: 12px;
        background: transparent;
        box-shadow: 0 12px 30px rgba(0,0,0,0.35);
        color: #f5f8ff;
        perspective: 1200px;
        min-height: 440px;
        transition: all 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
      }
      .room-card.removing {
        opacity: 0;
        transform: scale(0.7) rotateY(90deg);
        pointer-events: none;
      }
      .rooms-grid.list-view .room-card { min-height: auto; }
      .room-card-inner {
        position: relative;
        width: 100%;
        height: 100%;
        border-radius: 12px;
        transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        transform-style: preserve-3d;
      }
      .rooms-grid.list-view .room-card-inner { height: auto; }
      .room-card:hover .room-card-inner {
        transform: rotateY(180deg);
        box-shadow: 0 16px 36px rgba(0,0,0,0.45);
        border-color: rgba(96,165,250,0.5);
        transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1) 0.3s;
      }
      /* Prevent flip when modal is open */
      body.modal-open .room-card:hover .room-card-inner {
        transform: none !important;
      }
      .rooms-grid.list-view .room-card:hover .room-card-inner,
      .rooms-grid.list-view .room-card .room-card-inner {
        transform: none;
      }
      .room-card-face {
        position: absolute;
        inset: 0;
        border-radius: 12px;
        padding: 1rem;
        background: linear-gradient(135deg, rgba(20,30,48,0.95), rgba(8,14,28,0.95));
        backface-visibility: hidden;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 0.6rem;
        border: 1px solid rgba(255,255,255,0.05);
        overflow: hidden;
      }

      /* Light mode overrides */
      body.live-light .booking-stat-card {
        background: #ffffff !important;
        color: #111827 !important;
        border: 1px solid #e5e7eb !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08) !important;
      }
      body.live-light .booking-stat-card h3 { color: #6b7280 !important; }
      body.live-light .booking-stat-card .stat-value { color: #111827 !important; }
      body.live-light .booking-stat-card .stat-chip { background: #f3f4f6 !important; color: #374151 !important; }

      body.live-light .room-card { 
        box-shadow: 0 2px 10px rgba(0,0,0,0.06) !important; 
        color: #111827 !important; 
      }
      body.live-light .room-card-face,
      body.live-light .room-card-face.front,
      body.live-light .room-card-face.back {
        background: #ffffff !important;
        border: 1px solid #e5e7eb !important;
        color: #111827 !important;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05) !important;
      }
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
      .room-card-face.front { padding-bottom: 1.25rem; }
      .rooms-grid.list-view .room-card-face { position: relative; inset: auto; min-height: 100px; height: auto; width: 100%; box-sizing: border-box; display: flex; flex-direction: row; gap: 1.2rem; align-items: center; padding: 1rem 1.5rem; }
      .rooms-grid.list-view .room-price { font-size: 1rem; line-height: 1.2; margin: 0.3rem 0; }
      .room-card-face.back {
        transform: rotateY(180deg);
        align-items: flex-start;
        gap: 0.75rem;
      }
      .rooms-grid.list-view .room-card-face.back { flex-direction: column; align-items: flex-start; display: none; }
      .room-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
      }
      .room-face-body { display: flex; flex-direction: column; gap: 0.75rem; width: 100%; }
      .rooms-grid.list-view .room-face-body { flex-direction: row; align-items: center; gap: 1.5rem; flex: 1; }
      .room-face-details { display: flex; flex-direction: column; gap: 0.5rem; flex: 1; min-width: 0; }
      .rooms-grid.list-view .room-number { min-width: 80px; }
      .rooms-grid.list-view .room-face-details { width: auto; gap: 0.2rem; }
      .room-number { font-size: 1.5rem; font-weight: bold; color: #f5f8ff; }
      .room-status { padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.875rem; background: #4caf50; color: white; }
      
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
      
      .room-info { margin: 0.2rem 0; color: rgba(255,255,255,0.75); font-size: 0.9rem; }
      .room-info-item { display: flex; justify-content: space-between; padding: 0.1rem 0; font-size: 0.9rem; }
      .room-price { font-size: 1.25rem; font-weight: bold; color: #60a5fa; margin: 0.75rem 0; }
      .room-image-container {
        margin: 0.25rem 0;
        aspect-ratio: 4 / 3;
        min-height: 250px;
        overflow: hidden;
        border-radius: 10px;
        background: linear-gradient(135deg, rgba(30,41,59,0.85), rgba(15,23,42,0.9));
        display: flex;
        align-items: center;
        justify-content: center;
      }
      .rooms-grid.list-view .room-image-container { margin: 0; width: 80px; max-width: 80px; min-height: 60px; height: 60px; flex-shrink: 0; }
      .room-image-container img { width: 100%; height: 100%; object-fit: cover; }
      .room-image-placeholder { display: grid; place-items: center; color: rgba(148,163,184,0.85); gap: 0.35rem; text-align: center; }
      .room-image-placeholder svg { width: 42px; height: 42px; opacity: 0.85; }
      .book-btn-front { display: none; }
      .rooms-grid.list-view .book-btn-front { display: inline-flex; width: 120px; justify-content: center; flex-shrink: 0; padding: 0.6rem 1rem; font-size: 0.9rem; margin-left: auto; }
      .rooms-grid.list-view .room-card-face.front { position: relative; display: flex; align-items: center; }
      .rooms-grid.list-view .room-info-item { justify-content: flex-start; gap: 0.5rem; font-size: 0.85rem; }
      .rooms-grid.list-view .room-number { font-size: 1.1rem; }
      .rooms-grid.list-view .room-face-details { gap: 0.3rem; }
      .rooms-grid.list-view .list-book-btn { margin-left: auto; }
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
                <h1>ภาพรวมผู้เช่า</h1>
                <p style="color:#94a3b8;margin-top:0.2rem;">สถิติผู้เช่าปัจจุบัน</p>
              </div>
            </div>
            <div class="booking-stats">
              <div class="booking-stat-card">
                <h3>ผู้เช่าทั้งหมด</h3>
                <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                <div class="stat-chip">
                  <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#60a5fa;"></span>
                  รวมทั้งหมด
                </div>
              </div>
              <div class="booking-stat-card">
                <h3>จองแล้ว</h3>
                <div class="stat-value"><?php echo number_format($stats['reserved']); ?></div>
                <div class="stat-chip">
                  <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#22c55e;"></span>
                  สถานะ = 1
                </div>
              </div>
              <div class="booking-stat-card">
                <h3>พักอยู่</h3>
                <div class="stat-value"><?php echo number_format($stats['checkedin']); ?></div>
                <div class="stat-chip">
                  <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#ef4444;"></span>
                  สถานะ = 2
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
                    <div class="room-card-inner">
                      <div class="room-card-face front">
                        <div class="room-card-header">
                          <span class="room-number">ห้อง <?php echo htmlspecialchars((string)$room['room_number']); ?></span>
                          <span class="room-price-header">฿<?php echo number_format((int)$room['type_price']); ?> / เดือน</span>
                          <span class="room-status">ว่าง</span>
                        </div>
                        <div class="room-face-body">
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
                          <div class="room-face-details">
                            <div class="room-info">
                              <div class="room-info-item">
                                <span>ประเภท:</span>
                                <span><strong><?php echo htmlspecialchars($room['type_name']); ?></strong></span>
                              </div>
                            </div>
                            <!-- Room price moved to header -->
                                <style>
                                  .room-price-header {
                                    font-size: 1.1rem;
                                    font-weight: bold;
                                    color: #60a5fa;
                                    margin-left: 0.5rem;
                                    margin-right: auto;
                                    background: rgba(15,23,42,0.15);
                                    border-radius: 8px;
                                    padding: 0.15em 0.7em;
                                    display: inline-block;
                                    vertical-align: middle;
                                  }
                                  .room-card-header {
                                    gap: 0.5rem;
                                  }
                                  @media (max-width: 768px) {
                                    .room-price-header {
                                      font-size: 1rem;
                                      padding: 0.1em 0.5em;
                                    }
                                  }
                                </style>
                            <div class="room-info list-book-btn">
                              <button type="button" class="book-btn book-btn-front"
                                      onclick="return window.__openBookingModal ? window.__openBookingModal(this, event) : true;"
                                      data-room-id="<?php echo $room['room_id']; ?>"
                                      data-room-number="<?php echo htmlspecialchars((string)$room['room_number']); ?>"
                                      data-room-type="<?php echo htmlspecialchars($room['type_name']); ?>"
                                      data-room-price="<?php echo number_format((int)$room['type_price']); ?>">
                                จองห้องนี้
                              </button>
                            </div>
                          </div>
                        </div>
                      </div>

                      <div class="room-card-face back">
                        <div>
                          <div class="room-number" style="display:block;">ห้อง <?php echo htmlspecialchars((string)$room['room_number']); ?></div>
                          <div style="margin-top:0.25rem; color: rgba(255,255,255,0.75);">ประเภท: <strong><?php echo htmlspecialchars($room['type_name']); ?></strong></div>
                          <div style="margin-top:0.25rem; color: rgba(255,255,255,0.75);">ราคา: ฿<?php echo number_format((int)$room['type_price']); ?> / เดือน</div>
                        </div>
                        <div style="margin-top:auto; width:100%; display:flex; gap:0.5rem;">
                          <button type="button" class="book-btn" 
                                  onclick="return window.__openBookingModal ? window.__openBookingModal(this, event) : true;"
                                  style="flex:1;"
                                  data-room-id="<?php echo $room['room_id']; ?>"
                                  data-room-number="<?php echo htmlspecialchars((string)$room['room_number']); ?>"
                                  data-room-type="<?php echo htmlspecialchars($room['type_name']); ?>"
                                  data-room-price="<?php echo number_format((int)$room['type_price']); ?>">
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
                    <th>รหัสการจอง</th>
                    <th>หมายเลขห้อง</th>
                    <th>ประเภทห้อง</th>
                    <th>ผู้จอง</th>
                    <th>วันที่จอง</th>
                    <th>วันที่เข้าพัก</th>
                    <th>สถานะ</th>
                    <th>ราคา</th>
                    <th class="crud-column">จัดการ</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($bookings)): ?>
                    <tr>
                      <td colspan="9" style="text-align: center; padding: 2rem; color: #666;">
                        ยังไม่มีรายการจองในระบบ
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach($bookings as $bkg): ?>
                      <tr>
                        <td><?php echo htmlspecialchars((string)$bkg['bkg_id']); ?></td>
                        <td><?php echo !empty($bkg['room_number']) ? htmlspecialchars((string)$bkg['room_number']) : '-'; ?></td>
                        <td><?php echo htmlspecialchars($bkg['type_name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($bkg['tnt_name'] ?? '-'); ?></td>
                        <td><?php echo !empty($bkg['bkg_date']) ? date('d/m/Y', strtotime($bkg['bkg_date'])) : '-'; ?></td>
                        <td><?php echo !empty($bkg['bkg_checkin_date']) ? date('d/m/Y', strtotime($bkg['bkg_checkin_date'])) : '-'; ?></td>
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
                            <?php echo $statusMap[$bkg['bkg_status']] ?? 'ไม่ระบุ'; ?>
                          </span>
                        </td>
                        <td>฿<?php echo number_format((int)($bkg['type_price'] ?? 0)); ?></td>
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
            if (btn) {
              btn.setAttribute('aria-expanded', sidebar.classList.contains('mobile-open').toString());
            }
          } else {
            const collapsed = sidebar.classList.toggle('collapsed');
            try { localStorage.setItem(SIDEBAR_KEY, collapsed.toString()); } catch (e) {}
            document.querySelectorAll('#sidebar-toggle, [data-sidebar-toggle]').forEach(b => b.setAttribute('aria-expanded', (!collapsed).toString()));
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
            const expanded = sidebar.classList.contains('mobile-open').toString();
            document.querySelectorAll('#sidebar-toggle, [data-sidebar-toggle]').forEach(b => b.setAttribute('aria-expanded', expanded));
          } else {
            const collapsed = sidebar.classList.toggle('collapsed');
            safeSet('sidebarCollapsed', collapsed.toString());
            document.querySelectorAll('#sidebar-toggle, [data-sidebar-toggle]').forEach(b => b.setAttribute('aria-expanded', (!collapsed).toString()));
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

        // Initialize DataTable
        const bookingTableEl = document.querySelector('#table-bookings');
        if (bookingTableEl && window.simpleDatatables) {
          try {
            const dt = new simpleDatatables.DataTable(bookingTableEl, {
              searchable: true,
              fixedHeight: false,
              perPage: 5,
              perPageSelect: [5, 10, 25, 50, 100],
              labels: {
                placeholder: 'ค้นหา...',
                perPage: '{select} แถวต่อหน้า',
                noRows: 'ไม่มีข้อมูล',
                info: 'แสดง {start}–{end} จาก {rows} รายการ'
              },
              columns: [
                { select: 7, sortable: false }
              ]
            });
            window.__bookingDataTable = dt;
          } catch (err) {
            console.error('Failed to init booking table', err);
          }
        }

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
