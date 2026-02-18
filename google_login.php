<?php
/**
 * Google OAuth Login Initiator
 * เริ่มต้นกระบวนการ OAuth กับ Google
 * ตรวจสอบอัตโนมัติว่าเป็น Admin หรือ Tenant
 */
declare(strict_types=1);
session_start();
require_once __DIR__ . '/ConnectDB.php';

function buildGoogleRedirectUri(string $redirectUri): string {
    $redirectUri = trim($redirectUri);
    if ($redirectUri === '') {
        $redirectUri = '/dormitory_management/google_callback.php';
    }

    if (preg_match('#^https?://#i', $redirectUri) === 1) {
        return $redirectUri;
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $serverName = trim((string)($_SERVER['SERVER_NAME'] ?? ''));

    if ($serverName === '' || $serverName === '_') {
        $httpHost = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $serverName = explode(':', $httpHost)[0];
    }

    if ($serverName === '') {
        $serverName = 'localhost';
    }

    $serverPort = (int)($_SERVER['SERVER_PORT'] ?? 0);
    $portPart = '';
    if (($protocol === 'http' && $serverPort > 0 && $serverPort !== 80) || ($protocol === 'https' && $serverPort > 0 && $serverPort !== 443)) {
        $portPart = ':' . $serverPort;
    }

    if ($redirectUri[0] !== '/') {
        $redirectUri = '/' . $redirectUri;
    }

    return $protocol . '://' . $serverName . $portPart . $redirectUri;
}

// ดึงค่าตั้งค่า Google OAuth จากฐานข้อมูล
try {
    $pdo = connectDB();
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('google_client_id', 'google_redirect_uri')");
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    $clientId = $settings['google_client_id'] ?? '';
    $redirectUri = $settings['google_redirect_uri'] ?? '';
    
    if (empty($clientId)) {
        header('Location: Login.php?google_error=' . urlencode('Google OAuth ยังไม่ได้ตั้งค่า Client ID'));
        exit;
    }
    
    // สร้าง redirect URI แบบคงที่ (หลีกเลี่ยงพอร์ตสุ่มจาก HTTP_HOST)
    $fullRedirectUri = buildGoogleRedirectUri($redirectUri);
    $_SESSION['google_redirect_uri'] = $fullRedirectUri;
    
    // สร้าง state token เพื่อป้องกัน CSRF
    $state = bin2hex(random_bytes(32));
    $_SESSION['google_state'] = $state;
    
    // สร้าง Google OAuth URL
    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $fullRedirectUri,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'access_type' => 'online',
        'prompt' => 'select_account'
    ]);
    
    // Redirect ไปยัง Google
    header('Location: ' . $authUrl);
    exit;
    
} catch (PDOException $e) {
    header('Location: Login.php?google_error=' . urlencode('เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล'));
    exit;
}
