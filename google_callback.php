<?php
ob_start();  // ✅ ต้องเป็นบรรทัดแรกสุด — ป้องกัน "headers already sent" จาก warning/notice ใดๆ
/**
 * Google OAuth Callback
 * รับ callback จาก Google หลังจากผู้ใช้ยืนยันการเข้าสู่ระบบ
 * ตรวจสอบอัตโนมัติว่าเป็น Admin หรือ Tenant
 */

// Set error handling
$debugMode = isset($_GET['debug']) && $_GET['debug'] === '1';
ini_set('display_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

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

// ฟังก์ชันสำหรับ redirect พร้อม ob_clean
function safeRedirect($url) {
    if (ob_get_level()) ob_clean();
    header('Location: ' . $url);
    exit;
}

// ฟังก์ชันสำหรับ redirect พร้อม error
function redirectWithError($error) {
    error_log("Redirecting with error: " . $error);
    if (ob_get_level()) ob_clean();
    
    // ✅ ส่งข้อมูลข้อผิดพลาดไปยังหน้าหลัก (รองรับทั้ง popup และ redirect)
    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>เกิดข้อผิดพลาด</title>
</head>
<body>
    <script>
        if (window.opener) {
            window.opener.postMessage({
                type: "google_link_error",
                message: "' . addslashes($error) . '"
            }, "*");
            setTimeout(() => { window.close(); }, 1000);
        } else {
            setTimeout(() => { window.location.href = "/dormitory_management/index.php"; }, 3000);
        }
    </script>
    <p style="color: red; font-family: Arial;">เกิดข้อผิดพลาด: ' . htmlspecialchars($error) . '</p>
    <p style="font-family: Arial; font-size: 13px; color: #666;">กำลังกลับหน้าหลัก...</p>
</body>
</html>';
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
    // ✅ แสดงข้อความให้ผู้ใช้ทราบว่ากำลังประมวลผล
    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>กำลังประมวลผล Google OAuth...</title>
    <style>
        body { 
            margin: 0; 
            padding: 0; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            background: #f5f5f5; 
            font-family: Arial, sans-serif; 
        }
        .container { text-align: center; }
        .spinner { 
            border: 4px solid #f3f3f3; 
            border-top: 4px solid #3498db; 
            border-radius: 50%; 
            width: 50px; 
            height: 50px; 
            animation: spin 1s linear infinite; 
            margin: 20px auto; 
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        p { color: #666; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="spinner"></div>
        <p>กำลังตรวจสอบข้อมูล Google...</p>
    </div>
</body>
</html>';
    // ไม่ flush เพราะต้องรอ header redirect — buffer จะถูก clean ก่อน redirect
    
    error_log("=== Google Callback Started ===");
    
    $pdo = connectDB();
    error_log("✓ Database connected");
    
    // ✅ Ensure google_oauth_state table exists
    try {
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS google_oauth_state (
                id INT AUTO_INCREMENT PRIMARY KEY,
                state VARCHAR(255) NOT NULL UNIQUE,
                admin_id INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_state (state),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    } catch (Exception $e) {
        error_log("Warning: Could not ensure google_oauth_state table exists: " . $e->getMessage());
    }
    
    $settings = getGoogleSettings($pdo);
    
    $clientId = $settings['google_client_id'] ?? '';
    $clientSecret = $settings['google_client_secret'] ?? '';
    $redirectUri = $settings['google_redirect_uri'] ?? '';
    
    if (empty($clientId) || empty($clientSecret)) {
        redirectWithError('Google OAuth ยังไม่ได้ตั้งค่า');
    }
    
    // ใช้ redirect URI เดียวกับตอน authorize
    $fullRedirectUri = $_SESSION['google_redirect_uri'] ?? buildGoogleRedirectUri($redirectUri);
    
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
    
    // ✅ ตรวจสอบ state เพื่อป้องกัน CSRF (ค้นหาจาก database เพื่อรองรับ popup)
    if (!isset($_GET['state'])) {
        error_log("No state provided from Google");
        redirectWithError('Invalid state token');
    }
    
    $stateFromGoogle = $_GET['state'];
    
    // ตรวจสอบจาก database ก่อน (สำหรับ popup scenario)
    $stateValid = false;
    try {
        $stateCheckStmt = $pdo->prepare('
            SELECT id, admin_id FROM google_oauth_state 
            WHERE state = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            LIMIT 1
        ');
        $stateCheckStmt->execute([$stateFromGoogle]);
        $stateRecord = $stateCheckStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($stateRecord) {
            $stateValid = true;
            error_log("✓ State token verified from database");
            
            // ลบ state token ที่ใช้แล้ว
            $deleteStateStmt = $pdo->prepare('DELETE FROM google_oauth_state WHERE id = ?');
            $deleteStateStmt->execute([$stateRecord['id']]);
        }
    } catch (Exception $e) {
        error_log("Warning: Could not check database for state token: " . $e->getMessage());
    }
    
    // ถ้าไม่พบในฐานข้อมูล ให้ตรวจสอบจาก session (compatibility)
    if (!$stateValid && isset($_SESSION['google_state']) && $stateFromGoogle === $_SESSION['google_state']) {
        $stateValid = true;
        error_log("✓ State token verified from session");
    }
    
    if (!$stateValid) {
        error_log("Invalid state token: $stateFromGoogle");
        redirectWithError('Invalid state token');
    }
    
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);  // ✅ Add 10 second timeout
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);  // ✅ Add 5 second connection timeout
    
    error_log("Exchanging code for access token...");
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // ✅ Check for cURL errors
    if ($curlError) {
        error_log("cURL error: $curlError");
        redirectWithError('cURL error: ' . $curlError);
    }
    
    if ($httpCode !== 200) {
        error_log("Token exchange failed: HTTP $httpCode");
        error_log("Response: " . $response);
        redirectWithError('ไม่สามารถแลกเปลี่ยน code เป็น token ได้ (HTTP ' . $httpCode . ')');
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);  // ✅ Add timeout
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);  // ✅ Add connection timeout
    
    error_log("Fetching user info from Google...");
    
    $userResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // ✅ Check for cURL errors
    if ($curlError) {
        error_log("cURL error fetching user info: $curlError");
        redirectWithError('cURL error: ' . $curlError);
    }
    
    if ($httpCode !== 200) {
        error_log("User info fetch failed: HTTP $httpCode");
        error_log("Response: " . $userResponse);
        redirectWithError('ไม่สามารถดึงข้อมูลผู้ใช้จาก Google ได้ (HTTP ' . $httpCode . ')');
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
    unset($_SESSION['google_redirect_uri']);
    
    // =============================================
    // ตรวจสอบว่าเป็นการ Link Account หรือไม่
    // =============================================
    if (!empty($_SESSION['google_link_mode']) && !empty($_SESSION['google_link_admin_id'])) {
        error_log("✓ Detected Google link mode");
        $adminId = $_SESSION['google_link_admin_id'];
        $action = $_SESSION['google_link_action'] ?? 'link';
        
        error_log("Admin ID: $adminId, Action: $action");
        
        unset($_SESSION['google_link_mode']);
        unset($_SESSION['google_link_admin_id']);
        unset($_SESSION['google_link_action']);
        
        // ✓ เช็คว่า Gmail นี้ถูกใช้โดย admin อื่นแล้วหรือไม่
        $checkStmt = $pdo->prepare('SELECT admin_id FROM admin_oauth WHERE provider = "google" AND provider_email = ? AND admin_id != ?');
        $checkStmt->execute([$email, $adminId]);
        
        if ($checkStmt->fetch()) {
            safeRedirect('/dormitory_management/Reports/dashboard.php?google_error=' . urlencode('อีเมล Google นี้ถูกเชื่อมกับบัญชีผู้ดูแลระบบอื่นแล้ว'));
        }
        
        // ✓ เช็คว่า Gmail นี้ถูกใช้โดย tenant แล้วหรือไม่
        $checkTenantStmt = $pdo->prepare('SELECT tnt_id FROM tenant_oauth WHERE provider = "google" AND provider_email = ?');
        $checkTenantStmt->execute([$email]);
        if ($checkTenantStmt->fetch()) {
            safeRedirect('/dormitory_management/Reports/dashboard.php?google_error=' . urlencode('อีเมล Google นี้ถูกเชื่อมกับบัญชีผู้เช่าอยู่แล้ว ไม่สามารถใช้กับบัญชีผู้ดูแลระบบได้'));
        }
        
        // ✓ เช็คว่า Google ID นี้ถูกใช้โดย user อื่นแล้วหรือไม่ (admin หรือ tenant)
        $checkGoogleIdAdmin = $pdo->prepare('SELECT admin_id FROM admin_oauth WHERE provider = "google" AND provider_id = ? AND admin_id != ?');
        $checkGoogleIdAdmin->execute([$googleId, $adminId]);
        if ($checkGoogleIdAdmin->fetch()) {
            safeRedirect('/dormitory_management/Reports/dashboard.php?google_error=' . urlencode('บัญชี Google นี้ถูกเชื่อมกับบัญชีผู้ดูแลระบบอื่นแล้ว'));
        }
        
        $checkGoogleIdTenant = $pdo->prepare('SELECT tnt_id FROM tenant_oauth WHERE provider = "google" AND provider_id = ?');
        $checkGoogleIdTenant->execute([$googleId]);
        if ($checkGoogleIdTenant->fetch()) {
            safeRedirect('/dormitory_management/Reports/dashboard.php?google_error=' . urlencode('บัญชี Google นี้ถูกเชื่อมกับบัญชีผู้เช่าอยู่แล้ว ไม่สามารถใช้กับบัญชีผู้ดูแลระบบได้'));
        }
        
        if ($action === 'relink') {
            $deleteStmt = $pdo->prepare('DELETE FROM admin_oauth WHERE admin_id = ? AND provider = "google"');
            $deleteStmt->execute([$adminId]);
        }
        
        $existingStmt = $pdo->prepare('SELECT provider_id FROM admin_oauth WHERE admin_id = ? AND provider = "google"');
        $existingStmt->execute([$adminId]);
        $existingOAuth = $existingStmt->fetch();
        
        if ($existingOAuth) {
            error_log("Updating existing OAuth link for admin $adminId");
            $updateStmt = $pdo->prepare('
                UPDATE admin_oauth
                SET provider_id = ?, provider_email = ?, picture = ?, updated_at = NOW()
                WHERE admin_id = ? AND provider = "google"
            ');
            $updateStmt->execute([$googleId, $email, $picture, $adminId]);
            $message = 'เปลี่ยนบัญชี Google เป็น ' . $email . ' สำเร็จ';
            error_log("✓ OAuth link updated: " . $message);
        } else {
            error_log("Creating new OAuth link for admin $adminId with email $email");
            $insertStmt = $pdo->prepare('
                INSERT INTO admin_oauth (admin_id, provider, provider_id, provider_email, picture, created_at, updated_at)
                VALUES (?, "google", ?, ?, ?, NOW(), NOW())
            ');
            $insertStmt->execute([$adminId, $googleId, $email, $picture]);
            $message = 'เชื่อมบัญชี Google ' . $email . ' สำเร็จ';
            error_log("✓ OAuth link created: " . $message);
        }
        
        $_SESSION['admin_picture'] = $picture;
        
        // ✓ ปิด popup โดยอัตโนมัติ (สำหรับการเชื่อม Google)
        error_log("Sending auto-close response to popup for email: $email");
        echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>เชื่อมบัญชี Google</title>
</head>
<body>
    <script>
        // ส่งข้อมูลสำเร็จไปยังหน้าหลัก
        if (window.opener) {
            window.opener.postMessage({
                type: "google_link_success",
                email: "' . addslashes($email) . '",
                message: "' . addslashes($message) . '"
            }, "*");
            setTimeout(() => { window.close(); }, 100);
        } else {
            window.location.href = "/dormitory_management/Reports/dashboard.php";
        }
    </script>
</body>
</html>';
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
        $_SESSION['last_activity'] = time();
        
        error_log("✓ Admin logged in: " . $admin['admin_id']);
        
        safeRedirect('/dormitory_management/Reports/dashboard.php');
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
        
        // ✓ เช็คว่า Gmail นี้ถูกใช้โดย tenant อื่นแล้วหรือไม่
        $checkDupEmailStmt = $pdo->prepare('
            SELECT tnt_id FROM tenant_oauth 
            WHERE tnt_id != ? AND provider = "google" AND provider_email = ?
        ');
        $checkDupEmailStmt->execute([$tenant['tnt_id'], $email]);
        if ($checkDupEmailStmt->fetch()) {
            redirectWithError('อีเมล Google นี้ถูกเชื่อมกับบัญชีผู้เช่าอื่นแล้ว');
        }
        
        // ✓ เช็คว่า Google ID นี้ถูกใช้โดย tenant อื่นแล้วหรือไม่
        $checkDupGoogleIdStmt = $pdo->prepare('
            SELECT tnt_id FROM tenant_oauth 
            WHERE tnt_id != ? AND provider = "google" AND provider_id = ?
        ');
        $checkDupGoogleIdStmt->execute([$tenant['tnt_id'], $googleId]);
        if ($checkDupGoogleIdStmt->fetch()) {
            redirectWithError('บัญชี Google นี้ถูกเชื่อมกับบัญชีผู้เช่าอื่นแล้ว');
        }
        
        // ✓ เช็คว่า Gmail นี้ถูกใช้โดย admin แล้วหรือไม่
        $checkAdminEmailStmt = $pdo->prepare('
            SELECT admin_id FROM admin_oauth 
            WHERE provider = "google" AND provider_email = ?
        ');
        $checkAdminEmailStmt->execute([$email]);
        if ($checkAdminEmailStmt->fetch()) {
            redirectWithError('อีเมล Google นี้ถูกเชื่อมกับบัญชีผู้ดูแลระบบแล้ว ไม่สามารถใช้กับบัญชีผู้เช่าได้');
        }
        
        // ✓ เช็คว่า Google ID นี้ถูกใช้โดย admin แล้วหรือไม่
        $checkAdminGoogleIdStmt = $pdo->prepare('
            SELECT admin_id FROM admin_oauth 
            WHERE provider = "google" AND provider_id = ?
        ');
        $checkAdminGoogleIdStmt->execute([$googleId]);
        if ($checkAdminGoogleIdStmt->fetch()) {
            redirectWithError('บัญชี Google นี้ถูกเชื่อมกับบัญชีผู้ดูแลระบบแล้ว ไม่สามารถใช้กับบัญชีผู้เช่าได้');
        }
        
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
        error_log("✓ Session data: " . json_encode($_SESSION, JSON_UNESCAPED_UNICODE));
        
        // Save session before redirect
        session_write_close();
        
        // หลังล็อกอิน Google ให้กลับหน้า index
        safeRedirect('/dormitory_management/index.php');
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
    safeRedirect('/dormitory_management/google_register.php');
    
} catch (PDOException $e) {
    error_log("PDOException in google_callback.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    redirectWithError('Database Error: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Exception in google_callback.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // ✅ แสดงข้อผิดพลาดและปิด popup
    $errorMsg = $e->getMessage();
    if (ob_get_level()) ob_clean();
    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>เกิดข้อผิดพลาด</title>
</head>
<body>
    <script>
        if (window.opener) {
            window.opener.postMessage({
                type: "google_link_error",
                message: "' . addslashes($errorMsg) . '"
            }, "*");
            setTimeout(() => { window.close(); }, 1000);
        } else {
            setTimeout(() => { window.location.href = "/dormitory_management/index.php"; }, 3000);
        }
    </script>
    <p style="color: red; font-family: Arial;">เกิดข้อผิดพลาด: ' . htmlspecialchars($errorMsg) . '</p>
    <p style="font-family: Arial; font-size: 13px; color: #666;">กำลังกลับหน้าหลัก...</p>
</body>
</html>';
    exit;
}
