<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../ConnectDB.php';

$pdo = connectDB();

// ดึงค่าตั้งค่าระบบ
$siteName = 'Sangthian Dormitory';
$logoFilename = 'Logo.jpg';
$themeColor = '#1e40af';
$bgFilename = 'bg.jpg';
$publicTheme = 'dark';
$useBgImage = '0';
$bankName = '';
$bankAccountName = '';
$bankAccountNumber = '';
$promptpayNumber = '';
$contactPhone = '';
$contactEmail = '';

try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        switch ($row['setting_key']) {
            case 'site_name': $siteName = $row['setting_value']; break;
            case 'logo_filename': $logoFilename = $row['setting_value']; break;
            case 'theme_color': if (!empty($row['setting_value'])) $themeColor = $row['setting_value']; break;
            case 'bg_filename': if (!empty($row['setting_value'])) $bgFilename = $row['setting_value']; break;
            case 'public_theme': if (!empty($row['setting_value'])) $publicTheme = $row['setting_value']; break;
            case 'use_bg_image': if ($row['setting_value'] !== null) $useBgImage = $row['setting_value']; break;
            case 'bank_name': $bankName = $row['setting_value'] ?? ''; break;
            case 'bank_account_name': $bankAccountName = $row['setting_value'] ?? ''; break;
            case 'bank_account_number': $bankAccountNumber = $row['setting_value'] ?? ''; break;
            case 'promptpay_number': $promptpayNumber = $row['setting_value'] ?? ''; break;
            case 'contact_phone': $contactPhone = $row['setting_value'] ?? ''; break;
            case 'contact_email': $contactEmail = $row['setting_value'] ?? ''; break;
        }
    }
} catch (PDOException $e) {}

// Helper: Thai date formatter
function thaiDate(?string $dateStr, string $format = 'd M Y') {
    if (empty($dateStr)) return '';
    $ts = strtotime($dateStr);
    if ($ts === false) return '';
    $day = date('j', $ts);
    $monthNum = (int)date('n', $ts);
    $yearBE = (int)date('Y', $ts) + 543;
    $thaiMonthsShort = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $thaiMonthsFull = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
    $monthShort = $thaiMonthsShort[$monthNum - 1] ?? '';
    $monthFull = $thaiMonthsFull[$monthNum - 1] ?? '';
    $out = $format;
    $out = str_replace('d', str_pad((string)$day, 2, '0', STR_PAD_LEFT), $out);
    $out = str_replace('j', (string)$day, $out);
    $out = str_replace('M', $monthShort, $out);
    $out = str_replace('F', $monthFull, $out);
    $out = str_replace('Y', (string)$yearBE, $out);
    return $out;
}

$bookingInfo = null;
$error = '';
$searchMethod = '';

// ตรวจสอบว่า tenant login หรือไม่
$isLoggedIn = !empty($_SESSION['tenant_logged_in']) && !empty($_SESSION['tenant_id']);

