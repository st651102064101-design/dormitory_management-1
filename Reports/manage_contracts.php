<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
  header('Location: ../Login.php');
  exit;
}

$error = '';

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
    // ดึงทุกสถานะ (รวม null/อื่น ๆ) เพื่อให้เห็นข้อมูลแน่ ๆ
    $stmt = $conn->prepare("SELECT c.*, 
      t.tnt_id, t.tnt_name, t.tnt_phone, t.tnt_status,
      r.room_number, r.room_status,
      rt.type_name
      FROM contract c
      LEFT JOIN tenant t ON t.tnt_id = c.tnt_id
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

// Count contracts by status and tenants by status
$statusCounts = [
    '0' => 0,
    '1' => 0,
    '2' => 0
];

// นับจำนวนผู้เช่าตามสถานะ
// (0=ย้ายออก, 1=พักอยู่, 2=รอการเข้าพัก, 3=จองห้อง, 4=ยกเลิกจองห้อง)
$tenantStatusCounts = [
    '0' => 0,  // ผู้เช่ารอเข้าพัก (tnt_status = 2)
    '1' => 0,  // ผู้เช่าที่พักอยู่ (tnt_status = 1)
    '2' => 0   // ผู้เช่าที่ย้ายออก (tnt_status = 0)
];

foreach($contracts as $contract) {
    $status = $contract['ctr_status'] ?? '0';
    // Ensure status is a string key
    $status = (string)$status;
    if(isset($statusCounts[$status])) {
        $statusCounts[$status]++;
    }
}

// ดึงจำนวนผู้เช่าตามสถานะ
try {
    // รอเข้าพัก (tnt_status = 2)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tenant WHERE tnt_status = 2");
    $stmt->execute();
    $tenantStatusCounts['0'] = $stmt->fetch()['total'] ?? 0;
    
    // กำลังพักอยู่ (tnt_status = 1)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tenant WHERE tnt_status = 1");
    $stmt->execute();
    $tenantStatusCounts['1'] = $stmt->fetch()['total'] ?? 0;
    
    // ย้ายออก (tnt_status = 0)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tenant WHERE tnt_status = 0");
    $stmt->execute();
    $tenantStatusCounts['2'] = $stmt->fetch()['total'] ?? 0;
} catch(Exception $e) {
    error_log("ERROR: Tenant status count error: " . $e->getMessage());
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

// Debug counters to ensure data is actually loaded
$ctrCount = count($contracts);
$ctrStatusBuckets = ['0' => 0, '1' => 0, '2' => 0, 'other' => 0];
foreach ($contracts as $c) {
  $k = (string)($c['ctr_status'] ?? 'other');
  if (isset($ctrStatusBuckets[$k])) {
    $ctrStatusBuckets[$k]++;
  } else {
    $ctrStatusBuckets['other']++;
  }
}
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
        padding: 0.65rem 1.3rem;
        background: #22c55e;
        color: #0f172a;
        border: 1px solid #16a34a;
        border-radius: 8px;
        cursor: pointer;
        font-size: 1rem;
        margin-bottom: 1.5rem;
        transition: all 0.2s ease;
        box-shadow: 0 4px 12px rgba(34,197,94,0.35);
        position: relative;
        z-index: 9999;
        pointer-events: auto;
        touch-action: manipulation;
      }
      .form-toggle-btn:hover {
        background: #16a34a;
        color: #e2fbe9;
        transform: translateY(-1px);
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
        border-collapse: collapse !important;
        table-layout: auto !important;
        color: #e2e8f0 !important;
        margin-bottom: 2rem;
      }
      .report-table thead { 
        display: table-header-group !important; 
        background: #0a1929 !important;
      }
      .report-table tbody { 
        display: table-row-group !important; 
        max-height: none !important; 
        overflow: visible !important;
      }
      .report-table tr { 
        display: table-row !important; 
      }
      .report-table th,
      .report-table td {
        display: table-cell !important;
        padding: 0.75rem;
        text-align: left;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        color: #e2e8f0 !important;
        background: transparent !important;
        vertical-align: middle;
      }
      .report-table th {
        background: rgba(10,25,41,0.8) !important;
        color: #cbd5e1 !important;
        font-weight: 600;
      }
      .report-table tbody tr { 
        height: auto !important;
      }
      .report-table tbody tr:hover {
        background: rgba(30,41,59,0.4) !important;
      }
      /* force visible in case of rogue styles */
      .report-table, .report-table * {
        opacity: 1 !important;
        visibility: visible !important;
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
    <script>
      // Define a global sidebar toggle early so the header button responds immediately
      window.__directSidebarToggle = function(event) {
        if (event) {
          event.preventDefault();
          event.stopPropagation();
        }
        const sidebar = document.querySelector('.app-sidebar');
        if (!sidebar) return false;
        if (window.innerWidth <= 1024) {
          sidebar.classList.toggle('mobile-open');
          document.body.classList.toggle('sidebar-open');
        } else {
          sidebar.classList.toggle('collapsed');
        }
        return false;
      };

      // Define contract form toggle early
      window.__toggleContractForm = function(e) {
        if (e) {
          e.preventDefault();
          e.stopPropagation();
        }
        var form = document.getElementById('contractForm');
        var btn = document.getElementById('toggleFormBtn');
        var icon = document.getElementById('toggleFormIcon');
        var text = document.getElementById('toggleFormText');
        if (!form || !btn) {
          console.log('Form or button not found yet');
          return false;
        }
        var isHidden = form.classList.contains('hide');
        if (isHidden) {
          form.classList.remove('hide');
          icon.textContent = '▼';
          text.textContent = 'ซ่อนฟอร์ม';
          btn.classList.add('open');
        } else {
          form.classList.add('hide');
          icon.textContent = '▶';
          text.textContent = 'แสดงฟอร์ม';
          btn.classList.remove('open');
        }
        if (btn) {
          btn.setAttribute('aria-expanded', (!isHidden).toString());
        }
        try { 
          var newState = isHidden ? 'true' : 'false';
          localStorage.setItem('contractFormVisible', newState);
          console.log('contractFormVisible saved:', newState);
        } catch(ex) {
          console.error('localStorage error:', ex);
        }
        return false;
      };
    </script>
</head>
<body>
    <div style="display: flex;">
        <?php include '../includes/sidebar.php'; ?>
        <main style="flex: 1; overflow-y: auto; height: 100vh; scrollbar-width: none; -ms-overflow-style: none; padding-bottom: 4rem;">
            
            <div class="manage-panel">
              <?php include '../includes/page_header.php'; ?>

                <?php if (!empty($_SESSION['error'])): ?>
                  <div style="margin: 0.5rem 0 1rem; padding: 0.75rem 1rem; background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; border-radius: 6px;">
                    <?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?>
                  </div>
                <?php endif; ?>
                <?php if (!empty($_SESSION['success'])): ?>
                  <div style="margin: 0.5rem 0 1rem; padding: 0.75rem 1rem; background: #ecfdf3; color: #166534; border: 1px solid #bbf7d0; border-radius: 6px;">
                    <?php echo htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['success']); ?>
                  </div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                  <div style="margin: 0.5rem 0 1rem; padding: 0.75rem 1rem; background: #fef3c7; color: #92400e; border: 1px solid #fde68a; border-radius: 6px;">
                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                  </div>
                <?php endif; ?>
                <div style="margin: 0.5rem 0 1rem; padding: 0.5rem 0.75rem; background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.8); border: 1px solid rgba(255,255,255,0.08); border-radius: 6px; font-size: 0.95rem;">
                  สัญญาที่พบ: <strong><?php echo $ctrCount; ?></strong> รายการ (แสดงทุกสถานะ) |
                  ปกติ: <?php echo $ctrStatusBuckets['0']; ?> | ยกเลิกแล้ว: <?php echo $ctrStatusBuckets['1']; ?> | แจ้งยกเลิก: <?php echo $ctrStatusBuckets['2']; ?> | อื่นๆ: <?php echo $ctrStatusBuckets['other']; ?>
                </div>

                <!-- Statistics -->
                <div class="contract-stats">
                    <div class="contract-stat-card">
                        <div class="stat-value" style="color: #FF9800;"><?php echo $tenantStatusCounts['0']; ?></div>
                        <div class="stat-label">รอเข้าพัก</div>
                        <div class="stat-chip">
                            <span style="display: inline-block; width: 8px; height: 8px; background: #FF9800; border-radius: 50%;"></span>
                            รอการเข้าพัก
                        </div>
                    </div>
                    <div class="contract-stat-card">
                        <div class="stat-value" style="color: #4CAF50;"><?php echo $tenantStatusCounts['1']; ?></div>
                        <div class="stat-label">กำลังพักอยู่</div>
                        <div class="stat-chip">
                            <span style="display: inline-block; width: 8px; height: 8px; background: #4CAF50; border-radius: 50%;"></span>
                            พักอยู่
                        </div>
                    </div>
                    <div class="contract-stat-card">
                        <div class="stat-value" style="color: #f44336;"><?php echo $tenantStatusCounts['2']; ?></div>
                        <div class="stat-label">ย้ายออก</div>
                        <div class="stat-chip">
                            <span style="display: inline-block; width: 8px; height: 8px; background: #f44336; border-radius: 50%;"></span>
                            ออกไปแล้ว
                        </div>
                    </div>
                </div>

                <style>
                  .form-toggle-btn {
                    padding: 0.8rem 1.5rem;
                    cursor: pointer;
                    font-size: 1rem;
                    background: #1e293b;
                    border: 1px solid #334155;
                    color: #cbd5e1;
                    border-radius: 8px;
                    transition: all 0.2s;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    margin-bottom: 20px;
                    white-space: nowrap;
                  }
                  .form-toggle-btn:hover {
                    background: #334155;
                    border-color: #475569;
                  }
                  .form-toggle-btn.open {
                    border-color: #475569;
                  }
                </style>
                <!-- Add Contract Form Toggle -->
                <button class="form-toggle-btn" id="toggleFormBtn" type="button" onclick="window.__toggleContractForm(event); return false;"><span id="toggleFormIcon">▶</span> <span id="toggleFormText">แสดงฟอร์ม</span></button>
                <script>
                  // Bind click immediately after button is created
                  (function(){
                    var btn = document.getElementById('toggleFormBtn');
                    if (btn) {
                      btn.onclick = function(e) {
                        window.__toggleContractForm(e);
                        return false;
                      };
                    }
                  })();
                  
                  // Restore form state on DOMContentLoaded
                  document.addEventListener('DOMContentLoaded', function(){
                    setTimeout(function(){
                      var saved = localStorage.getItem('contractFormVisible');
                      var form = document.getElementById('contractForm');
                      var btn = document.getElementById('toggleFormBtn');
                      var icon = document.getElementById('toggleFormIcon');
                      var text = document.getElementById('toggleFormText');
                      console.log('DOMContentLoaded - Checking localStorage:', saved);
                      console.log('Form element exists:', !!form);
                      console.log('Button element exists:', !!btn);
                      
                      if (saved === 'true' && form && btn && icon && text) {
                        form.classList.remove('hide');
                        icon.textContent = '▼';
                        text.textContent = 'ซ่อนฟอร์ม';
                        btn.classList.add('open');
                        console.log('✓ Form opened from localStorage');
                      } else if (saved === 'false' && form && btn && icon && text) {
                        form.classList.add('hide');
                        icon.textContent = '▶';
                        text.textContent = 'แสดงฟอร์ม';
                        btn.classList.remove('open');
                        console.log('✓ Form closed from localStorage');
                      } else {
                        console.log('! Could not restore state - saved:', saved);
                      }
                    }, 100);
                  });
                </script>

                <!-- Add Contract Form -->
                <form class="contract-form hide" id="contractForm" action="../Manage/process_contract.php" method="POST" data-allow-submit>
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
                          <input type="date" id="ctr_end" name="ctr_end" value="<?php echo date('Y-m-d', strtotime('+6 months')); ?>" required>
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
                        <button type="submit" class="btn-submit" data-allow-submit>บันทึกสัญญา</button>
                        <button type="button" class="btn-cancel" onclick="window.__toggleContractForm && window.__toggleContractForm(event);">ยกเลิก</button>
                    </div>
                </form>

                <div style="display: block !important; width: 100%;">
                    <h3>รายชื่อสัญญา</h3>
                    <div style="margin:0.25rem 0 0.75rem; padding:0.5rem 0.75rem; background: rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.08); border-radius:6px; color:rgba(255,255,255,0.9);">
                      สัญญาทั้งหมดที่แสดง: <strong><?php echo $ctrCount; ?></strong> รายการ (ทุกสถานะ)
                    </div>
                    <div class="report-table">
                    <table id="table-contracts" style="margin-bottom: 2rem; width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="padding: 0.75rem; text-align: left; background: rgba(10,25,41,0.8); color: #cbd5e1; border-bottom: 1px solid rgba(255,255,255,0.1);">เลขที่สัญญา</th>
                                <th style="padding: 0.75rem; text-align: left; background: rgba(10,25,41,0.8); color: #cbd5e1; border-bottom: 1px solid rgba(255,255,255,0.1);">ผู้เช่า</th>
                                <th style="padding: 0.75rem; text-align: left; background: rgba(10,25,41,0.8); color: #cbd5e1; border-bottom: 1px solid rgba(255,255,255,0.1);">ห้องพัก</th>
                                <th style="padding: 0.75rem; text-align: left; background: rgba(10,25,41,0.8); color: #cbd5e1; border-bottom: 1px solid rgba(255,255,255,0.1);">วันเริ่มสัญญา</th>
                                <th style="padding: 0.75rem; text-align: left; background: rgba(10,25,41,0.8); color: #cbd5e1; border-bottom: 1px solid rgba(255,255,255,0.1);">วันสิ้นสุด</th>
                                <th style="padding: 0.75rem; text-align: left; background: rgba(10,25,41,0.8); color: #cbd5e1; border-bottom: 1px solid rgba(255,255,255,0.1);">สถานะ</th>
                                <th style="padding: 0.75rem; text-align: left; background: rgba(10,25,41,0.8); color: #cbd5e1; border-bottom: 1px solid rgba(255,255,255,0.1);">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                          <?php 
                            if (count($contracts) > 0) {
                              foreach($contracts as $contract) {
                                // Cast to string before htmlspecialchars to avoid PHP 8 type errors on ints
                                $ctr_id = isset($contract['ctr_id']) ? htmlspecialchars((string)$contract['ctr_id'], ENT_QUOTES, 'UTF-8') : 'N/A';
                                $tnt_name = isset($contract['tnt_name']) ? htmlspecialchars((string)$contract['tnt_name'], ENT_QUOTES, 'UTF-8') : 'N/A';
                                $room_number = isset($contract['room_number']) ? htmlspecialchars((string)$contract['room_number'], ENT_QUOTES, 'UTF-8') : 'N/A';
                                $ctr_start = (isset($contract['ctr_start']) && !empty($contract['ctr_start'])) ? date('d/m/Y', strtotime($contract['ctr_start'])) : '-';
                                $ctr_end = (isset($contract['ctr_end']) && !empty($contract['ctr_end'])) ? date('d/m/Y', strtotime($contract['ctr_end'])) : '-';
                                $s = isset($contract['ctr_status']) ? (string)$contract['ctr_status'] : '0';
                                $lbl = isset($statusLabels[$s]) ? $statusLabels[$s] : 'N/A';
                                $col = isset($statusColors[$s]) ? $statusColors[$s] : '#999';
                          ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                              <td style="padding: 0.75rem; color: #e2e8f0;"><?php echo $ctr_id; ?></td>
                              <td style="padding: 0.75rem; color: #e2e8f0;"><?php echo $tnt_name; ?></td>
                              <td style="padding: 0.75rem; color: #e2e8f0;"><?php echo $room_number; ?></td>
                              <td style="padding: 0.75rem; color: #e2e8f0;"><?php echo $ctr_start; ?></td>
                              <td style="padding: 0.75rem; color: #e2e8f0;"><?php echo $ctr_end; ?></td>
                              <td style="padding: 0.75rem;">
                                <span class="status-badge" style="background-color: <?php echo $col; ?>; color: white; padding: 0.4rem 0.8rem; border-radius: 4px; font-size: 0.85rem;">
                                  <?php echo $lbl; ?>
                                </span>
                              </td>
                              <td style="padding: 0.75rem; color: #e2e8f0;">-</td>
                            </tr>
                          <?php
                              }
                            } else {
                          ?>
                            <tr>
                              <td colspan="7" style="text-align:center; padding:1.25rem; color:#fbbf24; background: rgba(251,191,36,0.1); border: 1px dashed rgba(251,191,36,0.35);">ไม่มีข้อมูล</td>
                            </tr>
                          <?php } ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../Assets/Javascript/animate-ui.js" defer></script>
    <script src="../Assets/Javascript/main.js" defer></script>
    <script>
        // Fallback sidebar toggle (in case animate-ui.js fails on this page)
        document.addEventListener('DOMContentLoaded', function() {
          const sidebar = document.querySelector('.app-sidebar');
          const sidebarToggleBtn = document.getElementById('sidebar-toggle');
          if (!sidebar || !sidebarToggleBtn) return;
          const toggleSidebar = function(e) {
            if (e) {
              e.preventDefault();
              e.stopPropagation();
            }
            if (window.innerWidth <= 1024) {
              sidebar.classList.toggle('mobile-open');
              document.body.classList.toggle('sidebar-open');
            } else {
              sidebar.classList.toggle('collapsed');
            }
            return false;
          };
          sidebarToggleBtn.addEventListener('click', toggleSidebar);
          window.__directSidebarToggle = toggleSidebar;
        });

        // Ensure table rows are visible even if any CSS override hides them
        const forceTableVisible = () => {
          const table = document.getElementById('table-contracts');
          console.log('forceTableVisible called - table found:', !!table);
          if (!table) return;
          
          const tbody = table.querySelector('tbody');
          console.log('tbody found:', !!tbody);
          if (!tbody) return;

          const rows = tbody.querySelectorAll('tr');
          console.log('tbody rows count:', rows.length);

          table.style.display = 'table';
          table.style.visibility = 'visible';
          table.style.opacity = '1';
          tbody.style.display = 'table-row-group';
          tbody.style.visibility = 'visible';
          tbody.style.opacity = '1';

          rows.forEach((row, idx) => {
            row.style.display = 'table-row';
            row.style.visibility = 'visible';
            row.style.opacity = '1';
            row.style.color = '#e2e8f0';
            console.log('Row ' + idx + ' style applied');
          });
          console.log('Contract table rows (server rendered):', rows.length);
        };

        // run immediately and after DOM ready
        console.log('Running forceTableVisible immediately...');
        forceTableVisible();
        document.addEventListener('DOMContentLoaded', () => {
          console.log('DOMContentLoaded - running forceTableVisible');
          forceTableVisible();
        });
        setTimeout(() => {
          console.log('setTimeout 300ms - running forceTableVisible');
          forceTableVisible();
        }, 300);
        setTimeout(() => {
          console.log('setTimeout 800ms - running forceTableVisible');
          forceTableVisible();
        }, 800);

        // Auto-calculate dates
        function formatDateDisplay(dateObj) {
          const d = dateObj.getDate().toString().padStart(2, '0');
          const m = (dateObj.getMonth() + 1).toString().padStart(2, '0');
          const y = dateObj.getFullYear();
          return `${d}/${m}/${y}`;
        }

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
            endDisplay.textContent = `${months} เดือนจากวันนี้ (${formatDateDisplay(endDate)})`;
          }
        }
        
        // Calculate on page load
        calculateDates();

        // Recalculate when duration changes
        const durationSelect = document.getElementById('contract_duration');
        durationSelect.addEventListener('change', calculateDates);

        // Guard form submission: ensure dates/deposit set and prevent double submit
        const submitBtn = document.querySelector('#contractForm .btn-submit');
        document.getElementById('contractForm').addEventListener('submit', function(e) {
          if (!validateForm()) {
            e.preventDefault();
            if (submitBtn) {
              submitBtn.disabled = false;
              submitBtn.textContent = 'บันทึกสัญญา';
            }
            return;
          }

          calculateDates();
          const depositInput = document.getElementById('ctr_deposit');
          if (depositInput && !depositInput.value) {
            depositInput.value = '2000';
          }
          if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'กำลังบันทึก...';
          }
        });
        
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
