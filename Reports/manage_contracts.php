<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
  header('Location: ../Login.php');
  exit;
}

$error = '';

require_once __DIR__ . '/../ConnectDB.php';
require_once __DIR__ . '/../Manage/auto_cancel_expired_contracts.php';

// Initialize database connection
$conn = connectDB();

// ตรวจสอบและยกเลิกสัญญาที่หมดอายุอัตโนมัติ
$autoCanceledContracts = autoCancelExpiredContracts($conn);

// ดึง theme color จากการตั้งค่าระบบ
$settingsStmt = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'theme_color' LIMIT 1");
$themeColor = '#0f172a'; // ค่า default (dark mode)
if ($settingsStmt) {
    $theme = $settingsStmt->fetch(PDO::FETCH_ASSOC);
    if ($theme && !empty($theme['setting_value'])) {
        $themeColor = htmlspecialchars($theme['setting_value'], ENT_QUOTES, 'UTF-8');
    }
}

// Get contracts (optionally filter by ctr_id passed via querystring)
try {
    $filterCtrId = isset($_GET['ctr_id']) ? (int)$_GET['ctr_id'] : 0;
    if ($filterCtrId > 0) {
        $stmt = $conn->prepare("SELECT c.*, 
          t.tnt_id, t.tnt_name, t.tnt_phone, t.tnt_status,
          r.room_number, r.room_status,
          rt.type_name
          FROM contract c
          LEFT JOIN tenant t ON t.tnt_id = c.tnt_id
          LEFT JOIN room r ON c.room_id = r.room_id
          LEFT JOIN roomtype rt ON r.type_id = rt.type_id
          WHERE c.ctr_id = :ctr_id
          ORDER BY c.ctr_start DESC");
        $stmt->bindValue(':ctr_id', $filterCtrId, PDO::PARAM_INT);
        $stmt->execute();
    } else {
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
    }
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
    
    // ย้ายออก (tnt_status = 0) - เฉพาะที่มีสัญญา
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT t.tnt_id) as total FROM tenant t INNER JOIN contract c ON t.tnt_id = c.tnt_id WHERE t.tnt_status = 0");
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

// นับสถานะสำหรับการ์ดให้ตรงกับกติกาการแสดงผลในตาราง
$tableStatusCounts = [
  'waiting' => 0,
  'staying' => 0,
  'cancelled' => 0,
];
foreach ($contracts as $contract) {
  $s = isset($contract['ctr_status']) ? (string)$contract['ctr_status'] : '0';
  $tenantStatus = isset($contract['tnt_status']) ? (string)$contract['tnt_status'] : '';

  if ($s === '1') {
    $tableStatusCounts['cancelled']++;
  } elseif ($tenantStatus === '2' || $s === '2') {
    $tableStatusCounts['waiting']++;
  } else {
    $tableStatusCounts['staying']++;
  }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสัญญา</title>
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css">
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/animate-ui.css">
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/particle-effects.css">
    <script src="/dormitory_management/Public/Assets/Javascript/particle-effects.js"></script>
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/confirm-modal.css">
    <!-- DataTable Modern -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4/dist/style.css" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/datatable-modern.css" />
    <style>
      /* ============================================================
         MANAGE CONTRACTS - Responsive Styles (Clean Rewrite)
         ============================================================ */
      :root {
        --theme-bg-color: <?php echo $themeColor; ?>;
      }

      /* --- Base --- */
      html, body {
        max-width: 100vw;
        overflow-x: hidden;
      }
      body {
        background: var(--bg-primary);
        color: var(--text-primary);
      }
      main::-webkit-scrollbar { display: none; }

      /* --- Layout wrapper --- */
      .app-layout-wrapper {
        display: flex;
        max-width: 100vw;
        overflow: hidden;
      }
      .app-main-content {
        flex: 1;
        min-width: 0;
        overflow-x: hidden;
      }

      /* --- Header area --- */
      .contracts-header-wrap {
        margin: 1.5rem 1.5rem 1rem;
      }
      .contracts-header-wrap .page-header-bar { margin: 0; }

      /* --- Panel --- */
      .manage-panel {
        margin: 0 1.5rem 3rem;
        padding: 1.5rem;
        background: var(--card-bg);
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        overflow: hidden;
        box-sizing: border-box;
      }
      
      /* --- Statistics cards --- */
      h1 { margin: 0 0 1.5rem 0; color: var(--text-primary); }
      .contract-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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
      @media (prefers-color-scheme: light) {
        .contract-stat-card {
          background: linear-gradient(135deg, rgba(0,0,0,0.05) 0%, rgba(0,0,0,0.03) 100%) !important;
          border: 1px solid rgba(0,0,0,0.1) !important;
        }
        .contract-stat-card .stat-chip { background: rgba(0,0,0,0.08) !important; }
      }
      html.light-theme .contract-stat-card {
        background: linear-gradient(135deg, rgba(0,0,0,0.05) 0%, rgba(0,0,0,0.03) 100%) !important;
        border: 1px solid rgba(0,0,0,0.1) !important;
      }
      html.light-theme .contract-stat-card .stat-chip { background: rgba(0,0,0,0.08) !important; }

      /* --- Form toggle button --- */
      .form-toggle-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.65rem 1.3rem;
        background: #22c55e;
        color: #ffffff !important;
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
        white-space: nowrap;
      }
      .form-toggle-btn:hover {
        background: #16a34a;
        color: #ffffff !important;
        transform: translateY(-1px);
      }
      .form-toggle-btn, .form-toggle-btn * {
        color: #ffffff !important;
      }

      /* --- Add Contract Form --- */
      .contract-form {
        display: block;
        padding: 1.5rem;
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 6px;
        margin-bottom: 2rem;
        box-sizing: border-box;
      }
      .contract-form.hide { display: none; }
      .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
      }
      .form-group { display: flex; flex-direction: column; }
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
        width: 100%;
        box-sizing: border-box;
      }
      .form-group input:focus,
      .form-group select:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 4px rgba(255,255,255,0.1);
      }
      .form-group select option { background: #1e293b; color: #e2e8f0; }
      .form-group select option:checked { background: #334155; color: #e2e8f0; }
      @media (prefers-color-scheme: light) {
        .form-group input, .form-group select {
          background: #ffffff !important; color: #1f2937 !important; border: 1px solid #e5e7eb !important;
        }
        .form-group input::placeholder { color: #9ca3af !important; }
        .form-group label { color: #374151 !important; }
        .form-group select option { background: #ffffff !important; color: #1f2937 !important; }
        .form-group select option:checked { background: #e5e7eb !important; color: #1f2937 !important; }
      }
      html.light-theme .form-group input, html.light-theme .form-group select {
        background: #ffffff !important; color: #1f2937 !important; border: 1px solid #e5e7eb !important;
      }
      html.light-theme .form-group input::placeholder { color: #9ca3af !important; }
      html.light-theme .form-group label { color: #374151 !important; }
      html.light-theme .form-group select option { background: #ffffff !important; color: #1f2937 !important; }
      html.light-theme .form-group select option:checked { background: #e5e7eb !important; color: #1f2937 !important; }
      .form-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
      .form-actions button {
        padding: 0.6rem 1.2rem;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        min-width: 100px;
      }
      .btn-submit { background: #4CAF50; color: white; }
      .btn-submit:hover { background: #45a049; }
      .btn-cancel { background: rgba(255,255,255,0.1); color: var(--text-primary); }
      .btn-cancel:hover { background: rgba(255,255,255,0.15); }
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
      .quick-date-btn:hover { background: rgba(255,255,255,0.2); border-color: var(--primary-color); }

      /* --- Table (desktop) --- */
      .table-responsive-wrap {
        width: 100%;
        overflow-x: auto;
        box-sizing: border-box;
      }
      .report-table {
        width: 100%;
        border-collapse: collapse;
        color: #e2e8f0;
        opacity: 1 !important;
        visibility: visible !important;
      }
      .report-table thead { background: #0a1929; }
      .report-table tbody tr:hover { background: rgba(30,41,59,0.4); }
      .report-table th,
      .report-table td {
        padding: 0.75rem;
        text-align: left;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        color: #e2e8f0 !important;
        vertical-align: middle;
        opacity: 1 !important;
        visibility: visible !important;
      }
      .report-table th {
        background: rgba(10,25,41,0.8) !important;
        color: #cbd5e1 !important;
        font-weight: 600;
      }

      /* --- DataTable controls (all sizes) --- */
      .datatable-wrapper,
      .datatable-container {
        width: 100% !important;
        max-width: 100% !important;
        overflow: hidden !important;
        box-sizing: border-box !important;
      }
      .datatable-top {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 1rem;
        width: 100%;
        box-sizing: border-box;
      }
      .datatable-bottom {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        margin-top: 1rem;
        width: 100%;
        box-sizing: border-box;
      }
      .datatable-selector,
      .datatable-input {
        box-sizing: border-box;
        max-width: 100%;
      }

      /* --- Status badge --- */
      .status-badge {
        display: inline-block;
        padding: 0.35rem 0.75rem;
        border-radius: 4px;
        font-size: 0.82rem;
        font-weight: 500;
        text-align: center;
        white-space: nowrap;
      }

      /* --- Cancel button --- */
      .cancel-contract-btn {
        background: #f59e0b !important;
        border: 1px solid #d97706 !important;
        color: #ffffff !important;
        padding: 0.4rem 0.75rem !important;
        border-radius: 6px !important;
        font-size: 0.85rem !important;
        cursor: pointer !important;
        white-space: nowrap !important;
      }
      .cancel-contract-btn:hover { background: #d97706 !important; }

      /* =====================================================
         MOBILE RESPONSIVE  (≤ 768px)
         ===================================================== */
      @media (max-width: 768px) {
        /* Prevent ALL horizontal overflow */
        html, body { overflow-x: hidden !important; max-width: 100vw !important; }
        * { box-sizing: border-box; }

        /* Layout */
        main, .app-main {
          overflow-x: hidden !important;
          width: 100% !important;
          max-width: 100% !important;
        }

        /* Header */
        .contracts-header-wrap {
          margin: 0.75rem 0.5rem 0.5rem !important;
        }

        /* Panel */
        .manage-panel {
          margin: 0 0.5rem 1.5rem !important;
          padding: 0.75rem !important;
          border-radius: 6px !important;
        }

        /* Stats: single column on small phones */
        .contract-stats {
          grid-template-columns: 1fr !important;
          gap: 0.6rem !important;
          margin-bottom: 1rem !important;
        }
        .contract-stat-card { padding: 1rem !important; }
        .contract-stat-card .stat-value { font-size: 1.6rem !important; }

        /* Summary info bar */
        .info-bar {
          font-size: 0.82rem !important;
          word-break: break-word;
          line-height: 1.6;
        }

        /* Form toggle button */
        .form-toggle-btn {
          width: 100% !important;
          justify-content: center !important;
          white-space: normal !important;
        }

        /* Add Contract Form */
        .contract-form { padding: 0.875rem !important; }
        .form-grid { grid-template-columns: 1fr !important; }
        .form-actions { flex-direction: column !important; }
        .form-actions button { width: 100% !important; }

        /* DataTable controls: stack vertically */
        .datatable-top {
          flex-direction: column !important;
          align-items: stretch !important;
          gap: 0.5rem !important;
        }
        .datatable-dropdown,
        .datatable-search {
          width: 100% !important;
        }
        .datatable-dropdown label {
          display: flex !important;
          align-items: center !important;
          gap: 0.5rem !important;
          flex-wrap: wrap !important;
          width: 100% !important;
          font-size: 0.9rem !important;
        }
        .datatable-selector {
          flex: 1 !important;
          width: auto !important;
          min-width: 60px !important;
          padding: 0.45rem 0.5rem !important;
          font-size: 0.9rem !important;
        }
        .datatable-input {
          width: 100% !important;
          padding: 0.5rem 0.75rem !important;
          font-size: 0.9rem !important;
          box-sizing: border-box !important;
        }

        /* DataTable pagination: center & wrap */
        .datatable-bottom {
          flex-direction: column !important;
          align-items: center !important;
          gap: 0.5rem !important;
        }
        .datatable-info {
          font-size: 0.82rem !important;
          text-align: center !important;
          width: 100% !important;
        }
        .datatable-pagination {
          display: flex !important;
          flex-wrap: wrap !important;
          justify-content: center !important;
          gap: 0.2rem !important;
          width: 100% !important;
        }
        .datatable-pagination li { display: inline-block !important; }
        .datatable-pagination li a,
        .datatable-pagination li button {
          padding: 0.3rem 0.6rem !important;
          font-size: 0.85rem !important;
          min-width: 32px !important;
          text-align: center !important;
        }

        /* DataTable wrapper overflow guard */
        .datatable-wrapper,
        .datatable-container {
          width: 100% !important;
          max-width: 100% !important;
          overflow: hidden !important;
        }
        .datatable-table,
        .report-table,
        .report-table.table-responsive,
        .table-responsive-wrap {
          width: 100% !important;
          overflow-x: hidden !important;
        }

        /* ---- Table → Card layout ---- */
        #table-contracts { display: block !important; width: 100% !important; }
        #table-contracts thead { display: none !important; }
        #table-contracts tbody { display: block !important; width: 100% !important; }
        #table-contracts tbody tr {
          display: block !important;
          width: 100% !important;
          margin-bottom: 0.875rem !important;
          border: 1px solid rgba(255,255,255,0.12) !important;
          border-radius: 10px !important;
          overflow: hidden !important;
          background: rgba(255,255,255,0.03) !important;
        }
        #table-contracts tbody td {
          display: flex !important;
          flex-direction: column !important;
          align-items: flex-start !important;
          padding: 0.55rem 0.875rem !important;
          border-bottom: 1px solid rgba(255,255,255,0.06) !important;
          width: 100% !important;
          font-size: 0.9rem !important;
          word-break: break-word !important;
          overflow-wrap: break-word !important;
          min-height: unset !important;
          height: auto !important;
          justify-content: unset !important;
        }
        #table-contracts tbody td:last-child { border-bottom: none !important; }
        #table-contracts tbody td::before {
          content: attr(data-label) !important;
          display: block !important;
          font-size: 0.7rem !important;
          font-weight: 700 !important;
          text-transform: uppercase !important;
          letter-spacing: 0.6px !important;
          color: #64748b !important;
          margin-bottom: 0.2rem !important;
        }
        #table-contracts tbody td:first-child {
          padding-bottom: 0.75rem !important;
          border-bottom: 2px solid rgba(255,255,255,0.1) !important;
          font-weight: 700 !important;
          color: #e2e8f0 !important;
          font-size: 0.975rem !important;
        }
        #table-contracts tbody td:first-child::before { display: none !important; }
        #table-contracts tbody td.action-cell { align-items: flex-start !important; }
        #table-contracts tbody td.action-cell::before { display: none !important; }
      }

      /* =====================================================
         VERY SMALL PHONES  (≤ 400px)
         ===================================================== */
      @media (max-width: 400px) {
        .contract-stat-card .stat-value { font-size: 1.4rem !important; }
        .manage-panel { padding: 0.5rem !important; }
        #table-contracts tbody td { padding: 0.45rem 0.625rem !important; }
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
    <div style="display: flex; max-width: 100vw; overflow: hidden;">
        <?php include '../includes/sidebar.php'; ?>
        <main style="flex: 1; min-width: 0; overflow-x: hidden; overflow-y: auto; height: 100vh; scrollbar-width: none; -ms-overflow-style: none; padding-bottom: 4rem;">
            <div class="contracts-header-wrap">
              <?php $pageTitle = 'จัดการสัญญาเช่า'; include '../includes/page_header.php'; ?>
            </div>

            <div class="manage-panel">
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
                <?php if (!empty($autoCanceledContracts) && count($autoCanceledContracts) > 0): ?>
                  <div style="margin: 0.5rem 0 1rem; padding: 1rem 1.25rem; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; border-radius: 6px;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; font-weight: 600;">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;color:#dc2626;">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                      </svg>
                      ยกเลิกสัญญาอัตโนมัติ (<?php echo count($autoCanceledContracts); ?> รายการ)
                    </div>
                    <div style="font-size: 0.9rem; line-height: 1.6;">
                      <p style="margin: 0 0 0.5rem 0;">สัญญาต่อไปนี้ถูกยกเลิกอัตโนมัติเนื่องจากครบกำหนด:</p>
                      <ul style="margin: 0; padding-left: 1.5rem;">
                        <?php foreach($autoCanceledContracts as $contract): ?>
                          <li>สัญญาเลขที่ <strong><?php echo htmlspecialchars((string)$contract['ctr_id'], ENT_QUOTES, 'UTF-8'); ?></strong> -
                          ผู้เช่า: <?php echo htmlspecialchars($contract['tnt_name'], ENT_QUOTES, 'UTF-8'); ?>,
                          ห้อง: <?php echo htmlspecialchars($contract['room_number'], ENT_QUOTES, 'UTF-8'); ?>
                          (หมดอายุวันที่: <?php echo date('d/m/Y', strtotime($contract['ctr_end'])); ?>)</li>
                        <?php endforeach; ?>
                      </ul>
                    </div>
                  </div>
                <?php endif; ?>
                <div class="info-bar" style="margin: 0.5rem 0 1rem; padding: 0.5rem 0.75rem; background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.8); border: 1px solid rgba(255,255,255,0.08); border-radius: 6px; font-size: 0.95rem; word-break: break-word;">
                  สัญญาที่พบ: <strong><?php echo $ctrCount; ?></strong> รายการ |
                  ปกติ: <?php echo $ctrStatusBuckets['0']; ?> | ยกเลิกแล้ว: <?php echo $ctrStatusBuckets['1']; ?> | แจ้งยกเลิก: <?php echo $ctrStatusBuckets['2']; ?>
                </div>

                <!-- Statistics -->
                <div class="contract-stats">
                    <div class="contract-stat-card particle-wrapper">
                        <div class="particle-container" data-particles="3"></div>
                    <div class="stat-value" style="color: #FF9800;"><?php echo $tableStatusCounts['waiting']; ?></div>
                        <div class="stat-label">รอเข้าพัก</div>
                        <div class="stat-chip">
                            <span style="display: inline-block; width: 8px; height: 8px; background: #FF9800; border-radius: 50%;"></span>
                            รอการเข้าพัก
                        </div>
                    </div>
                    <div class="contract-stat-card particle-wrapper">
                        <div class="particle-container" data-particles="3"></div>
                    <div class="stat-value" style="color: #4CAF50;"><?php echo $tableStatusCounts['staying']; ?></div>
                        <div class="stat-label">กำลังพักอยู่</div>
                        <div class="stat-chip">
                            <span style="display: inline-block; width: 8px; height: 8px; background: #4CAF50; border-radius: 50%;"></span>
                            พักอยู่
                        </div>
                    </div>
                    <div class="contract-stat-card particle-wrapper">
                        <div class="particle-container" data-particles="3"></div>
                    <div class="stat-value" style="color: #f44336;"><?php echo $tableStatusCounts['cancelled']; ?></div>
                      <div class="stat-label">ยกเลิกแล้ว</div>
                        <div class="stat-chip">
                            <span style="display: inline-block; width: 8px; height: 8px; background: #f44336; border-radius: 50%;"></span>
                        ออก
                        </div>
                    </div>
                </div>

                <!-- Add Contract Form Toggle -->
                <button class="form-toggle-btn" id="toggleFormBtn" type="button" onclick="window.__toggleContractForm(event); return false;" style="color: #ffffff;">
                  <span id="toggleFormIcon" style="color: inherit;">▶</span>
                  <span id="toggleFormText" style="color: inherit;">แสดงฟอร์ม</span>
                </button>
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
                        เลือกเฉพาะผู้เช่าและห้องพัก - วันที่และเงินประกันจะถูกกำหนดอัตโนมัติ
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
                        <div class="form-group" style="grid-column: 1 / -1; padding: 1rem; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 4px;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;color:#3b82f6;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                <strong style="color: #3b82f6;">หมายเหตุ:</strong>
                            </div>
                            <ul style="margin: 0; padding-left: 1.5rem; color: rgba(255,255,255,0.8);">
                                <li>วันเริ่มสัญญา: <strong style="color: #3b82f6;">วันนี้ (<?php echo date('d/m/Y'); ?>)</strong></li>
                                <li>วันสิ้นสุดสัญญา: <strong style="color: #3b82f6;" id="end_date_display">6 เดือนจากวันนี้</strong></li>
                                <li>เงินประกัน: <strong style="color: #3b82f6;">2,000 บาท</strong></li>
                                <li><strong style="color: #f59e0b;">⚠️ ระบบจะยกเลิกสัญญาอัตโนมัติเมื่อครบกำหนด</strong></li>
                            </ul>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-submit" data-allow-submit>บันทึกสัญญา</button>
                        <button type="button" class="btn-cancel" onclick="window.__toggleContractForm && window.__toggleContractForm(event);">ยกเลิก</button>
                    </div>
                </form>

                <div style="width: 100%; box-sizing: border-box;">
                    <h3>รายชื่อสัญญา</h3>
                    <div class="info-bar" style="margin:0.25rem 0 0.75rem; padding:0.5rem 0.75rem; background: rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.08); border-radius:6px; color:rgba(255,255,255,0.9); word-break: break-word;">
                      สัญญาทั้งหมดที่แสดง: <strong><?php echo $ctrCount; ?></strong> รายการ (ทุกสถานะ)
                    </div>
                    <div class="table-responsive-wrap">
                    <table id="table-contracts" class="report-table" style="margin-bottom: 2rem; width: 100%; border-collapse: collapse;">
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

                                // แสดงสถานะ "รอเข้าพัก" จากฝั่งผู้เช่าในตาราง
                                $tenantStatus = isset($contract['tnt_status']) ? (string)$contract['tnt_status'] : '';
                                if ($tenantStatus === '2' && $s !== '1') {
                                  $lbl = 'รอเข้าพัก';
                                  $col = '#FF9800';
                                }
                                // Prepare cancellation date display for notify-cancel (status '2')
                                $cancelDateDisplay = '-';
                                if ($s === '2' && !empty($contract['ctr_end']) && $contract['ctr_end'] !== '0000-00-00') {
                                  $cancelDateDisplay = date('d/m/Y', strtotime($contract['ctr_end']));
                                }
                          ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                              <td style="padding: 0.75rem; color: #e2e8f0;" data-label="เลขที่สัญญา"><?php echo $ctr_id; ?></td>
                              <td style="padding: 0.75rem; color: #e2e8f0;" data-label="ผู้เช่า"><?php echo $tnt_name; ?></td>
                              <td style="padding: 0.75rem; color: #e2e8f0;" data-label="ห้องพัก"><?php echo $room_number; ?></td>
                              <td style="padding: 0.75rem; color: #e2e8f0;" data-label="วันเริ่มสัญญา"><?php echo $ctr_start; ?></td>
                              <td style="padding: 0.75rem; color: #e2e8f0;" data-label="วันสิ้นสุด"><?php echo $ctr_end; ?></td>
                              <td style="padding: 0.75rem;" data-label="สถานะ">
                                <span class="status-badge" style="background-color: <?php echo $col; ?>; color: white; padding: 0.4rem 0.8rem; border-radius: 4px; font-size: 0.85rem;">
                                  <?php echo $lbl; ?>
                                </span>
                                <?php if ($s === '2' && $cancelDateDisplay !== '-'): ?>
                                  <div style="margin-top:0.35rem;font-size:0.82rem;color:rgba(255,255,255,0.8);">วันที่จะยกเลิก: <?php echo $cancelDateDisplay; ?></div>
                                <?php endif; ?>
                              </td>
                              <td style="padding: 0.75rem; color: #e2e8f0;" data-label="จัดการ" class="action-cell">
                                <?php if ($s === '2'): ?>
                                  <button type="button" class="action-btn btn-warning cancel-contract-btn" data-ctrid="<?php echo $ctr_id; ?>">ยกเลิกทันที</button>
                                <?php else: ?>
                                  -
                                <?php endif; ?>
                              </td>
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

    <script src="/dormitory_management/Public/Assets/Javascript/animate-ui.js" defer></script>
    <script src="/dormitory_management/Public/Assets/Javascript/main.js" defer></script>
    <script>
      // Handle immediate cancel action from "แจ้งยกเลิก"
      async function sendCancelContract(ctrId) {
        try {
          const form = new FormData();
          form.append('ctr_id', ctrId);
          form.append('ctr_status', '1');

          const res = await fetch('../Manage/update_contract_status.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: form
          });
          const data = await res.json();
          return data;
        } catch (err) {
          console.error('Cancel contract error', err);
          return { success: false, error: 'ข้อผิดพลาดเครือข่าย' };
        }
      }

      function findRowFromBtn(btn) {
        return btn.closest('tr');
      }

      document.addEventListener('click', function(e) {
        const btn = e.target.closest('.cancel-contract-btn');
        if (!btn) return;
        e.preventDefault();
        const ctrId = btn.getAttribute('data-ctrid');
        if (!ctrId) return alert('ไม่พบรหัสสัญญา');

        // Immediate cancel without confirmation (ยกเลิกทันที)
        btn.disabled = true;
        btn.textContent = 'กำลังยกเลิก...';

        sendCancelContract(ctrId).then(function(resp) {
          if (resp && resp.success) {
            const row = findRowFromBtn(btn);
            if (row) {
              const badge = row.querySelector('.status-badge');
              if (badge) {
                badge.textContent = 'ยกเลิกแล้ว';
                badge.style.backgroundColor = '#f44336';
              }
              // optionally mark tenant/room status visually
            }
            btn.remove();
            alert(resp.message || 'ยกเลิกสัญญาเรียบร้อยแล้ว');
          } else {
            btn.disabled = false;
            btn.textContent = 'ยกเลิกสัญญา';
            alert((resp && resp.error) ? resp.error : 'ไม่สามารถยกเลิกสัญญาได้');
          }
        });
      });
    </script>
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
          // On mobile, let handleContractsTableResponsive() manage the layout
          if (window.innerWidth <= 768) return;

          const table = document.getElementById('table-contracts');
          if (!table) return;
          
          const tbody = table.querySelector('tbody');
          if (!tbody) return;

          const rows = tbody.querySelectorAll('tr');

          table.style.display = 'table';
          table.style.visibility = 'visible';
          table.style.opacity = '1';
          tbody.style.display = 'table-row-group';
          tbody.style.visibility = 'visible';
          tbody.style.opacity = '1';

          rows.forEach((row) => {
            row.style.display = 'table-row';
            row.style.visibility = 'visible';
            row.style.opacity = '1';
            row.style.color = '#e2e8f0';
          });
        };

        // run immediately and after DOM ready
        forceTableVisible();
        document.addEventListener('DOMContentLoaded', () => { forceTableVisible(); });
        setTimeout(() => { forceTableVisible(); }, 300);
        setTimeout(() => { forceTableVisible(); }, 800);

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
    
    <!-- DataTable Initialization -->
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4" defer></script>
    <script>
      /**
       * Apply or remove mobile card-layout styles on the contracts table.
       * Uses element.style.setProperty(prop, value, 'important') so that
       * !important actually works via the CSSOM (plain assignment ignores it).
       */
      function handleContractsTableResponsive() {
        const contractsTable = document.getElementById('table-contracts');
        if (!contractsTable) return;

        const isMobile = window.innerWidth <= 768;

        // Always keep datatable wrapper overflow hidden
        document.querySelectorAll('.datatable-wrapper, .datatable-container').forEach(el => {
          el.style.setProperty('overflow', 'hidden', 'important');
          el.style.setProperty('width', '100%', 'important');
          el.style.setProperty('max-width', '100%', 'important');
          el.style.setProperty('box-sizing', 'border-box', 'important');
        });

        if (isMobile) {
          // Table itself
          contractsTable.style.setProperty('display', 'block', 'important');
          contractsTable.style.setProperty('width', '100%', 'important');

          // Hide thead
          const thead = contractsTable.querySelector('thead');
          if (thead) thead.style.setProperty('display', 'none', 'important');

          // tbody
          const tbody = contractsTable.querySelector('tbody');
          if (tbody) {
            tbody.style.setProperty('display', 'block', 'important');
            tbody.style.setProperty('width', '100%', 'important');
          }

          // Rows → cards
          contractsTable.querySelectorAll('tbody tr').forEach(row => {
            row.style.setProperty('display', 'block', 'important');
            row.style.setProperty('width', '100%', 'important');
            row.style.setProperty('margin-bottom', '0.875rem', 'important');
            row.style.setProperty('border', '1px solid rgba(255,255,255,0.12)', 'important');
            row.style.setProperty('border-radius', '10px', 'important');
            row.style.setProperty('overflow', 'hidden', 'important');
            row.style.setProperty('background', 'rgba(255,255,255,0.03)', 'important');

            const cells = row.querySelectorAll('td');
            cells.forEach((cell, index) => {
              // Clear any conflicting DataTable inline styles
              cell.style.removeProperty('justify-content');
              cell.style.removeProperty('height');
              cell.style.removeProperty('min-height');

              cell.style.setProperty('display', 'flex', 'important');
              cell.style.setProperty('flex-direction', 'column', 'important');
              cell.style.setProperty('align-items', 'flex-start', 'important');
              cell.style.setProperty('justify-content', 'unset', 'important');
              cell.style.setProperty('padding', '0.55rem 0.875rem', 'important');
              cell.style.setProperty('border-bottom', '1px solid rgba(255,255,255,0.06)', 'important');
              cell.style.setProperty('width', '100%', 'important');
              cell.style.setProperty('box-sizing', 'border-box', 'important');
              cell.style.setProperty('font-size', '0.9rem', 'important');
              cell.style.setProperty('word-break', 'break-word', 'important');
              cell.style.setProperty('overflow-wrap', 'break-word', 'important');

              if (index === cells.length - 1) {
                cell.style.setProperty('border-bottom', 'none', 'important');
              }
              if (index === 0) {
                cell.style.setProperty('padding-bottom', '0.75rem', 'important');
                cell.style.setProperty('border-bottom', '2px solid rgba(255,255,255,0.1)', 'important');
                cell.style.setProperty('font-weight', '700', 'important');
                cell.style.setProperty('color', '#e2e8f0', 'important');
                cell.style.setProperty('font-size', '0.975rem', 'important');
              }
            });
          });
        } else {
          // Desktop: clear all inline overrides and let CSS handle it
          contractsTable.style.removeProperty('display');
          contractsTable.style.removeProperty('width');

          const thead = contractsTable.querySelector('thead');
          if (thead) thead.style.removeProperty('display');

          const tbody = contractsTable.querySelector('tbody');
          if (tbody) {
            tbody.style.removeProperty('display');
            tbody.style.removeProperty('width');
          }

          contractsTable.querySelectorAll('tbody tr').forEach(row => {
            ['display','width','margin-bottom','border','border-radius','overflow','background'].forEach(p => row.style.removeProperty(p));
            row.querySelectorAll('td').forEach(cell => {
              ['display','flex-direction','align-items','justify-content','padding','padding-bottom',
               'border-bottom','width','box-sizing','font-size','word-break','overflow-wrap',
               'font-weight','color'].forEach(p => cell.style.removeProperty(p));
            });
          });
        }
      }
      
      document.addEventListener('DOMContentLoaded', function() {
        const contractsTable = document.getElementById('table-contracts');
        if (contractsTable && typeof simpleDatatables !== 'undefined') {
          new simpleDatatables.DataTable(contractsTable, {
            searchable: true,
            fixedHeight: false,
            perPage: 10,
            perPageSelect: [5, 10, 25, 50, 100],
            labels: {
              placeholder: 'ค้นหาสัญญา...',
              perPage: 'รายการต่อหน้า',
              noRows: 'ไม่พบข้อมูลสัญญา',
              info: 'แสดง {start} ถึง {end} จาก {rows} รายการ'
            }
          });
        }
        // Apply responsive styles after DataTable finishes rendering
        setTimeout(handleContractsTableResponsive, 150);
        setTimeout(handleContractsTableResponsive, 400);
      });
      
      // Re-apply on resize
      let resizeTimer;
      window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(handleContractsTableResponsive, 200);
      });
      window.addEventListener('orientationchange', function() {
        setTimeout(handleContractsTableResponsive, 150);
      });
    </script>
</body>
</html>
