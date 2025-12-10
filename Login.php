<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/ConnectDB.php';

$login_error = '';
$old_username = '';
 $login_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
}

?>
<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login | Dormitory Management</title>
    <link rel="stylesheet" href="Assets/Css/animate-ui.css" />
    <style>
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }

      body {
        min-height: 100vh;
        background: linear-gradient(135deg, #0a0a0f 0%, #1a1a2e 50%, #0f0f1a 100%);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
        font-size: 40px;
        box-shadow: 
          0 10px 30px rgba(59, 130, 246, 0.4),
          0 0 60px rgba(139, 92, 246, 0.3);
        animation: iconPulse 2s ease-in-out infinite;
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
        color: #64748b;
        transition: color 0.3s ease;
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

      .theme-switcher-btn {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        border: none;
        cursor: pointer;
        background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
        color: white;
        font-size: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 
          0 10px 30px rgba(59, 130, 246, 0.4),
          0 0 60px rgba(139, 92, 246, 0.2);
        transition: all 0.3s ease;
        animation: btnPulse 2s ease-in-out infinite;
      }

      .theme-switcher-btn:hover {
        transform: scale(1.1) rotate(15deg);
        box-shadow: 
          0 15px 40px rgba(59, 130, 246, 0.5),
          0 0 80px rgba(139, 92, 246, 0.3);
      }

      @keyframes btnPulse {
        0%, 100% { box-shadow: 0 10px 30px rgba(59, 130, 246, 0.4), 0 0 60px rgba(139, 92, 246, 0.2); }
        50% { box-shadow: 0 10px 40px rgba(59, 130, 246, 0.6), 0 0 80px rgba(139, 92, 246, 0.4); }
      }

      .theme-indicator {
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

      /* Theme transition */
      body, .login-card, .login-icon, .submit-btn, .orb, .particle, .glow-line, .bg-animation::before {
        transition: all 0.5s ease;
      }
    </style>
  </head>
  <body>
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
        <div class="login-icon">🏠</div>
        <h1 class="login-title neon-glow">เข้าสู่ระบบ</h1>
        <p class="login-subtitle">Dormitory Management System</p>
        
        <?php if ($login_error !== ''): ?>
          <div class="error-message">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="8" x2="12" y2="12"></line>
              <line x1="12" y1="16" x2="12.01" y2="16"></line>
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
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
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
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
              </svg>
            </div>
          </div>
          
          <button type="submit" class="submit-btn" id="submitBtn">
            เข้าสู่ระบบ
          </button>
        </form>
        
        <div class="login-footer">
          <p>© 2025 Dormitory Management System</p>
        </div>
      </div>
    </div>

    <!-- Theme Switcher Button -->
    <div class="theme-switcher">
      <div class="theme-indicator" id="themeIndicator">🌌 Cyber Neon</div>
      <button class="theme-switcher-btn" id="themeSwitchBtn" title="เปลี่ยนธีม">🌌</button>
    </div>

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

      // 3D Tilt Effect - Card follows mouse movement
      const card = document.getElementById('loginCard');
      const container = document.querySelector('.login-container');
      
      // Settings
      const maxTilt = 15; // Maximum tilt angle
      const glareOpacity = 0.3; // Glare effect opacity
      const perspective = 1000; // Perspective distance
      
      // Create glare element
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
        background: linear-gradient(
          135deg,
          rgba(255, 255, 255, 0) 0%,
          rgba(255, 255, 255, 0.1) 50%,
          rgba(255, 255, 255, 0) 100%
        );
        opacity: 0;
        transition: opacity 0.3s ease;
        z-index: 100;
      `;
      card.appendChild(glare);
      
      // Set card styles for 3D effect
      card.style.transformStyle = 'preserve-3d';
      container.style.perspective = perspective + 'px';
      
      // Mouse move handler
      card.addEventListener('mousemove', function(e) {
        const rect = card.getBoundingClientRect();
        const centerX = rect.left + rect.width / 2;
        const centerY = rect.top + rect.height / 2;
        
        // Calculate mouse position relative to card center (-1 to 1)
        const mouseX = (e.clientX - centerX) / (rect.width / 2);
        const mouseY = (e.clientY - centerY) / (rect.height / 2);
        
        // Calculate tilt angles
        const tiltX = -mouseY * maxTilt; // Tilt on X axis (up/down)
        const tiltY = mouseX * maxTilt;  // Tilt on Y axis (left/right)
        
        // Apply transform
        card.style.transform = `
          rotateX(${tiltX}deg) 
          rotateY(${tiltY}deg) 
          scale3d(1.02, 1.02, 1.02)
        `;
        
        // Update glare position
        const glareX = 50 + mouseX * 30;
        const glareY = 50 + mouseY * 30;
        glare.style.background = `
          radial-gradient(
            circle at ${glareX}% ${glareY}%,
            rgba(255, 255, 255, ${glareOpacity}) 0%,
            rgba(255, 255, 255, 0.1) 30%,
            transparent 60%
          )
        `;
        glare.style.opacity = '1';
      });
      
      // Mouse leave handler - reset card
      card.addEventListener('mouseleave', function() {
        card.style.transform = 'rotateX(0deg) rotateY(0deg) scale3d(1, 1, 1)';
        card.style.transition = 'transform 0.5s ease-out';
        glare.style.opacity = '0';
      });
      
      // Mouse enter handler
      card.addEventListener('mouseenter', function() {
        card.style.transition = 'transform 0.1s ease-out';
      });

      // Smooth shadow effect based on tilt
      card.addEventListener('mousemove', function(e) {
        const rect = card.getBoundingClientRect();
        const mouseX = (e.clientX - rect.left) / rect.width - 0.5;
        const mouseY = (e.clientY - rect.top) / rect.height - 0.5;
        
        // Dynamic shadow based on mouse position
        const shadowX = mouseX * 30;
        const shadowY = mouseY * 30;
        
        card.style.boxShadow = `
          ${shadowX}px ${shadowY}px 40px rgba(0, 0, 0, 0.3),
          ${shadowX * 0.5}px ${shadowY * 0.5}px 20px rgba(59, 130, 246, 0.2),
          0 0 60px rgba(139, 92, 246, 0.15),
          inset 0 1px 0 rgba(255, 255, 255, 0.1)
        `;
      });

      card.addEventListener('mouseleave', function() {
        card.style.boxShadow = `
          0 25px 50px -12px rgba(0, 0, 0, 0.5),
          0 0 0 1px rgba(255, 255, 255, 0.05),
          inset 0 1px 0 rgba(255, 255, 255, 0.1)
        `;
      });

      // ============================================
      // THEME SWITCHER
      // ============================================
      const themes = [
        { name: 'default', label: '🌌 Cyber Neon', icon: '🌌' },
        { name: 'aurora', label: '🌈 Aurora Borealis', icon: '🌈' },
        { name: 'holo', label: '✨ Holographic', icon: '✨' },
        { name: 'classic', label: '🎨 Classic', icon: '🎨' }
      ];
      
      let currentTheme = 0;
      
      // Load saved theme
      const savedTheme = localStorage.getItem('loginTheme');
      if (savedTheme) {
        const index = themes.findIndex(t => t.name === savedTheme);
        if (index !== -1) {
          currentTheme = index;
          if (themes[currentTheme].name !== 'default') {
            document.body.classList.add('theme-' + themes[currentTheme].name);
          }
        }
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
      
      // Theme switch handler
      document.getElementById('themeSwitchBtn').addEventListener('click', function() {
        // Remove current theme
        document.body.classList.remove('theme-aurora', 'theme-holo', 'theme-classic');
        
        // Next theme
        currentTheme = (currentTheme + 1) % themes.length;
        
        // Apply new theme
        if (themes[currentTheme].name !== 'default') {
          document.body.classList.add('theme-' + themes[currentTheme].name);
        }
        
        // Save preference
        localStorage.setItem('loginTheme', themes[currentTheme].name);
        
        // Update button icon
        this.textContent = themes[currentTheme].icon;
        
        // Show indicator
        updateIndicator();
        
        // Button animation
        this.style.transform = 'scale(1.2) rotate(360deg)';
        setTimeout(() => {
          this.style.transform = '';
        }, 300);
      });

      // Initialize button icon
      document.getElementById('themeSwitchBtn').textContent = themes[currentTheme].icon;
    </script>
  </body>
</html>