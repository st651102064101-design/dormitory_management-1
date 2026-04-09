<?php
/**
 * LINE Callback Handler
 * จัดการเมื่อใช้งาน LINE Login
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

    $basePath = dirname($_SERVER['PHP_SELF']);
    if ($basePath === '/' || $basePath === '\\') {
        $basePath = '';
    }

    return $protocol . '://' . $httpHost . $basePath . '/line_callback.php';
}

$error = $_GET['error'] ?? '';
$errorDescription = $_GET['error_description'] ?? '';

if ($error) {
    header('Location: Login.php?error=' . urlencode('LINE Login ยกเลิก: ' . $errorDescription));
    exit;
}

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

if (empty($code) || empty($state)) {
    header('Location: Login.php?error=' . urlencode('พารามิเตอร์ไม่ครบถ้วนจาก LINE'));
    exit;
}

$savedState = $_SESSION['line_state'] ?? '';
if ($state !== $savedState || empty($savedState)) {
    header('Location: Login.php?error=' . urlencode('การเชื่อมต่อไม่ถูกต้อง (State mismatch)'));
    exit;
}

unset($_SESSION['line_state']);

try {
    $pdo = connectDB();
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('line_login_channel_id', 'line_login_channel_secret')");
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    $clientId = $settings['line_login_channel_id'] ?? '';
    $clientSecret = $settings['line_login_channel_secret'] ?? '';
    
    if (empty($clientId) || empty($clientSecret)) {
        header('Location: Login.php?error=' . urlencode('ยังไม่ได้ตั้งค่า LINE Login ในระบบ'));
        exit;
    }

    $redirectUri = buildLineRedirectUri();

    // 1. Get Access Token
    $ch = curl_init('https://api.line.me/oauth2/v2.1/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $redirectUri,
        'client_id' => $clientId,
        'client_secret' => $clientSecret
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $tokenData = json_decode((string)$response, true);

    if ($httpCode !== 200 || empty($tokenData['access_token'])) {
        header('Location: Login.php?error=' . urlencode('ไม่สามารถขอ Token จาก LINE ได้'));
        exit;
    }

    // 2. Get User Profile using access token
    $chProfile = curl_init('https://api.line.me/v2/profile');
    curl_setopt($chProfile, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $tokenData['access_token']
    ]);
    curl_setopt($chProfile, CURLOPT_RETURNTRANSFER, true);
    $profileRes = curl_exec($chProfile);
    $profileHttpCode = curl_getinfo($chProfile, CURLINFO_HTTP_CODE);
    curl_close($chProfile);

    $profileData = json_decode((string)$profileRes, true);

    if ($profileHttpCode !== 200 || empty($profileData['userId'])) {
        header('Location: Login.php?error=' . urlencode('ไม่สามารถอ่านข้อมูลโปร์ไฟล์จาก LINE'));
        exit;
    }

    $lineUserId = $profileData['userId'];
    $lineDisplayName = $profileData['displayName'] ?? 'ผู้ใช้ LINE';

    // 3. จัดการผูกบัชญี (ผูก LINE เข้ากับ Tenant)
    if (($_SESSION['line_login_action'] ?? '') === 'link' && !empty($_SESSION['line_login_target_tenant'])) {
        $targetTenantId = $_SESSION['line_login_target_tenant'];
        
        $stmtUpdate = $pdo->prepare("UPDATE tenant SET line_user_id = ?, is_weather_alert_enabled = 1 WHERE tnt_id = ?");
        $stmtUpdate->execute([$lineUserId, $targetTenantId]);
        
        // ล้างค่า
        unset($_SESSION['line_login_action'], $_SESSION['line_login_target_tenant']);

        // วนกลับหน้าตรวจสอบสถานะจอง / ผู้เช่า
        // สมมุติว่าดึง ref ย้อนกลับจาก booking success
        $lastBookingId = $_SESSION['last_booking_id'] ?? '';
        $lastPhoneNumber = $_SESSION['last_phone_number'] ?? '';
        
        if ($lastBookingId && $lastPhoneNumber) {
            header('Location: Public/booking_status.php?ref=' . urlencode((string)$lastBookingId) . '&phone=' . urlencode((string)$lastPhoneNumber) . '&auto=1');
            exit;
        } else {
            // ถ้าย้อนกลับจาก Tenant/index.php
            header('Location: Tenant/index.php');
            exit;
        }
    } 

    // กรณีไม่ใช่การผูกตรงๆ อาจจะทำเป็นระบบ Login แบบเดียวกับ Google
    // สมมติว่าพยายามหาข้อมูล Tenant จาก line_user_id
    $stmtLogin = $pdo->prepare("SELECT tnt_id, tnt_name, tnt_phone FROM tenant WHERE line_user_id = ? LIMIT 1");
    $stmtLogin->execute([$lineUserId]);
    $tenantData = $stmtLogin->fetch(PDO::FETCH_ASSOC);
    
    if ($tenantData) {
        $_SESSION['tenant_logged_in'] = true;
        $_SESSION['tenant_id'] = $tenantData['tnt_id'];
        $_SESSION['tenant_name'] = $tenantData['tnt_name'];
        $_SESSION['tenant_phone'] = $tenantData['tnt_phone'];
        header('Location: Tenant/index.php');
        exit;
    } else {
        // หาไม่เจอ
        header('Location: Login.php?error=' . urlencode('บัญชี LINE ของคุณยังไม่ได้ผูกกับผู้เช่าหอพักใดๆ กรุณาล็อกอินด้วยเบอร์แล้วค่อยกดผูก LINE ภายหลัง'));
        exit;
    }

} catch (Exception $e) {
    header('Location: Login.php?error=' . urlencode('พบข้อผิดพลาดของระบบ'));
    exit;
}