// ถ้า login แล้วให้ดึงข้อมูลการจองอัตโนมัติ
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $tenantId = $_SESSION['tenant_id'];
    try {
        $phoneStmt = $pdo->prepare("SELECT tnt_phone FROM tenant WHERE tnt_id = ?");
        $phoneStmt->execute([$tenantId]);
        $phoneData = $phoneStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($phoneData && !empty($phoneData['tnt_phone'])) {
            $stmt = $pdo->prepare("
                SELECT 
                    t.tnt_id, t.tnt_name, t.tnt_phone, t.tnt_education, t.tnt_faculty, t.tnt_year,
                    b.bkg_id, b.bkg_date, b.bkg_checkin_date, b.bkg_status,
                    r.room_id, r.room_number, rt.type_name, rt.type_price,
                    c.ctr_id, c.ctr_start, c.ctr_end, c.ctr_deposit, c.ctr_status, c.access_token,
                    e.exp_id, e.exp_total, e.exp_status,
                    COUNT(p.pay_id) as payment_count,
                    SUM(IF(p.pay_status = '1', p.pay_amount, 0)) as paid_amount,
                    SUM(IF(p.pay_proof IS NOT NULL AND p.pay_proof != '', 1, 0)) as has_slip
                FROM tenant t
                LEFT JOIN booking b ON t.tnt_id = b.tnt_id AND b.bkg_status IN ('1', '2')
                LEFT JOIN room r ON b.room_id = r.room_id
                LEFT JOIN roomtype rt ON r.type_id = rt.type_id
                LEFT JOIN contract c ON t.tnt_id = c.tnt_id AND c.ctr_status = '0'
                LEFT JOIN expense e ON c.ctr_id = e.ctr_id
                LEFT JOIN payment p ON e.exp_id = p.exp_id
                WHERE t.tnt_phone = ? AND (b.bkg_id IS NOT NULL OR c.ctr_id IS NOT NULL)
                GROUP BY t.tnt_id, b.bkg_id, r.room_id, rt.type_id, c.ctr_id, e.exp_id
                ORDER BY b.bkg_date DESC, c.ctr_start DESC
                LIMIT 1
            ");
            $stmt->execute([$phoneData['tnt_phone']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && ($result['bkg_id'] || $result['ctr_id'])) {
                $bookingInfo = $result;
                $bookingInfo['ctr_deposit'] = floatval($bookingInfo['ctr_deposit'] ?? 0);
                $bookingInfo['paid_amount'] = floatval($bookingInfo['paid_amount'] ?? 0);
                $bookingInfo['payment_count'] = intval($bookingInfo['payment_count'] ?? 0);
                $bookingInfo['has_slip'] = intval($bookingInfo['has_slip'] ?? 0);
                $bookingInfo['type_price'] = floatval($bookingInfo['type_price'] ?? 0);
                $searchMethod = 'auto';
            }
        }
    } catch (PDOException $e) {}
}

// Handle POST search
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingRef = trim($_POST['booking_ref'] ?? '');
    $contactInfo = trim($_POST['contact_info'] ?? '');
    
    $bookingRef = preg_replace('/[^0-9a-zA-Z]/', '', $bookingRef);
    
    if (empty($bookingRef)) {
        $error = 'กรุณากรอกหมายเลขการจอง';
    } elseif (empty($contactInfo)) {
        $error = 'กรุณากรอกเบอร์โทรศัพท์';
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    t.tnt_id, t.tnt_name, t.tnt_phone, t.tnt_education, t.tnt_faculty, t.tnt_year,
                    b.bkg_id, b.bkg_date, b.bkg_checkin_date, b.bkg_status,
                    r.room_id, r.room_number, rt.type_name, rt.type_price,
                    c.ctr_id, c.ctr_start, c.ctr_end, c.ctr_deposit, c.ctr_status, c.access_token,
                    e.exp_id, e.exp_total, e.exp_status,
                    COUNT(p.pay_id) as payment_count,
                    SUM(IF(p.pay_status = '1', p.pay_amount, 0)) as paid_amount,
                    SUM(IF(p.pay_proof IS NOT NULL AND p.pay_proof != '', 1, 0)) as has_slip
                FROM tenant t
                LEFT JOIN booking b ON t.tnt_id = b.tnt_id AND b.bkg_status IN ('1', '2')
                LEFT JOIN room r ON b.room_id = r.room_id
                LEFT JOIN roomtype rt ON r.type_id = rt.type_id
                LEFT JOIN contract c ON t.tnt_id = c.tnt_id AND c.ctr_status = '0'
                LEFT JOIN expense e ON c.ctr_id = e.ctr_id
                LEFT JOIN payment p ON e.exp_id = p.exp_id
                WHERE (b.bkg_id = ? OR t.tnt_id = ?) AND t.tnt_phone = ?
                GROUP BY t.tnt_id, b.bkg_id, r.room_id, rt.type_id, c.ctr_id, e.exp_id
            ");
            $stmt->execute([$bookingRef, $bookingRef, $contactInfo]);
            $bookingInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$bookingInfo || empty($bookingInfo['bkg_id'])) {
                $error = 'ไม่พบข้อมูลการจอง กรุณาตรวจสอบหมายเลขการจองและเบอร์โทรศัพท์';
                $bookingInfo = null; // Reset to null if not found
            } else {
                $bookingInfo['ctr_deposit'] = floatval($bookingInfo['ctr_deposit'] ?? 0);
                $bookingInfo['paid_amount'] = floatval($bookingInfo['paid_amount'] ?? 0);
                $bookingInfo['payment_count'] = intval($bookingInfo['payment_count'] ?? 0);
                $bookingInfo['has_slip'] = intval($bookingInfo['has_slip'] ?? 0);
                $bookingInfo['type_price'] = floatval($bookingInfo['type_price'] ?? 0);
                $searchMethod = 'found';
            }
        } catch (PDOException $e) {
            $error = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
        }
    }
}

