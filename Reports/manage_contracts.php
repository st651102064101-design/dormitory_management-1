<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
  header('Location: ../Login.php');
  exit;
}

$error = '';

require_once __DIR__ . '/../ConnectDB.php';
require_once __DIR__ . '/../includes/thai_date_helper.php';
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
$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'active';
$validStatuses = ['all', 'active', 'waiting', 'notifying', 'cancelled', 'expiring'];
if (!in_array($filterStatus, $validStatuses, true)) $filterStatus = 'all';

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
          ORDER BY CAST(r.room_number AS UNSIGNED) ASC, r.room_number ASC");
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
          ORDER BY CAST(r.room_number AS UNSIGNED) ASC, r.room_number ASC");
        $stmt->execute();
    }
    $allContracts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Compute per-status counts from full dataset
    $filterCounts = ['all' => count($allContracts), 'active' => 0, 'waiting' => 0, 'notifying' => 0, 'cancelled' => 0, 'expiring' => 0];
    foreach ($allContracts as $_c) {
        $_s   = (string)($_c['ctr_status'] ?? '0');
        $_tnt = (string)($_c['tnt_status'] ?? '');
        $_end = $_c['ctr_end'] ?? '';
        if ($_s === '0' && $_tnt !== '2') $filterCounts['active']++;
        if ($_s !== '1' && $_tnt === '2') $filterCounts['waiting']++;
        if ($_s === '2') $filterCounts['notifying']++;
        if ($_s === '1') $filterCounts['cancelled']++;
        if ($_s === '0' && !empty($_end) && $_end !== '0000-00-00') {
            $_days = (strtotime($_end) - time()) / 86400;
            if ($_days >= 0 && $_days <= 30) $filterCounts['expiring']++;
        }
    }

    // Apply filter
    if ($filterStatus !== 'all') {
        $allContracts = array_values(array_filter($allContracts, function($c) use ($filterStatus) {
            $s   = (string)($c['ctr_status'] ?? '0');
            $tnt = (string)($c['tnt_status'] ?? '');
            $end = $c['ctr_end'] ?? '';
            switch ($filterStatus) {
                case 'active':    return $s === '0' && $tnt !== '2';
                case 'waiting':   return $s !== '1' && $tnt === '2';
                case 'notifying': return $s === '2';
                case 'cancelled': return $s === '1';
                case 'expiring':
                    if ($s !== '0' || empty($end) || $end === '0000-00-00') return false;
                    $_d = (strtotime($end) - time()) / 86400;
                    return $_d >= 0 && $_d <= 30;
                default: return true;
            }
        }));
    }
    $contracts = $allContracts;
} catch(Exception $e) {
    $contracts = [];
    $filterCounts = ['all'=>0,'active'=>0,'waiting'=>0,'notifying'=>0,'cancelled'=>0,'expiring'=>0];
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
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/Logo.jpg">
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

      /* --- Status filter bar --- */
      .ctr-filter-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 1.25rem;
        align-items: center;
      }
      .ctr-filter-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.38rem 0.9rem;
        border-radius: 999px;
        font-size: 0.82rem;
        font-weight: 500;
        cursor: pointer;
        text-decoration: none;
        border: 1.5px solid transparent;
        transition: background 0.15s, border-color 0.15s, transform 0.1s;
        white-space: nowrap;
        background: rgba(255,255,255,0.06);
        color: rgba(226,232,240,0.75);
        border-color: rgba(255,255,255,0.1);
      }
      .ctr-filter-pill:hover {
        background: rgba(255,255,255,0.12);
        color: #f1f5f9;
      }
      .ctr-filter-pill.active {
        font-weight: 600;
        color: #fff;
        transform: translateY(-1px);
      }
      .ctr-filter-pill.pill-all.active       { background:#334155; border-color:#64748b; }
      .ctr-filter-pill.pill-active.active    { background:rgba(34,197,94,0.18); border-color:#22c55e; color:#4ade80; }
      .ctr-filter-pill.pill-waiting.active   { background:rgba(245,158,11,0.18); border-color:#f59e0b; color:#fbbf24; }
      .ctr-filter-pill.pill-notifying.active { background:rgba(251,146,60,0.18); border-color:#fb923c; color:#fdba74; }
      .ctr-filter-pill.pill-cancelled.active { background:rgba(239,68,68,0.18);  border-color:#ef4444; color:#fca5a5; }
      .ctr-filter-pill.pill-expiring.active  { background:rgba(234,179,8,0.18);  border-color:#eab308; color:#fde047; }
      .ctr-filter-pill .pill-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 700;
        background: rgba(255,255,255,0.12);
      }
      .ctr-filter-pill.active .pill-count { background: rgba(255,255,255,0.2); }
      html.light-theme .ctr-filter-pill {
        background: rgba(0,0,0,0.04);
        color: #475569;
        border-color: rgba(0,0,0,0.1);
      }
      html.light-theme .ctr-filter-pill:hover { background: rgba(0,0,0,0.08); color: #0f172a; }
      html.light-theme .ctr-filter-pill.pill-all.active       { background:#e2e8f0; border-color:#94a3b8; color:#0f172a; }
      html.light-theme .ctr-filter-pill.pill-active.active    { background:#dcfce7; border-color:#16a34a; color:#15803d; }
      html.light-theme .ctr-filter-pill.pill-waiting.active   { background:#fef3c7; border-color:#d97706; color:#92400e; }
      html.light-theme .ctr-filter-pill.pill-notifying.active { background:#ffedd5; border-color:#ea580c; color:#9a3412; }
      html.light-theme .ctr-filter-pill.pill-cancelled.active { background:#fee2e2; border-color:#dc2626; color:#991b1b; }
      html.light-theme .ctr-filter-pill.pill-expiring.active  { background:#fefce8; border-color:#ca8a04; color:#713f12; }
      @media (max-width: 768px) {
        .ctr-filter-bar { gap: 0.35rem; }
        .ctr-filter-pill { font-size: 0.78rem; padding: 0.3rem 0.7rem; }
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

      /* --- Delete button --- */
      .delete-contract-btn,
      .btn-danger {
        background: #ef4444 !important;
        border: 1px solid #dc2626 !important;
        color: #ffffff !important;
        padding: 0.4rem 0.75rem !important;
        border-radius: 6px !important;
        font-size: 0.85rem !important;
        cursor: pointer !important;
        white-space: nowrap !important;
      }
      .delete-contract-btn:hover,
      .btn-danger:hover { background: #dc2626 !important; }

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

      /* =====================================================
         CONTRACT DETAIL DRAWER
         ===================================================== */
      /* --- CSS variables (dark default) --- */
      #cdrDrawer {
        --cdr-bg:          #1e293b;
        --cdr-border:      rgba(255,255,255,0.10);
        --cdr-text-pri:    #f1f5f9;
        --cdr-text-body:   #e2e8f0;
        --cdr-text-muted:  #64748b;
        --cdr-text-dim:    #475569;
        --cdr-divider:     rgba(255,255,255,0.08);
        --cdr-card-bg:     rgba(255,255,255,0.025);
        --cdr-card-bdr:    rgba(255,255,255,0.09);
        --cdr-card-hover:  rgba(255,255,255,0.16);
        --cdr-close-bg:    rgba(255,255,255,0.07);
        --cdr-close-bdr:   rgba(255,255,255,0.10);
      }
      /* --- Light theme override --- */
      html.light-theme #cdrDrawer {
        --cdr-bg:          #ffffff;
        --cdr-border:      rgba(0,0,0,0.10);
        --cdr-text-pri:    #0f172a;
        --cdr-text-body:   #1e293b;
        --cdr-text-muted:  #64748b;
        --cdr-text-dim:    #94a3b8;
        --cdr-divider:     rgba(0,0,0,0.07);
        --cdr-card-bg:     rgba(0,0,0,0.025);
        --cdr-card-bdr:    rgba(0,0,0,0.09);
        --cdr-card-hover:  rgba(0,0,0,0.06);
        --cdr-close-bg:    rgba(0,0,0,0.05);
        --cdr-close-bdr:   rgba(0,0,0,0.12);
      }
      html.light-theme #cdrOverlay { background: rgba(0,0,0,0.35) !important; }

      #cdrDrawer * { box-sizing: border-box; }
      .cdr-tab {
        flex: 1; padding: 0.75rem 0.5rem;
        background: none; border: none; border-bottom: 3px solid transparent;
        color: var(--cdr-text-muted); cursor: pointer; font-size: 0.9rem;
        transition: color .18s, border-color .18s, background .18s;
      }
      .cdr-tab:hover:not(.active) { color: var(--cdr-text-body); background: rgba(255,255,255,0.04); }
      /* Per-tab active colors */
      .cdr-tab[data-tab="overview"].active  { color: #38bdf8; border-bottom-color: #38bdf8; font-weight: 600; background: rgba(56,189,248,0.06); }
      .cdr-tab[data-tab="billing"].active   { color: #4ade80; border-bottom-color: #4ade80; font-weight: 600; background: rgba(74,222,128,0.06); }
      .cdr-tab[data-tab="meter"].active     { color: #fbbf24; border-bottom-color: #fbbf24; font-weight: 600; background: rgba(251,191,36,0.06); }
      /* Hover hints per tab */
      .cdr-tab[data-tab="overview"]:not(.active):hover { color: #7dd3fc; }
      .cdr-tab[data-tab="billing"]:not(.active):hover  { color: #86efac; }
      .cdr-tab[data-tab="meter"]:not(.active):hover    { color: #fde68a; }
      /* Light theme */
      html.light-theme .cdr-tab[data-tab="overview"].active { color: #0284c7; border-bottom-color: #0284c7; background: rgba(2,132,199,0.06); }
      html.light-theme .cdr-tab[data-tab="billing"].active  { color: #16a34a; border-bottom-color: #16a34a; background: rgba(22,163,74,0.06); }
      html.light-theme .cdr-tab[data-tab="meter"].active    { color: #d97706; border-bottom-color: #d97706; background: rgba(217,119,6,0.06); }
      html.light-theme .cdr-tab:hover:not(.active) { background: rgba(0,0,0,0.03); }
      .cdr-section { margin-bottom: 1.5rem; }
      .cdr-section-title {
        font-size: 0.72rem; font-weight: 700; letter-spacing: .06em;
        color: var(--cdr-text-muted); text-transform: uppercase; margin-bottom: 0.65rem;
        padding-bottom: 0.4rem; border-bottom: 1px solid var(--cdr-divider);
      }
      .cdr-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.65rem 1.25rem; }
      .cdr-field label {
        font-size: 0.75rem; color: var(--cdr-text-muted); display: block; margin-bottom: 0.1rem;
      }
      .cdr-field span { font-size: 0.9rem; color: var(--cdr-text-body); font-weight: 500; }
      .cdr-bill-card {
        border: 1px solid var(--cdr-card-bdr);
        border-radius: 10px; padding: 1rem 1.1rem;
        margin-bottom: 0.85rem;
        background: var(--cdr-card-bg);
        transition: border-color .2s;
      }
      .cdr-bill-card:hover { border-color: var(--cdr-card-hover); }
      .cdr-pay-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 0.45rem 0; border-top: 1px solid var(--cdr-divider);
        font-size: 0.83rem;
      }
      .cdr-meter-table { width: 100%; border-collapse: collapse; font-size: 0.83rem; }
      .cdr-meter-table th {
        text-align: left; color: var(--cdr-text-muted); font-weight: 600;
        padding: 0.4rem 0.55rem; border-bottom: 1px solid var(--cdr-border);
      }
      .cdr-meter-table td {
        padding: 0.45rem 0.55rem;
        border-top: 1px solid var(--cdr-divider);
        color: var(--cdr-text-body);
      }
      .cdr-meta-cell {
        flex: 1; padding: 0.7rem 1rem;
        border-right: 1px solid var(--cdr-divider);
      }
      .cdr-meta-cell:last-child { border-right: none; }
      .cdr-meta-cell .cdr-meta-label {
        font-size: 0.7rem; color: var(--cdr-text-muted); margin-bottom: 0.15rem;
      }
      .cdr-meta-cell .cdr-meta-val {
        font-size: 0.92rem; color: var(--cdr-text-body); font-weight: 600;
      }
      #table-contracts tbody tr.cdr-clickable {
        cursor: pointer;
        transition: background .15s;
      }
      #table-contracts tbody tr.cdr-clickable:hover { background: rgba(56,189,248,0.07) !important; }
      @media (max-width: 640px) {
        #cdrDrawer { width: 100% !important; }
        .cdr-grid { grid-template-columns: 1fr !important; }
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
                          (หมดอายุวันที่: <?php echo thaiDate($contract['ctr_end']); ?>)</li>
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
                                <li>วันเริ่มสัญญา: <strong style="color: #3b82f6;">วันนี้ (<?php echo thaiDate(date('Y-m-d')); ?>)</strong></li>
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

                <div id="contractsTableArea" style="width: 100%; box-sizing: border-box;">
                    <h3>รายชื่อสัญญา</h3>

                    <!-- Status filter pills -->
                    <?php
                        $baseHref = 'manage_contracts.php' . ($filterCtrId > 0 ? '?ctr_id=' . $filterCtrId . '&status=' : '?status=');
                        $pills = [
                            ['key'=>'all',        'label'=>'ทั้งหมด',      'class'=>'pill-all',        'icon'=>'📋'],
                            ['key'=>'active',     'label'=>'ทำสัญญาอยู่', 'class'=>'pill-active',     'icon'=>'✅'],
                            ['key'=>'waiting',    'label'=>'รอเข้าพัก',   'class'=>'pill-waiting',    'icon'=>'⏳'],
                            ['key'=>'notifying',  'label'=>'แจ้งยกเลิก',  'class'=>'pill-notifying',  'icon'=>'📢'],
                            ['key'=>'cancelled',  'label'=>'ยกเลิกแล้ว',  'class'=>'pill-cancelled',  'icon'=>'❌'],
                            ['key'=>'expiring',   'label'=>'ใกล้หมดสัญญา','class'=>'pill-expiring',   'icon'=>'⏰'],
                        ];
                    ?>
                    <div class="ctr-filter-bar">
                        <?php foreach ($pills as $pill): ?>
                            <a href="<?php echo htmlspecialchars($baseHref . $pill['key'], ENT_QUOTES, 'UTF-8'); ?>" data-filter="<?php echo htmlspecialchars($pill['key'], ENT_QUOTES, 'UTF-8'); ?>" class="ctr-filter-pill <?php echo $pill['class']; ?><?php echo $filterStatus === $pill['key'] ? ' active' : ''; ?>" onclick="filterContracts(event, this)">
                                <?php echo $pill['icon']; ?> <?php echo $pill['label']; ?>
                                <span class="pill-count"><?php echo $filterCounts[$pill['key']] ?? 0; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <div class="info-bar" style="margin:0.25rem 0 0.75rem; padding:0.5rem 0.75rem; background: rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.08); border-radius:6px; color:rgba(255,255,255,0.9); word-break: break-word;">
                      แสดง: <strong><?php echo count($contracts); ?></strong> รายการ
                      <?php if ($filterStatus !== 'all'): ?>
                        <span style="opacity:0.6;">· จากทั้งหมด <?php echo $filterCounts['all']; ?> รายการ</span>
                      <?php endif; ?>
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
                                $ctr_start = (isset($contract['ctr_start']) && !empty($contract['ctr_start'])) ? thaiDate($contract['ctr_start']) : '-';
                                $ctr_end = (isset($contract['ctr_end']) && !empty($contract['ctr_end'])) ? thaiDate($contract['ctr_end']) : '-';
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
                                  $cancelDateDisplay = thaiDate($contract['ctr_end']);
                                }
                          ?>
                            <tr class="cdr-clickable" style="border-bottom: 1px solid rgba(255,255,255,0.1);" onclick="if(!event.target.closest('[data-ctrid]'))openContractDetail(<?php echo $ctr_id; ?>)">
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
                                <?php elseif (in_array($s, ['0', ''])): ?>
                                  <button type="button" class="action-btn btn-danger cancel-contract-btn" data-ctrid="<?php echo $ctr_id; ?>">ยกเลิกสัญญา</button>
                                <?php else: ?>
                                  <span style="color:#475569;font-size:0.82rem;">คลิกเพื่อดูรายละเอียด</span>
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

    <!-- Toast container -->
    <div id="ctrToast" style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:0.5rem;pointer-events:none;"></div>

    <script>
    /* =====================================================
       AJAX UTILITIES
       ===================================================== */
    let _ctrCurrentFilter = '<?php echo htmlspecialchars($filterStatus, ENT_QUOTES, 'UTF-8'); ?>';

    function showCtrToast(msg, type = 'success') {
      const colors = { success: '#22c55e', error: '#ef4444', info: '#38bdf8' };
      const el = document.createElement('div');
      el.style.cssText = `padding:0.65rem 1.1rem;border-radius:8px;font-size:0.9rem;font-weight:500;
        background:${colors[type] || colors.success};color:#fff;box-shadow:0 4px 16px rgba(0,0,0,0.25);
        opacity:0;transition:opacity 0.25s;max-width:320px;word-break:break-word;pointer-events:auto;`;
      el.textContent = msg;
      document.getElementById('ctrToast').appendChild(el);
      requestAnimationFrame(() => { el.style.opacity = '1'; });
      setTimeout(() => {
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 300);
      }, 3500);
    }

    function refreshContractsTable(filterKey) {
      if (filterKey !== undefined) _ctrCurrentFilter = filterKey;
      const area = document.getElementById('contractsTableArea');
      if (!area) { location.reload(); return; }
      const sep = location.href.includes('?') ? '&' : '?';
      // Build URL preserving ctr_id if present, replace/add status
      let baseUrl = location.href.split('?')[0];
      const params = new URLSearchParams(location.search);
      params.set('status', _ctrCurrentFilter);
      params.set('_t', Date.now());
      const freshUrl = baseUrl + '?' + params.toString();

      // Update URL bar without reload
      const cleanParams = new URLSearchParams();
      cleanParams.set('status', _ctrCurrentFilter);
      if (params.get('ctr_id')) cleanParams.set('ctr_id', params.get('ctr_id'));
      history.replaceState(null, '', location.pathname + '?' + cleanParams.toString());

      fetch(freshUrl, { credentials: 'same-origin' })
        .then(r => r.text())
        .then(html => {
          const parser = new DOMParser();
          const doc = parser.parseFromString(html, 'text/html');
          const newArea = doc.getElementById('contractsTableArea');
          if (newArea) {
            area.innerHTML = newArea.innerHTML;
            // Re-bind filter pill clicks on new markup
            area.querySelectorAll('.ctr-filter-pill[data-filter]').forEach(pill => {
              pill.addEventListener('click', function(e) { filterContracts(e, this); });
            });
            // Re-init DataTable
            initContractsDataTable();
            setTimeout(handleContractsTableResponsive, 150);
          }
          // Also refresh stat cards
          const newStats = doc.querySelector('.contract-stats');
          const curStats = document.querySelector('.contract-stats');
          if (newStats && curStats) curStats.outerHTML = newStats.outerHTML;
          // Refresh info bar outside table area
          const newInfoBar = doc.querySelector('.info-bar');
          const curInfoBar = document.querySelector('.info-bar');
          if (newInfoBar && curInfoBar) curInfoBar.outerHTML = newInfoBar.outerHTML;
        })
        .catch(() => location.reload());
    }

    /* Filter pills — AJAX instead of navigation */
    function filterContracts(e, pill) {
      e.preventDefault();
      const key = pill.getAttribute('data-filter');
      if (!key) return;
      refreshContractsTable(key);
    }

    /* Add-contract form — AJAX submit */
    document.addEventListener('submit', function(e) {
      const form = e.target.closest('#contractForm');
      if (!form) return;
      e.preventDefault();

      if (!validateForm()) return;
      calculateDates();
      const depositInput = document.getElementById('ctr_deposit');
      if (depositInput && !depositInput.value) depositInput.value = '2000';

      const submitBtn = form.querySelector('.btn-submit');
      if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'กำลังบันทึก...'; }

      const fd = new FormData(form);
      fetch(form.action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd,
      })
        .then(r => r.json())
        .then(data => {
          if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'บันทึกสัญญา'; }
          if (data.success) {
            showCtrToast('✅ ' + (data.message || 'บันทึกสัญญาเรียบร้อยแล้ว'), 'success');
            form.reset();
            calculateDates();
            // collapse form
            if (window.__toggleContractForm) {
              const formEl = document.getElementById('contractForm');
              if (formEl && !formEl.classList.contains('hide')) window.__toggleContractForm();
            }
            refreshContractsTable();
          } else {
            showCtrToast('❌ ' + (data.error || 'ไม่สามารถบันทึกสัญญาได้'), 'error');
          }
        })
        .catch(() => {
          if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'บันทึกสัญญา'; }
          showCtrToast('❌ ข้อผิดพลาดเครือข่าย', 'error');
        });
    }, true);
    </script>

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

      /* ---- Refund API calls ---- */
      async function _saveRefund(ctrId) {
        var dedAmt = document.getElementById('rfDeductAmt');
        var dedReason = document.getElementById('rfDeductReason');
        var fd = new FormData();
        fd.append('action', 'create');
        fd.append('ctr_id', ctrId);
        fd.append('deduction_amount', dedAmt ? dedAmt.value : '0');
        fd.append('deduction_reason', dedReason ? dedReason.value : '');
        try {
          var res = await fetch('../Manage/process_deposit_refund.php', {
            method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'}, body: fd
          });
          var data = await res.json();
          if (data.success) {
            showCtrToast('✅ ' + data.message, 'success');
            openContractDetail(ctrId);
          } else {
            showCtrToast('❌ ' + data.error, 'error');
          }
        } catch(e) { showCtrToast('❌ ข้อผิดพลาดเครือข่าย', 'error'); }
      }

      async function _uploadRefundProof(ctrId) {
        var fileInput = document.getElementById('rfProofFile');
        if (!fileInput || !fileInput.files.length) {
          showCtrToast('กรุณาเลือกไฟล์', 'error'); return;
        }
        var fd = new FormData();
        fd.append('action', 'upload');
        fd.append('ctr_id', ctrId);
        fd.append('refund_proof', fileInput.files[0]);
        try {
          var res = await fetch('../Manage/process_deposit_refund.php', {
            method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'}, body: fd
          });
          var data = await res.json();
          if (data.success) {
            showCtrToast('✅ ' + data.message, 'success');
            openContractDetail(ctrId);
          } else {
            showCtrToast('❌ ' + data.error, 'error');
          }
        } catch(e) { showCtrToast('❌ ข้อผิดพลาดเครือข่าย', 'error'); }
      }

      async function _confirmRefund(ctrId) {
        var ok = await appleConfirm('ยืนยันว่าโอนคืนเงินมัดจำเรียบร้อยแล้ว?', 'ยืนยันการคืนเงินมัดจำ');
        if (!ok) return;
        var fd = new FormData();
        fd.append('action', 'confirm');
        fd.append('ctr_id', ctrId);
        try {
          var res = await fetch('../Manage/process_deposit_refund.php', {
            method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'}, body: fd
          });
          var data = await res.json();
          if (data.success) {
            showCtrToast('✅ ' + data.message, 'success');
            openContractDetail(ctrId);
            refreshContractsTable();
            try { localStorage.setItem('dataChanged', JSON.stringify({type:'refundCompleted',ctrId:ctrId,ts:Date.now()})); } catch(ex) {}
          } else {
            showCtrToast('❌ ' + data.error, 'error');
          }
        } catch(e) { showCtrToast('❌ ข้อผิดพลาดเครือข่าย', 'error'); }
      }

      document.addEventListener('click', function(e) {
        const btn = e.target.closest('.cancel-contract-btn');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        const ctrId = btn.getAttribute('data-ctrid');
        if (!ctrId) return showCtrToast('ไม่พบรหัสสัญญา', 'error');

        const origText = btn.textContent;
        appleConfirm('คุณแน่ใจหรือว่าต้องการยกเลิกสัญญานี้?', 'ยืนยันการยกเลิกสัญญา').then(function(confirmed) {
          if (!confirmed) return;

          btn.disabled = true;
          btn.textContent = 'กำลังยกเลิก...';

          sendCancelContract(ctrId).then(function(resp) {
            if (resp && resp.success) {
              showCtrToast('✅ ' + (resp.message || 'ยกเลิกสัญญาเรียบร้อยแล้ว'), 'success');
              refreshContractsTable();
              // แจ้งแท็บอื่นให้ refresh ด้วย
              try { localStorage.setItem('dataChanged', JSON.stringify({type:'contractCancelled',ctrId:ctrId,ts:Date.now()})); } catch(ex) {}
            } else {
              btn.disabled = false;
              btn.textContent = origText;
              // If deposit refund is needed, open the drawer to show the refund section
              if (resp && resp.need_refund) {
                showCtrToast('❌ ' + resp.error, 'error');
                setTimeout(function(){ openContractDetail(ctrId); }, 400);
              } else {
                showCtrToast('❌ ' + ((resp && resp.error) ? resp.error : 'ไม่สามารถยกเลิกสัญญาได้'), 'error');
              }
            }
          });
        });
      });

      // Delegated input handler for deposit deduction calculator (rfDeductAmt)
      document.addEventListener('input', function(e) {
        if (e.target && e.target.id === 'rfDeductAmt') {
          var maxAmt = parseFloat(e.target.getAttribute('max')) || 0;
          var ded = Math.max(0, Math.min(maxAmt, parseInt(e.target.value)||0));
          var refund = maxAmt - ded;
          var el = document.getElementById('rfRefundDisplay');
          if (el) el.textContent = refund.toLocaleString('th-TH') + ' ฿';
        }
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

        // Form validation (used by AJAX submit handler above)
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
      
      let _dtInstance = null;

      function initContractsDataTable() {
        // Destroy previous instance if any
        if (_dtInstance) {
          try { _dtInstance.destroy(); } catch(e) {}
          _dtInstance = null;
        }
        const contractsTable = document.getElementById('table-contracts');
        if (contractsTable && typeof simpleDatatables !== 'undefined') {
          _dtInstance = new simpleDatatables.DataTable(contractsTable, {
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
      }

      document.addEventListener('DOMContentLoaded', function() {
        initContractsDataTable();
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

    <!-- ========= CONTRACT DETAIL DRAWER ========= -->
    <div id="cdrOverlay"
         onclick="closeContractDetail()"
         style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1040;"></div>

    <div id="cdrDrawer"
         style="display:none;position:fixed;top:0;right:0;height:100%;width:min(600px,100vw);
                background:var(--cdr-bg);border-left:1px solid var(--cdr-border);
                z-index:1050;box-shadow:-6px 0 40px rgba(0,0,0,0.3);
                flex-direction:column;overflow:hidden;">

      <!-- Header -->
      <div style="padding:1.1rem 1.4rem;border-bottom:1px solid var(--cdr-border);
                  display:flex;align-items:flex-start;justify-content:space-between;
                  flex-shrink:0;gap:0.75rem;background:var(--cdr-bg);">
        <div style="min-width:0;">
          <div id="cdrTitle"
               style="font-size:1.1rem;font-weight:700;color:var(--cdr-text-pri);
                      white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">---</div>
          <div id="cdrSubtitle"
               style="font-size:0.82rem;color:var(--cdr-text-muted);margin-top:0.2rem;">---</div>
        </div>
        <div style="display:flex;align-items:center;gap:0.6rem;flex-shrink:0;">
          <span id="cdrStatusBadge"
                style="font-size:0.78rem;padding:0.25rem 0.65rem;border-radius:999px;
                       font-weight:600;white-space:nowrap;">---</span>
          <button onclick="closeContractDetail()"
                  style="background:var(--cdr-close-bg);border:1px solid var(--cdr-close-bdr);
                         color:var(--cdr-text-muted);width:30px;height:30px;border-radius:7px;
                         cursor:pointer;font-size:1rem;line-height:1;flex-shrink:0;"
                  title="ปิด">✕</button>
        </div>
      </div>

      <!-- Quick-stat strip -->
      <div id="cdrMeta"
           style="display:flex;border-bottom:1px solid var(--cdr-divider);flex-shrink:0;
                  background:var(--cdr-bg);"></div>

      <!-- Tabs -->
      <div style="display:flex;border-bottom:1px solid var(--cdr-border);flex-shrink:0;
                  background:var(--cdr-bg);">
        <button class="cdr-tab active" data-tab="overview" onclick="switchCdrTab('overview')">ภาพรวม</button>
        <button class="cdr-tab"        data-tab="billing"  onclick="switchCdrTab('billing')">ค่าใช้จ่าย</button>
        <button class="cdr-tab"        data-tab="meter"    onclick="switchCdrTab('meter')">มิเตอร์น้ำ/ไฟ</button>
      </div>

      <!-- Scrollable body -->
      <div id="cdrBody" style="overflow-y:auto;flex:1;padding:1.2rem 1.4rem;background:var(--cdr-bg);">
        <div id="cdrLoading" style="text-align:center;padding:3rem 0;color:var(--cdr-text-dim);">⏳ กำลังโหลด...</div>
        <div id="cdrTabOverview" class="cdr-tab-panel" style="display:none;"></div>
        <div id="cdrTabBilling"  class="cdr-tab-panel" style="display:none;"></div>
        <div id="cdrTabMeter"    class="cdr-tab-panel" style="display:none;"></div>
      </div>
    </div>

    <script>
    /* ============================================================
       CONTRACT DETAIL DRAWER — JavaScript
       ============================================================ */
    let _cdrCurrentTab = 'overview';

    /* ---- Theme helper: returns color set for current theme ---- */
    function _getCdrTheme() {
      const light = document.documentElement.classList.contains('light-theme');
      return {
        primary: light ? '#0f172a'              : '#f1f5f9',
        body:    light ? '#1e293b'              : '#e2e8f0',
        muted:   '#64748b',
        dim:     light ? '#94a3b8'              : '#475569',
        divider: light ? 'rgba(0,0,0,0.07)'    : 'rgba(255,255,255,0.08)',
        water:   light ? '#0284c7'             : '#38bdf8',
        elec:    light ? '#d97706'             : '#fbbf24',
        green:   light ? '#16a34a'             : '#22c55e',
        red:     light ? '#dc2626'             : '#f87171',
        link:    light ? '#0284c7'             : '#38bdf8',
      };
    }

    function openContractDetail(ctrId) {
      const drawer  = document.getElementById('cdrDrawer');
      const overlay = document.getElementById('cdrOverlay');
      const loading = document.getElementById('cdrLoading');

      // Reset
      document.querySelectorAll('.cdr-tab-panel').forEach(p => p.style.display = 'none');
      document.querySelectorAll('.cdr-tab').forEach(b => b.classList.toggle('active', b.dataset.tab === 'overview'));
      _cdrCurrentTab = 'overview';
      loading.style.display = 'block';

      overlay.style.display = 'block';
      drawer.style.display  = 'flex';

      fetch('../Manage/get_contract_detail.php?ctr_id=' + encodeURIComponent(ctrId), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(r => r.json())
        .then(data => {
          if (data.error) { loading.textContent = '⚠ ' + data.error; return; }
          loading.style.display = 'none';
          _renderCdrDrawer(data);
          switchCdrTab('overview');
        })
        .catch(() => { loading.textContent = '⚠ ไม่สามารถโหลดข้อมูลได้'; });
    }

    function closeContractDetail() {
      document.getElementById('cdrDrawer').style.display  = 'none';
      document.getElementById('cdrOverlay').style.display = 'none';
    }

    function switchCdrTab(tab) {
      _cdrCurrentTab = tab;
      document.querySelectorAll('.cdr-tab').forEach(b =>
        b.classList.toggle('active', b.dataset.tab === tab)
      );
      document.querySelectorAll('.cdr-tab-panel').forEach(p => p.style.display = 'none');
      const panel = document.getElementById('cdrTab' + tab.charAt(0).toUpperCase() + tab.slice(1));
      if (panel) panel.style.display = 'block';
    }

    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeContractDetail(); });

    /* ---- Helpers ---- */
    function _fmtDate(d) {
      if (!d || d === '0000-00-00' || d === '0000-00-00 00:00:00') return '-';
      const s = String(d).slice(0, 10).split('-');
      return s[2] + '/' + s[1] + '/' + s[0];
    }
    function _fmtMoney(n) {
      return Number(n || 0).toLocaleString('th-TH') + ' ฿';
    }
    function _ctrStatusInfo(s) {
      const map = {
        '0': { label: 'ปกติ',       color: '#22c55e' },
        '1': { label: 'ยกเลิกแล้ว', color: '#ef4444' },
        '2': { label: 'แจ้งยกเลิก', color: '#f59e0b' },
      };
      return map[s] || { label: s, color: '#64748b' };
    }
    function _expStatusInfo(s) {
      const map = {
        '0': { label: 'รอชำระ',       color: '#fbbf24' },
        '1': { label: 'ชำระแล้ว',     color: '#22c55e' },
        '2': { label: 'รอจดมิเตอร์', color: '#94a3b8' },
        '3': { label: 'ค้างชำระ',     color: '#f87171' },
        '4': { label: 'ค้างชำระ',     color: '#f87171' },
      };
      return map[s] || { label: s, color: '#94a3b8' };
    }
    function _payStatusLabel(s) {
      return { '0': 'รอตรวจสอบ', '1': '✓ อนุมัติ', '2': 'ปฎิเสธ' }[s] || s;
    }
    function _metaCell(label, val) {
      return `<div class="cdr-meta-cell">
        <div class="cdr-meta-label">${label}</div>
        <div class="cdr-meta-val">${val}</div>
      </div>`;
    }
    function _field(label, val) {
      return `<div class="cdr-field"><label>${label}</label><span>${val || '-'}</span></div>`;
    }

    /* ---- Deposit Refund Section ---- */
    function _renderRefundSection(data, t, c) {
      const rf = data.refund;
      const ctrId = c.ctr_id;
      const depAmount = data.deposit ? Number(data.deposit.bp_amount || 0) : (Number(c.ctr_deposit) || 0);

      if (rf && rf.refund_status === '1') {
        return `
          <div style="margin-top:1rem;padding:1rem;border-radius:10px;
                      background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.25);">
            <div style="font-weight:600;color:${t.green};margin-bottom:0.6rem;">✓ คืนเงินมัดจำแล้ว</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.4rem;font-size:0.85rem;">
              <div><span style="color:${t.muted};">เงินมัดจำ:</span> <span style="color:${t.body};">${_fmtMoney(rf.deposit_amount)}</span></div>
              <div><span style="color:${t.muted};">หักค่าเสียหาย:</span> <span style="color:${t.red};">${_fmtMoney(rf.deduction_amount)}</span></div>
              <div><span style="color:${t.muted};">ยอดคืน:</span> <span style="color:${t.green};font-weight:700;">${_fmtMoney(rf.refund_amount)}</span></div>
              <div><span style="color:${t.muted};">วันที่โอน:</span> <span style="color:${t.body};">${_fmtDate(rf.refund_date)}</span></div>
            </div>
            ${rf.deduction_reason ? '<div style="margin-top:0.4rem;font-size:0.82rem;color:'+t.muted+';">เหตุผล: '+rf.deduction_reason+'</div>' : ''}
            ${rf.refund_proof ? '<div style="margin-top:0.4rem;"><a href="/'+rf.refund_proof+'" target="_blank" style="font-size:0.82rem;color:'+t.link+';">📎 หลักฐานการโอน</a></div>' : ''}
          </div>`;
      }

      if (depAmount <= 0) return '';

      const dedAmt = rf ? rf.deduction_amount : 0;
      const dedReason = rf ? (rf.deduction_reason || '') : '';
      const rfProof = rf ? (rf.refund_proof || '') : '';

      // Bank account card from termination request
      const term = data.termination;
      const bankCard = term && term.bank_name
        ? `<div style="margin-bottom:0.8rem;padding:0.75rem;border-radius:10px;
                       background:rgba(59,130,246,0.08);border:1px solid rgba(59,130,246,0.2);">
             <div style="font-size:0.78rem;color:${t.muted};font-weight:600;margin-bottom:0.4rem;
                         text-transform:uppercase;letter-spacing:0.03em;">🏦 บัญชีรับเงินของผู้เช่า</div>
             <div style="font-size:0.88rem;color:${t.body};">${term.bank_name}</div>
             <div style="font-size:0.85rem;color:${t.muted};">${term.bank_account_name || '-'}</div>
             <div style="font-size:0.95rem;color:#60a5fa;font-weight:700;letter-spacing:0.06em;margin-top:0.15rem;">${term.bank_account_number || '-'}</div>
           </div>`
        : `<div style="margin-bottom:0.8rem;padding:0.6rem 0.75rem;border-radius:8px;
                       background:rgba(251,191,36,0.08);border:1px solid rgba(251,191,36,0.25);
                       font-size:0.82rem;color:#fbbf24;">
             ⚠️ ผู้เช่าไม่ได้ระบุบัญชีรับเงิน กรุณาติดต่อผู้เช่าโดยตรง
           </div>`;

      return `
        <div id="refundSection" style="margin-top:1rem;padding:1rem;border-radius:10px;
                    background:rgba(251,191,36,0.06);border:1px solid rgba(251,191,36,0.2);">
          <div style="font-weight:600;color:#fbbf24;margin-bottom:0.8rem;">💸 คืนเงินมัดจำ</div>
          <div style="display:grid;gap:0.6rem;">
            ${bankCard}
            <div style="font-size:0.85rem;">
              <span style="color:${t.muted};">เงินมัดจำ:</span>
              <span style="color:${t.body};font-weight:600;">${_fmtMoney(depAmount)}</span>
            </div>
            <div>
              <label style="display:block;font-size:0.8rem;color:${t.muted};margin-bottom:0.3rem;">หักค่าเสียหาย (บาท)</label>
              <input id="rfDeductAmt" type="number" min="0" max="${depAmount}" value="${dedAmt}"
                     style="width:100%;padding:0.5rem;border-radius:6px;border:1px solid ${t.divider};
                            background:rgba(0,0,0,0.15);color:${t.body};font-size:0.9rem;font-family:inherit;">
            </div>
            <div>
              <label style="display:block;font-size:0.8rem;color:${t.muted};margin-bottom:0.3rem;">เหตุผลการหัก (ถ้ามี)</label>
              <input id="rfDeductReason" type="text" value="${dedReason}" placeholder="เช่น ค่าซ่อมประตู, ค่าทำความสะอาด"
                     style="width:100%;padding:0.5rem;border-radius:6px;border:1px solid ${t.divider};
                            background:rgba(0,0,0,0.15);color:${t.body};font-size:0.9rem;font-family:inherit;">
            </div>
            <div id="rfCalcDisplay" style="font-size:0.9rem;padding:0.5rem;border-radius:6px;
                       background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.15);">
              <span style="color:${t.muted};">ยอดคืน:</span>
              <span id="rfRefundDisplay" style="color:${t.green};font-weight:700;">${_fmtMoney(depAmount - dedAmt)}</span>
            </div>
            <button onclick="_saveRefund(${ctrId})"
                    style="padding:0.6rem;border-radius:8px;border:none;cursor:pointer;font-family:inherit;
                           font-weight:600;font-size:0.88rem;color:#fff;
                           background:linear-gradient(135deg,#3b82f6,#2563eb);">
              💾 บันทึกข้อมูลคืนเงิน
            </button>
            ${rf ? '<div style="border-top:1px solid '+t.divider+';padding-top:0.6rem;margin-top:0.2rem;"><label style="display:block;font-size:0.8rem;color:'+t.muted+';margin-bottom:0.3rem;">หลักฐานการโอนคืน</label>'+(rfProof ? '<div style="margin-bottom:0.4rem;"><a href="/'+rfProof+'" target="_blank" style="font-size:0.82rem;color:'+t.link+';">📎 ดูหลักฐานปัจจุบัน</a></div>' : '')+'<input id="rfProofFile" type="file" accept="image/*,.pdf" style="font-size:0.82rem;color:'+t.muted+';"><button onclick="_uploadRefundProof('+ctrId+')" style="margin-top:0.4rem;padding:0.45rem 0.8rem;border-radius:6px;border:none;cursor:pointer;font-family:inherit;font-size:0.82rem;font-weight:500;color:#fff;background:#6366f1;">📤 อัพโหลด</button></div><button onclick="_confirmRefund('+ctrId+')" style="padding:0.6rem;border-radius:8px;border:none;cursor:pointer;font-family:inherit;font-weight:600;font-size:0.88rem;color:#fff;background:linear-gradient(135deg,#22c55e,#16a34a);">✅ ยืนยันโอนคืนเงินแล้ว</button>' : ''}
          </div>
        </div>`;
    }

    /* ---- Payment Gate: show unpaid warning ---- */
    function _renderPaymentGate(data, t, c) {
      // ไม่แสดงสำหรับสัญญาที่ยกเลิกแล้ว
      if (c.ctr_status === '1') return '';
      const ps = data.paymentSummary;
      if (!ps || parseInt(ps.unpaid_count) === 0) return '';
      return `
        <div class="cdr-section" style="border:1px solid rgba(239,68,68,0.3);background:rgba(239,68,68,0.05);border-radius:12px;padding:1rem;">
          <div style="color:${t.red};font-weight:600;font-size:0.95rem;margin-bottom:0.4rem;">
            ⚠️ ยังมีบิลค้างชำระ ${ps.unpaid_count} รายการ
          </div>
          <div style="font-size:0.85rem;color:${t.muted};">
            ยอดค้างชำระรวม: <span style="color:${t.red};font-weight:600;">${_fmtMoney(ps.total_outstanding)}</span>
          </div>
          <div style="margin-top:0.5rem;font-size:0.82rem;color:${t.dim};">
            ต้องชำระค่าห้องครบทุกเดือนก่อนจึงจะยกเลิกสัญญาได้
          </div>
        </div>`;
    }

    /* ---- Renderer ---- */
    function _renderCdrDrawer(data) {
      const c = data.contract;
      const t = _getCdrTheme();

      /* Header */
      document.getElementById('cdrTitle').textContent =
        'ห้อง ' + (c.room_number || '?') + ' — ' + (c.tnt_name || '-');
      document.getElementById('cdrSubtitle').textContent =
        'สัญญา #' + c.ctr_id + '  ·  ' + (c.type_name || '-') +
        '  ·  ' + _fmtMoney(c.type_price) + '/เดือน';

      const si = _ctrStatusInfo(c.ctr_status);
      const badge = document.getElementById('cdrStatusBadge');
      badge.textContent = si.label;
      badge.style.background  = si.color + '22';
      badge.style.color       = si.color;
      badge.style.border      = '1px solid ' + si.color + '55';

      /* Quick-stat strip */
      const monthsApart = (() => {
        if (!c.ctr_start || !c.ctr_end) return '-';
        const d1 = new Date(c.ctr_start), d2 = new Date(c.ctr_end);
        const m = Math.round((d2 - d1) / (1000 * 60 * 60 * 24 * 30.44));
        return m + ' เดือน';
      })();
      const totalPaid = (data.expenses || []).reduce((sum, e) =>
        sum + (e.payments || []).filter(p => p.pay_status === '1')
          .reduce((s2, p) => s2 + Number(p.pay_amount), 0), 0);

      document.getElementById('cdrMeta').innerHTML =
        _metaCell('เริ่มสัญญา', _fmtDate(c.ctr_start)) +
        _metaCell('สิ้นสุด',    _fmtDate(c.ctr_end)) +
        _metaCell('ระยะเวลา',   monthsApart) +
        _metaCell('ชำระรวม',    _fmtMoney(totalPaid));

      /* ===== TAB: ภาพรวม ===== */
      const dep = data.deposit;
      const ci  = data.checkin;

      document.getElementById('cdrTabOverview').innerHTML = `
        <div class="cdr-section">
          <div class="cdr-section-title">👤 ข้อมูลผู้เช่า</div>
          <div class="cdr-grid">
            ${_field('ชื่อ-นามสกุล', c.tnt_name)}
            ${_field('เบอร์โทร', c.tnt_phone)}
            ${_field('อายุ', c.tnt_age ? c.tnt_age + ' ปี' : '-')}
            ${_field('ยานพาหนะ', c.tnt_vehicle || '-')}
            ${_field('การศึกษา', c.tnt_education || '-')}
            ${_field('คณะ / สาขา', c.tnt_faculty || '-')}
            ${_field('ชั้นปี', c.tnt_year || '-')}
            ${_field('ผู้ติดต่อฉุกเฉิน', c.tnt_parent || '-')}
            ${_field('เบอร์ฉุกเฉิน', c.tnt_parentsphone || '-')}
          </div>
          ${c.tnt_address ? `<div class="cdr-field" style="margin-top:0.6rem;grid-column:1/-1">
            <label>ที่อยู่</label><span>${c.tnt_address}</span></div>` : ''}
        </div>

        <div class="cdr-section">
          <div class="cdr-section-title">🏠 ห้องพัก</div>
          <div class="cdr-grid">
            ${_field('ห้องเลขที่', c.room_number)}
            ${_field('ประเภทห้อง', c.type_name || '-')}
            ${_field('ค่าห้อง/เดือน', _fmtMoney(c.type_price))}
          </div>
        </div>

        <div class="cdr-section">
          <div class="cdr-section-title">📄 รายละเอียดสัญญา</div>
          <div class="cdr-grid">
            ${_field('เลขที่สัญญา', '#' + c.ctr_id)}
            ${_field('วันเริ่มสัญญา', _fmtDate(c.ctr_start))}
            ${_field('วันสิ้นสุด', _fmtDate(c.ctr_end))}
            ${_field('สถานะ', si.label)}
          </div>
          ${c.contract_pdf_path ? `
            <div style="margin-top:0.8rem;">
              <a href="/${c.contract_pdf_path}" target="_blank" rel="noopener"
                 style="display:inline-flex;align-items:center;gap:0.4rem;padding:0.4rem 0.9rem;
                        background:rgba(59,130,246,0.12);border:1px solid rgba(59,130,246,0.3);
                        color:${t.link};border-radius:8px;text-decoration:none;font-size:0.85rem;">
                📄 เปิดเอกสารสัญญา PDF
              </a>
            </div>` : `<div style="margin-top:0.5rem;font-size:0.8rem;color:${t.dim};">ไม่มีเอกสารสัญญา PDF ในระบบ</div>`}
        </div>

        <div class="cdr-section">
          <div class="cdr-section-title">💰 ค่ามัดจำ & คืนเงิน</div>
          <div class="cdr-grid">
            ${dep ? _field('ค่ามัดจำ', _fmtMoney(dep.bp_amount)) : _field('ค่ามัดจำ', '-')}
            ${dep ? _field('สถานะมัดจำ', dep.bp_status === '1' ? '✓ ยืนยันแล้ว' : 'รอยืนยัน') : _field('สถานะมัดจำ', '-')}
            ${ci  ? _field('วันเช็คอิน', _fmtDate(ci.checkin_date)) : _field('วันเช็คอิน', '-')}
            ${ci  ? _field('มิเตอร์น้ำเริ่มต้น', ci.water_meter_start || '0') : ''}
            ${ci  ? _field('มิเตอร์ไฟเริ่มต้น', ci.elec_meter_start || '0') : ''}
          </div>
          ${dep && dep.bp_proof ? `
            <div style="margin-top:0.6rem;">
              <a href="/dormitory_management/Public/Assets/Images/Payments/${dep.bp_proof}" target="_blank" rel="noopener"
                 style="font-size:0.82rem;color:${t.link};">📎 ดูหลักฐานการชำระมัดจำ</a>
            </div>` : ''}
          ${_renderRefundSection(data, t, c)}
        </div>
        ${_renderPaymentGate(data, t, c)}
      `;

      /* ===== TAB: ค่าใช้จ่าย ===== */
      const exps = data.expenses || [];
      if (exps.length === 0) {
        document.getElementById('cdrTabBilling').innerHTML =
          `<div style="text-align:center;padding:3rem;color:${t.dim};">ยังไม่มีรายการค่าใช้จ่าย</div>`;
      } else {
        document.getElementById('cdrTabBilling').innerHTML = exps.map(e => {
          const st  = _expStatusInfo(e.exp_status);
          const mth = e.exp_month
            ? (() => { const p = e.exp_month.slice(0,7).split('-'); return p[1]+'/'+p[0]; })()
            : '-';
          const paidAmt = (e.payments || []).filter(p => p.pay_status === '1')
            .reduce((s, p) => s + Number(p.pay_amount), 0);
          const remaining = Math.max(0, Number(e.exp_total) - paidAmt);

          return `
          <div class="cdr-bill-card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.8rem;">
              <div>
                <div style="font-size:0.75rem;color:${t.muted};">บิลเดือน</div>
                <div style="font-size:1rem;font-weight:700;color:${t.body};">${mth}</div>
              </div>
              <span style="font-size:0.76rem;padding:0.22rem 0.6rem;border-radius:999px;
                           background:${st.color}22;color:${st.color};border:1px solid ${st.color}44;">
                ${st.label}
              </span>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.4rem;
                        margin-bottom:0.75rem;font-size:0.8rem;">
              <div><div style="color:${t.muted};">ค่าห้อง</div>
                   <div style="color:${t.body};font-weight:600;">${_fmtMoney(e.room_price)}</div></div>
              <div><div style="color:${t.muted};">ค่าน้ำ&nbsp;(${e.exp_water_unit||0}&nbsp;หน่วย)</div>
                   <div style="color:${t.water};font-weight:600;">${_fmtMoney(e.exp_water)}</div></div>
              <div><div style="color:${t.muted};">ค่าไฟ&nbsp;(${e.exp_elec_unit||0}&nbsp;หน่วย)</div>
                   <div style="color:${t.elec};font-weight:600;">${_fmtMoney(e.exp_elec_chg)}</div></div>
            </div>

            <div style="display:flex;justify-content:space-between;font-weight:700;
                        padding:0.45rem 0;border-top:1px solid ${t.divider};
                        margin-bottom:${(e.payments||[]).length?'0.6rem':'0'};">
              <span style="color:${t.muted};font-size:0.9rem;">ยอดรวม</span>
              <span style="color:${t.primary};font-size:0.95rem;">${_fmtMoney(e.exp_total)}</span>
            </div>

            ${(e.payments||[]).length > 0 ? `
              <div style="font-size:0.72rem;color:${t.muted};margin-bottom:0.2rem;
                          text-transform:uppercase;letter-spacing:.04em;">การชำระเงิน</div>
              ${(e.payments||[]).map(p => `
                <div class="cdr-pay-row">
                  <div style="display:flex;flex-direction:column;gap:0.1rem;">
                    <span style="color:${p.pay_status==='1'?t.green:t.elec};
                                 font-weight:600;font-size:0.82rem;">${_payStatusLabel(p.pay_status)}</span>
                    <span style="color:${t.muted};font-size:0.77rem;">
                      ${_fmtDate(p.pay_date)}
                      ${p.pay_remark ? ' · ' + p.pay_remark : ''}
                    </span>
                  </div>
                  <div style="display:flex;align-items:center;gap:0.5rem;">
                    <span style="color:${t.body};font-weight:600;">${_fmtMoney(p.pay_amount)}</span>
                    ${p.pay_proof ? `<a href="/dormitory_management/Public/Assets/Images/Payments/${p.pay_proof}" target="_blank" rel="noopener"
                       title="ดูหลักฐาน" style="color:${t.link};font-size:0.85rem;text-decoration:none;">📎</a>` : ''}
                  </div>
                </div>`).join('')}
              <div style="display:flex;justify-content:space-between;padding:0.5rem 0;
                          border-top:1px solid ${t.divider};font-size:0.85rem;
                          margin-top:0.1rem;">
                <span style="color:${t.muted};">ชำระแล้ว / คงเหลือ</span>
                <span>
                  <span style="color:${t.green};font-weight:600;">${_fmtMoney(paidAmt)}</span>
                  ${remaining > 0
                    ? ' <span style="color:' + t.dim + ';">/ </span><span style="color:' + t.red + ';font-weight:600;">'+_fmtMoney(remaining)+'</span>'
                    : ' <span style="color:' + t.green + ';">✓</span>'}
                </span>
              </div>` : `<div style="font-size:0.82rem;color:${t.dim};">ยังไม่มีการชำระ</div>`}
          </div>`;
        }).join('');
      }

      /* ===== TAB: มิเตอร์ ===== */
      const utils = data.utility || [];
      if (utils.length === 0) {
        document.getElementById('cdrTabMeter').innerHTML =
          `<div style="text-align:center;padding:3rem;color:${t.dim};">ยังไม่มีประวัติการจดมิเตอร์</div>`;
      } else {
        document.getElementById('cdrTabMeter').innerHTML = `
          <table class="cdr-meter-table">
            <thead>
              <tr>
                <th>วันที่</th>
                <th style="color:${t.water};">น้ำเริ่ม</th>
                <th style="color:${t.water};">น้ำสิ้นสุด</th>
                <th style="color:${t.water};">ใช้ (หน่วย)</th>
                <th style="color:${t.elec};">ไฟเริ่ม</th>
                <th style="color:${t.elec};">ไฟสิ้นสุด</th>
                <th style="color:${t.elec};">ใช้ (หน่วย)</th>
              </tr>
            </thead>
            <tbody>
              ${utils.map(u => {
                const wUsed = u.utl_water_end != null
                  ? Math.max(0, (Number(u.utl_water_end)||0) - (Number(u.utl_water_start)||0)) : '-';
                const eUsed = u.utl_elec_end != null
                  ? Math.max(0, (Number(u.utl_elec_end)||0) - (Number(u.utl_elec_start)||0)) : '-';
                return `<tr>
                  <td>${_fmtDate(u.utl_date)}</td>
                  <td>${u.utl_water_start ?? 0}</td>
                  <td>${u.utl_water_end ?? '-'}</td>
                  <td style="color:${t.water};font-weight:600;">${wUsed}</td>
                  <td>${u.utl_elec_start ?? 0}</td>
                  <td>${u.utl_elec_end ?? '-'}</td>
                  <td style="color:${t.elec};font-weight:600;">${eUsed}</td>
                </tr>`;
              }).join('')}
            </tbody>
          </table>
        `;
      }
    }
    </script>
</body>
</html>
