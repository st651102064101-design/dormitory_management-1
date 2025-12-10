<?php
require_once '../ConnectDB.php';

// Initialize database connection
$conn = connectDB();

// ดึง theme color จากการตั้งค่าระบบ
$settingsStmt = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'theme_color' LIMIT 1");
$themeColor = '#0f172a'; // ค่า default (dark mode)
if ($settingsStmt) {
    $theme = $settingsStmt->fetch(PDO::FETCH_ASSOC);
    if ($theme && !empty($theme['setting_value'])) {
        $themeColor = htmlspecialchars($theme['setting_value'], ENT_QUOTES, 'UTF-8');
    }
}

// Get all contracts with related data
try {
    $stmt = $conn->prepare("SELECT c.*, 
        t.tnt_name, t.tnt_phone,
        r.room_number, r.room_status,
        rt.type_name
        FROM contract c
        LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
        LEFT JOIN room r ON c.room_id = r.room_id
        LEFT JOIN roomtype rt ON r.type_id = rt.type_id
        ORDER BY c.ctr_start DESC");
    $stmt->execute();
    $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("DEBUG: Contracts found: " . count($contracts));
    if(count($contracts) > 0) {
        error_log("DEBUG: First contract: " . json_encode($contracts[0]));
    }
} catch(Exception $e) {
    $contracts = [];
    $error = "ข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
    error_log("ERROR: Contract query error: " . $e->getMessage());
}

// Count contracts by status
$statusCounts = [
    '0' => 0,
    '1' => 0,
    '2' => 0
];

foreach($contracts as $contract) {
    $status = $contract['ctr_status'] ?? '0';
    // Ensure status is a string key
    $status = (string)$status;
    if(isset($statusCounts[$status])) {
        $statusCounts[$status]++;
    }
}

$statusLabels = [
    '0' => 'ปกติ',
    '1' => 'ยกเลิกแล้ว',
    '2' => 'แจ้งยกเลิก'
];

$statusColors = [
    '0' => '#4CAF50',
    '1' => '#f44336',
    '2' => '#FF9800'
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสัญญา</title>
    <link rel="stylesheet" href="../Assets/Css/main.css">
    <link rel="stylesheet" href="../Assets/Css/animate-ui.css">
    <link rel="stylesheet" href="../Assets/Css/confirm-modal.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4/dist/style.css">
    <style>
      :root {
        --theme-bg-color: <?php echo $themeColor; ?>;
      }
      
      body {
        background: var(--bg-primary);
        color: var(--text-primary);
      }
      main::-webkit-scrollbar {
        display: none;
      }
      .manage-panel {
        margin: 1.5rem;
        margin-bottom: 3rem;
        padding: 1.5rem;
        background: var(--card-bg);
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      }
      h1 {
        margin: 0 0 1.5rem 0;
        color: var(--text-primary);
      }
      .contract-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
      }
      .contract-stat-card {
        padding: 1.25rem;
        background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
        border: 1px solid rgba(255,255,255,0.2);
        border-radius: 8px;
        text-align: center;
      }
      .contract-stat-card .stat-value {
        font-size: 2rem;
        font-weight: bold;
        margin-bottom: 0.5rem;
      }
      .contract-stat-card .stat-label {
        font-size: 0.9rem;
        opacity: 0.85;
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
      
      /* Light theme overrides for stat cards */
      @media (prefers-color-scheme: light) {
        .contract-stat-card {
          background: linear-gradient(135deg, rgba(0,0,0,0.05) 0%, rgba(0,0,0,0.03) 100%) !important;
          border: 1px solid rgba(0,0,0,0.1) !important;
        }
        .contract-stat-card .stat-chip {
          background: rgba(0,0,0,0.08) !important;
        }
      }
      
      /* JavaScript-detected light theme class */
      html.light-theme .contract-stat-card {
        background: linear-gradient(135deg, rgba(0,0,0,0.05) 0%, rgba(0,0,0,0.03) 100%) !important;
        border: 1px solid rgba(0,0,0,0.1) !important;
      }
      
      html.light-theme .contract-stat-card .stat-chip {
        background: rgba(0,0,0,0.08) !important;
      }
      .form-toggle-btn {
        padding: 0.6rem 1.2rem;
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 1rem;
        margin-bottom: 1.5rem;
        transition: background 0.3s ease;
      }
      .form-toggle-btn:hover {
        background: var(--primary-hover);
      }
      .contract-form {
        display: block;
        padding: 1.5rem;
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 6px;
        margin-bottom: 2rem;
      }
      .contract-form.hide {
        display: none;
      }
      .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
      }
      .form-group {
        display: flex;
        flex-direction: column;
      }
      .form-group label {
        margin-bottom: 0.3rem;
        font-size: 0.9rem;
        font-weight: 500;
      }
      .form-group input,
      .form-group select {
        padding: 0.5rem;
        border: 1px solid rgba(255,255,255,0.2);
        border-radius: 4px;
        background: rgba(255,255,255,0.05);
        color: #e2e8f0;
        font-size: 0.95rem;
      }
      .form-group input:focus,
      .form-group select:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 4px rgba(255,255,255,0.1);
      }
      
      /* Light theme overrides for form inputs */
      @media (prefers-color-scheme: light) {
        .form-group input,
        .form-group select {
          background: #ffffff !important;
          color: #1f2937 !important;
          border: 1px solid #e5e7eb !important;
        }
        .form-group input::placeholder {
          color: #9ca3af !important;
        }
        .form-group label {
          color: #374151 !important;
        }
      }
      
      /* JavaScript-detected light theme class */
      html.light-theme .form-group input,
      html.light-theme .form-group select {
        background: #ffffff !important;
        color: #1f2937 !important;
        border: 1px solid #e5e7eb !important;
      }
      
      html.light-theme .form-group input::placeholder {
        color: #9ca3af !important;
      }
      
      html.light-theme .form-group label {
        color: #374151 !important;
      }
      .form-actions {
        display: flex;
        gap: 0.5rem;
      }
      .form-actions button {
        padding: 0.6rem 1.2rem;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.95rem;
        transition: all 0.3s ease;
      }
      .btn-submit {
        background: #4CAF50;
        color: white;
      }
      .btn-submit:hover {
        background: #45a049;
      }
      .btn-cancel {
        background: rgba(255,255,255,0.1);
        color: var(--text-primary);
      }
      .btn-cancel:hover {
        background: rgba(255,255,255,0.15);
      }
      .quick-date-btn {
        padding: 0.4rem 0.8rem;
        background: rgba(255,255,255,0.1);
        border: 1px solid rgba(255,255,255,0.2);
        border-radius: 4px;
        color: var(--text-primary);
        cursor: pointer;
        font-size: 0.85rem;
        transition: all 0.2s ease;
      }
      .quick-date-btn:hover {
        background: rgba(255,255,255,0.2);
        border-color: var(--primary-color);
      }
      /* Table overrides for proper display */
      .report-table {
        width: 100%;
        display: table !important;
        overflow-x: auto;
      }
      .report-table thead {
        display: table-header-group;
      }
      .report-table tbody {
        display: table-row-group;
        max-height: none;
        overflow: visible;
      }
      .report-table tr {
        display: table-row;
      }
      .report-table th,
      .report-table td {
        display: table-cell !important;
        padding: 0.75rem;
        text-align: left;
        border-bottom: 1px solid rgba(255,255,255,0.1);
      }
      .status-badge {
        display: inline-block;
        padding: 0.4rem 0.8rem;
        border-radius: 4px;
        font-size: 0.85rem;
        font-weight: 500;
        text-align: center;
      }
    </style>
