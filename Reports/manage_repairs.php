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

// ดึง theme color จากการตั้งค่าระบบ
$settingsStmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'theme_color' LIMIT 1");
$themeColor = '#0f172a'; // ค่า default (dark mode)
if ($settingsStmt) {
    $theme = $settingsStmt->fetch(PDO::FETCH_ASSOC);
    if ($theme && !empty($theme['setting_value'])) {
        $themeColor = htmlspecialchars($theme['setting_value'], ENT_QUOTES, 'UTF-8');
    }
}

// รับค่า sort จาก query parameter
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$orderBy = 'r.repair_date DESC, r.repair_id DESC';

switch ($sortBy) {
  case 'oldest':
    $orderBy = 'r.repair_date ASC, r.repair_id ASC';
    break;
  case 'room_number':
    $orderBy = 'rm.room_number ASC';
    break;
  case 'newest':
  default:
    $orderBy = 'r.repair_date DESC, r.repair_id DESC';
}

// รายการการแจ้งซ่อม
$repairStmt = $pdo->query("SELECT r.*, c.ctr_id, t.tnt_name, rm.room_number
  FROM repair r
  LEFT JOIN contract c ON r.ctr_id = c.ctr_id
  LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
  LEFT JOIN room rm ON c.room_id = rm.room_id
  ORDER BY $orderBy");
$repairs = $repairStmt->fetchAll(PDO::FETCH_ASSOC);

// รายการสัญญาสำหรับเลือกห้อง/ผู้เช่า (แสดงแค่สัญญาที่ใช้งาน และไม่มีการซ่อมในสถานะ 0 หรือ 1)
$contracts = $pdo->query("
  SELECT c.ctr_id, c.ctr_status, t.tnt_name, rm.room_number
  FROM contract c
  LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
  LEFT JOIN room rm ON c.room_id = rm.room_id
  WHERE c.ctr_status = '0'
    AND c.ctr_id NOT IN (SELECT DISTINCT ctr_id FROM repair WHERE repair_status IN ('0', '1'))
  ORDER BY rm.room_number
")->fetchAll(PDO::FETCH_ASSOC);

$statusMap = [
  '0' => 'รอซ่อม',
  '1' => 'กำลังซ่อม',
  '2' => 'ซ่อมเสร็จแล้ว',
  '3' => 'ยกเลิก',
];
$statusColors = [
  '0' => '#f97316',
  '1' => '#60a5fa',
  '2' => '#22c55e',
  '3' => '#ef4444',
];

$stats = [
  'pending' => 0,
  'inprogress' => 0,
  'done' => 0,
  'cancelled' => 0,
];
foreach ($repairs as $r) {
  if (($r['repair_status'] ?? '') === '0') $stats['pending']++;
  elseif (($r['repair_status'] ?? '') === '1') $stats['inprogress']++;
  elseif (($r['repair_status'] ?? '') === '2') $stats['done']++;
  elseif (($r['repair_status'] ?? '') === '3') $stats['cancelled']++;
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
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - จัดการการแจ้งซ่อม</title>
    <link rel="icon" type="image/jpeg" href="../Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="../Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="../Assets/Css/main.css" />
    <link rel="stylesheet" href="../Assets/Css/confirm-modal.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4/dist/style.css" />
    <style>
      :root {
        --theme-bg-color: <?php echo $themeColor; ?>;
      }
      
      /* Disable animate-ui modal overlays on this page */
      .animate-ui-modal, .animate-ui-modal-overlay { display:none !important; visibility:hidden !important; opacity:0 !important; }
      .repair-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:0.75rem; margin-top:1rem; }
      
      /* Default: Dark mode (original dark cards) */
      .repair-stat-card { 
        padding:1rem; 
        border-radius:12px; 
        background: var(--bg-secondary, #0f172a);
        color: var(--text-secondary, #e2e8f0);
        border:1px solid rgba(148,163,184,0.2); 
        box-shadow:0 12px 30px rgba(0,0,0,0.2);
        transition: background 0.35s ease, color 0.35s ease, border-color 0.35s ease, box-shadow 0.35s ease;
      }
      .repair-stat-card h3 { margin:0; font-size:0.95rem; color: var(--text-tertiary, #cbd5e1); transition: color 0.35s ease; }
      .repair-stat-card .stat-number { font-size:1.8rem; font-weight:700; margin-top:0.35rem; }
      
      /* Light mode override - detect when theme is light (#ffffff or similar light colors) */
      @media (prefers-color-scheme: light) {
        .repair-stat-card {
          background:#f3f4f6 !important;
          color:#1f2937 !important;
          border:1px solid #e5e7eb !important;
          box-shadow:0 2px 8px rgba(0,0,0,0.06) !important;
        }
        .repair-stat-card h3 {
          color:#6b7280 !important;
        }
      }
      
      /* JavaScript-detected light theme class */
      html.light-theme .repair-stat-card {
        background:#f3f4f6 !important;
        color:#1f2937 !important;
        border:1px solid #e5e7eb !important;
        box-shadow:0 2px 8px rgba(0,0,0,0.06) !important;
      }
      
      html.light-theme .repair-stat-card h3 {
        color:#6b7280 !important;
      }
      
      /* Fallback: Also use detection by CSS variable value */
      /* This will work if --theme-bg-color contains light color text */
      html[style*="--theme-bg-color: #fff"] .repair-stat-card,
      html[style*="--theme-bg-color: #FFF"] .repair-stat-card,
      html[style*="--theme-bg-color: #ffffff"] .repair-stat-card,
      html[style*="--theme-bg-color: #FFFFFF"] .repair-stat-card,
      html[style*="--theme-bg-color: rgb(255, 255, 255)"] .repair-stat-card {
        background:#f3f4f6 !important; 
        color:#1f2937 !important; 
        border:1px solid #e5e7eb !important; 
        box-shadow:0 2px 8px rgba(0,0,0,0.06) !important;
      }
      
      html[style*="--theme-bg-color: #fff"] .repair-stat-card h3,
      html[style*="--theme-bg-color: #FFF"] .repair-stat-card h3,
      html[style*="--theme-bg-color: #ffffff"] .repair-stat-card h3,
      html[style*="--theme-bg-color: #FFFFFF"] .repair-stat-card h3,
      html[style*="--theme-bg-color: rgb(255, 255, 255)"] .repair-stat-card h3 {
        color:#6b7280 !important;
      }
      .repair-form { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1rem; margin-top:1rem; }
      .repair-form label { font-weight:600; color:#cbd5e1; margin-bottom:0.35rem; display:block; }
      .repair-form input, .repair-form select, .repair-form textarea { width:100%; padding:0.75rem 0.85rem; border-radius:10px; border:1px solid rgba(148,163,184,0.35); background:#0b162a; color:#e2e8f0; }
      .repair-form textarea { min-height:110px; resize:vertical; }
      
      /* Light theme overrides for form inputs */
      @media (prefers-color-scheme: light) {
        .repair-form input,
        .repair-form select,
        .repair-form textarea {
          background: #ffffff !important;
          color: #1f2937 !important;
          border: 1px solid #e5e7eb !important;
        }
        .repair-form label {
          color: #374151 !important;
        }
      }
      
      /* JavaScript-detected light theme class */
      html.light-theme .repair-form input,
      html.light-theme .repair-form select,
      html.light-theme .repair-form textarea {
        background: #ffffff !important;
        color: #1f2937 !important;
        border: 1px solid #e5e7eb !important;
      }
      
      html.light-theme .repair-form label {
        color: #374151 !important;
      }
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
      
      /* Status Filter Buttons */
      .status-filter-btn {
        padding: 0.6rem 1rem;
        border-radius: 8px;
        border: 1px solid;
        background: transparent;
        cursor: pointer;
        font-weight: 500;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.3rem;
      }
      
      .status-filter-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
      }
      
      /* Active state - brighter and more prominent */
      .status-filter-btn.active {
        font-weight: 600;
        box-shadow: 0 6px 16px rgba(0,0,0,0.25);
      }
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
                <div class="stat-number" style="color:#f97316 !important;"><?php echo (int)$stats['pending']; ?></div>
              </div>
              <div class="repair-stat-card" style="border-color:rgba(96,165,250,0.35);">
                <h3>กำลังซ่อม</h3>
                <div class="stat-number" style="color:#60a5fa !important;"><?php echo (int)$stats['inprogress']; ?></div>
              </div>
              <div class="repair-stat-card" style="border-color:rgba(34,197,94,0.35);">
                <h3>ซ่อมเสร็จแล้ว</h3>
                <div class="stat-number" style="color:#22c55e !important;"><?php echo (int)$stats['done']; ?></div>
              </div>
            </div>
          </section>

          <!-- Toggle button for repair form -->
          <div style="margin:1.5rem 0;">
            <button type="button" id="toggleFormBtn" style="white-space:nowrap;padding:0.8rem 1.5rem;cursor:pointer;font-size:1rem;background:#1e293b;border:1px solid #334155;color:#cbd5e1;border-radius:8px;transition:all 0.2s;box-shadow:0 2px 4px rgba(0,0,0,0.1);" onclick="toggleRepairForm()" onmouseover="this.style.background='#334155';this.style.borderColor='#475569'" onmouseout="this.style.background='#1e293b';this.style.borderColor='#334155'">
              <span id="toggleFormIcon">▼</span> <span id="toggleFormText">ซ่อนฟอร์ม</span>
            </button>
          </div>

          <section class="manage-panel" style="color:#f8fafc;" id="addRepairSection">
            <div class="section-header">
              <div>
                <h1>เพิ่มการแจ้งซ่อม</h1>
                <p style="margin-top:0.25rem;color:rgba(255,255,255,0.7);">เลือกห้อง/ผู้เช่า ระบุวันที่และรายละเอียดงานซ่อม</p>
              </div>
            </div>
            <form action="../Manage/process_repair.php" method="post" id="repairForm" enctype="multipart/form-data">
              <div class="repair-form">
                <div>
                  <label for="ctr_id">สัญญา / ห้องพัก <span style="color:#f87171;">*</span></label>
                  <select id="ctr_id" name="ctr_id" required>
                    <option value="">-- เลือกสัญญา --</option>
                    <?php foreach ($contracts as $ctr): ?>
                      <option value="<?php echo (int)$ctr['ctr_id']; ?>">
                        ห้อง <?php echo htmlspecialchars((string)($ctr['room_number'] ?? '-')); ?> - <?php echo htmlspecialchars($ctr['tnt_name'] ?? 'ไม่ระบุ'); ?>
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
                  <input type="text" id="repair_time" name="repair_time" readonly style="background:rgba(148,163,184,0.1); cursor:not-allowed; font-weight:600; font-size:1.1rem;" value="<?php echo date('H:i:s'); ?>" />
                  <input type="hidden" id="repair_time_hidden" name="repair_time_hidden" value="<?php echo date('H:i:s'); ?>" />
                </div>
                <input type="hidden" name="repair_status" value="0" />
                <div style="grid-column:1 / -1;">
                  <label for="repair_desc">รายละเอียด <span style="color:#f87171;">*</span></label>
                  <textarea id="repair_desc" name="repair_desc" required placeholder="เช่น แอร์ไม่เย็น น้ำรั่ว ซิงค์อุดตัน"></textarea>
                </div>
                <div style="grid-column:1 / -1;">
                  <label for="repair_image">รูปภาพการซ่อม (ไม่จำเป็น)</label>
                  <input type="file" id="repair_image" name="repair_image" accept="image/*" />
                  <small style="color:#94a3b8; display:block; margin-top:0.4rem;">สนับสนุน: JPG, PNG, WebP (ขนาดไม่เกิน 5MB)</small>
                  <div id="image_preview" style="margin-top:0.75rem;"></div>
                </div>
                <div class="repair-form-actions">
                  <button type="submit" class="animate-ui-add-btn" data-animate-ui-skip="true" data-no-modal="true" data-allow-submit="true" style="flex:2; cursor: pointer;">บันทึกการแจ้งซ่อม</button>
                  <button type="reset" class="animate-ui-action-btn delete" style="flex:1;">ล้างข้อมูล</button>
                </div>
              </div>
            </form>
          </section>

          <section class="manage-panel">
            <div class="section-header" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
              <div>
                <h1>รายการแจ้งซ่อมทั้งหมด</h1>
                <p style="color:#94a3b8;margin-top:0.2rem;">ดูสถานะและจัดการงานซ่อม</p>
              </div>
              <select id="sortSelect" onchange="changeSortBy(this.value)" style="padding:0.6rem 0.85rem;border-radius:8px;border:1px solid rgba(255,255,255,0.2);background:rgba(255,255,255,0.05);color:#f5f8ff;font-size:0.95rem;cursor:pointer;">
                <option value="newest" <?php echo ($sortBy === 'newest' ? 'selected' : ''); ?>>แจ้งล่าสุด</option>
                <option value="oldest" <?php echo ($sortBy === 'oldest' ? 'selected' : ''); ?>>แจ้งเก่าสุด</option>
                <option value="room_number" <?php echo ($sortBy === 'room_number' ? 'selected' : ''); ?>>หมายเลขห้อง</option>
              </select>
            </div>
            
            <!-- Status Filter Buttons -->
            <div style="display:flex;gap:0.75rem;margin-bottom:1.5rem;flex-wrap:wrap;">
              <button type="button" class="status-filter-btn" data-status="all" onclick="filterByStatus('all')" style="background:rgba(96,165,250,0.3);border:1.5px solid rgba(96,165,250,0.6);color:#60a5fa;font-weight:500;">
                ทั้งหมด <span style="margin-left:0.4rem;font-weight:600;">(<span class="count-all"><?php echo count($repairs); ?></span>)</span>
              </button>
              <button type="button" class="status-filter-btn" data-status="0" onclick="filterByStatus('0')" style="background:rgba(249,115,22,0.15);border:1px solid rgba(249,115,22,0.3);color:#f97316;">
                รอซ่อม <span style="margin-left:0.4rem;font-weight:600;">(<span class="count-0"><?php echo $stats['pending']; ?></span>)</span>
              </button>
              <button type="button" class="status-filter-btn" data-status="1" onclick="filterByStatus('1')" style="background:rgba(96,165,250,0.15);border:1px solid rgba(96,165,250,0.3);color:#60a5fa;">
                กำลังซ่อม <span style="margin-left:0.4rem;font-weight:600;">(<span class="count-1"><?php echo $stats['inprogress']; ?></span>)</span>
              </button>
              <button type="button" class="status-filter-btn" data-status="2" onclick="filterByStatus('2')" style="background:rgba(34,197,94,0.15);border:1px solid rgba(34,197,94,0.3);color:#22c55e;">
                ซ่อมเสร็จแล้ว <span style="margin-left:0.4rem;font-weight:600;">(<span class="count-2"><?php echo $stats['done']; ?></span>)</span>
              </button>
            </div>
            <div class="report-table">
              <table class="table--compact" id="table-repairs">
                <thead>
                  <tr>
                    <th>วันที่</th>
                    <th>รูปภาพ</th>
                    <th>ห้อง/ผู้แจ้ง</th>
                    <th>รายละเอียด</th>
                    <th>สถานะ</th>
                    <th class="crud-column">จัดการ</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($repairs)): ?>
                    <tr><td colspan="6" style="text-align:center;padding:1.5rem;color:#94a3b8;">ยังไม่มีรายการแจ้งซ่อม</td></tr>
                  <?php else: ?>
                    <?php foreach ($repairs as $r): ?>
                      <?php $status = (string)($r['repair_status'] ?? ''); ?>
                      <tr data-repair-id="<?php echo (int)$r['repair_id']; ?>">
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
                                  data-timestamp="<?php 
                                    if ($repairDateTime) {
                                      try {
                                        $tz = new DateTimeZone('Asia/Bangkok');
                                        $dt = new DateTime($repairDateTime, $tz);
                                        echo $dt->getTimestamp();
                                      } catch (Exception $e) {
                                        echo (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->getTimestamp();
                                      }
                                    } else {
                                      echo (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->getTimestamp();
                                    }
                                  ?>">
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
                          <div style="display:flex; align-items:center; justify-content:center; min-width:60px; min-height:60px;">
                            <?php if (!empty($r['repair_image'])): ?>
                              <img src="../Assets/Images/Repairs/<?php echo htmlspecialchars(basename($r['repair_image'])); ?>" 
                                   alt="รูปการซ่อม" 
                                   style="max-width:60px; max-height:60px; border-radius:6px; object-fit:cover;" />
                            <?php else: ?>
                              <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                                <circle cx="8.5" cy="8.5" r="1.5" />
                                <path d="M21 15l-5-5L5 21" />
                              </svg>
                            <?php endif; ?>
                          </div>
                        </td>
                        <td>
                          <div style="display:flex; flex-direction:column; gap:0.15rem;">
                            <span>ห้อง <?php echo htmlspecialchars((string)($r['room_number'] ?? '-')); ?></span>
                            <span style="color:#64748b; font-size:0.8rem;">ผู้แจ้ง: <?php echo htmlspecialchars($r['tnt_name'] ?? '-'); ?></span>
                          </div>
                        </td>
                        <td><?php echo nl2br(htmlspecialchars($r['repair_desc'] ?? '-')); ?></td>
                        <td><span class="status-badge" style="background: <?php echo $statusColors[$status] ?? '#94a3b8'; ?>; color:white; padding:0.4rem 0.8rem; border-radius:6px; font-size:0.85rem; font-weight:500; display:inline-block;"><?php echo $statusMap[$status] ?? 'ไม่ระบุ'; ?></span></td>
                        <td class="crud-column">
                          <div class="crud-actions" data-repair-id="<?php echo (int)$r['repair_id']; ?>">
                            <?php if ($status === '0'): ?>
                              <button type="button" class="animate-ui-action-btn edit repair-primary" onclick="updateRepairStatus(<?php echo (int)$r['repair_id']; ?>, '1')">ทำการซ่อม</button>
                              <button type="button" class="animate-ui-action-btn delete" onclick="updateRepairStatus(<?php echo (int)$r['repair_id']; ?>, '3')" style="margin-left:0.4rem;">ยกเลิก</button>
                            <?php elseif ($status === '1'): ?>
                              <button type="button" class="animate-ui-action-btn edit" onclick="updateRepairStatus(<?php echo (int)$r['repair_id']; ?>, '2')">ซ่อมเสร็จแล้ว</button>
                              <button type="button" class="animate-ui-action-btn delete" onclick="updateRepairStatus(<?php echo (int)$r['repair_id']; ?>, '3')" style="margin-left:0.4rem;">ยกเลิก</button>
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

    <script src="../Assets/Javascript/confirm-modal.js" defer></script>
    <script src="../Assets/Javascript/toast-notification.js" defer></script>
    <script src="../Assets/Javascript/animate-ui.js" defer></script>
    <script src="../Assets/Javascript/main.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4" defer></script>
    <script>
      // Get server time (Bangkok timezone) for accurate relative time calculation
      const serverTimeMs = <?php echo (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->getTimestamp() * 1000; ?>;
      const clientTimeAtPageLoadMs = new Date().getTime();
      const timeDiffMs = serverTimeMs - clientTimeAtPageLoadMs;
      
      function getServerTime() {
        return new Date(new Date().getTime() + timeDiffMs);
      }
      
      // Update relative time in real-time
      function updateRelativeTime() {
        const timeElements = document.querySelectorAll('[data-timestamp]');
        timeElements.forEach(element => {
          const timestamp = parseInt(element.getAttribute('data-timestamp'));
          if (isNaN(timestamp)) return;
          
          const targetDateMs = timestamp * 1000;
          const nowMs = getServerTime().getTime();
          const diffMs = nowMs - targetDateMs;
          const diffSecs = Math.floor(diffMs / 1000);
          const diffMins = Math.floor(diffSecs / 60);
          const diffHours = Math.floor(diffSecs / 3600);
          const diffDays = Math.floor(diffSecs / 86400);
          
          let label = '';
          let className = 'time-fresh';
          
          if (diffSecs < 60) {
            label = Math.max(1, diffSecs) + ' วินาทีที่แล้ว';
            className = 'time-fresh';
          } else if (diffMins < 60) {
            label = diffMins + ' นาทีที่แล้ว';
            className = 'time-fresh';
          } else if (diffHours < 24) {
            label = diffHours + ' ชม.ที่แล้ว';
            className = 'time-fresh';
          } else if (diffDays === 1) {
            label = 'เมื่อวาน';
            className = 'time-warning';
          } else if (diffDays === 2) {
            label = 'เมื่อวานซืน';
            className = 'time-warning';
          } else if (diffDays < 4) {
            label = diffDays + ' วันที่แล้ว';
            className = 'time-warning';
          } else if (diffDays < 30) {
            label = diffDays + ' วันที่แล้ว';
            className = 'time-danger';
          } else if (diffDays < 365) {
            const months = Math.floor(diffDays / 30);
            label = months + ' เดือนที่แล้ว';
            className = 'time-danger';
          } else {
            const years = Math.floor(diffDays / 365);
            label = years + ' ปีที่แล้ว';
            className = 'time-danger';
          }
          
          element.textContent = label;
          element.className = 'time-badge relative-time ' + className;
        });
      }
      
      // Update current time display
      function updateCurrentTime() {
        const timeInput = document.getElementById('repair_time');
        const timeHidden = document.getElementById('repair_time_hidden');
        if (timeInput) {
          const now = getServerTime();
          const hours = String(now.getHours()).padStart(2, '0');
          const minutes = String(now.getMinutes()).padStart(2, '0');
          const seconds = String(now.getSeconds()).padStart(2, '0');
          const timeStr = `${hours}:${minutes}:${seconds}`;
          timeInput.value = timeStr;
          if (timeHidden) {
            timeHidden.value = timeStr;
          }
        }
      }
      
      // Update every 100ms for smooth time display
      setInterval(updateCurrentTime, 100);
      setInterval(updateRelativeTime, 500);
      
      // Initial update
      document.addEventListener('DOMContentLoaded', () => {
        updateCurrentTime();
        updateRelativeTime();
      });
      
      function getStatusFromBadge(badge) {
        const style = badge.getAttribute('style');
        if (style.includes('#f97316')) return '0';
        if (style.includes('#60a5fa')) return '1';
        if (style.includes('#22c55e')) return '2';
        return '';
      }
      
      
      let currentFilter = 'all';
      
      // Update repair status
      function updateRepairStatus(repairId, newStatus) {
        // Show confirmation if cancelling
        if (newStatus === '3') {
          showConfirmDialog('ยืนยันการยกเลิก', 'คุณต้องการยกเลิกการแจ้งซ่อมนี้หรือไม่?', 'warning').then(confirmed => {
            if (confirmed) {
              performUpdateRepairStatus(repairId, newStatus);
            }
          });
        } else {
          performUpdateRepairStatus(repairId, newStatus);
        }
      }
      
      function performUpdateRepairStatus(repairId, newStatus) {
        // If cancelling, delete the repair and its image
        if (newStatus === '3') {
          const formData = new FormData();
          formData.append('repair_id', repairId);
          
          fetch('../Manage/delete_repair.php', {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              const row = document.querySelector(`tr[data-repair-id="${repairId}"]`);
              if (row) {
                row.remove();
              }
              showToast('สำเร็จ', 'ยกเลิกการแจ้งซ่อมและลบรูปภาพแล้ว', 'success');
              // Re-apply filter to check if empty message should show
              filterByStatus(currentFilter);
            } else {
              showToast('ผิดพลาด', data.error || 'ไม่สามารถลบได้', 'error');
            }
          })
          .catch(error => {
            console.error('Error:', error);
            showToast('ผิดพลาด', 'เกิดข้อผิดพลาดในการลบ', 'error');
          });
          return;
        }
        
        // For other status updates
        const formData = new FormData();
        formData.append('repair_id', repairId);
        formData.append('repair_status', newStatus);
        
        fetch('../Manage/update_repair_status_ajax.php', {
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
            showToast('ผิดพลาด', data.error || 'ไม่สามารถอัปเดตสถานะได้', 'error');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showToast('ผิดพลาด', 'เกิดข้อผิดพลาดในการส่งข้อมูล', 'error');
        });
      }
      
      function filterByStatus(status) {
        currentFilter = status;
        // Save filter status to localStorage
        localStorage.setItem('repairFilterStatus', status);
        
        const table = document.getElementById('table-repairs');
        if (!table) return;
        const rows = table.querySelectorAll('tbody tr');
        let visibleCount = 0;
        
        document.querySelectorAll('.status-filter-btn').forEach(btn => {
          const btnStatus = btn.getAttribute('data-status');
          if (btnStatus === status) {
            btn.classList.add('active');
            btn.style.opacity = '1';
            btn.style.transform = 'scale(1.05)';
          } else {
            btn.classList.remove('active');
            btn.style.opacity = '0.5';
            btn.style.transform = 'scale(1)';
          }
        });
        
        rows.forEach(row => {
          if (!row.getAttribute('data-repair-id')) return;
          if (status === 'all') {
            row.style.display = '';
            visibleCount++;
          } else {
            const statusBadge = row.querySelector('.status-badge');
            if (statusBadge && getStatusFromBadge(statusBadge) === status) {
              row.style.display = '';
              visibleCount++;
            } else {
              row.style.display = 'none';
            }
          }
        });
        
        const tbody = table.querySelector('tbody');
        let emptyMsg = tbody.querySelector('.empty-filter-message');
        if (visibleCount === 0) {
          if (!emptyMsg) {
            emptyMsg = document.createElement('tr');
            emptyMsg.className = 'empty-filter-message';
            emptyMsg.innerHTML = '<td colspan="6" style="text-align:center;padding:1.5rem;color:#94a3b8;">ไม่มีรายการสำหรับสถานะนี้</td>';
            tbody.appendChild(emptyMsg);
          }
          emptyMsg.style.display = '';
        } else if (emptyMsg) {
          emptyMsg.style.display = 'none';
        }
      }
      
      function changeSortBy(sortValue) {
        const url = new URL(window.location);
        url.searchParams.set('sort', sortValue);
        window.location.href = url.toString();
      }

      // Toggle repair form visibility
      function toggleRepairForm() {
        const section = document.getElementById('addRepairSection');
        const icon = document.getElementById('toggleFormIcon');
        const text = document.getElementById('toggleFormText');
        const isHidden = section.style.display === 'none';
        
        if (isHidden) {
          section.style.display = '';
          icon.textContent = '▼';
          text.textContent = 'ซ่อนฟอร์ม';
          localStorage.setItem('repairFormVisible', 'true');
        } else {
          section.style.display = 'none';
          icon.textContent = '▶';
          text.textContent = 'แสดงฟอร์ม';
          localStorage.setItem('repairFormVisible', 'false');
        }
      }
      
      document.addEventListener('DOMContentLoaded', () => {
        // Load saved filter status from localStorage
        const savedFilter = localStorage.getItem('repairFilterStatus') || 'all';
        
        // Apply saved filter or default
        filterByStatus(savedFilter);
        
        // Restore form visibility from localStorage
        const isFormVisible = localStorage.getItem('repairFormVisible') !== 'false';
        const section = document.getElementById('addRepairSection');
        const icon = document.getElementById('toggleFormIcon');
        const text = document.getElementById('toggleFormText');
        if (!isFormVisible) {
          section.style.display = 'none';
          icon.textContent = '▶';
          text.textContent = 'แสดงฟอร์ม';
        }

        // Initialize DataTable
        const repairTableEl = document.querySelector('#table-repairs');
        if (repairTableEl && window.simpleDatatables) {
          try {
            const dt = new simpleDatatables.DataTable(repairTableEl, {
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
                { select: 5, sortable: false }
              ]
            });
            window.__repairDataTable = dt;
          } catch (err) {
            console.error('Failed to init repair table', err);
          }
        }
        
        const imageInput = document.getElementById('repair_image');
        const imagePreview = document.getElementById('image_preview');
        if (imageInput) {
          imageInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            imagePreview.innerHTML = '';
            if (file) {
              if (file.size > 5 * 1024 * 1024) {
                imagePreview.innerHTML = '<div style="color:#ef4444; font-size:0.85rem;">❌ ไฟล์ใหญ่เกินไป (ไม่เกิน 5MB)</div>';
                imageInput.value = '';
                return;
              }
              if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                imagePreview.innerHTML = '<div style="color:#ef4444; font-size:0.85rem;">❌ ประเภทไฟล์ไม่ถูกต้อง</div>';
                imageInput.value = '';
                return;
              }
              const reader = new FileReader();
              reader.onload = (event) => {
                imagePreview.innerHTML = `<div style="display:flex; flex-direction:column; gap:0.5rem;"><img src="${event.target.result}" alt="Preview" style="max-width:200px; max-height:200px; border-radius:8px; object-fit:contain;" /><div style="color:#22c55e; font-size:0.85rem;">✓ ${file.name}</div></div>`;
              };
              reader.readAsDataURL(file);
            }
          });
        }
      });

      // Sync hidden fields on form submit
      document.getElementById('repairForm').addEventListener('submit', function(e) {
        const ctr_id = document.getElementById('ctr_id').value;
        const repair_desc = document.getElementById('repair_desc').value.trim();
        
        console.log('Form submitting with:', {
          ctr_id: ctr_id,
          repair_desc: repair_desc,
          repair_time: document.getElementById('repair_time_hidden').value
        });
        
        if (!ctr_id || !repair_desc) {
          console.warn('Form validation failed: ctr_id=' + ctr_id + ', desc=' + repair_desc);
        }
        
        updateCurrentTime(); // Ensure latest time is in hidden fields
      });
      
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
      window.addEventListener('storage', applyThemeClass);
    </script>
  </body>
</html>
