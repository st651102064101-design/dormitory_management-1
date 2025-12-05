<?php
declare(strict_types=1);
session_start();

// ตั้ง timezone เป็น กรุงเทพ
date_default_timezone_set('Asia/Bangkok');

if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

// รายการการแจ้งซ่อม
$repairStmt = $pdo->query("SELECT r.*, c.ctr_id, t.tnt_name, rm.room_number
  FROM repair r
  LEFT JOIN contract c ON r.ctr_id = c.ctr_id
  LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
  LEFT JOIN room rm ON c.room_id = rm.room_id
  ORDER BY r.repair_date DESC, r.repair_id DESC");
$repairs = $repairStmt->fetchAll(PDO::FETCH_ASSOC);

// รายการสัญญาสำหรับเลือกห้อง/ผู้เช่า (แสดงแค่ที่ซ่อมเสร็จแล้ว หรือ ไม่มีการซ่อมอยู่)
$contracts = $pdo->query("
  SELECT c.ctr_id, c.ctr_status, t.tnt_name, rm.room_number,
         MAX(r.repair_status) as latest_repair_status
  FROM contract c
  LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
  LEFT JOIN room rm ON c.room_id = rm.room_id
  LEFT JOIN repair r ON c.ctr_id = r.ctr_id
  GROUP BY c.ctr_id
  HAVING latest_repair_status IS NULL OR latest_repair_status = '2'
  ORDER BY rm.room_number
")->fetchAll(PDO::FETCH_ASSOC);

$statusMap = [
  '0' => 'รอซ่อม',
  '1' => 'กำลังซ่อม',
  '2' => 'ซ่อมเสร็จแล้ว',
];
$statusColors = [
  '0' => '#f97316',
  '1' => '#60a5fa',
  '2' => '#22c55e',
];

$stats = [
  'pending' => 0,
  'inprogress' => 0,
  'done' => 0,
];
foreach ($repairs as $r) {
  if (($r['repair_status'] ?? '') === '0') $stats['pending']++;
  elseif (($r['repair_status'] ?? '') === '1') $stats['inprogress']++;
  elseif (($r['repair_status'] ?? '') === '2') $stats['done']++;
}

function relativeTimeInfo(?string $dateTimeStr): array {
  if (!$dateTimeStr) return ['label' => '-', 'class' => ''];
  try {
    $tz = new DateTimeZone('Asia/Bangkok');
    $target = new DateTime($dateTimeStr, $tz);
    $now = new DateTime('now', $tz);
  } catch (Exception $e) {
    return ['label' => '-', 'class' => ''];
  }

  $diff = $now->getTimestamp() - $target->getTimestamp();
  if ($diff < 0) return ['label' => 'เร็วๆ นี้', 'class' => 'time-neutral'];

  if ($diff < 60) {
    $sec = max(1, $diff);
    return ['label' => $sec . ' วินาทีที่แล้ว', 'class' => 'time-fresh'];
  }
  if ($diff < 3600) {
    $min = (int)floor($diff / 60);
    return ['label' => $min . ' นาทีที่แล้ว', 'class' => 'time-fresh'];
  }
  if ($diff < 86400) {
    $hrs = (int)floor($diff / 3600);
    return ['label' => $hrs . ' ชม.ที่แล้ว', 'class' => 'time-fresh'];
  }

  $days = (int)floor($diff / 86400);
  if ($days === 1) return ['label' => 'เมื่อวาน', 'class' => 'time-warning'];
  if ($days === 2) return ['label' => 'เมื่อวานซืน', 'class' => 'time-warning'];
  if ($days < 4) return ['label' => $days . ' วันที่แล้ว', 'class' => 'time-warning'];

  if ($days < 30) return ['label' => $days . ' วันที่แล้ว', 'class' => 'time-danger'];

  $months = (int)floor($days / 30);
  if ($months < 12) return ['label' => $months . ' เดือนที่แล้ว', 'class' => 'time-danger'];

  $years = (int)floor($days / 365);
  return ['label' => $years . ' ปีที่แล้ว', 'class' => 'time-danger'];
}
?>
<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>จัดการการแจ้งซ่อม</title>
    <link rel="stylesheet" href="../Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="../Assets/Css/main.css" />
    <link rel="stylesheet" href="../Assets/Css/confirm-modal.css" />
    <style>
      /* Disable animate-ui modal overlays on this page */
      .animate-ui-modal, .animate-ui-modal-overlay { display:none !important; visibility:hidden !important; opacity:0 !important; }
      .repair-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:0.75rem; margin-top:1rem; }
      .repair-stat-card { padding:1rem; border-radius:12px; background:#0f172a; color:#e2e8f0; border:1px solid rgba(148,163,184,0.25); box-shadow:0 12px 35px rgba(0,0,0,0.25); }
      .repair-stat-card h3 { margin:0; font-size:0.95rem; color:#cbd5e1; }
      .repair-stat-card .stat-number { font-size:1.8rem; font-weight:700; margin-top:0.35rem; }
      .repair-form { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1rem; margin-top:1rem; }
      .repair-form label { font-weight:600; color:#cbd5e1; margin-bottom:0.35rem; display:block; }
      .repair-form input, .repair-form select, .repair-form textarea { width:100%; padding:0.75rem 0.85rem; border-radius:10px; border:1px solid rgba(148,163,184,0.35); background:#0b162a; color:#e2e8f0; }
      .repair-form textarea { min-height:110px; resize:vertical; }
      .repair-form-actions { grid-column:1 / -1; display:flex; gap:0.75rem; }
      .status-badge { display:inline-flex; align-items:center; justify-content:center; min-width:90px; padding:0.25rem 0.75rem; border-radius:999px; font-weight:600; color:#fff; }
      .crud-actions { display:flex; gap:0.4rem; flex-wrap:wrap; }
      .reports-page .manage-panel { background:#0f172a; border:1px solid rgba(148,163,184,0.2); box-shadow:0 12px 30px rgba(0,0,0,0.2); margin-top:1rem; }
      .reports-page .manage-panel:first-of-type { margin-top:0; }
      .repair-primary,
      .animate-ui-action-btn.edit.repair-primary { background: #FF9500; color: #fff; border: none; font-weight:600; letter-spacing:0.2px; }
      .repair-primary:hover,
      .animate-ui-action-btn.edit.repair-primary:hover { background: #E68600; opacity: 0.9; transform: none; box-shadow: none; }
      .repair-primary:active,
      .animate-ui-action-btn.edit.repair-primary:active { opacity: 0.8; box-shadow: none; }
      .time-badge { display:inline-block; padding:0.3rem 0.65rem; border-radius:6px; font-weight:600; font-size:0.85rem; white-space:nowrap; }
      .time-fresh { background:#d1fae5; color:#065f46; }
      .time-warning { background:#fef3c7; color:#92400e; }
      .time-danger { background:#fee2e2; color:#b91c1c; }
      .time-neutral { background:#e2e8f0; color:#0f172a; }
    </style>
  </head>
  <body class="reports-page">
    <div class="app-shell">
      <?php include __DIR__ . '/../includes/sidebar.php'; ?>
      <main class="app-main">
        <div>
          <?php 
            $pageTitle = 'จัดการการแจ้งซ่อมอุปกรณ์';
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
            <div class="section-header">
              <div>
                <h1>สรุปการแจ้งซ่อม</h1>
                <p style="color:#94a3b8;margin-top:0.25rem;">สถานะปัจจุบันของงานซ่อมทั้งหมด</p>
              </div>
            </div>
            <div class="repair-stats">
              <div class="repair-stat-card" style="border-color:rgba(249,115,22,0.35);">
                <h3>รอซ่อม</h3>
                <div class="stat-number" style="color:#f97316;"><?php echo (int)$stats['pending']; ?></div>
              </div>
              <div class="repair-stat-card" style="border-color:rgba(96,165,250,0.35);">
                <h3>กำลังซ่อม</h3>
                <div class="stat-number" style="color:#60a5fa;"><?php echo (int)$stats['inprogress']; ?></div>
              </div>
              <div class="repair-stat-card" style="border-color:rgba(34,197,94,0.35);">
                <h3>ซ่อมเสร็จแล้ว</h3>
                <div class="stat-number" style="color:#22c55e;"><?php echo (int)$stats['done']; ?></div>
              </div>
            </div>
          </section>

          <section class="manage-panel" style="color:#f8fafc;">
            <div class="section-header">
              <div>
                <h1>เพิ่มการแจ้งซ่อม</h1>
                <p style="margin-top:0.25rem;color:rgba(255,255,255,0.7);">เลือกห้อง/ผู้เช่า ระบุวันที่และรายละเอียดงานซ่อม</p>
              </div>
            </div>
            <form action="../Manage/process_repair.php" method="post" id="repairForm">
              <div class="repair-form">
                <div>
                  <label for="ctr_id">สัญญา / ห้องพัก <span style="color:#f87171;">*</span></label>
                  <select id="ctr_id" name="ctr_id" required>
                    <option value="">-- เลือกสัญญา --</option>
                    <?php foreach ($contracts as $ctr): ?>
                      <option value="<?php echo (int)$ctr['ctr_id']; ?>">
                        ห้อง <?php echo str_pad((string)($ctr['room_number'] ?? '0'), 2, '0', STR_PAD_LEFT); ?> - <?php echo htmlspecialchars($ctr['tnt_name'] ?? 'ไม่ระบุ'); ?>
                        <?php if (($ctr['ctr_status'] ?? '') !== '0') echo ' (ไม่ใช้งาน)'; ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label for="repair_date">วันที่แจ้ง</label>
                  <input type="text" id="repair_date" name="repair_date" readonly style="background:rgba(148,163,184,0.1); cursor:not-allowed;" value="<?php echo date('d/m/Y'); ?>" />
                  <input type="hidden" id="repair_date_hidden" name="repair_date_hidden" value="<?php echo date('Y-m-d'); ?>" />
                </div>
                <div>
                  <label for="repair_time">เวลาแจ้ง</label>
                  <input type="text" id="repair_time" name="repair_time" readonly style="background:rgba(148,163,184,0.1); cursor:not-allowed;" value="<?php echo date('H:i:s'); ?>" />
                  <input type="hidden" id="repair_time_hidden" name="repair_time_hidden" value="<?php echo date('H:i:s'); ?>" />
                </div>
                <input type="hidden" name="repair_status" value="0" />
                <div style="grid-column:1 / -1;">
                  <label for="repair_desc">รายละเอียด <span style="color:#f87171;">*</span></label>
                  <textarea id="repair_desc" name="repair_desc" required placeholder="เช่น แอร์ไม่เย็น น้ำรั่ว ซิงค์อุดตัน"></textarea>
                </div>
                <div class="repair-form-actions">
                  <button type="submit" class="animate-ui-add-btn" data-animate-ui-skip="true" data-no-modal="true" data-allow-submit="true" style="flex:2; cursor: pointer;">บันทึกการแจ้งซ่อม</button>
                  <button type="reset" class="animate-ui-action-btn delete" style="flex:1;">ล้างข้อมูล</button>
                </div>
              </div>
            </form>
          </section>

          <section class="manage-panel">
            <div class="section-header">
              <div>
                <h1>รายการแจ้งซ่อมทั้งหมด</h1>
                <p style="color:#94a3b8;margin-top:0.2rem;">ดูสถานะและจัดการงานซ่อม</p>
              </div>
            </div>
            <div class="report-table">
              <table class="table--compact" id="table-repairs">
                <thead>
                  <tr>
                    <th>วันที่</th>
                    <th>ห้อง/ผู้แจ้ง</th>
                    <th>รายละเอียด</th>
                    <th>สถานะ</th>
                    <th class="crud-column">จัดการ</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($repairs)): ?>
                    <tr><td colspan="5" style="text-align:center;padding:1.5rem;color:#94a3b8;">ยังไม่มีรายการแจ้งซ่อม</td></tr>
                  <?php else: ?>
                    <?php foreach ($repairs as $r): ?>
                      <?php $status = (string)($r['repair_status'] ?? ''); ?>
                      <tr>
                        <?php 
                          $repairDate = $r['repair_date'] ?? '';
                          $repairTime = $r['repair_time'] ?? '00:00:00';
                          // ตัดเอาเฉพาะวันที่จาก repair_date (ตัดส่วนเวลา 00:00:00 ออก)
                          $dateOnly = $repairDate ? explode(' ', $repairDate)[0] : '';
                          
                          // แปลงวันที่จาก ค.ศ. เป็น พ.ศ. สำหรับแสดงผล
                          $displayDate = $dateOnly;
                          if ($dateOnly) {
                            $dateParts = explode('-', $dateOnly);
                            if (count($dateParts) === 3 && (int)$dateParts[0] < 2100) {
                              // ถ้าเป็น ค.ศ. (น้อยกว่า 2100) ให้แปลงเป็น พ.ศ.
                              $displayDate = ((int)$dateParts[0] + 543) . '-' . $dateParts[1] . '-' . $dateParts[2];
                            }
                          }
                          
                          $repairDateTime = trim($dateOnly . ' ' . $repairTime);
                          $timeInfo = relativeTimeInfo($repairDateTime);
                        ?>
                        <td>
                          <div style="display:flex; flex-direction:column; gap:0.2rem;">
                            <span class="time-badge relative-time <?php echo htmlspecialchars($timeInfo['class']); ?>" 
                                  data-datetime="<?php echo htmlspecialchars($repairDateTime); ?>">
                              <?php echo htmlspecialchars($timeInfo['label']); ?>
                            </span>
                            <span style="color:#64748b; font-size:0.75rem;">
                              <?php 
                                if ($displayDate) {
                                  echo htmlspecialchars($displayDate) . ' ' . htmlspecialchars($repairTime);
                                } else {
                                  echo '-';
                                }
                              ?>
                            </span>
                          </div>
                        </td>
                        <td>
                          <div style="display:flex; flex-direction:column; gap:0.15rem;">
                            <span>ห้อง <?php echo str_pad((string)($r['room_number'] ?? '0'), 2, '0', STR_PAD_LEFT); ?></span>
                            <span style="color:#64748b; font-size:0.8rem;">ผู้แจ้ง: <?php echo htmlspecialchars($r['tnt_name'] ?? '-'); ?></span>
                          </div>
                        </td>
                        <td><?php echo nl2br(htmlspecialchars($r['repair_desc'] ?? '-')); ?></td>
                        <td><span class="status-badge" style="background: <?php echo $statusColors[$status] ?? '#94a3b8'; ?>;"><?php echo $statusMap[$status] ?? 'ไม่ระบุ'; ?></span></td>
                        <td class="crud-column">
                          <div class="crud-actions">
                            <?php if ($status === '0'): ?>
                              <button type="button" class="animate-ui-action-btn edit repair-primary" onclick="updateRepairStatus(<?php echo (int)$r['repair_id']; ?>, '1')">ทำการซ่อม</button>
                            <?php elseif ($status === '1'): ?>
                              <span style="color:#60a5fa; font-weight:600;">กำลังซ่อม</span>
                            <?php else: ?>
                              <span style="color:#22c55e; font-weight:600;">ซ่อมเสร็จแล้ว</span>
                            <?php endif; ?>
                          </div>
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

    <script src="../Assets/Javascript/animate-ui.js" defer></script>
    <script src="../Assets/Javascript/main.js" defer></script>
    <script>
      // อัปเดตวันที่และเวลาลิงค์ทุกวินาที
      function updateRepairTime() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const year = now.getFullYear();
        const yearBE = year + 543; // เปลี่ยนเป็นปีพุทธศักราช
        
        const timeInput = document.getElementById('repair_time');
        const timeHidden = document.getElementById('repair_time_hidden');
        const dateDisplay = document.getElementById('repair_date');
        const dateHidden = document.getElementById('repair_date_hidden');
        
        if (timeInput) timeInput.value = `${hours}:${minutes}:${seconds}`;
        if (timeHidden) timeHidden.value = `${hours}:${minutes}:${seconds}`;
        if (dateDisplay) dateDisplay.value = `${day}/${month}/${yearBE}`;
        // ใช้เวลาท้องถิ่น (ค.ศ.) สำหรับเก็บฐานข้อมูล
        if (dateHidden) dateHidden.value = `${year}-${month}-${day}`;
      }
      
      // อัปเดตทันทีและทุก 1 วินาที
      updateRepairTime();
      setInterval(updateRepairTime, 1000);

      // ฟังก์ชันคำนวณเวลาสัมพัทธ์
      function getRelativeTime(dateTimeStr) {
        if (!dateTimeStr) return { label: '-', class: '' };
        
        const target = new Date(dateTimeStr);
        const now = new Date();
        const diff = Math.floor((now - target) / 1000); // วินาที
        
        if (diff < 0) return { label: 'เร็วๆ นี้', class: 'time-neutral' };
        
        if (diff < 60) {
          return { label: `${Math.max(1, diff)} วินาทีที่แล้ว`, class: 'time-fresh' };
        }
        if (diff < 3600) {
          const min = Math.floor(diff / 60);
          return { label: `${min} นาทีที่แล้ว`, class: 'time-fresh' };
        }
        if (diff < 86400) {
          const hrs = Math.floor(diff / 3600);
          return { label: `${hrs} ชม.ที่แล้ว`, class: 'time-fresh' };
        }
        
        const days = Math.floor(diff / 86400);
        if (days === 1) return { label: 'เมื่อวาน', class: 'time-warning' };
        if (days === 2) return { label: 'เมื่อวานซืน', class: 'time-warning' };
        if (days < 4) return { label: `${days} วันที่แล้ว`, class: 'time-warning' };
        if (days < 30) return { label: `${days} วันที่แล้ว`, class: 'time-danger' };
        
        const months = Math.floor(days / 30);
        if (months < 12) return { label: `${months} เดือนที่แล้ว`, class: 'time-danger' };
        
        const years = Math.floor(days / 365);
        return { label: `${years} ปีที่แล้ว`, class: 'time-danger' };
      }

      // อัพเดทเวลาสัมพัทธ์ในตาราง
      function updateRelativeTimes() {
        document.querySelectorAll('.relative-time').forEach(badge => {
          const datetime = badge.getAttribute('data-datetime');
          if (!datetime) return;
          
          const timeInfo = getRelativeTime(datetime);
          badge.textContent = timeInfo.label;
          
          // อัพเดท class
          badge.className = 'time-badge relative-time ' + timeInfo.class;
        });
      }

      async function updateRepairStatus(repairId, status) {
        if (!repairId) return;
        
        const confirmed = await showConfirmDialog(
          'ยืนยันการเปลี่ยนสถานะ',
          `คุณต้องการเปลี่ยนสถานะเป็น <strong>"ทำการซ่อม"</strong> หรือไม่?`
        );
        
        if (!confirmed) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '../Manage/update_repair_status.php';
        const idField = document.createElement('input');
        idField.type = 'hidden';
        idField.name = 'repair_id';
        idField.value = repairId;
        const statusField = document.createElement('input');
        statusField.type = 'hidden';
        statusField.name = 'repair_status';
        statusField.value = status;
        form.appendChild(idField);
        form.appendChild(statusField);
        document.body.appendChild(form);
        form.submit();
      }

      // แสดง toast notification ด้านล่างขวา (ปิดอัตโนมัติหลัง 3 วินาที)


      // โหลด dropdown สัญญาใหม่
      function loadContractOptions() {
        fetch(window.location.href)
          .then(response => response.text())
          .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // ดึง options ใหม่จาก HTML ที่โหลดมา
            const newSelect = doc.querySelector('#ctr_id');
            const currentSelect = document.getElementById('ctr_id');
            
            if (newSelect && currentSelect) {
              // เก็บค่าเดิม
              const oldValue = currentSelect.value;
              
              // แทนที่ options ทั้งหมด
              currentSelect.innerHTML = newSelect.innerHTML;
              
              // ถ้าค่าเดิมยังมีอยู่ ให้เลือกกลับ (แต่หลังเพิ่มการซ่อมแล้วน่าจะหายไป)
              if (oldValue && currentSelect.querySelector(`option[value="${oldValue}"]`)) {
                currentSelect.value = oldValue;
              } else {
                currentSelect.value = '';
              }
            }
          })
          .catch(error => {
            console.error('Error loading contract options:', error);
          });
      }

      // โหลดข้อมูลรายการแจ้งซ่อมใหม่
      function loadRepairList() {
        fetch(window.location.href)
          .then(response => response.text())
          .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // อัพเดทตาราง
            const newTable = doc.querySelector('#table-repairs tbody');
            const currentTable = document.querySelector('#table-repairs tbody');
            if (newTable && currentTable) {
              currentTable.innerHTML = newTable.innerHTML;
              
              // อัพเดทเวลาสัมพัทธ์สำหรับแถวใหม่
              updateRelativeTimes();
            }
            
            // อัพเดทสถิติ
            const statsCards = doc.querySelectorAll('.repair-stat-card .stat-number');
            const currentStats = document.querySelectorAll('.repair-stat-card .stat-number');
            if (statsCards.length === currentStats.length) {
              statsCards.forEach((stat, i) => {
                if (currentStats[i]) {
                  currentStats[i].textContent = stat.textContent;
                }
              });
            }
          })
          .catch(error => {
            console.error('Error loading repair list:', error);
          });
      }

      // บันทึกการแจ้งซ่อมด้วย AJAX
      function submitRepairForm(e) {
        e.preventDefault();
        
        const form = document.getElementById('repairForm');
        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');
        
        // Disable button
        submitBtn.disabled = true;
        submitBtn.textContent = 'กำลังบันทึก...';
        
        fetch('../Manage/process_repair.php', {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // แสดง toast notification
            showSuccessToast(data.message || 'บันทึกการแจ้งซ่อมเรียบร้อยแล้ว');
            
            // รีเซ็ตฟอร์ม
            form.reset();
            document.getElementById('ctr_id').value = '';
            document.getElementById('repair_desc').value = '';
            
            // โหลดข้อมูลใหม่แบบ AJAX (ไม่ reload หน้า)
            loadRepairList();
            
            // โหลด dropdown สัญญาใหม่
            loadContractOptions();
          } else {
            throw new Error(data.message || 'เกิดข้อผิดพลาด');
          }
        })
        .catch(error => {
          // แสดง toast notification error
          showErrorToast(error.message || 'เกิดข้อผิดพลาด กรุณาลองใหม่');
        })
        .finally(() => {
          // Enable button
          submitBtn.disabled = false;
          submitBtn.textContent = 'บันทึกการแจ้งซ่อม';
        });
      }

      document.addEventListener('DOMContentLoaded', () => {
        updateRepairTime();
        setInterval(updateRepairTime, 1000); // อัปเดตฟอร์มทุกวินาที
        
        updateRelativeTimes();
        setInterval(updateRelativeTimes, 1000); // อัปเดตเวลาสัมพัทธ์ทุกวินาที
        
        // เพิ่ม event listener สำหรับ form
        const repairForm = document.getElementById('repairForm');
        if (repairForm) {
          repairForm.addEventListener('submit', submitRepairForm);
        }
      });
    </script>
    <script src="../Assets/Javascript/toast-notification.js"></script>
    <script src="../Assets/Javascript/confirm-modal.js"></script>
  </body>
</html>
