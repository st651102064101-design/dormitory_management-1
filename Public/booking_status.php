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
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename', 'theme_color', 'bg_filename', 'public_theme', 'use_bg_image')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
        if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
        if ($row['setting_key'] === 'theme_color' && !empty($row['setting_value'])) $themeColor = $row['setting_value'];
        if ($row['setting_key'] === 'bg_filename' && !empty($row['setting_value'])) $bgFilename = $row['setting_value'];
        if ($row['setting_key'] === 'public_theme' && !empty($row['setting_value'])) $publicTheme = $row['setting_value'];
        if ($row['setting_key'] === 'use_bg_image' && $row['setting_value'] !== null) $useBgImage = $row['setting_value'];
    }
} catch (PDOException $e) {}

// Derive lighter/darker tones for backgrounds
function adjustColor($hex, $percent) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    $num = hexdec($hex);
    $r = max(0, min(255, (($num >> 16) & 0xFF) + (255 * $percent / 100)));
    $g = max(0, min(255, (($num >> 8) & 0xFF) + (255 * $percent / 100)));
    $b = max(0, min(255, ($num & 0xFF) + (255 * $percent / 100)));
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

$themeDark = adjustColor($themeColor, -35);
$themeLight = adjustColor($themeColor, 25);

// Thai date formatter (วัน เดือน ปี พ.ศ.)
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
    // Support simple tokens: d, M, F, Y
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idCard = trim($_POST['id_card'] ?? '');
    $idCard = preg_replace('/[^0-9]/', '', $idCard);
    $idCard = substr($idCard, -13);
    
    if (empty($idCard) || strlen($idCard) !== 13) {
        $error = 'กรุณากรอกเลขบัตรประชาชน 13 หลัก';
    } else {
        try {
            // ดึงข้อมูลการจอง สัญญา และการชำระเงิน
            $stmt = $pdo->prepare("
                SELECT 
                    t.tnt_id, t.tnt_name, t.tnt_phone, t.tnt_education, t.tnt_faculty, t.tnt_year,
                    b.bkg_id, b.bkg_date, b.bkg_checkin_date, b.bkg_status,
                    r.room_id, r.room_number, rt.type_name, rt.type_price,
                    c.ctr_id, c.ctr_start, c.ctr_end, c.ctr_deposit, c.ctr_status, c.access_token,
                    e.exp_id, e.exp_total, e.exp_status,
                    COUNT(p.pay_id) as payment_count,
                    SUM(IF(p.pay_status = '1', p.pay_amount, 0)) as paid_amount
                FROM tenant t
                LEFT JOIN booking b ON t.tnt_id = b.tnt_id AND b.bkg_status IN ('1', '2')
                LEFT JOIN room r ON b.room_id = r.room_id
                LEFT JOIN roomtype rt ON r.type_id = rt.type_id
                LEFT JOIN contract c ON t.tnt_id = c.tnt_id AND c.ctr_status = '0'
                LEFT JOIN expense e ON c.ctr_id = e.ctr_id
                LEFT JOIN payment p ON e.exp_id = p.exp_id
                WHERE t.tnt_id = ?
                GROUP BY t.tnt_id, t.tnt_name, t.tnt_phone, t.tnt_education, t.tnt_faculty, t.tnt_year, b.bkg_id, b.bkg_date, b.bkg_checkin_date, b.bkg_status, r.room_id, r.room_number, rt.type_name, rt.type_price, c.ctr_id, c.ctr_start, c.ctr_end, c.ctr_deposit, c.ctr_status, c.access_token, e.exp_id, e.exp_total, e.exp_status
            ");
            $stmt->execute([$idCard]);
            $bookingInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$bookingInfo || !$bookingInfo['bkg_id']) {
                $error = 'ไม่พบข้อมูลการจองของคุณ';
            } else {
                // แปลงค่า NULL เป็น 0 สำหรับการคำนวณ
                $bookingInfo['ctr_deposit'] = floatval($bookingInfo['ctr_deposit'] ?? 0);
                $bookingInfo['paid_amount'] = floatval($bookingInfo['paid_amount'] ?? 0);
                $bookingInfo['type_price'] = floatval($bookingInfo['type_price'] ?? 0);
                $searchMethod = 'found';
                
                // Debug: แสดงข้อมูลที่ดึงได้ (ลบบรรทัดนี้ในโปรดักชัน)
                // error_log("Booking Info: " . print_r($bookingInfo, true));
            }
        } catch (PDOException $e) {
            error_log("Booking status error: " . $e->getMessage());
            $error = 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง';
        }
    }
}

// Determine status labels
$bookingStatuses = [
    '0' => ['label' => 'ยกเลิก', 'class' => 'cancelled', 'icon' => 'x-circle'],
    '1' => ['label' => 'จองแล้ว (รอยืนยัน)', 'class' => 'pending', 'icon' => 'clock'],
    '2' => ['label' => 'เข้าพักแล้ว', 'class' => 'active', 'icon' => 'check-circle']
];

$contractStatuses = [
    '0' => ['label' => 'ใช้งาน', 'class' => 'active'],
    '1' => ['label' => 'ยกเลิก', 'class' => 'cancelled'],
    '2' => ['label' => 'แจ้งยกเลิก', 'class' => 'pending']
];

$paymentStatuses = [
    '0' => ['label' => 'รอตรวจสอบ', 'class' => 'pending', 'color' => '#fbbf24'],
    '1' => ['label' => 'ตรวจสอบแล้ว', 'class' => 'verified', 'color' => '#34d399']
];

$currentBkgStatus = $bookingInfo['bkg_status'] ?? null;
$currentCtrStatus = $bookingInfo['ctr_status'] ?? null;
$currentExpStatus = $bookingInfo['exp_status'] ?? null;

// Derive booking badge label/class: if payment verified, show "จองสำเร็จ"
$bookingBadge = $bookingStatuses[$currentBkgStatus] ?? ['label' => 'ไม่ทราบ', 'class' => 'pending'];
if ($currentBkgStatus === '1' && $currentExpStatus === '1') {
    $bookingBadge['label'] = 'จองสำเร็จ';
    $bookingBadge['class'] = 'verified';
}

