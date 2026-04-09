<?php
session_start();
require_once __DIR__ . '/../ConnectDB.php';
require_once __DIR__ . '/../includes/thai_date_helper.php';

// ตรวจสอบการ login
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}

$admin_name = $_SESSION['admin_username'];

try {
    $pdo = connectDB();
    
    // ดึงข้อมูล theme_color จาก system_settings (key-value format)
    $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'theme_color' LIMIT 1");
    $themeColor = $stmt->fetchColumn() ?: '#0f172a';
    
    // คำนวณความสว่างของสี
    $hex = ltrim($themeColor, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
    $isLight = $brightness > 155;
    
    // 1. รายงานข้อมูลการเข้าพัก
    // bkg_status: 0=รอยืนยัน, 1=จองแล้ว/รอเข้าพัก, 2=เข้าพักแล้ว, 3=ยกเลิก
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM booking WHERE bkg_status = 2");
    $booking_checkedin = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM booking b WHERE COALESCE(b.bkg_status, '0') = '1' AND NOT EXISTS (SELECT 1 FROM contract c WHERE c.room_id = b.room_id AND c.tnt_id = b.tnt_id) AND NOT EXISTS (SELECT 1 FROM contract c WHERE c.room_id = b.room_id AND c.ctr_status = '0' AND (c.ctr_end IS NULL OR c.ctr_end >= CURDATE()))");
    $booking_pending = $stmt->fetch()['total'] ?? 0;
    
    // 2. รายงานข้อมูลข่าวประชาสัมพันธ์
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM news");
    $news_count = $stmt->fetch()['total'] ?? 0;
    
    // 3. รายงานการแจ้งซ่อมอุปกรณ์
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM repair WHERE repair_status = 0");
    $repair_waiting = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM repair WHERE repair_status = 1");
    $repair_processing = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM repair WHERE repair_status = 2");
    $repair_completed = $stmt->fetch()['total'] ?? 0;
    
    // 4. ใบแจ้งชำระเงิน
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM payment WHERE pay_status = 0");
    $payment_pending = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM payment WHERE pay_status = 1");
    $payment_verified = $stmt->fetch()['total'] ?? 0;
    
    // 5. รายงานการชำระเงิน (รวมเฉพาะการชำระที่ตรวจสอบแล้ว/ยืนยันแล้ว)
    $stmt = $pdo->query("SELECT SUM(pay_amount) as total FROM payment WHERE pay_status = '1'");
    $total_payment = $stmt->fetch()['total'] ?? 0;
    
    // 6. รายงานข้อมูลห้องพัก
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM room WHERE room_status = 1");
    $room_available = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM room WHERE room_status = 0");
    $room_occupied = $stmt->fetch()['total'] ?? 0;
    
    // 7. รายงานสรุปการใช้น้ำ-ไฟ (ไม่รวมเดือนที่ใช้งาน 0 หน่วย)
    $stmt = $pdo->query("SELECT AVG(utl_water_end - utl_water_start) as avg_water, AVG(utl_elec_end - utl_elec_start) as avg_elec FROM utility WHERE (utl_water_end - utl_water_start) > 0 OR (utl_elec_end - utl_elec_start) > 0");
    $utility_avg = $stmt->fetch() ?? ['avg_water' => 0, 'avg_elec' => 0];
    $avg_water = round($utility_avg['avg_water'] ?? 0, 2);
    $avg_elec = round($utility_avg['avg_elec'] ?? 0, 2);
    
    // 8. รายงานข้อมูลรายรับ (ใช้ค่าเดียวกับ total_payment — เฉพาะรายการที่ตรวจสอบแล้ว)
    $total_revenue = $total_payment;
    
    // 9. ข้อมูลสัญญา
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM contract WHERE ctr_status = 0");
    $contract_active = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM contract WHERE ctr_status = 1");
    $contract_cancelled = $stmt->fetch()['total'] ?? 0;
    
    // ดึงข้อมูล Tenant (นับเฉพาะที่มีสัญญาที่ใช้งานอยู่)
    $stmt = $pdo->query("SELECT COUNT(DISTINCT tnt_id) as total FROM contract WHERE ctr_status = 0");
    $tenant_active = $stmt->fetch()['total'] ?? 0;
    
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
    
        // ข้อมูลรายได้รายเดือน (จากการชำระของผู้เช่า)
        $stmt = $pdo->query("SELECT DATE_FORMAT(pay_date, '%Y-%m') as month, SUM(pay_amount) as total 
            FROM payment 
            WHERE pay_status = '1' 
            GROUP BY DATE_FORMAT(pay_date, '%Y-%m')
            ORDER BY DATE_FORMAT(pay_date, '%Y-%m') DESC
            LIMIT 12");
        $monthly_revenue = array_reverse($stmt->fetchAll() ?: []);
        $miniRevenueLabels = [];
        $miniRevenueData = [];
        foreach ($monthly_revenue as $data) {
            $miniRevenueLabels[] = thaiMonthYear($data['month'] ?? '');
            $miniRevenueData[] = (float)($data['total'] ?? 0);
        }
    
    // ข้อมูล Booking trend (7 วันล่าสุด)
    $stmt = $pdo->query("SELECT DATE(bkg_date) as date, COUNT(*) as count 
            FROM booking 
            WHERE bkg_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(bkg_date)
            ORDER BY date ASC");
    $booking_trend = $stmt->fetchAll() ?: [];
    
    // ข้อมูล Contract status distribution
    $stmt = $pdo->query("SELECT ctr_status, COUNT(*) as count FROM contract GROUP BY ctr_status");
    $contract_distribution = [];
    foreach ($stmt->fetchAll() as $row) {
        switch ($row['ctr_status']) {
            case '0': $status = 'active'; break;
            case '2': $status = 'pending_cancel'; break;
            default:  $status = 'ended'; break;
        }
        $contract_distribution[$status] = ($contract_distribution[$status] ?? 0) + $row['count'];
    }
    
    // ข้อมูล Payment trend (7 วันล่าสุด — เฉพาะที่ตรวจสอบแล้ว)
    $stmt = $pdo->query("SELECT DATE(pay_date) as date, COUNT(*) as count, SUM(pay_amount) as total
            FROM payment 
            WHERE pay_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND pay_status = 1
            GROUP BY DATE(pay_date)
            ORDER BY date ASC");
    $payment_trend = $stmt->fetchAll() ?: [];
    
    // ข้อมูล Repair trends
    $stmt = $pdo->query("SELECT repair_status, COUNT(*) as count FROM repair GROUP BY repair_status");
    $repair_status_dist = [];
    foreach ($stmt->fetchAll() as $row) {
        $repair_status_dist[$row['repair_status']] = $row['count'];
    }
    
    // Occupancy Rate
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM room");
    $total_rooms = $stmt->fetch()['total'] ?? 0;
    $occupancy_rate = $total_rooms > 0 ? round(($room_occupied / $total_rooms) * 100, 1) : 0;
    
    // Utility Usage Trend (6 เดือนล่าสุด)
    $stmt = $pdo->query("SELECT DATE_FORMAT(utl_date, '%Y-%m') as month, 
            AVG(utl_water_end - utl_water_start) as avg_water, 
            AVG(utl_elec_end - utl_elec_start) as avg_elec
            FROM utility 
            GROUP BY DATE_FORMAT(utl_date, '%Y-%m')
            ORDER BY month DESC LIMIT 6");
    $utility_trend = array_reverse($stmt->fetchAll() ?: []);

    // Current & last month utility for mini meter display
    $curMonth = date('Y-m');
    $lastMonth = date('Y-m', strtotime('-1 month'));
    $stmt = $pdo->prepare("SELECT SUM(utl_water_end - utl_water_start) as total_water, SUM(utl_elec_end - utl_elec_start) as total_elec, MAX(utl_water_end) as latest_water_read, MAX(utl_elec_end) as latest_elec_read, COUNT(*) as room_count FROM utility WHERE DATE_FORMAT(utl_date,'%Y-%m') = ?");
    $stmt->execute([$curMonth]); $cur_utility = $stmt->fetch() ?? [];
    $stmt->execute([$lastMonth]); $last_utility = $stmt->fetch() ?? [];
    $cur_water_total  = intval($cur_utility['total_water'] ?? 0);
    $cur_elec_total   = intval($cur_utility['total_elec'] ?? 0);
    $cur_water_read   = intval($cur_utility['latest_water_read'] ?? 0);
    $cur_elec_read    = intval($cur_utility['latest_elec_read'] ?? 0);
    $cur_rooms_count  = intval($cur_utility['room_count'] ?? 0);
    $last_water_total = intval($last_utility['total_water'] ?? 0);
    $last_elec_total  = intval($last_utility['total_elec'] ?? 0);
    // Format reading as 7-digit string for odometer
    $water_digits = str_pad($cur_water_read, 7, '0', STR_PAD_LEFT);
    $elec_digits  = str_pad($cur_elec_read, 5, '0', STR_PAD_LEFT);
    $water_delta = $cur_water_total - $last_water_total;
    $elec_delta  = $cur_elec_total  - $last_elec_total;

    // Weekly Tenant Check-in (7 วันล่าสุด)
    $stmt = $pdo->query("SELECT DATE(ctr_start) as date, COUNT(*) as count 
            FROM contract 
            WHERE ctr_start >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(ctr_start)
            ORDER BY date ASC");
    $tenant_checkin_trend = $stmt->fetchAll() ?: [];
    
    // Room Types Distribution
    $stmt = $pdo->query("SELECT rt.type_name, COUNT(r.room_id) as count 
            FROM room r 
            JOIN roomtype rt ON r.type_id = rt.type_id 
            GROUP BY rt.type_id, rt.type_name");
    $room_types = $stmt->fetchAll() ?: [];
    
    // Today's Stats - จองห้องใหม่วันนี้
    $stmt = $pdo->query("SELECT COUNT(*) as today_bookings FROM booking b WHERE COALESCE(b.bkg_status, '0') = '1' AND DATE(bkg_date) = CURDATE() AND NOT EXISTS (SELECT 1 FROM contract c WHERE c.room_id = b.room_id AND c.tnt_id = b.tnt_id) AND NOT EXISTS (SELECT 1 FROM contract c WHERE c.room_id = b.room_id AND c.ctr_status = '0' AND (c.ctr_end IS NULL OR c.ctr_end >= CURDATE()))");
    $today_bookings = $stmt->fetch()['today_bookings'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as today_repairs FROM repair WHERE DATE(repair_date) = CURDATE()");
    $today_repairs = $stmt->fetch()['today_repairs'] ?? 0;
    
    $stmt = $pdo->query("SELECT SUM(pay_amount) as today_payments FROM payment WHERE DATE(pay_date) = CURDATE() AND pay_status = 1");
    $today_payments = $stmt->fetch()['today_payments'] ?? 0;
    
} catch (PDOException $e) {
    die('Database Error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - แดชบอร์ด</title>
    <?php include __DIR__ . '/../includes/sidebar_toggle.php'; ?>
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/animate-ui.css">
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css">
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/particle-effects.css">
    <script src="/dormitory_management/Public/Assets/Javascript/chart.umd.min.js"></script>
    <script src="/dormitory_management/Public/Assets/Javascript/particle-effects.js"></script>
    <style>
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            <?php if ($isLight): ?>
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
            <?php else: ?>
            background: linear-gradient(135deg, rgba(20,30,48,0.95), rgba(8,14,28,0.95));
            box-shadow: 0 10px 30px rgba(0,0,0,0.35);
            border: 1px solid rgba(255,255,255,0.05);
            <?php endif; ?>
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .dashboard-link-card {
            cursor: pointer;
        }

        .dashboard-link-card:focus-visible {
            outline: 2px solid #60a5fa;
            outline-offset: 2px;
        }

        <?php if (!$isLight): ?>
        .stat-card.danger { box-shadow: 0 10px 30px rgba(220,53,69,0.25); }
        .stat-card.success { box-shadow: 0 10px 30px rgba(40,167,69,0.22); }
        .stat-card.warning { box-shadow: 0 10px 30px rgba(255,193,7,0.22); }
        .stat-card.info { box-shadow: 0 10px 30px rgba(23,162,184,0.22); }
        <?php endif; ?>

        .stat-card h3 {
            font-size: 14px;
            <?php if ($isLight): ?>
            color: #6b7280;
            <?php else: ?>
            color: rgba(255,255,255,0.7);
            <?php endif; ?>
            margin-bottom: 10px;
            font-weight: normal;
        }

        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            <?php if ($isLight): ?>
            color: #111827;
            <?php else: ?>
            color: #f5f8ff;
            <?php endif; ?>
        }

        .chart-container {
            <?php if ($isLight): ?>
            background: #ffffff;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid #e5e7eb;
            <?php else: ?>
            background: linear-gradient(135deg, rgba(20,30,48,0.92), rgba(8,14,28,0.95));
            box-shadow: 0 10px 30px rgba(0,0,0,0.35);
            border: 1px solid rgba(255,255,255,0.05);
            <?php endif; ?>
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .chart-container:hover {
            transform: translateY(-2px);
            <?php if ($isLight): ?>
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
            <?php else: ?>
            box-shadow: 0 15px 40px rgba(0,0,0,0.45);
            <?php endif; ?>
        }

        .chart-container h3 {
            margin-top: 0;
            font-size: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            <?php if ($isLight): ?>
            color: #111827;
            border-bottom: 1px solid #e5e7eb;
            <?php else: ?>
            color: #f5f8ff;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            <?php endif; ?>
            padding-bottom: 16px;
            margin-bottom: 20px;
        }

        .chart-wrapper {
            position: relative;
            height: 200px;
        }
        
        .chart-wrapper.chart-lg {
            height: 260px;
        }
        
        .chart-wrapper.chart-sm {
            height: 160px;
        }

        .charts-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 1200px) {
            .charts-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .charts-row {
                grid-template-columns: 1fr;
            }
        }
        
        /* Mini Chart Container */
        .mini-chart-container {
            width: 90px;
            height: 90px;
            flex-shrink: 0;
            position: relative;
            overflow: hidden;
        }

        .mini-chart-container canvas {
            display: block;
            width: 100% !important;
            height: 100% !important;
        }
        
        .report-flex {
            display: flex;
            gap: 16px;
            align-items: center;
        }
        
        .report-flex > div:last-child {
            flex: 1;
            min-width: 0;
        }

        .report-section {
            <?php if ($isLight): ?>
            background: #ffffff;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid #e5e7eb;
            <?php else: ?>
            background: linear-gradient(135deg, rgba(20,30,48,0.92), rgba(8,14,28,0.95));
            box-shadow: 0 10px 30px rgba(0,0,0,0.35);
            border: 1px solid rgba(255,255,255,0.05);
            <?php endif; ?>
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .report-section[data-link] {
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .report-section[data-link]:hover {
            transform: translateY(-2px);
        }

        .report-section h3 {
            margin-top: 0;
            <?php if ($isLight): ?>
            color: #111827;
            border-bottom: 1px solid #e5e7eb;
            <?php else: ?>
            color: #f5f8ff;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            <?php endif; ?>
            padding-bottom: 15px;
        }

        .priority-group-title {
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.2px;
            margin: 6px 0 10px;
            padding-left: 2px;
            <?php if ($isLight): ?>
            color: #1f2937;
            <?php else: ?>
            color: #dbe7ff;
            <?php endif; ?>
        }

        .priority-group-title span {
            font-weight: 600;
            font-size: 12px;
            margin-left: 8px;
            <?php if ($isLight): ?>
            color: #6b7280;
            <?php else: ?>
            color: rgba(219, 231, 255, 0.75);
            <?php endif; ?>
        }

        .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .report-item {
            <?php if ($isLight): ?>
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            <?php else: ?>
            background: linear-gradient(135deg, rgba(30,41,59,0.9), rgba(15,23,42,0.95));
            border: 1px solid rgba(255,255,255,0.05);
            <?php endif; ?>
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }

        .report-item label {
            display: block;
            font-size: 12px;
            <?php if ($isLight): ?>
            color: #6b7280;
            <?php else: ?>
            color: rgba(255,255,255,0.65);
            <?php endif; ?>
            margin-bottom: 8px;
        }

        .report-item .value {
            font-size: 24px;
            font-weight: bold;
            <?php if ($isLight): ?>
            color: #111827;
            <?php else: ?>
            color: #f5f8ff;
            <?php endif; ?>
        }

        /* Stat card icons */
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
        }
        .stat-icon svg {
            width: 24px;
            height: 24px;
            stroke-width: 2;
        }
        .stat-icon.blue { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .stat-icon.green { background: linear-gradient(135deg, #22c55e, #16a34a); }
        .stat-icon.red { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .stat-icon.orange { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .stat-icon.cyan { background: linear-gradient(135deg, #06b6d4, #0891b2); }
        .stat-icon svg { color: #fff; stroke: #fff; }
        
        /* Section header icons */
        .section-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            margin-right: 8px;
            vertical-align: middle;
        }
        .section-icon svg {
            width: 16px;
            height: 16px;
            stroke: #fff;
        }
        .section-icon.blue { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .section-icon.green { background: linear-gradient(135deg, #22c55e, #16a34a); }
        .section-icon.red { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .section-icon.orange { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .section-icon.purple { background: linear-gradient(135deg, #a855f7, #7c3aed); }
        .section-icon.cyan { background: linear-gradient(135deg, #06b6d4, #0891b2); }
        .section-icon.pink { background: linear-gradient(135deg, #ec4899, #db2777); }
        .section-icon.indigo { background: linear-gradient(135deg, #6366f1, #4f46e5); }
        .section-icon.teal { background: linear-gradient(135deg, #14b8a6, #0d9488); }
        .section-icon.rose { background: linear-gradient(135deg, #f43f5e, #e11d48); }
        .section-icon.amber { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .section-icon.lime { background: linear-gradient(135deg, #84cc16, #65a30d); }
        .section-icon.sky { background: linear-gradient(135deg, #0ea5e9, #0284c7); }

        /* Apple-style Glassmorphism Hero Card */
        .hero-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .hero-card {
            <?php if ($isLight): ?>
            background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(249,250,251,0.95));
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            border: 1px solid rgba(229,231,235,0.8);
            <?php else: ?>
            background: linear-gradient(135deg, rgba(30,41,59,0.8), rgba(15,23,42,0.9));
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
            border: 1px solid rgba(255,255,255,0.08);
            backdrop-filter: blur(10px);
            <?php endif; ?>
            border-radius: 20px;
            padding: 24px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .hero-card:hover {
            transform: translateY(-4px);
            <?php if (!$isLight): ?>
            box-shadow: 0 12px 40px rgba(0,0,0,0.5);
            <?php else: ?>
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
            <?php endif; ?>
        }
        
        .hero-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            opacity: 0.1;
            transform: translate(30%, -30%);
        }
        
        .hero-card.gradient-blue::before { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .hero-card.gradient-green::before { background: linear-gradient(135deg, #22c55e, #16a34a); }
        .hero-card.gradient-purple::before { background: linear-gradient(135deg, #a855f7, #7c3aed); }
        .hero-card.gradient-orange::before { background: linear-gradient(135deg, #f59e0b, #d97706); }
        
        .hero-card-title {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            <?php if ($isLight): ?>
            color: #6b7280;
            <?php else: ?>
            color: rgba(255,255,255,0.6);
            <?php endif; ?>
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .hero-card-value {
            font-size: 42px;
            font-weight: 700;
            <?php if ($isLight): ?>
            color: #111827;
            <?php else: ?>
            color: #f5f8ff;
            <?php endif; ?>
            line-height: 1.1;
            margin-bottom: 12px;
        }
        
        .hero-card-trend {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
        }
        
        .trend-up { color: #22c55e; }
        .trend-down { color: #ef4444; }
        .trend-neutral { color: #6b7280; }
        
        /* Progress Ring */
        .progress-ring-container {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .progress-ring {
            position: relative;
            width: 100px;
            height: 100px;
        }
        
        .progress-ring svg {
            transform: rotate(-90deg);
        }
        
        .progress-ring-bg {
            <?php if ($isLight): ?>
            stroke: #e5e7eb;
            <?php else: ?>
            stroke: rgba(255,255,255,0.1);
            <?php endif; ?>
            fill: none;
            stroke-width: 8;
        }
        
        .progress-ring-fill {
            fill: none;
            stroke-width: 8;
            stroke-linecap: round;
            transition: stroke-dashoffset 1s ease;
        }
        
        .progress-ring-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 20px;
            font-weight: 700;
            <?php if ($isLight): ?>
            color: #111827;
            <?php else: ?>
            color: #f5f8ff;
            <?php endif; ?>
        }
        
        .progress-info h4 {
            margin: 0 0 4px 0;
            font-size: 16px;
            <?php if ($isLight): ?>
            color: #111827;
            <?php else: ?>
            color: #f5f8ff;
            <?php endif; ?>
        }
        
        .progress-info p {
            margin: 0;
            font-size: 13px;
            <?php if ($isLight): ?>
            color: #6b7280;
            <?php else: ?>
            color: rgba(255,255,255,0.6);
            <?php endif; ?>
        }
        
        /* Today's Activity Widget */
        .today-widget {
            <?php if ($isLight): ?>
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border: 1px solid #fcd34d;
            <?php else: ?>
            background: linear-gradient(135deg, rgba(251,191,36,0.15), rgba(245,158,11,0.1));
            border: 1px solid rgba(251,191,36,0.3);
            <?php endif; ?>
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .today-widget h3 {
            margin: 0 0 16px 0;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            <?php if ($isLight): ?>
            color: #92400e;
            <?php else: ?>
            color: #fcd34d;
            <?php endif; ?>
        }
        
        .today-stats {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }
        
        .today-stat {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .today-stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            <?php if ($isLight): ?>
            background: rgba(255,255,255,0.8);
            <?php else: ?>
            background: rgba(0,0,0,0.2);
            <?php endif; ?>
        }
        
        .today-stat-icon svg {
            width: 20px;
            height: 20px;
            <?php if ($isLight): ?>
            stroke: #92400e;
            <?php else: ?>
            stroke: #fcd34d;
            <?php endif; ?>
        }
        
        .today-stat-info span {
            display: block;
        }
        
        .today-stat-info .label {
            font-size: 12px;
            <?php if ($isLight): ?>
            color: #92400e;
            <?php else: ?>
            color: rgba(252,211,77,0.8);
            <?php endif; ?>
        }
        
        .today-stat-info .value {
            font-size: 20px;
            font-weight: 700;
            <?php if ($isLight): ?>
            color: #78350f;
            <?php else: ?>
            color: #fcd34d;
            <?php endif; ?>
        }
        
        /* Sparkline Mini Charts */
        .mini-chart {
            height: 40px;
            margin-top: 8px;
        }

        /* Two Column Layout for Charts */
        .charts-two-col {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 1024px) {
            .charts-two-col {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .stat-card .number {
                font-size: 24px;
            }

            .charts-row {
                grid-template-columns: 1fr;
            }

            .chart-wrapper {
                height: 250px;
            }
            
            .stat-icon {
                width: 40px;
                height: 40px;
            }
            .stat-icon svg {
                width: 20px;
                height: 20px;
            }
            
            .hero-stats {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .hero-card {
                padding: 20px;
            }
            
            .progress-ring-container {
                flex-direction: column;
                text-align: center;
            }
            
            .progress-ring {
                margin-bottom: 15px;
            }
            
            .chart-container {
                padding: 15px;
            }
            
            .chart-container h3 {
                font-size: 14px;
            }
            
            .today-widget {
                padding: 20px;
            }
            
            .today-stats {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-card .number {
                font-size: 20px;
            }
            
            .hero-card-value {
                font-size: 28px;
            }
            
            .chart-wrapper {
                height: 200px;
            }
            
            .section-icon {
                width: 30px;
                height: 30px;
            }
            
            .section-icon svg {
                width: 14px;
                height: 14px;
            }
        }

        /* ===== DASHBOARD METER SUMMARY (report_utility style) ===== */
        .dash-meter-summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin: 8px 0 10px;
        }
        .dash-meter-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 12px 8px 10px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.06);
            display: flex;
            flex-direction: column;
            align-items: center;
            overflow: hidden;
        }
        .dash-meter-visual {
            width: 100%;
            min-height: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .dash-meter-card.water .vm-water-body {
            transform: scale(0.70);
            transform-origin: center top;
            margin: -22px auto -34px;
        }
        .dash-meter-card.electric .vm-elec-body {
            transform: scale(0.88);
            transform-origin: center top;
            margin: -2px auto -20px;
        }
        .dash-meter-info {
            text-align: center;
            margin-top: 6px;
        }
        .dash-meter-label {
            font-size: 1.02rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 2px;
        }
        .dash-meter-stat {
            font-size: 0.95rem;
            color: #334155;
            font-weight: 600;
        }
        .dash-meter-delta {
            display: inline-block;
            margin-top: 6px;
            font-size: 0.98rem;
            font-weight: 800;
            border-radius: 999px;
            padding: 2px 10px;
        }
        .dash-meter-delta.up { background: #fef3c7; color: #92400e; }
        .dash-meter-delta.down { background: #dcfce7; color: #166534; }
        .dash-meter-delta.same { background: #f1f5f9; color: #475569; }
        .dash-meter-link {
            color: #0f172a;
            text-decoration: none;
            font-size: 1.03rem;
            font-weight: 500;
        }
        body.live-dark .dash-meter-card {
            background: rgba(30, 41, 59, 0.88);
            border-color: rgba(148, 163, 184, 0.28);
            box-shadow: 0 2px 12px rgba(2, 6, 23, 0.45);
        }
        body.live-dark .dash-meter-label { color: #e2e8f0; }
        body.live-dark .dash-meter-stat { color: #cbd5e1; }
        body.live-dark .dash-meter-link { color: #e2e8f0; }

        .vm-water-body { display: flex; align-items: center; justify-content: center; margin: 0 auto; max-width: 280px; position: relative; filter: drop-shadow(0 6px 12px rgba(0,0,0,0.35)); }
        .vm-pipe-left, .vm-pipe-right {
            width: 42px; height: 48px; flex-shrink: 0; position: relative; z-index: 2;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.08) 0%, transparent 8%, transparent 92%, rgba(0,0,0,0.10) 100%),
                linear-gradient(180deg, #52c8da 0%, #3cb8cc 5%, #2ea0b6 12%, #2290a8 22%, #1a7d96 35%, #146d84 50%, #0f5c72 65%, #0b4e62 78%, #084454 90%, #063a4a 100%);
            border-top: 1px solid rgba(100,210,230,0.35); border-bottom: 1.5px solid #042830;
        }
        .vm-pipe-left { border-radius: 8px 0 0 8px; border-right: none; margin-right: -3px; border-left: 1.5px solid #073e4c; box-shadow: inset 0 4px 8px rgba(255,255,255,0.15), inset 0 -4px 8px rgba(0,0,0,0.25), inset -3px 0 6px rgba(0,0,0,0.10), -2px 0 4px rgba(0,0,0,0.12); }
        .vm-pipe-right { border-radius: 0 8px 8px 0; border-left: none; margin-left: -3px; border-right: 1.5px solid #073e4c; box-shadow: inset 0 4px 8px rgba(255,255,255,0.15), inset 0 -4px 8px rgba(0,0,0,0.25), inset 3px 0 6px rgba(0,0,0,0.10), 2px 0 4px rgba(0,0,0,0.12); }
        .vm-pipe-flange {
            position: absolute; width: 14px; height: 110%; top: -5%; z-index: 3; border-radius: 2px;
            background: linear-gradient(180deg, #6ad0e0 0%, #48bcd0 8%, #30a4ba 20%, #228c9e 38%, #187888 55%, #106878 70%, #0c5868 85%, #084a5a 100%);
            border-top: 1px solid rgba(120,220,240,0.4); border-bottom: 1.5px solid #042830;
            box-shadow: inset 0 3px 5px rgba(255,255,255,0.20), inset 0 -3px 5px rgba(0,0,0,0.20);
        }
        .vm-pipe-left .vm-pipe-flange { right: -2px; border-right: 2px solid #0a3540; border-left: 1px solid #0a3540; box-shadow: 3px 0 6px rgba(0,0,0,0.25), inset 0 3px 5px rgba(255,255,255,0.20), inset 0 -3px 5px rgba(0,0,0,0.20); }
        .vm-pipe-right .vm-pipe-flange { left: -2px; border-left: 2px solid #0a3540; border-right: 1px solid #0a3540; box-shadow: -3px 0 6px rgba(0,0,0,0.25), inset 0 3px 5px rgba(255,255,255,0.20), inset 0 -3px 5px rgba(0,0,0,0.20); }
        .vm-pipe-bolt { position: absolute; width: 12px; height: 12px; background: radial-gradient(circle at 38% 30%, #a0e4ef 0%, #60c8d8 20%, #38a8bc 40%, #1e8ca0 60%, #0f6a7c 80%, #084a5a 100%); border-radius: 50%; top: 50%; left: 50%; transform: translate(-50%, -50%); box-shadow: inset 0 2px 3px rgba(255,255,255,0.50), inset 0 -2px 3px rgba(0,0,0,0.35), 0 1.5px 4px rgba(0,0,0,0.45); border: 0.5px solid #063a4a; }
        .vm-pipe-bolt::after { content: '+'; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 7px; font-weight: 900; color: rgba(0,0,0,0.22); line-height: 1; text-shadow: 0 0.5px 0 rgba(255,255,255,0.2); }

        .vm-dial-water {
            width: 190px; height: 190px; border-radius: 50%; flex-shrink: 0; position: relative; z-index: 1;
            background:
                radial-gradient(ellipse at 38% 25%, rgba(80,200,220,0.20) 0%, transparent 50%),
                radial-gradient(circle at 50% 50%, #1e8fa2 0%, #1a8496 12%, #157688 25%, #10687a 38%, #0c5a6c 52%, #094e60 65%, #074456 78%, #053a4c 90%, #043242 100%);
            border: 4.5px solid #053545;
            box-shadow: 0 0 0 1px rgba(60,180,210,0.25), inset 0 4px 10px rgba(80,200,230,0.12), inset 0 -6px 14px rgba(0,0,0,0.30), inset 4px 0 8px rgba(0,0,0,0.10), inset -4px 0 8px rgba(0,0,0,0.10), 0 10px 30px rgba(0,0,0,0.35), 0 3px 10px rgba(0,0,0,0.20);
        }
        .vm-dial-water::before { content: ''; position: absolute; top: 0; left: 10%; right: 10%; height: 40%; border-radius: 50%; background: radial-gradient(ellipse at 50% 0%, rgba(100,210,230,0.15) 0%, transparent 70%); pointer-events: none; z-index: 0; }
        .vm-dial-face {
            position: absolute; top: 9px; left: 9px; right: 9px; bottom: 9px; border-radius: 50%;
            background: radial-gradient(ellipse at 45% 35%, #ffffff 0%, #fefcf6 15%, #faf6ec 30%, #f4efe2 48%, #eee8d8 65%, #e6dfce 80%, #ddd6c4 100%);
            border: 6px solid transparent; background-clip: padding-box;
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1px; overflow: hidden;
            box-shadow: inset 0 3px 10px rgba(0,0,0,0.12), inset 0 -2px 6px rgba(0,0,0,0.06), inset 2px 0 4px rgba(0,0,0,0.04), inset -2px 0 4px rgba(0,0,0,0.04), 0 0 0 1px #6b5504, 0 0 0 2.5px #8c6d08, 0 0 0 4px #b8920e, 0 0 0 5.5px #d4aa18, 0 0 0 6.5px #e4bc28, 0 0 0 7.5px #d4aa18, 0 0 0 8.5px #b08010, 0 0 0 9px #8c6d08, 0 0 0 9.5px #6b5504;
        }
        .vm-dial-face::before { content: ''; position: absolute; top: -9px; left: -9px; right: -9px; bottom: -9px; border-radius: 50%; border: 8px solid transparent; background: linear-gradient(145deg, rgba(255,240,160,0.80) 0%, rgba(230,200,80,0.50) 15%, rgba(200,160,30,0.70) 30%, rgba(160,120,10,0.80) 45%, rgba(140,105,8,0.60) 55%, rgba(180,140,20,0.70) 65%, rgba(220,180,50,0.50) 78%, rgba(255,230,120,0.75) 88%, rgba(200,160,30,0.60) 100%); -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0); mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0); -webkit-mask-composite: xor; mask-composite: exclude; pointer-events: none; z-index: 5; }
        .vm-dial-face::after { content: ''; position: absolute; top: 2%; left: 6%; width: 65%; height: 40%; background: radial-gradient(ellipse at 40% 30%, rgba(255,255,255,0.55) 0%, rgba(255,255,255,0.30) 25%, rgba(255,255,255,0.10) 50%, transparent 70%); border-radius: 50%; pointer-events: none; z-index: 10; transform: rotate(-8deg); }
        .vm-dial-unit-top { font-size: 0.65rem; font-weight: 900; color: #1a1a1a; letter-spacing: 0.5px; margin-bottom: 2px; position: relative; z-index: 2; text-shadow: 0 0.5px 0 rgba(255,255,255,0.6); }
        .vm-dial-deco { font-size: 1rem; color: #555; margin: 1px 0; line-height: 1; opacity: 0.45; animation: waterMeterSpin 2.5s linear infinite; position: relative; z-index: 2; }
        @keyframes waterMeterSpin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .vm-dial-specs { font-size: 0.36rem; color: #888; letter-spacing: 0.3px; margin: 0; white-space: nowrap; position: relative; z-index: 2; }
        .vm-dial-label { font-size: 0.42rem; font-weight: 800; color: #555; letter-spacing: 1.5px; text-transform: uppercase; position: relative; z-index: 2; }
        .vm-sub-dial {
            position: absolute; bottom: 13%; right: 16%; width: 24px; height: 24px; border-radius: 50%; z-index: 3;
            background:
                conic-gradient(from 0deg, #ccc 0deg, #ccc 1deg, transparent 1deg, transparent 36deg, #ccc 36deg, #ccc 37deg, transparent 37deg, transparent 72deg, #ccc 72deg, #ccc 73deg, transparent 73deg, transparent 108deg, #ccc 108deg, #ccc 109deg, transparent 109deg, transparent 144deg, #ccc 144deg, #ccc 145deg, transparent 145deg, transparent 180deg, #ccc 180deg, #ccc 181deg, transparent 181deg, transparent 216deg, #ccc 216deg, #ccc 217deg, transparent 217deg, transparent 252deg, #ccc 252deg, #ccc 253deg, transparent 253deg, transparent 288deg, #ccc 288deg, #ccc 289deg, transparent 289deg, transparent 324deg, #ccc 324deg, #ccc 325deg, transparent 325deg, transparent 360deg),
                radial-gradient(circle at 45% 38%, #fff 0%, #fcf8f8 40%, #f5eaea 65%, #eedede 100%);
            border: 2px solid #c62828; box-shadow: inset 0 1.5px 4px rgba(0,0,0,0.15), inset 0 -1px 2px rgba(0,0,0,0.05), 0 1.5px 4px rgba(0,0,0,0.18);
        }
        .vm-sub-dial::before { content: ''; position: absolute; top: 50%; left: 50%; width: 8px; height: 1.5px; background: linear-gradient(90deg, #c62828 0%, #e53935 100%); transform-origin: 0 50%; transform: translate(0, -50%) rotate(-30deg); border-radius: 1px; animation: subDialSpin 8s linear infinite; box-shadow: 0 0.5px 1px rgba(0,0,0,0.3); }
        .vm-sub-dial::after { content: ''; position: absolute; top: 50%; left: 50%; width: 4px; height: 4px; background: radial-gradient(circle at 40% 35%, #f44336, #b71c1c); border-radius: 50%; transform: translate(-50%, -50%); box-shadow: inset 0 0.5px 1px rgba(255,255,255,0.4), 0 0.5px 1px rgba(0,0,0,0.3); }
        @keyframes subDialSpin { from { transform: translate(0, -50%) rotate(-30deg); } to { transform: translate(0, -50%) rotate(330deg); } }

        .vm-elec-body { display: flex; flex-direction: column; align-items: center; margin: 0 auto; max-width: 160px; }
        .vm-elec-frame { width: 140px; min-height: 160px; background: linear-gradient(160deg, #f8f8f8 0%, #ededed 25%, #e0e0e0 50%, #d5d5d5 80%, #ccc 100%); border: 2.5px solid #a0a0a0; border-radius: 14px 14px 8px 8px; position: relative; padding: 1rem 0.6rem 0.75rem; display: flex; flex-direction: column; align-items: center; gap: 5px; box-shadow: inset 0 1px 4px rgba(255,255,255,0.7), inset 0 -2px 5px rgba(0,0,0,0.06), 0 5px 18px rgba(0,0,0,0.2); overflow: hidden; }
        .vm-elec-frame::before { content: ''; position: absolute; top: 7px; left: 7px; right: 7px; bottom: 7px; border: 1.5px solid rgba(255,255,255,0.5); border-radius: 12px 12px 6px 6px; pointer-events: none; }
        .vm-elec-frame::after { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(135deg, rgba(255,255,255,0.15) 0%, transparent 50%, rgba(0,0,0,0.03) 100%); border-radius: inherit; pointer-events: none; }
        .vm-elec-screw { position: absolute; width: 10px; height: 10px; background: radial-gradient(circle at 35% 35%, #e8e8e8, #aaa 50%, #888); border-radius: 50%; border: 1px solid #888; box-shadow: inset 0 1px 1px rgba(255,255,255,0.5), 0 1px 2px rgba(0,0,0,0.3); z-index: 2; }
        .vm-elec-screw::after { content: ''; position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%) rotate(30deg); width: 5px; height: 1.2px; background: #666; }
        .vm-screw-tl { top: 5px; left: 5px; } .vm-screw-tr { top: 5px; right: 5px; } .vm-screw-bl { bottom: 5px; left: 5px; } .vm-screw-br { bottom: 5px; right: 5px; }
        .vm-elec-title { font-size: 0.44rem; font-weight: 700; color: #555; letter-spacing: 0.7px; text-transform: uppercase; margin-top: 2px; }
        .vm-elec-counter { display: flex; align-items: center; gap: 4px; background: linear-gradient(180deg, #1a1a1a 0%, #2d2d2d 100%); padding: 4px 6px; border-radius: 4px; border: 1.5px solid #444; box-shadow: inset 0 2px 5px rgba(0,0,0,0.6); }
        .vm-elec-kwh { font-size: 0.5rem; font-weight: 700; color: #ccc; letter-spacing: 0.5px; }
        .vm-elec-disc-area { width: 34px; height: 34px; border-radius: 50%; background: radial-gradient(circle, #1a1a1a 0%, #111 100%); border: 2px solid #555; display: flex; align-items: center; justify-content: center; box-shadow: inset 0 1px 4px rgba(0,0,0,0.6); }
        .vm-elec-disc { width: 20px; height: 20px; border-radius: 50%; background: conic-gradient(#d0d0d0 0deg, #888 90deg, #d0d0d0 180deg, #888 270deg, #d0d0d0 360deg); border: 1px solid #666; animation: elecSpin 3s linear infinite; }
        @keyframes elecSpin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .vm-elec-specs { font-size: 0.4rem; color: #999; letter-spacing: 0.5px; }
        .vm-elec-base { width: 105px; height: 18px; background: linear-gradient(180deg, #c8c8c8 0%, #adadad 30%, #999 70%, #888 100%); border-radius: 0 0 10px 10px; border: 1.5px solid #888; border-top: 1px solid #bbb; box-shadow: 0 4px 10px rgba(0,0,0,0.15); }

        .vm-digits { display: flex; gap: 0px; background: linear-gradient(180deg, #050505 0%, #111 8%, #1a1a1a 15%, #222 50%, #1a1a1a 85%, #111 92%, #050505 100%); padding: 3px 4px; border-radius: 4px; border: 1.5px solid #333; box-shadow: inset 0 3px 8px rgba(0,0,0,0.85), inset 0 -2px 6px rgba(0,0,0,0.60), inset 2px 0 4px rgba(0,0,0,0.40), inset -2px 0 4px rgba(0,0,0,0.40), 0 1px 3px rgba(0,0,0,0.2); position: relative; z-index: 2; }
        .vm-digits::before { content: ''; position: absolute; top: 0; left: 4px; right: 4px; height: 3px; background: linear-gradient(180deg, rgba(255,255,255,0.08) 0%, transparent 100%); border-radius: 4px 4px 0 0; pointer-events: none; z-index: 1; }
        .vm-digit { width: 18px; height: 24px; text-align: center; font-family: 'Courier New', 'Lucida Console', monospace; font-size: 0.85rem; font-weight: 900; border: none; padding: 0; display: flex; align-items: center; justify-content: center; background: linear-gradient(180deg, #999 0%, #bbb 3%, #d8d8d8 7%, #eee 14%, #f6f6f6 22%, #fafafa 35%, #fff 50%, #fafafa 65%, #f6f6f6 78%, #eee 86%, #d8d8d8 93%, #bbb 97%, #999 100%); color: #0a0a0a; text-shadow: 0 0.5px 0 rgba(255,255,255,0.5); border-radius: 2px; border-left: 0.5px solid rgba(0,0,0,0.10); border-right: 0.5px solid rgba(0,0,0,0.10); box-shadow: inset 0 3px 5px rgba(0,0,0,0.20), inset 0 -3px 5px rgba(0,0,0,0.15), inset 1px 0 2px rgba(0,0,0,0.10), inset -1px 0 2px rgba(0,0,0,0.10), 0 0 0 0.5px rgba(0,0,0,0.12); }
        .vm-digit.vm-digit-red { background: linear-gradient(180deg, #7f1d1d 0%, #991b1b 4%, #b91c1c 8%, #d42a2a 15%, #e53935 25%, #ef4444 38%, #f44840 50%, #ef4444 62%, #e53935 75%, #d42a2a 85%, #b91c1c 92%, #991b1b 96%, #7f1d1d 100%); color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.45); box-shadow: inset 0 3px 5px rgba(0,0,0,0.30), inset 0 -3px 5px rgba(0,0,0,0.20), inset 1px 0 2px rgba(0,0,0,0.15), inset -1px 0 2px rgba(0,0,0,0.15), 0 0 0 0.5px rgba(100,0,0,0.20); }

        @media (max-width: 980px) {
            .dash-meter-summary {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="reports-page <?php echo ($isLight ? 'live-light' : 'live-dark'); ?>">
    <script>
        // Ensure theme class is applied even if server-side didn't output it
        (function() {
            var c = '<?php echo ($isLight ? "live-light" : "live-dark"); ?>';
            if (c) {
                document.documentElement.classList.add(c);
                document.body.classList.add(c);
            }
        })();
    </script>
    <div class="app-shell">
        <?php echo "<!-- DEBUG: before include sidebar -->"; include __DIR__ . '/../includes/sidebar.php'; echo "<!-- DEBUG: after include sidebar -->"; ?>
        <main class="app-main">
            <div>
                <?php $pageTitle = 'แดชบอร์ด'; include __DIR__ . '/../includes/page_header.php'; ?>

            <?php if (!empty($_GET['google_success'])): ?>
            <div class="alert alert-success" style="background: linear-gradient(135deg, #10B981, #059669); color: white; padding: 16px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; animation: slideDown 0.3s ease;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <span><?php echo htmlspecialchars($_GET['google_success']); ?></span>
                <button onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: white; cursor: pointer; padding: 4px;">✕</button>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($_GET['google_error'])): ?>
            <div class="alert alert-error" style="background: linear-gradient(135deg, #EF4444, #DC2626); color: white; padding: 16px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; animation: slideDown 0.3s ease;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                <span><?php echo htmlspecialchars($_GET['google_error']); ?></span>
                <button onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: white; cursor: pointer; padding: 4px;">✕</button>
            </div>
            <?php endif; ?>

            <!-- Today's Activity Widget -->
            <div class="today-widget particle-wrapper">
                <div class="particle-container" data-particles="8"></div>
                <h3>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    กิจกรรมวันนี้ (<?php echo date('d M Y'); ?>)
                </h3>
                <div class="today-stats">
                    <div class="today-stat">
                        <div class="today-stat-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        </div>
                        <div class="today-stat-info">
                            <span class="label">จองห้องใหม่</span>
                            <span class="value"><?php echo $today_bookings; ?></span>
                        </div>
                    </div>
                    <div class="today-stat">
                        <div class="today-stat-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                        </div>
                        <div class="today-stat-info">
                            <span class="label">แจ้งซ่อมใหม่</span>
                            <span class="value"><?php echo $today_repairs; ?></span>
                        </div>
                    </div>
                    <div class="today-stat">
                        <div class="today-stat-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        </div>
                        <div class="today-stat-info">
                            <span class="label">รับชำระเงิน</span>
                            <span class="value">฿<?php echo number_format($today_payments, 0); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hero Stats with Occupancy Rate -->
            <div class="hero-stats">
                <div class="hero-card gradient-blue particle-wrapper dashboard-link-card" data-link="report_rooms.php">
                    <div class="particle-container" data-particles="5"></div>
                    <div class="hero-card-title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        อัตราการเข้าพัก
                    </div>
                    <div class="progress-ring-container">
                        <div class="progress-ring">
                            <svg width="100" height="100">
                                <circle class="progress-ring-bg" cx="50" cy="50" r="42"></circle>
                                <circle class="progress-ring-fill" cx="50" cy="50" r="42" 
                                    stroke="url(#blueGradient)" 
                                    stroke-dasharray="264" 
                                    stroke-dashoffset="<?php echo 264 - (264 * $occupancy_rate / 100); ?>"></circle>
                                <defs>
                                    <linearGradient id="blueGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#3b82f6"/>
                                        <stop offset="100%" stop-color="#1d4ed8"/>
                                    </linearGradient>
                                </defs>
                            </svg>
                            <span class="progress-ring-text"><?php echo $occupancy_rate; ?>%</span>
                        </div>
                        <div class="progress-info">
                            <h4><?php echo $room_occupied; ?> / <?php echo $total_rooms; ?> ห้อง</h4>
                            <p>ห้องว่าง <?php echo $room_available; ?> ห้อง</p>
                        </div>
                    </div>
                </div>
                
                <div class="hero-card gradient-green particle-wrapper dashboard-link-card" data-link="report_payments.php">
                    <div class="particle-container" data-particles="5"></div>
                    <div class="hero-card-title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        รายได้รวมทั้งหมด
                    </div>
                    <div class="hero-card-value">฿<?php echo number_format($total_revenue, 0); ?></div>
                    <div class="hero-card-trend trend-up">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                        จากค่าใช้จ่ายที่ชำระแล้ว
                    </div>
                </div>
                
                <div class="hero-card gradient-purple particle-wrapper dashboard-link-card" data-link="report_tenants.php">
                    <div class="particle-container" data-particles="5"></div>
                    <div class="hero-card-title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        ผู้เช่าทั้งหมด
                    </div>
                    <div class="hero-card-value"><?php echo $tenant_active; ?></div>
                    <div class="hero-card-trend trend-neutral">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        สัญญาที่ใช้งาน <?php echo $contract_active; ?> สัญญา
                    </div>
                </div>
                
                <div class="hero-card gradient-orange particle-wrapper dashboard-link-card" data-link="report_repairs.php">
                    <div class="particle-container" data-particles="5"></div>
                    <div class="hero-card-title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                        งานซ่อมที่รอ
                    </div>
                    <div class="hero-card-value"><?php echo $repair_waiting; ?></div>
                    <div class="hero-card-trend <?php echo $repair_waiting > 0 ? 'trend-down' : 'trend-up'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        ซ่อมเสร็จแล้ว <?php echo $repair_completed; ?> งาน
                    </div>
                </div>
            </div>

            <!-- สรุปข้อมูล Overview -->
            <div class="dashboard-grid">
                <div class="stat-card info particle-wrapper dashboard-link-card" data-link="report_tenants.php">
                    <div class="particle-container" data-particles="3"></div>
                    <div class="stat-icon blue">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <h3>ผู้เช่าทั้งหมด</h3>
                    <div class="number"><?php echo $tenant_active; ?></div>
                </div>
                <div class="stat-card success particle-wrapper dashboard-link-card" data-link="report_rooms.php">
                    <div class="particle-container" data-particles="3"></div>
                    <div class="stat-icon green">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    </div>
                    <h3>ห้องว่าง</h3>
                    <div class="number"><?php echo $room_available; ?></div>
                </div>
                <div class="stat-card danger particle-wrapper dashboard-link-card" data-link="report_rooms.php">
                    <div class="particle-container" data-particles="3"></div>
                    <div class="stat-icon red">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                    </div>
                    <h3>ห้องที่ใช้</h3>
                    <div class="number"><?php echo $room_occupied; ?></div>
                </div>
                <div class="stat-card warning particle-wrapper dashboard-link-card" data-link="report_tenants.php">
                    <div class="particle-container" data-particles="3"></div>
                    <div class="stat-icon orange">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    </div>
                    <h3>สัญญาที่ใช้งาน</h3>
                    <div class="number"><?php echo $contract_active; ?></div>
                </div>
                <div class="stat-card danger particle-wrapper dashboard-link-card" data-link="report_repairs.php">
                    <div class="particle-container" data-particles="3"></div>
                    <div class="stat-icon red">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                    </div>
                    <h3>การแจ้งซ่อมรอดำเนินการ</h3>
                    <div class="number"><?php echo $repair_waiting; ?></div>
                </div>
                <div class="stat-card info particle-wrapper dashboard-link-card" data-link="report_news.php">
                    <div class="particle-container" data-particles="3"></div>
                    <div class="stat-icon cyan">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8z"/></svg>
                    </div>
                    <h3>ข่าวประชาสัมพันธ์</h3>
                    <div class="number"><?php echo $news_count; ?></div>
                </div>
            </div>

            <!-- Charts - Main Row -->
            <div class="charts-row">
                <div class="chart-container particle-wrapper">
                    <div class="particle-container" data-particles="5"></div>
                    <h3><span class="section-icon green"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span>สถานะห้องพัก</h3>
                    <div class="chart-wrapper chart-sm">
                        <canvas id="roomStatusChart"></canvas>
                    </div>
                </div>

                <div class="chart-container particle-wrapper">
                    <div class="particle-container" data-particles="5"></div>
                    <h3><span class="section-icon orange"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></span>สถานะการแจ้งซ่อม</h3>
                    <div class="chart-wrapper chart-sm">
                        <canvas id="repairStatusChart"></canvas>
                    </div>
                </div>

                <div class="chart-container particle-wrapper">
                    <div class="particle-container" data-particles="5"></div>
                    <h3><span class="section-icon blue"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>สถานะการชำระเงิน</h3>
                    <div class="chart-wrapper chart-sm">
                        <canvas id="paymentStatusChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- รายได้รายเดือน -->
            <div class="chart-container particle-wrapper">
                <div class="particle-container" data-particles="8"></div>
                <h3><span class="section-icon purple"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span>รายได้รายเดือน</h3>
                <div class="chart-wrapper chart-lg">
                    <canvas id="monthlyRevenueChart"></canvas>
                </div>
            </div>

            <!-- Additional Analytics Row -->
            <div class="charts-row">
                <div class="chart-container particle-wrapper">
                    <div class="particle-container" data-particles="5"></div>
                    <h3><span class="section-icon indigo"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></span>Booking Trend</h3>
                    <div class="chart-wrapper chart-sm">
                        <canvas id="bookingTrendChart"></canvas>
                    </div>
                </div>

                <div class="chart-container particle-wrapper">
                    <div class="particle-container" data-particles="5"></div>
                    <h3><span class="section-icon teal"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z"/><line x1="12" y1="12" x2="12" y2="18"/><line x1="9" y1="15" x2="15" y2="15"/></svg></span>Contract Status</h3>
                    <div class="chart-wrapper chart-sm">
                        <canvas id="contractStatusChart"></canvas>
                    </div>
                </div>

                <div class="chart-container particle-wrapper">
                    <div class="particle-container" data-particles="5"></div>
                    <h3><span class="section-icon pink"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>Payment Trend</h3>
                    <div class="chart-wrapper chart-sm">
                        <canvas id="paymentTrendChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Two Column: Repair Distribution + Room Types -->
            <div class="charts-two-col">
                <div class="chart-container particle-wrapper">
                    <div class="particle-container" data-particles="5"></div>
                    <h3><span class="section-icon orange"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></span>Repair Status Distribution</h3>
                    <div class="chart-wrapper">
                        <canvas id="repairDistributionChart"></canvas>
                    </div>
                </div>
                <div class="chart-container particle-wrapper">
                    <div class="particle-container" data-particles="5"></div>
                    <h3><span class="section-icon rose"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></span>ประเภทห้องพัก</h3>
                    <div class="chart-wrapper">
                        <canvas id="roomTypesChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Utility Usage Trend -->
            <div class="chart-container particle-wrapper">
                <div class="particle-container" data-particles="8"></div>
                <h3><span class="section-icon sky"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 10h-4V4a2 2 0 0 0-4 0v6H6a2 2 0 0 0-2 2v8h16v-8a2 2 0 0 0-2-2z"/><path d="M8 20v-6"/><path d="M16 20v-6"/></svg></span>การใช้น้ำ-ไฟ (6 เดือนล่าสุด)</h3>
                <div class="chart-wrapper chart-lg">
                    <canvas id="utilityTrendChart"></canvas>
                </div>
            </div>

            <!-- Tenant Check-in Trend -->
            <div class="charts-row">
                <div class="chart-container">
                    <h3><span class="section-icon lime"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg></span>ผู้เช่าเข้าใหม่ (7 วันล่าสุด)</h3>
                    <div class="chart-wrapper">
                        <canvas id="tenantCheckinChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- รายงานรายละเอียด แบบเรียงตามความสำคัญ -->
            <div class="priority-group-title">ความสำคัญสูง <span>งานที่ควรตรวจทุกวัน</span></div>
            <div class="charts-row">
                <!-- ใบแจ้งชำระเงิน -->
                <div class="report-section" data-link="report_invoice.php">
                    <h3><span class="section-icon purple"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></span>ใบแจ้งชำระเงิน</h3>
                    <div class="report-flex">
                        <div class="mini-chart-container">
                            <canvas id="miniInvoiceChart"></canvas>
                        </div>
                        <div>
                            <div class="report-grid">
                                <div class="report-item">
                                    <label>รอตรวจสอบ</label>
                                    <div class="value" style="color: #f59e0b;"><?php echo $payment_pending; ?></div>
                                </div>
                                <div class="report-item">
                                    <label>ตรวจสอบแล้ว</label>
                                    <div class="value" style="color: #22c55e;"><?php echo $payment_verified; ?></div>
                                </div>
                            </div>
                            <div style="margin-top: 10px; text-align: center;">
                                <a href="report_invoice.php" style="color: #a855f7; text-decoration: none; font-size: 13px;">ดูรายละเอียด →</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- การแจ้งซ่อม -->
                <div class="report-section" data-link="report_repairs.php">
                    <h3><span class="section-icon orange"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></span>รายงานการแจ้งซ่อม</h3>
                    <div class="report-flex">
                        <div class="mini-chart-container">
                            <canvas id="miniRepairChart"></canvas>
                        </div>
                        <div>
                            <div class="report-grid">
                                <div class="report-item">
                                    <label>รอซ่อม</label>
                                    <div class="value" style="color: #ef4444;"><?php echo $repair_waiting; ?></div>
                                </div>
                                <div class="report-item">
                                    <label>กำลังซ่อม</label>
                                    <div class="value" style="color: #f59e0b;"><?php echo $repair_processing; ?></div>
                                </div>
                                <div class="report-item">
                                    <label>เสร็จแล้ว</label>
                                    <div class="value" style="color: #22c55e;"><?php echo $repair_completed; ?></div>
                                </div>
                            </div>
                            <div style="margin-top: 10px; text-align: center;">
                                <a href="report_repairs.php" style="color: #f59e0b; text-decoration: none; font-size: 13px;">ดูรายละเอียด →</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- การเข้าพัก -->
                <div class="report-section" data-link="report_reservations.php">
                    <h3><span class="section-icon blue"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>รายงานข้อมูลการเข้าพัก</h3>
                    <div class="report-flex">
                        <div class="mini-chart-container">
                            <canvas id="miniBookingChart"></canvas>
                        </div>
                        <div>
                            <div class="report-grid">
                                <div class="report-item">
                                    <label>เข้าพักแล้ว</label>
                                    <div class="value"><?php echo $booking_checkedin ?: '-'; ?></div>
                                </div>
                                <div class="report-item">
                                    <label>จองอยู่</label>
                                    <div class="value"><?php echo $booking_pending ?: '-'; ?></div>
                                </div>
                            </div>
                            <div style="margin-top: 10px; text-align: center;">
                                <a href="report_reservations.php" style="color: #3b82f6; text-decoration: none; font-size: 13px;">ดูรายละเอียด →</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="priority-group-title">ติดตามประจำ <span>การเงินและการใช้ทรัพยากร</span></div>
            <div class="charts-row">
                <!-- ยอดชำระเงิน -->
                <div class="report-section" data-link="report_payments.php">
                    <h3><span class="section-icon green"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>รายงานการชำระเงิน</h3>
                    <div class="report-flex">
                        <div class="mini-chart-container">
                            <canvas id="miniPaymentChart"></canvas>
                        </div>
                        <div>
                            <div class="report-grid">
                                <div class="report-item">
                                    <label>ยอดชำระทั้งหมด</label>
                                    <div class="value" style="color: #22c55e;">฿<?php echo number_format($total_payment, 0); ?></div>
                                </div>
                            </div>
                            <div style="margin-top: 10px; text-align: center;">
                                <a href="report_payments.php" style="color: #22c55e; text-decoration: none; font-size: 13px;">ดูรายละเอียด →</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- รายรับ -->
                <div class="report-section" data-link="report_payments.php">
                    <h3><span class="section-icon indigo"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></span>รายงานข้อมูลรายรับ</h3>
                    <div class="report-flex">
                        <div class="mini-chart-container">
                            <canvas id="miniRevenueChart"></canvas>
                        </div>
                        <div>
                            <div class="report-grid">
                                <div class="report-item">
                                    <label>รายรับทั้งหมด</label>
                                    <div class="value" style="color: #6366f1;">฿<?php echo number_format($total_revenue, 0); ?></div>
                                </div>
                            </div>
                            <div style="margin-top: 10px; text-align: center;">
                                <a href="report_payments.php" style="color: #6366f1; text-decoration: none; font-size: 13px;">ดูรายละเอียด →</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- น้ำ-ไฟ -->
                <div class="report-section" data-link="report_utility.php">
                    <h3><span class="section-icon teal"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 10h-4V4a2 2 0 0 0-4 0v6H6a2 2 0 0 0-2 2v8h16v-8a2 2 0 0 0-2-2z"/><path d="M8 20v-6"/><path d="M16 20v-6"/></svg></span>รายงานสรุปการใช้น้ำ-ไฟ</h3>
                    <div class="dash-meter-summary">
                        <div class="dash-meter-card water">
                            <div class="dash-meter-visual">
                                <div class="vm-water-body">
                                    <div class="vm-pipe-left"><div class="vm-pipe-flange"></div><div class="vm-pipe-bolt"></div></div>
                                    <div class="vm-dial-water">
                                        <div class="vm-dial-face">
                                            <div class="vm-dial-unit-top">m³</div>
                                            <div class="vm-digits">
                                                <?php for ($i = 0; $i < 7; $i++): ?>
                                                <div class="vm-digit <?php echo $i >= 5 ? 'vm-digit-red' : ''; ?>"><?php echo htmlspecialchars($water_digits[$i]); ?></div>
                                                <?php endfor; ?>
                                            </div>
                                            <div class="vm-dial-specs">20 mm · Qn 2.5 m³/hB</div>
                                            <div class="vm-dial-deco">✻</div>
                                            <div class="vm-dial-label">WATER METER</div>
                                            <div class="vm-sub-dial"></div>
                                        </div>
                                    </div>
                                    <div class="vm-pipe-right"><div class="vm-pipe-flange"></div><div class="vm-pipe-bolt"></div></div>
                                </div>
                            </div>
                            <div class="dash-meter-info">
                                <div class="dash-meter-label">มิเตอร์น้ำ</div>
                                <div class="dash-meter-stat">เดือนนี้รวม <?php echo number_format($cur_water_total); ?> หน่วย</div>
                                <?php $wd = $water_delta; $wc = $wd > 0 ? 'up' : ($wd < 0 ? 'down' : 'same'); $ws = $wd > 0 ? '▲' : '▼'; ?>
                                <span class="dash-meter-delta <?php echo $wc; ?>"><?php echo ($wd !== 0 ? $ws : '—') . ' ' . abs($wd); ?> vs เดือนก่อน</span>
                            </div>
                        </div>

                        <div class="dash-meter-card electric">
                            <div class="dash-meter-visual">
                                <div class="vm-elec-body">
                                    <div class="vm-elec-frame">
                                        <div class="vm-elec-screw vm-screw-tl"></div>
                                        <div class="vm-elec-screw vm-screw-tr"></div>
                                        <div class="vm-elec-title">KILOWATT-HOUR METER</div>
                                        <div class="vm-elec-counter">
                                            <div class="vm-digits">
                                                <?php for ($i = 0; $i < 5; $i++): ?>
                                                <div class="vm-digit <?php echo $i >= 4 ? 'vm-digit-red' : ''; ?>"><?php echo htmlspecialchars($elec_digits[$i]); ?></div>
                                                <?php endfor; ?>
                                            </div>
                                            <span class="vm-elec-kwh">kWh</span>
                                        </div>
                                        <div class="vm-elec-disc-area"><div class="vm-elec-disc"></div></div>
                                        <div class="vm-elec-specs">220V 50Hz</div>
                                        <div class="vm-elec-screw vm-screw-bl"></div>
                                        <div class="vm-elec-screw vm-screw-br"></div>
                                    </div>
                                    <div class="vm-elec-base"></div>
                                </div>
                            </div>
                            <div class="dash-meter-info">
                                <div class="dash-meter-label">มิเตอร์ไฟ</div>
                                <div class="dash-meter-stat">เดือนนี้รวม <?php echo number_format($cur_elec_total); ?> หน่วย</div>
                                <?php $ed = $elec_delta; $ec = $ed > 0 ? 'up' : ($ed < 0 ? 'down' : 'same'); $es = $ed > 0 ? '▲' : '▼'; ?>
                                <span class="dash-meter-delta <?php echo $ec; ?>"><?php echo ($ed !== 0 ? $es : '—') . ' ' . abs($ed); ?> vs เดือนก่อน</span>
                            </div>
                        </div>
                    </div>
                    <div style="margin-top: 8px; text-align: center;">
                        <a href="report_utility.php" class="dash-meter-link">ดูรายละเอียด →</a>
                    </div>
                </div>
            </div>

            <div class="priority-group-title">ข้อมูลอ้างอิง <span>ดูภาพรวมและเอกสารประกอบ</span></div>
            <div class="charts-row">
                <!-- ห้องพัก -->
                <div class="report-section" data-link="report_rooms.php">
                    <h3><span class="section-icon blue"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>รายงานข้อมูลห้องพัก</h3>
                    <div class="report-flex">
                        <div class="mini-chart-container">
                            <canvas id="miniRoomChart"></canvas>
                        </div>
                        <div>
                            <div class="report-grid">
                                <div class="report-item">
                                    <label>ห้องว่าง</label>
                                    <div class="value" style="color: #22c55e;"><?php echo $room_available; ?></div>
                                </div>
                                <div class="report-item">
                                    <label>ห้องไม่ว่าง</label>
                                    <div class="value" style="color: #ef4444;"><?php echo $room_occupied; ?></div>
                                </div>
                            </div>
                            <div style="margin-top: 10px; text-align: center;">
                                <a href="report_rooms.php" style="color: #3b82f6; text-decoration: none; font-size: 13px;">ดูรายละเอียด →</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- สัญญา -->
                <div class="report-section" data-link="print_contract.php">
                    <h3><span class="section-icon pink"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg></span>พิมพ์สัญญา</h3>
                    <div class="report-flex">
                        <div class="mini-chart-container">
                            <canvas id="miniContractChart"></canvas>
                        </div>
                        <div>
                            <div class="report-grid">
                                <div class="report-item">
                                    <label>สัญญาที่ใช้</label>
                                    <div class="value" style="color: #ec4899;"><?php echo $contract_active; ?></div>
                                </div>
                                <div class="report-item">
                                    <label>สิ้นสุดแล้ว</label>
                                    <div class="value" style="color: #6b7280;"><?php echo $contract_cancelled; ?></div>
                                </div>
                            </div>
                            <div style="margin-top: 10px; text-align: center;">
                                <a href="print_contract.php" style="color: #ec4899; text-decoration: none; font-size: 13px;">พิมพ์สัญญา →</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ข่าวประชาสัมพันธ์ -->
                <div class="report-section" data-link="report_news.php">
                    <h3><span class="section-icon cyan"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8z"/></svg></span>รายงานข้อมูลข่าวประชาสัมพันธ์</h3>
                    <div class="report-flex">
                        <div class="mini-chart-container">
                            <canvas id="miniNewsChart"></canvas>
                        </div>
                        <div>
                            <div class="report-grid">
                                <div class="report-item">
                                    <label>ข่าวทั้งหมด</label>
                                    <div class="value"><?php echo $news_count; ?></div>
                                </div>
                            </div>
                            <div style="margin-top: 10px; text-align: center;">
                                <a href="report_news.php" style="color: #06b6d4; text-decoration: none; font-size: 13px;">ดูรายละเอียด →</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            </div>
        </main>
    </div>

    <script src="/dormitory_management/Public/Assets/Javascript/main.js" defer></script>
    <script src="/dormitory_management/Public/Assets/Javascript/animate-ui.js" defer></script>

    <script>
        // ทำให้การ์ดรายงานคลิกได้ทั้งใบ
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.report-section[data-link], .dashboard-link-card[data-link]').forEach(function(card) {
                card.setAttribute('role', 'link');
                card.setAttribute('tabindex', '0');

                var navigate = function() {
                    var url = card.getAttribute('data-link');
                    if (url) window.location.href = url;
                };

                card.addEventListener('click', function(e) {
                    var interactive = e.target.closest('a, button, input, select, textarea, label');
                    if (interactive) return;
                    navigate();
                });

                card.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        navigate();
                    }
                });
            });
        });

        function initDashboardCharts() {
        // สีสำหรับ Charts
        const colors = {
            primary: 'rgba(0, 123, 255, 0.7)',
            primaryBorder: 'rgb(0, 123, 255)',
            success: 'rgba(40, 167, 69, 0.7)',
            successBorder: 'rgb(40, 167, 69)',
            danger: 'rgba(220, 53, 69, 0.7)',
            dangerBorder: 'rgb(220, 53, 69)',
            warning: 'rgba(255, 193, 7, 0.7)',
            warningBorder: 'rgb(255, 193, 7)',
            info: 'rgba(23, 162, 184, 0.7)',
            infoBorder: 'rgb(23, 162, 184)'
        };

        // Chart: สถานะห้องพัก
        const roomStatusCtx = document.getElementById('roomStatusChart').getContext('2d');
        new Chart(roomStatusCtx, {
            type: 'doughnut',
            data: {
                labels: ['ว่าง', 'ไม่ว่าง'],
                datasets: [{
                    data: [<?php echo $room_available; ?>, <?php echo $room_occupied; ?>],
                    backgroundColor: [colors.success, colors.danger],
                    borderColor: [colors.successBorder, colors.dangerBorder],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 14 } }
                    }
                }
            }
        });

        // Chart: สถานะการแจ้งซ่อม
        const repairStatusCtx = document.getElementById('repairStatusChart').getContext('2d');
        new Chart(repairStatusCtx, {
            type: 'doughnut',
            data: {
                labels: ['รอซ่อม', 'กำลังซ่อม', 'ซ่อมเสร็จ'],
                datasets: [{
                    data: [<?php echo $repair_waiting; ?>, <?php echo $repair_processing; ?>, <?php echo $repair_completed; ?>],
                    backgroundColor: [colors.danger, colors.warning, colors.success],
                    borderColor: [colors.dangerBorder, colors.warningBorder, colors.successBorder],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 14 } }
                    }
                }
            }
        });

        // Chart: สถานะการชำระเงิน
        const paymentStatusCtx = document.getElementById('paymentStatusChart').getContext('2d');
        new Chart(paymentStatusCtx, {
            type: 'doughnut',
            data: {
                labels: ['รอตรวจสอบ', 'ตรวจสอบแล้ว'],
                datasets: [{
                    data: [<?php echo $payment_pending; ?>, <?php echo $payment_verified; ?>],
                    backgroundColor: [colors.warning, colors.success],
                    borderColor: [colors.warningBorder, colors.successBorder],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 14 } }
                    }
                }
            }
        });

        // Chart: รายได้รายเดือน
        const monthlyRevenueCtx = document.getElementById('monthlyRevenueChart').getContext('2d');
        new Chart(monthlyRevenueCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php 
                    foreach ($monthly_revenue as $data) {
                        echo "'" . thaiMonthYear($data['month']) . "',";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'รายได้ (บาท)',
                    data: [
                        <?php 
                        foreach ($monthly_revenue as $data) {
                            echo $data['total'] . ",";
                        }
                        ?>
                    ],
                    borderColor: colors.primaryBorder,
                    backgroundColor: colors.primary,
                    borderWidth: 3,
                    tension: 0.3,
                    fill: true,
                    pointRadius: 5,
                    pointBackgroundColor: colors.primaryBorder,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { font: { size: 14 } }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '฿' + value.toLocaleString('th-TH');
                            }
                        }
                    }
                }
            }
        });

        // Chart: Booking Trend (7 days)
        const bookingTrendCtx = document.getElementById('bookingTrendChart').getContext('2d');
        new Chart(bookingTrendCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php 
                    for ($i = 6; $i >= 0; $i--) {
                        $date = new DateTime("-$i days");
                        echo "'" . thaiDate($date->format('Y-m-d'), 'chart_day_month') . "',";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Bookings',
                    data: [
                        <?php 
                        $dates_map = [];
                        foreach ($booking_trend as $item) {
                            $dates_map[$item['date']] = $item['count'];
                        }
                        for ($i = 6; $i >= 0; $i--) {
                            $date = (new DateTime("-$i days"))->format('Y-m-d');
                            echo (isset($dates_map[$date]) ? $dates_map[$date] : 0) . ",";
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(99, 102, 241, 0.7)',
                    borderColor: 'rgb(99, 102, 241)',
                    borderWidth: 2,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Chart: Contract Status
        const contractStatusCtx = document.getElementById('contractStatusChart').getContext('2d');
        new Chart(contractStatusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Ended', 'Pending Cancel'],
                datasets: [{
                    data: [
                        <?php echo isset($contract_distribution['active']) ? $contract_distribution['active'] : 0; ?>,
                        <?php echo isset($contract_distribution['ended']) ? $contract_distribution['ended'] : 0; ?>,
                        <?php echo isset($contract_distribution['pending_cancel']) ? $contract_distribution['pending_cancel'] : 0; ?>
                    ],
                    backgroundColor: [colors.success, colors.danger, colors.warning],
                    borderColor: [colors.successBorder, colors.dangerBorder, colors.warningBorder],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 12 } }
                    }
                }
            }
        });

        // Chart: Payment Trend
        const paymentTrendCtx = document.getElementById('paymentTrendChart').getContext('2d');
        new Chart(paymentTrendCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php 
                    for ($i = 6; $i >= 0; $i--) {
                        $date = new DateTime("-$i days");
                        echo "'" . thaiDate($date->format('Y-m-d'), 'chart_day') . "',";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Payments (฿)',
                    data: [
                        <?php 
                        $payment_map = [];
                        foreach ($payment_trend as $item) {
                            $payment_map[$item['date']] = $item['total'];
                        }
                        for ($i = 6; $i >= 0; $i--) {
                            $date = (new DateTime("-$i days"))->format('Y-m-d');
                            echo (isset($payment_map[$date]) ? $payment_map[$date] : 0) . ",";
                        }
                        ?>
                    ],
                    borderColor: colors.primaryBorder,
                    backgroundColor: 'rgba(217, 70, 239, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: colors.primaryBorder,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '฿' + (value / 1000).toFixed(1) + 'k';
                            }
                        }
                    }
                }
            }
        });

        // Chart: Repair Status Distribution
        const repairDistributionCtx = document.getElementById('repairDistributionChart').getContext('2d');
        new Chart(repairDistributionCtx, {
            type: 'bar',
            data: {
                labels: ['Waiting', 'Processing', 'Completed'],
                datasets: [{
                    label: 'Repairs',
                    data: [
                        <?php echo isset($repair_status_dist['0']) ? $repair_status_dist['0'] : 0; ?>,
                        <?php echo isset($repair_status_dist['1']) ? $repair_status_dist['1'] : 0; ?>,
                        <?php echo isset($repair_status_dist['2']) ? $repair_status_dist['2'] : 0; ?>
                    ],
                    backgroundColor: [
                        'rgba(239, 68, 68, 0.7)',
                        'rgba(251, 146, 60, 0.7)',
                        'rgba(34, 197, 94, 0.7)'
                    ],
                    borderColor: [
                        'rgb(239, 68, 68)',
                        'rgb(251, 146, 60)',
                        'rgb(34, 197, 94)'
                    ],
                    borderWidth: 2,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Chart: Room Types Distribution
        const roomTypesCtx = document.getElementById('roomTypesChart').getContext('2d');
        new Chart(roomTypesCtx, {
            type: 'polarArea',
            data: {
                labels: [
                    <?php foreach ($room_types as $type) { echo "'" . addslashes($type['type_name']) . "',"; } ?>
                ],
                datasets: [{
                    data: [
                        <?php foreach ($room_types as $type) { echo $type['count'] . ","; } ?>
                    ],
                    backgroundColor: [
                        'rgba(244, 63, 94, 0.7)',
                        'rgba(168, 85, 247, 0.7)',
                        'rgba(59, 130, 246, 0.7)',
                        'rgba(20, 184, 166, 0.7)',
                        'rgba(132, 204, 22, 0.7)',
                        'rgba(251, 146, 60, 0.7)'
                    ],
                    borderColor: [
                        'rgb(244, 63, 94)',
                        'rgb(168, 85, 247)',
                        'rgb(59, 130, 246)',
                        'rgb(20, 184, 166)',
                        'rgb(132, 204, 22)',
                        'rgb(251, 146, 60)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { font: { size: 11 }, padding: 12 }
                    }
                }
            }
        });

        // Chart: Utility Usage Trend
        const utilityTrendCtx = document.getElementById('utilityTrendChart').getContext('2d');
        new Chart(utilityTrendCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php foreach ($utility_trend as $data) {
                        echo "'" . thaiMonthYear($data['month']) . "',";
                    } ?>
                ],
                datasets: [
                    {
                        label: 'น้ำ (ยูนิต)',
                        data: [<?php foreach ($utility_trend as $data) { echo round($data['avg_water'], 1) . ","; } ?>],
                        borderColor: 'rgb(14, 165, 233)',
                        backgroundColor: 'rgba(14, 165, 233, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 5,
                        pointBackgroundColor: 'rgb(14, 165, 233)',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    },
                    {
                        label: 'ไฟ (ยูนิต)',
                        data: [<?php foreach ($utility_trend as $data) { echo round($data['avg_elec'], 1) . ","; } ?>],
                        borderColor: 'rgb(245, 158, 11)',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 5,
                        pointBackgroundColor: 'rgb(245, 158, 11)',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { font: { size: 12 }, usePointStyle: true }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    },
                    x: {
                        grid: { display: false }
                    }
                },
                interaction: {
                    mode: 'index',
                    intersect: false
                }
            }
        });

        // Chart: Tenant Check-in Trend
        const tenantCheckinCtx = document.getElementById('tenantCheckinChart').getContext('2d');
        new Chart(tenantCheckinCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php 
                    for ($i = 6; $i >= 0; $i--) {
                        $date = new DateTime("-$i days");
                        echo "'" . thaiDate($date->format('Y-m-d'), 'chart_day') . "',";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'ผู้เช่าเข้าใหม่',
                    data: [
                        <?php 
                        $checkin_map = [];
                        foreach ($tenant_checkin_trend as $item) {
                            $checkin_map[$item['date']] = $item['count'];
                        }
                        for ($i = 6; $i >= 0; $i--) {
                            $date = (new DateTime("-$i days"))->format('Y-m-d');
                            echo (isset($checkin_map[$date]) ? $checkin_map[$date] : 0) . ",";
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(132, 204, 22, 0.7)',
                    borderColor: 'rgb(132, 204, 22)',
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });

        // ===== MINI CHARTS FOR REPORT SECTIONS =====
        
        // Mini Chart: Booking Status
        const miniBookingCtx = document.getElementById('miniBookingChart').getContext('2d');
        new Chart(miniBookingCtx, {
            type: 'doughnut',
            data: {
                labels: ['เข้าพักแล้ว', 'จองอยู่'],
                datasets: [{
                    data: [<?php echo $booking_checkedin; ?>, <?php echo $booking_pending; ?>],
                    backgroundColor: ['rgba(59, 130, 246, 0.8)', 'rgba(147, 197, 253, 0.8)'],
                    borderColor: ['rgb(59, 130, 246)', 'rgb(147, 197, 253)'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: { legend: { display: false } }
            }
        });

        // Mini Chart: News (Bar showing count)
        const miniNewsCtx = document.getElementById('miniNewsChart').getContext('2d');
        new Chart(miniNewsCtx, {
            type: 'bar',
            data: {
                labels: ['ข่าว'],
                datasets: [{
                    data: [<?php echo $news_count; ?>],
                    backgroundColor: 'rgba(6, 182, 212, 0.7)',
                    borderColor: 'rgb(6, 182, 212)',
                    borderWidth: 2,
                    borderRadius: 8,
                    barThickness: 40
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: { x: { display: false }, y: { display: false } }
            }
        });

        // Mini Chart: Repair Status
        const miniRepairCtx = document.getElementById('miniRepairChart').getContext('2d');
        new Chart(miniRepairCtx, {
            type: 'doughnut',
            data: {
                labels: ['รอซ่อม', 'กำลังซ่อม', 'เสร็จแล้ว'],
                datasets: [{
                    data: [<?php echo $repair_waiting; ?>, <?php echo $repair_processing; ?>, <?php echo $repair_completed; ?>],
                    backgroundColor: ['rgba(239, 68, 68, 0.8)', 'rgba(245, 158, 11, 0.8)', 'rgba(34, 197, 94, 0.8)'],
                    borderColor: ['rgb(239, 68, 68)', 'rgb(245, 158, 11)', 'rgb(34, 197, 94)'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: { legend: { display: false } }
            }
        });

        // Mini Chart: Invoice Status
        const miniInvoiceCtx = document.getElementById('miniInvoiceChart').getContext('2d');
        new Chart(miniInvoiceCtx, {
            type: 'doughnut',
            data: {
                labels: ['รอตรวจสอบ', 'ตรวจสอบแล้ว'],
                datasets: [{
                    data: [<?php echo $payment_pending; ?>, <?php echo $payment_verified; ?>],
                    backgroundColor: ['rgba(245, 158, 11, 0.8)', 'rgba(34, 197, 94, 0.8)'],
                    borderColor: ['rgb(245, 158, 11)', 'rgb(34, 197, 94)'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: { legend: { display: false } }
            }
        });

        // Mini Chart: Payment Amount (Gauge-like)
        const miniPaymentCtx = document.getElementById('miniPaymentChart').getContext('2d');
        new Chart(miniPaymentCtx, {
            type: 'doughnut',
            data: {
                labels: ['ยอดชำระ', ''],
                datasets: [{
                    data: [<?php echo $total_payment; ?>, <?php echo max(10000 - $total_payment, 0); ?>],
                    backgroundColor: ['rgba(34, 197, 94, 0.8)', 'rgba(229, 231, 235, 0.3)'],
                    borderColor: ['rgb(34, 197, 94)', 'transparent'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                rotation: -90,
                circumference: 180,
                plugins: { legend: { display: false } }
            }
        });

        // Mini Chart: Room Status
        const miniRoomCtx = document.getElementById('miniRoomChart').getContext('2d');
        new Chart(miniRoomCtx, {
            type: 'doughnut',
            data: {
                labels: ['ว่าง', 'ไม่ว่าง'],
                datasets: [{
                    data: [<?php echo $room_available; ?>, <?php echo $room_occupied; ?>],
                    backgroundColor: ['rgba(34, 197, 94, 0.8)', 'rgba(239, 68, 68, 0.8)'],
                    borderColor: ['rgb(34, 197, 94)', 'rgb(239, 68, 68)'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: { legend: { display: false } }
            }
        });

        // Mini Chart: Utility Comparison
        const miniUtilityCtx = document.getElementById('miniUtilityChart').getContext('2d');
        new Chart(miniUtilityCtx, {
            type: 'bar',
            data: {
                labels: ['น้ำ', 'ไฟ'],
                datasets: [{
                    data: [<?php echo $avg_water; ?>, <?php echo $avg_elec; ?>],
                    backgroundColor: ['rgba(14, 165, 233, 0.8)', 'rgba(245, 158, 11, 0.8)'],
                    borderColor: ['rgb(14, 165, 233)', 'rgb(245, 158, 11)'],
                    borderWidth: 2,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { 
                    y: { display: false, beginAtZero: true }, 
                    x: { grid: { display: false } } 
                }
            }
        });

        // Mini Chart: Revenue (Bar chart - works better with few data points)
        const miniRevenueCtx = document.getElementById('miniRevenueChart');
        if (miniRevenueCtx) {
            const revenueLabelsRaw = <?php echo json_encode($miniRevenueLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            const revenueDataRaw = <?php echo json_encode($miniRevenueData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            const revenueLabels = revenueLabelsRaw.length ? revenueLabelsRaw : ['ไม่มีข้อมูล'];
            const revenueData = revenueDataRaw.length ? revenueDataRaw : [0];
            
            new Chart(miniRevenueCtx.getContext('2d'), {
                type: revenueData.length <= 2 ? 'bar' : 'line',
                data: {
                    labels: revenueLabels,
                    datasets: [{
                        data: revenueData,
                        borderColor: 'rgb(99, 102, 241)',
                        backgroundColor: revenueData.length <= 2 ? 'rgba(99, 102, 241, 0.7)' : 'rgba(99, 102, 241, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointRadius: revenueData.length <= 3 ? 4 : 0,
                        pointBackgroundColor: 'rgb(99, 102, 241)',
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { 
                        y: { display: false, beginAtZero: true }, 
                        x: { 
                            display: revenueData.length <= 3,
                            grid: { display: false },
                            ticks: { 
                                font: { size: 10 },
                                color: 'rgba(148, 163, 184, 0.8)'
                            }
                        } 
                    }
                }
            });
        }

        // Mini Chart: Contract Status
        const miniContractCtx = document.getElementById('miniContractChart').getContext('2d');
        new Chart(miniContractCtx, {
            type: 'doughnut',
            data: {
                labels: ['ใช้งาน', 'สิ้นสุด'],
                datasets: [{
                    data: [<?php echo $contract_active; ?>, <?php echo $contract_cancelled; ?>],
                    backgroundColor: ['rgba(236, 72, 153, 0.8)', 'rgba(156, 163, 175, 0.5)'],
                    borderColor: ['rgb(236, 72, 153)', 'rgb(156, 163, 175)'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: { legend: { display: false } }
            }
        });
        }

        (function bootstrapDashboardCharts() {
            if (typeof window.Chart !== 'undefined') {
                initDashboardCharts();
                return;
            }

            var fallbackScript = document.createElement('script');
            fallbackScript.src = 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js';
            fallbackScript.onload = initDashboardCharts;
            fallbackScript.onerror = function() {
                console.error('[Dashboard] Unable to load Chart.js from CDN fallback');
            };
            document.head.appendChild(fallbackScript);
        })();
    </script>
</body>
</html>
