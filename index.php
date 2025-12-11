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
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename', 'theme_color', 'bg_filename', 'contact_phone', 'contact_email')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
        if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
        if ($row['setting_key'] === 'theme_color') $themeColor = $row['setting_value'];
        if ($row['setting_key'] === 'bg_filename') $bgFilename = $row['setting_value'];
        if ($row['setting_key'] === 'contact_phone') $contactPhone = $row['setting_value'];
        if ($row['setting_key'] === 'contact_email') $contactEmail = $row['setting_value'];
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
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-dark);
            min-height: 100vh;
            color: var(--text-primary);
            overflow-x: hidden;
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
            background: var(--glass-bg);
            border: 1px solid var(--border-color);
            border-radius: 50px;
            font-size: 0.85rem;
            color: var(--primary);
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

        /* ===== ROOM CARDS ===== */
        .room-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 2rem;
        }
        
        .room-card {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid var(--border-color);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
        }
        
        .room-card:hover {
            transform: translateY(-10px);
            border-color: var(--primary);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4), 0 0 40px var(--primary-glow);
        }

        .room-card-image-wrapper {
            position: relative;
            height: 200px;
            overflow: hidden;
        }
        
        .room-card-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            background: linear-gradient(135deg, #1e293b, #0f172a);
            transition: transform 0.5s ease;
            animation: imageZoom 0.6s ease forwards;
        }
        
        @keyframes imageZoom {
            from {
                opacity: 0;
                transform: scale(1.05);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .room-card-placeholder-svg {
            width: 100%;
            height: 100%;
            animation: imageZoom 0.6s ease forwards;
        }

        .room-card:hover .room-card-image {
            transform: scale(1.1);
        }
        
        .room-card:hover .room-card-placeholder-svg {
            filter: brightness(1.1);
        }

        .room-status-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.4rem 1rem;
            background: rgba(34, 197, 94, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .room-status-badge::before {
            content: '';
            width: 6px;
            height: 6px;
            background: #fff;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        .room-card-header {
            background: linear-gradient(135deg, var(--primary), #1d4ed8);
            padding: 1.25rem 1.5rem;
            position: relative;
        }

        .room-card-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent);
        }
        
        .room-number {
            font-size: 1.8rem;
            font-weight: 700;
            position: relative;
        }
        
        .room-type {
            opacity: 0.9;
            font-size: 0.9rem;
            position: relative;
        }
        
        .room-card-body {
            padding: 1.5rem;
        }
        
        .room-price {
            font-size: 2rem;
            font-weight: 800;
            color: #22c55e;
            margin-bottom: 1rem;
            display: flex;
            align-items: baseline;
            gap: 0.3rem;
        }
        
        .room-price span {
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-weight: 400;
        }
        
        .room-features {
            list-style: none;
            margin-bottom: 1.5rem;
            display: grid;
            gap: 0.5rem;
        }
        
        .room-features li {
            padding: 0.6rem 0;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.95rem;
        }
        
        .room-features li .feature-icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }
        
        .room-features li .icon {
            width: 28px;
            height: 28px;
            background: var(--glass-bg);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }
        
        .btn-book {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary), #1d4ed8);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-book::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s ease;
        }
        
        .btn-book:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px var(--primary-glow);
        }

        .btn-book:hover::before {
            left: 100%;
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

        /* ===== VIEW ALL BUTTON ===== */
        .view-all-wrapper {
            text-align: center;
            margin-top: 3rem;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
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
    </style>
</head>
<body>
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
            <img src="Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="<?php echo htmlspecialchars($siteName); ?>">
            <h1><?php echo htmlspecialchars($siteName); ?></h1>
        </div>
        <nav class="nav-links">
            <a href="#services"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 1v6m0 6v6m11-7h-6m-6 0H1m15.364-6.364l-4.243 4.243m0 4.243l4.243 4.243M6.636 6.636l4.243 4.243m0 4.243l-4.243 4.243"/></svg> บริการ</a>
            <a href="#rooms"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg> ห้องพัก</a>
            <a href="#news"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6m-6-4h6"/></svg> ข่าวสาร</a>
            <a href="Public/booking.php"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01M16 18h.01"/></svg> จองห้อง</a>
            <a href="Login.php" class="btn-login"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/><circle cx="12" cy="16" r="1"/></svg> เข้าสู่ระบบ</a>
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
        <div class="room-grid">
            <?php foreach ($availableRooms as $room): ?>
            <div class="room-card animate-on-scroll">
                <div class="room-card-image-wrapper">
                    <?php if (!empty($room['room_image'])): ?>
                    <img src="Assets/Images/Rooms/<?php echo htmlspecialchars($room['room_image']); ?>" alt="ห้อง <?php echo htmlspecialchars($room['room_number']); ?>" class="room-card-image">
                    <?php else: ?>
                    <svg class="room-card-placeholder-svg" viewBox="0 0 400 300" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <linearGradient id="roomGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:rgba(30,41,59,1);stop-opacity:1" />
                                <stop offset="100%" style="stop-color:rgba(15,23,42,1);stop-opacity:1" />
                            </linearGradient>
                        </defs>
                        <rect width="400" height="300" fill="url(#roomGrad)"/>
                        <!-- Room building -->
                        <rect x="120" y="80" width="160" height="140" rx="10" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="2"/>
                        <path d="M 160 100 L 200 70 L 240 100 L 240 180 L 160 180 Z" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="2" stroke-linejoin="round"/>
                        <!-- Windows -->
                        <circle cx="185" cy="140" r="8" fill="none" stroke="rgba(96,165,250,0.4)" stroke-width="2"/>
                        <circle cx="215" cy="140" r="8" fill="none" stroke="rgba(96,165,250,0.4)" stroke-width="2"/>
                        <!-- Side windows -->
                        <rect x="145" y="115" width="12" height="12" fill="none" stroke="rgba(96,165,250,0.3)" stroke-width="2" rx="2"/>
                        <rect x="243" y="115" width="12" height="12" fill="none" stroke="rgba(96,165,250,0.3)" stroke-width="2" rx="2"/>
                        <!-- Door -->
                        <rect x="190" y="160" width="20" height="30" rx="3" fill="none" stroke="rgba(168,85,247,0.3)" stroke-width="2"/>
                        <!-- Decorative elements -->
                        <circle cx="80" cy="50" r="15" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="2"/>
                        <circle cx="320" cy="250" r="20" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="2"/>
                        <path d="M 30 220 Q 50 200 70 220" fill="none" stroke="rgba(34,197,94,0.2)" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <?php endif; ?>
                    <div class="room-status-badge">ว่าง</div>
                </div>
                <div class="room-card-header">
                    <div class="room-number">ห้อง <?php echo htmlspecialchars($room['room_number']); ?></div>
                    <div class="room-type"><?php echo htmlspecialchars($room['type_name'] ?? 'ห้องมาตรฐาน'); ?></div>
                </div>
                <div class="room-card-body">
                    <div class="room-price">
                        ฿<?php echo number_format($room['type_price'] ?? 0); ?> <span>/เดือน</span>
                    </div>
                    <ul class="room-features">
                        <li><svg class="feature-icon" viewBox="0 0 24 24" fill="none" stroke="#60a5fa" stroke-width="2"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg> ชั้น <?php echo htmlspecialchars($room['room_floor'] ?? '-'); ?></li>
                        <li><svg class="feature-icon" viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="2"><path d="M20 10c0-4.4-3.6-8-8-8s-8 3.6-8 8 3.6 8 8 8h8v-8z"/><path d="M4 18h2"/><path d="M18 18h2"/><path d="M12 18v2"/><path d="M4 14h2"/><path d="M4 10h2"/></svg> เฟอร์นิเจอร์ครบ</li>
                        <li><svg class="feature-icon" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> พร้อมเข้าอยู่ทันที</li>
                    </ul>
                    <a href="Public/booking.php?room=<?php echo $room['room_id']; ?>" class="btn-book">
                        <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> จองห้องนี้
                    </a>
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
            <h2>ที่อยู่หอพัก</h2>
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
                <a href="Login.php">เข้าสู่ระบบ</a>
            </div>
            <p style="margin-top: 2rem;">© <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName); ?> - สงวนลิขสิทธิ์</p>
        </div>
    </footer>

    <script>
        // Header scroll effect
        window.addEventListener('scroll', function() {
            const header = document.getElementById('header');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
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
        
        .leaflet-control-attribution {
            background: rgba(15, 23, 42, 0.8) !important;
            color: #94a3b8 !important;
            border-radius: 8px !important;
        }
        
        .leaflet-control {
            background: rgba(15, 23, 42, 0.9) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 8px !important;
        }
        
        .leaflet-control button {
            background: rgba(102, 126, 234, 0.2) !important;
            color: #667eea !important;
            border: none !important;
        }
        
        .leaflet-control button:hover {
            background: rgba(102, 126, 234, 0.3) !important;
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
        
        .leaflet-popup-content {
            color: #fff !important;
            margin: 0 !important;
            font-size: 0.9rem !important;
        }
        
        .leaflet-popup-tip {
            background: rgba(15, 23, 42, 0.95) !important;
            border: 1px solid rgba(102, 126, 234, 0.3) !important;
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
    </script>
</body>
</html>
