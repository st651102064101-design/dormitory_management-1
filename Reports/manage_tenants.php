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
$orderBy = 'tnt_ceatetime DESC';

switch ($sortBy) {
  case 'newest':
    $orderBy = 'tnt_ceatetime DESC';
    break;
  case 'oldest':
    $orderBy = 'tnt_ceatetime ASC';
    break;
  case 'name_asc':
    $orderBy = 'tnt_name ASC';
    break;
  case 'name_desc':
    $orderBy = 'tnt_name DESC';
    break;
  case 'status_name':
  default:
    $orderBy = '(tnt_status = \'1\') DESC, (tnt_status = \'2\') DESC, (tnt_status = \'3\') DESC, (tnt_status = \'4\') DESC, tnt_name ASC';
}

$tenants = $pdo->query("SELECT tnt_id, tnt_name, tnt_age, tnt_address, tnt_phone, tnt_education, tnt_faculty, tnt_year, tnt_vehicle, tnt_parent, tnt_parentsphone, tnt_status FROM tenant ORDER BY $orderBy")
                ->fetchAll(PDO::FETCH_ASSOC);

$statusMap = [
  '1' => 'พักอยู่',
  '2' => 'รอการเข้าพัก',
  '3' => 'จองห้อง',
  '4' => 'ยกเลิกจองห้อง',
  '0' => 'ย้ายออก',
];

$stats = [
  'total' => count($tenants),
  'active' => 0,
  'pending' => 0,
  'booking' => 0,
  'cancel_booking' => 0,
  'inactive' => 0,
];
foreach ($tenants as $t) {
    if (($t['tnt_status'] ?? '0') === '1') {
        $stats['active']++;
    } elseif (($t['tnt_status'] ?? '0') === '2') {
        $stats['pending']++;
    } elseif (($t['tnt_status'] ?? '0') === '3') {
        $stats['booking']++;
    } elseif (($t['tnt_status'] ?? '0') === '4') {
        $stats['cancel_booking']++;
    } else {
        $stats['inactive']++;
    }
}

// Default dropdown options (hardcoded list for Muang Phetchabun)
$defaultEducations = [
  'วิทยาลัยเทคนิคเพชรบูรณ์',
  'โรงเรียนเพชรบูรณ์พิทยาลัย',
  'วิทยาลัยอาชีวศึกษาเพชรบูรณ์',
  'โรงเรียนบ้านหลวง',
];

$defaultFaculties = [
  'วิศวกรรมไฟฟ้า',
  'วิศวกรรมเครื่องกล',
  'เทคโนโลยีสารสนเทศ',
  'ศิลปศาสตร์',
  'วิทยาศาสตร์',
];

// Load custom dropdown options from DB (persisted "other" values)
$customOptions = [
  'education' => [],
  'faculty' => [],
];

