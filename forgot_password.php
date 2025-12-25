<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/ConnectDB.php';

$error = '';
$success = '';
$step = 1; // 1 = ใส่ username, 2 = ตั้งรหัสใหม่, 3 = สำเร็จ

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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'verify_user') {
        // Step 1: Verify username exists
        $username = trim($_POST['username'] ?? '');
        
        if (empty($username)) {
            $response = ['success' => false, 'error' => 'กรุณากรอกชื่อผู้ใช้'];
        } else {
            try {
                $pdo = connectDB();
                $stmt = $pdo->prepare('SELECT admin_id, admin_username, admin_name FROM admin WHERE admin_username = :username LIMIT 1');
                $stmt->execute([':username' => $username]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($admin) {
                    // Store in session for next step
                    $_SESSION['reset_admin_id'] = $admin['admin_id'];
                    $_SESSION['reset_admin_username'] = $admin['admin_username'];
                    $response = [
                        'success' => true, 
                        'admin_name' => $admin['admin_name'] ?: $admin['admin_username'],
                        'message' => 'พบบัญชีผู้ใช้'
                    ];
                } else {
                    $response = ['success' => false, 'error' => 'ไม่พบชื่อผู้ใช้นี้ในระบบ'];
                }
            } catch (PDOException $e) {
                $response = ['success' => false, 'error' => 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล'];
            }
        }
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
    }
    
    if ($action === 'reset_password') {
        // Step 2: Reset password
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($newPassword) || empty($confirmPassword)) {
            $response = ['success' => false, 'error' => 'กรุณากรอกรหัสผ่านให้ครบ'];
        } elseif (strlen($newPassword) < 4) {
            $response = ['success' => false, 'error' => 'รหัสผ่านต้องมีอย่างน้อย 4 ตัวอักษร'];
        } elseif ($newPassword !== $confirmPassword) {
            $response = ['success' => false, 'error' => 'รหัสผ่านไม่ตรงกัน'];
        } elseif (!isset($_SESSION['reset_admin_id'])) {
            $response = ['success' => false, 'error' => 'Session หมดอายุ กรุณาเริ่มใหม่'];
        } else {
            try {
                $pdo = connectDB();
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE admin SET admin_password = :password WHERE admin_id = :id');
                $stmt->execute([
                    ':password' => $hashedPassword,
                    ':id' => $_SESSION['reset_admin_id']
                ]);
                
                // Clear session
                unset($_SESSION['reset_admin_id']);
                unset($_SESSION['reset_admin_username']);
                
                $response = ['success' => true, 'message' => 'เปลี่ยนรหัสผ่านสำเร็จ'];
            } catch (PDOException $e) {
                $response = ['success' => false, 'error' => 'เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน'];
            }
        }
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
    }
}

