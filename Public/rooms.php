<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../ConnectDB.php';

$pdo = connectDB();

// ดึงค่าตั้งค่าระบบ
$siteName = 'Sangthian Dormitory';
$logoFilename = 'Logo.jpg';
$themeColor = '#1e40af';
$publicTheme = 'dark';
// Room features default
$roomFeatures = ['ไฟฟ้า', 'น้ำประปา', 'WiFi', 'เฟอร์นิเจอร์', 'แอร์', 'ตู้เย็น'];

try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename', 'theme_color', 'public_theme', 'room_features')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
        if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
        if ($row['setting_key'] === 'theme_color') $themeColor = $row['setting_value'];
        if ($row['setting_key'] === 'public_theme') $publicTheme = $row['setting_value'];
        if ($row['setting_key'] === 'room_features' && !empty($row['setting_value'])) {
            $roomFeatures = array_map('trim', explode(',', $row['setting_value']));
        }
    }
} catch (PDOException $e) { echo "Settings Error: " . $e->getMessage(); }

// ดึงข้อมูลห้องทั้งหมด
$rooms = [];
$roomTypes = [];
$debugInfo = '';
try {
    // ดึงประเภทห้อง
    $stmt = $pdo->query("SELECT * FROM roomtype ORDER BY type_name");
    $roomTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $debugInfo .= "RoomTypes: " . count($roomTypes) . " | ";
    
    // ดึงห้องทั้งหมด
    $stmt = $pdo->query("SELECT r.*, rt.type_name, rt.type_price FROM room r LEFT JOIN roomtype rt ON r.type_id = rt.type_id ORDER BY CAST(r.room_number AS UNSIGNED) ASC");
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $debugInfo .= "Rooms: " . count($rooms);
} catch (PDOException $e) { 
    $debugInfo = "Query Error: " . $e->getMessage(); 
}

// Filter by type
$filterType = $_GET['type'] ?? '';
if ($filterType !== '') {
    $rooms = array_filter($rooms, fn($r) => $r['type_id'] == $filterType);
    $rooms = array_values($rooms); // reindex array
}

// Filter by status (0 = ว่าง, 1 = ไม่ว่าง)
$filterStatus = $_GET['status'] ?? '';
if ($filterStatus !== '') {
    $rooms = array_filter($rooms, fn($r) => (string)$r['room_status'] === $filterStatus);
    $rooms = array_values($rooms); // reindex array
}

