<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../ConnectDB.php';

$pdo = connectDB();

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
                    SUM(CASE WHEN p.pay_status = '1' THEN p.pay_amount ELSE 0 END) as paid_amount
                FROM tenant t
                LEFT JOIN booking b ON t.tnt_id = b.tnt_id AND b.bkg_status IN ('1', '2')
                LEFT JOIN room r ON b.room_id = r.room_id
                LEFT JOIN roomtype rt ON r.type_id = rt.type_id
                LEFT JOIN contract c ON t.tnt_id = c.tnt_id AND c.ctr_status = '0'
                LEFT JOIN expense e ON c.ctr_id = e.ctr_id
                LEFT JOIN payment p ON e.exp_id = p.exp_id
                WHERE t.tnt_id = ?
                GROUP BY t.tnt_id
            ");
            $stmt->execute([$idCard]);
            $bookingInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$bookingInfo || !$bookingInfo['bkg_id']) {
                $error = 'ไม่พบข้อมูลการจองของคุณ';
            } else {
                $searchMethod = 'found';
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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Prompt', system-ui, sans-serif;
            background: #0a0a0f;
            min-height: 100vh;
            color: #fff;
            overflow-x: hidden;
        }

        /* Animated Background */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .bg-gradient {
            position: absolute;
            width: 100%;
            height: 100%;
            background: linear-gradient(125deg, #0a0a0f 0%, #1a1a2e 25%, #16213e 50%, #0f3460 75%, #1a1a2e 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .floating-orb {
            position: absolute;
            border-radius: 50%;
            opacity: 0.1;
            filter: blur(40px);
            animation: float 20s ease-in-out infinite;
        }

        .orb1 { width: 300px; height: 300px; background: #667eea; top: -100px; left: -100px; animation-duration: 25s; }
        .orb2 { width: 250px; height: 250px; background: #764ba2; top: 50%; right: -50px; animation-duration: 30s; animation-delay: 5s; }
        .orb3 { width: 200px; height: 200px; background: #34d399; bottom: -50px; left: 10%; animation-duration: 28s; animation-delay: 10s; }

        @keyframes float {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(30px, -30px); }
        }
        
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
            gap: 6px;
            padding: 10px 16px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            color: #cbd5e1;
            text-decoration: none;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            background: rgba(255,255,255,0.15);
            color: #fff;
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
            padding: 1rem 1.25rem;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.05);
            color: #fff;
            font-size: 1rem;
        }
        
        .search-form input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }
        
        .search-form input::placeholder {
            color: #64748b;
        }
        
        .search-form button {
            padding: 1rem 2rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 10px;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .search-form button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
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
<body>
    <!-- Animated Background -->
    <div class="bg-animation">
        <div class="bg-gradient"></div>
        <div class="floating-orb orb1"></div>
        <div class="floating-orb orb2"></div>
        <div class="floating-orb orb3"></div>
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
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                    </svg>
                    กลับหน้าแรก
                </a>
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
                <input type="text" name="id_card" placeholder="กรอกเลขบัตรประชาชน 13 หลัก" required maxlength="13" inputmode="numeric">
                <button type="submit">ค้นหา</button>
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
                        <?php 
                            echo match($currentBkgStatus) {
                                '1' => '⏳',
                                '2' => '✓',
                                default => '✗'
                            };
                        ?>
                    </div>
                    <div>
                        <div class="status-card-title">สถานะการจอง</div>
                        <span class="status-badge <?php echo $bookingStatuses[$currentBkgStatus]['class'] ?? 'pending'; ?>">
                            <?php echo $bookingStatuses[$currentBkgStatus]['label'] ?? 'ไม่ทราบ'; ?>
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
                        <?php 
                            echo match($currentCtrStatus) {
                                '0' => '📜',
                                '1' => '✗',
                                default => '⏳'
                            };
                        ?>
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
                        <?php 
                            echo match($currentExpStatus) {
                                '1' => '✓',
                                '0' => '⏳',
                                default => '✗'
                            };
                        ?>
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
        
        <!-- Booking Details -->
        <div class="info-section">
            <h3>ข้อมูลการจองห้อง</h3>
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
                    <div class="info-label">หมายเลขห้อง</div>
                    <div class="info-value"><?php echo htmlspecialchars($bookingInfo['room_number'] ?? '-'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">ประเภทห้อง</div>
                    <div class="info-value"><?php echo htmlspecialchars($bookingInfo['type_name'] ?? '-'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">ค่าห้องต่อเดือน</div>
                    <div class="info-value highlight">฿<?php echo number_format($bookingInfo['type_price'] ?? 0); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">เงินมัดจำ</div>
                    <div class="info-value highlight">฿<?php echo number_format($bookingInfo['ctr_deposit'] ?? 0); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Contract Details -->
        <?php if ($bookingInfo['ctr_id']): ?>
        <div class="info-section">
            <h3>รายละเอียดสัญญา</h3>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">วันที่เข้าพัก</div>
                    <div class="info-value"><?php echo date_format(date_create($bookingInfo['ctr_start']), 'd M Y'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">วันที่สิ้นสุดสัญญา</div>
                    <div class="info-value"><?php echo date_format(date_create($bookingInfo['ctr_end']), 'd M Y'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">ระยะเวลาเช่า</div>
                    <div class="info-value">
                        <?php 
                            $start = new DateTime($bookingInfo['ctr_start']);
                            $end = new DateTime($bookingInfo['ctr_end']);
                            $interval = $start->diff($end);
                            echo $interval->m . ' เดือน ' . $interval->d . ' วัน';
                        ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">ยอดชำระ</div>
                    <div class="info-value highlight">฿<?php echo number_format($bookingInfo['ctr_deposit'] ?? 0); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">ยอดที่ชำระแล้ว</div>
                    <div class="info-value highlight">฿<?php echo number_format($bookingInfo['paid_amount'] ?? 0); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">คงเหลือ</div>
                    <div class="info-value highlight" style="<?php echo ($bookingInfo['ctr_deposit'] - ($bookingInfo['paid_amount'] ?? 0) > 0) ? 'color: #f87171;' : 'color: #34d399;'; ?>">
                        ฿<?php echo number_format(max(0, ($bookingInfo['ctr_deposit'] ?? 0) - ($bookingInfo['paid_amount'] ?? 0))); ?>
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
</body>
</html>