// Determine status
$bookingStatuses = [
    '0' => ['label' => 'ยกเลิก', 'class' => 'cancelled', 'icon' => 'x-circle'],
    '1' => ['label' => 'รอยืนยัน', 'class' => 'pending', 'icon' => 'clock'],
    '2' => ['label' => 'เข้าพักแล้ว', 'class' => 'success', 'icon' => 'check-circle']
];

$currentBkgStatus = $bookingInfo['bkg_status'] ?? null;
$currentCtrStatus = $bookingInfo['ctr_status'] ?? null;
$currentExpStatus = $bookingInfo['exp_status'] ?? null;

// Progress calculation
$hasPaymentProof = ($bookingInfo['has_slip'] ?? 0) > 0 || $currentExpStatus === '1' || $currentExpStatus === '2';
$progressStage = 1;
if (($bookingInfo['payment_count'] ?? 0) > 0) $progressStage = 2;
if ($hasPaymentProof) $progressStage = 2.5;
if ($currentExpStatus === '2') $progressStage = 3;
if ($currentExpStatus === '1') $progressStage = 4;
if (!empty($bookingInfo['ctr_id']) && $currentExpStatus === '1') $progressStage = 5;
if ($currentBkgStatus === '2') $progressStage = 6;

// Steps for progress
$steps = [
    ['id' => 1, 'label' => 'รับคำจอง', 'icon' => 'clipboard-check'],
    ['id' => 2, 'label' => 'ชำระมัดจำ', 'icon' => 'credit-card'],
    ['id' => 3, 'label' => 'ตรวจสอบ', 'icon' => 'search'],
    ['id' => 4, 'label' => 'ออกสัญญา', 'icon' => 'file-text'],
    ['id' => 5, 'label' => 'เข้าพัก', 'icon' => 'home']
];

// Calculate amounts
$deposit = 2000;
$paid = floatval($bookingInfo['paid_amount'] ?? 0);
$remaining = max(0, $deposit - $paid);