// Count available and occupied
$availableCount = count(array_filter($rooms, fn($r) => $r['room_status'] === '0'));
$occupiedCount = count(array_filter($rooms, fn($r) => $r['room_status'] === '1'));
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อมูลห้องพัก - <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include_once __DIR__ . '/../includes/public_theme.php'; ?>
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
            filter: blur(80px);
            opacity: 0.5;
            animation: floatOrb 20s ease-in-out infinite;
        }

        .orb-1 {
            width: 400px;
            height: 400px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            top: -100px;
            right: -100px;
            animation-delay: 0s;
        }

        .orb-2 {
            width: 300px;
            height: 300px;
            background: linear-gradient(135deg, #f093fb, #f5576c);
            bottom: -50px;
            left: -50px;
            animation-delay: -7s;
        }

        .orb-3 {
            width: 250px;
            height: 250px;
            background: linear-gradient(135deg, #4facfe, #00f2fe);
            top: 50%;
            left: 50%;
            animation-delay: -14s;
        }

        @keyframes floatOrb {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(50px, -50px) scale(1.1); }
            50% { transform: translate(-30px, 30px) scale(0.9); }
            75% { transform: translate(-50px, -30px) scale(1.05); }
        }

        .grid-lines {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            z-index: -1;
        }

        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 50%;
            animation: particleFloat 15s linear infinite;
        }

        .particle:nth-child(1) { left: 10%; animation-delay: 0s; animation-duration: 12s; }
        .particle:nth-child(2) { left: 20%; animation-delay: -2s; animation-duration: 14s; }
        .particle:nth-child(3) { left: 30%; animation-delay: -4s; animation-duration: 16s; }
        .particle:nth-child(4) { left: 40%; animation-delay: -6s; animation-duration: 13s; }
        .particle:nth-child(5) { left: 50%; animation-delay: -8s; animation-duration: 15s; }
        .particle:nth-child(6) { left: 60%; animation-delay: -10s; animation-duration: 11s; }
        .particle:nth-child(7) { left: 70%; animation-delay: -12s; animation-duration: 17s; }
        .particle:nth-child(8) { left: 80%; animation-delay: -14s; animation-duration: 14s; }
        .particle:nth-child(9) { left: 90%; animation-delay: -16s; animation-duration: 12s; }

        @keyframes particleFloat {
            0% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-100vh) rotate(720deg); opacity: 0; }
        }

        /* Header */
        .header {
            background: rgba(10, 10, 15, 0.8);
            backdrop-filter: blur(20px);
            padding: 1rem 2rem;
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s ease;
        }

        .header.scrolled {
            background: rgba(10, 10, 15, 0.95);
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }

        .logo { 
            display: flex; 
            align-items: center; 
            gap: 1rem; 
            text-decoration: none; 
        }
        .logo img { 
            width: 45px; 
            height: 45px; 
            border-radius: 12px; 
            object-fit: cover;
            border: 2px solid rgba(255,255,255,0.2);
            box-shadow: 0 0 20px rgba(102, 126, 234, 0.3);
        }
        .logo h1 { 
            font-size: 1.25rem; 
            color: #fff;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-links { display: flex; gap: 0.5rem; align-items: center; }
        .nav-links a {
            color: #94a3b8;
            text-decoration: none;
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            transition: all 0.3s;
            font-size: 0.95rem;
            position: relative;
            overflow: hidden;
        }
        .nav-links a::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s;
        }
        .nav-links a:hover::before {
            left: 100%;
        }
        .nav-links a:hover { 
            color: #fff; 
            background: rgba(255,255,255,0.1);
            transform: translateY(-2px);
        }
        
        /* Nav Icons */
        .nav-icon {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
            flex-shrink: 0;
        }
        .nav-links a {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        @keyframes iconDraw {
            from { stroke-dashoffset: 100; }
            to { stroke-dashoffset: 0; }
        }
        .nav-links a:hover .nav-icon {
            animation: iconDraw 0.6s ease forwards;
            stroke-dasharray: 100;
        }

        /* Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 7rem 1.5rem 3rem;
            position: relative;
            z-index: 1;
        }

        /* Back Link */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #60a5fa;
            text-decoration: none;
            margin-bottom: 2rem;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            background: rgba(96, 165, 250, 0.1);
            border: 1px solid rgba(96, 165, 250, 0.2);
            transition: all 0.3s;
        }
        .back-link:hover {
            background: rgba(96, 165, 250, 0.2);
            transform: translateX(-5px);
        }

        /* Page Title */
        .page-title {
            text-align: center;
            margin-bottom: 2rem;
        }
        .page-title .label {
            display: inline-block;
            padding: 0.5rem 1.5rem;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2), rgba(118, 75, 162, 0.2));
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 50px;
            font-size: 0.9rem;
            color: #a78bfa;
            margin-bottom: 1rem;
        }
        .page-title h2 { 
            font-size: 2.5rem; 
            margin-bottom: 0.75rem;
            background: linear-gradient(135deg, #fff 0%, #a78bfa 50%, #60a5fa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .page-title p { 
            color: #94a3b8;
            font-size: 1.1rem;
        }

        /* Stats Bar */
        .stats-bar {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.5rem;
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .stat-item .icon {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 1.5rem;
        }

        .stat-item.available .icon {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(74, 222, 128, 0.2));
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .stat-item.occupied .icon {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(248, 113, 113, 0.2));
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .stat-item.total .icon {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2), rgba(118, 75, 162, 0.2));
            border: 1px solid rgba(102, 126, 234, 0.3);
        }

        .stat-item .info .number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
        }

        .stat-item .info .label {
            font-size: 0.85rem;
            color: #94a3b8;
        }

        /* Filters */
        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            justify-content: center;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .filters select {
            padding: 0.875rem 1.25rem;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.05);
            color: #fff;
            font-size: 1rem;
            min-width: 200px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filters select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }

        .filters select option {
            background: #1a1a2e;
            color: #fff;
        }

        /* ===== ANIMATIONS ===== */
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
            0%, 100% { transform: translateY(0) scale(1); opacity: 0.6; }
            50% { transform: translateY(-20px) scale(1.2); opacity: 1; }
        }
        
        @keyframes iconBounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        /* Room Grid */
        .room-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 2.5rem;
            margin-top: 1rem;
        }
        
        /* Desktop: 5 cards per row */
        @media (min-width: 1400px) {
            .room-grid {
                grid-template-columns: repeat(5, minmax(0, 1fr));
                gap: 2.5rem;
            }
        }
        @media (min-width: 1200px) and (max-width: 1399px) {
            .room-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 2rem;
            }
        }
        @media (max-width: 1199px) and (min-width: 768px) {
            .room-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 1.5rem;
            }
        }
        @media (max-width: 767px) and (min-width: 481px) {
            .room-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 1.25rem;
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

        /* ===== ENHANCED ROOM CARD WITH WEB3 STYLE ===== */
        .room-card {
            position: relative;
            border-radius: 24px;
            background: transparent;
            box-shadow: 0 15px 35px rgba(3,7,18,0.4);
            color: #f5f8ff;
            /* Web3 Portrait Card Style - ratio ~7:10 */
            aspect-ratio: 1137 / 1606;
            min-height: 280px;
            transition: transform 0.5s cubic-bezier(0.23, 1, 0.32, 1), filter 0.3s ease;
            will-change: transform, filter;
            /* Living breathing animation */
            animation: fadeInUp 0.6s ease-out backwards,
                       breathe 4s ease-in-out infinite,
                       floatOrbit 12s ease-in-out infinite;
            overflow: visible;
        }
        
        /* Each card has unique animation timing */
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
        
        .room-card:nth-child(4n) {
            animation: fadeInUp 0.6s ease-out backwards,
                       breathe 3.5s ease-in-out infinite 1s,
                       floatOrbit 10s ease-in-out infinite reverse;
        }
        
        /* Staggered entrance animations */
        .room-card:nth-child(1) { animation-delay: 0.1s, 0s, 0s; }
        .room-card:nth-child(2) { animation-delay: 0.15s, 0.3s, 0.5s; }
        .room-card:nth-child(3) { animation-delay: 0.2s, 0.6s, 1s; }
        .room-card:nth-child(4) { animation-delay: 0.25s, 0.9s, 1.5s; }
        .room-card:nth-child(5) { animation-delay: 0.3s, 1.2s, 2s; }
        .room-card:nth-child(6) { animation-delay: 0.35s, 0.2s, 0.7s; }
        .room-card:nth-child(7) { animation-delay: 0.4s, 0.5s, 1.2s; }
        .room-card:nth-child(8) { animation-delay: 0.45s, 0.8s, 1.7s; }
        .room-card:nth-child(9) { animation-delay: 0.5s, 1.1s, 2.2s; }
        .room-card:nth-child(10) { animation-delay: 0.55s, 0.4s, 0.9s; }
        
        /* Card Glow Effect - Aurora Borealis Style */
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
        
        /* Shimmer overlay */
        .room-card::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 24px;
            background: linear-gradient(105deg, 
                transparent 20%, 
                rgba(255,255,255,0.03) 35%,
                rgba(255,255,255,0.15) 50%,
                rgba(255,255,255,0.03) 65%,
                transparent 80%);
            background-size: 300% 100%;
            opacity: 0;
            pointer-events: none;
            z-index: 5;
        }
        
        .room-card:hover::before {
            opacity: 0.6;
            filter: blur(18px);
        }
        
        .room-card:hover {
            animation-play-state: paused;
            transform: translateY(-8px) scale(1.02);
        }
        
        /* Flipped card state */
        .room-card.flipped {
            animation-play-state: paused;
        }
        
        .room-card.flipped:hover {
            transform: translateY(-8px) scale(1.02);
        }
        
        /* Particle effects on cards - floating circles */
        .room-card .card-particles {
            position: absolute;
            inset: 0;
            pointer-events: none;
            overflow: visible;
            border-radius: 24px;
            z-index: 10; /* In front of card faces */
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
            background: radial-gradient(circle, rgba(255, 255, 255, 0.7) 0%, rgba(255, 255, 255, 0.3) 50%, transparent 70%);
            box-shadow: 0 0 25px rgba(255, 255, 255, 0.5);
        }
        
        .room-card .card-particles::after {
            bottom: 15%;
            right: 10%;
            width: 14px;
            height: 14px;
            animation-delay: 2s;
            animation-duration: 5s;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.7) 0%, rgba(255, 255, 255, 0.3) 50%, transparent 70%);
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.5);
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
        
        /* Status badge top right - with pulse animation */
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
        
        .status-badge-web3.occupied {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.9), rgba(220, 38, 38, 0.9));
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.5),
                        0 0 30px rgba(239, 68, 68, 0.3);
        }
        
        .status-badge-web3.occupied::before {
            background: linear-gradient(135deg, #ef4444, #dc2626, #b91c1c);
        }
        
        .room-price-web3 {
            font-size: 1.15rem;
            font-weight: 600;
            color: #ffffff;
            margin-top: 0.15rem;
            text-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }

        /* Hide old room-body (use new card layout) */
        .room-body {
            display: none;
        }
        
        /* Placeholder styling when no image */
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

        /* ===== FEATURE TAGS ===== */
        .room-features {
            display: none; /* Hidden in new card design */
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

        /* Hide old status badge */
        .status-badge {
            display: none;
        }

        /* Hide old btn-book (will add new one) */
        .btn-book {
            display: none;
        }

        .room-info {
            display: none;
        }

        /* ===== SECTION HEADER WITH VIEW TOGGLE ===== */
        .rooms-section {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .section-header h1 {
            background: linear-gradient(135deg, #fff 0%, #a78bfa 50%, #60a5fa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .view-toggle {
            display: flex;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 4px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .toggle-view-btn {
            padding: 0.5rem 1.25rem;
            border: none;
            background: transparent;
            color: #94a3b8;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 0.9rem;
            font-family: inherit;
        }
        
        .toggle-view-btn:hover {
            color: #fff;
        }
        
        .toggle-view-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        /* ===== CUSTOM SCROLLBAR ===== */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            border: 2px solid transparent;
            background-clip: padding-box;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, #7c8ff8 0%, #8b5fbf 100%);
            background-clip: padding-box;
        }
        
        ::-webkit-scrollbar-corner {
            background: transparent;
        }
        
        /* Firefox scrollbar */
        * {
            scrollbar-width: thin;
            scrollbar-color: #764ba2 rgba(255, 255, 255, 0.05);
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
        
        .back-card-content::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, #c4b5fd 0%, #a78bfa 100%);
        }

        /* ===== CARD FLIP 3D EFFECT ===== */
        .room-card {
            perspective: 1000px;
        }
        
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
        
        /* ===== FRONT CARD STYLES ===== */
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
        
        /* Gradient overlay for text */
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

        /* ===== BACK CARD STYLES ===== */
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
        
        .availability-badge.occupied {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(220, 38, 38, 0.2));
            border-color: rgba(239, 68, 68, 0.3);
            color: #f87171;
        }
        
        .back-details {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.5rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .detail-item.highlight {
            background: linear-gradient(135deg, rgba(167, 139, 250, 0.15), rgba(139, 92, 246, 0.1));
            border-color: rgba(167, 139, 250, 0.2);
        }
        
        .detail-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            flex-shrink: 0;
        }
        
        .detail-icon svg {
            width: 16px;
            height: 16px;
            color: #a78bfa;
        }
        
        .detail-text {
            display: flex;
            flex-direction: column;
            gap: 0.1rem;
            min-width: 0;
        }
        
        .detail-label {
            font-size: 0.65rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .detail-value {
            font-size: 0.85rem;
            color: #fff;
            font-weight: 500;
        }
        
        .detail-value.price {
            color: #a78bfa;
            font-weight: 600;
        }
        
        .back-features {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-top: auto;
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

        /* List view styles removed */

        /* ===== LOAD MORE BUTTON ===== */
        .load-more-container {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
        }
        
        .load-more-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 2rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #94a3b8;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .load-more-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border-color: rgba(167, 139, 250, 0.3);
            transform: translateY(-2px);
        }
        
        .load-more-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Hidden rooms for load more */
        .room-card.hidden-room {
            display: none;
        }

        /* ===== BOOK OVERLAY BUTTON ===== */
        .card-book-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 24px;
            z-index: 10;
            text-decoration: none;
            cursor: pointer;
        }
        
        .room-card:hover .card-book-overlay {
            opacity: 1;
        }
        
        .book-text {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border-radius: 12px;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            transform: translateY(20px);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 30px rgba(59, 130, 246, 0.5);
        }
        
        .room-card:hover .book-text {
            transform: translateY(0);
        }
        
        .book-text svg {
            width: 20px;
            height: 20px;
        }
        
        .book-text:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            transform: scale(1.05);
        }
        
        /* Occupied card - no hover effect */
        .room-card:has(.status-badge-web3.occupied) {
            cursor: default;
        }
        
        .room-card:has(.status-badge-web3.occupied):hover {
            transform: translateY(-4px) scale(1.01);
        }
        
        .room-card:has(.status-badge-web3.occupied)::before {
            opacity: 0.2;
        }
        
        .room-card:has(.status-badge-web3.occupied):hover::before {
            opacity: 0.3;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,0.1);
            grid-column: 1 / -1;
        }
        .empty-state .icon {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            display: block;
        }
        .empty-state p {
            color: #94a3b8;
            font-size: 1.2rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,0.1);
            grid-column: 1 / -1;
        }
        .empty-state .icon {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            display: block;
        }
        .empty-state p {
            color: #94a3b8;
            font-size: 1.2rem;
        }

        /* Header hide/show on scroll */
        .header {
            transition: transform 0.3s ease, background 0.3s ease;
        }
        .header.hide {
            transform: translateY(-100%);
        }

        /* Responsive */
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
            .container {
                padding-top: 8rem;
            }
            .page-title h2 {
                font-size: 1.75rem;
            }
            .stats-bar {
                gap: 1rem;
            }
            .stat-item {
                padding: 0.75rem 1rem;
            }
            .filters {
                flex-direction: column;
            }
            .filters select {
                width: 100%;
            }
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
                        radial-gradient(ellipse at 70% 60%, rgba(139, 92, 246, 0.06), transparent 50%);
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
        }
        body.theme-light .header {
            background: rgba(255, 255, 255, 0.9);
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
        }
        body.theme-light .logo h1 {
            color: #1e293b !important;
            background: none !important;
            -webkit-background-clip: unset !important;
            -webkit-text-fill-color: #1e293b !important;
        }
        body.theme-light .nav-links a {
            color: #475569;
        }
        body.theme-light .nav-links a:hover {
            color: #667eea;
        }
        body.theme-light .page-title h2 {
            color: #1e293b !important;
            background: none !important;
            -webkit-background-clip: unset !important;
            -webkit-text-fill-color: #1e293b !important;
        }
        body.theme-light .page-title p {
            color: #64748b !important;
        }
        body.theme-light .page-title .label {
            background: rgba(102, 126, 234, 0.1);
            border-color: rgba(102, 126, 234, 0.2);
            color: #6366f1 !important;
        }
        body.theme-light .stats-bar {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.2);
        }
        body.theme-light .stat-item {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(148, 163, 184, 0.2);
        }
        body.theme-light .stat-item .info .number {
            color: #1e293b !important;
        }
        body.theme-light .stat-item .info .label {
            color: #64748b !important;
        }
        body.theme-light .filters {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.2);
        }
        body.theme-light .filters select {
            background: rgba(255, 255, 255, 0.95);
            border-color: rgba(148, 163, 184, 0.3);
            color: #1e293b;
        }
        body.theme-light .back-link {
            background: rgba(59, 130, 246, 0.1);
            border-color: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }
        
        /* Light theme - Room Card */
        body.theme-light .room-card {
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        body.theme-light .room-card::before {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6, #22c55e) !important;
            opacity: 0.15;
        }
        body.theme-light .room-card-face.front {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%) !important;
            border: 1px solid rgba(148, 163, 184, 0.3);
        }
        body.theme-light .room-image-container {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 50%, #818cf8 100%) !important;
        }
        body.theme-light .room-number-web3 {
            color: #ffffff !important;
            text-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        body.theme-light .room-type-web3 {
            color: rgba(255,255,255,0.9) !important;
        }
        body.theme-light .room-price-web3 {
            color: #ffffff !important;
        }
        body.theme-light .status-badge-web3 {
            color: #ffffff !important;
        }
        body.theme-light .feature-tag {
            background: rgba(139, 92, 246, 0.1);
            color: #7c3aed;
        }
        body.theme-light .empty-state {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.2);
        }
        body.theme-light .empty-state p {
            color: #64748b !important;
        }
        
        /* Light theme - Section Header & View Toggle */
        body.theme-light .rooms-section {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(148, 163, 184, 0.2);
        }
        body.theme-light .section-header h1 {
            color: #1e293b !important;
            background: none !important;
            -webkit-background-clip: unset !important;
            -webkit-text-fill-color: #1e293b !important;
        }
        /* Light theme scrollbar */
        body.theme-light::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
        }
        body.theme-light::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #6366f1 0%, #8b5cf6 100%);
        }
        body.theme-light .back-card-content::-webkit-scrollbar-track {
            background: rgba(99, 102, 241, 0.1);
        }
        
        /* Light theme - Back Card */
        body.theme-light .room-card-face.back {
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 50%, #f1f5f9 100%);
            border: 1px solid rgba(148, 163, 184, 0.3);
        }
        body.theme-light .room-number-back {
            color: #1e293b;
        }
        body.theme-light .room-number-back svg {
            color: #6366f1;
        }
        body.theme-light .availability-badge {
            background: rgba(34, 197, 94, 0.1);
            border-color: rgba(34, 197, 94, 0.2);
            color: #16a34a;
        }
        body.theme-light .availability-badge.occupied {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.2);
            color: #dc2626;
        }
        body.theme-light .detail-item {
            background: rgba(0, 0, 0, 0.02);
            border-color: rgba(148, 163, 184, 0.1);
        }
        body.theme-light .detail-item.highlight {
            background: rgba(99, 102, 241, 0.08);
            border-color: rgba(99, 102, 241, 0.15);
        }
        body.theme-light .detail-icon {
            background: rgba(99, 102, 241, 0.1);
        }
        body.theme-light .detail-icon svg {
            color: #6366f1;
        }
        body.theme-light .detail-label {
            color: #64748b;
        }
        body.theme-light .detail-value {
            color: #1e293b;
        }
        body.theme-light .detail-value.price {
            color: #6366f1;
        }
        body.theme-light .back-actions {
            border-top-color: rgba(148, 163, 184, 0.2);
        }
        
        /* Light theme list view styles removed */
        
        /* Light theme - Load More */
        body.theme-light .load-more-container {
            background: transparent;
        }
        body.theme-light .load-more-btn {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(148, 163, 184, 0.3);
            color: #64748b;
        }
        body.theme-light .load-more-btn:hover {
            background: rgba(99, 102, 241, 0.1);
            border-color: rgba(99, 102, 241, 0.3);
            color: #6366f1;
        }
    </style>
