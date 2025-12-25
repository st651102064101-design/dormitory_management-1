<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/ConnectDB.php';

$login_error = '';
$old_username = '';
$login_success = false;

// ดึงค่าตั้งค่าระบบ
$siteName = 'Sangthian Dormitory';
$logoFilename = 'Logo.jpg';
$themeColor = '#1e40af';
$publicTheme = 'dark';
try {
    $pdo = connectDB();
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename', 'theme_color', 'public_theme')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
        if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
        if ($row['setting_key'] === 'theme_color') $themeColor = $row['setting_value'];
        if ($row['setting_key'] === 'public_theme') $publicTheme = $row['setting_value'];
    }
} catch (PDOException $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Check if it's an AJAX request
  $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
  
  $old_username = trim((string)($_POST['username'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  if ($old_username === '' || $password === '') {
    $login_error = 'กรุณากรอกข้อมูลให้ครบ';
  } else {
    $pdo = connectDB();
    $stmt = $pdo->prepare('SELECT * FROM admin WHERE admin_username = :username LIMIT 1');
    $stmt->execute([':username' => $old_username]);
    $row = $stmt->fetch();

    if ($row) {
      $stored = (string)($row['admin_password'] ?? '');
      $ok = false;

      // Prefer secure password verification; fall back to plain comparison if not hashed.
      if ($stored !== '' && password_verify($password, $stored)) {
        $ok = true;
      } elseif ($password === $stored) {
        $ok = true;
      }

      if ($ok) {
        $_SESSION['admin_id'] = $row['admin_id'];
        $_SESSION['admin_username'] = $row['admin_username'];
        $_SESSION['admin_name'] = $row['admin_name'] ?? '';
        $login_success = true;
      } else {
        $login_error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
      }
    } else {
      $login_error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
  }
  
  // If AJAX request, return JSON response
  if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
      'success' => $login_success,
      'error' => $login_error,
      'redirect' => $login_success ? 'Reports/manage.php' : ''
    ]);
    exit;
  }
  
  // If login success and not AJAX, redirect
  if ($login_success) {
    header('Location: Reports/manage.php');
    exit;
  }
  // If not AJAX and login failed, show error without reload (page stays as is)
}

?>
<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login | <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/animate-ui.css" />
    <style>
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Prompt', system-ui, sans-serif;
      }

      body {
        min-height: 100vh;
        background: linear-gradient(135deg, #0a0a0f 0%, #1a1a2e 50%, #0f0f1a 100%);
        font-family: 'Prompt', system-ui, sans-serif;
        overflow: hidden;
        position: relative;
      }

      /* Animated Background */
      .bg-animation {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 0;
        overflow: hidden;
      }

      .bg-animation::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: 
          radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.15) 0%, transparent 50%),
          radial-gradient(circle at 80% 20%, rgba(59, 130, 246, 0.15) 0%, transparent 50%),
          radial-gradient(circle at 40% 40%, rgba(139, 92, 246, 0.1) 0%, transparent 40%);
        animation: bgPulse 15s ease-in-out infinite;
      }

      @keyframes bgPulse {
        0%, 100% { transform: translate(0, 0) rotate(0deg); }
        33% { transform: translate(2%, 2%) rotate(1deg); }
        66% { transform: translate(-1%, 1%) rotate(-1deg); }
      }

      /* Floating Particles */
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
        animation: float 20s infinite;
        box-shadow: 0 0 10px rgba(96, 165, 250, 0.8), 0 0 20px rgba(96, 165, 250, 0.4);
      }

      .particle:nth-child(1) { left: 10%; animation-delay: 0s; animation-duration: 25s; }
      .particle:nth-child(2) { left: 20%; animation-delay: 2s; animation-duration: 20s; }
      .particle:nth-child(3) { left: 30%; animation-delay: 4s; animation-duration: 28s; }
      .particle:nth-child(4) { left: 40%; animation-delay: 1s; animation-duration: 22s; }
      .particle:nth-child(5) { left: 50%; animation-delay: 3s; animation-duration: 26s; }
      .particle:nth-child(6) { left: 60%; animation-delay: 5s; animation-duration: 21s; }
      .particle:nth-child(7) { left: 70%; animation-delay: 2.5s; animation-duration: 24s; }
      .particle:nth-child(8) { left: 80%; animation-delay: 1.5s; animation-duration: 27s; }
      .particle:nth-child(9) { left: 90%; animation-delay: 4.5s; animation-duration: 23s; }
      .particle:nth-child(10) { left: 15%; animation-delay: 3.5s; animation-duration: 29s; }

      @keyframes float {
        0% { transform: translateY(100vh) scale(0); opacity: 0; }
        10% { opacity: 1; }
        90% { opacity: 1; }
        100% { transform: translateY(-100vh) scale(1); opacity: 0; }
      }

      /* Glow Lines */
      .glow-lines {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 1;
        pointer-events: none;
        overflow: hidden;
      }

      .glow-line {
        position: absolute;
        width: 2px;
        height: 200px;
        background: linear-gradient(to bottom, transparent, rgba(59, 130, 246, 0.8), transparent);
        animation: lineMove 8s linear infinite;
      }

      .glow-line:nth-child(1) { left: 10%; animation-delay: 0s; }
      .glow-line:nth-child(2) { left: 30%; animation-delay: 2s; }
      .glow-line:nth-child(3) { left: 50%; animation-delay: 4s; }
      .glow-line:nth-child(4) { left: 70%; animation-delay: 1s; }
      .glow-line:nth-child(5) { left: 90%; animation-delay: 3s; }

      @keyframes lineMove {
        0% { top: -200px; opacity: 0; }
        20% { opacity: 1; }
        80% { opacity: 1; }
        100% { top: 100vh; opacity: 0; }
      }

      /* Login Container */
      .login-container {
        position: relative;
        z-index: 10;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        padding: 20px;
      }

      /* Login Card */
      .login-card {
        position: relative;
        width: 100%;
        max-width: 420px;
        padding: 50px 40px;
        background: rgba(15, 23, 42, 0.7);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-radius: 24px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 
          0 25px 50px -12px rgba(0, 0, 0, 0.5),
          0 0 0 1px rgba(255, 255, 255, 0.05),
          inset 0 1px 0 rgba(255, 255, 255, 0.1);
        animation: cardAppear 0.8s ease-out;
        transform-style: preserve-3d;
        -webkit-transform-style: preserve-3d;
        will-change: transform, box-shadow;
        transition: transform 0.1s ease-out, box-shadow 0.3s ease;
      }

      .login-card:hover {
        animation: none;
      }

      @keyframes cardAppear {
        from { 
          opacity: 0; 
          transform: translateY(30px) scale(0.95); 
        }
        to { 
          opacity: 1; 
          transform: translateY(0) scale(1); 
        }
      }

      /* Glowing Border Effect */
      .login-card::before {
        content: '';
        position: absolute;
        top: -2px;
        left: -2px;
        right: -2px;
        bottom: -2px;
        background: linear-gradient(45deg, 
          #3b82f6, #8b5cf6, #06b6d4, #3b82f6, #8b5cf6);
        background-size: 400% 400%;
        border-radius: 26px;
        z-index: -1;
        animation: glowBorder 4s ease infinite;
        opacity: 0.7;
        filter: blur(8px);
      }

      @keyframes glowBorder {
        0%, 100% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
      }

      /* Animated SVG Icons */
      .animated-icon {
        stroke-dasharray: 100;
        stroke-dashoffset: 100;
        animation: iconDraw 0.8s ease forwards;
      }

      @keyframes iconDraw {
        to {
          stroke-dashoffset: 0;
        }
      }

      /* Logo/Icon */
      .login-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto 25px;
        background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
        border-radius: 20px;
        display: flex;
        justify-content: center;
        align-items: center;
        box-shadow: 
          0 10px 30px rgba(59, 130, 246, 0.4),
          0 0 60px rgba(139, 92, 246, 0.3);
        animation: iconPulse 2s ease-in-out infinite;
      }

      .login-icon svg {
        width: 40px;
        height: 40px;
        stroke: #fff;
        stroke-width: 2;
        fill: none;
        stroke-dasharray: 150;
        stroke-dashoffset: 150;
        animation: iconDraw 1s ease forwards 0.3s;
      }

      @keyframes iconPulse {
        0%, 100% { 
          box-shadow: 
            0 10px 30px rgba(59, 130, 246, 0.4),
            0 0 60px rgba(139, 92, 246, 0.3);
        }
        50% { 
          box-shadow: 
            0 10px 40px rgba(59, 130, 246, 0.6),
            0 0 80px rgba(139, 92, 246, 0.5);
        }
      }

      /* Title */
      .login-title {
        text-align: center;
        font-size: 28px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 8px;
        background: linear-gradient(135deg, #fff 0%, #94a3b8 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
      }

      .login-subtitle {
        text-align: center;
        font-size: 14px;
        color: #64748b;
        margin-bottom: 35px;
      }

      /* Form Group */
      .form-group {
        margin-bottom: 24px;
        position: relative;
      }

      .form-group label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #94a3b8;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 1px;
      }

      .input-wrapper {
        position: relative;
      }

      .input-wrapper svg {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        width: 20px;
        height: 20px;
        color: #000000;
        transition: all 0.3s ease;
        stroke-dasharray: 100;
        stroke-dashoffset: 100;
        animation: iconDraw 0.6s ease forwards;
      }

      .input-wrapper svg:nth-of-type(1) {
        animation-delay: 0.2s;
      }

      .input-wrapper:focus-within svg {
        transform: translateY(-50%) scale(1.1);
        filter: drop-shadow(0 0 8px currentColor);
      }

      .form-input {
        width: 100%;
        padding: 16px 16px 16px 50px;
        background: rgba(30, 41, 59, 0.5);
        border: 2px solid rgba(71, 85, 105, 0.3);
        border-radius: 12px;
        font-size: 16px;
        color: #fff;
        transition: all 0.3s ease;
        outline: none;
      }

      .form-input::placeholder {
        color: #475569;
      }

      .form-input:focus {
        border-color: #3b82f6;
        background: rgba(30, 41, 59, 0.8);
        box-shadow: 
          0 0 0 4px rgba(59, 130, 246, 0.15),
          0 0 30px rgba(59, 130, 246, 0.2);
      }

      .form-input:focus + svg,
      .input-wrapper:focus-within svg {
        color: #3b82f6;
      }

      /* Error Message */
      .error-message {
        background: rgba(239, 68, 68, 0.15);
        border: 1px solid rgba(239, 68, 68, 0.3);
        border-radius: 10px;
        padding: 12px 16px;
        margin-bottom: 20px;
        color: #fca5a5;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: shake 0.5s ease;
      }

      .error-message .error-icon {
        flex-shrink: 0;
        stroke-dasharray: 100;
        stroke-dashoffset: 100;
        animation: iconDraw 0.6s ease forwards, errorPulse 1.5s ease-in-out infinite;
      }

      @keyframes errorPulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.6; }
      }

      @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
      }

      /* Submit Button */
      .submit-btn {
        width: 100%;
        padding: 16px;
        background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
        border: none;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 700;
        color: #fff;
        cursor: pointer;
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 2px;
      }

      .submit-btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
        transition: left 0.5s ease;
      }

      .submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 
          0 10px 30px rgba(59, 130, 246, 0.4),
          0 0 60px rgba(139, 92, 246, 0.3);
      }

      .submit-btn:hover::before {
        left: 100%;
      }

      .submit-btn:active {
        transform: translateY(0);
      }

      /* Loading State */
      .submit-btn.loading {
        pointer-events: none;
        opacity: 0.8;
      }

      .submit-btn.loading::after {
        content: '';
        width: 20px;
        height: 20px;
        border: 2px solid transparent;
        border-top-color: #fff;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        margin-left: 10px;
        display: inline-block;
        vertical-align: middle;
      }

      @keyframes spin {
        to { transform: rotate(360deg); }
      }

      /* Button States */
      .submit-btn .btn-text,
      .submit-btn .btn-loading {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
      }

      .submit-btn .spinner {
        animation: spin 1s linear infinite;
      }

      .submit-btn.loading .btn-text {
        display: none;
      }

      .submit-btn.loading .btn-loading {
        display: inline-flex !important;
      }

      /* Success Overlay */
      .login-success-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
        backdrop-filter: blur(10px);
        border-radius: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 200;
        opacity: 0;
        animation: fadeIn 0.3s ease forwards;
      }

      @keyframes fadeIn {
        to { opacity: 1; }
      }

      .success-content {
        text-align: center;
        color: #f5f8ff;
      }

      .success-icon {
        color: #22c55e;
        margin-bottom: 20px;
        animation: successBounce 0.5s ease;
      }

      .success-icon circle {
        stroke-dasharray: 100;
        stroke-dashoffset: 100;
        animation: drawCircle 0.5s ease forwards;
      }

      .success-icon path {
        stroke-dasharray: 50;
        stroke-dashoffset: 50;
        animation: drawCheck 0.3s ease 0.4s forwards;
      }

      @keyframes drawCircle {
        to { stroke-dashoffset: 0; }
      }

      @keyframes drawCheck {
        to { stroke-dashoffset: 0; }
      }

      @keyframes successBounce {
        0% { transform: scale(0); }
        50% { transform: scale(1.2); }
        100% { transform: scale(1); }
      }

      .success-content p {
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 10px;
        background: linear-gradient(135deg, #22c55e, #10b981);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
      }

      .redirect-text {
        color: #94a3b8;
        font-size: 14px;
      }

      /* Error shake animation */
      .login-card.shake {
        animation: shake 0.5s ease;
      }

      @keyframes shake {
        0%, 100% { transform: translateX(0); }
        20% { transform: translateX(-10px); }
        40% { transform: translateX(10px); }
        60% { transform: translateX(-10px); }
        80% { transform: translateX(10px); }
      }

      /* Dynamic error message */
      .error-message.show {
        animation: slideDown 0.3s ease;
      }

      @keyframes slideDown {
        from {
          opacity: 0;
          transform: translateY(-10px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }

      /* Success Animation */
      .login-card.success {
        animation: successPulse 0.5s ease;
      }

      .login-card.success::before {
        background: linear-gradient(45deg, #22c55e, #10b981, #06b6d4);
      }

      @keyframes successPulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.02); }
      }

      /* Footer */
      .login-footer {
        text-align: center;
        margin-top: 30px;
        color: #64748b;
        font-size: 13px;
      }

      .login-footer a {
        color: #3b82f6;
        text-decoration: none;
        transition: color 0.3s ease;
      }

      .login-footer a:hover {
        color: #60a5fa;
      }

      .forgot-password-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        background: rgba(251, 146, 60, 0.1);
        border: 1px solid rgba(251, 146, 60, 0.3);
        border-radius: 10px;
        color: #fb923c !important;
        font-size: 0.9rem;
        font-weight: 500;
        margin-bottom: 0.75rem;
        margin-right: 0.5rem;
        transition: all 0.3s ease;
      }

      .forgot-password-btn:hover {
        background: rgba(251, 146, 60, 0.2);
        border-color: rgba(251, 146, 60, 0.5);
        transform: translateY(-2px);
        color: #fdba74 !important;
        box-shadow: 0 5px 20px rgba(251, 146, 60, 0.3);
      }

      .forgot-password-btn svg {
        transition: transform 0.3s ease;
      }

      .forgot-password-btn:hover svg {
        transform: rotate(180deg);
      }

      .back-home-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        background: rgba(59, 130, 246, 0.1);
        border: 1px solid rgba(59, 130, 246, 0.3);
        border-radius: 10px;
        color: #60a5fa !important;
        font-size: 0.9rem;
        font-weight: 500;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
      }

      .back-home-btn:hover {
        background: rgba(59, 130, 246, 0.2);
        border-color: rgba(59, 130, 246, 0.5);
        transform: translateY(-2px);
        color: #93c5fd !important;
        box-shadow: 0 5px 20px rgba(59, 130, 246, 0.3);
      }

      .back-icon {
        width: 18px;
        height: 18px;
        stroke-dasharray: 100;
        stroke-dashoffset: 100;
        animation: iconDraw 0.8s ease forwards;
        transition: all 0.3s ease;
      }

      @keyframes iconDraw {
        to {
          stroke-dashoffset: 0;
        }
      }

      .back-home-btn:hover .back-icon {
        transform: scale(1.2);
        filter: drop-shadow(0 0 8px currentColor);
      }

      /* Responsive */
      @media (max-width: 480px) {
        .login-card {
          padding: 40px 25px;
          margin: 10px;
        }
        .login-title {
          font-size: 24px;
        }
      }

      /* Neon Text Glow */
      .neon-glow {
        text-shadow: 
          0 0 5px rgba(59, 130, 246, 0.5),
          0 0 10px rgba(59, 130, 246, 0.3),
          0 0 20px rgba(59, 130, 246, 0.2);
      }

      /* Cyberpunk Grid */
      .cyber-grid {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: 
          linear-gradient(rgba(59, 130, 246, 0.03) 1px, transparent 1px),
          linear-gradient(90deg, rgba(59, 130, 246, 0.03) 1px, transparent 1px);
        background-size: 50px 50px;
        z-index: 0;
        animation: gridMove 20s linear infinite;
      }

      @keyframes gridMove {
        0% { transform: perspective(500px) rotateX(60deg) translateY(0); }
        100% { transform: perspective(500px) rotateX(60deg) translateY(50px); }
      }

      /* Orbs */
      .orb {
        position: fixed;
        border-radius: 50%;
        filter: blur(60px);
        z-index: 0;
        animation: orbFloat 10s ease-in-out infinite;
      }

      .orb-1 {
        width: 400px;
        height: 400px;
        background: rgba(59, 130, 246, 0.15);
        top: -200px;
        right: -100px;
        animation-delay: 0s;
      }

      .orb-2 {
        width: 300px;
        height: 300px;
        background: rgba(139, 92, 246, 0.15);
        bottom: -150px;
        left: -100px;
        animation-delay: 2s;
      }

      .orb-3 {
        width: 200px;
        height: 200px;
        background: rgba(6, 182, 212, 0.15);
        top: 50%;
        left: 50%;
        animation-delay: 4s;
      }

      @keyframes orbFloat {
        0%, 100% { transform: translate(0, 0) scale(1); }
        33% { transform: translate(30px, -30px) scale(1.1); }
        66% { transform: translate(-20px, 20px) scale(0.9); }
      }

      /* ============================================
         THEME SWITCHER BUTTON
         ============================================ */
      .theme-switcher {
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 10px;
      }

      .theme-menu {
        display: flex;
        flex-direction: column;
        gap: 8px;
        opacity: 0;
        visibility: hidden;
        transform: translateY(20px);
        transition: all 0.3s ease;
      }

      .theme-switcher:hover .theme-menu,
      .theme-menu.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
      }

      .theme-menu-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 16px;
        background: #ffffff;
        backdrop-filter: blur(10px);
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        color: #374151;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.3s ease;
        white-space: nowrap;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
      }

      .theme-menu-item:hover {
        background: #f3f4f6;
        border-color: #667eea;
        color: #667eea;
        transform: translateX(-5px);
      }

      .theme-menu-item.active {
        background: #eef2ff;
        border-color: #667eea;
        color: #667eea;
      }

      .theme-menu-item .theme-icon {
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
      }

      .theme-menu-item .theme-icon svg {
        width: 18px;
        height: 18px;
        stroke: currentColor;
        stroke-dasharray: 100;
        stroke-dashoffset: 100;
        animation: iconDraw 0.5s ease forwards;
        transition: all 0.3s ease;
      }

      .theme-menu-item:hover .theme-icon svg {
        transform: scale(1.2);
        filter: drop-shadow(0 0 6px currentColor);
      }

      .theme-switcher-btn {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        border: none;
        cursor: pointer;
        background: #ffffff;
        color: #667eea;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        transition: all 0.3s ease;
        animation: none;
      }

      .theme-switcher-btn svg {
        width: 28px;
        height: 28px;
        stroke: #ffffff;
        stroke-dasharray: 100;
        stroke-dashoffset: 100;
        animation: iconDraw 0.8s ease forwards;
        transition: all 0.3s ease;
      }

      .theme-switcher:hover .theme-switcher-btn svg {
        transform: scale(1.1) rotate(10deg);
        filter: none;
        stroke: #667eea;
      }

      .theme-switcher:hover .theme-switcher-btn {
        animation: none;
        transform: scale(1.1);
        background: #ffffff;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
      }

      @keyframes btnPulse {
        0%, 100% { box-shadow: 0 0 20px rgba(59, 130, 246, 0.2); }
        50% { box-shadow: 0 0 30px rgba(59, 130, 246, 0.3); }
      }

      .theme-indicator {
        position: fixed;
        bottom: 100px;
        right: 30px;
        background: rgba(15, 23, 42, 0.9);
        backdrop-filter: blur(10px);
        padding: 8px 16px;
        border-radius: 20px;
        color: #94a3b8;
        font-size: 12px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        opacity: 0;
        transform: translateX(20px);
        transition: all 0.3s ease;
        pointer-events: none;
      }

      .theme-indicator.show {
        opacity: 1;
        transform: translateX(0);
      }

      /* ============================================
         THEME 2: AURORA BOREALIS
         ============================================ */
      body.theme-aurora {
        background: linear-gradient(135deg, #0c1821 0%, #1b2838 50%, #0f1c2e 100%);
      }

      body.theme-aurora .bg-animation::before {
        background: 
          radial-gradient(ellipse at 20% 20%, rgba(34, 197, 94, 0.2) 0%, transparent 50%),
          radial-gradient(ellipse at 80% 80%, rgba(6, 182, 212, 0.2) 0%, transparent 50%),
          radial-gradient(ellipse at 50% 50%, rgba(168, 85, 247, 0.15) 0%, transparent 40%);
        animation: auroraPulse 20s ease-in-out infinite;
      }

      @keyframes auroraPulse {
        0%, 100% { transform: translate(0, 0) scale(1) rotate(0deg); }
        25% { transform: translate(5%, -3%) scale(1.1) rotate(2deg); }
        50% { transform: translate(-3%, 5%) scale(0.95) rotate(-1deg); }
        75% { transform: translate(2%, 2%) scale(1.05) rotate(1deg); }
      }

      body.theme-aurora .cyber-grid {
        background-image: none;
        background: 
          repeating-linear-gradient(
            0deg,
            transparent,
            transparent 100px,
            rgba(34, 197, 94, 0.03) 100px,
            rgba(34, 197, 94, 0.03) 101px
          );
        animation: auroraWave 15s ease-in-out infinite;
      }

      @keyframes auroraWave {
        0%, 100% { opacity: 0.5; transform: scaleY(1); }
        50% { opacity: 1; transform: scaleY(1.2); }
      }

      body.theme-aurora .orb-1 {
        background: rgba(34, 197, 94, 0.2);
        animation: auroraOrb1 12s ease-in-out infinite;
      }

      body.theme-aurora .orb-2 {
        background: rgba(6, 182, 212, 0.2);
        animation: auroraOrb2 15s ease-in-out infinite;
      }

      body.theme-aurora .orb-3 {
        background: rgba(168, 85, 247, 0.2);
        animation: auroraOrb3 18s ease-in-out infinite;
      }

      @keyframes auroraOrb1 {
        0%, 100% { transform: translate(0, 0) scale(1); filter: blur(60px); }
        33% { transform: translate(100px, 50px) scale(1.3); filter: blur(80px); }
        66% { transform: translate(-50px, -30px) scale(0.8); filter: blur(40px); }
      }

      @keyframes auroraOrb2 {
        0%, 100% { transform: translate(0, 0) scale(1); }
        50% { transform: translate(-80px, -60px) scale(1.4); }
      }

      @keyframes auroraOrb3 {
        0%, 100% { transform: translate(0, 0) scale(1); }
        33% { transform: translate(60px, -40px) scale(1.2); }
        66% { transform: translate(-40px, 60px) scale(0.9); }
      }

      body.theme-aurora .particle {
        background: rgba(34, 197, 94, 0.6);
        box-shadow: 0 0 10px rgba(34, 197, 94, 0.8), 0 0 20px rgba(6, 182, 212, 0.4);
      }

      body.theme-aurora .glow-line {
        background: linear-gradient(to bottom, transparent, rgba(34, 197, 94, 0.6), rgba(6, 182, 212, 0.6), transparent);
      }

      body.theme-aurora .login-card {
        background: rgba(12, 24, 33, 0.8);
        border: 1px solid rgba(34, 197, 94, 0.2);
      }

      body.theme-aurora .login-card::before {
        background: linear-gradient(45deg, #22c55e, #06b6d4, #a855f7, #22c55e);
        background-size: 400% 400%;
      }

      body.theme-aurora .login-icon {
        background: linear-gradient(135deg, #22c55e 0%, #06b6d4 100%);
        box-shadow: 
          0 10px 30px rgba(34, 197, 94, 0.4),
          0 0 60px rgba(6, 182, 212, 0.3);
      }

      body.theme-aurora .login-title {
        background: linear-gradient(135deg, #22c55e 0%, #06b6d4 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        text-shadow: none;
      }

      body.theme-aurora .neon-glow {
        filter: drop-shadow(0 0 10px rgba(34, 197, 94, 0.5));
      }

      body.theme-aurora .form-input:focus {
        border-color: #22c55e;
        box-shadow: 
          0 0 0 4px rgba(34, 197, 94, 0.15),
          0 0 30px rgba(6, 182, 212, 0.2);
      }

      body.theme-aurora .submit-btn {
        background: linear-gradient(135deg, #22c55e 0%, #06b6d4 100%);
      }

      body.theme-aurora .submit-btn:hover {
        box-shadow: 
          0 10px 30px rgba(34, 197, 94, 0.4),
          0 0 60px rgba(6, 182, 212, 0.3);
      }

      body.theme-aurora .theme-switcher-btn {
        background: linear-gradient(135deg, #22c55e 0%, #06b6d4 100%);
        box-shadow: 
          0 10px 30px rgba(34, 197, 94, 0.4),
          0 0 60px rgba(6, 182, 212, 0.2);
      }

      /* ============================================
         THEME 3: HOLOGRAPHIC / IRIDESCENT
         ============================================ */
      body.theme-holo {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f0f1a 100%);
      }

      body.theme-holo .bg-animation::before {
        background: 
          conic-gradient(from 0deg at 50% 50%, 
            rgba(255, 0, 128, 0.15), 
            rgba(0, 255, 255, 0.15), 
            rgba(255, 255, 0, 0.15), 
            rgba(255, 0, 128, 0.15));
        animation: holoSpin 30s linear infinite;
      }

      @keyframes holoSpin {
        0% { transform: rotate(0deg) scale(2); }
        100% { transform: rotate(360deg) scale(2); }
      }

      body.theme-holo .cyber-grid {
        background-image: 
          linear-gradient(rgba(255, 0, 128, 0.05) 1px, transparent 1px),
          linear-gradient(90deg, rgba(0, 255, 255, 0.05) 1px, transparent 1px);
        background-size: 30px 30px;
        animation: holoGrid 10s linear infinite;
      }

      @keyframes holoGrid {
        0% { transform: perspective(500px) rotateX(60deg) translateY(0); background-position: 0 0; }
        100% { transform: perspective(500px) rotateX(60deg) translateY(30px); background-position: 30px 30px; }
      }

      body.theme-holo .orb-1 {
        background: conic-gradient(from 0deg, rgba(255, 0, 128, 0.3), rgba(0, 255, 255, 0.3), rgba(255, 255, 0, 0.3), rgba(255, 0, 128, 0.3));
        animation: holoOrb 8s linear infinite;
        filter: blur(80px);
      }

      body.theme-holo .orb-2 {
        background: conic-gradient(from 120deg, rgba(0, 255, 255, 0.3), rgba(255, 255, 0, 0.3), rgba(255, 0, 128, 0.3), rgba(0, 255, 255, 0.3));
        animation: holoOrb 10s linear infinite reverse;
        filter: blur(80px);
      }

      body.theme-holo .orb-3 {
        background: conic-gradient(from 240deg, rgba(255, 255, 0, 0.3), rgba(255, 0, 128, 0.3), rgba(0, 255, 255, 0.3), rgba(255, 255, 0, 0.3));
        animation: holoOrb 12s linear infinite;
        filter: blur(80px);
      }

      @keyframes holoOrb {
        0% { transform: rotate(0deg) translate(0, 0); }
        100% { transform: rotate(360deg) translate(0, 0); }
      }

      body.theme-holo .particle {
        background: linear-gradient(135deg, #ff0080, #00ffff, #ffff00);
        background-size: 200% 200%;
        animation: float 20s infinite, holoParticle 3s ease-in-out infinite;
        box-shadow: 0 0 15px rgba(255, 0, 128, 0.6), 0 0 30px rgba(0, 255, 255, 0.4);
      }

      @keyframes holoParticle {
        0%, 100% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
      }

      body.theme-holo .glow-line {
        background: linear-gradient(to bottom, 
          transparent, 
          rgba(255, 0, 128, 0.6), 
          rgba(0, 255, 255, 0.6), 
          rgba(255, 255, 0, 0.6), 
          transparent);
      }

      body.theme-holo .login-card {
        background: rgba(26, 26, 46, 0.7);
        border: 1px solid rgba(255, 255, 255, 0.15);
        position: relative;
        overflow: hidden;
      }

      body.theme-holo .login-card::before {
        background: linear-gradient(45deg, 
          #ff0080, #ff8c00, #ffff00, #00ff00, #00ffff, #0080ff, #8000ff, #ff0080);
        background-size: 400% 400%;
        animation: holoGlow 4s ease infinite;
      }

      @keyframes holoGlow {
        0%, 100% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
      }

      body.theme-holo .login-card::after {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(
          90deg,
          transparent,
          rgba(255, 255, 255, 0.1),
          transparent
        );
        animation: holoShine 3s ease-in-out infinite;
        pointer-events: none;
        z-index: 1;
      }

      @keyframes holoShine {
        0% { left: -100%; }
        50%, 100% { left: 100%; }
      }

      body.theme-holo .login-icon {
        background: linear-gradient(135deg, #ff0080 0%, #00ffff 50%, #ffff00 100%);
        background-size: 200% 200%;
        animation: holoIcon 3s ease infinite;
        box-shadow: 
          0 10px 30px rgba(255, 0, 128, 0.3),
          0 0 60px rgba(0, 255, 255, 0.3);
      }

      @keyframes holoIcon {
        0%, 100% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
      }

      body.theme-holo .login-title {
        background: linear-gradient(135deg, #ff0080 0%, #00ffff 50%, #ffff00 100%);
        background-size: 200% 200%;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        animation: holoText 4s ease infinite;
        text-shadow: none;
      }

      @keyframes holoText {
        0%, 100% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
      }

      body.theme-holo .neon-glow {
        filter: drop-shadow(0 0 10px rgba(255, 0, 128, 0.5)) drop-shadow(0 0 20px rgba(0, 255, 255, 0.3));
      }

      body.theme-holo .form-input:focus {
        border-color: #00ffff;
        box-shadow: 
          0 0 0 4px rgba(0, 255, 255, 0.15),
          0 0 30px rgba(255, 0, 128, 0.2);
      }

      body.theme-holo .submit-btn {
        background: linear-gradient(135deg, #ff0080 0%, #00ffff 50%, #ffff00 100%);
        background-size: 200% 200%;
        animation: holoBtn 3s ease infinite;
      }

      @keyframes holoBtn {
        0%, 100% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
      }

      body.theme-holo .submit-btn:hover {
        box-shadow: 
          0 10px 30px rgba(255, 0, 128, 0.4),
          0 0 60px rgba(0, 255, 255, 0.3);
      }

      body.theme-holo .theme-switcher-btn {
        background: linear-gradient(135deg, #ff0080 0%, #00ffff 50%, #ffff00 100%);
        background-size: 200% 200%;
        animation: holoBtn 3s ease infinite;
        box-shadow: 
          0 10px 30px rgba(255, 0, 128, 0.4),
          0 0 60px rgba(0, 255, 255, 0.2);
      }

      /* ============================================
         THEME 4: CLASSIC (Original animate-ui)
         ============================================ */
      body.theme-classic {
        background: radial-gradient(circle at top, #1c2541, #0b0c10 60%);
        overflow: auto;
      }

      body.theme-classic .bg-animation,
      body.theme-classic .cyber-grid,
      body.theme-classic .orb,
      body.theme-classic .particles,
      body.theme-classic .glow-lines {
        display: none !important;
      }

      body.theme-classic .login-container {
        min-height: 100vh;
        padding: 2rem;
        display: flex;
        justify-content: center;
        align-items: center;
      }

      body.theme-classic .login-card {
        width: min(420px, 90vw);
        max-width: 420px;
        padding: 2rem;
        border-radius: 1rem;
        box-shadow: 0 20px 45px rgba(0, 0, 0, 0.45);
        background: linear-gradient(135deg, #1f7a8c, #0b1d51);
        color: #f5f8ff;
        text-align: center;
        border: none;
        backdrop-filter: none;
        animation: none;
        transform: none !important;
      }

      body.theme-classic .login-card::before {
        display: none;
      }

      body.theme-classic .login-card:hover {
        transform: none !important;
        box-shadow: 0 20px 45px rgba(0, 0, 0, 0.45) !important;
      }

      body.theme-classic .login-icon {
        width: 70px;
        height: 70px;
        margin: 0 auto 1rem;
        background: linear-gradient(135deg, #ffd166, #f97316);
        border-radius: 50%;
        display: flex;
        justify-content: center;
        align-items: center;
        font-size: 32px;
        box-shadow: 0 8px 20px rgba(249, 115, 22, 0.3);
        animation: none;
      }

      body.theme-classic .login-title {
        margin-bottom: 0.75rem;
        font-size: clamp(1.8rem, 4vw, 2.4rem);
        background: none;
        -webkit-background-clip: unset;
        -webkit-text-fill-color: #f5f8ff;
        color: #f5f8ff;
        text-shadow: none;
      }

      body.theme-classic .login-subtitle {
        margin-bottom: 1rem;
        color: rgba(245, 248, 255, 0.85);
      }

      body.theme-classic .neon-glow {
        text-shadow: none;
        filter: none;
      }

      body.theme-classic .form-group {
        margin-bottom: 0.9rem;
        text-align: left;
      }

      body.theme-classic .form-group label {
        font-size: 0.9rem;
        color: rgba(245, 248, 255, 0.85);
        text-transform: none;
        letter-spacing: normal;
        margin-bottom: 0.4rem;
      }

      body.theme-classic .form-input {
        padding: 0.85rem 1.25rem 0.85rem 50px;
        border-radius: 0.75rem;
        border: 1px solid rgba(255, 255, 255, 0.25);
        background: rgba(15, 24, 52, 0.6);
        color: #f5f8ff;
        font-size: 1rem;
      }

      body.theme-classic .form-input:focus {
        border-color: #9ef3ff;
        box-shadow: 0 0 0 3px rgba(158, 243, 255, 0.25);
      }

      body.theme-classic .input-wrapper svg {
        color: rgba(245, 248, 255, 0.6);
      }

      body.theme-classic .error-message {
        background: rgba(239, 68, 68, 0.2);
        border: 1px solid rgba(239, 68, 68, 0.4);
        border-radius: 0.5rem;
        padding: 0.75rem 1rem;
        color: #fca5a5;
        animation: none;
      }

      body.theme-classic .submit-btn {
        padding: 0.9rem 1.4rem;
        border-radius: 999px;
        background: linear-gradient(135deg, #ffd166, #f97316);
        color: #0b0c10;
        font-weight: 700;
        text-transform: none;
        letter-spacing: normal;
        animation: none;
      }

      body.theme-classic .submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 15px 35px rgba(249, 115, 22, 0.35);
      }

      body.theme-classic .login-footer {
        margin-top: 1.5rem;
        color: rgba(245, 248, 255, 0.65);
      }

      body.theme-classic .theme-switcher-btn {
        background: linear-gradient(135deg, #ffd166, #f97316);
        box-shadow: 0 8px 20px rgba(249, 115, 22, 0.3);
        animation: none;
      }

      body.theme-classic .theme-switcher-btn:hover {
        box-shadow: 0 12px 30px rgba(249, 115, 22, 0.4);
      }

      body.theme-classic .card-glare {
        display: none !important;
      }

      /* ============================================
         THEME 5: DARK MINIMAL
         ============================================ */
      body.theme-dark {
        background: #0a0a0a;
        overflow: auto;
      }

      body.theme-dark .bg-animation,
      body.theme-dark .cyber-grid,
      body.theme-dark .orb,
      body.theme-dark .particles,
      body.theme-dark .glow-lines {
        display: none !important;
      }

      body.theme-dark .login-container {
        min-height: 100vh;
        padding: 2rem;
      }

      body.theme-dark .login-card {
        background: #141414;
        border: 1px solid #2a2a2a;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        backdrop-filter: none;
        animation: none;
        transform: none !important;
      }

      body.theme-dark .login-card::before {
        display: none;
      }

      body.theme-dark .login-card:hover {
        transform: none !important;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5) !important;
        border-color: #3a3a3a;
      }

      body.theme-dark .login-icon {
        width: 72px;
        height: 72px;
        background: #1f1f1f;
        border: 1px solid #2a2a2a;
        border-radius: 16px;
        box-shadow: none;
        animation: none;
      }

      body.theme-dark .login-title {
        background: none;
        -webkit-background-clip: unset;
        -webkit-text-fill-color: #ffffff;
        color: #ffffff;
        text-shadow: none;
        font-weight: 600;
      }

      body.theme-dark .login-subtitle {
        color: #666666;
      }

      body.theme-dark .neon-glow {
        text-shadow: none;
        filter: none;
      }

      body.theme-dark .form-group label {
        color: #888888;
        font-weight: 500;
      }

      body.theme-dark .form-input {
        background: #1a1a1a;
        border: 1px solid #2a2a2a;
        color: #ffffff;
        border-radius: 10px;
      }

      body.theme-dark .form-input::placeholder {
        color: #555555;
      }

      body.theme-dark .form-input:focus {
        border-color: #ffffff;
        box-shadow: none;
        background: #1f1f1f;
      }

      body.theme-dark .input-wrapper svg {
        color: #555555;
      }

      body.theme-dark .input-wrapper:focus-within svg {
        color: #ffffff;
      }

      body.theme-dark .error-message {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: #ef4444;
        animation: none;
      }

      body.theme-dark .submit-btn {
        background: #ffffff;
        color: #000000;
        border-radius: 10px;
        font-weight: 600;
        text-transform: none;
        letter-spacing: normal;
        animation: none;
      }

      body.theme-dark .submit-btn:hover {
        background: #f0f0f0;
        transform: none;
        box-shadow: none;
      }

      body.theme-dark .submit-btn::before {
        display: none;
      }

      body.theme-dark .login-footer {
        color: #444444;
      }

      body.theme-dark .theme-switcher-btn {
        background: #1f1f1f;
        border: 1px solid #2a2a2a;
        box-shadow: none;
        animation: none;
      }

      body.theme-dark .theme-switcher-btn:hover {
        background: #2a2a2a;
        box-shadow: none;
      }

      body.theme-dark .card-glare {
        display: none !important;
      }

      body.theme-dark .theme-indicator {
        background: #1a1a1a;
        border-color: #2a2a2a;
        color: #888888;
      }

      /* ============================================
         THEME 6: LIGHT / WHITE
         ============================================ */
      body.theme-light {
        background: #f5f5f7;
        overflow: auto;
        color: #333333 !important;
      }
      
      body.theme-light label,
      body.theme-light input,
      body.theme-light a,
      body.theme-light p,
      body.theme-light span:not(.btn-text):not(.btn-loading span),
      body.theme-light h1,
      body.theme-light h2,
      body.theme-light h3,
      body.theme-light h4,
      body.theme-light h5,
      body.theme-light h6 {
        color: #333333 !important;
        -webkit-text-fill-color: #333333 !important;
      }
      
      /* Ensure button text stays white */
      body.theme-light .submit-btn,
      body.theme-light .submit-btn .btn-text,
      body.theme-light .submit-btn .btn-loading,
      body.theme-light .submit-btn span {
        color: #ffffff !important;
        -webkit-text-fill-color: #ffffff !important;
      }

      body.theme-light .bg-animation,
      body.theme-light .cyber-grid,
      body.theme-light .orb,
      body.theme-light .particles,
      body.theme-light .glow-lines {
        display: none !important;
      }

      body.theme-light .login-container {
        min-height: 100vh;
        padding: 2rem;
      }

      body.theme-light .login-card {
        background: #ffffff;
        border: 1px solid #e5e5e5;
        border-radius: 20px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
        backdrop-filter: none;
        animation: none;
        transform: none !important;
      }

      body.theme-light .login-card::before {
        display: none;
      }

      body.theme-light .login-card:hover {
        transform: none !important;
        box-shadow: 0 15px 50px rgba(0, 0, 0, 0.1) !important;
      }

      body.theme-light .login-icon {
        width: 72px;
        height: 72px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 18px;
        box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
        animation: none;
      }

      body.theme-light .login-title {
        background: none;
        -webkit-background-clip: unset;
        -webkit-text-fill-color: #1a1a1a;
        color: #1a1a1a;
        text-shadow: none;
        font-weight: 700;
      }

      body.theme-light .login-subtitle {
        color: #666666;
      }

      body.theme-light .neon-glow {
        text-shadow: none;
        filter: none;
      }

      body.theme-light .form-group label {
        color: #333333;
        font-weight: 600;
      }

      body.theme-light .form-input {
        background: #ffffff;
        border: 2px solid #d1d5db;
        color: #1f2937 !important;
        border-radius: 12px;
      }

      body.theme-light .form-input::placeholder {
        color: #6b7280 !important;
        opacity: 1;
      }

      body.theme-light .form-input:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
        background: #ffffff;
      }

      body.theme-light .input-wrapper svg {
        color: #1f2937;
        stroke: #1f2937;
      }

      body.theme-light .input-wrapper:focus-within svg {
        color: #667eea;
        stroke: #667eea;
      }

      body.theme-light .error-message {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #dc2626;
        animation: none;
      }

      body.theme-light .submit-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #ffffff !important;
        -webkit-text-fill-color: #ffffff !important;
        border-radius: 12px;
        font-weight: 600;
        text-transform: none;
        letter-spacing: normal;
        animation: none;
      }

      body.theme-light .submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
      }

      body.theme-light .submit-btn::before {
        display: none;
      }

      body.theme-light .login-footer {
        color: #999999;
      }

      body.theme-light .theme-switcher-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
        animation: none;
      }

      body.theme-light .theme-switcher-btn:hover {
        box-shadow: 0 12px 32px rgba(102, 126, 234, 0.4);
      }

      body.theme-light .card-glare {
        display: none !important;
      }

      body.theme-light .theme-indicator {
        background: #ffffff;
        border-color: #e5e5e5;
        color: #666666;
      }

      /* Light theme - Success Overlay */
      body.theme-light .login-success-overlay {
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(10px);
      }

      body.theme-light .success-content {
        color: #333333;
      }

      body.theme-light .success-content p {
        background: linear-gradient(135deg, #22c55e, #10b981);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
      }

      body.theme-light .redirect-text {
        color: #666666 !important;
        -webkit-text-fill-color: #666666 !important;
      }

      body.theme-light .success-icon {
        color: #22c55e;
      }

      body.theme-light .success-icon circle,
      body.theme-light .success-icon path {
        stroke: #22c55e;
      }

      /* ============================================
         THEME 7: HOMEPAGE STYLE (Match index.php 100%)
         ============================================ */
      body.theme-homepage {
        background: #0a0a0f;
        overflow: auto;
      }

      /* Homepage Background - Animated Gradient */
      body.theme-homepage::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: 
          radial-gradient(ellipse at 20% 20%, rgba(59, 130, 246, 0.15) 0%, transparent 50%),
          radial-gradient(ellipse at 80% 80%, rgba(139, 92, 246, 0.1) 0%, transparent 50%),
          radial-gradient(ellipse at 50% 50%, rgba(6, 182, 212, 0.08) 0%, transparent 60%);
        animation: homepageBgPulse 20s ease-in-out infinite;
        z-index: 0;
        pointer-events: none;
      }

      @keyframes homepageBgPulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.8; transform: scale(1.1); }
      }

      body.theme-homepage .bg-animation,
      body.theme-homepage .cyber-grid,
      body.theme-homepage .glow-lines {
        display: none !important;
      }

      /* Show orbs with homepage style */
      body.theme-homepage .orb {
        display: block !important;
        filter: blur(80px);
        animation: homepageFloat 20s ease-in-out infinite;
      }

      body.theme-homepage .orb-1 {
        width: 500px;
        height: 500px;
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.3), rgba(139, 92, 246, 0.2)) !important;
        top: -200px;
        right: -100px;
        animation-delay: 0s;
      }

      body.theme-homepage .orb-2 {
        width: 400px;
        height: 400px;
        background: linear-gradient(135deg, rgba(6, 182, 212, 0.25), rgba(59, 130, 246, 0.2)) !important;
        bottom: -150px;
        left: -100px;
        animation-delay: 5s;
      }

      body.theme-homepage .orb-3 {
        width: 300px;
        height: 300px;
        background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(236, 72, 153, 0.15)) !important;
        top: 40%;
        left: 60%;
        animation-delay: 10s;
      }

      @keyframes homepageFloat {
        0%, 100% { transform: translate(0, 0) rotate(0deg); }
        25% { transform: translate(50px, -50px) rotate(5deg); }
        50% { transform: translate(0, -100px) rotate(0deg); }
        75% { transform: translate(-50px, -50px) rotate(-5deg); }
      }

      /* Show particles with homepage style */
      body.theme-homepage .particles {
        display: block !important;
      }

      body.theme-homepage .particle {
        background: rgba(96, 165, 250, 0.6) !important;
        box-shadow: 0 0 10px rgba(96, 165, 250, 0.8) !important;
        animation: homepageRise 15s infinite !important;
      }

      @keyframes homepageRise {
        0% { bottom: -10px; opacity: 0; transform: scale(0); }
        10% { opacity: 1; transform: scale(1); }
        90% { opacity: 1; }
        100% { bottom: 100vh; opacity: 0; transform: scale(0.5); }
      }

      body.theme-homepage .login-container {
        min-height: 100vh;
        padding: 2rem;
        position: relative;
        z-index: 10;
      }

      body.theme-homepage .login-card {
        background: rgba(15, 23, 42, 0.8);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 24px;
        box-shadow: 
          0 25px 50px -12px rgba(0, 0, 0, 0.5),
          0 0 0 1px rgba(255, 255, 255, 0.05),
          inset 0 1px 0 rgba(255, 255, 255, 0.1);
        animation: homepageCardAppear 0.8s ease-out;
        transform-style: preserve-3d;
        -webkit-transform-style: preserve-3d;
        will-change: transform, box-shadow;
        transition: transform 0.1s ease-out, box-shadow 0.3s ease;
      }

      @keyframes homepageCardAppear {
        from { 
          opacity: 0; 
          transform: translateY(30px) scale(0.95); 
        }
        to { 
          opacity: 1; 
          transform: translateY(0) scale(1); 
        }
      }

      body.theme-homepage .login-card::before {
        content: '';
        position: absolute;
        top: -2px;
        left: -2px;
        right: -2px;
        bottom: -2px;
        background: linear-gradient(135deg, 
          rgba(59, 130, 246, 0.5), 
          rgba(139, 92, 246, 0.5), 
          rgba(6, 182, 212, 0.5));
        background-size: 200% 200%;
        border-radius: 26px;
        z-index: -1;
        animation: homepageGlow 4s ease infinite;
        opacity: 0.5;
        filter: blur(10px);
      }

      @keyframes homepageGlow {
        0%, 100% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
      }

      body.theme-homepage .login-card:hover {
        transform: rotateX(0deg) rotateY(0deg) scale3d(1.02, 1.02, 1.02);
        box-shadow: 
          0 30px 60px -12px rgba(0, 0, 0, 0.6),
          0 0 0 1px rgba(255, 255, 255, 0.1),
          inset 0 1px 0 rgba(255, 255, 255, 0.1) !important;
      }

      body.theme-homepage .login-card:hover::before {
        opacity: 0.8;
      }

      /* Glare effect for Homepage theme */
      body.theme-homepage .card-glare {
        display: block !important;
      }

      body.theme-homepage .login-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 20px;
        box-shadow: 
          0 10px 30px rgba(102, 126, 234, 0.4),
          0 0 60px rgba(118, 75, 162, 0.3);
        animation: homepageIconPulse 3s ease-in-out infinite;
      }

      @keyframes homepageIconPulse {
        0%, 100% { 
          box-shadow: 
            0 10px 30px rgba(102, 126, 234, 0.4),
            0 0 60px rgba(118, 75, 162, 0.3);
          transform: scale(1);
        }
        50% { 
          box-shadow: 
            0 15px 40px rgba(102, 126, 234, 0.6),
            0 0 80px rgba(118, 75, 162, 0.5);
          transform: scale(1.05);
        }
      }

      body.theme-homepage .login-title {
        font-size: 2rem;
        font-weight: 700;
        background: linear-gradient(135deg, #fff 0%, #94a3b8 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        text-shadow: none;
      }

      body.theme-homepage .login-subtitle {
        color: #94a3b8;
        font-size: 0.95rem;
      }

      body.theme-homepage .neon-glow {
        text-shadow: none;
        filter: none;
      }

      body.theme-homepage .form-group label {
        color: #94a3b8;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 0.8rem;
      }

      body.theme-homepage .form-input {
        background: rgba(30, 41, 59, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #fff;
        border-radius: 12px;
        padding: 16px 16px 16px 50px;
        transition: all 0.3s ease;
      }

      body.theme-homepage .form-input::placeholder {
        color: #64748b;
      }

      body.theme-homepage .form-input:focus {
        border-color: #667eea;
        box-shadow: 
          0 0 0 4px rgba(102, 126, 234, 0.15),
          0 0 30px rgba(102, 126, 234, 0.2);
        background: rgba(30, 41, 59, 0.8);
      }

      body.theme-homepage .input-wrapper svg {
        color: #64748b;
        transition: color 0.3s ease;
      }

      body.theme-homepage .input-wrapper:focus-within svg {
        color: #667eea;
      }

      body.theme-homepage .error-message {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: #f87171;
        border-radius: 12px;
        animation: homepageShake 0.5s ease;
      }

      @keyframes homepageShake {
        0%, 100% { transform: translateX(0); }
        20% { transform: translateX(-5px); }
        40% { transform: translateX(5px); }
        60% { transform: translateX(-5px); }
        80% { transform: translateX(5px); }
      }

      body.theme-homepage .submit-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #ffffff;
        border-radius: 12px;
        font-weight: 600;
        text-transform: none;
        letter-spacing: normal;
        padding: 16px;
        font-size: 1rem;
        border: none;
        cursor: pointer;
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
      }

      body.theme-homepage .submit-btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transition: left 0.5s ease;
      }

      body.theme-homepage .submit-btn:hover {
        transform: translateY(-3px);
        box-shadow: 
          0 15px 35px rgba(102, 126, 234, 0.4),
          0 5px 15px rgba(0, 0, 0, 0.3);
      }

      body.theme-homepage .submit-btn:hover::before {
        left: 100%;
      }

      body.theme-homepage .submit-btn:active {
        transform: translateY(-1px);
      }

      body.theme-homepage .login-footer {
        color: #64748b;
        margin-top: 1.5rem;
      }

      body.theme-homepage .login-footer a {
        color: #667eea;
        text-decoration: none;
        transition: color 0.3s ease;
      }

      body.theme-homepage .login-footer a:hover {
        color: #764ba2;
      }

      body.theme-homepage .back-home-btn {
        background: rgba(102, 126, 234, 0.1);
        border: 1px solid rgba(102, 126, 234, 0.3);
        color: #667eea !important;
      }

      body.theme-homepage .back-home-btn:hover {
        background: rgba(102, 126, 234, 0.2);
        border-color: rgba(102, 126, 234, 0.5);
        color: #a78bfa !important;
        box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
      }

      body.theme-homepage .back-icon {
        stroke: #667eea;
      }

      body.theme-homepage .back-home-btn:hover .back-icon {
        stroke: #a78bfa;
      }

      body.theme-homepage .theme-switcher-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
        animation: none;
        border: none;
        transition: all 0.3s ease;
      }

      body.theme-homepage .theme-switcher-btn:hover {
        transform: scale(1.1) rotate(10deg);
        box-shadow: 0 12px 32px rgba(102, 126, 234, 0.5);
      }

      body.theme-homepage .card-glare {
        display: none !important;
      }

      body.theme-homepage .theme-indicator {
        background: rgba(15, 23, 42, 0.9);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(102, 126, 234, 0.3);
        color: #fff;
        border-radius: 12px;
      }

      /* Theme transition */
      body, .login-card, .login-icon, .submit-btn, .orb, .particle, .glow-line, .bg-animation::before {
        transition: all 0.5s ease;
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
    <!-- Background Effects -->
    <div class="bg-animation"></div>
    <div class="cyber-grid"></div>
    
    <!-- Orbs -->
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>
    
    <!-- Floating Particles -->
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
      <div class="particle"></div>
    </div>
    
    <!-- Glow Lines -->
    <div class="glow-lines">
      <div class="glow-line"></div>
      <div class="glow-line"></div>
      <div class="glow-line"></div>
      <div class="glow-line"></div>
      <div class="glow-line"></div>
    </div>

    <!-- Login Container -->
    <div class="login-container">
      <div class="login-card <?php echo $login_success ? 'success' : ''; ?>" id="loginCard">
        <div class="login-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0110 0v4"/>
            <circle cx="12" cy="16" r="1"/>
          </svg>
        </div>
        <h1 class="login-title neon-glow">เข้าสู่ระบบ</h1>
        <p class="login-subtitle"><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></p>
        
        <?php if ($login_error !== ''): ?>
          <div class="error-message">
            <svg class="animated-icon error-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/>
              <line x1="12" y1="8" x2="12" y2="12"/>
              <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <?php echo htmlspecialchars($login_error, ENT_QUOTES, 'UTF-8'); ?>
          </div>
        <?php endif; ?>
        
        <form id="loginForm" action="" method="post">
          <div class="form-group">
            <label for="username">Username</label>
            <div class="input-wrapper">
              <input
                type="text"
                id="username"
                name="username"
                class="form-input"
                placeholder="กรอกชื่อผู้ใช้"
                value="<?php echo htmlspecialchars($old_username, ENT_QUOTES, 'UTF-8'); ?>"
                autocomplete="username"
                required
              />
              <svg class="animated-icon input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
              </svg>
            </div>
          </div>
          
          <div class="form-group">
            <label for="password">Password</label>
            <div class="input-wrapper">
              <input
                type="password"
                id="password"
                name="password"
                class="form-input"
                placeholder="กรอกรหัสผ่าน"
                autocomplete="current-password"
                required
              />
              <svg class="animated-icon input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0110 0v4"/>
              </svg>
            </div>
          </div>
          
          <button type="submit" class="submit-btn" id="submitBtn">
            <span class="btn-text">เข้าสู่ระบบ</span>
            <span class="btn-loading" style="display: none;">
              <svg class="spinner" viewBox="0 0 24 24" width="20" height="20">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round" stroke-dasharray="30 70"/>
              </svg>
              กำลังเข้าสู่ระบบ...
            </span>
          </button>
        </form>
        
        <!-- Success overlay -->
        <div class="login-success-overlay" id="successOverlay" style="display: none;">
          <div class="success-content">
            <svg class="success-icon" viewBox="0 0 24 24" width="60" height="60" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/>
              <path d="M8 12l3 3 5-6"/>
            </svg>
            <p>เข้าสู่ระบบสำเร็จ!</p>
            <span class="redirect-text">กำลังนำคุณไปยังหน้าจัดการ...</span>
          </div>
        </div>
        
        <div class="login-footer">
          <a href="forgot_password.php" class="forgot-password-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
              <circle cx="12" cy="12" r="3"/>
              <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
            </svg>
            ลืมรหัสผ่าน?
          </a>
          <a href="index.php" class="back-home-btn">
            <svg class="back-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            กลับหน้าหลัก
          </a>
          <p>© <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - สงวนลิขสิทธิ์</p>
        </div>
      </div>
    </div>

    <!-- Theme Switcher Button -->
    <div class="theme-switcher" id="themeSwitcher">
      <div class="theme-menu" id="themeMenu">
        <div class="theme-menu-item" data-theme="homepage">
          <span class="theme-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
          </span>
          <span>Homepage</span>
        </div>
        <div class="theme-menu-item" data-theme="cyber">
          <span class="theme-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/>
            </svg>
          </span>
          <span>Cyber Neon</span>
        </div>
        <div class="theme-menu-item" data-theme="aurora">
          <span class="theme-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17.5 19H9a7 7 0 110-14h8.5"/><path d="M21 12h-3"/><path d="M21 16h-5"/><path d="M21 8h-5"/>
            </svg>
          </span>
          <span>Aurora Borealis</span>
        </div>
        <div class="theme-menu-item" data-theme="holo">
          <span class="theme-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
            </svg>
          </span>
          <span>Holographic</span>
        </div>
        <div class="theme-menu-item" data-theme="classic">
          <span class="theme-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="13.5" cy="6.5" r="2.5"/><path d="M17 3a2.83 2.83 0 114 4L7.5 20.5 2 22l1.5-5.5L17 3z"/>
            </svg>
          </span>
          <span>Classic</span>
        </div>
        <div class="theme-menu-item" data-theme="dark">
          <span class="theme-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
            </svg>
          </span>
          <span>Dark</span>
        </div>
        <div class="theme-menu-item" data-theme="light">
          <span class="theme-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
            </svg>
          </span>
          <span>Light</span>
        </div>
      </div>
      <button class="theme-switcher-btn" id="themeSwitchBtn" title="เปลี่ยนธีม">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
      </button>
    </div>
    <div class="theme-indicator" id="themeIndicator">Homepage</div>

    <?php if (!empty($login_success)): ?>
      <script>
        document.addEventListener('DOMContentLoaded', function() {
          const card = document.getElementById('loginCard');
          const btn = document.getElementById('submitBtn');
          card.classList.add('success');
          btn.textContent = '✓ สำเร็จ! กำลังเข้าสู่ระบบ...';
          btn.style.background = 'linear-gradient(135deg, #22c55e 0%, #10b981 100%)';
          setTimeout(function() {
            window.location.href = 'Reports/dashboard.php';
          }, 1500);
        });
      </script>
    <?php endif; ?>
    
    <script>
      // Form submission animation
      document.getElementById('loginForm').addEventListener('submit', function(e) {
        const btn = document.getElementById('submitBtn');
        btn.classList.add('loading');
        btn.textContent = 'กำลังตรวจสอบ...';
      });
      
      // Input focus effects
      document.querySelectorAll('.form-input').forEach(input => {
        input.addEventListener('focus', function() {
          this.parentElement.parentElement.classList.add('focused');
        });
        input.addEventListener('blur', function() {
          this.parentElement.parentElement.classList.remove('focused');
        });
      });

      // ============================================
      // ✨ ELEGANT 3D PARALLAX SYSTEM - Monochrome Style
      // ============================================
      const card = document.getElementById('loginCard');
      const container = document.querySelector('.login-container');
      
      // Elegant Settings
      const maxTilt = 12; // Subtle tilt angle
      const glareOpacity = 0.25;
      const perspective = 1200;
      const accentColor = { r: 99, g: 102, b: 241 }; // Indigo - #6366f1
      
      // Create elegant glare element
      const glare = document.createElement('div');
      glare.className = 'card-glare';
      glare.style.cssText = `
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        border-radius: 24px;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.4s ease;
        z-index: 100;
      `;
      card.appendChild(glare);
      
      // Create subtle border glow
      const borderGlow = document.createElement('div');
      borderGlow.className = 'border-glow';
      borderGlow.style.cssText = `
        position: absolute;
        top: -2px;
        left: -2px;
        right: -2px;
        bottom: -2px;
        border-radius: 26px;
        pointer-events: none;
        z-index: -1;
        opacity: 0;
        transition: opacity 0.4s ease;
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.5), rgba(99, 102, 241, 0.1), rgba(99, 102, 241, 0.5));
        background-size: 200% 200%;
        filter: blur(10px);
      `;
      card.appendChild(borderGlow);
      
      // Set card styles for 3D effect
      card.style.transformStyle = 'preserve-3d';
      card.style.willChange = 'transform';
      container.style.perspective = perspective + 'px';
      
      // Smooth interpolation values
      let currentTiltX = 0;
      let currentTiltY = 0;
      let targetTiltX = 0;
      let targetTiltY = 0;
      let isHovering = false;
      let animationFrame = null;
      
      // Add elegant animation CSS
      if (!document.getElementById('elegantAnimations')) {
        const styleSheet = document.createElement('style');
        styleSheet.id = 'elegantAnimations';
        styleSheet.textContent = `
          @keyframes shimmer {
            0% { background-position: 200% 50%; }
            100% { background-position: -200% 50%; }
          }
          
          @keyframes breathe {
            0%, 100% { opacity: 0.3; filter: blur(10px); }
            50% { opacity: 0.5; filter: blur(15px); }
          }
          
          .border-glow.active {
            animation: breathe 3s ease-in-out infinite, shimmer 4s linear infinite;
          }
        `;
        document.head.appendChild(styleSheet);
      }
      
      // Smooth animation loop
      function smoothUpdate() {
        const smoothness = isHovering ? 0.12 : 0.06;
        
        currentTiltX += (targetTiltX - currentTiltX) * smoothness;
        currentTiltY += (targetTiltY - currentTiltY) * smoothness;
        
        // Apply smooth transform
        const scale = isHovering ? 1.02 : 1;
        card.style.transform = `
          rotateX(${currentTiltX}deg) 
          rotateY(${currentTiltY}deg)
          scale3d(${scale}, ${scale}, ${scale})
        `;
        
        animationFrame = requestAnimationFrame(smoothUpdate);
      }
      smoothUpdate();
      
      // Elegant parallax function
      function applyCardParallax(cardElement, glareElement) {
        cardElement.addEventListener('mousemove', function(e) {
          const rect = cardElement.getBoundingClientRect();
          const centerX = rect.left + rect.width / 2;
          const centerY = rect.top + rect.height / 2;
          
          // Calculate mouse position relative to card center (-1 to 1)
          const mouseX = (e.clientX - centerX) / (rect.width / 2);
          const mouseY = (e.clientY - centerY) / (rect.height / 2);
          
          // Clamp values
          const clampedX = Math.max(-1, Math.min(1, mouseX));
          const clampedY = Math.max(-1, Math.min(1, mouseY));
          
          // Set target tilt angles
          targetTiltX = -clampedY * maxTilt;
          targetTiltY = clampedX * maxTilt;
          
          // Elegant single-point glare
          const glareX = 50 + clampedX * 35;
          const glareY = 50 + clampedY * 35;
          const glareSize = 60 + Math.abs(clampedX * clampedY) * 20;
          
          glareElement.style.background = `
            radial-gradient(
              ellipse ${glareSize}% ${glareSize}% at ${glareX}% ${glareY}%,
              rgba(255, 255, 255, ${glareOpacity}) 0%,
              rgba(255, 255, 255, 0.08) 40%,
              transparent 70%
            )
          `;
          
          // Dynamic shadow - single color scheme
          const shadowX = clampedX * 25;
          const shadowY = clampedY * 25;
          const intensity = Math.sqrt(clampedX * clampedX + clampedY * clampedY);
          
          const r = accentColor.r;
          const g = accentColor.g;
          const b = accentColor.b;
          
          cardElement.style.boxShadow = `
            ${shadowX}px ${shadowY}px 40px rgba(0, 0, 0, ${0.25 + intensity * 0.1}),
            ${shadowX * 0.5}px ${shadowY * 0.5}px 20px rgba(${r}, ${g}, ${b}, ${0.15 + intensity * 0.1}),
            0 0 ${40 + intensity * 20}px rgba(${r}, ${g}, ${b}, ${0.08 + intensity * 0.05}),
            inset 0 1px 0 rgba(255, 255, 255, 0.1)
          `;
          
          // Update border glow position
          const angle = Math.atan2(clampedY, clampedX) * 180 / Math.PI;
          borderGlow.style.background = `
            linear-gradient(${angle + 135}deg, 
              rgba(${r}, ${g}, ${b}, ${0.4 + intensity * 0.2}), 
              rgba(${r}, ${g}, ${b}, 0.05), 
              rgba(${r}, ${g}, ${b}, ${0.4 + intensity * 0.2})
            )
          `;
          borderGlow.style.backgroundSize = '200% 200%';
        });
        
        // Mouse enter
        cardElement.addEventListener('mouseenter', function() {
          isHovering = true;
          glareElement.style.opacity = '1';
          borderGlow.style.opacity = '1';
          borderGlow.classList.add('active');
        });
        
        // Mouse leave - elegant reset
        cardElement.addEventListener('mouseleave', function() {
          isHovering = false;
          targetTiltX = 0;
          targetTiltY = 0;
          
          glareElement.style.opacity = '0';
          borderGlow.style.opacity = '0';
          borderGlow.classList.remove('active');
          
          // Reset to elegant default shadow
          const r = accentColor.r;
          const g = accentColor.g;
          const b = accentColor.b;
          
          cardElement.style.boxShadow = `
            0 25px 50px -12px rgba(0, 0, 0, 0.4),
            0 0 0 1px rgba(255, 255, 255, 0.05),
            0 0 40px rgba(${r}, ${g}, ${b}, 0.08),
            inset 0 1px 0 rgba(255, 255, 255, 0.1)
          `;
        });
      }
      
      // Apply elegant parallax effect
      applyCardParallax(card, glare);

      // ============================================
      // THEME SWITCHER
      // ============================================
      const themeSvgIcons = {
        homepage: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
        cyber: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg>',
        aurora: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.5 19H9a7 7 0 110-14h8.5"/><path d="M21 12h-3"/><path d="M21 16h-5"/><path d="M21 8h-5"/></svg>',
        holo: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
        classic: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="13.5" cy="6.5" r="2.5"/><path d="M17 3a2.83 2.83 0 114 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>',
        dark: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>',
        light: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>'
      };

      const themes = [
        { name: 'homepage', label: 'Homepage' },
        { name: 'cyber', label: 'Cyber Neon' },
        { name: 'aurora', label: 'Aurora Borealis' },
        { name: 'holo', label: 'Holographic' },
        { name: 'classic', label: 'Classic' },
        { name: 'dark', label: 'Dark' },
        { name: 'light', label: 'Light' }
      ];
      
      let currentTheme = 0;
      
      // Load saved theme or apply default (homepage)
      const savedTheme = localStorage.getItem('loginTheme');
      if (savedTheme) {
        const index = themes.findIndex(t => t.name === savedTheme);
        if (index !== -1) {
          currentTheme = index;
        }
      }
      // Apply theme class (homepage is default)
      if (themes[currentTheme].name !== 'cyber') {
        document.body.classList.add('theme-' + themes[currentTheme].name);
      }
      
      // Update indicator
      function updateIndicator() {
        const indicator = document.getElementById('themeIndicator');
        indicator.textContent = themes[currentTheme].label;
        indicator.classList.add('show');
        setTimeout(() => {
          indicator.classList.remove('show');
        }, 2000);
      }

      // Apply theme function
      function applyTheme(themeName) {
        // Remove all theme classes
        document.body.classList.remove('theme-homepage', 'theme-cyber', 'theme-aurora', 'theme-holo', 'theme-classic', 'theme-dark', 'theme-light');
        
        // Map dark to homepage
        let actualTheme = themeName;
        if (themeName === 'dark') {
          actualTheme = 'homepage';
        }
        
        // Find theme index
        const index = themes.findIndex(t => t.name === themeName);
        if (index !== -1) {
          currentTheme = index;
          
          // Apply new theme (cyber neon has no class - it's the base CSS)
          if (actualTheme !== 'cyber') {
            document.body.classList.add('theme-' + actualTheme);
          }
          
          // Save preference
          localStorage.setItem('loginTheme', themeName);
          
          // Update button icon with SVG
          document.getElementById('themeSwitchBtn').innerHTML = themeSvgIcons[themeName];
          
          // Update active state in menu
          document.querySelectorAll('.theme-menu-item').forEach(item => {
            item.classList.remove('active');
            if (item.dataset.theme === themeName) {
              item.classList.add('active');
            }
          });
          
          // Show indicator
          updateIndicator();
        }
      }

      // Menu item click handlers
      document.querySelectorAll('.theme-menu-item').forEach(item => {
        item.addEventListener('click', function() {
          const themeName = this.dataset.theme;
          applyTheme(themeName);
        });
      });
      
      // Theme switch button - toggle menu on mobile or cycle through on click
      document.getElementById('themeSwitchBtn').addEventListener('click', function(e) {
        // On mobile, just toggle menu visibility
        const menu = document.getElementById('themeMenu');
        if (window.innerWidth <= 768) {
          menu.classList.toggle('show');
        } else {
          // On desktop, cycle through themes
          currentTheme = (currentTheme + 1) % themes.length;
          applyTheme(themes[currentTheme].name);
        }
      });

      // Initialize - set active menu item and button icon with SVG
      document.getElementById('themeSwitchBtn').innerHTML = themeSvgIcons[themes[currentTheme].name];
      document.querySelectorAll('.theme-menu-item').forEach(item => {
        if (item.dataset.theme === themes[currentTheme].name) {
          item.classList.add('active');
        }
      });

      // ============================================
      // AJAX LOGIN HANDLER
      // ============================================
      const loginForm = document.getElementById('loginForm');
      const submitBtn = document.getElementById('submitBtn');
      const loginCard = document.getElementById('loginCard');
      const successOverlay = document.getElementById('successOverlay');
      
      // Get or create error message element
      let errorMessageEl = document.querySelector('.error-message');
      
      loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Get form data
        const formData = new FormData(loginForm);
        const username = formData.get('username').trim();
        const password = formData.get('password');
        
        // Validate
        if (!username || !password) {
          showError('กรุณากรอกข้อมูลให้ครบ');
          return;
        }
        
        // Set loading state
        submitBtn.classList.add('loading');
        submitBtn.disabled = true;
        
        // Hide existing error
        if (errorMessageEl) {
          errorMessageEl.style.display = 'none';
        }
        
        try {
          const response = await fetch('Login.php', {
            method: 'POST',
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
          });
          
          const result = await response.json();
          
          if (result.success) {
            // Show success overlay
            successOverlay.style.display = 'flex';
            loginCard.classList.add('success');
            
            // Redirect after animation
            setTimeout(() => {
              window.location.href = result.redirect;
            }, 1500);
          } else {
            // Show error
            showError(result.error || 'เกิดข้อผิดพลาด กรุณาลองใหม่');
            loginCard.classList.add('shake');
            setTimeout(() => loginCard.classList.remove('shake'), 500);
            
            // Reset button
            submitBtn.classList.remove('loading');
            submitBtn.disabled = false;
          }
        } catch (error) {
          console.error('Login error:', error);
          showError('เกิดข้อผิดพลาดในการเชื่อมต่อ');
          
          // Reset button
          submitBtn.classList.remove('loading');
          submitBtn.disabled = false;
        }
      });
      
      function showError(message) {
        if (!errorMessageEl) {
          // Create error element if doesn't exist
          errorMessageEl = document.createElement('div');
          errorMessageEl.className = 'error-message';
          errorMessageEl.innerHTML = `
            <svg class="animated-icon error-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/>
              <line x1="12" y1="8" x2="12" y2="12"/>
              <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <span class="error-text"></span>
          `;
          loginForm.parentNode.insertBefore(errorMessageEl, loginForm);
        }
        
        // Update message
        const errorText = errorMessageEl.querySelector('.error-text');
        if (errorText) {
          errorText.textContent = message;
        } else {
          errorMessageEl.innerHTML = `
            <svg class="animated-icon error-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/>
              <line x1="12" y1="8" x2="12" y2="12"/>
              <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            ${message}
          `;
        }
        
        errorMessageEl.style.display = 'flex';
        errorMessageEl.classList.add('show');
      }
    </script>
  </body>
</html>