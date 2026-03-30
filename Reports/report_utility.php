<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

// เดือน/ปี filter
$filterMonth = $_GET['month'] ?? '';
$filterYear = $_GET['year'] ?? '';
$activeTab = $_GET['tab'] ?? 'water';

// อัตราค่าน้ำค่าไฟ
$waterRate = 18;
$electricRate = 8;
try {
    $rateStmt = $pdo->query("SELECT * FROM rate ORDER BY effective_date DESC LIMIT 1");
    $rate = $rateStmt->fetch(PDO::FETCH_ASSOC);
    if ($rate) {
        $waterRate = (int)$rate['rate_water'];
        $electricRate = (int)$rate['rate_elec'];
    }
} catch (PDOException $e) {}

// เดือน/ปีที่มีอยู่จริงในฐานข้อมูล (utility) + เดือนปัจจุบัน
$availableYears = [];
$availableMonthsByYear = [];
try {
    $periodStmt = $pdo->query("\n        SELECT DISTINCT YEAR(utl_date) AS y, MONTH(utl_date) AS m\n        FROM utility\n        WHERE utl_date IS NOT NULL\n        ORDER BY y DESC, m DESC\n    ");
    $periods = $periodStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($periods as $period) {
        $periodYear = (int)$period['y'];
        $periodMonth = (int)$period['m'];
        if (!isset($availableMonthsByYear[$periodYear])) {
            $availableMonthsByYear[$periodYear] = [];
            $availableYears[] = $periodYear;
        }
        $availableMonthsByYear[$periodYear][] = $periodMonth;
    }
} catch (PDOException $e) {}

$currentYear = (int)date('Y');
$currentMonth = (int)date('n');
if (!isset($availableMonthsByYear[$currentYear])) {
    $availableMonthsByYear[$currentYear] = [];
    $availableYears[] = $currentYear;
}
if (!in_array($currentMonth, $availableMonthsByYear[$currentYear], true)) {
    $availableMonthsByYear[$currentYear][] = $currentMonth;
}

$availableYears = array_values(array_unique(array_map('intval', $availableYears)));
rsort($availableYears);
foreach ($availableMonthsByYear as $yearKey => $monthsList) {
    $monthsList = array_values(array_unique(array_map('intval', $monthsList)));
    sort($monthsList);
    $availableMonthsByYear[(int)$yearKey] = $monthsList;
}

if ($filterMonth === '' && $filterYear === '' && !empty($availableYears)) {
    $latestYear = (int)$availableYears[0];
    $latestMonths = $availableMonthsByYear[$latestYear] ?? [];
    if (!empty($latestMonths)) {
        $filterYear = (string)$latestYear;
        $filterMonth = (string)max($latestMonths);
    }
}

$monthsForFilter = [];
if ($filterYear !== '' && isset($availableMonthsByYear[(int)$filterYear])) {
    $monthsForFilter = $availableMonthsByYear[(int)$filterYear];
} else {
    $monthSet = [];
    foreach ($availableMonthsByYear as $monthsList) {
        foreach ($monthsList as $m) {
            $monthSet[$m] = true;
        }
    }
    $monthsForFilter = array_keys($monthSet);
    sort($monthsForFilter);
}

// Build query
$whereClause = '';
$params = [];
if ($filterMonth && $filterYear) {
    $whereClause = ' AND MONTH(u.utl_date) = ? AND YEAR(u.utl_date) = ?';
    $params = [(int)$filterMonth, (int)$filterYear];
} elseif ($filterMonth) {
    $whereClause = ' AND MONTH(u.utl_date) = ?';
    $params = [(int)$filterMonth];
} elseif ($filterYear) {
    $whereClause = ' AND YEAR(u.utl_date) = ?';
    $params = [(int)$filterYear];
}

$sql = "
    SELECT u.*, c.ctr_id, t.tnt_name, r.room_number, r.room_id
    FROM utility u
    INNER JOIN (
        SELECT MAX(utl_id) AS utl_id
        FROM utility
        GROUP BY ctr_id, YEAR(utl_date), MONTH(utl_date)
    ) lu ON u.utl_id = lu.utl_id
    LEFT JOIN contract c ON u.ctr_id = c.ctr_id
    LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
    LEFT JOIN room r ON c.room_id = r.room_id
    WHERE 1=1 $whereClause
    ORDER BY CAST(r.room_number AS UNSIGNED) ASC, u.utl_date DESC