</head>
<body>
    <div style="display: flex;">
        <?php include '../includes/sidebar.php'; ?>
        <main style="flex: 1; overflow-y: auto; height: 100vh; scrollbar-width: none; -ms-overflow-style: none; padding-bottom: 4rem;">
            
            <div class="manage-panel">
              <?php include '../includes/page_header.php'; ?>

                <!-- Statistics -->
                <div class="contract-stats">
                    <div class="contract-stat-card">
                        <div class="stat-value" style="color: #4CAF50;"><?php echo $statusCounts['0']; ?></div>
                        <div class="stat-label">สัญญาปกติ</div>
                        <div class="stat-chip">
                            <span style="display: inline-block; width: 8px; height: 8px; background: #4CAF50; border-radius: 50%;"></span>
                            ยังมีผลบังคับใช้
                        </div>
                    </div>
                    <div class="contract-stat-card">
                        <div class="stat-value" style="color: #FF9800;"><?php echo $statusCounts['2']; ?></div>
                        <div class="stat-label">สัญญาแจ้งยกเลิก</div>
                        <div class="stat-chip">
                            <span style="display: inline-block; width: 8px; height: 8px; background: #FF9800; border-radius: 50%;"></span>
                            ได้แจ้งยกเลิก
                        </div>
                    </div>
                    <div class="contract-stat-card">
                        <div class="stat-value" style="color: #f44336;"><?php echo $statusCounts['1']; ?></div>
                        <div class="stat-label">สัญญาที่ยกเลิก</div>
                        <div class="stat-chip">
                            <span style="display: inline-block; width: 8px; height: 8px; background: #f44336; border-radius: 50%;"></span>
                            ยกเลิกแล้ว
                        </div>
                    </div>
                </div>

                <!-- Add Contract Form Toggle -->
                <button class="form-toggle-btn" id="toggleFormBtn">+ เพิ่มสัญญาใหม่</button>

                <!-- Add Contract Form -->
                <form class="contract-form" id="contractForm" action="../Manage/process_contract.php" method="POST" onsubmit="return validateForm()">
                    <h3 style="margin-top: 0;">เพิ่มสัญญาเช่าใหม่</h3>
                    <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem; margin: 0 0 1rem 0;">
                        📝 เลือกเฉพาะผู้เช่าและห้องพัก - วันที่และเงินประกันจะถูกกำหนดอัตโนมัติ
                    </p>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="tnt_id">ผู้เช่า *</label>
                            <select id="tnt_id" name="tnt_id" required>
                                <option value="">-- เลือกผู้เช่า --</option>
                                <?php
                                try {
                                    $stmt = $conn->prepare("SELECT tnt_id, tnt_name FROM tenant WHERE tnt_status = 2 ORDER BY tnt_name");
                                    $stmt->execute();
                                    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    foreach($tenants as $tenant) {
                                        echo "<option value='{$tenant['tnt_id']}'>{$tenant['tnt_name']}</option>";
                                    }
                                } catch(Exception $e) {
                                    echo "<option value=''>ไม่สามารถโหลดข้อมูล</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="room_id">ห้องพัก *</label>
                            <select id="room_id" name="room_id" required>
                                <option value="">-- เลือกห้องพัก --</option>
                                <?php
                                try {
                                    $stmt = $conn->prepare("SELECT r.room_id, r.room_number, rt.type_name FROM room r LEFT JOIN roomtype rt ON r.type_id = rt.type_id WHERE r.room_status = 0 ORDER BY rt.type_name, CAST(r.room_number AS UNSIGNED)");
                                    $stmt->execute();
                                    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    $currentType = '';
                                    foreach($rooms as $room) {
                                        if($currentType !== $room['type_name']) {
                                            if($currentType !== '') echo "</optgroup>";
                                            $currentType = $room['type_name'];
                                            echo "<optgroup label='{$currentType}'>";
                                        }
                                        echo "<option value='{$room['room_id']}'>ห้อง {$room['room_number']}</option>";
                                    }
                                    if($currentType !== '') echo "</optgroup>";
                                } catch(Exception $e) {
                                    echo "<option value=''>ไม่สามารถโหลดข้อมูล</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="contract_duration">ระยะเวลาสัญญา *</label>
                            <select id="contract_duration" name="contract_duration" required style="padding: 0.5rem; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; background: rgba(255,255,255,0.05); color: var(--text-primary); font-size: 0.95rem;">
                                <option value="3">3 เดือน</option>
                                <option value="6" selected>6 เดือน (แนะนำ)</option>
                                <option value="12">12 เดือน (1 ปี)</option>
                            </select>
                        </div>
                        <div class="form-group" style="display: none;">
                            <input type="date" id="ctr_start" name="ctr_start" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group" style="display: none;">
                            <input type="date" id="ctr_end" name="ctr_end" required>
                        </div>
                        <div class="form-group" style="display: none;">
                            <input type="number" id="ctr_deposit" name="ctr_deposit" value="2000">
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1; padding: 1rem; background: rgba(76, 175, 80, 0.1); border: 1px solid rgba(76, 175, 80, 0.3); border-radius: 4px;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <span style="font-size: 1.2rem;">ℹ️</span>
                                <strong style="color: #4CAF50;">ข้อมูลที่จะถูกบันทึกอัตโนมัติ:</strong>
                            </div>
                            <ul style="margin: 0; padding-left: 1.5rem; color: rgba(255,255,255,0.8);">
                                <li>📅 วันเริ่มสัญญา: <strong style="color: #4CAF50;">วันนี้ (<?php echo date('d/m/Y'); ?>)</strong></li>
                                <li>📅 วันสิ้นสุดสัญญา: <strong style="color: #4CAF50;" id="end_date_display">6 เดือนจากวันนี้</strong></li>
                                <li>💰 เงินประกัน: <strong style="color: #4CAF50;">2,000 บาท</strong></li>
                            </ul>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-submit">บันทึกสัญญา</button>
                        <button type="button" class="btn-cancel" onclick="document.getElementById('contractForm').classList.add('hide'); document.getElementById('toggleFormBtn').textContent = '+ เพิ่มสัญญาใหม่';">ยกเลิก</button>
                    </div>
                </form>

                <!-- Table Section -->
                <div style="display: block !important; width: 100%;">
                    <h3>รายชื่อสัญญา</h3>
                    <!-- Debug: Total contracts: <?php echo count($contracts); ?> -->
                    <table id="table-contracts" class="report-table" style="margin-bottom: 2rem;">
                        <thead>
                            <tr>
                                <th>เลขที่สัญญา</th>
                                <th>ผู้เช่า</th>
                                <th>ห้องพัก</th>
                                <th>วันเริ่มสัญญา</th>
                                <th>วันสิ้นสุด</th>
                                <th>สถานะ</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($contracts as $contract): 
                                $status = $contract['ctr_status'] ?? '0';
                                $statusLabel = $statusLabels[$status] ?? 'ไม่ระบุ';
                                $statusColor = $statusColors[$status] ?? '#999';
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($contract['ctr_id'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($contract['tnt_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($contract['room_number'] ?? 'N/A'); ?></td>
                                <td><?php echo isset($contract['ctr_start']) ? date('d/m/Y', strtotime($contract['ctr_start'])) : ''; ?></td>
                                <td><?php echo isset($contract['ctr_end']) ? date('d/m/Y', strtotime($contract['ctr_end'])) : ''; ?></td>
                                <td>
                                    <span class="status-badge" style="background-color: <?php echo $statusColor; ?>; color: white;">
                                        <?php echo $statusLabel; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (($contract['ctr_status'] ?? '0') === '2'): ?>
                                        <form method="POST" action="../Manage/update_contract_status.php" style="margin:0;">
                                            <input type="hidden" name="ctr_id" value="<?php echo htmlspecialchars($contract['ctr_id'] ?? ''); ?>">
                                            <input type="hidden" name="ctr_status" value="1">
                                            <button type="submit" class="quick-date-btn" style="background:#f44336; color:white; border-color:#f44336;">ยกเลิกสัญญา</button>
                                        </form>
                                    <?php elseif (($contract['ctr_status'] ?? '0') === '1'): ?>
                                        <span style="color: rgba(255,255,255,0.7);">ยกเลิกแล้ว</span>
                                    <?php else: ?>
                                        <span style="color: rgba(255,255,255,0.7);">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script src="../Assets/Javascript/animate-ui.js" defer></script>
    <script src="../Assets/Javascript/main.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4"></script>
    <script>
        // Form toggle
        const toggleBtn = document.getElementById('toggleFormBtn');
        const contractForm = document.getElementById('contractForm');
        const formVisibleKey = 'contractFormVisible';

        toggleBtn.addEventListener('click', function() {
            contractForm.classList.toggle('hide');
            const isHidden = contractForm.classList.contains('hide');
            localStorage.setItem(formVisibleKey, !isHidden);
            toggleBtn.textContent = isHidden ? '+ เพิ่มสัญญาใหม่' : '- ซ่อนฟอร์ม';
        });

        // Restore form visibility from localStorage
        if(localStorage.getItem(formVisibleKey) === 'false') {
            contractForm.classList.add('hide');
            toggleBtn.textContent = '+ เพิ่มสัญญาใหม่';
        } else {
            toggleBtn.textContent = '- ซ่อนฟอร์ม';
        }

        // Auto-calculate dates
        function calculateDates() {
            const today = new Date();
            const durationSelect = document.getElementById('contract_duration');
            const months = parseInt(durationSelect.value, 10) || 6;

            const endDate = new Date(today);
            endDate.setMonth(endDate.getMonth() + months);

            document.getElementById('ctr_start').value = today.toISOString().split('T')[0];
            document.getElementById('ctr_end').value = endDate.toISOString().split('T')[0];

            const endDisplay = document.getElementById('end_date_display');
            if (endDisplay) {
                endDisplay.textContent = `${months} เดือนจากวันนี้`;
            }
        }
        
        // Calculate on page load
        calculateDates();

        // Recalculate when duration changes
        const durationSelect = document.getElementById('contract_duration');
        durationSelect.addEventListener('change', calculateDates);
        
        // Form validation
        function validateForm() {
            const tntId = document.getElementById('tnt_id').value;
            const roomId = document.getElementById('room_id').value;
            
            if(!tntId) {
                alert('กรุณาเลือกผู้เช่า');
                return false;
            }
            if(!roomId) {
                alert('กรุณาเลือกห้องพัก');
                return false;
            }
            
            return true;
        }

        // Initialize DataTable with better error handling
        document.addEventListener('DOMContentLoaded', function() {
            const tableElement = document.getElementById("table-contracts");
            console.log('Table element found:', tableElement);
            console.log('Table rows:', tableElement ? tableElement.querySelectorAll('tbody tr').length : 'N/A');
            
            if(tableElement && tableElement.querySelectorAll('tbody tr').length > 0) {
                try {
                    const dataTable = new simpleDatatables.DataTable("#table-contracts", {
                        searchable: true,
                        sortable: true,
                        perPageSelect: [10, 25, 50, 100],
                        perPage: 10,
                        labels: {
                            placeholder: "ค้นหา...",
                            perPage: "แสดง {pti} รายการต่อหน้า",
                            noRows: "ไม่พบข้อมูล",
                            info: "แสดง {start} ถึง {end} จาก {rows} รายการ",
                        }
                    });
                    console.log('DataTable initialized successfully');
                } catch(e) {
                    console.error('DataTable initialization error:', e);
                }
            } else {
                console.log('No data rows found or table element missing');
            }
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
