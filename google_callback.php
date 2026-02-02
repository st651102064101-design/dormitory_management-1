<?php
/**
 * Google OAuth Callback
 * รับ callback จาก Google หลังจากผู้ใช้ยืนยันการเข้าสู่ระบบ
 * ตรวจสอบอัตโนมัติว่าเป็น Admin หรือ Tenant
 */

// Set error handling
$debugMode = isset($_GET['debug']) && $_GET['debug'] === '1';
ini_set('display_errors', $debugMode ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/ConnectDB.php';

// ฟังก์ชันสำหรับ redirect พร้อม error
function redirectWithError($error) {
    error_log("Redirecting with error: " . $error);
    header('Location: /dormitory_management/Login.php?google_error=' . urlencode($error));
    exit;
}

// ดึงค่าตั้งค่า Google OAuth จากฐานข้อมูล
function getGoogleSettings($pdo) {
    $settings = [];
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('google_client_id', 'google_client_secret', 'google_redirect_uri')");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {
        error_log("Error getting Google settings: " . $e->getMessage());
    }
    return $settings;
}

try {
    error_log("=== Google Callback Started ===");
    
    $pdo = connectDB();
    error_log("✓ Database connected");
    
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
    
    error_log("Full redirect URI: " . $fullRedirectUri);
    
    // ตรวจสอบว่ามี error จาก Google หรือไม่
    if (isset($_GET['error'])) {
        error_log("Google error: " . $_GET['error']);
        redirectWithError('Google ปฏิเสธการเข้าถึง: ' . $_GET['error']);
    }
    
    // ตรวจสอบว่ามี code หรือไม่
    if (!isset($_GET['code'])) {
        error_log("No code from Google");
        redirectWithError('ไม่พบรหัสยืนยันจาก Google');
    }
    
    error_log("✓ Got code from Google");
    
    // ตรวจสอบ state เพื่อป้องกัน CSRF
    if (!isset($_GET['state']) || !isset($_SESSION['google_state']) || $_GET['state'] !== $_SESSION['google_state']) {
        error_log("Invalid state token");
        redirectWithError('Invalid state token');
    }
    
    error_log("✓ State token verified");
    
    $code = $_GET['code'];
    
    // Exchange code for access token
    if (!function_exists('curl_init')) {
        redirectWithError('เซิร์ฟเวอร์ไม่ได้ติดตั้ง cURL');
    }
    $tokenUrl = 'https://oauth2.googleapis.com/token';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tokenUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $fullRedirectUri,
        'grant_type' => 'authorization_code'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("Token exchange failed: HTTP $httpCode");
        error_log("Response: " . $response);
        redirectWithError('ไม่สามารถแลกเปลี่ยน code เป็น token ได้');
    }
    
    error_log("✓ Got access token");
    
    $tokenData = json_decode($response, true);
    
    if (!isset($tokenData['access_token'])) {
        error_log("No access token in response");
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
        error_log("User info fetch failed: HTTP $httpCode");
        redirectWithError('ไม่สามารถดึงข้อมูลผู้ใช้จาก Google ได้');
    }
    
    error_log("✓ Got user info from Google");
    
    $userData = json_decode($userResponse, true);
    if (!isset($userData['id']) || !isset($userData['email'])) {
        error_log("Incomplete user data: " . json_encode($userData));
        redirectWithError('ข้อมูลผู้ใช้จาก Google ไม่ครบถ้วน');
    }
    
    $googleId = $userData['id'];
    $email = $userData['email'];
    $name = $userData['name'] ?? '';
    $picture = $userData['picture'] ?? '';
    $phone = $userData['phone_number'] ?? ($userData['phone'] ?? '');
    
    error_log("User: $email (ID: $googleId)");
    
    // ล้างค่า state จาก session
    unset($_SESSION['google_state']);
    
    // =============================================
    // ตรวจสอบว่าเป็นการ Link Account หรือไม่
    // =============================================
    if (!empty($_SESSION['google_link_mode']) && !empty($_SESSION['google_link_admin_id'])) {
        $adminId = $_SESSION['google_link_admin_id'];
        $action = $_SESSION['google_link_action'] ?? 'link';
        
        unset($_SESSION['google_link_mode']);
        unset($_SESSION['google_link_admin_id']);
        unset($_SESSION['google_link_action']);
        
        $checkStmt = $pdo->prepare('SELECT admin_id FROM admin_oauth WHERE provider = "google" AND provider_id = ? AND admin_id != ?');
        $checkStmt->execute([$googleId, $adminId]);
        
        if ($checkStmt->fetch()) {
            header('Location: /dormitory_management/Reports/dashboard.php?google_error=' . urlencode('บัญชี Google นี้ถูกเชื่อมกับบัญชีผู้ดูแลระบบอื่นแล้ว'));
            exit;
        }
        
        $checkTenantStmt = $pdo->prepare('SELECT tnt_id FROM tenant_oauth WHERE provider = "google" AND (provider_id = ? OR provider_email = ?)');
        $checkTenantStmt->execute([$googleId, $email]);
        if ($checkTenantStmt->fetch()) {
            header('Location: /dormitory_management/Reports/dashboard.php?google_error=' . urlencode('บัญชี Google นี้ถูกเชื่อมกับบัญชีผู้เช่าอยู่แล้ว ไม่สามารถใช้กับบัญชีผู้ดูแลระบบได้'));
            exit;
        }
        
        if ($action === 'relink') {
            $deleteStmt = $pdo->prepare('DELETE FROM admin_oauth WHERE admin_id = ? AND provider = "google"');
            $deleteStmt->execute([$adminId]);
        }
        
        $existingStmt = $pdo->prepare('SELECT oauth_id FROM admin_oauth WHERE admin_id = ? AND provider = "google"');
        $existingStmt->execute([$adminId]);
        $existingOAuth = $existingStmt->fetch();
        
        if ($existingOAuth) {
            $updateStmt = $pdo->prepare('
                UPDATE admin_oauth
                SET provider_id = ?, provider_email = ?, picture = ?, updated_at = NOW()
                WHERE admin_id = ? AND provider = "google"
            ');
            $updateStmt->execute([$googleId, $email, $picture, $adminId]);
            $message = 'เปลี่ยนบัญชี Google เป็น ' . $email . ' สำเร็จ';
        } else {
            $insertStmt = $pdo->prepare('
                INSERT INTO admin_oauth (admin_id, provider, provider_id, provider_email, picture, created_at, updated_at)
                VALUES (?, "google", ?, ?, ?, NOW(), NOW())
            ');
            $insertStmt->execute([$adminId, $googleId, $email, $picture]);
            $message = 'เชื่อมบัญชี Google ' . $email . ' สำเร็จ';
        }
        
        $_SESSION['admin_picture'] = $picture;
        header('Location: /dormitory_management/Reports/dashboard.php?google_success=' . urlencode($message));
        exit;
    }
    
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
        error_log("Admin found, updating OAuth");
        
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
        
        $_SESSION['admin_id'] = $admin['admin_id'];
        $_SESSION['admin_username'] = $admin['admin_username'];
        $_SESSION['admin_name'] = $admin['admin_name'] ?? '';
        $_SESSION['admin_picture'] = $admin['oauth_picture'] ?? $picture;
        
        error_log("✓ Admin logged in: " . $admin['admin_id']);
        
        header('Location: /dormitory_management/Reports/dashboard.php');
        exit;
    }
    
    // 2. ถ้าไม่ใช่ Admin, ตรวจสอบว่าเป็น Tenant หรือไม่
    // First try: ตรวจสอบ tenant_oauth
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
    
    // If not found in tenant_oauth, create new tenant_oauth record
    // This handles the case where customer is already registered but without OAuth
    if (!$tenant) {
        error_log("Tenant not found in tenant_oauth with email: $email");
        error_log("Google ID: $googleId, Name: $name, Phone: $phone");
        
        // Try to find by phone if available (common in Thai systems)
        if (!empty($phone)) {
            error_log("Searching tenant by phone: $phone");
            $stmt = $pdo->prepare('
                SELECT tnt_id, tnt_name, tnt_phone
                FROM tenant
                WHERE tnt_phone = ?
                LIMIT 1
            ');
            $stmt->execute([$phone]);
            $existingTenant = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingTenant) {
                error_log("Found tenant by phone: " . $existingTenant['tnt_id']);
                $tenantId = $existingTenant['tnt_id'];
                
                // Insert into tenant_oauth
                $insertStmt = $pdo->prepare('
                    INSERT INTO tenant_oauth (tnt_id, provider, provider_id, provider_email, created_at, updated_at)
                    VALUES (?, "google", ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE 
                        provider_id = VALUES(provider_id),
                        provider_email = VALUES(provider_email),
                        updated_at = NOW()
                ');
                try {
                    $insertStmt->execute([$tenantId, $googleId, $email]);
                    error_log("OAuth record created for tenant: $tenantId");
                } catch (PDOException $e) {
                    error_log("Error creating OAuth record: " . $e->getMessage());
                }
                
                // Set tenant variable with existing tenant data
                $tenant = $existingTenant;
                $tenant['oauth_picture'] = $picture;
            } else {
                error_log("No tenant found with phone: $phone");
            }
        } else {
            error_log("Phone number not provided from Google, skipping phone lookup");
        }
    }
    
    if ($tenant) {
        error_log("Tenant found: " . $tenant['tnt_id']);
        
        // ตรวจสอบว่า OAuth record มีอยู่หรือไม่
        $checkOAuthStmt = $pdo->prepare('
            SELECT oauth_id FROM tenant_oauth 
            WHERE tnt_id = ? AND provider = "google"
        ');
        $checkOAuthStmt->execute([$tenant['tnt_id']]);
        $existingOAuth = $checkOAuthStmt->fetch();
        
        if (!$existingOAuth) {
            error_log("Creating new OAuth record for: " . $tenant['tnt_id']);
            // Insert new OAuth record if doesn't exist
            $insertOAuthStmt = $pdo->prepare('
                INSERT INTO tenant_oauth (tnt_id, provider, provider_id, provider_email, created_at, updated_at)
                VALUES (?, "google", ?, ?, NOW(), NOW())
            ');
            try {
                $insertOAuthStmt->execute([$tenant['tnt_id'], $googleId, $email]);
            } catch (PDOException $e) {
                error_log("Error inserting OAuth: " . $e->getMessage());
            }
        } else {
            error_log("Updating existing OAuth record for: " . $tenant['tnt_id']);
        }
        
        // Update OAuth record
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
        $_SESSION['tenant_phone'] = $tenant['tnt_phone'] ?? '';
        $_SESSION['tenant_email'] = $email;
        $_SESSION['tenant_picture'] = $tenant['oauth_picture'] ?? $picture;
        $_SESSION['tenant_logged_in'] = true;
        
        error_log("✓ Tenant session set: " . $_SESSION['tenant_id']);
        // หลังล็อกอิน Google ให้ไปหน้าตรวจสอบสถานะการจอง
        header('Location: /dormitory_management/Public/booking_status.php?auto=1');
        exit;
    }
    
    // 3. ไม่พบทั้ง Admin และ Tenant - redirect ไปหน้าลงทะเบียน (สำหรับ Tenant ใหม่)
    error_log("No tenant found, redirecting to registration");
    $_SESSION['google_register'] = [
        'google_id' => $googleId,
        'email' => $email,
        'name' => $name,
        'picture' => $picture,
        'phone' => $phone
    ];
    header('Location: /dormitory_management/google_register.php');
    exit;
    
} catch (PDOException $e) {
    error_log("PDOException in google_callback.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    redirectWithError('Database Error: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Exception in google_callback.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    redirectWithError('Error: ' . $e->getMessage());
}
