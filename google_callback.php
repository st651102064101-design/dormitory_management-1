<?php
/**
 * Google OAuth Callback
 * รับ callback จาก Google หลังจากผู้ใช้ยืนยันการเข้าสู่ระบบ
 */
declare(strict_types=1);
session_start();
require_once __DIR__ . '/ConnectDB.php';

// ฟังก์ชันสำหรับ redirect พร้อม error
function redirectWithError($error) {
    header('Location: Login.php?google_error=' . urlencode($error));
    exit;
}

// ดึงค่าตั้งค่า Google OAuth จากฐานข้อมูล
function getGoogleSettings($pdo) {
    $settings = [];
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('google_client_id', 'google_client_secret', 'google_redirect_uri')");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

try {
    $pdo = connectDB();
    $settings = getGoogleSettings($pdo);
    
    $clientId = $settings['google_client_id'] ?? '';
    $clientSecret = $settings['google_client_secret'] ?? '';
    $redirectUri = $settings['google_redirect_uri'] ?? '';
    
    if (empty($clientId) || empty($clientSecret)) {
        redirectWithError('Google OAuth ยังไม่ได้ตั้งค่า');
    }
    
    // สร้าง full redirect URI
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $fullRedirectUri = $protocol . '://' . $host . $redirectUri;
    
    // ตรวจสอบว่ามี error จาก Google หรือไม่
    if (isset($_GET['error'])) {
        redirectWithError('Google ปฏิเสธการเข้าถึง: ' . $_GET['error']);
    }
    
    // ตรวจสอบว่ามี code หรือไม่
    if (!isset($_GET['code'])) {
        redirectWithError('ไม่พบรหัสยืนยันจาก Google');
    }
    
    // ตรวจสอบ state เพื่อป้องกัน CSRF
    if (!isset($_GET['state']) || !isset($_SESSION['google_state']) || $_GET['state'] !== $_SESSION['google_state']) {
        redirectWithError('Invalid state token');
    }
    unset($_SESSION['google_state']);
    
    $code = $_GET['code'];
    
    // แลกเปลี่ยน code เป็น access token
    $tokenUrl = 'https://oauth2.googleapis.com/token';
    $tokenData = [
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $fullRedirectUri,
        'grant_type' => 'authorization_code'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tokenUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $tokenResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        redirectWithError('ไม่สามารถรับ token จาก Google ได้');
    }
    
    $tokenData = json_decode($tokenResponse, true);
    if (!isset($tokenData['access_token'])) {
        redirectWithError('ไม่พบ access token');
    }
    
    $accessToken = $tokenData['access_token'];
    
    // ดึงข้อมูลผู้ใช้จาก Google
    $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $userInfoUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $userResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        redirectWithError('ไม่สามารถดึงข้อมูลผู้ใช้จาก Google ได้');
    }
    
    $userData = json_decode($userResponse, true);
    if (!isset($userData['id']) || !isset($userData['email'])) {
        redirectWithError('ข้อมูลผู้ใช้จาก Google ไม่ครบถ้วน');
    }
    
    $googleId = $userData['id'];
    $email = $userData['email'];
    $name = $userData['name'] ?? '';
    $picture = $userData['picture'] ?? '';
    
    // ค้นหา admin จากตาราง admin_oauth
    $stmt = $pdo->prepare('
        SELECT a.* 
        FROM admin a
        INNER JOIN admin_oauth ao ON a.admin_id = ao.admin_id
        WHERE ao.provider = "google" 
        AND (ao.provider_id = :google_id OR ao.provider_email = :email)
        LIMIT 1
    ');
    $stmt->execute([':google_id' => $googleId, ':email' => $email]);
    $admin = $stmt->fetch();
    
    if ($admin) {
        // อัพเดทข้อมูล OAuth ถ้ามีการเปลี่ยนแปลง
        $updateOAuthStmt = $pdo->prepare('
            UPDATE admin_oauth 
            SET provider_id = :google_id, 
                provider_email = :email,
                updated_at = NOW()
            WHERE admin_id = :admin_id AND provider = "google"
        ');
        $updateOAuthStmt->execute([
            ':google_id' => $googleId, 
            ':email' => $email, 
            ':admin_id' => $admin['admin_id']
        ]);
        
        // ตั้งค่า session
        $_SESSION['admin_id'] = $admin['admin_id'];
        $_SESSION['admin_username'] = $admin['admin_username'];
        $_SESSION['admin_name'] = $admin['admin_name'] ?? '';
        $_SESSION['admin_picture'] = $picture;
        
        // Redirect ไปหน้า dashboard
        header('Location: Reports/dashboard.php');
        exit;
    } else {
        // ไม่พบ admin ที่ผูกกับบัญชี Google นี้
        redirectWithError('ไม่พบบัญชีผู้ดูแลที่ผูกกับ Google นี้ กรุณาติดต่อผู้ดูแลระบบเพื่อเพิ่มอีเมล ' . $email . ' ในระบบ');
    }
    
} catch (PDOException $e) {
    redirectWithError('เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล');
} catch (Exception $e) {
    redirectWithError('เกิดข้อผิดพลาด: ' . $e->getMessage());
}
