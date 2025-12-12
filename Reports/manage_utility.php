<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

// ตัวเลือกแสดงห้อง: all = ทุกห้อง, occupied = เฉพาะห้องมีผู้เช่า
$showMode = $_GET['show'] ?? 'all';

// ดึงข้อมูลห้องทั้งหมด (รวม ctr_id สำหรับบันทึก utility)
// เฉพาะสัญญา active (ctr_status = '0') เท่านั้น - สัญญาที่ยกเลิกแล้ว (1) ไม่ควรจดมิเตอร์ได้
$rooms = [];
try {
    if ($showMode === 'occupied') {
        // เฉพาะห้องที่มีผู้เช่า (มี contract active)
        $stmt = $pdo->query("
            SELECT r.room_id, r.room_number, r.room_status, c.ctr_id, t.tnt_name
            FROM room r
            JOIN contract c ON r.room_id = c.room_id AND c.ctr_status = '0'
            JOIN tenant t ON c.tnt_id = t.tnt_id
            ORDER BY CAST(r.room_number AS UNSIGNED) ASC
        ");
    } else {
        // ทุกห้อง - LEFT JOIN เพื่อให้ห้องว่างก็แสดงด้วย (แต่ ctr_id จะ NULL ถ้าไม่มีสัญญา active)
        $stmt = $pdo->query("
            SELECT r.room_id, r.room_number, r.room_status, 
                   c.ctr_id, COALESCE(t.tnt_name, '') as tnt_name
            FROM room r
            LEFT JOIN contract c ON r.room_id = c.room_id AND c.ctr_status = '0'
            LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
            ORDER BY CAST(r.room_number AS UNSIGNED) ASC
        ");
    }
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
}

// ดึงอัตราค่าน้ำค่าไฟ (ล่าสุดตาม effective_date)
$waterRate = 18;
$electricRate = 8;
try {
    $rateStmt = $pdo->query("SELECT * FROM rate ORDER BY effective_date DESC, rate_id DESC LIMIT 1");
    $rate = $rateStmt->fetch(PDO::FETCH_ASSOC);
    if ($rate) {
        $waterRate = (int)$rate['rate_water'];
        $electricRate = (int)$rate['rate_elec'];
    }
} catch (PDOException $e) {}

// ดึงเดือน/ปีปัจจุบัน
$currentMonth = date('m');
$currentYear = date('Y');
$selectedMonth = $_GET['month'] ?? $currentMonth;
$selectedYear = $_GET['year'] ?? $currentYear;

// ดึงข้อมูลมิเตอร์ล่าสุดของแต่ละห้อง (ผ่าน contract)
$latestReadings = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.*, r.room_id, r.room_number
        FROM utility u
        JOIN contract c ON u.ctr_id = c.ctr_id
        JOIN room r ON c.room_id = r.room_id
        WHERE MONTH(u.utl_date) = ? AND YEAR(u.utl_date) = ?
        ORDER BY CAST(r.room_number AS UNSIGNED) ASC
    ");
    $stmt->execute([$selectedMonth, $selectedYear]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $latestReadings[$row['room_id']] = $row;
    }
} catch (PDOException $e) {}

// ดึงค่าตั้งค่าระบบ
$siteName = 'Sangthian Dormitory';
$logoFilename = 'Logo.jpg';
$themeColor = '#0f172a';
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename', 'theme_color')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
        if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
        if ($row['setting_key'] === 'theme_color') $themeColor = $row['setting_value'];
    }
} catch (PDOException $e) {}

// ตรวจสอบว่าเป็น light theme หรือไม่
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

// Debug: แสดง POST data (ลบออกหลังทดสอบเสร็จ)
$debugMode = false;
if ($debugMode && $_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<pre style='background:#333;color:#0f0;padding:1rem;'>POST DATA: ";
    print_r($_POST);
    echo "</pre>";
}

