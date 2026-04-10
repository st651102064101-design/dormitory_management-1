<?php
/**
 * Admin - สร้าง QR Code สำหรับผู้เช่า (Apple Style)
 */
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

// ดึงรายการสัญญาที่ยังใช้งานอยู่
$contracts = [];
try {
    // ตรวจสอบว่ามีคอลัมน์ access_token หรือยัง
    $checkColumn = $pdo->query("SHOW COLUMNS FROM contract LIKE 'access_token'");
    $hasColumn = $checkColumn ? $checkColumn->fetch(PDO::FETCH_ASSOC) : false;
    
    if (!$hasColumn) {
        $pdo->exec("ALTER TABLE `contract` ADD COLUMN `access_token` VARCHAR(64) DEFAULT NULL COMMENT 'Token สำหรับเข้าถึง Tenant Portal' AFTER `room_id`");
        $pdo->exec("ALTER TABLE `contract` ADD UNIQUE KEY `access_token_unique` (`access_token`)");
    }

    // ล้าง token ของสัญญาที่ยกเลิกแล้ว เพื่อป้องกันคนเช่าเก่าเข้าถึง
    $pdo->exec("UPDATE `contract` SET `access_token` = NULL WHERE `ctr_status` = '1' AND `access_token` IS NOT NULL");

    $pdo->exec("UPDATE `contract` SET `access_token` = MD5(CONCAT(ctr_id, '-', tnt_id, '-', room_id, '-', NOW(), '-', RAND())) WHERE `access_token` IS NULL AND `ctr_status` IN ('0', '2')");
    
    $stmt = $pdo->query(" 
        SELECT c.*, 
               t.tnt_name, t.tnt_phone,
               r.room_number,
               rt.type_name
        FROM contract c
        JOIN tenant t ON c.tnt_id = t.tnt_id
        JOIN room r ON c.room_id = r.room_id
        LEFT JOIN roomtype rt ON r.type_id = rt.type_id
        WHERE c.ctr_status IN ('0', '2')
          AND NOT EXISTS (
              SELECT 1
              FROM contract newer
              WHERE newer.room_id = c.room_id
                AND newer.ctr_status IN ('0', '2')
                AND (
                    COALESCE(newer.ctr_end, '0000-00-00') > COALESCE(c.ctr_end, '0000-00-00')
                    OR (
                        COALESCE(newer.ctr_end, '0000-00-00') = COALESCE(c.ctr_end, '0000-00-00')
                        AND COALESCE(newer.ctr_start, '0000-00-00') > COALESCE(c.ctr_start, '0000-00-00')
                    )
                    OR (
                        COALESCE(newer.ctr_end, '0000-00-00') = COALESCE(c.ctr_end, '0000-00-00')
                        AND COALESCE(newer.ctr_start, '0000-00-00') = COALESCE(c.ctr_start, '0000-00-00')
                        AND newer.ctr_id > c.ctr_id
                    )
                )
          )
        ORDER BY CAST(r.room_number AS UNSIGNED) ASC
    ");
    $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $uniqueContracts = [];
    $seenRooms = [];
    foreach ($contracts as $contract) {
        $roomKey = (string)($contract['room_id'] ?? $contract['room_number'] ?? '');
        if ($roomKey === '' || isset($seenRooms[$roomKey])) {
            continue;
        }
        $seenRooms[$roomKey] = true;
        $uniqueContracts[] = $contract;
    }
    $contracts = $uniqueContracts;
} catch (PDOException $e) {
    $error = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
}

// ดึงค่าตั้งค่าระบบ
$siteName = 'Sangthian Dormitory';
$logoFilename = 'Logo.jpg';
$themeColor = '#0f172a';
$isLightTheme = false;
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename', 'theme_color')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
        if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
        if ($row['setting_key'] === 'theme_color') $themeColor = $row['setting_value'];
    }
    
    // คำนวณ brightness ของสี theme
    $hex = str_replace('#', '', $themeColor);
    if (strlen($hex) === 6) {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
        $isLightTheme = ($brightness > 155);
    }
} catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }

