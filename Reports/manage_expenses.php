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
?>
<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>จัดการค่าใช้จ่าย</title>
    <link rel="stylesheet" href="../Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="../Assets/Css/main.css" />
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
            <form action="../Manage/process_expense.php" method="post" id="expenseForm">
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
                  <input type="number" id="rate_elec" name="rate_elec" min="0" step="0.01" required value="7" />
                </div>
                <div class="expense-form-group">
                  <label for="exp_water_unit">หน่วยน้ำ <span style="color:#f87171;">*</span></label>
                  <input type="number" id="exp_water_unit" name="exp_water_unit" min="0" step="1" required value="0" />
                </div>
                <div class="expense-form-group">
                  <label for="rate_water">อัตราค่าน้ำ/หน่วย (บาท) <span style="color:#f87171;">*</span></label>
                  <input type="number" id="rate_water" name="rate_water" min="0" step="0.01" required value="20" />
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
                            <button type="button" class="animate-ui-action-btn edit" onclick="updateExpenseStatus(<?php echo (int)$exp['exp_id']; ?>, '1')">ชำระแล้ว</button>
                          <?php else: ?>
                            <button type="button" class="animate-ui-action-btn delete" onclick="updateExpenseStatus(<?php echo (int)$exp['exp_id']; ?>, '0')">ยกเลิกชำระ</button>
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

    <script src="../Assets/Javascript/animate-ui.js" defer></script>
    <script src="../Assets/Javascript/main.js" defer></script>
    <script>
      function updateExpenseStatus(expenseId, newStatus) {
        const confirmText = newStatus === '1' ? 'บันทึกการชำระเงิน' : 'ยกเลิกการชำระเงิน';
        if (!confirm(`ยืนยันการ${confirmText}?`)) return;
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '../Manage/update_expense_status.php';
        
        const idField = document.createElement('input');
        idField.type = 'hidden';
        idField.name = 'exp_id';
        idField.value = expenseId;
        
        const statusField = document.createElement('input');
        statusField.type = 'hidden';
        statusField.name = 'exp_status';
        statusField.value = newStatus;
        
        form.appendChild(idField);
        form.appendChild(statusField);
        document.body.appendChild(form);
        form.submit();
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
          elecRate.addEventListener('input', updatePreview);
          waterUnit.addEventListener('input', updatePreview);
          waterRate.addEventListener('input', updatePreview);
          
          document.getElementById('expenseForm').addEventListener('reset', () => {
            setTimeout(updatePreview, 10);
          });
        }
      })();
    </script>
  </body>
</html>
