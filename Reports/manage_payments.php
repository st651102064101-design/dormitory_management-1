<?php
declare(strict_types=1);
session_start();
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

// รับค่า filter จาก query parameter
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';
$filterMonth = isset($_GET['month']) ? $_GET['month'] : '';

// สร้าง WHERE clause
$whereConditions = [];
$whereParams = [];

if ($filterStatus !== '') {
    $whereConditions[] = "p.pay_status = ?";
    $whereParams[] = $filterStatus;
}

if ($filterMonth !== '') {
    $whereConditions[] = "DATE_FORMAT(p.pay_date, '%Y-%m') = ?";
    $whereParams[] = $filterMonth;
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// ดึงข้อมูลการชำระเงิน
$sql = "
    SELECT p.*,
           e.exp_id, e.exp_month, e.exp_total, e.exp_status,
           c.ctr_id, c.room_id,
           t.tnt_id, t.tnt_name, t.tnt_phone,
           r.room_number,
           rt.type_name
    FROM payment p
    LEFT JOIN expense e ON p.exp_id = e.exp_id
    LEFT JOIN contract c ON e.ctr_id = c.ctr_id
    LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
    LEFT JOIN room r ON c.room_id = r.room_id
    LEFT JOIN roomtype rt ON r.type_id = rt.type_id
    $whereClause
    ORDER BY p.pay_id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($whereParams);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงค่าใช้จ่ายที่ยังไม่ชำระและชำระยังไม่ครบ (สำหรับ dropdown ในฟอร์ม)
$unpaidExpenses = $pdo->query("
    SELECT e.exp_id, e.exp_month, e.exp_total, e.exp_status,
           c.ctr_id,
           t.tnt_name,
           r.room_number
    FROM expense e
    LEFT JOIN contract c ON e.ctr_id = c.ctr_id
    LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
    LEFT JOIN room r ON c.room_id = r.room_id
    WHERE e.exp_status IN ('0', '3')
    ORDER BY r.room_number, e.exp_month DESC
")->fetchAll(PDO::FETCH_ASSOC);

$statusMap = [
    '0' => 'รอตรวจสอบ',
    '1' => 'ตรวจสอบแล้ว',
];
$statusColors = [
    '0' => '#fbbf24',
    '1' => '#22c55e',
];

// คำนวณสถิติ
$stats = [
    'pending' => 0,
    'verified' => 0,
    'total_pending' => 0,
    'total_verified' => 0,
];
foreach ($payments as $pay) {
    if ($pay['pay_status'] === '0') {
        $stats['pending']++;
        $stats['total_pending'] += (int)($pay['pay_amount'] ?? 0);
    } else {
        $stats['verified']++;
        $stats['total_verified'] += (int)($pay['pay_amount'] ?? 0);
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

// ดึงเดือนที่มีข้อมูล
$availableMonths = $pdo->query("
    SELECT DISTINCT DATE_FORMAT(pay_date, '%Y-%m') as month_key
    FROM payment
    WHERE pay_date IS NOT NULL
    ORDER BY month_key DESC
")->fetchAll(PDO::FETCH_COLUMN);
?>
<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - จัดการการชำระเงิน</title>
    <link rel="icon" type="image/jpeg" href="../Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="../Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="../Assets/Css/main.css" />
    <link rel="stylesheet" href="../Assets/Css/confirm-modal.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4/dist/style.css" />
    <style>
      :root {
        --theme-bg-color: <?php echo $themeColor; ?>;
      }

      /* Toast fallback สำหรับหน้านี้ */
      #toast-container {
        position: fixed;
        top: 1.25rem;
        right: 1.25rem;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        z-index: 99999;
      }
      .toast {
        min-width: 240px;
        max-width: 320px;
        padding: 0.9rem 1rem;
        border-radius: 10px;
        color: #0f172a;
        background: #f8fafc;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        display: flex;
        align-items: center;
        gap: 0.6rem;
        font-weight: 600;
      }
      .toast-success { border-left: 6px solid #22c55e; }
      .toast-error { border-left: 6px solid #ef4444; }
      .toast small { font-weight: 500; color: #1f2937; }

      .payment-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
      }
      .payment-stat-card {
        background: linear-gradient(135deg, rgba(18,24,40,0.85), rgba(7,13,26,0.95));
        border-radius: 16px;
        padding: 1.25rem;
        border: 1px solid rgba(255,255,255,0.08);
        color: #f5f8ff;
        box-shadow: 0 15px 35px rgba(3,7,18,0.4);
      }
      .payment-stat-card h3 {
        margin: 0;
        font-size: 0.95rem;
        color: rgba(255,255,255,0.7);
      }
      .payment-stat-card .stat-value {
        font-size: 2.2rem;
        font-weight: 700;
        margin-top: 0.5rem;
      }
      .payment-stat-card .stat-money {
        font-size: 1.1rem;
        color: rgba(255,255,255,0.6);
        margin-top: 0.5rem;
      }

      /* Light theme overrides */
      @media (prefers-color-scheme: light) {
        .payment-stat-card {
          background: linear-gradient(135deg, rgba(243,244,246,0.95), rgba(229,231,235,0.85)) !important;
          border: 1px solid rgba(0,0,0,0.1) !important;
          color: #374151 !important;
        }
        .payment-stat-card h3 {
          color: #6b7280 !important;
        }
        .payment-stat-card .stat-money {
          color: #9ca3af !important;
        }
      }
      html.light-theme .payment-stat-card {
        background: linear-gradient(135deg, rgba(243,244,246,0.95), rgba(229,231,235,0.85)) !important;
        border: 1px solid rgba(0,0,0,0.1) !important;
        color: #374151 !important;
      }
      html.light-theme .payment-stat-card h3 {
        color: #6b7280 !important;
      }
      html.light-theme .payment-stat-card .stat-money {
        color: #9ca3af !important;
      }

      .payment-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        padding: 1.5rem 0;
      }
      .payment-form-group label {
        display: block;
        margin-bottom: 0.4rem;
        font-weight: 600;
        color: rgba(255,255,255,0.85);
        font-size: 0.95rem;
      }
      .payment-form-group input,
      .payment-form-group select {
        width: 100%;
        padding: 0.75rem 0.85rem;
        border-radius: 10px;
        border: 1px solid rgba(148,163,184,0.35);
        background: rgba(15,23,42,0.9);
        color: #e2e8f0;
        font-size: 1rem;
        transition: all 0.25s ease;
      }
      .payment-form-group input:focus,
      .payment-form-group select:focus {
        border-color: #38bdf8;
        outline: none;
        box-shadow: 0 0 0 3px rgba(56,189,248,0.2);
      }

      .filter-section {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        align-items: flex-end;
        margin-bottom: 1rem;
        padding: 1rem;
        background: rgba(15,23,42,0.5);
        border-radius: 12px;
        border: 1px solid rgba(255,255,255,0.08);
      }
      .filter-group {
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
      }
      .filter-group label {
        font-size: 0.85rem;
        color: rgba(255,255,255,0.7);
      }
      .filter-group select {
        padding: 0.5rem 0.75rem;
        border-radius: 8px;
        border: 1px solid rgba(148,163,184,0.35);
        background: rgba(15,23,42,0.9);
        color: #e2e8f0;
        font-size: 0.9rem;
        min-width: 150px;
      }

      .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.35rem 0.75rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
      }
      .status-pending {
        background: rgba(251, 191, 36, 0.15);
        color: #fbbf24;
        border: 1px solid rgba(251, 191, 36, 0.3);
      }
      .status-verified {
        background: rgba(34, 197, 94, 0.15);
        color: #22c55e;
        border: 1px solid rgba(34, 197, 94, 0.3);
      }

      .proof-link {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.3rem 0.6rem;
        background: rgba(96, 165, 250, 0.15);
        color: #60a5fa;
        border: 1px solid rgba(96, 165, 250, 0.3);
        border-radius: 6px;
        font-size: 0.8rem;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s;
      }
      .proof-link:hover {
        background: rgba(96, 165, 250, 0.25);
        transform: scale(1.05);
      }

      .action-btn {
        padding: 0.4rem 0.8rem;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
      }
      .btn-verify {
        background: rgba(34, 197, 94, 0.15);
        color: #22c55e;
        border: 1px solid rgba(34, 197, 94, 0.3);
      }
      .btn-verify:hover {
        background: rgba(34, 197, 94, 0.25);
      }
      .btn-reject {
        background: rgba(239, 68, 68, 0.15);
        color: #ef4444;
        border: 1px solid rgba(239, 68, 68, 0.3);
      }
      .btn-reject:hover {
        background: rgba(239, 68, 68, 0.25);
      }
      .btn-delete {
        background: rgba(239, 68, 68, 0.15);
        color: #ef4444;
        border: 1px solid rgba(239, 68, 68, 0.3);
      }
      .btn-delete:hover {
        background: rgba(239, 68, 68, 0.25);
      }

      /* Modal styles */
      .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: background-color 0.5s cubic-bezier(0.2, 0.55, 0.45, 0.8), opacity 0.5s cubic-bezier(0.2, 0.55, 0.45, 0.8);
        backdrop-filter: blur(0px);
      }
      .modal-overlay.active {
        display: flex;
        opacity: 1;
        background: rgba(0,0,0,0.4);
        backdrop-filter: blur(10px);
      }
      .modal-content {
        background: linear-gradient(135deg, rgba(15,23,42,0.98), rgba(2,6,23,0.98));
        border-radius: 20px;
        padding: 2rem;
        max-width: 90vw;
        max-height: 90vh;
        overflow: auto;
        border: 1px solid rgba(255,255,255,0.12);
        box-shadow: 0 20px 60px rgba(0,0,0,0.3), inset 0 1px 0 rgba(255,255,255,0.1);
        transform: scale(0.9) translateY(30px) opacity(0.8);
        opacity: 0;
        transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.5s cubic-bezier(0.16, 1, 0.3, 1);
      }
      .modal-overlay.active .modal-content {
        transform: scale(1) translateY(0) opacity(1);
        opacity: 1;
      }
      .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(255,255,255,0.08);
      }
      .modal-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #f8fafc;
      }
      .modal-close {
        width: 36px;
        height: 36px;
        border: none;
        background: rgba(255,255,255,0.08);
        color: #f8fafc;
        font-size: 1.5rem;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s cubic-bezier(0.2, 0.55, 0.45, 0.8);
      }
      .modal-close:hover {
        background: rgba(239, 68, 68, 0.15);
        color: #ef4444;
        transform: rotate(90deg);
      }
      .modal-body {
        animation: fadeInUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) 0.15s backwards;
      }
      @keyframes fadeInUp {
        from {
          opacity: 0;
          transform: translateY(20px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }

      .info-box {
        margin-top: 0.75rem;
        padding: 1rem 1.25rem;
        background: rgba(56,189,248,0.12);
        border-radius: 8px;
        color: #0369a1;
        font-size: 1rem;
        border-left: 4px solid #38bdf8;
      }
      .info-box strong {
        display: block;
        margin-bottom: 0.3rem;
      }

      /* responsive table */
      @media (max-width: 768px) {
        .manage-table {
          display: block;
          overflow-x: auto;
        }
      }
    </style>
  </head>
  <body class="reports-page">
    <div class="app-shell">
      <?php include __DIR__ . '/../includes/sidebar.php'; ?>

      <main class="app-main">
        <div class="container" style="max-width:100%;padding:1.5rem;">

          <!-- Header -->
          <section class="manage-panel">
            <div class="section-header">
              <div>
                <h1 style="margin:0;font-size:1.75rem;">💳 จัดการการชำระเงิน</h1>
                <p style="color:#94a3b8;margin-top:0.2rem;">ตรวจสอบและยืนยันการชำระเงินของผู้เช่า</p>
              </div>
            </div>
          </section>

          <!-- Stats -->
          <section class="manage-panel">
            <div class="section-header">
              <div>
                <p style="color:#94a3b8;margin-top:0.2rem;">สรุปสถานะการชำระเงิน</p>
              </div>
            </div>
            <div class="payment-stats">
              <div class="payment-stat-card">
                <h3>⏳ รอตรวจสอบ</h3>
                <div class="stat-value" style="color:#fbbf24;"><?php echo number_format($stats['pending']); ?></div>
                <div class="stat-money">฿<?php echo number_format($stats['total_pending']); ?></div>
              </div>
              <div class="payment-stat-card">
                <h3>✅ ตรวจสอบแล้ว</h3>
                <div class="stat-value" style="color:#22c55e;"><?php echo number_format($stats['verified']); ?></div>
                <div class="stat-money">฿<?php echo number_format($stats['total_verified']); ?></div>
              </div>
              <div class="payment-stat-card">
                <h3>📊 รวมทั้งหมด</h3>
                <div class="stat-value"><?php echo number_format($stats['pending'] + $stats['verified']); ?></div>
                <div class="stat-money">฿<?php echo number_format($stats['total_pending'] + $stats['total_verified']); ?></div>
              </div>
            </div>
          </section>

          <!-- Toggle button for payment form -->
          <div style="margin:1.5rem 0;">
            <button type="button" id="togglePaymentFormBtn" style="white-space:nowrap;padding:0.8rem 1.5rem;cursor:pointer;font-size:1rem;background:#1e293b;border:1px solid #334155;color:#cbd5e1;border-radius:8px;transition:all 0.2s;box-shadow:0 2px 4px rgba(0,0,0,0.1);" onclick="togglePaymentForm()" onmouseover="this.style.background='#334155';this.style.borderColor='#475569'" onmouseout="this.style.background='#1e293b';this.style.borderColor='#334155'">
              <span id="togglePaymentFormIcon">▼</span> <span id="togglePaymentFormText">ซ่อนฟอร์ม</span>
            </button>
          </div>

          <!-- Add Payment Form -->
          <section class="manage-panel" style="background:linear-gradient(135deg, rgba(15,23,42,0.95), rgba(2,6,23,0.95)); color:#f8fafc;" id="addPaymentSection">
            <div class="section-header">
              <div>
                <h2 style="margin:0;">➕ บันทึกการชำระเงินใหม่</h2>
                <p style="margin-top:0.25rem;color:rgba(255,255,255,0.7);">เลือกรายการค่าใช้จ่ายและกรอกข้อมูลการชำระ</p>
              </div>
            </div>
            <form action="../Manage/process_payment.php" method="post" enctype="multipart/form-data" id="paymentForm">
              <div class="payment-form">
                <div class="payment-form-group">
                  <label for="exp_id">ค่าใช้จ่าย (ยังไม่ชำระ) <span style="color:#f87171;">*</span></label>
                  <select name="exp_id" id="exp_id" required>
                    <option value="">-- เลือกรายการค่าใช้จ่าย --</option>
                    <?php 
                    $thaiMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
                    $grouped = [];
                    foreach ($unpaidExpenses as $exp) {
                      $month = $exp['exp_month'] ? date('m', strtotime($exp['exp_month'])) : '00';
                      $year = $exp['exp_month'] ? date('Y', strtotime($exp['exp_month'])) : '0000';
                      $monthKey = $year . '-' . $month;
                      $monthLabel = ($thaiMonths[(int)$month] ?? 'ไม่ทราบ') . ' ' . ((int)$year + 543);
                      if (!isset($grouped[$monthKey])) {
                        $grouped[$monthKey] = ['label' => $monthLabel, 'items' => []];
                      }
                      $grouped[$monthKey]['items'][] = $exp;
                    }
                    krsort($grouped); // เรียงจากใหม่ไปเก่า
                    foreach ($grouped as $monthKey => $group):
                    ?>
                      <optgroup label="<?php echo htmlspecialchars($group['label']); ?>">
                        <?php foreach ($group['items'] as $exp): ?>
                          <option value="<?php echo (int)$exp['exp_id']; ?>" data-amount="<?php echo (int)($exp['exp_total'] ?? 0); ?>">
                            ห้อง <?php echo htmlspecialchars((string)($exp['room_number'] ?? '-')); ?> - 
                            <?php echo htmlspecialchars($exp['tnt_name'] ?? 'ไม่ระบุ'); ?> 
                            (฿<?php echo number_format((int)($exp['exp_total'] ?? 0)); ?>)
                          </option>
                        <?php endforeach; ?>
                      </optgroup>
                    <?php endforeach; ?>
                  </select>
                </div>
                <input type="hidden" id="pay_date" name="pay_date" value="<?php echo date('Y-m-d'); ?>" />
                <div class="payment-form-group">
                  <label for="pay_amount">จำนวนเงิน (บาท) <span style="color:#f87171;">*</span></label>
                  <input type="number" id="pay_amount" name="pay_amount" min="1" step="1" required placeholder="0" />
                </div>
                <div class="payment-form-group">
                  <label for="pay_proof">หลักฐานการชำระ <span style="color:#f87171;">*</span></label>
                  <input type="file" id="pay_proof" name="pay_proof" accept="image/*,.pdf" style="padding:0.5rem;" required />
                </div>
              </div>
              <div style="padding:1rem 0;">
                <button type="submit" class="btn btn-primary" style="padding:0.75rem 2rem;font-size:1rem;background:linear-gradient(135deg,#22c55e,#16a34a);border:none;border-radius:10px;color:#fff;font-weight:600;cursor:pointer;box-shadow:0 4px 12px rgba(34,197,94,0.3);transition:all 0.2s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 16px rgba(34,197,94,0.4)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 12px rgba(34,197,94,0.3)'">
                  💾 บันทึกการชำระเงิน
                </button>
              </div>
            </form>
          </section>

          <!-- Filter Section -->
          <section class="manage-panel">
            <div class="filter-section">
              <div class="filter-group">
                <label>กรองตามสถานะ</label>
                <select id="filterStatus" onchange="applyFilters()">
                  <option value="">ทั้งหมด</option>
                  <option value="0" <?php echo $filterStatus === '0' ? 'selected' : ''; ?>>รอตรวจสอบ</option>
                  <option value="1" <?php echo $filterStatus === '1' ? 'selected' : ''; ?>>ตรวจสอบแล้ว</option>
                </select>
              </div>
              <div class="filter-group">
                <label>กรองตามเดือน</label>
                <select id="filterMonth" onchange="applyFilters()">
                  <option value="">ทั้งหมด</option>
                  <?php 
                  $thaiMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
                  foreach ($availableMonths as $m): 
                    $parts = explode('-', $m);
                    $year = (int)$parts[0] + 543;
                    $month = (int)$parts[1];
                    $label = $thaiMonths[$month] . ' ' . $year;
                  ?>
                    <option value="<?php echo $m; ?>" <?php echo $filterMonth === $m ? 'selected' : ''; ?>><?php echo $label; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="filter-group">
                <button type="button" onclick="clearFilters()" style="padding:0.5rem 1rem;background:rgba(148,163,184,0.2);border:1px solid rgba(148,163,184,0.3);color:#94a3b8;border-radius:8px;cursor:pointer;font-size:0.9rem;">🔄 ล้างตัวกรอง</button>
              </div>
            </div>
          </section>

          <!-- Payments Table -->
          <section class="manage-panel">
            <div class="section-header">
              <div>
                <h2 style="margin:0;">📋 รายการการชำระเงิน</h2>
                <p style="color:#94a3b8;margin-top:0.2rem;">พบ <?php echo count($payments); ?> รายการ</p>
              </div>
            </div>
            <div style="overflow-x:auto;">
              <table class="manage-table" id="paymentsTable">
                <thead>
                  <tr>
                    <th>รหัส</th>
                    <th>ห้อง</th>
                    <th>ผู้เช่า</th>
                    <th>เดือนค่าใช้จ่าย</th>
                    <th>วันที่ชำระ</th>
                    <th>จำนวนเงิน</th>
                    <th>หลักฐาน</th>
                    <th>สถานะ</th>
                    <th>การดำเนินการ</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($payments)): ?>
                    <tr>
                      <td colspan="9" style="text-align:center;padding:2rem;color:#64748b;">ยังไม่มีข้อมูลการชำระเงิน</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($payments as $pay): ?>
                      <tr data-pay-id="<?php echo (int)$pay['pay_id']; ?>">
                        <td><?php echo (int)$pay['pay_id']; ?></td>
                        <td><?php echo htmlspecialchars((string)($pay['room_number'] ?? '-')); ?></td>
                        <td><?php echo htmlspecialchars($pay['tnt_name'] ?? '-'); ?></td>
                        <td><?php echo $pay['exp_month'] ? date('m/Y', strtotime($pay['exp_month'])) : '-'; ?></td>
                        <td><?php echo $pay['pay_date'] ? date('d/m/Y', strtotime($pay['pay_date'])) : '-'; ?></td>
                        <td style="text-align:right;font-weight:700;color:#22c55e;">฿<?php echo number_format((int)($pay['pay_amount'] ?? 0)); ?></td>
                        <td>
                          <?php if (!empty($pay['pay_proof'])): ?>
                            <span class="proof-link" onclick="showProof('<?php echo htmlspecialchars($pay['pay_proof'], ENT_QUOTES, 'UTF-8'); ?>')">
                              📎 ดูหลักฐาน
                            </span>
                          <?php else: ?>
                            <span style="color:#64748b;">-</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php 
                          $statusClass = $pay['pay_status'] === '1' ? 'status-verified' : 'status-pending';
                          $statusText = $statusMap[$pay['pay_status']] ?? 'ไม่ทราบ';
                          $statusIcon = $pay['pay_status'] === '1' ? '✅' : '⏳';
                          ?>
                          <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusIcon; ?> <?php echo $statusText; ?></span>
                        </td>
                        <td>
                          <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                            <?php if ($pay['pay_status'] === '0'): ?>
                              <button type="button" class="action-btn btn-verify" onclick="updatePaymentStatus(<?php echo (int)$pay['pay_id']; ?>, '1', <?php echo (int)$pay['exp_id']; ?>)">✅ ยืนยัน</button>
                            <?php else: ?>
                              <button type="button" class="action-btn btn-reject" onclick="updatePaymentStatus(<?php echo (int)$pay['pay_id']; ?>, '0', <?php echo (int)$pay['exp_id']; ?>)">↩️ ยกเลิก</button>
                            <?php endif; ?>
                            <button type="button" class="action-btn btn-delete" onclick="deletePayment(<?php echo (int)$pay['pay_id']; ?>)">🗑️ ลบ</button>
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

    <!-- Proof Modal -->
    <div class="modal-overlay" id="proofModal">
      <div class="modal-content" style="max-width:80vw;">
        <div class="modal-header">
          <h3 class="modal-title">📎 หลักฐานการชำระเงิน</h3>
          <button class="modal-close" onclick="closeProofModal()">×</button>
        </div>
        <div class="modal-body" id="proofModalBody" style="text-align:center;">
          <!-- Content will be loaded here -->
        </div>
      </div>
    </div>

    <script src="../Assets/Javascript/animate-ui.js"></script>
    <script src="../Assets/Javascript/confirm-modal.js"></script>
    <script>
      // Toast fallback (ถ้าไม่มีประกาศไว้จากไฟล์อื่น)
      if (typeof showSuccessToast !== 'function' || typeof showErrorToast !== 'function') {
        const ensureToastContainer = () => {
          let c = document.getElementById('toast-container');
          if (!c) {
            c = document.createElement('div');
            c.id = 'toast-container';
            document.body.appendChild(c);
          }
          return c;
        };
        const makeToast = (message, type) => {
          const c = ensureToastContainer();
          const t = document.createElement('div');
          t.className = `toast ${type === 'error' ? 'toast-error' : 'toast-success'}`;
          t.textContent = message;
          c.appendChild(t);
          setTimeout(() => {
            t.style.opacity = '0';
            t.style.transition = 'opacity 0.3s ease';
            setTimeout(() => t.remove(), 300);
          }, 2500);
        };
        if (typeof showSuccessToast !== 'function') {
          window.showSuccessToast = (msg) => makeToast(msg || 'สำเร็จ', 'success');
        }
        if (typeof showErrorToast !== 'function') {
          window.showErrorToast = (msg) => makeToast(msg || 'เกิดข้อผิดพลาด', 'error');
        }
      }

      // Toggle payment form
      function togglePaymentForm() {
        const section = document.getElementById('addPaymentSection');
        const icon = document.getElementById('togglePaymentFormIcon');
        const text = document.getElementById('togglePaymentFormText');
        
        if (section.style.display === 'none') {
          section.style.display = 'block';
          icon.textContent = '▼';
          text.textContent = 'ซ่อนฟอร์ม';
        } else {
          section.style.display = 'none';
          icon.textContent = '▶';
          text.textContent = 'แสดงฟอร์ม';
        }
      }

      // Auto-fill amount when selecting expense
      document.getElementById('exp_id')?.addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        const amount = selected.dataset.amount || '';
        document.getElementById('pay_amount').value = amount;
      });

      // Auto-set pay date (hidden field)
      (function setToday() {
        const payDate = document.getElementById('pay_date');
        if (!payDate) return;
        const today = new Date();
        const y = today.getFullYear();
        const m = String(today.getMonth() + 1).padStart(2, '0');
        const d = String(today.getDate()).padStart(2, '0');
        payDate.value = `${y}-${m}-${d}`;
      })();

      // Apply filters
      function applyFilters() {
        const status = document.getElementById('filterStatus').value;
        const month = document.getElementById('filterMonth').value;
        
        let url = window.location.pathname + '?';
        const params = [];
        if (status !== '') params.push('status=' + status);
        if (month !== '') params.push('month=' + month);
        
        window.location.href = url + params.join('&');
      }

      function clearFilters() {
        window.location.href = window.location.pathname;
      }

      // Show proof modal
      function showProof(filename) {
        const modal = document.getElementById('proofModal');
        const body = document.getElementById('proofModalBody');
        
        const ext = filename.toLowerCase().split('.').pop();
        const isPdf = ext === 'pdf';
        const path = '../Assets/Images/Payments/' + filename;
        
        if (isPdf) {
          body.innerHTML = '<embed src="' + path + '" type="application/pdf" width="100%" height="600px" />';
        } else {
          body.innerHTML = '<img src="' + path + '" alt="หลักฐานการชำระเงิน" style="max-width:100%;max-height:70vh;border-radius:8px;" />';
        }
        
        modal.classList.add('active');
      }

      function closeProofModal() {
        document.getElementById('proofModal').classList.remove('active');
      }

      // Close modal on overlay click
      document.getElementById('proofModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeProofModal();
      });

      // Update payment status
      async function updatePaymentStatus(payId, newStatus, expId) {
        const statusText = newStatus === '1' ? 'ยืนยันการชำระเงิน' : 'ยกเลิกการยืนยัน';
        
        let confirmed = false;
        if (typeof showConfirmDialog === 'function') {
          confirmed = await showConfirmDialog(
            'ยืนยันการดำเนินการ',
            `คุณต้องการ${statusText}นี้หรือไม่?`,
            'warning'
          );
        } else {
          confirmed = confirm(`คุณต้องการ${statusText}นี้หรือไม่?`);
        }
        if (confirmed) {
          await doUpdatePaymentStatus(payId, newStatus, expId);
        }
      }

      async function doUpdatePaymentStatus(payId, newStatus, expId) {
        try {
          const formData = new FormData();
          formData.append('pay_id', payId);
          formData.append('pay_status', newStatus);
          formData.append('exp_id', expId);

          const response = await fetch('../Manage/update_payment_status.php', {
            method: 'POST',
            body: formData
          });

          const data = await response.json();

          if (data.success) {
            if (typeof showSuccessToast === 'function') {
              showSuccessToast(data.message || 'อัปเดตสถานะเรียบร้อย');
            }
            setTimeout(() => location.reload(), 1000);
          } else {
            if (typeof showErrorToast === 'function') {
              showErrorToast(data.error || 'เกิดข้อผิดพลาด');
            } else {
              alert(data.error || 'เกิดข้อผิดพลาด');
            }
          }
        } catch (err) {
          console.error(err);
          if (typeof showErrorToast === 'function') {
            showErrorToast('เกิดข้อผิดพลาดในการเชื่อมต่อ');
          } else {
            alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
          }
        }
      }

      // Delete payment
      async function deletePayment(payId) {
        let confirmed = false;
        if (typeof showConfirmDialog === 'function') {
          confirmed = await showConfirmDialog(
            'ยืนยันการลบ',
            'คุณต้องการลบรายการชำระเงินนี้หรือไม่? การดำเนินการนี้ไม่สามารถย้อนกลับได้',
            'delete'
          );
        } else {
          confirmed = confirm('คุณต้องการลบรายการชำระเงินนี้หรือไม่?');
        }
        if (confirmed) {
          await doDeletePayment(payId);
        }
      }

      async function doDeletePayment(payId) {
        try {
          const formData = new FormData();
          formData.append('pay_id', payId);

          const response = await fetch('../Manage/delete_payment.php', {
            method: 'POST',
            body: formData
          });

          let data;
          try {
            data = await response.json();
          } catch (err) {
            console.error('Delete payment: invalid JSON', err);
            if (typeof showErrorToast === 'function') {
              showErrorToast('ลบไม่สำเร็จ: ตอบกลับไม่ถูกต้อง');
            } else {
              alert('ลบไม่สำเร็จ: ตอบกลับไม่ถูกต้อง');
            }
            return;
          }

          if (data.success) {
            if (typeof showSuccessToast === 'function') {
              showSuccessToast(data.message || 'ลบรายการเรียบร้อย');
            }
            setTimeout(() => location.reload(), 500);
          } else {
            console.error('Delete payment error:', data.error);
            if (typeof showErrorToast === 'function') {
              showErrorToast(data.error || 'เกิดข้อผิดพลาด');
            } else {
              alert(data.error || 'เกิดข้อผิดพลาด');
            }
          }
        } catch (err) {
          console.error(err);
          if (typeof showErrorToast === 'function') {
            showErrorToast('เกิดข้อผิดพลาดในการเชื่อมต่อ');
          } else {
            alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
          }
        }
      }

      // Form submission
      document.getElementById('paymentForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        try {
          const response = await fetch(this.action, {
            method: 'POST',
            body: formData
          });

          const data = await response.json();

          if (data.success) {
            if (typeof showSuccessToast === 'function') {
              showSuccessToast(data.message || 'บันทึกการชำระเงินเรียบร้อย');
            }
            setTimeout(() => location.reload(), 1000);
          } else {
            if (typeof showErrorToast === 'function') {
              showErrorToast(data.error || 'เกิดข้อผิดพลาด');
            } else {
              alert(data.error || 'เกิดข้อผิดพลาด');
            }
          }
        } catch (err) {
          console.error(err);
          if (typeof showErrorToast === 'function') {
            showErrorToast('เกิดข้อผิดพลาดในการเชื่อมต่อ');
          } else {
            alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
          }
        }
      });

      // Keyboard shortcut to close modal
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          closeProofModal();
        }
      });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4" type="text/javascript"></script>
    <script>
      // Initialize DataTable for payments table
      document.addEventListener('DOMContentLoaded', function() {
        const paymentsTable = document.getElementById('paymentsTable');
        if (paymentsTable) {
          new simpleDatatables.DataTable(paymentsTable, {
            searchable: true,
            fixedHeight: false,
            perPage: 10,
            perPageSelect: [5, 10, 25, 50],
            labels: {
              placeholder: 'ค้นหา...',
              perPage: 'รายการต่อหน้า',
              noRows: 'ไม่พบข้อมูล',
              info: 'แสดง {start} ถึง {end} จาก {rows} รายการ'
            }
          });
        }
      });
    </script>
  </body>
</html>