// Build tracking steps (shipment-like) with dynamic state
$hasPaymentProof = (($bookingInfo['paid_amount'] ?? 0) > 0) || $currentExpStatus === '1';
$progressStage = 1; // baseline: booking found
if ($hasPaymentProof) $progressStage = 2;
if ($currentExpStatus === '1') $progressStage = 3;
if (!empty($bookingInfo['ctr_id']) && $currentExpStatus === '1') $progressStage = 4;
if ($currentBkgStatus === '2') $progressStage = 5;

$trackingSteps = [
    [
        'label' => 'รับคำจอง',
        'desc' => 'ระบบบันทึกคำจองแล้ว ' . (!empty($bookingInfo['bkg_date']) ? '(' . htmlspecialchars(thaiDate($bookingInfo['bkg_date'], 'd M Y')) . ')' : ''),
    ],
    [
        'label' => 'ส่งหลักฐานมัดจำ',
        'desc' => $hasPaymentProof ? 'รับสลิปแล้ว กำลังประมวลผล' : 'รออัปโหลดสลิปหรือใบเสร็จ',
    ],
    [
        'label' => 'ตรวจสอบ/อนุมัติ',
        'desc' => $currentExpStatus === '1' ? 'ตรวจสอบแล้ว เตรียมออกสัญญา' : 'เจ้าหน้าที่จะตรวจสอบภายในเวลาไม่นาน',
    ],
    [
        'label' => 'สัญญาพร้อมลงชื่อ',
        'desc' => !empty($bookingInfo['ctr_id']) && !empty($bookingInfo['access_token']) ? 'เปิดดู/ดาวน์โหลดและเตรียมลงชื่อได้เลย' : 'สัญญาจะพร้อมหลังอนุมัติการชำระ',
    ],
    [
        'label' => 'เข้าพัก',
        'desc' => $currentBkgStatus === '2' ? 'เช็คอินแล้ว' : ('เตรียมเข้าพัก' . (!empty($bookingInfo['bkg_checkin_date']) ? ' (' . htmlspecialchars(thaiDate($bookingInfo['bkg_checkin_date'], 'd M Y')) . ')' : '')), 
    ],
];

