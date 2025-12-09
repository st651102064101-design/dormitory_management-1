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
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'start_date';
$orderBy = 'c.ctr_start DESC, c.ctr_id DESC';

switch ($sortBy) {
  case 'room_number':
    $orderBy = 'r.room_number ASC';
    break;
  case 'tenant_name':
    $orderBy = 't.tnt_name ASC';
    break;
  case 'start_date':
  default:
    $orderBy = 'c.ctr_start DESC, c.ctr_id DESC';
}

// ดึงข้อมูลสัญญา
$ctrStmt = $pdo->query("\n  SELECT c.*,\n         t.tnt_name, t.tnt_phone,\n         r.room_number, r.room_status,\n         rt.type_name\n  FROM contract c\n  LEFT JOIN tenant t ON c.tnt_id = t.tnt_id\n  LEFT JOIN room r ON c.room_id = r.room_id\n  LEFT JOIN roomtype rt ON r.type_id = rt.type_id\n  ORDER BY $orderBy\n");
$contracts = $ctrStmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงข้อมูลผู้เช่าและห้องสำหรับฟอร์มสร้างสัญญา
$tenants = $pdo->query("SELECT tnt_id, tnt_name, tnt_phone FROM tenant ORDER BY tnt_name")->fetchAll(PDO::FETCH_ASSOC);
$rooms = $pdo->query("SELECT room_id, room_number, room_status FROM room ORDER BY room_number")->fetchAll(PDO::FETCH_ASSOC);

$statusMap = [
  '0' => 'ปกติ',
  '1' => 'ยกเลิกแล้ว',
  '2' => 'แจ้งยกเลิก',
];
$statusColors = [
  '0' => '#22c55e',
  '1' => '#ef4444',
  '2' => '#f97316',
];

$stats = [
  'active' => 0,
  'cancelled' => 0,
  'notice' => 0,
];
foreach ($contracts as $ctr) {
    $status = (string)($ctr['ctr_status'] ?? '');
    if ($status === '0') {
        $stats['active']++;
    } elseif ($status === '1') {
        $stats['cancelled']++;
    } elseif ($status === '2') {
        $stats['notice']++;
    }
}

// ฟังก์ชันแปลงวันที่เป็นรูปแบบไทย (เช่น 1 ม.ค. 68)
function formatThaiDate($dateStr) {
    if (!$dateStr) return '-';
    
    $thaiMonths = [
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
        5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
        9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
    ];
    
    $timestamp = strtotime($dateStr);
    if (!$timestamp) return '-';
    
    $day = (int)date('j', $timestamp);
    $month = (int)date('n', $timestamp);
    $year = (int)date('Y', $timestamp) + 543; // แปลงเป็น พ.ศ.
    $yearShort = $year - 2500; // แสดงแค่ 2 หัก (เช่น 2568 -> 68)
    
    return $day . ' ' . $thaiMonths[$month] . ' ' . $yearShort;
}