// บันทึกข้อมูลมิเตอร์
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_meter'])) {
    $roomId = (int)$_POST['room_id'];
    $ctrId = (int)$_POST['ctr_id'];
    $waterMeter = (int)$_POST['water_meter'];
    $electricMeter = (int)$_POST['electric_meter'];
    $meterDate = $_POST['meter_date'];
    
    try {
        // ตรวจสอบว่ามีข้อมูลเดือนนี้แล้วหรือยัง
        $checkStmt = $pdo->prepare("
            SELECT utl_id, utl_water_start, utl_elec_start FROM utility 
            WHERE ctr_id = ? AND MONTH(utl_date) = MONTH(?) AND YEAR(utl_date) = YEAR(?)
        ");
        $checkStmt->execute([$ctrId, $meterDate, $meterDate]);
        $existing = $checkStmt->fetch();
        
        if ($existing) {
            // อัพเดท - ใช้ค่า start เดิมที่บันทึกไว้แล้ว
            $waterOld = (int)$existing['utl_water_start'];
            $electricOld = (int)$existing['utl_elec_start'];
            
            $updateStmt = $pdo->prepare("
                UPDATE utility SET 
                    utl_water_end = ?,
                    utl_elec_end = ?,
                    utl_date = ?
                WHERE utl_id = ?
            ");
            $updateStmt->execute([
                $waterMeter,
                $electricMeter,
                $meterDate, $existing['utl_id']
            ]);
        } else {
            // เพิ่มใหม่ - ดึงค่าจากเดือนก่อน
            $prevStmt = $pdo->prepare("
                SELECT utl_water_end, utl_elec_end 
                FROM utility 
                WHERE ctr_id = ? 
                ORDER BY utl_date DESC 
                LIMIT 1
            ");
            $prevStmt->execute([$ctrId]);
            $prev = $prevStmt->fetch(PDO::FETCH_ASSOC);
            
            $waterOld = $prev ? (int)$prev['utl_water_end'] : 0;
            $electricOld = $prev ? (int)$prev['utl_elec_end'] : 0;
            
            $insertStmt = $pdo->prepare("
                INSERT INTO utility (ctr_id, utl_water_start, utl_water_end, utl_elec_start, utl_elec_end, utl_date)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([
                $ctrId, $waterOld, $waterMeter, $electricOld, $electricMeter, $meterDate
            ]);
        }
        
        // ===== อัพเดต expense table ด้วย =====
        // คำนวณหน่วยที่ใช้
        $waterUsed = $waterMeter - $waterOld;
        $elecUsed = $electricMeter - $electricOld;
        
        // ดึงเดือน/ปีจาก meter_date
        $expMonth = date('Y-m-01', strtotime($meterDate)); // วันที่ 1 ของเดือน
        
        // อัพเดต expense ถ้ามี record อยู่แล้ว - ใช้ rate ล่าสุดจากตัวแปร $waterRate และ $electricRate
        $updateExpStmt = $pdo->prepare("
            UPDATE expense 
            SET exp_elec_unit = ?,
                exp_water_unit = ?,
                rate_elec = ?,
                rate_water = ?,
                exp_elec_chg = ? * ?,
                exp_water = ? * ?,
                exp_total = room_price + (? * ?) + (? * ?)
            WHERE ctr_id = ? 
            AND MONTH(exp_month) = MONTH(?) 
            AND YEAR(exp_month) = YEAR(?)
        ");
        $updateExpStmt->execute([
            $elecUsed, $waterUsed, 
            $electricRate, $waterRate,
            $elecUsed, $electricRate,
            $waterUsed, $waterRate,
            $elecUsed, $electricRate, $waterUsed, $waterRate,
            $ctrId, $expMonth, $expMonth
        ]);
        
        // ใช้ค่าจาก POST สำหรับ redirect
        $redirectMonth = $_POST['redirect_month'] ?? $selectedMonth;
        $redirectYear = $_POST['redirect_year'] ?? $selectedYear;
        $redirectShow = $_POST['redirect_show'] ?? $showMode;
        
        $_SESSION['success'] = "บันทึกมิเตอร์ห้อง {$_POST['room_number']} และอัพเดตค่าใช้จ่ายสำเร็จ!";
        header("Location: manage_utility.php?month=$redirectMonth&year=$redirectYear&show=$redirectShow");
        exit;
    } catch (PDOException $e) {
        $error = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }
}

$thaiMonths = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 
               'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
?>
<!doctype html>
<html lang="th" class="<?php echo $lightThemeClass; ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($siteName); ?> - จดมิเตอร์น้ำไฟ</title>
    <link rel="icon" type="image/jpeg" href="../Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>">
    <link rel="stylesheet" href="../Assets/Css/animate-ui.css">
    <link rel="stylesheet" href="../Assets/Css/main.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4/dist/style.css">
    <link rel="stylesheet" href="../Assets/Css/datatable-modern.css">
    <style>
        /* ===== Light Theme Overrides ===== */
        html.light-theme .page-title,
        html.light-theme .utility-container,
        html.light-theme .rate-info span,
        html.light-theme .datatable-table th,
        html.light-theme .datatable-table td,
        html.light-theme label,
        html.light-theme .show-mode-text {
            color: #111827 !important;
        }
        html.light-theme .month-selector select,
        html.light-theme .datatable-input,
        html.light-theme .datatable-selector {
            background: rgba(0,0,0,0.05) !important;
            border-color: rgba(0,0,0,0.12) !important;
            color: #111827 !important;
        }
        html.light-theme .rate-info {
            background: rgba(59, 130, 246, 0.08) !important;
            border-color: rgba(59, 130, 246, 0.2) !important;
        }
        html.light-theme .rate-info span {
            color: #374151 !important;
        }
        html.light-theme .rate-info strong {
            color: #2563eb !important;
        }
        html.light-theme .utility-table input {
            background: rgba(255,255,255,0.95) !important;
            border-color: rgba(0,0,0,0.2) !important;
            color: #111827 !important;
        }
        html.light-theme .utility-table input:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15) !important;
        }
        /* Status badges - ensure proper colors in light mode */
        html.light-theme .status-badge.saved {
            background: rgba(34, 197, 94, 0.15) !important;
            color: #15803d !important;
        }
        html.light-theme .status-badge.pending {
            background: rgba(251, 191, 36, 0.2) !important;
            color: #b45309 !important;
        }
        html.light-theme .status-badge.empty,
        html.light-theme .status-empty {
            background: rgba(148, 163, 184, 0.25) !important;
            color: #475569 !important;
        }
        html.light-theme .status-saved {
            background: #22c55e !important;
            color: #ffffff !important;
        }
        html.light-theme .btn-save,
        html.light-theme .btn-update,
        html.light-theme .btn-save-small {
            color: #ffffff !important;
        }
        html.light-theme .view-toggle-btn.active {
            color: #ffffff !important;
        }
        html.light-theme .datatable-info,
        html.light-theme .datatable-pagination a {
            color: #374151 !important;
        }
        /* Row with data - light green background */
        html.light-theme tr.has-reading,
        html.light-theme .utility-table tbody tr:has(.status-badge.saved) {
            background: rgba(34, 197, 94, 0.08) !important;
        }
        /* Page title SVG */
        html.light-theme .page-title svg {
            stroke: #111827 !important;
        }
        /* View toggle buttons */
        html.light-theme .view-toggle-btn,
        html.light-theme .view-btn {
            color: #374151 !important;
            border-color: rgba(0,0,0,0.15) !important;
            background: rgba(0,0,0,0.05) !important;
        }
        html.light-theme .view-toggle-btn svg,
        html.light-theme .view-btn svg {
            stroke: #374151 !important;
        }
        html.light-theme .view-toggle-btn.active,
        html.light-theme .view-btn.active {
            color: #ffffff !important;
            background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
        }
        html.light-theme .view-toggle-btn.active svg,
        html.light-theme .view-btn.active svg {
            stroke: #ffffff !important;
        }
        /* Mode buttons */
        html.light-theme .mode-btn {
            background: rgba(0,0,0,0.05) !important;
            border-color: rgba(0,0,0,0.15) !important;
            color: #374151 !important;
        }
        html.light-theme .mode-btn svg {
            stroke: #374151 !important;
        }
        html.light-theme .mode-btn:hover {
            background: rgba(59, 130, 246, 0.15) !important;
            border-color: rgba(59, 130, 246, 0.4) !important;
            color: #2563eb !important;
        }
        html.light-theme .mode-btn.active {
            background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
            border-color: #3b82f6 !important;
            color: #ffffff !important;
        }
        html.light-theme .mode-btn.active svg {
            stroke: #ffffff !important;
        }
        /* Room cards in light theme */
        html.light-theme .room-card {
            background: rgba(255, 255, 255, 0.9) !important;
            border-color: rgba(0, 0, 0, 0.1) !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
        }
        html.light-theme .room-card:hover {
            border-color: rgba(59, 130, 246, 0.4) !important;
        }
        html.light-theme .room-card.has-data {
            border-color: rgba(34, 197, 94, 0.4) !important;
            background: rgba(34, 197, 94, 0.05) !important;
        }
        html.light-theme .room-header {
            border-bottom-color: rgba(0, 0, 0, 0.1) !important;
        }
        html.light-theme .room-number {
            color: #2563eb !important;
        }
        html.light-theme .tenant-name {
            color: #64748b !important;
        }
        html.light-theme .meter-group label {
            color: #475569 !important;
        }
        html.light-theme .meter-group input {
            background: rgba(255, 255, 255, 0.95) !important;
            border-color: rgba(0, 0, 0, 0.15) !important;
            color: #111827 !important;
        }
        html.light-theme .meter-group input:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15) !important;
        }
        html.light-theme .old-reading {
            color: #6b7280 !important;
        }
        html.light-theme .meter-summary {
            background: rgba(0, 0, 0, 0.03) !important;
            color: #475569 !important;
        }
        html.light-theme .meter-summary .used {
            color: #d97706 !important;
        }
        html.light-theme .meter-summary .cost {
            color: #15803d !important;
        }
        html.light-theme .no-contract-msg {
            background: rgba(0, 0, 0, 0.05) !important;
            color: #64748b !important;
        }
        /* Utility table in light theme */
        html.light-theme .utility-table {
            background: rgba(255, 255, 255, 0.95) !important;
        }
        html.light-theme .utility-table th {
            background: rgba(241, 245, 249, 0.95) !important;
            color: #475569 !important;
            border-bottom: 2px solid rgba(0, 0, 0, 0.1) !important;
        }
        html.light-theme .utility-table td {
            color: #374151 !important;
            border-bottom-color: rgba(0, 0, 0, 0.06) !important;
        }
        html.light-theme .utility-table tr:hover td {
            background: rgba(59, 130, 246, 0.06) !important;
        }
        html.light-theme .utility-table td[style*="color: #fbbf24"] {
            color: #d97706 !important;
        }
        html.light-theme .utility-table td[style*="color: #22c55e"] {
            color: #15803d !important;
        }
        /* DataTable overrides for light theme */
        html.light-theme .datatable-wrapper {
            background: transparent !important;
        }
        html.light-theme .datatable-top,
        html.light-theme .datatable-bottom {
            background: transparent !important;
        }
        html.light-theme .datatable-pagination li a {
            color: #374151 !important;
            background: rgba(0, 0, 0, 0.05) !important;
            border-color: rgba(0, 0, 0, 0.1) !important;
        }
        html.light-theme .datatable-pagination li.active a {
            background: #3b82f6 !important;
            color: #ffffff !important;
        }
        /* Empty state in light theme */
        html.light-theme div[style*="text-align: center"][style*="padding: 3rem"] {
            background: rgba(0, 0, 0, 0.03) !important;
            color: #64748b !important;
        }
        html.light-theme div[style*="text-align: center"][style*="padding: 3rem"] svg {
            stroke: #94a3b8 !important;
        }

        .utility-container {
            padding: 1.5rem;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .page-title {
            font-size: 1.5rem;
            color: #f8fafc;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .month-selector {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        .month-selector select {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.15);
            background: rgba(30, 41, 59, 0.8);
            color: #f8fafc;
            font-size: 0.95rem;
        }
        .mode-btn {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255,255,255,0.15);
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .mode-btn:hover {
            background: rgba(59, 130, 246, 0.2);
            border-color: rgba(59, 130, 246, 0.5);
            color: #60a5fa;
        }
        .mode-btn.active {
            background: rgba(59, 130, 246, 0.3);
            border-color: #3b82f6;
            color: #60a5fa;
            font-weight: 600;
        }
        .rate-info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }
        .rate-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .rate-item span {
            color: #94a3b8;
        }
        .rate-item strong {
            color: #60a5fa;
        }
        .rooms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1rem;
        }
        .room-card {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 1.25rem;
            transition: all 0.3s;
        }
        .room-card:hover {
            border-color: rgba(59, 130, 246, 0.5);
        }
        .room-card.has-data {
            border-color: rgba(34, 197, 94, 0.5);
            background: rgba(34, 197, 94, 0.05);
        }
        .room-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .room-number {
            font-size: 1.25rem;
            font-weight: 700;
            color: #60a5fa;
        }
        .tenant-name {
            font-size: 0.85rem;
            color: #94a3b8;
        }
        .status-saved {
            background: #22c55e;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .meter-form {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .meter-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }
        .meter-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .meter-group label {
            font-size: 0.8rem;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        .meter-group input {
            padding: 0.6rem 0.75rem;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.15);
            background: rgba(15, 23, 42, 0.8);
            color: #f8fafc;
            font-size: 1rem;
            width: 100%;
        }
        .meter-group input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        .meter-summary {
            background: rgba(15, 23, 42, 0.5);
            border-radius: 8px;
            padding: 0.75rem;
            font-size: 0.85rem;
            color: #94a3b8;
        }
        .meter-summary .used {
            color: #fbbf24;
            font-weight: 600;
        }
        .meter-summary .cost {
            color: #22c55e;
            font-weight: 600;
        }
        .btn-save {
            padding: 0.6rem 1rem;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
        }
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }
        .btn-save:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        .success-toast {
            position: fixed;
            top: 1rem;
            right: 1rem;
            background: #22c55e;
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            z-index: 9999;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .old-reading {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.25rem;
        }
        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: flex-start; }
            .rooms-grid { grid-template-columns: 1fr; }
            .meter-row { grid-template-columns: 1fr; }
        }
        /* View Toggle Buttons */
        .view-toggle {
            display: flex;
            gap: 0.5rem;
            margin-left: auto;
        }
        .view-btn {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255,255,255,0.15);
            color: #94a3b8;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .view-btn:hover {
            background: rgba(59, 130, 246, 0.2);
            border-color: rgba(59, 130, 246, 0.5);
            color: #60a5fa;
        }
        .view-btn.active {
            background: rgba(59, 130, 246, 0.3);
            border-color: #3b82f6;
            color: #60a5fa;
            font-weight: 600;
        }
        /* Table View Styles */
        .table-view {
            display: none;
        }
        .table-view.active {
            display: block;
        }
        .grid-view {
            display: grid;
        }
        .grid-view.hidden {
            display: none;
        }
        .table-view {
            overflow-x: auto;
            width: 100%;
        }
        .utility-table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
            background: rgba(30, 41, 59, 0.8);
            border-radius: 12px;
            overflow: hidden;
            font-size: 0.85rem;
        }
        .utility-table th {
            background: rgba(15, 23, 42, 0.8);
            color: #94a3b8;
            padding: 0.6rem 0.5rem;
            text-align: center;
            font-weight: 600;
            font-size: 0.75rem;
            white-space: nowrap;
        }
        .utility-table td {
            padding: 0.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            color: #e2e8f0;
            text-align: center;
            font-size: 0.8rem;
        }
        .utility-table tr:hover td {
            background: rgba(59, 130, 246, 0.1);
        }
        .utility-table input[type="number"] {
            width: 70px;
            padding: 0.3rem 0.4rem;
            border-radius: 6px;
            border: 1px solid rgba(255,255,255,0.15);
            background: rgba(15, 23, 42, 0.8);
            color: #f8fafc;
            font-size: 0.85rem;
            text-align: center;
        }
        .utility-table .btn-save-small {
            padding: 0.3rem 0.5rem;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.75rem;
            white-space: nowrap;
        }
        .utility-table .btn-save-small:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.4);
        }
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-badge.saved {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }
        .status-badge.pending {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
        }
        .status-badge.empty {
            background: rgba(148, 163, 184, 0.2);
            color: #94a3b8;
        }
    </style>
