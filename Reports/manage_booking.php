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

// ดึงข้อมูลการจองทั้งหมด
$stmtBookings = $pdo->query("
  SELECT b.*, r.room_number, rt.type_name, rt.type_price
  FROM booking b
  LEFT JOIN room r ON b.room_id = r.room_id
  LEFT JOIN roomtype rt ON r.type_id = rt.type_id
  WHERE b.bkg_status <> '0'
  ORDER BY $orderBy
");
$bookings = $stmtBookings->fetchAll(PDO::FETCH_ASSOC);

// คำนวณสถิติ
$stats = [
  'total' => 0,
  'booked' => 0,
  'checkedin' => 0,
];
foreach ($bookings as $bkg) {
  $status = (string)($bkg['bkg_status'] ?? '');
  if ($status !== '0') {
    $stats['total']++;
    if ($status === '1') {
      $stats['booked']++;
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
      /* Desktop: fix 5 cards per row when there is space */
      @media (min-width: 1200px) {
        .rooms-grid {
          grid-template-columns: repeat(5, minmax(0, 1fr));
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
        overflow-y: auto;
        color: #f5f8ff;
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
                <h3>พักอยู่</h3>
                <div class="stat-value"><?php echo number_format($stats['booked']); ?></div>
                <div class="stat-chip">
                  <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#22c55e;"></span>
                  สถานะ = 1
                </div>
              </div>
              <div class="booking-stat-card">
                <h3>ย้ายออก</h3>
                <div class="stat-value"><?php echo number_format($stats['checkedin']); ?></div>
                <div class="stat-chip">
                  <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#ef4444;"></span>
                  สถานะ = 0
                </div>
              </div>
            </div>
          </section>

          <!-- ส่วนแสดงห้องว่าง -->
          <section class="manage-panel booking-section">
            <div class="section-header">
              <div>
                <h1>ห้องพักที่ว่าง</h1>
              </div>
              <div class="view-toggle">
                <button type="button" class="toggle-view-btn active" data-view="grid">Grid</button>
                <button type="button" class="toggle-view-btn" data-view="list">List</button>
              </div>
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
                      <td colspan="8" style="text-align: center; padding: 2rem; color: #666;">
                        ยังไม่มีรายการจองในระบบ
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach($bookings as $bkg): ?>
                      <tr>
                        <td><?php echo htmlspecialchars((string)$bkg['bkg_id']); ?></td>
                        <td><?php echo !empty($bkg['room_number']) ? htmlspecialchars((string)$bkg['room_number']) : '-'; ?></td>
                        <td><?php echo htmlspecialchars($bkg['type_name'] ?? '-'); ?></td>
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
        <h2>จองห้องพัก</h2>
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
    <script>
      // รอให้ DOM โหลดเสร็จ
      document.addEventListener('DOMContentLoaded', function() {
        console.log('Page loaded');
        const roomsGrid = document.getElementById('roomsGrid');
        const viewToggleButtons = document.querySelectorAll('.toggle-view-btn');
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

        const savedView = localStorage.getItem(VIEW_KEY) || 'grid';
        applyViewMode(savedView);

        viewToggleButtons.forEach(btn => {
          btn.addEventListener('click', () => {
            const mode = btn.dataset.view === 'list' ? 'list' : 'grid';
            applyViewMode(mode);
            localStorage.setItem(VIEW_KEY, mode);
          });
        });
        
        // เปิด modal สำหรับจองห้อง
        document.querySelectorAll('.book-btn').forEach(btn => {
          btn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Book button clicked');
            
            const roomId = this.dataset.roomId;
            const roomNumber = this.dataset.roomNumber;
            const roomType = this.dataset.roomType;
            const roomPrice = this.dataset.roomPrice;
            
            console.log('Room data:', {roomId, roomNumber, roomType, roomPrice});
            
            document.getElementById('modal_room_id').value = roomId;
            document.getElementById('modal_room_number').value = 'ห้อง ' + roomNumber;
            document.getElementById('modal_room_type').value = roomType;
            document.getElementById('modal_room_price').value = '฿' + roomPrice;
            
            document.getElementById('bookingModal').classList.add('active');
            document.body.classList.add('modal-open');
          });
        });
        
        // ปิด modal เมื่อคลิกนอก content (เฉพาะที่ background เท่านั้น)
        const modal = document.getElementById('bookingModal');
        if (modal) {
          modal.addEventListener('click', function(e) {
            // ตรวจสอบว่าคลิกที่ background (ไม่ใช่ content)
            if (e.target === this) {
              closeBookingModal();
            }
          });
        }
        
        // ป้องกันการปิด modal เมื่อกดปุ่ม submit
        const bookingForm = document.getElementById('bookingForm');
        if (bookingForm) {
          // ใส่ค่ามัดจำคงที่ 2000 ไปกับฟอร์ม (field เดิมไม่มีให้กรอก)
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
                closeBookingModal();
                if (typeof showSuccessToast === 'function') {
                  showSuccessToast(result.message || 'จองห้องพักสำเร็จ');
                }
                
                // รีเฟรชข้อมูลโดยไม่รีโหลดหน้า พร้อม animation
                setTimeout(() => {
                  refreshBookingData(bookedRoomId);
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
        
        // ตั้งค่า default date: วันที่จอง = วันนี้และปิดการเลือก, วันที่เข้าพัก = วันนี้ขึ้นไป พร้อมเปิด picker ให้ง่าย
        const today = new Date().toISOString().split('T')[0];
        const dateInputs = document.querySelectorAll('input[type="date"]');
        dateInputs.forEach(input => {
          input.min = today;
          if (!input.value) input.value = today;
          // สำหรับวันที่จอง (bkg_date) ไม่ต้องให้เลือกเอง
          if (input.id === 'bkg_date') {
            input.readOnly = true;
            return;
          }
          // เปิด native picker ทันทีเมื่อคลิก/โฟกัส (ถ้าซัพพอร์ต showPicker)
          const openPicker = () => {
            if (typeof input.showPicker === 'function') {
              try { input.showPicker(); } catch (e) {}
            }
          };
          input.addEventListener('focus', openPicker);
          input.addEventListener('click', openPicker);
        });
      });
      
      // Load More Rooms Function
      let visibleRooms = 5;
      const ROOMS_PER_LOAD = 5;
      
      function loadMoreRooms() {
        const hiddenRooms = document.querySelectorAll('.room-card.hidden-room');
        const totalRooms = document.querySelectorAll('.room-card').length;
        let showCount = 0;
        
        hiddenRooms.forEach((room, index) => {
          if (showCount < ROOMS_PER_LOAD) {
            room.classList.remove('hidden-room');
            showCount++;
            visibleRooms++;
          }
        });
        
        // จดจำจำนวนการ์ดที่แสดงไว้
        try {
          localStorage.setItem('bookingVisibleRooms', String(visibleRooms));
        } catch (e) {}
        
        // Update remaining count
        const remaining = totalRooms - visibleRooms;
        const remainingCountEl = document.getElementById('remainingCount');
        const loadMoreBtn = document.getElementById('loadMoreBtn');
        
        if (remaining > 0) {
          remainingCountEl.textContent = remaining;
        } else {
          // Hide button when all rooms are shown
          loadMoreBtn.classList.add('hidden');
        }
      }

      // Restore visible rooms on load
      document.addEventListener('DOMContentLoaded', () => {
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
      });


      
      // ฟังก์ชันรีเฟรชข้อมูล
      function refreshBookingData(bookedRoomId = null) {
        fetch(window.location.href)
          .then(response => response.text())
          .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // อัพเดทสถิติ
            const statCards = document.querySelectorAll('.booking-stat-card .stat-value');
            const newStats = doc.querySelectorAll('.booking-stat-card .stat-value');
            
            if (statCards.length === newStats.length) {
              statCards.forEach((card, index) => {
                card.textContent = newStats[index].textContent;
              });
            }
            
            // อัพเดทตารางการจอง (รายการจองทั้งหมด)
            const newBookingTable = doc.querySelector('#table-bookings tbody');
            const currentBookingTable = document.querySelector('#table-bookings tbody');
            if (newBookingTable && currentBookingTable) {
              currentBookingTable.innerHTML = newBookingTable.innerHTML;
            }
            
            // อัพเดทรายการห้องว่าง with animation
            const newRoomsGrid = doc.querySelector('#roomsGrid');
            const currentRoomsGrid = document.querySelector('#roomsGrid');
            if (newRoomsGrid && currentRoomsGrid) {
              // ถ้ามีห้องที่ถูกจอง ให้ทำ animation ก่อน
              if (bookedRoomId) {
                const bookedCard = currentRoomsGrid.querySelector(`[data-room-id="${bookedRoomId}"]`)?.closest('.room-card');
                if (bookedCard) {
                  bookedCard.classList.add('removing');
                  // รอ animation เสร็จก่อนอัพเดท DOM
                  setTimeout(() => {
                    updateRoomsGrid(newRoomsGrid, currentRoomsGrid);
                  }, 600); // ตรงกับเวลาใน CSS transition
                  return;
                }
              }
              // ถ้าไม่มี animation ให้อัพเดทเลย
              updateRoomsGrid(newRoomsGrid, currentRoomsGrid);
            }
          })
          .catch(error => {
            console.error('Error refreshing data:', error);
          });
      }
      
      // ฟังก์ชันช่วยอัพเดท rooms grid
      function updateRoomsGrid(newRoomsGrid, currentRoomsGrid) {
        currentRoomsGrid.innerHTML = newRoomsGrid.innerHTML;
        
        // ต้องผูก event listener ใหม่อีกครั้ง
        document.querySelectorAll('.book-btn').forEach(btn => {
          btn.addEventListener('click', function(e) {
            e.preventDefault();
            const roomId = this.dataset.roomId;
            const roomNumber = this.dataset.roomNumber;
            const roomType = this.dataset.roomType;
            const roomPrice = this.dataset.roomPrice;
            
            document.getElementById('modal_room_id').value = roomId;
            document.getElementById('modal_room_number').value = 'ห้อง ' + roomNumber;
            document.getElementById('modal_room_type').value = roomType;
            document.getElementById('modal_room_price').value = '฿' + roomPrice;
            
            document.getElementById('bookingModal').classList.add('active');
            document.body.classList.add('modal-open');
          });
        });
      }
      
      // ปิด modal
      function closeBookingModal() {
        const modal = document.getElementById('bookingModal');
        const form = document.getElementById('bookingForm');
        
        modal.classList.remove('active');
        document.body.classList.remove('modal-open');
        if (form) {
          form.reset();
        }
      }
      
      // อัพเดทสถานะการจอง
      async function updateBookingStatus(bookingId, newStatus) {
        const statusText = newStatus === '2' ? 'เข้าพัก' : 'ยกเลิก';
        const confirmed = await showConfirmDialog(
          `ยืนยันการ${statusText}การจอง`,
          `คุณต้องการเปลี่ยนสถานะการจองนี้เป็น <strong>"${statusText}"</strong> หรือไม่?`
        );
        
        if (confirmed) {
          const form = document.createElement('form');
          form.method = 'POST';
          form.action = '../Manage/update_booking_status.php';
          
          const inputId = document.createElement('input');
          inputId.type = 'hidden';
          inputId.name = 'bkg_id';
          inputId.value = bookingId;
          
          const inputStatus = document.createElement('input');
          inputStatus.type = 'hidden';
          inputStatus.name = 'bkg_status';
          inputStatus.value = newStatus;
          
          form.appendChild(inputId);
          form.appendChild(inputStatus);
          document.body.appendChild(form);
          form.submit();
        }
      }

      // ฟังก์ชันสำหรับตรวจจับและปรับสีการ์ดตาม theme
      function updateCardTheme() {
        const themeColor = getComputedStyle(document.documentElement).getPropertyValue('--theme-bg-color').trim();
        const bodyBg = getComputedStyle(document.body).backgroundColor;
        
        // ตรวจสอบว่าเป็นสีขาวหรือสีอ่อน
        const isLightTheme = themeColor === '#fff' || themeColor === '#ffffff' || 
                             themeColor === 'rgb(255, 255, 255)' || themeColor === 'white' ||
                             bodyBg === 'rgb(255, 255, 255)' || bodyBg === '#fff' || bodyBg === '#ffffff';
        
        if (isLightTheme) {
          document.body.classList.add('live-light');
        } else {
          document.body.classList.remove('live-light');
        }
      }

      // เรียกใช้เมื่อโหลดหน้า
      document.addEventListener('DOMContentLoaded', () => {
        updateCardTheme();
        
        // ตรวจสอบการเปลี่ยน theme color (ใช้ MutationObserver)
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
    <script>
      function changeSortBy(sortValue) {
        const url = new URL(window.location);
        url.searchParams.set('sort', sortValue);
        window.location.href = url.toString();
      }
    </script>
  </body>
</html>
