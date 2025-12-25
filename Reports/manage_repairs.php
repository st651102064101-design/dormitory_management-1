<?php
declare(strict_types=1);
session_start();

// ตั้ง timezone เป็น กรุงเทพ
date_default_timezone_set('Asia/Bangkok');

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

// รับค่า sort จาก query parameter
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$orderBy = 'r.repair_date DESC, r.repair_id DESC';

switch ($sortBy) {
  case 'oldest':
    $orderBy = 'r.repair_date ASC, r.repair_id ASC';
    break;
  case 'room_number':
    $orderBy = 'rm.room_number ASC';
    break;
  case 'newest':
  default:
    $orderBy = 'r.repair_date DESC, r.repair_id DESC';
}

// ตรวจสอบว่ามีคอลัมน์นัดหมายหรือยัง
$hasScheduleColumns = false;
try {
    $checkColumn = $pdo->query("SHOW COLUMNS FROM repair LIKE 'scheduled_date'");
    $hasScheduleColumns = $checkColumn->rowCount() > 0;
} catch (Exception $e) {}

// รายการการแจ้งซ่อม
$scheduleFields = $hasScheduleColumns ? ", r.scheduled_date, r.scheduled_time_start, r.scheduled_time_end, r.technician_name, r.technician_phone, r.schedule_note" : "";
$repairStmt = $pdo->query("SELECT r.*, c.ctr_id, t.tnt_name, t.tnt_phone, rm.room_number $scheduleFields
  FROM repair r
  LEFT JOIN contract c ON r.ctr_id = c.ctr_id
  LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
  LEFT JOIN room rm ON c.room_id = rm.room_id
  ORDER BY $orderBy");
$repairs = $repairStmt->fetchAll(PDO::FETCH_ASSOC);

// รายการสัญญาสำหรับเลือกห้อง/ผู้เช่า (แสดงแค่สัญญาที่ใช้งาน และไม่มีการซ่อมในสถานะ 0 หรือ 1)
$contracts = $pdo->query("
  SELECT c.ctr_id, c.ctr_status, t.tnt_name, rm.room_number
  FROM contract c
  LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
  LEFT JOIN room rm ON c.room_id = rm.room_id
  WHERE c.ctr_status = '0'
    AND c.ctr_id NOT IN (SELECT DISTINCT ctr_id FROM repair WHERE repair_status IN ('0', '1'))
  ORDER BY rm.room_number
")->fetchAll(PDO::FETCH_ASSOC);

$statusMap = [
  '0' => 'รอซ่อม',
  '1' => 'กำลังซ่อม',
  '2' => 'ซ่อมเสร็จแล้ว',
  '3' => 'ยกเลิก',
];
$statusColors = [
  '0' => '#f97316',
  '1' => '#60a5fa',
  '2' => '#22c55e',
  '3' => '#ef4444',
];

$stats = [
  'pending' => 0,
  'inprogress' => 0,
  'done' => 0,
  'cancelled' => 0,
];
foreach ($repairs as $r) {
  if (($r['repair_status'] ?? '') === '0') $stats['pending']++;
  elseif (($r['repair_status'] ?? '') === '1') $stats['inprogress']++;
  elseif (($r['repair_status'] ?? '') === '2') $stats['done']++;
  elseif (($r['repair_status'] ?? '') === '3') $stats['cancelled']++;
}

function relativeTimeInfo(?string $dateTimeStr): array {
  if (!$dateTimeStr) return ['label' => '-', 'class' => ''];
  try {
    $tz = new DateTimeZone('Asia/Bangkok');
    $target = new DateTime($dateTimeStr, $tz);
    $now = new DateTime('now', $tz);
  } catch (Exception $e) {
    return ['label' => '-', 'class' => ''];
  }

  $diff = $now->getTimestamp() - $target->getTimestamp();
  if ($diff < 0) return ['label' => 'เร็วๆ นี้', 'class' => 'time-neutral'];

  if ($diff < 60) {
    $sec = max(1, $diff);
    return ['label' => $sec . ' วินาทีที่แล้ว', 'class' => 'time-fresh'];
  }
  if ($diff < 3600) {
    $min = (int)floor($diff / 60);
    return ['label' => $min . ' นาทีที่แล้ว', 'class' => 'time-fresh'];
  }
  if ($diff < 86400) {
    $hrs = (int)floor($diff / 3600);
    return ['label' => $hrs . ' ชม.ที่แล้ว', 'class' => 'time-fresh'];
  }

  $days = (int)floor($diff / 86400);
  if ($days === 1) return ['label' => 'เมื่อวาน', 'class' => 'time-warning'];
  if ($days === 2) return ['label' => 'เมื่อวานซืน', 'class' => 'time-warning'];
  if ($days < 4) return ['label' => $days . ' วันที่แล้ว', 'class' => 'time-warning'];

  if ($days < 30) return ['label' => $days . ' วันที่แล้ว', 'class' => 'time-danger'];

  $months = (int)floor($days / 30);
  if ($months < 12) return ['label' => $months . ' เดือนที่แล้ว', 'class' => 'time-danger'];

  $years = (int)floor($days / 365);
  return ['label' => $years . ' ปีที่แล้ว', 'class' => 'time-danger'];
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

// Detect if theme is light (white or very light color)
$isLightTheme = false;
if ($themeColor) {
    $color = strtolower(trim($themeColor));
    // Check for common light color values
    if (in_array($color, ['#fff', '#ffffff', '#fafafa', '#f8fafc', '#f1f5f9', '#e2e8f0', 'white', 'rgb(255,255,255)', 'rgb(255, 255, 255)'])) {
        $isLightTheme = true;
    }
    // Also check if it's a hex color with high brightness
    if (preg_match('/^#([a-f0-9]{6}|[a-f0-9]{3})$/i', $color)) {
        $hex = ltrim($color, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        // Calculate perceived brightness (0-255)
        $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
        if ($brightness > 200) {
            $isLightTheme = true;
        }
    }
}
$lightThemeClass = $isLightTheme ? 'light-theme' : '';
?>
<!doctype html>
<html lang="th" class="<?php echo $lightThemeClass; ?>">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - จัดการการแจ้งซ่อม</title>
    <link rel="icon" type="image/jpeg" href="..//Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="..//Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="..//Assets/Css/main.css" />
    <link rel="stylesheet" href="..//Assets/Css/confirm-modal.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4/dist/style.css" />
    <link rel="stylesheet" href="..//Assets/Css/datatable-modern.css" />
    <style>
      :root {
        --theme-bg-color: <?php echo $themeColor; ?>;
      }
      
      /* ===== Apple-Style Modern Design with Animations ===== */
      
      /* Disable animate-ui modal overlays on this page */
      .animate-ui-modal, .animate-ui-modal-overlay { display:none !important; visibility:hidden !important; opacity:0 !important; }
      
      /* Modern Stats Cards Grid */
      .repair-stats { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); 
        gap: 1.25rem; 
        margin-top: 1.5rem; 
      }
      
      /* Animated Stats Cards - Apple Style */
      .repair-stat-card { 
        background: rgba(255,255,255,0.02);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255,255,255,0.06);
        border-radius: 20px;
        padding: 1.5rem;
        position: relative;
        overflow: hidden;
        transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        cursor: default;
      }
      
      .repair-stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--stat-accent, #3b82f6), var(--stat-accent-end, #8b5cf6));
        opacity: 0;
        transition: opacity 0.3s ease;
      }
      
      .repair-stat-card::after {
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
      
      .repair-stat-card:hover {
        transform: translateY(-6px) scale(1.02);
        box-shadow: 0 25px 50px rgba(0,0,0,0.25), 0 0 0 1px rgba(96,165,250,0.1);
      }
      
      .repair-stat-card:hover::before,
      .repair-stat-card:hover::after {
        opacity: 1;
      }
      
      /* Stat Card Content */
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
      
      .repair-stat-card:hover .stat-card-icon {
        transform: scale(1.1) rotate(-5deg);
      }
      
      .stat-card-icon svg {
        width: 26px;
        height: 26px;
        color: white;
        stroke: white;
      }
      
      /* Animated SVG Icons */
      .stat-card-icon svg {
        animation: iconPulse 2s ease-in-out infinite;
      }
      
      @keyframes iconPulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.08); }
      }
      
      .repair-stat-card h3 { 
        margin: 0; 
        font-size: 0.95rem; 
        font-weight: 500;
        color: rgba(255,255,255,0.6); 
        letter-spacing: 0.02em;
      }
      
      .repair-stat-card .stat-number { 
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
      
      /* Stat Card Color Variants */
      .repair-stat-card.pending { --stat-accent: #f97316; --stat-accent-end: #fb923c; }
      .repair-stat-card.inprogress { --stat-accent: #3b82f6; --stat-accent-end: #60a5fa; }
      .repair-stat-card.done { --stat-accent: #22c55e; --stat-accent-end: #4ade80; }
      
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
      
      /* Light mode override - detect when theme is light (#ffffff or similar light colors) */
      @media (prefers-color-scheme: light) {
        .repair-stat-card {
          background: rgba(255,255,255,0.8) !important;
          border: 1px solid rgba(0,0,0,0.06) !important;
          box-shadow: 0 4px 20px rgba(0,0,0,0.08) !important;
        }
        .repair-stat-card h3 {
          color: rgba(0,0,0,0.5) !important;
        }
      }
      
      /* JavaScript-detected light theme class */
      html.light-theme .repair-stat-card {
        background: rgba(255,255,255,0.8) !important;
        border: 1px solid rgba(0,0,0,0.06) !important;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08) !important;
      }
      
      html.light-theme .repair-stat-card h3 {
        color: rgba(0,0,0,0.5) !important;
      }
      
      /* Fallback: Also use detection by CSS variable value */
      /* This will work if --theme-bg-color contains light color text */
      html[style*="--theme-bg-color: #fff"] .repair-stat-card,
      html[style*="--theme-bg-color: #FFF"] .repair-stat-card,
      html[style*="--theme-bg-color: #ffffff"] .repair-stat-card,
      html[style*="--theme-bg-color: #FFFFFF"] .repair-stat-card,
      html[style*="--theme-bg-color: rgb(255, 255, 255)"] .repair-stat-card {
        background: rgba(255,255,255,0.8) !important;
        border: 1px solid rgba(0,0,0,0.06) !important;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08) !important;
      }
      
      html[style*="--theme-bg-color: #fff"] .repair-stat-card h3,
      html[style*="--theme-bg-color: #FFF"] .repair-stat-card h3,
      html[style*="--theme-bg-color: #ffffff"] .repair-stat-card h3,
      html[style*="--theme-bg-color: #FFFFFF"] .repair-stat-card h3,
      html[style*="--theme-bg-color: rgb(255, 255, 255)"] .repair-stat-card h3 {
        color: rgba(0,0,0,0.5) !important;
      }
      
      /* Modern Form Styling */
      .repair-form { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); 
        gap: 1.25rem; 
        margin-top: 1.5rem; 
      }
      
      .form-group {
        position: relative;
      }
      
      .repair-form label { 
        font-weight: 600; 
        color: rgba(255,255,255,0.7); 
        margin-bottom: 0.5rem; 
        display: block; 
        font-size: 0.9rem;
        letter-spacing: 0.02em;
      }
      
      .repair-form input, 
      .repair-form select, 
      .repair-form textarea { 
        width: 100%; 
        padding: 0.85rem 1rem; 
        border-radius: 12px; 
        border: 1px solid rgba(255,255,255,0.1); 
        background: rgba(255,255,255,0.05);
        backdrop-filter: blur(10px);
        color: #f8fafc; 
        font-size: 0.95rem;
        transition: all 0.3s ease;
      }
      
      .repair-form input:focus, 
      .repair-form select:focus, 
      .repair-form textarea:focus {
        outline: none;
        border-color: rgba(96,165,250,0.5);
        box-shadow: 0 0 0 3px rgba(96,165,250,0.1), 0 4px 20px rgba(0,0,0,0.1);
      }
      
      .repair-form textarea { min-height: 120px; resize: vertical; }
      
      /* Light theme overrides for form inputs */
      @media (prefers-color-scheme: light) {
        .repair-form input,
        .repair-form select,
        .repair-form textarea {
          background: rgba(255,255,255,0.9) !important;
          color: #1f2937 !important;
          border: 1px solid rgba(0,0,0,0.1) !important;
        }
        .repair-form label {
          color: rgba(0,0,0,0.6) !important;
        }
      }
      
      /* JavaScript-detected light theme class */
      html.light-theme .repair-form input,
      html.light-theme .repair-form select,
      html.light-theme .repair-form textarea {
        background: rgba(255,255,255,0.9) !important;
        color: #1f2937 !important;
        border: 1px solid rgba(0,0,0,0.1) !important;
      }
      
      html.light-theme .repair-form label {
        color: rgba(0,0,0,0.6) !important;
      }
      
      .repair-form-actions { 
        grid-column: 1 / -1; 
        display: flex; 
        gap: 1rem; 
        margin-top: 0.5rem;
      }
      
      /* Modern Action Buttons */
      .btn-modern {
        padding: 0.9rem 1.75rem;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.95rem;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        border: none;
        position: relative;
        overflow: hidden;
      }
      
      .btn-modern::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(255,255,255,0.2), transparent);
        opacity: 0;
        transition: opacity 0.3s ease;
      }
      
      .btn-modern:hover::before {
        opacity: 1;
      }
      
      .btn-modern:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 25px rgba(0,0,0,0.25);
      }
      
      .btn-modern:active {
        transform: translateY(-1px);
      }
      
      .btn-primary {
        background: linear-gradient(135deg, #f97316, #fb923c);
        color: white;
      }
      
      .btn-secondary {
        background: rgba(255,255,255,0.1);
        color: rgba(255,255,255,0.8);
        border: 1px solid rgba(255,255,255,0.1);
      }
      
      .btn-secondary:hover {
        background: rgba(255,255,255,0.15);
        color: white;
      }
      
      /* Status Badge Modern */
      .status-badge { 
        display: inline-flex; 
        align-items: center; 
        justify-content: center; 
        min-width: 100px; 
        padding: 0.4rem 1rem; 
        border-radius: 100px; 
        font-weight: 600; 
        font-size: 0.85rem;
        color: #fff;
        position: relative;
        overflow: hidden;
      }
      
      .status-badge::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(255,255,255,0.2), transparent);
      }
      
      .crud-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
      
      /* Modern Panel Styling */
      .reports-page .manage-panel { 
        background: rgba(15,23,42,0.6);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255,255,255,0.06);
        border-radius: 24px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.15);
        padding: 2rem;
        margin-top: 1.5rem;
        position: relative;
        overflow: hidden;
        animation: fadeInUp 0.6s ease forwards;
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
      
      .reports-page .manage-panel:nth-child(1) { animation-delay: 0s; }
      .reports-page .manage-panel:nth-child(2) { animation-delay: 0.1s; }
      .reports-page .manage-panel:nth-child(3) { animation-delay: 0.2s; }
      
      .reports-page .manage-panel:first-of-type { margin-top: 0; }
      
      /* Panel Header Modern */
      .panel-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
      }
      
      .panel-icon {
        width: 56px;
        height: 56px;
        background: linear-gradient(135deg, var(--panel-accent, #3b82f6), var(--panel-accent-end, #8b5cf6));
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 8px 20px rgba(0,0,0,0.15);
      }
      
      .panel-icon svg {
        width: 28px;
        height: 28px;
        color: white;
        stroke: white;
        animation: iconFloat 3s ease-in-out infinite;
      }
      
      @keyframes iconFloat {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-4px); }
      }
      
      .panel-title h1 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #f8fafc;
        margin: 0;
        letter-spacing: -0.01em;
      }
      
      .panel-title p {
        color: rgba(255,255,255,0.5);
        margin: 0.25rem 0 0 0;
        font-size: 0.95rem;
      }
      
      /* Repair Action Buttons */
      .repair-primary,
      .animate-ui-action-btn.edit.repair-primary { 
        background: linear-gradient(135deg, #f97316, #fb923c); 
        color: #fff; 
        border: none; 
        font-weight: 600; 
        letter-spacing: 0.2px;
        border-radius: 10px;
        padding: 0.5rem 1rem;
        transition: all 0.3s ease;
      }
      
      .repair-primary:hover,
      .animate-ui-action-btn.edit.repair-primary:hover { 
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(249,115,22,0.3);
      }
      
      .time-badge { 
        display: inline-block; 
        padding: 0.35rem 0.75rem; 
        border-radius: 8px; 
        font-weight: 600; 
        font-size: 0.85rem; 
        white-space: nowrap;
        animation: timePulse 2s ease-in-out infinite;
      }
      
      @keyframes timePulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.02); }
      }
      
      .time-fresh { background: linear-gradient(135deg, #d1fae5, #a7f3d0); color: #065f46; }
      .time-warning { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; }
      .time-danger { background: linear-gradient(135deg, #fee2e2, #fecaca); color: #b91c1c; }
      .time-neutral { background: linear-gradient(135deg, #e2e8f0, #cbd5e1); color: #0f172a; }
      
      /* Modern Status Filter Buttons */
      .status-filters {
        display: flex;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
      }
      
      .status-filter-btn {
        padding: 0.7rem 1.25rem;
        border-radius: 100px;
        border: 1px solid;
        background: transparent;
        cursor: pointer;
        font-weight: 500;
        font-size: 0.9rem;
        transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        position: relative;
        overflow: hidden;
      }
      
      .status-filter-btn::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent);
        opacity: 0;
        transition: opacity 0.3s ease;
      }
      
      .status-filter-btn:hover {
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 8px 20px rgba(0,0,0,0.2);
      }
      
      .status-filter-btn:hover::before {
        opacity: 1;
      }
      
      /* Active state - brighter and more prominent */
      .status-filter-btn.active {
        font-weight: 600;
        box-shadow: 0 8px 25px rgba(0,0,0,0.25);
        transform: scale(1.05);
      }
      
      /* Toggle Button Modern */
      .toggle-btn {
        padding: 0.85rem 1.5rem;
        border-radius: 12px;
        background: rgba(255,255,255,0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.1);
        color: rgba(255,255,255,0.8);
        font-size: 0.95rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
      }
      
      .toggle-btn:hover {
        background: rgba(255,255,255,0.1);
        border-color: rgba(255,255,255,0.2);
        color: white;
        transform: translateY(-2px);
      }
      
      .toggle-btn svg {
        width: 16px;
        height: 16px;
        transition: transform 0.3s ease;
      }
      
      .toggle-btn.collapsed svg {
        transform: rotate(-90deg);
      }
      
      /* Sort Select Modern */
      .sort-select {
        padding: 0.7rem 1rem;
        border-radius: 10px;
        border: 1px solid rgba(255,255,255,0.1);
        background: rgba(255,255,255,0.05);
        backdrop-filter: blur(10px);
        color: #f5f8ff;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s ease;
      }
      
      .sort-select:hover {
        border-color: rgba(255,255,255,0.2);
        background: rgba(255,255,255,0.1);
      }
      
      .sort-select:focus {
        outline: none;
        border-color: rgba(96,165,250,0.5);
        box-shadow: 0 0 0 3px rgba(96,165,250,0.1);
      }
      
      /* Animated Wrench SVG */
      @keyframes wrenchRotate {
        0%, 100% { transform: rotate(0deg); }
        25% { transform: rotate(-15deg); }
        75% { transform: rotate(15deg); }
      }
      
      .wrench-animated {
        animation: wrenchRotate 2s ease-in-out infinite;
        transform-origin: center;
      }
      
      /* Animated Gear SVG */
      @keyframes gearSpin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
      }
      
      .gear-animated {
        animation: gearSpin 8s linear infinite;
        transform-origin: center;
      }
      
      /* Animated Checkmark */
      @keyframes checkDraw {
        0% { stroke-dashoffset: 100; }
        100% { stroke-dashoffset: 0; }
      }
      
      .check-animated path {
        stroke-dasharray: 100;
        animation: checkDraw 1s ease forwards;
      }
      
      /* Shimmer effect for loading states */
      @keyframes shimmer {
        0% { background-position: -200% 0; }
        100% { background-position: 200% 0; }
      }
      
      .shimmer {
        background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.1) 50%, transparent 100%);
        background-size: 200% 100%;
        animation: shimmer 2s infinite;
      }
      
      /* Wave animation for decorative background */
      .wave-bg {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 100px;
        opacity: 0.05;
        overflow: hidden;
      }
      
      .wave-bg svg {
        width: 100%;
        height: 100%;
      }
      
      .wave-bg path {
        animation: waveMove 10s ease-in-out infinite;
      }
      
      @keyframes waveMove {
        0%, 100% { d: path('M0,50 Q250,0 500,50 T1000,50 V100 H0 Z'); }
        50% { d: path('M0,50 Q250,100 500,50 T1000,50 V100 H0 Z'); }
      }
      
      /* Image preview modern */
      .image-preview-container {
        margin-top: 1rem;
        padding: 1rem;
        border-radius: 12px;
        background: rgba(255,255,255,0.03);
        border: 1px dashed rgba(255,255,255,0.1);
        transition: all 0.3s ease;
      }
      
      .image-preview-container:hover {
        border-color: rgba(96,165,250,0.3);
        background: rgba(255,255,255,0.05);
      }
      
      .image-preview-container img {
        border-radius: 10px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        transition: transform 0.3s ease;
      }
      
      .image-preview-container img:hover {
        transform: scale(1.05);
      }
      
      /* Table Modern Styling */
      .report-table {
        border-radius: 16px;
        overflow: hidden;
        background: rgba(255,255,255,0.02);
        border: 1px solid rgba(255,255,255,0.06);
      }
      
      .report-table table {
        width: 100%;
      }
      
      .report-table thead {
        background: rgba(255,255,255,0.05);
      }
      
      .report-table th {
        padding: 1rem;
        font-weight: 600;
        color: rgba(255,255,255,0.7);
        text-align: left;
        font-size: 0.9rem;
        letter-spacing: 0.02em;
      }
      
      .report-table td {
        padding: 1rem;
        border-bottom: 1px solid rgba(255,255,255,0.05);
      }
      
      .report-table tbody tr {
        transition: all 0.3s ease;
      }
      
      .report-table tbody tr:hover {
        background: rgba(255,255,255,0.03);
      }
      
      /* Responsive */
      @media (max-width: 768px) {
        .repair-stats {
          grid-template-columns: 1fr;
        }
        
        .repair-form {
          grid-template-columns: 1fr;
        }
        
        .panel-header {
          flex-direction: column;
          text-align: center;
        }
        
        .status-filters {
          justify-content: center;
        }
      }
      
      /* Schedule Modal Styles */
      .schedule-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.7);
        backdrop-filter: blur(8px);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
      }
      
      .schedule-modal-overlay.active {
        opacity: 1;
        visibility: visible;
      }
      
      .schedule-modal {
        background: linear-gradient(145deg, #1e293b, #0f172a);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 24px;
        padding: 2rem;
        width: 95%;
        max-width: 550px;
        max-height: 90vh;
        overflow-y: auto;
        transform: scale(0.9) translateY(20px);
        transition: transform 0.3s ease;
        box-shadow: 0 25px 60px rgba(0,0,0,0.5);
      }
      
      .schedule-modal-overlay.active .schedule-modal {
        transform: scale(1) translateY(0);
      }
      
      .schedule-modal-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(255,255,255,0.1);
      }
      
      .schedule-modal-icon {
        width: 52px;
        height: 52px;
        background: linear-gradient(135deg, #8b5cf6, #a855f7);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 8px 20px rgba(139,92,246,0.3);
      }
      
      .schedule-modal-icon svg {
        width: 26px;
        height: 26px;
        color: white;
        stroke: white;
      }
      
      .schedule-modal-title h2 {
        color: #f8fafc;
        font-size: 1.35rem;
        font-weight: 700;
        margin: 0;
      }
      
      .schedule-modal-title p {
        color: rgba(255,255,255,0.5);
        font-size: 0.9rem;
        margin: 0.25rem 0 0 0;
      }
      
      .schedule-modal-close {
        margin-left: auto;
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 10px;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: rgba(255,255,255,0.6);
        transition: all 0.2s ease;
      }
      
      .schedule-modal-close:hover {
        background: rgba(239,68,68,0.2);
        border-color: rgba(239,68,68,0.3);
        color: #f87171;
      }
      
      .schedule-form {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
      }
      
      .schedule-form .form-group {
        position: relative;
      }
      
      .schedule-form .form-group.full-width {
        grid-column: 1 / -1;
      }
      
      .schedule-form label {
        display: block;
        font-weight: 600;
        color: rgba(255,255,255,0.7);
        font-size: 0.85rem;
        margin-bottom: 0.5rem;
      }
      
      .schedule-form input,
      .schedule-form textarea {
        width: 100%;
        padding: 0.75rem 1rem;
        border-radius: 10px;
        border: 1px solid rgba(255,255,255,0.1);
        background: rgba(255,255,255,0.05);
        color: #f8fafc;
        font-size: 0.95rem;
        transition: all 0.2s ease;
      }
      
      .schedule-form input:focus,
      .schedule-form textarea:focus {
        outline: none;
        border-color: rgba(139,92,246,0.5);
        box-shadow: 0 0 0 3px rgba(139,92,246,0.1);
      }
      
      .schedule-form textarea {
        min-height: 80px;
        resize: vertical;
      }
      
      .schedule-form-actions {
        grid-column: 1 / -1;
        display: flex;
        gap: 0.75rem;
        margin-top: 0.5rem;
      }
      
      .schedule-info-box {
        grid-column: 1 / -1;
        background: rgba(139,92,246,0.1);
        border: 1px solid rgba(139,92,246,0.2);
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 0.5rem;
      }
      
      .schedule-info-box h4 {
        color: #a78bfa;
        font-size: 0.9rem;
        font-weight: 600;
        margin: 0 0 0.5rem 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }
      
      .schedule-info-box p {
        color: rgba(255,255,255,0.7);
        font-size: 0.85rem;
        margin: 0;
        line-height: 1.5;
      }
      
      /* Schedule Badge in Table */
      .schedule-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.4rem 0.75rem;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 500;
        max-width: 180px;
      }
      
      .schedule-badge.scheduled {
        background: rgba(139,92,246,0.15);
        border: 1px solid rgba(139,92,246,0.3);
        color: #a78bfa;
      }
      
      .schedule-badge.not-scheduled {
        background: rgba(148,163,184,0.1);
        border: 1px solid rgba(148,163,184,0.2);
        color: rgba(255,255,255,0.4);
      }
      
      .schedule-badge svg {
        width: 14px;
        height: 14px;
        flex-shrink: 0;
      }
      
      .schedule-details {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
      }
      
      .schedule-details .date {
        font-weight: 600;
      }
      
      .schedule-details .time {
        font-size: 0.75rem;
        opacity: 0.8;
      }
      
      .schedule-details .technician {
        font-size: 0.75rem;
        opacity: 0.7;
        display: flex;
        align-items: center;
        gap: 0.25rem;
      }
      
      /* Schedule Button */
      .btn-schedule {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        color: white;
        padding: 0.45rem 0.85rem;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 600;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        transition: all 0.2s ease;
      }
      
      .btn-schedule:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(139,92,246,0.35);
      }
      
      .btn-schedule svg {
        width: 14px;
        height: 14px;
      }
      
      /* Additional Schedule Modal Form Styles */
      .schedule-modal-title {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: #f8fafc;
        font-size: 1.25rem;
        font-weight: 700;
      }
      
      .schedule-modal-title svg {
        color: #a78bfa;
      }
      
      .schedule-modal-body {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        margin-bottom: 1.5rem;
      }
      
      .schedule-info-card {
        display: flex;
        align-items: center;
        gap: 1rem;
        background: rgba(139,92,246,0.1);
        border: 1px solid rgba(139,92,246,0.2);
        border-radius: 12px;
        padding: 1rem;
      }
      
      .schedule-info-icon {
        width: 44px;
        height: 44px;
        background: linear-gradient(135deg, #8b5cf6, #a855f7);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
      }
      
      .schedule-info-icon svg {
        color: white;
      }
      
      .schedule-info-content {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
      }
      
      .schedule-info-label {
        font-size: 0.8rem;
        color: rgba(255,255,255,0.5);
      }
      
      .schedule-info-value {
        font-size: 1rem;
        font-weight: 600;
        color: #f8fafc;
      }
      
      .schedule-form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
      }
      
      .schedule-form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
      }
      
      .schedule-label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.9rem;
        font-weight: 600;
        color: rgba(255,255,255,0.7);
      }
      
      .schedule-label svg {
        color: rgba(139,92,246,0.7);
      }
      
      .schedule-input {
        width: 100%;
        padding: 0.75rem 1rem;
        border-radius: 10px;
        border: 1px solid rgba(255,255,255,0.1);
        background: rgba(255,255,255,0.05);
        color: #f8fafc;
        font-size: 0.95rem;
        transition: all 0.2s ease;
      }
      
      .schedule-input:focus {
        outline: none;
        border-color: rgba(139,92,246,0.5);
        box-shadow: 0 0 0 3px rgba(139,92,246,0.15);
        background: rgba(255,255,255,0.08);
      }
      
      .schedule-textarea {
        min-height: 80px;
        resize: vertical;
        font-family: inherit;
      }
      
      .schedule-modal-footer {
        display: flex;
        gap: 0.75rem;
        justify-content: flex-end;
        padding-top: 1rem;
        border-top: 1px solid rgba(255,255,255,0.1);
      }
      
      .schedule-btn {
        padding: 0.75rem 1.5rem;
        border-radius: 10px;
        font-size: 0.9rem;
        font-weight: 600;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
      }
      
      .schedule-btn-cancel {
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.1);
        color: rgba(255,255,255,0.7);
      }
      
      .schedule-btn-cancel:hover {
        background: rgba(255,255,255,0.1);
        border-color: rgba(255,255,255,0.2);
        color: #f8fafc;
      }
      
      .schedule-btn-save {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        color: white;
        box-shadow: 0 4px 15px rgba(139,92,246,0.3);
      }
      
      .schedule-btn-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(139,92,246,0.4);
      }
      
      .schedule-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none !important;
      }
      
      @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
      }
      
      .spin {
        animation: spin 1s linear infinite;
      }
      
      /* Mobile responsive for schedule modal */
      @media (max-width: 640px) {
        .schedule-modal {
          padding: 1.5rem;
          margin: 1rem;
          width: calc(100% - 2rem);
        }
        
        .schedule-form-row {
          grid-template-columns: 1fr;
        }
        
        .schedule-modal-footer {
          flex-direction: column;
        }
        
        .schedule-btn {
          justify-content: center;
        }
      }
      /* Ensure active filter icons and counts are visible (white) in light theme.
         Targets when theme class is on <html> (html.light-theme) or on <body> (body.live-light).
         Also tries to match inline blue backgrounds (#60a5fa) used on some buttons. */
      html.light-theme .status-filter-btn.active,
      html.light-theme .status-filter-btn.active *,
      html.light-theme a.filter-btn[style*="#60a5fa"],
      html.light-theme a.filter-btn[style*="#60a5fa"] *,
      body.live-light .status-filter-btn.active,
      body.live-light a.filter-btn[style*="#60a5fa"] {
        color: #ffffff !important;
      }

      html.light-theme .status-filter-btn.active svg,
      html.light-theme .status-filter-btn.active svg *,
      body.live-light .status-filter-btn.active svg,
      body.live-light .status-filter-btn.active svg *,
      html.light-theme a.filter-btn[style*="#60a5fa"] svg,
      body.live-light a.filter-btn[style*="#60a5fa"] svg {
        stroke: #ffffff !important;
        fill: #ffffff !important;
      }

      /* If numeric counts are wrapped in a span with class 'count' enforce white */
      html.light-theme .status-filter-btn.active .count,
      html.light-theme a.filter-btn .count,
      body.live-light .status-filter-btn.active .count,
      body.live-light a.filter-btn .count,
      html.light-theme .status-filters .status-filter-btn[style*="#60a5fa"] .count,
      body.live-light .status-filters .status-filter-btn[style*="#60a5fa"] .count {
        color: #ffffff !important;
      }

      /* Image placeholder color: adapt to light/dark themes */
      .image-placeholder { color: rgba(255,255,255,0.3); }
      html.light-theme .image-placeholder,
      body.live-light .image-placeholder { color: rgba(0,0,0,0.35) !important; }

      /* Force schedule/action buttons to use dark-mode appearance even in light theme */
      html.light-theme .btn-schedule,
      body.live-light .btn-schedule {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed) !important;
        color: #ffffff !important;
        border: none !important;
        box-shadow: 0 8px 20px rgba(124,58,237,0.15) !important;
      }

      html.light-theme .schedule-badge.scheduled,
      body.live-light .schedule-badge.scheduled {
        background: linear-gradient(135deg, #8b5cf6, #a855f7) !important;
        color: #ffffff !important;
        border: none !important;
        box-shadow: 0 8px 20px rgba(168,85,247,0.12) !important;
      }

      /* Cancel button should appear like dark-mode secondary in light theme */
      html.light-theme .schedule-btn-cancel,
      body.live-light .schedule-btn-cancel {
        background: rgba(0,0,0,0.06) !important;
        border: 1px solid rgba(0,0,0,0.08) !important;
        color: #ffffff !important;
      }

      /* Ensure status-badge action buttons (like 'ซ่อมเสร็จแล้ว') keep white icons/text */
      html.light-theme .schedule-badge.scheduled svg,
      body.live-light .schedule-badge.scheduled svg,
      html.light-theme .btn-schedule svg,
      body.live-light .btn-schedule svg {
        stroke: #ffffff !important;
        fill: #ffffff !important;
      }

      /* Override inline-styled action buttons in the table to match dark-mode look */
      html.light-theme .crud-actions button[onclick*="openScheduleModal"],
      body.live-light .crud-actions button[onclick*="openScheduleModal"] {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed) !important;
        color: #ffffff !important;
        border: none !important;
        box-shadow: 0 8px 20px rgba(124,58,237,0.12) !important;
      }

      html.light-theme .crud-actions button[onclick*="'2'"],
      body.live-light .crud-actions button[onclick*="'2'"] {
        background: linear-gradient(135deg, #22c55e, #16a34a) !important;
        color: #ffffff !important;
        border: none !important;
      }

      html.light-theme .crud-actions button[onclick*="'3'"],
      body.live-light .crud-actions button[onclick*="'3'"] {
        background: rgba(153,27,27,0.12) !important;
        color: #f87171 !important;
        border: 1px solid rgba(153,27,27,0.25) !important;
      }

      /* Ensure SVG icons inside these action buttons are white/visible */
      html.light-theme .crud-actions button[onclick*="openScheduleModal"] svg,
      body.live-light .crud-actions button[onclick*="openScheduleModal"] svg,
      html.light-theme .crud-actions button[onclick*="'2'"] svg,
      body.live-light .crud-actions button[onclick*="'2'"] svg {
        stroke: #ffffff !important;
        fill: #ffffff !important;
      }

      /* Cover any other buttons that open the schedule modal (not only inside .crud-actions) */
      html.light-theme button[onclick*="openScheduleModal"],
      body.live-light button[onclick*="openScheduleModal"] {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed) !important;
        color: #ffffff !important;
        border: none !important;
        box-shadow: 0 8px 20px rgba(124,58,237,0.12) !important;
      }

      html.light-theme button[onclick*="openScheduleModal"] svg,
      body.live-light button[onclick*="openScheduleModal"] svg {
        stroke: #ffffff !important;
        fill: #ffffff !important;
      }

      /* Ensure SVGs inherit currentColor in buttons generally */
      button svg { stroke: currentColor; fill: none; }

      /* Also ensure view-toggle active icons are white in light theme */
      html.light-theme .view-toggle-btn.active svg,
      body.live-light .view-toggle-btn.active svg {
        stroke: #ffffff !important;
        fill: #ffffff !important;
        color: #ffffff !important;
      }
    </style>
  </head>
  <body class="reports-page">
    <div class="app-shell">
      <?php include __DIR__ . '/../includes/sidebar.php'; ?>
      <main class="app-main">
        <div>
          <?php 
            $pageTitle = 'จัดการการแจ้งซ่อมอุปกรณ์';
            include __DIR__ . '/../includes/page_header.php'; 
          ?>

          <?php if ($isLightTheme): ?>
          <style>
            /* ===== Light Theme Active - Complete Override ===== */
            
            /* Dark text for all content areas */
            .manage-panel,
            .manage-panel *,
            .repair-form,
            .repair-form *,
            .repair-stats *,
            .status-filters *,
            .report-table,
            .report-table *,
            .panel-title,
            .panel-title *,
            .form-group,
            .form-group *,
            .crud-actions,
            .crud-actions * {
              color: #111827 !important;
            }

            /* SVGs should be dark by default */
            .manage-panel svg,
            .repair-form svg,
            .report-table svg,
            .status-filters svg,
            .form-group svg {
              stroke: #111827 !important;
              fill: none !important;
            }

            /* Placeholder styling */
            .image-placeholder {
              color: rgba(17,24,39,0.35) !important;
              border-color: rgba(17,24,39,0.15) !important;
              background: rgba(0,0,0,0.03) !important;
            }
            .image-placeholder svg {
              stroke: rgba(17,24,39,0.35) !important;
              fill: none !important;
            }

            /* ===== Buttons with colored backgrounds - keep white text/icons ===== */
            
            /* Panel icons */
            .manage-panel .panel-icon,
            .manage-panel .panel-icon svg {
              color: #ffffff !important;
              stroke: #ffffff !important;
            }

            /* Stat card icons */
            .repair-stat-card .stat-card-icon,
            .repair-stat-card .stat-card-icon svg {
              color: #ffffff !important;
              stroke: #ffffff !important;
            }

            /* Toggle button - dark text on light background */
            .toggle-btn {
              background: rgba(0,0,0,0.08) !important;
              border-color: rgba(0,0,0,0.15) !important;
              color: #111827 !important;
            }
            .toggle-btn:hover {
              background: rgba(0,0,0,0.12) !important;
              border-color: rgba(0,0,0,0.2) !important;
              color: #111827 !important;
            }
            .toggle-btn svg {
              stroke: #111827 !important;
            }

            /* Schedule badge (purple card) */
            .schedule-badge,
            .schedule-badge *,
            .schedule-badge svg,
            .schedule-badge.scheduled,
            .schedule-badge.scheduled *,
            .schedule-badge.scheduled svg {
              color: #ffffff !important;
              stroke: #ffffff !important;
            }

            /* Schedule button (purple) */
            .btn-schedule,
            .btn-schedule *,
            .btn-schedule svg {
              color: #ffffff !important;
              stroke: #ffffff !important;
              background: linear-gradient(135deg, #8b5cf6, #7c3aed) !important;
            }

            /* Primary buttons (purple gradient) */
            .btn-modern.btn-primary,
            .btn-modern.btn-primary *,
            .btn-modern.btn-primary svg,
            button.btn-modern[style*="background:linear-gradient"],
            button.btn-modern[style*="background:linear-gradient"] * {
              color: #ffffff !important;
              stroke: #ffffff !important;
            }

            /* Green success button */
            button[style*="background:linear-gradient(135deg, #22c55e"],
            button[style*="background:linear-gradient(135deg, #22c55e"] *,
            button[style*="background:linear-gradient(135deg, #22c55e"] svg {
              color: #ffffff !important;
              stroke: #ffffff !important;
            }

            /* Red/Pink cancel button - keep its original styling */
            button[style*="background:rgba(239,68,68"],
            button[style*="background:rgba(239,68,68"] * {
              color: #f87171 !important;
            }
            button[style*="background:rgba(239,68,68"] svg {
              stroke: #f87171 !important;
            }

            /* Status badge */
            .status-badge,
            .status-badge *,
            .status-badge svg {
              color: #ffffff !important;
              stroke: #ffffff !important;
            }

            /* CRUD action buttons - need specific handling */
            .crud-actions button[style*="background:linear-gradient"],
            .crud-actions button[style*="background:linear-gradient"] *,
            .crud-actions button[style*="background:linear-gradient"] svg {
              color: #ffffff !important;
              stroke: #ffffff !important;
            }
          </style>
          <?php endif; ?>

          <?php if (isset($_SESSION['success'])): ?>
            <div style="padding: 1rem 1.25rem; margin-bottom: 1.5rem; background: linear-gradient(135deg, #22c55e, #16a34a); color: white; border-radius: 14px; font-weight: 600; display: flex; align-items: center; gap: 0.75rem; box-shadow: 0 8px 25px rgba(34,197,94,0.25); animation: fadeInUp 0.5s ease forwards;">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
              <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
          <?php endif; ?>
          <?php if (isset($_SESSION['error'])): ?>
            <div style="padding: 1rem 1.25rem; margin-bottom: 1.5rem; background: linear-gradient(135deg, #ef4444, #dc2626); color: white; border-radius: 14px; font-weight: 600; display: flex; align-items: center; gap: 0.75rem; box-shadow: 0 8px 25px rgba(239,68,68,0.25); animation: fadeInUp 0.5s ease forwards;">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
              <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
          <?php endif; ?>

          <!-- Stats Section with Animated Cards -->
          <section class="manage-panel" style="--panel-accent: #f97316; --panel-accent-end: #fb923c;">
            <div class="panel-header">
              <div class="panel-icon" style="background: linear-gradient(135deg, #f97316, #fb923c);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="wrench-animated">
                  <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                </svg>
              </div>
              <div class="panel-title">
                <h1>สรุปการแจ้งซ่อม</h1>
                <p>สถานะปัจจุบันของงานซ่อมทั้งหมด</p>
              </div>
            </div>
            <div class="repair-stats">
              <!-- Pending Card -->
              <div class="repair-stat-card pending">
                <div class="stat-particles">
                  <span></span><span></span><span></span><span></span>
                </div>
                <div class="stat-card-header">
                  <div class="stat-card-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="12" cy="12" r="10"/>
                      <polyline points="12 6 12 12 16 14"/>
                    </svg>
                  </div>
                  <h3>รอซ่อม</h3>
                </div>
                <div class="stat-number"><?php echo (int)$stats['pending']; ?></div>
              </div>
              
              <!-- In Progress Card -->
              <div class="repair-stat-card inprogress">
                <div class="stat-particles">
                  <span></span><span></span><span></span><span></span>
                </div>
                <div class="stat-card-header">
                  <div class="stat-card-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="gear-animated">
                      <circle cx="12" cy="12" r="3"/>
                      <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                    </svg>
                  </div>
                  <h3>กำลังซ่อม</h3>
                </div>
                <div class="stat-number"><?php echo (int)$stats['inprogress']; ?></div>
              </div>
              
              <!-- Done Card -->
              <div class="repair-stat-card done">
                <div class="stat-particles">
                  <span></span><span></span><span></span><span></span>
                </div>
                <div class="stat-card-header">
                  <div class="stat-card-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="check-animated">
                      <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                      <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                  </div>
                  <h3>ซ่อมเสร็จแล้ว</h3>
                </div>
                <div class="stat-number"><?php echo (int)$stats['done']; ?></div>
              </div>
            </div>
          </section>

          <!-- Modern Toggle Button -->
          <div style="margin: 1.75rem 0;">
            <button type="button" id="toggleFormBtn" class="toggle-btn" onclick="toggleRepairForm()">
              <svg id="toggleFormIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 12 15 18 9"/>
              </svg>
              <span id="toggleFormText">ซ่อนฟอร์มแจ้งซ่อม</span>
            </button>
          </div>

          <!-- Add Repair Form Section -->
          <section class="manage-panel" id="addRepairSection" style="--panel-accent: #3b82f6; --panel-accent-end: #6366f1;">
            <div class="panel-header">
              <div class="panel-icon" style="background: linear-gradient(135deg, #3b82f6, #6366f1);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="12" y1="5" x2="12" y2="19"/>
                  <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
              </div>
              <div class="panel-title">
                <h1>เพิ่มการแจ้งซ่อม</h1>
                <p>เลือกห้อง/ผู้เช่า ระบุวันที่และรายละเอียดงานซ่อม</p>
              </div>
            </div>
            <form action="../Manage/process_repair.php" method="post" id="repairForm" enctype="multipart/form-data">
              <div class="repair-form">
                <div class="form-group">
                  <label for="ctr_id">
                    <svg style="width:16px;height:16px;vertical-align:middle;margin-right:4px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    สัญญา / ห้องพัก <span style="color:#f87171;">*</span>
                  </label>
                  <select id="ctr_id" name="ctr_id" required>
                    <option value="">-- เลือกสัญญา --</option>
                    <?php foreach ($contracts as $ctr): ?>
                      <option value="<?php echo (int)$ctr['ctr_id']; ?>">
                        ห้อง <?php echo htmlspecialchars((string)($ctr['room_number'] ?? '-')); ?> - <?php echo htmlspecialchars($ctr['tnt_name'] ?? 'ไม่ระบุ'); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label for="repair_date">
                    <svg style="width:16px;height:16px;vertical-align:middle;margin-right:4px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    วันที่แจ้ง
                  </label>
                  <input type="text" id="repair_date" name="repair_date" readonly style="opacity:0.7; cursor:not-allowed;" value="<?php echo date('d/m/Y'); ?>" />
                  <input type="hidden" id="repair_date_hidden" name="repair_date_hidden" value="<?php echo date('Y-m-d'); ?>" />
                </div>
                <div class="form-group">
                  <label for="repair_time">
                    <svg style="width:16px;height:16px;vertical-align:middle;margin-right:4px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    เวลาแจ้ง
                  </label>
                  <input type="text" id="repair_time" name="repair_time" readonly style="opacity:0.7; cursor:not-allowed; font-weight:600; font-size:1.1rem;" value="<?php echo date('H:i:s'); ?>" />
                  <input type="hidden" id="repair_time_hidden" name="repair_time_hidden" value="<?php echo date('H:i:s'); ?>" />
                </div>
                <input type="hidden" name="repair_status" value="0" />
                <div class="form-group" style="grid-column:1 / -1;">
                  <label for="repair_desc">
                    <svg style="width:16px;height:16px;vertical-align:middle;margin-right:4px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    รายละเอียด <span style="color:#f87171;">*</span>
                  </label>
                  <textarea id="repair_desc" name="repair_desc" required placeholder="เช่น แอร์ไม่เย็น น้ำรั่ว ซิงค์อุดตัน"></textarea>
                </div>
                <div class="form-group" style="grid-column:1 / -1;">
                  <label for="repair_image">
                    <svg style="width:16px;height:16px;vertical-align:middle;margin-right:4px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    รูปภาพการซ่อม (ไม่จำเป็น)
                  </label>
                  <input type="file" id="repair_image" name="repair_image" accept="image/*" />
                  <small style="color:rgba(255,255,255,0.5); display:block; margin-top:0.5rem;">
                    <svg style="width:14px;height:14px;vertical-align:middle;margin-right:4px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    สนับสนุน: JPG, PNG, WebP (ขนาดไม่เกิน 5MB)
                  </small>
                  <div id="image_preview" class="image-preview-container" style="display:none;"></div>
                </div>
                <div class="repair-form-actions">
                  <button type="submit" class="btn-modern btn-primary" data-animate-ui-skip="true" data-no-modal="true" data-allow-submit="true" style="flex:2;">
                    <svg style="width:20px;height:20px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    บันทึกการแจ้งซ่อม
                  </button>
                  <button type="reset" class="btn-modern btn-secondary" style="flex:1;">
                    <svg style="width:18px;height:18px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                    ล้างข้อมูล
                  </button>
                </div>
              </div>
            </form>
          </section>

          <!-- Repair List Section -->
          <section class="manage-panel" style="--panel-accent: #8b5cf6; --panel-accent-end: #a855f7;">
            <div class="panel-header" style="justify-content:space-between;flex-wrap:wrap;">
              <div style="display:flex;align-items:center;gap:1rem;">
                <div class="panel-icon" style="background: linear-gradient(135deg, #8b5cf6, #a855f7);">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="8" y1="6" x2="21" y2="6"/>
                    <line x1="8" y1="12" x2="21" y2="12"/>
                    <line x1="8" y1="18" x2="21" y2="18"/>
                    <line x1="3" y1="6" x2="3.01" y2="6"/>
                    <line x1="3" y1="12" x2="3.01" y2="12"/>
                    <line x1="3" y1="18" x2="3.01" y2="18"/>
                  </svg>
                </div>
                <div class="panel-title">
                  <h1>รายการแจ้งซ่อมทั้งหมด</h1>
                  <p>ดูสถานะและจัดการงานซ่อม</p>
                </div>
              </div>
              <select id="sortSelect" class="sort-select" onchange="changeSortBy(this.value)">
                <option value="newest" <?php echo ($sortBy === 'newest' ? 'selected' : ''); ?>>
                  ▼ แจ้งล่าสุด
                </option>
                <option value="oldest" <?php echo ($sortBy === 'oldest' ? 'selected' : ''); ?>>
                  ▲ แจ้งเก่าสุด
                </option>
                <option value="room_number" <?php echo ($sortBy === 'room_number' ? 'selected' : ''); ?>>
                  # หมายเลขห้อง
                </option>
              </select>
            </div>
            
            <!-- Modern Status Filter Buttons -->
            <div class="status-filters">
              <button type="button" class="status-filter-btn" data-status="all" onclick="filterByStatus('all')" style="background:rgba(96,165,250,0.2);border:1.5px solid rgba(96,165,250,0.5);color:#60a5fa;">
                <svg style="width:16px;height:16px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                ทั้งหมด <span style="margin-left:0.3rem;font-weight:700;">(<span class="count-all"><?php echo count($repairs); ?></span>)</span>
              </button>
              <button type="button" class="status-filter-btn" data-status="0" onclick="filterByStatus('0')" style="background:rgba(249,115,22,0.15);border:1.5px solid rgba(249,115,22,0.4);color:#f97316;">
                <svg style="width:16px;height:16px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                รอซ่อม <span style="margin-left:0.3rem;font-weight:700;">(<span class="count-0"><?php echo $stats['pending']; ?></span>)</span>
              </button>
              <button type="button" class="status-filter-btn" data-status="1" onclick="filterByStatus('1')" style="background:rgba(96,165,250,0.15);border:1.5px solid rgba(96,165,250,0.4);color:#60a5fa;">
                <svg style="width:16px;height:16px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="gear-animated"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                กำลังซ่อม <span style="margin-left:0.3rem;font-weight:700;">(<span class="count-1"><?php echo $stats['inprogress']; ?></span>)</span>
              </button>
              <button type="button" class="status-filter-btn" data-status="2" onclick="filterByStatus('2')" style="background:rgba(34,197,94,0.15);border:1.5px solid rgba(34,197,94,0.4);color:#22c55e;">
                <svg style="width:16px;height:16px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                ซ่อมเสร็จแล้ว <span style="margin-left:0.3rem;font-weight:700;">(<span class="count-2"><?php echo $stats['done']; ?></span>)</span>
              </button>
            </div>
            <div class="report-table">
              <table class="table--compact" id="table-repairs">
                <thead>
                  <tr>
                    <th>วันที่</th>
                    <th>รูปภาพ</th>
                    <th>ห้อง/ผู้แจ้ง</th>
                    <th>รายละเอียด</th>
                    <th>นัดหมายซ่อม</th>
                    <th>สถานะ</th>
                    <th class="crud-column">จัดการ</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($repairs)): ?>
                    <tr><td colspan="7" style="text-align:center;padding:2.5rem;color:rgba(255,255,255,0.5);">
                      <svg style="width:48px;height:48px;margin-bottom:1rem;opacity:0.5;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                      <br>ยังไม่มีรายการแจ้งซ่อม
                    </td></tr>
                  <?php else: ?>
                    <?php foreach ($repairs as $r): ?>
                      <?php $status = (string)($r['repair_status'] ?? ''); ?>
                      <tr data-repair-id="<?php echo (int)$r['repair_id']; ?>">
                        <?php 
                          $repairDate = $r['repair_date'] ?? '';
                          $repairTime = $r['repair_time'] ?? '00:00:00';
                          // ตัดเอาเฉพาะวันที่จาก repair_date (ตัดส่วนเวลา 00:00:00 ออก)
                          $dateOnly = $repairDate ? explode(' ', $repairDate)[0] : '';
                          
                          // แปลงวันที่จาก ค.ศ. เป็น พ.ศ. สำหรับแสดงผล
                          $displayDate = $dateOnly;
                          if ($dateOnly) {
                            $dateParts = explode('-', $dateOnly);
                            if (count($dateParts) === 3 && (int)$dateParts[0] < 2100) {
                              // ถ้าเป็น ค.ศ. (น้อยกว่า 2100) ให้แปลงเป็น พ.ศ.
                              $displayDate = ((int)$dateParts[0] + 543) . '-' . $dateParts[1] . '-' . $dateParts[2];
                            }
                          }
                          
                          $repairDateTime = trim($dateOnly . ' ' . $repairTime);
                          $timeInfo = relativeTimeInfo($repairDateTime);
                        ?>
                        <td>
                          <div style="display:flex; flex-direction:column; gap:0.2rem;">
                            <span class="time-badge relative-time <?php echo htmlspecialchars($timeInfo['class']); ?>" 
                                  data-timestamp="<?php 
                                    if ($repairDateTime) {
                                      try {
                                        $tz = new DateTimeZone('Asia/Bangkok');
                                        $dt = new DateTime($repairDateTime, $tz);
                                        echo $dt->getTimestamp();
                                      } catch (Exception $e) {
                                        echo (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->getTimestamp();
                                      }
                                    } else {
                                      echo (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->getTimestamp();
                                    }
                                  ?>">
                              <?php echo htmlspecialchars($timeInfo['label']); ?>
                            </span>
                            <span style="color:#64748b; font-size:0.75rem;">
                              <?php 
                                if ($displayDate) {
                                  echo htmlspecialchars($displayDate) . ' ' . htmlspecialchars($repairTime);
                                } else {
                                  echo '-';
                                }
                              ?>
                            </span>
                          </div>
                        </td>
                        <td>
                          <div style="display:flex; align-items:center; justify-content:center; min-width:60px; min-height:60px;">
                            <?php if (!empty($r['repair_image'])): ?>
                              <img src="..//Assets/Images/Repairs/<?php echo htmlspecialchars(basename($r['repair_image'])); ?>" 
                                   alt="รูปการซ่อม" 
                                   style="max-width:60px; max-height:60px; border-radius:10px; object-fit:cover; box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: transform 0.3s ease;" 
                                   onmouseover="this.style.transform='scale(1.1)'" 
                                   onmouseout="this.style.transform='scale(1)'" />
                            <?php else: ?>
                              <div class="image-placeholder" style="width:50px; height:50px; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,0.05); border-radius:10px; border:1px dashed rgba(255,255,255,0.1);">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                  <rect x="3" y="3" width="18" height="18" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="1.5" />
                                  <circle cx="8.5" cy="8.5" r="1.5" fill="none" stroke="currentColor" stroke-width="1.5" />
                                  <path d="M21 15l-5-5L5 21" fill="none" stroke="currentColor" stroke-width="1.5" />
                                </svg>
                              </div>
                            <?php endif; ?>
                          </div>
                        </td>
                        <td>
                          <div style="display:flex; flex-direction:column; gap:0.25rem;">
                            <span style="font-weight:600; display:flex; align-items:center; gap:0.4rem;">
                              <svg style="width:14px;height:14px;opacity:0.6;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                              ห้อง <?php echo htmlspecialchars((string)($r['room_number'] ?? '-')); ?>
                            </span>
                            <span style="color:rgba(255,255,255,0.5); font-size:0.8rem; display:flex; align-items:center; gap:0.35rem;">
                              <svg style="width:12px;height:12px;opacity:0.6;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                              <?php echo htmlspecialchars($r['tnt_name'] ?? '-'); ?>
                            </span>
                          </div>
                        </td>
                        <td style="max-width:250px;">
                          <div style="line-height:1.5; color:rgba(255,255,255,0.8);">
                            <?php echo nl2br(htmlspecialchars($r['repair_desc'] ?? '-')); ?>
                          </div>
                        </td>
                        <!-- Schedule Column -->
                        <td>
                          <?php 
                            $scheduledDate = $r['scheduled_date'] ?? null;
                            $scheduledTimeStart = $r['scheduled_time_start'] ?? null;
                            $scheduledTimeEnd = $r['scheduled_time_end'] ?? null;
                            $technicianName = $r['technician_name'] ?? null;
                            $hasSchedule = !empty($scheduledDate);
                            
                            // Format date for display
                            $formattedScheduleDate = '';
                            if ($scheduledDate) {
                              $dateObj = DateTime::createFromFormat('Y-m-d', $scheduledDate);
                              if ($dateObj) {
                                $thaiYear = (int)$dateObj->format('Y') + 543;
                                $formattedScheduleDate = $dateObj->format('d/m/') . $thaiYear;
                              }
                            }
                            
                            // Format time range
                            $timeRange = '';
                            if ($scheduledTimeStart && $scheduledTimeEnd) {
                              $timeRange = substr($scheduledTimeStart, 0, 5) . '-' . substr($scheduledTimeEnd, 0, 5);
                            } elseif ($scheduledTimeStart) {
                              $timeRange = substr($scheduledTimeStart, 0, 5) . ' น.';
                            }
                          ?>
                          <div id="schedule-cell-<?php echo (int)$r['repair_id']; ?>">
                            <?php if ($hasSchedule): ?>
                              <div class="schedule-badge scheduled" style="cursor:pointer;" onclick="openScheduleModal(<?php echo (int)$r['repair_id']; ?>, '<?php echo htmlspecialchars($r['room_number'] ?? '-', ENT_QUOTES); ?>')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                <div class="schedule-details">
                                  <span class="date"><?php echo htmlspecialchars($formattedScheduleDate); ?></span>
                                  <?php if ($timeRange): ?>
                                    <span class="time"><?php echo htmlspecialchars($timeRange); ?></span>
                                  <?php endif; ?>
                                  <?php if ($technicianName): ?>
                                    <span class="technician">
                                      <svg style="width:10px;height:10px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                      <?php echo htmlspecialchars($technicianName); ?>
                                    </span>
                                  <?php endif; ?>
                                </div>
                              </div>
                            <?php elseif ($status !== '2' && $status !== '3'): ?>
                              <button type="button" class="btn-schedule" onclick="openScheduleModal(<?php echo (int)$r['repair_id']; ?>, '<?php echo htmlspecialchars($r['room_number'] ?? '-', ENT_QUOTES); ?>')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="12" y1="14" x2="12" y2="18"/><line x1="10" y1="16" x2="14" y2="16"/></svg>
                                นัดหมาย
                              </button>
                            <?php else: ?>
                              <span class="schedule-badge not-scheduled">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                -
                              </span>
                            <?php endif; ?>
                          </div>
                        </td>
                        <td>
                          <span class="status-badge" style="background: linear-gradient(135deg, <?php echo $statusColors[$status] ?? '#94a3b8'; ?>, <?php echo $statusColors[$status] ?? '#94a3b8'; ?>dd); padding:0.5rem 1rem; border-radius:100px; font-size:0.85rem; font-weight:600; display:inline-flex; align-items:center; gap:0.35rem; box-shadow: 0 4px 12px <?php echo $statusColors[$status] ?? '#94a3b8'; ?>40;">
                            <?php if ($status === '0'): ?>
                              <svg style="width:14px;height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <?php elseif ($status === '1'): ?>
                              <svg style="width:14px;height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="3"/><path d="M12 1v2m0 18v2M4.22 4.22l1.42 1.42m12.72 12.72l1.42 1.42M1 12h2m18 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                            <?php elseif ($status === '2'): ?>
                              <svg style="width:14px;height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            <?php else: ?>
                              <svg style="width:14px;height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            <?php endif; ?>
                            <?php echo $statusMap[$status] ?? 'ไม่ระบุ'; ?>
                          </span>
                        </td>
                        <td class="crud-column">
                          <div class="crud-actions" data-repair-id="<?php echo (int)$r['repair_id']; ?>" style="gap:0.5rem;">
                            <?php if ($status === '0'): ?>
                              <?php if (!empty($hasSchedule)): ?>
                                <button type="button" class="btn-modern btn-primary" onclick="updateRepairStatus(<?php echo (int)$r['repair_id']; ?>, '1')" style="padding:0.5rem 0.9rem; font-size:0.85rem;">
                                  <svg style="width:16px;height:16px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                                  ทำการซ่อม
                                </button>
                              <?php else: ?>
                                <button type="button" class="btn-modern btn-primary" onclick="openScheduleModal(<?php echo (int)$r['repair_id']; ?>, '<?php echo htmlspecialchars($r['room_number'] ?? '-', ENT_QUOTES); ?>')" title="ต้องกำหนดนัดหมายก่อน" style="padding:0.5rem 0.9rem; font-size:0.85rem; opacity:0.8;">
                                  <svg style="width:16px;height:16px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                                  กำหนดนัดหมายก่อน
                                </button>
                              <?php endif; ?>
                              <button type="button" class="btn-modern" onclick="updateRepairStatus(<?php echo (int)$r['repair_id']; ?>, '3')" style="padding:0.5rem 0.9rem; font-size:0.85rem; background:rgba(239,68,68,0.2); color:#f87171; border:1px solid rgba(239,68,68,0.3);">
                                <svg style="width:16px;height:16px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                                ยกเลิก
                              </button>
                            <?php elseif ($status === '1'): ?>
                              <button type="button" class="btn-modern" onclick="updateRepairStatus(<?php echo (int)$r['repair_id']; ?>, '2')" style="padding:0.5rem 0.9rem; font-size:0.85rem; background:linear-gradient(135deg, #22c55e, #16a34a); color:white;">
                                <svg style="width:16px;height:16px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                ซ่อมเสร็จแล้ว
                              </button>
                              <button type="button" class="btn-modern" onclick="updateRepairStatus(<?php echo (int)$r['repair_id']; ?>, '3')" style="padding:0.5rem 0.9rem; font-size:0.85rem; background:rgba(239,68,68,0.2); color:#f87171; border:1px solid rgba(239,68,68,0.3);">
                                <svg style="width:16px;height:16px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                                ยกเลิก
                              </button>
                            <?php else: ?>
                              <span style="color:#22c55e; font-weight:600; display:flex; align-items:center; gap:0.4rem;">
                                <svg style="width:18px;height:18px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                ซ่อมเสร็จแล้ว
                              </span>
                            <?php endif; ?>
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

    <!-- Schedule Modal -->
    <div id="scheduleModal" class="schedule-modal-overlay" style="display:none;">
      <div class="schedule-modal">
        <div class="schedule-modal-header">
          <div class="schedule-modal-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:28px;height:28px;">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
              <line x1="16" y1="2" x2="16" y2="6"/>
              <line x1="8" y1="2" x2="8" y2="6"/>
              <line x1="3" y1="10" x2="21" y2="10"/>
              <circle cx="12" cy="15" r="2"/>
            </svg>
            <span>นัดหมายซ่อม</span>
          </div>
          <button type="button" class="schedule-modal-close" onclick="closeScheduleModal()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:24px;height:24px;">
              <line x1="18" y1="6" x2="6" y2="18"/>
              <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>
        
        <form id="scheduleForm" onsubmit="saveSchedule(event)">
          <input type="hidden" id="schedule_repair_id" name="repair_id" value="">
          
          <div class="schedule-modal-body">
            <div class="schedule-info-card">
              <div class="schedule-info-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;">
                  <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                  <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
              </div>
              <div class="schedule-info-content">
                <span class="schedule-info-label">ห้อง</span>
                <span id="schedule_room_info" class="schedule-info-value">-</span>
              </div>
            </div>
            
            <div class="schedule-form-group">
              <label class="schedule-label">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;">
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                  <line x1="16" y1="2" x2="16" y2="6"/>
                  <line x1="8" y1="2" x2="8" y2="6"/>
                  <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                วันที่นัดหมาย
              </label>
              <input type="date" id="scheduled_date" name="scheduled_date" class="schedule-input" required>
            </div>
            
            <div class="schedule-form-row">
              <div class="schedule-form-group">
                <label class="schedule-label">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                  </svg>
                  เวลาเริ่ม
                </label>
                <input type="time" id="scheduled_time_start" name="scheduled_time_start" class="schedule-input" required>
              </div>
              <div class="schedule-form-group">
                <label class="schedule-label">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                  </svg>
                  เวลาสิ้นสุด
                </label>
                <input type="time" id="scheduled_time_end" name="scheduled_time_end" class="schedule-input" required>
              </div>
            </div>
            
            <div class="schedule-form-group">
              <label class="schedule-label">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                  <circle cx="12" cy="7" r="4"/>
                </svg>
                ชื่อช่าง
              </label>
              <input type="text" id="technician_name" name="technician_name" class="schedule-input" placeholder="ระบุชื่อช่าง">
            </div>
            
            <div class="schedule-form-group">
              <label class="schedule-label">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;">
                  <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
                </svg>
                เบอร์โทรช่าง
              </label>
              <input type="tel" id="technician_phone" name="technician_phone" class="schedule-input" placeholder="0xx-xxx-xxxx">
            </div>
            
            <div class="schedule-form-group">
              <label class="schedule-label">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;">
                  <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                หมายเหตุ
              </label>
              <textarea id="schedule_note" name="schedule_note" class="schedule-input schedule-textarea" placeholder="ข้อมูลเพิ่มเติม..."></textarea>
            </div>
          </div>
          
          <div class="schedule-modal-footer">
            <button type="button" class="schedule-btn schedule-btn-cancel" onclick="closeScheduleModal()">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
              </svg>
              ยกเลิก
            </button>
            <button type="submit" class="schedule-btn schedule-btn-save">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                <polyline points="17 21 17 13 7 13 7 21"/>
                <polyline points="7 3 7 8 15 8"/>
              </svg>
              บันทึกนัดหมาย
            </button>
          </div>
        </form>
      </div>
    </div>

    <script src="..//Assets/Javascript/confirm-modal.js" defer></script>
    <script src="..//Assets/Javascript/toast-notification.js" defer></script>
    <script src="..//Assets/Javascript/animate-ui.js" defer></script>
    <script src="..//Assets/Javascript/main.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4" defer></script>
    <script>
      // Get server time (Bangkok timezone) for accurate relative time calculation
      const serverTimeMs = <?php echo (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->getTimestamp() * 1000; ?>;
      const clientTimeAtPageLoadMs = new Date().getTime();
      const timeDiffMs = serverTimeMs - clientTimeAtPageLoadMs;
      
      function getServerTime() {
        return new Date(new Date().getTime() + timeDiffMs);
      }
      
      // Update relative time in real-time
      function updateRelativeTime() {
        const timeElements = document.querySelectorAll('[data-timestamp]');
        timeElements.forEach(element => {
          const timestamp = parseInt(element.getAttribute('data-timestamp'));
          if (isNaN(timestamp)) return;
          
          const targetDateMs = timestamp * 1000;
          const nowMs = getServerTime().getTime();
          const diffMs = nowMs - targetDateMs;
          const diffSecs = Math.floor(diffMs / 1000);
          const diffMins = Math.floor(diffSecs / 60);
          const diffHours = Math.floor(diffSecs / 3600);
          const diffDays = Math.floor(diffSecs / 86400);
          
          let label = '';
          let className = 'time-fresh';
          
          if (diffSecs < 60) {
            label = Math.max(1, diffSecs) + ' วินาทีที่แล้ว';
            className = 'time-fresh';
          } else if (diffMins < 60) {
            label = diffMins + ' นาทีที่แล้ว';
            className = 'time-fresh';
          } else if (diffHours < 24) {
            label = diffHours + ' ชม.ที่แล้ว';
            className = 'time-fresh';
          } else if (diffDays === 1) {
            label = 'เมื่อวาน';
            className = 'time-warning';
          } else if (diffDays === 2) {
            label = 'เมื่อวานซืน';
            className = 'time-warning';
          } else if (diffDays < 4) {
            label = diffDays + ' วันที่แล้ว';
            className = 'time-warning';
          } else if (diffDays < 30) {
            label = diffDays + ' วันที่แล้ว';
            className = 'time-danger';
          } else if (diffDays < 365) {
            const months = Math.floor(diffDays / 30);
            label = months + ' เดือนที่แล้ว';
            className = 'time-danger';
          } else {
            const years = Math.floor(diffDays / 365);
            label = years + ' ปีที่แล้ว';
            className = 'time-danger';
          }
          
          element.textContent = label;
          element.className = 'time-badge relative-time ' + className;
        });
      }
      
      // Update current time display
      function updateCurrentTime() {
        const timeInput = document.getElementById('repair_time');
        const timeHidden = document.getElementById('repair_time_hidden');
        if (timeInput) {
          const now = getServerTime();
          const hours = String(now.getHours()).padStart(2, '0');
          const minutes = String(now.getMinutes()).padStart(2, '0');
          const seconds = String(now.getSeconds()).padStart(2, '0');
          const timeStr = `${hours}:${minutes}:${seconds}`;
          timeInput.value = timeStr;
          if (timeHidden) {
            timeHidden.value = timeStr;
          }
        }
      }
      
      // Update every 100ms for smooth time display
      setInterval(updateCurrentTime, 100);
      setInterval(updateRelativeTime, 500);
      
      // Initial update
      document.addEventListener('DOMContentLoaded', () => {
        updateCurrentTime();
        updateRelativeTime();
      });
      
      function getStatusFromBadge(badge) {
        const style = badge.getAttribute('style');
        if (style.includes('#f97316')) return '0';
        if (style.includes('#60a5fa')) return '1';
        if (style.includes('#22c55e')) return '2';
        return '';
      }
      
      
      let currentFilter = 'all';
      
      // Update repair status
      function updateRepairStatus(repairId, newStatus) {
        // Show confirmation if cancelling
        if (newStatus === '3') {
          showConfirmDialog('ยืนยันการยกเลิก', 'คุณต้องการยกเลิกการแจ้งซ่อมนี้หรือไม่?', 'warning').then(confirmed => {
            if (confirmed) {
              performUpdateRepairStatus(repairId, newStatus);
            }
          });
        } else {
          performUpdateRepairStatus(repairId, newStatus);
        }
      }
      
      function performUpdateRepairStatus(repairId, newStatus) {
        // If cancelling, delete the repair and its image
        if (newStatus === '3') {
          const formData = new FormData();
          formData.append('repair_id', repairId);
          
          fetch('../Manage/delete_repair.php', {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              const row = document.querySelector(`tr[data-repair-id="${repairId}"]`);
              if (row) {
                row.remove();
              }
              showToast('สำเร็จ', 'ยกเลิกการแจ้งซ่อมและลบรูปภาพแล้ว', 'success');
              // Re-apply filter to check if empty message should show
              filterByStatus(currentFilter);
            } else {
              showToast('ผิดพลาด', data.error || 'ไม่สามารถลบได้', 'error');
            }
          })
          .catch(error => {
            console.error('Error:', error);
            showToast('ผิดพลาด', 'เกิดข้อผิดพลาดในการลบ', 'error');
          });
          return;
        }
        
        // For other status updates
        const formData = new FormData();
        formData.append('repair_id', repairId);
        formData.append('repair_status', newStatus);
        
        fetch('../Manage/update_repair_status_ajax.php', {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showToast('สำเร็จ', data.message, 'success');
            setTimeout(() => location.reload(), 1500);
          } else {
            showToast('ผิดพลาด', data.error || 'ไม่สามารถอัปเดตสถานะได้', 'error');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showToast('ผิดพลาด', 'เกิดข้อผิดพลาดในการส่งข้อมูล', 'error');
        });
      }
      
      function filterByStatus(status) {
        currentFilter = status;
        // Save filter status to localStorage
        localStorage.setItem('repairFilterStatus', status);
        
        const table = document.getElementById('table-repairs');
        if (!table) return;
        const rows = table.querySelectorAll('tbody tr');
        let visibleCount = 0;
        
        document.querySelectorAll('.status-filter-btn').forEach(btn => {
          const btnStatus = btn.getAttribute('data-status');
          if (btnStatus === status) {
            btn.classList.add('active');
            btn.style.opacity = '1';
            btn.style.transform = 'scale(1.05)';
          } else {
            btn.classList.remove('active');
            btn.style.opacity = '0.5';
            btn.style.transform = 'scale(1)';
          }
        });
        
        rows.forEach(row => {
          if (!row.getAttribute('data-repair-id')) return;
          if (status === 'all') {
            row.style.display = '';
            visibleCount++;
          } else {
            const statusBadge = row.querySelector('.status-badge');
            if (statusBadge && getStatusFromBadge(statusBadge) === status) {
              row.style.display = '';
              visibleCount++;
            } else {
              row.style.display = 'none';
            }
          }
        });
        
        const tbody = table.querySelector('tbody');
        let emptyMsg = tbody.querySelector('.empty-filter-message');
        if (visibleCount === 0) {
          if (!emptyMsg) {
            emptyMsg = document.createElement('tr');
            emptyMsg.className = 'empty-filter-message';
            emptyMsg.innerHTML = `<td colspan="6" style="text-align:center;padding:2.5rem;color:rgba(255,255,255,0.5);">
              <svg style="width:48px;height:48px;margin-bottom:1rem;opacity:0.5;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
              <br>ไม่มีรายการสำหรับสถานะนี้
            </td>`;
            tbody.appendChild(emptyMsg);
          }
          emptyMsg.style.display = '';
        } else if (emptyMsg) {
          emptyMsg.style.display = 'none';
        }
      }
      
      function changeSortBy(sortValue) {
        const url = new URL(window.location);
        url.searchParams.set('sort', sortValue);
        window.location.href = url.toString();
      }

      // Toggle repair form visibility with modern animation
      function toggleRepairForm() {
        const section = document.getElementById('addRepairSection');
        const icon = document.getElementById('toggleFormIcon');
        const text = document.getElementById('toggleFormText');
        const btn = document.getElementById('toggleFormBtn');
        const isHidden = section.style.display === 'none';
        
        if (isHidden) {
          section.style.display = '';
          section.style.animation = 'fadeInUp 0.4s ease forwards';
          icon.innerHTML = '<polyline points="6 9 12 15 18 9"/>';
          text.textContent = 'ซ่อนฟอร์มแจ้งซ่อม';
          btn.classList.remove('collapsed');
          localStorage.setItem('repairFormVisible', 'true');
        } else {
          section.style.animation = 'fadeOutDown 0.3s ease forwards';
          setTimeout(() => {
            section.style.display = 'none';
          }, 300);
          icon.innerHTML = '<polyline points="9 18 15 12 9 6"/>';
          text.textContent = 'แสดงฟอร์มแจ้งซ่อม';
          btn.classList.add('collapsed');
          localStorage.setItem('repairFormVisible', 'false');
        }
      }
      
      // Add fadeOutDown animation
      const styleSheet = document.createElement('style');
      styleSheet.textContent = `
        @keyframes fadeOutDown {
          from { opacity: 1; transform: translateY(0); }
          to { opacity: 0; transform: translateY(20px); }
        }
      `;
      document.head.appendChild(styleSheet);
      
      document.addEventListener('DOMContentLoaded', () => {
        // Load saved filter status from localStorage
        const savedFilter = localStorage.getItem('repairFilterStatus') || 'all';
        
        // Apply saved filter or default
        filterByStatus(savedFilter);
        
        // Restore form visibility from localStorage
        const isFormVisible = localStorage.getItem('repairFormVisible') !== 'false';
        const section = document.getElementById('addRepairSection');
        const icon = document.getElementById('toggleFormIcon');
        const text = document.getElementById('toggleFormText');
        const btn = document.getElementById('toggleFormBtn');
        if (!isFormVisible) {
          section.style.display = 'none';
          icon.innerHTML = '<polyline points="9 18 15 12 9 6"/>';
          text.textContent = 'แสดงฟอร์มแจ้งซ่อม';
          btn.classList.add('collapsed');
        }

        // Initialize DataTable
        const repairTableEl = document.querySelector('#table-repairs');
        if (repairTableEl && window.simpleDatatables) {
          try {
            const dt = new simpleDatatables.DataTable(repairTableEl, {
              searchable: true,
              fixedHeight: false,
              perPage: 5,
              perPageSelect: [5, 10, 25, 50, 100],
              labels: {
                placeholder: 'ค้นหา...',
                perPage: '{select} แถวต่อหน้า',
                noRows: 'ไม่มีข้อมูล',
                info: 'แสดง {start}–{end} จาก {rows} รายการ'
              },
              columns: [
                { select: 5, sortable: false }
              ]
            });
            window.__repairDataTable = dt;
          } catch (err) {
            console.error('Failed to init repair table', err);
          }
        }
        
        const imageInput = document.getElementById('repair_image');
        const imagePreview = document.getElementById('image_preview');
        if (imageInput) {
          imageInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            imagePreview.innerHTML = '';
            imagePreview.style.display = 'none';
            if (file) {
              if (file.size > 5 * 1024 * 1024) {
                imagePreview.style.display = 'block';
                imagePreview.innerHTML = `
                  <div style="display:flex; align-items:center; gap:0.5rem; color:#ef4444;">
                    <svg style="width:20px;height:20px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    ไฟล์ใหญ่เกินไป (ไม่เกิน 5MB)
                  </div>`;
                imageInput.value = '';
                return;
              }
              if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                imagePreview.style.display = 'block';
                imagePreview.innerHTML = `
                  <div style="display:flex; align-items:center; gap:0.5rem; color:#ef4444;">
                    <svg style="width:20px;height:20px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    ประเภทไฟล์ไม่ถูกต้อง
                  </div>`;
                imageInput.value = '';
                return;
              }
              const reader = new FileReader();
              reader.onload = (event) => {
                imagePreview.style.display = 'block';
                imagePreview.innerHTML = `
                  <div style="display:flex; flex-direction:column; gap:0.75rem; align-items:flex-start;">
                    <img src="${event.target.result}" alt="Preview" style="max-width:250px; max-height:250px; border-radius:12px; object-fit:contain; box-shadow: 0 8px 25px rgba(0,0,0,0.2);" />
                    <div style="display:flex; align-items:center; gap:0.5rem; color:#22c55e; font-weight:500;">
                      <svg style="width:18px;height:18px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                      ${file.name}
                    </div>
                  </div>`;
              };
              reader.readAsDataURL(file);
            }
          });
        }
        
        // Add entrance animations for stat cards
        const statCards = document.querySelectorAll('.repair-stat-card');
        statCards.forEach((card, index) => {
          card.style.animation = `fadeInUp 0.5s ease forwards`;
          card.style.animationDelay = `${index * 0.1}s`;
          card.style.opacity = '0';
        });
      });

      // Sync hidden fields on form submit
      document.getElementById('repairForm').addEventListener('submit', function(e) {
        const ctr_id = document.getElementById('ctr_id').value;
        const repair_desc = document.getElementById('repair_desc').value.trim();
        
        console.log('Form submitting with:', {
          ctr_id: ctr_id,
          repair_desc: repair_desc,
          repair_time: document.getElementById('repair_time_hidden').value
        });
        
        if (!ctr_id || !repair_desc) {
          console.warn('Form validation failed: ctr_id=' + ctr_id + ', desc=' + repair_desc);
        }
        
        updateCurrentTime(); // Ensure latest time is in hidden fields
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
      
      // ==================== Schedule Modal Functions ====================
      let currentScheduleRepairId = null;
      let lastTechnicianInfo = null; // เก็บข้อมูลช่างล่าสุด
      
      // Load last technician info on page load
      async function loadLastTechnician() {
        try {
          const response = await fetch('../Manage/update_repair_schedule.php?action=last_technician');
          const data = await response.json();
          if (data.success && data.data) {
            lastTechnicianInfo = data.data;
          }
        } catch (err) {
          console.log('Could not load last technician info');
        }
      }
      loadLastTechnician();
      
      function openScheduleModal(repairId, roomName) {
        currentScheduleRepairId = repairId;
        const modal = document.getElementById('scheduleModal');
        const form = document.getElementById('scheduleForm');
        
        // Reset form
        form.reset();
        document.getElementById('schedule_repair_id').value = repairId;
        document.getElementById('schedule_room_info').textContent = 'ห้อง ' + (roomName || 'ไม่ระบุ');
        
        // Set default date to today
        const today = new Date();
        const dateStr = today.toISOString().split('T')[0];
        document.getElementById('scheduled_date').value = dateStr;
        
        // Set default time
        document.getElementById('scheduled_time_start').value = '09:00';
        document.getElementById('scheduled_time_end').value = '12:00';
        
        // Pre-fill last technician info if available
        if (lastTechnicianInfo) {
          document.getElementById('technician_name').value = lastTechnicianInfo.technician_name || '';
          document.getElementById('technician_phone').value = lastTechnicianInfo.technician_phone || '';
        }
        
        // Load existing schedule if any (will override last technician if exists)
        loadSchedule(repairId);
        
        // Show modal
        modal.style.display = 'flex';
        setTimeout(() => {
          modal.classList.add('active');
        }, 10);
        
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
      }
      
      function closeScheduleModal() {
        const modal = document.getElementById('scheduleModal');
        modal.classList.remove('active');
        
        setTimeout(() => {
          modal.style.display = 'none';
        }, 300);
        
        document.body.style.overflow = '';
        currentScheduleRepairId = null;
      }
      
      async function loadSchedule(repairId) {
        try {
          const response = await fetch(`../Manage/get_repair_schedule.php?repair_id=${repairId}`);
          const data = await response.json();
          
          if (data.success && data.schedule) {
            const s = data.schedule;
            if (s.scheduled_date) document.getElementById('scheduled_date').value = s.scheduled_date;
            if (s.scheduled_time_start) document.getElementById('scheduled_time_start').value = s.scheduled_time_start;
            if (s.scheduled_time_end) document.getElementById('scheduled_time_end').value = s.scheduled_time_end;
            if (s.technician_name) document.getElementById('technician_name').value = s.technician_name;
            if (s.technician_phone) document.getElementById('technician_phone').value = s.technician_phone;
            if (s.schedule_note) document.getElementById('schedule_note').value = s.schedule_note;
          }
        } catch (err) {
          console.log('No existing schedule or error loading:', err);
        }
      }
      
      async function saveSchedule(event) {
        event.preventDefault();
        
        const form = document.getElementById('scheduleForm');
        const formData = new FormData(form);
        
        // Show loading state
        const saveBtn = form.querySelector('.schedule-btn-save');
        const originalText = saveBtn.innerHTML;
        saveBtn.innerHTML = `
          <svg class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;">
            <circle cx="12" cy="12" r="10" stroke-dasharray="50" stroke-dashoffset="15"/>
          </svg>
          กำลังบันทึก...
        `;
        saveBtn.disabled = true;
        
        try {
          const response = await fetch('../Manage/update_repair_schedule.php', {
            method: 'POST',
            body: formData
          });
          
          const data = await response.json();
          
          if (data.success) {
            // Update last technician info for next use
            const techName = document.getElementById('technician_name').value;
            const techPhone = document.getElementById('technician_phone').value;
            if (techName || techPhone) {
              lastTechnicianInfo = {
                technician_name: techName,
                technician_phone: techPhone
              };
            }
            
            // Show success toast
            if (typeof showToast === 'function') {
              showToast('บันทึกนัดหมายเรียบร้อยแล้ว', 'success');
            }
            
            closeScheduleModal();
            
            // Reload page to show updated data
            setTimeout(() => {
              window.location.reload();
            }, 500);
          } else {
            throw new Error(data.error || data.message || 'เกิดข้อผิดพลาด');
          }
        } catch (err) {
          console.error('Save schedule error:', err);
          if (typeof showToast === 'function') {
            showToast(err.message || 'ไม่สามารถบันทึกได้', 'error');
          } else {
            alert(err.message || 'เกิดข้อผิดพลาด');
          }
          
          saveBtn.innerHTML = originalText;
          saveBtn.disabled = false;
        }
      }
      
      // Close modal on overlay click
      document.getElementById('scheduleModal').addEventListener('click', function(e) {
        if (e.target === this) {
          closeScheduleModal();
        }
      });
      
      // Close modal on escape key
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          const modal = document.getElementById('scheduleModal');
          if (modal.style.display !== 'none') {
            closeScheduleModal();
          }
        }
      });
    </script>
  </body>
</html>
