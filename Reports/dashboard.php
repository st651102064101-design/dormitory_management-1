<?php
session_start();
require_once __DIR__ . '/../ConnectDB.php';
require_once __DIR__ . '/../includes/lang.php';
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
    
    // 1. __('occupancy_report')
    // bkg_status: 0=รอยืนยัน, 1=__('booked')/รอ__('occupied'), 2=__('occupied'), 3=__('cancelled')
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM booking WHERE bkg_status = 2");
    $booking_checkedin = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM booking b WHERE COALESCE(b.bkg_status, '0') = '1' AND NOT EXISTS (SELECT 1 FROM contract c WHERE c.room_id = b.room_id AND c.tnt_id = b.tnt_id) AND NOT EXISTS (SELECT 1 FROM contract c WHERE c.room_id = b.room_id AND c.ctr_status = '0' AND (c.ctr_end IS NULL OR c.ctr_end >= CURDATE()))");
    $booking_pending = $stmt->fetch()['total'] ?? 0;
    
    // 2. __('data_report')__('news_announcements')
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM news");
    $news_count = $stmt->fetch()['total'] ?? 0;
    
    // 3. __('repair_report')อุปกรณ์
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM repair WHERE repair_status = 0");
    $repair_waiting = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM repair WHERE repair_status = 1");
    $repair_processing = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM repair WHERE repair_status = 2");
    $repair_completed = $stmt->fetch()['total'] ?? 0;
    
    // 4. __('invoices')
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM payment WHERE pay_status = 0");
    $payment_pending = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM payment WHERE pay_status = 1");
    $payment_verified = $stmt->fetch()['total'] ?? 0;
    
    // 5. __('payment_report') (รวมเฉพาะการชำระที่__('verified')/ยืนยันแล้ว)
    $stmt = $pdo->query("SELECT SUM(pay_amount) as total FROM payment WHERE pay_status = '1'");
    $total_payment = $stmt->fetch()['total'] ?? 0;
    
    // ซ่อมแซมข้อมูลห้องให้ถูกต้องแบบอัตโนมัติเมื่อดู Dashboard
    $pdo->exec("
        UPDATE booking b
        JOIN contract c ON b.tnt_id = c.tnt_id AND b.room_id = c.room_id
        SET b.bkg_status = '2'
        WHERE b.bkg_status = '1'
    ");
    $pdo->exec("UPDATE room SET room_status = '0'");
    $pdo->exec("UPDATE room SET room_status = '1' WHERE EXISTS (
        SELECT 1 FROM contract c WHERE c.room_id = room.room_id AND c.ctr_status = '0'
    ) OR EXISTS (
        SELECT 1 FROM booking b WHERE b.room_id = room.room_id AND b.bkg_status = '1'
    )");

    // 6. __('room_info_report')
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM room WHERE room_status = 0");
    $room_available = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM room WHERE room_status = 1");
    $room_occupied = $stmt->fetch()['total'] ?? 0;
    
    // 7. __('utility_summary_report') (ไม่รวมเดือนที่ใช้งาน 0 __('units'))
    $stmt = $pdo->query("SELECT AVG(utl_water_end - utl_water_start) as avg_water, AVG(utl_elec_end - utl_elec_start) as avg_elec FROM utility WHERE (utl_water_end - utl_water_start) > 0 OR (utl_elec_end - utl_elec_start) > 0");
    $utility_avg = $stmt->fetch() ?? ['avg_water' => 0, 'avg_elec' => 0];
    $avg_water = round($utility_avg['avg_water'] ?? 0, 2);
    $avg_elec = round($utility_avg['avg_elec'] ?? 0, 2);
    
    // 8. __('income_report') (ใช้ค่าเดียวกับ total_payment — เฉพาะรายการที่__('verified'))
    $total_revenue = $total_payment;
    
    // 9. ข้อมูลสัญญา
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM contract WHERE ctr_status = 0");
    $contract_active = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM contract WHERE ctr_status = 1");
    $contract_cancelled = $stmt->fetch()['total'] ?? 0;
    
    // ดึงข้อมูล Tenant (นับเฉพาะที่มี__('active_contracts')งานอยู่)
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
    } catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }
    
        // ข้อมูล__('monthly_revenue') (จากการชำระของผู้เช่า)
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
    
    // ข้อมูล Payment trend (7 วันล่าสุด — เฉพาะที่__('verified'))
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
    
    // occupancy_rate
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
    
    // Today's Stats - __('new_bookings')วันนี้
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
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - <?php echo __('dashboard'); ?></title>
    <?php include __DIR__ . '/../includes/sidebar_toggle.php'; ?>
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/animate-ui.css">
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="/dormitory_management/Public/Assets/Javascript/chart.umd.min.js"></script>
    <script>
      // บังคับให้หน้า Dashboard ย่อ Sidebar ทุกครั้งที่เริ่มเข้า
      try { localStorage.setItem('sidebarCollapsed', 'true'); } catch(e){}

      document.addEventListener('DOMContentLoaded', function() {
        if (typeof window.__initSidebarState === 'function' && !window.__sidebarStateInitialized) {
          window.__sidebarStateInitialized = true;
          window.__initSidebarState();
        }
      });
    </script>
    <style>
        :root {
            --dash-bg-a: rgba(99, 102, 241, 0.14);
            --dash-bg-b: rgba(14, 165, 233, 0.1);
            --dash-bg-c: rgba(16, 185, 129, 0.1);
            --dash-edge: rgba(226, 232, 240, 0.92);
            --dash-shadow: 0 8px 24px -14px rgba(15, 23, 42, 0.38);
        }

        body {
            font-family: 'Prompt', sans-serif;
            margin: 0;
            padding: 0;
            background:
                radial-gradient(75rem 45rem at -12% -22%, var(--dash-bg-a) 0%, rgba(99, 102, 241, 0) 65%),
                radial-gradient(56rem 36rem at 110% 10%, var(--dash-bg-b) 0%, rgba(14, 165, 233, 0) 70%),
                radial-gradient(46rem 32rem at 82% 88%, var(--dash-bg-c) 0%, rgba(16, 185, 129, 0) 68%),
                #f8fafc;
        }

        .app-main {
            background: transparent !important;
            position: relative;
            isolation: isolate;
        }

        .app-main::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            background-image:
                linear-gradient(rgba(148, 163, 184, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(148, 163, 184, 0.05) 1px, transparent 1px);
            background-size: 22px 22px;
            mask-image: radial-gradient(circle at 45% 25%, black 20%, transparent 80%);
            z-index: -2;
        }

        .dashboard-shell {
            position: relative;
        }

        .dashboard-shell::before,
        .dashboard-shell::after {
            content: "";
            position: absolute;
            filter: blur(56px);
            border-radius: 999px;
            pointer-events: none;
            z-index: -1;
            opacity: 0.45;
        }

        .dashboard-shell::before {
            width: 220px;
            height: 220px;
            background: rgba(99, 102, 241, 0.28);
            top: -70px;
            left: -80px;
        }

        .dashboard-shell::after {
            width: 260px;
            height: 260px;
            background: rgba(16, 185, 129, 0.24);
            right: -90px;
            bottom: -120px;
        }

        .reveal-item {
            opacity: 0;
            transform: translateY(14px);
            animation: dash-reveal 0.72s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }

        .reveal-item.delay-1 { animation-delay: 0.06s; }
        .reveal-item.delay-2 { animation-delay: 0.13s; }
        .reveal-item.delay-3 { animation-delay: 0.2s; }

        @keyframes dash-reveal {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .saas-card {
            position: relative;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.92);
            border-radius: 1rem;
            border: 1px solid var(--dash-edge);
            box-shadow: var(--dash-shadow);
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
            cursor: pointer;
            backdrop-filter: blur(6px);
        }

        .saas-card::after {
            content: "";
            position: absolute;
            top: -130%;
            left: -45%;
            width: 36%;
            height: 360%;
            transform: rotate(18deg) translateX(-220%);
            background: linear-gradient(180deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.68), rgba(255, 255, 255, 0));
            transition: transform 0.85s ease;
            pointer-events: none;
        }

        .saas-card:hover {
            transform: translateY(-4px);
            border-color: rgba(129, 140, 248, 0.4);
            box-shadow: 0 20px 34px -24px rgba(30, 41, 59, 0.5);
        }

        .saas-card:hover::after {
            transform: rotate(18deg) translateX(350%);
        }

        .saas-card.no-hover:hover {
            transform: none;
            box-shadow: var(--dash-shadow);
            border-color: var(--dash-edge);
            cursor: default;
        }

        .saas-card.no-hover:hover::after {
            transform: rotate(18deg) translateX(-220%);
        }

        .kpi-card::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 4px;
            border-radius: 10px 0 0 10px;
            opacity: 0.85;
            background: linear-gradient(180deg, #6366f1, #4f46e5);
        }

        .kpi-card.kpi-occupancy::before { background: linear-gradient(180deg, #0ea5e9, #0284c7); }
        .kpi-card.kpi-tenants::before { background: linear-gradient(180deg, #a855f7, #7e22ce); }
        .kpi-card.kpi-repairs::before { background: linear-gradient(180deg, #f59e0b, #d97706); }

        .status-date-pill {
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(4px);
            box-shadow: 0 10px 24px -18px rgba(30, 41, 59, 0.62);
        }

        .status-date-pill::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(110deg, rgba(255, 255, 255, 0) 20%, rgba(255, 255, 255, 0.55) 50%, rgba(255, 255, 255, 0) 80%);
            transform: translateX(-150%);
            animation: date-pill-shine 3.8s ease-in-out infinite;
            pointer-events: none;
        }

        @keyframes date-pill-shine {
            45%, 100% { transform: translateX(160%); }
        }

        .chart-card {
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        }

        .chart-card canvas {
            transition: transform 0.25s ease, filter 0.25s ease;
            filter: saturate(1.03);
        }

        .chart-card:hover canvas {
            transform: scale(1.01);
            filter: saturate(1.08);
        }

        .action-required-card a {
            position: relative;
            overflow: hidden;
        }

        .action-required-card a::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.08), rgba(99, 102, 241, 0));
            transform: scaleX(0);
            transform-origin: left center;
            transition: transform 0.24s ease;
            pointer-events: none;
        }

        .action-required-card a:hover::before {
            transform: scaleX(1);
        }

        .today-activity-card {
            box-shadow: 0 16px 36px -22px rgba(37, 99, 235, 0.65);
        }

        .today-activity-card a {
            transition: transform 0.2s ease, background-color 0.2s ease;
        }

        .today-activity-card a:hover {
            transform: translateX(4px);
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        @media (max-width: 640px) {
            .dashboard-shell::before,
            .dashboard-shell::after {
                opacity: 0.28;
                filter: blur(42px);
            }

            .kpi-card::before {
                width: 3px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .reveal-item,
            .saas-card,
            .saas-card::after,
            .status-date-pill::before,
            .chart-card canvas,
            .today-activity-card a,
            .action-required-card a::before {
                animation: none !important;
                transition: none !important;
                transform: none !important;
            }
        }
    </style>
</head>
<body class="reports-page live-light text-slate-800 antialiased">
    <div class="app-shell">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="app-main flex-1 w-full overflow-y-auto p-4 pb-14 sm:p-8 sm:pb-20 lg:p-10 lg:pb-24">
            <div class="max-w-7xl mx-auto space-y-8 pb-24 sm:pb-32 lg:pb-36 dashboard-shell">
                
                <!-- Header -->
                <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 mt-2 reveal-item">
                    <div class="flex items-center gap-4">
                        <button id="sidebar-toggle" data-sidebar-toggle="" aria-label="Toggle sidebar" aria-expanded="false" class="sidebar-toggle-btn p-2 bg-white border border-slate-200 rounded-lg shadow-sm hover:bg-slate-50 transition text-slate-600 flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="3" y1="6" x2="21" y2="6"></line>
                                <line x1="3" y1="12" x2="21" y2="12"></line>
                                <line x1="3" y1="18" x2="21" y2="18"></line>
                            </svg>
                        </button>
                        <div>
                            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight"><?php echo __('dashboard'); ?></h1>
                            <p class="text-slate-500 mt-1.5 text-base">ยินดีต้อนรับกลับมา, <?php echo htmlspecialchars($admin_name); ?>! นี่คือภาพรวมข้อมูลของวันนี้</p>
                        </div>
                    </div>
                    <div class="status-date-pill flex items-center gap-3 bg-white px-4 py-2 border border-slate-200 rounded-full shadow-sm">
                        <span class="flex h-3 w-3 relative">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                        </span>
                        <span class="text-sm font-semibold text-slate-700">
                            <?php echo getLang() === 'th' ? formatDate(date('Y-m-d'), 'short') : date('d M Y'); ?>
                        </span>
                    </div>
                </div>

                <!-- Alerts -->
                <?php if (!empty($_GET['google_success'])): ?>
                <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 flex items-center justify-between" id="alert-success">
                    <div class="flex items-center gap-3 text-emerald-800">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        <span class="font-medium"><?php echo htmlspecialchars($_GET['google_success']); ?></span>
                    </div>
                    <button onclick="document.getElementById('alert-success').remove()" class="text-emerald-600 hover:text-emerald-800"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
                </div>
                <?php endif; ?>

                <!-- Hero Stats Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 reveal-item delay-1">
                    <!-- Revenue -->
                    <div class="saas-card kpi-card kpi-revenue p-6" onclick="window.location.href='report_payments.php'">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest"><?php echo __('total_revenue'); ?></h3>
                            <div class="p-2 bg-indigo-50 text-indigo-600 rounded-lg">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-slate-900 mb-1">฿<?php echo number_format($total_revenue, 0); ?></div>
                        <div class="text-xs font-medium text-emerald-600 flex items-center mt-2">
                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                            <?php echo __('from_paid_expenses'); ?>
                        </div>
                    </div>

                    <!-- Occupancy -->
                    <div class="saas-card kpi-card kpi-occupancy p-6" onclick="window.location.href='report_rooms.php'">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest"><?php echo __('occupancy_rate'); ?></h3>
                            <div class="p-2 bg-sky-50 text-sky-600 rounded-lg">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m-1 4h1m-1 4h1m4-12h1m-1 4h1m-1 4h1m-1 4h1"></path></svg>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-slate-900 mb-1"><?php echo $occupancy_rate; ?>%</div>
                        <div class="text-xs font-medium text-slate-500 mt-2">
                            <?php echo $room_occupied; ?> / <?php echo $total_rooms; ?> <?php echo __('not_vacant'); ?>
                        </div>
                    </div>

                    <!-- Tenants -->
                    <div class="saas-card kpi-card kpi-tenants p-6" onclick="window.location.href='report_tenants.php'">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest"> ผู้เช่าปัจจุบัน</h3>
                            <div class="p-2 bg-purple-50 text-purple-600 rounded-lg">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-slate-900 mb-1"><?php echo $tenant_active; ?></div>
                        <div class="text-xs font-medium text-slate-500 mt-2">
                            <?php echo $contract_active; ?> <?php echo __('active_contracts'); ?>งาน
                        </div>
                    </div>

                    <!-- Pending Repairs -->
                    <div class="saas-card kpi-card kpi-repairs p-6" onclick="window.location.href='report_repairs.php'">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest"> <?php echo __('pending_repairs'); ?></h3>
                            <div class="p-2 bg-amber-50 text-amber-600 rounded-lg">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-slate-900 mb-1"><?php echo $repair_waiting; ?></div>
                        <div class="text-xs font-medium <?php echo $repair_processing > 0 ? 'text-amber-600' : 'text-slate-500'; ?> mt-2">
                            <?php echo $repair_processing; ?> กำลังดำเนินการ
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 reveal-item delay-2">
                    <!-- Chart 1: Revenue -->
                    <div class="saas-card no-hover chart-card p-6 lg:col-span-2">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-slate-900 leading-none"><?php echo __('monthly_revenue'); ?></h3>
                            <a href="report_payments.php" class="text-sm text-indigo-600 font-semibold hover:text-indigo-800 transition"><?php echo __('view_details_arrow'); ?></a>
                        </div>
                        <div class="relative h-72 w-full">
                            <canvas id="monthlyRevenueChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Chart 2: Room Status -->
                    <div class="saas-card no-hover chart-card p-6 lg:col-span-1 flex flex-col">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-lg font-bold text-slate-900 leading-none"> <?php echo __('room_status'); ?></h3>
                        </div>
                        <div class="relative flex-grow min-h-[220px] w-full flex items-center justify-center -mt-2">
                            <canvas id="roomStatusChart"></canvas>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-center">
                            <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-100">
                                <div class="text-[10px] font-bold text-emerald-600 uppercase tracking-widest mb-1"> <?php echo __('vacant'); ?></div>
                                <div class="text-xl font-extrabold text-emerald-700 leading-none"><?php echo $room_available; ?></div>
                            </div>
                            <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                                <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1"> <?php echo __('not_vacant'); ?></div>
                                <div class="text-xl font-extrabold text-slate-700 leading-none"><?php echo $room_occupied; ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Secondary Row -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8 reveal-item delay-3">
                    <!-- Action Required List -->
                    <div class="saas-card no-hover action-required-card flex flex-col h-full overflow-hidden p-0">
                        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                            <h3 class="text-lg font-bold text-slate-900 leading-none">รายการต้องดำเนินการ</h3>
                            <span class="text-xs font-bold bg-rose-100 text-rose-700 px-3 py-1 rounded-full"><?php echo ($payment_pending + $repair_waiting); ?> รายการ</span>
                        </div>
                        <div class="flex-grow divide-y divide-slate-100">
                            <!-- Pending Invoices -->
                            <a href="report_invoice.php" class="flex flex-col sm:flex-row sm:items-center justify-between p-5 hover:bg-slate-50 transition group gap-4">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-full bg-amber-100 flex-shrink-0 flex items-center justify-center text-amber-600 group-hover:scale-110 transition-transform">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-900 leading-tight">รอตรวจสอบชำระเงิน</p>
                                        <p class="text-sm text-slate-500 mt-1">รายการที่ต้องตรวจสอบด้วยตนเอง</p>
                                    </div>
                                </div>
                                <div class="flex items-center sm:justify-end gap-3 ml-16 sm:ml-0">
                                    <span class="text-xl font-bold text-amber-600"><?php echo $payment_pending; ?></span>
                                    <svg class="w-5 h-5 text-slate-300 group-hover:text-amber-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                </div>
                            </a>
                            <!-- Waiting Repairs -->
                            <a href="report_repairs.php" class="flex flex-col sm:flex-row sm:items-center justify-between p-5 hover:bg-slate-50 transition group gap-4">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-full bg-rose-100 flex-shrink-0 flex items-center justify-center text-rose-600 group-hover:scale-110 transition-transform">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"></path></svg>
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-900 leading-tight">รอรับงานแจ้งซ่อม</p>
                                        <p class="text-sm text-slate-500 mt-1">คำร้องที่รอการตอบรับ</p>
                                    </div>
                                </div>
                                <div class="flex items-center sm:justify-end gap-3 ml-16 sm:ml-0">
                                    <span class="text-xl font-bold text-rose-600"><?php echo $repair_waiting; ?></span>
                                    <svg class="w-5 h-5 text-slate-300 group-hover:text-rose-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- Today's <?php echo __('today_activity'); ?> -->
                    <div class="saas-card no-hover today-activity-card relative overflow-hidden bg-gradient-to-br from-indigo-500 via-indigo-600 to-blue-600 border-none">
                        <!-- Decorative bg -->
                        <div class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 rounded-full bg-white opacity-5"></div>
                        <div class="absolute bottom-0 left-0 -ml-12 -mb-12 w-40 h-40 rounded-full bg-white opacity-10"></div>
                        
                        <div class="relative p-6 sm:p-8 h-full flex flex-col text-white">
                            <h3 class="text-xl font-bold mb-8 flex items-center gap-3 text-white leading-none">
                                <span class="p-2 bg-white/20 backdrop-blur-sm rounded-lg"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg></span>
                                <?php echo __('today_activity'); ?>
                            </h3>
                            
                            <div class="space-y-4 flex-grow flex flex-col justify-center">
                                <a href="manage_booking.php" class="bg-white/10 backdrop-blur-md rounded-2xl p-5 flex items-center justify-between border border-white/20 hover:bg-white/20 transition cursor-pointer">
                                    <div class="font-medium text-indigo-50">จองห้องใหม่</div>
                                    <div class="text-3xl font-extrabold text-white tracking-tight"><?php echo $today_bookings; ?></div>
                                </a>
                                <a href="manage_repairs.php" class="bg-white/10 backdrop-blur-md rounded-2xl p-5 flex items-center justify-between border border-white/20 hover:bg-white/20 transition cursor-pointer">
                                    <div class="font-medium text-indigo-50">แจ้งซ่อมใหม่</div>
                                    <div class="text-3xl font-extrabold text-white tracking-tight"><?php echo $today_repairs; ?></div>
                                </a>
                                <a href="report_payments.php" class="bg-white/10 backdrop-blur-md rounded-2xl p-5 flex items-center justify-between border border-white/20 hover:bg-white/20 transition cursor-pointer">
                                    <div class="font-medium text-indigo-50">ได้ชำระเงินแล้ว</div>
                                    <div class="text-3xl font-extrabold text-emerald-300 tracking-tight">฿<?php echo number_format($today_payments, 0); ?></div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div><!-- End Secondary Row -->

                <div class="h-16 sm:h-20 lg:h-24" aria-hidden="true"></div>

            </div>
        </main>
    </div>

    <!-- Scripting -->
    <script src="/dormitory_management/Public/Assets/Javascript/main.js" defer></script>
    <script src="/dormitory_management/Public/Assets/Javascript/animate-ui.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Options for charts
            const defaultLineOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { padding: 12, cornerRadius: 8, titleFont: { size: 14, family: 'Inter' }, bodyFont: { size: 14, family: 'Inter' } } },
                scales: {
                    y: { beginAtZero: true, grid: { borderDash: [4, 4], color: '#f1f5f9' }, border: { display: false }, ticks: { color: '#64748b', font: { family: 'Inter' } } },
                    x: { grid: { display: false }, border: { display: false }, ticks: { color: '#64748b', font: { family: 'Inter' } } }
                },
                interaction: { mode: 'index', intersect: false }
            };

            // 1. Chart Setup (Monthly Revenue)
            const revenueCtx = document.getElementById('monthlyRevenueChart');
            if (revenueCtx && typeof Chart !== 'undefined') {
                new Chart(revenueCtx, {
                    type: 'line',
                    data: {
                        labels: [<?php foreach ($monthly_revenue as $data) { echo "'" . thaiMonthYear($data['month']) . "',"; } ?>],
                        datasets: [{
                            label: '<?php echo __('revenue_baht'); ?>',
                            data: [<?php foreach ($monthly_revenue as $data) { echo $data['total'] . ","; } ?>],
                            borderColor: '#6366f1',
                            backgroundColor: 'rgba(99, 102, 241, 0.08)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#6366f1',
                            pointBorderWidth: 2
                        }]
                    },
                    options: defaultLineOptions
                });
            }

            // 2. Chart Setup (Room Status)
            const roomCtx = document.getElementById('roomStatusChart');
            if (roomCtx && typeof Chart !== 'undefined') {
                new Chart(roomCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['<?php echo __('vacant'); ?>', '<?php echo __('not_vacant'); ?>'],
                        datasets: [{
                            data: [<?php echo $room_available; ?>, <?php echo $room_occupied; ?>],
                            backgroundColor: ['#10b981', '#f1f5f9'],
                            borderWidth: 0,
                            hoverOffset: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '75%',
                        plugins: { legend: { display: false }, tooltip: { padding: 12, cornerRadius: 8, bodyFont: { size: 14, family: 'Inter' } } }
                    }
                });
            }
            
            // 3. First Time Tour Guide (Sidebar Toggle)
            if (!localStorage.getItem('sidebar_tour_seen')) {
                setTimeout(function() {
                    const toggleBtn = document.getElementById('sidebar-toggle');
                    const overlay = document.getElementById('first-time-tour-overlay');
                    const pointerBox = document.getElementById('tour-pointer');
                    
                    if (!toggleBtn || !overlay) return;
                    
                    // Show overlay
                    overlay.style.display = 'block';
                    
                    // Highlight button
                    const originalZIndex = toggleBtn.style.zIndex;
                    const originalPosition = toggleBtn.style.position || 'static';
                    
                    toggleBtn.style.position = 'relative';
                    toggleBtn.style.zIndex = '9999';
                    toggleBtn.classList.add('ring-4', 'ring-blue-500', 'ring-opacity-60', 'scale-105', 'transition-transform');
                    
                    // Position the pointer box near the button
                    const rect = toggleBtn.getBoundingClientRect();
                    pointerBox.style.top = (rect.bottom + 20) + 'px';
                    pointerBox.style.left = Math.max(10, rect.left - 10) + 'px'; // prevent off-screen
                    
                    // Setup close handler
                    const closeTour = function() {
                        overlay.style.display = 'none';
                        toggleBtn.style.zIndex = originalZIndex;
                        if (originalPosition === 'static') {
                            toggleBtn.style.position = '';
                        } else {
                            toggleBtn.style.position = originalPosition;
                        }
                        toggleBtn.classList.remove('ring-4', 'ring-blue-500', 'ring-opacity-60', 'scale-105', 'transition-transform');
                        localStorage.setItem('sidebar_tour_seen', 'true');
                    };
                    
                    document.getElementById('close-tour-btn').addEventListener('click', closeTour);
                    toggleBtn.addEventListener('click', closeTour, {once: true});
                }, 800); // Wait for animations to finish
            }
        });
    </script>

    <!-- First Time Tour HTML -->
    <div id="first-time-tour-overlay" class="animate-fade-in" style="display: none; position: fixed; inset: 0; z-index: 9998; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px);">
        <div id="tour-pointer" style="position: absolute; display: flex; flex-direction: column; align-items: flex-start; z-index: 9999;">
            <!-- Arrow -->
            <div style="width: 0; height: 0; border-left: 10px solid transparent; border-right: 10px solid transparent; border-bottom: 12px solid white; margin-left: 20px; margin-bottom: -1px;"></div>
            <!-- Text Box -->
            <div class="bg-white rounded-xl p-5 shadow-2xl max-w-[280px] animate-bounce-slight" style="border: 2px solid #3b82f6;">
                <h3 class="font-bold text-lg mb-2 text-slate-800 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16v-4"/><path d="M12 8h.01"/><rect width="20" height="14" x="2" y="5" rx="2"/></svg>
                    เมนูอยู่ตรงนี้นะ!
                </h3>
                <p class="text-sm text-slate-600 mb-4 leading-relaxed tracking-wide">คลิกที่ปุ่มนี้เพื่อเปิด/ปิดแผงเมนู และเข้าถึงฟังก์ชันต่างๆ ของระบบ</p>
                <button id="close-tour-btn" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-blue-700 w-full transition-colors shadow-sm">เข้าใจแล้ว</button>
            </div>
        </div>
    </div>
    <style>
        .animate-bounce-slight {
            animation: bounce-slight 2s infinite ease-in-out;
        }
        @keyframes bounce-slight {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }
    </style>
</body>
</html>