$themeClass = $publicTheme === 'light' ? 'theme-light' : '';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>ตรวจสอบสถานะการจอง - <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: <?php echo htmlspecialchars($themeColor); ?>;
            --primary-light: <?php echo htmlspecialchars($themeColor); ?>22;
            --bg: #0f0f1a;
            --bg-card: #1a1a2e;
            --bg-card-hover: #252542;
            --text: #ffffff;
            --text-secondary: #a0a0b0;
            --border: rgba(255, 255, 255, 0.08);
            --success: #22c55e;
            --success-bg: rgba(34, 197, 94, 0.1);
            --warning: #f59e0b;
            --warning-bg: rgba(245, 158, 11, 0.1);
            --danger: #ef4444;
            --danger-bg: rgba(239, 68, 68, 0.1);
            --radius: 16px;
            --radius-sm: 12px;
            --shadow: 0 4px 24px rgba(0, 0, 0, 0.3);
        }
        
        .theme-light {
            --bg: #f8fafc;
            --bg-card: #ffffff;
            --bg-card-hover: #f1f5f9;
            --text: #0f172a;
            --text-secondary: #64748b;
            --border: rgba(0, 0, 0, 0.08);
            --shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
        }
        
        body {
            font-family: 'Prompt', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            line-height: 1.5;
        }
        
        /* Container */
        .container {
            max-width: 640px;
            margin: 0 auto;
            padding: 16px;
            padding-bottom: 100px;
        }
        
        @media (min-width: 768px) {
            .container {
                padding: 24px;
                padding-bottom: 40px;
            }
        }
        
        /* Header */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            margin-bottom: 24px;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .back-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            color: var(--text);
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .back-btn svg {
            width: 20px;
            height: 20px;
            stroke: var(--text);
        }
        
        .back-btn:hover {
            background: var(--bg-card-hover);
            transform: scale(1.05);
        }
        
        .logo {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            object-fit: cover;
        }
        
        .site-name {
            font-size: 1rem;
            font-weight: 600;
        }
        
        /* Hero Section */
        .hero {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .hero-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            background: var(--primary-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .hero-icon svg {
            width: 32px;
            height: 32px;
            stroke: var(--primary);
        }
        
        /* Global SVG styles */
        svg {
            flex-shrink: 0;
        }
        
        .hero h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .hero p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        
        /* Search Card */
        .search-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
        }
        
        .input-group {
            margin-bottom: 16px;
        }
        
        .input-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--text-secondary);
        }
        
        .input-field {
            width: 100%;
            padding: 14px 16px;
            background: var(--bg);
            border: 2px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text);
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.2s;
        }
        
        .input-field:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-light);
        }
        
        .input-field::placeholder {
            color: var(--text-secondary);
            opacity: 0.6;
        }
        
        .btn-primary {
            width: 100%;
            padding: 16px 24px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 1rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        /* Alert */
        .alert {
            padding: 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .alert-error {
            background: var(--danger-bg);
            border: 1px solid var(--danger);
            color: var(--danger);
        }
        
        .alert-icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        /* Status Hero */
        .status-hero {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 16px;
            box-shadow: var(--shadow);
        }
        
        .status-hero-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .booking-ref {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .booking-ref strong {
            color: var(--text);
            font-weight: 600;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .status-badge.pending {
            background: var(--warning-bg);
            color: var(--warning);
        }
        
        .status-badge.success {
            background: var(--success-bg);
            color: var(--success);
        }
        
        .status-badge.cancelled {
            background: var(--danger-bg);
            color: var(--danger);
        }
        
        .status-badge svg {
            width: 16px;
            height: 16px;
        }
        
        /* Progress Steps - Horizontal Timeline */
        .progress-wrapper {
            margin: 24px 0;
        }
        
        .progress-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
        }
        
        .progress-line {
            position: absolute;
            top: 18px;
            left: 20px;
            right: 20px;
            height: 3px;
            background: var(--border);
            border-radius: 2px;
            z-index: 1;
        }
        
        .progress-line-fill {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            background: var(--success);
            border-radius: 2px;
            transition: width 0.5s ease;
        }
        
        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }
        
        .step-dot {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--bg-card);
            border: 3px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
            transition: all 0.3s;
        }
        
        .step-dot svg {
            width: 16px;
            height: 16px;
            color: var(--text-secondary);
            opacity: 0.5;
        }
        
        .progress-step.completed .step-dot {
            background: var(--success);
            border-color: var(--success);
        }
        
        .progress-step.completed .step-dot svg {
            color: white;
            opacity: 1;
        }
        
        .progress-step.current .step-dot {
            border-color: var(--primary);
            background: var(--primary-light);
            animation: pulse 2s infinite;
        }
        
        .progress-step.current .step-dot svg {
            color: var(--primary);
            opacity: 1;
        }
        
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 var(--primary-light); }
            50% { box-shadow: 0 0 0 8px transparent; }
        }
        
        .step-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            text-align: center;
            max-width: 60px;
        }
        
        .progress-step.completed .step-label,
        .progress-step.current .step-label {
            color: var(--text);
            font-weight: 500;
        }
        
        @media (max-width: 480px) {
            .step-label {
                font-size: 0.65rem;
                max-width: 50px;
            }
            .step-dot {
                width: 32px;
                height: 32px;
            }
            .step-dot svg {
                width: 14px;
                height: 14px;
            }
        }
        
        /* Info Cards */
        .info-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            margin-bottom: 16px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .info-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .info-card-header h3 {
            font-size: 1rem;
            font-weight: 600;
        }
        
        .info-card-header svg {
            width: 20px;
            height: 20px;
            color: var(--primary);
        }
        
        .info-card-body {
            padding: 20px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .info-item.full {
            grid-column: 1 / -1;
        }
        
        .info-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            font-size: 1rem;
            font-weight: 500;
        }
        
        .info-value.highlight {
            color: var(--success);
            font-size: 1.25rem;
            font-weight: 700;
        }
        
        .info-value.danger {
            color: var(--danger);
        }
        
        /* Payment Card - Special Design */
        .payment-card {
            background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-hover) 100%);
            border: 2px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 16px;
            position: relative;
            overflow: hidden;
        }
        
        .payment-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 150px;
            height: 150px;
            background: var(--primary);
            opacity: 0.05;
            border-radius: 50%;
            transform: translate(30%, -30%);
        }
        
        .payment-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .payment-header h3 {
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .payment-header svg {
            width: 20px;
            height: 20px;
            color: var(--primary);
        }
        
        .payment-amount {
            text-align: center;
            padding: 24px 0;
            border-top: 1px dashed var(--border);
            border-bottom: 1px dashed var(--border);
            margin: 16px 0;
        }
        
        .payment-amount-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }
        
        .payment-amount-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--danger);
            line-height: 1;
        }
        
        .payment-amount-value.paid {
            color: var(--success);
        }
        
        .payment-details {
            display: flex;
            justify-content: space-between;
            margin-top: 16px;
            font-size: 0.875rem;
        }
        
        .payment-detail {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .payment-detail span:first-child {
            color: var(--text-secondary);
        }
        
        .payment-detail span:last-child {
            font-weight: 600;
        }
        
        /* Bank Transfer Info */
        .bank-info {
            margin-top: 20px;
            padding: 16px;
            background: var(--bg);
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
        }
        
        .bank-info-title {
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .bank-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .bank-row:last-child {
            border-bottom: none;
        }
        
        .bank-row-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .bank-row-value {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .copy-btn {
            width: 32px;
            height: 32px;
            border: none;
            background: var(--bg-card);
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .copy-btn:hover {
            background: var(--bg-card-hover);
        }
        
        .copy-btn svg {
            width: 16px;
            height: 16px;
            color: var(--text-secondary);
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 16px;
        }
        
        .quick-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 20px 16px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            text-decoration: none;
            color: var(--text);
            transition: all 0.2s;
        }
        
        .quick-action:hover {
            background: var(--bg-card-hover);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .quick-action svg {
            width: 24px;
            height: 24px;
            color: var(--primary);
        }
        
        .quick-action span {
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        /* Help Section */
        .help-section {
            margin-top: 24px;
            padding: 20px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
        }
        
        .help-title {
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .help-title svg {
            width: 18px;
            height: 18px;
            color: var(--warning);
        }
        
        .help-list {
            list-style: none;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .help-list li {
            padding: 8px 0;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .help-list li::before {
            content: '•';
            color: var(--primary);
            font-weight: bold;
        }
        
        /* Contract Link */
        .contract-banner {
            background: linear-gradient(135deg, var(--success-bg) 0%, transparent 100%);
            border: 2px solid var(--success);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .contract-banner-icon {
            width: 48px;
            height: 48px;
            background: var(--success);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .contract-banner-icon svg {
            width: 24px;
            height: 24px;
            color: white;
        }
        
        .contract-banner-content {
            flex: 1;
        }
        
        .contract-banner-content h4 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .contract-banner-content p {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .contract-banner a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--success);
            font-weight: 600;
            text-decoration: none;
        }
        
        .contract-banner a:hover {
            text-decoration: underline;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.4s ease;
        }
        
        .fade-in-delay-1 { animation-delay: 0.1s; animation-fill-mode: both; }
        .fade-in-delay-2 { animation-delay: 0.2s; animation-fill-mode: both; }
        .fade-in-delay-3 { animation-delay: 0.3s; animation-fill-mode: both; }
        
        /* Tooltip */
        .tooltip {
            position: fixed;
            background: var(--text);
            color: var(--bg);
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.875rem;
            z-index: 1000;
            pointer-events: none;
            animation: fadeIn 0.2s ease;
        }
    </style>
</head>
<body class="<?php echo $themeClass; ?>">
    <div class="container">
        <!-- Header -->
        <div class="header fade-in">
            <div class="header-left">
                <a href="../index.php" class="back-btn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                </a>
                <img src="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="" class="logo">
                <span class="site-name"><?php echo htmlspecialchars($siteName); ?></span>
            </div>
        </div>
        
        <?php 
        // ตรวจสอบว่ามีข้อมูลจริงๆ หรือไม่
        $hasBookingData = !empty($bookingInfo) && !empty($bookingInfo['bkg_id']) && ($searchMethod === 'found' || $searchMethod === 'auto');
        ?>
        
        <?php if (!$hasBookingData && empty($error)): ?>
        <!-- Search Form -->
        <div class="hero fade-in">
            <div class="hero-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="m21 21-4.35-4.35"/>
                </svg>
            </div>
            <h1>ตรวจสอบสถานะการจอง</h1>
            <p>กรอกข้อมูลเพื่อดูสถานะการจองของคุณ</p>
        </div>
        
        <div class="search-card fade-in fade-in-delay-1">
            <form method="post">
                <div class="input-group">
                    <label>หมายเลขการจอง</label>
                    <input type="text" name="booking_ref" class="input-field" placeholder="เช่น 767830691" required>
                </div>
                <div class="input-group">
                    <label>เบอร์โทรศัพท์</label>
                    <input type="tel" name="contact_info" class="input-field" placeholder="เช่น 0812345678" required maxlength="10" inputmode="tel">
                </div>
                <button type="submit" class="btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.35-4.35"/>
                    </svg>
                    ค้นหาการจอง
                </button>
            </form>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-error fade-in">
            <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
        
        <!-- Show search form again after error -->
        <div class="search-card fade-in">
            <form method="post">
                <div class="input-group">
                    <label>หมายเลขการจอง</label>
                    <input type="text" name="booking_ref" class="input-field" placeholder="เช่น 767830691" required>
                </div>
                <div class="input-group">
                    <label>เบอร์โทรศัพท์</label>
                    <input type="tel" name="contact_info" class="input-field" placeholder="เช่น 0812345678" required maxlength="10" inputmode="tel">
                </div>
                <button type="submit" class="btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.35-4.35"/>
                    </svg>
                    ค้นหาอีกครั้ง
                </button>
            </form>
        </div>
        <?php endif; ?>
        
        <?php if ($hasBookingData): ?>
        
        <!-- Status Hero -->
        <div class="status-hero fade-in">
            <div class="status-hero-header">
                <div class="booking-ref">
                    หมายเลขจอง: <strong>#<?php echo htmlspecialchars($bookingInfo['bkg_id'] ?? '-'); ?></strong>
                </div>
                <?php
                // กำหนดค่า badge
                if ($currentBkgStatus === '0') {
                    $badgeClass = 'cancelled';
                } elseif ($currentBkgStatus === '2') {
                    $badgeClass = 'success';
                } else {
                    $badgeClass = 'pending';
                }
                $badgeLabel = $bookingStatuses[$currentBkgStatus]['label'] ?? 'ไม่ทราบ';
                if ($currentBkgStatus === '1' && $currentExpStatus === '1') {
                    $badgeLabel = 'จองสำเร็จ';
                    $badgeClass = 'success';
                }
                ?>
                <span class="status-badge <?php echo $badgeClass; ?>">
                    <?php if ($badgeClass === 'success'): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    <?php elseif ($badgeClass === 'pending'): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                    <?php else: ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="15" y1="9" x2="9" y2="15"/>
                        <line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                    <?php endif; ?>
                    <?php echo $badgeLabel; ?>
                </span>
            </div>
            
            <!-- Progress Steps -->
            <div class="progress-wrapper">
                <div class="progress-steps">
                    <div class="progress-line">
                        <?php 
                        // คำนวณ progress percent
                        if ($progressStage >= 6) {
                            $progressPercent = 100;
                        } elseif ($progressStage >= 5) {
                            $progressPercent = 80;
                        } elseif ($progressStage >= 4) {
                            $progressPercent = 60;
                        } elseif ($progressStage >= 3) {
                            $progressPercent = 45;
                        } elseif ($progressStage >= 2) {
                            $progressPercent = 25;
                        } else {
                            $progressPercent = 0;
                        }
                        ?>
                        <div class="progress-line-fill" style="width: <?php echo $progressPercent; ?>%"></div>
                    </div>
                    <?php foreach ($steps as $idx => $step): 
                        $stepClass = '';
                        if ($progressStage > $step['id']) {
                            $stepClass = 'completed';
                        } elseif (floor($progressStage) == $step['id']) {
                            $stepClass = 'current';
                        }
                    ?>
                    <div class="progress-step <?php echo $stepClass; ?>">
                        <div class="step-dot">
                            <?php if ($stepClass === 'completed'): ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <?php else: ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <?php endif; ?>
                        </div>
                        <span class="step-label"><?php echo $step['label']; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Contract Banner (if available) -->
        <?php if (!empty($bookingInfo['ctr_id']) && !empty($bookingInfo['access_token']) && ($currentExpStatus === '1' || $currentBkgStatus === '2')): ?>
        <div class="contract-banner fade-in fade-in-delay-1">
            <div class="contract-banner-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                </svg>
            </div>
            <div class="contract-banner-content">
                <h4>สัญญาเช่าพร้อมแล้ว</h4>
                <p>คุณสามารถดูหรือดาวน์โหลดสัญญาได้</p>
                <a href="../Tenant/contract.php?token=<?php echo urlencode($bookingInfo['access_token']); ?>" target="_blank">
                    ดูสัญญา
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                        <polyline points="15 3 21 3 21 9"/>
                        <line x1="10" y1="14" x2="21" y2="3"/>
                    </svg>
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Payment Card -->
        <div class="payment-card fade-in fade-in-delay-1">
            <div class="payment-header">
                <h3>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="1" y="4" width="22" height="16" rx="2"/>
                        <line x1="1" y1="10" x2="23" y2="10"/>
                    </svg>
                    ค่ามัดจำ
                </h3>
                <?php if ($remaining <= 0): ?>
                <span class="status-badge success">ชำระแล้ว</span>
                <?php elseif ($currentExpStatus === '2'): ?>
                <span class="status-badge pending">รอตรวจสอบ</span>
                <?php else: ?>
                <span class="status-badge cancelled">รอชำระ</span>
                <?php endif; ?>
            </div>
            
            <div class="payment-amount">
                <div class="payment-amount-label">ยอดที่ต้องชำระ</div>
                <div class="payment-amount-value <?php echo $remaining <= 0 ? 'paid' : ''; ?>">
                    ฿<?php echo number_format($remaining); ?>
                </div>
            </div>
            
            <div class="payment-details">
                <div class="payment-detail">
                    <span>ค่ามัดจำทั้งหมด</span>
                    <span>฿<?php echo number_format($deposit); ?></span>
                </div>
                <div class="payment-detail">
                    <span>ชำระแล้ว</span>
                    <span style="color: var(--success);">฿<?php echo number_format($paid); ?></span>
                </div>
            </div>
            
            <?php if ($remaining > 0 && (!empty($bankName) || !empty($promptpayNumber))): ?>
            <div class="bank-info">
                <div class="bank-info-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                        <path d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11M20 10v11M8 14v3M12 14v3M16 14v3"/>
                    </svg>
                    ช่องทางการชำระเงิน
                </div>
                
                <?php if (!empty($bankName)): ?>
                <div class="bank-row">
                    <span class="bank-row-label">ธนาคาร</span>
                    <span class="bank-row-value"><?php echo htmlspecialchars($bankName); ?></span>
                </div>
                <?php if (!empty($bankAccountName)): ?>
                <div class="bank-row">
                    <span class="bank-row-label">ชื่อบัญชี</span>
                    <span class="bank-row-value"><?php echo htmlspecialchars($bankAccountName); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($bankAccountNumber)): ?>
                <div class="bank-row">
                    <span class="bank-row-label">เลขบัญชี</span>
                    <span class="bank-row-value">
                        <?php echo htmlspecialchars($bankAccountNumber); ?>
                        <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($bankAccountNumber); ?>')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="9" y="9" width="13" height="13" rx="2"/>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                            </svg>
                        </button>
                    </span>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php if (!empty($promptpayNumber)): ?>
                <div class="bank-row">
                    <span class="bank-row-label">พร้อมเพย์</span>
                    <span class="bank-row-value" style="color: var(--success);">
                        <?php echo htmlspecialchars($promptpayNumber); ?>
                        <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($promptpayNumber); ?>')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="9" y="9" width="13" height="13" rx="2"/>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                            </svg>
                        </button>
                    </span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Room & Booking Info -->
        <div class="info-card fade-in fade-in-delay-2">
            <div class="info-card-header">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                <h3>ข้อมูลห้องพัก</h3>
            </div>
            <div class="info-card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">ห้อง</span>
                        <span class="info-value"><?php echo htmlspecialchars($bookingInfo['room_number'] ?? '-'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">ประเภท</span>
                        <span class="info-value"><?php echo htmlspecialchars($bookingInfo['type_name'] ?? '-'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">ค่าห้อง/เดือน</span>
                        <span class="info-value highlight">฿<?php echo number_format($bookingInfo['type_price'] ?? 0); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">วันเข้าพัก</span>
                        <span class="info-value"><?php echo !empty($bookingInfo['bkg_checkin_date']) ? thaiDate($bookingInfo['bkg_checkin_date'], 'j F Y') : '-'; ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Personal Info -->
        <div class="info-card fade-in fade-in-delay-2">
            <div class="info-card-header">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                <h3>ข้อมูลผู้จอง</h3>
            </div>
            <div class="info-card-body">
                <div class="info-grid">
                    <div class="info-item full">
                        <span class="info-label">ชื่อ-นามสกุล</span>
                        <span class="info-value"><?php echo htmlspecialchars($bookingInfo['tnt_name'] ?? '-'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">เบอร์โทร</span>
                        <span class="info-value"><?php echo htmlspecialchars($bookingInfo['tnt_phone'] ?? '-'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">วันที่จอง</span>
                        <span class="info-value"><?php echo !empty($bookingInfo['bkg_date']) ? thaiDate($bookingInfo['bkg_date'], 'j F Y') : '-'; ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions fade-in fade-in-delay-3">
            <a href="tel:<?php echo htmlspecialchars($contactPhone); ?>" class="quick-action">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                </svg>
                <span>โทรหาเรา</span>
            </a>
            <a href="https://maps.google.com/?q=<?php echo urlencode($siteName); ?>" target="_blank" class="quick-action">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                    <circle cx="12" cy="10" r="3"/>
                </svg>
                <span>นำทาง</span>
            </a>
        </div>
        
        <!-- Help Section -->
        <div class="help-section fade-in fade-in-delay-3">
            <div class="help-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
                สิ่งที่ต้องเตรียม
            </div>
            <ul class="help-list">
                <li>บัตรประชาชนตัวจริง</li>
                <li>หลักฐานการชำระค่ามัดจำ (สลิป)</li>
                <li>ค่าห้องเดือนแรก ฿<?php echo number_format($bookingInfo['type_price'] ?? 0); ?></li>
                <li>เอกสารเพิ่มเติมตามที่เจ้าหน้าที่แจ้ง</li>
            </ul>
        </div>
        
        <?php endif; ?>
    </div>
    
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                showTooltip('คัดลอกแล้ว!');
            }).catch(() => {
                // Fallback for older browsers
                const input = document.createElement('input');
                input.value = text;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);
                showTooltip('คัดลอกแล้ว!');
            });
        }
        
        function showTooltip(message) {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = message;
            tooltip.style.left = '50%';
            tooltip.style.bottom = '100px';
            tooltip.style.transform = 'translateX(-50%)';
            document.body.appendChild(tooltip);
            
            setTimeout(() => {
                tooltip.remove();
            }, 2000);
        }
        
        // Phone number formatting
        document.querySelectorAll('input[type="tel"]').forEach(input => {
            input.addEventListener('input', function(e) {
                this.value = this.value.replace(/\D/g, '').slice(0, 10);
            });
        });
    </script>
    
    <?php include_once __DIR__ . '/../includes/apple_alert.php'; ?>
</body>
</html>