</head>
<body class="reports-page">
    <div class="app-shell">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="app-main">
            <div class="utility-container">
                <?php if (isset($_SESSION['success'])): ?>
                <div class="success-toast" id="successToast">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
                <script>setTimeout(() => document.getElementById('successToast')?.remove(), 3000);</script>
                <?php endif; ?>

                <div class="page-header">
                    <h1 class="page-title"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg> จดมิเตอร์น้ำ-ไฟ</h1>
                    <form class="month-selector" method="get">
                        <input type="hidden" name="show" value="<?php echo htmlspecialchars($showMode); ?>">
                        <select name="month" onchange="this.form.submit()">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $selectedMonth == $m ? 'selected' : ''; ?>>
                                <?php echo $thaiMonths[$m]; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                        <select name="year" onchange="this.form.submit()">
                            <?php for ($y = $currentYear - 2; $y <= $currentYear + 1; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $selectedYear == $y ? 'selected' : ''; ?>>
                                <?php echo $y + 543; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </form>
                </div>

                <!-- ปุ่มเลือกโหมดแสดงห้อง -->
                <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap; align-items: center;">
                    <a href="?month=<?php echo $selectedMonth; ?>&year=<?php echo $selectedYear; ?>&show=all" 
                       class="mode-btn <?php echo $showMode === 'all' ? 'active' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg> ทุกห้อง
                    </a>
                    <a href="?month=<?php echo $selectedMonth; ?>&year=<?php echo $selectedYear; ?>&show=occupied" 
                       class="mode-btn <?php echo $showMode === 'occupied' ? 'active' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> เฉพาะห้องมีผู้เช่า
                    </a>
                    
                    <div class="view-toggle">
                        <button type="button" class="view-btn active" id="gridViewBtn" onclick="switchView('grid')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg> การ์ด
                        </button>
                        <button type="button" class="view-btn" id="tableViewBtn" onclick="switchView('table')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M3 9h18"/><path d="M3 15h18"/><path d="M9 3v18"/></svg> ตาราง
                        </button>
                    </div>
                </div>

                <div class="rate-info">
                    <div class="rate-item">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/></svg> <span>ค่าน้ำ:</span> <strong><?php echo number_format($waterRate); ?> บาท/หน่วย</strong>
                    </div>
                    <div class="rate-item">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#eab308" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg> <span>ค่าไฟ:</span> <strong><?php echo number_format($electricRate); ?> บาท/หน่วย</strong>
                    </div>
                    <div class="rate-item">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg> <span>เดือน:</span> <strong><?php echo $thaiMonths[(int)$selectedMonth] . ' ' . ((int)$selectedYear + 543); ?></strong>
                    </div>
                </div>

                <?php if (empty($rooms)): ?>
                <div style="text-align: center; padding: 3rem; color: #94a3b8; background: rgba(30,41,59,0.5); border-radius: 16px;">
                    <div style="margin-bottom: 1rem;"><svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
                    <?php if ($showMode === 'occupied'): ?>
                    <p style="font-size: 1.1rem; margin-bottom: 1rem;">ไม่มีห้องที่มีผู้เช่าในขณะนี้</p>
                    <a href="?month=<?php echo $selectedMonth; ?>&year=<?php echo $selectedYear; ?>&show=all" 
                       style="color: #60a5fa; text-decoration: underline;">
                        ดูทุกห้องแทน →
                    </a>
                    <?php else: ?>
                    <p style="font-size: 1.1rem;">ไม่พบห้องพักในระบบ</p>
                    <p style="font-size: 0.9rem; margin-top: 0.5rem;">กรุณาเพิ่มห้องพักใน <a href="manage_rooms.php" style="color:#60a5fa;">จัดการห้องพัก</a></p>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <!-- Grid View (Cards) -->
                <div class="rooms-grid grid-view" id="gridView">
                    <?php foreach ($rooms as $room): 
                        $reading = $latestReadings[$room['room_id']] ?? null;
                        $hasData = $reading !== null;
                        $ctrId = $room['ctr_id'] ?? null;
                        
                        // ดึงค่าเดิมจากเดือนก่อน (ใช้ ctr_id)
                        $waterOld = 0;
                        $electricOld = 0;
                        if ($ctrId) {
                            $prevStmt = $pdo->prepare("
                                SELECT utl_water_end, utl_elec_end 
                                FROM utility 
                                WHERE ctr_id = ? AND utl_date < ?
                                ORDER BY utl_date DESC 
                                LIMIT 1
                            ");
                            $prevStmt->execute([$ctrId, "$selectedYear-$selectedMonth-01"]);
                            $prev = $prevStmt->fetch(PDO::FETCH_ASSOC);
                            $waterOld = $prev ? (int)$prev['utl_water_end'] : ($reading ? (int)$reading['utl_water_start'] : 0);
                            $electricOld = $prev ? (int)$prev['utl_elec_end'] : ($reading ? (int)$reading['utl_elec_start'] : 0);
                        }
                    ?>
                    <div class="room-card <?php echo $hasData ? 'has-data' : ''; ?> <?php echo !$ctrId ? 'no-contract' : ''; ?>">
                        <div class="room-header">
                            <div>
                                <div class="room-number"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg> ห้อง <?php echo htmlspecialchars($room['room_number']); ?></div>
                                <div class="tenant-name"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> <?php echo htmlspecialchars($room['tnt_name'] ?: 'ว่าง'); ?></div>
                            </div>
                            <?php if ($hasData): ?>
                            <span class="status-saved"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> บันทึกแล้ว</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($ctrId): ?>
                        <form class="meter-form" method="post" action="">
                            <input type="hidden" name="save_meter" value="1">
                            <input type="hidden" name="room_id" value="<?php echo $room['room_id']; ?>">
                            <input type="hidden" name="ctr_id" value="<?php echo $ctrId; ?>">
                            <input type="hidden" name="room_number" value="<?php echo htmlspecialchars($room['room_number']); ?>">
                            <input type="hidden" name="meter_date" value="<?php echo $selectedYear . '-' . str_pad($selectedMonth, 2, '0', STR_PAD_LEFT) . '-' . date('d'); ?>">
                            <input type="hidden" name="redirect_month" value="<?php echo $selectedMonth; ?>">
                            <input type="hidden" name="redirect_year" value="<?php echo $selectedYear; ?>">
                            <input type="hidden" name="redirect_show" value="<?php echo $showMode; ?>">
                            
                            <div class="meter-row">
                                <div class="meter-group">
                                    <label><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/></svg> มิเตอร์น้ำ (ใหม่)</label>
                                    <input type="number" name="water_meter" 
                                           value="<?php echo $hasData ? $reading['utl_water_end'] : ''; ?>" 
                                           placeholder="เลขมิเตอร์" min="0" required
                                           data-old="<?php echo $waterOld; ?>">
                                    <div class="old-reading">เดิม: <?php echo number_format($waterOld); ?></div>
                                </div>
                                <div class="meter-group">
                                    <label><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#eab308" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg> มิเตอร์ไฟ (ใหม่)</label>
                                    <input type="number" name="electric_meter" 
                                           value="<?php echo $hasData ? $reading['utl_elec_end'] : ''; ?>" 
                                           placeholder="เลขมิเตอร์" min="0" required
                                           data-old="<?php echo $electricOld; ?>">
                                    <div class="old-reading">เดิม: <?php echo number_format($electricOld); ?></div>
                                </div>
                            </div>
                            
                            <div class="meter-summary" id="summary_<?php echo $room['room_id']; ?>">
                                <?php if ($hasData): 
                                    $waterUsed = (int)$reading['utl_water_end'] - (int)$reading['utl_water_start'];
                                    $elecUsed = (int)$reading['utl_elec_end'] - (int)$reading['utl_elec_start'];
                                    $waterCost = $waterUsed * $waterRate;
                                    $elecCost = $elecUsed * $electricRate;
                                ?>
                                น้ำใช้: <span class="used"><?php echo number_format($waterUsed); ?></span> หน่วย = 
                                <span class="cost">฿<?php echo number_format($waterCost); ?></span> | 
                                ไฟใช้: <span class="used"><?php echo number_format($elecUsed); ?></span> หน่วย = 
                                <span class="cost">฿<?php echo number_format($elecCost); ?></span>
                                <?php else: ?>
                                กรอกเลขมิเตอร์เพื่อคำนวณค่าใช้จ่าย
                                <?php endif; ?>
                            </div>
                            
                            <button type="button" class="btn-save" onclick="this.closest('form').submit();">
                                <?php if ($hasData): ?><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> อัพเดท<?php else: ?><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> บันทึก<?php endif; ?>
                            </button>
                        </form>
                        <?php else: ?>
                        <div class="no-contract-msg" style="padding: 1rem; text-align: center; color: #94a3b8; background: rgba(0,0,0,0.2); border-radius: 8px;">
                            ห้องว่าง - ไม่มีสัญญาเช่า
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Table View -->
                <div class="table-view" id="tableView">
                    <table class="utility-table" id="utilityDataTable">
                        <thead>
                            <tr>
                                <th>ห้อง</th>
                                <th>ผู้เช่า</th>
                                <th><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/></svg> น้ำเดิม</th>
                                <th><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/></svg> น้ำใหม่</th>
                                <th><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/></svg> ใช้ไป</th>
                                <th><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/></svg> ค่าน้ำ</th>
                                <th><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#eab308" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg> ไฟเดิม</th>
                                <th><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#eab308" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg> ไฟใหม่</th>
                                <th><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#eab308" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg> ใช้ไป</th>
                                <th><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#eab308" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg> ค่าไฟ</th>
                                <th>สถานะ</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rooms as $room): 
                                $reading = $latestReadings[$room['room_id']] ?? null;
                                $hasData = $reading !== null;
                                $ctrId = $room['ctr_id'] ?? null;
                                
                                // ดึงค่าเดิมจากเดือนก่อน
                                $waterOld = 0;
                                $electricOld = 0;
                                if ($ctrId) {
                                    $prevStmt2 = $pdo->prepare("
                                        SELECT utl_water_end, utl_elec_end 
                                        FROM utility 
                                        WHERE ctr_id = ? AND utl_date < ?
                                        ORDER BY utl_date DESC 
                                        LIMIT 1
                                    ");
                                    $prevStmt2->execute([$ctrId, "$selectedYear-$selectedMonth-01"]);
                                    $prev2 = $prevStmt2->fetch(PDO::FETCH_ASSOC);
                                    $waterOld = $prev2 ? (int)$prev2['utl_water_end'] : ($reading ? (int)$reading['utl_water_start'] : 0);
                                    $electricOld = $prev2 ? (int)$prev2['utl_elec_end'] : ($reading ? (int)$reading['utl_elec_start'] : 0);
                                }
                                
                                $waterNew = $hasData ? (int)$reading['utl_water_end'] : 0;
                                $elecNew = $hasData ? (int)$reading['utl_elec_end'] : 0;
                                $waterUsed = $waterNew - $waterOld;
                                $elecUsed = $elecNew - $electricOld;
                                $waterCost = $waterUsed * $waterRate;
                                $elecCost = $elecUsed * $electricRate;
                            ?>
                            <tr>
                                <?php $formId = 'form_' . $room['room_id']; ?>
                                <td><strong><?php echo htmlspecialchars($room['room_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($room['tnt_name'] ?: 'ว่าง'); ?></td>
                                <td><?php echo number_format($waterOld); ?></td>
                                <td>
                                    <?php if ($ctrId): ?>
                                    <form method="post" id="<?php echo $formId; ?>" action="manage_utility.php?month=<?php echo $selectedMonth; ?>&year=<?php echo $selectedYear; ?>&show=<?php echo $showMode; ?>" style="display:none;">
                                        <input type="hidden" name="save_meter" value="1">
                                        <input type="hidden" name="room_id" value="<?php echo $room['room_id']; ?>">
                                        <input type="hidden" name="ctr_id" value="<?php echo $ctrId; ?>">
                                        <input type="hidden" name="room_number" value="<?php echo htmlspecialchars($room['room_number']); ?>">
                                        <input type="hidden" name="meter_date" value="<?php echo $selectedYear . '-' . str_pad($selectedMonth, 2, '0', STR_PAD_LEFT) . '-' . date('d'); ?>">
                                    </form>
                                    <input type="number" form="<?php echo $formId; ?>" name="water_meter" value="<?php echo $hasData ? $waterNew : ''; ?>" min="0" placeholder="0" class="table-input-water" data-room="<?php echo $room['room_id']; ?>" data-old="<?php echo $waterOld; ?>" required>
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
                                <td style="color: #fbbf24;"><?php echo $hasData ? number_format($waterUsed) : '-'; ?></td>
                                <td style="color: #22c55e;"><?php echo $hasData ? '฿'.number_format($waterCost) : '-'; ?></td>
                                <td><?php echo number_format($electricOld); ?></td>
                                <td>
                                    <?php if ($ctrId): ?>
                                        <input type="number" form="<?php echo $formId; ?>" name="electric_meter" value="<?php echo $hasData ? $elecNew : ''; ?>" min="0" placeholder="0" class="table-input-elec" data-room="<?php echo $room['room_id']; ?>" data-old="<?php echo $electricOld; ?>" required>
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
                                <td style="color: #fbbf24;"><?php echo $hasData ? number_format($elecUsed) : '-'; ?></td>
                                <td style="color: #22c55e;"><?php echo $hasData ? '฿'.number_format($elecCost) : '-'; ?></td>
                                <td>
                                    <?php if (!$ctrId): ?>
                                    <span class="status-badge empty">ว่าง</span>
                                    <?php elseif ($hasData): ?>
                                    <span class="status-badge saved"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><polyline points="20 6 9 17 4 12"/></svg> บันทึกแล้ว</span>
                                    <?php else: ?>
                                    <span class="status-badge pending">รอบันทึก</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($ctrId): ?>
                                        <button type="button" class="btn-save-small" onclick="if(validateTableForm('<?php echo $formId; ?>')) document.getElementById('<?php echo $formId; ?>').submit();"><?php echo $hasData ? 'อัพเดท' : 'บันทึก'; ?></button>
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
    const waterRate = <?php echo $waterRate; ?>;
    const electricRate = <?php echo $electricRate; ?>;

    // คำนวณค่าใช้จ่ายอัตโนมัติ
    document.querySelectorAll('.meter-form').forEach(form => {
        const waterInput = form.querySelector('input[name="water_meter"]');
        const electricInput = form.querySelector('input[name="electric_meter"]');
        const roomId = form.querySelector('input[name="room_id"]').value;
        const summary = document.getElementById('summary_' + roomId);

        function calculate() {
            const waterOld = parseInt(waterInput.dataset.old) || 0;
            const electricOld = parseInt(electricInput.dataset.old) || 0;
            const waterNew = parseInt(waterInput.value) || 0;
            const electricNew = parseInt(electricInput.value) || 0;

            if (waterNew > 0 || electricNew > 0) {
                const waterUsed = Math.max(0, waterNew - waterOld);
                const electricUsed = Math.max(0, electricNew - electricOld);
                const waterCost = waterUsed * waterRate;
                const electricCost = electricUsed * electricRate;

                summary.innerHTML = `
                    น้ำใช้: <span class="used">${waterUsed.toLocaleString()}</span> หน่วย = 
                    <span class="cost">฿${waterCost.toLocaleString()}</span> | 
                    ไฟใช้: <span class="used">${electricUsed.toLocaleString()}</span> หน่วย = 
                    <span class="cost">฿${electricCost.toLocaleString()}</span>
                `;
            }
        }

        waterInput?.addEventListener('input', calculate);
        electricInput?.addEventListener('input', calculate);
    });

    function validateForm(form) {
        const water = form.querySelector('input[name="water_meter"]');
        const electric = form.querySelector('input[name="electric_meter"]');
        
        // Handle cases where data-old might be empty or undefined
        const waterOld = parseInt(water.dataset.old) || 0;
        const electricOld = parseInt(electric.dataset.old) || 0;
        const waterNew = parseInt(water.value) || 0;
        const electricNew = parseInt(electric.value) || 0;
        
        if (waterNew < waterOld) {
            alert('เลขมิเตอร์น้ำใหม่ต้องมากกว่าหรือเท่ากับเลขเดิม (' + waterOld + ')');
            water.focus();
            return false;
        }
        if (electricNew < electricOld) {
            alert('เลขมิเตอร์ไฟใหม่ต้องมากกว่าหรือเท่ากับเลขเดิม (' + electricOld + ')');
            electric.focus();
            return false;
        }
        return true;
    }

    // Validate table form using form attribute
    function validateTableForm(formId) {
        const water = document.querySelector('input[form="' + formId + '"][name="water_meter"]');
        const electric = document.querySelector('input[form="' + formId + '"][name="electric_meter"]');
        
        if (!water || !electric || !water.value || !electric.value) {
            alert('กรุณากรอกเลขมิเตอร์ให้ครบ');
            return false;
        }
        if (parseInt(water.value) < parseInt(water.dataset.old)) {
            alert('เลขมิเตอร์น้ำใหม่ต้องมากกว่าหรือเท่ากับเลขเดิม');
            water.focus();
            return false;
        }
        if (parseInt(electric.value) < parseInt(electric.dataset.old)) {
            alert('เลขมิเตอร์ไฟใหม่ต้องมากกว่าหรือเท่ากับเลขเดิม');
            electric.focus();
            return false;
        }
        return true;
    }

    // View Toggle
    function switchView(mode) {
        const gridView = document.getElementById('gridView');
        const tableView = document.getElementById('tableView');
        const gridBtn = document.getElementById('gridViewBtn');
        const tableBtn = document.getElementById('tableViewBtn');
        
        if (mode === 'table') {
            gridView?.classList.add('hidden');
            tableView?.classList.add('active');
            gridBtn?.classList.remove('active');
            tableBtn?.classList.add('active');
            localStorage.setItem('utilityViewMode', 'table');
            
            // Initialize DataTable if not already
            initDataTable();
        } else {
            gridView?.classList.remove('hidden');
            tableView?.classList.remove('active');
            gridBtn?.classList.add('active');
            tableBtn?.classList.remove('active');
            localStorage.setItem('utilityViewMode', 'grid');
        }
    }

    // DataTable initialization
    let dataTableInstance = null;
    function initDataTable() {
        if (dataTableInstance) return;
        
        const table = document.getElementById('utilityDataTable');
        if (!table || typeof simpleDatatables === 'undefined') {
            setTimeout(initDataTable, 100);
            return;
        }
        
        dataTableInstance = new simpleDatatables.DataTable(table, {
            searchable: true,
            fixedHeight: false,
            perPage: 15,
            perPageSelect: [10, 15, 25, 50],
            labels: {
                placeholder: "ค้นหา...",
                perPage: "แสดง {select} รายการ",
                noRows: "ไม่พบข้อมูล",
                info: "แสดง {start} ถึง {end} จาก {rows} รายการ"
            }
        });
    }

    // Restore view mode on load
    document.addEventListener('DOMContentLoaded', () => {
        const savedMode = localStorage.getItem('utilityViewMode');
        if (savedMode === 'table') {
            switchView('table');
        }
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4" defer></script>
    <script src="../Assets/Javascript/animate-ui.js"></script>
</body>
</html>
