<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/ConnectDB.php';

$pdo = connectDB();

// ดึงค่าตั้งค่าระบบ
$siteName = 'Sangthian Dormitory';
$logoFilename = 'Logo.jpg';
$themeColor = '#1e40af';
$bgFilename = 'bg.jpg';
$contactPhone = '0895656083';
$contactEmail = 'test@gmail.com';
$publicTheme = 'dark';
$useBgImage = '0';
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename', 'theme_color', 'bg_filename', 'contact_phone', 'contact_email', 'public_theme', 'use_bg_image')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
        if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
        if ($row['setting_key'] === 'theme_color') $themeColor = $row['setting_value'];
        if ($row['setting_key'] === 'bg_filename') $bgFilename = $row['setting_value'];
        if ($row['setting_key'] === 'contact_phone') $contactPhone = $row['setting_value'];
        if ($row['setting_key'] === 'contact_email') $contactEmail = $row['setting_value'];
        if ($row['setting_key'] === 'public_theme') $publicTheme = $row['setting_value'];
        if ($row['setting_key'] === 'use_bg_image') $useBgImage = $row['setting_value'];
    }
} catch (PDOException $e) {}

// ดึงข้อมูลห้องว่าง (room_status = '0' คือห้องว่าง)
$availableRooms = [];
try {
    $stmt = $pdo->query("
        SELECT r.*, rt.type_name, rt.type_price 
        FROM room r 
        LEFT JOIN roomtype rt ON r.type_id = rt.type_id 
        WHERE r.room_status = '0' 
        ORDER BY CAST(r.room_number AS UNSIGNED) ASC
        LIMIT 6
    ");
    $availableRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ดึงข่าวประชาสัมพันธ์ (ดึงทั้งหมดเรียงตามวันที่)
$news = [];
try {
    $stmt = $pdo->query("SELECT * FROM news ORDER BY news_date DESC LIMIT 4");
    $news = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // debug: echo $e->getMessage();
}

// นับจำนวนห้องว่าง (room_status = '0' คือห้องว่าง)
$roomStats = ['total' => 0, 'available' => 0];
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM room");
    $roomStats['total'] = (int)$stmt->fetchColumn();
    $stmt = $pdo->query("SELECT COUNT(*) as available FROM room WHERE room_status = '0'");
    $roomStats['available'] = (int)$stmt->fetchColumn();
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteName); ?> - หอพักคุณภาพ</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><defs><linearGradient id='grad' x1='0%' y1='0%' x2='100%' y2='100%'><stop offset='0%' style='stop-color:%23667eea;stop-opacity:1' /><stop offset='100%' style='stop-color:%23764ba2;stop-opacity:1' /></linearGradient><style>.house { animation: draw 1s ease-in-out forwards; stroke-dasharray: 200; } @keyframes draw { to { stroke-dashoffset: 0; } }</style></defs><rect width='100' height='100' fill='white'/><g class='house' fill='none' stroke='url(%23grad)' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'><path d='M20 60 L50 25 L80 60 Z'/><rect x='25' y='60' width='50' height='35' rx='3'/><rect x='35' y='70' width='12' height='15'/><rect x='53' y='70' width='12' height='15'/><rect x='44' y='80' width='12' height='15'/></g></svg>" />
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="/dormitory_management/Public/Assets/Javascript/public_theme_toggle.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: <?php echo $themeColor; ?>;
            --primary-glow: <?php echo $themeColor; ?>40;
            --bg-dark: #0a0a0f;
            --bg-card: rgba(15, 23, 42, 0.8);
            --text-primary: #ffffff;
            --text-secondary: #94a3b8;
            --border-color: rgba(255, 255, 255, 0.1);
            --glass-bg: rgba(255, 255, 255, 0.03);
            <?php if (!empty($useBgImage) && $useBgImage === '1' && !empty($bgFilename)): ?>
            --bg-image: url('/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($bgFilename); ?>');
            <?php else: ?>
            --bg-image: none;
            <?php endif; ?>
        }
        
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

        /* ===== Modern Scrollbar (Public pages) ===== */
        html, body {
            scrollbar-width: thin; /* Firefox */
            scrollbar-color: #8b5cf6 transparent;
        }

        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #a78bfa, #8b5cf6);
            border-radius: 999px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.35);
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, #c4b5fd, #a78bfa);
        }

        /* ===== ANIMATED BACKGROUND ===== */
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

        /* Particles */
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

        /* Grid Lines */
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

        /* ===== HEADER ===== */
        .header {
            background: rgba(10, 10, 15, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 1rem 2rem;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .header.scrolled {
            padding: 0.75rem 2rem;
            background: rgba(10, 10, 15, 0.95);
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .logo img {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .logo img:hover {
            border-color: var(--primary);
            box-shadow: 0 0 20px var(--primary-glow);
        }
        
        .logo h1 {
            font-size: 1.5rem;
            color: #fff;
            background: linear-gradient(135deg, #fff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .nav-links {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .nav-links a {
            color: var(--text-secondary);
            text-decoration: none;
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }

        .nav-links a::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--primary);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }
        
        .nav-links a:hover {
            color: #fff;
            background: var(--glass-bg);
        }

        .nav-links a:hover::before {
            width: 60%;
        }

        .nav-icon {
            width: 18px;
            height: 18px;
            vertical-align: middle;
            margin-right: 4px;
            transition: all 0.3s ease;
        }

        .nav-links a:hover .nav-icon {
            transform: scale(1.2);
            filter: drop-shadow(0 0 8px currentColor);
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--primary), #1d4ed8) !important;
            color: #fff !important;
            padding: 0.75rem 1.5rem !important;
            border-radius: 10px;
            font-weight: 600;
            box-shadow: 0 4px 20px var(--primary-glow);
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px var(--primary-glow);
        }

        .btn-login::before {
            display: none !important;
        }
        
        /* Google Login Button */
        .btn-login.btn-google {
            background: linear-gradient(135deg, #4285F4, #34A853) !important;
            box-shadow: 0 4px 20px rgba(66, 133, 244, 0.3);
        }
        
        .btn-login.btn-google:hover {
            box-shadow: 0 8px 30px rgba(66, 133, 244, 0.5);
        }
        
        .btn-login.btn-google svg {
            width: 18px;
            height: 18px;
        }
        
        /* Logout Button */
        .btn-login.btn-logout {
            background: linear-gradient(135deg, #ef4444, #dc2626) !important;
            box-shadow: 0 4px 20px rgba(239, 68, 68, 0.3);
        }
        
        .btn-login.btn-logout:hover {
            box-shadow: 0 8px 30px rgba(239, 68, 68, 0.5);
        }
        
        /* User Avatar Dropdown */
        .user-avatar-container {
            position: relative;
            display: inline-block;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
            object-fit: cover;
        }
        
        .user-avatar:hover {
            border-color: rgba(255, 255, 255, 0.8);
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }
        
        .avatar-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: rgba(17, 24, 39, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            min-width: 200px;
            padding: 8px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
        }
        
        .avatar-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .avatar-dropdown::before {
            content: '';
            position: absolute;
            top: -6px;
            right: 15px;
            width: 12px;
            height: 12px;
            background: rgba(17, 24, 39, 0.95);
            border-left: 1px solid rgba(255, 255, 255, 0.1);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            transform: rotate(45deg);
        }
        
        .dropdown-header {
            padding: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 8px;
        }
        
        .dropdown-header .user-name {
            font-weight: 600;
            color: #fff;
            font-size: 14px;
            margin-bottom: 4px;
        }
        
        .dropdown-header .user-email {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-size: 14px;
        }
        
        .dropdown-item:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        
        .dropdown-item svg {
            width: 18px;
            height: 18px;
        }

        /* ===== HERO SECTION ===== */
        .hero {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 8rem 2rem 4rem;
            text-align: center;
            position: relative;
            z-index: 10;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--glass-bg);
            border: 1px solid var(--border-color);
            border-radius: 50px;
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            animation: fadeInDown 0.8s ease;
        }

        .hero-badge .dot {
            width: 8px;
            height: 8px;
            background: #22c55e;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.2); }
        }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .hero h2 {
            font-size: clamp(2.5rem, 6vw, 4.5rem);
            margin-bottom: 1.5rem;
            line-height: 1.1;
            animation: fadeInUp 0.8s ease 0.2s both;
        }

        .hero h2 .gradient-text {
            background: linear-gradient(135deg, #60a5fa 0%, #a78bfa 50%, #f472b6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            background-size: 200% auto;
            animation: gradientShift 5s ease infinite;
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% center; }
            50% { background-position: 100% center; }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .hero p {
            font-size: 1.25rem;
            color: var(--text-secondary);
            margin-bottom: 2.5rem;
            max-width: 650px;
            line-height: 1.8;
            animation: fadeInUp 0.8s ease 0.4s both;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeInUp 0.8s ease 0.6s both;
        }
        
        .btn {
            padding: 1rem 2rem;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-icon {
            width: 20px;
            height: 20px;
            vertical-align: middle;
            margin-right: 8px;
            transition: all 0.3s ease;
        }

        .btn:hover .btn-icon {
            transform: scale(1.15) rotate(-5deg);
            filter: drop-shadow(0 0 10px currentColor);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #1d4ed8);
            color: #fff;
            box-shadow: 0 4px 20px var(--primary-glow);
        }
        
        .btn-primary:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 10px 40px var(--primary-glow);
        }
        
        .btn-secondary {
            background: var(--glass-bg);
            color: #fff;
            border: 1px solid var(--border-color);
            backdrop-filter: blur(10px);
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--primary);
            transform: translateY(-3px);
        }
        
        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 4rem;
            margin-top: 4rem;
            animation: fadeInUp 0.8s ease 0.8s both;
        }
        
        .stat-box {
            text-align: center;
            position: relative;
        }

        .stat-box::after {
            content: '';
            position: absolute;
            right: -2rem;
            top: 50%;
            transform: translateY(-50%);
            width: 1px;
            height: 50px;
            background: linear-gradient(to bottom, transparent, var(--border-color), transparent);
        }

        .stat-box:last-child::after {
            display: none;
        }
        
        .stat-box .number {
            font-size: 3.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #60a5fa, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
        }
        
        .stat-box .label {
            color: var(--text-secondary);
            font-size: 1rem;
            margin-top: 0.5rem;
        }

        /* Scroll Indicator */
        .scroll-indicator {
            position: absolute;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.85rem;
            animation: bounce 2s infinite;
        }

        .scroll-indicator .mouse {
            width: 24px;
            height: 38px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            position: relative;
        }

        .scroll-indicator .mouse::before {
            content: '';
            position: absolute;
            top: 8px;
            left: 50%;
            transform: translateX(-50%);
            width: 4px;
            height: 8px;
            background: var(--primary);
            border-radius: 2px;
            animation: scroll 2s infinite;
        }

        @keyframes scroll {
            0%, 100% { top: 8px; opacity: 1; }
            50% { top: 18px; opacity: 0.3; }
        }

        @keyframes bounce {
            0%, 100% { transform: translateX(-50%) translateY(0); }
            50% { transform: translateX(-50%) translateY(10px); }
        }

        /* ===== SECTIONS ===== */
        .section {
            padding: 6rem 2rem;
            max-width: 1400px;
            margin: 0 auto;
            position: relative;
            z-index: 10;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-title .label {
            display: inline-block;
            padding: 0.4rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 50px;
            font-size: 0.85rem;
            color: #ffffff;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .section-title h3 {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
            background: linear-gradient(135deg, #fff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title h2 {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
            background: linear-gradient(135deg, #fff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section-icon {
            width: 40px;
            height: 40px;
            flex-shrink: 0;
            animation: floatIcon 3s ease-in-out infinite;
            filter: drop-shadow(0 0 10px rgba(96, 165, 250, 0.5));
        }
        
        .section-title p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        /* ===== QUICK LINKS ===== */
        .quick-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .quick-link-card {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2.5rem;
            text-align: center;
            border: 1px solid var(--border-color);
            text-decoration: none;
            color: inherit;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }

        .quick-link-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), #a78bfa);
            transform: scaleX(0);
            transition: transform 0.4s ease;
        }

        .quick-link-card::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.1) 0%, transparent 60%);
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        
        .quick-link-card:hover {
            transform: translateY(-10px) scale(1.02);
            border-color: var(--primary);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3), 0 0 40px var(--primary-glow);
        }

        .quick-link-card:hover::before {
            transform: scaleX(1);
        }

        .quick-link-card:hover::after {
            opacity: 1;
        }
        
        .quick-link-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, var(--glass-bg), rgba(59, 130, 246, 0.1));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            border: 1px solid var(--border-color);
            transition: all 0.4s ease;
        }

        .quick-link-card:hover .quick-link-icon {
            transform: scale(1.1) rotate(5deg);
            border-color: var(--primary);
            box-shadow: 0 0 30px var(--primary-glow);
        }

        .quick-link-card:hover .quick-link-icon svg {
            transform: scale(1.1);
        }

        .quick-link-icon svg {
            width: 40px;
            height: 40px;
            transition: all 0.4s ease;
        }

        /* Animated Icon Styles */
        .animated-icon {
            position: relative;
        }

        .animated-icon .icon-glow {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 60px;
            height: 60px;
            background: radial-gradient(circle, var(--primary-glow) 0%, transparent 70%);
            border-radius: 50%;
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .quick-link-card:hover .animated-icon .icon-glow {
            opacity: 1;
            animation: iconPulse 1.5s ease-in-out infinite;
        }

        @keyframes iconPulse {
            0%, 100% { transform: translate(-50%, -50%) scale(1); opacity: 0.5; }
            50% { transform: translate(-50%, -50%) scale(1.3); opacity: 0.8; }
        }

        /* SVG Icon Animations */
        @keyframes floatIcon {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        @keyframes rotateIcon {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes scaleIcon {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        @keyframes drawIcon {
            0% { stroke-dashoffset: 100; }
            100% { stroke-dashoffset: 0; }
        }

        .icon-float {
            animation: floatIcon 3s ease-in-out infinite;
        }

        .icon-scale {
            animation: scaleIcon 2s ease-in-out infinite;
        }

        .quick-link-card:hover .icon-draw path {
            stroke-dasharray: 100;
            animation: drawIcon 1s ease forwards;
        }
        
        .quick-link-card h4 {
            font-size: 1.4rem;
            margin-bottom: 0.75rem;
            color: #fff;
        }
        
        .quick-link-card p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* ===== WEB3 ROOM CARDS ===== */
        
        /* Animations */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes breathe {
            0%, 100% { 
                transform: scale(1) translateY(0);
                filter: brightness(1);
            }
            50% { 
                transform: scale(1.008) translateY(-3px);
                filter: brightness(1.05);
            }
        }
        
        @keyframes floatOrbit {
            0% { transform: translateY(0) translateX(0) rotate(0deg); }
            25% { transform: translateY(-6px) translateX(3px) rotate(0.5deg); }
            50% { transform: translateY(-2px) translateX(6px) rotate(0deg); }
            75% { transform: translateY(-8px) translateX(3px) rotate(-0.5deg); }
            100% { transform: translateY(0) translateX(0) rotate(0deg); }
        }
        
        @keyframes auroraGlow {
            0%, 100% { 
                background-position: 0% 50%;
                filter: hue-rotate(0deg) blur(15px);
            }
            25% { 
                background-position: 50% 100%;
                filter: hue-rotate(30deg) blur(18px);
            }
            50% { 
                background-position: 100% 50%;
                filter: hue-rotate(60deg) blur(20px);
            }
            75% { 
                background-position: 50% 0%;
                filter: hue-rotate(30deg) blur(18px);
            }
        }
        
        @keyframes pulseRing {
            0% { transform: scale(1); opacity: 0.8; }
            50% { transform: scale(1.05); opacity: 0.4; }
            100% { transform: scale(1); opacity: 0.8; }
        }
        
        @keyframes particleFloat {
            0%, 100% { 
                transform: translateY(0) translateX(0) scale(1); 
                opacity: 0.7; 
            }
            25% { 
                transform: translateY(-15px) translateX(10px) scale(1.1); 
                opacity: 1; 
            }
            50% { 
                transform: translateY(-25px) translateX(5px) scale(0.9); 
                opacity: 0.8; 
            }
            75% { 
                transform: translateY(-10px) translateX(-5px) scale(1.05); 
                opacity: 0.9; 
            }
        }
        
        @keyframes iconBounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        
        .room-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 2.5rem;
        }
        
        @media (min-width: 1200px) {
            .room-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .room-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 1.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .room-grid {
                grid-template-columns: 1fr;
                max-width: 280px;
                margin-left: auto;
                margin-right: auto;
                gap: 1.5rem;
            }
        }

        /* Room Card - Web3 Style */
        .room-card {
            position: relative;
            border-radius: 24px;
            background: transparent;
            box-shadow: 0 15px 35px rgba(3,7,18,0.4);
            color: #f5f8ff;
            aspect-ratio: 1137 / 1606;
            min-height: 280px;
            transition: transform 0.5s cubic-bezier(0.23, 1, 0.32, 1), filter 0.3s ease;
            will-change: transform, filter;
            animation: fadeInUp 0.6s ease-out backwards,
                       breathe 4s ease-in-out infinite,
                       floatOrbit 12s ease-in-out infinite;
            overflow: visible;
            perspective: 1000px;
        }
        
        .room-card:nth-child(odd) {
            animation: fadeInUp 0.6s ease-out backwards,
                       breathe 4.5s ease-in-out infinite,
                       floatOrbit 14s ease-in-out infinite reverse;
        }
        
        .room-card:nth-child(3n) {
            animation: fadeInUp 0.6s ease-out backwards,
                       breathe 5s ease-in-out infinite 0.5s,
                       floatOrbit 16s ease-in-out infinite;
        }
        
        .room-card:nth-child(1) { animation-delay: 0.1s, 0s, 0s; }
        .room-card:nth-child(2) { animation-delay: 0.15s, 0.3s, 0.5s; }
        .room-card:nth-child(3) { animation-delay: 0.2s, 0.6s, 1s; }
        
        /* Card Glow Effect */
        .room-card::before {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 28px;
            background: linear-gradient(135deg, 
                #667eea 0%, #764ba2 15%, 
                #f093fb 30%, #f5576c 45%,
                #4facfe 60%, #00f2fe 75%,
                #43e97b 90%, #667eea 100%);
            background-size: 300% 300%;
            opacity: 0.3;
            z-index: -1;
            filter: blur(15px);
            animation: auroraGlow 8s ease infinite;
            transition: opacity 0.4s ease, filter 0.4s ease;
        }
        
        .room-card:hover::before {
            opacity: 0.6;
            filter: blur(18px);
        }
        
        .room-card:hover {
            animation-play-state: paused;
            transform: translateY(-8px) scale(1.02);
        }
        
        .room-card.flipped {
            animation-play-state: paused;
        }
        
        /* Particle effects */
        .room-card .card-particles {
            position: absolute;
            inset: 0;
            pointer-events: none;
            overflow: visible;
            border-radius: 24px;
            z-index: 10;
        }
        
        .room-card .card-particles::before,
        .room-card .card-particles::after {
            content: '';
            position: absolute;
            width: 24px;
            height: 24px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.6) 0%, rgba(255, 255, 255, 0.3) 50%, transparent 70%);
            border-radius: 50%;
            animation: particleFloat 4s ease-in-out infinite;
            filter: blur(2px);
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.4);
        }
        
        .room-card .card-particles::before {
            top: 10%;
            left: 10%;
            width: 18px;
            height: 18px;
            animation-delay: 0s;
        }
        
        .room-card .card-particles::after {
            bottom: 15%;
            right: 10%;
            width: 14px;
            height: 14px;
            animation-delay: 2s;
            animation-duration: 5s;
        }
        
        /* Card Inner - 3D flip */
        .room-card-inner {
            position: relative;
            width: 100%;
            height: 100%;
            border-radius: 24px;
            transform-style: preserve-3d;
            transition: transform 0.6s ease;
        }
        
        .room-card.flipped .room-card-inner {
            transform: rotateY(180deg);
        }
        
        .room-card-face {
            position: absolute;
            inset: 0;
            border-radius: 24px;
            overflow: hidden;
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .room-card-face.front {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border: 1px solid rgba(255,255,255,0.1);
            z-index: 2;
        }
        
        .room-card-face.back {
            background: linear-gradient(145deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            border: 1px solid rgba(167, 139, 250, 0.2);
            transform: rotateY(180deg);
            z-index: 1;
        }
        
        /* Front Card Styles */
        .room-image-container {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 50%, #818cf8 100%);
        }
        
        .room-image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .room-card-face.front::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60%;
            background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.5) 40%, transparent 100%);
            pointer-events: none;
            z-index: 1;
        }
        
        .card-info-bottom {
            position: absolute;
            bottom: 1rem;
            left: 1rem;
            right: 1rem;
            z-index: 2;
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
        }
        
        .room-number-web3 {
            font-size: 1.4rem;
            font-weight: 700;
            color: #fff;
            text-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        
        .room-type-web3 {
            font-size: 0.9rem;
            color: rgba(255,255,255,0.9);
            text-shadow: 0 1px 4px rgba(0,0,0,0.3);
        }
        
        .room-price-web3 {
            font-size: 1.15rem;
            font-weight: 600;
            color: #ffffff;
            margin-top: 0.15rem;
            text-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        
        /* Status Badge */
        .status-badge-web3 {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.9), rgba(16, 185, 129, 0.9));
            backdrop-filter: blur(10px);
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
            z-index: 3;
            text-shadow: 0 1px 3px rgba(0,0,0,0.2);
            animation: pulseRing 2s ease-in-out infinite;
            box-shadow: 0 0 15px rgba(34, 197, 94, 0.5),
                        0 0 30px rgba(34, 197, 94, 0.3);
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .status-badge-web3::before {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: 22px;
            background: linear-gradient(135deg, #22c55e, #10b981, #14b8a6);
            z-index: -1;
            opacity: 0.5;
            filter: blur(4px);
            animation: pulseRing 2s ease-in-out infinite 0.5s;
        }
        
        /* Image Placeholder */
        .room-image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            color: rgba(255,255,255,0.5);
        }
        
        .room-image-placeholder svg {
            width: 60px;
            height: 60px;
            opacity: 0.4;
        }
        
        .room-image-placeholder span {
            font-size: 0.8rem;
            opacity: 0.6;
        }
        
        /* Back Card Styles */
        .back-card-content {
            flex: 1;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            overflow-y: auto;
            pointer-events: auto;
        }
        
        .back-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .room-number-back {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
            font-weight: 600;
            color: #fff;
        }
        
        .room-number-back svg {
            width: 20px;
            height: 20px;
            color: #a78bfa;
        }
        
        .room-icon-animated {
            animation: iconBounce 2s ease-in-out infinite;
        }
        
        .availability-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.3rem 0.6rem;
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(16, 185, 129, 0.2));
            border: 1px solid rgba(34, 197, 94, 0.3);
            border-radius: 20px;
            font-size: 0.7rem;
            color: #4ade80;
            font-weight: 500;
        }
        
        .back-details {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .back-details .detail-item {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.5rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .back-details .detail-item.highlight {
            background: linear-gradient(135deg, rgba(167, 139, 250, 0.15), rgba(139, 92, 246, 0.1));
            border-color: rgba(167, 139, 250, 0.2);
        }
        
        .back-details .detail-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            flex-shrink: 0;
        }
        
        .back-details .detail-icon svg {
            width: 16px;
            height: 16px;
            color: #a78bfa;
        }
        
        .back-details .detail-text {
            display: flex;
            flex-direction: column;
            gap: 0.1rem;
            min-width: 0;
        }
        
        .back-details .detail-label {
            font-size: 0.65rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .back-details .detail-value {
            font-size: 0.85rem;
            color: #fff;
            font-weight: 500;
        }
        
        .back-details .detail-value.price {
            color: #a78bfa;
            font-weight: 600;
        }
        
        .back-features {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-top: auto;
        }
        
        .feature-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.2rem;
            padding: 0.15rem 0.4rem;
            background: rgba(167, 139, 250, 0.15);
            border-radius: 6px;
            font-size: 0.55rem;
            font-weight: 500;
            color: #a78bfa;
        }
        
        .feature-tag svg {
            stroke: currentColor;
            width: 9px;
            height: 9px;
        }
        
        .back-actions {
            padding: 0.75rem 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .book-btn-back {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            padding: 0.75rem 1rem;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            font-family: inherit;
        }
        
        .book-btn-back:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
        }
        
        /* Back card scrollbar */
        .back-card-content::-webkit-scrollbar {
            width: 6px;
        }
        
        .back-card-content::-webkit-scrollbar-track {
            background: rgba(167, 139, 250, 0.1);
            border-radius: 10px;
            margin: 8px 0;
        }
        
        .back-card-content::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #a78bfa 0%, #8b5cf6 100%);
            border-radius: 10px;
        }

        /* ===== NEWS CARDS ===== */
        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .news-card {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2rem;
            border: 1px solid var(--border-color);
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .news-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(to bottom, var(--primary), #a78bfa);
            transform: scaleY(0);
            transition: transform 0.4s ease;
            transform-origin: bottom;
        }
        
        .news-card:hover {
            border-color: var(--primary);
            transform: translateX(5px);
        }

        .news-card:hover::before {
            transform: scaleY(1);
        }
        
        .news-date {
            color: var(--primary);
            font-size: 0.85rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .date-icon {
            width: 16px;
            height: 16px;
            transition: all 0.3s ease;
        }

        .news-card:hover .date-icon {
            transform: scale(1.2);
            filter: drop-shadow(0 0 5px var(--primary));
        }
        
        .news-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: #fff;
            line-height: 1.4;
        }
        
        .news-excerpt {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.7;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--bg-card);
            border-radius: 20px;
            border: 1px dashed var(--border-color);
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        .empty-state .subtitle {
            color: #64748b;
            font-size: 0.95rem;
            margin-top: 0.5rem;
        }

        /* ===== LOCATION SECTION ===== */
        .location-section {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.6), rgba(30, 41, 59, 0.6));
            padding: 5rem 2rem;
        }
        
        body.theme-light .location-section {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
        }

        .location-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            max-width: 1200px;
            margin: 0 auto;
            align-items: flex-start;
        }

        .map-wrapper {
            position: relative;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(102, 126, 234, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.1);
            aspect-ratio: 1 / 1;
            animation: fadeInUp 0.8s ease;
        }

        .map-container {
            width: 100%;
            height: 100%;
            border-radius: 24px;
            background: #1e293b;
        }

        .location-info-card {
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 16px;
            padding: 2rem;
            animation: slideUpIn 0.6s ease;
            position: relative;
            z-index: 10;
            height: fit-content;
        }

        @keyframes slideUpIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .location-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .location-header h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #fff;
        }

        .location-badge {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            padding: 0.35rem 0.85rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .location-details {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .detail-item {
            display: flex;
            gap: 1rem;
            align-items: flex-start;
        }

        .detail-icon {
            width: 24px;
            height: 24px;
            color: #667eea;
            flex-shrink: 0;
            margin-top: 0.2rem;
        }

        .detail-label {
            font-size: 0.85rem;
            color: #94a3b8;
            margin-bottom: 0.25rem;
        }

        .detail-value {
            font-size: 0.95rem;
            color: #fff;
            font-weight: 500;
        }

        .detail-value a {
            color: #667eea;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            border-bottom: 1px solid rgba(102, 126, 234, 0.3);
        }

        .detail-value a:hover {
            color: #764ba2;
            border-bottom-color: rgba(118, 75, 162, 0.6);
            text-decoration: underline;
        }

        .google-maps-btn {
            width: 100%;
            padding: 1rem;
            margin-top: 1.5rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.2);
        }

        .google-maps-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.3);
            background: linear-gradient(135deg, #764ba2, #667eea);
        }

        .google-maps-btn:active {
            transform: translateY(0);
        }

        @media (max-width: 768px) {
            .location-container {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .map-wrapper {
                aspect-ratio: 16 / 12;
            }

            .location-info-card {
                max-width: 100%;
            }
        }

        /* ===== FOOTER ===== */
        .footer {
            background: rgba(10, 10, 15, 0.9);
            backdrop-filter: blur(20px);
            padding: 4rem 2rem;
            text-align: center;
            border-top: 1px solid var(--border-color);
            position: relative;
            z-index: 10;
        }

        .footer-content {
            max-width: 600px;
            margin: 0 auto;
        }

        .footer-logo {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #fff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .footer p {
            color: #64748b;
            font-size: 0.95rem;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 1.5rem;
        }

        .footer-links a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--primary);
        }

        /* Header hide/show on scroll */
        .header {
            transition: transform 0.3s ease, background 0.3s ease;
        }
        .header.hide {
            transform: translateY(-100%);
        }

        /* ===== VIEW ALL BUTTON ===== */
        .view-all-wrapper {
            text-align: center;
            margin-top: 3rem;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 0.5rem;
                padding: 0.75rem 1rem;
            }
            
            .nav-links {
                width: 100%;
                overflow-x: auto;
                overflow-y: hidden;
                display: flex;
                flex-wrap: nowrap;
                gap: 0.5rem;
                padding: 0.25rem 0;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
            }
            .nav-links::-webkit-scrollbar {
                display: none;
            }
            .nav-links a {
                padding: 0.5rem 1rem;
                font-size: 0.85rem;
                white-space: nowrap;
                flex-shrink: 0;
            }
            
            .hero {
                padding: 7rem 1rem 3rem;
                min-height: auto;
            }
            
            .hero h2 {
                font-size: 2rem;
            }
            
            .hero-stats {
                flex-direction: column;
                gap: 2rem;
            }

            .stat-box::after {
                display: none;
            }
            
            .hero-buttons {
                flex-direction: column;
                width: 100%;
                max-width: 300px;
            }
            
            .section {
                padding: 3rem 1rem;
            }

            .room-grid {
                grid-template-columns: 1fr;
            }

            .scroll-indicator {
                display: none;
            }
        }

        /* ===== ANIMATIONS ON SCROLL ===== */
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }

        .animate-on-scroll.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* ===== LIGHT THEME ===== */
        body.theme-light {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
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
        body.theme-light .header {
            background: rgba(255, 255, 255, 0.9);
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        body.theme-light .header.scrolled {
            background: rgba(255, 255, 255, 0.95);
        }
        body.theme-light .logo-text {
            color: #1e293b;
        }
        body.theme-light .nav-links a {
            color: #475569;
        }
        body.theme-light .nav-links a:hover {
            color: var(--primary);
        }
        body.theme-light .hero-content h1 {
            color: #1e293b;
        }
        body.theme-light .hero-content p {
            color: #64748b;
        }
        body.theme-light .section-title h2 {
            color: #1e293b;
        }
        body.theme-light .section-title p {
            color: #64748b;
        }
        body.theme-light .service-card {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.2);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
        }
        body.theme-light .service-card:hover {
            border-color: rgba(59, 130, 246, 0.3);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.12);
        }
        body.theme-light .service-card h3 {
            color: #1e293b;
        }
        body.theme-light .service-card p {
            color: #64748b;
        }
        body.theme-light .room-card {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(148, 163, 184, 0.2);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
        }
        body.theme-light .room-card:hover {
            border-color: var(--primary);
            box-shadow: 0 20px 60px rgba(59, 130, 246, 0.15);
        }
        body.theme-light .room-card-body {
            background: #fff;
        }
        body.theme-light .room-features li {
            color: #475569;
        }
        body.theme-light .news-card {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.2);
        }
        body.theme-light .news-card:hover {
            border-color: rgba(59, 130, 246, 0.3);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.12);
        }
        body.theme-light .news-card h3 {
            color: #1e293b;
        }
        body.theme-light .news-card p {
            color: #64748b;
        }
        body.theme-light .news-meta {
            color: #94a3b8;
        }
        body.theme-light .contact-card {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.2);
        }
        body.theme-light .contact-card h3 {
            color: #1e293b;
        }
        body.theme-light .contact-detail {
            color: #475569;
        }
        body.theme-light .quick-link-card {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.2);
        }
        body.theme-light .quick-link-card:hover {
            border-color: rgba(59, 130, 246, 0.3);
        }
        body.theme-light .quick-link-card h4 {
            color: #1e293b;
        }
        body.theme-light .quick-link-card p {
            color: #64748b;
        }
        body.theme-light .location-card {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.2);
        }
        body.theme-light .location-card h3 {
            color: #1e293b;
        }
        body.theme-light .location-card p {
            color: #64748b;
        }
        body.theme-light .footer {
            background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
            border-top: 1px solid rgba(148, 163, 184, 0.2);
        }
        body.theme-light .footer-links a {
            color: #64748b;
        }
        body.theme-light .footer-links a:hover {
            color: var(--primary);
        }
        body.theme-light .footer-copyright {
            color: #94a3b8;
        }
        body.theme-light .stat-number {
            color: #1e293b;
        }
        body.theme-light .stat-label {
            color: #64748b;
        }
        /* Room card texts */
        body.theme-light .room-number {
            color: #1e293b;
        }
        body.theme-light .room-type {
            color: #64748b;
        }
        body.theme-light .room-price {
            color: #1e293b;
        }
        body.theme-light .room-price span {
            color: #64748b;
        }
        body.theme-light .room-card-header {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }
        body.theme-light .room-card-header .room-number,
        body.theme-light .room-card-header .room-type {
            color: #fff;
        }
        /* News card texts */
        body.theme-light .news-title {
            color: #1e293b;
        }
        body.theme-light .news-excerpt {
            color: #64748b;
        }
        body.theme-light .news-date {
            color: #94a3b8;
        }
        /* Contact section */
        body.theme-light .contact-info h3 {
            color: #1e293b;
        }
        body.theme-light .contact-info p {
            color: #64748b;
        }
        body.theme-light .contact-item {
            color: #475569;
        }
        body.theme-light .contact-item span {
            color: #1e293b;
        }
        /* Hero section */
        body.theme-light .hero-stats .stat-box {
            background: transparent;
            border: none;
        }
        body.theme-light .hero-stats .stat-box::after {
            display: none !important;
        }
        body.theme-light .hero-stats .stat-box .number {
            color: #1e293b;
        }
        body.theme-light .hero-stats .stat-box .label {
            color: #64748b;
        }
        body.theme-light .scroll-indicator {
            color: #1e293b;
        }
        
        body.theme-light .scroll-indicator .mouse {
            border: 2px solid #1e293b;
        }
        
        body.theme-light .scroll-indicator .mouse::before {
            background: #1e293b;
        }
        
        /* Buttons - keep visible */
        body.theme-light .btn-primary {
            color: #fff;
        }
        body.theme-light .btn-book {
            color: #fff;
        }
        /* Quick links */
        body.theme-light .quick-link-title {
            color: #1e293b;
        }
        body.theme-light .quick-link-desc {
            color: #64748b;
        }
        /* Service icons remain colorful */
        body.theme-light .service-icon {
            color: var(--primary);
        }
        
        /* ============================================= */
        /* ULTIMATE LIGHT THEME - ALL TEXT BLACK        */
        /* ============================================= */
        body.theme-light,
        body.theme-light *,
        body.theme-light *::before,
        body.theme-light *::after {
            color: #1e293b !important;
            -webkit-text-fill-color: #1e293b !important;
            text-shadow: none !important;
            opacity: 1 !important;
        }
        
        /* Remove all gradient text effects */
        body.theme-light h1,
        body.theme-light h2,
        body.theme-light h3,
        body.theme-light h4,
        body.theme-light h5,
        body.theme-light h6,
        body.theme-light p,
        body.theme-light span,
        body.theme-light a,
        body.theme-light div,
        body.theme-light li,
        body.theme-light label,
        body.theme-light .gradient-text,
        body.theme-light .logo h1,
        body.theme-light .nav-links a,
        body.theme-light .hero h2,
        body.theme-light .section-title h2 {
            color: #1e293b !important;
            -webkit-text-fill-color: #1e293b !important;
            background: none !important;
            -webkit-background-clip: unset !important;
            background-clip: unset !important;
            text-shadow: none !important;
        }
        
        /* ============ EXCEPTIONS - WHITE TEXT ============ */
        /* Primary buttons - dark text in light theme */
        body.theme-light .btn-primary,
        body.theme-light .btn-primary *,
        body.theme-light .btn-login,
        body.theme-light .btn-login *,
        body.theme-light button[type="submit"],
        body.theme-light button[type="submit"] * {
            color: #1e293b !important;
            -webkit-text-fill-color: #1e293b !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
        }
        
        /* Room status badge - white text */
        body.theme-light .room-status-badge {
            color: #fff !important;
            -webkit-text-fill-color: #fff !important;
        }
        
        /* ============ SECONDARY BUTTON - DARK TEXT ============ */
        body.theme-light .btn-secondary,
        body.theme-light .btn-secondary *,
        body.theme-light a.btn-secondary,
        body.theme-light a.btn-secondary * {
            color: #1e293b !important;
            -webkit-text-fill-color: #1e293b !important;
            background: rgba(255, 255, 255, 0.95) !important;
            stroke: #1e293b !important;
        }
        body.theme-light .btn-secondary {
            border: 2px solid #1e293b !important;
        }
        
        /* Room card header - dark text on light background */
        body.theme-light .room-card-header {
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe) !important;
        }
        body.theme-light .room-card-header,
        body.theme-light .room-card-header .room-number,
        body.theme-light .room-card-header .room-type,
        body.theme-light .room-card-header * {
            color: #1e293b !important;
            -webkit-text-fill-color: #1e293b !important;
        }
        body.theme-light .room-status-badge {
            color: #fff !important;
            -webkit-text-fill-color: #fff !important;
        }
        /* Room book button - dark text */
        body.theme-light .btn-book,
        body.theme-light .btn-book *,
        body.theme-light a.btn-book,
        body.theme-light a.btn-book * {
            color: #1e293b !important;
            -webkit-text-fill-color: #1e293b !important;
            background: transparent !important;
        }
        /* Google Maps button - white text */
        body.theme-light .google-maps-btn,
        body.theme-light .google-maps-btn * {
            color: #fff !important;
            -webkit-text-fill-color: #fff !important;
            stroke: #fff !important;
        }
        /* Force site name to dark */
        body.theme-light .logo h1 {
            color: #1e293b !important;
            background: transparent !important;
            background-clip: unset !important;
            -webkit-background-clip: unset !important;
            -webkit-text-fill-color: #1e293b !important;
            text-shadow: none !important;
        }
        /* Force gradient text to dark */
        body.theme-light .gradient-text {
            color: #1e293b !important;
            background: transparent !important;
            -webkit-background-clip: unset !important;
            background-clip: unset !important;
            -webkit-text-fill-color: #1e293b !important;
            text-shadow: none !important;
        }
        body.theme-light .hero-content h1,
        body.theme-light .section-title h2,
        body.theme-light .service-card h3,
        body.theme-light .news-card h3,
        body.theme-light .contact-card h3,
        body.theme-light .quick-link-card h4,
        body.theme-light .location-card h3 {
            color: #1e293b !important;
        }
        body.theme-light .hero-content p,
        body.theme-light .section-title p,
        body.theme-light .service-card p,
        body.theme-light .news-card p,
        body.theme-light .contact-card p,
        body.theme-light .quick-link-card p,
        body.theme-light .location-card p {
            color: #475569 !important;
        }
        /* Location card - light background */
        body.theme-light .location-card {
            background: rgba(255, 255, 255, 0.95) !important;
            border: 1px solid rgba(148, 163, 184, 0.3);
        }
        body.theme-light .location-info {
            background: rgba(248, 250, 252, 0.9);
        }
        body.theme-light .location-address {
            color: #475569 !important;
        }
        /* Contact section light */
        body.theme-light .contact-section {
            background: rgba(248, 250, 252, 0.95);
        }
        body.theme-light .contact-detail {
            color: #475569 !important;
        }
        body.theme-light .contact-detail a {
            color: #1e293b !important;
        }
        /* Nav menu items */
        body.theme-light .nav-links a {
            color: #475569 !important;
        }
        body.theme-light .nav-links a:hover {
            color: var(--primary) !important;
        }
        /* Button text stays white */
        body.theme-light .btn-primary,
        body.theme-light .btn-book,
        body.theme-light .btn-login,
        body.theme-light .btn-view-rooms {
            color: #fff !important;
        }
        /* Override gradient text for light theme - IMPORTANT */
        body.theme-light .section-title h3,
        body.theme-light .hero-content h1 {
            background: none !important;
            -webkit-background-clip: unset !important;
            -webkit-text-fill-color: #1e293b !important;
            background-clip: unset !important;
            color: #1e293b !important;
        }
        body.theme-light .section-title .label {
            background: rgba(59, 130, 246, 0.1) !important;
            border-color: rgba(59, 130, 246, 0.3) !important;
            color: #3b82f6 !important;
        }
        body.theme-light .section-title p {
            color: #64748b !important;
        }
        body.theme-light .service-card h3,
        body.theme-light .quick-link-card h4,
        body.theme-light .news-card h3,
        body.theme-light .contact-card h3 {
            background: none !important;
            -webkit-background-clip: unset !important;
            -webkit-text-fill-color: #1e293b !important;
            background-clip: unset !important;
            color: #1e293b !important;
        }
        body.theme-light .service-card p,
        body.theme-light .quick-link-card p,
        body.theme-light .news-card p {
            color: #64748b !important;
        }
        /* Web3 Room card - keep white text on dark cards */
        body.theme-light .room-card .room-number-web3,
        body.theme-light .room-card .room-type-web3,
        body.theme-light .room-card .room-price-web3,
        body.theme-light .room-card .status-badge-web3,
        body.theme-light .room-card .room-number-back,
        body.theme-light .room-card .room-number-back span,
        body.theme-light .room-card .detail-value,
        body.theme-light .room-card .availability-badge,
        body.theme-light .room-card .feature-tag,
        body.theme-light .room-card .book-btn-back {
            color: #fff !important;
        }
        body.theme-light .room-card .detail-label {
            color: #94a3b8 !important;
        }
        body.theme-light .room-card .detail-value.price {
            color: #a78bfa !important;
        }
        body.theme-light .room-card .availability-badge {
            color: #4ade80 !important;
        }
        body.theme-light .room-card .feature-tag {
            color: #a78bfa !important;
        }
        /* ENSURE ALL TEXT IS DARK IN LIGHT THEME */
        body.theme-light {
            color: #1e293b !important;
        }
        body.theme-light * {
            color: #1e293b !important;
        }
        body.theme-light,
        body.theme-light a,
        body.theme-light p,
        body.theme-light span,
        body.theme-light div,
        body.theme-light h1,
        body.theme-light h2,
        body.theme-light h3,
        body.theme-light h4,
        body.theme-light h5,
        body.theme-light h6,
        body.theme-light li,
        body.theme-light label,
        body.theme-light strong,
        body.theme-light em,
        body.theme-light small,
        body.theme-light .logo-text,
        body.theme-light .nav-links a,
        body.theme-light .hero-content,
        body.theme-light .section-title,
        body.theme-light .service-card,
        body.theme-light .room-card-body,
        body.theme-light .news-card,
        body.theme-light .contact-card,
        body.theme-light .location-card,
        body.theme-light .footer {
            color: #1e293b !important;
        }
        /* Remove all gradients and text effects */
        body.theme-light h1,
        body.theme-light h2,
        body.theme-light h3,
        body.theme-light h4,
        body.theme-light h5,
        body.theme-light h6,
        body.theme-light .section-title h3,
        body.theme-light .section-title h2,
        body.theme-light .hero-content h1,
        body.theme-light .service-card h3,
        body.theme-light .quick-link-card h4,
        body.theme-light .news-card h3,
        body.theme-light .contact-card h3 {
            background: none !important;
            -webkit-background-clip: unset !important;
            -webkit-text-fill-color: unset !important;
            background-clip: unset !important;
            color: #1e293b !important;
            text-shadow: none !important;
            opacity: 1 !important;
        }
        /* All text must be dark black */
        body.theme-light,
        body.theme-light * {
            color: #1e293b !important;
            opacity: 1 !important;
        }
        /* Except buttons - keep white text */
        body.theme-light .btn-primary,
        body.theme-light .btn-book,
        body.theme-light .btn-login,
        body.theme-light .btn-view-rooms,
        body.theme-light .btn-submit,
        body.theme-light button[type="submit"],
        body.theme-light a[class*="btn"] {
            color: #fff !important;
        }
        /* Keep header card text white */
        body.theme-light .room-card-header,
        body.theme-light .room-card-header .room-number,
        body.theme-light .room-card-header .room-type,
        body.theme-light .room-status-badge {
            color: #fff !important;
        }
    </style>