$baseUrl = getTenantPortalUrl();
$totalContracts = count($contracts);
$lightThemeClass = $isLightTheme ? 'live-light' : '';
$defaultQrView = 'list';
?>
<!DOCTYPE html>
<html lang="th" class="<?php echo $lightThemeClass; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สร้าง QR Code ผู้เช่า - <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css">
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/animate-ui.css">
    <?php include __DIR__ . '/../includes/sidebar_toggle.php'; ?>
    <script>
        // Apply theme immediately before page renders
        (function() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'light') {
                document.documentElement.classList.add('light-theme');
                document.documentElement.classList.add('live-light');
            }
        })();
    </script>
    <style>
        :root {
            --apple-blue: #007aff;
            --apple-green: #34c759;
            --apple-purple: #af52de;
            --apple-pink: #ff2d55;
            --apple-orange: #ff9500;
            --apple-teal: #5ac8fa;
            --apple-bg: #000000;
            --apple-card: rgba(28, 28, 30, 0.9);
            --apple-card-hover: rgba(44, 44, 46, 0.95);
            --apple-text: #ffffff;
            --apple-text-secondary: #8e8e93;
            --apple-separator: rgba(84, 84, 88, 0.65);
            --apple-radius: 20px;
        }

        /* Light Theme CSS Variables */
        html.light-theme,
        body.light-theme,
        .light-theme {
            --apple-bg: #f8fafc;
            --apple-card: #ffffff;
            --apple-card-hover: #f1f5f9;
            --apple-text: #1e293b;
            --apple-text-secondary: #64748b;
            --apple-separator: #e2e8f0;
        }

        body.qr-page {
            background: linear-gradient(135deg, #0a0a0f 0%, #1a1a2e 50%, #0a0a0f 100%);
            min-height: 100vh;
            font-family: 'Prompt', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        /* Light Theme Body Background */
        html.light-theme body.qr-page,
        body.light-theme.qr-page {
            background: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 50%, #f8fafc 100%) !important;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes scaleIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        @keyframes glow {
            0%, 100% { box-shadow: 0 0 20px rgba(0, 122, 255, 0.3); }
            50% { box-shadow: 0 0 40px rgba(0, 122, 255, 0.6); }
        }

        .app-main {
            padding: 0 !important;
            margin: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }

        .app-main main {
            padding: 0 !important;
            margin: 0 !important;
            width: 100% !important;
        }

        body.qr-page .page-header-bar {
            width: min(1600px, calc(100% - 3rem));
            max-width: min(1600px, calc(100% - 3rem));
            margin: 1.5rem auto 0 !important;
            box-sizing: border-box;
        }

        html.light-theme body.qr-page .quick-action-link,
        body.light-theme.qr-page .quick-action-link,
        body.qr-page.live-light .quick-action-link,
        .light-theme .qr-page .quick-action-link {
            background: rgba(59, 130, 246, 0.14) !important;
            border-color: rgba(59, 130, 246, 0.5) !important;
            color: #1d4ed8 !important;
        }

        html.light-theme body.qr-page .quick-action-link:hover,
        body.light-theme.qr-page .quick-action-link:hover,
        body.qr-page.live-light .quick-action-link:hover,
        .light-theme .qr-page .quick-action-link:hover {
            background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
            border-color: #1d4ed8 !important;
            color: #ffffff !important;
        }

        html.light-theme body.qr-page .quick-action-link.active,
        body.light-theme.qr-page .quick-action-link.active,
        body.qr-page.live-light .quick-action-link.active,
        .light-theme .qr-page .quick-action-link.active {
            background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
            border-color: #1d4ed8 !important;
            color: #ffffff !important;
        }

        @keyframes floatParticle {
            0% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10% { opacity: 0.3; }
            90% { opacity: 0.3; }
            100% { transform: translateY(-100vh) rotate(720deg); opacity: 0; }
        }

        .qr-wrapper {
            width: min(1600px, calc(100% - 3rem));
            max-width: min(1600px, calc(100% - 3rem));
            margin: 1.5rem auto;
            padding: 1.5rem;
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            animation: fadeInUp 0.6s ease;
            box-sizing: border-box;
        }

        .qr-header {
            text-align: center;
            margin-bottom: 40px;
            animation: fadeInUp 0.6s ease;
        }

        .qr-header-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, var(--apple-blue), var(--apple-purple));
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: float 3s ease-in-out infinite;
            box-shadow: 0 10px 40px rgba(0, 122, 255, 0.3);
        }

        .qr-header-icon svg {
            width: 44px;
            height: 44px;
            color: #ffffff;
            stroke: none;
            fill: currentColor;
        }

        .qr-header-icon svg rect {
            fill: currentColor;
            stroke: none;
        }

        /* Light theme override for header icon to ensure contrast */
        html.light-theme .qr-header-icon,
        body.light-theme .qr-header-icon,
        .light-theme .qr-header-icon,
        html.live-light .qr-header-icon,
        body.live-light .qr-header-icon {
            background: linear-gradient(135deg, #ffffff, #f1f5f9) !important;
            box-shadow: 0 6px 18px rgba(16,24,40,0.06) !important;
            border: 1px solid #e6eef6 !important;
        }

        html.light-theme .qr-header-icon svg,
        body.light-theme .qr-header-icon svg,
        .light-theme .qr-header-icon svg,
        html.live-light .qr-header-icon svg,
        body.live-light .qr-header-icon svg {
            color: #334155 !important;
            stroke: none !important;
            fill: currentColor !important;
        }

        html.light-theme .qr-header-icon svg rect,
        body.light-theme .qr-header-icon svg rect,
        .light-theme .qr-header-icon svg rect,
        html.live-light .qr-header-icon svg rect,
        body.live-light .qr-header-icon svg rect {
            fill: currentColor !important;
            stroke: none !important;
        }

        .qr-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #fff 0%, #94a3b8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }

        .qr-header p {
            color: var(--apple-text-secondary);
            font-size: 1.1rem;
        }

        .stats-bar {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 40px;
            flex-wrap: wrap;
        }

        .stat-item {
            background: var(--apple-card);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--apple-separator);
            border-radius: 16px;
            padding: 20px 30px;
            text-align: center;
            animation: scaleIn 0.5s ease;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .stat-item:hover {
            transform: translateY(-5px) scale(1.02);
            border-color: var(--apple-blue);
            box-shadow: 0 10px 40px rgba(0, 122, 255, 0.2);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            margin: 0 auto 12px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-icon.blue { background: linear-gradient(135deg, #007aff, #5856d6); }
        .stat-icon.green { background: linear-gradient(135deg, #34c759, #30d158); }
        .stat-icon.purple { background: linear-gradient(135deg, #af52de, #bf5af2); }

        .stat-icon svg {
            width: 26px;
            height: 26px;
            stroke: white;
            stroke-width: 2;
            fill: none;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--apple-text);
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 0.85rem;
            color: var(--apple-text-secondary);
        }

        .action-bar {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 40px;
            flex-wrap: wrap;
        }

        .btn-apple {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 28px;
            border: none;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            font-family: inherit;
        }

        .btn-apple svg {
            width: 22px;
            height: 22px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
            transition: transform 0.3s;
        }

        .btn-apple:hover svg {
            transform: scale(1.1);
        }

        .btn-apple.primary {
            background: linear-gradient(135deg, var(--apple-blue), #5856d6);
            color: white;
            box-shadow: 0 4px 20px rgba(0, 122, 255, 0.4);
        }

        .btn-apple.primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(0, 122, 255, 0.5);
        }

        .btn-apple.secondary {
            background: var(--apple-card);
            color: var(--apple-text);
            border: 1px solid var(--apple-separator);
        }

        .btn-apple.secondary:hover {
            background: var(--apple-card-hover);
            border-color: var(--apple-blue);
        }

        .btn-apple.view-toggle-btn {
            min-width: 170px;
            justify-content: center;
        }

        .view-toggle-group {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: transparent;
            border: none;
            border-radius: 14px;
            padding: 0;
        }

        .view-toggle-group .btn-apple.view-toggle-btn {
            min-width: 130px;
            padding: 10px 16px;
            border-radius: 10px;
            box-shadow: none;
            transform: none;
            background: #f8fafc !important;
            border: 1px solid #cbd5e1 !important;
            color: #334155 !important;
        }

        .view-toggle-group .btn-apple.view-toggle-btn svg {
            color: currentColor !important;
            stroke: currentColor !important;
            fill: none !important;
        }

        .view-toggle-group .btn-apple.view-toggle-btn:hover {
            background: #f1f5f9 !important;
            border-color: #94a3b8 !important;
        }

        .view-toggle-group .btn-apple.view-toggle-btn.active {
            background: linear-gradient(135deg, var(--apple-blue), #5856d6) !important;
            border-color: transparent !important;
            color: #ffffff !important;
            box-shadow: 0 4px 14px rgba(0, 122, 255, 0.35);
        }

        .view-toggle-group .btn-apple.view-toggle-btn.active svg {
            color: #ffffff !important;
            stroke: #ffffff !important;
        }

        #viewListBtn,
        #viewGridBtn {
            color: #1f2937 !important;
        }

        #viewListBtn svg,
        #viewGridBtn svg,
        #viewListBtn svg *,
        #viewGridBtn svg * {
            stroke: currentColor !important;
            fill: none !important;
        }

        #viewListBtn.active,
        #viewGridBtn.active {
            color: #ffffff !important;
            background: linear-gradient(135deg, var(--apple-blue), #5856d6) !important;
            border-color: transparent !important;
        }

        #viewListBtn.active svg,
        #viewGridBtn.active svg,
        #viewListBtn.active svg *,
        #viewGridBtn.active svg * {
            stroke: #ffffff !important;
        }

        .qr-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 24px;
            width: 100%;
        }

        .qr-grid.hidden {
            display: none !important;
        }

        .qr-grid.row-view {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .qr-grid.row-view .qr-card {
            display: grid;
            grid-template-columns: auto minmax(220px, 1fr) auto auto;
            gap: 14px;
            align-items: center;
            text-align: left;
            padding: 16px 18px;
            animation: none;
        }

        .qr-grid.row-view .qr-card::before {
            display: none;
        }

        .qr-grid.row-view .qr-card:hover {
            transform: translateY(-2px);
        }

        .qr-grid.row-view .room-badge {
            margin-bottom: 0;
            font-size: 0.95rem;
            padding: 8px 14px;
            min-width: 130px;
            justify-content: center;
        }

        .qr-grid.row-view .tenant-info {
            margin-bottom: 0;
        }

        .qr-grid.row-view .tenant-name,
        .qr-grid.row-view .tenant-phone {
            justify-content: flex-start;
        }

        .qr-grid.row-view .qr-container {
            margin-bottom: 0;
            padding: 10px;
        }

        .qr-grid.row-view .qr-container img {
            width: 96px;
            height: 96px;
        }

        .qr-grid.row-view .card-actions {
            margin-left: auto;
            justify-content: flex-end;
        }

        @media (max-width: 1600px) { .qr-grid { grid-template-columns: repeat(4, 1fr); } }
        @media (max-width: 1400px) { .qr-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 1000px) { .qr-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 600px) { .qr-grid { grid-template-columns: 1fr; } }

        @media (max-width: 980px) {
            .qr-grid.row-view .qr-card {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .qr-grid.row-view .tenant-name,
            .qr-grid.row-view .tenant-phone,
            .qr-grid.row-view .card-actions {
                justify-content: center;
                margin-left: 0;
            }
        }

        .qr-table-wrap {
            display: none;
            width: 100%;
            overflow-x: auto;
            background: transparent !important;
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
            border-radius: 16px;
            padding: 0;
        }

        .qr-table-wrap.active {
            display: block;
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
        }

        .qr-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 980px;
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
            background: transparent !important;
        }

        .qr-table th,
        .qr-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #e2e8f0 !important;
            color: var(--apple-text);
            font-size: 0.95rem;
            text-align: left;
            vertical-align: middle;
            background: transparent !important;
        }

        .qr-table th {
            color: var(--apple-text-secondary);
            font-weight: 600;
            font-size: 0.86rem;
            white-space: nowrap;
        }

        .qr-table tr:last-child td {
            border-bottom: none;
        }

        .qr-table-room {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, var(--apple-blue), var(--apple-purple));
            color: #000000 !important;
            border-radius: 999px;
            padding: 6px 10px;
            font-weight: 600;
            font-size: 0.88rem;
            white-space: nowrap;
        }

        .qr-table-room,
        .qr-table-room * {
            color: #000000 !important;
        }

        .qr-table-room svg {
            width: 14px;
            height: 14px;
            stroke: #000000 !important;
            fill: none;
        }

        .qr-thumb {
            width: 72px;
            height: 72px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid rgba(148,163,184,0.35);
            background: #fff;
        }

        .qr-table-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .qr-table-actions .btn-card {
            padding: 8px 12px;
            font-size: 0.8rem;
        }

        .qr-card {
            background: var(--apple-card);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--apple-separator);
            border-radius: var(--apple-radius);
            padding: 24px;
            text-align: center;
            animation: fadeInUp 0.6s ease backwards;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            overflow: hidden;
        }

        /* Light Theme - QR Card Override */
        html.light-theme .qr-card,
        html.live-light .qr-card,
        body.live-light .qr-card {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08) !important;
        }
        
        html.light-theme .qr-card:hover,
        html.live-light .qr-card:hover,
        body.live-light .qr-card:hover {
            border-color: #007aff !important;
            box-shadow: 0 8px 30px rgba(0, 122, 255, 0.15) !important;
        }

        html.light-theme .tenant-name,
        html.live-light .tenant-name,
        body.live-light .tenant-name {
            color: #1e293b !important;
        }

        html.light-theme .tenant-phone,
        html.live-light .tenant-phone,
        body.live-light .tenant-phone {
            color: #64748b !important;
        }

        html.light-theme .stat-item,
        html.live-light .stat-item,
        body.live-light .stat-item {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
        }

        html.light-theme .stat-value,
        html.live-light .stat-value,
        body.live-light .stat-value {
            color: #1e293b !important;
        }

        html.light-theme .stat-label,
        html.live-light .stat-label,
        body.live-light .stat-label {
            color: #64748b !important;
        }

        html.light-theme .qr-header h1,
        html.live-light .qr-header h1,
        body.live-light .qr-header h1 {
            background: linear-gradient(135deg, #1e293b 0%, #475569 100%) !important;
            -webkit-background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
        }

        html.light-theme .qr-header p,
        html.live-light .qr-header p,
        body.live-light .qr-header p {
            color: #64748b !important;
        }

        html.light-theme .room-badge,
        html.live-light .room-badge,
        body.live-light .room-badge {
            color: white !important;
        }

        /* Force room-badge inline SVG to white in light modes */
        html.light-theme .room-badge svg,
        html.live-light .room-badge svg,
        body.live-light .room-badge svg,
        html.light-theme .room-badge svg *,
        html.live-light .room-badge svg *,
        body.live-light .room-badge svg * {
            stroke: #ffffff !important;
            fill: #ffffff !important;
        }

        html.light-theme .room-badge svg path,
        html.live-light .room-badge svg path,
        body.live-light .room-badge svg path {
            stroke: currentColor !important;
            fill: none !important;
        }

        html.light-theme .btn-apple.secondary,
        html.live-light .btn-apple.secondary,
        body.live-light .btn-apple.secondary {
            background: #ffffff !important;
            color: #1e293b !important;
            border: 1px solid #e2e8f0 !important;
        }

        html.light-theme .btn-apple.secondary:hover,
        html.live-light .btn-apple.secondary:hover,
        body.live-light .btn-apple.secondary:hover {
            border-color: #007aff !important;
            color: #007aff !important;
        }

        html.light-theme .btn-card.download,
        html.live-light .btn-card.download,
        body.live-light .btn-card.download {
            background: rgba(34, 197, 94, 0.1) !important;
            color: #16a34a !important;
        }

        html.light-theme .btn-card.print,
        html.live-light .btn-card.print,
        body.live-light .btn-card.print {
            background: rgba(0, 122, 255, 0.1) !important;
            color: #0066cc !important;
        }

        html.light-theme body.qr-page,
        html.live-light body.qr-page,
        body.live-light.qr-page {
            background: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 50%, #f8fafc 100%) !important;
        }

        .qr-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--apple-blue), var(--apple-purple), var(--apple-pink));
            opacity: 0;
            transition: opacity 0.3s;
        }

        .qr-card:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: var(--apple-blue);
            box-shadow: 0 20px 60px rgba(0, 122, 255, 0.2);
        }

        .qr-card:hover::before {
            opacity: 1;
        }

        .qr-card:nth-child(1) { animation-delay: 0.1s; }
        .qr-card:nth-child(2) { animation-delay: 0.15s; }
        .qr-card:nth-child(3) { animation-delay: 0.2s; }
        .qr-card:nth-child(4) { animation-delay: 0.25s; }
        .qr-card:nth-child(5) { animation-delay: 0.3s; }
        .qr-card:nth-child(6) { animation-delay: 0.35s; }
        .qr-card:nth-child(7) { animation-delay: 0.4s; }
        .qr-card:nth-child(8) { animation-delay: 0.45s; }

        .room-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--apple-blue), var(--apple-purple));
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 16px;
            box-shadow: 0 4px 15px rgba(0, 122, 255, 0.3);
            transition: all 0.3s;
        }

        .qr-card:hover .room-badge {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(0, 122, 255, 0.4);
        }

        .room-badge svg {
            width: 20px;
            height: 20px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }

        .room-badge svg path,
        .room-badge svg polyline,
        .room-badge svg line {
            stroke: currentColor;
            fill: none;
        }

        .tenant-info {
            margin-bottom: 20px;
        }

        .tenant-name {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--apple-text);
            margin-bottom: 8px;
        }

        .tenant-name svg {
            width: 18px;
            height: 18px;
            stroke: var(--apple-blue);
            stroke-width: 2;
            fill: none;
        }

        .tenant-phone {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 0.9rem;
            color: var(--apple-text-secondary);
        }

        .tenant-phone svg {
            width: 16px;
            height: 16px;
            stroke: var(--apple-green);
            stroke-width: 2;
            fill: none;
        }

        .qr-container {
            background: white;
            padding: 16px;
            border-radius: 16px;
            display: inline-block;
            margin-bottom: 20px;
            position: relative;
            transition: all 0.3s;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .qr-card:hover .qr-container {
            transform: scale(1.02);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
        }

        .qr-container img {
            display: block;
            width: 160px;
            height: 160px;
            border-radius: 8px;
        }

        .qr-container::before,
        .qr-container::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 3px solid var(--apple-blue);
            transition: all 0.3s;
        }

        .qr-container::before {
            top: 8px;
            left: 8px;
            border-right: none;
            border-bottom: none;
            border-radius: 8px 0 0 0;
        }

        .qr-container::after {
            bottom: 8px;
            right: 8px;
            border-left: none;
            border-top: none;
            border-radius: 0 0 8px 0;
        }

        .qr-card:hover .qr-container::before,
        .qr-card:hover .qr-container::after {
            border-color: var(--apple-purple);
        }

        .card-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
            width: 100%;
        }

        .btn-card {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 16px;
            border: none;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            font-family: inherit;
            max-width: 100%;
        }

        .qr-grid:not(.row-view) .card-actions .btn-card {
            flex: 1 1 110px;
            justify-content: center;
        }

        .btn-card svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }

        .btn-card.download {
            background: rgba(52, 199, 89, 0.15);
            color: var(--apple-green);
        }

        .btn-card.download:hover {
            background: var(--apple-green);
            color: white;
            transform: translateY(-2px);
        }

        .btn-card.print {
            background: rgba(0, 122, 255, 0.15);
            color: var(--apple-blue);
        }

        .btn-card.print:hover {
            background: var(--apple-blue);
            color: white;
            transform: translateY(-2px);
        }

        .btn-card.portal {
            background: rgba(168, 85, 247, 0.16);
            color: #a855f7;
        }

        .btn-card.portal:hover {
            background: #a855f7;
            color: #ffffff;
            transform: translateY(-2px);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            animation: fadeInUp 0.6s ease;
        }

        .empty-icon {
            width: 120px;
            height: 120px;
            margin: 0 auto 24px;
            background: var(--apple-card);
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: float 3s ease-in-out infinite;
        }

        .empty-icon svg {
            width: 60px;
            height: 60px;
            stroke: var(--apple-text-secondary);
            stroke-width: 1.5;
            fill: none;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            color: var(--apple-text);
            margin-bottom: 10px;
        }

        .empty-state p {
            color: var(--apple-text-secondary);
            font-size: 1rem;
        }

        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: var(--apple-blue);
            border-radius: 50%;
            opacity: 0.3;
            animation: floatParticle 15s infinite linear;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .app-main { padding: 0 !important; }
            .qr-wrapper { padding: 10px; width: 100%; }
            .qr-header, .stats-bar, .action-bar, .particles { display: none !important; }
            .qr-card {
                break-inside: avoid;
                page-break-inside: avoid;
                border: 1px solid #ccc !important;
                background: white !important;
                margin-bottom: 15px;
                box-shadow: none !important;
            }
            .qr-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 15px;
            }
            .room-badge {
                background: #007aff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .tenant-name, .tenant-phone { color: #333 !important; }
            .qr-container::before, .qr-container::after { display: none; }
        }

        /* ============ Light Theme ============ */
        html.light-theme body.qr-page,
        body.light-theme.qr-page,
        .light-theme .qr-page,
        .light-theme.qr-page {
            background: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 50%, #f8fafc 100%) !important;
        }

        html.light-theme .qr-header h1,
        body.light-theme .qr-header h1,
        .light-theme .qr-header h1 {
            background: linear-gradient(135deg, #1e293b 0%, #475569 100%) !important;
            -webkit-background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
            background-clip: text !important;
        }

        html.light-theme .qr-header p,
        body.light-theme .qr-header p,
        .light-theme .qr-header p {
            color: #64748b !important;
        }

        html.light-theme .stat-item,
        body.light-theme .stat-item,
        .light-theme .stat-item {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05) !important;
        }

        html.light-theme .stat-item:hover,
        body.light-theme .stat-item:hover,
        .light-theme .stat-item:hover {
            border-color: #007aff !important;
            box-shadow: 0 8px 25px rgba(0, 122, 255, 0.15) !important;
        }

        html.light-theme .stat-value,
        body.light-theme .stat-value,
        .light-theme .stat-value {
            color: #1e293b !important;
        }

        html.light-theme .stat-label,
        body.light-theme .stat-label,
        .light-theme .stat-label {
            color: #64748b !important;
        }

        html.light-theme .btn-apple.secondary,
        body.light-theme .btn-apple.secondary,
        .light-theme .btn-apple.secondary {
            background: #ffffff !important;
            color: #1e293b !important;
            border: 1px solid #e2e8f0 !important;
        }

        html.light-theme .btn-apple.secondary:hover,
        body.light-theme .btn-apple.secondary:hover,
        .light-theme .btn-apple.secondary:hover {
            background: #f8fafc !important;
            border-color: #007aff !important;
            color: #007aff !important;
        }

        html.light-theme .qr-card,
        body.light-theme .qr-card,
        .light-theme .qr-card {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06) !important;
        }

        html.light-theme .qr-card:hover,
        body.light-theme .qr-card:hover,
        .light-theme .qr-card:hover {
            border-color: #007aff !important;
            box-shadow: 0 12px 40px rgba(0, 122, 255, 0.15) !important;
            background: #ffffff !important;
        }

        html.light-theme .tenant-name,
        body.light-theme .tenant-name,
        .light-theme .tenant-name {
            color: #1e293b !important;
        }

        html.light-theme .tenant-phone,
        body.light-theme .tenant-phone,
        .light-theme .tenant-phone {
            color: #64748b !important;
        }

        html.light-theme .qr-container,
        body.light-theme .qr-container,
        .light-theme .qr-container {
            background: #ffffff !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08) !important;
        }

        html.light-theme .qr-card:hover .qr-container,
        body.light-theme .qr-card:hover .qr-container,
        .light-theme .qr-card:hover .qr-container {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12) !important;
        }

        html.light-theme .btn-card.download,
        body.light-theme .btn-card.download,
        .light-theme .btn-card.download {
            background: rgba(52, 199, 89, 0.12) !important;
            color: #22c55e !important;
        }

        html.light-theme .btn-card.download:hover,
        body.light-theme .btn-card.download:hover,
        .light-theme .btn-card.download:hover {
            background: #22c55e !important;
            color: white !important;
        }

        html.light-theme .btn-card.print,
        body.light-theme .btn-card.print,
        .light-theme .btn-card.print {
            background: rgba(0, 122, 255, 0.12) !important;
            color: #0066cc !important;
        }

        html.light-theme .btn-card.print:hover,
        body.light-theme .btn-card.print:hover,
        .light-theme .btn-card.print:hover {
            background: #0066cc !important;
            color: white !important;
        }

        html.light-theme .btn-card.portal,
        body.light-theme .btn-card.portal,
        .light-theme .btn-card.portal {
            background: rgba(168, 85, 247, 0.12) !important;
            color: #7e22ce !important;
        }

        html.light-theme .btn-card.portal:hover,
        body.light-theme .btn-card.portal:hover,
        .light-theme .btn-card.portal:hover {
            background: #7e22ce !important;
            color: #ffffff !important;
        }

        html.light-theme .empty-state h3,
        body.light-theme .empty-state h3,
        .light-theme .empty-state h3 {
            color: #1e293b !important;
        }

        html.light-theme .empty-state p,
        body.light-theme .empty-state p,
        .light-theme .empty-state p {
            color: #64748b !important;
        }

        html.light-theme .empty-icon,
        body.light-theme .empty-icon,
        .light-theme .empty-icon {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
        }

        html.light-theme .empty-icon svg,
        body.light-theme .empty-icon svg,
        .light-theme .empty-icon svg {
            stroke: #64748b !important;
        }

        html.light-theme .particle,
        body.light-theme .particle,
        .light-theme .particle {
            background: #007aff !important;
            opacity: 0.15 !important;
        }

        html.light-theme .card-actions,
        body.light-theme .card-actions,
        .light-theme .card-actions {
            background: transparent !important;
        }
    </style>
    <script>
    // QR Code Download Functions - defined in head to ensure availability
    function downloadQR(imageUrl, roomNumber) {
        fetch(imageUrl)
            .then(function(response) {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.blob();
            })
            .then(function(blob) {
                var url = window.URL.createObjectURL(blob);
                var link = document.createElement('a');
                link.style.display = 'none';
                link.href = url;
                link.download = 'QR_Room_' + roomNumber + '.png';
                document.body.appendChild(link);
                link.click();
                setTimeout(function() {
                    document.body.removeChild(link);
                    window.URL.revokeObjectURL(url);
                }, 100);
            })
            .catch(function(err) {
                console.error('Download failed:', err);
                window.open(imageUrl, '_blank');
            });
    }

    function downloadAll() {
        var cards = document.querySelectorAll('.qr-card');
        var delay = 0;
        cards.forEach(function(card) {
            var img = card.querySelector('.qr-container img');
            var roomBadge = card.querySelector('.room-badge');
            var roomNumber = roomBadge.textContent.replace('ห้อง', '').trim();
            setTimeout(function() {
                downloadQR(img.src, roomNumber);
            }, delay);
            delay += 500;
        });
    }

    function printAll() {
        var cards = document.querySelectorAll('.qr-card');
        if (!cards.length) {
            window.print();
            return;
        }

        var htmlCards = '';
        cards.forEach(function(card) {
            var roomBadge = card.querySelector('.room-badge');
            var tenantNameEl = card.querySelector('.tenant-name');
            var qrImg = card.querySelector('.qr-container img');
            if (!roomBadge || !tenantNameEl || !qrImg) return;

            var roomText = roomBadge.textContent.trim();
            var tenantText = tenantNameEl.textContent.trim();
            var imageUrl = qrImg.getAttribute('src') || '';

            htmlCards +=
                '<div class="print-item">' +
                    '<div class="print-room">' + roomText + '</div>' +
                    '<div class="print-tenant">' + tenantText + '</div>' +
                    '<img src="' + imageUrl + '" class="print-qr" alt="QR">' +
                '</div>';
        });

        var printWindow = window.open('', '_blank');
        if (!printWindow) {
            window.print();
            return;
        }

        printWindow.document.write(
            '<!DOCTYPE html>' +
            '<html><head><meta charset="utf-8">' +
            '<title>พิมพ์ QR Code ทั้งหมด</title>' +
            '<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700&display=swap" rel="stylesheet">' +
            '<style>' +
            '*{box-sizing:border-box;} body{margin:0;padding:16px;font-family:"Prompt",sans-serif;background:#fff;color:#111827;}' +
            '.print-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;}' +
            '.print-item{border:1px solid #d1d5db;border-radius:14px;padding:14px;text-align:center;break-inside:avoid;page-break-inside:avoid;}' +
            '.print-room{display:inline-block;background:linear-gradient(135deg,#2563eb,#7c3aed);color:#fff;padding:6px 14px;border-radius:999px;font-weight:700;font-size:14px;margin-bottom:8px;}' +
            '.print-tenant{font-size:14px;font-weight:600;color:#1f2937;margin-bottom:10px;}' +
            '.print-qr{width:170px;height:170px;object-fit:contain;border-radius:8px;}' +
            '@page{size:A4 portrait;margin:10mm;}' +
            '@media print{body{padding:0;} .print-grid{gap:12px;} .print-item{border:1px solid #cbd5e1;}}' +
            '</style></head><body>' +
            '<div class="print-grid">' + htmlCards + '</div>' +
            '<script>window.onload=function(){setTimeout(function(){window.print();window.close();},350);};<\/script>' +
            '</body></html>'
        );
        printWindow.document.close();
    }

    function openQrTarget(url) {
        if (!url) return;
        window.open(url, '_blank', 'noopener,noreferrer');
    }

    function printSingleQR(imageUrl, roomNumber, tenantName) {
        var printWindow = window.open('', '_blank');
        printWindow.document.write(
            '<!DOCTYPE html>' +
            '<html>' +
            '<head>' +
            '<title>QR Code ห้อง ' + roomNumber + '</title>' +
            '<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700&display=swap" rel="stylesheet">' +
            '<style>' +
            '* { margin: 0; padding: 0; box-sizing: border-box; }' +
            'body { font-family: "Prompt", sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #f5f5f7; }' +
            '.print-card { background: white; border-radius: 20px; padding: 40px; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }' +
            '.room-badge { display: inline-block; background: linear-gradient(135deg, #007aff, #5856d6); color: white; padding: 12px 30px; border-radius: 30px; font-size: 1.5rem; font-weight: 700; margin-bottom: 15px; }' +
            '.tenant-name { font-size: 1.2rem; font-weight: 600; color: #1d1d1f; margin-bottom: 20px; }' +
            '.qr-image { width: 200px; height: 200px; border-radius: 12px; }' +
            '.instructions { margin-top: 20px; font-size: 0.95rem; color: #86868b; }' +
            '@media print { body { background: white; } .print-card { box-shadow: none; border: 2px solid #e5e5e5; } }' +
            '</style>' +
            '</head>' +
            '<body>' +
            '<div class="print-card">' +
            '<div class="room-badge">ห้อง ' + roomNumber + '</div>' +
            '<div class="tenant-name">' + tenantName + '</div>' +
            '<img src="' + imageUrl + '" class="qr-image" alt="QR Code">' +
            '<div class="instructions">สแกน QR Code เพื่อเข้าสู่ระบบผู้เช่า</div>' +
            '</div>' +
            '<script>window.onload = function() { setTimeout(function() { window.print(); window.close(); }, 500); };<\/script>' +
            '</body>' +
            '</html>'
        );
        printWindow.document.close();
    }
    </script>
