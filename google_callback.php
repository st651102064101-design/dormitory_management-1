<?php
/**
 * Google OAuth Callback
 * รับ callback จาก Google หลังจากผู้ใช้ยืนยันการเข้าสู่ระบบ
 * ตรวจสอบอัตโนมัติว่าเป็น Admin หรือ Tenant
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
    $phone = $userData['phone_number'] ?? ($userData['phone'] ?? ''); // พยายามดึงเบอร์โทร
    
    // =============================================
    // ตรวจสอบว่าเป็นการ Link Account หรือไม่
    // =============================================
    if (!empty($_SESSION['google_link_mode']) && !empty($_SESSION['google_link_admin_id'])) {
        $adminId = $_SESSION['google_link_admin_id'];
        $action = $_SESSION['google_link_action'] ?? 'link';
        
        // ล้าง session
        unset($_SESSION['google_link_mode']);
        unset($_SESSION['google_link_admin_id']);
        unset($_SESSION['google_link_action']);
        
        // ตรวจสอบว่า Google account นี้ถูกใช้กับ admin อื่นหรือไม่
        $checkStmt = $pdo->prepare('SELECT admin_id FROM admin_oauth WHERE provider = "google" AND provider_id = ? AND admin_id != ?');
        $checkStmt->execute([$googleId, $adminId]);
        if ($checkStmt->fetch()) {
            header('Location: Reports/dashboard.php?google_error=' . urlencode('บัญชี Google นี้ถูกเชื่อมกับบัญชีผู้ดูแลระบบอื่นแล้ว'));
            exit;
        }
        
        // ตรวจสอบว่า Google account นี้ถูกใช้กับ tenant หรือไม่
        $checkTenantStmt = $pdo->prepare('SELECT tnt_id FROM tenant_oauth WHERE provider = "google" AND (provider_id = ? OR provider_email = ?)');
        $checkTenantStmt->execute([$googleId, $email]);
        if ($checkTenantStmt->fetch()) {
            header('Location: Reports/dashboard.php?google_error=' . urlencode('บัญชี Google นี้ถูกเชื่อมกับบัญชีผู้เช่าอยู่แล้ว ไม่สามารถใช้กับบัญชีผู้ดูแลระบบได้'));
            exit;
        }
        
        // ถ้าเป็น relink ให้ลบ record เก่าก่อน
        if ($action === 'relink') {
            $deleteStmt = $pdo->prepare('DELETE FROM admin_oauth WHERE admin_id = ? AND provider = "google"');
            $deleteStmt->execute([$adminId]);
        }
        
        // ตรวจสอบว่ามี record อยู่แล้วหรือไม่
        $existingStmt = $pdo->prepare('SELECT oauth_id FROM admin_oauth WHERE admin_id = ? AND provider = "google"');
        $existingStmt->execute([$adminId]);
        $existingOAuth = $existingStmt->fetch();
        
        if ($existingOAuth) {
            // อัพเดท record ที่มีอยู่
            $updateStmt = $pdo->prepare('
                UPDATE admin_oauth 
                SET provider_id = ?, provider_email = ?, picture = ?, updated_at = NOW()
                WHERE admin_id = ? AND provider = "google"
            ');
            $updateStmt->execute([$googleId, $email, $picture, $adminId]);
            $message = 'เปลี่ยนบัญชี Google เป็น ' . $email . ' สำเร็จ';
        } else {
            // สร้าง record ใหม่
            $insertStmt = $pdo->prepare('
                INSERT INTO admin_oauth (admin_id, provider, provider_id, provider_email, picture, created_at, updated_at)
                VALUES (?, "google", ?, ?, ?, NOW(), NOW())
            ');
            $insertStmt->execute([$adminId, $googleId, $email, $picture]);
            $message = 'เชื่อมบัญชี Google ' . $email . ' สำเร็จ';
        }
        
        // อัพเดท session picture
        $_SESSION['admin_picture'] = $picture;
        
        header('Location: Reports/dashboard.php?google_success=' . urlencode($message));
        exit;
    }
    
    // ล้างค่า login type จาก session (ถ้ามี)
    unset($_SESSION['google_login_type']);
    
    // =============================================
    // ตรวจสอบอัตโนมัติ: Admin ก่อน, ถ้าไม่พบก็ตรวจสอบ Tenant
    // =============================================
    
    // 1. ตรวจสอบว่าเป็น Admin หรือไม่
    $stmt = $pdo->prepare('
        SELECT a.*, ao.picture as oauth_picture 
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
                picture = :picture,
                updated_at = NOW()
            WHERE admin_id = :admin_id AND provider = "google"
        ');
        $updateOAuthStmt->execute([
            ':google_id' => $googleId, 
            ':email' => $email,
            ':picture' => $picture,
            ':admin_id' => $admin['admin_id']
        ]);
        
        // ตั้งค่า session
        $_SESSION['admin_id'] = $admin['admin_id'];
        $_SESSION['admin_username'] = $admin['admin_username'];
        $_SESSION['admin_name'] = $admin['admin_name'] ?? '';
        // ใช้ picture จาก OAuth table ถ้ามี ถ้าไม่ใช้ picture จาก Google API
        $_SESSION['admin_picture'] = $admin['oauth_picture'] ?? $picture;
        
        // Redirect ไปหน้า dashboard
        header('Location: Reports/dashboard.php');
        exit;
    }
    
    // 2. ถ้าไม่ใช่ Admin, ตรวจสอบว่าเป็น Tenant หรือไม่
    $stmt = $pdo->prepare('
        SELECT t.*, tao.picture as oauth_picture 
        FROM tenant t
        INNER JOIN tenant_oauth tao ON t.tnt_id = tao.tnt_id
        WHERE tao.provider = "google" 
        AND (tao.provider_id = :google_id OR tao.provider_email = :email)
        LIMIT 1
    ');
    $stmt->execute([':google_id' => $googleId, ':email' => $email]);
    $tenant = $stmt->fetch();
    
    if ($tenant) {
        // อัพเดทข้อมูล OAuth ถ้ามีการเปลี่ยนแปลง
        $updateOAuthStmt = $pdo->prepare('
            UPDATE tenant_oauth 
            SET provider_id = :google_id, 
                provider_email = :email,
                picture = :picture,
                updated_at = NOW()
            WHERE tnt_id = :tnt_id AND provider = "google"
        ');
        $updateOAuthStmt->execute([
            ':google_id' => $googleId, 
            ':email' => $email,
            ':picture' => $picture,
            ':tnt_id' => $tenant['tnt_id']
        ]);
        
        // ตั้งค่า session สำหรับ tenant
        $_SESSION['tenant_id'] = $tenant['tnt_id'];
        $_SESSION['tenant_name'] = $tenant['tnt_name'] ?? '';
        // ใช้ picture จาก OAuth table ถ้ามี ถ้าไม่ใช้ picture จาก Google API
        $_SESSION['tenant_picture'] = $tenant['oauth_picture'] ?? $picture;
        $_SESSION['tenant_logged_in'] = true;
        
        // Redirect ไปหน้าหลัก
        header('Location: index.php');
        exit;
    }
    
    // 3. ไม่พบทั้ง Admin และ Tenant - redirect ไปหน้าลงทะเบียน (สำหรับ Tenant ใหม่)
    $_SESSION['google_register'] = [
        'google_id' => $googleId,
        'email' => $email,
        'name' => $name,
        'picture' => $picture,
        'phone' => $phone
    ];
    header('Location: google_register.php');
    exit;
    
} catch (PDOException $e) {
    // DEBUG: แสดง error จริง
    redirectWithError('DB Error: ' . $e->getMessage());
} catch (Exception $e) {
    redirectWithError('เกิดข้อผิดพลาด: ' . $e->getMessage());
}
