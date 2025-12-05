<?php
declare(strict_types=1);
session_start();
header('Content-Type: text/html; charset=utf-8');
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

// ซิงก์สถานะห้องอัตโนมัติจากสัญญา (ctr_status 0/2 = มีผู้เช่า, 1 = ยกเลิก)
try {
  $pdo->exec("UPDATE room SET room_status = '0'");
  $pdo->exec("UPDATE room SET room_status = '1' WHERE EXISTS (SELECT 1 FROM contract c WHERE c.room_id = room.room_id AND c.ctr_status IN ('0','2'))");
} catch (PDOException $e) {
  // ถ้าซิงก์ไม่สำเร็จ ให้ไปต่อแต่แสดงสถานะตามข้อมูลเดิม
}

// หากมีการส่งฟอร์มเพิ่มห้องจากหน้านี้ ให้บันทึกลงฐานข้อมูล (สถานะกำหนดอัตโนมัติ)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['room_number'], $_POST['type_id'])) {
  $roomNumber = trim($_POST['room_number']);
  $typeId = (int)($_POST['type_id'] ?? 0);
  // ห้องใหม่เริ่มต้นเป็นว่าง (0)
  $roomStatus = '0';
  $roomImage = '';

  if ($roomNumber === '' || $typeId <= 0) {
    $_SESSION['error'] = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    header('Location: manage_rooms.php');
    exit;
  }

  try {
    // ตรวจสอบห้องซ้ำ
    $stmtCheck = $pdo->prepare('SELECT room_id FROM room WHERE room_number = ?');
    $stmtCheck->execute([$roomNumber]);
    if ($stmtCheck->fetch()) {
      $_SESSION['error'] = 'หมายเลขห้องนี้มีอยู่แล้ว';
      header('Location: manage_rooms.php');
      exit;
    }

    // อัปโหลดรูปถ้ามี
    if (!empty($_FILES['room_image']['name'])) {
      $file = $_FILES['room_image'];
      $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
      if (in_array($file['type'], $allowed, true)) {
        $uploadDir = __DIR__ . '/../Assets/Images/Rooms/';
        if (!is_dir($uploadDir)) {
          mkdir($uploadDir, 0755, true);
        }
        $filename = time() . '_' . basename($file['name']);
        $filepath = $uploadDir . $filename;
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
          $roomImage = $filename;
        }
      }
    }

    // บันทึกข้อมูลห้อง
    $stmtInsert = $pdo->prepare('INSERT INTO room (room_number, type_id, room_status, room_image) VALUES (?, ?, ?, ?)');
    $stmtInsert->execute([$roomNumber, $typeId, $roomStatus, $roomImage]);

    $_SESSION['success'] = 'เพิ่มห้องพัก ห้อง ' . htmlspecialchars($roomNumber) . ' เรียบร้อยแล้ว';
    header('Location: manage_rooms.php');
    exit;
  } catch (PDOException $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    header('Location: manage_rooms.php');
    exit;
  }
}

