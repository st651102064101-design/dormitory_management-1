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

// ตรวจสอบว่าเป็น light theme หรือไม่ (คำนวณจากความสว่างของสี)
$isLightTheme = false;
$lightThemeClass = '';
if (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $themeColor)) {
    $hex = ltrim($themeColor, '#');
    if (strlen($hex) == 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
    if ($brightness > 180) {
        $isLightTheme = true;
        $lightThemeClass = 'light-theme';
    }
}

// ดึงข้อมูลการตั้งค่าธนาคาร (bank information)
$settings = [
    'bank_name' => '',
    'bank_account_name' => '',
    'bank_account_number' => '',
    'promptpay_number' => ''
];
try {
    $settingsQuery = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('bank_name','bank_account_name','bank_account_number','promptpay_number')");
    while ($row = $settingsQuery->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    // Handle error silently
}

// รับค่า filter จาก query parameter
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';
$filterMonth = isset($_GET['filter_month']) ? $_GET['filter_month'] : '';
$filterYear = isset($_GET['filter_year']) ? $_GET['filter_year'] : '';
$filterRoom = isset($_GET['room']) ? $_GET['room'] : '';

// สร้าง WHERE clause
$whereConditions = [];
$whereParams = [];

if ($filterStatus !== '') {
    $whereConditions[] = "p.pay_status = ?";
    $whereParams[] = $filterStatus;
}

if ($filterMonth !== '' && $filterYear !== '') {
    $whereConditions[] = "MONTH(p.pay_date) = ? AND YEAR(p.pay_date) = ?";
    $whereParams[] = $filterMonth;
    $whereParams[] = $filterYear;
} elseif ($filterMonth !== '') {
    $whereConditions[] = "MONTH(p.pay_date) = ?";
    $whereParams[] = $filterMonth;
} elseif ($filterYear !== '') {
    $whereConditions[] = "YEAR(p.pay_date) = ?";
    $whereParams[] = $filterYear;
}

if ($filterRoom !== '') {
    $whereConditions[] = "r.room_number = ?";
    $whereParams[] = $filterRoom;
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

// ดึงสรุปการชำระเงินแยกตามห้อง
$roomPaymentSummary = $pdo->query("
    SELECT 
        r.room_id,
        r.room_number,
        t.tnt_name,
        COUNT(p.pay_id) as payment_count,
        SUM(CASE WHEN p.pay_status = '1' THEN p.pay_amount ELSE 0 END) as total_verified,
        SUM(CASE WHEN p.pay_status = '0' THEN p.pay_amount ELSE 0 END) as total_pending,
        MAX(p.pay_date) as last_payment_date
    FROM room r
    LEFT JOIN contract c ON r.room_id = c.room_id AND c.ctr_status = '0'
    LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
    LEFT JOIN expense e ON c.ctr_id = e.ctr_id
    LEFT JOIN payment p ON e.exp_id = p.exp_id
    WHERE c.ctr_id IS NOT NULL
    GROUP BY r.room_id, r.room_number, t.tnt_name
    ORDER BY r.room_number ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="th" class="<?php echo $lightThemeClass; ?>">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - จัดการการชำระเงิน</title>
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/confirm-modal.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4/dist/style.css" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/datatable-modern.css" />
    <style>
      :root {
        --theme-bg-color: <?php echo $themeColor; ?>;
      }

      /* ===== Comprehensive Light Theme Overrides ===== */
      html.light-theme .manage-panel,
      html.light-theme .section-header,
      html.light-theme .panel-title h1,
      html.light-theme .panel-title p,
      html.light-theme .section-title h3,
      html.light-theme .section-title p,
      html.light-theme .title-block h1,
      html.light-theme .title-block p,
      html.light-theme .report-table,
      html.light-theme .report-table th,
      html.light-theme .report-table td,
      html.light-theme label,
      html.light-theme select,
      html.light-theme input:not([type="submit"]):not([type="button"]),
      html.light-theme textarea {
        color: #111827 !important;
      }

      /* Buttons with gradient background - keep white text - HIGH SPECIFICITY */
      html.light-theme button[type="submit"],
      html.light-theme button[type="submit"] *,
      html.light-theme button.submit-btn-animated,
      html.light-theme button.submit-btn-animated *,
      html.light-theme .btn.btn-primary,
      html.light-theme .btn.btn-primary * {
        color: #ffffff !important;
      }
      html.light-theme button[type="submit"] svg,
      html.light-theme button.submit-btn-animated svg,
      html.light-theme .btn.btn-primary svg {
        stroke: #ffffff !important;
        color: #ffffff !important;
      }

      html.light-theme .manage-panel {
        background: rgba(255,255,255,0.7) !important;
        border-color: rgba(0,0,0,0.08) !important;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08) !important;
      }

      html.light-theme .manage-panel svg {
        stroke: #111827 !important;
      }

      /* Payment form section - keep dark background and white text */
      html.light-theme .payment-form-section {
        background: linear-gradient(135deg, rgba(15,23,42,0.95), rgba(2,6,23,0.95)) !important;
      }
      html.light-theme .payment-form-section,
      html.light-theme .payment-form-section *,
      html.light-theme .payment-form-section label,
      html.light-theme .payment-form-section span,
      html.light-theme .payment-form-section h3 {
        color: #f8fafc !important;
      }
      html.light-theme .payment-form-section svg {
        stroke: #f8fafc !important;
      }
      html.light-theme .payment-form-section input:not([type="submit"]):not([type="button"]),
      html.light-theme .payment-form-section select {
        color: #111827 !important;
        background: rgba(255,255,255,0.9) !important;
      }
      /* Submit button - force green background and white text */
      html.light-theme .payment-form-section button[type="submit"],
      html.light-theme .payment-form-section #submitPaymentBtn,
      html.light-theme #submitPaymentBtn {
        background: linear-gradient(135deg,#22c55e,#16a34a) !important;
        color: #ffffff !important;
      }
      html.light-theme .payment-form-section button[type="submit"] span,
      html.light-theme .payment-form-section button[type="submit"] *,
      html.light-theme #submitPaymentBtn span,
      html.light-theme #submitPaymentBtn * {
        color: #ffffff !important;
      }
      html.light-theme .payment-form-section button[type="submit"] svg,
      html.light-theme #submitPaymentBtn svg {
        stroke: #ffffff !important;
      }

      /* HIGHEST SPECIFICITY: Target by ID */
      html.light-theme #addPaymentSection button[type="submit"],
      html.light-theme #addPaymentSection button[type="submit"] *,
      html.light-theme #addPaymentSection .submit-btn-animated,
      html.light-theme #addPaymentSection .submit-btn-animated * {
        color: #ffffff !important;
      }
      html.light-theme #addPaymentSection button[type="submit"] svg,
      html.light-theme #addPaymentSection .submit-btn-animated svg {
        stroke: #ffffff !important;
        fill: none !important;
      }

      /* Exception: buttons with colored background keep white icons */
      html.light-theme .manage-panel button[type="submit"] svg,
      html.light-theme .manage-panel button.submit-btn-animated svg,
      html.light-theme .manage-panel .btn.btn-primary svg,
      html.light-theme .manage-panel button[style*="background:linear-gradient"] svg,
      html.light-theme .manage-panel button[style*="background: linear-gradient"] svg,
      html.light-theme .manage-panel .status-badge svg {
        stroke: #ffffff !important;
      }
      html.light-theme .manage-panel button[type="submit"],
      html.light-theme .manage-panel button[type="submit"] span,
      html.light-theme .manage-panel button.submit-btn-animated,
      html.light-theme .manage-panel button.submit-btn-animated span,
      html.light-theme .manage-panel .btn.btn-primary,
      html.light-theme .manage-panel .btn.btn-primary span {
        color: #ffffff !important;
      }

      /* Keep white icons on colored backgrounds */
      html.light-theme .panel-icon-modern svg,
      html.light-theme .panel-icon-animated svg,
      html.light-theme .payment-info-icon svg,
      html.light-theme .stat-card-icon svg,
      html.light-theme .icon-circle svg {
        stroke: #ffffff !important;
      }

      /* Section header styles */
      html.light-theme .section-header .title-block h1 { color: #1e293b !important; }
      html.light-theme .section-header .title-block p { color: #64748b !important; }

      /* Filter inputs */
      html.light-theme .filter-select,
      html.light-theme select {
        background: rgba(0,0,0,0.05) !important;
        border-color: rgba(0,0,0,0.12) !important;
        color: #111827 !important;
      }

      /* Action buttons - keep text white on colored backgrounds */
      html.light-theme .btn-modern,
      html.light-theme .btn-action,
      html.light-theme button[style*="background:linear-gradient"],
      html.light-theme button[style*="background: linear-gradient"] {
        color: #ffffff !important;
      }
      html.light-theme .btn-modern svg,
      html.light-theme .btn-action svg,
      html.light-theme button[style*="background:linear-gradient"] svg,
      html.light-theme button[style*="background: linear-gradient"] svg {
        stroke: #ffffff !important;
      }

      /* Outline/ghost buttons - dark text */
      html.light-theme button[style*="background:rgba(239,68,68"],
      html.light-theme button[style*="background: rgba(239,68,68"] {
        color: #dc2626 !important;
      }
      html.light-theme button[style*="background:rgba(239,68,68"] svg,
      html.light-theme button[style*="background: rgba(239,68,68"] svg {
        stroke: #dc2626 !important;
      }

      /* Status badges - solid background with white text in light theme */
      html.light-theme .status-badge.status-pending {
        background: linear-gradient(135deg, #f59e0b, #d97706) !important;
        color: #ffffff !important;
        border: none !important;
      }
      html.light-theme .status-badge.status-verified {
        background: linear-gradient(135deg, #22c55e, #16a34a) !important;
        color: #ffffff !important;
        border: none !important;
      }
      html.light-theme .status-badge svg {
        stroke: #ffffff !important;
      }

      /* Submit button - ensure visible */
      html.light-theme .submit-btn-animated,
      html.light-theme button[type="submit"] {
        color: #ffffff !important;
      }
      html.light-theme .submit-btn-animated svg,
      html.light-theme button[type="submit"] svg {
        stroke: #ffffff !important;
      }

      /* Table action buttons with specific styling */
      html.light-theme .crud-actions button {
        color: #ffffff !important;
      }
      html.light-theme .crud-actions button svg {
        stroke: #ffffff !important;
      }

      /* DataTable specific */
      html.light-theme .datatable-table th,
      html.light-theme .datatable-table td {
        color: #111827 !important;
      }
      html.light-theme .datatable-input,
      html.light-theme .datatable-selector {
        background: rgba(0,0,0,0.03) !important;
        border-color: rgba(0,0,0,0.1) !important;
        color: #111827 !important;
      }
      html.light-theme .datatable-info,
      html.light-theme .datatable-pagination a {
        color: #374151 !important;
      }

      /* Modern panel + animations */
      .manage-panel {
        background: linear-gradient(135deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));
        border-radius: 14px;
        padding: 1rem 1.25rem;
        margin-bottom: 1rem;
        border: 1px solid rgba(255,255,255,0.04);
        box-shadow: 0 8px 30px rgba(2,6,23,0.6);
        backdrop-filter: blur(6px);
      }

      .section-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        justify-content: space-between;
      }

      .section-header .title-block {
        display:flex; align-items:center; gap:0.75rem;
      }

      .panel-icon-modern {
        width:56px; height:56px; display:flex; align-items:center; justify-content:center; border-radius:12px;
        background: linear-gradient(135deg,#8b5cf6,#7c3aed);
        box-shadow: 0 8px 25px rgba(124,58,237,0.2);
        flex-shrink:0;
      }

      .panel-icon-modern svg { width:28px; height:28px; color:white; }

      /* small animated coin */
      .coin-animated { transform-origin: center; transition: transform 0.4s ease; }
      .coin-animated.spin { animation: spin 2.5s linear infinite; }

      @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
      }

      /* Entrance animations for cards */
      .fade-in-up { opacity:0; transform: translateY(10px); animation: fadeInUp 0.6s forwards; }
      @keyframes fadeInUp { to { opacity:1; transform: translateY(0); } }


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

      /* ===== Modern Payment Info Section with Animations ===== */
      .payment-info-section {
        position: relative;
        background: linear-gradient(135deg, rgba(99,102,241,0.08), rgba(139,92,246,0.05));
        border: 1px solid rgba(99,102,241,0.15);
        border-radius: 20px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        overflow: hidden;
      }

      .payment-info-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.25rem;
        position: relative;
        z-index: 2;
      }

      .payment-info-icon {
        width: 52px;
        height: 52px;
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 8px 25px rgba(139,92,246,0.35);
        flex-shrink: 0;
      }

      .payment-info-icon svg {
        width: 26px;
        height: 26px;
        stroke: white;
      }

      /* Wallet animation */
      .wallet-animated {
        animation: walletBounce 3s ease-in-out infinite;
      }

      @keyframes walletBounce {
        0%, 100% { transform: translateY(0) rotate(0deg); }
        25% { transform: translateY(-3px) rotate(-3deg); }
        75% { transform: translateY(-3px) rotate(3deg); }
      }

      .coin-pulse {
        animation: coinPulse 2s ease-in-out infinite;
        transform-origin: center;
      }

      @keyframes coinPulse {
        0%, 100% { r: 2; opacity: 1; }
        50% { r: 2.5; opacity: 0.7; }
      }

      .payment-info-title h4 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 700;
        color: #f8fafc;
        letter-spacing: -0.01em;
      }

      .payment-info-title p {
        margin: 0.25rem 0 0 0;
        font-size: 0.85rem;
        color: rgba(255,255,255,0.5);
      }

      .payment-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
        position: relative;
        z-index: 2;
      }

      .payment-info-card {
        position: relative;
        background: rgba(255,255,255,0.03);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 16px;
        padding: 1rem 1.25rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        overflow: hidden;
      }

      .payment-info-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--card-accent, #3b82f6);
        opacity: 0;
        transition: opacity 0.3s ease;
      }

      .payment-info-card:hover {
        transform: translateY(-4px) scale(1.02);
        border-color: rgba(255,255,255,0.15);
        box-shadow: 0 15px 35px rgba(0,0,0,0.2);
      }

      .payment-info-card:hover::before {
        opacity: 1;
      }

      .payment-info-card.copyable {
        cursor: pointer;
      }

      .payment-info-card.copyable:hover {
        background: rgba(255,255,255,0.06);
      }

      .payment-info-card.copyable:active {
        transform: translateY(-2px) scale(1);
      }

      .card-icon-wrapper {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: transform 0.3s ease;
      }

      .payment-info-card:hover .card-icon-wrapper {
        transform: scale(1.1) rotate(-5deg);
      }

      .card-icon-wrapper svg {
        width: 22px;
        height: 22px;
        stroke: white;
      }

      .card-icon-wrapper.blue {
        background: linear-gradient(135deg, #3b82f6, #60a5fa);
        box-shadow: 0 6px 15px rgba(59,130,246,0.3);
      }

      .card-icon-wrapper.purple {
        background: linear-gradient(135deg, #8b5cf6, #a78bfa);
        box-shadow: 0 6px 15px rgba(139,92,246,0.3);
      }

      .card-icon-wrapper.green {
        background: linear-gradient(135deg, #10b981, #34d399);
        box-shadow: 0 6px 15px rgba(16,185,129,0.3);
      }

      .card-icon-wrapper.orange {
        background: linear-gradient(135deg, #f59e0b, #fbbf24);
        box-shadow: 0 6px 15px rgba(245,158,11,0.3);
      }

      /* Icon float animation */
      .icon-float {
        animation: iconFloat 3s ease-in-out infinite;
      }

      @keyframes iconFloat {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-3px); }
      }

      .card-content {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
        flex: 1;
        min-width: 0;
      }

      .card-label {
        font-size: 0.75rem;
        font-weight: 500;
        color: rgba(255,255,255,0.5);
        text-transform: uppercase;
        letter-spacing: 0.05em;
      }

      .card-value {
        font-size: 1rem;
        font-weight: 600;
        color: #f1f5f9;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        word-break: break-all;
      }

      .card-value .not-set {
        color: rgba(255,255,255,0.3);
        font-weight: 500;
        font-style: italic;
      }

      .copy-icon {
        width: 16px;
        height: 16px;
        stroke: rgba(255,255,255,0.4);
        flex-shrink: 0;
        transition: all 0.2s ease;
      }

      .payment-info-card.copyable:hover .copy-icon {
        stroke: rgba(255,255,255,0.8);
        transform: scale(1.1);
      }

      /* Card accent colors */
      .bank-card { --card-accent: #3b82f6; }
      .account-card { --card-accent: #8b5cf6; }
      .number-card { --card-accent: #10b981; }
      .promptpay-card { --card-accent: #f59e0b; }

      /* Card glow effect */
      .card-glow {
        position: absolute;
        bottom: -50%;
        right: -50%;
        width: 100%;
        height: 100%;
        border-radius: 50%;
        opacity: 0;
        transition: opacity 0.3s ease;
        pointer-events: none;
      }

      .card-glow.blue { background: radial-gradient(circle, rgba(59,130,246,0.15), transparent 70%); }
      .card-glow.purple { background: radial-gradient(circle, rgba(139,92,246,0.15), transparent 70%); }
      .card-glow.green { background: radial-gradient(circle, rgba(16,185,129,0.15), transparent 70%); }
      .card-glow.orange { background: radial-gradient(circle, rgba(245,158,11,0.15), transparent 70%); }

      .payment-info-card:hover .card-glow {
        opacity: 1;
      }

      /* Info section floating particles */
      .info-particles {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        overflow: hidden;
        z-index: 1;
      }

      .info-particles span {
        position: absolute;
        width: 6px;
        height: 6px;
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        border-radius: 50%;
        opacity: 0.3;
        animation: particleFloat 5s ease-in-out infinite;
      }

      .info-particles span:nth-child(1) { left: 10%; top: 20%; animation-delay: 0s; }
      .info-particles span:nth-child(2) { left: 30%; top: 70%; animation-delay: 1.2s; }
      .info-particles span:nth-child(3) { left: 70%; top: 30%; animation-delay: 2.4s; }
      .info-particles span:nth-child(4) { left: 85%; top: 60%; animation-delay: 3.6s; }

      @keyframes particleFloat {
        0%, 100% { transform: translateY(0) scale(1); opacity: 0.3; }
        50% { transform: translateY(-15px) scale(1.2); opacity: 0.6; }
      }

      /* Light theme overrides for payment info section */
      html.light-theme .payment-info-section {
        background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(139,92,246,0.03));
        border-color: rgba(99,102,241,0.1);
      }

      html.light-theme .payment-info-title h4 {
        color: #1e293b;
      }

      html.light-theme .payment-info-title p {
        color: rgba(0,0,0,0.5);
      }

      html.light-theme .payment-info-card {
        background: rgba(255,255,255,0.7);
        border-color: rgba(0,0,0,0.06);
      }

      html.light-theme .card-label {
        color: rgba(0,0,0,0.5);
      }

      html.light-theme .card-value {
        color: #1e293b;
      }

      html.light-theme .card-value .not-set {
        color: rgba(0,0,0,0.3);
      }

      /* ===== Panel Icon Animations ===== */
      .panel-header {
        display: flex;
        align-items: center;
        gap: 1rem;
      }

      .panel-icon-animated {
        width: 56px;
        height: 56px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: transform 0.3s ease;
      }

      .panel-icon-animated:hover {
        transform: scale(1.1) rotate(-5deg);
      }

      .panel-icon-animated svg {
        width: 28px;
        height: 28px;
        stroke: white;
      }

      .panel-icon-animated.add-payment {
        background: linear-gradient(135deg, #22c55e, #16a34a);
        box-shadow: 0 8px 25px rgba(34,197,94,0.35);
      }

      /* Plus icon animation */
      .plus-animated {
        animation: plusPulse 2.5s ease-in-out infinite;
      }

      @keyframes plusPulse {
        0%, 100% { transform: scale(1) rotate(0deg); }
        25% { transform: scale(1.1) rotate(90deg); }
        50% { transform: scale(1) rotate(90deg); }
        75% { transform: scale(1.1) rotate(0deg); }
      }

      .panel-title-content h2 {
        letter-spacing: -0.01em;
      }

      /* Payment form section styling */
      .payment-form-section {
        position: relative;
        overflow: hidden;
      }

      .payment-form-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #22c55e, #10b981, #14b8a6);
        opacity: 0;
        transition: opacity 0.3s ease;
      }

      .payment-form-section:hover::before {
        opacity: 1;
      }

      /* ===== End Panel Icon Animations ===== */

      /* Toggle Form Button Styling */
      .toggle-form-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.85rem 1.5rem;
        background: rgba(255,255,255,0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 12px;
        color: rgba(255,255,255,0.8);
        font-size: 0.95rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        white-space: nowrap;
      }

      .toggle-form-btn:hover {
        background: rgba(255,255,255,0.1);
        border-color: rgba(255,255,255,0.2);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.2);
      }

      .toggle-form-btn .toggle-icon {
        width: 18px;
        height: 18px;
        transition: transform 0.3s ease;
      }

      .toggle-form-btn.collapsed .toggle-icon {
        transform: rotate(-90deg);
      }

      /* Light theme toggle button */
      html.light-theme .toggle-form-btn {
        background: rgba(0,0,0,0.05);
        border-color: rgba(0,0,0,0.1);
        color: rgba(0,0,0,0.7);
      }

      html.light-theme .toggle-form-btn:hover {
        background: rgba(0,0,0,0.08);
        color: rgba(0,0,0,0.9);
      }

      /* ===== Animated SVG Icons for Stat Cards ===== */
      
      /* Clock animation for pending */
      .clock-animated .clock-hands {
        transform-origin: 12px 12px;
        animation: clockTick 4s linear infinite;
      }

      @keyframes clockTick {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
      }

      /* Check mark animation */
      .check-animated .check-path {
        stroke-dasharray: 30;
        stroke-dashoffset: 30;
        animation: checkDraw 1.5s ease-out forwards, checkPulse 3s ease-in-out 1.5s infinite;
      }

      @keyframes checkDraw {
        to { stroke-dashoffset: 0; }
      }

      @keyframes checkPulse {
        0%, 100% { stroke-width: 2; }
        50% { stroke-width: 2.5; }
      }

      /* Coins animation */
      .coins-animated {
        animation: coinsRotate 5s ease-in-out infinite;
      }

      .coins-animated .coin-orbit {
        transform-origin: center;
        animation: orbitPulse 2s ease-in-out infinite;
      }

      @keyframes coinsRotate {
        0%, 100% { transform: rotate(0deg); }
        25% { transform: rotate(-5deg); }
        75% { transform: rotate(5deg); }
      }

      @keyframes orbitPulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.6; }
      }

      /* Header coin animation */
      .coin-animated {
        animation: coinSpin 3s ease-in-out infinite;
      }

      @keyframes coinSpin {
        0%, 100% { transform: rotateY(0deg); }
        50% { transform: rotateY(180deg); }
      }

      /* ===== End Animated SVG Icons ===== */

      /* Modern Copy Toast */
      .copy-toast-modern {
        position: fixed;
        bottom: 2rem;
        left: 50%;
        transform: translateX(-50%) translateY(20px);
        background: linear-gradient(135deg, #22c55e, #16a34a);
        color: white;
        padding: 0.9rem 1.5rem;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 0.6rem;
        box-shadow: 0 15px 40px rgba(34,197,94,0.3);
        z-index: 99999;
        opacity: 0;
        transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
      }

      .copy-toast-modern.show {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
      }

      .copy-toast-modern .toast-check {
        width: 20px;
        height: 20px;
        stroke: white;
      }

      .copy-toast-modern .toast-check path {
        stroke-dasharray: 30;
        stroke-dashoffset: 30;
        animation: toastCheckDraw 0.4s ease-out 0.1s forwards;
      }

      @keyframes toastCheckDraw {
        to { stroke-dashoffset: 0; }
      }

      /* ===== Header Animation ===== */
      .money-animated {
        animation: moneyFloat 3s ease-in-out infinite;
      }

      .money-animated .center-coin {
        animation: coinPulseCenter 2s ease-in-out infinite;
        transform-origin: center;
      }

      .money-animated .left-dot,
      .money-animated .right-dot {
        animation: dotBlink 1.5s ease-in-out infinite;
      }

      .money-animated .right-dot {
        animation-delay: 0.75s;
      }

      @keyframes moneyFloat {
        0%, 100% { transform: translateY(0) rotate(0deg); }
        25% { transform: translateY(-2px) rotate(-2deg); }
        75% { transform: translateY(-2px) rotate(2deg); }
      }

      @keyframes coinPulseCenter {
        0%, 100% { r: 3; }
        50% { r: 3.5; }
      }

      @keyframes dotBlink {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.3; }
      }

      /* Header Stats Wrapper */
      .header-stats-wrapper {
        display: flex;
        align-items: center;
        gap: 1rem;
      }

      .header-stat-item {
        text-align: right;
        padding: 0.5rem 1rem;
        background: rgba(255,255,255,0.03);
        border-radius: 10px;
        border: 1px solid rgba(255,255,255,0.05);
        transition: all 0.3s ease;
      }

      .header-stat-item:hover {
        background: rgba(255,255,255,0.06);
        transform: translateY(-2px);
      }

      .header-stat-item .stat-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: rgba(255,255,255,0.5);
        margin-bottom: 0.2rem;
      }

      .header-stat-item .stat-amount {
        font-size: 1.1rem;
        font-weight: 700;
      }

      .header-stat-item .stat-amount.pending {
        color: #fbbf24;
      }

      .header-stat-item .stat-amount.verified {
        color: #22c55e;
      }

      /* Light theme header stats */
      html.light-theme .header-stat-item {
        background: rgba(0,0,0,0.03);
        border-color: rgba(0,0,0,0.05);
      }

      html.light-theme .header-stat-item .stat-label {
        color: rgba(0,0,0,0.5);
      }

      /* Responsive header stats */
      @media (max-width: 768px) {
        .header-stats-wrapper {
          flex-direction: column;
          gap: 0.5rem;
        }
        .header-stat-item {
          text-align: center;
          width: 100%;
        }
      }

      /* ===== End Header Animation ===== */

      /* ===== Action Button SVG Animations ===== */
      
      /* Save icon animation */
      .save-icon-animated {
        animation: saveFloat 2s ease-in-out infinite;
      }

      @keyframes saveFloat {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-2px); }
      }

      .submit-btn-animated:hover .save-icon-animated {
        animation: savePulse 0.5s ease-in-out;
      }

      @keyframes savePulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.2); }
      }

      /* List icon animation */
      .list-icon-animated {
        animation: listBounce 3s ease-in-out infinite;
      }

      .list-icon-animated .list-line-1 {
        stroke-dasharray: 6;
        stroke-dashoffset: 6;
        animation: lineAppear 1.5s ease-out forwards;
      }

      .list-icon-animated .list-line-2 {
        stroke-dasharray: 6;
        stroke-dashoffset: 6;
        animation: lineAppear 1.5s ease-out 0.3s forwards;
      }

      @keyframes listBounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-2px); }
      }

      @keyframes lineAppear {
        to { stroke-dashoffset: 0; }
      }

      /* Copy icon SVG styling */
      .copy-icon-svg {
        width: 14px;
        height: 14px;
        stroke: rgba(255,255,255,0.5);
        vertical-align: middle;
        margin-left: 0.3rem;
        transition: all 0.2s ease;
      }

      .copy-text:hover .copy-icon-svg {
        stroke: rgba(255,255,255,0.9);
        transform: scale(1.1);
      }

      /* Status icons animation */
      .status-icon-check {
        stroke: currentColor;
      }

      .status-icon-check polyline {
        stroke-dasharray: 30;
        stroke-dashoffset: 30;
        animation: checkAppear 0.5s ease-out forwards;
      }

      @keyframes checkAppear {
        to { stroke-dashoffset: 0; }
      }

      .status-icon-pending {
        stroke: currentColor;
      }

      .status-icon-pending polyline {
        transform-origin: 12px 12px;
        animation: pendingTick 3s linear infinite;
      }

      @keyframes pendingTick {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
      }

      /* Action button SVG styling */
      .action-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
      }

      .action-btn svg {
        transition: transform 0.2s ease;
      }

      .action-btn:hover svg {
        transform: scale(1.15);
      }

      .btn-verify:hover svg {
        animation: verifyPop 0.3s ease;
      }

      @keyframes verifyPop {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.3); }
      }

      .btn-delete:hover svg {
        animation: deleteBounce 0.3s ease;
      }

      @keyframes deleteBounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-3px); }
      }

      .btn-reject:hover svg {
        animation: rejectSpin 0.5s ease;
      }

      @keyframes rejectSpin {
        from { transform: rotate(0deg); }
        to { transform: rotate(-360deg); }
      }

      /* ===== End Action Button SVG Animations ===== */

      /* ===== End Payment Info Section ===== */

      .payment-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
      }

      .payment-stat-card {
        position: relative;
        background: linear-gradient(135deg, rgba(30,41,59,0.8), rgba(15,23,42,0.95));
        border-radius: 20px;
        padding: 1.5rem;
        border: 1px solid rgba(255,255,255,0.08);
        box-shadow: 0 15px 40px rgba(3,7,18,0.5);
        color: #f5f8ff;
        transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        overflow: hidden;
      }

      .payment-stat-card::before {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.05), transparent);
        opacity: 0;
        transition: opacity 0.3s ease;
      }

      .payment-stat-card::after {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: 20px;
        padding: 1px;
        background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent);
        -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
        mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
        -webkit-mask-composite: xor;
        mask-composite: exclude;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.3s ease;
      }

      .payment-stat-card:hover {
        transform: translateY(-6px) scale(1.02);
        box-shadow: 0 25px 50px rgba(0,0,0,0.25), 0 0 0 1px rgba(96,165,250,0.1);
      }

      .payment-stat-card:hover::before,
      .payment-stat-card:hover::after {
        opacity: 1;
      }

      .stat-card-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
      }

      .stat-card-icon {
        width: 52px;
        height: 52px;
        background: linear-gradient(135deg, var(--stat-accent, #3b82f6), var(--stat-accent-end, #8b5cf6));
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        transition: transform 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
      }

      .payment-stat-card:hover .stat-card-icon {
        transform: scale(1.1) rotate(-5deg);
      }

      .stat-card-icon svg {
        width: 26px;
        height: 26px;
        color: white;
        stroke: white;
        animation: iconPulse 2s ease-in-out infinite;
      }

      @keyframes iconPulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.08); }
      }

      .payment-stat-card h3 {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 500;
        color: rgba(255,255,255,0.6);
        letter-spacing: 0.02em;
      }

      .payment-stat-card .stat-value {
        font-size: 2.5rem;
        font-weight: 700;
        margin-top: 0.5rem;
        background: linear-gradient(135deg, var(--stat-accent, #fff), var(--stat-accent-end, #fff));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        animation: numberGlow 3s ease-in-out infinite;
      }

      @keyframes numberGlow {
        0%, 100% { filter: brightness(1); }
        50% { filter: brightness(1.2); }
      }

      .payment-stat-card .stat-money {
        font-size: 1.1rem;
        color: rgba(255,255,255,0.6);
        margin-top: 0.5rem;
      }

      /* Color variants for stat cards */
      .payment-stat-card.pending { --stat-accent: #fbbf24; --stat-accent-end: #fcd34d; }
      .payment-stat-card.verified { --stat-accent: #22c55e; --stat-accent-end: #4ade80; }
      .payment-stat-card.total { --stat-accent: #8b5cf6; --stat-accent-end: #a855f7; }

      /* Floating particles animation */
      .stat-particles {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        overflow: hidden;
        pointer-events: none;
        opacity: 0.5;
      }

      .stat-particles span {
        position: absolute;
        width: 4px;
        height: 4px;
        background: var(--stat-accent, #3b82f6);
        border-radius: 50%;
        animation: floatUp 4s ease-in-out infinite;
      }

      .stat-particles span:nth-child(1) { left: 20%; animation-delay: 0s; }
      .stat-particles span:nth-child(2) { left: 40%; animation-delay: 1s; }
      .stat-particles span:nth-child(3) { left: 60%; animation-delay: 2s; }
      .stat-particles span:nth-child(4) { left: 80%; animation-delay: 3s; }

      @keyframes floatUp {
        0% { transform: translateY(100px) scale(0); opacity: 0; }
        50% { opacity: 0.6; }
        100% { transform: translateY(-20px) scale(1); opacity: 0; }
      }

      /* Light theme overrides */
      @media (prefers-color-scheme: light) {
        .payment-stat-card {
          background: rgba(255,255,255,0.8) !important;
          border: 1px solid rgba(0,0,0,0.06) !important;
          box-shadow: 0 4px 20px rgba(0,0,0,0.08) !important;
        }
        .payment-stat-card h3 {
          color: rgba(0,0,0,0.5) !important;
        }
      }

      html.light-theme .payment-stat-card {
        background: rgba(255,255,255,0.8) !important;
        border: 1px solid rgba(0,0,0,0.06) !important;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08) !important;
      }

      html.light-theme .payment-stat-card h3 {
        color: rgba(0,0,0,0.5) !important;
      }

      /* Room Payment Summary */
      .room-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
      }

      .room-card {
        position: relative;
        background: linear-gradient(135deg, rgba(30,41,59,0.8), rgba(15,23,42,0.95));
        border-radius: 20px;
        padding: 1.5rem;
        border: 1px solid rgba(255,255,255,0.08);
        transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        cursor: pointer;
        overflow: hidden;
      }

      .room-card::before {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.05), transparent);
        opacity: 0;
        transition: opacity 0.3s ease;
      }

      .room-card:hover {
        transform: translateY(-6px) scale(1.02);
        box-shadow: 0 25px 50px rgba(0,0,0,0.25), 0 0 0 1px rgba(96,165,250,0.1);
      }

      .room-card:hover::before {
        opacity: 1;
      }

      .room-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid rgba(255,255,255,0.08);
        position: relative;
        z-index: 1;
      }

      .room-number {
        font-size: 1.4rem;
        font-weight: 700;
        color: #60a5fa;
      }

      .payment-count {
        display: flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.3rem 0.7rem;
        background: rgba(34,197,94,0.15);
        color: #22c55e;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 600;
      }

      .room-card-body {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        position: relative;
        z-index: 1;
      }

      .room-tenant {
        font-size: 0.95rem;
        color: rgba(255,255,255,0.8);
      }

      .room-stats {
        display: flex;
        gap: 1rem;
        margin-top: 0.5rem;
      }

      .room-stat {
        flex: 1;
      }

      .room-stat-label {
        font-size: 0.75rem;
        color: rgba(255,255,255,0.5);
        margin-bottom: 0.2rem;
      }

      .room-stat-value {
        font-size: 1rem;
        font-weight: 600;
      }

      .room-stat-value.verified { color: #22c55e; }
      .room-stat-value.pending { color: #fbbf24; }

      .room-last-payment {
        font-size: 0.8rem;
        color: rgba(255,255,255,0.5);
        margin-top: 0.5rem;
        position: relative;
        z-index: 1;
      }

      /* Light theme overrides for room cards */
      @media (prefers-color-scheme: light) {
        .room-card {
          background: rgba(255,255,255,0.8) !important;
          border: 1px solid rgba(0,0,0,0.06) !important;
          box-shadow: 0 4px 20px rgba(0,0,0,0.08) !important;
        }
        .room-card:hover {
          box-shadow: 0 4px 30px rgba(0,0,0,0.12) !important;
        }
        .room-number { color: #2563eb !important; }
        .room-tenant { color: #374151 !important; }
        .room-stat-label { color: #6b7280 !important; }
        .room-last-payment { color: #9ca3af !important; }
      }

      html.light-theme .room-card {
        background: rgba(255,255,255,0.8) !important;
        border: 1px solid rgba(0,0,0,0.06) !important;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08) !important;
      }

      html.light-theme .room-card:hover {
        box-shadow: 0 4px 30px rgba(0,0,0,0.12) !important;
      }

      html.light-theme .room-number { color: #2563eb !important; }
      html.light-theme .room-tenant { color: #374151 !important; }
      html.light-theme .room-stat-label { color: #6b7280 !important; }
      html.light-theme .room-last-payment { color: #9ca3af !important; }
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

      /* Bank Info Styles */
      .bank-info-section {
        background: linear-gradient(135deg, rgba(30, 41, 59, 0.9) 0%, rgba(51, 65, 85, 0.9) 100%);
        border: 1px solid rgba(59, 130, 246, 0.3) !important;
        border-radius: 20px;
        padding: 1.5rem !important;
        margin-bottom: 1.5rem;
      }

      .bank-info-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem 0;
        border-bottom: 1px solid rgba(255,255,255,0.08);
      }

      .bank-info-item:last-child {
        border-bottom: none;
      }

      .bank-info-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
      }

      .bank-info-icon.bank {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
      }

      .bank-info-icon.account {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
      }

      .bank-info-icon.number {
        background: linear-gradient(135deg, #10b981, #059669);
      }

      .bank-info-icon.promptpay {
        background: linear-gradient(135deg, #f59e0b, #d97706);
      }

      .bank-info-icon svg {
        width: 24px;
        height: 24px;
        stroke: white;
        stroke-width: 2;
        fill: none;
      }

      .bank-info-content {
        flex: 1;
      }

      .bank-info-label {
        font-size: 0.8rem;
        color: #94a3b8;
        margin-bottom: 4px;
        font-weight: 500;
      }

      .bank-info-value {
        font-size: 1.05rem;
        font-weight: 600;
        color: #f8fafc;
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }

      .copy-text {
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.25rem 0.5rem;
        border-radius: 6px;
      }

      .copy-text:hover {
        color: #60a5fa;
        background: rgba(96, 165, 250, 0.1);
      }

      .copy-text:active {
        transform: scale(0.98);
      }

      .copy-icon {
        font-size: 0.9rem;
        opacity: 0.7;
      }

      .section-title {
        font-size: 1.15rem;
        font-weight: 700;
        color: #f1f5f9;
        margin-bottom: 1.25rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
      }

      .section-icon {
        display: inline-flex;
        width: 24px;
        height: 24px;
        align-items: center;
        justify-content: center;
      }

      .section-icon svg {
        width: 20px;
        height: 20px;
        stroke: currentColor;
        stroke-width: 2;
        fill: none;
      }

      /* Copy toast notification */
      .copy-toast {
        position: fixed;
        bottom: 100px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(16, 185, 129, 0.95);
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 30px;
        font-size: 0.9rem;
        font-weight: 600;
        z-index: 10000;
        animation: toastIn 0.3s ease, toastOut 0.3s ease 1.7s forwards;
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
      }

      @keyframes toastIn {
        from { opacity: 0; transform: translateX(-50%) translateY(20px); }
        to { opacity: 1; transform: translateX(-50%) translateY(0); }
      }

      @keyframes toastOut {
        from { opacity: 1; transform: translateX(-50%) translateY(0); }
        to { opacity: 0; transform: translateX(-50%) translateY(-20px); }
      }

      /* Light theme for bank info */
      @media (prefers-color-scheme: light) {
        .bank-info-section {
          background: rgba(255, 255, 255, 0.8) !important;
          border: 1px solid rgba(0,0,0,0.06) !important;
        }
        .bank-info-label {
          color: #6b7280 !important;
        }
        .bank-info-value {
          color: #1f2937 !important;
        }
      }

      html.light-theme .bank-info-section {
        background: rgba(255, 255, 255, 0.8) !important;
        border: 1px solid rgba(0,0,0,0.06) !important;
      }

      html.light-theme .bank-info-label {
        color: #6b7280 !important;
      }

      html.light-theme .bank-info-value {
        color: #1f2937 !important;
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
          <section class="manage-panel fade-in-up">
            <div class="section-header">
              <div class="title-block">
                <div class="panel-icon-modern">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="money-animated" xmlns="http://www.w3.org/2000/svg">
                    <rect x="2" y="6" width="20" height="12" rx="2" stroke="white"/>
                    <circle cx="12" cy="12" r="3" class="center-coin"/>
                    <circle cx="6" cy="12" r="1" fill="white" class="left-dot"/>
                    <circle cx="18" cy="12" r="1" fill="white" class="right-dot"/>
                  </svg>
                </div>
                <div>
                  <h1 style="margin:0;font-size:1.6rem;line-height:1;">จัดการการชำระเงิน</h1>
                  <p style="color:#94a3b8;margin-top:0.25rem;">ตรวจสอบและยืนยันการชำระเงินของผู้เช่า</p>
                </div>
              </div>
              <div class="header-stats-wrapper">
                <div class="header-stat-item pending-stat">
                  <div class="stat-label">รอตรวจสอบ</div>
                  <div class="stat-amount pending">฿<?php echo number_format($stats['total_pending']); ?></div>
                </div>
                <div class="header-stat-item verified-stat">
                  <div class="stat-label">ตรวจสอบแล้ว</div>
                  <div class="stat-amount verified">฿<?php echo number_format($stats['total_verified']); ?></div>
                </div>
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
              <div class="payment-stat-card pending fade-in-up" style="animation-delay: 0s;">
                <div class="stat-particles">
                  <span></span><span></span><span></span><span></span>
                </div>
                <div class="stat-card-header">
                  <div class="stat-card-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="clock-animated">
                      <circle cx="12" cy="12" r="10"/>
                      <polyline points="12 6 12 12 16 14" class="clock-hands"/>
                    </svg>
                  </div>
                  <h3>รอตรวจสอบ</h3>
                </div>
                <div class="stat-value"><?php echo number_format($stats['pending']); ?></div>
                <div class="stat-money">฿<?php echo number_format($stats['total_pending']); ?></div>
              </div>

              <div class="payment-stat-card verified fade-in-up" style="animation-delay: 0.1s;">
                <div class="stat-particles">
                  <span></span><span></span><span></span><span></span>
                </div>
                <div class="stat-card-header">
                  <div class="stat-card-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="check-animated">
                      <polyline points="20 6 9 17 4 12" class="check-path"/>
                    </svg>
                  </div>
                  <h3>ตรวจสอบแล้ว</h3>
                </div>
                <div class="stat-value"><?php echo number_format($stats['verified']); ?></div>
                <div class="stat-money">฿<?php echo number_format($stats['total_verified']); ?></div>
              </div>

              <div class="payment-stat-card total fade-in-up" style="animation-delay: 0.2s;">
                <div class="stat-particles">
                  <span></span><span></span><span></span><span></span>
                </div>
                <div class="stat-card-header">
                  <div class="stat-card-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="coins-animated">
                      <circle cx="8" cy="8" r="6"/>
                      <path d="M18.09 10.37A6 6 0 1 1 10.34 18" class="coin-orbit"/>
                      <path d="M7 6h2v4"/>
                      <path d="M15 12h2v4"/>
                    </svg>
                  </div>
                  <h3>รวมทั้งหมด</h3>
                </div>
                <div class="stat-value"><?php echo number_format($stats['pending'] + $stats['verified']); ?></div>
                <div class="stat-money">฿<?php echo number_format($stats['total_pending'] + $stats['total_verified']); ?></div>
              </div>
            </div>
          </section>

          <!-- Bank Payment Destination Section -->
          <?php if (!empty($settings['bank_name']) || !empty($settings['promptpay_number'])): ?>
          <!-- <section class="manage-panel">
            <div class="section-title">
              <span class="section-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                </svg>
              </span>
              ปลายทางการชำระเงิน
            </div>

            <div class="bank-info-section">
              <?php if (!empty($settings['bank_name'])): ?>
              <div class="bank-info-item">
                <div class="bank-info-icon bank">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 21h18"/><path d="M3 10h18"/><path d="M5 6l7-3 7 3"/><path d="M4 10v11"/><path d="M20 10v11"/><path d="M8 14v3"/><path d="M12 14v3"/><path d="M16 14v3"/>
                  </svg>
                </div>
                <div class="bank-info-content">
                  <div class="bank-info-label">ธนาคาร</div>
                  <div class="bank-info-value"><?php echo htmlspecialchars($settings['bank_name']); ?></div>
                </div>
              </div>
              <?php endif; ?>

              <?php if (!empty($settings['bank_account_name'])): ?>
              <div class="bank-info-item">
                <div class="bank-info-icon account">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                  </svg>
                </div>
                <div class="bank-info-content">
                  <div class="bank-info-label">ชื่อบัญชี</div>
                  <div class="bank-info-value"><?php echo htmlspecialchars($settings['bank_account_name']); ?></div>
                </div>
              </div>
              <?php endif; ?>

              <?php if (!empty($settings['bank_account_number'])): ?>
              <div class="bank-info-item">
                <div class="bank-info-icon number">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="4" width="20" height="16" rx="2"/><path d="M7 15h0M2 9.5h20"/>
                  </svg>
                </div>
                <div class="bank-info-content">
                  <div class="bank-info-label">เลขบัญชี</div>
                  <div class="bank-info-value copy-text" onclick="copyToClipboard('<?php echo htmlspecialchars($settings['bank_account_number']); ?>')">
                    <?php echo htmlspecialchars($settings['bank_account_number']); ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="copy-icon-svg"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                  </div>
                </div>
              </div>
              <?php endif; ?>

              <?php if (!empty($settings['promptpay_number'])): ?>
              <div class="bank-info-item">
                <div class="bank-info-icon promptpay">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/>
                  </svg>
                </div>
                <div class="bank-info-content">
                  <div class="bank-info-label">พร้อมเพย์</div>
                  <div class="bank-info-value copy-text" onclick="copyToClipboard('<?php echo htmlspecialchars($settings['promptpay_number']); ?>')">
                    <?php echo htmlspecialchars($settings['promptpay_number']); ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="copy-icon-svg"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                  </div>
                </div>
              </div>
              <?php endif; ?>
            </div>
          </section> -->
          <?php endif; ?>

          <!-- Room Payment Summary -->
          <section class="manage-panel">
            <div class="section-header">
              <div>
                <h2 style="margin:0;">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 8px;"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                  สรุปการชำระเงินแยกตามห้อง
                </h2>
                <p style="color:#94a3b8;margin-top:0.2rem;">แสดงจำนวนครั้งและยอดชำระของแต่ละห้อง (เฉพาะห้องที่มีผู้เช่า)</p>
              </div>
            </div>
            <?php if (empty($roomPaymentSummary)): ?>
              <p style="text-align:center;color:#64748b;padding:2rem;">ยังไม่มีข้อมูลการชำระเงิน</p>
            <?php else: ?>
              <div class="room-summary-grid">
                <?php foreach ($roomPaymentSummary as $room): ?>
                  <div class="room-card" onclick="filterByRoom('<?php echo htmlspecialchars($room['room_number']); ?>')">
                    <div class="room-card-header">
                      <span class="room-number">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        ห้อง <?php echo htmlspecialchars($room['room_number']); ?>
                      </span>
                      <span class="payment-count">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        <?php echo (int)$room['payment_count']; ?> ครั้ง
                      </span>
                    </div>
                    <div class="room-card-body">
                      <div class="room-tenant">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <?php echo htmlspecialchars($room['tnt_name'] ?? 'ไม่ระบุ'); ?>
                      </div>
                      <div class="room-stats">
                        <div class="room-stat">
                          <div class="room-stat-label">ยอดที่ตรวจสอบแล้ว</div>
                          <div class="room-stat-value verified">฿<?php echo number_format((int)($room['total_verified'] ?? 0)); ?></div>
                        </div>
                        <div class="room-stat">
                          <div class="room-stat-label">รอตรวจสอบ</div>
                          <div class="room-stat-value pending">฿<?php echo number_format((int)($room['total_pending'] ?? 0)); ?></div>
                        </div>
                      </div>
                      <?php if (!empty($room['last_payment_date'])): ?>
                        <div class="room-last-payment">
                          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                          ชำระล่าสุด: <?php echo date('d/m/Y', strtotime($room['last_payment_date'])); ?>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>

          <!-- Toggle button for payment form -->
          <div style="margin:1.5rem 0;">
            <button type="button" id="togglePaymentFormBtn" class="toggle-form-btn" onclick="togglePaymentForm()">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="toggle-icon" id="togglePaymentFormIcon">
                <polyline points="6 9 12 15 18 9"/>
              </svg>
              <span id="togglePaymentFormText">ซ่อนฟอร์ม</span>
            </button>
          </div>

          <!-- Add Payment Form -->
          <section class="manage-panel payment-form-section fade-in-up" style="background:linear-gradient(135deg, rgba(15,23,42,0.95), rgba(2,6,23,0.95)); color:#f8fafc;" id="addPaymentSection">
            <div class="section-header">
              <div class="panel-header">
                <div class="panel-icon-animated add-payment">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="plus-animated">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="16"/>
                    <line x1="8" y1="12" x2="16" y2="12"/>
                  </svg>
                </div>
                <div class="panel-title-content">
                  <h2 style="margin:0; font-size:1.35rem; font-weight:700; color:#f8fafc;">บันทึกการชำระเงินใหม่</h2>
                  <p style="margin-top:0.25rem;color:rgba(255,255,255,0.5); font-size:0.9rem;">เลือกรายการค่าใช้จ่ายและกรอกข้อมูลการชำระ</p>
                </div>
              </div>
            </div>

            <!-- Owner Payment Destination Info - Modern Animated Section -->
            <div class="payment-info-section fade-in-up">
              <div class="payment-info-header">
                <div class="payment-info-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="wallet-animated">
                    <path d="M21 4H3a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h18a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z"/>
                    <path d="M1 10h22"/>
                    <circle cx="18" cy="14" r="2" class="coin-pulse"/>
                  </svg>
                </div>
                <div class="payment-info-title">
                  <h4>ข้อมูลการรับเงินของเจ้าของหอ</h4>
                  <p>ช่องทางการชำระเงินสำหรับผู้เช่า</p>
                </div>
              </div>
              <div class="payment-info-grid">
                <!-- Bank Name Card -->
                <div class="payment-info-card bank-card">
                  <div class="card-icon-wrapper blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-float">
                      <path d="M3 21h18"/>
                      <path d="M5 21V7l7-4 7 4v14"/>
                      <path d="M9 21v-8h6v8"/>
                      <path d="M9 9h6"/>
                    </svg>
                  </div>
                  <div class="card-content">
                    <span class="card-label">ธนาคาร</span>
                    <span class="card-value"><?php echo !empty($settings['bank_name']) ? htmlspecialchars($settings['bank_name']) : '<span class="not-set">ยังไม่ได้ตั้งค่า</span>'; ?></span>
                  </div>
                  <div class="card-glow blue"></div>
                </div>

                <!-- Account Name Card -->
                <div class="payment-info-card account-card">
                  <div class="card-icon-wrapper purple">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-float">
                      <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                      <circle cx="12" cy="7" r="4"/>
                    </svg>
                  </div>
                  <div class="card-content">
                    <span class="card-label">ชื่อบัญชี</span>
                    <span class="card-value"><?php echo !empty($settings['bank_account_name']) ? htmlspecialchars($settings['bank_account_name']) : '<span class="not-set">ยังไม่ได้ตั้งค่า</span>'; ?></span>
                  </div>
                  <div class="card-glow purple"></div>
                </div>

                <!-- Account Number Card -->
                <div class="payment-info-card number-card <?php echo !empty($settings['bank_account_number']) ? 'copyable' : ''; ?>" <?php if (!empty($settings['bank_account_number'])): ?>onclick="copyToClipboard('<?php echo htmlspecialchars($settings['bank_account_number']); ?>')"<?php endif; ?>>
                  <div class="card-icon-wrapper green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-float">
                      <rect x="2" y="5" width="20" height="14" rx="2"/>
                      <line x1="2" y1="10" x2="22" y2="10"/>
                    </svg>
                  </div>
                  <div class="card-content">
                    <span class="card-label">เลขบัญชี</span>
                    <span class="card-value">
                      <?php if (!empty($settings['bank_account_number'])): ?>
                        <?php echo htmlspecialchars($settings['bank_account_number']); ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="copy-icon">
                          <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                          <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                        </svg>
                      <?php else: ?>
                        <span class="not-set">ยังไม่ได้ตั้งค่า</span>
                      <?php endif; ?>
                    </span>
                  </div>
                  <div class="card-glow green"></div>
                </div>

                <!-- PromptPay Card -->
                <div class="payment-info-card promptpay-card <?php echo !empty($settings['promptpay_number']) ? 'copyable' : ''; ?>" <?php if (!empty($settings['promptpay_number'])): ?>onclick="copyToClipboard('<?php echo htmlspecialchars($settings['promptpay_number']); ?>')"<?php endif; ?>>
                  <div class="card-icon-wrapper orange">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-float">
                      <path d="M12 2v4"/>
                      <path d="M12 18v4"/>
                      <path d="m4.93 4.93 2.83 2.83"/>
                      <path d="m16.24 16.24 2.83 2.83"/>
                      <path d="M2 12h4"/>
                      <path d="M18 12h4"/>
                      <path d="m4.93 19.07 2.83-2.83"/>
                      <path d="m16.24 7.76 2.83-2.83"/>
                      <circle cx="12" cy="12" r="4"/>
                    </svg>
                  </div>
                  <div class="card-content">
                    <span class="card-label">พร้อมเพย์</span>
                    <span class="card-value">
                      <?php if (!empty($settings['promptpay_number'])): ?>
                        <?php echo htmlspecialchars($settings['promptpay_number']); ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="copy-icon">
                          <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                          <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                        </svg>
                      <?php else: ?>
                        <span class="not-set">ยังไม่ได้ตั้งค่า</span>
                      <?php endif; ?>
                    </span>
                  </div>
                  <div class="card-glow orange"></div>
                </div>
              </div>
              <!-- Floating particles for background effect -->
              <div class="info-particles">
                <span></span><span></span><span></span><span></span>
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
                <button type="submit" id="submitPaymentBtn" class="btn btn-primary submit-btn-animated" style="padding:0.75rem 2rem;font-size:1rem;background:linear-gradient(135deg,#22c55e,#16a34a) !important;border:none;border-radius:10px;font-weight:600;cursor:pointer;box-shadow:0 4px 12px rgba(34,197,94,0.3);transition:all 0.2s;display:inline-flex;align-items:center;gap:0.5rem;color:#ffffff !important;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 16px rgba(34,197,94,0.4)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 12px rgba(34,197,94,0.3)'">
                  <svg viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="save-icon-animated" style="width:18px;height:18px;stroke:#ffffff !important;">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                    <polyline points="17 21 17 13 7 13 7 21"/>
                    <polyline points="7 3 7 8 15 8"/>
                  </svg>
                  <span style="color:#ffffff !important;">บันทึกการชำระเงิน</span>
                </button>
              </div>
            </form>
          </section>

          <!-- Filter Section -->
          <section class="manage-panel">
            <div class="filter-section">
              <div class="filter-group">
                <label>กรองตามห้อง</label>
                <select id="filterRoom" onchange="applyFilters()">
                  <option value="">ทุกห้อง</option>
                  <?php foreach ($roomPaymentSummary as $room): ?>
                    <option value="<?php echo htmlspecialchars($room['room_number']); ?>" <?php echo $filterRoom === $room['room_number'] ? 'selected' : ''; ?>>
                      ห้อง <?php echo htmlspecialchars($room['room_number']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
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
                  for ($m = 1; $m <= 12; $m++):
                  ?>
                    <option value="<?php echo $m; ?>" <?php echo $filterMonth === (string)$m ? 'selected' : ''; ?>><?php echo $thaiMonths[$m]; ?></option>
                  <?php endfor; ?>
                </select>
              </div>
              <div class="filter-group">
                <label>กรองตามปี</label>
                <select id="filterYear" onchange="applyFilters()">
                  <option value="">ทั้งหมด</option>
                  <?php 
                  $currentYear = (int)date('Y');
                  for ($y = $currentYear - 2; $y <= $currentYear + 1; $y++):
                    $thaiYear = $y + 543;
                  ?>
                    <option value="<?php echo $y; ?>" <?php echo $filterYear === (string)$y ? 'selected' : ''; ?>><?php echo $thaiYear; ?></option>
                  <?php endfor; ?>
                </select>
              </div>
              <div class="filter-group">
                <button type="button" onclick="clearFilters()" style="padding:0.5rem 1rem;background:rgba(148,163,184,0.2);border:1px solid rgba(148,163,184,0.3);color:#94a3b8;border-radius:8px;cursor:pointer;font-size:0.9rem;display:inline-flex;align-items:center;gap:4px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>ล้างตัวกรอง</button>
              </div>
            </div>
            <?php if ($filterRoom !== ''): ?>
              <div style="margin-top:0.75rem;padding:0.75rem 1rem;background:rgba(59,130,246,0.15);border:1px solid rgba(59,130,246,0.3);border-radius:8px;color:#60a5fa;display:flex;align-items:center;gap:0.5rem;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2  2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                กำลังแสดงเฉพาะห้อง <strong><?php echo htmlspecialchars($filterRoom); ?></strong>
                <a href="?<?php echo http_build_query(array_filter(['status' => $filterStatus, 'filter_month' => $filterMonth, 'filter_year' => $filterYear])); ?>" style="margin-left:auto;color:#f87171;text-decoration:none;">✕ ยกเลิก</a>
              </div>
            <?php endif; ?>
          </section>

          <!-- Payments Table -->
          <section class="manage-panel">
            <div class="section-header">
              <div>
                <h2 style="margin:0;display:flex;align-items:center;gap:0.5rem;">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="list-icon-animated" style="width:24px;height:24px;">
                    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                    <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
                    <line x1="9" y1="12" x2="15" y2="12" class="list-line-1"/>
                    <line x1="9" y1="16" x2="15" y2="16" class="list-line-2"/>
                  </svg>
                  รายการการชำระเงิน
                </h2>
                <p style="color:#94a3b8;margin-top:0.2rem;">พบ <?php echo count($payments); ?> รายการ<?php echo $filterRoom !== '' ? ' (ห้อง ' . htmlspecialchars($filterRoom) . ')' : ''; ?></p>
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
                              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:3px;"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>ดูหลักฐาน
                            </span>
                          <?php else: ?>
                            <span style="color:#64748b;">-</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php 
                          $statusClass = $pay['pay_status'] === '1' ? 'status-verified' : 'status-pending';
                          $statusText = $statusMap[$pay['pay_status']] ?? 'ไม่ทราบ';
                          ?>
                          <span class="status-badge <?php echo $statusClass; ?>">
                            <?php if ($pay['pay_status'] === '1'): ?>
                              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="status-icon-check" style="width:14px;height:14px;"><polyline points="20 6 9 17 4 12"/></svg>
                            <?php else: ?>
                              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="status-icon-pending" style="width:14px;height:14px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <?php endif; ?>
                            <?php echo $statusText; ?>
                          </span>
                        </td>
                        <td>
                          <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                            <?php if ($pay['pay_status'] === '0'): ?>
                              <button type="button" class="action-btn btn-verify" onclick="updatePaymentStatus(<?php echo (int)$pay['pay_id']; ?>, '1', <?php echo (int)$pay['exp_id']; ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;"><polyline points="20 6 9 17 4 12"/></svg> ยืนยัน</button>
                            <?php else: ?>
                              <button type="button" class="action-btn btn-reject" onclick="updatePaymentStatus(<?php echo (int)$pay['pay_id']; ?>, '0', <?php echo (int)$pay['exp_id']; ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg> ยกเลิก</button>
                            <?php endif; ?>
                            <button type="button" class="action-btn btn-delete" onclick="deletePayment(<?php echo (int)$pay['pay_id']; ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> ลบ</button>
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
          <h3 class="modal-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;vertical-align:-4px;margin-right:6px;"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>หลักฐานการชำระเงิน</h3>
          <button class="modal-close" onclick="closeProofModal()">×</button>
        </div>
        <div class="modal-body" id="proofModalBody" style="text-align:center;">
          <!-- Content will be loaded here -->
        </div>
      </div>
    </div>

    <script src="/dormitory_management/Public/Assets/Javascript/animate-ui.js"></script>
    <script src="/dormitory_management/Public/Assets/Javascript/confirm-modal.js"></script>
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
        const btn = document.getElementById('togglePaymentFormBtn');
        const text = document.getElementById('togglePaymentFormText');
        
        if (section.style.display === 'none') {
          section.style.display = 'block';
          btn.classList.remove('collapsed');
          text.textContent = 'ซ่อนฟอร์ม';
          // Add slide down animation
          section.style.opacity = '0';
          section.style.transform = 'translateY(-10px)';
          requestAnimationFrame(() => {
            section.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            section.style.opacity = '1';
            section.style.transform = 'translateY(0)';
          });
        } else {
          section.style.opacity = '0';
          section.style.transform = 'translateY(-10px)';
          setTimeout(() => {
            section.style.display = 'none';
            btn.classList.add('collapsed');
            text.textContent = 'แสดงฟอร์ม';
          }, 300);
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
        const room = document.getElementById('filterRoom').value;
        const status = document.getElementById('filterStatus').value;
        const month = document.getElementById('filterMonth').value;
        const year = document.getElementById('filterYear').value;
        
        let url = window.location.pathname + '?';
        const params = [];
        if (room !== '') params.push('room=' + encodeURIComponent(room));
        if (status !== '') params.push('status=' + status);
        if (month !== '') params.push('filter_month=' + month);
        if (year !== '') params.push('filter_year=' + year);
        
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
        const path = '/dormitory_management/Public/Assets/Images/Payments/' + filename;
        
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

      // Copy to clipboard function with animated toast
      function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
          // Show animated toast notification
          const toast = document.createElement('div');
          toast.className = 'copy-toast-modern';
          toast.innerHTML = `
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="toast-check">
              <path d="M20 6L9 17l-5-5"/>
            </svg>
            <span>คัดลอกสำเร็จ!</span>
          `;
          document.body.appendChild(toast);
          
          // Trigger animation
          requestAnimationFrame(() => {
            toast.classList.add('show');
          });
          
          setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
          }, 2000);
        }).catch(function(err) {
          console.error('Failed to copy: ', err);
        });
      }

      // Filter by room - redirect with room parameter and scroll anchor
      function filterByRoom(roomNumber) {
        const url = new URL(window.location.href);
        url.searchParams.set('room', roomNumber);
        // Clear other filters when clicking room card
        url.searchParams.delete('status');
        url.searchParams.delete('month');
        url.hash = 'paymentsTable';
        window.location.href = url.toString();
      }

      // Auto scroll to table if room filter is active
      document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('room') && urlParams.get('room') !== '') {
          setTimeout(function() {
            const table = document.getElementById('paymentsTable');
            if (table) {
              table.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
          }, 100);
        }
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
    <script>
      // Small UI animations for payments page
      document.addEventListener('DOMContentLoaded', function() {
        // animate coins
        const coin = document.querySelector('.coin-animated');
        if (coin) {
          coin.classList.add('spin');
          setTimeout(() => coin.classList.remove('spin'), 8000);
        }

        // animate stat cards
        const statCards = document.querySelectorAll('.payment-stat-card, .room-card');
        statCards.forEach((card, i) => {
          card.style.animation = 'fadeInUp 0.6s cubic-bezier(.16,1,.3,1) forwards';
          card.style.animationDelay = (i * 0.08) + 's';
        });
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