</head>
<?php
// กำหนด theme class
$themeClass = '';
if ($publicTheme === 'light') {
    $themeClass = 'theme-light';
} elseif ($publicTheme === 'auto') {
    $themeClass = '';
}
?>
<body class="<?php echo $themeClass; ?>" data-theme-mode="<?php echo $publicTheme; ?>">
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

    <!-- Header -->
    <header class="header" id="header">
        <a href="../index.php" class="logo">
            <img src="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="">
            <h1><?php echo htmlspecialchars($siteName); ?></h1>
        </a>
        <nav class="nav-links">
            <a href="../index.php">
                <svg class="nav-icon" viewBox="0 0 24 24">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                หน้าแรก
            </a>
            <a href="rooms.php">
                <svg class="nav-icon" viewBox="0 0 24 24">
                    <path d="M3 21h18"/>
                    <path d="M5 21V7l8-4 8 4v14"/>
                    <path d="M9 21v-4h6v4"/>
                    <path d="M10 9h4"/>
                    <path d="M10 13h4"/>
                </svg>
                ห้องพัก
            </a>
            <a href="news.php">
                <svg class="nav-icon" viewBox="0 0 24 24">
                    <path d="M4 22h16a2 2 0 0 0 2-2V4H6a2 2 0 0 0-2 2v14c0 1.1-.9 2-2 2"/>
                    <path d="M18 2v20"/>
                    <path d="M8 6h6"/>
                    <path d="M8 10h8"/>
                    <path d="M8 14h6"/>
                </svg>
                ข่าวสาร
            </a>
            <a href="booking.php">
                <svg class="nav-icon" viewBox="0 0 24 24">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                    <path d="M8 14h.01"/>
                    <path d="M12 14h.01"/>
                    <path d="M16 14h.01"/>
                    <path d="M8 18h.01"/>
                    <path d="M12 18h.01"/>
                </svg>
                จองห้อง
            </a>
        </nav>
    </header>

    <div class="container">
        <a href="../index.php" class="back-link">← กลับหน้าแรก</a>
        
        <div class="page-title">
            <span class="label">
                <svg class="nav-icon" viewBox="0 0 24 24" style="width:16px;height:16px;vertical-align:middle;margin-right:4px;">
                    <path d="M3 21h18"/>
                    <path d="M5 21V7l8-4 8 4v14"/>
                    <path d="M9 21v-4h6v4"/>
                    <path d="M10 9h4"/>
                    <path d="M10 13h4"/>
                </svg>
                ห้องพัก
            </span>
            <h2>รายงานข้อมูลห้องพัก</h2>
            <p>รายละเอียดห้องพักทั้งหมดในหอพัก</p>
        </div>

        <div class="stats-bar">
            <div class="stat-item total">
                <div class="icon">
                    <svg class="nav-icon" viewBox="0 0 24 24" style="width:24px;height:24px;">
                        <path d="M3 21h18"/>
                        <path d="M5 21V7l8-4 8 4v14"/>
                        <path d="M9 21v-4h6v4"/>
                        <path d="M10 9h4"/>
                        <path d="M10 13h4"/>
                    </svg>
                </div>
                <div class="info">
                    <div class="number"><?php echo count($rooms); ?></div>
                    <div class="label">ห้องทั้งหมด</div>
                </div>
            </div>
            <div class="stat-item available">
                <div class="icon">
                    <svg class="nav-icon" viewBox="0 0 24 24" style="width:24px;height:24px;">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
                <div class="info">
                    <div class="number"><?php echo $availableCount; ?></div>
                    <div class="label">ห้องว่าง</div>
                </div>
            </div>
            <div class="stat-item occupied">
                <div class="icon">
                    <svg class="nav-icon" viewBox="0 0 24 24" style="width:24px;height:24px;">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>
                <div class="info">
                    <div class="number"><?php echo $occupiedCount; ?></div>
                    <div class="label">มีผู้เช่า</div>
                </div>
            </div>
        </div>
        
        <!-- Filters Section with View Toggle -->
        <section class="rooms-section" id="roomsSection">
            <div class="section-header">
                <div>
                    <h1 style="font-size:1.5rem;color:#fff;margin:0;">รายการห้องพัก</h1>
                </div>
            </div>
            
            <form class="filters" method="get" style="margin-top:1rem;">
                <select name="type" onchange="this.form.submit()">
                    <option value="">◎ ประเภทห้องทั้งหมด</option>
                    <?php foreach ($roomTypes as $type): ?>
                    <option value="<?php echo $type['type_id']; ?>" <?php echo $filterType == $type['type_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($type['type_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="status" onchange="this.form.submit()">
                    <option value="">☰ สถานะทั้งหมด</option>
                    <option value="0" <?php echo $filterStatus === '0' ? 'selected' : ''; ?>>◉ ว่าง</option>
                    <option value="1" <?php echo $filterStatus === '1' ? 'selected' : ''; ?>>◈ มีผู้เช่า</option>
                </select>
            </form>
        </section>
        
        <div class="room-grid" id="roomsGrid" aria-live="polite">
            <?php if (count($rooms) > 0): ?>
                <?php 
                // SVG icons for features
                $featureIcons = [
                    'ไฟฟ้า' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path></svg>',
                    'น้ำประปา' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path></svg>',
                    'WiFi' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;"><path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"></path></svg>',
                    'แอร์' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;"><path d="M8 16a4 4 0 0 1-4-4 4 4 0 0 1 4-4h8a4 4 0 0 1 4 4 4 4 0 0 1-4 4H8zm0-8v12M16 8v12"></path></svg>',
                    'เฟอร์นิเจอร์' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;"><rect x="2" y="6" width="20" height="8" rx="2"></rect><path d="M4 14v4M20 14v4M2 10h20"></path></svg>',
                    'ที่จอดรถ' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;"><rect x="1" y="3" width="15" height="13" rx="2"></rect><path d="M16 8h3l3 3v5h-2M8 16h8M7 16a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM16 16a2 2 0 1 0 0 4 2 2 0 0 0 0-4z"></path></svg>',
                    'กล้องวงจรปิด' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>',
                    'ตู้เย็น' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;"><rect x="4" y="2" width="16" height="20" rx="2"></rect><line x1="4" y1="10" x2="20" y2="10"></line><line x1="10" y1="6" x2="10" y2="6"></line><line x1="10" y1="14" x2="10" y2="18"></line></svg>',
                    'default' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>'
                ];
                ?>
                <?php foreach ($rooms as $index => $room): ?>
                <div class="room-card <?php echo $index >= 10 ? 'hidden-room' : ''; ?>" data-index="<?php echo $index; ?>" style="cursor: <?php echo $room['room_status'] === '0' ? 'pointer' : 'default'; ?>;">
                    <!-- Particle effects -->
                    <div class="card-particles"></div>
                    
                    <div class="room-card-inner">
                        <div class="room-card-face front">
                            <!-- Status Badge -->
                            <span class="status-badge-web3 <?php echo $room['room_status'] === '1' ? 'occupied' : ''; ?>">
                                <?php echo $room['room_status'] === '0' ? 'ว่าง' : 'ไม่ว่าง'; ?>
                            </span>
                            
                            <!-- Room Image Container -->
                            <div class="room-image-container">
                                <?php if (!empty($room['room_image'])): 
                                    $img = basename($room['room_image']); 
                                ?>
                                    <img src="/dormitory_management/Public/Assets/Images/Rooms/<?php echo htmlspecialchars($img); ?>" alt="รูปห้อง <?php echo $room['room_number']; ?>">
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
                                <div class="room-type-web3"><?php echo htmlspecialchars($room['type_name'] ?? 'มาตรฐาน'); ?></div>
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
                                    <span class="availability-badge <?php echo $room['room_status'] === '1' ? 'occupied' : ''; ?>">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;">
                                            <?php if ($room['room_status'] === '0'): ?>
                                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                            <?php else: ?>
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <line x1="15" y1="9" x2="9" y2="15"></line>
                                            <line x1="9" y1="9" x2="15" y2="15"></line>
                                            <?php endif; ?>
                                        </svg>
                                        <?php echo $room['room_status'] === '0' ? 'พร้อมให้เช่า' : 'มีผู้เช่าแล้ว'; ?>
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
                                            <span class="detail-value"><?php echo htmlspecialchars($room['type_name'] ?? 'มาตรฐาน'); ?></span>
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
                                    <?php foreach ($roomFeatures as $feature): 
                                        $icon = $featureIcons[$feature] ?? $featureIcons['default'];
                                    ?>
                                    <span class="feature-tag"><?php echo $icon; ?> <?php echo htmlspecialchars($feature); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <?php if ($room['room_status'] === '0'): ?>
                            <div class="back-actions">
                                <a href="booking.php?room=<?php echo $room['room_id']; ?>" class="book-btn book-btn-back">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                        <line x1="16" y1="2" x2="16" y2="6"></line>
                                        <line x1="8" y1="2" x2="8" y2="6"></line>
                                        <line x1="3" y1="10" x2="21" y2="10"></line>
                                    </svg>
                                    จองห้องนี้
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <span class="icon">
                        <svg class="nav-icon" viewBox="0 0 24 24" style="width:48px;height:48px;">
                            <circle cx="11" cy="11" r="8"/>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                    </span>
                    <p>ไม่พบห้องพักตามเงื่อนไขที่เลือก</p>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (count($rooms) > 10): ?>
        <div class="load-more-container">
            <button type="button" class="load-more-btn" id="loadMoreBtn" onclick="loadMoreRooms()">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                <span id="loadMoreText">โหลดเพิ่มเติม (<span id="remainingCount"><?php echo count($rooms) - 10; ?></span> ห้อง)</span>
            </button>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // ===== LOAD MORE FUNCTIONALITY =====
        let visibleRooms = 10;
        const roomsPerLoad = 10;
        
        function loadMoreRooms() {
            const hiddenRooms = document.querySelectorAll('.room-card.hidden-room');
            const btn = document.getElementById('loadMoreBtn');
            const countSpan = document.getElementById('remainingCount');
            
            let shown = 0;
            hiddenRooms.forEach((card, index) => {
                if (shown < roomsPerLoad) {
                    card.classList.remove('hidden-room');
                    card.style.animationDelay = `${index * 0.08}s`;
                    shown++;
                }
            });
            
            visibleRooms += shown;
            
            // Update remaining count
            const stillHidden = document.querySelectorAll('.room-card.hidden-room').length;
            if (stillHidden > 0) {
                countSpan.textContent = stillHidden;
            } else {
                btn.style.display = 'none';
            }
            
            // Re-setup card flip for new cards
            setupCardFlip();
        }

        // ===== HEADER SCROLL EFFECT =====
        window.addEventListener('scroll', function() {
            const header = document.getElementById('header');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
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
                    // Clear timer
                    const timer = flipTimers.get(card);
                    if (timer) {
                        clearTimeout(timer);
                        flipTimers.delete(card);
                    }
                    // Unflip immediately
                    card.classList.remove('flipped');
                });
            });
        }

        // ===== ANIMATE CARDS ON SCROLL =====
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    const card = entry.target;
                    const delay = (parseInt(card.style.getPropertyValue('--index')) || 0) * 0.08;
                    card.style.animationDelay = `${delay}s, 0s, 0s`;
                    card.style.animationPlayState = 'running';
                    card.classList.add('visible');
                }
            });
        }, observerOptions);

        // ===== RIPPLE EFFECT =====
        function addRippleEffect(card, e) {
            if (e.target.closest('.card-book-overlay')) return;
            
            const ripple = document.createElement('span');
            ripple.classList.add('card-ripple');
            
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            
            card.appendChild(ripple);
            setTimeout(() => ripple.remove(), 600);
        }

        // ===== 3D PARALLAX EFFECT =====
        function add3DEffect(card, e) {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            
            const rotateX = (y - centerY) / 25;
            const rotateY = (centerX - x) / 25;
            
            card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-8px) scale(1.02)`;
        }

        function reset3DEffect(card) {
            card.style.transform = '';
        }

        // ===== THEME DETECTION =====
        function updateCardTheme() {
            const bodyBg = getComputedStyle(document.body).backgroundColor;
            const isLightTheme = bodyBg === 'rgb(255, 255, 255)' || 
                                 bodyBg === '#fff' || 
                                 bodyBg === '#ffffff' ||
                                 document.body.classList.contains('theme-light');
            
            if (isLightTheme) {
                document.body.classList.add('live-light');
            } else {
                document.body.classList.remove('live-light');
            }
        }

        // ===== INITIALIZE ON DOM READY =====
        document.addEventListener('DOMContentLoaded', function() {
            // Setup card flip
            setupCardFlip();
            
            // Setup scroll animations
            document.querySelectorAll('.room-card').forEach((card, index) => {
                card.style.setProperty('--index', index);
                card.style.animationPlayState = 'paused';
                observer.observe(card);
                
                // Add click ripple
                card.addEventListener('click', function(e) {
                    addRippleEffect(this, e);
                });
                
                // Add 3D parallax on mouse move
                card.addEventListener('mousemove', function(e) {
                    add3DEffect(this, e);
                });
                
                card.addEventListener('mouseleave', function() {
                    reset3DEffect(this);
                });
            });

            // Theme detection
            updateCardTheme();
            
            // Watch for theme changes
            const themeObserver = new MutationObserver(() => {
                updateCardTheme();
            });
            themeObserver.observe(document.body, { 
                attributes: true, 
                attributeFilter: ['class', 'style'] 
            });

            // Re-setup after any dynamic content changes
            const roomGrid = document.querySelector('.room-grid');
            if (roomGrid) {
                const gridObserver = new MutationObserver(() => {
                    setupCardFlip();
                });
                gridObserver.observe(roomGrid, { childList: true, subtree: true });
            }

            console.log('✅ Room cards initialized with animations');
        });

        // ===== PRELOAD BOOKING PAGE FOR FASTER NAVIGATION =====
        document.querySelectorAll('.card-book-overlay').forEach(link => {
            link.addEventListener('mouseenter', function() {
                const href = this.getAttribute('href');
                if (href && !document.querySelector(`link[rel="prefetch"][href="${href}"]`)) {
                    const prefetch = document.createElement('link');
                    prefetch.rel = 'prefetch';
                    prefetch.href = href;
                    document.head.appendChild(prefetch);
                }
            });
        });
    </script>
    
    <style>
        /* Ripple effect */
        .card-ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.4);
            transform: scale(0);
            animation: ripple 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            pointer-events: none;
            z-index: 20;
            width: 100px;
            height: 100px;
            margin-left: -50px;
            margin-top: -50px;
        }
        
        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
        
        /* Card visible state */
        .room-card.visible {
            opacity: 1;
        }
        
        /* Card flipped state - for future flip feature */
        .room-card.flipped {
            /* transform: rotateY(180deg); */
        }
        
        .room-card.was-flipped {
            animation: none !important;
        }
        
        /* Live light theme detection */
        body.live-light .room-card::before {
            opacity: 0.15;
        }
        
        body.live-light .card-book-overlay {
            background: rgba(255, 255, 255, 0.85);
        }
        
        body.live-light .book-text {
            color: white;
        }
    </style>
</body>
</html>