try {
    $customTable = 'tenant_custom_dropdowns';

    // Ensure new table exists with a clear name
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$customTable} (
      id INT AUTO_INCREMENT PRIMARY KEY,
      type VARCHAR(50) NOT NULL,
      value VARCHAR(255) NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY type_value (type, value)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Migrate data from the legacy table if it exists
    $hasLegacy = $pdo->query("SHOW TABLES LIKE 'custom_options'")?->fetchColumn();
    if ($hasLegacy) {
        $pdo->exec("INSERT IGNORE INTO {$customTable} (type, value, created_at)
          SELECT type, value, created_at FROM custom_options");
    }

    $stmtCustom = $pdo->prepare("SELECT type, value FROM {$customTable} WHERE type IN ('education','faculty') ORDER BY value ASC");
    $stmtCustom->execute();
    while ($row = $stmtCustom->fetch(PDO::FETCH_ASSOC)) {
        $customOptions[$row['type']][] = $row['value'];
    }
} catch (PDOException $e) {
    // swallow to avoid breaking page rendering; UI still works with defaults
}

// Remove duplicates that already exist in defaults (case-insensitive compare)
$lowerDefaultsEdu = array_map(fn($v) => mb_strtolower($v, 'UTF-8'), $defaultEducations);
$lowerDefaultsFaculty = array_map(fn($v) => mb_strtolower($v, 'UTF-8'), $defaultFaculties);

$customEducations = array_values(array_filter($customOptions['education'], function ($val) use ($lowerDefaultsEdu) {
  return !in_array(mb_strtolower($val, 'UTF-8'), $lowerDefaultsEdu, true);
}));

$customFaculties = array_values(array_filter($customOptions['faculty'], function ($val) use ($lowerDefaultsFaculty) {
  return !in_array(mb_strtolower($val, 'UTF-8'), $lowerDefaultsFaculty, true);
}));

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
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - จัดการข้อมูลผู้เช่า</title>
    <link rel="icon" type="image/jpeg" href="../Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="../Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="../Assets/Css/main.css" />
    <link rel="stylesheet" href="../Assets/Css/confirm-modal.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4/dist/style.css" />
    <style>
      .tenant-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1rem; margin-top:1rem; }
      .tenant-stat-card { background:linear-gradient(135deg, rgba(18,24,40,0.85), rgba(7,13,26,0.95)); border-radius:16px; padding:1.25rem; border:1px solid rgba(255,255,255,0.08); color:#f5f8ff; box-shadow:0 15px 35px rgba(3,7,18,0.4); }
      .tenant-stat-card h3 { margin:0; font-size:0.95rem; color:rgba(255,255,255,0.7); }
      .tenant-stat-card .stat-value { font-size:2.2rem; font-weight:700; margin-top:0.35rem; }
      .tenant-stat-card .stat-meta { font-size:0.95rem; color:rgba(255,255,255,0.6); margin-top:0.35rem; }
      .tenant-form { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1rem; margin-top:1.5rem; }
      .tenant-form-group label { color:rgba(255,255,255,0.8); font-weight:600; display:block; margin-bottom:0.35rem; font-size:0.95rem; }
      .tenant-form-group input, .tenant-form-group select, .tenant-form-group textarea { width:100%; padding:0.75rem 0.85rem; border-radius:10px; border:1px solid rgba(255,255,255,0.15); background:rgba(8,12,24,0.85); color:#f5f8ff; font-size:0.95rem; }
      .tenant-form-group textarea { min-height:82px; resize:vertical; }
      .tenant-form-group input:focus, .tenant-form-group select:focus, .tenant-form-group textarea:focus { outline:none; border-color:#60a5fa; box-shadow:0 0 0 3px rgba(96,165,250,0.25); }
      .tenant-form-actions { grid-column:1 / -1; display:flex; gap:0.75rem; margin-top:1rem; }
      .status-badge { display:inline-flex; align-items:center; justify-content:center; min-width:80px; padding:0.25rem 0.75rem; border-radius:999px; font-size:0.85rem; font-weight:600; color:#fff; }
      .status-active { background:rgba(34,197,94,0.25); color:#34d399; }
      .status-pending { background:rgba(96,165,250,0.25); color:#93c5fd; }
      .status-booking { background:rgba(248,180,0,0.22); color:#facc15; }
      .status-cancel-booking { background:rgba(248,113,113,0.25); color:#fca5a5; }
      .status-inactive { background:rgba(239,68,68,0.25); color:#f87171; }
      .table-note { color:#94a3b8; font-size:0.9rem; margin-top:0.4rem; }
      .row-fade-out { animation: rowFadeOut 220ms ease forwards; }
      @keyframes rowFadeOut { from { opacity:1; transform:translateY(0); } to { opacity:0; transform:translateY(6px); } }
      .status-filters { display:flex; flex-wrap:wrap; gap:0.5rem; align-items:center; }
      .status-filter-btn { padding:0.55rem 0.95rem; border-radius:10px; border:1px solid rgba(255,255,255,0.14); background:rgba(255,255,255,0.05); color:#e2e8f0; cursor:pointer; font-weight:600; transition:all 0.15s ease; }
      .status-filter-btn:hover { background:rgba(255,255,255,0.08); border-color:rgba(255,255,255,0.2); }
      .status-filter-btn.active { background:linear-gradient(135deg,#60a5fa,#2563eb); color:#0b1727; border-color:transparent; box-shadow:0 10px 20px rgba(37,99,235,0.25); }
      /* Modal */
      .booking-modal { display:none; position:fixed; inset:0; background:rgba(15,23,42,0.85); z-index:1000; align-items:center; justify-content:center; padding:1.5rem; }
      .booking-modal.active { display:flex; }
      .booking-modal-content { background:radial-gradient(circle at top, #1c2541, #0b0c10 60%); border:1px solid rgba(255,255,255,0.08); box-shadow:0 25px 60px rgba(7, 11, 23, 0.65); padding:1.5rem; border-radius:16px; max-width:720px; width:min(720px,95vw); color:#f5f8ff; max-height:90vh; overflow-y:auto; }
      .booking-modal-content h2 { margin-top:0; margin-bottom:1rem; }
      .modal-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1rem; }
      .modal-actions { display:flex; gap:0.75rem; margin-top:1.25rem; justify-content:flex-end; }
      .modal-actions .btn-submit {
        padding: 0.7rem 1.1rem;
        border-radius: 10px;
        border: none;
        background: linear-gradient(135deg, #60a5fa, #2563eb);
        color: #0b1727;
        font-weight: 700;
        box-shadow: 0 10px 20px rgba(37,99,235,0.35);
        cursor: pointer;
        transition: transform 0.15s ease, box-shadow 0.2s ease, opacity 0.2s ease;
      }
      .modal-actions .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 12px 24px rgba(37,99,235,0.45); }
      .modal-actions .btn-submit:active { transform: translateY(0); opacity: 0.92; }
      .modal-actions .btn-cancel {
        padding: 0.7rem 1.1rem;
        border-radius: 10px;
        border: 1px solid rgba(248,113,113,0.45);
        background: rgba(248,113,113,0.12);
        color: #fecaca;
        font-weight: 700;
        cursor: pointer;
        transition: transform 0.15s ease, opacity 0.2s ease, background 0.2s ease, border-color 0.2s ease;
      }
      .modal-actions .btn-cancel:hover { transform: translateY(-1px); background: rgba(248,113,113,0.22); border-color: rgba(248,113,113,0.7); }
      .modal-actions .btn-cancel:active { transform: translateY(0); opacity: 0.9; }
      .reports-page .manage-panel { margin-top: 1.4rem; margin-bottom: 1.4rem; background: #0f172a; border: 1px solid rgba(148,163,184,0.2); box-shadow: 0 12px 30px rgba(0,0,0,0.2); }
      .reports-page .manage-panel:first-of-type { margin-top: 0.2rem; }
    </style>
  </head>
  <body class="reports-page">
    <div class="app-shell">
      <?php include __DIR__ . '/../includes/sidebar.php'; ?>
      <main class="app-main">
        <div>
          <?php 
            $pageTitle = 'จัดการข้อมูลผู้เช่า';
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
                <h1>ภาพรวมผู้เช่า</h1>
                <p style="color:#94a3b8; margin-top:0.2rem;">สถิติผู้เช่าปัจจุบัน</p>
              </div>
            </div>
            <div class="tenant-stats">
              <div class="tenant-stat-card">
                <h3>ผู้เช่าทั้งหมด</h3>
                <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
              </div>
              <div class="tenant-stat-card">
                <h3>พักอยู่</h3>
                <div class="stat-value" style="color:#22c55e;"><?php echo number_format($stats['active']); ?></div>
                <div class="stat-meta">สถานะ = 1</div>
              </div>
              <div class="tenant-stat-card">
                <h3>รอการเข้าพัก</h3>
                <div class="stat-value" style="color:#93c5fd;"><?php echo number_format($stats['pending']); ?></div>
                <div class="stat-meta">สถานะ = 2</div>
              </div>
              <div class="tenant-stat-card">
                <h3>จองห้อง</h3>
                <div class="stat-value" style="color:#facc15;"><?php echo number_format($stats['booking']); ?></div>
                <div class="stat-meta">สถานะ = 3</div>
              </div>
              <div class="tenant-stat-card">
                <h3>ยกเลิกจองห้อง</h3>
                <div class="stat-value" style="color:#fca5a5;"><?php echo number_format($stats['cancel_booking']); ?></div>
                <div class="stat-meta">สถานะ = 4</div>
              </div>
              <div class="tenant-stat-card">
                <h3>ย้ายออก</h3>
                <div class="stat-value" style="color:#f87171;"><?php echo number_format($stats['inactive']); ?></div>
                <div class="stat-meta">สถานะ = 0</div>
              </div>
            </div>
          </section>

          <!-- Toggle button for tenant form -->
          <div style="margin:1.5rem 0;">
            <button type="button" id="toggleTenantFormBtn" style="white-space:nowrap;padding:0.8rem 1.5rem;cursor:pointer;font-size:1rem;background:#1e293b;border:1px solid #334155;color:#cbd5e1;border-radius:8px;transition:all 0.2s;box-shadow:0 2px 4px rgba(0,0,0,0.1);" onclick="toggleTenantForm()" onmouseover="this.style.background='#334155';this.style.borderColor='#475569'" onmouseout="this.style.background='#1e293b';this.style.borderColor='#334155'">
              <span id="toggleTenantFormIcon">▼</span> <span id="toggleTenantFormText">ซ่อนฟอร์ม</span>
            </button>
          </div>

          <section class="manage-panel" style="background:linear-gradient(135deg, rgba(15,23,42,0.95), rgba(2,6,23,0.95)); color:#f8fafc;" id="addTenantSection">
            <div class="section-header">
              <div>
                <h1>เพิ่มผู้เช่าใหม่</h1>
                <p style="margin-top:0.25rem;color:rgba(255,255,255,0.7);">สถานะผู้เช่าจะถูกตั้งค่าและปรับอัตโนมัติตามการจองและการเข้าพัก</p>
              </div>
            </div>
            <form action="../Manage/process_tenant.php" method="post" id="tenantForm">
              <div class="tenant-form">
                <div class="tenant-form-group">
                  <label for="tnt_id">เลขบัตรประชาชน (13 หลัก) <span style="color:#f87171;">*</span></label>
                  <input type="text" id="tnt_id" name="tnt_id" maxlength="13" minlength="13" inputmode="numeric" pattern="\d{13}" required placeholder="เช่น 1103701234567" />
                </div>
                <div class="tenant-form-group">
                  <label for="tnt_name">ชื่อ-สกุล <span style="color:#f87171;">*</span></label>
                  <input type="text" id="tnt_name" name="tnt_name" required />
                </div>
                <div class="tenant-form-group">
                  <label for="tnt_age">อายุ</label>
                  <div style="display:flex; gap:0.5rem; align-items:center;">
                    <select id="tnt_age_select" style="flex:1;">
                      <option value="">เลือกอายุ</option>
                      <option value="15">15</option>
                      <option value="18">18</option>
                      <option value="20">20</option>
                      <option value="23">23</option>
                      <option value="25">25</option>
                      <option value="30">30</option>
                      <option value="35">35</option>
                      <option value="40">40</option>
                      <option value="45">45</option>
                      <option value="50">50</option>
                      <option value="custom">กำหนดเอง</option>
                    </select>
                    <div id="tnt_age_wrap" style="flex:0 0 auto; display:none;">
                      <input type="number" id="tnt_age" name="tnt_age" min="0" max="120" placeholder="กำหนดเอง" style="width:120px;" disabled />
                    </div>
                  </div>
                </div>
                <div class="tenant-form-group">
                  <label for="tnt_phone">เบอร์โทรศัพท์</label>
                  <input type="text" id="tnt_phone" name="tnt_phone" maxlength="10" placeholder="เช่น 0812345678" />
                </div>
                <div class="tenant-form-group">	
                  <label for="tnt_address">ที่อยู่ตามบัตรประชาชน</label>
                  <textarea id="tnt_address" name="tnt_address" rows="2"></textarea>
                </div>
                <div class="tenant-form-group">
                  <label for="tnt_education">สถานศึกษา</label>
                  <div style="display:flex; gap:0.5rem; align-items:center;">
                    <select id="tnt_education_select" style="flex:1;">
                      <option value="">เลือกสถานศึกษา</option>
                      <option value="วิทยาลัยเทคนิคเพชรบูรณ์">วิทยาลัยเทคนิคเพชรบูรณ์</option>
                      <option value="โรงเรียนเพชรบูรณ์พิทยาลัย">โรงเรียนเพชรบูรณ์พิทยาลัย</option>
                      <option value="วิทยาลัยอาชีวศึกษาเพชรบูรณ์">วิทยาลัยอาชีวศึกษาเพชรบูรณ์</option>
                      <option value="โรงเรียนบ้านหลวง">โรงเรียนบ้านหลวง</option>
                      <?php foreach ($customEducations as $edu): ?>
                        <option value="<?php echo htmlspecialchars($edu, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($edu, ENT_QUOTES, 'UTF-8'); ?></option>
                      <?php endforeach; ?>
                      <option value="other">อื่น ๆ</option>
                    </select>
                    <div id="tnt_education_wrap" style="flex:0 0 auto; display:none;">
                      <input type="text" id="tnt_education" name="tnt_education" placeholder="ระบุสถานศึกษา" style="width:200px;" disabled />
                    </div>
                  </div>
                  <p style="margin:0.35rem 0 0; color:#94a3b8; font-size:0.9rem;"></p>
                </div>
                <div class="tenant-form-group">
                  <label for="tnt_faculty">คณะ</label>
                  <div style="display:flex; gap:0.5rem; align-items:center;">
                    <select id="tnt_faculty_select" style="flex:1;">
                      <option value="">เลือกคณะ</option>
                      <option value="วิศวกรรมไฟฟ้า">วิศวกรรมไฟฟ้า</option>
                      <option value="วิศวกรรมเครื่องกล">วิศวกรรมเครื่องกล</option>
                      <option value="เทคโนโลยีสารสนเทศ">เทคโนโลยีสารสนเทศ</option>
                      <option value="ศิลปศาสตร์">ศิลปศาสตร์</option>
                      <option value="วิทยาศาสตร์">วิทยาศาสตร์</option>
                      <?php foreach ($customFaculties as $faculty): ?>
                        <option value="<?php echo htmlspecialchars($faculty, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($faculty, ENT_QUOTES, 'UTF-8'); ?></option>
                      <?php endforeach; ?>
                      <option value="other">อื่น ๆ</option>
                    </select>
                    <div id="tnt_faculty_wrap" style="flex:0 0 auto; display:none;">
                      <input type="text" id="tnt_faculty" name="tnt_faculty" placeholder="ระบุคณะ" style="width:200px;" disabled />
                    </div>
                  </div>
                  <p style="margin:0.35rem 0 0; color:#94a3b8; font-size:0.9rem;"></p>
                </div>
                <div class="tenant-form-group">
                  <label for="tnt_year">ชั้นปี</label>
                  <select id="tnt_year" name="tnt_year">
                    <option value="">เลือกชั้นปี</option>
                    <option value="ปี 1">ปี 1</option>
                    <option value="ปี 2">ปี 2</option>
                    <option value="ปี 3">ปี 3</option>
                    <option value="ปี 4">ปี 4</option>
                    <option value="ปี 5">ปี 5</option>
                    <option value="ปี 6">ปี 6</option>
                  </select>
                </div>
                <div class="tenant-form-group">
                  <label for="tnt_vehicle">ทะเบียนรถ</label>
                  <input type="text" id="tnt_vehicle" name="tnt_vehicle" />
                </div>
                <div class="tenant-form-group">
                  <label for="tnt_parent">ชื่อผู้ปกครอง</label>
                  <input type="text" id="tnt_parent" name="tnt_parent" />
                </div>
                <div class="tenant-form-group">
                  <label for="tnt_parentsphone">เบอร์ผู้ปกครอง</label>
                  <input type="text" id="tnt_parentsphone" name="tnt_parentsphone" maxlength="10" />
                </div>
                <div class="tenant-form-actions">
                  <button type="submit" class="animate-ui-add-btn" data-animate-ui-skip="true" data-no-modal="true" data-allow-submit="true" style="flex:2;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></svg>
                    บันทึกผู้เช่า
                  </button>
                  <button type="reset" class="animate-ui-action-btn delete" style="flex:1;">ล้างข้อมูล</button>
                </div>
              </div>
            </form>
          </section>

          <section class="manage-panel">
            <div class="section-header" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
              <div>
                <h1>ผู้เช่าทั้งหมด</h1>
                <p style="color:#94a3b8;margin-top:0.2rem;">รายการผู้เช่าและสถานะ</p>
              </div>
              <div class="status-filters">
                <button type="button" class="status-filter-btn active" data-status-filter="all">ทั้งหมด</button>
                <button type="button" class="status-filter-btn" data-status-filter="1">พักอยู่</button>
                <button type="button" class="status-filter-btn" data-status-filter="2">รอการเข้าพัก</button>
                <button type="button" class="status-filter-btn" data-status-filter="3">จองห้อง</button>
                <button type="button" class="status-filter-btn" data-status-filter="4">ยกเลิกจองห้อง</button>
                <button type="button" class="status-filter-btn" data-status-filter="0">ย้ายออก</button>
              </div>
              <select id="sortSelect" onchange="changeSortBy(this.value)" style="padding:0.6rem 0.85rem;border-radius:8px;border:1px solid rgba(255,255,255,0.2);background:rgba(255,255,255,0.05);color:#f5f8ff;font-size:0.95rem;cursor:pointer;">
                <option value="newest" <?php echo ($sortBy === 'newest' ? 'selected' : ''); ?>>ล่าสุด → เก่าสุด</option>
                <option value="oldest" <?php echo ($sortBy === 'oldest' ? 'selected' : ''); ?>>เก่าสุด → ล่าสุด</option>
                <option value="status_name" <?php echo ($sortBy === 'status_name' ? 'selected' : ''); ?>>สถานะ และ ชื่อ</option>
                <option value="name_asc" <?php echo ($sortBy === 'name_asc' ? 'selected' : ''); ?>>ชื่อ (ก-ฮ)</option>
                <option value="name_desc" <?php echo ($sortBy === 'name_desc' ? 'selected' : ''); ?>>ชื่อ (ฮ-ก)</option>
              </select>
            </div>
            <div class="report-table">
              <table class="table--compact" id="table-tenants">
                <thead>
                  <tr>
                    <th>ชื่อ-สกุล</th>
                    <th>สถานะ</th>
                    <th>สถานศึกษา</th>
                    <th class="crud-column">จัดการ</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($tenants)): ?>
                    <tr><td colspan="6" style="text-align:center; padding:1.5rem; color:#64748b;">ยังไม่มีข้อมูลผู้เช่า</td></tr>
                  <?php else: ?>
                    <?php foreach ($tenants as $t): ?>
                      <?php $statusKey = (string)($t['tnt_status'] ?? '0'); ?>
                      <tr data-status="<?php echo htmlspecialchars($statusKey); ?>">
                        <td>
                          <div>เลขบัตรประชาชน: <span class="expense-meta" style="color:#94a3b8;"><?php echo htmlspecialchars($t['tnt_id'] ?? '-'); ?></span></div>
                          <div>ชื่อ-สกุล: <span class="expense-meta" style="color:#94a3b8;"><?php echo htmlspecialchars($t['tnt_name'] ?? '-'); ?></span></div>
                          <div>อายุ: <span class="expense-meta" style="color:#94a3b8;"><?php echo htmlspecialchars((string)($t['tnt_age'] ?? '-')); ?></span></div>
                          <div>เบอร์โทร: <span class="expense-meta" style="color:#94a3b8;"><?php echo htmlspecialchars($t['tnt_phone'] ?? '-'); ?></span></div>
                        </td>
                        <td>
                          <?php
                            $badgeClass = 'status-inactive';
                            if ($statusKey === '1') $badgeClass = 'status-active';
                            elseif ($statusKey === '2') $badgeClass = 'status-pending';
                            elseif ($statusKey === '3') $badgeClass = 'status-booking';
                            elseif ($statusKey === '4') $badgeClass = 'status-cancel-booking';
                          ?>
                          <span class="status-badge <?php echo $badgeClass; ?>"><?php echo $statusMap[$statusKey] ?? '-'; ?></span>
                        </td>
                        <td>
                          <div><?php echo htmlspecialchars($t['tnt_education'] ?? '-'); ?></div>
                          <div class="expense-meta" style="color:#94a3b8;">คณะ: <?php echo htmlspecialchars($t['tnt_faculty'] ?? '-'); ?> | ปี: <?php echo htmlspecialchars($t['tnt_year'] ?? '-'); ?></div>
                        </td>
                        <td class="crud-column">
                          <button type="button" class="animate-ui-action-btn edit btn-edit-tenant"
                            data-tnt-id="<?php echo htmlspecialchars($t['tnt_id']); ?>"
                            data-tnt-name="<?php echo htmlspecialchars($t['tnt_name'] ?? ''); ?>"
                            data-tnt-age="<?php echo htmlspecialchars((string)($t['tnt_age'] ?? '')); ?>"
                            data-tnt-phone="<?php echo htmlspecialchars($t['tnt_phone'] ?? ''); ?>"
                            data-tnt-address="<?php echo htmlspecialchars($t['tnt_address'] ?? ''); ?>"
                            data-tnt-education="<?php echo htmlspecialchars($t['tnt_education'] ?? ''); ?>"
                            data-tnt-faculty="<?php echo htmlspecialchars($t['tnt_faculty'] ?? ''); ?>"
                            data-tnt-year="<?php echo htmlspecialchars($t['tnt_year'] ?? ''); ?>"
                            data-tnt-vehicle="<?php echo htmlspecialchars($t['tnt_vehicle'] ?? ''); ?>"
                            data-tnt-parent="<?php echo htmlspecialchars($t['tnt_parent'] ?? ''); ?>"
                            data-tnt-parentsphone="<?php echo htmlspecialchars($t['tnt_parentsphone'] ?? ''); ?>">
                            แก้ไข
                          </button>
                          <button type="button" class="animate-ui-action-btn delete btn-delete-tenant" data-tnt-id="<?php echo htmlspecialchars($t['tnt_id']); ?>">ลบ</button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
              <p class="table-note">สถานะผู้เช่าจะถูกอัปเดตอัตโนมัติตามการจอง การยืนยันเข้าพัก หรือการย้ายออก ระบบจะจัดการให้เอง</p>
            </div>
          </section>
        </div>
      </main>
    </div>

    <!-- Edit Tenant Modal -->
    <div class="booking-modal" id="tenantEditModal">
      <div class="booking-modal-content">
        <h2>แก้ไขข้อมูลผู้เช่า</h2>
        <form id="tenantEditForm" method="POST" action="../Manage/update_tenant.php" data-animate-ui-skip="true" data-no-modal="true" data-allow-submit="true">
          <input type="hidden" name="tnt_id_original" id="edit_tnt_id_original">
          <div class="modal-grid">
            <div class="tenant-form-group">
              <label for="edit_tnt_id">เลขบัตรประชาชน</label>
              <input type="text" id="edit_tnt_id" name="tnt_id" readonly />
            </div>
            <div class="tenant-form-group">
              <label for="edit_tnt_name">ชื่อ-สกุล <span style="color:#f87171;">*</span></label>
              <input type="text" id="edit_tnt_name" name="tnt_name" required />
            </div>
            <div class="tenant-form-group">
              <label for="edit_tnt_age">อายุ</label>
              <div style="display:flex; gap:0.5rem; align-items:center;">
                <select id="edit_tnt_age_select" style="flex:1;">
                  <option value="">เลือกอายุ</option>
                  <option value="15">15</option>
                  <option value="18">18</option>
                  <option value="20">20</option>
                  <option value="23">23</option>
                  <option value="25">25</option>
                  <option value="30">30</option>
                  <option value="35">35</option>
                  <option value="40">40</option>
                  <option value="45">45</option>
                  <option value="50">50</option>
                  <option value="custom">กำหนดเอง</option>
                </select>
                <div id="edit_tnt_age_wrap" style="flex:0 0 auto; display:none;">
                  <input type="number" id="edit_tnt_age" name="tnt_age" min="0" max="120" placeholder="กำหนดเอง" style="width:120px;" disabled />
                </div>
              </div>
            </div>
            <div class="tenant-form-group">
              <label for="edit_tnt_phone">เบอร์โทรศัพท์</label>
              <input type="text" id="edit_tnt_phone" name="tnt_phone" maxlength="10" />
            </div>
            <div class="tenant-form-group" style="grid-column:1 / -1;">
              <label for="edit_tnt_address">ที่อยู่ตามบัตรประชาชน</label>
              <textarea id="edit_tnt_address" name="tnt_address" rows="2"></textarea>
            </div>
            <div class="tenant-form-group">
              <label for="edit_tnt_education">สถานศึกษา</label>
              <div style="display:flex; gap:0.5rem; align-items:center;">
                <select id="edit_tnt_education_select" style="flex:1;">
                  <option value="">เลือกสถานศึกษา</option>
                  <option value="วิทยาลัยเทคนิคเพชรบูรณ์">วิทยาลัยเทคนิคเพชรบูรณ์</option>
                  <option value="โรงเรียนเพชรบูรณ์พิทยาลัย">โรงเรียนเพชรบูรณ์พิทยาลัย</option>
                  <option value="วิทยาลัยอาชีวศึกษาเพชรบูรณ์">วิทยาลัยอาชีวศึกษาเพชรบูรณ์</option>
                  <option value="โรงเรียนบ้านหลวง">โรงเรียนบ้านหลวง</option>
                  <?php foreach ($customEducations as $edu): ?>
                    <option value="<?php echo htmlspecialchars($edu, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($edu, ENT_QUOTES, 'UTF-8'); ?></option>
                  <?php endforeach; ?>
                  <option value="other">อื่น ๆ</option>
                </select>
                <div id="edit_tnt_education_wrap" style="flex:0 0 auto; display:none;">
                  <input type="text" id="edit_tnt_education" name="tnt_education" placeholder="ระบุสถานศึกษา" style="width:200px;" disabled />
                </div>
              </div>
            </div>
            <div class="tenant-form-group">
              <label for="edit_tnt_faculty">คณะ</label>
              <div style="display:flex; gap:0.5rem; align-items:center;">
                <select id="edit_tnt_faculty_select" style="flex:1;">
                  <option value="">เลือกคณะ</option>
                  <option value="วิศวกรรมไฟฟ้า">วิศวกรรมไฟฟ้า</option>
                  <option value="วิศวกรรมเครื่องกล">วิศวกรรมเครื่องกล</option>
                  <option value="เทคโนโลยีสารสนเทศ">เทคโนโลยีสารสนเทศ</option>
                  <option value="ศิลปศาสตร์">ศิลปศาสตร์</option>
                  <option value="วิทยาศาสตร์">วิทยาศาสตร์</option>
                  <?php foreach ($customFaculties as $faculty): ?>
                    <option value="<?php echo htmlspecialchars($faculty, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($faculty, ENT_QUOTES, 'UTF-8'); ?></option>
                  <?php endforeach; ?>
                  <option value="other">อื่น ๆ</option>
                </select>
                <div id="edit_tnt_faculty_wrap" style="flex:0 0 auto; display:none;">
                  <input type="text" id="edit_tnt_faculty" name="tnt_faculty" placeholder="ระบุคณะ" style="width:200px;" disabled />
                </div>
              </div>
            </div>
            <div class="tenant-form-group">
              <label for="edit_tnt_year">ชั้นปี</label>
              <select id="edit_tnt_year" name="tnt_year">
                <option value="">เลือกชั้นปี</option>
                <option value="ปี 1">ปี 1</option>
                <option value="ปี 2">ปี 2</option>
                <option value="ปี 3">ปี 3</option>
                <option value="ปี 4">ปี 4</option>
                <option value="ปี 5">ปี 5</option>
                <option value="ปี 6">ปี 6</option>
              </select>
            </div>
            <div class="tenant-form-group">
              <label for="edit_tnt_vehicle">ทะเบียนรถ</label>
              <input type="text" id="edit_tnt_vehicle" name="tnt_vehicle" />
            </div>
            <div class="tenant-form-group">
              <label for="edit_tnt_parent">ชื่อผู้ปกครอง</label>
              <input type="text" id="edit_tnt_parent" name="tnt_parent" />
            </div>
            <div class="tenant-form-group">
              <label for="edit_tnt_parentsphone">เบอร์ผู้ปกครอง</label>
              <input type="text" id="edit_tnt_parentsphone" name="tnt_parentsphone" maxlength="10" />
            </div>
          </div>
          <div class="modal-actions">
            <button type="submit" class="btn-submit">บันทึกการแก้ไข</button>
            <button type="button" class="btn-cancel" onclick="closeTenantModal()">ยกเลิก</button>
          </div>
        </form>
      </div>
    </div>

    <script src="../Assets/Javascript/animate-ui.js" defer></script>
    <script src="../Assets/Javascript/main.js" defer></script>
    <script src="../Assets/Javascript/toast-notification.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4" defer></script>
    <script>
      function changeSortBy(sortValue) {
        const url = new URL(window.location);
        url.searchParams.set('sort', sortValue);
        window.location.href = url.toString();
      }

      function applyAgeSelection(selectId, inputId, wrapId, value) {
        const select = document.getElementById(selectId);
        const input = document.getElementById(inputId);
        const wrap = document.getElementById(wrapId);
        if (!select || !input) return;

        const presetValues = ['15','18','20','23','25','30','35','40','45','50'];
        if (presetValues.includes(String(value))) {
          select.value = String(value);
          input.value = String(value);
          input.disabled = true;
          if (wrap) wrap.style.display = 'none';
          return;
        }

        if (!value) {
          select.value = '';
          input.value = '';
          input.disabled = true;
          if (wrap) wrap.style.display = 'none';
          return;
        }

        select.value = 'custom';
        input.value = String(value);
        input.disabled = false;
        if (wrap) wrap.style.display = 'block';
      }

      function setupAgeSync(selectId, inputId, wrapId) {
        const select = document.getElementById(selectId);
        const input = document.getElementById(inputId);
        const wrap = document.getElementById(wrapId);
        if (!select || !input) return;

        select.addEventListener('change', () => {
          if (select.value === 'custom') {
            select.style.flex = '0 0 40%';
            input.disabled = false;
            input.value = '';
            input.focus();
            if (wrap) wrap.style.display = 'block';
          } else if (select.value === '') {
            select.style.flex = '1';
            input.value = '';
            input.disabled = true;
            if (wrap) wrap.style.display = 'none';
          } else {
            select.style.flex = '1';
            input.value = select.value;
            input.disabled = true;
            if (wrap) wrap.style.display = 'none';
          }
        });

        input.addEventListener('input', () => {
          select.style.flex = '0 0 40%';
          select.value = 'custom';
          input.disabled = false;
          if (wrap) wrap.style.display = 'block';
        });
      }

      function setupSelectSync(selectId, inputId, wrapId, optionValue = 'other') {
        const select = document.getElementById(selectId);
        const input = document.getElementById(inputId);
        const wrap = document.getElementById(wrapId);
        if (!select || !input) return;

        // Initialize based on current state
        if (select.value === optionValue && input.value) {
          input.disabled = false;
          if (wrap) wrap.style.display = 'block';
        }

        select.addEventListener('change', () => {
          if (select.value === optionValue) {
            input.disabled = false;
            input.value = '';
            input.focus();
            if (wrap) wrap.style.display = 'block';
          } else {
            input.value = '';
            input.disabled = true;
            if (wrap) wrap.style.display = 'none';
          }
        });

        input.addEventListener('input', () => {
          select.value = optionValue;
        });
      }

      function applySelectValue(selectId, inputId, wrapId, value, optionValue = 'other') {
        const select = document.getElementById(selectId);
        const input = document.getElementById(inputId);
        const wrap = document.getElementById(wrapId);
        if (!select || !input) return;

        const isOther = !Array.from(select.options).some(opt => opt.value === value && opt.value !== '');
        
        if (isOther && value) {
          select.value = optionValue;
          input.value = value;
          input.disabled = false;
          if (wrap) wrap.style.display = 'block';
        } else if (value) {
          select.value = value;
          input.value = '';
          input.disabled = true;
          if (wrap) wrap.style.display = 'none';
        } else {
          select.value = '';
          input.value = '';
          input.disabled = true;
          if (wrap) wrap.style.display = 'none';
        }
      }

      function addSelectOptionIfNew(selectId, value, optionType = null) {
        if (!value || value.trim() === '') return false;
        
        const select = document.getElementById(selectId);
        if (!select) return false;

        const trimmedValue = value.trim();
        const exists = Array.from(select.options).some(opt => opt.value.toLowerCase() === trimmedValue.toLowerCase());
        
        if (exists) {
          console.log('Option already exists:', trimmedValue);
          return false;
        }

        const newOption = document.createElement('option');
        newOption.value = trimmedValue;
        newOption.textContent = trimmedValue;
        
        const otherOption = Array.from(select.options).find(opt => opt.value === 'other');
        if (otherOption) {
          select.insertBefore(newOption, otherOption);
        } else {
          select.appendChild(newOption);
        }
        
        console.log('Added new option to', selectId, ':', trimmedValue);
        
        // Save to server if optionType is provided
        if (optionType) {
          const formData = new FormData();
          formData.append('type', optionType);
          formData.append('value', trimmedValue);
          
          fetch('../Manage/add_custom_option.php', {
            method: 'POST',
            body: formData
          }).then(response => response.json())
            .then(data => {
              if (data.success) {
                console.log('Custom option saved:', trimmedValue);
              } else {
                console.error('Failed to save option:', data.message);
              }
            })
            .catch(err => console.error('Error saving option:', err));
        }
        
        return true;
      }

      function applyTenantStatusFilter(filter) {
        const rows = document.querySelectorAll('#table-tenants tbody tr');
        rows.forEach(row => {
          const rowStatus = row.getAttribute('data-status') || '';
          const shouldShow = filter === 'all' || rowStatus === filter;
          row.style.display = shouldShow ? '' : 'none';
        });

        try { localStorage.setItem('tenantStatusFilter', filter); } catch (e) {}

        document.querySelectorAll('.status-filter-btn').forEach(btn => {
          const isActive = btn.dataset.statusFilter === filter;
          btn.classList.toggle('active', isActive);
        });
      }

      function openTenantModal(data) {
        document.getElementById('tenantEditModal').classList.add('active');
        document.body.classList.add('modal-open');
        const setVal = (id, val = '') => { const el = document.getElementById(id); if (el) el.value = val; };
        setVal('edit_tnt_id', data.tntId || '');
        setVal('edit_tnt_id_original', data.tntId || '');
        setVal('edit_tnt_name', data.tntName || '');
        applyAgeSelection('edit_tnt_age_select', 'edit_tnt_age', 'edit_tnt_age_wrap', data.tntAge || '');
        setVal('edit_tnt_phone', data.tntPhone || '');
        setVal('edit_tnt_address', data.tntAddress || '');
        applySelectValue('edit_tnt_education_select', 'edit_tnt_education', 'edit_tnt_education_wrap', data.tntEducation || '');
        applySelectValue('edit_tnt_faculty_select', 'edit_tnt_faculty', 'edit_tnt_faculty_wrap', data.tntFaculty || '');
        setVal('edit_tnt_year', data.tntYear || '');
        setVal('edit_tnt_vehicle', data.tntVehicle || '');
        setVal('edit_tnt_parent', data.tntParent || '');
        setVal('edit_tnt_parentsphone', data.tntParentsPhone || '');
      }

      function closeTenantModal() {
        const modal = document.getElementById('tenantEditModal');
        modal.classList.remove('active');
        document.body.classList.remove('modal-open');
        const form = document.getElementById('tenantEditForm');
        if (form) form.reset();
      }

      // Toggle tenant form visibility
      function toggleTenantForm() {
        const section = document.getElementById('addTenantSection');
        const icon = document.getElementById('toggleTenantFormIcon');
        const text = document.getElementById('toggleTenantFormText');
        const isHidden = section.style.display === 'none';
        
        if (isHidden) {
          section.style.display = '';
          icon.textContent = '▼';
          text.textContent = 'ซ่อนฟอร์ม';
          localStorage.setItem('tenantFormVisible', 'true');
        } else {
          section.style.display = 'none';
          icon.textContent = '▶';
          text.textContent = 'แสดงฟอร์ม';
          localStorage.setItem('tenantFormVisible', 'false');
        }
      }

      document.addEventListener('DOMContentLoaded', () => {
        // Restore form visibility from localStorage
        const isFormVisible = localStorage.getItem('tenantFormVisible') !== 'false';
        const section = document.getElementById('addTenantSection');
        const icon = document.getElementById('toggleTenantFormIcon');
        const text = document.getElementById('toggleTenantFormText');
        if (!isFormVisible) {
          section.style.display = 'none';
          icon.textContent = '▶';
          text.textContent = 'แสดงฟอร์ม';
        }

        // ทำให้ input tnt_id รับเฉพาะตัวเลข
        const tntIdInput = document.getElementById('tnt_id');
        if (tntIdInput) {
          tntIdInput.addEventListener('input', (e) => {
            e.target.value = e.target.value.replace(/[^\d]/g, '').slice(0, 13);
          });
        }

        setupAgeSync('tnt_age_select', 'tnt_age', 'tnt_age_wrap');
        applyAgeSelection('tnt_age_select', 'tnt_age', 'tnt_age_wrap', '');

        setupSelectSync('tnt_education_select', 'tnt_education', 'tnt_education_wrap');
        setupSelectSync('tnt_faculty_select', 'tnt_faculty', 'tnt_faculty_wrap');

        document.querySelectorAll('.btn-edit-tenant').forEach(btn => {
          btn.addEventListener('click', () => {
            openTenantModal({
              tntId: btn.dataset.tntId,
              tntName: btn.dataset.tntName,
              tntAge: btn.dataset.tntAge,
              tntPhone: btn.dataset.tntPhone,
              tntAddress: btn.dataset.tntAddress,
              tntEducation: btn.dataset.tntEducation,
              tntFaculty: btn.dataset.tntFaculty,
              tntYear: btn.dataset.tntYear,
              tntVehicle: btn.dataset.tntVehicle,
              tntParent: btn.dataset.tntParent,
              tntParentsPhone: btn.dataset.tntParentsphone,
            });
          });
        });

        setupAgeSync('edit_tnt_age_select', 'edit_tnt_age', 'edit_tnt_age_wrap');

        setupSelectSync('edit_tnt_education_select', 'edit_tnt_education', 'edit_tnt_education_wrap');
        setupSelectSync('edit_tnt_faculty_select', 'edit_tnt_faculty', 'edit_tnt_faculty_wrap');

        document.querySelectorAll('.status-filter-btn').forEach(btn => {
          btn.addEventListener('click', () => {
            applyTenantStatusFilter(btn.dataset.statusFilter || 'all');
          });
        });

        const savedFilter = localStorage.getItem('tenantStatusFilter') || 'all';
        applyTenantStatusFilter(savedFilter);

        // Initialize enhanced table with Simple-DataTables
        const tenantTableEl = document.querySelector('#table-tenants');
        if (tenantTableEl && window.simpleDatatables) {
          try {
            const dt = new simpleDatatables.DataTable(tenantTableEl, {
              searchable: true,
              fixedHeight: false,
              perPage: 10,
              perPageSelect: [10, 25, 50, 100],
              labels: {
                placeholder: 'ค้นหา...',
                perPage: '{select} แถวต่อหน้า',
                noRows: 'ไม่มีข้อมูล',
                info: 'แสดง {start}–{end} จาก {rows} รายการ'
              },
              columns: [
                { select: 5, sortable: false } // จัดการ
              ],
            });
            // save reference if needed later
            window.__tenantDataTable = dt;
          } catch (err) {
            console.error('Failed to init data table', err);
          }
        }

        document.querySelectorAll('.btn-delete-tenant').forEach(btn => {
          btn.addEventListener('click', async () => {
            const id = btn.dataset.tntId;
            if (!id) return;
            
            const confirmed = await showConfirmDialog(
              'ยืนยันการลบผู้เช่า',
              `คุณต้องการลบผู้เช่า <strong>ID: ${id}</strong> หรือไม่?<br><br>ข้อมูลทั้งหมดจะถูกลบอย่างถาวร`
            );
            
            if (!confirmed) return;

            try {
              const formData = new FormData();
              formData.append('tnt_id', id);
              const response = await fetch('../Manage/delete_tenant.php', {
                method: 'POST',
                body: formData,
              });

              if (!response.ok) throw new Error('delete failed');

              const row = btn.closest('tr');
              const removeRow = () => {
                if (row && row.parentNode) {
                  row.parentNode.removeChild(row);
                  const tbody = document.querySelector('#table-tenants tbody');
                  const hasRows = tbody && tbody.querySelectorAll('tr').length > 0;
                  if (!hasRows) {
                    window.location.reload();
                  }
                }
              };

              if (row) {
                row.classList.add('row-fade-out');
                row.addEventListener('animationend', removeRow, { once: true });
                setTimeout(removeRow, 300); // fallback
              }

              showSuccessToast('ลบผู้เช่าเรียบร้อยแล้ว');
            } catch (err) {
              console.error(err);
              showErrorToast('ลบไม่สำเร็จ');
            }
          });
        });

        document.getElementById('tenantForm')?.addEventListener('submit', (e) => {
          const id = document.getElementById('tnt_id');
          if (id && !/^\d{13}$/.test(id.value.trim())) {
            e.preventDefault();
            showErrorToast('เลขบัตรประชาชนต้องมี 13 หลัก');
            id.focus();
            return;
          }

          // Handle custom education value
          const educationSelect = document.getElementById('tnt_education_select');
          const educationInput = document.getElementById('tnt_education');
          if (educationSelect && educationInput) {
            if (educationSelect.value === 'other') {
              const customValue = educationInput.value.trim();
              if (customValue) {
                // Add to dropdown and save to server
                addSelectOptionIfNew('tnt_education_select', customValue, 'education');
                educationInput.value = customValue;
                educationInput.disabled = false;
              }
            } else if (educationSelect.value) {
              educationInput.value = educationSelect.value;
              educationInput.disabled = false;
            }
          }

          // Handle custom faculty value
          const facultySelect = document.getElementById('tnt_faculty_select');
          const facultyInput = document.getElementById('tnt_faculty');
          if (facultySelect && facultyInput) {
            if (facultySelect.value === 'other') {
              const customValue = facultyInput.value.trim();
              if (customValue) {
                // Add to dropdown and save to server
                addSelectOptionIfNew('tnt_faculty_select', customValue, 'faculty');
                facultyInput.value = customValue;
                facultyInput.disabled = false;
              }
            } else if (facultySelect.value) {
              facultyInput.value = facultySelect.value;
              facultyInput.disabled = false;
            }
          }
        });

        document.getElementById('tenantEditForm')?.addEventListener('submit', (e) => {
          // Handle custom education value in edit modal
          const editEducationSelect = document.getElementById('edit_tnt_education_select');
          const editEducationInput = document.getElementById('edit_tnt_education');
          if (editEducationSelect && editEducationInput) {
            if (editEducationSelect.value === 'other') {
              const customValue = editEducationInput.value.trim();
              if (customValue) {
                addSelectOptionIfNew('edit_tnt_education_select', customValue, 'education');
                addSelectOptionIfNew('tnt_education_select', customValue, 'education');
                editEducationInput.value = customValue;
                editEducationInput.disabled = false;
              }
            } else if (editEducationSelect.value) {
              editEducationInput.value = editEducationSelect.value;
              editEducationInput.disabled = false;
            }
          }

          // Handle custom faculty value in edit modal
          const editFacultySelect = document.getElementById('edit_tnt_faculty_select');
          const editFacultyInput = document.getElementById('edit_tnt_faculty');
          if (editFacultySelect && editFacultyInput) {
            if (editFacultySelect.value === 'other') {
              const customValue = editFacultyInput.value.trim();
              if (customValue) {
                addSelectOptionIfNew('edit_tnt_faculty_select', customValue, 'faculty');
                addSelectOptionIfNew('tnt_faculty_select', customValue, 'faculty');
                editFacultyInput.value = customValue;
                editFacultyInput.disabled = false;
              }
            } else if (editFacultySelect.value) {
              editFacultyInput.value = editFacultySelect.value;
              editFacultyInput.disabled = false;
            }
          }
        });

        document.getElementById('tenantEditModal')?.addEventListener('click', (e) => {
          if (e.target === e.currentTarget) closeTenantModal();
        });
      });
    </script>
    <script src="../Assets/Javascript/confirm-modal.js"></script>
  </body>
</html>
