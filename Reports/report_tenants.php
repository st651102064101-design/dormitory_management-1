<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

// ดึงค่าตั้งค่าระบบ
$siteName = 'Sangthian Dormitory';
$logoFilename = 'Logo.jpg';
$themeColor = '#0f172a';
$defaultViewMode = 'grid';
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename', 'theme_color', 'default_view_mode')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
        if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
        if ($row['setting_key'] === 'theme_color') $themeColor = $row['setting_value'];
        if ($row['setting_key'] === 'default_view_mode') $defaultViewMode = strtolower($row['setting_value']) === 'list' ? 'list' : 'grid';
    }
} catch (PDOException $e) {}

// ดึงข้อมูลผู้เช่าทั้งหมดพร้อมข้อมูลห้องและสัญญา
$tenants = [];
try {
    $stmt = $pdo->query("
        SELECT t.*, 
               c.ctr_id, c.ctr_start, c.ctr_end, c.ctr_status, c.ctr_deposit,
               r.room_id, r.room_number, r.room_status,
               rt.type_name, rt.type_price
        FROM tenant t
        LEFT JOIN contract c ON t.tnt_id = c.tnt_id AND c.ctr_status IN ('0', '2')
        LEFT JOIN room r ON c.room_id = r.room_id
        LEFT JOIN roomtype rt ON r.type_id = rt.type_id
        ORDER BY t.tnt_ceatetime DESC
    ");
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = $e->getMessage();
}

// สถิติ
$stats = [
    'total' => count($tenants),
    'staying' => 0,        // tnt_status = 1
    'waiting' => 0,        // tnt_status = 2
    'moved_out' => 0,      // tnt_status = 0
    'booking' => 0,        // tnt_status = 3
    'cancel_booking' => 0, // tnt_status = 4
    'with_contract' => 0
];

foreach ($tenants as $t) {
    $status = (string)($t['tnt_status'] ?? '');
    if ($status === '1') $stats['staying']++;
    elseif ($status === '2') $stats['waiting']++;
    elseif ($status === '0') $stats['moved_out']++;
    elseif ($status === '3') $stats['booking']++;
    elseif ($status === '4') $stats['cancel_booking']++;
    if (!empty($t['ctr_id'])) $stats['with_contract']++;
}

$statusMap = [
    '0' => ['label' => 'ย้ายออก', 'color' => '#ef4444', 'bg' => 'rgba(239,68,68,0.15)'],
    '1' => ['label' => 'พักอยู่', 'color' => '#22c55e', 'bg' => 'rgba(34,197,94,0.15)'],
    '2' => ['label' => 'รอเข้าพัก', 'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.15)'],
    '3' => ['label' => 'จองห้อง', 'color' => '#3b82f6', 'bg' => 'rgba(59,130,246,0.15)'],
    '4' => ['label' => 'ยกเลิกจอง', 'color' => '#6b7280', 'bg' => 'rgba(107,114,128,0.15)']
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteName); ?> - รายงานผู้เช่า</title>
    <?php include __DIR__ . '/../includes/sidebar_toggle.php'; ?>
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/lottie-icons.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4/dist/style.css" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/datatable-modern.css" />
    <style>
        :root {
            --theme-bg: <?php echo $themeColor; ?>;
            --card-bg: linear-gradient(145deg, rgba(30,41,59,0.95), rgba(15,23,42,0.98));
            --glass-bg: rgba(255,255,255,0.03);
            --glass-border: rgba(255,255,255,0.08);
            --accent-blue: #60a5fa;
            --accent-green: #22c55e;
            --accent-orange: #f59e0b;
            --accent-red: #ef4444;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --shadow-glow: 0 0 40px rgba(96,165,250,0.15);
        }

        /* ===== Page Header ===== */
        .page-header {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-glow);
            position: relative;
            overflow: hidden;
        }
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent-blue), var(--accent-green), var(--accent-orange));
        }
        .page-header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        .page-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .page-title-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--accent-blue), #3b82f6);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            box-shadow: 0 10px 30px rgba(59,130,246,0.3);
        }
        .page-title h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }
        .page-title p {
            color: var(--text-secondary);
            margin: 0.25rem 0 0;
            font-size: 0.95rem;
        }

        /* ===== Stats Cards ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid var(--glass-border);
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: radial-gradient(circle, var(--stat-color) 0%, transparent 70%);
            opacity: 0.1;
            border-radius: 50%;
            transform: translate(30%, -30%);
        }
        .stat-card.blue { --stat-color: var(--accent-blue); }
        .stat-card.green { --stat-color: var(--accent-green); }
        .stat-card.orange { --stat-color: var(--accent-orange); }
        .stat-card.red { --stat-color: var(--accent-red); }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            background: var(--stat-color);
            opacity: 0.9;
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1;
        }
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        /* ===== View Toggle ===== */
        .view-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .view-toggle {
            display: flex;
            background: rgba(15,23,42,0.8);
            border-radius: 12px;
            padding: 4px;
            border: 1px solid var(--glass-border);
        }
        .view-btn {
            padding: 0.6rem 1.2rem;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        .view-btn:hover {
            color: var(--text-primary);
        }
        .view-btn.active {
            background: linear-gradient(135deg, var(--accent-blue), #3b82f6);
            color: white;
            box-shadow: 0 4px 15px rgba(59,130,246,0.4);
        }
        .filter-select {
            padding: 0.6rem 1rem;
            background: rgba(15,23,42,0.8);
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 0.9rem;
            cursor: pointer;
            outline: none;
            transition: all 0.3s ease;
        }
        .filter-select:hover, .filter-select:focus {
            border-color: var(--accent-blue);
        }

        /* ===== Content Container ===== */
        .content-container {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 1.5rem;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-glow);
            min-height: 400px;
        }

        /* ===== Grid View ===== */
        .tenants-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            opacity: 1;
            transform: translateY(0);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .tenants-grid.hidden {
            opacity: 0;
            transform: translateY(20px);
            position: absolute;
            pointer-events: none;
        }

        .tenant-card {
            background: linear-gradient(160deg, rgba(30,41,59,0.9), rgba(15,23,42,0.95));
            border-radius: 16px;
            border: 1px solid var(--glass-border);
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            animation: cardFadeIn 0.5s ease forwards;
            opacity: 0;
        }
        @keyframes cardFadeIn {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        .tenant-card:nth-child(1) { animation-delay: 0.05s; }
        .tenant-card:nth-child(2) { animation-delay: 0.1s; }
        .tenant-card:nth-child(3) { animation-delay: 0.15s; }
        .tenant-card:nth-child(4) { animation-delay: 0.2s; }
        .tenant-card:nth-child(5) { animation-delay: 0.25s; }
        .tenant-card:nth-child(6) { animation-delay: 0.3s; }
        .tenant-card:nth-child(n+7) { animation-delay: 0.35s; }

        .tenant-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 25px 50px rgba(0,0,0,0.4), 0 0 30px rgba(96,165,250,0.1);
            border-color: rgba(96,165,250,0.3);
        }

        .tenant-card-header {
            padding: 1.25rem;
            background: linear-gradient(135deg, rgba(96,165,250,0.1), rgba(59,130,246,0.05));
            border-bottom: 1px solid var(--glass-border);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .tenant-avatar {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--accent-blue), #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            text-transform: uppercase;
            box-shadow: 0 8px 20px rgba(96,165,250,0.3);
            flex-shrink: 0;
        }
        .tenant-info h3 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            line-height: 1.3;
        }
        .tenant-info .tenant-id {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
            font-family: 'Monaco', monospace;
        }

        .tenant-card-body {
            padding: 1.25rem;
        }
        .tenant-detail {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .tenant-detail:last-child {
            border-bottom: none;
        }
        .tenant-detail-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: var(--glass-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            flex-shrink: 0;
        }
        .tenant-detail-content {
            flex: 1;
            min-width: 0;
        }
        .tenant-detail-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .tenant-detail-value {
            font-size: 0.95rem;
            color: var(--text-primary);
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .tenant-card-footer {
            padding: 1rem 1.25rem;
            background: rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 0.9rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .room-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 0.9rem;
            border-radius: 8px;
            background: rgba(96,165,250,0.15);
            color: var(--accent-blue);
            font-size: 0.85rem;
            font-weight: 600;
        }

        /* ===== Table View ===== */
        .table-container {
            opacity: 1;
            transform: translateY(0);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .table-container.hidden {
            opacity: 0;
            transform: translateY(20px);
            position: absolute;
            pointer-events: none;
        }
        
        #tenants-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        #tenants-table thead th {
            background: rgba(15,23,42,0.8);
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            padding: 1rem;
            text-align: left;
            border-bottom: 2px solid var(--glass-border);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        #tenants-table tbody tr {
            transition: all 0.3s ease;
        }
        #tenants-table tbody tr:hover {
            background: rgba(96,165,250,0.08);
        }
        #tenants-table tbody td {
            padding: 1rem;
            color: var(--text-primary);
            border-bottom: 1px solid var(--glass-border);
            vertical-align: middle;
        }
        .table-tenant-name {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .table-avatar {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--accent-blue), #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 600;
            color: white;
            flex-shrink: 0;
        }
        
        /* Mobile Table Responsive */
        @media (max-width: 768px) {
            #tenants-table {
                display: block;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            #tenants-table thead {
                display: none;
            }
            #tenants-table tbody {
                display: block;
            }
            #tenants-table tbody tr {
                display: block;
                background: linear-gradient(160deg, rgba(30,41,59,0.9), rgba(15,23,42,0.95));
                border: 1px solid var(--glass-border);
                border-radius: 12px;
                margin-bottom: 1rem;
                padding: 1rem;
            }
            #tenants-table tbody tr:hover {
                background: linear-gradient(160deg, rgba(30,41,59,0.95), rgba(15,23,42,1));
            }
            #tenants-table tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.75rem 0;
                border-bottom: 1px solid rgba(255,255,255,0.05);
            }
            #tenants-table tbody td:last-child {
                border-bottom: none;
            }
            #tenants-table tbody td::before {
                content: attr(data-label);
                font-weight: 600;
                color: var(--text-secondary);
                font-size: 0.85rem;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                flex-shrink: 0;
                margin-right: 1rem;
            }
            #tenants-table tbody td:first-child {
                margin-bottom: 0.5rem;
                padding-bottom: 1rem;
                border-bottom: 2px solid var(--glass-border);
            }
            #tenants-table tbody td:first-child::before {
                display: none;
            }
            .table-tenant-name {
                width: 100%;
                justify-content: flex-start;
            }
            .table-avatar {
                width: 48px;
                height: 48px;
                font-size: 1.2rem;
            }
        }
        
        @media (max-width: 480px) {
            #tenants-table tbody tr {
                padding: 0.875rem;
            }
            #tenants-table tbody td {
                padding: 0.625rem 0;
                font-size: 0.9rem;
            }
            #tenants-table tbody td::before {
                font-size: 0.75rem;
            }
            .table-avatar {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
        }

        /* ===== Search Box ===== */
        .search-box {
            position: relative;
            flex: 1;
            max-width: 300px;
        }
        .search-box input {
            width: 100%;
            padding: 0.7rem 1rem 0.7rem 2.8rem;
            background: rgba(15,23,42,0.8);
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 0.9rem;
            outline: none;
            transition: all 0.3s ease;
        }
        .search-box input:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(96,165,250,0.2);
        }
        .search-box input::placeholder {
            color: var(--text-secondary);
        }
        .search-box::before {
            content: '';
            display: none;
        }

        /* ===== Empty State ===== */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        .empty-state h3 {
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        /* ===== Responsive ===== */
        @media (max-width: 768px) {
            .page-header {
                padding: 1.25rem;
                margin-bottom: 1.5rem;
            }
            .page-header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            .page-title {
                width: 100%;
            }
            .page-title-icon {
                width: 48px;
                height: 48px;
                font-size: 1.4rem;
            }
            .page-title h1 {
                font-size: 1.4rem;
            }
            .page-title p {
                font-size: 0.85rem;
            }
            .view-controls {
                width: 100%;
                flex-direction: column;
                gap: 0.75rem;
            }
            .search-box {
                max-width: 100%;
                width: 100%;
                order: 0;
            }
            .filter-select {
                width: 100%;
            }
            .view-toggle {
                width: 100%;
                justify-content: center;
            }
            .view-btn {
                flex: 1;
                justify-content: center;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            .stat-card {
                padding: 1.25rem;
            }
            .stat-value {
                font-size: 2rem;
            }
            .tenants-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            .content-container {
                padding: 1rem;
                border-radius: 16px;
            }
            .main-content {
                padding-bottom: 1rem;
            }
        }
        
        @media (max-width: 480px) {
            .page-header {
                padding: 1rem;
                border-radius: 16px;
            }
            .page-title-icon {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
                border-radius: 12px;
            }
            .page-title h1 {
                font-size: 1.2rem;
            }
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            .stat-card {
                padding: 1rem;
            }
            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
            }
            .stat-value {
                font-size: 1.75rem;
            }
            .stat-label {
                font-size: 0.85rem;
            }
            .tenant-card {
                border-radius: 12px;
            }
            .tenant-card-header {
                padding: 1rem;
            }
            .tenant-avatar {
                width: 48px;
                height: 48px;
                font-size: 1.2rem;
            }
            .tenant-info h3 {
                font-size: 1rem;
            }
            .tenant-card-body {
                padding: 1rem;
            }
            .tenant-detail {
                padding: 0.5rem 0;
            }
            .tenant-card-footer {
                padding: 0.75rem 1rem;
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            .view-btn {
                padding: 0.5rem 1rem;
                font-size: 0.85rem;
            }
        }

        /* ===== Light Theme ===== */
        body.light-theme {
            --card-bg: linear-gradient(145deg, #ffffff, #f8fafc);
            --glass-bg: rgba(0,0,0,0.02);
            --glass-border: rgba(0,0,0,0.08);
            --text-primary: #1e293b;
            --text-secondary: #64748b;
        }
        body.light-theme .stat-card,
        body.light-theme .tenant-card,
        body.light-theme .content-container,
        body.light-theme .page-header {
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        body.light-theme .view-btn,
        body.light-theme .filter-select,
        body.light-theme .search-box input {
            background: #f1f5f9;
        }
        body.light-theme #tenants-table thead th {
            background: #f1f5f9;
        }
        body.light-theme .tenant-card-footer {
            background: rgba(0,0,0,0.03);
        }

        /* DataTable pagination align right */
        .datatable-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            padding: 1rem 0;
        }
        .datatable-pagination {
            margin-left: auto !important;
        }
        .datatable-pagination ul {
            justify-content: flex-end !important;
        }
        .datatable-info {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        /* DataTable Mobile Responsive */
        @media (max-width: 768px) {
            .datatable-top {
                flex-direction: column;
                gap: 0.75rem;
                padding: 0 0 1rem 0;
            }
            .datatable-search {
                width: 100% !important;
                margin: 0 !important;
            }
            .datatable-search input {
                width: 100% !important;
                padding: 0.6rem 1rem !important;
                font-size: 0.9rem !important;
            }
            .datatable-dropdown {
                width: 100% !important;
            }
            .datatable-selector {
                width: 100% !important;
                padding: 0.6rem 1rem !important;
                font-size: 0.9rem !important;
            }
            .datatable-bottom {
                flex-direction: column;
                align-items: stretch;
                gap: 0.75rem;
            }
            .datatable-info {
                text-align: center;
                order: 1;
                font-size: 0.85rem;
            }
            .datatable-pagination {
                margin: 0 !important;
                order: 2;
            }
            .datatable-pagination ul {
                justify-content: center !important;
                flex-wrap: wrap;
            }
            .datatable-pagination li {
                margin: 0.25rem !important;
            }
            .datatable-pagination a {
                padding: 0.4rem 0.7rem !important;
                font-size: 0.85rem !important;
                min-width: 32px !important;
            }
        }
        
        @media (max-width: 480px) {
            .datatable-pagination a {
                padding: 0.35rem 0.6rem !important;
                font-size: 0.8rem !important;
                min-width: 28px !important;
            }
        }

        /* Fix sidebar and scroll */
        .app-container {
            display: flex !important;
            min-height: 100vh;
        }
        .main-content {
            flex: 1;
            overflow-y: auto !important;
            overflow-x: hidden;
            height: 100vh;
            padding-bottom: 2rem;
        }
        
        /* Fix sidebar toggle button */
        .app-sidebar {
            position: relative;
            z-index: 1000 !important;
            pointer-events: auto !important;
        }
        .app-sidebar * {
            pointer-events: auto !important;
        }
        #sidebar-toggle {
            z-index: 1001 !important;
            pointer-events: auto !important;
            cursor: pointer !important;
        }
        header {
            position: relative;
            z-index: 100;
        }
        header button {
            pointer-events: auto !important;
            cursor: pointer !important;
        }

        /* Show only sidebar toggle button, hide header text */
        header {
            display: block !important;
            position: static !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        header h2 {
            display: none !important;
        }
        header div {
            display: flex !important;
            align-items: center !important;
            height: auto !important;
            margin: 0 !important;
            padding: 0.5rem 1rem !important;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <?php $pageTitle = 'รายงานผู้เช่า'; include __DIR__ . '/../includes/page_header.php'; ?>
            <div style="margin: 1.5rem; margin-top: 0rem; padding-top: 0rem;">
                
                <!-- Page Header -->
                <div class="page-header">
                    <div class="page-header-content">
                        <div class="page-title">
                            <div class="page-title-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:24px;height:24px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                            <div>
                                <h1>รายงานผู้เช่า</h1>
                                <p>ข้อมูลผู้เช่าทั้งหมด <?php echo number_format($stats['total']); ?> คน</p>
                            </div>
                        </div>
                        <div class="view-controls">
                            <div class="search-box">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);width:16px;height:16px;opacity:0.6;pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                <input type="text" id="searchInput" placeholder="ค้นหาผู้เช่า...">
                            </div>
                            <select class="filter-select" id="statusFilter">
                                <option value="">ทุกสถานะ</option>
                                <option value="3">จองห้อง</option>
                                <option value="2">รอเข้าพัก</option>
                                <option value="1">พักอยู่</option>
                                <option value="4">ยกเลิกจอง</option>
                                <option value="0">ย้ายออก</option>
                            </select>
                            <div class="view-toggle">
                                <button class="view-btn active" data-view="grid" onclick="switchView('grid')">
                                    <span>▦</span> Grid
                                </button>
                                <button class="view-btn" data-view="table" onclick="switchView('table')">
                                    <span>☰</span> Table
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card blue">
                        <div class="lottie-icon blue">
                          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </div>
                        <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                        <div class="stat-label">ผู้เช่าทั้งหมด</div>
                    </div>
                    <div class="stat-card green">
                        <div class="lottie-icon green">
                          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        </div>
                        <div class="stat-value"><?php echo number_format($stats['staying']); ?></div>
                        <div class="stat-label">กำลังพักอยู่</div>
                    </div>
                    <div class="stat-card orange">
                        <div class="lottie-icon orange">
                          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </div>
                        <div class="stat-value"><?php echo number_format($stats['waiting']); ?></div>
                        <div class="stat-label">รอเข้าพัก</div>
                    </div>
                    <div class="stat-card red">
                        <div class="lottie-icon red">
                          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2z"/><circle cx="15" cy="12" r="1"/></svg>
                        </div>
                        <div class="stat-value"><?php echo number_format($stats['moved_out']); ?></div>
                        <div class="stat-label">ย้ายออกแล้ว</div>
                    </div>
                    <div class="stat-card" style="--stat-color: #3b82f6;">
                        <div class="lottie-icon indigo">
                          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
                        </div>
                        <div class="stat-value"><?php echo number_format($stats['booking']); ?></div>
                        <div class="stat-label">จองห้อง</div>
                    </div>
                    <div class="stat-card" style="--stat-color: #6b7280;">
                        <div class="lottie-icon gray">
                          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        </div>
                        <div class="stat-value"><?php echo number_format($stats['cancel_booking']); ?></div>
                        <div class="stat-label">ยกเลิกจอง</div>
                    </div>
                </div>

                <!-- Content Container -->
                <div class="content-container">
                    <!-- Grid View -->
                    <div class="tenants-grid" id="gridView">
                        <?php if (empty($tenants)): ?>
                            <div class="empty-state" style="grid-column: 1/-1;">
                                <div class="empty-state-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:48px;height:48px;opacity:0.5;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                                <h3>ไม่พบข้อมูลผู้เช่า</h3>
                                <p>ยังไม่มีข้อมูลผู้เช่าในระบบ</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($tenants as $tenant): 
                                $status = (string)($tenant['tnt_status'] ?? '2');
                                $statusInfo = $statusMap[$status] ?? $statusMap['2'];
                                $initials = mb_substr($tenant['tnt_name'] ?? 'N', 0, 2, 'UTF-8');
                            ?>
                            <div class="tenant-card" data-status="<?php echo $status; ?>" data-name="<?php echo htmlspecialchars(strtolower($tenant['tnt_name'] ?? '')); ?>">
                                <div class="tenant-card-header">
                                    <div class="tenant-avatar"><?php echo htmlspecialchars($initials); ?></div>
                                    <div class="tenant-info">
                                        <h3><?php echo htmlspecialchars($tenant['tnt_name'] ?? 'ไม่ระบุ'); ?></h3>
                                        <div class="tenant-id"><?php echo htmlspecialchars($tenant['tnt_id'] ?? '-'); ?></div>
                                    </div>
                                </div>
                                <div class="tenant-card-body">
                                    <div class="tenant-detail">
                                        <div class="tenant-detail-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg></div>
                                        <div class="tenant-detail-content">
                                            <div class="tenant-detail-label">เบอร์โทร</div>
                                            <div class="tenant-detail-value"><?php echo htmlspecialchars($tenant['tnt_phone'] ?? '-'); ?></div>
                                        </div>
                                    </div>
                                    <div class="tenant-detail">
                                        <div class="tenant-detail-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
                                        <div class="tenant-detail-content">
                                            <div class="tenant-detail-label">อายุ</div>
                                            <div class="tenant-detail-value"><?php echo $tenant['tnt_age'] ? $tenant['tnt_age'] . ' ปี' : '-'; ?></div>
                                        </div>
                                    </div>
                                    <div class="tenant-detail">
                                        <div class="tenant-detail-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
                                        <div class="tenant-detail-content">
                                            <div class="tenant-detail-label">ห้องพัก</div>
                                            <div class="tenant-detail-value"><?php echo $tenant['room_number'] ? 'ห้อง ' . htmlspecialchars($tenant['room_number']) : 'ยังไม่มีห้อง'; ?></div>
                                        </div>
                                    </div>
                                    <div class="tenant-detail">
                                        <div class="tenant-detail-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
                                        <div class="tenant-detail-content">
                                            <div class="tenant-detail-label">สัญญาถึง</div>
                                            <div class="tenant-detail-value"><?php echo $tenant['ctr_end'] ? date('d/m/Y', strtotime($tenant['ctr_end'])) : '-'; ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="tenant-card-footer">
                                    <span class="status-badge" style="background: <?php echo $statusInfo['bg']; ?>; color: <?php echo $statusInfo['color']; ?>;">
                                        <?php echo $statusInfo['label']; ?>
                                    </span>
                                    <?php if ($tenant['room_number']): ?>
                                        <span class="room-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;vertical-align:middle;margin-right:2px;"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg><?php echo htmlspecialchars($tenant['room_number']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Table View -->
                    <div class="table-container hidden" id="tableView">
                        <table id="tenants-table">
                            <thead>
                                <tr>
                                    <th>ผู้เช่า</th>
                                    <th>รหัส</th>
                                    <th>เบอร์โทร</th>
                                    <th>อายุ</th>
                                    <th>ห้อง</th>
                                    <th>สถานะ</th>
                                    <th>สัญญาถึง</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tenants as $tenant): 
                                    $status = (string)($tenant['tnt_status'] ?? '2');
                                    $statusInfo = $statusMap[$status] ?? $statusMap['2'];
                                    $initials = mb_substr($tenant['tnt_name'] ?? 'N', 0, 1, 'UTF-8');
                                ?>
                                <tr data-status="<?php echo $status; ?>">
                                    <td>
                                        <div class="table-tenant-name">
                                            <div class="table-avatar"><?php echo htmlspecialchars($initials); ?></div>
                                            <span><?php echo htmlspecialchars($tenant['tnt_name'] ?? 'ไม่ระบุ'); ?></span>
                                        </div>
                                    </td>
                                    <td data-label="รหัส" style="font-family: monospace; font-size: 0.85rem; color: var(--text-secondary);">
                                        <?php echo htmlspecialchars($tenant['tnt_id'] ?? '-'); ?>
                                    </td>
                                    <td data-label="เบอร์โทร"><?php echo htmlspecialchars($tenant['tnt_phone'] ?? '-'); ?></td>
                                    <td data-label="อายุ"><?php echo $tenant['tnt_age'] ? $tenant['tnt_age'] . ' ปี' : '-'; ?></td>
                                    <td data-label="ห้อง">
                                        <?php if ($tenant['room_number']): ?>
                                            <span class="room-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;vertical-align:middle;margin-right:2px;"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg><?php echo htmlspecialchars($tenant['room_number']); ?></span>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="สถานะ">
                                        <span class="status-badge" style="background: <?php echo $statusInfo['bg']; ?>; color: <?php echo $statusInfo['color']; ?>;">
                                            <?php echo $statusInfo['label']; ?>
                                        </span>
                                    </td>
                                    <td data-label="สัญญาถึง"><?php echo $tenant['ctr_end'] ? date('d/m/Y', strtotime($tenant['ctr_end'])) : '-'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script src="/dormitory_management/Public/Assets/Javascript/animate-ui.js" defer></script>
    <script src="/dormitory_management/Public/Assets/Javascript/main.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4" defer></script>
    <script>
        let dataTable = null;
        let currentView = 'grid';

        // Safe localStorage getter
        function safeGet(key) {
            try {
                return localStorage.getItem(key) || '';
            } catch (e) {
                console.warn('localStorage access denied:', e);
                return '';
            }
        }

        // Switch between Grid and Table view
        function switchView(view) {
            currentView = view;
            const gridView = document.getElementById('gridView');
            const tableView = document.getElementById('tableView');
            const viewBtns = document.querySelectorAll('.view-btn');

            console.log('switchView called with:', view);
            console.log('gridView:', gridView ? 'found' : 'NOT FOUND');
            console.log('tableView:', tableView ? 'found' : 'NOT FOUND');
            console.log('viewBtns count:', viewBtns.length);

            viewBtns.forEach(btn => {
                btn.classList.toggle('active', btn.dataset.view === view);
            });

            if (view === 'grid') {
                tableView.classList.add('hidden');
                setTimeout(() => {
                    gridView.classList.remove('hidden');
                    // Re-trigger animations
                    document.querySelectorAll('.tenant-card').forEach((card, i) => {
                        card.style.animation = 'none';
                        card.offsetHeight; // Trigger reflow
                        card.style.animation = `cardFadeIn 0.5s ease forwards ${Math.min(i * 0.05, 0.35)}s`;
                    });
                }, 150);
            } else if (view === 'table') {
                gridView.classList.add('hidden');
                setTimeout(() => {
                    tableView.classList.remove('hidden');
                    initDataTable();
                }, 150);
            }

            localStorage.setItem('tenantViewPreference', view);
        }

        // Initialize DataTable
        function initDataTable() {
            if (dataTable) return;
            
            const table = document.getElementById('tenants-table');
            if (table && typeof simpleDatatables !== 'undefined') {
                // Check if mobile view
                const isMobile = window.innerWidth <= 768;
                
                dataTable = new simpleDatatables.DataTable(table, {
                    searchable: true,
                    fixedHeight: false,
                    perPage: isMobile ? 5 : 10,
                    perPageSelect: isMobile ? [5, 10, 25] : [5, 10, 25, 50],
                    labels: {
                        placeholder: 'ค้นหาผู้เช่า...',
                        perPage: 'รายการต่อหน้า',
                        noRows: 'ไม่พบข้อมูลผู้เช่า',
                        info: 'แสดง {start} ถึง {end} จาก {rows} รายการ'
                    },
                    layout: {
                        top: isMobile ? '' : '{select}{search}',
                        bottom: '{info}{pager}'
                    }
                });
            }
        }

        // Search functionality for Grid view
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            filterCards();
        });

        // Status filter
        document.getElementById('statusFilter').addEventListener('change', function(e) {
            filterCards();
        });

        function filterCards() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const cards = document.querySelectorAll('.tenant-card');

            cards.forEach(card => {
                const name = card.dataset.name || '';
                const status = card.dataset.status || '';
                
                const matchesSearch = name.includes(searchTerm);
                const matchesStatus = !statusFilter || status === statusFilter;

                if (matchesSearch && matchesStatus) {
                    card.style.display = '';
                    card.style.animation = 'cardFadeIn 0.3s ease forwards';
                } else {
                    card.style.display = 'none';
                }
            });

            // Also filter table rows
            const rows = document.querySelectorAll('#tenants-table tbody tr');
            rows.forEach(row => {
                const status = row.dataset.status || '';
                const matchesStatus = !statusFilter || status === statusFilter;
                row.style.display = matchesStatus ? '' : 'none';
            });
        }

        // Theme detection
        function applyTheme() {
            const themeColor = '<?php echo $themeColor; ?>';
            const isLight = /^(#fff|#ffffff|rgb\(25[0-5],\s*25[0-5],\s*25[0-5]\))$/i.test(themeColor.trim());
            document.body.classList.toggle('light-theme', isLight);
        }

        // Initialize on load
        window.addEventListener('load', function() {
            console.log('===== WINDOW LOAD EVENT =====');
            applyTheme();

            // Get default view mode from database (via PHP)
            // list -> table, grid -> grid
            const dbDefaultView = '<?php echo $defaultViewMode === "list" ? "table" : "grid"; ?>';
            
            console.log('Window Load: $defaultViewMode =', '<?php echo htmlspecialchars($defaultViewMode, ENT_QUOTES, "UTF-8"); ?>');
            console.log('Window Load: dbDefaultView =', dbDefaultView);
            console.log('Window Load: Calling switchView with:', dbDefaultView);
            
            // Use database default directly (no localStorage fallback)
            switchView(dbDefaultView);
            
            console.log('===== WINDOW LOAD COMPLETE =====');
        });
    </script>
</body>
</html>
