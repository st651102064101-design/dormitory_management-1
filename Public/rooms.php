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
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename', 'theme_color', 'public_theme')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
        if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
        if ($row['setting_key'] === 'theme_color') $themeColor = $row['setting_value'];
        if ($row['setting_key'] === 'public_theme') $publicTheme = $row['setting_value'];
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
    <link rel="icon" type="image/jpeg" href="../Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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

        /* Room Grid */
        .room-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        /* Room Card */
        .room-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.4s ease;
            animation: fadeInUp 0.6s ease forwards;
            animation-delay: calc(var(--index, 0) * 0.05s);
            opacity: 0;
        }

        .room-card:hover {
            border-color: rgba(102, 126, 234, 0.5);
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 30px 60px rgba(102, 126, 234, 0.2);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .room-header {
            padding: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
            min-height: 250px;
            display: flex;
            align-items: flex-end;
            justify-content: center;
        }
        
        .room-image {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 0;
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
        
        .room-placeholder-svg {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }

        /* Background color for placeholder only */
        .room-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            opacity: 0.9;
            z-index: 0;
        }

        .room-header[data-status="0"]::before {
            background: linear-gradient(135deg, #22c55e, #16a34a, #15803d);
        }

        .room-header[data-status="1"]::before {
            background: linear-gradient(135deg, #ef4444, #dc2626, #b91c1c);
        }
        
        /* Dark overlay for text readability */
        .room-header::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to bottom, rgba(0,0,0,0.2), rgba(0,0,0,0.5));
            z-index: 2;
        }

        .room-header .content {
            position: relative;
            z-index: 3;
        }

        .room-number {
            font-size: 2rem;
            font-weight: 800;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .room-type {
            opacity: 0.9;
            font-size: 0.95rem;
            margin-top: 0.25rem;
        }

        .room-body {
            padding: 1.75rem;
        }

        .room-price {
            font-size: 1.75rem;
            font-weight: 700;
            background: linear-gradient(135deg, #22c55e, #4ade80);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1.25rem;
        }

        .room-price span {
            font-size: 1rem;
            font-weight: 400;
            color: #94a3b8;
            -webkit-text-fill-color: #94a3b8;
        }

        .room-info {
            list-style: none;
            margin-bottom: 1.5rem;
        }

        .room-info li {
            padding: 0.75rem 0;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .room-info li:last-child {
            border-bottom: none;
        }

        .room-info li .icon {
            font-size: 1.1rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .status-badge[data-status="0"] {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(74, 222, 128, 0.2));
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #4ade80;
        }

        .status-badge[data-status="1"] {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(248, 113, 113, 0.2));
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
        }

        .btn-book {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            border: none;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s;
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
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-book:hover::before {
            left: 100%;
        }

        .btn-book:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
        }

        .btn-book:disabled, .btn-book.disabled {
            background: linear-gradient(135deg, #475569, #334155);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-book.disabled:hover::before {
            left: -100%;
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

        /* Responsive */
        @media (max-width: 768px) {
            .header { 
                flex-direction: column; 
                gap: 1rem; 
                padding: 1rem; 
            }
            .nav-links { 
                flex-wrap: wrap; 
                justify-content: center;
                gap: 0.5rem;
            }
            .nav-links a {
                padding: 0.5rem 0.8rem;
                font-size: 0.85rem;
            }
            .container {
                padding-top: 9rem;
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
            .room-grid {
                grid-template-columns: 1fr;
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
        body.theme-light .logo-text {
            color: #1e293b;
        }
        body.theme-light .nav-links a {
            color: #475569;
        }
        body.theme-light .page-title h2 {
            color: #1e293b;
        }
        body.theme-light .page-title p {
            color: #64748b;
        }
        body.theme-light .stats-bar {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.2);
        }
        body.theme-light .stat-item span:first-child {
            color: #64748b;
        }
        body.theme-light .filters select {
            background: rgba(255, 255, 255, 0.9);
            border-color: rgba(148, 163, 184, 0.3);
            color: #1e293b;
        }
        body.theme-light .room-card {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(148, 163, 184, 0.2);
        }
        body.theme-light .room-card:hover {
            border-color: var(--primary);
        }
        body.theme-light .room-card-body {
            background: #fff;
        }
        body.theme-light .room-features li {
            color: #475569;
        }
        body.theme-light .footer {
            background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
            border-top: 1px solid rgba(148, 163, 184, 0.2);
        }
        body.theme-light .footer-links a {
            color: #64748b;
        }
        body.theme-light .footer-copyright {
            color: #94a3b8;
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
        body.theme-light .room-detail {
            color: #475569;
        }
        body.theme-light .room-detail-value {
            color: #1e293b;
        }
        body.theme-light,
        body.theme-light p,
        body.theme-light span,
        body.theme-light div,
        body.theme-light li {
            --text-primary: #1e293b;
            --text-secondary: #64748b;
        }
        /* Force all text to dark */
        body.theme-light .logo-text,
        body.theme-light .nav-links a,
        body.theme-light h1,
        body.theme-light h2,
        body.theme-light h3,
        body.theme-light h4 {
            color: #1e293b !important;
        }
        body.theme-light .page-title p {
            color: #475569 !important;
        }
        body.theme-light .nav-links a {
            color: #475569 !important;
        }
        body.theme-light .nav-links a:hover {
            color: var(--primary) !important;
        }
        body.theme-light .btn-primary,
        body.theme-light .btn-login,
        body.theme-light .btn-book {
            color: #fff !important;
        }
        /* AGGRESSIVE: Force ALL text to dark black in light theme */
        body.theme-light {
            color: #1e293b !important;
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
        body.theme-light .nav-links a {
            color: #1e293b !important;
            -webkit-text-fill-color: #1e293b !important;
            background: none !important;
            -webkit-background-clip: unset !important;
            background-clip: unset !important;
            text-shadow: none !important;
        }
        
        /* ============ EXCEPTIONS - WHITE TEXT ============ */
        body.theme-light .btn-primary,
        body.theme-light .btn-primary *,
        body.theme-light .btn-login,
        body.theme-light .btn-login *,
        body.theme-light button[type="submit"],
        body.theme-light button[type="submit"] *,
        body.theme-light .room-status-badge {
            color: #1e293b !important;
            -webkit-text-fill-color: #1e293b !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
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
        /* Room book button - dark text */
        body.theme-light .btn-book,
        body.theme-light .btn-book *,
        body.theme-light a.btn-book,
        body.theme-light a.btn-book * {
            color: #1e293b !important;
            -webkit-text-fill-color: #1e293b !important;
            background: transparent !important;
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
            background: none !important;
            -webkit-background-clip: unset !important;
            background-clip: unset !important;
            -webkit-text-fill-color: unset !important;
            text-shadow: none !important;
        }
        body.theme-light h1,
        body.theme-light h2,
        body.theme-light h3,
        body.theme-light h4 {
            background: none !important;
            -webkit-background-clip: unset !important;
            -webkit-text-fill-color: unset !important;
            background-clip: unset !important;
        }
        /* Except buttons and header - keep white text */
        body.theme-light .btn-primary,
        body.theme-light .btn-login,
        body.theme-light .btn-book,
        body.theme-light button[type="submit"],
        body.theme-light a[class*="btn"],
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
            <img src="../Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="">
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
        
        <form class="filters" method="get">
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
        
        <div class="room-grid">
            <?php if (count($rooms) > 0): ?>
                <?php foreach ($rooms as $index => $room): ?>
                <div class="room-card" style="--index: <?php echo $index; ?>">
                    <div class="room-header" data-status="<?php echo $room['room_status']; ?>">
                        <?php if (!empty($room['room_image'])): ?>
                            <img src="../Assets/Images/Rooms/<?php echo htmlspecialchars($room['room_image']); ?>" alt="ห้อง <?php echo htmlspecialchars($room['room_number']); ?>" class="room-image">
                        <?php else: ?>
                            <svg class="room-placeholder-svg" viewBox="0 0 400 300" xmlns="http://www.w3.org/2000/svg">
                                <defs>
                                    <linearGradient id="bgGrad<?php echo $index; ?>" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" style="stop-color:rgba(100,150,255,0.1);stop-opacity:1" />
                                        <stop offset="100%" style="stop-color:rgba(150,100,255,0.1);stop-opacity:1" />
                                    </linearGradient>
                                </defs>
                                <rect width="400" height="300" fill="url(#bgGrad<?php echo $index; ?>)"/>
                                <!-- Room icon -->
                                <rect x="120" y="80" width="160" height="140" rx="10" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="2"/>
                                <path d="M 160 100 L 200 70 L 240 100 L 240 180 L 160 180 Z" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="2" stroke-linejoin="round"/>
                                <circle cx="185" cy="140" r="8" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="2"/>
                                <circle cx="215" cy="140" r="8" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="2"/>
                                <rect x="145" y="115" width="12" height="12" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="2" rx="2"/>
                                <rect x="243" y="115" width="12" height="12" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="2" rx="2"/>
                                <!-- Door -->
                                <rect x="190" y="160" width="20" height="30" rx="3" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="2"/>
                                <!-- Decorative pattern -->
                                <circle cx="80" cy="50" r="15" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="2"/>
                                <circle cx="320" cy="250" r="20" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="2"/>
                                <path d="M 30 220 Q 50 200 70 220" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        <?php endif; ?>
                        <div class="content">
                            <div class="room-number">■ ห้อง <?php echo htmlspecialchars($room['room_number']); ?></div>
                            <div class="room-type"><?php echo htmlspecialchars($room['type_name'] ?? 'มาตรฐาน'); ?></div>
                        </div>
                    </div>
                    <div class="room-body">
                        <div class="room-price">
                            ฿<?php echo number_format($room['type_price'] ?? 0); ?> <span>/เดือน</span>
                        </div>
                        <ul class="room-info">
                            <li><span class="icon">△</span> ชั้น <?php echo htmlspecialchars($room['room_floor'] ?? '-'); ?></li>
                            <li><span class="icon">◇</span> <?php echo htmlspecialchars($room['type_name'] ?? 'ห้องพักมาตรฐาน'); ?></li>
                            <li>
                                <span class="icon">≡</span>
                                สถานะ: 
                                <span class="status-badge" data-status="<?php echo $room['room_status']; ?>">
                                    <?php 
                                    $statusText = ['0' => '◉ ว่าง', '1' => '◈ มีผู้เช่า'];
                                    echo $statusText[$room['room_status']] ?? 'ไม่ทราบ';
                                    ?>
                                </span>
                            </li>
                        </ul>
                        <?php if ($room['room_status'] === '0'): ?>
                        <a href="booking.php?room=<?php echo $room['room_id']; ?>" class="btn-book">
                            <span>▶</span> จองห้องนี้
                        </a>
                        <?php else: ?>
                        <button class="btn-book disabled" disabled>
                            <span>×</span> ไม่ว่าง
                        </button>
                        <?php endif; ?>
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
    </div>

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

        // Animate cards on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animationPlayState = 'running';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.room-card').forEach(card => {
            card.style.animationPlayState = 'paused';
            observer.observe(card);
        });
    </script>
</body>
</html>