?>
<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>ลืมรหัสผ่าน | <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }

      body {
        min-height: 100vh;
        background: linear-gradient(135deg, #0a0a0f 0%, #1a1a2e 50%, #0f0f1a 100%);
        font-family: 'Prompt', system-ui, sans-serif;
        overflow-x: hidden;
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
          radial-gradient(circle at 20% 80%, rgba(251, 146, 60, 0.15) 0%, transparent 50%),
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
        background: rgba(251, 146, 60, 0.6);
        border-radius: 50%;
        animation: float 20s infinite;
        box-shadow: 0 0 10px rgba(251, 146, 60, 0.8), 0 0 20px rgba(251, 146, 60, 0.4);
      }

      .particle:nth-child(1) { left: 10%; animation-delay: 0s; animation-duration: 25s; }
      .particle:nth-child(2) { left: 20%; animation-delay: 2s; animation-duration: 20s; }
      .particle:nth-child(3) { left: 30%; animation-delay: 4s; animation-duration: 28s; }
      .particle:nth-child(4) { left: 40%; animation-delay: 1s; animation-duration: 22s; }
      .particle:nth-child(5) { left: 50%; animation-delay: 3s; animation-duration: 26s; }
      .particle:nth-child(6) { left: 60%; animation-delay: 5s; animation-duration: 21s; }
      .particle:nth-child(7) { left: 70%; animation-delay: 2.5s; animation-duration: 24s; }
      .particle:nth-child(8) { left: 80%; animation-delay: 1.5s; animation-duration: 27s; }

      @keyframes float {
        0% { transform: translateY(100vh) scale(0); opacity: 0; }
        10% { opacity: 1; }
        90% { opacity: 1; }
        100% { transform: translateY(-100vh) scale(1); opacity: 0; }
      }

      /* Container */
      .container {
        position: relative;
        z-index: 10;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        padding: 20px;
      }

      /* Card */
      .card {
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

      /* Glowing Border */
      .card::before {
        content: '';
        position: absolute;
        top: -2px;
        left: -2px;
        right: -2px;
        bottom: -2px;
        background: linear-gradient(45deg, #f97316, #fb923c, #fbbf24, #f97316);
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

      /* Icon */
      .icon-box {
        width: 80px;
        height: 80px;
        margin: 0 auto 25px;
        background: linear-gradient(135deg, #f97316 0%, #fb923c 100%);
        border-radius: 20px;
        display: flex;
        justify-content: center;
        align-items: center;
        box-shadow: 
          0 10px 30px rgba(249, 115, 22, 0.4),
          0 0 60px rgba(251, 146, 60, 0.3);
        animation: iconPulse 2s ease-in-out infinite;
      }

      .icon-box svg {
        width: 40px;
        height: 40px;
        stroke: #fff;
        stroke-width: 2;
        fill: none;
      }

      @keyframes iconPulse {
        0%, 100% { 
          box-shadow: 
            0 10px 30px rgba(249, 115, 22, 0.4),
            0 0 60px rgba(251, 146, 60, 0.3);
        }
        50% { 
          box-shadow: 
            0 10px 40px rgba(249, 115, 22, 0.6),
            0 0 80px rgba(251, 146, 60, 0.5);
        }
      }

      /* Title */
      .title {
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

      .subtitle {
        text-align: center;
        font-size: 14px;
        color: #64748b;
        margin-bottom: 35px;
      }

      /* Steps indicator */
      .steps {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-bottom: 30px;
      }

      .step-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        transition: all 0.3s ease;
      }

      .step-dot.active {
        background: linear-gradient(135deg, #f97316, #fb923c);
        box-shadow: 0 0 15px rgba(249, 115, 22, 0.5);
        transform: scale(1.2);
      }

      .step-dot.completed {
        background: #22c55e;
        box-shadow: 0 0 15px rgba(34, 197, 94, 0.5);
      }

      /* Form */
      .form-group {
        margin-bottom: 24px;
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
        border-color: #f97316;
        background: rgba(30, 41, 59, 0.8);
        box-shadow: 
          0 0 0 4px rgba(249, 115, 22, 0.15),
          0 0 30px rgba(249, 115, 22, 0.2);
      }

      .form-input:focus + svg,
      .input-wrapper:focus-within svg {
        color: #f97316;
      }

      /* Messages */
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

      .success-message {
        background: rgba(34, 197, 94, 0.15);
        border: 1px solid rgba(34, 197, 94, 0.3);
        border-radius: 10px;
        padding: 12px 16px;
        margin-bottom: 20px;
        color: #86efac;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideDown 0.3s ease;
      }

      .info-message {
        background: rgba(59, 130, 246, 0.15);
        border: 1px solid rgba(59, 130, 246, 0.3);
        border-radius: 10px;
        padding: 12px 16px;
        margin-bottom: 20px;
        color: #93c5fd;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 10px;
      }

      @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
      }

      @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
      }

      /* Buttons */
      .btn {
        width: 100%;
        padding: 16px;
        border: none;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 2px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
      }

      .btn-primary {
        background: linear-gradient(135deg, #f97316 0%, #fb923c 100%);
        color: #fff;
      }

      .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 
          0 10px 30px rgba(249, 115, 22, 0.4),
          0 0 60px rgba(251, 146, 60, 0.3);
      }

      .btn-success {
        background: linear-gradient(135deg, #22c55e 0%, #10b981 100%);
        color: #fff;
      }

      .btn-success:hover {
        transform: translateY(-2px);
        box-shadow: 
          0 10px 30px rgba(34, 197, 94, 0.4),
          0 0 60px rgba(16, 185, 129, 0.3);
      }

      .btn.loading {
        pointer-events: none;
        opacity: 0.8;
      }

      .btn .spinner {
        animation: spin 1s linear infinite;
      }

      @keyframes spin {
        to { transform: rotate(360deg); }
      }

      .btn .btn-text { display: inline-flex; align-items: center; gap: 8px; }
      .btn .btn-loading { display: none; align-items: center; gap: 8px; }
      .btn.loading .btn-text { display: none; }
      .btn.loading .btn-loading { display: inline-flex; }

      /* Footer Links */
      .footer {
        text-align: center;
        margin-top: 30px;
      }

      .footer a {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        background: rgba(59, 130, 246, 0.1);
        border: 1px solid rgba(59, 130, 246, 0.3);
        border-radius: 10px;
        color: #60a5fa;
        font-size: 0.9rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s ease;
      }

      .footer a:hover {
        background: rgba(59, 130, 246, 0.2);
        border-color: rgba(59, 130, 246, 0.5);
        transform: translateY(-2px);
      }

      .footer a svg {
        width: 18px;
        height: 18px;
      }

      /* Step sections */
      .step-section {
        display: none;
        animation: fadeIn 0.3s ease;
      }

      .step-section.active {
        display: block;
      }

      @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
      }

      /* User info card */
      .user-info {
        background: rgba(34, 197, 94, 0.1);
        border: 1px solid rgba(34, 197, 94, 0.3);
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 25px;
        text-align: center;
      }

      .user-info .user-name {
        color: #86efac;
        font-size: 18px;
        font-weight: 600;
      }

      .user-info .user-label {
        color: #64748b;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 1px;
      }

      /* Password strength */
      .password-strength {
        margin-top: 8px;
        height: 4px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 2px;
        overflow: hidden;
      }

      .password-strength-bar {
        height: 100%;
        width: 0;
        transition: all 0.3s ease;
        border-radius: 2px;
      }

      .password-strength-bar.weak { width: 33%; background: #ef4444; }
      .password-strength-bar.medium { width: 66%; background: #f59e0b; }
      .password-strength-bar.strong { width: 100%; background: #22c55e; }

      /* Success overlay */
      .success-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(15, 23, 42, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 24px;
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 200;
        animation: fadeIn 0.3s ease;
      }

      .success-overlay.show {
        display: flex;
      }

      .success-overlay .content {
        text-align: center;
      }

      .success-overlay .check-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto 20px;
        background: linear-gradient(135deg, #22c55e, #10b981);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: successBounce 0.5s ease;
      }

      .success-overlay .check-icon svg {
        width: 40px;
        height: 40px;
        stroke: #fff;
        stroke-width: 3;
      }

      @keyframes successBounce {
        0% { transform: scale(0); }
        50% { transform: scale(1.2); }
        100% { transform: scale(1); }
      }

      .success-overlay h3 {
        color: #fff;
        font-size: 24px;
        margin-bottom: 10px;
      }

      .success-overlay p {
        color: #94a3b8;
        margin-bottom: 20px;
      }

      /* Responsive */
      @media (max-width: 480px) {
        .card {
          padding: 40px 25px;
          margin: 10px;
        }
        
        .title {
          font-size: 24px;
        }
      }

      /* ============================================
         THEME SWITCHER
         ============================================ */
      .theme-switcher {
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 15px;
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
        border-color: #f97316;
        color: #f97316;
        transform: translateX(-5px);
      }

      .theme-menu-item.active {
        background: #fff7ed;
        border-color: #f97316;
        color: #f97316;
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
        background: linear-gradient(135deg, #f97316 0%, #fb923c 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3);
        transition: all 0.3s ease;
        animation: none;
      }

      .theme-switcher-btn svg {
        width: 28px;
        height: 28px;
        stroke: #ffffff;
        transition: all 0.3s ease;
      }

      .theme-switcher:hover .theme-switcher-btn svg {
        transform: scale(1.1) rotate(180deg);
        filter: none;
        stroke: #f97316;
      }

      .theme-switcher:hover .theme-switcher-btn {
        animation: none;
        transform: scale(1.1);
        background: #ffffff;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
      }

      @keyframes btnPulse {
        0%, 100% { box-shadow: 0 0 20px rgba(249, 115, 22, 0.2); }
        50% { box-shadow: 0 0 30px rgba(249, 115, 22, 0.3); }
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
        z-index: 1001;
      }

      .theme-indicator.show {
        opacity: 1;
        transform: translateX(0);
      }

      /* ============================================
         THEME: CYBER NEON (Blue)
         ============================================ */
      body.theme-cyber {
        background: linear-gradient(135deg, #0a0a0f 0%, #1a1a2e 50%, #0f0f1a 100%);
      }

      body.theme-cyber .bg-animation::before {
        background: 
          radial-gradient(circle at 20% 80%, rgba(59, 130, 246, 0.15) 0%, transparent 50%),
          radial-gradient(circle at 80% 20%, rgba(139, 92, 246, 0.15) 0%, transparent 50%),
          radial-gradient(circle at 40% 40%, rgba(96, 165, 250, 0.1) 0%, transparent 40%);
      }

      body.theme-cyber .particle {
        background: rgba(59, 130, 246, 0.6);
        box-shadow: 0 0 10px rgba(59, 130, 246, 0.8), 0 0 20px rgba(139, 92, 246, 0.4);
      }

      body.theme-cyber .card::before {
        background: linear-gradient(45deg, #3b82f6, #8b5cf6, #06b6d4, #3b82f6);
        background-size: 400% 400%;
      }

      body.theme-cyber .icon-box {
        background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
        box-shadow: 
          0 10px 30px rgba(59, 130, 246, 0.4),
          0 0 60px rgba(139, 92, 246, 0.3);
      }

      body.theme-cyber .form-input:focus {
        border-color: #3b82f6;
        box-shadow: 
          0 0 0 4px rgba(59, 130, 246, 0.15),
          0 0 30px rgba(59, 130, 246, 0.2);
      }

      body.theme-cyber .input-wrapper:focus-within svg {
        color: #3b82f6;
      }

      body.theme-cyber .btn-primary {
        background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
      }

      body.theme-cyber .btn-primary:hover {
        box-shadow: 
          0 10px 30px rgba(59, 130, 246, 0.4),
          0 0 60px rgba(139, 92, 246, 0.3);
      }

      body.theme-cyber .step-dot.active {
        background: linear-gradient(135deg, #3b82f6, #8b5cf6);
        box-shadow: 0 0 15px rgba(59, 130, 246, 0.5);
      }

      body.theme-cyber .theme-switcher-btn {
        background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
        box-shadow: 0 10px 30px rgba(59, 130, 246, 0.4), 0 0 60px rgba(139, 92, 246, 0.2);
      }

      body.theme-cyber .theme-menu-item:hover {
        background: rgba(59, 130, 246, 0.2);
        border-color: rgba(59, 130, 246, 0.5);
      }

      body.theme-cyber .theme-menu-item.active {
        background: rgba(59, 130, 246, 0.3);
        border-color: #3b82f6;
      }

      /* ============================================
         THEME: AURORA (Green/Cyan)
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

      body.theme-aurora .particle {
        background: rgba(34, 197, 94, 0.6);
        box-shadow: 0 0 10px rgba(34, 197, 94, 0.8), 0 0 20px rgba(6, 182, 212, 0.4);
      }

      body.theme-aurora .card {
        background: rgba(12, 24, 33, 0.8);
        border: 1px solid rgba(34, 197, 94, 0.2);
      }

      body.theme-aurora .card::before {
        background: linear-gradient(45deg, #22c55e, #06b6d4, #a855f7, #22c55e);
        background-size: 400% 400%;
      }

      body.theme-aurora .icon-box {
        background: linear-gradient(135deg, #22c55e 0%, #06b6d4 100%);
        box-shadow: 
          0 10px 30px rgba(34, 197, 94, 0.4),
          0 0 60px rgba(6, 182, 212, 0.3);
      }

      body.theme-aurora .form-input:focus {
        border-color: #22c55e;
        box-shadow: 
          0 0 0 4px rgba(34, 197, 94, 0.15),
          0 0 30px rgba(6, 182, 212, 0.2);
      }

      body.theme-aurora .input-wrapper:focus-within svg {
        color: #22c55e;
      }

      body.theme-aurora .btn-primary {
        background: linear-gradient(135deg, #22c55e 0%, #06b6d4 100%);
      }

      body.theme-aurora .btn-primary:hover {
        box-shadow: 
          0 10px 30px rgba(34, 197, 94, 0.4),
          0 0 60px rgba(6, 182, 212, 0.3);
      }

      body.theme-aurora .step-dot.active {
        background: linear-gradient(135deg, #22c55e, #06b6d4);
        box-shadow: 0 0 15px rgba(34, 197, 94, 0.5);
      }

      body.theme-aurora .theme-switcher-btn {
        background: linear-gradient(135deg, #22c55e 0%, #06b6d4 100%);
        box-shadow: 0 10px 30px rgba(34, 197, 94, 0.4), 0 0 60px rgba(6, 182, 212, 0.2);
      }

      body.theme-aurora .theme-menu-item:hover {
        background: rgba(34, 197, 94, 0.2);
        border-color: rgba(34, 197, 94, 0.5);
      }

      body.theme-aurora .theme-menu-item.active {
        background: rgba(34, 197, 94, 0.3);
        border-color: #22c55e;
      }

      /* ============================================
         THEME: PURPLE
         ============================================ */
      body.theme-purple {
        background: linear-gradient(135deg, #1a0a2e 0%, #2d1b4e 50%, #1a0a2e 100%);
      }

      body.theme-purple .bg-animation::before {
        background: 
          radial-gradient(circle at 20% 80%, rgba(168, 85, 247, 0.2) 0%, transparent 50%),
          radial-gradient(circle at 80% 20%, rgba(236, 72, 153, 0.15) 0%, transparent 50%),
          radial-gradient(circle at 40% 40%, rgba(139, 92, 246, 0.1) 0%, transparent 40%);
      }

      body.theme-purple .particle {
        background: rgba(168, 85, 247, 0.6);
        box-shadow: 0 0 10px rgba(168, 85, 247, 0.8), 0 0 20px rgba(236, 72, 153, 0.4);
      }

      body.theme-purple .card {
        background: rgba(26, 10, 46, 0.8);
        border: 1px solid rgba(168, 85, 247, 0.2);
      }

      body.theme-purple .card::before {
        background: linear-gradient(45deg, #a855f7, #ec4899, #8b5cf6, #a855f7);
        background-size: 400% 400%;
      }

      body.theme-purple .icon-box {
        background: linear-gradient(135deg, #a855f7 0%, #ec4899 100%);
        box-shadow: 
          0 10px 30px rgba(168, 85, 247, 0.4),
          0 0 60px rgba(236, 72, 153, 0.3);
      }

      body.theme-purple .form-input:focus {
        border-color: #a855f7;
        box-shadow: 
          0 0 0 4px rgba(168, 85, 247, 0.15),
          0 0 30px rgba(236, 72, 153, 0.2);
      }

      body.theme-purple .input-wrapper:focus-within svg {
        color: #a855f7;
      }

      body.theme-purple .btn-primary {
        background: linear-gradient(135deg, #a855f7 0%, #ec4899 100%);
      }

      body.theme-purple .btn-primary:hover {
        box-shadow: 
          0 10px 30px rgba(168, 85, 247, 0.4),
          0 0 60px rgba(236, 72, 153, 0.3);
      }

      body.theme-purple .step-dot.active {
        background: linear-gradient(135deg, #a855f7, #ec4899);
        box-shadow: 0 0 15px rgba(168, 85, 247, 0.5);
      }

      body.theme-purple .theme-switcher-btn {
        background: linear-gradient(135deg, #a855f7 0%, #ec4899 100%);
        box-shadow: 0 10px 30px rgba(168, 85, 247, 0.4), 0 0 60px rgba(236, 72, 153, 0.2);
      }

      body.theme-purple .theme-menu-item:hover {
        background: rgba(168, 85, 247, 0.2);
        border-color: rgba(168, 85, 247, 0.5);
      }

      body.theme-purple .theme-menu-item.active {
        background: rgba(168, 85, 247, 0.3);
        border-color: #a855f7;
      }

      /* ============================================
         THEME: DARK (Minimal)
         ============================================ */
      body.theme-dark {
        background: linear-gradient(135deg, #111111 0%, #1a1a1a 50%, #0d0d0d 100%);
      }

      body.theme-dark .bg-animation::before {
        background: 
          radial-gradient(circle at 20% 80%, rgba(75, 85, 99, 0.1) 0%, transparent 50%),
          radial-gradient(circle at 80% 20%, rgba(107, 114, 128, 0.1) 0%, transparent 50%);
      }

      body.theme-dark .particle {
        background: rgba(156, 163, 175, 0.4);
        box-shadow: 0 0 10px rgba(156, 163, 175, 0.6), 0 0 20px rgba(107, 114, 128, 0.3);
      }

      body.theme-dark .card {
        background: rgba(17, 17, 17, 0.9);
        border: 1px solid rgba(75, 85, 99, 0.3);
      }

      body.theme-dark .card::before {
        background: linear-gradient(45deg, #4b5563, #6b7280, #9ca3af, #4b5563);
        background-size: 400% 400%;
        opacity: 0.5;
      }

      body.theme-dark .icon-box {
        background: linear-gradient(135deg, #4b5563 0%, #6b7280 100%);
        box-shadow: 
          0 10px 30px rgba(75, 85, 99, 0.3),
          0 0 40px rgba(107, 114, 128, 0.2);
      }

      body.theme-dark .form-input:focus {
        border-color: #6b7280;
        box-shadow: 
          0 0 0 4px rgba(107, 114, 128, 0.1),
          0 0 20px rgba(75, 85, 99, 0.2);
      }

      body.theme-dark .input-wrapper:focus-within svg {
        color: #9ca3af;
      }

      body.theme-dark .btn-primary {
        background: linear-gradient(135deg, #4b5563 0%, #6b7280 100%);
      }

      body.theme-dark .btn-primary:hover {
        box-shadow: 
          0 10px 30px rgba(75, 85, 99, 0.3),
          0 0 40px rgba(107, 114, 128, 0.2);
      }

      body.theme-dark .step-dot.active {
        background: linear-gradient(135deg, #4b5563, #6b7280);
        box-shadow: 0 0 15px rgba(107, 114, 128, 0.4);
      }

      body.theme-dark .theme-switcher-btn {
        background: linear-gradient(135deg, #4b5563 0%, #6b7280 100%);
        box-shadow: 0 10px 30px rgba(75, 85, 99, 0.3), 0 0 40px rgba(107, 114, 128, 0.1);
        animation: none;
      }

      body.theme-dark .theme-menu-item:hover {
        background: rgba(107, 114, 128, 0.2);
        border-color: rgba(107, 114, 128, 0.5);
      }

      body.theme-dark .theme-menu-item.active {
        background: rgba(107, 114, 128, 0.3);
        border-color: #6b7280;
      }

      /* Theme: Light */
      body.theme-light {
        background: linear-gradient(135deg, #f8fafc, #e2e8f0, #cbd5e1);
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
      body.theme-light .btn-primary,
      body.theme-light .btn-primary span,
      body.theme-light .submit-btn,
      body.theme-light .submit-btn span {
        color: #ffffff !important;
        -webkit-text-fill-color: #ffffff !important;
      }
      body.theme-light .bg-animation::before {
        background: radial-gradient(ellipse at 20% 50%, rgba(249, 115, 22, 0.08), transparent 50%),
                    radial-gradient(ellipse at 80% 50%, rgba(251, 146, 60, 0.08), transparent 50%);
      }
      body.theme-light .particle {
        background: rgba(249, 115, 22, 0.8);
        box-shadow: 0 0 10px rgba(249, 115, 22, 0.8);
      }
      body.theme-light .card {
        background: rgba(255, 255, 255, 0.9);
        border-color: rgba(148, 163, 184, 0.3);
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
      }
      body.theme-light .card::before {
        background: linear-gradient(135deg, rgba(249, 115, 22, 0.1), transparent 50%);
      }
      body.theme-light .card-header h1,
      body.theme-light .card-header p {
        color: #334155;
      }
      body.theme-light .icon-box {
        background: linear-gradient(135deg, #f97316 0%, #fb923c 100%);
        border-color: rgba(249, 115, 22, 0.3);
        box-shadow: 0 10px 30px rgba(249, 115, 22, 0.3);
      }
      body.theme-light .icon-box svg {
        stroke: #ffffff;
      }
      body.theme-light .form-label {
        color: #1f2937;
      }
      body.theme-light .form-input {
        background: #ffffff;
        border-color: #d1d5db;
        color: #1f2937 !important;
      }
      body.theme-light .form-input::placeholder {
        color: #6b7280 !important;
        opacity: 1;
      }
      body.theme-light .form-input:focus {
        border-color: #f97316;
        box-shadow: 0 0 20px rgba(249, 115, 22, 0.2);
      }
      body.theme-light .input-wrapper svg {
        stroke: #1f2937;
        color: #1f2937;
      }
      body.theme-light .input-wrapper:focus-within svg {
        stroke: #f97316;
        color: #f97316;
      }
      body.theme-light .btn-primary {
        background: linear-gradient(135deg, #f97316, #ea580c);
        color: #ffffff !important;
        -webkit-text-fill-color: #ffffff !important;
      }
      body.theme-light .btn-primary:hover {
        background: linear-gradient(135deg, #fb923c, #f97316);
        box-shadow: 0 10px 30px rgba(249, 115, 22, 0.4);
      }
      body.theme-light .step-dot {
        background: rgba(148, 163, 184, 0.5);
      }
      body.theme-light .step-dot.active {
        background: #f97316;
        box-shadow: 0 0 15px rgba(249, 115, 22, 0.5);
      }
      body.theme-light .step-dot.completed {
        background: #22c55e;
        box-shadow: 0 0 15px rgba(34, 197, 94, 0.5);
      }
      body.theme-light .back-link {
        color: #64748b;
      }
      body.theme-light .back-link:hover {
        color: #f97316;
      }
      body.theme-light .theme-switcher-btn {
        background: linear-gradient(135deg, #f97316, #ea580c);
      }
      body.theme-light .theme-menu-item:hover {
        background: rgba(249, 115, 22, 0.1);
        border-color: rgba(249, 115, 22, 0.3);
      }
      body.theme-light .theme-menu-item.active {
        background: rgba(249, 115, 22, 0.2);
        border-color: #f97316;
      }
      body.theme-light .theme-menu {
        background: transparent;
        border: none;
      }
      body.theme-light .theme-menu-item {
        color: #334155;
      }
      body.theme-light .theme-indicator {
        background: rgba(255, 255, 255, 0.9);
        color: #334155;
      }

      /* Theme transitions */
      body, .card, .icon-box, .btn, .particle, .bg-animation::before {
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
    <!-- Background Animation -->
    <div class="bg-animation"></div>
    
    <!-- Particles -->
    <div class="particles">
      <div class="particle"></div>
      <div class="particle"></div>
      <div class="particle"></div>
      <div class="particle"></div>
      <div class="particle"></div>
      <div class="particle"></div>
      <div class="particle"></div>
      <div class="particle"></div>
    </div>

    <div class="container">
      <div class="card" id="mainCard">
        <!-- Icon -->
        <div class="icon-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <circle cx="12" cy="12" r="3"/>
            <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
          </svg>
        </div>
        
        <h1 class="title">ลืมรหัสผ่าน</h1>
        <p class="subtitle"><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></p>
        
        <!-- Steps Indicator -->
        <div class="steps">
          <div class="step-dot active" id="step1Dot"></div>
          <div class="step-dot" id="step2Dot"></div>
          <div class="step-dot" id="step3Dot"></div>
        </div>
        
        <!-- Error/Success Messages -->
        <div id="messageContainer"></div>
        
        <!-- Step 1: Enter Username -->
        <div class="step-section active" id="step1">
          <form id="verifyForm">
            <input type="hidden" name="action" value="verify_user">
            <div class="form-group">
              <label>ชื่อผู้ใช้ (Username)</label>
              <div class="input-wrapper">
                <input type="text" name="username" class="form-input" placeholder="กรอกชื่อผู้ใช้ของคุณ" required autofocus>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                  <circle cx="12" cy="7" r="4"/>
                </svg>
              </div>
            </div>
            <button type="submit" class="btn btn-primary" id="verifyBtn">
              <span class="btn-text">ตรวจสอบบัญชี</span>
              <span class="btn-loading">
                <svg class="spinner" viewBox="0 0 24 24" width="20" height="20">
                  <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round" stroke-dasharray="30 70"/>
                </svg>
                กำลังตรวจสอบ...
              </span>
            </button>
          </form>
        </div>
        
        <!-- Step 2: Set New Password -->
        <div class="step-section" id="step2">
          <div class="user-info" id="userInfo">
            <div class="user-label">กำลังรีเซ็ตรหัสผ่านสำหรับ</div>
            <div class="user-name" id="userName"></div>
          </div>
          
          <form id="resetForm">
            <input type="hidden" name="action" value="reset_password">
            <div class="form-group">
              <label>รหัสผ่านใหม่</label>
              <div class="input-wrapper">
                <input type="password" name="new_password" id="newPassword" class="form-input" placeholder="กรอกรหัสผ่านใหม่" required minlength="4">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                  <path d="M7 11V7a5 5 0 0110 0v4"/>
                </svg>
              </div>
              <div class="password-strength">
                <div class="password-strength-bar" id="strengthBar"></div>
              </div>
            </div>
            
            <div class="form-group">
              <label>ยืนยันรหัสผ่านใหม่</label>
              <div class="input-wrapper">
                <input type="password" name="confirm_password" id="confirmPassword" class="form-input" placeholder="กรอกรหัสผ่านอีกครั้ง" required minlength="4">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                  <path d="M7 11V7a5 5 0 0110 0v4"/>
                </svg>
              </div>
            </div>
            
            <button type="submit" class="btn btn-success" id="resetBtn">
              <span class="btn-text">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M20 6L9 17l-5-5"/>
                </svg>
                เปลี่ยนรหัสผ่าน
              </span>
              <span class="btn-loading">
                <svg class="spinner" viewBox="0 0 24 24" width="20" height="20">
                  <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round" stroke-dasharray="30 70"/>
                </svg>
                กำลังบันทึก...
              </span>
            </button>
          </form>
        </div>
        
        <!-- Success Overlay -->
        <div class="success-overlay" id="successOverlay">
          <div class="content">
            <div class="check-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M20 6L9 17l-5-5"/>
              </svg>
            </div>
            <h3>เปลี่ยนรหัสผ่านสำเร็จ!</h3>
            <p>กำลังนำคุณไปหน้าเข้าสู่ระบบ...</p>
          </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
          <a href="Login.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            กลับหน้าเข้าสู่ระบบ
          </a>
        </div>
      </div>
    </div>

    <!-- Theme Switcher Button -->
    <div class="theme-switcher" id="themeSwitcher">
      <div class="theme-menu" id="themeMenu">
        <div class="theme-menu-item" data-theme="default">
          <span class="theme-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="3"/>
              <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
            </svg>
          </span>
          <span>Default Orange</span>
        </div>
        <div class="theme-menu-item" data-theme="cyber">
          <span class="theme-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/>
              <path d="M2 12h20M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/>
            </svg>
          </span>
          <span>Cyber Neon</span>
        </div>
        <div class="theme-menu-item" data-theme="aurora">
          <span class="theme-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17.5 19H9a7 7 0 110-14h8.5"/>
              <path d="M21 12h-3M21 16h-5M21 8h-5"/>
            </svg>
          </span>
          <span>Aurora</span>
        </div>
        <div class="theme-menu-item" data-theme="purple">
          <span class="theme-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
            </svg>
          </span>
          <span>Purple</span>
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
              <circle cx="12" cy="12" r="5"/>
              <line x1="12" y1="1" x2="12" y2="3"/>
              <line x1="12" y1="21" x2="12" y2="23"/>
              <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
              <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
              <line x1="1" y1="12" x2="3" y2="12"/>
              <line x1="21" y1="12" x2="23" y2="12"/>
              <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
              <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
            </svg>
          </span>
          <span>Light</span>
        </div>
      </div>
      <button class="theme-switcher-btn" id="themeSwitchBtn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="3"/>
          <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
        </svg>
      </button>
    </div>
    <div class="theme-indicator" id="themeIndicator"></div>

    <script>
      const verifyForm = document.getElementById('verifyForm');
      const resetForm = document.getElementById('resetForm');
      const verifyBtn = document.getElementById('verifyBtn');
      const resetBtn = document.getElementById('resetBtn');
      const messageContainer = document.getElementById('messageContainer');
      const newPasswordInput = document.getElementById('newPassword');
      const confirmPasswordInput = document.getElementById('confirmPassword');
      const strengthBar = document.getElementById('strengthBar');
      
      // Step 1: Verify Username
      verifyForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        verifyBtn.classList.add('loading');
        messageContainer.innerHTML = '';
        
        const formData = new FormData(verifyForm);
        
        try {
          const response = await fetch('forgot_password.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
          });
          
          const result = await response.json();
          
          if (result.success) {
            // Move to step 2
            document.getElementById('step1').classList.remove('active');
            document.getElementById('step2').classList.add('active');
            document.getElementById('step1Dot').classList.remove('active');
            document.getElementById('step1Dot').classList.add('completed');
            document.getElementById('step2Dot').classList.add('active');
            document.getElementById('userName').textContent = result.admin_name;
            
            showMessage('success', result.message);
          } else {
            showMessage('error', result.error);
            shakeCard();
          }
        } catch (error) {
          showMessage('error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ');
        }
        
        verifyBtn.classList.remove('loading');
      });
      
      // Step 2: Reset Password
      resetForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const newPass = newPasswordInput.value;
        const confirmPass = confirmPasswordInput.value;
        
        if (newPass !== confirmPass) {
          showMessage('error', 'รหัสผ่านไม่ตรงกัน');
          shakeCard();
          return;
        }
        
        if (newPass.length < 4) {
          showMessage('error', 'รหัสผ่านต้องมีอย่างน้อย 4 ตัวอักษร');
          shakeCard();
          return;
        }
        
        resetBtn.classList.add('loading');
        messageContainer.innerHTML = '';
        
        const formData = new FormData(resetForm);
        
        try {
          const response = await fetch('forgot_password.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
          });
          
          const result = await response.json();
          
          if (result.success) {
            // Show success overlay
            document.getElementById('step2Dot').classList.remove('active');
            document.getElementById('step2Dot').classList.add('completed');
            document.getElementById('step3Dot').classList.add('active');
            document.getElementById('successOverlay').classList.add('show');
            
            // Redirect after 2 seconds
            setTimeout(() => {
              window.location.href = 'Login.php';
            }, 2000);
          } else {
            showMessage('error', result.error);
            shakeCard();
          }
        } catch (error) {
          showMessage('error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ');
        }
        
        resetBtn.classList.remove('loading');
      });
      
      // Password strength checker
      newPasswordInput.addEventListener('input', function() {
        const password = this.value;
        let strength = '';
        
        if (password.length >= 8 && /[A-Z]/.test(password) && /[0-9]/.test(password)) {
          strength = 'strong';
        } else if (password.length >= 6) {
          strength = 'medium';
        } else if (password.length >= 1) {
          strength = 'weak';
        }
        
        strengthBar.className = 'password-strength-bar ' + strength;
      });
      
      function showMessage(type, message) {
        const iconSvg = type === 'error' 
          ? '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'
          : '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>';
        
        messageContainer.innerHTML = `
          <div class="${type}-message">
            ${iconSvg}
            ${message}
          </div>
        `;
      }
      
      function shakeCard() {
        const card = document.getElementById('mainCard');
        card.style.animation = 'shake 0.5s ease';
        setTimeout(() => card.style.animation = '', 500);
      }

      // ============================================
      // ✨ ELEGANT 3D PARALLAX SYSTEM
      // ============================================
      const card = document.getElementById('mainCard');
      const container = document.querySelector('.container');
      
      // Elegant Settings
      const maxTilt = 10;
      const glareOpacity = 0.2;
      const perspective = 1200;
      const accentColor = { r: 249, g: 115, b: 22 }; // Orange - #f97316
      
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
        background: linear-gradient(135deg, rgba(249, 115, 22, 0.5), rgba(249, 115, 22, 0.1), rgba(249, 115, 22, 0.5));
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
          
          // Dynamic shadow
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
        default: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>',
        cyber: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg>',
        aurora: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.5 19H9a7 7 0 110-14h8.5"/><path d="M21 12h-3"/><path d="M21 16h-5"/><path d="M21 8h-5"/></svg>',
        purple: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
        dark: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>',
        light: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>'
      };

      const themes = [
        { name: 'default', label: 'Default Orange' },
        { name: 'cyber', label: 'Cyber Neon' },
        { name: 'aurora', label: 'Aurora' },
        { name: 'purple', label: 'Purple' },
        { name: 'dark', label: 'Dark' },
        { name: 'light', label: 'Light' }
      ];
      
      let currentTheme = 0;
      
      // Load saved theme
      const savedTheme = localStorage.getItem('forgotPasswordTheme');
      if (savedTheme) {
        const index = themes.findIndex(t => t.name === savedTheme);
        if (index !== -1) {
          currentTheme = index;
        }
      }
      
      // Apply initial theme
      if (themes[currentTheme].name !== 'default') {
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
        document.body.classList.remove('theme-default', 'theme-cyber', 'theme-aurora', 'theme-purple', 'theme-dark', 'theme-light');
        
        // Map dark to default (orange theme)
        let actualTheme = themeName;
        if (themeName === 'dark') {
          actualTheme = 'default';
        }
        
        // Find theme index
        const index = themes.findIndex(t => t.name === themeName);
        if (index !== -1) {
          currentTheme = index;
          
          // Apply new theme (default has no class)
          if (actualTheme !== 'default') {
            document.body.classList.add('theme-' + actualTheme);
          }
          
          // Save preference
          localStorage.setItem('forgotPasswordTheme', themeName);
          
          // Update button icon
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
      
      // Theme switch button
      document.getElementById('themeSwitchBtn').addEventListener('click', function(e) {
        const menu = document.getElementById('themeMenu');
        if (window.innerWidth <= 768) {
          menu.classList.toggle('show');
        } else {
          currentTheme = (currentTheme + 1) % themes.length;
          applyTheme(themes[currentTheme].name);
        }
      });

      // Initialize
      document.getElementById('themeSwitchBtn').innerHTML = themeSvgIcons[themes[currentTheme].name];
      document.querySelectorAll('.theme-menu-item').forEach(item => {
        if (item.dataset.theme === themes[currentTheme].name) {
          item.classList.add('active');
        }
      });
    </script>
  </body>
</html>
