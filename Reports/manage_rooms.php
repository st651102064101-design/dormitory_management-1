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

// ดึงข้อมูลห้องพัก
$stmt = $pdo->query("
    SELECT r.room_id, r.room_number, r.room_status, r.room_image, r.type_id, rt.type_name, rt.type_price
    FROM room r
    LEFT JOIN roomtype rt ON r.type_id = rt.type_id
    ORDER BY CAST(r.room_number AS UNSIGNED) ASC
");
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงประเภทห้อง
$stmt = $pdo->query("SELECT type_id, type_name, type_price FROM roomtype ORDER BY type_name ASC");
$roomTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// นับสถานะห้อง
$vacant = 0;
$occupied = 0;
foreach ($rooms as $room) {
    if ($room['room_status'] == '1') {
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
    </style>
  </head>
  <body class="reports-page">
    <div class="app-shell">
      <?php include __DIR__ . '/../includes/sidebar.php'; ?>
      <main class="app-main">
        <div>
          <?php 
            $pageTitle = 'จัดการห้องพัก';
            include __DIR__ . '/../includes/page_header.php'; 
          ?>

          <?php if (isset($_SESSION['success'])): ?>
            <div style="padding: 1rem; margin-bottom: 1rem; background: #22c55e; color: #0f172a; border-radius: 10px; font-weight:600;">
              <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
          <?php endif; ?>
          <?php if (isset($_SESSION['error'])): ?>
            <div style="padding: 1rem; margin-bottom: 1rem; background: #ef4444; color: #fff; border-radius: 10px; font-weight:600;">
              <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
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
            <form action="../Manage/process_room.php" method="post" enctype="multipart/form-data">
              <div class="room-form">
                <div class="room-form-group">
                  <label for="room_number">หมายเลขห้อง <span style="color:#f87171;">*</span></label>
                  <input type="text" id="room_number" name="room_number" required maxlength="2" placeholder="เช่น 01, 02, ..." />
                </div>
                <div class="room-form-group" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                  <div>
                    <label for="type_id">ประเภทห้อง <span style="color:#f87171;">*</span></label>
                    <select id="type_id" name="type_id" required>
                      <option value="">-- เลือกประเภทห้อง --</option>
                      <?php foreach ($roomTypes as $type): ?>
                        <option value="<?php echo $type['type_id']; ?>">
                          <?php echo htmlspecialchars($type['type_name']); ?> (<?php echo number_format($type['type_price']); ?> บาท/เดือน)
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div>
                    <label for="room_status">สถานะห้อง <span style="color:#f87171;">*</span></label>
                    <select id="room_status" name="room_status" required>
                      <option value="1">ว่าง</option>
                      <option value="0">ไม่ว่าง</option>
                    </select>
                  </div>
                </div>
                <div class="room-form-group">
                  <label for="room_image">รูปภาพห้อง</label>
                  <input type="file" id="room_image" name="room_image" accept="image/*" />
                </div>
                <div class="room-form-actions">
                  <button type="submit" class="animate-ui-add-btn" style="flex:2;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></svg>
                    เพิ่มห้องพัก
                  </button>
                  <button type="reset" class="animate-ui-action-btn delete" style="flex:1;">ล้างข้อมูล</button>
                </div>
              </div>
            </form>
          </section>

          <section class="manage-panel">
            <div class="section-header">
              <div>
                <h1>รายการห้องพัก</h1>
                <p style="color:#94a3b8;margin-top:0.2rem;">ห้องพักและข้อมูลทั้งหมด</p>
              </div>
            </div>
            
            <?php if (empty($rooms)): ?>
              <div class="room-empty">
                <div class="room-empty-icon">🛏️</div>
                <h3>ยังไม่มีห้องพัก</h3>
                <p>เริ่มต้นเพิ่มห้องพัก</p>
              </div>
            <?php else: ?>
              <div class="rooms-grid">
                <?php foreach ($rooms as $room): ?>
                  <div class="room-card">
                    <div class="room-card-image">
                      <?php if (!empty($room['room_image'])): ?>
                        <img src="../Assets/Images/Rooms/<?php echo htmlspecialchars($room['room_image']); ?>" alt="ห้อง <?php echo htmlspecialchars($room['room_number']); ?>" />
                      <?php else: ?>
                        🛏️
                      <?php endif; ?>
                    </div>
                    <div class="room-card-content">
                      <h3 class="room-card-number">ห้อง <?php echo htmlspecialchars($room['room_number']); ?></h3>
                      <div class="room-card-meta">
                        ประเภท: <?php echo htmlspecialchars($room['type_name'] ?? '-'); ?><br>
                        ราคา: <?php echo number_format($room['type_price'] ?? 0); ?> บาท/เดือน
                      </div>
                      <div class="room-card-status <?php echo $room['room_status'] == '1' ? 'vacant' : 'occupied'; ?>">
                        <?php echo $room['room_status'] == '1' ? '✓ ว่าง' : '✗ ไม่ว่าง'; ?>
                      </div>
                      <div class="room-card-actions">
                        <button type="button" class="animate-ui-action-btn edit" onclick="editRoom(<?php echo $room['room_id']; ?>)">แก้ไข</button>
                        <button type="button" class="animate-ui-action-btn delete" onclick="deleteRoom(<?php echo $room['room_id']; ?>, '<?php echo htmlspecialchars(addslashes($room['room_number'])); ?>')">ลบ</button>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
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
              <label>ประเภทห้อง: <span style="color: red;">*</span></label>
              <select name="type_id" id="edit_type_id" required>
                <option value="">-- เลือกประเภทห้อง --</option>
                <?php foreach ($roomTypes as $type): ?>
                  <option value="<?php echo $type['type_id']; ?>">
                    <?php echo htmlspecialchars($type['type_name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label>สถานะห้อง: <span style="color: red;">*</span></label>
              <select name="room_status" id="edit_room_status" required>
                <option value="1">ว่าง</option>
                <option value="0">ไม่ว่าง</option>
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
      const roomsData = <?php echo json_encode($rooms); ?>;
      
      function editRoom(roomId) {
        const room = roomsData.find(r => r.room_id == roomId);
        if (!room) return;
        
        document.getElementById('edit_room_id').value = room.room_id;
        document.getElementById('edit_room_number').value = room.room_number;
        document.getElementById('edit_type_id').value = room.type_id;
        document.getElementById('edit_room_status').value = room.room_status;
        
        const preview = document.getElementById('edit_image_preview');
        if (room.room_image) {
          preview.innerHTML = '<div style="color:#22c55e;">✓ มีรูปภาพแล้ว (' + room.room_image + ')</div>';
        } else {
          preview.innerHTML = '<div style="color:#94a3b8;">ไม่มีรูปภาพ</div>';
        }
        
        document.getElementById('editModal').style.display = 'flex';
      }
      
      function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
        document.getElementById('editForm').reset();
      }
      
      function deleteRoom(roomId, roomNumber) {
        if (!confirm(`ยืนยันการลบห้อง "${roomNumber}"?`)) return;
        
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
      
      // Close modal when clicking outside
      document.getElementById('editModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
      });
    </script>
  </body>
</html>
