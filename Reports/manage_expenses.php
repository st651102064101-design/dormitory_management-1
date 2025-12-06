<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

// ดึงข้อมูลค่าใช้จ่าย
$expStmt = $pdo->query("
  SELECT e.*,
         c.ctr_id, c.ctr_start, c.ctr_end,
         t.tnt_name, t.tnt_phone,
         r.room_number, r.room_id,
         rt.type_name
  FROM expense e
  LEFT JOIN contract c ON e.ctr_id = c.ctr_id
  LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
  LEFT JOIN room r ON c.room_id = r.room_id
  LEFT JOIN roomtype rt ON r.type_id = rt.type_id
  ORDER BY e.exp_month DESC, e.exp_id DESC
");
$expenses = $expStmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงสัญญาที่ใช้งานอยู่สำหรับฟอร์ม
$activeContracts = $pdo->query("
  SELECT c.ctr_id, c.room_id,
         t.tnt_name,
         r.room_number,
         rt.type_price
  FROM contract c
  LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
  LEFT JOIN room r ON c.room_id = r.room_id
  LEFT JOIN roomtype rt ON r.type_id = rt.type_id
  WHERE c.ctr_status = '0'
  ORDER BY r.room_number
")->fetchAll(PDO::FETCH_ASSOC);

// ดึงค่าไฟ/ค่าน้ำ จากตาราง rate (คอลัมน์ rate_water, rate_elec)
$rateRows = [];
try {
  $rateRows = $pdo->query("SELECT rate_id, rate_water, rate_elec FROM rate ORDER BY rate_id")
          ->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  // ใช้ค่า fallback หากไม่มีตาราง rate หรือคอลัมน์ไม่ตรง
  $rateRows = [];
}

$electricRates = array_map(function(array $row) {
  return ['rate_id' => $row['rate_id'] ?? null, 'rate_price' => $row['rate_elec'] ?? null];
}, $rateRows);
$waterRates = array_map(function(array $row) {
  return ['rate_id' => $row['rate_id'] ?? null, 'rate_price' => $row['rate_water'] ?? null];
}, $rateRows);

// fallback ค่าเดิมถ้าไม่มีข้อมูลจากตาราง rate
if (empty($electricRates)) {
  $electricRates = [ ['rate_id' => null, 'rate_price' => 7] ];
}
if (empty($waterRates)) {
  $waterRates = [ ['rate_id' => null, 'rate_price' => 20] ];
}

$statusMap = [
  '0' => 'ยังไม่จ่าย',
  '1' => 'จ่ายแล้ว',
];
$statusColors = [
  '0' => '#ef4444',
  '1' => '#22c55e',
];

// คำนวณสถิติ
$stats = [
  'unpaid' => 0,
  'paid' => 0,
  'total_unpaid' => 0,
  'total_paid' => 0,
];
foreach ($expenses as $exp) {
    if ($exp['exp_status'] === '0') {
        $stats['unpaid']++;
        $stats['total_unpaid'] += (int)($exp['exp_total'] ?? 0);
    } else {
        $stats['paid']++;
        $stats['total_paid'] += (int)($exp['exp_total'] ?? 0);
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
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - จัดการค่าใช้จ่าย</title>
    <link rel="icon" type="image/jpeg" href="../Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="../Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="../Assets/Css/main.css" />
    <link rel="stylesheet" href="../Assets/Css/confirm-modal.css" />
    <style>
      .expense-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
      }
      .expense-stat-card {
        background: linear-gradient(135deg, rgba(18,24,40,0.85), rgba(7,13,26,0.95));
        border-radius: 16px;
        padding: 1.25rem;
        border: 1px solid rgba(255,255,255,0.08);
        color: #f5f8ff;
        box-shadow: 0 15px 35px rgba(3,7,18,0.4);
      }
      .expense-stat-card h3 {
        margin: 0;
        font-size: 0.95rem;
        color: rgba(255,255,255,0.7);
      }
      .expense-stat-card .stat-value {
        font-size: 2.2rem;
        font-weight: 700;
        margin-top: 0.5rem;
      }
      .expense-stat-card .stat-money {
        font-size: 1.1rem;
        color: rgba(255,255,255,0.6);
        margin-top: 0.5rem;
      }
      .expense-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-top: 1.5rem;
      }
      .expense-form-group label {
        color: rgba(255,255,255,0.8);
        font-weight: 600;
        display: block;
        margin-bottom: 0.4rem;
        font-size: 0.9rem;
      }
      .expense-form-group input,
      .expense-form-group select {
        width: 100%;
        padding: 0.75rem 0.85rem;
        border-radius: 10px;
        border: 1px solid rgba(255,255,255,0.15);
        background: rgba(8,12,24,0.85);
        color: #f5f8ff;
        font-size: 0.95rem;
      }
      .add-type-row { display:flex; align-items:center; gap:0.5rem; justify-content:space-between; }
      .add-type-btn {
        padding: 0.5rem 0.85rem;
        border-radius: 10px;
        border: 1px dashed rgba(96,165,250,0.6);
        background: rgba(15,23,42,0.7);
        color: #60a5fa;
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
        transition: background 0.2s ease, border-color 0.2s ease;
      }
      .add-type-btn:hover { background: rgba(37,99,235,0.15); border-color: rgba(96,165,250,0.95); }
      .delete-type-btn { border-color: rgba(248,113,113,0.45); color: #fca5a5; }
      .delete-type-btn:hover { background: rgba(248,113,113,0.12); border-color: rgba(248,113,113,0.75); }
      .expense-form-group input:focus,
      .expense-form-group select:focus {
        outline: none;
        border-color: #60a5fa;
        box-shadow: 0 0 0 3px rgba(96,165,250,0.25);
      }
      .expense-form-actions {
        grid-column: 1 / -1;
        display: flex;
        gap: 0.75rem;
        margin-top: 1rem;
      }
      .calc-preview {
        grid-column: 1 / -1;
        padding: 1rem;
        background: rgba(59,130,246,0.1);
        border: 1px solid rgba(59,130,246,0.3);
        border-radius: 10px;
        color: #93c5fd;
      }
      .calc-preview h4 {
        margin: 0 0 0.5rem 0;
        font-size: 1rem;
      }
      .calc-row {
        display: flex;
        justify-content: space-between;
        padding: 0.25rem 0;
        font-size: 0.9rem;
      }
      .calc-row.total {
        margin-top: 0.5rem;
        padding-top: 0.5rem;
        border-top: 1px solid rgba(59,130,246,0.3);
        font-weight: 700;
        font-size: 1.1rem;
      }
      .expense-table-room {
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
      }
      .expense-meta {
        font-size: 0.75rem;
        color: #64748b;
      }
      .status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 80px;
        padding: 0.25rem 0.75rem;
        border-radius: 999px;
        font-size: 0.85rem;
        font-weight: 600;
        color: #fff;
      }
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
            $pageTitle = 'จัดการค่าใช้จ่าย';
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
                <p style="color:#94a3b8;margin-top:0.2rem;">สรุปยอดค่าใช้จ่ายและสถานะการชำระเงิน</p>
              </div>
            </div>
            <div class="expense-stats">
              <div class="expense-stat-card">
                <h3>ยังไม่จ่าย</h3>
                <div class="stat-value" style="color:#ef4444;"><?php echo number_format($stats['unpaid']); ?></div>
                <div class="stat-money">฿<?php echo number_format($stats['total_unpaid']); ?></div>
              </div>
              <div class="expense-stat-card">
                <h3>จ่ายแล้ว</h3>
                <div class="stat-value" style="color:#22c55e;"><?php echo number_format($stats['paid']); ?></div>
                <div class="stat-money">฿<?php echo number_format($stats['total_paid']); ?></div>
              </div>
              <div class="expense-stat-card">
                <h3>ยอดรวมทั้งหมด</h3>
                <div class="stat-value"><?php echo number_format($stats['unpaid'] + $stats['paid']); ?></div>
                <div class="stat-money">฿<?php echo number_format($stats['total_unpaid'] + $stats['total_paid']); ?></div>
              </div>
            </div>
          </section>

          <section class="manage-panel" style="background:linear-gradient(135deg, rgba(15,23,42,0.95), rgba(2,6,23,0.95)); color:#f8fafc;">
            <div class="section-header">
              <div>
                <h1>บันทึกค่าใช้จ่ายใหม่</h1>
                <p style="margin-top:0.25rem;color:rgba(255,255,255,0.7);">เลือกสัญญาและระบุค่าใช้จ่ายประจำเดือน</p>
              </div>
            </div>
            <form action="../Manage/process_expense.php" method="post" id="expenseForm" data-allow-submit="true">
              <div class="expense-form">
                <div class="expense-form-group">
                  <label for="ctr_id">สัญญา / ห้องพัก <span style="color:#f87171;">*</span></label>
                  <select name="ctr_id" id="ctr_id" required>
                    <option value="">-- เลือกสัญญา --</option>
                    <?php foreach ($activeContracts as $ctr): ?>
                      <option value="<?php echo (int)$ctr['ctr_id']; ?>" 
                              data-room-price="<?php echo (int)($ctr['type_price'] ?? 0); ?>">
                        ห้อง <?php echo str_pad((string)($ctr['room_number'] ?? '0'), 2, '0', STR_PAD_LEFT); ?> - 
                        <?php echo htmlspecialchars($ctr['tnt_name'] ?? 'ไม่ระบุ'); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="expense-form-group">
                  <label for="exp_month">เดือน/ปีที่เรียกเก็บ <span style="color:#f87171;">*</span></label>
                  <input type="month" id="exp_month" name="exp_month" required value="<?php echo date('Y-m'); ?>" />
                </div>
                <div class="expense-form-group">
                  <label for="exp_elec_unit">หน่วยไฟ <span style="color:#f87171;">*</span></label>
                  <input type="number" id="exp_elec_unit" name="exp_elec_unit" min="0" step="1" required value="0" />
                </div>
                <div class="expense-form-group">
                  <label for="rate_elec">อัตราค่าไฟ/หน่วย (บาท) <span style="color:#f87171;">*</span></label>
                  <select id="rate_elec" name="rate_elec" required>
                    <?php foreach ($electricRates as $rate): ?>
                      <option value="<?php echo (float)($rate['rate_price'] ?? 0); ?>" data-rate-id="<?php echo (int)($rate['rate_id'] ?? 0); ?>">
                        ฿<?php echo number_format((float)($rate['rate_price'] ?? 0), 2); ?> / หน่วย
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="expense-form-group">
                  <label for="exp_water_unit">หน่วยน้ำ <span style="color:#f87171;">*</span></label>
                  <input type="number" id="exp_water_unit" name="exp_water_unit" min="0" step="1" required value="0" />
                </div>
                <div class="expense-form-group">
                  <div class="add-type-row">
                    <label for="rate_water" style="margin:0;">อัตราค่าน้ำ/หน่วย (บาท) <span style="color:#f87171;">*</span></label>
                    <div style="display:flex; gap:0.35rem;">
                      <button type="button" class="add-type-btn" id="addRateBtn">+ เพิ่มเรต</button>
                      <button type="button" class="add-type-btn delete-type-btn" id="deleteRateBtn">ลบเรต</button>
                    </div>
                  </div>
                  <select id="rate_water" name="rate_water" required>
                    <?php foreach ($waterRates as $rate): ?>
                      <option value="<?php echo (float)($rate['rate_price'] ?? 0); ?>" data-rate-id="<?php echo (int)($rate['rate_id'] ?? 0); ?>">
                        ฿<?php echo number_format((float)($rate['rate_price'] ?? 0), 2); ?> / หน่วย
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                
                <div class="calc-preview" id="calcPreview" style="display:none;">
                  <h4>💰 สรุปค่าใช้จ่าย</h4>
                  <div class="calc-row">
                    <span>ค่าห้องพัก:</span>
                    <span id="preview_room">฿0</span>
                  </div>
                  <div class="calc-row">
                    <span>ค่าไฟ (<span id="preview_elec_unit">0</span> หน่วย × <span id="preview_elec_rate">0</span>):</span>
                    <span id="preview_elec">฿0</span>
                  </div>
                  <div class="calc-row">
                    <span>ค่าน้ำ (<span id="preview_water_unit">0</span> หน่วย × <span id="preview_water_rate">0</span>):</span>
                    <span id="preview_water">฿0</span>
                  </div>
                  <div class="calc-row total">
                    <span>ยอดรวมทั้งหมด:</span>
                    <span id="preview_total" style="color:#22c55e;">฿0</span>
                  </div>
                </div>

                <div class="expense-form-actions">
                  <button type="submit" class="animate-ui-add-btn" style="flex:2;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></svg>
                    บันทึกค่าใช้จ่าย
                  </button>
                  <button type="reset" class="animate-ui-action-btn delete" style="flex:1;">ล้างข้อมูล</button>
                </div>
              </div>
            </form>
          </section>

          <section class="manage-panel">
            <div class="section-header">
              <div>
                <h1>รายการค่าใช้จ่ายทั้งหมด</h1>
                <p style="color:#94a3b8;margin-top:0.2rem;">ประวัติการเรียกเก็บค่าใช้จ่ายแต่ละห้อง</p>
              </div>
            </div>
            <div class="report-table">
              <table class="table--compact" id="table-expenses">
                <thead>
                  <tr>
                    <th>รหัส</th>
                    <th>ห้อง/ผู้เช่า</th>
                    <th>เดือน/ปี</th>
                    <th>ค่าห้อง</th>
                    <th>ค่าไฟ</th>
                    <th>ค่าน้ำ</th>
                    <th>ยอดรวม</th>
                    <th>สถานะ</th>
                    <th class="crud-column">จัดการ</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($expenses)): ?>
                    <tr>
                      <td colspan="9" style="text-align:center;padding:2rem;color:#64748b;">ยังไม่มีข้อมูลค่าใช้จ่าย</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($expenses as $exp): ?>
                      <tr>
                        <td>#<?php echo str_pad((string)$exp['exp_id'], 4, '0', STR_PAD_LEFT); ?></td>
                        <td>
                          <div class="expense-table-room">
                            <span>ห้อง <?php echo str_pad((string)($exp['room_number'] ?? '0'), 2, '0', STR_PAD_LEFT); ?></span>
                            <span class="expense-meta"><?php echo htmlspecialchars($exp['tnt_name'] ?? '-'); ?></span>
                          </div>
                        </td>
                        <td><?php echo $exp['exp_month'] ? date('m/Y', strtotime($exp['exp_month'])) : '-'; ?></td>
                        <td>฿<?php echo number_format((int)($exp['room_price'] ?? 0)); ?></td>
                        <td>
                          <div><?php echo number_format((int)($exp['exp_elec_unit'] ?? 0)); ?> หน่วย</div>
                          <div class="expense-meta">฿<?php echo number_format((int)($exp['exp_elec_chg'] ?? 0)); ?></div>
                        </td>
                        <td>
                          <div><?php echo number_format((int)($exp['exp_water_unit'] ?? 0)); ?> หน่วย</div>
                          <div class="expense-meta">฿<?php echo number_format((int)($exp['exp_water'] ?? 0)); ?></div>
                        </td>
                        <td><strong style="color:#22c55e;">฿<?php echo number_format((int)($exp['exp_total'] ?? 0)); ?></strong></td>
                        <td>
                          <?php $status = (string)($exp['exp_status'] ?? ''); ?>
                          <span class="status-badge" style="background: <?php echo $statusColors[$status] ?? '#94a3b8'; ?>;">
                            <?php echo $statusMap[$status] ?? 'ไม่ระบุ'; ?>
                          </span>
                        </td>
                        <td class="crud-column">
                          <?php if ($status === '0'): ?>
                            <button type="button" class="animate-ui-action-btn btn-success update-status-btn" data-expense-id="<?php echo (int)$exp['exp_id']; ?>" data-new-status="1">ชำระแล้ว</button>
                          <?php else: ?>
                            <button type="button" class="animate-ui-action-btn btn-cancel update-status-btn" data-expense-id="<?php echo (int)$exp['exp_id']; ?>" data-new-status="0">ยกเลิกชำระ</button>
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

    <script src="../Assets/Javascript/toast-notification.js"></script>
    <script src="../Assets/Javascript/confirm-modal.js"></script>
    <script src="../Assets/Javascript/animate-ui.js"></script>
    <script src="../Assets/Javascript/main.js"></script>
    <script>
      // AJAX delete expense
      async function deleteExpense(expenseId) {
        if (!expenseId) return;
        
        const confirmed = await showConfirmDialog(
          'ยืนยันการลบ',
          `คุณต้องการลบรายการค่าใช้จ่ายนี้ <strong>ถาวร</strong> หรือไม่?`,
          'delete'
        );
        
        if (!confirmed) return;
        
        try {
          const formData = new FormData();
          formData.append('exp_id', expenseId);
          
          const response = await fetch('../Manage/delete_expense.php', {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          });
          
          const result = await response.json();
          
          if (result.success) {
            showSuccessToast(result.message);
            
            // Reload expense list after 500ms
            setTimeout(() => {
              loadExpenseList();
            }, 500);
          } else {
            showErrorToast(result.error || 'เกิดข้อผิดพลาด');
          }
        } catch (error) {
          console.error('Error:', error);
          showErrorToast('เกิดข้อผิดพลาดในการเชื่อมต่อ');
        }
      }

      // AJAX update expense status
      async function updateExpenseStatus(expenseId, newStatus) {
        console.log('updateExpenseStatus called:', expenseId, newStatus);
        
        if (!expenseId) {
          console.error('No expenseId provided');
          return;
        }
        
        const statusNum = parseInt(newStatus);
        const statusText = statusNum === 1 ? 'จ่ายแล้ว' : 'ยังไม่จ่าย';
        
        // ใช้ custom confirm modal
        console.log('Showing confirm dialog...');
        console.log('showConfirmDialog available:', typeof showConfirmDialog);
        let confirmed = false;
        
        if (typeof showConfirmDialog === 'function') {
          try {
            console.log('Calling showConfirmDialog with:', {
              title: 'ยืนยันการเปลี่ยนสถานะ',
              message: `คุณต้องการเปลี่ยนสถานะเป็น <strong>"${statusText}"</strong> หรือไม่?`,
              type: 'warning'
            });
            confirmed = await showConfirmDialog(
              'ยืนยันการเปลี่ยนสถานะ',
              `คุณต้องการเปลี่ยนสถานะเป็น <strong>"${statusText}"</strong> หรือไม่?`,
              'warning'
            );
            console.log('showConfirmDialog returned:', confirmed);
          } catch (error) {
            console.error('showConfirmDialog error:', error);
            confirmed = confirm('คุณต้องการเปลี่ยนสถานะเป็น "' + statusText + '" หรือไม่?');
          }
        } else {
          console.warn('showConfirmDialog not a function, using browser confirm');
          confirmed = confirm('คุณต้องการเปลี่ยนสถานะเป็น "' + statusText + '" หรือไม่?');
        }
        
        console.log('Confirm result:', confirmed);
        
        if (!confirmed) {
          console.log('User cancelled');
          return;
        }
        
        console.log('User confirmed, proceeding...');
        
        try {
          console.log('Sending request to update status...');
          const formData = new FormData();
          formData.append('exp_id', expenseId);
          formData.append('exp_status', statusNum);
          
          const response = await fetch('../Manage/update_expense_status_ajax.php', {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          });
          
          console.log('Response received:', response.status);
          const result = await response.json();
          console.log('Result:', result);
          
          if (result.success) {
            if (typeof showSuccessToast === 'function') {
              showSuccessToast(result.message);
            } else {
              alert(result.message);
            }
            
            // Reload expense list after 500ms
            setTimeout(() => {
              loadExpenseList();
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
            showErrorToast('เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + error.message);
          } else {
            alert('เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + error.message);
          }
        }
      }

      // Load expense list and stats
      function loadExpenseList() {
        fetch(window.location.href)
          .then(response => response.text())
          .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // Update table
            const newTable = doc.querySelector('#table-expenses tbody');
            const currentTable = document.querySelector('#table-expenses tbody');
            if (newTable && currentTable) {
              currentTable.innerHTML = newTable.innerHTML;
              // Re-setup status buttons after updating table
              setupStatusButtons();
            }
            
            // Update stats cards
            const statsCards = doc.querySelectorAll('.expense-stat-card .stat-value');
            const currentStats = document.querySelectorAll('.expense-stat-card .stat-value');
            if (statsCards.length === currentStats.length) {
              statsCards.forEach((stat, i) => {
                if (currentStats[i]) {
                  currentStats[i].textContent = stat.textContent;
                }
              });
            }
            
            // Update stat money values
            const statsMoney = doc.querySelectorAll('.expense-stat-card .stat-money');
            const currentMoney = document.querySelectorAll('.expense-stat-card .stat-money');
            if (statsMoney.length === currentMoney.length) {
              statsMoney.forEach((money, i) => {
                if (currentMoney[i]) {
                  currentMoney[i].textContent = money.textContent;
                }
              });
            }
          })
          .catch(error => {
            console.error('Error loading expense list:', error);
          });
      }

      // Setup event listeners for update status buttons
      function setupStatusButtons() {
        console.log('Setting up status buttons...');
        const buttons = document.querySelectorAll('.update-status-btn');
        console.log('Found buttons:', buttons.length);
        
        buttons.forEach(button => {
          button.addEventListener('click', function(e) {
            e.preventDefault();
            const expenseId = parseInt(this.getAttribute('data-expense-id'));
            const newStatus = this.getAttribute('data-new-status');
            console.log('Button clicked:', expenseId, newStatus);
            updateExpenseStatus(expenseId, newStatus);
          });
        });
      }

      // Call setup when DOM is ready
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupStatusButtons);
      } else {
        setupStatusButtons();
      }

      (function setupExpenseCalculator() {
        const ctrSelect = document.getElementById('ctr_id');
        const elecUnit = document.getElementById('exp_elec_unit');
        const elecRate = document.getElementById('rate_elec');
        const waterUnit = document.getElementById('exp_water_unit');
        const waterRate = document.getElementById('rate_water');
        const preview = document.getElementById('calcPreview');
        
        function updatePreview() {
          const selectedOpt = ctrSelect.options[ctrSelect.selectedIndex];
          const roomPrice = selectedOpt ? parseInt(selectedOpt.dataset.roomPrice || '0') : 0;
          const elecU = parseInt(elecUnit.value || '0');
          const elecR = parseFloat(elecRate.value || '0');
          const waterU = parseInt(waterUnit.value || '0');
          const waterR = parseFloat(waterRate.value || '0');
          
          const elecChg = Math.round(elecU * elecR);
          const waterChg = Math.round(waterU * waterR);
          const total = roomPrice + elecChg + waterChg;
          
          if (roomPrice > 0 || elecU > 0 || waterU > 0) {
            preview.style.display = 'block';
            document.getElementById('preview_room').textContent = '฿' + roomPrice.toLocaleString();
            document.getElementById('preview_elec_unit').textContent = elecU;
            document.getElementById('preview_elec_rate').textContent = elecR.toFixed(2);
            document.getElementById('preview_elec').textContent = '฿' + elecChg.toLocaleString();
            document.getElementById('preview_water_unit').textContent = waterU;
            document.getElementById('preview_water_rate').textContent = waterR.toFixed(2);
            document.getElementById('preview_water').textContent = '฿' + waterChg.toLocaleString();
            document.getElementById('preview_total').textContent = '฿' + total.toLocaleString();
          } else {
            preview.style.display = 'none';
          }
        }
        
        if (ctrSelect && elecUnit && elecRate && waterUnit && waterRate) {
          ctrSelect.addEventListener('change', updatePreview);
          elecUnit.addEventListener('input', updatePreview);
          elecRate.addEventListener('change', updatePreview);
          waterUnit.addEventListener('input', updatePreview);
          waterRate.addEventListener('change', updatePreview);
          
          const expenseForm = document.getElementById('expenseForm');
          
          expenseForm.addEventListener('reset', () => {
            setTimeout(updatePreview, 10);
          });
          
          // Submit form with AJAX
          expenseForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const submitBtn = expenseForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'กำลังบันทึก...';
            
            try {
              const response = await fetch('../Manage/process_expense.php', {
                method: 'POST',
                body: new FormData(expenseForm),
                headers: {
                  'X-Requested-With': 'XMLHttpRequest'
                }
              });
              
              const result = await response.json();
              
              if (result.success) {
                showSuccessToast(result.message || 'บันทึกค่าใช้จ่ายเรียบร้อยแล้ว');
                expenseForm.reset();
                setTimeout(() => {
                  loadExpenseList();
                }, 500);
              } else {
                showErrorToast(result.error || 'เกิดข้อผิดพลาด');
              }
            } catch (error) {
              console.error('Error:', error);
              showErrorToast('เกิดข้อผิดพลาดในการเชื่อมต่อ');
            } finally {
              submitBtn.disabled = false;
              submitBtn.textContent = originalText;
            }
          });

          updatePreview();
        }
      })();

                // Custom Input Dialog
                function showInputDialog(title, label, placeholder = '', type = 'number') {
                  return new Promise((resolve) => {
                    const overlay = document.createElement('div');
                    overlay.className = 'confirm-overlay';
                    overlay.innerHTML = `
                      <div class="confirm-modal" style="border-color: rgba(96, 165, 250, 0.3);">
                        <div class="confirm-header">
                          <div class="confirm-icon" style="background: linear-gradient(135deg, #3b82f6, #2563eb);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                          </div>
                          <h3 class="confirm-title" style="color: #60a5fa;">${title}</h3>
                        </div>
                        <div class="confirm-message">
                          <label style="display: block; margin-bottom: 0.75rem; color: #cbd5e1; font-weight: 500;">${label}</label>
                          <input type="${type}" class="custom-input" placeholder="${placeholder}" 
                                 style="width: 100%; padding: 0.85rem; border-radius: 10px; border: 1px solid rgba(96, 165, 250, 0.3); 
                                        background: rgba(15, 23, 42, 0.8); color: #f5f8ff; font-size: 1rem;" />
                        </div>
                        <div class="confirm-actions">
                          <button class="confirm-btn confirm-btn-cancel" data-action="cancel">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                              <line x1="18" y1="6" x2="6" y2="18"/>
                              <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                            ยกเลิก
                          </button>
                          <button class="confirm-btn" data-action="confirm" style="background: #3b82f6;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                              <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            ตกลง
                          </button>
                        </div>
                      </div>
                    `;
                    
                    const input = overlay.querySelector('.custom-input');
                    const cancelBtn = overlay.querySelector('[data-action="cancel"]');
                    const confirmBtn = overlay.querySelector('[data-action="confirm"]');
                    
                    // Focus input
                    setTimeout(() => input.focus(), 100);
                    
                    // Handle Enter key
                    input.addEventListener('keydown', (e) => {
                      if (e.key === 'Enter') {
                        e.preventDefault();
                        overlay.remove();
                        resolve(input.value);
                      }
                    });
                    
                    // Handle buttons
                    cancelBtn.addEventListener('click', () => {
                      overlay.remove();
                      resolve(null);
                    });
                    
                    confirmBtn.addEventListener('click', () => {
                      overlay.remove();
                      resolve(input.value);
                    });
                    
                    // Close on overlay click
                    overlay.addEventListener('click', (e) => {
                      if (e.target === overlay) {
                        overlay.remove();
                        resolve(null);
                      }
                    });
                    
                    // ESC key
                    const handleKeyDown = (e) => {
                      if (e.key === 'Escape') {
                        overlay.remove();
                        resolve(null);
                        document.removeEventListener('keydown', handleKeyDown);
                      }
                    };
                    document.addEventListener('keydown', handleKeyDown);
                    
                    document.body.appendChild(overlay);
                  });
                }

                // เพิ่ม/ลบเรตน้ำไฟ
                async function addRateFlow() {
                  const water = await showInputDialog('เพิ่มอัตราค่าน้ำ', 'กรอกอัตราค่าน้ำ/หน่วย (บาท)', '20', 'number');
                  if (water === null || water === '') return;
                  const waterVal = parseFloat(water);
                  if (Number.isNaN(waterVal) || waterVal < 0) { 
                    showErrorToast('กรุณากรอกตัวเลขค่าน้ำให้ถูกต้อง'); 
                    return; 
                  }

                  const elec = await showInputDialog('เพิ่มอัตราค่าไฟ', 'กรอกอัตราค่าไฟ/หน่วย (บาท)', '7', 'number');
                  if (elec === null || elec === '') return;
                  const elecVal = parseFloat(elec);
                  if (Number.isNaN(elecVal) || elecVal < 0) { 
                    showErrorToast('กรุณากรอกตัวเลขค่าไฟให้ถูกต้อง'); 
                    return; 
                  }

                  const formData = new FormData();
                  formData.append('rate_water', waterVal.toString());
                  formData.append('rate_elec', elecVal.toString());

                  fetch('../Manage/add_rate.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                      if (!data.success) throw new Error(data.message || 'เพิ่มเรตไม่สำเร็จ');
                      const waterSel = document.getElementById('rate_water');
                      const elecSel = document.getElementById('rate_elec');
                      const optWater = document.createElement('option');
                      optWater.value = waterVal;
                      optWater.dataset.rateId = data.rate_id;
                      optWater.textContent = `฿${waterVal.toFixed(2)} / หน่วย`;
                      const optElec = document.createElement('option');
                      optElec.value = elecVal;
                      optElec.dataset.rateId = data.rate_id;
                      optElec.textContent = `฿${elecVal.toFixed(2)} / หน่วย`;
                      waterSel.appendChild(optWater);
                      elecSel.appendChild(optElec);
                      waterSel.value = waterVal;
                      elecSel.value = elecVal;
                      showSuccessToast('เพิ่มเรตเรียบร้อยแล้ว');
                      if (typeof updatePreview === 'function') updatePreview();
                    })
                    .catch(err => {
                      console.error(err);
                      showErrorToast(err.message || 'เพิ่มเรตไม่สำเร็จ');
                    });
                }

                function deleteRateFlow() {
                  const waterSel = document.getElementById('rate_water');
                  const elecSel = document.getElementById('rate_elec');
                  const selected = waterSel.options[waterSel.selectedIndex];
                  const rateId = selected ? parseInt(selected.dataset.rateId || '0') : 0;
                  if (!rateId) { 
                    showErrorToast('เรตนี้ลบไม่ได้ (ไม่มีรหัส)'); 
                    return; 
                  }
                  
                  const formData = new FormData();
                  formData.append('rate_id', rateId.toString());

                  fetch('../Manage/delete_rate.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                      if (!data.success) throw new Error(data.message || 'ลบเรตไม่สำเร็จ');
                      // remove matching rate_id in both selects
                      [waterSel, elecSel].forEach(sel => {
                        [...sel.options].forEach((o, idx) => {
                          if (parseInt(o.dataset.rateId || '0') === rateId) {
                            sel.remove(idx);
                          }
                        });
                      });
                      // เลือก option แรกที่เหลืออยู่
                      if (waterSel.options.length) waterSel.selectedIndex = 0;
                      if (elecSel.options.length) elecSel.selectedIndex = 0;
                      showSuccessToast('ลบเรตเรียบร้อยแล้ว');
                      if (typeof updatePreview === 'function') updatePreview();
                    })
                    .catch(err => {
                      console.error(err);
                      showErrorToast(err.message || 'ลบเรตไม่สำเร็จ');
                    });
                }

                document.getElementById('addRateBtn')?.addEventListener('click', addRateFlow);
                document.getElementById('deleteRateBtn')?.addEventListener('click', deleteRateFlow);
    </script>
  </body>
</html>