// ดึงข้อมูลห้องพัก
$stmt = $pdo->query("
    SELECT r.room_id, r.room_number, r.room_status, r.room_image, r.type_id, rt.type_name, rt.type_price
    FROM room r
    LEFT JOIN roomtype rt ON r.type_id = rt.type_id
    ORDER BY CAST(r.room_number AS UNSIGNED) ASC
");
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// หาหมายเลขห้องถัดไป (padding 2 หลัก)
$maxRoomNumber = 0;
foreach ($rooms as $room) {
  $num = (int)($room['room_number'] ?? 0);
  if ($num > $maxRoomNumber) {
    $maxRoomNumber = $num;
  }
}
$nextRoomNumber = str_pad((string)($maxRoomNumber + 1), 2, '0', STR_PAD_LEFT);

// ดึงประเภทห้อง
$stmt = $pdo->query("SELECT type_id, type_name, type_price FROM roomtype ORDER BY type_name ASC");
$roomTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$defaultTypeId = $roomTypes[0]['type_id'] ?? '';

// นับสถานะห้อง
$vacant = 0;
$occupied = 0;
foreach ($rooms as $room) {
  if ($room['room_status'] === '0') {
    $vacant++;
  } else {
    $occupied++;
  }
}

$totalRooms = count($rooms);
?>
<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>จัดการห้องพัก</title>
    <link rel="stylesheet" href="../Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="../Assets/Css/main.css" />
    <link rel="stylesheet" href="../Assets/Css/confirm-modal.css" />
    <style>
      .rooms-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
      }
      .room-stat-card {
        background: linear-gradient(135deg, rgba(18,24,40,0.85), rgba(7,13,26,0.95));
        border-radius: 16px;
        padding: 1.25rem;
        border: 1px solid rgba(255,255,255,0.08);
        color: #f5f8ff;
        box-shadow: 0 15px 35px rgba(3,7,18,0.4);
      }
      .room-stat-card h3 {
        margin: 0;
        font-size: 0.95rem;
        color: rgba(255,255,255,0.7);
      }
      .room-stat-card .stat-value {
        font-size: 2.5rem;
        font-weight: 700;
        margin-top: 0.5rem;
        color: #60a5fa;
      }
      .rooms-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
      }
      .room-card {
        background: linear-gradient(135deg, rgba(30,41,59,0.6), rgba(15,23,42,0.8));
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 12px;
        overflow: hidden;
        transition: all 0.3s ease;
        cursor: pointer;
      }
      .room-card:hover {
        border-color: rgba(96,165,250,0.4);
        box-shadow: 0 8px 24px rgba(96,165,250,0.15);
        transform: translateY(-2px);
      }
      .room-card-image {
        width: 100%;
        height: 140px;
        background: linear-gradient(135deg, #1e293b, #0f172a);
        display: flex;
        align-items: center;
        justify-content: center;
        color: rgba(255,255,255,0.4);
        font-size: 3rem;
        overflow: hidden;
      }
      .room-card-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
      }
      .room-card-content {
        padding: 1rem;
      }
      .room-card-number {
        font-size: 1.4rem;
        font-weight: 700;
        color: #f5f8ff;
        margin: 0 0 0.5rem 0;
      }
      .room-card-meta {
        font-size: 0.85rem;
        color: #cbd5e1;
        margin-bottom: 0.75rem;
      }
      .room-card-status {
        display: inline-block;
        padding: 0.35rem 0.75rem;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        margin-bottom: 0.75rem;
      }
      .room-card-status.vacant {
        background: rgba(34,197,94,0.2);
        color: #22c55e;
      }
      .room-card-status.occupied {
        background: rgba(239,68,68,0.2);
        color: #ef4444;
      }
      .room-card-actions {
        display: flex;
        gap: 0.5rem;
      }
      .room-empty {
        text-align: center;
        padding: 3rem 1rem;
        color: #64748b;
      }
      .room-empty-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.5;
      }
      .room-form {
        display: grid;
        gap: 1rem;
        margin-top: 1.5rem;
      }
      .room-form-group label {
        color: rgba(255,255,255,0.8);
        font-weight: 600;
        display: block;
        margin-bottom: 0.4rem;
      }
      .room-form-group input,
      .room-form-group select {
        width: 100%;
        padding: 0.75rem 0.85rem;
        border-radius: 10px;
        border: 1px solid rgba(255,255,255,0.15);
        background: rgba(8,12,24,0.85);
        color: #f5f8ff;
        font-family: inherit;
      }
      .add-type-row { display:flex; align-items:center; gap:0.5rem; }
      .add-type-btn {
        padding: 0.65rem 0.9rem;
        border-radius: 10px;
        border: 1px dashed rgba(96,165,250,0.6);
        background: rgba(15,23,42,0.7);
        color: #60a5fa;
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
        transition: background 0.2s ease, border-color 0.2s ease;
      }
      .add-type-btn:hover {
        background: rgba(37,99,235,0.15);
        border-color: rgba(96,165,250,0.95);
      }
      .delete-type-btn {
        border-color: rgba(248,113,113,0.6);
        color: #fca5a5;
      }
      .delete-type-btn:hover {
        background: rgba(248,113,113,0.15);
        border-color: rgba(248,113,113,0.9);
      }
      .room-form-group input:focus,
      .room-form-group select:focus {
        outline: none;
        border-color: #60a5fa;
        box-shadow: 0 0 0 3px rgba(96,165,250,0.25);
      }
      .room-form-actions {
        display: flex;
        gap: 0.75rem;
        margin-top: 1rem;
      }
      
      /* Edit Modal Styles */
      .booking-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.85);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 10000;
        padding: 1rem;
      }
      .booking-modal-content {
        background: linear-gradient(135deg, rgba(15,23,42,0.98), rgba(2,6,23,0.98));
        border-radius: 16px;
        padding: 2rem;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        border: 1px solid rgba(96,165,250,0.3);
        box-shadow: 0 20px 60px rgba(0,0,0,0.5);
      }
      .booking-modal-content h2 {
        margin: 0 0 1.5rem 0;
        color: #f5f8ff;
        font-size: 1.5rem;
      }
      .booking-form-group {
        margin-bottom: 1.25rem;
      }
      .booking-form-group label {
        display: block;
        color: rgba(255,255,255,0.85);
        font-weight: 600;
        margin-bottom: 0.5rem;
      }
      .booking-form-group input,
      .booking-form-group select,
      .booking-form-group textarea {
        width: 100%;
        padding: 0.75rem 0.85rem;
        border-radius: 10px;
        border: 1px solid rgba(255,255,255,0.2);
        background: rgba(8,12,24,0.9);
        color: #f5f8ff;
        font-family: inherit;
        font-size: 0.95rem;
      }
      .booking-form-group input:focus,
      .booking-form-group select:focus,
      .booking-form-group textarea:focus {
        outline: none;
        border-color: #60a5fa;
        box-shadow: 0 0 0 3px rgba(96,165,250,0.25);
      }
      .booking-form-actions {
        display: flex;
        gap: 0.75rem;
        margin-top: 1.5rem;
      }
      .btn-submit {
        flex: 1;
        padding: 0.85rem 1.5rem;
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
      }
      .btn-submit:hover {
        background: linear-gradient(135deg, #1d4ed8, #1e40af);
        box-shadow: 0 4px 12px rgba(37,99,235,0.4);
        transform: translateY(-1px);
      }
      .btn-cancel {
        flex: 1;
        padding: 0.85rem 1.5rem;
        background: rgba(100,116,139,0.2);
        color: #cbd5e1;
        border: 1px solid rgba(148,163,184,0.3);
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
      }
      .btn-cancel:hover {
        background: rgba(100,116,139,0.3);
        border-color: rgba(148,163,184,0.5);
      }
      
      /* View Toggle Button */
      .view-toggle-btn {
        padding: 0.6rem 1.2rem;
        background: rgba(37,99,235,0.15);
        color: #60a5fa;
        border: 1px solid rgba(96,165,250,0.3);
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
      }
      .view-toggle-btn:hover {
        background: rgba(37,99,235,0.25);
        border-color: rgba(96,165,250,0.5);
      }
      
      /* Table View Styles */
      .rooms-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1rem;
      }
      .rooms-table thead {
        background: rgba(15,23,42,0.6);
      }
      .rooms-table th {
        padding: 1rem;
        text-align: left;
        color: #f5f8ff;
        font-weight: 600;
        border-bottom: 2px solid rgba(96,165,250,0.3);
      }
      .rooms-table td {
        padding: 0.65rem 1rem;
        color: #cbd5e1;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        font-size: 0.9rem;
      }
      .rooms-table tbody tr {
        transition: background 0.2s ease;
      }
      .rooms-table tbody tr:hover {
        background: rgba(30,41,59,0.5);
      }
      .rooms-table tbody tr.hidden-row {
        display: none;
      }
      .room-image-small {
        width: 45px;
        height: 45px;
        border-radius: 6px;
        object-fit: cover;
        background: linear-gradient(135deg, #1e293b, #0f172a);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
      }
      .room-image-small img {
        width: 100%;
        height: 100%;
        border-radius: 8px;
        object-fit: cover;
      }
      .reports-page .manage-panel { margin-top: 1.4rem; margin-bottom: 1.4rem; background: #0f172a; border: 1px solid rgba(148,163,184,0.2); box-shadow: 0 12px 30px rgba(0,0,0,0.2); }
      .reports-page .manage-panel:first-of-type { margin-top: 0.2rem; }
      .rooms-table .room-card-actions {
        display: flex;
        gap: 0.5rem;
        justify-content: flex-start;
      }
      .rooms-table-view { display: none; }
      .rooms-grid-view { display: grid; }
      
      .load-more-container {
        display: flex;
        justify-content: center;
        margin-top: 1.5rem;
        padding-top: 1rem;
        border-top: 1px solid rgba(255,255,255,0.1);
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
  <body class="reports-page" data-disable-edit-modal="true">
    <div class="app-shell">
      <?php include __DIR__ . '/../includes/sidebar.php'; ?>
      <main class="app-main">
        <div>
          <?php 
            $pageTitle = 'จัดการห้องพัก';
            include __DIR__ . '/../includes/page_header.php'; 
          ?>

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

          <section class="manage-panel">
            <div class="rooms-stats">
              <div class="room-stat-card">
                <h3>ห้องทั้งหมด</h3>
                <div class="stat-value"><?php echo number_format($totalRooms); ?></div>
              </div>
              <div class="room-stat-card">
                <h3>ห้องว่าง</h3>
                <div class="stat-value" style="color:#22c55e;"><?php echo number_format($vacant); ?></div>
              </div>
              <div class="room-stat-card">
                <h3>ห้องที่มีผู้เข้าอยู่</h3>
                <div class="stat-value" style="color:#ef4444;"><?php echo number_format($occupied); ?></div>
              </div>
            </div>
          </section>

          <section class="manage-panel" style="background:linear-gradient(135deg, rgba(15,23,42,0.95), rgba(2,6,23,0.95)); color:#f8fafc;">
            <div class="section-header">
              <div>
                <h1>เพิ่มห้องพัก</h1>
                <p style="margin-top:0.25rem;color:rgba(255,255,255,0.7);">สร้างห้องพัก</p>
              </div>
            </div>
            <form action="manage_rooms.php" method="post" enctype="multipart/form-data">
              <div class="room-form">
                <div class="room-form-group">
                  <label for="room_number">หมายเลขห้อง <span style="color:#f87171;">*</span></label>
                  <input type="text" id="room_number" name="room_number" required maxlength="2" placeholder="เช่น 01, 02, ..." value="<?php echo htmlspecialchars($nextRoomNumber); ?>" />
                </div>
                <div class="room-form-group" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                  <div>
                    <div class="add-type-row">
                      <label for="type_id" style="margin:0;">ประเภทห้อง <span style="color:#f87171;">*</span></label>
                      <button type="button" class="add-type-btn" id="addTypeBtn">+ เพิ่มประเภทห้อง</button>
                      <button type="button" class="add-type-btn delete-type-btn" id="deleteTypeBtn">ลบประเภทห้อง</button>
                    </div>
                    <select id="type_id" name="type_id" required>
                      <?php foreach ($roomTypes as $index => $type): ?>
                        <option value="<?php echo $type['type_id']; ?>" <?php echo ($index === 0 ? 'selected' : ''); ?>>
                          <?php echo htmlspecialchars($type['type_name']); ?> (<?php echo number_format($type['type_price']); ?> บาท/เดือน)
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                   <div>
                     <label style="display:block;">สถานะห้อง</label>
                     <div style="padding:0.9rem 0.85rem; border-radius:10px; border:1px dashed rgba(255,255,255,0.25); color:#cbd5e1;">
                       ระบบจะปรับสถานะอัตโนมัติ เมื่อมีการจอง/ทำสัญญา (ว่าง = 0, ไม่ว่าง = 1)
                     </div>
                   </div>
                </div>
                <div class="room-form-group">
                  <label for="room_image">รูปภาพห้อง</label>
                  <input type="file" id="room_image" name="room_image" accept="image/*" />
                </div>
                <div class="room-form-actions">
                  <button type="submit" class="animate-ui-add-btn" data-allow-submit="true" data-animate-ui-skip="true" data-no-modal="true" style="flex:2;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></svg>
                    เพิ่มห้องพัก
                  </button>
                  <button type="reset" class="animate-ui-action-btn delete" style="flex:1;">ล้างข้อมูล</button>
                </div>
              </div>
            </form>
          </section>

          <section class="manage-panel">
            <div class="section-header" style="display:flex;justify-content:space-between;align-items:center;">
              <div>
                <h1>รายการห้องพัก</h1>
                <p style="color:#94a3b8;margin-top:0.2rem;">ห้องพักและข้อมูลทั้งหมด</p>
              </div>
              <button type="button" class="view-toggle-btn" id="viewToggleBtn" onclick="toggleView()">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                <span id="viewToggleText">มุมมองแถว</span>
              </button>
            </div>
            
            <?php if (empty($rooms)): ?>
              <div class="room-empty">
                <div class="room-empty-icon">🛏️</div>
                <h3>ยังไม่มีห้องพัก</h3>
                <p>เริ่มต้นเพิ่มห้องพัก</p>
              </div>
            <?php else: ?>
              <!-- Grid View -->
              <div class="rooms-grid rooms-grid-view" id="roomsGrid">
                <?php foreach ($rooms as $room): ?>
                  <div class="room-card">
                    <div class="room-card-image">
                      <?php if (!empty($room['room_image'])): ?>
                        <img src="../Assets/Images/Rooms/<?php echo htmlspecialchars($room['room_image']); ?>" alt="ห้อง <?php echo htmlspecialchars($room['room_number']); ?>" />
                      <?php else: ?>
                        <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M4 12h16a2 2 0 0 1 2 2v4H2v-4a2 2 0 0 1 2-2Z" />
                          <path d="M6 12V7a2 2 0 0 1 2-2h2" />
                          <path d="M2 16v-2" />
                          <path d="M22 16v-2" />
                        </svg>
                      <?php endif; ?>
                    </div>
                    <div class="room-card-content">
                      <h3 class="room-card-number">ห้อง <?php echo htmlspecialchars($room['room_number']); ?></h3>
                      <div class="room-card-meta">
                        ประเภท: <?php echo htmlspecialchars($room['type_name'] ?? '-'); ?><br>
                        ราคา: <?php echo number_format($room['type_price'] ?? 0); ?> บาท/เดือน
                      </div>
                      <div class="room-card-status <?php echo $room['room_status'] === '0' ? 'vacant' : 'occupied'; ?>">
                        <?php echo $room['room_status'] === '0' ? '✓ ว่าง' : '✗ ไม่ว่าง'; ?>
                      </div>
                      <div class="room-card-actions">
                        <button type="button" class="animate-ui-action-btn edit" data-room-id="<?php echo $room['room_id']; ?>" data-animate-ui-skip="true" data-no-modal="true" data-allow-submit="true" onclick="editRoom(<?php echo $room['room_id']; ?>)">แก้ไข</button>
                        <button type="button" class="animate-ui-action-btn delete" onclick="deleteRoom(<?php echo $room['room_id']; ?>, '<?php echo htmlspecialchars(addslashes($room['room_number'])); ?>')">ลบ</button>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              
              <!-- Table View -->
              <div class="rooms-table-view" id="roomsTable">
                <table class="rooms-table">
                  <thead>
                    <tr>
                      <th>รูปภาพ</th>
                      <th>หมายเลขห้อง</th>
                      <th>ประเภท</th>
                      <th>ราคา/เดือน</th>
                      <th>สถานะ</th>
                      <th>จัดการ</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($rooms as $index => $room): ?>
                      <tr class="room-row <?php echo $index >= 5 ? 'hidden-row' : ''; ?>" data-index="<?php echo $index; ?>">
                        <td>
                          <div class="room-image-small">
                            <?php if (!empty($room['room_image'])): ?>
                              <img src="../Assets/Images/Rooms/<?php echo htmlspecialchars($room['room_image']); ?>" alt="ห้อง <?php echo htmlspecialchars($room['room_number']); ?>" />
                            <?php else: ?>
                              <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 12h16a2 2 0 0 1 2 2v4H2v-4a2 2 0 0 1 2-2Z" />
                                <path d="M6 12V7a2 2 0 0 1 2-2h2" />
                                <path d="M2 16v-2" />
                                <path d="M22 16v-2" />
                              </svg>
                            <?php endif; ?>
                          </div>
                        </td>
                        <td style="font-weight:600;color:#f5f8ff;">ห้อง <?php echo htmlspecialchars($room['room_number']); ?></td>
                        <td><?php echo htmlspecialchars($room['type_name'] ?? '-'); ?></td>
                        <td><?php echo number_format($room['type_price'] ?? 0); ?> บาท</td>
                        <td>
                          <span class="room-card-status <?php echo $room['room_status'] === '0' ? 'vacant' : 'occupied'; ?>">
                            <?php echo $room['room_status'] === '0' ? '✓ ว่าง' : '✗ ไม่ว่าง'; ?>
                          </span>
                        </td>
                        <td>
                          <div class="room-card-actions">
                            <button type="button" class="animate-ui-action-btn edit" data-room-id="<?php echo $room['room_id']; ?>" data-animate-ui-skip="true" data-no-modal="true" data-allow-submit="true" onclick="editRoom(<?php echo $room['room_id']; ?>)">แก้ไข</button>
                            <button type="button" class="animate-ui-action-btn delete" onclick="deleteRoom(<?php echo $room['room_id']; ?>, '<?php echo htmlspecialchars(addslashes($room['room_number'])); ?>')">ลบ</button>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
                <?php if (count($rooms) > 5): ?>
                <div class="load-more-container">
                  <button type="button" class="load-more-btn" id="loadMoreBtn" onclick="loadMoreRooms()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                    <span id="loadMoreText">โหลดเพิ่มเติม (<span id="remainingCount"><?php echo count($rooms) - 5; ?></span> ห้อง)</span>
                  </button>
                </div>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </section>
        </div>
      </main>
    </div>

    <!-- Edit Modal -->
    <div class="booking-modal" id="editModal" style="display:none;">
      <div class="booking-modal-content" style="max-width:600px;">
        <h2>แก้ไขห้องพัก</h2>
        <form id="editForm" method="POST" action="../Manage/update_room.php" enctype="multipart/form-data">
          <input type="hidden" name="room_id" id="edit_room_id">
          
          <div class="booking-form-group">
            <label>หมายเลขห้อง: <span style="color: red;">*</span></label>
            <input type="text" name="room_number" id="edit_room_number" required maxlength="2">
          </div>
          
          <div class="booking-form-group" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div>
              <div class="add-type-row" style="margin-bottom:0.35rem;">
                <label style="margin:0;">ประเภทห้อง: <span style="color: red;">*</span></label>
                <button type="button" class="add-type-btn" id="addTypeBtnEdit">+ เพิ่มประเภทห้อง</button>
                <button type="button" class="add-type-btn delete-type-btn" id="deleteTypeBtnEdit">ลบประเภทห้อง</button>
              </div>
              <select name="type_id" id="edit_type_id" required>
                <option value="">-- เลือกประเภทห้อง --</option>
                <?php foreach ($roomTypes as $type): ?>
                  <option value="<?php echo $type['type_id']; ?>">
                    <?php echo htmlspecialchars($type['type_name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          
          <div class="booking-form-group">
            <label>รูปภาพห้อง:</label>
            <input type="file" name="room_image" id="edit_room_image" accept="image/*">
            <div id="edit_image_preview" style="margin-top:0.5rem; color:#94a3b8; font-size:0.85rem;"></div>
          </div>
          
          <div class="booking-form-actions">
            <button type="submit" class="btn-submit">บันทึกการแก้ไข</button>
            <button type="button" class="btn-cancel" onclick="closeEditModal()">ยกเลิก</button>
          </div>
        </form>
      </div>
    </div>

    <script src="../Assets/Javascript/animate-ui.js" defer></script>
    <script src="../Assets/Javascript/main.js" defer></script>
    <script>
      // Hide animate-ui modal overlays for this page
      document.addEventListener('DOMContentLoaded', () => {
        const overlays = document.querySelectorAll('.animate-ui-modal-overlay');
        overlays.forEach(el => {
          el.style.display = 'none';
          el.remove();
        });
      });

      const roomsData = <?php echo json_encode($rooms); ?>;
      window.roomsData = roomsData;

      function editRoom(roomId) {
        const room = roomsData.find(r => r.room_id == roomId);
        if (!room) return;
        
        document.getElementById('edit_room_id').value = room.room_id;
        document.getElementById('edit_room_number').value = room.room_number;
        document.getElementById('edit_type_id').value = room.type_id;
        
        const preview = document.getElementById('edit_image_preview');
        if (room.room_image) {
          preview.innerHTML = '<div style="color:#22c55e;">✓ มีรูปภาพแล้ว (' + room.room_image + ')</div>';
        } else {
          preview.innerHTML = '<div style="color:#94a3b8;">ไม่มีรูปภาพ</div>';
        }
        
        document.getElementById('editModal').style.display = 'flex';
      }
      window.editRoom = editRoom;
      
      function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
        document.getElementById('editForm').reset();
      }
      
      async function deleteRoom(roomId, roomNumber) {
        const confirmed = await showConfirmDialog(
          'ยืนยันการลบห้อง',
          `คุณต้องการลบห้อง <strong>"${roomNumber}"</strong> หรือไม่?<br><br>การดำเนินการนี้ไม่สามารถย้อนกลับได้`
        );
        
        if (!confirmed) return;
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '../Manage/delete_room.php';
        
        const idField = document.createElement('input');
        idField.type = 'hidden';
        idField.name = 'room_id';
        idField.value = roomId;
        
        form.appendChild(idField);
        document.body.appendChild(form);
        form.submit();
      }

      // Add new room type (inline prompt + AJAX)
      function addRoomTypeFlow() {
        const name = prompt('ชื่อประเภทห้อง');
        if (!name) return;
        const priceRaw = prompt('ราคา/เดือน (ตัวเลขเท่านั้น)');
        if (!priceRaw) return;
        const price = priceRaw.replace(/[^0-9]/g, '');
        if (!price) { showErrorToast('กรุณากรอกราคาเป็นตัวเลข'); return; }

        const formData = new FormData();
        formData.append('type_name', name.trim());
        formData.append('type_price', price);

        fetch('../Manage/add_room_type.php', {
          method: 'POST',
          body: formData
        })
        .then(res => res.json())
        .then(data => {
          if (!data.success) throw new Error(data.message || 'เพิ่มประเภทห้องไม่สำเร็จ');
          const { id, label } = data;
          const addOption = (selectId) => {
            const sel = document.getElementById(selectId);
            if (!sel) return;
            const opt = document.createElement('option');
            opt.value = id;
            opt.textContent = label;
            sel.appendChild(opt);
            sel.value = id;
          };
          addOption('type_id');
          addOption('edit_type_id');
          showSuccessToast('เพิ่มประเภทห้องเรียบร้อยแล้ว');
        })
        .catch(err => {
          console.error(err);
          showErrorToast(err.message || 'เพิ่มประเภทห้องไม่สำเร็จ');
        });
      }

      // Delete room type (current selection)
      async function deleteRoomTypeFlow(selectId) {
        const sel = document.getElementById(selectId);
        if (!sel || !sel.value) { showErrorToast('เลือกประเภทห้องที่จะลบก่อน'); return; }
        const opt = sel.options[sel.selectedIndex];
        
        const confirmed = await showConfirmDialog(
          'ยืนยันการลบประเภทห้อง',
          `คุณต้องการลบประเภทห้อง <strong>"${opt.text}"</strong> หรือไม่?<br><br>การดำเนินการนี้ไม่สามารถย้อนกลับได้`
        );
        
        if (!confirmed) return;

        const formData = new FormData();
        formData.append('type_id', sel.value);

        fetch('../Manage/delete_room_type.php', {
          method: 'POST',
          body: formData
        })
        .then(res => res.json())
        .then(data => {
          if (!data.success) throw new Error(data.message || 'ลบประเภทห้องไม่สำเร็จ');
          // remove from both selects
          ['type_id', 'edit_type_id'].forEach(id => {
            const s = document.getElementById(id);
            if (!s) return;
            [...s.options].forEach((o, idx) => {
              if (o.value === sel.value) s.remove(idx);
            });
            s.value = '';
          });
          showSuccessToast('ลบประเภทห้องเรียบร้อยแล้ว');
        })
        .catch(err => {
          console.error(err);
          showErrorToast(err.message || 'ลบประเภทห้องไม่สำเร็จ');
        });
      }

      document.getElementById('addTypeBtn')?.addEventListener('click', addRoomTypeFlow);
      document.getElementById('addTypeBtnEdit')?.addEventListener('click', addRoomTypeFlow);
      document.getElementById('deleteTypeBtn')?.addEventListener('click', () => deleteRoomTypeFlow('type_id'));
      document.getElementById('deleteTypeBtnEdit')?.addEventListener('click', () => deleteRoomTypeFlow('edit_type_id'));
      
      // Wire up edit buttons - highest priority capture to block animate-ui
      const invokeEdit = (btn, evt) => {
        if (!btn) return;
        // Remove any animate-ui modal overlays immediately
        document.querySelectorAll('.animate-ui-modal-overlay').forEach(el => {
          el.style.display = 'none';
          el.remove();
        });
        try { evt?.stopImmediatePropagation?.(); } catch (err) {}
        try { evt?.stopPropagation?.(); } catch (err) {}
        evt?.preventDefault?.();
        const id = btn.getAttribute('data-room-id');
        if (id) {
          try { editRoom(id); } catch (err) { console.error('editRoom failed', err); }
        }
      };
      // Use capture phase to intercept before animate-ui
      document.body.addEventListener('click', (e) => {
        const btn = e.target.closest('.animate-ui-action-btn.edit');
        if (!btn) return;
        invokeEdit(btn, e);
      }, { capture: true });
      // Also bind directly on each button
      document.querySelectorAll('.animate-ui-action-btn.edit').forEach(btn => {
        btn.addEventListener('click', (evt) => invokeEdit(btn, evt), { capture: true });
      });
      // Toast helper for inline flows (fallbacks to alert if CSS/JS not ready)
      const toast = (msg, duration = 2200) => {
        if (typeof window.showToast === 'function') {
          try { window.showToast(msg, duration); return; } catch (err) { console.warn(err); }
        }
        const existing = document.querySelector('.animate-ui-toast');
        if (existing) existing.remove();
        const el = document.createElement('div');
        el.className = 'animate-ui-toast';
        el.textContent = msg;
        document.body.appendChild(el);
        const hide = () => {
          el.style.animation = 'toastOut 260ms ease forwards';
          setTimeout(() => {
            if (el.parentNode) el.parentNode.removeChild(el);
          }, 260);
        };
        el.addEventListener('click', hide);
        setTimeout(hide, duration);
      };
      // Debug submit add-room form: alert values then continue submit
      const addRoomForm = document.querySelector('form[action="manage_rooms.php"]');
      if (addRoomForm) {
        const defaultTypeId = '<?php echo $defaultTypeId; ?>';
        const typeSelectEl = document.getElementById('type_id');
        if (typeSelectEl && defaultTypeId && !typeSelectEl.value) {
          typeSelectEl.value = defaultTypeId;
        }
        const validateAddRoom = () => {
          const roomNumberInput = document.getElementById('room_number');
          if (!roomNumberInput || !roomNumberInput.value.trim()) {
            toast('กรุณากรอกหมายเลขห้อง');
            roomNumberInput?.focus();
            return null;
          }
          const typeSelect = document.getElementById('type_id');
          if (!typeSelect || !typeSelect.value) {
            toast('กรุณาเลือกประเภทห้อง');
            typeSelect?.focus();
            return null;
          }
          const roomNumber = roomNumberInput.value.trim();
          const typeText = typeSelect.options[typeSelect.selectedIndex]?.text || '';
          return { roomNumber, typeText };
        };

        addRoomForm.addEventListener('submit', (e) => {
          const vals = validateAddRoom();
          if (!vals) { e.preventDefault(); return; }
          toast(`บันทึกห้อง ${vals.roomNumber} | ${vals.typeText} (สถานะกำหนดอัตโนมัติ)`, 2600);
          console.log('[AddRoom] submit fired', vals);
        });
        // เผื่อกรณีบางเบราว์เซอร์กดปุ่มแล้วไม่ได้เข้าฟอร์ม submit ให้ติดที่ปุ่มด้วย
        const addRoomBtn = addRoomForm.querySelector('.animate-ui-add-btn');
        if (addRoomBtn) {
          addRoomBtn.addEventListener('click', (e) => {
            // กัน event bubble ไปโดน handler กลางของ animate-ui ที่เปิด modal
            e.stopImmediatePropagation();
            const vals = validateAddRoom();
            if (!vals) { e.preventDefault(); e.stopPropagation(); return; }
            toast(`บันทึกห้อง ${vals.roomNumber} | ${vals.typeText} (สถานะกำหนดอัตโนมัติ)`, 2600);
            console.log('[AddRoom] button clicked', vals);
          });
        }
        const resetBtn = addRoomForm.querySelector('button[type="reset"]');
        if (resetBtn) {
          resetBtn.addEventListener('click', (e) => {
            e.preventDefault();
            addRoomForm.reset();
            const roomNumberInput = document.getElementById('room_number');
            if (roomNumberInput) roomNumberInput.value = '';
            const fileInput = document.getElementById('room_image');
            if (fileInput) fileInput.value = '';
            if (typeSelectEl && defaultTypeId) typeSelectEl.value = defaultTypeId;
            toast('ล้างข้อมูลแล้ว', 1800);
          });
        }
      }
      
      // Close modal when clicking outside
      document.getElementById('editModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
      });

      // View Toggle Function
      let currentView = 'grid'; // 'grid' or 'table'
      
      function toggleView() {
        const gridView = document.getElementById('roomsGrid');
        const tableView = document.getElementById('roomsTable');
        const toggleText = document.getElementById('viewToggleText');
        const toggleBtn = document.getElementById('viewToggleBtn');
        
        if (currentView === 'grid') {
          // Switch to table view
          gridView.style.display = 'none';
          tableView.style.display = 'block';
          toggleText.textContent = 'มุมมองการ์ด';
          toggleBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg><span id="viewToggleText">มุมมองการ์ด</span>`;
          currentView = 'table';
          try { localStorage.setItem('roomsView', 'table'); } catch (e) {}
        } else {
          // Switch to grid view
          gridView.style.display = 'grid';
          tableView.style.display = 'none';
          toggleText.textContent = 'มุมมองแถว';
          toggleBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg><span id="viewToggleText">มุมมองแถว</span>`;
          currentView = 'grid';
          try { localStorage.setItem('roomsView', 'grid'); } catch (e) {}
        }
      }
      
      // Restore saved view on page load
      document.addEventListener('DOMContentLoaded', () => {
        try {
          const savedView = localStorage.getItem('roomsView');
          if (savedView === 'table' && currentView === 'grid') {
            toggleView();
          }
        } catch (e) {}
      });

      // Load More Rooms Function
      let visibleRooms = 5;
      const ROOMS_PER_LOAD = 5;
      
      function loadMoreRooms() {
        const hiddenRows = document.querySelectorAll('.room-row.hidden-row');
        const totalRooms = document.querySelectorAll('.room-row').length;
        let showCount = 0;
        
        hiddenRows.forEach((row, index) => {
          if (showCount < ROOMS_PER_LOAD) {
            row.classList.remove('hidden-row');
            showCount++;
            visibleRooms++;
          }
        });
        
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
    </script>
    <script src="../Assets/Javascript/toast-notification.js"></script>
    <script src="../Assets/Javascript/confirm-modal.js"></script>
  </body>
</html>