foreach ($trackingSteps as $idx => &$step) {
    $stepIndex = $idx + 1;
    if ($progressStage > $stepIndex) {
        $step['state'] = 'done';
    } elseif ($progressStage === $stepIndex) {
        $step['state'] = 'current';
    } else {
        $step['state'] = 'next';
    }
}
unset($step);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตรวจสอบสถานะการจอง - <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="icon" type="image/jpeg" href="../Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: <?php echo htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8'); ?>;
            --primary-glow: <?php echo htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8'); ?>40;
            --theme-base: <?php echo htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8'); ?>;
            --theme-dark: <?php echo htmlspecialchars($themeDark, ENT_QUOTES, 'UTF-8'); ?>;
            --theme-light: <?php echo htmlspecialchars($themeLight, ENT_QUOTES, 'UTF-8'); ?>;
            --bg-dark: #0a0a0f;
            --bg-card: rgba(15, 23, 42, 0.8);
            --text-primary: #ffffff;
            --text-secondary: #94a3b8;
            --border-color: rgba(255, 255, 255, 0.1);
            --glass-bg: rgba(255, 255, 255, 0.03);
            <?php if (!empty($useBgImage) && $useBgImage === '1' && !empty($bgFilename)): ?>
            --bg-image: url('../Assets/Images/<?php echo htmlspecialchars($bgFilename, ENT_QUOTES, 'UTF-8'); ?>');
            <?php else: ?>
            --bg-image: none;
            <?php endif; ?>
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Prompt', system-ui, sans-serif;
            background: var(--bg-dark);
            <?php if (!empty($useBgImage) && $useBgImage === '1' && !empty($bgFilename)): ?>
            background-image: var(--bg-image);
            background-attachment: fixed;
            background-size: cover;
            background-position: center;
            <?php endif; ?>
            min-height: 100vh;
            color: var(--text-primary);
            overflow-x: hidden;
        }

        /* Animated Background (match index) */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
            overflow: hidden;
        }

        .bg-gradient {
            position: absolute;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(ellipse at 20% 20%, rgba(59, 130, 246, 0.15) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 80%, rgba(139, 92, 246, 0.1) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 50%, rgba(6, 182, 212, 0.08) 0%, transparent 60%);
            animation: bgPulse 20s ease-in-out infinite;
        }

        @keyframes bgPulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.1); }
        }

        .floating-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            animation: float 20s ease-in-out infinite;
        }

        .orb-1 {
            width: 500px;
            height: 500px;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.3), rgba(139, 92, 246, 0.2));
            top: -200px;
            right: -100px;
            animation-delay: 0s;
        }

        .orb-2 {
            width: 400px;
            height: 400px;
            background: linear-gradient(135deg, rgba(6, 182, 212, 0.25), rgba(59, 130, 246, 0.2));
            bottom: -150px;
            left: -100px;
            animation-delay: 5s;
        }

        .orb-3 {
            width: 300px;
            height: 300px;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(236, 72, 153, 0.15));
            top: 40%;
            left: 60%;
            animation-delay: 10s;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            25% { transform: translate(50px, -50px) rotate(5deg); }
            50% { transform: translate(0, -100px) rotate(0deg); }
            75% { transform: translate(-50px, -50px) rotate(-5deg); }
        }

        /* Particles & grid lines */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(96, 165, 250, 0.6);
            border-radius: 50%;
            animation: rise 15s infinite;
            box-shadow: 0 0 10px rgba(96, 165, 250, 0.8);
        }

        .particle:nth-child(1) { left: 10%; animation-delay: 0s; }
        .particle:nth-child(2) { left: 20%; animation-delay: 2s; }
        .particle:nth-child(3) { left: 30%; animation-delay: 4s; }
        .particle:nth-child(4) { left: 40%; animation-delay: 1s; }
        .particle:nth-child(5) { left: 50%; animation-delay: 3s; }
        .particle:nth-child(6) { left: 60%; animation-delay: 5s; }
        .particle:nth-child(7) { left: 70%; animation-delay: 2.5s; }
        .particle:nth-child(8) { left: 80%; animation-delay: 1.5s; }
        .particle:nth-child(9) { left: 90%; animation-delay: 4.5s; }

        @keyframes rise {
            0% { bottom: -10px; opacity: 0; transform: scale(0); }
            10% { opacity: 1; transform: scale(1); }
            90% { opacity: 1; }
            100% { bottom: 100vh; opacity: 0; transform: scale(0.5); }
        }

        .grid-lines {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(59, 130, 246, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(59, 130, 246, 0.03) 1px, transparent 1px);
            background-size: 60px 60px;
            z-index: 1;
            pointer-events: none;
        }

        /* Light theme overrides (match index) */
        body.theme-light {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            color: #0f172a;
        }
        body.theme-light .bg-animation {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
        }
        body.theme-light .bg-gradient {
            background: radial-gradient(ellipse at 30% 20%, rgba(59, 130, 246, 0.08), transparent 50%),
                        radial-gradient(ellipse at 70% 60%, rgba(139, 92, 246, 0.06), transparent 50%),
                        radial-gradient(ellipse at 40% 80%, rgba(34, 197, 94, 0.04), transparent 40%);
        }
        body.theme-light .grid-lines {
            background-image: linear-gradient(rgba(148, 163, 184, 0.1) 1px, transparent 1px),
                             linear-gradient(90deg, rgba(148, 163, 184, 0.1) 1px, transparent 1px);
        }
        body.theme-light .floating-orb {
            opacity: 0.15;
        }
        body.theme-light .particle {
            background: rgba(59, 130, 246, 0.3);
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.2);
        }
        body.theme-light .card, body.theme-light .status-card, body.theme-light .input-field {
            background: rgba(255,255,255,0.9);
            color: #0f172a;
            border-color: rgba(148,163,184,0.3);
        }
        body.theme-light .search-card {
            background: rgba(255,255,255,0.92);
            border-color: rgba(148,163,184,0.35);
        }
        body.theme-light .search-form input {
            border: 2px solid #cbd5e1;
            background: #f8fafc;
            color: #0f172a;
            box-shadow: inset 0 1px 4px rgba(15,23,42,0.05);
            text-shadow: none;
        }
        body.theme-light .search-form input:focus {
            border-color: #a5b4fc;
            box-shadow: 0 0 0 4px rgba(165, 180, 252, 0.25), inset 0 1px 4px rgba(15,23,42,0.08);
            background: #fff;
            color: #0f172a;
        }
        body.theme-light .search-form input::placeholder { color: #94a3b8; opacity: 0.9; }
        body.theme-light .search-form button { box-shadow: 0 6px 18px rgba(102, 126, 234, 0.35); }
        body.theme-light .search-form button,
        body.theme-light .search-form button span,
        body.theme-light .search-form button svg {
            color: #ffffff !important;
        }
        body.theme-light .status-card,
        body.theme-light .info-section,
        body.theme-light .progress-section {
            background: rgba(255,255,255,0.92);
            border-color: rgba(148,163,184,0.35);
            color: #0f172a;
        }
        body.theme-light .status-card-title,
        body.theme-light .info-label,
        body.theme-light .progress-title {
            color: #475569;
        }
        body.theme-light .info-value { color: #0f172a; }
        body.theme-light .info-value.highlight { color: #16a34a; }
        body.theme-light .status-card:hover { background: rgba(255,255,255,0.96); }
        body.theme-light .progress-steps::before { background: rgba(102, 126, 234, 0.2); }
        body.theme-light .progress-step small { color: #475569; }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        /* Header */
        .header {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            color: #fff;
        }
        
        .logo img {
            width: 50px;
            height: 50px;
            border-radius: 10px;
        }
        
        .logo h1 {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .header-actions {
            margin-left: auto;
        }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 16px;
            color: #fff;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .btn-back::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-back:hover::before {
            left: 100%;
        }
        
        .btn-back svg {
            width: 22px;
            height: 22px;
            transition: transform 0.3s;
        }
        
        .btn-back span {
            position: relative;
            z-index: 1;
        }
        
        .btn-back:hover {
            background: linear-gradient(135deg, rgba(255,255,255,0.2), rgba(255,255,255,0.1));
            border-color: rgba(255,255,255,0.3);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-back:hover svg {
            transform: translateX(-4px);
        }
        
        .btn-back:active {
            transform: translateY(0);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        /* Back button - light theme */
        body.theme-light .btn-back {
            background: rgba(255,255,255,0.95);
            border-color: rgba(148,163,184,0.35);
            color: #0f172a;
            box-shadow: 0 10px 25px rgba(148, 163, 184, 0.25);
        }
        body.theme-light .btn-back svg {
            color: #0f172a;
        }
        body.theme-light .btn-back:hover {
            background: rgba(255,255,255,0.98);
            border-color: rgba(148,163,184,0.5);
            box-shadow: 0 12px 28px rgba(148, 163, 184, 0.32);
        }
        
        /* Page Title */
        .page-title {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .page-title h2 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .page-title p {
            color: #94a3b8;
        }

        body.theme-light h1,
        body.theme-light h2,
        body.theme-light h3,
        body.theme-light p,
        body.theme-light span,
        body.theme-light label {
            color: #0f172a;
        }
        
        /* Search Form */
        .search-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .search-form {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .search-form input {
            flex: 1;
            min-width: 250px;
            padding: 1.2rem 1.5rem;
            border-radius: 12px;
            border: 2px solid #667eea;
            background: linear-gradient(135deg, rgba(30, 41, 59, 1), rgba(15, 23, 42, 1));
            color: #fff;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 5px;
            text-align: center;
            font-family: 'Courier New', Courier, monospace;
            transition: all 0.3s ease;
            caret-color: #fbbf24;
            text-shadow: 0 2px 10px rgba(102, 126, 234, 0.5);
            box-shadow: inset 0 2px 10px rgba(0, 0, 0, 0.5);
        }
        
        .search-form input:focus {
            outline: none;
            border-color: #fbbf24;
            background: linear-gradient(135deg, rgba(30, 41, 59, 1), rgba(15, 23, 42, 1));
            box-shadow: 0 0 0 4px rgba(251, 191, 36, 0.3), inset 0 2px 10px rgba(0, 0, 0, 0.5);
            color: #fff;
        }
        
        .search-form input::placeholder {
            color: #64748b;
            font-family: 'Prompt', sans-serif;
            letter-spacing: 1px;
            font-weight: 400;
            font-size: 16px;
            opacity: 0.6;
        }
        
        .search-form input::-webkit-input-placeholder {
            color: #64748b;
        }
        
        .search-form input::-moz-placeholder {
            color: #64748b;
            opacity: 0.6;
        }
        
        .search-form input:-ms-input-placeholder {
            color: #64748b;
        }
        
        .search-form button {
            padding: 1rem 2.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-weight: 600;
            font-size: 1.05rem;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
        }
        
        .search-form button::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .search-form button:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .search-form button span {
            position: relative;
            z-index: 1;
        }
        
        .search-form button:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.5);
            background: linear-gradient(135deg, #7c8ff5 0%, #8a5bb8 100%);
        }
        
        .search-form button:active {
            transform: translateY(-1px) scale(0.98);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        /* Error Alert */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 2rem;
        }
        
        .alert-error {
            background: linear-gradient(135deg, rgba(248, 113, 113, 0.1), rgba(239, 68, 68, 0.05));
            border: 1px solid rgba(248, 113, 113, 0.3);
            color: #fca5a5;
        }
        
        .alert svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }
        
        /* Status Cards */
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .status-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 1.5rem;
            transition: all 0.3s;
        }
        
        .status-card:hover {
            border-color: rgba(102, 126, 234, 0.5);
            background: rgba(255, 255, 255, 0.05);
        }
        
        .status-card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1rem;
        }
        
        .status-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        .status-card-title {
            font-size: 0.95rem;
            color: #94a3b8;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-badge.pending {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
            border: 1px solid rgba(251, 191, 36, 0.3);
        }
        
        .status-badge.verified,
        .status-badge.active {
            background: rgba(52, 211, 153, 0.2);
            color: #34d399;
            border: 1px solid rgba(52, 211, 153, 0.3);
        }
        
        .status-badge.cancelled {
            background: rgba(248, 113, 113, 0.2);
            color: #f87171;
            border: 1px solid rgba(248, 113, 113, 0.3);
        }
        
        /* Info Section */
        .info-section {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .info-section h3 {
            color: #fff;
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .info-item {
            padding: 1rem;
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .info-label {
            color: #64748b;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }
        
        .info-value {
            color: #fff;
            font-size: 1rem;
            font-weight: 500;
        }
        
        .info-value.highlight {
            color: #34d399;
            font-size: 1.2rem;
        }

        /* Upcoming Box Styles */
        .upcoming-box .upcoming-content {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            color: var(--text-primary);
        }
        .upcoming-box .amount {
            font-size: 1.25rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--accent, #fbbf24), #fde047);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: 0.25px;
            animation: amountPulse 2.4s ease-in-out infinite;
        }
        .upcoming-box .upcoming-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: linear-gradient(135deg, rgba(255,255,255,0.08), rgba(255,255,255,0.03));
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 999px;
            box-shadow: 0 6px 18px rgba(2, 8, 23, 0.35);
            color: var(--text-primary);
            position: relative;
            overflow: hidden;
        }
        .upcoming-box .upcoming-pill::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 10% 10%, rgba(255,255,255,0.12), transparent 40%),
                        radial-gradient(circle at 90% 90%, rgba(255,255,255,0.08), transparent 40%);
            opacity: 0;
            animation: pillGlow 3.2s ease-in-out infinite;
        }
        @keyframes amountPulse {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-2px); }
        }
        @keyframes pillGlow {
            0% { opacity: 0; }
            50% { opacity: 1; }
            100% { opacity: 0; }
        }

        /* Futuristic Tracking Steps */
        .tracking-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.04), rgba(255,255,255,0.02));
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 18px;
            padding: 1.6rem;
            margin: 1.5rem 0 2.5rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0,0,0,0.25);
        }
        .tracking-card::before {
            content: '';
            position: absolute;
            inset: -50%;
            background: radial-gradient(circle at 20% 20%, rgba(56,189,248,0.12), transparent 35%),
                        radial-gradient(circle at 80% 40%, rgba(99,102,241,0.12), transparent 30%),
                        radial-gradient(circle at 60% 80%, rgba(52,211,153,0.12), transparent 30%);
            filter: blur(10px);
            z-index: 0;
        }
        .tracking-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            position: relative;
            z-index: 1;
        }
        .tracking-kicker {
            letter-spacing: 0.08em;
            font-size: 0.78rem;
            color: #a5b4fc;
            text-transform: uppercase;
            margin-bottom: 0.25rem;
        }
        .tracking-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #e2e8f0;
        }
        .tracking-meta {
            font-size: 0.9rem;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .tracking-rail {
            position: relative;
            margin-top: 1.2rem;
            padding-left: 10px;
        }
        .tracking-rail::before {
            content: '';
            position: absolute;
            left: 18px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(180deg, rgba(99,102,241,0.5), rgba(45,212,191,0.4));
            opacity: 0.7;
        }
        .track-step {
            position: relative;
            display: grid;
            grid-template-columns: 60px 1fr;
            gap: 1rem;
            padding: 0.75rem 0.75rem 0.75rem 0;
            border-radius: 12px;
            margin-left: 10px;
            z-index: 1;
        }
        .track-step::before {
            content: '';
            position: absolute;
            left: 7px;
            top: 22px;
            width: 2px;
            height: calc(100% - 22px);
            background: linear-gradient(180deg, rgba(255,255,255,0), rgba(99,102,241,0.4));
            opacity: 0.4;
        }
        .track-step:last-child::before { display: none; }
        .track-step:hover { background: rgba(255,255,255,0.02); }
        .track-step .state-pill {
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 12px;
            letter-spacing: 0.01em;
            background: rgba(255,255,255,0.08);
            color: #cbd5e1;
            border: 1px solid rgba(255,255,255,0.15);
        }
        .track-step.done .state-pill { background: rgba(52,211,153,0.15); border-color: rgba(52,211,153,0.4); color: #a7f3d0; }
        .track-step.current .state-pill { background: rgba(99,102,241,0.15); border-color: rgba(99,102,241,0.4); color: #c7d2fe; }
        .track-step.next .state-pill { background: rgba(148,163,184,0.1); border-color: rgba(148,163,184,0.2); color: #cbd5e1; }
        .track-node {
            position: relative;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            margin: 0 auto;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            overflow: hidden;
        }
        .track-node::after {
            content: '';
            position: absolute;
            inset: -30%;
            background: conic-gradient(from 0deg, rgba(99,102,241,0.25), rgba(56,189,248,0.25), rgba(52,211,153,0.25), rgba(99,102,241,0.25));
            animation: spin 6s linear infinite;
            opacity: 0;
        }
        .track-node .node-core {
            position: relative;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: #0f172a;
            border: 2px solid rgba(255,255,255,0.2);
            display: grid;
            place-items: center;
            z-index: 1;
        }
        .track-node .node-number {
            font-size: 12px;
            color: #cbd5e1;
            font-weight: 700;
        }
        .track-node .node-icon {
            display: none;
        }
        .track-step.done .track-node { border-color: rgba(52,211,153,0.5); box-shadow: 0 0 0 6px rgba(52,211,153,0.12); }
        .track-step.done .track-node::after { opacity: 0.6; }
        .track-step.done .node-core { border-color: rgba(52,211,153,0.8); }
        .track-step.current .track-node { border-color: rgba(99,102,241,0.6); box-shadow: 0 0 0 8px rgba(99,102,241,0.1); }
        .track-step.current .track-node::after { opacity: 0.7; animation-duration: 3s; }
        .track-step.current .node-core { border-color: rgba(99,102,241,0.8); }
        .track-body {
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding-top: 2px;
        }
        .track-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .track-label {
            font-weight: 600;
            color: #e2e8f0;
        }
        .track-desc {
            color: #94a3b8;
            font-size: 0.92rem;
            line-height: 1.4;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (max-width: 640px) {
            .track-row { flex-direction: column; align-items: flex-start; }
            .tracking-rail::before { left: 14px; }
            .track-step { grid-template-columns: 50px 1fr; }
        }

        /* Light theme overrides for upcoming pill */
        body.theme-light .upcoming-box .upcoming-pill {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #0f172a;
            box-shadow: 0 6px 16px rgba(15,23,42,0.08);
        }
        body.theme-light .upcoming-box .upcoming-pill svg {
            stroke: #334155;
        }

        /* Light theme overrides for tracking */
        body.theme-light .tracking-card {
            background: rgba(255,255,255,0.92);
            border-color: rgba(148,163,184,0.35);
            box-shadow: 0 12px 30px rgba(148,163,184,0.35);
        }
        body.theme-light .tracking-title { color: #0f172a; }
        body.theme-light .tracking-kicker { color: #6b7280; }
        body.theme-light .tracking-meta { color: #475569; }
        body.theme-light .tracking-rail::before { background: linear-gradient(180deg, rgba(99,102,241,0.35), rgba(45,212,191,0.3)); }
        body.theme-light .track-step:hover { background: rgba(15,23,42,0.04); }
        body.theme-light .track-node { background: rgba(255,255,255,0.9); border-color: rgba(148,163,184,0.35); }
        body.theme-light .node-core { background: #fff; border-color: rgba(148,163,184,0.6); }
        body.theme-light .node-number { color: #334155; }
        body.theme-light .track-label { color: #0f172a; }
        body.theme-light .track-desc { color: #475569; }
        body.theme-light .state-pill { background: rgba(241,245,249,0.9); color: #334155; border-color: rgba(203,213,225,0.8); }
        body.theme-light .track-step.done .state-pill { background: rgba(220,252,231,0.9); color: #166534; border-color: rgba(187,247,208,0.9); }
        body.theme-light .track-step.current .state-pill { background: rgba(224,231,255,0.9); color: #3730a3; border-color: rgba(199,210,254,0.9); }
        
        /* Progress Bar */
        .progress-section {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .progress-title {
            color: #fff;
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
        }
        
        .progress-steps {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
        }
        
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: rgba(102, 126, 234, 0.3);
            z-index: -1;
        }
        
        .progress-step {
            text-align: center;
            position: relative;
            flex: 1;
        }
        
        .progress-step-number {
            width: 40px;
            height: 40px;
            margin: 0 auto 0.75rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            border: 2px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.05);
            color: #cbd5e1;
            transition: all 0.3s;
        }
        
        .progress-step.active .progress-step-number {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-color: #667eea;
            color: #fff;
        }
        
        .progress-step.completed .progress-step-number {
            background: linear-gradient(135deg, #34d399, #10b981);
            border-color: #34d399;
            color: #fff;
        }
        
        .progress-step-label {
            font-size: 0.85rem;
            color: #94a3b8;
        }
        
        .progress-step.active .progress-step-label {
            color: #667eea;
            font-weight: 600;
        }
        
        .progress-step.completed .progress-step-label {
            color: #34d399;
        }
        
        /* Timeline */
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 6px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: rgba(102, 126, 234, 0.3);
        }
        
        .timeline-item {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -20px;
            top: 2px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: rgba(102, 126, 234, 0.5);
            border: 2px solid rgba(102, 126, 234, 0.3);
        }
        
        .timeline-item.active::before {
            background: #667eea;
            border-color: #667eea;
        }
        
        .timeline-item.completed::before {
            background: #34d399;
            border-color: #34d399;
        }
        
        .timeline-item-title {
            color: #fff;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .timeline-item-date {
            color: #64748b;
            font-size: 0.9rem;
        }
        
        /* No Result */
        .no-result {
            text-align: center;
            padding: 3rem 1rem;
        }
        
        .no-result-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1rem;
            opacity: 0.5;
        }
        
        .no-result-text {
            color: #94a3b8;
            font-size: 1.1rem;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header-actions {
                margin-left: 0;
            }
            
            .status-grid {
                grid-template-columns: 1fr;
            }
            
            .progress-steps {
                flex-direction: column;
                gap: 1.5rem;
            }
            
            .progress-steps::before {
                top: auto;
                left: 20px;
                right: auto;
                width: 2px;
                height: 100%;
            }
        }
    </style>
</head>
<?php
// กำหนด theme class (align with index.php)
$themeClass = '';
if ($publicTheme === 'light') {
    $themeClass = 'theme-light';
} elseif ($publicTheme === 'auto') {
    $themeClass = '';
}
?>
<body class="<?php echo $themeClass; ?>" data-theme-mode="<?php echo htmlspecialchars($publicTheme, ENT_QUOTES, 'UTF-8'); ?>">
        <script>
            // Respect admin setting; only apply saved preference when admin chooses 'auto'
            (function() {
                try {
                    const mode = document.body.getAttribute('data-theme-mode');
                    if (mode === 'light') {
                        document.body.classList.add('theme-light');
                    } else if (mode === 'dark') {
                        document.body.classList.remove('theme-light');
                    } else if (mode === 'auto') {
                        const saved = localStorage.getItem('public_theme');
                        if (saved === 'light') document.body.classList.add('theme-light');
                        if (saved === 'dark') document.body.classList.remove('theme-light');
                    }
                } catch(e) {}
            })();
        </script>
    <?php if ($publicTheme === 'auto'): ?>
    <script>
      (function() {
        const hour = new Date().getHours();
        const isDay = hour >= 6 && hour < 18;
        if (isDay) {
          document.body.classList.add('theme-light');
        }
      })();
    </script>
    <?php endif; ?>
    <!-- Animated Background -->
    <div class="bg-animation">
        <div class="bg-gradient"></div>
        <div class="floating-orb orb-1"></div>
        <div class="floating-orb orb-2"></div>
        <div class="floating-orb orb-3"></div>
    </div>
    <div class="grid-lines"></div>
    <div class="particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>
    
    <div class="container">
        <!-- Header -->
        <div class="header">
            <a href="../index.php" class="logo">
                <img src="../Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="">
                <h1><?php echo htmlspecialchars($siteName); ?></h1>
            </a>
            <div class="header-actions">
                <a href="../index.php" class="btn-back">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="19" y1="12" x2="5" y2="12" stroke-linecap="round"/>
                        <polyline points="12 19 5 12 12 5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span>กลับหน้าแรก</span>
                </a>
                <button id="btnThemeToggle" class="btn-back" style="margin-left:8px;">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="4" />
                        <path d="M21 12a9 9 0 1 1-9-9" />
                    </svg>
                    <span>สลับธีม</span>
                </button>
            </div>
        </div>
        
        <!-- Page Title -->
        <div class="page-title">
            <h2>ตรวจสอบสถานะการจอง</h2>
            <p>ค้นหาข้อมูลการจองห้องพักของคุณ</p>
        </div>
        
        <!-- Search Form -->
        <div class="search-card">
            <form method="post" class="search-form">
                <input type="text" name="id_card" id="id_card_input" placeholder="กรอกเลขบัตรประชาชน 13 หลัก" required maxlength="13" inputmode="numeric">
                <button type="submit"><span>ค้นหา</span></button>
            </form>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-error">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($bookingInfo && $searchMethod === 'found'): ?>
        
        <!-- Status Overview -->
        <div class="status-grid">
            <!-- Booking Status -->
            <div class="status-card">
                <div class="status-card-header">
                    <div class="status-icon" style="background: <?php 
                        echo match($currentBkgStatus) {
                            '1' => 'rgba(251, 191, 36, 0.2)',
                            '2' => 'rgba(52, 211, 153, 0.2)',
                            default => 'rgba(248, 113, 113, 0.2)'
                        };
                    ?>">
                        <?php if ($currentBkgStatus === '1'): ?>
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2">
                                <circle cx="12" cy="12" r="10">
                                    <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="3s" repeatCount="indefinite"/>
                                </circle>
                                <polyline points="12 6 12 12 16 14">
                                    <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="2s" repeatCount="indefinite"/>
                                </polyline>
                            </svg>
                        <?php elseif ($currentBkgStatus === '2'): ?>
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#34d399" stroke-width="2.5">
                                <circle cx="12" cy="12" r="10" stroke-dasharray="63" stroke-dashoffset="0">
                                    <animate attributeName="stroke-dashoffset" from="63" to="0" dur="0.8s" fill="freeze"/>
                                </circle>
                                <polyline points="8 12 11 15 16 9" stroke-dasharray="15" stroke-dashoffset="0">
                                    <animate attributeName="stroke-dashoffset" from="15" to="0" dur="0.6s" begin="0.4s" fill="freeze"/>
                                </polyline>
                            </svg>
                        <?php else: ?>
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2.5">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="8" y1="8" x2="16" y2="16">
                                    <animate attributeName="opacity" values="0;1" dur="0.3s" fill="freeze"/>
                                </line>
                                <line x1="16" y1="8" x2="8" y2="16">
                                    <animate attributeName="opacity" values="0;1" dur="0.3s" begin="0.15s" fill="freeze"/>
                                </line>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="status-card-title">สถานะการจอง</div>
                        <span class="status-badge <?php echo $bookingBadge['class']; ?>">
                            <?php echo $bookingBadge['label']; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Contract Status -->
            <?php if ($bookingInfo['ctr_id']): ?>
            <div class="status-card">
                <div class="status-card-header">
                    <div class="status-icon" style="background: <?php 
                        echo match($currentCtrStatus) {
                            '0' => 'rgba(52, 211, 153, 0.2)',
                            '1' => 'rgba(248, 113, 113, 0.2)',
                            default => 'rgba(251, 191, 36, 0.2)'
                        };
                    ?>">
                        <?php if ($currentCtrStatus === '0'): ?>
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#34d399" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke-dasharray="80" stroke-dashoffset="0">
                                    <animate attributeName="stroke-dashoffset" from="80" to="0" dur="1s" fill="freeze"/>
                                </path>
                                <polyline points="14 2 14 8 20 8" opacity="0">
                                    <animate attributeName="opacity" values="0;1" dur="0.3s" begin="0.7s" fill="freeze"/>
                                </polyline>
                                <line x1="8" y1="13" x2="16" y2="13" opacity="0">
                                    <animate attributeName="opacity" values="0;1" dur="0.3s" begin="1s" fill="freeze"/>
                                </line>
                                <line x1="8" y1="17" x2="13" y2="17" opacity="0">
                                    <animate attributeName="opacity" values="0;1" dur="0.3s" begin="1.2s" fill="freeze"/>
                                </line>
                            </svg>
                        <?php elseif ($currentCtrStatus === '1'): ?>
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2.5">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="8" y1="8" x2="16" y2="16">
                                    <animate attributeName="opacity" values="0;1" dur="0.3s" fill="freeze"/>
                                </line>
                                <line x1="16" y1="8" x2="8" y2="16">
                                    <animate attributeName="opacity" values="0;1" dur="0.3s" begin="0.15s" fill="freeze"/>
                                </line>
                            </svg>
                        <?php else: ?>
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2">
                                <circle cx="12" cy="12" r="10">
                                    <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="3s" repeatCount="indefinite"/>
                                </circle>
                                <polyline points="12 6 12 12 16 14">
                                    <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="2s" repeatCount="indefinite"/>
                                </polyline>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="status-card-title">สถานะสัญญา</div>
                        <span class="status-badge <?php echo $contractStatuses[$currentCtrStatus]['class'] ?? 'pending'; ?>">
                            <?php echo $contractStatuses[$currentCtrStatus]['label'] ?? 'ไม่ทราบ'; ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Payment Status -->
            <?php if ($bookingInfo['exp_id']): ?>
            <div class="status-card">
                <div class="status-card-header">
                    <div class="status-icon" style="background: <?php 
                        echo match($currentExpStatus) {
                            '1' => 'rgba(52, 211, 153, 0.2)',
                            '0' => 'rgba(251, 191, 36, 0.2)',
                            default => 'rgba(248, 113, 113, 0.2)'
                        };
                    ?>">
                        <?php if ($currentExpStatus === '1'): ?>
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#34d399" stroke-width="2.5">
                                <circle cx="12" cy="12" r="10" stroke-dasharray="63" stroke-dashoffset="0">
                                    <animate attributeName="stroke-dashoffset" from="63" to="0" dur="0.8s" fill="freeze"/>
                                </circle>
                                <polyline points="8 12 11 15 16 9" stroke-dasharray="15" stroke-dashoffset="0">
                                    <animate attributeName="stroke-dashoffset" from="15" to="0" dur="0.6s" begin="0.4s" fill="freeze"/>
                                </polyline>
                            </svg>
                        <?php elseif ($currentExpStatus === '0'): ?>
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2">
                                <circle cx="12" cy="12" r="10">
                                    <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="3s" repeatCount="indefinite"/>
                                </circle>
                                <polyline points="12 6 12 12 16 14">
                                    <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="2s" repeatCount="indefinite"/>
                                </polyline>
                            </svg>
                        <?php else: ?>
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2.5">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="8" y1="8" x2="16" y2="16">
                                    <animate attributeName="opacity" values="0;1" dur="0.3s" fill="freeze"/>
                                </line>
                                <line x1="16" y1="8" x2="8" y2="16">
                                    <animate attributeName="opacity" values="0;1" dur="0.3s" begin="0.15s" fill="freeze"/>
                                </line>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="status-card-title">สถานะการชำระมัดจำ</div>
                        <span class="status-badge <?php echo $paymentStatuses[$currentExpStatus]['class'] ?? 'pending'; ?>">
                            <?php echo $paymentStatuses[$currentExpStatus]['label'] ?? 'ไม่ทราบ'; ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Futuristic Tracking Steps -->
        <?php $latestStepLabel = $trackingSteps[$progressStage - 1]['label'] ?? 'กำลังดำเนินการ'; ?>
        <div class="tracking-card">
            <div class="tracking-header">
                <div>
                    <div class="tracking-kicker">เส้นทางการจอง</div>
                    <div class="tracking-title">สถานะล่าสุด: <?php echo htmlspecialchars($latestStepLabel); ?></div>
                </div>
                <div class="tracking-meta">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="9" opacity="0.4"/>
                        <polyline points="12 7 12 12 15 14" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <span><?php echo !empty($bookingInfo['bkg_checkin_date']) ? 'วันเข้าพัก: ' . htmlspecialchars(thaiDate($bookingInfo['bkg_checkin_date'], 'd M Y')) : 'เตรียมเอกสารให้พร้อม'; ?></span>
                </div>
            </div>
            <div class="tracking-rail">
                <?php foreach ($trackingSteps as $idx => $step): ?>
                <div class="track-step <?php echo htmlspecialchars($step['state']); ?>">
                    <div class="track-node">
                        <div class="node-core">
                            <?php if ($step['state'] === 'done'): ?>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#34d399" stroke-width="2.5">
                                    <polyline points="5 13 10 17 19 7" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            <?php elseif ($step['state'] === 'current'): ?>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#a5b4fc" stroke-width="2">
                                    <circle cx="12" cy="12" r="6">
                                        <animate attributeName="r" values="6;7;6" dur="1.6s" repeatCount="indefinite" />
                                    </circle>
                                </svg>
                            <?php else: ?>
                                <span class="node-number"><?php echo $idx + 1; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="track-body">
                        <div class="track-row">
                            <div class="track-label"><?php echo htmlspecialchars($step['label']); ?></div>
                            <span class="state-pill <?php echo htmlspecialchars($step['state']); ?>">
                                <?php echo $step['state'] === 'done' ? 'เสร็จแล้ว' : ($step['state'] === 'current' ? 'กำลังดำเนินการ' : 'รอถัดไป'); ?>
                            </span>
                        </div>
                        <div class="track-desc"><?php echo htmlspecialchars($step['desc']); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Essential Booking & Contract Snapshot -->
        <div class="info-section">
            <h3>ข้อมูลสำคัญ</h3>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">ชื่อผู้จอง</div>
                    <div class="info-value"><?php echo htmlspecialchars($bookingInfo['tnt_name'] ?? '-'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">เบอร์โทร</div>
                    <div class="info-value"><?php echo htmlspecialchars($bookingInfo['tnt_phone'] ?? '-'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">ห้อง</div>
                    <div class="info-value"><?php echo htmlspecialchars($bookingInfo['room_number'] ?? '-'); ?> · <?php echo htmlspecialchars($bookingInfo['type_name'] ?? '-'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">ค่าห้อง/เดือน</div>
                    <div class="info-value highlight">฿<?php echo number_format($bookingInfo['type_price'] ?? 0); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">มัดจำ</div>
                    <div class="info-value highlight">฿<?php echo number_format($bookingInfo['ctr_deposit'] ?? 0); ?></div>
                </div>
                <?php 
                  // Upcoming room fee due logic (simplified)
                  $deposit = floatval($bookingInfo['ctr_deposit'] ?? 0);
                  $paid = floatval($bookingInfo['paid_amount'] ?? 0);
                  $monthly = floatval($bookingInfo['type_price'] ?? 0);
                  $expStatus = $currentExpStatus; // 0=รอตรวจ,1=ตรวจแล้ว
                  $bkgStatus = $currentBkgStatus; // 1=จองแล้ว,2=เข้าพักแล้ว
                  $ctrStart = $bookingInfo['ctr_start'] ?? null;
                  $bkgCheckin = $bookingInfo['bkg_checkin_date'] ?? null;
                  $dueBase = !empty($ctrStart) ? $ctrStart : $bkgCheckin;
                  $dueDateStr = '-';
                  if (!empty($dueBase)) { try { $dueDateStr = date_format(date_create($dueBase), 'd M Y'); } catch (Exception $e) {} }
                  $showUpcoming = ($expStatus === '1' && $bkgStatus === '1' && $monthly > 0);
                  $remainingColor = $showUpcoming ? '#fbbf24' : (($deposit - $paid > 0) ? '#f87171' : '#34d399');
                ?>
                <div class="info-item upcoming-box">
                    <div class="info-label">คงเหลือ</div>
                    <div class="upcoming-content" style="--accent: <?php echo $remainingColor; ?>;">
                        <span class="amount"><?php echo ($showUpcoming ? '฿' . number_format($monthly) : '฿' . number_format(max(0, $deposit - $paid))); ?></span>
                        <?php if ($showUpcoming): ?>
                        <span class="upcoming-pill">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M12 6v6l4 2"/>
                            </svg>
                            ที่กำลังมาถึง: <?php echo $dueDateStr; ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Next Steps for ผู้จอง -->
        <div class="info-section">
            <h3>ขั้นตอนต่อไป</h3>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">มาถึงหอ</div>
                    <div class="info-value">ปักหมุดนำทาง</div>
                    <div class="info-value" style="margin-top:8px;">
                        <a class="status-badge verified" style="text-decoration:none; padding:8px 12px; display:inline-flex; align-items:center; gap:8px;" href="https://maps.google.com/?q=<?php echo urlencode($siteName); ?>" target="_blank" rel="noopener">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 12-9 12S3 17 3 10a9 9 0 1 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            เปิดแผนที่
                        </a>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">ติดต่อ</div>
                    <div class="info-value">โทร: <?php echo htmlspecialchars($contactPhone ?? '-'); ?></div>
                    <div class="info-value">อีเมล: <?php echo htmlspecialchars($contactEmail ?? '-'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">เตรียมเอกสาร</div>
                    <div class="info-value">- บัตรประชาชนตัวจริง<br>- หลักฐานชำระมัดจำ (ถ้ามี)<br>- เอกสารเพิ่มเติมที่เจ้าหน้าที่แจ้ง</div>
                </div>
                <div class="info-item">
                    <div class="info-label">ชำระวันเข้าพัก</div>
                    <div class="info-value">ค่าห้องเดือนแรก ฿<?php echo number_format($bookingInfo['type_price'] ?? 0); ?><br>ชำระในวันเข้าพัก</div>
                </div>
            </div>
        </div>

        <!-- Contract Access -->
        <?php if (!empty($bookingInfo['ctr_id']) && !empty($bookingInfo['access_token']) && ($currentExpStatus === '1' || $currentBkgStatus === '2')): ?>
        <div class="info-section">
            <h3>สัญญาเช่า</h3>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">ดู/ดาวน์โหลด</div>
                    <div class="info-value" style="margin-top:8px;">
                        <a class="status-badge verified" style="text-decoration:none; padding:10px 14px; display:inline-flex; align-items:center; gap:8px;" href="../Tenant/contract.php?token=<?php echo urlencode($bookingInfo['access_token']); ?>" target="_blank" rel="noopener">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="11" y2="17"/></svg>
                            เปิดสัญญาเช่า
                        </a>
                        <div style="margin-top:10px; color:#94a3b8; font-size:14px; line-height:1.6;">
                            คำแนะนำเกี่ยวกับสัญญาและหลักฐานการชำระเงิน:<br>
                            1) ดาวน์โหลดสัญญา หากมีเครื่องปริ้นให้ปริ้นและเซ็นก่อนนำมาแสดงในวันเข้าพัก<br>
                            2) หากคุณได้อัปโหลดหลักฐานการชำระเงิน (สลิป/ใบเสร็จ) ในระบบแล้ว ให้เตรียมแสดงไฟล์หรือแสดงบนมือถือเพื่อให้เจ้าหน้าที่ตรวจสอบได้สะดวก<br>
                            3) หากยังไม่ได้อัปโหลดหลักฐาน กรุณานำใบเสร็จตัวจริง (เช่น สลิปโอน/ใบเสร็จเงินสด) และบัตรประชาชนของผู้ลงชื่อมาด้วยในวันเข้าพัก<br>
                            4) ถ้าไม่สะดวกปริ้น สามารถมาลงชื่อเซ็นสัญญากับเจ้าหน้าที่ในวันเข้าพักได้
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- No Booking Found -->
        <?php else: ?>
        <?php if ($searchMethod !== 'found' && empty($error)): ?>
        <div class="no-result">
            <svg class="no-result-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            <p class="no-result-text">กรอกเลขบัตรประชาชนเพื่อค้นหาสถานะการจอง</p>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const idCardInput = document.getElementById('id_card_input');
            const btnThemeToggle = document.getElementById('btnThemeToggle');
            if (btnThemeToggle) {
                btnThemeToggle.addEventListener('click', function() {
                    const mode = document.body.getAttribute('data-theme-mode');
                    // Allow user toggle only in auto mode; otherwise follow admin setting
                    if (mode === 'auto') {
                        const isLight = document.body.classList.toggle('theme-light');
                        try { localStorage.setItem('public_theme', isLight ? 'light' : 'dark'); } catch(e) {}
                    }
                });
            }
            
            if (idCardInput) {
                console.log('✅ พบ input element');
                
                // บังคับให้แสดงตัวเลข
                idCardInput.style.color = '#ffffff';
                idCardInput.style.fontSize = '24px';
                idCardInput.style.fontWeight = '700';
                idCardInput.style.letterSpacing = '5px';
                idCardInput.style.textAlign = 'center';
                idCardInput.style.fontFamily = 'Courier New, monospace';
                
                // จัดการการพิมพ์
                idCardInput.addEventListener('input', function(e) {
                    // กรองเฉพาะตัวเลข
                    let value = e.target.value.replace(/\D/g, '');
                    value = value.slice(0, 13);
                    e.target.value = value;
                    
                    // แสดงใน console
                    console.log('📝 ค่าที่พิมพ์:', value, '| ความยาว:', value.length);
                    
                    // บังคับ style อีกครั้ง
                    e.target.style.color = '#ffffff';
                });
                
                // เมื่อ focus
                idCardInput.addEventListener('focus', function(e) {
                    console.log('🎯 Focus เข้าช่อง input');
                    e.target.style.color = '#ffffff';
                });
                
                // เมื่อพิมพ์
                idCardInput.addEventListener('keypress', function(e) {
                    console.log('⌨️ กดปุ่ม:', e.key);
                });
                
            } else {
                console.error('❌ ไม่พบ input element!');
            }
        });
    </script>
</body>
</html>
