<?php
declare(strict_types=1);
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
} catch (PDOException $e) {}

// ดึงห้องว่าง (room_status = '0' คือห้องว่าง)
$availableRooms = [];
try {
    $stmt = $pdo->query("
        SELECT r.*, rt.type_name, rt.type_price 
        FROM room r 
        LEFT JOIN roomtype rt ON r.type_id = rt.type_id 
        WHERE r.room_status = '0' 
        ORDER BY CAST(r.room_number AS UNSIGNED) ASC
    ");
    $availableRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ถ้ามีการเลือกห้องมา
$selectedRoom = null;
if (!empty($_GET['room'])) {
    $roomId = (int)$_GET['room'];
    foreach ($availableRooms as $room) {
        if ($room['room_id'] == $roomId) {
            $selectedRoom = $room;
            break;
        }
    }
}

$success = false;
$error = '';

// Process form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roomId = (int)($_POST['room_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $idCard = trim($_POST['id_card'] ?? '');
    $moveInDate = $_POST['move_in_date'] ?? '';
    $note = trim($_POST['note'] ?? '');
    
    // Validate
    if (!$roomId || !$name || !$phone) {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    } else {
        try {
            // ตรวจสอบว่าห้องยังว่างอยู่
            $stmt = $pdo->prepare("SELECT room_status FROM room WHERE room_id = ?");
            $stmt->execute([$roomId]);
            $roomStatus = $stmt->fetchColumn();
            
            if ($roomStatus !== '0') {
                $error = 'ขออภัย ห้องนี้ถูกจองไปแล้ว';
            } else {
                // บันทึกการจอง
                $stmt = $pdo->prepare("
                    INSERT INTO booking (room_id, bkg_name, bkg_phone, bkg_email, bkg_id_card, bkg_move_in_date, bkg_note, bkg_status, bkg_date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([$roomId, $name, $phone, $email, $idCard, $moveInDate, $note]);
                $success = true;
            }
        } catch (PDOException $e) {
            $error = 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จองห้องพัก - <?php echo htmlspecialchars($siteName); ?></title>
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

        /* Container */
        .container {
            max-width: 800px;
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
            margin-bottom: 3rem;
        }
        .page-title .label {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1.5rem;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2), rgba(118, 75, 162, 0.2));
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 50px;
            font-size: 0.9rem;
            color: #a78bfa;
            margin-bottom: 1rem;
        }

        .label-icon {
            width: 16px;
            height: 16px;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
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

        /* Booking Form Card */
        .booking-form {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 2.5rem;
            border: 1px solid rgba(255,255,255,0.1);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease forwards;
        }

        .booking-form::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2, #f093fb);
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

        /* Form Groups */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.6rem;
            color: #94a3b8;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .form-group label .required {
            color: #f472b6;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 1rem 1.25rem;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.05);
            color: #fff;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2), 0 0 20px rgba(102, 126, 234, 0.1);
            background: rgba(255,255,255,0.08);
        }

        .form-group select option {
            background: #1a1a2e;
            color: #fff;
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: #64748b;
        }

        /* Selected Room Card */
        .selected-room {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border: 1px solid rgba(102, 126, 234, 0.3);
            padding: 1.5rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .selected-room .room-info h4 {
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
            color: #fff;
        }

        .selected-room .room-info p {
            color: #94a3b8;
        }

        .selected-room .room-price {
            font-size: 1.75rem;
            font-weight: 700;
            background: linear-gradient(135deg, #22c55e, #4ade80);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Submit Button */
        .btn-submit {
            width: 100%;
            padding: 1.1rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            border: none;
            border-radius: 14px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-submit:hover::before {
            left: 100%;
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
        }

        /* Alert Messages */
        .alert {
            padding: 1.25rem;
            border-radius: 14px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #4ade80;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
        }

        /* Success Box */
        .success-box {
            text-align: center;
            padding: 3rem 2rem;
        }
        .success-box .icon {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            display: block;
            animation: successPulse 2s ease-in-out infinite;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            margin-bottom: 1.5rem;
            animation: successPulse 2s ease-in-out infinite;
            filter: drop-shadow(0 0 20px rgba(34, 197, 94, 0.5));
        }

        .empty-icon {
            width: 80px;
            height: 80px;
            margin-bottom: 1rem;
            animation: floatIcon 3s ease-in-out infinite;
        }

        @keyframes floatIcon {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .btn-icon-sm {
            width: 18px;
            height: 18px;
            vertical-align: middle;
            margin-right: 6px;
        }

        .btn-icon {
            width: 20px;
            height: 20px;
            vertical-align: middle;
            margin-right: 8px;
            transition: all 0.3s ease;
        }

        .btn-submit:hover .btn-icon {
            transform: scale(1.2);
            filter: drop-shadow(0 0 8px currentColor);
        }

        .alert-icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .room-icon {
            width: 24px;
            height: 24px;
            vertical-align: middle;
            margin-right: 4px;
            transition: all 0.3s ease;
        }

        .room-option:hover .room-icon,
        .room-option.selected .room-icon {
            transform: scale(1.15);
            filter: drop-shadow(0 0 8px currentColor);
        }

        @keyframes successPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .success-box h3 {
            font-size: 2rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #22c55e, #4ade80);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .success-box p {
            color: #94a3b8;
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
            line-height: 1.8;
        }
        .success-box a {
            display: inline-block;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .success-box a:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        /* No Rooms State */
        .no-rooms {
            text-align: center;
            padding: 3rem 2rem;
        }
        .no-rooms .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            display: block;
        }
        .no-rooms p {
            color: #94a3b8;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }
        .no-rooms a {
            display: inline-block;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .no-rooms a:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        /* Room Selection Cards */
        .room-selection {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .room-option {
            background: rgba(255,255,255,0.03);
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 1.25rem;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }

        .room-option:hover {
            border-color: rgba(102, 126, 234, 0.5);
            background: rgba(102, 126, 234, 0.1);
            transform: translateY(-3px);
        }

        .room-option.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2), rgba(118, 75, 162, 0.2));
            box-shadow: 0 0 30px rgba(102, 126, 234, 0.3);
        }

        .room-option input {
            display: none;
        }

        .room-option .room-num {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #fff;
        }

        .room-option .room-type {
            font-size: 0.85rem;
            color: #94a3b8;
            margin-bottom: 0.5rem;
        }

        .room-option .room-price {
            font-size: 1.1rem;
            font-weight: 600;
            color: #4ade80;
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
            .booking-form { 
                padding: 1.5rem; 
            }
            .selected-room { 
                flex-direction: column; 
                gap: 1rem; 
                text-align: center; 
            }
            .page-title h2 {
                font-size: 1.75rem;
            }
            .room-selection {
                grid-template-columns: repeat(2, 1fr);
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
        body.theme-light .booking-form {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(148, 163, 184, 0.2);
        }
        body.theme-light .booking-form h3 {
            color: #1e293b;
        }
        body.theme-light .form-group label {
            color: #475569;
        }
        body.theme-light .form-group input,
        body.theme-light .form-group select,
        body.theme-light .form-group textarea {
            background: rgba(248, 250, 252, 0.9);
            border-color: rgba(148, 163, 184, 0.3);
            color: #1e293b;
        }
        body.theme-light .room-option {
            background: rgba(248, 250, 252, 0.9);
            border-color: rgba(148, 163, 184, 0.3);
            color: #1e293b;
        }
        body.theme-light .room-option:hover {
            border-color: var(--primary);
        }
        body.theme-light .selected-room {
            background: rgba(59, 130, 246, 0.1);
            border-color: rgba(59, 130, 246, 0.3);
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
        /* All texts in light theme */
        body.theme-light .room-info h4 {
            color: #1e293b;
        }
        body.theme-light .room-info p {
            color: #64748b;
        }
        body.theme-light .room-price {
            color: #1e293b;
        }
        body.theme-light .success-message h3 {
            color: #1e293b;
        }
        body.theme-light .success-message p {
            color: #64748b;
        }
        body.theme-light .error-message {
            color: #dc2626;
        }
        body.theme-light,
        body.theme-light p,
        body.theme-light span,
        body.theme-light div,
        body.theme-light li,
        body.theme-light label {
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
        body.theme-light .page-title p,
        body.theme-light .booking-form p,
        body.theme-light label {
            color: #475569 !important;
        }
        body.theme-light .nav-links a {
            color: #475569 !important;
        }
        body.theme-light .nav-links a:hover {
            color: var(--primary) !important;
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
        body.theme-light .btn-submit,
        body.theme-light .btn-submit *,
        body.theme-light button[type="submit"],
        body.theme-light button[type="submit"] * {
            color: #1e293b !important;
            -webkit-text-fill-color: #1e293b !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
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
        body.theme-light,
        body.theme-light a,
        body.theme-light p,
        body.theme-light span,
        body.theme-light div,
        body.theme-light h1,
        body.theme-light h2,
        body.theme-light h3,
        body.theme-light h4,
        body.theme-light li,
        body.theme-light label,
        body.theme-light .logo-text,
        body.theme-light .nav-links a,
        body.theme-light .page-title,
        body.theme-light .booking-form {
            color: #1e293b !important;
            opacity: 1 !important;
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
        /* Except buttons - keep white text */
        body.theme-light .btn-primary,
        body.theme-light .btn-login,
        body.theme-light .btn-submit,
        body.theme-light button[type="submit"],
        body.theme-light a[class*="btn"] {
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
            <a href="../index.php"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg> หน้าแรก</a>
            <a href="rooms.php"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg> ห้องพัก</a>
            <a href="news.php"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6m-6-4h6"/></svg> ข่าวสาร</a>
            <a href="booking.php"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> จองห้อง</a>
        </nav>
    </header>

    <div class="container">
        <a href="../index.php" class="back-link">← กลับหน้าแรก</a>
        
        <div class="page-title">
            <span class="label"><svg class="label-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> การจอง</span>
            <h2>จองห้องพักออนไลน์</h2>
            <p>กรอกข้อมูลเพื่อจองห้องพักที่คุณต้องการ</p>
        </div>
        
        <?php if ($success): ?>
        <div class="booking-form">
            <div class="success-box">
                <svg class="success-icon" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <h3>จองห้องพักสำเร็จ!</h3>
                <p>ทางหอพักจะติดต่อกลับเพื่อยืนยันการจองภายใน 24 ชั่วโมง<br>กรุณารอการติดต่อจากเจ้าหน้าที่</p>
                <a href="../index.php"><svg class="btn-icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg> กลับหน้าแรก</a>
            </div>
        </div>
        
        <?php elseif (count($availableRooms) === 0): ?>
        <div class="booking-form">
            <div class="no-rooms">
                <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M16 16s-1.5-2-4-2-4 2-4 2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                <p>ขณะนี้ห้องพักเต็มทุกห้อง</p>
                <p>กรุณาติดต่อสอบถามหรือลองใหม่ภายหลัง</p>
                <a href="../index.php"><svg class="btn-icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg> กลับหน้าแรก</a>
            </div>
        </div>
        
        <?php else: ?>
        <div class="booking-form">
            <?php if ($error): ?>
            <div class="alert alert-error"><svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="post">
                <div class="form-group">
                    <label>เลือกห้องพัก <span class="required">*</span></label>
                    <div class="room-selection">
                        <?php foreach ($availableRooms as $room): ?>
                        <label class="room-option <?php echo $selectedRoom && $selectedRoom['room_id'] == $room['room_id'] ? 'selected' : ''; ?>">
                            <input type="radio" name="room_id" value="<?php echo $room['room_id']; ?>" required 
                                <?php echo $selectedRoom && $selectedRoom['room_id'] == $room['room_id'] ? 'checked' : ''; ?>>
                            <div class="room-num"><svg class="room-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg> <?php echo htmlspecialchars($room['room_number']); ?></div>
                            <div class="room-type"><?php echo htmlspecialchars($room['type_name'] ?? 'มาตรฐาน'); ?></div>
                            <div class="room-price">฿<?php echo number_format($room['type_price'] ?? 0); ?>/เดือน</div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>ชื่อ-นามสกุล <span class="required">*</span></label>
                    <input type="text" name="name" required placeholder="กรอกชื่อ-นามสกุล">
                </div>
                
                <div class="form-group">
                    <label>เบอร์โทรศัพท์ <span class="required">*</span></label>
                    <input type="tel" name="phone" required placeholder="0812345678">
                </div>
                
                <div class="form-group">
                    <label>อีเมล</label>
                    <input type="email" name="email" placeholder="email@example.com">
                </div>
                
                <div class="form-group">
                    <label>เลขบัตรประชาชน</label>
                    <input type="text" name="id_card" maxlength="13" placeholder="1234567890123">
                </div>
                
                <div class="form-group">
                    <label>วันที่ต้องการเข้าพัก</label>
                    <input type="date" name="move_in_date" min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label>หมายเหตุ / ข้อความเพิ่มเติม</label>
                    <textarea name="note" placeholder="ระบุข้อความเพิ่มเติม (ถ้ามี)"></textarea>
                </div>
                
                <button type="submit" class="btn-submit">
                    <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M9 16l2 2 4-4"/></svg> ยืนยันการจองห้องพัก
                </button>
            </form>
        </div>
        <?php endif; ?>
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

        // Room selection effect
        document.querySelectorAll('.room-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.room-option').forEach(o => o.classList.remove('selected'));
                this.classList.add('selected');
            });
        });
    </script>
</body>
</html>
