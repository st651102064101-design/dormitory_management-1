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

    // 3. จัดการผูกบัญชี (ผูก LINE เข้ากับ Tenant)
    if (($_SESSION['line_login_action'] ?? '') === 'link' && !empty($_SESSION['line_login_target_tenant'])) {
        $targetTenantId = $_SESSION['line_login_target_tenant'];
        
        $stmtUpdate = $pdo->prepare("UPDATE tenant SET line_user_id = ?, is_weather_alert_enabled = 1 WHERE tnt_id = ?");
        $stmtUpdate->execute([$lineUserId, $targetTenantId]);
        
        // ล็อกอินให้ทันทีหลังจากผูกเสร็จ
        $stmtTenant = $pdo->prepare("SELECT tnt_name, tnt_phone FROM tenant WHERE tnt_id = ? LIMIT 1");
        $stmtTenant->execute([$targetTenantId]);
        $tntData = $stmtTenant->fetch(PDO::FETCH_ASSOC);
        if ($tntData) {
            $_SESSION['tenant_logged_in'] = true;
            $_SESSION['tenant_id'] = $targetTenantId;
            $_SESSION['tenant_name'] = $tntData['tnt_name'];
            $_SESSION['tenant_phone'] = $tntData['tnt_phone'];
            
            try {
                require_once __DIR__ . '/LineHelper.php';
                
                $stmtC = $pdo->prepare("SELECT ctr_id, ctr_status, access_token, ctr_start FROM contract WHERE tnt_id = ? AND ctr_status != '1' ORDER BY ctr_id DESC LIMIT 1");
                $stmtC->execute([$targetTenantId]);
                $contract = $stmtC->fetch(PDO::FETCH_ASSOC);
                
                if ($contract) {
                    $billCount = 0;
                    $billStmt = $pdo->prepare("SELECT COUNT(*) FROM expense e INNER JOIN ( SELECT MAX(exp_id) AS exp_id FROM expense WHERE ctr_id = ? GROUP BY exp_month ) latest ON e.exp_id = latest.exp_id WHERE e.ctr_id = ? AND DATE_FORMAT(e.exp_month, '%Y-%m') >= DATE_FORMAT(?, '%Y-%m') AND DATE_FORMAT(e.exp_month, '%Y-%m') <= DATE_FORMAT(CURDATE(), '%Y-%m') AND ( e.exp_month = (SELECT MIN(e2.exp_month) FROM expense e2 WHERE e2.ctr_id = e.ctr_id) OR EXISTS ( SELECT 1 FROM utility u WHERE u.ctr_id = e.ctr_id AND YEAR(u.utl_date) = YEAR(e.exp_month) AND MONTH(u.utl_date) = MONTH(e.exp_month) AND u.utl_water_end IS NOT NULL AND u.utl_elec_end IS NOT NULL ) ) AND COALESCE(( SELECT SUM(p.pay_amount) FROM payment p WHERE p.exp_id = e.exp_id AND p.pay_status IN ('0','1') ), 0) < e.exp_total");
                    $billStmt->execute([$contract['ctr_id'], $contract['ctr_id'], $contract['ctr_start'] ?? date('Y-m-d')]);
                    $billCount = (int)($billStmt->fetchColumn() ?? 0);

                    $homeBadgeCount = 0;
                    $homeBadgeStmt = $pdo->prepare("SELECT 1 FROM contract c LEFT JOIN signature_logs sl ON c.ctr_id = sl.contract_id AND sl.signer_type = 'tenant' LEFT JOIN tenant_workflow tw ON c.tnt_id = tw.tnt_id WHERE c.ctr_id = ? AND c.ctr_status != '1' AND tw.current_step >= 3 AND sl.id IS NULL LIMIT 1");
                    $homeBadgeStmt->execute([$contract['ctr_id']]);
                    if ($homeBadgeStmt->fetchColumn()) { $homeBadgeCount = 1; }
                    
                    $msg = "🎉 ยินดีต้อนรับคุณ {$tntData['tnt_name']}!\nบัญชี LINE ของคุณเชื่อมต่อกับระบบหอพักสำเร็จแล้ว\n\n";
                    if ($billCount > 0) { $msg .= "⚠️ คุณมียอดค้างชำระ: {$billCount} รายการ\n"; } else { $msg .= "✅ คุณไม่มียอดค้างชำระ\n"; }
                    if ($homeBadgeCount > 0) { $msg .= "📝 แจ้งเตือนสำคัญ:\nคุณมีสัญญาเช่าที่ยังไม่ได้เซ็นชื่อ โปรดเข้าสู่ระบบเพื่อดำเนินการเซ็นสัญญา\n"; }
                    
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $dashUrl = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['PHP_SELF'] ?? '/line_callback.php') . '/Tenant/index.php?token=' . urlencode($contract['access_token']);
                    $msg .= "\n📱 เข้าสู่ระบบจัดการผู้เช่า (Dashboard):\n{$dashUrl}";
                    
                    if (function_exists('sendLineMulticast')) { sendLineMulticast($pdo, [$lineUserId], $msg); }
                }
            } catch (Exception $e) { error_log("LINE MultiCast Error: " . $e->getMessage()); }
        }
        
        // ล้างค่า
        $linkedRoom = $_SESSION['line_login_room_back'] ?? '';
        unset($_SESSION['line_login_action'], $_SESSION['line_login_target_tenant'], $_SESSION['line_login_room_back']);

        if (!empty($linkedRoom)) {
            header('Location: Public/booking.php?room=' . urlencode((string)$linkedRoom) . '&success=1&line_linked=1');
            exit;
        }

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
        // หาไม่เจอ ให้เด้งไปหน้าลงทะเบียนผูกบัญชี/สร้างบัญชีใหม่
        $_SESSION['line_register'] = [
            'line_id' => $lineUserId,
            'name' => $lineDisplayName,
            'email' => $profileData['email'] ?? '',
            'picture' => $profileData['pictureUrl'] ?? '',
            'phone' => '' // LINE doesn't provide phone number easily without extra scopes
        ];
        header('Location: line_register.php');
        exit;
    }

} catch (Exception $e) {
    header('Location: Login.php?error=' . urlencode('พบข้อผิดพลาดของระบบ'));
    exit;
}
