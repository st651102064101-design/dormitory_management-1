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
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="/dormitory_management/Public/Assets/Javascript/chart.umd.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; margin: 0; padding: 0; }
        .saas-card { background: #ffffff; border-radius: 1rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); border: 1px solid rgba(226, 232, 240, 0.8); transition: all 0.2s ease; cursor: pointer; }
        .saas-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); border-color: rgba(203, 213, 225, 1); }
        .saas-card.no-hover:hover { transform: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); cursor: default; border-color: auto; }
        .app-main { background: #f8fafc !important; }
        
        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="reports-page live-light text-slate-800 antialiased">
    <div class="app-shell">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="app-main p-4 sm:p-8 lg:p-10 w-full overflow-y-auto">
            <div class="max-w-7xl mx-auto space-y-8 pb-12">
                
                <!-- Header -->
                <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mt-2">
                    <div>
                        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Overview</h1>
                        <p class="text-slate-500 mt-1.5 text-base">Welcome back, <?php echo htmlspecialchars($admin_name); ?>! Here's what's happening today.</p>
                    </div>
                    <div class="flex items-center gap-3 bg-white px-4 py-2 border border-slate-200 rounded-full shadow-sm">
                        <span class="flex h-3 w-3 relative">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                        </span>
                        <span class="text-sm font-semibold text-slate-700"><?php echo date('d M Y'); ?></span>
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
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Revenue -->
                    <div class="saas-card p-6" onclick="window.location.href='report_payments.php'">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest">Total Revenue</h3>
                            <div class="p-2 bg-indigo-50 text-indigo-600 rounded-lg">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-slate-900 mb-1">฿<?php echo number_format($total_revenue, 0); ?></div>
                        <div class="text-xs font-medium text-emerald-600 flex items-center mt-2">
                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                            From verified payments
                        </div>
                    </div>

                    <!-- Occupancy -->
                    <div class="saas-card p-6" onclick="window.location.href='report_rooms.php'">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest">Occupancy Rate</h3>
                            <div class="p-2 bg-sky-50 text-sky-600 rounded-lg">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m-1 4h1m-1 4h1m4-12h1m-1 4h1m-1 4h1m-1 4h1"></path></svg>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-slate-900 mb-1"><?php echo $occupancy_rate; ?>%</div>
                        <div class="text-xs font-medium text-slate-500 mt-2">
                            <?php echo $room_occupied; ?> / <?php echo $total_rooms; ?> rooms used
                        </div>
                    </div>

                    <!-- Tenants -->
                    <div class="saas-card p-6" onclick="window.location.href='report_tenants.php'">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest">Active Tenants</h3>
                            <div class="p-2 bg-purple-50 text-purple-600 rounded-lg">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-slate-900 mb-1"><?php echo $tenant_active; ?></div>
                        <div class="text-xs font-medium text-slate-500 mt-2">
                            <?php echo $contract_active; ?> active contracts
                        </div>
                    </div>

                    <!-- Pending Repairs -->
                    <div class="saas-card p-6" onclick="window.location.href='report_repairs.php'">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest">Pending Repairs</h3>
                            <div class="p-2 bg-amber-50 text-amber-600 rounded-lg">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-slate-900 mb-1"><?php echo $repair_waiting; ?></div>
                        <div class="text-xs font-medium <?php echo $repair_processing > 0 ? 'text-amber-600' : 'text-slate-500'; ?> mt-2">
                            <?php echo $repair_processing; ?> currently in progress
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Chart 1: Revenue -->
                    <div class="saas-card no-hover p-6 lg:col-span-2">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-slate-900 leading-none">Revenue Trajectory</h3>
                            <a href="report_payments.php" class="text-sm text-indigo-600 font-semibold hover:text-indigo-800 transition">View Full Report &rarr;</a>
                        </div>
                        <div class="relative h-72 w-full">
                            <canvas id="monthlyRevenueChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Chart 2: Room Status -->
                    <div class="saas-card no-hover p-6 lg:col-span-1 flex flex-col">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-lg font-bold text-slate-900 leading-none">Room Status</h3>
                        </div>
                        <div class="relative flex-grow min-h-[220px] w-full flex items-center justify-center -mt-2">
                            <canvas id="roomStatusChart"></canvas>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-center">
                            <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-100">
                                <div class="text-[10px] font-bold text-emerald-600 uppercase tracking-widest mb-1">Vacant</div>
                                <div class="text-xl font-extrabold text-emerald-700 leading-none"><?php echo $room_available; ?></div>
                            </div>
                            <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                                <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Occupied</div>
                                <div class="text-xl font-extrabold text-slate-700 leading-none"><?php echo $room_occupied; ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Secondary Row -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Action Required List -->
                    <div class="saas-card no-hover flex flex-col h-full overflow-hidden p-0">
                        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                            <h3 class="text-lg font-bold text-slate-900 leading-none">Requires Attention</h3>
                            <span class="text-xs font-bold bg-rose-100 text-rose-700 px-3 py-1 rounded-full"><?php echo ($payment_pending + $repair_waiting); ?> Items</span>
                        </div>
                        <div class="flex-grow divide-y divide-slate-100">
                            <!-- Pending Invoices -->
                            <a href="report_invoice.php" class="flex items-center justify-between p-5 hover:bg-slate-50 transition group">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-full bg-amber-100 flex items-center justify-center text-amber-600 group-hover:scale-110 transition-transform">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-900 leading-tight">Unverified Payments</p>
                                        <p class="text-sm text-slate-500 mt-1">Transactions that need manual review</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-xl font-bold text-amber-600"><?php echo $payment_pending; ?></span>
                                    <svg class="w-5 h-5 text-slate-300 group-hover:text-amber-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                </div>
                            </a>
                            <!-- Waiting Repairs -->
                            <a href="report_repairs.php" class="flex items-center justify-between p-5 hover:bg-slate-50 transition group">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-full bg-rose-100 flex items-center justify-center text-rose-600 group-hover:scale-110 transition-transform">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"></path></svg>
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-900 leading-tight">Pending Repair Tickets</p>
                                        <p class="text-sm text-slate-500 mt-1">Maintenance issues awaiting action</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-xl font-bold text-rose-600"><?php echo $repair_waiting; ?></span>
                                    <svg class="w-5 h-5 text-slate-300 group-hover:text-rose-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- Today's Activity Spotlight -->
                    <div class="saas-card no-hover relative overflow-hidden bg-gradient-to-br from-indigo-500 via-indigo-600 to-blue-600 border-none">
                        <!-- Decorative bg -->
                        <div class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 rounded-full bg-white opacity-5"></div>
                        <div class="absolute bottom-0 left-0 -ml-12 -mb-12 w-40 h-40 rounded-full bg-white opacity-10"></div>
                        
                        <div class="relative p-6 sm:p-8 h-full flex flex-col text-white">
                            <h3 class="text-xl font-bold mb-8 flex items-center gap-3 text-white leading-none">
                                <span class="p-2 bg-white/20 backdrop-blur-sm rounded-lg"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg></span>
                                Activity Spotlight
                            </h3>
                            
                            <div class="space-y-4 flex-grow flex flex-col justify-center">
                                <div class="bg-white/10 backdrop-blur-md rounded-2xl p-5 flex items-center justify-between border border-white/20 hover:bg-white/15 transition cursor-default">
                                    <div class="font-medium text-indigo-50">New Bookings Today</div>
                                    <div class="text-3xl font-extrabold text-white tracking-tight"><?php echo $today_bookings; ?></div>
                                </div>
                                <div class="bg-white/10 backdrop-blur-md rounded-2xl p-5 flex items-center justify-between border border-white/20 hover:bg-white/15 transition cursor-default">
                                    <div class="font-medium text-indigo-50">New Repair Tickets</div>
                                    <div class="text-3xl font-extrabold text-white tracking-tight"><?php echo $today_repairs; ?></div>
                                </div>
                                <div class="bg-white/10 backdrop-blur-md rounded-2xl p-5 flex items-center justify-between border border-white/20 hover:bg-white/15 transition cursor-default">
                                    <div class="font-medium text-indigo-50">Payments Received</div>
                                    <div class="text-3xl font-extrabold text-emerald-300 tracking-tight">฿<?php echo number_format($today_payments, 0); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!-- End Secondary Row -->

            </div>
        </main>
    </div>

    <!-- Scripting -->
    <script src="/dormitory_management/Public/Assets/Javascript/main.js" defer></script>
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
                            label: 'Revenue (THB)',
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
                        labels: ['Vacant', 'Occupied'],
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
        });
    </script>
</body>
</html>