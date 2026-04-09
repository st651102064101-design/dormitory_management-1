<?php
/**
 * LINE Login Initiator
 * เริ่มต้นกระบวนการ OAuth กับ LINE Login
 */
declare(strict_types=1);
session_start();
require_once __DIR__ . '/ConnectDB.php';

function buildLineRedirectUri(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $protocol = 'https';
    }
    $httpHost = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');

    // Path base for the redirect URI
    $basePath = dirname($_SERVER['PHP_SELF']);
    if ($basePath === '/' || $basePath === '\\') {
        $basePath = '';
    }

    return $protocol . '://' . $httpHost . $basePath . '/line_callback.php';
}

try {
    $pdo = connectDB();
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('line_login_channel_id', 'line_login_channel_secret')");
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    $clientId = $settings['line_login_channel_id'] ?? '';
    
    if (empty($clientId)) {
        header('Location: Login.php?error=' . urlencode('ระบบ LINE Login ยังไม่ได้ตั้งค่า Channel ID'));
        exit;
    }
    
    $redirectUri = buildLineRedirectUri();
    
    // บันทึกเป้าหมายการผูกบัญชีถ้ามีส่งมา (ใช้กรณีจองสำเร็จ)
    if (!empty($_GET['action']) && $_GET['action'] === 'link' && !empty($_GET['tenant_id'])) {
        $_SESSION['line_login_action'] = 'link';
        $_SESSION['line_login_target_tenant'] = $_GET['tenant_id'];
        
        if (!empty($_GET['room'])) {
            $_SESSION['line_login_room_back'] = $_GET['room'];
        }
    } else {
        $_SESSION['line_login_action'] = 'login';
    }
    
    // สร้าง state token
    $state = bin2hex(random_bytes(32));
    $_SESSION['line_state'] = $state;
    
    // สร้าง LINE Login URL (v2.1)
    $authUrl = 'https://access.line.me/oauth2/v2.1/authorize?' . http_build_query([
        'response_type' => 'code',
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'state' => $state,
        'scope' => 'profile openid',
        'prompt' => 'consent' // หรือเอาออกเพื่อให้ขอ consent เฉพาะครั้งแรก
    ]);
    
    header('Location: ' . $authUrl);
    exit;
    
} catch (PDOException $e) {
    header('Location: Login.php?error=' . urlencode('เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล'));
    exit;
}