</head>
<?php
// กำหนด theme class
$themeClass = '';
if ($publicTheme === 'light') {
    $themeClass = 'theme-light';
} elseif ($publicTheme === 'auto') {
    // Auto mode: ใช้ JavaScript ในการตรวจสอบเวลา
    $themeClass = ''; // จะถูกกำหนดโดย JavaScript
}
?>
<body class="<?php echo $themeClass; ?>" data-theme-mode="<?php echo $publicTheme; ?>">
    <?php if ($publicTheme === 'auto'): ?>
    <script>
      // Auto theme: ตรวจสอบเวลา (6:00-18:00 = light, 18:00-6:00 = dark)
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

    <!-- Header -->
    <header class="header" id="header">
        <div class="logo">
            <img src="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="<?php echo htmlspecialchars($siteName); ?>">
            <h1><?php echo htmlspecialchars($siteName); ?></h1>
        </div>
        <nav class="nav-links">
            <a href="#services"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 1v6m0 6v6m11-7h-6m-6 0H1m15.364-6.364l-4.243 4.243m0 4.243l4.243 4.243M6.636 6.636l4.243 4.243m0 4.243l-4.243 4.243"/></svg> บริการ</a>
            <a href="#rooms"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg> ห้องพัก</a>
            <a href="#news"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6m-6-4h6"/></svg> ข่าวสาร</a>
            <a href="Public/booking.php"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01M16 18h.01"/></svg> จองห้อง</a>
            <?php if (!empty($_SESSION['tenant_logged_in'])): ?>
            <div class="user-avatar-container">
                <img src="<?php echo htmlspecialchars($_SESSION['tenant_picture'] ?? '/dormitory_management/Public/Assets/Images/default-avatar.png'); ?>" 
                     alt="<?php echo htmlspecialchars($_SESSION['tenant_name'] ?? 'User'); ?>" 
                     class="user-avatar" 
                     onclick="toggleAvatarDropdown()">
                <div class="avatar-dropdown" id="avatarDropdown">
                    <div class="dropdown-header">
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['tenant_name'] ?? 'ผู้ใช้'); ?></div>
                        <div class="user-email">ผู้เช่า</div>
                    </div>
                    <a href="Public/booking_status.php" class="dropdown-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        ตรวจสอบสถานะการจอง
                    </a>
                    <a href="tenant_logout.php" class="dropdown-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                        ออกจากระบบ
                    </a>
                </div>
            </div>
            <?php else: ?>
            <a href="Login.php" class="btn-login"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/><circle cx="12" cy="16" r="1"/></svg> เข้าสู่ระบบ</a>
            <?php endif; ?>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-badge">
            <span class="dot"></span>
            <span>พร้อมให้บริการ 24 ชั่วโมง</span>
        </div>
        
        <h2>
            <span class="gradient-text"><?php echo htmlspecialchars($siteName); ?></span>
        </h2>
        <p>หอพักคุณภาพระดับพรีเมียม สะอาด ปลอดภัย ใกล้แหล่งสิ่งอำนวยความสะดวก พร้อมระบบรักษาความปลอดภัยตลอด 24 ชั่วโมง</p>
        
        <div class="hero-buttons">
            <a href="Public/booking.php" class="btn btn-primary">
                <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M12 14l2 2 4-4"/></svg> จองห้องพักเลย
            </a>
            <a href="#rooms" class="btn btn-secondary">
                <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg> ดูห้องว่าง
            </a>
        </div>
        
        <div class="hero-stats">
            <div class="stat-box">
                <div class="number"><?php echo $roomStats['total']; ?></div>
                <div class="label">ห้องพักทั้งหมด</div>
            </div>
            <div class="stat-box">
                <div class="number"><?php echo $roomStats['available']; ?></div>
                <div class="label">ห้องว่างพร้อมเข้าอยู่</div>
            </div>
        </div>

        <div class="scroll-indicator">
            <div class="mouse"></div>
            <span>เลื่อนลง</span>
        </div>
    </section>

    <!-- Quick Links for Public -->
    <section class="section" id="services">
        <div class="section-title">
            <span class="label">บริการของเรา</span>
            <h3><svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="url(#gradient1)" stroke-width="2"><defs><linearGradient id="gradient1" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#60a5fa"/><stop offset="100%" stop-color="#a78bfa"/></linearGradient></defs><circle cx="12" cy="12" r="3"/><path d="M12 1v6m0 6v6m11-7h-6m-6 0H1m15.364-6.364l-4.243 4.243m0 4.243l4.243 4.243M6.636 6.636l4.243 4.243m0 4.243l-4.243 4.243"/></svg> บริการสำหรับบุคคลทั่วไป</h3>
            <p>เลือกบริการที่ต้องการได้ทันที</p>
        </div>
        
        <div class="quick-links">
            <a href="Public/booking.php" class="quick-link-card animate-on-scroll">
                <div class="quick-link-icon animated-icon">
                    <div class="icon-glow"></div>
                    <svg class="icon-float" viewBox="0 0 24 24" fill="none" stroke="url(#bookingGrad)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <defs>
                            <linearGradient id="bookingGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#60a5fa"/>
                                <stop offset="50%" stop-color="#a78bfa"/>
                                <stop offset="100%" stop-color="#f472b6"/>
                            </linearGradient>
                        </defs>
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                        <path d="M9 16l2 2 4-4"/>
                    </svg>
                </div>
                <h4>จองห้องพัก</h4>
                <p>จองห้องพักออนไลน์ ง่าย สะดวก รวดเร็ว ตลอด 24 ชั่วโมง</p>
            </a>
            
            <a href="Public/rooms.php" class="quick-link-card animate-on-scroll">
                <div class="quick-link-icon animated-icon">
                    <div class="icon-glow"></div>
                    <svg class="icon-float" viewBox="0 0 24 24" fill="none" stroke="url(#roomGrad)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <defs>
                            <linearGradient id="roomGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#22c55e"/>
                                <stop offset="50%" stop-color="#60a5fa"/>
                                <stop offset="100%" stop-color="#a78bfa"/>
                            </linearGradient>
                        </defs>
                        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                        <circle cx="12" cy="8" r="1"/>
                    </svg>
                </div>
                <h4>ข้อมูลห้องพัก</h4>
                <p>ดูรายละเอียดห้องพัก ประเภท ราคา และสิ่งอำนวยความสะดวก</p>
            </a>
            
            <a href="Public/booking_status.php" class="quick-link-card animate-on-scroll">
                <div class="quick-link-icon animated-icon">
                    <div class="icon-glow"></div>
                    <svg class="icon-float" viewBox="0 0 24 24" fill="none" stroke="url(#statusGrad)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <defs>
                            <linearGradient id="statusGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#fbbf24"/>
                                <stop offset="50%" stop-color="#60a5fa"/>
                                <stop offset="100%" stop-color="#a78bfa"/>
                            </linearGradient>
                        </defs>
                        <path d="M12 2a10 10 0 110 20 10 10 0 010-20z"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
                <h4>ตรวจสอบสถานะการจอง</h4>
                <p>ตรวจสอบสถานะการจอง สัญญา และการชำระเงิน</p>
            </a>
            
            <a href="Public/news.php" class="quick-link-card animate-on-scroll">
                <div class="quick-link-icon animated-icon">
                    <div class="icon-glow"></div>
                    <svg class="icon-float" viewBox="0 0 24 24" fill="none" stroke="url(#newsGrad)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <defs>
                            <linearGradient id="newsGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#f472b6"/>
                                <stop offset="50%" stop-color="#a78bfa"/>
                                <stop offset="100%" stop-color="#60a5fa"/>
                            </linearGradient>
                        </defs>
                        <path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1"/>
                        <path d="M17 20a2 2 0 002-2V9a2 2 0 00-2-2h-2"/>
                        <path d="M17 20a2 2 0 01-2-2V7"/>
                        <line x1="9" y1="9" x2="13" y2="9"/>
                        <line x1="7" y1="13" x2="13" y2="13"/>
                        <line x1="7" y1="17" x2="11" y2="17"/>
                    </svg>
                </div>
                <h4>ข่าวประชาสัมพันธ์</h4>
                <p>ติดตามข่าวสารและประกาศล่าสุดจากหอพัก</p>
            </a>
        </div>
    </section>

    <!-- Available Rooms -->
    <section class="section" id="rooms">
        <div class="section-title">
            <span class="label">ห้องพักของเรา</span>
            <h3><svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="url(#gradient2)" stroke-width="2"><defs><linearGradient id="gradient2" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#22c55e"/><stop offset="100%" stop-color="#60a5fa"/></linearGradient></defs><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg> ห้องพักว่างพร้อมเข้าอยู่</h3>
            <p>ห้องพักคุณภาพที่พร้อมให้เข้าพักได้ทันที</p>
        </div>
        
        <?php if (count($availableRooms) > 0): ?>
        <?php
        // Feature icons for room cards
        $featureIcons = [
            'ไฟฟ้า' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path></svg>',
            'น้ำประปา' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path></svg>',
            'WiFi' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;"><path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"></path></svg>',
            'เฟอร์นิเจอร์' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;"><rect x="2" y="6" width="20" height="8" rx="2"></rect><path d="M4 14v4M20 14v4M2 10h20"></path></svg>',
            'แอร์' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;"><path d="M8 16a4 4 0 0 1-4-4 4 4 0 0 1 4-4h8a4 4 0 0 1 4 4 4 4 0 0 1-4 4H8zm0-8v12M16 8v12"></path></svg>',
            'ตู้เย็น' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;"><rect x="4" y="2" width="16" height="20" rx="2"></rect><line x1="4" y1="10" x2="20" y2="10"></line><line x1="10" y1="6" x2="10" y2="6"></line><line x1="10" y1="14" x2="10" y2="18"></line></svg>'
        ];
        $defaultFeatures = ['ไฟฟ้า', 'น้ำประปา', 'WiFi', 'เฟอร์นิเจอร์', 'แอร์', 'ตู้เย็น'];
        ?>
        <div class="room-grid">
            <?php foreach ($availableRooms as $index => $room): ?>
            <div class="room-card animate-on-scroll" data-index="<?php echo $index; ?>" style="cursor: pointer;">
                <!-- Particle effects -->
                <div class="card-particles"></div>
                
                <div class="room-card-inner">
                    <div class="room-card-face front">
                        <!-- Status Badge -->
                        <span class="status-badge-web3">ว่าง</span>
                        
                        <!-- Room Image Container -->
                        <div class="room-image-container">
                            <?php if (!empty($room['room_image'])): ?>
                            <img src="/dormitory_management/Public/Assets/Images/Rooms/<?php echo htmlspecialchars($room['room_image']); ?>" alt="รูปห้อง <?php echo $room['room_number']; ?>">
                            <?php else: ?>
                            <div class="room-image-placeholder" aria-label="ไม่มีรูปห้อง">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="14" rx="2" ry="2"></rect>
                                    <circle cx="8.5" cy="9.5" r="1.5"></circle>
                                    <path d="M21 15l-4.5-4.5a2 2 0 0 0-3 0L5 19"></path>
                                </svg>
                                <span>ไม่มีรูป</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Info at bottom left -->
                        <div class="card-info-bottom">
                            <div class="room-number-web3">ห้อง <?php echo htmlspecialchars((string)$room['room_number']); ?></div>
                            <div class="room-type-web3"><?php echo htmlspecialchars($room['type_name'] ?? 'ห้องมาตรฐาน'); ?></div>
                            <div class="room-price-web3"><?php echo number_format((int)($room['type_price'] ?? 0)); ?>/เดือน</div>
                        </div>
                    </div>
                    
                    <div class="room-card-face back">
                        <!-- Enhanced Back Card with More Information -->
                        <div class="back-card-content">
                            <div class="back-header">
                                <div class="room-number-back">
                                    <svg class="room-icon-animated" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                    </svg>
                                    <span>ห้อง <?php echo htmlspecialchars((string)$room['room_number']); ?></span>
                                </div>
                                <span class="availability-badge">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                    </svg>
                                    พร้อมให้เช่า
                                </span>
                            </div>
                            
                            <div class="back-details">
                                <div class="detail-item">
                                    <div class="detail-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                            <line x1="3" y1="9" x2="21" y2="9"></line>
                                            <line x1="9" y1="21" x2="9" y2="9"></line>
                                        </svg>
                                    </div>
                                    <div class="detail-text">
                                        <span class="detail-label">ประเภทห้อง</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($room['type_name'] ?? 'ห้องมาตรฐาน'); ?></span>
                                    </div>
                                </div>
                                
                                <div class="detail-item highlight">
                                    <div class="detail-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="12" y1="1" x2="12" y2="23"></line>
                                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                        </svg>
                                    </div>
                                    <div class="detail-text">
                                        <span class="detail-label">ค่าเช่ารายเดือน</span>
                                        <span class="detail-value price">฿<?php echo number_format((int)($room['type_price'] ?? 0)); ?></span>
                                    </div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <polyline points="12 6 12 12 16 14"></polyline>
                                        </svg>
                                    </div>
                                    <div class="detail-text">
                                        <span class="detail-label">ค่ามัดจำ</span>
                                        <span class="detail-value">฿2,000</span>
                                    </div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                            <polyline points="14 2 14 8 20 8"></polyline>
                                            <line x1="16" y1="13" x2="8" y2="13"></line>
                                            <line x1="16" y1="17" x2="8" y2="17"></line>
                                        </svg>
                                    </div>
                                    <div class="detail-text">
                                        <span class="detail-label">สัญญาขั้นต่ำ</span>
                                        <span class="detail-value">6 เดือน</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="back-features">
                                <?php foreach ($defaultFeatures as $feature): 
                                    $icon = $featureIcons[$feature] ?? '';
                                ?>
                                <span class="feature-tag"><?php echo $icon; ?> <?php echo htmlspecialchars($feature); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="back-actions">
                            <a href="Public/booking.php?room=<?php echo $room['room_id']; ?>" class="book-btn-back">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                จองห้องนี้
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="view-all-wrapper">
            <a href="Public/rooms.php" class="btn btn-secondary">ดูห้องพักทั้งหมด →</a>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">😔</div>
            <p>ขณะนี้ห้องพักเต็มทุกห้อง</p>
            <p class="subtitle">กรุณาติดต่อสอบถามหรือจองล่วงหน้า</p>
        </div>
        <?php endif; ?>
    </section>

    <!-- News Section -->
    <section class="section" id="news">
        <div class="section-title">
            <span class="label">อัพเดทล่าสุด</span>
            <h3><svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="url(#gradient3)" stroke-width="2"><defs><linearGradient id="gradient3" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#f472b6"/><stop offset="100%" stop-color="#a78bfa"/></linearGradient></defs><path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6m-6-4h6"/></svg> ข่าวประชาสัมพันธ์</h3>
            <p>ข่าวสารและประกาศล่าสุดจากหอพัก</p>
        </div>
        
        <?php if (count($news) > 0): ?>
        <div class="news-grid">
            <?php foreach ($news as $item): ?>
            <div class="news-card animate-on-scroll">
                <div class="news-date">
                    <svg class="date-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <?php echo date('d/m/Y', strtotime($item['news_date'])); ?>
                </div>
                <div class="news-title"><?php echo htmlspecialchars($item['news_title'] ?? ''); ?></div>
                <div class="news-excerpt"><?php echo htmlspecialchars(strip_tags($item['news_details'] ?? '')); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="view-all-wrapper">
            <a href="Public/news.php" class="btn btn-secondary">ดูข่าวทั้งหมด →</a>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">📭</div>
            <p>ยังไม่มีข่าวประชาสัมพันธ์</p>
        </div>
        <?php endif; ?>
    </section>

    <!-- Location Section -->
    <section class="section location-section" id="location">
        <div class="section-title">
            <span class="label">📍 ที่ตั้ง</span>
            <h2>ที่ตั้งหอพัก</h2>
            <p>เข้าชมสภาพแวดล้อมและที่ตั้งหอพักของเรา</p>
        </div>

        <div class="location-container">
            <div class="map-wrapper">
                <div id="map" class="map-container"></div>
            </div>
            <div class="location-info-card">
                <div class="location-header">
                    <h3><?php echo htmlspecialchars($siteName); ?></h3>
                    <div class="location-badge">สถานที่ตั้ง</div>
                </div>
                <div class="location-details">
                    <div class="detail-item">
                        <svg class="detail-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                        </svg>
                        <div>
                            <div class="detail-label">พิกัด GPS</div>
                            <div class="detail-value">16.436550, 101.149011</div>
                        </div>
                    </div>
                    <div class="detail-item">
                        <svg class="detail-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                        </svg>
                        <div>
                            <div class="detail-label">โทรศัพท์</div>
                            <div class="detail-value"><a href="tel:<?php echo htmlspecialchars($contactPhone); ?>"><?php echo htmlspecialchars($contactPhone); ?></a></div>
                        </div>
                    </div>
                    <div class="detail-item">
                        <svg class="detail-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                        </svg>
                        <div>
                            <div class="detail-label">อีเมล</div>
                            <div class="detail-value"><a href="mailto:<?php echo htmlspecialchars($contactEmail); ?>"><?php echo htmlspecialchars($contactEmail); ?></a></div>
                        </div>
                    </div>
                </div>
                <button class="google-maps-btn" onclick="openGoogleMaps()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px; margin-right: 0.5rem;">
                        <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                    </svg>
                    ดูใน Google Maps
                </button>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-logo"><?php echo htmlspecialchars($siteName); ?></div>
            <p>หอพักคุณภาพ สะอาด ปลอดภัย พร้อมบริการดูแลตลอด 24 ชั่วโมง</p>
            <div class="footer-links">
                <a href="Public/rooms.php">ห้องพัก</a>
                <a href="Public/news.php">ข่าวสาร</a>
                <a href="Public/booking.php">จองห้อง</a>
                <?php if (!empty($_SESSION['tenant_logged_in'])): ?>
                <a href="tenant_logout.php">ออกจากระบบ</a>
                <?php else: ?>
                <a href="Login.php">เข้าสู่ระบบ</a>
                <?php endif; ?>
            </div>
            <p style="margin-top: 2rem;">© <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName); ?> - สงวนลิขสิทธิ์</p>
        </div>
    </footer>

    <script>
        // Header scroll effect with hide/show
        let lastScrollTop = 0;
        const header = document.getElementById('header');
        
        window.addEventListener('scroll', function() {
            let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            // Add scrolled class
            if (scrollTop > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
            
            // Hide/show header based on scroll direction
            if (scrollTop > lastScrollTop && scrollTop > 100) {
                // Scrolling down
                header.classList.add('hide');
            } else {
                // Scrolling up
                header.classList.remove('hide');
            }
            
            lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
        });

        // Animate on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.animate-on-scroll').forEach(el => {
            observer.observe(el);
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // ===== CARD FLIP ON HOVER WITH DELAY =====
        const flipDelay = 400;
        const flipTimers = new WeakMap();
        
        function setupCardFlip() {
            document.querySelectorAll('.room-card').forEach(card => {
                if (card.dataset.flipSetup) return;
                card.dataset.flipSetup = 'true';
                
                card.addEventListener('mouseenter', () => {
                    // Clear any existing timer
                    const existingTimer = flipTimers.get(card);
                    if (existingTimer) clearTimeout(existingTimer);
                    
                    // Set timer to flip after delay
                    const timer = setTimeout(() => {
                        card.classList.add('flipped');
                    }, flipDelay);
                    flipTimers.set(card, timer);
                });
                
                card.addEventListener('mouseleave', () => {
                    // Clear timer if mouse leaves before delay
                    const existingTimer = flipTimers.get(card);
                    if (existingTimer) {
                        clearTimeout(existingTimer);
                        flipTimers.delete(card);
                    }
                    
                    // Remove flipped class
                    card.classList.remove('flipped');
                });
            });
        }
        
        // Initialize card flip
        setupCardFlip();
    </script>

    <!-- Leaflet Map (Open-source, no API key required) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    
    <style>
        /* Leaflet map custom styling */
        .leaflet-container {
            background: #1e293b !important;
            border-radius: 24px;
        }
        
        body.theme-light .leaflet-container {
            background: #f8fafc !important;
            filter: invert(0.95) hue-rotate(180deg);
        }
        
        .leaflet-control-attribution {
            background: rgba(15, 23, 42, 0.8) !important;
            color: #94a3b8 !important;
            border-radius: 8px !important;
        }
        
        body.theme-light .leaflet-control-attribution {
            background: rgba(248, 250, 252, 0.9) !important;
            color: #64748b !important;
        }
        
        .leaflet-control {
            background: rgba(15, 23, 42, 0.9) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 8px !important;
        }
        
        body.theme-light .leaflet-control {
            background: rgba(248, 250, 252, 0.95) !important;
            border: 1px solid rgba(51, 65, 85, 0.2) !important;
        }
        
        .leaflet-control button {
            background: rgba(102, 126, 234, 0.2) !important;
            color: #667eea !important;
            border: none !important;
        }
        
        body.theme-light .leaflet-control button {
            background: rgba(59, 130, 246, 0.15) !important;
            color: #3b82f6 !important;
        }
        
        .leaflet-control button:hover {
            background: rgba(102, 126, 234, 0.3) !important;
        }
        
        body.theme-light .leaflet-control button:hover {
            background: rgba(59, 130, 246, 0.25) !important;
        }
        
        .custom-marker {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: 3px solid #fff;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            }
            50% {
                box-shadow: 0 4px 20px rgba(102, 126, 234, 0.7);
            }
        }
        
        .custom-marker::before {
            content: '';
            width: 8px;
            height: 8px;
            background: #fff;
            border-radius: 50%;
        }
        
        .leaflet-popup-content-wrapper {
            background: rgba(15, 23, 42, 0.95) !important;
            border: 1px solid rgba(102, 126, 234, 0.3) !important;
            border-radius: 12px !important;
            backdrop-filter: blur(20px);
        }
        
        body.theme-light .leaflet-popup-content-wrapper {
            background: rgba(248, 250, 252, 0.98) !important;
            border: 1px solid rgba(59, 130, 246, 0.3) !important;
        }
        
        .leaflet-popup-content {
            color: #fff !important;
            margin: 0 !important;
            font-size: 0.9rem !important;
        }
        
        body.theme-light .leaflet-popup-content {
            color: #1e293b !important;
        }
        
        .leaflet-popup-tip {
            background: rgba(15, 23, 42, 0.95) !important;
            border: 1px solid rgba(102, 126, 234, 0.3) !important;
        }
        
        body.theme-light .leaflet-popup-tip {
            background: rgba(248, 250, 252, 0.98) !important;
            border: 1px solid rgba(59, 130, 246, 0.3) !important;
        }
    </style>
    
    <script>
        function initMap() {
            const locationCoords = [16.436442694042363, 101.14910715953714];
            const siteName = '<?php echo htmlspecialchars($siteName); ?>';
            
            // Create map
            const map = L.map('map').setView(locationCoords, 16);
            
            // Add dark-themed tile layer (CartoDB Positron)
            L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_nolabels/{z}/{x}/{y}{r}.png', {
                attribution: '© OpenStreetMap contributors © CARTO',
                maxZoom: 20,
                crossOrigin: true
            }).addTo(map);
            
            // Add labels layer
            L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_only_labels/{z}/{x}/{y}{r}.png', {
                attribution: '',
                maxZoom: 20,
                crossOrigin: true,
                pane: 'markerPane'
            }).addTo(map);
            
            // Create custom marker
            const markerElement = L.divIcon({
                html: '<div class="custom-marker"></div>',
                iconSize: [40, 40],
                iconAnchor: [20, 20],
                popupAnchor: [0, -20],
                className: 'custom-div-icon'
            });
            
            // Add marker
            const marker = L.marker(locationCoords, { icon: markerElement })
                .bindPopup(
                    '<div style="text-align: center;"><strong style="font-size:1.1em;color:#fff;display:block;margin-bottom:8px;">' + siteName + '</strong>' +
                    '<svg style="width:24px;height:24px;margin-bottom:8px;" viewBox="0 0 24 24" fill="none" stroke="#667eea" stroke-width="2">' +
                    '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg><br/>' +
                    '<span style="color:#94a3b8;font-size:0.9em;">16.436550, 101.149011</span></div>',
                    { closeButton: true, className: 'custom-popup' }
                )
                .addTo(map);
            
            // Open popup on load
            setTimeout(() => marker.openPopup(), 500);
            
            // Handle window resize
            window.addEventListener('resize', () => {
                setTimeout(() => map.invalidateSize(), 100);
            });
        }
        
        // Initialize map when page loads
        document.addEventListener('DOMContentLoaded', () => {
            if (document.getElementById('map')) {
                initMap();
            }
        });

        // Open location on Google Maps
        function openGoogleMaps() {
            const latitude = 16.436550126945612;
            const longitude = 101.14901061039491;
            const googleMapsUrl = `https://www.google.com/maps/search/?api=1&query=${latitude},${longitude}`;
            window.open(googleMapsUrl, '_blank');
        }
        
        // Avatar Dropdown Toggle
        function toggleAvatarDropdown() {
            const dropdown = document.getElementById('avatarDropdown');
            dropdown.classList.toggle('show');
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const container = document.querySelector('.user-avatar-container');
            const dropdown = document.getElementById('avatarDropdown');
            
            if (container && dropdown && !container.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
    </script>
    
    <?php include_once __DIR__ . '/includes/apple_alert.php'; ?>
</body>
</html>
