<?php
/**
 * Google OAuth Login Initiator
 * เริ่มต้นกระบวนการ OAuth กับ Google
 */
declare(strict_types=1);
session_start();
require_once __DIR__ . '/ConnectDB.php';

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
    
    // สร้าง full redirect URI
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $fullRedirectUri = $protocol . '://' . $host . $redirectUri;
    
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
