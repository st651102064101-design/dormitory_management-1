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

// ดึงข่าวทั้งหมด (เรียงตามวันที่ล่าสุด)
$news = [];
try {
    $stmt = $pdo->query("
        SELECT * FROM news 
        ORDER BY news_date DESC
    ");
    $news = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข่าวประชาสัมพันธ์ - <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            max-width: 1000px;
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

        /* News List */
        .news-list {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        /* News Card */
        .news-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 2rem;
            border: 1px solid rgba(255,255,255,0.1);
            position: relative;
            overflow: hidden;
            transition: all 0.4s ease;
            animation: fadeInUp 0.6s ease forwards;
            animation-delay: calc(var(--index, 0) * 0.1s);
            opacity: 0;
        }

        .news-card:hover {
            border-color: rgba(102, 126, 234, 0.5);
            transform: translateY(-5px);
            box-shadow: 0 20px 60px rgba(102, 126, 234, 0.2);
        }

        .news-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2, #f093fb);
            transform: scaleX(0);
            transition: transform 0.4s ease;
        }

        .news-card:hover::before {
            transform: scaleX(1);
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

        .news-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .news-date {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: linear-gradient(135deg, rgba(96, 165, 250, 0.1), rgba(59, 130, 246, 0.1));
            border: 1px solid rgba(96, 165, 250, 0.2);
            border-radius: 50px;
            color: #60a5fa;
            font-size: 0.9rem;
        }

        .news-category {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: linear-gradient(135deg, rgba(167, 139, 250, 0.1), rgba(139, 92, 246, 0.1));
            border: 1px solid rgba(167, 139, 250, 0.2);
            border-radius: 50px;
            color: #a78bfa;
            font-size: 0.85rem;
        }

        .news-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #fff, #e2e8f0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.4;
        }

        .news-content {
            color: #94a3b8;
            line-height: 1.9;
            font-size: 1rem;
        }

        .news-content p {
            margin-bottom: 1rem;
        }

        .news-image {
            width: 100%;
            max-height: 400px;
            object-fit: cover;
            border-radius: 16px;
            margin: 1.5rem 0;
            border: 1px solid rgba(255,255,255,0.1);
        }

        /* Read More Button */
        .read-more {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2), rgba(118, 75, 162, 0.2));
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 12px;
            color: #a78bfa;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }

        .read-more:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.3), rgba(118, 75, 162, 0.3));
            transform: translateX(5px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .empty-state .icon {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            display: block;
        }

        .empty-icon {
            width: 80px;
            height: 80px;
            margin-bottom: 1.5rem;
            animation: floatIcon 3s ease-in-out infinite;
            filter: drop-shadow(0 0 10px rgba(148, 163, 184, 0.3));
        }

        @keyframes floatIcon {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .date-icon {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }

        .news-card:hover .date-icon {
            transform: scale(1.2);
            filter: drop-shadow(0 0 5px currentColor);
        }

        .cat-icon {
            width: 14px;
            height: 14px;
            vertical-align: middle;
            margin-right: 4px;
        }
        .empty-state p {
            color: #94a3b8;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }
        .empty-state .subtitle {
            font-size: 1rem;
            color: #64748b;
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
            .news-card { 
                padding: 1.5rem; 
            }
            .news-title { 
                font-size: 1.35rem; 
            }
            .page-title h2 {
                font-size: 1.75rem;
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
        body.theme-light .news-card {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(148, 163, 184, 0.2);
        }
        body.theme-light .news-card:hover {
            border-color: var(--primary);
        }
        body.theme-light .news-title {
            color: #1e293b;
        }
        body.theme-light .news-excerpt {
            color: #64748b;
        }
        body.theme-light .news-meta {
            color: #94a3b8;
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
        body.theme-light .news-content {
            color: #475569;
        }
        body.theme-light .news-full-content {
            color: #475569;
        }
        body.theme-light .news-full-content p {
            color: #475569;
        }
        body.theme-light,
        body.theme-light p,
        body.theme-light span,
        body.theme-light div {
            --text-primary: #1e293b;
            --text-secondary: #64748b;
        }
        /* Force all text to dark */
        body.theme-light .logo-text,
        body.theme-light .nav-links a,
        body.theme-light h1,
        body.theme-light h2,
        body.theme-light h3 {
            color: #1e293b !important;
        }
        body.theme-light .page-title p,
        body.theme-light .news-card p {
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
        body.theme-light li,
        body.theme-light .logo-text,
        body.theme-light .nav-links a,
        body.theme-light .page-title,
        body.theme-light .news-card {
            color: #1e293b !important;
            opacity: 1 !important;
        }
        body.theme-light h1,
        body.theme-light h2,
        body.theme-light h3 {
            background: none !important;
            -webkit-background-clip: unset !important;
            -webkit-text-fill-color: unset !important;
            background-clip: unset !important;
        }
        /* Except buttons - keep white text */
        body.theme-light .btn-primary,
        body.theme-light .btn-login,
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
            <img src="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="">
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
            <span class="label"><svg class="label-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6m-6-4h6"/></svg> ข่าวสาร</span>
            <h2>ข่าวประชาสัมพันธ์</h2>
            <p>ติดตามข่าวสารและประกาศล่าสุดจากหอพัก</p>
        </div>
        
        <?php if (count($news) > 0): ?>
        <div class="news-list">
            <?php foreach ($news as $index => $item): ?>
            <article class="news-card" style="--index: <?php echo $index; ?>">
                <div class="news-header">
                    <div class="news-date">
                        <svg class="date-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <?php echo date('d/m/Y', strtotime($item['news_date'])); ?>
                        <?php if (!empty($item['news_time'])): ?>
                        - <?php echo date('H:i', strtotime($item['news_time'])); ?> น.
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($item['news_category'])): ?>
                    <span class="news-category"><svg class="cat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg> <?php echo htmlspecialchars($item['news_category']); ?></span>
                    <?php endif; ?>
                </div>
                
                <h3 class="news-title"><?php echo htmlspecialchars($item['news_title'] ?? ''); ?></h3>
                
                <div class="news-content">
                    <?php echo nl2br(htmlspecialchars($item['news_details'] ?? '')); ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5"><rect x="3" y="4" width="18" height="16" rx="2" ry="2"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 2v4m8-4v4"/><path d="M7 14h.01M12 14h.01M17 14h.01M7 18h.01M12 18h.01"/></svg>
            <p>ยังไม่มีข่าวประชาสัมพันธ์</p>
            <p class="subtitle">กรุณากลับมาตรวจสอบภายหลัง</p>
        </div>
        <?php endif; ?>
    </div>

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

        document.querySelectorAll('.news-card').forEach(card => {
            card.style.animationPlayState = 'paused';
            observer.observe(card);
        });
    </script>
</body>
</html>