</head>
<body class="reports-page qr-page <?php echo $lightThemeClass; ?>">
    <div class="particles no-print">
        <?php for ($i = 0; $i < 20; $i++): ?>
        <div class="particle" style="left: <?php echo rand(0, 100); ?>%; animation-delay: <?php echo rand(0, 15); ?>s; animation-duration: <?php echo rand(12, 20); ?>s;"></div>
        <?php endfor; ?>
    </div>

    <div class="app-shell">
        <?php 
        $currentPage = 'qr_codes.php';
        include __DIR__ . '/../includes/sidebar.php'; 
        ?>
        <div class="app-main">
            <main>
                <?php $pageTitle = 'จัดการ QR Code ผู้เช่า'; include __DIR__ . '/../includes/page_header.php'; ?>
                <div class="qr-wrapper">
                    <div class="qr-header">
                        <div class="qr-header-icon">
                            <svg viewBox="0 0 24 24">
                                <rect x="3" y="3" width="7" height="7" rx="1"/>
                                <rect x="14" y="3" width="7" height="7" rx="1"/>
                                <rect x="3" y="14" width="7" height="7" rx="1"/>
                                <rect x="14" y="14" width="3" height="3"/>
                                <rect x="18" y="14" width="3" height="3"/>
                                <rect x="14" y="18" width="3" height="3"/>
                                <rect x="18" y="18" width="3" height="3"/>
                            </svg>
                        </div>
                        <h1>QR Code ผู้เช่า</h1>
                        <p>สร้างและจัดการ QR Code สำหรับเข้าสู่ระบบผู้เช่า</p>
                    </div>

                    <div class="stats-bar no-print">
                        <div class="stat-item">
                            <div class="stat-icon blue">
                                <svg viewBox="0 0 24 24">
                                    <rect x="3" y="3" width="7" height="7" rx="1"/>
                                    <rect x="14" y="3" width="7" height="7" rx="1"/>
                                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                                    <path d="M14 14h7v7h-7z"/>
                                </svg>
                            </div>
                            <div class="stat-value"><?php echo $totalContracts; ?></div>
                            <div class="stat-label">QR Code ทั้งหมด</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon green">
                                <svg viewBox="0 0 24 24">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                            </div>
                            <div class="stat-value"><?php echo $totalContracts; ?></div>
                            <div class="stat-label">ผู้เช่าใช้งาน</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon purple">
                                <svg viewBox="0 0 24 24">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                    <polyline points="22 4 12 14.01 9 11.01"/>
                                </svg>
                            </div>
                            <div class="stat-value">100%</div>
                            <div class="stat-label">พร้อมใช้งาน</div>
                        </div>
                    </div>

                    <?php if (!empty($contracts)): ?>
                    <div class="action-bar no-print">
                        <div class="view-toggle-group" role="group" aria-label="สลับมุมมอง">
                            <button class="btn-apple secondary view-toggle-btn active" id="viewListBtn" type="button" onclick="setQrView('list')" aria-pressed="true">
                                <svg viewBox="0 0 24 24">
                                    <line x1="8" y1="6" x2="21" y2="6"/>
                                    <line x1="8" y1="12" x2="21" y2="12"/>
                                    <line x1="8" y1="18" x2="21" y2="18"/>
                                    <line x1="3" y1="6" x2="3.01" y2="6"/>
                                    <line x1="3" y1="12" x2="3.01" y2="12"/>
                                    <line x1="3" y1="18" x2="3.01" y2="18"/>
                                </svg>
                                List
                            </button>
                            <button class="btn-apple secondary view-toggle-btn" id="viewGridBtn" type="button" onclick="setQrView('grid')" aria-pressed="false">
                                <svg viewBox="0 0 24 24">
                                    <rect x="3" y="3" width="7" height="7" rx="1"/>
                                    <rect x="14" y="3" width="7" height="7" rx="1"/>
                                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                                    <rect x="14" y="14" width="7" height="7" rx="1"/>
                                </svg>
                                Grid
                            </button>
                        </div>
                        <button class="btn-apple primary" onclick="printAll()" style="color: white;">
                            <svg viewBox="0 0 24 24" style="stroke: white; fill: none;">
                                <polyline points="6 9 6 2 18 2 18 9"/>
                                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                                <rect x="6" y="14" width="12" height="8"/>
                            </svg>
                            พิมพ์ทั้งหมด
                        </button>
                        <button class="btn-apple secondary" onclick="downloadAll()">
                            <svg viewBox="0 0 24 24">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="7 10 12 15 17 10"/>
                                <line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                            ดาวน์โหลดทั้งหมด
                        </button>
                    </div>

                    <div class="qr-grid row-view" id="qrListView">
                        <?php foreach ($contracts as $index => $contract): ?>
                        <?php 
                            $tenantUrl = $baseUrl . '?token=' . urlencode($contract['access_token']);
                            $qrId = 'qr_' . $contract['ctr_id'];
                            $qrImageUrl = '../qr_generate.php?data=' . urlencode($tenantUrl);
                        ?>
                        <div class="qr-card" style="animation-delay: <?php echo ($index % 8) * 0.05; ?>s;">
                            <div class="room-badge">
                                <svg viewBox="0 0 24 24" stroke="#ffffff" fill="none">
                                    <path stroke="#ffffff" d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                    <polyline stroke="#ffffff" points="9 22 9 12 15 12 15 22"/>
                                </svg>
                                ห้อง <?php echo htmlspecialchars($contract['room_number']); ?>
                            </div>
                            
                            <div class="tenant-info">
                                <div class="tenant-name">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                        <circle cx="12" cy="7" r="4"/>
                                    </svg>
                                    <?php echo htmlspecialchars($contract['tnt_name']); ?>
                                </div>
                                <div class="tenant-phone">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                    </svg>
                                    <?php echo htmlspecialchars($contract['tnt_phone'] ?? '-'); ?>
                                </div>
                            </div>
                            
                            <div class="qr-container">
                                <img id="<?php echo $qrId; ?>" src="<?php echo $qrImageUrl; ?>" alt="QR Code ห้อง <?php echo htmlspecialchars($contract['room_number']); ?>">
                            </div>
                            
                            <div class="card-actions no-print">
                                <button class="btn-card portal" onclick="openQrTarget('<?php echo htmlspecialchars($tenantUrl, ENT_QUOTES, 'UTF-8'); ?>')">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M14 3h7v7"/>
                                        <path d="M10 14L21 3"/>
                                        <path d="M21 14v7h-7"/>
                                        <path d="M3 10V3h7"/>
                                        <path d="M3 21h7v-7"/>
                                    </svg>
                                    เข้าหน้าผู้เช่า
                                </button>
                                <button class="btn-card download" onclick="downloadQR('<?php echo $qrImageUrl; ?>', '<?php echo $contract['room_number']; ?>')">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                        <polyline points="7 10 12 15 17 10"/>
                                        <line x1="12" y1="15" x2="12" y2="3"/>
                                    </svg>
                                    ดาวน์โหลด
                                </button>
                                <button class="btn-card print" onclick="printSingleQR('<?php echo $qrImageUrl; ?>', '<?php echo htmlspecialchars($contract['room_number']); ?>', '<?php echo htmlspecialchars(addslashes($contract['tnt_name'])); ?>')">
                                    <svg viewBox="0 0 24 24">
                                        <polyline points="6 9 6 2 18 2 18 9"/>
                                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                                        <rect x="6" y="14" width="12" height="8"/>
                                    </svg>
                                    พิมพ์
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="qr-table-wrap" id="qrTableView">
                        <table class="qr-table">
                            <thead>
                                <tr>
                                    <th>ห้อง</th>
                                    <th>ผู้เช่า</th>
                                    <th>เบอร์โทร</th>
                                    <th>QR</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contracts as $contract): ?>
                                <?php 
                                    $tenantUrl = $baseUrl . '?token=' . urlencode($contract['access_token']);
                                    $qrImageUrl = '../qr_generate.php?data=' . urlencode($tenantUrl);
                                ?>
                                <tr>
                                    <td>
                                        <span class="qr-table-room">
                                            <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                                            ห้อง <?php echo htmlspecialchars($contract['room_number']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($contract['tnt_name']); ?></td>
                                    <td><?php echo htmlspecialchars($contract['tnt_phone'] ?? '-'); ?></td>
                                    <td>
                                        <img class="qr-thumb" src="<?php echo $qrImageUrl; ?>" alt="QR ห้อง <?php echo htmlspecialchars($contract['room_number']); ?>">
                                    </td>
                                    <td>
                                        <div class="qr-table-actions no-print">
                                            <button class="btn-card portal" onclick="openQrTarget('<?php echo htmlspecialchars($tenantUrl, ENT_QUOTES, 'UTF-8'); ?>')">
                                                <svg viewBox="0 0 24 24"><path d="M14 3h7v7"/><path d="M10 14L21 3"/><path d="M21 14v7h-7"/><path d="M3 10V3h7"/><path d="M3 21h7v-7"/></svg>
                                                เข้าหน้าผู้เช่า
                                            </button>
                                            <button class="btn-card download" onclick="downloadQR('<?php echo $qrImageUrl; ?>', '<?php echo $contract['room_number']; ?>')">
                                                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                                ดาวน์โหลด
                                            </button>
                                            <button class="btn-card print" onclick="printSingleQR('<?php echo $qrImageUrl; ?>', '<?php echo htmlspecialchars($contract['room_number']); ?>', '<?php echo htmlspecialchars(addslashes($contract['tnt_name'])); ?>')">
                                                <svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                                พิมพ์
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <svg viewBox="0 0 24 24">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                <line x1="9" y1="9" x2="15" y2="15"/>
                                <line x1="15" y1="9" x2="9" y2="15"/>
                            </svg>
                        </div>
                        <h3>ไม่มี QR Code</h3>
                        <p>กรุณาเพิ่มสัญญาเช่าก่อนสร้าง QR Code</p>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <script>
        function setQrView(view) {
            var listView = document.getElementById('qrListView');
            var tableView = document.getElementById('qrTableView');
            var listBtn = document.getElementById('viewListBtn');
            var gridBtn = document.getElementById('viewGridBtn');

            var safeView = (view === 'grid' || view === 'list') ? view : 'list';
            var isTableView = false;
            var isListView = (safeView === 'list');
            var isGridView = (safeView === 'grid');

            if (listView) {
                listView.classList.toggle('hidden', isTableView);
                listView.classList.toggle('row-view', isListView);
            }
            if (tableView) tableView.classList.toggle('active', isTableView);

            if (listBtn) listBtn.classList.toggle('active', isListView);
            if (gridBtn) gridBtn.classList.toggle('active', isGridView);

            if (listBtn) listBtn.setAttribute('aria-pressed', isListView ? 'true' : 'false');
            if (gridBtn) gridBtn.setAttribute('aria-pressed', isGridView ? 'true' : 'false');

            try {
                localStorage.setItem('qrViewMode', safeView);
            } catch (e) {}
        }

        // Ensure badge SVG icons render white in Light Theme (runtime override)
        (function(){
            function applyWhiteToBadgeSVGs() {
                const isLight = document.documentElement.classList.contains('light-theme') || document.documentElement.classList.contains('live-light') || document.body.classList.contains('live-light');
                document.querySelectorAll('.room-badge svg').forEach(svg => {
                    try {
                        if (isLight) {
                            svg.setAttribute('stroke', '#ffffff');
                            svg.setAttribute('fill', 'none');
                            svg.style.setProperty('stroke', '#ffffff', 'important');
                            svg.style.setProperty('fill', 'none', 'important');
                            svg.querySelectorAll('*').forEach(el => {
                                el.setAttribute('stroke', '#ffffff');
                                el.setAttribute('fill', 'none');
                                el.style.setProperty('stroke', '#ffffff', 'important');
                                el.style.setProperty('fill', 'none', 'important');
                            });
                        }
                    } catch (e) {
                        // fail silently
                    }
                });
            }

            document.addEventListener('DOMContentLoaded', applyWhiteToBadgeSVGs);

            document.addEventListener('DOMContentLoaded', function() {
                var saved = 'list';
                try {
                    saved = localStorage.getItem('qrViewMode') || '<?php echo $defaultQrView; ?>';
                } catch (e) {
                    saved = '<?php echo $defaultQrView; ?>';
                }
                if (saved !== 'list' && saved !== 'grid') {
                    saved = 'list';
                }
                setQrView(saved);
            });

            // Watch for theme class changes (system-settings.js may toggle classes later)
            const obs = new MutationObserver(muts => {
                for (const m of muts) {
                    if (m.type === 'attributes' && (m.attributeName === 'class')) {
                        applyWhiteToBadgeSVGs();
                        break;
                    }
                }
            });
            obs.observe(document.documentElement, { attributes: true });
            obs.observe(document.body, { attributes: true });
        })();
    </script>
    <script src="/dormitory_management/Public/Assets/Javascript/animate-ui.js" defer></script>
    <script src="/dormitory_management/Public/Assets/Javascript/main.js" defer></script>
</body>
</html>