";
$utilStmt = $pdo->prepare($sql);
$utilStmt->execute($params);
$utilities = $utilStmt->fetchAll(PDO::FETCH_ASSOC);

// จัดกลุ่มตามชั้น
$floors = [];
foreach ($utilities as $util) {
    $num = (int)($util['room_number'] ?? 0);
    $floorNum = ($num >= 100) ? (int)floor($num / 100) : 1;
    $floors[$floorNum][] = $util;
}
ksort($floors);

// ค่าตั้งค่าระบบ
$siteName = 'Sangthian Dormitory';
$logoFilename = 'Logo.jpg';
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
        if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
    }
} catch (PDOException $e) {}

$thaiMonthsFull = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - ประวัติมิเตอร์น้ำ-ไฟ</title>
    <?php include __DIR__ . '/../includes/sidebar_toggle.php'; ?>
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/animate-ui.css">
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css">
    <style>
        :root {
            --report-accent: #f97316;
            --report-accent-dark: #ea580c;
        }

        body[data-report-tab="water"] {
            --report-accent: #0ea5e9;
            --report-accent-dark: #0284c7;
        }

        body[data-report-tab="electric"] {
            --report-accent: #f97316;
            --report-accent-dark: #ea580c;
        }

        /* === Clean Light Theme === */
        html, body, .app-shell, .app-main, .reports-page {
            background: #f0f0f0 !important;
        }
        .page-header-bar {
            background: rgba(255,255,255,0.97) !important;
            border-bottom: 1px solid #e0e0e0 !important;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06) !important;
            margin-top: 0.75rem !important;
        }
        .page-header-bar h2 { color: #222 !important; }
        .sidebar-toggle-btn svg { stroke: #333 !important; }

        .report-page {
            width: 100%;
            max-width: none;
            margin: 0;
            padding: 0 !important;
        }
        .app-main > .report-page {
            padding-left: 0 !important;
            padding-right: 1rem !important;
        }
        .report-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.07);
            margin: 0;
            overflow: hidden;
        }
        .report-card-title {
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
            padding: 1.25rem 1rem 0.5rem;
        }

        /* Filter Bar */
        .filter-bar {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.25rem 1rem 0.75rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-bar select {
            padding: 0.4rem 0.7rem;
            border: 1px solid #d0d0d0;
            border-radius: 8px;
            font-size: 0.9rem;
            color: #333;
            background: #fff;
        }
        .filter-bar .filter-btn {
            padding: 0.4rem 0.85rem;
            border-radius: 8px;
            font-size: 0.85rem;
            text-decoration: none;
            color: #fff;
            border: none;
            background: var(--report-accent);
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s;
        }
        .filter-bar .filter-btn:hover { background: var(--report-accent-dark); }
        .filter-bar .clear-btn {
            padding: 0.4rem 0.85rem;
            border-radius: 8px;
            font-size: 0.85rem;
            text-decoration: none;
            color: #666;
            border: 1px solid #d0d0d0;
            background: #fff;
            transition: all 0.2s;
        }
        .filter-bar .clear-btn:hover { background: #f5f5f5; }

        /* Summary Row */
        .summary-row {
            display: flex;
            justify-content: center;
            gap: 0.75rem;
            padding: 0 1rem 0.75rem;
            flex-wrap: wrap;
        }
        .summary-badge {
            padding: 0.35rem 0.85rem;
            border-radius: 16px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .summary-badge.count { background: #e3f2fd; color: #1565c0; }
        .summary-badge.water-sum { background: #e1f5fe; color: #0277bd; }
        .summary-badge.elec-sum { background: #fce4ec; color: #c2185b; }

        /* Tabs */
        .report-tabs {
            display: flex;
            margin: 0 1rem;
            border-radius: 10px;
            overflow: hidden;
        }
        .report-tab {
            flex: 1;
            text-align: center;
            padding: 0.8rem;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            border: none !important;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            transition: background 0.2s;
            color: #64748b !important;
            background: transparent !important;
        }
        .report-tab:hover {
            background: #eef2f7 !important;
            color: #334155 !important;
        }
        .report-tab.water-tab.active {
            background: linear-gradient(135deg, #0ea5e9, #0284c7) !important;
            color: #ffffff !important;
            box-shadow: inset 0 -3px 0 rgba(255,255,255,0.25) !important;
        }
        .report-tab.elec-tab.active {
            background: linear-gradient(135deg, #f97316, #ea580c) !important;
            color: #ffffff !important;
            box-shadow: inset 0 -3px 0 rgba(255,255,255,0.25) !important;
        }
        .report-tab svg { width: 18px; height: 18px; }

        /* Floor Header */
        .floor-header {
            padding: 0.5rem 1rem;
            font-weight: 600;
            font-size: 0.9rem;
            color: #555;
            background: #fafafa;
            border-bottom: 1px solid #eee;
            border-top: 1px solid #eee;
        }

        /* Table */
        .report-table { width: 100%; border-collapse: collapse; }
        .report-table thead th {
            background: var(--report-accent);
            color: #fff;
            font-weight: 600;
            font-size: 0.82rem;
            padding: 0.7rem 0.4rem;
            text-align: center;
            white-space: nowrap;
        }
        .report-table tbody tr { border-bottom: 1px solid #f0f0f0; transition: background 0.12s; }
        .report-table tbody tr:hover { background: #fffde7; }
        .report-table td {
            padding: 0.6rem 0.4rem;
            text-align: center;
            font-size: 0.95rem;
            color: #333;
            vertical-align: middle;
        }
        .room-num-cell { font-weight: 700; font-size: 1.05rem; color: #222; }
        .status-icon svg { width: 22px; height: 22px; fill: #666; }
        .usage-cell { font-weight: 700; color: #0277bd; }
        .usage-cell.elec-usage { color: #c2185b; }
        .date-cell { font-size: 0.85rem; color: #888; }
        .prev-val { color: #999; }
        .curr-val { 
            font-weight: 600;
            background: #e1f5fe;
            border-radius: 4px;
            padding: 0.25rem 0.5rem;
            display: inline-block;
        }
        .curr-val.elec-val {
            background: #fce4ec;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #aaa;
        }
        .empty-state svg { width: 48px; height: 48px; margin-bottom: 0.75rem; }

        /* Go to manage link */
        .manage-link {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.5rem 1rem;
            margin: 0.75rem 1rem;
            border-radius: 8px;
            background: #e8f5e9;
            color: #2e7d32;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: background 0.2s;
        }
        .manage-link:hover { background: #c8e6c9; }

        /* Responsive */
        @media (max-width: 768px) {
            .report-page { max-width: 100%; }
            .report-card { margin: 0; border-radius: 0; }
            .report-table td, .report-table th { padding: 0.45rem 0.25rem; font-size: 0.82rem; }
        }
        @media (max-width: 480px) {
            .report-table td, .report-table th { padding: 0.35rem 0.15rem; font-size: 0.75rem; }
            .report-card-title { font-size: 1.2rem; }
            .report-tab { font-size: 0.82rem; padding: 0.65rem 0.4rem; }
            .curr-val { padding: 0.15rem 0.3rem; font-size: 0.8rem; }
        }

        /* Mobile card conversion for utility tables */
        @media (max-width:768px) {
            .report-table { display:block; overflow-x:auto; -webkit-overflow-scrolling:touch; }
            .report-table thead { display:none; }
            .report-table tbody { display:block; }
            .report-table tbody tr {
                display:block;
                background:#fff;
                border:1px solid #eee;
                border-radius:12px;
                margin-bottom:1rem;
                padding:1rem;
            }
            .report-table tbody td {
                display:flex;
                justify-content:space-between;
                align-items:center;
                padding:0.75rem 0;
                border-bottom:1px solid rgba(0,0,0,0.05);
            }
            .report-table tbody td:last-child { border-bottom:none; }
            .report-table tbody td::before {
                content:attr(data-label);
                font-weight:600;
                color:#555;
                font-size:0.85rem;
                text-transform:uppercase;
                letter-spacing:0.5px;
                flex-shrink:0;
                margin-right:1rem;
            }
            .report-table tbody td:first-child {
                margin-bottom:0.5rem;
                padding-bottom:1rem;
                border-bottom:2px solid #eee;
            }
            .report-table tbody td:first-child::before { display:none; }
        }
        @media (max-width:480px) {
            .report-table tbody tr { padding:0.875rem; }
            .report-table tbody td { padding:0.625rem 0; font-size:0.9rem; }
            .report-table tbody td::before { font-size:0.75rem; }
        }
    </style>
</head>
<body class="reports-page" data-report-tab="<?php echo htmlspecialchars($activeTab, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="app-shell">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="app-main">
            <div class="report-page">
                <?php $pageTitle = 'ประวัติมิเตอร์น้ำ-ไฟ'; include __DIR__ . '/../includes/page_header.php'; ?>

                <div class="report-card">
                    <div class="report-card-title">ประวัติมิเตอร์น้ำ-ไฟ</div>

                    <!-- Filter -->
                    <form method="get" class="filter-bar" data-allow-submit>
                        <input type="hidden" id="tabInput" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
                        <select name="month" onchange="this.form.submit()">
                            <option value="">-- ทุกเดือน --</option>
                            <?php foreach ($monthsForFilter as $m): ?>
                            <option value="<?php echo $m; ?>" <?php echo $filterMonth == $m ? 'selected' : ''; ?>><?php echo $thaiMonthsFull[$m]; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="year" onchange="this.form.submit()">
                            <option value="">-- ทุกปี --</option>
                            <?php foreach ($availableYears as $y): ?>
                            <option value="<?php echo $y; ?>" <?php echo $filterYear == $y ? 'selected' : ''; ?>><?php echo $y + 543; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <!-- Summary -->
                    <?php
                    $totalWaterUsage = 0;
                    $totalElecUsage = 0;
                    foreach ($utilities as $u) {
                        $totalWaterUsage += (int)($u['utl_water_end'] ?? 0) - (int)($u['utl_water_start'] ?? 0);
                        $totalElecUsage += (int)($u['utl_elec_end'] ?? 0) - (int)($u['utl_elec_start'] ?? 0);
                    }
                    ?>
                    <div class="summary-row">
                        <span class="summary-badge count"><?php echo count($utilities); ?> รายการ</span>
                        <span class="summary-badge water-sum">💧 น้ำรวม <?php echo number_format($totalWaterUsage); ?> หน่วย</span>
                        <span class="summary-badge elec-sum">⚡ ไฟรวม <?php echo number_format($totalElecUsage); ?> หน่วย</span>
                    </div>

                    <!-- Link to manage -->
                    <div style="text-align:center;">
                        <a href="manage_utility.php" class="manage-link">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            ไปหน้าจดมิเตอร์
                        </a>
                    </div>

                    <!-- Tabs -->
                    <div class="report-tabs">
                        <button type="button" class="report-tab water-tab <?php echo $activeTab==='water'?'active':''; ?>" onclick="switchTab('water')">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/></svg>
                            ประวัติค่าน้ำ
                        </button>
                        <button type="button" class="report-tab elec-tab <?php echo $activeTab==='electric'?'active':''; ?>" onclick="switchTab('electric')">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                            ประวัติค่าไฟ
                        </button>
                    </div>

                    <?php if (empty($utilities)): ?>
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1.5"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                        <p>ยังไม่มีข้อมูลมิเตอร์</p>
                    </div>
                    <?php else: ?>

                    <!-- WATER TAB -->
                    <div id="waterPanel" style="<?php echo $activeTab!=='water'?'display:none':''; ?>">
                        <?php foreach ($floors as $floorNum => $floorUtils): ?>
                        <div class="floor-header">ชั้นที่ <?php echo $floorNum; ?></div>
                        <div class="table-responsive">
                        <table class="report-table">
                            <thead><tr>
                                <th>ห้อง</th>
                                <th>สถานะ</th>
                                <th>เลขมิเตอร์เดือนก่อนหน้า</th>
                                <th>เลขมิเตอร์เดือนล่าสุด</th>
                                <th>หน่วยที่ใช้</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($floorUtils as $util):
                                $waterUsage = (int)($util['utl_water_end'] ?? 0) - (int)($util['utl_water_start'] ?? 0);
                            ?>
                            <tr>
                                <td class="room-num-cell" data-label="ห้อง"><?php echo htmlspecialchars((string)($util['room_number'] ?? '-')); ?></td>
                                <td class="status-icon" data-label="สถานะ">
                                    <?php if (!empty($util['tnt_name'])): ?>
                                    <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                                    <?php endif; ?>
                                </td>
                                <td class="prev-val" data-label="เลขมิเตอร์เดือนก่อนหน้า"><?php echo str_pad((string)(int)($util['utl_water_start'] ?? 0), 7, '0', STR_PAD_LEFT); ?></td>
                                <td data-label="เลขมิเตอร์เดือนล่าสุด"><span class="curr-val"><?php echo str_pad((string)(int)($util['utl_water_end'] ?? 0), 7, '0', STR_PAD_LEFT); ?></span></td>
                                <td class="usage-cell" data-label="หน่วยที่ใช้"><?php echo number_format($waterUsage); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- ELECTRIC TAB -->
                    <div id="electricPanel" style="<?php echo $activeTab!=='electric'?'display:none':''; ?>">
                        <?php foreach ($floors as $floorNum => $floorUtils): ?>
                        <div class="floor-header">ชั้นที่ <?php echo $floorNum; ?></div>
                        <div class="table-responsive">
                        <table class="report-table">
                            <thead><tr>
                                <th>ห้อง</th>
                                <th>สถานะ</th>
                                <th>เลขมิเตอร์เดือนก่อนหน้า</th>
                                <th>เลขมิเตอร์เดือนล่าสุด</th>
                                <th>หน่วยที่ใช้</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($floorUtils as $util):
                                $elecUsage = (int)($util['utl_elec_end'] ?? 0) - (int)($util['utl_elec_start'] ?? 0);
                            ?>
                            <tr>
                                <td class="room-num-cell" data-label="ห้อง"><?php echo htmlspecialchars((string)($util['room_number'] ?? '-')); ?></td>
                                <td class="status-icon" data-label="สถานะ">
                                    <?php if (!empty($util['tnt_name'])): ?>
                                    <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                                    <?php endif; ?>
                                </td>
                                <td class="prev-val" data-label="เลขมิเตอร์เดือนก่อนหน้า"><?php echo str_pad((string)(int)($util['utl_elec_start'] ?? 0), 5, '0', STR_PAD_LEFT); ?></td>
                                <td data-label="เลขมิเตอร์เดือนล่าสุด"><span class="curr-val elec-val"><?php echo str_pad((string)(int)($util['utl_elec_end'] ?? 0), 5, '0', STR_PAD_LEFT); ?></span></td>
                                <td class="usage-cell elec-usage" data-label="หน่วยที่ใช้"><?php echo number_format($elecUsage); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
    function switchTab(tab) {
        document.getElementById('waterPanel').style.display = tab==='water' ? '' : 'none';
        document.getElementById('electricPanel').style.display = tab==='electric' ? '' : 'none';
        document.querySelector('.water-tab').classList.toggle('active', tab==='water');
        document.querySelector('.elec-tab').classList.toggle('active', tab==='electric');
        if (document.body) document.body.setAttribute('data-report-tab', tab);

        const tabInput = document.getElementById('tabInput');
        if (tabInput) tabInput.value = tab;

        const clearFilterLink = document.getElementById('clearFilterLink');
        if (clearFilterLink) clearFilterLink.href = 'report_utility.php?tab=' + encodeURIComponent(tab);

        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url.toString());
    }
    </script>
    <script src="/dormitory_management/Public/Assets/Javascript/animate-ui.js"></script>
</body>
</html>
