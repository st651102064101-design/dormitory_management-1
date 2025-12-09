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
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'status_name';
$orderBy = '(tnt_status = \'1\') DESC, tnt_name ASC';

switch ($sortBy) {
  case 'name_asc':
    $orderBy = 'tnt_name ASC';
    break;
  case 'name_desc':
    $orderBy = 'tnt_name DESC';
    break;
  case 'status_name':
  default:
    $orderBy = '(tnt_status = \'1\') DESC, tnt_name ASC';
}

$tenants = $pdo->query("SELECT tnt_id, tnt_name, tnt_age, tnt_address, tnt_phone, tnt_education, tnt_faculty, tnt_year, tnt_vehicle, tnt_parent, tnt_parentsphone, tnt_status FROM tenant ORDER BY $orderBy")
                ->fetchAll(PDO::FETCH_ASSOC);

$statusMap = [
  '1' => 'พักอยู่',
  '0' => 'ย้ายออก',
];

$stats = [
  'total' => count($tenants),
  'active' => 0,
  'inactive' => 0,
];
foreach ($tenants as $t) {
    if (($t['tnt_status'] ?? '0') === '1') {
        $stats['active']++;
    } else {
        $stats['inactive']++;
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
      .status-inactive { background:rgba(239,68,68,0.25); color:#f87171; }
      .table-note { color:#94a3b8; font-size:0.9rem; margin-top:0.4rem; }
      /* Modal */
      .booking-modal { display:none; position:fixed; inset:0; background:rgba(15,23,42,0.85); z-index:1000; align-items:center; justify-content:center; padding:1.5rem; }
      .booking-modal.active { display:flex; }
      .booking-modal-content { background:radial-gradient(circle at top, #1c2541, #0b0c10 60%); border:1px solid rgba(255,255,255,0.08); box-shadow:0 25px 60px rgba(7, 11, 23, 0.65); padding:1.5rem; border-radius:16px; max-width:720px; width:min(720px,95vw); color:#f5f8ff; }
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
                <h3>ย้ายออก</h3>
                <div class="stat-value" style="color:#f87171;"><?php echo number_format($stats['inactive']); ?></div>
                <div class="stat-meta">สถานะ = 0</div>
              </div>
            </div>
          </section>

          <section class="manage-panel" style="background:linear-gradient(135deg, rgba(15,23,42,0.95), rgba(2,6,23,0.95)); color:#f8fafc;">
            <div class="section-header">
              <div>
                <h1>เพิ่มผู้เช่าใหม่</h1>
                <p style="margin-top:0.25rem;color:rgba(255,255,255,0.7);">กรอกข้อมูลผู้เช่าพร้อมสถานะ</p>
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
                  <input type="number" id="tnt_age" name="tnt_age" min="0" max="120" />
                </div>
                <div class="tenant-form-group">
                  <label for="tnt_phone">เบอร์โทรศัพท์</label>
                  <input type="text" id="tnt_phone" name="tnt_phone" maxlength="10" placeholder="เช่น 0812345678" />
                </div>
                <div class="tenant-form-group">	
                  <label for="tnt_address">ที่อยู่</label>
                  <textarea id="tnt_address" name="tnt_address" rows="2"></textarea>
                </div>
                <div class="tenant-form-group">
                  <label for="tnt_education">สถานศึกษา</label>
                  <input type="text" id="tnt_education" name="tnt_education" />
                </div>
                <div class="tenant-form-group">
                  <label for="tnt_faculty">คณะ</label>
                  <input type="text" id="tnt_faculty" name="tnt_faculty" />
                </div>
                <div class="tenant-form-group">
                  <label for="tnt_year">ชั้นปี</label>
                  <input type="text" id="tnt_year" name="tnt_year" placeholder="เช่น ปี 1" />
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
                <div class="tenant-form-group">
                  <label for="tnt_status">สถานะผู้เช่า</label>
                  <select id="tnt_status" name="tnt_status">
                    <option value="1" selected>พักอยู่</option>
                    <option value="0">ย้ายออก</option>
                  </select>
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
              <select id="sortSelect" onchange="changeSortBy(this.value)" style="padding:0.6rem 0.85rem;border-radius:8px;border:1px solid rgba(255,255,255,0.2);background:rgba(255,255,255,0.05);color:#f5f8ff;font-size:0.95rem;cursor:pointer;">
                <option value="status_name" <?php echo ($sortBy === 'status_name' ? 'selected' : ''); ?>>สถานะ และ ชื่อ</option>
                <option value="name_asc" <?php echo ($sortBy === 'name_asc' ? 'selected' : ''); ?>>ชื่อ (ก-ฮ)</option>
                <option value="name_desc" <?php echo ($sortBy === 'name_desc' ? 'selected' : ''); ?>>ชื่อ (ฮ-ก)</option>
              </select>
            </div>
            <div class="report-table">
              <table class="table--compact" id="table-tenants">
                <thead>
                  <tr>
                    <th>เลขบัตรประชาชน</th>
                    <th>ชื่อ-สกุล</th>
                    <th>เบอร์โทร</th>
                    <th>สถานะ</th>
                    <th>สถานศึกษา</th>
                    <th>ผู้ปกครอง</th>
                    <th>ทะเบียนรถ</th>
                    <th class="crud-column">จัดการ</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($tenants)): ?>
                    <tr><td colspan="8" style="text-align:center; padding:1.5rem; color:#64748b;">ยังไม่มีข้อมูลผู้เช่า</td></tr>
                  <?php else: ?>
                    <?php foreach ($tenants as $t): ?>
                      <?php $statusKey = (string)($t['tnt_status'] ?? '0'); ?>
                      <tr>
                        <td style="font-weight:600;color:#f5f8ff;"><?php echo htmlspecialchars($t['tnt_id']); ?></td>
                        <td>
                          <div><?php echo htmlspecialchars($t['tnt_name'] ?? '-'); ?></div>
                          <div class="expense-meta" style="color:#94a3b8;">อายุ: <?php echo htmlspecialchars((string)($t['tnt_age'] ?? '-')); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($t['tnt_phone'] ?? '-'); ?></td>
                        <td>
                          <span class="status-badge <?php echo $statusKey === '1' ? 'status-active' : 'status-inactive'; ?>"><?php echo $statusMap[$statusKey] ?? '-'; ?></span>
                        </td>
                        <td>
                          <div><?php echo htmlspecialchars($t['tnt_education'] ?? '-'); ?></div>
                          <div class="expense-meta" style="color:#94a3b8;">คณะ: <?php echo htmlspecialchars($t['tnt_faculty'] ?? '-'); ?> | ปี: <?php echo htmlspecialchars($t['tnt_year'] ?? '-'); ?></div>
                        </td>
                        <td>
                          <div><?php echo htmlspecialchars($t['tnt_parent'] ?? '-'); ?></div>
                          <div class="expense-meta" style="color:#94a3b8;">โทร: <?php echo htmlspecialchars($t['tnt_parentsphone'] ?? '-'); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($t['tnt_vehicle'] ?? '-'); ?></td>
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
                            data-tnt-parentsphone="<?php echo htmlspecialchars($t['tnt_parentsphone'] ?? ''); ?>"
                            data-tnt-status="<?php echo htmlspecialchars($statusKey); ?>">
                            แก้ไข
                          </button>
                          <button type="button" class="animate-ui-action-btn delete btn-delete-tenant" data-tnt-id="<?php echo htmlspecialchars($t['tnt_id']); ?>">ลบ</button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
              <p class="table-note">สถานะ 1 = พักอยู่, 0 = ย้ายออก</p>
            </div>
          </section>
        </div>
      </main>
    </div>

    <!-- Edit Tenant Modal -->
    <div class="booking-modal" id="tenantEditModal">
      <div class="booking-modal-content">
        <h2>แก้ไขข้อมูลผู้เช่า</h2>
        <form id="tenantEditForm" method="POST" action="../Manage/update_tenant.php">
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
              <input type="number" id="edit_tnt_age" name="tnt_age" min="0" max="120" />
            </div>
            <div class="tenant-form-group">
              <label for="edit_tnt_phone">เบอร์โทรศัพท์</label>
              <input type="text" id="edit_tnt_phone" name="tnt_phone" maxlength="10" />
            </div>
            <div class="tenant-form-group" style="grid-column:1 / -1;">
              <label for="edit_tnt_address">ที่อยู่</label>
              <textarea id="edit_tnt_address" name="tnt_address" rows="2"></textarea>
            </div>
            <div class="tenant-form-group">
              <label for="edit_tnt_education">สถานศึกษา</label>
              <input type="text" id="edit_tnt_education" name="tnt_education" />
            </div>
            <div class="tenant-form-group">
              <label for="edit_tnt_faculty">คณะ</label>
              <input type="text" id="edit_tnt_faculty" name="tnt_faculty" />
            </div>
            <div class="tenant-form-group">
              <label for="edit_tnt_year">ชั้นปี</label>
              <input type="text" id="edit_tnt_year" name="tnt_year" />
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
            <div class="tenant-form-group">
              <label for="edit_tnt_status">สถานะผู้เช่า</label>
              <select id="edit_tnt_status" name="tnt_status">
                <option value="1">พักอยู่</option>
                <option value="0">ย้ายออก</option>
              </select>
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
    <script>
      function changeSortBy(sortValue) {
        const url = new URL(window.location);
        url.searchParams.set('sort', sortValue);
        window.location.href = url.toString();
      }

      function openTenantModal(data) {
        document.getElementById('tenantEditModal').classList.add('active');
        document.body.classList.add('modal-open');
        const setVal = (id, val = '') => { const el = document.getElementById(id); if (el) el.value = val; };
        setVal('edit_tnt_id', data.tntId || '');
        setVal('edit_tnt_id_original', data.tntId || '');
        setVal('edit_tnt_name', data.tntName || '');
        setVal('edit_tnt_age', data.tntAge || '');
        setVal('edit_tnt_phone', data.tntPhone || '');
        setVal('edit_tnt_address', data.tntAddress || '');
        setVal('edit_tnt_education', data.tntEducation || '');
        setVal('edit_tnt_faculty', data.tntFaculty || '');
        setVal('edit_tnt_year', data.tntYear || '');
        setVal('edit_tnt_vehicle', data.tntVehicle || '');
        setVal('edit_tnt_parent', data.tntParent || '');
        setVal('edit_tnt_parentsphone', data.tntParentsPhone || '');
        setVal('edit_tnt_status', data.tntStatus || '0');
      }

      function closeTenantModal() {
        const modal = document.getElementById('tenantEditModal');
        modal.classList.remove('active');
        document.body.classList.remove('modal-open');
        const form = document.getElementById('tenantEditForm');
        if (form) form.reset();
      }

      document.addEventListener('DOMContentLoaded', () => {
        // ทำให้ input tnt_id รับเฉพาะตัวเลข
        const tntIdInput = document.getElementById('tnt_id');
        if (tntIdInput) {
          tntIdInput.addEventListener('input', (e) => {
            e.target.value = e.target.value.replace(/[^\d]/g, '').slice(0, 13);
          });
        }

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
              tntStatus: btn.dataset.tntStatus,
            });
          });
        });

        document.querySelectorAll('.btn-delete-tenant').forEach(btn => {
          btn.addEventListener('click', async () => {
            const id = btn.dataset.tntId;
            if (!id) return;
            
            const confirmed = await showConfirmDialog(
              'ยืนยันการลบผู้เช่า',
              `คุณต้องการลบผู้เช่า <strong>ID: ${id}</strong> หรือไม่?<br><br>ข้อมูลทั้งหมดจะถูกลบอย่างถาวร`
            );
            
            if (!confirmed) return;
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../Manage/delete_tenant.php';
            const f = document.createElement('input');
            f.type = 'hidden';
            f.name = 'tnt_id';
            f.value = id;
            form.appendChild(f);
            document.body.appendChild(form);
            form.submit();
          });
        });

        document.getElementById('tenantForm')?.addEventListener('submit', (e) => {
          const id = document.getElementById('tnt_id');
          if (id && !/^\d{13}$/.test(id.value.trim())) {
            e.preventDefault();
            showErrorToast('เลขบัตรประชาชนต้องมี 13 หลัก');
            id.focus();
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