// ฟังก์ชันคำนวณระยะเวลาสัญญาและแสดงช่วงวันที่
function formatContractPeriod($startDate, $endDate) {
    if (!$startDate || !$endDate) return '-';
    
    $start = strtotime($startDate);
    $end = strtotime($endDate);
    
    if (!$start || !$end) return '-';
    
    // คำนวณจำนวนวัน
    $diffDays = ($end - $start) / (60 * 60 * 24);
    
    // คำนวณจำนวนเดือนและปี
    $startDate = new DateTime($startDate);
    $endDate = new DateTime($endDate);
    $interval = $startDate->diff($endDate);
    
    $years = $interval->y;
    $months = $interval->m;
    
    // สร้างข้อความระยะเวลา
    $duration = [];
    if ($years > 0) {
        $duration[] = $years . ' ปี';
    }
    if ($months > 0) {
        $duration[] = $months . ' เดือน';
    }
    if (empty($duration)) {
        $duration[] = ceil($diffDays) . ' วัน';
    }
    
    $durationText = implode(', ', $duration);
    
    // แสดงวันที่แบบไทย
    $startFormatted = formatThaiDate($startDate->format('Y-m-d'));
    $endFormatted = formatThaiDate($endDate->format('Y-m-d'));
    
    return '<div style="text-align:center;">' . $durationText . '<br><span style="color:#94a3b8;font-size:0.85rem;">' . $startFormatted . ' - ' . $endFormatted . '</span></div>';
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
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - จัดการสัญญาเช่า</title>
    <link rel="icon" type="image/jpeg" href="../Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="../Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="../Assets/Css/main.css" />
    <link rel="stylesheet" href="../Assets/Css/confirm-modal.css" />
    <style>
      .contract-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
      }
      .contract-stat-card {
        background: linear-gradient(135deg, rgba(18,24,40,0.85), rgba(7,13,26,0.95));
        border-radius: 16px;
        padding: 1.25rem;
        border: 1px solid rgba(255,255,255,0.08);
        color: #f5f8ff;
        box-shadow: 0 15px 35px rgba(3,7,18,0.4);
      }
      .contract-stat-card h3 {
        margin: 0;
        font-size: 0.95rem;
        color: rgba(255,255,255,0.7);
      }
      .contract-stat-card .stat-value {
        font-size: 2.5rem;
        font-weight: 700;
        margin-top: 0.5rem;
      }
      .contract-stat-card .stat-chip {
        margin-top: 1rem;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.85rem;
        padding: 0.2rem 0.75rem;
        border-radius: 999px;
        background: rgba(255,255,255,0.1);
      }
      .contract-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1rem;
        margin-top: 1.5rem;
      }
      .contract-form-group label {
        color: rgba(255,255,255,0.8);
        font-weight: 600;
        display: block;
        margin-bottom: 0.4rem;
      }
      .contract-form-group input,
      .contract-form-group select {
        width: 100%;
        padding: 0.75rem 0.85rem;
        border-radius: 10px;
        border: 1px solid rgba(255,255,255,0.15);
        background: rgba(8,12,24,0.85);
        color: #f5f8ff;
      }
      .contract-form-group input:focus,
      .contract-form-group select:focus {
        outline: none;
        border-color: #60a5fa;
        box-shadow: 0 0 0 3px rgba(96,165,250,0.25);
      }
      .contract-form-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-top: 1.5rem;
      }
      .contract-form-actions button {
        flex: 1;
        min-width: 180px;
      }
      .status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 90px;
        padding: 0.25rem 0.85rem;
        border-radius: 999px;
        font-size: 0.85rem;
        font-weight: 600;
        color: #fff;
      }
      .status-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
      }
      .reports-page .manage-panel { margin-top: 1.4rem; margin-bottom: 1.4rem; background: #0f172a; border: 1px solid rgba(148,163,184,0.2); box-shadow: 0 12px 30px rgba(0,0,0,0.2); }
      .reports-page .manage-panel:first-of-type { margin-top: 0.2rem; }
      .notice-banner {
        margin-top: 0.75rem;
        padding: 0.75rem 1rem;
        border-radius: 0.75rem;
        background: rgba(249,115,22,0.12);
        color: #fb923c;
        font-size: 0.9rem;
      }
      .contract-table-room {
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
      }
      .contract-room-meta {
        font-size: 0.75rem;
        color: #64748b;
      }
      #table-contracts tbody tr {
        transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
      }
      #table-contracts tbody tr.removing {
        opacity: 0;
        transform: translateX(-50px) scale(0.8);
        background: rgba(239, 68, 68, 0.1);
      }
    </style>
  </head>
  <body class="reports-page">
    <div class="app-shell">
      <?php include __DIR__ . '/../includes/sidebar.php'; ?>
      <main class="app-main">
        <div>
          <?php 
            $pageTitle = 'จัดการสัญญาเช่า';
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
            <div class="section-header">
              <div>
                <p style="color:#94a3b8;margin-top:0.2rem;">ติดตามสถานะสัญญาและจำนวนที่ต้องดำเนินการ</p>
              </div>
            </div>
            <div class="contract-stats">
              <div class="contract-stat-card">
                <h3>ใช้งานปกติ</h3>
                <div class="stat-value"><?php echo number_format($stats['active']); ?></div>
                <div class="stat-chip">
                  <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#22c55e;"></span>
                  สถานะปกติ
                </div>
              </div>
              <div class="contract-stat-card">
                <h3>แจ้งยกเลิก</h3>
                <div class="stat-value"><?php echo number_format($stats['notice']); ?></div>
                <div class="stat-chip">
                  <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#f97316;"></span>
                  รอดำเนินการ
                </div>
              </div>
              <div class="contract-stat-card">
                <h3>ยกเลิกแล้ว</h3>
                <div class="stat-value"><?php echo number_format($stats['cancelled']); ?></div>
                <div class="stat-chip">
                  <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#ef4444;"></span>
                  ปิดสัญญาเรียบร้อย
                </div>
              </div>
            </div>
          </section>

          <!-- Toggle button for contract form -->
          <div style="margin:1.5rem 0;">
            <button type="button" id="toggleContractFormBtn" style="white-space:nowrap;padding:0.8rem 1.5rem;cursor:pointer;font-size:1rem;background:#1e293b;border:1px solid #334155;color:#cbd5e1;border-radius:8px;transition:all 0.2s;box-shadow:0 2px 4px rgba(0,0,0,0.1);" onclick="toggleContractForm()" onmouseover="this.style.background='#334155';this.style.borderColor='#475569'" onmouseout="this.style.background='#1e293b';this.style.borderColor='#334155'">
              <span id="toggleContractFormIcon">▼</span> <span id="toggleContractFormText">ซ่อนฟอร์ม</span>
            </button>
          </div>

          <section class="manage-panel" style="background:linear-gradient(135deg, rgba(15,23,42,0.95), rgba(2,6,23,0.95)); color:#f8fafc;" id="addContractSection">
            <div class="section-header">
              <div>
                <h1>ทำสัญญาใหม่</h1>
                <p style="margin-top:0.25rem;color:rgba(255,255,255,0.7);">ระบุผู้เช่า ห้อง วันที่เริ่ม-สิ้นสุด และเงินมัดจำ</p>
              </div>
            </div>
            <form action="../Manage/process_contract.php" method="post" data-allow-submit>
              <div class="contract-form">
                <div class="contract-form-group">
                  <label for="tnt_id">ผู้เช่า <span style="color:#f87171;">*</span></label>
                  <select name="tnt_id" id="tnt_id" required>
                    <option value="">-- เลือกผู้เช่า --</option>
                    <?php foreach ($tenants as $tenant): ?>
                      <option value="<?php echo htmlspecialchars($tenant['tnt_id']); ?>">
                        <?php echo htmlspecialchars($tenant['tnt_name']); ?> (<?php echo htmlspecialchars($tenant['tnt_phone']); ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="contract-form-group">
                  <label for="room_id">ห้องพัก <span style="color:#f87171;">*</span></label>
                  <select name="room_id" id="room_id" required>
                    <option value="">-- เลือกห้องพัก --</option>
                    
                    <?php
                    // แยกห้องตามสถานะ
                    $availableRooms = array_filter($rooms, fn($r) => $r['room_status'] === '0');
                    $occupiedRooms = array_filter($rooms, fn($r) => $r['room_status'] !== '0');
                    ?>
                    
                    <?php if (!empty($availableRooms)): ?>
                      <optgroup label="ห้องว่าง">
                        <?php foreach ($availableRooms as $room): ?>
                          <option value="<?php echo (int)$room['room_id']; ?>" data-room-status="0">
                            ห้อง <?php echo htmlspecialchars((string)$room['room_number']); ?>
                          </option>
                        <?php endforeach; ?>
                      </optgroup>
                    <?php endif; ?>
                    
                    <?php if (!empty($occupiedRooms)): ?>
                      <optgroup label="ห้องไม่ว่าง">
                        <?php foreach ($occupiedRooms as $room): ?>
                          <option value="<?php echo (int)$room['room_id']; ?>" data-room-status="1">
                            ห้อง <?php echo htmlspecialchars((string)$room['room_number']); ?>
                          </option>
                        <?php endforeach; ?>
                      </optgroup>
                    <?php endif; ?>
                  </select>
                </div>
                <div class="contract-form-group">
                  <label for="ctr_start">วันที่เริ่มสัญญา <span style="color:#f87171;">*</span></label>
                  <input type="date" id="ctr_start" name="ctr_start" required value="<?php echo date('Y-m-d'); ?>" />
                </div>
                <div class="contract-form-group">
                  <label for="ctr_end">วันที่สิ้นสุด <span style="color:#f87171;">*</span></label>
                  <input type="date" id="ctr_end" name="ctr_end" required />
                  <div style="display:flex;gap:0.3rem;margin-top:0.5rem;flex-wrap:wrap;">
                    <button type="button" class="quick-date-btn" data-months="3">+3 เดือน</button>
                    <button type="button" class="quick-date-btn" data-months="6">+6 เดือน</button>
                    <button type="button" class="quick-date-btn" data-months="9">+9 เดือน</button>
                    <button type="button" class="quick-date-btn" data-months="12">+1 ปี</button>
                  </div>
                </div>
                <style>
                  .quick-date-btn {
                    padding: 0.3rem 0.6rem;
                    font-size: 0.75rem;
                    border-radius: 6px;
                    border: 1px solid rgba(96, 165, 250, 0.4);
                    background: rgba(59, 130, 246, 0.1);
                    color: #60a5fa;
                    cursor: pointer;
                    transition: all 0.2s;
                  }
                  .quick-date-btn:hover {
                    background: rgba(59, 130, 246, 0.2);
                    border-color: rgba(96, 165, 250, 0.6);
                  }
                </style>
                <div class="contract-form-group">
                  <label for="ctr_deposit">เงินมัดจำ (บาท)</label>
                  <input type="number" id="ctr_deposit" name="ctr_deposit" min="0" step="500" placeholder="เช่น 5000" value="2000" />
                </div>
              </div>
              <div class="contract-form-actions">
                <button type="submit" class="animate-ui-add-btn" style="flex:2;">
                  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></svg>
                  บันทึกสัญญา
                </button>
                <button type="reset" class="animate-ui-action-btn delete" style="flex:1;">ล้างข้อมูล</button>
              </div>
              <div id="room-status-hint" class="notice-banner" style="display:none;"></div>
            </form>
          </section>

          <section class="manage-panel">
            <div class="section-header" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
              <div>
                <h1>รายการสัญญาทั้งหมด</h1>
                <p style="color:#94a3b8;margin-top:0.2rem;">อัปเดตสถานะหรือพิมพ์เอกสารได้จากที่นี่</p>
              </div>
              <select id="sortSelect" onchange="changeSortBy(this.value)" style="padding:0.6rem 0.85rem;border-radius:8px;border:1px solid rgba(255,255,255,0.2);background:rgba(255,255,255,0.05);color:#f5f8ff;font-size:0.95rem;cursor:pointer;">
                <option value="start_date" <?php echo ($sortBy === 'start_date' ? 'selected' : ''); ?>>วันที่เพิ่มล่าสุด</option>
                <option value="room_number" <?php echo ($sortBy === 'room_number' ? 'selected' : ''); ?>>หมายเลขห้อง</option>
                <option value="tenant_name" <?php echo ($sortBy === 'tenant_name' ? 'selected' : ''); ?>>ชื่อผู้เช่า</option>
              </select>
            </div>
            <div class="report-table">
              <table class="table--compact" id="table-contracts">
                <thead>
                  <tr>
                    <th>เลขที่สัญญา</th>
                    <th>ผู้เช่า</th>
                    <th>ห้องพัก</th>
                    <th style="text-align:center;">ช่วงสัญญา</th>
                    <th>เงินมัดจำ</th>
                    <th>สถานะ</th>
                    <th class="crud-column">จัดการ</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($contracts)): ?>
                    <tr>
                      <td colspan="7" style="text-align:center;padding:2rem;color:#64748b;">ยังไม่มีข้อมูลสัญญา</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($contracts as $ctr): ?>
                      <tr>
                        <td>#<?php echo str_pad((string)$ctr['ctr_id'], 4, '0', STR_PAD_LEFT); ?></td>
                        <td>
                          <div><?php echo htmlspecialchars($ctr['tnt_name'] ?? 'ไม่พบข้อมูล'); ?></div>
                          <div class="contract-room-meta">โทร <?php echo htmlspecialchars($ctr['tnt_phone'] ?? '-'); ?></div>
                        </td>
                        <td>
                          <div class="contract-table-room">ห้อง <?php echo htmlspecialchars((string)($ctr['room_number'] ?? '-')); ?></div>
                          <div class="contract-room-meta">ประเภท: <?php echo htmlspecialchars($ctr['type_name'] ?? '-'); ?></div>
                        </td>
                        <td>
                          <?php 
                            echo formatContractPeriod($ctr['ctr_start'], $ctr['ctr_end']);
                          ?>
                        </td>
                        <td>฿<?php echo number_format((int)($ctr['ctr_deposit'] ?? 0)); ?></td>
                        <td>
                          <?php $status = (string)($ctr['ctr_status'] ?? ''); ?>
                          <span class="status-badge" style="background: <?php echo $statusColors[$status] ?? '#94a3b8'; ?>;">
                            <?php echo $statusMap[$status] ?? 'ไม่ระบุ'; ?>
                          </span>
                        </td>
                        <td class="crud-column">
                          <div class="status-actions" style="display:flex;flex-direction:column;gap:0.5rem;">
                            <div style="display:flex;gap:0.5rem;">
                              <a href="print_contract.php?ctr_id=<?php echo (int)$ctr['ctr_id']; ?>" target="_blank" style="flex:1;text-align:center;text-decoration:none;padding:0.75rem 1rem;font-size:0.9rem;background:#1e293b;border:1px solid #334155;color:#cbd5e1;border-radius:8px;transition:all 0.2s;box-shadow:0 2px 4px rgba(0,0,0,0.1);cursor:pointer;line-height:1.4;" onmouseover="this.style.background='#334155';this.style.borderColor='#475569'" onmouseout="this.style.background='#1e293b';this.style.borderColor='#334155'">🖨️ พิมพ์</a>
                            </div>
                            <div class="status-actions" style="display:flex;gap:0.5rem;">
                              <?php if ($status === '0'): ?>
                                <button type="button" class="animate-ui-action-btn delete" onclick="updateContractStatus(<?php echo (int)$ctr['ctr_id']; ?>, '2')" style="flex:1;">แจ้งยกเลิก</button>
                                <button type="button" class="animate-ui-action-btn delete" onclick="updateContractStatus(<?php echo (int)$ctr['ctr_id']; ?>, '1')" style="flex:1;">ยกเลิกทันที</button>
                              <?php elseif ($status === '2'): ?>
                                <button type="button" class="animate-ui-action-btn edit" onclick="updateContractStatus(<?php echo (int)$ctr['ctr_id']; ?>, '0')" style="flex:1;">กลับเป็นปกติ</button>
                                <button type="button" class="animate-ui-action-btn delete" onclick="updateContractStatus(<?php echo (int)$ctr['ctr_id']; ?>, '1')" style="flex:1;">ยกเลิกสัญญา</button>
                              <?php elseif ($status === '1'): ?>
                                <button type="button" class="animate-ui-action-btn edit" onclick="updateContractStatus(<?php echo (int)$ctr['ctr_id']; ?>, '0')" style="flex:1;">เปิดใช้งานใหม่</button>
                              <?php endif; ?>
                            </div>
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

    <script>
      function changeSortBy(sortValue) {
        const url = new URL(window.location);
        url.searchParams.set('sort', sortValue);
        window.location.href = url.toString();
      }

      // Toggle contract form visibility
      function toggleContractForm() {
        const section = document.getElementById('addContractSection');
        const icon = document.getElementById('toggleContractFormIcon');
        const text = document.getElementById('toggleContractFormText');
        const isHidden = section.style.display === 'none';
        
        if (isHidden) {
          section.style.display = '';
          icon.textContent = '▼';
          text.textContent = 'ซ่อนฟอร์ม';
          localStorage.setItem('contractFormVisible', 'true');
        } else {
          section.style.display = 'none';
          icon.textContent = '▶';
          text.textContent = 'แสดงฟอร์ม';
          localStorage.setItem('contractFormVisible', 'false');
        }
      }

      // ปิดการทำงานของ modal ใน main.js
      document.addEventListener('DOMContentLoaded', () => {
        // Restore form visibility from localStorage
        const isFormVisible = localStorage.getItem('contractFormVisible') !== 'false';
        const section = document.getElementById('addContractSection');
        const icon = document.getElementById('toggleContractFormIcon');
        const text = document.getElementById('toggleContractFormText');
        if (!isFormVisible) {
          section.style.display = 'none';
          icon.textContent = '▶';
          text.textContent = 'แสดงฟอร์ม';
        }

        // ลบ modal overlay ที่ main.js สร้างขึ้น
        const mainModals = document.querySelectorAll('.animate-ui-modal-overlay');
        mainModals.forEach(modal => modal.remove());
        
        // ลบ modal ทุกประเภทที่ไม่ต้องการ
        setInterval(() => {
          document.querySelectorAll('.animate-ui-modal-overlay, .confirm-overlay').forEach(el => {
            // เช็คว่าเป็น modal ที่เราต้องการหรือไม่
            const title = el.querySelector('.confirm-title, h3');
            if (title && title.textContent !== 'ยืนยันการเปลี่ยนสถานะสัญญา') {
              el.remove();
            }
          });
        }, 50);
      });
    </script>
    <script src="../Assets/Javascript/animate-ui.js" defer></script>
    <script src="../Assets/Javascript/main.js" defer></script>
    <script src="../Assets/Javascript/confirm-modal.js"></script>
    <script>

      // ฟังก์ชันอัพเดทสถิติ
      function updateStats(contractId = null) {
        fetch(window.location.href)
          .then(response => response.text())
          .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // อัพเดทค่าสถิติ
            const statCards = document.querySelectorAll('.contract-stat-card .stat-value');
            const newStats = doc.querySelectorAll('.contract-stat-card .stat-value');
            
            if (statCards.length === newStats.length) {
              statCards.forEach((card, index) => {
                card.textContent = newStats[index].textContent;
              });
            }
            
            // อัพเดทตาราง with animation
            const newTable = doc.querySelector('#table-contracts tbody');
            const currentTable = document.querySelector('#table-contracts tbody');
            if (newTable && currentTable) {
              // ถ้ามีสัญญาที่ถูกอัพเดท ให้ทำ animation ก่อน
              if (contractId) {
                const targetRow = currentTable.querySelector(`tr:has(button[onclick*="${contractId}"])`);
                if (targetRow) {
                  targetRow.classList.add('removing');
                  // รอ animation เสร็จก่อนอัพเดท DOM
                  setTimeout(() => {
                    currentTable.innerHTML = newTable.innerHTML;
                  }, 500); // ตรงกับเวลาใน CSS transition
                  return;
                }
              }
              // ถ้าไม่มี animation ให้อัพเดทเลย
              currentTable.innerHTML = newTable.innerHTML;
            }
          })
          .catch(error => {
            console.error('Error updating stats:', error);
          });
      }

      async function updateContractStatus(contractId, newStatus) {
        const labelMap = { '0': 'สถานะปกติ', '1': 'ยกเลิกสัญญา', '2': 'แจ้งยกเลิก' };
        const confirmText = labelMap[newStatus] || 'อัปเดต';
        
        const confirmed = await showConfirmDialog(
          'ยืนยันการเปลี่ยนสถานะสัญญา',
          `คุณต้องการเปลี่ยนสัญญานี้เป็น <strong>"${confirmText}"</strong> หรือไม่?`,
          'warning'
        );
        
        if (!confirmed) {
          console.log('User cancelled update contract status');
          return;
        }
        
        try {
          const formData = new FormData();
          formData.append('ctr_id', contractId);
          formData.append('ctr_status', newStatus);
          
          const response = await fetch('../Manage/update_contract_status.php', {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          });
          
          const result = await response.json();
          console.log('Response:', result);
          
          if (result.success) {
            // แสดง toast สำเร็จ
            if (typeof showSuccessToast === 'function') {
              showSuccessToast(result.message || 'เปลี่ยนสถานะเรียบร้อยแล้ว');
            }
            
            // อัพเดทสถิติและตาราง พร้อม animation
            setTimeout(() => {
              updateStats(contractId);
            }, 500);
          } else {
            // แสดง error
            if (typeof showErrorToast === 'function') {
              showErrorToast(result.error || 'เกิดข้อผิดพลาด');
            }
          }
          
        } catch (error) {
          console.error('Error:', error);
          if (typeof showErrorToast === 'function') {
            showErrorToast('เกิดข้อผิดพลาดในการอัพเดทสถานะ');
          }
        }
      }

      (function setupFormHelpers() {
        const roomSelect = document.getElementById('room_id');
        const hint = document.getElementById('room-status-hint');
        if (roomSelect && hint) {
          roomSelect.addEventListener('change', () => {
            const opt = roomSelect.options[roomSelect.selectedIndex];
            const status = opt ? opt.dataset.roomStatus : null;
            if (!status) {
              hint.style.display = 'none';
              return;
            }
            if (status === '0') {
              hint.style.display = 'none';
            } else {
              hint.textContent = 'หมายเหตุ: ห้องนี้ไม่ว่าง หากทำสัญญาใหม่ระบบจะถือว่าเริ่มใช้งานทันที';
              hint.style.display = 'block';
            }
          });
        }
        const startInput = document.getElementById('ctr_start');
        const endInput = document.getElementById('ctr_end');
        
        // ฟังก์ชันคำนวณวันที่สิ้นสุด (อย่างน้อย 1 เดือนหลังวันเริ่ม)
        function calculateMinEndDate(startDate) {
          const date = new Date(startDate);
          date.setMonth(date.getMonth() + 1);
          date.setDate(date.getDate() + 1); // +1 วันเพื่อห้ามเดือนเดียวกัน
          return date.toISOString().split('T')[0];
        }
        
        // ฟังก์ชันเพิ่มเดือน
        function addMonths(startDate, months) {
          const date = new Date(startDate);
          date.setMonth(date.getMonth() + months);
          return date.toISOString().split('T')[0];
        }
        
        if (startInput && endInput) {
          // ตั้งค่าเริ่มต้น: วันสิ้นสุด = 6 เดือนหลังวันเริ่ม
          const initialEndDate = addMonths(startInput.value, 6);
          endInput.value = initialEndDate;
          endInput.min = calculateMinEndDate(startInput.value);
          
          // เมื่อเปลี่ยนวันเริ่มสัญญา
          startInput.addEventListener('change', () => {
            const minEnd = calculateMinEndDate(startInput.value);
            endInput.min = minEnd;
            
            // ถ้าวันสิ้นสุดน้อยกว่าขั้นต่ำ ให้ตั้งเป็น 6 เดือนหลังวันเริ่ม
            if (!endInput.value || endInput.value < minEnd) {
              endInput.value = addMonths(startInput.value, 6);
            }
          });
          
          // ปุ่มทางลัด +6 เดือน, +1 ปี
          document.querySelectorAll('.quick-date-btn').forEach(btn => {
            btn.addEventListener('click', () => {
              const months = parseInt(btn.dataset.months);
              const startDate = startInput.value;
              if (startDate) {
                endInput.value = addMonths(startDate, months);
              }
            });
          });
        }
        
        // Form submission handler with AJAX
        const contractForm = document.querySelector('form[action="../Manage/process_contract.php"]');
        if (contractForm) {
          contractForm.addEventListener('submit', async (e) => {
            e.preventDefault(); // ป้องกันการรีเฟรชหน้า
            
            const tntId = document.getElementById('tnt_id').value;
            const roomId = document.getElementById('room_id').value;
            const ctrStart = document.getElementById('ctr_start').value;
            const ctrEnd = document.getElementById('ctr_end').value;
            const ctrDeposit = document.getElementById('ctr_deposit').value;
            
            // Validation
            if (!tntId || !roomId || !ctrStart || !ctrEnd) {
              if (typeof showErrorToast === 'function') {
                showErrorToast('กรุณากรอกข้อมูลให้ครบถ้วน');
              } else {
                alert('กรุณากรอกข้อมูลให้ครบถ้วน');
              }
              return;
            }
            
            if (ctrEnd < ctrStart) {
              if (typeof showErrorToast === 'function') {
                showErrorToast('วันที่สิ้นสุดต้องไม่น้อยกว่าวันที่เริ่มสัญญา');
              } else {
                alert('วันที่สิ้นสุดต้องไม่น้อยกว่าวันที่เริ่มสัญญา');
              }
              return;
            }
            
            // ปิดปุ่มชั่วคราว
            const submitBtn = contractForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<svg style="animation: spin 1s linear infinite; display: inline-block;" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> กำลังบันทึก...';
            
            try {
              const formData = new FormData(contractForm);
              const response = await fetch('../Manage/process_contract.php', {
                method: 'POST',
                body: formData,
                headers: {
                  'X-Requested-With': 'XMLHttpRequest'
                }
              });
              
              const result = await response.json();
              console.log('Response:', result);
              
              if (result.success) {
                // สำเร็จ - แสดง toast และรีเซ็ตฟอร์ม
                if (typeof showSuccessToast === 'function') {
                  showSuccessToast(result.message || 'บันทึกสัญญาเรียบร้อยแล้ว');
                } else {
                  alert(result.message || 'บันทึกสัญญาเรียบร้อยแล้ว');
                }
                
                // รีเซ็ตฟอร์ม
                contractForm.reset();
                document.getElementById('room-status-hint').style.display = 'none';
                
                // อัพเดทข้อมูลโดยไม่รีโหลดหน้า
                setTimeout(() => {
                  updateStats();
                }, 500);
              } else {
                if (typeof showErrorToast === 'function') {
                  showErrorToast(result.error || 'เกิดข้อผิดพลาด');
                } else {
                  alert(result.error || 'เกิดข้อผิดพลาด');
                }
              }
            } catch (error) {
              console.error('Error:', error);
              if (typeof showErrorToast === 'function') {
                showErrorToast('เกิดข้อผิดพลาดในการบันทึกข้อมูล');
              } else {
                alert('เกิดข้อผิดพลาดในการบันทึกข้อมูล');
              }
            } finally {
              submitBtn.disabled = false;
              submitBtn.innerHTML = originalText;
            }
          });
        }
      })();
    </script>
    <script src="../Assets/Javascript/toast-notification.js"></script>
  </body>
</html>
